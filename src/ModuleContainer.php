<?php

namespace PUXT;

use Closure;

/**
 * @property App $puxt
 */
class ModuleContainer
{
    public $puxt;
    public $config;

    public function __construct(App $puxt)
    {
        $this->puxt = $puxt;
        $this->config = $puxt->config;
    }

    public function ready()
    {
        $this->puxt->callHook("modules:before", $this);

        //modules

        $modules = $this->puxt->config["modules"] ?? [];
        foreach ($modules as $module) {
            $this->addModule($module);
        }

        $this->puxt->callHook("ready", $this->puxt);
    }

    public function addPlugin(string $path)
    {

        if (!file_exists($path)) return;

        $m = require_once($path);


        if ($m instanceof Closure) {

            $context = $this->puxt->context;
            $inject = function (string $key, $value) use ($context) {
                $context->$key = $value;
            };
            $m->call($this, $context, $inject);
        }
    }

    public function addModule($module)
    {
        $options = [];
        if (is_array($module)) {
            $options = $module[1];
            $module = $module[0];
        }

        if (is_dir($dir = $this->puxt->root .  DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . $module)) {

            $entry = $dir . DIRECTORY_SEPARATOR . "index.php";
        }

        if (is_dir($dir = $this->puxt->root . DIRECTORY_SEPARATOR . "modules" . DIRECTORY_SEPARATOR . $module)) {
            $entry = $dir . DIRECTORY_SEPARATOR . "index.php";
        }

        if (!file_exists($entry)) {
            echo "Module: $module/index.php not found";
            die();
        }

        if (!$entry) {
            echo "Module: $module not found";
            die();
        }

        $m = require_once($entry);
        if ($m instanceof Closure) {
            $m->call($this, $options);
        }
    }

    public function addLayout(string $template, string $name)
    {
        $this->puxt->config["layouts"][$name] = $template;
    }
}
