<?php
/**
 * Batio Jobs Queue Entrance
 */
ini_set('default_socket_timeout', -1);

require __DIR__."/bootstrap/init.php";

app()->set('isJob', true);

Batio::bootstrap();

$error = false;
$redis = app()->redis(true);

echo date('Y-m-d H:i:s').' Start Queue Service.'.PHP_EOL;

while (true) {
    try {
        if ($error) {
            $error = false;
            $redis = app()->redis(true);
        }

        // 从队列中获取任务
        $job = $redis->brpop(env('APP_NAME').':QueueJobs', 3600);
        if (is_array($job) && count($job) === 2) {
            $job = json_decode($job[1], true);

            // 执行任务
            $controller = $job['controller'];
            $action = $job['action'];
            $params = $job['params'];

            $result = $controller::$action($params);
            if ($result === false) {
                // 重新推入队列
                $redis->rpush(env('APP_NAME').':QueueJobs', json_encode($job));
            }

            if (filter_var(env('DEBUG_MODE', false), FILTER_VALIDATE_BOOLEAN)) {
                // 直接输出结果
                print_r($result);
            }

            // 记录日志
            Batio::log('queueJob', true)->info('RunQueueJob', [
                'timestamp' => time(),
                'job' => $job,
                'result' => $result,
            ]);

            echo time()."\n";
        }
        unset($job);
    } catch (\Exception $e) {
        // 异常重连机制
        sleep(3);
        $error = true;
        // 记录日志
        Batio::log('queueJob', true)->info('JOB_REDIS_RECONNECTING', [
            'timestamp' => time(),
            'error' => $e->getMessage(),
        ]);
        echo "reconnect...\n";
    }
}
