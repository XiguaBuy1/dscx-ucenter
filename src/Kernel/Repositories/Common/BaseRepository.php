<?php

namespace App\Kernel\Repositories\Common;

use App\Kernel\Repositories\Repository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * 公共函数
 * Class BaseRepository
 * @package App\Kernel\Repositories
 */
class BaseRepository extends Repository
{

    /**
     * 返回数组列表
     *
     * @param array $query
     * @return array
     */
    public function getToArrayGet($query = [])
    {
        if ($query) {
            $query = $query->get();

            $query = $query ? $query->toArray() : [];
        }

        return $query;
    }

    /**
     * 返回一条数据数组
     *
     * @param array $query
     * @return array
     */
    public function getToArrayFirst($query = [])
    {
        if ($query) {
            $query = $query->first();

            $query = $query ? $query->toArray() : [];
        }

        return $query;
    }

    /**
     * 按指定key排序，返回排序数组数据
     *
     * @param array $list
     * @param string $sort
     * @param string $order
     * @return array|static
     */
    public function getSortBy($list = [], $sort = '', $order = 'asc')
    {
        if ($list) {
            if ($order == 'asc') {
                $list = collect($list)->sortBy($sort);
            } else {
                $list = collect($list)->sortByDesc($sort);
            }

            $list = $list->values()->all();
        }

        return $list;
    }

    /**
     * 按数组值排序，返回排序数组数据
     *
     * 默认从小到大
     *
     * @param array $list
     * @return array|\Illuminate\Support\Collection
     */
    public function getSort($list = [])
    {
        if ($list) {
            $list = collect($list)->sort();
            $list = $list->values()->all();
        }

        return $list;
    }

    /**
     * 按key值排序，返回排序数组数据
     *
     * @param array $list
     * @param string $order
     * @return array|\Illuminate\Support\Collection
     */
    public function sortKeys($list = [], $order = 'asc')
    {
        if ($list) {
            if ($order == 'desc') {
                $list = collect($list)->sortKeysDesc();
            } else {
                $list = collect($list)->sortKeys();
            }

            $list = $list->values()->all();
        }

        return $list;
    }

    /**
     * 获取指定数组条数
     *
     * @param array $list
     * @param int $mun
     * @return array
     */
    public function getTake($list = [], $mun = 0)
    {
        if ($list && $mun) {
            $list = collect($list)->take($mun)->all();
        }

        return $list;
    }

    /**
     * 获取指定键值数组数据
     *
     * @param array $list
     * @param string $key
     * @return array
     */
    public function getKeyPluck($list = [], $key = '')
    {
        if ($list && $key) {
            $list = collect($list)->pluck($key)->all();
            $list = array_unique($list);
            $list = array_values($list);
        }

        return $list;
    }

    /**
     * 获取组合数组的新数组
     *
     * @access  public
     * @param   array $list
     * @param   string $val
     * @return  array
     */
    public function getGroupBy($list = [], $val = '')
    {
        if ($list && $val) {
            $list = collect($list)->groupBy($val)->toArray();
        }

        return $list;
    }

    /**
     * 获取多维数组转为一维数组
     *
     * @param array $list
     * @param int $mun
     * @return array
     */
    public function getFlatten($list = [], $mun = 0)
    {
        if ($list) {
            if ($mun > 0) {
                $list = collect($list)->flatten($mun)->all();
            } else {
                $list = collect($list)->flatten()->all();
            }
        }

        return $list;
    }

    /**
     * 获取字符串转数组
     *
     * @param array $val
     * @param string $str
     * @return array
     */
    public function getExplode($val = [], $str = ',')
    {
        if ($val) {
            $val = !is_array($val) ? explode($str, $val) : $val;
        } else {
            $val = [];
        }

        return $val;
    }

