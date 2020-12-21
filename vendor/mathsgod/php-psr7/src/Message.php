<?php

namespace PHP\Psr7;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

class Message implements MessageInterface
{
    protected $protocolVersion;
    protected $headers = [];
    protected $body;

    public function __construct(array $headers = [], $body = null, string $version = "1.1")
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = array_map("trim", explode(",", $value));
        }

        if (is_string($body)) {
            $this->body = new StringStream($body);
        } elseif ($body instanceof StreamInterface) {
            $this->body = $body;
        } else {
            $this->body = new Stream($body);
        }

        $this->protocolVersion = $version;
    }

    public function __clone()
    {
        $this->body = clone $this->body;
    }

    public function getHeader($name)
    {
        $headers = array_change_key_case($this->headers);
        return $headers[strtolower($name)] ?? [];
    }

    public function getHeaderLine($name)
    {
        return implode(",", $this->getHeader($name));
    }

    public function hasHeader($name)
    {
        $names = array_keys(array_change_key_case($this->headers));
        return in_array(strtolower($name), $names);
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getProtocolVersion()
    {
        return $this->protocolVersion;
    }

    public function withAddedHeader($name, $value)
    {
        $clone = clone $this;


        if ($clone->hasHeader($name)) {
            foreach ($clone->headers as $n => $v) {

                if (strtolower($n) == strtolower($name)) {
                    if (is_array($value)) {
                        foreach ($value as $v1) {
                            $v[] = (string)$v1;
                        }
                    } else {
                        $v[] = (string)$value;
                    }

                    $clone->headers[$n] = $v;
                    break;
                }
            }
        } else {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $clone->headers[$name][] = (string)$v;
                }
            } else {
                $clone->headers[$name][] = (string)$value;
            }
        }


        return $clone;
    }

    public function withBody(StreamInterface $body)
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    public function withHeader($name, $value)
    {
        $value = is_array($value) ? $value : [$value];
        $clone = (clone $this)->withoutHeader($name);
        foreach ($value as $v) {
            $clone->headers[$name][] = $v;
        }
        return $clone;
    }

    public function withoutHeader($name)
    {
        $clone = clone $this;
        foreach ($clone->headers as $n => $value) {
            if (strtolower($n) == strtolower($name)) {
                unset($clone->headers[$n]);
            }
        }
        return $clone;
    }

    public function withProtocolVersion($version)
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }
}
