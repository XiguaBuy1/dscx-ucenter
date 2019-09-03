<?php

namespace App\Kernel\Repositories\Cloud;

use App\Kernel\Repositories\Repository;
use App\Models\OssConfigure;
use App\Kernel\Repositories\Common\BaseRepository;
use Illuminate\Support\Facades\Log;
use OSS\OssClient;

class AliOssRepository extends Repository
{
    private $shop;
    private $baseRepository;

    public function __construct(
        BaseRepository $baseRepository
    )
    {
        $this->baseRepository = $baseRepository;
    }

    /**
     * 上传OSS图片
     *
     * @param array $data
     * @throws \OSS\Core\OssException
     */
    public function cloudUpload($data = [])
    {
        if ($data) {
            $ossClient = new OssClient($data['keyid'], $data['keysecret'], $data['endpoint'], $data['is_cname']);

            $list = [];
            if ($data['object']) {
                foreach ($data['object'] as $key => $val) {
                    if ($val) {
                        $list[$key] = $val;
                    }
                }
            }

            if ($list) {

                $list = $this->baseRepository->getExplode($list);

                foreach ($list as $key => $row) {
                    if ($row) {
                        $row = trim($row);
                        $file = storage_public($row);
                        $objects = $row;
                        if (is_file($file)) {
                            $ossClient->uploadFile($data['bucket'], $objects, $file);
                            // OSS文件上传成功后，移除本地文件
                            if ($ossClient->doesObjectExist($data['bucket'], $objects)) {
                                $this->baseRepository->dscUnlink($file);
                            } else {
                                Log::error('OSS FIAL: ' . $data['bucket'] . '=' . $objects);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 删除OSS图片
     *
     * @param array $data
     * @throws \OSS\Core\OssException
     */
    public function cloudDelete($data = [])
    {
        if ($data) {
            $ossClient = new OssClient($data['keyid'], $data['keysecret'], $data['endpoint'], $data['is_cname']);

            $list = [];
            if ($data['object']) {
                foreach ($data['object'] as $key => $val) {
                    if ($val) {
                        $list[$key] = $val;
                    }
                }
            }

            if ($list) {

                $list = $this->baseRepository->getExplode($list);

                $ossClient->deleteObjects($data['bucket'], $list);

                foreach ($list as $key => $row) {

                    $file = storage_public($row);

                    if ($row && is_file($file)) {
                        // OSS文件移除成功后，移除本地文件
                        if (!$ossClient->doesObjectExist($data['bucket'], $row)) {
                            $this->baseRepository->dscUnlink($file);
                        } else {
                            Log::error('OSS FIAL: ' . $data['bucket'] . '=' . $row);
                        }
                    }
                }
            }
        }
    }

    /**
     * 获取OSS指定信息
     *
     * @param $data
     * @return array
     * @throws \OSS\Core\OssException
     */
    public function cloudList($data)
    {
        $arr = [];
        if ($data) {
            $ossClient = new OssClient($data['keyid'], $data['keysecret'], $data['endpoint'], $data['is_cname']);

            $cloudList = [];
            if ($data['object']) {
                foreach ($data['object'] as $key => $val) {
                    if ($val) {
                        $cloudList[$key] = $val;
                    }
                }
            }

            $list = [];
            if ($cloudList) {

                $cloudList = $this->baseRepository->getExplode($cloudList);

                foreach ($cloudList as $key => $val) {
                    if ($val) {
                        $data = [
                            'prefix' => $val
                        ];

                        $list[] = $ossClient->listObjects($data['bucket'], $data);
                    }
                }
            }

            if ($list) {
                foreach ($list as $key => $row) {

                    //对象转数组
                    $row = $row && !is_array($row) ? collect($row)->toArray() : $row;

                    if ($row) {
                        foreach ($row as $idx => $val) {
                            if ($val && is_array($val)) {
                                foreach ($val as $k => $v) {

                                    //对象转数组
                                    $v = $v && !is_array($v) ? collect($v)->toArray() : $v;

                                    $v = array_values($v);
                                    $arr[$key][$idx][$k] = $v[0];
                                }
                            }
                        }
                    }
                }
            }

            //转成一维数组
            $arr = $this->baseRepository->getFlatten($arr);
        }

        return $arr;
    }

    /**
     * 获取OSS Bucket信息
     *
     * @return array|bool|\Illuminate\Cache\CacheManager|mixed
     * @throws \Exception
     */
    public function bucketInfo()
    {
        $res = cache('oss_bucket_info');
        $res = !is_null($res) ? $res : false;

        if ($res === false) {
            $res = OssConfigure::where('is_use', 1);
            $res = $res->first();
            $res = $res ? $res->toArray() : [];

            if ($res) {
                $http = $this->baseRepository->dscHttp();
                $regional = substr($res['regional'], 0, 2);

                if ($regional == 'us' || $regional == 'ap') {
                    $res['outside_site'] = $http . $res['bucket'] . ".oss-" . $res['regional'] . ".aliyuncs.com";
                    $res['inside_site'] = $http . $res['bucket'] . ".oss-" . $res['regional'] . "-internal.aliyuncs.com";
                } else {
                    $res['outside_site'] = $http . $res['bucket'] . ".oss-cn-" . $res['regional'] . ".aliyuncs.com";
                    $res['inside_site'] = $http . $res['bucket'] . ".oss-cn-" . $res['regional'] . "-internal.aliyuncs.com";
                }

                if (empty($res['endpoint'])) {
                    $res['endpoint'] = $res['outside_site'] . "/";

                    if ($regional == 'us' || $regional == 'ap') {
                        $res['outside_site'] = "oss-" . $res['regional'] . ".aliyuncs.com";
                    } else {
                        $res['outside_site'] = "oss-cn-" . $res['regional'] . ".aliyuncs.com";
                    }
                } else {
                    $res['endpoint'] = rtrim($res['endpoint'], '/') . "/";
                }

                cache()->forever('oss_bucket_info', $res);
            } else {
                $res['endpoint'] = '';
            }
        }

        return $res;
    }
}
