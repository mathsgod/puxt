<?php

namespace PUXT;

use Exception;
use Laminas\Config\Config;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Laminas\ServiceManager\ServiceManager;
use League\Event\EventDispatcherAware;
use League\Event\EventDispatcherAwareBehavior;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\StorageAttributes;
use League\Route\Http\Exception as HttpException;
use League\Route\Router;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\LoaderInterface;
use Laminas\Di;
use Laminas\Di\Container;
use Laminas\Di\InjectorInterface;
use Laminas\Diactoros\ResponseFactory;
use Laminas\HttpHandlerRunner\RequestHandlerRunner;
use Laminas\HttpHandlerRunner\RequestHandlerRunnerInterface;
use Laminas\Stratigility\MiddlewarePipe;
use League\Route\RouteGroup;
use League\Route\Strategy\JsonStrategy;
use Psr\Http\Server\MiddlewareInterface;
use Throwable;

class App implements EventDispatcherAware, LoggerAwareInterface, RequestHandlerRunnerInterface, MiddlewareInterface
{
    use EventDispatcherAwareBehavior;
    use LoggerAwareTrait;

    /**
     * @var string
     */
    public $root;


    public $base_path;
    public $document_root;
    public $config;

    public $twig;
    protected $twig_extensions = [];
    public $router;

    protected $server;
    protected $middleware;
    protected $serviceManager;
    protected $base;

    protected $head = [];


    static $instance;

    public function __construct()
    {
        self::$instance = $this;


        $debug = debug_backtrace()[0];
        $this->root = dirname($debug["file"]);

        //env
        \Dotenv\Dotenv::createImmutable($this->root)->safeLoad();

        //config
        $this->config = new Config([
            "dir" => [
                "layouts" => "layouts",
                "pages" => "pages"
            ]
        ], true);

        if (file_exists($file = $this->root . "/puxt.config.php")) {
            $this->config->merge(new Config(include $file));
        }


        //service manager
        $this->serviceManager = new ServiceManager([
            "services" => [
                App::class => $this,
                EventDispatcherInterface::class => $this->eventDispatcher(),
                Config::class => $this->config,
            ],
            "factories" => [
                Di\ConfigInterface::class => Container\ConfigFactory::class,
                Di\InjectorInterface::class => Container\InjectorFactory::class,
            ]
        ]);
        $this->serviceManager->setService(ServiceManager::class, $this->serviceManager);
        $this->serviceManager->setAllowOverride(true);

        $this->middleware = new MiddlewarePipe();


        //request attributes
        $serviceManager = $this->serviceManager;
        $this->server = new RequestHandlerRunner(
            $this->middleware,
            new SapiEmitter(),
            function () use ($serviceManager) {
                $request = ServerRequestFactory::fromGlobals();
                return $request->withAttribute(ServiceManager::class, $serviceManager);
            },
            function (Throwable $e) {
                return new HtmlResponse('Error: ' . $e->getMessage(), 500);
            }
        );

        $this->base = $_ENV["BASE_PATH"];
        //if base path is end with "/", remove it
        if (substr($this->base, -1) == "/") {
            $this->base = substr($this->base, 0, -1);
        }
    }

    function getInjector(): InjectorInterface
    {
        return $this->serviceManager->get(InjectorInterface::class);
    }

    function useHead(array $head)
    {
        $this->head = $head;
    }

    /**
     * @param MiddlewareInterface|string $middleware
     */
    function pipe(mixed $middleware)
    {
        if (is_string($middleware)) {
            try {
                $middleware = $this->getInjector()->create($middleware);
            } catch (Exception $e) {
                $this->emitException($e);
                return;
            }
        }

        if ($middleware instanceof MiddlewareInterface) {
            $this->middleware->pipe($middleware);
        }

        return $this;
    }

    function getServiceManager(): ServiceManager
    {
        return $this->serviceManager;
    }

    function handle(ServerRequestInterface $request): ResponseInterface
    {
        //load default router
        $router = $this->getRouter();

        try {
            $response = $router->dispatch($request);
        } catch (HttpException $e) {
            return new HtmlResponse($e->getMessage(),  $e->getStatusCode());
        } catch (Exception $e) {
            return $this->generateResponseFromException($e);
        }

        if (
            $request->getMethod() == "GET"
            && strpos($request->getHeaderLine("Accept"), "text/html") !== false
            && strpos($response->getHeaderLine("Content-Type"), "text/html") !== false
        ) {

            $app_template = $this->getAppTemplate();
            $data = [];
            $data["app"] = $response->getBody()->getContents();
            $data["head"] = $this->generateHeader($this->head);

            $data["html_attrs"] = $this->generateTagAttr($head["htmlAttrs"] ?? []);
            $data["head_attrs"] = $this->generateTagAttr($head["headAttrs"] ?? []);
            $data["body_attrs"] = $this->generateTagAttr($head["bodyAttrs"] ?? []);
            $response = $response->withBody((new StreamFactory)->createStream($app_template->render($data)));
        }

        return $response;
    }


    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->getServiceManager()->setService(ServerRequestInterface::class, $request);

