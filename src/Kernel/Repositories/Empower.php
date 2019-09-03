<?php

namespace App\Kernel\Repositories;

use App\Models\ShopConfig;
use App\Kernel\Repositories\Common\UrlRepository;
use Illuminate\Http\Request;

class Empower
{

    /**
     * 检测是否授权
     */
    public function getPoweredBy()
    {
        /* 缓存授权 */
        $isempower_cache_name = 'dscIsEmpower_001';
        $is_empower = cache($isempower_cache_name);
        $is_empower = !is_null($is_empower) ? $is_empower : 0;

        if ($is_empower === 0) {

            $empower = app(UrlRepository::class)->dscKeyCert();
            $empowerWhite = app(UrlRepository::class)->dscKeyCert('empowerWhite');

            //初始授权状态
            $is_empower = 0;
            $DscKey = config('app.dsc_key');

            $encryptFile = storage_path('app/certs/artisanEmpower.key');
            if (!is_file($encryptFile)) {
                $activate_time = (time() - date('Z'));
                $empowerRes = app(UrlRepository::class)->dscUrlEmpower($DscKey, $activate_time);

                if ($empowerRes && $empowerRes['error'] == 0) {
                    $is_empower = 1;
                }
            }

            if ($is_empower == 0) {
                $pathFile = app_path('Repositories/Common/BaseRepository.php');
                $strpos = file_get_contents($pathFile);

                $is_business = 0;
                if (is_file(MOBILE_WECHAT) || is_file(MOBILE_DRP) || is_file(MOBILE_WXAPP) || is_file(MOBILE_TEAM)
                    || is_file(MOBILE_BARGAIN) || is_file(MOBILE_KEFU)) {
                    $is_business = 1;
                }

                /* 检测是否授权 */
                if (strpos($strpos, 'SWOOLEC') === false || $is_business == 1) {
                    $cache_name = 'config_appkey';
                    $appkey = cache($cache_name);
                    $appkey = !is_null($appkey) ? $appkey : false;

                    if ($appkey === false) {
                        $appkey = ShopConfig::where('code', 'appkey')->value('value');
                        $appkey = $appkey ? $appkey : '';

                        cache()->forever($cache_name, $appkey);
                    }

                    if ($empower || $empowerWhite) {

                        $empower['site'] = $empower['site'] ?? '';
                        $empower['activate'] = $empower['activate'] ?? 0;
                        $empower['appkey'] = $empower['appkey'] ?? '';
                        $empower['ip'] = $empower['ip'] ?? '';

                        $empowerWhite['site'] = $empowerWhite['site'] ?? '';
                        $empowerWhite['appkey'] = $empowerWhite['appkey'] ?? '';
                        $empowerWhite['ip'] = $empowerWhite['ip'] ?? '';

                        $url = asset('/');
                        $url = app(UrlRepository::class)->trimUrl($url);

                        $site = app(UrlRepository::class)->trimUrl($empower['site']);

                        if ($site == $url && $empower['activate'] == 1 && ($empower['appkey'] == $appkey)) {
                            $is_empower = 1;
                        }

                        $whiteSite = '';
                        if ($is_empower == 0 && $empowerWhite) {
                            $whiteSite = app(UrlRepository::class)->trimUrl($empowerWhite['site']);

                            if ($whiteSite == $url && $DscKey == $empowerWhite['appkey']) {
                                $is_empower = 1;
                            }
                        }

                        /* 校验IP 解决负载均衡问题以及IP访问 */
                        $ipUrl = asset('/');
                        $ipUrl = $this->trimHttp($ipUrl);
                        $strCount = intval(substr_count($ipUrl, '.')); //必须是IP
                        if ($is_empower == 0 && $strCount == 3) {

                            $dir_cache_name = 'appkey_store_dir';
                            $appkey_store_dir = cache($dir_cache_name);
                            $appkey_store_dir = !is_null($appkey_store_dir) ? $appkey_store_dir : false;
                            if ($appkey_store_dir === false) {
                                $appkey_store_dir = ShopConfig::where('code', 'appkey')->value('store_dir');
                                $appkey_store_dir = $appkey_store_dir ? $appkey_store_dir : '';

                                cache()->forever($dir_cache_name, $appkey_store_dir);
                            }

                            $configSite = app(UrlRepository::class)->trimUrl($appkey_store_dir);

                            $empowerIp = isset($empower['ip']) ? explode(',', $empower['ip']) : '';
                            $empowerWhiteIp = isset($empowerWhite['ip']) ? explode(',', $empowerWhite['ip']) : '';
                            if ((($empowerIp && in_array($ipUrl, $empowerIp) && $configSite === $site) || ($empowerWhiteIp && in_array($ipUrl, $empowerWhiteIp) && $configSite == $whiteSite)) && ($empower['appkey'] == $appkey)) {
                                $is_empower = 1;
                            }

                        }
                    }

                    /* 过滤后台【登录页、授权求情】无需验证授权 start */
                    $current = url()->current();
                    $current = explode('/', $current);
                    $current = $current[count($current) - 1];

                    if ($current == ADMIN_PATH || $current == 'index.php' || $current == 'index.html' || $current == 'privilege.php' || $current == 'dialog.php') {
                        $is_empower = 1;
                    }
                    /* 过滤后台【登录页、授权求情】无需验证授权 end */

                    //任何人都不能修改此文件，除《蛋到捣蛋》
                    if ($is_empower == 0) {

                        $is_source = 0;
                        if (strpos($strpos, 'SWOOLEC') === false) {
                            $is_source = 1; //源码
                        } else {
                            if ($is_business == 1) {
                                $is_source = 2; //商业授权
                            }
                        }

                        $str = '';
                        if ($is_source == 1) {
                            $str = "源码授权";
                        } elseif ($is_source == 2) {
                            $str = "商业授权";
                        }

                        dd("您网站使用的系统是大商创X" . $str . "版本，目前网站尚未授权，请您尽快联系客服索取授权码，大商创x https://www.dscmall.cn/, 大商创咨询热线:4001-021-758");
                    } else {
                        if (($current == ADMIN_PATH || $current == 'index.php' || $current == 'index.html' || $current == 'privilege.php' || $current == 'dialog.php') === false) {
                            cache()->forever($isempower_cache_name, $is_empower);
                        }
                    }
                }
            } else {
                cache()->forever($isempower_cache_name, $is_empower);
            }

            $url = request()->root() . '/';
            $url = app(UrlRepository::class)->trimUrl($url);

            $empower['site'] = $empower['site'] ?? '';
            $site = app(UrlRepository::class)->trimUrl($empower['site']);

            if (($empower && $empowerWhite && $is_empower == 0) || ($empower && empty($empowerWhite) && $url !== $site)) {
                if (empty($empowerWhite)) {
                    if (\Illuminate\Support\Facades\Storage::disk('local')->exists('certs/DscEmpower.key')) {
                        \Illuminate\Support\Facades\Storage::disk('local')->delete('certs/DscEmpower.key');
                    }
                }
            }
        }
    }

    /**
     * 过滤 http|https
     *
     * @param $url
     * @return mixed|string
     */
    private function trimHttp($url)
    {
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

        return $url;
    }
}