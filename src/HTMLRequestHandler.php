<?php

namespace PUXT;

use Laminas\Diactoros\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HTMLRequestHandler extends RequestHandler
{

    function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = (new ResponseFactory)->createResponse();
        $response = $response->withBody(new \Laminas\Diactoros\Stream(fopen($this->file, 'r')));
        return $response;
    }
}
