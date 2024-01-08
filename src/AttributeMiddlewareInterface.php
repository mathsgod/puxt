<?php

namespace PUXT;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionAttribute;

interface AttributeMiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler, ReflectionAttribute $attribute): ResponseInterface;
}
