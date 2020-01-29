<?php
namespace service;

use Tx\Mailer;
use GuzzleHttp\Client;
use OSS\OssClient;
use OSS\Core\OssException;
use OSS\Core\OssUtil;

use model\UserModel;
use model\CaptchaModel;
use model\AttachmentModel;
use model\UserBlackListModel;

/**
 * UserService
 * @author ROC <i@rocs.me>
 */
class UserService extends Service
{
    /**
     * 创建用户
     *
     * @param string $username
     * @param string $email
     * @param string $password
     * @return boolean
     */
    public function createUser($username, $email, $password)
    {
        // Avatar Generator (PNG Format)
        $identicon = new \Identicon\Identicon();
        $avatarData = $identicon->getImageData($email, 512, null, '#EEEEEE');
        $avatar = '';

        // 上传默认头像至阿里云OSS
        try {
            $ossClient = new \OSS\OssClient(env('ALIYUN_ACCESS_KEY_ID'), env('ALIYUN_ACCESS_KEY_SECRET'), env('ALIYUN_OSS_ENDPOINT'));

            $objectPath = 'avatars/'.chunk_split(substr(md5($email), 8, 8), 2, '/').uniqid().'.png';
            $ossClient->putObject(env('ALIYUN_OSS_BUCKET'), $objectPath, $avatarData);

            $avatar = 'https://'.env('ALIYUN_OSS_BUCKET_DOMAIN').'/'.$objectPath;
        } catch (\OSS\Core\OssException $e) {
            app()->log()->error('Upload default avatar fail.', ['error' => $e->getMessage()]);

            $this->_error = $e->getMessage();
            return false;
        }

        $claimToken = uniqid();

        $model = $this->userModel;
        $model->username = $username;
        $model->email = $email;
        $model->avatar = $avatar;
        $model->password = password_hash($password, PASSWORD_DEFAULT);
        $model->claim_token = $claimToken;
        $model->is_banned = 0;
        if ($model->save()) {
            // 设置刚刚注册的用户ID
            app()->set('register.user.id', $model->getPrimaryKey()['id']);
            app()->set('register.user.claim_token', $claimToken);
            return true;
        }

        $this->_error = '新增用户异常';
        return false;
    }

    /**
     * 编辑用户数据
     *
     * @param integer $userId
     * @param array $data
     * @return boolean
     */
    public function editUser($userId, array $data)
    {
        $this->userModel->load([
            'id' => $userId,
            'is_deleted' => 0,
        ]);

        if (!empty($this->userModel->getData())) {
            foreach ($data as $key => $value) {
                $this->userModel->$key = $value;
            }
            $this->userModel->update(array_keys($data));

            return true;
        }

        $this->_error = '用户不存在';
        return false;
    }

    /**
     * 用户登录
     *
     * @param string $type
     * @param string $account
     * @param string $password
     * @return boolean
     */
    public function doLogin($type, $account, $password)
    {
        $type = in_array($type, ['email', 'username']) ? $type : 'username';

        $model = $this->userModel;
        $model->load([
            $type => $account,
            'is_deleted' => 0,
        ]);
        $user = $model->getData();
        if (!empty($user)) {
            // 密码验证，兼容老系统加密方式
            if (password_verify($password, $user['password']) || (strlen($user['password']) == 32 && md5($password) == $user['password'])) {
                unset($user['password']);
                app()->set('user', $user);

                return true;
            }
            $this->_error = '密码不正确';
            return false;
        }
        $this->_error = '用户不存在';
        return false;
    }

    /**
     * 绑定QQ OAuth授权
     *
     * @param integer $userId
     * @param array $userInfo
     * @param boolean $saveAvatar
     * @return boolean
     */
    public function bindQQOAuth($userId, $userInfo, $saveAvatar = false)
    {
        if (empty($userInfo['openid']) || empty($userInfo['avatar'])) {
            $this->_error = '获取QQ授权信息失败';
            return false;
        }

        $model = $this->resetModel('userModel')->userModel;
        $model->load([
            'qq_openid' => $userInfo['openid'],
            'is_deleted' => 0,
        ]);
        if (!empty($model->getData())) {
            $this->_error = '该QQ已被其他账号绑定';
            return false;
        }

        $model = $this->resetModel('userModel')->userModel;
        $model->load([
            'id' => $userId,
            'is_deleted' => 0,
        ]);
        $userData = $model->getData();
        if (!empty($userData)) {
            if (!empty($userData['qq_openid'])) {
                $this->_error = '该账号已绑定其他QQ';
                return false;
            }

            // 头像转存OSS
            if ($saveAvatar) {
                // 头像转存
                $tmpPath = sys_get_temp_dir().'/'.md5($userInfo['avatar']).'.jpeg';
                $response = (new Client(['timeout' => 3.0, 'verify' => false]))->get($userInfo['avatar'], ['save_to' => $tmpPath]);
                if ($response->getStatusCode() == 200) {
                    // 更新头像
                    $remoteUrl = 'avatars/'.chunk_split(substr(md5($userId), 8, 8), 2, '/').uniqid().'.png';
                    $this->updateAvatar($userId, $remoteUrl, $tmpPath);
                }
            }

            // 执行绑定
            $model->qq_openid = $userInfo['openid'];
            if ($model->save('qq_openid') === true) {
                return true;
            }
        }

        $this->_error = '绑定QQ失败';
        return false;
    }

