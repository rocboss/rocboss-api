<?php
namespace api;

use BaseController;
use service\PostService;
use service\UtilService;

/**
 * PostController
 * @author ROC <i@rocs.me>
 */
class PostController extends BaseController
{
    protected static $_checkSign = true;

    /**
     * 推送新冒泡/文章
     *
     * @return array
     */
    protected function push()
    {
        $data = app()->request()->data;
        $this->checkParams($data, ['contents', 'type', 'group_id']);
        $params = $data->getData();

        // 类型，1冒泡，2文章
        $type = in_array($params['type'], [1, 2]) ? $params['type'] : 1;
        $groupId = intval($params['group_id']);
        $userId = app()->get('uid');
        $contents = is_array($params['contents']) ? $params['contents'] : [];

        $service = new PostService();
        // 发布权限检测
        if (!$service->postAccessCheck($groupId, $userId)) {
            return [
                'code' => 500,
                'msg' => $service->_error,
            ];
        }

        // 请求发布
        $return = $service->add($groupId, $userId, $type, $contents);

        if (!empty($return)) {
            return [
                'code' => 0,
                'msg' => 'success',
                'data' => [
                    'alias_id' => $return,
                ],
            ];
        }

        return [
            'code' => 500,
            'msg' => $service->_error,
        ];
    }

    /**
     * 获取POST列表
     *
     * @return array
     */
    protected function list()
    {
        $query = app()->request()->query;
        $this->checkParams($query, ['page', 'page_size']);
        $params = $query->getData();

        // 获取当前登录用户信息
        $currentUserId = app()->get('uid');

        $page = $params['page'] > 0 ? intval($params['page']) : 1;
        $pageSize = $params['page_size'] > 0 && $params['page_size'] <= self::MAX_PAGESIZE ? intval($params['page_size']) : 20;
        $userId = !empty($params['user_id']) ? intval($params['user_id']) : 0;
        $type = !empty($params['type']) && in_array($params['type'], [1, 2]) ? $params['type'] : 0;
        $groupId= !empty($params['group_id']) && $params['group_id'] > 0 ? intval($params['group_id']) : 0;
        $keyword = !empty($params['keyword']) ? trim($params['keyword']) : '';

        $condition = [
            'bool' => [
                'should' => [
                    [
                        'match' => [
                            'type' => '1',
                        ]
                    ],
                    [
                        'match' => [
                            'type' => '2',
                        ]
                    ]
                ],
                'must' => [

                ]
            ],
        ];

        // 类型限制
        if ($type > 0) {
            unset($condition['bool']['should']);
            $condition['bool']['must'][] = [
                'match' => [
                    'type' => $type
                ]
            ];
        }

        // Group限制
        if ($groupId > 0) {
            $condition['bool']['must'][] = [
                'match' => [
                    'group_id' => $groupId
                ]
            ];
        }

        if ($userId > 0) {
            // 获取指定用户数据
            $condition['bool']['must'][] = [
                'match' => [
                    'user_id' => $userId
                ]
            ];
        }

        if (!empty($keyword)) {
            $condition['bool']['must'][] = [
                'match' => [
                    'contents_text' => $keyword
                ]
            ];
        }

        // 排序类型
        $sort = ['created_at' => 'desc'];
        if (!empty($params['filter_type']) && in_array($params['filter_type'], ['latest_post', 'latest_comment', 'hot_comment', 'essence_post'])) {
            switch ($params['filter_type']) {
                case 'latest_post':
                    $sort = ['is_top' => 'desc', 'created_at' => 'desc'];
                    break;

                case 'latest_comment':
                    $sort = ['is_top' => 'desc', 'updated_at' => 'desc'];
                    break;

                case 'hot_comment':
                    $sort = ['is_top' => 'desc', 'comment_count' => 'desc'];
                    break;

                case 'essence_post':
                    $condition['bool']['must'][] = [
                        'match' => [
                            'is_essence' => 1
                        ]
                    ];
                    $sort = ['is_top' => 'desc', 'updated_at' => 'desc'];
                    break;

                default:
                    $sort = ['is_top' => 'desc', 'created_at' => 'desc'];
                    break;
            }
        }

        $service = new PostService();
        $data = $service->list($condition, ($page - 1) * $pageSize, $pageSize, $sort);

        // 数据处理
        foreach ($data['rows'] as &$row) {
            $row['title'] = '';
            $row['created_at'] = formatTime($row['created_at_timestamp']);
            $row['updated_at'] = formatTime($row['updated_at_timestamp']);
            $row['image_count'] = 0;
            $row['video_count'] = 0;
            $row['attachment_count'] = 0;

            // 标题&图片类型做聚合处理
            $imgs = [];
            foreach ($row['contents'] as $key => &$content) {
                // 图片类型
                if ($content['type'] === '3') {
                    array_push($imgs, $content['content']);
                    unset($row['contents'][$key]);
                    $row['image_count']++;
                }
                // 视频类型
                if ($content['type'] === '4') {
                    $row['video_count']++;
                }
                // 附件类型
                if ($content['type'] === '7') {
                    $row['attachment_count']++;
                }
                // 文章标题摘要
                if ($content['type'] === '1') {
                    $row['title'] = $content['content'];
                }
                // 冒泡内容精简
                if ($content['type'] === '2' && $row['type'] === '1') {
                    $content['content'] = mb_strlen($content['content']) > 200 ? mb_substr($content['content'], 0, 200).'...' : $content['content'];
                }
            }
            if (!empty($imgs)) {
                array_push($row['contents'], [
                    'type' => '3',
                    'content' => $imgs,
                ]);
            }
            $row['contents'] = array_values($row['contents']);

            unset($row['content_text']);
        }

        return [
            'code' => 0,
            'data' => $data,
        ];
    }

