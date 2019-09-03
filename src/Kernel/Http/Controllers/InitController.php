<?php

namespace App\Kernel\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Dsctrait\IniTrait;

class InitController extends Controller
{
    use IniTrait;

    /**
     * 仓库ID
     *
     * @return int
     */
    protected function warehouseId()
    {
        return $this->warehouse_id;
    }

    /**
     * 仓库地区
     *
     * @return int
     */
    protected function areaId()
    {
        return $this->area_id;
    }

    /**
     * 仓库地区区县
     *
     * @return int
     */
    protected function areaCity()
    {
        return $this->area_city;
    }

    /**
     * 系统配置
     *
     * @return mixed
     */
    protected function config()
    {
        return $this->config;
    }
}
