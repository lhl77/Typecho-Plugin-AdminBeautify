/**
 * @name        Links Plus 兼容
 * @description 修复 Links Plus 插件「管理友情链接」页面在 AdminBeautify 下的样式问题
 *              ① 亮色模式：插件在页面 body 中注入的 :root {} 块（特异性 0,1,0）晚于
 *                 AdminBeautify head 中注入的 :root{}（同等特异性），导致主题色变量
 *                （--md-primary / --md-primary-container 等）被蓝色覆盖，按钮不跟随
 *                 用户选择的 AdminBeautify 主题色。
 *                 修复：在 DOMContentLoaded 时解析 head 中 AdminBeautify 的 <style>，
 *                 通过 document.documentElement.style.setProperty() 以内联样式
 *                （最高特异性）恢复正确的主题色变量；暗色模式由 AdminBeautify
 *                 style.css 中带 !important 的 [data-theme="dark"] 块自动覆盖，
 *                 无需额外处理。
 *              ② 暗色模式：插件 CSS 中存在大量硬编码的亮色值（#111827、#ffffff 渐变、
 *                 rgba(209,228,255,.7) 等），AdminBeautify 全局规则无法覆盖这些
 *                 Links 专属选择器。注入带 [data-theme="dark"] 的覆盖样式表修复。
 *              已支持 AdminBeautify AJAX 导航（ab:pageload）。
 * @plugins     Links Plus (Links)
 * @version     1.0.0
 * @author      LHL
 */
