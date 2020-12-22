<?php

namespace PUXT;

class View
{
    public $_methods = [];

    public function __call($methodName, array $args)
    {
        if (isset($this->_methods[$methodName])) {
            return call_user_func_array($this->_methods[$methodName], $args);
        }
        //throw RunTimeException('There is no method with the given name to call');
    }
}
