/**
 * @name        Notice 插件兼容
 * @description 修复 Notice 插件在 AdminBeautify 下的兼容问题。
 *              v1.x：修复「编辑邮件模版」和「配置测试」页面文本框过窄，以及侧边栏博客名称前多余 "-" 前缀，
 *                    并修复 AdminBeautify 的 `button.primary { display:inline-flex !important }` 导致
 *                    notice.css 中 `.btn.primary { display:none }` 失效（保存按钮重复显示）。
 *              v2.x：修复 AJAX 导航时 notice.css / mdui.css 不被加载导致的排版错乱，
 *                    并重新执行 notice.js 初始化逻辑（展开错误面板、移除 hero desc 等）。
 *                    同时修复 AB 的 `!important` 导致保存按钮重复显示，添加暗色模式适配，
 *                    以及防止 v2.0.0 独立页面错误修改 ab-sidebar-title 内容。
 * @plugins     Notice
 * @version     2.0.2
 * @author      LHL
 *
 * Changelog:
 *   2.0.2 – 修复 [data-theme="dark"] 下编辑邮件模板/配置测试页面文本框无暗色样式；
 *           修复 notice-md3-panel-zone 各 section 卡片在暗色模式下显示为亮白色；
 *           修复 submit_only 保存按钮在暗色模式（浅紫背景+白字）对比度过低；
 *           修复 edit-template.php / test.php 页面错误修改 ab-sidebar-title 的问题。
 */
