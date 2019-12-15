<?php

namespace App\Kernel\Modules\Seller\Controllers;

use App\Http\Controllers\Controller;
use App\Dsctrait\Modules\Seller\IniTrait;

class InitController extends Controller
{
    use IniTrait;

    /**
     * 获取店铺移动端地址
     * @param $seller_shop_id
     * @return \Illuminate\Contracts\Routing\UrlGenerator|string
     */
    public function getShopMobileUrl($seller_shop_id)
    {
        return url('mobile/#/shopHome/' . $seller_shop_id);
    }
}


