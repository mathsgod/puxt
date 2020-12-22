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
        ]
    ],
    "db" => [
        "hostname" => "127.0.0.1",
        "port" => 3306,
        "username" => "admin",
        "password" => "111111"
    ],
    "i18n" => [
        "locales" => ['en', 'fr', 'es'],
        "defaultLocale" => "en"
    ]
];
