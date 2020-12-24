<?php

namespace PUXT\I18n;

use PHP\Psr7\ServerRequest;

class App
{

    public $root;
    public $config;
    public $request;

    public function __construct(string $root, array $config, string $path)
    {
        $this->root = $root;
        $this->config = $config;
        $this->request = new ServerRequest();
        $this->path = $path;
    }

    public function run()
    {

        if ($this->path == "getFolder") {
            $res = $this->getFolder($_GET["path"]);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);;
        }
    }

    public function getFolder(string $path = "")
    {

        $ret = [];

        foreach (glob($this->root . "/$path/*.twig") as $f) {

            $name = substr($f, strlen($this->root . "/$path/"));
            $ret[] = $name;
        }
        return $ret;
    }
}
