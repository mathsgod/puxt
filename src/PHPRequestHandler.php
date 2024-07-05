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
use Laminas\Stdlib\RequestInterface;
use Laminas\Stratigility\MiddlewarePipe;
use League\Route\Http\Exception\HttpExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use ReflectionObject;
use Twig\TwigFunction;
use \Psr\Http\Server\MiddlewareInterface;
use ReflectionAttribute;
use ReflectionNamedType;
use Twig\Environment;

class PHPRequestHandler extends RequestHandler implements MiddlewareInterface
{
    private $stub;

    private $twig_content;
    private $layout;
    private $app;
    private $service;

    public $middleware;

    function __construct(string $file)
    {
        parent::__construct($file);

        ob_start();
        $this->stub = require($file);
        $this->twig_content = ob_get_contents();
        ob_end_clean();

        $this->layout = $this->stub->layout ?? "default";

        $this->middleware = new MiddlewarePipe();

        foreach ($this->stub->middleware ?? [] as $middleware) {
            $file = getcwd() . DIRECTORY_SEPARATOR . "middleware" . DIRECTORY_SEPARATOR . $middleware . ".php";
            if (file_exists($file)) {
                $middleware = require($file);
                if ($middleware instanceof MiddlewareInterface) {
                    $this->middleware->pipe($middleware);
                }
            }
        }
    }

    function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->middleware->pipe($this);
        return $this->middleware->handle($request);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        $this->service = $request->getAttribute(ServiceManager::class);
        $this->app = $this->service->get(App::class);


        $response = $this->handleRequest($request);

        if ($request->getMethod() == "GET") {
            try {
                $h = RequestHandler::Create($this->app->root . "/layouts/" . $this->layout);
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

            if (isset($params["_entry"]) && $entry = $params["_entry"]) {
                $ret = $this->processEntry($entry, $request);
                if ($ret instanceof ResponseInterface) {
                    return $ret;
                }
                return new JsonResponse($ret);
            }

            //--- method ---
            $verb = $request->getMethod();
            return $this->processVerb($verb, $request);
        }
    }


    private function processVerb(string $verb, ServerRequestInterface $request)
    {
        /**
         * @var ContainerInterface
         */
        $container = $request->getAttribute(ServiceManager::class);
        $app = $container->get(App::class);
        assert($app instanceof App);

        $ref_obj = new ReflectionObject($this->stub);

        if ($ref_obj->hasMethod($verb)) {
            $fallback_handler = new class($this, $this->stub, $verb, $container, $app) implements RequestHandlerInterface
            {
                private $php;
                private $object;
                private $ref_method;
                private $container;
                private $app;

                public function __construct(PHPRequestHandler $php, $object, string $method, ContainerInterface $container, App $app)
                {
                    $this->php = $php;
                    $this->object = $object;
                    $this->ref_method = new ReflectionMethod($this->object, $method);
                    $this->container = $container;
                    $this->app = $app;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {


                    $args = [];

                    foreach ($this->ref_method->getParameters() as $param) {

                        foreach ($param->getAttributes() as $attribute) {
                            if ($handler = $this->app->getParameterHandler($attribute->getName())) {
                                $args[] = $handler->handle($request, $attribute, $param);
                            } else {
                                $args[] = null;
                            }

                            continue 2;
                        }

                        if ($type = $param->getType()) {

                            if ($type->getName() == ServerRequestInterface::class) {
                                $args[] = $request;
                                continue;
                            }

                            if (assert($type instanceof ReflectionNamedType) && $this->container->has($type->getName())) {
                                $args[] = $this->container->get($type->getName());
                            } else {
                                $args[] = null;
                            }
                        } else {
                            $args[] = null;
                        }
                    }

                    ob_start();
                    $ret = $this->ref_method->invoke($this->object, ...$args);
                    ob_get_contents();
                    ob_end_clean();

                    if ($ret instanceof ResponseInterface) {
                        return $ret;
                    }

                    if (is_array($ret) || $ret instanceof JsonSerializable) {

                        return new JsonResponse($ret, 200, [], JsonResponse::DEFAULT_JSON_FLAGS | JSON_UNESCAPED_UNICODE);
                    }

                    if (is_string($ret)) {
                        return new TextResponse($ret);
                    }

                    if ($this->ref_method == "GET") {

                        return new HtmlResponse($this->php->render($request));
                    }

                    return new EmptyResponse(200);
                }
            };

            $request_handler = new class($fallback_handler) implements RequestHandlerInterface
            {
                private $middlewares = [];
                private $fallback;

                public function __construct(RequestHandlerInterface $fallback)
                {
                    $this->fallback = $fallback;
                }


                public function add(AttributeMiddlewareInterface $middleware, ReflectionAttribute $attribute)
                {
                    $this->middlewares[] = [$middleware, $attribute];
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    if (count($this->middlewares) == 0) {
                        //fallback
                        return $this->fallback->handle($request);
                    }
                    $middleware = array_shift($this->middlewares);
                    return $middleware[0]->process($request, $this, $middleware[1]);
                }
            };


            $ref_method = $ref_obj->getMethod($verb);
            foreach ($ref_method->getAttributes() as $attribute) {
                foreach ($app->getAttributeMiddlewares() as $middleware) {
                    $request_handler->add($middleware, $attribute);
                }
            }


            return $request_handler->handle($request);
        }

        return new EmptyResponse(200);
    }

    public function processEntry(string $entry, ServerRequestInterface $request)
    {
        return $this->processVerb($entry, $request);
    }

    public function render(ServerRequestInterface $request)
    {

        $twig_env = $request->getAttribute(\Twig\Environment::class);

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

        $context = $request->getAttribute("context", []);
        $data["puxt"] = $context["puxt"] ?? null;

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
