<?php
namespace api;

use anerg\OAuth2\OAuth;
use GuzzleHttp\Client;

use BaseController;
use OSS\OssClient;
use OSS\Core\OssException;
use OSS\Core\OssUtil;
use service\UtilService;
use service\UserService;
use service\WhisperService;

/**
 * UserController
 * @author ROC <i@rocs.me>
 */
class UserController extends BaseController
{
    protected static $_checkSign = true;

    // 最少允许的私信字数
    const MIN_WHISPER_STR_COUNT = 3;

    /**
     * 检测用户名是否已存在
     *
     * @return array
     */
    protected function isExistUsername()
    {
        $query = app()->request()->query;
        $this->checkParams($query, ['username']);
        $params = $query->getData();

        $username = trim($params['username']);

        return [
            'code' => 0,
            'data' => [
                'username' => $username,
                'result' => (new UserService())->isExistUsername($username),
            ],
        ];
    }

    /**
     * 发送验证码
     *
     * @return array
     */
    protected function sendCaptcha()
    {
        $data = app()->request()->data;
        $this->checkParams($data, ['email', 'captchaCode', 'captchaToken']);
        $params = $data->getData();

        // 检测验证码
        if (!(new UtilService())->validateImageCaptcha($params['captchaToken'], $params['captchaCode'])) {
            return [
                'code' => 500,
                'msg' => '图形验证码不正确',
            ];
        }

        if (!preg_match("/^[a-z0-9]+([._\\-]*[a-z0-9])*@([a-z0-9]+[-a-z0-9]*[a-z0-9]+.){1,63}[a-z0-9]+$/", $params['email'])) {
            return [
                'code' => 500,
                'msg' => '邮箱格式不正确',
            ];
        }

        $service = new UserService();
        if ($service->sendCaptcha($params['email'])) {
            return [
                'code' => 0,
            ];
        }
        return [
            'code' => 500,
            'msg' => $service->_error,
        ];
    }

    /**
     * 用户注册
     *
     * @return array
     */
    protected function register()
    {
        $data = app()->request()->data;
        $this->checkParams($data, ['email', 'captcha', 'username', 'password', 'bindToken']);
        $params = $data->getData();

        $username = trim($params['username']);
        $password = trim($params['password']);

        $service = new UserService();
        // 检测用户名
        if (!$service->checkUsername($username)) {
            return [
                'code' => 500,
                'msg' => $service->_error,
            ];
        }
        // 密码长度检测
        if (!(strlen($password) >= 6 && strlen($password) <= 20)) {
            return [
                'code' => 500,
                'msg' => '密码长度不合法',
            ];
        }
        // 用户名重复性检测
        if ($service->isExistUsername($username)) {
            return [
                'code' => 500,
                'msg' => '用户名已存在',
            ];
        }
        // 手机号重复性检测
        if ($service->isExistEmail($params['email'])) {
            return [
                'code' => 500,
                'msg' => '邮箱已存在',
            ];
        }
        // 短信验证码检测
        if (!$service->validateCaptcha($params['captcha'], $params['email'])) {
            return [
                'code' => 500,
                'msg' => $service->_error,
            ];
        }
        // 创建用户
        if (!$service->createUser($username, $params['email'], $password)) {
            return [
                'code' => 500,
                'msg' => $service->_error,
            ];
        }

        // QQ OAuth绑定流程
        if (!empty($params['bindToken'])) {
            // 登录成功，Check是否可以绑定
            $userInfo = app()->redis()->get($params['bindToken']);
            if (!empty($userInfo)) {
                $bindResult = $service->bindQQOAuth(app()->get('register.user.id'), json_decode($userInfo, true), true);

                if (!$bindResult) {
                    return [
                        'code' => 500,
                        'msg' => $service->_error.'（已注册成功）',
                    ];
                }
            } else {
                return [
                    'code' => 401,
                    'msg' => 'Token已过期，请重新登录授权（已注册成功）',
                ];
            }
        }

        return [
            'code' => 0,
            'data' => [
                'token' => (string) \Auth::getToken(app()->get('register.user.id'), app()->get('register.user.claim_token')),
            ],
        ];
    }

