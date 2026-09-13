<?php
/**
 * 网易云音乐 API 服务（netease-music-api），唯一入口
 *
 * 路由：/api/music/v1/{endpoint}，其它路径一律 404
 *   playlist 歌单 / song 单曲 / lyric 歌词 / url 音频直链(302) / search 搜索 / health 状态检查
 *
 * 公共处理：CORS（config.php 的 API_ALLOWED_ORIGINS）、OPTIONS 预检、
 * 接口白名单、统一错误 JSON（500 不暴露内部信息）
 */

declare(strict_types=1);

define('API_ROOT', __DIR__);
define('API_LIB_DIR', API_ROOT . '/api/lib');
define('LIB_DIR', API_ROOT . '/lib');
define('DATA_DIR', API_ROOT . '/data');
define('CACHE_DIR', API_ROOT . '/cache');

require_once API_LIB_DIR . '/response.php';
require_once API_LIB_DIR . '/request.php';
require_once API_LIB_DIR . '/ratelimit.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . ltrim($path, '/');

// ── 只服务 /api/music/v1 ──
if (!str_starts_with($path, '/api/music/v1/')) {
    api_cors();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    api_err('not_found', '接口不存在', 404);
}

api_cors();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 接口白名单：只允许列出的接口
$allowedEndpoints = ['playlist', 'song', 'lyric', 'url', 'search', 'health'];

$matched = preg_match('#^/api/music/v1/([a-z0-9-]+)$#', $path, $m);
if ($matched !== 1 || !in_array($m[1], $allowedEndpoints, true)) {
    api_err('not_found', '接口不存在', 404);
}

$file = API_ROOT . '/api/music/v1/' . $m[1] . '.php';
if (!is_file($file)) {
    api_err('not_found', '接口不存在', 404);
}

try {
    require $file;
} catch (Throwable $e) {
    error_log('[music.gateway] ' . $path . ' → ' . $e->getMessage());
    if (!headers_sent()) {
        api_err('internal_error', '服务器内部错误', 500);
    }
    exit;
}
exit;
