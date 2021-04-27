<?php

return function () {
    $this->puxt->hook("ready", function ($puxt) {

        echo "ready";
    });
};
