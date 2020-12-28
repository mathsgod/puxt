<?php

namespace PUXT;

use Closure;

class Context
{
    public $_redirected = false;
    public $_redirected_url;

    public function redirect(string $url)
    {
        $this->_redirected = true;
        $this->_redirected_url = $url;
    }


}
