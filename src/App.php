<?php

namespace PUXT;

use Closure;
use Exception;
use PHP\Psr7\ServerRequest;
use stdClass;

class App
{
    public $root;
    public $request;
    public $base_path;
    public $document_root;
    public $config = [];

    public function __construct(string $root)
    {
        $this->root = $root;
        $this->request = new ServerRequest();

        if (file_exists($file = $root . "/puxt.config.php")) {
            $this->config = require_once($file);
        }

        $loader = new \Twig\Loader\FilesystemLoader($this->root);
        $this->twig = new \Twig\Environment($loader, ["debug" => true]);
        $this->twig->addExtension(new \Twig_Extensions_Extension_I18n());
        //$twig["environment"]->addExtension(new \Twig_Extensions_Extension_I18n());

    }

    private function getTextDomain(string $path)
    {

        $mo = glob($this->root . "/locale/" . $this->i18n->locale . "/LC_MESSAGES/" . $path . "-*.mo")[0];
        if ($mo) {
            $mo_file = substr($mo, strlen($this->root . "/locale/" . $this->i18n->locale . "/LC_MESSAGES/"));
            $domain = preg_replace('/.[^.]*$/', '', $mo_file);
            return $domain;
        }
        return uniqid();
    }

    public function addPlugin(string $path)
    {
        if (!file_exists($path)) return;

        $m = require_once($path);

        if ($m instanceof Closure) {

            $context = $this->context;

            $inject = function (string $key, $value) use ($context) {
                $context->$key = $value;
            };


            $m->call($this, $context, $inject);
        }
    }

    private function loadModule($module)
    {
        if (is_array($module)) {
            $options = $module[1];
            $module = $module[0];
        }

        if (is_dir($this->root . "/" . $module)) {
            $entry = $this->root . "/" . $module . "/index.php";
        }


        $context = $this->context;

        $inject = function (string $key, $value) use ($context) {
            $context->$key = $value;
        };
        $m = require_once($entry);

        if ($m instanceof Closure) {
            $m->call($this, $context, $inject);
        }
    }

    public function run()
    {
        //base path
        $this->base_path = dirname($this->request->getServerParams()["SCRIPT_NAME"]);
        if (substr($this->base_path, -1) != "/") {
            $this->base_path .= "/";
        }

        $this->document_root = substr($this->root . "/", 0, -strlen($this->base_path));
        $path = $this->request->getUri()->getPath();

        $request_path = substr($path, strlen($this->base_path));

        if ($request_path === false) {
            $request_path = "error";
        }

        if ($request_path == "") {
            $request_path = "index";
        }

        $route = new Route();
        $route->path = $request_path;
        $route->params =  new stdClass;

        $this->context = new Context;
        //$context->app = $app;
        $this->context->route = $route;
        $this->context->params = $route->params;


        //modules
        $modules = $this->config["modules"] ?? [];
        foreach ($modules as $module) {
            $this->loadModule($module);
        }


        $this->render($request_path);
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
        if ($head["title"]) {
            $html[] = "<title>" . htmlentities($head['title']) . "</title>";
        }

        foreach ($head["meta"] as $meta) {
            $html[] = (string)html("meta")->attr($meta);
        }

        foreach ($head["link"] as $link) {
            $html[] = (string)html("link")->attr($link);
        }


        foreach ($head["script"] as $script) {
            $html[] = (string)html("script")->attr($script);
        }


        return implode("\n", $html);
    }

    public function render(string $request_path)
    {

        if (substr($request_path, 0, 1) == "/") {
            $request_path = substr($request_path, 1);
        }

        if (substr($request_path, 0, 5) == "_i18n") {

            $path = substr($request_path, 6);
            $i18n = new I18n\App($this->root, $this->config["i18n"], $path);
            $i18n->run();
            die();
        }


        if ($i18n = $this->config["i18n"]) {
            $this->i18n = new stdClass();
            $this->i18n->locale = $i18n["defaultLocale"];
            $paths = explode("/", $request_path);
            if (in_array($paths[0], $i18n["locales"])) {


                $this->i18n->locale = array_shift($paths);
                $request_path = implode("/", $paths);
            }
        }


        $data = [
            "head" => $this->config["head"]
        ];



        $context = $this->context;


        if (count(glob($this->root . "/pages/" . $request_path . ".*")) == 0) {

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
                if (count($files = glob($this->root . "/pages/" . $test["test"]))) {
                    $value = array_shift($test["suffix"]);

                    $f = $test["test"] . "/" . implode("/", $test["suffix"]);
                    $file = $files[0];
                    if (is_file($file)) {
                        $file = $files[0];
                        $file = substr($file, strlen($this->root . "/pages/"));

                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        $file = substr($file, 0, -strlen($ext) - 1);

                        $s = explode("_*", $f);
                        $fa = substr($file, strlen($s[0]));


                        $g = explode($s[1], $fa);

                        $name = substr($g[0], 1);

                        $route->params->$name = $value;

                        $request_path = $file;
                        break;
                    }



                    if (count($files = glob($this->root . "/pages/" . $test["test"] . "/" . implode("/", $test["suffix"]) . ".*"))) {
                        $file = $files[0];
                        $file = substr($file, strlen($this->root . "/pages/"));

                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        $file = substr($file, 0, -strlen($ext) - 1);

                        $s = explode("_*", $f);
                        $fa = substr($file, strlen($s[0]));


                        $g = explode($s[1], $fa);

                        $name = substr($g[0], 1);

                        $route->params->$name = $value;

                        $request_path = $file;
                        break;
                    }
                }
            }
        }


        $page_loader = new Loader("pages/" . $request_path, $this, $context);
        $layout_loader = new Loader("layouts/" . ($page_loader->layout ?? "default"), $this, $context, $this->config["head"]);


        foreach ($layout_loader->middleware as $middleware) {
        }


        foreach ($page_loader->middleware as $middleware) {
            $m = require_once($this->root . "/middleware/$middleware.php");
            if ($m instanceof Closure) {
                $m->call($this, $context);

                if ($context->_redirected) {
                    $this->render($context->_redirected_url);
                    return;
                }
            }
        }



        $layout_loader->processCreated();
        $head = $layout_loader->getHead($this->config["head"]);

        $page_loader->processCreated();

        $head = $page_loader->getHead($head);


        if ($this->i18n) {
            $domain = $this->getTextDomain($page_loader->path);
            bindtextdomain($domain, $this->root . "/locale");
            textdomain($domain);
        }
        $puxt = $page_loader->render("");


        if ($this->i18n) {
            $domain = $this->getTextDomain($layout_loader->path);
            bindtextdomain($domain, $this->root . "/locale");
            textdomain($domain);
        }
        $app = $layout_loader->render($puxt);



        //$layout_loader->getHead();

        /*   if ($this->request->getMethod() == "POST") {

            $ret = $loader->post($this->request->getParsedBody());
            die();
        } */

        //$page = $loader->render($data);
        //page layout
        //$layout = $layout_loader->render($page);

        $app_template = $this->twig->load("app.twig");

        $data = [];
        $data["app"] = $app;
        $data["head"] = $this->generateHeader($head);
        $data["html_attrs"] = $this->generateTagAttr($head["htmlAttrs"] ?? []);
        $data["head_attrs"] = $this->generateTagAttr($head["headAttrs"] ?? []);
        $data["body_attrs"] = $this->generateTagAttr($head["bodyAttrs"] ?? []);

        $app_html = $app_template->render($data);

        echo $app_html;
    }
}