    /**
     * 获取POST详情
     *
     * @param string $aliasId
     * @return array
     */
    protected function detail($aliasId)
    {
        $service = new PostService();
        // 通过aliasId获取POST详情
        $post = $service->getDetailByAliasId($aliasId);

        if (empty($post)) {
            return [
                'code' => 404,
                'msg' => 'POST不存在',
            ];
        }

        // 数据处理
        $post['created_at'] = formatTime(strtotime($post['created_at']));
        $post['updated_at'] = formatTime(strtotime($post['updated_at']));

        if ($post['type'] == 1) {
            // 图片类型做聚合处理
            $imgs = [];
            foreach ($post['contents'] as $key => &$content) {
                if ($content['type'] === '3') {
                    array_push($imgs, $content['content']);
                    unset($post['contents'][$key]);
                }
            }
            if (!empty($imgs)) {
                array_push($post['contents'], [
                    'type' => '3',
                    'content' => $imgs,
                ]);
            }
            $post['contents'] = array_values($post['contents']);
        }

        // 初始用户点赞、收藏详情
        $post['hasUpvoted'] = false;
        $post['hasStarred'] = false;
        $post['canManage'] = false;
        // 用户已登录
        if (app()->get('uid') > 0) {
            // 获取登录态下用户点赞、收藏详情
            $post['hasUpvoted'] = $service->getUserUpvotedStatus($post['id'], app()->get('uid'));
            $post['hasStarred'] = $service->getUserStarredStatus($post['id'], app()->get('uid'));
            $post['canManage'] = $service->manageAccessCheck($service->getPost($aliasId, 'alias_id'), app()->get('uid'));
        }

        return [
            'code' => 0,
            'msg' => 'success',
            'data' => $post,
        ];
    }

    /**
     * POST 收藏操作
     *
     * @return array
     */
    protected function star()
    {
        $data = app()->request()->data;
        $this->checkParams($data, ['post_id']);
        $params = $data->getData();

        $userId = app()->get('uid');

        $service = new PostService();
        $return = $service->star($params['post_id'], $userId);
        if ($return === 0) {
            return [
                'code' => 500,
                'msg' => 'error',
            ];
        }

        // 重新推送ES
        $service->pushToElasticSearch($params['post_id']);

        return [
            'code' => 0,
            'data' => $return
        ];
    }

    /**
     * POST 点赞操作
     *
     * @return array
     */
    protected function upvote()
    {
        $data = app()->request()->data;
        $this->checkParams($data, ['post_id']);
        $params = $data->getData();

        $userId = app()->get('uid');

        $service = new PostService();
        $return = $service->upvote($params['post_id'], $userId);
        if ($return === 0) {
            return [
                'code' => 500,
                'msg' => 'error',
            ];
        }

        // 重新推送ES
        $service->pushToElasticSearch($params['post_id']);

        return [
            'code' => 0,
            'data' => $return
        ];
    }

    /**
     * POST 置顶
     *
     * @param integer $postId
     * @return array
     */
    protected function top($postId)
    {
        $service = new PostService();
        // 获取POST
        $post = $service->getPost($postId, 'id');

        if (!$post) {
            return [
                'code' => 500,
                'msg' => $this->_error,
            ];
        }

        // 权限检测
        $accessCheck = $service->manageAccessCheck($post, app()->get('uid'));

        if ($accessCheck && in_array('admin', app()->get('user')['access'])) {
            // 处理 POST
            $service->setPostTop($post);

            return [
                'code' => 0,
            ];
        }

        return [
            'code' => 403,
            'msg' => '无权置顶操作',
        ];
    }

    /**
     * POST 精华
     *
     * @param integer $postId
     * @return array
     */
    protected function essence($postId)
    {
        $service = new PostService();
        // 获取POST
        $post = $service->getPost($postId, 'id');

        if (!$post) {
            return [
                'code' => 500,
                'msg' => $this->_error,
            ];
        }

        // 权限检测
        $accessCheck = $service->manageAccessCheck($post, app()->get('uid'));

        if ($accessCheck && in_array('admin', app()->get('user')['access'])) {
            // 处理 POST
            $service->setPostEssence($post);

            return [
                'code' => 0,
            ];
        }

        return [
            'code' => 403,
            'msg' => '无权精华操作',
        ];
    }

    /**
     * POST 删除操作
     *
     * @param integer $postId
     * @return array
     */
    protected function delete($postId)
    {
        $service = new PostService();
        // 获取POST
        $post = $service->getPost($postId, 'id');

        if (!$post) {
            return [
                'code' => 500,
                'msg' => $this->_error,
            ];
        }

        // 权限检测
        $accessCheck = $service->manageAccessCheck($post, app()->get('uid'));

        if ($accessCheck) {
            // 删除POST
            $service->delete($post);

            return [
                'code' => 0,
            ];
        }

        return [
            'code' => 403,
            'msg' => '无权删除',
        ];
    }
}
