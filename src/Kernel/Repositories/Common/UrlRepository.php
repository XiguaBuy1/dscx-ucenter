<?php

namespace App\Kernel\Repositories\Common;

use App\Libraries\Http;
use App\Models\Goods;
use App\Models\OrderInfo;
use App\Models\Region;
use App\Models\SellerShopinfo;
use App\Models\ShopConfig;
use App\Models\Users;
use App\Kernel\Common\KernelRsa;

class UrlRepository
{

    /**
     * 判断是否加密
     */
    public function getIsSwoolec()
    {
        $path = str_replace('Common', '', __DIR__);

        $empowerPathFile = $path . '/Empower.php';
        $empowerStrpos = file_get_contents($empowerPathFile);

        $repositoryPathFile = $path . '/Repository.php';
        $repositoryStrpos = file_get_contents($repositoryPathFile);

        $leftStrpos = (strpos($empowerStrpos, "SWOOLEC") === false && strpos($empowerStrpos, "app(UrlRepository::class)->dscKeyCert();") === false);
        $rightStrpos = (strpos($repositoryStrpos, "SWOOLEC") === false && strpos($repositoryStrpos, "app(Empower::class)->getPoweredBy();") === false);

        if ($leftStrpos || $rightStrpos) {

            $cache_name = 'config_appkey';
            $appkey = cache($cache_name);
            $appkey = !is_null($appkey) ? $appkey : false;

            if ($appkey === false) {
                $appkey = ShopConfig::where('code', 'appkey')->value('value');
                $appkey = $appkey ? $appkey : '';

                cache()->forever($cache_name, $appkey);
            }

            $empower = $this->dscKeyCert();
            $empowerWhite = $this->dscKeyCert('empowerWhite');

            $is_empower = 0; //授权状态
            if ($empower) {

                $url = asset('/');
                $url = $this->trimUrl($url);

                $site = $this->trimUrl($empower['site']);

                if ($site == $url && $empower['activate'] == 1 && ($empower['appkey'] == $appkey)) {
                    $is_empower = 1;
                }

                if ($is_empower == 0 && $empowerWhite) {
                    $whiteSite = $this->trimUrl($empowerWhite['site']);

                    $DscKey = config('app.dsc_key');
                    if ($whiteSite == $url && $DscKey == $empowerWhite['appkey']) {
                        $is_empower = 1;
                    }
                }
            }

            //任何人都不能修改此文件，除《蛋到捣蛋》
            if ($is_empower == 0) {

                $is_source = 0;
                if (strpos($empowerStrpos, 'SWOOLEC') === false || strpos($repositoryStrpos, 'SWOOLEC') === false) {
                    $is_source = 1; //源码
                }

                if ($is_source == 1) {
                    $str = "源码授权";
                } else {
                    $str = '商业授权';
                }

                dd("您网站使用的系统是大商创X" . $str . "版本，目前网站尚未授权，请您尽快联系客服索取授权码，大商创x https://www.dscmall.cn/, 大商创咨询热线:4001-021-758");
            }
        }
    }

