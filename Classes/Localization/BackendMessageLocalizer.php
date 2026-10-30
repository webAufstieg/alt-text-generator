<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Localization;

use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * Localizes extension-owned messages at the backend presentation boundary.
 * Stored diagnostics, exceptions and API contracts retain their original text.
 * Unknown provider/Core messages are preserved rather than guessed or hidden.
 */
final class BackendMessageLocalizer
{
    private const LLL = 'LLL:EXT:alt_text_generator/Resources/Private/Language/locallang.xlf:';

    private const MESSAGES = [
        'Use account default' => 'settings.writingStyle.default',
        'Neutral – Clear, balanced, and objective' => 'settings.writingStyle.neutral',
        'Friendly – Warm, approachable, and informal' => 'settings.writingStyle.friendly',
        'Professional – Polished and business-appropriate' => 'settings.writingStyle.professional',
        'Casual – Laid-back and conversational' => 'settings.writingStyle.casual',
        'Witty – Clever, playful, with light humor' => 'settings.writingStyle.witty',
        'Confident – Assertive and bold' => 'settings.writingStyle.confident',
        'Empathetic – Sensitive and understanding' => 'settings.writingStyle.empathetic',
        'Inspiring – Motivational and uplifting' => 'settings.writingStyle.inspiring',
        'Technical – Precise, jargon-friendly, and expert-level' => 'settings.writingStyle.technical',
        'Minimalist – Simple, clean, and concise' => 'settings.writingStyle.minimalist',
        'Luxury – Elegant and premium-sounding' => 'settings.writingStyle.luxury',
        'Youthful – Trendy, fresh, and informal' => 'settings.writingStyle.youthful',
        'Quirky – Fun, offbeat, and creative' => 'settings.writingStyle.quirky',
        'Persuasive – Convincing and sales-driven' => 'settings.writingStyle.persuasive',
        'Playful – Light, cheerful, and energetic' => 'settings.writingStyle.playful',
        'Storytelling – Narrative and engaging' => 'settings.writingStyle.storytelling',
        'Elegant – Refined and graceful' => 'settings.writingStyle.elegant',
        'Bold – Strong, impactful, and direct' => 'settings.writingStyle.bold',
        'Humorous – Funny and entertaining' => 'settings.writingStyle.humorous',
        'Descriptive – Rich in detail and imagery' => 'settings.writingStyle.descriptive',
        'Brand-centric – Tailored to your brand voice' => 'settings.writingStyle.brandCentric',
        'AutoAlt.ai API request failed. Please try again later.' => 'error.api.requestFailed',
        'AutoAlt.ai returned invalid JSON.' => 'error.api.invalidJson',
        'AutoAlt.ai returned an empty alt text response.' => 'error.api.emptyAltText',
        'AutoAlt.ai returned an empty translation.' => 'error.api.emptyTranslation',
        'You do not have permission to generate alt text for this image.' => 'error.image.noPermission',
        'Connect an AutoAlt.ai API key before sending diagnostics.' => 'error.diagnostics.noApiKey',
        'Connect an AutoAlt.ai API key before using AI rename.' => 'error.rename.noApiKey',
        'Invalid rename request.' => 'error.rename.invalidRequest',
        'This file is already being processed.' => 'error.rename.busy',
        'The uploaded file has no valid TYPO3 file UID.' => 'error.rename.invalidUpload',
        'Invalid file UID.' => 'error.rename.invalidUid',
        'No valid rename is available to undo.' => 'error.rename.noUndo',
        'The previous filename is already occupied and cannot be restored.' => 'error.rename.nameOccupied',
        'TYPO3 could not apply the requested filename.' => 'error.rename.applyFailed',
        'Rename history could not be saved; the file rename was rolled back.' => 'error.rename.historyFailed',
        'The physical file is missing.' => 'error.rename.fileMissing',
        'The file is not a supported image.' => 'error.rename.unsupported',
        'This image type is not supported.' => 'error.rename.unsupportedType',
        'The file storage is offline.' => 'error.rename.storageOffline',
        'The file storage is read-only.' => 'error.rename.storageReadOnly',
        'You do not have permission to rename this file.' => 'error.rename.noPermission',
        'AutoAlt.ai did not return a filename. The file was not changed.' => 'error.rename.emptyFilename',
        'AI rename failed.' => 'error.rename.aiFailed',
        'The file could not be renamed.' => 'error.rename.failed',
        'Invalid filename. Enter a basename without a path.' => 'error.filename.invalid',
        'Path traversal is not allowed in filenames.' => 'error.filename.pathTraversal',
        'The filename is empty or reserved after normalization.' => 'error.filename.reserved',
        'The normalized filename is empty.' => 'error.filename.empty',
        'No available filename could be found.' => 'error.filename.noAvailableName',
        'A default metadata record, target language and translated ALT text are required.' => 'error.metadata.translationRequired',
        'Localized metadata must be created from its language-0 source record.' => 'error.metadata.sourceRequired',
        'TYPO3 backend user context is required to localize file metadata.' => 'error.metadata.backendRequired',
    ];

    private const PATTERNS = [
        '/^Image #(\d+) no longer exists\.$/D' => 'error.image.missing',
        '/^File contents could not be read for image #(\d+)\.$/D' => 'error.image.unreadable',
        '/^TYPO3 could not create metadata translation (\d+) for record (\d+)\.$/D' => 'error.metadata.localizeFailed',
        '/^Unsupported metadata field "([^"\r\n]+)"\.$/D' => 'error.metadata.unsupportedField',
        '/^API returned an error: (.+)$/sD' => 'error.log.api',
        '/^Manual generation failed for "([^"\r\n]*)": (.+)$/sD' => 'error.log.manual',
        '/^Retry generation failed for "([^"\r\n]*)": (.+)$/sD' => 'error.log.retry',
        '/^Automatic upload processing failed for "([^"\r\n]*)": (.+)$/sD' => 'error.log.upload',
        '/^Auto-rename on upload failed for "([^"\r\n]*)": (.+)$/sD' => 'error.log.rename',
        '/^Bulk generation failed for "([^"\r\n]*)": (.+)$/sD' => 'error.log.bulk',
        '/^ALT translation failed for language ([^\r\n]+?): (.+)$/sD' => 'error.log.translation',
        '/^Could not send bulk completion notification email: (.+)$/sD' => 'error.log.email',
    ];

    public static function localize(string $message, LanguageService $languageService, int $depth = 0): string
    {
        if (isset(self::MESSAGES[$message])) {
            return $languageService->sL(self::LLL . self::MESSAGES[$message]) ?: $message;
        }
        // A bounded recursion translates the known cause inside stored log
        // wrappers without treating arbitrary diagnostic text as label IDs.
        if ($depth < 3) {
            foreach (self::PATTERNS as $pattern => $key) {
                if (preg_match($pattern, $message, $matches) !== 1) {
                    continue;
                }
                $format = $languageService->sL(self::LLL . $key);
                if ($format === '') {
                    return $message;
                }
                $arguments = array_slice($matches, 1);
                if (str_starts_with($key, 'error.log.')) {
                    $last = array_key_last($arguments);
                    $arguments[$last] = self::localize($arguments[$last], $languageService, $depth + 1);
                }
                return vsprintf($format, $arguments);
            }
        }
        return $message;
    }
}