        //default handler
        return $this->handle($request);
    }

    private function getRouter(): Router
    {
        $router = new Router();

        if (isset($_ENV["ROUTE_STRATEGY"]) && $_ENV["ROUTE_STRATEGY"] == "json") {
            $strategy = new JsonStrategy(new ResponseFactory());
            $router->setStrategy($strategy);
        }

        $router->addPatternMatcher("any", ".+");

        $base_path = $this->root . DIRECTORY_SEPARATOR . "pages";

        $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter($base_path);
        $fs = new \League\Flysystem\Filesystem($adapter);

        $dirs = $fs->listContents('/', true)->filter(function (StorageAttributes $attributes) {
            return $attributes->isDir();
        })->filter(function (DirectoryAttributes $dir) use ($fs) {
            $path = $dir["path"];
            return $fs->fileExists($path . "/index.twig")
                || $fs->fileExists($path . "/index.php")
                || $fs->fileExists($path . "/index.html");
        })->map(function (DirectoryAttributes $attributes) use ($base_path) {
            return  ["path" => $attributes->path()];
        })->toArray();

        $data = [];
        foreach ($dirs as $dir) {
            $path = $dir["path"];
            $p = str_replace(DIRECTORY_SEPARATOR, "/", $path) . "/";
            $data["/" . $p] = $base_path . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . "index";
        }

        $files = $fs->listContents('/', true)->filter(function (StorageAttributes $attributes) {
            return $attributes->isFile();
        })->filter(function (FileAttributes $attributes) {
            $ext = pathinfo($attributes->path(), PATHINFO_EXTENSION);
            return $ext == "php" || $ext == "html" || $ext == "twig";
        })->map(function (FileAttributes $attributes) {
            $path = $attributes->path();
            //find ext
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            //remove all extension
            $path = substr($path, 0, -strlen($ext) - 1);
            return $path;
        })->toArray();

        $files = array_unique($files);
        foreach ($files as $s) {
            $data["/" . $s] = $base_path . DIRECTORY_SEPARATOR . $s;
        }

        //root index
        if ($fs->fileExists("index.php") || $fs->fileExists("index.html") || $fs->fileExists("index.twig")) {
            $data[""] = $base_path . DIRECTORY_SEPARATOR . "index";
            $data["/"] = $base_path . DIRECTORY_SEPARATOR . "index";
        }


        $methods = ["GET", "POST", "PATCH", "PUT", "DELETE"];
        foreach ($data as $path => $file) {
            foreach ($methods as $method) {
                $path = str_replace("@", ":", $path);
                $router->map($method, $this->base . $path, function (ServerRequestInterface $request, array $args) use ($file) {
                    $twig = $this->getTwig(new \Twig\Loader\FilesystemLoader([$this->root]));
                    $request = $request->withAttribute(\Twig\Environment::class, $twig);
                    return RequestHandler::Create($file)->handle($request);
                });
            }
        }

        return $router;
    }

    function generateResponseFromException(Exception $e)
    {
        $code = $e->getCode();
        if ($code < 100 || $code > 599) {
            $code = 500;
        }


        $message = "Internal Server Error";
        if ($_ENV["DEBUG"]) {
            $message = $e->getMessage();
        }

        if ($_ENV["DEBUG_EXCEPTION_FORMAT"] == "json") {
            $response = new JsonResponse([
                "error" => [
                    "code" => $e->getCode(),
                    "message" => $message,
                    "line" => $e->getLine(),
                    "file" => $e->getFile(),
                    "trace" => $e->getTraceAsString()

                ],
            ]);
            $response = $response->withStatus($code, $e->getMessage());
        } else {
            if ($_ENV["DEBUG"]) {
                $message .= "<pre>" . $e->getTraceAsString() . "</pre>";
            }
            $response = new HtmlResponse($message, 500);
        }
        return $response;
    }

    function emitException(Exception $e)
    {
        (new SapiEmitter())->emit($this->generateResponseFromException($e));
    }

    public function addExtension(ExtensionInterface $extension)
    {
        $this->twig_extensions[] = $extension;
    }

    /**
     * (new App)->run()
     */
    public function run(): void
    {
        $this->middleware->pipe($this);
        $this->server->run();
    }

    private function generateTagAttr(array $attrs)
    {
        $ret = [];
        foreach ($attrs ?? [] as $name => $attr) {
            if (is_array($attr)) {
                $ret[] = "$name=\"" . htmlspecialchars(implode(" ", $attr)) . "\"";
            } else {
                $ret[] = "$name=\"" . htmlspecialchars($attr) . "\"";
            }
        }
        return implode(" ", $ret);
    }

    private function generateHeader(array $head)
    {
        $html = [];
        if ($head["base"]) {
            $html[] = (string)html("base")->attr($head["base"]);
        }

        if ($head["title"]) {
            $html[] = "<title>" . htmlentities($head['title']) . "</title>";
        }

        if (is_array($head["meta"])) {
            foreach ($head["meta"] as $meta) {
                $html[] = (string)html("meta")->attr($meta);
            }
        }

        if (is_array($head["link"])) {
            foreach ($head["link"] as $link) {
                $html[] = (string)html("link")->attr($link);
            }
        }


        if (is_array($head["script"])) {
            foreach ($head["script"] as $script) {
                $html[] = (string)html("script")->attr($script);
            }
        }


        return implode("\n", $html);
    }

    private function getAppTemplate()
    {
        if (file_exists($this->root . "/app.twig")) {
            return $this->getTemplate("app.twig");
        } else { //load from default
            $loader = new \Twig\Loader\FilesystemLoader(dirname(__DIR__));
            $twig = $this->getTwig($loader);
            return $twig->load("app.twig");
        }
    }

    public function getTemplate(string $file)
    {
        return $this->getTwig()->load($file);
    }

    public function getTwig(LoaderInterface $loader = null)
    {
        if (!$loader) {
            $loader = new \Twig\Loader\FilesystemLoader($this->root);
        }

        $twig = new \Twig\Environment($loader, ["debug" => true]);

        foreach ($this->twig_extensions as $ext) {
            $twig->addExtension($ext);
        }

        return $twig;
    }
}
