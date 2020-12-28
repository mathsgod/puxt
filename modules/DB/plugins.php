<?php
return function ($context, $inject) {

    $inject("db", new DB());
};
