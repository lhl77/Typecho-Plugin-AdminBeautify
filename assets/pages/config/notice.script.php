<?php
/**
 * 配置面板 - 公告弹窗脚本（通过服务端代理拉取 notice.md，避免浏览器 CORS 限制）
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<script>(function(){
function abInitModal(){
    var CFG=window.__AB_CONFIG__||{};
    if((CFG.notifyOptOut||"0")==="1") return;
    // 通过本站  action 代理请求，规避浏览器 CORS 及国内 GitHub 访问问题
    var ajax=window.__AB_AJAX__||{};
    if(!ajax.url) return;
    fetch(ajax.url+"?do=get-notice",{cache:"no-cache",credentials:"include"})
        .then(function(r){return r.ok?r.json():null;})
        .then(function(res){
            if(!res||res.code!==0||!res.data||!res.data.content) return;
            var md=res.data.content;
            if(!md) return;
            // 解析 YAML frontmatter
            var fm={id:"",title:""};
            var m=md.match(/^---\r?\n([\s\S]*?)\r?\n---/);
            if(m){
                var raw=m[1];
                var idM=raw.match(/id:\s*(.+)/);
                var ttM=raw.match(/title:\s*(.+)/);
                if(idM) fm.id=idM[1].trim();
                if(ttM) fm.title=ttM[1].trim();
            }
            if(!fm.id) return;
            var lsKey="ab-notice-"+fm.id;
            var dismissed="";
            try{dismissed=localStorage.getItem(lsKey)||"";}catch(e){}
            if(dismissed==="1") return;
            // 提取正文
            var body=md.replace(/^---[\s\S]*?---\r?\n/,"").trim();
            showModal(fm,body,lsKey);
        })
        .catch(function(){});

    function simpleMarkdown(text){
        return text
            .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
            .replace(/\*\*(.+?)\*\*/g,"<strong>$1</strong>")
            .replace(/\*(.+?)\*/g,"<em>$1</em>")
            .replace(/`(.+?)`/g,"<code>$1</code>")
            .replace(/^#{1,3}\s+(.+)$/gm,"<strong>$1</strong>")
            .replace(/^[-*]\s+(.+)$/gm,"• $1")
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g,"<a href=\"$2\" target=\"_blank\" rel=\"noopener\">$1</a>")
            .replace(/\n/g,"<br>");
    }

    function showModal(fm,body,lsKey){
        var overlay=document.createElement("div");
        overlay.id="ab-modal-notify";
        overlay.style.opacity="0";
        var modal=document.createElement("div");
        modal.className="ab-modal-dialog";
        modal.setAttribute("role","dialog");
        modal.setAttribute("aria-modal","true");
        modal.setAttribute("aria-labelledby","ab-modal-title");
        modal.style.transform="translateY(24px) scale(.96)";
        // 标题栏
        var hdr=document.createElement("div");
        hdr.className="ab-modal-hdr";
        var ic=document.createElement("span");ic.textContent="📢";ic.style.fontSize="20px";
        var ttl=document.createElement("h3");
        ttl.id="ab-modal-title";
        ttl.className="ab-modal-title";
        ttl.textContent=fm.title||"插件公告";
        var cls=document.createElement("button");
        cls.className="ab-modal-close";
        cls.setAttribute("aria-label","关闭");
        cls.textContent="×";
        cls.onclick=function(){ closeModal(false); };
        hdr.appendChild(ic);hdr.appendChild(ttl);hdr.appendChild(cls);
        // 正文
        var bd=document.createElement("div");
        bd.className="ab-modal-bd";
        bd.innerHTML=simpleMarkdown(body);
        // 底部按钮
        var ft=document.createElement("div");
        ft.className="ab-modal-ft";
        var btnNever=document.createElement("button");
        btnNever.className="ab-modal-btn-outline";
        btnNever.textContent="不再显示";
        btnNever.onclick=function(){ closeModal(true); };
        var btnOk=document.createElement("button");
        btnOk.className="ab-modal-btn-filled";
        btnOk.textContent="知道了";
        btnOk.onclick=function(){ closeModal(false); };
        ft.appendChild(btnNever);ft.appendChild(btnOk);
        modal.appendChild(hdr);modal.appendChild(bd);modal.appendChild(ft);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        // 点击遮罩关闭
        overlay.addEventListener("click",function(e){ if(e.target===overlay) closeModal(false); });
        // 入场动画
        requestAnimationFrame(function(){
            overlay.style.opacity="1";
            modal.style.transform="translateY(0) scale(1)";
        });
        function closeModal(never){
            if(never){try{localStorage.setItem(lsKey,"1");}catch(e){}}
            overlay.style.opacity="0";
            modal.style.transform="translateY(12px) scale(.97)";
            setTimeout(function(){overlay.parentNode&&overlay.parentNode.removeChild(overlay);},300);
        }
    }
}
if(document.readyState==="loading"){
    document.addEventListener("DOMContentLoaded",abInitModal);
} else {
    abInitModal();
}
})();</script>

