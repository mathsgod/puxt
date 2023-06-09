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
        $twig = $request->getAttribute(\Twig\Environment::class);
        $context = $request->getAttribute("context", []);

        if ($twig->getLoader()->exists($this->file)) {
            $html = $twig->render($this->file, $context);
            return new HtmlResponse($html);
        } else {

            //create load
            $twig->setLoader(new \Twig\Loader\ArrayLoader([
                "page" => file_get_contents($this->file)
            ]));


            $html = $twig->render("page", $context);
            return new HtmlResponse($html);
        }
    }
}