    /**
     * 获取字符串转数组
     *
     * * 注：一维数组
     * $arr = [1, 2, 3];
     * 或 getImplode($arr, $where['replace' => ' | ']);
     *
     * 注：可获取二维数组自定键值的数组进行拆分
     * $arr = [
     *    [
     *       'id' => 1,
     *       'name' => '大商创'
     *    ],
     *    [
     *       'id' => 2,
     *       'name' => 'Ectouch'
     *    ],
     *   [
     *       'id' => 3,
     *       'name' => 'Ecjia'
     *    ]
     * ];
     *
     * getImplode($arr, $where['str' => 'name', 'replace' => ' | ']);
     *
     * 注：$where['is_need']，扩展判断未知数组是否为一维数组
     * 【无需判断：0, 执行判断：1】
     *
     * @access  public
     * @param   array $val
     * @param   array $where
     * @return  array
     */
    public function getImplode($val = [], $where = ['str' => '', 'replace' => ',', 'is_need' => 0])
    {
        $where['replace'] = !isset($where['replace']) ? ',' : $where['replace'];
        $where['is_need'] = !isset($where['is_need']) ? 0 : $where['is_need'];

        if ($val) {
            if (isset($where['str']) && !empty($where['str'])) {
                $val = collect($val)->implode($where['str'], $where['replace']);
            } else {
                if ($where['is_need'] == 1) {
                    $nonOne = $this->getNonOneDimensionalArray($val);

                    if ($nonOne) {
                        return $val;
                    }
                }

                $val = implode($where['replace'], $val);
            }
        }

        return $val;
    }

    /**
     * 判断是否非一维数组
     *
     * 【false：一维数组, true：多维数组】
     *
     * @param array $list
     * @return bool
     */
    public function getNonOneDimensionalArray($list = [])
    {
        if ($list) {
            foreach ($list as $k => $v) {
                if (is_array($v)) {
                    return true;
                }
            }
        } else {
            return false;
        }
    }

    /**
     * 合并数组
     *
     * @param array $list
     * @param array $row
     * @return array
     */
    public function getArrayMerge($list = [], $row = [])
    {
        if ($list && $row) {
            $list = array_merge($list, $row);
        } elseif (empty($list) && $row) {
            $list = $row;
        }

        return $list;
    }

    /**
     * 获取数组计算指定键值数量
     *
     * 适用数据为二维数组
     *
     * @param array $list
     * @param string $str
     * @return int|mixed
     */
    public function getSum($list = [], $str = '')
    {
        $mun = 0;
        if ($list && $str) {
            $mun = collect($list)->sum($str);
        }

        return $mun;
    }

    /**
     * 获取数组计算指定键值数量
     *
     * 适用数据为二维数组
     *
     * @param array $list
     * @param array $where
     * @return array
     */
    public function getWhere($list = [], $where = ['str' => '', 'estimate' => '', 'val' => ''])
    {
        $where['str'] = !isset($where['str']) ? '' : $where['str'];
        $where['estimate'] = !isset($where['estimate']) ? '' : $where['estimate'];
        $where['val'] = !isset($where['val']) ? '' : $where['val'];

        if ($list && $where['str']) {
            if ($where['estimate']) {
                $list = collect($list)->where($where['str'], $where['estimate'], $where['val'])->all();
            } else {
                $list = collect($list)->where($where['str'], $where['val'])->all();
            }
        }

        return $list;
    }

    /**
     * 交换数组中的键和值
     *
     * 适用数据为二维数组
     *
     * @param array $list
     * @return array
     */
    public function getArrayFlip($list = [])
    {
        if ($list) {
            $list = collect($list)->flip()->all();
        }

        return $list;
    }

    /**
     * 数组的「键」进行比较，计算数组的交集
     *
     * 适用数据为二维数组
     *
     * @param array $list 返回值
     * @param array $columns 交集部分（value值）
     * @return array
     */
    public function getArrayIntersect($list = [], $columns = [])
    {
        if ($list && $columns) {
            $list = collect($list)->intersect($columns)->all();
        }

        return $list;
    }

