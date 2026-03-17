<?php
/**
 * 登录页头部 CSS 模板
 *
 * 由 AdminBeautify_Plugin::outputLoginHeaderCss() 通过 include 调用。
 * 调用方在 include 前已确保以下变量均已定义：
 *
 * @var string $primary      主色 hex，例如 #7d5260
 * @var string $primary2     渐变辅色 hex，例如 #9e7b8a
 * @var string $bgCss        背景图 CSS 值，例如 url(...) 或 none
 * @var int    $blurSize     模糊像素值 0-80
 * @var string $blurType     模糊类型：none / filter / backdrop
 * @var string $customCss    自定义 CSS 原始字符串
 * @var string $jsThemeMode  主题模式的 JS 安全字符串，例如 'auto'
 */
?>

<style id="loginbeautify-style">
:root{
--lb-primary:<?php echo htmlspecialchars($primary, ENT_QUOTES, 'UTF-8'); ?>;
--lb-primary2:<?php echo htmlspecialchars($primary2, ENT_QUOTES, 'UTF-8'); ?>;
--lb-surface:#f3f4f5;
--lb-surface-alpha:rgba(255,255,255,.8);
--lb-on-surface:#111827;
--lb-on-surface-muted:#4b5563;
--lb-border:rgba(0,0,0,.08);
--lb-shadow: 0 20px 40px -10px rgba(0,0,0,.15), 0 0 0 1px rgba(255,255,255,.4) inset;
--lb-radius: 20px;
--lb-input-bg: rgba(255,255,255,.8);
--lb-input-border: #e5e7eb;
--lb-bg-image: <?php echo $bgCss; ?>;
--lb-blur: <?php echo (int) $blurSize; ?>px;
}

@media (max-width: 575px) {
  body {
    padding-top: 0 !important;
  }
}

.typecho-login-wrap{
opacity: 0 !important;
position: absolute !important;
pointer-events: none !important;
}

html[data-lb-theme="dark"]{
--lb-surface:#111827;
--lb-surface-alpha:rgba(20,20,20,.75);
--lb-on-surface:#f9fafb;
--lb-on-surface-muted:#9ca3af;
--lb-border:rgba(255,255,255,.08);
--lb-shadow: 0 25px 50px -12px rgba(0,0,0,.6), 0 0 0 1px rgba(255,255,255,.05) inset;
--lb-input-bg: rgba(0,0,0,.2);
--lb-input-border: rgba(255,255,255,.1);
}

html{
transition: background-color .3s ease, color .3s ease;
}

body{
margin:0;
background: var(--lb-surface);
color: var(--lb-on-surface);
font-family: system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
transition: background-color .3s ease, color .3s ease;
}

.lb-wrap{
min-height:100vh;
display:flex;
align-items:center;
justify-content:center;
position:relative;
overflow:hidden;
}

.lb-bg{
position:absolute;
inset:0;
background-image: var(--lb-bg-image);
background-size: cover;
background-position: center;
background-repeat:no-repeat;
z-index:-2;
transform: scale(1.03);
}

.lb-bg-overlay{
position:absolute;
inset:0;
background: linear-gradient(180deg, rgba(0,0,0,.2), rgba(0,0,0,.4));
z-index:-1;
transition: background .3s ease;
}

html[data-lb-theme="light"] .lb-bg-overlay{
background: linear-gradient(180deg, rgba(255,255,255,0), rgba(255,255,255,0));
}

.lb-card{
width:min(400px, 94%);
background: var(--lb-surface-alpha);
color: var(--lb-on-surface);
border: 1px solid var(--lb-border);
border-radius: var(--lb-radius);
box-shadow: var(--lb-shadow);
padding: 32px 32px 28px;
transition: background-color .3s ease, border-color .3s ease, box-shadow .3s ease;
backdrop-filter: blur(20px);
-webkit-backdrop-filter: blur(20px);
}

<?php if ($blurType === 'backdrop') { ?>
.lb-card{
backdrop-filter: blur(var(--lb-blur));
-webkit-backdrop-filter: blur(var(--lb-blur));
}
<?php } ?>

<?php if ($blurType === 'filter') { ?>
.lb-bg{
filter: blur(var(--lb-blur));
}
<?php } ?>

.lb-head{
display:flex;
flex-direction:column;
align-items:center;
text-align:center;
margin-bottom: 20px;
}

.lb-title{
display:flex;
flex-direction:column;
gap:8px;
width:100%;
}

