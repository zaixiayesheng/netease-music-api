<?php
/**
 * API 参数校验：请求参数统一在这里取，带类型、长度、范围限制。
 */

declare(strict_types=1);

/** 请求方法白名单：不匹配返回 405 */
function api_method(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $methods, true)) {
        api_err('method_not_allowed', '请求方法不允许', 405);
    }
}

/** 字符串参数：trim + 长度上限 */
function param_str(string $key, string $default = '', int $maxLen = 200): string
{
    $v = trim((string)($_GET[$key] ?? $_POST[$key] ?? $default));
    if (mb_strlen($v) > $maxLen) {
        api_err('invalid_param', '参数 ' . $key . ' 过长', 400);
    }
    return $v;
}

/**
 * ID 参数：只接受数字（id 会用来拼缓存文件名）。
 */
function param_id(string $key = 'id', string $default = ''): string
{
    $v = (string)($_GET[$key] ?? $_POST[$key] ?? $default);
    if ($v !== '' && !ctype_digit($v)) {
        api_err('invalid_param', '非法 ' . $key, 400);
    }
    return $v;
}

/** 整数参数：限制在 [min, max] 区间 */
function param_int(string $key, int $default, int $min, int $max): int
{
    $v = (int)($_GET[$key] ?? $_POST[$key] ?? $default);
    return max(min($v, $max), $min);
}

/** 布尔参数（"true"/"1" 为真） */
function param_bool(string $key, bool $default = false): bool
{
    $v = (string)($_GET[$key] ?? $_POST[$key] ?? '');
    if ($v === '') return $default;
    return $v === 'true' || $v === '1';
}
