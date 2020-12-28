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

    /*  "i18n" => [
        "locales" => ['en', 'fr', 'es'],
        "defaultLocale" => "en"
    ] */

    "database" => [
        "hostname" => "127.0.0.1",
        "username" => "root",
        "password" => "111111",
        "port" => 3306,
        "database" => "raymond",
    ],
    "modules" => [
        "hostlink/puxt-db"
    ]
];
