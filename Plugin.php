<?php
/**
 * AB-Admin (Admin Beautify) - 后台管理界面美化插件，包含登录界面美化 (原LoginBeautify)，Material Design 3风格
 *
 * @package AB-Admin (Admin Beautify)
 * @author LHL
 * @version 2.1.9
 * @link https://github.com/lhl77/Typecho-Plugin-AdminBeautify
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class AdminBeautify_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 判断当前是否为登录页（未登录状态）
     */
    private static function isLoginPage()
    {
        try {
            return !Typecho_Widget::widget('Widget_User')->hasLogin();
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * JSON 编码字符串，用于 JS 输出
     */
    private static function jsString($s)
    {
        return json_encode((string) $s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 激活插件
     */
    public static function activate()
    {
        Typecho_Plugin::factory('admin/header.php')->header = array(__CLASS__, 'renderHeader');
        Typecho_Plugin::factory('admin/footer.php')->begin = array(__CLASS__, 'renderFooter');
        Typecho_Plugin::factory('admin/footer.php')->end = array(__CLASS__, 'renderLoginFooter');
        Utils\Helper::addAction('admin-beautify', 'AdminBeautify_Action');
        return _t('AdminBeautify 已启用（含登录页美化）');
    }

    /**
     * 禁用插件
     */
    public static function deactivate()
    {
        Utils\Helper::removeAction('admin-beautify');
        return _t('AdminBeautify 已禁用');
    }

    /**
     * 插件配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // ====== 读取当前主题色 ======
        $abConfigColors = array(
            // 调整后的主色/辅色：更贴近 Material Design 3 的语义色，同时略微降低饱和度，降低视觉疲劳
            'purple' => array('#7D5260', '#9E7B8A'),
            'blue'   => array('#556270', '#7A8A9E'),
            'teal'   => array('#4A6363', '#6A8A8A'),
            'green'  => array('#55624C', '#7A8A6E'),
            'orange' => array('#725A42', '#9E8062'),
            'pink'   => array('#74565F', '#9E7A85'),
            'red'    => array('#775654', '#A27A78'),
        );
        try {
            $abOpt = Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautify');
            $abScheme = isset($abOpt->primaryColor) ? (string) $abOpt->primaryColor : 'purple';
        } catch (Exception $e) {
            $abScheme = 'purple';
        }
        if (!isset($abConfigColors[$abScheme])) $abScheme = 'purple';
        $abC1 = $abConfigColors[$abScheme][0];
        $abC2 = $abConfigColors[$abScheme][1];
        $abVer = '2.1.9';

        // ====== 插件信息头部 ======
        echo '<div id="ab-header-banner" style="margin:16px 0 24px;padding:24px 28px;background:linear-gradient(135deg,' . $abC1 . ',' . $abC2 . ');color:#fff;border-radius:28px;box-shadow:0 4px 16px rgba(0,0,0,.18);text-shadow:0 1px 3px rgba(0,0,0,.25)">
            <div style="display:flex;align-items:center;gap:20px;margin-bottom:16px">
                <div style="width:64px;height:64px;background:rgba(255,255,255,.15);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:32px;backdrop-filter:blur(10px);flex-shrink:0;text-shadow:none">🎨</div>
                <div style="flex:1">
                    <h2 style="margin:0 0 6px;font-size:22px;font-weight:600;letter-spacing:-0.02em">Admin Beautify <span style="font-size:13px;font-weight:400;opacity:.8;margin-left:4px">v' . $abVer . '</span></h2>
                    <p style="margin:0;font-size:14px;opacity:0.9;line-height:1.6">后台管理界面 + 登录界面美化 · Material Design 3 风格</p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;text-shadow:none">
                <a id="ab-btn-github" href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;background:rgba(255,255,255,.22);color:#fff !important;border-radius:20px;font-size:13px;font-weight:500;text-decoration:none;backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.35);transition:background .2s;cursor:pointer;text-shadow:0 1px 2px rgba(0,0,0,.2)" onmouseover="this.style.background=\'rgba(255,255,255,.35)\'" onmouseout="this.style.background=\'rgba(255,255,255,.22)\'">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
                    GitHub
                </a>
                <a id="ab-btn-blog" href="https://blog.lhl.one/artical/977.html" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;background:rgba(255,255,255,.22);color:#fff !important;border-radius:20px;font-size:13px;font-weight:500;text-decoration:none;backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.35);transition:background .2s;cursor:pointer;text-shadow:0 1px 2px rgba(0,0,0,.2)" onmouseover="this.style.background=\'rgba(255,255,255,.35)\'" onmouseout="this.style.background=\'rgba(255,255,255,.22)\'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    作者博客
                </a>
                <button id="ab-btn-update" type="button" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;background:rgba(255,255,255,.22);color:#fff;border-radius:20px;font-size:13px;font-weight:500;line-height:1.6;border:1px solid rgba(255,255,255,.35);backdrop-filter:blur(6px);transition:background .2s;cursor:pointer;text-shadow:0 1px 2px rgba(0,0,0,.2)" onmouseover="this.style.background=\'rgba(255,255,255,.35)\'" onmouseout="this.style.background=\'rgba(255,255,255,.22)\'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                    检查更新
                </button>
            </div>
        </div>';

        // ================================================================
        // ====== MD3 折叠卡片 — 暗色模式适配 ======
        // ================================================================
        echo '<style>
[data-theme="dark"] #ab-card-admin,
[data-theme="dark"] #ab-card-login {
    background: var(--md-surface-container-low, #1d1b20) !important;
    border-color: var(--md-outline-variant, rgba(255,255,255,.12)) !important;
    box-shadow: 0 1px 3px rgba(0,0,0,.4), 0 2px 8px rgba(0,0,0,.25) !important;
}
[data-theme="dark"] #ab-card-admin-hdr:hover,
[data-theme="dark"] #ab-card-login-hdr:hover {
    background: rgba(255,255,255,.05) !important;
}
[data-theme="dark"] .ab-card-title {
    color: var(--md-on-surface, #e6e1e5) !important;
}
[data-theme="dark"] .ab-card-subtitle {
    color: var(--md-on-surface-variant, #cac4d0) !important;
}
[data-theme="dark"] .ab-card-tip {
    background: rgba(96,165,250,.1) !important;
    border-color: rgba(96,165,250,.25) !important;
}
[data-theme="dark"] .ab-card-tip-text {
    color: #93c5fd !important;
}
[data-theme="dark"] .ab-card-tip-green {
    background: rgba(74,222,128,.08) !important;
    border-color: rgba(74,222,128,.2) !important;
}
[data-theme="dark"] .ab-card-tip-green-text {
    color: #86efac !important;
}
[data-theme="dark"] .ab-card-tip-green-text code {
    background: rgba(74,222,128,.15) !important;
    color: #86efac !important;
}
[data-theme="dark"] #ab-compat-scripts-list .ab-compat-list-title {
    color: var(--md-on-surface, #e6e1e5) !important;
}
[data-theme="dark"] .ab-compat-script-item {
    background: var(--md-surface-container, #211f26) !important;
    border-color: var(--md-outline-variant, rgba(255,255,255,.12)) !important;
}
[data-theme="dark"] .ab-compat-script-item .ab-compat-name {
    color: var(--md-on-surface, #e6e1e5) !important;
}
[data-theme="dark"] .ab-compat-script-item .ab-compat-desc {
    color: var(--md-on-surface-variant, #cac4d0) !important;
}
[data-theme="dark"] .ab-compat-script-item .ab-compat-meta {
    color: rgba(255,255,255,.4) !important;
}
[data-theme="dark"] .ab-compat-script-item .ab-compat-meta[style*="background"] {
    background: rgba(255,255,255,.08) !important;
}
[data-theme="dark"] .ab-compat-script-item .ab-compat-toggle-bg {
    background: rgba(255,255,255,.2) !important;
}
[data-theme="dark"] .ab-compat-script-item .ab-compat-toggle-bg.active {
    background: var(--md-primary, #d0bcff) !important;
}
[data-theme="dark"] .ab-compat-empty {
    background: rgba(250,204,21,.08) !important;
    border-color: rgba(250,204,21,.2) !important;
    color: #fde68a !important;
}
[data-theme="dark"] #ab-card-pwa,
[data-theme="dark"] #ab-card-compat,
[data-theme="dark"] #ab-card-perf,
[data-theme="dark"] #ab-card-editor {
    background: var(--md-surface-container-low, #1d1b20) !important;
    border-color: var(--md-outline-variant, rgba(255,255,255,.12)) !important;
    box-shadow: 0 1px 3px rgba(0,0,0,.3), 0 2px 12px rgba(0,0,0,.2) !important;
}
[data-theme="dark"] #ab-card-pwa-hdr:hover,
[data-theme="dark"] #ab-card-compat-hdr:hover,
[data-theme="dark"] #ab-card-perf-hdr:hover,
[data-theme="dark"] #ab-card-editor-hdr:hover {
    background: rgba(255,255,255,.04) !important;
}
</style>';

        // ================================================================
        // ====== 管理后台设置（MD3 折叠卡片） ======
        // ================================================================
        echo '<div id="ab-card-admin" style="margin:0 0 16px;border-radius:20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08),0 2px 12px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.06);overflow:hidden">
            <div id="ab-card-admin-hdr" style="display:flex;align-items:center;gap:12px;padding:18px 22px;cursor:pointer;user-select:none;-webkit-user-select:none;transition:background .15s" onmouseover="this.style.background=\'rgba(0,0,0,.025)\'" onmouseout="this.style.background=\'\'">
                <div id="ab-card-admin-strip" style="width:3px;height:36px;background:' . $abC1 . ';border-radius:2px;flex-shrink:0;transition:background .3s"></div>
                <div id="ab-card-admin-icon" style="width:40px;height:40px;background:' . $abC1 . '1a;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;transition:background .3s">⚙️</div>
                <div style="flex:1;min-width:0">
                    <div class="ab-card-title" style="font-size:15px;font-weight:600;color:#1c1b1f;line-height:1.3">管理后台设置</div>
                    <div class="ab-card-subtitle" style="font-size:12px;color:#79747e;margin-top:2px">主题色、暗色模式、圆角、动画、布局</div>
                </div>
                <svg id="ab-card-admin-chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . $abC1 . '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;transition:transform .35s"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div id="ab-card-admin-body" style="overflow:hidden;max-height:9999px;padding:0 16px;transition:max-height .4s cubic-bezier(.4,0,.2,1)"></div>
        </div>';

        // 主题色选择
        $primaryColor = new Typecho_Widget_Helper_Form_Element_Select(
            'primaryColor',
            array(
                'purple'  => '🟣 紫 (默认)',
                'blue'    => '🔵 蓝',
                'teal'    => '🩵 青',
                'green'   => '🟢 绿',
                'orange'  => '🟠 橙',
                'pink'    => '🩷 粉',
                'red'     => '🔴 红',
            ),
            'purple',
            _t('主题色'),
            _t('选择管理后台的主题色方案')
        );
        $form->addInput($primaryColor);

        // 暗色模式
        $darkMode = new Typecho_Widget_Helper_Form_Element_Select(
            'darkMode',
            array(
                'auto'  => '跟随系统',
                'light' => '浅色模式',
                'dark'  => '深色模式',
            ),
            'auto',
            _t('颜色模式'),
            _t('选择后台的明暗模式')
        );
        $form->addInput($darkMode);

        // 圆角大小
        $borderRadius = new Typecho_Widget_Helper_Form_Element_Select(
            'borderRadius',
            array(
                'small'  => '小圆角',
                'medium' => '中圆角 (默认)',
                'large'  => '大圆角',
            ),
            'medium',
            _t('圆角风格'),
            _t('控制界面元素的圆角大小')
        );
        $form->addInput($borderRadius);

        // 开启动画
        $enableAnimation = new Typecho_Widget_Helper_Form_Element_Select(
            'enableAnimation',
            array(
                '1' => '开启',
                '0' => '关闭',
            ),
            '1',
            _t('过渡动画'),
            _t('是否开启界面元素的过渡动画效果')
        );
        $form->addInput($enableAnimation);

        // 导航栏位置
        $navPosition = new Typecho_Widget_Helper_Form_Element_Select(
            'navPosition',
            array(
                'left' => '侧边栏 (默认)',
                'top'  => '导航栏 (原版)',
            ),
            'left',
            _t('导航栏位置'),
            _t('选择导航栏在页面顶部还是左侧显示（仅桌面端生效，移动端始终为顶部折叠菜单）')
        );
        $form->addInput($navPosition);

        // 插件页展示方式
        $pluginCardView = new Typecho_Widget_Helper_Form_Element_Select(
            'pluginCardView',
            array(
                '1' => '卡片网格 (默认)',
                '0' => '原始表格',
            ),
            '1',
            _t('插件列表样式'),
            _t('选择插件管理页面的展示方式：卡片网格更直观，原始表格与 Typecho 默认保持一致')
        );
        $form->addInput($pluginCardView);

        // ================================================================
        // ====== 编辑器设置（MD3 折叠卡片） ======
        // ================================================================
        echo '<div id="ab-card-editor" style="margin:0 0 16px;border-radius:20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08),0 2px 12px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.06);overflow:hidden">
            <div id="ab-card-editor-hdr" style="display:flex;align-items:center;gap:12px;padding:18px 22px;cursor:pointer;user-select:none;-webkit-user-select:none;transition:background .15s" onmouseover="this.style.background=\'rgba(0,0,0,.025)\'" onmouseout="this.style.background=\'\'">
                <div id="ab-card-editor-strip" style="width:3px;height:36px;background:' . $abC1 . ';border-radius:2px;flex-shrink:0;transition:background .3s"></div>
                <div id="ab-card-editor-icon" style="width:40px;height:40px;background:' . $abC1 . '1a;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;transition:background .3s">✏️</div>
                <div style="flex:1;min-width:0">
                    <div class="ab-card-title" style="font-size:15px;font-weight:600;color:#1c1b1f;line-height:1.3">编辑器设置</div>
                    <div class="ab-card-subtitle" style="font-size:12px;color:#79747e;margin-top:2px">Vditor Markdown 编辑器，所见即所得 / 实时预览 / 分屏编辑</div>
                </div>
                <svg id="ab-card-editor-chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . $abC1 . '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;transition:transform .35s"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div id="ab-card-editor-body" style="overflow:hidden;max-height:9999px;padding:0 16px;transition:max-height .4s cubic-bezier(.4,0,.2,1)">
                <div class="ab-card-tip" style="margin:4px 6px 8px;padding:12px 15px;background:#f0f9ff;border:1px solid #bfdbfe;border-radius:12px">
                    <div style="display:flex;align-items:flex-start;gap:10px">
                        <span style="font-size:15px;flex-shrink:0;margin-top:1px">✏️</span>
                        <div class="ab-card-tip-text" style="flex:1;font-size:13px;color:#1e40af;line-height:1.7">开启 Vditor 后，文章 / 页面编辑界面将使用 Vditor 替代原版 PageDown 编辑器。原工具栏将被 Vditor 内置工具栏接管，原"撰写/预览"切换将变为 <strong>所见即所得 / 实时预览 / 分屏编辑</strong> 三种模式切换按钮。</div>
                    </div>
                </div>
            </div>
        </div>';

        // 编辑器 - Vditor 开关
        $editorVditor = new Typecho_Widget_Helper_Form_Element_Select(
            'editor_vditor',
            array(
                '0' => '关闭（使用原版 PageDown 编辑器）',
                '1' => '开启（使用 Vditor）',
            ),
            '0',
            _t('Vditor 编辑器'),
            _t('开启后，文章/页面编辑页将使用 Vditor Markdown 编辑器，支持所见即所得、实时预览、分屏编辑三种模式')
        );
        $form->addInput($editorVditor);

        // 编辑器 - Vditor 默认模式
        $editorVditorMode = new Typecho_Widget_Helper_Form_Element_Select(
            'editor_vditorMode',
            array(
                'wysiwyg' => '所见即所得',
                'ir'      => '实时预览（默认）',
                'sv'      => '分屏编辑（宽屏推荐）',
            ),
            'ir',
            _t('Vditor 默认模式'),
            _t('首次打开编辑器时使用的模式，用户可通过编辑器上方的模式切换按钮随时切换')
        );
        $form->addInput($editorVditorMode);

        // ================================================================
        // ====== 登录页设置（MD3 折叠卡片） ======
        // ================================================================
        echo '<div id="ab-card-login" style="margin:0 0 16px;border-radius:20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08),0 2px 12px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.06);overflow:hidden">
            <div id="ab-card-login-hdr" style="display:flex;align-items:center;gap:12px;padding:18px 22px;cursor:pointer;user-select:none;-webkit-user-select:none;transition:background .15s" onmouseover="this.style.background=\'rgba(0,0,0,.025)\'" onmouseout="this.style.background=\'\'">
                <div id="ab-card-login-strip" style="width:3px;height:36px;background:' . $abC2 . ';border-radius:2px;flex-shrink:0;transition:background .3s"></div>
                <div id="ab-card-login-icon" style="width:40px;height:40px;background:' . $abC2 . '1a;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;transition:background .3s">🔐</div>
                <div style="flex:1;min-width:0">
                    <div class="ab-card-title" style="font-size:15px;font-weight:600;color:#1c1b1f;line-height:1.3">登录页设置</div>
                    <div class="ab-card-subtitle" style="font-size:12px;color:#79747e;margin-top:2px">配色方案、背景图片、虚化效果、自定义样式</div>
                </div>
                <svg id="ab-card-login-chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . $abC2 . '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;transition:transform .35s"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div id="ab-card-login-body" style="overflow:hidden;max-height:9999px;padding:0 16px;transition:max-height .4s cubic-bezier(.4,0,.2,1)">
                <div class="ab-card-tip" style="margin:4px 6px 8px;padding:12px 15px;background:#f0f9ff;border:1px solid #bfdbfe;border-radius:12px">
                    <div style="display:flex;align-items:flex-start;gap:10px">
                        <span style="font-size:15px;flex-shrink:0;margin-top:1px">💡</span>
                        <div class="ab-card-tip-text" style="flex:1;font-size:13px;color:#1e40af;line-height:1.7">以下设置控制登录页面的样式，支持自定义配色、背景图片、虚化效果等。</div>
                    </div>
                </div>
            </div>
        </div>';

        // 登录页 - 颜色预设方案
        $loginColorPreset = new Typecho_Widget_Helper_Form_Element_Select(
            'login_colorPreset',
            array(
                'custom'   => _t('自定义'),
                'purple'   => _t('🟣 紫 (默认)'),
                'blue'     => _t('🔵 蓝'),
                'pink'     => _t('🌸 粉'),
                'green'    => _t('🌿 绿'),
                'orange'   => _t('🍊 橙'),
                'red'      => _t('❤️ 红'),
                'teal'     => _t('🌊 青'),
                'indigo'   => _t('💙 靛蓝'),
                'sunset'   => _t('🌅 日落渐变'),
                'ocean'    => _t('🌊 海洋渐变'),
                'forest'   => _t('🌲 森林渐变'),
                'lavender' => _t('💜 薰衣草'),
            ),
            'purple',
            _t('登录页配色方案'),
            _t('选择预设配色或使用自定义颜色')
        );
        $form->addInput($loginColorPreset);

        // 登录页 - 主题主色
        $loginPrimaryColor = new Typecho_Widget_Helper_Form_Element_Text(
            'login_primaryColor',
            null,
            '#7d5260',
            _t('登录页主色（自定义）'),
            _t('选择"自定义"方案后生效。如：#625fa0')
        );
        $form->addInput($loginPrimaryColor);

        $loginPrimaryColor2 = new Typecho_Widget_Helper_Form_Element_Text(
            'login_primaryColor2',
            null,
            '#9e7b8a',
            _t('登录页辅色（自定义）'),
            _t('选择"自定义"方案后生效。如：#7a6ec0')
        );
        $form->addInput($loginPrimaryColor2);

        // 登录页 - 站点名称显示
        $loginShowSiteName = new Typecho_Widget_Helper_Form_Element_Radio(
            'login_showSiteName',
            array('1' => _t('显示'), '0' => _t('隐藏')),
            '1',
            _t('登录页显示站点名称')
        );
        $form->addInput($loginShowSiteName);

        // 登录页 - 主题模式
        $loginThemeMode = new Typecho_Widget_Helper_Form_Element_Radio(
            'login_themeMode',
            array('auto' => _t('跟随系统'), 'light' => _t('亮色'), 'dark' => _t('暗色')),
            'auto',
            _t('登录页默认主题')
        );
        $form->addInput($loginThemeMode);

        // 登录页 - 主题切换按钮
        $loginShowThemeToggle = new Typecho_Widget_Helper_Form_Element_Radio(
            'login_showThemeToggle',
            array('1' => _t('显示'), '0' => _t('隐藏')),
            '1',
            _t('登录页显示主题切换按钮')
        );
        $form->addInput($loginShowThemeToggle);

        // 登录页 - 背景图片
        $loginBgImage = new Typecho_Widget_Helper_Form_Element_Text(
            'login_bgImage',
            null,
            '',
            _t('登录页背景图片 URL'),
            _t('留空则使用纯色背景。')
        );
        $form->addInput($loginBgImage);

        // 登录页 - 虚化方式
        $loginBlurType = new Typecho_Widget_Helper_Form_Element_Radio(
            'login_blurType',
            array(
                'none'   => _t('不虚化'),
                'filter' => _t('背景图模糊（filter: blur）'),
            ),
            'filter',
            _t('登录页虚化方式')
        );
        $form->addInput($loginBlurType);

        // 登录页 - 虚化大小
        $loginBlurSize = new Typecho_Widget_Helper_Form_Element_Text(
            'login_blurSize',
            null,
            '12',
            _t('登录页虚化大小(px)'),
            _t('建议 0-50。')
        );
        $form->addInput($loginBlurSize);

        // 登录页 - 自定义 CSS
        $loginCustomCss = new Typecho_Widget_Helper_Form_Element_Textarea(
            'login_customCss',
            null,
            '',
            _t('登录页自定义 CSS'),
            _t('将注入到登录页。无需 style 标签。如果不生效请加 !important')
        );
        $form->addInput($loginCustomCss);

        // 登录页 - 自定义 JS
        $loginCustomJs = new Typecho_Widget_Helper_Form_Element_Textarea(
            'login_customJs',
            null,
            '',
            _t('登录页自定义 JavaScript'),
            _t('将注入到登录页。无需 script 标签。')
        );
        $form->addInput($loginCustomJs);

        // ====== 登录页设置预览 ======
        self::renderLoginPreview();

        // ================================================================
        // ====== PWA 应用设置（MD3 折叠卡片） ======
        // ================================================================
        echo '<div id="ab-card-pwa" style="margin:0 0 16px;border-radius:20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08),0 2px 12px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.06);overflow:hidden">
            <div id="ab-card-pwa-hdr" style="display:flex;align-items:center;gap:12px;padding:18px 22px;cursor:pointer;user-select:none;-webkit-user-select:none;transition:background .15s" onmouseover="this.style.background=\'rgba(0,0,0,.025)\'" onmouseout="this.style.background=\'\'">
                <div id="ab-card-pwa-strip" style="width:3px;height:36px;background:' . $abC1 . ';border-radius:2px;flex-shrink:0;transition:background .3s"></div>
                <div id="ab-card-pwa-icon" style="width:40px;height:40px;background:' . $abC1 . '1a;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;transition:background .3s">📱</div>
                <div style="flex:1;min-width:0">
                    <div class="ab-card-title" style="font-size:15px;font-weight:600;color:#1c1b1f;line-height:1.3">PWA 应用设置</div>
                    <div class="ab-card-subtitle" style="font-size:12px;color:#79747e;margin-top:2px">将管理后台安装为渐进式 Web 应用，自定义名称和图标</div>
                </div>
                <svg id="ab-card-pwa-chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . $abC1 . '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;transition:transform .35s"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div id="ab-card-pwa-body" style="overflow:hidden;max-height:9999px;padding:0 16px;transition:max-height .4s cubic-bezier(.4,0,.2,1)"></div>
        </div>';

        // PWA 应用名称
        $pwaAppName = new Typecho_Widget_Helper_Form_Element_Text(
            'pwa_appName',
            null,
            '',
            _t('PWA 应用名称'),
            _t('安装为 PWA 后显示的应用名称。留空则默认为「博客名称 + 管理后台」')
        );
        $form->addInput($pwaAppName);

        // PWA 应用图标
        $pwaAppIcon = new Typecho_Widget_Helper_Form_Element_Text(
            'pwa_appIcon',
            null,
            'https://i.see.you/2026/03/08/Uei3/26ee132f48bd9453e9c4d1d3fa1d312d.jpg',
            _t('PWA 应用图标 URL'),
            _t('安装为 PWA 后显示的应用图标，建议使用 512×512 的正方形图片。')
        );
        $form->addInput($pwaAppIcon);

        // ================================================================
        // ====== 速度优化设置（MD3 折叠卡片） ======
        // ================================================================
        echo '<div id="ab-card-perf" style="margin:0 0 16px;border-radius:20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08),0 2px 12px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.06);overflow:hidden">
            <div id="ab-card-perf-hdr" style="display:flex;align-items:center;gap:12px;padding:18px 22px;cursor:pointer;user-select:none;-webkit-user-select:none;transition:background .15s" onmouseover="this.style.background=\'rgba(0,0,0,.025)\'" onmouseout="this.style.background=\'\'">
                <div id="ab-card-perf-strip" style="width:3px;height:36px;background:' . $abC1 . ';border-radius:2px;flex-shrink:0;transition:background .3s"></div>
                <div id="ab-card-perf-icon" style="width:40px;height:40px;background:' . $abC1 . '1a;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;transition:background .3s">⚡</div>
                <div style="flex:1;min-width:0">
                    <div class="ab-card-title" style="font-size:15px;font-weight:600;color:#1c1b1f;line-height:1.3">速度优化</div>
                    <div class="ab-card-subtitle" style="font-size:12px;color:#79747e;margin-top:2px">字体与图标静态资源来源，支持 Google CDN、国内镜像或自定义</div>
                </div>
                <svg id="ab-card-perf-chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . $abC1 . '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;transition:transform .35s"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div id="ab-card-perf-body" style="overflow:hidden;max-height:9999px;padding:0 16px;transition:max-height .4s cubic-bezier(.4,0,.2,1)">
            </div>
        </div>';

        // 静态资源来源
        $staticResource = new Typecho_Widget_Helper_Form_Element_Select(
            'staticResource',
            array(
                'google'    => _t('🌐 Google CDN（fonts.googleapis.com）'),
                'loli'      => _t('🇨🇳 loli.net 镜像（fonts.loli.net，国内推荐）'),
                'local'     => _t('💾 本地文件（需自行托管字体+图标，零外部依赖）'),
                'custom'    => _t('🔧 自定义 URL')
            ),
            'loli',
            _t('字体 & 图标资源来源'),
            _t('选择 Noto Sans SC 字体与 Material Icons 图标的加载方式')
        );
        $form->addInput($staticResource);

        // 自定义字体 CSS URL（选"自定义"时生效）
        $customFontUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'customFontUrl',
            null,
            '',
            _t('自定义字体 CSS URL'),
            _t('选「自定义 URL」后生效。填入 Noto Sans SC 的 CSS 链接，如本机路径或其他 CDN。')
        );
        $form->addInput($customFontUrl);

        // 自定义图标 CSS URL（选"自定义"时生效）
        $customIconUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'customIconUrl',
            null,
            '',
            _t('自定义图标 CSS URL'),
            _t('选「自定义 URL」后生效。填入 Material Icons Round 的 CSS 链接。注：一定是Material Icons Round，内含 .material-icons-round 类样式。')
        );
        $form->addInput($customIconUrl);

        // 本地字体 CSS URL（选"本地文件"时生效，默认指向插件内 assets/fonts/）
        $localFontUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'localFontUrl',
            null,
            '',
            _t('本地字体 CSS 路径（可选）'),
            _t('选「本地文件」后生效，留空则使用插件内默认路径（assets/fonts/NotoSansSC.css）。需将 NotoSansSC.css 及对应字体文件放入该目录，否则显示会不正常。')
        );
        $form->addInput($localFontUrl);

        // 本地图标 CSS URL（选"本地文件"时生效，默认指向插件内 assets/fonts/）
        $localIconUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'localIconUrl',
            null,
            '',
            _t('本地图标 CSS 路径（可选）'),
            _t('选「本地文件」后生效，留空则使用插件内默认路径（assets/fonts/MaterialIconsRound.css）。需将 MaterialIconsRound.css 及 woff2 字体放入该目录，否则显示会不正常。')
        );
        $form->addInput($localIconUrl);

        // ================================================================
        // ====== 兼容脚本管理（MD3 折叠卡片） ======
        // ================================================================
        echo '<div id="ab-card-compat" style="margin:0 0 16px;border-radius:20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08),0 2px 12px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.06);overflow:hidden">
            <div id="ab-card-compat-hdr" style="display:flex;align-items:center;gap:12px;padding:18px 22px;cursor:pointer;user-select:none;-webkit-user-select:none;transition:background .15s" onmouseover="this.style.background=\'rgba(0,0,0,.025)\'" onmouseout="this.style.background=\'\'">
                <div id="ab-card-compat-strip" style="width:3px;height:36px;background:' . $abC2 . ';border-radius:2px;flex-shrink:0;transition:background .3s"></div>
                <div id="ab-card-compat-icon" style="width:40px;height:40px;background:' . $abC2 . '1a;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;transition:background .3s">🧩</div>
                <div style="flex:1;min-width:0">
                    <div class="ab-card-title" style="font-size:15px;font-weight:600;color:#1c1b1f;line-height:1.3">兼容脚本管理</div>
                    <div class="ab-card-subtitle" style="font-size:12px;color:#79747e;margin-top:2px">手动启用兼容脚本，修复其他插件/旧版 Typecho 的页面排版</div>
                </div>
                <svg id="ab-card-compat-chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . $abC2 . '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;transition:transform .35s"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div id="ab-card-compat-body" style="overflow:hidden;max-height:9999px;padding:0 16px;transition:max-height .4s cubic-bezier(.4,0,.2,1)">
                <div class="ab-card-tip-green" style="margin:4px 6px 8px;padding:12px 15px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px">
                    <div style="display:flex;align-items:flex-start;gap:10px">
                        <span style="font-size:15px;flex-shrink:0;margin-top:1px">📦</span>
                        <div class="ab-card-tip-green-text" style="flex:1;font-size:13px;color:#166534;line-height:1.7">
                            兼容脚本默认不加载，请根据需要手动开启。脚本位于 <code style="background:#dcfce7;padding:2px 6px;border-radius:4px;font-size:12px">assets/compat/</code> 目录。<br>
                            开发者可参考 <code style="background:#dcfce7;padding:2px 6px;border-radius:4px;font-size:12px">assets/compat/README.md</code> 编写兼容脚本（需包含 <code style="background:#dcfce7;padding:2px 6px;border-radius:4px;font-size:12px">@name</code> / <code style="background:#dcfce7;padding:2px 6px;border-radius:4px;font-size:12px">@plugins</code> / <code style="background:#dcfce7;padding:2px 6px;border-radius:4px;font-size:12px">@description</code> 元数据）。<br/>
                            文档：<a href="https://blog.lhl.one/artical/977.html" target="_blank">https://blog.lhl.one/artical/977.html</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

        // --- 扫描本地兼容脚本并展示列表 ---
        $compatDir = dirname(__FILE__) . '/assets/compat/';
        $compatScripts = self::scanCompatScripts($compatDir);
        // 读取当前已启用脚本列表（默认全部关闭，需手动启用）
        // 注：字段名沿用 compat_disabledScripts 以保持数据库 schema 兼容，但语义已改为存储"已启用"列表
        $enabledRaw = '';
        try {
            $abOptCompat = Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautify');
            $enabledRaw = isset($abOptCompat->compat_disabledScripts) ? (string) $abOptCompat->compat_disabledScripts : '';
        } catch (Exception $e) {
            $enabledRaw = '';
        }
        $enabledList = ($enabledRaw !== '') ? (array) json_decode($enabledRaw, true) : array();
        if (!is_array($enabledList)) $enabledList = array();

        self::renderCompatScriptsList($compatScripts, $enabledList, $abC1);

        // 隐藏的表单字段：存储已启用脚本 JSON（字段名保持原名以兼容已激活的插件配置）
        $compatEnabledScripts = new Typecho_Widget_Helper_Form_Element_Hidden(
            'compat_disabledScripts',
            null,
            $enabledRaw
        );
        $form->addInput($compatEnabledScripts);

        // 兼容性 - 外部 JS 文件链接
        $compatExternalJs = new Typecho_Widget_Helper_Form_Element_Textarea(
            'compat_externalJs',
            null,
            '',
            _t('外部兼容脚本 URL'),
            _t('每行一个 JS 文件 URL，将在后台所有页面加载。示例：https://cdn.example.com/compat/my-plugin-fix.js')
        );
        $form->addInput($compatExternalJs);

        // 匿名使用统计开关
        $telemetryOptOut = new Typecho_Widget_Helper_Form_Element_Radio(
            'telemetryOptOut',
            array(
                '0' => _t('允许'),
                '1' => _t('关闭统计'),
            ),
            '0',
            _t('匿名使用统计'),
            _t('启用后，插件会通过浏览器向开发者统计服务发送，仅包含：插件版本号、站点表示。选择"关闭统计"后立即停止上报。')
        );
        $form->addInput($telemetryOptOut);

        // 通知开关
        $notifyOptOut = new Typecho_Widget_Helper_Form_Element_Radio(
            'notifyOptOut',
            array(
                '0' => _t('显示通知（默认）'),
                '1' => _t('关闭通知'),
            ),
            '0',
            _t('插件通知'),
            _t('关闭后，将不再显示插件更新横幅通知和公告弹窗。')
        );
        $form->addInput($notifyOptOut);

        // ====== MD3 折叠卡片 + 颜色跟随 + 检查更新 ======
        echo '<script>
// ---- 卡片构建：将各表单字段移入 MD3 折叠卡片 ----
(function(){
    // 查找字段对应的外层 <ul class="typecho-option"> 元素
    function findFieldUl(name){
        // Typecho 1.3 格式：ul[id^="typecho-option-item-{name}-"]
        var el=document.querySelector("ul[id^=\'typecho-option-item-"+name+"-\']");
        if(el) return el;
        // fallback：从 input[name] 向上找最近的 <ul>
        var form=document.querySelector("form.protected")||document.querySelector("form");
        if(!form) return null;
        var inp=form.querySelector("[name=\""+name+"\"]");
        if(!inp) return null;
        var c=inp.parentNode;
        while(c&&c!==form){ if(c.tagName==="UL") return c; c=c.parentNode; }
        return null;
    }

    function buildCards(){
        // ---- 管理后台卡片 ----
        var adminFields=["primaryColor","darkMode","borderRadius","enableAnimation","navPosition","pluginCardView"];
        var firstAdminUl=findFieldUl("primaryColor");
        var adminCard=document.getElementById("ab-card-admin");
        var adminBody=document.getElementById("ab-card-admin-body");

        if(adminCard&&adminBody&&firstAdminUl){
            var form=firstAdminUl.parentNode;
            form.insertBefore(adminCard,firstAdminUl);
            for(var i=0;i<adminFields.length;i++){
                var ul=findFieldUl(adminFields[i]);
                if(ul) adminBody.appendChild(ul);
            }
            adminBody.style.paddingBottom="16px";
        }

        // ---- 编辑器设置卡片（插在管理后台卡片之后）----
        var editorFields=["editor_vditor","editor_vditorMode"];
        var editorCard=document.getElementById("ab-card-editor");
        var editorBody=document.getElementById("ab-card-editor-body");
        if(editorCard&&editorBody){
            var firstEditorUl=findFieldUl("editor_vditor");
            if(firstEditorUl){
                var formE=firstEditorUl.parentNode;
                formE.insertBefore(editorCard,firstEditorUl);
            } else if(adminCard){
                if(adminCard.nextSibling) adminCard.parentNode.insertBefore(editorCard,adminCard.nextSibling);
                else adminCard.parentNode.appendChild(editorCard);
            }
            for(var e=0;e<editorFields.length;e++){
                var eu=findFieldUl(editorFields[e]);
                if(eu) editorBody.appendChild(eu);
            }
            editorBody.style.paddingBottom="16px";
        }

        // ---- 登录页卡片 ----
        var loginFields=["login_colorPreset","login_primaryColor","login_primaryColor2",
            "login_showSiteName","login_themeMode","login_showThemeToggle",
            "login_bgImage","login_blurType","login_blurSize","login_customCss","login_customJs"];
        var firstLoginUl=findFieldUl("login_colorPreset");
        var loginCard=document.getElementById("ab-card-login");
        var loginBody=document.getElementById("ab-card-login-body");

        if(loginCard&&loginBody&&firstLoginUl){
            var form2=firstLoginUl.parentNode;
            form2.insertBefore(loginCard,firstLoginUl);
            for(var j=0;j<loginFields.length;j++){
                var lu=findFieldUl(loginFields[j]);
                if(lu) loginBody.appendChild(lu);
            }
            var preview=document.getElementById("lb-preview");
            if(preview) loginBody.appendChild(preview);
            loginBody.style.paddingBottom="16px";
        }

        // ---- 兼容脚本卡片（插在登录页卡片之后）----
        var compatCard=document.getElementById("ab-card-compat");
        var compatBody=document.getElementById("ab-card-compat-body");
        if(compatCard&&compatBody){
            // 重新定位到登录页卡片之后
            if(loginCard){
                var formC=loginCard.parentNode;
                if(loginCard.nextSibling) formC.insertBefore(compatCard,loginCard.nextSibling);
                else formC.appendChild(compatCard);
            }
            // 兼容脚本列表
            var csList=document.getElementById("ab-compat-scripts-list");
            if(csList) compatBody.appendChild(csList);
            // 外部兼容 JS 字段
            var extUl=findFieldUl("compat_externalJs");
            if(extUl) compatBody.appendChild(extUl);
            compatBody.style.paddingBottom="16px";
        }
        // 隐藏 hidden 字段
        var hiddenUl=findFieldUl("compat_disabledScripts");
        if(hiddenUl) hiddenUl.style.display="none";

        // ---- PWA 应用卡片（插在兼容脚本卡片之后）----
        var pwaFields=["pwa_appName","pwa_appIcon"];
        var pwaCard=document.getElementById("ab-card-pwa");
        var pwaBody=document.getElementById("ab-card-pwa-body");
        if(pwaCard&&pwaBody&&compatCard){
            var form3=compatCard.parentNode;
            if(compatCard.nextSibling) form3.insertBefore(pwaCard,compatCard.nextSibling);
            else form3.appendChild(pwaCard);
            for(var p=0;p<pwaFields.length;p++){
                var pu=findFieldUl(pwaFields[p]);
                if(pu) pwaBody.appendChild(pu);
            }
            // ---- 一键安装 PWA 按钮区 ----
            (function(){
                var installBar=document.createElement("div");
                installBar.id="ab-pwa-install-bar";
                installBar.style.cssText="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:8px 0 4px;padding:14px 16px;background:rgba(103,80,164,.06);border-radius:14px;border:1px solid rgba(103,80,164,.12);";
                // 安装按钮
                var installBtn=document.createElement("button");
                installBtn.type="button";
                installBtn.id="ab-pwa-install-btn";
                installBtn.textContent="📲 安装到桌面";
                installBtn.style.cssText="padding:8px 18px;border-radius:20px;border:none;background:#6750a4;color:#fff;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .2s;white-space:nowrap;";
                installBtn.onmouseover=function(){this.style.opacity=".85";};
                installBtn.onmouseout=function(){this.style.opacity="1";};
                // 提示文字
                var tipSpan=document.createElement("span");
                tipSpan.id="ab-pwa-install-tip";
                tipSpan.style.cssText="font-size:12px;color:#79747e;line-height:1.5;";
                // 检测 beforeinstallprompt 支持
                var deferredPrompt=null;
                var supported="onbeforeinstallprompt" in window;
                if(supported){
                    tipSpan.textContent="支持一键安装（Chrome / Edge Chromium）";
                    window.addEventListener("beforeinstallprompt",function(e){
                        e.preventDefault();
                        deferredPrompt=e;
                        installBtn.disabled=false;
                        installBtn.style.opacity="1";
                        tipSpan.textContent="点击按钮即可安装到桌面（Chrome / Edge Chromium）";
                    });
                    window.addEventListener("appinstalled",function(){
                        deferredPrompt=null;
                        installBtn.disabled=true;
                        installBtn.style.opacity=".5";
                        tipSpan.textContent="✅ 已安装到桌面";
                    });
                    installBtn.disabled=true;
                    installBtn.style.opacity=".5";
                    installBtn.onclick=function(){
                        if(!deferredPrompt){
                            tipSpan.textContent="⚠️ 当前页面暂不满足安装条件（需通过 HTTPS 访问，且尚未安装）";
                            return;
                        }
                        deferredPrompt.prompt();
                        deferredPrompt.userChoice.then(function(r){
                            if(r.outcome==="accepted"){
                                tipSpan.textContent="✅ 安装已确认";
                            } else {
                                tipSpan.textContent="已取消安装";
                            }
                            deferredPrompt=null;
                        });
                    };
                } else {
                    // 不支持 beforeinstallprompt（Safari / Firefox 等）
                    installBtn.disabled=true;
                    installBtn.style.opacity=".45";
                    tipSpan.innerHTML="⚠️ 当前浏览器不支持一键安装。<br>仅 <strong>Chrome</strong>、<strong>Edge（Chromium 内核）</strong> 支持此功能；<br>Safari 请在浏览器菜单中选择「添加到主屏幕」。";
                }
                installBar.appendChild(installBtn);
                installBar.appendChild(tipSpan);
                pwaBody.appendChild(installBar);
            })();
            pwaBody.style.paddingBottom="16px";
        }

        // ---- 性能优化卡片（插在 PWA 卡片之后） ----
        var perfFields=["staticResource","customFontUrl","customIconUrl","localFontUrl","localIconUrl"];
        var perfCard=document.getElementById("ab-card-perf");
        var perfBody=document.getElementById("ab-card-perf-body");
        if(perfCard&&perfBody&&pwaCard){
            var form4=pwaCard.parentNode;
            if(pwaCard.nextSibling) form4.insertBefore(perfCard,pwaCard.nextSibling);
            else form4.appendChild(perfCard);
            for(var q=0;q<perfFields.length;q++){
                var qu=findFieldUl(perfFields[q]);
                if(qu) perfBody.appendChild(qu);
            }
            perfBody.style.paddingBottom="16px";
        }
        // 自定义/本地 URL 字段的显示/隐藏
        (function(){
            var sel=document.querySelector("[name=\"staticResource\"]");
            if(!sel) return;
            function toggleCustom(){
                var v=sel.value;
                var isCustom=(v==="custom");
                var isLocal=(v==="local");
                var fontUl=findFieldUl("customFontUrl");
                var iconUl=findFieldUl("customIconUrl");
                var localFontUl=findFieldUl("localFontUrl");
                var localIconUl=findFieldUl("localIconUrl");
                if(fontUl)      fontUl.style.display=isCustom?"":"none";
                if(iconUl)      iconUl.style.display=isCustom?"":"none";
                if(localFontUl) localFontUl.style.display=isLocal?"":"none";
                if(localIconUl) localIconUl.style.display=isLocal?"":"none";
            }
            sel.addEventListener("change",toggleCustom);
            toggleCustom();
        })();

        // ---- 绑定卡片点击 & 恢复/默认折叠状态 ----
        ["admin","editor","login","pwa","perf","compat"].forEach(function(id){
            var hdr=document.getElementById("ab-card-"+id+"-hdr");
            if(hdr) hdr.addEventListener("click",function(){ abToggleCard(id); });
            restoreCard(id);
        });
    }

    window.abToggleCard=function(id){
        var body=document.getElementById("ab-card-"+id+"-body");
        var chev=document.getElementById("ab-card-"+id+"-chev");
        var card=document.getElementById("ab-card-"+id);
        if(!body) return;
        var collapsed=body.getAttribute("data-collapsed")==="1";
        if(collapsed){
            // 展开：先恢复 paddingBottom，再展开高度
            body.style.paddingBottom="16px";
            body.style.maxHeight=body.scrollHeight+"px";
            setTimeout(function(){ body.style.maxHeight="9999px"; },420);
            body.setAttribute("data-collapsed","0");
            if(chev) chev.style.transform="";
            if(card) card.style.boxShadow="0 1px 3px rgba(0,0,0,.08),0 2px 12px rgba(0,0,0,.04)";
            try{ localStorage.setItem("ab-card-"+id,"1"); }catch(e){}
        } else {
            // 折叠：先锁定当前高度，再清除 paddingBottom，然后折叠到 0
            body.style.maxHeight=body.scrollHeight+"px";
            requestAnimationFrame(function(){ requestAnimationFrame(function(){
                body.style.paddingBottom="0";
                body.style.maxHeight="0";
            }); });
            body.setAttribute("data-collapsed","1");
            if(chev) chev.style.transform="rotate(-90deg)";
            if(card) card.style.boxShadow="0 1px 2px rgba(0,0,0,.04)";
            try{ localStorage.setItem("ab-card-"+id,"0"); }catch(e){}
        }
    };

    // 默认折叠；仅当 localStorage 明确为 "1" 时才展开
    function restoreCard(id){
        var saved=null;
        try{ saved=localStorage.getItem("ab-card-"+id); }catch(e){}
        var body=document.getElementById("ab-card-"+id+"-body");
        var chev=document.getElementById("ab-card-"+id+"-chev");
        var card=document.getElementById("ab-card-"+id);
        if(!body) return;
        if(saved==="1"){
            // 用户曾手动展开，保持展开
            body.setAttribute("data-collapsed","0");
        } else {
            // 默认折叠（首次访问或曾手动折叠）
            body.style.transition="none";
            body.style.paddingBottom="0";
            body.style.maxHeight="0";
            body.setAttribute("data-collapsed","1");
            if(chev) chev.style.transform="rotate(-90deg)";
            if(card) card.style.boxShadow="0 1px 2px rgba(0,0,0,.04)";
            setTimeout(function(){ body.style.transition="max-height .4s cubic-bezier(.4,0,.2,1)"; },50);
        }
    }

    if(document.readyState==="loading"){
        document.addEventListener("DOMContentLoaded",buildCards);
    } else {
        buildCards();
    }
})();

// ---- 卡片颜色跟随主题色 & 检查更新 ----
(function(){
    var abColorMap={
        purple:["#7D5260","#9E7B8A"],
        blue:  ["#556270","#7A8A9E"],
        teal:  ["#4A6363","#6A8A8A"],
        green: ["#55624C","#7A8A6E"],
        orange:["#725A42","#9E8062"],
        pink:  ["#74565F","#9E7A85"],
        red:   ["#775654","#A27A78"]
    };
    function applyConfigColors(scheme){
        var c=abColorMap[scheme]||abColorMap.purple;
        // Banner
        var banner=document.getElementById("ab-header-banner");
        if(banner) banner.style.background="linear-gradient(135deg,"+c[0]+","+c[1]+")";
        // 管理后台卡片
        var s1=document.getElementById("ab-card-admin-strip");
        if(s1) s1.style.background=c[0];
        var i1=document.getElementById("ab-card-admin-icon");
        if(i1) i1.style.background=c[0]+"1a";
        var v1=document.getElementById("ab-card-admin-chev");
        if(v1) v1.setAttribute("stroke",c[0]);
        // 编辑器设置卡片
        var se=document.getElementById("ab-card-editor-strip");
        if(se) se.style.background=c[0];
        var ie=document.getElementById("ab-card-editor-icon");
        if(ie) ie.style.background=c[0]+"1a";
        var ve=document.getElementById("ab-card-editor-chev");
        if(ve) ve.setAttribute("stroke",c[0]);
        // PWA 卡片
        var s3=document.getElementById("ab-card-pwa-strip");
        if(s3) s3.style.background=c[0];
        var i3=document.getElementById("ab-card-pwa-icon");
        if(i3) i3.style.background=c[0]+"1a";
        var v3=document.getElementById("ab-card-pwa-chev");
        if(v3) v3.setAttribute("stroke",c[0]);
        // 兼容脚本卡片
        var s4=document.getElementById("ab-card-compat-strip");
        if(s4) s4.style.background=c[1];
        var i4=document.getElementById("ab-card-compat-icon");
        if(i4) i4.style.background=c[1]+"1a";
        var v4=document.getElementById("ab-card-compat-chev");
        if(v4) v4.setAttribute("stroke",c[1]);
        // 登录页卡片
        var s2=document.getElementById("ab-card-login-strip");
        if(s2) s2.style.background=c[1];
        var i2=document.getElementById("ab-card-login-icon");
        if(i2) i2.style.background=c[1]+"1a";
        var v2=document.getElementById("ab-card-login-chev");
        if(v2) v2.setAttribute("stroke",c[1]);
        // 性能优化卡片
        var s5=document.getElementById("ab-card-perf-strip");
        if(s5) s5.style.background=c[0];
        var i5=document.getElementById("ab-card-perf-icon");
        if(i5) i5.style.background=c[0]+"1a";
        var v5=document.getElementById("ab-card-perf-chev");
        if(v5) v5.setAttribute("stroke",c[0]);
        // 关于插件卡片
        var s6=document.getElementById("ab-card-about-strip");
        if(s6) s6.style.background=c[0];
        var v6=document.getElementById("ab-card-about-chev");
        if(v6) v6.setAttribute("stroke",c[0]);
    }
    function initColorFollow(){
        var sel=document.querySelector("[name=\"primaryColor\"]");
        if(!sel) return;
        sel.addEventListener("change",function(){ applyConfigColors(this.value); });
    }

    // 检查更新（调用全局 abCheckUpdate，定义于 renderFooter 注入的脚本）
    function initUpdateCheck(){
        var btn=document.getElementById("ab-btn-update");
        if(!btn) return;
        btn.addEventListener("click",function(){ window.abCheckUpdate&&window.abCheckUpdate(true); });
    }

    // 注入动画
    if(!document.getElementById("ab-config-anim")){
        var st=document.createElement("style");
        st.id="ab-config-anim";
        st.textContent="@keyframes ab-spin{to{transform:rotate(360deg)}} @keyframes ab-fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}";
        document.head.appendChild(st);
    }

    if(document.readyState==="loading"){
        document.addEventListener("DOMContentLoaded",function(){ initColorFollow(); initUpdateCheck(); });
    } else {
        initColorFollow(); initUpdateCheck();
    }
})();
</script>';

        // ================================================================
        // ====== 关于插件（MD3 折叠卡片） ======
        // ================================================================
        echo '<style>
[data-theme="dark"] #ab-card-about {
    background: var(--md-surface-container-low, #1d1b20) !important;
    border-color: var(--md-outline-variant, rgba(255,255,255,.12)) !important;
}
[data-theme="dark"] #ab-card-about-hdr:hover { background: rgba(255,255,255,.05) !important; }
[data-theme="dark"] .ab-about-section-title { color: var(--md-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-about-author-name { color: var(--md-on-surface, #e6e1e5) !important; }
[data-theme="dark"] .ab-about-author-bio { color: var(--md-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-about-link-btn { background: rgba(255,255,255,.08) !important; color: var(--md-on-surface, #e6e1e5) !important; border-color: rgba(255,255,255,.12) !important; }
[data-theme="dark"] .ab-about-link-btn:hover { background: rgba(255,255,255,.14) !important; }
[data-theme="dark"] .ab-about-plugin-card { background: var(--md-surface-container, #211f26) !important; border-color: var(--md-outline-variant, rgba(255,255,255,.12)) !important; }
[data-theme="dark"] .ab-about-plugin-name { color: var(--md-on-surface, #e6e1e5) !important; }
[data-theme="dark"] .ab-about-plugin-desc { color: var(--md-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-about-changelog-item { border-color: rgba(255,255,255,.08) !important; }
[data-theme="dark"] .ab-about-tag-version { background: rgba(255,255,255,.1) !important; color: var(--md-on-surface, #e6e1e5) !important; }
[data-theme="dark"] .ab-about-changelog-body { color: var(--md-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-about-support-tip { background: rgba(255,255,255,.04) !important; border-color: rgba(255,255,255,.1) !important; }
[data-theme="dark"] .ab-about-support-title { color: var(--md-on-surface, #e6e1e5) !important; }
[data-theme="dark"] .ab-about-support-desc { color: var(--md-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-about-support-qr-label { color: var(--md-on-surface-variant, #cac4d0) !important; }
</style>';

        echo '<div id="ab-card-about" style="margin:0 0 16px;border-radius:20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08),0 2px 12px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.06);overflow:hidden">
            <div id="ab-card-about-hdr" style="display:flex;align-items:center;gap:12px;padding:18px 22px;cursor:pointer;user-select:none;-webkit-user-select:none;transition:background .15s" onmouseover="this.style.background=\'rgba(0,0,0,.025)\'" onmouseout="this.style.background=\'\'">
                <div id="ab-card-about-strip" style="width:3px;height:36px;background:' . $abC1 . ';border-radius:2px;flex-shrink:0;transition:background .3s"></div>
                <div style="width:40px;height:40px;background:' . $abC1 . '1a;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">💡</div>
                <div style="flex:1;min-width:0">
                    <div class="ab-card-title" style="font-size:15px;font-weight:600;color:#1c1b1f;line-height:1.3">关于插件</div>
                    <div class="ab-card-subtitle" style="font-size:12px;color:#79747e;margin-top:2px">作者信息 · 更新日志 · 支持作者</div>
                </div>
                <svg id="ab-card-about-chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="' . $abC1 . '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;transition:transform .35s"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div id="ab-card-about-body" style="overflow:hidden;max-height:9999px;padding:0 22px 24px;transition:max-height .4s cubic-bezier(.4,0,.2,1)">

                <!-- ── 作者信息 ── -->
                <div style="margin:16px 0 0;padding:18px;background:linear-gradient(135deg,' . $abC1 . '18,' . $abC2 . '10);border-radius:16px;border:1px solid ' . $abC1 . '22">
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px">
                        <img src="https://i.see.you/2026/03/08/Uei3/26ee132f48bd9453e9c4d1d3fa1d312d.jpg" alt="lhl77" style="width:52px;height:52px;border-radius:50%;border:2px solid ' . $abC1 . '44;flex-shrink:0">
                        <div>
                            <div class="ab-about-author-name" style="font-size:17px;font-weight:700;color:#1c1b1f;letter-spacing:-.01em">LHL (lhl77)</div>
                            <div class="ab-about-author-bio" style="font-size:12px;color:#79747e;margin-top:3px">插件作者</div>
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <a href="https://github.com/lhl77" target="_blank" rel="noopener" class="ab-about-link-btn" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#fff;border:1px solid rgba(0,0,0,.1);border-radius:20px;font-size:12px;font-weight:500;color:#333;text-decoration:none;transition:background .15s" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
                            GitHub
                        </a>
                        <a href="https://blog.lhl.one" target="_blank" rel="noopener" class="ab-about-link-btn" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#fff;border:1px solid rgba(0,0,0,.1);border-radius:20px;font-size:12px;font-weight:500;color:#333;text-decoration:none;transition:background .15s" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                            个人博客
                        </a>
                        <a href="https://t.me/+S_rnDEUlSPPRzvW_" target="_blank" rel="noopener" class="ab-about-link-btn" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#fff;border:1px solid rgba(0,0,0,.1);border-radius:20px;font-size:12px;font-weight:500;color:#333;text-decoration:none;transition:background .15s" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.17 13.667l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.978.892z"/></svg>
                            Telegram 群
                        </a>
                        <a href="https://qm.qq.com/q/OOzG20idi2" target="_blank" rel="noopener" class="ab-about-link-btn" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#fff;border:1px solid rgba(0,0,0,.1);border-radius:20px;font-size:12px;font-weight:500;color:#333;text-decoration:none;transition:background .15s" onmouseover="this.style.background=\'#f5f5f5\'" onmouseout="this.style.background=\'#fff\'">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.477 2 12c0 2.717.99 5.198 2.618 7.107C4.232 20.525 3 22 3 22s1.81-.474 3.49-1.388A9.947 9.947 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm1.5 14.5h-3v-5h3v5zm0-7h-3V7.5h3V9.5z"/></svg>
                            QQ 群
                        </a>
                    </div>
                </div>

                <!-- ── 作者的其他插件 ── -->
                <div style="margin-top:20px">
                    <div class="ab-about-section-title" style="font-size:12px;font-weight:600;color:#79747e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">🧩 作者的其他插件</div>
                    <div id="ab-about-more-plugins" style="margin-top:8px"><!-- GitHub API 动态加载 --></div>
                </div>

                <!-- ── 作者的其他项目 ── -->
                <div style="margin-top:20px">
                    <div class="ab-about-section-title" style="font-size:12px;font-weight:600;color:#79747e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">🚀 作者推荐(友情链接)</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px">
                        <a href="https://img.lhl.one" target="_blank" rel="noopener" class="ab-about-plugin-card" style="display:block;padding:12px 14px;background:#f8f8f8;border:1px solid rgba(0,0,0,.07);border-radius:14px;text-decoration:none;transition:box-shadow .15s" onmouseover="this.style.boxShadow=\'0 2px 12px rgba(0,0,0,.1)\'" onmouseout="this.style.boxShadow=\'none\'">
                            <div class="ab-about-plugin-name" style="font-size:13px;font-weight:600;color:#1c1b1f;margin-bottom:4px">🖼️ LHL\'s Images 聚合图床</div>
                            <div class="ab-about-plugin-desc" style="font-size:11px;color:#79747e;line-height:1.5">个人博客可申请免费使用•Telegram Bot上传•中国优化储存•S.EE•R2•OSS•Edge One</div>
                        </a>
                        <a href="https://shop.lhl.one" target="_blank" rel="noopener" class="ab-about-plugin-card" style="display:block;padding:12px 14px;background:#f8f8f8;border:1px solid rgba(0,0,0,.07);border-radius:14px;text-decoration:none;transition:box-shadow .15s" onmouseover="this.style.boxShadow=\'0 2px 12px rgba(0,0,0,.1)\'" onmouseout="this.style.boxShadow=\'none\'">
                            <div class="ab-about-plugin-name" style="font-size:13px;font-weight:600;color:#1c1b1f;margin-bottom:4px">🛒 LHL\'s Shop 小店</div>
                            <div class="ab-about-plugin-desc" style="font-size:11px;color:#79747e;line-height:1.5">售卖作者的一些付费服务、虚拟主机、源码等</div>
                        </a>
                    </div>
                </div>

                <!-- ── 支持作者 ── -->
                <div style="margin-top:20px">
                    <div class="ab-about-section-title" style="font-size:12px;font-weight:600;color:#79747e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">❤️ 支持作者</div>
                    <div class="ab-about-support-tip" style="display:flex;align-items:flex-start;gap:16px;padding:16px;background:#fdf6ff;border:1px solid ' . $abC1 . '22;border-radius:16px">
                        <div style="flex:1;min-width:0">
                            <div class="ab-about-support-title" style="font-size:14px;font-weight:600;color:#1c1b1f;margin-bottom:6px">如果插件对你有帮助，欢迎请作者喝杯咖啡 ☕</div>
                            <div class="ab-about-support-desc" style="font-size:12px;color:#79747e;line-height:1.6;margin-bottom:12px">你的支持是作者持续维护和更新插件的动力。感谢每一位使用者！<br><span style="display:block;margin-top:8px;font-size:12px;color:#59555a">备注支持时请在备注中填写：您的名字 + GitHub 或 个人博客，作者会定期把您的名字加入鸣谢列表。</span></div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                <a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify" target="_blank" rel="noopener" class="ab-star-btn" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:' . $abC1 . ';color:#fff;border-radius:20px;font-size:12px;font-weight:500;text-decoration:none">
                                    <span class="material-icons-round">star</span> 给个 Star
                                </a>
                                <a class="ab-star-btn" href="https://pay.lhl.one/paypage/?merchant=3b8dnSzIL2EXvvz2x7WwVEsYHZ6%2BokmCo5jAUlP0klNU" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:linear-gradient(90deg,' . $abC1 . ',' . $abC2 . ');color:#fff;border-radius:20px;font-size:12px;font-weight:500;text-decoration:none">
                                    <span class="material-icons-round">volunteer_activism</span> 捐助
                                </a>
                            </div>
                        </div>
                        <div style="flex-shrink:0;text-align:center">
                            <img src="https://i.see.you/2026/03/09/eS6p/4151a74124898d38a4e53fa8c7dcf3be.jpg" alt="赞赏码" style="width:110px;height:110px;border-radius:12px;object-fit:cover;border:1px solid rgba(0,0,0,.08)">
                            <div class="ab-about-support-qr-label" style="font-size:10px;color:#79747e;margin-top:6px">赞赏码</div>
                        </div>
                    </div>
                </div>

                <!-- ── 鸣谢（支持者） ── -->
                <div style="margin-top:18px">
                    <div class="ab-about-section-title" style="font-size:12px;font-weight:600;color:#79747e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">👏 鸣谢</div>
                    <div id="ab-about-thanks" style="border-radius:12px;padding:12px;background:var(--md-surface-container-low);border:1px solid var(--md-outline-variant);">
                        <div style="font-size:13px;color:var(--md-on-surface-variant);margin-bottom:8px">感谢朋友们：</div>
                        <ul style="margin:0;padding-left:18px;color:var(--md-on-surface);font-size:13px">
                            <!-- 列表将由作者维护或由后台脚本追加 -->
                            <li><a href="https://mzrme.com/" target="_blank" style="color:inherit">MZRME</a></li>
                        </ul><br/>
                        <div style="font-size:13px;color:var(--md-on-surface-variant);margin-bottom:8px">你们的支持是我开发的最大动力（将按周期更新）：</div>
                        <ul style="margin:0;padding-left:18px;color:var(--md-on-surface);font-size:13px">
                            <!-- 列表将由作者维护或由后台脚本追加 -->
                            <li>感谢 <a href="https://github.com/Yilimmilk" target="_blank" style="color:inherit">Yilimmilk</a> 的 20元 打赏</li>
                        </ul>
                        <br/><div style="font-size:13px;color:var(--md-on-surface-variant);margin-bottom:8px">（将按周期随版本更新，如有调整后续更新）</div>
                    </div>
                </div>

                <!-- ── 更新日志 ── -->
                <div style="margin-top:20px">
                    <div class="ab-about-section-title" style="font-size:12px;font-weight:600;color:#79747e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">📋 更新日志</div>
                    <div id="ab-about-changelog" style="border-radius:12px;overflow:hidden;border:1px solid rgba(0,0,0,.07)">
                        <div style="padding:20px;text-align:center;color:#79747e;font-size:13px">
                            <div style="animation:ab-spin 1s linear infinite;display:inline-block;width:20px;height:20px;border:2px solid rgba(0,0,0,.1);border-top-color:#79747e;border-radius:50%;margin-bottom:8px"></div>
                            <div>正在从 GitHub 加载更新日志...</div>
                        </div>
                    </div>
                </div>

                <!-- ── 数据与隐私 ── -->
                <div style="margin-top:20px">
                    <div class="ab-about-section-title" style="font-size:12px;font-weight:600;color:#79747e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">🔒 数据与隐私</div>
                    <div id="ab-telemetry-field-container"></div>
                </div>

            </div>
        </div>';

        echo '<script>
(function(){
    // ── 初始化关于卡片折叠 ──
    var aboutHdr=document.getElementById("ab-card-about-hdr");
    if(aboutHdr) aboutHdr.addEventListener("click",function(){ window.abToggleCard&&window.abToggleCard("about"); });

    function restoreAboutCard(){
        var saved=null;
        try{ saved=localStorage.getItem("ab-card-about"); }catch(e){}
        var body=document.getElementById("ab-card-about-body");
        var chev=document.getElementById("ab-card-about-chev");
        var card=document.getElementById("ab-card-about");
        if(!body) return;
        if(saved==="1"){
            body.setAttribute("data-collapsed","0");
        } else {
            body.style.transition="none";
            body.style.paddingBottom="0";
            body.style.maxHeight="0";
            body.setAttribute("data-collapsed","1");
            if(chev) chev.style.transform="rotate(-90deg)";
            if(card) card.style.boxShadow="0 1px 2px rgba(0,0,0,.04)";
            setTimeout(function(){ body.style.transition="max-height .4s cubic-bezier(.4,0,.2,1)"; },50);
        }
    }

    // ── 更新颜色 ──
    function updateAboutColors(c1){
        var s=document.getElementById("ab-card-about-strip");
        if(s) s.style.background=c1;
        var v=document.getElementById("ab-card-about-chev");
        if(v) v.setAttribute("stroke",c1);
    }

    // ── 从 GitHub API 加载更新日志 ──
    function loadChangelog(){
        var el=document.getElementById("ab-about-changelog");
        if(!el) return;
        var xhr=new XMLHttpRequest();
        xhr.open("GET","https://api.github.com/repos/lhl77/Typecho-Plugin-AdminBeautify/releases?per_page=5",true);
        xhr.withCredentials=false;
        xhr.timeout=8000;
        xhr.onload=function(){
            try{
                var releases=JSON.parse(xhr.responseText);
                if(!Array.isArray(releases)||releases.length===0){
                    el.innerHTML="<div style=\"padding:16px;text-align:center;font-size:13px;color:#79747e\">暂无更新日志</div>";
                    return;
                }
                var html="";
                releases.forEach(function(r,i){
                    var date=r.published_at?r.published_at.substring(0,10):"";
                    var body=(r.body||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/\n/g,"<br>").replace(/\*\*(.*?)\*\*/g,"<strong>$1</strong>").replace(/`(.*?)`/g,"<code style=\"background:rgba(0,0,0,.06);padding:1px 5px;border-radius:4px;font-size:11px\">$1</code>").replace(/^- /gm,"• ").replace(/<br>• /g,"<br>&nbsp;&nbsp;• ");
                    var isLatest=(i===0);
                    var itemId="ab-cl-item-"+i;
                    html+="<div class=\"ab-about-changelog-item\" style=\""+(i>0?"border-top:1px solid rgba(0,0,0,.06)":"")+"\">";
                    html+="<div class=\"ab-cl-hdr\" data-idx=\""+i+"\" style=\"display:flex;align-items:center;gap:8px;padding:12px 16px;cursor:"+(isLatest?"default":"pointer")+"\">";
                    html+="<span class=\"ab-about-tag-version\" style=\"display:inline-block;padding:2px 10px;background:rgba(0,0,0,.06);border-radius:10px;font-size:12px;font-weight:600;color:#333\">"+r.tag_name+"</span>";
                    if(r.prerelease) html+="<span style=\"display:inline-block;padding:2px 8px;background:#fef3c7;border-radius:10px;font-size:11px;color:#92400e\">预发布</span>";
                    if(date) html+="<span style=\"font-size:11px;color:#79747e;margin-left:auto\">"+date+"</span>";
                    if(!isLatest) html+="<svg class=\"ab-cl-chev\" width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"#79747e\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\" style=\"flex-shrink:0;transition:transform .25s;transform:rotate(-90deg)\"><polyline points=\"6 9 12 15 18 9\"/></svg>";
                    html+="</div>";
                    html+="<div id=\""+itemId+"\" class=\"ab-about-changelog-body\" style=\"padding:0 16px 12px;font-size:12px;color:#49454f;line-height:1.8;"+(isLatest?"":"display:none")+"\">"+body+"</div>";
                    html+="</div>";
                });
                el.innerHTML=html;
                el.addEventListener("click",function(e){
                    var hdr=e.target.closest?e.target.closest(".ab-cl-hdr"):null;
                    if(!hdr)return;
                    var idx=parseInt(hdr.getAttribute("data-idx"),10);
                    if(idx===0)return;
                    var bd=document.getElementById("ab-cl-item-"+idx);
                    if(!bd)return;
                    var open=bd.style.display!=="none";
                    bd.style.display=open?"none":"block";
                    var chev=hdr.querySelector(".ab-cl-chev");
                    if(chev)chev.style.transform=open?"rotate(-90deg)":"rotate(0)";
                });
                el.innerHTML=html;
            }catch(e){
                el.innerHTML="<div style=\"padding:16px;text-align:center;font-size:13px;color:#79747e\">加载失败，请访问 <a href=\"https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases\" target=\"_blank\" style=\"color:inherit\">GitHub Releases</a> 查看</div>";
            }
        };
        xhr.onerror=xhr.ontimeout=function(){
            el.innerHTML="<div style=\"padding:16px;text-align:center;font-size:13px;color:#79747e\">加载超时，请访问 <a href=\"https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases\" target=\"_blank\" style=\"color:inherit\">GitHub Releases</a> 查看</div>";
        };
        xhr.send();
    }

    // ── 从 GitHub API 加载作者其他 Repo ──
    function loadGithubRepos(){
        var xhr=new XMLHttpRequest();
        xhr.open("GET","https://api.github.com/users/lhl77/repos?sort=updated&per_page=20",true);
        xhr.withCredentials=false;
        xhr.timeout=8000;
        xhr.onload=function(){
            try{
                var repos=JSON.parse(xhr.responseText);
                if(!Array.isArray(repos)) return;
                // 过滤出 Typecho 插件 repo（名称包含 Typecho 或含 typecho topic）
                var plugins=repos.filter(function(r){ return /typecho/i.test(r.name)&&r.name!=="Typecho-Plugin-AdminBeautify"&&r.name!=="Typecho-Raw-Nontification"; });
                var otherRepos=repos.filter(function(r){ return !/typecho/i.test(r.name)&&!r.fork&&r.description; }).slice(0,4);
                // 渲染其他插件
                var pEl=document.getElementById("ab-about-more-plugins");
                if(pEl&&plugins.length>0){
                    var ph="<div style=\"display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px\">";
                    plugins.slice(0,4).forEach(function(r){
                        ph+="<a href=\""+r.html_url+"\" target=\"_blank\" rel=\"noopener\" class=\"ab-about-plugin-card\" style=\"display:block;padding:12px 14px;background:#f8f8f8;border:1px solid rgba(0,0,0,.07);border-radius:14px;text-decoration:none;transition:box-shadow .15s\" onmouseover=\"this.style.boxShadow=\'0 2px 12px rgba(0,0,0,.1)\'\" onmouseout=\"this.style.boxShadow=\'none\'\">";
                        ph+="<div class=\"ab-about-plugin-name\" style=\"font-size:13px;font-weight:600;color:#1c1b1f;margin-bottom:4px\">"+r.name.replace(/^Typecho-Plugin-/,"")+"</div>";
                        ph+="<div class=\"ab-about-plugin-desc\" style=\"font-size:11px;color:#79747e;line-height:1.5\">"+(r.description||"")+"</div>";
                        ph+="</a>";
                    });
                    ph+="</div>";
                    pEl.innerHTML=ph;
                }
            }catch(e){}
        };
        xhr.onerror=function(){};
        xhr.send();
    }

    function initAbout(){
        restoreAboutCard();
        // 颜色跟随主题色
        var sel=document.querySelector("[name=\"primaryColor\"]");
        if(sel){
            sel.addEventListener("change",function(){
                var abColorMap={purple:"#7D5260",blue:"#556270",teal:"#4A6363",green:"#55624C",orange:"#725A42",pink:"#74565F",red:"#775654"};
                updateAboutColors(abColorMap[this.value]||abColorMap.purple);
            });
        }
        // 插入卡片到 perf 卡片之后（顺序：admin→login→compat→pwa→perf→about）
        var perfCard=document.getElementById("ab-card-perf");
        var aboutCard=document.getElementById("ab-card-about");
        if(perfCard&&aboutCard){
            if(perfCard.nextSibling) perfCard.parentNode.insertBefore(aboutCard,perfCard.nextSibling);
            else perfCard.parentNode.appendChild(aboutCard);
        } else if(aboutCard){
            // fallback：找到任何一个已定位的卡片，追加在末尾
            var anyCard=document.getElementById("ab-card-compat")||document.getElementById("ab-card-login")||document.getElementById("ab-card-admin");
            if(anyCard&&anyCard.parentNode){
                anyCard.parentNode.appendChild(aboutCard);
            }
        }
        // 延迟加载异步内容（卡片展开后才有意义）
        setTimeout(function(){ loadChangelog(); loadGithubRepos(); }, 800);
        // 将「匿名使用统计」表单字段注入「数据与隐私」区块
        var telContainer=document.getElementById("ab-telemetry-field-container");
        if(telContainer){
            function findFieldUlAbout(name){
                var el=document.querySelector("ul[id^=\'typecho-option-item-"+name+"-\']");
                if(el) return el;
                var form=document.querySelector("form.protected")||document.querySelector("form");
                if(!form) return null;
                var inp=form.querySelector("[name=\""+name+"\"]");
                if(!inp) return null;
                var c=inp.parentNode;
                while(c&&c!==form){ if(c.tagName==="UL") return c; c=c.parentNode; }
                return null;
            }
            var telUl=findFieldUlAbout("telemetryOptOut");
            if(telUl) telContainer.appendChild(telUl);
            var notifyUl=findFieldUlAbout("notifyOptOut");
            if(notifyUl) telContainer.appendChild(notifyUl);
        }
    }

    if(document.readyState==="loading"){
        document.addEventListener("DOMContentLoaded",initAbout);
    } else {
        initAbout();
    }
})();
</script>';

        // ====== 公告弹窗通知（仅设置页，从 GitHub 拉取 notice.md） ======
        echo '<script>(function(){
