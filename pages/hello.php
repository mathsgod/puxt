<?php

return [
    "layout" => "abc",
    "head" => function () {
        return [
            "title" => rand(1, 10),
            "htmlAttrs" => [
                "lang" => "zh-hk"
            ],
        ];
    }

];
