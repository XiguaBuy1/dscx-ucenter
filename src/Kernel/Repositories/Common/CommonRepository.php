<?php

namespace App\Kernel\Repositories\Common;

use App\Kernel\Repositories\Repository;
use Illuminate\Support\Str;

class CommonRepository extends Repository
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
     * 查询是否已存在值
     *
     * $where = [
     * 'attr_name' => $val,
     * 'id' => [
     *      'filed' => [
     *          'attr_id' => $id
     *      ],
     *      'condition' => '<>'
     * ],
     * 'cat_id' => $cat_id
     * ];
     *
     * @param $object
     * @param array $where
     * @return bool
     */
    public function getManageIsOnly($object, $where = [])
    {
        $count = 0;
        if ($where) {
            foreach ($where as $key => $row) {
                if (is_array($row) && isset($row['filed'])) {
                    foreach ($row['filed'] as $idx => $val) {
                        $object = $object->where($idx, $row['condition'], $val);
                    }
                } else {
                    $object = $object->where($key, $row);
                }
            }

            $count = $object->count();
        }

        if ($count > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 判断值是否为邮箱
     * @param $value
     */
    public function getMatchEmail($value)
    {
        $regex = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/';

        return preg_match_all($regex, $value, $flag) === 1;
    }

    /**
     * 判断值是否为手机号
     * @param $value
     */
    public function getMatchPhone($value)
    {
        $regex = '/^((13[0-9])|(14[5,7])|(15[0-3,5-9])|(17[0,3,5-8])|(18[0-9])|166|198|199|(147))\d{8}$/';

        return preg_match_all($regex, $value, $flag) === 1;
    }

    /**
     * 发送短信
     *
     * @param int $mobile 接收手机号码
     * @param string $content 发送短信的内容数据
     * @param string $send_time 发送内容模板时机标记
     * @param bool $msg 是否显示错误信息
     * @return bool
     * @throws \Exception
     */
    public function smsSend($mobile = 0, $content = '', $send_time = '', $msg = true)
    {

        $sms_type = ['ihuyi', 'alidayu', 'aliyun', 'dscsms', 'huawei'];

        $config = [
            'driver' => 'sms',
            'driverConfig' => [
                'sms_type' => $sms_type[$this->config['sms_type']], // 短信类型对应 $sms_type 数组索引
                'ihuyi' => [
                    'sms_name' => $this->config['sms_ecmoban_user'],
                    'sms_password' => $this->config['sms_ecmoban_password']
                ],
                'alidayu' => [
                    'ali_appkey' => $this->config['ali_appkey'],
                    'ali_secretkey' => $this->config['ali_secretkey']
                ],
                'aliyun' => [
                    'access_key_id' => $this->config['access_key_id'],
                    'access_key_secret' => $this->config['access_key_secret']
                ],
                'dscsms' => [
                    'app_key' => $this->config['dsc_appkey'],
                    'app_secret' => $this->config['dsc_appsecret']
                ],
                'huawei' => [
                    'app_key' => $this->config['huawei_sms_key'],
                    'app_secret' => $this->config['huawei_sms_secret']
                ]
            ]
        ];

        $data = [];
        /* 手动补充 start */
        if (isset($content['set_sign']) && isset($content['temp_id'])) {
            $data['set_sign'] = isset($content['set_sign']) && !empty($content['set_sign']) ? trim($content['set_sign']) : '';
            $data['temp_id'] = isset($content['temp_id']) && !empty($content['temp_id']) ? trim($content['temp_id']) : '';

            unset($content['set_sign']);
            unset($content['temp_id']);
        }

        if (isset($content['temp_content'])) {
            $data['temp_content'] = isset($content['temp_content']) && !empty($content['temp_content']) ? trim($content['temp_content']) : '';

            unset($content['temp_content']);
        }
        /* 手动补充 end */

        // 发送消息
        $sms = new \App\Channels\Send($config);
        if ($sms->push($mobile, $send_time, $content, $data) === true) {
            return true;
        } else {
            if ($msg === true) {
                return $sms->getError();
            } else {
                return false;
            }
        }
    }

    /**
     * 返回配送方式实例
     * @param string $shipping_code
     * @return \Illuminate\Foundation\Application|mixed|null
     */
    public function shippingInstance($shipping_code = '')
    {
        $shipping = null;
        if ($shipping_code) {
            $shipping_code = Str::studly($shipping_code);
            $plugin = '\\App\\Plugins\\Shipping\\' . $shipping_code . '\\' . $shipping_code;
            if (class_exists($plugin)) {
                $shipping = app($plugin);
            }
        }

        return $shipping;
    }

    /**
     * 返回支付方式实例
     * @param string $pay_code
     * @return \Illuminate\Foundation\Application|mixed|null
     */
    public function paymentInstance($pay_code = '')
    {
        $payment = null;
        if ($pay_code) {
            $pay_code = Str::studly($pay_code);
            $plugin = '\\App\\Plugins\\Payment\\' . $pay_code . '\\' . $pay_code;
            if (class_exists($plugin)) {
                $payment = app($plugin);
            }
        }

        return $payment;
    }

    /**
     * 组合购买商品属性
     *
     * @param array $attr_array
     * @param int $goods_attr_id
     * @return int
     */
    public function getComboGodosAttr($attr_array = [], $goods_attr_id = 0)
    {
        if ($attr_array) {
            for ($i = 0; $i < count($attr_array); $i++) {
                if ($attr_array[$i] == $goods_attr_id) {
                    $checked = 1;
                    break;
                } else {
                    $checked = 0;
                }
            }
        } else {
            $checked = 0;
        }

        return $checked;
    }

    /**
     * 获取属性设置默认值是否大于0
     *
     * @param array $values
     * @return int
     */
    public function getAttrValues($values = [])
    {
        $is_checked = 0;

        if (count($values) > 0) {
            for ($i = 0; $i < count($values); $i++) {
                $is_checked += $values[$i]['attr_checked'] ?? 0;
            }
        }

        return $is_checked;
    }
}
