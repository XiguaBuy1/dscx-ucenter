<?php

namespace App\Kernel\Repositories\Common;

use App\Kernel\Common\KernelRsa;
use App\Kernel\Repositories\Cloud\CloudRepository;
use App\Kernel\Repositories\Repository;
use App\Libraries\Http;
use App\Models\ShopConfig;
use App\Models\OrderGoods;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * Class DscRepository
 * @package App\Kernel\Repositories\Common
 */
class DscRepository extends Repository
{
    protected $filesystem;
    protected $config = [];
    protected $cloudRepository;

    public function __construct(
        Filesystem $filesystem,
        CloudRepository $cloudRepository
    )
    {
        $this->filesystem = $filesystem;
        $this->cloudRepository = $cloudRepository;

        /* 获取缓存信息 start */
        $lockfile = base_path('storage/app/seeder/install.lock.php');

        if (file_exists($lockfile)) {
            $shopConfig = cache('shop_config');
            $shopConfig = !is_null($shopConfig) ? $shopConfig : false;
            if ($shopConfig === false) {
                $this->config = app(\App\Services\Common\ConfigService::class)->getConfig();
            } else {
                $this->config = $shopConfig;
            }
        }
        /* 获取缓存信息 end */

        /* 限制文件被篡改 start */
        $pathFile = __DIR__ . '/UrlRepository.php';
        $strpos = file_get_contents($pathFile);

        if (strpos($strpos, "SWOOLEC") === false && strpos($strpos, "private function localDate(") === false) {
            dd("您非法篡改程序代码，已造成站点程序无法访问，请恢复被篡改相关程序文件！");
        }
        /* 限制文件被篡改 end */

        app(UrlRepository::class)->getIsSwoolec();
    }

    /**
     * 处理系统设置[QQ客服/旺旺客服]
     *
     * @param $basic_info
     */
    public function chatQq($basic_info)
    {
        if ($basic_info) {
            // 旺旺
            if ($basic_info['kf_ww']) {
                $kf_ww = array_filter(preg_split('/\s+/', $basic_info['kf_ww']));
                $kf_ww = $kf_ww && $kf_ww[0] ? explode("|", $kf_ww[0]) : [];
                if (isset($kf_ww[1]) && !empty($kf_ww[1])) {
                    $basic_info['kf_ww'] = $kf_ww[1];
                } else {
                    $basic_info['kf_ww'] = '';
                }
            } else {
                $basic_info['kf_ww'] = '';
            }

            // QQ
            if ($basic_info['kf_qq']) {
                $kf_qq = array_filter(preg_split('/\s+/', $basic_info['kf_qq']));
                $kf_qq = $kf_qq && $kf_qq[0] ? explode("|", $kf_qq[0]) : [];
                if (isset($kf_qq[1]) && !empty($kf_qq[1])) {
                    $basic_info['kf_qq'] = $kf_qq[1];
                } else {
                    $basic_info['kf_qq'] = '';
                }
            } else {
                $basic_info['kf_qq'] = '';
            }
        }

        return ['kf_qq' => $basic_info['kf_qq'], 'kf_ww' => $basic_info['kf_ww']];
    }

    /**
     * 获取伪静态地址
     * @param $items
     * @return array
     */
    public function getUrlHtml($items = [])
    {
        $return = [];

        foreach ($items as $key => $item)
        {
            $return[$item] = $url = url($item . '.html');
        }

        return $return;
    }

    /**
     * 格式化商品价格
     *
     * @param int $price 商品价格
     * @param bool $change_price 是否使用后台配置
     * @return string
     */
    public function getPriceFormat($price = 0, $change_price = true)
    {
        if (empty($price)) {
            $price = 0;
        }

        if ($change_price && defined('ECS_ADMIN') === false) {
            switch ($this->config['price_format']) {
                case 0:
                    $price = number_format($price, 2, '.', '');
                    break;
                case 1: // 保留不为 0 的尾数
                    $price = preg_replace('/(.*)(\\.)([0-9]*?)0+$/', '\1\2\3', number_format($price, 2, '.', ''));

                    if (substr($price, -1) == '.') {
                        $price = substr($price, 0, -1);
                    }
                    break;
                case 2: // 不四舍五入，保留1位
                    $price = substr(number_format($price, 2, '.', ''), 0, -1);
                    break;
                case 3: // 直接取整
                    $price = intval($price);
                    break;
                case 4: // 四舍五入，保留 1 位
                    $price = number_format($price, 1, '.', '');
                    break;
                case 5: // 先四舍五入，不保留小数
                    $price = round($price);
                    break;
            }
        } else {
            $price = number_format($price, 2, '.', '');
        }

        return sprintf($this->config['currency_format'], $price);
    }

