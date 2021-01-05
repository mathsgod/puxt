<?php

namespace PUXT;

use Closure;
use Exception;
use ReflectionFunction;
use stdClass;

class Loader
{
    public $path;
    public $route;
    public $context;

    public $stub;
    public $twig_content = "";
    public $layout;
    public $view;
    public $middleware;

    public function __construct(string $path, $app, Context $context, $head = [])
    {
        $this->path = $path;
        $this->app = $app;
        $this->context = $context;

        $this->view = new View();

        if (file_exists($file = $this->path . ".php")) {

            ob_start();
            $this->stub = require_once($file);
            $this->twig_content = ob_get_contents();
            ob_end_clean();



            $this->layout = $this->stub["layout"];

            $data = $this->exec($this->stub["data"], $this);

            foreach ($data as $k => $v) {
                $this->view->$k = $v;
            }

            if ($this->stub["methods"]) {
                foreach ($this->stub["methods"] as $name => $method) {
                    $this->view->_methods[$name] = Closure::bind($method, $this->view, View::class);
                }
            }

            $this->middleware = $this->stub["middleware"] ?? [];
        }
    }

    public function processCreated()
    {
        //created
        $created = $this->stub["created"];
        if ($created instanceof Closure) {
            $created->call($this->view, $this->context);
        }
    }

    public function post(array $body = [])
    {
        if (file_exists($this->path . ".php")) {

            ob_start();
            $php = require_once($this->path . ".php");
            $twig_content = ob_get_contents();
            ob_end_clean();

            if ($php["post"]) {
                return $php["post"]->call($this, $body);
            }
        }
    }

    private function exec($stub, $context = null)
    {
        if ($stub instanceof Closure) {
            return $stub->call($context);
        }

        return $stub;
    }

    public function getHead(array $head)
    {
        $h = $this->stub["head"] ?? [];
        if ($h instanceof Closure) {
            $h = $h->call($this->view, $this->context);
        }

        if ($h["title"]) {
            $head["title"] = $h["title"];
        }

        foreach ($h["meta"] as $meta) {
            if ($meta["hid"]) {

                foreach ($head["meta"] as $k => $m) {
                    if ($m["hid"] == $meta["hid"]) {
                        $head["meta"][$k] = $meta;
                        continue 2;
                    }
                }
            }
            $head["meta"][] = $meta;
        }

        if ($h["htmlAttrs"]) {
            $head["htmlAttrs"] = $h["htmlAttrs"];
        }

        if ($h["bodyAttrs"]) {
            $head["bodyAttrs"] = $h["bodyAttrs"];
        }

        if ($h["headAttrs"]) {
            $head["headAttrs"] = $h["headAttrs"];
        }

        foreach ($h["link"] as $link) {
            $head["link"][] = $link;
        }

        foreach ($h["script"] as $script) {
            $head["script"][] = $script;
        }



        return $head;
    }

    public function render($puxt)
    {
        $data = (array)$this->view;
        $data["puxt"] = $puxt;
        $data["_params"] = $this->context->params;
        $data["_route"] = $this->context->route;
        $data["_config"] = $this->context->config;


        try {
            if (file_exists($this->path . ".twig")) {
                $twig = $this->app->twig->load($this->path . ".twig");
                $ret = $twig->render($data);
            } else {
                $twig_loader = new \Twig\Loader\ArrayLoader([
                    'page' => $this->twig_content,
                ]);
                $twig = new \Twig\Environment($twig_loader, ["debug" => true]);
                $twig->addExtension(new \Twig_Extensions_Extension_I18n());
                $ret = $twig->render("page", $data);
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return $ret;
    }

    public function processPost()
    {
        $post = $this->stub["post"];
        if ($post instanceof Closure) {
            return $post->call($this->view, $this->context);
        }
    }
}
