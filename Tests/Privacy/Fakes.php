<?php

declare(strict_types=1);

// Isolated test doubles only. This file must never be loaded by the extension.
namespace Psr\Http\Message {
    interface ResponseInterface {}
    interface ServerRequestInterface {}
}
namespace Psr\Log {
    interface LoggerInterface {}
}
namespace AutoAltAi\AltTextGenerator\Tests {
    final class Request implements \Psr\Http\Message\ServerRequestInterface
    {
        public function __construct(private string $method = 'GET', private mixed $body = [], private array $query = []) {}
        public function getMethod(): string { return $this->method; }
        public function getParsedBody(): mixed { return $this->body; }
        public function getQueryParams(): array { return $this->query; }
        public function getServerParams(): array { return []; }
        public function getUri(): object { return new class { public function getHost(): string { return 'example.test'; } }; }
    }
    class Response implements \Psr\Http\Message\ResponseInterface
    {
        public function __construct(public string $body = '', public int $status = 200, public array $headers = []) {}
        public function getStatusCode(): int { return $this->status; }
        public function getBody(): string { return $this->body; }
    }
    final class Logger implements \Psr\Log\LoggerInterface
    {
        public array $entries = [];
        public function warning(string $message, array $context = []): void { $this->entries[] = [$message, $context]; }
        public function error(string $message, array $context = []): void { $this->entries[] = [$message, $context]; }
    }
}
namespace TYPO3\CMS\Core\Configuration {
    class ExtensionConfiguration
    {
        public function __construct(public array $legacy = []) {}
        public function get(string $key): array { return $this->legacy; }
    }
}
namespace TYPO3\CMS\Core\Database {
    class ConnectionPool
    {
        public function __construct(public Connection $connection) {}
        public function getConnectionForTable(string $table): Connection { return $this->connection; }
    }
    class Connection
    {
        public const PARAM_INT = 1;
        public ?array $stored = null;
        public bool $unavailable = false;
        public bool $malformed = false;
        public function createQueryBuilder(): object
        {
            if ($this->unavailable) { throw new \RuntimeException('private database credentials'); }
            return new class($this) {
                private string $column = '';
                public function __construct(private Connection $connection) {}
                public function select(string $column): self { $this->column = $column; return $this; }
                public function from(string $table): self { return $this; }
                public function where(string $condition): self { return $this; }
                public function executeQuery(): self { return $this; }
                public function fetchOne(): mixed
                {
                    if ($this->connection->malformed) { return '{invalid'; }
                    if ($this->connection->stored === null) { return false; }
                    return $this->column === 'uid' ? 1 : json_encode($this->connection->stored, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
                }
            };
        }
        public function insert(string $table, array $values, array $types = []): void { $this->stored = json_decode($values['configuration'], true, flags: JSON_THROW_ON_ERROR); }
        public function update(string $table, array $values, array $identifier, array $types = []): void { $this->insert($table, $values); }
    }
}
namespace TYPO3\CMS\Core\Http {
    class RequestFactory
    {
        public array $requests = [];
        public ?\Throwable $failure = null;
        public \AutoAltAi\AltTextGenerator\Tests\Response $response;
        public function __construct() { $this->response = new \AutoAltAi\AltTextGenerator\Tests\Response('{"success":true}'); }
        public function request(string $url, string $method, array $options): \Psr\Http\Message\ResponseInterface
        {
            $this->requests[] = compact('url', 'method', 'options');
            if ($this->failure) { throw $this->failure; }
            return $this->response;
        }
    }
    class HtmlResponse extends \AutoAltAi\AltTextGenerator\Tests\Response {}
    class JsonResponse extends \AutoAltAi\AltTextGenerator\Tests\Response
    {
        public function __construct(array $data, int $status = 200) { parent::__construct(json_encode($data), $status); }
    }
    class RedirectResponse extends \AutoAltAi\AltTextGenerator\Tests\Response
    {
        public function __construct(string $url, int $status = 302) { parent::__construct('', $status, ['Location' => $url]); }
    }
}
namespace TYPO3\CMS\Core\Authentication {
    class BackendUserAuthentication
    {
        public array $user = ['uid' => 1];
        public array $session = [];
        public array $tsConfig = [];
        public function __construct(public bool $admin = true) {}
        public function isAdmin(): bool { return $this->admin; }
        public function getTSConfig(): array { return $this->tsConfig; }
        public function getSessionData(string $key): mixed { return $this->session[$key] ?? null; }
        public function setAndSaveSessionData(string $key, mixed $data): void { $this->session[$key] = $data; }
    }
}
namespace TYPO3\CMS\Core\FormProtection {
    class FormProtectionFactory
    {
        public function createFromRequest(\Psr\Http\Message\ServerRequestInterface $request): object
        {
            return new class {
                public function generateToken(string $form, string $action): string { return 'valid:' . $form . ':' . $action; }
                public function validateToken(string $token, string $form, string $action): bool { return $token === $this->generateToken($form, $action); }
            };
        }
    }
}
namespace TYPO3\CMS\Backend\Template {
    class ModuleTemplateFactory
    {
        public array $values = [];
        public function create(\Psr\Http\Message\ServerRequestInterface $request): object
        {
            return new class($this) {
                public function __construct(private ModuleTemplateFactory $factory) {}
                public function setTitle(string ...$titles): void {}
                public function assignMultiple(array $values): void { $this->factory->values = $values; }
                public function renderResponse(string $template): \Psr\Http\Message\ResponseInterface { return new \AutoAltAi\AltTextGenerator\Tests\Response($template); }
            };
        }
    }
}
namespace TYPO3\CMS\Backend\Routing {
    class UriBuilder { public function buildUriFromRoute(string $route, array $params = []): string { return '/module/' . $route; } }
}
namespace TYPO3\CMS\Core\Information {
    class Typo3Version
    {
        public function getVersion(): string { return '13.4.0'; }
        public function getMajorVersion(): int { return 13; }
    }
}
namespace TYPO3\CMS\Core\Utility {
    class ExtensionManagementUtility { public static function extPath(string $key): string { return dirname(__DIR__, 2) . '/'; } }
    class GeneralUtility
    {
        public static array $instances = [];
        public static function makeInstance(string $class): object { return self::$instances[$class] ?? new $class(); }
    }
}
namespace TYPO3\CMS\Core\Localization {
    class LanguageService { public function sL(string $key): string { return $key; } }
    class LanguageServiceFactory { public function createFromUserPreferences(object $user): LanguageService { return new LanguageService(); } }
}
namespace TYPO3\CMS\Core\Page {
    class PageRenderer
    {
        public array $cssFiles = [];
        public function addCssFile(string $file): void { $this->cssFiles[] = $file; }
        public function addInlineLanguageLabelFile(string $file): void {}
    }
}
namespace AutoAltAi\AltTextGenerator\Service {
    class ErrorLogService
    {
        public array $entries = [];
        public int $reads = 0;
        public function record(string $level, string $message, array $context): void { $this->entries[] = compact('level', 'message', 'context'); }
        public function getRecent(int $limit): array { ++$this->reads; return []; }
        public function clear(): void { $this->entries = []; }
    }
    class ImageScannerService
    {
        public int $scans = 0;
        public ?\Closure $onScan = null;
        public function scan(string $allowedImageExtensions, int $limit, int $shortAltTextLength): \AutoAltAi\AltTextGenerator\Dto\ImageScanResult
        {
            ++$this->scans;
            if ($this->onScan) { ($this->onScan)(); }
            return new \AutoAltAi\AltTextGenerator\Dto\ImageScanResult(12, 4, 8);
        }
    }
    class HistoryService { public function countSuccessfullyProcessedFileUids(): int { return 7; } }
    class CreditSummaryService { public function build(object $language, array $settings, string $domain): array { return []; } }
}
