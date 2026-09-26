<?php
/**
 * 「忘记密码」页面的补充样式
 *
 * 基础外观（玻璃卡片 / MD3 文本域 / 主按钮 / 主题切换 / 页脚）全部复用登录页的
 * assets/pages/login/style.php，本文件只补登录页没有的几块：
 *   · 步骤指示条
 *   · 提示条（普通 / 出错 / 成功）
 *   · 两种验证方式的入口卡
 *   · 本地文件验证的「验证串」展示框
 * 颜色一律取登录页已定义的 --lb-* 变量，因此亮/暗主题自动跟随。
 */
?>

<style id="ab-forgot-style">
/* 内联 SVG 图标统一样式（本页不使用字体图标，与登录页脚本保持一致） */
.ab-fp-ico{
flex: none;
display: block;
width: 16px;
height: 16px;
}

/* ---- 步骤指示条 ---- */
.ab-fp-steps{
display:flex;
align-items:center;
justify-content:center;
gap:6px;
margin: 2px 0 18px;
font-size: 11.5px;
font-weight: 500;
letter-spacing: .02em;
color: var(--lb-on-surface-muted);
animation: lb-fade-up .5s var(--lb-easing) backwards;
}

.ab-fp-steps .ab-fp-step{
display:inline-flex;
align-items:center;
gap:5px;
padding: 4px 10px;
border-radius: 100px;
background: var(--lb-field-bg);
border: 1px solid var(--lb-field-outline);
white-space: nowrap;
}

.ab-fp-steps .ab-fp-step.is-active{
background: color-mix(in srgb, var(--lb-accent) 14%, transparent);
border-color: color-mix(in srgb, var(--lb-accent) 40%, transparent);
color: var(--lb-accent);
font-weight: 600;
}

.ab-fp-steps .ab-fp-step.is-done{
color: var(--lb-accent);
}

.ab-fp-steps .ab-fp-step .ab-fp-ico{
width: 13px;
height: 13px;
}

.ab-fp-steps .ab-fp-step-sep{
opacity: .45;
}

/* ---- 提示条 ---- */
.ab-fp-note{
display:flex;
align-items:flex-start;
gap: 9px;
box-sizing: border-box;
margin: 0 0 16px;
padding: 11px 14px;
border-radius: 14px;
font-size: 12.5px;
line-height: 1.7;
background: var(--lb-field-bg);
border: 1px solid var(--lb-field-outline);
color: var(--lb-on-surface);
animation: lb-fade-up .5s var(--lb-easing) backwards;
}

.ab-fp-note > .ab-fp-ico{
flex: none;
width: 17px;
height: 17px;
margin-top: 2.5px;
color: var(--lb-accent);
}

