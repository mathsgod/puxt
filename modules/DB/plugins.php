<?php
class DB
{
    public function hello()
    {
        print_r("hellossss");
    }
}

return function ($context, $inject) {

    print_r($this->config["database"]);
    $inject("db", new DB());
};
