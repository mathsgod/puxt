<?php

return [
    "head" => [
        "title" => "Title",
        "meta" => [
            ["charset" => "utf-8"],
            ["name" => "viewport", "content" => "width=device-width, initial-scale=1"],
            ["hid" => "description", "name" => "description", "content" => "my website description"]
        ],
        "htmlAttrs" => [
            "lang" => "en"
        ],
        "bodyAttrs" => [
            "class" => ["dark-mode", "mobile"]
        ],
        "script" => [
            ["src" => "https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"]
        ],
        "link" => [
            [
                "rel" => "stylesheet",
                "href" => "https://fonts.googleapis.com/css?family=Roboto&display=swap"
            ]
        ]
    ],

    "i18n" => [
        "locale_language_mapping" => [
            "en_US" => "en",
            "zh_HK" => "tc"
        ],
        "locales" => ['en_US', 'zh_HK'],
        "defaultLocale" => "en_US"
    ],

    "database" => [
        "hostname" => "",
        "username" => "",
        "password" => "",
        "port" => 3306,
        "database" => "",
    ],
    "modules" => [],
    "log" => [
        "name" => "puxt",
        "handler" => [
            [
                "stream" => __DIR__ . "/log/" . date("Y-m-d") . ".log",
                "level" => Monolog\Logger::INFO
            ]
        ]
    ]
];
