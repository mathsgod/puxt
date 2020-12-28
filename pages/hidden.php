hidden
page

<?php

return [
    "middleware" => ["auth"],
    "created" => function ($context) {
        $context->db->hello("a");
    }
];
