<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once("vendor/autoload.php");
$app = new PUXT\App(__DIR__);

$app->run();
