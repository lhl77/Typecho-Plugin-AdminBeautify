/**
 * @name        Notice 插件兼容
 * @description 修复 Notice 插件「编辑邮件模版」和「配置测试」页面在 AdminBeautify 下文本框过窄的布局问题，以及侧边栏博客名称前多余 "-" 前缀的问题。已支持 AdminBeautify AJAX 导航（ab:pageload）。
 * @plugins     Notice
 * @version     1.0.0
 * @author      LHL
 */
(function () {
    'use strict';

    // ── CSS 内容（只定义一次）────────────────────────────────────────────────
    // Notice 的实际 HTML 结构：
    //   <div class="typecho-edit-theme">
    //     <div class="col-mb-12 col-tb-8 col-9 content">…</div>   ← 主内容区有 .content 类
    //     <ul class="col-mb-12 col-tb-4 col-3">…</ul>             ← 侧边栏是 <ul>
    //   </div>
    var STYLE_ID = 'ab-compat-notice';
    var CSS = ''
        // 确保容器铺满并开启 flex 布局
        + '.typecho-edit-theme {'
        + '  display: flex !important;'
        + '  flex-wrap: nowrap !important;'
        + '  align-items: flex-start !important;'
        + '  width: 100% !important;'
        + '  gap: 0 !important;'
        + '}'
        // 主内容区（使用实际存在的 .content 类）
        + '.typecho-edit-theme > .content {'
        + '  flex: 1 1 0% !important;'
        + '  max-width: none !important;'
        + '  width: 100% !important;'
        + '}'
        // 侧边栏（实际是 <ul> 元素）
        + '.typecho-edit-theme > ul {'
        + '  flex: 0 0 200px !important;'
        + '  max-width: 200px !important;'
        + '  width: 200px !important;'
        + '}'
        // 文本框撑满容器
        + '.typecho-edit-theme textarea {'
        + '  width: 100% !important;'
        + '  min-height: 500px !important;'
        + '  box-sizing: border-box !important;'
        + '  resize: vertical !important;'
        + '}'
        // 移动端恢复堆叠布局
        + '@media (max-width: 767px) {'
        + '  .typecho-edit-theme { flex-wrap: wrap !important; }'
        + '  .typecho-edit-theme > .content,'
        + '  .typecho-edit-theme > ul {'
        + '    flex: 0 0 100% !important;'
        + '    max-width: 100% !important;'
        + '    width: 100% !important;'
        + '  }'
        + '}'
        // 深色模式
        + '@media (prefers-color-scheme: dark) {'
        + '  .typecho-edit-theme textarea {'
        + '    background: #1e1e2e !important;'
        + '    color: #cdd6f4 !important;'
        + '    border-color: #45475a !important;'
        + '  }'
        + '}';

    // ── 核心修复函数（可重复调用，幂等）────────────────────────────────────────
    function applyFix(url) {
        var isNoticePage = (url || '').indexOf('Notice') !== -1;

        if (!isNoticePage) {
            // 不是 Notice 页面：清理已注入的样式（支持 AJAX 离开场景）
            var old = document.getElementById(STYLE_ID);
            if (old) old.remove();
            return;
        }

        // ── Issue 1：注入布局 CSS（幂等，不重复创建）
        if (!document.getElementById(STYLE_ID)) {
            var style = document.createElement('style');
            style.id = STYLE_ID;
            style.textContent = CSS;
            document.head.appendChild(style);
        }

        // ── Issue 2：修复侧边栏博客名称前多余的 "-" 前缀
        // 根因：Notice 的 addPanel 第4参数（subTitle）为空字符串，导致页面标题为
        // " - BlogName - Powered by Typecho"（前导空格）。浏览器修剪 document.title
        // 后变为 "- BlogName - Powered by Typecho"，script.js 按 " - " 分割后提取出
        // "- BlogName" 而非 "BlogName"。此处作为兜底修复直接清理 DOM 中的前导 "-"。
        var titleEl = document.querySelector('.ab-sidebar-title');
        if (titleEl) {
            titleEl.textContent = titleEl.textContent.replace(/^[\s\-]+/, '');
        }
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
    // AdminBeautify 在每次 AJAX 导航完成、history.pushState 之后派发此事件。
    // e.detail.url 是导航目标 URL；此时 document.title 已更新，DOM 主内容区已替换。
    document.addEventListener('ab:pageload', function (e) {
        var url = (e && e.detail && e.detail.url) ? e.detail.url : window.location.href;
        applyFix(url);
    });
})();
