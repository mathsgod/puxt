<?php

namespace PUXT;

use Closure;
use Composer\Autoload\ClassLoader;
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
use stdClass;

class App implements RequestHandlerInterface, EventDispatcherAware, LoggerAwareInterface
{
    use EventDispatcherAwareBehavior;
    use LoggerAwareTrait;

    /**
     * @var string
     */
    public $root;

    /**
     * @var ServerRequestInterface
     */
    public $request;

    public $base_path;
    public $document_root;
    public $config;
    public $context;
    public $moduleContainer;

    private $_hooks = [];
    public $twig;
    protected $twig_extensions = [];
    public $loader;
    public $router;
    protected $serviceManager;

    public function __construct(?string $root = null, ?ClassLoader $loader = null)
    {
        //create services manager
        $this->serviceManager = new ServiceManager([
            "services" => [
                App::class => $this,
                EventDispatcherInterface::class => $this->eventDispatcher(),
            ]
        ]);
        $this->serviceManager->setService(ServiceManager::class, $this->serviceManager);

        $this->serviceManager->setFactory(Di\ConfigInterface::class, Container\ConfigFactory::class);
        $this->serviceManager->setFactory(Di\InjectorInterface::class, Container\InjectorFactory::class);

        $this->serviceManager->setAllowOverride(true);


        if (!$root) {
            $debug = debug_backtrace()[0];
            $root = dirname($debug["file"]);
        }

        $this->root = $root;
        $this->loader = $loader;

        $this->config = new Config([
            "dir" => [
                "layouts" => "layouts",
                "pages" => "pages"
            ]
        ], true);


        if (file_exists($file = $root . "/puxt.config.php")) {
            $this->config->merge(new Config(include $file));
        }

        \Dotenv\Dotenv::createImmutable($root)->safeLoad();

        $this->context = new Context;
        $this->context->config = $this->config;
        $this->context->root = $root;
        $this->context->_get = $_GET;
        $this->context->_post = $_POST;

        $this->moduleContainer = new ModuleContainer($this);

        //base path
        $this->base_path = dirname($_SERVER["SCRIPT_NAME"]);
        if ($this->base_path == DIRECTORY_SEPARATOR) {
            $this->base_path = "/";
        }

        if (substr($this->base_path, -1) != "/") {
            $this->base_path .= "/";
        }

        $this->document_root = substr($this->root . "/", 0, -strlen($this->base_path));

        //plugins
        foreach ($this->config->get("plugins", []) as $plugin) {
            if (file_exists($plugin)) {
                $p = require($plugin);
                if ($p instanceof Closure) {
                    $context = $this->context;
                    $inject = function (string $key, $value) use ($context) {
                        $context->$key = $value;
                    };
                    $p->call($this, $context, $inject);
                }
            }
        }

        //module before
        try {
            $this->moduleContainer->ready();
        } catch (Exception $e) {
            $this->emitException($e);
            exit;
        }
    }

    function getServiceManager(): ServiceManager
    {
        return $this->serviceManager;
    }

