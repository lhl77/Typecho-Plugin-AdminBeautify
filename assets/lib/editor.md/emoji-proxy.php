<?php
/**
 * Local emoji proxy with multi-mirror fallback.
 * Usage: emoji-proxy.php?hex=1f600
 */

declare(strict_types=1);

$hex = isset($_GET['hex']) ? strtolower(trim((string) $_GET['hex'])) : '';
if ($hex === '' || !preg_match('/^[0-9a-f-]+$/', $hex)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Bad Request';
    exit;
}

$rootDir = __DIR__;
$cacheDir = $rootDir . '/emoji-cache';
$cacheFile = $cacheDir . '/' . $hex . '.png';

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

if (is_file($cacheFile)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=2592000');
    readfile($cacheFile);
    exit;
}

$mirrors = [
    'https://cdn.jsdelivr.net/gh/jdecked/twemoji@15.1.0/assets/72x72/%s.png',
    'https://fastly.jsdelivr.net/gh/jdecked/twemoji@15.1.0/assets/72x72/%s.png',
    'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/%s.png',
    'https://fastly.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/%s.png',
    'https://unpkg.com/twemoji@14.0.2/dist/72x72/%s.png'
];

$fetch = static function (string $url): string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AdminBeautify-EmojiProxy/1.1');
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp !== false && $code >= 200 && $code < 300) {
            return (string) $resp;
        }
    }

    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 15,
                'follow_location' => 1,
                'user_agent' => 'AdminBeautify-EmojiProxy/1.1'
            ]
        ]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp !== false) {
            return (string) $resp;
        }
    }

    return '';
};

$data = '';
foreach ($mirrors as $tpl) {
    $url = sprintf($tpl, rawurlencode($hex));
    $data = $fetch($url);
    if ($data !== '') {
        break;
    }
}

if ($data !== '') {
    @file_put_contents($cacheFile, $data, LOCK_EX);
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=2592000');
    echo $data;
    exit;
}

http_response_code(404);
header('Content-Type: image/png');
header('Cache-Control: no-store');
$fallback = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO8F7qkAAAAASUVORK5CYII=');
echo $fallback;
