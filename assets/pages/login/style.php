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

<?php
/**
 * 主色转 "r,g,b"：用于 rgba() 光晕 / 渐变阴影。
 * 非 hex（用户填了 rgb(...) 或颜色名）时回退到默认紫罗兰。
 */
$lbHexToRgb = function ($hex, $fallback) {
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return $fallback;
    }
    return hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
};
$lbPrimaryRgb  = $lbHexToRgb($primary, '125,82,96');
$lbPrimary2Rgb = $lbHexToRgb($primary2, '158,123,138');

/* 深色模式下把主色往白色方向提亮（MD3 深色主题使用浅色调），
 * 否则标签 / 聚焦描边在深色卡片上对比度不足。 */
$lbMixWhite = function ($rgb, $ratio) {
    $out = array();
    foreach (explode(',', $rgb) as $c) {
        $c = (int) $c;
        $out[] = (int) round($c + (255 - $c) * $ratio);
    }
    return implode(',', $out);
};
$lbRgbToHex = function ($rgb) {
    $p = array_map('intval', explode(',', $rgb));
    return sprintf('#%02x%02x%02x', $p[0], $p[1], $p[2]);
};
$lbAccentDarkRgb = $lbMixWhite($lbPrimaryRgb, 0.44);
$lbAccentDark    = $lbRgbToHex($lbAccentDarkRgb);

$lbHasBgImage  = (stripos((string) $bgCss, 'url(') !== false);
?>

<style id="loginbeautify-style">
:root{
--lb-primary:<?php echo htmlspecialchars($primary, ENT_QUOTES, 'UTF-8'); ?>;
--lb-primary2:<?php echo htmlspecialchars($primary2, ENT_QUOTES, 'UTF-8'); ?>;
--lb-primary-rgb:<?php echo $lbPrimaryRgb; ?>;
--lb-primary2-rgb:<?php echo $lbPrimary2Rgb; ?>;
/* 强调色：亮色=主色，深色=提亮版（对比度更佳） */
--lb-accent:var(--lb-primary);
--lb-accent-rgb:var(--lb-primary-rgb);
--lb-surface:#f3f4f5;
--lb-surface-alpha:rgba(255,255,255,.8);
--lb-on-surface:#111827;
--lb-on-surface-muted:#4b5563;
--lb-border:rgba(0,0,0,.08);
--lb-shadow: 0 20px 40px -10px rgba(0,0,0,.15), 0 0 0 1px rgba(255,255,255,.4) inset;
--lb-radius: 24px;
--lb-input-bg: rgba(255,255,255,.8);
--lb-input-border: #e5e7eb;
--lb-bg-image: <?php echo $bgCss; ?>;
--lb-blur: <?php echo (int) $blurSize; ?>px;

/* MD3 文本输入框令牌（四角大圆角 + 描边） */
--lb-field-autofill: #fff;
/* 底色用「纯色半透明」而非渐变：渐变不能插值，聚焦/失焦会瞬间跳变 */
/* 磨砂玻璃内的输入框不加 backdrop-filter（嵌套背景模糊会发浑且掉帧）。
   底色的 opacity 也不要过低，否则读不清背景。 */
--lb-field-bg: rgba(255,255,255,.55);
--lb-field-bg-hover: rgba(255,255,255,.66);
--lb-field-bg-focus: rgba(255,255,255,.82);
--lb-field-outline: rgba(17,24,39,.10);
--lb-field-outline-hover: rgba(17,24,39,.20);
--lb-field-rim: rgba(255,255,255,.70);
--lb-field-radius: 18px;

/* ---- 磨砂玻璃材质（成熟方案：半透明纯色底 + 背景模糊 + 细描边，无多层光效/无投影） ---- */
--lb-glass-bg: rgba(255,255,255,.72);
--lb-glass-border: rgba(255,255,255,.55);
/* 方向性描边：左上亮、中段暗、右下回一半，做出玻璃边缘的折射质感 */
--lb-glass-edge-1: rgba(255,255,255,.92);
--lb-glass-edge-2: rgba(255,255,255,.22);
--lb-glass-edge-3: rgba(255,255,255,.52);
--lb-glass-blur: 20px;
--lb-glass-saturate: 160%;
--lb-btn-spec: rgba(255,255,255,.30);

/* 动效曲线：emphasized decelerate + 微回弹 */
--lb-easing: cubic-bezier(.2,0,0,1);
--lb-easing-spring: cubic-bezier(.22,1.12,.36,1);
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

/* 深色模式强调色：主色提亮 44%（MD3 深色主题使用浅色调） */
--lb-accent: <?php echo $lbAccentDark; ?>;
--lb-accent-rgb: <?php echo $lbAccentDarkRgb; ?>;

/* MD3 文本输入框（深色） */
--lb-field-autofill: #2a2a2e;
--lb-field-bg: rgba(255,255,255,.07);
--lb-field-bg-hover: rgba(255,255,255,.10);
--lb-field-bg-focus: rgba(255,255,255,.14);
--lb-field-outline: rgba(255,255,255,.14);
--lb-field-outline-hover: rgba(255,255,255,.26);
--lb-field-rim: rgba(255,255,255,.16);

/* 磨砂玻璃（深色）：底色偏冷暗，避免发白 */
--lb-glass-bg: rgba(30,28,36,.74);
--lb-glass-border: rgba(255,255,255,.12);
--lb-glass-edge-1: rgba(255,255,255,.26);
--lb-glass-edge-2: rgba(255,255,255,.04);
--lb-glass-edge-3: rgba(255,255,255,.12);
--lb-glass-blur: 18px;
--lb-glass-saturate: 150%;
--lb-btn-spec: rgba(255,255,255,.14);
}