    /**
     * 重新获得商品图片与商品相册的地址
     *
     * @param string $image 原商品相册图片地址
     * @return string   $url
     */
    public function getImagePath($image = '')
    {
        if (!empty($image) && (strpos($image, 'http://') === false && strpos($image, 'https://') === false && strpos($image, 'errorImg.png') === false)) {
            if ($this->config['open_oss'] == 1) {
                $bucket_info = $this->cloudRepository->getDscBucketInfo();
                $image = isset($bucket_info['endpoint']) ? $bucket_info['endpoint'] . $image : $image;
            } else {
                $image = $this->config['site_domain'] . $image;
            }
        }

        // http or https
        if (strtolower(substr($image, 0, 4)) == 'http') {
            return $image;
        }

        $no_picture = isset($this->config['no_picture']) && !empty($this->config['no_picture']) ? str_replace("../", "", $this->config['no_picture']) : '';
        $url = empty($image) ? $no_picture : $image;

        return Storage::url($url);
    }

    /**
     * 正则批量替换详情内图片为 绝对路径
     *
     * @param $content
     * @return null|string|string[]
     */
    public function getContentImgReplace($content)
    {
        if ($this->config['open_oss'] == 1) {
            $bucket_info = $this->cloudRepository->getDscBucketInfo();
            $url = $bucket_info['endpoint'] ?? '';
        } else {
            $url = Storage::url('/');
        }

        $label = [
            // 图片路径 "img:"/images/5951cff07c39a.jpg"  => "img:"http://www.a.com/images/5951cff07c39a.jpg"
            '/<img.*?src=[\"|\']?\/(.*?)[\"|\'].*?>/i' => '<img src="' . $url . '$1" >',
        ];

        foreach ($label as $key => $value) {
            $content = preg_replace($key, $value, $content);
        }

        return $content;
    }

    /**
     * 处理指定目录文件数据调取
     *
     * @param string $path
     * @return array
     * @throws \Exception
     */
    public function getDdownloadTemplate($path = '')
    {
        $download_list = [];
        $dir = $this->filesystem->directories($path);
        if ($dir) {
            foreach ($dir as $key => $val) {
                $file = $this->filesystem->basename($val);

                if ($file != '.' && $file != '..' && $file != ".svn" && $file != "_svn" && is_dir($val) == true) {
                    $download_file = lang('common.charset.' . $file);

                    if ($file == $this->config['lang']) {
                        $download_list[$file] = sprintf(lang('common.download_file'), isset($download_file) ? $download_file : $file);
                    }
                }
            }
        }

        return $download_list;
    }

    /**
     * 对象转数组
     *
     * @param $obj
     * @return array
     */
    public function objectToArray($obj)
    {
        $_arr = is_object($obj) ? get_object_vars($obj) : $obj;
        if ($_arr) {
            foreach ($_arr as $key => $val) {
                $val = (is_array($val)) || is_object($val) ? $this->objectToArray($val) : $val;
                $arr[$key] = $val;
            }
        } else {
            $arr = [];
        }

        return $arr;
    }

    /**
     * 跳转H5方法
     *
     * @param string $url
     * @return bool|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|string
     */
    public function getReturnMobile($url = '')
    {
        $auto = $this->config['auto_mobile'] ?? 1;
        $ua = strtolower(request()->server('HTTP_USER_AGENT'));

        $uachar = "/(nokia|sony|ericsson|mot|samsung|sgh|lg|philips|panasonic|alcatel|lenovo|cldc|midp|mobile)/i";

        if (($ua == '' || preg_match($uachar, $ua)) && !strpos(strtolower(request()->server('REQUEST_URI')), 'wap')) {
            if (!empty($url)) {
                if ($auto == 1) {
                    return dsc_header("Location: $url\n");
                } else {
                    $current = url()->current();
                    if (strpos($current, 'mobile') !== false) {
                        return dsc_header("Location: $url\n");
                    }
                }
            }
        }

        return false;
    }

