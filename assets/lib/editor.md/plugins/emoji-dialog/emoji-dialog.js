/*!
 * Emoji dialog plugin for Editor.md
 *
 * @file        emoji-dialog.js
 * @author      pandao
 * @version     1.2.0
 * @updateTime  2015-03-08
 * {@link       https://github.com/pandao/editor.md}
 * @license     MIT
 */

(function() {

	var factory = function (exports) {

		var $             = jQuery;
		var pluginName    = "emoji-dialog";
		var emojiTabIndex = 0;
		var emojiData     = [];
        var selecteds     = [];
        var cfg           = window.__AB_CONFIG__ || {};

		var detectAssetBaseUrl = function() {
			var fromCfg = String(cfg.editorMdAssetBaseUrl || "").replace(/\/+$/, "");
			if (fromCfg) return fromCfg;

			var scripts = document.getElementsByTagName("script");
			for (var i = scripts.length - 1; i >= 0; i--) {
				var src = String(scripts[i].getAttribute("src") || "");
				if (!src) continue;
				if (src.indexOf("/assets/lib/editor.md/") !== -1) {
					return src
						.replace(/\?.*$/, "")
						.replace(/#.*$/, "")
						.replace(/\/(editormd_v1\.0\.0|editormd|local-emoji-loader)\.js$/i, "")
						.replace(/\/plugins\/[^\/]+\/[^\/]+\.js$/i, "")
						.replace(/\/+$/, "");
				}
			}

			return "/usr/plugins/AdminBeautify/assets/lib/editor.md";
		};

		var normalizeAssetBaseUrl = function() {
			var base = detectAssetBaseUrl();
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
			return base;
		};

		var toUnicodeFromHexSequence = function(hexSeq) {
			var text = String(hexSeq || "").toLowerCase();
			if (!text || !String.fromCodePoint) {
				return "";
			}

			var parts = text.split("-");
			var cps = [];

			for (var i = 0; i < parts.length; i++) {
				var n = parseInt(parts[i], 16);
				if (!isNaN(n)) {
					cps.push(n);
				}
			}

			if (!cps.length) {
				return "";
			}

			try {
				return String.fromCodePoint.apply(String, cps);
			} catch (e) {
				return "";
			}
		};

		var getLocalEmojiBasePath = function() {
			return normalizeAssetBaseUrl() + "/emojis";
		};

		var getLocalEmojiSrc = function(hexSeq, type) {
			// 使用本地文件而不是代理
			type = type || "twemoji";
			var basePath = getLocalEmojiBasePath();
			var hex = String(hexSeq || "").toLowerCase();
			
			if (type === "github") {
				// Github emoji 已经是名称
				return basePath + "/github-emoji/" + encodeURIComponent(hex) + ".png";
			}
			
			// Twemoji 使用 hex 码
			return basePath + "/twemoji/" + encodeURIComponent(hex) + ".png";
		};

		var logoPrefix    = "editormd-logo";
		var logos         = [
			logoPrefix,
			logoPrefix + "-1x",
			logoPrefix + "-2x",
			logoPrefix + "-3x",
			logoPrefix + "-4x",
			logoPrefix + "-5x",
			logoPrefix + "-6x",
			logoPrefix + "-7x",
			logoPrefix + "-8x"
		];

		var langs = {
			"zh-cn" : {
				toolbar : {
					emoji : "Emoji 表情"
				},
				dialog : {
					emoji : {
						title : "Emoji 表情"
					}
				}
			},
			"zh-tw" : {
				toolbar : {
					emoji : "Emoji 表情"
				},
				dialog : {
					emoji : {
						title : "Emoji 表情"
					}
				}
			},
			"en" : {
				toolbar : {
					emoji : "Emoji"
				},
				dialog : {
					emoji : {
						title : "Emoji"
					}
				}
			}
		};

		exports.fn.emojiDialog = function() {
			var _this       = this;
			var cm          = this.cm;
			var settings    = _this.settings;
            
            if (!settings.emoji)
            {
                alert("settings.emoji == false");
                return ;
            }
            
			var path        = settings.pluginPath + pluginName + "/";
			var editor      = this.editor;
			var cursor      = cm.getCursor();
			var selection   = cm.getSelection();
			var classPrefix = this.classPrefix;

			$.extend(true, this.lang, langs[this.lang.name]);
			this.setToolbar();

			var lang        = this.lang;
			var dialogName  = classPrefix + pluginName, dialog;
			var dialogLang  = lang.dialog.emoji;
			
			var dialogContent = [
				"<div class=\"" + classPrefix + "emoji-dialog-box\" style=\"width: 760px;height: 334px;margin-bottom: 8px;overflow: hidden;\">",
				"<div class=\"" + classPrefix + "tab\"></div>",
				"</div>",
			].join("\n");

			cm.focus();

			if (editor.find("." + dialogName).length > 0) 
			{
                dialog = editor.find("." + dialogName);

				selecteds = [];
				dialog.find("a").removeClass("selected");

				this.dialogShowMask(dialog);
				this.dialogLockScreen();
				dialog.show();
			} 
			else
			{
				dialog = this.createDialog({
					name       : dialogName,
					title      : dialogLang.title,
					width      : 800,
					height     : 475,
					mask       : settings.dialogShowMask,
					drag       : settings.dialogDraggable,
					content    : dialogContent,
					lockScreen : settings.dialogLockScreen,
					maskStyle  : {
						opacity         : settings.dialogMaskOpacity,
						backgroundColor : settings.dialogMaskBgColor
					},
					buttons    : {
						enter  : [lang.buttons.enter, function() {							
							cm.replaceSelection(selecteds.join(" "));
							this.hide().lockScreen(false).hideMask();
							
							return false;
						}],
						cancel : [lang.buttons.cancel, function() {                           
							this.hide().lockScreen(false).hideMask();
							
							return false;
						}]
					}
				});
			}
			
			var category = ["Github emoji", "Twemoji", "Font awesome", "Editor.md logo"];
			var tab      = dialog.find("." + classPrefix + "tab");

			if (tab.html() === "") 
			{
				var head = "<ul class=\"" + classPrefix + "tab-head\">";

				for (var i = 0; i<4; i++) {
					var active = (i === 0) ? " class=\"active\"" : "";
					head += "<li" + active + "><a href=\"javascript:;\">" + category[i] + "</a></li>";
				}

				head += "</ul>";

				tab.append(head);

				var container = "<div class=\"" + classPrefix + "tab-container\">";

				for (var x = 0; x < 4; x++) 
                {
					var display = (x === 0) ? "" : "display:none;";
					container += "<div class=\"" + classPrefix + "tab-box\" style=\"height: 260px;overflow: hidden;overflow-y: auto;" + display + "\"></div>";
				}

				container += "</div>";

				tab.append(container);  
			}
            
			var tabBoxs = tab.find("." + classPrefix + "tab-box");
            var emojiCategories = ["github-emoji", "twemoji", "font-awesome", logoPrefix];

			var drawTable = function() {
                var cname = emojiCategories[emojiTabIndex];
				var $data = emojiData[cname];
                var $tab  = tabBoxs.eq(emojiTabIndex);

				if ($tab.html() !== "") {
                    //console.log("break =>", cname);
                    return ;
                }
                
                var pagination = function(data, type) {
                    var rowNumber = (type === "editormd-logo") ? "5" : 20;
                    var pageTotal = Math.ceil(data.length / rowNumber);
                    var table     = "<div class=\"" + classPrefix + "grid-table\">";

                    for (var i = 0; i < pageTotal; i++)
                    {
                        var row = "<div class=\"" + classPrefix + "grid-table-row\">";

                        for (var x = 0; x < rowNumber; x++)
                        {
                            var emoji = $.trim(data[(i * rowNumber) + x]);
                            
                            if (typeof emoji !== "undefined" && emoji !== "")
                            {
                                var img = "", icon = "";
                                
                                if (type === "github-emoji")
                                {
									var gheHex = (window.__AB_GHE_HEX && window.__AB_GHE_HEX[emoji]) ? window.__AB_GHE_HEX[emoji] : "";
									var ghe = (window.__AB_GHE && window.__AB_GHE[emoji]) ? window.__AB_GHE[emoji] : "";
									var gheFallback = ghe || (":" + emoji + ":");
									img = "<img class=\"ab-emoji-img\" src=\"" + getLocalEmojiSrc(emoji, 'github') + "\" alt=\"" + emoji + "\" loading=\"lazy\" decoding=\"async\" referrerpolicy=\"no-referrer\" data-ab-emoji-name=\"" + emoji + "\" data-ab-fallback-text=\"" + gheFallback + "\" onerror=\"if(this.parentNode){var txt=this.getAttribute('data-ab-fallback-text')||(':' + this.getAttribute('alt') + ':');var span=document.createElement('span');span.className='ab-emoji-text';span.textContent=txt;this.parentNode.replaceChild(span,this);}\">";
                                    row += "<a href=\"javascript:;\" value=\":" + emoji + ":\" title=\":" + emoji + ":\" class=\"" + classPrefix + "emoji-btn\">" + img + "</a>";
                                }
                                else if (type === "twemoji")
                                {
									var tChar = toUnicodeFromHexSequence(emoji);
                                    img = tChar
									? "<img class=\"ab-emoji-img\" src=\"" + getLocalEmojiSrc(emoji, 'twemoji') + "\" alt=\"tw-" + emoji + "\" loading=\"lazy\" decoding=\"async\" referrerpolicy=\"no-referrer\" data-ab-emoji-hex=\"" + emoji + "\" data-ab-fallback-text=\"" + tChar + "\" onerror=\"if(this.parentNode){var txt=this.getAttribute('data-ab-fallback-text')||this.getAttribute('alt');var span=document.createElement('span');span.className='ab-emoji-text';span.textContent=txt;this.parentNode.replaceChild(span,this);}\">"
                                        : "<span class=\"ab-emoji-text\">" + emoji + "</span>";
                                    row += "<a href=\"javascript:;\" value=\":tw-" + emoji + ":\" title=\":tw-" + emoji + ":\" class=\"" + classPrefix + "emoji-btn\">" + img + "</a>";
                                }
                                else if (type === "font-awesome")
                                {
                                    icon = "<i class=\"fa fa-" + emoji + " fa-emoji\" title=\"" + emoji + "\"></i>";
                                    row += "<a href=\"javascript:;\" value=\":fa-" + emoji + ":\" title=\":fa-" + emoji + ":\" class=\"" + classPrefix + "emoji-btn\">" + icon + "</a>";
                                }
                                else if (type === "editormd-logo")
                                {
                                    icon = "<i class=\"" + emoji + "\" title=\"Editor.md logo (" + emoji + ")\"></i>";
                                    row += "<a href=\"javascript:;\" value=\":" + emoji + ":\" title=\":" + emoji + ":\" style=\"width:20%;\" class=\"" + classPrefix + "emoji-btn\">" + icon + "</a>";
                                }
                            }
                            else
                            {
                                row += "<a href=\"javascript:;\" value=\"\"></a>";                        
                            }
                        }

                        row += "</div>";

                        table += row;
                    }

                    table += "</div>";
                    
                    return table;
                };
                
                if (emojiTabIndex === 0)
                {
                    for (var i = 0, len = $data.length; i < len; i++)
                    {
                        var h4Style = (i === 0) ? " style=\"margin: 0 0 10px;\"" : " style=\"margin: 10px 0;\"";
                        $tab.append("<h4" + h4Style + ">" + $data[i].category + "</h4>");
                        $tab.append(pagination($data[i].list, cname));
                    }
                }
                else
                {
                    $tab.append(pagination($data, cname));
                }

				$tab.find("." + classPrefix + "emoji-btn").bind(exports.mouseOrTouch("click", "touchend"), function() {
					$(this).toggleClass("selected");

					if ($(this).hasClass("selected")) 
					{
						selecteds.push($(this).attr("value"));
					}
				});
			};
			
			if (emojiData.length < 1) 
			{            
				if (typeof dialog.loading === "function") {
                    dialog.loading(true);
                }

				$.getJSON(path + "emoji.json?temp=" + Math.random(), function(json) {

					if (typeof dialog.loading === "function") {
                        dialog.loading(false);
                    }

					emojiData = json;
                    emojiData[logoPrefix] = logos;
					drawTable();
				});
			} 
			else 
			{
				drawTable();
			}

			tab.find("li").bind(exports.mouseOrTouch("click", "touchend"), function() {
				var $this     = $(this);
				emojiTabIndex = $this.index();

				$this.addClass("active").siblings().removeClass("active");
				tabBoxs.eq(emojiTabIndex).show().siblings().hide();
				drawTable();
			});
		};

	};
    
	// CommonJS/Node.js
	if (typeof require === "function" && typeof exports === "object" && typeof module === "object")
    { 
        module.exports = factory;
    }
	else if (typeof define === "function")  // AMD/CMD/Sea.js
    {
		if (define.amd) { // for Require.js

			define(["editormd"], function(editormd) {
                factory(editormd);
            });

		} else { // for Sea.js
			define(function(require) {
                var editormd = require("./../../editormd");
                factory(editormd);
            });
		}
	} 
	else
	{
        factory(window.editormd);
	}

})();
