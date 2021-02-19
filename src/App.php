<?php

namespace PUXT;

use Closure;
use Exception;
use PHP\Psr7\ServerRequest;
use RuntimeException;
use stdClass;

class App
{
    public $root;
    public $request;
    public $base_path;
    public $document_root;
    public $config = [];
    public $context;

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

        $this->context = new Context;
        $this->context->config = $this->config;
    }

    private function getTextDomain(string $path)
    {

        $mo = glob($this->root . "/locale/" . $this->context->i18n->locale . "/LC_MESSAGES/" . $path . "-*.mo")[0];
        if ($mo) {
            $mo_file = substr($mo, strlen($this->root . "/locale/" . $this->context->i18n->locale . "/LC_MESSAGES/"));
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
        $options = [];
        if (is_array($module)) {
            $options = $module[1];
            $module = $module[0];
        }

        if (is_dir($dir = $this->root .  DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . $module)) {

            $entry = $dir . DIRECTORY_SEPARATOR . "index.php";
        }

        if (is_dir($dir = $this->root . DIRECTORY_SEPARATOR . "modules" . DIRECTORY_SEPARATOR . $module)) {
            $entry = $dir . DIRECTORY_SEPARATOR . "index.php";
        }

        if (!$entry) {
            echo "Module: $module not found";
        }

        $m = require_once($entry);
        if ($m instanceof Closure) {
            $m->call($this, $options);
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


        $route = new Route();
        $route->path = $request_path;
        $route->params =  new stdClass;

        $this->context->route = $route;
        $this->context->params = $route->params;

        //i18n process
        if ($i18n = $this->config["i18n"]) {
            $this->context->i18n->locale = $i18n["defaultLocale"];
            $this->context->i18n->language = $i18n["defaultLocale"];
            if ($i18n["locale_language_mapping"]) {
                foreach ($i18n["locale_language_mapping"] as $locale => $language) {
                    if ($this->context->i18n->locale == $locale) {
                        $this->context->i18n->language = $language;
                    }
                }
            }

            $languages = $i18n["locales"];
            if ($i18n["locale_language_mapping"]) {
                $languages = array_values($i18n["locale_language_mapping"]);
            }

            $paths = explode("/", $this->context->route->path);
            if (in_array($paths[0], $languages)) {
                $this->context->i18n->language = array_shift($paths);
                $this->context->route->path = implode("/", $paths);
                if ($i18n["locale_language_mapping"]) {
                    foreach ($i18n["locale_language_mapping"] as $locale => $language) {
                        if ($this->context->i18n->language == $language) {
                            $this->context->i18n->locale = $locale;
                        }
                    }
                }
            }
        }



        //modules
        $modules = $this->config["modules"] ?? [];
        foreach ($modules as $module) {
            $this->loadModule($module);
        }

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

    private function redirect(string $path)
    {
    }

    public function render(string $request_path)
    {

        if (substr($request_path, 0, 1) == "/") {
            $request_path = substr($request_path, 1);
        }

        if ($request_path == "") {
            $request_path = "index";
        }

        $head = $this->config["head"] ?? [];
        if ($this->context->i18n) {
            $head["base"] = ["href" => "/" . $this->context->i18n->language . "/"];
        }
        $context = $this->context;

        //dynamic route
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

                        $context->route->params->$name = $value;

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

                        $context->route->params->$name = $value;

                        $request_path = $file;
                        break;
                    }
                }
            }
        }

        //error page handle
        $page = "pages/" . $request_path;
        $pages = glob($this->root . "/" . $page . ".*");

        if (count($pages) == 0) { //page not found
            if ($request_path == "error") { //error page not found,load default
                $page = "vendor/mathsgod/puxt/pages/error";
            } else {
                if ($this->context->i18n->language) {
                    header("location: /{$this->context->i18n->language}/error");
                } else {
                    header("location: /error");
                }

                return;
            }
        }

        $page_loader = new Loader($page, $this, $context);

        if ($this->request->getMethod() == "POST") {
            $page_loader->processProps();
            $ret = $page_loader->processPost();
            if (is_array($ret)) {
                header("Content-type: application/json");
                echo json_encode($ret);
            }
            exit;
        }

        $layout = "layouts/" . ($page_loader->layout ?? "default");
        $layouts = glob($this->root . "/" . $layout . ".*");
        if (count($layouts) == 0) { //layout not found
            $layout = "vendor/mathsgod/puxt/layouts/default";
        }

        $layout_loader = new Loader($layout, $this, $context, $this->config["head"]);


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

        try {
            $layout_loader->processCreated();

            $head = $layout_loader->getHead($head);

            $page_loader->processProps();
            $page_loader->processCreated();

            if ($this->request->getMethod() == "GET") {
                $ret = $page_loader->processGet();
                if ($ret !== false) {
                    if (is_array($ret)) {
                        header("Content-type: application/json");
                        echo json_encode($ret, JSON_UNESCAPED_UNICODE);
                        die();
                    }
                }
            }


            $head = $page_loader->getHead($head);
        } catch (Exception $e) {
            //throw new RuntimeException($e->getMessage());
            echo $e->getMessage();
            die();
        }



        if ($this->context->i18n) {
            setlocale(LC_ALL, $this->context->i18n->locale);
            $domain = $this->getTextDomain($page_loader->path);
            bindtextdomain($domain, $this->root . "/locale");
            textdomain($domain);
        }
        $puxt = $page_loader->render("");


        if ($this->context->i18n) {
            setlocale(LC_ALL, $this->context->i18n->locale);
            $domain = $this->getTextDomain($layout_loader->path);
            bindtextdomain($domain, $this->root . "/locale");
            textdomain($domain);
        }
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
            $twig = new \Twig\Environment($loader);
            return $twig->load("app.twig");
        }
    }

    public function getTemplate(string $file)
    {
        return $this->twig->load($file);
    }
}
