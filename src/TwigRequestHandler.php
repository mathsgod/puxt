<?php

namespace PUXT;

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class TwigRequestHandler extends RequestHandler
{
    function handle(ServerRequestInterface $request): ResponseInterface
    {

        $twig = $request->getAttribute('twig');
        $context = $request->getAttribute('context');
        $file = basename($this->file);
        return new HtmlResponse($twig->render($file, (array)$context));
    }
}
