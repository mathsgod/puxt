<?php

namespace PUXT;

use Closure;
use Exception;

class Context
{
    public $params;
    public $_redirected = false;
    public $_redirected_url;

    /**
     * @property Route $route
     */
    public $route = null;

    public function redirect(string $url)
    {
        $this->_redirected = true;
        $this->_redirected_url = $url;
    }

    public function __call($name, $args)
    {
        if ($this->$name instanceof Closure) {
            return  call_user_func_array($this->$name, $args);
        } else {
            throw new Exception("method " . $name . " not found");
        }
    }
}
