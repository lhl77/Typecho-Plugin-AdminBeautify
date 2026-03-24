<?php
/**
 * 配置面板 - 主脚本（卡片构建 + 颜色跟随 + 检查更新）
 * 纯 JS，无 PHP 变量插值
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<script>
// ---- 卡片构建：将各表单字段移入 MD3 折叠卡片 ----
(function(){
    // 查找字段对应的外层 <ul class="typecho-option"> 元素
    function findFieldUl(name){
        // Typecho 1.3 格式：ul[id^="typecho-option-item-{name}-"]
        var el=document.querySelector("ul[id^='typecho-option-item-"+name+"-']");
        if(el) return el;
        // fallback：从 input[name] 向上找最近的 <ul>
        var form=document.querySelector("form.protected")||document.querySelector("form");
        if(!form) return null;
        var inp=form.querySelector("[name=\""+name+"\"]");
        if(!inp) return null;
        var c=inp.parentNode;
        while(c&&c!==form){ if(c.tagName==="UL") return c; c=c.parentNode; }
        return null;
    }

    function buildCards(){
        // ---- 管理后台卡片 ----
        var adminFields=["primaryColor","darkMode","borderRadius","enableAnimation","dashboardQuickShow","dashboardQuickStyle","dashboardQuickHint","dashboardCustomButtons","dashboardRecentStyle","navPosition","pluginCardView"];
        var firstAdminUl=findFieldUl("primaryColor");
        var adminCard=document.getElementById("ab-card-admin");
        var adminBody=document.getElementById("ab-card-admin-body");

        if(adminCard&&adminBody&&firstAdminUl){
            var form=firstAdminUl.parentNode;
            form.insertBefore(adminCard,firstAdminUl);
            for(var i=0;i<adminFields.length;i++){
                // 在"样式"分组前插入分割线 + 分组标签
                if(adminFields[i]==="primaryColor"){
                    var abDivider=document.createElement("div");
                    abDivider.className="ab-group-divider";
                    adminBody.appendChild(abDivider);
                    var abGroupLabel=document.createElement("div");
                    abGroupLabel.className="ab-group-label";
                    abGroupLabel.textContent="样式";
                    adminBody.appendChild(abGroupLabel);
                }
                // 在"导航栏"分组前插入分割线 + 分组标签
                if(adminFields[i]==="navPosition"){
                    var abDivider=document.createElement("div");
                    abDivider.className="ab-group-divider";
                    adminBody.appendChild(abDivider);
                    var abGroupLabel=document.createElement("div");
                    abGroupLabel.className="ab-group-label";
                    abGroupLabel.textContent="导航栏";
                    adminBody.appendChild(abGroupLabel);
                }
                // 在"插件管理页"分组前插入分割线 + 分组标签
                if(adminFields[i]==="pluginCardView"){
                    var abDivider=document.createElement("div");
                    abDivider.className="ab-group-divider";
                    adminBody.appendChild(abDivider);
                    var abGroupLabel=document.createElement("div");
                    abGroupLabel.className="ab-group-label";
                    abGroupLabel.textContent="插件管理页";
                    adminBody.appendChild(abGroupLabel);
                }
                // 在"概要页"分组前插入分割线 + 分组标签
                if(adminFields[i]==="dashboardQuickShow"){
                    var abDivider=document.createElement("div");
                    abDivider.className="ab-group-divider";
                    adminBody.appendChild(abDivider);
                    var abGroupLabel=document.createElement("div");
                    abGroupLabel.className="ab-group-label";
                    abGroupLabel.textContent="概要页";
                    adminBody.appendChild(abGroupLabel);
                }
                var ul=findFieldUl(adminFields[i]);
                if(ul) adminBody.appendChild(ul);
            }
            adminBody.style.padding="0px 38px 16px";
        }

        // ---- 编辑器设置卡片（插在管理后台卡片之后）----
        var editorFields=["editor_vditor","editor_vditorMode","editor_hideToolbar"];
        var editorCard=document.getElementById("ab-card-editor");
        var editorBody=document.getElementById("ab-card-editor-body");
        if(editorCard&&editorBody){
            var firstEditorUl=findFieldUl("editor_vditor");
            if(firstEditorUl){
                var formE=firstEditorUl.parentNode;
                formE.insertBefore(editorCard,firstEditorUl);
            } else if(adminCard){
                if(adminCard.nextSibling) adminCard.parentNode.insertBefore(editorCard,adminCard.nextSibling);
                else adminCard.parentNode.appendChild(editorCard);
            }
            for(var e=0;e<editorFields.length;e++){
                var eu=findFieldUl(editorFields[e]);
                if(eu) editorBody.appendChild(eu);
            }
            editorBody.style.padding="0px 38px 16px";

            // editor_hideToolbar 仅在 editor_vditor==="1" 时显示
            (function(){
                var vditorSel=document.querySelector("[name=\"editor_vditor\"]");
                if(!vditorSel) return;
                function toggleVditorMode(){
                    var hideToolbarUl=findFieldUl("editor_vditorMode");
                    if(hideToolbarUl) hideToolbarUl.style.display=(vditorSel.value==="1")?"":"none";
                }
                vditorSel.addEventListener("change",toggleVditorMode);
                toggleVditorMode();
            })();

            // editor_hideToolbar 仅在 editor_vditor==="2" 时显示
            (function(){
                var vditorSel=document.querySelector("[name=\"editor_vditor\"]");
                if(!vditorSel) return;
                function toggleHideToolbar(){
                    var hideToolbarUl=findFieldUl("editor_hideToolbar");
                    if(hideToolbarUl) hideToolbarUl.style.display=(vditorSel.value==="2")?"":"none";
                }
                vditorSel.addEventListener("change",toggleHideToolbar);
                toggleHideToolbar();
            })();
        }

        // ---- 登录页卡片 ----
        var loginFields=["login_isEnabled","login_colorPreset","login_primaryColor","login_primaryColor2",
            "login_showSiteName","login_themeMode","login_showThemeToggle",
            "login_bgImage","login_blurType","login_blurSize","login_customCss","login_customJs"];
        var firstLoginUl=findFieldUl("login_isEnabled");
        var loginCard=document.getElementById("ab-card-login");
        var loginBody=document.getElementById("ab-card-login-body");

        if(loginCard&&loginBody&&firstLoginUl){
            var form2=firstLoginUl.parentNode;
            form2.insertBefore(loginCard,firstLoginUl);
            for(var j=0;j<loginFields.length;j++){
                var lu=findFieldUl(loginFields[j]);
                if(lu) loginBody.appendChild(lu);
            }
            var preview=document.getElementById("lb-preview");
            if(preview) loginBody.appendChild(preview);
            loginBody.style.padding="0px 38px 16px";
        }

        // ---- 兼容脚本卡片（插在登录页卡片之后）----
        var compatCard=document.getElementById("ab-card-compat");
        var compatBody=document.getElementById("ab-card-compat-body");
        if(compatCard&&compatBody){
            // 重新定位到登录页卡片之后
            if(loginCard){
                var formC=loginCard.parentNode;
                if(loginCard.nextSibling) formC.insertBefore(compatCard,loginCard.nextSibling);
                else formC.appendChild(compatCard);
            }
            // 兼容脚本列表
            var csList=document.getElementById("ab-compat-scripts-list");
            if(csList) compatBody.appendChild(csList);
            // 外部兼容 JS 字段
            var extUl=findFieldUl("compat_externalJs");
            if(extUl) compatBody.appendChild(extUl);
            compatBody.style.padding="0px 38px 16px";
        }
        // 隐藏 hidden 字段
        var hiddenUl=findFieldUl("compat_disabledScripts");
        if(hiddenUl) hiddenUl.style.display="none";

        // ---- PWA 应用卡片（插在兼容脚本卡片之后）----
        var pwaFields=["pwa_appName","pwa_appIcon"];
        var pwaCard=document.getElementById("ab-card-pwa");
        var pwaBody=document.getElementById("ab-card-pwa-body");
        if(pwaCard&&pwaBody&&compatCard){
            var form3=compatCard.parentNode;
            if(compatCard.nextSibling) form3.insertBefore(pwaCard,compatCard.nextSibling);
            else form3.appendChild(pwaCard);
            for(var p=0;p<pwaFields.length;p++){
                var pu=findFieldUl(pwaFields[p]);
                if(pu) pwaBody.appendChild(pu);
            }
            // ---- 一键安装 PWA 按钮区 ----
            (function(){
                var installBar=document.createElement("div");
                installBar.id="ab-pwa-install-bar";
                installBar.className="ab-pwa-install-bar";
                // 安装按钮
                var installBtn=document.createElement("button");
                installBtn.type="button";
                installBtn.id="ab-pwa-install-btn";
                installBtn.textContent="📲 安装到桌面";
                installBtn.className="ab-pwa-install-btn";
                
                // 提示文字
                var tipSpan=document.createElement("span");
                tipSpan.id="ab-pwa-install-tip";
                tipSpan.className="ab-pwa-install-tip";
                // 检测 beforeinstallprompt 支持
                var deferredPrompt=null;
                var supported="onbeforeinstallprompt" in window;
                if(supported){
                    tipSpan.textContent="支持一键安装（Chrome / Edge Chromium）";
                    window.addEventListener("beforeinstallprompt",function(e){
                        e.preventDefault();
                        deferredPrompt=e;
                        installBtn.disabled=false;
                        installBtn.style.opacity="1";
                        tipSpan.textContent="点击按钮即可安装到桌面（Chrome / Edge Chromium）";
                    });
                    window.addEventListener("appinstalled",function(){
                        deferredPrompt=null;
                        installBtn.disabled=true;
                        installBtn.style.opacity=".5";
                        tipSpan.textContent="✅ 已安装到桌面";
                    });
                    installBtn.disabled=true;
                    installBtn.style.opacity=".5";
                    installBtn.onclick=function(){
                        if(!deferredPrompt){
                            tipSpan.textContent="⚠️ 当前页面暂不满足安装条件（需通过 HTTPS 访问，且尚未安装）";
                            return;
                        }
                        deferredPrompt.prompt();
                        deferredPrompt.userChoice.then(function(r){
                            if(r.outcome==="accepted"){
                                tipSpan.textContent="✅ 安装已确认";
                            } else {
                                tipSpan.textContent="已取消安装";
                            }
                            deferredPrompt=null;
                        });
                    };
                } else {
                    // 不支持 beforeinstallprompt（Safari / Firefox 等）
                    installBtn.disabled=true;
                    installBtn.style.opacity=".45";
                    tipSpan.innerHTML="⚠️ 当前浏览器不支持一键安装。<br>仅 <strong>Chrome</strong>、<strong>Edge（Chromium 内核）</strong> 支持此功能；<br>Safari 请在浏览器菜单中选择「添加到主屏幕」。";
                }
                installBar.appendChild(installBtn);
                installBar.appendChild(tipSpan);

                // ---- 清除 SW 缓存按钮 ----
                var sep=document.createElement("hr");
                sep.style.cssText="border:none;border-top:1px solid var(--md-outline-variant,#cac4d0);margin:14px 0 10px";
                var clearBtn=document.createElement("button");
                clearBtn.type="button";
                clearBtn.id="ab-pwa-clear-btn";
                clearBtn.textContent="🧹 清除 SW 缓存";
                clearBtn.className="ab-pwa-install-btn";
                var clearTip=document.createElement("span");
                clearTip.id="ab-pwa-clear-tip";
                clearTip.className="ab-pwa-install-tip";
                clearTip.textContent="清除所有由 Service Worker 缓存的页面、CSS、JS 等资源";
                if(!("serviceWorker" in navigator)){
                    clearBtn.disabled=true;
                    clearBtn.style.opacity=".45";
                    clearTip.textContent="⚠️ 当前浏览器不支持 Service Worker";
                } else {
                    clearBtn.onclick=function(){
                        clearBtn.disabled=true;
                        clearBtn.textContent="⏳ 清除中...";
                        function sendClear(){
                            if(navigator.serviceWorker.controller){
                                navigator.serviceWorker.controller.postMessage({type:"CLEAR_CACHE"});
                            } else {
                                navigator.serviceWorker.ready.then(function(reg){
                                    if(reg.active) reg.active.postMessage({type:"CLEAR_CACHE"});
                                });
                            }
                        }
                        sendClear();
                        var msgHandler=function(ev){
                            if(ev.data&&ev.data.type==="CACHE_CLEARED"){
                                clearBtn.disabled=false;
                                clearBtn.textContent="🧹 清除 SW 缓存";
                                clearTip.textContent="✅ 缓存已清除，刷新页面即可获取最新资源";
                                navigator.serviceWorker.removeEventListener("message",msgHandler);
                            }
                        };
                        navigator.serviceWorker.addEventListener("message",msgHandler);
                        // 3 秒超时兜底
                        setTimeout(function(){
                            navigator.serviceWorker.removeEventListener("message",msgHandler);
                            if(clearBtn.disabled){
                                clearBtn.disabled=false;
                                clearBtn.textContent="🧹 清除 SW 缓存";
                                clearTip.textContent="✅ 清除请求已发送（如未生效请强制刷新页面）";
                            }
                        },3000);
                    };
                }
                installBar.appendChild(sep);
                installBar.appendChild(clearBtn);
                installBar.appendChild(clearTip);

                pwaBody.appendChild(installBar);
            })();
            pwaBody.style.padding="0px 38px 16px";
        }

        // ---- 性能优化卡片（插在 PWA 卡片之后） ----
        var perfFields=["staticResource","customFontUrl","customIconUrl","localFontUrl","localIconUrl","avatarSource","customAvatarUrl"];
        var perfCard=document.getElementById("ab-card-perf");
        var perfBody=document.getElementById("ab-card-perf-body");
        if(perfCard&&perfBody&&pwaCard){
            var form4=pwaCard.parentNode;
            if(pwaCard.nextSibling) form4.insertBefore(perfCard,pwaCard.nextSibling);
            else form4.appendChild(perfCard);
            for(var q=0;q<perfFields.length;q++){
                var qu=findFieldUl(perfFields[q]);
                if(qu) perfBody.appendChild(qu);
            }
            perfBody.style.padding="0px 38px 16px";
        }
        // 自定义/本地 URL 字段的显示/隐藏
        (function(){
            var sel=document.querySelector("[name=\"staticResource\"]");
            if(!sel) return;
            function toggleCustom(){
                var v=sel.value;
                var isCustom=(v==="custom");
                var isLocal=(v==="local");
                var fontUl=findFieldUl("customFontUrl");
                var iconUl=findFieldUl("customIconUrl");
                var localFontUl=findFieldUl("localFontUrl");
                var localIconUl=findFieldUl("localIconUrl");
                if(fontUl)      fontUl.style.display=isCustom?"":"none";
                if(iconUl)      iconUl.style.display=isCustom?"":"none";
                if(localFontUl) localFontUl.style.display=isLocal?"":"none";
                if(localIconUl) localIconUl.style.display=isLocal?"":"none";
            }
            sel.addEventListener("change",toggleCustom);
            toggleCustom();
        })();

        // ---- 绑定卡片点击 & 恢复/默认折叠状态 ----
        ["admin","editor","login","pwa","perf","compat"].forEach(function(id){
            var hdr=document.getElementById("ab-card-"+id+"-hdr");
            if(hdr) hdr.addEventListener("click",function(){ abToggleCard(id); });
            restoreCard(id);
        });

        // ---- 重排完成后统一显示卡片（防 FOUC）----
        document.querySelectorAll(".ab-card").forEach(function(c){ c.style.display=""; });
    }

    window.abToggleCard=function(id){
        var body=document.getElementById("ab-card-"+id+"-body");
        var chev=document.getElementById("ab-card-"+id+"-chev");
        var card=document.getElementById("ab-card-"+id);
        if(!body) return;
        var collapsed=body.getAttribute("data-collapsed")==="1";
        if(collapsed){
            // 展开：先恢复 paddingBottom，再展开高度
            body.style.paddingBottom="16px";
            body.style.maxHeight=body.scrollHeight+"px";
            setTimeout(function(){ body.style.maxHeight="9999px"; },420);
            body.setAttribute("data-collapsed","0");
            if(chev) chev.style.transform="";
            if(card) card.style.boxShadow="0 1px 3px rgba(0,0,0,.08),0 2px 12px rgba(0,0,0,.04)";
            try{ localStorage.setItem("ab-card-"+id,"1"); }catch(e){}
        } else {
            // 折叠：先锁定当前高度，再清除 paddingBottom，然后折叠到 0
            body.style.maxHeight=body.scrollHeight+"px";
            requestAnimationFrame(function(){ requestAnimationFrame(function(){
                body.style.paddingBottom="0";
                body.style.maxHeight="0";
            }); });
            body.setAttribute("data-collapsed","1");
            if(chev) chev.style.transform="rotate(-90deg)";
            if(card) card.style.boxShadow="0 1px 2px rgba(0,0,0,.04)";
            try{ localStorage.setItem("ab-card-"+id,"0"); }catch(e){}
        }
    };

    // 默认折叠；仅当 localStorage 明确为 "1" 时才展开
    function restoreCard(id){
        var saved=null;
        try{ saved=localStorage.getItem("ab-card-"+id); }catch(e){}
        var body=document.getElementById("ab-card-"+id+"-body");
        var chev=document.getElementById("ab-card-"+id+"-chev");
        var card=document.getElementById("ab-card-"+id);
        if(!body) return;
        if(saved==="1"){
            // 用户曾手动展开，保持展开
            body.setAttribute("data-collapsed","0");
        } else {
            // 默认折叠（首次访问或曾手动折叠）
            body.style.transition="none";
            body.style.paddingBottom="0";
            body.style.maxHeight="0";
            body.setAttribute("data-collapsed","1");
            if(chev) chev.style.transform="rotate(-90deg)";
            if(card) card.style.boxShadow="0 1px 2px rgba(0,0,0,.04)";
            setTimeout(function(){ body.style.transition="max-height .4s cubic-bezier(.4,0,.2,1)"; },50);
        }
    }

    if(document.readyState==="loading"){
        document.addEventListener("DOMContentLoaded",buildCards);
    } else {
        buildCards();
    }
})();

// ---- 卡片颜色跟随主题色 & 检查更新 ----
(function(){
    var abColorMap={
        purple:["#7D5260","#9E7B8A"],
        blue:  ["#556270","#7A8A9E"],
        teal:  ["#4A6363","#6A8A8A"],
        green: ["#55624C","#7A8A6E"],
        orange:["#725A42","#9E8062"],
        pink:  ["#74565F","#9E7A85"],
        red:   ["#775654","#A27A78"]
    };
    function applyConfigColors(scheme){
        var c=abColorMap[scheme]||abColorMap.purple;
        // Banner
        var banner=document.getElementById("ab-header-banner");
        if(banner) banner.style.background="linear-gradient(135deg,"+c[0]+","+c[1]+")";
        // 管理后台卡片
        var s1=document.getElementById("ab-card-admin-strip");
        if(s1) s1.style.background=c[0];
        var i1=document.getElementById("ab-card-admin-icon");
        if(i1) i1.style.background=c[0]+"1a";
        var v1=document.getElementById("ab-card-admin-chev");
        if(v1) v1.setAttribute("stroke",c[0]);
        // 编辑器设置卡片
        var se=document.getElementById("ab-card-editor-strip");
        if(se) se.style.background=c[0];
        var ie=document.getElementById("ab-card-editor-icon");
        if(ie) ie.style.background=c[0]+"1a";
        var ve=document.getElementById("ab-card-editor-chev");
        if(ve) ve.setAttribute("stroke",c[0]);
        // PWA 卡片
        var s3=document.getElementById("ab-card-pwa-strip");
        if(s3) s3.style.background=c[0];
        var i3=document.getElementById("ab-card-pwa-icon");
        if(i3) i3.style.background=c[0]+"1a";
        var v3=document.getElementById("ab-card-pwa-chev");
        if(v3) v3.setAttribute("stroke",c[0]);
        // 兼容脚本卡片
        var s4=document.getElementById("ab-card-compat-strip");
        if(s4) s4.style.background=c[1];
        var i4=document.getElementById("ab-card-compat-icon");
        if(i4) i4.style.background=c[1]+"1a";
        var v4=document.getElementById("ab-card-compat-chev");
        if(v4) v4.setAttribute("stroke",c[1]);
        // 登录页卡片
        var s2=document.getElementById("ab-card-login-strip");
        if(s2) s2.style.background=c[1];
        var i2=document.getElementById("ab-card-login-icon");
        if(i2) i2.style.background=c[1]+"1a";
        var v2=document.getElementById("ab-card-login-chev");
        if(v2) v2.setAttribute("stroke",c[1]);
        // 性能优化卡片
        var s5=document.getElementById("ab-card-perf-strip");
        if(s5) s5.style.background=c[0];
        var i5=document.getElementById("ab-card-perf-icon");
        if(i5) i5.style.background=c[0]+"1a";
        var v5=document.getElementById("ab-card-perf-chev");
        if(v5) v5.setAttribute("stroke",c[0]);
        // 关于插件卡片
        var s6=document.getElementById("ab-card-about-strip");
        if(s6) s6.style.background=c[0];
        var v6=document.getElementById("ab-card-about-chev");
        if(v6) v6.setAttribute("stroke",c[0]);
    }
    function initColorFollow(){
        var sel=document.querySelector("[name=\"primaryColor\"]");
        if(!sel) return;
        sel.addEventListener("change",function(){ applyConfigColors(this.value); });
        applyConfigColors(sel.value);
    }

    // 检查更新（调用全局 abCheckUpdate，定义于 renderFooter 注入的脚本）
    function initUpdateCheck(){
        var btn=document.getElementById("ab-btn-update");
        if(!btn) return;
        btn.addEventListener("click",function(){ window.abCheckUpdate&&window.abCheckUpdate(true); });
    }

    if(document.readyState==="loading"){
        document.addEventListener("DOMContentLoaded",function(){ initColorFollow(); initUpdateCheck(); });
    } else {
        initColorFollow(); initUpdateCheck();
    }
})();
</script>
