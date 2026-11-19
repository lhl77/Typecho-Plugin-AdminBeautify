<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
class AdminBeautify_Skills
{
    const REPO   = 'lhl77/Typecho-Plugin-AdminBeautify';
    const BRANCH = 'main';
    const RAW = 'https://raw.githubusercontent.com/lhl77/Typecho-Plugin-AdminBeautify/main/skills/';
    const PAGE = 'https://github.com/lhl77/Typecho-Plugin-AdminBeautify/tree/main/skills';
    const TTL = 1800;
    const FAIL_TTL = 300;
    private static $mirrors = array(
        'https://gh1.lhl.one/',
    );
    public static function skillsDir()
    {
        return rtrim(dirname(__FILE__), '/\\') . '/skills';
    }
    private static function dataDir()
    {
        $plugins = rtrim(__TYPECHO_ROOT_DIR__ . __TYPECHO_PLUGIN_DIR__, '/\\');
        $dir     = $plugins . '/.ab-store';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
    private static function stateFile()
    {
        return self::dataDir() . '/skills.json';
    }
    private static function backupDir()
    {
        $dir = self::dataDir() . '/skills-backup';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
    private static function readState()
    {
        $file = self::stateFile();
        if (!is_file($file)) {
            return array();
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? $data : array();
    }
    private static function writeState(array $state)
    {
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return @file_put_contents(self::stateFile(), $json) !== false;
    }
    public static function localVersion()
    {
        $bundled      = '';
        $bundledMtime = 0;
        $file = self::skillsDir() . '/manifest.json';
        if (is_file($file)) {
            $bundledMtime = (int)@filemtime($file);
            $json = json_decode((string)@file_get_contents($file), true);
            if (is_array($json) && !empty($json['version'])) {
                $bundled = (string)$json['version'];
            }
        }
        $state = self::readState();
        if (!empty($state['version'])) {
            $appliedAt = isset($state['appliedAt']) ? (int)$state['appliedAt'] : 0;
            if ($bundled !== '' && $bundledMtime > $appliedAt) {
                return $bundled;
            }
            return (string)$state['version'];
        }
        return $bundled;
    }
    public static function lastCheck()
    {
        $state = self::readState();
        return isset($state['lastCheck']) ? (int)$state['lastCheck'] : 0;
    }
    public static function compareVersion($a, $b)
    {
        $a = trim((string)$a);
        $b = trim((string)$b);
        if ($a === $b) {
            return 0;
        }
        if ($a === '') {
            return -1;
        }
        if ($b === '') {
            return 1;
        }
        $pa = preg_split('/[._-]/', $a);
        $pb = preg_split('/[._-]/', $b);
        $n  = max(count($pa), count($pb));
        for ($i = 0; $i < $n; $i++) {
            $x = isset($pa[$i]) ? $pa[$i] : '0';
            $y = isset($pb[$i]) ? $pb[$i] : '0';
            if (is_numeric($x) && is_numeric($y)) {
                $ix = (int)$x;
                $iy = (int)$y;
                if ($ix !== $iy) {
                    return $ix > $iy ? 1 : -1;
                }
            } else {
                $c = strcmp($x, $y);
                if ($c !== 0) {
                    return $c > 0 ? 1 : -1;
                }
            }
        }
        return 0;
    }
    public static function check($force = false, $autoApply = true)
    {
        $state = self::readState();
        $now   = time();
        $ttl = self::TTL;
        if (isset($state['lastResult']) && is_array($state['lastResult'])
            && isset($state['lastResult']['ok']) && !$state['lastResult']['ok']
        ) {
            $ttl = self::FAIL_TTL;
        }
        if (!$force
            && isset($state['lastCheck'], $state['lastResult'])
            && $now - (int)$state['lastCheck'] < $ttl
            && is_array($state['lastResult'])
        ) {
            $cached = $state['lastResult'];
            $cached['cached'] = true;
            return $cached;
        }
        $local  = self::localVersion();
        $result = array(
            'ok'             => true,
            'hasUpdate'      => false,
            'behind'         => false,
            'local'          => $local,
            'remote'         => '',
            'remoteTime'     => '',
            'files'          => array(),
            'error'          => '',
            'cached'         => false,
            'checkedAt'      => $now,
            'page'           => self::PAGE,
            'applied'        => false,
            'appliedVersion' => '',
            'appliedFiles'   => array(),
        );
        $raw = self::fetch('manifest.json', 8, true);
        if ($raw === null) {
            $result['ok']    = false;
            $result['error'] = '拉取技能清单失败（直连与 gh1.lhl.one 镜像都没成功）';
        } else {
            $manifest = json_decode($raw, true);
            if (!is_array($manifest) || empty($manifest['version'])) {
                $result['ok']    = false;
                $result['error'] = '技能清单格式不正确';
            } else {
                $remote               = (string)$manifest['version'];
                $cmp                  = self::compareVersion($remote, $local);
                $result['remote']     = $remote;
                $result['remoteTime'] = isset($manifest['updated']) ? (string)$manifest['updated'] : '';
                $result['files']      = self::normalizeFiles(isset($manifest['files']) ? $manifest['files'] : array());
                $result['hasUpdate']  = ($cmp > 0);
                $result['behind']     = ($cmp < 0);
            }
        }
        $state['lastCheck']  = $now;
        $state['lastResult'] = $result;
        self::writeState($state);
        if ($autoApply && !empty($result['hasUpdate'])) {
            $applied = self::apply();
            if (isset($applied['error'])) {
                $result['autoError'] = (string)$applied['error'];
            } else {
                $result['applied']        = true;
                $result['appliedVersion'] = (string)$applied['version'];
                $result['appliedFiles']   = $applied['files'];
                $result['local']          = (string)$applied['version'];
                $result['hasUpdate']      = false;
                $result['behind']         = false;
                $st = self::readState();
                if (isset($st['lastResult']) && is_array($st['lastResult'])) {
                    $st['lastResult']['applied']        = true;
                    $st['lastResult']['appliedVersion'] = (string)$applied['version'];
                    $st['lastResult']['appliedFiles']   = $applied['files'];
                    self::writeState($st);
                }
            }
        }
        return $result;
    }
    public static function apply()
    {
        $raw = self::fetch('manifest.json', 8, true);
        if ($raw === null) {
            return array('error' => '拉取技能清单失败，请检查网络或稍后重试');
        }
        $manifest = json_decode($raw, true);
        if (!is_array($manifest) || empty($manifest['version'])) {
            return array('error' => '技能清单格式不正确');
        }
        $files = self::normalizeFiles(isset($manifest['files']) ? $manifest['files'] : array());
        if (empty($files)) {
            return array('error' => '技能清单里没有任何文件');
        }
        $dir = self::skillsDir();
        if (!is_dir($dir)) {
            return array('error' => '找不到技能目录：' . $dir);
        }
        if (!is_writable($dir)) {
            return array('error' => '技能目录不可写（' . $dir . '），请检查文件权限');
        }
        $stamp  = date('Ymd-His');
        $backup = rtrim(self::backupDir(), '/') . '/' . $stamp;
        @mkdir($backup, 0755, true);
        $written = array();
        $failed  = array();
        foreach ($files as $name) {
            $body = self::fetch($name, 10, false);
            if ($body === null || trim($body) === '') {
                $failed[] = $name;
                continue;
            }
            $target = $dir . '/' . $name;
            if (is_file($target)) {
                @copy($target, $backup . '/' . $name);
            }
            if (@file_put_contents($target, $body) === false) {
                $failed[] = $name;
            } else {
                $written[] = $name;
            }
        }
        if (empty($written)) {
            return array('error' => '所有技能文件都下载失败，请检查网络后重试');
        }
        $localManifest = $dir . '/manifest.json';
        if (is_file($localManifest)) {
            @copy($localManifest, $backup . '/manifest.json');
        }
        @file_put_contents($localManifest, $raw);
        $now   = time();
        $state = self::readState();
        $state['version']   = (string)$manifest['version'];
        $state['appliedAt'] = $now;
        $state['lastCheck'] = $now;
        $state['lastResult'] = array(
            'ok'             => true,
            'hasUpdate'      => false,
            'behind'         => false,
            'local'          => (string)$manifest['version'],
            'remote'         => (string)$manifest['version'],
            'remoteTime'     => isset($manifest['updated']) ? (string)$manifest['updated'] : '',
            'files'          => $files,
            'error'          => '',
            'cached'         => false,
            'checkedAt'      => $now,
            'page'           => self::PAGE,
            'applied'        => false,
            'appliedVersion' => '',
            'appliedFiles'   => array(),
        );
        self::writeState($state);
        return array(
            'ok'      => true,
            'version' => (string)$manifest['version'],
            'files'   => $written,
            'failed'  => $failed,
            'backup'  => $backup,
        );
    }
    private static function normalizeFiles($files)
    {
        if (!is_array($files)) {
            return array();
        }
        $out = array();
        foreach ($files as $name) {
            if (!is_string($name)) {
                continue;
            }
            $name = trim($name);
            if ($name === '' || !preg_match('/^[A-Za-z0-9_-][A-Za-z0-9_.-]*\.md$/', $name)) {
                continue;
            }
            if (!in_array($name, $out, true)) {
                $out[] = $name;
            }
        }
        return $out;
    }
    private static function fetch($name, $timeout = 8, $noCache = false)
    {
        $rel  = self::RAW . $name . ($noCache ? '?t=' . time() : '');
        $urls = array($rel);
        foreach (self::$mirrors as $mirror) {
            $urls[] = $mirror . $rel;
        }
        foreach ($urls as $url) {
            $body = self::httpGet($url, $timeout);
            if ($body !== null && $body !== '') {
                return $body;
            }
        }
        return null;
    }
    private static function httpGet($url, $timeout)
    {
        $ua = 'AdminBeautify-Skills/2.1.68';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => $ua,
            ));
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if (PHP_VERSION_ID < 80500) {
                curl_close($ch);
            }
            if ($body === false || $code < 200 || $code >= 300) {
                return null;
            }
            return (string)$body;
        }
        $ctx  = stream_context_create(array('http' => array(
            'timeout'    => $timeout,
            'user_agent' => $ua,
        )));
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : (string)$body;
    }
}
