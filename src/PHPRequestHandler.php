<?php

namespace PUXT;

use Closure;
use Exception;
use JsonSerializable;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\ServiceManager\ServiceManager;
use League\Route\Http\Exception\HttpExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use ReflectionObject;
use Twig\TwigFunction;
use \Psr\Http\Server\MiddlewareInterface;
use Twig\Environment;

class PHPRequestHandler extends RequestHandler
{
    private $stub;

    private $twig_content;
    private $layout;
    private $app;
    private $service;

    /**
     *  @var MiddlewareInterface []
     **/
    public $middleware = [];

    function __construct(string $file)
    {
        parent::__construct($file);

        ob_start();
        $this->stub = require($file);
        $this->twig_content = ob_get_contents();
        ob_end_clean();

        $this->layout = $this->stub->layout ?? "default";

        foreach ($this->stub->middleware ?? [] as $middleware) {
            $file = getcwd() . DIRECTORY_SEPARATOR . "middleware" . DIRECTORY_SEPARATOR . $middleware . ".php";
            if (file_exists($file)) {
                $middleware = require($file);
                if ($middleware instanceof MiddlewareInterface) {
                    $this->middleware[] = $middleware;
                }
            }
        }
    }

    function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->service = $request->getAttribute(ServiceManager::class);
        $this->app = $this->service->get(App::class);

        $response = $this->handleRequest($request);

        if ($request->getMethod() == "GET") {
            try {
                $h = RequestHandler::Create("layouts/" . $this->layout);
                $request = $request->withAttribute("context", [
                    "puxt" => $response->getBody()->getContents()
                ]);

                $response = $h->handle($request);
            } catch (Exception $e) {
            }
        }

        return $response;
    }

    private function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->stub instanceof RequestHandlerInterface) {
            $response = $this->stub->handle($request);
            //head
            if ($this->stub->head) {
                $response = $response->withHeader("puxt-head", json_encode($this->stub->head, JSON_UNESCAPED_UNICODE));
            }
            return $response;
        } else {

            $this->processVerb("created", $request);

            //--- entry ---
            $params = $request->getQueryParams();

            if (in_array("_entry", $params) || $entry = $params["_entry"]) {
                $ret = $this->processEntry($entry, $request);
                if ($ret instanceof ResponseInterface) {
                    return $ret;
                }
                return new JsonResponse($ret);
            }

            //--- method ---
            $verb = $request->getMethod();

            try {
                ob_start();
                $ret = $this->processVerb($verb, $request);
                ob_get_contents();
                ob_end_clean();
            } catch (HttpExceptionInterface $e) {
                ob_get_contents();
                ob_end_clean();
                throw $e;
            } catch (Exception $e) {
                ob_get_contents();
                ob_end_clean();
                throw new Exception($e->getMessage(), $e->getCode());
            }

            if ($ret instanceof ResponseInterface) {
                return $ret;
            }

            if (is_array($ret) || $ret instanceof JsonSerializable) {
                return new JsonResponse($ret, 200, [], JsonResponse::DEFAULT_JSON_FLAGS | JSON_UNESCAPED_UNICODE);
            }

            if (is_string($ret)) {
                return new TextResponse($ret);
            }

            if ($verb == "GET") {

                return new HtmlResponse($this->render($request->getAttribute(\Twig\Environment::class)));
            }

            return new EmptyResponse(200);
        }
    }


    private function processVerb(string $verb, ServerRequestInterface $request)
    {
        /**
         * @var ContainerInterface
         */
        $container = $request->getAttribute(ServiceManager::class);

        $ref_obj = new ReflectionObject($this->stub);
        if ($ref_obj->hasMethod($verb)) {

            $ref_method = $ref_obj->getMethod($verb);

            $args = [];

            foreach ($ref_method->getParameters() as $param) {
                if ($type = $param->getType()) {

                    if ($container->has($type->getName())) {
                        $args[] = $container->get($type->getName());
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

    public function processEntry(string $entry, ServerRequestInterface $request)
    {
        return $this->processVerb($entry, $request);
    }

    public function render(?Environment $twig_env)
    {

        if (!$twig_env) {
            throw new Exception("twig env is null");
        }

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

        try {
            $twig_file = substr($this->file, 0, -strlen("php")) . "twig";

            if (file_exists($twig_file)) {

                //remove root
                $twig_file = substr($twig_file, strlen($this->app->root));

                $twig = $twig_env->load($twig_file);
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
