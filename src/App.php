<?php

namespace PUXT;

use PHP\Psr7\ServerRequest;

class App
{
    public $root;
    public $request;
    public $base_path;
    public $document_root;

    public function __construct(string $root)
    {
        $this->root = $root;
        $this->request = new ServerRequest();

        $loader = new \Twig\Loader\FilesystemLoader($this->root);
        $this->twig = new \Twig\Environment($loader);
    }

    public function run()
    {
        //base path
        $this->base_path = dirname($this->request->getServerParams()["SCRIPT_NAME"]);
        if (substr($this->base_path, -1) != "/") {
            $this->base_path .= "/";
        }


        $this->document_root = substr($this->root . "/", 0, -strlen($this->base_path));
        $path = $this->request->getUri()->getPath();

        $request_path = substr($path, strlen($this->base_path));

        if ($request_path == "") {
            $request_path = "index";
        }

        $loader = new Loader("pages/" . $request_path, $this);

        if ($this->request->getMethod() == "POST") {

            $ret = $loader->post($this->request->getParsedBody());
            die();
        }

        $page = $loader->render();


        //page layout

        $layout_loader = new Loader("layouts/" . $page["layout"], $this);
        $layout = $layout_loader->render($page["html"]);


        $app_template = $this->twig->load("app.twig");


        $app_html = $app_template->render(["app" => $layout["html"]]);

        echo $app_html;
    }
}
