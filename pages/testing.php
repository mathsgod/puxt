<?php

return [
    "props" => [
        "b" => [
            "type" => "string",
            "default" => 1
        ]
    ],
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
        echo "create";

        $this->title = "xyz";
    }, "post" => function () {
    }, "get" => function () {
        echo "get";
  //      return ["a" => 1, "b" => $this->b];
    }
];