    /**
     * 用户登录
     *
     * @return array
     */
    protected function login()
    {
        $data = app()->request()->data;
        $this->checkParams($data, ['account', 'password', 'captchaCode', 'captchaToken', 'bindToken']);
        $params = $data->getData();

        $type = 'username';
        if (preg_match("/^[a-z0-9]+([._\\-]*[a-z0-9])*@([a-z0-9]+[-a-z0-9]*[a-z0-9]+.){1,63}[a-z0-9]+$/", $params['account'])) {
            $type = 'email';
        }

        // 检测验证码
        if (!(new UtilService())->validateImageCaptcha($params['captchaToken'], $params['captchaCode'])) {
            return [
                'code' => 500,
                'msg' => '图形验证码不正确',
            ];
        }

        $service = new UserService();
        if ($service->doLogin($type, $params['account'], $params['password'])) {

            // QQ OAuth绑定流程
            if (!empty($params['bindToken'])) {
                // 登录成功，Check是否可以绑定
                $userInfo = app()->redis()->get($params['bindToken']);
                if (!empty($userInfo)) {
                    $bindResult = $service->bindQQOAuth(app()->get('user')['id'], json_decode($userInfo, true));

                    if (!$bindResult) {
                        return [
                            'code' => 500,
                            'msg' => $service->_error,
                        ];
                    }
                } else {
                    return [
                        'code' => 401,
                        'msg' => 'Token已过期，请重新授权',
                    ];
                }
            }

            return [
                'code' => 0,
                'data' => [
                    'token' => (string) \Auth::getToken(app()->get('user')['id'], app()->get('user')['claim_token']),
                ],
            ];
        }

        return [
            'code' => 500,
            'msg' => $service->_error,
        ];
    }

    /**
     * 获取QQ Oauth授权地址
     *
     * @return array
     */
    protected function oauthQQ()
    {
        return [
            'code' => 0,
            'data' => [
                'redirect_url' => OAuth::qq([
                    'app_id' => env('QQ_APP_ID'),
                    'app_secret' => env('QQ_APP_SECRET'),
                    'scope' => 'get_user_info',
                    'callback' => env('QQ_REDIRECT_URI'),
                ])->setDisplay('pc')->getRedirectUrl(),
            ],
        ];
    }

    /**
     * QQ Oauth授权检查
     *
     * @return array
     */
    protected function oauthQQProceed()
    {
        $data = app()->request()->data;
        $this->checkParams($data, ['code']);
        $params = $data->getData();

        // 请求授权
        $response = (new Client(['timeout' => 2.0]))->get('https://graph.qq.com/oauth2.0/token', [
            'query' => [
                'grant_type' => 'authorization_code',
                'client_id' => env('QQ_APP_ID'),
                'client_secret' => env('QQ_APP_SECRET'),
                'code' => $params['code'],
                'redirect_uri' => env('QQ_REDIRECT_URI'),
            ]
        ]);

        $result = (string) $response->getBody();

        parse_str($result, $returnParams);

        // 授权正确
        if (!empty($returnParams['access_token'])) {
            $userInfo = OAuth::qq([
                'app_id' => env('QQ_APP_ID'),
                'app_secret' => env('QQ_APP_SECRET'),
                'access_token' => $returnParams['access_token'],
                
            ])->userinfo();

            if (!empty($userInfo['openid'])) {
                // 查询是否存在
                $service = new UserService();
                $userDetail = $service->getUserInfoByQQOpenId($userInfo['openid']);

                if (!empty($userDetail)) {
                    // 存在绑定用户，则直接登录，返回Token

                    return [
                        'code' => 0,
                        'data' => [
                            'hasBind' => true,
                            'token' => (string) \Auth::getToken($userDetail['id'], $userDetail['claim_token']),
                        ],
                    ];
                } else {
                    // 不存在绑定用户，执行绑定流程
                    $key = 'QQOAuth:' . (new \EndyJasmi\Cuid())->cuid();

                    // 存入Redis（十分钟有效期）
                    app()->redis()->setex($key, 600, json_encode($userInfo));

                    return [
                        'code' => 0,
                        'data' => [
                            'hasBind' => false,
                            'bindToken' => $key,
                            'QQUserInfo' => $userInfo,
                        ],
                    ];
                }
            }
        }

        return [
            'code' => 500,
            'msg' => 'QQ授权失败或已失效',
            'data' => [
                'result' => $result,
            ],
        ];
    }

    /**
     * 获取其他用户信息
     *
     * @param string $username
     * @return array
     */
    protected function profile($username)
    {
        // 当前用户ID
        $userId = app()->get('uid');

        $service = new UserService();
        // 获取用户数据
        $user = $service->profile($username, $userId);
        if (!$user) {
            return [
                'code' => 500,
                'msg' => $service->_error,
            ];
        }

        return [
            'code' => 0,
            'data' => $user,
        ];
    }

    /**
     * 获取当前登录用户信息
     *
     * @return array
     */
    protected function info()
    {
        return [
            'code' => 0,
            'data' => [
                'user' => app()->get('user'),
            ],
        ];
    }

