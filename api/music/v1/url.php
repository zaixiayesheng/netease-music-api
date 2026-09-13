<?php
/**
 * GET /api/music/v1/url?id={歌曲ID}&br={码率}
 * 302 → 音频直链。br 白名单 128/192/320，默认 320。
 */

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

api_method('GET');
rate_guard('music:url', 60, 60);

$id = param_id();
if ($id === '') api_err('invalid_param', '缺少 id');

$br = param_str('br', '320', 4);
if (!in_array($br, ['128', '192', '320'], true)) {
    $br = '320'; // 码率白名单：防畸形上游参数
}

try {
    $data = weapi_post('https://music.163.com/weapi/song/enhance/player/url', [
        'ids' => [(int)$id],
        'br'  => (int)$br * 1000,
    ]);
    $item = $data['data'][0] ?? null;
    if (!$item) {
        redirect_out('');
    }
    $url = $item['url'] ?? '';
    if ($url === '' && isset($item['uf']['url'])) {
        $url = $item['uf']['url'];
    }
    redirect_out($url);
} catch (Throwable $e) {
    music_upstream_err($e);
}
