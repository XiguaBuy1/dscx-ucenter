<?php

use App\Kernel\Repositories\Common\UrlRepository;

$path = str_replace('kernel\src\Kernel\Support', '', __DIR__);
$lockfile = $path . 'storage/app/seeder/install.lock.php';

/* 安装 start */
if (file_exists($lockfile)) {
    app(UrlRepository::class)->getCertiInfo();
    app(UrlRepository::class)->getTraceImpower();
}
/* 安装 end */