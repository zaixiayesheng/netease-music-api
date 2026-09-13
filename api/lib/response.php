<?php
/**
 * 统一响应与 CORS（所有接口共用）
 *
 * 成功 { "ok": true,  "data": ... }
 * 失败 { "ok": false, "code": "错误码", "msg": "错误信息" }
 *
 * CORS 来源：config.php 的 API_ALLOWED_ORIGINS，未配置时允许所有来源
 */

declare(strict_types=1);

function api_config(): array
{
    static $config = null;
    if ($config === null) {
        $file = DATA_DIR . '/../config.php';
        $config = is_file($file) ? require $file : [];
    }
    return $config;
}

/** CORS 与通用响应头（入口文件在分发前调用） */
function api_cors(): void
{
    $config = api_config();
    $allowed = (array)($config['API_ALLOWED_ORIGINS'] ?? []);
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($allowed !== []) {
        if ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
    } else {
        header('Access-Control-Allow-Origin: *');
    }
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Content-Type-Options: nosniff');
}

function api_ok(mixed $data = null, int $http = 200): never
{
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_err(string $code, string $msg, int $http = 400): never
{
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'code' => $code, 'msg' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
