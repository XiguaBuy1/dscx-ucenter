<?php

namespace App\Kernel\Repositories\Common;

use App\Kernel\Repositories\Repository;
use Illuminate\Support\Facades\Storage;

class FileSystemsRepository extends Repository
{
    protected $config;
    protected $cloudDriver = 'oss';

    public function __construct()
    {
        $shopConfig = cache('shop_config');
        $shopConfig = !is_null($shopConfig) ? $shopConfig : false;
        if ($shopConfig === false) {
            $this->config = app(\App\Services\Common\ConfigService::class)->getConfig();
        } else {
            $this->config = $shopConfig;
        }

        if (isset($this->config['cloud_storage']) && $this->config['cloud_storage'] == 1) {
            $this->cloudDriver = 'obs';
        } else {
            $this->cloudDriver = 'oss';
        }
    }

    /**
     * 判断路径的文件是否存在
     *
     * @param string $file 文件
     * @return bool
     */
    public function fileExists($file = '')
    {
        if ($this->config['open_oss'] == 1 && config('filesystems.default') == $this->cloudDriver) {
            $return = Storage::exists($file);
        } else {
            $return = is_file($file);
        }


        return $return;
    }

    /**
     * 判断路径的目录是否存在
     *
     * @param string $path 目录
     * @param int $make 0 : 不创建目录 1 : 创建目录
     * @return mixed
     */
    public function dirExists($path = '', $make = 0)
    {
        if ($this->config['open_oss'] == 1 && config('filesystems.default') == $this->cloudDriver) {
            $return = Storage::isDirectory($path);

            if ($return === false && $make == 1) {
                Storage::makeDirectory($path);
            }
        } else {
            $return = is_dir($path);

            if ($return === false && $make == 1) {
                make_dir($path);
            }
        }

        return $return;
    }
}
