<?php
namespace api;

use BaseController;
use service\UserService;

class AdminController extends BaseController
{
    protected static $_checkSign = false;

    /**
     * 获取系统概览
     *
     * @return array
     */
    protected function getSummary()
    {
        $userService = new UserService();
        if (!$userService->isSuperManager(app()->get('user')['id'])) {
            return [
                'code' => 500,
                'msg' => '无权访问'
            ];
        }

        return [
            'code' => 0,
            'msg' => 'success',
            'data' => [
                // 获取所有用户数
                'user_count' => $userService->userModel->count([
                    'is_deleted' => 0,
                ]),
                // 获取所有冒泡数
                'post_count' => $userService->postModel->count([
                    'type' => 1, // 冒泡
                    'is_deleted' => 0,
                ]),
                // 获取素有文章数
                'article_count' => $userService->postModel->count([
                    'type' => 2, // 文章
                    'is_deleted' => 0,
                ]),
                // 获取图片/视频素材数
                'material_count' => $userService->postContentModel->count([
                    'type' => [3, 4], // 图片、视频
                    'is_deleted' => 0,
                ]),
            ],
        ];
    }

    /**
     * 获取用户列表
     *
     * @return array
     */
    protected function getUsers()
    {
        $query = app()->request()->query;
        $this->checkParams($query, ['page', 'page_size']);
        $params = $query->getData();

        $page = $params['page'] > 0 ? intval($params['page']) : 1;
        $pageSize = $params['page_size'] > 0 && $params['page_size'] <= self::MAX_PAGESIZE ? intval($params['page_size']) : 20;

        $userService = new UserService();
        if (!$userService->isSuperManager(app()->get('user')['id'])) {
            return [
                'code' => 500,
                'msg' => '无权访问'
            ];
        }

        $users = $userService->list([
            'is_deleted' => 0,
        ], ($page - 1) * $pageSize, $pageSize);

        return [
            'code' => 0,
            'msg' => 'success',
            'data' => $users,
        ];
    }

    /**
     * 禁言用户
     *
     * @return array
     */
    protected function banUser()
    {
        $data = app()->request()->data;
        $this->checkParams($data, ['user_id']);
        $params = $data->getData();

        $userService = new UserService();
        if (!$userService->isSuperManager(app()->get('user')['id'])) {
            return [
                'code' => 500,
                'msg' => '无权访问'
            ];
        }

        $userModel = $userService->getUserModel([
            'id' => $params['user_id'],
            'is_deleted' => 0,
        ]);

        if (!$userModel) {
            return [
                'code' => 500,
                'msg' => $userService->_error,
            ];
        }

        $userModel->is_banned = $userService->isBanned($userModel) ? 0 : 1;
        if ($userModel->update(['is_banned'])) {
            return [
                'code' => 0,
                'msg' => 'success',
            ];
        }

        return [
            'code' => 500,
            'msg' => '操作失败，请重试'
        ];
    }
}
