<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Service;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/** Stores the reviewed snapshot in the authenticated backend session, never POST data. */
final readonly class SupportDiagnosticService
{
    private const SESSION_KEY = 'autoalt.supportDiagnosticReview';
    private const REVIEW_LIFETIME = 900;

    public function __construct(
        private PluginDataSyncService $pluginDataSyncService,
        private AutoAltApiService $autoAltApiService,
    ) {}

    /** @return array{token: string, createdAt: int, userId: int, payload: array<string, mixed>} */
    public function review(BackendUserAuthentication $user, string $domain): array
    {
        $user->setAndSaveSessionData(self::SESSION_KEY, null);
        $review = [
            'token' => bin2hex(random_bytes(32)),
            'createdAt' => time(),
            'userId' => (int)$user->user['uid'],
            'payload' => $this->pluginDataSyncService->collectPayload($domain, 'support'),
        ];
        $user->setAndSaveSessionData(self::SESSION_KEY, $review);
        return $review;
    }

    public function cancel(BackendUserAuthentication $user): void
    {
        $user->setAndSaveSessionData(self::SESSION_KEY, null);
    }

    /** Controller must first enforce authentication, permission, POST and CSRF. */
    public function confirm(BackendUserAuthentication $user, string $token): bool
    {
        try {
            return $this->consumeAndSend($user, $token);
        } catch (\Throwable) {
            return false;
        }
    }

    private function consumeAndSend(BackendUserAuthentication $user, string $token): bool
    {
        $review = $user->getSessionData(self::SESSION_KEY);
        if (!is_array($review) || !is_string($review['token'] ?? null)
            || !is_array($review['payload'] ?? null)
            || ($review['userId'] ?? null) !== (int)$user->user['uid']
            || !hash_equals($review['token'], $token)
        ) {
            return false;
        }

        // Consume before transport, including failures, to prevent ordinary replays.
        $this->cancel($user);
        $age = time() - (int)($review['createdAt'] ?? 0);
        if ($age < 0 || $age >= self::REVIEW_LIFETIME) {
            return false;
        }

        return $this->autoAltApiService->sendSupportDiagnosticData($review['payload']);
    }
}
