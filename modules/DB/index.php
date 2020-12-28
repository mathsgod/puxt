<?php

class DB
{
    public function hello()
    {
        print_r("hellossss");
    }
}

return function ($context, $inject) {

    $inject("db", new DB());
};
