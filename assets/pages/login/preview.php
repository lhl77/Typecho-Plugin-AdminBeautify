<?php
/**
 * 登录页配置预览模板
 *
 * 由 AdminBeautify_Plugin::renderLoginPreview() 通过 include 调用。
 * 调用方在 include 前已确保以下变量均已定义：
 *
 * @var string $pc1 自定义预设时的主色 hex 默认值，例如 #7d5260
 * @var string $pc2 自定义预设时的辅色 hex 默认值，例如 #9e7b8a
 */
?>

<style>
#lb-preview{margin-top:16px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.08)}#lb-preview .lbpv-head{padding:12px 16px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;background:#fff}#lb-preview .lbpv-head strong{font-size:14px;color:#374151;font-weight:600}#lb-preview .lbpv-head .lbpv-left{display:flex;align-items:center;gap:12px}#lb-preview .lbpv-head .lbpv-theme-btns{display:flex;gap:6px;background:#f3f4f6;padding:3px;border-radius:8px}#lb-preview .lbpv-theme-btns button{padding:4px 12px;border:none;border-radius:6px;background:transparent;cursor:pointer;font-size:12px;font-weight:500;color:#6b7280;transition:all .2s}#lb-preview .lbpv-theme-btns button:hover{color:#374151}#lb-preview .lbpv-theme-btns button.active{background:#fff;color:#000;box-shadow:0 1px 3px rgba(0,0,0,.1)}#lb-preview .lbpv-refresh{padding:6px 12px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;cursor:pointer;font-size:12px;color:#6b7280;transition:all .2s;display:flex;align-items:center;gap:6px}#lb-preview .lbpv-refresh:hover{background:#f9fafb;color:#374151;border-color:#d1d5db}#lb-preview .lbpv-refresh:active{transform:scale(0.96)}#lb-preview .lbpv-refresh svg{width:14px;height:14px;transition:transform .3s}#lb-preview .lbpv-refresh.spinning svg{animation:lb-spin .6s linear}@keyframes lb-spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}#lb-preview .lbpv-body{padding:40px 20px;background:#f9fafb;min-height:420px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;transition:background .3s}#lb-preview .lbpv-bg{position:absolute;inset:0;background-size:cover;background-position:center;z-index:0;transform:scale(1.03);transition:all .3s}#lb-preview .lbpv-bg-overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.2),rgba(0,0,0,.4));z-index:1;transition:background .3s}#lb-preview[data-theme="light"] .lbpv-bg-overlay{background:linear-gradient(180deg,rgba(255,255,255,.2),rgba(255,255,255,.4))}#lb-preview .lbpv-card{position:relative;z-index:2;max-width:380px;width:100%;border-radius:20px;border:1px solid rgba(255,255,255,.6);background:rgba(255,255,255,.8);padding:32px 28px;box-shadow:0 20px 40px -10px rgba(0,0,0,.15),0 0 0 1px rgba(255,255,255,.4) inset;transition:all .3s;backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);}#lb-preview[data-theme="dark"] .lbpv-card{background:rgba(20,20,20,.75);border-color:rgba(255,255,255,.08);box-shadow:0 25px 50px -12px rgba(0,0,0,.6),0 0 0 1px rgba(255,255,255,.05) inset;}#lb-preview[data-theme="dark"] .lbpv-body{background:#111827}#lb-preview .lbpv-title{font-size:16px;font-weight:500;text-align:center;margin-bottom:6px;color:#4b5563;transition:color .3s}#lb-preview[data-theme="dark"] .lbpv-title{color:#9ca3af}#lb-preview .lbpv-sub{font-size:24px;font-weight:800;color:#111827;text-align:center;margin-bottom:28px;transition:color .3s;letter-spacing:-0.025em}#lb-preview[data-theme="dark"] .lbpv-sub{color:#f9fafb}#lb-preview .lbpv-field{margin-bottom:16px}#lb-preview .lbpv-label{display:block;font-size:12px;color:#6b7280;margin-bottom:6px;font-weight:500}#lb-preview[data-theme="dark"] .lbpv-label{color:#9ca3af}#lb-preview .lbpv-input{width:100%;box-sizing:border-box;padding:12px 14px;border-radius:10px;border:1px solid #e5e7eb;background:rgba(255,255,255,.8);font-size:14px;outline:none;transition:all .2s;color:#1f2937}#lb-preview[data-theme="dark"] .lbpv-input{background:rgba(0,0,0,.2);border-color:rgba(255,255,255,.1);color:#e5e7eb}#lb-preview .lbpv-btn{width:100%;padding:12px;border:0;border-radius:12px;color:#fff;font-weight:600;font-size:14px;cursor:pointer;transition:all .2s;margin-top:8px;box-shadow:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -1px rgba(0,0,0,.06)}#lb-preview .lbpv-btn:hover{filter:brightness(1.08);transform:translateY(-1px);box-shadow:0 10px 15px -3px rgba(0,0,0,.15)}#lb-preview .lbpv-btn:active{transform:translateY(0);filter:brightness(0.95)}
</style>