    /**
     * 处理Url
     *
     * @param string $url
     * @return mixed|string
     */
    public function trimUrl($url = '')
    {
        $sitePrefix = [
            '.com', '.cn', '.xin', '.net', '.top', '.在线', '.xyz', '.wang', '.shop', '.site', '.club', '.cc', '.fun', '.online', '.biz', '.red', '.link',
            '.ltd', '.mobi', '.info', '.org', '.com.cn', '.net.cn', '.org.cn', '.gov.cn', '.name', '.vip', '.pro', '.work', '.tv', '.co', '.kim', '.group',
            '.tech', '.store', '.ren', '.ink', '.pub', '.live', '.wiki', '.design', '.中文网', '.我爱你', '.中国', '.网址', '.网店', '.公司', '.网络', '.集团',
            '.beer', '.art', '.餐厅', '.luxe', '.商标', '.me', '.im', '.io', '.tw', '.asia', '.hk', '.aero', '.ca', '.us', '.fr', '.se', '.ie', '.in', '.nu',
            '.ch', '.be', '.la', '.ws'
        ];

        if ($url && strpos($url, 'localhost') === false) {
            if (strpos($url, 'https://www.') !== false) {
                $url = str_replace('https://www.', '', $url);
            } elseif (strpos($url, 'http://www.') !== false) {
                $url = str_replace('http://www.', '', $url);
            } elseif (strpos($url, 'https://') !== false) {
                $url = str_replace('https://', '', $url);
            } elseif (strpos($url, 'http://') !== false) {
                $url = str_replace('http://', '', $url);
            }

            $arr = explode('/', $url);
            $url = $arr[0];
            $url = trim($url, '/');

            $site = explode(".", $url);

            $count = count($site);

            $prefix1 = '.' . $site[$count - 2] . '.' . $site[$count - 1];
            $prefix2 = '.' . $site[$count - 1];

            if (in_array($prefix1, $sitePrefix)) {
                $prefix = $prefix1;
            } else if (in_array($prefix2, $sitePrefix)) {
                $prefix = $prefix2;
            } else {
                $prefix = '.' . $site[$count - 1];
            }

            $url = str_replace($prefix, '', $url);
            $site = explode(".", $url);
            $url = $site[count($site) - 1] . $prefix;
        }

        return $url;
    }

    /**
     * 授权证书
     */
    public function dscKeyCert($key = 'DscEmpower')
    {
        $empower = [];

        //初始化类
        $pubfile = storage_path('app/certs/dsc_public_key.pem');
        if (is_file($pubfile)) {
            $m = new KernelRsa(['public_key_file' => $pubfile]);

            $encrypt = storage_path('app/certs/' . $key . '.key');
            if (is_file($encrypt)) {
                $file = file_get_contents($encrypt);
                $decrypt = $m->decrypt($file);
                $empower = $decrypt ? unserialize($decrypt) : [];
            }
        } else {
            dd("缺失" . $pubfile . "文件");
        }

        return $empower;
    }

    /**
     * 获取用户基本信息
     *
     * @throws \Exception
     */
    public function getCertiInfo()
    {
        $data = cache('dscurl_cache');
        $data = !is_null($data) ? $data : false;

        if ($data === false) {

            $certiCode = ShopConfig::where('code', 'certi')->count();

            if (!($certiCode > 0)) {
                dd('您不能修改shop_config表code的值certi，请您及时恢复。');
            }

            $dsc_url = 'https://localhost.api/api/browse';

            $order = OrderInfo::selectRaw("COUNT(*) AS ocount, " . "SUM(" . $this->orderAmountFieldInit() . ") AS oamount")
                ->where('main_count', 0)
                ->first();
            $order = $order ? $order->toArray() : [];

            $gcount = Goods::where('is_delete', 0)
                ->where('is_alone_sale', 1)
                ->where('is_real', 1)
                ->count();

            $usecount = Users::count();

            $scount = SellerShopinfo::count();

            $code = [
                'shop_name', 'shop_title', 'shop_desc', 'shop_keywords', 'qq', 'ww',
                'service_phone', 'msn', 'service_email', 'sms_shop_mobile', 'icp_number', 'lang',
                'shop_country', 'shop_province', 'shop_city', 'shop_address'
            ];
            $row = ShopConfig::whereIn('code', $code)->get();
            $row = $row ? $row->toArray() : [];
            $row = $this->getCfgValInit($row);

            $shop_country = Region::where('region_id', $row['shop_country'])->value('region_name');
            $shop_province = Region::where('region_id', $row['shop_province'])->value('region_name');
            $shop_city = Region::where('region_id', $row['shop_city'])->value('region_name');

            $time = $this->gmTime();

            $data = [
                'domain' => asset('/'), //当前域名
                'url' => asset('/'), //当前url
                'shop_name' => $row['shop_name'],
                'shop_title' => $row['shop_title'],
                'shop_desc' => $row['shop_desc'],
                'shop_keywords' => $row['shop_keywords'],
                'country' => $shop_country,
                'province' => $shop_province,
                'city' => $shop_city,
                'address' => $row['shop_province'],
                'qq' => $row['qq'],
                'ww' => $row['ww'],
                'ym' => $row['service_phone'], //客服电话
                'msn' => $row['msn'],
                'email' => $row['service_email'],
                'phone' => $row['sms_shop_mobile'], //手机号
                'icp' => $row['icp_number'],
                'version' => VERSION,
                'release' => RELEASE,
                'language' => $row['lang'],
                'php_ver' => PHP_VERSION,
                'charset' => EC_CHARSET,
                'ocount' => $order['ocount'] ?? 0,
                'oamount' => $order['oamount'] ?? 0,
                'gcount' => $gcount,
                'usecount' => $usecount,
                'scount' => $scount,
                'add_time' => $this->localDate("Y-m-d H:i:s", $time)
            ];

            $data = json_encode($data); // 对变量进行 JSON 编码
            $argument = array(
                'data' => $data
            );

            Http::doPost($dsc_url, $argument);

            ShopConfig::where('code', 'certi')->update([
                'value' => $dsc_url
            ]);

            /* 清除系统设置 */
            cache()->forget('shop_config');

            $cacheTime = $this->cacheTime(7); //存七天
            $cacheTime = now()->addSeconds($cacheTime);

            $other = [
                'dscurl_cache' => ['url' => 'https://www.dscmall.cn/']
            ];
            cache($other, $cacheTime);
        }
    }

