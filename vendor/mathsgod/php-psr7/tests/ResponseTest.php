<?php

declare(strict_types=1);
//error_reporting(E_ALL && ~E_WARNING);
use PHPUnit\Framework\TestCase;


use PHP\Psr7\Response;


final class ResponseTest extends TestCase
{
    public function testCreate()
    {
        $r = new Response;

        $this->assertInstanceOf(Response::class, $r);
    }
}
