<?php

namespace PUXT;

use Closure;
use ReflectionFunction;
use stdClass;

class Loader
{
    public $path;
    public  $route;
    public function __construct(string $path, $app, $route)
    {
        $this->path = $path;
        $this->app = $app;
        $this->route = $route;
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

    public function render(array $data = [])
    {
        $context = new Context();
        $context->_route = $this->route;

        $twig_content = "";
        $ret = $data;
        if (file_exists($this->path . ".php")) {

            ob_start();
            $php = require_once($this->path . ".php");
            $twig_content = ob_get_contents();
            ob_end_clean();

            if ($php["data"]) {
                $ret["data"] = $this->exec($php["data"], $this);
            }

            foreach ($ret["data"] as $k => $v) {
                $context->$k = $v;
            }

            if ($php["methods"]) {
                foreach ($php["methods"] as $name => $method) {
                    $context->_methods[$name] = Closure::bind($method, $context, Context::class);
                }
            }

            //created
            $created = $php["created"];
            if ($created instanceof Closure) {
                $reflection_function = new ReflectionFunction($created);

                $parameters = [];
                foreach ($reflection_function->getParameters() as $ref_par) {
                    if ($ref_par->name == "params") {
                        $parameters[] = $context->_route->params;
                    } else {
                        $parameters[] = null;
                    }
                }


                $created->call($context, ...$parameters);
            }

            if ($php["layout"]) {
                $ret["layout"] = $php["layout"];
            }


            $ret["head"] = $this->exec($php["head"], $context) ?? [];
            $ret["head"] = array_merge($data["head"] ?? [], $ret["head"]);
        }

        $ret["data"] = (array)$context;
        $ret["data"]["puxt"] = $data["puxt"];

        if (file_exists($this->path . ".twig")) {
            $twig = $this->app->twig->load($this->path . ".twig");
            $ret["puxt"] = $twig->render($ret["data"]);
        } else {

            $twig_loader = new \Twig\Loader\ArrayLoader([
                'page' => $twig_content,
            ]);
            $twig = new \Twig\Environment($twig_loader);

            $ret["puxt"] = $twig->render("page", $ret["data"]);
        }

        return $ret;
    }
}
