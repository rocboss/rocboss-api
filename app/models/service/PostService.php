<?php
namespace service;

use EndyJasmi\Cuid;
use service\UserService;
use service\UserAssetService;
use service\BillService;
use service\GroupUserService;
use model\PostModel;
use model\PostContentModel;
use model\GroupBlacklistModel;
use model\PostUpvoteModel;

/**
 * PostService
 * @author ROC <i@rocs.me>
 */
class PostService extends Service
{
    /**
     * 获取POST
     *
     * @param integer $id
     * @param string $type
     * @return boolean/PostModel
     */
    public function getPost($id, $type)
    {
        if (!in_array($type, PostModel::COLUMNS)) {
            $this->_error = '查询条件不合法';
            return false;
        }

        $this->resetModel('postModel')->postModel->load([
            $type => $id,
            'is_deleted' => 0,
        ]);
        $post = $this->postModel->getData();

        if (!empty($post)) {
            return $this->postModel;
        }

        $this->_error = 'POST不存在';
        return false;
    }

    /**
     * 新增POST
     *
     * @param integer $groupId
     * @param integer $userId
     * @param integer $type
     * @param array $contents
     * @return string
     */
    public function add($groupId, $userId, $type, array $contents)
    {
        $aliasId = strtoupper(Cuid::slug());

        $post = new PostModel();
        $post->alias_id = $aliasId;
        $post->group_id = $groupId;
        $post->user_id = $userId;
        $post->type = $type;

        // 保存POST主题
        if ($post->save()) {
            // 插入POST详细内容
            $sort = 100;
            foreach ($contents as $content) {
                // 媒体资源ID转换 (3图片地址，4视频地址，5语音地址，7附件资源)
                if (in_array($content['type'], [3, 4, 5, 7])) {
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

                $postContent = new PostContentModel();
                $postContent->post_id = $post->getPrimaryKey()['id'];
                $postContent->group_id = $groupId;
                $postContent->user_id = $userId;
                $postContent->content = $content['content'];
                $postContent->type = $content['type'];
                $postContent->sort = $sort ++;

                $postContent->save();
            }

            if ($post->getPrimaryKey()['id'] > 0) {
                // 推送至ES
                if (!$this->pushToElasticSearch($post->getPrimaryKey()['id'])) {
                    app()->log()->error('推送ES索引服务器失败', ['id' => $post->getPrimaryKey()['id']]);

                    $this->_error = '推送ES索引服务器失败';
                    return '';
                }

                return $aliasId;
            }
        }

        $this->_error = '新增失败';
        return '';
    }

    /**
     * 获取POST列表
     *
     * @param array $condition
     * @param integer $offset
     * @param integer $limit
     * @param array $sort
     * @return array
     */
    public function list(array $condition, $offset = 0, $limit = 20, array $sort = ['created_at' => 'desc'])
    {
        try {
            $posts = app()->es()->search([
                'query' => $condition,
                'from' => $offset,
                'size' => $limit,
                'sort' => $sort,
            ], env('ES_POST_INDEX'), env('ES_POST_TYPE'));
        } catch (\Exception $e) {
            app()->log()->error('ES_SEARCH_ERROR', ['error' => $e->getMessage()]);
        }

        $total = 0;
        $rows = [];

        if (!empty($posts['hits'])) {
            $total = $posts['hits']['total'];

            foreach ($posts['hits']['hits'] as $hit) {
                array_push($rows, $hit['_source']);
            }

            $userIds = array_column($rows, 'user_id');
            $groupIds = array_column($rows, 'group_id');

            $users = $this->userModel->dump([
                'id' => $userIds,
                'is_deleted' => 0,
            ], [
                'id',
                'username',
                'avatar',
            ]);

            $groups = $this->groupModel->dump([
                'id' => $groupIds,
                'is_deleted' => 0,
            ], [
                'id',
                'name',
                'desc',
                'cover',
            ]);

            foreach ($rows as &$row) {
                $row['user'] = $row['group'] = [];
                // 用户
                foreach ($users as $user) {
                    if ($user['id'] == $row['user_id']) {
                        $row['user'] = $user;
                    }
                }
                // 圈子
                foreach ($groups as $group) {
                    if ($group['id'] == $row['group_id']) {
                        $row['group'] = $group;
                    }
                }
            }
        }

        return [
            'rows' => $rows,
            'total' => $total,
        ];
    }

    /**
     * 通过别名ID获取详情
     *
     * @param string $aliasId
     * @return mixed
     */
    public function getDetailByAliasId($aliasId)
    {
        $this->postModel->load([
            'alias_id' => $aliasId,
            'is_deleted' => 0,
        ]);
        $post = $this->postModel->getData();

        if (!empty($post)) {
            // 获取具体内容
            $contentModel = $this->postContentModel;
            $contents = (array) $contentModel->dump([
                'post_id' => $post['id'],
                'is_deleted' => 0,
                'ORDER' => [
                    'sort' => 'ASC',
                    'id' => 'ASC',
                ],
            ], [
                'content',
                'type',
                'sort',
            ]);

            $post['contents'] = $contents;

            // 获取发布主信息
            $post['user'] = [];
            $userModel = $this->userModel;
            $userModel->load([
                'id' => $post['user_id'],
                'is_deleted' => 0,
            ]);
            $user = $userModel->getData();
            if (!empty($user)) {
                $post['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'avatar' => $user['avatar'],
                ];
            }

            return $post;
        }

        $this->_error = 'POST不存在';
        return false;
    }
    /**
     * 发布权限检测
     *
     * @param integer $groupId
     * @param integer $userId
     * @return boolean
     */
    public function postAccessCheck($groupId, $userId)
    {
        $userService = new UserService();
        $user = $userService->getUserModel(['id' => $userId]);
        if (!$user) {
            $this->_error = $userService->_error;
            return false;
        }
        // 是否被全局封号
        if ($userService->isBanned($user)) {
            $this->_error = '该账户已被封号';
            return false;
        }

        return true;
    }

    /**
     * 管理POST权限检测
     *
     * @param PostModel $post
     * @param integer $userId
     * @return boolean
     */
    public function manageAccessCheck(PostModel $post, $userId)
    {
        // 发布主check
        if ($post->user_id == $userId) {
            return true;
        }

        // 超级管理员身份CHECK
        if ((new UserService())->isSuperManager($userId)) {
            return true;
        }

        return false;
    }

    /**
     * 获取用户点赞状态
     *
     * @param integer $postId
     * @param integer $userId
     * @return boolean
     */
    public function getUserUpvotedStatus($postId, $userId)
    {
        $this->postUpvoteModel->load([
            'user_id' => $userId,
            'post_id' => $postId,
            'is_deleted' => 0,
        ]);

        return !empty($this->postUpvoteModel->getData()) ? true : false;
    }

    /**
     * 获取用户收藏状态
     *
     * @param integer $postId
     * @param integer $userId
     * @return boolean
     */
    public function getUserStarredStatus($postId, $userId)
    {
        $this->postStarModel->load([
            'user_id' => $userId,
            'post_id' => $postId,
            'is_deleted' => 0,
        ]);

        return !empty($this->postStarModel->getData()) ? true : false;
    }

    /**
     * 全量推送数据至ES服务器
     *
     * @return boolean
     */
    public function pushFullPostsToEs()
    {
        try {
            // 已有索引数据全部清除
            app()->es()->deleteIndex(env('ES_POST_INDEX'));
        } catch (\Exception $e) {
        }

        // 分页处理
        $total = $this->postModel->count([
            'is_deleted' => 0,
        ]);
        $per = 1000;
        $pages = ceil($total / $per);

        for ($page = 1; $page <= $pages; $page++) {
            $posts = $this->postModel->dump([
                'ORDER' => ['id' => 'ASC'],
                'LIMIT' => [($page - 1) * $per, $per],
                'is_deleted' => 0,
            ], [
                'id'
            ]);

            $postIds = array_column($posts, 'id');

            foreach ($postIds as $postId) {
                $this->pushToElasticSearch($postId);
            }

            // 延时缓冲
            sleep(1);
        }

        return true;
    }

    /**
     * 推送到ES服务器
     *
     * @param integer $postId
     * @return boolean
     */
    public function pushToElasticSearch($postId)
    {
        $post = $this->getDetailFromDatabase($postId);

        // 推送至ES
        if (!empty($post)) {
            // 不存在则新建索引
            if (!app()->es()->existsIndex(env('ES_POST_INDEX'))) {
                // 创建索引
                app()->es()->createIndex(env('ES_POST_INDEX'));
                // 创建文档映射
                app()->es()->createMappings([
                    'alias_id' => [
                        'type' => 'text',
                    ],
                    'group_id' => [
                        'type' => 'integer',
                    ],
                    'user_id' => [
                        'type' => 'integer',
                    ],
                    'type' => [
                        'type' => 'integer',
                    ],
                    'created_at' => [
                        'type' => 'date',
                    ],
                    'created_at_timestamp' => [
                        'type' => 'integer',
                    ],
                    'updated_at' => [
                        'type' => 'date',
                    ],
                    'updated_at_timestamp' => [
                        'type' => 'integer',
                    ],
                    'comment_count' => [
                        'type' => 'integer',
                    ],
                    'collection_count' => [
                        'type' => 'integer',
                    ],
                    'upvote_count' => [
                        'type' => 'integer',
                    ],
                    'is_top' => [
                        'type' => 'integer',
                    ],
                    'is_essence' => [
                        'type' => 'integer',
                    ],
                    'contents' => [],
                    'contents_text' => [
                        'type' => 'text',
                        'analyzer' => 'ik_max_word',
                    ]
                ], env('ES_POST_INDEX'), env('ES_POST_TYPE'));
            }

            $contentsText = '';
            if (is_array($post['contents'])) {
                foreach ($post['contents'] as $content) {
                    // 标题或者内容纳入进全文检索
                    if ($content['type'] == 1 || $content['type'] == 2) {
                        $contentsText .= ' '.$content['content'];
                    }
                }
            }
            // 推送文档
            app()->es()->addDoc($post['id'], [
                'alias_id' => $post['alias_id'],
                'group_id' => $post['group_id'],
                'user_id' => $post['user_id'],
                'type' => $post['type'],
                'created_at' => date('c', strtotime($post['created_at'])),
                'created_at_timestamp' => strtotime($post['created_at']),
                'updated_at' => date('c', strtotime($post['updated_at'])),
                'updated_at_timestamp' => strtotime($post['updated_at']),
                'contents' => $post['contents'],
                'comment_count' => $post['comment_count'],
                'collection_count' => $post['collection_count'],
                'upvote_count' => $post['upvote_count'],
                'is_top' => $post['is_top'],
                'is_essence' => $post['is_essence'],
                'contents_text' => $contentsText,
            ], env('ES_POST_INDEX'), env('ES_POST_TYPE'));

            return true;
        }

        return false;
    }

    /**
     * 获取POST详情
     *
     * @param integer $postId
     * @return mixed
     */
    public function getDetailFromDatabase($postId)
    {
        $this->postModel->load([
            'id' => $postId,
            'is_deleted' => 0,
        ]);
        $post = $this->postModel->getData();

        if (!empty($post)) {
            $content = $this->postContentModel;
            $contents = (array) $content->dump([
                'post_id' => $postId,
                'is_deleted' => 0,
                'ORDER' => [
                    'sort' => 'ASC',
                    'id' => 'ASC',
                ],
            ], [
                'content',
                'type',
                'sort',
            ]);

            $post['contents'] = $contents;

            return $post;
        }

        $this->_error = 'POST不存在';
        return false;
    }

    /**
     * POST 收藏操作
     *
     * @param integer $postId
     * @param integer $userId
     * @return integer
     */
    public function star($postId, $userId)
    {
        $this->postStarModel->load([
            'user_id' => $userId,
            'post_id' => $postId,
            'is_deleted' => 0,
        ]);
        if (!empty($this->postStarModel->getData())) {
            if ($this->postStarModel->delete()) {
                $this->updateCollectionCount(-1, $postId);

                return -1;
            }
            return 0;
        } else {
            $postStarModel = $this->postStarModel;
            $postStarModel->post_id = $postId;
            $postStarModel->user_id = $userId;

            if ($postStarModel->save()) {
                $this->updateCollectionCount(1, $postId);
                return 1;
            }
            return 0;
        }
    }

    /**
     * POST 点赞操作
     *
     * @param integer $postId
     * @param integer $userId
     * @return integer
     */
    public function upvote($postId, $userId)
    {
        $this->postUpvoteModel->load([
            'user_id' => $userId,
            'post_id' => $postId,
            'is_deleted' => 0,
        ]);
        if (!empty($this->postUpvoteModel->getData())) {
            if ($this->postUpvoteModel->delete()) {
                $this->updateUpvoteCount(-1, $postId);

                return -1;
            }
            return 0;
        } else {
            $postUpvoteModel = $this->postUpvoteModel;
            $postUpvoteModel->post_id = $postId;
            $postUpvoteModel->user_id = $userId;

            if ($postUpvoteModel->save()) {
                $this->updateUpvoteCount(1, $postId);
                return 1;
            }
            return 0;
        }
    }

    /**
     * 设置置顶
     *
     * @param PostModel $post
     * @return boolean
     */
    public function setPostTop($post)
    {
        if ($post->getDatabase()->update(PostModel::TABLE, [
            'is_top' => \Medoo\Medoo::raw('1 - is_top')
        ], [
            'id' => $post->id
        ])) {
            $this->pushToElasticSearch($post->id);
            return true;
        }

        return false;
    }

    /**
     * 设置置顶
     *
     * @param PostModel $post
     * @return boolean
     */
    public function setPostEssence($post)
    {
        if ($post->getDatabase()->update(PostModel::TABLE, [
            'is_essence' => \Medoo\Medoo::raw('1 - is_essence')
        ], [
            'id' => $post->id
        ])) {
            $this->pushToElasticSearch($post->id);
            return true;
        }

        return false;
    }

    /**
     * 删除POST
     *
     * @param PostModel $post
     * @return boolean
     */
    public function delete($post)
    {
        // 从ES中删除数据
        try {
            app()->es()->deleteDoc($post->id, env('ES_POST_INDEX'), env('ES_POST_TYPE'));
        } catch (\Exception $e) {
        }

        return $post->delete();
    }

    /**
     * 修改 POST 收藏数
     *
     * @param integer $change
     * @param integer $postId
     * @return void
     */
    public function updateCollectionCount($change, $postId)
    {
        $this->postModel->getDatabase()->update(PostModel::TABLE, [
            'collection_count[+]' => intval($change)
        ], [
            'id' => $postId
        ]);
    }

    /**
     * 修改 POST 点赞数
     *
     * @param integer $change
     * @param integer $postId
     * @return void
     */
    public function updateUpvoteCount($change, $postId)
    {
        $this->postModel->getDatabase()->update(PostModel::TABLE, [
            'upvote_count[+]' => intval($change)
        ], [
            'id' => $postId
        ]);
    }

    /**
     * 修改 POST 评论数
     *
     * @param integer $change
     * @param integer $postId
     * @return void
     */
    public function updateCommentCount($change, $postId)
    {
        $this->postModel->getDatabase()->update(PostModel::TABLE, [
            'comment_count[+]' => $change
        ], [
            'id' => $postId
        ]);
    }

    /**
     * 重新计算POST的评论数
     *
     * @param integer $postId
     * @return void
     */
    public function reCalcCommentCount($postId)
    {
        $comments = $this->commentModel->dump([
            'post_id' => $postId,
            'is_deleted' => 0,
        ], [
            'id'
        ]);
        $commentIds = array_column($comments, 'id');

        $this->postModel->getDatabase()->update(PostModel::TABLE, [
            'comment_count' => $this->commentModel->count([
                'post_id' => $postId,
                'is_deleted' => 0,
            ]) + $this->commentReplyModel->count([
                'comment_id' => $commentIds,
                'is_deleted' => 0,
            ])
        ], [
            'id' => $postId
        ]);
    }

    /**
     * 获取POST ID
     *
     * @param array $condition
     * @param integer $offset
     * @param integer $limit
     * @return array
     */
    public function getPostIds(array $condition, $offset = 0, $limit = 20)
    {
        $posts = (array) $this->postModel->dump(array_merge($condition, [
            'LIMIT' => [$offset, $limit]
        ]), [
            'id'
        ]);

        return array_column($posts, 'id');
    }
}
