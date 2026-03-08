<?php
/**
 * Admin Beautify - 后台管理界面美化插件，包含登录界面美化 (原LoginBeautify)，Material Design 3风格
 *
 * @package AdminBeautify
 * @author LHL
 * @version 2.0.1
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
        $abVer = '2.0.1';

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
                <button id="ab-btn-update" type="button" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;background:rgba(255,255,255,.22);color:#fff;border-radius:20px;font-size:13px;font-weight:500;border:1px solid rgba(255,255,255,.35);backdrop-filter:blur(6px);transition:background .2s;cursor:pointer;text-shadow:0 1px 2px rgba(0,0,0,.2)" onmouseover="this.style.background=\'rgba(255,255,255,.35)\'" onmouseout="this.style.background=\'rgba(255,255,255,.22)\'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                    检查更新
                </button>
            </div>
        </div>';

        // ================================================================
        // ====== 管理后台设置 ======
        // ================================================================
        echo '<div id="ab-section-admin" style="margin:24px 0 12px;padding:12px 0;border-bottom:2px solid ' . $abC1 . '">
            <h3 style="margin:0;font-size:18px;font-weight:600;color:' . $abC1 . ';display:flex;align-items:center;gap:8px">
                <span style="font-size:20px">⚙️</span> 管理后台设置
            </h3>
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

        // ================================================================
        // ====== 登录页设置 ======
        // ================================================================
        echo '<div id="ab-section-login" style="margin:32px 0 12px;padding:12px 0;border-bottom:2px solid ' . $abC2 . '">
            <h3 style="margin:0;font-size:18px;font-weight:600;color:' . $abC2 . ';display:flex;align-items:center;gap:8px">
                <span style="font-size:20px">🔐</span> 登录页设置
            </h3>
        </div>';

        echo '<div id="ab-section-login-tip" style="margin:12px 0 16px;padding:14px 16px;background:#f0f9ff;border:1px solid #bfdbfe;border-radius:10px">
            <div style="display:flex;align-items:flex-start;gap:10px">
                <span style="font-size:16px;flex-shrink:0;margin-top:1px">💡</span>
                <div style="flex:1;font-size:13px;color:#1e40af;line-height:1.7">
                    以下设置控制登录页面的样式，支持自定义配色、背景图片、虚化效果等。
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

        // ====== 用 JS 将分区标题移动到正确位置 ======
        // Typecho 的 config() 中 echo 的内容先输出，$form->addInput() 的表单项后渲染
        // 需要 JS 将分区标题移到对应表单项之前
        echo '<script>
(function(){
    function moveSection(){
        var form = document.querySelector("form.protected");
        if (!form) form = document.querySelector("form");
        if (!form) return;

        // 查找表单项容器（Typecho 用 ul > li 或 table 包裹表单项）
        // 每个 addInput 生成一个表单项，通过 name 属性定位
        function findFieldByName(name) {
            // 查找 input/select/textarea 的 name 属性
            var el = form.querySelector("[name=\"" + name + "\"]");
            if (!el) return null;
            // 向上找到最近的 li 或 typecho-option 容器
            var container = el.closest("li") || el.closest(".typecho-option") || el.closest("tr");
            if (!container) {
                // 再向上找 label 的父级
                container = el.parentNode;
                while (container && container !== form && container.tagName !== "LI" && container.tagName !== "TR") {
                    container = container.parentNode;
                }
            }
            return (container && container !== form) ? container : null;
        }

        // 移动"管理后台设置"标题到 primaryColor 字段之前
        var adminSection = document.getElementById("ab-section-admin");
        var firstAdminField = findFieldByName("primaryColor");
        if (adminSection && firstAdminField && firstAdminField.parentNode) {
            firstAdminField.parentNode.insertBefore(adminSection, firstAdminField);
        }

        // 移动"登录页设置"标题和提示到 login_colorPreset 字段之前
        var loginSection = document.getElementById("ab-section-login");
        var loginTip = document.getElementById("ab-section-login-tip");
        var firstLoginField = findFieldByName("login_colorPreset");
        if (loginSection && firstLoginField && firstLoginField.parentNode) {
            firstLoginField.parentNode.insertBefore(loginSection, firstLoginField);
            if (loginTip) {
                firstLoginField.parentNode.insertBefore(loginTip, firstLoginField);
            }
        }

        // 移动预览面板到 login_customJs 字段之后
        var previewPanel = document.getElementById("lb-preview");
        var lastLoginField = findFieldByName("login_customJs");
        if (previewPanel && lastLoginField && lastLoginField.parentNode) {
            // 包含预览的 style 标签也一起处理（预览面板自身已含在 lb-preview div 中）
            if (lastLoginField.nextSibling) {
                lastLoginField.parentNode.insertBefore(previewPanel, lastLoginField.nextSibling);
            } else {
                lastLoginField.parentNode.appendChild(previewPanel);
            }
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", moveSection);
    } else {
        moveSection();
    }
})();

// ====== 配置面板颜色跟随主题色 & 检查更新 ======
(function(){
        var abColorMap = {
        purple: ["#7D5260","#9E7B8A"],
        blue:   ["#556270","#7A8A9E"],
        teal:   ["#4A6363","#6A8A8A"],
        green:  ["#55624C","#7A8A6E"],
        orange: ["#725A42","#9E8062"],
        pink:   ["#74565F","#9E7A85"],
        red:    ["#775654","#A27A78"]
    };
    function applyConfigColors(scheme){
        var c = abColorMap[scheme] || abColorMap.purple;
        var banner = document.getElementById("ab-header-banner");
        if(banner) banner.style.background = "linear-gradient(135deg,"+c[0]+","+c[1]+")";
        var adminSec = document.getElementById("ab-section-admin");
        if(adminSec){
            adminSec.style.borderBottomColor = c[0];
            var h3 = adminSec.querySelector("h3");
            if(h3) h3.style.color = c[0];
        }
        var loginSec = document.getElementById("ab-section-login");
        if(loginSec){
            loginSec.style.borderBottomColor = c[1];
            var h3b = loginSec.querySelector("h3");
            if(h3b) h3b.style.color = c[1];
        }
    }
    function initColorFollow(){
        var sel = document.querySelector("[name=\"primaryColor\"]");
        if(!sel) return;
        sel.addEventListener("change", function(){ applyConfigColors(this.value); });
    }

    // 检查更新
    var currentVer = "' . $abVer . '";
    function compareVer(a, b){
        var pa = a.replace(/^v/i,"").split(".").map(Number);
        var pb = b.replace(/^v/i,"").split(".").map(Number);
        for(var i=0;i<Math.max(pa.length,pb.length);i++){
            var na = pa[i]||0, nb = pb[i]||0;
            if(na>nb) return 1;
            if(na<nb) return -1;
        }
        return 0;
    }
    function initUpdateCheck(){
        var btn = document.getElementById("ab-btn-update");
        if(!btn) return;
        btn.addEventListener("click", function(){
            var origHTML = btn.innerHTML;
            btn.innerHTML = \'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:ab-spin 1s linear infinite"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg> 检查中...\';
            btn.disabled = true;
            fetch("https://api.github.com/repos/lhl77/Typecho-Plugin-AdminBeautify/tags", {method:"GET"})
                .then(function(r){ return r.json(); })
                .then(function(tags){
                    if(!tags || !tags.length){
                        showUpdateResult(btn, origHTML, "info", "未找到版本信息");
                        return;
                    }
                    var latest = tags[0].name || "";
                    var cmp = compareVer(latest, currentVer);
                    if(cmp > 0){
                        showUpdateResult(btn, origHTML, "update", "发现新版本 " + latest + "（当前 v" + currentVer + "）");
                    } else {
                        showUpdateResult(btn, origHTML, "ok", "已是最新版本 v" + currentVer);
                    }
                })
                .catch(function(){
                    showUpdateResult(btn, origHTML, "error", "检查失败，请检查网络连接");
                });
        });
    }
    function showUpdateResult(btn, origHTML, type, msg){
        btn.disabled = false;
        btn.innerHTML = origHTML;
        // 移除旧提示
        var old = document.getElementById("ab-update-toast");
        if(old) old.remove();
        var colors = {ok:"#059669", update:"#d97706", error:"#dc2626", info:"#6366f1"};
        var icons = {
            ok: "✅",
            update: "🆕",
            error: "❌",
            info: "ℹ️"
        };
        var toast = document.createElement("div");
        toast.id = "ab-update-toast";
        toast.style.cssText = "margin:12px 0 0;padding:10px 16px;background:#fff;border:1px solid " + (colors[type]||"#6366f1") + ";border-radius:12px;font-size:13px;color:" + (colors[type]||"#6366f1") + ";display:flex;align-items:center;gap:8px;animation:ab-fadeIn .3s ease;text-shadow:none;font-weight:500;box-shadow:0 2px 8px rgba(0,0,0,.1)";
        toast.innerHTML = "<span>" + (icons[type]||"") + "</span><span>" + msg + "</span>";
        if(type === "update"){
            toast.innerHTML += " <a href=\'https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases\' target=\'_blank\' style=\'color:" + (colors[type]) + ";font-weight:600;text-decoration:underline;margin-left:4px\'>前往下载</a>";
        }
        var banner = document.getElementById("ab-header-banner");
        if(banner) banner.appendChild(toast);
        setTimeout(function(){ if(toast.parentNode) toast.remove(); }, 8000);
    }

    // 注入动画
    if(!document.getElementById("ab-config-anim")){
        var st = document.createElement("style");
        st.id = "ab-config-anim";
        st.textContent = "@keyframes ab-spin{to{transform:rotate(360deg)}} @keyframes ab-fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}";
        document.head.appendChild(st);
    }

    if(document.readyState === "loading"){
        document.addEventListener("DOMContentLoaded", function(){ initColorFollow(); initUpdateCheck(); });
    } else {
        initColorFollow(); initUpdateCheck();
    }
})();
</script>';
    }

    /**
     * 个人用户配置面板
     */
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
        $enableAnimation = $pluginOptions->enableAnimation ?? '1';
        $navPosition = $pluginOptions->navPosition ?: 'top';

        $cssUrl = Typecho_Common::url('AdminBeautify/assets/style.css', $options->pluginUrl);

        $inject = "\n" . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap">';
        $inject .= "\n" . '<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Round">';
        $inject .= "\n" . '<link rel="stylesheet" href="' . $cssUrl . '?v=' . '2.0.0' . '">';
        $inject .= "\n" . '<style>:root{';

        // 主题色 CSS 变量
        $colors = self::getColorScheme($primaryColor);
        foreach ($colors as $key => $value) {
            $inject .= $key . ':' . $value . ';';
        }

        // 圆角
        $radiusMap = array(
            'small'  => array('--md-radius-xs' => '4px', '--md-radius-sm' => '6px', '--md-radius-md' => '8px', '--md-radius-lg' => '12px', '--md-radius-xl' => '16px', '--md-radius-full' => '9999px'),
            'medium' => array('--md-radius-xs' => '6px', '--md-radius-sm' => '8px', '--md-radius-md' => '12px', '--md-radius-lg' => '16px', '--md-radius-xl' => '28px', '--md-radius-full' => '9999px'),
            'large'  => array('--md-radius-xs' => '8px', '--md-radius-sm' => '12px', '--md-radius-md' => '16px', '--md-radius-lg' => '24px', '--md-radius-xl' => '32px', '--md-radius-full' => '9999px'),
        );
        if (isset($radiusMap[$borderRadius])) {
            foreach ($radiusMap[$borderRadius] as $key => $value) {
                $inject .= $key . ':' . $value . ';';
            }
        }

        // 动画
        if ($enableAnimation === '0') {
            $inject .= '--md-transition-duration:0s;';
        } else {
            $inject .= '--md-transition-duration:0.2s;';
        }

        $inject .= '}</style>';

        // 暗色模式
        if ($darkMode === 'dark') {
            $inject .= "\n" . '<script>document.documentElement.setAttribute("data-theme","dark");</script>';
        } elseif ($darkMode === 'auto') {
            $inject .= "\n" . '<script>if(window.matchMedia&&window.matchMedia("(prefers-color-scheme:dark)").matches){document.documentElement.setAttribute("data-theme","dark");}</script>';
        }

        // 导航栏位置
        if ($navPosition === 'left') {
            $inject .= "\n" . '<script>document.documentElement.setAttribute("data-nav","left");if(localStorage.getItem("adminBeautifySidebarCollapsed")==="1"){document.documentElement.setAttribute("data-nav-collapsed","");}</script>';
        }

        return $header . $inject;
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
        )) . ';</script>';

        $jsUrl = Typecho_Common::url('AdminBeautify/assets/AdminBeautify.min.js', $options->pluginUrl);
        echo '<script src="' . $jsUrl . '?v=1.4.5"></script>';

        if ($darkMode === 'auto') {
            echo '<script>AdminBeautify.watchSystemTheme();</script>';
        }
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
