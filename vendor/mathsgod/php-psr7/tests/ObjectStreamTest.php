<?php

declare(strict_types=1);
//error_reporting(E_ALL && ~E_WARNING && ~E_NOTICE);
use PHPUnit\Framework\TestCase;

use PHP\Psr7\ObjectStream;
use PHP\Psr7\StringStream;

final class ObjectStreamTest extends TestCase
{
    public function test_create()
    {
        $o = new ObjectStream();
        $o->write(new StringStream("hello"));
        $o->write(new StringStream(" world"));

        $this->assertEquals("hello world", (string)$o);
    }
}
