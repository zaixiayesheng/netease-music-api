<?php
/**
 * 网易云音乐模块共用代码（仅供同目录的接口文件使用）
 *   - weapi 加密（移植 metowolf/Meting v1.5.11，需 openssl，bcmath 可选）
 *   - 上游 HTTP（SSL 证书校验开启）
 *   - 歌曲字段整理 / 封面直链 / 302 跳转
 */

declare(strict_types=1);

const LRC_CACHE_TTL      = 604800; // 歌词 7 天
const PLAYLIST_CACHE_TTL = 600;    // 歌单 10 分钟

function redirect_out(string $url): never
{
    if ($url === '') {
        api_err('unavailable', '资源不存在或需要会员', 404);
    }
    header('HTTP/1.1 302 Found');
    header('Location: ' . $url);
    exit;
}

// ────────────────────────── 上游 HTTP ──────────────────────────

function netease_headers(): array
{
    return [
        'Referer'         => 'https://music.163.com/',
        'Cookie'          => 'appver=8.2.30; os=iPhone OS; osver=15.0; EVNSM=1.0.0; buildver=2206; channel=distribution; machineid=iPhone13.3',
        'User-Agent'      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 CloudMusic/0.1.1 NeteaseMusic/8.2.30',
        'X-Real-IP'       => long2ip(mt_rand(1884815360, 1884890111)),
        'Accept'          => '*/*',
        'Accept-Language' => 'zh-CN,zh;q=0.8,gl;q=0.6,zh-TW;q=0.4',
        'Connection'      => 'keep-alive',
        'Content-Type'    => 'application/x-www-form-urlencoded',
    ];
}

function http_post(string $url, array $body): string
{
    $headers = [];
    foreach (netease_headers() as $k => $v) {
        $headers[] = $k . ': ' . $v;
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => 1,
        CURLOPT_POSTFIELDS     => http_build_query($body),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING       => 'gzip',
        CURLOPT_SSL_VERIFYPEER => 1,   // 证书校验开启
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $err = curl_errno($ch);
    curl_close($ch);
    if ($raw === false || $err !== 0) {
        throw new RuntimeException('上游请求失败 (' . $err . ')');
    }
    return $raw;
}

// ────────────────────────── weapi 加密 ──────────────────────────

function pkcs7_pad(string $text, int $block = 16): string
{
    $pad = $block - (strlen($text) % $block);
    return $text . str_repeat(chr($pad), $pad);
}

/** 随机 16 进制串（用于随机 AES key） */
function get_random_hex(int $length): string
{
    return bin2hex(random_bytes($length / 2));
}

/** 16 进制大数 → 十进制字符串 */
function bchexdec(string $hex): string
{
    $dec = '0';
    $len = strlen($hex);
    for ($i = 1; $i <= $len; $i++) {
        $dec = bcadd($dec, bcmul((string)hexdec($hex[$i - 1]), bcpow('16', (string)($len - $i))));
    }
    return $dec;
}

/** 十进制字符串 → 16 进制 */
function bcdechex(string $dec): string
{
    $hex = '';
    do {
        $last = bcmod($dec, '16');
        $hex = dechex((int)$last) . $hex;
        $dec = bcdiv(bcsub($dec, $last), '16');
    } while (bccomp($dec, '0') > 0);
    return $hex;
}

function str2hex(string $string): string
{
    $hex = '';
    for ($i = 0; $i < strlen($string); $i++) {
        $hex .= substr('0' . dechex(ord($string[$i])), -2);
    }
    return $hex;
}

/**
 * 网易云 weapi 加密：两层 AES-128-CBC。有 bcmath 用随机 key + RSA 计算 encSecKey，
 * 没有则用固定 key。请求必须走 /weapi/ 路径（/api/ 会被上游拒绝）。
 */
function weapi(array $body): array
{
    $modulus = '157794750267131502212476817800345498121872783333389747424011531025366277535262539913701806290766479189477533597854989606803194253978660329941980786072432806427833685472618792592200595694346872951301770580765135349259590167490536138082469680638514416594216629258349130257685001248172188325316586707301643237607';
    $pubkey = '65537';
    $nonce = '0CoJUm6Qyw8W8jud';
    $vi = '0102030405060708';

    if (extension_loaded('bcmath')) {
        $skey = get_random_hex(16);
    } else {
        $skey = 'B3v3kH4vRPWRJFfH';
    }

    $body = json_encode($body, JSON_UNESCAPED_UNICODE);

    if (function_exists('openssl_encrypt')) {
        $body = pkcs7_pad($body);
        $body = openssl_encrypt($body, 'aes-128-cbc', $nonce, OPENSSL_RAW_DATA, $vi);
        $body = base64_encode($body);

        $body = pkcs7_pad($body);
        $body = openssl_encrypt($body, 'aes-128-cbc', $skey, OPENSSL_RAW_DATA, $vi);
        $body = base64_encode($body);
    }

    if (extension_loaded('bcmath')) {
        $skey = strrev(mb_convert_encoding($skey, 'UTF-8', 'ISO-8859-1'));
        $skey = bchexdec(str2hex($skey));
        $skey = bcpowmod($skey, $pubkey, $modulus);
        $skey = bcdechex($skey);
        $skey = str_pad($skey, 256, '0', STR_PAD_LEFT);
    } else {
        $skey = '85302b818aea19b68db899c25dac229412d9bba9b3fcfe4f714dc016bc1686fc446a08844b1f8327fd9cb623cc189be00c5a365ac835e93d4858ee66f43fdc59e32aaed3ef24f0675d70172ef688d376a4807228c55583fe5bac647d10ecef15220feef61477c28cae8406f6f9896ed329d6db9f88757e31848a6c2ce2f94308';
    }

    return [
        'params'    => $body,
        'encSecKey' => $skey,
    ];
}

function weapi_post(string $url, array $body): array
{
    $raw = http_post($url, weapi($body));
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('上游返回非 JSON');
    }
    return $data;
}

