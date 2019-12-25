<?php

namespace App\Kernel\Repositories\Common;

use App\Kernel\Repositories\Repository;
use App\Models\Sessions;
use App\Models\SessionsData;

class SessionRepository extends Repository
{
    public $max_life_time = 1800; // SESSION Lifecycle

    public $session_name = '';
    public $session_id = '';

    public $session_expiry = '';
    public $session_md5 = '';

    public $_ip = '';
    public $_time = 0;

    // 存储一条session
    public function sessionPut($key = '', $value = '')
    {
        if (config('session.driver') === 'database') {
            session($key, $value);
        } else {
            session($key, $value);
        }
    }

    // 获取一条session
    public function sessionGet($key, $default_value = 0)
    {
        if (config('session.driver') === 'database') {
            return session()->has($key) ? session($key) : $default_value;
        } else {
            return session()->has($key) ? session($key) : $default_value;
        }
    }

    public function sessionRepy($session_name = 'ECS_ID', $session_id = '')
    {
        if (config('session.driver') === 'database') {
            session([]);

            $this->session_name = $session_name;

            $this->_ip = request()->getClientIp();

            if ($session_id == '' && request()->hasCookie($this->session_name) && request()->cookie($this->session_name)) {
                $this->session_id = request()->cookie($this->session_name);
            } else {
                $this->session_id = $session_id;
            }

            if ($this->session_id) {
                $tmp_session_id = substr($this->session_id, 0, 32);
                if ($this->gen_session_key($tmp_session_id) == substr($this->session_id, 32)) {
                    $this->session_id = $tmp_session_id;
                } else {
                    $this->session_id = '';
                }

                $this->_time = time();

                if ($this->session_id) {
                    $this->load_session();
                } else {
                    $this->gen_session_id();

                    cookie()->queue($this->session_name, $this->session_id . $this->gen_session_key($this->session_id), 0);
                }

                register_shutdown_function([&$this, 'close_session']);
            }
        } else {
            $this->load_session();
        }
    }

    public function gen_session_id()
    {
        $this->session_id = session()->getId();

        return $this->insert_session();
    }

    public function gen_session_key($session_id)
    {
        static $ip = '';

        if ($ip == '') {
            $ip = substr($this->_ip, 0, strrpos($this->_ip, '.'));
        }

        return sprintf('%08x', crc32(strtolower(base_path()) . $ip . $session_id));
    }

    public function insert_session()
    {
        if (config('session.driver') === 'database') {
            $id = 0;
            if ($this->session_id) {
                $id = Sessions::where('sesskey', $this->session_id)->count();
            }

            if ($id <= 0) {
                $other = [
                    'sesskey' => $this->session_id,
                    'expiry' => $this->_time,
                    'ip' => $this->_ip,
                    'data' => 'a:0:{}'
                ];

                $id = Sessions::insertGetId($other);
            }

            if ($id > 0) {
                return true;
            } else {
                Sessions::truncate();
                return $id = Sessions::insertGetId($other);;
            }
        } else {
            $other = [
                'data' => 'a:0:{}',
                'user_id' => 0,
                'admin_id' => 0,
                'user_name' => '',
                'user_rank' => 0,
                'discount' => 0,
                'email' => ''
            ];

            session($other);

            return true;
        }
    }

    public function load_session()
    {
        if (config('session.driver') === 'database') {
            $session = Sessions::where('sesskey', $this->session_id)->first();
            $session = $session ? $session->toArray() : [];

            if (empty($session)) {
                $this->insert_session();

                $this->session_expiry = 0;
                $this->session_md5 = '40cd750bba9870f18aada2478b24840a';

                session([]);
            } else {
                if (!empty($session['data']) && $this->_time - $session['expiry'] <= $this->max_life_time) {
                    $this->session_expiry = $session['expiry'];
                    $this->session_md5 = md5($session['data']);
                    $other = [
                        unserialize($session['data']),
                        'user_id' => $session['userid'],
                        'admin_id' => $session['adminid'],
                        'user_name' => $session['user_name'],
                        'user_rank' => $session['user_rank'],
                        'discount' => $session['discount'],
                        'email' => $session['email']
                    ];
                    session($other);
                } else {

                    $session_data = SessionsData::where('sesskey', $this->session_id)->first();
                    $session_data = $session_data ? $session_data->toArray() : [];

                    if ($session_data && !empty($session_data['data']) && $this->_time - $session_data['expiry'] <= $this->max_life_time) {
                        $this->session_expiry = $session_data['expiry'];
                        $this->session_md5 = md5($session_data['data']);

                        $other = [
                            unserialize($session_data['data']),
                            'user_id' => $session['userid'],
                            'admin_id' => $session['adminid'],
                            'user_name' => $session['user_name'],
                            'user_rank' => $session['user_rank'],
                            'discount' => $session['discount'],
                            'email' => $session['email']
                        ];

                        session($other);
                    } else {
                        $this->session_expiry = 0;
                        $this->session_md5 = '40cd750bba9870f18aada2478b24840a';
                        session([]);
                    }
                }
            }
        } else {
            $other = [
                'user_id' => session('user_id', 0),
                'user_name' => session('user_name', ''),
                'user_rank' => session('user_rank', 1),
                'discount' => session('discount', 1),
                'email' => session('email', '')
            ];
            session($other);
        }
    }

