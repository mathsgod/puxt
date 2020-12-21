<?php

declare(strict_types=1);
//error_reporting(E_ALL && ~E_WARNING && ~E_NOTICE);
use PHPUnit\Framework\TestCase;

use PHP\Psr7\JsonStream;

final class JsonStreamTest extends TestCase
{
    public function test_create()
    {
        $s = new JsonStream(["a" => "hello"]);

        $this->assertEquals('{"a":"hello"}', (string)$s);
    }

    public function test_write()
    {

        $s = new JsonStream(["a" => "hello"]);
        $s->write(["b" => "world"]);

        $this->assertEquals('{"a":"hello","b":"world"}', (string)$s);
    }
}