    function handle(ServerRequestInterface $request): ResponseInterface
    {
        $request = $request->withAttribute("service manager", $this->serviceManager);
        $this->serviceManager->setService(ServerRequestInterface::class, $request);
        $this->request = $request;

        if (strpos($request->getHeaderLine("Content-Type"), "application/json") !== false) {
            $body = $request->getBody()->getContents();
            $request = $request->withParsedBody(json_decode($body, true));
            $this->context->_post = $request->getParsedBody();
        }

        $this->context->_files = $request->getUploadedFiles();

        $path = $request->getUri()->getPath();

        $request_path = substr($path, strlen($this->base_path));
        if ($request_path === false) {
            $request_path = "error";
        }

        $route = new Route();
        $route->path = $request_path;
        $route->query = $request->getQueryParams();
        $route->params = new stdClass;

        $this->context->route = $route;
        $this->context->params = $route->params;
        $this->context->query = $route->query;
        $this->context->request = $request;

        if (!$this->router) {
            //load default router
            $this->router = $this->getDefaultRouter();
        }

        try {
            $response = $this->router->dispatch($request);
        } catch (HttpException $e) {


            $code = $e->getStatusCode();
            if ($code < 100 || $code > 599) {
                $code = 500;
            }

            if ($code == 500 && !$this->config->debug) {
                $message = "Internal Server Error";
            } else {
                $message = $e->getMessage();
            }

            if ($this->config->debug_format == "html") {
                return new HtmlResponse($message, $code);
            } else {
                return new JsonResponse([
                    "error" => [
                        "code" => $code,
                        "message" => $message
                    ]
                ], $code);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            if ($code < 100 || $code > 599) {
                $code = 500;
            }

            //hide message if debug is off and error is 500
            if ($code == 500 && !$this->config->debug) {
                $message = "Internal Server Error";
            } else {
                $message = $e->getMessage();
            }

            if ($this->config->debug_format == "html") {
                return new HtmlResponse($message, $code);
            } else {
                return new JsonResponse([
                    "error" => [
                        "code" => $code,
                        "message" => $message
                    ]
                ], $code);
            }
        }

        if (
            $request->getMethod() == "GET"
            && strpos($request->getHeaderLine("Accept"), "text/html") !== false
            && $response->getHeaderLine("Content-Type") == "text/html"
        ) {

            if ($head = $response->getHeaderLine("puxt-head")) {
                $head = json_decode($head, true);
                $response = $response->withoutHeader("puxt-head");
            } else {
                $head = $this->config->head ?? [];
            }

            $app_template = $this->getAppTemplate();
            $data = [];
            $data["app"] = $response->getBody()->getContents();
            $data["head"] = $this->generateHeader($head);
            $data["html_attrs"] = $this->generateTagAttr($head["htmlAttrs"] ?? []);
            $data["head_attrs"] = $this->generateTagAttr($head["headAttrs"] ?? []);
            $data["body_attrs"] = $this->generateTagAttr($head["bodyAttrs"] ?? []);
            $response = $response->withBody((new StreamFactory)->createStream($app_template->render($data)));
        }

        return $response;
    }


    private function getDefaultRouter(): Router
    {
        $router = new Router();

        $router->addPatternMatcher("any", ".+");

        $base_path = $this->root . DIRECTORY_SEPARATOR . $this->config["dir"]["pages"];

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
            $data[$p] = $base_path . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . "index";
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
            $data[$s] = $base_path . DIRECTORY_SEPARATOR . $s;
        }

        //root index
        if ($fs->fileExists("index.php") || $fs->fileExists("index.html") || $fs->fileExists("index.twig")) {
            $data[""] = $base_path . DIRECTORY_SEPARATOR . "index";
        }

        $methods = ["GET", "POST", "PATCH", "PUT", "DELETE"];
        foreach ($data as $path => $file) {
            foreach ($methods as $method) {
                $path = str_replace("@", ":", $path);

                $router->map($method, $this->base_path . $path, function (ServerRequestInterface $request, array $args) use ($file) {

                    foreach ($args as $k => $v) {
                        $this->context->params->$k = $v;
                    }
                    $request = $request->withAttribute("context", $this->context);

                    $twig = $this->getTwig(new \Twig\Loader\FilesystemLoader([$this->root]));
                    $request = $request->withAttribute("twig", $twig);

                    RequestHandler::Create($file)->handle($request);
                });
            }
        }
        return $router;
    }

    function setRouter(Router $router)
    {
        $this->router = $router;
    }

    function getRouter()
    {
        if (!$this->router) {
            $this->router = new Router();
        }
        return $this->router;
    }

    function emitException(Exception $e)
    {
        $code = $e->getCode();
        if ($code < 100 || $code > 599) {
            $code = 500;
        }

        $response = new JsonResponse([
            "error" => [
                "code" => $e->getCode(),
                "message" => $e->getMessage(),
                "line" => $e->getLine(),
                "file" => $e->getFile(),
                "trace" => $e->getTraceAsString()

            ],
        ]);
        $response = $response->withStatus($code, $e->getMessage());

        (new SapiEmitter())->emit($response);
    }

    public function addExtension(ExtensionInterface $extension)
    {
        $this->twig_extensions[] = $extension;
    }

    /**
     * (new App)->run()
     */
    public function run()
    {
        (new SapiEmitter)->emit($this->handle(ServerRequestFactory::fromGlobals()));
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

    public function callHook(string $name, $args)
    {
        if ($this->_hooks[$name]) {
            foreach ($this->_hooks[$name] as $hook) {
                $hook($args);
            }
        }
    }

    public function hook(string $name, callable $fn)
    {
        $this->_hooks[$name][] = $fn;
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
