# netease-music-api

网易云音乐 API 代理

> English version: [README.en.md](README.en.md)

## 介绍

一个网易云音乐的 REST API 代理：通过 weapi 加密直接请求官方接口，把歌单、歌曲信息、歌词、音频直链和搜索整理成统一的 JSON 返回，可给网页、小程序等前端调用。

## 快速开始

```bash
cp config.example.php config.php
php -S 127.0.0.1:8081 index.php

curl "http://127.0.0.1:8081/api/music/v1/playlist?id=7452421335"
```

PHP 8.0 以上，扩展 curl、openssl、mbstring；bcmath 可选（没有会自动降级）。

## 接口一览

| 接口 | 说明 |
|---|---|
| `GET /api/music/v1/playlist` | 歌单 |
| `GET /api/music/v1/song` | 单曲信息 |
| `GET /api/music/v1/lyric` | 歌词 |
| `GET /api/music/v1/url` | 音频直链（302） |
| `GET /api/music/v1/search` | 搜索 |
| `GET /api/music/v1/health` | 状态检查 |

## 接口说明

### 歌单 `GET /api/music/v1/playlist`

| 参数 | 必填 | 类型 | 默认 | 说明 |
|---|---|---|---|---|
| `id` | 是 | 字符串 | 无 | 歌单 ID，只能数字，否则返回 400 |

返回 `data.items`：歌曲数组，字段见「歌曲字段」。服务端缓存 10 分钟。

```bash
curl "http://127.0.0.1:8081/api/music/v1/playlist?id=7452421335"
```

```json
{ "ok": true, "data": { "items": [ { "id": "2155422573", "name": "使一颗心免于哀伤", "artist": "知更鸟/HOYO-MiX/Chevy", "album": "崩坏星穹铁道-空气蛹 INSIDE", "duration": 202.163, "pic": "https://...", "url": "/api/music/v1/url?id=2155422573", "lrc": "/api/music/v1/lyric?id=2155422573" } ] } }
```

### 单曲 `GET /api/music/v1/song`

| 参数 | 必填 | 类型 | 默认 | 说明 |
|---|---|---|---|---|
| `id` | 是 | 字符串 | 无 | 歌曲 ID，只能数字，否则返回 400 |

返回 `data.items`（通常一条）。歌曲不存在返回 404。

```bash
curl "http://127.0.0.1:8081/api/music/v1/song?id=2155422573"
```

### 歌词 `GET /api/music/v1/lyric`

| 参数 | 必填 | 类型 | 默认 | 说明 |
|---|---|---|---|---|
| `id` | 是 | 字符串 | 无 | 歌曲 ID，只能数字，否则返回 400 |
| `dwrc` | 否 | 布尔 | `false` | `true` 返回逐字歌词（YRC），`false` 返回普通歌词（LRC） |
| `trlrc` | 否 | 布尔 | `false` | `true` 附带翻译 |

返回 `data`：

| 字段 | 说明 |
|---|---|
| `lyric` | 歌词内容 |
| `tlyric` | 翻译内容（未请求或没有时为空字符串） |
| `type` | `yrc` 逐字 / `lrc` 行 |

`dwrc=true` 但歌曲没有逐字数据时，自动回退普通歌词（`type` 为 `lrc`）。服务端缓存 7 天。

```bash
curl "http://127.0.0.1:8081/api/music/v1/lyric?id=2155422573&dwrc=true&trlrc=true"
```

```json
{ "ok": true, "data": { "lyric": "[00:01.000] 作词 : 黑金雨", "tlyric": "", "type": "yrc" } }
```

### 音频直链 `GET /api/music/v1/url`

| 参数 | 必填 | 类型 | 默认 | 说明 |
|---|---|---|---|---|
| `id` | 是 | 字符串 | 无 | 歌曲 ID，只能数字，否则返回 400 |
| `br` | 否 | 字符串 | `320` | 码率，只能是 `128` / `192` / `320`，其它值按 `320` 处理 |

302 跳转到音频地址；音频不存在或需要会员返回 404。

```bash
curl -I "http://127.0.0.1:8081/api/music/v1/url?id=2155422573&br=320"
```

```
HTTP/1.1 302 Found
Location: http://m701.music.126.net/...
```

### 搜索 `GET /api/music/v1/search`

| 参数 | 必填 | 类型 | 默认 | 说明 |
|---|---|---|---|---|
| `keyword` | 是 | 字符串 | 无 | 关键词，最长 64 字，超长返回 400 |
| `limit` | 否 | 数字 | `20` | 每页条数，1–50，超出按边界取 |
| `page` | 否 | 数字 | `1` | 页码 |

返回 `data.items`：歌曲数组，字段见「歌曲字段」。

```bash
curl "http://127.0.0.1:8081/api/music/v1/search?keyword=星穹铁道&limit=10&page=1"
```

### 状态检查 `GET /api/music/v1/health`

无参数。

```json
{ "ok": true, "data": { "ok": true, "service": "music", "version": "v1", "time": 1789315887 } }
```

## 歌曲字段

`data.items` 每条：

```json
{
  "id": "2155422573",
  "name": "使一颗心免于哀伤",
  "artist": "知更鸟/HOYO-MiX/Chevy",
  "album": "崩坏星穹铁道-空气蛹 INSIDE",
  "duration": 202.163,
  "pic": "https://p3.music.126.net/...",
  "url": "/api/music/v1/url?id=2155422573",
  "lrc": "/api/music/v1/lyric?id=2155422573"
}
```

- `duration` 单位秒
- `pic` 封面直链，可直接用于 `<img>`
- `url`、`lrc` 指向本服务的接口，可直接请求

## 返回格式

成功：

```json
{ "ok": true, "data": ... }
```

失败：

```json
{ "ok": false, "code": "...", "msg": "..." }
```

| 错误码 | HTTP 状态 | 含义 |
|---|---|---|
| `invalid_param` | 400 | 参数缺失或不合法 |
| `method_not_allowed` | 405 | 请求方法不对（接口只接受 GET） |
| `not_found` | 404 | 接口不存在 / 歌曲不存在 / 音频不存在 |
| `rate_limited` | 429 | 触发限流 |
| `upstream_unavailable` | 502 | 网易云上游暂时不可用 |
| `internal_error` | 500 | 服务内部错误 |

## 限流

每个 IP 每分钟：

| 接口 | 次数 |
|---|---|
| playlist | 30 |
| search | 20 |
| song / lyric / url | 60 |

超出返回 429。

## 部署

### 宝塔面板

站点根目录指向本仓库，PHP 版本选 8.0 以上。「伪静态」框里填：

```
try_files $uri $uri/ /index.php?$query_string;
```

### 配置文件

nginx 配置文件里加：

```
location ^~ /config.php { return 404; }
location ^~ /cache/ { return 404; }
```

### Apache

`.htaccess`（放在站点根目录）：

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]

<Files "config.php">
  Require all denied
</Files>
```

## 配置

`config.php`（复制自 `config.example.php`）：

| 键 | 说明 |
|---|---|
| `API_ALLOWED_ORIGINS` | 允许跨域的来源列表，如 `['https://example.com']`；留空不限制 |
| `TRUST_PROXY` | 放在反代（CDN 等）后面时设为 `true` |
