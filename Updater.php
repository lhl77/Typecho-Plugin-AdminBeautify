<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
class AdminBeautify_Updater
{
    const GITHUB_REPO = 'lhl77/Typecho-Plugin-AdminBeautify';
    const GITHUB_API_RELEASES = 'https://api.github.com/repos/lhl77/Typecho-Plugin-AdminBeautify/releases/latest';
    const GITHUB_RELEASES_PAGE = 'https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases';
    const CURRENT_VERSION = '2.1.5';
    private $pluginDir;
    private $tmpDir;
    public function __construct()
    {
        $this->pluginDir = rtrim(dirname(__FILE__), '/\\');
        $this->tmpDir    = $this->pluginDir . '/tmp_update';
    }
    public function fetchLatestRelease()
    {
        $json = $this->httpGet(self::GITHUB_API_RELEASES);
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
    public function doUpdate($downloadUrl, $newVersion)
    {
        $details = array();
                $details[] = '正在下载 ' . $downloadUrl . ' ...';
        $zipContent = $this->httpGet($downloadUrl);
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
                $skipDirs = array('tmp_update', 'assets/compat');         $copied = $this->copyDir($sourceDir, $this->pluginDir, $skipDirs);
        $details[] = '已覆盖 ' . $copied . ' 个文件';
                @unlink($zipFile);
        $this->removeDir($extractDir);
        $details[] = '临时文件已清理';
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
    private function httpGet($url)
    {
        $opts = array(
            'http' => array(
                'method'  => 'GET',
                'timeout' => 30,
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
            CURLOPT_TIMEOUT        => 30,
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
}