    /**
     * 根据QQ OpenId获取用户信息
     *
     * @param string $openId
     * @return mixed
     */
    public function getUserInfoByQQOpenId($openId)
    {
        $model = $this->userModel;
        $model->load([
            'qq_openid' => $openId,
            'is_deleted' => 0,
        ]);
        $user = $model->getData();

        if (!empty($user)) {
            unset($user['password']);

            return $user;
        }

        $this->_error = '未找到授权用户';
        return false;
    }

    /**
     * 获取其他用户数据
     *
     * @param string $username
     * @param integer $userId
     * @return mixed
     */
    public function profile($username, $userId)
    {
        $model = $this->userModel;
        $model->load([
            'username' => $username,
            'is_deleted' => 0
        ]);

        $user = $model->getData();
        if (!empty($user)) {
            unset($user['password'], $user['phone'], $user['claim_token']);

            // 是否已关注
            $user['is_attentioned'] = 0;
            if ($userId > 0) {
                $user['is_attentioned'] = $this->userAttentionModel->has([
                    'user_id' => $userId,
                    'attentioned_user_id' => $user['id'],
                    'is_deleted' => 0,
                ]) ? 1 : 0;
            }

            // 用户关注数
            $user['attention_user_count'] = $this->userAttentionModel->count([
                'user_id' => $user['id'],
                'is_deleted' => 0,
            ]);

            // 用户粉丝数
            $user['fans_user_count'] = $this->userAttentionModel->count([
                'attentioned_user_id' => $user['id'],
                'is_deleted' => 0,
            ]);

            return $user;
        }

        $this->_error = '用户不存在';
        return false;
    }

    /**
     * 其他用户下的圈子
     *
     * @param string $username
     * @param integer $offset
     * @param integer $limit
     * @return mixed
     */
    public function profileGroups($username, $offset = 0, $limit = 20)
    {
        $model = $this->userModel;
        $model->load([
            'username' => $username,
            'is_deleted' => 0
        ]);

        $user = $model->getData();
        if (!empty($user)) {
            $model = $this->groupUserModel;
            $groupMaps = (array) $model->dump([
                'user_id' => $user['id'],
                'is_quited' => 0,
                'is_deleted' => 0,
                'ORDER' => ['id' => 'ASC'],
                'LIMIT' => [$offset, $limit],
            ], [
                'group_id'
            ]);

            $groupIds = array_column($groupMaps, 'group_id');

            $groupModel = $this->groupModel;
            $groups = $groupModel->dump([
                'id' => $groupIds,
                'is_deleted' => 0,
            ], [
                'id',
                'user_id',
                'name',
                'desc',
                'cover',
                'type',
                'post_count',
                'user_count',
            ]);

            return [
                'rows' => $groups,
                'total' => $this->groupUserModel->count([
                    'user_id' => $user['id'],
                    'is_quited' => 0,
                    'is_deleted' => 0,
                ]),
            ];
        }
    }

    /**
     * 用户资产变动记录列表
     *
     * @param array $condition
     * @param integer $offset
     * @param integer $limit
     * @return array
     */
    public function assetsRecords(array $condition, $offset = 0, $limit = 20)
    {
        $rows = $this->userAssetsRecordModel->dump(array_merge($condition, [
            'LIMIT' => [$offset, $limit]
        ]));

        foreach ($rows as &$row) {
        }

        return [
            'rows' => $rows,
            'total' => $this->userAssetsRecordModel->count($condition),
        ];
    }

    /**
     * 获取用户模型
     *
     * @param array $condition
     * @return mixed
     */
    public function getUserModel($condition)
    {
        $this->userModel->load(array_merge([
            'is_deleted' => 0,
        ], $condition));
        if (!empty($this->userModel->getData())) {
            return $this->userModel;
        }

        $this->_error = '用户不存在';
        return false;
    }

