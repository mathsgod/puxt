<?php

namespace PUXT;

use Closure;
use Exception;

/**
 * @property Route $route
 * @property \PHP\Psr7\ServerRequest $req
  */
class Context
{
    public $params;
    public $query;
    public $_redirected = false;
    public $req;
    public $_redirected_url;
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
