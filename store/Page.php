<?php
/**
 * AB Store 内置模块 —— 插件页（plugins.php）的统一界面
 *
 * 服务端渲染，无 JS 也能看到完整列表、也能启用/禁用（走 core 的链接）；
 * JS 只负责：分段筛选、搜索、安装/更新/卸载的异步调用。
 *
 * @package AdminBeautify
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
class AdminBeautify_StorePage
{
    public static function tab()
    {
        $tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'all';
        return in_array($tab, array('all', 'active', 'inactive', 'update', 'store'), true) ? $tab : 'all';
    }
    public static function render()
    {
        try {
            AdminBeautify_StoreInstall::pruneAllBackups(1);
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        $data = AdminBeautify_StoreCatalog::build();
        $rows = $data['rows'];
        $counts = $data['counts'];
        $tab = self::tab();
        $security = Typecho_Widget::widget('Widget_Security');
        $ajaxUrl = Typecho_Common::url('/action/admin-beautify', Typecho_Widget::widget('Widget_Options')->index);
        $token = $security->getToken($ajaxUrl);
        $tabs = array(
            'all'      => array('全部', $counts['all'], 'apps'),
            'update'   => array('可更新', $counts['update'], 'system_update_alt'),
            'active'   => array('已启用', $counts['active'], 'check_circle'),
            'inactive' => array('已停用', $counts['inactive'], 'do_not_disturb_on'),
            'store'    => array('AB Store', $counts['store'], 'storefront'),
        );
        $html = '<main class="main"><div class="body container">';
        $html .= '<div class="typecho-page-title abp-title-bar"><h2>插件</h2>'
            . '<span class="abp-by" title="AB Store · 作者 LHL">by <b>LHL</b> · AB Store</span>'
            . '<a class="abp-submit" href="' . self::url(AdminBeautify_Store::BASE) . '"'
            . ' target="_blank" rel="noopener noreferrer" title="向 AB Store 投稿插件">投稿插件</a></div>';
        $html .= '<div class="row typecho-page-main" role="main"><div class="col-mb-12 abp-wrap" data-tab="'
            . self::e($tab) . '" data-ajax="' . self::attr($ajaxUrl) . '" data-token="' . self::attr($token) . '">';
        $html .= '<div class="abp-bar"><div class="abp-tabs" role="tablist">';
        foreach ($tabs as $key => $info) {
            $active = ($key === $tab) ? ' is-active' : '';
            $html .= '<a class="abp-tab' . $active . '" href="?tab=' . $key . '" data-tab="' . $key
                . '" role="tab" title="' . self::attr($info[0]) . '" aria-label="' . self::attr($info[0] . ' ' . (int) $info[1]) . '"'
                . ' aria-selected="' . ($key === $tab ? 'true' : 'false') . '">'
                . '<span class="material-icons-round abp-tab-i" aria-hidden="true">' . self::e($info[2]) . '</span>'
                . '<span class="abp-tab-t">' . self::e($info[0]) . '</span>'
                . '<span class="abp-tab-n">' . (int) $info[1] . '</span></a>';
        }
        $html .= '</div>'
            . '<div class="abp-bar-tools">'
            . '<label class="abp-find" for="abp-q">'
            . '<span class="material-icons-round">search</span>'
            . '<input type="search" id="abp-q" placeholder="搜索插件 / 作者 / 目录…" autocomplete="off">'
            . '</label>'
            . '<button type="button" class="abp-iconbtn abp-token-btn" id="abp-token"'
            . ' title="口令安装：输入插件口令（ID）或完整分享链接" aria-label="口令安装">'
            . '<span class="material-icons-round">key</span>'
            . '<span class="abp-token-label">口令安装</span>'
            . '</button>'
            . '<div class="abp-sortwrap">'
            . '<button type="button" class="abp-iconbtn" id="abp-sort-btn" aria-haspopup="true" aria-expanded="false" title="排序方式">'
            . '<span class="material-icons-round">sort</span><span class="abp-sort-label" id="abp-sort-label">默认顺序</span>'
            . '</button>'
            . '<div class="abp-sortmenu" id="abp-sort-menu" hidden role="menu">'
            . '<button type="button" data-sort="default" class="is-checked" title="可更新 → 已启用 → 即插即用 → 已停用">默认顺序</button>'
            . '<button type="button" data-sort="name-asc">名称 A → Z</button>'
            . '<button type="button" data-sort="name-desc">名称 Z → A</button>'
            . '</div></div>'
            . '<input type="hidden" id="abp-sort" value="default">'
            . '<button type="button" class="abp-iconbtn" id="abp-refresh" title="重新从商店拉取列表">'
            . '<span class="material-icons-round">refresh</span>'
            . '</button>'
            . '</div></div>';
        $html .= '<div class="abp-batch" id="abp-batch" hidden>'
            . '<span class="abp-batch-n">已选 <b id="abp-batch-count">0</b> 项</span>'
            . '<span class="abp-batch-acts">'
            . '<button type="button" class="abp-btn abp-btn-filled" data-batch="install" aria-label="安装所选">'
            . '<span class="abp-btn-t">安装<span class="abp-batch-suf">所选</span></span></button>'
            . '<button type="button" class="abp-btn abp-btn-filled" data-batch="update" aria-label="更新所选">'
            . '<span class="abp-btn-t">更新<span class="abp-batch-suf">所选</span></span></button>'
            . '<button type="button" class="abp-btn abp-btn-outline" data-batch="disable" aria-label="禁用所选">'
            . '<span class="abp-btn-t">禁用<span class="abp-batch-suf">所选</span></span></button>'
            . '<button type="button" class="abp-btn abp-btn-outline abp-btn-warn" data-batch="uninstall" aria-label="卸载所选">'
            . '<span class="abp-btn-t">卸载<span class="abp-batch-suf">所选</span></span></button>'
            . '<button type="button" class="abp-btn abp-btn-icon" data-batch="clear" aria-label="取消选择">'
            . '<span class="material-icons-round">close</span></button>'
            . '</span></div>';
        if ($data['error'] !== '') {
            $html .= '<div class="abp-note">' . self::e($data['error'])
                . ' 已安装的插件不受影响，仍可正常启用 / 停用。</div>';
        }
        $privateRows = array();
        $normalRows = array();
        foreach ($rows as $row) {
            if (!empty($row['private']) && empty($row['installed'])) {
                $privateRows[] = $row;
            } else {
                $normalRows[] = $row;
            }
        }
        $html .= '<div class="abp-list" id="abp-list">';
        if (!$rows) {
            $html .= '<div class="abp-empty"><span class="material-icons-round">extension</span><p>没有找到插件</p></div>';
        }
        if ($privateRows) {
            $html .= '<section class="abp-private" id="abp-private">'
                . '<div class="abp-private-h">'
                . '<span class="material-icons-round">key</span>'
                . '<b>私密插件</b><span class="abp-private-n">口令安装 · ' . count($privateRows) . ' 个</span>'
                . '</div>';
            foreach ($privateRows as $row) {
                $html .= self::row($row, $tab, $security, $ajaxUrl);
            }
            $html .= '</section>';
        }
        foreach ($normalRows as $row) {
            $html .= self::row($row, $tab, $security, $ajaxUrl);
        }
        $html .= '</div>';
        $html .= '<div class="abp-foot">共 ' . (int) $counts['all'] . ' 个插件 · 商店源 '
            . self::e(AdminBeautify_Store::BASE) . ' · 安装前会自动备份旧版本到 <code>usr/plugins/.ab-store/backup</code>'
            . '</div>';
        $html .= '</div></div></div></main>';
        $cfg = array(
            'ajaxUrl' => $ajaxUrl,
            'token'   => $token,
            'tab'     => $tab,
        );
        $html .= '<script>window.__AB_STORE__=' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
        return $html;
    }
    public static function renderRow(array $r, $tab = 'all')
    {
        $security = Typecho_Widget::widget('Widget_Security');
        $ajaxUrl = Typecho_Common::url('/action/admin-beautify', Typecho_Widget::widget('Widget_Options')->index);
        return self::row($r, $tab, $security, $ajaxUrl);
    }
    private static function row(array $r, $tab, $security, $ajaxUrl)
    {
        $store = $r['store'];
        $flags = array();
        if ($r['installed'] && $r['activated']) {
            $flags[] = 'active';
        }
        if ($r['installed'] && !$r['activated']) {
            $flags[] = 'inactive';
        }
        if ($r['hasUpdate']) {
            $flags[] = 'update';
        }
        if ($store) {
            $flags[] = 'store';
        }
        if ($r['broken']) {
            $flags[] = 'broken';
        }
        if (!$r['installed']) {
            $flags[] = 'notinstalled';
        }
        if ($tab !== 'all' && !in_array($tab, $flags, true)) {
            $flags[] = 'is-hidden';
        }
        $search = strtolower($r['title'] . ' ' . $r['dir'] . ' ' . $r['desc'] . ' ' . $r['author']
            . ($store ? ' ' . $store['name'] . ' ' . implode(' ', $store['tags']) : ''));
        $initial = self::initial($r['title'] !== '' ? $r['title'] : $r['dir']);
        $sortName = strtolower(self::plain($r['title'] !== '' ? $r['title'] : $r['dir']));
        $rank = $r['broken'] ? 4
            : (!$r['installed'] ? 3
                : (empty($r['toggleable']) ? 1 : ($r['activated'] ? 0 : 2)));
        $weight = $r['hasUpdate'] ? $rank : (10 + $rank);
        $isOff = empty($r['activated']);
        $html = '<article class="abp-row' . ($isOff ? ' is-off' : '') . '" data-flags="'
            . self::attr(implode(' ', $flags)) . '"'
            . ' data-sort-name="' . self::attr($sortName) . '" data-weight="' . (int) $weight . '"'
            . ($store && $store['id'] !== '' ? ' data-store-id="' . self::attr($store['id']) . '"' : '')
            . (!empty($r['private']) ? ' data-private="1"' : '')
            . ' data-search="' . self::attr($search) . '">';
        $canInstall = (!$r['installed'] && $store);
        if (($r['installed'] && !$r['broken'] && !empty($r['toggleable'])) || $canInstall) {
            $disableUrl = ($r['installed'] && $r['activated'] && !empty($r['toggleable']))
                ? $security->getIndex('/action/plugins-edit?deactivate=' . urlencode($r['dir']))
                : '';
            $html .= '<span class="abp-check-wrap"><input type="checkbox" class="abp-check"'
                . ' data-dir="' . self::attr($r['dir']) . '"'
                . ' data-name="' . self::attr(self::plain($r['title'])) . '"'
                . ($canInstall ? ' data-id="' . self::attr($store['id']) . '"'
                    . ' data-install-dir="' . self::attr($store['directory']) . '"' : '')
                . ' data-can-install="' . ($canInstall ? '1' : '0') . '"'
                . ' data-can-update="' . ($r['hasUpdate'] ? '1' : '0') . '"'
                . ' data-can-uninstall="' . (($r['installed'] && $r['dir'] !== AdminBeautify_StoreInstall::SELF_DIR) ? '1' : '0') . '"'
                . ' data-disable-url="' . self::attr($disableUrl) . '"'
                . ' aria-label="选择 ' . self::attr(self::plain($r['title'])) . '"></span>';
        } else {
            $html .= '<span class="abp-check-wrap abp-check-none"></span>';
        }
        $html .= '<div class="abp-ico">' . self::e($initial) . '</div>';
        $html .= '<div class="abp-main"><div class="abp-head">';
        $html .= '<span class="abp-name">' . self::e(self::plain($r['title'])) . '</span>';
        if ($r['installed'] && $r['version'] !== '') {
            $html .= '<span class="abp-ver">' . self::e($r['version']) . '</span>';
        }
        if ($r['hasUpdate'] && $store) {
            $html .= '<span class="abp-arrow">→</span><span class="abp-ver abp-ver-new">'
                . self::e($store['version']) . '</span>';
        }
        if ($r['hasUpdate']) {
            $html .= '<span class="abp-chip chip-update">可更新</span>';
        }
        if ($r['broken']) {
            $html .= '<span class="abp-chip chip-bad">文件缺失</span>';
        } elseif ($r['installed']) {
            if (empty($r['toggleable'])) {
                $html .= '<span class="abp-chip chip-on">即插即用</span>';
            } else {
                $html .= $r['activated']
                    ? '<span class="abp-chip chip-on">已启用</span>'
                    : '<span class="abp-chip chip-off">已停用</span>';
            }
        } else {
            $html .= '<span class="abp-chip chip-new">未安装</span>';
        }
        if ($store) {
            $html .= '<span class="abp-chip chip-src" title="来自 AB Store">商店</span>';
        }
        if ($r['legacy']) {
            $html .= '<span class="abp-chip chip-legacy" title="旧 AB Store 插件，功能已并入 AB Admin">已并入</span>';
        }
        if ($r['blocked']) {
            $html .= '<span class="abp-chip chip-off">已忽略更新</span>';
        }
        if ($r['installed'] && !$r['dependence']) {
            $html .= '<span class="abp-chip chip-bad">版本不匹配</span>';
        }
        $html .= '</div>';
        if ($r['desc'] !== '') {
            $desc = self::plain($r['desc']);
            if ($desc !== '') {
                $html .= '<div class="abp-desc">' . self::e(self::trim($desc, 140)) . '</div>';
            }
        }
        $meta = array();
        $author = self::plain($r['author']);
        if ($author !== '') {
            $meta[] = '<span class="abp-mi">' . self::e($author) . '</span>';
        }
        if ($r['installed'] && $r['dir'] !== '') {
            $meta[] = '<span class="abp-mi"><code>' . self::e($r['dir']) . '</code></span>';
        }
        if ($store && $store['downloads'] > 0) {
            $meta[] = '<span class="abp-mi abp-dl">'
                . number_format((int) $store['downloads']) . ' 次下载</span>';
        }
        if ($store && !empty($store['tags'])) {
            $meta[] = '<span class="abp-mi">'
                . implode(' / ', array_map(array(__CLASS__, 'e'), array_slice($store['tags'], 0, 3)))
                . '</span>';
        }
        if ($meta) {
            $html .= '<div class="abp-meta">' . implode('', $meta) . '</div>';
        }
        $menuLinks = '';
        if ($store && $store['id'] !== '') {
            $storeUrl = AdminBeautify_Store::BASE . '/plugin/' . rawurlencode($store['id']);
            if (!empty($store['shareToken'])) {
                $storeUrl .= '?token=' . rawurlencode($store['shareToken']);
            }
            $menuLinks .= '<a class="abp-menu-item" href="' . self::url($storeUrl)
                . '" target="_blank" rel="noopener noreferrer">AB Store 插件详情'
                . '<span class="material-icons-round abp-menu-ext">open_in_new</span></a>';
        }
        if ($r['homepage'] !== '') {
            $menuLinks .= '<a class="abp-menu-item" href="' . self::url($r['homepage'])
                . '" target="_blank" rel="noopener noreferrer">插件主页'
                . '<span class="material-icons-round abp-menu-ext">open_in_new</span></a>';
        }
        if ($r['blocked'] && $store) {
            $html .= '<div class="abp-note abp-note-inline">'
                . (!empty($r['blockedAll'])
                    ? '已忽略该插件的所有更新。'
                    : '已忽略 v' . self::e($store['version']) . ' 的更新。')
                . ' <button type="button" class="abp-link" data-store-act="unblock" data-dir="'
                . self::attr($r['dir']) . '">恢复提醒</button></div>';
        }
        if ($r['legacy']) {
            $html .= '<div class="abp-note abp-note-inline">功能已并入 AB Admin —— 商店就在本页的'
                . ' <button type="button" class="abp-link" data-goto-tab="store">「AB Store」</button></div>';
        }
        $html .= '</div>';
        $html .= '<div class="abp-act">';
        if ($store && $r['hasUpdate']) {
            $html .= self::btn('upgrade', '更新', 'primary', array(
                'data-id'  => $store['id'],
                'data-dir' => $r['dir'],
                'data-ver' => $store['version'],
            ));
        }
        if (!$r['installed'] && $store) {
            $html .= self::btn('install', '安装', 'primary', array(
                'data-id'  => $store['id'],
                'data-dir' => $store['directory'],
                'data-ver' => $store['version'],
                'data-name' => $r['title'],
            ));
        }
        if ($r['installed']) {
            if ($r['canConfig'] && $r['activated']) {
                $cfgUrl = Typecho_Widget::widget('Widget_Options')->adminUrl . 'options-plugin.php?config=' . urlencode($r['dir']);
                $html .= '<a class="abp-btn abp-btn-outline" href="' . self::url($cfgUrl) . '">设置</a>';
            }
            if ($r['legacy']) {
                if ($r['activated']) {
                    $off = $security->getIndex('/action/plugins-edit?deactivate=' . urlencode($r['dir']));
                    $html .= '<a class="abp-btn abp-btn-outline" href="' . self::url($off)
                        . '" lang="' . self::e('确认禁用 AB Store？功能已并入 AB Admin，禁用后商店仍可用（在「控制台 → 插件」里）。') . '">禁用</a>';
                } else {
                    $on = $security->getIndex('/action/plugins-edit?activate=' . urlencode($r['dir']));
                    $html .= '<a class="abp-btn abp-btn-filled" href="' . self::url($on) . '">启用</a>';
                }
            } elseif ($r['broken']) {
                $fix = $security->getIndex('/action/plugins-edit?deactivate=' . urlencode($r['dir']));
                $html .= '<a class="abp-btn abp-btn-outline" href="' . self::url($fix) . '">清理启用记录</a>';
            } elseif (empty($r['toggleable'])) {
            } else {
                if ($r['activated']) {
                    $off = $security->getIndex('/action/plugins-edit?deactivate=' . urlencode($r['dir']));
                    $html .= '<a class="abp-btn abp-btn-outline" href="' . self::url($off)
                        . '" lang="' . self::e('确认要禁用插件 ' . $r['dir'] . ' 吗？') . '">禁用</a>';
                } else {
                    $on = $security->getIndex('/action/plugins-edit?activate=' . urlencode($r['dir']));
                    $html .= '<a class="abp-btn abp-btn-filled" href="' . self::url($on) . '">启用</a>';
                }
            }
            $acts = '';
            if ($store && $r['hasUpdate']) {
                $acts .= '<button type="button" class="abp-menu-item" data-store-act="block" data-dir="'
                    . self::attr($r['dir']) . '" data-version="' . self::attr($store['version']) . '">'
                    . '忽略 ' . self::e($store['version']) . '</button>'
                    . '<button type="button" class="abp-menu-item" data-store-act="block" data-dir="'
                    . self::attr($r['dir']) . '" data-version="*">忽略本插件所有更新</button>';
            } elseif ($store && $r['blocked'] && empty($r['blockedAll'])) {
                $acts .= '<button type="button" class="abp-menu-item" data-store-act="block" data-dir="'
                    . self::attr($r['dir']) . '" data-version="*">忽略本插件所有更新</button>';
            }
            if ($r['dir'] !== AdminBeautify_StoreInstall::SELF_DIR && !$r['activated']) {
                $acts .= '<button type="button" class="abp-menu-item abp-danger" data-store-act="uninstall" data-dir="'
                    . self::attr($r['dir']) . '" data-name="' . self::attr($r['title']) . '">卸载（删除文件）</button>';
            }
            if (!empty($r['private']) && $store && $store['id'] !== '') {
                $acts .= '<button type="button" class="abp-menu-item" data-store-act="tokenRemove" data-id="'
                    . self::attr($store['id']) . '">移出口令列表</button>';
            }
            if ($acts === '') {
                $more = $menuLinks;
            } elseif ($menuLinks === '') {
                $more = $acts;
            } else {
                $more = $menuLinks . '<div class="abp-menu-sep"></div>' . $acts;
            }
            if ($more !== '') {
                $html .= self::moreMenu($more);
            }
        } elseif ($store) {
            $acts = '';
            if (!empty($r['private']) && $store['id'] !== '') {
                $acts .= '<button type="button" class="abp-menu-item" data-store-act="tokenRemove" data-id="'
                    . self::attr($store['id']) . '">移出口令列表</button>';
            }
            if ($acts === '') {
                $more = $menuLinks;
            } elseif ($menuLinks === '') {
                $more = $acts;
            } else {
                $more = $menuLinks . '<div class="abp-menu-sep"></div>' . $acts;
            }
            if ($more !== '') {
                $html .= self::moreMenu($more);
            }
        }
        $html .= '</div></article>';
        return $html;
    }
    private static function btn($act, $label, $style, array $data)
    {
        $attrs = '';
        foreach ($data as $k => $v) {
            $attrs .= ' ' . $k . '="' . self::attr($v) . '"';
        }
        return '<button type="button" class="abp-btn abp-btn-' . $style . '" data-store-act="' . $act . '"'
            . $attrs . '><span class="abp-btn-t">' . self::e($label) . '</span></button>';
    }
    private static function moreMenu($inner)
    {
        return '<div class="abp-more-wrap"><button type="button" class="abp-btn abp-btn-icon abp-more"'
            . ' aria-label="更多操作"><span class="material-icons-round">more_vert</span></button>'
            . '<div class="abp-menu" hidden>' . $inner . '</div></div>';
    }
    private static function plain($s)
    {
        $s = (string) $s;
        for ($i = 0; $i < 3; $i++) {
            $next = strip_tags(html_entity_decode($s, ENT_QUOTES, 'UTF-8'));
            if ($next === $s) {
                break;
            }
            $s = $next;
        }
        return trim(preg_replace('/\s+/u', ' ', $s));
    }
    private static function initial($s)
    {
        $s = trim((string) $s);
        if ($s === '') {
            return 'P';
        }
        if (function_exists('mb_substr')) {
            $c = mb_substr($s, 0, 1, 'UTF-8');
        } else {
            $c = substr($s, 0, 1);
        }
        return strtoupper($c);
    }
    private static function trim($s, $len)
    {
        $s = trim(preg_replace('/\s+/u', ' ', (string) $s));
        if (function_exists('mb_strlen') && mb_strlen($s, 'UTF-8') > $len) {
            return mb_substr($s, 0, $len, 'UTF-8') . '…';
        }
        return $s;
    }
    public static function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
    public static function attr($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
    public static function url($s)
    {
        $s = (string) $s;
        if ($s === '') {
            return '';
        }
        if (!preg_match('#^(https?://|/|\?)#i', $s)) {
            return '';
        }
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
