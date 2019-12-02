<?php

namespace App\Kernel\Plugins;

abstract class PluginAbstract
{
    protected $templateValue;

    protected function display($view = '')
    {
        if ($view) {
            return view($view, $this->templateValue);
        }
    }

    /**
     * 变量分配到视图
     * @param $name
     * @param $value
     */
    protected function assign($name, $value)
    {
        if (is_array($name)) {
            $this->templateValue = array_merge($this->templateValue, $name);
        } else {
            $this->templateValue[$name] = $value;
        }
    }
}
