<?php

declare(strict_types=1);
//error_reporting(E_ALL && ~E_WARNING && ~E_NOTICE);
use PHPUnit\Framework\TestCase;

use PHP\Psr7\StringStream;

final class StringStreamTest extends TestCase
{
    public function test_create()
    {
        $s = new StringStream("hello");

        $this->assertEquals("hello", (string)$s);

        $s->write(" world");
        $this->assertEquals("hello world", (string)$s);
    }
}
