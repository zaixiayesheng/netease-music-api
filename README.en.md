# netease-music-api

NetEase Cloud Music API proxy

> 中文文档: [README.md](README.md)

## About

A REST API proxy for NetEase Cloud Music. It calls the official endpoints directly (weapi encryption) and returns playlists, song info, lyrics, audio URLs and search results as clean JSON for web pages, mini programs and other frontends.

## Quick start

```bash
cp config.example.php config.php
php -S 127.0.0.1:8081 index.php

curl "http://127.0.0.1:8081/api/music/v1/playlist?id=7452421335"
```

PHP 8.0+, extensions curl / openssl / mbstring; bcmath optional.

## Endpoints

| Endpoint | Description |
|---|---|
| `GET /api/music/v1/playlist` | Playlist |
| `GET /api/music/v1/song` | Song info |
| `GET /api/music/v1/lyric` | Lyrics |
| `GET /api/music/v1/url` | Audio URL (302) |
| `GET /api/music/v1/search` | Search |
| `GET /api/music/v1/health` | Status check |

## Details

### Playlist `GET /api/music/v1/playlist`

| Param | Required | Type | Default | Notes |
|---|---|---|---|---|
| `id` | yes | string | none | Playlist ID, digits only, otherwise 400 |

Returns `data.items`: song array, fields in Song fields. Cached 10 min.

```bash
curl "http://127.0.0.1:8081/api/music/v1/playlist?id=7452421335"
```

```json
{ "ok": true, "data": { "items": [ { "id": "2155422573", "name": "使一颗心免于哀伤", "artist": "知更鸟/HOYO-MiX/Chevy", "album": "崩坏星穹铁道-空气蛹 INSIDE", "duration": 202.163, "pic": "https://...", "url": "/api/music/v1/url?id=2155422573", "lrc": "/api/music/v1/lyric?id=2155422573" } ] } }
```

### Song `GET /api/music/v1/song`

| Param | Required | Type | Default | Notes |
|---|---|---|---|---|
| `id` | yes | string | none | Song ID, digits only, otherwise 400 |

Returns `data.items` (usually one item). Unknown song returns 404.

```bash
curl "http://127.0.0.1:8081/api/music/v1/song?id=2155422573"
```

### Lyrics `GET /api/music/v1/lyric`

| Param | Required | Type | Default | Notes |
|---|---|---|---|---|
| `id` | yes | string | none | Song ID, digits only, otherwise 400 |
| `dwrc` | no | boolean | `false` | `true` for word-by-word lyrics (YRC), `false` for line lyrics (LRC) |
| `trlrc` | no | boolean | `false` | `true` to include translation |

Returns `data`:

| Field | Notes |
|---|---|
| `lyric` | Lyrics text |
| `tlyric` | Translation (empty if not requested or none) |
| `type` | `yrc` word-by-word / `lrc` line |

If `dwrc=true` but no word-by-word data exists, falls back to line lyrics (`type` becomes `lrc`). Cached 7 days.

```bash
curl "http://127.0.0.1:8081/api/music/v1/lyric?id=2155422573&dwrc=true&trlrc=true"
```

```json
{ "ok": true, "data": { "lyric": "[00:01.000] 作词 : 黑金雨", "tlyric": "", "type": "yrc" } }
```

### Audio URL `GET /api/music/v1/url`

| Param | Required | Type | Default | Notes |
|---|---|---|---|---|
| `id` | yes | string | none | Song ID, digits only, otherwise 400 |
| `br` | no | string | `320` | Bitrate, only `128` / `192` / `320`, other values fall back to `320` |

302 redirect to the audio URL. Unavailable or VIP-only audio returns 404.

```bash
curl -I "http://127.0.0.1:8081/api/music/v1/url?id=2155422573&br=320"
```

```
HTTP/1.1 302 Found
Location: http://m701.music.126.net/...
```

### Search `GET /api/music/v1/search`

| Param | Required | Type | Default | Notes |
|---|---|---|---|---|
| `keyword` | yes | string | none | Keyword, max 64 chars, otherwise 400 |
| `limit` | no | number | `20` | Items per page, 1–50, clamped |
| `page` | no | number | `1` | Page number |

Returns `data.items`: song array, fields in Song fields.

```bash
curl "http://127.0.0.1:8081/api/music/v1/search?keyword=星穹铁道&limit=10&page=1"
```

### Status `GET /api/music/v1/health`

No params.

```json
{ "ok": true, "data": { "ok": true, "service": "music", "version": "v1", "time": 1789315887 } }
```

## Song fields

Each item in `data.items`:

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

- `duration` in seconds
- `pic` cover image URL, ready for `<img>`
- `url`, `lrc` point to this service, ready to request

## Response format

Success:

```json
{ "ok": true, "data": ... }
```

Failure:

```json
{ "ok": false, "code": "...", "msg": "..." }
```

| Code | HTTP status | Meaning |
|---|---|---|
| `invalid_param` | 400 | Missing or invalid parameter |
| `method_not_allowed` | 405 | Wrong method (endpoints accept GET only) |
| `not_found` | 404 | Unknown endpoint / song / audio |
| `rate_limited` | 429 | Rate limit reached |
| `upstream_unavailable` | 502 | NetEase upstream temporarily unavailable |
| `internal_error` | 500 | Internal error |

## Rate limits

Per IP per minute:

| Endpoint | Requests |
|---|---|
| playlist | 30 |
| search | 20 |
| song / lyric / url | 60 |

Exceeding returns 429.

## Deploy

### BT Panel

Point the site root to this repo and pick PHP 8.0+. Put the following in the rewrite box:

```
try_files $uri $uri/ /index.php?$query_string;
```

### Config file

Add to your nginx config file:

```
location ^~ /config.php { return 404; }
location ^~ /cache/ { return 404; }
```

### Apache

`.htaccess` (place in site root):

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]

<Files "config.php">
  Require all denied
</Files>
```

## Config

`config.php` (copy from `config.example.php`):

| Key | Notes |
|---|---|
| `API_ALLOWED_ORIGINS` | CORS origin allowlist, e.g. `['https://example.com']`; empty means no restriction |
| `TRUST_PROXY` | Set `true` behind a reverse proxy (CDN etc.) |
