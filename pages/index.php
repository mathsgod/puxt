<div>{{first_name}}</div>
<form method="POST">
    <input type="input" name="input1" value="abc" />
    <button type="submit">Submit</button>
</form>
<?php

return [
    "post" => function ($post) {
        print_r($post);
    },
    "data" => function () {

        return [
            "first_name" => "raymond"
        ];
    }
];
