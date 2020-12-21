<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once "vendor/autoload.php";
echo http_build_query([]);
die();

use PHP\Psr7\Message;
use PHP\Psr7\Stream;

$stream = new Stream("test");
$m1 = new Message();
$m1 = $m1->withBody($stream);

$m2 = $m1->withHeader("m2", "a");
$m3 = $m2->withHeader("m3", "b");

print_R((string)$m1->getBody());
echo "\n";
print_R((string)$m2->getBody());
echo "\n";
print_R((string)$m3->getBody());


$stream->write("aaa");

echo "\n";
print_R((string)$m1->getBody());
echo "\n";
print_R((string)$m2->getBody());
echo "\n";
print_R((string)$m3->getBody());
