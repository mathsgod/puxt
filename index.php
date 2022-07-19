<?php

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use PUXT\App;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once("vendor/autoload.php");

(new SapiEmitter())->emit((new App)->handle(ServerRequestFactory::fromGlobals()));