    /**
     * 获取用户基本信息
     *
     * @param integer $id
     * @return array
     */
    public function getUserBaseInfo($id)
    {
        $model = $this->userModel;
        $model->load([
            'id' => $id,
            'is_deleted' => 0,
        ]);
        $user = $model->getData();
        if (!empty($user)) {
            unset($user['password']);

            // 用户权限组
            $user['access'] = $user['role'] == 99 ? ['super_admin', 'admin'] : ['user'];
            
            return $user;
        }

        return [];
    }

    /**
     * 获取用户圈子列表
     *
     * @param integer $userId
     * @param integer $offset
     * @param integer $limit
     * @return array
     */
    public function getUserGroups($userId, $offset = 0, $limit = 20)
    {
        $model = $this->groupUserModel;
        $groupMaps = (array) $model->dump([
            'user_id' => $userId,
            'is_quited' => 0,
            'is_deleted' => 0,
            'ORDER' => ['id' => 'ASC'],
            'LIMIT' => [$offset, $limit],
        ], [
            'group_id'
        ]);

        $groupIds = array_column($groupMaps, 'group_id');

        $groupModel = $this->groupModel;
        $groups = $groupModel->dump([
            'id' => $groupIds,
            'is_deleted' => 0,
        ], [
            'id',
            'user_id',
            'name',
            'desc',
            'cover',
            'type',
            'post_count',
            'user_count',
        ]);

        return [
            'rows' => $groups,
            'total' => $this->groupUserModel->count([
                'user_id' => $userId,
                'is_quited' => 0,
                'is_deleted' => 0,
            ]),
        ];
    }

    /**
     * 检测用户名是否已存在
     *
     * @param string $username
     * @return boolean
     */
    public function isExistUsername($username)
    {
        return $this->userModel->count([
            'username' => $username,
            'is_deleted' => 0,
        ]) ? true : false;
    }

    /**
     * 检测用户ID是否存在
     *
     * @param integer $userId
     * @return boolean
     */
    public function isExistUserId($userId)
    {
        return $this->userModel->count([
            'id' => $userId,
            'is_deleted' => 0,
        ]) ? true : false;
    }

    /**
     * 检测邮箱是否已存在
     *
     * @param string $email
     * @return boolean
     */
    public function isExistEmail($email)
    {
        return $this->userModel->count([
            'email' => $email,
            'is_deleted' => 0,
        ]) ? true : false;
    }

    /**
     * 检测用户是否已被封号
     *
     * @param UserModel $user
     * @return boolean
     */
    public function isBanned(UserModel $user)
    {
        return $user->is_banned ? true : false;
    }

    /**
     * 上传头像
     *
     * @param integer $userId
     * @param string $remoteUrl
     * @param string $localPath
     * @return boolean
     */
    public function updateAvatar($userId, $remoteUrl, $localPath)
    {
        // 从文件中直接上传至阿里云
        try {
            $ossClient = new OssClient(env('ALIYUN_ACCESS_KEY_ID'), env('ALIYUN_ACCESS_KEY_SECRET'), env('ALIYUN_OSS_ENDPOINT'));

            // 上传OSS
            $ossClient->uploadFile(env('ALIYUN_OSS_BUCKET'), $remoteUrl, $localPath);
            $savePath = 'https://'.env('ALIYUN_OSS_BUCKET_DOMAIN').'/'.$remoteUrl;

            if ($userId > 0) {
                // 更新用户头像
                $model = $this->userModel;
                if (empty($model->id) || $model->id != $userId) {
                    $model->load([
                        'id' => $userId,
                    ]);
                }
                if (!empty($model->getData())) {
                    $model->avatar = $savePath;
                    if ($model->update(['avatar']) == true) {
                        return true;
                    }
                }

                $this->_error = '头像更新失败';
                return false;
            }

            return $savePath;
        } catch (OssException $e) {
            app()->log()->error('Upload img fail.', ['error' => $e->getMessage()]);

            $this->_error = $e->getMessage();
            return false;
        }
    }

    /**
     * 发送短信验证码
     *
     * @param string $email
     * @return boolean
     */
    public function sendCaptcha($email)
    {
        // 一分钟内不可重复发送
        $this->captchaModel->load([
            'email' => $email,
            'created_at[>]' => date('Y-m-d H:i:s', time() - 60),
            'is_deleted' => 0,
        ]);
        if (!empty($this->captchaModel->getData())) {
            $this->_error = '发送请求过于频繁，请稍后再试';
            return false;
        }

        $captcha = rand(100000, 999999);
        
        // Send Email Captcha
        if (!$this->sendEmailCaptcha([
            'email' => $email,
            'captcha' => $captcha,
        ])) {
            return false;
        }

        $model = $this->resetModel('captchaModel')->captchaModel;
        $model->email = $email;
        $model->expired_at = date('Y-m-d H:i:s', CaptchaModel::EXPIRE_MINUTE * 60 + time());
        $model->captcha = $captcha;
        if ($model->save()) {
            return true;
        }

        $this->_error = '发送失败';
        return false;
    }

