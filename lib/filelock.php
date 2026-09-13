<?php
/**
 * 文件读-改-写：持独占锁完成整个序列，防止 php-fpm 多进程并发时相互覆盖。
 * 用法：locked_update($file, fn(array $data) => $newData, $default)
 */

declare(strict_types=1);

function locked_update(string $file, callable $mutate, array $default = []): mixed
{
    $fp = @fopen($file, 'c+');
    if ($fp === false) {
        // 打不开文件（目录不可写等）：无锁直接修改
        return $mutate($default);
    }
    flock($fp, LOCK_EX);
    $raw = (string)stream_get_contents($fp);
    $data = json_decode($raw, true);
    $data = is_array($data) ? $data : $default;
    $result = $mutate($data);
    // mutate 必须返回修改后的数组，写回以返回值为准
    $write = is_array($result) ? $result : $data;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($write, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $result;
}
