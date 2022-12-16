<?php

namespace PUXT;

use Exception;
use Psr\Http\Server\RequestHandlerInterface;

abstract class RequestHandler implements RequestHandlerInterface
{

    protected $file;
    protected $container;

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