html{
transition: background-color .3s ease, color .3s ease;
overflow-x: hidden;
}

body{
margin:0;
background: var(--lb-surface);
color: var(--lb-on-surface);
font-family: system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
transition: background-color .3s ease, color .3s ease;
overflow-x: hidden;
}

/* ---- 无背景图时的氛围光斑（提升留白质感） ---- */
<?php if (!$lbHasBgImage) { ?>
/* 给玻璃一个可折射的底色（纯平色下看起来就是一块白板） */
body{
background:
  radial-gradient(120% 90% at 12% 0%, rgba(var(--lb-primary-rgb),.12), rgba(var(--lb-primary-rgb),0) 56%),
  radial-gradient(110% 85% at 100% 100%, rgba(var(--lb-primary2-rgb),.16), rgba(var(--lb-primary2-rgb),0) 62%),
  var(--lb-surface);
}
body::before,
body::after{
content: '';
position: fixed;
z-index: -3;
border-radius: 50%;
pointer-events: none;
}
body::before{
width: 46vmax;
height: 46vmax;
left: -14vmax;
top: -16vmax;
background: radial-gradient(circle at 30% 30%, rgba(var(--lb-primary-rgb),.55), rgba(var(--lb-primary-rgb),0) 68%);
filter: blur(28px);
}
body::after{
width: 42vmax;
height: 42vmax;
right: -14vmax;
bottom: -16vmax;
background: radial-gradient(circle at 65% 65%, rgba(var(--lb-primary2-rgb),.5), rgba(var(--lb-primary2-rgb),0) 68%);
filter: blur(30px);
}
html[data-lb-theme="dark"] body::before{
background: radial-gradient(circle at 30% 30%, rgba(var(--lb-primary-rgb),.5), rgba(var(--lb-primary-rgb),0) 70%);
}
html[data-lb-theme="dark"] body::after{
background: radial-gradient(circle at 65% 65%, rgba(var(--lb-primary2-rgb),.42), rgba(var(--lb-primary2-rgb),0) 70%);
}
/* 不动作：背景一动，卡片的 backdrop-filter 就要每帧重算，中低端机型会掉帧 */
<?php } ?>

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

/* ================================================================
   磨砂玻璃卡片（frosted glass）
   - 半透明纯色底（padding-box）+ backdrop-filter 模糊/提饱和
   - 描边用「1px 透明边 + border-box 方向性渐变」：左上亮、右下渐隐，
     比纯色 1px 线更有玻璃边缘的折射质感，且宽度严格一致
   - 不投影（背后无阴影）；入场动画用 backwards 填充，
     结束后不保留 transform（transform + backdrop-filter 易产生合成残影）
   ================================================================ */
.lb-card{
position: relative;   /* 移动端主题按钮需绝对定位到卡片右上角 */
width:min(400px, 94%);
border: 1px solid transparent;
background:
  linear-gradient(var(--lb-glass-bg), var(--lb-glass-bg)) padding-box,
  linear-gradient(152deg,
    var(--lb-glass-edge-1) 0%,
    var(--lb-glass-edge-2) 42%,
    var(--lb-glass-edge-3) 100%) border-box;
color: var(--lb-on-surface);
border-radius: var(--lb-radius);
box-shadow: none;
padding: 36px 32px 30px;
backdrop-filter: blur(var(--lb-glass-blur)) saturate(var(--lb-glass-saturate));
-webkit-backdrop-filter: blur(var(--lb-glass-blur)) saturate(var(--lb-glass-saturate));
animation: lb-card-in .62s var(--lb-easing-spring) backwards;
}

