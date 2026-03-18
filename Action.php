<?php
/**
 * AdminBeautify AJAX Action Handler
 *
 * @package AdminBeautify
 * @author LHL
 * @version 2.1.16
 * @link https://blog.lhl.one
 */

class AdminBeautify_Action extends Typecho_Widget implements Widget_Interface_Do
{
    /**
     * @var Typecho_Db
     */
    private $db;

    /**
     * @var object
     */
    private $options;

    /**
     * @var object
     */
    private $pluginOptions;

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->db = Typecho_Db::get();
        $this->options = Typecho_Widget::widget('Widget_Options');
        $this->pluginOptions = $this->options->plugin('AdminBeautify');
    }

    public function execute()
    {
    }

    /**
     * 检查管理员权限
     */
    private function checkAuth()
    {
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->hasLogin()) {
            $this->jsonError('未登录', 401);
        }
    }

    /**
     * 检查管理员权限（仅管理员）
     */
    private function checkAdmin()
    {
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->hasLogin() || !$user->pass('administrator', true)) {
            $this->jsonError('权限不足', 403);
        }
    }

    /**
     * 输出 JSON 成功响应
     */
    private function jsonSuccess($data = null, $message = 'ok')
    {
        $this->response->throwJson(array(
            'code'    => 0,
            'message' => $message,
            'data'    => $data,
        ));
    }

    /**
     * 输出 JSON 错误响应
     */
    private function jsonError($message = 'error', $code = 400)
    {
        $this->response->throwJson(array(
            'code'    => $code,
            'message' => $message,
            'data'    => null,
        ));
    }

    // ================================================================
    // AJAX 业务方法
    // ================================================================

    /**
     * 输出 PWA Web App Manifest (JSON)
     * 访问方式：/action/admin-beautify?do=manifest
     */
    public function manifest()
    {
        // Manifest 无需登录即可访问（浏览器会在安装前请求）
        $siteTitle = (string) $this->options->title;
        $adminUrl  = rtrim((string) $this->options->adminUrl, '/') . '/';
        $pluginUrl = rtrim((string) $this->options->pluginUrl, '/');

        // 读取主题色
        $primaryColor = $this->pluginOptions->primaryColor ?: 'purple';
        $colorMap = array(
            'purple' => array('theme' => '#7D5260', 'bg' => '#FFFBFE'),
            'blue'   => array('theme' => '#556270', 'bg' => '#FAFCFF'),
            'teal'   => array('theme' => '#4A6363', 'bg' => '#F4FBFB'),
            'green'  => array('theme' => '#55624C', 'bg' => '#F6FBF0'),
            'orange' => array('theme' => '#725A42', 'bg' => '#FFFBF6'),
            'pink'   => array('theme' => '#74565F', 'bg' => '#FFFBFF'),
            'red'    => array('theme' => '#775654', 'bg' => '#FFFBFF'),
        );
        $colors = isset($colorMap[$primaryColor]) ? $colorMap[$primaryColor] : $colorMap['purple'];
        $themeColor = $colors['theme'];
        $bgColor    = $colors['bg'];

        // 读取 PWA 自定义设置
        $pwaAppName = isset($this->pluginOptions->pwa_appName) ? trim((string) $this->pluginOptions->pwa_appName) : '';
        $pwaAppIcon = isset($this->pluginOptions->pwa_appIcon) ? trim((string) $this->pluginOptions->pwa_appIcon) : '';

        $appName = ($pwaAppName !== '') ? $pwaAppName : ($siteTitle . ' 管理后台');
        $shortName = ($pwaAppName !== '') ? $pwaAppName : ($siteTitle ?: 'Admin');

        // 图标
        $icons = array();
        if ($pwaAppIcon !== '') {
            // 使用用户自定义图标
            $icons[] = array(
                'src'     => $pwaAppIcon,
                'sizes'   => '192x192',
                'type'    => 'image/png',
                'purpose' => 'any',
            );
            $icons[] = array(
                'src'     => $pwaAppIcon,
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'any',
            );
            $icons[] = array(
                'src'     => $pwaAppIcon,
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'maskable',
            );
        } else {
            // 使用默认 SVG 图标
            $svgIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192">'
                . '<rect width="192" height="192" rx="48" fill="' . htmlspecialchars($themeColor) . '"/>'
                . '<text x="96" y="130" font-size="100" text-anchor="middle" fill="#fff" font-family="sans-serif">T</text>'
                . '</svg>';
            $svgDataUri = 'data:image/svg+xml;base64,' . base64_encode($svgIcon);
            $icons[] = array(
                'src'     => $svgDataUri,
                'sizes'   => 'any',
                'type'    => 'image/svg+xml',
                'purpose' => 'any maskable',
            );
        }

        $manifest = array(
            'name'             => $appName,
            'short_name'       => $shortName,
            'description'      => 'Typecho 管理后台 - 由 AdminBeautify 增强',
            'start_url'        => $adminUrl,
            'scope'            => $adminUrl,
            'display'          => 'standalone',
            'orientation'      => 'any',
            'theme_color'      => $themeColor,
            'background_color' => $bgColor,
            'lang'             => 'zh-CN',
            'icons'            => $icons,
            'screenshots'      => array(),
            'categories'       => array('productivity', 'utilities'),
        );

        $this->response->setContentType('application/manifest+json');
        // 允许浏览器缓存 1 小时
        $this->response->setHeader('Cache-Control', 'public, max-age=3600');
        echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * 代理输出 Service Worker 脚本
     * 访问方式：/action/admin-beautify?do=sw
     *
     * SW 文件必须与后台同源（同 path scope），因此通过 action 动态注入配置后输出。
     */
    public function sw()
    {
        $options    = $this->options;
        $pluginUrl  = rtrim((string) $options->pluginUrl, '/');
        $pluginVer  = '2.1.16';
        $cssUrl     = $pluginUrl . '/AdminBeautify/assets/AdminBeautify.v' . $pluginVer . '.css';
        $jsUrl      = $pluginUrl . '/AdminBeautify/assets/AdminBeautify.min.v' . $pluginVer . '.js';

        $swFile = dirname(__FILE__) . '/assets/sw.js';
        if (!file_exists($swFile)) {
            http_response_code(404);
            exit;
        }

        $swContent = file_get_contents($swFile);

        // 动态注入预缓存 URL 列表（替换占位数组）
        $precacheUrls = json_encode(array($cssUrl, $jsUrl), JSON_UNESCAPED_SLASHES);
        $swContent = str_replace('var PRECACHE_URLS = [];', 'var PRECACHE_URLS = ' . $precacheUrls . ';', $swContent);

        $this->response->setContentType('application/javascript');
        $this->response->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->response->setHeader('Service-Worker-Allowed', rtrim((string) $options->adminUrl, '/') . '/');
        echo $swContent;
        exit;
    }

    /**
     * 获取插件信息 & 仪表盘统计（示例）
     */
    public function info()
    {
        $this->checkAuth();

        $postsCount = $this->db->fetchObject(
            $this->db->select(array('COUNT(*)' => 'num'))
                ->from('table.contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
        )->num;

        $commentsCount = $this->db->fetchObject(
            $this->db->select(array('COUNT(*)' => 'num'))
                ->from('table.comments')
                ->where('status = ?', 'approved')
        )->num;

        $categoriesCount = $this->db->fetchObject(
            $this->db->select(array('COUNT(*)' => 'num'))
                ->from('table.metas')
                ->where('type = ?', 'category')
        )->num;

        $this->jsonSuccess(array(
            'version'    => '1.4.5',
            'posts'      => (int) $postsCount,
            'comments'   => (int) $commentsCount,
            'categories' => (int) $categoriesCount,
        ));
    }

    /**
     * 仪表盘统计趋势数据
     * 访问方式：/action/admin-beautify?do=stats
     *
     * 返回本周 vs 上周的文章/评论新增趋势，以及总数
     */
    public function stats()
    {
        $this->checkAuth();

        $db  = $this->db;
        $now = time();
        $weekAgo      = $now - 7 * 86400;
        $twoWeeksAgo  = $now - 14 * 86400;

        // --- 总数 ---
        $postsTotal = (int) $db->fetchObject(
            $db->select(array('COUNT(cid)' => 'num'))
               ->from('table.contents')
               ->where('type = ?', 'post')
               ->where('status = ?', 'publish')
        )->num;

        $commentsTotal = (int) $db->fetchObject(
            $db->select(array('COUNT(coid)' => 'num'))
               ->from('table.comments')
               ->where('status = ?', 'approved')
        )->num;

        $catsTotal = (int) $db->fetchObject(
            $db->select(array('COUNT(mid)' => 'num'))
               ->from('table.metas')
               ->where('type = ?', 'category')
        )->num;

        // --- 本周新增（最近 7 天） ---
        $postsThisWeek = (int) $db->fetchObject(
            $db->select(array('COUNT(cid)' => 'num'))
               ->from('table.contents')
               ->where('type = ?', 'post')
               ->where('status = ?', 'publish')
               ->where('created >= ?', $weekAgo)
        )->num;

        $commentsThisWeek = (int) $db->fetchObject(
            $db->select(array('COUNT(coid)' => 'num'))
               ->from('table.comments')
               ->where('status = ?', 'approved')
               ->where('created >= ?', $weekAgo)
        )->num;

        // --- 上周新增（7~14 天前） ---
        $postsLastWeek = (int) $db->fetchObject(
            $db->select(array('COUNT(cid)' => 'num'))
               ->from('table.contents')
               ->where('type = ?', 'post')
               ->where('status = ?', 'publish')
               ->where('created >= ?', $twoWeeksAgo)
               ->where('created < ?', $weekAgo)
        )->num;

        $commentsLastWeek = (int) $db->fetchObject(
            $db->select(array('COUNT(coid)' => 'num'))
               ->from('table.comments')
               ->where('status = ?', 'approved')
               ->where('created >= ?', $twoWeeksAgo)
               ->where('created < ?', $weekAgo)
        )->num;

        $this->jsonSuccess(array(
            'posts' => array(
                'total'    => $postsTotal,
                'thisWeek' => $postsThisWeek,
                'lastWeek' => $postsLastWeek,
            ),
            'comments' => array(
                'total'    => $commentsTotal,
                'thisWeek' => $commentsThisWeek,
                'lastWeek' => $commentsLastWeek,
            ),
            'cats' => array(
                'total'    => $catsTotal,
                'thisWeek' => null,
                'lastWeek' => null,
            ),
        ));
    }

    /**
     * 保存插件设置（AJAX 方式）
     */
    public function saveSettings()
    {
        $this->checkAdmin();

        // 获取请求中的设置参数
        $settings = $this->request->from(
            'primaryColor', 'darkMode', 'borderRadius',
            'enableAnimation', 'navPosition'
        );

        // 过滤空值
        $settings = array_filter($settings, function ($v) {
            return $v !== null && $v !== '';
        });

        if (empty($settings)) {
            $this->jsonError('没有需要保存的设置');
        }

        try {
            // 使用 Typecho 方式更新插件配置
            $pluginName = 'AdminBeautify';
            $currentSettings = $this->options->plugin($pluginName);

            $merged = array();
            // 取出当前所有设置
            foreach ($currentSettings as $key => $value) {
                $merged[$key] = $value;
            }
            // 覆盖变化的部分
            foreach ($settings as $key => $value) {
                $merged[$key] = $value;
            }

            // 写入数据库
            $this->db->query(
                $this->db->update('table.options')
                    ->rows(array('value' => serialize($merged)))
                    ->where('name = ?', 'plugin:' . $pluginName)
            );

            $this->jsonSuccess($merged, '设置已保存');
        } catch (Exception $e) {
            $this->jsonError('保存失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取当前插件配置（AJAX 获取设置）
     */
    public function getSettings()
    {
        $this->checkAdmin();

        $settings = array();
        foreach ($this->pluginOptions as $key => $value) {
            $settings[$key] = $value;
        }

        $this->jsonSuccess($settings);
    }

    /**
     * 通用 ping / 心跳检测
     */
    public function ping()
    {
        $this->jsonSuccess(array(
            'time'   => time(),
            'plugin' => 'AdminBeautify',
        ), 'pong');
    }

    /**
     * 检查插件更新
     * 访问方式：/action/admin-beautify?do=check-update
     *
     * 返回：
     *   - has_update: bool
     *   - current: 当前版本
     *   - latest: 最新版本
     *   - can_direct: 是否可直接更新（2.1.x → 2.1.y）
     *   - html_url: GitHub Releases 页面
     *   - download_url: ZIP 下载地址（直接更新时使用）
     *   - body: Release 说明
     */
    public function checkUpdate()
    {
        $this->checkAdmin();

        require_once dirname(__FILE__) . '/Updater.php';
        $updater = new AdminBeautify_Updater();

        $release = $updater->fetchLatestRelease();
        if ($release === false) {
            $this->jsonError('无法连接 GitHub，请检查服务器网络', 502);
        }

        $current   = AdminBeautify_Updater::CURRENT_VERSION;
        $latest    = $release['version'];
        $hasUpdate = AdminBeautify_Updater::compareVersion($latest, $current) > 0;
        $canDirect = $hasUpdate && AdminBeautify_Updater::canDirectUpdate($current, $latest);

        $this->jsonSuccess(array(
            'has_update'   => $hasUpdate,
            'current'      => $current,
            'latest'       => $latest,
            'can_direct'   => $canDirect,
            'html_url'     => $release['html_url'],
            'download_url' => $release['download_url'],
            'body'         => $release['body'],
        ), $hasUpdate ? '发现新版本 v' . $latest : '已是最新版本 v' . $current);
    }

    /**
     * 执行直接更新（仅允许 2.1.x → 2.1.y 的补丁更新）
     * 访问方式：/action/admin-beautify?do=do-update
     *
     * POST 参数：
     *   download_url: ZIP 包地址
     *   new_version:  目标版本号（用于安全校验）
     */
    public function doUpdate()
    {
        $this->checkAdmin();

        require_once dirname(__FILE__) . '/Updater.php';

        $downloadUrl = trim($this->request->get('download_url', ''));
        $newVersion  = trim($this->request->get('new_version', ''));

        if ($downloadUrl === '') {
            $this->jsonError('缺少 download_url 参数');
        }
        if ($newVersion === '') {
            $this->jsonError('缺少 new_version 参数');
        }

        // 安全校验：必须满足直接更新条件
        $current = AdminBeautify_Updater::CURRENT_VERSION;
        if (!AdminBeautify_Updater::canDirectUpdate($current, $newVersion)) {
            $this->jsonError('版本跨度过大（当前 v' . $current . ' → v' . $newVersion . '），请前往 GitHub 手动更新', 400);
        }

        // 校验 URL 必须来自 GitHub
        if (strpos($downloadUrl, 'github.com') === false && strpos($downloadUrl, 'codeload.github.com') === false) {
            $this->jsonError('下载地址不合法', 400);
        }

        $updater = new AdminBeautify_Updater();
        $result  = $updater->doUpdate($downloadUrl, $newVersion);

        if ($result['ok']) {
            $this->jsonSuccess(array(
                'details'     => $result['details'],
                'new_version' => $newVersion,
            ), $result['msg']);
        } else {
            $this->jsonError($result['msg'] . "\n详情：" . implode("\n", $result['details']), 500);
        }
    }

    /**
     * 从 GitHub 同步兼容脚本
     * 访问方式：/action/admin-beautify?do=sync-compat
     *
     * 逻辑：
     * 1. 通过 GitHub Contents API 获取 assets/compat/ 目录文件列表
     * 2. 仅处理 .js 文件，跳过 README.md 等
     * 3. 下载文件内容，解析 @version 元数据
     * 4. 与本地文件版本比较：本地不存在 → 新增；远程版本更高 → 更新；否则跳过
     * 5. 返回每个文件的处理结果
     */
    public function syncCompat()
    {
        $this->checkAdmin();

        $compatDir = dirname(__FILE__) . '/assets/compat/';
        if (!is_dir($compatDir)) {
            $this->jsonError('本地 assets/compat/ 目录不存在', 500);
        }
        if (!is_writable($compatDir)) {
            $this->jsonError('本地 assets/compat/ 目录不可写，请检查文件权限', 500);
        }

        // GitHub Contents API — 列出目录
        $apiUrl = 'https://api.github.com/repos/lhl77/Typecho-Plugin-AdminBeautify/contents/assets/compat';
        $listJson = $this->httpGet($apiUrl);
        if ($listJson === false) {
            $this->jsonError('无法连接 GitHub API，请检查服务器网络', 502);
        }

        $files = @json_decode($listJson, true);
        if (!is_array($files)) {
            $this->jsonError('GitHub API 返回数据解析失败', 502);
        }

        $results = array();

        foreach ($files as $item) {
            // 只处理 .js 文件
            if (!isset($item['name']) || !isset($item['type'])) continue;
            if ($item['type'] !== 'file') continue;
            if (substr($item['name'], -3) !== '.js') continue;

            $filename    = $item['name'];
            $downloadUrl = isset($item['download_url']) ? $item['download_url'] : '';
            if ($downloadUrl === '') continue;

            // 下载远程文件内容
            $remoteContent = $this->httpGet($downloadUrl);
            if ($remoteContent === false) {
                $results[] = array('file' => $filename, 'status' => 'error', 'msg' => '下载失败');
                continue;
            }

            // 解析远程版本
            $remoteVersion = $this->parseVersionFromContent($remoteContent);

            $localPath = $compatDir . $filename;

            if (!file_exists($localPath)) {
                // 本地不存在 → 新增
                if (file_put_contents($localPath, $remoteContent) !== false) {
                    $results[] = array('file' => $filename, 'status' => 'added', 'version' => $remoteVersion, 'msg' => '新增');
                } else {
                    $results[] = array('file' => $filename, 'status' => 'error', 'msg' => '写入失败');
                }
            } else {
                // 本地存在 → 比较版本
                $localContent = @file_get_contents($localPath, false, null, 0, 2048);
                $localVersion = $localContent !== false ? $this->parseVersionFromContent($localContent) : '0';

                if ($this->compareVersion($remoteVersion, $localVersion) > 0) {
                    // 远程版本更高 → 更新
                    if (file_put_contents($localPath, $remoteContent) !== false) {
                        $results[] = array(
                            'file'           => $filename,
                            'status'         => 'updated',
                            'version'        => $remoteVersion,
                            'localVersion'   => $localVersion,
                            'msg'            => '已更新 v' . $localVersion . ' → v' . $remoteVersion,
                        );
                    } else {
                        $results[] = array('file' => $filename, 'status' => 'error', 'msg' => '写入失败');
                    }
                } else {
                    // 版本相同或本地更新 → 跳过
                    $results[] = array(
                        'file'    => $filename,
                        'status'  => 'skip',
                        'version' => $localVersion,
                        'msg'     => '已是最新 v' . $localVersion,
                    );
                }
            }
        }

        $added   = count(array_filter($results, function($r){ return $r['status'] === 'added'; }));
        $updated = count(array_filter($results, function($r){ return $r['status'] === 'updated'; }));
        $skipped = count(array_filter($results, function($r){ return $r['status'] === 'skip'; }));
        $errors  = count(array_filter($results, function($r){ return $r['status'] === 'error'; }));

        $this->jsonSuccess(array(
            'results' => $results,
            'summary' => array(
                'added'   => $added,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors'  => $errors,
                'total'   => count($results),
            ),
        ), '同步完成：新增 ' . $added . ' 个，更新 ' . $updated . ' 个，跳过 ' . $skipped . ' 个' . ($errors > 0 ? '，失败 ' . $errors . ' 个' : ''));
    }

    /**
     * 简单 HTTP GET（支持 file_get_contents 和 cURL 回退）
     */
    private function httpGet($url)
    {
        $opts = array(
            'http' => array(
                'method'  => 'GET',
                'timeout' => 15,
                'header'  => "User-Agent: AdminBeautify-Typecho-Plugin/2.1.0\r\n"
                           . "Accept: application/vnd.github.v3+json\r\n",
            ),
            'ssl' => array(
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ),
        );
        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);
        if ($result !== false) return $result;

        // 回退到 cURL
        if (!function_exists('curl_init')) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'AdminBeautify-Typecho-Plugin/2.1.0',
            CURLOPT_HTTPHEADER     => array('Accept: application/vnd.github.v3+json'),
        ));
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($result !== false && $httpCode === 200) ? $result : false;
    }

    /**
     * 从脚本文件内容中解析 @version 元数据
     */
    private function parseVersionFromContent($content)
    {
        if (preg_match('/@version\s+([^\s\*\/]+)/i', $content, $m)) {
            return trim($m[1]);
        }
        return '0';
    }

    /**
     * 版本号比较（支持语义版本）
     * 返回 1 表示 $a > $b，-1 表示 $a < $b，0 表示相等
     */
    private function compareVersion($a, $b)
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

    // ================================================================
    // Action 路由入口
    // ================================================================

    /**
     * 一键安装 AdminBeautifyStore 插件
     * 访问方式：/action/admin-beautify?do=install-abs
     *
     * 从 GitHub 下载 main 分支 ZIP 并解压到 /usr/plugins/AdminBeautifyStore/
     */
    public function installAbs()
    {
        $this->checkAdmin();

        $pluginsDir = rtrim(dirname(__FILE__), '/\\') . DIRECTORY_SEPARATOR . '..';
        $targetDir  = $pluginsDir . DIRECTORY_SEPARATOR . 'AdminBeautifyStore';

        if (is_dir($targetDir)) {
            $this->jsonError('AdminBeautifyStore 目录已存在，无需重复安装', 409);
        }

        if (!class_exists('ZipArchive')) {
            $this->jsonError('PHP ZipArchive 扩展未安装，无法解压 ZIP，请联系主机商开启', 500);
        }

        // 下载 ZIP（超时 60 秒，支持重定向）
        $zipUrl = 'https://github.com/lhl77/Typecho-Plugin-AdminBeautifyStore/archive/refs/heads/main.zip';
        $ctx = stream_context_create(array(
            'http' => array(
                'method'          => 'GET',
                'timeout'         => 60,
                'follow_location' => 1,
                'max_redirects'   => 5,
                'user_agent'      => 'AdminBeautify-Typecho-Plugin/2.0 (+https://github.com/lhl77/Typecho-Plugin-AdminBeautify)',
                'header'          => "Accept: application/zip\r\n",
            ),
            'ssl' => array(
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ),
        ));

        $zipContent = @file_get_contents($zipUrl, false, $ctx);
        if ($zipContent === false || strlen($zipContent) < 100) {
            // 回退到 cURL
            if (function_exists('curl_init')) {
                $ch = curl_init($zipUrl);
                curl_setopt_array($ch, array(
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 60,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_USERAGENT      => 'AdminBeautify-Typecho-Plugin/2.0',
                ));
                $zipContent = curl_exec($ch);
                $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($zipContent === false || $httpCode !== 200 || strlen($zipContent) < 100) {
                    $this->jsonError('下载失败（HTTP ' . (int)$httpCode . '），请检查服务器网络或稍后重试', 502);
                }
            } else {
                $this->jsonError('下载失败，请检查服务器网络或稍后重试', 502);
            }
        }

        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'abs_install_' . md5(time()) . '.zip';
        if (@file_put_contents($tmpFile, $zipContent) === false) {
            $this->jsonError('无法写入临时文件，请检查系统 temp 目录权限', 500);
        }
        unset($zipContent); // 释放内存

        $zip = new ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            @unlink($tmpFile);
            $this->jsonError('ZIP 文件损坏或无法打开', 500);
        }

        // GitHub archive ZIP 内根目录名为 "Typecho-Plugin-AdminBeautifyStore-main/"
        $zipRootDir = 'Typecho-Plugin-AdminBeautifyStore-main/';

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $extracted = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (strpos($entry, $zipRootDir) !== 0) continue;

            $relative = substr($entry, strlen($zipRootDir));
            if ($relative === '' || $relative === false) continue;
            if (strpos($relative, '..') !== false) continue; // 安全：防路径穿越

            $destPath = $targetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (substr($entry, -1) === '/') {
                if (!is_dir($destPath)) @mkdir($destPath, 0755, true);
            } else {
                $destDirPath = dirname($destPath);
                if (!is_dir($destDirPath)) @mkdir($destDirPath, 0755, true);
                @file_put_contents($destPath, $zip->getFromIndex($i));
                $extracted = true;
            }
        }

        $zip->close();
        @unlink($tmpFile);

        if (!$extracted) {
            // 清理空目录
            @rmdir($targetDir);
            $this->jsonError('ZIP 解压失败：未找到有效文件（检查 ZIP 结构是否为 Typecho-Plugin-AdminBeautifyStore-main/），请手动下载安装', 500);
        }

        $this->jsonSuccess(array('dir' => 'AdminBeautifyStore'), '安装成功！请在插件管理页面启用 AdminBeautifyStore');
    }

    /**
     * 绑定动作 — 根据 ?do=xxx 分发到对应方法
     *
     * @access public
     * @return void
     */
    public function action()
    {
        $do = $this->request->get('do', '');

        /* manifest 和 sw 响应不走 JSON，提前分发 */
        if ($do === 'manifest') {
            $this->manifest();
            return;
        }
        if ($do === 'sw') {
            $this->sw();
            return;
        }

        // 设置 JSON 响应头
        $this->response->setContentType('application/json');

        switch ($do) {
            case 'ping':
                $this->ping();
                break;

            case 'info':
                $this->info();
                break;

            case 'stats':
                $this->stats();
                break;

            case 'get-settings':
                $this->getSettings();
                break;

            case 'save-settings':
                $this->saveSettings();
                break;

            case 'sync-compat':
                $this->syncCompat();
                break;

            case 'check-update':
                $this->checkUpdate();
                break;

            case 'do-update':
                $this->doUpdate();
                break;

            case 'install-abs':
                $this->installAbs();
                break;

            default:
                $this->jsonError('未知的操作: ' . $do, 404);
                break;
        }
    }
}
