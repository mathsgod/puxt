<?php

namespace PUXT;

class Loader
{
    public $path;
    public function __construct(string $path, $app)
    {
        $this->path = $path;
        $this->app = $app;
    }

    public function render($puxt = null)
    {
        $ret = [];

        $data = [];
        if (file_exists($this->path . ".php")) {

            $php = require_once($this->path . ".php");

            if ($php["data"]) {
                $data = $php["data"]->call($this);
            }

            if ($php["layout"]) {
                $ret["layout"] = $php["layout"];
            }
        }


        if (file_exists($this->path . ".twig")) {
            $twig = $this->app->twig->load($this->path . ".twig");

            if ($puxt) {
                $data["puxt"] = $puxt;
            }

            $ret["html"] = $twig->render($data);
        }

        return $ret;
    }
}
