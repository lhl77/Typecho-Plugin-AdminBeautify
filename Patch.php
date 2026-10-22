<?php
/**
 * AdminBeautify · Typecho 核心补丁
 *
 * 职责：从 lhl77/typecho-patches 拉取补丁清单 → 与本地文件哈希比对 → 一键应用 / 回滚。
 *
 * 设计要点（与 docs/core-patches.md 一致）：
 *   · 只允许改 var/、admin/ 与根目录白名单文件；拒绝 usr/、config.inc.php、.git、插件自身
 *   · 用 sha256 精确定位版本：命中 sha256_before 才允许打，等于 sha256_after 视为已打
 *   · 应用 = 备份 → 语法自检（token_get_all TOKEN_PARSE）→ 原子写 → 复核哈希
 *   · 任何一步失败整体还原，不允许部分应用
 *   · 状态（已打 / 备份 / 通知去重）落库（cardDataSave），不依赖 tmp_update/
 *
 * @package AdminBeautify
 */
class AdminBeautify_Patch
{
    const REPO_RAW = 'https://raw.githubusercontent.com/lhl77/typecho-patches/main/';
    const MIRROR = 'https://gh1.lhl.one/';
    const CDN = 'https://cdn.jsdelivr.net/gh/lhl77/typecho-patches@main/';
    const CACHE_TTL = 21600;
    const MAX_BYTES = 2097152;
    const KEEP_BACKUPS = 5;
    const STATE_KEY = 'core-patch-state';
    const BACKUP_DIR = '.patch-backup';
    private static $allowPrefix = array('var/', 'admin/');
    private static $allowRoot = array('index.php', 'install.php', '404.php');
    private static $denyPrefix = array('usr/', '.git/', '.svn/', 'node_modules/');
    private static $indexCache = null;
    private static $sourceBases = null;
    public static function setSourceBases($bases)
    {
        self::$sourceBases = $bases ? (array) $bases : null;
        self::$indexCache  = null;
    }
    public static function root()
    {
        if (defined('__TYPECHO_ROOT_DIR__') && __TYPECHO_ROOT_DIR__) {
            return rtrim(str_replace('\\', '/', __TYPECHO_ROOT_DIR__), '/');
        }
        return rtrim(str_replace('\\', '/', dirname(dirname(dirname(__FILE__)))), '/');
    }
    public static function typechoVersion()
    {
        if (defined('\\Typecho\\Common::VERSION')) {
            return (string) constant('\\Typecho\\Common::VERSION');
        }
        if (defined('Typecho_Common::VERSION')) {
            return (string) constant('Typecho_Common::VERSION');
        }
        $f = self::root() . '/var/Typecho/Common.php';
        if (is_readable($f)) {
            $src = (string) @file_get_contents($f);
            if (preg_match('/const\s+VERSION\s*=\s*[\'"]([0-9.]+)[\'"]/', $src, $m)) {
                return $m[1];
            }
        }
        return '';
    }
    private static function abVersion()
    {
        if (class_exists('AdminBeautify_Updater')) {
            return (string) AdminBeautify_Updater::CURRENT_VERSION;
        }
        $f = dirname(__FILE__) . '/Updater.php';
        if (is_readable($f)) {
            $src = (string) @file_get_contents($f);
            if (preg_match('/const\s+CURRENT_VERSION\s*=\s*[\'"]([0-9.]+)[\'"]/', $src, $m)) {
                return $m[1];
            }
        }
        return '0';
    }
    private static function cmpVersion($a, $b)
    {
        $pa = array_map('intval', explode('.', ltrim((string) $a, 'vV')));
        $pb = array_map('intval', explode('.', ltrim((string) $b, 'vV')));
        for ($i = 0; $i < 3; $i++) {
            $x = isset($pa[$i]) ? $pa[$i] : 0;
            $y = isset($pb[$i]) ? $pb[$i] : 0;
            if ($x > $y) return 1;
            if ($x < $y) return -1;
        }
        return 0;
    }
    private static function urls($rel)
    {
        $rel = ltrim($rel, '/');
        if (!empty(self::$sourceBases)) {
            $out = array();
            foreach (self::$sourceBases as $base) $out[] = rtrim($base, '/') . '/' . $rel;
            return $out;
        }
        $full = self::REPO_RAW . $rel;
        return array(
            self::MIRROR . $full,
            $full,
            self::CDN . $rel,
        );
    }
    private static function httpGet($url, $timeout = 8)
    {
        $opts = array(
            'http' => array(
                'method'          => 'GET',
                'timeout'         => $timeout,
                'follow_location' => 1,
                'max_redirects'   => 5,
                'header'          => "User-Agent: AdminBeautify-Patch/" . self::abVersion() . "\r\n",
            ),
            'ssl' => array('verify_peer' => true, 'verify_peer_name' => true),
        );
        $body = @file_get_contents($url, false, stream_context_create($opts));
        if ($body !== false && $body !== '') return $body;
        if (!function_exists('curl_init')) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'AdminBeautify-Patch/' . self::abVersion(),
        ));
        $out  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($out !== false && $code >= 200 && $code < 400) ? $out : false;
    }
    private static function fetchFirst($rel, $timeout = 8)
    {
        foreach (self::urls($rel) as $url) {
            $r = self::httpGet($url, $timeout);
            if ($r !== false && $r !== '') return $r;
        }
        return false;
    }
    private static function cacheFile()
    {
        return dirname(__FILE__) . '/tmp_update/.patch_index.json';
    }
    public static function fetchIndex($force = false)
    {
        if (!empty(self::$indexCache) && !$force) return self::$indexCache;
        $file = self::cacheFile();
        $cached = null;
        if (is_readable($file)) {
            $raw = @file_get_contents($file);
            $obj = $raw ? json_decode($raw, true) : null;
            if (is_array($obj) && isset($obj['index']) && is_array($obj['index'])) {
                $cached = $obj;
            }
        }
        $stale = true;
        if ($cached) {
            $stale = (time() - (int) $cached['ts']) >= self::CACHE_TTL;
        }
        if ($cached && !$force) {
            self::$indexCache = array('ok' => true, 'index' => $cached['index'],
                'from_cache' => true, 'ts' => (int) $cached['ts'], 'stale' => $stale, 'error' => '');
            return self::$indexCache;
        }
        if (!$cached && !$force) {
            self::$indexCache = array('ok' => false, 'index' => array('patches' => array()),
                'from_cache' => false, 'ts' => 0, 'stale' => true, 'error' => '尚未获取补丁清单');
            return self::$indexCache;
        }
        $body = self::fetchFirst('index.json', 8);
        if ($body !== false) {
            $idx = json_decode($body, true);
            if (is_array($idx) && isset($idx['patches']) && is_array($idx['patches'])) {
                $dir = dirname($file);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                @file_put_contents($file, json_encode(array('ts' => time(), 'index' => $idx)));
                self::$indexCache = array('ok' => true, 'index' => $idx,
                    'from_cache' => false, 'ts' => time(), 'stale' => false, 'error' => '');
                return self::$indexCache;
            }
        }
        if ($cached) {
            self::$indexCache = array('ok' => true, 'index' => $cached['index'],
                'from_cache' => true, 'ts' => (int) $cached['ts'], 'stale' => $stale,
                'error' => '网络不可用，使用上次缓存');
            return self::$indexCache;
        }
        self::$indexCache = array('ok' => false, 'index' => array('patches' => array()),
            'from_cache' => false, 'ts' => 0, 'stale' => true, 'error' => '无法获取补丁清单');
        return self::$indexCache;
    }
    public static function state()
    {
        $empty = array('v' => 1, 'applied' => array(), 'notified' => array(),
            'backups' => array(), 'log' => array(), 'last_check' => 0);
        if (!class_exists('AdminBeautify_Plugin')) return $empty;
        try {
            $r = AdminBeautify_Plugin::cardDataGet(self::STATE_KEY, 'site');
            if (is_array($r) && isset($r['json']) && is_array($r['json'])) {
                return array_merge($empty, $r['json']);
            }
            if (is_array($r) && isset($r['content']) && is_string($r['content'])) {
                $j = json_decode($r['content'], true);
                if (is_array($j)) return array_merge($empty, $j);
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        return $empty;
    }
    public static function saveState($s)
    {
        if (!class_exists('AdminBeautify_Plugin')) return false;
        try {
            $r = AdminBeautify_Plugin::cardDataSave(self::STATE_KEY, json_encode($s), 'site');
            return !(is_array($r) && isset($r['error']));
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }
    private static function progress($opt, $pct, $msg)
    {
        if (!isset($opt['emit']) || !is_callable($opt['emit'])) return;
        try {
            call_user_func($opt['emit'], max(0, min(100, (int) $pct)), (string) $msg);
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
    }
    private static function logTo(&$state, $action, $msg, $ok = true)
    {
        if (!isset($state['log']) || !is_array($state['log'])) $state['log'] = array();
        array_unshift($state['log'], array(
            'ts' => time(), 'act' => $action, 'ok' => (bool) $ok,
            'msg' => mb_substr((string) $msg, 0, 200, 'UTF-8'),
        ));
        $state['log'] = array_slice($state['log'], 0, 20);
    }
    private static function safePath($rel)
    {
        $rel = str_replace('\\', '/', (string) $rel);
        $rel = ltrim($rel, '/');
        if ($rel === '' || strpos($rel, "\0") !== false) return '';
        if (strpos($rel, '..') !== false) return '';
        $ok = in_array($rel, self::$allowRoot, true);
        if (!$ok) {
            foreach (self::$allowPrefix as $p) {
                if (strpos($rel, $p) === 0) { $ok = true; break; }
            }
        }
        if (!$ok) return '';
        foreach (self::$denyPrefix as $p) {
            if (strpos($rel, $p) === 0) return '';
        }
        $root = self::root();
        $full = $root . '/' . $rel;
        $realDir = realpath(dirname($full));
        $realRoot = realpath($root);
        if ($realDir === false || $realRoot === false) return '';
        if (strpos(str_replace('\\', '/', $realDir), str_replace('\\', '/', $realRoot)) !== 0) return '';
        return $full;
    }
    private static function normEol($s, $eol)
    {
        $s = str_replace("\r\n", "\n", (string) $s);
        if ($eol === 'crlf') $s = str_replace("\n", "\r\n", $s);
        return $s;
    }
    private static function sha($s)
    {
        return hash('sha256', (string) $s);
    }
    private static function syntaxOk($code)
    {
        if (!function_exists('token_get_all')) return true;
        try {
            token_get_all($code, TOKEN_PARSE);
            return true;
        } catch (ParseError $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }
    public static function analyze($index = null)
    {
        if ($index === null) {
            $r = self::fetchIndex(false);
            $index = $r['index'];
        }
        $tc  = self::typechoVersion();
        $ab  = self::abVersion();
        $out = array('typecho' => $tc, 'ab' => $ab, 'patches' => array(),
            'have' => false, 'count' => 0, 'applied' => 0, 'conflict' => 0,
            'ids' => array());
        $minAb = isset($index['min_ab']) ? (string) $index['min_ab'] : '';
        $patches = isset($index['patches']) && is_array($index['patches']) ? $index['patches'] : array();
        foreach ($patches as $p) {
            if (!isset($p['id'], $p['title']) || !is_array(isset($p['files']) ? $p['files'] : null)) continue;
            $range = isset($p['typecho']) ? $p['typecho'] : array();
            $min = isset($range['min']) ? (string) $range['min'] : '';
            $max = isset($range['max']) ? (string) $range['max'] : '';
            if ($min !== '' && self::cmpVersion($tc, $min) < 0) continue;
            if ($max !== '' && self::cmpVersion($tc, $max) > 0) continue;
            if ($minAb !== '' && self::cmpVersion($ab, $minAb) < 0) continue;
            $status  = 'applied';
            $fileOut = array();
            $hasWrite = false;
            $hasBlock = false;
            foreach ($p['files'] as $f) {
                $rel = isset($f['path']) ? (string) $f['path'] : '';
                $full = self::safePath($rel);
                $item = array('path' => $rel, 'state' => 'block', 'reason' => '');
                if ($full === '') {
                    $item['reason'] = '路径不在白名单内';
                    $hasBlock = true;
                    $fileOut[] = $item;
                    continue;
                }
                if (!is_file($full)) {
                    $item['state'] = 'block';
                    $item['reason'] = '文件不存在';
                    $hasBlock = true;
                    $fileOut[] = $item;
                    continue;
                }
                $size = (int) @filesize($full);
                if ($size > self::MAX_BYTES) {
                    $item['reason'] = '文件过大（' . $size . ' 字节）';
                    $hasBlock = true;
                    $fileOut[] = $item;
                    continue;
                }
                $eol = isset($f['eol']) ? (string) $f['eol'] : 'lf';
                $cur = self::normEol((string) @file_get_contents($full), $eol);
                $curSha = self::sha($cur);
                $afterSha = isset($f['sha256_after']) ? (string) $f['sha256_after'] : '';
                if ($afterSha !== '' && $curSha === $afterSha) {
                    $item['state'] = 'applied';
                } else {
                    $befores = isset($f['sha256_before']) ? $f['sha256_before'] : array();
                    if (is_string($befores)) $befores = array($befores);
                    if (in_array($curSha, $befores, true)) {
                        $item['state'] = 'write';
                        $hasWrite = true;
                    } else {
                        $item['state'] = 'block';
                        $item['reason'] = '本地文件与清单里的原版不一致（可能被人改过或版本不同）';
                        $hasBlock = true;
                    }
                }
                $fileOut[] = $item;
            }
            if ($hasBlock) {
                $status = 'conflict';
            } elseif ($hasWrite) {
                $status = 'pending';
            } else {
                $status = 'applied';
            }
            $out['patches'][] = array(
                'id'       => (string) $p['id'],
                'title'    => (string) $p['title'],
                'severity' => isset($p['severity']) ? (string) $p['severity'] : 'warn',
                'notes'    => isset($p['notes']) ? (string) $p['notes'] : '',
                'refs'     => isset($p['refs']) ? $p['refs'] : array(),
                'status'   => $status,
                'files'    => $fileOut,
            );
            if ($status === 'pending') {
                $out['have'] = true;
                $out['count']++;
                $out['ids'][] = (string) $p['id'];
            } elseif ($status === 'conflict') {
                $out['conflict']++;
                $out['have'] = true;
                $out['ids'][] = (string) $p['id'];
            } else {
                $out['applied']++;
            }
        }
        return $out;
    }
    public static function summary($force = false)
    {
        $r = self::fetchIndex($force);
        $a = self::analyze($r['index']);
        $list = array();
        foreach ($a['patches'] as $p) {
            if ($p['status'] === 'applied') continue;
            $paths = array();
            foreach ($p['files'] as $f) $paths[] = $f['path'];
            $list[] = array(
                'id' => $p['id'], 'title' => $p['title'], 'severity' => $p['severity'],
                'status' => $p['status'], 'files' => $paths,
            );
        }
        return array(
            'have'       => (bool) $a['have'],
            'count'      => (int) $a['count'],
            'conflict'   => (int) $a['conflict'],
            'applied'    => (int) $a['applied'],
            'typecho'    => $a['typecho'],
            'ids'        => $a['ids'],
            'list'       => $list,
            'checked_at' => $r['ts'] ? (int) $r['ts'] : 0,
            'from_cache' => (bool) $r['from_cache'],
            'stale'      => !empty($r['stale']),
            'error'      => (string) $r['error'],
        );
    }
    private static function backupRoot()
    {
        return dirname(__FILE__) . '/' . self::BACKUP_DIR;
    }
    private static function pruneBackups(&$state)
    {
        $root = self::backupRoot();
        if (!is_dir($root)) return;
        $dirs = array();
        foreach ((array) @scandir($root) as $d) {
            if ($d === '.' || $d === '..') continue;
            if (is_dir($root . '/' . $d)) $dirs[] = $d;
        }
        sort($dirs);
        while (count($dirs) > self::KEEP_BACKUPS) {
            $old = array_shift($dirs);
            self::removeDir($root . '/' . $old);
        }
        if (isset($state['backups']) && is_array($state['backups'])) {
            $keep = array();
            foreach ($state['backups'] as $b) {
                if (isset($b['dir']) && is_dir($root . '/' . $b['dir'])) $keep[] = $b;
            }
            $state['backups'] = array_slice($keep, -self::KEEP_BACKUPS);
        }
    }
    private static function removeDir($dir)
    {
        if (!is_dir($dir)) return;
        foreach ((array) @scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = $dir . '/' . $f;
            if (is_dir($p)) self::removeDir($p);
            else @unlink($p);
        }
        @rmdir($dir);
    }
    private static function backupFiles($id, $items)
    {
        $root = self::backupRoot();
        if (!is_dir($root) && !@mkdir($root, 0755, true) && !is_dir($root)) return false;
        $name = date('Ymd-His') . '-' . preg_replace('/[^A-Za-z0-9._-]/', '_', $id);
        $dir  = $root . '/' . $name;
        if (!@mkdir($dir, 0755, true)) return false;
        $manifest = array('id' => $id, 'ts' => time(), 'files' => array());
        foreach ($items as $it) {
            $dst = $dir . '/' . $it['rel'];
            if (!is_dir(dirname($dst))) @mkdir(dirname($dst), 0755, true);
            if (!@copy($it['full'], $dst)) {
                self::removeDir($dir);
                return false;
            }
            $manifest['files'][] = array('path' => $it['rel'], 'sha256' => $it['sha'] );
        }
        @file_put_contents($dir . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_SLASHES));
        return array('dir' => $name, 'files' => count($manifest['files']));
    }
    public static function backups()
    {
        $root = self::backupRoot();
        $out = array();
        if (!is_dir($root)) return $out;
        foreach ((array) @scandir($root) as $d) {
            if ($d === '.' || $d === '..') continue;
            $mf = $root . '/' . $d . '/manifest.json';
            $j = is_readable($mf) ? json_decode((string) @file_get_contents($mf), true) : null;
            $out[] = array(
                'dir' => $d,
                'ts'  => is_array($j) && isset($j['ts']) ? (int) $j['ts'] : (int) @filemtime($root . '/' . $d),
                'id'  => is_array($j) && isset($j['id']) ? (string) $j['id'] : '',
                'files' => is_array($j) && isset($j['files']) ? count($j['files']) : 0,
            );
        }
        usort($out, array('AdminBeautify_Patch', 'sortByTsAsc'));
        return $out;
    }
    public static function sortByTsAsc($a, $b)
    {
        return $a['ts'] === $b['ts'] ? 0 : ($a['ts'] < $b['ts'] ? -1 : 1);
    }
    public static function apply($id, $opt = array())
    {
        $id = (string) $id;
        $index = isset($opt['index']) ? $opt['index'] : null;
        if ($index === null) {
            $r = self::fetchIndex(false);
            $index = $r['index'];
        }
        $a = self::analyze($index);
        $target = null;
        foreach ($a['patches'] as $p) {
            if ($p['id'] === $id) { $target = $p; break; }
        }
        if (!$target) return array('ok' => false, 'message' => '补丁不存在或与当前 Typecho 版本不匹配', 'files' => array());
        self::progress($opt, 10, '校验补丁：' . $target['title']);
        if ($target['status'] === 'applied') {
            return array('ok' => true, 'message' => '已经是打好状态，无需重复应用', 'files' => array(), 'noop' => true);
        }
        $force = !empty($opt['force']);
        if ($target['status'] === 'conflict' && !$force) {
            $why = '';
            foreach ($target['files'] as $f) {
                if ($f['state'] === 'block') { $why = $f['path'] . '：' . $f['reason']; break; }
            }
            return array('ok' => false, 'message' => '存在冲突，已拒绝自动应用（' . $why . '）', 'files' => $target['files'], 'conflict' => true);
        }
        $raw = null;
        foreach ($index['patches'] as $p) {
            if ((string) $p['id'] === $id) { $raw = $p; break; }
        }
        if (!$raw) return array('ok' => false, 'message' => '清单里找不到该补丁', 'files' => array());
        $lock = @fopen(dirname(__FILE__) . '/tmp_update/.patch_lock', 'c');
        if ($lock) { @flock($lock, LOCK_EX); }
        $state = self::state();
        $written = array();
        $report  = array();
        $plan = array();
        $totalF = count($raw['files']);
        $idxF   = 0;
        foreach ($raw['files'] as $f) {
            $idxF++;
            $rel  = isset($f['path']) ? (string) $f['path'] : '';
            $full = self::safePath($rel);
            if ($full === '') {
                if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
                return array('ok' => false, 'message' => '路径不在白名单内：' . $rel, 'files' => array());
            }
            $eol = isset($f['eol']) ? (string) $f['eol'] : 'lf';
            $afterSha = isset($f['sha256_after']) ? (string) $f['sha256_after'] : '';
            $cur = is_file($full) ? self::normEol((string) @file_get_contents($full), $eol) : '';
            $curSha = self::sha($cur);
            if ($afterSha !== '' && $curSha === $afterSha) {
                $report[] = array('path' => $rel, 'ok' => true, 'skip' => true, 'message' => '已是最新');
                continue;
            }
            if (!$force) {
                $befores = isset($f['sha256_before']) ? $f['sha256_before'] : array();
                if (is_string($befores)) $befores = array($befores);
                if (!in_array($curSha, $befores, true)) {
                    if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
                    return array('ok' => false, 'message' => $rel . ' 与清单里的原版不一致，已拒绝（可用 force 覆盖）', 'files' => $report);
                }
            }
            self::progress($opt, 20 + (int) (35 * ($idxF - 1) / max(1, $totalF)), '下载补丁内容：' . $rel);
            $body = self::fetchFirst(isset($f['after']) ? (string) $f['after'] : '', 10);
            if ($body === false) {
                if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
                return array('ok' => false, 'message' => '下载补丁内容失败：' . $rel, 'files' => $report);
            }
            $new = self::normEol($body, $eol);
            if ($afterSha !== '' && self::sha($new) !== $afterSha) {
                if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
                return array('ok' => false, 'message' => '补丁内容校验失败（sha256 不符）：' . $rel, 'files' => $report);
            }
            if (!self::syntaxOk($new)) {
                if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
                return array('ok' => false, 'message' => '补丁内容语法自检未通过，已中止：' . $rel, 'files' => $report);
            }
            if (!empty($f['expect']) && @preg_match((string) $f['expect'], $new) !== 1) {
                if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
                return array('ok' => false, 'message' => '补丁内容未包含预期改动，已中止：' . $rel, 'files' => $report);
            }
            $plan[] = array('rel' => $rel, 'full' => $full, 'sha' => $curSha,
                'new' => $new, 'eol' => $eol, 'after' => $afterSha);
        }
        if (empty($plan)) {
            if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
            return array('ok' => true, 'message' => '无需改动', 'files' => $report, 'noop' => true);
        }
        self::progress($opt, 58, '备份原文件…');
        $bk = self::backupFiles($id, $plan);
        if ($bk === false) {
            if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
            return array('ok' => false, 'message' => '备份失败（插件目录不可写），已中止', 'files' => $report);
        }
        foreach ($plan as $it) {
            self::progress($opt, 65 + (int) (30 * (count($written) + 1) / max(1, count($plan))), '写入并复核：' . $it['rel']);
            $perm = @fileperms($it['full']);
            $tmp  = $it['full'] . '.abpatch.tmp';
            if (@file_put_contents($tmp, $it['new']) === false) {
                @unlink($tmp);
                self::rollbackWrites($written, $bk['dir']);
                if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
                return array('ok' => false, 'message' => '写入失败：' . $it['rel'], 'files' => $report);
            }
            if ($perm !== false) @chmod($tmp, $perm & 0777);
            if (!@rename($tmp, $it['full'])) {
                @unlink($tmp);
                self::rollbackWrites($written, $bk['dir']);
                if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
                return array('ok' => false, 'message' => '替换失败：' . $it['rel'], 'files' => $report);
            }
            $written[] = $it;
            $check = self::sha(self::normEol((string) @file_get_contents($it['full']), $it['eol']));
            if ($it['after'] !== '' && $check !== $it['after']) {
                self::rollbackWrites($written, $bk['dir']);
                if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
                return array('ok' => false, 'message' => '写入后校验失败，已还原：' . $it['rel'], 'files' => $report);
            }
            if (function_exists('opcache_invalidate')) @opcache_invalidate($it['full'], true);
            $report[] = array('path' => $it['rel'], 'ok' => true, 'message' => '已写入');
        }
        $state['applied'][$id] = array('ts' => time(), 'files' => count($written),
            'after' => isset($plan[0]['after']) ? $plan[0]['after'] : '');
        if (!isset($state['backups']) || !is_array($state['backups'])) $state['backups'] = array();
        $state['backups'][] = array('dir' => $bk['dir'], 'ts' => time(), 'id' => $id, 'files' => $bk['files']);
        self::logTo($state, 'apply', $id . '（' . count($written) . ' 个文件）', true);
        self::pruneBackups($state);
        self::saveState($state);
        if ($lock) { @flock($lock, LOCK_UN); @fclose($lock); }
        self::progress($opt, 100, '完成');
        return array('ok' => true, 'message' => '已应用补丁：' . $target['title'],
            'files' => $report, 'backup' => $bk['dir']);
    }
    private static function rollbackWrites($written, $backupDirName)
    {
        $root = self::backupRoot() . '/' . $backupDirName;
        foreach (array_reverse($written) as $it) {
            $src = $root . '/' . $it['rel'];
            if (is_file($src)) @copy($src, $it['full']);
            if (function_exists('opcache_invalidate')) @opcache_invalidate($it['full'], true);
        }
    }
    public static function applyAll($ids = null, $opt = array())
    {
        $r = self::fetchIndex(false);
        $a = self::analyze($r['index']);
        $targets = array();
        foreach ($a['patches'] as $p) {
            if ($p['status'] !== 'pending') continue;
            if ($ids !== null && !in_array($p['id'], (array) $ids, true)) continue;
            $targets[] = $p['id'];
        }
        if (empty($targets)) {
            return array('ok' => true, 'message' => '没有可应用的补丁', 'applied' => array(), 'noop' => true);
        }
        $applied = array();
        $n = count($targets);
        foreach ($targets as $i => $id) {
            self::progress($opt, (int) (100 * $i / max(1, $n)), '（' . ($i + 1) . '/' . $n . '）' . $id);
            $one = self::apply($id, $opt);
            if (empty($one['ok'])) {
                return array('ok' => false, 'message' => $one['message'], 'applied' => $applied,
                    'failed' => $id, 'files' => isset($one['files']) ? $one['files'] : array());
            }
            $applied[] = $id;
        }
        return array('ok' => true, 'message' => '已应用 ' . count($applied) . ' 个补丁', 'applied' => $applied);
    }
    public static function rollback($id, $force = false)
    {
        $id = (string) $id;
        $root = self::backupRoot();
        $cands = array();
        foreach (self::backups() as $b) {
            if ($b['id'] === $id) $cands[] = $b;
        }
        if (empty($cands)) {
            return array('ok' => false, 'message' => '没有找到该补丁的备份');
        }
        $bk = array_pop($cands);
        $dir = $root . '/' . $bk['dir'];
        $mf  = $dir . '/manifest.json';
        $man = is_readable($mf) ? json_decode((string) @file_get_contents($mf), true) : null;
        if (!is_array($man) || empty($man['files'])) {
            return array('ok' => false, 'message' => '备份清单损坏，无法回滚');
        }
        $done = array();
        foreach ($man['files'] as $f) {
            $rel  = isset($f['path']) ? (string) $f['path'] : '';
            $full = self::safePath($rel);
            $src  = $dir . '/' . $rel;
            if ($full === '' || !is_file($src)) {
                return array('ok' => false, 'message' => '备份文件缺失：' . $rel);
            }
            if (!$force && is_file($full)) {
                $cur = self::sha((string) @file_get_contents($full));
                $expect = isset($f['sha256']) ? (string) $f['sha256'] : '';
                if ($expect !== '' && $cur !== '' && $cur === $expect) {
                    return array('ok' => false, 'message' => $rel . ' 当前内容与补丁前一致，无需回滚');
                }
            }
            $content = (string) @file_get_contents($src);
            if (!self::syntaxOk($content)) {
                return array('ok' => false, 'message' => '备份文件语法异常，已中止：' . $rel);
            }
            $perm = @fileperms($full);
            $tmp  = $full . '.abpatch.tmp';
            if (@file_put_contents($tmp, $content) === false || !@rename($tmp, $full)) {
                @unlink($tmp);
                return array('ok' => false, 'message' => '还原失败：' . $rel);
            }
            if ($perm !== false) @chmod($full, $perm & 0777);
            if (function_exists('opcache_invalidate')) @opcache_invalidate($full, true);
            $done[] = $rel;
        }
        $state = self::state();
        if (isset($state['applied'][$id])) unset($state['applied'][$id]);
        self::logTo($state, 'rollback', $id . '（' . count($done) . ' 个文件）', true);
        self::saveState($state);
        return array('ok' => true, 'message' => '已回滚：' . $id, 'files' => $done, 'backup' => $bk['dir']);
    }
}
