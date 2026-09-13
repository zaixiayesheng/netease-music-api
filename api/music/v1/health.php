<?php
/**
 * GET /api/music/v1/health
 * 状态检查：服务是否存活。不暴露 PHP 版本。
 */

declare(strict_types=1);

api_method('GET');
rate_guard('health', 60, 30);

api_ok([
    'ok'      => true,
    'service' => 'music',
    'version' => 'v1',
    'time'    => time(),
]);
