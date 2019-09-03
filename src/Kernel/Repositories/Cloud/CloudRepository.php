<?php

namespace App\Kernel\Repositories\Cloud;

use App\Libraries\Http;
use App\Models\ShopConfig;
use App\Repositories\Cloud\AliOssRepository;
use App\Repositories\Cloud\HuaweiObsRepository;
use App\Repositories\Common\BaseRepository;

class CloudRepository
{
    protected $storage;
    protected $config = [];
    protected $baseRepository;

    public function __construct(
        AliOssRepository $aliOssRepository,
        HuaweiObsRepository $huaweiObsRepository,
        BaseRepository $baseRepository
    )
    {
        $this->baseRepository = $baseRepository;

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
            /* 获取缓存信息 end */
        } else {
            $this->config['cloud_storage'] = 0;
            $this->config['open_oss'] = 0;
        }

        if (!isset($this->config['cloud_storage'])) {
            $cloud_storage = ShopConfig::where('code', 'cloud_storage')->value('value');
            $cloud_storage = $cloud_storage ? $cloud_storage : 0;
        } else {
            $cloud_storage = $this->config['cloud_storage'];
        }

        if ($cloud_storage == 1) {
            $this->storage = $huaweiObsRepository;
        } else {
            $this->storage = $aliOssRepository;
        }
    }

    /**
     * 获取存储信息
     *
     * @return array|bool|\Illuminate\Cache\CacheManager|mixed
     * @throws \Exception
     */
    public function getDscBucketInfo()
    {
        $info = $this->storage->bucketInfo();
        return $info;
    }

    /**
     * 上传文件
     *
     * @param array $file
     * @throws \OSS\Core\OssException
     */
    public function getDscOssAddFile($file = [])
    {
        if (isset($this->config['open_oss']) && $this->config['open_oss'] == 1) {
            $post_data = $this->dscPostData($file);
            $this->storage->cloudUpload($post_data);
        }
    }

    /**
     * 删除文件
     *
     * @param array $file
     * @throws \OSS\Core\OssException
     */
    public function getDscOssDelFile($file = [])
    {
        if (isset($this->config['open_oss']) && $this->config['open_oss'] == 1) {
            $post_data = $this->dscPostData($file);
            $this->storage->cloudDelete($post_data);
        }
    }

    /**
     * 单个或批量删除图片
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
    public function getDscDelBatch($checkboxs = '', $val_id = '', $select = '', $id = '', $query, $del = 0, $fileDir = '')
    {
        if (empty($checkboxs) && empty($val_id)) {
            $is = false;
        } else {
            $is = true;
        }

        if ($is) {

            $checkboxs = $this->baseRepository->getExplode($checkboxs);

            if (!empty($checkboxs)) {
                $query = $query->whereIn($id, $checkboxs);
            } elseif (!empty($val_id)) {
                $query = $query->where($id, $val_id);
            }

            $list = $this->baseRepository->getToArrayGet($query);

            $arr = ['list' => ''];

            $select = $this->baseRepository->getExplode($select);

            $val = '';
            if ($list) {
                foreach ($list as $key => $row) {
                    $arr[] = $row;
                    foreach ($select as $ks => $rows) {
                        if ($del == 1) {
                            $val .= $row[$rows] . ",";
                            $this->baseRepository->dscUnlink(storage_public($row[$rows]));
                        } else {
                            $val .= $fileDir . $row[$rows] . ",";
                            $this->baseRepository->dscUnlink(storage_public($fileDir . $row[$rows]));
                        }
                    }
                    $arr['list'] .= $val;
                }
            }

            if ($arr) {
                $str_list = substr($arr['list'], 0, -1);
                $str_list = explode(',', $str_list);
            } else {
                $str_list = [];
            }

            foreach ($str_list as $key => $value) {
                if (empty($str_list[$key])) {
                    unset($str_list[$key]);
                }
            }

            if ($str_list && isset($this->config['open_oss']) && $this->config['open_oss'] == 1) {
                $post_data = $this->dscPostData($str_list);
                $this->storage->cloudDelete($post_data);
            }
        }
    }

    /**
     * 删除可视化模板OSS标识文件
     *
     * @param array $ip
     * @param string $suffix
     * @param string $act
     * @param int $seller_id
     */
    public function getDscDelVisualTemplates($ip = [], $suffix = '', $act = 'del_hometemplates', $seller_id = 0)
    {
        if ($ip) {
            $where = '';
            if ($seller_id) {
                $where .= "&seller_id=" . $seller_id;
            }

            if (count($ip) > 1) {
                foreach ($ip as $key => $row) {
                    $url = $this->shop->http() . $row . "/" . "ajax_dialog.php?act=" . $act . "&suffix=" . $suffix . $where;
                    Http::doGet($url);
                }
            } else {
                $url = $this->shop->http() . $ip . "/" . "ajax_dialog.php?act=" . $act . "&suffix=" . $suffix . $where;
                Http::doGet($url);
            }
        }
    }

    /**
     * OSS下载文件
     *
     * @param array $file
     * @return array
     */
    public function getDscOssListFile($file = [])
    {
        $list = [];
        if (isset($this->config['open_oss']) && $this->config['open_oss'] == 1) {
            $post_data = $this->dscPostData($file);
            $list = $this->storage->cloudList($post_data);
        }

        return $list;
    }

    /**
     * 过滤数据
     *
     * @param array $data
     * @return array
     */
    private function dscOssData($data = [])
    {
        $bucket = isset($data['bucket']) ? addslashes_deep($data['bucket']) : '';
        $keyid = isset($data['keyid']) ? addslashes_deep($data['keyid']) : '';
        $keysecret = isset($data['keysecret']) ? addslashes_deep($data['keysecret']) : '';
        $endpoint = isset($data['endpoint']) ? addslashes_deep($data['endpoint']) : '';
        $is_cname = isset($data['is_cname']) ? intval($data['is_cname']) : 1;
        $object = isset($data['object']) ? addslashes_deep($data['object']) : [];

        if ($is_cname == 1) {
            $is_cname = true;
        } else {
            $is_cname = false;
        }

        return [
            'bucket' => $bucket,
            'keyid' => $keyid,
            'keysecret' => $keysecret,
            'endpoint' => $endpoint,
            'is_cname' => $is_cname,
            'object' => $object
        ];
    }

    /**
     * OSS传输数据
     *
     * @param $file
     * @return array
     * @throws \Exception
     */
    private function dscPostData($file)
    {
        $post_data = [];
        if (!isset($this->config['open_oss'])) {
            $is_oss = ShopConfig::where('code', 'open_oss')->value('value');
        } else {
            $is_oss = $this->config['open_oss'];
        }

        if ($file && $is_oss) {
            $bucket_info = $this->getDscBucketInfo();
            $post_data = [
                'bucket' => $bucket_info['bucket'],
                'keyid' => $bucket_info['keyid'],
                'keysecret' => $bucket_info['keysecret'],
                'is_cname' => $bucket_info['is_cname'],
                'endpoint' => $bucket_info['outside_site'],
                'is_delimg' => $bucket_info['is_delimg'],
                'object' => array_values($file)
            ];

            $post_data = $this->dscOssData($post_data);
        }

        return $post_data;
    }
}
