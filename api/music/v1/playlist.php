<?php
/**
 * GET /api/music/v1/playlist?id={歌单ID}
 * 歌单。缓存 10 分钟。
 */

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

api_method('GET');
rate_guard('music:playlist', 60, 30);

$id = param_id();
if ($id === '') api_err('invalid_param', '缺少 id');

try {
    // 缓存键带格式版本：输出结构变更时递增，旧缓存自动失效
    $cacheFile = CACHE_DIR . '/playlist/' . $id . '_v2.json';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < PLAYLIST_CACHE_TTL) {
        $hit = json_decode((string)file_get_contents($cacheFile), true);
        // 新格式（无 ok 包裹）才回放，否则重新拉取
        if (is_array($hit) && !isset($hit['ok']) && isset($hit['items'])) {
            api_ok($hit);
        }
    }

    $data = weapi_post('https://music.163.com/weapi/v6/playlist/detail', [
        'id'     => $id,
        'offset' => '0',
        'total'  => 'True',
        'limit'  => '1000',
        'n'      => '1000',
    ]);
    $trackIds = $data['playlist']['trackIds'] ?? null;
    if (!is_array($trackIds)) {
        api_ok(['items' => []]);
    }
    $songs = [];
    foreach (array_chunk(array_column($trackIds, 'id'), 500) as $batch) {
        $songs = array_merge($songs, fetch_song_details($batch));
    }
    $out = ['items' => array_map('normalize_song', $songs)];
    @mkdir(dirname($cacheFile), 0775, true);
    @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    api_ok($out);
} catch (Throwable $e) {
    music_upstream_err($e);
}
