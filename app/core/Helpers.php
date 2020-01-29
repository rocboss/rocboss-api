<?php

if (!function_exists('app')) {
    /**
     * Get App instance
     *
     * @return mixed
     */
    function app()
    {
        return Flight::app();
    }
}

if (!function_exists('env')) {
    /**
     * Get ENV variable
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    function env($name, $default = null)
    {
        return getenv($name) ? : $default;
    }
}

if (!function_exists('guid')) {
    /**
    * 获取GUID
    * @method getGuid
    * @param  string  $rand [description]
    * @return string
    */
    function guid($rand = 'batio')
    {
        $charId = strtolower(md5(uniqid(mt_rand().$rand, true)));

        $hyphen = chr(45);// "-"
        $uuid = substr($charId, 0, 8).$hyphen
                .substr($charId, 8, 4).$hyphen
                .substr($charId, 12, 4).$hyphen
                .substr($charId, 16, 4).$hyphen
                .substr($charId, 20, 12);

        return $uuid;
    }
}

if (!function_exists('getAllHeader')) {
    /**
     * Get headers
     * @method getAllHeader
     * @return array
     */
    function getAllHeader()
    {
        if (!function_exists('getallheaders')) {
            $headers = [];

            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }

            return array_change_key_case($headers, CASE_LOWER);
        } else {
            return array_change_key_case(getallheaders(), CASE_LOWER);
        }
    }
}

if (!function_exists('encrypt')) {
    /**
     * 加密数据
     * @method encrypt
     * @param mixed $data
     * @param string $key
     * @return string
     */
    function encrypt($data, $key)
    {
        $data = serialize($data);
        $key = md5($key);
        $x  = 0;
        $len = strlen($data);
        $l  = strlen($key);
        $char = $str = '';
        for ($i = 0; $i < $len; $i++) {
            if ($x == $l) {
                $x = 0;
            }
            $char .= $key{$x};
            $x++;
        }
        for ($i = 0; $i < $len; $i++) {
            $str .= chr(ord($data{$i}) + (ord($char{$i})) % 256);
        }
        return base64_encode($str);
    }
}

if (!function_exists('decrypt')) {
    /**
     * 解密数据
     * @method decrypt
     * @param string $data
     * @param string $key
     * @return mixed
     */
    function decrypt($data, $key)
    {
        try {
            $key = md5($key);
            $x = 0;
            $data = base64_decode($data);
            $len = strlen($data);
            $l = strlen($key);
            $char = $str = '';
            for ($i = 0; $i < $len; $i++) {
                if ($x == $l) {
                    $x = 0;
                }
                $char .= substr($key, $x, 1);
                $x++;
            }
            for ($i = 0; $i < $len; $i++) {
                if (ord(substr($data, $i, 1)) < ord(substr($char, $i, 1))) {
                    $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
                } else {
                    $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
                }
            }
            return unserialize($str);
        } catch (\Exception $e) {
            return [];
        }
    }
}

if (!function_exists('extend')) {
    /**
     * 获取文件扩展名
     *
     * @param string $fileName
     * @return string
     */
    function extend($fileName)
    {
        $extend = pathinfo($fileName);
        $extend = strtolower($extend['extension']);
        return $extend;
    }
}

if (!function_exists('route')) {
    /**
     * Route
     * @method route
     * @param  mixed  $pattern
     * @param  mixed  $callback
     * @return Object
     */
    function route($pattern, $callback)
    {
        Flight::route($pattern, $callback);

        return Middleware::getInstance()->setCallback($callback);
    }
}

if (!function_exists('formatTime')) {
    /**
     * 格式化时间
     *
     * @param integer $time
     * @return void
     */
    function formatTime($time)
    {
        if ($time == 0) {
            return '下沉内容';
        }

        if (date('Y') != date('Y', $time)) {
            $rtime = date("Y-m-d H:i", $time);
        } else {
            $rtime = date("m-d H:i", $time);
        }
        
        $htime = date("H:i", $time);
        $time = time() - $time;
        if ($time < 60) {
            $str = '刚刚';
        } elseif ($time < 60 * 60) {
            $min = floor($time / 60);
            $str = $min.'分钟前';
        } elseif ($time < 60 * 60 * 24) {
            $h = floor($time / (60*60));
            $str = $h.'小时前';
        } elseif ($time < 60 * 60 * 24 * 3) {
            $d = floor($time / (60 * 60 * 24));
            if ($d == 1) {
                $str = '昨天 '.$htime;
            } else {
                $str = '前天 '.$htime;
            }
        } else {
            $str = $rtime;
        }
        return $str;
    }
}