<?php

namespace PUXT;

use Closure;
use Exception;
use Generator;
use JsonSerializable;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use League\Route\Http\Exception\HttpExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use Twig\TwigFunction;
use \Psr\Http\Server\MiddlewareInterface;

class PHPRequestHandler extends RequestHandler
{
    private $stub;
    private $twig;
    private $context;
    private $request;

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
                $this->middleware[] = require($file);
            }
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        if ($this->stub instanceof LoggerAwareInterface) {
            $this->stub->setLogger($logger);
        }
    }

    function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;
        if ($this->stub instanceof RequestHandlerInterface) {
            $response = $this->stub->handle($request);
            //head
            if ($this->stub->head) {
                $response = $response->withHeader("puxt-head", json_encode($this->stub->head, JSON_UNESCAPED_UNICODE));
            }
            return $response;
        } else {

            $this->context = $request->getAttribute("context");
            $this->twig = $request->getAttribute("twig");

            $this->processProps();
            $this->processVerb("created");


            //--- entry ---
            $params = $request->getQueryParams();
            if ($entry = $params["_entry"]) {
                $ret = $this->processEntry($entry);
                if ($ret instanceof ResponseInterface) {
                    return $ret;
                }
                return new JsonResponse($ret);
            }

            //--- method ---
            $verb = $request->getMethod();

            try {
                ob_start();
                $ret = $this->processVerb($verb);
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
                return new JsonResponse($ret);
            }

            if (is_string($ret)) {
                return new TextResponse($ret);
            }

            if ($verb == "GET") {
                return new HtmlResponse($this->render(""));
            }

            return new EmptyResponse(200);
        }
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
                        } elseif (is_a($type->getName(), RequestInterface::class, true)) {
                            $args[] = $this->request;
                        } elseif (is_a($type->getName(), ResponseInterface::class, true)) {
                            $args[] = $this->context->resp;
                        } elseif (is_a($type->getName(), EventDispatcherInterface::class, true)) {
                            $args[] = $this->eventDispatcher();
                        } elseif (is_a($type->getName(), LoggerInterface::class, true)) {
                            $args[] = $this->logger;
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
