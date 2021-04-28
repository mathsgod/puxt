<?php

namespace PUXT;

use Closure;

/**
 * @property App $puxt
 */
class ModuleContainer
{
    public $puxt;

    public function __construct(App $puxt)
    {
        $this->puxt = $puxt;
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
            echo "Module: $module not found";
        }

        if (!$entry) {
            echo "Module: $module not found";
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
