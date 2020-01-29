<?php
// 跨域
route('*', function () {
    header("Access-Control-Allow-Origin: *"); // 为了安全，建议更换为指定域名
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header('Access-Control-Allow-Headers: Content-Type,X-Authorization');
    if (app()->request()->method !== 'OPTIONS') {
        return true;
    }
});

// 输出系统版本（默认）
route('GET /', ['api\HomeController', 'index']);

// 全量推送索引
route('GET /task/push-full-index', ['job\QueueController', 'pushFullIndexTask']);

// 获取图形验证码
route('GET /imageCaptcha', ['api\HomeController', 'getImageCaptcha']);

// 用户上传图片
route('POST /upload/img', ['api\HomeController', 'uploadImg'])->auth();

// 用户上传头像
route('POST /upload/avatar', ['api\HomeController', 'uploadAvatar'])->auth();

// 用户上传视频（分片上传）
route('POST /upload/video', ['api\HomeController', 'uploadVideo'])->auth();

// 用户上传视频（分片上传）结果检测
route('GET /upload/video', ['api\HomeController', 'uploadVideoCheck'])->auth();

/***** Group 集合 *****/

// 获取 Group 列表
route('GET /groups', ['api\GroupController', 'list']);

// 获取 Group 详情
route('GET /group/@groupId:\d+', ['api\GroupController', 'detail']);

// 获取 Group 下 Posts列表
route('GET /group/@groupId:\d+/_posts', ['api\GroupController', 'posts']);

/***** Post 集合 *****/

// 获取冒泡/文章列表
route('GET /posts', ['api\PostController', 'list'])->auth(false);

// 获取冒泡/文章详情
route('GET /post/detail/@aliasId:\w+', ['api\PostController', 'detail'])->auth(false);

// 发表冒泡/文章
route('POST /post/push', ['api\PostController', 'push'])->auth();

// 置顶冒泡/文章
route('POST /post/top/@postId:\d+', ['api\PostController', 'top'])->auth();

// 精华冒泡/文章
route('POST /post/essence/@postId:\d+', ['api\PostController', 'essence'])->auth();

// 删除冒泡/文章
route('DELETE /post/@postId:\d+', ['api\PostController', 'delete'])->auth();

// 获取评论列表
route('GET /post/@postId:\d+/_comments', ['api\CommentController', 'list'])->auth(false);

// 获取评论回复列表
route('GET /post/_comment/@commentId:\d+/_replies', ['api\CommentController', 'replyList'])->auth(false);

// 发布评论
route('POST /post/_comment', ['api\CommentController', 'push'])->auth();

// 删除评论
route('DELETE /comment/@id:\d+', ['api\CommentController', 'delete'])->auth();

// 发布评论回复
route('POST /post/_reply', ['api\CommentController', 'pushReply'])->auth();

// 删除评论回复
route('DELETE /reply/@id:\d+', ['api\CommentController', 'deleteReply'])->auth();

// 冒泡/文章收藏操作
route('POST /post/star', ['api\PostController', 'star'])->auth();

// 冒泡/文章点赞操作
route('POST /post/upvote', ['api\PostController', 'upvote'])->auth();

/***** User 集合 *****/

// 检测用户名是否重复
route('GET /user/check-username', ['api\UserController', 'isExistUsername']);

// 发送验证码 Checked
route('POST /user/send-captcha', ['api\UserController', 'sendCaptcha']);

// 用户注册 Checked
route('POST /user/register', ['api\UserController', 'register']);

// 用户登录 Checked
route('POST /user/login', ['api\UserController', 'login']);

// 获取QQ登录信息
route('GET /user/oauth/qq', ['api\UserController', 'oauthQQ']);

// QQ登录回调处理
route('POST /user/oauth/qq', ['api\UserController', 'oauthQQProceed']);

// 获取登录用户信息 Checked
route('GET /user/info', ['api\UserController', 'info'])->auth();

// 获取其他用户信息
route('GET /user/profile/@username', ['api\UserController', 'profile'])->auth(false);

// 获取登录用户资金记录
route('GET /user/assets/record', ['api\UserController', 'assetsRecord'])->auth();

// 修改用户信息
route('POST /user/baseinfo', ['api\UserController', 'changeBaseInfo'])->auth();

// 修改用户密码
route('POST /user/password', ['api\UserController', 'changePassword'])->auth();

// 用户关注其他用户
route('POST /user/attend', ['api\UserController', 'attend'])->auth();

// 用户私信
route('POST /user/whisper', ['api\UserController', 'whisper'])->auth();

// 获取消息列表
route('GET /messages', ['api\MessageController', 'list'])->auth();

// 标记为已读
route('POST /message/read/remark', ['api\MessageController', 'remarkRead'])->auth();

// 获取未读消息数量
route('GET /message/unread/count', ['api\MessageController', 'unreadCount'])->auth();

/***** 管理集合 *****/

// 获取系统概况
route('GET /admin/summary', ['api\AdminController', 'getSummary'])->auth();

// 获取用户详情
route('GET /admin/users', ['api\AdminController', 'getUsers'])->auth();

// 禁言用户
route('POST /admin/ban/user', ['api\AdminController', 'banUser'])->auth();
