<?php

namespace PHP\Psr7;

class CallbackStream extends Stream
{
    protected $callback;
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
        parent::__construct();
    }

    public function getContents()
    {
        return call_user_func($this->callback);
    }
}
