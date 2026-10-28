<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use AutoAltAi\AltTextGenerator\Dto\CreditInformation;
use AutoAltAi\AltTextGenerator\Dto\GenerateAltTextRequest;
use AutoAltAi\AltTextGenerator\Dto\GenerateAltTextResult;
use AutoAltAi\AltTextGenerator\Exception\AutoAltApiException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

final readonly class AutoAltApiService
{
    private const API_BASE_URL = 'https://ahxdfj.autoalt.ai/api/';
    private const GENERIC_REQUEST_FAILURE_MESSAGE = 'AutoAlt.ai API request failed. Please try again later.';
    private const PLUGIN_DATA_SYNC_TIMEOUT = 5;

    public function __construct(
        private RequestFactory $requestFactory,
        private ConfigurationService $configurationService,
        private ErrorLogService $errorLogService,
        private LoggerInterface $logger,
    ) {}

    public function verifyApiKey(?string $apiKey = null, string $websiteDomain = ''): bool
    {
        $key = $apiKey ?? $this->getApiKey();
        if ($key === '') {
            return false;
        }

        $response = $this->postJson('autoalt-verify-api-key', [
            'apiKey' => $key,
            'website_domain' => $websiteDomain,
            'framework' => 'TYPO3',
        ], apiKey: '');

        return (bool)($response['is_verify'] ?? false);
    }

    public function getCreditInformation(?string $apiKey = null, string $websiteDomain = ''): CreditInformation
    {
        $response = $this->postJson('autoalt-credit-count', [
            'apiKey' => $apiKey ?? $this->getApiKey(),
            'website_domain' => $websiteDomain,
        ], apiKey: $apiKey);

        return CreditInformation::fromApiResponse($response);
    }

    public function generateAltText(GenerateAltTextRequest $request, ?string $apiKey = null): GenerateAltTextResult
    {
        $response = $this->postJson('autoalt-generate-alt', $request->toPayload(), apiKey: $apiKey);

        return GenerateAltTextResult::fromApiResponse($response);
    }

    public function translate(string $text, string $targetLanguage, string $baseLanguage = 'en', string $websiteDomain = '', ?string $apiKey = null): ?string
    {
        $response = $this->postJson('autoalt-translate', [
            'word_org' => $text,
            'targetLang' => $targetLanguage,
            'baseLang' => $baseLanguage,
            'website_domain' => $websiteDomain,
        ], apiKey: $apiKey);

        $translated = trim((string)($response['return_string'] ?? ''));

        return $translated !== '' ? $translated : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function sendOtp(string $email, string $domain): array
    {
        return $this->postJson('autoalt-send-otp', [
            'email' => $email,
            'domain' => $domain,
        ], apiKey: '');
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyOtp(string $email, string $otp, string $domain): array
    {
        return $this->postJson('autoalt-verify-otp', [
            'email' => $email,
            'otp' => $otp,
            'domain' => $domain,
            'framework' => 'TYPO3',
        ], apiKey: '');
    }

    /**
     * Automatic diagnostics recheck persisted consent immediately before transport.
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Empty when consent has been revoked.
     */
    public function synchronizePluginData(array $payload): array
    {
        if (!$this->configurationService->isTelemetryEnabled()) {
            return [];
        }
        return $this->sendDiagnosticData($payload, true);
    }

    /**
     * Only the confirmed support workflow supplies this server-stored snapshot.
     * @param array<string, mixed> $payload
     */
    public function sendSupportDiagnosticData(array $payload): bool
    {
        return ($this->sendDiagnosticData($payload, false)['success'] ?? null) === true;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sendDiagnosticData(array $payload, bool $requiresConsent): array
    {
        if ($this->getApiKey() === '') {
            throw new AutoAltApiException('Connect an AutoAlt.ai API key before sending diagnostics.');
        }
        return $this->requestJson('autoalt-get-data-from-plugin', 'POST', [
            'json' => $payload,
            'timeout' => self::PLUGIN_DATA_SYNC_TIMEOUT,
            'connect_timeout' => 3,
            'allow_redirects' => false,
            'verify' => true,
        ], requiresTelemetryConsent: $requiresConsent);
    }

    public function getApiKey(): string
    {
        return $this->configurationService->getApiKey();
    }

    public function getRequestTimeout(): int
    {
        return $this->configurationService->getRequestTimeout();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postJson(string $endpoint, array $payload, ?string $apiKey = null): array
    {
        return $this->requestJson($endpoint, 'POST', [
            'json' => $payload,
        ], $apiKey);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $endpoint, ?string $apiKey = null): array
    {
        return $this->requestJson($endpoint, 'GET', [], $apiKey);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function requestJson(string $endpoint, string $method, array $options, ?string $apiKey = null, bool $requiresTelemetryConsent = false): array
    {
        $apiKey = $apiKey ?? $this->getApiKey();
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
            $headers['x-api-key'] = $apiKey;
        }

        $options = array_replace_recursive([
            'headers' => $headers,
            'http_errors' => false,
            'timeout' => $this->getRequestTimeout(),
        ], $options);

        if ($requiresTelemetryConsent && !$this->configurationService->isTelemetryEnabled()) {
            return [];
        }

        try {
            $response = $this->requestFactory->request($this->buildEndpointUrl($endpoint), $method, $options);
        } catch (\Throwable) {
            if ($this->isLoggingEnabled()) {
                $this->logger->error('AutoAlt.ai API request failed.', [
                    'endpoint' => $endpoint,
                ]);
                $this->recordErrorLog('error', self::GENERIC_REQUEST_FAILURE_MESSAGE, [
                    'endpoint' => $endpoint,
                ]);
            }

            throw new AutoAltApiException(self::GENERIC_REQUEST_FAILURE_MESSAGE);
        }

        return $this->decodeResponse($response, $endpoint);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(ResponseInterface $response, string $endpoint): array
    {
        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();
        $data = [];

        if ($body !== '') {
            try {
                $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                $data = is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                if ($this->isLoggingEnabled()) {
                    $this->logger->warning('AutoAlt.ai API returned invalid JSON.', [
                        'endpoint' => $endpoint,
                        'statusCode' => $statusCode,
                    ]);
                    $this->recordErrorLog('warning', 'AutoAlt.ai returned invalid JSON.', [
                        'endpoint' => $endpoint,
                        'statusCode' => $statusCode,
                    ]);
                }

                throw new AutoAltApiException('AutoAlt.ai returned invalid JSON.', $statusCode);
            }
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = self::GENERIC_REQUEST_FAILURE_MESSAGE;
            if ($this->isLoggingEnabled()) {
                $this->logger->warning('AutoAlt.ai API returned an error.', [
                    'endpoint' => $endpoint,
                    'statusCode' => $statusCode,
                    'message' => $message,
                ]);
                $this->recordErrorLog('warning', 'API returned an error: ' . $message, [
                    'endpoint' => $endpoint,
                    'statusCode' => $statusCode,
                ]);
            }

            throw new AutoAltApiException($message, $statusCode);
        }

        return $data;
    }

    private function buildEndpointUrl(string $endpoint): string
    {
        return self::API_BASE_URL . ltrim($endpoint, '/');
    }

    private function isLoggingEnabled(): bool
    {
        return $this->configurationService->isLoggingEnabled();
    }

    /**
     * Logging must never replace the API exception that initiated it. The
     * extension log table can be unavailable during an upgrade or database
     * failure, while TYPO3's configured logger remains the primary record.
     *
     * @param array<string, mixed> $context
     */
    private function recordErrorLog(string $level, string $message, array $context): void
    {
        try {
            $this->errorLogService->record($level, $message, $context);
        } catch (\Throwable) {
            $this->logger->warning('AutoAlt.ai extension error log could not be written.');
        }
    }
}
