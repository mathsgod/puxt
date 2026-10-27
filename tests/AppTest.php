<?php

namespace PUXT\Tests;

use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use PUXT\App;

final class AppTest extends TestCase
{
    private string $root;
    private string $previousDirectory;
    private array $previousServer;

    protected function setUp(): void
    {
        $this->previousDirectory = getcwd();
        $this->previousServer = $_SERVER;
        $this->root = sys_get_temp_dir() . '/puxt-test-' . bin2hex(random_bytes(8));
        mkdir($this->root);
        mkdir($this->root . '/pages');
        chdir($this->root);
        $_SERVER['SCRIPT_NAME'] = '/index.php';
    }

    protected function tearDown(): void
    {
        chdir($this->previousDirectory);
        $_SERVER = $this->previousServer;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    private function write(string $path, string $content): void
    {
        $file = $this->root . '/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $content);
    }

    private function request(App $app, string $path = '/', string $method = 'GET', array $query = [], array $headers = [], string $body = ''): ResponseInterface
    {
        $stream = (new \Laminas\Diactoros\StreamFactory())->createStream($body);
        $request = new ServerRequest([], [], 'http://localhost' . $path, $method, $stream, $headers);
        return $app->handle($request->withQueryParams($query));
    }

    public function testRootPageReturnsText(): void
    {
        $this->write('pages/index.php', '<?php return new class { public function get() { return "Hello world"; } };');
        $response = $this->request(new App($this->root));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame('Hello world', (string) $response->getBody());
    }

    public static function methods(): array
    {
        return array_map(static fn ($method) => [$method], ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
    }

    #[DataProvider('methods')]
    public function testHttpMethodsReturnJson(string $method): void
    {
        $this->write('pages/api.php', <<<'PHP'
<?php
return new class {
    private $ready = false;
    public function created() { $this->ready = true; }
    public function get() { return ['method' => 'GET', 'ready' => $this->ready]; }
    public function post() { return ['method' => 'POST', 'ready' => $this->ready]; }
    public function put() { return ['method' => 'PUT', 'ready' => $this->ready]; }
    public function patch() { return ['method' => 'PATCH', 'ready' => $this->ready]; }
    public function delete() { return ['method' => 'DELETE', 'ready' => $this->ready]; }
};
PHP);
        $response = $this->request(new App($this->root), '/api', $method);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(['method' => $method, 'ready' => true], json_decode((string) $response->getBody(), true));
    }

    public function testNestedIndexAndDynamicParameters(): void
    {
        $this->write('pages/users/{id}/index.php', <<<'PHP'
<?php
return new class {
    public function get(\PUXT\Context $context, \Psr\Http\Message\ServerRequestInterface $request) {
        return ['id' => $context->params->id, 'query' => $request->getQueryParams()];
    }
};
PHP);
        $response = $this->request(new App($this->root), '/users/42/', 'GET', ['sort' => 'name']);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['id' => '42', 'query' => ['sort' => 'name']], json_decode((string) $response->getBody(), true));
    }

    public function testJsonRequestBodyIsAvailableInContext(): void
    {
        $this->write('pages/index.php', '<?php return new class { public function post(\PUXT\Context $context) { return $context->_post; } };');
        $response = $this->request(new App($this->root), '/', 'POST', [], ['Content-Type' => 'application/json'], '{"name":"客戶"}');
        self::assertSame(['name' => '客戶'], json_decode((string) $response->getBody(), true));
    }

    public function testEntryCallsExistingPageMethod(): void
    {
        $this->write('pages/index.php', '<?php return new class { public function lookup() { return ["found" => true]; } };');
        $response = $this->request(new App($this->root), '/', 'POST', ['_entry' => 'lookup']);
        self::assertSame(['found' => true], json_decode((string) $response->getBody(), true));
    }

    public function testPhpPageRendersTwigAndDefaultLayout(): void
    {
        $this->write('pages/index.php', '<?php return new class { public $name = "<客戶>"; public function get() {} };');
        $this->write('pages/index.twig', '<p>{{ name }}</p>');
        $this->write('layouts/default.twig', '<main>{{ puxt|raw }}</main>');
        $response = $this->request(new App($this->root));
        self::assertSame('<main><p>&lt;客戶&gt;</p></main>', (string) $response->getBody());
    }

    public function testStandaloneTwigPage(): void
    {
        $this->write('pages/index.twig', '<p>{{ query.name }}</p>');
        $response = $this->request(new App($this->root), '/', 'GET', ['name' => '<Raymond>']);
        self::assertSame('<p>&lt;Raymond&gt;</p>', (string) $response->getBody());
    }

    public function testHtmlPage(): void
    {
        $this->write('pages/index.html', '<h1>Static page</h1>');
        $response = $this->request(new App($this->root));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<h1>Static page</h1>', (string) $response->getBody());
    }

    public function testAppTemplateAndHead(): void
    {
        $this->write('pages/index.php', <<<'PHP'
<?php
return new class implements \Psr\Http\Server\RequestHandlerInterface {
    public $head = ['title' => '客戶 <site>', 'htmlAttrs' => ['lang' => 'zh-HK']];
    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface {
        return new \Laminas\Diactoros\Response\HtmlResponse('<p>Page</p>', 200, ['Content-Type' => 'text/html']);
    }
};
PHP);
        $app = new App($this->root);
        $response = $this->request($app, '/', 'GET', [], ['Accept' => 'text/html']);
        $html = (string) $response->getBody();
        self::assertStringContainsString('<title>客戶 &lt;site&gt;</title>', $html);
        self::assertStringContainsString('lang="zh-HK"', $html);
        self::assertStringContainsString('<p>Page</p>', $html);
    }

    public function testPluginInjectionPreservesDynamicContextValues(): void
    {
        $this->write('plugin.php', '<?php return function ($context, $inject) { $inject("customer", "Raymond"); };');
        $this->write('puxt.config.php', '<?php return ["plugins" => [__DIR__ . "/plugin.php"]];');
        $this->write('pages/index.php', '<?php return new class { public function get(\PUXT\Context $context) { return ["customer" => $context->customer]; } };');
        $response = $this->request(new App($this->root));
        self::assertSame(['customer' => 'Raymond'], json_decode((string) $response->getBody(), true));
    }

    public function testPhpConfigurationUsesPuxtConfigAndIsSharedWithContext(): void
    {
        $this->write('puxt.config.php', '<?php return ["VX" => ["site" => "客戶", "table" => ["User" => "users"]]];');
        $this->write('pages/index.php', '<?php return new class { public function get(\PUXT\Context $context) { $context->config["VX"]["site"] = "updated"; return $context->config->VX->toArray(); } };');
        $app = new App($this->root);
        self::assertInstanceOf(\PUXT\Config::class, $app->config);
        self::assertSame($app->config, $app->context->config);
        self::assertSame($app->config, $app->moduleContainer->config);
        self::assertSame('客戶', $app->config->VX->site);
        self::assertSame('pages', $app->config['dir']['pages']);

        $response = $this->request($app);
        self::assertSame(['site' => 'updated', 'table' => ['User' => 'users']], json_decode((string) $response->getBody(), true));
        self::assertSame('updated', $app->config['VX']['site']);
    }

    public function testPageMiddlewareCanModifyResponse(): void
    {
        $this->write('middleware/header.php', <<<'PHP'
<?php
return new class implements \Psr\Http\Server\MiddlewareInterface {
    public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface {
        return $handler->handle($request)->withHeader('X-Middleware', 'yes');
    }
};
PHP);
        $this->write('pages/index.php', '<?php return new class { public $middleware = ["header"]; public function get() { return "Page"; } };');
        $response = $this->request(new App($this->root));
        self::assertSame('yes', $response->getHeaderLine('X-Middleware'));
        self::assertSame('Page', (string) $response->getBody());
    }

    public function testBasePath(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/customer/index.php';
        $this->write('pages/index.php', '<?php return new class { public function get() { return "Customer site"; } };');
        $response = $this->request(new App($this->root), '/customer/');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Customer site', (string) $response->getBody());
    }

    public function testMissingRouteReturns404(): void
    {
        $response = $this->request(new App($this->root), '/missing');
        self::assertSame(404, $response->getStatusCode());
        self::assertSame(404, json_decode((string) $response->getBody(), true)['error']['code']);
    }

    public function testInternalErrorHidesDetails(): void
    {
        $this->write('pages/index.php', '<?php return new class { public function get() { throw new \RuntimeException("secret details"); } };');
        $response = $this->request(new App($this->root));
        self::assertSame(500, $response->getStatusCode());
        self::assertSame(['error' => ['code' => 500, 'message' => 'Internal Server Error']], json_decode((string) $response->getBody(), true));
    }
}
