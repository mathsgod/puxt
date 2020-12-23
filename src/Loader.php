<?php

namespace PUXT;

use Closure;
use ReflectionFunction;
use stdClass;

class Loader
{
    public $path;
    public $route;
    public $context;

    public $stub;
    public $twig_content = "";
    public $data = [];
    public $layout;
    public $view;
    public $middleware;

    public function __construct(string $path, $app, $route, $head = [])
    {
        $this->path = $path;
        $this->app = $app;
        $this->route = $route;

        $context = new Context;
        //$context->app = $app;
        $context->route = $route;
        $context->params = $route->params;
        $this->context = $context;


        $this->view = new View();
        $this->_route = $this->route;

        if (file_exists($file = $this->path . ".php")) {

            ob_start();
            $this->stub = require_once($file);
            $this->twig_content = ob_get_contents();
            ob_end_clean();

            $this->layout = $this->stub["layout"];

            $this->data = $this->exec($this->stub["data"], $this);

            foreach ($this->data as $k => $v) {
                $this->view->$k = $v;
            }

            if ($this->stub["methods"]) {
                foreach ($this->stub["methods"] as $name => $method) {
                    $this->view->_methods[$name] = Closure::bind($method, $this->view, View::class);
                }
            }

            $middleware = $this->stub["middleware"];
            //$this->middleware=


        }
    }

    public function processCreated()
    {
        //created
        $created = $this->stub["created"];

        if ($created instanceof Closure) {
            $reflection_function = new ReflectionFunction($created);

            $parameters = [];
            foreach ($reflection_function->getParameters() as $ref_par) {
                if ($ref_par->name == "params") {
                    $parameters[] = $this->context->_route->params;
                } else {
                    $parameters[] = null;
                }
            }

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
        $h = $this->exec($this->stub["head"], $this->view);


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

        return $head;
    }

    public function render($puxt)
    {
        $data = (array)$this->view;
        $data["puxt"] = $puxt;
        $data["_params"] = $this->context->params;
        //        $data["_context"] = $this->context;
        //$data["_context"] = "abc";

        if (file_exists($this->path . ".twig")) {
            $twig = $this->app->twig->load($this->path . ".twig");
            $ret = $twig->render($data);
        } else {
            $twig_loader = new \Twig\Loader\ArrayLoader([
                'page' => $this->twig_content,
            ]);
            $twig = new \Twig\Environment($twig_loader, ["debug" => true]);

            $ret = $twig->render("page", $data);
        }

        return $ret;
    }
}
