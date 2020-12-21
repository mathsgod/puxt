<?php

namespace PHP\Psr7;

use Psr\Http\Message\StreamInterface;

class ObjectStream extends StringStream implements StreamInterface
{
    protected $_data = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function write($obj)
    {
        $this->_data[] = $obj;
    }

    public function getContents()
    {
        ftruncate($this->stream, 0);
        foreach ($this->_data as $obj) {
            parent::write((string) $obj);
        }
        return parent::getContents();
    }
}
