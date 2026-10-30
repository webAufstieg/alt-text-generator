<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Tests\Unit\Localization;

use AutoAltAi\AltTextGenerator\Controller\HistoryController;
use AutoAltAi\AltTextGenerator\Controller\DiagnosticsController;
use AutoAltAi\AltTextGenerator\Localization\BackendMessageLocalizer;
use AutoAltAi\AltTextGenerator\Service\BulkCompletionNotifierService;
use AutoAltAi\AltTextGenerator\Service\ErrorLogService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Localization\LanguageStore;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Localization\LocalizationFactory;
use TYPO3\CMS\Core\Localization\LabelFileResolver;
use TYPO3\CMS\Core\Localization\TranslationDomainMapper;
use TYPO3\CMS\Core\Localization\TranslationDomainResolver;
use TYPO3\CMS\Core\Localization\Parser\XliffParser;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Package\PackageInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3\CMS\Fluid\ViewHelpers\Asset\ModuleViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Format\DateViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Uri\ResourceViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;

/** Uses the installed TYPO3 parser, language service and fallback handling. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BackendLocalizationTest extends TestCase
{
    private const RESOURCE = 'EXT:alt_text_generator/Resources/Private/Language/locallang.xlf';
    private string $root;
    private LanguageServiceFactory $factory;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
        Environment::initialize(new ApplicationContext('Testing'), true, true, $this->root, $this->root, sys_get_temp_dir() . '/autoalt-localization-var', $this->root . '/config', $this->root . '/index.php', PHP_OS);
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['lang']['parser']['xlf'] = XliffParser::class;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['lang']['requireApprovedLocalizations'] = true;
        $packageManager = $this->createMock(PackageManager::class);
        $package = $this->createMock(PackageInterface::class);
        $package->method('getPackagePath')->willReturn($this->root . '/');
        $package->method('getPackageKey')->willReturn('alt_text_generator');
        $packageManager->method('getPackage')->willReturn($package);
        $packageManager->method('getActivePackages')->willReturn(['alt_text_generator' => $package]);
        $packageManager->method('extractPackageKeyFromPackagePath')->willReturn('alt_text_generator');
        $packageManager->method('resolvePackagePath')->willReturnCallback(fn(string $path): string => str_replace('EXT:alt_text_generator/', $this->root . '/', $path));
        ExtensionManagementUtility::setPackageManager($packageManager);
        if ((new Typo3Version())->getMajorVersion() >= 14) {
            // TYPO3 14 replaced the legacy parser factory with its Symfony
            // translation adapter. Exercise the real loader on both versions.
            $cache = new VariableFrontend('autoalt_l10n', new TransientMemoryBackend());
            $GLOBALS['TYPO3_CONF_VARS']['LANG']['loader']['xlf'] = \TYPO3\CMS\Core\Localization\Loader\XliffLoader::class;
            $GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations'] = true;
            $domainResolver = new TranslationDomainResolver();
            $fileResolver = new LabelFileResolver($packageManager, $domainResolver);
            $events = $this->createMock(EventDispatcherInterface::class);
            $events->method('dispatch')->willReturnArgument(0);
            $mapper = new TranslationDomainMapper($packageManager, $fileResolver, $domainResolver, $cache, $events);
            $localizationFactory = new LocalizationFactory(new Translator('en'), $cache, $cache, $mapper, $fileResolver, $domainResolver);
        } else {
            $cache = new VariableFrontend('autoalt_l10n', new TransientMemoryBackend('Testing'));
            $cacheManager = $this->createMock(CacheManager::class);
            $cacheManager->method('getCache')->willReturn($cache);
            $localizationFactory = new LocalizationFactory(new LanguageStore($packageManager), $cacheManager);
        }
        $this->factory = new LanguageServiceFactory(new Locales(), $localizationFactory, $cache);
    }

    public function testGermanBackendUserGetsGermanLabelsThroughCanonicalEnglishReferences(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = ['lang' => 'de'];
        $language = $this->factory->createFromUserPreferences($user);
        self::assertSame('Einstellungen', $language->sL('LLL:' . self::RESOURCE . ':settings.title'));
        self::assertSame('Alt-Text generieren – AutoAlt.ai', $language->sL('LLL:' . self::RESOURCE . ':single.button'));
    }

    public function testEnglishBackendUserRetainsEnglishLabels(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = ['lang' => 'en'];
        $language = $this->factory->createFromUserPreferences($user);
        self::assertSame('Settings', $language->sL('LLL:' . self::RESOURCE . ':settings.title'));
        self::assertSame('Generate Alt Text - AutoAlt.ai', $language->sL('LLL:' . self::RESOURCE . ':single.button'));
    }

    public function testGermanRegionalLocaleFallsBackToBundledGermanCatalogue(): void
    {
        self::assertSame('Einstellungen', $this->factory->create('de-CH')->sL('LLL:' . self::RESOURCE . ':settings.title'));
    }

    public function testEveryLabelIsResolvedByTypo3AndPreservesItsSourceAndPlaceholders(): void
    {
        $english = $this->catalogue('locallang.xlf');
        $german = $this->catalogue('de.locallang.xlf');
        self::assertSame(array_keys($english), array_keys($german));
        $defaultLanguage = $this->factory->create('default');
        $germanLanguage = $this->factory->create('de');
        foreach ($english as $id => $unit) {
            self::assertSame($unit['source'], $german[$id]['source'], $id);
            self::assertNotSame('', trim($german[$id]['target']), $id);
            preg_match_all('/%(?:\d+\$)?[sd]|%%/', $unit['source'], $sourceTokens);
            preg_match_all('/%(?:\d+\$)?[sd]|%%/', $german[$id]['target'], $targetTokens);
            self::assertSame($sourceTokens[0], $targetTokens[0], 'Placeholders: ' . $id);
            self::assertSame($unit['source'], $defaultLanguage->sL('LLL:' . self::RESOURCE . ':' . $id), 'English: ' . $id);
            self::assertSame($german[$id]['target'], $germanLanguage->sL('LLL:' . self::RESOURCE . ':' . $id), 'German: ' . $id);
        }
    }

    public function testModulesAndInlineJavascriptLabelsResolveInGerman(): void
    {
        $language = $this->factory->create('de');
        $modules = require $this->root . '/Configuration/Backend/Modules.php';
        foreach ($modules as $module) {
            foreach ($module['labels'] as $reference) {
                self::assertNotSame('', $language->sL($reference), $reference);
            }
        }
        $labels = $language->getLabelsFromResource(self::RESOURCE);
        self::assertSame('Dateinamenprüfung', $labels['rename.audit.title']);
        self::assertSame('Einstellungen gespeichert.', $labels['settings.saved']);
        self::assertSame('%1$d von %2$d', $labels['bulk.progress.count']);
        self::assertSame('Abbrechen', $labels['settings.action.cancel']);
    }

    public function testHistoryLanguageNamesFollowBackendUserAndDoNotChangeStoredLanguage(): void
    {
        $controller = (new \ReflectionClass(HistoryController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(HistoryController::class, 'buildLanguagePresentation');
        self::assertSame('Deutsch', $method->invoke($controller, '1:de-DE', $this->factory->create('de'))['languageLabel']);
        self::assertSame('German', $method->invoke($controller, '1:de-DE', $this->factory->create('default'))['languageLabel']);
        self::assertSame('Sprache', $method->invoke($controller, '', $this->factory->create('de'))['languageLabel']);
    }

    public function testKnownErrorsAreLocalizedAndEnglishDiagnosticsRemainIntact(): void
    {
        $message = 'Manual generation failed for "hotel-lobby.jpg": AutoAlt.ai returned an empty alt text response.';
        self::assertSame('Manuelle Generierung für „hotel-lobby.jpg“ fehlgeschlagen: AutoAlt.ai hat keinen Alt-Text zurückgegeben.', BackendMessageLocalizer::localize($message, $this->factory->create('de')));
        self::assertSame($message, BackendMessageLocalizer::localize($message, $this->factory->create('default')));
        self::assertSame('Bild Nr. 42 existiert nicht mehr.', BackendMessageLocalizer::localize('Image #42 no longer exists.', $this->factory->create('de')));
        $providerMessage = 'Provider detail: asset hotel-lobby.jpg, 50% complete.';
        self::assertSame($providerMessage, BackendMessageLocalizer::localize($providerMessage, $this->factory->create('de')));
    }

    public function testDiagnosticErrorsAreGermanAndRetainTheirHttpStatusAndHeaders(): void
    {
        $GLOBALS['LANG'] = $this->factory->create('de');
        $controller = (new \ReflectionClass(DiagnosticsController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(DiagnosticsController::class, 'errorResponse');
        $response = $method->invoke($controller, 'diagnostics.error.adminRequired', 'Administrator access is required.', 403);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame('Administratorzugriff ist erforderlich.', (string)$response->getBody());
        $response = $method->invoke($controller, 'diagnostics.error.methodNotAllowed', 'Method not allowed.', 405, ['Allow' => 'GET, POST']);
        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET, POST', $response->getHeaderLine('Allow'));
    }

    public function testLiteralPhpFluidAndJavascriptReferencesExist(): void
    {
        $english = $this->catalogue('locallang.xlf');
        foreach (['Classes', 'Configuration', 'Resources/Private/Templates', 'Resources/Private/Partials', 'Resources/Public/JavaScript'] as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root . '/' . $directory));
            foreach ($files as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $text = file_get_contents($file->getPathname());
                preg_match_all('/locallang\.xlf:([a-zA-Z0-9_.]+)|(?:self::LLL\s*\.\s*|\blang\(\s*)\x27([^\x27]+)\x27/', $text, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $key = ($match[1] ?? '') !== '' ? $match[1] : $match[2];
                    if (!str_ends_with($key, '.')) {
                        self::assertArrayHasKey($key, $english, $file->getPathname() . ': ' . $key);
                    }
                }
            }
        }
        $localizer = new \ReflectionClass(BackendMessageLocalizer::class);
        foreach ($localizer->getConstant('MESSAGES') as $message => $key) {
            self::assertArrayHasKey($key, $english);
            self::assertSame($message, $english[$key]['source']);
        }
        foreach ($localizer->getConstant('PATTERNS') as $key) {
            self::assertArrayHasKey($key, $english);
        }
    }

    public function testAllFluidTemplatesAndPartialsParseWithTheInstalledTypo3ViewHelpers(): void
    {
        // Thumbnail ViewHelpers request this service while parsing. No image
        // is rendered or processed in this syntax/argument validation check.
        $container = new Container();
        $container->set(ImageService::class, $this->createMock(ImageService::class));
        if ((new Typo3Version())->getMajorVersion() >= 14) {
            $container->set(ModuleViewHelper::class, new ModuleViewHelper(new AssetCollector()));
            $container->set(DateViewHelper::class, new DateViewHelper(new Context()));
            // URI services are only needed when rendering; keep the real
            // initializeArguments() method for parser validation here.
            $container->set(ResourceViewHelper::class, (new \ReflectionClass(ResourceViewHelper::class))->newInstanceWithoutConstructor());
        }
        GeneralUtility::setContainer($container);
        $context = new RenderingContext();
        $namespaces = $context->getViewHelperResolver()->getNamespaces();
        $namespaces['f'][] = 'TYPO3\\CMS\\Fluid\\ViewHelpers';
        $context->setViewHelperResolver(new ViewHelperResolver($container, $namespaces));
        $files = array_merge(glob($this->root . '/Resources/Private/Templates/*/*.html'), glob($this->root . '/Resources/Private/Partials/*.html'));
        foreach ($files as $file) {
            self::assertNotNull($context->getTemplateParser()->parse(file_get_contents($file)), $file);
        }
    }

    public function testCompletionEmailUsesGermanBackendLanguageWithoutSendingRealMail(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = ['lang' => 'de'];
        $GLOBALS['BE_USER'] = $user;
        GeneralUtility::addInstance(LanguageServiceFactory::class, $this->factory);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(static function (MailMessage $message): bool {
            return $message->getSubject() === 'AutoAlt.ai: Stapelgenerierung der Alt-Texte abgeschlossen'
                && str_contains($message->getTextBody(), "Bilder insgesamt: 5\nErfolgreich abgeschlossen: 4\nFehlgeschlagen: 1\n");
        }));
        $errorLogs = (new \ReflectionClass(ErrorLogService::class))->newInstanceWithoutConstructor();
        (new BulkCompletionNotifierService($mailer, $errorLogs, new NullLogger()))->notifyIfEnabled(
            ['notifyOnBulkComplete' => '1', 'notificationEmail' => 'admin@example.test'],
            ['total' => 5, 'completed' => 4, 'failed' => 1],
        );
    }

    /** @return array<string, array{source: string, target: string}> */
    private function catalogue(string $file): array
    {
        $xml = simplexml_load_file($this->root . '/Resources/Private/Language/' . $file);
        self::assertNotFalse($xml);
        $xml->registerXPathNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
        self::assertSame('1.2', (string)$xml['version']);
        $result = [];
        foreach ($xml->xpath('//x:trans-unit') as $unit) {
            $id = (string)$unit['id'];
            self::assertArrayNotHasKey($id, $result, 'Duplicate key: ' . $id);
            $result[$id] = ['source' => (string)$unit->source, 'target' => (string)$unit->target];
        }
        self::assertNotEmpty($result);
        return $result;
    }
}
