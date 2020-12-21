<?php

declare(strict_types=1);
//error_reporting(E_ALL && ~E_WARNING && ~E_NOTICE);
use PHPUnit\Framework\TestCase;

use PHP\Psr7\Stream;

final class StreamTest extends TestCase
{
    public function test_eof()
    {
        $s = new Stream("test");
        $this->assertFalse($s->eof());

        $s->rewind();
        $this->assertEquals("t", $s->read(1));
        $this->assertFalse($s->eof());

        $str = (string) $s;
        $this->assertEquals("test", $str);

        $this->assertTrue($s->eof());
    }

    public function test_tell()
    {
        $s = new Stream("test");
        $this->assertEquals(4, $s->tell());

        $s->rewind();
        $this->assertEquals(0, $s->tell());

        $s->write("123");

        $this->assertEquals(3, $s->tell());

        $this->assertEquals("123t", (string) $s);
    }

    public function test_create()
    {
        $s = new Stream("test");
        $this->assertInstanceOf(Stream::class, $s);
    }

    public function test_toString()
    {
        $s = new Stream("test");
        $this->assertEquals("test", (string) $s);
    }

    public function test_getSize()
    {
        $s = new Stream("test");
        $this->assertEquals(4, $s->getSize());
    }

    public function test_write()
    {
        $s = new Stream("test");
        $s->write("abc");
        $this->assertEquals("testabc", (string) $s);
    }

}