    /**
     * 获取当前登录用户资产记录
     *
     * @return array
     */
    protected function assetsRecord()
    {
        $query = app()->request()->query;
        $this->checkParams($query, ['page', 'page_size']);
        $params = $query->getData();
        
        $page = $params['page'] > 0 ? intval($params['page']) : 1;
        $pageSize = $params['page_size'] > 0 && $params['page_size'] <= self::MAX_PAGESIZE ? intval($params['page_size']) : 20;
        $userId = app()->get('uid');

        $service = new UserService();
        $records = $service->assetsRecords([
            'user_id' => $userId,
        ], ($page - 1) * $pageSize, $pageSize);

        return [
            'code' => 0,
            'msg' => 'success',
            'data' => $records,
        ];
    }

    /**
     * 关注用户
     *
     * @return array
     */
    protected function attend()
    {
        $userId = app()->get('uid');
        $data = app()->request()->data;
        $this->checkParams($data, ['attentioned_user_id']);
        $params = $data->getData();

        $attentionedUserId = $params['attentioned_user_id'];

        if ($userId === $attentionedUserId) {
            return [
                'code' => 500,
                'msg' => '不能关注自己'
            ];
        }

        $service = new UserService();
        if (!$service->isExistUserId($attentionedUserId)) {
            return [
                'code' => 500,
                'msg' => '用户不存在'
            ];
        }

        $return = $service->attend($userId, $attentionedUserId);
        if ($return === 0) {
            return [
                'code' => 500,
                'msg' => 'error'
            ];
        }
        return [
            'code' => 0,
            'msg' => 'success',
            'data' => $return,
        ];
    }

    /**
     * 用户私信
     *
     * @return array
     */
    protected function whisper()
    {
        $sendUserId = app()->get('uid');

        $data = app()->request()->data;
        $this->checkParams($data, ['receive_user_id', 'content']);
        $params = $data->getData();

        
        $receiveUserId = intval($params['receive_user_id']);
        $content = !empty($params['content']) ? trim($params['content']) : '';

        if (mb_strlen($content) < self::MIN_WHISPER_STR_COUNT) {
            return [
                'code' => 500,
                'msg' => '私信内容不能少于'.(self::MIN_WHISPER_STR_COUNT).'个字'
            ];
        }
        // 需要校验能不能发私信
        $whisperService = new WhisperService();
        if (!$whisperService->checkWhisperAuth($sendUserId, $receiveUserId)) {
            return [
                'code' => 500,
                'msg' => $whisperService->_error
            ];
        }
        $res = $whisperService->add($sendUserId, $receiveUserId, $content);
        if (!$res) {
            return [
                'code' => 500,
                'msg' => '发送私信失败'
            ];
        }
        return [
            'code' => 0,
            'msg' => 'success'
        ];
    }

    /**
     * 修改用户基本信息
     *
     * @return array
     */
    protected function changeBaseInfo()
    {
        // 获取当前用户ID
        $userId = app()->get('uid');

        $data = app()->request()->data;
        $this->checkParams($data, ['avatar_id', 'signature']);
        $params = $data->getData();

        $avatarId = intval($params['avatar_id']);
        $signature = trim($params['signature']);

        $service = new UserService();

        // 检测昵称 & 签名
        if (!$service->checkSignature($signature)) {
            return [
                'code' => 500,
                'msg' => $service->_error,
            ];
        }

        $changeArr = [
            'signature' => $signature,
        ];
        if ($avatarId > 0) {
            $avatar = $service->getAvatarByAvatarId($avatarId);
            if (!empty($avatar)) {
                $changeArr = array_merge($changeArr, ['avatar' => $avatar]);
            }
        }

        // 编辑用户数据
        $ret = $service->editUser($userId, $changeArr);
        if (!$ret) {
            return [
                'code' => 500,
                'msg' => $service->_error,
            ];
        }

        return [
            'code' => 0,
        ];
    }

    /**
     * 修改密码
     *
     * @return void
     */
    protected function changePassword()
    {
        $userId = app()->get('uid');

        $params = app()->request()->data;
        $this->checkParams($params, ['old_password', 'new_password']);

        $oldPassword = $params['old_password'];
        $newPassword = $params['new_password'];

        if (empty($params['old_password'])) {
            return [
                'code' => 500,
                'msg' => '旧密码不能为空'
             ];
        }
        if (empty($params['new_password'])) {
            return [
                'code' => 500,
                'msg' => '新密码不能为空'
             ];
        }
        if (!(strlen($newPassword) >= 6 && strlen($newPassword) <= 20)) {
            return [
                'code' => 500,
                'msg' => '密码长度不合法',
            ];
        }

        $userService = new UserService();
        $res = $userService->changePassword($userId, $oldPassword, $newPassword);
        if ($res) {
            return [
                'code' => 0,
                'msg' => 'success'
            ];
        }
        return [
            'code' => 500,
            'msg' => $userService->_error
        ];
    }
}
