<?php

class DB
{
    public function hello()
    {
        print_r("hellossss");
    }
}

return function ($options) {
    $this->addPlugin(__DIR__ . "/plugins.php");
};
