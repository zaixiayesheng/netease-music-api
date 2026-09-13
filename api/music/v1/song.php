<?php
/**
 * GET /api/music/v1/song?id={歌曲ID}
 * 单曲信息（统一前端格式）。
 */

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

api_method('GET');
rate_guard('music:song', 60, 60);

$id = param_id();
if ($id === '') api_err('invalid_param', '缺少 id');

try {
    $songs = fetch_song_details([(int)$id]);
    if (!$songs) {
        api_err('not_found', '未知歌曲', 404);
    }
    api_ok(['items' => array_map('normalize_song', $songs)]);
} catch (Throwable $e) {
    music_upstream_err($e);
}
