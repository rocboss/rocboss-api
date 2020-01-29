SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for rocboss_attachment
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_attachment`;
CREATE TABLE `rocboss_attachment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL DEFAULT '0',
  `file_size` int(11) unsigned NOT NULL,
  `img_width` int(11) unsigned NOT NULL DEFAULT '0',
  `img_height` int(11) unsigned NOT NULL DEFAULT '0',
  `type` tinyint(4) unsigned NOT NULL DEFAULT '1',
  `content` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='附件';

-- ----------------------------
-- Table structure for rocboss_captcha
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_captcha`;
CREATE TABLE `rocboss_captcha` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '验证码ID',
  `email` varchar(64) NOT NULL DEFAULT '' COMMENT '邮箱',
  `captcha` varchar(16) NOT NULL DEFAULT '' COMMENT '验证码',
  `use_times` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '使用次数',
  `expired_at` datetime NOT NULL COMMENT '过期时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_email` (`email`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='验证码';

-- ----------------------------
-- Table structure for rocboss_comment
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_comment`;
CREATE TABLE `rocboss_comment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '评论ID',
  `post_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'POST ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  PRIMARY KEY (`id`),
  KEY `idx_post` (`post_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=60000000 DEFAULT CHARSET=utf8mb4 COMMENT='评论';

-- ----------------------------
-- Table structure for rocboss_comment_content
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_comment_content`;
CREATE TABLE `rocboss_comment_content` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '内容ID',
  `comment_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '评论ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `content` varchar(255) NOT NULL DEFAULT '' COMMENT '内容',
  `type` tinyint(4) unsigned NOT NULL DEFAULT '2' COMMENT '类型，1标题，2文字段落，3图片地址，4视频地址，5语音地址，6链接地址',
  `sort` int(11) unsigned NOT NULL DEFAULT '100' COMMENT '排序，越小越靠前',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  PRIMARY KEY (`id`),
  KEY `idx_reply` (`comment_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=100000 DEFAULT CHARSET=utf8mb4 COMMENT='评论内容';

-- ----------------------------
-- Table structure for rocboss_comment_reply
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_comment_reply`;
CREATE TABLE `rocboss_comment_reply` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '回复ID',
  `comment_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '评论ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `at_user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '@用户ID',
  `content` varchar(255) NOT NULL DEFAULT '' COMMENT '内容',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  PRIMARY KEY (`id`),
  KEY `idx_comment` (`comment_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='评论回复';

-- ----------------------------
-- Table structure for rocboss_group
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_group`;
CREATE TABLE `rocboss_group` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '圈子ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '圈主用户ID',
  `name` varchar(32) NOT NULL DEFAULT '' COMMENT '圈子名称',
  `desc` varchar(255) NOT NULL DEFAULT '' COMMENT '圈子描述',
  `cover` varchar(255) NOT NULL DEFAULT '' COMMENT '圈子封面地址',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `idx_name` (`name`) USING BTREE,
  KEY `idx_user` (`user_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=8005 DEFAULT CHARSET=utf8mb4 COMMENT='圈子';

-- ----------------------------
-- Records of rocboss_group
-- ----------------------------
BEGIN;
INSERT INTO `rocboss_group` VALUES (8001, 101000001, '默认分组', '技术交流', '', '2019-11-01 22:38:12', '2020-01-29 16:41:29', 0);
INSERT INTO `rocboss_group` VALUES (8002, 101000001, '技术分享', '天下杂谈', '', '2019-11-01 22:38:12', '2020-01-29 16:41:39', 0);
INSERT INTO `rocboss_group` VALUES (8003, 101000001, '心情分享', '心情分享', '', '2019-11-01 22:38:12', '0000-00-00 00:00:00', 0);
INSERT INTO `rocboss_group` VALUES (8004, 101000001, '灌水专区', '灌水专区', '', '2019-11-01 22:38:12', '2019-11-02 02:00:11', 0);
COMMIT;

-- ----------------------------
-- Table structure for rocboss_message
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_message`;
CREATE TABLE `rocboss_message` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '消息通知ID',
  `sender_user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '发送方用户ID',
  `receiver_user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '接收方用户ID',
  `type` tinyint(4) unsigned NOT NULL DEFAULT '1' COMMENT '通知类型，1评论&回复，2加入圈子通知，3打赏通知，4转账通知，5私信',
  `breif` varchar(255) NOT NULL COMMENT '摘要说明',
  `content` varchar(255) NOT NULL DEFAULT '' COMMENT '详细内容',
  `post_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '关联POST ID',
  `comment_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '评论ID',
  `reply_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '回复ID',
  `group_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '关联圈子 ID',
  `whisper_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '私信ID',
  `is_read` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否已读',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  PRIMARY KEY (`id`),
  KEY `idx_receiver` (`receiver_user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='消息通知';

-- ----------------------------
-- Table structure for rocboss_post
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_post`;
CREATE TABLE `rocboss_post` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主题ID',
  `alias_id` varchar(32) NOT NULL DEFAULT '' COMMENT '别名ID',
  `group_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '圈子ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `type` tinyint(4) unsigned NOT NULL DEFAULT '1' COMMENT '类型，1冒泡，2文章',
  `comment_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '评论数',
  `collection_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '收藏数',
  `upvote_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '点赞数',
  `is_top` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否置顶',
  `is_essence` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否精华',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_alias` (`alias_id`),
  KEY `idx_group` (`group_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=101000000 DEFAULT CHARSET=utf8mb4 COMMENT='冒泡/文章';

-- ----------------------------
-- Table structure for rocboss_post_content
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_post_content`;
CREATE TABLE `rocboss_post_content` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '内容ID',
  `post_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'POST ID',
  `group_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '圈子ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `content` varchar(2000) NOT NULL DEFAULT '' COMMENT '内容',
  `type` tinyint(4) unsigned NOT NULL DEFAULT '2' COMMENT '类型，1标题，2文字段落，3图片地址，4视频地址，5语音地址，6链接地址，7附件资源',
  `sort` int(11) unsigned NOT NULL DEFAULT '100' COMMENT '排序，越小越靠前',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_post` (`post_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='冒泡/文章内容';

-- ----------------------------
-- Table structure for rocboss_post_star
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_post_star`;
CREATE TABLE `rocboss_post_star` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '收藏ID',
  `post_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'POST ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  PRIMARY KEY (`id`),
  KEY `idx_post` (`post_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='冒泡/文章收藏';

-- ----------------------------
-- Table structure for rocboss_post_upvote
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_post_upvote`;
CREATE TABLE `rocboss_post_upvote` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'POST ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'USER ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  PRIMARY KEY (`id`),
  KEY `idx_post` (`post_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='冒泡/文章点赞';

-- ----------------------------
-- Table structure for rocboss_user
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_user`;
CREATE TABLE `rocboss_user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` varchar(32) NOT NULL DEFAULT '' COMMENT '用户名',
  `signature` varchar(255) NOT NULL DEFAULT '' COMMENT '用户签名',
  `email` varchar(64) NOT NULL DEFAULT '' COMMENT '用户邮箱',
  `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '头像',
  `password` varchar(255) NOT NULL DEFAULT '' COMMENT '密码',
  `claim_token` varchar(32) NOT NULL DEFAULT '' COMMENT 'Claim Token',
  `qq_openid` char(32) NOT NULL DEFAULT '' COMMENT 'QQ授权OpenID',
  `is_banned` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否被禁封',
  `role` tinyint(4) unsigned NOT NULL DEFAULT '1' COMMENT '角色，1普通用户，99超级管理员',
  `balance` decimal(20,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '用户可用资产',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `idx_username` (`username`) USING BTREE,
  UNIQUE KEY `idx_phone` (`email`) USING BTREE,
  KEY `idx_qq_openid` (`qq_openid`)
) ENGINE=InnoDB AUTO_INCREMENT=101000002 DEFAULT CHARSET=utf8mb4 COMMENT='用户';

-- ----------------------------
-- Records of rocboss_user
-- ----------------------------
BEGIN;
INSERT INTO `rocboss_user` VALUES (101000001, 'admin', '没签名不个性', 'admin@admin.com', 'https://assets.rocboss.com/avatars/b1/9b/d8/40//5e3145f095526.png', '$2y$10$YO.ks0hYoJ8gKjEf2VDmKexVU1Ke3nYzSwcQ4RadF6qXjTVp/ETGO', '5e3141f3ec8c3', '', 0, 99, 0.00, '2020-01-29 18:00:00', '2020-01-29 18:00:00', 0);
COMMIT;

-- ----------------------------
-- Table structure for rocboss_user_assets_record
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_user_assets_record`;
CREATE TABLE `rocboss_user_assets_record` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '资产记录ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `change` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '变动',
  `note` varchar(255) NOT NULL COMMENT '备注',
  `balance` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '余额',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户资产变更记录';

-- ----------------------------
-- Table structure for rocboss_user_attention
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_user_attention`;
CREATE TABLE `rocboss_user_attention` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '关注ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `attentioned_user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '被关注者ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_user` (`user_id`) USING BTREE,
  KEY `idx_attentioned_user` (`attentioned_user_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户关注';

-- ----------------------------
-- Table structure for rocboss_user_whisper
-- ----------------------------
DROP TABLE IF EXISTS `rocboss_user_whisper`;
CREATE TABLE `rocboss_user_whisper` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `sender_user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '发送者用户ID',
  `receiver_user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '接受者用户ID',
  `content` varchar(255) NOT NULL DEFAULT '' COMMENT '内容',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_deleted` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_sender` (`sender_user_id`) USING BTREE,
  KEY `idx_receiver` (`receiver_user_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户私信';

SET FOREIGN_KEY_CHECKS = 1;
