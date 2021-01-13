<?php

return [
    "head" => [
        "title" => "abc",
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
        "hostname" => "127.0.0.1",
        "username" => "root",
        "password" => "111111",
        "port" => 3306,
        "database" => "raymond",
    ],
    "modules" => [
        //"hostlink/puxt-db",
        //"hostlink/puxt-log",
        // "hostlink/puxt-i18n",
        //["hostlink/puxt-i18n", ["username" => "admin", "password" => "111111"]],
        //"hostlink/puxt-gql"
        //"hostlink/puxt-vue-loader",
        //"hostlink/puxt-content"
    ],
    "gql" => [
        "baseURL" => 'https://isapi.hostlink.com.hk:8888/v3/'
    ],
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
