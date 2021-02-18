<?php

return [
    "props" => [
        "s" => [
            "type" => "string",
            "default" => "abc"
        ],
        "i" => [
            "type" => "int",
            "required" => true
        ]
    ],
    "created" => function () {
        var_dump($this->s);
        var_dump($this->i);
    }

];
