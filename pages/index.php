<?php

return [
    "data" => function () {
        return [
            "first_name" => "raymond"
        ];
    },
    "created" => function () {
        print_r($this);
        echo "creatd";
        return "a";
    }
];
