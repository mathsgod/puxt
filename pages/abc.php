<?php
return [
    "layout" => "abc",
    "data" => function () {


        function a()
        {
            return "x2234";
        }


        return ["x1" => a()];
    }
];