function abInitModal(){
    var CFG=window.__AB_CONFIG__||{};
    if((CFG.notifyOptOut||"0")==="1") return;
    var noticeUrl="https://raw.githubusercontent.com/lhl77/Typecho-Raw-Nontification/main/AdminBeautify/notice.md";
    fetch(noticeUrl,{cache:"no-cache"})
        .then(function(r){return r.ok?r.text():null;})
        .then(function(md){
            if(!md) return;
            // 解析 YAML frontmatter
            var fm={id:"",title:""};
            var m=md.match(/^---\r?\n([\s\S]*?)\r?\n---/);
            if(m){
                var raw=m[1];
                var idM=raw.match(/id:\s*(.+)/);
                var ttM=raw.match(/title:\s*(.+)/);
                if(idM) fm.id=idM[1].trim();
                if(ttM) fm.title=ttM[1].trim();
            }
            if(!fm.id) return;
            var lsKey="ab-notice-"+fm.id;
            var dismissed="";
            try{dismissed=localStorage.getItem(lsKey)||"";}catch(e){}
            if(dismissed==="1") return;
            // 提取正文
            var body=md.replace(/^---[\s\S]*?---\r?\n/,"").trim();
            showModal(fm,body,lsKey);
        })
        .catch(function(){});

    function simpleMarkdown(text){
        return text
            .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
            .replace(/\*\*(.+?)\*\*/g,"<strong>$1</strong>")
            .replace(/\*(.+?)\*/g,"<em>$1</em>")
            .replace(/`(.+?)`/g,"<code>$1</code>")
            .replace(/^#{1,3}\s+(.+)$/gm,"<strong>$1</strong>")
            .replace(/^[-*]\s+(.+)$/gm,"• $1")
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g,"<a href=\"$2\" target=\"_blank\" rel=\"noopener\">$1</a>")
            .replace(/\n/g,"<br>");
    }

    function showModal(fm,body,lsKey){
        var overlay=document.createElement("div");
        overlay.id="ab-modal-notify";
        overlay.style.opacity="0";
        var modal=document.createElement("div");
        modal.className="ab-modal-dialog";
        modal.setAttribute("role","dialog");
        modal.setAttribute("aria-modal","true");
        modal.setAttribute("aria-labelledby","ab-modal-title");
        modal.style.transform="translateY(24px) scale(.96)";
        // 标题栏
        var hdr=document.createElement("div");
        hdr.className="ab-modal-hdr";
        var ic=document.createElement("span");ic.textContent="📢";ic.style.fontSize="20px";
        var ttl=document.createElement("h3");
        ttl.id="ab-modal-title";
        ttl.className="ab-modal-title";
        ttl.textContent=fm.title||"插件公告";
        var cls=document.createElement("button");
        cls.className="ab-modal-close";
        cls.setAttribute("aria-label","关闭");
        cls.textContent="×";
        cls.onclick=function(){ closeModal(false); };
        hdr.appendChild(ic);hdr.appendChild(ttl);hdr.appendChild(cls);
        // 正文
        var bd=document.createElement("div");
        bd.className="ab-modal-bd";
        bd.innerHTML=simpleMarkdown(body);
        // 底部按钮
        var ft=document.createElement("div");
        ft.className="ab-modal-ft";
        var btnNever=document.createElement("button");
        btnNever.className="ab-modal-btn-outline";
        btnNever.textContent="不再显示";
        btnNever.onclick=function(){ closeModal(true); };
        var btnOk=document.createElement("button");
        btnOk.className="ab-modal-btn-filled";
        btnOk.textContent="知道了";
        btnOk.onclick=function(){ closeModal(false); };
        ft.appendChild(btnNever);ft.appendChild(btnOk);
        modal.appendChild(hdr);modal.appendChild(bd);modal.appendChild(ft);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        // 点击遮罩关闭
        overlay.addEventListener("click",function(e){ if(e.target===overlay) closeModal(false); });
        // 入场动画
        requestAnimationFrame(function(){
            overlay.style.opacity="1";
            modal.style.transform="translateY(0) scale(1)";
        });
        function closeModal(never){
            if(never){try{localStorage.setItem(lsKey,"1");}catch(e){}}
            overlay.style.opacity="0";
            modal.style.transform="translateY(12px) scale(.97)";
            setTimeout(function(){overlay.parentNode&&overlay.parentNode.removeChild(overlay);},300);
        }
    }
}
if(document.readyState==="loading"){
    document.addEventListener("DOMContentLoaded",abInitModal);
} else {
    abInitModal();
}
})();</script>';
    }
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 输出头部 CSS（自动区分登录页和管理页）
     */
    public static function renderHeader($header)
    {
        // 登录页 → 注入 LoginBeautify 样式
        if (self::isLoginPage()) {
            ob_start();
            self::outputLoginHeaderCss();
            $inject = ob_get_clean();
            return $header . $inject;
        }

        // 管理页 → 注入 AdminBeautify 样式
        return self::renderAdminHeader($header);
    }

    /**
     * 输出管理页头部 CSS
     */
    private static function renderAdminHeader($header)
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginOptions = $options->plugin('AdminBeautify');

        $primaryColor = $pluginOptions->primaryColor ?: 'purple';
        $darkMode = $pluginOptions->darkMode ?: 'auto';
        $borderRadius = $pluginOptions->borderRadius ?: 'medium';
        // 使用 isset + 空字符串判断，确保 '0' 被正确识别为"关闭"
        $rawAnim = isset($pluginOptions->enableAnimation) ? (string)$pluginOptions->enableAnimation : '';
        $enableAnimation = ($rawAnim !== '') ? $rawAnim : '1';
        $navPosition = $pluginOptions->navPosition ?: 'left';

    // 提前获取颜色方案（后续多处复用，避免重复调用）
    $colors = self::getColorScheme($primaryColor);
    $lightBg = isset($colors['--md-surface'])      ? $colors['--md-surface']      : '#FFFBFE';
    $darkBg  = isset($colors['--md-dark-surface']) ? $colors['--md-dark-surface'] : '#1C1B1F';

    $cssUrl = Typecho_Common::url('AdminBeautify/assets/AdminBeautify', $options->pluginUrl);

        // ⚠️ 必须在所有 CSS 之前执行：设置 data-theme / data-nav / data-animation，并立即设置 html 内联背景与 color-scheme，
        // 以尽量保证首次渲染时初始画布颜色与插件主题一致，减少在非 AJAX 跳转中的闪黑/闪白。
        $earlyScript = '<script>';
        if ($darkMode === 'dark') {
            $earlyScript .= 'document.documentElement.setAttribute("data-theme","dark");';
            $earlyScript .= 'document.documentElement.style.background="' . $darkBg . '";';
            $earlyScript .= 'document.documentElement.style.setProperty("color-scheme","dark");';
        } elseif ($darkMode === 'light') {
            $earlyScript .= 'document.documentElement.removeAttribute("data-theme");';
            $earlyScript .= 'document.documentElement.style.background="' . $lightBg . '";';
            $earlyScript .= 'document.documentElement.style.setProperty("color-scheme","light");';
        } elseif ($darkMode === 'auto') {
            // 根据系统偏好即时设置 data-theme 与 html 内联背景
            $earlyScript .= '(function(){var m=window.matchMedia&&window.matchMedia("(prefers-color-scheme:dark)");if(m&&m.matches){document.documentElement.setAttribute("data-theme","dark");document.documentElement.style.background="' . $darkBg . '";document.documentElement.style.setProperty("color-scheme","dark");}else{document.documentElement.removeAttribute("data-theme");document.documentElement.style.background="' . $lightBg . '";document.documentElement.style.setProperty("color-scheme","light");}})();';
        }
        if ($navPosition === 'left') {
            $earlyScript .= 'document.documentElement.setAttribute("data-nav","left");if(localStorage.getItem("adminBeautifySidebarCollapsed")==="1"){document.documentElement.setAttribute("data-nav-collapsed","");}';
        }
        if ($enableAnimation === '0') {
            $earlyScript .= 'document.documentElement.setAttribute("data-no-animation","");';
        }
        // 防止初始渲染时 body/元素过渡动画触发（页面加载完成后再由 JS 移除）
        $earlyScript .= 'document.documentElement.setAttribute("data-ab-loading","");';
        $earlyScript .= '</script>';

        // 提前获取颜色方案（后续多处复用，避免重复调用）
        $colors = self::getColorScheme($primaryColor);
        $lightBg = isset($colors['--md-surface'])      ? $colors['--md-surface']      : '#FFFBFE';
        $darkBg  = isset($colors['--md-dark-surface']) ? $colors['--md-dark-surface'] : '#1C1B1F';

        // ⚠️ 关键：将 earlyScript + 内联 CSS 变量提前到 Typecho 自身 CSS 链接之前
        //   浏览器在遇到 <link rel="stylesheet"> 时会阻塞后续内联 <script> 的执行，
        //   因此 earlyScript 必须出现在 Typecho CSS 链接之前，才能在首次渲染前设置
        //   data-ab-loading（导航隐藏）和 data-theme（主题色变量），彻底消除闪黑。
        //
        //   ├── injectHead（置于 Typecho CSS 之前）：
        //   │     0. color-scheme meta（告知浏览器色彩模式）
        //   │     1. earlyScript（立即执行，无 CSS 阻塞：设置 data-theme / data-ab-loading）
        //   │     2. 内联 <style>（CSS 变量 + 导航隐藏规则，Typecho CSS 之前已生效）
        //   └── injectTail（置于 Typecho CSS 之后）：
        //         3. style.css <link>
        //         4. 字体等外部资源 + DOMContentLoaded + PWA

        // 0. 色彩模式值
        $colorSchemeValue = ($darkMode === 'dark') ? 'dark' : (($darkMode === 'light') ? 'light' : 'light dark');

        // ─── HEAD 注入：必须在 Typecho CSS 之前 ───────────────────────────────────
        // color-scheme meta：告知浏览器该页面支持的色彩模式
        $injectHead = "\n" . '<meta name="color-scheme" content="' . $colorSchemeValue . '">';

        // 1. 早期脚本（在 CSS <link> 之前，不会被 CSS 加载阻塞，立即执行）
        $injectHead .= "\n" . $earlyScript;

        // 2. 内联 CSS 变量 + 防闪规则（在 Typecho CSS 之前即可生效）
        $injectHead .= "\n" . '<style>';
        // 防加载闪烁：页面加载期间禁用所有过渡，DOMContentLoaded 后移除
        $injectHead .= '[data-ab-loading],[data-ab-loading] *{transition:none!important;}';
        // 隐藏 Typecho 原生深色顶部导航栏（background:#292D33），防止外部 CSS 加载前短暂闪黑
        $injectHead .= '[data-ab-loading] .typecho-head-nav{visibility:hidden!important;}';
        // 同时设置 html 和 body 背景色，并用 !important 阻断 Typecho 自身 CSS（body{background:#F6F6F3}，无 !important）对 body 的覆盖；
        // style.css 内的 body{background:var(--md-surface)!important} 因加载顺序更晚，仍可正确覆盖此处规则，不影响最终样式。
        $injectHead .= 'html,body{background:' . $lightBg . '!important;}';
        $injectHead .= 'html[data-theme="dark"],html[data-theme="dark"] body{background:' . $darkBg . '!important;}';
        $injectHead .= ':root{';
        // color-scheme CSS 属性（配合 meta tag，确保表单控件等 UA 渲染跟随页面主题）
        $injectHead .= 'color-scheme:' . $colorSchemeValue . ';';

        // 主题色 CSS 变量
        foreach ($colors as $key => $value) {
            $injectHead .= $key . ':' . $value . ';';
        }

        // 圆角
        $radiusMap = array(
            'small'  => array('--md-radius-xs' => '4px', '--md-radius-sm' => '6px', '--md-radius-md' => '8px', '--md-radius-lg' => '12px', '--md-radius-xl' => '16px', '--md-radius-full' => '9999px'),
            'medium' => array('--md-radius-xs' => '6px', '--md-radius-sm' => '8px', '--md-radius-md' => '12px', '--md-radius-lg' => '16px', '--md-radius-xl' => '28px', '--md-radius-full' => '9999px'),
            'large'  => array('--md-radius-xs' => '8px', '--md-radius-sm' => '12px', '--md-radius-md' => '16px', '--md-radius-lg' => '24px', '--md-radius-xl' => '32px', '--md-radius-full' => '9999px'),
        );
        if (isset($radiusMap[$borderRadius])) {
            foreach ($radiusMap[$borderRadius] as $key => $value) {
                $injectHead .= $key . ':' . $value . ';';
            }
        }

        // 动画
        if ($enableAnimation === '0') {
            $injectHead .= '--md-transition-duration:0s;';
        } else {
            $injectHead .= '--md-transition-duration:0.2s;';
        }

        $injectHead .= '}</style>';

        // ─── TAIL 注入：置于 Typecho CSS 之后 ────────────────────────────────────
        // 3. style.css（此时 CSS 变量已全部就绪，不会出现 var() fallback 闪烁）
        $injectTail = "\n" . '<link rel="stylesheet" href="' . $cssUrl . '.' .'v2.1.9' . '.css">';

        // Vditor CSS：仅在编写页面且开启时注入
        $editorVditor = isset($pluginOptions->editor_vditor) ? (string)$pluginOptions->editor_vditor : '0';
        $reqUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $isWritePage = (strpos($reqUri, 'write-post.php') !== false || strpos($reqUri, 'write-page.php') !== false);
        if ($editorVditor === '1' && $isWritePage) {
            $injectTail .= "\n" . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vditor/dist/index.css">';
        }

        // 4. 外部字体/图标（根据「静态资源来源」设置加载）
        $staticResource = isset($pluginOptions->staticResource) ? (string) $pluginOptions->staticResource : 'google';
        $customFontUrl  = isset($pluginOptions->customFontUrl)  ? trim((string) $pluginOptions->customFontUrl)  : '';
        $customIconUrl  = isset($pluginOptions->customIconUrl)  ? trim((string) $pluginOptions->customIconUrl)  : '';
        $localFontUrl   = isset($pluginOptions->localFontUrl)   ? trim((string) $pluginOptions->localFontUrl)   : '';
        $localIconUrl   = isset($pluginOptions->localIconUrl)   ? trim((string) $pluginOptions->localIconUrl)   : '';

        if ($staticResource === 'google') {
            $resFontUrl = 'https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap';
            $resIconUrl = 'https://fonts.googleapis.com/icon?family=Material+Icons+Round';
        } elseif ($staticResource === 'loli') {
            // loli.net 镜像（fonts.googleapis.com → fonts.loli.net）
            $resFontUrl = 'https://fonts.loli.net/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap';
            $resIconUrl = 'https://fonts.loli.net/icon?family=Material+Icons+Round';
        } elseif ($staticResource === 'jsdelivr') {
            // jsDelivr CDN：@fontsource/noto-sans-sc + material-icons npm 包
            $resFontUrl = 'https://cdn.jsdelivr.net/npm/@fontsource/noto-sans-sc@5/index.css';
            $resIconUrl = 'https://cdn.jsdelivr.net/npm/material-icons@1/iconfont/round.css';
        } elseif ($staticResource === 'local') {
            // 本地文件（从插件 assets/fonts/ 目录加载，或使用用户自定义路径）
            $localPluginFontDefault = Typecho_Common::url('AdminBeautify/assets/fonts/NotoSansSC.css', $options->pluginUrl);
            $localPluginIconDefault = Typecho_Common::url('AdminBeautify/assets/fonts/MaterialIconsRound.css', $options->pluginUrl);
            $resFontUrl = ($localFontUrl !== '') ? $localFontUrl : $localPluginFontDefault;
            $resIconUrl = ($localIconUrl !== '') ? $localIconUrl : $localPluginIconDefault;
        } elseif ($staticResource === 'custom') {
            $resFontUrl = $customFontUrl;
            $resIconUrl = $customIconUrl;
        } else {
            // 'none' 或其他：不加载外部字体/图标，回退至系统字体
            $resFontUrl = '';
            $resIconUrl = '';
        }

        if ($resFontUrl !== '') {
            $injectTail .= "\n" . '<link rel="stylesheet" href="' . htmlspecialchars($resFontUrl) . '">';
        }
        if ($resIconUrl !== '') {
            $injectTail .= "\n" . '<link rel="stylesheet" href="' . htmlspecialchars($resIconUrl) . '">';
        }

        // DOMContentLoaded 后移除 data-ab-loading，恢复过渡效果
        $injectTail .= '<script>document.addEventListener("DOMContentLoaded",function(){document.documentElement.removeAttribute("data-ab-loading");},false);</script>';

        // PWA：manifest + iOS meta 标签（$colors 已在函数开头定义，无需重复调用）
        $themeColorMap = array(
            'purple' => '#7D5260', 'blue' => '#556270', 'teal' => '#4A6363',
            'green'  => '#55624C', 'orange' => '#725A42', 'pink' => '#74565F', 'red' => '#775654',
        );
        $themeHex = isset($themeColorMap[$primaryColor]) ? $themeColorMap[$primaryColor] : '#7D5260';
        $manifestUrl = Typecho_Common::url('/action/admin-beautify?do=manifest', $options->index);

        // 读取 PWA 自定义设置
        $pwaAppName = isset($pluginOptions->pwa_appName) ? trim((string) $pluginOptions->pwa_appName) : '';
        $pwaAppIcon = isset($pluginOptions->pwa_appIcon) ? trim((string) $pluginOptions->pwa_appIcon) : '';
        $pwaTitle = ($pwaAppName !== '') ? $pwaAppName : ((string) $options->title . ' 管理后台');

        $injectTail .= "\n" . '<link rel="manifest" href="' . htmlspecialchars($manifestUrl) . '">';
        $injectTail .= "\n" . '<meta name="theme-color" content="' . $themeHex . '">';
        $injectTail .= "\n" . '<meta name="apple-mobile-web-app-capable" content="yes">';
        $injectTail .= "\n" . '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">';
        $injectTail .= "\n" . '<meta name="apple-mobile-web-app-title" content="' . htmlspecialchars($pwaTitle) . '">';
        $injectTail .= "\n" . '<meta name="mobile-web-app-capable" content="yes">';
        // PWA 图标 apple-touch-icon
        if ($pwaAppIcon !== '') {
            $injectTail .= "\n" . '<link rel="apple-touch-icon" sizes="180x180" href="' . htmlspecialchars($pwaAppIcon) . '">';
        }

        // 最终返回：injectHead（Typecho CSS 之前）+ Typecho 原生 CSS + injectTail（Typecho CSS 之后）
        // earlyScript 在 CSS <link> 之前，不被阻塞，首次渲染前 data-ab-loading 已设置 → 导航已隐藏
        // 匿名使用统计：用户未关闭时注入 Umami 脚本到 <head>
        $telemetryOptOut = isset($pluginOptions->telemetryOptOut) ? (string)$pluginOptions->telemetryOptOut : '0';
        if ($telemetryOptOut !== '1') {
            $injectTail .= "\n" . '<script defer src="https://umami.lhl.one/script.js" data-website-id="dfabc99f-991e-4f7c-9358-03177fbee0ec"></script>';
        }

        return $injectHead . $header . $injectTail;
    }

    /**
     * 输出管理页尾部 JS（仅在已登录时执行）
     */
    public static function renderFooter()
    {
        if (self::isLoginPage()) {
            return;
        }

        $options = Typecho_Widget::widget('Widget_Options');
        $pluginOptions = $options->plugin('AdminBeautify');
        $darkMode = $pluginOptions->darkMode ?: 'auto';
        $rawAnim = isset($pluginOptions->enableAnimation) ? (string)$pluginOptions->enableAnimation : '';
        $enableAnimation = ($rawAnim !== '') ? $rawAnim : '1';
        $pluginCardView = isset($pluginOptions->pluginCardView) ? (string)$pluginOptions->pluginCardView : '1';
        $editorVditor = isset($pluginOptions->editor_vditor) ? (string)$pluginOptions->editor_vditor : '0';
        $editorVditorMode = isset($pluginOptions->editor_vditorMode) ? (string)$pluginOptions->editor_vditorMode : 'ir';

        // Inject user avatar URL for sidebar header
        $user = Typecho_Widget::widget('Widget_User');
        $avatarUrl = '';
        if ($user->hasLogin() && $user->mail) {
            $hash = md5(strtolower(trim($user->mail)));
            $avatarUrl = 'https://cravatar.cn/avatar/' . $hash . '?s=80&d=mp';
        }
        $screenName = $user->hasLogin() ? $user->screenName : '';
        $ajaxUrl = Typecho_Common::url('/action/admin-beautify', $options->index);
        $security = Typecho_Widget::widget('Widget_Security');
        $token = $security->getToken($ajaxUrl);
        echo '<script>window.__AB_USER__=' . json_encode(array(
            'avatar' => $avatarUrl,
            'name'   => $screenName,
        )) . ';';
        echo 'window.__AB_AJAX__=' . json_encode(array(
            'url'   => $ajaxUrl,
            'token' => $token,
        )) . ';';
        $notifyOptOut = isset($pluginOptions->notifyOptOut) ? (string)$pluginOptions->notifyOptOut : '0';
        echo 'window.__AB_CONFIG__=' . json_encode(array(
            'darkMode'         => $darkMode,
            'enableAnimation'  => $enableAnimation,
            'pluginCardView'   => $pluginCardView,
            'siteName'         => $options->title,
            'editorVditor'     => $editorVditor,
            'editorVditorMode' => $editorVditorMode,
            'pluginVersion'    => '2.1.9',
            'notifyOptOut'     => $notifyOptOut,
        )) . ';</script>';

        $jsUrlPrefix = Typecho_Common::url('AdminBeautify/assets/AdminBeautify.min', $options->pluginUrl);
        echo '<script src="' . $jsUrlPrefix . '.v2.1.9.js"></script>';

        if ($darkMode === 'auto') {
            echo '<script>AdminBeautify.watchSystemTheme();</script>';
        }

        // 匿名统计：通过 umami.track() 发送含域名的自定义事件，可在 Umami 后台 Events 中直接看到来源域名
        $telemetryOptOut = isset($pluginOptions->telemetryOptOut) ? (string)$pluginOptions->telemetryOptOut : '0';
        if ($telemetryOptOut !== '1') {
            echo '<script>(function(){function abTrack(){if(window.umami&&typeof window.umami.track==="function"){window.umami.track("settings_visit",{domain:window.location.hostname,version:"2.1.9"});}else{setTimeout(abTrack,300);}}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",function(){setTimeout(abTrack,200);});}else{setTimeout(abTrack,200);}})();</script>';
        }

        // ====== 横幅更新通知（版本变化时显示，所有后台页面） ======
        if ($notifyOptOut !== '1') {
            echo '<script>(function(){
var CFG=window.__AB_CONFIG__||{};
var ver=CFG.pluginVersion||"";
if(!ver) return;
var lsKey="ab-seen-version";
var seen="";
try{seen=localStorage.getItem(lsKey)||"";}catch(e){}
if(seen===ver) return;
function mkBanner(release){
    var tag=release.tag_name||ver;
    var body=release.body||"";
    var url=release.html_url||("https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases");
    var lines=body.split("\n").filter(function(l){return l.trim()!=="";});
    var preview=lines.slice(0,4).join("\n");
    var hasMore=lines.length>4;
    var wrap=document.createElement("div");
    wrap.id="ab-banner-notify";
    wrap.setAttribute("role","region");
    wrap.setAttribute("aria-label","插件更新通知");
    wrap.style.transform="translateX(380px)";
    var hdr=document.createElement("div");
    hdr.className="ab-notify-hdr";
    var ic=document.createElement("span");
    ic.textContent="🎉";
    ic.style.fontSize="18px";
    var ttl=document.createElement("div");
    ttl.className="ab-notify-title";
    ttl.textContent="AdminBeautify 已更新";
    var pill=document.createElement("span");
    pill.className="ab-notify-pill";
    pill.textContent=tag;
    var cls=document.createElement("button");
    cls.className="ab-notify-close";
    cls.setAttribute("aria-label","关闭");
    cls.textContent="×";
    cls.onclick=function(){
        wrap.style.transform="translateX(380px)";
        setTimeout(function(){wrap.parentNode&&wrap.parentNode.removeChild(wrap);},400);
        try{localStorage.setItem(lsKey,ver);}catch(e){}
    };
    hdr.appendChild(ic);hdr.appendChild(ttl);hdr.appendChild(pill);hdr.appendChild(cls);
    var bd=document.createElement("div");
    bd.className="ab-notify-bd";
    var pre=document.createElement("pre");
    pre.id="ab-banner-preview";
    pre.className="ab-notify-pre";
    pre.textContent=preview;
    bd.appendChild(pre);
    if(hasMore){
        var full=document.createElement("pre");
        full.id="ab-banner-full";
        full.className="ab-notify-pre-full";
        full.textContent=lines.join("\n");
        bd.appendChild(full);
        var tog=document.createElement("button");
        tog.className="ab-notify-toggle";
        tog.textContent="展开更多 ▾";
        tog.onclick=function(){
            var expanded=full.style.display!=="none";
            full.style.display=expanded?"none":"block";
            pre.style.display=expanded?"block":"none";
            tog.textContent=expanded?"展开更多 ▾":"收起 ▴";
        };
        bd.appendChild(tog);
    }
    var ft=document.createElement("div");
    ft.className="ab-notify-ft";
    var lnk=document.createElement("a");
    lnk.href=url;lnk.target="_blank";lnk.rel="noopener";
    lnk.className="ab-notify-link";
    lnk.textContent="查看完整更新日志 →";
    ft.appendChild(lnk);
    wrap.appendChild(hdr);wrap.appendChild(bd);wrap.appendChild(ft);
    document.body.appendChild(wrap);
    setTimeout(function(){wrap.style.transform="translateX(0)";},50);
}
fetch("https://api.github.com/repos/lhl77/Typecho-Plugin-AdminBeautify/releases/latest",{cache:"no-cache"})
    .then(function(r){return r.ok?r.json():null;})
    .then(function(d){if(d&&d.tag_name) mkBanner(d);})
    .catch(function(){});
})();</script>';
        }


        $reqUriFooter = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $isWritePageFooter = (strpos($reqUriFooter, 'write-post.php') !== false || strpos($reqUriFooter, 'write-page.php') !== false);
        if ($editorVditor === '1' && $isWritePageFooter) {
            // Vditor 样式与逻辑：从 assets/vditor/ 加载独立文件，避免 PHP 文件膨胀
            $vditorCssUrl = Typecho_Common::url('AdminBeautify/assets/vditor/vditor_v1.0.1.css', $options->pluginUrl);
            $vditorJsUrl  = Typecho_Common::url('AdminBeautify/assets/vditor/vditor_v1.0.2.js', $options->pluginUrl);
            echo '<link rel="stylesheet" href="' . htmlspecialchars($vditorCssUrl) . '">';
            echo '<script src="' . htmlspecialchars($vditorJsUrl) . '"></script>';
        }
        $swUrl = Typecho_Common::url('/action/admin-beautify?do=sw', $options->index);
        echo '<script>(function(){'
            // ── toast 函数（MD3 风格，兼容亮/暗主题） ──
            . 'function abSwToast(){'
            .   'if(document.getElementById("ab-sw-toast"))return;'
            .   'var t=document.createElement("div");'
            .   't.id="ab-sw-toast";'
            .   't.setAttribute("role","alert");'
            .   'var isDark=document.documentElement.getAttribute("data-theme")==="dark";'
            .   'var bg=isDark?"#2d2b31":"#1c1b1f";'
            .   'var fg=isDark?"#e6e1e5":"#f3eff4";'
            .   'var btnBg=isDark?"rgba(208,188,255,.18)":"rgba(208,188,255,.22)";'
            .   't.style.cssText="position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);'
            .     'background:"+bg+";color:"+fg+";padding:14px 20px;border-radius:16px;'
            .     'box-shadow:0 4px 20px rgba(0,0,0,.35);display:flex;align-items:center;gap:14px;'
            .     'font-size:14px;font-family:inherit;z-index:9999;white-space:nowrap;'
            .     'transition:transform .35s cubic-bezier(.4,0,.2,1),opacity .35s;opacity:0;";'
            .   'var txt=document.createElement("span");'
            .   'txt.textContent="✨ 插件已更新，刷新后生效";'
            .   'var btn=document.createElement("button");'
            .   'btn.textContent="立即刷新";'
            .   'btn.style.cssText="border:none;background:"+btnBg+";color:"+fg+";padding:7px 16px;'
            .     'border-radius:999px;cursor:pointer;font-size:13px;font-weight:500;flex-shrink:0;'
            .     'transition:background .15s;";'
            .   'btn.onmouseover=function(){this.style.background=isDark?"rgba(208,188,255,.32)":"rgba(208,188,255,.36)";};'
            .   'btn.onmouseout=function(){this.style.background=btnBg;};'
            .   'btn.onclick=function(){window.location.reload();};'
            .   'var close=document.createElement("button");'
            .   'close.textContent="✕";'
            .   'close.title="关闭";'
            .   'close.style.cssText="border:none;background:transparent;color:"+fg+";'
            .     'cursor:pointer;font-size:16px;padding:2px 4px;opacity:.6;flex-shrink:0;";'
            .   'close.onclick=function(){'
            .     't.style.opacity="0";t.style.transform="translateX(-50%) translateY(80px)";'
            .     'setTimeout(function(){t.parentNode&&t.parentNode.removeChild(t);},400);'
            .   '};'
            .   't.appendChild(txt);t.appendChild(btn);t.appendChild(close);'
            .   'document.body.appendChild(t);'
            .   'requestAnimationFrame(function(){requestAnimationFrame(function(){'
            .     't.style.opacity="1";t.style.transform="translateX(-50%) translateY(0)";'
            .   '});});'
            . '}'
            // ── 监听 SW 发来的 SW_UPDATED 消息 ──
            . 'if("serviceWorker"in navigator){'
            .   'navigator.serviceWorker.addEventListener("message",function(e){'
            .     'if(e.data&&e.data.type==="SW_UPDATED"){ abSwToast(); }'
            .   '});'
            // ── 注册 SW，发现 waiting 状态直接跳过，并在 controllerchange 后 toast ──
            .   'var refreshing=false;'
            .   'navigator.serviceWorker.addEventListener("controllerchange",function(){'
            .     'if(refreshing)return;'  // 避免循环刷新
            .     '/* controller 已切换，toast 由 SW_UPDATED 消息触发，此处不再重复 */'
            .   '});'
            .   'navigator.serviceWorker.register(' . json_encode($swUrl) . ',{scope:' . json_encode(rtrim((string)$options->adminUrl, '/') . '/') . '})'
            .   '.then(function(reg){'
            .     'reg.addEventListener("updatefound",function(){'
            .       'var newSW=reg.installing;'
            .       'newSW.addEventListener("statechange",function(){'
            .         'if(newSW.state==="installed"&&navigator.serviceWorker.controller){'
            .           '/* 新 SW 已安装，等待激活后由 SW_UPDATED 消息触发 toast */'
            .           '/* 若 SW 没有自动 skipWaiting，则主动通知它跳过等待 */'
            .           'newSW.postMessage({type:"SKIP_WAITING"});'
            .         '}'
            .       '});'
            .     '});'
            .   '})'
            .   '.catch(function(){});'
            . '}'
            . '}());</script>';

        // PWA 独立模式下：延长认证 cookie 有效期，防止关闭应用后需重新登录
        $pingUrl = Typecho_Common::url('/action/admin-beautify?do=ping', $options->index);
        echo '<script>(function(){'
            . 'var isStandalone=window.matchMedia&&window.matchMedia("(display-mode:standalone)").matches||window.navigator.standalone===true;'
            . 'if(!isStandalone)return;'
            // 读取现有 __typecho_uid 和 __typecho_authCode cookie，重新设置 30 天过期
            . 'function renewCookies(){'
            .   'var cookies=document.cookie.split(";");'
            .   'for(var i=0;i<cookies.length;i++){'
            .     'var c=cookies[i].trim();'
            .     'if(c.indexOf("__typecho_uid=")=== 0||c.indexOf("__typecho_authCode=")===0){'
            .       'var eqIdx=c.indexOf("=");'
            .       'var name=c.substring(0,eqIdx);'
            .       'var value=c.substring(eqIdx+1);'
            .       'var d=new Date();d.setTime(d.getTime()+30*24*60*60*1000);'
            .       'document.cookie=name+"="+value+";expires="+d.toUTCString()+";path=/;SameSite=Lax";'
            .     '}'
            .   '}'
            . '}'
            // 立即续期一次
            . 'renewCookies();'
            // 每 10 分钟续期一次（保活）
            . 'setInterval(renewCookies,10*60*1000);'
            // 每 15 分钟 ping 后端保活 session
            . 'setInterval(function(){fetch(' . json_encode($pingUrl) . ',{credentials:"include"}).catch(function(){});},15*60*1000);'
            . '}());</script>';

        // ====== 插件更新检查模块（全局可用） ======
        echo '<script>(function(){';
        echo 'var __AB_VER__="2.1.9";';
        echo <<<'UPDATEJS'
