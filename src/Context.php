<?php

namespace PUXT;

use Closure;
use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class Context
{
    public $params;
    public $query;
    public $_redirected = false;

    /**
     * @var ServerRequestInterface
     */
    public $request;

    /**
     * @deprecated use $request
     * @var ServerRequestInterface
     */
    public $req;
    /**
     * @var ResponseInterface
     */
    public $resp;

    public $_redirected_url;
    /**
     * @var Route
     */
    public $route = null;
    public $_get = [];
    public $_post = [];
    public $_files = [];
    public $root;


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
