<?php

declare(strict_types=1);
//error_reporting(E_ALL && ~E_WARNING && ~E_NOTICE);
use PHPUnit\Framework\TestCase;

use PHP\Psr7\FileStream;

final class FileStreamTest extends TestCase
{
    public function test_create()
    {
        $s = new FileStream(__DIR__ . "/hello.txt");

        $this->assertInstanceOf(FileStream::class, $s);
        $this->assertEquals("this is testing", (string)$s);
    }
}