// ---- abCheckUpdate: 向后端请求最新版信息 ----
window.abCheckUpdate=function(manual){
    var btn=document.getElementById("ab-btn-update");
    var origHTML=btn?btn.innerHTML:"";
    var ajax=window.__AB_AJAX__||{};
    if(!ajax.url) return;
    var spinSVG='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:ab-spin 1s linear infinite"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>';
    if(btn){ btn.disabled=true; btn.innerHTML=spinSVG+' 检查中...'; }
    var xhr=new XMLHttpRequest();
    xhr.open("GET",ajax.url+"?do=check-update",true);
    xhr.setRequestHeader("X-Requested-With","XMLHttpRequest");
    xhr.timeout=25000;
    xhr.onload=function(){
        if(btn){ btn.disabled=false; btn.innerHTML=origHTML; }
        try{
            var res=JSON.parse(xhr.responseText);
            if(res.code!==0){
                if(manual) abShowUpdateToast("error","❌ "+(res.message||"检查失败"));
                return;
            }
            var d=res.data;
            try{ localStorage.setItem("ab-update-check",JSON.stringify({ts:Date.now(),data:d})); }catch(e){}
            if(!d.has_update){
                if(manual) abShowUpdateToast("ok","✅ 已是最新版本 v"+d.current);
                return;
            }
            // 检查是否已忽略该版本
            try{ if(localStorage.getItem("ab-update-dismissed-v"+d.latest)) return; }catch(e){}
            abShowUpdateAvailable(d);
        }catch(e){
            if(manual) abShowUpdateToast("error","❌ 响应解析失败");
        }
    };
    xhr.onerror=xhr.ontimeout=function(){
        if(btn){ btn.disabled=false; btn.innerHTML=origHTML; }
        if(manual) abShowUpdateToast("error","❌ 检查失败，请确认服务器可访问 GitHub");
    };
    xhr.send();
};

