<?php
namespace service;

use Gregwar\Captcha\CaptchaBuilder;

/**
 * UtilService
 * @author ROC <i@rocs.me>
 */
class UtilService extends Service
{
    const CAPTCHA_EXPIRE = 600;

    /**
     * 获取验证码
     *
     * @return array
     */
    public function getImageCaptcha()
    {
        $builder = new CaptchaBuilder(4);
        $builder->setBackgroundColor(255, 255, 255);
        $builder->build();

        $token = md5(\guid());
        app()->redis()->setex('captcha:'.$token, self::CAPTCHA_EXPIRE, $builder->getPhrase());

        return [
            'captchaToken' => $token,
            'captchaImage' => $builder->inline(),
        ];
    }

    /**
     * 检测图形验证码
     *
     * @param string $captchaToken
     * @param string $captchaCode
     * @return boolean
     */
    public function validateImageCaptcha($captchaToken, $captchaCode)
    {
        $captchaData = app()->redis()->get('captcha:'.$captchaToken);

        if (!empty($captchaData) && $captchaData == $captchaCode) {
            // 清除验证码
            app()->redis()->del('captcha:'.$captchaToken);
            return true;
        }

        return false;
    }

    /**
     * 私钥生成签名字符串
     *
     * @param array $params
     * @param string $rsaPrivateKey
     * @return mixed
     */
    public function generateSignWithRsa(array $params, $rsaPrivateKey)
    {
        if (empty($rsaPrivateKey) || empty($params)) {
            $this->_error = '参数和私钥不能为空';
            return false;
        }

        if (!function_exists('openssl_pkey_get_private') || !function_exists('openssl_sign')) {
            $this->_error = 'openssl扩展不存在';
            return false;
        }

        try {
            $privateKey = openssl_pkey_get_private($rsaPrivateKey);
            if (isset($params['sign'])) {
                unset($params['sign']);
            }

            ksort($params); // 按字母升序排序

            $parts = [];
            foreach ($params as $k => $v) {
                $parts[] = $k . '=' . $v;
            }

            $plaintext = implode('&', $parts);
            openssl_sign($plaintext, $sign, $privateKey);
            openssl_free_key($privateKey);
        } catch (\Exception $e) {
            $this->_error = '私钥不合法';
            return false;
        }

        return base64_encode($sign);
    }

    /**
     * 公钥校验签名
     *
     * @param array $params
     * @param string $rsaPublicKey
     * @return boolean
     */
    public function checkSignWithRsa(array $params, $rsaPublicKey)
    {
        if (!isset($params['sign']) || empty($params) || empty($rsaPublicKey)) {
            return false;
        }

        if (!function_exists('openssl_pkey_get_public') || !function_exists('openssl_verify')) {
            $this->_error = 'openssl扩展不存在';
            return false;
        }

        $sign = $params['sign'];
        unset($params['sign']);

        if (empty($params)) {
            return false;
        }

        // 按字母升序排序
        ksort($params);

        $parts = [];
        foreach ($params as $k => $v) {
            $parts[] = $k . '=' . $v;
        }
        $plaintext = implode('&', $parts);
        $sign = base64_decode($sign);

        try {
            $publicKey = openssl_pkey_get_public($rsaPublicKey);
            $result = (bool) openssl_verify($plaintext, $sign, $publicKey);
            openssl_free_key($publicKey);
        } catch (\Exception $e) {
            return false;
        }

        return $result;
    }
}
