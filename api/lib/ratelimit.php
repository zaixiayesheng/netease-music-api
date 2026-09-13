<?php
/**
 * 限流：每个 IP 一个计数器，存在 cache/ratelimit/ 下，
 * 文件名是 IP 的 md5，不存原始 IP。
 */

declare(strict_types=1);

require_once LIB_DIR . '/filelock.php';
require_once LIB_DIR . '/net.php';

function rate_guard(string $bucket, int $windowSec, int $max): void
{
    $ip = client_ip((bool)(api_config()['TRUST_PROXY'] ?? false));
    $file = CACHE_DIR . '/ratelimit/' . $bucket . '_' . md5($ip) . '.json';
    $now = time();
    $hit = false;
    @mkdir(dirname($file), 0775, true);
    locked_update($file, function (array $rec) use ($now, $windowSec, $max, &$hit): array {
        if (!isset($rec['w']) || $now - $rec['w'] >= $windowSec) {
            $rec = ['w' => $now, 'c' => 0];
        }
        if ($rec['c'] >= $max) {
            $hit = true;
            return $rec;
        }
        $rec['c'] += 1;
        return $rec;
    }, []);
    if ($hit) {
        api_err('rate_limited', '请求过于频繁，请稍后再试', 429);
    }
}