    /**
     * 数组的「值」进行比较，计算数组的交集
     *
     * 适用数据为二维数组
     *
     * @param array $list
     * @param array $columns
     * @return array
     */
    public function getArrayIntersectByKeys($list = [], $columns = [])
    {
        if ($list && $columns) {
            $list = collect($list)->intersectByKeys($columns)->all();
        }

        return $list;
    }

    /**
     * 数组的「值」进行比较， 计算数组的差集
     *
     * 适用数据为二维数组
     *
     * @param array $list
     * @param array $columns
     * @return array
     */
    public function getArrayDiff($list = [], $columns = [])
    {
        if ($list) {
            $list = collect($list)->diff($columns)->all();
        }

        return $list;
    }

    /**
     * 数组的「键」进行比较， 计算数组的差集
     *
     * 适用数据为二维数组
     *
     * @param array $list
     * @param array $columns
     * @return array
     */
    public function getArrayDiffKeys($list = [], $columns = [])
    {
        if ($list) {
            $list = collect($list)->diffKeys($columns)->all();
        }

        return $list;
    }

    /**
     * 移除数组中重复的值
     *
     * 适用数据为二维数组
     *
     * @param array $list 返回值
     * @param string $key 指定键名【可选】
     * @return array|\Illuminate\Support\Collection
     */
    public function getArrayUnique($list = [], $key = '')
    {
        if ($list) {
            $list = collect($list)->unique($key);
            $list = $list->values()->all();
        }

        return $list;
    }

    /**
     * 存储缓存数组指定内容
     *
     * @param array $list
     * @throws \Exception
     */
    public function getCacheForeverlist($list = [])
    {
        if ($list) {
            foreach ($list as $key => $row) {
                cache()->forever($key, $row);
            }
        }
    }

    /**
     * 清除数组指定缓存
     *
     * @param array $list
     * @throws \Exception
     */
    public function getCacheForgetlist($list = [])
    {
        if ($list) {
            foreach ($list as $key => $row) {
                cache()->forget($row);
            }
        }
    }

    /**
     * 获取接口数据
     *
     * @return string
     */
    public function getBrowseUrl()
    {
        return 'https://console.dscmall.cn/api/browse';
    }

    /**
     * 数组内容移除指定键名项
     *
     * @access  public
     * @param   array $list 列表
     * @return  array
     */

    public function getArrayExcept($list = [], $val = [])
    {
        if ($val) {
            $list = collect($list)->except($val)->all();
        }

        return $list;
    }

    /**
     * 生成永久缓存文件
     *
     * @param string $name
     * @param array $data
     */
    public function setDiskForever($name = 'file', $data = [])
    {
        $content = "<?php\r\n";
        $content .= "\$data = " . var_export($data, true) . ";\r\n\r\n";
        $content .= "return \$data;\r\n";

        Storage::disk('forever')->put($name . '.php', $content);
    }

    /**
     * 获取缓存文件内容
     *
     * @param string $name
     * @return bool|mixed
     */
    public function getDiskForeverData($name = '')
    {
        if ($this->getDiskForeverExists($name)) {
            return include_once(storage_path('framework/cache/forever/' . $name . ".php"));
        } else {
            return false;
        }
    }

    /**
     * 删除缓存文件
     *
     * @param string $name
     */
    public function getDiskForeverDelete($name = '')
    {
        if ($this->getDiskForeverExists($name)) {
            Storage::disk('forever')->delete($name . '.php');
        }
    }

