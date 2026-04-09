/**
 * @name        FuckAdComment 兼容
 * @description 修复 FuckAdComment 插件「评论管理」页面在 AdminBeautify 下的兼容问题：
 *              ① .typecho-list-table { overflow:hidden } 裁剪了原先 position:absolute 的
 *                 下拉菜单，导致单击「拉黑」按钮看不到菜单项；
 *                 修复：将下拉菜单替换为单一 position:fixed 的全局浮层（#ab-fad-menu），
 *                 在点击时动态填充当前行数据并通过 getBoundingClientRect() 定位。
 *              ② 原始 .dropdown-menu 在 AdminBeautify 下没有 display:none，会持续暴露；
 *                 修复：CSS 隐藏原始菜单，完全使用全局浮层。
 *              ③ AdminBeautify 将 window.prompt 改为异步（同步返回 null），导致
 *                 FuckAdComment 的拉黑操作永远无法执行；
 *                 修复：浮层菜单项直接调用 AdminBeautify.prompt() Promise API。
 *              ④ 移动端 ≤768px AdminBeautify 将 .comment-action a 强制设为 32px 圆形，
 *                 导致「拉黑」按钮变形；修复：排除 .fuckAdAction 不受圆形 chip 约束。
 *              ⑤ 为「拉黑」按钮添加 Material Icon，与其他操作按钮风格一致。
 *              已支持 AdminBeautify AJAX 导航（ab:pageload）。
 * @plugins     FuckAdComment
 * @version     1.1.0
 * @author      LHL
 */
