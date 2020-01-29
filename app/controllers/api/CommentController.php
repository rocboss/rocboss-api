<?php
namespace api;

use BaseController;
use model\CommentModel;
use service\MessageService;
use service\PostService;
use service\UserService;
use service\GroupService;
use service\GroupUserService;
use service\UtilService;
use service\CommentService;
use service\CommentReplyService;

/**
 * CommentController class
 * @author ROC <i@rocs.me>
 */
class CommentController extends BaseController
{
    protected static $_checkSign = false;

    // 默认回复获取数量
    const DEFAULT_REPLY_NUM = 3;
    const MAX_REPLY_COUNT = 100;

    /**
     * 发表评论
     *
     * @return array
     */
    protected function push()
    {
        $data = app()->request()->data;
        $this->checkParams($data, ['contents', 'post_id']);
        $params = $data->getData();

        // 类型检测
        $postId = intval($params['post_id']);
        $userId = app()->get('uid');
        $contents = is_array($params['contents']) ? $params['contents'] : [];

        $service = new CommentService();
        // 发布权限检测
        if (!$service->postAccessCheck($userId)) {
            return [
                'code' => 500,
                'msg' => $service->_error,
            ];
        }

        // TODO 发布频次限制

        // 发布回复
        $commentId = $service->add($postId, $userId, $contents);
        if ($commentId > 0) {
            // 重新推送ES
            (new PostService())->pushToElasticSearch($postId);

            // 新增用户消息提醒
            (new MessageService())->addPostPushMessage($userId, $postId, $commentId);

            // TODO 消息推送

            return [
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'comment_id' => $commentId,
                ],
            ];
        }