    /**
     * 判断缓存文件是否存在
     *
     * @param string $name
     * @return bool
     */
    public function getDiskForeverExists($name = '')
    {
        $exists = Storage::disk('forever')->exists($name . '.php');

        if ($exists) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取最小值
     *
     * @param array $list
     * @param string $str
     * @return array|mixed
     */
    public function getArrayMin($list = [], $str = '')
    {
        $min = [];
        if ($list) {
            $min = collect($list)->min($str);
        }

        return $min;
    }

    /**
     * 获取最大值
     *
     * @param array $list
     * @param string $str
     * @return mixed
     */
    public function getArrayMax($list = [], $str = '')
    {
        $max = [];
        if ($list) {
            $max = collect($list)->max($str);
        }

        return $max;
    }

    /**
     * 计算数组数量
     *
     * @param array $list
     * @return int
     */
    public function getArrayCount($list = [])
    {
        $count = 0;
        if ($list) {
            $count = collect($list)->count();
        }

        return $count;
    }

    /**
     * 多数组交集组合新数组
     *
     * 【支持10组数组】
     *
     * @param array $list
     * @param int $page
     * @param int $size
     * @return array
     */
    public function getArrayCrossJoin($list = [], $page = 1, $size = 0)
    {
        if (count($list) > 1) {
            $matrix = collect($list[0]);

            if (count($list) == 2) {
                $matrix = $matrix->crossJoin($list[1]);
            } elseif (count($list) == 3) {
                $matrix = $matrix->crossJoin($list[1], $list[2]);
            } elseif (count($list) == 4) {
                $matrix = $matrix->crossJoin($list[1], $list[2], $list[3]);
            } elseif (count($list) == 5) {
                $matrix = $matrix->crossJoin($list[1], $list[2], $list[3], $list[4]);
            } elseif (count($list) == 6) {
                $matrix = $matrix->crossJoin($list[1], $list[2], $list[3], $list[4], $list[5]);
            } elseif (count($list) == 7) {
                $matrix = $matrix->crossJoin($list[1], $list[2], $list[3], $list[4], $list[5], $list[6]);
            } elseif (count($list) == 8) {
                $matrix = $matrix->crossJoin($list[1], $list[2], $list[3], $list[4], $list[5], $list[6], $list[7]);
            } elseif (count($list) == 9) {
                $matrix = $matrix->crossJoin($list[1], $list[2], $list[3], $list[4], $list[5], $list[6], $list[7], $list[8]);
            } elseif (count($list) == 10) {
                $matrix = $matrix->crossJoin($list[1], $list[2], $list[3], $list[4], $list[5], $list[6], $list[7], $list[8], $list[9]);
            } elseif (count($list) == 11) {
                $matrix = $matrix->crossJoin($list[1], $list[2], $list[3], $list[4], $list[5], $list[6], $list[7], $list[8], $list[9], $list[10]);
            } elseif (count($list) == 12) {
                $matrix = $matrix->crossJoin($list[1], $list[2], $list[3], $list[4], $list[5], $list[6], $list[7], $list[8], $list[9], $list[10], $list[11]);
            } elseif (count($list) == 13) {
                $matrix = $matrix->crossJoin($list[1], $list[2], $list[3], $list[4], $list[5], $list[6], $list[7], $list[8], $list[9], $list[10], $list[11], $list[12]);
            } elseif (count($list) == 14) {
                $matrix = $matrix->crossJoin($list[1], $list[2], $list[3], $list[4], $list[5], $list[6], $list[7], $list[8], $list[9], $list[10], $list[11], $list[12], $list[13]);
            } elseif (count($list) == 15) {
                $matrix = $matrix->crossJoin($list[1], $list[2], $list[3], $list[4], $list[5], $list[6], $list[7], $list[8], $list[9], $list[10], $list[11], $list[12], $list[13], $list[14]);
            }

            $matrix = $matrix->all();

            if ($size > 0) {
                $list = app(DscRepository::class)->pageArray($size, $page, $matrix);
            } else {
                $list = $matrix;
            }
        }

        return $list;
    }

    /**
     * 分页
     *
     * $path = asset('/') . 'user.php';
     * $pageName = 'a=1&b=2&page';
     * $options = [
     *      'path' => $path,
     *      'pageName' => $pageName
     * ];
     *
     * @param array $list
     * @param int $size
     * @param array $options
     * @return array|LengthAwarePaginator
     */
    public function getPaginate($list = [], $size = 15, $options = [])
    {
        //获取当前的分页数
        $page = LengthAwarePaginator::resolveCurrentPage();
        $page = intval($page);

        $count = collect($list)->count();

        //获取当前需要显示的数据列表
        $currentPageSearchResults = collect($list)->slice($page * $size, $size)->all();

        //创建一个新的分页方法
        $list = new LengthAwarePaginator($currentPageSearchResults, $count, $size, $page, $options);
        $list = $list->toArray();

        $list['first_page_url'] = $list['first_page_url'] ? urldecode($list['first_page_url']) : '';
        $list['next_page_url'] = $list['next_page_url'] ? urldecode($list['next_page_url']) : '';
        $list['prev_page_url'] = $list['prev_page_url'] ? urldecode($list['prev_page_url']) : '';
        $list['last_page_url'] = $list['last_page_url'] ? urldecode($list['last_page_url']) : '';

        return $list;
    }

    /**
     * 打印SQL语句
     *
     * @param $builder
     */
    public function toSql($builder)
    {
        $bindings = $builder->getBindings();
        $sql = str_replace('?', '%s', $builder->toSql());
        $sql = sprintf($sql, ...$bindings);
        dd($sql);
    }

    /**
     * 返回以键名为集合的数组
     *
     * @param array $list
     * @return array
     */
    public function getArrayKeys($list = [])
    {
        if ($list) {
            $list = collect($list)->keys()->all();
            $list = array_unique($list);
            $list = array_values($list);
        }

        return $list;
    }

    /**
     * 将值添加到数组
     *
     * @param array $list
     * @param string $push
     * @return array
     */
    public function getArrayPush($list = [], $push = '')
    {
        if ($push) {
            $list = collect($list)->push($push)->all();
        }

        return $list;
    }

    /**
     * 查找指定值是否存在在数组中
     *
     * @param array $list
     * @param string $val
     * @param null $bool
     * @return bool|mixed
     */
    public function getArraySearch($list = [], $val = '', $bool = null)
    {
        if ($list && $val) {
            if (!is_null($bool)) {
                return collect($list)->search($val, $bool);
            } else {
                return collect($list)->search($val);
            }
        }

        return false;
    }

    /**
     * 过滤表字段数组
     *
     * @param array $other
     * @param string $table
     * @return array
     */
    public function getArrayfilterTable($other = [], $table = '')
    {
        /* 获取表字段 */
        $columns = Schema::getColumnListing($table);
        $columns = $this->getArrayFlip($columns);

        $other = $this->getArrayIntersectByKeys($other, $columns);

        return $other;
    }

    /**
     * 生成原生SQL
     *
     * @param array $list
     * @return array
     */
    public function getDbRaw($list = [])
    {
        $arr = [];
        if ($list) {
            foreach ($list as $key => $val) {
                $arr[$key] = DB::raw($val);
            }
        }

        return $arr;
    }

    /**
     * 将多个数组合并成一个数组
     *
     * $list = [$arr1, $arr2] = array_merge($arr1, $arr2) 方法的强化版
     *
     * @param array $list
     * @return array
     */
    public function getArrayCollapse($list = [])
    {
        if ($list) {

            $arr = [];

            foreach ($list as $key => $val) {
                if ($val) {
                    $arr[$key] = $val;
                }
            }

            if ($arr) {
                $list = Arr::collapse($arr);
            }
        }

        return $list;
    }

    /**
     * 删除文件
     *
     * @param string $file
     * @param string $path
     */
    public function dscUnlink($file = '', $path = '')
    {
        if ($file) {
            if (is_array($file)) {
                foreach ($file as $key => $row) {
                    if ($row) {
                        $row = trim($row);
                        $row = $path . $row;
                        if (is_file($row)) {
                            @unlink($row);
                        }
                    }
                }
            } else {
                $file = trim($file);
                $file = $path . $file;
                if (is_file($file)) {
                    @unlink($file);
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
    public function getTrimUrl($url = '')
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
     * 获取http(s)://
     *
     * @return string
     */
    public function dscHttp()
    {
        $url = asset('/');
        $url = explode('//', $url);
        $http = $url[0] . '//';

        return $http;
    }
}
