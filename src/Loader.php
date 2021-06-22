<?php

namespace PUXT;

use Closure;
use Exception;
use Generator;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use stdClass;
use Twig\TwigFunction;
use Twig_SimpleFunction;

class Loader
{
    public $path;
    public $route;
    public $context;

    public $stub;
    public $twig_content = "";
    public $layout;
    public $component;
    public $middleware = [];

    public function __construct(string $path, $app, Context $context, $head = [])
    {

        $this->path = $path;
        $this->app = $app;
        $this->context = $context;

        $this->component = new Component();
        foreach ($this->context as $k => $v) {
            $this->component->{"_" . $k} = $v;
        }

        if (file_exists($file = $this->path . ".php")) {

            ob_start();
            $this->stub = require($file);
            $this->twig_content = ob_get_contents();
            ob_end_clean();

            if (is_object($this->stub)) {
                $this->layout = $this->stub->layout;
                $this->middleware = $this->stub->middleware ?? [];

                foreach ($this->context as $k => $v) {
                    $this->stub->{"_" . $k} = $v;
                }
            } else {

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
    }

    public function processEntry(string $entry)
    {
        if (is_object($this->stub)) {

            $ref_obj = new ReflectionObject($this->stub);
            if ($ref_obj->hasMethod($entry)) {
                return $ref_obj->getMethod($entry)->invoke($this->stub, $this->context);
            }
        } else {
            $act = $this->stub["entries"][$entry];
            if ($act instanceof Closure) {
                return $act->call($this->component, $this->context);
            }
        }
    }

    public function processProps()
    {
        if (is_object($this->stub)) {
            return;
        }
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
        if (is_object($this->stub)) {

            $ref_obj = new ReflectionObject($this->stub);
            if ($ref_obj->hasMethod("created")) {
                $ref_obj->getMethod("created")->invoke($this->stub, $this->context);
            }
        } else {
            //created
            $created = $this->stub["created"];
            if ($created instanceof Closure) {
                $created->call($this->component, $this->context);
            }
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

        if (is_object($this->stub)) {
            $h = $this->stub->head ?? [];
        } else {
            $h = $this->stub["head"] ?? [];
            if ($h instanceof Closure) {
                $h = $h->call($this->component, $this->context);
            }
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
        if (file_exists($this->path . ".twig")) {
            $twig = $this->app->twig;
        } else {
            $twig_loader = new \Twig\Loader\ArrayLoader([
                'page' => $this->twig_content,
            ]);
            $twig = new \Twig\Environment($twig_loader, ["debug" => true]);
            $twig->addExtension(new \Twig_Extensions_Extension_I18n());
        }

        if (is_object($this->stub)) {

            $stub = $this->stub;
            $ref_obj = new ReflectionObject($this->stub);
            foreach ($ref_obj->getMethods(ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PUBLIC) as $method) {

                $twig->addFunction(new TwigFunction($method->name, function () use ($method, $stub) {

                    $args=func_get_args();
                    $name = $method->name;
                    return  Closure::bind(
                        function ($class) use ($name,$args) {
                            return call_user_func_array([$class,$name],$args);
                        },
                        $stub,
                        get_class($stub)
                    )($stub);

                }));
            }
            $data = (array)$this->stub;
        } else {
            $data = (array)$this->component;
            $data["puxt"] = $puxt;
            $data["_params"] = $this->context->params;
            $data["_route"] = $this->context->route;
            $data["_config"] = $this->context->config;
            if ($this->context->i18n) {
                $data["_i18n"] = $this->context->i18n;
            }
            $name = $this->context->config["context"]["name"] ?? "_puxt";
            $data[$name] = $this->context;
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
                $ret = $twig->load($this->path . ".twig")->render($data);
            } else {
                $ret = $twig->render("page", $data);
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return $ret;
    }

    public function processGet()
    {
        return $this->processVerb("get");
    }

    public function processPost()
    {
        return $this->processVerb("post");
    }

    public function processPut()
    {
        return $this->processVerb("put");
    }

    public function processDelete()
    {
        return $this->processVerb("delete");
    }

    public function processPatch()
    {
        return $this->processVerb("patch");
    }

    private function processVerb(string $verb)
    {
        if (is_object($this->stub)) {
            $ref_obj = new ReflectionObject($this->stub);
            if ($ref_obj->hasMethod($verb)) {
                return $ref_obj->getMethod($verb)->invoke($this->stub, $this->context);
            }
        } else {
            $get = $this->stub[$verb];
            if ($get instanceof Closure) {
                $ret = $get->call($this->component, $this->context);

                if ($ret instanceof Generator) {
                    return iterator_to_array($ret);
                }
                return $ret;
            }
        }
    }
}
