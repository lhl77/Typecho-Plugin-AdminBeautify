<?php
/**
 * AdminBeautify AJAX Action Handler
 *
 * @package AdminBeautify
 * @author LHL
 * @version 2.1.32
 * @link https://blog.lhl.one
 */
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
    private function sendJsonRaw(array $payload)
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    private function jsonSuccess($data = null, $message = 'ok')
    {
        $this->sendJsonRaw(array(
            'code'    => 0,
            'message' => $message,
            'data'    => $data,
        ));
    }
    private function jsonError($message = 'error', $code = 400)
    {
        $this->sendJsonRaw(array(
            'code'    => $code,
            'message' => $message,
            'data'    => null,
        ));
    }
    private function _sendJsonAndContinue(array $payload)
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Connection: close');
            header('Content-Length: ' . strlen($body));
        }
        echo $body;
        @ob_start();
        @ob_end_flush();
        @flush();
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
    }
    public function manifest()
    {
        $siteTitle = (string) $this->options->title;
        $adminUrl  = rtrim((string) $this->options->adminUrl, '/') . '/';
        $pluginUrl = rtrim((string) $this->options->pluginUrl, '/');
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
        $pwaAppName = isset($this->pluginOptions->pwa_appName) ? trim((string) $this->pluginOptions->pwa_appName) : '';
        $pwaAppIcon = isset($this->pluginOptions->pwa_appIcon) ? trim((string) $this->pluginOptions->pwa_appIcon) : '';
        $appName = ($pwaAppName !== '') ? $pwaAppName : ($siteTitle . ' 管理后台');
        $shortName = ($pwaAppName !== '') ? $pwaAppName : ($siteTitle ?: 'Admin');
        $icons = array();
        if ($pwaAppIcon !== '') {
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
        $this->response->setHeader('Cache-Control', 'public, max-age=3600');
        echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
    public function sw()
    {
        $options    = $this->options;
        $pluginUrl  = rtrim((string) $options->pluginUrl, '/');
        $pluginVer  = '2.1.32';
        $cssUrl     = $pluginUrl . '/AdminBeautify/assets/AdminBeautify.v' . $pluginVer . '.css';
        $jsUrl      = $pluginUrl . '/AdminBeautify/assets/AdminBeautify.min.v' . $pluginVer . '.js';
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
    public function stats()
    {
        $this->checkAuth();
        $db  = $this->db;
        $now = time();
        $weekAgo      = $now - 7 * 86400;
        $twoWeeksAgo  = $now - 14 * 86400;
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
    public function chartData()
    {
        $this->checkAuth();
        $db   = $this->db;
        $days = (int) $this->request->get('days', 30);
        $since = ($days > 0) ? (time() - $days * 86400) : 0;
        $freqSelect = $db->select('created')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish');
        if ($since > 0) {
            $freqSelect = $freqSelect->where('created >= ?', $since);
        }
        $freqRows = $db->fetchAll($freqSelect);
        $dayBucket = array();
        foreach ($freqRows as $row) {
            $dateKey = date('Y-m-d', (int) $row['created']);
            $dayBucket[$dateKey] = isset($dayBucket[$dateKey]) ? $dayBucket[$dateKey] + 1 : 1;
        }
        if ($days > 0) {
            $fullRange = array();
            for ($i = $days - 1; $i >= 0; $i--) {
                $d = date('Y-m-d', time() - $i * 86400);
                $fullRange[$d] = isset($dayBucket[$d]) ? $dayBucket[$d] : 0;
            }
            $frequency = array();
            foreach ($fullRange as $d => $c) {
                $frequency[] = array('date' => $d, 'count' => $c);
            }
        } else {
            ksort($dayBucket);
            $frequency = array();
            foreach ($dayBucket as $d => $c) {
                $frequency[] = array('date' => $d, 'count' => $c);
            }
        }
        $commentSelect = $db->select('cid')
            ->from('table.comments')
            ->where('status = ?', 'approved');
        if ($since > 0) {
            $commentSelect = $commentSelect->where('created >= ?', $since);
        }
        $commentRows = $db->fetchAll($commentSelect);
        $cids = array();
        foreach ($commentRows as $r) {
            $cid = (int) $r['cid'];
            $cids[$cid] = isset($cids[$cid]) ? $cids[$cid] + 1 : 1;
        }
        $commentsByCat = array();
        if (!empty($cids)) {
            $allCatRows = $db->fetchAll(
                $db->select('mid', 'name', 'parent')
                   ->from('table.metas')
                   ->where('type = ?', 'category')
            );
            $catMap = array();
            foreach ($allCatRows as $cr) {
                $catMap[(int)$cr['mid']] = array('name' => $cr['name'], 'parent' => (int)$cr['parent']);
            }
            $childMids = array();
            foreach ($catMap as $mid => $info) {
                if ($info['parent'] > 0) {
                    $childMids[$info['parent']] = true;
                }
            }
            $leafMids = array();
            foreach ($catMap as $mid => $info) {
                if (!isset($childMids[$mid])) {
                    $leafMids[$mid] = true;
                }
            }
            $catPathCache = array();
            $buildPath = function($mid) use (&$catMap, &$catPathCache, &$buildPath) {
                if (isset($catPathCache[$mid])) return $catPathCache[$mid];
                if (!isset($catMap[$mid])) return '';
                $info = $catMap[$mid];
                if ($info['parent'] > 0 && isset($catMap[$info['parent']])) {
                    $parentPath = $buildPath($info['parent']);
                    $path = ($parentPath !== '') ? $parentPath . '-' . $info['name'] : $info['name'];
                } else {
                    $path = $info['name'];
                }
                $catPathCache[$mid] = $path;
                return $path;
            };
            $relRows = $db->fetchAll(
                $db->select('table.relationships.cid', 'table.relationships.mid')
                   ->from('table.relationships')
                   ->join('table.metas', 'table.relationships.mid = table.metas.mid')
                   ->where('table.metas.type = ?', 'category')
                   ->where('table.relationships.cid IN ?', array_keys($cids))
            );
            $cidMids = array();
            foreach ($relRows as $rr) {
                $rc = (int)$rr['cid'];
                $rm = (int)$rr['mid'];
                if (!isset($cidMids[$rc])) $cidMids[$rc] = array();
                $cidMids[$rc][] = $rm;
            }
            $catBucket = array();
            foreach ($cidMids as $rc => $mids) {
                $cnt = isset($cids[$rc]) ? $cids[$rc] : 0;
                $useMids = array_filter($mids, function($m) use (&$leafMids) {
                    return isset($leafMids[$m]);
                });
                if (empty($useMids)) $useMids = $mids;
                foreach ($useMids as $m) {
                    $path = $buildPath($m);
                    if ($path === '') continue;
                    $catBucket[$path] = isset($catBucket[$path]) ? $catBucket[$path] + $cnt : $cnt;
                }
            }
            arsort($catBucket);
            foreach ($catBucket as $name => $count) {
                $commentsByCat[] = array('name' => $name, 'count' => $count);
            }
        }
        $this->jsonSuccess(array(
            'days'          => $days,
            'frequency'     => $frequency,
            'commentsByCat' => $commentsByCat,
        ));
    }
    public function saveSettings()
    {
        $this->checkAdmin();
        $settings = $this->request->from(
            'primaryColor', 'darkMode', 'borderRadius',
            'enableAnimation', 'navPosition'
        );
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
    public function getSettings()
    {
        $this->checkAdmin();
        $settings = array();
        foreach ($this->pluginOptions as $key => $value) {
            $settings[$key] = $value;
        }
        $this->jsonSuccess($settings);
    }
    public function uploadMedia()
    {
        ob_start();
        try {
            $this->_doUploadMedia();
        } catch (\Throwable $e) {
            error_log('[AdminBeautify] uploadMedia fatal: ' . get_class($e) . ': ' . $e->getMessage());
            $this->jsonError('上传处理出错：' . $e->getMessage(), 500);
        }
    }
    private function _doUploadMedia()
    {
        $this->checkAuth();
        if (empty($_FILES['files'])) {
            $this->jsonError('没有收到文件', 400);
            return;
        }
        $parent = max(0, (int)$this->request->get('parent', 0));
        $rawFiles = $_FILES['files'];
        $fileList = array();
        if (is_array($rawFiles['name'])) {
            $count = count($rawFiles['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($rawFiles['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                $fileList[] = array(
                    'name'     => $rawFiles['name'][$i],
                    'tmp_name' => $rawFiles['tmp_name'][$i],
                    'size'     => $rawFiles['size'][$i],
                    'type'     => $rawFiles['type'][$i],
                    'error'    => $rawFiles['error'][$i],
                );
            }
        } else {
            if ($rawFiles['error'] === UPLOAD_ERR_OK) {
                $fileList[] = $rawFiles;
            }
        }
        if (empty($fileList)) {
            $this->jsonError('所有文件上传失败（可能超出大小限制）', 400);
            return;
        }
        $results  = array();
        $failures = array();
        foreach ($fileList as $file) {
            try {
                $uploadResult = \Widget\Upload::uploadHandle($file);
            } catch (\Throwable $e) {
                $failures[] = $file['name'] . ': ' . $e->getMessage();
                continue;
            }
            if (!$uploadResult || !is_array($uploadResult) || empty($uploadResult['path'])) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext && !\Widget\Upload::checkFileType($ext)) {
                    $failures[] = $file['name'] . ': 不支持的文件类型（.' . $ext . '）';
                } else {
                    $failures[] = $file['name'] . ': 上传失败，请检查文件大小限制或服务器权限';
                }
                continue;
            }
            try {
                $now   = time();
                $title = isset($uploadResult['name']) ? $uploadResult['name'] : basename($uploadResult['path']);
                $db   = $this->db;
                $user = Typecho_Widget::widget('Widget_User');
                $slugBase = pathinfo($title, PATHINFO_FILENAME);
                $slugBase = preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fa5}_-]/u', '-', $slugBase);
                $slugBase = trim(preg_replace('/-{2,}/', '-', $slugBase), '-');
                if (empty($slugBase)) {
                    $slugBase = 'attachment';
                }
                $slug = $slugBase . '-' . $now . '-' . substr(md5(uniqid('', true)), 0, 8);
                $insertData = array(
                    'title'        => $title,
                    'slug'         => $slug,
                    'created'      => $now,
                    'modified'     => $now,
                    'text'         => json_encode($uploadResult),
                    'authorId'     => (int)$user->uid,
                    'type'         => 'attachment',
                    'status'       => 'publish',
                    'password'     => '',
                    'commentsNum'  => 0,
                    'allowComment' => '0',
                    'allowPing'    => '0',
                    'allowFeed'    => '0',
                    'parent'       => $parent,
                );
                $cid = (int)$db->query($db->insert('table.contents')->rows($insertData));
                $url = '';
                try {
                    $attachConfig = new Typecho_Config($uploadResult);
                    $url = \Widget\Upload::attachmentHandle($attachConfig);
                } catch (\Throwable $e) {
                }
                if (empty($url)) {
                    $path = $uploadResult['path'];
                    if (strpos($path, 'http') === 0 || strpos($path, '//') === 0) {
                        $url = $path;
                    } else {
                        $siteUrl = rtrim((string)$this->options->siteUrl, '/');
                        $url     = $siteUrl . '/' . ltrim($path, '/');
                    }
                }
                $results[] = array(
                    'cid'  => $cid,
                    'name' => $title,
                    'url'  => $url,
                    'mime' => isset($uploadResult['mime']) ? $uploadResult['mime'] : '',
                    'size' => isset($uploadResult['size']) ? (int)$uploadResult['size'] : 0,
                );
            } catch (\Throwable $e) {
                error_log('[AdminBeautify] uploadMedia DB error: ' . get_class($e) . ': ' . $e->getMessage());
                $failures[] = $file['name'] . ': 数据库写入失败（' . $e->getMessage() . '）';
            }
        }
        $this->jsonSuccess(array(
            'uploaded' => $results,
            'failed'   => $failures,
        ));
    }
    public function listMedia()
    {
        $this->checkAuth();
        $page   = max(1, (int)$this->request->get('page', 1));
        $per    = min(100, max(1, (int)$this->request->get('per', 20)));
        $parent = (int)$this->request->get('parent', -1);
        $search = trim((string)$this->request->get('search', ''));
        $offset = ($page - 1) * $per;
        try {
            $db = $this->db;
            $baseQuery = $db->select('COUNT(cid)', 'num')
                ->from('table.contents')
                ->where('type = ?', 'attachment');
            $dataQuery = $db->select('cid', 'title', 'text', 'authorId', 'created', 'parent')
                ->from('table.contents')
                ->where('type = ?', 'attachment');
            if ($parent >= 0) {
                $baseQuery->where('parent = ?', $parent);
                $dataQuery->where('parent = ?', $parent);
            }
            if ($search !== '') {
                $like = '%' . str_replace(array('%', '_'), array('\\%', '\\_'), $search) . '%';
                $baseQuery->where('title LIKE ?', $like);
                $dataQuery->where('title LIKE ?', $like);
            }
            $totalRow = $db->fetchObject($db->select(array('COUNT(cid)' => 'num'))
                ->from('table.contents')
                ->where('type = ?', 'attachment'));
            $countQ = $db->select(array('COUNT(cid)' => 'num'))
                ->from('table.contents')
                ->where('type = ?', 'attachment');
            if ($parent >= 0) {
                $countQ->where('parent = ?', $parent);
            }
            if ($search !== '') {
                $like = '%' . str_replace(array('%', '_'), array('\\%', '\\_'), $search) . '%';
                $countQ->where('title LIKE ?', $like);
            }
            $totalRow = $db->fetchObject($countQ);
            $total = $totalRow ? (int)$totalRow->num : 0;
            $dataQ = $db->select('cid', 'title', 'text', 'created', 'parent')
                ->from('table.contents')
                ->where('type = ?', 'attachment');
            if ($parent >= 0) {
                $dataQ->where('parent = ?', $parent);
            }
            if ($search !== '') {
                $like = '%' . str_replace(array('%', '_'), array('\\%', '\\_'), $search) . '%';
                $dataQ->where('title LIKE ?', $like);
            }
            $dataQ->order('cid', \Typecho\Db::SORT_DESC)->page($page, $per);
            $rows = $db->fetchAll($dataQ);
            $items = array();
            foreach ($rows as $row) {
                $content = json_decode($row['text'], true);
                if (!is_array($content)) { $content = array(); }
                $url = '';
                try {
                    $attachCfg = new \Typecho\Config($content);
                    $url = \Widget\Upload::attachmentHandle($attachCfg);
                } catch (\Throwable $e) {}
                if (empty($url)) {
                    $path = isset($content['path']) ? $content['path'] : '';
                    if (strpos($path, 'http') === 0 || strpos($path, '//') === 0) {
                        $url = $path;
                    } elseif ($path !== '') {
                        $siteUrl = rtrim((string)$this->options->siteUrl, '/');
                        $url     = $siteUrl . '/' . ltrim($path, '/');
                    }
                }
                $items[] = array(
                    'cid'     => (int)$row['cid'],
                    'name'    => $row['title'],
                    'url'     => $url,
                    'mime'    => isset($content['mime']) ? $content['mime'] : '',
                    'size'    => isset($content['size']) ? (int)$content['size'] : 0,
                    'created' => (int)$row['created'],
                    'parent'  => (int)$row['parent'],
                );
            }
            $this->jsonSuccess(array(
                'total' => $total,
                'page'  => $page,
                'per'   => $per,
                'items' => $items,
            ));
        } catch (\Throwable $e) {
            error_log('[AdminBeautify] listMedia error: ' . $e->getMessage());
            $this->jsonError('获取媒体列表失败：' . $e->getMessage(), 500);
        }
    }
    public function deleteMedia()
    {
        $this->checkAuth();
        $cid = (int)$this->request->get('cid', 0);
        if ($cid <= 0) {
            $this->jsonError('缺少 cid 参数', 400);
            return;
        }
        try {
            $db = $this->db;
            $row = $db->fetchRow(
                $db->select('cid', 'text', 'type')
                    ->from('table.contents')
                    ->where('cid = ?', $cid)
                    ->where('type = ?', 'attachment')
                    ->limit(1)
            );
            if (!$row) {
                $this->jsonError('附件不存在', 404);
                return;
            }
            $content = json_decode($row['text'], true);
            if (is_array($content)) {
                try {
                    \Widget\Upload::deleteHandle(new \Typecho\Config($content));
                } catch (\Throwable $e) {
                    error_log('[AdminBeautify] deleteMedia deleteHandle error: ' . $e->getMessage());
                }
            }
            $db->query($db->delete('table.contents')->where('cid = ?', $cid));
            $this->jsonSuccess(array('cid' => $cid));
        } catch (\Throwable $e) {
            error_log('[AdminBeautify] deleteMedia error: ' . $e->getMessage());
            $this->jsonError('删除失败：' . $e->getMessage(), 500);
        }
    }
    public function getMediaUrls()
    {
        $this->checkAuth();
        $cidParam = $this->request->get('cids', '');
        if (empty($cidParam)) {
            $this->jsonSuccess(array());
            return;
        }
        $rawCids = explode(',', (string)$cidParam);
        $cids = array_values(array_filter(array_map('intval', $rawCids)));
        if (empty($cids)) {
            $this->jsonSuccess(array());
            return;
        }
        $result = array();
        try {
            $rows = $this->db->fetchAll(
                $this->db->select('cid', 'text')
                    ->from('table.contents')
                    ->where('type = ?', 'attachment')
                    ->where('cid IN ?', $cids)
            );
            foreach ($rows as $row) {
                $cid     = (int)$row['cid'];
                $content = json_decode($row['text'], true);
                if (!is_array($content) || empty($content['path'])) {
                    continue;
                }
                $url = '';
                try {
                    $attachment = new \Typecho\Config($content);
                    $url = \Widget\Upload::attachmentHandle($attachment);
                } catch (\Throwable $e) {
                }
                if (empty($url)) {
                    $path = $content['path'];
                    if (strpos($path, 'http') === 0 || strpos($path, '//') === 0) {
                        $url = $path;
                    } else {
                        $siteUrl = rtrim((string)$this->options->siteUrl, '/');
                        $url     = $siteUrl . '/' . ltrim($path, '/');
                    }
                }
                if (!empty($url)) {
                    $result[(string)$cid] = $url;
                }
            }
        } catch (Exception $e) {
            error_log('[AdminBeautify] getMediaUrls error: ' . $e->getMessage());
        }
        $this->jsonSuccess($result);
    }
    public function ping()
    {
        $this->jsonSuccess(array(
            'time'   => time(),
            'plugin' => 'AdminBeautify',
        ), 'pong');
    }
    public function getNotice()
    {
        $this->checkAdmin();
        $cacheFile = dirname(__FILE__) . '/tmp_update/.notice_cache.txt';
        $lockFile  = dirname(__FILE__) . '/tmp_update/.notice_lock';
        $cacheTTL  = 1800;
        $cachedContent = null;
        $cacheAge      = PHP_INT_MAX;
        if (file_exists($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            if ($raw !== false && trim($raw) !== '') {
                $cachedContent = $raw;
                $cacheAge      = time() - (int) @filemtime($cacheFile);
            }
        }
        $isStale        = ($cacheAge >= $cacheTTL);
        $alreadyLocked  = (file_exists($lockFile) && (time() - (int) @filemtime($lockFile)) < 60);
        $needsBgRefresh = ($isStale || $cachedContent === null) && !$alreadyLocked;
        if ($cachedContent !== null) {
            $this->_sendJsonAndContinue(array(
                'code'    => 0,
                'message' => 'ok',
                'data'    => array('content' => $cachedContent, 'from_cache' => true, 'cache_stale' => $isStale),
            ));
        } else {
            $this->_sendJsonAndContinue(array(
                'code'    => 0,
                'message' => 'ok',
                'data'    => array('content' => '', 'from_cache' => false, 'cache_stale' => true),
            ));
        }
        if ($needsBgRefresh) {
            $cacheDir = dirname($cacheFile);
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
            @file_put_contents($lockFile, time());
            @ignore_user_abort(true);
            @set_time_limit(60);
            $noticeUrl = 'https://raw.githubusercontent.com/lhl77/Typecho-Raw-Nontification/main/AdminBeautify/notice.md';
            $content   = $this->httpGetWithMirror($noticeUrl, 8);
            if ($content !== false && trim($content) !== '') {
                @file_put_contents($cacheFile, $content);
            }
            @unlink($lockFile);
        }
        exit;
    }
    public function checkUpdate()
    {
        $this->checkAdmin();
        require_once dirname(__FILE__) . '/Updater.php';
        $cacheFile = dirname(__FILE__) . '/tmp_update/.update_cache.json';
        $lockFile  = dirname(__FILE__) . '/tmp_update/.update_lock';
        $cacheTTL  = 3600;
        if ($this->request->get('force', '0') === '1') {
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
            }
            $updater = new AdminBeautify_Updater();
            $release = $updater->fetchLatestRelease();
            if ($release === false) {
                if (file_exists($cacheFile)) {
                    $raw = @file_get_contents($cacheFile);
                    $obj = $raw ? @json_decode($raw, true) : null;
                    if (is_array($obj) && isset($obj['data'])) {
                        $this->jsonSuccess(
                            array_merge($obj['data'], array('from_cache' => true, 'force_failed' => true)),
                            '网络不可用，显示上次缓存结果'
                        );
                    }
                }
                $this->jsonError('无法连接 GitHub，请检查服务器网络', 502);
            }
            $current   = AdminBeautify_Updater::CURRENT_VERSION;
            $latest    = $release['version'];
            $hasUpdate = AdminBeautify_Updater::compareVersion($latest, $current) > 0;
            $canDirect = $hasUpdate && AdminBeautify_Updater::canDirectUpdate($current, $latest);
            $freshData = array(
                'has_update'   => $hasUpdate,
                'current'      => $current,
                'latest'       => $latest,
                'can_direct'   => $canDirect,
                'html_url'     => $release['html_url'],
                'download_url' => $release['download_url'],
                'body'         => $release['body'],
                'from_cache'   => false,
            );
            $cacheDir = dirname($cacheFile);
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
            $cacheData = $freshData;
            unset($cacheData['from_cache']);
            @file_put_contents($cacheFile, json_encode(array('ts' => time(), 'data' => $cacheData)));
            @unlink($lockFile);
            $this->jsonSuccess(
                $freshData,
                $hasUpdate ? '发现新版本 v' . $latest : '已是最新版本 v' . $current
            );
        }
        $cachedData = null;
        $cacheAge   = PHP_INT_MAX;
        if (file_exists($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            $obj = $raw ? @json_decode($raw, true) : null;
            if (is_array($obj) && isset($obj['ts'], $obj['data'])) {
                $cacheAge   = time() - (int) $obj['ts'];
                $cachedData = $obj['data'];
            }
        }
        $isStale        = ($cacheAge >= $cacheTTL);
        $alreadyLocked  = (file_exists($lockFile) && (time() - (int) @filemtime($lockFile)) < 60);
        $needsBgRefresh = ($isStale || $cachedData === null) && !$alreadyLocked;
        if ($cachedData !== null) {
            $this->_sendJsonAndContinue(array(
                'code'    => 0,
                'message' => $isStale ? '检查中，显示上次缓存' : (
                    (isset($cachedData['has_update']) && $cachedData['has_update'])
                        ? '发现新版本 v' . $cachedData['latest']
                        : '已是最新版本 v' . $cachedData['current']
                ),
                'data'    => array_merge($cachedData, array(
                    'from_cache'  => true,
                    'cache_stale' => $isStale,
                )),
            ));
        } else {
            $this->_sendJsonAndContinue(array(
                'code'    => 0,
                'message' => '正在后台检查更新...',
                'data'    => array(
                    'has_update'  => false,
                    'current'     => AdminBeautify_Updater::CURRENT_VERSION,
                    'latest'      => null,
                    'cache_stale' => true,
                    'checking'    => true,
                ),
            ));
        }
        if ($needsBgRefresh) {
            $cacheDir = dirname($cacheFile);
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
            @file_put_contents($lockFile, time());
            @ignore_user_abort(true);
            @set_time_limit(60);
            $updater = new AdminBeautify_Updater();
            $release = $updater->fetchLatestRelease();
            if ($release !== false) {
                $current   = AdminBeautify_Updater::CURRENT_VERSION;
                $latest    = $release['version'];
                $hasUpdate = AdminBeautify_Updater::compareVersion($latest, $current) > 0;
                $canDirect = $hasUpdate && AdminBeautify_Updater::canDirectUpdate($current, $latest);
                $freshData = array(
                    'has_update'   => $hasUpdate,
                    'current'      => $current,
                    'latest'       => $latest,
                    'can_direct'   => $canDirect,
                    'html_url'     => $release['html_url'],
                    'download_url' => $release['download_url'],
                    'body'         => $release['body'],
                );
                @file_put_contents($cacheFile, json_encode(array('ts' => time(), 'data' => $freshData)));
            }
            @unlink($lockFile);
        }
        exit;
    }
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
        $current = AdminBeautify_Updater::CURRENT_VERSION;
        if (!AdminBeautify_Updater::canDirectUpdate($current, $newVersion)) {
            $this->jsonError('版本跨度过大（当前 v' . $current . ' → v' . $newVersion . '），请前往 GitHub 手动更新', 400);
        }
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
    public function doUpdateStream()
    {
        $this->checkAdmin();
        require_once dirname(__FILE__) . '/Updater.php';
        $downloadUrl = trim($this->request->get('download_url', ''));
        $newVersion  = trim($this->request->get('new_version', ''));
        if ($downloadUrl === '') {
            $this->response->setContentType('application/json');
            $this->jsonError('缺少 download_url 参数');
        }
        if ($newVersion === '') {
            $this->response->setContentType('application/json');
            $this->jsonError('缺少 new_version 参数');
        }
        $current = AdminBeautify_Updater::CURRENT_VERSION;
        if (!AdminBeautify_Updater::canDirectUpdate($current, $newVersion)) {
            $this->response->setContentType('application/json');
            $this->jsonError('版本跨度过大（当前 v' . $current . ' → v' . $newVersion . '），请前往 GitHub 手动更新', 400);
        }
        if (strpos($downloadUrl, 'github.com') === false && strpos($downloadUrl, 'codeload.github.com') === false) {
            $this->response->setContentType('application/json');
            $this->jsonError('下载地址不合法', 400);
        }
        @ini_set('zlib.output_compression', 'Off');
        while (ob_get_level() > 0) { @ob_end_clean(); }
        @set_time_limit(300);
        @ob_implicit_flush(1);
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');
        header('X-Content-Type-Options: nosniff');
        header('Connection: keep-alive');
        $emit = function ($type, $message, $progress) {
            $data = json_encode(array(
                'type'     => $type,
                'message'  => $message,
                'progress' => $progress,
            ), JSON_UNESCAPED_UNICODE);
            echo "data: {$data}\n\n";
            if (ob_get_level() > 0) @ob_flush();
            @flush();
        };
        $updater = new AdminBeautify_Updater();
        $updater->doUpdateStreaming($downloadUrl, $newVersion, $emit);
        exit;
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
            if (!isset($item['name']) || !isset($item['type'])) continue;
            if ($item['type'] !== 'file') continue;
            if (substr($item['name'], -3) !== '.js') continue;
            $filename    = $item['name'];
            $downloadUrl = isset($item['download_url']) ? $item['download_url'] : '';
            if ($downloadUrl === '') continue;
            $remoteContent = $this->httpGetWithMirror($downloadUrl);
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
    private static $GH_MIRRORS = array(
        'https://gh1.lhl.one/',
    );
    private function httpGetWithMirror($url, $timeout = 15)
    {
        foreach (self::$GH_MIRRORS as $mirror) {
            $result = $this->httpGet($mirror . $url, $timeout);
            if ($result !== false) return $result;
        }
        return false;
    }
    private function httpGet($url, $timeout = 15)
    {
        $opts = array(
            'http' => array(
                'method'  => 'GET',
                'timeout' => $timeout,
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
        if (!function_exists('curl_init')) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
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
    private function parseVersionFromContent($content)
    {
        if (preg_match('/@version\s+([^\s\*\/]+)/i', $content, $m)) {
            return trim($m[1]);
        }
        return '0';
    }
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
    public function umamiProxy()
    {
        $this->checkAuth();
        $opts   = $this->pluginOptions;
        $base   = rtrim($opts->umamiApiBase ?? '', '/');
        $token  = $opts->umamiApiToken ?? '';
        if (!$base || !$token) {
            $this->jsonError('Umami 未配置', 400);
            return;
        }
        $path = $this->request->get('path', '');
        if (!$path || strpos($path, '/api/') !== 0) {
            $this->jsonError('非法路径', 400);
            return;
        }
        $url = $base . $path;
        $body = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $body   = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err    = curl_error($ch);
            curl_close($ch);
            if ($body === false || $err) {
                $this->jsonError('代理请求失败: ' . $err, 502);
                return;
            }
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'GET',
                    'header'  => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
                    'timeout' => 10,
                ],
                'ssl' => ['verify_peer' => true],
            ]);
            $body   = @file_get_contents($url, false, $ctx);
            $status = 200;
            if ($body === false) {
                $this->jsonError('代理请求失败', 502);
                return;
            }
        }
        $data = json_decode($body, true);
        if ($data === null) {
            $this->jsonError('Umami 响应解析失败', 502);
            return;
        }
        $this->jsonSuccess($data);
    }
    public function fetchThemeList()
    {
        $this->checkAuth();
        $cacheDir  = dirname(__FILE__) . '/tmp_update';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        $cacheFile = $cacheDir . '/ab_theme_list_cache.json';
        $cacheTtl  = 3600;
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
            $cached = @file_get_contents($cacheFile);
            if ($cached !== false && strlen($cached) > 5) {
                while (ob_get_level() > 0) @ob_end_clean();
                if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
                echo $cached;
                exit;
            }
        }
        $remoteUrl = 'https://raw.githubusercontent.com/lhl77/Typecho-Raw-Nontification/refs/heads/main/AdminBeautify/theme.json';
        $mirrors   = array('https://gh1.lhl.one/' . $remoteUrl, $remoteUrl);
        $content   = false;
        foreach ($mirrors as $url) {
            $ctx = stream_context_create(array(
                'http' => array('method' => 'GET', 'timeout' => 15, 'follow_location' => 1,
                    'max_redirects' => 5, 'user_agent' => 'AdminBeautify-Typecho-Plugin/2.0'),
                'ssl'  => array('verify_peer' => false, 'verify_peer_name' => false),
            ));
            $content = @file_get_contents($url, false, $ctx);
            if ($content !== false && strlen($content) > 5) break;
            $content = false;
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
                    CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false, CURLOPT_USERAGENT => 'AdminBeautify-Typecho-Plugin/2.0'));
                $content  = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($content !== false && $httpCode === 200 && strlen($content) > 5) break;
                $content = false;
            }
        }
        if ($content === false) {
            if (is_file($cacheFile)) {
                $stale = @file_get_contents($cacheFile);
                if ($stale) {
                    while (ob_get_level() > 0) @ob_end_clean();
                    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
                    echo $stale;
                    exit;
                }
            }
            $this->jsonError('获取外观仓库列表失败，请检查服务器网络', 502);
        }
        $decoded = @json_decode($content, true);
        if (!is_array($decoded)) $this->jsonError('外观仓库数据格式错误', 500);
        @file_put_contents($cacheFile, $content, LOCK_EX);
        while (ob_get_level() > 0) @ob_end_clean();
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo $content;
        exit;
    }
    public function downloadTheme()
    {
        $this->checkAdmin();
        if (!class_exists('ZipArchive')) {
            $this->jsonError('PHP ZipArchive 扩展未安装，无法解压 ZIP', 500);
        }
        $zipUrl   = isset($_POST['url'])  ? trim($_POST['url'])  : '';
        $themeName = isset($_POST['name']) ? trim($_POST['name']) : '';
        if (empty($zipUrl) || !preg_match('#^https?://#i', $zipUrl)) {
            $this->jsonError('无效的下载地址', 400);
        }
        if ($themeName !== '' && !preg_match('/^[a-zA-Z0-9_\-]+$/', $themeName)) {
            $this->jsonError('主题目录名不合法', 400);
        }
        $themesDir = rtrim(dirname(dirname(dirname(__FILE__))), '/\\') . DIRECTORY_SEPARATOR . 'themes';
        if (!is_dir($themesDir))     $this->jsonError('themes 目录不存在: ' . $themesDir, 500);
        if (!is_writable($themesDir)) $this->jsonError('themes 目录不可写，请检查权限', 500);
        $mirrors    = array('https://gh1.lhl.one/' . $zipUrl, $zipUrl);
        $zipContent = false;
        foreach ($mirrors as $tryUrl) {
            $ctx = stream_context_create(array(
                'http' => array('method' => 'GET', 'timeout' => 90, 'follow_location' => 1,
                    'max_redirects' => 5, 'user_agent' => 'AdminBeautify-Typecho-Plugin/2.0'),
                'ssl'  => array('verify_peer' => false, 'verify_peer_name' => false),
            ));
            $zipContent = @file_get_contents($tryUrl, false, $ctx);
            if ($zipContent !== false && strlen($zipContent) >= 100) break;
            $zipContent = false;
            if (function_exists('curl_init')) {
                $ch = curl_init($tryUrl);
                curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90,
                    CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false, CURLOPT_USERAGENT => 'AdminBeautify-Typecho-Plugin/2.0'));
                $zipContent = curl_exec($ch);
                $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($zipContent !== false && $httpCode === 200 && strlen($zipContent) >= 100) break;
                $zipContent = false;
            }
        }
        if ($zipContent === false) $this->jsonError('下载外观 ZIP 失败，请检查服务器网络', 502);
        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ab_theme_' . md5(time() . $zipUrl) . '.zip';
        if (@file_put_contents($tmpFile, $zipContent) === false) {
            $this->jsonError('无法写入临时文件，请检查 temp 目录权限', 500);
        }
        unset($zipContent);
        $zip = new ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            @unlink($tmpFile);
            $this->jsonError('ZIP 文件损坏或无法打开', 500);
        }
        $zipRootDir = '';
        $firstEntry = $zip->getNameIndex(0);
        if ($firstEntry !== false) {
            $parts = explode('/', $firstEntry);
            if (count($parts) > 1) $zipRootDir = $parts[0] . '/';
        }
        if ($themeName === '') {
            $dirName   = rtrim($zipRootDir, '/');
            $dirName   = preg_replace('/-(?:main|master)$/', '', $dirName);
            $themeName = ($dirName !== '') ? $dirName : 'theme_' . time();
        }
        $targetDir = $themesDir . DIRECTORY_SEPARATOR . $themeName;
        $extracted = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry    = $zip->getNameIndex($i);
            if ($zipRootDir !== '' && strpos($entry, $zipRootDir) !== 0) continue;
            $relative = ($zipRootDir !== '') ? substr($entry, strlen($zipRootDir)) : $entry;
            if ($relative === '' || $relative === false) continue;
            if (strpos($relative, '..') !== false) continue;
            $destPath = $targetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (substr($entry, -1) === '/') {
                if (!is_dir($destPath)) @mkdir($destPath, 0755, true);
            } else {
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
                @file_put_contents($destPath, $zip->getFromIndex($i));
                $extracted++;
            }
        }
        $zip->close();
        @unlink($tmpFile);
        if ($extracted === 0) $this->jsonError('ZIP 解压失败：未找到有效文件，请手动安装', 500);
        $this->jsonSuccess(array(
            'theme_dir' => $themeName,
            'files'     => $extracted,
        ), '外观 "' . $themeName . '" 安装成功，共解压 ' . $extracted . ' 个文件');
    }
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
        $zipUrl = 'https://github.com/lhl77/Typecho-Plugin-AdminBeautifyStore/archive/refs/heads/main.zip';
        $zipMirrors = array(
            'https://gh1.lhl.one/' . $zipUrl,
        );
        $zipContent = false;
        foreach ($zipMirrors as $tryUrl) {
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
            $zipContent = @file_get_contents($tryUrl, false, $ctx);
            if ($zipContent !== false && strlen($zipContent) >= 100) break;
            $zipContent = false;
            if (function_exists('curl_init')) {
                $ch = curl_init($tryUrl);
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
                if ($zipContent !== false && $httpCode === 200 && strlen($zipContent) >= 100) break;
                $zipContent = false;
            }
        }
        if ($zipContent === false) {
            $this->jsonError('下载失败，已尝试直连及镜像代理，请检查服务器网络或稍后重试', 502);
        }
        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'abs_install_' . md5(time()) . '.zip';
        if (@file_put_contents($tmpFile, $zipContent) === false) {
            $this->jsonError('无法写入临时文件，请检查系统 temp 目录权限', 500);
        }
        unset($zipContent);
        $zip = new ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            @unlink($tmpFile);
            $this->jsonError('ZIP 文件损坏或无法打开', 500);
        }
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
            if (strpos($relative, '..') !== false) continue;
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
            @rmdir($targetDir);
            $this->jsonError('ZIP 解压失败：未找到有效文件（检查 ZIP 结构是否为 Typecho-Plugin-AdminBeautifyStore-main/），请手动下载安装', 500);
        }
        $this->jsonSuccess(array('dir' => 'AdminBeautifyStore'), '安装成功！请在插件管理页面启用 AdminBeautifyStore');
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
        if ($do === 'do-update-stream') {
            $this->doUpdateStream();
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
            case 'stats':
                $this->stats();
                break;
            case 'chart-data':
                $this->chartData();
                break;
            case 'get-settings':
                $this->getSettings();
                break;
            case 'get-media-urls':
                $this->getMediaUrls();
                break;
            case 'list-media':
                $this->listMedia();
                break;
            case 'delete-media':
                $this->deleteMedia();
                break;
            case 'upload-media':
                $this->uploadMedia();
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
            case 'get-notice':
                $this->getNotice();
                break;
            case 'do-update':
                $this->doUpdate();
                break;
            case 'install-abs':
                $this->installAbs();
                break;
            case 'fetch-theme-list':
                $this->fetchThemeList();
                break;
            case 'download-theme':
                $this->downloadTheme();
                break;
            case 'umami-proxy':
                $this->umamiProxy();
                break;
            default:
                $this->jsonError('未知的操作: ' . $do, 404);
                break;
        }
    }
}