.lb-title .name{
font-size: 16px;
font-weight: 500;
color: var(--lb-on-surface-muted);
}

.lb-title .sub{
font-size: 24px;
font-weight: 800;
letter-spacing: -0.025em;
color: var(--lb-on-surface);
margin-bottom: 8px;
}

.lb-form .lb-field{
margin-top: 16px;
}

.lb-form label{
display:block;
font-size: 12px;
font-weight: 500;
color: var(--lb-on-surface-muted);
margin: 0 0 6px 1px;
}

.lb-form input[type="text"],
.lb-form input[type="password"],
.lb-form input[type="email"]{
width:100%;
box-sizing:border-box;
padding: 12px 14px;
border-radius: 10px;
border: 1px solid var(--lb-input-border);
background: var(--lb-input-bg);
color: var(--lb-on-surface);
font-size: 14px;
outline: none;
transition: all .2s ease;
}

html[data-lb-theme="dark"] .lb-form input[type="text"],
html[data-lb-theme="dark"] .lb-form input[type="password"]{
background: rgba(255,255,255,.06);
}

.lb-form input[type="text"]:focus,
.lb-form input[type="password"]:focus{
border-color: var(--lb-primary);
background: var(--lb-surface);
box-shadow: 0 0 0 3px color-mix(in srgb, var(--lb-primary) 15%, transparent);
}

@supports not (color: color-mix(in srgb, red, blue)) {
.lb-form input[type="text"]:focus,
.lb-form input[type="password"]:focus{
box-shadow: 0 0 0 4px rgba(103,80,164,.18);
}
html[data-lb-theme="dark"] .lb-form input[type="text"]:focus,
html[data-lb-theme="dark"] .lb-form input[type="password"]:focus{
box-shadow: 0 0 0 4px rgba(103,80,164,.25);
}
}

.lb-actions{
display:flex;
align-items:center;
justify-content:space-between;
gap:12px;
margin: 12px 0 6px;
}

.lb-remember{
font-size: 13px;
color: var(--lb-on-surface2);
display:flex;
align-items:center;
gap:8px;
}

.lb-remember input{ accent-color: var(--lb-primary); }

.lb-submit input[type="submit"],
.lb-submit button{
width:100%;
margin-top: 20px;
border:0;
cursor:pointer;
padding: 12px 16px;
border-radius: 12px;
font-size: 14px;
background: linear-gradient(135deg, var(--lb-primary), var(--lb-primary2));
color:#fff;
font-weight:600;
letter-spacing:0.5px;
box-shadow: 0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -1px rgba(0,0,0,.06);
transition: all .2s ease;
}

.lb-submit input[type="submit"]:hover{
filter: brightness(1.08);
transform: translateY(-1px);
box-shadow: 0 10px 15px -3px rgba(0,0,0,.15);
}

@supports not (color: color-mix(in srgb, red, blue)) {
.lb-submit input[type="submit"]:hover{
box-shadow: 0 10px 24px rgba(103,80,164,.30);
}
}

.message.popup{
position: fixed !important;
top: 20px !important;
left: 50% !important;
transform: translateX(-50%) !important;
width: auto !important;
max-width: calc(100vw - 40px) !important;
min-width: 280px !important;
border-radius: 10px !important;
padding: 0 !important;
margin: 0 !important;
background: none !important;
border: none !important;
box-shadow: none !important;
backdrop-filter: blur(10px) !important;
-webkit-backdrop-filter: blur(10px) !important;
animation: lb-slide-down 0.3s ease-out !important;
z-index: 9999 !important;
}

.notice{
background:none!important;
}

@keyframes lb-slide-down {
from { opacity: 0; transform: translateX(-50%) translateY(-20px); }
to { opacity: 1; transform: translateX(-50%) translateY(0); }
}

.message.popup ul{
margin: 0 !important;
padding: 0 !important;
list-style: none !important;
}

.message.popup ul li{
padding: 14px 18px !important;
margin: 5px !important;
font-size: 14px !important;
line-height: 1.5 !important;
color: var(--lb-on-surface) !important;
display: flex !important;
align-items: center !important;
gap: 10px !important;
}

.message.popup ul li:before{
content: '⚠' !important;
font-size: 18px !important;
display: inline-block !important;
}

