<?php
/**
 * AB Store 内置模块 —— AJAX 动作（do=store.*）
 *
 * 全部挂在 AdminBeautify 已有的 /action/admin-beautify 上：
 *   · 不新增 actionTable 键 → 不会和旧 AB Store 的 `abs` 撞；
 *   · 鉴权复用 Action 的 checkWriteRequest()（管理员 + token + POST）。
 *
 * 返回数组约定：成功 ['data'=>…, 'message'=>…]；失败 ['error'=>…, 'code'=>…]。
 *
 * @package AdminBeautify
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
class AdminBeautify_StoreAjax
{
    public static function owns($do)
    {
        return strpos((string) $do, 'store.') === 0;
    }
    public static function handle($do)
    {
        $req = Typecho_Request::getInstance();
        $id = trim((string) $req->get('id', ''));
        $dir = AdminBeautify_Store::safeDir($req->get('dir', ''));
        $version = trim((string) $req->get('version', ''));
        switch ($do) {
            case 'store.refresh':
                $reg = AdminBeautify_Store::registry(true);
                $notify = self::pushUpdateNotice(true);
                return array(
                    'data'    => array(
                        'count'   => count($reg['plugins']),
                        'stale'   => !empty($reg['stale']),
                        'updates' => isset($notify['data']['count']) ? (int) $notify['data']['count'] : 0,
                    ),
                    'message' => $reg['error'] !== '' ? $reg['error'] : '商店列表已刷新',
                );
            case 'store.install':
            case 'store.upgrade':
                $item = AdminBeautify_Store::find($id, $dir);
                if (!$item) {
                    return array('error' => '商店里找不到这个插件（可能已下架）', 'code' => 404);
                }
                $where = AdminBeautify_Store::pluginsDir() . '/'
                    . AdminBeautify_Store::safeDir($item['directory'] ?: $item['name']);
                if ($do === 'store.upgrade' && !is_dir($where)) {
                    return array('error' => '该插件还没安装，请用「安装」', 'code' => 400);
                }
                return AdminBeautify_StoreInstall::install($item);
            case 'store.row':
                $token = trim((string) $req->get('token', ''));
                if ($id === '') {
                    return array('error' => '请输入插件口令', 'code' => 400);
                }
                if ($token !== '') {
                    $added = AdminBeautify_Store::addToken($id, $token);
                    if (isset($added['error'])) {
                        return array('error' => '口令或分享链接无效（插件不存在或已下架）', 'code' => 400);
                    }
                    $id = isset($added['data']['id']) ? (string) $added['data']['id'] : $id;
                }
                $item = AdminBeautify_Store::find($id, $dir);
                if (!$item && $token === '') {
                    AdminBeautify_Store::registry(true);
                    $item = AdminBeautify_Store::find($id, $dir);
                    if (!$item) {
                        $item = AdminBeautify_Store::find($id, $dir);
                    }
                }
                if (!$item) {
                    return array(
                        'error' => $token === ''
                            ? '商店里没有这个插件（可能不存在，或是需要口令的私密插件）'
                            : '口令或分享链接无效（插件不存在或已下架）',
                        'code'  => 404,
                    );
                }
                $cat = AdminBeautify_StoreCatalog::build();
                $row = null;
                foreach ($cat['rows'] as $rrow) {
                    if (!empty($rrow['store']['id']) && (string) $rrow['store']['id'] === (string) $item['id']) {
                        $row = $rrow;
                        break;
                    }
                }
                if (!$row) {
                    return array('error' => '插件信息获取失败，请刷新页面后重试', 'code' => 500);
                }
                return array(
                    'data'    => array(
                        'id'        => (string) $item['id'],
                        'private'   => !empty($row['private']),
                        'installed' => !empty($row['installed']),
                        'html'      => AdminBeautify_StorePage::renderRow($row, 'all'),
                    ),
                    'message' => !empty($row['private']) ? '已添加口令插件' : '已找到插件',
                );
            case 'store.uninstall':
                return AdminBeautify_StoreInstall::uninstall($dir);
            case 'store.block':
                if ($dir === '') {
                    return array('error' => '缺少目录名', 'code' => 400);
                }
                $all = ($version === '*' || $version === '');
                AdminBeautify_Store::block($dir, $all ? '*' : $version);
                return array(
                    'data'    => array('dir' => $dir, 'version' => $all ? '*' : $version),
                    'message' => $all
                        ? '已忽略该插件的所有更新，可在「更多」里恢复提醒'
                        : '已忽略 v' . $version . '，有新版本会继续提醒',
                );
            case 'store.unblock':
                AdminBeautify_Store::unblock($dir);
                return array('data' => array('dir' => $dir), 'message' => '已恢复更新提醒');
            case 'store.tokenAdd':
                $token = trim((string) $req->get('token', $req->get('shareToken', '')));
                return AdminBeautify_Store::addToken($id, $token);
            case 'store.tokenRemove':
                return AdminBeautify_Store::removeToken($id);
            case 'store.check':
                $data = AdminBeautify_StoreCatalog::build();
                $updates = array();
                foreach ($data['rows'] as $r) {
                    if (!empty($r['hasUpdate'])) {
                        $updates[] = array(
                            'dir'    => $r['dir'],
                            'name'   => $r['title'],
                            'local'  => $r['version'],
                            'remote' => $r['store']['version'],
                        );
                    }
                }
                return array('data' => array('updates' => $updates, 'count' => count($updates)));
            case 'store.notify':
                return self::pushUpdateNotice();
        }
        return array('error' => '未知的商店操作：' . $do, 'code' => 404);
    }
    private static function pushUpdateNotice($rearm = false)
    {
        $data = AdminBeautify_StoreCatalog::build();
        $updates = array();
        $sig = array();
        foreach ($data['rows'] as $r) {
            if (!empty($r['hasUpdate']) && !empty($r['store'])) {
                $updates[] = $r;
                $sig[] = $r['store']['id'] . '@' . $r['store']['version'];
            }
        }
        if (!$updates) {
            return array('data' => array('count' => 0), 'message' => '所有插件都是最新版');
        }
        sort($sig);
        $count = count($updates);
        $names = array();
        foreach (array_slice($updates, 0, 3) as $u) {
            $names[] = $u['title'];
        }
        $lines = array();
        foreach ($updates as $u) {
            $lines[] = '- **' . $u['title'] . '**  ' . $u['version'] . ' → ' . $u['store']['version'];
        }
        $result = AdminBeautify_Plugin::pushNotice(
            '发现 ' . $count . ' 个插件更新',
            implode('、', $names) . ($count > 3 ? ' 等 ' . $count . ' 个' : ''),
            'info',
            array(
                'dedup'  => 'store:updates:' . implode(',', $sig),
                'link'   => AdminBeautify_Store::adminUrl('plugins.php?tab=update'),
                'act'    => 'open-self',
                'src'    => '插件仓库',
                'detail' => "## 可更新的插件\n\n" . implode("\n", $lines)
                    . "\n\n打开「控制台 → 插件 → 可更新」可一键升级，旧版本会自动备份。",
                'rearm'  => (bool) $rearm,
            )
        );
        if (is_array($result) && isset($result['error'])) {
            return array('error' => (string) $result['error'], 'code' => 500);
        }
        return array('data' => array('count' => $count), 'message' => '已提醒 ' . $count . ' 个插件更新');
    }
}
