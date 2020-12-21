<?php

namespace PHP\Psr7;

use Psr\Http\Message\ResponseInterface;

class Response extends Message implements ResponseInterface
{
    protected $code;
    protected $reasonPhrase = '';

    public function __construct(int $status = 200, array $headers = [], $body = null, string $version = '1.1')
    {
        $this->code = $status;
        parent::__construct($headers, $body, $version);
    }

    public function getStatusCode()
    {
        return $this->code;
    }

    public function getReasonPhrase()
    {
        return $this->reasonPhrase;
    }

    public function withStatus($code, $reasonPhrase = '')
    {
        $clone = clone $this;
        $clone->code = $code;
        $clone->reasonPhrase = $reasonPhrase;
        return $clone;
    }
}
