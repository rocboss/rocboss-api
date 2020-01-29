<?php
namespace job;

use BaseController;

/**
 * QueueController
 * @author ROC <i@rocs.me>
 */
class QueueController extends BaseController
{
    protected static $_checkSign = false;

    /**
     * 任务推送·全量推送ES
     *
     * @return array
     */
    protected function pushFullIndexTask()
    {
        if (app()->request()->query->token != env('SIGN_TOKEN')) {
            return [
                'code' => 500,
                'msg' => 'Invalid Token.'
            ];
        }

        // 全量推送ES
        app()->redis()->lpush(env('APP_NAME').':QueueJobs', json_encode([
            'controller' => 'job\QueueController',
            'action' => 'pushFullIndex',
            'params' => [],
        ]));

        return [
            'code' => 0,
            'msg' => 'success'
        ];
    }

    /**
     * 全量推送ES
     *
     * @return array
     */
    protected function pushFullIndex()
    {
        $startTime = time();

        (new \service\PostService())->pushFullPostsToEs();

        return [
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'spent' => time() - $startTime,
            ]
        ];
    }
}