// ────────────────────────── 封面与字段整理 ──────────────────────────

function encrypt_id(string $id): string
{
    $magic = str_split('3go8&$8*3*3h0k(2)2');
    $chars = str_split($id);
    foreach ($chars as $i => $c) {
        $chars[$i] = chr(ord($c) ^ ord($magic[$i % count($magic)]));
    }
    $result = base64_encode(md5(implode('', $chars), true));
    return str_replace(['/', '+'], ['_', '-'], $result);
}

function pic_url(string $id, ?string $size = null): string
{
    $url = 'https://p3.music.126.net/' . encrypt_id($id) . '/' . $id . '.jpg';
    if ($size !== null && $size !== '') {
        $url .= '?param=' . $size . 'y' . $size;
    }
    return $url;
}

function fetch_song_details(array $ids): array
{
    $items = array_map(fn($id) => ['id' => (int)$id, 'v' => 0], $ids);
    $data = weapi_post('https://music.163.com/weapi/v3/song/detail/', ['c' => json_encode($items)]);
    return $data['songs'] ?? [];
}

/** 把网易云歌曲字段整理为统一格式（url/lrc 指向本服务 /api/music/v1/...） */
function normalize_song(array $s): array
{
    $id = (string)($s['id'] ?? '');
    $album = $s['al']['name'] ?? $s['album'] ?? '';
    // pic_str/pic 可能是数字（JSON 解码为 int），统一转字符串
    $picId = (string)($s['al']['pic_str'] ?? $s['al']['pic'] ?? '');
    if ($picId === '' && isset($s['al']['picUrl'])) {
        preg_match('/\/(\d+)\./', (string)$s['al']['picUrl'], $m);
        $picId = $m[1] ?? '';
    }
    $artists = [];
    foreach (($s['ar'] ?? []) as $vo) {
        $artists[] = $vo['name'];
    }
    return [
        'id'       => $id,
        'name'     => $s['name'] ?? '',
        'artist'   => implode('/', $artists),
        'album'    => (string)$album,
        'duration' => isset($s['dt']) ? (int)$s['dt'] / 1000 : 0,
        'pic'      => pic_url($picId !== '' ? $picId : $id, '200'),
        'url'      => '/api/music/v1/url?id=' . $id,
        'lrc'      => '/api/music/v1/lyric?id=' . $id,
    ];
}

/** 上游异常：记录日志并返回 502，不暴露内部信息 */
function music_upstream_err(Throwable $e): never
{
    error_log('[music] ' . $e->getMessage());
    api_err('upstream_unavailable', '上游服务暂时不可用，请稍后重试', 502);
}
