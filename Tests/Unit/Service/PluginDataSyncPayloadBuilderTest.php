<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Tests\Unit\Service;

use AutoAltAi\AltTextGenerator\Dto\ImageScanResult;
use AutoAltAi\AltTextGenerator\Service\PluginDataSyncPayloadBuilder;
use PHPUnit\Framework\TestCase;

final class PluginDataSyncPayloadBuilderTest extends TestCase
{
    public function testBuildUsesTheSharedAutoAltPayloadContractAndMapsTypo3Settings(): void
    {
        $payload = (new PluginDataSyncPayloadBuilder())->build(
            configuration: [
                'apiKey' => 'test-api-key',
                'generateTitle' => '1',
                'generateDescription' => '0',
                'altTextMinLength' => '100',
                'altTextMaxLength' => '150',
                'autoGenerateOnUpload' => '1',
                'autoRenameOnUpload' => '1',
                'allowedImageExtensions' => 'jpg,png,webp',
                'overwriteExistingAltText' => '1',
                'shortAltTextLength' => '40',
            ],
            imageStatistics: new ImageScanResult(42, 8, 34, 3),
            processedByPlugin: 12,
            siteUrl: 'https://www.example.test/typo3',
            typo3Version: '13.4.34',
            extensionVersion: '1.0.8',
        );

        self::assertSame(42, $payload['total_images']);
        self::assertSame(8, $payload['missing_alt']);
        self::assertSame('typo3', $payload['framework']);
        self::assertSame('13.4.34', $payload['framework_version']);
        self::assertSame('1.0.8', $payload['plugin_version']);
        self::assertSame(12, $payload['process_by_plugin']);
        self::assertSame('example.test', $payload['site_url']);
        self::assertSame('example.test', $payload['website_domain']);
        self::assertSame([], $payload['error_log']);

        $settings = $payload['plugin_setting_data'];
        self::assertArrayNotHasKey('api_key', $settings);
        self::assertTrue($settings['generate_title']);
        self::assertFalse($settings['generate_description']);
        self::assertTrue($settings['upload_enabled']);
        self::assertTrue($settings['rename_file']);
        self::assertSame('jpg,png,webp', $settings['allowed_imagetype']);
        self::assertTrue($settings['typo3']['overwrite_existing_alt_text']);
        self::assertSame(40, $settings['typo3']['short_alt_text_length']);
        self::assertSame('telemetry', $settings['_diagnostic_metadata']['diagnostic_type']);
        self::assertMatchesRegularExpression('/^\\d+(?:\\.\\d+){1,3}$/', $settings['_diagnostic_metadata']['php_version']);
    }

    public function testSensitiveSettingsAndUrlComponentsNeverEnterSupportReports(): void
    {
        $secret = 'PRIVATE-TEST-MARKER';
        $configuration = array_fill_keys([
            'apiKey', 'writingStyle', 'altTextPrefix', 'altTextSuffix',
            'seoKeywords', 'negativeKeywords', 'customPrompt',
            'notificationEmail', 'password', 'unknownSetting',
        ], $secret);
        $payload = (new PluginDataSyncPayloadBuilder())->build(
            $configuration,
            new ImageScanResult(1, 0, 1),
            1,
            'https://user:' . $secret . '@www.example.test/private?token=' . $secret,
            '13.4.34',
            '1.0.8',
            'support',
        );

        self::assertStringNotContainsString($secret, json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertSame('example.test', $payload['site_url']);
        self::assertSame([], $payload['error_log']);
        self::assertSame('support', $payload['plugin_setting_data']['_diagnostic_metadata']['diagnostic_type']);
    }

    public function testRejectsInvalidWebsiteDomains(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PluginDataSyncPayloadBuilder())->build([], new ImageScanResult(0, 0, 0), 0, 'invalid domain', '13.4.34', '1.0.8');
    }
}
