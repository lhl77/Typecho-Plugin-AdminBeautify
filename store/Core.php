<?php
/**
 * AB Store 内置模块 —— 注册表 / 缓存 / 令牌 / 迁移
 *
 * 与旧 AB Store 插件（usr/plugins/AdminBeautifyStore）的数据兼容：
 *   · cache.json           → 不再使用（缓存改存 store/data/state.json）
 *   · token-plugins.json   → 首次运行迁移到 store/data/tokens.json
 *   · backup/              → 首次运行尽力拷到 usr/plugins/.ab-store/backup
 *   · 插件选项 notifyOptOut / downloadMode → 记进 state.json，沿用用户偏好
 *   · 通知中心里指向旧面板的 link → 一次性改写成 plugins.php?tab=store
 *     （必须改：pushNotice 命中 dedup 时 link 只在旧值为空时才更新，否则永远指向旧面板）
 *
 * @package AdminBeautify
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
class AdminBeautify_Store
{
    const BASE = 'https://ab-store.lhl.one';
    const CACHE_TTL = 1800;
    const PAGE_QUERY = 'plugins.php?tab=store';
    public static function pluginDir()
    {
        return rtrim(dirname(__DIR__), '/\\');
    }
    public static function pluginsDir()
    {
        return rtrim(__TYPECHO_ROOT_DIR__ . __TYPECHO_PLUGIN_DIR__, '/\\');
    }
    public static function dataDir()
    {
        return self::ensureDir(__DIR__ . '/data');
    }
    public static function backupDir()
    {
        return self::ensureDir(self::pluginsDir() . '/.ab-store/backup');
    }
    public static function tokenFile()
    {
        return self::ensureDir(self::pluginsDir() . '/.ab-store') . '/tokens.json';
    }
    private static function ensureDir($dir)
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
    public static function opt($key, $default = '')
    {
        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $po = AdminBeautify_Plugin::pluginOptions($options);
            if (isset($po->$key) && (string) $po->$key !== '') {
                return (string) $po->$key;
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        return $default;
    }
    public static function enabled()
    {
        if (is_file(__DIR__ . '/disabled')) {
            return false;
        }
        return self::opt('storeEnabled', '1') === '1';
    }
    public static function legacyStoreActive()
    {
        try {
            $export = Typecho_Plugin::export();
            return !empty($export['activated']['AdminBeautifyStore']);
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        return false;
    }
    public static function notifyOptOut()
    {
        return self::opt('storeNotifyOptOut', '0') === '1';
    }
    public static function adminUrl($suffix = '')
    {
        try {
            $options = Typecho_Widget::widget('Widget_Options');
            return rtrim((string) $options->adminUrl, '/') . '/' . ltrim($suffix, '/');
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        return '/admin/' . ltrim($suffix, '/');
    }
    public static function pageUrl()
    {
        return self::adminUrl(self::PAGE_QUERY);
    }
    public static function stateFile()
    {
        return self::dataDir() . '/state.json';
    }
    public static function state()
    {
        $file = self::stateFile();
        $data = array();
        if (is_file($file)) {
            $data = json_decode((string) @file_get_contents($file), true);
        }
        return is_array($data) ? $data : array();
    }
    public static function saveState(array $state)
    {
        @file_put_contents(
            self::stateFile(),
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }
    public static function setState($key, $value)
    {
        $state = self::state();
        $state[$key] = $value;
        self::saveState($state);
    }
    public static function registry($force = false)
    {
        $state = self::state();
        $cache = (isset($state['registry']) && is_array($state['registry'])) ? $state['registry'] : array();
        $at = isset($state['registryAt']) ? (int) $state['registryAt'] : 0;
        $fresh = $cache && (time() - $at) < self::CACHE_TTL;
        if (!$force && $fresh) {
            return array('plugins' => $cache, 'at' => $at, 'stale' => false, 'error' => '');
        }
        $list = self::fetchRegistry();
        if ($list !== null) {
            $state['registry'] = $list;
            $state['registryAt'] = time();
            self::saveState($state);
            return array('plugins' => $list, 'at' => time(), 'stale' => false, 'error' => '');
        }
        return array(
            'plugins' => $cache,
            'at'     => $at,
            'stale'  => true,
            'error'  => '商店服务暂时无法连接，下面只显示本机已安装的插件。',
        );
    }
    public static function fetchRegistry()
    {
        $raw = self::httpJson(self::BASE . '/api/plugins', 10);
        if (!is_array($raw) || empty($raw['ok']) || empty($raw['plugins']) || !is_array($raw['plugins'])) {
            return null;
        }
        $out = array();
        foreach ($raw['plugins'] as $p) {
            if (is_array($p)) {
                $out[] = self::normalize($p);
            }
        }
        return $out;
    }
    public static function normalize(array $p)
    {
        $id = isset($p['id']) ? trim((string) $p['id']) : '';
        $dir = isset($p['dir']) ? trim((string) $p['dir']) : '';
        $tags = array();
        if (isset($p['tags']) && is_array($p['tags'])) {
            foreach ($p['tags'] as $t) {
                $tags[] = (string) $t;
            }
        }
        return array(
            'id'          => $id,
            'name'        => isset($p['name']) ? (string) $p['name'] : $id,
            'directory'   => $dir,
            'version'     => isset($p['version']) ? (string) $p['version'] : '',
            'author'      => isset($p['author']) ? (string) $p['author'] : '',
            'description' => isset($p['desc']) ? (string) $p['desc'] : '',
            'homepage'    => isset($p['homepage']) ? (string) $p['homepage'] : '',
            'github'      => isset($p['github']) ? (string) $p['github'] : '',
            'tags'        => $tags,
            'downloads'   => isset($p['downloads']) ? (int) $p['downloads'] : 0,
            'downloadUrl' => $id === '' ? '' : self::BASE . '/api/download/' . rawurlencode($id),
            'minVer'      => isset($p['minVer']) ? (string) $p['minVer'] : '',
            'maxVer'      => isset($p['maxVer']) ? (string) $p['maxVer'] : '',
        );
    }
    public static function allPlugins($force = false)
    {
        $reg = self::registry($force);
        $list = $reg['plugins'];
        foreach (self::tokens() as $t) {
            $t['private'] = true;
            $list[] = $t;
        }
        $reg['plugins'] = $list;
        return $reg;
    }
    public static function find($id = '', $dir = '')
    {
        $reg = self::allPlugins();
        $id = strtolower(trim((string) $id));
        $dir = strtolower(trim((string) $dir));
        foreach ($reg['plugins'] as $p) {
            if ($id !== '' && strtolower($p['id']) === $id) {
                return $p;
            }
            if ($dir !== '' && strtolower($p['directory']) === $dir) {
                return $p;
            }
        }
        return null;
    }
    public static function tokens()
    {
        $file = self::tokenFile();
        if (!is_file($file)) {
            return array();
        }
        $data = json_decode((string) @file_get_contents($file), true);
        return is_array($data) ? $data : array();
    }
    public static function saveTokens(array $list)
    {
        @file_put_contents($file = self::tokenFile(), json_encode(
            array_values($list),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        ));
    }
    public static function addToken($id, $token)
    {
        $id = trim((string) $id);
        $token = trim((string) $token);
        if ($id === '') {
            return array('error' => '缺少插件 ID', 'code' => 400);
        }
        $url = self::BASE . '/api/plugin/' . rawurlencode($id)
            . ($token !== '' ? '?token=' . rawurlencode($token) : '');
        $resp = self::httpJson($url, 10);
        if (!is_array($resp) || empty($resp['ok']) || empty($resp['plugin'])) {
            return array('error' => '插件不存在或口令无效', 'code' => 400);
        }
        $p = self::normalize($resp['plugin']);
        $p['shareToken'] = $token;
        if (empty($p['directory'])) {
            $p['directory'] = $p['name'];
        }
        $list = array();
        $found = false;
        foreach (self::tokens() as $item) {
            if ($item['id'] === $p['id']) {
                $list[] = $p;
                $found = true;
            } else {
                $list[] = $item;
            }
        }
        if (!$found) {
            $list[] = $p;
        }
        self::saveTokens($list);
        return array('data' => array('id' => $p['id']), 'message' => '已添加：' . $p['name']);
    }
    public static function removeToken($id)
    {
        $id = trim((string) $id);
        $list = array();
        foreach (self::tokens() as $item) {
            if ($item['id'] !== $id) {
                $list[] = $item;
            }
        }
        self::saveTokens($list);
        return array('data' => array('id' => $id), 'message' => '已移除');
    }
    public static function blocked()
    {
        $state = self::state();
        return (isset($state['blocked']) && is_array($state['blocked'])) ? $state['blocked'] : array();
    }
    public static function block($dir, $version)
    {
        $state = self::state();
        if (!isset($state['blocked']) || !is_array($state['blocked'])) {
            $state['blocked'] = array();
        }
        $state['blocked'][$dir] = (string) $version;
        self::saveState($state);
    }
    public static function unblock($dir)
    {
        $state = self::state();
        if (isset($state['blocked'][$dir])) {
            unset($state['blocked'][$dir]);
            self::saveState($state);
        }
    }
    public static function migrate()
    {
        $state = self::state();
        if (!empty($state['migrated'])) {
            return;
        }
        $legacyDir = self::pluginsDir() . '/AdminBeautifyStore';
        $legacy = array('found' => is_dir($legacyDir));
        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $po = $options->plugin('AdminBeautifyStore');
            $legacy['notifyOptOut'] = isset($po->notifyOptOut) ? (string) $po->notifyOptOut : '';
            $legacy['downloadMode'] = isset($po->downloadMode) ? (string) $po->downloadMode : '';
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        $legacyTokens = $legacyDir . '/token-plugins.json';
        if (is_file($legacyTokens) && !is_file(self::tokenFile())) {
            $raw = @file_get_contents($legacyTokens);
            $list = json_decode((string) $raw, true);
            if (is_array($list)) {
                self::saveTokens($list);
                $legacy['tokens'] = count($list);
            }
        }
        $legacyBackup = $legacyDir . '/backup';
        if (is_dir($legacyBackup)) {
            $n = 0;
            foreach ((array) @glob($legacyBackup . '/*') as $src) {
                $dst = self::backupDir() . '/' . basename($src);
                if (!file_exists($dst)) {
                    AdminBeautify_StoreInstall::copyDir($src, $dst);
                    $n++;
                }
            }
            $legacy['backups'] = $n;
        }
        $legacy['links'] = self::rewriteNoticeLinks();
        $state['migrated'] = date('Y-m-d H:i:s');
        $state['legacy'] = $legacy;
        self::saveState($state);
    }
    public static function rewriteNoticeLinks()
    {
        $needles = array(
            'panel=AdminBeautifyStore%2FPanel.php',
            'panel=AdminBeautifyStore/Panel.php',
            'AdminBeautifyStore/Panel.php',
        );
        $target = self::pageUrl();
        $count = 0;
        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll($db->select('id', 'content')
                ->from('table.' . AdminBeautify_Plugin::CARD_TABLE)
                ->where('card_key LIKE ?', AdminBeautify_Plugin::NOTICE_KEY_PREFIX . '%'));
            foreach ($rows as $row) {
                $content = json_decode((string) $row['content'], true);
                if (!is_array($content) || empty($content['link'])) {
                    continue;
                }
                $link = (string) $content['link'];
                $hit = false;
                foreach ($needles as $n) {
                    if (strpos($link, $n) !== false) {
                        $hit = true;
                        break;
                    }
                }
                if (!$hit) {
                    continue;
                }
                $content['link'] = $target;
                if (isset($content['act']) && $content['act'] === 'open-self') {
                    $content['act'] = 'open-self';
                }
                $db->query($db->update('table.' . AdminBeautify_Plugin::CARD_TABLE)
                    ->rows(array('content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)))
                    ->where('id = ?', (int) $row['id']));
                $count++;
            }
        } catch (Exception $e) {
            return -1;
        } catch (Throwable $e) {
            return -1;
        }
        return $count;
    }
    public static function httpJson($url, $timeout = 8)
    {
        $raw = self::httpGet($url, $timeout);
        if ($raw === null || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
    public static function httpGet($url, $timeout = 8)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT      => 'AdminBeautify-Store/' . self::opt('pluginVersion', '2.1.62'),
                CURLOPT_HTTPHEADER     => array('Accept: application/json'),
            ));
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($raw !== false && $code >= 200 && $code < 400) ? (string) $raw : null;
        }
        $ctx = stream_context_create(array(
            'http' => array(
                'timeout'       => $timeout,
                'user_agent'    => 'AdminBeautify-Store/' . self::opt('pluginVersion', '2.1.62'),
                'ignore_errors' => true,
            ),
            'ssl'  => array('verify_peer' => true, 'verify_peer_name' => true),
        ));
        $raw = @file_get_contents($url, false, $ctx);
        return $raw === false ? null : (string) $raw;
    }
    public static function download($url, $file, $timeout = 30)
    {
        if (function_exists('curl_init')) {
            $fp = @fopen($file, 'wb');
            if (!$fp) {
                return false;
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT      => 'AdminBeautify-Store/' . self::opt('pluginVersion', '2.1.62'),
            ));
            $ok = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);
            return $ok !== false && $code >= 200 && $code < 400 && filesize($file) > 0;
        }
        $raw = self::httpGet($url, $timeout);
        if ($raw === null || $raw === '') {
            return false;
        }
        return (bool) @file_put_contents($file, $raw);
    }
    public static function safeDir($dir)
    {
        $dir = trim((string) $dir);
        $dir = str_replace(array('/', '\\', "\0"), '', $dir);
        if ($dir === '' || $dir === '.' || $dir === '..') {
            return '';
        }
        return preg_replace('/[^A-Za-z0-9_.\-]/', '', $dir);
    }
}