    /**
     * 校验邮箱验证码
     *
     * @param string $captcha
     * @param string $email
     * @return boolean
     */
    public function validateCaptcha($captcha, $email)
    {
        $model = $this->captchaModel;
        $model->load([
            'captcha' => $captcha,
            'email' => $email,
            'use_times[<]' => 3, // 每个验证码最多使用3次
            'is_deleted' => 0,
        ]);

        if (!empty($model->getData()) && $model->getData()['captcha'] == $captcha) {
            $model->use_times = $model->getData()['use_times'] + 1;
            $model->update(['use_times']);

            return true;
        }
        $this->_error = '验证码无效';
        return false;
    }

    /**
     * 检测用户名是否合法
     *
     * @param string $username
     * @return boolean
     */
    public function checkUsername($username)
    {
        if (strlen($username) < 4 || mb_strlen($username, 'utf-8') < 2) {
            $this->_error = '用户名太短了';
            return false;
        }
        if (mb_strlen($username, 'utf-8') > 12) {
            $this->_error = '用户名太长了';
            return false;
        }
        if (preg_match('/\s/', $username) || strpos($username, ' ')) {
            $this->_error = '用户名不允许存在空格';
            return false;
        }
        if (is_numeric(substr($username, 0, 1)) || substr($username, 0, 1) == "_") {
            $this->_error = '用户名不能以数字和下划线开头';
            return false;
        }
        if (!preg_match('/^[_a-zA-Z0-9]+$/u', $username)) {
            $this->_error = '用户名只能包含英文、数字及下划线';
            return false;
        }

        return true;
    }

    /**
     * 检测签名是否合法
     *
     * @param string $signature
     * @return boolean
     */
    public function checkSignature($signature)
    {
        if (mb_strlen($signature, 'utf-8') > 32) {
            $this->_error = '签名太长了';
            return false;
        }

        return true;
    }

    /**
     * 关注用户
     *
     * @param integer $userId
     * @param integer $attentionedId
     * @return integer
     */
    public function attend($userId, $attentionedUserId)
    {
        $this->userAttentionModel->load([
            'user_id' => $userId,
            'attentioned_user_id' => $attentionedUserId,
            'is_deleted'=> 0
        ]);
      
        if (!empty($this->userAttentionModel->getData())) {
            if ($this->userAttentionModel->delete()) {
                return -1;
            };
            return 0;
        } else {
            $model = $this->userAttentionModel;
            $model->user_id = $userId;
            $model->attentioned_user_id = $attentionedUserId;
            if ($model->save()) {
                return 1;
            }
            return 0;
        }
    }

    /**
     * 获取推荐用户列表
     *
     * @param integer $num
     * @param integer $userId
     * @return array
     */
    public function recommendList($num, $userId = 0)
    {
        // TODO 用户数据量增长后，需要优化推荐算法

        // 批量去100条数据
        $users = (array) $this->userModel->dump([
            'id[!]' => $userId,
            'is_deleted' => 0,
            'ORDER' => \Medoo\Medoo::raw('RAND()'),
            'LIMIT' => [0, 100]
        ], [
            'id',
            'nickname',
            'username',
            'avatar'
        ]);

        // 重新随机数据
        shuffle($users);

        $users = array_slice($users, 0, $num);
        $userIds = array_column($users, 'id');

        $attentions = $this->userAttentionModel->dump([
            'user_id' => $userId,
            'attentioned_user_id' => $userIds,
            'is_deleted' => 0,
        ]);
        $attentionUsers = array_column($attentions, 'attentioned_user_id');

        foreach ($users as &$user) {
            // 未关注
            $user['is_attentioned'] = 0;
            if ($userId > 0 && in_array($user['id'], $attentionUsers)) {
                $user['is_attentioned'] = 1;
            }
        }

        return $users;
    }

