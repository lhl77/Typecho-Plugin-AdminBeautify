<?php
/**
 * Admin Beautify - 后台管理界面美化插件，包含登录界面美化 (原LoginBeautify)，Material Design 3风格
 *
 * @package AdminBeautify
 * @author LHL
 * @version 2.1.0
 * @link https://github.com/lhl77/Typecho-Plugin-AdminBeautify
 */
 
 if(!defined('__TYPECHO_ROOT_DIR__')){exit;}class AdminBeautify_Plugin implements Typecho_Plugin_Interface{private static function isLoginPage(){try{return!Typecho_Widget::widget('Widget_User')->hasLogin();}catch(Exception $a){return true;}}private static function jsString($b){return json_encode((string) $b,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}public static function activate(){Typecho_Plugin::factory('admin/header.php')->header=array(__CLASS__,'renderHeader');Typecho_Plugin::factory('admin/footer.php')->begin=array(__CLASS__,'renderFooter');Typecho_Plugin::factory('admin/footer.php')->end=array(__CLASS__,'renderLoginFooter');Utils\Helper::addAction('admin-beautify','AdminBeautify_Action');return _t('AdminBeautify 已启用（含登录页美化）');}public static function deactivate(){Utils\Helper::removeAction('admin-beautify');return _t('AdminBeautify 已禁用');}public static function config(Typecho_Widget_Helper_Form $c){$d=array('purple'=>array('#7D5260','#9E7B8A'),'blue'=>array('#556270','#7A8A9E'),'teal'=>array('#4A6363','#6A8A8A'),'green'=>array('#55624C','#7A8A6E'),'orange'=>array('#725A42','#9E8062'),'pink'=>array('#74565F','#9E7A85'),'red'=>array('#775654','#A27A78'),);try{$f=Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautify');$g=isset($f->primaryColor)?(string) $f->primaryColor:'purple';}catch(Exception $a){$g='purple';}if(!isset($d[$g]))$g='purple';$h=$d[$g][0];$i=$d[$g][1];$j='2.1.0';echo '<div id="ab-header-banner" style="margin:16px 0 24px;padding:24px 28px;background:linear-gradient(135deg,'.$h.','.$i.');color:#fff;border-radius:28px;box-shadow:0 4px 16px rgba(0,0,0,.18);text-shadow:0 1px 3px rgba(0,0,0,.25)">
            <div style="display:flex;align-items:center;gap:20px;margin-bottom:16px">
                <div style="width:64px;height:64px;background:rgba(255,255,255,.15);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:32px;backdrop-filter:blur(10px);flex-shrink:0;text-shadow:none">🎨</div>
                <div style="flex:1">
                    <h2 style="margin:0 0 6px;font-size:22px;font-weight:600;letter-spacing:-0.02em">Admin Beautify <span style="font-size:13px;font-weight:400;opacity:.8;margin-left:4px">v'.$j.'</span></h2>
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
        </div>';echo '<style>
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
[data-theme="dark"] #ab-card-compat {
    background: var(--md-surface-container-low, #1d1b20) !important;
    border-color: var(--md-outline-variant, rgba(255,255,255,.12)) !important;
    box-shadow: 0 1px 3px rgba(0,0,0,.3), 0 2px 12px rgba(0,0,0,.2) !important;
}
[data-theme="dark"] #ab-card-pwa-hdr:hover,
[data-theme="dark"] #ab-card-compat-hdr:hover {
    background: rgba(255,255,255,.04) !important;
}
</style>';echo '<div id="ab-card-admin" style="margin:0 0 16px;border-radius:20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08),0 2px 12px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.06);overflow:hidden">
            <div id="ab-card-admin-hdr" style="display:flex;align-items:center;gap:12px;padding:18px 22px;cursor:pointer;user-select:none;-webkit-user-select:none;transition:background .15s" onmouseover="this.style.background=\'rgba(0,0,0,.025)\'" onmouseout="this.style.background=\'\'">
                <div id="ab-card-admin-strip" style="width:3px;height:36px;background:'.$h.';border-radius:2px;flex-shrink:0;transition:background .3s"></div>
                <div id="ab-card-admin-icon" style="width:40px;height:40px;background:'.$h.'1a;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;transition:background .3s">⚙️</div>
                <div style="flex:1;min-width:0">
                    <div class="ab-card-title" style="font-size:15px;font-weight:600;color:#1c1b1f;line-height:1.3">管理后台设置</div>
                    <div class="ab-card-subtitle" style="font-size:12px;color:#79747e;margin-top:2px">主题色、暗色模式、圆角、动画、布局</div>
                </div>
                <svg id="ab-card-admin-chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="'.$h.'" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;transition:transform .35s"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div id="ab-card-admin-body" style="overflow:hidden;max-height:9999px;padding:0 16px;transition:max-height .4s cubic-bezier(.4,0,.2,1)"></div>
        </div>';$k=new Typecho_Widget_Helper_Form_Element_Select('primaryColor',array('purple'=>'🟣 紫 (默认)','blue'=>'🔵 蓝','teal'=>'🩵 青','green'=>'🟢 绿','orange'=>'🟠 橙','pink'=>'🩷 粉','red'=>'🔴 红',),'purple',_t('主题色'),_t('选择管理后台的主题色方案'));$c->addInput($k);$l=new Typecho_Widget_Helper_Form_Element_Select('darkMode',array('auto'=>'跟随系统','light'=>'浅色模式','dark'=>'深色模式',),'auto',_t('颜色模式'),_t('选择后台的明暗模式'));$c->addInput($l);$n=new Typecho_Widget_Helper_Form_Element_Select('borderRadius',array('small'=>'小圆角','medium'=>'中圆角 (默认)','large'=>'大圆角',),'medium',_t('圆角风格'),_t('控制界面元素的圆角大小'));$c->addInput($n);$o=new Typecho_Widget_Helper_Form_Element_Select('enableAnimation',array('1'=>'开启','0'=>'关闭',),'1',_t('过渡动画'),_t('是否开启界面元素的过渡动画效果'));$c->addInput($o);$q=new Typecho_Widget_Helper_Form_Element_Select('navPosition',array('left'=>'侧边栏 (默认)','top'=>'导航栏 (原版)',),'left',_t('导航栏位置'),_t('选择导航栏在页面顶部还是左侧显示（仅桌面端生效，移动端始终为顶部折叠菜单）'));$c->addInput($q);$r=new Typecho_Widget_Helper_Form_Element_Select('pluginCardView',array('1'=>'卡片网格 (默认)','0'=>'原始表格',),'1',_t('插件列表样式'),_t('选择插件管理页面的展示方式：卡片网格更直观，原始表格与 Typecho 默认保持一致'));$c->addInput($r);echo '<div id="ab-card-login" style="margin:0 0 16px;border-radius:20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08),0 2px 12px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.06);overflow:hidden">
            <div id="ab-card-login-hdr" style="display:flex;align-items:center;gap:12px;padding:18px 22px;cursor:pointer;user-select:none;-webkit-user-select:none;transition:background .15s" onmouseover="this.style.background=\'rgba(0,0,0,.025)\'" onmouseout="this.style.background=\'\'">
                <div id="ab-card-login-strip" style="width:3px;height:36px;background:'.$i.';border-radius:2px;flex-shrink:0;transition:background .3s"></div>
                <div id="ab-card-login-icon" style="width:40px;height:40px;background:'.$i.'1a;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;transition:background .3s">🔐</div>
                <div style="flex:1;min-width:0">
                    <div class="ab-card-title" style="font-size:15px;font-weight:600;color:#1c1b1f;line-height:1.3">登录页设置</div>
                    <div class="ab-card-subtitle" style="font-size:12px;color:#79747e;margin-top:2px">配色方案、背景图片、虚化效果、自定义样式</div>
                </div>
                <svg id="ab-card-login-chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="'.$i.'" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;transition:transform .35s"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div id="ab-card-login-body" style="overflow:hidden;max-height:9999px;padding:0 16px;transition:max-height .4s cubic-bezier(.4,0,.2,1)">
                <div class="ab-card-tip" style="margin:4px 6px 8px;padding:12px 15px;background:#f0f9ff;border:1px solid #bfdbfe;border-radius:12px">
                    <div style="display:flex;align-items:flex-start;gap:10px">
                        <span style="font-size:15px;flex-shrink:0;margin-top:1px">💡</span>
                        <div class="ab-card-tip-text" style="flex:1;font-size:13px;color:#1e40af;line-height:1.7">以下设置控制登录页面的样式，支持自定义配色、背景图片、虚化效果等。</div>
                    </div>
                </div>
            </div>
        </div>';$t=new Typecho_Widget_Helper_Form_Element_Select('login_colorPreset',array('custom'=>_t('自定义'),'purple'=>_t('🟣 紫 (默认)'),'blue'=>_t('🔵 蓝'),'pink'=>_t('🌸 粉'),'green'=>_t('🌿 绿'),'orange'=>_t('🍊 橙'),'red'=>_t('❤️ 红'),'teal'=>_t('🌊 青'),'indigo'=>_t('💙 靛蓝'),'sunset'=>_t('🌅 日落渐变'),'ocean'=>_t('🌊 海洋渐变'),'forest'=>_t('🌲 森林渐变'),'lavender'=>_t('💜 薰衣草'),),'purple',_t('登录页配色方案'),_t('选择预设配色或使用自定义颜色'));$c->addInput($t);$u=new Typecho_Widget_Helper_Form_Element_Text('login_primaryColor',null,'#7d5260',_t('登录页主色（自定义）'),_t('选择"自定义"方案后生效。如：#625fa0'));$c->addInput($u);$v=new Typecho_Widget_Helper_Form_Element_Text('login_primaryColor2',null,'#9e7b8a',_t('登录页辅色（自定义）'),_t('选择"自定义"方案后生效。如：#7a6ec0'));$c->addInput($v);$w=new Typecho_Widget_Helper_Form_Element_Radio('login_showSiteName',array('1'=>_t('显示'),'0'=>_t('隐藏')),'1',_t('登录页显示站点名称'));$c->addInput($w);$x=new Typecho_Widget_Helper_Form_Element_Radio('login_themeMode',array('auto'=>_t('跟随系统'),'light'=>_t('亮色'),'dark'=>_t('暗色')),'auto',_t('登录页默认主题'));$c->addInput($x);$y=new Typecho_Widget_Helper_Form_Element_Radio('login_showThemeToggle',array('1'=>_t('显示'),'0'=>_t('隐藏')),'1',_t('登录页显示主题切换按钮'));$c->addInput($y);$z=new Typecho_Widget_Helper_Form_Element_Text('login_bgImage',null,'',_t('登录页背景图片 URL'),_t('留空则使用纯色背景。'));$c->addInput($z);$aa=new Typecho_Widget_Helper_Form_Element_Radio('login_blurType',array('none'=>_t('不虚化'),'filter'=>_t('背景图模糊（filter: blur）'),),'filter',_t('登录页虚化方式'));$c->addInput($aa);$bb=new Typecho_Widget_Helper_Form_Element_Text('login_blurSize',null,'12',_t('登录页虚化大小(px)'),_t('建议 0-50。'));$c->addInput($bb);$cc=new Typecho_Widget_Helper_Form_Element_Textarea('login_customCss',null,'',_t('登录页自定义 CSS'),_t('将注入到登录页。无需 style 标签。如果不生效请加 !important'));$c->addInput($cc);$dd=new Typecho_Widget_Helper_Form_Element_Textarea('login_customJs',null,'',_t('登录页自定义 JavaScript'),_t('将注入到登录页。无需 script 标签。'));$c->addInput($dd);self::renderLoginPreview();echo '<div id="ab-card-pwa" style="margin:0 0 16px;border-radius:20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08),0 2px 12px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.06);overflow:hidden">
            <div id="ab-card-pwa-hdr" style="display:flex;align-items:center;gap:12px;padding:18px 22px;cursor:pointer;user-select:none;-webkit-user-select:none;transition:background .15s" onmouseover="this.style.background=\'rgba(0,0,0,.025)\'" onmouseout="this.style.background=\'\'">
                <div id="ab-card-pwa-strip" style="width:3px;height:36px;background:'.$h.';border-radius:2px;flex-shrink:0;transition:background .3s"></div>
                <div id="ab-card-pwa-icon" style="width:40px;height:40px;background:'.$h.'1a;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;transition:background .3s">📱</div>
                <div style="flex:1;min-width:0">
                    <div class="ab-card-title" style="font-size:15px;font-weight:600;color:#1c1b1f;line-height:1.3">PWA 应用设置</div>
                    <div class="ab-card-subtitle" style="font-size:12px;color:#79747e;margin-top:2px">将管理后台安装为渐进式 Web 应用，自定义名称和图标</div>
                </div>
                <svg id="ab-card-pwa-chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="'.$h.'" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;transition:transform .35s"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div id="ab-card-pwa-body" style="overflow:hidden;max-height:9999px;padding:0 16px;transition:max-height .4s cubic-bezier(.4,0,.2,1)"></div>
        </div>';$ee=new Typecho_Widget_Helper_Form_Element_Text('pwa_appName',null,'',_t('PWA 应用名称'),_t('安装为 PWA 后显示的应用名称。留空则默认为「博客名称 + 管理后台」'));$c->addInput($ee);$ff=new Typecho_Widget_Helper_Form_Element_Text('pwa_appIcon',null,'https://i.see.you/2026/03/08/Uei3/26ee132f48bd9453e9c4d1d3fa1d312d.jpg',_t('PWA 应用图标 URL'),_t('安装为 PWA 后显示的应用图标，建议使用 512×512 的正方形图片。'));$c->addInput($ff);echo '<div id="ab-card-compat" style="margin:0 0 16px;border-radius:20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08),0 2px 12px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.06);overflow:hidden">
            <div id="ab-card-compat-hdr" style="display:flex;align-items:center;gap:12px;padding:18px 22px;cursor:pointer;user-select:none;-webkit-user-select:none;transition:background .15s" onmouseover="this.style.background=\'rgba(0,0,0,.025)\'" onmouseout="this.style.background=\'\'">
                <div id="ab-card-compat-strip" style="width:3px;height:36px;background:'.$i.';border-radius:2px;flex-shrink:0;transition:background .3s"></div>
                <div id="ab-card-compat-icon" style="width:40px;height:40px;background:'.$i.'1a;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;transition:background .3s">🧩</div>
                <div style="flex:1;min-width:0">
                    <div class="ab-card-title" style="font-size:15px;font-weight:600;color:#1c1b1f;line-height:1.3">兼容脚本管理</div>
                    <div class="ab-card-subtitle" style="font-size:12px;color:#79747e;margin-top:2px">自动修复其他插件页面排版，管理本地与外部兼容脚本</div>
                </div>
                <svg id="ab-card-compat-chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="'.$i.'" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;transition:transform .35s"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div id="ab-card-compat-body" style="overflow:hidden;max-height:9999px;padding:0 16px;transition:max-height .4s cubic-bezier(.4,0,.2,1)">
                <div class="ab-card-tip-green" style="margin:4px 6px 8px;padding:12px 15px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px">
                    <div style="display:flex;align-items:flex-start;gap:10px">
                        <span style="font-size:15px;flex-shrink:0;margin-top:1px">📦</span>
                        <div class="ab-card-tip-green-text" style="flex:1;font-size:13px;color:#166534;line-height:1.7">
                            AdminBeautify 会自动加载 <code style="background:#dcfce7;padding:2px 6px;border-radius:4px;font-size:12px">assets/compat/</code> 目录下的兼容脚本来修复其他插件的页面排版。<br>
                            开发者可参考 <code style="background:#dcfce7;padding:2px 6px;border-radius:4px;font-size:12px">assets/compat/README.md</code> 编写兼容脚本（需包含 <code style="background:#dcfce7;padding:2px 6px;border-radius:4px;font-size:12px">@name</code> / <code style="background:#dcfce7;padding:2px 6px;border-radius:4px;font-size:12px">@plugins</code> / <code style="background:#dcfce7;padding:2px 6px;border-radius:4px;font-size:12px">@description</code> 元数据）。
                        </div>
                    </div>
                </div>
            </div>
        </div>';$gg=dirname(__FILE__).'/assets/compat/';$hh=self::scanCompatScripts($gg);$ii='';try{$jj=Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautify');$ii=isset($jj->compat_disabledScripts)?(string) $jj->compat_disabledScripts:'';}catch(Exception $a){$ii='';}$kk=($ii!=='')?(array) json_decode($ii,true):array();if(!is_array($kk))$kk=array();self::renderCompatScriptsList($hh,$kk,$h);$ll=new Typecho_Widget_Helper_Form_Element_Hidden('compat_disabledScripts',null,$ii);$c->addInput($ll);$mm=new Typecho_Widget_Helper_Form_Element_Textarea('compat_externalJs',null,'',_t('外部兼容脚本 URL'),_t('每行一个 JS 文件 URL，将在后台所有页面加载。示例：https://cdn.example.com/compat/my-plugin-fix.js'));$c->addInput($mm);echo '<script>
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

        // ---- PWA 应用卡片（插在登录页卡片之后） ----
        var pwaFields=["pwa_appName","pwa_appIcon"];
        var pwaCard=document.getElementById("ab-card-pwa");
        var pwaBody=document.getElementById("ab-card-pwa-body");
        if(pwaCard&&pwaBody&&loginCard){
            var form3=loginCard.parentNode;
            if(loginCard.nextSibling) form3.insertBefore(pwaCard,loginCard.nextSibling);
            else form3.appendChild(pwaCard);
            for(var p=0;p<pwaFields.length;p++){
                var pu=findFieldUl(pwaFields[p]);
                if(pu) pwaBody.appendChild(pu);
            }
            pwaBody.style.paddingBottom="16px";
        }

        // ---- 兼容脚本卡片 ----
        var compatCard=document.getElementById("ab-card-compat");
        var compatBody=document.getElementById("ab-card-compat-body");
        if(compatCard&&compatBody){
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

        // ---- 绑定卡片点击 & 恢复/默认折叠状态 ----
        ["admin","login","pwa","compat"].forEach(function(id){
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
</script>';}public static function personalConfig(Typecho_Widget_Helper_Form $c){}public static function renderHeader($nn){if(self::isLoginPage()){ob_start();self::outputLoginHeaderCss();$oo=ob_get_clean();return $nn.$oo;}return self::renderAdminHeader($nn);}private static function renderAdminHeader($nn){$pp=Typecho_Widget::widget('Widget_Options');$qq=$pp->plugin('AdminBeautify');$k=$qq->primaryColor?:'purple';$l=$qq->darkMode?:'auto';$n=$qq->borderRadius?:'medium';$rr=isset($qq->enableAnimation)?(string)$qq->enableAnimation:'';$o=($rr!=='')?$rr:'1';$q=$qq->navPosition?:'left';$ss=Typecho_Common::url('AdminBeautify/assets/style.css',$pp->pluginUrl);$tt='<script>';if($l==='dark'){$tt.='document.documentElement.setAttribute("data-theme","dark");';}elseif($l==='light'){$tt.='document.documentElement.removeAttribute("data-theme");';}elseif($l==='auto'){$tt.='(function(){var m=window.matchMedia&&window.matchMedia("(prefers-color-scheme:dark)");if(m&&m.matches){document.documentElement.setAttribute("data-theme","dark");}})();';}if($q==='left'){$tt.='document.documentElement.setAttribute("data-nav","left");if(localStorage.getItem("adminBeautifySidebarCollapsed")==="1"){document.documentElement.setAttribute("data-nav-collapsed","");}';}if($o==='0'){$tt.='document.documentElement.setAttribute("data-no-animation","");';}$tt.='</script>';$oo="\n".$tt;$oo.="\n".'<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap">';$oo.="\n".'<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Round">';$oo.="\n".'<link rel="stylesheet" href="'.$ss.'?v='.'2.0.0'.'">';$oo.="\n".'<style>:root{';$uu=self::getColorScheme($k);foreach($uu as $vv=>$ww){$oo.=$vv.':'.$ww.';';}$xx=array('small'=>array('--md-radius-xs'=>'4px','--md-radius-sm'=>'6px','--md-radius-md'=>'8px','--md-radius-lg'=>'12px','--md-radius-xl'=>'16px','--md-radius-full'=>'9999px'),'medium'=>array('--md-radius-xs'=>'6px','--md-radius-sm'=>'8px','--md-radius-md'=>'12px','--md-radius-lg'=>'16px','--md-radius-xl'=>'28px','--md-radius-full'=>'9999px'),'large'=>array('--md-radius-xs'=>'8px','--md-radius-sm'=>'12px','--md-radius-md'=>'16px','--md-radius-lg'=>'24px','--md-radius-xl'=>'32px','--md-radius-full'=>'9999px'),);if(isset($xx[$n])){foreach($xx[$n]as $vv=>$ww){$oo.=$vv.':'.$ww.';';}}if($o==='0'){$oo.='--md-transition-duration:0s;';}else{$oo.='--md-transition-duration:0.2s;';}$oo.='}</style>';$uu=self::getColorScheme($k);$yy=array('purple'=>'#7D5260','blue'=>'#556270','teal'=>'#4A6363','green'=>'#55624C','orange'=>'#725A42','pink'=>'#74565F','red'=>'#775654',);$zz=isset($yy[$k])?$yy[$k]:'#7D5260';$aaa=Typecho_Common::url('/action/admin-beautify?do=manifest',$pp->index);$ee=isset($qq->pwa_appName)?trim((string) $qq->pwa_appName):'';$ff=isset($qq->pwa_appIcon)?trim((string) $qq->pwa_appIcon):'';$bbb=($ee!=='')?$ee:((string) $pp->title.' 管理后台');$oo.="\n".'<link rel="manifest" href="'.htmlspecialchars($aaa).'">';$oo.="\n".'<meta name="theme-color" content="'.$zz.'">';$oo.="\n".'<meta name="apple-mobile-web-app-capable" content="yes">';$oo.="\n".'<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">';$oo.="\n".'<meta name="apple-mobile-web-app-title" content="'.htmlspecialchars($bbb).'">';$oo.="\n".'<meta name="mobile-web-app-capable" content="yes">';if($ff!==''){$oo.="\n".'<link rel="apple-touch-icon" sizes="180x180" href="'.htmlspecialchars($ff).'">';}return $nn.$oo;}public static function renderFooter(){if(self::isLoginPage()){return;}$pp=Typecho_Widget::widget('Widget_Options');$qq=$pp->plugin('AdminBeautify');$l=$qq->darkMode?:'auto';$rr=isset($qq->enableAnimation)?(string)$qq->enableAnimation:'';$o=($rr!=='')?$rr:'1';$r=isset($qq->pluginCardView)?(string)$qq->pluginCardView:'1';$ccc=Typecho_Widget::widget('Widget_User');$ddd='';if($ccc->hasLogin()&&$ccc->mail){$eee=md5(strtolower(trim($ccc->mail)));$ddd='https://cravatar.cn/avatar/'.$eee.'?s=80&d=mp';}$fff=$ccc->hasLogin()?$ccc->screenName:'';$ggg=Typecho_Common::url('/action/admin-beautify',$pp->index);$hhh=Typecho_Widget::widget('Widget_Security');$iii=$hhh->getToken($ggg);echo '<script>window.__AB_USER__='.json_encode(array('avatar'=>$ddd,'name'=>$fff,)).';';echo 'window.__AB_AJAX__='.json_encode(array('url'=>$ggg,'token'=>$iii,)).';';echo 'window.__AB_CONFIG__='.json_encode(array('darkMode'=>$l,'enableAnimation'=>$o,'pluginCardView'=>$r,)).';</script>';$jjj=Typecho_Common::url('AdminBeautify/assets/AdminBeautify.min.js',$pp->pluginUrl);echo '<script src="'.$jjj.'"></script>';if($l==='auto'){echo '<script>AdminBeautify.watchSystemTheme();</script>';}$kkk=Typecho_Common::url('/action/admin-beautify?do=sw',$pp->index);echo '<script>(function(){if(!("serviceWorker"in navigator))return;'.'navigator.serviceWorker.register('.json_encode($kkk).',{scope:'.json_encode(rtrim((string)$pp->adminUrl,'/').'/').'})'.'.then(function(reg){'.'reg.addEventListener("updatefound",function(){'.'var newSW=reg.installing;'.'newSW.addEventListener("statechange",function(){'.'if(newSW.state==="installed"&&navigator.serviceWorker.controller){'.'/* 新版本已就绪，可选择提示用户刷新 */'.'}'.'});'.'});'.'})'.'.catch(function(){});'.'}());</script>';$lll=Typecho_Common::url('/action/admin-beautify?do=ping',$pp->index);echo '<script>(function(){'.'var isStandalone=window.matchMedia&&window.matchMedia("(display-mode:standalone)").matches||window.navigator.standalone===true;'.'if(!isStandalone)return;'.'function renewCookies(){'.'var cookies=document.cookie.split(";");'.'for(var i=0;i<cookies.length;i++){'.'var c=cookies[i].trim();'.'if(c.indexOf("__typecho_uid=")=== 0||c.indexOf("__typecho_authCode=")===0){'.'var eqIdx=c.indexOf("=");'.'var name=c.substring(0,eqIdx);'.'var value=c.substring(eqIdx+1);'.'var d=new Date();d.setTime(d.getTime()+30*24*60*60*1000);'.'document.cookie=name+"="+value+";expires="+d.toUTCString()+";path=/;SameSite=Lax";'.'}'.'}'.'}'.'renewCookies();'.'setInterval(renewCookies,10*60*1000);'.'setInterval(function(){fetch('.json_encode($lll).',{credentials:"include"}).catch(function(){});},15*60*1000);'.'}());</script>';echo '<script>(function(){';echo 'var __AB_VER__="2.1.0";';echo <<<'UPDATEJS'
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
        actionBtns+='<button id="ab-btn-do-update" type="button" onclick="abDoUpdate()" style="'+btnBase+'background:rgba(255,255,255,.25);color:inherit;cursor:pointer;">⬆️ 立即更新</button>';
    } else {
        // 不支持直接更新：显示禁用态按钮 + 原因提示
        actionBtns+='<span style="'+btnBase+'background:rgba(255,255,255,.08);color:inherit;opacity:.5;cursor:not-allowed;" title="当前版本跨越了主/次版本号，需手动下载">⬆️ 立即更新</span>'
                   +'<span style="font-size:11px;opacity:.65;align-self:center;">需手动更新</span>';
    }
    actionBtns+='<a href="'+d.html_url+'" target="_blank" style="'+btnBase+'background:transparent;color:inherit;opacity:.85;">🔗 查看详情</a>';

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
UPDATEJS;echo '})();</script>';self::loadCompatScripts($pp,$qq);}private static function loadCompatScripts($pp,$qq){$mmm=Typecho_Common::url('AdminBeautify/assets/compat/',$pp->pluginUrl);$gg=dirname(__FILE__).'/assets/compat/';$ii=isset($qq->compat_disabledScripts)?(string) $qq->compat_disabledScripts:'';$kk=($ii!=='')?(array) json_decode($ii,true):array();if(!is_array($kk))$kk=array();if(is_dir($gg)){$nnn=scandir($gg);if($nnn){foreach($nnn as $ooo){if(substr($ooo,-3)==='.js'&&is_file($gg.$ooo)){if(in_array($ooo,$kk)){continue;}$ppp=$mmm.$ooo;$qqq=filemtime($gg.$ooo);echo '<script src="'.htmlspecialchars($ppp).'?v='.$qqq.'"></script>'."\n";}}}}$rrr=isset($qq->compat_externalJs)?trim((string) $qq->compat_externalJs):'';if($rrr!==''){$sss=preg_split('/[\r\n]+/',$rrr);foreach($sss as $ttt){$uuu=trim($ttt);if($uuu!==''&&(strpos($uuu,'http://')===0||strpos($uuu,'https://')===0||strpos($uuu,'//')===0)){echo '<script src="'.htmlspecialchars($uuu).'"></script>'."\n";}}}}private static function scanCompatScripts($gg){$vvv=array();if(!is_dir($gg))return $vvv;$nnn=scandir($gg);if(!$nnn)return $vvv;foreach($nnn as $ooo){if(substr($ooo,-3)!=='.js'||!is_file($gg.$ooo))continue;$www=self::parseCompatMeta($gg.$ooo);$www['file']=$ooo;$vvv[]=$www;}return $vvv;}private static function parseCompatMeta($xxx){$yyy=array('name'=>'','description'=>'','plugins'=>'','version'=>'','author'=>'',);$zzz=@file_get_contents($xxx,false,null,0,2048);if($zzz===false)return $yyy;if(!preg_match('/\/\*\*(.*?)\*\//s',$zzz,$aaaa)){return $yyy;}$bbbb=$aaaa[1];$cccc=array('name','description','plugins','version','author');foreach($cccc as $dddd){if(preg_match('/@'.$dddd.'\s+(.+)/i',$bbbb,$eeee)){$yyy[$dddd]=trim($eeee[1]);}}if($yyy['name']===''){$yyy['name']=basename($xxx,'.js');}return $yyy;}private static function renderCompatScriptsList($ffff,$kk,$gggg){if(empty($ffff)){echo '<div id="ab-compat-scripts-list" class="ab-compat-empty" style="margin:12px 0 20px;padding:16px;background:#fefce8;border:1px solid #fde68a;border-radius:10px;font-size:13px;color:#854d0e">
                <span style="margin-right:6px">📂</span> <code>assets/compat/</code> 目录中未找到兼容脚本。
            </div>';return;}echo '<div id="ab-compat-scripts-list" style="margin:12px 0 20px">';echo '<div class="ab-compat-list-title" style="font-size:14px;font-weight:600;color:#374151;margin-bottom:10px;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
            <span style="font-size:16px">📋</span>
            <span style="flex:1">本地兼容脚本（共 '.count($ffff).' 个）</span>
            <button id="ab-compat-sync-btn" type="button" onclick="abSyncCompat()" style="'.'display:inline-flex;align-items:center;gap:6px;padding:6px 14px;'.'background:'.$gggg.';color:#fff;border:none;border-radius:20px;'.'font-size:12px;font-weight:500;cursor:pointer;transition:opacity .2s;'.'box-shadow:0 1px 4px rgba(0,0,0,.15);flex-shrink:0'.'" onmouseover="this.style.opacity=\'.85\'" onmouseout="this.style.opacity=\'1\'">'.'<span id="ab-compat-sync-icon">☁️</span><span id="ab-compat-sync-label">从 GitHub 同步</span>'.'</button>
        </div>
        <div id="ab-compat-sync-result" style="display:none;margin-bottom:12px"></div>';foreach($ffff as $b){$ooo=htmlspecialchars($b['file']);$hhhh=htmlspecialchars($b['name']);$iiii=htmlspecialchars($b['description']);$jjjj=htmlspecialchars($b['plugins']);$kkkk=htmlspecialchars($b['version']);$llll=htmlspecialchars($b['author']);$mmmm=in_array($b['file'],$kk);$nnnn='ab-compat-toggle-'.md5($b['file']);echo '<div class="ab-compat-script-item" data-file="'.$ooo.'" style="'.'display:flex;align-items:flex-start;gap:14px;padding:14px 18px;margin-bottom:8px;'.'background:'.($mmmm?'#f9fafb':'#fff').';'.'border:1px solid '.($mmmm?'#e5e7eb':$gggg.'44').';'.'border-radius:12px;transition:all .2s;'.($mmmm?'opacity:.6;':'').'">';echo '<div style="flex-shrink:0;padding-top:2px">'.'<label style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer">'.'<input type="checkbox" class="ab-compat-checkbox" data-file="'.$ooo.'" '.($mmmm?'':'checked').' '.'style="opacity:0;width:0;height:0;position:absolute">'.'<span style="'.'position:absolute;top:0;left:0;right:0;bottom:0;'.'background:'.($mmmm?'#d1d5db':$gggg).';'.'border-radius:12px;transition:background .2s;'.'"></span>'.'<span style="'.'position:absolute;top:2px;left:'.($mmmm?'2px':'22px').';'.'width:20px;height:20px;background:#fff;border-radius:50%;'.'transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);'.'"></span>'.'</label>'.'</div>';echo '<div style="flex:1;min-width:0">';echo '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">';echo '<span class="ab-compat-name" style="font-size:14px;font-weight:600;color:#111827">'.$hhhh.'</span>';if($kkkk){echo '<span class="ab-compat-meta" style="font-size:11px;color:#6b7280;background:#f3f4f6;padding:1px 8px;border-radius:8px">v'.$kkkk.'</span>';}if($llll){echo '<span class="ab-compat-meta" style="font-size:11px;color:#6b7280">by '.$llll.'</span>';}echo '</div>';if($iiii){echo '<div class="ab-compat-desc" style="font-size:13px;color:#4b5563;line-height:1.5;margin-bottom:4px">'.$iiii.'</div>';}if($jjjj){$oooo=array_map('trim',explode(',',$jjjj));echo '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">';foreach($oooo as $pppp){echo '<span style="font-size:11px;color:'.$gggg.';background:'.$gggg.'15;padding:2px 10px;border-radius:8px;border:1px solid '.$gggg.'33;font-weight:500">'.htmlspecialchars($pppp).'</span>';}echo '</div>';}echo '<div class="ab-compat-meta" style="font-size:11px;color:#9ca3af;margin-top:4px">📄 '.$ooo.'</div>';echo '</div>';echo '</div>';}echo '</div>';echo '<script>
(function(){
    function updateDisabledList(){
        var items = document.querySelectorAll(".ab-compat-checkbox");
        var disabled = [];
        for(var i=0;i<items.length;i++){
            if(!items[i].checked){
                disabled.push(items[i].getAttribute("data-file"));
            }
        }
        var hidden = document.querySelector("[name=compat_disabledScripts]");
        if(hidden) hidden.value = JSON.stringify(disabled);
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
                    if(track) track.style.background="'.$gggg.'";
                    if(thumb) thumb.style.left="22px";
                }else{
                    if(item){item.style.opacity=".6";item.style.background="#f9fafb";}
                    if(track) track.style.background="#d1d5db";
                    if(thumb) thumb.style.left="2px";
                }
                updateDisabledList();
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
                    + "<span>🆕 新增 <strong>" + (summary.added||0) + "</strong></span>"
                    + "<span>⬆️ 更新 <strong>" + (summary.updated||0) + "</strong></span>"
                    + "<span>✔️ 跳过 <strong>" + (summary.skipped||0) + "</strong></span>"
                    + (summary.errors > 0 ? "<span style=\"color:#dc2626\">❌ 失败 <strong>" + summary.errors + "</strong></span>" : "")
                    + "</div>";
                var changed = [];
                for(var i=0;i<results.length;i++){
                    var r = results[i];
                    if(r.status === "added" || r.status === "updated" || r.status === "error"){
                        var icon2 = r.status==="added"?"🆕":r.status==="updated"?"⬆️":"❌";
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
</script>';}public static function renderLoginFooter(){if(!self::isLoginPage()){return;}$pp=Typecho_Widget::widget('Widget_Options');$qq=$pp->plugin('AdminBeautify');$qqqq=((string) $qq->login_showSiteName!=='0');$rrrr=((string) $qq->login_showThemeToggle!=='0');$ssss=(string) $qq->login_customJs;$tttt=(string) $pp->title;$uuuu=self::jsString($tttt);$vvvv=$qqqq?'true':'false';$wwww=$rrrr?'true':'false';echo"\n<script id=\"loginbeautify-main\">
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

    var showSiteName = {$vvvv};

    if (showSiteName) {
      var name = document.createElement('div');
      name.className = 'name';
      name.textContent = {$uuuu};
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

    var showToggle = {$wwww};
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
</script>\n";if(trim($ssss)!==''){echo "\n<script id=\"loginbeautify-custom-js\">\n";echo $ssss."\n";echo "</script>\n";}}private static function outputLoginHeaderCss(){$pp=Typecho_Widget::widget('Widget_Options');$qq=$pp->plugin('AdminBeautify');$xxxx=isset($qq->login_themeMode)?(string) $qq->login_themeMode:'auto';if(!in_array($xxxx,array('auto','light','dark'),true)){$xxxx='auto';}$yyyy=trim((string) $qq->login_bgImage);$zzzz=in_array($qq->login_blurType,array('none','filter','backdrop'),true)?$qq->login_blurType:'filter';$aaaaa=(int) $qq->login_blurSize;if($aaaaa<0)$aaaaa=0;if($aaaaa>80)$aaaaa=80;$bbbbb=(string) $qq->login_customCss;$ccccc=isset($qq->login_colorPreset)?(string) $qq->login_colorPreset:'purple';$ddddd=array('purple'=>array('#7d5260','#9e7b8a'),'blue'=>array('#556270','#7a8a9e'),'pink'=>array('#74565f','#9e7a85'),'green'=>array('#55624c','#7a8a6e'),'orange'=>array('#725a42','#9e8062'),'red'=>array('#775654','#a27a78'),'teal'=>array('#4a6363','#6a8a8a'),'indigo'=>array('#5a4fd9','#7b6ef2'),'sunset'=>array('#d38d1a','#e06b3a'),'ocean'=>array('#0da0d8','#39c1dd'),'forest'=>array('#2f7a3b','#7fbf3a'),'lavender'=>array('#8f6ee8','#b89cfb'),);if($ccccc==='custom'){$eeeee=isset($qq->login_primaryColor)&&trim((string) $qq->login_primaryColor)!==''?trim((string) $qq->login_primaryColor):'#7d5260';$fffff=isset($qq->login_primaryColor2)&&trim((string) $qq->login_primaryColor2)!==''?trim((string) $qq->login_primaryColor2):'#9e7b8a';}else{$uu=isset($ddddd[$ccccc])?$ddddd[$ccccc]:$ddddd['purple'];$eeeee=$uu[0];$fffff=$uu[1];}$ggggg=$yyyy!==''?"url(".htmlspecialchars($yyyy,ENT_QUOTES,'UTF-8').")":"none";echo "\n".'<style id="loginbeautify-style">'."\n";?>
:root{
--lb-primary:<?php echo htmlspecialchars($eeeee,ENT_QUOTES,'UTF-8');?>;
--lb-primary2:<?php echo htmlspecialchars($fffff,ENT_QUOTES,'UTF-8');?>;
--lb-surface:#f3f4f5;
--lb-surface-alpha:rgba(255,255,255,.8);
--lb-on-surface:#111827;
--lb-on-surface-muted:#4b5563;
--lb-border:rgba(0,0,0,.08);
--lb-shadow: 0 20px 40px -10px rgba(0,0,0,.15), 0 0 0 1px rgba(255,255,255,.4) inset;
--lb-radius: 20px;
--lb-input-bg: rgba(255,255,255,.8);
--lb-input-border: #e5e7eb;
--lb-bg-image: <?php echo $ggggg;?>;
--lb-blur: <?php echo (int) $aaaaa;?>px;
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

<?php if($zzzz==='backdrop'){?>
.lb-card{
backdrop-filter: blur(var(--lb-blur));
-webkit-backdrop-filter: blur(var(--lb-blur));
}
<?php }?>

<?php if($zzzz==='filter'){?>
.lb-bg{
filter: blur(var(--lb-blur));
}
<?php }?>

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
 if(trim($bbbbb)!==''){echo "\n/* --- custom login css --- */\n";echo $bbbbb."\n";}echo "</style>\n";$hhhhh=self::jsString($xxxx);echo"\n<script id=\"loginb
