<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use Composer\InstalledVersions;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Performs a consent-gated synchronization of installation information. It is
 * deliberately fire-and-forget from the caller's perspective: a remote API or
 * database failure is logged and can never break a TYPO3 request.
 */
final readonly class PluginDataSyncService
{
    private const PACKAGE_NAME = 'autoaltai/alt-text-generator';

    public function __construct(
        private AutoAltApiService $autoAltApiService,
        private ConfigurationService $configurationService,
        private ImageScannerService $imageScannerService,
        private HistoryService $historyService,
        // Retained for warmed containers; local error logs are never collected.
        ErrorLogService $legacyErrorLogService,
        private PluginDataSyncPayloadBuilder $payloadBuilder,
        private LoggerInterface $logger,
    ) {}

    public function synchronize(string $websiteDomain): void
    {
        try {
            if (!$this->configurationService->isTelemetryEnabled()) {
                return;
            }

            $this->autoAltApiService->synchronizePluginData($this->collectPayload($websiteDomain));
        } catch (\Throwable) {
            // Exceptions can contain authenticated requests. Never log them.
            $this->logger->warning('AutoAlt.ai optional diagnostic submission failed.');
        }
    }

    /** @return array<string, mixed> Locally collected; no data is transmitted. */
    public function collectPayload(string $websiteDomain, string $diagnosticType = 'telemetry'): array
    {
        $configuration = $this->configurationService->get();
        if (trim((string)($configuration['apiKey'] ?? '')) === '') {
            throw new \RuntimeException('Connect an AutoAlt.ai API key before sending diagnostics.');
        }

        $imageStatistics = $this->imageScannerService->scan(
            allowedImageExtensions: trim((string)($configuration['allowedImageExtensions'] ?? '')),
            limit: 1,
            shortAltTextLength: max(1, (int)($configuration['shortAltTextLength'] ?? 40)),
        );

        return $this->payloadBuilder->build(
            configuration: $configuration,
            imageStatistics: $imageStatistics,
            processedByPlugin: $this->historyService->countSuccessfullyProcessedFileUids(),
            siteUrl: $websiteDomain,
            typo3Version: (new Typo3Version())->getVersion(),
            extensionVersion: $this->resolveExtensionVersion(),
            diagnosticType: $diagnosticType,
        );
    }

    private function resolveExtensionVersion(): string
    {
        try {
            $extensionKey = ConfigurationService::EXTENSION_KEY;
            $emConfPath = ExtensionManagementUtility::extPath($extensionKey) . 'ext_emconf.php';
            if (is_file($emConfPath)) {
                $EM_CONF = [];
                $_EXTKEY = $extensionKey;
                require $emConfPath;
                $version = trim((string)($EM_CONF[$extensionKey]['version'] ?? ''));
                if ($version !== '') {
                    return $version;
                }
            }
        } catch (\Throwable) {
            // Composer metadata below is a safe fallback.
        }

        try {
            return InstalledVersions::getPrettyVersion(self::PACKAGE_NAME) ?? 'unknown';
        } catch (\Throwable) {
            return 'unknown';
        }
    }
}