/* ---- 卡片入场 / 元素依次浮现 ---- */
@keyframes lb-card-in{
from{ opacity: 0; transform: translateY(22px) scale(.965); }
to{ opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes lb-fade-up{
from{ opacity: 0; transform: translateY(11px); }
to{ opacity: 1; transform: translateY(0); }
}

<?php if ($blurType === 'backdrop') { ?>
.lb-card{
backdrop-filter: blur(var(--lb-blur)) saturate(var(--lb-glass-saturate));
-webkit-backdrop-filter: blur(var(--lb-blur)) saturate(var(--lb-glass-saturate));
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
margin-bottom: 22px;
animation: lb-fade-up .5s var(--lb-easing) .04s backwards;
}

/* 顶部图标徽章：MD3 tonal 圆形 + 渐变主色 */
.lb-badge{
width: 58px;
height: 58px;
margin: 0 auto 14px;
display:flex;
align-items:center;
justify-content:center;
border-radius: 20px;
color: #fff;
background: linear-gradient(135deg, var(--lb-primary), var(--lb-primary2));
box-shadow: 0 10px 22px -10px rgba(var(--lb-primary-rgb),.85), inset 0 1px 0 rgba(255,255,255,.35);
animation: lb-badge-pop .68s var(--lb-easing-spring) backwards;
}

.lb-badge svg{
width: 27px;
height: 27px;
}

@keyframes lb-badge-pop{
from{ opacity: 0; transform: scale(.62) rotate(-10deg); }
60%{ opacity: 1; }
to{ opacity: 1; transform: scale(1) rotate(0); }
}

.lb-title{
display:flex;
flex-direction:column;
gap:6px;
width:100%;
}

.lb-title .name{
font-size: 15px;
font-weight: 500;
letter-spacing: .01em;
color: var(--lb-on-surface-muted);
}

.lb-title .sub{
font-size: 27px;
font-weight: 800;
letter-spacing: -0.03em;
line-height: 1.25;
color: var(--lb-on-surface);
margin-bottom: 6px;
}

/* ================================================================
   MD3 Filled Text Field（浮动标签 + 激活指示线）
   - 未启用 JS 时回退为「标签在上、输入框在下」的经典布局
   - JS 生效时字段容器会带上 .lb-field--md3
   ================================================================ */

/* ---- 回退布局（JS 未生效时） ---- */
.lb-form .lb-field{
margin-top: 16px;
}

.lb-form .lb-field > label,
.lb-form .lb-field > .lb-field-label{
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
border-radius: 14px;
border: 1px solid var(--lb-input-border);
background: var(--lb-input-bg);
color: var(--lb-on-surface);
font-size: 14px;
outline: none;
transition: border-color .18s var(--lb-easing), background-color .18s var(--lb-easing), box-shadow .18s var(--lb-easing);
}

html[data-lb-theme="dark"] .lb-form input[type="text"],
html[data-lb-theme="dark"] .lb-form input[type="password"]{
background: rgba(255,255,255,.06);
}

.lb-form input[type="text"]:focus,
.lb-form input[type="password"]:focus{
border-color: var(--lb-accent);
background: var(--lb-surface);
box-shadow: 0 0 0 3.5px rgba(var(--lb-accent-rgb),.16);
}

/* ================================================================
   MD3 文本域：四角大圆角描边 + 浮动标签 + 弹簧聚焦光环
   - 未启用 JS 时回退为「标签在上、输入框在下」的经典布局
   - JS 生效时字段容器会带上 .lb-field--md3
   ================================================================ */

/* ---- 字段容器 ---- */
.lb-form .lb-field--md3{
position: relative;
margin-top: 20px;
animation: lb-fade-up .5s var(--lb-easing) backwards;
}

/* ---- 浮动标签（只动 transform：不触碰 top / padding 等布局属性） ---- */
.lb-form .lb-field--md3 > label,
.lb-form .lb-field--md3 > .lb-field-label{
position: absolute;
left: 18px;
right: 18px;
top: 50%;                 /* 固定不动，仅作基准 */
z-index: 2;
margin: 0;
padding: 0;
font-size: 15px;
font-weight: 400;
line-height: 20px;
letter-spacing: .01em;
color: var(--lb-on-surface-muted);
pointer-events: none;
white-space: nowrap;
overflow: hidden;
text-overflow: ellipsis;
transform: translateY(-50%) translateY(0) scale(1);
transform-origin: left center;
will-change: transform;
transition: transform .3s var(--lb-easing-spring),
            color .22s var(--lb-easing);
}

/* 聚焦 / 已填写 → 标签上浮并缩小（位移 -16px，纯 transform 实现） */
.lb-form .lb-field--md3.is-focused > label,
.lb-form .lb-field--md3.is-filled > label,
.lb-form .lb-field--md3.is-focused > .lb-field-label,
.lb-form .lb-field--md3.is-filled > .lb-field-label{
transform: translateY(-50%) translateY(-16px) scale(.74);
}

.lb-form .lb-field--md3.is-focused > label,
.lb-form .lb-field--md3.is-focused > .lb-field-label{
color: var(--lb-accent);
}

/* ---- 输入框本体：四角大圆角 + 1px 内高光（磨砂玻璃内的半透明面） ---- */
.lb-form .lb-field--md3 input[type="text"],
.lb-form .lb-field--md3 input[type="password"],
.lb-form .lb-field--md3 input[type="email"]{
height: 58px;
padding: 24px 18px 10px;
/* ★ 描边宽度恒定 1px（整数，任何 DPR 下四边一致）：
   之前用 1.5px + 聚焦再叠一层 2px 光环，会出现「四边粗细不一 / 状态间不一致」，
   现在只在「颜色 + 外圈阴影」上做状态区分，几何完全不动 */
border: 1px solid var(--lb-field-outline);
border-radius: var(--lb-field-radius);
background-color: var(--lb-field-bg);
color: var(--lb-on-surface);
font-size: 15px;
line-height: 20px;
caret-color: var(--lb-accent);
box-shadow: inset 0 1px 0 var(--lb-field-rim);
/* ★ 只过渡「颜色 / 阴影」这类绘制属性：
   不含 padding、width、height、border-width 等布局属性，避免聚焦时出现位移/抖动 */
transition: border-color .45s var(--lb-easing),
            background-color .45s var(--lb-easing),
            box-shadow .6s var(--lb-easing);
}

/* 悬停（仍为 1px，仅换色） */
.lb-form .lb-field--md3:hover input[type="text"],
.lb-form .lb-field--md3:hover input[type="password"],
.lb-form .lb-field--md3:hover input[type="email"]{
border-color: var(--lb-field-outline-hover);
background-color: var(--lb-field-bg-hover);
}

/* 聚焦：描边转强调色（宽度不变）+ 外圈柔光 */
.lb-form .lb-field--md3.is-focused input[type="text"],
.lb-form .lb-field--md3.is-focused input[type="password"],
.lb-form .lb-field--md3.is-focused input[type="email"]{
border-color: var(--lb-accent);
background-color: var(--lb-field-bg-focus);
box-shadow: inset 0 1px 0 var(--lb-field-rim),
            0 0 0 3px rgba(var(--lb-accent-rgb),.14);
outline: none;
}

.lb-form .lb-field--md3 input[type="text"]:focus,
.lb-form .lb-field--md3 input[type="password"]:focus,
.lb-form .lb-field--md3 input[type="email"]:focus{
outline: none;
}

/* 浏览器自动填充：去掉黄色底色，并保证文字与标签颜色一致 */
.lb-form .lb-field--md3 input:-webkit-autofill,
.lb-form .lb-field--md3 input:-webkit-autofill:hover,
.lb-form .lb-field--md3 input:-webkit-autofill:focus{
-webkit-box-shadow: 0 0 0 1000px var(--lb-field-autofill) inset;
-webkit-text-fill-color: var(--lb-on-surface);
caret-color: var(--lb-accent);
}

/* 校验失败字段（Typecho 服务端错误回显时可手动加 .is-error） */
.lb-form .lb-field--md3.is-error > label,
.lb-form .lb-field--md3.is-error > .lb-field-label{
color: #b3261e;
}

.lb-form .lb-field--md3.is-error input{
border-color: #b3261e;
animation: lb-shake .42s var(--lb-easing);
}

@keyframes lb-shake{
0%, 100%{ transform: translateX(0); }
18%{ transform: translateX(-5px); }
38%{ transform: translateX(4px); }
58%{ transform: translateX(-3px); }
78%{ transform: translateX(2px); }
}

.lb-actions{
display:flex;
align-items:center;
justify-content:space-between;
gap:12px;
margin: 12px 0 6px;
}

/* ================================================================
   「下次自动登录」（Typecho 原生 <input name="remember">）
   - 对应 Widget_Login 里的 $this->request->is('remember=1')，
     勾选时会把登录 Cookie 的过期时间延长到 30 天，必须保留在表单里并允许提交
   - 视觉上改造成 MD3 switch：原生 checkbox 加 appearance:none 重绘轨道 + 滑块，
     表单语义 / 键盘操作 / 读屏朗读全部沿用原生控件，不额外造交互
   ================================================================ */
.lb-remember{
display:flex;
align-items:center;
margin: 16px 0 0;
font-size: 13px;
color: var(--lb-on-surface-muted);
animation: lb-fade-up .5s var(--lb-easing) backwards;
}

.lb-remember > label{
display:inline-flex;
align-items:center;
gap:10px;
margin:0;
cursor:pointer;
line-height:1.45;
color: inherit;
user-select:none;
-webkit-user-select:none;
-webkit-tap-highlight-color: transparent;
}

/* 轨道：用字段描边/底色令牌，亮暗两套主题自动跟随 */
.lb-remember input[type="checkbox"]{
appearance:none;
-webkit-appearance:none;
flex:none;
position:relative;
width:36px;
height:20px;
margin:0;
padding:0;
box-sizing:border-box;
border-radius:100px;
border:1px solid var(--lb-field-outline-hover);
background: var(--lb-field-bg);
cursor:pointer;
transition: background-color .2s var(--lb-easing), border-color .2s var(--lb-easing);
}

/* 滑块 */
.lb-remember input[type="checkbox"]::after{
content:'';
position:absolute;
top:50%;
left:3px;
width:14px;
height:14px;
border-radius:50%;
background: var(--lb-on-surface-muted);
transform: translateY(-50%);
transition: transform .22s var(--lb-easing-spring), background-color .2s var(--lb-easing);
}

.lb-remember input[type="checkbox"]:checked{
background: var(--lb-accent);
border-color: var(--lb-accent);
}

.lb-remember input[type="checkbox"]:checked::after{
background:#fff;
transform: translate(16px, -50%);
}

.lb-remember input[type="checkbox"]:hover{
border-color: var(--lb-accent);
}

.lb-remember input[type="checkbox"]:focus-visible{
outline: 3px solid rgba(var(--lb-accent-rgb),.28);
outline-offset: 2px;
}

.lb-remember input[type="checkbox"]:disabled{
opacity:.5;
cursor:default;
}

/* 不支持 appearance:none 的老浏览器：退回原生复选框，至少保证选项可用 */
@supports not (appearance: none){
.lb-remember input[type="checkbox"]{
width:auto;
height:auto;
border:0;
background:none;
accent-color: var(--lb-accent);
}
.lb-remember input[type="checkbox"]::after{ content:none; }
}

/* ================================================================
   MD3 Filled Button：状态层 + 涟漪 + 加载动画
   ================================================================ */
.lb-submit{
position: relative;
margin-top: 24px;
animation: lb-fade-up .5s var(--lb-easing) backwards;
}

.lb-submit input[type="submit"],
.lb-submit button{
position: relative;
overflow: hidden;
display: flex;
align-items: center;
justify-content: center;
width:100%;
height: 54px;
margin: 0;
padding: 0 24px;
border:0;
cursor:pointer;
border-radius: 100px;
font-family: inherit;
font-size: 15px;
font-weight:600;
line-height: 1;
letter-spacing:0.03em;
color:#fff;
/* 三停渐变 + 悬停时位移 → 自带一道扫光，无需额外元素 */
background: linear-gradient(115deg, var(--lb-primary) 0%, var(--lb-primary2) 48%, var(--lb-primary) 100%);
background-size: 220% 100%;
background-position: 0% 50%;
box-shadow: 0 10px 24px -12px rgba(var(--lb-primary-rgb),.95),
            0 3px 8px -3px rgba(0,0,0,.26),
            inset 0 1px 0 rgba(255,255,255,.42),
            inset 0 -1px 0 rgba(0,0,0,.10);
transform: scale(1);
-webkit-tap-highlight-color: transparent;
transition: box-shadow .28s var(--lb-easing),
            transform .2s var(--lb-easing-spring),
            background-position .7s var(--lb-easing),
            opacity .2s var(--lb-easing);
}

/* 静态镜面光泽（固定位置；不做鼠标跟随 —— 跟随需要每帧重画笔大渐变，会明显卡顿） */
.lb-submit input[type="submit"]::before,
.lb-submit button::before{
content: '';
position: absolute;
inset: 0;
z-index: 1;
border-radius: inherit;
background: linear-gradient(168deg,
  var(--lb-btn-spec) 0%,
  rgba(255,255,255,.06) 40%,
  rgba(255,255,255,0) 64%);
pointer-events: none;
}

/* MD3 状态层：hover 8% / focus 12% / pressed 16% */
.lb-submit input[type="submit"]::after,
.lb-submit button::after{
content: '';
position: absolute;
inset: 0;
z-index: 3;
background: #fff;
opacity: 0;
pointer-events: none;
border-radius: inherit;
transition: opacity .15s var(--lb-easing);
}

.lb-submit input[type="submit"]:hover::after,
.lb-submit button:hover::after{ opacity: .08; }

.lb-submit button:hover{
background-position: 100% 50%;
transform: translateY(-1px);
box-shadow: 0 16px 32px -14px rgba(var(--lb-primary-rgb),1),
            0 4px 10px -3px rgba(0,0,0,.3),
            inset 0 1px 0 rgba(255,255,255,.32);
}

.lb-submit input[type="submit"]:focus-visible::after,
.lb-submit button:focus-visible::after{ opacity: .12; }

.lb-submit input[type="submit"]:active,
.lb-submit button:active{
transform: scale(.972);
transition-duration: .09s;
box-shadow: 0 4px 12px -8px rgba(var(--lb-primary-rgb),.9),
            0 1px 3px rgba(0,0,0,.2);
}

/* 按压涟漪 */
.lb-ripple{
position: absolute;
z-index: 0;
border-radius: 50%;
background: #fff;
opacity: .3;
transform: scale(0);
pointer-events: none;
animation: lb-ripple-out .6s var(--lb-easing) forwards;
}

@keyframes lb-ripple-out{
to{ transform: scale(2.4); opacity: 0; }
}

/* 按钮文字 */
.lb-btn-label{
position: relative;
z-index: 2;
display: inline-block;
transition: opacity .2s var(--lb-easing), transform .32s var(--lb-easing-spring);
}

/* 加载指示器 */
.lb-btn-spinner{
position: absolute;
z-index: 2;
left: 50%;
top: 50%;
width: 21px;
height: 21px;
margin: -10.5px 0 0 -10.5px;
border-radius: 50%;
border: 2.5px solid rgba(255,255,255,.28);
border-top-color: #fff;
border-right-color: rgba(255,255,255,.8);
opacity: 0;
transform: scale(.55);
pointer-events: none;
transition: opacity .2s var(--lb-easing), transform .34s var(--lb-easing-spring);
}

@keyframes lb-btn-spin{
from{ transform: rotate(0deg) scale(1); }
to{ transform: rotate(360deg) scale(1); }
}

/* 加载态 */
.lb-submit.is-loading input[type="submit"],
.lb-submit.is-loading button{
pointer-events: none;
cursor: progress;
background-position: 50% 50%;
box-shadow: 0 5px 14px -10px rgba(var(--lb-primary-rgb),.85),
            0 1px 3px rgba(0,0,0,.2);
animation: lb-btn-breathe 1.8s ease-in-out infinite;
}

@keyframes lb-btn-breathe{
0%, 100%{ transform: scale(.984); }
50%{ transform: scale(1); }
}

.lb-submit.is-loading input[type="submit"]::after,
.lb-submit.is-loading button::after{ opacity: 0; }

.lb-submit.is-loading .lb-btn-label{
opacity: 0;
transform: translateY(-10px) scale(.94);
}

.lb-submit.is-loading .lb-btn-spinner{
opacity: 1;
transform: scale(1);
animation: lb-btn-spin .72s linear infinite;
}

/* 成功态（可选，由脚本在跳转成功前短暂展示） */
.lb-submit.is-success input[type="submit"],
.lb-submit.is-success button{
background: linear-gradient(115deg, #10b981, #059669);
}

/* 无障碍：尊重系统「减少动态效果」设置 */
@media (prefers-reduced-motion: reduce){
.lb-card,
.lb-head,
.lb-badge,
.lb-more-link,
.lb-footer-theme,
.lb-submit,
.lb-submit input[type="submit"],
.lb-submit button,
.lb-form .lb-field--md3,
.lb-form .lb-field--md3 > label,
.lb-form .lb-field--md3 > .lb-field-label,
.lb-btn-label,
.lb-btn-spinner{
transition: none !important;
animation: none !important;
}
.lb-ripple{ display: none; }
}

/* ================================================================
   移动端适配（≤600px）
   - 卡片全屏：铺满视口、去圆角/描边、纵向居中
   - 主题切换按钮嵌进卡片右上角（由脚本把节点移入卡片）
   - 背景下模糊半径同步下调，降低手机端绘制开销
   ================================================================ */
@media (max-width: 600px){
/* 全屏卡片需要 wrap 拉伸且不裁剪，否则溢出部分会被 overflow:hidden 截掉 */
.lb-wrap{
align-items: stretch;
overflow: visible;
}
.lb-card{
position: relative;
width: 100%;
max-width: none;
min-height: 100vh;
min-height: 100dvh;
box-sizing: border-box;
border: 0;
border-radius: 0;
background: var(--lb-glass-bg);
padding: 58px 22px 92px;   /* 底部留出固定页脚的空间 */
backdrop-filter: blur(14px) saturate(var(--lb-glass-saturate));
-webkit-backdrop-filter: blur(14px) saturate(var(--lb-glass-saturate));
}
/* 注意：基础 .lb-theme-toggle 规则在本块之后，故这里用 .lb-card 提高权重，
   否则 position/top/尺寸仍然沿用桌面端的固定定位 */
.lb-card .lb-theme-toggle{
position: absolute;
right: 14px;
top: 14px;
width: 42px;
height: 42px;
backdrop-filter: blur(10px) saturate(var(--lb-glass-saturate));
-webkit-backdrop-filter: blur(10px) saturate(var(--lb-glass-saturate));
}
.lb-card .lb-theme-toggle svg{
width: 19px;
height: 19px;
}
.lb-head{
margin-bottom: 26px;
}
.lb-badge{
width: 52px;
height: 52px;
border-radius: 18px;
margin-bottom: 12px;
}
.lb-badge svg{
width: 24px;
height: 24px;
}
.lb-title .sub{
font-size: 24px;
}
.lb-form .lb-field--md3{
margin-top: 16px;
}
.lb-submit{
margin-top: 22px;
}
}

/* 不支持 backdrop-filter 时退化为高不透明度实色，保证可读性 */
@supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))){
:root{
--lb-glass-bg: rgba(255,255,255,.95);
}
html[data-lb-theme="dark"]{
--lb-glass-bg: rgba(24,22,30,.96);
}
.lb-theme-toggle{
background: rgba(255,255,255,.95);
}
html[data-lb-theme="dark"] .lb-theme-toggle{
background: rgba(24,22,30,.96);
}
}

/* ================================================================
   Typecho 1.3.0 的消息提示：.message.popup.success|notice|error
   来源：admin/common-js.php 在 $(document).ready 里读 __typecho_notice Cookie，
        针对登录页（无 .typecho-head-nav）执行 prependTo(document.body)：
          <div class="message popup error"><ul><li>…</li></ul></div>
   原生只给了 sticky / 居中 / 直角 / 无背景 —— 颜色仅靠 jQuery UI 的
   effect('highlight') 闪一下，闪完就只剩一行裸文字，登录页上很突兀。

   这里按 Material Design 3 重做成「带前置图标的 tonal 提示条」：
     · 错误 → error container（浅 #FFDAD6/#410E0B，深 #8C1D18/#F9DEDC，
       与后台 admin 的 MD3 语义色同一套值）
     · 警告 → 暖色 tonal 容器   · 成功 → 绿色 tonal 容器
     · 形状：圆角 16（MD3 大圆角）；高度：单行 48 起
     · 高度：elevation level 3
     · 图标：用 mask + currentColor 画，不依赖图标字体，也不用 emoji

   动效：Typecho 原生是 slideDown（动画高度）+ 5 秒后 fadeOut，
   这里由脚本 login/script.php 接管，改成 MD3 的
   进场 emphasized-decelerate / 退场 emphasized-accelerate（见 .ab-toast-*）。
   ⚠️ 所以容器本身不再自带 animation，避免和脚本加的两个类三方打架。
   ================================================================ */
.message.popup{
/* 语义色变量（默认按错误态，下面 .error/.notice/.success 各自覆盖） */
--ab-msg-bg: #FFDAD6;
--ab-msg-fg: #410E0B;
--ab-msg-ic: #BA1A1A;

position: fixed !important;
top: 20px !important;
left: 50% !important;
transform: translateX(-50%) !important;
width: auto !important;
min-width: 280px !important;
max-width: calc(100vw - 40px) !important;              /* 老浏览器兜底 */
max-width: min(420px, calc(100vw - 40px)) !important;
box-sizing: border-box !important;
margin: 0 !important;
padding: 14px 18px 14px 50px !important;
border: none !important;
border-radius: 16px !important;
background: var(--ab-msg-bg) !important;
color: var(--ab-msg-fg) !important;
box-shadow: 0 4px 8px 3px rgba(0, 0, 0, .15),
            0 1px 3px rgba(0, 0, 0, .3) !important;
text-align: left !important;
z-index: 9999 !important;
}

/* ---- 进场：emphasized decelerate（淡入 + 上浮 12px + 从 0.92 放大）---- */
.message.popup.ab-toast-in{
animation: lb-toast-in .3s cubic-bezier(.05, .7, .1, 1) both !important;
}

/* ---- 退场：emphasized accelerate（淡出 + 再上浮一点 + 微缩）---- */
.message.popup.ab-toast-out{
animation: lb-toast-out .2s cubic-bezier(.3, 0, .8, .15) forwards !important;
}

@keyframes lb-toast-in{
from{ opacity: 0; transform: translateX(-50%) translateY(-12px) scale(.92); }
to{ opacity: 1; transform: translateX(-50%) translateY(0) scale(1); }
}

@keyframes lb-toast-out{
from{ opacity: 1; transform: translateX(-50%) translateY(0) scale(1); }
to{ opacity: 0; transform: translateX(-50%) translateY(-10px) scale(.94); }
}

/* 只淡入淡出（不位移），用于「减少动态效果」偏好 */
@keyframes lb-toast-fade-in{
from{ opacity: 0; }
to{ opacity: 1; }
}

@keyframes lb-toast-fade-out{
from{ opacity: 1; }
to{ opacity: 0; }
}

/* 前置状态图标：mask + currentColor，与文字同一套色 */
.message.popup::before{
content: '' !important;
position: absolute !important;
left: 18px !important;
top: 50% !important;
width: 20px !important;
height: 20px !important;
transform: translateY(-50%) !important;
background-color: var(--ab-msg-ic) !important;
-webkit-mask: var(--ab-msg-icon) center / contain no-repeat !important;
mask: var(--ab-msg-icon) center / contain no-repeat !important;
}

.message.popup ul{
margin: 0 !important;
padding: 0 !important;
list-style: none !important;
text-align: left !important;
}

.message.popup ul li{
margin: 0 !important;
padding: 0 !important;
display: block !important;
font-size: 14px !important;
font-weight: 500 !important;
line-height: 20px !important;
letter-spacing: .01em !important;
color: inherit !important;
background: none !important;
}

/* 多条提示时分行，次要行降低字重 */
.message.popup ul li + li{
margin-top: 6px !important;
font-weight: 400 !important;
opacity: .9;
}

.message.popup ul li a{
color: inherit !important;
font-weight: 700 !important;
text-decoration: underline !important;
text-underline-offset: 2px;
}

/* ---- 错误：error container ---- */
.message.popup.error{
--ab-msg-bg: #FFDAD6;
--ab-msg-fg: #410E0B;
--ab-msg-ic: #BA1A1A;
--ab-msg-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-2h2v2Zm0-4h-2V7h2v6Z'/%3E%3C/svg%3E");
}

/* ---- 警告：暖色 tonal 容器 ---- */
.message.popup.notice{
--ab-msg-bg: #FFE4B8;
--ab-msg-fg: #3D2E00;
--ab-msg-ic: #B57500;
--ab-msg-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M12 2 1 21h22L12 2Zm1 15h-2v-2h2v2Zm0-4h-2v-4h2v4Z'/%3E%3C/svg%3E");
}

/* ---- 成功：绿色 tonal 容器 ---- */
.message.popup.success{
--ab-msg-bg: #C6EFD3;
--ab-msg-fg: #0A3B1E;
--ab-msg-ic: #1B7A46;
--ab-msg-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.2 14.2-4-4 1.4-1.4 2.6 2.6 5.6-5.6 1.4 1.4-7 7Z'/%3E%3C/svg%3E");
}

/* ---- 暗色：容器 tone-30 / 文字 tone-90（与后台 admin 的 MD3 语义色一致）---- */
html[data-lb-theme="dark"] .message.popup.error{
--ab-msg-bg: #8C1D18;
--ab-msg-fg: #F9DEDC;
--ab-msg-ic: #FFB4AB;
}

html[data-lb-theme="dark"] .message.popup.notice{
--ab-msg-bg: #5C4200;
--ab-msg-fg: #FFE08A;
--ab-msg-ic: #FFD54F;
}

html[data-lb-theme="dark"] .message.popup.success{
--ab-msg-bg: #0F5132;
--ab-msg-fg: #A8E6B8;
--ab-msg-ic: #7BE0A0;
}

/* 减少动态效果：只做很短的淡入淡出，不做位移/缩放（位移会让前庭敏感用户不适） */
@media (prefers-reduced-motion: reduce){
.message.popup.ab-toast-in{
animation: lb-toast-fade-in .12s linear both !important;
}
.message.popup.ab-toast-out{
animation: lb-toast-fade-out .12s linear forwards !important;
}
}

@media (max-width: 480px){
.message.popup{
top: 16px !important;
min-width: 0 !important;
max-width: calc(100vw - 32px) !important;
padding: 12px 16px 12px 46px !important;
}
.message.popup::before{
left: 16px !important;
width: 18px !important;
height: 18px !important;
}
.message.popup ul li{
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
border: 1px solid var(--lb-glass-border);
background: var(--lb-glass-bg);
color: var(--lb-on-surface);
backdrop-filter: blur(14px) saturate(var(--lb-glass-saturate));
-webkit-backdrop-filter: blur(14px) saturate(var(--lb-glass-saturate));
box-shadow: none;
cursor:pointer;
transition: transform .25s cubic-bezier(0.4, 0, 0.2, 1), border-color .25s ease, background-color .25s ease;
display:flex;
align-items:center;
justify-content:center;
padding:0;
z-index:1000;
}

.lb-theme-toggle:hover{
transform: translateY(-2px) scale(1.05);
box-shadow: 0 8px 20px rgba(0,0,0,.18);
border-color: var(--lb-accent);
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

/* 注意：这里曾经有一条把 .lb-remember 整段隐藏掉的规则（display 强制 none）。
   它让 Typecho 原生的「下次自动登录」复选框在美化后的登录页彻底消失
   （虽然仍会随表单提交，但用户无法勾选）。现已删除，
   该选项改由上方 §「下次自动登录」的 MD3 switch 样式呈现。 */

/* ================================================================
   页脚版权信息（在卡片之外，固定于页面底部）
   - 用玻璃胶囊承载文字，避免压在任何背景图上都看不清
   - 外层 pointer-events:none，不遮挡卡片底部区域的交互
   ================================================================ */
.lb-footer-theme{
position: fixed;
left: 0;
right: 0;
bottom: 0;
z-index: 5;
display: flex;
justify-content: center;
padding: 10px 16px calc(14px + env(safe-area-inset-bottom, 0px));
pointer-events: none;
animation: lb-fade-up .5s var(--lb-easing) .34s backwards;
}

.lb-footer-pill{
pointer-events: auto;
display: inline-flex;
align-items: center;
gap: 5px;
padding: 7px 16px;
border-radius: 100px;
border: 1px solid var(--lb-glass-border);
background: var(--lb-glass-bg);
backdrop-filter: blur(14px) saturate(var(--lb-glass-saturate));
-webkit-backdrop-filter: blur(14px) saturate(var(--lb-glass-saturate));
color: var(--lb-on-surface-muted);
font-size: 11.5px;
font-weight: 500;
letter-spacing: .02em;
line-height: 1;
white-space: nowrap;
}

.lb-footer-pill a{
color: var(--lb-accent);
font-weight: 600;
text-decoration: none;
border-bottom: 1px solid transparent;
transition: border-color .2s ease;
}

.lb-footer-pill a:hover{
border-bottom-color: currentColor;
}

.lb-hide { display:none !important; }

.lb-more-link {
  display: flex;
  justify-content: center;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
  margin: 20px 0 0;
  padding: 16px 0 0;
  border-top: 1px solid var(--lb-border);
  animation: lb-fade-up .5s var(--lb-easing) .28s backwards;
}

.lb-more-link a {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 100px;
  font-size: 13px;
  font-weight: 500;
  color: var(--lb-accent);
  text-decoration: none;
  background: transparent;
  transition: background .2s ease, transform .1s ease;
  position: relative;
  overflow: hidden;
  -webkit-tap-highlight-color: transparent;
}

/* 悬停状态层：用强调色 rgba，随主题自动适配，无需 color-mix 兜底 */
.lb-more-link a:hover {
  background: rgba(var(--lb-accent-rgb), 0.12);
}

.lb-more-link a:active {
  background: rgba(var(--lb-accent-rgb), 0.2);
  transform: scale(0.97);
}

.lb-more-link a svg {
  width: 15px;
  height: 15px;
  flex-shrink: 0;
  opacity: 0.85;
}

<?php
if (trim($customCss) !== '') {
    echo "\n/* --- custom login css --- */\n";
    echo $customCss . "\n";
}
?>
</style>

<script id="loginbeautify-theme-init">
(function(){
    try{
      var mode = <?php echo $jsThemeMode; ?>;
      var saved = localStorage.getItem('lb-theme');
      var dark = false;

      if (saved === 'light' || saved === 'dark') {
        dark = saved === 'dark';
      } else if (mode === 'dark') {
        dark = true;
      } else if (mode === 'light') {
        dark = false;
      } else {
        dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      }

      document.documentElement.setAttribute('data-lb-theme', dark ? 'dark' : 'light');
    }catch(e){}
})();
</script>
