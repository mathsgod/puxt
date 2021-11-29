<?php

namespace PUXT;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface
{
    /**
     * @var RequestHandlerInterface
     */
    private $handler;
    private $extension;

    public function __construct(string $file)
    {

        $this->extension = pathinfo($file, PATHINFO_EXTENSION);
        if ($this->extension == "php") {
            $this->handler = new PHPRequestHandler($file);
        } elseif ($this->extension == "twig") {
            $this->handler = new TwigRequestHandler($file);
        } elseif ($this->extension == "html") {
            $this->handler = new HTMLRequestHandler($file);
        }
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handler->handle($request);
    }
}
