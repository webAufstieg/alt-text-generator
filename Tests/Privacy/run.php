<?php

declare(strict_types=1);

// Dependency-free behavioral tests. HTTP, persistence and TYPO3 adapters are faked.
require __DIR__ . '/Fakes.php';
spl_autoload_register(static function (string $class): void {
    $prefix = 'AutoAltAi\\AltTextGenerator\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__, 2) . '/Classes/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

use AutoAltAi\AltTextGenerator\Controller\ConnectAjaxController;
use AutoAltAi\AltTextGenerator\Controller\DiagnosticsController;
use AutoAltAi\AltTextGenerator\Controller\SettingsController;
use AutoAltAi\AltTextGenerator\Dto\GenerateAltTextRequest;
use AutoAltAi\AltTextGenerator\Dto\ImageScanResult;
use AutoAltAi\AltTextGenerator\Exception\AutoAltApiException;
use AutoAltAi\AltTextGenerator\Service\AutoAltApiService;
use AutoAltAi\AltTextGenerator\Service\ConfigurationService;
use AutoAltAi\AltTextGenerator\Service\CreditSummaryService;
use AutoAltAi\AltTextGenerator\Service\ErrorLogService;
use AutoAltAi\AltTextGenerator\Service\HistoryService;
use AutoAltAi\AltTextGenerator\Service\ImageScannerService;
use AutoAltAi\AltTextGenerator\Service\KeywordValidationService;
use AutoAltAi\AltTextGenerator\Service\PermissionsService;
use AutoAltAi\AltTextGenerator\Service\PluginDataSyncPayloadBuilder;
use AutoAltAi\AltTextGenerator\Service\PluginDataSyncService;
use AutoAltAi\AltTextGenerator\Service\SupportDiagnosticService;
use AutoAltAi\AltTextGenerator\Tests\Logger;
use AutoAltAi\AltTextGenerator\Tests\Request;
use AutoAltAi\AltTextGenerator\Tests\Response;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Page\PageRenderer;

$passed = 0;
function check(bool $condition, string $name): void
{
    if (!$condition) { throw new RuntimeException('FAIL: ' . $name); }
    ++$GLOBALS['passed'];
    echo 'PASS: ' . $name . PHP_EOL;
}
function fixture(array $settings = [], array $legacy = []): object
{
    $db = new Connection();
    $db->stored = $settings;
    $config = new ConfigurationService(new ExtensionConfiguration($legacy), new ConnectionPool($db));
    $http = new RequestFactory();
    $logs = new ErrorLogService();
    $logger = new Logger();
    $api = new AutoAltApiService($http, $config, $logs, $logger);
    $scanner = new ImageScannerService();
    $builder = new PluginDataSyncPayloadBuilder();
    $sync = new PluginDataSyncService($api, $config, $scanner, new HistoryService(), $logs, $builder, $logger);
    $support = new SupportDiagnosticService($sync, $api);
    $templates = new ModuleTemplateFactory();
    $forms = new FormProtectionFactory();
    $urls = new UriBuilder();
    $pageRenderer = new PageRenderer();
    $diagnostics = new DiagnosticsController($templates, $urls, $forms, $support, $pageRenderer);
    $settingsController = new SettingsController($templates, new PageRenderer(), $config, $urls, $api, new CreditSummaryService(), $logs, new KeywordValidationService(), new PermissionsService(), new LanguageServiceFactory(), $sync, $forms);
    $connect = new ConnectAjaxController($api, new CreditSummaryService(), $config, new PermissionsService(), new LanguageServiceFactory(), $sync);
    return (object)compact('db', 'config', 'http', 'logs', 'logger', 'api', 'scanner', 'builder', 'sync', 'support', 'templates', 'forms', 'diagnostics', 'settingsController', 'connect', 'pageRenderer');
}
function confirmBody(array $review, string $action = 'confirm'): array
{
    return ['formToken' => 'valid:autoaltDiagnostics:send', 'reviewToken' => $review['token'], 'diagnosticAction' => $action];
}

$GLOBALS['BE_USER'] = new BackendUserAuthentication();
$f = fixture();
$f->db->stored = null;
check($f->config->get()['telemetryEnabled'] === '0' && !$f->config->isTelemetryEnabled(), 'new installations default off');
$f = fixture([], ['apiKey' => 'legacy-key', 'writingStyle' => 'preserved', 'telemetryEnabled' => '1']);
$f->db->stored = null;
$migrated = $f->config->get();
check($migrated['telemetryEnabled'] === '0' && $migrated['apiKey'] === 'legacy-key' && $migrated['writingStyle'] === 'preserved', 'legacy migration preserves settings and never migrates consent');
foreach ([null, false, 0, '0', '', 'yes', 'true', 'on', ' 1 ', 2, 1.0, [], ['1']] as $invalid) {
    $f->db->stored = ['telemetryEnabled' => $invalid];
    check(!$f->config->isTelemetryEnabled(), 'missing/invalid consent fails closed: ' . json_encode($invalid));
}
foreach (['1', 1, true] as $enabled) {
    $f->db->stored = ['telemetryEnabled' => $enabled];
    check($f->config->isTelemetryEnabled(), 'explicit consent accepted: ' . json_encode($enabled));
}
$f->config->set(['telemetryEnabled' => 1.0, 'unrelated' => 'preserved']);
check(!$f->config->isTelemetryEnabled() && $f->config->getStored()['unrelated'] === 'preserved', 'saving invalid consent cannot become opt-in through JSON serialization');
$f->db->unavailable = true;
check(!$f->config->isTelemetryEnabled(), 'database failure cannot fall back to legacy consent');
$f->db->unavailable = false;
$f->db->malformed = true;
check(!$f->config->isTelemetryEnabled(), 'corrupt stored JSON fails closed');
$f = fixture(['apiKey' => 'secret']);
$f->sync->synchronize('example.test');
check($f->scanner->scans === 0 && $f->http->requests === [] && $f->logs->reads === 0, 'disabled telemetry collects and sends nothing');
check($f->api->synchronizePluginData(['ignored' => true]) === [] && $f->http->requests === [], 'direct API telemetry path enforces consent');
$f->config->set(['apiKey' => 'secret', 'telemetryEnabled' => '1']);
$f->sync->synchronize('example.test');
check(count($f->http->requests) === 1 && $f->scanner->scans === 1 && $f->logs->reads === 0, 'opt-in sends eligible diagnostics without reading logs');
$f->config->set(['apiKey' => 'secret', 'telemetryEnabled' => '0']);
$f->sync->synchronize('example.test');
check(count($f->http->requests) === 1, 'revocation is respected by the same service instance');
$f->config->set(['apiKey' => 'secret', 'telemetryEnabled' => '1']);
$f->scanner->onScan = static function () use ($f): void { $f->config->set(['apiKey' => 'secret', 'telemetryEnabled' => '0']); };
$f->sync->synchronize('example.test');
check(count($f->http->requests) === 1, 'revocation during collection is checked again before HTTP');
$f = fixture(['apiKey' => 'secret', 'telemetryEnabled' => '1']);
$f->http->failure = new RuntimeException('Authorization: Bearer secret; private credentials');
$f->sync->synchronize('example.test');
check(!str_contains(json_encode([$f->logger->entries, $f->logs->entries]), 'secret'), 'telemetry transport failure is contained and credentials are absent from logs');
$f->scanner->onScan = static function (): void { throw new RuntimeException('private credentials'); };
$f->sync->synchronize('example.test');
check(count($f->http->requests) === 1, 'collection failures do not reach transport or break the caller');

$builder = new PluginDataSyncPayloadBuilder();
$config = array_fill_keys(['apiKey', 'customPrompt', 'seoKeywords', 'negativeKeywords', 'altTextPrefix', 'altTextSuffix', 'notificationEmail', 'writingStyle', 'unknown'], 'SECRET-MARKER');
$config += ['generateTitle' => '1', 'allowedImageExtensions' => 'jpg,php,SECRET-MARKER', 'altTextMinLength' => -10, 'altTextMaxLength' => 9999];
$payload = $builder->build($config, new ImageScanResult(12, 4, 8), -2, 'https://user:SECRET-MARKER@www.Example.test/path?token=SECRET-MARKER', '13.4.0-private', '1.0.7-secret');
check(!str_contains(json_encode($payload), 'SECRET-MARKER'), 'allowlist excludes secrets, free text, URL credentials and paths');
check($payload['site_url'] === 'example.test' && $payload['framework_version'] === '13.4.0' && $payload['plugin_version'] === '1.0.7', 'domain and version fields are sanitized');
check($payload['process_by_plugin'] === 0 && $payload['plugin_setting_data']['alt_text_min'] === 0 && $payload['plugin_setting_data']['alt_text_max'] === 300 && $payload['plugin_setting_data']['allowed_imagetype'] === 'jpg', 'counts, numeric settings and image types are constrained');
check(array_keys($payload) === ['plugin_setting_data', 'total_images', 'missing_alt', 'framework', 'framework_version', 'plugin_version', 'process_by_plugin', 'error_log', 'site_url', 'website_domain'] && $payload['error_log'] === [], 'only documented envelope fields are sent');
try { $builder->build([], new ImageScanResult(0, 0, 0), 0, 'bad domain', '13.4', '1.0'); check(false, 'invalid domain rejected'); }
catch (InvalidArgumentException) { check(true, 'invalid domain rejected'); }

$f = fixture(['apiKey' => 'secret']);
$user = $GLOBALS['BE_USER'];
$response = $f->diagnostics->handleRequest(new Request());
$review = array_values($user->session)[0];
check($response->getStatusCode() === 200 && $f->http->requests === [] && $f->templates->values['canConfirm'], 'opening confirmation page collects locally and never sends');
check(in_array('EXT:alt_text_generator/Resources/Public/Css/backend-module.css', $f->pageRenderer->cssFiles, true), 'diagnostic review loads the shared backend stylesheet');
check(json_decode($f->templates->values['payload'], true) === $review['payload'], 'administrator sees the exact stored snapshot');
$response = $f->diagnostics->handleRequest(new Request('POST', confirmBody($review, 'cancel')));
check($response->getStatusCode() === 303 && $f->http->requests === [] && !$f->support->confirm($user, $review['token']), 'cancellation sends nothing and invalidates review');
$f->diagnostics->handleRequest(new Request());
$review = array_values($user->session)[0];
$before = $f->config->getStored();
$f->config->set($before + ['generateTitle' => '1']);
$response = $f->diagnostics->handleRequest(new Request('POST', confirmBody($review) + ['payload' => ['apiKey' => 'injected']]));
check($f->templates->values['result'] === 'success' && count($f->http->requests) === 1, 'explicit valid confirmation sends and reports backend acceptance');
check($f->http->requests[0]['options']['json'] === $review['payload'], 'confirmed report is the reviewed snapshot and ignores injected fields');
check(!$f->config->isTelemetryEnabled() && $f->config->getStored()['telemetryEnabled'] === '0', 'one-time report never enables ongoing telemetry');
check(!$f->support->confirm($user, $review['token']) && count($f->http->requests) === 1, 'consumed token prevents ordinary duplicate submission');
check($f->http->requests[0]['options']['timeout'] === 5 && $f->http->requests[0]['options']['allow_redirects'] === false && !str_contains(json_encode($review['payload']), 'secret'), 'diagnostics use bounded transport, no redirects, and authentication only in headers');

foreach ([null, new BackendUserAuthentication(false)] as $unauthorized) {
    $GLOBALS['BE_USER'] = $unauthorized;
    check($f->diagnostics->handleRequest(new Request('POST', confirmBody($review)))->getStatusCode() === 403 && count($f->http->requests) === 1, 'unauthenticated/non-admin user cannot send');
}
$GLOBALS['BE_USER'] = $user;
foreach ([[], ['formToken' => ['invalid']], ['formToken' => 'bad']] as $body) {
    check($f->diagnostics->handleRequest(new Request('POST', $body))->getStatusCode() === 403 && count($f->http->requests) === 1, 'missing/invalid/malformed CSRF token cannot send');
}
check($f->diagnostics->handleRequest(new Request('DELETE'))->getStatusCode() === 405, 'unsupported HTTP method is rejected');
$review = $f->support->review($user, 'example.test');
check(!$f->support->confirm($user, 'wrong-token') && count($f->http->requests) === 1, 'forged review token cannot send');
$review = $f->support->review($user, 'example.test');
$key = array_key_first($user->session);
$user->session[$key]['createdAt'] = time() - 900;
check(!$f->support->confirm($user, $review['token']), 'expired review cannot send');
$review = $f->support->review($user, 'example.test');
$user->session[$key]['userId'] = 999;
check(!$f->support->confirm($user, $review['token']), 'review is bound to the backend user');
foreach ([new Response('{"success":false}'), new Response('{}'), new Response('{"success":"true"}'), new Response('not json'), new Response('{"message":"secret"}', 500)] as $upstream) {
    $f->http->response = $upstream;
    $review = $f->support->review($user, 'example.test');
    $f->diagnostics->handleRequest(new Request('POST', confirmBody($review)));
    check($f->templates->values['result'] === 'failure', 'backend failure/malformed response is never shown as success: ' . $upstream->body);
}
$f->config->set(['apiKey' => 'secret', 'telemetryEnabled' => '1']);
$f->http->response = new Response('{"success":true}');
$before = $f->config->getStored();
$review = $f->support->review($user, 'example.test');
check($f->support->confirm($user, $review['token']) && $f->config->getStored() === $before, 'one-time reports also preserve existing enabled consent and all stored settings');
$f->http->failure = new RuntimeException('secret');
$review = $f->support->review($user, 'example.test');
check(!$f->support->confirm($user, $review['token']) && !$f->support->confirm($user, $review['token']), 'network failure is safe and consumes the report');
check(!str_contains(json_encode([$f->logger->entries, $f->logs->entries]), 'secret'), 'upstream error bodies and exceptions do not enter logs');

$f = fixture(['apiKey' => 'secret', 'unknownSetting' => 'preserved']);
$f->settingsController->handleRequest(new Request());
check($f->http->requests === [] && !$f->config->isTelemetryEnabled(), 'opening backend settings sends no telemetry by default');
check($f->settingsController->handleRequest(new Request('POST', ['settings' => ['telemetryEnabled' => '1']]))->getStatusCode() === 403 && !$f->config->isTelemetryEnabled(), 'settings consent requires valid CSRF');
$settingsBody = ['formToken' => 'valid:autoaltSettings:save', 'settingsAction' => 'save', 'settings' => []];
$f->settingsController->handleRequest(new Request('POST', $settingsBody));
check($f->http->requests === [] && $f->config->getStored()['unknownSetting'] === 'preserved', 'saving settings while opted out sends nothing and preserves unrelated values');
$settingsBody['settings']['telemetryEnabled'] = '1';
$f->settingsController->handleRequest(new Request('POST', $settingsBody));
check($f->config->isTelemetryEnabled() && count($f->http->requests) === 1, 'administrator can explicitly save consent');
$GLOBALS['BE_USER'] = new BackendUserAuthentication(false);
$GLOBALS['BE_USER']->tsConfig = ['tx_alttextgenerator.' => ['permissions.' => ['manageSettings' => '1']]];
$settingsBody['settings']['telemetryEnabled'] = '0';
$f->settingsController->handleRequest(new Request('POST', $settingsBody));
check($f->config->isTelemetryEnabled(), 'delegated settings editors cannot revoke administrator consent');
$GLOBALS['BE_USER'] = $user;
$f->settingsController->handleRequest(new Request('POST', $settingsBody));
$requests = count($f->http->requests);
check(!$f->config->isTelemetryEnabled(), 'administrator can revoke consent');
$GLOBALS['BE_USER'] = new BackendUserAuthentication(false);
$GLOBALS['BE_USER']->tsConfig = ['tx_alttextgenerator.' => ['permissions.' => ['manageSettings' => '1']]];
$settingsBody['settings']['telemetryEnabled'] = '1';
$f->settingsController->handleRequest(new Request('POST', $settingsBody));
check(!$f->config->isTelemetryEnabled() && count($f->http->requests) === $requests, 'delegated editors cannot grant consent through crafted settings');
$GLOBALS['BE_USER'] = $user;

$f = fixture(['unknownSetting' => 'preserved']);
$f->http->response = new Response('{"is_verify":true}');
$f->connect->connectApiKeyAction(new Request('POST', ['apiKey' => 'new-key']));
check($f->config->getApiKey() === 'new-key' && !$f->config->isTelemetryEnabled() && count($f->http->requests) === 1 && $f->config->getStored()['unknownSetting'] === 'preserved', 'API-key connection preserves settings without enabling or sending telemetry');
$f->http->response = new Response('{"api_key":"otp-key"}');
$f->connect->verifyOtpAction(new Request('POST', ['email' => 'admin@example.test', 'otp' => '123456']));
check($f->config->getApiKey() === 'otp-key' && !$f->config->isTelemetryEnabled() && count($f->http->requests) === 2, 'OTP verification saves key without enabling or sending telemetry');

$f = fixture(['apiKey' => 'secret']);
$f->http->response = new Response('{"is_verify":true}');
check($f->api->verifyApiKey(websiteDomain: 'example.test'), 'API-key validation remains operational without telemetry consent');
$f->http->response = new Response('{"credit":8,"credit_free":2}');
check($f->api->getCreditInformation(websiteDomain: 'example.test')->available === 10, 'credit checking remains operational without telemetry consent');
$f->http->response = new Response('{"alt_text":"Example image","title":"Example"}');
check($f->api->generateAltText(new GenerateAltTextRequest('https://example.test/image.jpg', 'example.test', base64Image: 'aW1hZ2U='))->altText === 'Example image', 'ALT generation remains operational without telemetry consent');
$f->http->response = new Response('{"return_string":"Bild"}');
check($f->api->translate('image', 'de', websiteDomain: 'example.test') === 'Bild', 'translation remains operational without telemetry consent');
$f->api->sendOtp('admin@example.test', 'example.test');
$f->api->verifyOtp('admin@example.test', '123456', 'example.test');
check(count($f->http->requests) === 6 && !$f->config->isTelemetryEnabled(), 'OTP requests work independently of consent');
$paths = array_map(static fn(array $r): string => basename($r['url']), $f->http->requests);
check($paths === ['autoalt-verify-api-key', 'autoalt-credit-count', 'autoalt-generate-alt', 'autoalt-translate', 'autoalt-send-otp', 'autoalt-verify-otp'], 'established operational endpoints are unchanged');
check($f->http->requests[0]['options']['json'] === ['apiKey' => 'secret', 'website_domain' => 'example.test', 'framework' => 'TYPO3'] && $f->http->requests[4]['options']['json'] === ['email' => 'admin@example.test', 'domain' => 'example.test'], 'account association and operational request fields are unchanged');

$modules = require dirname(__DIR__, 2) . '/Configuration/Backend/Modules.php';
$diagnosticRoute = $modules['media_autoalt_alt_text_generator_settings']['routes']['diagnostics'];
check($diagnosticRoute['target'] === DiagnosticsController::class . '::handleRequest' && $diagnosticRoute['methods'] === ['GET', 'POST'], 'diagnostic route stays inside the protected backend settings module');

echo PHP_EOL . $passed . ' privacy behavior checks passed. No real HTTP requests were made.' . PHP_EOL;
