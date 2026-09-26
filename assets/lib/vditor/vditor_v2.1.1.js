/**
 * AB Admin — Vditor 适配器
 *
 * 适配器版本：v2.1.1
 * 适配的 Vditor：v4.0.0（向下兼容 v3.x）
 *
 * v2.1.1 相对 v2.1.0 的变更（移动端深度打磨 + 弹窗重设计）：
 *   1. 移动端第一行工具栏**停靠到编辑区底部**（_abStructureToolbar），
 *      并用 _abSyncDockFit() 把 wrap 定高、编辑器收缩，避免滚动行被顶出视口；
 *      软键盘弹出时整块编辑区铺满键盘上方，滚动行贴键盘上沿。
 *   2. 全屏时滚动行回到顶部两行，进度条改画在固定行上沿（不再停靠）。
 *   3. 「关于 AB Vditor」弹窗按 Material 3 重做，并列出**全部打包组件版本**
 *      （_abVditorComponents / _abRenderAboutComponents）。
 *   4. ⚠️ 滚动行被搬出 #ab-vditor 带来的一连串「祖先依赖失效」问题，已逐项修好，
 *      改这块前请先读：_abVditorScope()（找元素/观察变化的作用域）、
 *      CSS 里的 :is(.vditor-toolbar,.ab-toolbar-scroller) 写法、
 *      _abSyncDockedTheme()（主题类与主题变量）、_abWatchToolbarPanelClip()（面板裁剪）。
 *
 * v2.1.0 相对 v2.0.1 的变更（对齐 Vditor v4.0.0）：
 *   1. Vditor 4.0.0 把 SV 模式从 contenteditable <pre> 换成了 <textarea>
 *      （`.vditor-sv__marker*` 语法高亮 span 一并消失），textarea 的内部选区
 *      不会出现在 window.getSelection() 里，旧写法会直接返回 null。
 *      现在按元素类型分流：textarea 读 selectionStart/selectionEnd，其余模式仍走 Range。
 *   2. 修正模式激活态同步选择器为 `button[data-mode].vditor-menu--current`。
 *      旧写法 `.vditor-toolbar__item--current button[data-mode]` 在 3.11.2 和 4.0.0
 *      里都不存在（Vditor 用的是 vditor-menu--current），属于自始就失效的死选择器，
 *      后果是 AB 自己的模式栏高亮从不跟随编辑器内部切换。
 *   3. 其余用到的 API（getValue/setValue/insertMD/insertValue/getCurrentMode/setTheme、
 *      工具条与面板 DOM、上传配置）在 4.0.0 未变，无需改动。
 *
 * 同一版本内还完成两件事（配套改动都在 vditor_v2.1.1.css 的 A / C / D 段）：
 *   4. 颜色跟随插件主题色：加载动画的转圈环与骨架屏、两个弹窗（关于 / 插入文本）的
 *      面色与文字色，此前写死成 Material 默认紫，换成绿/蓝/橙主题后依旧是紫的；
 *      现统一改用 AdminBeautify 的 --md-* 语义色变量。
 *   5. 移动端深度适配（断点统一取 575px，与 AdminBeautify 自己的移动端断点一致）：
 *      - 模式栏 ≤575px 换成纯图标（wysiwyg / visibility / vertical_split），
 *        桌面端仍显示文字，两种形态都由 CSS 在同一个按钮里切换
 *      - 工具栏 ≤575px 改**两行**：第一行（.ab-toolbar-scroller）横向滚动，
 *        第二行（.ab-toolbar-pinned）固定显示 撤销/恢复/保存/关于。
 *        布局由 _abStructureToolbar() 建容器，桌面端用 display:contents 等于不存在
 *      - 子面板弹出时用 _abWatchToolbarPanelClip() 临时解除滚动容器的裁剪
 *      - 编辑器高度按视口比例；触摸目标、面板限宽、iOS 输入聚焦缩放、
 *        全屏安全区等一并在 CSS 里处理
 *   6. **修正 iPhone 上模式切换失效**：Vditor 用具 getEventName() 把工具栏按钮绑在
 *      touchstart（UA 含 "iPhone"）而不是 click 上，原来只调 btn.click() 在真机 iOS 上
 *      根本不会切换（表现为 AB 模式栏高亮变了、编辑器模式没变）。
 *      现新增 _abFireVditorToolbarEvent()：iPhone 补发 touchstart，同时仍发 click。
 *   7. 气泡工具栏避让光标：Vditor 把气泡顶边放在「当前块 top - 21」，气泡自身有高度，
 *      于是会向下盖住块首行（光标所在）。新增 _abKeepWysiwygPopoverOffCaret()：
 *      每次定位后校验一次，与光标矩形相交就整体移位（优先上方，空间不够则下方）。
 *   8. E 段（CSS）修了两个自始失效的选择器：工具栏的 vditor-menu--current 与面板的
 *      vditor-icon--current（原写法 .vditor-toolbar__item--current /
 *      .vditor-panel__action--current 在 3.11.2 和 4.0.0 里都不存在），
 *      并补了 .ab-icon 字体基线（没它的话，新加的 .ab-icon 拿不到图标字体，
 *      连字名会被当普通文字渲染）。
 *
 * 已下线：曾适配过 Vditor 4.0.0 的移动端 WYSIWYG「+」快捷菜单
 * （options.customWysiwygMobileToolbar）。现已整体移除、不再传该回调，
 * 所以 Vditor 不会生成 .vditor-wysiwyg__mobile-toolbar，气泡里只剩它自己的
 * 上移 / 下移 / 删除按钮。
 */
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
    // 所有 Vditor 资源统一走插件本地路径
    var _abVditorAssetBase = (cfg.vditorAssetBaseUrl || '').replace(/\/+$/, '');
    var VDITOR_LOCAL_JS = cfg.vditorLocalJsUrl
        || (_abVditorAssetBase ? (_abVditorAssetBase + '/index.min.js') : '/usr/plugins/AdminBeautify/assets/lib/vditor/index.min.js');
    var VDITOR_CDN = (cfg.vditorCdnBaseUrl || _abVditorAssetBase || '/usr/plugins/AdminBeautify/assets/lib/vditor').replace(/\/+$/, '');
    var AB_VDITOR_ADAPTER_VERSION = '2.1.1';

    var _abVditorZhI18n = {
        'alignCenter': '居中',
        'alignLeft': '居左',
        'alignRight': '居右',
        'alternateText': '替代文本',
        'bold': '粗体',
        'both': '编辑 & 预览',
        'cancelUpload': '取消上传',
        'check': '任务列表',
        'close': '关闭',
        'code': '代码块',
        'code-theme': '代码块主题预览',
        'column': '列',
        'comment': '评论',
        'confirm': '确定',
        'content-theme': '内容主题预览',
        'copied': '已复制',
        'copy': '复制',
        'delete-column': '删除列',
        'delete-row': '删除行',
        'devtools': '开发者工具',
        'down': '下',
        'downloadTip': '该浏览器不支持下载功能',
        'edit': '编辑',
        'edit-mode': '切换编辑模式',
        'emoji': '表情',
        'export': '导出',
        'fileTypeError': '文件类型不允许上传，请压缩后再试',
        'footnoteRef': '脚注标识',
        'fullscreen': '全屏切换',
        'generate': '生成中',
        'headings': '标题',
        'heading1': '一级标题',
        'heading2': '二级标题',
        'heading3': '三级标题',
        'heading4': '四级标题',
        'heading5': '五级标题',
        'heading6': '六级标题',
        'help': '帮助',
        'imageURL': '图片地址',
        'indent': '列表缩进',
        'info': '关于',
        'inline-code': '行内代码',
        'insert-after': '末尾插入行',
        'insert-before': '起始插入行',
        'insertColumnLeft': '在左边插入一列',
        'insertColumnRight': '在右边插入一列',
        'insertRowAbove': '在上方插入一行',
        'insertRowBelow': '在下方插入一行',
        'instantRendering': '即时渲染',
        'italic': '斜体',
        'language': '语言',
        'line': '分隔线',
        'link': '链接',
        'linkRef': '引用标识',
        'list': '无序列表',
        'more': '更多',
        'nameEmpty': '文件名不能为空',
        'ordered-list': '有序列表',
        'outdent': '列表反向缩进',
        'outline': '大纲',
        'over': '超过',
        'performanceTip': '实时预览需 ${x}ms，可点击编辑 & 预览按钮进行关闭',
        'preview': '预览',
        'quote': '引用',
        'record': '开始录音/结束录音',
        'record-tip': '该设备不支持录音功能',
        'recording': '录音中...',
        'redo': '重做',
        'remove': '删除',
        'row': '行',
        'spin': '旋转',
        'splitView': '分屏预览',
        'strike': '删除线',
        'table': '表格',
        'textIsNotEmpty': '文本（不能为空）',
        'title': '标题',
        'tooltipText': '提示文本',
        'undo': '撤销',
        'up': '上',
        'update': '更新',
        'upload': '上传图片或文件',
        'uploadError': '上传错误',
        'uploading': '上传中...',
        'wysiwyg': '所见即所得'
    };

    function _abInstallVditorPromiseGuard() {
        if (window._abVditorPromiseGuardInstalled) return;
        window._abVditorPromiseGuardInstalled = true;

        window.addEventListener('unhandledrejection', function(ev) {
            if (!ev) return;
            var reason = ev.reason;
            var target = reason && (reason.target || reason.srcElement);
            if (!target || String(target.tagName || '').toUpperCase() !== 'SCRIPT') return;

            var src = String(target.src || '');
            if (!src) return;

            if (src.indexOf('/vditor/') === -1 && src.indexOf('/assets/lib/vditor/') === -1) return;

            if (typeof ev.preventDefault === 'function') {
                ev.preventDefault();
            }
            console.error('[AB] Vditor 资源加载失败：' + src);
        });
    }

    _abInstallVditorPromiseGuard();

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

    // ── 触发 Vditor 工具栏按钮「真正监听的那个事件」─────────────────────────
    // Vditor 用具 getEventName() 决定绑定哪个事件：UA 含 "iPhone" → touchstart，
    // 其余 → click。真机 iOS 上只 dispatch click 是**不会**触发的。
    // 模式切换是「设为某个模式」而不是取反，重复触发幂等，所以两个都发是安全的。
    function _abFireVditorToolbarEvent(el) {
        if (!el) return;
        if (navigator.userAgent.indexOf('iPhone') > -1) {
            try {
                if (typeof Touch === 'function' && typeof TouchEvent === 'function') {
                    var t = new Touch({ identifier: Date.now(), target: el, clientX: 0, clientY: 0 });
                    el.dispatchEvent(new TouchEvent('touchstart', {
                        bubbles: true, cancelable: true,
                        touches: [t], targetTouches: [t], changedTouches: [t]
                    }));
                } else {
                    var ev = document.createEvent('Event');
                    ev.initEvent('touchstart', true, true);
                    el.dispatchEvent(ev);
                }
            } catch (e) { /* 部分浏览器构造不了 TouchEvent → 至少下面的 click 还有机会 */ }
        }
        try { el.click(); } catch (e2) {}
    }

    // ── Vditor 作用域：窄屏下第一行滚动行会被停靠到 #ab-vditor-wrap ─────────
    // 停靠后它就是 #ab-vditor 的**兄弟**，里头那些按钮（含隐藏的 button[data-mode]）
    // 和面板都跑到了 container 之外。凡「在 Vditor 内部找东西 / 观察它内部变化」的代码
    // 都必须用这个作用域，否则移动端会静默失效（真踩过：模式按钮找不到、
    // 面板被滚动行裁掉、模式高亮不跟随）。
    function _abVditorScope(container) {
        var wrap = container && container.parentNode;
        return (wrap && wrap.id === 'ab-vditor-wrap') ? wrap : container;
    }

    // ── 切换编辑模式 ───────────────────────────────────────────────────────
    // Vditor 没有公开的 setMode，只能驱动它自己的 edit-mode 面板按钮
    // （那几个 button[data-mode] 就藏在工具栏里，AB 的 CSS 将外层 li 隐藏但元素在 DOM 中）。
    function _abSetVditorMode(vd, containerId, mode) {
        if (vd && typeof vd.setMode === 'function') {
            vd.setMode(mode);
            return;
        }
        var container = document.getElementById(containerId);
        if (!container) {
            console.warn('[AB] Vditor: setMode 失败（找不到容器）', mode);
            return;
        }
        var btn = _abVditorScope(container).querySelector('button[data-mode="' + mode + '"]');
        if (!btn) {
            console.warn('[AB] Vditor: setMode 失败（找不到模式按钮）', mode);
            return;
        }
        _abFireVditorToolbarEvent(btn);
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

    // WMD 默认按钮（非扩展按钮）id 列表
    var _abWmdBuiltinButtonIds = {
        'wmd-bold-button': 1,
        'wmd-italic-button': 1,
        'wmd-link-button': 1,
        'wmd-quote-button': 1,
        'wmd-code-button': 1,
        'wmd-image-button': 1,
        'wmd-olist-button': 1,
        'wmd-ulist-button': 1,
        'wmd-heading-button': 1,
        'wmd-hr-button': 1,
        'wmd-more-button': 1,
        'wmd-undo-button': 1,
        'wmd-redo-button': 1,
        'wmd-fullscreen-button': 1,
        'wmd-exit-fullscreen-button': 1
    };

    var _abLegacyWmdButtonMap = {};
    var _abLegacyNameCounter = 0;
    var _abLegacyBridgeStopper = null;
    var _abLegacyCaptureWatcher = null;

    function _abEscHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function _abGetMainTextarea() {
        return document.getElementById('text') || document.querySelector('textarea[name="text"]');
    }

    function _abEscapeMarkdownLabel(text) {
        return String(text || '')
            .replace(/\\/g, '\\\\')
            .replace(/\[/g, '\\[')
            .replace(/\]/g, '\\]');
    }

    function _abEscapeMarkdownUrl(url) {
        return String(url || '')
            .replace(/\s/g, '%20')
            .replace(/\(/g, '%28')
            .replace(/\)/g, '%29');
    }

    function _abBuildAttachmentMarkdown(file, url, isImage) {
        var safeUrl = _abEscapeMarkdownUrl(url).trim();
        if (!safeUrl) return '';

        var isImg = !!(
            isImage === true || isImage === 1 || isImage === '1' ||
            String(isImage || '').toLowerCase() === 'true'
        );
        var label = _abEscapeMarkdownLabel(file || (isImg ? 'image' : 'file'));
        return isImg ? ('![' + label + '](' + safeUrl + ')') : ('[' + label + '](' + safeUrl + ')');
    }

    function _abInsertMdIntoTextarea(mdText) {
        var ta = _abGetMainTextarea();
        if (!ta) return false;

        var value = String(ta.value || '');
        var start = (typeof ta.selectionStart === 'number') ? ta.selectionStart : value.length;
        var end = (typeof ta.selectionEnd === 'number') ? ta.selectionEnd : start;
        if (start > end) {
            var tmp = start;
            start = end;
            end = tmp;
        }

        ta.value = value.slice(0, start) + mdText + value.slice(end);
        var caret = start + mdText.length;
        if (typeof ta.setSelectionRange === 'function') {
            ta.setSelectionRange(caret, caret);
        }

        try { ta.focus(); } catch (e) {}

        try {
            var evt = document.createEvent('Event');
            evt.initEvent('input', true, true);
            ta.dispatchEvent(evt);
        } catch (e2) {}

        return true;
    }

    function _abInsertMarkdownWithVditor(mdText) {
        var vd = window.__abVditor;
        if (!mdText) return false;

        if (vd) {
            try { if (typeof vd.focus === 'function') vd.focus(); } catch (e0) {}

            try {
                if (typeof vd.insertMD === 'function') {
                    vd.insertMD(mdText);
                    _abSyncTextareaFromVditor();
                    return true;
                }
            } catch (e1) {}

            try {
                if (typeof vd.insertValue === 'function') {
                    vd.insertValue(mdText, true);
                    _abSyncTextareaFromVditor();
                    return true;
                }
            } catch (e2) {}

            try {
                if (typeof vd.getValue === 'function' && typeof vd.setValue === 'function') {
                    vd.setValue(String(vd.getValue() || '') + mdText);
                    _abSyncTextareaFromVditor();
                    return true;
                }
            } catch (e3) {}
        }

        return _abInsertMdIntoTextarea(mdText);
    }

    function _abInstallTypechoInsertFileBridge() {
        var typecho = window.Typecho;
        if (!typecho) return false;
        if (typecho._abInsertFileBridgeInstalled) return true;

        var originalInsert = (typeof typecho.insertFileToEditor === 'function') ? typecho.insertFileToEditor : null;

        var wrappedInsert = function(file, url, isImage) {
            var mdText = _abBuildAttachmentMarkdown(file, url, isImage);
            if (mdText && _abInsertMarkdownWithVditor(mdText)) {
                return;
            }

            if (typeof originalInsert === 'function') {
                try {
                    return originalInsert.apply(this, arguments);
                } catch (e) {
                    return;
                }
            }
        };

        try {
            Object.defineProperty(typecho, 'insertFileToEditor', {
                configurable: true,
                enumerable: true,
                get: function() {
                    return wrappedInsert;
                },
                set: function(fn) {
                    if (typeof fn === 'function' && fn !== wrappedInsert) {
                        originalInsert = fn;
                        typecho._abInsertFileToEditorOriginal = fn;
                    }
                }
            });
        } catch (e4) {
            typecho.insertFileToEditor = wrappedInsert;
        }

        typecho._abInsertFileBridgeInstalled = true;
        typecho._abInsertFileToEditorOriginal = originalInsert;
        return true;
    }

    function _abClampSelectionRange(range, maxLen) {
        if (!range || typeof range !== 'object') return null;
        var max = Math.max(0, parseInt(maxLen, 10) || 0);
        var start = parseInt(range.start, 10);
        var end = parseInt(range.end, 10);
        if (isNaN(start) || isNaN(end)) return null;

        if (start < 0) start = 0;
        if (end < 0) end = 0;
        if (start > max) start = max;
        if (end > max) end = max;
        if (end < start) {
            var tmp = start;
            start = end;
            end = tmp;
        }
        return { start: start, end: end };
    }

    // ── 读当前编辑器内的选区（换算成「全文字符偏移」）───────────────────────
    //  Vditor 4.0.0 起 SV 模式由 contenteditable <pre> 改为 <textarea>，
    //  textarea 内部选区不会出现在 window.getSelection() 里（Range 方案直接失效），
    //  所以按元素类型分流：textarea 读 selectionStart/selectionEnd，其余模式仍走 Range。
    function _abGetVditorSelectionRangeHint() {
        var vd = window.__abVditor;
        if (!vd || !vd.vditor) return null;

        var mode = '';
        try {
            mode = typeof vd.getCurrentMode === 'function' ? vd.getCurrentMode() : (vd.vditor.currentMode || '');
        } catch (e0) {}

        var modeState = vd.vditor[mode];
        var editor = modeState && modeState.element;
        if (!editor) return null;

        // ① textarea 编辑器：Vditor 4.0.0+ 的 SV 模式
        if (typeof editor.setSelectionRange === 'function' && typeof editor.selectionStart === 'number') {
            var taStart = editor.selectionStart;
            var taEnd   = editor.selectionEnd;
            if (typeof taEnd !== 'number') taEnd = taStart;
            return {
                start: Math.min(taStart, taEnd),
                end:   Math.max(taStart, taEnd)
            };
        }

        // ② contenteditable 编辑器：IR / WYSIWYG，以及 Vditor 3.x 的 SV
        if (typeof editor.contains !== 'function') return null;

        var selection;
        var range;
        try {
            selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) return null;
            range = selection.getRangeAt(0);
        } catch (e1) {
            return null;
        }
        if (!range) return null;

        var startNode = range.startContainer;
        var endNode = range.endContainer;
        var inEditor =
            (editor === startNode || editor.contains(startNode)) &&
            (editor === endNode || editor.contains(endNode));
        if (!inEditor) return null;

        try {
            var beforeStart = range.cloneRange();
            beforeStart.selectNodeContents(editor);
            beforeStart.setEnd(startNode, range.startOffset);

            var beforeEnd = range.cloneRange();
            beforeEnd.selectNodeContents(editor);
            beforeEnd.setEnd(endNode, range.endOffset);

            return {
                start: beforeStart.toString().length,
                end: beforeEnd.toString().length
            };
        } catch (e2) {
            return null;
        }
    }

    function _abSyncTextareaFromVditor(selectionHint) {
        var ta = _abGetMainTextarea();
        if (!ta || !window.__abVditor || typeof window.__abVditor.getValue !== 'function') return;
        try {
            var nextVal = String(window.__abVditor.getValue() || '');
            ta.value = nextVal;
            var normalized = _abClampSelectionRange(selectionHint, nextVal.length);
            if (normalized && typeof ta.setSelectionRange === 'function') {
                ta.setSelectionRange(normalized.start, normalized.end);
            }
        } catch (e) {}
    }

    function _abSyncVditorFromTextarea() {
        var ta = _abGetMainTextarea();
        if (!ta || !window.__abVditor || typeof window.__abVditor.setValue !== 'function') return;
        var nextVal = String(ta.value || '');
        var current = '';
        try { current = String(window.__abVditor.getValue() || ''); } catch (e) {}
        if (nextVal === current) return;
        try { window.__abVditor.setValue(nextVal); } catch (e2) {}
    }

    function _abPrepareLegacyTextareaForAction(selectionHint) {
        var ta = _abGetMainTextarea();
        if (!ta) return null;
        var state = {
            el: ta,
            style: ta.getAttribute('style') || '',
            display: ta.style.display || '',
            selectionStart: ta.selectionStart,
            selectionEnd: ta.selectionEnd,
            focused: (document.activeElement === ta)
        };

        try {
            ta.style.setProperty('display', 'block', 'important');
            ta.style.setProperty('position', 'fixed', 'important');
            ta.style.setProperty('left', '-99999px', 'important');
            ta.style.setProperty('top', '0', 'important');
            ta.style.setProperty('width', '1px', 'important');
            ta.style.setProperty('height', '1px', 'important');
            ta.style.setProperty('opacity', '0', 'important');
            ta.style.setProperty('pointer-events', 'none', 'important');
            ta.focus();

            var normalized = _abClampSelectionRange(selectionHint, ta.value.length);
            if (!normalized) {
                normalized = _abClampSelectionRange({
                    start: state.selectionStart,
                    end: state.selectionEnd
                }, ta.value.length);
            }
            if (!normalized) {
                normalized = {
                    start: ta.value.length,
                    end: ta.value.length
                };
            }

            if (typeof ta.setSelectionRange === 'function') {
                ta.setSelectionRange(normalized.start, normalized.end);
            }
        } catch (e) {}

        return state;
    }

    function _abRestoreLegacyTextarea(state) {
        if (!state || !state.el) return;
        var ta = state.el;
        try {
            if (state.style) {
                ta.setAttribute('style', state.style);
            } else {
                ta.removeAttribute('style');
            }
            if (state.display) {
                ta.style.display = state.display;
            }
        } catch (e) {}
    }

    function _abStartLegacyTextareaBridge(maxMs, selectionHint, syncToVditor) {
        var state = _abPrepareLegacyTextareaForAction(selectionHint);
        if (!state || !state.el) {
            return function() {};
        }

        var ta = state.el;
        var lastValue = String(ta.value || '');
        var startedAt = Date.now();
        var aliveMs = Math.max(8000, parseInt(maxMs, 10) || 30000);
        var shouldSyncToVditor = (syncToVditor !== false);
        var timer = setInterval(function() {
            var current = String(ta.value || '');
            if (current !== lastValue) {
                lastValue = current;
                if (shouldSyncToVditor) {
                    _abSyncVditorFromTextarea();
                }
            }
            if (Date.now() - startedAt >= aliveMs) {
                clearInterval(timer);
                _abRestoreLegacyTextarea(state);
            }
        }, 120);

        return function() {
            clearInterval(timer);
            _abRestoreLegacyTextarea(state);
        };
    }

    function _abExtractInsertedTextFromDiff(beforeValue, afterValue) {
        var oldText = String(beforeValue || '');
        var newText = String(afterValue || '');
        if (oldText === newText) return '';

        var left = 0;
        var oldLen = oldText.length;
        var newLen = newText.length;
        while (left < oldLen && left < newLen && oldText.charCodeAt(left) === newText.charCodeAt(left)) {
            left++;
        }

        var rightOld = oldLen - 1;
        var rightNew = newLen - 1;
        while (rightOld >= left && rightNew >= left && oldText.charCodeAt(rightOld) === newText.charCodeAt(rightNew)) {
            rightOld--;
            rightNew--;
        }

        return newText.slice(left, rightNew + 1);
    }

    function _abWatchLegacyInsertedText(beforeValue, beforeRange, tipText) {
        var ta = _abGetMainTextarea();
        if (!ta) return;

        if (_abLegacyCaptureWatcher) {
            clearInterval(_abLegacyCaptureWatcher);
            _abLegacyCaptureWatcher = null;
        }

        var startedAt = Date.now();
        var timeoutMs = 45000;
        _abLegacyCaptureWatcher = setInterval(function() {
            var current = String(ta.value || '');
            if (current !== beforeValue) {
                clearInterval(_abLegacyCaptureWatcher);
                _abLegacyCaptureWatcher = null;

                var insertedText = _abExtractInsertedTextFromDiff(beforeValue, current);

                ta.value = beforeValue;
                var normalized = _abClampSelectionRange(beforeRange, beforeValue.length);
                if (normalized && typeof ta.setSelectionRange === 'function') {
                    ta.setSelectionRange(normalized.start, normalized.end);
                }
                _abSyncVditorFromTextarea();

                if (typeof _abLegacyBridgeStopper === 'function') {
                    _abLegacyBridgeStopper();
                    _abLegacyBridgeStopper = null;
                }

                if (String(insertedText || '').length > 0) {
                    _abOpenLegacyInsertTextModal(insertedText, tipText);
                }
                return;
            }

            if (Date.now() - startedAt >= timeoutMs) {
                clearInterval(_abLegacyCaptureWatcher);
                _abLegacyCaptureWatcher = null;
                if (typeof _abLegacyBridgeStopper === 'function') {
                    _abLegacyBridgeStopper();
                    _abLegacyBridgeStopper = null;
                }
            }
        }, 120);
    }

    function _abNormalizeTipText(raw) {
        return String(raw || '').replace(/\s+/g, ' ').trim();
    }

    function _abExtractToolbarTooltipText(el) {
        if (!el) return '';
        var tip = _abNormalizeTipText(
            el.getAttribute('aria-label') ||
            el.getAttribute('title') ||
            el.getAttribute('data-title') ||
            el.getAttribute('data-original-title') ||
            el.getAttribute('placeholder')
        );
        if (tip) return tip;
        return _abNormalizeTipText(el.textContent || el.getAttribute('data-type') || '');
    }

    function _abReadLegacyToolbarTip(li) {
        if (!li) return '';
        var tip = _abExtractToolbarTooltipText(li);
        if (tip) return tip;

        var clickable = li.querySelector('button,a,label,input,[role="button"]');
        if (clickable) {
            tip = _abExtractToolbarTooltipText(clickable);
            if (tip) return tip;
        }

        return _abNormalizeTipText(li.textContent || '');
    }

    function _abIsLegacyUploadButton(li, tip) {
        if (!li) return false;
        if (li.querySelector('input[type="file"]')) return true;
        var idText = String(li.id || '');
        var clsText = String(li.className || '');
        var allText = (idText + ' ' + clsText + ' ' + String(tip || '')).toLowerCase();
        return /\bupload\b/.test(allText) || allText.indexOf('上传') !== -1;
    }

    function _abTriggerNativeVditorUpload() {
        var candidates = [
            '#ab-vditor .vditor-toolbar button[data-type="upload"]',
            '#vditor .vditor-toolbar button[data-type="upload"]',
            '#ab-vditor .vditor-toolbar label[data-type="upload"]',
            '#vditor .vditor-toolbar label[data-type="upload"]',
            '#ab-vditor .vditor-toolbar div[data-type="upload"]',
            '#vditor .vditor-toolbar div[data-type="upload"]'
        ];
        for (var i = 0; i < candidates.length; i++) {
            var btn = document.querySelector(candidates[i]);
            if (!btn) continue;
            try { btn.click(); return true; } catch (e) {}
            _abDispatchClick(btn);
            return true;
        }
        return false;
    }

    function _abExtractLegacyToolbarIconHtml(li, tip) {
        if (_abIsLegacyUploadButton(li, tip)) {
            return '<span class="ab-icon">upload</span>';
        }

        var material = li.querySelector('.ab-icon, .material-icons-round, .material-icons');
        if (material) {
            var ligature = (material.textContent || '').trim() || 'extension';
            return '<span class="ab-icon">' + _abEscHtml(ligature) + '</span>';
        }

        var svg = li.querySelector('svg');
        if (svg) return svg.outerHTML;

        var img = li.querySelector('img');
        if (img) {
            var src = img.getAttribute('src') || '';
            return '<img class="ab-toolbar-custom-img" src="' + _abEscHtml(src) + '" alt="" />';
        }

        var iconLike = li.querySelector('i[class], span[class*="icon"], span[class*="fa-"]');
        if (iconLike) return iconLike.outerHTML;

        var textFallback = (tip || '').trim();
        if (textFallback) {
            return '<span class="ab-toolbar-custom-text">' + _abEscHtml(textFallback.slice(0, 2)) + '</span>';
        }
        return '<span class="ab-icon">extension</span>';
    }

    function _abDispatchClick(el) {
        if (!el) return;
        if (typeof el.click === 'function') {
            try { el.click(); return; } catch (e0) {}
        }

        var fireMouse = function(type) {
            try {
                el.dispatchEvent(new MouseEvent(type, {
                    bubbles: true,
                    cancelable: true,
                    view: window
                }));
                return true;
            } catch (e) {
                return false;
            }
        };

        var downOk = fireMouse('mousedown');
        var upOk = fireMouse('mouseup');
        var clickOk = fireMouse('click');
        if (downOk || upOk || clickOk) return;

        try {
            var ev = document.createEvent('MouseEvents');
            ev.initMouseEvent('mousedown', true, true, window, 1,
                0, 0, 0, 0, false, false, false, false, 0, null);
            el.dispatchEvent(ev);
            var ev2 = document.createEvent('MouseEvents');
            ev2.initMouseEvent('mouseup', true, true, window, 1,
                0, 0, 0, 0, false, false, false, false, 0, null);
            el.dispatchEvent(ev2);
            var ev3 = document.createEvent('MouseEvents');
            ev3.initMouseEvent('click', true, true, window, 1,
                0, 0, 0, 0, false, false, false, false, 0, null);
            el.dispatchEvent(ev3);
        } catch (e2) {}
    }

    function _abTriggerLegacyToolbarButton(name) {
        var target = _abLegacyWmdButtonMap[name];
        if (!target) return;

        var tip = _abReadLegacyToolbarTip(target);
        if (_abIsLegacyUploadButton(target, tip) && _abTriggerNativeVditorUpload()) {
            return;
        }

        var selectionHint = _abGetVditorSelectionRangeHint();
        _abSyncTextareaFromVditor(selectionHint);

        var ta = _abGetMainTextarea();
        if (!ta) return;
        var beforeValue = String(ta.value || '');
        var beforeRange = _abClampSelectionRange({
            start: ta.selectionStart,
            end: ta.selectionEnd
        }, beforeValue.length);
        if (!beforeRange) {
            beforeRange = { start: beforeValue.length, end: beforeValue.length };
        }

        if (typeof _abLegacyBridgeStopper === 'function') {
            _abLegacyBridgeStopper();
        }
        _abLegacyBridgeStopper = _abStartLegacyTextareaBridge(45000, selectionHint, false);

        _abDispatchClick(target);
        var clickable = target.querySelector('button,a,label,[role="button"]');
        if (clickable && clickable !== target) {
            _abDispatchClick(clickable);
        }
        var inputFile = target.querySelector('input[type="file"]');
        if (inputFile) {
            try { inputFile.click(); } catch (e3) { _abDispatchClick(inputFile); }
        }

        _abWatchLegacyInsertedText(beforeValue, beforeRange, tip);
    }

    function _abCollectLegacyToolbarExtras() {
        var row = document.getElementById('wmd-button-row');
        if (!row) return [];

        var extras = [];
        var btns = row.querySelectorAll('li.wmd-button');
        for (var i = 0; i < btns.length; i++) {
            var li = btns[i];
            var id = li.id || ('ab-wmd-extra-' + i);
            if (_abWmdBuiltinButtonIds[id]) continue;

            var existed = li.getAttribute('data-ab-vditor-name');
            if (existed && _abLegacyWmdButtonMap[existed]) continue;

            var tip = _abReadLegacyToolbarTip(li);
            if (!tip) tip = '扩展工具';
            var isUploadBtn = _abIsLegacyUploadButton(li, tip);

            var baseName = 'ab-extra-' + id.replace(/[^a-zA-Z0-9_-]/g, '-');
            var name = baseName;
            while (_abLegacyWmdButtonMap[name]) {
                _abLegacyNameCounter++;
                name = baseName + '-' + _abLegacyNameCounter;
            }

            _abLegacyWmdButtonMap[name] = li;
            li.setAttribute('data-ab-vditor-name', name);
            li.setAttribute('data-ab-vditor-upload', isUploadBtn ? '1' : '0');

            extras.push({
                name: name,
                tip: tip,
                icon: _abExtractLegacyToolbarIconHtml(li, tip),
                __abUpload: isUploadBtn,
                click: (function(nm) {
                    return function() {
                        _abTriggerLegacyToolbarButton(nm);
                    };
                })(name)
            });
        }

        return extras;
    }

    function _abReadExternalToolbarExtras() {
        var sourceList = [
            window.__AB_VDITOR_TOOLBAR_EXTRA__,
            window.AB_VDITOR_TOOLBAR_EXTRA,
            window.vditorToolbarExtra,
            window.VDITOR_TOOLBAR_EXTRA
        ];
        var extras = [];
        var used = {};

        for (var i = 0; i < sourceList.length; i++) {
            var src = sourceList[i];
            if (typeof src === 'function') {
                try { src = src(); } catch (e) { src = null; }
            }
            if (!Array.isArray(src)) continue;

            for (var j = 0; j < src.length; j++) {
                var item = src[j];
                if (!item) continue;

                if (typeof item === 'string') {
                    var strKey = 's:' + item;
                    if (used[strKey]) continue;
                    used[strKey] = 1;
                    extras.push(item);
                    continue;
                }

                if (typeof item !== 'object') continue;
                var objKey = 'o:' + (item.name || item.tip || ('idx-' + j));
                if (used[objKey]) continue;
                used[objKey] = 1;
                extras.push(item);
            }
        }

        return extras;
    }

    function _abMergeToolbarExtras(baseToolbar, extras) {
        var merged = baseToolbar.slice();
        if (!extras || !extras.length) return merged;

        var undoIndex = -1;
        for (var i = 0; i < merged.length; i++) {
            if (merged[i] === 'undo') {
                undoIndex = i;
                break;
            }
        }

        if (undoIndex < 0) {
            if (merged.length && merged[merged.length - 1] !== '|') {
                merged.push('|');
            }
            for (var j = 0; j < extras.length; j++) {
                merged.push(extras[j]);
            }
            return merged;
        }

        var beforeUndo = merged.slice(0, undoIndex);
        var afterUndo = merged.slice(undoIndex);
        if (beforeUndo.length && beforeUndo[beforeUndo.length - 1] !== '|') {
            beforeUndo.push('|');
        }
        for (var k = 0; k < extras.length; k++) {
            beforeUndo.push(extras[k]);
        }
        if (beforeUndo.length && beforeUndo[beforeUndo.length - 1] !== '|') {
            beforeUndo.push('|');
        }
        return beforeUndo.concat(afterUndo);
    }

    function _abHideLegacyEditorBars() {
        document.body.classList.add('ab-vditor-active');
        var $legacy = $('#wmd-button-bar, #wmd-button-row, #wmd-preview, #wmd-editarea');
        if (!$legacy.length) return;
        $legacy.addClass('ab-vditor-legacy-bar').hide();
    }

    function _abToolbarHasButtonType(toolbar, typeName) {
        if (!toolbar || !typeName) return false;
        var btns = toolbar.querySelectorAll('button[data-type], label[data-type], div[data-type]');
        for (var i = 0; i < btns.length; i++) {
            if (btns[i].getAttribute('data-type') === typeName) {
                return true;
            }
        }
        return false;
    }

    function _abFindToolbarUndoItem(toolbar) {
        if (!toolbar) return null;
        var undoBtn = toolbar.querySelector('[data-type="undo"]');
        if (!undoBtn) return null;
        return undoBtn.closest ? undoBtn.closest('li') : undoBtn.parentNode;
    }

    function _abFindToolbarExtraAnchor(toolbar) {
        return _abFindToolbarUndoItem(toolbar);
    }

    function _abIsToolbarExtraItem(item) {
        if (!item) return false;
        if (item.getAttribute('data-ab-extra') === '1') return true;

        var btn = item.querySelector('button[data-type], label[data-type], div[data-type]');
        if (!btn) return false;
        var type = String(btn.getAttribute('data-type') || '').trim();
        if (!type) return false;

        if (_abLegacyWmdButtonMap[type]) return true;
        if (type.indexOf('ab-extra-') === 0) return true;
        return !_abIconMap[type];
    }

    // ── 移动端两行工具栏：主按钮横向滚动 + 撤销/恢复/保存/关于单独一行 ──────
    // 做法：把 .vditor-toolbar 的直接子节点分到两个容器里：
    //   .ab-toolbar-scroller  主按钮（移动端 overflow-x:auto 横向滚动）
    //   .ab-toolbar-pinned    撤销 / 恢复 / 保存 / 关于（固定可见，不再埋在滑动行尽头）
    // 桌面端两个容器都是 display:contents，等于不存在，按钮仍按原来的 flex-wrap 排布。
    // 本函数幂等：位置已正确时不会产生任何 DOM 变更（否则会把自己的 MutationObserver 拖进循环）。
    var _abPinnedButtonSelectors = [
        'button[data-type="undo"]',
        'button[data-type="redo"]',
        '#ab-vditor-save-btn',
        '#ab-vditor-about-btn'
    ];

    function _abToolbarChild(toolbar, className) {
        if (!toolbar || !toolbar.children) return null;
        var kids = toolbar.children;
        for (var i = 0; i < kids.length; i++) {
            if (kids[i].classList && kids[i].classList.contains(className)) return kids[i];
        }
        return null;
    }

    // 扩展按钮要插到滚动行里（没建结构时退回工具栏本身）
    // 传 container（#ab-vditor / #vditor）：窄屏时滚动行会被挪到「编辑区末尾」，
    // 不再是工具栏的子节点，所以连它上一级容器一起找。
    function _abToolbarScroller(container) {
        if (!container) return null;
        for (var i = 0, hosts = [container, container.parentNode]; i < hosts.length; i++) {
            var host = hosts[i];
            if (!host || !host.querySelector) continue;
            var sc = host.querySelector('.ab-toolbar-scroller');
            if (sc) return sc;
        }
        return container.querySelector('.vditor-toolbar') || container;
    }

    // ── 停靠后同步 Vditor 主题环境到滚动行 ───────────────────────────────
    // Vditor 把主题类（vditor--dark）和**全部主题变量**都挂在 #ab-vditor 上
    // （.vditor{--border-color:…} / .vditor--dark{…}）。窄屏把第一行搬到编辑区末尾后，
    // 它不再是它们的后代，于是：
    //   ① `.vditor--dark :is(.vditor-toolbar,.ab-toolbar-scroller) …` 系列规则失配；
    //   ② `.vditor-toolbar .ab-icon{font-size:24px}` 失配 → 图标继承按钮的 font-size:0
    //      → **整行按钮空白，看起来只剩一条分隔线**（真踩过，别删这段）；
    //   ③ 变量失效，亮色下 .vditor-hint{background-color:var(--panel-background-color)}
    //      解析不出 → 从滚动行弹出的面板是透明的。
    // 所以这里：同步主题类（解决 ①②）+ 把关键变量的计算值复制到行上（解决 ③，
    // 亮色没有对应类名可用，只能复制；暗色复制上去也是同一个值）。
    var _abVditorThemeVars = [
        '--border-color', '--second-color', '--panel-background-color', '--panel-shadow',
        '--toolbar-background-color', '--toolbar-icon-color', '--toolbar-icon-hover-color',
        '--toolbar-height', '--toolbar-divider-margin-top',
        '--textarea-background-color', '--textarea-text-color', '--blockquote-color'
    ];

    function _abSyncDockedTheme(container, scroller) {
        if (!container || !scroller) return;
        // 只有「搬到编辑区末尾」才算停靠；桌面端滚动行仍在工具栏里（display:contents），
        // 那时它本来就是 #ab-vditor 的后代，什么都不用补。
        var docked = !!(scroller.parentNode && scroller.parentNode.id === 'ab-vditor-wrap');
        var i, cls, names = (container.className || '').split(/\s+/);

        // 先清掉行上残留的 vditor--* 与复制来的变量，再按当前位置重写
        var own = [];
        for (i = 0; i < scroller.classList.length; i++) {
            cls = scroller.classList[i];
            if (cls.indexOf('vditor--') === 0) own.push(cls);
        }
        for (i = 0; i < own.length; i++) scroller.classList.remove(own[i]);
        for (i = 0; i < _abVditorThemeVars.length; i++) scroller.style.removeProperty(_abVditorThemeVars[i]);
        if (!docked) return;

        for (i = 0; i < names.length; i++) {
            cls = names[i];
            if (cls && cls.indexOf('vditor--') === 0) scroller.classList.add(cls);
        }
        var cs = getComputedStyle(container);
        for (i = 0; i < _abVditorThemeVars.length; i++) {
            var v = cs.getPropertyValue(_abVditorThemeVars[i]);
            if (v && v.trim()) scroller.style.setProperty(_abVditorThemeVars[i], v.trim());
        }
    }

    function _abIsPinnedToolbarItem(el) {        if (!el || !el.querySelector) return false;
        for (var i = 0; i < _abPinnedButtonSelectors.length; i++) {
            if (el.querySelector(_abPinnedButtonSelectors[i])) return true;
        }
        return false;
    }

    function _abStructureToolbar(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;
        var toolbar = container.querySelector('.vditor-toolbar');
        if (!toolbar) return;

        var scroller = _abToolbarChild(toolbar, 'ab-toolbar-scroller');
        var pinned = _abToolbarChild(toolbar, 'ab-toolbar-pinned');
        // 窄屏时滚动行可能已被挪到编辑区末尾，那里也要找一次
        if (!scroller && container.parentNode) {
            scroller = container.parentNode.querySelector('.ab-toolbar-scroller');
        }

        if (!scroller && !pinned) {
            scroller = document.createElement('div');
            scroller.className = 'ab-toolbar-scroller';
            pinned = document.createElement('div');
            pinned.className = 'ab-toolbar-pinned';
            var initial = Array.prototype.slice.call(toolbar.children);
            toolbar.appendChild(scroller);
            toolbar.appendChild(pinned);
            for (var i = 0; i < initial.length; i++) {
                (scroller.appendChild(initial[i]));
            }
        } else {
            if (!scroller) { scroller = document.createElement('div'); scroller.className = 'ab-toolbar-scroller'; toolbar.insertBefore(scroller, toolbar.firstChild); }
            if (!pinned) { pinned = document.createElement('div'); pinned.className = 'ab-toolbar-pinned'; toolbar.appendChild(pinned); }
        }

        var children = Array.prototype.slice.call(toolbar.children);
        for (var k = 0; k < children.length; k++) {
            var child = children[k];
            if (child === scroller || child === pinned) continue;
            var target = _abIsPinnedToolbarItem(child) ? pinned : scroller;
            if (child.parentNode !== target) target.appendChild(child);
        }

        // 把误留在滚动行里的固定项收回（结构二次应用 / 一次性补正）
        var inScroller = Array.prototype.slice.call(scroller.children);
        for (var m = 0; m < inScroller.length; m++) {
            if (_abIsPinnedToolbarItem(inScroller[m])) pinned.appendChild(inScroller[m]);
        }

        // ── 滚动行挂在哪：窄屏→编辑区底部；宽屏→回到工具栏（那里是 display:contents，等于不存在）──
        // 只对 AB 自己的编辑器（#ab-vditor-wrap）生效；Mirages 等外部实例保持原样。
        // ⚠️ 全屏例外：全屏下 wrap 已是 100dvh 的 flex 列，滚动行回到工具栏顶部两行里更好用
        //    （用户要求「全屏时底部工具栏恢复顶部显示」）。
        var wrap = container.parentNode;
        var canDock = !!(wrap && wrap.id === 'ab-vditor-wrap');
        var wantDock = canDock && window.innerWidth <= 575 && !wrap.classList.contains('ab-fullscreen');
        if (wantDock) {
            if (scroller.parentNode !== wrap) wrap.appendChild(scroller);
            wrap.classList.add('ab-toolbar-docked');
        } else {
            if (scroller.parentNode !== toolbar) toolbar.appendChild(scroller);
            // 桌面端靠 display:contents 把两容器摊平，顺序必须是「滚动行 → 固定行」
            if (pinned && scroller.nextElementSibling !== pinned) toolbar.insertBefore(scroller, pinned);
            if (canDock) wrap.classList.remove('ab-toolbar-docked');
        }
        // 搬进搬出都要同步主题类（见 _abSyncDockedTheme 的注释）
        _abSyncDockedTheme(container, scroller);
    }

    function _abEnsureExtraDividerBeforeUndo(toolbar) {
        if (!toolbar) return;
        var undoItem = _abFindToolbarUndoItem(toolbar);
        if (!undoItem || !undoItem.parentNode) return;

        var immediatePrev = undoItem.previousElementSibling;

        // 清理撤销按钮前重复的 AB 自建分隔符
        while (
            immediatePrev &&
            immediatePrev.classList &&
            immediatePrev.classList.contains('vditor-toolbar__divider') &&
            immediatePrev.getAttribute('data-ab-extra-divider') === '1'
        ) {
            var stale = immediatePrev;
            immediatePrev = stale.previousElementSibling;
            stale.parentNode.removeChild(stale);
        }

        // 仅判断撤销按钮前的“相邻区域”是否存在扩展按钮
        var hasExtraBeforeUndo = false;
        var probe = immediatePrev;
        while (probe) {
            if (probe.classList && probe.classList.contains('vditor-toolbar__divider')) {
                probe = probe.previousElementSibling;
                continue;
            }
            if (_abIsToolbarExtraItem(probe)) {
                hasExtraBeforeUndo = true;
            }
            break;
        }

        if (hasExtraBeforeUndo) {
            if (
                undoItem.previousElementSibling &&
                undoItem.previousElementSibling.classList &&
                undoItem.previousElementSibling.classList.contains('vditor-toolbar__divider')
            ) {
                return;
            }
            var divider = document.createElement('li');
            divider.className = 'vditor-toolbar__divider';
            divider.setAttribute('data-ab-extra-divider', '1');
            // 用 undoItem.parentNode：移动端两行结构下 undo 可能在 .ab-toolbar-pinned 里，
            // 直接 toolbar.insertBefore(divider, undoItem) 会抛 NotFoundError。
            undoItem.parentNode.insertBefore(divider, undoItem);
            return;
        }

        if (
            immediatePrev &&
            immediatePrev.classList &&
            immediatePrev.classList.contains('vditor-toolbar__divider') &&
            immediatePrev.getAttribute('data-ab-extra-divider') === '1'
        ) {
            immediatePrev.parentNode.removeChild(immediatePrev);
        }
    }

    function _abTooltipDirectionClass(tipText, btn) {
        if (document.body.classList.contains('ab-vditor-fullscreen')) {
            if (btn && typeof btn.getBoundingClientRect === 'function') {
                var rect = btn.getBoundingClientRect();
                var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
                var edgeGap = 120;
                if (rect.left < edgeGap) return 'vditor-tooltipped__e';
                if ((viewportWidth - rect.right) < edgeGap) return 'vditor-tooltipped__w';
            }
            return 'vditor-tooltipped__s';
        }
        var normalized = _abNormalizeTipText(tipText).toLowerCase();
        if (!normalized) return 'vditor-tooltipped__n';
        if (normalized.length >= 14) return 'vditor-tooltipped__ne';
        return 'vditor-tooltipped__n';
    }

    function _abApplyToolbarButtonTooltipClass(btn, tipText) {
        if (!btn) return;
        btn.classList.remove('vditor-tooltipped__n', 'vditor-tooltipped__s', 'vditor-tooltipped__e', 'vditor-tooltipped__w', 'vditor-tooltipped__ne', 'vditor-tooltipped__nw', 'vditor-tooltipped__se', 'vditor-tooltipped__sw');
        btn.classList.add('vditor-tooltipped', _abTooltipDirectionClass(tipText, btn));
    }

    function _abRefreshToolbarTooltips(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;
        // 用作用域查：窄屏下第一行滚动行在 #ab-vditor 之外（停靠到编辑区末尾）
        var nodes = _abVditorScope(container).querySelectorAll('.vditor-toolbar button, .vditor-toolbar label, .vditor-toolbar div[data-type]');
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            var typeName = _abNormalizeTipText(node.getAttribute('data-type') || '').toLowerCase();
            var tip = _abExtractToolbarTooltipText(node);
            if (typeName === 'upload' || _abIsLegacyUploadButton(node, tip)) {
                tip = '上传图片或文件';
            }
            if (!tip) {
                tip = _abNormalizeTipText(node.getAttribute('data-type') || '扩展工具');
            }
            node.setAttribute('aria-label', tip);
            node.setAttribute('title', tip);
            _abApplyToolbarButtonTooltipClass(node, tip);
        }
    }

    function _abEnsureUploadInputTrigger(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;
        var uploads = _abVditorScope(container).querySelectorAll('.vditor-toolbar [data-type="upload"]');
        for (var i = 0; i < uploads.length; i++) {
            var node = uploads[i];
            if (!node || node._abUploadTriggerBound) continue;

            var input = node.querySelector('input[type="file"]');
            if (!input) continue;

            node.addEventListener('click', function(ev) {
                var target = ev && ev.target;
                if (target && String(target.tagName || '').toLowerCase() === 'input') return;
                var fileInput = this.querySelector('input[type="file"]');
                if (!fileInput) return;
                try { fileInput.click(); } catch (e) {}
            });

            node._abUploadTriggerBound = true;
        }
    }

    function _abEnsureToolbarExtras(containerId, extras) {
        if (!extras || !extras.length) return;
        var container = document.getElementById(containerId);
        if (!container) return;
        var toolbar = container.querySelector('.vditor-toolbar');
        if (!toolbar) return;

        // ⚠️ 插入目标必须是滚动行（移动端两行结构的上面那行），不能直接用 toolbar，
        //    否则扩展按钮会落到「撤销/恢复/保存」那一行之后。
        var host = _abToolbarScroller(container);
        var anchorItem = _abFindToolbarExtraAnchor(toolbar);
        var changed = false;
        for (var i = 0; i < extras.length; i++) {
            var extra = extras[i];
            if (!extra || typeof extra !== 'object') continue;

            var name = String(extra.name || '').trim();
            if (!name) continue;
            if (_abToolbarHasButtonType(toolbar, name)) continue;
            var tip = _abNormalizeTipText(extra.tip || '扩展工具');

            var li = document.createElement('li');
            li.className = 'vditor-toolbar__item';
            li.setAttribute('data-ab-extra', '1');

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.setAttribute('data-type', name);
            btn.setAttribute('aria-label', tip);
            btn.setAttribute('title', tip);
            _abApplyToolbarButtonTooltipClass(btn, tip);

            if (typeof extra.icon === 'string' && extra.icon.trim()) {
                btn.innerHTML = extra.icon;
            } else {
                btn.innerHTML = '<span class="ab-icon">extension</span>';
            }

            if (extra.__abUpload || _abIsLegacyUploadButton(li, tip)) {
                btn.classList.add('ab-toolbar-custom-upload');
            }

            if (typeof extra.click === 'function') {
                (function(clickHandler) {
                    btn.addEventListener('click', function(ev) {
                        ev.preventDefault();
                        clickHandler();
                    });
                })(extra.click);
            }

            li.appendChild(btn);
            if (anchorItem && anchorItem.parentNode === host) {
                host.insertBefore(li, anchorItem);
            } else {
                host.appendChild(li);
            }
            changed = true;
        }

        _abEnsureExtraDividerBeforeUndo(toolbar);

        if (changed) {
            _abApplyVditorIcons(containerId);
            _abRefreshToolbarTooltips(containerId);
        }
    }

    function _abSyncToolbarExtras(containerId) {
        // 先把两行结构的容器建好，后面的扩展按钮才知道该往哪里放
        _abStructureToolbar(containerId);
        var legacyToolbarExtras = _abCollectLegacyToolbarExtras();
        var externalToolbarExtras = _abReadExternalToolbarExtras();
        _abEnsureToolbarExtras(containerId, legacyToolbarExtras.concat(externalToolbarExtras));
        // 结构建好后才能挂滚动提示（它需要 .ab-toolbar-scroller 已存在）
        _abWatchToolbarScroll(containerId);
    }

    function _abWatchLegacyToolbarExtras(containerId) {
        var row = document.getElementById('wmd-button-row');
        if (!row || row._abVditorExtrasObserver) return;

        var pending = false;
        var sync = function() { _abSyncToolbarExtras(containerId); };
        sync();

        var obs = new MutationObserver(function() {
            if (pending) return;
            pending = true;
            setTimeout(function() {
                pending = false;
                sync();
            }, 40);
        });
        obs.observe(row, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style', 'title', 'aria-label']
        });
        row._abVditorExtrasObserver = obs;
    }

    function _abApplyCustomToolbarIconStyle(btn) {
        if (!btn) return;
        if (btn.classList.contains('ab-toolbar-custom-ready')) return;
        btn.classList.add('ab-toolbar-custom-btn', 'ab-toolbar-custom-ready');

        var tip = _abExtractToolbarTooltipText(btn);
        var typeName = _abNormalizeTipText(btn.getAttribute('data-type') || '').toLowerCase();
        if (typeName === 'upload' || _abIsLegacyUploadButton(btn, tip)) {
            tip = '上传图片或文件';
        }
        if (!tip) tip = '扩展工具';
        btn.setAttribute('aria-label', tip);
        btn.setAttribute('title', tip);
        _abApplyToolbarButtonTooltipClass(btn, tip);

        if (_abIsLegacyUploadButton(btn, tip) || (btn.getAttribute('data-type') || '') === 'upload') {
            if (!btn.querySelector('.ab-icon')) {
                var uploadIcon = document.createElement('span');
                uploadIcon.className = 'ab-icon';
                uploadIcon.textContent = 'upload';
                btn.appendChild(uploadIcon);
            }
            btn.classList.add('ab-toolbar-custom-upload', 'ab-iconized');
            return;
        }

        if (btn.querySelector('.ab-icon')) {
            btn.classList.add('ab-iconized');
            return;
        }

        var svg = btn.querySelector('svg');
        if (svg) {
            svg.classList.add('ab-toolbar-custom-svg');
            return;
        }

        var img = btn.querySelector('img');
        if (img) {
            img.classList.add('ab-toolbar-custom-img');
            return;
        }

        var iconFont = btn.querySelector('i, .material-icons, .material-icons-round, [class*="icon"]');
        if (iconFont) {
            iconFont.classList.add('ab-toolbar-custom-iconfont');
            return;
        }

        var text = (btn.textContent || '').replace(/\s+/g, ' ').trim();
        if (!text) {
            var fallback = document.createElement('span');
            fallback.className = 'ab-icon';
            fallback.textContent = 'extension';
            btn.appendChild(fallback);
            btn.classList.add('ab-iconized');
            return;
        }

        if (btn.children.length === 0) {
            btn.textContent = '';
            var textBadge = document.createElement('span');
            textBadge.className = 'ab-toolbar-custom-text';
            textBadge.textContent = text.length > 4 ? text.slice(0, 4) : text;
            btn.appendChild(textBadge);
        }
    }

    // ── 用 JS 注入 <span class="ab-icon"> 替换 SVG（连字必须是文本节点）──
    function _abApplyVditorIcons(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;
        // 用作用域查：窄屏下第一行滚动行在 #ab-vditor 之外（停靠到编辑区末尾）
        var btns = _abVditorScope(container).querySelectorAll('.vditor-toolbar button, .vditor-toolbar label, .vditor-toolbar div[data-type]');
        for (var i = 0; i < btns.length; i++) {
            var btn = btns[i];
            var type = btn.getAttribute('data-type') || '';
            var iconName = _abIconMap[type];
            if (!iconName && _abIsLegacyUploadButton(btn, type)) {
                iconName = 'upload';
            }
            if (!iconName) {
                _abApplyCustomToolbarIconStyle(btn);
                continue;
            }

            // 避免重复注入
            if (!btn.querySelector('.ab-icon')) {
                var span = document.createElement('span');
                span.className = 'ab-icon';
                span.textContent = iconName;  // 文本节点才能触发 Material Icons 连字
                btn.appendChild(span);
            }
            btn.classList.add('ab-iconized');
            if (iconName === 'upload') {
                btn.classList.add('ab-toolbar-custom-upload');
            }
        }
        _abEnsureUploadInputTrigger(containerId);
        _abRefreshToolbarTooltips(containerId);
    }

    // ── 为浮动工具栏（vditor-panel）按钮注入 Material Icons Round 图标 ───────
    function _abApplyPanelIcons(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;

        if (container._abPanelIconsInitDone) return;
        container._abPanelIconsInitDone = true;

        var isUndefinedText = function(text) {
            var t = _abNormalizeTipText(text || '').toLowerCase();
            return !t || t === 'undefined' || t.indexOf('undefined<') === 0 || t.indexOf('undefined ') === 0;
        };

        var panelLabelByType = {
            'left': '居左',
            'center': '居中',
            'right': '居右',
            'up': '上',
            'down': '下',
            'remove': '删除',
            'deleteRow': '删除行',
            'deleteColumn': '删除列'
        };

        var panelLabelByTypeNth = {
            'insertRow': ['在下方插入一行', '在上方插入一行'],
            'insertColumn': ['在右边插入一列', '在左边插入一列']
        };

        var labelToIconRules = [
            { re: /(居左|左对齐|align left)/i, icon: 'format_align_left' },
            { re: /(居中|居中对齐|align center)/i, icon: 'format_align_center' },
            { re: /(居右|右对齐|align right)/i, icon: 'format_align_right' },
            { re: /(\b上\b|向上|move up|\bup\b)/i, icon: 'keyboard_arrow_up' },
            { re: /(\b下\b|向下|move down|\bdown\b)/i, icon: 'keyboard_arrow_down' },
            { re: /(上方插入一行|insert row above)/i, icon: 'vertical_align_top' },
            { re: /(下方插入一行|insert row below)/i, icon: 'vertical_align_bottom' },
            { re: /(左边插入一列|insert column left)/i, icon: 'border_left' },
            { re: /(右边插入一列|insert column right)/i, icon: 'border_right' },
            { re: /(删除|移除|remove|trash)/i, icon: 'delete' },
            { re: /(删除行|delete row)/i, icon: 'delete' },
            { re: /(删除列|delete column)/i, icon: 'delete' },
            { re: /(评论|comment)/i, icon: 'comment' }
        ];

        // data-type → 图标（唯一 type）
        var panelIconMap = {
            'left':         'format_align_left',
            'center':       'format_align_center',
            'right':        'format_align_right',
            'up':           'keyboard_arrow_up',
            'down':         'keyboard_arrow_down',
            'remove':       'delete',
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

        var panelSvgSymbolIconMap = {
            'up': 'keyboard_arrow_up',
            'down': 'keyboard_arrow_down',
            'trashcan': 'delete',
            'delete': 'delete',
            'remove': 'delete',
            'align-left': 'format_align_left',
            'align-center': 'format_align_center',
            'align-right': 'format_align_right',
            'insert-row': 'vertical_align_bottom',
            'insert-rowb': 'vertical_align_top',
            'insert-column': 'border_right',
            'insert-columnb': 'border_left',
            'comment': 'comment'
        };

        var getPanelIconFromSvgSymbol = function(btn) {
            if (!btn) return '';
            var useNode = btn.querySelector('use');
            if (!useNode) return '';
            var href = useNode.getAttribute('xlink:href') || useNode.getAttribute('href') || '';
            var symbol = String(href).replace(/^#vditor-icon-/, '').trim();
            if (!symbol) return '';
            return panelSvgSymbolIconMap[symbol] || '';
        };

        var removePanelSvgNodes = function(btn) {
            if (!btn) return;
            var svgs = btn.querySelectorAll('svg');
            for (var si = 0; si < svgs.length; si++) {
                var node = svgs[si];
                if (node && node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            }
        };

        function injectPanelIcons() {
            var panels = _abVditorScope(container).querySelectorAll('.vditor-panel');
            Array.prototype.forEach.call(panels, function(panel) {
                var typeCount = {};
                // 包含无 data-type 的按钮（评论等）
                var btns = panel.querySelectorAll('button.vditor-icon');
                Array.prototype.forEach.call(btns, function(btn) {
                    var type = btn.getAttribute('data-type');
                    var iconName;
                    var labelName = '';
                    if (type) {
                        if (!typeCount[type]) typeCount[type] = 0;
                        var nth = typeCount[type]++;
                        if (panelIconMap[type]) {
                            iconName = panelIconMap[type];
                            labelName = panelLabelByType[type] || '';
                        } else if (panelIconMapNth[type]) {
                            iconName = panelIconMapNth[type][nth] || panelIconMapNth[type][0];
                            if (panelLabelByTypeNth[type]) {
                                labelName = panelLabelByTypeNth[type][nth] || panelLabelByTypeNth[type][0] || '';
                            }
                        }
                    } else {
                        // 无 data-type：aria-label 关键字匹配
                        var ariaLabel = (btn.getAttribute('aria-label') || '').toLowerCase();
                        var ariaKeys = Object.keys(ariaLabelIconMap);
                        for (var ki = 0; ki < ariaKeys.length; ki++) {
                            if (ariaLabel.indexOf(ariaKeys[ki]) !== -1) {
                                iconName = ariaLabelIconMap[ariaKeys[ki]];
                                if (ariaKeys[ki] === 'comment') labelName = '评论';
                                break;
                            }
                        }
                    }

                    var currentLabel = _abNormalizeTipText(btn.getAttribute('aria-label') || btn.getAttribute('title') || '');
                    if (isUndefinedText(currentLabel)) {
                        var fallbackLabel = labelName || _abNormalizeTipText(type || btn.textContent || '工具操作');
                        if (!fallbackLabel || fallbackLabel.toLowerCase() === 'undefined') {
                            fallbackLabel = '工具操作';
                        }
                        btn.setAttribute('aria-label', fallbackLabel);
                        btn.setAttribute('title', fallbackLabel);
                        currentLabel = fallbackLabel;
                    }

                    if (!iconName && currentLabel) {
                        for (var ri = 0; ri < labelToIconRules.length; ri++) {
                            if (labelToIconRules[ri].re.test(currentLabel)) {
                                iconName = labelToIconRules[ri].icon;
                                break;
                            }
                        }
                    }

                    if (!iconName) {
                        iconName = getPanelIconFromSvgSymbol(btn);
                    }

                    if (!iconName) return;
                    // 避免重复注入
                    if (btn.querySelector('.ab-icon')) {
                        if (btn.querySelector('svg')) {
                            removePanelSvgNodes(btn);
                        }
                        if (!btn.classList.contains('ab-iconized')) {
                            btn.classList.add('ab-iconized');
                        }
                        return;
                    }
                    var span = document.createElement('span');
                    span.className = 'ab-icon';
                    span.textContent = iconName;
                    btn.appendChild(span);
                    removePanelSvgNodes(btn);
                    btn.classList.add('ab-iconized');
                });
            });
        }
        injectPanelIcons();
        // 观察各面板：仅监听子树节点变化，并做节流，避免频繁回调导致卡顿
        var panelPending = false;
        var scheduleInjectPanelIcons = function() {
            if (panelPending) return;
            panelPending = true;
            setTimeout(function() {
                panelPending = false;
                injectPanelIcons();
            }, 40);
        };

        var panelObs = new MutationObserver(function() { scheduleInjectPanelIcons(); });
        Array.prototype.forEach.call(_abVditorScope(container).querySelectorAll('.vditor-panel'), function(panel) {
            panelObs.observe(panel, { childList: true, subtree: true });
        });

        container._abPanelIconsObserver = panelObs;
    }

    // ── 监听 Vditor 内部模式切换（同步 ab-mode-bar 激活态）──────────────────
    function _abObserveModeChange(containerId, onModeChange) {
        var container = document.getElementById(containerId);
        if (!container) return;
        // ⚠️ 观察的是**作用域**而不是 container：窄屏下那排按钮（button[data-mode]）
        //    随滚动行停靠到了 #ab-vditor-wrap 里，只在 container 上观察收不到它的
        //    class 变化，模式栏高亮就不会跟随。
        var scope = _abVditorScope(container);
        var lastMode = '';

        // Vditor 把激活态类加在 edit-mode 下拉里的 button[data-mode] 上：vditor-menu--current
        // （旧代码用的 .vditor-toolbar__item--current 在 3.11.2 / 4.0.0 都不存在，
        //  选择器永远匹配不到，AB 模式栏的高亮也就从不跟随内部切换 —— 此处一并修正。）
        var readActiveMode = function() {
            var btn = scope.querySelector('button[data-mode].vditor-menu--current');
            return btn ? (btn.getAttribute('data-mode') || '') : '';
        };

        var obs = new MutationObserver(function() {
            var mode = readActiveMode();
            if (mode && mode !== lastMode) {
                lastMode = mode;
                onModeChange(mode);
            }
        });
        obs.observe(scope, { subtree: true, attributes: true, attributeFilter: ['class'] });

        // 首次渲染不会再触发 class 变动，这里主动对齐一次
        // （对 Mirages 这类已存在的实例尤其重要：它的模式未必等于本地记忆的模式）
        var initial = readActiveMode();
        if (initial) {
            lastMode = initial;
            onModeChange(initial);
        }
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
                _abSyncToolbarExtras(containerId);
                _abRefreshToolbarTooltips(containerId);
            }, 50);
        });
        obs.observe(toolbar, { childList: true, subtree: true });
    }

    // ── 移动端：工具栏横向滚动时临时解除裁剪 ───────────────────────────────
    // ≤768px 时工具栏改成单行横向滚动（overflow-x:auto），但工具栏的子面板
    // （vditor-hint / vditor-panel--arrow）就挂在 vditor-toolbar__item 里，
    // 会被滚动容器一起裁掉。
    // 做法：只要检测到「有子面板可见」，就把 overflow 临时改回 visible；
    //       关闭后再恢复，并还原横向滚动位置（避免收回时跳回最左边）。
    function _abWatchToolbarPanelClip(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;
        var toolbar = container.querySelector('.vditor-toolbar');
        if (!toolbar) return;
        // 需要解除裁剪的不止 toolbar：窄屏下第一行滚动行被停靠到编辑区末尾，
        // 它自己是 `overflow-x:auto; overflow-y:hidden` 的滚动容器，而面板就挂在
        // 它里头的 .vditor-toolbar__item 上 —— 向上/向下弹出都会被它裁掉
        // （实测点面板位置命中的是底下的编辑器，面板完全看不见）。
        var scope = _abVditorScope(container);

        if (toolbar._abClipObserver) {
            toolbar._abClipObserver.disconnect();
            toolbar._abClipObserver = null;
        }

        // 要解除裁剪的容器列表（工具栏 + 两行容器）
        var clipTargets = function() {
            var list = [toolbar];
            var rows = scope.querySelectorAll('.ab-toolbar-scroller, .ab-toolbar-pinned');
            for (var i = 0; i < rows.length; i++) {
                if (rows[i] !== toolbar && list.indexOf(rows[i]) === -1) list.push(rows[i]);
            }
            return list;
        };

        var isOpen = null;
        var sync = function() {
            var open = false;
            var panels = scope.querySelectorAll('.vditor-hint, .vditor-panel');
            for (var i = 0; i < panels.length; i++) {
                if (getComputedStyle(panels[i]).display !== 'none') { open = true; break; }
            }
            if (open === isOpen) return;
            var targets = clipTargets();
            for (var k = 0; k < targets.length; k++) {
                var el = targets[k];
                if (open) {
                    // overflow 一旦变 visible，元素不再是滚动容器，scrollLeft 会被清零 →
                    // 先存下来，关闭时还原（否则收回时滚动行会跳回最左边）
                    el._abClipScrollLeft = el.scrollLeft || 0;
                    el.style.setProperty('overflow-x', 'visible', 'important');
                    el.style.setProperty('overflow-y', 'visible', 'important');
                } else {
                    el.style.removeProperty('overflow-x');
                    el.style.removeProperty('overflow-y');
                    var back = el._abClipScrollLeft || 0;
                    el._abClipScrollLeft = 0;
                    if (back) el.scrollLeft = back;
                }
            }
            isOpen = open;
        };

        var pending = false;
        var obs = new MutationObserver(function(mutations) {
            // 只关心面板自身的显隐（面板靠 element.style.display 开关），
            // 忽略鼠标移动引起的 toolbar 类名变动，避免频繁强制样式重算。
            var relevant = false;
            for (var i = 0; i < mutations.length; i++) {
                var t = mutations[i].target;
                if (!t || !t.classList) continue;
                if (t.classList.contains('vditor-panel') || t.classList.contains('vditor-hint')) {
                    relevant = true;
                    break;
                }
            }
            if (!relevant || pending) return;
            pending = true;
            setTimeout(function() { pending = false; sync(); }, 60);
        });
        obs.observe(scope, { subtree: true, attributes: true, attributeFilter: ['style'] });
        toolbar._abClipObserver = obs;
        sync();
    }

    // ── 移动端：WYSIWYG 气泡工具栏不要盞住正在输入的光标 ──────────────────
    // Vditor 把气泡顶边放在「当前块 top - 21」（见其 setPopoverPosition），而气泡自身有高度，
    // 于是它向下压住块首行 —— 光标就在那里，输入时直接被盖住。
    // 这里在气泡每次定位后校验一次：与光标矩形相交就整体移位
    // （优先移到光标行上方，上方空间不够就移到下方）。
    // 移位量用**视口坐标差**计算，不依赖 Vditor 的坐标系，比较稳。
    function _abKeepWysiwygPopoverOffCaret(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;
        var wysiwyg = container.querySelector('.vditor-wysiwyg');
        if (!wysiwyg) return;

        // ⚠️ 不要用 CSS 选择器猜气泡：.vditor-wysiwyg 下有**两个** .vditor-panel--none
        //    （另一个是快捷插入条），选错就会去改一个不相干的元素。
        //    实例上的 vditor.wysiwyg.popover 才是权威引用。
        var popover = null;
        try {
            var vd = window.__abVditor;
            if (vd && vd.vditor && vd.vditor.wysiwyg && vd.vditor.wysiwyg.popover) {
                popover = vd.vditor.wysiwyg.popover;
                if (!container.contains(popover)) popover = null;
            }
        } catch (e) { popover = null; }
        if (!popover) {
            for (var i = 0; i < wysiwyg.children.length; i++) {
                var child = wysiwyg.children[i];
                if (!child.classList) continue;
                if (child.classList.contains('vditor-panel') && child.classList.contains('vditor-panel--none')) {
                    popover = child;
                    break;
                }
            }
        }
        if (!popover) return;
        if (popover._abCaretObserver) popover._abCaretObserver.disconnect();

        var adjust = function() {
            if (window.innerWidth > 575) return;                       // 只处理移动端
            if (getComputedStyle(popover).display === 'none') return;   // 气泡没显示
            var cur = parseFloat(popover.style.top);
            if (isNaN(cur)) return;

            var sel = window.getSelection();
            if (!sel || !sel.rangeCount) return;
            var range = sel.getRangeAt(0);
            if (!wysiwyg.contains(range.startContainer)) return;        // 光标不在编辑器里
            var cr = range.getBoundingClientRect();
            if (!cr || (cr.width === 0 && cr.height === 0)) {
                // ⚠️ 实测坑：Vditor 常把光标放在 <wbr> 这类空内联元素里，这种 range 的
                // rect 是 0×0；而 <wbr> 自身也没有盒子，只往上看一层同样是 0×0。
                // 所以要逐层往上找第一个「有尺寸」的祖先（通常就是当前块）。
                // 不这么做的后果：避让会在「光标在标题/列表首行」这个最需要它的场景下彻底失效。
                var node = range.startContainer;
                var host = node && node.nodeType === 1 ? node : (node ? node.parentElement : null);
                var hops = 0;
                while (host && host !== wysiwyg && hops < 4) {
                    var hRect = host.getBoundingClientRect();
                    if (hRect.width > 0 || hRect.height > 0) { cr = hRect; break; }
                    host = host.parentElement;
                    hops++;
                }
                // 退化成「整个编辑器」就没意义了，这种情况直接放弃
                if (!cr || host === wysiwyg || cr.height > (wysiwyg.clientHeight * 0.8)) return;
            }
            if (!cr || (cr.width === 0 && cr.height === 0)) return;

            var pr = popover.getBoundingClientRect();
            if (!pr.height) return;
            if (!(pr.top < cr.bottom && pr.bottom > cr.top)) return;   // 没和光标相交，不动

            // 用「绝对目标位置」而不是相对位移：重复调用结果一致（幂等），不会越调越偏。
            // 目标：整块挪到光标行上方，上方放不下就挪到下方；
            // 两边都放不下（编辑器很矮）就保持 Vditor 原样，绝不硬塞出编辑区。
            var base = wysiwyg.getBoundingClientRect();
            var gap = 6;
            var room = 4;
            var aboveTop = (cr.top - gap) - pr.height;
            var belowTop = cr.bottom + gap;
            var minTop = base.top + room;
            var maxTop = base.bottom - pr.height - room;
            var target = null;
            if (aboveTop >= minTop) target = aboveTop;
            else if (belowTop <= maxTop) target = belowTop;
            if (target === null) return;

            var next = Math.round(cur + (target - pr.top));
            if (Math.abs(cur - next) < 1) return;                      // 已到位，避免观察器自激
            popover.style.top = next + 'px';
        };

        var pending = false;
        var obs = new MutationObserver(function() {
            if (pending) return;
            pending = true;
            setTimeout(function() { pending = false; adjust(); }, 40);
        });
        obs.observe(popover, { attributes: true, attributeFilter: ['style'] });
        popover._abCaretObserver = obs;
    }

    // ── 移动端：横向滚动行的「还能滑多少」提示 ─────────────────────────────
    // 滚动行总宽约 976px / 可视 353px，只能看到约三分之一 —— 没有提示时
    // 用户不知道右边还有多少内容。这里把可见比例与已滑比例写成 CSS 变量，
    // 由 CSS 渲染成第一行底部的细进度条。
    // （曾经还有一层右侧渐隐遮罩，用户反馈「莫名出现遮罩」已删除，别加回来）
    function _abWatchToolbarScroll(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;
        var toolbar = container.querySelector('.vditor-toolbar');
        if (!toolbar) return;
        var scroller = _abToolbarScroller(container);
        if (!scroller || scroller === toolbar) return;   // 还没建两行结构

        // 指示器（进度条）画在滚动行的父节点上：窄屏下滚动行被停靠到编辑区末尾，
        // 父节点就是 #ab-vditor-wrap；宽屏回位后父节点是工具栏（那里是 display:contents）。

        // 幂等：结构重建（全屏切换、停靠/归位）后会再调一次，这时只需要重算
        if (scroller._abScrollSync) {
            scroller._abScrollSync();
            return;
        }
        var sync = function() {
            // ⚠️ 宿主必须在 sync 里现算：注册时捕获下来，档位切换后就是旧节点了，
            //    旧节点上会残留 ab-scroll-* 脏类（实测横屏后 #ab-vditor-wrap 带着 ab-scroll-none）。
            var host = scroller.parentNode || toolbar;
            if (scroller._abScrollHost && scroller._abScrollHost !== host) {
                scroller._abScrollHost.classList.remove('ab-scroll-none');
                scroller._abScrollHost.style.removeProperty('--ab-scroll-pos');
                scroller._abScrollHost.style.removeProperty('--ab-scroll-ratio');
            }
            scroller._abScrollHost = host;

            var max = scroller.scrollWidth - scroller.clientWidth;
            if (max <= 1) {                              // 放得下 → 什么都不用显示
                host.classList.add('ab-scroll-none');
                return;
            }
            host.classList.remove('ab-scroll-none');
            var pos = Math.min(1, Math.max(0, scroller.scrollLeft / max));
            var ratio = Math.min(1, scroller.clientWidth / scroller.scrollWidth);
            // pos 是「已滑比例」，进度条需要的是「还能滑的比例」，乘以 (1 - ratio) 即可
            host.style.setProperty('--ab-scroll-pos', (pos * (1 - ratio)).toFixed(4));
            host.style.setProperty('--ab-scroll-ratio', ratio.toFixed(4));
        };
        scroller._abScrollSync = sync;
        scroller.addEventListener('scroll', sync, { passive: true });
        if (!scroller._abScrollResizeBound) {
            scroller._abScrollResizeBound = true;
            window.addEventListener('resize', sync);   // 旋转屏幕后重算
        }
        sync();
    }

    // ── 键盘态：把编辑卡片顶到可视区最上方 / 还原 ──────────────────────────
    // 目的：键盘弹出后让 #ab-vditor 真的「铺满键盘上方的区域」，而不是被页面顶部的
    // 标题、选项挤成几十像素。只在卡片确实在下方时才滚，并记下滚前位置；
    // 键盘收起时若用户没有自己滚过，就还原回去（不抢用户的滚动位置）。
    var _abKbScrollFrom = null;
    var _abKbScrollTo = null;

    // ⚠️ 必须**即时**滚动：AB 样式里有 scroll-behavior:smooth，普通 scrollTo 会走动画，
    //    紧接着的 getBoundingClientRect() 量到的还是旧位置，高度就算错了
    //    （实测：卡片还在 265 处量出 271px，滚完之后才应该是 536px）。
    function _abScrollToInstant(y) {
        try {
            window.scrollTo({ top: y, left: 0, behavior: 'instant' });
        } catch (e) {
            window.scrollTo(0, y);
        }
    }

    function _abKbLiftIntoView(delta) {
        if (delta <= 8) return;                     // 已经在顶上（含 8px 容差）
        var scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        var docH = Math.max(document.documentElement.scrollHeight || 0, document.body ? document.body.scrollHeight || 0 : 0);
        var next = Math.min(Math.max(0, docH - window.innerHeight), scrollY + delta);
        if (next <= scrollY + 1) return;             // 已经滚到底，没得滚了
        if (_abKbScrollFrom === null) _abKbScrollFrom = scrollY;
        _abKbScrollTo = next;
        _abScrollToInstant(next);
    }

    function _abKbRestoreScroll() {
        if (_abKbScrollFrom === null) return;
        var from = _abKbScrollFrom;
        var to = _abKbScrollTo;
        _abKbScrollFrom = null;
        _abKbScrollTo = null;
        var scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        if (Math.abs(scrollY - to) > 4) return;      // 用户自己滚过 → 不还原
        _abScrollToInstant(from);
    }

    // 「键盘是否弹出」：iOS 只缩视觉视口、Android 缩的是布局视口，
    // 所以取两者的较小值，跟「本次方向下见过的最大值」比（130px 阈值避开地址栏收缩）。
    // 宽度变化（旋转屏）时重置基线，否则横屏会被一直当成开着键盘。
    var _abVpKbKey = null;
    var _abVpKbBaseline = 0;
    function _abIsKeyboardOpen() {
        var vv = window.visualViewport;
        var vvBottom = vv ? (vv.offsetTop + vv.height) : window.innerHeight;
        var h = Math.min(window.innerHeight, vvBottom);
        var key = window.innerWidth;
        if (_abVpKbKey !== key) { _abVpKbKey = key; _abVpKbBaseline = 0; }
        if (h > _abVpKbBaseline) _abVpKbBaseline = h;
        return (_abVpKbBaseline - h) > 130;
    }

    // ── 移动端：停靠行的可用高度（关着键盘 / 弹出键盘分别算） ───────────────
    // 滚动行停靠在编辑区底部后，「模式栏 + 编辑器 + 滚动行」总高会超过可视区，
    // 滚动行被顶到屏幕外（390×844 实测 scroller.top=849 > 844），等于白停靠。
    // 这里算出一个可用高度写进 --ab-dock-avail，CSS 用它给 wrap 定高，
    // 编辑器（#ab-vditor，它本身就是 .vditor 根节点）在 flex 收缩下让位。
    //
    // 两种状态算法不同：
    //   键盘关着：用**文档**纵坐标算「整块正好铺满一屏」。
    //     用 getBoundingClientRect().top 会让高度随滚动变化 →高度变→文档高变，必然抖；
    //     再用视觉视口底部兜一层下限。
    //   键盘开着：只用视觉视口底部（键盘上沿）算，**不加文档坐标上限** ——
    //     此时卡片往往已被滚到视口上方外，上限会把高度卡小，滚动行又掉回键盘底下；
    //     并先把卡片顶到可视区最上方，才能真正「铺满键盘上方区域」。
    //
    // ⚠️ 不要试图用 position:fixed;bottom:键盘高 把行「浮」到键盘上：
    //    它会被祖先 div.body.container 上的 transform（matrix 单位阵也算）
    //    当成包含块，实测 bottom:300px 落到 rect.top=1078，根本没贴在键盘上。
    //    （全屏就是碰到同一个坑才把 wrap 挂到 body 上的，见 _abToggleFullscreen）
    //
    // 重算只由 resize / 焦点 / 键盘状态触发，**不监听 visualViewport 的 scroll**：
    // iOS 为了露出光标会频繁 pan，跟着算就会边滚边伸缩。
    function _abSyncDockFit() {
        var wrap = document.getElementById('ab-vditor-wrap');
        if (!wrap) return;

        // 全屏时 wrap 已经是 height:100dvh 的 flex 列，不需要也不应该再定高
        if (window.innerWidth > 575 || !wrap.classList.contains('ab-toolbar-docked') || wrap.classList.contains('ab-fullscreen')) {
            wrap.classList.remove('ab-dock-fit');
            wrap.style.removeProperty('--ab-dock-avail');
            _abKbRestoreScroll();
            return;
        }

        var scroller = wrap.querySelector('.ab-toolbar-scroller');
        var modeBar = document.getElementById('ab-vditor-mode-bar');
        var barH = modeBar ? modeBar.offsetHeight : 52;
        var rowH = scroller ? scroller.offsetHeight : 57;

        var vv = window.visualViewport;
        var vvTop = vv ? vv.offsetTop : 0;
        var vvBottom = vv ? (vv.offsetTop + vv.height) : window.innerHeight;
        var kbOpen = _abIsKeyboardOpen();

        var rect = wrap.getBoundingClientRect();
        var avail;
        if (kbOpen) {
            // 先贴顶，再按新位置算高度（否则算出来的还是没贴顶时的旧值）
            _abKbLiftIntoView(rect.top - vvTop);
            rect = wrap.getBoundingClientRect();
            avail = vvBottom - rect.top - 8;
        } else {
            _abKbRestoreScroll();
            rect = wrap.getBoundingClientRect();     // 还原滚动后重新量
            var scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
            avail = window.innerHeight - (rect.top + scrollY) - 8;
            avail = Math.min(avail, window.innerHeight - 8, vvBottom - rect.top - 8);
        }

        // 键盘开着时编辑区可以压得更矮（优先保证滚动行在键盘上头）；
        // 键盘关着时给它留 160px，再挤就没法写字了。
        var floor = barH + rowH + (kbOpen ? 90 : 160);
        avail = Math.round(Math.max(floor, avail));
        wrap.style.setProperty('--ab-dock-avail', avail + 'px');
        wrap.classList.add('ab-dock-fit');
    }

    // ── 移动端：停靠位置跟着「窄/宽」档位与软键盘走 ─────────────────────────
    function _abWatchMobileDock(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;
        var wrap = container.parentNode;
        if (!wrap || wrap.id !== 'ab-vditor-wrap') return;   // 只处理 AB 自己的编辑器
        var scroller = _abToolbarScroller(container);
        var toolbar = container.querySelector('.vditor-toolbar');
        if (!scroller || scroller === toolbar) return;       // 两行结构还没建

        var lastNarrow = null;
        var sync = function() {
            var narrow = window.innerWidth <= 575;
            // 只在「窄/宽」档位变化时重建结构（重建会动 DOM，没必要每次 resize 都做）
            if (narrow !== lastNarrow) {
                lastNarrow = narrow;
                _abStructureToolbar(containerId);
                _abWatchToolbarScroll(containerId);
            }
            _abSyncDockFit();
        };

        if (scroller._abDockSync) {          // 幂等：结构重建后只需重算
            scroller._abDockSync = sync;
            sync();
            return;
        }
        scroller._abDockSync = sync;

        var vv = window.visualViewport;
        if (vv) vv.addEventListener('resize', sync);
        window.addEventListener('resize', sync);
        window.addEventListener('orientationchange', function() { setTimeout(sync, 60); });
        // 有的浏览器弹键盘不派发 visualViewport 事件，补一层焦点兜底
        container.addEventListener('focusin', function() { setTimeout(sync, 250); });
        container.addEventListener('focusout', function() { setTimeout(sync, 250); });
        sync();
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

    function _abBuildVditorLoadingStage() {
        return $(
            '<div id="ab-vditor-loading" class="ab-vditor-loading">' +
                '<div class="ab-vditor-loading__pulse"></div>' +
                '<div class="ab-vditor-loading__title">Vditor 正在初始化</div>' +
                '<div class="ab-vditor-loading__meta">加载本地资源与工具栏扩展中...</div>' +
                '<div class="ab-vditor-loading__skeleton">' +
                    '<span></span><span></span><span></span><span></span>' +
                '</div>' +
            '</div>'
        );
    }

    function _abHideVditorLoadingStage() {
        var el = document.getElementById('ab-vditor-loading');
        if (!el) return;
        el.classList.add('ab-vditor-loading--leave');
        setTimeout(function() {
            if (el && el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }, 240);
    }

    function _abGetVditorRuntimeVersion(vd) {
        if (vd && vd.version) return String(vd.version);
        if (window.__abVditor && window.__abVditor.version) {
            return String(window.__abVditor.version);
        }
        // 兜底：实例还没建好时显示内置 Vditor 的版本
        return '4.0.0';
    }

    function _abSetAboutField(modal, key, value) {
        if (!modal) return;
        var el = modal.querySelector('[data-ab-about="' + key + '"]');
        if (el) el.textContent = String(value || '-');
    }

    function _abCloseVditorAboutModal() {
        var modal = document.getElementById('ab-vditor-about-modal');
        if (!modal) return;
        modal.classList.remove('ab-show');
        document.body.classList.remove('ab-vditor-about-open');
        if (modal._abEscHandler) {
            document.removeEventListener('keydown', modal._abEscHandler);
        }
    }

    function _abCloseLegacyInsertTextModal() {
        var modal = document.getElementById('ab-vditor-insert-text-modal');
        if (!modal) return;
        modal.classList.remove('ab-show');
        document.body.classList.remove('ab-vditor-insert-text-open');
        if (modal._abEscHandler) {
            document.removeEventListener('keydown', modal._abEscHandler);
        }
    }

    function _abCopyPlainTextToClipboard(text, done) {
        var finish = (typeof done === 'function') ? done : function() {};
        var plain = String(text || '');
        if (!plain) {
            finish(false);
            return;
        }

        var fallbackCopy = function() {
            var helper = document.createElement('textarea');
            helper.value = plain;
            helper.setAttribute('readonly', 'readonly');
            helper.style.position = 'fixed';
            helper.style.left = '-99999px';
            helper.style.top = '0';
            document.body.appendChild(helper);
            helper.select();

            var ok = false;
            try {
                ok = document.execCommand('copy');
            } catch (e) {
                ok = false;
            }

            if (helper.parentNode) {
                helper.parentNode.removeChild(helper);
            }
            finish(!!ok);
        };

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(plain).then(function() {
                finish(true);
            }).catch(function() {
                fallbackCopy();
            });
            return;
        }

        fallbackCopy();
    }

    function _abOpenLegacyInsertTextModal(text, tipText) {
        var content = String(text || '');
        if (!content) return;

        var modal = document.getElementById('ab-vditor-insert-text-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'ab-vditor-insert-text-modal';
            modal.className = 'ab-vditor-insert-text';
            modal.innerHTML =
                '<div class="ab-vditor-insert-text__mask" data-close="1"></div>' +
                '<div class="ab-vditor-insert-text__dialog" role="dialog" aria-modal="true" aria-label="扩展按钮文本提示">' +
                    '<div class="ab-vditor-insert-text__head">' +
                        '<h3>扩展按钮文本提示</h3>' +
                        '<button type="button" class="ab-vditor-insert-text__close" aria-label="关闭">×</button>' +
                    '</div>' +
                    '<div class="ab-vditor-insert-text__content">' +
                        '<p class="ab-vditor-insert-text__desc">检测到该扩展按钮将插入以下文本。请复制后选择需要插入文本的位置。</p>' +
                        '<p class="ab-vditor-insert-text__from" data-ab-insert-from></p>' +
                        '<textarea class="ab-vditor-insert-text__editor" data-ab-insert-text readonly></textarea>' +
                        '<div class="ab-vditor-insert-text__actions">' +
                            '<span class="ab-vditor-insert-text__status" data-ab-insert-status></span>' +
                            '<button type="button" class="ab-vditor-insert-text__btn" data-action="copy">复制文本</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            modal.addEventListener('click', function(ev) {
                var target = ev.target;
                if (!target) return;

                if (target.getAttribute('data-close') === '1' || target.classList.contains('ab-vditor-insert-text__close')) {
                    _abCloseLegacyInsertTextModal();
                    return;
                }

                var action = target.getAttribute('data-action');
                if (!action) return;

                var textEl = modal.querySelector('[data-ab-insert-text]');
                var statusEl = modal.querySelector('[data-ab-insert-status]');
                var currentText = textEl ? String(textEl.value || '') : '';

                if (action === 'copy') {
                    _abCopyPlainTextToClipboard(currentText, function(ok) {
                        if (!statusEl) return;
                        statusEl.textContent = ok ? '已复制，可到任意位置粘贴。' : '复制失败，请手动选择文本复制。';
                    });
                }
            });

            modal._abEscHandler = function(ev) {
                if (ev.key === 'Escape') {
                    _abCloseLegacyInsertTextModal();
                }
            };

            document.body.appendChild(modal);
        }

        var textEl = modal.querySelector('[data-ab-insert-text]');
        var fromEl = modal.querySelector('[data-ab-insert-from]');
        var statusEl = modal.querySelector('[data-ab-insert-status]');
        if (textEl) {
            textEl.value = content;
            try {
                textEl.scrollTop = 0;
                textEl.setSelectionRange(0, 0);
            } catch (e) {}
        }
        if (fromEl) {
            var fromText = _abNormalizeTipText(tipText || '');
            fromEl.textContent = fromText ? ('来源：' + fromText) : '来源：扩展工具栏';
        }
        if (statusEl) statusEl.textContent = '';

        modal.classList.add('ab-show');
        document.body.classList.add('ab-vditor-insert-text-open');
        document.addEventListener('keydown', modal._abEscHandler);
    }

    // ── 「关于 AB Vditor」的内置组件清单 ─────────────────────────────────
    // 表里的 version 全部取自**随包文件自身**（banner 注释 / 文件内常量），不是猜的。
    // ⚠️ 升级 Vditor 时必须同步刷新这张表，依据如下：
    //     Vditor            dist/index.min.js        window.Vditor.version
    //     Lute              js/lute/lute.min.js      {k:"Version",v:new $String("1.7.6")}
    //     highlight.js      .../highlight.min.js     banner "… v11.7.0"
    //     KaTeX             .../katex.min.js         version:"0.16.9"
    //     MathJax           .../tex-svg-full.js      MathJax={version:"3.1.2"}
    //     Mermaid           .../mermaid.min.js       version:"11.16.1"
    //     ECharts           .../echarts.min.js       exports.version="5.5.1"
    //     abcjs             .../abcjs_basic.min.js   banner "abcjs_basic v5.10.3"
    //     markmap           .../markmap.min.js       banner "v6.7.0"
    //     WaveDrom          .../wavedrom.min.js      3.6.2（文件内常量）
    //     viz.js            .../graphviz/viz.js      banner "Viz.js 2.1.2 (Graphviz 2.40.1 …)"
    //     flowchart.js      .../flowchart.min.js     banner "// flowchart.js, v1.14.1"
    //     SmilesDrawer      .../smiles-drawer.min.js SmilesDrawer={Version:"1.0.0"}
    //     plantuml-encoder  .../plantuml-encoder.min.js  文件里没有版本号 → 只显示包名
    // global：运行时的 window 变量名 —— 有它才能判断「本次是否已按需加载」，
    //         已加载时优先显示库里自报的版本（比表里的随包版本更准）
    var _abVditorComponents = [
        { name: 'Vditor', desc: '编辑器内核（含工具栏 / 模式 / 上传）', icon: 'edit_note', file: 'dist/index.min.js', global: 'Vditor', ver: '', fromInstance: true },
        { name: 'Lute', desc: 'Markdown 解析与渲染引擎（Go 编译为 JS）', icon: 'terminal', file: 'js/lute/lute.min.js', global: 'Lute', ver: '1.7.6' },
        { name: 'highlight.js', desc: '代码块语法高亮', icon: 'code', file: 'js/highlight.js/highlight.min.js', global: 'hljs', ver: '11.7.0' },
        { name: 'KaTeX', desc: 'LaTeX 数学公式（快速渲染）', icon: 'functions', file: 'js/katex/katex.min.js', global: 'katex', ver: '0.16.9' },
        { name: 'MathJax', desc: '数学公式（SVG 全量包）', icon: 'calculate', file: 'js/mathjax/tex-svg-full.js', global: 'MathJax', ver: '3.1.2' },
        { name: 'Mermaid', desc: '流程图 / 时序图 / 甘特图等', icon: 'account_tree', file: 'js/mermaid/mermaid.min.js', global: 'mermaid', ver: '11.16.1' },
        { name: 'ECharts', desc: '数据图表', icon: 'bar_chart', file: 'js/echarts/echarts.min.js', global: 'echarts', ver: '5.5.1' },
        { name: 'abcjs', desc: '五线谱（ABC 记谱）', icon: 'music_note', file: 'js/abcjs/abcjs_basic.min.js', global: 'ABCJS', ver: '5.10.3' },
        { name: 'markmap', desc: '思维导图', icon: 'hub', file: 'js/markmap/markmap.min.js', global: 'markmap', ver: '6.7.0' },
        { name: 'WaveDrom', desc: '数字波形图', icon: 'waves', file: 'js/wavedrom/wavedrom.min.js', global: 'WaveDrom', ver: '3.6.2' },
        { name: 'viz.js', desc: 'Graphviz DOT 图（内含 Graphviz 2.40.1）', icon: 'polyline', file: 'js/graphviz/viz.js', global: 'Viz', ver: '2.1.2' },
        { name: 'flowchart.js', desc: '流程图（内含 Raphael）', icon: 'schema', file: 'js/flowchart.js/flowchart.min.js', global: 'flowchart', ver: '1.14.1' },
        { name: 'SmilesDrawer', desc: '化学结构式', icon: 'science', file: 'js/smiles-drawer/smiles-drawer.min.js', global: 'SmilesDrawer', ver: '1.0.0' },
        { name: 'plantuml-encoder', desc: 'PlantUML 图表编码器', icon: 'description', file: 'js/plantuml/plantuml-encoder.min.js', global: 'plantumlEncoder', ver: '' }
    ];

    // 随包资源：这些是 Vditor 的静态资源，本身没有版本号，用数量表达
    var _abVditorPackAssets = [
        { name: '图标集', desc: 'material / ant 两套内置图标', icon: 'interests', file: 'js/icons', badge: '2 套' },
        { name: '界面语言', desc: 'i18n 语言包', icon: 'translate', file: 'js/i18n', badge: '12 种' },
        { name: '内容主题', desc: '预览区配色：ant-design / dark / light / wechat', icon: 'palette', file: 'css/content-theme', badge: '4 套' },
        { name: '内置表情', desc: '图片表情资源', icon: 'mood', file: 'images/emoji', badge: '17 个' }
    ];

    function _abReadAboutLibRuntime(entry) {
        var out = { loaded: false, ver: '' };
        if (!entry || !entry.global) return out;
        // Vditor 的版本号在实例上（window.Vditor 上没有 version），单独取一次
        if (entry.fromInstance) {
            var inst = window.__abVditor;
            if (inst || window[entry.global]) {
                out.loaded = true;
                out.ver = inst ? _abGetVditorRuntimeVersion(inst) : '';
            }
            return out;
        }
        var obj = null;
        try { obj = window[entry.global]; } catch (e) { obj = null; }
        if (!obj) return out;
        out.loaded = true;
        var fields = ['version', 'Version', 'versionString'];
        for (var i = 0; i < fields.length; i++) {
            if (obj[fields[i]]) { out.ver = String(obj[fields[i]]); break; }
        }
        return out;
    }

    function _abBuildAboutRow(entry, isAsset) {
        var rt = _abReadAboutLibRuntime(entry);
        var chip = isAsset ? (entry.badge || '随包') : (rt.ver || entry.ver || '随包');
        var state = isAsset ? '' : (rt.loaded ? '已加载' : '按需加载');
        var tip = entry.file + (state ? ' · ' + state : '');
        return '<li class="ab-vditor-about__item' + (rt.loaded ? ' is-loaded' : '') + '" title="' + tip + '">' +
            '<span class="ab-vditor-about__item-icon ab-icon" aria-hidden="true">' + entry.icon + '</span>' +
            '<span class="ab-vditor-about__item-main">' +
                '<span class="ab-vditor-about__item-name">' + entry.name + '</span>' +
                '<span class="ab-vditor-about__item-desc">' + entry.desc + '</span>' +
            '</span>' +
            '<span class="ab-vditor-about__item-chip" title="' + tip + '">' + chip + '</span>' +
        '</li>';
    }

    function _abRenderAboutComponents(modal) {
        if (!modal) return;
        var html = '';
        var i, loaded = 0;
        for (i = 0; i < _abVditorComponents.length; i++) {
            html += _abBuildAboutRow(_abVditorComponents[i], false);
            if (_abReadAboutLibRuntime(_abVditorComponents[i]).loaded) loaded++;
        }
        var libs = modal.querySelector('[data-ab-about="components"]');
        if (libs) libs.innerHTML = html;

        html = '';
        for (i = 0; i < _abVditorPackAssets.length; i++) {
            html += _abBuildAboutRow(_abVditorPackAssets[i], true);
        }
        var assets = modal.querySelector('[data-ab-about="assets"]');
        if (assets) assets.innerHTML = html;

        var meta = modal.querySelector('[data-ab-about="lib-count"]');
        if (meta) meta.textContent = _abVditorComponents.length + ' 项 · 已加载 ' + loaded;
    }

    function _abOpenVditorAboutModal(vd) {
        var modal = document.getElementById('ab-vditor-about-modal');
        // 结构变了就重建（旧标记的缓存节点直接丢掉）
        if (modal && !modal.querySelector('[data-ab-about="components"]')) {
            if (modal.parentNode) {
                modal.parentNode.removeChild(modal);
            }
            modal = null;
        }

        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'ab-vditor-about-modal';
            modal.className = 'ab-vditor-about';
            modal.innerHTML =
                '<div class="ab-vditor-about__mask" data-close="1"></div>' +
                '<div class="ab-vditor-about__dialog" role="dialog" aria-modal="true" aria-labelledby="ab-vditor-about-title">' +
                    '<div class="ab-vditor-about__head">' +
                        '<span class="ab-vditor-about__head-icon ab-icon" aria-hidden="true">info</span>' +
                        '<div class="ab-vditor-about__head-text">' +
                            '<h3 id="ab-vditor-about-title">关于 AB Vditor</h3>' +
                            '<p>AdminBeautify 内置 Markdown 编辑器适配</p>' +
                        '</div>' +
                        '<button type="button" class="ab-vditor-about__close" aria-label="关闭">' +
                            '<span class="ab-icon" aria-hidden="true">close</span>' +
                        '</button>' +
                    '</div>' +
                    '<div class="ab-vditor-about__content">' +
                        '<div class="ab-vditor-about__hero">' +
                            '<div class="ab-vditor-about__hero-card">' +
                                '<span class="ab-vditor-about__hero-label">Vditor</span>' +
                                '<strong class="ab-vditor-about__hero-value" data-ab-about="vditor">-</strong>' +
                            '</div>' +
                            '<div class="ab-vditor-about__hero-card">' +
                                '<span class="ab-vditor-about__hero-label">AB 适配器</span>' +
                                '<strong class="ab-vditor-about__hero-value" data-ab-about="adapter">-</strong>' +
                            '</div>' +
                        '</div>' +
                        '<section class="ab-vditor-about__section">' +
                            '<div class="ab-vditor-about__section-head">' +
                                '<h4>渲染组件</h4>' +
                                '<span class="ab-vditor-about__section-meta" data-ab-about="lib-count"></span>' +
                            '</div>' +
                            '<p class="ab-vditor-about__section-hint">版本号取自随包文件；带圆点的表示本次已按需加载。</p>' +
                            '<ul class="ab-vditor-about__list" data-ab-about="components"></ul>' +
                        '</section>' +
                        '<section class="ab-vditor-about__section">' +
                            '<div class="ab-vditor-about__section-head">' +
                                '<h4>随包资源</h4>' +
                            '</div>' +
                            '<ul class="ab-vditor-about__list" data-ab-about="assets"></ul>' +
                        '</section>' +
                        '<div class="ab-vditor-about__links">' +
                            '<a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify" target="_blank" rel="noopener noreferrer">' +
                                '<span class="ab-icon" aria-hidden="true">widgets</span>AdminBeautify</a>' +
                            '<a href="https://github.com/Vanessa219/vditor" target="_blank" rel="noopener noreferrer">' +
                                '<span class="ab-icon" aria-hidden="true">code</span>Vditor</a>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            modal.addEventListener('click', function(ev) {
                if (ev.target && ev.target.getAttribute('data-close') === '1') {
                    _abCloseVditorAboutModal();
                }
                if (ev.target && ev.target.closest && ev.target.closest('.ab-vditor-about__close')) {
                    _abCloseVditorAboutModal();
                }
            });

            modal._abEscHandler = function(ev) {
                if (ev.key === 'Escape') {
                    _abCloseVditorAboutModal();
                }
            };

            document.body.appendChild(modal);
        }

        var versionVditor = _abGetVditorRuntimeVersion(vd);
        _abSetAboutField(modal, 'vditor', versionVditor);
        _abSetAboutField(modal, 'adapter', AB_VDITOR_ADAPTER_VERSION);
        _abRenderAboutComponents(modal);

        modal.classList.add('ab-show');
        document.body.classList.add('ab-vditor-about-open');
        document.addEventListener('keydown', modal._abEscHandler);
    }

    function _abGetUploadParentCid() {
        var cidInput = document.querySelector('input[name="cid"]');
        if (!cidInput) return 0;
        var cid = parseInt(cidInput.value, 10);
        return isNaN(cid) ? 0 : Math.max(0, cid);
    }

    function _abNormalizeUploadAccept(raw) {
        if (!raw) return '';
        if (Array.isArray(raw)) {
            return raw.join(',');
        }
        return String(raw);
    }

    function _abBuildUploadUrl() {
        var ajaxCfg = window.__AB_AJAX__ || {};
        var url = cfg.vditorUploadUrl || '';
        if (!url) {
            var base = ajaxCfg.url || cfg.ajaxUrl || '';
            if (!base) return '';
            url = base + (base.indexOf('?') >= 0 ? '&' : '?') + 'do=upload-media';
        }

        var token = ajaxCfg.token || cfg.ajaxToken || '';
        if (token && !/[?&]_=/i.test(url)) {
            url += (url.indexOf('?') >= 0 ? '&' : '?') + '_=' + encodeURIComponent(token);
        }
        return url;
    }

    function _abBuildUploadFormatResponse(responseText) {
        var out = {
            code: 0,
            msg: '',
            data: { errFiles: [], succMap: {} }
        };

        var parsed;
        try {
            parsed = JSON.parse(responseText || '{}');
        } catch (e) {
            return JSON.stringify({
                code: 1,
                msg: '上传响应解析失败',
                data: { errFiles: [], succMap: {} }
            });
        }

        if (!parsed || parsed.code !== 0 || !parsed.data) {
            return JSON.stringify({
                code: 1,
                msg: (parsed && parsed.message) ? String(parsed.message) : '上传失败',
                data: { errFiles: [], succMap: {} }
            });
        }

        var uploaded = parsed.data.uploaded;
        if (Array.isArray(uploaded)) {
            uploaded.forEach(function(item, idx) {
                if (!item || !item.url) return;
                var name = item.name ? String(item.name) : ('file-' + idx);
                out.data.succMap[name] = String(item.url);
            });
        }

        var failed = parsed.data.failed;
        if (Array.isArray(failed)) {
            failed.forEach(function(item) {
                var text = String(item || '').trim();
                if (!text) return;
                var pos = text.indexOf(':');
                out.data.errFiles.push(pos > 0 ? text.slice(0, pos).trim() : text);
            });
        }

        if (Object.keys(out.data.succMap).length === 0 && out.data.errFiles.length > 0) {
            out.code = 1;
            out.msg = parsed.message ? String(parsed.message) : '上传失败';
        }

        return JSON.stringify(out);
    }

    function _abBuildVditorUploadConfig() {
        var uploadMax = parseInt(cfg.uploadMaxBytes, 10);
        if (isNaN(uploadMax) || uploadMax <= 0) {
            uploadMax = 10 * 1024 * 1024;
        }

        var uploadCfg = {
            url: _abBuildUploadUrl(),
            fieldName: 'files[]',
            max: uploadMax,
            multiple: true,
            withCredentials: true,
            extraData: {
                parent: _abGetUploadParentCid()
            },
            format: function(files, responseText) {
                return _abBuildUploadFormatResponse(responseText);
            }
        };

        uploadCfg.validate = function(files) {
            uploadCfg.extraData.parent = _abGetUploadParentCid();
        };

        var accept = _abNormalizeUploadAccept(cfg.uploadAccept);
        if (accept) {
            uploadCfg.accept = accept;
        }

        return uploadCfg;
    }

    // ── 动态加载本地 Vditor 脚本（仅在没有其他插件提供时才加载）────────────────
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
        s.onerror = function() { console.error('[AB] Vditor 本地脚本加载失败'); };
        document.head.appendChild(s);
    }

    // ── 构建模式切换栏（MD3 Segmented Button 样式，通过 CSS 类控制激活态）──
    // ── 模式栏图标（Material Icons Round 连字名；已实测内置 MDIR 子集字体含这三个字形）──
    var _abModeIconMap = {wysiwyg: 'wysiwyg', ir: 'visibility', sv: 'vertical_split'};

    function _abBuildModeBar(activeMode, onModeChange) {
        var modeLabels = {wysiwyg:'所见即所得', ir:'实时预览', sv:'分屏编辑'};
        var modes = ['wysiwyg', 'ir', 'sv'];

        var $bar = $('<div id="ab-vditor-mode-bar"></div>');
        // 胶囊按钮组
        var $group = $('<div class="ab-mode-btn-group"></div>');

        modes.forEach(function(m) {
            // 文字与图标都渲染：桌面端 CSS 只显示文字，≤520px 只显示图标。
            // 图标态下按钮里没有可读文本，所以必须挂 title / aria-label。
            var $btn = $('<button type="button"></button>')
                .attr('data-vmode', m)
                .attr('title', modeLabels[m])
                .attr('aria-label', modeLabels[m])
                .addClass('ab-mode-btn' + (m === activeMode ? ' ab-mode-active' : ''))
                .on('click', function() {
                    $group.find('.ab-mode-btn').removeClass('ab-mode-active');
                    $(this).addClass('ab-mode-active');
                    onModeChange(m);
                    try { localStorage.setItem('ab-vditor-mode', m); } catch(e) {}
                });
            $('<span class="ab-icon ab-mode-icon"></span>').text(_abModeIconMap[m]).appendTo($btn);
            $('<span class="ab-mode-label"></span>').text(modeLabels[m]).appendTo($btn);
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
            _abSyncDockFit();   // 全屏自带 100dvh，清掉停靠定高（见 C2/D1 里的 :not(.ab-fullscreen)）
            // 全屏下滚动行回到工具栏顶部两行里（_abStructureToolbar 会因 ab-fullscreen 不再停靠）
            _abStructureToolbar('ab-vditor');
            _abWatchToolbarScroll('ab-vditor');
            if (icon) icon.textContent = 'fullscreen_exit';
            _abRefreshToolbarTooltips('ab-vditor');
            _abRefreshToolbarTooltips('vditor');

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
            _abRefreshToolbarTooltips('ab-vditor');
            _abRefreshToolbarTooltips('vditor');

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
            // 回到普通布局：先重建两行结构（重新停靠到底部），再按新视口算停靠高度
            _abStructureToolbar('ab-vditor');
            _abWatchToolbarScroll('ab-vditor');
            _abRefreshToolbarTooltips('ab-vditor');
            _abSyncDockFit();
        }
    }

    // ── 为已有 Vditor（如 Mirages 的 #vditor）添加模式栏 ───────────────────
    function _abAddModeBarToExisting(vd, $vditorEl) {
        _abInstallTypechoInsertFileBridge();
        _abHideLegacyEditorBars();
        if ($('#ab-vditor-mode-bar').length) {
            _abSyncToolbarExtras('vditor');
            _abWatchLegacyToolbarExtras('vditor');
            return;
        }
        var savedMode;
        try { savedMode = localStorage.getItem('ab-vditor-mode') || defaultMode; } catch(e) { savedMode = defaultMode; }
        var $bar = _abBuildModeBar(savedMode, function(mode) {
            _abSetVditorMode(window.__abVditor, 'vditor', mode);
        });
        $vditorEl.before($bar);
        window.__abVditor = vd;
        // 注入 Material Icons 图标
        setTimeout(function() {
            _abSyncToolbarExtras('vditor');
            _abApplyVditorIcons('vditor');
            var _tbEl = document.querySelector('#vditor .vditor-toolbar');
            if (_tbEl && !document.getElementById('ab-vditor-about-btn')) {
                var _anchorLi = null;
                var _saveBtn = document.getElementById('ab-vditor-save-btn');
                if (_saveBtn) {
                    _anchorLi = _saveBtn.closest ? _saveBtn.closest('li') : _saveBtn.parentNode;
                    if (_anchorLi && !_tbEl.contains(_anchorLi)) _anchorLi = null;
                }
                if (!_anchorLi) {
                    _anchorLi = _tbEl.querySelector('li:has(button[data-type="redo"])');
                }
                if (_anchorLi) {
                    var _aboutLi = document.createElement('li');
                    _aboutLi.className = 'vditor-toolbar__item';
                    var _aboutBtn = document.createElement('button');
                    _aboutBtn.type = 'button';
                    _aboutBtn.id = 'ab-vditor-about-btn';
                    _aboutBtn.className = 'vditor-tooltipped vditor-tooltipped__n';
                    _aboutBtn.setAttribute('aria-label', '关于 AB Vditor');
                    var _aboutIcon = document.createElement('span');
                    _aboutIcon.className = 'ab-icon';
                    _aboutIcon.textContent = 'info';
                    _aboutBtn.appendChild(_aboutIcon);
                    _aboutLi.appendChild(_aboutBtn);
                    _aboutBtn.addEventListener('click', function() { _abOpenVditorAboutModal(vd); });
                    if (_anchorLi.nextSibling) {
                        _anchorLi.parentNode.insertBefore(_aboutLi, _anchorLi.nextSibling);
                    } else {
                        _anchorLi.parentNode.appendChild(_aboutLi);
                    }
                    _abApplyVditorIcons('vditor');
                }
            }
        }, 300);
        // 监听工具栏 DOM 变化（全屏切换等会覆盖按钮 innerHTML）
        setTimeout(function() {
            _abWatchLegacyToolbarExtras('vditor');
            _abObserveToolbarForIcons('vditor');
            _abWatchToolbarPanelClip('vditor');
            _abKeepWysiwygPopoverOffCaret('vditor');
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
        _abInstallTypechoInsertFileBridge();
        var legacyToolbarExtras = _abCollectLegacyToolbarExtras();
        var externalToolbarExtras = _abReadExternalToolbarExtras();
        var allToolbarExtras = legacyToolbarExtras.concat(externalToolbarExtras);

        // 不删除旧工具栏，避免扩展插件失去事件绑定；仅隐藏并通过桥接按钮触发
        _abHideLegacyEditorBars();
        var $editArea = $('#wmd-editarea');

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
        var $loadingStage = _abBuildVditorLoadingStage();
        $wrap.append($modeBar).append($vditorWrap).append($loadingStage);
        $editArea.before($wrap);

        // 首屏加载态遮罩覆盖编辑器区域（顶部避开模式栏）
        setTimeout(function() {
            var top = $('#ab-vditor-mode-bar').outerHeight() || 52;
            $loadingStage.css('top', top + 'px');
        }, 0);

        // 构建工具栏数组：含 edit-mode（CSS 隐藏，供 _abSetVditorMode 回退点击）及插件工具
        var _toolbarArr = [
            'emoji','headings','bold','italic','strike','|',
            'line','quote','list','ordered-list','check','indent','outdent','|',
            'code','inline-code','insert-before','insert-after','|',
            'upload','link','table','|',
            'undo','redo',
            'edit-mode'
        ];

        if (allToolbarExtras.length) {
            _toolbarArr = _abMergeToolbarExtras(_toolbarArr, allToolbarExtras);
        }

        var vditorReady = false;

        // 高度自适应：原来固定 max(480, 视口高 - 260)，在 667px 高的手机上会算出 480px，
        // 白白撑出多余的页面滚动。窄屏改成按视口比例给（CSS 侧 ≤768px 也把 min-height 降到 300px）。
        var _abVpW = window.innerWidth || $(window).width();
        var _abVpH = window.innerHeight || $(window).height();
        var _abVdHeight = (_abVpW <= 575)
            ? Math.max(300, Math.round(_abVpH * 0.62))
            : Math.max(480, _abVpH - 260);

        var vd = new Vditor('ab-vditor', {
            mode: savedMode,
            height: _abVdHeight,
            theme: isDark ? 'dark' : 'classic',
            lang: 'zh_CN',
            i18n: window.VditorI18n || _abVditorZhI18n,
            icon: '',
            cdn: VDITOR_CDN,
            _lutePath: VDITOR_CDN + '/dist/js/lute/lute.min.js',
            preview: { theme: { current: isDark ? 'dark' : 'light' } },
            upload: _abBuildVditorUploadConfig(),
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
                // 移动端：工具栏横向滚动会裁掉子面板，这里在面板打开时临时解除裁剪
                _abWatchToolbarPanelClip('ab-vditor');
                // 移动端：WYSIWYG 气泡工具栏不得盞住正在输入的光标
                _abKeepWysiwygPopoverOffCaret('ab-vditor');
                // 为浮动面板（vditor-panel）注入 Material Icons 图标
                _abApplyPanelIcons('ab-vditor');
                // 在 redo 按钮右侧插入保存草稿按钮和关于按钮（防重复插入）
                var _tbEl = document.querySelector('#ab-vditor .vditor-toolbar');
                if (_tbEl) {
                    var _saveBtnNode = document.getElementById('ab-vditor-save-btn');
                    var _saveLiNode = _saveBtnNode ? (_saveBtnNode.closest ? _saveBtnNode.closest('li') : _saveBtnNode.parentNode) : null;

                    if (!_saveBtnNode) {
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
                        _saveBtnNode = _saveBtn;
                        _saveLiNode = _saveLi;
                    }

                    if (_saveLiNode && !document.getElementById('ab-vditor-about-btn')) {
                        var _aboutLi = document.createElement('li');
                        _aboutLi.className = 'vditor-toolbar__item';
                        var _aboutBtn = document.createElement('button');
                        _aboutBtn.type = 'button';
                        _aboutBtn.id = 'ab-vditor-about-btn';
                        _aboutBtn.className = 'vditor-tooltipped vditor-tooltipped__n';
                        _aboutBtn.setAttribute('aria-label', '关于 AB Vditor');
                        var _aboutIcon = document.createElement('span');
                        _aboutIcon.className = 'ab-icon';
                        _aboutIcon.textContent = 'info';
                        _aboutBtn.appendChild(_aboutIcon);
                        _aboutLi.appendChild(_aboutBtn);
                        _aboutBtn.addEventListener('click', function() { _abOpenVditorAboutModal(vd); });

                        if (_saveLiNode.nextSibling) {
                            _saveLiNode.parentNode.insertBefore(_aboutLi, _saveLiNode.nextSibling);
                        } else {
                            _saveLiNode.parentNode.appendChild(_aboutLi);
                        }
                    }
                }

                _abApplyVditorIcons('ab-vditor');
                _abSyncToolbarExtras('ab-vditor');
                // 移动端：工具栏第一行停靠在编辑区底部，并跟着软键盘重算高度。
                // ⚠️ 必须放在 _abSyncToolbarExtras() **之后**：要先有 .ab-toolbar-scroller
                //    才能挂上观察（放在 after 开头是个静默失效的坑，曾经踩过）。
                _abWatchMobileDock('ab-vditor');
                _abHideVditorLoadingStage();
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
                    // setTheme 会改写 #ab-vditor 的主题类，停靠在外面的滚动行要跟着走
                    setTimeout(function() {
                        var c = document.getElementById('ab-vditor');
                        _abSyncDockedTheme(c, _abToolbarScroller(c));
                    }, 0);
                }
            });
        });
        themeObserver.observe(document.documentElement, { attributes: true });

        // 表单提交同步
        $textarea.closest('form').on('submit', function() {
            if (window.__abVditor) $textarea.val(window.__abVditor.getValue());
        });

        _abWatchLegacyToolbarExtras('ab-vditor');

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

        _abInstallTypechoInsertFileBridge();

        // 检测 Mirages 等已集成 Vditor 的插件（通过 LocalConst.VDITOR_BASE_URL 判断）
        var hasMiragesVditor = !!(window.LocalConst && window.LocalConst.VDITOR_BASE_URL);

        if (hasMiragesVditor) {
            _abHideLegacyEditorBars();
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
