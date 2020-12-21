<?php

namespace PHP\Psr7;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class Request extends Message implements RequestInterface
{
    protected $uri;
    protected $method;
    protected $requestTarget;

    public function __construct(string $method, UriInterface $uri, array $headers = [], $body = null, $version = '1.1')
    {
        $this->method = $method;
        $this->uri = $uri;

        parent::__construct($headers, $body, $version);
    }

    public function getRequestTarget()
    {
        if ($this->requestTarget) {
            return $this->requestTarget;
        }
        $uri = $this->getUri();
        $target = $uri->getPath();
        if ($query = $uri->getQuery()) {
            $target .= "?" . $query;
        }
        if (empty($target)) {
            $target = '/';
        }
        return $target;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function withMethod($method)
    {
        $clone = clone $this;
        $clone->method = $method;
        return $clone;
    }

    public function withRequestTarget($requestTarget)
    {
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $clone = clone $this;
        $clone->uri = $uri;
        if (!$preserveHost) {
            if ($uri->getHost() !== '') {
                $clone = $clone->withHeader("Host", $uri->getHost());
            }
        } else {
            if ($uri->getHost() !== '' && (!$this->hasHeader('Host') || $this->getHeaderLine('Host') === '')) {
                $clone = $clone->withHeader("Host", $uri->getHost());
            }
        }
        return $clone;
    }
}
