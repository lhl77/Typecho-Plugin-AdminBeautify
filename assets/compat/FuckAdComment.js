/**
 * @name        FuckAdComment 兼容
 * @description 修复 FuckAdComment 插件「评论管理」页面在 AdminBeautify 下的兼容问题：
 *              ① .typecho-list-table { overflow:hidden } 裁剪了 position:absolute 的
 *                 下拉菜单，导致单击「拉黑」按钮看不到菜单项；
 *                 修复：创建单一 position:fixed 全局浮层（#ab-fad-menu append 到 body），
 *                 点击时动态填充当前行数据并以 getBoundingClientRect() 定位。
 *              ② 原始 .dropdown-menu 持续暴露或被 chip 样式破坏；修复：CSS 隐藏原始菜单。
 *              ③ AdminBeautify 将 window.prompt 改为异步（同步返回 null），导致
 *                 FuckAdComment 的拉黑操作永远无法执行；
 *                 修复：浮层菜单项直接调用 AdminBeautify.prompt() Promise API。
 *              ④ 移动端 ≤768px AdminBeautify 将 .comment-action a 强制设为 32px 圆形，
 *                 导致「拉黑」按钮变形；修复：排除 .fuckAdAction 不受圆形 chip 约束。
 *              ⑤ 为「拉黑」按钮及菜单项添加 Material Icon，与其他操作按钮风格一致。
 *              已支持 AdminBeautify AJAX 导航（ab:pageload）。
 * @plugins     FuckAdComment
 * @version     1.1.2
 * @author      LHL
 */
