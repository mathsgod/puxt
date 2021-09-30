<?php

namespace PUXT;

use Closure;
use Composer\Autoload\ClassLoader;
use Exception;
use JsonSerializable;
use Laminas\Diactoros\ResponseFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use PHP\Psr7\ServerRequestFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\LoaderInterface;


class App
{
    /**
     * @var String
     */
    public $root;

    /**
     * @var ServerRequestInterface
     */
    public $request;

    /**
     * @var ResponseInterface
     */
    public $response;

    public $base_path;
    public $document_root;
    public $config = [
        "dir" => [
            "layouts" => "layouts",
            "pages" => "pages"
        ]
    ];
    public $context;
    public $moduleContainer;

    private $_hooks = [];
    public $twig;
    protected $twig_extensions = [];
    public $loader;

    public function __construct(?string $root = null, ?ClassLoader $loader = null)
    {
        if (!$root) {
            $debug = debug_backtrace()[0];
            $root = dirname($debug["file"]);
        }

        $this->root = $root;
        $this->loader = $loader;
        $this->request = ServerRequestFactory::fromGlobals();
        $this->response = (new ResponseFactory)->createResponse();

        if (file_exists($file = $root . "/puxt.config.php")) {
            $config = require_once($file);
            foreach ($config as $k => $v) {
                $this->config[$k] = $v;
            }
        }


        $this->context = new Context;
        $this->context->config = $this->config;
        $this->context->root = $root;
        $this->context->_get = $_GET;
        $this->context->_post = $this->request->getParsedBody();

        $this->context->_files = $this->request->getUploadedFiles();

        $this->moduleContainer = new ModuleContainer($this);

        //base path
        $this->base_path = dirname($this->request->getServerParams()["SCRIPT_NAME"]);
        if ($this->base_path == DIRECTORY_SEPARATOR) {
            $this->base_path = "/";
        }


        if (substr($this->base_path, -1) != "/") {
            $this->base_path .= "/";
        }

        $this->document_root = substr($this->root . "/", 0, -strlen($this->base_path));

        $path = $this->request->getUri()->getPath();

        $request_path = substr($path, strlen($this->base_path));
        if ($request_path === false) {
            $request_path = "error";
        }

        $route = new Route();
        $route->path = $request_path;
        $route->query = $this->request->getQueryParams();
        $route->params = new stdClass;

        $this->context->route = $route;
        $this->context->params = $route->params;
        $this->context->query = $route->query;
        $this->context->req = $this->request;
        $this->context->resp = $this->response;


        //plugins
        foreach ($this->config["plugins"] as $plugin) {
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
        $this->moduleContainer->ready();
    }

    public function addExtension(ExtensionInterface $extension)
    {
        $this->twig_extensions[] = $extension;
    }

    public function run()
    {
        $this->render($this->context->route->path);
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

    private function redirect(string $path)
    {
        $location = $this->base_path;

        if ($this->context->i18n->language) {
            $location .= $this->context->i18n->language . "/";
        }

        $location .= $path;

        header("location: $location");
    }

    public function render(string $request_path)
    {
        if ($request_path == "") {
            $request_path = "index";
        }

        if (substr($request_path, -1) == "/") {
            $request_path .=  "index";
        }

        if (substr($request_path, 0, 1) == "/") {
            $request_path = substr($request_path, 1);
        }

        $head = $this->config["head"] ?? [];
        if ($this->context->i18n) {
            $head["base"] = ["href" => "/" . $this->context->i18n->language . "/"];
        }
        $context = $this->context;

        //dynamic route
        if (count(glob($this->root . "/" . $this->config["dir"]["pages"] . "/" . $request_path . ".*")) == 0) {



            $path_path = explode("/", $request_path);

            $s = [];
            $test_path = [];

            foreach ($path_path as $i => $path) {
                $s[] = $path;
                $test_path[] = [
                    "test" => implode("/", $s) . "/_*",
                    "path" => $path,
                    "suffix" => array_slice($path_path, $i + 1)
                ];
            }

            $test_path = array_reverse($test_path);


            foreach ($test_path as $test) {
                if (count($files = glob($this->root . "/" . $this->config["dir"]["pages"] . "/" . $test["test"]))) {
                    $value = array_shift($test["suffix"]);

                    $f = $test["test"] . "/" . implode("/", $test["suffix"]);
                    $file = $files[0];
                    if (is_file($file)) {
                        $file = $files[0];
                        $file = substr($file, strlen($this->root . "/" . $this->config["dir"]["pages"] . "/"));

                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        $file = substr($file, 0, -strlen($ext) - 1);

                        $s = explode("_*", $f);
                        $fa = substr($file, strlen($s[0]));


                        $g = explode($s[1], $fa);

                        $name = substr($g[0], 1);

                        $context->route->params->$name = $value;

                        $request_path = $file;
                        break;
                    }



                    if (count($files = glob($this->root . "/" . $this->config["dir"]["pages"] . "/" . $test["test"] . "/" . implode("/", $test["suffix"]) . ".*"))) {
                        $file = $files[0];
                        $file = substr($file, strlen($this->root . "/" . $this->config["dir"]["pages"] . "/"));

                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        $file = substr($file, 0, -strlen($ext) - 1);

                        $s = explode("_*", $f);
                        $fa = substr($file, strlen($s[0]));


                        $g = explode($s[1], $fa);

                        $name = substr($g[0], 1);

                        $context->route->params->$name = $value;

                        $request_path = $file;
                        break;
                    }
                }
            }
        }

        //error page handle
        $page = $this->config["dir"]["pages"] . "/" . $request_path;
        $pages = glob($this->root . "/$page.*");


        if (count($pages) == 0) {
            $pages = glob($this->root . "/$page/index.*");

            if (count($pages) != 0) {
                $this->redirect("$request_path/");
                return;
            }
        }

        if (count($pages) == 0) { //page not found
            if ($request_path == "error") { //error page not found,load default
                $page = "vendor/mathsgod/puxt/pages/error";
            } else {
                $this->redirect("error");
                return;
            }
        }


        $page_loader = new Loader($page, $this, $context);

        $page_loader->processProps();
        $page_loader->processCreated();


        foreach ($page_loader->middleware as $middleware) {
            $m = require_once($this->root . "/middleware/$middleware.php");
            if ($m instanceof Closure) {
                $m->call($this, $context);

                if ($context->_redirected) {
                    header("location: $context->_redirected_url");
                    return;
                }
            }
        }


        //process entry
        $params = $this->request->getQueryParams();
        if ($params["_entry"]) {
            try {
                $ret = $page_loader->processEntry($params["_entry"]);
            } catch (Exception $e) {
                $ret = [
                    "error" =>
                    [
                        "code" => $e->getCode(),
                        "message" => $e->getMessage()
                    ]
                ];
            }
            header("Content-type: application/json");
            echo json_encode($ret, JSON_UNESCAPED_UNICODE);
            die();
        }

        $verb = $this->request->getMethod();


        if ($verb == "GET") {
            $layout = ($page_loader->layout ?? "default");
            if ($this->config["layouts"][$layout]) {
                $layout = $this->config["layouts"][$layout];
            } else {
                $layout = "layouts/$layout";
            }
            $layouts = glob($this->root . "/" . $layout . ".*");

            if (count($layouts) == 0) { //layout not found
                $layout = "vendor/mathsgod/puxt/layouts/default";
            }

            $layout_loader = new Loader($layout, $this, $context, $this->config["head"]);
            if (is_array($layout_loader->middleware)) {
                foreach ($layout_loader->middleware as $middleware) {
                    $m = require_once($this->root . "/middleware/$middleware.php");
                    if ($m instanceof Closure) {
                        $m->call($this, $context);

                        if ($context->_redirected) {
                            header("location: $context->_redirected_url");
                            return;
                        }
                    }
                }
            }
        }

        try {
            ob_start();
            $ret = $page_loader->processVerb($verb);
            $content = ob_get_contents();
            ob_end_clean();

            if ($ret instanceof ResponseInterface) {
                $this->emit($ret);
                die();
            }

            if (is_array($ret) || $ret instanceof JsonSerializable) {
                $this->response = $this->response->withHeader("Content-Type", "application/json");
                $this->response->getBody()->write(json_encode($ret, JSON_UNESCAPED_UNICODE));
                $this->emit($this->response);
                die();
            }

            $puxt = $content;
            if ($verb == "GET") {
                $head = $page_loader->getHead($head);
            }
        } catch (Exception $e) {
            $content = ob_get_contents();
            ob_end_clean();

            $accept = $this->request->getHeader("accept")[0];

            if (strstr($accept, "application/json") || strstr($accept, "*/*")) {

                if (strstr($accept, "application/json")) {
                    header("Content-type: application/json");
                    echo json_encode(["error" => ["message" => $e->getMessage(), "code" => $e->getCode()]]);
                    die();
                }


                if ($verb == "GET") {
                    $puxt = $e->getMessage();
                } else {
                    header("Content-type: application/json");
                    echo json_encode(["error" => ["message" => $e->getMessage(), "code" => $e->getCode()]]);
                    die();
                }
            } else {
                echo $e->getMessage();
                die();
            }
        }

        if ($verb != "GET") {
            exit;
        }

        $this->callHook("render:before", $page_loader);
        if (!$puxt) {
            $puxt = $page_loader->render("");
        }


        $this->callHook("render:before", $layout_loader);
        $app = $layout_loader->render($puxt);


        $app_template = $this->getAppTemplate();

        $data = [];
        $data["app"] = $app;
        $data["head"] = $this->generateHeader($head);
        $data["html_attrs"] = $this->generateTagAttr($head["htmlAttrs"] ?? []);
        $data["head_attrs"] = $this->generateTagAttr($head["headAttrs"] ?? []);
        $data["body_attrs"] = $this->generateTagAttr($head["bodyAttrs"] ?? []);

        echo $app_template->render($data);
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

    private function emit(ResponseInterface $response)
    {
        $emiter = new SapiEmitter();
        $emiter->emit($response);
    }
}
