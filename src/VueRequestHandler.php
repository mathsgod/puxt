<?php

namespace PUXT;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class VueRequestHandler implements RequestHandlerInterface
{
    private $file;

    function __construct(string $file)
    {
        $this->file = $file;
    }

    function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = (new ResponseFactory)->createResponse();
        $response = $response->withBody(new \Laminas\Diactoros\Stream(fopen($this->file, 'r')));
        return $response->withHeader('Content-Type', 'text/vue');
    }
}