    /**
     * @throws \Exception
     */
    public function getTraceImpower()
    {
        $data = cache('trace_impower');
        $data = !is_null($data) ? $data : false;

        if ($data === false) {
            $DscKey = app(UrlRepository::class)->dscKeyCert();

            $pathFile = app_path('Repositories/Common/BaseRepository.php');
            $strpos = file_get_contents($pathFile);

            if (strpos($strpos, 'SWOOLEC') !== false) {
                $code = [
                    'shop_name', 'shop_title', 'shop_desc', 'shop_keywords', 'qq', 'ww',
                    'service_phone', 'msn', 'service_email', 'sms_shop_mobile', 'icp_number', 'lang',
                    'shop_country', 'shop_province', 'shop_city', 'shop_address', 'appkey'
                ];
                $res = ShopConfig::whereIn('code', $code)->get();
                $res = $res ? $res->toArray() : [];
                $res = $this->getCfgValInit($res);

                $shopCountry = Region::where('region_id', $res['shop_country'])->value('region_name');
                $shopProvince = Region::where('region_id', $res['shop_province'])->value('region_name');
                $shopCity = Region::where('region_id', $res['shop_city'])->value('region_name');

                $order = OrderInfo::selectRaw("COUNT(*) AS ocount, SUM(" . $this->orderAmountFieldInit() . ") AS oamount")
                    ->where('main_count', 0)
                    ->first();
                $order = $order ? $order->toArray() : [];

                $sellerCount = SellerShopinfo::count();
                $userCount = Users::count();
                $goodsCount = Goods::where('is_delete', 0)
                    ->where('is_alone_sale', 1)
                    ->where('is_real', 1)
                    ->count();

                $addTime = $this->gmTime();

                if (isset($DscKey['appkey']) && $DscKey['appkey']) {
                    $key = $DscKey['appkey'];
                } else {
                    $key = $res['appkey'] ?? '';
                }

                $data = [
                    'domain' => asset('/'),
                    'url' => asset('/'),
                    'shop_name' => $res['shop_name'],
                    'appkey' => $key,
                    'icp' => $res['icp_number'],
                    'country' => $shopCountry,
                    'province' => $shopProvince,
                    'city' => $shopCity,
                    'address' => $res['shop_address'],
                    'email' => $res['service_email'],
                    'mobile' => $res['sms_shop_mobile'],
                    'ym' => $res['service_phone'],
                    'scount' => $sellerCount,
                    'usecount' => $userCount,
                    'ocount' => $order['ocount'],
                    'oamount' => $order['oamount'],
                    'gcount' => $goodsCount,
                    'version' => VERSION,
                    'release' => RELEASE,
                    'add_time' => $this->localDate("Y-m-d H:i:s", $addTime)
                ];

                $data = json_encode($data); // 对变量进行 JSON 编码
                $argument = array(
                    'data' => $data
                );

                Http::doPost('https://console.dscmall.cn/api/trace', $argument);
            }

            /* 清除系统设置 */
            cache()->forget('shop_config');

            $cacheTime = $this->cacheTime(14); //存七天
            $cacheTime = now()->addSeconds($cacheTime);

            $other = [
                'trace_impower' => ['url' => 'https://www.dscmall.cn/']
            ];

            cache($other, $cacheTime);
        }
    }

