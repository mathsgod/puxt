<?php

namespace PUXT;

use Closure;
use Exception;
use JsonSerializable;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use Twig\TwigFunction;

class PHPLoader
{
    private $file;
    private $base;
    function __construct(string $file, string $base)
    {
        $this->file = $file;
        $this->base = $base;
    }

    function handle(ServerRequestInterface $request): ResponseInterface
    {
        $context = $request->getAttribute("context");

        $this->context = $context;

        ob_start();
        $stub = require($this->file);
        $twig_content = ob_get_clean();
        $response = new Response();

        $this->processVerb($stub, "created", $request);

        $verb = $request->getMethod();
        try {
            ob_start();
            $ret = $this->processVerb($stub, $verb, $request);
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


        return $response;
    }

    private function getTwig()
    {
        $loader = new \Twig\Loader\FilesystemLoader($this->base);
        echo pathinfo($this->file, PATHINFO_FILENAME);;
        die();

        $ref_obj = new ReflectionObject($this->stub);
        foreach ($ref_obj->getMethods(ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PUBLIC) as $method) {
            $twig->addFunction(new TwigFunction($method->name, function () use ($method, $stub) {

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
    }

    private function render()
    {
        $stub = $this->stub;
        $twig = $this->getTwig();

        $data = (array)$this->stub;
        $data["_params"] = $this->context->params;
        $data["_route"] = $this->context->route;
        $data["_config"] = $this->context->config;
        $name = $this->context->config["context"]["name"] ?? "_puxt";
        $data[$name] = $this->context;
    }

    private function processVerb($stub, string $verb, ServerRequestInterface $request)
    {
        $ref_obj = new ReflectionObject($stub);
        if ($ref_obj->hasMethod($verb)) {
            $ref_method = $ref_obj->getMethod($verb);

            $args = [];
            $context_class = new ReflectionClass($this->context);
            foreach ($ref_method->getParameters() as $param) {
                if ($type = $param->getType()) {

                    if ($type == $context_class->getName()) {
                        $args[] = $this->context;
                    } elseif ($type == RequestInterface::class) {
                        $args[] = $request;
                    } elseif ($type == ResponseInterface::class) {
                        $args[] = new Response();
                    } else {
                        $args[] = null;
                    }
                } else {
                    $args[] = null;
                }
            }

            return $ref_obj->getMethod($verb)->invoke($this->stub, ...$args);
        }
    }
}
