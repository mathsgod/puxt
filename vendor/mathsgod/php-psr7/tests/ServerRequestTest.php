<?php

declare(strict_types=1);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

use PHPUnit\Framework\TestCase;

use PHP\Psr7\ServerRequest;

final class ServerRequestTest extends TestCase
{

    public function test_create()
    {
        $server = [
            "METHOD" => "GET",
            "SERVER_NAME" => "127.0.0.1",
            "SERVER_PORT" => 80,
            "REQUEST_URI" => "/cms/User/test?a=1&b=2",
            "SCRIPT_NAME" => "/cms/index.php",
            "REQUEST_SCHEME" => "http",
            "QUERY_STRING" => "a=1&b=2"
        ];

        $r = new ServerRequest($server);
        $this->assertInstanceOf(ServerRequest::class, $r);
        $this->assertEquals("GET", $r->getMethod());
        $this->assertEquals("http://127.0.0.1/cms/User/test?a=1&b=2", (string)$r->getUri());
    }
}
