<?php

namespace App\Kernel\Repositories\Common;

use App\Kernel\Repositories\Repository;
use Yurun\Util\Chinese;

class PinyinRepository extends Repository
{

    /**
     * 文字转拼音
     *
     * @param $string
     * @param string $split
     * @return array|string
     */
    public function convertMode($string, $split = ' ')
    {
        $list = Chinese::toPinyin($string, Chinese\Pinyin::CONVERT_MODE_PINYIN);

        $arr = [];
        if (isset($list['pinyin']) && $list['pinyin']) {
            $arr = implode($split, $list['pinyin'][0]);
        }

        return $arr;
    }

    /**
     * 首字母转大写
     *
     * 主要针对带空格字符串处理
     *
     * @param string $str
     * @return string
     */
    public function ucwordsStrtolower($str = '')
    {
        return ucwords(strtolower($str));
    }

    /**
     * 获取拼音首字母
     *
     * @param $string
     * @param string $split
     * @return array|string
     */
    public function convertModeFirst($string, $split = '')
    {
        $list = Chinese::toPinyin($string, Chinese\Pinyin::CONVERT_MODE_PINYIN_FIRST);

        $arr = [];
        if (isset($list['pinyinFirst']) && $list['pinyinFirst']) {
            $arr = implode($split, $list['pinyinFirst'][0]);
        }

        return $arr;
    }
}