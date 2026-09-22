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
#lb-preview{margin-top:16px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.08)}
#lb-preview .lbpv-head{padding:12px 16px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;background:#fff}
#lb-preview .lbpv-head strong{font-size:14px;color:#374151;font-weight:600}
#lb-preview .lbpv-head .lbpv-left{display:flex;align-items:center;gap:12px}
#lb-preview .lbpv-head .lbpv-theme-btns{display:flex;gap:6px;background:#f3f4f6;padding:3px;border-radius:8px}
#lb-preview .lbpv-theme-btns button{padding:4px 12px;border:none;border-radius:6px;background:transparent;cursor:pointer;font-size:12px;font-weight:500;color:#6b7280;transition:all .2s}
#lb-preview .lbpv-theme-btns button:hover{color:#374151}
#lb-preview .lbpv-theme-btns button.active{background:#fff;color:#000;box-shadow:0 1px 3px rgba(0,0,0,.1)}
#lb-preview .lbpv-refresh{padding:6px 12px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;cursor:pointer;font-size:12px;color:#6b7280;transition:all .2s;display:flex;align-items:center;gap:6px}
#lb-preview .lbpv-refresh:hover{background:#f9fafb;color:#374151;border-color:#d1d5db}
#lb-preview .lbpv-refresh:active{transform:scale(0.96)}
#lb-preview .lbpv-refresh svg{width:14px;height:14px;transition:transform .3s}
#lb-preview .lbpv-refresh.spinning svg{animation:lb-spin .6s linear}
@keyframes lb-spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
#lb-preview .lbpv-body{padding:40px 20px;background:#f9fafb;min-height:420px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;transition:background .3s}
#lb-preview .lbpv-bg{position:absolute;inset:0;background-size:cover;background-position:center;z-index:0;transform:scale(1.03);transition:all .3s}
#lb-preview .lbpv-bg-overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.2),rgba(0,0,0,.4));z-index:1;transition:background .3s}
#lb-preview[data-theme="light"] .lbpv-bg-overlay{background:linear-gradient(180deg,rgba(255,255,255,.2),rgba(255,255,255,.4))}
#lb-preview .lbpv-card{position:relative;z-index:2;max-width:380px;width:100%;border-radius:24px;border:1px solid transparent;background:linear-gradient(rgba(255,255,255,.72),rgba(255,255,255,.72)) padding-box,linear-gradient(152deg,rgba(255,255,255,.92) 0%,rgba(255,255,255,.22) 42%,rgba(255,255,255,.52) 100%) border-box;padding:32px 28px;box-shadow:none;transition:background .3s,backdrop-filter .3s;backdrop-filter:blur(20px) saturate(160%);-webkit-backdrop-filter:blur(20px) saturate(160%);}
#lb-preview[data-theme="dark"] .lbpv-card{background:linear-gradient(rgba(30,28,36,.74),rgba(30,28,36,.74)) padding-box,linear-gradient(152deg,rgba(255,255,255,.26) 0%,rgba(255,255,255,.04) 42%,rgba(255,255,255,.12) 100%) border-box;}
#lb-preview[data-theme="dark"] .lbpv-body{background:#111827}
#lb-preview .lbpv-title{font-size:16px;font-weight:500;text-align:center;margin-bottom:6px;color:#4b5563;transition:color .3s}
#lb-preview[data-theme="dark"] .lbpv-title{color:#9ca3af}
#lb-preview .lbpv-sub{font-size:24px;font-weight:800;color:#111827;text-align:center;margin-bottom:28px;transition:color .3s;letter-spacing:-0.025em}
#lb-preview[data-theme="dark"] .lbpv-sub{color:#f9fafb}
#lb-preview .lbpv-field{margin-bottom:20px;position:relative}
/* MD3 四角大圆角文本域：预览中的输入框始终有内容，标签保持上浮状态 */
#lb-preview .lbpv-label{position:absolute;left:18px;right:18px;top:13px;transform:translateY(-50%) scale(.74);transform-origin:left center;font-size:15px;font-weight:400;line-height:20px;color:#6b7280;margin:0;pointer-events:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;z-index:2}
#lb-preview[data-theme="dark"] .lbpv-label{color:#9ca3af}
#lb-preview .lbpv-input{width:100%;box-sizing:border-box;height:58px;padding:24px 18px 10px;border-radius:18px;border:1px solid rgba(17,24,39,.10);background-color:rgba(255,255,255,.55);font-size:15px;line-height:20px;outline:none;box-shadow:inset 0 1px 0 rgba(255,255,255,.70);transition:border-color .45s cubic-bezier(.2,0,0,1),background-color .45s cubic-bezier(.2,0,0,1),box-shadow .6s cubic-bezier(.2,0,0,1);color:#1f2937}
#lb-preview[data-theme="dark"] .lbpv-input{background-color:rgba(255,255,255,.07);border-color:rgba(255,255,255,.14);box-shadow:inset 0 1px 0 rgba(255,255,255,.16);color:#e5e7eb}
#lb-preview .lbpv-input:focus{border-color:var(--lbpv-c1,#7d5260);background-color:rgba(255,255,255,.82);box-shadow:inset 0 1px 0 rgba(255,255,255,.70),0 0 0 3px rgba(125,82,96,.14)}
#lb-preview[data-theme="dark"] .lbpv-input:focus{background-color:rgba(255,255,255,.14)}
#lb-preview .lbpv-btn{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center;width:100%;height:54px;padding:0 24px;border:0;border-radius:100px;color:#fff;font-weight:600;font-size:15px;line-height:1;letter-spacing:.03em;cursor:pointer;margin-top:24px;box-shadow:0 10px 24px -12px rgba(125,82,96,.95),0 3px 8px -3px rgba(0,0,0,.26),inset 0 1px 0 rgba(255,255,255,.28);transition:box-shadow .28s cubic-bezier(.2,0,0,1),transform .2s cubic-bezier(.22,1.12,.36,1)}
#lb-preview .lbpv-btn::after{content:'';position:absolute;inset:0;z-index:2;background:#fff;opacity:0;pointer-events:none;border-radius:inherit;transition:opacity .15s cubic-bezier(.2,0,0,1)}
#lb-preview .lbpv-btn:hover::after{opacity:.08}
#lb-preview .lbpv-btn:hover{transform:translateY(-1px);box-shadow:0 16px 32px -14px rgba(125,82,96,1),0 4px 10px -3px rgba(0,0,0,.3),inset 0 1px 0 rgba(255,255,255,.32)}
#lb-preview .lbpv-btn:active{transform:scale(.972);box-shadow:0 4px 12px -8px rgba(125,82,96,.9),0 1px 3px rgba(0,0,0,.2)}
#lb-preview .lbpv-btn > span{position:relative;z-index:1}
/* 「下次自动登录」开关（与真实登录页的 MD3 switch 一致的静态示意；点击可预览勾选态） */
#lb-preview .lbpv-remember{display:flex;align-items:center;gap:10px;margin-top:16px;font-size:13px;color:#4b5563;cursor:pointer;user-select:none;transition:color .3s}
#lb-preview[data-theme="dark"] .lbpv-remember{color:#9ca3af}
#lb-preview .lbpv-switch{flex:none;position:relative;width:36px;height:20px;border-radius:100px;background:rgba(17,24,39,.10);transition:background .25s}
#lb-preview[data-theme="dark"] .lbpv-switch{background:rgba(255,255,255,.12)}
#lb-preview .lbpv-switch::after{content:'';position:absolute;top:50%;left:3px;width:14px;height:14px;border-radius:50%;background:#6b7280;transform:translateY(-50%);transition:transform .28s cubic-bezier(.22,1.12,.36,1),background .25s}
#lb-preview[data-theme="dark"] .lbpv-switch::after{background:#9ca3af}
#lb-preview .lbpv-remember.is-on .lbpv-switch{background:var(--lbpv-c1,#7d5260)}
#lb-preview .lbpv-remember.is-on .lbpv-switch::after{background:#fff;transform:translate(16px,-50%)}
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
      <button class="lbpv-btn" id="lbpv-btn" type="button"><span>登录</span></button>
      <div class="lbpv-remember" id="lbpv-remember">
        <span class="lbpv-switch" aria-hidden="true"></span>
        <span>下次自动登录</span>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
    var colorPresets = {
        purple: ["#7d5260", "#9e7b8a"],
        blue: ["#556270", "#7a8a9e"],
        pink: ["#74565f", "#9e7a85"],
        green: ["#55624c", "#7a8a6e"],
        orange: ["#725a42", "#9e8062"],
        red: ["#775654", "#a27a78"],
        teal: ["#4a6363", "#6a8a8a"],
        indigo: ["#5a4fd9", "#7b6ef2"],
        sunset: ["#d38d1a", "#e06b3a"],
        ocean: ["#0da0d8", "#39c1dd"],
        forest: ["#2f7a3b", "#7fbf3a"],
        lavender: ["#8f6ee8", "#b89cfb"]
    };

  function val(name){
    var el = document.querySelector('[name="' + name + '"]');
    if (!el) return "";
    if (el.type === "radio") {
      var c = document.querySelector('[name="' + name + '"]:checked');
      return c ? c.value : "";
    }
    return (el.value || "").trim();
  }

  var btn = document.getElementById("lbpv-btn");
  var title = document.getElementById("lbpv-title");
  var bg = document.getElementById("lbpv-bg");
  var preview = document.getElementById("lb-preview");
  var themeButtons = preview.querySelectorAll(".lbpv-theme-btns button");
  var refreshBtn = document.getElementById("lbpv-refresh");

  function normalizeColor(s, fallback){
    s = (s || "").trim();
    return s ? s : fallback;
  }

  function getCurrentColors(){
    var preset = val("login_colorPreset") || "purple";
    var c1, c2;
    if (preset === "custom") {
      c1 = normalizeColor(val("login_primaryColor"), <?php echo json_encode($pc1); ?>);
      c2 = normalizeColor(val("login_primaryColor2"), <?php echo json_encode($pc2); ?>);
    } else {
      var colors = colorPresets[preset] || colorPresets.purple;
      c1 = colors[0];
      c2 = colors[1];
    }
    return {c1: c1, c2: c2};
  }

  function updateAllButtonColors(){
    var colors = getCurrentColors();
    var gradient = "linear-gradient(135deg," + colors.c1 + "," + colors.c2 + ")";
    btn.style.background = gradient;
    /* 供输入框聚焦描边使用 */
    preview.style.setProperty("--lbpv-c1", colors.c1);
    var inputs = preview.querySelectorAll(".lbpv-input");
    inputs.forEach(function(inp){ inp.style.caretColor = colors.c1; });
    themeButtons.forEach(function(b){
      if (b.classList.contains("active")) {
        b.style.background = gradient;
        b.style.color = "#fff";
      } else {
        b.style.background = "#fff";
        b.style.color = "";
      }
    });
  }

  function render(){
    var showName = val("login_showSiteName") || "1";
    var bgUrl = val("login_bgImage") || "";
    var blurType = val("login_blurType") || "filter";
    var blurSize = parseInt(val("login_blurSize") || "12");
    if (isNaN(blurSize) || blurSize < 0) blurSize = 0;
    if (blurSize > 80) blurSize = 80;

    updateAllButtonColors();
    title.style.display = (showName === "1") ? "block" : "none";

    var overlay = preview.querySelector(".lbpv-bg-overlay");
    var body = preview.querySelector(".lbpv-body");

    if (bgUrl) {
      bg.style.backgroundImage = "url('" + bgUrl + "')";
      bg.style.display = "block";
      overlay.style.display = "block";
      var currentTheme = preview.getAttribute("data-theme");
      if (currentTheme === "dark") {
        overlay.style.background = "linear-gradient(180deg,rgba(0,0,0,.3),rgba(0,0,0,.5))";
      } else {
        overlay.style.background = "transparent";
      }
      body.style.background = "transparent";
    } else {
      bg.style.backgroundImage = "none";
      bg.style.display = "none";
      overlay.style.display = "none";
      var currentTheme = preview.getAttribute("data-theme");
      if (currentTheme === "dark") {
        body.style.background = "#111827";
      } else {
        body.style.background = "#f9fafb";
      }
    }

    bg.style.filter = "";
    var card = preview.querySelector(".lbpv-card");
    card.style.backdropFilter = "blur(20px) saturate(160%)";
    card.style.webkitBackdropFilter = "blur(20px) saturate(160%)";

    if (bgUrl && blurType === "filter") {
      bg.style.filter = "blur(" + blurSize + "px)";
    } else if (bgUrl && blurType === "backdrop") {
      var size = Math.max(10, blurSize);
      card.style.backdropFilter = "blur(" + size + "px) saturate(160%)";
      card.style.webkitBackdropFilter = "blur(" + size + "px) saturate(160%)";
    }
  }

  refreshBtn.addEventListener("click", function(){
    this.classList.add("spinning");
    var self = this;
    setTimeout(function(){ self.classList.remove("spinning"); }, 600);
    render();
  });

  themeButtons.forEach(function(themeBtn){
    themeBtn.addEventListener("click", function(){
      var theme = this.getAttribute("data-theme");
      preview.setAttribute("data-theme", theme);
      themeButtons.forEach(function(b){ b.classList.remove("active"); });
      this.classList.add("active");
      render();
    });
  });

  /* 「下次自动登录」开关：点击切换预览态（真实登录页由原生 checkbox 承担） */
  var rememberEl = document.getElementById("lbpv-remember");
  if (rememberEl) {
    rememberEl.addEventListener("click", function () {
      rememberEl.classList.toggle("is-on");
    });
  }

  setTimeout(function(){ render(); }, 500);
})();
</script>
