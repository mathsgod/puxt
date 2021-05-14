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
    public $component;
    public $middleware;

    public function __construct(string $path, $app, Context $context, $head = [])
    {
        $this->path = $path;
        $this->app = $app;
        $this->context = $context;

        $this->component = new Component();

        if (file_exists($file = $this->path . ".php")) {

            ob_start();
            $this->stub = require_once($file);
            $this->twig_content = ob_get_contents();
            ob_end_clean();

            $this->layout = $this->stub["layout"];

            if ($this->stub["methods"]) {
                foreach ($this->stub["methods"] as $name => $method) {
                    $this->component->_methods[$name] = Closure::bind($method, $this->component, Component::class);
                }
            }

            $data = $this->stub["data"];
            if ($data instanceof Closure) {
                $data->call($this->component, $this->context);
            } else {
                if (is_array($data)) {
                    foreach ($data as $k => $v) {
                        $this->component->$k = $v;
                    }
                }
            }



            $this->middleware = $this->stub["middleware"] ?? [];
        }
    }

    public function processAction(string $action)
    {
        $act = $this->stub["action"][$action];
        if ($act instanceof Closure) {
            return $act->call($this->component, $this->context);
        }
    }

    public function processProps()
    {
        //props
        $props = $this->stub["props"] ?? [];
        foreach ($props as $name => $value) {


            $type = $value;
            $default = "";
            $required = false;

            if (is_array($value)) {
                $type = $value["type"];
                $default = $value["default"];
                $required = (bool) $value["required"];
            }


            if ($required && !isset($_GET[$name])) {
                throw new Exception("props [$name] is required");
            }


            if (isset($_GET[$name])) {
                $default = $_GET[$name];
            }

            if ($type == "string") {
                $this->component->$name = (string)$default;
            } elseif ($type == "int") {
                $this->component->$name = intval($default);
            } elseif ($type == "float") {
                $this->component->$name = floatval($default);
            } elseif ($type == "object") {
                $this->component->$name = $default;
                if ($default instanceof Closure) {
                    $this->component->$name = $default->call($this->context);
                }
            } elseif ($type == "array") {
                $this->component->$name = $default;
                if ($default instanceof Closure) {
                    $this->component->$name = $default->call($this->context);
                }
                $this->component->$name = array_values($this->component->$name);
            }
        }
    }

    public function processCreated()
    {
        //created
        $created = $this->stub["created"];
        if ($created instanceof Closure) {
            $created->call($this->component, $this->context);
        }
    }

    public function processGet()
    {
        $get = $this->stub["get"];
        if ($get instanceof Closure) {
            return $get->call($this->component, $this->context);
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

    public function getHead(array $head)
    {
        $h = $this->stub["head"] ?? [];
        if ($h instanceof Closure) {
            $h = $h->call($this->component, $this->context);
        }

        if ($h["title"]) {
            $head["title"] = $h["title"];
        }

        foreach ($h["meta"] ?? [] as $meta) {
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

        foreach ($h["link"] ?? [] as $link) {
            $head["link"][] = $link;
        }

        foreach ($h["script"] ?? [] as $script) {
            $head["script"][] = $script;
        }



        return $head;
    }

    public function render($puxt)
    {
        $data = (array)$this->component;
        $data["puxt"] = $puxt;
        $data["_params"] = $this->context->params;
        $data["_route"] = $this->context->route;
        $data["_config"] = $this->context->config;
        if ($this->context->i18n) {
            $data["_i18n"] = $this->context->i18n;
        }

        if (file_exists($this->path . ".vue")) {
            $path = $this->app->base_path . "_vue/" . $this->context->route->path;

            $twig_loader = new \Twig\Loader\ArrayLoader([
                'vue' => file_get_contents(dirname(__DIR__) . "/vue.twig"),
            ]);
            $twig = new \Twig\Environment($twig_loader, ["debug" => true]);
            return  $twig->render("vue", ["path" => $path]);
        }

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
            return $post->call($this->component, $this->context);
        }
    }
}
