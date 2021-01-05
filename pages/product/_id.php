<?php
return [
    "data" => [
        "id" => "abc"
    ],
    "created" => function ($context) {
        $this->id = $context->params->id;
    }
];
