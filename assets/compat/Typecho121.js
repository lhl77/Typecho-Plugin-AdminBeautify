/**
 * @name        Typecho 1.2.1 部分兼容
 * @description 适配 Typecho 1.2.x 的后台 HTML 结构差异，使 AdminBeautify 的 CSS/JS，能正确应用于旧版 Typecho。仅适用于亮色模式/侧边栏，请尽快升级至1.3.0.
 *              ① 导航栏：将 1.2.x 的 <button class="menu-bar"> + <nav id="typecho-nav-list">
 *                + <ul class="root">/<ul class="child"> + <div class="operate">
 *                 结构重组为 1.3 的 <details class="menu-bar"><summary> + <menu>/<li>/<menu> 布局。
 *              ② 表单提交：为 <div class="main"> 内的 form 补充 1.3 的 submitting 逻辑，
 *                 防止重复提交时按钮不被禁用。
 *              已支持 AdminBeautify AJAX 导航（ab:pageload）。
 * @plugins     Typecho (1.2.x)
 * @version     1.0.0
 * @author      LHL
 */
(function () {
    'use strict';

    // ── 版本检测 ──────────────────────────────────────────────────────────
    // Typecho 1.3 的 menu.php 使用 <header class="typecho-head-nav"> + <menu>
    // Typecho 1.2.x 使用 <div class="typecho-head-nav"> + <nav id="typecho-nav-list">
    // 如果页面中已有 <menu> 子元素说明是 1.3+，不需要此脚本
    function isTypecho12x() {
        var nav = document.querySelector('.typecho-head-nav');
        if (!nav) return false;
        // 1.3 使用 <menu> 标签，1.2.x 使用 <nav id="typecho-nav-list">
        if (nav.querySelector('menu')) return false;
        if (nav.querySelector('#typecho-nav-list') || nav.querySelector('button.menu-bar')) return true;
        return false;
    }

    // ── 导航栏 DOM 重组 ───────────────────────────────────────────────────
    function restructureNav() {
        var headNav = document.querySelector('.typecho-head-nav');
        if (!headNav) return;

        // 1. 将 <button class="menu-bar">菜单</button>
        //    替换为 <details class="menu-bar"><summary>菜单</summary></details>
        var menuBtn = headNav.querySelector('button.menu-bar');
        if (menuBtn) {
            var details = document.createElement('details');
            details.className = 'menu-bar';
            var summary = document.createElement('summary');
            summary.textContent = menuBtn.textContent || '菜单';
            details.appendChild(summary);
            menuBtn.parentNode.replaceChild(details, menuBtn);
        }

        // 2. 获取旧版 nav#typecho-nav-list 和 div.operate
        var oldNav = headNav.querySelector('#typecho-nav-list') || headNav.querySelector('nav');
        var operateDiv = headNav.querySelector('.operate');
        if (!oldNav) return;

        // 3. 收集所有 <ul class="root"> 并转换为 <li> + <menu> 结构
        var newMenu = document.createElement('menu');
        var roots = oldNav.querySelectorAll('ul.root');

        for (var i = 0; i < roots.length; i++) {
            var root = roots[i];
            var li = document.createElement('li');

            // 复制 focus 类（1.2.x 中 ul.root.focus → 1.3 中 li.focus）
            if (root.classList.contains('focus')) {
                li.className = 'focus';
            }

            // 将 <li class="parent"> 中的 <a> 提升为 li 的直接子 <a>
            var parentLi = root.querySelector('li.parent');
            if (parentLi) {
                var parentLink = parentLi.querySelector('a');
                if (parentLink) {
                    li.appendChild(parentLink.cloneNode(true));
                }
            }

            // 将 <ul class="child"> 转换为 <menu>
            var childUl = root.querySelector('ul.child');
            if (childUl) {
                var subMenu = document.createElement('menu');
                var childItems = childUl.querySelectorAll(':scope > li');
                for (var j = 0; j < childItems.length; j++) {
                    subMenu.appendChild(childItems[j].cloneNode(true));
                }
                li.appendChild(subMenu);
            }

            newMenu.appendChild(li);
        }

        // 4. 将 <div class="operate"> 转换为 <li class="operate"> 并追加到 menu
        if (operateDiv) {
            var operateLi = document.createElement('li');
            operateLi.className = 'operate';
            var links = operateDiv.querySelectorAll('a');
            for (var k = 0; k < links.length; k++) {
                operateLi.appendChild(links[k].cloneNode(true));
            }
            newMenu.appendChild(operateLi);
        }

        // 5. 创建新的 <nav> 结构包含 details + menu
        //    清空 headNav 并重建内部结构
        var newNav = document.createElement('nav');

        // 保留已转换的 details（如果存在）
        var existingDetails = headNav.querySelector('details.menu-bar');
        if (existingDetails) {
            newNav.appendChild(existingDetails);
        }
        newNav.appendChild(newMenu);

        // 清空旧内容
        while (headNav.firstChild) {
            headNav.removeChild(headNav.firstChild);
        }
        headNav.appendChild(newNav);

        // 6. 移除 clearfix 类（1.3 不使用）
        headNav.classList.remove('clearfix');
    }

    // ── 表单提交兼容 ─────────────────────────────────────────────────────
    // Typecho 1.3 的 form-js.php 使用 .main form + submitting class
    // Typecho 1.2.x 使用 form + this.submitted flag
    // AdminBeautify 可能依赖 1.3 的行为，这里补充 submitting class
    function patchFormSubmit() {
        if (typeof $ === 'undefined' || !$.fn) return;
        // 仅在 1.2.x 的表单提交逻辑未包含 submitting 时补丁
        $(document).on('submit', '.main form, div.main form', function () {
            var $form = $(this);
            if ($form.hasClass('submitting')) return false;
            $('button[type=submit]', this).attr('disabled', 'disabled');
            $form.addClass('submitting');
        }).on('submitted', '.main form, div.main form', function () {
            $('button[type=submit]', this).removeAttr('disabled');
            $(this).removeClass('submitting');
        });
    }

    // ── 自定义字段兼容 ──────────────────────────────────────────────────
    // Typecho 1.2.x: <section id="custom-field" class="fold"> + <label id="custom-field-expand">
    // Typecho 1.3:   <details id="custom-field"> + <summary>
    // AdminBeautify CSS 可能仅针对 details/summary 设置样式
    function patchCustomFields() {
        var section = document.querySelector('section#custom-field');
        if (!section) return;

        var details = document.createElement('details');
        details.id = 'custom-field';
        details.className = 'typecho-post-option';

        // 如果原来没有 fold 类，说明展开状态
        if (!section.classList.contains('fold')) {
            details.setAttribute('open', '');
        }

        var summary = document.createElement('summary');
        var expandLabel = section.querySelector('#custom-field-expand');
        if (expandLabel) {
            summary.textContent = expandLabel.textContent.replace(/[▶▸►\s]/g, '').trim() || '自定义字段';
        } else {
            summary.textContent = '自定义字段';
        }
        details.appendChild(summary);

        // 移动其余内容到 details 中
        var children = [];
        for (var i = 0; i < section.children.length; i++) {
            if (section.children[i] !== expandLabel) {
                children.push(section.children[i]);
            }
        }
        for (var j = 0; j < children.length; j++) {
            details.appendChild(children[j]);
        }

        section.parentNode.replaceChild(details, section);
    }

    // ── 消息弹窗兼容 ─────────────────────────────────────────────────────
    // Typecho 1.2.x common-js.php 的消息弹窗逻辑使用了 position fixed + scroll 检测
    // Typecho 1.3 的消息弹窗使用 slideDown 动画
    // AdminBeautify 的 CSS 统一覆盖了 .message.popup 样式，此处不需要特殊处理

    // ── 主执行入口 ──────────────────────────────────────────────────────
    function run() {
        if (!isTypecho12x()) return;

        // 标记为 1.2.x 环境，供其他脚本检测
        document.documentElement.setAttribute('data-typecho-compat', '1.2');

        restructureNav();
        patchCustomFields();
        patchFormSubmit();
    }

    // 脚本在 footer 中加载（DOM 已就绪），直接执行
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }

    // 支持 AdminBeautify AJAX 导航
    document.addEventListener('ab:pageload', function () {
        if (isTypecho12x()) {
            restructureNav();
            patchCustomFields();
        }
    });
})();
