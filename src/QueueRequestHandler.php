<?php

namespace PUXT;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class QueueRequestHandler implements RequestHandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    private $middleware = [];
    private $fallbackHandler;

    public function __construct(RequestHandlerInterface $fallbackHandler)
    {
        $this->fallbackHandler = $fallbackHandler;
    }

    public function add(MiddlewareInterface $middleware)
    {
        $this->middleware[] = $middleware;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Last middleware in the queue has called on the request handler.
        if (0 === count($this->middleware)) {

            if ($this->logger && $this->fallbackHandler instanceof LoggerAwareInterface) {
                $this->fallbackHandler->setLogger($this->logger);
            }

            return $this->fallbackHandler->handle($request);
        }

        $middleware = array_shift($this->middleware);


        if ($this->logger && $middleware instanceof LoggerAwareInterface) {
            $middleware->setLogger($this->logger);
        }

        return $middleware->process($request, $this);
    }
}
