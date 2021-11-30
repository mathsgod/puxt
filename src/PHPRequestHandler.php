<?php

namespace PUXT;

use Closure;
use Exception;
use JsonSerializable;
use Laminas\Diactoros\Response;
use PHP\Psr7\StringStream;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use Twig\Environment;
use Twig\TwigFunction;

class PHPRequestHandler implements RequestHandlerInterface
{
    private $file;
    private $stub;
    private $twig;
    private $context;
    private $layout;
    private $middleware = [];

    function __construct(string $file)
    {
        $this->file = $file;

        ob_start();
        $this->stub = require($file);
        $this->twig_content = ob_get_contents();
        ob_end_clean();

        $this->layout = $this->stub->layout ?? "default";
    }

    function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->context = $request->getAttribute("context");
        $this->twig = $request->getAttribute("twig");
        $response = new Response();
        $this->processProps();
        $this->processVerb("created");

        foreach ($this->middleware as $middleware) {

            $file = $this->app->root . DIRECTORY_SEPARATOR . "middleware" . DIRECTORY_SEPARATOR . $middleware . ".php";
            if (file_exists($file)) {
                $m = require_once($file);
                if ($m instanceof Closure) {
                    $m->call($this->component, $this->context);

                    if ($context->_redirected) {
                        $response = $response->withHeader("location", $content->_redirected_url);
                        return $response;
                    }
                }
            }
        }

        //--- entry ---
        $params = $request->getQueryParams();
        if ($entry = $params["_entry"]) {
            $ret = $this->processEntry($entry);
            if ($ret instanceof ResponseInterface) {
                return $ret;
            }
            $response = $response->withHeader("Content-Type", "application/json");
            $response->getBody()->write(json_encode($ret, JSON_UNESCAPED_UNICODE));
            return $response;
        }


        //--- method ---
        $verb = $request->getMethod();
        if ($verb == "GET") { //load layout
            //    $layout = $this->getLayout();
        }

        try {
            ob_start();
            $ret = $this->processVerb($verb);
            $content = ob_get_contents();
            ob_end_clean();
        } catch (Exception $e) {
            $content = ob_get_contents();
            ob_end_clean();
            throw new Exception($e->getMessage(), $e->getCode());
        }

        if ($ret instanceof ResponseInterface) {
            return $ret;
        }

        if (is_array($ret) || $ret instanceof JsonSerializable) {
            $response = $response->withHeader("Content-Type", "application/json");
            $response->getBody()->write(json_encode($ret, JSON_UNESCAPED_UNICODE));
            return $response;
        }

        //$this->app->callHook("render:before", $this);
        $response =  $response->withBody(new StringStream($this->render("")));

        if ($verb == "GET") {

            //    $response = $response->withBody(new StringStream($layout->render($response->getBody()->getContents())));
        }
        return $response;
    }

    private function processVerb(string $verb)
    {
        if (is_object($this->stub)) {
            $ref_obj = new ReflectionObject($this->stub);
            if ($ref_obj->hasMethod($verb)) {

                $ref_method = $ref_obj->getMethod($verb);

                $args = [];
                $context_class = new ReflectionClass($this->context);
                foreach ($ref_method->getParameters() as $param) {
                    if ($type = $param->getType()) {

                        if ($type == $context_class->getName()) {

                            $args[] = $this->context;
                        } elseif ($type == RequestInterface::class) {
                            $args[] = $this->context->request;
                        } elseif ($type == ResponseInterface::class) {
                            $args[] = $this->context->resp;
                        } else {
                            $args[] = null;
                        }
                    } else {
                        $args[] = null;
                    }
                }

                return $ref_obj->getMethod($verb)->invoke($this->stub, ...$args);
            }
        } else {
            $func = $this->stub[strtolower($verb)];
            if ($func instanceof Closure) {
                $ret = $func->call($this->component, $this->context);

                if ($ret instanceof Generator) {
                    return iterator_to_array($ret);
                }
                return $ret;
            }
        }
    }

    public function processEntry(string $entry)
    {
        if (is_object($this->stub)) {
            return $this->processVerb($entry);
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

    private function getTwigEnvironment(): \Twig\Environment
    {
        return $this->twig;
    }

    public function render($puxt)
    {

        $twig_env = $this->getTwigEnvironment();

        if (is_object($this->stub)) {
            $stub = $this->stub;
            $ref_obj = new ReflectionObject($this->stub);
            foreach ($ref_obj->getMethods(ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PUBLIC) as $method) {

                $twig_env->addFunction(new TwigFunction($method->name, function () use ($method, $stub) {

                    $args = func_get_args();
                    $name = $method->name;
                    return  Closure::bind(
                        function ($class) use ($name, $args) {
                            return call_user_func_array([$class, $name], $args);
                        },
                        $stub,
                        get_class($stub)
                    )($stub);
                }));
            }
            $data = (array)$this->stub;
            $data["_params"] = $this->context->params;
            $data["_route"] = $this->context->route;
            $data["_config"] = $this->context->config;
            $name = $this->context->config["context"]["name"] ?? "_puxt";
            $data[$name] = $this->context;
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

        try {
            $twig_file = substr($this->file, 0, -strlen("php")) . "twig";
            if (file_exists($twig_file)) {
                $twig = $twig_env->load(basename($twig_file));
            } else {
                $twig_env->setLoader(new \Twig\Loader\ArrayLoader([
                    'page' => $this->twig_content,
                ]));
                $twig = $twig_env->load("page");
            }

            $ret = $twig->render($data);
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return $ret;
    }
}