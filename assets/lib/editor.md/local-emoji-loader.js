/**
 * 本地 Emoji 加载器
 * 用于直接从本地文件加载 emoji，而不是通过 proxy
 */

window.__AB_LOCAL_EMOJI_CONFIG = {
    // 本地 emoji 目录
    basePath: (function() {
        var cfg = window.__AB_CONFIG__ || {};
        var base = String(cfg.editorMdAssetBaseUrl || "").replace(/\/+$/, "");

        if (!base) {
            var scripts = document.getElementsByTagName("script");
            for (var i = scripts.length - 1; i >= 0; i--) {
                var src = String(scripts[i].getAttribute("src") || "");
                if (!src) continue;
                if (src.indexOf("/assets/lib/editor.md/") !== -1) {
                    base = src
                        .replace(/\?.*$/, "")
                        .replace(/#.*$/, "")
                        .replace(/\/(editormd_v1\.0\.0|editormd|local-emoji-loader)\.js$/i, "")
                        .replace(/\/plugins\/[^\/]+\/[^\/]+\.js$/i, "")
                        .replace(/\/+$/, "");
                    break;
                }
            }
        }

        if (!base) base = "/usr/plugins/AdminBeautify/assets/lib/editor.md";
        base = base.replace(/\/usr\/plugin\//i, '/usr/plugins/');
        if (/^https?:\/\//i.test(base) && window.location && window.location.protocol === "https:") {
            base = base.replace(/^http:\/\//i, "https://");
        } else if (!/^https?:\/\//i.test(base)) {
            if (base.slice(0, 2) === "//") {
                base = (window.location ? window.location.protocol : "https:") + base;
            } else {
                if (base.charAt(0) !== "/") base = "/" + base;
                if (window.location && window.location.origin) {
                    base = window.location.origin + base;
                }
            }
        }

        return base + "/emojis";
    })(),
    
    // Twemoji 本地加载
    getTwemojiUrl: function(hexSeq) {
        if (!hexSeq) return '';
        var hex = String(hexSeq || '').toLowerCase();
        return this.basePath + "/twemoji/" + hex + ".png";
    },
    
    // Github Emoji 本地加载
    getGithubEmojiUrl: function(emojiName) {
        if (!emojiName) return '';
        return this.basePath + "/github-emoji/" + emojiName + ".png";
    },
    
    // 处理 emoji 加载失败
    handleLocalEmojiError: function(imgEl, emojiName, isGithub) {
        if (!imgEl || !emojiName) return false;
        
        var fallbackText = imgEl.getAttribute('data-ab-fallback-text') || emojiName;
        
        // 如果是 github emoji，尝试显示字符
        if (isGithub) {
            var ghe = (window.__AB_GHE && window.__AB_GHE[emojiName]) ? window.__AB_GHE[emojiName] : '';
            if (ghe) {
                fallbackText = ghe;
            }
        }
        
        // 替换为文字
        if (imgEl.parentNode) {
            imgEl.parentNode.replaceChild(document.createTextNode(fallbackText), imgEl);
        }
        return false;
    }
};

/**
 * 全局错误处理函数
 * 处理本地 emoji 加载失败
 */
window.__abHandleLocalEmojiError = function(imgEl, emojiName, type) {
    if (!imgEl || !emojiName) return false;
    return window.__AB_LOCAL_EMOJI_CONFIG.handleLocalEmojiError(imgEl, emojiName, type === 'github');
};
