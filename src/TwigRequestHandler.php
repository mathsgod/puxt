<?php

namespace PUXT;

use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use PHP\Psr7\StringStream;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TwigRequestHandler extends RequestHandler
{
    function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = (new ResponseFactory)->createResponse();
        $twig = $request->getAttribute('twig');
        $context = $request->getAttribute('context');
        $file = basename($this->file);

        //$response = $response->withBody(new StringStream($twig->render($file, (array)$context)));
        $response = $response->withBody((new StreamFactory)->createStream($twig->render($file, (array)$context)));
        return $response;
    }
}
