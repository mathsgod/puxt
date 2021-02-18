<?php

return [
    "props" => [
        "s" => [
            "type" => "string",
            "default" => "abc"
        ],
        "i" => "int",
        "o" => [
            "type" => "object",
            "default" => function () {
                return ["a" => 1];
            }
        ], "a" => [
            "type" => "array",
            "default" => function () {
                return [1, 2, 3, 4];
            }
        ]
    ],
    "created" => function () {
        var_dump($this->a);
    }

];
