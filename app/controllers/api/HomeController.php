<?php
namespace api;

use OSS\OssClient;
use OSS\Core\OssException;
use OSS\Core\OssUtil;
use BaseController;
use service\UtilService;
use service\UserService;

/**
 * HomeController
 */
class HomeController extends BaseController
{
    protected static $_checkSign = false;

    // 分片上传最大时间
    const CHUNK_UPLOAD_MAX_TIME = 86400;
    /**
     * This is a demo method.
     *
     * @method index
     * @return array
     */
    protected function index()
    {
        // Add Log
        app()->log()->info('IndexLog', [
            'data' => 'index',
        ]);

        return [
            'code' => 0,
            'msg'  => 'success',
            'data' => 'rocboss api version '.static::ROCBOSS_VERSION.'; framework version: '.\Batio::VERSION,
        ];
    }

    /**
     * DEMO JOB
     *
     * @return array
     */
    protected function demoJob()
    {
        return [
            'time' => time(),
        ];
    }

    /**
     * 图片上传
     *
     * @method uploadImg
     * @return array
     */
    protected function uploadImg()
    {
        $userId = app()->get('uid');

        $userService = new UserService();
        $user = $userService->getUserModel(['id' => $userId]);
        if (!$user) {
            return [
                'code' => 500,
                'msg'  => $userService->_error,
            ];
        }

        // 检测用户是否被禁封
        if ($userService->isBanned($user)) {
            return [
                'code' => 500,
                'msg'  => '您当前的角色已被限制上传资源',
            ];
        }

        // 允许上传的文件扩展
        $allowExts = ['jpg', 'png', 'jpeg', 'gif'];

        $files = app()->request()->files;

        if (!empty($files['file'])) {
            $name = $files['file']['name'];
            $fileType = $files['file']['type'];
            $tmpName = $files['file']['tmp_name'];
            $size = $files['file']['size'];
            $error = $files['file']['error'];

            $imgInfo = getimagesize($tmpName);
            
            // 上传成功
            if ($error === 0) {
                $ext = extend($name);
                if (!in_array($ext, $allowExts)) {
                    return [
                        'code' => 500,
                        'msg'  => '上传文件类型不合法',
                    ];
                }

                if ($size > 5242880) {
                    return [
                        'code' => 500,
                        'msg'  => '图片大小不能超过5MB',
                    ];
                }

                $hash = md5($tmpName.'.'.$userId.'.'.time());
                // BOS存储目录
                $dir = chunk_split(substr($hash, 8, 8), 2, '/');
                // 存储目录
                $remoteUrl = 'imgs/'. $dir . encrypt(time(), \Auth::APP_DEFAULT_KEY) . '-' . substr($hash, 12, 4) . '.' .$ext;

                // 从文件中直接上传至阿里云
                try {
                    $ossClient = new OssClient(env('ALIYUN_ACCESS_KEY_ID'), env('ALIYUN_ACCESS_KEY_SECRET'), env('ALIYUN_OSS_ENDPOINT'));

                    // 上传OSS
                    $ossClient->uploadFile(env('ALIYUN_OSS_BUCKET'), $remoteUrl, $tmpName);
                    $savePath = 'https://'.env('ALIYUN_OSS_BUCKET_DOMAIN').'/'.$remoteUrl;
                } catch (OssException $e) {
                    app()->log()->error('Upload img fail.', ['error' => $e->getMessage()]);

                    return [
                        'code' => 500,
                        'msg'  => $e->getMessage(),
                    ];
                }
                // 入库
                $model = new \model\AttachmentModel();
                $model->user_id = $userId;
                $model->content = $savePath;
                $model->type = 1;
                $model->file_size = $size;
                $model->img_width = $imgInfo[0];
                $model->img_height = $imgInfo[1];
                if ($model->save() === true) {
                    return [
                        'code' => 0,
                        'msg'  => 'success',
                        'data' => [
                            'img_id' => $model->getPrimaryKey()['id'],
                            'img_url' => $savePath,
                        ],
                    ];
                }
            }
        }

        return [
            'code' => 500,
            'msg'  => '上传失败',
        ];
    }

