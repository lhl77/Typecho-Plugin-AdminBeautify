<?php
/**
 * AdminBeautify AJAX Action Handler
 *
 * @package AdminBeautify
 * @author LHL
 * @version 2.1.28
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
     * 底层 JSON 输出：清空所有输出缓冲区后直接用 header()+echo+exit 发送。
     * 完全绕过 Typecho 的 response 链（addResponder / respond / sandbox），
     * 是最可靠的 JSON 响应方式，对 ob_start / PicUp 等场景均安全。
     *
     * @param array $payload
     */
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

    /**
     * 输出 JSON 成功响应
     */
    private function jsonSuccess($data = null, $message = 'ok')
    {
        $this->sendJsonRaw(array(
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
        $this->sendJsonRaw(array(
            'code'    => $code,
            'message' => $message,
            'data'    => null,
        ));
    }

    /**
     * 立即将 JSON 发送给客户端并关闭 HTTP 连接，
     * 调用方在此之后仍可执行后台任务（不阻塞用户）。
     *
     * 兼容：PHP-FPM (fastcgi_finish_request) 和 mod_php (flush)。
     *
     * 关键：调用前必须已完成所有 Session 读写操作。
     * 本方法会在 flush 前主动 session_write_close()，
     * 释放 PHP Session 文件锁，避免后续同 Session 的请求（如 index.php 跳转）
     * 因等待锁而阻塞长达数十秒。
     */
    private function _sendJsonAndContinue(array $payload)
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        // 清空所有输出缓冲区
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        // ── 释放 Session 文件锁 ──────────────────────────────────────────────
        // 必须在 flush/fastcgi_finish_request 之前调用：
        // 虽然 fastcgi_finish_request() 关闭了 HTTP 连接，但 Session 锁仍然被
        // 当前 PHP 进程持有，直到脚本结束或显式释放。
        // 若此时用户跳转到新后台页面，新请求的 session_start() 将阻塞等待这把锁，
        // 造成 index.php 卡 20 秒的假象。
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        // 发送响应头
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Connection: close');
            header('Content-Length: ' . strlen($body));
        }

        echo $body;

        // 刷新并关闭连接
        @ob_start();
        @ob_end_flush();
        @flush();

        // PHP-FPM：立即通知 FastCGI 前端（Nginx/Apache）连接已完成
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
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
        $pluginVer  = '2.1.28';
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
     * 概要页图表数据
     * 访问方式：/action/admin-beautify?do=chart-data&days=30
     *
     * 返回：
     *   frequency      => [{date, count}]  — 文章发布频率（按日聚合）
     *   commentsByCat  => [{name, count}]  — 评论按分类分布
     */
    public function chartData()
    {
        $this->checkAuth();

        $db   = $this->db;
        $days = (int) $this->request->get('days', 30);
        // 0 = 全部；否则取指定天数
        $since = ($days > 0) ? (time() - $days * 86400) : 0;

        // ---- 1. 文章发布频率（按天聚合） ----
        $freqSelect = $db->select('created')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish');
        if ($since > 0) {
            $freqSelect = $freqSelect->where('created >= ?', $since);
        }
        $freqRows = $db->fetchAll($freqSelect);

        // 在 PHP 中按日聚合（兼容 MySQL / SQLite / PostgreSQL）
        $dayBucket = array();
        foreach ($freqRows as $row) {
            $dateKey = date('Y-m-d', (int) $row['created']);
            $dayBucket[$dateKey] = isset($dayBucket[$dateKey]) ? $dayBucket[$dateKey] + 1 : 1;
        }
        // 补全日期范围，未发文的天数填 0
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

        // ---- 2. 近期评论按分类分布 ----
        // 先取符合时间范围的已审核评论的 cid 列表
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
            // 加载全部分类（含层级关系）
            $allCatRows = $db->fetchAll(
                $db->select('mid', 'name', 'parent')
                   ->from('table.metas')
                   ->where('type = ?', 'category')
            );
            // 构建 catMap[mid] = ['name'=>..., 'parent'=>...]
            $catMap = array();
            foreach ($allCatRows as $cr) {
                $catMap[(int)$cr['mid']] = array('name' => $cr['name'], 'parent' => (int)$cr['parent']);
            }
            // 找出叶子分类（没有子分类的节点）
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
            // 构建分类完整路径：从叶子向上追溯，生成 "顶级-二级-最小分类"
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

            // 查询文章与分类的关系
            $relRows = $db->fetchAll(
                $db->select('table.relationships.cid', 'table.relationships.mid')
                   ->from('table.relationships')
                   ->join('table.metas', 'table.relationships.mid = table.metas.mid')
                   ->where('table.metas.type = ?', 'category')
                   ->where('table.relationships.cid IN ?', array_keys($cids))
            );

            // 按文章 cid 分组，收集该文章的所有分类 mid
            $cidMids = array();
            foreach ($relRows as $rr) {
                $rc = (int)$rr['cid'];
                $rm = (int)$rr['mid'];
                if (!isset($cidMids[$rc])) $cidMids[$rc] = array();
                $cidMids[$rc][] = $rm;
            }

            // 对每篇文章：只取最小叶子分类（若存在叶子则过滤，否则退回任意分类）
            $catBucket = array();
            foreach ($cidMids as $rc => $mids) {
                $cnt = isset($cids[$rc]) ? $cids[$rc] : 0;
                // 优先使用叶子分类
                $useMids = array_filter($mids, function($m) use (&$leafMids) {
                    return isset($leafMids[$m]);
                });
                if (empty($useMids)) $useMids = $mids;
                // 若同一篇文章属于多个叶子分类，各自累计
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
     * 上传文件（通过 Typecho 内置上传机制，兼容 PicUp 等插件 hook）
     * 访问方式：POST /action/admin-beautify?do=upload-media
     * Content-Type: multipart/form-data
     *
     * POST 参数：
     *   files[]  — 一个或多个文件
     *   parent   — 归属文章 cid（可选，默认 0 表示未归档）
     *
     * 返回：{ code:0, data:[ { cid, name, url, mime, size }, ... ] }
     */
    public function uploadMedia()
    {
        // 捕获处理过程中所有 PHP 警告/通知等杂散输出，确保 JSON 响应干净
        ob_start();

        try {
            $this->_doUploadMedia();
        } catch (\Throwable $e) {
            // 捕获任何未处理的 PHP 7+ Error / Exception（含 PicUp 驱动抛出的异常）
            error_log('[AdminBeautify] uploadMedia fatal: ' . get_class($e) . ': ' . $e->getMessage());
            $this->jsonError('上传处理出错：' . $e->getMessage(), 500);
        }
    }

    /**
     * uploadMedia 的实际逻辑，由 uploadMedia() 在外层 try/catch 中调用。
     */
    private function _doUploadMedia()
    {
        $this->checkAuth();

        if (empty($_FILES['files'])) {
            $this->jsonError('没有收到文件', 400);
            return;
        }

        $parent = max(0, (int)$this->request->get('parent', 0));

        // 将 PHP 的 $_FILES['files'] 转为 [文件索引 => 单文件数组] 的形式
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
            // 通过 Widget_Upload::uploadHandle 走完整的 Typecho 上传流程（含 PicUp 等 hook）
            try {
                $uploadResult = \Widget\Upload::uploadHandle($file);
            } catch (\Throwable $e) {
                $failures[] = $file['name'] . ': ' . $e->getMessage();
                continue;
            }

            if (!$uploadResult || !is_array($uploadResult) || empty($uploadResult['path'])) {
                // 尝试给出更具体的失败原因
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext && !\Widget\Upload::checkFileType($ext)) {
                    $failures[] = $file['name'] . ': 不支持的文件类型（.' . $ext . '）';
                } else {
                    $failures[] = $file['name'] . ': 上传失败，请检查文件大小限制或服务器权限';
                }
                continue;
            }

            // 写入 table.contents（与原生 Typecho 媒体上传流程一致，不写 table.fields）
            try {
                $now   = time();
                $title = isset($uploadResult['name']) ? $uploadResult['name'] : basename($uploadResult['path']);

                $db   = $this->db;
                $user = Typecho_Widget::widget('Widget_User');

                // 生成 URL 安全的唯一 slug。
                // 策略：取 title（原始文件名）去掉扩展名作为可读前缀，
                // 追加时间戳 + uniqid 随机串，无需 DB 查重，100% 不冲突。
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

                // 获取文件访问 URL（触发 PicUp 等 hook）
                $url = '';
                try {
                    $attachConfig = new Typecho_Config($uploadResult);
                    $url = \Widget\Upload::attachmentHandle($attachConfig);
                } catch (\Throwable $e) {
                    // 降级：URL 在下方通过 path 拼接
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

    /**
     * 列出附件（分页+搜索，支持按 parent 筛选）
     * 访问方式：GET /action/admin-beautify?do=list-media&page=1&per=20&parent=0&search=xxx
     *   parent=-1  → 全部附件
     *   parent=0   → 未归档（parent=0）
     *   parent=N   → 属于文章 N 的附件
     *
     * 返回：{ total, page, per, items:[ { cid, name, url, mime, size, created } ] }
     */
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

            // ── 构建基础查询条件 ──
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
            // 重新构建独立计数查询
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

                // 获取访问 URL
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

    /**
     * 删除附件（软删除：从 table.contents 移除，同时调用 Widget\Upload::deleteHandle 删除实际文件）
     * 访问方式：POST /action/admin-beautify?do=delete-media
     * POST 参数：cid — 附件 cid
     */
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

            // 取出附件信息
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

    /**
     * 批量获取媒体文件的实际访问 URL（兼容 PicUp 等插件的 CDN 路径）
     * 访问方式：GET /action/admin-beautify?do=get-media-urls&cids=1,2,3
     *
     * 返回：{ "1": "https://...", "2": "https://..." }
     */
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

                // 优先通过 Upload::attachmentHandle 获取 URL（触发 PicUp 等插件的 CDN 路径 hook）
                $url = '';
                try {
                    $attachment = new \Typecho\Config($content);
                    $url = \Widget\Upload::attachmentHandle($attachment);
                } catch (\Throwable $e) {
                    // 降级：直接用 path 字段
                }

                // 二次降级：path 若为完整 URL 直接用；否则拼接站点根
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
    /**
     * 服务端代理拉取 GitHub 公告 notice.md，解决浏览器直连 CORS / 被墙问题
     * 访问方式：/action/admin-beautify?do=get-notice
     *
     * 策略（stale-while-revalidate）：
     *   - 有缓存（任何新旧）→ 立即返回缓存，若已过期则在客户端连接关闭后后台刷新
     *   - 无缓存 → 立即返回空，同时后台异步拉取并写缓存（下次访问生效）
     */
    public function getNotice()
    {
        $this->checkAdmin();

        $cacheFile = dirname(__FILE__) . '/tmp_update/.notice_cache.txt';
        $lockFile  = dirname(__FILE__) . '/tmp_update/.notice_lock';
        $cacheTTL  = 1800; // 30 分钟

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

        // ── 立即响应 ──
        if ($cachedContent !== null) {
            $this->_sendJsonAndContinue(array(
                'code'    => 0,
                'message' => 'ok',
                'data'    => array('content' => $cachedContent, 'from_cache' => true, 'cache_stale' => $isStale),
            ));
        } else {
            // 完全没有缓存时也立即返回空（前端静默忽略）
            $this->_sendJsonAndContinue(array(
                'code'    => 0,
                'message' => 'ok',
                'data'    => array('content' => '', 'from_cache' => false, 'cache_stale' => true),
            ));
        }

        // ── 后台刷新（客户端已断开，不再阻塞任何 worker）──
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
        $cacheTTL  = 3600; // 1 小时

        // ── 手动强制检查（force=1）：跳过缓存，直接请求 GitHub 获取实时结果 ──
        if ($this->request->get('force', '0') === '1') {
            // 释放 Session 锁，避免 GitHub 请求（最长 ~20s）期间阻塞其他页面跳转
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
            }

            $updater = new AdminBeautify_Updater();
            $release = $updater->fetchLatestRelease();

            if ($release === false) {
                // 网络不通：降级返回缓存（如有）
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

            // 写入缓存，同时清除 lock，让后续自动检查直接命中新鲜缓存
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

        // ── 自动后台检查（非手动）：stale-while-revalidate，立即返回缓存 ──
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

        // ── 立即响应：不管缓存新旧，先把现有数据推给客户端 ──
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
            // 完全没有缓存：立即返回"正在检查"占位，后台填充
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

        // ── 后台刷新（客户端连接已关闭，不占用用户等待时间）──
        if ($needsBgRefresh) {
            $cacheDir = dirname($cacheFile);
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
            @file_put_contents($lockFile, time());

            @ignore_user_abort(true);
            @set_time_limit(60);

            $updater = new AdminBeautify_Updater();
            $release = $updater->fetchLatestRelease(); // 内部使用 5s 短超时 + 镜像兜底

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
     * 流式更新（SSE 版本）——实时推送下载/解压/覆盖进度
     * 访问方式（GET）：/action/admin-beautify?do=do-update-stream&download_url=xxx&new_version=xxx
     *
     * 响应格式：text/event-stream
     *   data: {"type":"downloading","message":"下载中 512 KB / 1024 KB","progress":50}
     *   data: {"type":"done","message":"更新成功！...","progress":100}
     *   data: {"type":"error","message":"...","progress":-1}
     */
    public function doUpdateStream()
    {
        $this->checkAdmin();

        require_once dirname(__FILE__) . '/Updater.php';

        $downloadUrl = trim($this->request->get('download_url', ''));
        $newVersion  = trim($this->request->get('new_version', ''));

        // 参数校验（此时尚未切换到 SSE，可以正常输出 JSON 错误）
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

        // ── 切换到 SSE 模式 ──
        // 关闭所有输出缓冲，确保 flush 实时生效
        // 禁用 gzip 压缩，防止数据积压导致 SSE 帧无法实时到达客户端
        @ini_set('zlib.output_compression', 'Off');

        while (ob_get_level() > 0) { @ob_end_clean(); }
        @set_time_limit(300); // 5 分钟足够完成更新
        // 隐式 flush：每次 echo 后自动发送，不依赖显式调用 flush()
        @ob_implicit_flush(1);

        // 释放 Session 锁，SSE 可能持续数分钟，不应阻塞同 Session 的其他请求
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');   // 禁用 nginx 缓冲
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

        // GitHub Contents API — 直连 api.github.com（不走镜像代理，避免封禁）
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

            // 下载远程文件内容（download_url 通常为 raw.githubusercontent.com，直连失败走镜像）
            $remoteContent = $this->httpGetWithMirror($downloadUrl);
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
     * GitHub 镜像代理列表（大陆可用），格式：直接附在原始 URL 前缀即可
     */
    private static $GH_MIRRORS = array(
        'https://gh1.lhl.one/',
    );

    /**
     * HTTP GET，直连失败时依次尝试大陆镜像代理（适用于 github.com / api.github.com / raw.githubusercontent.com）
     *
     * @param string $url     目标 URL
     * @param int    $timeout 每个节点超时秒数（默认 15s；检查类操作建议传 5s）
     */
    private function httpGetWithMirror($url, $timeout = 15)
    {
        foreach (self::$GH_MIRRORS as $mirror) {
            $result = $this->httpGet($mirror . $url, $timeout);
            if ($result !== false) return $result;
        }
        return false;
    }

    /**
     * 简单 HTTP GET（支持 file_get_contents 和 cURL 回退）
     *
     * @param string $url
     * @param int    $timeout 超时秒数
     */
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

        // 回退到 cURL
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
    /**
     * Umami API 代理 — 绕过跨域 Authorization 头限制
     *
     * 前端调用：/action/admin-beautify?do=umami-proxy&path=/api/websites/xxx/stats?startAt=...
     * 服务端用 curl/file_get_contents 转发请求并携带 Bearer Token。
     *
     * 访问方式：/action/admin-beautify?do=umami-proxy
     */
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

        // 只允许代理 /api/ 路径，防止 SSRF 滥用
        $path = $this->request->get('path', '');
        if (!$path || strpos($path, '/api/') !== 0) {
            $this->jsonError('非法路径', 400);
            return;
        }

        $url = $base . $path;

        // 尝试 curl，不存在则 fallback file_get_contents
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

    /**
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
        // 统一走 gh1.lhl.one 镜像
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

            // cURL 回退
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
        /* SSE 流式更新也需要在设置 JSON Content-Type 之前分发 */
        if ($do === 'do-update-stream') {
            $this->doUpdateStream();
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

            case 'umami-proxy':
                $this->umamiProxy();
                break;

            default:
                $this->jsonError('未知的操作: ' . $do, 404);
                break;
        }
    }
}
