<?php
/**
 * GET /api/music/v1/search?keyword={关键词}&limit=&page=
 * 搜索。关键词 64 字上限；limit 1-50；page ≥1。
 */

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

api_method('GET');
rate_guard('music:search', 60, 20);

$keyword = param_str('keyword', '', 64);
if ($keyword === '') api_err('invalid_param', '请输入搜索关键词');
$limit = param_int('limit', 20, 1, 50);
$page = param_int('page', 1, 1, 1000);

try {
    $data = weapi_post('https://music.163.com/weapi/cloudsearch/pc', [
        's'      => $keyword,
        'type'   => 1,
        'limit'  => $limit,
        'offset' => ($page - 1) * $limit,
        'total'  => 'true',
    ]);
    $songs = $data['result']['songs'] ?? [];
    api_ok(['items' => array_map('normalize_song', $songs)]);
} catch (Throwable $e) {
    music_upstream_err($e);
}
