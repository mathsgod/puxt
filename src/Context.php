<?php

namespace PUXT;

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
}
