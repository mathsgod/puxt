<?php

declare(strict_types=1);
//error_reporting(E_ALL && ~E_WARNING);

use PHP\Psr7\Request;
use PHP\Psr7\Uri;
use PHPUnit\Framework\TestCase;


final class RequestTest extends TestCase
{
    public function testCreate()
    {
        $uri = new Uri;
        $request = new Request("GET", new Uri());

        $this->assertInstanceOf(Request::class, $request);
    }
}
