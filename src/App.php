<?php

namespace PUXT;

use PHP\Psr7\ServerRequest;
use stdClass;

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

        $data = [
            "layout" => "default"
        ];


        $route = new Route();
        $route->path = $request_path;
        $route->params =  new stdClass;

        if (count(glob($this->root . "/pages/" . $request_path . ".*")) == 0) {

            $e = explode("/", $request_path);
            $params_value = array_pop($e);
            $request_path = implode("/", $e);

            print_R(glob($this->root . "/pages/client/1/_*.*"));
            print_R(glob($this->root . "/pages/client/_*/view.*"));
            print_R(glob($this->root . "/pages/_*/1/view.*"));
            die();
            if (count($files = glob($this->root . "/pages/" . $request_path . "/_*.*")) == 0) {
            } else {
                $file = pathinfo($files[0], PATHINFO_FILENAME);
                $ext = pathinfo($files[0], PATHINFO_EXTENSION);

                $name = substr($file, 1);
                $route->params->$name = $params_value;
                $request_path = $request_path . "/$file";
            }
        }

        $loader = new Loader("pages/" . $request_path, $this, $route);

        if ($this->request->getMethod() == "POST") {

            $ret = $loader->post($this->request->getParsedBody());
            die();
        }

        $page = $loader->render($data);
        //page layout
        $layout_loader = new Loader("layouts/" . $page["layout"], $this, $route);
        $layout = $layout_loader->render($page);

        $app_template = $this->twig->load("app.twig");


        $data = [];
        $data["app"] = $layout["puxt"];

        $data["head"] = $this->generateHeader($layout["head"]);

        $app_html = $app_template->render($data);

        echo $app_html;
    }

    private function generateHeader(array $head)
    {
        $html = [];
        if ($head["title"]) {
            $html[] = "<title>" . htmlentities($head['title']) . "</title>";
        }

        return implode("\n", $html);
    }
}
