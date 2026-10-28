<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Controller;

use AutoAltAi\AltTextGenerator\Service\SupportDiagnosticService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsController]
final readonly class DiagnosticsController
{
    private const ROUTE = 'media_autoalt_alt_text_generator_settings';

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private UriBuilder $uriBuilder,
        private FormProtectionFactory $formProtectionFactory,
        private SupportDiagnosticService $supportDiagnosticService,
        private ?PageRenderer $pageRenderer = null,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $user = $GLOBALS['BE_USER'] ?? null;
        if (!$user instanceof BackendUserAuthentication || (int)($user->user['uid'] ?? 0) <= 0 || !$user->isAdmin()) {
            return new HtmlResponse('Administrator access is required.', 403);
        }

        $method = $request->getMethod();
        if (!in_array($method, ['GET', 'POST'], true)) {
            return new HtmlResponse('Method not allowed.', 405, ['Allow' => 'GET, POST']);
        }
        ($this->pageRenderer ?? GeneralUtility::makeInstance(PageRenderer::class))
            ->addCssFile('EXT:alt_text_generator/Resources/Public/Css/backend-module.css');
        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $result = null;
        $review = null;

        if ($method === 'POST') {
            $body = $request->getParsedBody();
            $body = is_array($body) ? $body : [];
            $token = is_string($body['formToken'] ?? null) ? $body['formToken'] : '';
            if (!$formProtection->validateToken($token, 'autoaltDiagnostics', 'send')) {
                return new HtmlResponse('Invalid form token. Reopen the diagnostic review.', 403);
            }
            if (($body['diagnosticAction'] ?? null) === 'cancel') {
                $this->supportDiagnosticService->cancel($user);
                return new RedirectResponse((string)$this->uriBuilder->buildUriFromRoute(self::ROUTE), 303);
            }
            if (($body['diagnosticAction'] ?? null) !== 'confirm') {
                return new HtmlResponse('Invalid diagnostic action.', 400);
            }
            $reviewToken = is_string($body['reviewToken'] ?? null) ? $body['reviewToken'] : '';
            $result = $this->supportDiagnosticService->confirm($user, $reviewToken) ? 'success' : 'failure';
        } else {
            try {
                $review = $this->supportDiagnosticService->review($user, $request->getUri()->getHost());
            } catch (\Throwable) {
                $result = 'collectionFailure';
            }
        }

        $template = $this->moduleTemplateFactory->create($request);
        $template->assignMultiple([
            'actionUrl' => (string)$this->uriBuilder->buildUriFromRoute(self::ROUTE . '.diagnostics'),
            'backUrl' => (string)$this->uriBuilder->buildUriFromRoute(self::ROUTE),
            'formToken' => $formProtection->generateToken('autoaltDiagnostics', 'send'),
            'reviewToken' => $review['token'] ?? '',
            'payload' => $review !== null ? json_encode($review['payload'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '',
            'canConfirm' => $review !== null,
            'result' => $result,
        ]);
        return $template->renderResponse('Settings/Diagnostics');
    }
}
