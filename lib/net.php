<?php
/**
 * IP 工具
 */

declare(strict_types=1);

/**
 * 客户端 IP。
 * X-Forwarded-For 可被伪造，仅当反代确实覆盖该头
 * （proxy_set_header X-Forwarded-For $remote_addr）且 config.php 的
 * TRUST_PROXY 为 true 时才使用；否则用 REMOTE_ADDR。
 */
function client_ip(bool $trustProxy = false): string
{
    if ($trustProxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $first = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}
