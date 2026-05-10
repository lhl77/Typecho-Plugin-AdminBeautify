<?php
/**
 * 下载 Emoji 文件到本地
 * 支持 Twemoji 和 Github Emoji
 */

set_time_limit(300);
ini_set('max_execution_time', 300);

$basePath = __DIR__;
$twemojiDir = $basePath . '/emojis/twemoji';
$githubEmojiDir = $basePath . '/emojis/github-emoji';

// 确保目录存在
if (!is_dir($twemojiDir)) {
    mkdir($twemojiDir, 0755, true);
}
if (!is_dir($githubEmojiDir)) {
    mkdir($githubEmojiDir, 0755, true);
}

// 记录日志
$logLines = array();
$startTime = time();

function logMsg($msg) {
    global $logLines;
    $logLines[] = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $msg . "\n";
    if (!empty($GLOBALS['isHtml'])) {
        echo "<br/>";
    }
}

// 检测是否是 HTML 模式
$isHtml = (php_sapi_name() !== 'cli');

function downloadFile($url, $savePath) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300 && !empty($data)) {
        return file_put_contents($savePath, $data);
    }
    
    // 尝试 file_get_contents
    $ctx = stream_context_create(array(
        'http' => array(
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0'
        ),
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false
        )
    ));
    
    $data = @file_get_contents($url, false, $ctx);
    if ($data !== false && !empty($data)) {
        return file_put_contents($savePath, $data);
    }
    
    return false;
}

if ($isHtml) {
    echo "<html><head><meta charset='utf-8'><style>body{font-family:monospace;margin:10px;}pre{background:#f5f5f5;padding:10px;border-radius:4px;max-height:600px;overflow-y:auto;}</style></head><body>";
    echo "<h2>Emoji 文件下载器</h2>";
    echo "<pre>";
}

// 读取 Twemoji 列表
logMsg("正在下载 Twemoji 列表...");

$twemojiJsonUrl = 'https://cdn.jsdelivr.net/npm/twemoji@14/metadata.json';
$twemojiJson = @file_get_contents($twemojiJsonUrl);

if (!$twemojiJson) {
    // 使用备用源
    $twemojiJsonUrl = 'https://raw.githubusercontent.com/jdecked/twemoji/main/assets/72x72/metadata.json';
    $twemojiJson = @file_get_contents($twemojiJsonUrl);
}

$twemojiCount = 0;
$twemojiSkipped = 0;
$twemojiFailed = 0;

if ($twemojiJson) {
    $metadata = json_decode($twemojiJson, true);
    if (is_array($metadata)) {
        foreach ($metadata as $hexCode => $emojiData) {
            $filename = strtolower($hexCode) . '.png';
            $filePath = $twemojiDir . '/' . $filename;
            
            // 跳过已存在的文件
            if (file_exists($filePath) && filesize($filePath) > 100) {
                $twemojiSkipped++;
                continue;
            }
            
            $cdnUrl = 'https://cdn.jsdelivr.net/npm/twemoji@14/dist/72x72/' . $filename;
            
            if (downloadFile($cdnUrl, $filePath)) {
                $twemojiCount++;
                if ($twemojiCount % 100 == 0) {
                    logMsg("已下载 Twemoji: $twemojiCount 个");
                }
            } else {
                $twemojiFailed++;
            }
        }
        logMsg("✓ Twemoji 下载完成: 新增 $twemojiCount 个，跳过 $twemojiSkipped 个，失败 $twemojiFailed 个");
    }
} else {
    logMsg("✗ 无法获取 Twemoji 列表");
}

// 下载 Github Emoji
logMsg("正在下载 Github Emoji 列表...");

$githubEmojiUrl = 'https://api.github.com/emojis';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $githubEmojiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$githubEmojiJson = curl_exec($ch);
curl_close($ch);

$githubCount = 0;
$githubSkipped = 0;
$githubFailed = 0;

if ($githubEmojiJson) {
    $emojis = json_decode($githubEmojiJson, true);
    if (is_array($emojis)) {
        foreach ($emojis as $name => $url) {
            $filename = $name . '.png';
            $filePath = $githubEmojiDir . '/' . $filename;
            
            // 跳过已存在的文件
            if (file_exists($filePath) && filesize($filePath) > 100) {
                $githubSkipped++;
                continue;
            }
            
            if (downloadFile($url, $filePath)) {
                $githubCount++;
                if ($githubCount % 100 == 0) {
                    logMsg("已下载 Github Emoji: $githubCount 个");
                }
            } else {
                $githubFailed++;
            }
        }
        logMsg("✓ Github Emoji 下载完成: 新增 $githubCount 个，跳过 $githubSkipped 个，失败 $githubFailed 个");
    }
} else {
    logMsg("✗ 无法获取 Github Emoji 列表");
}

$endTime = time();
$duration = $endTime - $startTime;
logMsg("总耗时: " . $duration . " 秒");
logMsg("下载完成！");

if ($isHtml) {
    echo "</pre>";
    echo "</body></html>";
}
?>
