<?php
namespace service;

use model\MessageModel;

/**
 * MessageService
 * @author ROC <i@rocs.me>
 */
class MessageService extends service
{
    /**
     * 获取消息列表
     *
     * @param array $condition
     * @param integer $offset
     * @param integer $limit
     * @return array
     */
    public function list(array $condition, $offset = 0, $limit = 20)
    {
        $messages = $this->messageModel->dump(array_merge($condition, [
            'ORDER' => ['id' => 'DESC'],
            'LIMIT' => [$offset, $limit]
        ]));
        
        // 获取相关用户ID
        $sendUserIds = array_column($messages, 'sender_user_id');
        $receiverUserIds = array_column($messages, 'receiver_user_id');
        $userIds = array_merge($sendUserIds, $receiverUserIds);
        $userIds = array_unique($userIds);

        // 获取相关用户信息
        $users = $this->userModel->dump([
            'id' => $userIds,
            'is_deleted' => 0,
        ], [
            'id',
            'username',
            'avatar'
        ]);

        // 获取POST相关信息
        $postIds = array_column($messages, 'post_id');
        $posts = $this->postModel->dump([
            'id' => $postIds,
            'is_deleted' => 0,
        ], [
            'id',
            'alias_id',
            'type',
        ]);

        // 获取评论相关信息
        $commentIds = array_column($messages, 'comment_id');
        // 获取评论主表数据
        $comments = (array) $this->commentModel->dump([
            'id' => $commentIds,
            'is_deleted' => 0,
        ]);
        // 获取评论详情数据
        $commentContents = (array) $this->commentContentModel->dump([
            'comment_id' => $commentIds,
            'is_deleted' => 0,
        ]);

        // 评论数据整合
        foreach ($comments as &$comment) {
            foreach ($commentContents as $content) {
                if ($content['comment_id'] == $comment['id']) {
                    if (!isset($comment['contents'])) {
                        $comment['contents'] = [];
                    }
                    array_push($comment['contents'], $content);
                }
            }
        }

        // 获取群组相关信息
        $groupIds = array_column($messages, 'group_id');
        $groups = $this->groupModel->dump([
            'id' => $groupIds,
            'is_deleted' => 0
        ], [
            'id',
            'name',
        ]);

        // 获取私信相关信息
        $whisperIds = array_column($messages, 'whisper_id');
        $whispers = $this->userWhisperModel->dump([
            'id' => $whisperIds,
            'is_deleted' => 0
        ], [
            'id',
            'sender_user_id',
            'receiver_user_id',
            'content'
        ]);


        // 数据处理
        foreach ($messages as &$message) {
            // 时间格式化
            $message['created_at_timestamp'] = strtotime($message['created_at']);
            $message['updated_at_timestamp'] = strtotime($message['updated_at']);
            $message['created_at'] = formatTime($message['created_at_timestamp']);
            $message['updated_at'] = formatTime($message['updated_at_timestamp']);

            // init默认信息
            $message['sender_user'] = $message['receiver_user'] = [
                'id' => 0,
                'username' => 'system',
                'avatar' => ''
            ];
            foreach ($users as $user) {
                if ($user['id'] == $message['sender_user_id']) {
                    $message['sender_user'] = $user;
                }
                if ($user['id'] == $message['receiver_user_id']) {
                    $message['receiver_user'] = $user;
                }
            }

            $message['post'] = [
                'id' => 0,
                'alias_id' => '',
                'type' => 1,
            ];
            if ($message['post_id'] > 0) {
                foreach ($posts as $post) {
                    if ($post['id'] == $message['post_id']) {
                        $message['post'] = $post;
                    }
                }
            }

            $message['comment'] = [
                'id' => 0,
                'contents' => []
            ];
            if ($message['comment_id'] > 0) {
                foreach ($comments as $cmt) {
                    if ($cmt['id'] == $message['comment_id']) {
                        $message['comment'] = $cmt;
                    }
                }
            }
            // 群组数据整合到message中
            $messsge['group'] = [
                'id' => 0,
                'name' => '',
                'type' => 1,
                'user_id' => 0

            ];
            if ($message['group_id'] > 0) {
                foreach ($groups as $group) {
                    if ($group['id'] === $message['group_id']) {
                        $messsge['group'] = $group;
                    }
                }
            }
            // 私信信息整合
            $message['whisper'] = [
                'id' => 0,
                'sender_user_id' => 0,
                'receiver_user_id' => 0,
                'content' => ''
            ];
            if ($message['whisper_id'] > 0) {
                foreach ($whispers as $whisper) {
                    if ($whisper['id'] === $message['whisper_id']) {
                        $message['whisper'] = $whisper;
                    }
                }
            }
        }

        return [
            'rows' => $messages,
            'total' => $this->messageModel->count($condition),
        ];
    }

