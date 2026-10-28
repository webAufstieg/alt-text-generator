<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use AutoAltAi\AltTextGenerator\Dto\ImageScanResult;

/** Explicit allowlist within the existing autoalt-get-data-from-plugin contract. */
final class PluginDataSyncPayloadBuilder
{
    /**
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public function build(
        array $configuration,
        ImageScanResult $imageStatistics,
        int $processedByPlugin,
        string $siteUrl,
        string $typo3Version,
        string $extensionVersion,
        string $diagnosticType = 'telemetry',
    ): array {
        if (!in_array($diagnosticType, ['telemetry', 'support'], true)) {
            throw new \InvalidArgumentException('Invalid diagnostic type.');
        }
        $domain = $this->normalizeWebsiteDomain($siteUrl);
        $flag = static fn(string $key): bool => in_array($configuration[$key] ?? null, [true, 1, '1', 'true', 'on', 'yes'], true);
        $number = static fn(string $key, int $max): int => min($max, max(0, (int)($configuration[$key] ?? 0)));
        $extensions = array_intersect(
            ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg'],
            explode(',', strtolower((string)($configuration['allowedImageExtensions'] ?? ''))),
        );

        return [
            'plugin_setting_data' => [
                'generate_title' => $flag('generateTitle'),
                'generate_description' => $flag('generateDescription'),
                'alt_text_min' => $number('altTextMinLength', 250),
                'alt_text_max' => $number('altTextMaxLength', 300),
                'upload_enabled' => $flag('autoGenerateOnUpload'),
                'allowed_imagetype' => implode(',', $extensions),
                'rename_file' => $flag('autoRenameOnUpload'),
                'typo3' => [
                    'enabled' => $flag('enabled'),
                    'overwrite_existing_alt_text' => $flag('overwriteExistingAltText'),
                    'short_alt_text_length' => $number('shortAltTextLength', 50),
                    'notify_on_bulk_complete' => $flag('notifyOnBulkComplete'),
                ],
                '_diagnostic_metadata' => [
                    'diagnostic_type' => $diagnosticType,
                    'php_version' => $this->versionNumber(PHP_VERSION),
                ],
            ],
            'total_images' => max(0, $imageStatistics->totalImages),
            'missing_alt' => max(0, $imageStatistics->missingAltText),
            'framework' => 'typo3',
            'framework_version' => $this->versionNumber($typo3Version),
            'plugin_version' => $this->versionNumber($extensionVersion),
            'process_by_plugin' => max(0, $processedByPlugin),
            // Preserve the envelope without ever sharing local logs or exceptions.
            'error_log' => [],
            'site_url' => $domain,
            'website_domain' => $domain,
        ];
    }

    private function normalizeWebsiteDomain(string $siteUrl): string
    {
        $host = parse_url(str_contains($siteUrl, '://') ? $siteUrl : 'https://' . $siteUrl, PHP_URL_HOST);
        if (!is_string($host) || !preg_match('/\A[a-z0-9.-]+\z/i', $host)) {
            throw new \InvalidArgumentException('A valid website domain is required for diagnostics.');
        }
        return preg_replace('/^www\./', '', strtolower($host)) ?? '';
    }

    private function versionNumber(string $version): string
    {
        return preg_match('/\A\d+(?:\.\d+){1,3}/', $version, $matches) ? $matches[0] : 'unknown';
    }
}
