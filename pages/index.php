<div>{{first_name}}</div>
<form method="POST">
    <input type="input" name="input1" value="abc" />
    <button type="submit">Submit</button>
</form>
<?php

return [
    "head" => function () {
        return [
            "htmlAttrs" => [
                "lang" => "zh-hk"
            ],
            "title" => $this->first_name . " " . $this->getLastName(),
            "meta" => [
                ["hid" => "description", "name" => "description", "content" => "index"]
            ]
        ];
    },
    "data" => function () {
        return [
            "first_name" => "raymond",
            "last_name" => "chong"
        ];
    },
    "created" => function ($context) {
        //created
        $this->first_name = "hello";
        $this->last_name = "world";


        $context->log->info("hello");
    },
    "post" => function ($context) {
    },
    "methods" => [
        "getLastName" => function () {
            return $this->last_name;
        }
    ]
];