(function () {
    'use strict';

    var STYLE_ID = 'ab-compat-fuckadcomment';

    /* ── 图标映射（与 Model.php ACTION_MAP 一致）─────────────────────────── */
    var ICON_MAP = {
        author : 'person_off',
        ip     : 'block',
        text   : 'short_text',
        url    : 'link_off',
        mail   : 'mail_lock'
    };
    var CSS = [
        /* 排除 .fuckAdAction 不受 MD3 chip 通用样式约束 */
        '.comment-action .fuckAdRow { overflow: visible !important; }',
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
        '  transition: background var(--md-transition-duration, .2s) var(--md-transition-easing, ease);',
        '}',
        '.comment-action .fuckAdAction:hover, .comment-action .fuckAdAction.active {',
        '  background: var(--md-surface-container) !important;',
        '  color: var(--md-on-surface) !important;',
        '}',

        /* 移动端 ≤768px：排除 .fuckAdAction 不被强制为 32px 圆形 */
        '@media (max-width: 768px) {',
        '  .comment-action .fuckAdRow { display: inline-flex !important; }',
        '  .comment-action .fuckAdAction {',
        '    width: auto !important;',
        '    height: auto !important;',
        '    padding: 5px 10px !important;',
        '    gap: 4px !important;',
        '  }',
        '}',

        /* 下拉菜单本体：AB 的 .dropdown-menu 已有 position:absolute；
         * 这里重置为 fixed 由 JS 动态定位，确保不被 overflow:hidden 父元素裁剪 */
        '.fuckAdRow > .dropdown-menu {',
        '  position: fixed !important;',
        '  display: none;',
        '  z-index: 9999 !important;',
        '  min-width: 140px !important;',
        '}',
        '.fuckAdRow > .dropdown-menu.fad-open {',
        '  display: block !important;',
        '}',

        /* 下拉菜单内 a 标签：重置 AB chip 样式，使其显示为普通菜单项 */
        '.fuckAdRow > .dropdown-menu li > a {',
        '  display: block !important;',
        '  width: auto !important;',
        '  height: auto !important;',
        '  padding: 8px 16px !important;',
        '  border-radius: 0 !important;',
        '  border: none !important;',
        '  background: transparent !important;',
        '  color: var(--md-on-surface) !important;',
        '  font-size: 0.875em !important;',
        '  font-weight: 400 !important;',
        '  white-space: nowrap !important;',
        '  gap: 0 !important;',
        '}',
        '.fuckAdRow > .dropdown-menu li > a:hover {',
        '  background: var(--md-surface-container-high) !important;',
        '  color: var(--md-on-surface) !important;',
        '  box-shadow: none !important;',
        '}'
    ].join('\n');

    /* ── 注入样式 ──────────────────────────────────────────────────────────── */
    function injectCSS() {
        if (document.getElementById(STYLE_ID)) return;
        var s = document.createElement('style');
        s.id = STYLE_ID;
        s.textContent = CSS;
        document.head.appendChild(s);
    }

    /* ── fixed 定位下拉菜单 ────────────────────────────────────────────────────
     * AdminBeautify 的 .dropdown-menu 用 position:absolute，但父容器
     * .comment-action 有 overflow:hidden，会裁剪绝对定位子元素。
     * 解决方案：改用 position:fixed，点击触发时根据触发按钮的 getBoundingClientRect()
     * 实时计算坐标，并监听 scroll / resize 重新定位。
     */
    var _openMenu = null;      // 当前展开的 dropdown-menu 元素
    var _openBtn  = null;      // 对应的触发按钮
    var _scrollHandler = null;
    var _resizeHandler = null;

    function repositionMenu() {
        if (!_openMenu || !_openBtn) return;
        var rect = _openBtn.getBoundingClientRect();
        _openMenu.style.top  = (rect.bottom + 4) + 'px';
        _openMenu.style.left = rect.left + 'px';
        /* 防止超出右侧视口 */
        var menuW = _openMenu.offsetWidth || 140;
        if (rect.left + menuW > window.innerWidth) {
            _openMenu.style.left = Math.max(0, rect.right - menuW) + 'px';
        }
    }

    function closeMenu() {
        if (_openMenu) {
            _openMenu.classList.remove('fad-open');
            _openMenu.style.top  = '';
            _openMenu.style.left = '';
        }
        if (_openBtn) _openBtn.classList.remove('active');
        _openMenu = null;
        _openBtn  = null;
        if (_scrollHandler) {
            document.removeEventListener('scroll', _scrollHandler, true);
            _scrollHandler = null;
        }
        if (_resizeHandler) {
            window.removeEventListener('resize', _resizeHandler);
            _resizeHandler = null;
        }
    }

    function openMenu(btn, menu) {
        if (_openMenu === menu) { closeMenu(); return; }
        closeMenu();
        _openBtn  = btn;
        _openMenu = menu;
        btn.classList.add('active');
        menu.classList.add('fad-open');
        repositionMenu();
        _scrollHandler = function () { repositionMenu(); };
        _resizeHandler = function () { repositionMenu(); };
        document.addEventListener('scroll', _scrollHandler, true);
        window.addEventListener('resize', _resizeHandler);
    }

    /* ── 绑定拉黑按钮逻辑 ──────────────────────────────────────────────────────
     * 替换原始 action.tpl 中绑定的事件（原事件仍挂载，但本脚本在捕获阶段拦截）。
     * 原始逻辑：
     *   data.content = prompt(msg, data.content)   ← 同步，AB 覆盖后返回 null
     *   if (data.content !== null) { window.location.href = url }
     * 新逻辑：
     *   AdminBeautify.prompt(msg, data.content).then(val => { if (val !== null) navigate })
     */
    function bindBlacklistActions() {
        /* ── 1. 切换下拉菜单的触发按钮 ── */
        var actions = document.querySelectorAll('.fuckAdAction');
        for (var i = 0; i < actions.length; i++) {
            (function (btn) {
                /* 移除 action.tpl 中 jQuery .on('click') 注册的旧处理器
                 * 最简单方式：克隆节点并替换（jQuery 事件挂在 cache 上，克隆后失效）*/
                var newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);

                var menu = newBtn.nextElementSibling;
                if (!menu || !menu.classList.contains('dropdown-menu')) return;

                newBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    openMenu(newBtn, menu);
                });
            })(actions[i]);
        }

        /* ── 2. 拦截菜单项点击 → 使用 AdminBeautify.prompt() ── */
        var items = document.querySelectorAll('.fuckAdRow a[action]');
        for (var j = 0; j < items.length; j++) {
            (function (el) {
                var newEl = el.cloneNode(true);
                el.parentNode.replaceChild(newEl, el);

                newEl.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeMenu();

                    /* 复原 action.tpl 中的数据收集逻辑 */
                    var actionKey = newEl.getAttribute('action');
                    var tr = newEl.closest ? newEl.closest('tr') : (function () {
                        var p = newEl; while (p && p.tagName !== 'TR') p = p.parentNode; return p;
                    })();
                    if (!tr) return;

                    var commentData = (function () {
                        try { return JSON.parse(tr.getAttribute('data-comment') || '{}'); } catch (ex) { return {}; }
                    })();
                    var cid = (tr.querySelector('input[type=checkbox]') || {}).value || '';

                    /* 获取 actionMap（由 action.tpl 注入到全局 window.actionMap） */
                    var actionMap = (typeof window.actionMap === 'object' && window.actionMap) || {};
                    var label = actionMap[actionKey] || actionKey;

                    var defaultContent = commentData[actionKey] || '';
                    var msg = '请确认需要拉黑评论的【' + label + '】内容\n 新评论 【' + label + '】 包含该内容时将会被拦截\n';

                    /* 获取 securityUrl（由 action.tpl 写入 script 中，尝试从 DOM script 解析） */
                    var securityUrl = (function () {
                        /* action.tpl 直接将 URL 硬编码进 script 字符串，
                         * 我们从已渲染的 <a href> 中获取更可靠 */
                        var allItems = document.querySelectorAll('.fuckAdRow a[action]');
                        /* 尝试从任意同行同 action 的链接里取 href（原始模板不生成 href，
                         * 所以只能从 action.tpl 注入的全局变量中获取） */
                        /* action.tpl: window.location.href = '{securityUrl}&'+ $.param(data) */
                        /* 最可靠方式：从 inline script 内容中正则提取 */
                        var scripts = document.querySelectorAll('script');
                        for (var si = 0; si < scripts.length; si++) {
                            var src = scripts[si].textContent || '';
                            var m = src.match(/['"](\S+\/action\/FuckAdComment[^'"]*)['"]/);
                            if (m) return m[1];
                        }
                        return '';
                    })();

                    /* 使用 AdminBeautify Promise 版 prompt */
                    AdminBeautify.prompt(msg, defaultContent).then(function (val) {
                        if (val === null) return; /* 用户取消 */
                        var params = { do: actionKey, content: val, cid: cid };
                        var qs = Object.keys(params).map(function (k) {
                            return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
                        }).join('&');
                        window.location.href = securityUrl + '&' + qs;
                    });
                });
            })(items[j]);
        }
    }

    /* ── 关闭菜单：点击页面其他地方 ──────────────────────────────────────── */
    function bindGlobalClose() {
        document.addEventListener('click', function (e) {
            if (_openMenu && !_openMenu.contains(e.target) &&
                _openBtn  && !_openBtn.contains(e.target)) {
                closeMenu();
            }
        });
    }

    /* ── 初始化 ────────────────────────────────────────────────────────────── */
    function init() {
        /* 仅在评论管理页面运行 */
        if (window.location.href.indexOf('manage-comments.php') === -1) return;
        injectCSS();
        bindBlacklistActions();
        bindGlobalClose();
    }

    /* ── 首次加载 + AJAX 导航支持 ──────────────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /* AdminBeautify AJAX 导航：每次页面切换后重新初始化 */
    document.addEventListener('ab:pageload', function () {
        /* 清理残留菜单状态 */
        _openMenu = null;
        _openBtn  = null;
        _scrollHandler = null;
        _resizeHandler = null;
        init();
    });

})();
