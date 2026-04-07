<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
class AdminBeautify_Updater
{
    const GITHUB_REPO = 'lhl77/Typecho-Plugin-AdminBeautify';
    const GITHUB_API_RELEASES = 'https://api.github.com/repos/lhl77/Typecho-Plugin-AdminBeautify/releases/latest';
    private static $GITHUB_MIRRORS = array(
        'https://gh1.lhl.one/',
    );
    const GITHUB_RELEASES_PAGE = 'https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases';
    const CURRENT_VERSION = '2.1.34';
    private $pluginDir;
    private $tmpDir;
    public function __construct()
    {
        $this->pluginDir = rtrim(dirname(__FILE__), '/\\');
        $this->tmpDir    = $this->pluginDir . '/tmp_update';
    }
    public function fetchLatestRelease()
    {
        $json = $this->httpGet(self::GITHUB_API_RELEASES, 5);
        if ($json === false) return false;
        $data = @json_decode($json, true);
        if (!is_array($data) || empty($data['tag_name'])) return false;
        $version    = ltrim($data['tag_name'], 'vV');
        $htmlUrl    = isset($data['html_url']) ? $data['html_url'] : self::GITHUB_RELEASES_PAGE;
        $body       = isset($data['body']) ? $data['body'] : '';
        $downloadUrl = '';
        if (!empty($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (isset($asset['browser_download_url']) && substr($asset['browser_download_url'], -4) === '.zip') {
                    $downloadUrl = $asset['browser_download_url'];
                    break;
                }
            }
        }
        if ($downloadUrl === '' && !empty($data['zipball_url'])) {
            $downloadUrl = $data['zipball_url'];
        }
        return array(
            'version'      => $version,
            'download_url' => $downloadUrl,
            'html_url'     => $htmlUrl,
            'body'         => $body,
        );
    }
    public static function compareVersion($a, $b)
    {
        $pa = array_map('intval', explode('.', ltrim((string)$a, 'vV')));
        $pb = array_map('intval', explode('.', ltrim((string)$b, 'vV')));
        $len = max(count($pa), count($pb));
        for ($i = 0; $i < $len; $i++) {
            $na = isset($pa[$i]) ? $pa[$i] : 0;
            $nb = isset($pb[$i]) ? $pb[$i] : 0;
            if ($na > $nb) return 1;
            if ($na < $nb) return -1;
        }
        return 0;
    }
    public static function canDirectUpdate($current, $remote)
    {
        $pc = array_map('intval', explode('.', ltrim((string)$current, 'vV')));
        $pr = array_map('intval', explode('.', ltrim((string)$remote, 'vV')));
        while (count($pc) < 3) $pc[] = 0;
        while (count($pr) < 3) $pr[] = 0;
        return ($pc[0] === $pr[0] && $pc[1] === $pr[1] && $pr[2] > $pc[2]);
    }
    public function doUpdateStreaming($downloadUrl, $newVersion, $emit)
    {
        call_user_func($emit, 'download_start', '正在连接下载服务器...', 0);
        $zipContent = $this->httpDownloadWithProgress(
            $downloadUrl,
            function ($dlNow, $dlTotal) use ($emit) {
                if ($dlTotal > 0) {
                    $pct     = min(99, (int) round($dlNow / $dlTotal * 100));
                    $nowKb   = round($dlNow  / 1024, 0);
                    $totalKb = round($dlTotal / 1024, 0);
                    call_user_func($emit, 'downloading',
                        "下载中 {$nowKb} KB / {$totalKb} KB", $pct);
                } else {
                    $nowKb = round($dlNow / 1024, 0);
                    call_user_func($emit, 'downloading', "下载中 {$nowKb} KB...", -1);
                }
            },
            90
        );
        if ($zipContent === false || strlen($zipContent) < 100) {
            call_user_func($emit, 'error', '下载失败，服务器无法访问 GitHub，请手动更新', -1);
            return false;
        }
        $sizeKb = round(strlen($zipContent) / 1024, 1);
        call_user_func($emit, 'downloaded', "下载完成（{$sizeKb} KB）", 100);
        if (!function_exists('zip_open') && !class_exists('ZipArchive')) {
            call_user_func($emit, 'error', '服务器未安装 PHP zip 扩展，无法自动解压，请手动下载更新', -1);
            return false;
        }
        call_user_func($emit, 'saving', '正在保存临时文件...', 0);
        if (!is_dir($this->tmpDir)) {
            @mkdir($this->tmpDir, 0755, true);
        }
        $zipFile = $this->tmpDir . '/update.zip';
        if (file_put_contents($zipFile, $zipContent) === false) {
            call_user_func($emit, 'error', '无法写入临时文件，请检查目录权限', -1);
            return false;
        }
        unset($zipContent);
        call_user_func($emit, 'extract_start', '正在解压安装包...', 0);
        $extractDir = $this->tmpDir . '/extracted';
        if (is_dir($extractDir)) $this->removeDir($extractDir);
        @mkdir($extractDir, 0755, true);
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            $res = $zip->open($zipFile);
            if ($res !== true) {
                $this->cleanup();
                call_user_func($emit, 'error', '无法打开 ZIP 文件（ZipArchive 错误 ' . $res . '）', -1);
                return false;
            }
            $total = $zip->numFiles;
            $step  = max(1, (int) ceil($total / 20));
            for ($i = 0; $i < $total; $i++) {
                $entryName = $zip->getNameIndex($i);
                $destPath  = $extractDir . '/' . $entryName;
                if (substr($entryName, -1) === '/') {
                    @mkdir($destPath, 0755, true);
                } else {
                    @mkdir(dirname($destPath), 0755, true);
                    $content = $zip->getFromIndex($i);
                    if ($content !== false) @file_put_contents($destPath, $content);
                }
                if ($i % $step === 0) {
                    $pct = min(99, (int) round($i / max(1, $total) * 100));
                    call_user_func($emit, 'extracting', "解压中 {$i}/{$total}", $pct);
                }
            }
            $zip->close();
        } else {
            $zh = zip_open($zipFile);
            if (!is_resource($zh)) {
                $this->cleanup();
                call_user_func($emit, 'error', '无法打开 ZIP 文件', -1);
                return false;
            }
            $i = 0;
            while ($entry = zip_read($zh)) {
                $entryName = zip_entry_name($entry);
                $destPath  = $extractDir . '/' . $entryName;
                if (substr($entryName, -1) === '/') {
                    @mkdir($destPath, 0755, true);
                } else {
                    @mkdir(dirname($destPath), 0755, true);
                    if (zip_entry_open($zh, $entry)) {
                        file_put_contents($destPath, zip_entry_read($entry, zip_entry_filesize($entry)));
                        zip_entry_close($entry);
                    }
                }
                $i++;
                if ($i % 10 === 0) {
                    call_user_func($emit, 'extracting', "解压中（已处理 {$i} 个文件）...", -1);
                }
            }
            zip_close($zh);
        }
        call_user_func($emit, 'extracted', '解压完成', 100);
        $sourceDir = $this->findPluginRoot($extractDir);
        if ($sourceDir === false) {
            $this->cleanup();
            call_user_func($emit, 'error', '在 ZIP 中找不到插件文件，请手动更新', -1);
            return false;
        }
        call_user_func($emit, 'backup', '正在备份当前版本...', 0);
        $backupDir = $this->tmpDir . '/backup_' . self::CURRENT_VERSION;
        if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
        $this->copyDir($this->pluginDir, $backupDir, array('tmp_update'));
        call_user_func($emit, 'backed_up', '备份完成（' . self::CURRENT_VERSION . '）', 100);
        call_user_func($emit, 'copy_start', '正在清理旧版本文件并写入新版本...', 0);
        $this->cleanPluginDir($sourceDir);
        $copied   = $this->copyDir($sourceDir, $this->pluginDir, array('tmp_update'));
        call_user_func($emit, 'copied', "已写入 {$copied} 个文件", 100);
        @unlink($zipFile);
        $this->removeDir($extractDir);
        $postUpdateData = array(
            'has_update'   => false,
            'current'      => $newVersion,
            'latest'       => $newVersion,
            'can_direct'   => false,
            'html_url'     => '',
            'download_url' => '',
            'body'         => '',
        );
        @file_put_contents(
            $this->tmpDir . '/.update_cache.json',
            json_encode(array('ts' => time(), 'data' => $postUpdateData), JSON_UNESCAPED_UNICODE)
        );
        @unlink($this->tmpDir . '/.update_lock');
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->pluginDir . '/Updater.php', true);
            @opcache_invalidate($this->pluginDir . '/Plugin.php',  true);
            @opcache_invalidate($this->pluginDir . '/Action.php',  true);
        }
        call_user_func($emit, 'done',
            '更新成功！已从 v' . self::CURRENT_VERSION . ' 更新至 v' . $newVersion . '，请刷新页面。',
            100
        );
        return true;
    }
    public function httpDownloadWithProgress($url, $progressCallback, $timeout = 60)
    {
        if (function_exists('curl_init')) {
            $urls = array($url);
            foreach (self::$GITHUB_MIRRORS as $mirror) {
                $urls[] = $mirror . $url;
            }
            foreach ($urls as $tryUrl) {
                $result = $this->curlDownloadWithProgress($tryUrl, $progressCallback, $timeout);
                if ($result !== false) return $result;
            }
            return false;
        }
        $result = $this->httpGetWithMirror($url, $timeout);
        if ($result !== false && is_callable($progressCallback)) {
            $len = strlen($result);
            call_user_func($progressCallback, $len, $len);
        }
        return $result;
    }
    private function curlDownloadWithProgress($url, $progressCallback, $timeout = 60)
    {
        $ch      = curl_init($url);
        $body    = '';
        $lastPct = -1;
        $progressFn = function () use ($progressCallback, &$lastPct) {
            $args    = func_get_args();
            $dlTotal = count($args) >= 5 ? $args[1] : $args[0];
            $dlNow   = count($args) >= 5 ? $args[2] : $args[1];
            if ($dlTotal > 0 && is_callable($progressCallback)) {
                $pct = (int) round($dlNow / $dlTotal * 100);
                if ($pct !== $lastPct) {
                    $lastPct = $pct;
                    call_user_func($progressCallback, (int) $dlNow, (int) $dlTotal);
                }
            } elseif ($dlNow > 0 && is_callable($progressCallback)) {
                call_user_func($progressCallback, (int) $dlNow, 0);
            }
            return 0;
        };
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER   => false,
            CURLOPT_TIMEOUT          => $timeout,
            CURLOPT_FOLLOWLOCATION   => true,
            CURLOPT_MAXREDIRS        => 5,
            CURLOPT_SSL_VERIFYPEER   => false,
            CURLOPT_SSL_VERIFYHOST   => false,
            CURLOPT_USERAGENT        => 'AdminBeautify-Updater/' . self::CURRENT_VERSION,
            CURLOPT_NOPROGRESS       => false,
            CURLOPT_PROGRESSFUNCTION => $progressFn,
            CURLOPT_WRITEFUNCTION    => function ($ch, $chunk) use (&$body) {
                $body .= $chunk;
                return strlen($chunk);
            },
        ));
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);
        if ($curlErr || $httpCode < 200 || $httpCode >= 400 || strlen($body) < 100) {
            return false;
        }
        return $body;
    }
    public function doUpdate($downloadUrl, $newVersion)
    {
        $details = array();
        $details[] = '正在下载 ' . $downloadUrl . ' ...';
        $zipContent = $this->httpGetWithMirror($downloadUrl, 60);
        if ($zipContent === false || strlen($zipContent) < 100) {
            return array('ok' => false, 'msg' => '下载失败，请检查服务器网络或手动更新', 'details' => $details);
        }
        $details[] = '下载完成，大小 ' . round(strlen($zipContent) / 1024, 1) . ' KB';
        if (!function_exists('zip_open') && !class_exists('ZipArchive')) {
            return array('ok' => false, 'msg' => '服务器未安装 PHP zip 扩展，无法自动解压，请手动下载更新', 'details' => $details);
        }
        if (!is_dir($this->tmpDir)) {
            @mkdir($this->tmpDir, 0755, true);
        }
        $zipFile = $this->tmpDir . '/update.zip';
        if (file_put_contents($zipFile, $zipContent) === false) {
            return array('ok' => false, 'msg' => '无法写入临时文件，请检查目录权限', 'details' => $details);
        }
        $details[] = '已保存临时 ZIP';
        $extractDir = $this->tmpDir . '/extracted';
        if (is_dir($extractDir)) $this->removeDir($extractDir);
        @mkdir($extractDir, 0755, true);
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            $res = $zip->open($zipFile);
            if ($res !== true) {
                $this->cleanup();
                return array('ok' => false, 'msg' => '无法打开 ZIP 文件（ZipArchive 错误 ' . $res . '）', 'details' => $details);
            }
            $zip->extractTo($extractDir);
            $zip->close();
        } else {
            $zh = zip_open($zipFile);
            if (!is_resource($zh)) {
                $this->cleanup();
                return array('ok' => false, 'msg' => '无法打开 ZIP 文件', 'details' => $details);
            }
            while ($entry = zip_read($zh)) {
                $entryName = zip_entry_name($entry);
                $destPath  = $extractDir . '/' . $entryName;
                if (substr($entryName, -1) === '/') {
                    @mkdir($destPath, 0755, true);
                } else {
                    @mkdir(dirname($destPath), 0755, true);
                    if (zip_entry_open($zh, $entry)) {
                        file_put_contents($destPath, zip_entry_read($entry, zip_entry_filesize($entry)));
                        zip_entry_close($entry);
                    }
                }
            }
            zip_close($zh);
        }
        $details[] = '解压完成';
        $sourceDir = $this->findPluginRoot($extractDir);
        if ($sourceDir === false) {
            $this->cleanup();
            return array('ok' => false, 'msg' => '在 ZIP 中找不到插件文件，请手动更新', 'details' => $details);
        }
        $details[] = '插件根目录：' . str_replace($extractDir, '', $sourceDir);
        $backupDir = $this->tmpDir . '/backup_' . self::CURRENT_VERSION;
        if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
        $this->copyDir($this->pluginDir, $backupDir, array('tmp_update'));
        $details[] = '已备份当前版本到 tmp_update/backup_' . self::CURRENT_VERSION;
        $this->cleanPluginDir($sourceDir);
        $copied = $this->copyDir($sourceDir, $this->pluginDir, array('tmp_update'));
        $details[] = '已写入 ' . $copied . ' 个文件';
        @unlink($zipFile);
        $this->removeDir($extractDir);
        $details[] = '临时文件已清理';
        $postUpdateData = array(
            'has_update'   => false,
            'current'      => $newVersion,
            'latest'       => $newVersion,
            'can_direct'   => false,
            'html_url'     => '',
            'download_url' => '',
            'body'         => '',
        );
        @file_put_contents(
            $this->tmpDir . '/.update_cache.json',
            json_encode(array('ts' => time(), 'data' => $postUpdateData), JSON_UNESCAPED_UNICODE)
        );
        @unlink($this->tmpDir . '/.update_lock');
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->pluginDir . '/Updater.php', true);
            @opcache_invalidate($this->pluginDir . '/Plugin.php',  true);
            @opcache_invalidate($this->pluginDir . '/Action.php',  true);
        }
        return array('ok' => true, 'msg' => '更新成功！已从 v' . self::CURRENT_VERSION . ' 更新至 v' . $newVersion . '，请刷新页面。', 'details' => $details);
    }
    private function findPluginRoot($dir)
    {
        if (file_exists($dir . '/Plugin.php')) return $dir;
        $items = @scandir($dir);
        if ($items) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $sub = $dir . '/' . $item;
                if (is_dir($sub) && file_exists($sub . '/Plugin.php')) return $sub;
            }
        }
        return false;
    }
    private function cleanPluginDir($sourceDir)
    {
        $pluginDir  = $this->pluginDir;
        $tmpRelName = 'tmp_update';
        $newCompatDir  = $sourceDir . '/assets/compat';
        $newCompatFiles = array();
        if (is_dir($newCompatDir)) {
            $items = @scandir($newCompatDir);
            if ($items) {
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    if (is_file($newCompatDir . '/' . $item)) {
                        $newCompatFiles[$item] = true;
                    }
                }
            }
        }
        $items = @scandir($pluginDir);
        if (!$items) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if ($item === $tmpRelName) continue;
            $path = $pluginDir . '/' . $item;
            if ($item === 'assets') {
                $this->cleanAssetsDir($path, $newCompatFiles);
                continue;
            }
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
    }
    private function cleanAssetsDir($assetsDir, $newCompatFiles)
    {
        if (!is_dir($assetsDir)) return;
        $items = @scandir($assetsDir);
        if (!$items) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $assetsDir . '/' . $item;
            if ($item === 'compat' && is_dir($path)) {
                $compatItems = @scandir($path);
                if ($compatItems) {
                    foreach ($compatItems as $ci) {
                        if ($ci === '.' || $ci === '..') continue;
                        $ciPath = $path . '/' . $ci;
                        if (is_file($ciPath) && isset($newCompatFiles[$ci])) {
                            @unlink($ciPath);
                        }
                    }
                }
                continue;
            }
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
    }
    private function copyDir($src, $dst, $skipDirs = array())
    {
        $count = 0;
        if (!is_dir($dst)) @mkdir($dst, 0755, true);
        $items = @scandir($src);
        if (!$items) return 0;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;
            $skipThis = false;
            foreach ($skipDirs as $skip) {
                $baseName = basename($skip);
                if ($item === $baseName) {
                    $skipThis = true;
                    break;
                }
            }
            if ($skipThis) continue;
            if (is_dir($srcPath)) {
                $count += $this->copyDir($srcPath, $dstPath, $skipDirs);
            } else {
                if (copy($srcPath, $dstPath)) $count++;
            }
        }
        return $count;
    }
    private function removeDir($dir)
    {
        if (!is_dir($dir)) return;
        $items = @scandir($dir);
        if ($items) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $path = $dir . '/' . $item;
                if (is_dir($path)) $this->removeDir($path);
                else @unlink($path);
            }
        }
        @rmdir($dir);
    }
    private function cleanup()
    {
        @unlink($this->tmpDir . '/update.zip');
        $extractDir = $this->tmpDir . '/extracted';
        if (is_dir($extractDir)) $this->removeDir($extractDir);
    }
    private function httpGet($url, $timeout = 30)
    {
        $opts = array(
            'http' => array(
                'method'  => 'GET',
                'timeout' => $timeout,
                'header'  => "User-Agent: AdminBeautify-Updater/" . self::CURRENT_VERSION . "\r\n"
                           . "Accept: application/vnd.github.v3+json\r\n",
                'follow_location' => 1,
            ),
            'ssl' => array(
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ),
        );
        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);
        if ($result !== false) return $result;
        if (!function_exists('curl_init')) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'AdminBeautify-Updater/' . self::CURRENT_VERSION,
            CURLOPT_HTTPHEADER     => array('Accept: application/vnd.github.v3+json'),
        ));
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($result !== false && $httpCode >= 200 && $httpCode < 400) ? $result : false;
    }
    private function httpGetWithMirror($url, $timeout = 30)
    {
        foreach (self::$GITHUB_MIRRORS as $mirror) {
            $mirroredUrl = $mirror . $url;
            $result = $this->httpGet($mirroredUrl, $timeout);
            if ($result !== false) return $result;
        }
        return false;
    }
}
