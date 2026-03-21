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
    const CURRENT_VERSION = '2.1.19';

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
        $json = $this->httpGetWithMirror(self::GITHUB_API_RELEASES);
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
     * 执行就地更新
     *
     * @param string $downloadUrl  ZIP 包的下载地址
     * @param string $newVersion   新版本号（用于日志）
     * @return array  ['ok'=>bool, 'msg'=>string, 'details'=>array]
     */
    public function doUpdate($downloadUrl, $newVersion)
    {
        $details = array();

        // 1. 下载 ZIP（先尝试直连，失败则依次尝试镜像代理）
        $details[] = '正在下载 ' . $downloadUrl . ' ...';
        $zipContent = $this->httpGetWithMirror($downloadUrl);
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
     */
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

    /**
     * HTTP GET，直连失败时依次通过大陆可用的 GitHub 镜像代理重试。
     * 适用于 api.github.com、github.com、codeload.github.com 等在大陆不稳定的地址。
     */
    private function httpGetWithMirror($url)
    {
        // 1. 优先直连
        $result = $this->httpGet($url);
        if ($result !== false) return $result;

        // 2. 依次尝试镜像代理（将原始 URL 附在代理前缀后即可）
        foreach (self::$GITHUB_MIRRORS as $mirror) {
            $mirroredUrl = $mirror . $url;
            $result = $this->httpGet($mirroredUrl);
            if ($result !== false) return $result;
        }

        return false;
    }
}
