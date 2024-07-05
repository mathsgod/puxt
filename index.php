<?php

use Laminas\Di\Injector;
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
        if ($attribute->getName() == "Logged") {
            //            return new HtmlResponse("logged");
        }
        return $handler->handle($request);
    }
}

class User
{
    public function getName()
    {
        return "Raymond";
    }
}
class InjectedUser implements PUXT\ParameterHandlerInterface
{
    public function handle(ServerRequestInterface $request, ReflectionAttribute $attribute, ReflectionParameter $parameter): mixed
    {
        //if ($parameter->getType()->getName() == "InjectedUser") {
        return new User;
        //}
    }
}

$req = ServerRequestFactory::fromGlobals();

$app = new App;

/* app->pipe(new class implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new HtmlResponse("Hello World3");
    }
});

\ */


/* $app->addAttributeMiddleware(new Middleware);

$app->addParameterHandler(Injector::class, new InjectedUser);
 */

/* $app->getServiceManager()->setService(Injector::class, function(){
    return "abc";
});
 */
$app->run();
