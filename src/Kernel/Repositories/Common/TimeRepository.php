<?php

namespace App\Kernel\Repositories\Common;

use App\Kernel\Repositories\Repository;

/**
 * Class TimeRepository
 * @package App\Kernel\Repositories\Common
 */
class TimeRepository extends Repository
{
    protected $config;

    public function __construct()
    {
        $shopConfig = cache('shop_config');
        $shopConfig = !is_null($shopConfig) ? $shopConfig : false;
        if ($shopConfig === false) {
            $this->config = app(\App\Services\Common\ConfigService::class)->getConfig();
        } else {
            $this->config = $shopConfig;
        }
    }

    /**
     * 获得当前格林威治时间的时间戳
     *
     * @return  integer
     */
    public function getGmTime()
    {
        return (time() - date('Z'));
    }

    /**
     * 获得服务器的时区
     *
     * @return  integer
     */
    public function getServerTimezone()
    {
        if (function_exists('date_default_timezone_get')) {
            return date_default_timezone_get();
        } else {
            return date('Z') / 3600;
        }
    }

    /**
     * 生成一个用户自定义时区日期的GMT时间戳
     *
     * @param null $hour
     * @param null $minute
     * @param null $second
     * @param null $month
     * @param null $day
     * @param null $year
     * @return false|float|int
     */
    public function getLocalMktime($hour = null, $minute = null, $second = null, $month = null, $day = null, $year = null)
    {
        $timezone = isset($this->config['timezone']) ? $this->config['timezone'] : 8;

        /**
         * $time = mktime($hour, $minute, $second, $month, $day, $year) - date('Z') + (date('Z') - $timezone * 3600)
         * 先用mktime生成时间戳，再减去date('Z')转换为GMT时间，然后修正为用户自定义时间。以下是化简后结果
         **/
        $time = mktime($hour, $minute, $second, $month, $day, $year) - $timezone * 3600;

        return $time;
    }

    /**
     * 将GMT时间戳格式化为用户自定义时区日期
     *
     * @param  string $format
     * @param  integer $time 该参数必须是一个GMT的时间戳
     *
     * @return  string
     */

    public function getLocalDate($format, $time = null)
    {
        $timezone = isset($this->config['timezone']) ? $this->config['timezone'] : 8;

        if ($time === null) {
            $time = $this->getGmTime();
        } elseif ($time <= 0) {
            return '';
        }

        $time += ($timezone * 3600);

        return date($format, $time);
    }

    /**
     * 转换字符串形式的时间表达式为GMT时间戳
     *
     * @param   string $str
     *
     * @return  integer
     */
    public function getGmstrTime($str)
    {
        $time = strtotime($str);

        if ($time > 0) {
            $time -= date('Z');
        }

        return $time;
    }

    /**
     *  将一个用户自定义时区的日期转为GMT时间戳
     *
     * @access  public
     * @param   string $str
     *
     * @return  integer
     */
    public function getLocalStrtoTime($str)
    {
        $timezone = isset($this->config['timezone']) ? $this->config['timezone'] : 8;

        /**
         * $time = mktime($hour, $minute, $second, $month, $day, $year) - date('Z') + (date('Z') - $timezone * 3600)
         * 先用mktime生成时间戳，再减去date('Z')转换为GMT时间，然后修正为用户自定义时间。以下是化简后结果
         **/
        $time = strtotime($str) - $timezone * 3600;

        return $time;
    }

    /**
     * 获得用户所在时区指定的时间戳
     *
     * @param   $timestamp  integer     该时间戳必须是一个服务器本地的时间戳
     *
     * @return  array
     */
    public function getLocalGettime($timestamp = null)
    {
        $tmp = $this->getLocalGetDate($timestamp);
        return $tmp[0];
    }

    /**
     * 获得用户所在时区指定的日期和时间信息
     *
     * @param   $timestamp  integer     该时间戳必须是一个服务器本地的时间戳
     *
     * @return  array
     */
    public function getLocalGetDate($timestamp = null)
    {
        $timezone = isset($this->config['timezone']) ? $this->config['timezone'] : 8;

        /* 如果时间戳为空，则获得服务器的当前时间 */
        if ($timestamp === null) {
            $timestamp = time();
        }

        $gmt = $timestamp - date('Z');       // 得到该时间的格林威治时间
        $local_time = $gmt + ($timezone * 3600);    // 转换为用户所在时区的时间戳

        return getdate($local_time);
    }

    /**
     * cal_days_in_month PHP系统自带的函数
     *
     * 重新定义
     *
     * @param $calendar
     * @param $month
     * @param $year
     * @return int|string
     */
    public function getCalDaysInMonth($calendar, $month, $year)
    {
        if (!function_exists('cal_days_in_month')) {
            return $this->getLocalDate('t', $this->getLocalMktime(0, 0, 0, $month, 1, $year));
        } else {
            return cal_days_in_month($calendar, $month, $year);
        }
    }

    /**
     * 缓存时间
     *
     * @param int $date 天数
     * @return float|int
     */
    public function getCacheTime($date = 1)
    {
        $cacheTime = $date * 24 * 60 * 60;
        return $cacheTime;
    }

    /**
     * 格式化时间函数
     *
     * @param int $time
     * @return false|string
     */
    public function getMdate($time = 0)
    {
        $now_time = $this->getGmTime();
        $time = $time === null || $time > $time ? $time : intval($time);
        $t = $now_time - $time; //时间差 （秒）
        $y = $this->getLocalDate('Y', $now_time) - $this->getLocalDate('Y', $time);//是否跨年
        switch ($t) {
            case $t == 0:
                $text = '刚刚';
                break;
            case $t < 60:
                $text = $t . '秒前'; // 一分钟内
                break;
            case $t < 60 * 60:
                $text = floor($t / 60) . '分钟前'; //一小时内
                break;
            case $t < 60 * 60 * 24:
                $text = floor($t / (60 * 60)) . '小时前'; // 一天内
                break;
            case $t < 60 * 60 * 24 * 3:
                $text = floor($time / (60 * 60 * 24)) == 1 ? '昨天 ' . $this->getLocalDate('H:i', $time) : '前天 ' . $this->getLocalDate('H:i', $time); //昨天和前天
                break;
            case $t < 60 * 60 * 24 * 30:
                $text = $this->getLocalDate('m月d日 H:i', $time); //一个月内
                break;
            case $t < 60 * 60 * 24 * 365 && $y == 0:
                $text = $this->getLocalDate('m月d日', $time); //一年内
                break;
            default:
                $text = $this->getLocalDate('Y年m月d日', $time); //一年以前
                break;
        }

        return $text;
    }

    /**
     * 转换时间戳的具体时间
     *
     * @param int $time
     * @return int|string
     */
    public function getBuyDate($time = 0)
    {
        $t = $time - $this->getGmTime(); //时间差 （秒）

        if ($t <= 0) {
            return 1;
        }

        switch ($t) {
            case $t == 0:
                $text = '刚刚';
                break;
            case $t < 60:
                $text = $t . '秒'; // 一分钟内
                break;
            case $t < 60 * 60:
                $text = floor($t / 60) . '分'; //一小时内
                break;
            case $t < 60 * 60 * 24:
                $text = floor($t / (60 * 60)) . '时'; // 一天内
                break;
            default:
                $text = floor($t / (60 * 60 * 24)) . '天'; //一年以前
                break;
        }

        return $text;
    }
}
