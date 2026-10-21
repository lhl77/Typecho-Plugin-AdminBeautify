<?php
/**
 * AB Store 内置模块 —— 本地插件 × 仓库插件 合并成一份列表
 *
 * 匹配规则：仓库的 directory ↔ 本地插件目录名（大小写不敏感）；
 *          都没匹配上时退一步按「标题」匹配（老站点的目录名可能和仓库 dir 不一致）。
 *
 * @package AdminBeautify
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
class AdminBeautify_StoreCatalog
{
    const LEGACY_DIR = 'AdminBeautifyStore';
    public static function build($force = false)
    {
        $reg = AdminBeautify_Store::allPlugins($force);
        $blocked = AdminBeautify_Store::blocked();
        $rows = array();
        $index = array();
        foreach (self::localPlugins() as $p) {
            $rows[] = array(
                'dir'        => $p['dir'],
                'title'      => $p['title'],
                'desc'       => $p['desc'],
                'version'    => $p['version'],
                'author'     => $p['author'],
                'homepage'   => $p['homepage'],
                'activated'  => $p['activated'],
                'canConfig'  => $p['config'],
                'toggleable' => !empty($p['toggleable']),
                'dependence' => $p['dependence'],
                'installed'  => true,
                'broken'     => false,
                'store'      => null,
                'hasUpdate'  => false,
                'blocked'    => false,
                'legacy'     => ($p['dir'] === self::LEGACY_DIR),
            );
            $index[strtolower($p['dir'])] = count($rows) - 1;
        }
        foreach ($reg['plugins'] as $s) {
            $key = strtolower(AdminBeautify_Store::safeDir($s['directory']));
            $hit = ($key !== '' && isset($index[$key])) ? $index[$key] : null;
            if ($hit === null && $s['name'] !== '') {
                $want = self::norm($s['name']);
                foreach ($rows as $i => $r) {
                    if (self::norm($r['title']) === $want) {
                        $hit = $i;
                        break;
                    }
                }
            }
            if ($hit !== null) {
                $rows[$hit]['store'] = $s;
                continue;
            }
            $rows[] = array(
                'dir'        => AdminBeautify_Store::safeDir($s['directory']),
                'title'      => $s['name'],
                'desc'       => $s['description'],
                'version'    => '',
                'author'     => $s['author'],
                'homepage'   => $s['homepage'],
                'activated'  => false,
                'canConfig'  => false,
                'toggleable' => false,
                'dependence' => true,
                'installed'  => false,
                'broken'     => false,
                'store'      => $s,
                'hasUpdate'  => false,
                'blocked'    => false,
                'legacy'     => false,
                'private'    => !empty($s['private']),
            );
        }
        foreach (self::activatedMap() as $name => $unused) {
            if (isset($index[strtolower($name)])) {
                continue;
            }
            $found = false;
            foreach ($rows as $r) {
                if (strcasecmp($r['dir'], $name) === 0) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                continue;
            }
            $rows[] = array(
                'dir'        => (string) $name,
                'title'      => (string) $name,
                'desc'       => '插件文件已被删除，但启用记录还在（建议清理）',
                'version'    => '',
                'author'     => '',
                'homepage'   => '',
                'activated'  => false,
                'canConfig'  => false,
                'toggleable' => false,
                'dependence' => false,
                'installed'  => false,
                'broken'     => true,
                'store'      => null,
                'hasUpdate'  => false,
                'blocked'    => false,
                'legacy'     => false,
            );
        }
        foreach ($rows as $i => $r) {
            if (!$r['store'] || !$r['installed']) {
                continue;
            }
            $remote = (string) $r['store']['version'];
            $local = (string) $r['version'];
            $newer = ($remote !== '' && $local !== '' && version_compare($remote, $local, '>'));
            if ($newer) {
                $rows[$i]['hasUpdate'] = true;
            }
            $blk = isset($blocked[$r['dir']]) ? (string) $blocked[$r['dir']] : '';
            if ($blk !== '' && ($blk === '*' || $blk === $remote)) {
                $rows[$i]['blocked'] = true;
                $rows[$i]['hasUpdate'] = false;
                $rows[$i]['blockedAll'] = ($blk === '*');
            }
        }
        usort($rows, array(__CLASS__, 'cmp'));
        return array(
            'rows'   => $rows,
            'counts' => self::counts($rows),
            'stale'  => !empty($reg['stale']),
            'error'  => isset($reg['error']) ? (string) $reg['error'] : '',
        );
    }
    private static function cmp($a, $b)
    {
        $ua = !empty($a['hasUpdate']) ? 0 : 1;
        $ub = !empty($b['hasUpdate']) ? 0 : 1;
        if ($ua !== $ub) {
            return $ua - $ub;
        }
        $sa = self::statusRank($a);
        $sb = self::statusRank($b);
        if ($sa !== $sb) {
            return $sa - $sb;
        }
        return strcasecmp($a['title'] !== '' ? $a['title'] : $a['dir'], $b['title'] !== '' ? $b['title'] : $b['dir']);
    }
    private static function statusRank($r)
    {
        if ($r['broken']) {
            return 4;
        }
        if (!$r['installed']) {
            return 3;
        }
        if (empty($r['toggleable'])) {
            return 1;
        }
        return $r['activated'] ? 0 : 2;
    }
    private static function counts(array $rows)
    {
        $c = array(
            'all' => count($rows), 'installed' => 0,
            'active' => 0, 'inactive' => 0, 'update' => 0, 'store' => 0
        );
        foreach ($rows as $r) {
            if (!empty($r['installed'])) {
                $c['installed']++;
            }
            if ($r['installed'] && $r['activated']) {
                $c['active']++;
            }
            if ($r['installed'] && !$r['activated']) {
                $c['inactive']++;
            }
            if ($r['hasUpdate']) {
                $c['update']++;
            }
            if ($r['store']) {
                $c['store']++;
            }
        }
        return $c;
    }
    public static function localPlugins()
    {
        $dir = AdminBeautify_Store::pluginsDir();
        $activated = self::activatedMap();
        $out = array();
        foreach ((array) @glob($dir . '/*', GLOB_ONLYDIR) as $path) {
            $name = basename($path);
            if ($name === '' || $name[0] === '.') {
                continue;
            }
            $file = $path . '/Plugin.php';
            if (!is_file($file)) {
                continue;
            }
            $info = array();
            try {
                $info = \Typecho\Plugin::parseInfo($file);
            } catch (Exception $e) {
            } catch (Throwable $e) {
            }
            $needsActivate = !empty($info['activate']) || !empty($info['deactivate'])
                || !empty($info['config']) || !empty($info['personalConfig']);
            $dependence = true;
            try {
                $dependence = \Typecho\Plugin::checkDependence(isset($info['since']) ? $info['since'] : '');
            } catch (Exception $e) {
            } catch (Throwable $e) {
            }
            $out[] = array(
                'dir'        => $name,
                'title'      => isset($info['title']) && $info['title'] !== '' ? $info['title'] : $name,
                'desc'       => isset($info['description']) ? trim((string) $info['description']) : '',
                'version'    => isset($info['version']) ? (string) $info['version'] : '',
                'author'     => isset($info['author']) ? (string) $info['author'] : '',
                'homepage'   => isset($info['homepage']) ? (string) $info['homepage'] : '',
                'config'     => !empty($info['config']),
                'toggleable' => (bool) $needsActivate,
                'dependence' => (bool) $dependence,
                'activated'  => $needsActivate ? isset($activated[$name]) : true,
            );
        }
        return $out;
    }
    public static function activatedMap()
    {
        try {
            $export = \Typecho\Plugin::export();
            return (!empty($export['activated']) && is_array($export['activated'])) ? $export['activated'] : array();
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        return array();
    }
    private static function norm($s)
    {
        return strtolower(preg_replace('/\s+/u', '', (string) $s));
    }
}
