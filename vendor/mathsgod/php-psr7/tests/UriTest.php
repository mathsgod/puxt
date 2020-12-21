<?php

declare(strict_types=1);
//error_reporting(E_ALL && ~E_WARNING);

use PHPUnit\Framework\TestCase;

use PHP\Psr7\Uri;

final class UriTest extends TestCase
{
    public function getURI()
    {
        return (new URI)
            ->withScheme("http")
            ->withUserInfo("a", "b")
            ->withHost("raymond2.hostlink.com.hk")
            ->withPath("/cms/testing/download");
    }

    public function test_construct()
    {
        $uri = new Uri("https://a:b@raymond3.hostlink.com.hk:8081/test/def?g=1&h=2#/i/j/k");
        $this->assertEquals("https", $uri->getScheme());
        $this->assertEquals("a:b", $uri->getUserInfo());
        $this->assertEquals("raymond3.hostlink.com.hk", $uri->getHost());
        $this->assertEquals(8081, $uri->getPort());
        $this->assertEquals("/test/def", $uri->getPath());
        $this->assertEquals("g=1&h=2", $uri->getQuery());
        $this->assertEquals("/i/j/k", $uri->getFragment());
        $this->assertEquals("https://a:b@raymond3.hostlink.com.hk:8081/test/def?g=1&h=2#/i/j/k", (string)$uri);
    }

    public function test_getAuthority()
    {
        $uri = $this->getURI();
        $this->assertEquals("a:b@raymond2.hostlink.com.hk", $uri->getAuthority());

        $uri = $uri->withPort(8080);
        $this->assertEquals("a:b@raymond2.hostlink.com.hk:8080", $uri->getAuthority());




        $uri = $uri->withPort(80); //standard port
        $this->assertEquals("a:b@raymond2.hostlink.com.hk", $uri->getAuthority());
    }

    public function test_getScheme()
    {
        $this->assertEquals("http", $this->getURI()->getScheme());
    }

    public function test_port()
    {
        $uri = $this->getURI();
        $this->assertNull($uri->getPort());
    }


    public function test_withPath()
    {
        $uri = $this->getURI();

        $this->assertEquals("http://a:b@raymond2.hostlink.com.hk/cms/testing/download", (string) $uri);
    }

    public function test_host()
    {
        $uri = $this->getURI();
        $this->assertEquals("raymond2.hostlink.com.hk", $uri->getHost());

        $uri = $uri->withHost('raymond.hostlink.com.hk');
        $this->assertEquals("raymond.hostlink.com.hk", $uri->getHost());
    }

    public function test_path()
    {
        $uri = $this->getURI();
        $this->assertEquals("/cms/testing/download", $uri->getPath());

        $uri = $uri->withPath("/testing2/abc");
        $this->assertEquals("/testing2/abc", $uri->getPath());


        $this->assertEquals("http://a:b@raymond2.hostlink.com.hk/testing2/abc", (string) $uri);
    }

    public function test_query()
    {
        $uri = $this->getURI();
        $uri = $uri->withQuery("a=1&b=2&c=3");
        $this->assertEquals("a=1&b=2&c=3", $uri->getQuery());

        $uri = $uri->withQuery("x=4&y=5&z=6");
        $this->assertEquals("x=4&y=5&z=6", $uri->getQuery());
    }

    public function test_fragment()
    {
        $uri = $this->getURI();
        $uri = $uri->withFragment("hash/x/1");
        $this->assertEquals("hash/x/1", $uri->getFragment());

        $uri = $uri->withFragment("hello/a/2");
        $this->assertEquals("hello/a/2", $uri->getFragment());

        $this->assertEquals("http://a:b@raymond2.hostlink.com.hk/cms/testing/download#hello/a/2", (string) $uri);
    }
}