    public function update_session()
    {
        $adminid = session()->has('admin_id') && session('admin_id') ? intval(session('admin_id')) : 0;
        $userid = session()->has('user_id') && session('user_id') ? trim(session('admin_id')) : 0;
        $user_name = session()->has('user_name') && session('user_name') ? intval(session('admin_id')) : 0;
        $user_rank = session()->has('user_rank') && session('user_rank') ? intval(session('admin_id')) : 0;
        $discount = session()->has('discount') && session('discount') ? round(session('discount'), 2) : 0;
        $email = session()->has('email') && session('email') ? trim(session('email')) : 0;

        session('admin_id')->forget();
        session('user_id')->forget();
        session('user_name')->forget();
        session('user_rank')->forget();
        session('discount')->forget();
        session('email')->forget();

        $res = true;
        if (config('session.driver') === 'database') {
            $data = serialize(session());
            $this->_time = time();

            if ($this->session_md5 == md5($data) && $this->_time < $this->session_expiry + 10) {
                return true;
            }

            $data = addslashes($data);

            if (isset($data{255})) {

                $other = [
                    'sesskey' => $this->session_id,
                    'expiry' => $this->_time, 'data' => $data
                ];
                $search = [
                    'expiry' => $this->_time,
                    'data' => $data
                ];

                SessionsData::updateOrCreate($other, $search);

                $data = '';
            }

            $res = Sessions::where('sesskey', $this->session_id)->update([
                'expiry' => $this->_time,
                'ip' => $this->_ip,
                'userid' => $userid,
                'adminid' => $adminid,
                'user_name' => $user_name,
                'user_rank' => $user_rank,
                'discount' => $discount,
                'email' => $email,
                'data' => $data
            ]);
        } else {
            $data = serialize(session()->all());
            $data = addslashes($data);

            $other = [
                'expiry' => $this->_time,
                'ip' => $this->_ip,
                'userid' => $userid,
                'adminid' => $adminid,
                'user_name' => $user_name,
                'user_rank' => $user_rank,
                'discount' => $discount,
                'email' => $email,
                'data' => $data
            ];

            session($other);
        }

        return $res;
    }

    public function close_session()
    {
        $this->update_session();

        if (config('session.driver') === 'database') {
            $expiry = $this->_time - $this->max_life_time;

            if (mt_rand(0, 2) == 2) {
                SessionsData::where('expiry', '<', $expiry)->delete();
            }

            if ((time() % 2) == 0) {
                return Sessions::where('expiry', '<', $expiry)->delete();
            }
        }

        return true;
    }

    public function delete_spec_admin_session($adminid)
    {
        if (config('session.driver') === 'database') {
            if (session()->has('admin_id') && !empty(session('admin_id')) && $adminid) {
                return Sessions::where('adminid', $adminid)->delete();
            } else {
                return false;
            }
        }

        return false;
    }

    /**
     * 清除session
     *
     * @param array $list
     * @return bool
     */
    public function destroy_session($list = [])
    {
        if ($list) {
            foreach ($list as $key => $val) {
                session()->forget($val);
            }
        } else {
            session()->flush();
        }

        if (config('session.driver') === 'database') {
            SessionsData::where('sesskey', $this->session_id)->delete();

            return Sessions::where('sesskey', $this->session_id)->delete();
        }

        return true;
    }