    /**
     * 数组分页函数 核心函数 array_slice
     *
     * 用此函数之前要先将数据库里面的所有数据按一定的顺序查询出来存入数组中
     *
     * @param int $page_size 每页多少条数据
     * @param int $page 当前第几页
     * @param array $array 查询出来的所有数组
     * @param int $order 0 - 不变   1- 反序
     * @param array $filter_arr 合并数组
     * @return array
     */
    public function pageArray($page_size = 1, $page = 1, $array = [], $order = 0, $filter_arr = [])
    {
        $arr = [];
        if ($array) {
            global $countpage; #定全局变量

            $start = ($page - 1) * $page_size; #计算每次分页的开始位置

            if ($order == 1) {
                $array = array_reverse($array);
            }

            $totals = count($array);
            $countpage = ceil($totals / $page_size); #计算总页面数
            $pagedata = array_slice($array, $start, $page_size);

            $filter = [
                'page' => $page,
                'page_size' => $page_size,
                'record_count' => $totals,
                'page_count' => $countpage
            ];

            if ($filter_arr) {
                $filter = array_merge($filter, $filter_arr);
            }

            $arr = ['list' => $pagedata, 'filter' => $filter, 'page_count' => $countpage, 'record_count' => $totals];
        }

        return $arr; #返回查询数据
    }

    /**
     * 升级补丁SQL
     */
    public function getPatch()
    {
        $list = glob(app_path('Patch/*.php'));

        if ($list) {
            foreach ($list as $key => $row) {
                $name = $this->filesystem->name($row);
                $version = 'App\\Patch\\' . $name;

                if (class_exists($version)) {
                    app($version)->run();
                }
            }
        }
    }

    /**
     * 获得所有模块的名称以及链接地址
     *
     * @access      public
     * @param string $directory 插件存放的目录
     * @return      array
     */
    public function readModules($directory = '.')
    {
        $modules = [];
        foreach (glob($directory . '/*/config.php') as $key => $val) {
            $modules[] = include_once($val);
        }

        return $modules;
    }

