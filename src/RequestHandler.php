<?php

namespace PUXT;

use Closure;
use Exception;
use League\Event\EventDispatcherAware;
use League\Event\EventDispatcherAwareBehavior;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

abstract class RequestHandler implements RequestHandlerInterface, LoggerAwareInterface, EventDispatcherAware
{
    use LoggerAwareTrait;
    use EventDispatcherAwareBehavior;

    protected $file;

    function __construct(string $file)
    {
        $this->file = $file;
    }

    public static function Create(string $file): RequestHandlerInterface
    {
        if (file_exists($file . ".php")) {
            $php = new PHPRequestHandler($file . ".php");
            $queue = new QueueRequestHandler($php);
            foreach ($php->middleware as $middleware) {
                $queue->add($middleware);
            }
            return $queue;
        } elseif (file_exists($file . ".twig")) {
            return new TwigRequestHandler($file . ".twig");
        } elseif (file_exists($file . ".html")) {
            return new HTMLRequestHandler($file . ".html");
        } elseif (file_exists($file . ".vue")) {
            return new VueRequestHandler($file . ".vue");
        } else {
            throw new Exception("Not found file: " . $file . " .php, .twig , .html or .vue");
        }
    }
}
