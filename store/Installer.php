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
