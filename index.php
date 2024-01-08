<?php

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PUXT\App;
use PUXT\AttributeMiddlewareInterface;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once("vendor/autoload.php");

class Middleware implements AttributeMiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler, ReflectionAttribute $attribute): ResponseInterface
    {
        if( $attribute->getName()=="Logged" )
        {
            return new HtmlResponse("test");
        }
        die;
        return $handler->handle($request);
    }
}


$req = ServerRequestFactory::fromGlobals();
$app = new App;
$app->addAttributeMiddleware(new Middleware);
$app->run();
