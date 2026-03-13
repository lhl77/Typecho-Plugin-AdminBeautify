<?php
class AdminBeautify_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private $db;
    private $options;
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
    private function checkAuth()
    {
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->hasLogin()) {
            $this->jsonError('未登录', 401);
        }
    }
    private function checkAdmin()
    {
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->hasLogin() || !$user->pass('administrator', true)) {
            $this->jsonError('权限不足', 403);
        }
    }
    private function jsonSuccess($data = null, $message = 'ok')
    {
        $this->response->throwJson(array('code' => 0, 'message' => $message, 'data' => $data));
    }
    private function jsonError($message = 'error', $code = 400)
    {
        $this->response->throwJson(array('code' => $code, 'message' => $message, 'data' => null));
    }
    public function manifest()
    {
        $siteTitle = (string) $this->options->title;
        $adminUrl = rtrim((string) $this->options->adminUrl, '/') . '/';
        $pluginUrl = rtrim((string) $this->options->pluginUrl, '/');
        $primaryColor = $this->pluginOptions->primaryColor ?: 'purple';
        $colorMap = array('purple' => array('theme' => '#7D5260', 'bg' => '#FFFBFE'), 'blue' => array('theme' => '#556270', 'bg' => '#FAFCFF'), 'teal' => array('theme' => '#4A6363', 'bg' => '#F4FBFB'), 'green' => array('theme' => '#55624C', 'bg' => '#F6FBF0'), 'orange' => array('theme' => '#725A42', 'bg' => '#FFFBF6'), 'pink' => array('theme' => '#74565F', 'bg' => '#FFFBFF'), 'red' => array('theme' => '#775654', 'bg' => '#FFFBFF'));
        $colors = isset($colorMap[$primaryColor]) ? $colorMap[$primaryColor] : $colorMap['purple'];
        $themeColor = $colors['theme'];
        $bgColor = $colors['bg'];
        $pwaAppName = isset($this->pluginOptions->pwa_appName) ? trim((string) $this->pluginOptions->pwa_appName) : '';
        $pwaAppIcon = isset($this->pluginOptions->pwa_appIcon) ? trim((string) $this->pluginOptions->pwa_appIcon) : '';
        $appName = $pwaAppName !== '' ? $pwaAppName : $siteTitle . ' 管理后台';
        $shortName = $pwaAppName !== '' ? $pwaAppName : ($siteTitle ?: 'Admin');
        $icons = array();
        if ($pwaAppIcon !== '') {
            $icons[] = array('src' => $pwaAppIcon, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any');
            $icons[] = array('src' => $pwaAppIcon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any');
            $icons[] = array('src' => $pwaAppIcon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable');
        } else {
            $svgIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192">' . '<rect width="192" height="192" rx="48" fill="' . htmlspecialchars($themeColor) . '"/>' . '<text x="96" y="130" font-size="100" text-anchor="middle" fill="#fff" font-family="sans-serif">T</text>' . '</svg>';
            $svgDataUri = 'data:image/svg+xml;base64,' . base64_encode($svgIcon);
            $icons[] = array('src' => $svgDataUri, 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable');
        }
        $manifest = array('name' => $appName, 'short_name' => $shortName, 'description' => 'Typecho 管理后台 - 由 AdminBeautify 增强', 'start_url' => $adminUrl, 'scope' => $adminUrl, 'display' => 'standalone', 'orientation' => 'any', 'theme_color' => $themeColor, 'background_color' => $bgColor, 'lang' => 'zh-CN', 'icons' => $icons, 'screenshots' => array(), 'categories' => array('productivity', 'utilities'));
        $this->response->setContentType('application/manifest+json');
        $this->response->setHeader('Cache-Control', 'public, max-age=3600');
        echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
    public function sw()
    {
        $options = $this->options;
        $pluginUrl = rtrim((string) $options->pluginUrl, '/');
        $cssUrl = $pluginUrl . '/AdminBeautify/assets/style.css?v=2.0.2';
        $jsUrl = $pluginUrl . '/AdminBeautify/assets/script.js?v=2.0.2';
        $swFile = dirname(__FILE__) . '/assets/sw.js';
        if (!file_exists($swFile)) {
            http_response_code(404);
            exit;
        }
        $swContent = file_get_contents($swFile);
        $precacheUrls = json_encode(array($cssUrl, $jsUrl), JSON_UNESCAPED_SLASHES);
        $swContent = str_replace('var PRECACHE_URLS = [];', 'var PRECACHE_URLS = ' . $precacheUrls . ';', $swContent);
        $this->response->setContentType('application/javascript');
        $this->response->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->response->setHeader('Service-Worker-Allowed', rtrim((string) $options->adminUrl, '/') . '/');
        echo $swContent;
        exit;
    }
    public function info()
    {
        $this->checkAuth();
        $postsCount = $this->db->fetchObject($this->db->select(array('COUNT(*)' => 'num'))->from('table.contents')->where('type = ?', 'post')->where('status = ?', 'publish'))->num;
        $commentsCount = $this->db->fetchObject($this->db->select(array('COUNT(*)' => 'num'))->from('table.comments')->where('status = ?', 'approved'))->num;
        $categoriesCount = $this->db->fetchObject($this->db->select(array('COUNT(*)' => 'num'))->from('table.metas')->where('type = ?', 'category'))->num;
        $this->jsonSuccess(array('version' => '1.4.5', 'posts' => (int) $postsCount, 'comments' => (int) $commentsCount, 'categories' => (int) $categoriesCount));
    }
    public function saveSettings()
    {
        $this->checkAdmin();
        $settings = $this->request->from('primaryColor', 'darkMode', 'borderRadius', 'enableAnimation', 'navPosition');
        $settings = array_filter($settings, function ($v) {
            return $v !== null && $v !== '';
        });
        if (empty($settings)) {
            $this->jsonError('没有需要保存的设置');
        }
        try {
            $pluginName = 'AdminBeautify';
            $currentSettings = $this->options->plugin($pluginName);
            $merged = array();
            foreach ($currentSettings as $key => $value) {
                $merged[$key] = $value;
            }
            foreach ($settings as $key => $value) {
                $merged[$key] = $value;
            }
            $this->db->query($this->db->update('table.options')->rows(array('value' => serialize($merged)))->where('name = ?', 'plugin:' . $pluginName));
            $this->jsonSuccess($merged, '设置已保存');
        } catch (Exception $e) {
            $this->jsonError('保存失败: ' . $e->getMessage(), 500);
        }
    }
    public function getSettings()
    {
        $this->checkAdmin();
        $settings = array();
        foreach ($this->pluginOptions as $key => $value) {
            $settings[$key] = $value;
        }
        $this->jsonSuccess($settings);
    }
    public function ping()
    {
        $this->jsonSuccess(array('time' => time(), 'plugin' => 'AdminBeautify'), 'pong');
    }
    public function checkUpdate()
    {
        $this->checkAdmin();
        require_once dirname(__FILE__) . '/Updater.php';
        $updater = new AdminBeautify_Updater();
        $release = $updater->fetchLatestRelease();
        if ($release === false) {
            $this->jsonError('无法连接 GitHub，请检查服务器网络', 502);
        }
        $current = AdminBeautify_Updater::CURRENT_VERSION;
        $latest = $release['version'];
        $hasUpdate = AdminBeautify_Updater::compareVersion($latest, $current) > 0;
        $canDirect = $hasUpdate && AdminBeautify_Updater::canDirectUpdate($current, $latest);
        $this->jsonSuccess(array('has_update' => $hasUpdate, 'current' => $current, 'latest' => $latest, 'can_direct' => $canDirect, 'html_url' => $release['html_url'], 'download_url' => $release['download_url'], 'body' => $release['body']), $hasUpdate ? '发现新版本 v' . $latest : '已是最新版本 v' . $current);
    }
    public function doUpdate()
    {
        $this->checkAdmin();
        require_once dirname(__FILE__) . '/Updater.php';
        $downloadUrl = trim($this->request->get('download_url', ''));
        $newVersion = trim($this->request->get('new_version', ''));
        if ($downloadUrl === '') {
            $this->jsonError('缺少 download_url 参数');
        }
        if ($newVersion === '') {
            $this->jsonError('缺少 new_version 参数');
        }
        $current = AdminBeautify_Updater::CURRENT_VERSION;
        if (!AdminBeautify_Updater::canDirectUpdate($current, $newVersion)) {
            $this->jsonError('版本跨度过大（当前 v' . $current . ' → v' . $newVersion . '），请前往 GitHub 手动更新', 400);
        }
        if (strpos($downloadUrl, 'github.com') === false && strpos($downloadUrl, 'codeload.github.com') === false) {
            $this->jsonError('下载地址不合法', 400);
        }
        $updater = new AdminBeautify_Updater();
        $result = $updater->doUpdate($downloadUrl, $newVersion);
        if ($result['ok']) {
            $this->jsonSuccess(array('details' => $result['details'], 'new_version' => $newVersion), $result['msg']);
        } else {
            $this->jsonError($result['msg'] . "\n详情：" . implode("\n", $result['details']), 500);
        }
    }
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
            if (!isset($item['name']) || !isset($item['type'])) {
                continue;
            }
            if ($item['type'] !== 'file') {
                continue;
            }
            if (substr($item['name'], -3) !== '.js') {
                continue;
            }
            $filename = $item['name'];
            $downloadUrl = isset($item['download_url']) ? $item['download_url'] : '';
            if ($downloadUrl === '') {
                continue;
            }
            $remoteContent = $this->httpGet($downloadUrl);
            if ($remoteContent === false) {
                $results[] = array('file' => $filename, 'status' => 'error', 'msg' => '下载失败');
                continue;
            }
            $remoteVersion = $this->parseVersionFromContent($remoteContent);
            $localPath = $compatDir . $filename;
            if (!file_exists($localPath)) {
                if (file_put_contents($localPath, $remoteContent) !== false) {
                    $results[] = array('file' => $filename, 'status' => 'added', 'version' => $remoteVersion, 'msg' => '新增');
                } else {
                    $results[] = array('file' => $filename, 'status' => 'error', 'msg' => '写入失败');
                }
            } else {
                $localContent = @file_get_contents($localPath, false, null, 0, 2048);
                $localVersion = $localContent !== false ? $this->parseVersionFromContent($localContent) : '0';
                if ($this->compareVersion($remoteVersion, $localVersion) > 0) {
                    if (file_put_contents($localPath, $remoteContent) !== false) {
                        $results[] = array('file' => $filename, 'status' => 'updated', 'version' => $remoteVersion, 'localVersion' => $localVersion, 'msg' => '已更新 v' . $localVersion . ' → v' . $remoteVersion);
                    } else {
                        $results[] = array('file' => $filename, 'status' => 'error', 'msg' => '写入失败');
                    }
                } else {
                    $results[] = array('file' => $filename, 'status' => 'skip', 'version' => $localVersion, 'msg' => '已是最新 v' . $localVersion);
                }
            }
        }
        $added = count(array_filter($results, function ($r) {
            return $r['status'] === 'added';
        }));
        $updated = count(array_filter($results, function ($r) {
            return $r['status'] === 'updated';
        }));
        $skipped = count(array_filter($results, function ($r) {
            return $r['status'] === 'skip';
        }));
        $errors = count(array_filter($results, function ($r) {
            return $r['status'] === 'error';
        }));
        $this->jsonSuccess(array('results' => $results, 'summary' => array('added' => $added, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors, 'total' => count($results))), '同步完成：新增 ' . $added . ' 个，更新 ' . $updated . ' 个，跳过 ' . $skipped . ' 个' . ($errors > 0 ? '，失败 ' . $errors . ' 个' : ''));
    }
    private function httpGet($url)
    {
        $opts = array('http' => array('method' => 'GET', 'timeout' => 15, 'header' => "User-Agent: AdminBeautify-Typecho-Plugin/2.1.0\r\n" . "Accept: application/vnd.github.v3+json\r\n"), 'ssl' => array('verify_peer' => false, 'verify_peer_name' => false));
        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);
        if ($result !== false) {
            return $result;
        }
        if (!function_exists('curl_init')) {
            return false;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_USERAGENT => 'AdminBeautify-Typecho-Plugin/2.1.0', CURLOPT_HTTPHEADER => array('Accept: application/vnd.github.v3+json')));
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $result !== false && $httpCode === 200 ? $result : false;
    }
    private function parseVersionFromContent($content)
    {
        if (preg_match('/@version\\s+([^\\s\\*\\/]+)/i', $content, $m)) {
            return trim($m[1]);
        }
        return '0';
    }
    private function compareVersion($a, $b)
    {
        $pa = array_map('intval', explode('.', ltrim((string) $a, 'vV')));
        $pb = array_map('intval', explode('.', ltrim((string) $b, 'vV')));
        $len = max(count($pa), count($pb));
        for ($i = 0; $i < $len; $i++) {
            $na = isset($pa[$i]) ? $pa[$i] : 0;
            $nb = isset($pb[$i]) ? $pb[$i] : 0;
            if ($na > $nb) {
                return 1;
            }
            if ($na < $nb) {
                return -1;
            }
        }
        return 0;
    }
    public function action()
    {
        $do = $this->request->get('do', '');
        if ($do === 'manifest') {
            $this->manifest();
            return;
        }
        if ($do === 'sw') {
            $this->sw();
            return;
        }
        $this->response->setContentType('application/json');
        switch ($do) {
            case 'ping':
                $this->ping();
                break;
            case 'info':
                $this->info();
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
            default:
                $this->jsonError('未知的操作: ' . $do, 404);
                break;
        }
    }
}
