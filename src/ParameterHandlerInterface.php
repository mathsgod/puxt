<?php

namespace PUXT;

use Psr\Http\Message\ServerRequestInterface;
use ReflectionAttribute;
use ReflectionParameter;

interface ParameterHandlerInterface
{
    public function handle(ServerRequestInterface $request, ReflectionAttribute $attribute, ReflectionParameter $parameter);
}