(function () {
    'use strict';

    var STYLE_ID = 'ab-compat-links';

    // Links 插件在 :root 中覆盖的变量列表（只恢复这些，其余由 AdminBeautify 统一管理）
    var VARS_OVERRIDDEN_BY_LINKS = [
        '--md-primary',
        '--md-primary-container',
        '--md-on-primary-container',
        '--md-surface',
        '--md-surface-variant',
        '--md-outline',
        '--md-outline-variant',
        '--md-surface-container'
    ];

    // ── 暗色模式 CSS 覆盖 ─────────────────────────────────────────────────────
    // 说明：AdminBeautify style.css 已通过 [data-theme="dark"] { --md-xxx: var(--md-dark-xxx) !important }
    //       将 CSS 变量正确切换到暗色值（!important 优先于 Links 插件无 !important 的 :root 块），
    //       故此处无需重复覆盖变量，只需修复硬编码颜色值。
    var CSS = ''
        // 1. AppBar 标题文字：hardcoded #111827 / #6b7280
        + '[data-theme="dark"] .md3-appbar-title b {'
        + '  color: var(--md-on-surface, #e6e1e5) !important;'
        + '}'
        + '[data-theme="dark"] .md3-appbar-title span {'
        + '  color: var(--md-on-surface-variant, #cac4d0) !important;'
        + '}'

        // 2. 基础按钮（帮助、无修饰符按钮）：hardcoded color:#1f2937
        + '[data-theme="dark"] .md3-btn {'
        + '  color: var(--md-on-surface, #e6e1e5) !important;'
        + '}'
        // 2b. danger 按钮文字颜色固定为白色（亮色/暗色均适用）
        //     根因：上方 [data-theme="dark"] .md3-btn { !important } 与
        //     .md3-btn.danger { color:#fff } 特异性相同，!important 的后来者居上，
        //     导致暗色模式下 danger 按钮文字被意外改为 on-surface 色；
        //     此规则无 data-theme 前缀，特异性 (0,2,0) + !important，双模式均覆盖。
        + '.md3-btn.danger {'
        + '  color: #fff !important;'
        + '}'
        // 2c. tonal 按钮 hover：hardcoded rgba(209,228,255,.7)（蓝色亮底）
        + '[data-theme="dark"] .md3-btn.tonal:hover {'
        + '  background: color-mix(in srgb, var(--md-on-primary-container, #d0bcff) 16%, var(--md-primary-container, #4f378b)) !important;'
        + '}'

        // 3. 列表顶部操作栏：hardcoded white gradient 背景
        + '[data-theme="dark"] .manage-list-header {'
        + '  background: var(--md-surface-container, rgba(255,255,255,.06)) !important;'
        + '}'

        // 4. 表格行底部边框：hardcoded #f0f0f0（AdminBeautify 覆盖的是 border-top，不同属性）
        + '[data-theme="dark"] .typecho-list-table td {'
        + '  border-bottom-color: transparent !important;'
        + '}'

        // 5. 说明文字：hardcoded #666（.typecho-option .description 已被全局覆盖，
        //    但 Links 的 .description 用在 .typecho-option 之外，需单独处理）
        + '[data-theme="dark"] .description {'
        + '  color: var(--md-on-surface-variant, #cac4d0) !important;'
        + '}'

        // 6. 状态标签：hardcoded 亮色背景 + 文字
        + '[data-theme="dark"] .status-normal {'
        + '  background: rgba(30,142,62,.18) !important;'
        + '  color: #86efac !important;'
        + '}'
        + '[data-theme="dark"] .status-ban {'
        + '  background: rgba(217,48,37,.18) !important;'
        + '  color: #fca5a5 !important;'
        + '}'

        // 7. 一键检查：行高亮（原规则带 !important，需更高特异性 + !important 才能覆盖）
        + '[data-theme="dark"] .typecho-list-table tr.link-check-fail td {'
        + '  background: rgba(183,28,28,.22) !important;'
        + '}'
        + '[data-theme="dark"] .typecho-list-table tr.link-check-fail:hover td {'
        + '  background: rgba(183,28,28,.32) !important;'
        + '}'
        + '[data-theme="dark"] .typecho-list-table tr.link-check-ok td {'
        + '  background: rgba(27,94,32,.22) !important;'
        + '}'
        + '[data-theme="dark"] .typecho-list-table tr.link-check-ok:hover td {'
        + '  background: rgba(27,94,32,.32) !important;'
        + '}'
        + '[data-theme="dark"] .typecho-list-table tr.link-check-redirect td {'
        + '  background: rgba(130,119,23,.22) !important;'
        + '}'
        + '[data-theme="dark"] .typecho-list-table tr.link-check-redirect:hover td {'
        + '  background: rgba(130,119,23,.32) !important;'
        + '}'
        + '[data-theme="dark"] .typecho-list-table tr.link-check-uncertain td {'
        + '  background: rgba(255,255,255,.04) !important;'
        + '}'
        + '[data-theme="dark"] .typecho-list-table tr.link-check-uncertain:hover td {'
        + '  background: rgba(255,255,255,.08) !important;'
        + '}'

        // 8. 检查结果徽章（行内 hint 标签）：hardcoded #fff 背景 + 亮色文字
        + '[data-theme="dark"] .link-check-hint {'
        + '  background: rgba(255,255,255,.07) !important;'
        + '  color: var(--md-on-surface-variant, #cac4d0) !important;'
        + '  border-color: rgba(255,255,255,.14) !important;'
        + '}'
        + '[data-theme="dark"] .link-check-hint.ok {'
        + '  color: #86efac !important;'
        + '  border-color: rgba(134,239,172,.28) !important;'
        + '}'
        + '[data-theme="dark"] .link-check-hint.redirect {'
        + '  color: #fde68a !important;'
        + '  border-color: rgba(253,230,138,.28) !important;'
        + '}'
        + '[data-theme="dark"] .link-check-hint.uncertain {'
        + '  color: var(--md-on-surface-variant, #cac4d0) !important;'
        + '  border-color: rgba(255,255,255,.18) !important;'
        + '}'
        + '[data-theme="dark"] .link-check-hint.fail {'
        + '  color: #fca5a5 !important;'
        + '  border-color: rgba(252,165,165,.28) !important;'
        + '}';

    // ── 读取 AdminBeautify head <style> 中的亮色主题变量 ───────────────────────
    // AdminBeautify 在 <head> 中注入 <style>:root{--md-primary:#7D5260;...--md-dark-primary:#D0BCFF;...}</style>
    // 该块同时包含亮色和暗色的 --md-dark-* 变量，用两者的存在来定位该节点。
    function readABHeadVars() {
        var styles = document.head.querySelectorAll('style');
        for (var i = 0; i < styles.length; i++) {
            var text = styles[i].textContent || '';
            // 同时包含 --md-primary 与 --md-dark-primary 才是 AdminBeautify 的颜色块
            if (text.indexOf('--md-primary') === -1 || text.indexOf('--md-dark-primary') === -1) {
                continue;
            }
            var m = text.match(/:root\s*\{([\s\S]+?)\}/);
            if (!m) continue;
            var vars = {};
            var parts = m[1].split(';');
            for (var j = 0; j < parts.length; j++) {
                var p = parts[j].trim();
                var ci = p.indexOf(':');
                if (ci > -1) {
                    var k = p.substring(0, ci).trim();
                    var v = p.substring(ci + 1).trim();
                    if (k && k.indexOf('--') === 0) vars[k] = v;
                }
            }
            return vars;
        }
        return null;
    }

    // ── 亮色模式修复：以内联样式恢复被 Links :root 覆盖的变量 ────────────────
    // 内联样式特异性 > 任何 :root{} 规则；暗色模式由 AdminBeautify 的 !important 块
    // 自动胜出，内联样式（无 !important）会被其覆盖，故不会干扰暗色模式。
    function applyLightVarFix() {
        var abVars = readABHeadVars();
        if (!abVars) return;
        for (var i = 0; i < VARS_OVERRIDDEN_BY_LINKS.length; i++) {
            var key = VARS_OVERRIDDEN_BY_LINKS[i];
            if (abVars[key]) {
                document.documentElement.style.setProperty(key, abVars[key]);
            }
        }
    }

    // ── 清理：离开 Links 页面时移除所有注入 ─────────────────────────────────
    function removeFix() {
        var old = document.getElementById(STYLE_ID);
        if (old) old.remove();
        for (var i = 0; i < VARS_OVERRIDDEN_BY_LINKS.length; i++) {
            document.documentElement.style.removeProperty(VARS_OVERRIDDEN_BY_LINKS[i]);
        }
    }

    // ── 核心修复函数（幂等，可重复调用）─────────────────────────────────────
    function applyFix(url) {
        var isLinksPage = (url || '').indexOf('Links') !== -1;

        if (!isLinksPage) {
            removeFix();
            return;
        }

        // 注入暗色模式覆盖样式（幂等）
        if (!document.getElementById(STYLE_ID)) {
            var style = document.createElement('style');
            style.id = STYLE_ID;
            style.textContent = CSS;
            document.head.appendChild(style);
        }

        // 恢复亮色模式主题色变量
        applyLightVarFix();
    }

    // ── 初始执行（页面首次加载）──────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            applyFix(window.location.href);
        });
    } else {
        applyFix(window.location.href);
    }

    // ── 监听 AdminBeautify AJAX 导航事件（ab:pageload）──────────────────────
    // extending.php 页面在 AdminBeautify 中触发全页刷新（不走 AJAX），
    // 但仍监听此事件以应对后续架构变化。
    document.addEventListener('ab:pageload', function (e) {
        var url = (e && e.detail && e.detail.url) ? e.detail.url : window.location.href;
        applyFix(url);
    });
})();
