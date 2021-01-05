<?php

return [
    "head" => function () {

        return  [
            "title" => $this->title,
            "meta" => [
                ["name" => "raymond", "content" => "123"]
            ]
        ];
    },
    "data" => function () {

        return ["title" => "abc"];
    },
    "created" => function () {
        //  $this

        $this->title = "xyz";
    }, "post" => function () {
        
    }
];