<div id="lb-preview" data-theme="light">
  <div class="lbpv-head">
    <div class="lbpv-left">
      <strong>🔐 登录页预览</strong>
      <button type="button" class="lbpv-refresh" id="lbpv-refresh" title="刷新预览">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
        </svg>
        刷新
      </button>
    </div>
    <div class="lbpv-theme-btns">
      <button type="button" data-theme="light" class="lbpv-theme-light active">☀️ 亮色</button>
      <button type="button" data-theme="dark" class="lbpv-theme-dark">🌙 暗色</button>
    </div>
  </div>
  <div class="lbpv-body">
    <div class="lbpv-bg" id="lbpv-bg"></div>
    <div class="lbpv-bg-overlay"></div>
    <div class="lbpv-card">
      <div class="lbpv-title" id="lbpv-title">我的博客</div>
      <div class="lbpv-sub">登录</div>
      <div class="lbpv-field">
        <label class="lbpv-label">用户名/邮箱</label>
        <input type="text" class="lbpv-input" value="user" readonly>
      </div>
      <div class="lbpv-field">
        <label class="lbpv-label">密码</label>
        <input type="password" class="lbpv-input" value="password" readonly>
      </div>
      <button class="lbpv-btn" id="lbpv-btn" type="button">登录</button>
    </div>
  </div>
</div>

<script>
(function(){var colorPresets={purple:["#7d5260","#9e7b8a"],blue:["#556270","#7a8a9e"],pink:["#74565f","#9e7a85"],green:["#55624c","#7a8a6e"],orange:["#725a42","#9e8062"],red:["#775654","#a27a78"],teal:["#4a6363","#6a8a8a"],indigo:["#5a4fd9","#7b6ef2"],sunset:["#d38d1a","#e06b3a"],ocean:["#0da0d8","#39c1dd"],forest:["#2f7a3b","#7fbf3a"],lavender:["#8f6ee8","#b89cfb"]};function val(name){var el=document.querySelector('[name="'+name+'"]');if(!el)return"";if(el.type==="radio"){var c=document.querySelector('[name="'+name+'"]:checked');return c?c.value:""}return(el.value||"").trim()}var btn=document.getElementById("lbpv-btn");var title=document.getElementById("lbpv-title");var bg=document.getElementById("lbpv-bg");var preview=document.getElementById("lb-preview");var themeButtons=preview.querySelectorAll(".lbpv-theme-btns button");var refreshBtn=document.getElementById("lbpv-refresh");function normalizeColor(s,fallback){s=(s||"").trim();return s?s:fallback}function getCurrentColors(){var preset=val("login_colorPreset")||"purple";var c1,c2;if(preset==="custom"){c1=normalizeColor(val("login_primaryColor"),<?php echo json_encode($pc1);?>);c2=normalizeColor(val("login_primaryColor2"),<?php echo json_encode($pc2);?>)}else{var colors=colorPresets[preset]||colorPresets.purple;c1=colors[0];c2=colors[1]}return{c1:c1,c2:c2}}function updateAllButtonColors(){var colors=getCurrentColors();var gradient="linear-gradient(135deg,"+colors.c1+","+colors.c2+")";btn.style.background=gradient;var inputs=preview.querySelectorAll(".lbpv-input");inputs.forEach(function(inp){inp.style.caretColor=colors.c1});themeButtons.forEach(function(b){if(b.classList.contains("active")){b.style.background=gradient;b.style.color="#fff"}else{b.style.background="#fff";b.style.color=""}})}function render(){var showName=val("login_showSiteName")||"1";var bgUrl=val("login_bgImage")||"";var blurType=val("login_blurType")||"filter";var blurSize=parseInt(val("login_blurSize")||"12");if(isNaN(blurSize)||blurSize<0)blurSize=0;if(blurSize>80)blurSize=80;updateAllButtonColors();title.style.display=(showName==="1")?"block":"none";var overlay=preview.querySelector(".lbpv-bg-overlay");var body=preview.querySelector(".lbpv-body");if(bgUrl){bg.style.backgroundImage="url('"+bgUrl+"')";bg.style.display="block";overlay.style.display="block";var currentTheme=preview.getAttribute("data-theme");if(currentTheme==="dark"){overlay.style.background="linear-gradient(180deg,rgba(0,0,0,.3),rgba(0,0,0,.5))"}else{overlay.style.background="transparent"}body.style.background="transparent"}else{bg.style.backgroundImage="none";bg.style.display="none";overlay.style.display="none";var currentTheme=preview.getAttribute("data-theme");if(currentTheme==="dark"){body.style.background="#111827"}else{body.style.background="#f9fafb"}}bg.style.filter="";var card=preview.querySelector(".lbpv-card");card.style.backdropFilter="blur(20px)";card.style.webkitBackdropFilter="blur(20px)";if(bgUrl&&blurType==="filter"){bg.style.filter="blur("+blurSize+"px)"}else if(bgUrl&&blurType==="backdrop"){var size=Math.max(20,blurSize);card.style.backdropFilter="blur("+size+"px)";card.style.webkitBackdropFilter="blur("+size+"px)"}}refreshBtn.addEventListener("click",function(){this.classList.add("spinning");var self=this;setTimeout(function(){self.classList.remove("spinning")},600);render()});themeButtons.forEach(function(themeBtn){themeBtn.addEventListener("click",function(){var theme=this.getAttribute("data-theme");preview.setAttribute("data-theme",theme);themeButtons.forEach(function(b){b.classList.remove("active")});this.classList.add("active");render()})});setTimeout(function(){render()},500)})();
</script>
