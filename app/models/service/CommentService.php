<?php
namespace service;

use EndyJasmi\Cuid;
use service\UserService;
use service\PostService;
use service\GroupUserService;
use model\CommentModel;
use model\CommentContentModel;

/**
 * CommentService
 * @author ROC <i@rocs.me>
 */
class CommentService extends Service
{
    /**
     * 发布评论
     *
     * @param integer $postId
     * @param integer $userId
     * @param array $contents
     * @return integer
     */
    public function add($postId, $userId, array $contents)
    {
        $comment = $this->commentModel;
        $comment->post_id = $postId;
        $comment->user_id = $userId;

        if ($comment->save()) {
            $sort = 100;
            foreach ($contents as $content) {
                // 媒体资源ID转换
                if (in_array($content['type'], [3, 4, 5])) {
                    $this->attachmentModel->load([
                        'id' => $content['content'],
                        'user_id' => $userId,
                        'is_deleted' => 0,
                    ]);
                    $attachment = $this->attachmentModel->getData();
                    if (!empty($attachment)) {
                        $content['content'] = $attachment['content'];
                    } else {
                        continue;
                    }
                }

                $commentContent = new CommentContentModel();
                $commentContent->comment_id = $comment->getPrimaryKey()['id'];
                $commentContent->user_id = $userId;
                $commentContent->content = $content['content'];
                $commentContent->type = $content['type'];
                $commentContent->sort = $sort++;
                $commentContent->save();
            }
            // TODO 推送es

            // 修改POST评论数
            (new PostService())->updateCommentCount(1, $postId);

            return $comment->getPrimaryKey()['id'];
        }

        $this->_error = '新增失败';
        return 0;
    }

    /**
     * 发布权限检测
     *
     * @param integer $userId
     * @return boolean
     */
    public function postAccessCheck($userId)
    {
        $userService = new UserService();
        $user = $userService->getUserModel(['id' => $userId]);
        if (!$user) {
            $this->_error = $userService->_error;
            return false;
        }

        // 检测用户是否被禁封
        if ($userService->isBanned($user)) {
            $this->_error = '您当前的角色没有发布权限';
            return false;
        }

        return true;
    }

    /**
     * 管理评论权限检测
     *
     * @param integer $commentId
     * @param integer $userId
     * @return boolean
     */
    public function manageAccessCheck($commentId, $userId)
    {
        // 检测评论是否存在
        $this->commentModel->load([
            'id' => $commentId,
            'is_deleted' => 0,
        ]);

        $comment = $this->commentModel->getData();
        if (!empty($comment)) {
            // 发布主check
            if ($comment['user_id'] == $userId) {
                return true;
            }

            // 超级管理员身份CHECK
            if ((new UserService())->isSuperManager($userId)) {
                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * 检测用户是否存在评论对话中
     *
     * @param integer $id
     * @param integer $userId
     * @return boolean
     */
    public function hasUserInTalk($id, $userId)
    {
        return $this->commentReplyModel->has([
            'comment_id' => $id,
            'user_id' => $userId,
            'is_deleted' => 0,
        ]);
    }

    /**
     * 获取评论详情
     *
     * @param integer $id
     * @return array
     */
    public function detail($id)
    {
        $this->commentModel->load([
            'id' => $id,
            'is_deleted' => 0,
        ]);

        return $this->commentModel->getData();
    }

    /**
     * 获取评论列表
     *
     * @param integer $postId
     * @param integer $offset
     * @param integer $limit
     * @return array
     */
    public function list($postId, $offset = 0, $limit = 20)
    {
        // TODO 后期数据量上来后需要切ES

        // 获取评论主表数据
        $comments = (array) $this->commentModel->dump([
            'post_id' => $postId,
            'is_deleted' => 0,
            'ORDER' => ['id' => 'DESC'],
            'LIMIT' => [$offset, $limit],
        ]);

        $commentIds = array_column($comments, 'id');
        $userIds = array_column($comments, 'user_id');
        
        // 获取评论详情数据
        $contents = (array) $this->commentContentModel->dump([
            'comment_id' => $commentIds,
            'is_deleted' => 0,
        ]);

        // 获取用户详情数据
        $users = (array) $this->userModel->dump([
            'id' => $userIds
        ], [
            'id',
            'username',
            'avatar',
        ]);
        
        // 数据整合
        foreach ($comments as &$comment) {
            $comment['user'] = [];
            foreach ($contents as $content) {
                if ($content['comment_id'] == $comment['id']) {
                    if (!isset($comment['contents'])) {
                        $comment['contents'] = [];
                    }

                    array_push($comment['contents'], $content);
                }
            }
            foreach ($users as $user) {
                if ($user['id'] == $comment['user_id']) {
                    $comment['user'] = $user;
                }
            }
        }

        return [
            'rows' => $comments,
            'total' => $this->commentModel->count([
                'post_id' => $postId,
                'is_deleted' => 0,
            ]),
        ];
    }

    /**
     * 删除评论
     *
     * @param integer $commentId
     * @return boolean
     */
    public function delete($commentId)
    {
        $this->commentModel->load([
            'id' => $commentId,
            'is_deleted' => 0,
        ]);

        $comment = $this->commentModel->getData();

        if (!empty($comment) && $this->commentModel->delete()) {
            // 重新计算POST的评论数
            $service = new PostService();
            $service->reCalcCommentCount($comment['post_id']);

            // 重新推送数据至ES
            $service->pushToElasticSearch($comment['post_id']);
            
            return true;
        }

        return false;
    }
}
