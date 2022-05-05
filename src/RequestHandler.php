<?php

namespace PUXT;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface
{
    /**
     * @var RequestHandlerInterface
     */
    private $handler;

    //file without extension
    public function __construct(string $file)
    {
        if (file_exists($file . ".php")) {
            $this->handler = new PHPRequestHandler($file . ".php");
        } elseif (file_exists($file . ".twig")) {
            $this->handler = new TwigRequestHandler($file . ".twig");
        } elseif (file_exists($file . ".html")) {
            $this->handler = new HTMLRequestHandler($file . ".html");
        } elseif (file_exists($file . ".vue")) {
            $this->handler = new VueRequestHandler($file . ".vue");
        } else {
            throw new \Exception("Not found file: " . $file . " .php, .twig , .html or .vue");
        }
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->handler instanceof PHPRequestHandler) {

            $handler = new QueueRequestHandler($this->handler);

            foreach ($this->handler->middleware as $middleware) {
                $handler->add($middleware);
            }

            return $handler->handle($request);
        }

        return $this->handler->handle($request);
    }
}
