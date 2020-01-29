<?php
namespace service;

use model\CommentReplyModel;

/**
 * CommentReplyService
 * @author ROC <i@rocs.me>
 */
class CommentReplyService extends Service
{
    /**
     * 获取评论下回复列表
     *
     * @param Array/Integer $commentIds
     * @param integer $offset
     * @param integer $limit
     * @return Array
     */
    public function list($commentIds, $offset = 0, $limit = 3)
    {
        // 单ID场景
        if (is_numeric($commentIds)) {
            $commentIds = [$commentIds];
        }

        $data = [];
        $userIds = [];
        foreach ($commentIds as &$commentId) {
            $replies = $this->commentReplyModel->dump([
                'comment_id' => $commentId,
                'is_deleted' => 0,
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => [$offset, $limit],
            ]);

            // 用户ID集合
            $userIds = array_merge($userIds, array_column($replies, 'user_id'), array_column($replies, 'at_user_id'));

            $data[$commentId] = [
                'rows' => $replies,
                'total' => $this->commentReplyModel->count([
                    'comment_id' => $commentId,
                    'is_deleted' => 0,
                ]),
                'offset' => $offset,
                'limit' => $limit,
            ];
        }

        // 一次性获取所需所有用户数据
        $users = $this->userModel->dump([
            'id' => array_unique($userIds),
            'is_deleted' => 0,
        ], [
            'id',
            'username',
            'avatar',
        ]);

        // 数据深度加工（TODO 优化三层循环）
        foreach ($data as $commentId => &$replies) {
            foreach ($replies['rows'] as &$row) {
                // 默认值
                $row['user'] = $row['at_user'] = [
                    'id' => 0,
                    'username' => '',
                    'avatar' => '',
                ];
                foreach ($users as $user) {
                    // 发布者匹配
                    if ($user['id'] == $row['user_id']) {
                        $row['user'] = $user;
                    }
                    // @用户数据匹配
                    if ($user['id'] == $row['at_user_id']) {
                        $row['at_user'] = $user;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * 添加评论回复
     *
     * @param integer $commentId
     * @param integer $userId
     * @param integer $content
     * @param integer $atUserId
     * @return integer
     */
    public function add($commentId, $userId, $content, $atUserId = 0)
    {
        // 检测评论是否存在
        $this->commentModel->load([
            'id' => $commentId,
            'is_deleted' => 0,
        ]);

        $comment = $this->commentModel->getData();
        if (!empty($comment)) {
            $model = new CommentReplyModel();
            $model->comment_id = $commentId;
            $model->user_id = $userId;
            $model->at_user_id = $atUserId;
            $model->content = $content;

            if ($model->save()) {

                // 修改POST评论数
                (new PostService())->updateCommentCount(1, $comment['post_id']);

                return $model->getPrimaryKey()['id'];
            }
        }

        $this->_error = '新增失败';
        return 0;
    }

    /**
     * 获取评论下回复数量
     *
     * @param integer $commentId
     * @return integer
     */
    public function count($commentId)
    {
        return $this->commentReplyModel->count([
            'comment_id' => $commentId,
            'is_deleted' => 0,
        ]);
    }

    /**
     * 管理回复权限检测
     *
     * @param integer $replyId
     * @param integer $userId
     * @return boolean
     */
    public function manageAccessCheck($replyId, $userId)
    {
        // 检测回复是否存在
        $this->commentReplyModel->load([
            'id' => $replyId,
            'is_deleted' => 0,
        ]);

        $reply = $this->commentReplyModel->getData();
        if (!empty($reply)) {
            // 发布主check
            if ($reply['user_id'] == $userId) {
                return true;
            }

            // 超级管理员身份check
            if ((new UserService())->isSuperManager($userId)) {
                return true;
            }

            $this->_error = '无删除权限';
            return false;
        }

        return true;
    }

    /**
     * 删除回复
     *
     * @param integer $replyId
     * @return boolean
     */
    public function delete($replyId)
    {
        $this->commentReplyModel->load([
            'id' => $replyId,
            'is_deleted' => 0,
        ]);

        $reply = $this->commentReplyModel->getData();

        if ($this->commentReplyModel->delete()) {

            // 获取评论信息
            $this->commentModel->load([
                'id' => $reply['comment_id'],
                'is_deleted' => 0,
            ]);

            $comment = $this->commentModel->getData();
            if (!empty($comment)) {
                    
                // 重新计算POST的评论数
                $service = new PostService();
                $service->reCalcCommentCount($comment['post_id']);

                // 重新推送数据至ES
                $service->pushToElasticSearch($comment['post_id']);
            }

            return true;
        }

        return false;
    }
}