        return [
            'code' => 500,
            'msg' => $service->_error,
        ];
    }

    /**
     * 获取评论列表
     *
     * @param integer $postId
     * @return array
     */
    protected function list($postId)
    {
        $query = app()->request()->query;
        $this->checkParams($query, ['page', 'page_size']);
        $params = $query->getData();

        $page = $params['page'] > 0 ? intval($params['page']) : 1;
        $pageSize = $params['page_size'] > 0 && $params['page_size'] <= self::MAX_PAGESIZE ? intval($params['page_size']) : 20;

        $service = new CommentService();
        $data = $service->list($postId, ($page - 1) * $pageSize, $pageSize);

        // 获取评论ID
        $commentIds = array_column($data['rows'], 'id');
        // 获取评论下回复（默认取3条）
        $replies = (new CommentReplyService())->list($commentIds, 0, self::DEFAULT_REPLY_NUM);

        $userId = app()->get('uid');
        $canManage = false;
        if ($userId > 0) {
            // 超级管理员身份CHECK
            if ((new UserService())->isSuperManager($userId)) {
                $canManage = true;
            }
        }

        // 数据处理
        foreach ($data['rows'] as &$row) {
            // 是否可以管理
            $row['canManage'] = $canManage;

            $row['created_at_timestamp'] = strtotime($row['created_at']);
            $row['updated_at_timestamp'] = strtotime($row['updated_at']);

            $row['created_at'] = formatTime($row['created_at_timestamp']);
            $row['updated_at'] = formatTime($row['updated_at_timestamp']);

            $row['replies'] = isset($replies[$row['id']]) ? $replies[$row['id']] : [
                'rows' => [],
                'total' => 0,
                'offset' => 0,
                'limit' => self::DEFAULT_REPLY_NUM,
            ];

            foreach ($row['replies']['rows'] as &$reply) {
                $reply['canManage'] = $canManage;
                // 时间格式化处理
                $reply['created_at_timestamp'] = strtotime($reply['created_at']);
                $reply['updated_at_timestamp'] = strtotime($reply['updated_at']);

                $reply['created_at'] = formatTime($reply['created_at_timestamp']);
                $reply['updated_at'] = formatTime($reply['updated_at_timestamp']);

                // 管理权限判断（用户自己发的帖子）
                if (!$reply['canManage'] && $reply['user_id'] == $userId) {
                    $reply['canManage'] = true;
                }
            }
        }

        return [
            'code' => 0,
            'msg' => 'success',
            'data' => $data,
        ];
    }

    /**
     * 获取评论回复列表（业务逻辑限制，评论下最大回复100）
     *
     * @param integer $commentId
     * @return array
     */
    protected function replyList($commentId)
    {
        $query = app()->request()->query;
        $this->checkParams($query, ['page', 'page_size']);
        $params = $query->getData();

        $page = $params['page'] > 0 ? intval($params['page']) : 1;
        $pageSize = $params['page_size'] > 0 && $params['page_size'] <= self::MAX_REPLY_COUNT ? intval($params['page_size']) : self::MAX_REPLY_COUNT;

        $replies = (new CommentReplyService())->list($commentId, ($page - 1) * $pageSize, $pageSize);

        $userId = app()->get('uid');
        $canManage = false;
        if ($userId > 0) {
            // 超级管理员身份CHECK
            if ((new UserService())->isSuperManager($userId)) {
                $canManage = true;
            }
        }

        if (isset($replies[$commentId])) {
            foreach ($replies[$commentId]['rows'] as &$reply) {
                $reply['canManage'] = $canManage;

                // 时间格式化处理
                $reply['created_at_timestamp'] = strtotime($reply['created_at']);
                $reply['updated_at_timestamp'] = strtotime($reply['updated_at']);

                $reply['created_at'] = formatTime($reply['created_at_timestamp']);
                $reply['updated_at'] = formatTime($reply['updated_at_timestamp']);

                // 管理权限判断（用户自己发的帖子）
                if (!$reply['canManage'] && $reply['user_id'] == $userId) {
                    $reply['canManage'] = true;
                }
            }
        }

        return [
            'code' => 0,
            'msg' => 'success',
            'data' => $replies[$commentId],
        ];
    }

    /**
     * 发布评论回复
     *
     * @return array
     */
    protected function pushReply()
    {
        $data = app()->request()->data;
        $this->checkParams($data, ['comment_id', 'content', 'at_user_id']);
        $params = $data->getData();

        $userId = app()->get('uid');
        $commentId = intval($params['comment_id']);
        $atUserId = intval($params['at_user_id']);

        if (empty(trim($params['content']))) {
            return [
                'code' => 500,
                'msg' => '回复内容不可为空',
            ];
        }

        $commentService = new CommentService();
        $comment = $commentService->detail($commentId);

        if (empty($comment)) {
            return [
                'code' => 500,
                'msg' => '评论不存在',
            ];
        }

        $service = new CommentReplyService();

        // 发布数量限制
        if ($service->count($comment['id']) >= self::MAX_REPLY_COUNT) {
            return [
                'code' => 500,
                'msg' => '该评论已达最大回复数量',
            ];
        }

        // 发布权限检测
        if (!$commentService->postAccessCheck($userId)) {
            return [
                'code' => 500,
                'msg' => $service->_error,
            ];
        }

        // 检查被@用户是否在本次对话中
        if (!$commentService->hasUserInTalk($comment['id'], $atUserId) || $atUserId == $userId) {
            // 置零被@用户ID
            $atUserId = 0;
        }

        $replyId = $service->add($commentId, $userId, $params['content'], $atUserId);
        if ($replyId > 0) {
            // 重新推送ES
            (new PostService())->pushToElasticSearch($comment['post_id']);

            if ($atUserId > 0) {
                // 通知被@用户
                (new MessageService())->addCommentReply($userId, $atUserId, $comment['post_id'], $commentId, $replyId, $params['content']);
            }

            // 用户提醒（评论主）
            if ($comment['user_id'] != $userId && $comment['user_id'] != $atUserId) {
                (new MessageService())->addCommentReply($userId, $comment['user_id'], $comment['post_id'], $commentId, $replyId, $params['content']);
            }

            // 用户提醒（POST主）
            if (!empty(app()->get('post.access.check.data'))
                && app()->get('post.access.check.data')['user_id'] != $userId
                && app()->get('post.access.check.data')['user_id'] != $comment['user_id']
                && app()->get('post.access.check.data')['user_id'] != $atUserId) {
                (new MessageService())->addCommentReply($userId, app()->get('post.access.check.data')['user_id'], $comment['post_id'], $commentId, $replyId, $params['content']);
            }

            // TODO 消息推送

            return [
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'comment_reply_id' => $replyId,
                ],
            ];
        }

        return [
            'code' => 500,
            'msg' => $service->_error,
        ];
    }

    /**
     * 删除评论
     *
     * @param integer $id
     * @return array
     */
    protected function delete($id)
    {
        $userId = app()->get('uid');

        $service = new CommentService();
        if ($service->manageAccessCheck($id, $userId)) {
            // 删除
            $service->delete($id);

            return [
                'code' => 0,
                'msg' => 'success',
            ];
        }

        return [
            'code' => 500,
            'msg' => $service->_error,
        ];
    }

    /**
     * 删除回复
     *
     * @param integer $id
     * @return array
     */
    protected function deleteReply($id)
    {
        $userId = app()->get('uid');

        $service = new CommentReplyService();
        if ($service->manageAccessCheck($id, $userId)) {
            // 删除
            $service->delete($id);

            return [
                'code' => 0,
                'msg' => 'success',
            ];
        }

        return [
            'code' => 500,
            'msg' => $service->_error,
        ];
    }
}
