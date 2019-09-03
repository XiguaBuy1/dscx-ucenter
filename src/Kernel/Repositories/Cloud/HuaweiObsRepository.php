<?php

namespace App\Kernel\Repositories\Cloud;

use App\Kernel\Repositories\Repository;
use App\Libraries\Shop;
use App\Models\ObsConfigure;
use App\Kernel\Repositories\Common\BaseRepository;
use App\Kernel\Repositories\Common\DscRepository;
use Obs\ObsClient;

class HuaweiObsRepository extends Repository
{
    private $shop;
    private $baseRepository;

    public function __construct(
        Shop $shop,
        BaseRepository $baseRepository
    )
    {
        $this->shop = $shop;
        $this->baseRepository = $baseRepository;
    }

    /**
     * 上传OBS图片
     *
     * @param array $data
     */
    public function cloudUpload($data = [])
    {
        if ($data) {
            $fileList = app(DscRepository::class)->getInciseDirectory($data['object']);

            $config = [
                'key' => $data['keyid'],
                'secret' => $data['keysecret'],
                'endpoint' => $data['endpoint']
            ];

            $obsClient = new ObsClient($config);

            if ($fileList) {
                foreach ($fileList as $key => $row) {
                    if (isset($row['file']) && $row['file']) {
                        $uploadFile = [
                            'Bucket' => $data['bucket'],
                            'Key' => $row['file'],
                            'SourceFile' => storage_public($row['file'])
                        ];

                        if (is_file($uploadFile['SourceFile'])) {
                            $obsClient->putObject($uploadFile);
                        }
                    }
                }

                $arr = $this->getObjectList($obsClient, $data['bucket'], $fileList);

                if ($arr) {
                    foreach ($fileList as $key => $val) {
                        if (in_array($val['file'], $arr)) {
                            $this->baseRepository->dscUnlink(storage_public($val['file']));
                        }
                    }
                }
            }

            $obsClient->close();
        }
    }

    /**
     * 删除OBS图片
     *
     * @param array $data
     */
    public function cloudDelete($data = [])
    {
        if ($data) {
            $fileList = app(DscRepository::class)->getInciseDirectory($data['object']);

            $config = [
                'key' => $data['keyid'],
                'secret' => $data['keysecret'],
                'endpoint' => $data['endpoint']
            ];

            $obsClient = new ObsClient($config);

            if ($fileList) {
                foreach ($fileList as $key => $row) {

                    $deleteFile = [
                        'Bucket' => $data['bucket'],
                        'Key' => $row['file']
                    ];

                    $obsClient->deleteObject($deleteFile);

                    $file = $row ? storage_public($row['file']) : '';
                    if ($file && is_file($file)) {
                        $this->baseRepository->dscUnlink($file);
                    }
                }
            }

            $obsClient->close();
        }
    }

    /**
     * 获取OBS指定信息
     *
     * @param $data
     * @return array
     */
    public function cloudList($data)
    {

        $arr = [];
        if ($data) {
            $fileList = app(DscRepository::class)->getInciseDirectory($data['object']);

            $config = [
                'key' => $data['keyid'],
                'secret' => $data['keysecret'],
                'endpoint' => $data['endpoint']
            ];

            $obsClient = new ObsClient($config);

            $arr = $this->getObjectList($obsClient, $data['bucket'], $fileList);

            $obsClient->close();
        }

        return $arr;
    }

    /**
     * 获取OBS Bucket信息
     *
     * @return array|bool|\Illuminate\Cache\CacheManager|mixed
     * @throws \Exception
     */
    public function bucketInfo()
    {
        $res = cache('obs_bucket_info');
        $res = !is_null($res) ? $res : false;

        if ($res === false) {
            $res = ObsConfigure::where('is_use', 1);
            $res = $res->first();
            $res = $res ? $res->toArray() : [];

            if ($res) {
                $res['port'] = $res['port'] ?? '';

                $port = '';
                if ($res['port']) {
                    $port = ':' . $res['port'];
                }

                $res['outside_site'] = $this->shop->http() . 'obs.' . $res['regional'] . '.myhuaweicloud.com' . $port;

                if (empty($res['endpoint'])) {
                    $res['endpoint'] = $this->shop->http() . $res['bucket'] . '.obs.' . $res['regional'] . ".myhuaweicloud.com/";
                } else {
                    $res['endpoint'] = rtrim($res['endpoint'], '/') . "/";
                }

                cache()->forever('obs_bucket_info', $res);
            } else {
                $res['endpoint'] = '';
            }

            $res['is_delimg'] = 1;
        }

        return $res;
    }

    /**
     * 获取OBS对象列表
     *
     * @param $obsClient
     * @param $bucket
     * @param $fileList
     * @return array
     */
    private function getObjectList($obsClient, $bucket, $fileList)
    {
        $arr = [];
        $list = [];
        if ($fileList) {
            foreach ($fileList as $key => $row) {
                if (isset($row['file']) && $row['file']) {
                    $data = [
                        'Bucket' => $bucket,
                        'Prefix' => $row['file']
                    ];

                    $list[] = $obsClient->listObjects($data);
                }
            }

            if ($list) {
                foreach ($list as $key => $val) {
                    if (isset($val['Contents']) && $val['Contents']) {
                        $arr[$key] = $val['key'];
                        foreach ($val['Contents'] as $k => $v) {
                            $arr[$key][$k] = $v['Key'];
                        }
                    }
                }
            }
        }

        //转成一维数组
        $arr = $this->baseRepository->getFlatten($arr);

        return $arr;
    }
}
