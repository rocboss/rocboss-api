<?php
namespace api;

use BaseController;
use service\MessageService;

/**
 * MessageController
 * @author ROC <i@rocs.me>
 */
class MessageController extends BaseController
{
    protected static $_checkSign = false;

    /**
     * 获取消息列表
     *
     * @return array
     */
    protected function list()
    {
        $query = app()->request()->query;
        $this->checkParams($query, ['page', 'page_size']);
        $params = $query->getData();
        
        $page = $params['page'] > 0 ? intval($params['page']) : 1;
        $pageSize = $params['page_size'] > 0 && $params['page_size'] <= self::MAX_PAGESIZE ? intval($params['page_size']) : 20;
        $userId = app()->get('uid');

        $service = new MessageService();
        $messages = $service->list([
            'receiver_user_id' => $userId,
        ], ($page - 1) * $pageSize, $pageSize);

        return [
            'code' => 0,
            'msg' => 'success',
            'data' => $messages,
        ];
    }

    /**
     * 标记消息为已读
     *
     * @return array
     */
    protected function remarkRead()
    {
        $data = app()->request()->data;
        $this->checkParams($data, ['message_id']);
        $params = $data->getData();

        $userId = app()->get('uid');
        $messageId = intval($params['message_id']);
        
        $service = new MessageService();
        $service->remarkRead($userId, $messageId);

        return [
            'code' => 0,
            'msg' => 'success',
        ];
    }

    /**
     * 获取未读消息数量
     *
     * @return array
     */
    protected function unreadCount()
    {
        $userId = app()->get('uid');

        $service = new MessageService();
        $count = $service->unreadCount($userId);

        return [
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'unread_count' => $count,
            ],
        ];
    }
}
