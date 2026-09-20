/**
 * AdminBeautify - Material Design 3 JavaScript Enhancements
 */
(function () {
    'use strict';

    // Prevent double-initialization (e.g. if script is loaded twice)
    if (window.__AB_INITIALIZED__) return;
    window.__AB_INITIALIZED__ = true;

    var AdminBeautify = {

        isSidebar: false,
        _ajaxNavActive: false,
        _currentXHR: null,
        _progressTimer: null,
        _hideTimer: null,
        _loadedScripts: {}, // track loaded external scripts

        // ── 页面级请求登记表 ──────────────────────────────────────────────
        // 统计 / 图表 / Umami 代理等"属于当前页面"的请求。页面切换时立即中断：
        // 这些请求（尤其 Umami 需要服务端出网）会长期占用浏览器同域连接与
        // PHP Session，若不放弃，新页面的 HTML 请求只能排队，转圈迟迟不停。
        _pageRequests: [],

        // ── 悬停预取缓存 ────────────────────────────────────────────────
        _prefetch: {},      // url -> { html, url, ts }
        _prefetchXHRs: {},  // url -> xhr
        _prefetchMax: 8,
        _prefetchTTL: 20000,

        // ====== AJAX Utility ======

        /**
         * 发起 AJAX 请求到 AdminBeautify Action
         *
         * @param {string} action - 操作名 (对应 ?do=xxx)
         * @param {object} [data] - 额外参数 (会拼入 URL query 或 POST body)
         * @param {object} [opts] - { method:'GET'|'POST', onSuccess, onError }
         * @returns {XMLHttpRequest}
         *
         * 用法示例:
         *   AdminBeautify.ajax('ping', null, {
         *       onSuccess: function(json){ console.log(json); },
         *       onError:   function(err){ console.error(err); }
         *   });
         */
        ajax: function (action, data, opts) {
            opts = opts || {};
            var cfg = window.__AB_AJAX__;
            if (!cfg || !cfg.url) {
                console.warn('[AdminBeautify] AJAX URL 未注入，请检查插件配置');
                if (opts.onError) opts.onError({ code: -1, message: 'AJAX URL not available' });
                return null;
            }

            var method = (opts.method || 'GET').toUpperCase();
            var url = cfg.url + '?do=' + encodeURIComponent(action);
            if (cfg.token) {
                url += '&_=' + encodeURIComponent(cfg.token);
            }

            var body = null;
            if (data) {
                if (method === 'GET') {
                    Object.keys(data).forEach(function (k) {
                        url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
                    });
                } else {
                    var parts = [];
                    Object.keys(data).forEach(function (k) {
                        parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
                    });
                    body = parts.join('&');
                }
            }

            var xhr = new XMLHttpRequest();
            // 页面级组件请求（统计 / 图表等）登记后可被页面切换即时中断，
            // 避免旧页面的请求拖慢新页面的加载动画（详见 _trackPageRequest）
            if (opts.abortOnNavigate) this._trackPageRequest(xhr);
            // 写操作后数据可能已变化，清空悬停预取缓存，防止切换回列表页看到旧数据
            if (method === 'POST') this._clearPrefetch();
            xhr.open(method, url, true);
            if (method === 'POST') {
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            }
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                try {
                    var json = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300 && json.code === 0) {
                        if (opts.onSuccess) opts.onSuccess(json);
                    } else {
                        if (opts.onError) opts.onError(json);
                    }
                } catch (e) {
                    if (opts.onError) opts.onError({
                        code: xhr.status,
                        message: 'JSON parse error',
                        raw: xhr.responseText
                    });
                }
            };

            xhr.send(body);
            return xhr;
        },

        // ====== 页面级请求 & 悬停预取 ======

        /**
         * 登记一个"属于当前页面"的请求
         *
         * 被登记的请求会在 AJAX 页面切换时被立即 abort()。
         * 适用于：统计卡片、图表、Umami 代理等只服务于当前页面的组件请求。
         * 不要登记表单提交类请求（保存设置等），避免中断数据写入。
         *
         * @param {XMLHttpRequest} xhr
         * @returns {XMLHttpRequest}
         */
        _trackPageRequest: function (xhr) {
            if (!xhr) return xhr;
            var self = this;
            this._pageRequests.push(xhr);
            var drop = function () {
                var i = self._pageRequests.indexOf(xhr);
                if (i !== -1) self._pageRequests.splice(i, 1);
            };
            // 无论成功、失败还是被中断，完成后都要从登记表移除，避免数组无限增长
            xhr.addEventListener('loadend', drop);
            return xhr;
        },

        /**
         * 中断所有已登记的页面级请求（页面切换时调用）
         */
        _abortPageRequests: function () {
            var list = this._pageRequests;
            this._pageRequests = [];
            for (var i = 0; i < list.length; i++) {
                try { list[i].abort(); } catch (e) {}
            }
        },

        /**
         * 清空悬停预取缓存（数据可能已变更时调用）
         */
        _clearPrefetch: function () {
            this._prefetch = {};
            for (var url in this._prefetchXHRs) {
                if (!this._prefetchXHRs.hasOwnProperty(url)) continue;
                try { this._prefetchXHRs[url].abort(); } catch (e) {}
            }
            this._prefetchXHRs = {};
        },

        /**
         * 绑定悬停 / 触摸预取
         *
         * 后台页面多为静态渲染，提前把目标页 HTML 拉到内存缓存，点击时零等待。
         * 仅在鼠标停留 60ms 后触发，避免划过整排菜单时狂发请求。
         */
        _bindPrefetch: function () {
            var self = this;
            var timer = null;
            var lastHref = '';

            var onIntent = function (e) {
                if (!self._ajaxNavActive || !e.target || !e.target.closest) return;

                // 省流模式 / 2G 网络下不做预取
                var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
                if (conn && (conn.saveData || /(^|-)2g$/i.test(conn.effectiveType || ''))) return;

                var link = e.target.closest('a');
                if (!link || !self._isAjaxable(link)) return;

                // 只预取"纯展示"页面：带操作类查询参数的一律跳过，
                // 避免预取时误触发了带副作用的 GET（删除 / 更新 / 登出等）
                var rawHref = link.getAttribute('href') || '';
                if (/[?&](do|action|delete|remove|update|install|logout|_)=/i.test(rawHref)) return;

                var href = link.href;
                if (href === lastHref) return;   // 同一链接内的子元素移动不重置计时
                lastHref = href;

                clearTimeout(timer);
                timer = setTimeout(function () { self._prefetchUrl(href); }, 60);
            };

            document.addEventListener('mouseover', onIntent, true);
            document.addEventListener('touchstart', onIntent, { passive: true, capture: true });
        },

        /**
         * 预取指定 URL 的页面 HTML
         *
         * @param {string} url
         */
        _prefetchUrl: function (url) {
            var self = this;
            if (!url || url === location.href) return;
            if (this._prefetch[url] || this._prefetchXHRs[url]) return;

            var xhr = new XMLHttpRequest();
            this._prefetchXHRs[url] = xhr;

            xhr.open('GET', url, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-PJAX', 'true');
            xhr.setRequestHeader('X-AB-Prefetch', 'true');
            xhr.responseType = 'text';

            xhr.onload = function () {
                delete self._prefetchXHRs[url];

                if (xhr.status < 200 || xhr.status >= 300) return;
                var ct = xhr.getResponseHeader('Content-Type') || '';
                if (ct.indexOf('text/html') === -1) return;

                // 会话过期被重定向到登录页 → 丢弃，避免把登录页当成后台内容套用
                var finalUrl = xhr.responseURL || url;
                if (finalUrl.indexOf('login.php') !== -1) return;

                // 超量时淘汰最旧的一条
                var keys = Object.keys(self._prefetch);
                if (keys.length >= self._prefetchMax) {
                    var oldest = null;
                    for (var i = 0; i < keys.length; i++) {
                        if (oldest === null || self._prefetch[keys[i]].ts < self._prefetch[oldest].ts) {
                            oldest = keys[i];
                        }
                    }
                    if (oldest !== null) delete self._prefetch[oldest];
                }

                self._prefetch[url] = { html: xhr.responseText, url: finalUrl, ts: Date.now() };
            };

            xhr.onerror = function () { delete self._prefetchXHRs[url]; };
            xhr.ontimeout = function () { delete self._prefetchXHRs[url]; };
            xhr.timeout = 10000;

            xhr.send();
        },

        /**
         * 取出可用的预取结果（命中即删除，保证只用一次）
         *
         * @param {string} url
         * @returns {object|null} { html, url, ts }
         */
        _takePrefetch: function (url) {
            var entry = this._prefetch[url];
            if (!entry) return null;
            delete this._prefetch[url];
            if (Date.now() - entry.ts > this._prefetchTTL) return null;
            return entry;
        },

        /**
         * 初始化
         */
        init: function () {
            this.isSidebar = document.documentElement.getAttribute('data-nav') === 'left';
            this.addNavIcons();       // icons for ALL modes (top & sidebar)
            this.checkNavOverflow();  // detect overflow → icon-only mode
            this.enhanceNavigation();
            this.enhanceButtons();
            this.interceptNativeDialogs(); // MD3 dialog intercept
            this.enhanceTables();
            this.enhanceDropdowns();  // dropdown click-outside-to-close
            this.addThemeToggle();
            this.initMobileMoreMenu(); // mobile ⋮ more menu (≤599px)
            this.enhanceScrollBehavior();
            this.enhancePopupMessages();
            this.enhanceDashboard();  // MD3 dashboard cards
            this.enhanceProfile();    // MD3 profile page
            this.enhancePlugins();    // MD3 plugins page
            this.enhanceThemes();     // MD3 themes page
            this.enhanceOptions();    // MD3 options pages
            this.enhanceComments();   // MD3 comment management page
            this.enhancePosts();      // MD3 post/page management page
            this.enhanceUsers();      // MD3 user management page
            this.enhanceMedias();     // MD3 media management page
            this.enhanceMediaEdit();  // MD3 media detail page (delete button)
            this.enhanceTags();       // MD3 tags management page
            this.initTabsFab();       // Collapse tabs to FAB when wrapping
            this.initEditorToolbar(); // MD3 pill toolbar for write-post/write-page
            this.initPreviewSheet();  // MD3 Bottom/Side Sheet for article preview
            this.initAttachPicker();  // MD3 attach picker modal for write-post/write-page
            this._checkAjaxCompat(); // AJAX 兼容性检测（仅在设置页/面板页触发）
            this._checkCompatHint(); // 兼容脚本建议提醒（已安装插件但未启用对应脚本时弹窗）
            if (this.isSidebar) {
                this.enhanceSidebar();
            } else {
                // 非侧边栏模式（移动端抽屉导航 / 桌面端顶部导航）：
                // 标记有子菜单的 li 并绑定折叠展开。
                // sidebarBindToggle 内部在桌面端顶部导航时会自动 return，不拦截链接。
                // 点击处理统一由 sidebarBindToggle 负责，abInitMobileNav 只负责品牌注入和标记。
                this.sidebarMarkExpandable();
                this.sidebarBindToggle();
            }
            this.addFooterInfo();     // Theme footer info

            // AJAX 导航可在插件设置中关闭
            var cfg = window.__AB_CONFIG__ || {};
            if (cfg.ajaxEnabled !== '0') {
                this.initAjaxNav();
            }

            // ── 所有增强完成，移除加载遮罩属性 ─────────────────────────────────
            // PHP 已在 <head> 注入 DOMContentLoaded 备用移除，此处确保在
            // init() 全部执行完毕后移除，使加载动画遮住 JS 增强前的原始界面。
            document.documentElement.removeAttribute('data-ab-loading');

            // Re-check nav overflow on resize (debounced)
            if (!this.isSidebar) {
                var self = this;
                var resizeTimer = null;
                window.addEventListener('resize', function () {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(function () { self.checkNavOverflow(); }, 150);
                });
            }
        },

        /**
         * 在 typecho-foot 后追加 Theme 信息
         */
        addFooterInfo: function () {
            var foot = document.querySelector('.typecho-foot');
            if (!foot || document.querySelector('.ab-footer-theme')) return;

            // 读取 Typecho 版本号（从已有页脚文字或 meta generator 中提取）
            var tcVerStr = '';
            var footText = foot.textContent || foot.innerText || '';
            var footMatch = footText.match(/版本\s*([\d.]+)/);
            if (footMatch) {
                tcVerStr = footMatch[1];
            } else {
                var metaGen = document.querySelector('meta[name="generator"]');
                if (metaGen) {
                    var metaMatch = (metaGen.getAttribute('content') || '').match(/Typecho[\s\/]+([\d.]+)/i);
                    if (metaMatch) tcVerStr = metaMatch[1];
                }
            }

            var cfg = window.__AB_CONFIG__ || {};
            var ver = cfg.pluginVersion || '2.1.50';

            var themeInfo = document.createElement('div');
            themeInfo.className = 'ab-footer-theme';
            themeInfo.style.marginLeft = '0px';
            themeInfo.style.marginRight = '0px';
            themeInfo.innerHTML =
                '<div class="ab-footer-inner">' +
                  '<div class="ab-footer-brand">' +
                    '<div class="ab-footer-logo">' +
                      '<span class="material-icons-round">admin_panel_settings</span>' +
                      '<span class="ab-footer-brand-name">AB-Admin</span>' +
                    '</div>' +
                    '<p class="ab-footer-desc">一款为 Typecho 打造的后台美化增强插件，基于 Material Design 3 风格设计，让后台更美观、更好用。</p>' +
                    '<span class="ab-footer-ver">v' + AdminBeautify._escHtml(ver) + (tcVerStr ? ' \u00b7 Typecho ' + AdminBeautify._escHtml(tcVerStr) : '') + '</span>' +
                  '</div>' +
                  '<div class="ab-footer-cols">' +
                    '<div class="ab-footer-col">' +
                      '<div class="ab-footer-col-title">\u9879\u76ee</div>' +
                      '<a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify" target="_blank" rel="noopener noreferrer">GitHub</a>' +
                      '<a href="https://see.lhl.one/Typecho-AB-Admin" target="_blank" rel="noopener noreferrer">\u6587\u6863</a>' +
                      '<a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases" target="_blank" rel="noopener noreferrer">\u66f4\u65b0\u65e5\u5fd7</a>' +
                    '</div>' +
                    '<div class="ab-footer-col">' +
                      '<div class="ab-footer-col-title">建言献策</div>' +
                      '<a href="https://blog.lhl.one/artical/977.html" target="_blank" rel="noopener noreferrer">\u535a\u5ba2\u7559\u8a00</a>' +
                      '<a href="https://t.me/+S_rnDEUlSPPRzvW_" target="_blank" rel="noopener noreferrer">Telegram \u7fa4</a>' +
                      '<a href="https://qm.qq.com/q/OOzG20idi2" target="_blank" rel="noopener noreferrer">QQ \u7fa4</a>' +
                    '</div>' +
                    '<div class="ab-footer-col">' +
                      '<div class="ab-footer-col-title">关于作者</div>' +
                      '<a href="https://blog.lhl.one" target="_blank" rel="noopener noreferrer">\u535a\u5ba2</a>' +
                      '<a href="options-plugin.php?config=AdminBeautify&amp;to=donateModal">\u6350\u52a9\u4f5c\u8005</a>' +
                    '</div>' +
                  '</div>' +
                '</div>';

            foot.parentNode.insertBefore(themeInfo, foot.nextSibling);
        },

        /**
         * 监听系统主题变化 (auto mode)
         */
        watchSystemTheme: function () {
            if (!window.matchMedia) return;

            var mq = window.matchMedia('(prefers-color-scheme: dark)');

            var applyTheme = function (e) {
                if (e.matches) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.removeAttribute('data-theme');
                }
            };

            if (mq.addEventListener) {
                mq.addEventListener('change', applyTheme);
            } else if (mq.addListener) {
                mq.addListener(applyTheme);
            }
        },

        /**
         * 为所有导航链接注入图标 + 将文本包裹在 span 中（top-nav & sidebar 通用）
         */
        addNavIcons: function () {
            var mainIconMap = {
                'index.php': '<span class="material-icons-round">apps</span>',
                'write-post.php': '<span class="material-icons-round">create</span>',
                'manage-posts.php': '<span class="material-icons-round">list_alt</span>',
                'options-general.php': '<span class="material-icons-round">settings</span>',
                'themes.php': '<span class="material-icons-round">palette</span>',
                'plugins.php': '<span class="material-icons-round">extension</span>',
                'default': '<span class="material-icons-round">folder</span>',
            };

            var operateIconMap = {
                'author': '<span class="material-icons-round">account_circle</span>',
                'exit': '<span class="material-icons-round">exit_to_app</span>',
                'site': '<span class="material-icons-round">public</span>'
            };

            // Helper: inject icon + wrap text in span
            function injectIcon(link, iconHtml) {
                if (link.querySelector('.ab-nav-icon')) return;
                // Set title for tooltip (use existing text)
                if (!link.getAttribute('title')) {
                    link.setAttribute('title', link.innerText.trim());
                }
                // Wrap text nodes in <span class="ab-nav-text">
                var children = Array.prototype.slice.call(link.childNodes);
                children.forEach(function (node) {
                    if (node.nodeType === 3 && node.textContent.trim()) {
                        var textSpan = document.createElement('span');
                        textSpan.className = 'ab-nav-text';
                        textSpan.textContent = node.textContent;
                        link.replaceChild(textSpan, node);
                    }
                });
                // Insert icon
                var iconSpan = document.createElement('span');
                iconSpan.className = 'ab-nav-icon';
                iconSpan.innerHTML = iconHtml;
                link.insertBefore(iconSpan, link.firstChild);
            }

            // --- Main menu items ---
            var mainItems = document.querySelectorAll('.typecho-head-nav nav > menu > li:not(.operate) > a');
            mainItems.forEach(function (link) {
                var href = link.getAttribute('href');
                var iconHtml = mainIconMap['default'];
                if (href) {
                    if (href.indexOf('extending.php') !== -1) iconHtml = mainIconMap['plugins.php'];
                    else if (href.indexOf('index.php') !== -1 && href.indexOf('options') === -1 && href.indexOf('manage') === -1) iconHtml = mainIconMap['index.php'];
                    else if (href.indexOf('write-') !== -1) iconHtml = mainIconMap['write-post.php'];
                    else if (href.indexOf('manage-') !== -1) iconHtml = mainIconMap['manage-posts.php'];
                    else if (href.indexOf('options-') !== -1) iconHtml = mainIconMap['options-general.php'];
                    else if (href.indexOf('themes.php') !== -1 || href.indexOf('theme-') !== -1) iconHtml = mainIconMap['themes.php'];
                    else if (href.indexOf('plugins.php') !== -1) iconHtml = mainIconMap['plugins.php'];
                }
                injectIcon(link, iconHtml);
            });

            // --- Operate items (author / exit / site link) ---
            var operateLi = document.querySelector('.typecho-head-nav nav > menu > li.operate');
            if (operateLi) {
                var opLinks = operateLi.querySelectorAll('a:not(.md3-theme-toggle)');
                opLinks.forEach(function (link) {
                    var type = 'site';
                    if (link.classList.contains('author')) type = 'author';
                    else if (link.classList.contains('exit')) type = 'exit';
                    if (operateIconMap[type]) {
                        injectIcon(link, operateIconMap[type]);
                    }
                });
            }
        },

        /**
         * 检测顶栏菜单是否溢出，溢出时切换为 icon-only 模式
         * 方法：禁止 flex 收缩 + 开启 flex-wrap，看是否换行
         */
        checkNavOverflow: function () {
            if (this.isSidebar) return;

            var nav = document.querySelector('.typecho-head-nav');
            var menu = document.querySelector('.typecho-head-nav nav > menu');
            if (!nav || !menu) return;

            // 1. 移除 icon 模式，恢复文字，准备测量
            nav.classList.remove('ab-nav-icon-mode');
            void menu.offsetWidth; // force reflow

            // 2. 取第一个 li 的高度作为「单行高度」基准
            var firstItem = menu.querySelector(':scope > li');
            if (!firstItem) return;
            var singleLineHeight = firstItem.offsetHeight;

            // 3. 禁止所有 li 收缩（否则 flex 会压缩它们来避免换行）
            var items = Array.prototype.slice.call(menu.children);
            items.forEach(function (li) { li.style.flexShrink = '0'; });

            // 4. 临时允许换行
            menu.style.flexWrap = 'wrap';
            void menu.offsetWidth; // force reflow

            // 5. 如果 menu 高度 > 单行高度，说明有项目换行了
            var overflows = menu.offsetHeight > singleLineHeight + 4;

            // 6. 恢复所有临时样式
            items.forEach(function (li) { li.style.flexShrink = ''; });
            menu.style.flexWrap = '';

            // 7. 溢出则切换到 icon 模式
            if (overflows) {
                nav.classList.add('ab-nav-icon-mode');
            }
        },

        /**
         * 增强导航栏
         */
        enhanceNavigation: function () {
            var navLinks = document.querySelectorAll('.typecho-head-nav nav > menu > li > a');
            navLinks.forEach(function (link) {
                link.addEventListener('mousedown', function (e) {
                    AdminBeautify.createRipple(e, this);
                });
            });
        },

        /**
         * 增强按钮 - 添加涟漪效果
         */
        enhanceButtons: function () {
            var buttons = document.querySelectorAll('.btn, .primary, .btn-warn');
            buttons.forEach(function (btn) {
                btn.style.position = 'relative';
                btn.style.overflow = 'hidden';
                btn.addEventListener('mousedown', function (e) {
                    AdminBeautify.createRipple(e, this);
                });
            });
        },

        /**
         * MD3 涟漪动画效果
         */
        createRipple: function (event, element) {
            var existingRipple = element.querySelector('.md3-ripple');
            if (existingRipple) existingRipple.remove();

            var rect = element.getBoundingClientRect();
            var ripple = document.createElement('span');
            var size = Math.max(rect.width, rect.height);
            var x = event.clientX - rect.left - size / 2;
            var y = event.clientY - rect.top - size / 2;

            ripple.className = 'md3-ripple';
            ripple.style.cssText =
                'position:absolute;border-radius:50%;pointer-events:none;' +
                'width:' + size + 'px;height:' + size + 'px;' +
                'left:' + x + 'px;top:' + y + 'px;' +
                'background:currentColor;opacity:0.12;' +
                'transform:scale(0);animation:md3Ripple 0.8s cubic-bezier(0.2,0,0,1) forwards;' +
                'z-index:1;';

            element.style.position = 'relative';
            element.style.overflow = 'hidden';
            element.appendChild(ripple);

            setTimeout(function () {
                if (ripple.parentNode) ripple.remove();
            }, 600);
        },

        /**
         * 表格增强
         */
        enhanceTables: function () {
            var tables = document.querySelectorAll('.typecho-list-table');
            tables.forEach(function (table) {
                var firstRow = table.querySelector('thead tr');
                if (firstRow) {
                    var ths = firstRow.querySelectorAll('th');
                    if (ths.length > 0) {
                        ths[0].style.borderRadius = 'var(--md-radius-lg) 0 0 0';
                        ths[ths.length - 1].style.borderRadius = '0 var(--md-radius-lg) 0 0';
                    }
                }
            });
        },

        /**
         * MD3 评论管理页增强：
         *  1. 为 .comment-action 中每个操作链接注入 Material Icon chip 图标
         *  2. 为评论行添加状态徽章（通过/待审/垃圾）
         *  3. 为操作按钮绑定涟漪效果
         */
        enhanceComments: function () {
            // 只在评论管理页运行
            if (!document.querySelector('.comment-action')) return;

            // ── 修复 thead 列头对齐 + 响应式隐藏头像列 ──
            // 原始模板将"作者"放在第 2 列（头像列），实际作者信息在第 3 列。
            // 1. 交换 th[1]/th[2] 的文本，使"作者"标题对准实际的作者信息列。
            // 2. 给 th[1]（空的头像列标题）和所有头像 td 打上 ab-avatar-col-* 类，
            //    CSS 在 ≤991px 时统一 display:none，整列同时消失，不破坏 table-layout:fixed。
            var commentTable = document.querySelector('.typecho-list-table');
            if (commentTable && !commentTable.dataset.abHeaderFixed) {
                // 给表格本身打标记，CSS nth-child 选择器直接控制响应式列宽，无需逐行打类
                commentTable.classList.add('ab-comment-table');
                var headRow = commentTable.querySelector('thead tr');
                if (headRow) {
                    var ths = headRow.querySelectorAll('th');
                    // ths[1]="作者"（头像列，错误位置）  ths[2]=""（作者信息列，正确位置）
                    if (ths.length >= 4) {
                        var t1 = (ths[1].textContent || '').trim();
                        var t2 = (ths[2].textContent || '').trim();
                        // 只在确实是"作者/内容"错位时才交换，避免重复运行时乱改
                        if (t1 && !t2) {
                            ths[1].textContent = '';
                            ths[2].textContent = t1;
                        }
                    }
                }
                commentTable.dataset.abHeaderFixed = '1';
            }

            // 各操作对应的 Material Icon 名称
            var chipIconMap = {
                'operate-approved': 'check_circle',
                'operate-waiting':  'schedule',
                'operate-spam':     'report',
                'operate-edit':     'edit',
                'operate-reply':    'reply',
                'operate-delete':   'delete'
            };

            // 在 chip 文字前注入图标（幂等：已注入则跳过）
            function injectChipIcon(el, iconName) {
                if (el.querySelector('.ab-chip-icon')) return;
                var icon = document.createElement('span');
                icon.className = 'ab-chip-icon material-icons-round';
                icon.textContent = iconName;
                el.insertBefore(icon, el.firstChild);
            }

            // 处理所有 .comment-action 容器
            var actions = document.querySelectorAll('.comment-action');
            actions.forEach(function (actionBar) {
                // 注入图标到可点击的 <a> 按钮
                Object.keys(chipIconMap).forEach(function (cls) {
                    var el = actionBar.querySelector('a.' + cls);
                    if (el) {
                        injectChipIcon(el, chipIconMap[cls]);
                        // 涟漪效果
                        if (!el.__abRipple) {
                            el.__abRipple = true;
                            el.style.position = 'relative';
                            el.style.overflow = 'hidden';
                            el.addEventListener('mousedown', function (e) {
                                AdminBeautify.createRipple(e, this);
                            });
                        }
                    }
                });

                // 为 .weak span（当前状态）也注入图标
                var weakSpans = actionBar.querySelectorAll('span.weak');
                weakSpans.forEach(function (span) {
                    var text = (span.textContent || '').trim();
                    var iconName = '';
                    if (text === '通过')   iconName = 'check_circle';
                    else if (text === '待审核') iconName = 'schedule';
                    else if (text === '垃圾')   iconName = 'report';
                    if (iconName) injectChipIcon(span, iconName);
                });

                // 将 chip 的纯文本节点包裹进 <span class="ab-chip-text">，
                // 以便在窄屏下通过 CSS 隐藏文字、仅保留图标
                var allChips = actionBar.querySelectorAll('a, span.weak');
                Array.prototype.forEach.call(allChips, function (chip) {
                    if (chip.querySelector('.ab-chip-text')) return; // 幂等
                    var nodes = Array.prototype.slice.call(chip.childNodes);
                    nodes.forEach(function (n) {
                        if (n.nodeType === 3 && n.textContent.trim()) {
                            var labelSpan = document.createElement('span');
                            labelSpan.className = 'ab-chip-text';
                            labelSpan.textContent = n.textContent;
                            chip.replaceChild(labelSpan, n);
                        }
                    });
                });
            });

            // 为评论行添加状态色条（左边框指示状态），并重组布局：
            // 为评论行添加状态色条（左边框指示状态）
            var rows = document.querySelectorAll('.typecho-list-table tbody tr[id]');
            rows.forEach(function (tr) {
                if (tr.classList.contains('ab-comment-enhanced')) return;
                tr.classList.add('ab-comment-enhanced');

                // ── 状态色条 ──
                var actionBar = tr.querySelector('.comment-action');
                if (!actionBar) return;
                var weakEl = actionBar.querySelector('span.weak');
                if (!weakEl) return;
                var statusText = (weakEl.textContent || '').replace(/\s/g, '');

                if (statusText.indexOf('通过') !== -1) {
                    tr.classList.add('ab-comment-approved');
                } else if (statusText.indexOf('待审核') !== -1) {
                    tr.classList.add('ab-comment-waiting');
                } else if (statusText.indexOf('垃圾') !== -1) {
                    tr.classList.add('ab-comment-spam');
                }
            });
        },

        /**
         * MD3 文章/页面管理页增强：
         *  1. 给表格打 ab-posts-table 类，供 CSS 响应式列宽控制
         *  2. 为每行注入状态类（ab-status-draft / waiting / hidden / private / password）
         *  3. 将 i-edit / i-exlink 链接替换为 Material Icon 按钮，并包入 .ab-post-actions 容器（鼠标悬停显示）
         */
        enhancePosts: function () {
            var href = window.location.href;
            if (href.indexOf('manage-posts.php') === -1 && href.indexOf('manage-pages.php') === -1) return;

            var postsTable = document.querySelector('.typecho-list-table');
            if (!postsTable || postsTable.dataset.abPostsEnhanced) return;
            postsTable.classList.add('ab-posts-table');
            postsTable.dataset.abPostsEnhanced = '1';

            // ── 重写 colgroup：将 Typecho 的百分比宽度替换为 px ──
            var colgroup = postsTable.querySelector('colgroup');
            var col4El = null, col5El = null;
            // if (colgroup) {
            //     colgroup.innerHTML =
            //         '<col style="width:40px">' +   // col1: checkbox
            //         '<col style="width:64px">' +   // col2: comments
            //         '<col>' +                       // col3: title (auto)
            //         '<col style="width:120px">' +  // col4: author
            //         '<col style="width:120px">' +  // col5: category
            //         '<col style="width:160px">';   // col6: date
            //     var cols = colgroup.querySelectorAll('col');
            //     col4El = cols[3]; col5El = cols[4];
            // }
            // ── 响应式：≤991px 通过 visibility:collapse 折叠作者/分类列（不使用 display:none）──
            function abPostsResponsive() {
                var narrow = window.matchMedia('(max-width: 991px)').matches;
                if (col4El) { col4El.style.visibility = narrow ? 'collapse' : ''; col4El.style.width = narrow ? '0' : '120px'; }
                if (col5El) { col5El.style.visibility = narrow ? 'collapse' : ''; col5El.style.width = narrow ? '0' : '120px'; }
            }
            abPostsResponsive();
            var mq991 = window.matchMedia('(max-width: 991px)');
            try { mq991.addEventListener('change', abPostsResponsive); } catch(e) { mq991.addListener(abPostsResponsive); }

            var rows = postsTable.querySelectorAll('tbody tr');
            Array.prototype.forEach.call(rows, function (tr) {
                // 标题列是第 3 列（nth-child(3)）
                var titleTd = tr.querySelector('td:nth-child(3)');
                if (!titleTd) return;

                // ── 1. 状态类 ──
                var statusEl = titleTd.querySelector('em.status');
                if (statusEl) {
                    var st = (statusEl.textContent || '').trim();
                    if (st === '草稿')       tr.classList.add('ab-status-draft');
                    else if (st === '待审核') tr.classList.add('ab-status-waiting');
                    else if (st === '隐藏')   tr.classList.add('ab-status-hidden');
                    else if (st === '私密')   tr.classList.add('ab-status-private');
                    else if (st === '密码保护') tr.classList.add('ab-status-password');
                }

                // ── 2. 操作按钮：收集含 i-edit / i-exlink 的链接，包入 .ab-post-actions ──
                var allLinks = titleTd.querySelectorAll('a');
                var actionLinks = [];
                Array.prototype.forEach.call(allLinks, function (a) {
                    if (a.querySelector('.i-edit') || a.querySelector('.i-exlink')) {
                        actionLinks.push(a);
                    }
                });
                if (actionLinks.length === 0) return;

                var actionsSpan = document.createElement('span');
                actionsSpan.className = 'ab-post-actions';
                Array.prototype.forEach.call(actionLinks, function (a) {
                    var iEl = a.querySelector('.i-edit, .i-exlink');
                    if (iEl) {
                        var iconName = iEl.classList.contains('i-edit') ? 'edit' : 'open_in_new';
                        var iconSpan = document.createElement('span');
                        iconSpan.className = 'material-icons-round';
                        iconSpan.textContent = iconName;
                        a.innerHTML = '';
                        a.appendChild(iconSpan);
                    }
                    actionsSpan.appendChild(a);
                });
                titleTd.appendChild(actionsSpan);
            });
        },

        /**
         * MD3 用户管理页增强：将用户表格转换为卡片网格
         */
        enhanceUsers: function () {
            if (location.href.indexOf('manage-users.php') === -1) return;
            var listWrap = document.querySelector('.typecho-list');
            if (!listWrap || listWrap.dataset.abUsersEnhanced) return;
            var table = listWrap.querySelector('table.typecho-list-table');
            if (!table) return;

            listWrap.dataset.abUsersEnhanced = '1';

            var cfg = window.__AB_CONFIG__ || {};
            var avatarHost = window.__AB_AVATAR_HOST__ || 'gravatar.loli.net';

            // 组色映射
            var groupColorMap = {
                'administrator': 'var(--md-primary)',
                'editor':        'var(--md-tertiary, var(--md-secondary))',
                'contributor':   'var(--md-secondary)',
                'subscriber':    'var(--md-on-surface-variant)',
                'visitor':       'var(--md-on-surface-variant)'
            };
            var groupLabelMap = {
                'administrator': '管理员',
                'editor':        '编辑',
                'contributor':   '贡献者',
                'subscriber':    '关注者',
                'visitor':       '访问者'
            };

            // 构建卡片网格
            var grid = document.createElement('div');
            grid.className = 'ab-users-grid';

            var rows = table.querySelectorAll('tbody tr');
            Array.prototype.forEach.call(rows, function (tr) {
                var tds = tr.querySelectorAll('td');
                if (tds.length < 6) return;

                // 解析数据
                var checkbox = tds[0].querySelector('input[type="checkbox"]');
                var uid = checkbox ? checkbox.value : '';
                var postsLink = tds[1].querySelector('a');
                var postsNum = postsLink ? postsLink.textContent.trim() : '0';
                var postsHref = postsLink ? postsLink.href : '';
                var nameCell = tds[2];
                var nameLink = nameCell.querySelector('a');
                var userName = nameLink ? nameLink.textContent.trim() : '';
                var userEditHref = nameLink ? nameLink.href : '';
                var extLink = nameCell.querySelector('a[title]');
                var userSiteHref = extLink ? extLink.href : '';
                var screenName = tds[3].textContent.trim();
                var mailCell = tds[4];
                var mailLink = mailCell.querySelector('a');
                var mail = mailLink ? mailLink.textContent.trim() : tds[4].textContent.trim();
                var groupText = tds[5].textContent.trim();

                // 通过 mail MD5 构建头像 URL（仅当存在 mailto: 链接时才请求 Gravatar，避免用"暂无"等文字计算 MD5）
                // 注意：window.__AB_AVATAR_HOST__ 已是完整 URL（含 /avatar），如 "https://gravatar.loli.net/avatar"
                var avatarHtml = '';
                if (mailLink && mail) {
                    var avatarSrc = avatarHost + '/' + AdminBeautify._md5(mail.toLowerCase().trim()) + '?s=80&d=mm&r=g';
                    avatarHtml = '<img class="ab-user-avatar-img" src="' + AdminBeautify._escHtml(avatarSrc) + '" alt="" onerror="this.style.display=\'none\';this.nextSibling.style.display=\'flex\'">' +
                                 '<span class="ab-user-avatar-fallback" style="display:none">' + (userName.charAt(0) || '?').toUpperCase() + '</span>';
                } else {
                    avatarHtml = '<span class="ab-user-avatar-fallback">' + (userName.charAt(0) || '?').toUpperCase() + '</span>';
                }

                // 查找 group 键名
                var groupKey = 'visitor';
                for (var k in groupLabelMap) {
                    if (groupLabelMap[k] === groupText) { groupKey = k; break; }
                }

                var card = document.createElement('div');
                card.className = 'ab-user-card';
                card.id = tr.id || '';

                card.innerHTML =
                    '<div class="ab-user-card-top">' +
                        '<label class="ab-user-card-check"><input type="checkbox" name="uid[]" value="' + AdminBeautify._escHtml(uid) + '"' + (checkbox && checkbox.checked ? ' checked' : '') + '></label>' +
                        '<div class="ab-user-avatar">' + avatarHtml + '</div>' +
                        '<div class="ab-user-meta">' +
                            '<a class="ab-user-name" href="' + AdminBeautify._escHtml(userEditHref) + '">' + AdminBeautify._escHtml(userName) + '</a>' +
                            (screenName && screenName !== userName ? '<span class="ab-user-screen">' + AdminBeautify._escHtml(screenName) + '</span>' : '') +
                            '<span class="ab-user-group" style="color:' + (groupColorMap[groupKey] || 'var(--md-on-surface-variant)') + '">' + AdminBeautify._escHtml(groupText) + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="ab-user-card-body">' +
                        (mail ? '<a class="ab-user-mail" href="mailto:' + AdminBeautify._escHtml(mail) + '"><span class="material-icons-round">mail</span>' + AdminBeautify._escHtml(mail) + '</a>' : '<span class="ab-user-mail ab-user-mail-empty"><span class="material-icons-round">mail_off</span>暂无邮箱</span>') +
                    '</div>' +
                    '<div class="ab-user-card-footer">' +
                        '<a class="ab-user-posts-btn" href="' + AdminBeautify._escHtml(postsHref) + '" title="查看文章"><span class="material-icons-round">article</span>' + AdminBeautify._escHtml(postsNum) + ' 篇</a>' +
                        '<div class="ab-user-actions">' +
                            '<a class="ab-user-btn ab-user-btn-edit" href="' + AdminBeautify._escHtml(userEditHref) + '"><span class="material-icons-round">edit</span>编辑</a>' +
                            (userSiteHref ? '<a class="ab-user-btn ab-user-btn-view" href="' + AdminBeautify._escHtml(userSiteHref) + '" target="_blank"><span class="material-icons-round">open_in_new</span></a>' : '') +
                        '</div>' +
                    '</div>';

                grid.appendChild(card);
            });

            // 将网格插入 table 之前，隐藏原始 table
            table.parentNode.insertBefore(grid, table);
            table.style.display = 'none';

            // 同步批量操作的 checkbox 全选功能（原 form 里的 typecho-table-select-all）
            var selectAlls = listWrap.querySelectorAll('.typecho-table-select-all');
            Array.prototype.forEach.call(selectAlls, function (sa) {
                sa.addEventListener('change', function () {
                    var checked = sa.checked;
                    var boxes = grid.querySelectorAll('input[type="checkbox"]');
                    Array.prototype.forEach.call(boxes, function (cb) { cb.checked = checked; });
                });
            });

            // 将卡片网格内的 checkbox 绑定到原始 form 的批量操作
            // 通过克隆原 form 的提交方式：使用全局 manage_users form
            grid.addEventListener('change', function (e) {
                if (e.target && e.target.type === 'checkbox') {
                    var uid = e.target.value;
                    var origForm = document.querySelector('form[name="manage_users"]');
                    if (!origForm) return;
                    // 同步到原始 form 里的 checkbox
                    var origCb = origForm.querySelector('input[name="uid[]"][value="' + uid + '"]');
                    if (origCb) origCb.checked = e.target.checked;
                }
            });
        },

        /**
         * MD3 文件管理页增强：将媒体文件表格转换为卡片网格，图片展示缩略图
         */
        enhanceMedias: function () {
            if (location.href.indexOf('manage-medias.php') === -1) return;
            var wrap = document.querySelector('.typecho-page-main');
            if (!wrap || wrap.dataset.abMediasEnhanced) return;
            var table = wrap.querySelector('table.typecho-list-table');
            if (!table) return;

            wrap.dataset.abMediasEnhanced = '1';

            // 全局图片加载失败回退处理（用 data-* 避免 innerHTML 中 onerror 属性转义问题）
            if (!window._abMediaImgError) {
                window._abMediaImgError = function (img) {
                    var thumb = img.parentNode;
                    if (!thumb) return;
                    var icon  = img.getAttribute('data-fallback-icon')  || 'image';
                    var color = img.getAttribute('data-fallback-color') || 'var(--md-on-surface-variant)';
                    var span  = document.createElement('span');
                    span.className = 'material-icons-round ab-media-thumb-icon';
                    span.style.color = color;
                    span.textContent = icon;
                    thumb.classList.remove('ab-media-thumb-loading'); // 移除骨架类
                    thumb.innerHTML = '';
                    thumb.appendChild(span);
                };
            }

            // MIME 类型 → 图标 + 颜色
            var mimeIconMap = {
                'image': { icon: 'image',       color: 'var(--md-primary)' },
                'video': { icon: 'videocam',     color: '#e91e63' },
                'audio': { icon: 'audiotrack',   color: '#9c27b0' },
                'doc':   { icon: 'description',  color: '#1976d2' },
                'zip':   { icon: 'folder_zip',   color: '#f57c00' },
                'pdf':   { icon: 'picture_as_pdf', color: '#d32f2f' },
                'xls':   { icon: 'table_chart',  color: '#388e3c' },
                'ppt':   { icon: 'slideshow',    color: '#e64a19' },
                'txt':   { icon: 'text_snippet', color: 'var(--md-on-surface-variant)' }
            };

            function getMimeInfo(mimeClass) {
                // mimeClass 是 CSS class 中 mime-xxx 的 xxx 部分（如 image / video / doc 等）
                return mimeIconMap[mimeClass] || { icon: 'insert_drive_file', color: 'var(--md-on-surface-variant)' };
            }

            function isImageMime(mimeStr) {
                return mimeStr && mimeStr.indexOf('image') !== -1;
            }

            var grid = document.createElement('div');
            grid.className = 'ab-medias-grid';

            var rows = table.querySelectorAll('tbody tr');
            Array.prototype.forEach.call(rows, function (tr) {
                var tds = tr.querySelectorAll('td');
                if (tds.length < 6) return;

                var checkbox = tds[0].querySelector('input[type="checkbox"]');
                var cid = checkbox ? checkbox.value : '';
                var fileCell = tds[2];
                var mimeI = fileCell.querySelector('i[class*="mime-"]');
                var mimeClass = '';
                if (mimeI) {
                    var mimeMatch = mimeI.className.match(/mime-(\S+)/);
                    mimeClass = mimeMatch ? mimeMatch[1] : '';
                }
                var fileLink = fileCell.querySelector('a');
                var fileName = fileLink ? fileLink.textContent.trim() : '';
                var fileEditHref = fileLink ? fileLink.href : '';
                // 文件直链（浏览按钮）
                var extLink = fileCell.querySelector('a[title]');
                var fileViewHref = extLink ? extLink.href : '';
                var authorCell = tds[3];
                var author = authorCell.textContent.trim();
                var parentCell = tds[4];
                var parentLink = parentCell.querySelector('a');
                var parentTitle = parentLink ? parentLink.textContent.trim() : (parentCell.textContent.trim() || '未归档');
                var parentHref = parentLink ? parentLink.href : '';
                var dateCell = tds[5];
                var date = dateCell.textContent.trim();

                var mimeInfo = getMimeInfo(mimeClass);

                // 媒体预览区
                var previewHtml = '';
                if (mimeClass === 'image' || isImageMime(mimeClass)) {
                    // 图片：不预设 src/onerror，等 AJAX 拿到真实 URL 后再赋值，避免 src="" 立刻触发 onerror
                    previewHtml = '<div class="ab-media-thumb ab-media-thumb-image ab-media-thumb-loading">' +
                        '<img alt="" data-cid="' + AdminBeautify._escHtml(cid) + '" data-fallback-icon="image" data-fallback-color="' + mimeInfo.color + '">' +
                        '</div>';
                } else {
                    previewHtml = '<div class="ab-media-thumb ab-media-thumb-icon-wrap">' +
                        '<span class="material-icons-round ab-media-thumb-icon" style="color:' + mimeInfo.color + '">' + mimeInfo.icon + '</span>' +
                        '</div>';
                }

                var card = document.createElement('div');
                card.className = 'ab-media-card';
                card.id = tr.id || '';

                card.innerHTML =
                    '<label class="ab-media-card-check"><input type="checkbox" name="cid[]" value="' + AdminBeautify._escHtml(cid) + '"' + (checkbox && checkbox.checked ? ' checked' : '') + '></label>' +
                    previewHtml +
                    '<div class="ab-media-card-info">' +
                        '<a class="ab-media-filename" href="' + AdminBeautify._escHtml(fileEditHref) + '" title="' + AdminBeautify._escHtml(fileName) + '">' + AdminBeautify._escHtml(fileName) + '</a>' +
                        '<div class="ab-media-meta">' +
                            '<span><span class="material-icons-round">person</span>' + AdminBeautify._escHtml(author) + '</span>' +
                            '<span><span class="material-icons-round">schedule</span>' + AdminBeautify._escHtml(date) + '</span>' +
                        '</div>' +
                        '<div class="ab-media-parent">' +
                            (parentLink
                                ? '<a href="' + AdminBeautify._escHtml(parentHref) + '"><span class="material-icons-round">link</span>' + AdminBeautify._escHtml(parentTitle) + '</a>'
                                : '<span class="ab-media-unarchived"><span class="material-icons-round">link_off</span>' + AdminBeautify._escHtml(parentTitle) + '</span>') +
                        '</div>' +
                    '</div>' +
                    '<div class="ab-media-card-actions">' +
                        '<a class="ab-media-btn ab-media-btn-edit" href="' + AdminBeautify._escHtml(fileEditHref) + '"><span class="material-icons-round">edit</span></a>' +
                        (fileViewHref ? '<a class="ab-media-btn ab-media-btn-view" href="' + AdminBeautify._escHtml(fileViewHref) + '" target="_blank"><span class="material-icons-round">open_in_new</span></a>' : '') +
                    '</div>';

                grid.appendChild(card);
            });

            // 处理空状态
            var emptyRow = table.querySelector('tbody tr td.none');
            if (emptyRow || rows.length === 0) {
                var emptyDiv = document.createElement('div');
                emptyDiv.className = 'ab-medias-empty';
                emptyDiv.innerHTML = '<span class="material-icons-round">perm_media</span><p>没有任何文件</p>';
                table.parentNode.insertBefore(emptyDiv, table);
            } else {
                table.parentNode.insertBefore(grid, table);
            }
            table.style.display = 'none';

            // 批量 AJAX 获取图片真实 URL（通过 Action.php 端点，兼容 PicUp CDN 路径）
            var imageCidMap = {};
            var thumbImgs = grid.querySelectorAll('img[data-cid]');
            Array.prototype.forEach.call(thumbImgs, function (img) {
                var c = img.getAttribute('data-cid');
                if (c) imageCidMap[c] = img;
            });
            var fetchCids = Object.keys(imageCidMap);
            if (fetchCids.length > 0) {
                AdminBeautify.ajax('get-media-urls', { cids: fetchCids.join(',') }, {
                    onSuccess: function (resp) {
                        // ajax() 回调参数是完整响应 {code,message,data}，取 .data 才是 CID→URL 映射
                        var data = (resp && resp.data) ? resp.data : {};
                        for (var c in data) {
                            if (imageCidMap[c] && data[c]) {
                                (function (img, url) {
                                    // onload：图片真正渲染后再移除骨架类，避免 src 刚赋值图片还未解码时白屏
                                    img.onload = function () {
                                        if (img.parentNode) img.parentNode.classList.remove('ab-media-thumb-loading');
                                    };
                                    img.onerror = function () { window._abMediaImgError(img); };
                                    img.src = url;
                                    // 已缓存时 onload 不会再触发，手动检测
                                    if (img.complete && img.naturalWidth > 0) {
                                        if (img.parentNode) img.parentNode.classList.remove('ab-media-thumb-loading');
                                    }
                                }(imageCidMap[c], data[c]));
                            }
                        }
                        // 对未获取到 URL 的图片显示回退图标
                        for (var c in imageCidMap) {
                            if (!data[c]) { window._abMediaImgError(imageCidMap[c]); }
                        }
                    },
                    onError: function () {
                        for (var c in imageCidMap) { window._abMediaImgError(imageCidMap[c]); }
                    }
                });
            }

            // 全选同步
            var selectAlls = wrap.querySelectorAll('.typecho-table-select-all');
            Array.prototype.forEach.call(selectAlls, function (sa) {
                sa.addEventListener('change', function () {
                    var checked = sa.checked;
                    var boxes = grid.querySelectorAll('input[type="checkbox"]');
                    Array.prototype.forEach.call(boxes, function (cb) { cb.checked = checked; });
                });
            });

            // 同步卡片 checkbox 到原始 form
            grid.addEventListener('change', function (e) {
                if (e.target && e.target.type === 'checkbox') {
                    var cidVal = e.target.value;
                    var origForm = document.querySelector('form[name="manage_medias"]');
                    if (!origForm) return;
                    var origCb = origForm.querySelector('input[name="cid[]"][value="' + cidVal + '"]');
                    if (origCb) origCb.checked = e.target.checked;
                }
            });

            // ---- 新增按钮 & 上传对话框 ----
            // .typecho-page-title 是 .typecho-page-main 的兄弟节点，需用 document 查询
            var self = this;
            var pageTitle = document.querySelector('.typecho-page-title');
            if (pageTitle && !pageTitle.querySelector('.ab-media-new-btn')) {
                var newBtn = document.createElement('button');
                newBtn.type = 'button';
                newBtn.className = 'ab-media-new-btn';
                newBtn.innerHTML = '<span class="material-icons-round">add</span><span>新增</span>';
                newBtn.addEventListener('click', function () {
                    self._openMediaUploadDialog(grid);
                });
                pageTitle.appendChild(newBtn);
            }
        },

        /**
         * 打开文件上传对话框（MD3 全屏遮罩，拖拽 + 点击选择，上传后返回 URL 并刷新卡片列表）
         */
        _openMediaUploadDialog: function (grid) {
            if (document.getElementById('ab-upload-dialog')) return;

            var self = this;

            // ---- 遮罩 ----
            var overlay = document.createElement('div');
            overlay.id = 'ab-upload-dialog-overlay';
            overlay.className = 'ab-upload-overlay';

            // ---- 对话框面板 ----
            var dialog = document.createElement('div');
            dialog.id = 'ab-upload-dialog';
            dialog.className = 'ab-upload-dialog';
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('aria-label', '上传文件');

            dialog.innerHTML =
                '<div class="ab-upload-dialog-header">' +
                    '<h2 class="ab-upload-dialog-title">上传文件</h2>' +
                    '<button type="button" class="ab-upload-close-btn" aria-label="关闭">' +
                        '<span class="material-icons-round">close</span>' +
                    '</button>' +
                '</div>' +
                '<div class="ab-upload-dialog-body">' +
                    '<div class="ab-upload-dropzone" id="ab-upload-dropzone">' +
                        '<span class="material-icons-round ab-upload-dropzone-icon">cloud_upload</span>' +
                        '<p class="ab-upload-dropzone-hint">将文件拖拽至此，或<button type="button" class="ab-upload-select-btn">点击选择文件</button></p>' +
                        '<p class="ab-upload-dropzone-sub">支持多文件，Ctrl/Cmd 多选</p>' +
                        '<input type="file" id="ab-upload-input" multiple style="display:none">' +
                    '</div>' +
                    '<ul class="ab-upload-file-list" id="ab-upload-file-list"></ul>' +
                '</div>' +
                '<div class="ab-upload-dialog-footer">' +
                    '<button type="button" class="ab-upload-clear-btn" id="ab-upload-clear-btn">清空列表</button>' +
                    '<button type="button" class="ab-upload-submit-btn" id="ab-upload-submit-btn">开始上传</button>' +
                '</div>';

            overlay.appendChild(dialog);
            document.body.appendChild(overlay);

            // ---- 状态 ----
            var pendingFiles = []; // {file, id, itemEl}

            // ---- 工具 ----
            function genId() {
                return 'f' + Math.random().toString(36).slice(2, 9);
            }
            function fmtSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / 1024 / 1024).toFixed(2) + ' MB';
            }

            // ---- 渲染单个文件 item ----
            function renderItem(id, file) {
                var li = document.createElement('li');
                li.className = 'ab-upload-item';
                li.id = 'ab-upload-item-' + id;
                li.innerHTML =
                    '<span class="material-icons-round ab-upload-item-icon">insert_drive_file</span>' +
                    '<span class="ab-upload-item-name" title="' + AdminBeautify._escHtml(file.name) + '">' + AdminBeautify._escHtml(file.name) + '</span>' +
                    '<span class="ab-upload-item-size">' + fmtSize(file.size) + '</span>' +
                    '<span class="ab-upload-item-status" id="ab-upload-status-' + id + '"></span>' +
                    '<button type="button" class="ab-upload-item-remove" data-id="' + id + '" aria-label="移除"><span class="material-icons-round">close</span></button>';
                return li;
            }

            // ---- 添加文件 ----
            function addFiles(files) {
                var list = document.getElementById('ab-upload-file-list');
                for (var i = 0; i < files.length; i++) {
                    var f = files[i];
                    var id = genId();
                    var item = { file: f, id: id };
                    pendingFiles.push(item);
                    var li = renderItem(id, f);
                    item.itemEl = li;
                    list.appendChild(li);
                }
                updateSubmitBtn();
            }

            function updateSubmitBtn() {
                var btn = document.getElementById('ab-upload-submit-btn');
                if (btn) {
                    btn.disabled = pendingFiles.length === 0;
                }
            }

            // ---- 移除文件 ----
            var fileList = document.getElementById('ab-upload-file-list');
            fileList.addEventListener('click', function (e) {
                var removeBtn = e.target.closest('.ab-upload-item-remove');
                if (!removeBtn) return;
                var id = removeBtn.getAttribute('data-id');
                pendingFiles = pendingFiles.filter(function (item) { return item.id !== id; });
                var li = document.getElementById('ab-upload-item-' + id);
                if (li) li.remove();
                updateSubmitBtn();
            });

            // ---- 文件选择 / 拖拽 ----
            var fileInput = document.getElementById('ab-upload-input');
            var dropzone  = document.getElementById('ab-upload-dropzone');
            var selectBtn = dropzone.querySelector('.ab-upload-select-btn');

            selectBtn.addEventListener('click', function () { fileInput.click(); });
            fileInput.addEventListener('change', function () {
                if (fileInput.files.length) addFiles(fileInput.files);
                fileInput.value = '';
            });

            dropzone.addEventListener('dragover', function (e) {
                e.preventDefault();
                dropzone.classList.add('ab-upload-dropzone-drag');
            });
            dropzone.addEventListener('dragleave', function (e) {
                if (!dropzone.contains(e.relatedTarget)) {
                    dropzone.classList.remove('ab-upload-dropzone-drag');
                }
            });
            dropzone.addEventListener('drop', function (e) {
                e.preventDefault();
                dropzone.classList.remove('ab-upload-dropzone-drag');
                if (e.dataTransfer && e.dataTransfer.files.length) addFiles(e.dataTransfer.files);
            });

            // ---- 清空 ----
            document.getElementById('ab-upload-clear-btn').addEventListener('click', function () {
                pendingFiles = [];
                fileList.innerHTML = '';
                updateSubmitBtn();
            });

            // ---- 关闭 ----
            function closeDialog() {
                var el = document.getElementById('ab-upload-dialog-overlay');
                if (el) {
                    el.classList.add('ab-upload-overlay-out');
                    setTimeout(function () { if (el.parentNode) el.remove(); }, 250);
                }
            }
            overlay.querySelector('.ab-upload-close-btn').addEventListener('click', closeDialog);
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeDialog();
            });
            document.addEventListener('keydown', function escHandler(e) {
                if (e.key === 'Escape') { closeDialog(); document.removeEventListener('keydown', escHandler); }
            });

            // ---- 上传 ----
            document.getElementById('ab-upload-submit-btn').addEventListener('click', function () {
                if (!pendingFiles.length) return;

                var submitBtn = document.getElementById('ab-upload-submit-btn');
                submitBtn.disabled = true;
                submitBtn.textContent = '上传中…';

                var cfg = window.__AB_AJAX__;
                if (!cfg || !cfg.url) {
                    AdminBeautify.showNotice('AJAX 配置未找到', 'error', 4000);
                    submitBtn.disabled = false;
                    submitBtn.textContent = '开始上传';
                    return;
                }

                var url = cfg.url + '?do=upload-media';
                if (cfg.token) url += '&_=' + encodeURIComponent(cfg.token);

                // 将所有文件塞进一个 FormData，批量提交
                var fd = new FormData();
                for (var i = 0; i < pendingFiles.length; i++) {
                    fd.append('files[]', pendingFiles[i].file);
                    // 设置 pending 状态
                    var statusEl = document.getElementById('ab-upload-status-' + pendingFiles[i].id);
                    if (statusEl) {
                        statusEl.className = 'ab-upload-item-status ab-upload-item-status-pending';
                        statusEl.textContent = '等待中';
                    }
                }

                var xhr = new XMLHttpRequest();
                xhr.open('POST', url, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                // 进度
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        var pct = Math.round(e.loaded / e.total * 100);
                        submitBtn.textContent = '上传中 ' + pct + '%…';
                    }
                });

                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) return;
                    submitBtn.disabled = false;
                    submitBtn.textContent = '开始上传';
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (xhr.status >= 200 && xhr.status < 300 && resp.code === 0) {
                            var data = resp.data || {};
                            var uploaded = data.uploaded || [];
                            var failed   = data.failed   || [];

                            // 结果面板 —— 用 snackbar + 内嵌列表展示 URL
                            if (uploaded.length > 0) {
                                // 在对话框内显示结果面板
                                var resultsArea = document.getElementById('ab-upload-results');
                                if (!resultsArea) {
                                    resultsArea = document.createElement('div');
                                    resultsArea.id = 'ab-upload-results';
                                    resultsArea.className = 'ab-upload-results';
                                    dialog.querySelector('.ab-upload-dialog-body').appendChild(resultsArea);
                                }
                                resultsArea.innerHTML = '<p class="ab-upload-results-title"><span class="material-icons-round">check_circle</span>上传成功 ' + uploaded.length + ' 个文件</p>';
                                var urlList = document.createElement('ul');
                                urlList.className = 'ab-upload-url-list';
                                for (var ui = 0; ui < uploaded.length; ui++) {
                                    var u = uploaded[ui];
                                    var urlLi = document.createElement('li');
                                    urlLi.className = 'ab-upload-url-item';
                                    urlLi.innerHTML =
                                        '<span class="ab-upload-url-name" title="' + AdminBeautify._escHtml(u.name) + '">' + AdminBeautify._escHtml(u.name) + '</span>' +
                                        '<span class="ab-upload-url-val" title="' + AdminBeautify._escHtml(u.url) + '">' + AdminBeautify._escHtml(u.url) + '</span>' +
                                        '<button type="button" class="ab-upload-url-copy" data-url="' + AdminBeautify._escHtml(u.url) + '" title="复制 URL">' +
                                            '<span class="material-icons-round">content_copy</span>' +
                                        '</button>';
                                    urlList.appendChild(urlLi);
                                }
                                resultsArea.appendChild(urlList);

                                // 复制 URL 按钮
                                urlList.addEventListener('click', function (e) {
                                    var copyBtn = e.target.closest('.ab-upload-url-copy');
                                    if (!copyBtn) return;
                                    var urlToCopy = copyBtn.getAttribute('data-url');
                                    if (navigator.clipboard) {
                                        navigator.clipboard.writeText(urlToCopy).then(function () {
                                            AdminBeautify.showNotice('URL 已复制', 'success', 2000);
                                        });
                                    } else {
                                        var ta = document.createElement('textarea');
                                        ta.value = urlToCopy;
                                        document.body.appendChild(ta);
                                        ta.select();
                                        document.execCommand('copy');
                                        ta.remove();
                                        AdminBeautify.showNotice('URL 已复制', 'success', 2000);
                                    }
                                });

                                // 更新 pending 状态 → 成功
                                for (var si = 0; si < uploaded.length; si++) {
                                    // 按名字回查（批量上传没有精确 ID 映射）
                                    for (var pi = 0; pi < pendingFiles.length; pi++) {
                                        if (pendingFiles[pi].file.name === uploaded[si].name) {
                                            var sEl = document.getElementById('ab-upload-status-' + pendingFiles[pi].id);
                                            if (sEl) {
                                                sEl.className = 'ab-upload-item-status ab-upload-item-status-ok';
                                                sEl.textContent = '成功';
                                            }
                                        }
                                    }
                                }

                                AdminBeautify.showNotice('已成功上传 ' + uploaded.length + ' 个文件', 'success', 3000);
                            }

                            if (failed.length > 0) {
                                AdminBeautify.showNotice('以下文件上传失败：' + failed.join('、'), 'error', 6000);
                                for (var fi = 0; fi < failed.length; fi++) {
                                    for (var pj = 0; pj < pendingFiles.length; pj++) {
                                        if (pendingFiles[pj].file.name === failed[fi] || failed[fi].indexOf(pendingFiles[pj].file.name) !== -1) {
                                            var fEl = document.getElementById('ab-upload-status-' + pendingFiles[pj].id);
                                            if (fEl) {
                                                fEl.className = 'ab-upload-item-status ab-upload-item-status-fail';
                                                fEl.textContent = '失败';
                                            }
                                        }
                                    }
                                }
                            }

                            // 清空已成功的文件
                            var successNames = {};
                            for (var un = 0; un < uploaded.length; un++) { successNames[uploaded[un].name] = true; }
                            pendingFiles = pendingFiles.filter(function (item) {
                                return !successNames[item.file.name];
                            });
                            updateSubmitBtn();

                            // 无论成功/失败均刷新卡片网格
                            self._refreshMediaGrid(grid);

                        } else {
                            AdminBeautify.showNotice('上传失败：' + (resp.message || '未知错误'), 'error', 5000);
                            self._refreshMediaGrid(grid);
                        }
                    } catch (e) {
                        AdminBeautify.showNotice('上传响应解析失败', 'error', 4000);
                        self._refreshMediaGrid(grid);
                    }
                };

                xhr.send(fd);
            });

            // 初始化时禁用提交按钮
            updateSubmitBtn();

            // 入场动画
            requestAnimationFrame(function () {
                overlay.classList.add('ab-upload-overlay-in');
            });
        },

        /**
         * 无感刷新媒体卡片网格（AJAX 获取最新文件列表，重建卡片）
         */
        _refreshMediaGrid: function (grid) {
            // 重新增强：先清除增强标记，再重新获取最新数据
            var wrap = document.querySelector('.typecho-page-main');
            if (!wrap) return;

            var self = this;
            // 构建当前页 URL（带原有分页/筛选参数）
            var fetchUrl = location.href;

            var xhr = new XMLHttpRequest();
            xhr.open('GET', fetchUrl, true);
            // 不设置 X-Requested-With，避免 Typecho 鉴权层将 AJAX 请求重定向为非 HTML 响应
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4 || xhr.status < 200 || xhr.status >= 300) return;
                try {
                    var parser = new DOMParser();
                    var doc    = parser.parseFromString(xhr.responseText, 'text/html');
                    var newTable = doc.querySelector('.typecho-page-main table.typecho-list-table');
                    if (!newTable) return;

                    // 清除旧网格和空状态
                    var oldGrid = wrap.querySelector('.ab-medias-grid');
                    if (oldGrid) oldGrid.remove();
                    var oldEmpty = wrap.querySelector('.ab-medias-empty');
                    if (oldEmpty) oldEmpty.remove();

                    // 替换 table 内容
                    var oldTable = wrap.querySelector('table.typecho-list-table');
                    if (oldTable) {
                        oldTable.innerHTML = newTable.innerHTML;
                        oldTable.style.display = 'none';
                    }

                    // 重置标记，重新增强
                    delete wrap.dataset.abMediasEnhanced;
                    self.enhanceMedias();

                } catch (e) {
                    // 静默失败，不影响已上传结果的展示
                }
            };
            xhr.send();
        },

        /**
         * MD3 media.php 媒体详情页增强：删除按钮样式
         */
        enhanceMediaEdit: function () {
            if (location.href.indexOf('media.php') === -1) return;
            var editCol = document.querySelector('.edit-media');
            if (!editCol || editCol.dataset.abMediaEditEnhanced) return;
            editCol.dataset.abMediaEditEnhanced = '1';

            // 等待 form render 完成（media.php 使用 $attachment->form()->render()）
            var self = this;
            function doEnhance() {
                // 查找删除按钮（.operate-delete 或含 "删除" 文字的 a）
                var deleteLinks = editCol.querySelectorAll('a.operate-delete, a[lang*="删除"]');
                Array.prototype.forEach.call(deleteLinks, function (link) {
                    if (link.dataset.abEnhanced) return;
                    link.dataset.abEnhanced = '1';
                    link.classList.add('ab-media-delete-btn');
                    if (!link.querySelector('.material-icons-round')) {
                        var icon = document.createElement('span');
                        icon.className = 'material-icons-round';
                        icon.textContent = 'delete_forever';
                        link.insertBefore(icon, link.firstChild);
                    }
                });

                // 提交按钮
                var submitBtns = editCol.querySelectorAll('input[type="submit"], button[type="submit"]');
                Array.prototype.forEach.call(submitBtns, function (btn) {
                    if (btn.dataset.abEnhanced) return;
                    btn.dataset.abEnhanced = '1';
                    btn.classList.add('ab-media-submit-btn');
                });
            }
            doEnhance();
            // 如果 form 是异步渲染的，用 MutationObserver 补充
            var mo = new MutationObserver(function() { doEnhance(); });
            mo.observe(editCol, { childList: true, subtree: true });
            setTimeout(function() { mo.disconnect(); }, 3000);
        },

        /**
         * MD3 标签管理页增强（manage-tags.php）
         * 将原始 .tag-list <ul> 替换为 MD3 Chip 风格的标签云，
         * 支持多选、全选联动、以及 AJAX 无刷新删除/刷新/合并操作。
         */
        enhanceTags: function () {
            if (location.href.indexOf('manage-tags.php') === -1) return;
            var tagList = document.querySelector('ul.typecho-list-notable.tag-list');
            if (!tagList || tagList.dataset.abTagsEnhanced) return;
            tagList.dataset.abTagsEnhanced = '1';

            var form = document.querySelector('form[name="manage_tags"]');
            // 右侧编辑面板
            var editPanel = document.querySelector('div[role="form"].col-tb-4') ||
                            document.querySelector('.col-mb-12.col-tb-4[role="form"]');

            // ── 构建 MD3 chip 云容器 ──────────────────────────────────
            var cloud = document.createElement('div');
            cloud.className = 'ab-tag-cloud';

            // chipMap: cbVal → chip div（用于全选联动）
            var chipMap = {};

            // 检测当前 URL 是否正在编辑某个标签
            var urlParams = new URLSearchParams(location.search);
            var editingMid = urlParams.get('mid') || '';

            // ── AJAX 加载编辑面板 ──────────────────────────────────────
            function _loadTagEdit(mid, href) {
                // 更新 editing 高亮
                Object.keys(chipMap).forEach(function (v) {
                    chipMap[v].classList.remove('ab-tag-chip-editing');
                });
                if (mid && chipMap[mid]) chipMap[mid].classList.add('ab-tag-chip-editing');
                editingMid = mid;

                // pushState（不刷新页面）
                if (history.pushState) history.pushState({ mid: mid }, '', href);

                if (!editPanel) return;
                editPanel.classList.add('ab-tag-edit-loading');

                fetch(href, { credentials: 'same-origin' })
                    .then(function (res) { return res.text(); })
                    .then(function (html) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(html, 'text/html');
                        var newPanel = doc.querySelector('div[role="form"].col-tb-4') ||
                                       doc.querySelector('.col-mb-12.col-tb-4[role="form"]');
                        if (newPanel) {
                            editPanel.innerHTML = newPanel.innerHTML;
                            _bindEditFormSubmit(); // 拦截新表单的提交
                        }
                        editPanel.classList.remove('ab-tag-edit-loading');
                        // 滚动到编辑面板（移动端友好）
                        editPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    })
                    .catch(function () {
                        editPanel.classList.remove('ab-tag-edit-loading');
                        AdminBeautify.showNotice('加载编辑表单失败', 'error');
                    });
            }

            // ── 拦截编辑面板内的表单提交（AJAX 无刷新保存） ─────────────
            function _bindEditFormSubmit() {
                if (!editPanel) return;
                var ef = editPanel.querySelector('form');
                if (!ef || ef.dataset.abAjaxBound) return;
                ef.dataset.abAjaxBound = '1';
                ef.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var actionUrl = ef.getAttribute('action') || location.href;
                    var fd = new FormData(ef);
                    editPanel.classList.add('ab-tag-edit-loading');
                    fetch(actionUrl, {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin',
                        redirect: 'follow'
                    }).then(function (res) { return res.text(); })
                    .then(function (html) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(html, 'text/html');
                        // 刷新 cloud（标签名可能已改变）
                        var newList = doc.querySelector('ul.typecho-list-notable.tag-list');
                        if (newList) _rebuildCloud(newList, editingMid);
                        // 刷新编辑面板
                        var newPanel = doc.querySelector('div[role="form"].col-tb-4') ||
                                       doc.querySelector('.col-mb-12.col-tb-4[role="form"]');
                        if (newPanel) {
                            editPanel.innerHTML = newPanel.innerHTML;
                            _bindEditFormSubmit();
                        }
                        editPanel.classList.remove('ab-tag-edit-loading');
                        AdminBeautify.showNotice('标签已保存', 'success');
                    })
                    .catch(function () {
                        editPanel.classList.remove('ab-tag-edit-loading');
                        AdminBeautify.showNotice('保存失败，请刷新页面重试', 'error');
                    });
                });
            }

            // ── 构建 / 重建 chip 的公用逻辑 ───────────────────────────
            function _makeChip(name, cbVal, editHref, sizeClass, cb) {
                var chip = document.createElement('div');
                chip.className = 'ab-tag-chip' + (sizeClass ? ' ' + sizeClass : '');
                chip.setAttribute('data-mid', cbVal);
                chip.innerHTML =
                    '<span class="ab-tag-chip-toggle" aria-label="选择">' +
                        '<span class="material-icons-round ab-tag-chip-check">check</span>' +
                        '<span class="material-icons-round ab-tag-icon">label</span>' +
                    '</span>' +
                    '<a class="ab-tag-chip-name" href="' + AdminBeautify._escHtml(editHref) + '">' +
                        AdminBeautify._escHtml(name) +
                    '</a>' +
                    '<a class="ab-tag-chip-edit material-icons-round" href="' + AdminBeautify._escHtml(editHref) + '" title="编辑">edit</a>';

                // 选中逻辑（点 toggle 区域 / chip 非链接部分）
                chip.addEventListener('click', function (e) {
                    if (e.target.tagName === 'A' || e.target.closest('a')) return;
                    var selected = !chip.classList.contains('ab-tag-chip-selected');
                    chip.classList.toggle('ab-tag-chip-selected', selected);
                    if (cb) cb.checked = selected;
                    _updateSelectAll();
                });

                // 拦截编辑链接（AJAX 加载）
                var links = chip.querySelectorAll('.ab-tag-chip-name, .ab-tag-chip-edit');
                Array.prototype.forEach.call(links, function (a) {
                    a.addEventListener('click', function (e) {
                        e.preventDefault();
                        _loadTagEdit(cbVal, a.getAttribute('href'));
                    });
                });

                return chip;
            }

            // ── 重建 cloud（从服务器返回的 newList DOM） ──────────────
            function _rebuildCloud(newList, keepEditingMid) {
                cloud.innerHTML = '';
                chipMap = {};
                var newItems = newList.querySelectorAll('li[id]');
                Array.prototype.forEach.call(newItems, function (li) {
                    var cb2    = li.querySelector('input[type="checkbox"]');
                    var ns     = li.querySelector('span');
                    var el     = li.querySelector('a.tag-edit-link');
                    if (!ns) return;
                    var name2  = ns.textContent.trim();
                    var cv2    = cb2 ? cb2.value : '';
                    var eh     = el ? el.getAttribute('href') : '#';
                    var sc     = '';
                    li.className.split(' ').forEach(function (c) { if (c.indexOf('size-') === 0) sc = c; });
                    var chip2 = _makeChip(name2, cv2, eh, sc, cb2);
                    if (cv2 === keepEditingMid) chip2.classList.add('ab-tag-chip-editing');
                    chipMap[cv2] = chip2;
                    cloud.appendChild(chip2);
                });
                tagList.innerHTML = newList.innerHTML;
            }

            // ── 初始构建 chip 列表 ────────────────────────────────────
            var items = tagList.querySelectorAll('li[id]');
            Array.prototype.forEach.call(items, function (li) {
                var cb       = li.querySelector('input[type="checkbox"]');
                var nameSpan = li.querySelector('span');
                var editLink = li.querySelector('a.tag-edit-link');
                if (!nameSpan) return;

                var name     = nameSpan.textContent.trim();
                var cbVal    = cb ? cb.value : '';
                var editHref = editLink ? editLink.getAttribute('href') : '#';
                var sizeClass = '';
                li.className.split(' ').forEach(function (c) {
                    if (c.indexOf('size-') === 0) sizeClass = c;
                });

                var chip = _makeChip(name, cbVal, editHref, sizeClass, cb);
                // 页面加载时高亮正在编辑的标签
                if (editingMid && cbVal === editingMid) chip.classList.add('ab-tag-chip-editing');

                chipMap[cbVal] = chip;
                cloud.appendChild(chip);
            });

            // 页面加载时若有编辑面板，拦截其提交
            _bindEditFormSubmit();

            // 全选按钮状态刷新
            function _updateSelectAll() {
                var total    = Object.keys(chipMap).length;
                var selected = cloud.querySelectorAll('.ab-tag-chip-selected').length;
                var sas = document.querySelectorAll('.typecho-table-select-all');
                Array.prototype.forEach.call(sas, function (sa) {
                    sa.checked = total > 0 && selected === total;
                    sa.indeterminate = selected > 0 && selected < total;
                });
            }

            // 插入云容器，隐藏原始列表
            tagList.parentNode.insertBefore(cloud, tagList);
            tagList.style.display = 'none';

            // ── select-all 联动 ────────────────────────────────────────
            var selectAlls = document.querySelectorAll('.typecho-table-select-all');
            Array.prototype.forEach.call(selectAlls, function (sa) {
                sa.addEventListener('change', function () {
                    var checked = sa.checked;
                    Object.keys(chipMap).forEach(function (v) {
                        chipMap[v].classList.toggle('ab-tag-chip-selected', checked);
                    });
                    var hiddenCbs = tagList.querySelectorAll('input[type="checkbox"]');
                    Array.prototype.forEach.call(hiddenCbs, function (cb) { cb.checked = checked; });
                });
            });

            // ── AJAX 操作：删除 / 刷新 ─────────────────────────────────
            function _getSelectedMids() {
                var mids = [];
                Array.prototype.forEach.call(cloud.querySelectorAll('.ab-tag-chip-selected'), function (c) {
                    mids.push(c.getAttribute('data-mid'));
                });
                return mids;
            }

            function _doTagAction(actionUrl, extraFields, onDone) {
                var mids = _getSelectedMids();
                if (mids.length === 0) {
                    AdminBeautify.showNotice('请先选择标签', 'warning');
                    return;
                }
                cloud.classList.add('ab-tag-cloud-loading');
                var fd = new FormData();
                mids.forEach(function (m) { fd.append('mid[]', m); });
                if (extraFields) Object.keys(extraFields).forEach(function (k) { fd.append(k, extraFields[k]); });

                fetch(actionUrl, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    redirect: 'follow'
                }).then(function (res) {
                    return res.text();
                }).then(function (html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    var newList = doc.querySelector('ul.typecho-list-notable.tag-list');
                    if (newList) _rebuildCloud(newList, editingMid);
                    cloud.classList.remove('ab-tag-cloud-loading');
                    if (onDone) onDone(true);
                }).catch(function () {
                    cloud.classList.remove('ab-tag-cloud-loading');
                    AdminBeautify.showNotice('操作失败，请刷新页面重试', 'error');
                    if (onDone) onDone(false);
                });
            }

            // 绑定下拉菜单操作（删除 / 刷新）
            var dropLinks = document.querySelectorAll('.dropdown-menu a[lang]');
            Array.prototype.forEach.call(dropLinks, function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var confirmMsg = link.getAttribute('lang');
                    var doAction = function () {
                        var actionUrl = link.getAttribute('href');
                        var doMatch = actionUrl.match(/[?&]do=([^&]+)/);
                        var doVal = doMatch ? doMatch[1] : 'delete';
                        _doTagAction(actionUrl, { do: doVal }, function (ok) {
                            if (ok) AdminBeautify.showNotice('操作成功', 'success');
                        });
                    };
                    if (confirmMsg) { AdminBeautify.confirm(confirmMsg).then(function (ok) { if (ok) doAction(); }); }
                    else { doAction(); }
                });
            });

            // 绑定合并按钮
            var mergeBtn = document.querySelector('.dropdown-menu button.merge');
            if (mergeBtn) {
                mergeBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var mergeInput = document.querySelector('input[name="merge"]');
                    var mergeTo = mergeInput ? mergeInput.value.trim() : '';
                    if (!mergeTo) { AdminBeautify.showNotice('请输入要合并到的标签名', 'warning'); return; }
                    var actionUrl = mergeBtn.getAttribute('rel');
                    _doTagAction(actionUrl, { do: 'merge', merge: mergeTo }, function (ok) {
                        if (ok) { AdminBeautify.showNotice('合并成功', 'success'); if (mergeInput) mergeInput.value = ''; }
                    });
                });
            }
        },

        /**
         * 简单的 MD5 实现（用于生成 Gravatar hash）
         */
        _md5: (function(){
            function safeAdd(x, y){ var lsw=(x&0xFFFF)+(y&0xFFFF);var msw=(x>>16)+(y>>16)+(lsw>>16);return(msw<<16)|(lsw&0xFFFF); }
            function bitRotateLeft(num,cnt){ return(num<<cnt)|(num>>>(32-cnt)); }
            function md5cmn(q,a,b,x,s,t){ return safeAdd(bitRotateLeft(safeAdd(safeAdd(a,q),safeAdd(x,t)),s),b); }
            function md5ff(a,b,c,d,x,s,t){ return md5cmn((b&c)|((~b)&d),a,b,x,s,t); }
            function md5gg(a,b,c,d,x,s,t){ return md5cmn((b&d)|(c&(~d)),a,b,x,s,t); }
            function md5hh(a,b,c,d,x,s,t){ return md5cmn(b^c^d,a,b,x,s,t); }
            function md5ii(a,b,c,d,x,s,t){ return md5cmn(c^(b|(~d)),a,b,x,s,t); }
            function md5blk(s){ var md5blks=[],i;for(i=0;i<64;i+=4){md5blks[i>>2]=s.charCodeAt(i)+(s.charCodeAt(i+1)<<8)+(s.charCodeAt(i+2)<<16)+(s.charCodeAt(i+3)<<24);}return md5blks; }
            function md5blks2(s){ var md5blks=[],i;for(i=0;i<64;i+=4){md5blks[i>>2]=s[i]+(s[i+1]<<8)+(s[i+2]<<16)+(s[i+3]<<24);}return md5blks; }
            function rhex(n){ var s='',j=0;for(;j<4;j++){s+=((n>>(j*8+4))&0x0F).toString(16)+((n>>(j*8))&0x0F).toString(16);}return s; }
            function hex(x){ var i=0,il=x.length,s='';for(;i<il;i++){s+=rhex(x[i]);}return s; }
            function md51(s){
                var n=s.length,state=[1732584193,-271733879,-1732584194,271733878],i,length32,tail;
                for(i=64;i<=n;i+=64){md5cycle(state,md5blk(s.substring(i-64,i)));}
                s=s.substring(i-64);length32=s.length;tail=[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0];
                for(i=0;i<length32;i++){tail[i>>2]|=s.charCodeAt(i)<<((i%4)*8);}
                tail[i>>2]|=0x80<<((i%4)*8);
                if(i>55){md5cycle(state,tail);tail=[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0];}
                tail[14]=n*8; md5cycle(state,tail); return state;
            }
            function md5cycle(x,k){
                var a=x[0],b=x[1],c=x[2],d=x[3];
                a=md5ff(a,b,c,d,k[0],7,-680876936);d=md5ff(d,a,b,c,k[1],12,-389564586);c=md5ff(c,d,a,b,k[2],17,606105819);b=md5ff(b,c,d,a,k[3],22,-1044525330);
                a=md5ff(a,b,c,d,k[4],7,-176418897);d=md5ff(d,a,b,c,k[5],12,1200080426);c=md5ff(c,d,a,b,k[6],17,-1473231341);b=md5ff(b,c,d,a,k[7],22,-45705983);
                a=md5ff(a,b,c,d,k[8],7,1770035416);d=md5ff(d,a,b,c,k[9],12,-1958414417);c=md5ff(c,d,a,b,k[10],17,-42063);b=md5ff(b,c,d,a,k[11],22,-1990404162);
                a=md5ff(a,b,c,d,k[12],7,1804603682);d=md5ff(d,a,b,c,k[13],12,-40341101);c=md5ff(c,d,a,b,k[14],17,-1502002290);b=md5ff(b,c,d,a,k[15],22,1236535329);
                a=md5gg(a,b,c,d,k[1],5,-165796510);d=md5gg(d,a,b,c,k[6],9,-1069501632);c=md5gg(c,d,a,b,k[11],14,643717713);b=md5gg(b,c,d,a,k[0],20,-373897302);
                a=md5gg(a,b,c,d,k[5],5,-701558691);d=md5gg(d,a,b,c,k[10],9,38016083);c=md5gg(c,d,a,b,k[15],14,-660478335);b=md5gg(b,c,d,a,k[4],20,-405537848);
                a=md5gg(a,b,c,d,k[9],5,568446438);d=md5gg(d,a,b,c,k[14],9,-1019803690);c=md5gg(c,d,a,b,k[3],14,-187363961);b=md5gg(b,c,d,a,k[8],20,1163531501);
                a=md5gg(a,b,c,d,k[13],5,-1444681467);d=md5gg(d,a,b,c,k[2],9,-51403784);c=md5gg(c,d,a,b,k[7],14,1735328473);b=md5gg(b,c,d,a,k[12],20,-1926607734);
                a=md5hh(a,b,c,d,k[5],4,-378558);d=md5hh(d,a,b,c,k[8],11,-2022574463);c=md5hh(c,d,a,b,k[11],16,1839030562);b=md5hh(b,c,d,a,k[14],23,-35309556);
                a=md5hh(a,b,c,d,k[1],4,-1530992060);d=md5hh(d,a,b,c,k[4],11,1272893353);c=md5hh(c,d,a,b,k[7],16,-155497632);b=md5hh(b,c,d,a,k[10],23,-1094730640);
                a=md5hh(a,b,c,d,k[13],4,681279174);d=md5hh(d,a,b,c,k[0],11,-358537222);c=md5hh(c,d,a,b,k[3],16,-722521979);b=md5hh(b,c,d,a,k[6],23,76029189);
                a=md5hh(a,b,c,d,k[9],4,-640364487);d=md5hh(d,a,b,c,k[12],11,-421815835);c=md5hh(c,d,a,b,k[15],16,530742520);b=md5hh(b,c,d,a,k[2],23,-995338651);
                a=md5ii(a,b,c,d,k[0],6,-198630844);d=md5ii(d,a,b,c,k[7],10,1126891415);c=md5ii(c,d,a,b,k[14],15,-1416354905);b=md5ii(b,c,d,a,k[5],21,-57434055);
                a=md5ii(a,b,c,d,k[12],6,1700485571);d=md5ii(d,a,b,c,k[3],10,-1894986606);c=md5ii(c,d,a,b,k[10],15,-1051523);b=md5ii(b,c,d,a,k[1],21,-2054922799);
                a=md5ii(a,b,c,d,k[8],6,1873313359);d=md5ii(d,a,b,c,k[15],10,-30611744);c=md5ii(c,d,a,b,k[6],15,-1560198380);b=md5ii(b,c,d,a,k[13],21,1309151649);
                a=md5ii(a,b,c,d,k[4],6,-145523070);d=md5ii(d,a,b,c,k[11],10,-1120210379);c=md5ii(c,d,a,b,k[2],15,718787259);b=md5ii(b,c,d,a,k[9],21,-343485551);
                x[0]=safeAdd(a,x[0]);x[1]=safeAdd(b,x[1]);x[2]=safeAdd(c,x[2]);x[3]=safeAdd(d,x[3]);
            }
            return function(str){ return hex(md51(str)); };
        })(),

        /**
         * 下拉菜单增强 — 点击外部关闭
         */
        enhanceDropdowns: function () {
            // Typecho 的 dropdownMenu 只在按钮点击时 toggle，
            // 没有点击外部关闭的处理。这里补充一个 document 级别的 click handler。
            // 注意：Typecho 按钮的 click handler 会 return false（stopPropagation），
            // 所以按钮点击不会冒泡到 document，不会与此 handler 冲突。
            document.addEventListener('click', function (e) {
                // 如果点击的是 btn-drop 内部区域，不做处理（让 Typecho 原生 toggle 生效）
                if (e.target.closest('.btn-drop')) return;

                // 关闭所有打开的下拉菜单
                var menus = document.querySelectorAll('.btn-drop .dropdown-menu');
                for (var i = 0; i < menus.length; i++) {
                    menus[i].style.display = 'none';
                }
                var toggles = document.querySelectorAll('.btn-drop .dropdown-toggle');
                for (var j = 0; j < toggles.length; j++) {
                    toggles[j].classList.remove('active');
                }
            });
        },

        /**
         * 添加深色模式切换按钮到导航栏
         */
        addThemeToggle: function () {
            var operateLi = document.querySelector('.typecho-head-nav nav > menu > li.operate');
            if (!operateLi) return;

            // Idempotency: skip if already injected
            if (operateLi.querySelector('.md3-theme-toggle')) return;

            var toggleLink = document.createElement('a');
            toggleLink.href = 'javascript:void(0)';
            toggleLink.title = '切换深色/浅色模式';
            toggleLink.className = 'md3-theme-toggle';

            if (this.isSidebar) {
                toggleLink.style.cssText = 'cursor:pointer;';
                toggleLink.innerHTML = '<span class="ab-nav-icon">' + this.getThemeIcon() + '</span><span class="ab-nav-label">切换主题</span>';
            } else {
                toggleLink.style.cssText = 'cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;';
                toggleLink.innerHTML = this.getThemeIcon();
            }

            var _applyThemeSetting = function (setting) {
                if (setting === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else if (setting === 'light') {
                    document.documentElement.removeAttribute('data-theme');
                } else {
                    // system: follow OS preference
                    var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (prefersDark) {
                        document.documentElement.setAttribute('data-theme', 'dark');
                    } else {
                        document.documentElement.removeAttribute('data-theme');
                    }
                }
            };

            var _updateToggleIcon = function () {
                if (AdminBeautify.isSidebar) {
                    var iconSpan = toggleLink.querySelector('.ab-nav-icon');
                    if (iconSpan) iconSpan.innerHTML = AdminBeautify.getThemeIcon();
                } else {
                    toggleLink.innerHTML = AdminBeautify.getThemeIcon();
                }
            };

            toggleLink.addEventListener('click', function (e) {
                e.preventDefault();
                var saved = localStorage.getItem('adminBeautifyTheme');
                var next = (saved === 'light') ? 'dark' : (saved === 'dark') ? 'system' : 'light';
                localStorage.setItem('adminBeautifyTheme', next);
                _applyThemeSetting(next);
                _updateToggleIcon();
            });

            operateLi.insertBefore(toggleLink, operateLi.firstChild);

            var savedTheme = localStorage.getItem('adminBeautifyTheme');
            _applyThemeSetting(savedTheme || 'system');
            _updateToggleIcon();

            // 系统模式下监听 OS 主题变化
            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                    var cur = localStorage.getItem('adminBeautifyTheme');
                    if (!cur || cur === 'system') _applyThemeSetting('system');
                });
            }
        },

        /**
         * 移动端 (≤599px) 右上角三点更多菜单
         * ── 将 li.operate 的链接克隆到浮层，点击 more_vert 按钮切换显示
         */
        initMobileMoreMenu: function () {
            // Works in both sidebar and top-nav mode on mobile (<576px)
            var operateLi = document.querySelector('.typecho-head-nav nav > menu > li.operate');
            if (!operateLi) return;

            /* ── button: position:fixed, attached to body ── */
            var btn = document.createElement('button');
            btn.id = 'ab-mobile-more-btn';
            btn.setAttribute('aria-label', '更多操作');
            btn.setAttribute('aria-expanded', 'false');
            btn.setAttribute('type', 'button');
            btn.innerHTML = '<span class="material-icons-round">more_vert</span>';
            document.body.appendChild(btn);

            /* ── panel: position:fixed, attached to body ── */
            var panel = document.createElement('div');
            panel.id = 'ab-mobile-more-panel';
            panel.setAttribute('role', 'menu');

            var links = Array.prototype.slice.call(operateLi.querySelectorAll('a'));
            links.forEach(function (link) {
                var clone = link.cloneNode(true);
                if (clone.classList.contains('md3-theme-toggle')) {
                    var orig = operateLi.querySelector('.md3-theme-toggle');
                    if (orig) {
                        /* ── 实时同步：桌面端切换主题后 clone 立即更新 ── */
                        var _iconObs = new MutationObserver(function () {
                            clone.innerHTML = orig.innerHTML;
                        });
                        _iconObs.observe(orig, { childList: true, subtree: true, characterData: true });
                    }
                    clone.addEventListener('click', function (e) {
                        e.preventDefault();
                        /* delegate to original — _updateToggleIcon fires on orig,
                           MutationObserver propagates change to clone */
                        if (orig) orig.click();
                    });
                }
                panel.appendChild(clone);
            });
            document.body.appendChild(panel);

            /* ── state ── */
            var panelAnim = null;
            var panelIsOpen = false;

            /* ── position button at right of header ── */
            function _positionBtn() {
                var header = document.querySelector('.typecho-head-nav');
                if (!header) return;
                var r = header.getBoundingClientRect();
                btn.style.top   = Math.round(r.top + (r.height - 44) / 2) + 'px';
                btn.style.right = '8px';
            }

            /* ── position panel below button ── */
            function _positionPanel() {
                var r = btn.getBoundingClientRect();
                panel.style.top   = Math.round(r.bottom + 6) + 'px';
                panel.style.right = '8px';
            }

            /* ── MD3 Zoom Transition with WAAPI — supports interruption ── */
            function _triggerAnimation(open) {
                // Commit current animated value to inline style, then cancel
                if (panelAnim) {
                    try { panelAnim.commitStyles(); } catch (e) {}
                    panelAnim.cancel();
                    panelAnim = null;
                }
                // Read current visual state (after commitStyles)
                var cs = window.getComputedStyle(panel);
                var curOpacity = parseFloat(cs.opacity) || 0;
                var curScale = 1;
                var t = cs.transform;
                if (t && t !== 'none') {
                    var m = t.match(/^matrix\(([^,]+)/);
                    if (m) curScale = parseFloat(m[1]);
                }

                if (open) {
                    _positionBtn();
                    _positionPanel();
                    panel.style.pointerEvents = 'auto';
                    panelIsOpen = true;
                    btn.classList.add('ab-more-open');
                    btn.setAttribute('aria-expanded', 'true');
                    panelAnim = panel.animate(
                        [
                            { transform: 'scale(' + curScale + ')', opacity: curOpacity },
                            { transform: 'scale(1)',                  opacity: 1          }
                        ],
                        { duration: 300, easing: 'cubic-bezier(0.05,0.7,0.1,1)', fill: 'forwards' }
                    );
                    panelAnim.onfinish = function () { panelAnim = null; };
                } else {
                    panel.style.pointerEvents = 'none';
                    panelIsOpen = false;
                    btn.classList.remove('ab-more-open');
                    btn.setAttribute('aria-expanded', 'false');
                    panelAnim = panel.animate(
                        [
                            { transform: 'scale(' + curScale + ')', opacity: curOpacity },
                            { transform: 'scale(0.85)',               opacity: 0          }
                        ],
                        { duration: 200, easing: 'cubic-bezier(0.3,0,0.8,0.15)', fill: 'forwards' }
                    );
                    panelAnim.onfinish = function () {
                        // Restore CSS-defined initial state after close completes
                        panel.style.removeProperty('transform');
                        panel.style.removeProperty('opacity');
                        panelAnim = null;
                    };
                }
            }

            function _open()  { _triggerAnimation(true);  }
            function _close() { _triggerAnimation(false); }

            /* ── show/hide button based on viewport width ── */
            function _updateBtnVisibility() {
                var shouldShow = window.innerWidth < 576;
                btn.style.display = shouldShow ? 'inline-flex' : 'none';
                if (shouldShow) _positionBtn();
                else _close();
            }
            _updateBtnVisibility();
            window.addEventListener('resize', _updateBtnVisibility);

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                panelIsOpen ? _close() : _open();
            });

            document.addEventListener('click', function (e) {
                if (panelIsOpen && !panel.contains(e.target) && e.target !== btn) {
                    _close();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && panelIsOpen) _close();
            });

            window.addEventListener('scroll', function () {
                if (panelIsOpen) { _positionBtn(); _positionPanel(); }
            }, { passive: true });
        },

        /**
         * 拦截原生 alert / confirm / prompt，替换为 MD3 弹窗
         */
        interceptNativeDialogs: function () {

            /* ── 保存原生引用（供 API 降级 & 外部插件绕过使用）── */
            var _nativeAlert   = window.alert.bind(window);
            var _nativeConfirm = window.confirm.bind(window);
            var _nativePrompt  = window.prompt.bind(window);
            AdminBeautify.nativeAlert   = _nativeAlert;
            AdminBeautify.nativeConfirm = _nativeConfirm;
            AdminBeautify.nativePrompt  = _nativePrompt;

            /* ── DOM helpers ─────────────────────────────────────── */
            function _ensureDOM() {
                if (document.getElementById('ab-dialog-overlay')) return;
                var ov = document.createElement('div');
                ov.id = 'ab-dialog-overlay';
                var bx = document.createElement('div');
                bx.id = 'ab-dialog-box';
                ov.appendChild(bx);
                document.body.appendChild(ov);
            }

            function _open(opts) {
                _ensureDOM();
                var overlay = document.getElementById('ab-dialog-overlay');
                var box     = document.getElementById('ab-dialog-box');

                var iconMap = { confirm: 'help_outline', alert: 'info', prompt: 'edit_note' };
                var iconName = iconMap[opts.type] || 'info';

                var inputHtml = '';
                if (opts.type === 'prompt') {
                    inputHtml = '<div id="ab-dialog-input-wrap">' +
                        '<input id="ab-dialog-input" type="text" autocomplete="off" value="' +
                        (opts.defaultVal || '').replace(/"/g, '&quot;') + '"></div>';
                }
                var cancelHtml = (opts.type !== 'alert')
                    ? '<button class="ab-dialog-btn ab-dialog-btn-cancel" id="ab-dialog-cancel">取消</button>'
                    : '';

                box.innerHTML =
                    '<div id="ab-dialog-host"><span class="material-icons-round">language</span>' + location.hostname + '</div>' +
                    '<div id="ab-dialog-title"><span class="material-icons-round" style="font-size:22px;vertical-align:-4px;color:var(--md-primary,#6750a4);margin-right:6px">' +
                    iconName + '</span>' + (opts.title || '') + '</div>' +
                    (opts.message ? '<p id="ab-dialog-msg">' + opts.message + '</p>' : '') +
                    inputHtml +
                    '<div id="ab-dialog-actions">' + cancelHtml +
                    '<button class="ab-dialog-btn ab-dialog-btn-confirm" id="ab-dialog-confirm">' +
                    (opts.type === 'alert' ? '好的' : '确定') + '</button></div>';

                overlay.classList.add('ab-dialog-open');

                /* ── 入场 WAAPI Zoom ── */
                (function () {
                    var box = document.getElementById('ab-dialog-box');
                    if (!box || !box.animate) return;
                    var isMobile = window.innerWidth <= 599;
                    var kfFrom = isMobile
                        ? { transform: 'translateY(40px)', opacity: 0 }
                        : { transform: 'scale(0.85)', opacity: 0 };
                    var kfTo = isMobile
                        ? { transform: 'translateY(0)', opacity: 1 }
                        : { transform: 'scale(1)', opacity: 1 };
                    var anim = box.animate([kfFrom, kfTo], {
                        duration: 300,
                        easing: isMobile ? 'cubic-bezier(0.05,0.7,0.1,1)' : 'cubic-bezier(0.05,0.7,0.1,1)',
                        fill: 'forwards'
                    });
                    anim.onfinish = function () {
                        box.style.removeProperty('transform');
                        box.style.removeProperty('opacity');
                    };
                })();

                var inputEl = document.getElementById('ab-dialog-input');
                if (inputEl) { setTimeout(function () { inputEl.focus(); inputEl.select(); }, 50); }

                var closed = false;
                function _close() {
                    if (closed) return;
                    closed = true;
                    document.removeEventListener('keydown', _keyHandler);
                    /* ── 退场 WAAPI Zoom ── */
                    var box = document.getElementById('ab-dialog-box');
                    if (!box || !box.animate) {
                        overlay.classList.remove('ab-dialog-open');
                        return;
                    }
                    var isMobile = window.innerWidth <= 599;
                    var cs = window.getComputedStyle(box);
                    var curScale = 1, curOp = parseFloat(cs.opacity) || 1;
                    var t = cs.transform;
                    if (t && t !== 'none') { var m = t.match(/^matrix\(([^,]+)/); if (m) curScale = parseFloat(m[1]); }
                    var kfTo = isMobile
                        ? { transform: 'translateY(40px)', opacity: 0 }
                        : { transform: 'scale(0.85)', opacity: 0 };
                    var kfFrom = isMobile
                        ? { transform: 'translateY(' + Math.round((1 - curScale) * 0 + (cs.transform === 'none' ? 0 : 0)) + 'px)', opacity: curOp }
                        : { transform: 'scale(' + curScale + ')', opacity: curOp };
                    box.animate([kfFrom, kfTo], {
                        duration: 200,
                        easing: 'cubic-bezier(0.3,0,0.8,0.15)',
                        fill: 'forwards'
                    }).onfinish = function () {
                        overlay.classList.remove('ab-dialog-open');
                    };
                }

                document.getElementById('ab-dialog-confirm').addEventListener('click', function () {
                    _close();
                    if (opts.onConfirm) opts.onConfirm(inputEl ? inputEl.value : true);
                });
                var cancelBtn = document.getElementById('ab-dialog-cancel');
                if (cancelBtn) {
                    cancelBtn.addEventListener('click', function () { _close(); if (opts.onCancel) opts.onCancel(); });
                }
                overlay.addEventListener('click', function handler(e) {
                    if (e.target === overlay) { overlay.removeEventListener('click', handler); _close(); if (opts.onCancel) opts.onCancel(); }
                });
                function _keyHandler(e) {
                    if (e.key === 'Enter')  { e.preventDefault(); document.getElementById('ab-dialog-confirm').click(); }
                    if (e.key === 'Escape') { e.preventDefault(); if (cancelBtn) cancelBtn.click(); else document.getElementById('ab-dialog-confirm').click(); }
                }
                document.addEventListener('keydown', _keyHandler);
            }

            function _parseMsg(message) {
                var parts = (''+message).split('\n');
                return { title: parts[0], body: parts.slice(1).join('\n') };
            }

            /* ── Intercept clicks via capture ─────────────────────
             * Handles two Typecho patterns:
             * 1. onclick="return confirm('...')"
             * 2. <a lang="确认消息" href="action-url"> (jQuery batch-action buttons)
             *    typecho.js: t && !confirm(t) || form.submit()
             *    Our window.confirm returns false → expression short-circuits → form never submits.
             *    Fix: intercept in capture phase before jQuery fires, submit directly on confirm.
             */
            document.addEventListener('click', function (e) {
                /* 旁路模式：由 onConfirm 重放的点击，跳过所有拦截，同时允许 window.confirm 返回 true */
                if (window.__abConfirmBypass) return;
                /* 记录当前点击目标，供 window.confirm 局部使用 */
                window.__abLastClickTarget = e.target;
                var el = e.target;
                for (var i = 0; i < 5; i++) {
                    if (!el || el === document) break;

                    // Pattern 2: Typecho [lang] attribute batch-action buttons
                    var langMsg = el.getAttribute && el.getAttribute('lang');
                    if (langMsg) {
                        var langTarget = el;
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        var lp = _parseMsg(langMsg);
                        _open({
                            type: 'confirm', title: lp.title, message: lp.body,
                            onConfirm: function () {
                                var href = langTarget.getAttribute('href');
                                // Typecho admin has two sibling forms:
                                //   1. <form method="get" class="typecho-list-operate"> — contains the <a lang> buttons
                                //   2. <form method="post" class="operate-form"> — contains the checkboxes (item IDs)
                                // We must submit the POST operate-form, not the filter form.
                                var form = document.querySelector('form.operate-form') ||
                                           document.querySelector('form[method="post"]') ||
                                           (langTarget.closest ? langTarget.closest('form') : null);
                                if (form && href && href !== '#' && href.indexOf('javascript:') !== 0) {
                                    form.setAttribute('action', href);
                                    form.submit();
                                } else if (href && href !== '#' && href.indexOf('javascript:') !== 0) {
                                    window.location.href = href;
                                }
                            }
                        });
                        return;
                    }

                    // Pattern 1: onclick="return confirm(...)"
                    var oc = el.getAttribute && el.getAttribute('onclick');
                    if (oc && oc.indexOf('confirm(') !== -1) {
                        // Extract the confirm message from onclick attr
                        var m = oc.match(/confirm\s*\(\s*['"]([^'"]*)['"]\s*\)/);
                        var msg = m ? m[1] : '确认执行此操作？';
                        var p = _parseMsg(msg);
                        var target = el; // capture
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        _open({
                            type: 'confirm', title: p.title, message: p.body,
                            onConfirm: function () {
                                // Strip the confirm() from onclick and re-execute, or follow href
                                var cleanOnclick = (target.getAttribute('onclick') || '')
                                    .replace(/return\s+confirm\s*\([^)]*\)\s*;?/g, '')
                                    .replace(/confirm\s*\([^)]*\)\s*&&\s*/g, '')
                                    .trim();
                                if (cleanOnclick) {
                                    try { (new Function(cleanOnclick)).call(target); } catch (err) {}
                                }
                                var href = target.getAttribute('href');
                                if (href && href !== '#' && href.indexOf('javascript:') !== 0) {
                                    if (window.AdminBeautify && AdminBeautify._ajaxNavActive && AdminBeautify._isAjaxable(target)) {
                                        AdminBeautify._navigateTo(href);
                                    } else {
                                        window.location.href = href;
                                    }
                                } else if (target.tagName === 'BUTTON' || target.type === 'submit') {
                                    var form = target.closest ? target.closest('form') : null;
                                    if (form) form.submit();
                                }
                            }
                        });
                        return;
                    }

                    // Pattern 3: <form onsubmit="return confirm(...)"> — submit button clicked
                    if (el.type === 'submit') {
                        var _sf3 = el.closest ? el.closest('form') : null;
                        if (!_sf3) { var _wp3 = el; while (_wp3 && _wp3.tagName !== 'FORM') _wp3 = _wp3.parentNode; _sf3 = _wp3 || null; }
                        if (_sf3) {
                            var _os3 = _sf3.getAttribute('onsubmit');
                            if (_os3 && _os3.indexOf('confirm(') !== -1) {
                                var _m3 = _os3.match(/confirm\s*\(\s*['"]([^'"]*)['"]\s*\)/);
                                var _pp3 = _parseMsg(_m3 ? _m3[1] : '确认提交？');
                                var _sfRef3 = _sf3;
                                e.preventDefault();
                                e.stopImmediatePropagation();
                                _open({
                                    type: 'confirm', title: _pp3.title, message: _pp3.body,
                                    onConfirm: function () {
                                        var _clean = (_sfRef3.getAttribute('onsubmit') || '')
                                            .replace(/return\s+confirm\s*\([^)]*\)\s*;?/g, '')
                                            .replace(/confirm\s*\([^)]*\)\s*&&\s*/g, '')
                                            .trim();
                                        if (_clean) { _sfRef3.setAttribute('onsubmit', _clean); } else { _sfRef3.removeAttribute('onsubmit'); _sfRef3.onsubmit = null; }
                                        _sfRef3.submit();
                                    }
                                });
                                return;
                            }
                        }
                    }

                    el = el.parentElement;
                }
            }, true); // capture phase

            /* ── Override window.alert ───────────────────────────── */
            window.alert = function (message) {
                var p = _parseMsg(message);
                _open({ type: 'alert', title: p.title, message: p.body,
                    onConfirm: function () {
                        if (window._abPendingAlert) { var fn = window._abPendingAlert; window._abPendingAlert = null; fn(); }
                    }
                });
            };

            /* ── Override window.confirm (fallback for dynamic calls) */
            window.confirm = function (message) {
                /* 旁路模式：由 onConfirm 重放点击时返回 true，让原始处理器正常运行 */
                if (window.__abConfirmBypass) { window.__abConfirmBypass = false; return true; }
                var p = _parseMsg(message);
                var _lastTarget = window.__abLastClickTarget || null;
                _open({
                    type: 'confirm', title: p.title, message: p.body,
                    onConfirm: function () {
                        if (window._abPendingConfirm) { var fn = window._abPendingConfirm; window._abPendingConfirm = null; fn(true); }
                        /* 重放点击：以旁路模式重新触发原始元素点击，confirm 将返回 true */
                        if (_lastTarget && !window._abPendingConfirm) {
                            window.__abConfirmBypass = true;
                            try { _lastTarget.click(); } catch(err) {}
                            window.__abConfirmBypass = false;
                        }
                    },
                    onCancel: function () {
                        if (window._abPendingConfirm) { var fn = window._abPendingConfirm; window._abPendingConfirm = null; fn(false); }
                    }
                });
                return false;
            };

            /* ── Override window.prompt ──────────────────────────── */
            window.prompt = function (message, defaultVal) {
                var p = _parseMsg(message);
                _open({
                    type: 'prompt', title: p.title, message: p.body, defaultVal: defaultVal || '',
                    onConfirm: function (val) {
                        if (window._abPendingPrompt) { var fn = window._abPendingPrompt; window._abPendingPrompt = null; fn(val); }
                    },
                    onCancel: function () {
                        if (window._abPendingPrompt) { var fn = window._abPendingPrompt; window._abPendingPrompt = null; fn(null); }
                    }
                });
                return null;
            };

            /* ── Promise 公开 API ── AdminBeautify.alert / confirm / prompt ── */
            AdminBeautify.alert = function (message) {
                return new Promise(function (resolve) {
                    var p = _parseMsg(message);
                    _open({ type: 'alert', title: p.title, message: p.body,
                        onConfirm: function () { resolve(); }
                    });
                });
            };
            AdminBeautify.confirm = function (message) {
                return new Promise(function (resolve) {
                    var p = _parseMsg(message);
                    _open({ type: 'confirm', title: p.title, message: p.body,
                        onConfirm: function () { resolve(true); },
                        onCancel:  function () { resolve(false); }
                    });
                });
            };
            AdminBeautify.prompt = function (message, defaultVal) {
                return new Promise(function (resolve) {
                    var p = _parseMsg(message);
                    _open({ type: 'prompt', title: p.title, message: p.body, defaultVal: defaultVal || '',
                        onConfirm: function (val) { resolve(val); },
                        onCancel:  function ()    { resolve(null); }
                    });
                });
            };
        },

        /**
         * 根据当前主题偏好返回图标 HTML
         */
        getThemeIcon: function () {
            var saved = localStorage.getItem('adminBeautifyTheme');
            if (saved === 'dark')  return '<span class="material-icons-round">dark_mode</span>';
            if (saved === 'light') return '<span class="material-icons-round">light_mode</span>';
            return '<span class="material-icons-round">contrast</span>';
        },

        /**
         * 增强滚动行为
         */
        enhanceScrollBehavior: function () {
            document.documentElement.style.scrollBehavior = 'smooth';

            // 左侧边栏模式下不需要导航栏滚动阴影
            if (this.isSidebar) return;

            var nav = document.querySelector('.typecho-head-nav');
            if (!nav) return;

            window.addEventListener('scroll', function () {
                var currentScroll = window.pageYOffset;
                if (currentScroll > 64) {
                    nav.style.boxShadow = '0 4px 8px 3px rgba(0,0,0,.1), 0 1px 3px rgba(0,0,0,.12)';
                } else {
                    nav.style.boxShadow = '0 2px 6px 2px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.1)';
                }
            }, { passive: true });
        },

        /**
         * 增强 popup 消息 — MD3 Snackbar 风格
         * 拦截 Typecho 原生 jQuery 动画，改用 MD3 动画
         */
        enhancePopupMessages: function () {
            var self = this;

            // Handle any popups already in the DOM (created by common-js.php before us)
            var existing = document.querySelectorAll('.popup');
            for (var k = 0; k < existing.length; k++) {
                self._showSnackbar(existing[k]);
            }

            // Use MutationObserver to catch dynamically inserted .popup elements
            var handleMutation = function (mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    var added = mutations[i].addedNodes;
                    for (var j = 0; j < added.length; j++) {
                        var node = added[j];
                        if (node.nodeType !== 1) continue;
                        if (!node.classList || !node.classList.contains('popup')) continue;
                        self._showSnackbar(node);
                    }
                }
            };

            var observer = new MutationObserver(handleMutation);
            observer.observe(document.body, { childList: true });

            // Also observe head-nav's parent for insertAfter
            var headNav = document.querySelector('.typecho-head-nav');
            if (headNav && headNav.parentNode && headNav.parentNode !== document.body) {
                observer.observe(headNav.parentNode, { childList: true });
            }
        },

        /**
         * 以 MD3 Snackbar 动画显示 popup
         */
        _showSnackbar: function (el, duration) {
            // Prevent double-processing
            if (el.getAttribute('data-ab-snackbar')) return;
            el.setAttribute('data-ab-snackbar', '1');

            // Stop any jQuery animation that may be running
            if (window.jQuery && jQuery.fn.stop) {
                jQuery(el).stop(true, true).clearQueue().off();
            }

            // Reset ALL jQuery inline styles, force our CSS to take over
            el.removeAttribute('style');
            el.style.display = 'block';
            el.style.animation = 'md3-snackbar-in 0.35s cubic-bezier(0.2, 0, 0, 1) forwards';
            el.style.cursor = 'pointer';

            // Auto-dismiss after specified duration (default 5000ms; 0 = no auto-dismiss)
            var dismissed = false;
            var dismiss = function () {
                if (dismissed) return;
                dismissed = true;
                AdminBeautify._dismissSnackbar(el);
            };

            var autoDismissMs = (typeof duration === 'number') ? duration : 5000;
            var dismissTimer = (autoDismissMs > 0) ? setTimeout(dismiss, autoDismissMs) : null;

            // Click to dismiss immediately
            el.addEventListener('click', function () {
                if (dismissTimer) clearTimeout(dismissTimer);
                dismiss();
            });
        },

        /**
         * MD3 Snackbar 淡出消失
         */
        _dismissSnackbar: function (el) {
            el.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateX(-50%) translateY(-8px) scale(0.97)';
            setTimeout(function () {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 250);
        },

        /**
         * 显示横幅通知（Snackbar）— 公开 API，供外部插件调用
         *
         * @param {string} message    通知文本
         * @param {string} [type]     通知类型: 'info'（默认）| 'success' | 'warn' | 'error'
         * @param {number} [duration] 自动消失毫秒数（默认 5000；传 0 则只能点击关闭）
         *
         * 用法：AdminBeautify.showNotice('保存成功', 'success');
         *       AdminBeautify.showNotice('发生错误', 'error', 8000);
         *       AdminBeautify.showNotice('正在处理…', 'info', 0);
         */
        showNotice: function (message, type, duration) {
            var el = document.createElement('div');
            el.className = 'popup' + (type ? ' ab-notice-' + type : '');
            // 使用 ul>li 结构以匹配 .popup ul 的 CSS（含 padding、icon 等）
            var safe = String(message).replace(/[&<>"']/g, function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];});
            el.innerHTML = '<ul><li>' + safe + '</li></ul>';
            document.body.appendChild(el);
            // 直接调用；MutationObserver 也会触发，但 data-ab-snackbar 幂等守卫防止重复处理
            this._showSnackbar(el, typeof duration === 'number' ? duration : 5000);
        },

        // ==================================================
        // Sidebar enhancement methods
        // ==================================================

        /**
         * 增强左侧边栏 (MD3 Navigation Drawer)
         */
        enhanceSidebar: function () {
            this.sidebarInjectHeader();
            // Icons already injected by addNavIcons() in init()
            this.sidebarMarkExpandable();
            this.sidebarBindToggle();
            this.sidebarAddCollapseBtn();
            // Operate icons already injected by addNavIcons() in init()
            this.sidebarRestoreState();
        },

        /**
         * 注入侧边栏头部品牌区域（使用用户头像）
         */
        sidebarInjectHeader: function () {
            var nav = document.querySelector('.typecho-head-nav nav');
            if (!nav) return;

            // Idempotency: skip if already injected
            if (nav.querySelector('.ab-sidebar-header')) return;

            // 获取站点标题 (从 <title> 中提取)
            // 当某些插件的 addPanel subTitle 为空时，PHP 输出 " - BlogName - Powered by Typecho"
            // 浏览器会修剪 document.title 的前导空格，使其变为 "- BlogName - Powered by Typecho"
            // 此时按 " - " 分割只能得到 ["- BlogName", "Powered by Typecho"]，提取结果带有前导 "-"
            // 修复方式：先去除末尾固定后缀，再去除可能的前导 "- "，最后取最后一个 " - " 后面的部分
            var pageTitle = (document.title || '').trim();
            var siteTitle = 'Typecho';
            // 去掉末尾的 " - Powered by Typecho"（固定后缀）
            var stripped = pageTitle.replace(/\s*-\s*Powered by Typecho\s*$/i, '').trim();
            // 去掉前导 "- "（当 $menu->title/subTitle 为空且浏览器修剪标题后出现的多余 "- "）
            stripped = stripped.replace(/^-\s+/, '');
            // 取末尾最后一个 " - " 之后的部分即为博客名（若无则整体就是博客名）
            var lastDashIdx = stripped.lastIndexOf(' - ');
            if (lastDashIdx !== -1) {
                siteTitle = stripped.slice(lastDashIdx + 3).trim();
            } else if (stripped) {
                siteTitle = stripped.trim();
            }
            // 兜底：若仍为空，保持 'Typecho'
            if (!siteTitle) { siteTitle = 'Typecho'; }
            var firstChar = siteTitle.charAt(0).toUpperCase();

            // 获取用户信息（由 PHP renderFooter 注入）
            var userInfo = window.__AB_USER__ || {};
            var avatarUrl = userInfo.avatar || '';
            var userName = userInfo.name || '';

            // Logo: 优先使用头像，fallback 到首字母
            var logoHtml;
            if (avatarUrl) {
                logoHtml = '<img class="ab-sidebar-avatar" src="' + this._escHtml(avatarUrl) + '" alt="" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">' +
                           '<div class="ab-sidebar-logo" style="display:none">' + firstChar + '</div>';
            } else {
                logoHtml = '<div class="ab-sidebar-logo">' + firstChar + '</div>';
            }

            // 副标题：显示用户名
            var subtitle = userName ? this._escHtml(userName) : '管理后台';

            var header = document.createElement('div');
            header.className = 'ab-sidebar-header';
            header.innerHTML =
                '<div class="ab-sidebar-avatar-wrap">' + logoHtml + '</div>' +
                '<div>' +
                    '<div class="ab-sidebar-title">' + this._escHtml(siteTitle) + '</div>' +
                    '<div class="ab-sidebar-subtitle">' + subtitle + '</div>' +
                '</div>';

            nav.insertBefore(header, nav.firstChild);
        },

        /**
         * 为主菜单项添加图标
         */
        sidebarAddMainIcons: function () {
            var items = document.querySelectorAll('.typecho-head-nav nav > menu > li:not(.operate) > a');
            var iconMap = {
                'index.php': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',
                'write-post.php': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>',
                'manage-posts.php': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>',
                'options-general.php': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>',
                'themes.php': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg>',
                'plugins.php': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
                'default': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle></svg>'
            };

            items.forEach(function (link) {
                if (link.querySelector('.ab-nav-icon')) return;

                var href = link.getAttribute('href');
                var iconHtml = iconMap['default'];
                
                // Add title for tooltip
                if (!link.getAttribute('title')) {
                    link.setAttribute('title', link.innerText.trim());
                }

                if (href) {
                     // Plugin pages (extending.php) — always use plugin icon, check FIRST
                     if (href.indexOf('extending.php') !== -1) iconHtml = iconMap['plugins.php'];
                     else if (href.indexOf('index.php') !== -1 && href.indexOf('options') === -1 && href.indexOf('manage') === -1) iconHtml = iconMap['index.php'];
                     else if (href.indexOf('write-') !== -1) iconHtml = iconMap['write-post.php'];
                     else if (href.indexOf('manage-') !== -1) iconHtml = iconMap['manage-posts.php'];
                     else if (href.indexOf('options-') !== -1) iconHtml = iconMap['options-general.php'];
                     else if (href.indexOf('themes.php') !== -1 || href.indexOf('theme-') !== -1) iconHtml = iconMap['themes.php'];
                     else if (href.indexOf('plugins.php') !== -1) iconHtml = iconMap['plugins.php'];
                }

                var iconSpan = document.createElement('span');
                iconSpan.className = 'ab-nav-icon';
                iconSpan.innerHTML = iconHtml;
                link.insertBefore(iconSpan, link.firstChild);
            });
        },

        /**
         * 标记有子菜单的项目
         */
        sidebarMarkExpandable: function () {
            // 仅在侧边栏模式或移动端抽屉模式下使用 JS max-height 动画；
            // 顶栏桌面模式下子菜单通过 CSS :hover display 显示，无需 max-height
            var isMobileDrawer = window.innerWidth <= 575;
            var usesMaxHeight = AdminBeautify.isSidebar || isMobileDrawer;

            var items = document.querySelectorAll('.typecho-head-nav nav > menu > li:not(.operate)');
            items.forEach(function (li) {
                var subMenu = li.querySelector('menu');
                if (subMenu && subMenu.children.length > 0) {
                    li.classList.add('ab-has-children');
                    // 如果是当前激活项，自动展开
                    if (li.classList.contains('focus')) {
                        li.classList.add('ab-expanded');
                        // 仅在需要 max-height 动画的模式（侧边栏/移动端）下设置内联样式；
                        // 顶栏桌面模式不设置，避免子菜单 scrollHeight=0 时意外将 max-height 置零
                        if (usesMaxHeight) {
                            subMenu.style.maxHeight = subMenu.scrollHeight + 'px';
                        }
                    }
                    // JS fallback for :has(.focus) — add class when child is active
                    var childFocus = subMenu.querySelector('li.focus');
                    if (childFocus && li.classList.contains('focus')) {
                        li.classList.add('ab-child-focus');
                    }
                }
            });
        },

        /**
         * 绑定父级菜单点击展开/折叠子菜单
         */
        sidebarBindToggle: function () {
            var items = document.querySelectorAll('.typecho-head-nav nav > menu > li.ab-has-children > a');
            items.forEach(function (link) {
                link.addEventListener('click', function (e) {
                    var isMobileDrawer = window.innerWidth <= 575;
                    // 桌面端顶部导航模式不拦截点击，让链接正常导航
                    if (!AdminBeautify.isSidebar && !isMobileDrawer) return;
                    // 如果侧边栏处于折叠状态，不拦截，让它正常导航
                    if (document.documentElement.hasAttribute('data-nav-collapsed')) return;

                    e.preventDefault();
                    e.stopPropagation();
                    var li = this.parentElement;
                    var wasExpanded = li.classList.contains('ab-expanded');

                    // 手风琴模式：关闭其他展开的项，并重置其 max-height（不保留 focus 项，用户主动点击时应无条件折叠）
                    var siblings = document.querySelectorAll('.typecho-head-nav nav > menu > li.ab-expanded');
                    siblings.forEach(function (sib) {
                        if (sib !== li) {
                            sib.classList.remove('ab-expanded');
                            var sibSub = sib.querySelector(':scope > menu');
                            if (sibSub) sibSub.style.maxHeight = '0';
                        }
                    });

                    var sub = li.querySelector(':scope > menu');
                    if (wasExpanded) {
                        li.classList.remove('ab-expanded');
                        if (sub) sub.style.maxHeight = '0';
                    } else {
                        li.classList.add('ab-expanded');
                        // 用实际 scrollHeight 设置精确高度，动画流畅且不会截断内容
                        if (sub) sub.style.maxHeight = sub.scrollHeight + 'px';
                    }
                });
            });
        },

        /**
         * 添加侧边栏折叠/展开按钮
         */
        sidebarAddCollapseBtn: function () {
            var headNav = document.querySelector('.typecho-head-nav');
            if (!headNav) return;

            // Idempotency: skip if already injected
            if (headNav.querySelector('.ab-sidebar-collapse')) return;

            var btn = document.createElement('div');
            btn.className = 'ab-sidebar-collapse';
            btn.title = '折叠/展开侧边栏';
            if (document.documentElement.hasAttribute('data-nav-collapsed')) {
                btn.innerHTML = '<span class="material-icons-round">chevron_right</span>';
            } else {
                btn.innerHTML = '<span class="material-icons-round">chevron_left</span>';
            }

            btn.addEventListener('click', function () {
                var html = document.documentElement;
                if (html.hasAttribute('data-nav-collapsed')) {
                    html.removeAttribute('data-nav-collapsed');
                    localStorage.setItem('adminBeautifySidebarCollapsed', '0');
                    btn.innerHTML = '<span class="material-icons-round">chevron_left</span>';
                    // 展开 sidebar 时重新设置活跃子菜单的 max-height：
                    // 折叠状态下 scrollHeight 为 0，导致内联 style.maxHeight 被设为 0，展开后子菜单不显示
                    document.querySelectorAll('.typecho-head-nav nav > menu > li.ab-has-children').forEach(function (li) {
                        var sub = li.querySelector(':scope > menu');
                        if (!sub) return;
                        var isActive = li.classList.contains('focus') || li.classList.contains('ab-child-focus');
                        if (isActive) {
                            li.classList.add('ab-expanded');
                            sub.style.maxHeight = sub.scrollHeight + 'px';
                        } else if (li.classList.contains('ab-expanded')) {
                            sub.style.maxHeight = sub.scrollHeight + 'px';
                        }
                    });
                } else {
                    html.setAttribute('data-nav-collapsed', '');
                    localStorage.setItem('adminBeautifySidebarCollapsed', '1');
                    btn.innerHTML = '<span class="material-icons-round">chevron_right</span>';
                }
            });

            headNav.appendChild(btn);
        },

        /**
         * 为 operate 区域的链接添加图标
         */
        sidebarAddOperateIcons: function () {
            var operateLi = document.querySelector('.typecho-head-nav nav > menu > li.operate');
            if (!operateLi) return;

            var iconMap = {
                'author': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
                'exit': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>',
                'site': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>'
            };

            var links = operateLi.querySelectorAll('a:not(.md3-theme-toggle)');
            links.forEach(function (link) {
                var type = 'site'; // default
                if (link.classList.contains('author')) type = 'author';
                else if (link.classList.contains('exit')) type = 'exit';

                if (iconMap[type]) {
                    var iconSpan = document.createElement('span');
                    iconSpan.className = 'ab-nav-icon';
                    iconSpan.innerHTML = iconMap[type];
                    link.insertBefore(iconSpan, link.firstChild);
                }
            });
        },

        /**
         * 恢复侧边栏折叠状态
         */
        sidebarRestoreState: function () {
            var collapsed = localStorage.getItem('adminBeautifySidebarCollapsed');
            if (collapsed === '1') {
                document.documentElement.setAttribute('data-nav-collapsed', '');
            }
        },

        /**
         * MD3 Dashboard 仪表盘增强
         */
        enhanceDashboard: function () {
            var dashboard = document.querySelector('.typecho-dashboard');
            if (!dashboard || dashboard.classList.contains('ab-dashboard-enhanced')) return;

            // ---- 0. 在页面标题右侧添加主题设置按钮 ----
            var pageTitle = dashboard.querySelector('.typecho-page-title');
            var cfg = window.__AB_CONFIG__ || {};
            var themeButtonMode = cfg.dashboardThemeButtonShow || 'standalone';
            if (themeButtonMode === '1') themeButtonMode = 'standalone';
            if (themeButtonMode === '0') themeButtonMode = 'hide';

            if (pageTitle && themeButtonMode !== 'standalone') {
                var oldBtn = pageTitle.querySelector('.ab-dashboard-settings-btn');
                if (oldBtn) oldBtn.remove();
            }

            if (themeButtonMode === 'standalone' && pageTitle && !pageTitle.querySelector('.ab-dashboard-settings-btn')) {
                var settingsBtn = document.createElement('a');
                settingsBtn.className = 'ab-dashboard-settings-btn';
                settingsBtn.href = 'options-plugin.php?config=AdminBeautify';
                settingsBtn.title = '主题设置';
                settingsBtn.innerHTML =
                    '<span class="material-icons-round">settings</span>' +
                    '<span>主题设置</span>';
                pageTitle.appendChild(settingsBtn);
            }

            // ---- 1. 改造欢迎统计区为 stat cards ----
            var board = dashboard.querySelector('.welcome-board');
            if (board) {
                var boardP = board.querySelector('p');
                if (boardP) {
                    var ems = boardP.querySelectorAll('em');
                    // 解析三个统计数字
                    var postsNum = ems[0] ? ems[0].textContent : '0';
                    var commentsNum = ems[1] ? ems[1].textContent : '0';
                    var catsNum = ems[2] ? ems[2].textContent : '0';

                    // 三个 stat card 的 SVG 图标
                    var statIcons = {
                        posts: '<span class="material-icons-round">article</span>',
                        comments: '<span class="material-icons-round">comment</span>',
                        cats: '<span class="material-icons-round">category</span>'
                    };

                    var statsGrid = document.createElement('div');
                    statsGrid.className = 'ab-stats-grid';

                    var statsData = [
                        { key: 'posts',    icon: statIcons.posts,    num: postsNum,    label: '文章', href: 'manage-posts.php' },
                        { key: 'comments', icon: statIcons.comments, num: commentsNum, label: '评论', href: 'manage-comments.php' },
                        { key: 'cats',     icon: statIcons.cats,     num: catsNum,     label: '分类', href: 'manage-categories.php' }
                    ];

                    for (var i = 0; i < statsData.length; i++) {
                        var card = document.createElement('a');
                        card.className = 'ab-stat-card';
                        card.href = statsData[i].href;
                        card.dataset.statKey = statsData[i].key;
                        card.innerHTML =
                            '<div class="ab-stat-icon">' + statsData[i].icon + '</div>' +
                            '<div class="ab-stat-body">' +
                                '<span class="ab-stat-num">' + statsData[i].num + '</span>' +
                                '<span class="ab-stat-label">' + statsData[i].label + '</span>' +
                                '<span class="ab-stat-trend ab-stat-trend-loading" aria-hidden="true"></span>' +
                            '</div>';
                        statsGrid.appendChild(card);
                    }

                    // 隐藏原始 p 标签；统计卡片追加到 board 末尾（在快捷按钮之后）
                    boardP.style.display = 'none';

                    // ---- 异步拉取趋势数据（复用插件统一 AJAX 工具） ----
                    (function (grid) {
                        // 超时兜底：3 秒内未完成则隐藏 loading 骨架
                        var fallbackTimer = setTimeout(function () {
                            var loadings = grid.querySelectorAll('.ab-stat-trend-loading');
                            for (var ti = 0; ti < loadings.length; ti++) {
                                loadings[ti].style.display = 'none';
                            }
                        }, 3000);

                        AdminBeautify.ajax('stats', null, {
                            method: 'GET',
                            abortOnNavigate: true,
                            onSuccess: function (resp) {
                                clearTimeout(fallbackTimer);
                                var data = resp.data;
                                if (!data) return;

                                // 每个 key 对应的单位
                                var unitMap = { posts: '篇', comments: '条', cats: '个' };
                                var keyMap  = { posts: data.posts, comments: data.comments, cats: data.cats };

                                var cards = grid.querySelectorAll('.ab-stat-card');
                                for (var ci = 0; ci < cards.length; ci++) {
                                    var c = cards[ci];
                                    var key  = c.dataset.statKey;
                                    var stat = keyMap[key];
                                    var trendEl = c.querySelector('.ab-stat-trend');
                                    if (!trendEl || !stat) continue;

                                    trendEl.classList.remove('ab-stat-trend-loading');

                                    var thisWeek  = stat.thisWeek;   // 本7天新增数
                                    var lastWeek  = stat.lastWeek;   // 前7天新增数（后端补充）
                                    var unit      = unitMap[key] || '';

                                    if (thisWeek === null || thisWeek === undefined) {
                                        // 分类等无时间字段，不显示趋势
                                        trendEl.className = 'ab-stat-trend ab-stat-trend-neutral';
                                        trendEl.textContent = '共 ' + stat.total + ' ' + unit;
                                    } else if (thisWeek === 0) {
                                        trendEl.className = 'ab-stat-trend ab-stat-trend-flat';
                                        trendEl.textContent = '本周暂无新增';
                                    } else {
                                        // 本周新增 N 篇/条
                                        var diff = (lastWeek !== null && lastWeek !== undefined)
                                            ? (thisWeek - lastWeek)
                                            : null;

                                        var baseText = '本周新增 ' + thisWeek + ' ' + unit;

                                        if (diff === null) {
                                            trendEl.className = 'ab-stat-trend ab-stat-trend-neutral';
                                            trendEl.textContent = baseText;
                                        } else if (diff > 0) {
                                            trendEl.className = 'ab-stat-trend ab-stat-trend-up';
                                            trendEl.textContent = baseText;
                                        } else if (diff < 0) {
                                            trendEl.className = 'ab-stat-trend ab-stat-trend-down';
                                            trendEl.textContent = baseText;
                                        } else {
                                            trendEl.className = 'ab-stat-trend ab-stat-trend-flat';
                                            trendEl.textContent = baseText;
                                        }
                                    }
                                }
                            },
                            onError: function () {
                                clearTimeout(fallbackTimer);
                                var loadings = grid.querySelectorAll('.ab-stat-trend-loading');
                                for (var ti = 0; ti < loadings.length; ti++) {
                                    loadings[ti].style.display = 'none';
                                }
                            }
                        });
                    })(statsGrid);
                }

                // ---- 2. 改造快速链接为 MD3 tonal buttons（先渲染，再追加统计卡片） ----
                var startLink = board.querySelector('#start-link');
                if (startLink) {
                    cfg = window.__AB_CONFIG__ || {};
                    var quickShow = cfg.dashboardQuickShow !== '0'; // 默认显示
                    var quickHint = cfg.dashboardQuickHint !== '0'; // 默认显示
                    var customBtns = Array.isArray(cfg.dashboardCustomButtons) ? cfg.dashboardCustomButtons : [];

                    startLink.className = 'ab-quick-actions';
                    // 大/小样式
                    if (cfg.dashboardQuickStyle === 'large') {
                        startLink.classList.add('ab-quick-large');
                    }

                    // 先处理原有 li 条目（无论隐藏与否都注入图标，但隐藏时给每个 li 打 display:none）
                    var quickIcons = {
                        'write-post':      '<span class="material-icons-round">edit</span>',
                        'manage-comments': '<span class="material-icons-round">comments_disabled</span>',
                        'themes':          '<span class="material-icons-round">palette</span>',
                        'plugins':         '<span class="material-icons-round">extension</span>',
                        'options-general': '<span class="material-icons-round">home</span>'
                    };

                    var origItems = startLink.querySelectorAll('li');
                    for (var j = 0; j < origItems.length; j++) {
                        var li = origItems[j];

                        // 隐藏原有按钮时，直接隐藏每个 li（不操作容器，避免被自定义按钮逻辑覆盖）
                        if (!quickShow) {
                            li.style.setProperty('display', 'none', 'important');
                            continue;
                        }

                        var a = li.querySelector('a');
                        if (!a) continue;

                        var href = a.getAttribute('href') || '';
                        var iconKey = '';
                        if (href.indexOf('write-post') !== -1)           iconKey = 'write-post';
                        else if (href.indexOf('manage-comments') !== -1) iconKey = 'manage-comments';
                        else if (href.indexOf('themes') !== -1)          iconKey = 'themes';
                        else if (href.indexOf('plugins') !== -1)         iconKey = 'plugins';
                        else if (href.indexOf('options-general') !== -1) iconKey = 'options-general';

                        if (iconKey && quickIcons[iconKey]) {
                            var iconEl = document.createElement('span');
                            iconEl.className = 'ab-quick-icon';
                            iconEl.innerHTML = quickIcons[iconKey];
                            a.insertBefore(iconEl, a.firstChild);
                        }

                        // 移动 balloon 到 a 内部
                        var balloon = li.querySelector('.balloon');
                        if (balloon) {
                            a.appendChild(balloon);
                        }
                    }

                    // 追加自定义按钮
                    if (customBtns.length > 0) {
                        for (var cb = 0; cb < customBtns.length; cb++) {
                            var btn = customBtns[cb];
                            if (!btn.label || !btn.href) continue;
                            // 外链必须以 http:// 或 https:// 开头，否则跳过
                            var isExternal = /^https?:\/\//i.test(btn.href);
                            var isInternal = !isExternal;
                            if (!isInternal && !isExternal) continue;
                            var customLi = document.createElement('li');
                            var btnA = document.createElement('a');
                            btnA.href = btn.href;
                            if (isExternal) {
                                btnA.target = '_blank';
                                btnA.rel = 'noopener noreferrer';
                            }
                            if (btn.highlight) {
                                btnA.classList.add('ab-quick-highlight');
                            }
                            var btnIcon = document.createElement('span');
                            btnIcon.className = 'ab-quick-icon';
                            btnIcon.innerHTML = '<span class="material-icons-round">' + AdminBeautify._escHtml(btn.icon || 'link') + '</span>';
                            btnA.appendChild(btnIcon);
                            btnA.appendChild(document.createTextNode(btn.label));
                            customLi.appendChild(btnA);
                            startLink.appendChild(customLi);
                        }
                    }

                    // 主题设置入口：合并到概要页快捷操作
                    var mergedThemeBtnCount = 0;
                    if (themeButtonMode === 'merge') {
                        var mergedLi = document.createElement('li');
                        var mergedA = document.createElement('a');
                        mergedA.href = 'options-plugin.php?config=AdminBeautify';
                        mergedA.className = 'ab-quick-highlight';
                        var mergedIcon = document.createElement('span');
                        mergedIcon.className = 'ab-quick-icon';
                        mergedIcon.innerHTML = '<span class="material-icons-round">settings</span>';
                        mergedA.appendChild(mergedIcon);
                        mergedA.appendChild(document.createTextNode('主题设置'));
                        mergedLi.appendChild(mergedA);
                        startLink.appendChild(mergedLi);
                        mergedThemeBtnCount = 1;
                    }

                    if (quickHint && quickShow){
                        var customLi = document.createElement('li');
                        var btnA = document.createElement('a');
                        btnA.href = 'options-plugin.php?config=AdminBeautify&to=dashboardCustomButtons';
                        btnA.id = 'ab-quick-icon-add';
                        var btnIcon = document.createElement('span');
                        btnIcon.className = 'ab-quick-icon';
                        btnIcon.innerHTML = '<span class="material-icons-round">add</span>';
                        btnA.appendChild(btnIcon);
                        btnA.appendChild(document.createTextNode('自定义'));
                        customLi.appendChild(btnA);
                        startLink.appendChild(customLi);
                    }

                    // 捐助作者按钮（受"隐藏捐助作者"设置控制）
                    if (cfg.dashboardHideDonate !== '1') {
                        var donateLi = document.createElement('li');
                        var donateA = document.createElement('a');
                        donateA.href = 'options-plugin.php?config=AdminBeautify&to=donateModal';
                        donateA.classList.add('ab-quick-highlight');
                        var donateIconWrap = document.createElement('span');
                        donateIconWrap.className = 'ab-quick-icon';
                        donateIconWrap.innerHTML = '<span class="material-icons-round">coffee</span>';
                        donateA.appendChild(donateIconWrap);
                        donateA.appendChild(document.createTextNode('捐助作者'));
                        donateLi.appendChild(donateA);
                        startLink.appendChild(donateLi);
                    }

                    // 若原有按钮全部隐藏且无自定义按钮，则隐藏整个容器
                    if (!quickShow && customBtns.length === 0 && mergedThemeBtnCount === 0) {
                        startLink.style.display = 'none';
                    }

                    
                }

                // 快捷按钮处理完毕后，再追加统计卡片到 board 末尾
                if (typeof statsGrid !== 'undefined' && statsGrid) {
                    board.appendChild(statsGrid);
                    // 数字计数动画（开启过渡动画时从 0 上升到实际值）
                    if ((window.__AB_CONFIG__ || {}).enableAnimation !== '0') {
                        var numEls = statsGrid.querySelectorAll('.ab-stat-num');
                        for (var ni = 0; ni < numEls.length; ni++) {
                            (function (el) {
                                var target = parseInt(el.textContent, 10);
                                if (isNaN(target) || target < 2) return;
                                el.textContent = '0';
                                var startTs = null, dur = 700;
                                function countStep(ts) {
                                    if (!startTs) startTs = ts;
                                    var p = Math.min((ts - startTs) / dur, 1);
                                    var ease = 1 - Math.pow(1 - p, 3); // easeOutCubic
                                    el.textContent = Math.round(ease * target);
                                    if (p < 1) requestAnimationFrame(countStep);
                                    else el.textContent = target;
                                }
                                requestAnimationFrame(countStep);
                            })(numEls[ni]);
                        }
                    }
                }
            }

            // ---- 3. 改造三列内容区为 MD3 cards ----
            var latestSections = dashboard.querySelectorAll('.latest-link');
            var recentCfg = window.__AB_CONFIG__ || {};
            var recentStyle = recentCfg.dashboardRecentStyle || 'md3';

            // 条数规则：回复固定 5 条；文章按卡片剩余高度自动填满（不溢出）
            // 各列表的完整条目全部缓存在 ul.__abAllItems，便于重算（fitDashboardCards）
            var recentMoreUrls = ['manage-posts.php', 'manage-comments.php'];
            var recentMoreText = ['查看全部文章', '查看全部评论'];
            var recentLists = [];

            var sectionIcons = [
                '<span class="material-icons-round">article</span>',
                '<span class="material-icons-round">comment</span>',
                '<span class="material-icons-round">rss_feed</span>'
            ];

            for (var k = 0; k < latestSections.length; k++) {
                var section = latestSections[k];

                // 始终隐藏第三个卡片（官方最新日志）
                if (k === 2) {
                    var rssCol = section.parentNode;
                    while (rssCol && !rssCol.classList.contains('col-tb-4')) rssCol = rssCol.parentNode;
                    if (rssCol) rssCol.style.display = 'none';
                    continue;
                }

                section.classList.add('ab-card', 'ab-dash-card');
                // 统一容器内的排序键（与「概要页卡片设置」保持一致）
                section.setAttribute('data-ab-card', k === 0 ? 'posts' : 'replies');

                // 卡片标题：重建为与其他卡片完全相同的结构（图标块 + 纯文本），
                // 避免原生 <h3> 里的空白/属性/包裹节点造成标题样式差异
                var secH3 = section.querySelector('h3');
                if (secH3) {
                    var hTitle = (secH3.textContent || '').replace(/^\s+|\s+$/g, '');
                    secH3.innerHTML =
                        '<span class="ab-card-header-icon"><span class="material-icons-round">' +
                        (k === 0 ? 'article' : 'comment') + '</span></span>' +
                        AdminBeautify._escHtml(hTitle);
                }

                // MD3 列表模式：重构 li 结构
                if (recentStyle === 'md3') {
                    var isCommentSec = (k === 1);
                    section.classList.add('ab-list-md3');
                    var mdItems = section.querySelectorAll('ul > li');
                    for (var mi = 0; mi < mdItems.length; mi++) {
                        var mdLi = mdItems[mi];
                        var mdDateSpan = mdLi.querySelector('span');
                        var mdTitleA = mdLi.querySelector('a.title');
                        if (!mdTitleA) continue;

                        var mdDate = mdDateSpan ? mdDateSpan.textContent.trim() : '';
                        var mdHref = mdTitleA.getAttribute('href') || '#';
                        var mdName = mdTitleA.textContent.trim();

                        if (isCommentSec) {
                            // 收集评论摘要：a.title 之后的所有节点文本
                            var mdExcerpt = '';
                            var mdSib = mdTitleA.nextSibling;
                            while (mdSib) {
                                mdExcerpt += mdSib.textContent || '';
                                mdSib = mdSib.nextSibling;
                            }
                            mdExcerpt = mdExcerpt.replace(/^\s*[:：]\s*/, '').trim();

                            mdLi.className = 'ab-list-item ab-list-item-comment';
                            mdLi.innerHTML =
                                '<a href="' + AdminBeautify._escHtml(mdHref) + '" class="ab-list-item-link">' +
                                    '<span class="ab-list-item-icon material-icons-round">person</span>' +
                                    '<span class="ab-list-item-content">' +
                                        '<span class="ab-list-item-line1">' +
                                            '<span class="ab-list-item-author">' + AdminBeautify._escHtml(mdName) + '</span>' +
                                            '<span class="ab-list-item-time">' + AdminBeautify._escHtml(mdDate) + '</span>' +
                                        '</span>' +
                                        (mdExcerpt ? '<span class="ab-list-item-excerpt">' + AdminBeautify._escHtml(mdExcerpt) + '</span>' : '') +
                                    '</span>' +
                                '</a>';
                        } else {
                            // 文章：单行，标题居左，日期居右
                            mdLi.className = 'ab-list-item ab-list-item-post';
                            mdLi.innerHTML =
                                '<a href="' + AdminBeautify._escHtml(mdHref) + '" class="ab-list-item-link">' +
                                    '<span class="ab-list-item-icon material-icons-round">article</span>' +
                                    '<span class="ab-list-item-title">' + AdminBeautify._escHtml(mdName) + '</span>' +
                                    '<span class="ab-list-item-time">' + AdminBeautify._escHtml(mdDate) + '</span>' +
                                '</a>';
                        }
                    }
                }

                // ---- 缓存完整条目，条数裁剪在布局完成后统一处理 ----
                var recentUl = section.querySelector('ul');
                if (recentUl) {
                    recentUl.__abAllItems = Array.prototype.slice.call(recentUl.children);
                    recentLists.push(recentUl);
                }

                // ---- 卡片底部「查看全部」入口（CSS 吸底） ----
                if (recentMoreUrls[k]) {
                    var moreLink = document.createElement('a');
                    moreLink.className = 'ab-card-footer';
                    moreLink.href = recentMoreUrls[k];
                    moreLink.innerHTML =
                        '<span class="ab-card-footer-text">' + recentMoreText[k] + '</span>' +
                        '<span class="material-icons-round ab-card-footer-icon">arrow_forward</span>';
                    section.appendChild(moreLink);
                }
            }

            // ---- 条数规则：回复固定 5 条，文章按高度填充 ----
            // 先至少保证两列条数不超过 10（官方 pageSize），细节由 fitDashboardCards 处理
            for (var rl = 0; rl < recentLists.length; rl++) {
                var rlUl = recentLists[rl];
                for (var rj = rlUl.children.length - 1; rj >= 10; rj--) {
                    rlUl.removeChild(rlUl.children[rj]);
                }
            }

            // ---- 统一容器：两个最近卡片脱离原列容器，与统计/图表卡片共用一个 grid ----
            var dashCards = document.createElement('div');
            dashCards.className = 'ab-cards-grid ab-dash-cards';
            dashCards.id = 'ab-dash-cards';
            var colContainers = dashboard.querySelectorAll('.col-tb-4');
            if (colContainers.length > 0) {
                var gridAnchor = colContainers[0];
                var gridParent  = gridAnchor.parentNode;
                for (var m = 0; m < colContainers.length; m++) {
                    var moveSec = colContainers[m].querySelector('.latest-link.ab-card');
                    if (moveSec) dashCards.appendChild(moveSec);
                }
                gridParent.insertBefore(dashCards, gridAnchor);
                // 清掉已空的列容器（「官方最新日志」列保持 display:none）
                for (var mc = colContainers.length - 1; mc >= 0; mc--) {
                    if (!colContainers[mc].children.length) {
                        colContainers[mc].parentNode.removeChild(colContainers[mc]);
                    }
                }
            } else {
                dashboard.appendChild(dashCards);
            }

            // ---- 4. 原版模式：增强评论列表项（包裹摘要文本） ----
            if (recentStyle !== 'md3') {
                var commentCards = dashboard.querySelectorAll('.ab-card');
                for (var n = 0; n < commentCards.length; n++) {
                    var oriLis = commentCards[n].querySelectorAll('ul > li');
                    for (var p = 0; p < oriLis.length; p++) {
                        var oriLi = oriLis[p];
                        var oriTitleA = oriLi.querySelector('a.title');
                        if (!oriTitleA) continue;

                        var afterNodes = [];
                        var sibling = oriTitleA.nextSibling;
                        while (sibling) {
                            afterNodes.push(sibling);
                            sibling = sibling.nextSibling;
                        }
                        if (afterNodes.length === 0) continue;

                        var hasContent = false;
                        for (var q = 0; q < afterNodes.length; q++) {
                            if (afterNodes[q].textContent && afterNodes[q].textContent.trim().length > 0) {
                                hasContent = true;
                                break;
                            }
                        }
                        if (!hasContent) continue;

                        var excerptSpan = document.createElement('span');
                        excerptSpan.className = 'ab-comment-excerpt';
                        for (var r = 0; r < afterNodes.length; r++) {
                            excerptSpan.appendChild(afterNodes[r]);
                        }
                        oriLi.appendChild(excerptSpan);
                    }
                }
            }

            // 标记 dashboard 已增强
            dashboard.classList.add('ab-dashboard-enhanced');

            // ---- 5. 概要页图表（更新频率 + 近期评论分类） ----
            (function () {
                var chartCfg = window.__AB_CONFIG__ || {};
                if (chartCfg.overviewChartEnabled === '0') return;

                var days = parseInt(chartCfg.overviewTimeRange || '30', 10);
                var periodLabel = days > 0 ? '近 ' + days + ' 天' : '全部时间';

                var freqCard = document.createElement('div');
                freqCard.className = 'ab-chart-card ab-card ab-dash-card';
                freqCard.setAttribute('data-ab-card', 'freq');
                freqCard.innerHTML =
                    '<h3><span class="ab-card-header-icon"><span class="material-icons-round">trending_up</span></span>更新频率<span class="ab-chart-period">' + periodLabel + '</span></h3>' +
                    '<div class="ab-chart-canvas-wrap"><div id="ab-chart-freq" class="ab-chart-canvas"><div class="ab-chart-skeleton"></div></div></div>';

                var catCard = document.createElement('div');
                catCard.className = 'ab-chart-card ab-card ab-dash-card';
                catCard.setAttribute('data-ab-card', 'cat');
                catCard.innerHTML =
                    '<h3><span class="ab-card-header-icon"><span class="material-icons-round">comment</span></span>近期评论<span class="ab-chart-period">' + periodLabel + '</span></h3>' +
                    '<div class="ab-chart-canvas-wrap"><div id="ab-chart-cat" class="ab-chart-canvas"><div class="ab-chart-skeleton"></div></div></div>';

                // 并入统一容器（与最近文章/评论、Umami 同一个 grid）
                var cardsGrid = document.getElementById('ab-dash-cards') || dashboard.querySelector('.ab-cards-grid');
                if (cardsGrid) {
                    cardsGrid.appendChild(freqCard);
                    cardsGrid.appendChild(catCard);
                } else {
                    dashboard.appendChild(freqCard);
                    dashboard.appendChild(catCard);
                }

                // 懒加载本地 ab-charts.js：统一走 AdminBeautify.charts.load()（只加载一次）
                function loadABCharts(cb) {
                    AdminBeautify.charts.load().then(function () {
                        cb();
                    }, function () {
                        var freqEl = document.getElementById('ab-chart-freq');
                        var catEl  = document.getElementById('ab-chart-cat');
                        if (freqEl) freqEl.innerHTML = '<div class="ab-chart-err">图表库加载失败</div>';
                        if (catEl)  catEl.innerHTML  = '<div class="ab-chart-err">图表库加载失败</div>';
                    });
                }

                // 请求图表数据
                AdminBeautify.ajax('chart-data', { days: days }, {
                    method: 'GET',
                    abortOnNavigate: true,
                    onSuccess: function (resp) {
                        var data          = resp.data || {};
                        var frequency     = data.frequency     || [];
                        var commentsByCat = data.commentsByCat || [];

                        /* ── 聚合频率数据（减少数据点密度）── */
                        var totalPosts = frequency.reduce(function (s, f) { return s + f.count; }, 0);
                        /* 按天数选组粒度：≤7天→逐天, ≤14天→每2天, ≤27天→每3天, 其余→每7天 */
                        var groupSize = days <= 7 ? 1 : (days <= 14 ? 2 : (days <= 27 ? 3 : 7));
                        var buckets = [];
                        for (var bi = 0; bi < frequency.length; bi += groupSize) {
                            var chunk = frequency.slice(bi, bi + groupSize);
                            var cnt   = chunk.reduce(function (s, f) { return s + f.count; }, 0);
                            /* X 轴标签：取该组第一天的 MM/DD */
                            var rawDate = chunk[0].date;
                            var parts   = rawDate.split('-');
                            buckets.push({
                                label: (parts[1] || '') + '/' + (parts[2] || ''),
                                count: cnt
                            });
                        }
                        var freqXData    = buckets.map(function (b) { return b.label; });
                        var freqYData    = buckets.map(function (b) { return b.count; });

                        loadABCharts(function () {
                            // ---- 折线图：更新频率 ----
                            var freqDom = document.getElementById('ab-chart-freq');
                            if (freqDom) {
                                if (frequency.length > 0) {
                                    window.ABCharts.line(freqDom, {
                                        xData:     freqXData,
                                        yData:     freqYData,
                                        color:     chartCfg.primaryColorHex     || '#6750a4',
                                        colorDark: chartCfg.primaryColorDarkHex || '#d0bcff'
                                    });
                                } else {
                                    freqDom.innerHTML = '<div class="ab-chart-empty">暂无文章数据</div>';
                                }
                            }

                            // ---- 极坐标图：近期评论分类 ----
                            var catDom = document.getElementById('ab-chart-cat');
                            if (catDom) {
                                if (commentsByCat.length > 0) {
                                    window.ABCharts.polar(catDom, {
                                        data: commentsByCat.map(function (c) {
                                            return { name: c.name, value: c.count };
                                        })
                                    });
                                } else {
                                    catDom.innerHTML = '<div class="ab-chart-empty">暂无评论数据</div>';
                                }
                            }
                        });
                    },
                    onError: function () {
                        var freqEl = document.getElementById('ab-chart-freq');
                        var catEl  = document.getElementById('ab-chart-cat');
                        if (freqEl) freqEl.innerHTML = '<div class="ab-chart-err">数据加载失败</div>';
                        if (catEl)  catEl.innerHTML  = '<div class="ab-chart-err">数据加载失败</div>';
                    }
                });
            })();

            // ---- 6. Umami 访问统计卡片 ----
            (function () {
                var cfg = window.__AB_CONFIG__ || {};
                if (cfg.umamiEnabled !== '1') return;
                var provider  = (cfg.umamiProvider || 'self') === 'cloud' ? 'cloud' : 'self';
                var apiBase   = provider === 'cloud' ? 'https://api.umami.is' : (cfg.umamiApiBase || '').replace(/\/$/, '');
                var websiteId = cfg.umamiWebsiteId  || '';
                var apiToken  = cfg.umamiApiToken   || '';
                if (!apiBase || !websiteId || !apiToken) return;
                var websiteApiPrefix = (provider === 'cloud' ? '/v1/websites/' : '/api/websites/') + websiteId;

                /* 时间范围：访客/时长/跳出率 三项使用此设置 */
                var umamiDays  = parseInt(cfg.umamiTimeRange || '30', 10);
                var now        = Date.now();
                var todayMs    = new Date(new Date().toDateString()).getTime();
                var rangeStart = umamiDays > 0 ? (now - umamiDays * 86400000) : 0;

                /* 单个「访问统计」卡片：5 项指标作为卡内对齐列表，作为普通卡片参与网格 */
                var umamiCard = document.createElement('div');
                umamiCard.className = 'ab-card ab-dash-card ab-umami-card-wrap';
                umamiCard.setAttribute('data-ab-card', 'umami');
                umamiCard.innerHTML =
                    '<h3><span class="ab-card-header-icon"><span class="material-icons-round">insights</span></span>' +
                    '访问统计</h3>' +
                    '<div class="ab-umami-grid" id="ab-umami-grid"></div>';
                var umamiHost = umamiCard.querySelector('.ab-umami-grid');

                var cardDefs = [
                    { id: 'ab-umami-today',    icon: 'today',        label: '今日访问' },
                    { id: 'ab-umami-total',    icon: 'bar_chart',    label: '总访问量' },
                    { id: 'ab-umami-visitors', icon: 'people',       label: '访客数量' },
                    { id: 'ab-umami-duration', icon: 'schedule',     label: '平均时长' },
                    { id: 'ab-umami-bounce',   icon: 'exit_to_app',  label: '跳出率'  }
                ];
                cardDefs.forEach(function (def) {
                    var card = document.createElement('div');
                    card.className = 'ab-umami-card';
                    card.id = def.id;
                    /* 结构：[图标胶囊] [指标名称] [右对齐数值]，行与行之间由发丝线分隔 */
                    card.innerHTML =
                        '<span class="material-icons-round ab-umami-icon">' + def.icon + '</span>' +
                        '<span class="ab-umami-card-label">' + def.label + '</span>' +
                        '<span class="ab-umami-card-value ab-chart-skeleton">\u00a0</span>';
                    umamiHost.appendChild(card);
                });

                /* 并入统一容器（默认排在最前，最终顺序由「概要页卡片设置」决定） */
                var umamiBox = document.getElementById('ab-dash-cards') || dashboard.querySelector('.ab-cards-grid');
                if (umamiBox) {
                    umamiBox.insertBefore(umamiCard, umamiBox.firstChild);
                } else {
                    dashboard.appendChild(umamiCard);
                }

                /* 辅助：通过 PHP proxy 请求 Umami API（绕过 CORS Authorization 限制）
                 * apiPath 只传 "/api/..." 部分，不含 apiBase */
                function uFetch(apiPath, cb) {
                    var ajaxBase = (window.__AB_AJAX__ || {}).url || '/action/admin-beautify';
                    var proxyUrl = ajaxBase + '?do=umami-proxy&path=' + encodeURIComponent(apiPath);
                    var xhr = new XMLHttpRequest();
                    // Umami 代理需要服务端出网（最长 10s）。登记为页面级请求：
                    // 页面一旦切换立即中断，绝不占用连接 / Session 拖慢新页面。
                    AdminBeautify._trackPageRequest(xhr);
                    xhr.open('GET', proxyUrl);
                    xhr.onload = function () {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            try {
                                var resp = JSON.parse(xhr.responseText);
                                if (resp && resp.code === 0) { cb(null, resp.data); }
                                else { cb(new Error((resp && resp.message) || 'proxy error')); }
                            } catch (e) { cb(e); }
                        } else { cb(new Error('HTTP ' + xhr.status)); }
                    };
                    xhr.onerror = function () { cb(new Error('network error')); };
                    xhr.send();
                }

                /* 辅助：写入卡片数值（替换骨架屏） */
                function setCardVal(id, text) {
                    var el = document.getElementById(id);
                    if (!el) return;
                    var v = el.querySelector('.ab-umami-card-value');
                    if (v) {
                        v.className = 'ab-umami-card-value';
                        v.textContent = text;
                    }
                }

                // 兼容 Umami 自建/Cloud 统计字段结构
                function statVal(stats, key) {
                    if (!stats || typeof stats !== 'object') return 0;
                    var raw = stats[key];
                    if (typeof raw === 'number') return raw;
                    if (raw && typeof raw.value === 'number') return raw.value;
                    return 0;
                }

                /* 辅助：格式化秒数 → "Xm Ys" 或 "Xs" */
                function fmtDuration(sec) {
                    return Math.round(sec) + 's';
                }

                /* ① 今日访问量（pageviews） */
                uFetch(
                    websiteApiPrefix +
                    '/stats?startAt=' + todayMs + '&endAt=' + now,
                    function (err, d) {
                        if (err || !d) { setCardVal('ab-umami-today', '—'); return; }
                        var todayPv = statVal(d, 'pageviews');
                        setCardVal('ab-umami-today', String(todayPv || 0));
                    }
                );

                /* ② 总访问量（全部时间） */
                uFetch(
                    websiteApiPrefix +
                    '/stats?startAt=0&endAt=' + now,
                    function (err, d) {
                        if (err || !d) { setCardVal('ab-umami-total', '—'); return; }
                        var v = statVal(d, 'pageviews');
                        setCardVal('ab-umami-total', v >= 10000 ? (v / 10000).toFixed(1) + 'w' : String(v));
                    }
                );

                /* ③ 时间范围内：访客数量 + 平均时长 + 跳出率（一次请求） */
                uFetch(
                    websiteApiPrefix +
                    '/stats?startAt=' + rangeStart + '&endAt=' + now,
                    function (err, d) {
                        if (err || !d) {
                            setCardVal('ab-umami-visitors', '—');
                            setCardVal('ab-umami-duration', '—');
                            setCardVal('ab-umami-bounce',   '—');
                            return;
                        }
                        /* 访客数量 */
                        var visitors = statVal(d, 'visitors');
                        setCardVal('ab-umami-visitors', visitors >= 10000
                            ? (visitors / 10000).toFixed(1) + 'w'
                            : String(visitors));

                        /* 平均访问时长 */
                        var totaltime = statVal(d, 'totaltime');
                        var avgSec = visitors > 0 ? totaltime / visitors : 0;
                        setCardVal('ab-umami-duration', visitors > 0 ? fmtDuration(avgSec) : '—');

                        /* 跳出率 */
                        var bounces = statVal(d, 'bounces');
                        var pct = visitors > 0 ? Math.round(bounces / visitors * 100) : 0;
                        setCardVal('ab-umami-bounce', visitors > 0 ? pct + '%' : '—');
                    }
                );
            })();

            // ---- 7. 自定义卡片（方案 C：自由 HTML/JS，默认关闭，开启后渲染） ----
            (function () {
                var ccfg = window.__AB_CONFIG__ || {};
                var host = document.getElementById('ab-dash-cards');
                if (!host) return;
                var esc = function (s) { return AdminBeautify._escHtml(String(s == null ? '' : s)); };
                var rawList = ccfg.dashboardCustomCards;
                var hasCards = !!(rawList && rawList.length);
                var enabled = ccfg.dashboardCustomCardsEnabled === '1';
                /* 已有卡片但用户把开关关掉时：什么都不渲染 */
                if (hasCards && !enabled) return;

                /* 一张自定义卡片都没有 → 放一张「引导卡」：
                 * 大加号 + 「开始管理你的自定义卡片」，点击进入插件设置的「概要页卡片设置」 */
                if (!hasCards) {
                    if (host.querySelector('[data-ab-card="custom:__empty__"]')) return;
                    var sBase = String(ccfg.pluginSettingsUrl || '');
                    if (!sBase) return;
                    var guideUrl = sBase + (sBase.indexOf('?') === -1 ? '?' : '&') + 'to=dashboardcards';
                    var guide = document.createElement('div');
                    guide.className = 'ab-card ab-dash-card ab-custom-card ab-custom-guide';
                    guide.setAttribute('data-ab-card', 'custom:__empty__');
                    guide.innerHTML =
                        '<h3><span class="ab-card-header-icon"><span class="material-icons-round">widgets</span></span>' +
                        '自定义卡片</h3>' +
                        '<div class="ab-custom-body"></div>';
                    abUi.mount(guide.querySelector('.ab-custom-body'), abUi.empty({
                        icon: 'add',
                        size: 'lg',
                        text: '开始管理你的自定义卡片',
                        hint: '点击前往「概要页卡片设置」开启并添加卡片',
                        href: guideUrl
                    }));
                    guide.style.cursor = 'pointer';
                    guide.title = '前往插件设置 · 概要页卡片设置';
                    guide.addEventListener('click', function (e) {
                        if (e.target && e.target.closest && e.target.closest('a[href]')) return;
                        location.href = guideUrl;
                    });
                    host.appendChild(guide);
                    return;
                }

                var list = rawList;
                for (var ci = 0; ci < list.length; ci++) {
                    var def = list[ci] || {};
                    var key = 'custom:' + (def.id || ci);
                    if (host.querySelector('[data-ab-card="' + key + '"]')) continue;
                    var card = document.createElement('div');
                    card.className = 'ab-card ab-dash-card ab-custom-card';
                    card.setAttribute('data-ab-card', key);
                    card.innerHTML =
                        '<h3><span class="ab-card-header-icon"><span class="material-icons-round">' + esc(def.icon || 'widgets') + '</span></span>' +
                        esc(def.title || '自定义卡片') + '</h3>' +
                        '<div class="ab-custom-body"></div>';
                    var bodyEl = card.querySelector('.ab-custom-body');
                    bodyEl.innerHTML = def.html || '';
                    /* HTML 字段里的 <style> 提到 card 上（不留在 .ab-custom-body 里）：
                     * 卡片脚本通常紧接着调用 ab.ui.mount(body, …)，那会清空 body，
                     * 样式若不搬走会被连根删掉 → 「写了 CSS 却不生效」（2.1.49 修复） */
                    var htmlStyles = bodyEl.querySelectorAll('style');
                    for (var hsIdx = 0; hsIdx < htmlStyles.length; hsIdx++) {
                        card.insertBefore(htmlStyles[hsIdx], bodyEl);
                    }
                    host.appendChild(card);
                    // 脚本：以 card 为 this，并注入迷你 API
                    // （ab.ui / ab.data / ab.db / ab.ajax / ab.request / ab.esc / ab.config
                    //   + 工具集：notify / copy / format / chart / onCleanup / interval / timeout）
                    if (def.js) {
                        var abApi = {
                            esc: esc,
                            ajax: function (doName, params, opts) { return AdminBeautify.ajax(doName, params, opts); },
                            config: ccfg,
                            ui: abUi,
                            data: abData,
                            db: abDb,
                            request: abRequest,
                            version: ccfg.pluginVersion || '',
                            /* ---- 工具集 ---- */
                            notify: abNotify,
                            copy: abCopy,
                            copyText: abCopyText,
                            format: abFormat,
                            chart: abChart,
                            /* 卡片被销毁（AJAX 切页 / 重渲染）时自动执行，用来收尾定时器与监听 */
                            onCleanup: function (fn) { addCardCleanup(card, fn); return card; },
                            interval: function (fn, ms) {
                                var id = window.setInterval(fn, ms);
                                addCardCleanup(card, function () { window.clearInterval(id); });
                                return id;
                            },
                            timeout: function (fn, ms) {
                                var id = window.setTimeout(fn, ms);
                                addCardCleanup(card, function () { window.clearTimeout(id); });
                                return id;
                            },
                            cleanup: function () { return runCardCleanups(card); }
                        };
                        try {
                            (new Function('card', 'ab', def.js)).call(card, card, abApi);
                        } catch (err) {
                            if (window.console && console.warn) console.warn('[AB] 自定义卡片脚本出错：' + key, err);
                            /* 卡片里也给一条看得见的提示，而不是只留一片空白 */
                            try {
                                if (!bodyEl.querySelector('.ab-custom-error')) {
                                    var errBox = document.createElement('div');
                                    errBox.className = 'ab-custom-error';
                                    errBox.innerHTML = '<span class="material-icons-round">error_outline</span>' +
                                        '<span>卡片脚本执行出错：' + esc(String((err && err.message) || err || '未知错误')) +
                                        '（详情见浏览器控制台）</span>';
                                    bodyEl.insertBefore(errBox, bodyEl.firstChild);
                                }
                            } catch (e2) {}
                        }
                    }
                }
            })();

            // ---- 8. 统一卡片顺序：读取「概要页卡片设置」保存的顺序（含自定义卡片） ----
            (function () {
                var grid = document.getElementById('ab-dash-cards');
                if (!grid) return;
                var cards = Array.prototype.slice.call(grid.querySelectorAll('[data-ab-card]'));
                if (!cards.length) return;
                var exists = {};
                for (var c = 0; c < cards.length; c++) {
                    exists[cards[c].getAttribute('data-ab-card')] = cards[c];
                }
                var raw = String((window.__AB_CONFIG__ || {}).dashboardCardOrder || '');
                var parts = raw ? raw.split(',') : [];
                var ordered = [], used = {};
                for (var i = 0; i < parts.length; i++) {
                    var key = parts[i].replace(/^\s+|\s+$/g, '');
                    if (key && exists[key] && !used[key]) { ordered.push(exists[key]); used[key] = 1; }
                }
                // 未在设置中出现的内置卡片：按默认顺序追加
                var defaults = ['umami', 'freq', 'cat', 'posts', 'replies'];
                for (var d = 0; d < defaults.length; d++) {
                    if (exists[defaults[d]] && !used[defaults[d]]) { ordered.push(exists[defaults[d]]); used[defaults[d]] = 1; }
                }
                // 剩下的（自定义卡片等）追加到末尾
                for (var e = 0; e < cards.length; e++) {
                    var k2 = cards[e].getAttribute('data-ab-card');
                    if (!used[k2]) { ordered.push(cards[e]); used[k2] = 1; }
                }
                for (var n = 0; n < ordered.length; n++) grid.appendChild(ordered[n]);
            })();

            // ---- 9. 应用条数规则（回复 5 条 / 文章按高度填充），并在尺寸变化时重算 ----
            if (!AdminBeautify._dashFitBound) {
                AdminBeautify._dashFitBound = true;
                var fitTimer = null;
                window.addEventListener('resize', function () {
                    if (fitTimer) clearTimeout(fitTimer);
                    fitTimer = setTimeout(function () { AdminBeautify.fitDashboardCards(); }, 200);
                });
            }
            AdminBeautify.fitDashboardCards();
            window.addEventListener('load', function () { AdminBeautify.fitDashboardCards(); });
            if (document.fonts && document.fonts.ready && document.fonts.ready.then) {
                document.fonts.ready.then(function () { AdminBeautify.fitDashboardCards(); });
            }
        },

        /**
         * 概览页条数规则：回复卡片固定 5 条；文章卡片按同行卡片高度自动填满（不溢出）
         * - 两卡片同行时：行高由回复卡片决定，文章条目逐条添加直到填满剩余高度
         * - 不同行（窄屏单列）时：文章卡片使用自然高度，最多 10 条
         */
        fitDashboardCards: function () {
            var grid = document.getElementById('ab-dash-cards');
            if (!grid) return;
            var postCard  = grid.querySelector('[data-ab-card="posts"]');
            var replyCard = grid.querySelector('[data-ab-card="replies"]');
            if (!postCard || !replyCard) return;
            var postUl  = postCard.querySelector('ul');
            var replyUl = replyCard.querySelector('ul');
            if (!postUl || !replyUl) return;
            var postItems  = postUl.__abAllItems;
            var replyItems = replyUl.__abAllItems;
            if (!postItems || !replyItems) return;

            /* ① 回复卡片固定 5 条 */
            var keep = Math.min(5, replyItems.length);
            for (var i = 0; i < keep; i++) {
                if (replyItems[i].parentNode !== replyUl) replyUl.appendChild(replyItems[i]);
            }
            for (var j = keep; j < replyItems.length; j++) {
                if (replyItems[j].parentNode === replyUl) replyUl.removeChild(replyItems[j]);
            }

            /* ② 两卡片是否在同一行 */
            var pr = postCard.getBoundingClientRect();
            var rr = replyCard.getBoundingClientRect();
            var sameRow = Math.abs(pr.top - rr.top) < 2;

            /* ③ 不同行：自然高度（最多 10 条） */
            if (!sameRow) {
                postUl.classList.remove('ab-fit-posts');
                for (var a = 0; a < postItems.length; a++) {
                    if (postItems[a].parentNode !== postUl) postUl.appendChild(postItems[a]);
                }
                return;
            }

            /* ④ 同行：先清空让行高由回复卡片决定，再逐条填满剩余高度 */
            postUl.classList.add('ab-fit-posts');
            while (postUl.firstChild) postUl.removeChild(postUl.firstChild);
            void postUl.offsetHeight;                 // 强制布局，拿到可用高度
            var avail = postUl.clientHeight;
            if (avail < 24) {                         // 测不出高度（隐藏/未布局）：退回自然高度
                postUl.classList.remove('ab-fit-posts');
                for (var b = 0; b < postItems.length; b++) {
                    if (postItems[b].parentNode !== postUl) postUl.appendChild(postItems[b]);
                }
                return;
            }
            for (var k = 0; k < postItems.length; k++) {
                postUl.appendChild(postItems[k]);
                if (postUl.scrollHeight > avail) { postUl.removeChild(postItems[k]); break; }
            }
        },

        /**
         * MD3 Profile 个人设置页增强
         */
        enhanceProfile: function () {
            var avatar = document.querySelector('.profile-avatar');
            if (!avatar) return;
            // Find the profile page main container
            var pageMain = document.querySelector('.typecho-page-main');
            if (!pageMain || pageMain.classList.contains('ab-profile-enhanced')) return;

            // ---- 1. 左侧用户信息卡片 ----
            var leftCol = pageMain.querySelector('.col-tb-3');
            if (leftCol) {
                leftCol.classList.add('ab-profile-sidebar');

                // 将左侧内容包裹在 MD3 卡片中
                var sidebarCard = document.createElement('div');
                sidebarCard.className = 'ab-profile-card';

                // 头像区域
                var avatarWrap = document.createElement('div');
                avatarWrap.className = 'ab-profile-avatar-wrap';
                var avatarLink = avatar.parentElement; // <a> wrapping the img
                if (avatarLink && avatarLink.tagName === 'A') {
                    var avatarP = avatarLink.parentElement;
                    avatarWrap.appendChild(avatarLink);
                    if (avatarP && avatarP.tagName === 'P') avatarP.style.display = 'none';
                } else {
                    avatarWrap.appendChild(avatar.cloneNode(true));
                }
                sidebarCard.appendChild(avatarWrap);

                // 用户信息区
                var infoWrap = document.createElement('div');
                infoWrap.className = 'ab-profile-info';

                // 获取 screenName (h2) 和 name (p)
                var h2 = leftCol.querySelector('h2');
                var allP = leftCol.querySelectorAll('p');

                if (h2) {
                    var nameEl = document.createElement('h2');
                    nameEl.className = 'ab-profile-name';
                    nameEl.textContent = h2.textContent;
                    infoWrap.appendChild(nameEl);
                    h2.style.display = 'none';
                }

                // name (second p, after avatar p)
                if (allP.length > 1 && allP[1].textContent.trim()) {
                    var usernameEl = document.createElement('p');
                    usernameEl.className = 'ab-profile-username';
                    usernameEl.textContent = '@' + allP[1].textContent.trim();
                    infoWrap.appendChild(usernameEl);
                    allP[1].style.display = 'none';
                }

                sidebarCard.appendChild(infoWrap);

                // 统计信息
                var statsP = null;
                var loginP = null;
                for (var i = 0; i < allP.length; i++) {
                    var pText = allP[i].textContent;
                    if (pText.indexOf('篇日志') !== -1 || pText.indexOf('篇文章') !== -1) {
                        statsP = allP[i];
                    }
                    if (pText.indexOf('最后登录') !== -1) {
                        loginP = allP[i];
                    }
                }

                if (statsP) {
                    var statsWrap = document.createElement('div');
                    statsWrap.className = 'ab-profile-stats';

                    var ems = statsP.querySelectorAll('em');
                    var statItems = [
                        { num: ems[0] ? ems[0].textContent : '0', label: '文章' },
                        { num: ems[1] ? ems[1].textContent : '0', label: '评论' },
                        { num: ems[2] ? ems[2].textContent : '0', label: '分类' }
                    ];

                    for (var s = 0; s < statItems.length; s++) {
                        var statDiv = document.createElement('div');
                        statDiv.className = 'ab-profile-stat-item';
                        statDiv.innerHTML =
                            '<span class="ab-profile-stat-num">' + statItems[s].num + '</span>' +
                            '<span class="ab-profile-stat-label">' + statItems[s].label + '</span>';
                        statsWrap.appendChild(statDiv);
                    }

                    sidebarCard.appendChild(statsWrap);
                    statsP.style.display = 'none';
                }

                if (loginP) {
                    var loginDiv = document.createElement('div');
                    loginDiv.className = 'ab-profile-login-info';
                    loginDiv.innerHTML =
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                            '<circle cx="12" cy="12" r="10"></circle>' +
                            '<polyline points="12 6 12 12 16 14"></polyline>' +
                        '</svg>' +
                        '<span>' + loginP.textContent.trim() + '</span>';
                    sidebarCard.appendChild(loginDiv);
                    loginP.style.display = 'none';
                }

                leftCol.insertBefore(sidebarCard, leftCol.firstChild);
            }

            // ---- 2. 右侧表单区域卡片化 ----
            var contentPanel = pageMain.querySelector('.typecho-content-panel');
            if (contentPanel) {
                contentPanel.classList.add('ab-profile-content');

                var sections = contentPanel.querySelectorAll('section');
                var sectionIcons = [
                    // 个人资料
                    '<span class="material-icons-round">person</span>',
                    // 撰写设置
                    '<span class="material-icons-round">edit</span>',
                    // 密码修改
                    '<span class="material-icons-round">lock</span>'
                ];

                for (var j = 0; j < sections.length; j++) {
                    var section = sections[j];
                    section.classList.add('ab-profile-section');

                    // 移除前面的 <br> 标签
                    var prevEl = section.previousElementSibling;
                    if (prevEl && prevEl.tagName === 'BR') {
                        prevEl.style.display = 'none';
                    }

                    // 增强 h3 标题
                    var h3 = section.querySelector('h3');
                    if (h3 && sectionIcons[j]) {
                        var iconSpan = document.createElement('span');
                        iconSpan.className = 'ab-profile-section-icon';
                        iconSpan.innerHTML = sectionIcons[j];
                        h3.insertBefore(iconSpan, h3.firstChild);
                        h3.classList.add('ab-profile-section-title');
                    }
                }
            }

            // 标记已增强
            pageMain.classList.add('ab-profile-enhanced');
        },

        /**
         * MD3 Options 设置页面增强 — 表单卡片化
         * 适用于: options-general / options-discussion / options-reading / options-permalink
         */
        enhanceOptions: function () {
            // 仅在 4 个设置页面执行
            var optionsPages = [
                'options-general.php',
                'options-discussion.php',
                'options-reading.php',
                'options-permalink.php'
            ];
            var currentPage = '';
            for (var i = 0; i < optionsPages.length; i++) {
                if (location.href.indexOf(optionsPages[i]) !== -1) {
                    currentPage = optionsPages[i];
                    break;
                }
            }
            if (!currentPage) return;

            var pageMain = document.querySelector('.typecho-page-main');
            if (!pageMain || pageMain.classList.contains('ab-options-enhanced')) return;

            var col = pageMain.querySelector('.col-mb-12');
            if (!col) return;

            var form = col.querySelector('form');
            if (!form) return;

            // 页面图标映射
            var pageIcons = {
                'options-general.php':
                    '<span class="material-icons-round">settings</span>',
                'options-discussion.php':
                    '<span class="material-icons-round">comment</span>',
                'options-reading.php':
                    '<span class="material-icons-round">menu_book</span>',
                'options-permalink.php':
                    '<span class="material-icons-round">link</span>'
            };

            // 获取页面标题
            var pageTitle = '';
            var pageTitleEl = document.querySelector('.typecho-page-title h2');
            if (pageTitleEl) {
                pageTitle = pageTitleEl.textContent.trim();
            }

            // 给 form 添加卡片类
            form.classList.add('ab-options-card');

            // 创建卡片 header
            var header = document.createElement('div');
            header.className = 'ab-options-card-header';
            header.innerHTML =
                '<span class="ab-options-card-icon">' +
                    (pageIcons[currentPage] || '') +
                '</span>' +
                '<span class="ab-options-card-title">' +
                    this._escHtml(pageTitle) +
                '</span>';
            form.insertBefore(header, form.firstChild);

            // 找到提交按钮所在的 ul，添加 footer 类
            var allOptions = form.querySelectorAll('ul.typecho-option');
            for (var j = allOptions.length - 1; j >= 0; j--) {
                if (allOptions[j].querySelector('.btn.primary, button[type="submit"], input[type="submit"]')) {
                    allOptions[j].classList.add('ab-options-card-submit');
                    break;
                }
            }

            // 标记已增强
            pageMain.classList.add('ab-options-enhanced');
        },

        /**
         * MD3 Plugins 页面增强 — 将表格转为卡片网格
         */
        enhancePlugins: function () {
            // 仅在 plugins.php 页面执行
            if (location.href.indexOf('plugins.php') === -1) return;
            var listWrap = document.querySelector('.typecho-list');
            if (!listWrap || listWrap.classList.contains('ab-plugins-enhanced') || listWrap.classList.contains('ab-plugins-table-mode')) return;
            // 必须同时有 typecho-list-table 表格存在
            var tables = listWrap.querySelectorAll('table.typecho-list-table');
            if (!tables.length) return;

            var self = this;
            var cfg = window.__AB_CONFIG__ || {};

            // 插入搜索框（无论卡片/表格模式均显示）
            if (!listWrap.querySelector('.ab-plugins-search-wrap')) {
                var searchWrap = document.createElement('div');
                searchWrap.className = 'ab-plugins-search-wrap';
                searchWrap.innerHTML =
                    '<span class="ab-plugins-search-icon">' +
                        '<span class="material-icons-round">search</span>' +
                    '</span>' +
                    '<input type="search" class="ab-plugins-search" placeholder="搜索插件名称或描述…" autocomplete="off">';
                listWrap.insertBefore(searchWrap, listWrap.firstChild);

                var searchInput = searchWrap.querySelector('.ab-plugins-search');
                searchInput.addEventListener('input', function () {
                    var q = this.value.trim().toLowerCase();
                    // 同时处理卡片和原始表格行
                    var cards = listWrap.querySelectorAll('.ab-plugin-card');
                    var rows  = listWrap.querySelectorAll('table.typecho-list-table tbody > tr');
                    cards.forEach(function (card) {
                        var text = card.textContent.toLowerCase();
                        card.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
                    });
                    rows.forEach(function (row) {
                        var text = row.textContent.toLowerCase();
                        row.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
                    });
                    // 隐藏所有卡片都被过滤掉的分组标题
                    var grids = listWrap.querySelectorAll('.ab-plugins-grid');
                    grids.forEach(function (grid) {
                        var visibleCards = grid.querySelectorAll('.ab-plugin-card:not([style*="none"])');
                        var h4 = grid.previousElementSibling;
                        if (h4 && h4.tagName === 'H4') {
                            h4.style.display = (visibleCards.length === 0 && q) ? 'none' : '';
                        }
                        grid.style.display = (visibleCards.length === 0 && q) ? 'none' : '';
                    });
                });
            }

            // 如果设置为"原始表格"模式则不进行卡片化转换
            // 注意：不加 ab-plugins-enhanced 类，避免 CSS 隐藏原始表格
            if (cfg.pluginCardView === '0') {
                listWrap.classList.add('ab-plugins-table-mode');
                return;
            }

            // 生成首字母哈希色
            function letterColor(name) {
                var colors = [
                    '#7D5260','#7D5260','#4A6741','#006B5F','#00658E',
                    '#6B5778','#855318','#4A6267','#006874','#006D3B'
                ];
                var code = 0;
                for (var c = 0; c < name.length; c++) code += name.charCodeAt(c);
                return colors[code % colors.length];
            }

            // 处理每个 section（启用 / 禁用）
            for (var t = 0; t < tables.length; t++) {
                var table = tables[t];
                var isDeactivated = table.classList.contains('deactivate');

                // 对应的 section 标题
                var sectionTitle = table.previousElementSibling;
                if (sectionTitle && sectionTitle.tagName === 'H4') {
                    sectionTitle.classList.add('ab-plugins-section-title');
                    // 添加图标
                    if (!sectionTitle.querySelector('.ab-plugins-section-icon')) {
                        var titleIcon = document.createElement('span');
                        titleIcon.className = 'ab-plugins-section-icon';
                        if (isDeactivated) {
                            titleIcon.innerHTML = '<span class="material-icons-round">block</span>';
                        } else {
                            titleIcon.innerHTML = '<span class="material-icons-round">check_circle_outline</span>';
                        }
                        sectionTitle.insertBefore(titleIcon, sectionTitle.firstChild);
                    }

                    // 计数徽章
                    var rows = table.querySelectorAll('tbody > tr');
                    var countBadge = document.createElement('span');
                    countBadge.className = 'ab-plugins-count';
                    countBadge.textContent = rows.length;
                    sectionTitle.appendChild(countBadge);
                }

                // 构建卡片网格
                var grid = document.createElement('div');
                grid.className = 'ab-plugins-grid' + (isDeactivated ? ' ab-plugins-grid-deactivated' : '');

                var tbodyRows = table.querySelectorAll('tbody > tr');
                for (var r = 0; r < tbodyRows.length; r++) {
                    var tr = tbodyRows[r];
                    var tds = tr.querySelectorAll('td');
                    if (tds.length < 2) continue;

                    // 解析数据
                    var nameCell = tds[0];
                    var descCell = tds[1];
                    var pluginName = nameCell.textContent.trim();
                    var pluginId = tr.id || '';
                    var hasBroken = nameCell.querySelector('.i-delete');
                    var isBrokenOrphan = nameCell.querySelector('.warning') || descCell.querySelector('.warning');

                    // 版本 & 作者
                    var version = '';
                    var authorHtml = '';
                    if (tds.length >= 4) {
                        version = tds[2] ? tds[2].textContent.trim() : '';
                        authorHtml = tds[3] ? tds[3].innerHTML.trim() : '';
                    }

                    // 描述
                    var descHtml = descCell.innerHTML;
                    // 如果是损坏的插件行（colspan），描述在第二个 td
                    if (isBrokenOrphan) {
                        descHtml = '<span class="ab-plugin-warning">' + descCell.innerHTML + '</span>';
                    }

                    // 操作
                    var actionCell = tds[tds.length - 1];
                    var actionsHtml = '';
                    var actionLinks = actionCell.querySelectorAll('a');
                    var isPlugAndPlay = !!actionCell.querySelector('.important');

                    for (var a = 0; a < actionLinks.length; a++) {
                        var aLink = actionLinks[a];
                        var aText = aLink.textContent.trim();
                        var aHref = aLink.getAttribute('href') || '';
                        var aLang = aLink.getAttribute('lang') || '';
                        var btnClass = 'ab-plugin-btn';

                        if (aText === '设置') {
                            btnClass += ' ab-plugin-btn-settings';
                        } else if (aText === '禁用') {
                            btnClass += ' ab-plugin-btn-deactivate';
                        } else if (aText === '启用') {
                            btnClass += ' ab-plugin-btn-activate';
                        }

                        actionsHtml += '<a class="' + btnClass + '" href="' + self._escHtml(aHref) + '"';
                        if (aLang) actionsHtml += ' lang="' + self._escHtml(aLang) + '"';
                        actionsHtml += '>' + self._escHtml(aText) + '</a>';
                    }

                    if (isPlugAndPlay) {
                        actionsHtml += '<span class="ab-plugin-plug-play">即插即用</span>';
                    }

                    // 构建卡片
                    var card = document.createElement('div');
                    card.className = 'ab-plugin-card';
                    if (pluginId) card.id = pluginId;
                    if (isDeactivated) card.classList.add('ab-plugin-card-deactivated');
                    if (hasBroken) card.classList.add('ab-plugin-card-broken');
                    if (isBrokenOrphan) card.classList.add('ab-plugin-card-orphan');

                    var color = letterColor(pluginName);
                    var initial = pluginName.charAt(0).toUpperCase();

                    card.innerHTML =
                        '<div class="ab-plugin-card-header">' +
                            '<div class="ab-plugin-icon" style="background:' + color + '">' +
                                '<span>' + self._escHtml(initial) + '</span>' +
                            '</div>' +
                            '<div class="ab-plugin-meta">' +
                                '<div class="ab-plugin-name-row">' +
                                    '<span class="ab-plugin-name">' + self._escHtml(pluginName) + '</span>' +
                                    (version ? '<span class="ab-plugin-version">v' + self._escHtml(version) + '</span>' : '') +
                                    (hasBroken ? '<span class="ab-plugin-broken-badge" title="此插件无法在当前版本正常工作">⚠</span>' : '') +
                                '</div>' +
                                (authorHtml ? '<div class="ab-plugin-author">' + authorHtml + '</div>' : '') +
                            '</div>' +
                        '</div>' +
                        '<div class="ab-plugin-card-body">' +
                            '<div class="ab-plugin-desc">' + descHtml + '</div>' +
                        '</div>' +
                        '<div class="ab-plugin-card-footer">' +
                            (!isDeactivated ? '<span class="ab-plugin-status-active"><span class="material-icons-round">check</span>已启用</span>' : '') +
                            '<div class="ab-plugin-actions">' + actionsHtml + '</div>' +
                        '</div>';

                    grid.appendChild(card);
                }

                // 替换表格
                table.style.display = 'none';
                table.parentNode.insertBefore(grid, table.nextSibling);
            }

            // 拦截插件操作按钮（启用/禁用），使其通过 AJAX 导航
            // 这些链接指向 /action/plugins-edit?activate=... 或 ?deactivate=...
            // 被 _isAjaxable() 拦截（含 /action/），且脱离了原 table 的 jQuery 确认处理
            listWrap.addEventListener('click', function (e) {
                var link = e.target.closest('.ab-plugin-btn-activate, .ab-plugin-btn-deactivate');
                if (!link) return;

                e.preventDefault();
                e.stopPropagation();

                // 禁用按钮有 lang 属性用于确认对话框（与 Typecho 原生行为一致）
                var confirmMsg = link.getAttribute('lang');
                if (confirmMsg && !confirm(confirmMsg)) return;

                // XHR 会透明跟随 302 重定向回 plugins.php，_applyPage 正常处理
                self._navigateTo(link.href);
            });

            // 标记已增强
            listWrap.classList.add('ab-plugins-enhanced');
        },

        /**
         * MD3 Themes 外观页面增强 — 将表格转为卡片网格
         */
        enhanceThemes: function () {
            // 仅在 themes.php 页面执行
            if (location.href.indexOf('themes.php') === -1) return;
            var table = document.querySelector('.typecho-theme-list');
            if (!table || table.classList.contains('ab-themes-enhanced')) return;

            var self = this;
            var tbodyRows = table.querySelectorAll('tbody > tr');
            if (!tbodyRows.length) return;

            // 构建卡片网格
            var grid = document.createElement('div');
            grid.className = 'ab-themes-grid';

            // IntersectionObserver 懒加载
            var themeImgObserver = null;
            if (window.IntersectionObserver) {
                themeImgObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (!entry.isIntersecting) return;
                        var img = entry.target;
                        var src = img.getAttribute('data-src');
                        if (src) { img.src = src; img.removeAttribute('data-src'); }
                        themeImgObserver.unobserve(img);
                    });
                }, { rootMargin: '120px' });
            }

            for (var r = 0; r < tbodyRows.length; r++) {
                var tr = tbodyRows[r];
                var tds = tr.querySelectorAll('td');
                var isCurrent = tr.classList.contains('current');
                var isWarning = tds.length === 1 && tds[0].getAttribute('colspan');

                // 缺失主题警告行（colspan="2"）
                if (isWarning) {
                    var warnCard = document.createElement('div');
                    warnCard.className = 'ab-theme-card ab-theme-card-warning';
                    warnCard.innerHTML =
                        '<div class="ab-theme-card-body">' +
                            '<div class="ab-theme-warning">' + tds[0].innerHTML + '</div>' +
                        '</div>';
                    grid.appendChild(warnCard);
                    continue;
                }

                if (tds.length < 2) continue;

                // 解析数据
                var imgCell = tds[0];
                var infoCell = tds[1];
                var img = imgCell.querySelector('img');
                var h3 = infoCell.querySelector('h3');
                var cite = infoCell.querySelector('cite');
                var descP = infoCell.querySelector('p');
                var actionLinks = infoCell.querySelectorAll('a.edit, a.activate');

                var themeName = h3 ? h3.textContent.trim() : '';
                var themeDesc = descP ? descP.innerHTML.trim() : '';
                var citeHtml = cite ? cite.innerHTML.trim() : '';
                var imgSrc = img ? (img.getAttribute('src') || '') : '';
                var imgAlt = img ? (img.getAttribute('alt') || themeName) : themeName;

                // 构建操作按钮
                var actionsHtml = '';
                if (isCurrent) {
                    actionsHtml += '<span class="ab-theme-status-active">' +
                        '<span class="material-icons-round">check</span>' +
                        '当前外观</span>';
                }
                for (var a = 0; a < actionLinks.length; a++) {
                    var aLink = actionLinks[a];
                    var aText = aLink.textContent.trim();
                    var aHref = aLink.getAttribute('href') || '';
                    var aClass = aLink.getAttribute('class') || '';
                    var btnClass = 'ab-theme-btn';

                    if (aClass.indexOf('edit') !== -1 || aText === '编辑') {
                        btnClass += ' ab-theme-btn-edit';
                    } else if (aClass.indexOf('activate') !== -1 || aText === '启用') {
                        btnClass += ' ab-theme-btn-activate';
                    }

                    actionsHtml += '<a class="' + btnClass + '" href="' + self._escHtml(aHref) + '">' + self._escHtml(aText) + '</a>';
                }

                // 构建卡片
                var card = document.createElement('div');
                card.className = 'ab-theme-card';
                if (isCurrent) card.classList.add('ab-theme-card-current');
                if (tr.id) card.id = tr.id;

                card.innerHTML =
                    '<div class="ab-theme-card-screenshot">' +
                        (imgSrc
                            ? '<img data-src="' + self._escHtml(imgSrc) + '" alt="" />'
                            : '<div class="ab-theme-no-screenshot"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg></div>'
                        ) +
                        (isCurrent ? '<div class="ab-theme-current-badge">使用中</div>' : '') +
                    '</div>' +
                    '<div class="ab-theme-card-info">' +
                        '<h3 class="ab-theme-name">' + self._escHtml(themeName) + '</h3>' +
                        (citeHtml ? '<div class="ab-theme-meta">' + citeHtml + '</div>' : '') +
                        (themeDesc ? '<div class="ab-theme-desc">' + themeDesc + '</div>' : '') +
                    '</div>' +
                    '<div class="ab-theme-card-footer">' +
                        '<div class="ab-theme-actions">' + actionsHtml + '</div>' +
                    '</div>';

                grid.appendChild(card);

                // 懒加载：插入 DOM 后再 observe
                var lazyImg = card.querySelector('[data-src]');
                if (lazyImg) {
                    lazyImg.addEventListener('load', function () {
                        var wrap = this.closest('.ab-theme-card-screenshot');
                        if (wrap) wrap.classList.add('ab-loaded');
                    });
                    lazyImg.addEventListener('error', function () {
                        var wrap = this.closest('.ab-theme-card-screenshot');
                        if (wrap) wrap.classList.add('ab-loaded');
                    });
                    if (themeImgObserver) {
                        themeImgObserver.observe(lazyImg);
                    } else {
                        lazyImg.src = lazyImg.getAttribute('data-src');
                    }
                } else {
                    // 无图片时也标记完成
                    var wrap = card.querySelector('.ab-theme-card-screenshot');
                    if (wrap) wrap.classList.add('ab-loaded');
                }
            }

            // 替换表格
            table.style.display = 'none';
            table.parentNode.insertBefore(grid, table.nextSibling);

            // 拦截启用外观按钮（/action/ URL 被 _isAjaxable 阻止）
            grid.addEventListener('click', function (e) {
                var link = e.target.closest('.ab-theme-btn-activate');
                if (!link) return;

                e.preventDefault();
                e.stopPropagation();

                var confirmMsg = link.getAttribute('lang');
                if (confirmMsg && !confirm(confirmMsg)) return;

                self._navigateTo(link.href);
            });

            // 标记已增强
            table.classList.add('ab-themes-enhanced');
        },

        /**
         * Option Tabs FAB：当 tabs 换行时折叠为下拉菜单
         */
        initTabsFab: function () {
            var self = this;

            var checkAll = function () {
                var lists = document.querySelectorAll('.typecho-option-tabs');
                for (var i = 0; i < lists.length; i++) {
                    self._checkTabsWrap(lists[i]);
                }
            };

            checkAll();

            // 响应窗口 resize（防抖）
            if (!self._tabsFabResizeBound) {
                self._tabsFabResizeBound = true;
                var rt = null;
                window.addEventListener('resize', function () {
                    clearTimeout(rt);
                    rt = setTimeout(checkAll, 200);
                });
            }
        },

        _checkTabsWrap: function (tabList) {
            var items = tabList.querySelectorAll('li');
            if (!items.length || items.length < 2) return;

            // 若当前处于隐藏状态（fab mode），先临时显示以便测量
            var wasFabMode = tabList.classList.contains('ab-tabs-fab-mode');
            if (wasFabMode) {
                tabList.classList.remove('ab-tabs-fab-mode');
                void tabList.offsetWidth; // force reflow
            }

            var firstTop = items[0].getBoundingClientRect().top;
            var lastTop  = items[items.length - 1].getBoundingClientRect().top;
            var isWrapped = lastTop > firstTop + 4;

            // 查找已有 FAB（插入在 tabList 之前）
            var prev   = tabList.previousElementSibling;
            var hasFab = prev && prev.classList.contains('ab-tabs-fab');

            if (isWrapped) {
                tabList.classList.add('ab-tabs-fab-mode');
                if (!hasFab) {
                    this._createTabsFab(tabList);
                }
            } else {
                // 不换行 —— 确保 tabs 显示，移除 FAB
                // （已在上方移除了 ab-tabs-fab-mode，无需再操作）
                if (hasFab) {
                    prev.parentNode.removeChild(prev);
                }
            }
        },

        _createTabsFab: function (tabList) {
            var self = this;

            // 获取当前激活 tab 文字
            var activeItem = tabList.querySelector('li.active, li.current');
            var activeText = '选项';
            if (activeItem) {
                var aEl = activeItem.querySelector('a');
                if (aEl) activeText = aEl.textContent.trim();
            }

            // 构建 FAB 容器
            var fab = document.createElement('div');
            fab.className = 'ab-tabs-fab';

            // 触发按钮
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ab-tabs-fab-btn';
            btn.innerHTML =
                '<span class="ab-tabs-fab-label">' + this._escHtml(activeText) + '</span>' +
                '<em class="ab-tabs-fab-arrow">&#9660;</em>';

            // 下拉菜单
            var menu = document.createElement('div');
            menu.className = 'ab-tabs-fab-menu';

            var liItems = tabList.querySelectorAll('li');
            for (var i = 0; i < liItems.length; i++) {
                (function (li) {
                    var a = li.querySelector('a');
                    if (!a) return;

                    var link = document.createElement('a');
                    link.href = a.href || '#';
                    link.textContent = a.textContent.trim();
                    if (li.classList.contains('active') || li.classList.contains('current')) {
                        link.className = 'ab-active';
                    }

                    link.addEventListener('click', function (e) {
                        fab.classList.remove('ab-open');
                        // 触发原始 tab 链接的 click（兼容 jQuery 绑定的切换处理）
                        a.click();
                        e.preventDefault();
                    });
                    menu.appendChild(link);
                })(liItems[i]);
            }

            // 切换开关
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                fab.classList.toggle('ab-open');
            });

            // 点击外部关闭
            document.addEventListener('click', function () {
                fab.classList.remove('ab-open');
            });

            fab.appendChild(btn);
            fab.appendChild(menu);

            // 插入到 tabList 之前（取代其视觉位置）
            tabList.parentNode.insertBefore(fab, tabList);
        },

        /**
         * 文章预览 MD3 Sheet：
         *   移动端（< 768px）—— 底部 Bottom Sheet
         *   桌面端（≥ 768px）—— 右侧 Modal Side Sheet（可拖拽左边缘调整宽度）
         *
         * 原理：通过 MutationObserver 监听 body 中 .preview-frame 元素的插入，
         * 拦截 Typecho 原生全屏预览，改为自定义 sheet 展示。
         * 不破坏原生的"自动保存后再预览"逻辑。
         */
        initPreviewSheet: function () {
            var href = location.href;
            if (href.indexOf('write-post.php') === -1 && href.indexOf('write-page.php') === -1) return;

            // ── 构建 DOM ──
            var scrim = document.createElement('div');
            scrim.id = 'ab-preview-scrim';
            scrim.className = 'ab-preview-scrim';
            document.body.appendChild(scrim);

            var sheet = document.createElement('div');
            sheet.id = 'ab-preview-sheet';
            sheet.className = 'ab-preview-sheet';
            sheet.innerHTML =
                '<div class="ab-sheet-drag-handle"></div>' +
                '<div class="ab-sheet-header">' +
                    '<span class="ab-sheet-title">预览文章</span>' +
                    '<button class="ab-sheet-close" type="button" aria-label="关闭预览">' +
                        '<i class="material-icons-round">close</i>' +
                    '</button>' +
                '</div>' +
                '<div class="ab-sheet-body">' +
                    '<iframe id="ab-preview-iframe" sandbox="allow-same-origin allow-scripts allow-forms allow-popups" frameborder="0"></iframe>' +
                '</div>' +
                '<div class="ab-sheet-resize-handle"></div>';
            document.body.appendChild(sheet);

            var iframe = document.getElementById('ab-preview-iframe');
            var isOpen = false;

            // ── 打开 / 关闭 ──
            function openSheet(url) {
                iframe.src = url;
                scrim.classList.add('ab-preview-open');
                sheet.classList.add('ab-preview-open');
                document.body.classList.add('ab-preview-active');
                isOpen = true;
            }

            function closeSheet() {
                scrim.classList.remove('ab-preview-open');
                sheet.classList.remove('ab-preview-open');
                document.body.classList.remove('ab-preview-active');
                isOpen = false;
                // 动画结束后清空 iframe，释放内存
                setTimeout(function () {
                    if (!isOpen) iframe.src = 'about:blank';
                }, 400);
            }

            // ── 事件绑定 ──
            scrim.addEventListener('click', closeSheet);
            sheet.querySelector('.ab-sheet-close').addEventListener('click', closeSheet);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && isOpen) closeSheet();
            });

            // ── 桌面端：拖拽左边缘调整 side sheet 宽度 ──
            var resizeHandle = sheet.querySelector('.ab-sheet-resize-handle');
            var resizing = false;
            var resizeStartX = 0;
            var resizeStartW = 0;

            resizeHandle.addEventListener('mousedown', function (e) {
                resizing = true;
                resizeStartX = e.clientX;
                resizeStartW = sheet.offsetWidth;
                e.preventDefault();
                document.body.style.userSelect = 'none';
            });
            document.addEventListener('mousemove', function (e) {
                if (!resizing) return;
                var newW = resizeStartW + (resizeStartX - e.clientX);
                newW = Math.max(320, Math.min(Math.round(window.innerWidth * 0.9), newW));
                sheet.style.width = newW + 'px';
            });
            document.addEventListener('mouseup', function () {
                if (resizing) {
                    resizing = false;
                    document.body.style.userSelect = '';
                }
            });

            // ── 拦截 Typecho 原生预览 ──
            // previewData() 在 write-js.php 闭包内，无法直接覆盖。
            // 改用 MutationObserver 监听 .preview-frame 插入 body，
            // 立即移除原生 iframe 并改用 sheet 显示，保留原有的保存逻辑。
            var previewObserver = new MutationObserver(function (mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    var added = mutations[i].addedNodes;
                    for (var j = 0; j < added.length; j++) {
                        var node = added[j];
                        if (node.nodeType === 1 && node.classList && node.classList.contains('preview-frame')) {
                            // 获取预览 URL
                            var src = node.src || node.getAttribute('src') || '';
                            // 立即移除原生 iframe
                            if (node.parentNode) node.parentNode.removeChild(node);
                            // 移除 Typecho 添加的 fullscreen / preview 类（避免影响编辑器布局）
                            document.body.classList.remove('fullscreen', 'preview');
                            // 打开自定义 sheet
                            if (src) openSheet(src);
                            return;
                        }
                    }
                }
            });
            previewObserver.observe(document.body, { childList: true });
        },

        /**
         * 文章附件选择器：在 write-post/write-page 的附件面板中注入"插入附件"按钮，
         * 点击弹出全屏遮罩弹窗，含三个 Tab：上传 / 本文章 / 全部。
         */
        initAttachPicker: function () {
            var self = this;
            // 通过 Typecho 写作页独有的表单 class 判断当前页面：
            // write-post.php 和 write-page.php 的 <form> 均含 typecho-post-area，
            // 其他管理页面不含此 class，无需硬编码 URL 路径。
            if (!document.querySelector('.typecho-post-area')) return;

            // 等待 #upload-panel 出现（Typecho 异步渲染）
            var maxTry = 40, tried = 0;
            var tryInit = function () {
                var panel = document.getElementById('upload-panel');
                if (!panel) {
                    if (tried++ < maxTry) setTimeout(tryInit, 250);
                    return;
                }
                self._setupAttachPicker(panel);
            };
            tryInit();
        },

        _setupAttachPicker: function (panel) {
            var self = this;
            if (panel.dataset.abPickerInited) return;
            panel.dataset.abPickerInited = '1';

            // 获取当前文章 cid（URL 参数 cid=N 或 表单 input[name=cid]）
            function getPostCid() {
                var m = location.search.match(/[?&]cid=(\d+)/);
                if (m) return parseInt(m[1], 10);
                var inp = document.querySelector('input[name="cid"]');
                if (inp && inp.value) return parseInt(inp.value, 10);
                return 0;
            }

            // ── 隐藏原生上传区域与文件列表（保留 hidden input 用于表单提交）──
            var uploadArea = panel.querySelector('.upload-area');
            if (uploadArea) uploadArea.style.display = 'none';
            var fileList = document.getElementById('file-list');
            if (fileList) fileList.classList.add('ab-ap-hidden-list');

            // ── 更新"附件"tab 上的气泡数字 ──
            function updateNativeBadge() {
                var btn = document.getElementById('tab-files-btn');
                if (!btn) return;
                var count = document.querySelectorAll('#file-list li').length;
                var balloon = btn.querySelector('.balloon');
                // 当标签文本换行时隐藏气泡（避免遮挡）
                var labelWrapped = (function() {
                    var text = btn.textContent || '';
                    // 临时测量：创建 span 包裹文本并比较宽度
                    try {
                        var span = document.createElement('span');
                        span.style.whiteSpace = 'nowrap';
                        span.style.visibility = 'hidden';
                        span.textContent = text.trim();
                        btn.appendChild(span);
                        var wrapped = span.offsetWidth > btn.clientWidth;
                        btn.removeChild(span);
                        return wrapped;
                    } catch(e) { return false; }
                })();

                if (count > 0 && !labelWrapped) {
                    if (!balloon) {
                        balloon = document.createElement('span');
                        balloon.className = 'balloon';
                        btn.appendChild(balloon);
                    }
                    balloon.textContent = count;
                } else if (balloon) {
                    btn.removeChild(balloon);
                }
            }

            // ── 在 panel 顶部注入两个 FAB 按钮（插入附件 + 上传附件）──
            var fabRow = document.createElement('div');
            fabRow.className = 'ab-ap-fab-row';
            var triggerBtn = document.createElement('button');
            triggerBtn.type = 'button';
            triggerBtn.className = 'ab-attach-picker-trigger';
            triggerBtn.innerHTML = '<span class="material-icons-round">attach_file</span><span class="ab-ap-fab-label">插入附件</span>';
            var uploadBtn = document.createElement('button');
            uploadBtn.type = 'button';
            uploadBtn.className = 'ab-attach-picker-trigger ab-ap-upload-fab';
            uploadBtn.innerHTML = '<span class="material-icons-round">upload</span><span class="ab-ap-fab-label">上传附件</span>';
            uploadBtn.title = '点击上传附件或直接将文件拖入窗口';
            fabRow.appendChild(triggerBtn);
            fabRow.appendChild(uploadBtn);
            panel.insertBefore(fabRow, panel.firstChild);

            // 按钮行宽度监测：太窄时改为竖排
            if (window.ResizeObserver) {
                new ResizeObserver(function(entries) {
                    var w = entries[0].contentRect.width;
                    fabRow.classList.toggle('ab-ap-fab-wrap', w < 220);
                }).observe(fabRow);
            }

            // ── 构建弹窗 DOM ──
            var overlay = document.createElement('div');
            overlay.id = 'ab-attach-picker-overlay';
            overlay.className = 'ab-attach-picker-overlay';

            overlay.innerHTML =
                '<div class="ab-ap-dialog" role="dialog" aria-modal="true" aria-label="插入附件">' +
                    '<div class="ab-ap-header">' +
                        '<span class="ab-ap-title">插入附件</span>' +
                        '<button type="button" class="ab-ap-close" aria-label="关闭">' +
                            '<span class="material-icons-round">close</span>' +
                        '</button>' +
                    '</div>' +
                    '<div class="ab-ap-tabs">' +
                        '<button type="button" class="ab-ap-tab" data-tab="article">本文章</button>' +
                        '<button type="button" class="ab-ap-tab" data-tab="upload">上传</button>' +
                        '<button type="button" class="ab-ap-tab" data-tab="all">全部</button>' +
                    '</div>' +
                    '<div class="ab-ap-body">' +
                        // Tab: 本文章
                        '<div class="ab-ap-pane" id="ab-ap-pane-article">' +
                            '<div class="ab-ap-grid" id="ab-ap-article-grid"></div>' +
                            '<div class="ab-ap-empty" id="ab-ap-article-empty" style="display:none">' +
                                '<span class="material-icons-round">folder_open</span>' +
                                '<p>本文章暂无附件</p>' +
                            '</div>' +
                        '</div>' +
                        // Tab: 上传
                        '<div class="ab-ap-pane" id="ab-ap-pane-upload" style="display:none">' +
                            '<div class="ab-ap-upload-zone" id="ab-ap-dropzone">' +
                                '<span class="material-icons-round ab-ap-upload-icon">cloud_upload</span>' +
                                '<p>将文件拖拽至此，或<button type="button" class="ab-ap-select-btn">点击选择文件</button></p>' +
                                '<p class="ab-ap-upload-sub">支持多文件，Ctrl/Cmd 多选</p>' +
                                '<input type="file" id="ab-ap-file-input" multiple style="display:none">' +
                            '</div>' +
                            '<ul class="ab-ap-upload-list" id="ab-ap-upload-list"></ul>' +
                            '<div class="ab-ap-upload-footer">' +
                                '<button type="button" class="ab-ap-clear-btn" id="ab-ap-clear-btn">清空列表</button>' +
                                '<button type="button" class="ab-ap-submit-btn" id="ab-ap-submit-btn" disabled>开始上传</button>' +
                            '</div>' +
                        '</div>' +
                        // Tab: 全部
                        '<div class="ab-ap-pane" id="ab-ap-pane-all" style="display:none">' +
                            '<div class="ab-ap-all-toolbar">' +
                                '<div class="ab-ap-search-wrap">' +
                                    '<span class="material-icons-round ab-ap-search-icon">search</span>' +
                                    '<input type="text" class="ab-ap-search-input" id="ab-ap-search" placeholder="搜索文件名…" autocomplete="off">' +
                                '</div>' +
                            '</div>' +
                            '<div class="ab-ap-grid" id="ab-ap-all-grid"></div>' +
                            '<div class="ab-ap-empty" id="ab-ap-all-empty" style="display:none">' +
                                '<span class="material-icons-round">folder_open</span>' +
                                '<p>暂无文件</p>' +
                            '</div>' +
                            '<div class="ab-ap-pagination" id="ab-ap-pagination"></div>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            document.body.appendChild(overlay);

            var dialog   = overlay.querySelector('.ab-ap-dialog');
            var tabs     = overlay.querySelectorAll('.ab-ap-tab');
            var panes    = { article: document.getElementById('ab-ap-pane-article'), upload: document.getElementById('ab-ap-pane-upload'), all: document.getElementById('ab-ap-pane-all') };
            var curTab   = 'article';

            // ── Tab 切换 ──
            function switchTab(name) {
                curTab = name;
                [].forEach.call(tabs, function (t) {
                    t.classList.toggle('ab-ap-tab-active', t.dataset.tab === name);
                });
                Object.keys(panes).forEach(function (k) {
                    panes[k].style.display = k === name ? '' : 'none';
                });
                if (name === 'article') loadArticleFiles();
                if (name === 'all')     {
                    allPage = 1; allSearch = '';
                    document.getElementById('ab-ap-search').value = '';
                    // 先渲染出 pane，再量高度算每页数量，然后加载
                    requestAnimationFrame(function() {
                        var newPer = calcAllPer();
                        if (newPer !== allPer) { allPer = newPer; }
                        loadAllFiles();
                    });
                }
            }

            [].forEach.call(tabs, function (t) {
                t.addEventListener('click', function () { switchTab(t.dataset.tab); });
            });

            // ── 关闭 ──
            function closeOverlay() {
                overlay.classList.add('ab-ap-overlay-out');
                document.body.style.overflow = '';
                document.body.style.touchAction = '';
                setTimeout(function () { overlay.classList.remove('ab-ap-overlay-in', 'ab-ap-overlay-out'); }, 250);
            }
            overlay.querySelector('.ab-ap-close').addEventListener('click', closeOverlay);
            overlay.addEventListener('click', function (e) { if (e.target === overlay) closeOverlay(); });
            document.addEventListener('keydown', function escAP(e) {
                if (e.key === 'Escape' && overlay.classList.contains('ab-ap-overlay-in')) {
                    closeOverlay();
                }
            });

            // ── 打开 ──
            function openOverlay(tab) {
                overlay.classList.add('ab-ap-overlay-in');
                overlay.classList.remove('ab-ap-overlay-out');
                document.body.style.overflow = 'hidden';
                document.body.style.touchAction = 'none';
                switchTab(tab || 'article');
            }

            triggerBtn.addEventListener('click', function () { openOverlay('article'); });
            uploadBtn.addEventListener('click', function () { openOverlay('upload'); });

            // ── 全页面拖拽文件 → 自动打开"上传"选项卡 ──
            var dragCounter = 0;
            var dropHint = document.createElement('div');
            dropHint.id = 'ab-ap-drop-hint';
            dropHint.className = 'ab-ap-drop-hint';
            dropHint.innerHTML =
                '<span class="material-icons-round">cloud_upload</span>' +
                '<span>松开以上传附件</span>';
            document.body.appendChild(dropHint);

            document.addEventListener('dragenter', function (e) {
                if (!e.dataTransfer || !e.dataTransfer.types) return;
                var hasFile = [].indexOf.call(e.dataTransfer.types, 'Files') !== -1 ||
                              [].indexOf.call(e.dataTransfer.types, 'application/x-moz-file') !== -1;
                if (!hasFile) return;
                dragCounter++;
                if (dragCounter === 1) dropHint.classList.add('ab-ap-drop-hint-show');
            });
            document.addEventListener('dragleave', function (e) {
                dragCounter--;
                if (dragCounter <= 0) { dragCounter = 0; dropHint.classList.remove('ab-ap-drop-hint-show'); }
            });
            document.addEventListener('dragover', function (e) { e.preventDefault(); });
            document.addEventListener('drop', function (e) {
                dragCounter = 0;
                dropHint.classList.remove('ab-ap-drop-hint-show');
                if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files.length) return;
                // 如果落点在弹窗内的 dropzone，不拦截（由 dropzone 自己处理）
                if (e.target && e.target.closest && e.target.closest('#ab-ap-dropzone')) return;
                e.preventDefault();
                // 打开弹窗"上传"tab，并把文件传给 addUploadFiles
                openOverlay('upload');
                // addUploadFiles 在后面定义，用 setTimeout 等它就绪
                setTimeout(function () {
                    if (typeof addUploadFiles === 'function') addUploadFiles(e.dataTransfer.files);
                }, 50);
            });

            // ── 工具函数 ──
            function escHtml(s) { return String(s).replace(/[&<>"']/g, function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];}); }
            function fmtSize(b) { if (b < 1024) return b + ' B'; if (b < 1048576) return (b/1024).toFixed(1) + ' KB'; return (b/1048576).toFixed(2) + ' MB'; }
            function fmtDate(ts) { var d = new Date(ts * 1000); return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'); }
            function mimeIcon(mime) {
                if (!mime) return {icon:'insert_drive_file', color:'var(--md-on-surface-variant)'};
                if (mime.indexOf('image/') === 0) return {icon:'image', color:'#4caf50'};
                if (mime.indexOf('video/') === 0) return {icon:'videocam', color:'#2196f3'};
                if (mime.indexOf('audio/') === 0) return {icon:'headphones', color:'#9c27b0'};
                if (mime === 'application/pdf') return {icon:'picture_as_pdf', color:'#f44336'};
                if (mime.indexOf('zip') !== -1 || mime.indexOf('rar') !== -1 || mime.indexOf('7z') !== -1 || mime.indexOf('tar') !== -1) return {icon:'archive', color:'#ff9800'};
                if (mime.indexOf('text/') === 0 || mime.indexOf('javascript') !== -1 || mime.indexOf('json') !== -1) return {icon:'code', color:'#00bcd4'};
                return {icon:'insert_drive_file', color:'var(--md-on-surface-variant)'};
            }

            // 插入文件到编辑器（优先直接写入 Vditor/textarea，避免触发 PageDown 弹窗链路）
            function insertFile(url, name, mime) {
                var isImage = !!(mime && mime.indexOf('image/') === 0);
                var safeName = String(name || (isImage ? 'image' : 'file'))
                    .replace(/\\/g, '\\\\')
                    .replace(/\[/g, '\\[')
                    .replace(/\]/g, '\\]');
                var safeUrl = String(url || '')
                    .replace(/\s/g, '%20')
                    .replace(/\(/g, '%28')
                    .replace(/\)/g, '%29')
                    .trim();
                if (!safeUrl) return;

                var tag = isImage ? '![' + safeName + '](' + safeUrl + ')' : '[' + safeName + '](' + safeUrl + ')';

                function fireInputEvent(ta) {
                    try {
                        var evt = document.createEvent('Event');
                        evt.initEvent('input', true, true);
                        ta.dispatchEvent(evt);
                    } catch (e) {}
                }

                function insertToTextarea(text) {
                    var ta = document.getElementById('text');
                    if (!ta || typeof ta.value === 'undefined') return false;

                    var value = String(ta.value || '');
                    var start = (typeof ta.selectionStart === 'number') ? ta.selectionStart : value.length;
                    var end = (typeof ta.selectionEnd === 'number') ? ta.selectionEnd : start;
                    if (start > end) {
                        var tmp = start;
                        start = end;
                        end = tmp;
                    }

                    ta.value = value.slice(0, start) + text + value.slice(end);
                    ta.selectionStart = ta.selectionEnd = start + text.length;
                    ta.focus();
                    fireInputEvent(ta);
                    return true;
                }

                function getVditorInstance() {
                    if (window.__abVditor) return window.__abVditor;
                    if (window.vditor && (typeof window.vditor.insertMD === 'function' || typeof window.vditor.insertValue === 'function')) {
                        return window.vditor;
                    }
                    return null;
                }

                function insertToVditor(text) {
                    var vd = getVditorInstance();
                    if (!vd) return false;

                    try { if (typeof vd.focus === 'function') vd.focus(); } catch (e0) {}

                    try {
                        if (typeof vd.insertMD === 'function') {
                            vd.insertMD(text);
                            if (typeof vd.getValue === 'function') {
                                var ta = document.getElementById('text');
                                if (ta) {
                                    ta.value = vd.getValue();
                                    fireInputEvent(ta);
                                }
                            }
                            return true;
                        }
                    } catch (e1) {}

                    try {
                        if (typeof vd.insertValue === 'function') {
                            vd.insertValue(text, true);
                            if (typeof vd.getValue === 'function') {
                                var ta2 = document.getElementById('text');
                                if (ta2) {
                                    ta2.value = vd.getValue();
                                    fireInputEvent(ta2);
                                }
                            }
                            return true;
                        }
                    } catch (e2) {}

                    return false;
                }

                // 关闭弹窗后插入，避免焦点被遮罩抢占
                closeOverlay();
                setTimeout(function () {
                    if (insertToVditor(tag)) return;
                    if (insertToTextarea(tag)) return;

                    // 兜底：仅在前两种方案都不可用时才调用 Typecho 原生弹窗
                    if (typeof Typecho !== 'undefined' && typeof Typecho.insertFileToEditor === 'function') {
                        try {
                            Typecho.insertFileToEditor(name, url, isImage ? 1 : 0);
                        } catch (e3) {
                            insertToTextarea(tag);
                        }
                    }
                }, 60);
            }

            // 构建单个文件卡片
            function buildCard(item) {
                var ic = mimeIcon(item.mime);
                var isImg = item.mime && item.mime.indexOf('image/') === 0;
                var card = document.createElement('div');
                card.className = 'ab-ap-card';
                card.dataset.cid = item.cid;

                var preview = '';
                if (isImg) {
                    preview = '<div class="ab-ap-card-img-wrap"><img class="ab-ap-card-img" src="' + escHtml(item.url) + '" alt="" loading="lazy"></div>';
                } else {
                    preview = '<div class="ab-ap-card-icon-wrap"><span class="material-icons-round" style="color:' + ic.color + '">' + ic.icon + '</span></div>';
                }

                card.innerHTML =
                    preview +
                    '<div class="ab-ap-card-info">' +
                        '<span class="ab-ap-card-name" title="' + escHtml(item.name) + '">' + escHtml(item.name) + '</span>' +
                        '<span class="ab-ap-card-meta">' + fmtSize(item.size) + (item.created ? ' · ' + fmtDate(item.created) : '') + '</span>' +
                    '</div>' +
                    '<div class="ab-ap-card-actions">' +
                        '<button type="button" class="ab-ap-card-insert" data-url="' + escHtml(item.url) + '" data-name="' + escHtml(item.name) + '" data-mime="' + escHtml(item.mime||'') + '" title="插入">' +
                            '<span class="material-icons-round">add_link</span>' +
                        '</button>' +
                        '<button type="button" class="ab-ap-card-delete" data-cid="' + item.cid + '" title="删除">' +
                            '<span class="material-icons-round">delete</span>' +
                        '</button>' +
                    '</div>';

                return card;
            }

            // 渲染卡片列表到容器
            function renderCards(container, emptyEl, items) {
                container.innerHTML = '';
                if (!items.length) {
                    emptyEl.style.display = '';
                    return;
                }
                emptyEl.style.display = 'none';
                items.forEach(function (item) {
                    container.appendChild(buildCard(item));
                });
                // 绑定卡片内按钮事件
                container.querySelectorAll('.ab-ap-card-insert').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        insertFile(btn.dataset.url, btn.dataset.name, btn.dataset.mime);
                    });
                });
                container.querySelectorAll('.ab-ap-card-delete').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var cid = parseInt(btn.dataset.cid, 10);
                        if (!cid) return;
                        if (!confirm('确认删除此附件？此操作不可撤销。')) return;
                        deleteFile(cid, btn.closest('.ab-ap-card'));
                    });
                });
            }

            // API 请求封装
            function apiGet(doName, params, cb) {
                var cfg = window.__AB_AJAX__;
                if (!cfg || !cfg.url) { cb(null, '未找到 AJAX 配置'); return; }
                var url = cfg.url + '?do=' + doName;
                if (cfg.token) url += '&_=' + encodeURIComponent(cfg.token);
                Object.keys(params).forEach(function (k) { url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); });
                var xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) return;
                    try { var r = JSON.parse(xhr.responseText); cb(r.code === 0 ? r.data : null, r.code !== 0 ? r.message : null); }
                    catch(e) { cb(null, '响应解析失败'); }
                };
                xhr.send();
            }
            function apiPost(doName, params, cb) {
                var cfg = window.__AB_AJAX__;
                if (!cfg || !cfg.url) { cb(null, '未找到 AJAX 配置'); return; }
                var url = cfg.url + '?do=' + doName;
                if (cfg.token) url += '&_=' + encodeURIComponent(cfg.token);
                var fd = new FormData();
                Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
                var xhr = new XMLHttpRequest();
                xhr.open('POST', url, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) return;
                    try { var r = JSON.parse(xhr.responseText); cb(r.code === 0 ? r.data : null, r.code !== 0 ? r.message : null); }
                    catch(e) { cb(null, '响应解析失败'); }
                };
                xhr.send(fd);
            }

            // ── Tab: 本文章 ──
            var articleLoaded = false;
            function loadArticleFiles() {
                var cid = getPostCid();
                var grid  = document.getElementById('ab-ap-article-grid');
                var empty = document.getElementById('ab-ap-article-empty');
                grid.innerHTML = '<div class="ab-ap-loading"><span class="material-icons-round ab-ap-spin">sync</span></div>';
                empty.style.display = 'none';
                apiGet('list-media', { parent: cid, per: 100 }, function (data, err) {
                    if (err || !data) { grid.innerHTML = '<p class="ab-ap-err">加载失败：' + escHtml(err||'未知错误') + '</p>'; return; }
                    renderCards(grid, empty, data.items || []);
                    articleLoaded = true;
                });
            }

            // ── Tab: 全部 ──
            var allPage = 1, allSearch = '', allTotal = 0, allPer = 12;
            var allSearchTimer = null;

            // 根据 grid 实际可用高度动态计算每页卡片数
            function calcAllPer() {
                var pane = document.getElementById('ab-ap-pane-all');
                var grid = document.getElementById('ab-ap-all-grid');
                var pag  = document.getElementById('ab-ap-pagination');
                var toolbar = pane ? pane.querySelector('.ab-ap-all-toolbar') : null;
                if (!pane || !grid) return 12;

                // pane 可用高度 = pane 高度 - toolbar - pagination
                var paneH = pane.clientHeight || 500;
                var toolbarH = toolbar ? toolbar.offsetHeight : 70;
                var pagH = pag ? pag.offsetHeight : 56;
                var gridH = paneH - toolbarH - pagH - 16; // 16px 余量
                if (gridH < 100) gridH = 300; // 兜底

                // 卡片列数（根据 grid 宽度和 minmax(170px)）
                var gridW = grid.clientWidth || 600;
                var cols = Math.max(1, Math.floor((gridW + 12) / (170 + 12)));

                // 单张卡片高度：4:3 图标区 + info + padding ≈ 127 * (gridW/cols/gridW) + 95
                // 实际卡片宽 = gridW / cols（近似）
                var cardW = Math.floor((gridW - 12 * (cols - 1)) / cols);
                var cardH = Math.round(cardW * 3 / 4) + 95; // 4:3 预览区 + info(~50) + actions(~45)
                var rows  = Math.max(1, Math.floor((gridH + 12) / (cardH + 12)));
                return rows * cols;
            }
            // 监听全部面板尺寸变化，动态重新计算每页数量并在变化时刷新文件列表
            (function() {
                var pane = document.getElementById('ab-ap-pane-all');
                if (!pane) return;
                var prevPer = allPer;
                function checkAndReload() {
                    var newPer = calcAllPer();
                    if (newPer !== prevPer) {
                        prevPer = newPer;
                        allPer = newPer;
                        if (curTab === 'all') loadAllFiles();
                    }
                }
                if (window.ResizeObserver) {
                    try {
                        new ResizeObserver(function() { checkAndReload(); }).observe(pane);
                    } catch(e) { window.addEventListener('resize', checkAndReload); }
                } else {
                    window.addEventListener('resize', checkAndReload);
                }
            })();
            function loadAllFiles() {
                var grid  = document.getElementById('ab-ap-all-grid');
                var empty = document.getElementById('ab-ap-all-empty');
                grid.innerHTML = '<div class="ab-ap-loading"><span class="material-icons-round ab-ap-spin">sync</span></div>';
                empty.style.display = 'none';
                apiGet('list-media', { parent: -1, page: allPage, per: allPer, search: allSearch }, function (data, err) {
                    if (err || !data) { grid.innerHTML = '<p class="ab-ap-err">加载失败：' + escHtml(err||'未知错误') + '</p>'; return; }
                    allTotal = data.total || 0;
                    renderCards(grid, empty, data.items || []);
                    renderPagination(allTotal, allPage, allPer);
                });
            }

            // 分页
            function renderPagination(total, page, per) {
                var pag = document.getElementById('ab-ap-pagination');
                pag.innerHTML = '';
                var totalPages = Math.max(1, Math.ceil(total / per));
                if (totalPages <= 1) return;

                function mkBtn(label, p, disabled, active) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'ab-ap-page-btn' + (active ? ' ab-ap-page-btn-active' : '');
                    btn.textContent = label;
                    btn.disabled = !!disabled;
                    btn.addEventListener('click', function () { allPage = p; loadAllFiles(); });
                    return btn;
                }

                pag.appendChild(mkBtn('‹', page - 1, page <= 1, false));
                var start = Math.max(1, page - 2), end = Math.min(totalPages, page + 2);
                if (start > 1) { pag.appendChild(mkBtn('1', 1, false, false)); if (start > 2) { var sp = document.createElement('span'); sp.className='ab-ap-page-sep'; sp.textContent='…'; pag.appendChild(sp); } }
                for (var i = start; i <= end; i++) pag.appendChild(mkBtn(i, i, false, i === page));
                if (end < totalPages) { if (end < totalPages - 1) { var sp2 = document.createElement('span'); sp2.className='ab-ap-page-sep'; sp2.textContent='…'; pag.appendChild(sp2); } pag.appendChild(mkBtn(totalPages, totalPages, false, false)); }
                pag.appendChild(mkBtn('›', page + 1, page >= totalPages, false));
            }

            // 搜索防抖
            document.getElementById('ab-ap-search').addEventListener('input', function (e) {
                clearTimeout(allSearchTimer);
                allSearch = e.target.value.trim();
                allPage = 1;
                allSearchTimer = setTimeout(loadAllFiles, 400);
            });

            // ── 删除文件 ──
            function deleteFile(cid, cardEl) {
                apiPost('delete-media', { cid: cid }, function (data, err) {
                    if (err) { AdminBeautify.showNotice('删除失败：' + err, 'error', 4000); return; }
                    if (cardEl) cardEl.remove();
                    // 同步刷新当前 tab 的计数
                    if (curTab === 'article') {
                        var grid = document.getElementById('ab-ap-article-grid');
                        if (grid && !grid.querySelectorAll('.ab-ap-card').length) {
                            document.getElementById('ab-ap-article-empty').style.display = '';
                        }
                    } else if (curTab === 'all') {
                        allTotal = Math.max(0, allTotal - 1);
                        var totalPages = Math.ceil(allTotal / allPer);
                        if (allPage > totalPages && allPage > 1) allPage = totalPages;
                        loadAllFiles();
                    }
                    AdminBeautify.showNotice('附件已删除', 'success', 2000);
                    // 同步原生 file-list 面板（若附件归属本文章）
                    var li = document.querySelector('#file-list li[data-cid="' + cid + '"]');
                    if (li) li.remove();
                    updateNativeBadge();
                });
            }

            // ── Tab: 上传 ──
            var pendingUploadFiles = [];
            function genUploadId() { return 'u' + Math.random().toString(36).slice(2, 8); }
            function fmtSz(b) { return fmtSize(b); }

            function renderUploadItem(id, file) {
                var li = document.createElement('li');
                li.className = 'ab-ap-upload-item';
                li.id = 'ab-ap-uitem-' + id;
                li.innerHTML =
                    '<span class="material-icons-round ab-ap-uitem-icon">insert_drive_file</span>' +
                    '<span class="ab-ap-uitem-name" title="' + escHtml(file.name) + '">' + escHtml(file.name) + '</span>' +
                    '<span class="ab-ap-uitem-size">' + fmtSz(file.size) + '</span>' +
                    '<span class="ab-ap-uitem-status" id="ab-ap-ustatus-' + id + '"></span>' +
                    '<button type="button" class="ab-ap-uitem-remove" data-id="' + id + '" aria-label="移除"><span class="material-icons-round">close</span></button>';
                return li;
            }

            function addUploadFiles(files) {
                var list = document.getElementById('ab-ap-upload-list');
                for (var i = 0; i < files.length; i++) {
                    var id = genUploadId();
                    var item = { file: files[i], id: id };
                    pendingUploadFiles.push(item);
                    var li = renderUploadItem(id, files[i]);
                    item.itemEl = li;
                    list.appendChild(li);
                }
                document.getElementById('ab-ap-submit-btn').disabled = pendingUploadFiles.length === 0;
            }

            document.getElementById('ab-ap-upload-list').addEventListener('click', function (e) {
                var btn = e.target.closest('.ab-ap-uitem-remove');
                if (!btn) return;
                var id = btn.dataset.id;
                pendingUploadFiles = pendingUploadFiles.filter(function (it) { return it.id !== id; });
                var li = document.getElementById('ab-ap-uitem-' + id);
                if (li) li.remove();
                document.getElementById('ab-ap-submit-btn').disabled = pendingUploadFiles.length === 0;
            });

            var fileInput2 = document.getElementById('ab-ap-file-input');
            var dropzone2  = document.getElementById('ab-ap-dropzone');
            dropzone2.querySelector('.ab-ap-select-btn').addEventListener('click', function () { fileInput2.click(); });
            fileInput2.addEventListener('change', function () { if (fileInput2.files.length) addUploadFiles(fileInput2.files); fileInput2.value = ''; });
            dropzone2.addEventListener('dragover', function (e) { e.preventDefault(); dropzone2.classList.add('ab-ap-dropzone-drag'); });
            dropzone2.addEventListener('dragleave', function (e) { if (!dropzone2.contains(e.relatedTarget)) dropzone2.classList.remove('ab-ap-dropzone-drag'); });
            dropzone2.addEventListener('drop', function (e) { e.preventDefault(); dropzone2.classList.remove('ab-ap-dropzone-drag'); if (e.dataTransfer && e.dataTransfer.files.length) addUploadFiles(e.dataTransfer.files); });

            document.getElementById('ab-ap-clear-btn').addEventListener('click', function () {
                pendingUploadFiles = [];
                document.getElementById('ab-ap-upload-list').innerHTML = '';
                document.getElementById('ab-ap-submit-btn').disabled = true;
            });

            document.getElementById('ab-ap-submit-btn').addEventListener('click', function () {
                if (!pendingUploadFiles.length) return;
                var submitBtn = document.getElementById('ab-ap-submit-btn');
                submitBtn.disabled = true;
                submitBtn.textContent = '上传中…';

                var cfg = window.__AB_AJAX__;
                if (!cfg || !cfg.url) { AdminBeautify.showNotice('AJAX 配置未找到', 'error', 3000); submitBtn.disabled = false; submitBtn.textContent = '开始上传'; return; }

                var postCid = getPostCid();
                var url = cfg.url + '?do=upload-media&parent=' + postCid;
                if (cfg.token) url += '&_=' + encodeURIComponent(cfg.token);

                var fd = new FormData();
                pendingUploadFiles.forEach(function (item) {
                    fd.append('files[]', item.file);
                    var st = document.getElementById('ab-ap-ustatus-' + item.id);
                    if (st) { st.className = 'ab-ap-uitem-status ab-ap-uitem-status-pending'; st.textContent = '等待中'; }
                });

                var xhr = new XMLHttpRequest();
                xhr.open('POST', url, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) submitBtn.textContent = '上传中 ' + Math.round(e.loaded/e.total*100) + '%…';
                });
                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) return;
                    submitBtn.disabled = false;
                    submitBtn.textContent = '开始上传';
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        var data = (resp.code === 0 && resp.data) ? resp.data : {};
                        var uploaded = data.uploaded || [], failed = data.failed || [];

                        uploaded.forEach(function (u) {
                            // 更新状态
                            pendingUploadFiles.forEach(function (pi) {
                                if (pi.file.name === u.name) {
                                    var st = document.getElementById('ab-ap-ustatus-' + pi.id);
                                    if (st) { st.className='ab-ap-uitem-status ab-ap-uitem-status-ok'; st.textContent='成功'; }
                                }
                            });
                            // 同步原生 file-list（保持 Typecho 表单的 hidden input）
                            var nativeLi = document.createElement('li');
                            nativeLi.dataset.cid = u.cid;
                            nativeLi.dataset.url = u.url;
                            nativeLi.dataset.image = (u.mime && u.mime.indexOf('image/')===0) ? '1' : '0';
                            nativeLi.innerHTML = '<input type="hidden" name="attachment[]" value="' + u.cid + '">';
                            var nativeList = document.getElementById('file-list');
                            if (nativeList) nativeList.appendChild(nativeLi);
                        });
                        updateNativeBadge();
                        if (failed.length > 0) AdminBeautify.showNotice('以下文件失败：' + failed.join('、'), 'error', 6000);

                        var successNames = {};
                        uploaded.forEach(function (u) { successNames[u.name] = true; });
                        pendingUploadFiles = pendingUploadFiles.filter(function (it) { return !successNames[it.file.name]; });
                        document.getElementById('ab-ap-submit-btn').disabled = pendingUploadFiles.length === 0;

                        if (uploaded.length > 0) {
                            AdminBeautify.showNotice('上传成功 ' + uploaded.length + ' 个文件', 'success', 2500);
                            // 刷新本文章 tab
                            articleLoaded = false;
                            if (curTab === 'article') loadArticleFiles();
                        }
                    } catch(e) {
                        AdminBeautify.showNotice('上传响应解析失败', 'error', 4000);
                    }
                };
                xhr.send(fd);
            });
        },

        /**
         * Editor Toolbar：write-post/write-page 的 MD3 pill 工具栏 + 溢出"更多"菜单
         */
        initEditorToolbar: function () {
            var self = this;
            var href = location.href;
            if (href.indexOf('write-post.php') === -1 && href.indexOf('write-page.php') === -1) return;

            var trySetup = function () {
                var row = document.getElementById('wmd-button-row');
                if (row) { self._setupEditorToolbar(row); return true; }
                return false;
            };

            if (!trySetup()) {
                var obs = new MutationObserver(function (mutations, ob) {
                    if (trySetup()) ob.disconnect();
                });
                obs.observe(document.body, { childList: true, subtree: true });
                setTimeout(function () { obs.disconnect(); }, 10000);
            }
        },

        _setupEditorToolbar: function (row) {
            var self = this;
            var bar = document.getElementById('wmd-button-bar');
            if (!bar || bar.classList.contains('ab-toolbar-ready')) return;
            bar.classList.add('ab-toolbar-ready');
            // 写文章页面动画：给 body 打上 ab-write-page 类，CSS 动画依赖此类
            document.body.classList.add('ab-write-page');

            // 清除 pagedown 注入的 inline left: 样式
            var allLi = row.querySelectorAll('li');
            for (var i = 0; i < allLi.length; i++) {
                allLi[i].style.left = '';
            }

            // 替换 sprite span 为 Material Icons
            var iconMap = {
                'wmd-bold-button':            'format_bold',
                'wmd-italic-button':          'format_italic',
                'wmd-link-button':            'link',
                'wmd-quote-button':           'format_quote',
                'wmd-code-button':            'code',
                'wmd-image-button':           'image',
                'wmd-olist-button':           'format_list_numbered',
                'wmd-ulist-button':           'format_list_bulleted',
                'wmd-heading-button':         'title',
                'wmd-hr-button':              'horizontal_rule',
                'wmd-more-button':            'read_more',
                'wmd-undo-button':            'undo',
                'wmd-redo-button':            'redo',
                'wmd-fullscreen-button':      'fullscreen',
                'wmd-exit-fullscreen-button': 'fullscreen_exit'
            };
            var buttons = row.querySelectorAll('li.wmd-button');
            for (var b = 0; b < buttons.length; b++) {
                var iconName = iconMap[buttons[b].id];
                if (iconName) {
                    buttons[b].innerHTML = '<i class="material-icons-round">' + iconName + '</i>';
                }
            }

            // 创建内层 pill 容器（包裹 row + moreBtn），edittab 单独在右侧
            var pill = document.createElement('div');
            pill.className = 'ab-inner-pill';
            bar.insertBefore(pill, row);  // 插入到 row 当前位置之前
            pill.appendChild(row);         // 将 row 移入 pill

            // 将撤销/重做按钮移出 row，固定在 pill 右侧（始终可见，不参与溢出检测）
            var undoLi = row.querySelector('#wmd-undo-button');
            var redoLi = row.querySelector('#wmd-redo-button');
            // 在移走 undoLi 之前记录其前一个 spacer，移走后需隐藏以防悬挂
            var spacerBeforeUndo = (undoLi && undoLi.previousElementSibling &&
                undoLi.previousElementSibling.classList.contains('wmd-spacer'))
                ? undoLi.previousElementSibling : null;
            var fixedBtns = document.createElement('ul');
            fixedBtns.className = 'ab-fixed-btns';
            if (undoLi) fixedBtns.appendChild(undoLi);
            if (redoLi) fixedBtns.appendChild(redoLi);

            // 保存草稿按钮：直接 POST FormData+do=save，绕过 changed 标志检查
            var saveLi = document.createElement('li');
            saveLi.className = 'wmd-button ab-save-btn';
            saveLi.title = '保存草稿';
            saveLi.innerHTML = '<i class="material-icons-round">save</i>';
            (function (btn) {
                btn.addEventListener('click', function () {
                    var form = document.querySelector('form[name=write_post], form[name=write_page]');
                    if (!form) return;
                    var icon = btn.querySelector('i');
                    icon.textContent = 'hourglass_empty';
                    btn.style.opacity = '0.6';
                    btn.style.pointerEvents = 'none';
                    var fd = new FormData(form);
                    fd.append('do', 'save');
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', form.action, true);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.onload = function () {
                        icon.textContent = 'save';
                        btn.style.opacity = '';
                        btn.style.pointerEvents = '';
                        var msg = '已保存';
                        try {
                            var res = JSON.parse(xhr.responseText);
                            if (res && res.time) msg = '已保存 (' + res.time + ')';
                            // 同步更新页面底部自动保存文字
                            var autoSaveEl = document.getElementById('auto-save-message');
                            if (autoSaveEl) autoSaveEl.textContent = msg;
                        } catch (e) {}
                        // MD3 Snackbar 提示
                        var toast = document.createElement('div');
                        toast.className = 'ab-save-toast';
                        toast.textContent = msg;
                        document.body.appendChild(toast);
                        // 触发进入动画（下一帧）
                        requestAnimationFrame(function () {
                            toast.classList.add('ab-save-toast-show');
                        });
                        setTimeout(function () {
                            toast.classList.remove('ab-save-toast-show');
                            setTimeout(function () {
                                if (toast.parentNode) toast.parentNode.removeChild(toast);
                            }, 300);
                        }, 2200);
                    };
                    xhr.onerror = function () {
                        icon.textContent = 'save';
                        btn.style.opacity = '';
                        btn.style.pointerEvents = '';
                    };
                    xhr.send(fd);
                });
            })(saveLi);
            fixedBtns.appendChild(saveLi);

            pill.appendChild(fixedBtns);
            // 隐藏 undo 前的悬挂 spacer
            if (spacerBeforeUndo) {
                spacerBeforeUndo.style.setProperty('display', 'none', 'important');
            }
            // 隐藏 row 尾部悬挂的 spacer（跳过已被 CSS display:none 的元素，如全屏按钮）
            var tailLi = row.lastElementChild;
            while (tailLi) {
                if (window.getComputedStyle(tailLi).display === 'none') {
                    tailLi = tailLi.previousElementSibling;
                    continue;
                }
                if (tailLi.classList.contains('wmd-spacer')) {
                    tailLi.style.setProperty('display', 'none', 'important');
                    tailLi = tailLi.previousElementSibling;
                } else {
                    break;
                }
            }

            // 创建"更多"按钮（pill 内，row 的 flex 兄弟）
            var moreBtn = document.createElement('button');
            moreBtn.type = 'button';
            moreBtn.id = 'ab-wmd-more-btn';
            moreBtn.title = '更多';
            moreBtn.innerHTML = '<i class="material-icons-round">more_vert</i>';
            pill.appendChild(moreBtn);

            // 溢出菜单（pill 内绝对定位，right:0 相对 pill 右对齐）
            var moreMenu = document.createElement('div');
            moreMenu.className = 'ab-toolbar-more-menu';
            pill.appendChild(moreMenu);

            // 将 edittab 移到 bar 末尾（独立右侧 pill，与工具栏 pill 并排）
            var edittab = bar.querySelector('.wmd-edittab');
            if (edittab) { bar.appendChild(edittab); }

            // 切换菜单
            moreBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                moreMenu.classList.toggle('ab-open');
            });
            document.addEventListener('click', function () {
                moreMenu.classList.remove('ab-open');
            });

            // 撰写/预览 tab 切换：预览模式下隐藏工具栏 pill
            // 注意：.ab-inner-pill 有 display:flex !important，必须用 setProperty('display','none','important')
            // 才能覆盖，否则 pill.style.display='none' 无效，导致切到预览时 pill 仍占据空间形成大片空白
            if (edittab) {
                var tabLinks = edittab.querySelectorAll('a');
                for (var t = 0; t < tabLinks.length; t++) {
                    (function (link, idx) {
                        link.addEventListener('click', function () {
                            var isPreview = (idx > 0);
                            if (isPreview) {
                                pill.style.setProperty('display', 'none', 'important');
                                bar.classList.add('ab-preview-mode');
                            } else {
                                pill.style.removeProperty('display');
                                bar.classList.remove('ab-preview-mode');
                            }
                        });
                    })(tabLinks[t], t);
                }
            }

            // 全屏编辑按钮（edittab 右侧，圆角方形）
            // 直接使用 bar 的直接父元素（编辑器列 div），避免 closest('form') 选中整个含侧边栏的表单
            var fsContainer = bar.parentElement;
            var fsBtn = document.createElement('button');
            fsBtn.type = 'button';
            fsBtn.id = 'ab-editor-fs-btn';
            fsBtn.title = '全屏编辑';
            fsBtn.innerHTML = '<i class="material-icons-round">open_in_full</i>';
            bar.appendChild(fsBtn);

            var toggleFs = function (forceExit) {
                var entering = forceExit ? false : !document.body.classList.contains('ab-editor-fs');
                // 注意：ab-editor-fs 类的添加/移除均延迟到动画时机处理，不在此处统一 toggle
                var icon = fsBtn.querySelector('i');
                if (entering) {
                    // ── 进入全屏 ──
                    document.body.classList.add('ab-editor-fs');
                    icon.textContent = 'close_fullscreen';
                    fsBtn.title = '退出全屏';

                    // 记录全屏按钮中心坐标作为动画原点
                    var btnRectIn = fsBtn.getBoundingClientRect();
                    var originXIn = Math.round(btnRectIn.left + btnRectIn.width / 2);
                    var originYIn = Math.round(btnRectIn.top + btnRectIn.height / 2);

                    // 进入全屏：创建 body 级覆盖层并移入编辑器列
                    // 挂在 body 下可避免 flex/transform 祖先影响 fixed 定位
                    var fsOverlay = document.createElement('div');
                    fsOverlay.id = 'ab-editor-fs-overlay';
                    fsOverlay.className = 'ab-fs-overlay ab-fs-entering';
                    fsOverlay.style.transformOrigin = originXIn + 'px ' + originYIn + 'px';

                    var fsPlaceholder = document.createElement('div');
                    fsPlaceholder.id = 'ab-editor-fs-placeholder';
                    fsPlaceholder.style.cssText = 'display:none;visibility:hidden;height:0;overflow:hidden;';
                    fsContainer.parentNode.insertBefore(fsPlaceholder, fsContainer);
                    fsOverlay.appendChild(fsContainer);
                    document.body.appendChild(fsOverlay);

                    // 恢复所有因溢出检测被隐藏的按钮
                    var overflowItems = row.querySelectorAll('[data-ab-overflow]');
                    for (var k = 0; k < overflowItems.length; k++) {
                        overflowItems[k].style.removeProperty('display');
                        overflowItems[k].removeAttribute('data-ab-overflow');
                    }
                    while (moreMenu.firstChild) moreMenu.removeChild(moreMenu.firstChild);
                    moreMenu.classList.remove('ab-open');
                } else {
                    // ── 退出全屏 ──
                    var exitOverlay = document.getElementById('ab-editor-fs-overlay');
                    var exitPlaceholder = document.getElementById('ab-editor-fs-placeholder');
                    if (exitOverlay && exitPlaceholder) {
                        // 更新图标（按钮仍在覆盖层中，此时 getBoundingClientRect 有效）
                        icon.textContent = 'open_in_full';
                        fsBtn.title = '全屏编辑';

                        // 切换到退出动画类（淡出 + 轻微缩小，不需要 origin 定位）
                        exitOverlay.classList.remove('ab-fs-entering');
                        exitOverlay.classList.add('ab-fs-exiting');

                        // 等退出动画结束后再还原 DOM，避免画面闪跳
                        exitOverlay.addEventListener('animationend', function () {
                            exitPlaceholder.parentNode.insertBefore(fsContainer, exitPlaceholder);
                            exitPlaceholder.parentNode.removeChild(exitPlaceholder);
                            document.body.removeChild(exitOverlay);
                            document.body.classList.remove('ab-editor-fs');
                            setTimeout(function () { self._checkToolbarOverflow(row, moreBtn, moreMenu); }, 100);
                        }, { once: true });
                    } else {
                        // 兜底：没有覆盖层时直接清理状态
                        document.body.classList.remove('ab-editor-fs');
                        setTimeout(function () { self._checkToolbarOverflow(row, moreBtn, moreMenu); }, 100);
                    }
                }
            };
            fsBtn.addEventListener('click', function () { toggleFs(false); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && document.body.classList.contains('ab-editor-fs')) {
                    toggleFs(true);
                }
            });

            // 初始检查 + resize 防抖
            var resizeTimer = null;
            var checkOverflow = function () {
                self._checkToolbarOverflow(row, moreBtn, moreMenu);
            };
            setTimeout(checkOverflow, 120);
            window.addEventListener('resize', function () {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(checkOverflow, 150);
            });
        },

        _checkToolbarOverflow: function (row, moreBtn, moreMenu) {
            // 全屏模式下所有按钮展开显示，跳过溢出检测
            if (document.body.classList.contains('ab-editor-fs')) return;
            var bar = document.getElementById('wmd-button-bar');
            if (!bar) return;

            var iconMap = {
                'wmd-bold-button':            'format_bold',
                'wmd-italic-button':          'format_italic',
                'wmd-link-button':            'link',
                'wmd-quote-button':           'format_quote',
                'wmd-code-button':            'code',
                'wmd-image-button':           'image',
                'wmd-olist-button':           'format_list_numbered',
                'wmd-ulist-button':           'format_list_bulleted',
                'wmd-heading-button':         'title',
                'wmd-hr-button':              'horizontal_rule',
                'wmd-more-button':            'read_more',
                'wmd-undo-button':            'undo',
                'wmd-redo-button':            'redo',
                'wmd-fullscreen-button':      'fullscreen',
                'wmd-exit-fullscreen-button': 'fullscreen_exit'
            };

            var alwaysVisible = [
                // undo/redo 已移至 .ab-fixed-btns，不在 row 中，无需保护
                // fullscreen/exit-fullscreen 通过 CSS 隐藏，跳过溢出检测即可
                'wmd-fullscreen-button', 'wmd-exit-fullscreen-button'
            ];

            // Step 1: 恢复上次我们隐藏的元素，清空菜单，隐藏 moreBtn
            var prevHidden = row.querySelectorAll('[data-ab-overflow]');
            for (var i = 0; i < prevHidden.length; i++) {
                prevHidden[i].style.removeProperty('display');
                prevHidden[i].removeAttribute('data-ab-overflow');
            }
            while (moreMenu.firstChild) moreMenu.removeChild(moreMenu.firstChild);
            moreBtn.style.display = 'none';

            // Step 2: 强制重排，检查是否有溢出
            void bar.offsetWidth;
            if (row.scrollWidth <= row.offsetWidth) return;

            // Step 3: 显示 moreBtn（flex 兄弟，占用 bar 宽度，row 的 flex:1 相应缩小），重排
            moreBtn.style.display = 'flex';
            void bar.offsetWidth;

            // Step 4: 用 offsetLeft 相对坐标精确检测溢出
            // row 和 li 的 offsetParent 都是 .ab-inner-pill（position:relative）
            // getBoundingClientRect 在 overflow:hidden 容器内会被裁切，不可靠
            // 正确做法：li.offsetLeft - row.offsetLeft = li 在 row 内的起始 x，+ offsetWidth = 右边缘
            var rowStart = row.offsetLeft;
            var rowWidth = row.offsetWidth;
            var allItems = row.querySelectorAll('li');
            var toHide = [];
            for (var j = 0; j < allItems.length; j++) {
                var li = allItems[j];
                if (alwaysVisible.indexOf(li.id) !== -1) continue;
                if ((li.offsetLeft - rowStart) + li.offsetWidth > rowWidth) {
                    toHide.push(li);
                }
            }

            // 若快照后无溢出（moreBtn 本身已使 row 缩窄但刚好够放），收回 moreBtn
            if (toHide.length === 0) {
                moreBtn.style.display = 'none';
                return;
            }

            // Step 5: 按 DOM 顺序（左→右）隐藏溢出项，同顺序填充菜单
            for (var k = 0; k < toHide.length; k++) {
                var btn = toHide[k];
                var isButton = btn.classList.contains('wmd-button');
                var btnTitle = isButton ? (btn.title || '') : '';
                var btnId    = btn.id;

                // CSS 中 li.wmd-button 有 display:flex !important，inline style.display='none' 会被覆盖
                // 必须用 setProperty 的 important 标志才能胜出
                btn.style.setProperty('display', 'none', 'important');
                btn.setAttribute('data-ab-overflow', '1');

                if (isButton && btnTitle) {
                    var item = document.createElement('div');
                    item.className = 'ab-overflow-item';

                    var iconEl = document.createElement('i');
                    iconEl.className = 'material-icons-round';
                    // 优先使用 iconMap，其次读取按钮内已有的 material-icons-round 文字（兼容 Mirages 等主题替换过的图标），最后回退 extension
                    var existingMdIcon = btn.querySelector('i.material-icons-round');
                    iconEl.textContent = iconMap[btnId] || (existingMdIcon ? existingMdIcon.textContent.trim() : 'extension');

                    var labelEl = document.createElement('span');
                    labelEl.textContent = btnTitle;

                    item.appendChild(iconEl);
                    item.appendChild(labelEl);

                    (function (origId, menu) {
                        item.addEventListener('click', function (e) {
                            e.stopPropagation();
                            var origBtn = document.getElementById(origId);
                            if (origBtn) origBtn.click();
                            menu.classList.remove('ab-open');
                        });
                    })(btnId, moreMenu);

                    moreMenu.appendChild(item);
                }
            }
        },

        /**
         * AJAX 兼容性检测（plugins.php / themes.php 页面）
         * 检测当前已激活的插件或当前外观中是否存在 AJAX/XMLHttpRequest/fetch 调用，
         * 若存在则弹出兼容提醒 Modal。
         */
        _checkAjaxCompat: function () {
            return; // 已关闭兼容性检测弹窗
        },

        _checkAjaxCompat_unused_body: function () {
            var loc = location.href;
            var locSearch = location.search;
            var isPluginConfig   = loc.indexOf('options-plugin.php') !== -1 && locSearch.indexOf('config=') !== -1;
            var isThemeOptions   = loc.indexOf('options-theme.php') !== -1;
            var isExtendingPanel = loc.indexOf('extending.php') !== -1 && locSearch.indexOf('panel=') !== -1;
            if (!isPluginConfig && !isThemeOptions && !isExtendingPanel) return;

            // 每次页面只提示一次
            if (document.getElementById('ab-ajax-compat-modal')) return;

            // ── 1. 内置白名单（AdminBeautify 自身 / 已知安全的面板） ───────────────
            var builtinWhitelist = ['adminbeautifystore', 'adminbeautify'];

            // ── 2. 从当前 URL 实时计算页面标识符（避免 AJAX 导航后 __AB_CONFIG__ 值过期） ──
            var pageKey = '';
            if (isPluginConfig) {
                var cfgM = locSearch.match(/[?&]config=([^&]+)/);
                pageKey = cfgM ? decodeURIComponent(cfgM[1]).toLowerCase() : '';
            } else if (isThemeOptions) {
                // 主题名无法从 URL 获取，回退到 PHP 注入的值（初次访问时正确）
                var cfgPhp = window.__AB_CONFIG__ || {};
                pageKey = typeof cfgPhp.currentPageCompatKey === 'string' ? cfgPhp.currentPageCompatKey : '';
            } else if (isExtendingPanel) {
                var panM = locSearch.match(/[?&]panel=([^&]+)/);
                pageKey = panM ? decodeURIComponent(panM[1]).split('/')[0].toLowerCase() : '';
            }

            // 命中白名单则静默跳过
            if (pageKey && builtinWhitelist.indexOf(pageKey) !== -1) return;

            // ── 3. 若当前页已启用对应兼容脚本，则静默跳过 ─────────────────────────
            var cfg = window.__AB_CONFIG__ || {};
            var enabledCompat = Array.isArray(cfg.enabledCompatPlugins) ? cfg.enabledCompatPlugins : [];
            if (pageKey && enabledCompat.indexOf(pageKey) !== -1) return;

            // ── 4. 扫描内联脚本 ────────────────────────────────────────────────────
            //   • 排除含 __AB_ 标记的 AdminBeautify 自身注入块
            //   • 主动兼容检测：若脚本已监听 ab:pageload，说明已适配 AJAX 导航 → 跳过
            //   • 影响判断：AJAX 调用须同时存在"页面初始化绑定"特征（DOMContentLoaded /
            //     $(document).ready / $(function 等），否则视为后台请求，不影响功能
            var ajaxKeywords  = ['XMLHttpRequest', '$.ajax', '$.get', '$.post', 'fetch(', 'axios'];
            var initPatterns  = ['DOMContentLoaded', '$(document).ready', '$(function', 'window.onload', 'jQuery(function', 'jQuery(document)'];
            var hasAjax = false;
            var activeCompatFound = false;

            var scripts = document.querySelectorAll('script:not([src])');
            for (var si = 0; si < scripts.length && !hasAjax; si++) {
                var code = scripts[si].textContent || '';
                if (code.indexOf('__AB_') !== -1) continue; // 跳过 AdminBeautify 自身注入

                // 主动兼容：脚本已监听 ab:pageload → 整页安全，直接返回
                if (code.indexOf('ab:pageload') !== -1) { activeCompatFound = true; break; }

                // 检测 AJAX 关键字
                var codeHasAjax = false;
                for (var ki = 0; ki < ajaxKeywords.length; ki++) {
                    if (code.indexOf(ajaxKeywords[ki]) !== -1) { codeHasAjax = true; break; }
                }
                if (!codeHasAjax) continue;

                // 仅当同时存在"初始化绑定"特征时才认定为影响功能的 AJAX
                var codeHasInit = false;
                for (var ii = 0; ii < initPatterns.length; ii++) {
                    if (code.indexOf(initPatterns[ii]) !== -1) { codeHasInit = true; break; }
                }
                if (codeHasInit) hasAjax = true;
            }

            if (activeCompatFound) return;

            // ── 5. 扫描来自用户目录的外部脚本（/usr/plugins/ 或 /usr/themes/，排除自身）──
            if (!hasAjax) {
                var extScripts = document.querySelectorAll('script[src]');
                for (var ei = 0; ei < extScripts.length && !hasAjax; ei++) {
                    var src = extScripts[ei].getAttribute('src') || '';
                    var isUserScript = src.indexOf('/usr/plugins/') !== -1 || src.indexOf('/usr/themes/') !== -1;
                    var isSelf = src.indexOf('AdminBeautify') !== -1;
                    if (isUserScript && !isSelf && src.indexOf('ajax') !== -1) {
                        hasAjax = true;
                    }
                }
            }

            if (!hasAjax) return;

            // 构建 Modal
            // "不再提醒"的 localStorage key 基于当前页面标识，每个插件独立记录
            var lsKey = 'ab-ajax-compat-mute:' + (pageKey || location.pathname + location.search);
            try { if (localStorage.getItem(lsKey) === '1') return; } catch (e) {}

            var modal = document.createElement('div');
            modal.id = 'ab-ajax-compat-modal';
            modal.style.cssText =
                'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;' +
                'background:rgba(0,0,0,.45);backdrop-filter:blur(4px);animation:ab-fade-in .2s ease';
            modal.innerHTML =
                '<div style="background:var(--md-surface,#fff);border-radius:var(--md-radius-xl,20px);' +
                'padding:28px 32px;max-width:480px;width:calc(100% - 48px);box-shadow:var(--md-elevation-4,0 8px 32px rgba(0,0,0,.18));' +
                'border:1px solid var(--md-outline-variant,#cac4d0)">' +
                    '<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">' +
                        '<span class="material-icons-round" style="font-size:28px;color:var(--md-warn-color,#e65100)">warning_amber</span>' +
                        '<h3 style="margin:0;font-size:17px;font-weight:700;color:var(--md-on-surface,#1c1b1f)">AJAX 兼容性提醒</h3>' +
                    '</div>' +
                    '<p style="margin:0 0 12px;font-size:14px;line-height:1.7;color:var(--md-on-surface-variant,#49454f)">' +
                        '检测到当前页面存在 <strong>AJAX 请求</strong>（来自已激活的插件或外观）。' +
                        'AdminBeautify 开启了 AJAX 导航，<strong>可能</strong>与这些请求产生兼容问题，' +
                        '但不一定会影响实际使用，具体表现因插件而异。' +
                    '</p>' +
                    '<p style="margin:0 0 20px;font-size:14px;line-height:1.7;color:var(--md-on-surface-variant,#49454f)">' +
                        '如果当前页面功能正常，可忽略此提醒；若出现内容加载异常，' +
                        '建议前往<strong>插件设置 → 兼容脚本</strong>启用对应兼容支持，或查阅兼容文档。' +
                    '</p>' +
                    '<div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;align-items:center">' +
                        '<button id="ab-ajax-compat-mute" ' +
                        'style="display:inline-flex;align-items:center;gap:5px;padding:9px 18px;border-radius:var(--md-radius-full,999px);' +
                        'background:transparent;color:var(--md-on-surface-variant,#49454f);' +
                        'font-size:13px;font-weight:500;border:1px solid var(--md-outline-variant,#cac4d0);cursor:pointer;margin-right:auto">' +
                            '<span class="material-icons-round" style="font-size:15px">notifications_off</span>不再提醒' +
                        '</button>' +
                        '<a href="https://blog.lhl.one/artical/977.html" target="_blank" rel="noopener noreferrer" ' +
                        'style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--md-radius-full,999px);' +
                        'background:var(--md-primary-container,#eaddff);color:var(--md-on-primary-container,#21005d);' +
                        'font-size:13px;font-weight:600;text-decoration:none">' +
                            '<span class="material-icons-round" style="font-size:16px">open_in_new</span>查看兼容文档' +
                        '</a>' +
                        '<button id="ab-ajax-compat-dismiss" ' +
                        'style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--md-radius-full,999px);' +
                        'background:var(--md-surface-container-high,#ece6f0);color:var(--md-on-surface,#1c1b1f);' +
                        'font-size:13px;font-weight:600;border:none;cursor:pointer">' +
                            '知道了' +
                        '</button>' +
                    '</div>' +
                '</div>';

            document.body.appendChild(modal);

            var closeModal = function () {
                modal.style.animation = 'ab-fade-out .15s ease forwards';
                setTimeout(function () {
                    if (modal.parentNode) modal.parentNode.removeChild(modal);
                }, 160);
            };

            document.getElementById('ab-ajax-compat-dismiss').addEventListener('click', closeModal);

            // "不再提醒"：写入 localStorage 后关闭
            document.getElementById('ab-ajax-compat-mute').addEventListener('click', function () {
                try { localStorage.setItem(lsKey, '1'); } catch (e) {}
                closeModal();
            });

            // 点击蒙层关闭
            modal.addEventListener('click', function (e) {
                if (e.target === modal) { closeModal(); }
            });
        },

        /**
         * 兼容脚本建议提醒
         *
         * 当已安装插件有对应的兼容脚本但尚未启用时，弹窗提醒用户启用。
         * 弹窗包含三个按钮：
         *   - 去启用：跳转到插件设置页
         *   - 忽略：写入 localStorage，永久不再提醒
         *   - 知道了：关闭弹窗，下次仍会提醒
         */
        _checkCompatHint: function () {
            var cfg = window.__AB_CONFIG__ || {};
            var suggestions = Array.isArray(cfg.pendingCompatSuggestions) ? cfg.pendingCompatSuggestions : [];
            if (suggestions.length === 0) return;

            // 已永久忽略则跳过
            try { if (localStorage.getItem('ab-compat-hint-mute') === '1') return; } catch (e) {}

            // 已有弹窗则跳过
            if (document.getElementById('ab-compat-hint-modal')) return;

            // 在插件设置页（AdminBeautify）不重复提示
            if (location.href.indexOf('options-plugin.php') !== -1 && location.search.indexOf('config=AdminBeautify') !== -1) return;

            var settingsUrl = typeof cfg.pluginSettingsUrl === 'string' ? cfg.pluginSettingsUrl : '';

            // 构建插件列表 HTML
            var listHtml = '<ul style="margin:8px 0 0;padding-left:20px;font-size:13px;line-height:2;color:var(--md-on-surface-variant,#49454f)">';
            for (var i = 0; i < suggestions.length; i++) {
                var s = suggestions[i];
                var esc = function (str) {
                    var d = document.createElement('div');
                    d.appendChild(document.createTextNode(String(str)));
                    return d.innerHTML;
                };
                listHtml += '<li><strong>' + esc(s.plugin) + '</strong>';
                if (s.name) listHtml += ' &rarr; <span style="color:var(--md-on-surface-variant,#49454f)">' + esc(s.name) + '</span>';
                listHtml += '</li>';
            }
            listHtml += '</ul>';

            var goBtn = settingsUrl
                ? '<a href="' + settingsUrl + '#ab-card-compat" '
                    + 'style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--md-radius-full,999px);'
                    + 'background:var(--md-primary,#6750a4);color:var(--md-on-primary,#fff)!important;'
                    + 'font-size:13px;font-weight:600;text-decoration:none">'
                    + '<span class="material-icons-round" style="font-size:16px">settings</span>去启用</a>'
                : '';

            var modal = document.createElement('div');
            modal.id = 'ab-compat-hint-modal';
            modal.style.cssText =
                'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;' +
                'background:rgba(0,0,0,.45);backdrop-filter:blur(4px);animation:ab-fade-in .2s ease';
            modal.innerHTML =
                '<div style="background:var(--md-surface,#fff);border-radius:var(--md-radius-xl,20px);' +
                'padding:28px 32px;max-width:460px;width:calc(100% - 48px);' +
                'box-shadow:var(--md-elevation-4,0 8px 32px rgba(0,0,0,.18));' +
                'border:1px solid var(--md-outline-variant,#cac4d0)">' +
                    '<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">' +
                        '<span class="material-icons-round" style="font-size:28px;color:var(--md-primary,#6750a4)">extension</span>' +
                        '<h3 style="margin:0;font-size:17px;font-weight:700;color:var(--md-on-surface,#1c1b1f)">检测到可用兼容脚本</h3>' +
                    '</div>' +
                    '<p style="margin:0 0 4px;font-size:14px;line-height:1.7;color:var(--md-on-surface-variant,#49454f)">' +
                        '以下已安装插件有对应的兼容脚本，但尚未启用：' +
                    '</p>' +
                    listHtml +
                    '<p style="margin:12px 0 20px;font-size:13px;line-height:1.7;color:var(--md-on-surface-variant,#49454f)">' +
                        '启用兼容脚本可改善 AJAX 导航与这些插件的兼容性。' +
                    '</p>' +
                    '<div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;align-items:center">' +
                        '<button id="ab-compat-hint-mute" ' +
                        'style="display:inline-flex;align-items:center;gap:5px;padding:9px 18px;border-radius:var(--md-radius-full,999px);' +
                        'background:transparent;color:var(--md-on-surface-variant,#49454f);' +
                        'font-size:13px;font-weight:500;border:1px solid var(--md-outline-variant,#cac4d0);cursor:pointer;margin-right:auto">' +
                            '<span class="material-icons-round" style="font-size:15px">notifications_off</span>忽略' +
                        '</button>' +
                        '<button id="ab-compat-hint-dismiss" ' +
                        'style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--md-radius-full,999px);' +
                        'background:var(--md-surface-container-high,#ece6f0);color:var(--md-on-surface,#1c1b1f);' +
                        'font-size:13px;font-weight:600;border:none;cursor:pointer">' +
                            '知道了' +
                        '</button>' +
                        goBtn +
                    '</div>' +
                '</div>';

            document.body.appendChild(modal);

            var closeModal = function () {
                modal.style.animation = 'ab-fade-out .15s ease forwards';
                setTimeout(function () { if (modal.parentNode) modal.parentNode.removeChild(modal); }, 160);
            };

            document.getElementById('ab-compat-hint-dismiss').addEventListener('click', closeModal);
            document.getElementById('ab-compat-hint-mute').addEventListener('click', function () {
                try { localStorage.setItem('ab-compat-hint-mute', '1'); } catch (e) {}
                closeModal();
            });
            modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
        },

        /**
         * HTML 转义
         */
        _escHtml: function (str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },

        // ==================================================
        // AJAX Navigation (pjax-like)
        // ==================================================

        /**
         * 解析 URL 为绝对路径（用于脚本去重比较）
         */
        _resolveUrl: function (url) {
            if (!url) return '';
            try {
                return new URL(url, location.href).href;
            } catch (e) {
                return url;
            }
        },

        /**
         * 检查脚本是否已加载
         */
        _isScriptLoaded: function (src, attr) {
            if (!src && !attr) return false;

            // Core scripts must NEVER be reloaded (reloading jQuery destroys all plugins)
            // Exception: if jQuery is not currently in window, allow them to load
            // (fixes "$ is not defined" when initial page has no jQuery, e.g. Panel.php)
            var coreScripts = ['jquery.js', 'jquery-ui.js', 'typecho.js'];
            var checkName = (attr || src || '').split('/').pop().split('?')[0];
            if (checkName) {
                for (var ci = 0; ci < coreScripts.length; ci++) {
                    if (checkName === coreScripts[ci]) {
                        // Only skip if jQuery is actually available in window
                        return typeof window.jQuery === 'function';
                    }
                }
            }

            // 直接匹配
            if (src && this._loadedScripts[src]) return true;
            if (attr && this._loadedScripts[attr]) return true;
            // 解析后匹配（处理 DOMParser 文档中 URL 解析不一致的问题）
            var resolved = this._resolveUrl(attr || src);
            if (resolved && this._loadedScripts[resolved]) return true;
            // 反向：用文件名部分匹配（兜底）
            var filename = (attr || src || '').split('/').pop().split('?')[0];
            if (filename) {
                for (var key in this._loadedScripts) {
                    if (this._loadedScripts.hasOwnProperty(key)) {
                        var keyFile = key.split('/').pop().split('?')[0];
                        if (keyFile === filename) return true;
                    }
                }
            }
            return false;
        },

        /**
         * 初始化 AJAX 导航
         */
        initAjaxNav: function () {
            if (!window.history || !window.history.pushState) return;
            if (!window.fetch && !window.XMLHttpRequest) return;

            var cfg = window.__AB_CONFIG__ || {};
            if (cfg.ajaxEnabled === '0') return;

            // Idempotency: skip if already initialized
            if (this._ajaxNavActive) return;
            this._ajaxNavActive = true;

            // Record initially loaded external scripts
            var scripts = document.querySelectorAll('script[src]');
            for (var i = 0; i < scripts.length; i++) {
                this._loadedScripts[scripts[i].src] = true;
            }

            // Save jQuery reference (protection against noConflict or overwrites)
            if (typeof window.jQuery === 'function') {
                this._jQueryRef = window.jQuery;
            }

            // Create progress bar
            this._createProgressBar();

            // Bind nav links
            this._bindAjaxLinks();

            // Bind content area links (initial page)
            this._bindContentLinks();

            // 悬停 / 触摸预取目标页，命中缓存时页面切换零等待
            this._bindPrefetch();

            // Handle browser back/forward
            var self = this;
            window.addEventListener('popstate', function (e) {
                if (self._ajaxNavActive) {
                    self._navigateTo(location.href, true);
                }
            });

            // Replace initial state
            history.replaceState({ abAjax: true, url: location.href }, document.title, location.href);
        },

        /**
         * 创建 MD3 Spinner
         */
        _createProgressBar: function () {
            var el = document.createElement('div');
            el.id = 'ab-ajax-progress';
            if ((window.__AB_CONFIG__ || {}).loadingAnimation === 'topbar') {
                el.className = 'ab-ajax-progress-topbar';
                el.innerHTML = '<span class="ab-ajax-progress-bar"></span>';
            }
            document.body.appendChild(el);
        },

        _showProgress: function () {
            var el = document.getElementById('ab-ajax-progress');
            if (!el) return;

            // 取消上一次 _hideProgress 遗留的延迟隐藏，避免它在本轮加载中途把指示器关掉
            if (this._hideTimer) {
                clearTimeout(this._hideTimer);
                this._hideTimer = null;
            }

            el.classList.add('active');

            if ((window.__AB_CONFIG__ || {}).loadingAnimation === 'topbar') {
                var bar = el.querySelector('.ab-ajax-progress-bar');
                if (!bar) return;
                if (this._progressTimer) {
                    clearInterval(this._progressTimer);
                    this._progressTimer = null;
                }
                var width = 12;
                bar.style.width = width + '%';
                this._progressTimer = setInterval(function () {
                    width = Math.min(88, width + (width < 45 ? 8 : 3));
                    bar.style.width = width + '%';
                }, 170);
            }
        },

        _hideProgress: function () {
            var el = document.getElementById('ab-ajax-progress');
            if (!el) return;
            // 幂等：未处于加载态时无需任何动画（新页面 HTML 到手时可能连续调用）
            if (!el.classList.contains('active')) return;

            if ((window.__AB_CONFIG__ || {}).loadingAnimation === 'topbar') {
                var bar = el.querySelector('.ab-ajax-progress-bar');
                if (this._progressTimer) {
                    clearInterval(this._progressTimer);
                    this._progressTimer = null;
                }
                if (bar) {
                    var self = this;
                    bar.style.width = '100%';
                    this._hideTimer = setTimeout(function () {
                        self._hideTimer = null;
                        el.classList.remove('active');
                        bar.style.width = '0%';
                    }, 180);
                    return;
                }
            }

            el.classList.remove('active');
        },

        /**
         * 新页面内容已到手：立即结束加载指示器，并淡出旧内容等待替换
         *
         * 关键点：转圈的结束时机只取决于"新页面 HTML 是否到达"，
         * 与上一页残留的异步组件（Umami 统计、图表数据…）是否加载完无关。
         */
        _beginContentSwap: function () {
            this._hideProgress();
            var main = this._findMainContent(document);
            if (main) {
                main.style.transition = 'opacity 0.12s ease';
                main.style.opacity = '0';
            }
        },

        /**
         * 判断链接是否可以 AJAX 加载
         */
        _isAjaxable: function (link) {
            if (!link || !link.href) return false;
            var href = link.href;

            // Must be same origin
            if (link.origin && link.origin !== location.origin) return false;

            // Skip external links
            if (link.target === '_blank') return false;

            // Skip hash-only links
            if (link.getAttribute('href').charAt(0) === '#') return false;

            // Skip javascript: links
            if (href.indexOf('javascript:') === 0) return false;

            // Skip logout
            if (link.classList.contains('exit')) return false;

            // Skip action URLs (form handlers, logout, etc.)
            if (href.indexOf('/action/') !== -1) return false;

            // Skip theme settings page (themes often have custom JS that requires full reload)
            if (href.indexOf('options-theme.php') !== -1) return false;

            // Skip plugin settings & panel pages (plugins may have complex custom JS/forms)
            if (href.indexOf('options-plugin.php') !== -1) return false;
            if (href.indexOf('extending.php') !== -1) return false;

            // Skip write/edit pages (editor, file upload, rich plugin hooks — too many dynamic scripts)
            if (href.indexOf('write-post.php') !== -1) return false;
            if (href.indexOf('write-page.php') !== -1) return false;

            // Only admin area links
            var adminUrl = this._getAdminUrl();
            if (adminUrl && href.indexOf(adminUrl) === -1) return false;

            return true;
        },

        /**
         * 获取 admin 基础 URL
         */
        _getAdminUrl: function () {
            // Extract from current sidebar links or current URL
            if (this._adminBaseUrl) return this._adminBaseUrl;
            var link = document.querySelector('.typecho-head-nav nav > menu > li:not(.operate) > a');
            if (link && link.href) {
                var idx = link.href.lastIndexOf('/');
                this._adminBaseUrl = link.href.substring(0, idx + 1);
            } else {
                // Fallback: current directory
                this._adminBaseUrl = location.href.substring(0, location.href.lastIndexOf('/') + 1);
            }
            return this._adminBaseUrl;
        },

        /**
         * 绑定导航栏链接的 AJAX 点击（sidebar & top-nav 通用）
         */
        _bindAjaxLinks: function () {
            var self = this;
            var sidebar = document.querySelector('.typecho-head-nav');
            if (!sidebar) return;

            sidebar.addEventListener('click', function (e) {
                var link = e.target.closest('a');
                if (!link) return;

                // Skip operate area links (except profile)
                var operateLi = link.closest('li.operate');
                if (operateLi) {
                    // Allow profile link via AJAX
                    if (link.classList.contains('author')) {
                        // allow
                    } else {
                        return; // exit, site, theme toggle - don't intercept
                    }
                }

                // Skip if it's a parent menu toggle (ab-has-children)
                var parentLi = link.closest('li.ab-has-children');
                if (parentLi && link === parentLi.querySelector(':scope > a') && !document.documentElement.hasAttribute('data-nav-collapsed')) {
                    return; // Let the accordion toggle handler manage it
                }

                if (!self._isAjaxable(link)) return;

                e.preventDefault();
                e.stopPropagation();
                self._navigateTo(link.href);
            }, true);
        },

        /**
         * 绑定内容区域的链接 AJAX 加载
         */
        _bindContentLinks: function () {
            var self = this;
            // Use event delegation on body for content area links
            document.body.addEventListener('click', function (e) {
                if (!self._ajaxNavActive) return;

                var link = e.target.closest('a');
                if (!link) return;

                // Skip if inside sidebar
                if (link.closest('.typecho-head-nav')) return;

                // Skip if inside forms, editors, or special elements
                if (link.closest('form')) return;
                if (link.closest('#wmd-button-bar')) return;
                if (link.closest('.typecho-pager')) {
                    // Pager links - allow AJAX
                    if (self._isAjaxable(link)) {
                        e.preventDefault();
                        self._navigateTo(link.href);
                    }
                    return;
                }

                // Only AJAX navigate for admin page links (not action links, not edit links)
                if (!self._isAjaxable(link)) return;

                // Skip links that look like they trigger actions
                var href = link.getAttribute('href') || '';
                if (href.indexOf('do=') !== -1 && href.indexOf('action') !== -1) return;

                // For general content links in the admin area, allow AJAX
                e.preventDefault();
                self._navigateTo(link.href);
            });
        },

        /**
         * 核心 AJAX 导航方法
         */
        _navigateTo: function (url, isPopState) {
            var self = this;

            // Cancel any in-flight request
            if (this._currentXHR) {
                this._currentXHR.abort();
                this._currentXHR = null;
            }

            // 立即放弃上一页遗留的组件请求（统计 / 图表 / Umami 等）。
            // 这些请求往往需要服务端出网，继续挂着会占满浏览器同域连接并长期
            // 持有 PHP Session，新页面的 HTML 只能排队 —— 表现就是"转圈要等上
            // 一页的组件加载完才停"。这里不再等待它们。
            this._abortPageRequests();

            // Same URL — skip
            if (url === location.href && !isPopState) return;

            // ---- 悬停预取命中：无需网络请求，直接套用（零等待切换） ----
            var cachedPage = isPopState ? null : this._takePrefetch(url);
            if (cachedPage) {
                this._beginContentSwap();
                try {
                    this._applyPage(cachedPage.html, url, isPopState);
                } catch (err) {
                    console.error('[AdminBeautify AJAX Nav] Error applying prefetched page:', err);
                    window.location.href = url;
                }
                return;
            }

            // ---- 立即淡出当前内容，避免"旧页面内容闪过" ----
            // 在发起 XHR 前就开始淡出，XHR 完成时内容已不可见，不会有明显闪烁
            var _preMain = this._findMainContent(document);
            if (_preMain) {
                _preMain.style.transition = 'opacity 0.12s ease';
                _preMain.style.opacity   = '0';
            }

            this._showProgress();

            var xhr = new XMLHttpRequest();
            this._currentXHR = xhr;

            xhr.open('GET', url, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-PJAX', 'true');
            xhr.responseType = 'text';

            // 某些浏览器/环境下 XHR 长时间无响应，快速回退到常规跳转
            var forceFallbackTimer = setTimeout(function () {
                if (xhr.readyState < 2) {
                    try { xhr.abort(); } catch (e) {}
                    window.location.href = url;
                }
            }, 4500);

            xhr.onload = function () {
                clearTimeout(forceFallbackTimer);
                self._currentXHR = null;

                // Use the final URL after any redirects (XHR follows 302 transparently)
                var finalUrl = xhr.responseURL || url;

                if (xhr.status >= 200 && xhr.status < 300) {
                    // Check if response is HTML
                    var contentType = xhr.getResponseHeader('Content-Type') || '';
                    if (contentType.indexOf('text/html') === -1) {
                        // Not HTML — do full navigation
                        window.location.href = finalUrl;
                        return;
                    }

                    // ★ 新页面 HTML 已返回：立刻结束转圈，并中断上一页残留的组件请求。
                    //   后续的脚本执行 / 组件增强耗时与加载动画无关，
                    //   切换体感速度只取决于本次 XHR 的耗时。
                    self._abortPageRequests();
                    self._beginContentSwap();

                    try {
                        self._applyPage(xhr.responseText, finalUrl, isPopState);
                    } catch (err) {
                        console.error('[AdminBeautify AJAX Nav] Error applying page:', err);
                        window.location.href = finalUrl;
                    }
                } else if (xhr.status >= 300 && xhr.status < 400) {
                    // Redirect
                    var redirectUrl = xhr.getResponseHeader('Location');
                    if (redirectUrl) {
                        self._navigateTo(redirectUrl);
                    } else {
                        window.location.href = url;
                    }
                } else {
                    // Error — fallback to normal navigation
                    window.location.href = url;
                }
            };

            xhr.onerror = function () {
                clearTimeout(forceFallbackTimer);
                self._currentXHR = null;
                self._hideProgress();
                window.location.href = url;
            };

            xhr.ontimeout = function () {
                clearTimeout(forceFallbackTimer);
                self._currentXHR = null;
                self._hideProgress();
                window.location.href = url;
            };

            xhr.timeout = 8000; // 8s timeout
            xhr.send();
        },

        /**
         * 查找页面主内容区（兼容 <main class="main"> 和 <div class="main">）
         */
        _findMainContent: function (root) {
            return root.querySelector('main.main') || root.querySelector('div.main');
        },

        /**
         * 解析并应用新页面内容
         */
        _applyPage: function (html, url, isPopState) {
            /* 页面即将被整个替换：先跑自定义卡片的清理函数（定时器 / 监听器），避免泄漏 */
            if (typeof AdminBeautify.cleanupCustomCards === 'function') {
                AdminBeautify.cleanupCustomCards();
            }
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');

            // --- Extract key elements from new page ---
            var newTitle = doc.querySelector('title');
            var newMain = this._findMainContent(doc);
            // 兼容 <footer> 和 <div> 两种 .typecho-foot 写法
            var newFoot = doc.querySelector('footer.typecho-foot') || doc.querySelector('.typecho-foot');
            var newBody = doc.querySelector('body');

            // If no content area found → full reload
            if (!newMain) {
                window.location.href = url;
                return;
            }

            // If current page has no content area to replace → full reload
            var currentMain = this._findMainContent(document);
            if (!currentMain) {
                window.location.href = url;
                return;
            }

            // --- Extract head elements (page-specific styles/meta) ---
            var newHeadLinks = doc.querySelectorAll('head link[rel="stylesheet"]');
            var currentHeadLinks = document.querySelectorAll('head link[rel="stylesheet"]');
            var currentHrefs = {};
            for (var i = 0; i < currentHeadLinks.length; i++) {
                currentHrefs[currentHeadLinks[i].href] = true;
            }
            // Add any new stylesheets
            for (var j = 0; j < newHeadLinks.length; j++) {
                if (!currentHrefs[newHeadLinks[j].href]) {
                    var cloned = newHeadLinks[j].cloneNode(true);
                    document.head.appendChild(cloned);
                }
            }

            // --- Update document title ---
            if (newTitle) {
                document.title = newTitle.textContent;
            }

            // --- Update body class ---
            if (newBody && newBody.className) {
                document.body.className = newBody.className;
            } else {
                document.body.className = '';
            }

            // --- Remove old page-specific dynamic resources (scripts + styles) FIRST ---
            var oldDynamic = document.querySelectorAll('[data-ab-dynamic]');
            for (var k = 0; k < oldDynamic.length; k++) {
                oldDynamic[k].remove();
            }

            // --- THEN add new inline <style> from head and body (plugin-specific styles) ---
            var newHeadStyles = doc.querySelectorAll('head style');
            var newBodyStyles = doc.querySelectorAll('body style');
            var allNewStyles = Array.prototype.slice.call(newHeadStyles).concat(Array.prototype.slice.call(newBodyStyles));
            for (var si = 0; si < allNewStyles.length; si++) {
                var styleEl = document.createElement('style');
                styleEl.setAttribute('data-ab-dynamic', 'true');
                styleEl.textContent = allNewStyles[si].textContent;
                document.head.appendChild(styleEl);
            }

            // --- Replace content area with animation ---
            // 注意：opacity 淡出已在 _navigateTo 中提前触发，此处无需再次设置
            // Replace content (handle tag mismatch: e.g. current=<div>, new=<main>)
            var replaceFn = function () {
                // If tag names differ, swap the entire element
                if (currentMain.tagName !== newMain.tagName) {
                    var replacement = document.createElement(newMain.tagName);
                    replacement.className = newMain.className;
                    replacement.innerHTML = newMain.innerHTML;
                    if (newMain.getAttribute('role')) replacement.setAttribute('role', newMain.getAttribute('role'));
                    currentMain.parentNode.replaceChild(replacement, currentMain);
                    currentMain = replacement;
                } else {
                    currentMain.innerHTML = newMain.innerHTML;
                }
                // Trigger reflow
                void currentMain.offsetHeight;
                self._hideProgress();
                currentMain.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                currentMain.style.opacity = '1';
                currentMain.style.transform = 'translateY(0)';
                
                setTimeout(function () {
                    currentMain.style.transition = '';
                    currentMain.style.opacity = '';
                    currentMain.style.transform = '';
                }, 300);
            };
            setTimeout(replaceFn, 50);

            // --- Replace <footer> ---
            // 兼容 <footer> 和 <div> 两种 .typecho-foot 写法
            var currentFoot = document.querySelector('footer.typecho-foot') || document.querySelector('.typecho-foot');
            if (currentFoot && newFoot) {
                currentFoot.innerHTML = newFoot.innerHTML;
            }

            // --- Extract and execute scripts ---
            // Collect all scripts from the new body (after main)
            var allNewScripts = doc.querySelectorAll('body script');
            var scriptsToRun = [];
            
            for (var s = 0; s < allNewScripts.length; s++) {
                var script = allNewScripts[s];
                // Use both .src (resolved) and getAttribute (raw) for robust matching
                var scriptSrc = script.src || '';
                var scriptAttr = script.getAttribute('src') || '';
                // Skip AdminBeautify's own script (check both resolved and raw src)
                if ((scriptSrc && scriptSrc.indexOf('AdminBeautify') !== -1) ||
                    (scriptAttr && scriptAttr.indexOf('AdminBeautify') !== -1)) continue;
                // Skip our watchSystemTheme call and __AB_ variable injection
                if (!scriptSrc && !scriptAttr && (
                    script.textContent.indexOf('AdminBeautify') !== -1 ||
                    script.textContent.indexOf('__AB_') !== -1
                )) continue;
                
                scriptsToRun.push({
                    src: scriptSrc || scriptAttr || null,
                    attr: scriptAttr || null,
                    text: script.textContent || '',
                    type: script.type || ''
                });
            }

            // Execute scripts sequentially
            var self = this;
            setTimeout(function () {
                self._executeScripts(scriptsToRun, 0, function () {
                    // --- Post-load tasks ---

                    // Update history FIRST so location.href is correct
                    // for enhance functions that check URL (e.g. enhancePlugins)
                    if (!isPopState) {
                        history.pushState({ abAjax: true, url: url }, document.title, url);
                    }

                    // Update nav active state + sync dynamic items from new page
                    self._updateNavFromDoc(doc, url);

                    // Re-enhance new content
                    self.enhanceButtons();
                    self.enhanceTables();
                    self.enhanceDashboard();
                    self.enhanceProfile();
                    self.enhancePlugins();
                    self.enhanceThemes();
                    self.enhanceOptions();
                    self.enhanceComments();
                    self.enhancePosts();
                    self.enhanceUsers();
                    self.enhanceMedias();
                    self.enhanceMediaEdit();
                    self.enhanceTags();
                    self.initTabsFab();
                    self.initEditorToolbar();
                    self.initAttachPicker();
                    self.addFooterInfo();    // 重建 footer 主题信息（AJAX 替换后可能丢失）
                    self._checkAjaxCompat(); // AJAX 兼容性检测（AJAX 导航后重新检测）
                    self._checkCompatHint(); // 兼容脚本建议提醒（AJAX 导航后重新检测）

                    // Scroll to top
                    window.scrollTo(0, 0);

                    // Rebind content links for new page
                    // (event delegation handles this automatically)

                    // Dispatch custom event for other plugins
                    var event;
                    try {
                        event = new CustomEvent('ab:pageload', { detail: { url: url } });
                    } catch (e) {
                        event = document.createEvent('CustomEvent');
                        event.initCustomEvent('ab:pageload', true, true, { url: url });
                    }
                    document.dispatchEvent(event);
                });
            }, 100);
        },

        /**
         * 确保 jQuery 全局可用（防止被 noConflict 或其他脚本移除）
         */
        _ensureJQuery: function () {
            if (typeof window.jQuery === 'function') {
                // jQuery exists — always (re-)set $ alias to jQuery
                // (prevents "$ is not defined" if a previous script called noConflict)
                window.$ = window.jQuery;
            } else if (this._jQueryRef) {
                // jQuery was removed — restore from saved reference
                window.jQuery = window.$ = this._jQueryRef;
            }
        },

        /**
         * 顺序执行脚本列表
         */
        _executeScripts: function (scripts, index, callback) {
            if (index >= scripts.length) {
                if (callback) callback();
                return;
            }

            var scriptInfo = scripts[index];
            var self = this;

            // Restore jQuery globals before every step (some scripts may destroy them)
            this._ensureJQuery();

            if (scriptInfo.src) {
                // External script — use robust matching
                if (this._isScriptLoaded(scriptInfo.src, scriptInfo.attr)) {
                    // Already loaded — skip
                    this._executeScripts(scripts, index + 1, callback);
                } else {
                    // Load new external script (resolve to absolute URL)
                    var resolvedSrc = this._resolveUrl(scriptInfo.attr || scriptInfo.src);
                    var el = document.createElement('script');
                    el.src = resolvedSrc || scriptInfo.src;
                    el.setAttribute('data-ab-dynamic', 'true');
                    if (scriptInfo.type) el.type = scriptInfo.type;
                    el.onload = function () {
                        self._loadedScripts[el.src] = true;
                        // Also store the original attr for future matching
                        if (scriptInfo.attr) self._loadedScripts[scriptInfo.attr] = true;
                        // Update _jQueryRef if jQuery just became available via this script
                        if (typeof window.jQuery === 'function' && !self._jQueryRef) {
                            self._jQueryRef = window.jQuery;
                        }
                        // Restore jQuery in case the loaded script called noConflict
                        self._ensureJQuery();
                        self._executeScripts(scripts, index + 1, callback);
                    };
                    el.onerror = function () {
                        console.warn('[AdminBeautify] Failed to load script:', resolvedSrc);
                        self._executeScripts(scripts, index + 1, callback);
                    };
                    document.body.appendChild(el);
                }
            } else if (scriptInfo.text) {
                // Inline script — skip common-js.php notice handler on AJAX
                // (uses $.cookie which may conflict; notices are handled on first load)
                if (scriptInfo.text.indexOf('__typecho_notice') !== -1) {
                    this._executeScripts(scripts, index + 1, callback);
                    return;
                }
                // Execute via new script element
                try {
                    var el = document.createElement('script');
                    el.setAttribute('data-ab-dynamic', 'true');
                    if (scriptInfo.type) el.type = scriptInfo.type;
                    // 1. 先恢复 window.jQuery / window.$（防 noConflict 清除）
                    this._ensureJQuery();
                    // 2. 获取 jQuery 引用（_ensureJQuery 恢复后再读，fallback _jQueryRef）
                    var _jq = (typeof window.jQuery === 'function') ? window.jQuery
                            : (typeof this._jQueryRef === 'function' ? this._jQueryRef : null);
                    // 3. 无论如何，只要有 jQuery 就用 IIFE 注入局部 $ / jQuery，
                    //    与全局状态彻底解耦，防止 "$ is not defined" ReferenceError
                    var safeText = scriptInfo.text;
                    if (_jq) {
                        // 将 jQuery 引用挂到一个局部变量名，供 IIFE 末尾调用时能正确求值
                        window.__ab_jq_tmp__ = _jq;
                        safeText = '(function($,jQuery){\n' + safeText
                            + '\n})(window.__ab_jq_tmp__,window.__ab_jq_tmp__);'
                            + 'delete window.__ab_jq_tmp__;';
                    }
                    el.textContent = safeText;
                    document.body.appendChild(el);
                } catch (err) {
                    console.warn('[AdminBeautify] Error executing inline script:', err);
                }
                this._executeScripts(scripts, index + 1, callback);
            } else {
                this._executeScripts(scripts, index + 1, callback);
            }
        },

        /**
         * 从新页面的 DOM 更新导航栏活跃状态（sidebar & top-nav 通用）
         * 策略1: 按索引从新页面 DOM 复制 focus 状态（最可靠，菜单顺序恒定）
         * 策略2: 按目标 URL 匹配子菜单 href（兜底方案）
         */
        _updateNavFromDoc: function (doc, targetUrl) {
            var currentMenu = document.querySelector('.typecho-head-nav nav > menu');
            if (!currentMenu) return;

            // ── 0. 清除之前动态插入的导航项 ──
            var oldDynamic = currentMenu.querySelectorAll('[data-ab-dynamic-nav]');
            for (var d = 0; d < oldDynamic.length; d++) {
                oldDynamic[d].remove();
            }

            var currentItems = currentMenu.querySelectorAll(':scope > li:not(.operate)');
            if (!currentItems.length) return;

            // 辅助：提取 <a> 的 href 属性（原始值，不解析）
            function linkHref(el) {
                var a = el.querySelector ? el.querySelector('a') : null;
                return a ? (a.getAttribute('href') || '') : '';
            }

            // ── 策略1: 从新页面 DOM 同步导航状态 + 动态项 ──
            var newMenu = doc ? doc.querySelector('.typecho-head-nav nav > menu, .typecho-head-nav nav > ul') : null;
            if (newMenu) {
                var newItems = newMenu.querySelectorAll(':scope > li:not(.operate)');
                // 父级菜单数量应一致（控制台/撰写/管理/设置）
                if (newItems.length > 0 && newItems.length === currentItems.length) {
                    for (var i = 0; i < currentItems.length; i++) {
                        var curLi = currentItems[i];
                        var newLi = newItems[i];

                        // 清除旧状态，并收起可能遗留的内联高度（避免 AJAX 切换时仍然可见）
                        curLi.classList.remove('focus', 'ab-expanded', 'ab-child-focus');
                        var curSubMenuCleanup = curLi.querySelector(':scope > menu');
                        if (curSubMenuCleanup) curSubMenuCleanup.style.maxHeight = '0';

                        // 复制父级 focus
                        if (newLi.classList.contains('focus')) {
                            curLi.classList.add('focus');
                        }

                        // 同步子菜单：支持 Typecho 动态菜单项
                        // （如 options-plugin.php?config=XXX、options-theme.php 等）
                        var curSubMenu = curLi.querySelector(':scope > menu');
                        var newSubMenu = newLi.querySelector(':scope > menu');
                        var hasChildFocus = false;

                        if (curSubMenu && newSubMenu) {
                            var curSubs = curSubMenu.querySelectorAll(':scope > li');
                            var newSubs = newSubMenu.querySelectorAll(':scope > li');

                            // 构建当前子项的 href 映射
                            var curHrefMap = {};
                            for (var c = 0; c < curSubs.length; c++) {
                                var cHref = linkHref(curSubs[c]);
                                if (cHref) curHrefMap[cHref] = curSubs[c];
                            }

                            // 构建新页面子项的 href 集合
                            var newHrefSet = {};
                            for (var ns = 0; ns < newSubs.length; ns++) {
                                var nsHref = linkHref(newSubs[ns]);
                                if (nsHref) newHrefSet[nsHref] = true;
                            }

                            // 先清除所有子项 focus
                            for (var cc = 0; cc < curSubs.length; cc++) {
                                curSubs[cc].classList.remove('focus');
                            }

                            // 删除新页面中不存在的子项
                            // （处理服务器渲染的动态项，如从 options-theme.php 跳出后）
                            for (var rc = curSubs.length - 1; rc >= 0; rc--) {
                                var rcHref = linkHref(curSubs[rc]);
                                if (rcHref && !newHrefSet[rcHref]) {
                                    curSubs[rc].remove();
                                }
                            }

                            // 遍历新页面的子菜单，同步到当前导航
                            var prevCurSub = null;
                            for (var j = 0; j < newSubs.length; j++) {
                                var newSub = newSubs[j];
                                var newSubHref = linkHref(newSub);
                                var newSubFocus = newSub.classList.contains('focus');

                                var matched = curHrefMap[newSubHref];
                                if (matched) {
                                    // 已有项 — 复制 focus
                                    if (newSubFocus) {
                                        matched.classList.add('focus');
                                        hasChildFocus = true;
                                    }
                                    prevCurSub = matched;
                                } else {
                                    // 新的动态项 — 插入到当前导航
                                    var dynLi = document.createElement('li');
                                    dynLi.setAttribute('data-ab-dynamic-nav', 'true');
                                    if (newSubFocus) {
                                        dynLi.classList.add('focus');
                                        hasChildFocus = true;
                                    }
                                    dynLi.innerHTML = newSub.innerHTML;

                                    // 插入到上一个已匹配项之后
                                    if (prevCurSub && prevCurSub.nextSibling) {
                                        curSubMenu.insertBefore(dynLi, prevCurSub.nextSibling);
                                    } else if (prevCurSub) {
                                        curSubMenu.appendChild(dynLi);
                                    } else {
                                        curSubMenu.insertBefore(dynLi, curSubMenu.firstChild);
                                    }
                                    prevCurSub = dynLi;
                                }
                            }
                        }

                        // 更新 sidebar 展开状态 —— 如果需要展开，设置精确的 maxHeight
                        if (curLi.classList.contains('focus') && curLi.classList.contains('ab-has-children')) {
                            curLi.classList.add('ab-expanded');
                            if (hasChildFocus) {
                                curLi.classList.add('ab-child-focus');
                            }
                            var curSubMenu = curLi.querySelector(':scope > menu');
                            if (curSubMenu) curSubMenu.style.maxHeight = curSubMenu.scrollHeight + 'px';
                        }
                    }
                    return; // 策略1 成功
                }
            }

            // ── 策略2: 按目标 URL 匹配（兜底） ──
            if (!targetUrl) return;

            var targetPath = targetUrl.split('?')[0].split('#')[0];
            var targetFile = targetPath.substring(targetPath.lastIndexOf('/') + 1);

            // 先清除所有 focus
            for (var k = 0; k < currentItems.length; k++) {
                currentItems[k].classList.remove('focus', 'ab-expanded', 'ab-child-focus');
                var childLis = currentItems[k].querySelectorAll(':scope > menu > li');
                for (var cl = 0; cl < childLis.length; cl++) {
                    childLis[cl].classList.remove('focus');
                }
            }

            // 遍历子菜单项匹配 URL
            var matched2 = false;
            for (var m = 0; m < currentItems.length; m++) {
                var li = currentItems[m];
                var subItems = li.querySelectorAll(':scope > menu > li');
                var parentMatched = false;

                for (var n = 0; n < subItems.length; n++) {
                    var subLink = subItems[n].querySelector('a');
                    if (!subLink) continue;
                    var subHref = (subLink.getAttribute('href') || '').split('?')[0].split('#')[0];
                    var subFile = subHref.substring(subHref.lastIndexOf('/') + 1);

                    if (subHref === targetPath || (subFile && subFile === targetFile)) {
                        subItems[n].classList.add('focus');
                        parentMatched = true;
                    }
                }

                if (parentMatched) {
                    li.classList.add('focus');
                    if (li.classList.contains('ab-has-children')) {
                        li.classList.add('ab-expanded', 'ab-child-focus');
                    }
                    matched2 = true;
                }
            }

            // 如果都没匹配到，检查父级链接本身
            if (!matched2) {
                for (var p = 0; p < currentItems.length; p++) {
                    var pLink = currentItems[p].querySelector(':scope > a');
                    if (!pLink) continue;
                    var pHref = (pLink.getAttribute('href') || '').split('?')[0].split('#')[0];
                    var pFile = pHref.substring(pHref.lastIndexOf('/') + 1);
                    if (pHref === targetPath || (pFile && pFile === targetFile)) {
                        currentItems[p].classList.add('focus');
                        if (currentItems[p].classList.contains('ab-has-children')) {
                            currentItems[p].classList.add('ab-expanded');
                        }
                        break;
                    }
                }
            }
        }
    };

    // 注入 Ripple 动画 keyframes
    var style = document.createElement('style');
    style.textContent = '@keyframes md3Ripple{0%{transform:scale(0);opacity:0.12}100%{transform:scale(2.5);opacity:0}}';
    document.head.appendChild(style);

    // 注入概要页图表区域样式
    var abChartStyle = document.createElement('style');
    abChartStyle.id = 'ab-chart-style';
    abChartStyle.textContent = [
        /* 统一容器：概要页所有卡片（访问统计 / 更新频率 / 近期评论 / 最近文章 / 最近回复 / 自定义）
         * 都放进同一个 grid，顺序由「概要页卡片设置」决定；列数随宽度自适应（每行尽量多放） */
        '.ab-cards-grid.ab-dash-cards{padding:0 10px;margin-top:20px;width:100%;box-sizing:border-box;grid-template-columns:repeat(auto-fit,minmax(340px,1fr)) !important;gap:16px;align-items:stretch;}',
        /* 卡片本体作为 grid 子项时拉伸等高，内部 flex 列让内容自适应 */
        '.ab-dash-cards > .ab-card{display:flex !important;flex-direction:column !important;min-width:0;}',
        '.ab-dash-cards > .ab-card > ul{flex:1 1 auto;}',
        /* 最近文章卡片：按卡片剩余高度填充（不溢出） */
        '.ab-dash-cards > .ab-card > ul.ab-fit-posts{flex:1 1 0 !important;min-height:0 !important;overflow:hidden !important;}',
        /* Umami 卡片内部：指标列表（图标胶囊 + 名称 + 右对齐数值），窄卡片下自动压缩 */
        '.ab-umami-grid{display:flex;flex-direction:column;justify-content:center;flex:1 1 auto;min-height:0;padding:0 8px 6px;}',
        /* 自定义卡片内容区（方案 C） */
        '.ab-custom-body{padding:14px 20px 16px;font-size:13px;line-height:1.7;color:var(--md-on-surface,#1c1b1f);word-break:break-word;}',
        '.ab-custom-body img{max-width:100%;height:auto;border-radius:10px;}',
        '.ab-custom-body a{color:var(--md-primary,#6750a4);}',
        '.ab-custom-body ul,.ab-custom-body ol{margin:6px 0;padding-left:20px;}',
        '.ab-custom-body>*:first-child{margin-top:0;}',
        '.ab-custom-body>*:last-child{margin-bottom:0;}',
        '[data-theme=dark] .ab-custom-body{color:var(--md-dark-on-surface,#e6e1e5);}',
        '[data-theme=dark] .ab-custom-body a{color:var(--md-dark-primary,#d0bcff);}',
        /* 卡片标题图标：统一为 28px 方块 + 主色
         * 必须带 !important：Typecho admin/css/style.css 的
         *   .latest-link span{display:inline-block;margin-right:4px;padding-right:8px;
         *                     border-right:1px solid #ECECEC;width:37px;text-align:right;color:#999}
         * 特异性 (0,1,1) 高于 .ab-card-header-icon，会把「最近文章/回复」
         * 两张卡片的标题图标挤成 37px 宽并变灰，导致标题与其他卡片不一致。 */
        '.ab-card h3 .ab-card-header-icon{width:28px !important;height:28px !important;min-width:28px !important;padding:0 !important;margin:0 !important;border:0 !important;background:transparent !important;text-align:center !important;color:var(--md-primary,#6750a4) !important;}',
        '.ab-card h3 .ab-card-header-icon .material-icons-round{width:auto !important;height:auto !important;min-width:0 !important;padding:0 !important;margin:0 !important;border:0 !important;background:transparent !important;text-align:center !important;color:inherit !important;font-size:20px !important;line-height:1 !important;display:inline-flex !important;align-items:center !important;justify-content:center !important;}',
        '[data-theme=dark] .ab-card h3 .ab-card-header-icon{color:var(--md-dark-primary,#d0bcff) !important;}',
        /* 最新内容卡片的标题文字：不要被 .latest-link span 影响 */
        '.ab-card h3{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}',
        /* —— 自适应断点 —— */
        '@media(max-width:1200px){',
        '  .ab-cards-grid.ab-dash-cards{gap:14px;}',
        '}',
        '@media(max-width:768px){',
        '  .ab-chart-canvas-wrap{height:200px !important;}',
        '}',
        '@media(max-width:600px){',
        '  .ab-cards-grid.ab-dash-cards{gap:12px;padding:0 6px;}',
        '}',
        /* 图表卡片 */
        '.ab-chart-card{padding:16px 20px 14px;border-radius:16px;min-width:0;box-sizing:border-box;}',
        '.ab-chart-card h3{margin:0 0 10px;display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;}',
        /* 时间范围标签（卡片标题内）*/
        '.ab-chart-period{',
        '  margin-left:auto;font-size:11px;font-weight:400;',
        '  color:var(--md-on-surface-variant,#49454f);opacity:.7;',
        '}',
        '[data-theme=dark] .ab-chart-period{color:var(--md-dark-on-surface-variant,#cac4d0);}',
        /* 图表画布容器（SVG 自适应宽度，固定高度） */
        '.ab-chart-canvas-wrap{position:relative;width:100%;height:240px;}',
        '.ab-chart-canvas{width:100%;height:100%;display:block;}',
        /* 图表卡片标题图标颜色（跟随主题主色） */
        '.ab-chart-card .ab-card-header-icon{color:var(--md-primary,#6750a4);}',
        '[data-theme=dark] .ab-chart-card .ab-card-header-icon{color:var(--md-dark-primary,#d0bcff);}',
        /* 空数据 / 错误提示 */
        '.ab-chart-empty,.ab-chart-err{',
        '  display:flex;align-items:center;justify-content:center;',
        '  height:100%;font-size:13px;',
        '  color:var(--md-on-surface-variant,#49454f);opacity:.7;',
        '}',
        '[data-theme=dark] .ab-chart-empty,[data-theme=dark] .ab-chart-err{',
        '  color:var(--md-dark-on-surface-variant,#cac4d0);',
        '}',
        /* MD3 骨架屏动画 */
        '@keyframes ab-shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}',
        '.ab-chart-skeleton{',
        '  width:100%;height:100%;border-radius:12px;',
        '  background:linear-gradient(90deg,',
        '    var(--md-surface-container-high,#ece6f0) 25%,',
        '    var(--md-surface-container-highest,#e6e0e9) 50%,',
        '    var(--md-surface-container-high,#ece6f0) 75%);',
        '  background-size:200% 100%;',
        '  animation:ab-shimmer 1.5s infinite linear;',
        '}',
        '[data-theme=dark] .ab-chart-skeleton{',
        '  background:linear-gradient(90deg,',
        '    var(--md-dark-surface-container-high,#36343b) 25%,',
        '    var(--md-dark-surface-container-highest,#484649) 50%,',
        '    var(--md-dark-surface-container-high,#36343b) 75%);',
        '  background-size:200% 100%;',
        '}',
        /* Umami 指标行（卡片内部，不再是独立卡片）：左图标胶囊、中间名称、右侧数值 */
        '.ab-umami-card{display:grid !important;grid-template-columns:32px minmax(0,1fr) auto;align-items:center;column-gap:10px;padding:9px 6px;min-width:0;box-sizing:border-box;border-top:1px solid var(--md-outline-variant,#cac4d0);}',
        '.ab-umami-card:first-child{border-top:0;}',
        /* 图标胶囊：圆角方块 + 次色容器背景，比裸图标更稳重、每行视觉锚点一致 */
        '.ab-umami-icon{width:32px !important;height:32px !important;min-width:32px !important;display:inline-flex !important;align-items:center !important;justify-content:center !important;border-radius:10px;background:var(--md-secondary-container,#e8def8);color:var(--md-primary,#6750a4) !important;font-size:18px !important;line-height:1 !important;}',
        '[data-theme=dark] .ab-umami-icon{background:var(--md-dark-surface-container-highest,#484649);color:var(--md-dark-primary,#d0bcff) !important;}',
        '.ab-umami-card-label{font-size:13px;line-height:1.35;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--md-on-surface-variant,#49454f);}',
        '[data-theme=dark] .ab-umami-card-label{color:var(--md-dark-on-surface-variant,#cac4d0);}',
        /* 数值：右对齐 + 等宽数字，多行之间小数点位对齐，便于竖读对比 */
        '.ab-umami-card-value{',
        '  font-size:19px;font-weight:700;line-height:1.2;',
        '  white-space:nowrap;text-align:right;',
        '  font-variant-numeric:tabular-nums;letter-spacing:-.2px;',
        '  color:var(--md-on-surface,#1c1b1f);',
        '}',
        /* 骨架屏状态下固定尺寸，防止加载前后布局跳动 */
        '.ab-umami-card-value.ab-chart-skeleton{',
        '  display:inline-block;width:56px;height:20px;border-radius:6px;',
        '}',
        '[data-theme=dark] .ab-umami-card-value{color:var(--md-dark-on-surface,#e6e1e5);}',
        '@media(max-width:600px){',
        '  .ab-umami-card{padding:8px 4px;column-gap:8px;}',
        '}'
    ].join('');
    document.head.appendChild(abChartStyle);

    /* ============================================================
       自定义卡片内置组件库（ab.ui）
       ------------------------------------------------------------
       供「概要页卡片设置 → 自定义卡片」里填写的 JS 直接调用。
       组件自带 MD3 样式（注入 #ab-ui-style），浅色 / 深色主题自动适配，
       开发者**不需要写任何 CSS**：

         var box = ab.ui.metrics([
             { icon: 'link',            label: '已启用友链', value: '—' },
             { icon: 'pending_actions', label: '待审核',     value: '—', id: 'p' }
         ]);
         ab.ui.mount(card.querySelector('.ab-custom-body'), box);
         ab.ui.footer(card, { text: '管理友链', href: '/admin/xxx.php' });

       完整 API 见 usr/plugins/AdminBeautify/docs/custom-cards.md
       ============================================================ */
    var abUiStyle = document.createElement('style');
    abUiStyle.id = 'ab-ui-style';
    abUiStyle.textContent = [
        /* ---- 色板：每个 tone 只定义 4 个变量，所有组件共用 ---- */
        '.ab-ui-tone-default{--ab-ui-bg:var(--md-surface-container-low,#f7f2fa);--ab-ui-fg:var(--md-on-surface,#1c1b1f);--ab-ui-icon:var(--md-primary,#6750a4);--ab-ui-chip:var(--md-secondary-container,#e8def8);}',
        '[data-theme=dark] .ab-ui-tone-default{--ab-ui-chip:var(--md-dark-surface-container-highest,#484649);--ab-ui-icon:var(--md-dark-primary,#d0bcff);}',
        '.ab-ui-tone-primary{--ab-ui-bg:var(--md-primary-container,#eaddff);--ab-ui-fg:var(--md-on-primary-container,#21005d);--ab-ui-icon:var(--md-on-primary-container,#21005d);--ab-ui-chip:var(--md-primary-container,#eaddff);}',
        '.ab-ui-tone-success{--ab-ui-bg:var(--md-approve-bg,rgba(46,125,50,.12));--ab-ui-fg:var(--md-approve-color,#2e7d32);--ab-ui-icon:var(--md-approve-color,#2e7d32);--ab-ui-chip:var(--md-approve-bg,rgba(46,125,50,.12));}',
        '.ab-ui-tone-warn{--ab-ui-bg:rgba(217,119,6,.12);--ab-ui-fg:#92400e;--ab-ui-icon:#b45309;--ab-ui-chip:rgba(217,119,6,.18);}',
        '[data-theme=dark] .ab-ui-tone-warn{--ab-ui-bg:rgba(251,191,36,.14);--ab-ui-fg:#fcd34d;--ab-ui-icon:#fbbf24;--ab-ui-chip:rgba(251,191,36,.2);}',
        '.ab-ui-tone-danger{--ab-ui-bg:var(--md-error-container,#f9dedc);--ab-ui-fg:var(--md-on-error-container,#410e0b);--ab-ui-icon:var(--md-on-error-container,#410e0b);--ab-ui-chip:var(--md-error-container,#f9dedc);}',
        '.ab-ui-tone-info{--ab-ui-bg:var(--md-surface-container-high,#ece6f0);--ab-ui-fg:var(--md-on-surface,#1c1b1f);--ab-ui-icon:var(--md-primary,#6750a4);--ab-ui-chip:var(--md-surface-container-highest,#e6e0e9);}',
        /* 公共：可点击态 + 骨架屏 */
        '.ab-ui-clickable{cursor:pointer;}',
        '.ab-ui-clickable:hover{box-shadow:0 2px 10px rgba(0,0,0,.10);}',
        '.ab-ui-clickable:focus-visible,.ab-ui-metric:focus-visible,.ab-ui-row:focus-visible,.ab-ui-list-item:focus-visible,.ab-ui-empty:focus-visible{outline:2px solid var(--md-primary,#6750a4);outline-offset:2px;}',
        '.ab-ui-skeleton{display:inline-block !important;width:56px;height:20px;border-radius:6px;',
        '  background:linear-gradient(90deg,var(--md-surface-container-high,#ece6f0) 25%,var(--md-surface-container-highest,#e6e0e9) 50%,var(--md-surface-container-high,#ece6f0) 75%);',
        '  background-size:200% 100%;animation:ab-shimmer 1.5s infinite linear;color:transparent !important;}',
        '[data-theme=dark] .ab-ui-skeleton{background:linear-gradient(90deg,var(--md-dark-surface-container-high,#36343b) 25%,var(--md-dark-surface-container-highest,#484649) 50%,var(--md-dark-surface-container-high,#36343b) 75%);background-size:200% 100%;}',
        /* ---- 指标磁贴：ab.ui.metrics([...]) ---- */
        '.ab-ui-metrics{display:grid !important;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;}',
        '.ab-ui-metrics.ab-ui-cols-2{grid-template-columns:repeat(2,minmax(0,1fr)) !important;}',
        '.ab-ui-metrics.ab-ui-cols-3{grid-template-columns:repeat(3,minmax(0,1fr)) !important;}',
        '.ab-ui-metrics.ab-ui-cols-4{grid-template-columns:repeat(4,minmax(0,1fr)) !important;}',
        '.ab-ui-metric{display:flex !important;flex-direction:column;gap:2px;min-width:0;box-sizing:border-box;',
        '  padding:12px 14px;border-radius:14px;text-decoration:none !important;',
        '  background:var(--ab-ui-bg,var(--md-surface-container-low,#f7f2fa)) !important;',
        '  color:var(--ab-ui-fg,var(--md-on-surface,#1c1b1f)) !important;',
        '  transition:background-color var(--md-transition-duration,.2s),box-shadow var(--md-transition-duration,.2s);}',
        '.ab-ui-metric-icon{font-size:20px !important;line-height:1 !important;color:var(--ab-ui-icon,var(--md-primary,#6750a4)) !important;}',
        '.ab-ui-metric-value{font-size:24px;font-weight:700;line-height:1.15;letter-spacing:-.2px;font-variant-numeric:tabular-nums;color:inherit !important;}',
        '.ab-ui-metric-label{font-size:12px;line-height:1.35;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:inherit !important;opacity:.78;}',
        '.ab-ui-metric-hint{font-size:11px;line-height:1.35;color:inherit !important;opacity:.62;}',
        /* ---- 大号指标：ab.ui.hero({...}) ---- */
        '.ab-ui-hero{display:flex !important;flex-direction:column;gap:3px;min-width:0;box-sizing:border-box;',
        '  padding:16px 18px;border-radius:16px;text-decoration:none !important;',
        '  background:var(--ab-ui-bg,var(--md-surface-container-low,#f7f2fa)) !important;',
        '  color:var(--ab-ui-fg,var(--md-on-surface,#1c1b1f)) !important;}',
        '.ab-ui-hero-icon{font-size:24px !important;line-height:1 !important;color:var(--ab-ui-icon,var(--md-primary,#6750a4)) !important;}',
        '.ab-ui-hero-value{font-size:34px;font-weight:700;line-height:1.1;letter-spacing:-.4px;font-variant-numeric:tabular-nums;color:inherit !important;}',
        '.ab-ui-hero-label{font-size:13px;line-height:1.4;color:inherit !important;opacity:.8;}',
        '.ab-ui-hero-hint{font-size:12px;line-height:1.4;color:inherit !important;opacity:.62;}',
        /* ---- 对齐行列表：ab.ui.rows([...])（与「访问统计」卡片同款） ---- */
        '.ab-ui-rows{display:flex !important;flex-direction:column;margin:0 !important;}',
        '.ab-ui-row{display:grid !important;grid-template-columns:32px minmax(0,1fr) auto;align-items:center;column-gap:10px;',
        '  padding:9px 6px;min-width:0;box-sizing:border-box;text-decoration:none !important;',
        '  border-top:1px solid var(--md-outline-variant,#cac4d0);border-radius:0 !important;',
        '  background:transparent !important;color:var(--ab-ui-fg,var(--md-on-surface,#1c1b1f)) !important;}',
        '.ab-ui-rows>.ab-ui-row:first-child{border-top:0;}',
        '.ab-ui-row-icon{width:32px !important;height:32px !important;min-width:32px !important;display:inline-flex !important;',
        '  align-items:center !important;justify-content:center !important;border-radius:10px;font-size:18px !important;line-height:1 !important;',
        '  background:var(--ab-ui-chip,var(--md-secondary-container,#e8def8)) !important;color:var(--ab-ui-icon,var(--md-primary,#6750a4)) !important;}',
        '.ab-ui-row-label{font-size:13px;line-height:1.35;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:inherit !important;}',
        '.ab-ui-row-value{font-size:19px;font-weight:700;line-height:1.2;white-space:nowrap;text-align:right;letter-spacing:-.2px;font-variant-numeric:tabular-nums;color:inherit !important;}',
        '@media(max-width:600px){.ab-ui-row{padding:8px 4px;column-gap:8px;}}',
        /* ---- 清单：ab.ui.list([...]) ---- */
        '.ab-ui-list{display:flex !important;flex-direction:column;gap:2px;margin:0 !important;}',
        '.ab-ui-list-item{display:flex !important;align-items:center;gap:10px;min-width:0;box-sizing:border-box;',
        '  padding:8px 10px;border-radius:10px;text-decoration:none !important;color:var(--md-on-surface,#1c1b1f) !important;',
        '  transition:background-color var(--md-transition-duration,.2s);}',
        '.ab-ui-list-item:hover{background:var(--md-surface-container-high,#ece6f0) !important;}',
        '.ab-ui-list-icon{flex:none !important;font-size:18px !important;line-height:1 !important;color:var(--md-primary,#6750a4) !important;}',
        '.ab-ui-list-main{display:flex !important;flex-direction:column;min-width:0;flex:1 1 auto;}',
        '.ab-ui-list-label{font-size:13px;line-height:1.4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}',
        '.ab-ui-list-desc{font-size:11.5px;line-height:1.4;color:var(--md-on-surface-variant,#49454f) !important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}',
        '.ab-ui-list-value{flex:none !important;font-size:12.5px;font-weight:600;color:var(--md-on-surface-variant,#49454f) !important;font-variant-numeric:tabular-nums;}',
        /* ---- 徽标 / 按钮 / 提示条 ---- */
        '.ab-ui-badge{display:inline-flex !important;align-items:center;gap:4px;padding:2px 9px;border-radius:999px;',
        '  font-size:11.5px !important;font-weight:600 !important;line-height:1.6 !important;white-space:nowrap;',
        '  background:var(--ab-ui-bg,var(--md-surface-container-high,#ece6f0)) !important;color:var(--ab-ui-fg,var(--md-on-surface,#1c1b1f)) !important;}',
        '.ab-ui-badge.ab-ui-tone-default{--ab-ui-bg:var(--md-surface-container-high,#ece6f0);}',
        '.ab-ui-actions{display:flex !important;flex-wrap:wrap;align-items:center;gap:8px;margin-top:12px;}',
        '.ab-ui-btn{display:inline-flex !important;align-items:center;justify-content:center;gap:6px;',
        '  padding:8px 16px;border-radius:999px;border:0 !important;cursor:pointer;',
        '  font-size:12.5px !important;font-weight:600 !important;line-height:1.5 !important;text-decoration:none !important;',
        '  background:var(--ab-ui-bg,var(--md-primary-container,#eaddff)) !important;color:var(--ab-ui-fg,var(--md-on-primary-container,#21005d)) !important;',
        '  transition:background-color var(--md-transition-duration,.2s),box-shadow var(--md-transition-duration,.2s);}',
        '.ab-ui-btn:hover{box-shadow:0 2px 8px rgba(0,0,0,.12);}',
        '.ab-ui-btn .material-icons-round{font-size:16px !important;line-height:1 !important;color:inherit !important;}',
        '.ab-ui-btn-block{display:flex !important;width:100% !important;}',
        '.ab-ui-notice{display:flex !important;align-items:flex-start;gap:8px;box-sizing:border-box;',
        '  margin-top:12px;padding:10px 14px;border-radius:12px;font-size:12.5px !important;line-height:1.6 !important;',
        '  background:var(--ab-ui-bg,var(--md-surface-container-low,#f7f2fa)) !important;color:var(--ab-ui-fg,var(--md-on-surface,#1c1b1f)) !important;}',
        '.ab-ui-notice-icon{flex:none !important;font-size:18px !important;line-height:1.35 !important;color:inherit !important;}',
        '.ab-ui-notice-text{min-width:0;}',
        /* ---- 空状态：ab.ui.empty({...}) ---- */
        '.ab-ui-empty{display:flex !important;flex-direction:column !important;align-items:center !important;justify-content:center !important;',
        '  gap:6px;box-sizing:border-box;min-height:96px;padding:18px 16px;border-radius:14px;text-align:center;text-decoration:none !important;',
        '  border:1px dashed var(--md-outline-variant,#cac4d0);background:transparent !important;color:var(--md-on-surface-variant,#49454f) !important;}',
        '.ab-ui-empty-icon{display:inline-flex !important;align-items:center !important;justify-content:center !important;',
        '  width:44px !important;height:44px !important;border-radius:50%;font-size:26px !important;line-height:1 !important;',
        '  background:var(--ab-ui-bg,var(--md-primary-container,#eaddff)) !important;color:var(--ab-ui-fg,var(--md-on-primary-container,#21005d)) !important;}',
        '.ab-ui-empty-text{font-size:13.5px;font-weight:600;color:var(--md-on-surface,#1c1b1f) !important;}',
        '.ab-ui-empty-hint{font-size:12px;color:var(--md-on-surface-variant,#49454f) !important;opacity:.85;}',
        '.ab-ui-empty-lg{min-height:150px;gap:10px;padding:22px 16px;}',
        '.ab-ui-empty-lg .ab-ui-empty-icon{width:72px !important;height:72px !important;font-size:42px !important;}',
        '.ab-ui-empty-lg .ab-ui-empty-text{font-size:15px;}',
        '.ab-ui-empty-lg .ab-ui-empty-hint{font-size:12.5px;}',
        /* ---- 卡片底部入口：ab.ui.footer(card, {...}) ----
         * 复用主样式表的 .ab-card-footer；这里只补按钮版重置与吸底兜底 */
        'button.ab-card-footer{width:100% !important;border:0 !important;background:transparent !important;cursor:pointer;font-family:inherit;}',
        '.ab-card-footer.ab-ui-footer{flex:none !important;margin-top:auto !important;}',
        /* ---- 对话框：ab.ui.dialog / ab.ui.confirm ---- */
        '.ab-ui-scrim{position:fixed !important;inset:0;z-index:9998;display:flex !important;align-items:center !important;justify-content:center !important;',
        '  padding:24px;box-sizing:border-box;background:rgba(0,0,0,.32);',
        '  opacity:0;transition:opacity var(--md-transition-duration,.2s) var(--md-transition-easing,cubic-bezier(.2,0,0,1));}',
        '.ab-ui-scrim.ab-ui-scrim-in{opacity:1;}',
        '.ab-ui-dialog{width:100% !important;max-width:min(440px,100%);box-sizing:border-box;padding:24px;border-radius:28px;',
        '  background:var(--md-surface-container-high,#ece6f0) !important;color:var(--md-on-surface,#1c1b1f) !important;',
        '  box-shadow:var(--md-elevation-3);transform:translateY(8px) scale(.98);',
        '  transition:transform var(--md-transition-duration,.2s) var(--md-transition-easing,cubic-bezier(.2,0,0,1));}',
        '.ab-ui-scrim-in .ab-ui-dialog{transform:none;}',
        '.ab-ui-dialog-icon{display:inline-flex !important;align-items:center !important;font-size:22px !important;line-height:1 !important;color:var(--md-primary,#6750a4) !important;flex:none !important;}',
        '.ab-ui-dialog-title{margin:0 0 8px;display:flex !important;align-items:center !important;gap:8px;font-size:16px;font-weight:600;line-height:1.5;}',
        '.ab-ui-dialog-text{margin:0;font-size:13.5px;line-height:1.7;color:var(--md-on-surface-variant,#49454f) !important;}',
        '.ab-ui-dialog-body{margin-top:12px;}',
        '.ab-ui-dialog-actions{display:flex !important;flex-wrap:wrap;justify-content:flex-end !important;gap:8px;margin-top:22px;}',
        /* ---- 开关：ab.ui.switch({label, checked, onChange}) ---- */
        '.ab-ui-switch{display:flex !important;align-items:center;gap:10px;padding:8px 0;cursor:pointer;font-size:13px;user-select:none;}',
        '.ab-ui-switch-track{flex:none !important;position:relative;width:44px;height:24px;border-radius:999px;',
        '  background:var(--md-surface-container-highest,#e6e0e9) !important;transition:background-color var(--md-transition-duration,.2s);}',
        '.ab-ui-switch-track::after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;',
        '  background:var(--md-outline,#79747e) !important;transition:transform var(--md-transition-duration,.2s),background-color var(--md-transition-duration,.2s);}',
        '.ab-ui-switch.is-on .ab-ui-switch-track{background:var(--md-primary,#6750a4) !important;}',
        '.ab-ui-switch.is-on .ab-ui-switch-track::after{transform:translateX(20px);background:var(--md-on-primary,#fff) !important;}',
        '.ab-ui-switch-label{flex:1 1 auto;min-width:0;}',
        /* ---- 分段标签页：ab.ui.tabs([...]) ---- */
        '.ab-ui-tabs{display:flex !important;gap:4px;border-bottom:1px solid var(--md-outline-variant,#cac4d0);margin-bottom:10px;}',
        '.ab-ui-tab{flex:1 1 0;display:inline-flex !important;align-items:center !important;justify-content:center !important;gap:5px;',
        '  padding:9px 6px;border:0 !important;background:transparent !important;cursor:pointer;font-family:inherit;',
        '  font-size:12.5px !important;font-weight:600 !important;color:var(--md-on-surface-variant,#49454f) !important;',
        '  border-bottom:2px solid transparent !important;transition:color var(--md-transition-duration,.2s),border-color var(--md-transition-duration,.2s);}',
        '.ab-ui-tab.is-active{color:var(--md-primary,#6750a4) !important;border-bottom-color:var(--md-primary,#6750a4) !important;}',
        '.ab-ui-tab .material-icons-round{font-size:16px !important;line-height:1 !important;color:inherit !important;}',
        /* ---- 进度条：ab.ui.progress({value, max, label}) ---- */
        '.ab-ui-progress{display:flex !important;flex-direction:column !important;gap:6px;margin-top:12px;}',
        '.ab-ui-progress-head{display:flex !important;justify-content:space-between !important;gap:8px;font-size:12px;',
        '  color:var(--md-on-surface-variant,#49454f) !important;}',
        '.ab-ui-progress-track{height:6px;border-radius:999px;background:var(--md-surface-container-highest,#e6e0e9) !important;overflow:hidden;}',
        '.ab-ui-progress-bar{height:100%;width:0;border-radius:999px;background:var(--ab-ui-bg,var(--md-primary,#6750a4)) !important;',
        '  transition:width var(--md-transition-duration,.2s) var(--md-transition-easing,cubic-bezier(.2,0,0,1));}',
        /* ---- 文本字段 / 下拉：ab.ui.field / input / select ---- */
        '.ab-ui-field{display:flex !important;flex-direction:column !important;gap:5px;margin-top:12px;}',
        '.ab-ui-field-label{font-size:11.5px;font-weight:600;color:var(--md-on-surface-variant,#49454f) !important;}',
        '.ab-ui-field-input{box-sizing:border-box;width:100% !important;padding:10px 14px;border-radius:12px;font-family:inherit;',
        '  border:1px solid var(--md-outline,#79747e) !important;background:var(--md-surface-container-lowest,#fff) !important;',
        '  color:var(--md-on-surface,#1c1b1f) !important;font-size:13px !important;line-height:1.5;outline:none;}',
        'textarea.ab-ui-field-input{min-height:76px;resize:vertical;}',
        '.ab-ui-field-input:focus{border-color:var(--md-primary,#6750a4) !important;box-shadow:0 0 0 3px var(--md-primary-container,#eaddff);}',
        '.ab-ui-field-hint{font-size:11.5px;color:var(--md-on-surface-variant,#49454f) !important;opacity:.85;}',
        /* ---- 卡片脚本出错时的内联错误块（插件注入）---- */
        '.ab-custom-error{display:flex !important;align-items:flex-start;gap:8px;box-sizing:border-box;padding:10px 14px;border-radius:12px;',
        '  font-size:12.5px !important;line-height:1.6 !important;background:var(--md-error-container,#f9dedc) !important;',
        '  color:var(--md-on-error-container,#410e0b) !important;}',
        '.ab-custom-error .material-icons-round{flex:none !important;font-size:18px !important;line-height:1.35 !important;color:inherit !important;}'
    ].join('');
    document.head.appendChild(abUiStyle);

    var abUi = (function () {
        var TONES = ['default', 'primary', 'success', 'warn', 'danger', 'info'];

        /* ---------- 基础工具 ---------- */
        function el(tag, cls, text) {
            var n = document.createElement(tag);
            if (cls) n.className = cls;
            if (text != null) n.textContent = String(text);
            return n;
        }
        function icon(name, cls) {
            return el('span', 'material-icons-round' + (cls ? ' ' + cls : ''), name || 'widgets');
        }
        function isEl(v) { return !!v && v.nodeType === 1; }
        function resolveRef(target) {
            if (!target) return null;
            if (typeof target === 'string') {
                try { return document.querySelector(target); } catch (e) { return null; }
            }
            return target;
        }
        function append(host, content) {
            if (!host || content == null || content === false) return host;
            if (Array.isArray(content)) {
                for (var i = 0; i < content.length; i++) append(host, content[i]);
                return host;
            }
            if (isEl(content)) { host.appendChild(content); return host; }
            if (typeof content === 'string' && content) host.insertAdjacentHTML('beforeend', content);
            return host;
        }
        /** 清空目标并写入内容（元素 / 元素数组 / HTML 字符串） */
        function mount(target, content) {
            var host = resolveRef(target);
            if (!host) return null;
            while (host.firstChild) host.removeChild(host.firstChild);
            append(host, content);
            return host;
        }
        function normalizeTone(tone) {
            return TONES.indexOf(String(tone || '').replace(/^ab-ui-tone-/, '')) !== -1
                ? String(tone || '').replace(/^ab-ui-tone-/, '') : 'default';
        }
        function applyAttrs(node, attrs) {
            if (!attrs) return;
            for (var k in attrs) {
                if (Object.prototype.hasOwnProperty.call(attrs, k)) node.setAttribute(k, attrs[k]);
            }
        }
        /* href / onClick 统一挂载：<a> 走原生跳转，其它元素走 JS 跳转 */
        var abUiApi = null;              // 指向最终对外暴露的对象（见 IIFE 末尾）
        function navigate(href) {
            location.href = href;
        }
        function setLink(node, href, onClick) {
            if (href) {
                if (node.tagName === 'A') node.setAttribute('href', href);
                else node.__abHref = href;
                node.classList.add('ab-ui-clickable');
                if (!node.getAttribute('title')) node.setAttribute('title', '点击前往');
            }
            if (typeof onClick === 'function') node.__abOnClick = onClick;
            if (!node.__abLinkBound) {
                node.__abLinkBound = true;
                node.addEventListener('click', function (e) {
                    if (e.target && e.target.closest && e.target.closest('a[href]')) return;
                    if (typeof node.__abOnClick === 'function') { node.__abOnClick(e); return; }
                    if (!node.__abHref) return;
                    /* 走对外暴露的 navigate，方便开发者/测试覆盖跳转方式 */
                    var fn = (abUiApi && typeof abUiApi.navigate === 'function') ? abUiApi.navigate : navigate;
                    fn(node.__abHref);
                });
            }
        }

        /* ---------- 指标（tile / row / hero 共用） ---------- */
        var PREFIX = { metric: 'ab-ui-metric', row: 'ab-ui-row', hero: 'ab-ui-hero' };
        function makeStat(kind, item) {
            item = item || {};
            var pre = PREFIX[kind] || PREFIX.metric;
            var node = el(item.href ? 'a' : 'div', pre + ' ab-ui-tone-' + normalizeTone(item.tone), null);
            if (item.id) node.id = item.id;
            applyAttrs(node, item.attrs);
            var ic = icon(item.icon || 'insights', pre + '-icon');
            var value = el('span', pre + '-value', item.value == null ? '—' : item.value);
            var label = el('span', pre + '-label', item.label || '');
            var hint = item.hint ? el('span', pre + '-hint', item.hint) : null;
            if (kind === 'row') { node.appendChild(ic); node.appendChild(label); node.appendChild(value); }
            else {
                node.appendChild(ic); node.appendChild(value); node.appendChild(label);
                if (hint) node.appendChild(hint);
            }
            if (item.skeleton) value.classList.add('ab-ui-skeleton');
            node.__abParts = { icon: ic, value: value, label: label, hint: hint, kind: kind };
            if (item.href) setLink(node, item.href, null);
            setLink(node, null, item.onClick);
            /** 局部更新：node.update({ value, label, hint, icon, tone, href, onClick, skeleton }) */
            node.update = function (patch) { return updateStat(node, patch); };
            return node;
        }
        function updateStat(node, patch) {
            patch = patch || {};
            var p = node.__abParts || {};
            if ('value' in patch && p.value) {
                p.value.textContent = patch.value == null ? '—' : String(patch.value);
                p.value.classList.remove('ab-ui-skeleton');
            }
            if ('label' in patch && p.label) p.label.textContent = String(patch.label);
            if ('icon' in patch && p.icon) p.icon.textContent = String(patch.icon || 'insights');
            if ('hint' in patch && p.hint) p.hint.textContent = String(patch.hint);
            if ('skeleton' in patch && p.value) {
                if (patch.skeleton) p.value.classList.add('ab-ui-skeleton');
                else p.value.classList.remove('ab-ui-skeleton');
            }
            if ('tone' in patch) {
                var cls = (' ' + node.className + ' ').replace(/\sab-ui-tone-[a-z]+\s/g, ' ');
                node.className = (cls + ' ab-ui-tone-' + normalizeTone(patch.tone)).replace(/\s+/g, ' ').replace(/^\s|\s$/g, '');
            }
            if ('href' in patch || 'onClick' in patch) setLink(node, patch.href, patch.onClick);
            return node;
        }
        /* ---------- 列表条目 ---------- */
        function listItem(item) {
            item = item || {};
            var node = el(item.href ? 'a' : 'div', 'ab-ui-list-item', null);
            if (item.id) node.id = item.id;
            applyAttrs(node, item.attrs);
            node.appendChild(icon(item.icon || 'chevron_right', 'ab-ui-list-icon'));
            var main = el('span', 'ab-ui-list-main');
            main.appendChild(el('span', 'ab-ui-list-label', item.label || ''));
            if (item.desc) main.appendChild(el('span', 'ab-ui-list-desc', item.desc));
            node.appendChild(main);
            if (item.value != null) node.appendChild(el('span', 'ab-ui-list-value', item.value));
            setLink(node, item.href, item.onClick);
            return node;
        }

        /* ---------- 组装式组件 ---------- */
        function statOf(kind) {
            return function (item) { return makeStat(kind, item); };
        }
        function statList(kind, items, opts) {
            opts = opts || {};
            var wrap = el('div', kind === 'row' ? 'ab-ui-rows' : 'ab-ui-metrics', null);
            if (opts.id) wrap.id = opts.id;
            if (opts.cols && kind !== 'row') wrap.classList.add('ab-ui-cols-' + opts.cols);
            if (opts.className) wrap.classList.add(opts.className);
            items = items || [];
            for (var i = 0; i < items.length; i++) {
                /* 允许传入已经创建好的指标元素（便于先建好、拿到数据后再 update） */
                wrap.appendChild(isEl(items[i]) ? items[i] : makeStat(kind, items[i]));
            }
            return wrap;
        }
        function badge(text, tone) {
            return el('span', 'ab-ui-badge ab-ui-tone-' + normalizeTone(tone), text);
        }
        function button(opts) {
            opts = opts || {};
            var node = el(opts.href ? 'a' : 'button', 'ab-ui-btn ab-ui-tone-' + normalizeTone(opts.tone || 'primary'), null);
            if (!opts.href) node.type = 'button';
            if (opts.block) node.classList.add('ab-ui-btn-block');
            if (opts.id) node.id = opts.id;
            applyAttrs(node, opts.attrs);
            if (opts.icon) node.appendChild(icon(opts.icon, null));
            node.appendChild(el('span', 'ab-ui-btn-text', opts.text || '操作'));
            setLink(node, opts.href, opts.onClick);
            return node;
        }
        function actions(list) {
            var wrap = el('div', 'ab-ui-actions', null);
            append(wrap, list);
            return wrap;
        }
        function notice(opts) {
            opts = (typeof opts === 'string') ? { text: opts } : (opts || {});
            var node = el('div', 'ab-ui-notice ab-ui-tone-' + normalizeTone(opts.tone), null);
            if (opts.id) node.id = opts.id;
            node.appendChild(icon(opts.icon || 'info', 'ab-ui-notice-icon'));
            var text = el('div', 'ab-ui-notice-text', opts.text || '');
            node.appendChild(text);
            node.__abText = text;
            node.update = function (patch) {
                patch = patch || {};
                if ('text' in patch) text.textContent = String(patch.text);
                if ('icon' in patch) node.firstChild.textContent = String(patch.icon || 'info');
                if ('tone' in patch) {
                    var cls = (' ' + node.className + ' ').replace(/\sab-ui-tone-[a-z]+\s/g, ' ');
                    node.className = (cls + ' ab-ui-tone-' + normalizeTone(patch.tone)).replace(/\s+/g, ' ').replace(/^\s|\s$/g, '');
                }
                return node;
            };
            return node;
        }
        function empty(opts) {
            opts = opts || {};
            var node = el(opts.href ? 'a' : 'div',
                'ab-ui-empty ab-ui-tone-' + normalizeTone(opts.tone || 'primary') + (opts.size === 'lg' ? ' ab-ui-empty-lg' : ''), null);
            if (opts.id) node.id = opts.id;
            applyAttrs(node, opts.attrs);
            node.appendChild(icon(opts.icon || 'inbox', 'ab-ui-empty-icon'));
            node.appendChild(el('p', 'ab-ui-empty-text', opts.text || '暂无数据'));
            if (opts.hint) node.appendChild(el('p', 'ab-ui-empty-hint', opts.hint));
            setLink(node, opts.href, opts.onClick);
            return node;
        }
        /** 卡片底部入口（复用 .ab-card-footer，自动吸底） */
        function footer(cardRef, opts) {
            opts = (typeof opts === 'string') ? { text: opts } : (opts || {});
            var card = resolveRef(cardRef);
            if (!card) return null;
            var old = card.querySelector('.ab-card-footer');
            if (old && old.parentNode === card) card.removeChild(old);
            var node = el(opts.href ? 'a' : 'button',
                'ab-card-footer ab-ui-footer ab-ui-tone-' + normalizeTone(opts.tone || 'primary'), null);
            if (!opts.href) node.type = 'button';
            if (opts.id) node.id = opts.id;
            applyAttrs(node, opts.attrs);
            node.appendChild(el('span', 'ab-card-footer-text', opts.text || '查看全部'));
            node.appendChild(icon(opts.icon || 'arrow_forward', 'ab-card-footer-icon'));
            card.appendChild(node);
            setLink(node, opts.href, opts.onClick);
            return node;
        }

        /* ---------- 数值工具 ---------- */
        function num(n, compact) {
            n = Number(n);
            if (!isFinite(n)) return '0';
            if (compact && Math.abs(n) >= 10000) return (n / 10000).toFixed(1) + 'w';
            return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        function setValue(target, value) {
            var node = resolveRef(target);
            if (!node) return null;
            var box = (node.__abParts && node.__abParts.value)
                ? node.__abParts.value
                : node.querySelector('.ab-ui-metric-value,.ab-ui-row-value,.ab-ui-hero-value,[data-ab-value]');
            if (!box) return null;
            box.textContent = value == null ? '—' : String(value);
            box.classList.remove('ab-ui-skeleton');
            return box;
        }
        function set(target, patch) {
            var node = resolveRef(target);
            if (!node) return null;
            if (typeof node.update === 'function') return node.update(patch);
            if (patch && 'value' in patch) return setValue(node, patch.value);
            return node;
        }

        /* ---------- 同源请求（自动带后台登录态） ---------- */
        function request(url, opts) {
            opts = opts || {};
            return fetch(url, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                method: opts.method || 'GET',
                body: opts.body || undefined
            }).then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return opts.json ? res.json() : res.text();
            });
        }
        function getText(url, opts) { return request(url, opts || {}); }
        function getJSON(url, opts) {
            var o = {};
            for (var k in (opts || {})) if (Object.prototype.hasOwnProperty.call(opts, k)) o[k] = opts[k];
            o.json = true;
            return request(url, o);
        }
        function getDoc(url, opts) {
            return getText(url, opts).then(function (html) {
                return new DOMParser().parseFromString(html, 'text/html');
            });
        }

        /* ---------- 对话框：ab.ui.dialog({...}) ---------- */
        function dialog(opts) {
            opts = (typeof opts === 'string') ? { text: opts } : (opts || {});
            var scrim = el('div', 'ab-ui-scrim', null);
            var box = el('div', 'ab-ui-dialog', null);
            box.setAttribute('role', 'dialog');
            box.setAttribute('aria-modal', 'true');
            if (opts.id) box.id = opts.id;
            scrim.appendChild(box);

            if (opts.title) {
                var head = el('div', 'ab-ui-dialog-title', null);
                if (opts.icon) head.appendChild(icon(opts.icon, 'ab-ui-dialog-icon'));
                head.appendChild(el('span', null, opts.title));
                box.appendChild(head);
            }
            if (opts.text) box.appendChild(el('p', 'ab-ui-dialog-text', opts.text));
            if (opts.body) {
                var bodyBox = el('div', 'ab-ui-dialog-body', null);
                append(bodyBox, opts.body);
                box.appendChild(bodyBox);
            }
            var actionRow = el('div', 'ab-ui-dialog-actions', null);
            box.appendChild(actionRow);

            var closed = false;
            function close(value) {
                if (closed) return;
                closed = true;
                document.removeEventListener('keydown', onKey);
                scrim.classList.remove('ab-ui-scrim-in');
                var kill = function () { if (scrim.parentNode) scrim.parentNode.removeChild(scrim); };
                if (window.setTimeout) window.setTimeout(kill, 220); else kill();
                if (typeof opts.onClose === 'function') opts.onClose(value);
            }
            function onKey(e) {
                if (e.key === 'Escape' || e.key === 'Esc') { e.preventDefault(); close(null); }
            }

            var actionList = (opts.actions || []).slice();
            if (!actionList.length) actionList.push({ text: '知道了' });
            for (var ai = 0; ai < actionList.length; ai++) {
                (function (a) {
                    actionRow.appendChild(button({
                        text: a.text || '操作',
                        icon: a.icon,
                        tone: a.tone || (a.primary ? 'primary' : 'info'),
                        onClick: function () {
                            var v = (a.value === undefined) ? true : a.value;
                            close(v);
                            if (typeof a.onClick === 'function') a.onClick(v);
                        }
                    }));
                })(actionList[ai]);
            }

            if (opts.dismissible !== false) {
                scrim.addEventListener('click', function (e) { if (e.target === scrim) close(null); });
            }
            document.addEventListener('keydown', onKey);
            document.body.appendChild(scrim);
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(function () { scrim.classList.add('ab-ui-scrim-in'); });
            } else {
                scrim.classList.add('ab-ui-scrim-in');
            }
            return { el: box, scrim: scrim, close: close };
        }

        /** 确认框：返回 Promise<boolean>（确定 true / 取消 false） */
        function confirmDialog(opts) {
            opts = (typeof opts === 'string') ? { text: opts } : (opts || {});
            return new Promise(function (resolve) {
                dialog({
                    title: opts.title || '确认操作',
                    icon: opts.icon || 'help_outline',
                    text: opts.text || '',
                    body: opts.body,
                    actions: [
                        { text: opts.cancelText || '取消', tone: 'info', value: false },
                        { text: opts.okText || '确定', tone: opts.tone || 'danger', value: true }
                    ],
                    onClose: function (v) { resolve(v === true); }
                });
            });
        }

        /* ---------- 开关：ab.ui.switch({label, checked, onChange}) ---------- */
        function switchControl(opts) {
            opts = (typeof opts === 'string') ? { label: opts } : (opts || {});
            var node = el('div', 'ab-ui-switch', null);
            if (opts.id) node.id = opts.id;
            node.setAttribute('role', 'switch');
            node.setAttribute('tabindex', '0');
            node.appendChild(el('span', 'ab-ui-switch-track', null));
            node.appendChild(el('span', 'ab-ui-switch-label', opts.label || ''));
            var state = !!opts.checked;
            function apply(next, silent) {
                state = !!next;
                node.classList.toggle('is-on', state);
                node.setAttribute('aria-checked', state ? 'true' : 'false');
                if (!silent && typeof opts.onChange === 'function') opts.onChange(state);
            }
            function toggle() { apply(!state); }
            apply(state, true);
            node.addEventListener('click', toggle);
            node.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') { e.preventDefault(); toggle(); }
            });
            node.setValue = function (next) { apply(next, true); return node; };
            node.getValue = function () { return state; };
            node.update = function (patch) {
                patch = patch || {};
                if ('checked' in patch) apply(patch.checked, true);
                if ('label' in patch) node.lastChild.textContent = String(patch.label || '');
                return node;
            };
            return node;
        }

        /* ---------- 分段标签页：ab.ui.tabs([{id,label,icon}], {active, onChange}) ---------- */
        function tabs(items, opts) {
            opts = opts || {};
            var wrap = el('div', 'ab-ui-tabs', null);
            if (opts.id) wrap.id = opts.id;
            var nodes = [];
            items = items || [];
            function keyOf(it, i) { return (it && it.id != null) ? String(it.id) : String(i); }
            for (var i = 0; i < items.length; i++) {
                var it = (typeof items[i] === 'string') ? { label: items[i] } : (items[i] || {});
                var btn = el('button', 'ab-ui-tab', null);
                btn.type = 'button';
                if (it.icon) btn.appendChild(icon(it.icon, null));
                btn.appendChild(el('span', null, it.label || ('选项 ' + (i + 1))));
                nodes.push({ node: btn, key: keyOf(it, i) });
                (function (key) {
                    btn.addEventListener('click', function () { wrap.setActive(key, true); });
                })(nodes[i].key);
                wrap.appendChild(btn);
            }
            wrap.setActive = function (id, fire) {
                var hit = null;
                for (var j = 0; j < nodes.length; j++) {
                    var on = String(nodes[j].key) === String(id);
                    nodes[j].node.classList.toggle('is-active', on);
                    if (on) hit = nodes[j].key;
                }
                if (fire && typeof opts.onChange === 'function') opts.onChange(hit, wrap);
                return hit;
            };
            wrap.getActive = function () {
                for (var j = 0; j < nodes.length; j++) {
                    if (nodes[j].node.classList.contains('is-active')) return nodes[j].key;
                }
                return null;
            };
            wrap.setActive(opts.active != null ? opts.active : (nodes[0] ? nodes[0].key : null), false);
            return wrap;
        }

        /* ---------- 进度条：ab.ui.progress({value, max, label, tone}) ---------- */
        function progress(opts) {
            opts = (typeof opts === 'number') ? { value: opts } : (opts || {});
            var node = el('div', 'ab-ui-progress ab-ui-tone-' + normalizeTone(opts.tone || 'primary'), null);
            if (opts.id) node.id = opts.id;
            var head = el('div', 'ab-ui-progress-head', null);
            var labelEl = el('span', 'ab-ui-progress-label', opts.label || '');
            var valueEl = el('span', 'ab-ui-progress-value', '');
            head.appendChild(labelEl);
            head.appendChild(valueEl);
            var track = el('div', 'ab-ui-progress-track', null);
            var bar = el('div', 'ab-ui-progress-bar', null);
            track.appendChild(bar);
            node.appendChild(head);
            node.appendChild(track);

            var max = (opts.max || 100);
            var val = (opts.value == null ? 0 : opts.value);
            function paint() {
                var pct = (max > 0) ? Math.max(0, Math.min(100, val / max * 100)) : 0;
                bar.style.width = pct + '%';
                valueEl.textContent = opts.hideValue ? '' : (Math.round(pct) + '%');
            }
            paint();
            node.setValue = function (next) { val = (next == null ? 0 : next); paint(); return node; };
            node.getValue = function () { return val; };
            node.update = function (patch) {
                patch = patch || {};
                if ('value' in patch) val = (patch.value == null ? 0 : patch.value);
                if ('max' in patch) max = (patch.max || 100);
                if ('label' in patch) labelEl.textContent = String(patch.label || '');
                if ('tone' in patch) {
                    for (var t = 0; t < TONES.length; t++) node.classList.remove('ab-ui-tone-' + TONES[t]);
                    node.classList.add('ab-ui-tone-' + normalizeTone(patch.tone));
                }
                paint();
                return node;
            };
            return node;
        }

        /* ---------- 文本字段 / 下拉：ab.ui.field / input / select ---------- */
        function field(opts) {
            opts = (typeof opts === 'string') ? { label: opts } : (opts || {});
            var node = el('div', 'ab-ui-field', null);
            if (opts.id) node.id = opts.id;
            if (opts.label) node.appendChild(el('span', 'ab-ui-field-label', opts.label));

            var input;
            if (opts.options) {
                input = el('select', 'ab-ui-field-input', null);
                var list = opts.options || [];
                for (var i = 0; i < list.length; i++) {
                    var o = (typeof list[i] === 'string') ? { value: list[i], label: list[i] } : (list[i] || {});
                    var opt = el('option', null, (o.label == null ? o.value : o.label));
                    opt.value = (o.value == null ? '' : String(o.value));
                    input.appendChild(opt);
                }
                if (opts.value != null) input.value = String(opts.value);
            } else if (opts.type === 'textarea') {
                input = el('textarea', 'ab-ui-field-input', null);
                input.value = (opts.value == null ? '' : String(opts.value));
                if (opts.placeholder) input.placeholder = opts.placeholder;
            } else {
                input = el('input', 'ab-ui-field-input', null);
                input.type = opts.type || 'text';
                input.value = (opts.value == null ? '' : String(opts.value));
                if (opts.placeholder) input.placeholder = opts.placeholder;
            }
            node.appendChild(input);
            if (opts.hint) node.appendChild(el('span', 'ab-ui-field-hint', opts.hint));

            input.addEventListener('change', function () {
                if (typeof opts.onChange === 'function') opts.onChange(input.value, node);
            });
            if (!opts.options) {
                input.addEventListener('input', function () {
                    if (typeof opts.onInput === 'function') opts.onInput(input.value, node);
                });
            }
            node.input = input;
            node.getValue = function () { return input.value; };
            node.setValue = function (v) { input.value = (v == null ? '' : String(v)); return node; };
            return node;
        }

        abUiApi = {
            version: '1.1',
            tones: TONES.slice(),
            el: el,
            icon: icon,
            append: append,
            mount: mount,
            metric: statOf('metric'),
            metrics: function (items, opts) { return statList('metric', items, opts); },
            row: statOf('row'),
            rows: function (items, opts) { return statList('row', items, opts); },
            hero: statOf('hero'),
            listItem: listItem,
            list: function (items, opts) {
                opts = opts || {};
                var wrap = el('div', 'ab-ui-list', null);
                if (opts.id) wrap.id = opts.id;
                if (opts.className) wrap.classList.add(opts.className);
                items = items || [];
                for (var i = 0; i < items.length; i++) {
                    wrap.appendChild(isEl(items[i]) ? items[i] : listItem(items[i]));
                }
                return wrap;
            },
            badge: badge,
            button: button,
            actions: actions,
            notice: notice,
            empty: empty,
            footer: footer,
            dialog: dialog,
            confirm: confirmDialog,
            switch: switchControl,
            tabs: tabs,
            progress: progress,
            field: field,
            input: field,
            select: field,
            num: num,
            navigate: navigate,
            setValue: setValue,
            set: set,
            getText: getText,
            getJSON: getJSON,
            getDoc: getDoc
        };
        return abUiApi;
    })();
    AdminBeautify.ui = abUi;

    /* ============================================================
       自定义卡片数据存储（ab.data）与只读数据查询（ab.db）
       ------------------------------------------------------------
       服务端实现：Plugin.php::cardDataSave / cardDataGet / cardDataList /
                   cardDataDelete / dbReadTable
       服务端接口：/action/admin-beautify?do=save-card-data | get-card-data |
                   list-card-data | delete-card-data | db-read
       鉴权：必须是已登录管理员 + 有效 CSRF token（本封装自动附带 &_=<token>）
       数据隔离：按登录用户 uid 隔离，前端无法指定 uid
       ============================================================ */
    var abRequest = function (doName, params, method) {
        method = (method || 'GET').toUpperCase();
        var cfg = window.__AB_AJAX__ || {};
        if (!cfg.url) return Promise.reject(new Error('AJAX URL 未注入，请检查插件配置'));
        var url = cfg.url + '?do=' + encodeURIComponent(doName);
        if (cfg.token) url += '&_=' + encodeURIComponent(cfg.token);
        var body = null;
        if (method === 'POST') {
            body = new URLSearchParams();
            Object.keys(params || {}).forEach(function (k) {
                body.append(k, params[k] == null ? '' : String(params[k]));
            });
        } else {
            Object.keys(params || {}).forEach(function (k) {
                url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k] == null ? '' : params[k]);
            });
        }
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(function (res) {
            return res.json().catch(function () {
                throw new Error('服务端返回了非 JSON 响应（HTTP ' + res.status + '）');
            });
        }).then(function (json) {
            if (!json || json.code !== 0) {
                var err = new Error((json && json.message) || '请求失败');
                err.code = json && json.code;
                throw err;
            }
            return json.data;
        });
    };

    var abData = (function () {
        var KEY_RE = /^[A-Za-z0-9_-]{1,64}$/;
        function checkKey(key) {
            if (typeof key !== 'string' || !KEY_RE.test(key)) {
                throw new Error('数据键非法：只允许 A-Za-z0-9_- 且长度 1~64（不要带 card- 前缀）');
            }
            if (key.toLowerCase().indexOf('card-') === 0) {
                throw new Error('数据键不要带 card- 前缀，服务端会自动拼接');
            }
            return key;
        }
        /* scope: 'user'（默认，仅自己）| 'site'（全站共享，所有管理员同一份）*/
        function scopeOf(opts) {
            var s = (opts && opts.scope) ? String(opts.scope).toLowerCase() : 'user';
            if (s !== 'user' && s !== 'site') {
                throw new Error("scope 只能是 'user'（仅自己）或 'site'（全站共享）");
            }
            return s;
        }
        function withScope(params, opts) {
            var p = params || {};
            if (scopeOf(opts) === 'site') p.scope = 'site';
            return p;
        }
        function save(key, value, opts) {
            var json = (typeof value === 'string') ? value : JSON.stringify(value === undefined ? null : value);
            return abRequest('save-card-data', withScope({ key: checkKey(key), content: json }, opts), 'POST');
        }
        function getRow(key, opts) {
            return abRequest('get-card-data', withScope({ key: checkKey(key) }, opts), 'GET');
        }
        function get(key, fallback, opts) {
            return getRow(key, opts).then(function (data) {
                if (data && data.exists) return data.json;
                return (fallback === undefined) ? null : fallback;
            });
        }
        function list(opts) {
            return abRequest('list-card-data', withScope({}, opts), 'GET');
        }
        function remove(key, opts) {
            return abRequest('delete-card-data', withScope({ key: checkKey(key) }, opts), 'POST');
        }

        /* ---- 站点级（全站共享）简写 ---- */
        function siteSave(key, value)   { return save(key, value, { scope: 'site' }); }
        function siteGet(key, fallback) { return get(key, fallback, { scope: 'site' }); }
        function siteGetRow(key)        { return getRow(key, { scope: 'site' }); }
        function siteList()             { return list({ scope: 'site' }); }
        function siteRemove(key)        { return remove(key, { scope: 'site' }); }

        /* ---- 带 TTL 的缓存：ab.data.getCached(key, ttlMs, loader, opts) ----
         * 缓存命中就不调 loader；loader 可以是函数（返回 Promise 或值）或直接传值。
         * 例如：ab.data.getCached('gh-stars', 10*60*1000, function () {
         *          return ab.ui.getJSON('https://api.github.com/repos/lhl77/…');
         *       }).then(function (d) { … }); */
        function getCached(key, ttl, loader, opts) {
            ttl = (typeof ttl === 'number' && ttl > 0) ? ttl : 5 * 60 * 1000;
            return get(key, null, opts).then(function (cached) {
                if (cached && cached.t && ('d' in cached) && (Date.now() - cached.t) < ttl) return cached.d;
                return Promise.resolve(typeof loader === 'function' ? loader() : loader).then(function (data) {
                    var payload = { t: Date.now(), d: (data === undefined ? null : data) };
                    return save(key, payload, opts).then(function () { return payload.d; });
                });
            });
        }
        function clearCached(key, opts) { return remove(key, opts); }

        return {
            save: save, get: get, getRow: getRow, list: list, remove: remove,
            siteSave: siteSave, siteGet: siteGet, siteGetRow: siteGetRow,
            siteList: siteList, siteRemove: siteRemove,
            getCached: getCached, clearCached: clearCached
        };
    })();

    var abDb = {
        /** 只读查询（表名不带站点前缀；只能读，不能写） */
        read: function (table, query) {
            if (typeof table !== 'string' || !/^[A-Za-z0-9_]{1,64}$/.test(table)) {
                return Promise.reject(new Error('表名非法：只允许字母 / 数字 / 下划线，且不要带站点前缀'));
            }
            return abRequest('db-read', {
                table: table,
                query: query == null ? '' : (typeof query === 'string' ? query : JSON.stringify(query))
            }, 'GET');
        }
    };

    /* ============================================================
       开发者工具集：notify / copy / format / chart / 卡片生命周期
       ============================================================ */

    /* ---- 轻提示：ab.notify(text, tone, ms) ---- */
    var NOTIFY_TYPE = {
        default: 'info', primary: 'info', info: 'info',
        success: 'success', warn: 'warn', warning: 'warn',
        danger: 'error', error: 'error'
    };
    function abNotify(text, tone, ms) {
        if (text == null || text === '') return null;
        if (typeof AdminBeautify.showNotice !== 'function') return null;
        var type = NOTIFY_TYPE[String(tone || 'info').toLowerCase()] || 'info';
        return AdminBeautify.showNotice(String(text), type, typeof ms === 'number' ? ms : 2600);
    }

    /* ---- 剪贴板：ab.copy(text) → Promise<boolean> ---- */
    function legacyCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', 'readonly');
        ta.style.cssText = 'position:fixed;top:-1000px;left:-1000px;opacity:0;';
        document.body.appendChild(ta);
        var ok = false;
        try {
            ta.select();
            ok = document.execCommand('copy');
        } catch (e) { ok = false; }
        if (ta.parentNode) ta.parentNode.removeChild(ta);
        return ok;
    }
    function abCopy(text) {
        text = (text == null) ? '' : String(text);
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).then(function () { return true; }, function () { return legacyCopy(text); });
        }
        return Promise.resolve(legacyCopy(text));
    }
    /** 复制并顺带轻提示：ab.copyText(text, tipText) */
    function abCopyText(text, tip) {
        return abCopy(text).then(function (ok) {
            abNotify(ok ? (tip || '已复制到剪贴板') : '复制失败，请手动选择复制', ok ? 'success' : 'error');
            return ok;
        });
    }

    /* ---- 格式化：ab.format.{bytes,date,relative,duration,num} ---- */
    function toDate(v) {
        if (v == null || v === '') return null;
        if (v instanceof Date) return isNaN(v.getTime()) ? null : v;
        if (typeof v === 'number' || /^\d+$/.test(String(v).trim())) {
            var n = parseInt(v, 10);
            if (!isFinite(n) || n <= 0) return null;
            return new Date(n < 100000000000 ? n * 1000 : n);   // 秒 / 毫秒自动判别
        }
        var s = String(v).trim();
        var m = s.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})(?:[ T](\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?/);
        if (m) return new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0));
        var d = new Date(s.replace(/-/g, '/'));
        return isNaN(d.getTime()) ? null : d;
    }
    function pad2(n) { return (n < 10 ? '0' : '') + n; }
    var abFormat = {
        /** 1536 → "1.5 KB" */
        bytes: function (bytes, digits) {
            var n = Number(bytes);
            if (!isFinite(n) || n < 0) return '—';
            var units = ['B', 'KB', 'MB', 'GB', 'TB'];
            var i = 0;
            while (n >= 1024 && i < units.length - 1) { n = n / 1024; i++; }
            var d = (typeof digits === 'number') ? digits : (i === 0 ? 0 : 1);
            return n.toFixed(d) + ' ' + units[i];
        },
        /** 时间戳 / 日期字符串 / Date → 默认 "2026-09-20 15:04" */
        date: function (value, pattern) {
            var d = toDate(value);
            if (!d) return '—';
            var p = pattern || 'YYYY-MM-DD HH:mm';
            return p.replace(/YYYY/g, d.getFullYear())
                    .replace(/MM/g, pad2(d.getMonth() + 1))
                    .replace(/DD/g, pad2(d.getDate()))
                    .replace(/HH/g, pad2(d.getHours()))
                    .replace(/mm/g, pad2(d.getMinutes()))
                    .replace(/ss/g, pad2(d.getSeconds()));
        },
        /** "刚刚" / "3 分钟前" / "昨天 15:04" */
        relative: function (value, now) {
            var d = toDate(value);
            if (!d) return '—';
            var base = now ? toDate(now) : new Date();
            var diff = Math.round(((base || new Date()).getTime() - d.getTime()) / 1000);
            if (diff < 0)     return abFormat.date(d, 'YYYY-MM-DD HH:mm');
            if (diff < 60)    return '刚刚';
            if (diff < 3600)  return Math.floor(diff / 60) + ' 分钟前';
            if (diff < 86400) return Math.floor(diff / 3600) + ' 小时前';
            if (diff < 172800) return '昨天 ' + abFormat.date(d, 'HH:mm');
            if (diff < 604800) return Math.floor(diff / 86400) + ' 天前';
            return abFormat.date(d, 'YYYY-MM-DD');
        },
        /** 3725 → "1 小时 2 分" */
        duration: function (seconds) {
            var s = Math.max(0, Math.floor(Number(seconds) || 0));
            if (s < 60)    return s + ' 秒';
            if (s < 3600)  return Math.floor(s / 60) + ' 分 ' + (s % 60) + ' 秒';
            if (s < 86400) return Math.floor(s / 3600) + ' 小时 ' + Math.floor((s % 3600) / 60) + ' 分';
            return Math.floor(s / 86400) + ' 天 ' + Math.floor((s % 86400) / 3600) + ' 小时';
        },
        /** 同 ab.ui.num：1234 → "1,234"（compact 时 "1.2w"） */
        num: function (n, compact) { return abUi.num(n, compact); }
    };

    /* ---- 图表：懒加载本地 ab-charts.js（只加载一次）---- */
    function resolveTarget(t) {
        if (!t) return null;
        if (typeof t === 'string') return document.querySelector(t);
        if (t.nodeType === 1) return t;
        if (t.el && t.el.nodeType === 1) return t.el;
        return null;
    }
    var abChartsPromise = null;
    function abLoadCharts() {
        if (window.ABCharts) return Promise.resolve(window.ABCharts);
        if (abChartsPromise) return abChartsPromise;
        abChartsPromise = new Promise(function (resolve, reject) {
            var abScript = document.querySelector('script[src*="AdminBeautify.min"]');
            var base = abScript ? abScript.src.replace(/assets\/AdminBeautify\.min[^/]*\.js(\?.*)?$/, '') : '';
            var s = document.createElement('script');
            s.src = base + 'assets/lib/ab-charts.v1.0.js';
            s.onload = function () {
                if (window.ABCharts) { resolve(window.ABCharts); return; }
                abChartsPromise = null;
                reject(new Error('图表库已加载但未导出 ABCharts'));
            };
            s.onerror = function () {
                abChartsPromise = null;
                reject(new Error('图表库加载失败'));
            };
            document.head.appendChild(s);
        });
        return abChartsPromise;
    }
    var abChart = {
        load: abLoadCharts,
        /** 折线图：ab.chart.line(el, {xData:[…], yData:[…], color, colorDark}) */
        line: function (target, opts) {
            return abLoadCharts().then(function (c) {
                var node = resolveTarget(target);
                if (!node) return null;
                node.innerHTML = '';
                return c.line(node, opts || {});
            });
        },
        /** 极坐标柱状图：ab.chart.polar(el, {data:[{name,value}]}) */
        polar: function (target, opts) {
            return abLoadCharts().then(function (c) {
                var node = resolveTarget(target);
                if (!node) return null;
                node.innerHTML = '';
                return c.polar(node, opts || {});
            });
        }
    };

    /* ---- 卡片生命周期：销毁时自动清理定时器 / 监听 ---- */
    function addCardCleanup(card, fn) {
        if (!card || typeof fn !== 'function') return;
        if (!card.__abCleanups) card.__abCleanups = [];
        card.__abCleanups.push(fn);
    }
    function runCardCleanups(card) {
        if (!card || !card.__abCleanups || !card.__abCleanups.length) return 0;
        var list = card.__abCleanups;
        card.__abCleanups = [];
        for (var i = 0; i < list.length; i++) {
            try { list[i](); } catch (e) {
                if (window.console && console.warn) console.warn('[AB] 卡片清理函数出错', e);
            }
        }
        return list.length;
    }
    /** 跑掉当前文档里所有自定义卡片的清理函数（页面切换前调用）*/
    function cleanupCustomCards(root) {
        var scope = root || document;
        var nodes = scope.querySelectorAll ? scope.querySelectorAll('.ab-custom-card') : [];
        var total = 0;
        for (var i = 0; i < nodes.length; i++) total += runCardCleanups(nodes[i]);
        return total;
    }

    AdminBeautify.request = abRequest;
    AdminBeautify.data = abData;
    AdminBeautify.db = abDb;
    AdminBeautify.notify = abNotify;
    AdminBeautify.copy = abCopy;
    AdminBeautify.copyText = abCopyText;
    AdminBeautify.format = abFormat;
    AdminBeautify.charts = abChart;
    AdminBeautify.chart = abChart;
    AdminBeautify.cardCleanup = runCardCleanups;
    AdminBeautify.cleanupCustomCards = cleanupCustomCards;

    // DOM Ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            AdminBeautify.init();
        });
    } else {
        AdminBeautify.init();
    }

    // 暴露全局对象
    window.AdminBeautify = AdminBeautify;

})();

/* ====== AB 外观仓库（Theme Store）====== */
var AB_TS = (function(){
    var overlay, dialog, searchEl, listView, detailView, carouselInner,
        thumbsEl, prevBtn, nextBtn, detailContent, backRow;
    var allThemes = [];
    var shuffledThemes = [];
    var curSort    = 'random';
    var imgObserver = null;
    var curTheme  = null;
    var carouselIdx = 0;
    var carouselLen = 0;
    var tsOpen = false;
    var loaded = false;
    var lightboxOpen = false;

    function shuffle(arr){
        var a = arr.slice();
        for(var i = a.length - 1; i > 0; i--){
            var j = Math.floor(Math.random() * (i + 1));
            var t = a[i]; a[i] = a[j]; a[j] = t;
        }
        return a;
    }

    function openLightbox(src){
        var lb = document.getElementById('ab-ts-lightbox');
        if(!lb) return;
        document.getElementById('ab-ts-lb-img').src = src;
        lb.classList.add('open');
        lightboxOpen = true;
    }
    function closeLightbox(){
        var lb = document.getElementById('ab-ts-lightbox');
        if(!lb) return;
        lb.classList.remove('open');
        lightboxOpen = false;
    }

    function init(){
        if(document.getElementById('ab-ts-overlay')) return;
        var el = document.createElement('div');
        el.id = 'ab-ts-overlay';
        el.innerHTML = [
            '<div id="ab-ts-dialog">',
              '<div id="ab-ts-header">',
                '<span class="material-icons-round" style="font-size:22px;color:var(--md-primary,#6750a4)">palette</span>',
                '<h2>AB外观仓库</h2>',
                '<a id="ab-ts-contribute" href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify/issues/3" target="_blank" rel="noopener noreferrer" title="投稿主题">投稿主题</a>',
                '<button id="ab-ts-close" title="关闭" aria-label="关闭">&times;</button>',
              '</div>',
              '<div id="ab-ts-body">',
                '<div id="ab-ts-list-wrap">',
                  '<div id="ab-ts-search-bar">',
                    '<span class="material-icons-round ab-ts-search-icon">search</span>',
                    '<input type="search" id="ab-ts-search" placeholder="搜索外观名称、作者...">',
                    '<button id="ab-ts-refresh" title="刷新外观列表" aria-label="刷新外观列表"><span class="material-icons-round">refresh</span></button>',
                  '</div>',
                  '<div id="ab-ts-sort-bar">',
                    '<span class="ab-ts-sort-label">排序：</span>',
                    '<button class="ab-ts-sort-btn active" data-sort="random">随机</button>',
                    '<button class="ab-ts-sort-btn" data-sort="az">A → Z</button>',
                    '<button class="ab-ts-sort-btn" data-sort="za">Z → A</button>',
                  '</div>',
                  '<div id="ab-ts-list-view">',
                    '<div id="ab-ts-loading">',
                      '<span class="material-icons-round" style="animation:ab-ts-spin 1s linear infinite;font-size:28px">progress_activity</span>',
                      '<br>正在加载外观仓库...',
                    '</div>',
                  '</div>',
                '</div>',
                '<div id="ab-ts-detail-view">',
                  '<div class="ab-ts-back-row">',
                    '<button class="ab-ts-btn ab-ts-btn-back" id="ab-ts-back">',
                      '<span class="material-icons-round" style="font-size:18px">arrow_back</span> 返回',
                    '</button>',
                  '</div>',
                  '<div id="ab-ts-carousel">',
                    '<div id="ab-ts-carousel-inner"></div>',
                    '<button class="ab-ts-carousel-btn" id="ab-ts-carousel-prev">',
                      '<span class="material-icons-round">chevron_left</span>',
                    '</button>',
                    '<button class="ab-ts-carousel-btn" id="ab-ts-carousel-next">',
                      '<span class="material-icons-round">chevron_right</span>',
                    '</button>',
                  '</div>',
                  '<div id="ab-ts-thumbs"></div>',
                  '<div id="ab-ts-progress" style="display:none"><div id="ab-ts-progress-bar"></div></div>',
                  '<div id="ab-ts-detail-content"></div>',
                '</div>',
              '</div>',
            '</div>'
        ].join('');
        document.body.appendChild(el);

        overlay       = el;
        dialog        = document.getElementById('ab-ts-dialog');
        searchEl      = document.getElementById('ab-ts-search');
        listView      = document.getElementById('ab-ts-list-view');
        detailView    = document.getElementById('ab-ts-detail-view');
        carouselInner = document.getElementById('ab-ts-carousel-inner');
        thumbsEl      = document.getElementById('ab-ts-thumbs');
        prevBtn       = document.getElementById('ab-ts-carousel-prev');
        nextBtn       = document.getElementById('ab-ts-carousel-next');
        detailContent = document.getElementById('ab-ts-detail-content');

        // IntersectionObserver 按需加载
        if(window.IntersectionObserver){
            imgObserver = new IntersectionObserver(function(entries){
                entries.forEach(function(entry){
                    if(!entry.isIntersecting) return;
                    var img = entry.target;
                    var src = img.getAttribute('data-src');
                    if(src){ img.src = src; img.removeAttribute('data-src'); }
                    imgObserver.unobserve(img);
                });
            }, { root: listView, rootMargin: '60px' });
        }
        // 排序按钮
        [].forEach.call(document.querySelectorAll('#ab-ts-sort-bar .ab-ts-sort-btn'), function(btn){
            btn.addEventListener('click', function(){
                curSort = this.getAttribute('data-sort');
                [].forEach.call(document.querySelectorAll('#ab-ts-sort-bar .ab-ts-sort-btn'), function(b){ b.classList.remove('active'); });
                this.classList.add('active');
                renderCards(searchEl ? searchEl.value.trim() : '');
            });
        });

        document.getElementById('ab-ts-refresh').addEventListener('click', function(){
            var btn = this;
            btn.classList.add('ab-ts-refreshing');
            allThemes = [];
            shuffledThemes = [];
            loaded = false;
            listView.innerHTML = '<div id="ab-ts-loading"><span class="material-icons-round" style="animation:ab-ts-spin 1s linear infinite;font-size:28px">progress_activity</span><br>正在加载外观仓库...</div>';
            loadList();
            setTimeout(function(){ btn.classList.remove('ab-ts-refreshing'); }, 1500);
        });
        document.getElementById('ab-ts-close').addEventListener('click', close);
        document.getElementById('ab-ts-back').addEventListener('click', showList);
        searchEl.addEventListener('input', function(){ renderCards(searchEl.value.trim()); });
        prevBtn.addEventListener('click', function(){ slideTo(carouselIdx - 1); });
        nextBtn.addEventListener('click', function(){ slideTo(carouselIdx + 1); });
        overlay.addEventListener('click', function(e){ if(e.target === overlay) close(); });
        document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && tsOpen) closeLightbox(); });
        document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && tsOpen && !lightboxOpen) close(); });

        // 灯箱
        var lb = document.createElement('div');
        lb.id = 'ab-ts-lightbox';
        lb.innerHTML = '<img id="ab-ts-lb-img" src="" alt="">';
        lb.addEventListener('click', closeLightbox);
        document.body.appendChild(lb);

        (function(){
            var sx=0, sy=0;
            var cEl=document.getElementById('ab-ts-carousel');
            cEl.addEventListener('touchstart',function(e){sx=e.touches[0].clientX;sy=e.touches[0].clientY;},{passive:true});
            cEl.addEventListener('touchend',function(e){
                var dx=e.changedTouches[0].clientX-sx, dy=e.changedTouches[0].clientY-sy;
                if(Math.abs(dx)>Math.abs(dy)&&Math.abs(dx)>30){ slideTo(carouselIdx+(dx<0?1:-1)); }
            },{passive:true});
        })();
    }

    function open(){
        init();
        overlay.classList.add('ab-ts-open');
        tsOpen = true;
        document.body.style.overflow = 'hidden';
        if(!loaded) loadList();
        /* ── 入场 WAAPI Zoom ── */
        var dlg = document.getElementById('ab-ts-dialog');
        if (dlg && dlg.animate) {
            var anim = dlg.animate(
                [{ transform: 'scale(0.85)', opacity: 0 }, { transform: 'scale(1)', opacity: 1 }],
                { duration: 300, easing: 'cubic-bezier(0.05,0.7,0.1,1)', fill: 'forwards' }
            );
            anim.onfinish = function () {
                dlg.style.removeProperty('transform');
                dlg.style.removeProperty('opacity');
            };
        }
    }

    function close(){
        if(!overlay) return;
        tsOpen = false;
        document.body.style.overflow = '';
        /* ── 退场 WAAPI Zoom ── */
        var dlg = document.getElementById('ab-ts-dialog');
        if (dlg && dlg.animate) {
            var cs = window.getComputedStyle(dlg);
            var curScale = 1, curOp = parseFloat(cs.opacity) || 1;
            var t = cs.transform;
            if (t && t !== 'none') { var m = t.match(/^matrix\(([^,]+)/); if (m) curScale = parseFloat(m[1]); }
            dlg.animate(
                [{ transform: 'scale(' + curScale + ')', opacity: curOp }, { transform: 'scale(0.85)', opacity: 0 }],
                { duration: 200, easing: 'cubic-bezier(0.3,0,0.8,0.15)', fill: 'forwards' }
            ).onfinish = function () {
                overlay.classList.remove('ab-ts-open');
                dlg.style.removeProperty('transform');
                dlg.style.removeProperty('opacity');
            };
        } else {
            overlay.classList.remove('ab-ts-open');
        }
    }

    function loadList(){
        loaded = true;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', window.__AB_TS_URL__ + '?do=fetch-theme-list', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function(){
            try{
                var raw = JSON.parse(xhr.responseText);
                var data = raw;
                if(raw && raw.code === 0 && raw.data) data = raw.data;
                allThemes = (data && Array.isArray(data.themes)) ? data.themes : [];
                shuffledThemes = shuffle(allThemes);
                renderCards('');
            }catch(e){ showError('数据解析失败'); }
        };
        xhr.onerror = function(){ showError('网络错误，无法加载外观仓库'); };
        xhr.ontimeout = function(){ showError('请求超时'); };
        xhr.timeout = 20000;
        xhr.send();
    }

    function renderCards(query){
        var q = (query||'').toLowerCase();
        var base;
        if(curSort === 'random')     base = shuffledThemes.length ? shuffledThemes : allThemes;
        else if(curSort === 'az')    base = allThemes.slice().sort(function(a,b){ return (a.name||'').localeCompare(b.name||''); });
        else                         base = allThemes.slice().sort(function(a,b){ return (b.name||'').localeCompare(a.name||''); });
        var list = base.filter(function(t){
            if(!q) return true;
            return (t.name||'').toLowerCase().indexOf(q)!==-1 ||
                   (t.author||'').toLowerCase().indexOf(q)!==-1 ||
                   (t.description||'').toLowerCase().indexOf(q)!==-1;
        });
        listView.innerHTML = '';
        if(list.length === 0){
            var emEl = document.createElement('div');
            emEl.id = 'ab-ts-empty';
            emEl.textContent = q ? ('没有找到 "' + q + '" 相关的外观') : '外观仓库暂无数据';
            listView.appendChild(emEl);
            return;
        }
        list.forEach(function(theme, idx){
            var card = document.createElement('div');
            card.className = 'ab-ts-card';
            card.style.setProperty('--ab-i', idx < 20 ? idx : 20);
            var imgHtml;
            if(theme.cover){
                imgHtml = '<div class="ab-ts-card-img-wrap">'
                        + '<img class="ab-ts-card-img" data-src="'+esc(theme.cover)+'" alt="">'
                        + '</div>';
            } else {
                imgHtml = '<div class="ab-ts-card-img-ph"><span class="material-icons-round">palette</span></div>';
            }
            card.innerHTML = imgHtml
                + '<div class="ab-ts-card-info">'
                +   '<div class="ab-ts-card-name">'+esc(theme.name||'未知')+'</div>'
                +   '<div class="ab-ts-card-meta">'
                +     esc(theme.author||'') + (theme.version ? (' · v'+esc(theme.version)) : '')
                +   '</div>'
                +   (theme.description ? '<div class="ab-ts-card-desc">'+esc(theme.description)+'</div>' : '')
                + '</div>';
            // 懒加载 + shimmer
            var lazyImg = card.querySelector('[data-src]');
            if(lazyImg){
                lazyImg.addEventListener('load', function(){
                    var w = this.parentNode; if(w) w.classList.add('loaded');
                });
                lazyImg.addEventListener('error', function(){
                    var w = this.parentNode; if(w) w.classList.add('loaded');
                });
            }
            card.addEventListener('click', function(){ showDetail(theme); });
            listView.appendChild(card);
            // 必须在 card 插入 DOM 之后再 observe，否则 IO 无法判断可见性
            if(lazyImg){
                if(imgObserver){ imgObserver.observe(lazyImg); }
                else { lazyImg.src = lazyImg.getAttribute('data-src'); }
            }
        });
    }

    function showDetail(theme){
        curTheme = theme;
        renderDetail(theme);
        detailView.classList.add('ab-ts-detail-open');
        detailView.scrollTop = 0;
    }

    function showList(){
        detailView.classList.remove('ab-ts-detail-open');
        curTheme = null;
    }

    function renderDetail(t){
        var imgs = [];
        if(t.cover) imgs.push(t.cover);
        if(Array.isArray(t.images)) t.images.forEach(function(u){ if(u&&imgs.indexOf(u)===-1) imgs.push(u); });

        carouselLen = imgs.length;
        carouselIdx = 0;
        var carousel = document.getElementById('ab-ts-carousel');
        if(carouselLen === 0){
            carousel.style.display = 'none';
        } else {
            carousel.style.display = '';
            carouselInner.innerHTML = imgs.map(function(u){
                return '<img src="'+esc(u)+'" alt="'+esc(t.name||'')+'" loading="lazy" style="cursor:zoom-in">';
            }).join('');
            // 点击主图放大
            [].forEach.call(carouselInner.querySelectorAll('img'), function(img){
                img.addEventListener('click', function(){ openLightbox(this.src); });
            });
            thumbsEl.innerHTML = imgs.map(function(u,i){
                return '<button class="ab-ts-thumb'+(i===0?' active':'')+'" data-i="'+i+'"><img src="'+esc(u)+'" loading="lazy"></button>';
            }).join('');
            [].forEach.call(thumbsEl.querySelectorAll('.ab-ts-thumb'), function(btn){
                btn.addEventListener('click', function(){ slideTo(+this.getAttribute('data-i')); });
            });
            thumbsEl.style.display = carouselLen > 1 ? '' : 'none';
            updateCarousel();
            prevBtn.style.display = carouselLen > 1 ? '' : 'none';
            nextBtn.style.display = carouselLen > 1 ? '' : 'none';
        }

        var dlId = 'ab-ts-dl-btn-' + Date.now();
        var html = '<div class="ab-ts-d-body">';
        html += '<div class="ab-ts-d-title">'+esc(t.name||'未知')+'</div>';
        var chips = '';
        if(t.author)  chips += '<span class="ab-ts-chip"><span class="material-icons-round">person</span>'+esc(t.author)+'</span>';
        if(t.version) chips += '<span class="ab-ts-chip"><span class="material-icons-round">sell</span>v'+esc(t.version)+'</span>';
        if(chips) html += '<div class="ab-ts-d-chips">'+chips+'</div>';
        html += '<div class="ab-ts-d-actions">';
        if(t.download) html += '<button class="ab-ts-btn ab-ts-btn-primary" id="'+dlId+'"><span class="material-icons-round" style="font-size:18px">download</span> 下载安装</button>';
        if(t.buyUrl && t.price) html += '<a class="ab-ts-btn ab-ts-btn-primary" href="'+esc(t.buyUrl)+'" target="_blank" rel="noopener"><span class="material-icons-round" style="font-size:18px">open_in_new</span> ￥ '+t.price+' 购买</a>';
        if(t.github)   html += '<a class="ab-ts-btn ab-ts-btn-tonal" href="'+esc(t.github)+'" target="_blank" rel="noopener"><span class="material-icons-round" style="font-size:18px">code</span> GitHub</a>';
        if(t.homepage) html += '<a class="ab-ts-btn ab-ts-btn-tonal" href="'+esc(t.homepage)+'" target="_blank" rel="noopener"><span class="material-icons-round" style="font-size:18px">open_in_new</span> 外观主页</a>';
        html += '</div></div>';
        if(t.description){
            html += '<div class="ab-ts-d-section">'
                + '<div class="ab-ts-d-section-head"><span class="ab-ts-d-section-label">简介</span></div>'
                + '<div class="ab-ts-d-section-body"><p class="ab-ts-d-desc">'+esc(t.description)+'</p></div>'
                + '</div>';
        }
        if(t.data && typeof t.data === 'object' && Object.keys(t.data).length > 0){
            html += '<div class="ab-ts-d-section">'
                + '<div class="ab-ts-d-section-head"><span class="ab-ts-d-section-label">详细信息</span></div>'
                + '<div class="ab-ts-d-section-body"><div class="ab-ts-fields">';
            Object.keys(t.data).forEach(function(k){
                var v = t.data[k], vHtml;
                if(v && typeof v === 'object' && v.value){
                    vHtml = v.link ? '<a href="'+esc(v.link)+'" target="_blank" rel="noopener">'+esc(v.value)+'</a>' : esc(v.value);
                } else { vHtml = esc(String(v)); }
                html += '<span class="ab-ts-field-key">'+esc(k)+'</span><span class="ab-ts-field-val">'+vHtml+'</span>';
            });
            html += '</div></div></div>';
        }
        detailContent.innerHTML = html;
        if(t.download){
            var dlBtn = document.getElementById(dlId);
            if(dlBtn) dlBtn.addEventListener('click', function(){ doDownload(t, dlBtn); });
        }
    }

    function slideTo(idx){
        carouselIdx = ((idx % carouselLen) + carouselLen) % carouselLen;
        updateCarousel();
    }
    function updateCarousel(){
        var w = document.getElementById('ab-ts-carousel').offsetWidth;
        carouselInner.style.transform = 'translateX(-' + (carouselIdx * w) + 'px)';
        [].forEach.call(thumbsEl.querySelectorAll('.ab-ts-thumb'), function(d,i){
            d.classList.toggle('active', i===carouselIdx);
            if(i===carouselIdx) d.scrollIntoView({inline:'nearest',block:'nearest'});
        });
    }

    function doDownload(t, btn){
        var prog = document.getElementById('ab-ts-progress');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-icons-round" style="font-size:18px">downloading</span> 安装中...';
        if(prog) prog.style.display = '';
        var form = 'url='+encodeURIComponent(t.download)+'&name='+encodeURIComponent(t.id||'')+'&_='+encodeURIComponent(window.__AB_TS_TOKEN__||'');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.__AB_TS_URL__ + '?do=download-theme', true);
        xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
        xhr.onload = function(){
            if(prog) prog.style.display = 'none';
            try{
                var res = JSON.parse(xhr.responseText);
                if(res.code === 0){
                    btn.innerHTML = '<span class="material-icons-round" style="font-size:18px">check_circle</span> 安装成功';
                    btn.style.background = 'var(--md-tertiary-container,#c4eed0)';
                    btn.style.color = 'var(--md-on-tertiary-container,#002116)';
                    // 安装成功后关闭弹窗并刷新外观列表
                    setTimeout(function(){
                        close();
                        if(window.AdminBeautify && typeof AdminBeautify._navigateTo === 'function'){
                            AdminBeautify._navigateTo(location.href, true);
                        } else {
                            location.reload();
                        }
                    }, 1000);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-icons-round" style="font-size:18px">download</span> 下载安装';
                    alert(res.message||'安装失败');
                }
            }catch(e){
                btn.disabled = false;
                btn.innerHTML = '<span class="material-icons-round" style="font-size:18px">download</span> 下载安装';
                alert('响应解析失败');
            }
        };
        xhr.onerror = function(){
            if(prog) prog.style.display = 'none';
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-round" style="font-size:18px">download</span> 下载安装';
            alert('网络错误');
        };
        xhr.timeout = 120000;
        xhr.ontimeout = xhr.onerror;
        xhr.send(form);
    }

    function showError(msg){
        listView.innerHTML = '<div id="ab-ts-empty"><span class="material-icons-round" style="font-size:28px;color:var(--md-error,#b3261e)">error_outline</span><br>'+esc(msg)+'</div>';
    }

    function esc(s){
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    return { open: open, close: close };
})();

/* ── theme store 按钮注入 themes.php ── */
(function(){
    var BTN_ID = 'ab-theme-store-btn';
    function injectBtn(){
        if(document.getElementById(BTN_ID)) return;
        var url = window.location.href;
        if(url.indexOf('themes.php') === -1) return;
        var titleArea = document.querySelector('.typecho-page-title');
        if(!titleArea) return;
        titleArea.style.display    = 'flex';
        titleArea.style.alignItems = 'center';
        titleArea.style.flexWrap   = 'wrap';
        titleArea.style.gap        = '8px';
        var h = titleArea.querySelector('h2,h3');
        if(h) h.style.flex = '1 1 auto';
        var btn = document.createElement('button');
        btn.id        = BTN_ID;
        btn.type      = 'button';
        btn.innerHTML = '<span class="material-icons-round" style="font-size:18px;margin-right:6px;vertical-align:-3px">palette</span>AB外观仓库';
        btn.style.cssText =
            'display:inline-flex;align-items:center;margin-left:auto;flex-shrink:0;'
            +'padding:8px 16px 8px 12px;border-radius:20px;border:none;cursor:pointer;'
            +'font-size:14px;font-weight:600;font-family:inherit;'
            +'background:var(--md-secondary-container,#e8def8);'
            +'color:var(--md-on-secondary-container,#1d192b);'
            +'box-shadow:0 1px 3px rgba(0,0,0,.12);transition:box-shadow .2s;line-height:1;';
        btn.addEventListener('mouseover',function(){this.style.boxShadow='0 3px 10px rgba(0,0,0,.18)';});
        btn.addEventListener('mouseout', function(){this.style.boxShadow='0 1px 3px rgba(0,0,0,.12)';});
        btn.addEventListener('click', function(){ AB_TS.open(); });
        titleArea.appendChild(btn);
    }
    function removeBtn(){
        var b = document.getElementById(BTN_ID);
        if(b) b.parentNode.removeChild(b);
    }
    function check(url){
        if((url||window.location.href).indexOf('themes.php') !== -1){ injectBtn(); }
        else{ removeBtn(); }
    }
    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', function(){ check(); });
    } else { check(); }
    document.addEventListener('ab:pageload', function(e){
        check((e&&e.detail&&e.detail.url) ? e.detail.url : window.location.href);
    });
})();
