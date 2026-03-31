(function(){
    var cfg = window.__AB_CONFIG__ || {};
    if(cfg.editorVditor !== '1') return;

    // ── Unicode 安全 btoa/atob 补丁（Vditor 内部对中文/Emoji 内容使用 base64 会崩溃） ──
    // btoa 只能处理 Latin-1，中文等多字节字符必须先 encodeURIComponent
    (function() {
        var _nativeBtoa = window.btoa;
        var _nativeAtob = window.atob;
        if (_nativeBtoa) {
            window.btoa = function(str) {
                try {
                    return _nativeBtoa(str);
                } catch(e) {
                    // Unicode → percent-encode → Latin-1 safe → base64
                    return _nativeBtoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function(_, p1) {
                        return String.fromCharCode(parseInt(p1, 16));
                    }));
                }
            };
        }
        if (_nativeAtob) {
            window.atob = function(str) {
                // 去除非法 base64 字符（空白、换行等），避免 InvalidCharacterError
                var cleaned = str.replace(/[^A-Za-z0-9+/=]/g, '');
                try {
                    return _nativeAtob(cleaned);
                } catch(e) {
                    return decodeURIComponent(_nativeAtob(cleaned).split('').map(function(c) {
                        return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                    }).join(''));
                }
            };
        }
    })();

    var defaultMode = cfg.editorVditorMode || 'ir';
    // 本地 index.min.js 路径：从 pluginUrl 拼接
    var _abPluginUrl = (cfg.pluginUrl || '').replace(/\/+$/, '');
    var VDITOR_LOCAL_JS = _abPluginUrl + '/usr/plugins/AdminBeautify/assets/lib/vditor/index.min.js';
    // cdn 选项依然指向远端，供 Vditor 内部子资源（highlight、math 等）使用
    var VDITOR_CDN = 'https://cdn.jsdelivr.net/npm/vditor';

    // ── 拦截 window.Vditor 的所有赋值，捕获任意插件创建的实例 ──────────────
    var _abVditorInstances = {};
    var _abCurrentVditorClass = window.Vditor || null;

    function _abWrapVditorClass(VClass) {
        if (!VClass || VClass.__abWrapped) return VClass;
        function WrappedVditor(id, opts) {
            var inst = new VClass(id, opts);
            if (typeof id === 'string') _abVditorInstances[id] = inst;
            return inst;
        }
        WrappedVditor.prototype = VClass.prototype;
        try {
            var keys = Object.getOwnPropertyNames(VClass);
            for (var i = 0; i < keys.length; i++) {
                try { WrappedVditor[keys[i]] = VClass[keys[i]]; } catch(e) {}
            }
        } catch(e) {}
        WrappedVditor.__abWrapped = true;
        WrappedVditor.__abOriginal = VClass;
        return WrappedVditor;
    }

    try {
        Object.defineProperty(window, 'Vditor', {
            configurable: true,
            enumerable: true,
            get: function() { return _abCurrentVditorClass; },
            set: function(v) { _abCurrentVditorClass = _abWrapVditorClass(v); }
        });
        if (_abCurrentVditorClass) {
            _abCurrentVditorClass = _abWrapVditorClass(_abCurrentVditorClass);
        }
    } catch(e) {
        if (window.Vditor) window.Vditor = _abWrapVditorClass(window.Vditor);
    }

    // ── 等待指定 id 的 Vditor 实例出现 ─────────────────────────────────────
    function _abWaitForInstance(id, cb, maxMs) {
        var elapsed = 0;
        var t = setInterval(function() {
            elapsed += 100;
            if (_abVditorInstances[id]) { clearInterval(t); cb(_abVditorInstances[id]); }
            else if (elapsed >= (maxMs || 15000)) { clearInterval(t); }
        }, 100);
    }

    // ── 切换编辑模式（兼容 Vditor 3.8.x 无 setMode / 3.9+ 有 setMode）──────
    function _abSetVditorMode(vd, containerId, mode) {
        if (vd && typeof vd.setMode === 'function') {
            vd.setMode(mode);
            return;
        }
        // 3.8.x 降级：直接 click 工具栏内部的 button[data-mode]（始终在 DOM 中）
        var container = document.getElementById(containerId);
        if (container) {
            var btn = container.querySelector('button[data-mode="' + mode + '"]');
            if (btn) { btn.click(); return; }
        }
        console.warn('[AB] Vditor: setMode fallback 失败', mode);
    }

    // ── 图标映射表（Material Icons Round 连字名称）────────────────────────
    var _abIconMap = {
        'emoji':'mood','headings':'title','bold':'format_bold',
        'italic':'format_italic','strike':'strikethrough_s',
        'line':'horizontal_rule','quote':'format_quote',
        'list':'format_list_bulleted','ordered-list':'format_list_numbered',
        'check':'check_box','indent':'format_indent_increase',
        'outdent':'format_indent_decrease','code':'integration_instructions',
        'inline-code':'code','insert-before':'vertical_align_top',
        'insert-after':'vertical_align_bottom','upload':'upload',
        'link':'link','table':'table_chart','undo':'undo','redo':'redo',
        'fullscreen':'fullscreen','edit-mode':'tune','preview':'preview',
        'outline':'toc','export':'download','both':'vertical_split',
        'code-theme':'palette','content-theme':'style','more':'more_horiz'
    };

    // ── 用 JS 注入 <span class="ab-icon"> 替换 SVG（连字必须是文本节点）──
    function _abApplyVditorIcons(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;
        var btns = container.querySelectorAll('.vditor-toolbar button[data-type]');
        for (var i = 0; i < btns.length; i++) {
            var btn = btns[i];
            var type = btn.getAttribute('data-type');
            var iconName = _abIconMap[type];
            if (!iconName) continue;
            // 隐藏已有 SVG
            var svgs = btn.querySelectorAll('svg');
            for (var j = 0; j < svgs.length; j++) svgs[j].style.display = 'none';
            // 避免重复注入
            if (btn.querySelector('.ab-icon')) continue;
            var span = document.createElement('span');
            span.className = 'ab-icon';
            span.textContent = iconName;  // 文本节点才能触发 Material Icons 连字
            btn.appendChild(span);
        }
    }

    // ── 为浮动工具栏（vditor-panel）按钮注入 Material Icons Round 图标 ───────
    function _abApplyPanelIcons(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;
        // data-type → 图标（唯一 type）
        var panelIconMap = {
            'left':         'format_align_left',
            'center':       'format_align_center',
            'right':        'format_align_right',
            'deleteRow':    'delete',
            'deleteColumn': 'delete'
        };
        // data-type → [第 n 次出现的图标]（同 type 多个按钮）
        var panelIconMapNth = {
            'insertRow':    ['vertical_align_bottom', 'vertical_align_top'],
            'insertColumn': ['border_right', 'border_left']
        };
        // 无 data-type 的按钮按 aria-label 关键字匹配（如评论按钮）
        var ariaLabelIconMap = {
            'comment': 'comment'
        };
        function injectPanelIcons() {
            var panels = container.querySelectorAll('.vditor-panel');
            Array.prototype.forEach.call(panels, function(panel) {
                var typeCount = {};
                // 包含无 data-type 的按钮（评论等）
                var btns = panel.querySelectorAll('button.vditor-icon');
                Array.prototype.forEach.call(btns, function(btn) {
                    var type = btn.getAttribute('data-type');
                    var iconName;
                    if (type) {
                        if (!typeCount[type]) typeCount[type] = 0;
                        var nth = typeCount[type]++;
                        if (panelIconMap[type]) {
                            iconName = panelIconMap[type];
                        } else if (panelIconMapNth[type]) {
                            iconName = panelIconMapNth[type][nth] || panelIconMapNth[type][0];
                        }
                    } else {
                        // 无 data-type：aria-label 关键字匹配
                        var ariaLabel = (btn.getAttribute('aria-label') || '').toLowerCase();
                        var ariaKeys = Object.keys(ariaLabelIconMap);
                        for (var ki = 0; ki < ariaKeys.length; ki++) {
                            if (ariaLabel.indexOf(ariaKeys[ki]) !== -1) {
                                iconName = ariaLabelIconMap[ariaKeys[ki]];
                                break;
                            }
                        }
                    }
                    if (!iconName) return;
                    // 隐藏原生 SVG（inline style 高优先级）
                    Array.prototype.forEach.call(btn.querySelectorAll('svg'), function(s) { s.style.display = 'none'; });
                    // 避免重复注入
                    if (btn.querySelector('.ab-icon')) return;
                    var span = document.createElement('span');
                    span.className = 'ab-icon';
                    span.textContent = iconName;
                    btn.appendChild(span);
                });
            });
        }
        injectPanelIcons();
        // 观察各面板：子树变化（按钮动态填入）+ class 属性变化（--none 移除→面板可见，重新注入）
        var panelObs = new MutationObserver(function() { injectPanelIcons(); });
        Array.prototype.forEach.call(container.querySelectorAll('.vditor-panel'), function(panel) {
            panelObs.observe(panel, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
        });
    }

    // ── 监听 Vditor 内部模式切换（同步 ab-mode-bar 激活态）──────────────────
    function _abObserveModeChange(containerId, onModeChange) {
        var container = document.getElementById(containerId);
        if (!container) return;
        var lastMode = '';
        var obs = new MutationObserver(function() {
            var activeBtn = container.querySelector(
                '.vditor-toolbar__item--current button[data-mode]'
            );
            if (activeBtn) {
                var mode = activeBtn.getAttribute('data-mode');
                if (mode && mode !== lastMode) {
                    lastMode = mode;
                    onModeChange(mode);
                }
            }
        });
        obs.observe(container, { subtree: true, attributes: true, attributeFilter: ['class'] });
    }

    // ── 监听工具栏 DOM 变化以重注入图标（全屏切换等导致 innerHTML 被替换）──
    function _abObserveToolbarForIcons(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;
        var toolbar = container.querySelector('.vditor-toolbar');
        if (!toolbar) return;
        var pending = false;
        var obs = new MutationObserver(function() {
            if (pending) return;
            pending = true;
            setTimeout(function() {
                pending = false;
                _abApplyVditorIcons(containerId);
            }, 50);
        });
        obs.observe(toolbar, { childList: true, subtree: true });
    }

    // ── Vditor 保存草稿（与 PageDown 模式保存逻辑完全一致）────────────────────
    function _abVditorSaveDraft($textarea) {
        // 先将 Vditor 内容同步到 textarea
        if (window.__abVditor) {
            try { $textarea.val(window.__abVditor.getValue()); } catch(e) {}
        }
        var form = document.querySelector('form[name=write_post], form[name=write_page]');
        if (!form) return;
        var btn = document.getElementById('ab-vditor-save-btn');
        var icon = btn ? btn.querySelector('.ab-icon') : null;
        if (icon) icon.textContent = 'hourglass_empty';
        if (btn) { btn.style.opacity = '0.6'; btn.style.pointerEvents = 'none'; }
        var fd = new FormData(form);
        fd.append('do', 'save');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            if (icon) icon.textContent = 'save';
            if (btn) { btn.style.opacity = ''; btn.style.pointerEvents = ''; }
            var msg = '已保存';
            try {
                var res = JSON.parse(xhr.responseText);
                if (res && res.time) msg = '已保存 (' + res.time + ')';
                var autoSaveEl = document.getElementById('auto-save-message');
                if (autoSaveEl) autoSaveEl.textContent = msg;
            } catch(e) {}
            var toast = document.createElement('div');
            toast.className = 'ab-save-toast';
            toast.textContent = msg;
            document.body.appendChild(toast);
            requestAnimationFrame(function() { toast.classList.add('ab-save-toast-show'); });
            setTimeout(function() {
                toast.classList.remove('ab-save-toast-show');
                setTimeout(function() { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 300);
            }, 2200);
        };
        xhr.onerror = function() {
            if (icon) icon.textContent = 'save';
            if (btn) { btn.style.opacity = ''; btn.style.pointerEvents = ''; }
        };
        xhr.send(fd);
    }

    // ── 动态加载 CDN Vditor（仅在没有其他插件提供时才加载）───────────────────
    function _abLoadVditorCDN(cb) {
        // 如果已有可用的 Vditor（无论哪个版本），直接回调
        if (window.Vditor) { cb(); return; }
        if (window._abVditorCDNLoading) {
            var chk = setInterval(function() {
                if (window.Vditor) { clearInterval(chk); cb(); }
            }, 50);
            return;
        }
        window._abVditorCDNLoading = true;
        var s = document.createElement('script');
        s.src = VDITOR_LOCAL_JS;
        s.onload = function() { window._abVditorCDNLoading = false; cb(); };
        s.onerror = function() { console.error('[AB] Vditor CDN 加载失败'); };
        document.head.appendChild(s);
    }

    // ── 构建模式切换栏（MD3 Segmented Button 样式，通过 CSS 类控制激活态）──
    function _abBuildModeBar(activeMode, onModeChange) {
        var modeLabels = {wysiwyg:'所见即所得', ir:'实时预览', sv:'分屏编辑'};
        var modes = ['wysiwyg', 'ir', 'sv'];

        var $bar = $('<div id="ab-vditor-mode-bar"></div>');
        // 胶囊按钮组
        var $group = $('<div class="ab-mode-btn-group"></div>');

        modes.forEach(function(m) {
            var $btn = $('<button type="button"></button>')
                .text(modeLabels[m])
                .attr('data-vmode', m)
                .addClass('ab-mode-btn' + (m === activeMode ? ' ab-mode-active' : ''))
                .on('click', function() {
                    $group.find('.ab-mode-btn').removeClass('ab-mode-active');
                    $(this).addClass('ab-mode-active');
                    onModeChange(m);
                    try { localStorage.setItem('ab-vditor-mode', m); } catch(e) {}
                });
            $group.append($btn);
        });

        $bar.append($group);
        return $bar;
    }

    // ── 全屏切换：将 wrap 移至 body 层，彻底避免祖先 transform 导致 fixed 定位失效 ──
    // _abVditorFsParent / _abVditorFsNext 用于退出时还原位置
    function _abToggleFullscreen() {
        var wrap = document.getElementById('ab-vditor-wrap');
        if (!wrap) return;
        var fsBtn = document.getElementById('ab-vditor-fullscreen-btn');
        var icon = fsBtn ? fsBtn.querySelector('.ab-icon') : null;
        var isFs = wrap.classList.contains('ab-fullscreen');

        if (!isFs) {
            // ── 进入全屏 ──
            // 记录原始位置，退出时还原
            window._abVditorFsParent = wrap.parentNode;
            window._abVditorFsNext   = wrap.nextSibling;

            // 移到 body 最末，避免任何祖先 transform/will-change/filter 影响 fixed 定位
            document.body.appendChild(wrap);
            wrap.classList.add('ab-fullscreen');
            document.body.classList.add('ab-vditor-fullscreen');  // 用于 CSS 隐藏侧边栏
            document.body.style.overflow = 'hidden';
            if (icon) icon.textContent = 'fullscreen_exit';

            // ESC 退出
            function escHandler(e) {
                if (e.key === 'Escape') {
                    document.removeEventListener('keydown', escHandler);
                    _abToggleFullscreen();
                }
            }
            document.addEventListener('keydown', escHandler);

        } else {
            // ── 退出全屏 ──
            wrap.classList.remove('ab-fullscreen');
            document.body.classList.remove('ab-vditor-fullscreen');
            document.body.style.overflow = '';
            if (icon) icon.textContent = 'fullscreen';

            // 将 wrap 还原到原始 DOM 位置
            var fsParent = window._abVditorFsParent;
            var fsNext   = window._abVditorFsNext;
            window._abVditorFsParent = null;
            window._abVditorFsNext   = null;
            if (fsParent) {
                if (fsNext && fsNext.parentNode === fsParent) {
                    fsParent.insertBefore(wrap, fsNext);
                } else {
                    fsParent.appendChild(wrap);
                }
            }
        }
    }

    // ── 为已有 Vditor（如 Mirages 的 #vditor）添加模式栏 ───────────────────
    function _abAddModeBarToExisting(vd, $vditorEl) {
        if ($('#ab-vditor-mode-bar').length) return;
        var savedMode;
        try { savedMode = localStorage.getItem('ab-vditor-mode') || defaultMode; } catch(e) { savedMode = defaultMode; }
        var $bar = _abBuildModeBar(savedMode, function(mode) {
            _abSetVditorMode(window.__abVditor, 'vditor', mode);
        });
        $vditorEl.before($bar);
        window.__abVditor = vd;
        // 注入 Material Icons 图标
        setTimeout(function() { _abApplyVditorIcons('vditor'); }, 300);
        // 监听工具栏 DOM 变化（全屏切换等会覆盖按钮 innerHTML）
        setTimeout(function() {
            _abObserveToolbarForIcons('vditor');
            // 监听 Vditor 内部模式切换，同步 ab-mode-bar 激活态
            _abObserveModeChange('vditor', function(mode) {
                $('#ab-vditor-mode-bar .ab-mode-btn').removeClass('ab-mode-active');
                $('#ab-vditor-mode-bar .ab-mode-btn[data-vmode="' + mode + '"]').addClass('ab-mode-active');
                try { localStorage.setItem('ab-vditor-mode', mode); } catch(e) {}
            });
        }, 500);
    }

    // ── 初始化我们自己的 Vditor（无 Mirages 时）───────────────────────────────
    function _abInitOwnVditor($textarea) {
        $('#wmd-button-bar').remove();
        $('#wmd-preview').remove();
        var $editArea = $('#wmd-editarea');
        $editArea.hide();

        var initContent = $textarea.val() || '';
        var savedMode;
        try { savedMode = localStorage.getItem('ab-vditor-mode') || defaultMode; } catch(e) { savedMode = defaultMode; }
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';

        var $vditorWrap = $('<div id="ab-vditor"></div>');
        var $modeBar = _abBuildModeBar(savedMode, function(mode) {
            _abSetVditorMode(window.__abVditor, 'ab-vditor', mode);
        });

        // 在模式栏右侧加 MD3 全屏按钮
        var $fsBtn = $('<button type="button" id="ab-vditor-fullscreen-btn" title="全屏编辑"><span class="ab-icon">fullscreen</span></button>');
        $fsBtn.on('click', function() { _abToggleFullscreen(); });
        $modeBar.append($fsBtn);

        // 用 wrap 包裹模式栏 + 编辑器（全屏动画的目标容器）
        var $wrap = $('<div id="ab-vditor-wrap"></div>');
        $wrap.append($modeBar).append($vditorWrap);
        $editArea.before($wrap);

        // 构建工具栏数组：含 edit-mode（CSS 隐藏，供 _abSetVditorMode 回退点击）及插件工具
        var _toolbarArr = [
            'emoji','headings','bold','italic','strike','|',
            'line','quote','list','ordered-list','check','indent','outdent','|',
            'code','inline-code','insert-before','insert-after','|',
            'upload','link','table','|',
            'undo','redo',
            'edit-mode'
        ];

        var vditorReady = false;
        var vd = new Vditor('ab-vditor', {
            mode: savedMode,
            height: Math.max(480, $(window).height() - 260),
            theme: isDark ? 'dark' : 'classic',
            lang: 'zh_CN',
            cdn: VDITOR_CDN,
            preview: { theme: { current: isDark ? 'dark' : 'light' } },
            toolbar: _toolbarArr,
            input: function(val) { $textarea.val(val); },
            after: function() {
                // 在 after 回调中设置初始内容，避免构造时 Vditor 内部 btoa 因中文/Emoji 崩溃
                if (initContent) {
                    try { vd.setValue(initContent); } catch(e) { /* 兼容保护 */ }
                }
                $textarea.val(vd.getValue());
                vditorReady = true;
                _abApplyVditorIcons('ab-vditor');
                // 监听工具栏 DOM 变化（全屏切换会覆盖按钮 innerHTML，需重注入图标）
                _abObserveToolbarForIcons('ab-vditor');
                // 为浮动面板（vditor-panel）注入 Material Icons 图标
                _abApplyPanelIcons('ab-vditor');
                // 在 redo 按钮右侧插入保存草稿按钮（防重复插入）
                if (!document.getElementById('ab-vditor-save-btn')) {
                    var _tbEl = document.querySelector('#ab-vditor .vditor-toolbar');
                    if (_tbEl) {
                        var _redoItem = _tbEl.querySelector('li:has(button[data-type="redo"])');
                        var _saveLi = document.createElement('li');
                        _saveLi.className = 'vditor-toolbar__item';
                        var _saveBtn = document.createElement('button');
                        _saveBtn.type = 'button';
                        _saveBtn.id = 'ab-vditor-save-btn';
                        _saveBtn.className = 'vditor-tooltipped vditor-tooltipped__n';
                        _saveBtn.setAttribute('aria-label', '保存草稿');
                        var _saveIcon = document.createElement('span');
                        _saveIcon.className = 'ab-icon';
                        _saveIcon.textContent = 'save';
                        _saveBtn.appendChild(_saveIcon);
                        _saveLi.appendChild(_saveBtn);
                        _saveBtn.addEventListener('click', function() { _abVditorSaveDraft($textarea); });
                        if (_redoItem && _redoItem.nextSibling) {
                            _tbEl.insertBefore(_saveLi, _redoItem.nextSibling);
                        } else {
                            _tbEl.appendChild(_saveLi);
                        }
                    }
                }
                // 监听 Vditor 内部模式切换，同步 ab-mode-bar 激活态
                _abObserveModeChange('ab-vditor', function(mode) {
                    $('#ab-vditor-mode-bar .ab-mode-btn').removeClass('ab-mode-active');
                    $('#ab-vditor-mode-bar .ab-mode-btn[data-vmode="' + mode + '"]').addClass('ab-mode-active');
                    try { localStorage.setItem('ab-vditor-mode', mode); } catch(e) {}
                });
            },
            cache: { enable: false },
            // 兼容新版 Vditor（≥3.9）：传入空函数避免 WYSIWYG 气泡工具栏报错
            customWysiwygToolbar: function() { return []; }
        });
        window.__abVditor = vd;

        // 跟随暗色主题切换（after 完成后才响应）
        var themeObserver = new MutationObserver(function(mutations) {
            if (!vditorReady) return;
            mutations.forEach(function(m) {
                if (m.attributeName === 'data-theme') {
                    var dark = document.documentElement.getAttribute('data-theme') === 'dark';
                    window.__abVditor.setTheme(dark ? 'dark' : 'classic', dark ? 'dark' : 'light');
                }
            });
        });
        themeObserver.observe(document.documentElement, { attributes: true });

        // 表单提交同步
        $textarea.closest('form').on('submit', function() {
            if (window.__abVditor) $textarea.val(window.__abVditor.getValue());
        });

        // 覆写 Typecho.savePost
        var _origSave = window.Typecho && window.Typecho.savePost;
        if (window.Typecho) {
            window.Typecho.savePost = function() {
                if (window.__abVditor) $textarea.val(window.__abVditor.getValue());
                if (typeof _origSave === 'function') return _origSave.apply(this, arguments);
            };
        }
    }

    // ── 主入口：DOMContentLoaded 后执行 ────────────────────────────────────
    $(document).ready(function() {
        var $textarea = $('#text');
        if (!$textarea.length) return;

        // 检测 Mirages 等已集成 Vditor 的插件（通过 LocalConst.VDITOR_BASE_URL 判断）
        var hasMiragesVditor = !!(window.LocalConst && window.LocalConst.VDITOR_BASE_URL);

        if (hasMiragesVditor) {
            // Mirages 已负责 Vditor，等待其实例创建完成后接管模式栏
            _abWaitForInstance('vditor', function(vd) {
                _abAddModeBarToExisting(vd, $('#vditor'));
            }, 15000);
            return;
        }

        // 无其他 Vditor 插件：动态加载 CDN 后自行初始化
        _abLoadVditorCDN(function() {
            _abInitOwnVditor($textarea);
        });
    });
})();