.ab-fp-note--error{
background: color-mix(in srgb, #b3261e 10%, transparent);
border-color: color-mix(in srgb, #b3261e 32%, transparent);
color: #b3261e;
}

.ab-fp-note--error > .ab-fp-ico{ color: #b3261e; }

.ab-fp-note--ok{
background: color-mix(in srgb, #2e7d32 12%, transparent);
border-color: color-mix(in srgb, #2e7d32 34%, transparent);
color: #2e7d32;
}

.ab-fp-note--ok > .ab-fp-ico{ color: #2e7d32; }

html[data-lb-theme="dark"] .ab-fp-note--error{ color: #f2b8b5; }
html[data-lb-theme="dark"] .ab-fp-note--ok{ color: #81c784; }

.ab-fp-note b{ font-weight: 600; }

.ab-fp-note code{
font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
font-size: 11.5px;
padding: 1px 5px;
border-radius: 6px;
background: color-mix(in srgb, var(--lb-on-surface) 8%, transparent);
word-break: break-all;
}

/* ---- 两种验证方式入口 ---- */
.ab-fp-methods{
display:flex;
flex-direction:column;
gap: 10px;
margin: 0 0 4px;
animation: lb-fade-up .5s var(--lb-easing) .04s backwards;
}

.ab-fp-method{
display:flex;
align-items:center;
gap: 12px;
box-sizing: border-box;
width: 100%;
padding: 13px 15px;
border-radius: 16px;
border: 1px solid var(--lb-field-outline);
background: var(--lb-field-bg);
color: var(--lb-on-surface) !important;
text-decoration: none !important;
font-family: inherit;
font-size: 13px;
text-align: left;
cursor: pointer;
transition: border-color .2s var(--lb-easing), background-color .2s var(--lb-easing), transform .2s var(--lb-easing);
}

.ab-fp-method:hover{
border-color: var(--lb-accent);
background: var(--lb-field-bg-hover);
transform: translateY(-1px);
}

.ab-fp-method .ab-fp-method-icon{
flex: none;
display: inline-flex;
align-items: center;
justify-content: center;
width: 40px;
height: 40px;
border-radius: 13px;
background: color-mix(in srgb, var(--lb-accent) 14%, transparent);
color: var(--lb-accent) !important;
}

.ab-fp-method .ab-fp-method-icon .ab-fp-ico{
width: 21px;
height: 21px;
}

.ab-fp-method .ab-fp-method-text{
display: flex;
flex-direction: column;
gap: 2px;
min-width: 0;
}

.ab-fp-method .ab-fp-method-title{
font-size: 13.5px;
font-weight: 600;
color: var(--lb-on-surface);
}

.ab-fp-method .ab-fp-method-desc{
font-size: 11.5px;
line-height: 1.55;
color: var(--lb-on-surface-muted);
}

.ab-fp-method .ab-fp-method-arrow{
flex: none;
margin-left: auto;
width: 18px;
height: 18px;
color: var(--lb-on-surface-muted);
}

/* ---- 请求进行中（发信要等 SMTP，必须给出反馈并挡住连点）---- */
.ab-fp-method:disabled{
cursor: progress;
}

.ab-fp-method:disabled:not(.is-loading){
opacity: .5;
transform: none;
}

.ab-fp-method:disabled:not(.is-loading):hover{
border-color: var(--lb-field-outline);
background: var(--lb-field-bg);
}

.ab-fp-method.is-loading{
opacity: 1;
cursor: progress;
border-color: var(--lb-accent);
background: var(--lb-field-bg-hover);
transform: none;
animation: lb-btn-breathe 1.8s ease-in-out infinite;
}

.ab-fp-method.is-loading .ab-fp-method-arrow{
display: none;
}

.ab-fp-method.is-loading .ab-fp-method-title{
color: var(--lb-accent);
}

.ab-fp-method .ab-fp-spinner{
margin-left: auto;
}

/* 通用转圈指示器（卡片 / 次级按钮共用） */
.ab-fp-spinner{
flex: none;
display: block;
width: 18px;
height: 18px;
box-sizing: border-box;
border-radius: 50%;
border: 2px solid color-mix(in srgb, var(--lb-accent) 28%, transparent);
border-top-color: var(--lb-accent);
animation: lb-btn-spin .72s linear infinite;
}

.ab-fp-links button:disabled{
opacity: .5;
cursor: progress;
}

.ab-fp-links button.is-loading{
opacity: 1;
cursor: progress;
color: var(--lb-accent);
}

.ab-fp-links button.is-loading .ab-fp-ico{
display: none;
}

.ab-fp-links button .ab-fp-spinner{
width: 15px;
height: 15px;
border-width: 1.8px;
}

/* ---- 本地文件验证：验证串展示 ---- */
.ab-fp-token{
display: flex;
align-items: center;
gap: 10px;
box-sizing: border-box;
margin: 0 0 14px;
padding: 13px 14px;
border-radius: 14px;
background: var(--lb-field-bg);
border: 1px dashed color-mix(in srgb, var(--lb-accent) 45%, transparent);
}

.ab-fp-token-val{
flex: 1 1 auto;
min-width: 0;
font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
font-size: 13px;
font-weight: 600;
letter-spacing: .06em;
line-height: 1.5;
color: var(--lb-accent);
word-break: break-all;
user-select: all;
-webkit-user-select: all;
}

.ab-fp-copy{
flex: none;
display: inline-flex;
align-items: center;
justify-content: center;
width: 34px;
height: 34px;
padding: 0;
border: 1px solid var(--lb-field-outline);
border-radius: 11px;
background: transparent;
color: var(--lb-on-surface-muted);
cursor: pointer;
transition: border-color .2s var(--lb-easing), color .2s var(--lb-easing);
}

.ab-fp-copy:hover{
border-color: var(--lb-accent);
color: var(--lb-accent);
}

.ab-fp-copy .ab-fp-ico{
width: 17px;
height: 17px;
color: inherit;
}

.ab-fp-validity{
display: flex;
align-items: center;
gap: 8px;
box-sizing: border-box;
margin: 0 0 16px;
padding: 10px 13px;
border-radius: 14px;
background: color-mix(in srgb, var(--lb-accent) 8%, transparent);
border: 1px solid color-mix(in srgb, var(--lb-accent) 24%, transparent);
}

.ab-fp-validity > .ab-fp-ico{
width: 16px;
height: 16px;
color: var(--lb-accent);
}

.ab-fp-validity-label{
font-size: 12.5px;
color: var(--lb-on-surface-muted);
}

.ab-fp-validity-left{
margin-left: auto;
font-size: 13px;
font-weight: 600;
font-variant-numeric: tabular-nums;
color: var(--lb-accent);
}

.ab-fp-validity.is-expired{
background: color-mix(in srgb, #b3261e 10%, transparent);
border-color: color-mix(in srgb, #b3261e 32%, transparent);
}

.ab-fp-validity.is-expired > .ab-fp-ico,
.ab-fp-validity.is-expired .ab-fp-validity-left{
color: #b3261e;
}

html[data-lb-theme="dark"] .ab-fp-validity.is-expired .ab-fp-validity-left{
color: #f2b8b5;
}

/* ---- 本地文件验证：文件名提示 ---- */
.ab-fp-file-names{
display: flex;
gap: 10px;
box-sizing: border-box;
margin: 0 0 14px;
padding: 11px 13px;
border-radius: 14px;
background: var(--lb-field-bg);
border: 1px solid var(--lb-field-outline);
}

.ab-fp-file-names-icon{
width: 18px;
height: 18px;
margin-top: 2px;
color: var(--lb-accent);
}

.ab-fp-file-names-body{
min-width: 0;
}

.ab-fp-file-names-title{
font-size: 11.5px;
color: var(--lb-on-surface-muted);
margin-bottom: 5px;
}

.ab-fp-file-name{
display: block;
font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
font-size: 12px;
line-height: 1.9;
color: var(--lb-accent);
word-break: break-all;
user-select: all;
-webkit-user-select: all;
}

/* ---- 底部次要操作（返回上一步 / 重新开始）---- */
.ab-fp-links{
display: flex;
align-items: center;
justify-content: center;
flex-wrap: wrap;
gap: 6px 16px;
margin: 16px 0 0;
font-size: 12.5px;
animation: lb-fade-up .5s var(--lb-easing) .18s backwards;
}

.ab-fp-links a,
.ab-fp-links button{
display: inline-flex;
align-items: center;
gap: 5px;
padding: 0;
border: 0;
background: transparent;
color: var(--lb-accent) !important;
font-family: inherit;
font-size: 12.5px;
font-weight: 500;
text-decoration: none !important;
cursor: pointer;
}

.ab-fp-links .ab-fp-ico{
width: 15px;
height: 15px;
color: inherit;
}

.ab-fp-links a:hover,
.ab-fp-links button:hover{
text-decoration: underline !important;
text-underline-offset: 3px;
}

/* ---- 完成页 ---- */
.ab-fp-done{
display: flex;
flex-direction: column;
align-items: center;
gap: 10px;
padding: 6px 0 2px;
text-align: center;
animation: lb-fade-up .5s var(--lb-easing) backwards;
}

.ab-fp-done-icon{
display: inline-flex;
align-items: center;
justify-content: center;
width: 62px;
height: 62px;
border-radius: 50%;
background: color-mix(in srgb, #2e7d32 14%, transparent);
color: #2e7d32;
animation: lb-badge-pop .6s var(--lb-easing-spring) backwards;
}

.ab-fp-done-icon .ab-fp-ico{
width: 32px;
height: 32px;
color: inherit;
}

html[data-lb-theme="dark"] .ab-fp-done-icon{ color: #81c784; }

.ab-fp-done-text{
margin: 0;
font-size: 13.5px;
line-height: 1.7;
color: var(--lb-on-surface);
}

.ab-fp-done-hint{
margin: 0;
font-size: 12px;
line-height: 1.6;
color: var(--lb-on-surface-muted);
}

@media (max-width: 600px){
  .ab-fp-method{ padding: 12px 13px; }
  .ab-fp-token{ padding: 11px 12px; }
  .ab-fp-token-val{ font-size: 12px; }
}

/* ================================================================
   忘记密码接管登录卡片时的微调
   （界面是注入进登录页卡片的，所以这里只处理「与登录态不同」的地方）
   ================================================================ */

/* 「下次自动登录」是登录页专属选项，找回密码流程里不应出现 */
.ab-fp-active .lb-remember{
display: none !important;
}

/* 接管后 .more-link 里只剩「返回首页」，让它保持居中 */
.ab-fp-active .lb-more-link{
margin-top: 18px;
}

/* ---- Cloudflare Turnstile（登录 / 注册 / 忘记密码 共用；位于提交按钮之上）---- */
.ab-turnstile-wrap{
box-sizing: border-box;
width: 100%;
margin: 0 0 18px;
display: flex;
flex-direction: column;
align-items: center;
}

.ab-turnstile-cap{
display: inline-flex;
align-items: center;
gap: 6px;
margin: 0 0 10px;
font-size: 12px;
font-weight: 500;
letter-spacing: .01em;
color: var(--lb-on-surface-muted);
}

.ab-turnstile-cap .ab-fp-ico{
width: 15px;
height: 15px;
color: var(--lb-accent);
}

.ab-turnstile-box{
width: 100%;
min-height: 65px;
display: flex;
align-items: center;
justify-content: center;
}

.ab-turnstile-box > *{
max-width: 100%;
}

.ab-turnstile-error{
display: none;
width: 100%;
box-sizing: border-box;
margin-top: 10px;
padding: 8px 12px;
border-radius: 10px;
background: color-mix(in srgb, #b3261e 10%, transparent);
border: 1px solid color-mix(in srgb, #b3261e 32%, transparent);
color: #b3261e;
font-size: 12.5px;
font-weight: 600;
text-align: center;
}

/* 只是在等组件算令牌 → 中性提示色，不是错误 */
.ab-turnstile-error.is-info{
background: color-mix(in srgb, var(--lb-accent) 8%, transparent);
border-color: color-mix(in srgb, var(--lb-accent) 24%, transparent);
color: var(--lb-on-surface-muted);
}

html[data-lb-theme="dark"] .ab-turnstile-error{
color: #f2b8b5;
}

html[data-lb-theme="dark"] .ab-turnstile-error.is-info{
color: var(--lb-on-surface-muted);
}

.ab-turnstile-retry{
display: none;
margin: 8px auto 0;
padding: 6px 14px;
border: 1px solid currentColor;
border-radius: 999px;
background: transparent;
color: inherit;
font: inherit;
font-size: 12px;
font-weight: 600;
line-height: 1.4;
cursor: pointer;
transition: background-color .2s var(--lb-easing);
}

.ab-turnstile-retry:hover{
background: color-mix(in srgb, currentColor 12%, transparent);
}
</style>
