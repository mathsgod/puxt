<?php

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use PUXT\App;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once("vendor/autoload.php");
/* 
class A implements MiddlewareInterface
{

    function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new HtmlResponse("test");
        
    }
}
 */

(new App)
    //->pipe(new A)
    ->run();