(function () {
    'use strict';

    var STYLE_ID = 'ab-compat-fuckadcomment';

    /* ── 图标映射（与 Model.php ACTION_MAP 一致）────────────────────────── */
    var ICON_MAP = {
        author: 'person_off',
        ip:     'block',
        text:   'short_text',
        url:    'link_off',
        mail:   'mail_lock'
    };

    /* ── CSS ─────────────────────────────────────────────────────────────── */
    var CSS = [
        /* 1. 彻底隐藏原始下拉菜单（被 overflow:hidden 裁剪 + AB chip 破坏） */
        '.fuckAdRow > .dropdown-menu { display: none !important; }',

        /* 2. .fuckAdAction 排除 MD3 32px 圆形 chip 约束 */
        '.comment-action .fuckAdAction {',
        '  display: inline-flex !important;',
        '  align-items: center !important;',
        '  gap: 4px !important;',
        '  padding: 5px 12px !important;',
        '  border-radius: var(--md-radius-full) !important;',
        '  font-size: 0.8125em !important;',
        '  font-weight: 500 !important;',
        '  white-space: nowrap !important;',
        '  cursor: pointer !important;',
        '  border: 1px solid var(--md-outline-variant) !important;',
        '  background: transparent !important;',
        '  color: var(--md-on-surface-variant) !important;',
        '  text-decoration: none !important;',
        '  position: relative !important;',
        '  overflow: visible !important;',
        '  width: auto !important;',
        '  height: auto !important;',
        '  transition: background var(--md-transition-duration,.2s);',
        '}',
        '.comment-action .fuckAdAction:hover,',
        '.comment-action .fuckAdAction.fad-btn-active {',
        '  background: var(--md-surface-container) !important;',
        '  color: var(--md-on-surface) !important;',
        '}',
        /* 拉黑按钮内的图标 */
        '.comment-action .fuckAdAction .material-icons-round {',
        '  font-size: 15px !important;',
        '  line-height: 1;',
        '  flex-shrink: 0;',
        '}',
        /* 移动端与其他 chip 统一为 32px 圆形 icon-only */
        '@media (max-width: 768px) {',
        '  .comment-action .fuckAdAction {',
        '    width: 32px !important;',
        '    height: 32px !important;',
        '    padding: 0 !important;',
        '    gap: 0 !important;',
        '    justify-content: center !important;',
        '    align-items: center !important;',
        '    flex-shrink: 0 !important;',
        '  }',
        '  .comment-action .fuckAdAction .fad-chip-text {',
        '    display: none !important;',
        '  }',
        '  .comment-action .fuckAdAction .material-icons-round {',
        '    font-size: 16px !important;',
        '    display: flex !important;',
        '    align-items: center !important;',
        '    justify-content: center !important;',
        '    margin-right: 0 !important;',
        '  }',
        '}',
        /* 拉黑按钮文字标签（桌面端显示，移动端隐藏） */
        '.comment-action .fuckAdAction .fad-chip-text {',
        '  display: inline !important;',
        '}',

        /* 3. 全局浮层菜单 #ab-fad-menu（append 到 body，position:fixed） */
        '#ab-fad-menu {',
        '  position: fixed;',
        '  z-index: 99999;',
        '  min-width: 150px;',
        '  border: 1px solid var(--md-outline-variant, #cac4d0);',
        '  background: var(--md-surface-container-low, #f7f2fa);',
        '  border-radius: var(--md-radius-md, 12px);',
        '  box-shadow: var(--md-elevation-3, 0 4px 16px rgba(0,0,0,.14));',
        '  padding: 6px 0;',
        '  list-style: none;',
        '  margin: 0;',
        '  display: none;',
        '}',
        '#ab-fad-menu.fad-visible {',
        '  display: block;',
        '}',
        '#ab-fad-menu li a {',
        '  display: flex !important;',
        '  align-items: center !important;',
        '  gap: 8px !important;',
        '  padding: 8px 16px !important;',
        '  cursor: pointer;',
        '  color: var(--md-on-surface, #1c1b1f) !important;',
        '  font-size: 0.875em !important;',
        '  font-weight: 400 !important;',
        '  white-space: nowrap;',
        '  text-decoration: none !important;',
        '  width: auto !important;',
        '  height: auto !important;',
        '  border: none !important;',
        '  border-radius: 0 !important;',
        '  background: transparent !important;',
        '  box-shadow: none !important;',
        '  transition: background var(--md-transition-duration,.2s);',
        '}',
        '#ab-fad-menu li a:hover {',
        '  background: var(--md-surface-container-high, #ece6f0) !important;',
        '}',
        '#ab-fad-menu li a .material-icons-round {',
        '  font-size: 16px !important;',
        '  color: var(--md-on-surface-variant, #49454f) !important;',
        '  flex-shrink: 0;',
        '}'
    ].join('\n');

    /* ── 注入样式 ─────────────────────────────────────────────────────────── */
    function injectCSS() {
        if (document.getElementById(STYLE_ID)) return;
        var s = document.createElement('style');
        s.id = STYLE_ID;
        s.textContent = CSS;
        document.head.appendChild(s);
    }

    /* ── 全局浮层菜单 ─────────────────────────────────────────────────────── */
    var _floatMenu = null;   /* ul#ab-fad-menu */
    var _activeBtn = null;   /* 当前触发按钮 */

    function ensureFloatMenu() {
        if (_floatMenu && document.body.contains(_floatMenu)) return;
        _floatMenu = document.createElement('ul');
        _floatMenu.id = 'ab-fad-menu';
        document.body.appendChild(_floatMenu);
    }

    function positionFloatMenu(btn) {
        if (!_floatMenu) return;
        var rect = btn.getBoundingClientRect();
        var mh   = _floatMenu.offsetHeight || 0;
        var mw   = _floatMenu.offsetWidth  || 150;
        var top  = rect.bottom + 4;
        var left = rect.left;
        if (top + mh > window.innerHeight) top = Math.max(0, rect.top - mh - 4);
        if (left + mw > window.innerWidth) left = Math.max(0, rect.right - mw);
        _floatMenu.style.top  = top  + 'px';
        _floatMenu.style.left = left + 'px';
    }

    function openFloatMenu(btn, tr) {
        ensureFloatMenu();

        /* 再次点同一按钮 → 关闭 */
        if (_activeBtn === btn && _floatMenu.classList.contains('fad-visible')) {
            closeFloatMenu();
            return;
        }
        closeFloatMenu();

        /* 解析评论数据 */
        var commentData;
        try { commentData = JSON.parse(tr.getAttribute('data-comment') || '{}'); }
        catch (ex) { commentData = {}; }

        var actionMap = getActionMap();
        var secUrl    = getSecurityUrl();
        var cid       = (tr.querySelector('input[type="checkbox"]') || {}).value || '';

        /* 动态填充菜单项 */
        _floatMenu.innerHTML = '';
        var hasItem = false;

        Object.keys(actionMap).forEach(function (k) {
            if (!commentData[k]) return;  /* 该字段为空 → 跳过（同 action.tpl 逻辑） */
            hasItem = true;
            var label = actionMap[k];
            var li    = document.createElement('li');
            var a     = document.createElement('a');
            a.href    = 'javascript:void(0)';

            /* 图标 */
            var ico = document.createElement('span');
            ico.className   = 'material-icons-round';
            ico.textContent = ICON_MAP[k] || 'block';
            a.appendChild(ico);
            a.appendChild(document.createTextNode('拉黑 ' + label));

            li.appendChild(a);
            _floatMenu.appendChild(li);

            /* 菜单项点击 → 拉黑操作 */
            a.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                closeFloatMenu();
                doBlacklist(k, label, commentData[k], cid, secUrl);
            });
        });

        if (!hasItem) return;

        _activeBtn = btn;
        btn.classList.add('fad-btn-active');
        _floatMenu.classList.add('fad-visible');
        positionFloatMenu(btn);

        /* 滚动/缩放时跟随 */
        window.addEventListener('scroll', onScrollResize, true);
        window.addEventListener('resize', onScrollResize);
    }

    function onScrollResize() {
        if (_activeBtn && _floatMenu) positionFloatMenu(_activeBtn);
    }

    function closeFloatMenu() {
        if (_floatMenu) _floatMenu.classList.remove('fad-visible');
        if (_activeBtn) _activeBtn.classList.remove('fad-btn-active');
        _activeBtn = null;
        window.removeEventListener('scroll', onScrollResize, true);
        window.removeEventListener('resize', onScrollResize);
    }

    /* ── 拉黑操作（AdminBeautify.prompt Promise API）─────────────────────── */
    function doBlacklist(actionKey, label, defaultContent, cid, secUrl) {
        var msg = '请确认需要拉黑评论的【' + label + '】内容\n 新评论 【' + label + '】 包含该内容时将会被拦截';
        AdminBeautify.prompt(msg, defaultContent).then(function (val) {
            if (val === null) return;
            window.location.href = secUrl
                + 'do='       + encodeURIComponent(actionKey)
                + '&content=' + encodeURIComponent(val)
                + '&cid='     + encodeURIComponent(cid);
        });
    }

    /* ── 从页面内联 script 提取 securityUrl ──────────────────────────────── */
    function getSecurityUrl() {
        var scripts = document.querySelectorAll('script:not([src])');
        for (var i = 0; i < scripts.length; i++) {
            var txt = scripts[i].textContent || '';
            var m = txt.match(/['"]([^'"]*\/action\/FuckAdComment[^'"]*)['"]/);
            if (m) return m[1];
        }
        return '';
    }

    /* ── 从页面内联 script 提取 actionMap ────────────────────────────────── */
    /* action.tpl 使用 let actionMap = {...}; 声明，
     * let 不会挂载到 window 上，因此必须从 inline script 文本中解析。 */
    function getActionMap() {
        var scripts = document.querySelectorAll('script:not([src])');
        for (var i = 0; i < scripts.length; i++) {
            var txt = scripts[i].textContent || '';
            if (txt.indexOf('FuckAdComment') === -1) continue;
            var m = txt.match(/actionMap\s*=\s*(\{[^}]+\})/);
            if (m) {
                try { return JSON.parse(m[1]); } catch (e) {}
            }
        }
        return {};
    }

    /* ── 向 .fuckAdAction 按钮注入 Material Icon ─────────────────────────── */
    function injectBtnIcons() {
        var btns = document.querySelectorAll('.fuckAdAction');
        for (var i = 0; i < btns.length; i++) {
            var btn = btns[i];
            if (btn.querySelector('.material-icons-round')) continue;
            /* 插入图标 */
            var ico = document.createElement('span');
            ico.className   = 'material-icons-round';
            ico.textContent = 'person_off';
            btn.insertBefore(ico, btn.firstChild);
            /* 将文字节点包裹在 span 中，移动端可隐藏文字只保留图标 */
            var textNode = ico.nextSibling;
            if (textNode && textNode.nodeType === 3 && textNode.textContent.trim()) {
                var span = document.createElement('span');
                span.className = 'fad-chip-text';
                span.textContent = textNode.textContent;
                btn.replaceChild(span, textNode);
            }
        }
    }

    /* ── 捕获阶段事件委托（在 jQuery 冒泡处理器之前拦截）───────────────────── */
    var _captureAttached = false;

    function attachCapture() {
        if (_captureAttached) return;
        _captureAttached = true;

        document.addEventListener('click', function (e) {
            if (window.location.href.indexOf('manage-comments.php') === -1) return;

            /* 向上查找 .fuckAdAction（最多 4 层） */
            var el = e.target, btn = null;
            for (var i = 0; i < 4 && el && el !== document; i++) {
                if (el.classList && el.classList.contains('fuckAdAction')) {
                    btn = el;
                    break;
                }
                el = el.parentElement;
            }

            if (btn) {
                e.preventDefault();
                e.stopImmediatePropagation(); /* 彻底阻止 jQuery 同元素处理器 */
                var tr = btn.closest ? btn.closest('tr') : (function () {
                    var p = btn;
                    while (p && p.tagName !== 'TR') p = p.parentNode;
                    return p;
                })();
                if (tr) openFloatMenu(btn, tr);
                return;
            }

            /* 点击浮层以外 → 关闭 */
            if (_floatMenu && _floatMenu.classList.contains('fad-visible') &&
                !_floatMenu.contains(e.target)) {
                closeFloatMenu();
            }
        }, true); /* capture = true */

        /* Escape 关闭 */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeFloatMenu();
        });
    }

    /* ── 初始化 ──────────────────────────────────────────────────────────── */
    function init() {
        if (window.location.href.indexOf('manage-comments.php') === -1) return;
        injectCSS();
        injectBtnIcons();
        attachCapture();
    }

    /* ── 首次加载 + AJAX 导航 ────────────────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('ab:pageload', function () {
        _activeBtn = null;
        if (_floatMenu) {
            _floatMenu.classList.remove('fad-visible');
            _floatMenu.innerHTML = '';
        }
        init();
    });
})();
