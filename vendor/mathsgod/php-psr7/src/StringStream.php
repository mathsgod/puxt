<?php

namespace PHP\Psr7;

class StringStream extends Stream
{
    public function __construct(string $data = "")
    {
        $stream = fopen("php://memory", "r+");
        fwrite($stream, $data);
        parent::__construct($stream);
    }
}
