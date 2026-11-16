<?php
/**
 * AB Store 内置模块 —— 安装 / 升级 / 卸载
 *
 * 全部使用纯 PHP 实现：
 *   · 解压优先用 ZipArchive，**没有 zip 扩展时退回内置的 ZIP 读取器**（zlib 即可），
 *     这样主机少装一个扩展也能装插件（本机 PHP 就没有 ZipArchive）。
 *   · 任何写操作前先备份到 usr/plugins/.ab-store/backup/<目录>-<时间>/，
 *     失败时把备份挪回原位（尽力回滚）。
 *
 * @package AdminBeautify
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
class AdminBeautify_StoreInstall
{
    const SELF_DIR = 'AdminBeautify';
    public static function install(array $item)
    {
        $id = isset($item['id']) ? (string) $item['id'] : '';
        $url = isset($item['downloadUrl']) && $item['downloadUrl'] !== ''
            ? $item['downloadUrl']
            : ($id !== '' ? AdminBeautify_Store::BASE . '/api/download/' . rawurlencode($id) : '');
        if ($url !== '' && !empty($item['shareToken'])) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'token=' . rawurlencode($item['shareToken']);
        }
        if ($url === '') {
            return array('error' => '缺少下载地址', 'code' => 400);
        }
        $tmpZip = tempnam(sys_get_temp_dir(), 'abstore-');
        if ($tmpZip === false) {
            return array('error' => '无法创建临时文件（检查 /tmp 权限）', 'code' => 500);
        }
        @unlink($tmpZip);
        $tmpZip .= '.zip';
        if (!AdminBeautify_Store::download($url, $tmpZip)) {
            @unlink($tmpZip);
            return array('error' => '下载安装包失败（网络或商店服务异常）', 'code' => 502);
        }
        $work = sys_get_temp_dir() . '/abstore-' . uniqid('', true);
        $unzip = self::unzip($tmpZip, $work);
        if (!empty($unzip['error'])) {
            self::removeDir($work);
            @unlink($tmpZip);
            return array('error' => $unzip['error'], 'code' => 500);
        }
        $root = self::findPluginRoot($work);
        if ($root === '') {
            self::removeDir($work);
            @unlink($tmpZip);
            return array('error' => '安装包里没有找到 Plugin.php', 'code' => 500);
        }
        $dir = AdminBeautify_Store::safeDir(
            !empty($item['directory']) ? $item['directory'] : basename($root)
        );
        if ($dir === '') {
            self::removeDir($work);
            @unlink($tmpZip);
            return array('error' => '插件目录名不合法', 'code' => 400);
        }
        $target = AdminBeautify_Store::pluginsDir() . '/' . $dir;
        $backup = '';
        if (is_dir($target) || is_link($target)) {
            $backup = self::backup($dir);
            if (!$backup) {
                self::removeDir($work);
                @unlink($tmpZip);
                return array('error' => '备份现有插件失败（检查 usr/plugins 写权限）', 'code' => 500);
            }
            self::removeDir($target);
        }
        $ok = @rename($root, $target);
        if (!$ok) {
            $ok = self::copyDir($root, $target);
        }
        self::removeDir($work);
        @unlink($tmpZip);
        if (!$ok) {
            if ($backup) {
                self::removeDir($target);
                @rename($backup, $target);
            }
            return array('error' => '写入插件目录失败（检查 usr/plugins 写权限）', 'code' => 500);
        }
        self::pruneBackups($dir, 1);
        \Typecho\Plugin::export();
        return array(
            'data'    => array('dir' => $dir, 'version' => isset($item['version']) ? $item['version'] : ''),
            'message' => '已安装 ' . $dir . '（旧版本已备份）',
        );
    }
    public static function uninstall($dir)
    {
        $dir = AdminBeautify_Store::safeDir($dir);
        if ($dir === '') {
            return array('error' => '目录名不合法', 'code' => 400);
        }
        if ($dir === self::SELF_DIR) {
            return array('error' => '不能卸载 AdminBeautify 自身', 'code' => 400);
        }
        $target = AdminBeautify_Store::pluginsDir() . '/' . $dir;
        if (!is_dir($target)) {
            return array('error' => '插件目录不存在：' . $dir, 'code' => 404);
        }
        $backup = self::backup($dir);
        if (!$backup) {
            return array('error' => '备份失败，已中止卸载', 'code' => 500);
        }
        if (!self::removeDir($target)) {
            return array('error' => '删除目录失败（检查 usr/plugins 写权限）', 'code' => 500);
        }
        self::pruneBackups($dir, 1);
        return array(
            'data'    => array('dir' => $dir, 'backup' => $backup),
            'message' => '已卸载 ' . $dir . '（备份在 ' . self::shortBackup($backup) . '）',
        );
    }
    public static function backup($dir)
    {
        $src = AdminBeautify_Store::pluginsDir() . '/' . $dir;
        if (!is_dir($src)) {
            return '';
        }
        $dst = AdminBeautify_Store::backupDir() . '/' . $dir . '-' . date('Ymd-His');
        $i = 1;
        while (is_dir($dst)) {
            $dst = AdminBeautify_Store::backupDir() . '/' . $dir . '-' . date('Ymd-His') . '-' . $i++;
        }
        return self::copyDir($src, $dst) ? $dst : '';
    }
    private static function shortBackup($path)
    {
        return str_replace(AdminBeautify_Store::pluginsDir() . '/', '', $path);
    }
    public static function pruneBackups($dir, $keep = 1)
    {
        $list = (array) @glob(AdminBeautify_Store::backupDir() . '/' . $dir . '-*', GLOB_ONLYDIR);
        if (count($list) <= $keep) {
            return;
        }
        sort($list);
        while (count($list) > $keep) {
            self::removeDir(array_shift($list));
        }
    }
    public static function pruneAllBackups($keep = 1)
    {
        $base = AdminBeautify_Store::backupDir();
        $dirs = (array) @glob($base . '/*', GLOB_ONLYDIR);
        if (!$dirs) {
            return;
        }
        $groups = array();
        foreach ($dirs as $path) {
            $name = basename($path);
            if (!preg_match('/^(.*)-(\d{8}-\d{6}(?:-\d+)?)$/', $name, $m)) {
                continue;
            }
            $groups[$m[1]][] = $path;
        }
        foreach ($groups as $list) {
            if (count($list) <= $keep) {
                continue;
            }
            sort($list);
            while (count($list) > $keep) {
                self::removeDir(array_shift($list));
            }
        }
    }
    public static function unzip($zipFile, $dest)
    {
        @mkdir($dest, 0755, true);
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== true) {
                return array('error' => 'ZIP 打不开（文件损坏？）');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (self::unsafePath($name)) {
                    continue;
                }
                if (substr($name, -1) === '/') {
                    @mkdir($dest . '/' . $name, 0755, true);
                    continue;
                }
                @mkdir(dirname($dest . '/' . $name), 0755, true);
                $fp = $zip->getStream($name);
                if ($fp) {
                    $out = @fopen($dest . '/' . $name, 'wb');
                    if ($out) {
                        stream_copy_to_stream($fp, $out);
                        fclose($out);
                    }
                    fclose($fp);
                }
            }
            $zip->close();
            return array('ok' => true);
        }
        return self::unzipPure($zipFile, $dest);
    }
    private static function unzipPure($zipFile, $dest)
    {
        $fp = @fopen($zipFile, 'rb');
        if (!$fp) {
            return array('error' => '无法读取 ZIP 文件');
        }
        $size = (int) @filesize($zipFile);
        $tailLen = min($size, 66000);
        fseek($fp, $size - $tailLen);
        $tail = (string) fread($fp, $tailLen);
        $pos = strrpos($tail, "PK\x05\x06");
        if ($pos === false) {
            fclose($fp);
            return array('error' => '不是有效的 ZIP（找不到目录尾）');
        }
        $eocd = unpack(
            'vdisk/vcddisk/vdiskentries/ventries/Vcdsize/Vcdoffset/vcommentlen',
            substr($tail, $pos + 4, 18)
        );
        $count = (int) $eocd['entries'];
        $offset = (int) $eocd['cdoffset'];
        if ($count <= 0 || $offset <= 0 || $offset >= $size) {
            fclose($fp);
            return array('error' => 'ZIP 目录为空或损坏');
        }
        fseek($fp, $offset);
        $cd = (string) fread($fp, $size - $offset);
        $p = 0;
        for ($i = 0; $i < $count; $i++) {
            if (substr($cd, $p, 4) !== "PK\x01\x02") {
                break;
            }
            $h = unpack(
                'vmade/vneed/vflags/vmethod/vmtime/vmdate/Vcrc/Vcsize/Vusize/vnlen/vxlen/vclen/vdisk/viattr/Veattr/Vlocaloff',
                substr($cd, $p + 4, 42)
            );
            $name = (string) substr($cd, $p + 46, $h['nlen']);
            $p += 46 + $h['nlen'] + $h['xlen'] + $h['clen'];
            $name = str_replace('\\', '/', $name);
            if ($name === '' || self::unsafePath($name)) {
                continue;
            }
            if (substr($name, -1) === '/') {
                @mkdir($dest . '/' . $name, 0755, true);
                continue;
            }
            fseek($fp, (int) $h['localoff']);
            $lh = (string) fread($fp, 30);
            if (substr($lh, 0, 4) !== "PK\x03\x04") {
                continue;
            }
            $l = unpack('vneed/vflags/vmethod/vmtime/vmdate/Vcrc/Vcsize/Vusize/vnlen/vxlen', substr($lh, 4, 26));
            $dataOffset = (int) $h['localoff'] + 30 + $l['nlen'] + $l['xlen'];
            fseek($fp, $dataOffset);
            $raw = $h['csize'] > 0 ? (string) fread($fp, (int) $h['csize']) : '';
            if ($raw === '') {
                continue;
            }
            $method = (int) $h['method'];
            if ($method === 8) {
                $data = @gzinflate($raw);
            } elseif ($method === 0) {
                $data = $raw;
            } else {
                continue;
            }
            if ($data === false) {
                continue;
            }
            @mkdir(dirname($dest . '/' . $name), 0755, true);
            @file_put_contents($dest . '/' . $name, $data);
        }
        fclose($fp);
        return array('ok' => true);
    }
    private static function unsafePath($name)
    {
        $name = (string) $name;
        if ($name === '' || strpos($name, "\0") !== false) {
            return true;
        }
        if ($name[0] === '/' || preg_match('#^[A-Za-z]:#', $name)) {
            return true;
        }
        foreach (explode('/', $name) as $seg) {
            if ($seg === '..') {
                return true;
            }
        }
        return false;
    }
    public static function findPluginRoot($work)
    {
        $work = rtrim($work, '/\\');
        if (is_file($work . '/Plugin.php')) {
            return $work;
        }
        $dirs = (array) @glob($work . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $d) {
            if (is_file($d . '/Plugin.php')) {
                return $d;
            }
        }
        foreach ($dirs as $d) {
            foreach ((array) @glob($d . '/*', GLOB_ONLYDIR) as $dd) {
                if (is_file($dd . '/Plugin.php')) {
                    return $dd;
                }
            }
        }
        return '';
    }
    public static function copyDir($src, $dst)
    {
        $src = rtrim($src, '/\\');
        $dst = rtrim($dst, '/\\');
        if (!is_dir($src)) {
            return false;
        }
        if (!is_dir($dst) && !@mkdir($dst, 0755, true) && !is_dir($dst)) {
            return false;
        }
        $ok = true;
        $items = @scandir($src);
        if ($items === false) {
            return false;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $src . '/' . $item;
            $to = $dst . '/' . $item;
            if (is_dir($from)) {
                $ok = self::copyDir($from, $to) && $ok;
            } else {
                $ok = @copy($from, $to) && $ok;
            }
        }
        return $ok;
    }
    public static function installFromZip($zipFile, $type = 'plugin')
    {
        $type = ($type === 'theme') ? 'theme' : 'plugin';
        if (!is_file($zipFile)) {
            return array('error' => '没有收到上传文件', 'code' => 400);
        }
        $size = (int) @filesize($zipFile);
        if ($size <= 0) {
            return array('error' => '上传文件为空', 'code' => 400);
        }
        $work = sys_get_temp_dir() . '/abstore-local-' . uniqid('', true);
        $unzip = self::unzip($zipFile, $work);
        if (!empty($unzip['error'])) {
            self::removeDir($work);
            return array('error' => $unzip['error'], 'code' => 400);
        }
        return self::placeExtracted($work, $type);
    }
    private static function placeExtracted($work, $type)
    {
        $isTheme = ($type === 'theme');
        $root = $isTheme ? self::findThemeRoot($work) : self::findPluginRoot($work);
        if ($root === '') {
            self::removeDir($work);
            return array(
                'error' => $isTheme
                    ? '安装包里没有找到主题（缺少 index.php，或请把主题放进一个顶层目录后压缩）'
                    : '安装包里没有找到 Plugin.php（或请把插件放进一个顶层目录后压缩）',
                'code'  => 400,
            );
        }
        $probe = $isTheme ? ($root . '/index.php') : ($root . '/Plugin.php');
        $dir = '';
        if (rtrim($root, '/\\') !== rtrim($work, '/\\')) {
            $dir = AdminBeautify_Store::safeDir(basename($root));
        }
        if ($dir === '') {
            $dir = AdminBeautify_Store::safeDir(self::readPackage($probe));
        }
        if ($dir === '') {
            self::removeDir($work);
            return array('error' => '无法确定目录名（请把文件放进一个顶层目录后再压缩）', 'code' => 400);
        }
        $baseDir = $isTheme ? self::themesDir() : AdminBeautify_Store::pluginsDir();
        $target = $baseDir . '/' . $dir;
        $backup = '';
        if (is_dir($target) || is_link($target)) {
            $backup = $isTheme ? self::backupTheme($dir) : self::backup($dir);
            if (!$backup) {
                self::removeDir($work);
                return array('error' => '备份现有版本失败（检查写权限）', 'code' => 500);
            }
            self::removeDir($target);
        }
        $ok = @rename($root, $target);
        if (!$ok) {
            $ok = self::copyDir($root, $target);
        }
        self::removeDir($work);
        if (!$ok) {
            if ($backup) {
                self::removeDir($target);
                @rename($backup, $target);
            }
            return array('error' => '写入目录失败（检查写权限）', 'code' => 500);
        }
        if ($isTheme) {
            self::pruneThemeBackups($dir, 1);
        } else {
            self::pruneBackups($dir, 1);
        }
        return array(
            'data'    => array(
                'dir'     => $dir,
                'type'    => $isTheme ? 'theme' : 'plugin',
                'version' => $isTheme ? '' : self::readVersion($target . '/Plugin.php'),
            ),
            'message' => '已安装' . ($isTheme ? '主题 ' : '插件 ') . $dir,
        );
    }
    public static function themesDir()
    {
        $dir = rtrim(__TYPECHO_ROOT_DIR__ . __TYPECHO_THEME_DIR__, '/\\');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
    public static function findThemeRoot($work)
    {
        $work = rtrim($work, '/\\');
        if (is_file($work . '/index.php')) {
            return $work;
        }
        foreach ((array) @glob($work . '/*', GLOB_ONLYDIR) as $d) {
            if (is_file($d . '/index.php')) {
                return $d;
            }
        }
        foreach ((array) @glob($work . '/*', GLOB_ONLYDIR) as $d) {
            foreach ((array) @glob($d . '/*', GLOB_ONLYDIR) as $dd) {
                if (is_file($dd . '/index.php')) {
                    return $dd;
                }
            }
        }
        return '';
    }
    public static function readPackage($file)
    {
        if (!is_file($file)) {
            return '';
        }
        $src = (string) @file_get_contents($file, false, null, 0, 4096);
        if (preg_match('/@package\s+([A-Za-z0-9_\-]+)/', $src, $m)) {
            return $m[1];
        }
        return '';
    }
    public static function readVersion($file)
    {
        if (!is_file($file)) {
            return '';
        }
        $src = (string) @file_get_contents($file, false, null, 0, 8192);
        if (preg_match('/@version\s+([0-9][0-9A-Za-z.\-]*)/i', $src, $m)) {
            return $m[1];
        }
        if (preg_match('/\bVersion\s*[:：]\s*([0-9][0-9A-Za-z.\-]*)/i', $src, $m)) {
            return $m[1];
        }
        return '';
    }
    public static function backupTheme($dir)
    {
        $src = self::themesDir() . '/' . $dir;
        if (!is_dir($src)) {
            return '';
        }
        $dst = AdminBeautify_Store::backupDir() . '/theme-' . $dir . '-' . date('Ymd-His');
        $i = 1;
        while (is_dir($dst)) {
            $dst = AdminBeautify_Store::backupDir() . '/theme-' . $dir . '-' . date('Ymd-His') . '-' . $i++;
        }
        return self::copyDir($src, $dst) ? $dst : '';
    }
    public static function pruneThemeBackups($dir, $keep = 1)
    {
        $prefix = 'theme-' . $dir . '-';
        $list = array();
        foreach ((array) @glob(AdminBeautify_Store::backupDir() . '/' . $prefix . '*', GLOB_ONLYDIR) as $p) {
            if (strpos(basename($p), $prefix) === 0) {
                $list[] = $p;
            }
        }
        if (count($list) <= $keep) {
            return;
        }
        sort($list);
        while (count($list) > $keep) {
            self::removeDir(array_shift($list));
        }
    }
    public static function backupList()
    {
        $base = AdminBeautify_Store::backupDir();
        $items = array();
        foreach ((array) @glob($base . '/*', GLOB_ONLYDIR) as $path) {
            $name = basename($path);
            if (!preg_match('/^(.+?)-(\d{8}-\d{6})(?:-\d+)?$/', $name, $m)) {
                continue;
            }
            $target = $m[1];
            $stamp  = $m[2];
            $isTheme = (strpos($target, 'theme-') === 0);
            $dirName = $isTheme ? substr($target, 6) : $target;
            $version = '';
            if (!$isTheme) {
                $version = self::readVersion($path . '/Plugin.php');
            } else {
                $version = self::readPackage($path . '/index.php');
            }
            $items[] = array(
                'name'    => $name,
                'type'    => $isTheme ? 'theme' : 'plugin',
                'dir'     => $dirName,
                'version' => $version,
                'date'    => substr($stamp, 0, 4) . '-' . substr($stamp, 4, 2) . '-' . substr($stamp, 6, 2)
                    . ' ' . substr($stamp, 9, 2) . ':' . substr($stamp, 11, 2) . ':' . substr($stamp, 13, 2),
                'ts'      => self::dateToTs($stamp),
                'size'    => self::dirSize($path),
            );
        }
        usort($items, function ($a, $b) {
            return $b['ts'] - $a['ts'];
        });
        return array('items' => $items, 'count' => count($items));
    }
    public static function backupRestore($name)
    {
        $name = basename(trim((string) $name));
        $base = AdminBeautify_Store::backupDir();
        $src  = $base . '/' . $name;
        if ($name === '' || !is_dir($src)) {
            return array('error' => '备份不存在', 'code' => 404);
        }
        if (!preg_match('/^(.+?)-(\d{8}-\d{6})(?:-\d+)?$/', $name, $m)) {
            return array('error' => '备份名格式不合法', 'code' => 400);
        }
        $target  = $m[1];
        $isTheme = (strpos($target, 'theme-') === 0);
        $dirName = AdminBeautify_Store::safeDir($isTheme ? substr($target, 6) : $target);
        if ($dirName === '') {
            return array('error' => '目录名不合法', 'code' => 400);
        }
        $baseDir = $isTheme ? self::themesDir() : AdminBeautify_Store::pluginsDir();
        $dest = $baseDir . '/' . $dirName;
        $curBackup = '';
        if (is_dir($dest)) {
            $curBackup = $isTheme ? self::backupTheme($dirName) : self::backup($dirName);
            if (!$curBackup) {
                return array('error' => '备份当前版本失败，已中止回滚', 'code' => 500);
            }
            self::removeDir($dest);
        }
        $ok = self::copyDir($src, $dest);
        if (!$ok) {
            if ($curBackup) {
                self::removeDir($dest);
                @rename($curBackup, $dest);
            }
            return array('error' => '回滚写入失败（检查写权限）', 'code' => 500);
        }
        return array(
            'data'    => array('dir' => $dirName, 'type' => $isTheme ? 'theme' : 'plugin'),
            'message' => '已回滚 ' . $dirName,
        );
    }
    public static function backupClear()
    {
        $base = AdminBeautify_Store::backupDir();
        $removed = 0;
        foreach ((array) @glob($base . '/*') as $path) {
            if (is_dir($path)) {
                if (self::removeDir($path)) {
                    $removed++;
                }
            } elseif (is_file($path)) {
                @unlink($path);
                $removed++;
            }
        }
        return array('data' => array('removed' => $removed), 'message' => '已清空备份');
    }
    public static function backupDelete($names)
    {
        $base = AdminBeautify_Store::backupDir();
        $baseReal = @realpath($base);
        $removed = 0;
        $errors = array();
        foreach ((array) $names as $name) {
            $name = basename(trim((string) $name));
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }
            $path = $base . '/' . $name;
            $real = @realpath($path);
            if ($real === false || $baseReal === false
                || strpos($real, $baseReal . DIRECTORY_SEPARATOR) !== 0
                || dirname($real) !== $baseReal) {
                $errors[] = $name;
                continue;
            }
            if (is_dir($real) && self::removeDir($real)) {
                $removed++;
            } else {
                $errors[] = $name;
            }
        }
        return array(
            'data'    => array('removed' => $removed, 'errors' => $errors),
            'message' => '已删除 ' . $removed . ' 份备份',
        );
    }
    public static function dirSize($dir)
    {
        $size = 0;
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $size += (int) $f->getSize();
                }
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        return $size;
    }
    private static function dateToTs($stamp)
    {
        $dt = \DateTime::createFromFormat('Ymd-His', $stamp);
        return $dt ? (int) $dt->getTimestamp() : 0;
    }
    public static function removeDir($dir)
    {
        $dir = rtrim((string) $dir, '/\\');
        if ($dir === '' || !file_exists($dir)) {
            return true;
        }
        if (is_link($dir) || is_file($dir)) {
            return @unlink($dir);
        }
        $items = @scandir($dir);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                self::removeDir($dir . '/' . $item);
            }
        }
        return @rmdir($dir);
    }
}
