<?php

namespace App\Kernel\Repositories;

use Illuminate\Support\Facades\Storage;
use App\Kernel\Repositories\Common\UrlRepository;

class Repository
{
    public function __construct()
    {

        app(Empower::class)->getPoweredBy();

        $path = str_replace('Repositories', '', __DIR__);

        $pathFile = $path . 'Support/helpers.php';

        if (is_file($pathFile)) {
            $strpos = file_get_contents($pathFile);

            if (strpos($strpos, "app(UrlRepository::class)->getCertiInfo();") === false) {
                /* 安装 start */
                $lockfile = Storage::disk('local')->exists('seeder/install.lock.php');
                if ($lockfile) {
                    app(UrlRepository::class)->getCertiInfo();
                    app(UrlRepository::class)->getTraceImpower();
                }
                /* 安装 end */
            }
        } else {
            dd("缺失" . $pathFile . "文件");
        }
    }
}