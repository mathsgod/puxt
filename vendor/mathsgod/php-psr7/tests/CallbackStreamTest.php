<?php

declare(strict_types=1);
//error_reporting(E_ALL && ~E_WARNING && ~E_NOTICE);
use PHPUnit\Framework\TestCase;

use PHP\Psr7\CallbackStream;

final class CallbackStreamTest extends TestCase
{
    public function test_create()
    {
        $s = new CallbackStream(function () {
            return "hello world";
        });


        $this->assertEquals("hello world", (string)$s);
    }
}
