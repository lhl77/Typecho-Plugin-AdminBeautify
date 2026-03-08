/**
 * @name        TelegramNotice 兼容
 * @description 为 TelegramNotice 插件的设置页与「文章推送」独立面板
 *              全面注入 Material Design 3 风格（亮/暗色均适用），
 *              并适配移动端响应式布局。
 *
 * 触发页面：
 *   - 插件设置页  options-plugin.php?config=TelegramNotice
 *   - 推送面板    extending.php?panel=TelegramNotice/push.php
 *
 * @plugins     TelegramNotice
 * @version     1.0.0
 * @author      LHL
 */
(function () {
    'use strict';

    var STYLE_ID = 'ab-compat-telegramnotice';

    /* ─────────────────────────────────────────────────────────────
     * 策略说明
     *   - 全部使用 AdminBeautify 已定义的 MD3 CSS 变量
     *   - 变量在 [data-theme="dark"] 时由 AdminBeautify 自动切换
     *   - 因此无需重复写 [data-theme="dark"] 规则，一套 CSS 通用
     *   - 仅 .tg-ver-ok 在暗色下需单独覆盖（绿色需更亮）
     * ───────────────────────────────────────────────────────────── */

    var CSS = ''

        /* ══════════════════════════════════════════
         * 1. 卡片容器 .tg-card
         *    原：灰底 #f2f2f2 + 细边框 #e5e5e5 + radius 6px
         *    改：MD3 surface-container-low + outline-variant + radius-xl + 阴影
         * ══════════════════════════════════════════ */
        + '.tg-card {'
        + '  background: var(--md-surface-container-low) !important;'
        + '  border: 1px solid var(--md-outline-variant) !important;'
        + '  border-radius: var(--md-radius-xl, 28px) !important;'
        + '  padding: 20px 24px !important;'
        + '  box-shadow: var(--md-elevation-1) !important;'
        + '  transition: box-shadow var(--md-transition-duration, .2s) var(--md-transition-easing, ease) !important;'
        + '}'
        + '.tg-card:hover {'
        + '  box-shadow: var(--md-elevation-2) !important;'
        + '}'

        /* ── 卡片内 section 标题 ── */
        + '.tg-card > h2, .tg-card > h3 {'
        + '  color: var(--md-on-surface) !important;'
        + '  font-size: 16px !important;'
        + '  font-weight: 600 !important;'
        + '  letter-spacing: -0.01em !important;'
        + '  padding-left: 10px !important;'
        + '  border-left: 3px solid var(--md-primary) !important;'
        + '  margin: 0 0 16px !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 2. 搜索栏容器 .tg-stickybar
         *    原：灰底 #f2f2f2 + radius 6px
         *    改：已在 .tg-card 内，去掉嵌套方框感，保留底部间距
         * ══════════════════════════════════════════ */
        + '.tg-stickybar {'
        + '  background: transparent !important;'
        + '  border: none !important;'
        + '  border-radius: 0 !important;'
        + '  padding: 0 0 12px 0 !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 3. 输入框 .tg-input
         *    原：border #d9d9d9，无背景
         *    改：MD3 outlined text field 风格，focus 时主色描边
         * ══════════════════════════════════════════ */
        + '.tg-input {'
        + '  background: var(--md-surface) !important;'
        + '  border: 1px solid var(--md-outline) !important;'
        + '  border-radius: var(--md-radius-xs, 6px) !important;'
        + '  color: var(--md-on-surface) !important;'
        + '  transition: border-color var(--md-transition-duration, .2s) !important;'
        + '}'
        + '.tg-input:focus {'
        + '  border-color: var(--md-primary) !important;'
        + '  outline: none !important;'
        + '  box-shadow: 0 0 0 3px color-mix(in srgb, var(--md-primary) 18%, transparent) !important;'
        + '}'
        + '.tg-input::placeholder {'
        + '  color: var(--md-on-surface-variant) !important;'
        + '  opacity: .7 !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 4. 徽章 / Chip .tg-badge
         *    原：灰底 #f3f3f3 + 深灰字 #555
         *    改：MD3 surface-container-high + on-surface-variant + 字重
         * ══════════════════════════════════════════ */
        + '.tg-badge {'
        + '  background: var(--md-surface-container-high) !important;'
        + '  color: var(--md-on-surface-variant) !important;'
        + '  border-radius: var(--md-radius-full, 9999px) !important;'
        + '  font-size: 12px !important;'
        + '  font-weight: 500 !important;'
        + '  padding: 3px 10px !important;'
        + '  line-height: 18px !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 5. 错误/危险行 .tg-danger
         *    原：粉底 #fff5f5 + 浅红边 #ffd6d6
         *    改：MD3 error-container（暗色由 AdminBeautify 自动切换为 #8C1D18）
         * ══════════════════════════════════════════ */
        + '.tg-danger {'
        + '  background: var(--md-error-container, #FFDAD6) !important;'
        + '  border-color: color-mix(in srgb, var(--md-error, #B3261E) 40%, transparent) !important;'
        + '  color: var(--md-on-error-container, #410E0B) !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 6. 静音文字 .tg-muted
         *    原：硬编码 #777
         *    改：MD3 on-surface-variant
         * ══════════════════════════════════════════ */
        + '.tg-muted {'
        + '  color: var(--md-on-surface-variant) !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 7. Toast 提示 .tg-toast
         *    原：黑底 rgba(0,0,0,.82)，无品牌感
         *    改：MD3 surface-container-highest + 描边 + 阴影
         * ══════════════════════════════════════════ */
        + '.tg-toast {'
        + '  background: var(--md-surface-container-highest) !important;'
        + '  color: var(--md-on-surface) !important;'
        + '  border: 1px solid var(--md-outline-variant) !important;'
        + '  border-radius: var(--md-radius-md, 12px) !important;'
        + '  font-size: 14px !important;'
        + '  font-weight: 500 !important;'
        + '  letter-spacing: 0.01em !important;'
        + '  padding: 12px 18px !important;'
        + '  box-shadow: var(--md-elevation-3) !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 8. 版本/状态颜色（设置页）
         *    原：#1e8e3e / #d63638 / #666
         *    改：MD3 语义颜色变量
         * ══════════════════════════════════════════ */
        + '.tg-ver-ok   { color: var(--md-tertiary,      #386A20) !important; }'
        + '.tg-ver-warn { color: var(--md-error,         #B3261E) !important; }'
        + '.tg-ver-muted{ color: var(--md-on-surface-variant)     !important; }'
        /* 暗色下 tertiary 不够亮，单独覆盖为更亮的绿色 */
        + '[data-theme="dark"] .tg-ver-ok { color: #81c995 !important; }'

        /* ══════════════════════════════════════════
         * 9. Webhook 操作结果 <pre>
         *    原：Typecho 默认 pre 背景 #DDD
         *    改：MD3 代码块风格
         * ══════════════════════════════════════════ */
        + '#tg-webhook-result {'
        + '  background: var(--md-surface-container) !important;'
        + '  color: var(--md-on-surface) !important;'
        + '  border: 1px solid var(--md-outline-variant) !important;'
        + '  border-radius: var(--md-radius-sm, 8px) !important;'
        + '  padding: 12px 14px !important;'
        + '  font-size: 13px !important;'
        + '  line-height: 1.6 !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 10. 页面大标题（裸 h2）
         *     改：MD3 display 字体风格
         * ══════════════════════════════════════════ */
        + '.body.container > h2 {'
        + '  color: var(--md-on-surface) !important;'
        + '  font-size: 22px !important;'
        + '  font-weight: 700 !important;'
        + '  letter-spacing: -0.02em !important;'
        + '  margin: 0 0 20px !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 11. 操作列「推送/上一页/下一页」按钮
         *     AdminBeautify .btn 强制 padding:0 24px + height:40px
         *     导致按钮宽度 > col width="80"，左侧溢出被覆盖
         *     改：缩小内边距与高度，确保按钮在列内完整显示
         * ══════════════════════════════════════════ */
        + '.tg-actions .btn, .tg-actions a.btn {'
        + '  padding: 0 14px !important;'
        + '  height: 36px !important;'
        + '  font-size: 13px !important;'
        + '}'

        /* ══════════════════════════════════════════
         * 12. 发布时间列折行修复（仅宽屏有效）
         *     AdminBeautify .typecho-list-table td 强制 padding:14px 16px
         *     使 col width="160" 的内容区仅剩 128px，而 AJAX 渲染后
         *     日期含秒（Y-m-d H:i:s，~160px）必然折行
         *     改：① table-layout:auto 让列宽按内容自适应
         *         ② 日期列 white-space:nowrap 阻止字符级折行
         *     注：仅在 >980px 时启用——980px 以下日期列已被 push.php
         *         的响应式 CSS 隐藏，不需要修复
         * ══════════════════════════════════════════ */
        + '@media (min-width: 981px) {'
        + '  .tg-table { table-layout: auto !important; }'
        + '  .tg-table td:nth-child(3), .tg-table th:nth-child(3) {'
        + '    white-space: nowrap !important;'
        + '  }'
        + '}'

        /* ══════════════════════════════════════════
         * 13. 移动端响应式适配
         *     push.php 自带 4 档断点（980/720/560/420px）用于
         *     隐藏列、缩小按钮，但 AdminBeautify 全局 !important
         *     样式以及本脚本的桌面端修正会覆盖这些规则。
         *     此处按断点逐级覆盖，确保移动端布局正确。
         * ══════════════════════════════════════════ */

        /* ── 768px：平板 / 小屏笔记本 ── */
        + '@media (max-width: 768px) {'
        + '  .tg-card {'
        + '    padding: 14px 16px !important;'
        + '    border-radius: var(--md-radius-lg, 16px) !important;'
        + '  }'
        + '  .tg-card > h2, .tg-card > h3 {'
        + '    font-size: 15px !important;'
        + '  }'
        + '  .tg-table td, .tg-table th {'
        + '    padding: 10px 8px !important;'
        + '  }'
        + '}'

        /* ── 575px：AdminBeautify 汉堡菜单阈值 ── */
        + '@media (max-width: 575px) {'
        + '  .tg-card {'
        + '    padding: 12px !important;'
        + '    border-radius: var(--md-radius-md, 12px) !important;'
        + '    box-shadow: none !important;'
        + '  }'
        + '  .tg-input {'
        + '    max-width: 100% !important;'
        + '  }'
        + '  .body.container > h2 {'
        + '    font-size: 18px !important;'
        + '    margin: 0 0 14px !important;'
        + '  }'
        + '  .tg-badge {'
        + '    font-size: 11px !important;'
        + '    padding: 2px 8px !important;'
        + '  }'
        + '}'

        /* ── 560px：push.php 缩小按钮 + 隐藏文字断点 ── */
        + '@media (max-width: 560px) {'
        + '  .tg-actions .btn, .tg-actions a.btn {'
        + '    padding: 0 8px !important;'
        + '    font-size: 12px !important;'
        + '    height: 28px !important;'
        + '    line-height: 28px !important;'
        + '  }'
        + '  .tg-stickybar {'
        + '    padding: 0 0 8px 0 !important;'
        + '  }'
        + '  .tg-table td, .tg-table th {'
        + '    padding: 8px 6px !important;'
        + '  }'
        + '}'

        /* ── 420px：极窄屏 ── */
        + '@media (max-width: 420px) {'
        + '  .tg-actions .btn, .tg-actions a.btn {'
        + '    padding: 0 6px !important;'
        + '  }'
        + '  .tg-card {'
        + '    padding: 10px !important;'
        + '    border-radius: var(--md-radius-sm, 8px) !important;'
        + '  }'
        + '  .tg-table td, .tg-table th {'
        + '    padding: 6px 4px !important;'
        + '  }'
        + '}'

        ;

    /* ─── 注入 / 移除 ───────────────────────────────────────────── */

    function applyFix(url) {
        var isTarget = (url || '').indexOf('TelegramNotice') !== -1;

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
