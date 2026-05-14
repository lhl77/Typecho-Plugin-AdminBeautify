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
    var AB_VDITOR_ADAPTER_VERSION = '2.0.1';

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

    function _abGetVditorSelectionRangeHint() {
        var vd = window.__abVditor;
        if (!vd || !vd.vditor) return null;

        var mode = '';
        try {
            mode = typeof vd.getCurrentMode === 'function' ? vd.getCurrentMode() : (vd.vditor.currentMode || '');
        } catch (e0) {}

        var modeState = vd.vditor[mode];
        var editor = modeState && modeState.element;
        if (!editor || typeof editor.contains !== 'function') return null;

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
            toolbar.insertBefore(divider, undoItem);
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
        var nodes = container.querySelectorAll('.vditor-toolbar button, .vditor-toolbar label, .vditor-toolbar div[data-type]');
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
        var uploads = container.querySelectorAll('.vditor-toolbar [data-type="upload"]');
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
            if (anchorItem && anchorItem.parentNode === toolbar) {
                toolbar.insertBefore(li, anchorItem);
            } else {
                toolbar.appendChild(li);
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
        var legacyToolbarExtras = _abCollectLegacyToolbarExtras();
        var externalToolbarExtras = _abReadExternalToolbarExtras();
        _abEnsureToolbarExtras(containerId, legacyToolbarExtras.concat(externalToolbarExtras));
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
        var btns = container.querySelectorAll('.vditor-toolbar button, .vditor-toolbar label, .vditor-toolbar div[data-type]');
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
            var panels = container.querySelectorAll('.vditor-panel');
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
        Array.prototype.forEach.call(container.querySelectorAll('.vditor-panel'), function(panel) {
            panelObs.observe(panel, { childList: true, subtree: true });
        });

        container._abPanelIconsObserver = panelObs;
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
                _abSyncToolbarExtras(containerId);
                _abRefreshToolbarTooltips(containerId);
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
        return '3.11.2';
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

    function _abOpenVditorAboutModal(vd) {
        var modal = document.getElementById('ab-vditor-about-modal');
        if (modal && (modal.querySelector('[data-ab-about="upload"]') || modal.querySelector('[data-ab-about="plugin"]'))) {
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
                '<div class="ab-vditor-about__dialog" role="dialog" aria-modal="true" aria-label="关于 AB Vditor">' +
                    '<div class="ab-vditor-about__head">' +
                        '<h3>关于 AB Vditor</h3>' +
                        '<button type="button" class="ab-vditor-about__close" aria-label="关闭">×</button>' +
                    '</div>' +
                    '<div class="ab-vditor-about__content">' +
                        '<div class="ab-vditor-about__grid">' +
                            '<div><label>Vditor 版本</label><strong data-ab-about="vditor">-</strong></div>' +
                            '<div><label>AB Vditor 版本</label><strong data-ab-about="adapter">-</strong></div>' +
                        '</div>' +
                        '<div class="ab-vditor-about__libs">' +
                            '<span class="ab-vditor-about__libs-label">相关仓库：</span>' +
                            '<a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify" target="_blank" rel="noopener noreferrer">AdminBeautify</a>' +
                            '<a href="https://github.com/Vanessa219/vditor" target="_blank" rel="noopener noreferrer">Vditor</a>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            modal.addEventListener('click', function(ev) {
                if (ev.target && ev.target.getAttribute('data-close') === '1') {
                    _abCloseVditorAboutModal();
                }
                if (ev.target && ev.target.classList.contains('ab-vditor-about__close')) {
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
                        _tbEl.insertBefore(_aboutLi, _anchorLi.nextSibling);
                    } else {
                        _tbEl.appendChild(_aboutLi);
                    }
                    _abApplyVditorIcons('vditor');
                }
            }
        }, 300);
        // 监听工具栏 DOM 变化（全屏切换等会覆盖按钮 innerHTML）
        setTimeout(function() {
            _abWatchLegacyToolbarExtras('vditor');
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
        var vd = new Vditor('ab-vditor', {
            mode: savedMode,
            height: Math.max(480, $(window).height() - 260),
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
                            _tbEl.insertBefore(_aboutLi, _saveLiNode.nextSibling);
                        } else {
                            _tbEl.appendChild(_aboutLi);
                        }
                    }
                }

                _abApplyVditorIcons('ab-vditor');
                _abSyncToolbarExtras('ab-vditor');
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
