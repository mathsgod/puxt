<?php

namespace PUXT;

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Twig\Environment;

class TwigRequestHandler extends RequestHandler
{
    function handle(ServerRequestInterface $request): ResponseInterface
    {

        /** @var Environment $twig */
        $twig = $request->getAttribute('twig');
        $context = $request->getAttribute('context');


        return new HtmlResponse($twig->render($this->file, (array)$context));
    }
}