(function () {
    'use strict';

    // ── 常量 ────────────────────────────────────────────────────────────────────
    var STYLE_ID_V1  = 'ab-compat-notice-v1';    // v1.x 布局修复 <style>
    var STYLE_ID_V2  = 'ab-compat-notice-v2';    // v2.x AB 冲突修复 <style>
    var LINK_ID_MDUI = 'ab-compat-notice-mdui';  // mdui.css 的 <link>（data-ab-dynamic）
    var LINK_ID_CSS  = 'ab-compat-notice-css';   // notice.css 的 <link>（data-ab-dynamic）

    var MDUI_CSS_URL = 'https://cdn.jsdelivr.net/npm/mdui@0.4.3/dist/css/mdui.min.css';

    // ── 运行时缓存 ────────────────────────────────────────────────────────────
    var cachedNoticeCssUrl = null;

    // ── v1.x 布局修复 CSS ────────────────────────────────────────────────────
    // Notice v1.x HTML 结构：
    //   <div class="typecho-edit-theme">
    //     <div class="col-mb-12 col-tb-8 col-9 content">…</div>  ← 主内容（有 .content 类）
    //     <ul class="col-mb-12 col-tb-4 col-3">…</ul>            ← 侧边栏是 <ul>
    //   </div>
    var CSS_V1 = ''
        + '.typecho-edit-theme {'
        + '  display: flex !important;'
        + '  flex-wrap: nowrap !important;'
        + '  align-items: flex-start !important;'
        + '  width: 100% !important;'
        + '  gap: 0 !important;'
        + '}'
        + '.typecho-edit-theme > .content {'
        + '  flex: 1 1 0% !important;'
        + '  max-width: none !important;'
        + '  width: 100% !important;'
        + '}'
        + '.typecho-edit-theme > ul {'
        + '  flex: 0 0 200px !important;'
        + '  max-width: 200px !important;'
        + '  width: 200px !important;'
        + '}'
        + '.typecho-edit-theme textarea {'
        + '  width: 100% !important;'
        + '  min-height: 500px !important;'
        + '  box-sizing: border-box !important;'
        + '  resize: vertical !important;'
        + '}'
        + '@media (max-width: 767px) {'
        + '  .typecho-edit-theme { flex-wrap: wrap !important; }'
        + '  .typecho-edit-theme > .content,'
        + '  .typecho-edit-theme > ul {'
        + '    flex: 0 0 100% !important;'
        + '    max-width: 100% !important;'
        + '    width: 100% !important;'
        + '  }'
        + '}'
        + '@media (prefers-color-scheme: dark) {'
        + '  .typecho-edit-theme textarea {'
        + '    background: #1e1e2e !important;'
        + '    color: #cdd6f4 !important;'
        + '    border-color: #45475a !important;'
        + '  }'
        + '}'
        // AdminBeautify 通过 [data-theme="dark"] 属性驱动暗色模式，prefers-color-scheme 的
        // CSS 优先级可能被 AB 覆盖，因此额外添加属性选择器版本以保证暗色样式生效。
        + '[data-theme="dark"] .typecho-edit-theme textarea {'
        + '  background: #1e1e2e !important;'
        + '  color: #cdd6f4 !important;'
        + '  border-color: #45475a !important;'
        + '}'
        // 修复保存按钮：AB 的 `button.primary { display:inline-flex !important }` 会覆盖
        // notice.css 的 `button.btn.primary { display:none }`（无 !important），导致两个保存按钮同时显示。
        + '.typecho-option-submit button.btn.primary {'
        + '  display: none !important;'
        + '}';

    // ── v2.x AdminBeautify 冲突修复 CSS ──────────────────────────────────────
    // 根因：notice.css 由 Config::style() 输出在 <body> 中（位于菜单与 .main 之间），
    // 而非 <head>。AdminBeautify AJAX 导航只提取 <head> 中的 <link>，body 级 <link>
    // 既不在 .main 内部也不在 <head> 中，因此完全被丢弃——导致 AJAX 后 notice.css /
    // mdui.css 未加载，页面排版彻底错乱。此处同时修复 AB 自身 CSS 与 Notice 2.0.0
    // 的细节冲突（动画闪烁、布局行宽等）。
    var CSS_V2 = ''
        // 禁用 AB 对 .container 的入场动画（Notice 2.0.0 自带渐变，两者叠加会闪烁）
        + '.notice-md3-shell .container,'
        + '.notice-md3-shell .body.container {'
        + '  animation: none !important;'
        + '}'
        // 防止 AB 的 [data-nav="left"] .row { width:100% } 破坏 Notice 2.0.0 布局
        + '.notice-md3-shell .row {'
        + '  width: auto;'
        + '}'
        // 编辑器双栏 grid 兜底（确保 AB 不覆盖 notice.css 的 grid 定义）
        + '.notice-md3-editor-layout {'
        + '  display: grid !important;'
        + '  grid-template-columns: minmax(0, 1fr) 340px !important;'
        + '  gap: 56px !important;'
        + '}'
        + '@media (max-width: 960px) {'
        + '  .notice-md3-editor-layout {'
        + '    grid-template-columns: 1fr !important;'
        + '  }'
        + '}'
        // 修复保存按钮：AB 的 `button.primary { display:inline-flex !important }` 会覆盖
        // notice.css 的 `.typecho-option-submit button.btn.primary { display:none }`（无 !important），
        // 导致标准 .btn.primary 与 .submit_only FAB 同时显示（双重保存按钮）。
        + '.typecho-option-submit button.btn.primary,'
        + '.notice-md3-shell .typecho-option-submit button.btn.primary {'
        + '  display: none !important;'
        + '}'
        // ── 暗色模式适配（Notice 2.0.0 的 notice.css 无暗色模式支持）──────────────
        // AdminBeautify 使用 [data-theme="dark"] 切换暗色模式（与 prefers-color-scheme 联动），
        // 在此覆盖所有 --notice-md3-* 变量以适配 MD3 深色色调映射（Tonal Palette）。
        + '[data-theme="dark"] {'
        + '  --notice-md3-primary: #d0bcff;'
        + '  --notice-md3-primary-strong: #b69df8;'
        + '  --notice-md3-primary-soft: rgba(208, 188, 255, 0.12);'
        + '  --notice-md3-secondary: #ccc2dc;'
        + '  --notice-md3-surface: #1c1b1f;'
        + '  --notice-md3-surface-soft: #141218;'
        + '  --notice-md3-card: rgba(49, 48, 51, 0.86);'
        + '  --notice-md3-card-strong: rgba(49, 48, 51, 0.94);'
        + '  --notice-md3-outline: rgba(208, 188, 255, 0.16);'
        + '  --notice-md3-outline-strong: rgba(208, 188, 255, 0.26);'
        + '  --notice-md3-text: #e6e1e5;'
        + '  --notice-md3-text-muted: #cac4d0;'
        + '  --notice-md3-danger: #f2b8b5;'
        + '  --notice-md3-shadow: 0 20px 46px rgba(0, 0, 0, 0.4);'
        + '  --notice-md3-shadow-soft: 0 10px 24px rgba(0, 0, 0, 0.24);'
        + '}'
        // Notice 2.0.0 的 Hero 背景在暗色模式下颜色需相应调整
        + '[data-theme="dark"] .notice-md3-hero {'
        + '  background:'
        + '    radial-gradient(circle at top right, rgba(255, 255, 255, 0.08), transparent 32%),'
        + '    linear-gradient(135deg, #4a3880 0%, #5c4b9a 52%, #7259cc 100%);'
        + '}'
        // Notice 2.0.0 的 .typecho-option（设置块白色卡片背景）在暗色模式下需适配
        + '[data-theme="dark"] .typecho-option {'
        + '  background: var(--md-surface-container-high, #2b2930) !important;'
        + '  color: var(--notice-md3-text);'
        + '}'
        // ── notice-md3-panel-zone 各 section 卡片暗色修复 ────────────────────────
        // notice.css 中 .notice-md3-panels > .notice-md3-section 使用了硬编码的
        // rgba(255,255,255,...) 白色渐变背景（带 !important），在暗色模式下显示为
        // 突兀的亮白色卡片，需通过更高优先级选择器进行覆盖。
        + '[data-theme="dark"] .notice-md3-panels > .notice-md3-section {'
        + '  background: linear-gradient(180deg, rgba(48, 45, 55, 0.9), rgba(36, 33, 42, 0.82)) !important;'
        + '}'
        // section 标题栏同样使用硬编码白色渐变背景
        + '[data-theme="dark"] .notice-md3-section > .notice-md3-section__header {'
        + '  background: linear-gradient(180deg, rgba(48, 45, 55, 0.92), rgba(36, 33, 42, 0.68)) !important;'
        + '  border-bottom-color: rgba(208, 188, 255, 0.08) !important;'
        + '  color: white !important;'
        + '}'
        + '.notice-md3-field > .notice-md3-field__header,'
        + '.mdui-panel-item-open > .notice-md3-field__header {'
        + '  border-radius: 24px !important;'
        + '}'
        // ── 保存按钮暗色修复 ───────────────────────────────────────────────────
        // .submit_only 在 notice.css 中使用 color: #fff !important（硬编码白色），
        // 暗色模式下 --notice-md3-primary 为 #d0bcff（浅紫色），白色文字对比度极差。
        // 使用 MD3 on-primary-dark 颜色（深色文字）以确保足够的对比度。
        + '[data-theme="dark"] .submit_only {'
        + '  color: #1c1b1f !important;'
        + '  box-shadow: 0 16px 32px rgba(103, 80, 164, 0.15) !important;'
        + '}'
        + '[data-theme="dark"] .typecho-option {'
        + '  background: none !important;'
        + '}'
        // mdui-panel中元素修复
        + '[data-theme="dark"] .notice-md3-field > .notice-md3-field__header,'
        + '[data-theme="dark"] .mdui-panel-item-open > .notice-md3-field__header {'
        + '  background: var(--md-surface-container-high, #2b2930) !important;'
        + '  color: white !important;'
        + '}'
        + '[data-theme="dark"] .notice-md3-field-panels > .notice-md3-field {'
        + '  background: #373342 !important;'
        + '}'
        + '[data-theme="dark"] .notice-md3-choice {'
        + '  background: #373342 !important;'
        + '}'
        + '[data-theme="dark"] .notice-md3-choice span{'
        + '  color: white !important;'
        + '}'
        + '[data-theme="dark"] .notice-md3-choice:hover{'
        + '  background: #4a3880 !important;'
        + '}'
        + '[data-theme="dark"] .notice-md3-panels .description, .description {'
        + '  color: #a7a7a7 !important;'
        + '}'
        + '[data-theme="dark"] .mdui-textfield-input {'
        + '  box-shadow:none !important;'
        + '}'
        // mdui 元素圆角修复
        + '.notice-md3-field > .notice-md3-field__header,'
        + '.mdui-panel-item-open > .notice-md3-field__header {'
        + '  border-radius:24px !important'
        + '}'
        + '[data-theme="dark"] .notice-md3-editor-card, [data-theme="dark"] .notice-md3-editor-sidebar{'
        + '  background: #3b3852 !important;'
        + '}'
        + '[data-theme="dark"] .notice-md3-editor-sidebar__title{'
        + '   color: white !important;'
        + '}'
        + '[data-theme="dark"] .notice-md3-file-item a{'
        + '  background: rgb(47, 44, 65) !important;'
        + '}'
        + '[data-theme="dark"] .notice-md3-file-item a:hover{'
        + '  background: rgb(63, 59, 78) !important;'
        + '}'
        + '[data-theme="dark"] .notice-md3-utility-card{'
        + '  background: #3b3852 !important;'
        + '}'
        + '[data-theme="dark"] .notice-md3-utility-form .typecho-option{'
        + '  background: #3b3852 !important;'
        + '}'
        + '.notice-md3-tab.is-current{'
        + '  color:white !important;'
        + '}'
        + '[data-theme="dark"] .notice-md3-tab{'
        + '  color:#3b3852 !important;'
        + '}'
        + '[data-theme="dark"] .notice-md3-tab.is-current{'
        + '  color:white !important;'
        + '}'
        ;

    // ── 工具函数 ──────────────────────────────────────────────────────────────
    function injectStyleOnce(id, css) {
        if (!document.getElementById(id)) {
            var el = document.createElement('style');
            el.id = id;
            el.textContent = css;
            document.head.appendChild(el);
        }
    }

    // 注入外链样式表并标记 data-ab-dynamic（AB 在下次 AJAX 导航前会自动清理）
    function injectLinkDynamic(id, href) {
        if (document.getElementById(id)) return; // 幂等：已存在则跳过
        var el = document.createElement('link');
        el.id = id;
        el.rel = 'stylesheet';
        el.href = href;
        el.setAttribute('data-ab-dynamic', 'true');
        document.head.appendChild(el);
    }

    function removeById(id) {
        var el = document.getElementById(id);
        if (el) el.remove();
    }

    // 清理 Config::style() 在 body 中留下的 notice.css / mdui.css <link>
    // 场景：首次直接访问 Notice 2.0.0 页面后，通过 AJAX 导航离开时调用
    function cleanBodyNoticeLinks() {
        var links = document.querySelectorAll(
            'link[href*="Notice/assets/notice.css"], link[href*="mdui@0.4.3"]'
        );
        for (var i = 0; i < links.length; i++) {
            links[i].remove();
        }
    }

    // 获取 notice.css 的完整 URL（带缓存）
    // 策略1：从文档中现有的 <link> 标签提取（首次直接访问 Notice 2.0.0 页面时，
    //         Config::style() 已将链接输出至 body，可在此读取）
    // 策略2：从 AB 注入的 notice.js <script> 标签推导（AJAX 首次导航到 Notice 2.0.0 时，
    //         _executeScripts 会将 notice.js 加入 body，可由此派生 CSS 路径）
    // 策略3：缓存命中（后续每次调用直接返回已缓存的 URL）
    function getNoticeCssUrl() {
        if (cachedNoticeCssUrl) return cachedNoticeCssUrl;

        // 策略1：查找文档中任意位置的 notice.css link 标签（body-level 首次加载时存在）
        var bodyLink = document.querySelector('link[href*="Notice/assets/notice.css"]');
        if (bodyLink && bodyLink.href) {
            cachedNoticeCssUrl = bodyLink.href;
            return cachedNoticeCssUrl;
        }

        // 策略2：从 notice.js 脚本标签的 src 推导（将 /notice.js 替换为 /notice.css）
        var noticeScript = document.querySelector('script[src*="Notice/assets/notice.js"]');
        if (noticeScript && noticeScript.src) {
            cachedNoticeCssUrl = noticeScript.src.replace(/(\/notice)\.js(\?|$)/, '$1.css$2');
            return cachedNoticeCssUrl;
        }

        return null;
    }

    // ── v2.x 特有修复 ────────────────────────────────────────────────────────
    function applyV2Fix() {
        // 1. 注入 mdui.css（data-ab-dynamic，AJAX 离开时由 AB 自动清理）
        injectLinkDynamic(LINK_ID_MDUI, MDUI_CSS_URL);

        // 2. 注入 notice.css（data-ab-dynamic，URL 来自缓存或实时提取）
        var noticeCssUrl = getNoticeCssUrl();
        if (noticeCssUrl) {
            injectLinkDynamic(LINK_ID_CSS, noticeCssUrl);
        }

        // 3. 注入 AB 冲突修复样式（选择器已精确至 .notice-md3-*，无需 data-ab-dynamic）
        injectStyleOnce(STYLE_ID_V2, CSS_V2);

        // 4. 补跑 notice.js 初始化逻辑
        //    notice.js 用 $(function(){}), AB 在 AJAX 后如脚本已加载则跳过执行，
        //    因此需在此处手动重新执行其初始化内容。
        reinitNoticeV2();
        restoreSidebarTitle() 
    }

    // 重新执行 notice.js 的初始化内容（面板展开 + hero desc 移除）
    function reinitNoticeV2() {
        if (typeof window.$ !== 'function') return;
        var shell = window.$('.notice-md3-shell');
        if (shell.length) {
            shell.find('.notice-md3-hero__desc').remove();
        }
        window.$('.error.message').each(function () {
            window.$(this).closest('.notice-md3-field, .notice-md3-section')
                .addClass('mdui-panel-item-open');
        });
    }

    // 修复侧边栏博客名称前多余的 "-" 前缀（仅用于 v1.x 配置页，兜底兼容旧版 AB）
    function fixSidebarTitle() {
        var titleEl = document.querySelector('.ab-sidebar-title');
        if (titleEl) {
            titleEl.textContent = titleEl.textContent.replace(/^[\s\-]+/, '');
        }
    }

    // 从 window.__AB_CONFIG__.siteName 还原侧边栏标题（用于独立面板子页面）
    // AB v2.1.12 的 sidebarInjectHeader 已能正确处理空 subTitle 面板，但作为防御性
    // 兜底，确保 edit-template.php / test.php 页面侧边栏标题始终显示正确的博客名。
    function restoreSidebarTitle() {
        var cfg = window.__AB_CONFIG__ || {};
        var siteName = cfg.siteName || '';
        if (!siteName) return;
        var titleEl = document.querySelector('.ab-sidebar-title');
        if (titleEl && titleEl.textContent !== siteName) {
            titleEl.textContent = siteName;
        }
    }

    // ── 主修复函数（幂等，可重复调用）───────────────────────────────────────
    function applyFix(url) {
        var isNoticePage = (url || '').indexOf('Notice') !== -1;

        if (!isNoticePage) {
            // 离开 Notice 页面：清理所有注入的样式
            removeById(STYLE_ID_V1);
            removeById(STYLE_ID_V2);
            // 额外清理首次直接加载 Notice 2.0.0 页面后残留在 body 中的 CSS 链接
            // （AB 的 data-ab-dynamic 机制只清理 head 中的标记元素，无法处理 body 残留）
            cleanBodyNoticeLinks();
            return;
        }

        // 版本检测：v2.0.0 特征类（模版/测试页用 .notice-md3-shell，配置页用 .notice-md3-panels）
        var isV2 = !!(document.querySelector('.notice-md3-shell') ||
                      document.querySelector('.notice-md3-panels'));

        // 独立子页面检测：edit-template.php / test.php
        // 实际 URL 格式：extending.php?panel=Notice%2Fpage%2Ftest.php
        //              / extending.php?panel=Notice%2Fpage%2Fedit-template.php
        // 使用正则精确匹配 panel 参数值，兼容 %2F 编码和 / 未编码两种形式，
        // 避免仅检测文件名导致误匹配其他同名页面。 
        // 这两个页面使用标准 Typecho admin 布局（.typecho-edit-theme），
        // 不带 .notice-md3-shell/.notice-md3-panels，isV2 = false，
        // 但同样不应由 fixSidebarTitle() 处理（AB v2.1.12 已内置对空 subTitle 的修复）。
        var isPanelSubpage = /Notice(%2F|\/)(page)(%2F|\/)(test\.php|edit-template\.php)/i.test(url || '');

        if (isV2) {
            removeById(STYLE_ID_V1);  // 防止版本切换时遗留 v1 样式
            applyV2Fix();
            // v2.0.0：Notice 的独立页面（edit-template / test）内联 JS 已正确修改
            // document.title（格式："页面名 - 博客名 - Powered by Typecho"），
            // AB v2.1.12 的 sidebarInjectHeader 解析算法（取最后一个 " - " 后的部分）
            // 可正确提取博客名，无需再修改 .ab-sidebar-title，
            // 否则可能在某些边缘情况下将侧边栏标题改为错误内容。
        } else if (isPanelSubpage) {
            // edit-template.php / test.php：应用布局修复样式，但不调用 fixSidebarTitle()
            // AB v2.1.12 已正确处理此类页面的侧边栏标题；
            // restoreSidebarTitle() 作为防御性兜底，从 __AB_CONFIG__.siteName 恢复。
            removeById(STYLE_ID_V2);
            injectStyleOnce(STYLE_ID_V1, CSS_V1);
            restoreSidebarTitle();
        } else {
            // v1.x 修复
            removeById(STYLE_ID_V2);  // 防止版本切换时遗留 v2 样式
            injectStyleOnce(STYLE_ID_V1, CSS_V1);
            // v1.x：修复侧边栏博客名称前多余的 "-" 前缀
            // 根因：Notice 的 addPanel 第4参数（subTitle）为空字符串，导致页面标题为
            // " - BlogName - Powered by Typecho"，浏览器修剪后变为 "- BlogName - …"。
            // AB v2.1.12 已在 sidebarInjectHeader 中内置此修复（stripped.replace(/^-\s+/,''）），
            // 但此处保留作为兜底，以兼容可能存在的旧版 AB 或异常场景。
            fixSidebarTitle();
        }
    }

    // ── 初始执行（页面首次加载）─────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            applyFix(window.location.href);
        });
    } else {
        applyFix(window.location.href);
    }

    // ── 监听 AdminBeautify AJAX 导航事件（ab:pageload）──────────────────────
    // AB 在每次 AJAX 导航完成（history.pushState 之后）派发此事件。
    // 此时 [data-ab-dynamic] 已清理、.main 内容已替换、新页面脚本已执行。
    document.addEventListener('ab:pageload', function (e) {
        var url = (e && e.detail && e.detail.url) ? e.detail.url : window.location.href;
        applyFix(url);
    });
})();