    /**
     * 对象转数组
     *
     * @param null $array
     * @return array|null
     */
    public function objectArray($array = null)
    {
        if (is_object($array)) {
            $array = (array)$array;
        }

        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $array[$key] = object_array($value);
            }
        }
        return $array;
    }

    /**
     * 切割目录文件
     *
     * @param array $list
     * @return array
     */
    public function getInciseDirectory($list = [])
    {
        $arr = [];
        if ($list) {
            $list = is_array($list) ? $list : [$list];

            foreach ($list as $key => $val) {
                if ($val) {
                    $val = trim($val);

                    $file = explode('/', $val);
                    $count = count($file);

                    $file_name = $file[$count - 1];
                    $directory = str_replace($file_name, '', $val);

                    $arr[$key]['file'] = $val;
                    $arr[$key]['directory'] = $directory;
                    $arr[$key]['file_name'] = $file_name;
                }
            }
        }

        return $arr;
    }

    /**
     * 对 MYSQL LIKE 的内容进行转义
     *
     * @param $str
     * @return string
     */
    public function mysqlLikeQuote($str)
    {
        return strtr($str, ["\\\\" => "\\\\\\\\", '_' => '\_', '%' => '\%', "\'" => "\\\\\'"]);
    }

    /**
     * 将字符串以 * 号格式显示 配合msubstr_ect函数使用
     *
     * @param string $string 至少9个字符长度
     * @param int $num
     * @param string $start_len
     * @return string 例如 string_to_star($str,1)  w******f , string_to_star($str,2) we****af
     */
    public function stringToStar($string = '', $num = 3, $start_len = '')
    {
        if (strlen($string) >= 3 && strlen($string) > $num) {
            $lenth = $start_len > 0 ? $start_len : strlen($string) - $num * 2;
            $star_length = '';
            for ($x = 1; $x <= $lenth; $x++) {
                $star_length .= "*";
            }
            $result = $this->msubstrEct($string, 0, $num, 'utf-8', $star_length);
        } else {
            $result = $string;
        }

        return $result;
    }

    /**
     * 字符串截取，支持中文和其他编码
     *
     * @param string $str 需要转换的字符串
     * @param int $start 开始位置
     * @param int $length 截取长度
     * @param string $charset 编码格式
     * @param string $suffix 截断显示字符 默认 ***
     * @param int $position 截断显示字符位置 默认 1 为中间 例：刘***然，0 为后缀 刘***
     * @return string
     */
    public function msubstrEct($str = '', $start = 0, $length = 1, $charset = "utf-8", $suffix = '***', $position = 1)
    {
        if (function_exists("mb_substr")) {
            $slice = mb_substr($str, $start, $length, $charset);
            $slice_end = mb_substr($str, -$length, $length, $charset);
        } elseif (function_exists('iconv_substr')) {
            $slice = iconv_substr($str, $start, $length, $charset);
            $slice_end = iconv_substr($str, -$length, $length, $charset);
        } else {
            $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
            $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
            $re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
            $re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
            preg_match_all($re[$charset], $str, $match);
            $slice = join("", array_slice($match[0], $start, $length));
            $slice_end = join("", array_slice($match[0], -$length, $length));
        }

        return $position == 0 ? $slice . $suffix : $slice . $suffix . $slice_end;
    }

    /**
     * 获取用户IP ：可能会出现误差
     *
     * @return null|string
     */
    public function dscIp()
    {
        return request()->getClientIp();
    }

    /**
     * 正则过滤内容样式 style='' width='' height=''
     *
     * @param $content
     * @return null|string|string[]
     */
    public function contentStyleReplace($content)
    {
        $label = [
            //'/style=.+?[*|\"]/i' => '', // 所有style内容
            '/width\=\"[0-9]+?\"/i' => '', // 去除width="100"
            '/height\=\"[0-9]+?\"/i' => '',
            '/width\:(.*?)\;/i' => '', // 去除width:733px
            '/height\:(.*?)\;/i' => '',
        ];
        foreach ($label as $key => $value) {
            $content = preg_replace($key, $value, $content);
        }
        return $content;
    }

    /**
     * 组合语言包信息
     *
     * @param array $files
     * @param string $module
     * @param int $langPath
     */
    public function helpersLang($files = [], $module = '', $langPath = 0)
    {

        $path = 0;
        if ($module && is_numeric($module)) {
            $path = 1;
        }

        $GLOBALS['_LANG'] = $GLOBALS['_LANG'] ?? [];

        $arr = [];

        if ($files) {

            $base_path = '';

            if (!is_array($files)) {
                $files = explode(',', $files);
            }

            if ($path == 0) {
                if (empty($this->config['lang']) || $this->config['lang'] == 'zh_cn') {
                    $this->config['lang'] = 'zh-CN';
                }

                $lang_path = $this->config['lang'] ?? 'zh-CN';

                if ($langPath == 1) {
                    $base_path = plugin_path($module);
                } else {
                    if (empty($module)) {
                        $base_path = resource_path('lang/' . $lang_path . '/');
                    } else {
                        $base_path = app_path('Modules/' . ucfirst($module) . '/Languages/' . $lang_path . '/');
                    }
                }
            }

            foreach ($files as $key => $vo) {
                if ($langPath == 1) {
                    $helper = $base_path . '.php';
                } else {
                    if ($path == 1) {
                        $helper = $vo;
                    } else {
                        $helper = $base_path . $vo . '.php';
                    }
                }

                if (file_exists($helper)) {
                    $list = require($helper);

                    if ($list) {
                        $arr[$key] = $list;
                    }
                }
            }

            if ($arr) {
                $arr = Arr::collapse($arr);
            }
        }

        if ($arr && $GLOBALS['_LANG']) {
            $GLOBALS['_LANG'] = Arr::collapse([$GLOBALS['_LANG'], $arr]);
        } elseif (!$GLOBALS['_LANG'] && $arr) {
            $GLOBALS['_LANG'] = $arr;
        }
    }

    /**
     * 读结果缓存文件
     *
     * @param string $cache_path 读取文件目录路径
     * @param string $cache_name 读取文件目录文件名称
     * @param string $storage_path 存储文件目录路径
     * @param string $prefix $prefix 存储文件后缀
     * @return bool|mixed
     */
    public function readStaticCache($cache_path = '', $cache_name = '', $storage_path = 'common_cache/', $prefix = "php")
    {

        if (!Storage::disk('local')->exists($storage_path . $cache_path)) {
            Storage::disk('local')->makeDirectory($storage_path . $cache_path);
        }

        static $result = array();
        if (!empty($result[$cache_name]) && Storage::disk('local')->exists($storage_path . $cache_path . '/' . $cache_name . "." . $prefix)) {
            return $result[$cache_name];
        }

        if (Storage::disk('local')->exists($storage_path . $cache_path . '/' . $cache_name . "." . $prefix)) {

            $cache_file_path = storage_path('app/' . $storage_path . $cache_path . '/' . $cache_name . "." . $prefix);

            if (file_exists($cache_file_path)) {
                include_once($cache_file_path);
            } else {
                $arr = array();
            }

            $result[$cache_name] = $arr;
            return $result[$cache_name];
        } else {
            return false;
        }
    }

    /**
     * 写结果缓存文件
     *
     * @param string $cache_path 写入文件目录路径
     * @param string $cache_name 写入文件目录文件名称
     * @param string $caches 缓存数据
     * @param string $storage_path 存储文件目录路径
     * @param string $prefix 存储文件后缀
     */
    public function writeStaticCache($cache_path = '', $cache_name = '', $caches = '', $storage_path = 'common_cache/', $prefix = "php")
    {

        if (!Storage::disk('local')->exists($storage_path . $cache_path)) {
            Storage::disk('local')->makeDirectory($storage_path . $cache_path);
        }

        $cache_file_path = storage_path('app/' . $storage_path . $cache_path . '/' . $cache_name . "." . $prefix);

        $content = "<?php\r\n\r\n";
        $content .= "\$arr = " . var_export($caches, true) . ";\r\n";
        $content .= "\r\n\r\n";
        $content .= "return \$arr;\r\n";

        $cache_file_path = str_replace("//", '/', $cache_file_path);

        file_put_contents($cache_file_path, $content, LOCK_EX);
    }

    /**
     * 下载远程图片
     *
     * @param string $url 下载外链文件地址
     * @param string $path 存放的路径
     * @param string $goods_lib 是否商品库
     * @return bool|string
     */
    public function getHttpBasename($url = '', $path = '', $goods_lib = '')
    {
        if ($url && strpos($url, 'http') !== false) {
            $response = get_headers($url);

            if (preg_match('/200/', $response[0])) {
                $return_content = Http::doGet($url);
                $url = basename($url);
                if ($goods_lib) {
                    $filename = $path;
                } else {
                    $filename = $path . "/" . $url;
                }

                if (file_put_contents($filename, $return_content)) {
                    return $filename;
                }
            }
        }

        return false;
    }

    /**
     * 判断远程链接|判断本地链接 -- 是否存在
     *
     * @param $url
     * @return bool
     */
    public function remoteLinkExists($url)
    {
        if ($url) {
            if (strpos($url, 'http') !== false) {
                $response = get_headers($url);

                if (preg_match('/200/', $response[0])) {
                    return true;
                }
            } else {
                if (!empty($url) && file_exists($url)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 调取插件语言包
     *
     * 插件名称(Alipay/), __DIR__
     *
     * @param $plugin
     * @param $dir
     */
    public function pluginsLang($plugin, $dir)
    {
        $list = str_replace('\\', '/', $dir);
        $list = explode('/', $list);
        $code = $list[count($list) - 1];

        $lang = $plugin . '/' . $code . '/Languages/' . $this->config['lang'];

        $this->helpersLang($code, $lang, 1);
    }

    /**
     * 截取UTF-8编码下字符串的函数
     *
     * @param $str 被截取的字符串
     * @param int $length 截取的长度
     * @param bool $append 是否附加省略号
     * @return bool|string
     */
    public function subStr($str, $length = 0, $append = true)
    {
        $str = trim($str);
        $strlength = strlen($str);

        if ($length == 0 || $length >= $strlength) {
            return $str;
        } elseif ($length < 0) {
            $length = $strlength + $length;
            if ($length < 0) {
                $length = $strlength;
            }
        }

        if (function_exists('mb_substr')) {
            $newstr = mb_substr($str, 0, $length, EC_CHARSET);
        } elseif (function_exists('iconv_substr')) {
            $newstr = iconv_substr($str, 0, $length, EC_CHARSET);
        } else {
            $newstr = substr($str, 0, $length);
        }

        if ($append && $str != $newstr) {
            $newstr .= '...';
        }

        return $newstr;
    }

    /**
     * 去除字符串右侧可能出现的乱码
     *
     * @param $str 字符串
     * @return bool|string
     */
    public function trimRight($str)
    {
        $len = strlen($str);
        /* 为空或单个字符直接返回 */
        if ($len == 0 || ord($str{$len - 1}) < 127) {
            return $str;
        }
        /* 有前导字符的直接把前导字符去掉 */
        if (ord($str{$len - 1}) >= 192) {
            return substr($str, 0, $len - 1);
        }
        /* 有非独立的字符，先把非独立字符去掉，再验证非独立的字符是不是一个完整的字，不是连原来前导字符也截取掉 */
        $r_len = strlen(rtrim($str, "\x80..\xBF"));
        if ($r_len == 0 || ord($str{$r_len - 1}) < 127) {
            return $this->subStr($str, 0, $r_len);
        }

        $as_num = ord(~$str{$r_len - 1});
        if ($as_num > (1 << (6 + $r_len - $len))) {
            return $str;
        } else {
            return substr($str, 0, $r_len - 1);
        }
    }

    /**
     * 计算字符串的长度（汉字按照两个字符计算）
     *
     * @param string $str 字符串
     * @return float|int|string
     */
    public function strLen($str = '')
    {
        if ($str) {
            $length = strlen(preg_replace('/[\x00-\x7F]/', '', $str));

            if ($length) {
                return strlen($str) - $length + intval($length / 3) * 2;
            } else {
                return strlen($str);
            }
        } else {
            return $str;
        }
    }

    /**
     * 去除字符串中首尾逗号
     * 去除字符串中出现两个连续逗号
     *
     * @param string $str
     * @param string $delstr
     * @return bool|mixed|string
     */
    public function delStrComma($str = '', $delstr = ',')
    {
        if ($str && is_array($str)) {
            return $str;
        } else {
            if ($str) {
                $str = str_replace("{$delstr}{$delstr}", "{$delstr}", $str);

                $str1 = substr($str, 0, 1);
                $str2 = substr($str, $this->strLen($str) - 1);

                if ($str1 === "{$delstr}" && $str2 !== "{$delstr}") {
                    $str = substr($str, 1);
                } elseif ($str1 !== "{$delstr}" && $str2 === "{$delstr}") {
                    $str = substr($str, 0, -1);
                } elseif ($str1 === "{$delstr}" && $str2 === "{$delstr}") {
                    $str = substr($str, 1);
                    $str = substr($str, 0, -1);
                }
            }

            return $str;
        }
    }

    /**
     * 【云存储】获取存储信息
     *
     * @return array|bool|\Illuminate\Cache\CacheManager|mixed
     * @throws \Exception
     */
    public function getBucketInfo()
    {
        return $this->cloudRepository->getDscBucketInfo();
    }

    /**
     * 【云存储】上传文件
     *
     * @param array $file
     */
    public function getOssAddFile($file = [])
    {
        $this->cloudRepository->getDscOssAddFile($file);
    }

    /**
     * 【云存储】删除文件
     *
     * @param array $file
     * @throws \OSS\Core\OssException
     */
    public function getOssDelFile($file = [])
    {
        $this->cloudRepository->getDscOssDelFile($file);
    }

    /**
     * 【云存储】单个或批量删除图片
     *
     * @param string $checkboxs
     * @param string $val_id
     * @param string $select
     * @param string $id
     * @param $query
     * @param int $del
     * @param string $fileDir
     * @throws \Exception
     */
    public function getDelBatch($checkboxs = '', $val_id = '', $select = '', $id = '', $query, $del = 0, $fileDir = '')
    {
        $this->cloudRepository->getDscDelBatch($checkboxs, $val_id, $select, $id, $query, $del, $fileDir);
    }

    /**
     * 【云存储】删除可视化模板OSS标识文件
     *
     * @param array $ip
     * @param string $suffix
     * @param string $act
     * @param int $seller_id
     */
    public function getDelVisualTemplates($ip = [], $suffix = '', $act = 'del_hometemplates', $seller_id = 0)
    {
        $this->cloudRepository->getDscDelVisualTemplates($ip, $suffix, $act, $seller_id);
    }

    /**
     * 【云存储】下载文件
     *
     * @param array $file
     * @return array
     * @throws \OSS\Core\OssException
     */
    public function getOssListFile($file = [])
    {
        $list = $this->cloudRepository->getDscOssListFile($file);

        return $list;
    }

    /**
     * 生成授权证书
     *
     * @param $AppKey
     * @param $activate_time
     * @return mixed
     */
    public function dscEmpower($AppKey, $activate_time)
    {
        $data = [
            'AppKey' => $AppKey,
            'shop_site' => $this->dscUrl(),
            'activate_time' => $activate_time
        ];

        // 对变量进行 JSON 编码
        $data = json_encode($data);
        $argument = array(
            'data' => $data
        );

        $url = "https://localhost.api/api/empower";

        $res = Http::doPost($url, $argument);

        $res = json_decode($res, true);

        if ($res && $res['error'] == 0 && $res['activate'] == 1) {
            ShopConfig::where('code', 'appkey')->update([
                'value' => $res['AppKey'],
                'store_dir' => $this->dscUrl()
            ]);

            /* 清除系统设置 */
            cache()->forget('shop_config');

            if (isset($res['encrypt']) && $res['encrypt']) {
                Storage::disk('local')->put('certs/DscEmpower.key', $res['encrypt']);
            }
        }

        return $res;
    }

    /**
     * 校验授权
     *
     * @return int
     */
    public function checkEmpower()
    {
        $empower = $this->getDscKeyCert();

        $url = $this->dscUrl();
        $url = $this->dscTrimUrl($url);

        $is_empower = 0;
        if ($empower) {
            $site = $this->dscTrimUrl($empower['site']);

            if ($site == $url && $empower['activate'] == 1 && (isset($GLOBALS['_CFG']['appkey']) && $empower['appkey'] == $GLOBALS['_CFG']['appkey'])) {
                $is_empower = 1;
            }
        }

        return $is_empower;
    }

    /**
     * 解析授权证书
     *
     * @param string $key
     * @return array|mixed
     */
    public function getDscKeyCert($key = 'DscEmpower')
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
        }

        return $empower;
    }

    /**
     * 核对均摊红包商品金额是否大于订单红包金额
     *
     * @param array $bonus_list
     * @param int $orderBonus
     * @param int $goods_bonus
     */
    public function collateOrderGoodsBonus($bonus_list = [], $orderBonus = 0, $goods_bonus = 0)
    {
        if ($orderBonus > 0) {
            $bonusCount = count($bonus_list);
            if ($goods_bonus > $orderBonus) {
                /* 商品均摊红包总额大于订单红包金额 */
                foreach ($bonus_list as $idx => $val) {
                    if ($idx == ($bonusCount - 1)) {
                        $bonusTotal = $val['goods_bonus'] - ($goods_bonus - $orderBonus);
                        OrderGoods::where('rec_id', $val['rec_id'])
                            ->update([
                                'goods_bonus' => $bonusTotal
                            ]);
                    }
                }
            } elseif ($goods_bonus < $orderBonus) {
                /* 商品均摊红包总额小于订单红包金额 */
                foreach ($bonus_list as $idx => $val) {
                    if ($idx == ($bonusCount - 1)) {
                        $bonusTotal = $val['goods_bonus'] + ($orderBonus - $goods_bonus);
                        OrderGoods::where('rec_id', $val['rec_id'])
                            ->update([
                                'goods_bonus' => $bonusTotal
                            ]);
                    }
                }
            }
        }
    }

    /**
     * 处理Url
     *
     * @param string $url
     * @return mixed|string
     */
    private function dscTrimUrl($url = '')
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
     * 商城配置信息
     *
     * @param string $str
     * @return array
     */
    public function dscConfig($str = '')
    {
        if (!empty($str)) {
            $config = [];
            $list = !is_array($str) ? explode(',', $str) : $str;
            foreach ($list as $key => $val) {
                $config[$val] = $this->config[$val];
            }

            return $config;
        } else {
            return $this->config;
        }
    }

    /**
     * 获取站点访问地址[域名]
     *
     * @param string $str
     * @return string
     */
    public function dscUrl($str = '')
    {
        $url = request()->root() . '/' . $str;

        if (strpos($url, 'index.php/') !== false) {
            $url = str_replace('index.php/', '', $url);
        } elseif (strpos($url, 'index.php') !== false) {
            $url = str_replace('index.php', '', $url);
        } elseif (strpos($url, 'index.html/') !== false) {
            $url = str_replace('index.html/', '', $url);
        } elseif (strpos($url, 'index.html') !== false) {
            $url = str_replace('index.html', '', $url);
        }

        return $url;
    }
}