    /**
     * 视频上传（分块上传）
     *
     * @method uploadVideo
     * @return array
     */
    protected function uploadVideo()
    {
        $userId = app()->get('uid');

        $userService = new UserService();
        $user = $userService->getUserModel(['id' => $userId]);
        if (!$user) {
            return [
                'code' => 500,
                'msg'  => $userService->_error,
            ];
        }

        // 允许上传的文件扩展
        $allowExts = ['mp4'];

        // 获取分片相关数据
        $data = app()->request()->data;
        $this->checkParams($data, ['chunkNumber', 'totalChunks', 'chunkSize', 'totalSize', 'identifier']);
        $params = $data->getData();

        // 获取分片文件
        $files = app()->request()->files;

        if (!empty($files['file'])) {
            $name = $files['file']['name'];
            $fileType = $files['file']['type'];
            $tmpName = $files['file']['tmp_name'];
            $size = $files['file']['size'];
            $error = $files['file']['error'];

            $imgInfo = getimagesize($tmpName);
            
            // 上传成功
            if ($error === 0) {
                $ext = extend($name);
                if (!in_array($ext, $allowExts)) {
                    return [
                        'code' => 500,
                        'msg'  => '上传文件类型不合法',
                    ];
                }

                if ($size > 5242880) {
                    return [
                        'code' => 500,
                        'msg'  => '单片大小不能超过5MB',
                    ];
                }

                // 从文件中直接上传至阿里云
                try {
                    $ossClient = new OssClient(env('ALIYUN_ACCESS_KEY_ID'), env('ALIYUN_ACCESS_KEY_SECRET'), env('ALIYUN_OSS_ENDPOINT'));

                    // 首个分片时创建OSS分片ID
                    if ($params['chunkNumber'] == 1) {
                        $hash = md5($tmpName.'.'.$userId.'.'.time());
                        // BOS存储目录
                        $dir = chunk_split(substr($hash, 8, 8), 2, '/');
                        // 存储目录
                        $remoteUrl = 'videos/'. $dir . encrypt(time(), \Auth::APP_DEFAULT_KEY) . '-' . substr($hash, 12, 4) . '.' .$ext;
        
                        // 返回 uploadId，分片上传事件的唯一标识
                        $uploadId = $ossClient->initiateMultipartUpload(env('ALIYUN_OSS_BUCKET'), $remoteUrl);
                        
                        // 设置 uploadId 与客户端 identifier 的映射
                        app()->redis()->setex('video-uploads:'.$userId.':'.$params['identifier'], self::CHUNK_UPLOAD_MAX_TIME, json_encode([
                            'uploadId' => $uploadId,
                            'remoteUrl' => $remoteUrl,
                        ]));
                    } else {
                        $uploadConfig = app()->redis()->get('video-uploads:'.$userId.':'.$params['identifier']);
                        if (empty($uploadConfig)) {
                            return [
                                'code' => 500,
                                'msg'  => '上传会话非法，请重试',
                            ];
                        }
                        $uploadConfig = json_decode($uploadConfig, true);
                        $uploadId = $uploadConfig['uploadId'];
                        $remoteUrl = $uploadConfig['remoteUrl'];
                    }

                    try {
                        // 上传分片。
                        $eTag = $ossClient->uploadPart(env('ALIYUN_OSS_BUCKET'), $remoteUrl, $uploadId, [
                            $ossClient::OSS_FILE_UPLOAD => $tmpName,
                            $ossClient::OSS_PART_NUM => $params['chunkNumber'],
                            $ossClient::OSS_SEEK_TO => 0,
                            $ossClient::OSS_LENGTH => $params['currentChunkSize'],
                            $ossClient::OSS_CHECK_MD5 => false,
                        ]);

                        // 推送至Redis
                        app()->redis()->lpush('video-uploads:'.$userId.':'.$params['identifier'].'_etags', json_encode([
                            'PartNumber' => $params['chunkNumber'],
                            'ETag' => $eTag,
                        ]));
                        // 超时设置
                        app()->redis()->expire('video-uploads:'.$userId.':'.$params['identifier'].'_etags', self::CHUNK_UPLOAD_MAX_TIME);
                    } catch (OssException $e) {
                        app()->log()->error('Upload video part fail.', ['error' => $e->getMessage()]);

                        return [
                            'code' => 500,
                            'msg'  => $e->getMessage(),
                        ];
                    }

                    $savePath = 'https://'.env('ALIYUN_OSS_BUCKET_DOMAIN').'/'.$remoteUrl;

                    if ($params['chunkNumber'] == $params['totalChunks']) {
                        // 最后一个分片时，完成分片数据上传
                        try {
                            $etags = app()->redis()->lrange('video-uploads:'.$userId.':'.$params['identifier'].'_etags', 0, -1);
                            if (!empty($etags)) {
                                foreach ($etags as &$etag) {
                                    $etag = json_decode($etag, true);
                                }
                            }
                            usort($etags, function ($a, $b) {
                                return $a['PartNumber'] > $b['PartNumber'] ? 1 : -1;
                            });

                            $ossClient->completeMultipartUpload(env('ALIYUN_OSS_BUCKET'), $remoteUrl, $uploadId, $etags);

                            // 清空Redis
                            app()->redis()->del('video-uploads:'.$userId.':'.$params['identifier']);
                            app()->redis()->del('video-uploads:'.$userId.':'.$params['identifier'].'_etags');

                            // 入库
                            $model = new \model\AttachmentModel();
                            $model->user_id = $userId;
                            $model->content = $savePath;
                            $model->type = 2; // 视频
                            $model->file_size = $params['totalSize'];
                            $model->img_width = 0;
                            $model->img_height = 0;
                            if ($model->save() === true) {
                                return [
                                    'code' => 0,
                                    'msg'  => 'success',
                                    'data' => [
                                        'video_id' => $model->getPrimaryKey()['id'],
                                        'video_url' => $savePath,
                                    ],
                                ];
                            }

                            return [
                                'code' => 500,
                                'msg'  => '请重新上传',
                            ];
                        } catch (OssException $e) {
                            app()->log()->error('finish video part upload fail.', ['error' => $e->getMessage()]);

                            return [
                                'code' => 500,
                                'msg'  => $e->getMessage(),
                            ];
                        }
                    }

                    return [
                        'code' => 0,
                        'msg'  => 'Upload block success.',
                    ];
                } catch (OssException $e) {
                    app()->log()->error('Upload video fail.', ['error' => $e->getMessage()]);

                    return [
                        'code' => 500,
                        'msg'  => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'code' => 500,
            'msg'  => '上传失败',
        ];
    }

    /**
     * 头像上传
     *
     * @method uploadAvatar
     * @return array
     */
    protected function uploadAvatar()
    {
        $userId = app()->get('uid');

        // 允许上传的文件扩展
        $allowExts = ['jpg', 'png', 'jpeg', 'gif'];

        $files = app()->request()->files;

        if (!empty($files['file'])) {
            $name = $files['file']['name'];
            $fileType = $files['file']['type'];
            $tmpName = $files['file']['tmp_name'];
            $size = $files['file']['size'];
            $error = $files['file']['error'];

            $imgInfo = getimagesize($tmpName);
            
            // 上传成功
            if ($error === 0) {
                $ext = extend($name);
                if (!in_array($ext, $allowExts)) {
                    return [
                        'code' => 500,
                        'msg'  => '上传文件类型不合法',
                    ];
                }

                if ($size > 5242880) {
                    return [
                        'code' => 500,
                        'msg'  => '图片大小不能超过5MB',
                    ];
                }

                $hash = md5($tmpName.'.'.$userId.'.'.time());
                // BOS存储目录
                $dir = chunk_split(substr($hash, 8, 8), 2, '/');
                // 存储目录
                $remoteUrl = 'avatars/'.$dir.'/'.uniqid().'.png';
                $savePath = 'https://'.env('ALIYUN_OSS_BUCKET_DOMAIN').'/'.$remoteUrl;

                // 从文件中直接上传至阿里云
                $service = new UserService();
                if ($service->updateAvatar($userId, $remoteUrl, $tmpName)) {
                    // 入库
                    $model = new \model\AttachmentModel();
                    $model->user_id = $userId;
                    $model->content = $savePath;
                    $model->type = 1;
                    $model->file_size = $size;
                    $model->img_width = $imgInfo[0];
                    $model->img_height = $imgInfo[1];
                    if ($model->save() === true) {
                        return [
                            'code' => 0,
                            'msg'  => 'success',
                            'data' => [
                                'img_id' => $model->getPrimaryKey()['id'],
                                'img_url' => $savePath,
                            ],
                        ];
                    }
                }

                return [
                    'code' => 500,
                    'msg'  => $this->_error,
                ];
            }
        }

        return [
            'code' => 500,
            'msg'  => '上传失败',
        ];
    }

    /**
     * 检测视频分片是否已经上传
     *
     * @return array
     */
    protected function uploadVideoCheck()
    {
        $userId = app()->get('uid');

        // 允许上传的文件扩展
        $allowExts = ['mp4'];

        // 获取分片相关数据
        $data = app()->request()->query;
        $this->checkParams($data, ['chunkNumber', 'totalChunks', 'chunkSize', 'totalSize', 'identifier']);
        $params = $data->getData();

        $etags = app()->redis()->llen('video-uploads:'.$userId.':'.$params['identifier'].'_etags');

        if ($params['chunkNumber'] > $etags) {
            app()->halt([
                'code' => 206,
                'msg'  => '未找到分片'.$params['chunkNumber'],
            ], 206);
            app()->stop();
        }

        return [
            'code' => 0,
            'msg'  => 'success',
        ];
    }

    /**
     * 获取图片验证码
     *
     * @return array
     */
    protected function getImageCaptcha()
    {
        return [
            'code' => 0,
            'data' => (new UtilService())->getImageCaptcha(),
        ];
    }
}
