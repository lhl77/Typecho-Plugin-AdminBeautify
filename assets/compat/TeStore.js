/**
 * @name        TeStore 兼容
 * @description 修复 TeStore 插件仓库页面在 AdminBeautify 下的样式冲突：内联 .error 标签异常块化、操作列按钮溢出、底部 .notice 链接样式错乱、暗色模式 SVG 图标不可见、AJAX 导航导致页面重复渲染等。
 *
 * 触发页面：
 *   - 插件设置页  options-plugin.php?config=TeStore
 *   - 仓库市场    te-store/market（自定义路由）
 *
 * @plugins     TeStore
 * @version     1.0.0
 * @author      LHL
 */
(function () {
    'use strict';

    var STYLE_ID = 'ab-compat-testore';

    /* ─────────────────────────────────────────────────────────────
     * 策略说明
     *   - TeStore 市场页大量使用 Typecho 标准 class（.typecho-list-table、
     *     .btn、.btn-xs、.btn-warn、.typecho-option-tabs 等），
     *     AdminBeautify 全局样式已覆盖。
     *   - 本脚本仅针对「AdminBeautify 全局样式与 TeStore 特殊用法冲突」的部分
     *     做最小范围修正。
     * ───────────────────────────────────────────────────────────── */

    var CSS = ''

        /* ══════════════════════════════════════════
         * 1. 内联 .error 标签（"有新版本！"）
         *    AdminBeautify .error 全局样式为 block 级错误横幅：
         *      background: var(--md-error-container)
         *    但 TeStore 在 <td> 内使用 <span class="error"> 做内联提示，
         *    块化后背景铺满整个单元格，极不协调。
         *    改：保留 error-container 色调，做成 MD3 小药丸 chip
         * ══════════════════════════════════════════ */
        + '.typecho-list-table td .error {'
        + '  display: inline !important;'
        + '  padding: 2px 8px !important;'
        + '  border-radius: var(--md-radius-full, 9999px) !important;'
        + '  font-size: 12px !important;'
        + '  font-weight: 500 !important;'
        + '  white-space: nowrap !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 2. 操作列按钮（安装 / 删除 / 升级）
         *    TeStore 操作列仅占 10%（~80-90px），
         *    AdminBeautify .btn-xs 强制 padding:0 16px，
         *    按钮总宽 ≈ 58px，在 td padding:16px 后仅剩 ~48-58px
         *    可能被挤压。缩小内边距以确保按钮完整可见。
         * ══════════════════════════════════════════ */
        + '.typecho-list-table td form .btn-xs {'
        + '  padding: 0 12px !important;'
        + '  min-width: 0 !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 3. 底部 "我要添加插件信息" 链接
         *    原标记 <a class="tflink profile-avatar notice">
         *    AdminBeautify .notice 给了块级 primary-container 背景，
         *    与 .profile-avatar 合并后变成一个怪异色块。
         *    改：重置为 MD3 text-button 风格
         * ══════════════════════════════════════════ */
        + 'a.tflink.notice {'
        + '  background: transparent !important;'
        + '  color: var(--md-primary) !important;'
        + '  padding: 6px 12px !important;'
        + '  border-radius: var(--md-radius-full, 9999px) !important;'
        + '  font-weight: 500 !important;'
        + '  transition: background var(--md-transition-duration, .2s) !important;'
        + '}'
        + 'a.tflink.notice:hover {'
        + '  background: color-mix(in srgb, var(--md-primary) 8%, transparent) !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 4. 空结果 / 错误提示文字 .list-notice
         *    原仅 text-align:center，暗色下颜色依赖默认值。
         *    改：使用 MD3 on-surface-variant 保证可读性
         * ══════════════════════════════════════════ */
        + '.list-notice {'
        + '  color: var(--md-on-surface-variant) !important;'
        + '  padding: 40px 16px !important;'
        + '  font-size: 14px !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 5. AJAX 加载状态文字 .loading
         *    加载中提示需在暗色背景下可见
         * ══════════════════════════════════════════ */
        + '.list-notice .loading {'
        + '  color: var(--md-on-surface-variant) !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 6. 暗色模式 SVG 图标亮度
         *    gh.svg  fill:#8A6D3B（深棕）在暗色背景上几乎不可见
         *    tf.svg  fill:#457a95（深青）偏暗也需提亮
         *    改：暗色下统一提亮 <img> 图标
         * ══════════════════════════════════════════ */
        + '[data-theme="dark"] .typecho-list-table td img {'
        + '  filter: brightness(1.6) !important;'
        + '}'
        + '[data-theme="dark"] .tflink img {'
        + '  filter: brightness(1.6) !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 7. 搜索区域 .search 内按钮与输入框对齐
         *    AdminBeautify input 有 12px 16px padding，
         *    .text-s 高度可能与 .btn-s 不一致
         *    改：确保搜索框行高一致
         * ══════════════════════════════════════════ */
        + '.search input.text-s {'
        + '  height: 36px !important;'
        + '  padding: 0 12px !important;'
        + '  box-sizing: border-box !important;'
        + '}'

        ;

    /* ─── 注入 / 移除 ───────────────────────────────────────────── */

    function applyFix(url) {
        var u = url || '';
        var isTarget = u.indexOf('TeStore') !== -1 || u.indexOf('te-store') !== -1;

        if (!isTarget) {
            var old = document.getElementById(STYLE_ID);
            if (old) old.parentNode.removeChild(old);
            return;
        }

        if (!document.getElementById(STYLE_ID)) {
            var style = document.createElement('style');
            style.id = STYLE_ID;
            style.textContent = CSS;
            document.head.appendChild(style);
        }
    }

    /* ─── AJAX 导航拦截 ──────────────────────────────────────────
     *  AdminBeautify 的 PJAX 导航会把 te-store/ 自定义路由页面
     *  当作普通 admin 页面 AJAX 加载。但 TeStore 市场页自身依赖
     *  jQuery AJAX 加载插件列表、绑定表单事件，脚本上下文与 PJAX
     *  替换不兼容，导致页面重复渲染或功能失效。
     *
     *  解决：在捕获阶段拦截所有指向 te-store/ 的链接点击，
     *  阻止事件继续传播到 AdminBeautify 的冒泡阶段处理器，
     *  改为浏览器原生整页跳转。
     * ────────────────────────────────────────────────────────── */
    function interceptAjaxNav() {
        document.addEventListener('click', function (e) {
            var link = e.target.closest ? e.target.closest('a') : null;
            if (!link || !link.href) return;
            // 放行外链
            if (link.target === '_blank') return;
            // 仅拦截 te-store/ 路由链接
            if (link.href.indexOf('te-store/') === -1) return;
            // 表单内链接不拦截
            if (link.closest && link.closest('form')) return;

            e.preventDefault();
            e.stopPropagation();
            window.location.href = link.href;
        }, true); // capture phase — 先于 AdminBeautify 冒泡阶段处理器
    }

    /* 立即注册拦截器（不依赖当前页面是否为 TeStore） */
    interceptAjaxNav();

    /* 首次加载 */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            applyFix(window.location.href);
        });
    } else {
        applyFix(window.location.href);
    }

    /* AdminBeautify AJAX 页面切换 */
    document.addEventListener('ab:pageload', function (e) {
        var url = (e && e.detail && e.detail.url) ? e.detail.url : window.location.href;
        applyFix(url);
    });

})();
