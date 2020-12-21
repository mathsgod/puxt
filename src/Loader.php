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

    public function post(array $body = [])
    {
        if (file_exists($this->path . ".php")) {

            ob_start();
            $php = require_once($this->path . ".php");
            $twig_content = ob_get_contents();
            ob_end_clean();

            if ($php["post"]) {
                return $php["post"]->call($this, $body);
            }
        }
    }

    public function render($puxt = null)
    {
        $ret = [
            "layout" => "default"
        ];

        $data = [];
        $twig_content = "";
        if (file_exists($this->path . ".php")) {

            ob_start();
            $php = require_once($this->path . ".php");
            $twig_content = ob_get_contents();
            ob_end_clean();

            if ($php["data"]) {
                $data = $php["data"]->call($this);
            }

            if ($php["layout"]) {
                $ret["layout"] = $php["layout"];
            }
        }

        if ($puxt) {
            $data["puxt"] = $puxt;
        }

        if (file_exists($this->path . ".twig")) {
            $twig = $this->app->twig->load($this->path . ".twig");
            $ret["html"] = $twig->render($data);
        } else {

            $twig_loader = new \Twig\Loader\ArrayLoader([
                'page' => $twig_content,
            ]);
            $twig = new \Twig\Environment($twig_loader);

            $ret["html"] = $twig->render("page", $data);
        }

        return $ret;
    }
}
