<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class AdminBeautify_Updater
{
    /** GitHub 仓库 */
    const GITHUB_REPO = 'lhl77/Typecho-Plugin-AdminBeautify';

    /** GitHub Releases API */
    const GITHUB_API_RELEASES = 'https://api.github.com/repos/lhl77/Typecho-Plugin-AdminBeautify/releases/latest';

    /**
     * GitHub 镜像代理列表（大陆可访问），按优先级排列。
     * 用法：将原始 GitHub URL 直接附在代理前缀之后即可。
     */
    private static $GITHUB_MIRRORS = array(
        'https://gh-proxy.top/',
        'https://ghfast.top/',
        'https://ghproxy.com/',
    );

    /** GitHub Releases 页面（用于引导手动更新） */
    const GITHUB_RELEASES_PAGE = 'https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases';

    /** 当前版本 */
    const CURRENT_VERSION = '2.1.22';

    /** 插件根目录 */
    private $pluginDir;

    /** 临时目录 */
    private $tmpDir;

    public function __construct()
    {
        $this->pluginDir = rtrim(dirname(__FILE__), '/\\');
        $this->tmpDir    = $this->pluginDir . '/tmp_update';
    }

    // ================================================================
    // 版本检查
    // ================================================================

    /**
     * 从 GitHub API 获取最新 Release 信息
     *
     * @return array|false  成功返回 ['version'=>'2.1.3', 'download_url'=>'...', 'html_url'=>'...', 'body'=>'...']，失败返回 false
     */
    public function fetchLatestRelease()
    {
        // 版本检查使用短超时（5s/节点），避免阻塞后台页面加载
        $json = $this->httpGetWithMirror(self::GITHUB_API_RELEASES, 5);
        if ($json === false) return false;

        $data = @json_decode($json, true);
        if (!is_array($data) || empty($data['tag_name'])) return false;

        $version    = ltrim($data['tag_name'], 'vV');
        $htmlUrl    = isset($data['html_url']) ? $data['html_url'] : self::GITHUB_RELEASES_PAGE;
        $body       = isset($data['body']) ? $data['body'] : '';

        // 找到 zip 下载地址（优先从 assets 中找 .zip，找不到用 zipball_url）
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

    /**
     * 比较版本号
     *
     * @return int  1: $a > $b, -1: $a < $b, 0: 相等
     */
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

    /**
     * 判断是否可以直接更新（major.minor 必须一致，只有 patch 不同）
     *
     * 规则：当前 2.1.x，远程 2.1.y 且 y > x → 可直接更新
     *       其他情况（跨次版本 / 跨主版本）→ 引导 GitHub
     *
     * @param string $current  当前版本，如 "2.1.0"
     * @param string $remote   远程版本，如 "2.1.3"
     * @return bool
     */
    public static function canDirectUpdate($current, $remote)
    {
        $pc = array_map('intval', explode('.', ltrim((string)$current, 'vV')));
        $pr = array_map('intval', explode('.', ltrim((string)$remote, 'vV')));

        // 补齐到三段
        while (count($pc) < 3) $pc[] = 0;
        while (count($pr) < 3) $pr[] = 0;

        // major 和 minor 必须一致，patch 远程更大
        return ($pc[0] === $pr[0] && $pc[1] === $pr[1] && $pr[2] > $pc[2]);
    }

    // ================================================================
    // 直接更新
    // ================================================================

    /**
     * 执行流式就地更新（SSE 版本），通过 $emit 回调实时上报进度
     *
     * @param string   $downloadUrl  ZIP 包的下载地址
     * @param string   $newVersion   新版本号
     * @param callable $emit         进度回调 function($type, $message, $progress)
     *                                $type: download_start/downloading/downloaded/extract_start/extracting/extracted/
     *                                       backup/backed_up/copy_start/copied/done/error
     *                                $progress: 0-100 百分比，-1 表示进度不确定
     * @return bool
     */
    public function doUpdateStreaming($downloadUrl, $newVersion, $emit)
    {
        // 1. 下载（带进度回调，依次尝试直连 + 镜像，超时 90s）
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

        // 2. 检查 zip 扩展
        if (!function_exists('zip_open') && !class_exists('ZipArchive')) {
            call_user_func($emit, 'error', '服务器未安装 PHP zip 扩展，无法自动解压，请手动下载更新', -1);
            return false;
        }

        // 3. 保存 ZIP 到临时文件
        call_user_func($emit, 'saving', '正在保存临时文件...', 0);
        if (!is_dir($this->tmpDir)) {
            @mkdir($this->tmpDir, 0755, true);
        }
        $zipFile = $this->tmpDir . '/update.zip';
        if (file_put_contents($zipFile, $zipContent) === false) {
            call_user_func($emit, 'error', '无法写入临时文件，请检查目录权限', -1);
            return false;
        }
        unset($zipContent); // 释放内存

        // 4. 解压
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
            $step  = max(1, (int) ceil($total / 20)); // 每 5% 汇报一次
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
            // fallback: zip_open（无逐文件进度）
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

        // 5. 找到插件根目录
        $sourceDir = $this->findPluginRoot($extractDir);
        if ($sourceDir === false) {
            $this->cleanup();
            call_user_func($emit, 'error', '在 ZIP 中找不到插件文件，请手动更新', -1);
            return false;
        }

        // 6. 备份当前版本
        call_user_func($emit, 'backup', '正在备份当前版本...', 0);
        $backupDir = $this->tmpDir . '/backup_' . self::CURRENT_VERSION;
        if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
        $this->copyDir($this->pluginDir, $backupDir, array('tmp_update'));
        call_user_func($emit, 'backed_up', '备份完成（' . self::CURRENT_VERSION . '）', 100);

        // 7. 覆盖文件
        call_user_func($emit, 'copy_start', '正在覆盖插件文件...', 0);
        $skipDirs = array('tmp_update', 'assets/compat');
        $copied   = $this->copyDir($sourceDir, $this->pluginDir, $skipDirs);
        call_user_func($emit, 'copied', "已覆盖 {$copied} 个文件", 100);

        // 8. 清理临时文件（保留 backup）
        @unlink($zipFile);
        $this->removeDir($extractDir);

        // 9. 用新版本号直接覆写更新缓存，并清除 lock 文件。
        //    不能只删缓存：PHP opcache 可能仍持有旧 Updater.php 字节码，
        //    导致后台重建缓存时 CURRENT_VERSION 仍读到旧值（2.1.20），
        //    再次显示"有新版本"。直接写入已知的正确状态可绕过该问题。
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

        // 尝试让 opcache 重新加载插件核心文件，使新版本号对后续请求生效
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

    /**
     * 带下载进度的 HTTP 下载，依次尝试直连 + 镜像代理。
     * 若 cURL 可用则使用 CURLOPT_PROGRESSFUNCTION 实时上报；否则退化为普通下载（无进度）。
     *
     * @param string   $url              下载地址（原始 GitHub URL）
     * @param callable $progressCallback function($dlNow, $dlTotal)
     * @param int      $timeout          超时秒数
     * @return string|false
     */
    public function httpDownloadWithProgress($url, $progressCallback, $timeout = 60)
    {
        if (function_exists('curl_init')) {
            // 构建尝试列表：直连 + 各镜像
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

        // 无 cURL：退化为 file_get_contents + 镜像，最后补一次 100% 进度回调
        $result = $this->httpGetWithMirror($url, $timeout);
        if ($result !== false && is_callable($progressCallback)) {
            $len = strlen($result);
            call_user_func($progressCallback, $len, $len);
        }
        return $result;
    }

    /**
     * cURL 下载单个 URL，通过 CURLOPT_PROGRESSFUNCTION 实时上报进度。
     *
     * @param string   $url
     * @param callable $progressCallback function($dlNow, $dlTotal)
     * @param int      $timeout
     * @return string|false
     */
    private function curlDownloadWithProgress($url, $progressCallback, $timeout = 60)
    {
        $ch      = curl_init($url);
        $body    = '';
        $lastPct = -1;

        // CURLOPT_PROGRESSFUNCTION 回调签名在 PHP < 7 与 PHP >= 7 之间不同：
        // PHP 5.x: function($dlTotal, $dlNow, $ulTotal, $ulNow)
        // PHP 7.x: function($resource, $dlTotal, $dlNow, $ulTotal, $ulNow)
        // 用 func_get_args() 兼容处理
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
            return 0; // 返回 0 表示继续下载
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

    /**
     * 执行就地更新（同步版本，无进度回调）
     *
     * @param string $downloadUrl  ZIP 包的下载地址
     * @param string $newVersion   新版本号（用于日志）
     * @return array  ['ok'=>bool, 'msg'=>string, 'details'=>array]
     */
    public function doUpdate($downloadUrl, $newVersion)
    {
        $details = array();

        // 1. 下载 ZIP（先尝试直连，失败则依次尝试镜像代理；下载使用 60s 超时）
        $details[] = '正在下载 ' . $downloadUrl . ' ...';
        $zipContent = $this->httpGetWithMirror($downloadUrl, 60);
        if ($zipContent === false || strlen($zipContent) < 100) {
            return array('ok' => false, 'msg' => '下载失败，请检查服务器网络或手动更新', 'details' => $details);
        }
        $details[] = '下载完成，大小 ' . round(strlen($zipContent) / 1024, 1) . ' KB';

        // 2. 检查 zip 扩展
        if (!function_exists('zip_open') && !class_exists('ZipArchive')) {
            return array('ok' => false, 'msg' => '服务器未安装 PHP zip 扩展，无法自动解压，请手动下载更新', 'details' => $details);
        }

        // 3. 保存 ZIP 到临时文件
        if (!is_dir($this->tmpDir)) {
            @mkdir($this->tmpDir, 0755, true);
        }
        $zipFile = $this->tmpDir . '/update.zip';
        if (file_put_contents($zipFile, $zipContent) === false) {
            return array('ok' => false, 'msg' => '无法写入临时文件，请检查目录权限', 'details' => $details);
        }
        $details[] = '已保存临时 ZIP';

        // 4. 解压
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
            // fallback: zip_open
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

        // 5. 找到插件根目录（ZIP 内可能有一级子目录，如 lhl77-Typecho-Plugin-AdminBeautify-xxxxxx/）
        $sourceDir = $this->findPluginRoot($extractDir);
        if ($sourceDir === false) {
            $this->cleanup();
            return array('ok' => false, 'msg' => '在 ZIP 中找不到插件文件，请手动更新', 'details' => $details);
        }
        $details[] = '插件根目录：' . str_replace($extractDir, '', $sourceDir);

        // 6. 备份当前版本到 tmp_update/backup/
        $backupDir = $this->tmpDir . '/backup_' . self::CURRENT_VERSION;
        if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
        $this->copyDir($this->pluginDir, $backupDir, array('tmp_update'));
        $details[] = '已备份当前版本到 tmp_update/backup_' . self::CURRENT_VERSION;

        // 7. 将新文件覆盖到插件目录（跳过用户数据目录和本 tmp 目录）
        $skipDirs = array('tmp_update', 'assets/compat'); // 不覆盖 compat（保留用户自定义脚本）
        $copied = $this->copyDir($sourceDir, $this->pluginDir, $skipDirs);
        $details[] = '已覆盖 ' . $copied . ' 个文件';

        // 8. 清理临时文件（保留 backup）
        @unlink($zipFile);
        $this->removeDir($extractDir);
        $details[] = '临时文件已清理';

        // 9. 用新版本号直接覆写更新缓存（同 doUpdateStreaming，避免 opcache 旧常量问题）
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

    // ================================================================
    // 工具方法
    // ================================================================

    /**
     * 在解压目录中寻找包含 Plugin.php 的插件根目录
     */
    private function findPluginRoot($dir)
    {
        // 直接找 Plugin.php
        if (file_exists($dir . '/Plugin.php')) return $dir;
        // 一级子目录
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

    /**
     * 递归复制目录，返回复制的文件数
     *
     * @param string   $src      源目录
     * @param string   $dst      目标目录
     * @param string[] $skipDirs 相对于 $src 跳过的目录名（支持前缀匹配）
     * @return int 复制的文件数
     */
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

            // 检查是否在跳过列表中
            $skipThis = false;
            foreach ($skipDirs as $skip) {
                // 支持 "assets/compat" 这种路径前缀
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

    /**
     * 递归删除目录
     */
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

    /**
     * 清理临时目录（ZIP 和解压内容，保留 backup）
     */
    private function cleanup()
    {
        @unlink($this->tmpDir . '/update.zip');
        $extractDir = $this->tmpDir . '/extracted';
        if (is_dir($extractDir)) $this->removeDir($extractDir);
    }

    /**
     * HTTP GET（file_get_contents + cURL 回退）
     *
     * @param string $url     目标 URL
     * @param int    $timeout 超时秒数（默认 30s）
     */
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

    /**
     * HTTP GET，直连失败时依次通过大陆可用的 GitHub 镜像代理重试。
     * 适用于 api.github.com、github.com、codeload.github.com 等在大陆不稳定的地址。
     *
     * @param string $url     目标 URL
     * @param int    $timeout 每个节点的超时秒数（默认 30s；版本检查建议传 5s 避免阻塞）
     */
    private function httpGetWithMirror($url, $timeout = 30)
    {
        // 1. 优先直连
        $result = $this->httpGet($url, $timeout);
        if ($result !== false) return $result;

        // 2. 依次尝试镜像代理（将原始 URL 附在代理前缀后即可）
        foreach (self::$GITHUB_MIRRORS as $mirror) {
            $mirroredUrl = $mirror . $url;
            $result = $this->httpGet($mirroredUrl, $timeout);
            if ($result !== false) return $result;
        }

        return false;
    }
}