.message.popup.notice ul li{
background: linear-gradient(135deg, #f59e0b, #ef4444) !important;
color: #fff !important;
border-radius: 14px !important;
}

.message.popup.notice ul li:before{
content: '⚠' !important;
font-weight: bold !important;
}

.message.popup.success ul li{
background: linear-gradient(135deg, #10b981, #059669) !important;
color: #fff !important;
border-radius: 14px !important;
}

.message.popup.success ul li:before{
content: '✓' !important;
font-weight: bold !important;
}

.message.popup.error ul li{
background: linear-gradient(135deg, #dc2626, #ef4444) !important;
color: #fff !important;
border-radius: 14px !important;
}

.message.popup.error ul li:before{
content: '✕' !important;
font-weight: bold !important;
}

@media (max-width: 480px) {
.message.popup{
top: 16px !important;
max-width: calc(100vw - 32px) !important;
min-width: 260px !important;
}
.message.popup ul li{
padding: 12px 16px !important;
font-size: 13px !important;
}
}

.lb-theme-toggle{
position: fixed;
right: 20px;
top: 20px;
width: 48px;
height: 48px;
border-radius: 50%;
border: 1px solid var(--lb-outline);
background: var(--lb-surface-alpha);
color: var(--lb-on-surface);
backdrop-filter: blur(10px);
-webkit-backdrop-filter: blur(10px);
box-shadow: 0 4px 12px rgba(0,0,0,.12);
cursor:pointer;
transition: all .25s cubic-bezier(0.4, 0, 0.2, 1);
display:flex;
align-items:center;
justify-content:center;
padding:0;
z-index:1000;
}

.lb-theme-toggle:hover{
transform: translateY(-2px) scale(1.05);
box-shadow: 0 8px 20px rgba(0,0,0,.18);
border-color: var(--lb-primary);
}

.lb-theme-toggle:active {
transform: translateY(0) scale(0.98);
box-shadow: 0 2px 8px rgba(0,0,0,.12);
}

.lb-theme-toggle svg{
width: 20px;
height: 20px;
transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
}

.lb-theme-toggle .lb-icon-sun,
.lb-theme-toggle .lb-icon-moon{
position: absolute;
transition: opacity .3s ease, transform .3s cubic-bezier(0.4, 0, 0.2, 1);
}

html[data-lb-theme="light"] .lb-theme-toggle .lb-icon-sun{
opacity: 0;
transform: rotate(-90deg) scale(0.8);
}

html[data-lb-theme="light"] .lb-theme-toggle .lb-icon-moon{
opacity: 1;
transform: rotate(0) scale(1);
}

html[data-lb-theme="dark"] .lb-theme-toggle .lb-icon-sun{
opacity: 1;
transform: rotate(0) scale(1);
}

html[data-lb-theme="dark"] .lb-theme-toggle .lb-icon-moon{
opacity: 0;
transform: rotate(90deg) scale(0.8);
}

@media (max-width: 480px) {
.lb-theme-toggle{
right: 16px;
top: 16px;
width: 44px;
height: 44px;
}
.lb-theme-toggle svg{
width: 18px;
height: 18px;
}
}

.lb-remember{
  display:none !important;
}

.lb-footer-theme{
text-align: center;
margin-top: 28px;
font-size: 11.5px;
font-weight: 500;
letter-spacing: 0.02em;
color: var(--lb-on-surface-muted);
opacity: 0.55;
transition: opacity .3s ease;
line-height: 1.9;
}

.lb-footer-theme:hover{
opacity: 0.95;
}

.lb-footer-theme a{
color: inherit;
font-weight: 600;
text-decoration: none;
padding-bottom: 2px;
border-bottom: 1px solid transparent;
transition: all .2s ease;
}

.lb-footer-theme a:hover{
color: var(--lb-primary);
border-bottom-color: color-mix(in srgb, var(--lb-primary) 40%, transparent);
}

.lb-footer-links{
display: block;
font-size: 11px;
font-weight: 400;
opacity: 0.75;
margin-top: 2px;
}

.lb-hide { display:none !important; }

<?php
if (trim($customCss) !== '') {
    echo "\n/* --- custom login css --- */\n";
    echo $customCss . "\n";
}
?>
</style>

<script id="loginbeautify-theme-init">
(function(){try{var mode=<?php echo $jsThemeMode;?>;var saved=localStorage.getItem('lb-theme');var dark=false;if(saved==='light'||saved==='dark'){dark=saved==='dark'}else if(mode==='dark'){dark=true}else if(mode==='light'){dark=false}else{dark=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches}document.documentElement.setAttribute('data-lb-theme',dark?'dark':'light')}catch(e){}})();
</script>
