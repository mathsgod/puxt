<?php

namespace PHP\Psr7;

class FileStream extends Stream
{
    public function __construct(string $file, string $mode = "r+")
    {
        $stream = fopen($file, $mode);
        parent::__construct($stream);
    }
}
