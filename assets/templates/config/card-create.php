<?php
/**
 * 配置面板 - MD3 折叠卡片构建函数
 *
 * abCard($id, $color, $emoji, $title, $subtitle, $bodyHtml = '')
 *   $id       — 卡片 id 后缀，如 'admin'、'editor'，会生成 id="ab-card-{id}" 等
 *   $color    — 强调色 hex，如 $abC1 / $abC2
 *   $emoji    — 图标 emoji
 *   $title    — 卡片标题
 *   $subtitle — 卡片副标题
 *   $bodyHtml — 卡片展开体内的初始 HTML（可含提示框；默认为空）
 *
 * abCardTip($icon, $html, $green = false)
 *   构建卡片内提示框，返回 HTML 字符串。
 *   $green = true → 绿色提示框；false → 蓝色提示框
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if (!function_exists('abCard')):
function abCard($id, $color, $emoji, $title, $subtitle, $bodyHtml = '') {
    $id    = htmlspecialchars($id,    ENT_QUOTES);
    $color = htmlspecialchars($color, ENT_QUOTES);
    $title    = htmlspecialchars($title,    ENT_QUOTES | ENT_HTML5);
    $subtitle = htmlspecialchars($subtitle, ENT_QUOTES | ENT_HTML5);
    echo <<<HTML
<div id="ab-card-{$id}" class="ab-card">
    <div id="ab-card-{$id}-hdr" class="ab-card-hdr">
        <div id="ab-card-{$id}-strip" class="ab-card-strip" style="background:{$color}"></div>
        <div id="ab-card-{$id}-icon"  class="ab-card-icon"  style="background:{$color}1a">{$emoji}</div>
        <div class="ab-card-meta">
            <div class="ab-card-title">{$title}</div>
            <div class="ab-card-subtitle">{$subtitle}</div>
        </div>  
        <svg id="ab-card-{$id}-chev" class="ab-card-chev"
             width="20" height="20" viewBox="0 0 24 24"
             fill="none" stroke="{$color}"
             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </div>
    <div id="ab-card-{$id}-body" class="ab-card-body">{$bodyHtml}</div>
</div>
HTML;
}
endif;

if (!function_exists('abCardTip')):
/**
 * 构建卡片提示框 HTML 字符串（不转义 $html，支持内嵌标签）
 * $green = false → 蓝色，$green = true → 绿色
 */
function abCardTip($icon, $html, $green = false) {
    $wrap  = $green ? 'ab-card-tip-green'      : 'ab-card-tip';
    $inner = $green ? 'ab-card-tip-green-text'  : 'ab-card-tip-text';
    return '<div class="' . $wrap . '">'
         .   '<div class="ab-card-tip-inner">'
         .     '<span class="ab-card-tip-icon">' . $icon . '</span>'
         .     '<div class="' . $inner . '">' . $html . '</div>'
         .   '</div>'
         . '</div>';
}
endif;