    /**
     * 生成查询订单总金额的字段
     * @param   string $alias order表的别名（包括.例如 o.）
     * @return  string
     */
    private function orderAmountFieldInit($alias = '')
    {
        return "   {$alias}goods_amount + {$alias}tax + {$alias}shipping_fee" .
            " + {$alias}insure_fee + {$alias}pay_fee + {$alias}pack_fee" .
            " + {$alias}card_fee ";
    }

    /**
     * @param array $arr
     * @return array
     */
    private function getCfgValInit($arr = [])
    {
        $new_arr = [];
        if ($arr) {
            foreach ($arr as $row) {
                array_push($new_arr, $row['code'] . "**" . $row['value']);
            }

            $new_arr2 = [];
            foreach ($new_arr as $key => $rows) {
                $rows = explode('**', $rows);
                $new_arr2[$rows[0]] = $rows[1];
            }

            $new_arr = $new_arr2;
        }

        return $new_arr;
    }

    /**
     * 获得当前格林威治时间的时间戳
     *
     * @return  integer
     */
    private function gmTime()
    {
        return (time() - date('Z'));
    }

    /**
     * 将GMT时间戳格式化为用户自定义时区日期
     *
     * @param  string $format
     * @param  integer $time 该参数必须是一个GMT的时间戳
     *
     * @return  string
     */
    private function localDate($format, $time = null)
    {
        $timezone = ShopConfig::where('code', 'timezone')->value('value');
        $timezone = $timezone ? $timezone : 8;

        if ($time === null) {
            $time = $this->gmTime();
        } elseif ($time <= 0) {
            return '';
        }

        $time += ($timezone * 3600);

        return date($format, $time);
    }

    /**
     * 缓存时间
     *
     * @param int $date 天数
     * @return float|int
     */
    private function cacheTime($date = 1)
    {
        $cacheTime = $date * 24 * 60 * 60;
        return $cacheTime;
    }

    public function dscUrlEmpower($AppKey, $activate_time)
    {
        $data = [
            'AppKey' => $AppKey,
            'shop_site' => asset('/'),
            'activate_time' => $activate_time
        ];

        // 对变量进行 JSON 编码
        $data = json_encode($data);
        $argument = array(
            'data' => $data
        );

        $url = "https://console.dscmall.cn/api/empower";

        $res = Http::doPost($url, $argument);

        $res = json_decode($res, true);

        if ($res && $res['error'] == 0 && $res['activate'] == 1) {
            /* 清除系统设置 */
            cache()->forget('shop_config');

            if (isset($res['encrypt']) && $res['encrypt']) {
                \Illuminate\Support\Facades\Storage::disk('local')->put('certs/DscEmpower.key', $res['encrypt']);
                \Illuminate\Support\Facades\Storage::disk('local')->put('certs/artisanEmpower.key', 1);
            }
        }

        return $res;
    }
}
