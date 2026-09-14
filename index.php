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

date_default_timezone_set('Asia/Shanghai'); // 限流窗口按北京时间

require_once API_LIB_DIR . '/response.php';
require_once API_LIB_DIR . '/request.php';
require_once API_LIB_DIR . '/ratelimit.php';

if (!extension_loaded('mbstring') || !extension_loaded('curl') || !extension_loaded('openssl')) {
    api_err('internal_error', '服务器缺少必要扩展（mbstring/curl/openssl）', 500);
}

/** 清理超过 maxAge 的缓存文件（歌词缓存按歌曲落盘，只靠 TTL 不删文件会无限增长） */
function music_gc_caches(string $dir, int $maxAge): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        if (time() - $file->getMTime() > $maxAge) {
            @unlink($file->getPathname());
        }
    }
}

// 每 100 次请求顺带清理一次 7 天前的缓存文件（音乐服务无 stats 端点，挂在入口计数）
$gcCounter = CACHE_DIR . '/.gc_counter.json';
@mkdir(CACHE_DIR, 0775, true);
locked_update($gcCounter, function (array $rec): array {
    $rec['c'] = (int)($rec['c'] ?? 0) + 1;
    if ($rec['c'] % 100 === 0) {
        music_gc_caches(CACHE_DIR, 7 * 86400);
    }
    return $rec;
}, []);

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
    error_log('[music.gateway] ' . preg_replace('/[\x00-\x1F\x7F]/u', ' ', $path) . ' → ' . $e->getMessage());
    if (!headers_sent()) {
        api_err('internal_error', '服务器内部错误', 500);
    }
    exit;
}
exit;