// ---- abShowUpdateAvailable: 配置页用 banner 内嵌，其他页面用固定顶栏 ----
window.abShowUpdateAvailable=function(d){
    var old=document.getElementById("ab-update-notify"); if(old) old.remove();
    var banner=document.getElementById("ab-header-banner");
    var notify=document.createElement("div");
    notify.id="ab-update-notify";

    var dismissBtn='<button type="button" onclick="(function(el){el.remove();try{localStorage.setItem(\'ab-update-dismissed-v\'+el.dataset.ver,\'1\')}catch(e){}})(document.getElementById(\'ab-update-notify\'))" data-ver="'+d.latest+'" style="background:none;border:none;cursor:pointer;font-size:16px;opacity:.7;padding:0 0 0 8px;color:inherit;line-height:1" title="忽略此版本">✕</button>';

    // 统一按钮基础样式：圆角描边，透明背景，继承颜色
    // box-sizing + line-height + vertical-align 三项确保 button/a/span 高度完全一致
    var btnBase='display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:500;line-height:1.4;white-space:nowrap;text-decoration:none;border:1px solid currentColor;box-sizing:border-box;vertical-align:middle;';
    var actionBtns='';
    if(d.can_direct){
        actionBtns+='<button id="ab-btn-do-update" type="button" onclick="abDoUpdate()" style="'+btnBase+'background:rgba(255,255,255,.25);color:inherit;cursor:pointer;">立即更新</button>';
    } else {
        // 不支持直接更新：显示禁用态按钮 + 原因提示
        actionBtns+='<span style="'+btnBase+'background:rgba(255,255,255,.08);color:inherit;opacity:.5;cursor:not-allowed;" title="当前版本跨越了主/次版本号，需手动下载">立即更新</span>'
                   +'<span style="font-size:11px;opacity:.65;align-self:center;">需手动更新</span>';
    }
    actionBtns+='<a href="'+d.html_url+'" target="_blank" style="'+btnBase+'background:transparent;color:inherit;opacity:.85;">查看详情</a>';

    if(banner){
        // 配置页：嵌入 banner 内
        notify.style.cssText="margin:12px 0 0;padding:12px 16px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.35);border-radius:14px;font-size:13px;color:#fff;animation:ab-fadeIn .3s ease;backdrop-filter:blur(4px)";
        var bodyText='';
        if(d.body){ bodyText=d.body.replace(/[#*`]/g,"").replace(/\r?\n/g," ").trim(); if(bodyText.length>120) bodyText=bodyText.substring(0,120)+"..."; }
        notify.innerHTML='<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">'
            +'<div style="flex:1"><div style="font-weight:600;margin-bottom:'+(bodyText?'6px':'0')+'">🎉 发现新版本 <strong>v'+d.latest+'</strong> <span style="font-size:11px;opacity:.7;font-weight:400">（当前 v'+d.current+'）</span></div>'
            +(bodyText?'<div style="font-size:12px;opacity:.8;margin-bottom:10px;line-height:1.5">'+bodyText+'</div>':'')
            +'<div style="display:flex;gap:8px;flex-wrap:wrap">'+actionBtns+'</div></div>'
            +dismissBtn+'</div>';
        banner.appendChild(notify);
    } else {
        // 其他页面：固定顶部通知栏
        notify.style.cssText="position:fixed;top:0;left:0;right:0;z-index:99999;padding:8px 16px;background:linear-gradient(90deg,#5c6bc0,#7e57c2);color:#fff;font-size:13px;display:flex;align-items:center;gap:10px;box-shadow:0 2px 12px rgba(0,0,0,.25);animation:ab-slideDown .3s ease;font-weight:500";
        notify.innerHTML='<span style="flex:1">🎉 AdminBeautify 发现新版本 <strong>v'+d.latest+'</strong> <span style="opacity:.8;font-size:12px;font-weight:400">（当前 v'+d.current+'）</span></span>'
            +'<div style="display:flex;gap:8px;align-items:center">'+actionBtns+dismissBtn+'</div>';
        document.body.style.paddingTop=(parseInt(document.body.style.paddingTop||0)+40)+'px';
        document.body.insertBefore(notify,document.body.firstChild);
    }
    if(d.can_direct){
        notify.setAttribute("data-download-url",d.download_url||"");
        notify.setAttribute("data-new-version",d.latest);
    }
};

// ---- abDoUpdate: 执行直接更新 ----
window.abDoUpdate=function(){
    var notify=document.getElementById("ab-update-notify");
    if(!notify) return;
    var dlUrl=notify.getAttribute("data-download-url");
    var newVer=notify.getAttribute("data-new-version");
    if(!dlUrl||!newVer){ window.open("https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases","_blank"); return; }
    var btn=document.getElementById("ab-btn-do-update");
    if(btn){ btn.disabled=true; btn.innerHTML="⏳ 更新中..."; btn.style.opacity=".7"; }
    var ajax=window.__AB_AJAX__||{};
    var xhr=new XMLHttpRequest();
    xhr.open("POST",ajax.url+"?do=do-update",true);
    xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
    xhr.setRequestHeader("X-Requested-With","XMLHttpRequest");
    xhr.timeout=120000;
    xhr.onload=function(){
        try{
            var res=JSON.parse(xhr.responseText);
            if(res.code===0){
                notify.style.background="linear-gradient(90deg,#059669,#10b981)";
                notify.innerHTML='<span style="flex:1">✅ '+res.message+'</span><span style="font-size:12px;opacity:.85">3 秒后自动刷新...</span>';
                try{ localStorage.removeItem("ab-update-check"); }catch(e){}
                setTimeout(function(){ location.reload(); },3000);
            } else {
                notify.innerHTML='<span style="flex:1;color:#fca5a5">❌ '+(res.message||"更新失败")+'</span><a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases" target="_blank" style="color:#fff;text-decoration:underline;font-size:12px">前往 GitHub 手动下载</a>';
            }
        }catch(e){
            if(btn){ btn.disabled=false; btn.innerHTML="立即更新"; btn.style.opacity="1"; }
        }
    };
    xhr.onerror=xhr.ontimeout=function(){
        if(btn){ btn.disabled=false; btn.innerHTML="立即更新"; btn.style.opacity="1"; }
        abShowUpdateToast("error","❌ 更新超时，请手动下载");
    };
    xhr.send("download_url="+encodeURIComponent(dlUrl)+"&new_version="+encodeURIComponent(newVer)+"&_="+encodeURIComponent(ajax.token||""));
};

// ---- abShowUpdateToast: 轻量级提示（主要用于配置页手动检查反馈）----
window.abShowUpdateToast=function(type,msg){
    var old=document.getElementById("ab-update-notify"); if(old) old.remove();
    var colors={ok:"#059669",update:"#d97706",error:"#dc2626",info:"#6366f1"};
    var toast=document.createElement("div");
    toast.id="ab-update-notify";
    var banner=document.getElementById("ab-header-banner");
    if(banner){
        toast.style.cssText="margin:12px 0 0;padding:9px 14px;background:#fff;border:1px solid "+(colors[type]||"#6366f1")+";border-radius:12px;font-size:13px;color:"+(colors[type]||"#6366f1")+";display:flex;align-items:center;gap:8px;animation:ab-fadeIn .3s ease;font-weight:500;box-shadow:0 2px 8px rgba(0,0,0,.1)";
        toast.innerHTML="<span>"+msg+"</span>";
        banner.appendChild(toast);
    } else {
        toast.style.cssText="position:fixed;top:0;left:0;right:0;z-index:99999;padding:10px 20px;background:"+(colors[type]||"#6366f1")+";color:#fff;font-size:13px;text-align:center;animation:ab-slideDown .3s ease;font-weight:500";
        toast.innerHTML=msg;
        document.body.insertBefore(toast,document.body.firstChild);
    }
    setTimeout(function(){ if(toast.parentNode) toast.remove(); },6000);
};

// ---- 注入动画样式（如未注入）----
if(!document.getElementById("ab-update-anim")){
    var st=document.createElement("style");
    st.id="ab-update-anim";
    st.textContent="@keyframes ab-spin{to{transform:rotate(360deg)}}@keyframes ab-slideDown{from{transform:translateY(-100%);opacity:0}to{transform:translateY(0);opacity:1}}";
    document.head.appendChild(st);
}

// ---- 自动检查（每小时一次，页面加载后 4 秒执行）----
setTimeout(function(){
    try{
        var cached=JSON.parse(localStorage.getItem("ab-update-check")||"null");
        var ONE_HOUR=3600000;
        if(!cached||(Date.now()-cached.ts)>ONE_HOUR){
            // 缓存已过期或不存在，发起网络请求
            window.abCheckUpdate(false);
        } else if(cached.data&&cached.data.has_update){
            // 缓存中有更新信息，检查是否已忽略
            var dismissed=localStorage.getItem("ab-update-dismissed-v"+cached.data.latest);
            if(!dismissed) window.abShowUpdateAvailable(cached.data);
        }
    }catch(e){}
},4000);
UPDATEJS;
        echo '})();</script>';

        // ====== 移动端导航：遮罩关闭抽屉 + 子菜单点击展开 ======
        echo '<script>(function(){'
            . '"use strict";'
            . 'function abIsMobile(){return window.innerWidth<=575;}'
            // 1. 点击抽屉外部（遮罩区域）关闭抽屉
            . 'document.addEventListener("click",function(e){'
            .   'if(!abIsMobile())return;'
            .   'var mb=document.querySelector(".typecho-head-nav .menu-bar[open]");'
            .   'if(!mb)return;'
            .   'var dr=document.querySelector(".typecho-head-nav nav > menu");'
            .   'if(mb.contains(e.target))return;'
            .   'if(dr&&dr.contains(e.target))return;'
            .   'mb.removeAttribute("open");'
            . '},true);'
            // 2. App Bar 注入头像 + 博客名，子菜单点击展开/折叠
            . 'function abInitMobileNav(){'
            .   'if(!abIsMobile())return;'
            // 2a. 注入品牌区域（头像 + 博客名）到抽屉顶端，sticky
            .   'var drawerMenu=document.querySelector(".typecho-head-nav nav > menu");'
            .   'if(drawerMenu&&!document.getElementById("ab-mobile-branding")){'
            .     'var user=window.__AB_USER__||{};'
            .     'var cfg=window.__AB_CONFIG__||{};'
            .     'var siteName=cfg.siteName||"";'
            .     'var avatarUrl=user.avatar||"";'
            .     'var bd=document.createElement("div");'
            .     'bd.id="ab-mobile-branding";'
            .     'var bhtml="";'
            .     'if(avatarUrl)bhtml+="<img src=\""+avatarUrl+"\" alt=\"\" style=\"width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0\">";'
            .     'if(siteName)bhtml+="<span style=\"font-size:15px;font-weight:600;color:var(--md-on-surface);white-space:nowrap;overflow:hidden;text-overflow:ellipsis\">"+siteName+"</span>";'
            .     'bd.innerHTML=bhtml;'
            .     'drawerMenu.insertBefore(bd,drawerMenu.firstChild);'
            .   '}'
            // 2b. 子菜单：活跃项自动展开，点击切换折叠
            .   'var items=document.querySelectorAll(".typecho-head-nav nav > menu > li");'
            .   '[].forEach.call(items,function(li){'
            .     'var sub=li.querySelector("menu");'
            .     'if(!sub)return;'
            .     '/* 标记父级菜单项：避免被 AJAX 导航捕获拦截 */'
            .     'li.classList.add("ab-has-children");'
            .     'if(li.classList.contains("focus"))li.classList.add("ab-expanded");'
            .   '});'
            . '}'
            . 'if(document.readyState==="loading"){'
            .   'document.addEventListener("DOMContentLoaded",abInitMobileNav);'
            . '}else{abInitMobileNav();}'
            // 3. 监听窗口大小变化：切换到桌面端时移除品牌区域
            . 'var abResizeTimer;'
            . 'window.addEventListener("resize",function(){'
            .   'clearTimeout(abResizeTimer);'
            .   'abResizeTimer=setTimeout(function(){'
            .     'var bd=document.getElementById("ab-mobile-branding");'
            .     'if(!abIsMobile()&&bd){'
            .       'bd.parentNode&&bd.parentNode.removeChild(bd);'
            .     '} else if(abIsMobile()&&!document.getElementById("ab-mobile-branding")){'
            .       'abInitMobileNav();'
            .     '}'
            .   '},100);'
            . '});'
            . '}());</script>';

        // ====== Typecho 版本兼容提示（低于 1.3.0 时提示升级；1.2.1 提示启用兼容脚本） ======
        $typechoVer = isset($options->version) ? (string) $options->version : '';
        if ($typechoVer && version_compare($typechoVer, '1.3.0', '<')) {
            $noteMsg = '检测到 Typecho 版本：v' . htmlspecialchars($typechoVer) . '，建议升级到 v1.3.0 以获得最佳兼容性。';
            if (version_compare($typechoVer, '1.2.1', '==')) {
                $noteMsg .= ' 如果暂时无法升级，请在 AdminBeautify 插件设置中手动启用 Typecho 1.2.1 兼容脚本。';
            }
            // 插入到页面顶部的 JS 提示条（不依赖服务端渲染位置，可安全注入）
            echo '<script>(function(){try{var b=document.createElement("div");b.id="ab-typecho-version-note";b.style.cssText="position:fixed;top:0;left:0;right:0;z-index:99998;padding:10px 14px;background:#fef3c7;color:#92400e;font-size:13px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 12px rgba(0,0,0,.06);";b.innerHTML=' . json_encode($noteMsg) . ';document.body.insertBefore(b,document.body.firstChild);document.body.style.paddingTop=(parseInt(document.body.style.paddingTop||0)+48)+"px";setTimeout(function(){b.style.transform="translateY(0)";},10);}catch(e){} })();</script>';
        }

        // ====== AB插件仓库 快捷入口（仅 plugins.php 页面注入按钮）======
        $absDir = rtrim(dirname(__FILE__), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'AdminBeautifyStore';
        $absInstalled = is_dir($absDir);
        // 检测 AdminBeautifyStore 是否已激活（try/catch：未激活时 plugin() 会抛异常）
        $absActivated = false;
        if ($absInstalled) {
            try {
                $options->plugin('AdminBeautifyStore');
                $absActivated = true;
            } catch (Exception $e) {
                $absActivated = false;
            }
        }

        if (!$absInstalled) {
            // 未安装：点击按钮触发 AJAX 自动安装
            $absStoreUrl  = '';
            $absBtnText   = '安装AB插件仓库';
            $absTarget    = '';
            $absWarnText  = '';
            $absWarnUrl   = '';
            $absAction    = 'install';
        } elseif (!$absActivated) {
            // 已安装但未启用：指向 plugins.php 启用
            $absStoreUrl  = Typecho_Common::url('/admin/plugins.php', $options->index);
            $absBtnText   = 'AB插件仓库';
            $absTarget    = '_self';
            $absWarnText  = '插件未启用，请先启用 AB-Store';
            $absWarnUrl   = Typecho_Common::url('/admin/plugins.php', $options->index);
            $absAction    = 'go';
        } else {
            // 已启用：直接进入面板
            $absStoreUrl  = Typecho_Common::url('/admin/extending.php?panel=' . urlencode('AdminBeautifyStore/Panel.php'), $options->index);
            $absBtnText   = 'AB插件仓库';
            $absTarget    = '_self';
            $absWarnText  = '';
            $absWarnUrl   = '';
            $absAction    = 'go';
        }

        echo '<script>(function(){'
            . 'var BTN_ID="ab-store-shortcut-btn";'
            . 'var WARN_ID="ab-store-shortcut-warn";'
            . 'var BTN_URL=' . json_encode($absStoreUrl) . ';'
            . 'var BTN_TEXT=' . json_encode($absBtnText) . ';'
            . 'var BTN_TARGET=' . json_encode($absTarget) . ';'
            . 'var WARN_TEXT=' . json_encode($absWarnText) . ';'
            . 'var BTN_ACTION=' . json_encode($absAction) . ';'
            . 'function abInjectStoreBtn(){'
            .   'if(document.getElementById(BTN_ID)) return;'
            .   'var titleArea=document.querySelector(".typecho-page-title");'
            .   'if(!titleArea) return;'
            // 让标题区变为 flex 布局，按钮居右
            .   'titleArea.style.display="flex";'
            .   'titleArea.style.alignItems="center";'
            .   'titleArea.style.flexWrap="wrap";'
            .   'titleArea.style.gap="8px";'
            .   'var h2=titleArea.querySelector("h2,h3");'
            .   'if(h2) h2.style.flex="1 1 auto";'
            // 注入警告提示（已安装但未启用时）
            .   'if(WARN_TEXT&&!document.getElementById(WARN_ID)){'
            .     'var warn=document.createElement("span");'
            .     'warn.id=WARN_ID;'
            .     'warn.textContent=WARN_TEXT;'
            .     'warn.style.cssText="font-size:12px;color:var(--md-error,#b3261e,#b3261e);font-weight:500;white-space:nowrap;";'
            .     'titleArea.appendChild(warn);'
            .   '}'
            // 注入按钮
            .   'var btn;'
            .   'if(BTN_ACTION==="install"){'
            // 未安装状态：创建 <button> 触发 AJAX 自动安装
            .     'btn=document.createElement("button");'
            .     'btn.type="button";'
            .     'btn.onclick=function(){'
            .       'btn.disabled=true;'
            .       'btn.textContent="安装中...";'
            .       'var ajax=window.__AB_AJAX__||{};'
            .       'if(!ajax.url){btn.textContent="❌ 获取接口地址失败";btn.disabled=false;return;}'
            .       'var xhr=new XMLHttpRequest();'
            .       'xhr.open("POST",ajax.url+"?do=install-abs",true);'
            .       'xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded");'
            .       'xhr.setRequestHeader("X-Requested-With","XMLHttpRequest");'
            .       'xhr.onload=function(){'
            .         'try{'
            .           'var res=JSON.parse(xhr.responseText);'
            .           'if(res.code===0){'
            .             'btn.textContent="✅ 安装成功，刷新中...";'
            .             'setTimeout(function(){location.reload();},1500);'
            .           '}else{'
            .             'btn.textContent="❌ "+(res.message||"安装失败");'
            .             'btn.disabled=false;'
            .           '}'
            .         '}catch(e){'
            .           'btn.textContent="❌ 安装失败";'
            .           'btn.disabled=false;'
            .         '}'
            .       '};'
            .       'xhr.onerror=function(){btn.textContent="❌ 网络错误";btn.disabled=false;};'
            .       'xhr.send("_="+encodeURIComponent(ajax.token||""));'
            .     '};'
            .   '}else{'
            // 已安装或已启用：创建 <a> 跳转链接
            .     'btn=document.createElement("a");'
            .     'btn.href=BTN_URL;'
            .     'btn.target=BTN_TARGET;'
            .     'btn.rel="noopener";'
            .   '}'
            .   'btn.id=BTN_ID;'
            .   'btn.textContent=BTN_TEXT;'
            .   'btn.className="btn btn-s btn-primary";'
            .   'btn.style.cssText="margin-left:auto;flex-shrink:0;";'
            .   'titleArea.appendChild(btn);'
            . '}'
            . 'function abRemoveStoreBtn(){'
            .   'var old=document.getElementById(BTN_ID);'
            .   'if(old)old.parentNode.removeChild(old);'
            .   'var ow=document.getElementById(WARN_ID);'
            .   'if(ow)ow.parentNode.removeChild(ow);'
            . '}'
            . 'function abCheckAndInject(url){'
            .   'if((url||window.location.href).indexOf("plugins.php")!==-1){abInjectStoreBtn();}'
            .   'else{abRemoveStoreBtn();}'
            . '}'
            . 'if(document.readyState==="loading"){'
            .   'document.addEventListener("DOMContentLoaded",function(){abCheckAndInject();});'
            . '}else{abCheckAndInject();}'
            . 'document.addEventListener("ab:pageload",function(e){'
            .   'var url=(e&&e.detail&&e.detail.url)?e.detail.url:window.location.href;'
            .   'abCheckAndInject(url);'
            . '});'
            . '})();</script>';

        // ====== 加载兼容性脚本 ======
        self::loadCompatScripts($options, $pluginOptions);
    }

    /**
     * 加载兼容性脚本（本地 + 外部）
     *
     * 本地脚本：扫描 assets/compat/ 目录下的 .js 文件，仅加载用户手动启用的脚本
     * 外部脚本：从插件设置中读取用户配置的 URL 列表
     *
     * 开发者可在 assets/compat/ 目录下放置 JS 文件来兼容其他插件的页面排版。
     * 每个 JS 文件应自行判断当前页面是否需要执行。
     * 详细规范见 assets/compat/README.md
     */
    private static function loadCompatScripts($options, $pluginOptions)
    {
        $compatBaseUrl = Typecho_Common::url('AdminBeautify/assets/compat/', $options->pluginUrl);
        $compatDir = dirname(__FILE__) . '/assets/compat/';

        // 读取已启用脚本列表（默认全部关闭，需手动启用）
        // 注：字段名沿用 compat_disabledScripts 以保持数据库 schema 兼容，语义已改为存储"已启用"列表
        $enabledRaw = isset($pluginOptions->compat_disabledScripts) ? (string) $pluginOptions->compat_disabledScripts : '';
        $enabledList = ($enabledRaw !== '') ? (array) json_decode($enabledRaw, true) : array();
        if (!is_array($enabledList)) $enabledList = array();

        // 1. 加载本地兼容脚本（仅加载用户手动启用的脚本）
        if (is_dir($compatDir) && !empty($enabledList)) {
            foreach ($enabledList as $file) {
                if (substr($file, -3) === '.js' && is_file($compatDir . $file)) {
                    $fileUrl = $compatBaseUrl . $file;
                    $fileMtime = filemtime($compatDir . $file);
                    echo '<script src="' . htmlspecialchars($fileUrl) . '?v=' . $fileMtime . '"></script>' . "\n";
                }
            }
        }

        // 2. 加载外部兼容脚本
        $externalJs = isset($pluginOptions->compat_externalJs) ? trim((string) $pluginOptions->compat_externalJs) : '';
        if ($externalJs !== '') {
            $lines = preg_split('/[\r\n]+/', $externalJs);
            foreach ($lines as $line) {
                $url = trim($line);
                if ($url !== '' && (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0 || strpos($url, '//') === 0)) {
                    echo '<script src="' . htmlspecialchars($url) . '"></script>' . "\n";
                }
            }
        }
    }

    /**
     * 扫描 compat 目录中的 JS 文件并解析元数据
     *
     * @param string $compatDir 目录绝对路径
     * @return array 每个元素: ['file'=>文件名, 'name'=>名称, 'description'=>简介, 'plugins'=>适用插件, 'version'=>版本, 'author'=>作者]
     */
    private static function scanCompatScripts($compatDir)
    {
        $result = array();
        if (!is_dir($compatDir)) return $result;
        $files = scandir($compatDir);
        if (!$files) return $result;
        foreach ($files as $file) {
            if (substr($file, -3) !== '.js' || !is_file($compatDir . $file)) continue;
            $meta = self::parseCompatMeta($compatDir . $file);
            $meta['file'] = $file;
            $result[] = $meta;
        }
        return $result;
    }

    /**
     * 从 JS 文件头部注释中解析 @name, @description, @plugins, @version, @author 元数据
     *
     * 格式示例:
     *   /**
     *    * @name 插件名兼容
     *    * @description 修复xx问题
     *    * @plugins PluginA, PluginB
     *    * @version 1.0.0
     *    * @author 作者名
     *    *\/
     */
    private static function parseCompatMeta($filePath)
    {
        $defaults = array(
            'name'        => '',
            'description' => '',
            'plugins'     => '',
            'version'     => '',
            'author'      => '',
        );
        // 只读取文件前 2KB，避免大文件读取
        $content = @file_get_contents($filePath, false, null, 0, 2048);
        if ($content === false) return $defaults;

        // 匹配第一个 /** ... */ 块
        if (!preg_match('/\/\*\*(.*?)\*\//s', $content, $blockMatch)) {
            return $defaults;
        }
        $block = $blockMatch[1];

        // 逐行解析 @key value
        $tags = array('name', 'description', 'plugins', 'version', 'author');
        foreach ($tags as $tag) {
            if (preg_match('/@' . $tag . '\s+(.+)/i', $block, $m)) {
                $defaults[$tag] = trim($m[1]);
            }
        }

        // 如果 name 为空，用文件名（去掉 .js）作为 fallback
        if ($defaults['name'] === '') {
            $defaults['name'] = basename($filePath, '.js');
        }

        return $defaults;
    }

    /**
     * 在配置面板中渲染兼容脚本列表（含启用/禁用开关，默认关闭）
     */
    private static function renderCompatScriptsList($scripts, $enabledList, $accentColor)
    {
        if (empty($scripts)) {
            echo '<div id="ab-compat-scripts-list" class="ab-compat-empty" style="margin:12px 0 20px;padding:16px;background:#fefce8;border:1px solid #fde68a;border-radius:10px;font-size:13px;color:#854d0e">
                <span style="margin-right:6px">📂</span> <code>assets/compat/</code> 目录中未找到兼容脚本。
            </div>';
            return;
        }

        echo '<div id="ab-compat-scripts-list" style="margin:12px 0 20px">';
        echo '<div class="ab-compat-list-title" style="font-size:14px;font-weight:600;color:#374151;margin-bottom:10px;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
            <span style="font-size:16px">📋</span>
            <span style="flex:1">本地兼容脚本（共 ' . count($scripts) . ' 个）</span>
            <button id="ab-compat-sync-btn" type="button" onclick="abSyncCompat()" style="'
                . 'display:inline-flex;align-items:center;gap:6px;padding:6px 14px;'
                . 'background:' . $accentColor . ';color:#fff;border:none;border-radius:20px;'
                . 'font-size:12px;font-weight:500;cursor:pointer;transition:opacity .2s;'
                . 'box-shadow:0 1px 4px rgba(0,0,0,.15);flex-shrink:0'
                . '" onmouseover="this.style.opacity=\'.85\'" onmouseout="this.style.opacity=\'1\'">'
                . '<span id="ab-compat-sync-icon">☁️</span><span id="ab-compat-sync-label">从 GitHub 同步</span>'
            . '</button>
        </div>
        <div id="ab-compat-sync-result" style="display:none;margin-bottom:12px"></div>';

        foreach ($scripts as $s) {
            $file = htmlspecialchars($s['file']);
            $name = htmlspecialchars($s['name']);
            $desc = htmlspecialchars($s['description']);
            $plugins = htmlspecialchars($s['plugins']);
            $version = htmlspecialchars($s['version']);
            $author = htmlspecialchars($s['author']);
            $isEnabled = in_array($s['file'], $enabledList);
            $toggleId = 'ab-compat-toggle-' . md5($s['file']);

            echo '<div class="ab-compat-script-item" data-file="' . $file . '" style="'
                . 'display:flex;align-items:flex-start;gap:14px;padding:14px 18px;margin-bottom:8px;'
                . 'background:' . ($isEnabled ? '#fff' : '#f9fafb') . ';'
                . 'border:1px solid ' . ($isEnabled ? $accentColor . '44' : '#e5e7eb') . ';'
                . 'border-radius:12px;transition:all .2s;'
                . ($isEnabled ? '' : 'opacity:.6;')
                . '">';

            // 开关
            echo '<div style="flex-shrink:0;padding-top:2px">'
                . '<label style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer">'
                . '<input type="checkbox" class="ab-compat-checkbox" data-file="' . $file . '" '
                . ($isEnabled ? 'checked' : '') . ' '
                . 'style="opacity:0;width:0;height:0;position:absolute">'
                . '<span style="'
                . 'position:absolute;top:0;left:0;right:0;bottom:0;'
                . 'background:' . ($isEnabled ? $accentColor : '#d1d5db') . ';'
                . 'border-radius:12px;transition:background .2s;'
                . '"></span>'
                . '<span style="'
                . 'position:absolute;top:2px;left:' . ($isEnabled ? '22px' : '2px') . ';'
                . 'width:20px;height:20px;background:#fff;border-radius:50%;'
                . 'transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);'
                . '"></span>'
                . '</label>'
                . '</div>';

            // 信息区域
            echo '<div style="flex:1;min-width:0">';
            echo '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">';
            echo '<span class="ab-compat-name" style="font-size:14px;font-weight:600;color:#111827">' . $name . '</span>';
            if ($version) {
                echo '<span class="ab-compat-meta" style="font-size:11px;color:#6b7280;background:#f3f4f6;padding:1px 8px;border-radius:8px">v' . $version . '</span>';
            }
            if ($author) {
                echo '<span class="ab-compat-meta" style="font-size:11px;color:#6b7280">by ' . $author . '</span>';
            }
            echo '</div>';

            if ($desc) {
                echo '<div class="ab-compat-desc" style="font-size:13px;color:#4b5563;line-height:1.5;margin-bottom:4px">' . $desc . '</div>';
            }

            if ($plugins) {
                $pluginArr = array_map('trim', explode(',', $plugins));
                echo '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">';
                foreach ($pluginArr as $p) {
                    echo '<span style="font-size:11px;color:' . $accentColor . ';background:' . $accentColor . '15;padding:2px 10px;border-radius:8px;border:1px solid ' . $accentColor . '33;font-weight:500">'
                        . htmlspecialchars($p) . '</span>';
                }
                echo '</div>';
            }

            echo '<div class="ab-compat-meta" style="font-size:11px;color:#9ca3af;margin-top:4px">📄 ' . $file . '</div>';
            echo '</div>'; // end info

            echo '</div>'; // end item
        }

        echo '</div>'; // end list

        // JS: 开关联动隐藏字段
        echo '<script>
(function(){
    function updateEnabledList(){
        var items = document.querySelectorAll(".ab-compat-checkbox");
        var enabled = [];
        for(var i=0;i<items.length;i++){
            if(items[i].checked){
                enabled.push(items[i].getAttribute("data-file"));
            }
        }
        var hidden = document.querySelector("[name=compat_disabledScripts]");
        if(hidden) hidden.value = JSON.stringify(enabled);
    }
    function initToggles(){
        var checkboxes = document.querySelectorAll(".ab-compat-checkbox");
        for(var i=0;i<checkboxes.length;i++){
            checkboxes[i].addEventListener("change", function(){
                var item = this.closest(".ab-compat-script-item");
                var track = this.nextElementSibling;
                var thumb = track ? track.nextElementSibling : null;
                if(this.checked){
                    if(item){item.style.opacity="1";item.style.background="#fff";}
                    if(track) track.style.background="' . $accentColor . '";
                    if(thumb) thumb.style.left="22px";
                }else{
                    if(item){item.style.opacity=".6";item.style.background="#f9fafb";}
                    if(track) track.style.background="#d1d5db";
                    if(thumb) thumb.style.left="2px";
                }
                updateEnabledList();
            });
        }
    }
    if(document.readyState==="loading"){
        document.addEventListener("DOMContentLoaded",initToggles);
    }else{
        initToggles();
    }
})();

// ---- 从 GitHub 同步兼容脚本 ----
window.abSyncCompat = function(){
    var btn = document.getElementById("ab-compat-sync-btn");
    var icon = document.getElementById("ab-compat-sync-icon");
    var label = document.getElementById("ab-compat-sync-label");
    var resultBox = document.getElementById("ab-compat-sync-result");
    if(!btn) return;

    btn.disabled = true;
    btn.style.opacity = ".6";
    if(icon) icon.textContent = "⏳";
    if(label) label.textContent = "同步中...";
    if(resultBox){ resultBox.style.display="none"; resultBox.innerHTML=""; }

    var ajax = window.__AB_AJAX__ || {};
    var url = (ajax.url || "") + "?do=sync-compat";
    var token = ajax.token || "";

    var xhr = new XMLHttpRequest();
    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.timeout = 60000;
    xhr.onload = function(){
        btn.disabled = false; btn.style.opacity = "1";
        if(icon) icon.textContent = "☁️";
        if(label) label.textContent = "从 GitHub 同步";
        try{
            var res = JSON.parse(xhr.responseText);
            if(res.code === 0){
                var d = res.data || {};
                var summary = d.summary || {};
                var results = d.results || [];
                var html = "<div style=\"padding:12px 16px;border-radius:12px;font-size:13px;line-height:1.8;"
                    + "background:#f0fdf4;border:1px solid #bbf7d0;color:#166534\">"
                    + "<div style=\"font-weight:600;margin-bottom:6px\">✅ " + res.message + "</div>"
                    + "<div style=\"display:flex;gap:16px;flex-wrap:wrap;margin-bottom:8px\">"
                    + "<span>❗ 新增 <strong>" + (summary.added||0) + "</strong></span>"
                    + "<span>☁️ 更新 <strong>" + (summary.updated||0) + "</strong></span>"
                    + "<span>✔️ 跳过 <strong>" + (summary.skipped||0) + "</strong></span>"
                    + (summary.errors > 0 ? "<span style=\"color:#dc2626\">❌ 失败 <strong>" + summary.errors + "</strong></span>" : "")
                    + "</div>";
                var changed = [];
                for(var i=0;i<results.length;i++){
                    var r = results[i];
                    if(r.status === "added" || r.status === "updated" || r.status === "error"){
                        var icon2 = r.status==="added"?"❗":r.status==="updated"?"☁️":"❌";
                        changed.push("<span style=\"margin-right:12px\">" + icon2 + " " + r.file
                            + (r.msg ? " <span style=\"color:#6b7280;font-size:12px\">(" + r.msg + ")</span>" : "")
                            + "</span>");
                    }
                }
                if(changed.length > 0){
                    html += "<div style=\"flex-wrap:wrap;display:flex\">" + changed.join("") + "</div>";
                }
                if((summary.added||0) + (summary.updated||0) > 0){
                    html += "<div style=\"margin-top:8px;font-size:12px;color:#166534\">💡 有脚本已更新，请刷新页面以查看最新列表。</div>";
                }
                html += "</div>";
                if(resultBox){ resultBox.innerHTML=html; resultBox.style.display="block"; }
            } else {
                var errHtml = "<div style=\"padding:12px 16px;border-radius:12px;font-size:13px;background:#fef2f2;border:1px solid #fecaca;color:#dc2626\">"
                    + "❌ " + (res.message || "同步失败") + "</div>";
                if(resultBox){ resultBox.innerHTML=errHtml; resultBox.style.display="block"; }
            }
        } catch(e){
            if(resultBox){ resultBox.innerHTML="<div style=\"padding:12px;border-radius:10px;background:#fef2f2;color:#dc2626;font-size:13px\">❌ 响应解析失败</div>"; resultBox.style.display="block"; }
        }
    };
    xhr.onerror = xhr.ontimeout = function(){
        btn.disabled = false; btn.style.opacity = "1";
        if(icon) icon.textContent = "☁️";
        if(label) label.textContent = "从 GitHub 同步";
        if(resultBox){ resultBox.innerHTML="<div style=\"padding:12px;border-radius:10px;background:#fef2f2;color:#dc2626;font-size:13px\">❌ 请求超时或网络错误，请检查服务器是否能访问 GitHub</div>"; resultBox.style.display="block"; }
    };
    xhr.send("_=" + encodeURIComponent(token));
};
</script>';
    }

    /**
     * 输出登录页尾部 JS（仅在未登录时执行）
     */
    public static function renderLoginFooter()
    {
        if (!self::isLoginPage()) {
            return;
        }

        $options = Typecho_Widget::widget('Widget_Options');
        $pluginOptions = $options->plugin('AdminBeautify');

        $showSiteName = ((string) $pluginOptions->login_showSiteName !== '0');
        $showThemeToggle = ((string) $pluginOptions->login_showThemeToggle !== '0');
        $customJs = (string) $pluginOptions->login_customJs;

        $siteTitle = (string) $options->title;
        $jsSiteTitle = self::jsString($siteTitle);
        $jsShowSiteName = $showSiteName ? 'true' : 'false';
        $jsShowToggle = $showThemeToggle ? 'true' : 'false';

        echo "\n<script id=\"loginbeautify-main\">
(function(){
    function qs(sel, root){ return (root||document).querySelector(sel); }
    function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

    var form = qs('form[action*=\"login\"]') || qs('form') || qs('.typecho-login form') || qs('.typecho-login');
    if (!form) return;

    var wrap = document.createElement('div');
    wrap.className = 'lb-wrap';

    var bg = document.createElement('div');
    bg.className = 'lb-bg';
    wrap.appendChild(bg);

    var overlay = document.createElement('div');
    overlay.className = 'lb-bg-overlay';
    wrap.appendChild(overlay);

    var card = document.createElement('div');
    card.className = 'lb-card';

    var head = document.createElement('div');
    head.className = 'lb-head';

    var titleWrap = document.createElement('div');
    titleWrap.className = 'lb-title';

    var showSiteName = {$jsShowSiteName};

    if (showSiteName) {
      var name = document.createElement('div');
      name.className = 'name';
      name.textContent = {$jsSiteTitle};
      titleWrap.appendChild(name);
    }

    var isRegister = location.href.indexOf('register.php') !== -1;

    var sub = document.createElement('div');
    sub.className = 'sub';
    sub.textContent = isRegister ? '注册' : '登录';
    titleWrap.appendChild(sub);

    head.appendChild(titleWrap);
    card.appendChild(head);

    form.classList.add('lb-form');

    var inputs = qsa('input[type=\"text\"], input[type=\"password\"], input[type=\"email\"]', form);
    inputs.forEach(function(input, idx){
      var field = document.createElement('div');
      field.className = 'lb-field';

      var label = document.createElement('label');
      var n = (input.getAttribute('name') || '').toLowerCase();
      var t = (input.getAttribute('type') || '').toLowerCase();

      if (isRegister) {
        if (idx === 0) {
          label.textContent = '用户名';
          input.setAttribute('placeholder', '请输入用户名');
        } else if (idx === 1 || t === 'email' || n === 'mail') {
          label.textContent = '邮箱';
          input.setAttribute('placeholder', '请输入邮箱');
        } else {
          label.textContent = '输入';
          if (!input.getAttribute('placeholder')) {
            input.setAttribute('placeholder', '请输入内容');
          }
        }
      } else {
        if (n.indexOf('name') !== -1 || n.indexOf('user') !== -1) {
          label.textContent = '用户名/邮箱';
          input.setAttribute('placeholder', '用户名/邮箱');
        } else if (n.indexOf('pass') !== -1) {
          label.textContent = '密码';
          if (!input.getAttribute('placeholder')) {
            input.setAttribute('placeholder', '请输入密码');
          }
        } else {
          label.textContent = '输入';
          if (!input.getAttribute('placeholder')) {
            input.setAttribute('placeholder', '请输入内容');
          }
        }
      }

      var parent = input.parentNode;
      parent.insertBefore(field, input);
      field.appendChild(label);
      field.appendChild(input);
    });

    var remember = qs('input[type=\"checkbox\"]', form);
    if (remember) {
      var rememberWrap = remember.closest('p') || remember.parentNode;
      if (rememberWrap) {
        rememberWrap.classList.add('lb-remember');
      }
      // PWA 独立模式下自动勾选「记住我」，防止关闭应用后 session cookie 丢失需重新登录
      var isStandalone = window.matchMedia('(display-mode: standalone)').matches
                      || window.navigator.standalone === true;
      if (isStandalone && !remember.checked) {
        remember.checked = true;
      }
    }

    var submit = qs('input[type=\"submit\"], button[type=\"submit\"]', form);
    if (submit) {
      var submitWrap = document.createElement('div');
      submitWrap.className = 'lb-submit';
      var p = submit.parentNode;
      p.insertBefore(submitWrap, submit);
      submitWrap.appendChild(submit);
    }

    card.appendChild(form);
    wrap.appendChild(card);

    document.body.insertBefore(wrap, document.body.firstChild);

    var typechoLogin = qs('.typecho-login');
    if (typechoLogin && !typechoLogin.contains(wrap)) {
      typechoLogin.classList.add('lb-hide');
    }

    // Theme footer
    var lbFooter = document.createElement('div');
    lbFooter.className = 'lb-footer-theme';
    lbFooter.innerHTML = 'Theme <a href=\"https://github.com/lhl77/Typecho-Plugin-AdminBeautify\" target=\"_blank\" rel=\"noopener noreferrer\">AdminBeautify</a> by <a href=\"https://blog.lhl.one\" target=\"_blank\" rel=\"noopener noreferrer\">LHL</a>';
    card.appendChild(lbFooter);

    var showToggle = {$jsShowToggle};
    if (showToggle) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'lb-theme-toggle';
      btn.setAttribute('aria-label', '切换主题');

      var sunIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      sunIcon.setAttribute('viewBox', '0 0 24 24');
      sunIcon.setAttribute('fill', 'none');
      sunIcon.setAttribute('stroke', 'currentColor');
      sunIcon.setAttribute('stroke-width', '2');
      sunIcon.setAttribute('stroke-linecap', 'round');
      sunIcon.setAttribute('stroke-linejoin', 'round');
      sunIcon.setAttribute('class', 'lb-icon-sun');
      sunIcon.innerHTML = '<circle cx=\"12\" cy=\"12\" r=\"5\"/><line x1=\"12\" y1=\"1\" x2=\"12\" y2=\"3\"/><line x1=\"12\" y1=\"21\" x2=\"12\" y2=\"23\"/><line x1=\"4.22\" y1=\"4.22\" x2=\"5.64\" y2=\"5.64\"/><line x1=\"18.36\" y1=\"18.36\" x2=\"19.78\" y2=\"19.78\"/><line x1=\"1\" y1=\"12\" x2=\"3\" y2=\"12\"/><line x1=\"21\" y1=\"12\" x2=\"23\" y2=\"12\"/><line x1=\"4.22\" y1=\"19.78\" x2=\"5.64\" y2=\"18.36\"/><line x1=\"18.36\" y1=\"5.64\" x2=\"19.78\" y2=\"4.22\"/>';

      var moonIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      moonIcon.setAttribute('viewBox', '0 0 24 24');
      moonIcon.setAttribute('fill', 'none');
      moonIcon.setAttribute('stroke', 'currentColor');
      moonIcon.setAttribute('stroke-width', '2');
      moonIcon.setAttribute('stroke-linecap', 'round');
      moonIcon.setAttribute('stroke-linejoin', 'round');
      moonIcon.setAttribute('class', 'lb-icon-moon');
      moonIcon.innerHTML = '<path d=\"M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z\"/>';

      btn.appendChild(sunIcon);
      btn.appendChild(moonIcon);

      btn.addEventListener('click', function(){
        var cur = document.documentElement.getAttribute('data-lb-theme') === 'dark' ? 'dark' : 'light';
        var next = cur === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-lb-theme', next);
        try{ localStorage.setItem('lb-theme', next); }catch(e){}
      });
      document.body.appendChild(btn);
    }
})();
</script>\n";

        if (trim($customJs) !== '') {
            echo "\n<script id=\"loginbeautify-custom-js\">\n";
            echo $customJs . "\n";
            echo "</script>\n";
        }
    }

    // ================================================================
    // 登录页 CSS 输出
    // ================================================================

    /**
     * 输出登录页头部 CSS
     */
    private static function outputLoginHeaderCss()
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginOptions = $options->plugin('AdminBeautify');

        $themeMode = isset($pluginOptions->login_themeMode) ? (string) $pluginOptions->login_themeMode : 'auto';
        if (!in_array($themeMode, array('auto', 'light', 'dark'), true)) {
            $themeMode = 'auto';
        }

        $bgImage = trim((string) $pluginOptions->login_bgImage);
        $blurType = in_array($pluginOptions->login_blurType, array('none', 'filter', 'backdrop'), true) ? $pluginOptions->login_blurType : 'filter';

        $blurSize = (int) $pluginOptions->login_blurSize;
        if ($blurSize < 0) $blurSize = 0;
        if ($blurSize > 80) $blurSize = 80;

        $customCss = (string) $pluginOptions->login_customCss;

        // 颜色预设处理
        $preset = isset($pluginOptions->login_colorPreset) ? (string) $pluginOptions->login_colorPreset : 'purple';

        $colorPresets = array(
            'purple'   => array('#7d5260', '#9e7b8a'),
            'blue'     => array('#556270', '#7a8a9e'),
            'pink'     => array('#74565f', '#9e7a85'),
            'green'    => array('#55624c', '#7a8a6e'),
            'orange'   => array('#725a42', '#9e8062'),
            'red'      => array('#775654', '#a27a78'),
            'teal'     => array('#4a6363', '#6a8a8a'),
            'indigo'   => array('#5a4fd9', '#7b6ef2'),
            'sunset'   => array('#d38d1a', '#e06b3a'),
            'ocean'    => array('#0da0d8', '#39c1dd'),
            'forest'   => array('#2f7a3b', '#7fbf3a'),
            'lavender' => array('#8f6ee8', '#b89cfb'),
        );

        if ($preset === 'custom') {
            $primary = isset($pluginOptions->login_primaryColor) && trim((string) $pluginOptions->login_primaryColor) !== '' ? trim((string) $pluginOptions->login_primaryColor) : '#7d5260';
            $primary2 = isset($pluginOptions->login_primaryColor2) && trim((string) $pluginOptions->login_primaryColor2) !== '' ? trim((string) $pluginOptions->login_primaryColor2) : '#9e7b8a';
        } else {
            $colors = isset($colorPresets[$preset]) ? $colorPresets[$preset] : $colorPresets['purple'];
            $primary = $colors[0];
            $primary2 = $colors[1];
        }

        $bgCss = $bgImage !== '' ? "url(" . htmlspecialchars($bgImage, ENT_QUOTES, 'UTF-8') . ")" : "none";

        echo "\n" . '<style id="loginbeautify-style">' . "\n";
        ?>
:root{
--lb-primary:<?php echo htmlspecialchars($primary, ENT_QUOTES, 'UTF-8'); ?>;
--lb-primary2:<?php echo htmlspecialchars($primary2, ENT_QUOTES, 'UTF-8'); ?>;
--lb-surface:#f3f4f5;
--lb-surface-alpha:rgba(255,255,255,.8);
--lb-on-surface:#111827;
--lb-on-surface-muted:#4b5563;
--lb-border:rgba(0,0,0,.08);
--lb-shadow: 0 20px 40px -10px rgba(0,0,0,.15), 0 0 0 1px rgba(255,255,255,.4) inset;
--lb-radius: 20px;
--lb-input-bg: rgba(255,255,255,.8);
--lb-input-border: #e5e7eb;
--lb-bg-image: <?php echo $bgCss; ?>;
--lb-blur: <?php echo (int) $blurSize; ?>px;
}

@media (max-width: 575px) {
  body {
    padding-top: 0 !important;
  }
}

.typecho-login-wrap{
opacity: 0 !important;
position: absolute !important;
pointer-events: none !important;
}

html[data-lb-theme="dark"]{
--lb-surface:#111827;
--lb-surface-alpha:rgba(20,20,20,.75);
--lb-on-surface:#f9fafb;
--lb-on-surface-muted:#9ca3af;
--lb-border:rgba(255,255,255,.08);
--lb-shadow: 0 25px 50px -12px rgba(0,0,0,.6), 0 0 0 1px rgba(255,255,255,.05) inset;
--lb-input-bg: rgba(0,0,0,.2);
--lb-input-border: rgba(255,255,255,.1);
}

html{
transition: background-color .3s ease, color .3s ease;
}

body{
margin:0;
background: var(--lb-surface);
color: var(--lb-on-surface);
font-family: system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
transition: background-color .3s ease, color .3s ease;
}

.lb-wrap{
min-height:100vh;
display:flex;
align-items:center;
justify-content:center;
position:relative;
overflow:hidden;
}

.lb-bg{
position:absolute;
inset:0;
background-image: var(--lb-bg-image);
background-size: cover;
background-position: center;
background-repeat:no-repeat;
z-index:-2;
transform: scale(1.03);
}

.lb-bg-overlay{
position:absolute;
inset:0;
background: linear-gradient(180deg, rgba(0,0,0,.2), rgba(0,0,0,.4));
z-index:-1;
transition: background .3s ease;
}

html[data-lb-theme="light"] .lb-bg-overlay{
background: linear-gradient(180deg, rgba(255,255,255,0), rgba(255,255,255,0));
}

.lb-card{
width:min(400px, 94%);
background: var(--lb-surface-alpha);
color: var(--lb-on-surface);
border: 1px solid var(--lb-border);
border-radius: var(--lb-radius);
box-shadow: var(--lb-shadow);
padding: 32px 32px 28px;
transition: background-color .3s ease, border-color .3s ease, box-shadow .3s ease;
backdrop-filter: blur(20px);
-webkit-backdrop-filter: blur(20px);
}

<?php if ($blurType === 'backdrop') { ?>
.lb-card{
backdrop-filter: blur(var(--lb-blur));
-webkit-backdrop-filter: blur(var(--lb-blur));
}
<?php } ?>

<?php if ($blurType === 'filter') { ?>
.lb-bg{
filter: blur(var(--lb-blur));
}
<?php } ?>

.lb-head{
display:flex;
flex-direction:column;
align-items:center;
text-align:center;
margin-bottom: 20px;
}

.lb-title{
display:flex;
flex-direction:column;
gap:8px;
width:100%;
}

.lb-title .name{
font-size: 16px;
font-weight: 500;
color: var(--lb-on-surface-muted);
}

.lb-title .sub{
font-size: 24px;
font-weight: 800;
letter-spacing: -0.025em;
color: var(--lb-on-surface);
margin-bottom: 8px;
}

.lb-form .lb-field{
margin-top: 16px;
}

.lb-form label{
display:block;
font-size: 12px;
font-weight: 500;
color: var(--lb-on-surface-muted);
margin: 0 0 6px 1px;
}

.lb-form input[type="text"],
.lb-form input[type="password"],
.lb-form input[type="email"]{
width:100%;
box-sizing:border-box;
padding: 12px 14px;
border-radius: 10px;
border: 1px solid var(--lb-input-border);
background: var(--lb-input-bg);
color: var(--lb-on-surface);
font-size: 14px;
outline: none;
transition: all .2s ease;
}

html[data-lb-theme="dark"] .lb-form input[type="text"],
html[data-lb-theme="dark"] .lb-form input[type="password"]{
background: rgba(255,255,255,.06);
}

.lb-form input[type="text"]:focus,
.lb-form input[type="password"]:focus{
border-color: var(--lb-primary);
background: var(--lb-surface);
box-shadow: 0 0 0 3px color-mix(in srgb, var(--lb-primary) 15%, transparent);
}

@supports not (color: color-mix(in srgb, red, blue)) {
.lb-form input[type="text"]:focus,
.lb-form input[type="password"]:focus{
box-shadow: 0 0 0 4px rgba(103,80,164,.18);
}
html[data-lb-theme="dark"] .lb-form input[type="text"]:focus,
html[data-lb-theme="dark"] .lb-form input[type="password"]:focus{
box-shadow: 0 0 0 4px rgba(103,80,164,.25);
}
}

.lb-actions{
display:flex;
align-items:center;
justify-content:space-between;
gap:12px;
margin: 12px 0 6px;
}

.lb-remember{
font-size: 13px;
color: var(--lb-on-surface2);
display:flex;
align-items:center;
gap:8px;
}

.lb-remember input{ accent-color: var(--lb-primary); }

.lb-submit input[type="submit"],
.lb-submit button{
width:100%;
margin-top: 20px;
border:0;
cursor:pointer;
padding: 12px 16px;
border-radius: 12px;
font-size: 14px;
background: linear-gradient(135deg, var(--lb-primary), var(--lb-primary2));
color:#fff;
font-weight:600;
letter-spacing:0.5px;
box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);
transition: all .2s ease;
}

.lb-submit input[type="submit"]:hover{
filter: brightness(1.08);
transform: translateY(-1px);
box-shadow: 0 10px 15px -3px rgba(0,0,0,.15);
}

@supports not (color: color-mix(in srgb, red, blue)) {
.lb-submit input[type="submit"]:hover{
box-shadow: 0 10px 24px rgba(103,80,164,.30);
}
}

.message.popup{
position: fixed !important;
top: 20px !important;
left: 50% !important;
transform: translateX(-50%) !important;
width: auto !important;
max-width: calc(100vw - 40px) !important;
min-width: 280px !important;
border-radius: 10px !important;
padding: 0 !important;
margin: 0 !important;
background: none !important;
border: none !important;
box-shadow: none !important;
backdrop-filter: blur(10px) !important;
-webkit-backdrop-filter: blur(10px) !important;
animation: lb-slide-down 0.3s ease-out !important;
z-index: 9999 !important;
}

.notice{
background:none!important;
}

@keyframes lb-slide-down {
from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
to { opacity: 1; transform: translateX(-50%) translateY(0); }
}

.message.popup ul{
margin: 0 !important;
padding: 0 !important;
list-style: none !important;
}

.message.popup ul li{
padding: 14px 18px !important;
margin: 5px !important;
font-size: 14px !important;
line-height: 1.5 !important;
color: var(--lb-on-surface) !important;
display: flex !important;
align-items: center !important;
gap: 10px !important;
}

.message.popup ul li:before{
content: '⚠' !important;
font-size: 18px !important;
display: inline-block !important;
}

.message.popup.notice ul li{
background: linear-gradient(135deg, #f59e0b, #ef4444) !important;
color: #fff !important;
border-radius: 14px !important;
}

.message.popup.notice ul li:before{
content: '⚠' !important;
font-weight: bold !important;
}

.message.popup.success ul li{
background: linear-gradient(135deg, #10b981, #059669) !important;
color: #fff !important;
border-radius: 14px !important;
}

.message.popup.success ul li:before{
content: '✓' !important;
font-weight: bold !important;
}

.message.popup.error ul li{
background: linear-gradient(135deg, #dc2626, #ef4444) !important;
color: #fff !important;
border-radius: 14px !important;
}

.message.popup.error ul li:before{
content: '✕' !important;
font-weight: bold !important;
}

@media (max-width: 480px) {
.message.popup{
top: 16px !important;
max-width: calc(100vw - 32px) !important;
min-width: 260px !important;
}
.message.popup ul li{
padding: 12px 16px !important;
font-size: 13px !important;
}
}

.lb-theme-toggle{
position: fixed;
right: 20px;
top: 20px;
width: 48px;
height: 48px;
border-radius: 50%;
border: 1px solid var(--lb-outline);
background: var(--lb-surface-alpha);
color: var(--lb-on-surface);
backdrop-filter: blur(10px);
-webkit-backdrop-filter: blur(10px);
box-shadow: 0 4px 12px rgba(0,0,0,.12);
cursor:pointer;
transition: all .25s cubic-bezier(0.4, 0, 0.2, 1);
display:flex;
align-items:center;
justify-content:center;
padding:0;
z-index:1000;
}

.lb-theme-toggle:hover{
transform: translateY(-2px) scale(1.05);
box-shadow: 0 8px 20px rgba(0,0,0,.18);
border-color: var(--lb-primary);
}

.lb-theme-toggle:active {
transform: translateY(0) scale(0.98);
box-shadow: 0 2px 8px rgba(0,0,0,.12);
}

.lb-theme-toggle svg{
width: 20px;
height: 20px;
transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
}

.lb-theme-toggle .lb-icon-sun,
.lb-theme-toggle .lb-icon-moon{
position: absolute;
transition: opacity .3s ease, transform .3s cubic-bezier(0.4, 0, 0.2, 1);
}

html[data-lb-theme="light"] .lb-theme-toggle .lb-icon-sun{
opacity: 0;
transform: rotate(-90deg) scale(0.8);
}

html[data-lb-theme="light"] .lb-theme-toggle .lb-icon-moon{
opacity: 1;
transform: rotate(0) scale(1);
}

html[data-lb-theme="dark"] .lb-theme-toggle .lb-icon-sun{
opacity: 1;
transform: rotate(0) scale(1);
}

html[data-lb-theme="dark"] .lb-theme-toggle .lb-icon-moon{
opacity: 0;
transform: rotate(90deg) scale(0.8);
}

@media (max-width: 480px) {
.lb-theme-toggle{
right: 16px;
top: 16px;
width: 44px;
height: 44px;
}
.lb-theme-toggle svg{
width: 18px;
height: 18px;
}
}

.lb-remember{
  display:none !important;
}

.lb-footer-theme{
text-align: center;
margin-top: 28px;
font-size: 11.5px;
font-weight: 500;
letter-spacing: 0.02em;
color: var(--lb-on-surface-muted);
opacity: 0.55;
transition: opacity .3s ease;
line-height: 1.9;
}

.lb-footer-theme:hover{
opacity: 0.95;
}

.lb-footer-theme a{
color: inherit;
font-weight: 600;
text-decoration: none;
padding-bottom: 2px;
border-bottom: 1px solid transparent;
transition: all .2s ease;
}

.lb-footer-theme a:hover{
color: var(--lb-primary);
border-bottom-color: color-mix(in srgb, var(--lb-primary) 40%, transparent);
}

.lb-footer-links{
display: block;
font-size: 11px;
font-weight: 400;
opacity: 0.75;
margin-top: 2px;
}

.lb-hide { display:none !important; }

<?php
        if (trim($customCss) !== '') {
            echo "\n/* --- custom login css --- */\n";
            echo $customCss . "\n";
        }

        echo "</style>\n";

        $jsThemeMode = self::jsString($themeMode);
        echo "\n<script id=\"loginbeautify-theme-init\">
(function(){
    try{
      var mode = {$jsThemeMode};
      var saved = localStorage.getItem('lb-theme');
      var dark = false;

      if (saved === 'light' || saved === 'dark') {
        dark = saved === 'dark';
      } else if (mode === 'dark') {
        dark = true;
      } else if (mode === 'light') {
        dark = false;
      } else {
        dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      }

      document.documentElement.setAttribute('data-lb-theme', dark ? 'dark' : 'light');
    }catch(e){}
})();
</script>\n";
    }

    // ================================================================
    // 登录页预览（config 页面用）
    // ================================================================

    /**
     * 渲染登录页设置预览
     */
    private static function renderLoginPreview()
    {
        try {
            $opt = Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautify');
            $preset = isset($opt->login_colorPreset) ? (string) $opt->login_colorPreset : 'purple';
            $pc1 = isset($opt->login_primaryColor) ? (string) $opt->login_primaryColor : '#7d5260';
            $pc2 = isset($opt->login_primaryColor2) ? (string) $opt->login_primaryColor2 : '#9e7b8a';
            $bgUrl = isset($opt->login_bgImage) ? (string) $opt->login_bgImage : '';
            $blurTypeVal = isset($opt->login_blurType) ? (string) $opt->login_blurType : 'filter';
            $blurSizeVal = isset($opt->login_blurSize) ? (int) $opt->login_blurSize : 12;
        } catch (Exception $e) {
            $preset = 'purple';
            $pc1 = '#7d5260';
            $pc2 = '#9e7b8a';
            $bgUrl = '';
            $blurTypeVal = 'filter';
            $blurSizeVal = 12;
        }

        echo '<style>
#lb-preview{margin-top:16px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.08)}
#lb-preview .lbpv-head{padding:12px 16px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;background:#fff}
#lb-preview .lbpv-head strong{font-size:14px;color:#374151;font-weight:600}
#lb-preview .lbpv-head .lbpv-left{display:flex;align-items:center;gap:12px}
#lb-preview .lbpv-head .lbpv-theme-btns{display:flex;gap:6px;background:#f3f4f6;padding:3px;border-radius:8px}
#lb-preview .lbpv-theme-btns button{padding:4px 12px;border:none;border-radius:6px;background:transparent;cursor:pointer;font-size:12px;font-weight:500;color:#6b7280;transition:all .2s}
#lb-preview .lbpv-theme-btns button:hover{color:#374151}
#lb-preview .lbpv-theme-btns button.active{background:#fff;color:#000;box-shadow:0 1px 3px rgba(0,0,0,.1)}
#lb-preview .lbpv-refresh{padding:6px 12px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;cursor:pointer;font-size:12px;color:#6b7280;transition:all .2s;display:flex;align-items:center;gap:6px}
#lb-preview .lbpv-refresh:hover{background:#f9fafb;color:#374151;border-color:#d1d5db}
#lb-preview .lbpv-refresh:active{transform:scale(0.96)}
#lb-preview .lbpv-refresh svg{width:14px;height:14px;transition:transform .3s}
#lb-preview .lbpv-refresh.spinning svg{animation:lb-spin .6s linear}
@keyframes lb-spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
#lb-preview .lbpv-body{padding:40px 20px;background:#f9fafb;min-height:420px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;transition:background .3s}
#lb-preview .lbpv-bg{position:absolute;inset:0;background-size:cover;background-position:center;z-index:0;transform:scale(1.03);transition:all .3s}
#lb-preview .lbpv-bg-overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.2),rgba(0,0,0,.4));z-index:1;transition:background .3s}
#lb-preview[data-theme="light"] .lbpv-bg-overlay{background:linear-gradient(180deg,rgba(255,255,255,.2),rgba(255,255,255,.4))}
#lb-preview .lbpv-card{position:relative;z-index:2;max-width:380px;width:100%;border-radius:20px;border:1px solid rgba(255,255,255,.6);background:rgba(255,255,255,.8);padding:32px 28px;box-shadow:0 20px 40px -10px rgba(0,0,0,.15), 0 0 0 1px rgba(255,255,255,.4) inset;transition:all .3s;backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);}
#lb-preview[data-theme="dark"] .lbpv-card{background:rgba(20,20,20,.75);border-color:rgba(255,255,255,.08);box-shadow:0 25px 50px -12px rgba(0,0,0,.6), 0 0 0 1px rgba(255,255,255,.05) inset;}
#lb-preview[data-theme="dark"] .lbpv-body{background:#111827}
#lb-preview .lbpv-title{font-size:16px;font-weight:500;text-align:center;margin-bottom:6px;color:#4b5563;transition:color .3s}
#lb-preview[data-theme="dark"] .lbpv-title{color:#9ca3af}
#lb-preview .lbpv-sub{font-size:24px;font-weight:800;color:#111827;text-align:center;margin-bottom:28px;transition:color .3s;letter-spacing:-0.025em}
#lb-preview[data-theme="dark"] .lbpv-sub{color:#f9fafb}
#lb-preview .lbpv-field{margin-bottom:16px}
#lb-preview .lbpv-label{display:block;font-size:12px;color:#6b7280;margin-bottom:6px;font-weight:500}
#lb-preview[data-theme="dark"] .lbpv-label{color:#9ca3af}
#lb-preview .lbpv-input{width:100%;box-sizing:border-box;padding:12px 14px;border-radius:10px;border:1px solid #e5e7eb;background:rgba(255,255,255,.8);font-size:14px;outline:none;transition:all .2s;color:#1f2937}
#lb-preview[data-theme="dark"] .lbpv-input{background:rgba(0,0,0,.2);border-color:rgba(255,255,255,.1);color:#e5e7eb}
#lb-preview .lbpv-btn{width:100%;padding:12px;border:0;border-radius:12px;color:#fff;font-weight:600;font-size:14px;cursor:pointer;transition:all .2s;margin-top:8px;box-shadow:0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06)}
#lb-preview .lbpv-btn:hover{filter:brightness(1.08);transform:translateY(-1px);box-shadow:0 10px 15px -3px rgba(0,0,0,.15)}
#lb-preview .lbpv-btn:active{transform:translateY(0);filter:brightness(0.95)}
</style>';

        echo '<div id="lb-preview" data-theme="light">
  <div class="lbpv-head">
    <div class="lbpv-left">
      <strong>🔐 登录页预览</strong>
      <button type="button" class="lbpv-refresh" id="lbpv-refresh" title="刷新预览">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
        </svg>
        刷新
      </button>
    </div>
    <div class="lbpv-theme-btns">
      <button type="button" data-theme="light" class="lbpv-theme-light active">☀️ 亮色</button>
      <button type="button" data-theme="dark" class="lbpv-theme-dark">🌙 暗色</button>
    </div>
  </div>
  <div class="lbpv-body">
    <div class="lbpv-bg" id="lbpv-bg"></div>
    <div class="lbpv-bg-overlay"></div>
    <div class="lbpv-card">
      <div class="lbpv-title" id="lbpv-title">我的博客</div>
      <div class="lbpv-sub">登录</div>
      <div class="lbpv-field">
        <label class="lbpv-label">用户名/邮箱</label>
        <input type="text" class="lbpv-input" value="user" readonly>
      </div>
      <div class="lbpv-field">
        <label class="lbpv-label">密码</label>
        <input type="password" class="lbpv-input" value="password" readonly>
      </div>
      <button class="lbpv-btn" id="lbpv-btn" type="button">登录</button>
    </div>
  </div>
</div>';

        echo '<script>
(function(){
    var colorPresets = {
        purple: ["#7d5260", "#9e7b8a"],
        blue: ["#556270", "#7a8a9e"],
        pink: ["#74565f", "#9e7a85"],
        green: ["#55624c", "#7a8a6e"],
        orange: ["#725a42", "#9e8062"],
        red: ["#775654", "#a27a78"],
        teal: ["#4a6363", "#6a8a8a"],
        indigo: ["#5a4fd9", "#7b6ef2"],
        sunset: ["#d38d1a", "#e06b3a"],
        ocean: ["#0da0d8", "#39c1dd"],
        forest: ["#2f7a3b", "#7fbf3a"],
        lavender: ["#8f6ee8", "#b89cfb"]
    };

  function val(name){
    var el = document.querySelector(\'[name="\' + name + \'"]\');
    if (!el) return "";
    if (el.type === "radio") {
      var c = document.querySelector(\'[name="\' + name + \'"]:checked\');
      return c ? c.value : "";
    }
    return (el.value || "").trim();
  }

  var btn = document.getElementById("lbpv-btn");
  var title = document.getElementById("lbpv-title");
  var bg = document.getElementById("lbpv-bg");
  var preview = document.getElementById("lb-preview");
  var themeButtons = preview.querySelectorAll(".lbpv-theme-btns button");
  var refreshBtn = document.getElementById("lbpv-refresh");

  function normalizeColor(s, fallback){
    s = (s || "").trim();
    return s ? s : fallback;
  }

  function getCurrentColors(){
    var preset = val("login_colorPreset") || "purple";
    var c1, c2;
    if (preset === "custom") {
      c1 = normalizeColor(val("login_primaryColor"), ' . json_encode($pc1) . ');
      c2 = normalizeColor(val("login_primaryColor2"), ' . json_encode($pc2) . ');
    } else {
      var colors = colorPresets[preset] || colorPresets.purple;
      c1 = colors[0];
      c2 = colors[1];
    }
    return {c1: c1, c2: c2};
  }

  function updateAllButtonColors(){
    var colors = getCurrentColors();
    var gradient = "linear-gradient(135deg," + colors.c1 + "," + colors.c2 + ")";
    btn.style.background = gradient;
    var inputs = preview.querySelectorAll(".lbpv-input");
    inputs.forEach(function(inp){ inp.style.caretColor = colors.c1; });
    themeButtons.forEach(function(b){
      if (b.classList.contains("active")) {
        b.style.background = gradient;
        b.style.color = "#fff";
      } else {
        b.style.background = "#fff";
        b.style.color = "";
      }
    });
  }

  function render(){
    var showName = val("login_showSiteName") || "1";
    var bgUrl = val("login_bgImage") || "";
    var blurType = val("login_blurType") || "filter";
    var blurSize = parseInt(val("login_blurSize") || "12");
    if (isNaN(blurSize) || blurSize < 0) blurSize = 0;
    if (blurSize > 80) blurSize = 80;

    updateAllButtonColors();
    title.style.display = (showName === "1") ? "block" : "none";

    var overlay = preview.querySelector(".lbpv-bg-overlay");
    var body = preview.querySelector(".lbpv-body");

    if (bgUrl) {
      bg.style.backgroundImage = "url(\'" + bgUrl + "\')";
      bg.style.display = "block";
      overlay.style.display = "block";
      var currentTheme = preview.getAttribute("data-theme");
      if (currentTheme === "dark") {
        overlay.style.background = "linear-gradient(180deg,rgba(0,0,0,.3),rgba(0,0,0,.5))";
      } else {
        overlay.style.background = "transparent";
      }
      body.style.background = "transparent";
    } else {
      bg.style.backgroundImage = "none";
      bg.style.display = "none";
      overlay.style.display = "none";
      var currentTheme = preview.getAttribute("data-theme");
      if (currentTheme === "dark") {
        body.style.background = "#111827";
      } else {
        body.style.background = "#f9fafb";
      }
    }

    bg.style.filter = "";
    var card = preview.querySelector(".lbpv-card");
    card.style.backdropFilter = "blur(20px)";
    card.style.webkitBackdropFilter = "blur(20px)";

    if (bgUrl && blurType === "filter") {
      bg.style.filter = "blur(" + blurSize + "px)";
    } else if (bgUrl && blurType === "backdrop") {
      var size = Math.max(20, blurSize);
      card.style.backdropFilter = "blur(" + size + "px)";
      card.style.webkitBackdropFilter = "blur(" + size + "px)";
    }
  }

  refreshBtn.addEventListener("click", function(){
    this.classList.add("spinning");
    var self = this;
    setTimeout(function(){ self.classList.remove("spinning"); }, 600);
    render();
  });

  themeButtons.forEach(function(themeBtn){
    themeBtn.addEventListener("click", function(){
      var theme = this.getAttribute("data-theme");
      preview.setAttribute("data-theme", theme);
      themeButtons.forEach(function(b){ b.classList.remove("active"); });
      this.classList.add("active");
      render();
    });
  });

  setTimeout(function(){ render(); }, 500);
})();
</script>';
    }

    /**
     * 获取颜色方案
     */
    private static function getColorScheme($scheme)
    {
        $schemes = array(
            'purple' => array(
                '--md-primary'           => '#7D5260',
                '--md-on-primary'        => '#FFFFFF',
                '--md-primary-container' => '#FFD8E4',
                '--md-on-primary-container' => '#21005D',
                '--md-secondary'         => '#625B71',
                '--md-on-secondary'      => '#FFFFFF',
                '--md-secondary-container' => '#E8DEF8',
                '--md-on-secondary-container' => '#1D192B',
                '--md-tertiary'          => '#7D5260',
                '--md-surface'           => '#FFFBFE',
                '--md-surface-dim'       => '#DED8E1',
                '--md-surface-bright'    => '#FFFBFE',
                '--md-surface-container-lowest'  => '#FFFFFF',
                '--md-surface-container-low'     => '#F7F2FA',
                '--md-surface-container'         => '#F3EDF7',
                '--md-surface-container-high'    => '#ECE6F0',
                '--md-surface-container-highest' => '#E6E0E9',
                '--md-on-surface'        => '#1C1B1F',
                '--md-on-surface-variant' => '#49454F',
                '--md-outline'           => '#79747E',
                '--md-outline-variant'   => '#CAC4D0',
                '--md-error'             => '#B3261E',
                '--md-on-error'          => '#FFFFFF',
                '--md-error-container'   => '#F9DEDC',
                '--md-on-error-container' => '#410E0B',
                // Dark variants
                '--md-dark-primary'           => '#D0BCFF',
                '--md-dark-on-primary'        => '#381E72',
                '--md-dark-primary-container' => '#4F378B',
                '--md-dark-on-primary-container' => '#EADDFF',
                '--md-dark-secondary'         => '#CCC2DC',
                '--md-dark-surface'           => '#1C1B1F',
                '--md-dark-surface-dim'       => '#1C1B1F',
                '--md-dark-surface-bright'    => '#3B383E',
                '--md-dark-surface-container-lowest'  => '#0F0D13',
                '--md-dark-surface-container-low'     => '#211F26',
                '--md-dark-surface-container'         => '#2B2930',
                '--md-dark-surface-container-high'    => '#36343B',
                '--md-dark-surface-container-highest' => '#484649',
                '--md-dark-on-surface'        => '#E6E1E5',
                '--md-dark-on-surface-variant' => '#CAC4D0',
                '--md-dark-outline'           => '#938F99',
                '--md-dark-outline-variant'   => '#49454F',
                '--md-dark-error'             => '#F2B8B5',
            ),
            'blue' => array(
                '--md-primary'           => '#556270',
                '--md-on-primary'        => '#FFFFFF',
                '--md-primary-container' => '#D9E2FF',
                '--md-on-primary-container' => '#001A41',
                '--md-secondary'         => '#565E71',
                '--md-on-secondary'      => '#FFFFFF',
                '--md-secondary-container' => '#DAE2F9',
                '--md-on-secondary-container' => '#131C2B',
                '--md-tertiary'          => '#715573',
                '--md-surface'           => '#FDFBFF',
                '--md-surface-dim'       => '#DBD9DD',
                '--md-surface-bright'    => '#FDFBFF',
                '--md-surface-container-lowest'  => '#FFFFFF',
                '--md-surface-container-low'     => '#F5F3F7',
                '--md-surface-container'         => '#EFEDF1',
                '--md-surface-container-high'    => '#EAE7EC',
                '--md-surface-container-highest' => '#E4E2E6',
                '--md-on-surface'        => '#1B1B1F',
                '--md-on-surface-variant' => '#44474F',
                '--md-outline'           => '#74777F',
                '--md-outline-variant'   => '#C4C6D0',
                '--md-error'             => '#BA1A1A',
                '--md-on-error'          => '#FFFFFF',
                '--md-error-container'   => '#FFDAD6',
                '--md-on-error-container' => '#410E0B',
                '--md-dark-primary'           => '#ADC6FF',
                '--md-dark-on-primary'        => '#002E69',
                '--md-dark-primary-container' => '#004494',
                '--md-dark-on-primary-container' => '#D8E2FF',
                '--md-dark-secondary'         => '#BEC6DC',
                '--md-dark-surface'           => '#1B1B1F',
                '--md-dark-surface-dim'       => '#1B1B1F',
                '--md-dark-surface-bright'    => '#3A3A3F',
                '--md-dark-surface-container-lowest'  => '#0E0E13',
                '--md-dark-surface-container-low'     => '#1F1F24',
                '--md-dark-surface-container'         => '#2A2A2F',
                '--md-dark-surface-container-high'    => '#35353A',
                '--md-dark-surface-container-highest' => '#464649',
                '--md-dark-on-surface'        => '#E4E2E6',
                '--md-dark-on-surface-variant' => '#C4C6D0',
                '--md-dark-outline'           => '#8E9099',
                '--md-dark-outline-variant'   => '#44474F',
                '--md-dark-error'             => '#FFB4AB',
            ),
            'teal' => array(
                '--md-primary'           => '#4A6363',
                '--md-on-primary'        => '#FFFFFF',
                '--md-primary-container' => '#CCE8E7',
                '--md-on-primary-container' => '#002020',
                '--md-secondary'         => '#4A6363',
                '--md-on-secondary'      => '#FFFFFF',
                '--md-secondary-container' => '#CCE8E7',
                '--md-on-secondary-container' => '#051F1F',
                '--md-tertiary'          => '#4B607C',
                '--md-surface'           => '#FAFDFC',
                '--md-surface-dim'       => '#DBE4E3',
                '--md-surface-bright'    => '#FAFDFC',
                '--md-surface-container-lowest'  => '#FFFFFF',
                '--md-surface-container-low'     => '#F2FAF9',
                '--md-surface-container'         => '#EDF5F3',
                '--md-surface-container-high'    => '#E7EFEE',
                '--md-surface-container-highest' => '#E1E9E8',
                '--md-on-surface'        => '#191C1C',
                '--md-on-surface-variant' => '#3F4948',
                '--md-outline'           => '#6F7979',
                '--md-outline-variant'   => '#BEC9C8',
                '--md-error'             => '#BA1A1A',
                '--md-on-error'          => '#FFFFFF',
                '--md-error-container'   => '#FFDAD6',
                '--md-on-error-container' => '#410E0B',
                '--md-dark-primary'           => '#4CDADA',
                '--md-dark-on-primary'        => '#003737',
                '--md-dark-primary-container' => '#004F50',
                '--md-dark-on-primary-container' => '#6FF7F6',
                '--md-dark-secondary'         => '#B0CCCB',
                '--md-dark-surface'           => '#191C1C',
                '--md-dark-surface-dim'       => '#191C1C',
                '--md-dark-surface-bright'    => '#3A3D3D',
                '--md-dark-surface-container-lowest'  => '#0C0F0F',
                '--md-dark-surface-container-low'     => '#1F2222',
                '--md-dark-surface-container'         => '#2A2D2D',
                '--md-dark-surface-container-high'    => '#353838',
                '--md-dark-surface-container-highest' => '#464949',
                '--md-dark-on-surface'        => '#E1E9E8',
                '--md-dark-on-surface-variant' => '#BEC9C8',
                '--md-dark-outline'           => '#899392',
                '--md-dark-outline-variant'   => '#3F4948',
                '--md-dark-error'             => '#FFB4AB',
            ),
            'green' => array(
                '--md-primary'           => '#55624C',
                '--md-on-primary'        => '#FFFFFF',
                '--md-primary-container' => '#D9E7CB',
                '--md-on-primary-container' => '#042100',
                '--md-secondary'         => '#55624C',
                '--md-on-secondary'      => '#FFFFFF',
                '--md-secondary-container' => '#D9E7CB',
                '--md-on-secondary-container' => '#131F0D',
                '--md-tertiary'          => '#386666',
                '--md-surface'           => '#FDFDF5',
                '--md-surface-dim'       => '#DBDBD4',
                '--md-surface-bright'    => '#FDFDF5',
                '--md-surface-container-lowest'  => '#FFFFFF',
                '--md-surface-container-low'     => '#F5F5ED',
                '--md-surface-container'         => '#F0F0E8',
                '--md-surface-container-high'    => '#EAEAE2',
                '--md-surface-container-highest' => '#E4E4DD',
                '--md-on-surface'        => '#1A1C18',
                '--md-on-surface-variant' => '#44483E',
                '--md-outline'           => '#74796D',
                '--md-outline-variant'   => '#C4C8BB',
                '--md-error'             => '#BA1A1A',
                '--md-on-error'          => '#FFFFFF',
                '--md-error-container'   => '#FFDAD6',
                '--md-on-error-container' => '#410E0B',
                '--md-dark-primary'           => '#9CD67D',
                '--md-dark-on-primary'        => '#0E3900',
                '--md-dark-primary-container' => '#215107',
                '--md-dark-on-primary-container' => '#B7F397',
                '--md-dark-secondary'         => '#BDC9B0',
                '--md-dark-surface'           => '#1A1C18',
                '--md-dark-surface-dim'       => '#1A1C18',
                '--md-dark-surface-bright'    => '#3A3D36',
                '--md-dark-surface-container-lowest'  => '#0D0F0B',
                '--md-dark-surface-container-low'     => '#1F2220',
                '--md-dark-surface-container'         => '#2A2D28',
                '--md-dark-surface-container-high'    => '#353833',
                '--md-dark-surface-container-highest' => '#464944',
                '--md-dark-on-surface'        => '#E4E4DD',
                '--md-dark-on-surface-variant' => '#C4C8BB',
                '--md-dark-outline'           => '#8E9285',
                '--md-dark-outline-variant'   => '#44483E',
                '--md-dark-error'             => '#FFB4AB',
            ),
            'orange' => array(
                '--md-primary'           => '#725A42',
                '--md-on-primary'        => '#FFFFFF',
                '--md-primary-container' => '#FFDCBE',
                '--md-on-primary-container' => '#2C1600',
                '--md-secondary'         => '#725A42',
                '--md-on-secondary'      => '#FFFFFF',
                '--md-secondary-container' => '#FDDDBF',
                '--md-on-secondary-container' => '#2A1706',
                '--md-tertiary'          => '#586339',
                '--md-surface'           => '#FFFBFF',
                '--md-surface-dim'       => '#E0D9D1',
                '--md-surface-bright'    => '#FFFBFF',
                '--md-surface-container-lowest'  => '#FFFFFF',
                '--md-surface-container-low'     => '#FAF3EB',
                '--md-surface-container'         => '#F4EDE5',
                '--md-surface-container-high'    => '#EEE8E0',
                '--md-surface-container-highest' => '#E8E2DA',
                '--md-on-surface'        => '#201B13',
                '--md-on-surface-variant' => '#51453A',
                '--md-outline'           => '#837568',
                '--md-outline-variant'   => '#D5C3B5',
                '--md-error'             => '#BA1A1A',
                '--md-on-error'          => '#FFFFFF',
                '--md-error-container'   => '#FFDAD6',
                '--md-on-error-container' => '#410E0B',
                '--md-dark-primary'           => '#FFB870',
                '--md-dark-on-primary'        => '#4A2800',
                '--md-dark-primary-container' => '#6A3C00',
                '--md-dark-on-primary-container' => '#FFDCBE',
                '--md-dark-secondary'         => '#DFBFA3',
                '--md-dark-surface'           => '#201B13',
                '--md-dark-surface-dim'       => '#201B13',
                '--md-dark-surface-bright'    => '#423A31',
                '--md-dark-surface-container-lowest'  => '#140F08',
                '--md-dark-surface-container-low'     => '#29231B',
                '--md-dark-surface-container'         => '#332D25',
                '--md-dark-surface-container-high'    => '#3E3830',
                '--md-dark-surface-container-highest' => '#4A443B',
                '--md-dark-on-surface'        => '#ECE0D4',
                '--md-dark-on-surface-variant' => '#D5C3B5',
                '--md-dark-outline'           => '#9D8E81',
                '--md-dark-outline-variant'   => '#51453A',
                '--md-dark-error'             => '#FFB4AB',
            ),
            'pink' => array(
                '--md-primary'           => '#74565F',
                '--md-on-primary'        => '#FFFFFF',
                '--md-primary-container' => '#FFD9E3',
                '--md-on-primary-container' => '#3E001E',
                '--md-secondary'         => '#74565F',
                '--md-on-secondary'      => '#FFFFFF',
                '--md-secondary-container' => '#FFD9E3',
                '--md-on-secondary-container' => '#2B151C',
                '--md-tertiary'          => '#7C5635',
                '--md-surface'           => '#FFFBFF',
                '--md-surface-dim'       => '#E4D6DB',
                '--md-surface-bright'    => '#FFFBFF',
                '--md-surface-container-lowest'  => '#FFFFFF',
                '--md-surface-container-low'     => '#FEF0F4',
                '--md-surface-container'         => '#F9EAEF',
                '--md-surface-container-high'    => '#F3E5E9',
                '--md-surface-container-highest' => '#EDDFE4',
                '--md-on-surface'        => '#201A1C',
                '--md-on-surface-variant' => '#524347',
                '--md-outline'           => '#847377',
                '--md-outline-variant'   => '#D5C2C6',
                '--md-error'             => '#BA1A1A',
                '--md-on-error'          => '#FFFFFF',
                '--md-error-container'   => '#FFDAD6',
                '--md-on-error-container' => '#410E0B',
                '--md-dark-primary'           => '#FFB0CA',
                '--md-dark-on-primary'        => '#5E1133',
                '--md-dark-primary-container' => '#7C294A',
                '--md-dark-on-primary-container' => '#FFD9E3',
                '--md-dark-secondary'         => '#E2BDC7',
                '--md-dark-surface'           => '#201A1C',
                '--md-dark-surface-dim'       => '#201A1C',
                '--md-dark-surface-bright'    => '#3F383A',
                '--md-dark-surface-container-lowest'  => '#150F11',
                '--md-dark-surface-container-low'     => '#29222B',
                '--md-dark-surface-container'         => '#332C2F',
                '--md-dark-surface-container-high'    => '#3E373A',
                '--md-dark-surface-container-highest' => '#4A4345',
                '--md-dark-on-surface'        => '#EDDFE4',
                '--md-dark-on-surface-variant' => '#D5C2C6',
                '--md-dark-outline'           => '#9E8C90',
                '--md-dark-outline-variant'   => '#524347',
                '--md-dark-error'             => '#FFB4AB',
            ),
            'red' => array(
                '--md-primary'           => '#775654',
                '--md-on-primary'        => '#FFFFFF',
                '--md-primary-container' => '#FFDAD7',
                '--md-on-primary-container' => '#410005',
                '--md-secondary'         => '#775654',
                '--md-on-secondary'      => '#FFFFFF',
                '--md-secondary-container' => '#FFDAD7',
                '--md-on-secondary-container' => '#2C1514',
                '--md-tertiary'          => '#725B2E',
                '--md-surface'           => '#FFFBFF',
                '--md-surface-dim'       => '#E3D6D5',
                '--md-surface-bright'    => '#FFFBFF',
                '--md-surface-container-lowest'  => '#FFFFFF',
                '--md-surface-container-low'     => '#FDF0EF',
                '--md-surface-container'         => '#F8EAEA',
                '--md-surface-container-high'    => '#F2E4E3',
                '--md-surface-container-highest' => '#ECDEDE',
                '--md-on-surface'        => '#201A19',
                '--md-on-surface-variant' => '#534342',
                '--md-outline'           => '#857372',
                '--md-outline-variant'   => '#D8C2C0',
                '--md-error'             => '#BA1A1A',
                '--md-on-error'          => '#FFFFFF',
                '--md-error-container'   => '#FFDAD6',
                '--md-on-error-container' => '#410E0B',
                '--md-dark-primary'           => '#FFB3AD',
                '--md-dark-on-primary'        => '#68000E',
                '--md-dark-primary-container' => '#930016',
                '--md-dark-on-primary-container' => '#FFDAD7',
                '--md-dark-secondary'         => '#E7BDB9',
                '--md-dark-surface'           => '#201A19',
                '--md-dark-surface-dim'       => '#201A19',
                '--md-dark-surface-bright'    => '#413735',
                '--md-dark-surface-container-lowest'  => '#140E0D',
                '--md-dark-surface-container-low'     => '#291F1E',
                '--md-dark-surface-container'         => '#332928',
                '--md-dark-surface-container-high'    => '#3E3433',
                '--md-dark-surface-container-highest' => '#4A3F3E',
                '--md-dark-on-surface'        => '#ECDEDE',
                '--md-dark-on-surface-variant' => '#D8C2C0',
                '--md-dark-outline'           => '#A08C8B',
                '--md-dark-outline-variant'   => '#534342',
                '--md-dark-error'             => '#FFB4AB',
            ),
        );

        return isset($schemes[$scheme]) ? $schemes[$scheme] : $schemes['purple'];
    }
}