    /**
     * 清除cookie
     *
     * @param array $list
     * @return bool
     */
    public function destroy_cookie($list = [])
    {
        $type = 0;
        if (empty($list)) {
            $list = request()->cookie();
        } else {
            $type = 1;
        }

        $cookieList = [];
        if ($list) {
            foreach ($list as $key => $val) {
                if ($type == 1) {
                    $cookieList[] = $val;
                } else {
                    $arr = [];
                    if (is_array($val)) {
                        foreach ($val as $idx => $row) {
                            $arr[] = $key . '[' . $idx . ']';
                        }

                        $this->deleteCookie($arr);
                    }

                    $cookieList[] = $key;
                }
            }
        }

        $this->deleteCookie($cookieList);

        return true;
    }

    /**
     *  获取 session_id
     *
     * @return string
     */
    public function getSessionId()
    {
        if (config('session.driver') !== 'database') {
            $this->session_id = session()->getId();
        }

        return $this->session_id;
    }

    public function get_users_count()
    {
        if (config('session.driver') === 'database') {
            return Sessions::count();
        } else {
            return 0;
        }
    }

    /**
     * 获得用户的真实IP地址和MAC地址
     *
     * @return array|false|null|string
     */
    public function realCartMacIp()
    {
        $realip = null;

        if (request()->hasHeader('X-Client-Hash')) {
            $realip = request()->header('X-Client-Hash');
        } else {

            //缓存地区ID
            if (request()->hasCookie('session_id_ip')) {
                $realip = request()->cookie('session_id_ip');
            } else {

                if (request()->server()) {
                    if (request()->server('HTTP_X_FORWARDED_FOR')) {
                        $arr = explode(',', request()->server('HTTP_X_FORWARDED_FOR'));

                        /* 取X-Forwarded-For中第一个非unknown的有效IP字符串 */
                        foreach ($arr as $ip) {
                            $ip = trim($ip);

                            if ($ip != 'unknown') {
                                $realip = $ip;

                                break;
                            }
                        }
                    } elseif (request()->server('HTTP_CLIENT_IP')) {
                        $realip = request()->server('HTTP_CLIENT_IP');
                    } else {
                        if (request()->server('REMOTE_ADDR')) {
                            $realip = request()->server('REMOTE_ADDR');
                        } else {
                            $realip = '0.0.0.0';
                        }
                    }
                } else {
                    if (getenv('HTTP_X_FORWARDED_FOR')) {
                        $realip = getenv('HTTP_X_FORWARDED_FOR');
                    } elseif (getenv('HTTP_CLIENT_IP')) {
                        $realip = getenv('HTTP_CLIENT_IP');
                    } else {
                        $realip = getenv('REMOTE_ADDR');
                    }
                }

                preg_match("/[\d\.]{7,15}/", $realip, $onlineip);
                $realip = !empty($onlineip[0]) ? $onlineip[0] : '0.0.0.0';

                if (defined('SESS_ID')) {
                    $realip = $realip . '_' . SESS_ID;
                }
                $time = $this->sessionTime() + 3600 * 24 * 365;
                cookie()->queue('session_id_ip', $realip, $time);
            }
        }

        return is_null($realip) ? session()->getId() : $realip;
    }

    /**
     * 获得当前格林威治时间的时间戳
     *
     * @return  integer
     */
    private function sessionTime()
    {
        return (time() - date('Z'));
    }

    /**
     * 删除cookie
     *
     * @param array $list
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteCookie($list = [])
    {

        if ($list) {
            if (!is_array($list)) {
                $list = explode(',', $list);
            }

            foreach ($list as $key => $val) {
                cookie()->queue(cookie()->forget($val));
            }
        }

        return response();
    }

    /**
     * 获取cookie
     *
     * @param array $list
     * @param int $default
     * @return array
     */
    public function getCookie($list = [], $default = 0)
    {
        if ($list) {
            $arr = [];
            if (is_array($list)) {
                foreach ($list as $key => $val) {
                    $arr[$key] = request()->cookie($val, $default);
                }

                return $arr;
            } else {
                return request()->cookie($list, $default);
            }
        } else {
            return request()->cookie();
        }
    }

    /**
     * 存储cookie
     *
     * @param array $list
     * @param int $default
     * @return array
     */
    public function setCookie($list = [], $default = 0)
    {
        if ($list) {
            if (is_array($list)) {
                foreach ($list as $key => $val) {

                    if (!is_array($val) && is_null($val)) {
                        $val = '';
                    }

                    if (!is_array($val)) {
                        cookie()->queue($key, $val);
                    }
                }
            } else {
                if (!is_array($default)) {
                    cookie()->queue($list, $default);
                }
            }

            return request()->cookie();
        }
    }
}
