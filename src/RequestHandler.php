<?php

namespace PUXT;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface
{
    /**
     * @var RequestHandlerInterface
     */
    private $handler;
    private $extension;

    public function __construct(string $file)
    {

        $this->extension = pathinfo($file, PATHINFO_EXTENSION);
        if ($this->extension == "php") {
            $this->handler = new PHPRequestHandler($file);
        } elseif ($this->extension == "twig") {
            $this->handler = new TwigRequestHandler($file);
        } elseif ($this->extension == "html") {
            $this->handler = new HTMLRequestHandler($file);
        }
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handler->handle($request);
    }

    public function getLayout(): RequestHandlerInterface

    {
        $layout = $this->layout ?? "default";
        if ($this->app->config["layouts"][$layout]) {
            $layout = $this->config["layouts"][$layout];
        } else {
            $layout = "layouts/$layout";
        }
        $layouts = glob($this->app->root . "/" . $layout . ".*");
        if (count($layouts) == 0) { //layout not found
            $layout = "vendor/mathsgod/puxt/layouts/default";
        }

        $loader = new RequestHandler($layout);
        $loader->setContext($this->context);
        return $loader;
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

    public function getHead(array $head)
    {

        if (is_object($this->stub)) {
            $h = $this->stub->head ?? [];
        } else {
            $h = $this->stub["head"] ?? [];
            if ($h instanceof Closure) {
                $h = $h->call($this->component, $this->context);
            }
        }


        if ($h["title"]) {
            $head["title"] = $h["title"];
        }

        foreach ($h["meta"] ?? [] as $meta) {
            if ($meta["hid"]) {

                foreach ($head["meta"] as $k => $m) {
                    if ($m["hid"] == $meta["hid"]) {
                        $head["meta"][$k] = $meta;
                        continue 2;
                    }
                }
            }
            $head["meta"][] = $meta;
        }

        if ($h["htmlAttrs"]) {
            $head["htmlAttrs"] = $h["htmlAttrs"];
        }

        if ($h["bodyAttrs"]) {
            $head["bodyAttrs"] = $h["bodyAttrs"];
        }

        if ($h["headAttrs"]) {
            $head["headAttrs"] = $h["headAttrs"];
        }

        foreach ($h["link"] ?? [] as $link) {
            $head["link"][] = $link;
        }

        foreach ($h["script"] ?? [] as $script) {
            $head["script"][] = $script;
        }

        return $head;
    }
}
