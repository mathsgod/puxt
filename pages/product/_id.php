<?php
return [
    "data" => [
        "id" => "abc"
    ],
    "created" => function ($params) {
        $this->id = $params->id;
    }
];
