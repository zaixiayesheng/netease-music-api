<?php
/**
 * GET /api/music/v1/lyric?id={歌曲ID}&dwrc=true&trlrc=true
 * 歌词（dwrc=true 逐字 YRC；trlrc=true 附带翻译）。7 天文件缓存。
 */

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

api_method('GET');
rate_guard('music:lyric', 60, 60);

$id = param_id();
if ($id === '') api_err('invalid_param', '缺少 id');
$dwrc = param_bool('dwrc');
$trlrc = param_bool('trlrc');

try {
    $cacheFile = CACHE_DIR . '/lyric/' . $id . ($dwrc ? '_yrc' : '') . '.json';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < LRC_CACHE_TTL) {
        $hit = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($hit) && !isset($hit['ok'])) {
            // 缓存里无翻译且本次需要翻译 → 绕过缓存重取一次
            if (!$trlrc || ($hit['tlyric'] ?? '') !== '') {
                api_ok($hit);
            }
        }
    }

    $data = weapi_post('https://music.163.com/weapi/song/lyric', [
        'id'        => $id,
        'os'        => 'pc',
        'lv'        => -1,
        'kv'        => -1,
        'tv'        => -1,
        'rv'        => -1,
        'yv'        => 1,           // 请求 YRC 逐字歌词
        'showRole'  => 'False',
        'cp'        => 'False',
        'e_r'       => 'False',
    ]);

    $yrc = $data['yrc']['lyric'] ?? '';
    $lrc = $data['lrc']['lyric'] ?? '';
    $tlyric = $data['tlyric']['lyric'] ?? '';

    $out = [
        'lyric'  => ($dwrc && $yrc !== '') ? $yrc : $lrc,
        'tlyric' => $tlyric,
        'type'   => ($dwrc && $yrc !== '') ? 'yrc' : 'lrc',
    ];

    @mkdir(dirname($cacheFile), 0775, true);
    @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE), LOCK_EX);
    api_ok($out);
} catch (Throwable $e) {
    music_upstream_err($e);
}
