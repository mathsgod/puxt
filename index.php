<?php

use PUXT\App;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once("vendor/autoload.php");

(new App)->run();