    /**
     * 获取用户列表
     *
     * @param array $conditions
     * @param integer $limit
     * @param integer $offset
     * @return array
     */
    public function list(array $conditions, $limit = 0, $offset = 20)
    {
        $users = $this->userModel->dump(array_merge($conditions, [
            'ORDER' => ['id' => 'DESC'],
            'LIMIT' => [$limit, $offset]
        ]), [
            'id',
            'username',
            'email',
            'avatar',
            'signature',
            'role',
            'is_banned',
            'created_at',
            'updated_at'
        ]);

        foreach ($users as &$user) {
            $user['is_banned'] = $user['is_banned'] == 1;
        }

        return [
            'rows' => $users,
            'total' => $this->userModel->count($conditions)
        ];
    }

    /**
     * 是否是超级管理员
     *
     * @param integer $userId
     * @return boolean
     */
    public function isSuperManager($userId)
    {
        $this->userModel->load([
            'id' => $userId,
            'is_deleted' => 0,
        ]);
        $user = $this->userModel->getData();

        if (!empty($user)) {
            return $user['role'] == 99;
        }

        return false;
    }

    /**
     * 根据头像ID获取头像
     *
     * @param integer $avatarId
     * @return string
     */
    public function getAvatarByAvatarId($avatarId)
    {
        $this->attachmentModel->load([
            'id' => $avatarId,
            'is_deleted' => 0,
        ]);
        $data = $this->attachmentModel->getData();

        return !empty($data) ? $data['content'] : '';
    }

    /**
     * 发送验证码
     *
     * @param array $content
     * @return boolean
     */
    private function sendEmailCaptcha(array $content)
    {
        try {
            $return = (new Mailer())
            ->setServer(env('SMTP_HOST'), env('SMTP_PORT'))
            ->setAuth(env('SMTP_USER'), env('SMTP_PASSWORD'))
            ->setFrom('ROCBOSS', env('SMTP_USER'))
            ->addTo($content['email'], $content['email'])
            ->setSubject(env('APP_NAME', 'ROCBOSS').' 邮箱验证码')
            ->setBody('您的验证码为 '.$content['captcha'].'，有效期'.CaptchaModel::EXPIRE_MINUTE.'分钟。（如非本人操作请忽略此邮件）')
            ->send();

            if (!$return) {
                throw new \Exception('SMTP邮件发送失败');
            }

            return true;
        } catch (\Exception $e) {
            app()->log()->error('500 request sending email captcha error.');

            $this->_error = $e->getMessage();
            return false;
        }
    }

    /**
     * 用户修改密码
     *
     * @param integer $userId
     * @param string $oldPassword
     * @param string $newPassword
     * @return boolean
     */
    public function changePassword($userId, $oldPassword, $newPassword)
    {
        $newPassword = password_hash($newPassword, PASSWORD_DEFAULT);

       
        $this->userModel->load([
            'id' => $userId,
            'is_deleted' => 0
        ]);
        $userModel =  $this->userModel;
        $userData = $userModel->getData();
        if (empty($userData)) {
            $this->_error = '用户不存在';
            return false;
        }
        $userPassword = isset($userData['password']) ? $userData['password'] : '';
        // 兼容旧版密码加密逻辑
        if (!password_verify($oldPassword, $userPassword)  || (strlen($userPassword) == 32 && md5($password) != $userPassword)) {
            $this->_error = '旧密码不正确';
            return false;
        }
        // 修改密码
        $userModel->password = $newPassword;
        if ($userModel->update(['password'])) {
            return true;
        }
        return false;
    }

    /**
     * 修改用户手机号
     *
     * @param integer $userId
     * @param integer $phone
     * @param integer $countryCode
     * @return boolean
     */
    public function changePhone($userId, $phone, $countryCode)
    {
        $this->userModel->load([
            'id' => $userId,
            'is_deleted' => 0
        ]);
        $user = $this->userModel->getData();
        if (empty($user)) {
            $this->_error = '用户不存在';
            return false;
        }
        $this->userModel->phone = $phone;
        $this->userModel->country_code = $countryCode;
        if (!$this->userModel->update(['phone', 'country_code'])) {
            $this->_error = '修改手机失败';
            return false;
        };
        return true;
    }

    /**
     * 用户加入，移除黑名单
     *
     * @param integer $userId
     * @param integer $aimUserId
     * @return integer
     */
    public function operateBlacklist($userId, $aimUserId)
    {
        $this->userBlackListModel->load([
            'user_id' => $userId,
            'aim_user_id' => $aimUserId,
            'is_deleted' => 0
        ]);

        if (!empty($this->userBlackListModel->getData())) {
            if ($this->userBlackListModel->delete()) {
                return -1;
            }
            return 0;
        }

        $model = new UserBlackListModel();
        $model->user_id = $userId;
        $model->aim_user_id = $aimUserId;
        if ($model->save()) {
            return 1;
        }
        return 0;
    }
}
