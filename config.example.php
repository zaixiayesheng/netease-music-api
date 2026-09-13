<?php
/**
 * 音乐 API 服务配置（复制为 config.php 使用；config.php 不入库）
 */
return [
    // API 跨域来源白名单（可选，如 ['https://example.com']；
    // 留空则允许所有来源（Access-Control-Allow-Origin: *））
    'API_ALLOWED_ORIGINS' => [],
    // 是否信任反向代理传入的 X-Forwarded-For（位于 nginx 等反代后设为 true，
    // 且反代必须用 proxy_set_header X-Forwarded-For $remote_addr 覆盖客户端伪造值）。
    // 默认 false：直接用 REMOTE_ADDR，防伪造 IP 绕过限流。
    'TRUST_PROXY' => false,
];