    /**
     * 新增消息(通用)
     *
     * @param array $data
     * @return boolean
     */
    public function add(array $data)
    {
        $model = $this->messageModel;
        foreach ($data as $key => $value) {
            if (in_array($key, MessageModel::COLUMNS)) {
                $model->$key = $value;
            }
        }

        return $model->save();
    }

    /**
     * 新增冒泡/文章推送消息
     *
     * @param integer $userId
     * @param integer $postId
     * @param integer $commentId
     * @return boolean
     */
    public function addPostPushMessage($userId, $postId, $commentId)
    {
        $this->postModel->load([
            'id' => $postId,
        ]);
        $post = $this->postModel->getData();

        if (!empty($post) && $post['user_id'] != $userId) {
            $model = $this->messageModel;
            $model->sender_user_id = $userId;
            $model->receiver_user_id = $post['user_id'];
            $model->type = 1;
            $model->breif = '在'.($post['type'] == '1' ? '冒泡' : '文章').'下面评论了你';
            $model->content = '';
            $model->post_id = $postId;
            $model->comment_id = $commentId;
            $model->reply_id = 0;
            $model->group_id = 0;
            $model->is_read = 0;

            return $model->save();
        }
        return true;
    }

    /**
     * 新增用户加圈消息 function
     *
     * @param integer $userId
     * @param integer $groupId
     * @return boolean
     */
    public function addUserGroupMessage($userId, $groupId)
    {
        $this->groupModel->load([
            'id' => $groupId
        ]);

        $group = $this->groupModel->getData();
        $receiverUserId = $group['user_id'];

        $model = $this->messageModel;
        $model->sender_user_id = $userId;
        $model->receiver_user_id = $receiverUserId;
        $model->type = 2;
        $model->breif = '加入了圈子'.$group['name'];
        $model->content = '';
        $model->post_id = 0;
        $model->comment_id = 0;
        $model->reply_id = 0;
        $model->group_id = $groupId;
        $model->is_read = 0;

        return $model->save();
    }


    /**
     * 新增评论回复消息
     *
     * @param integer $userId
     * @param integer $atUserId
     * @param integer $postId
     * @param integer $commentId
     * @param integer $replyId
     * @param string $content
     * @return boolean
     */
    public function addCommentReply($userId, $atUserId, $postId, $commentId, $replyId, $content)
    {
        $model = new MessageModel;
        $model->sender_user_id = $userId;
        $model->receiver_user_id = $atUserId;
        $model->type = 1;
        $model->breif = '在评论下回复了你';
        $model->content = $content;
        $model->post_id = $postId;
        $model->comment_id = $commentId;
        $model->reply_id = $replyId;
        $model->group_id = 0;
        $model->is_read = 0;

        return $model->save();
    }

    /**
     * 标记为已读
     *
     * @param integer $userId
     * @param integer $messageId
     * @return boolean
     */
    public function remarkRead($userId, $messageId)
    {
        $this->messageModel->load([
            'id' => $messageId,
            'receiver_user_id' => $userId,
            'is_deleted' => 0,
        ]);

        if ($this->messageModel->getData()) {
            $this->messageModel->is_read = 1;
            $this->messageModel->update(['is_read']);
        }

        return true;
    }

    /**
     * 获取未读消息数量
     *
     * @param integer $userId
     * @return void
     */
    public function unreadCount($userId)
    {
        return $this->messageModel->count([
            'receiver_user_id' => $userId,
            'is_read' => 0,
            'is_deleted' => 0,
        ]);
    }

    /**
    * 新增用户私信消息
    *
    * @param integer $userId
    * @param integer $groupId
    * @return boolean
    */
    public function addWhisperMessage($sendUserId, $receiveUserId, $whisperId)
    {
        $this->userModel->load([
            'id' => $sendUserId,
            'is_deleted' => 0
        ]);
        $user = $this->userModel->getData();

        $model = $this->messageModel;
        $model->sender_user_id = $sendUserId;
        $model->receiver_user_id = $receiveUserId;
        $model->type = 5;
        $model->breif = '用户'.$user['username'].'给你发了私信';
        $model->content = '';
        $model->post_id = 0;
        $model->comment_id = 0;
        $model->reply_id = 0;
        $model->group_id = 0;
        $model->whisper_id = $whisperId;
        $model->is_read = 0;

        return $model->save();
    }
}
