<?php
/**
 * 配置面板 - 全局样式
 * 包含：MD3 折叠卡片、提示框、分组标签、PWA 安装栏、动画、暗色模式覆盖
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<style>
    
/* 全局 .ab-card ul li { display:flex } 会把 label/input/description 横排，
   在配置面板中覆盖回竖排布局 */
.ab-card-body .typecho-option { padding: 0 !important; margin: 12px 0 !important; }
.ab-card-body .typecho-option li {
    display: block !important;
    padding: 0 !important;
    font-size: inherit !important;
    color: inherit !important;
    background: none !important;
    border-radius: 0 !important;
}
.ab-card-body .typecho-option li:hover { background: none !important; }
.ab-card-body .typecho-option label.typecho-label { display: block; margin-bottom: .4em; font-weight: 600; }
.ab-card-body .typecho-option .description { display: block; margin: .4em 0 0; color: #79747e; font-size: .9em; }

/* ── MD3 折叠卡片 ── */
.ab-card {
    margin: 0 0 16px;
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,.08), 0 2px 12px rgba(0,0,0,.04);
    border: 1px solid rgba(0,0,0,.06);
    overflow: hidden;
}
.ab-card-hdr {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 18px 22px;
    cursor: pointer;
    user-select: none;
    -webkit-user-select: none;
    transition: background .15s;
}
.ab-card-hdr:hover { background: rgba(0,0,0,.025); }
.ab-card-strip {
    width: 3px;
    height: 36px;
    border-radius: 2px;
    flex-shrink: 0;
    transition: background .3s;
}
.ab-card-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
    transition: background .3s;
}
.ab-card-icon .material-icons-round {
    font-size: 22px;
    line-height: 1;
}
.ab-card-meta { flex: 1; min-width: 0; }
.ab-card-title  { font-size: 15px; font-weight: 600; color: #1c1b1f; line-height: 1.3; }
.ab-card-subtitle { font-size: 12px; color: #79747e; margin-top: 2px; }
.ab-card-chev { flex-shrink: 0; transition: transform .35s; }
.ab-card-body {
    overflow: hidden;
    max-height: 9999px;
    padding: 0 16px;
    transition: max-height .4s cubic-bezier(.4,0,.2,1);
}

/* ── 提示框（蓝色 / 绿色） ── */
.ab-card-tip {
    margin: 4px 6px 8px;
    padding: 12px 15px;
    background: #f0f9ff;
    border: 1px solid #bfdbfe;
    border-radius: 12px;
}
.ab-card-tip-green {
    margin: 4px 6px 8px;
    padding: 12px 15px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
}
.ab-card-tip-inner      { display: flex; align-items: flex-start; gap: 10px; }
.ab-card-tip-icon       { font-size: 15px; flex-shrink: 0; margin-top: 1px; }
.ab-card-tip-text       { flex: 1; font-size: 13px; color: #1e40af; line-height: 1.7; }
.ab-card-tip-green-text { flex: 1; font-size: 13px; color: #166534; line-height: 1.7; }
.ab-card-tip-green-text code {
    background: #dcfce7;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
}

/* ── 分组分割线 & 标签（config.script.php 动态创建） ── */
.ab-group-divider {
    height: 1px;
    background: var(--md-outline-variant, rgba(0,0,0,.1));
    margin: 8px 0 4px;
}
.ab-group-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--md-on-surface-variant, #79747e);
    padding: 4px 6px 2px;
    letter-spacing: .08em;
    text-transform: uppercase;
}
.ab-group-sublabel {
    font-size: 10.5px;
    font-weight: 600;
    color: var(--md-primary, #6750a4);
    padding: 10px 6px 2px;
    letter-spacing: .06em;
    text-transform: uppercase;
    opacity: .85;
}

/* ── 管理后台设置：快速定位 / 搜索 ── */
.ab-global-search {
    position: sticky;
    top: var(--ab-global-search-top, 12px);
    z-index: 8;
    display: block;
    padding: 10px 12px;
    margin: 0 0 22px;
    border-radius: 14px;
    background: rgba(255,255,255,.86);
    border: 1px solid rgba(103,80,164,.22);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    box-shadow: 0 10px 28px rgba(103,80,164,.16), 0 0 0 2px rgba(255,255,255,.42);
}
.ab-global-search-main {
    display: flex;
    align-items: center;
    gap: 8px;
}
.ab-global-search-icon {
    font-size: 18px;
    color: var(--md-primary, #6750a4);
}
.ab-global-search-input {
    width: 100%;
    height: 34px;
    border: none;
    background: transparent;
    color: var(--md-on-surface, #1c1b1f);
    font-size: 13px;
}
.ab-global-search-input:focus {
    outline: none;
}
.ab-global-search-status {
    display: none;
    margin-top: 8px;
    font-size: 11px;
    color: var(--md-on-surface-variant, #79747e);
}
.ab-global-search-status.is-warn {
    color: #b3261e;
}
.ab-global-search-results {
    display: none;
    max-height: min(48vh, 360px);
    overflow: auto;
    margin-top: 8px;
    border-radius: 12px;
    background: var(--md-surface, #fff);
    border: 1px solid var(--md-outline-variant, #cac4d0);
}
.ab-global-search.is-open .ab-global-search-results {
    display: block;
}
.ab-global-search-item {
    width: 100%;
    border: none;
    background: transparent;
    text-align: left;
    padding: 10px 12px;
    cursor: pointer;
    display: grid;
    gap: 2px;
}
.ab-global-search-item + .ab-global-search-item {
    border-top: 1px solid var(--md-outline-variant, #e4dfe8);
}
.ab-global-search-item:hover {
    background: rgba(103,80,164,.08);
}
.ab-global-search-item.is-hidden {
    background: rgba(179,38,30,.04);
}
.ab-global-search-item.is-hidden:hover {
    background: rgba(179,38,30,.08);
}
.ab-global-search-item-title {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--md-on-surface, #1c1b1f);
}
.ab-global-search-item-path {
    font-size: 11px;
    color: var(--md-on-surface-variant, #79747e);
    white-space: normal;
    line-height: 1.4;
}
.ab-global-search-empty {
    padding: 12px;
    font-size: 12px;
    color: var(--md-on-surface-variant, #79747e);
}

/* 主色选择：前端增强为复选式卡片 */
.ab-color-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-top: 14px;
}
.ab-color-item {
    position: relative !important;
    border: 2px solid transparent !important;
    border-radius: 16px !important;
    background: transparent !important;
    padding: 0 !important;
    margin: 0 !important;
    height: 60px !important;
    display: block !important;
    cursor: pointer !important;
    text-align: center !important;
    box-sizing: border-box !important;
    outline: none !important;
    transition: transform .25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow .25s cubic-bezier(0.4, 0, 0.2, 1), border-color .25s cubic-bezier(0.4, 0, 0.2, 1) !important;
}
.ab-color-item:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 12px rgba(0,0,0,.08) !important;
}
.ab-color-item:active {
    transform: translateY(0) scale(0.96) !important;
    box-shadow: 0 2px 4px rgba(0,0,0,.08) !important;
}
.ab-color-item.is-active {
    border-color: var(--md-primary, #6750a4) !important;
    box-shadow: 0 4px 12px rgba(0,0,0,.15) !important;
}
.ab-color-check {
    position: absolute !important;
    top: 50% !important;
    left: 50% !important;
    transform: translate(-50%, -50%) scale(0.5) !important;
    width: 24px !important;
    height: 24px !important;
    border-radius: 50% !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 16px !important;
    background: rgba(0,0,0,0.3) !important;
    backdrop-filter: blur(2px) !important;
    color: #fff !important;
    opacity: 0 !important;
    pointer-events: none !important;
    z-index: 5 !important;
    transition: opacity .25s cubic-bezier(0.4, 0, 0.2, 1), transform .25s cubic-bezier(0.4, 0, 0.2, 1) !important;
}
.ab-color-item.is-active .ab-color-check {
    opacity: 1 !important;
    transform: translate(-50%, -50%) scale(1) !important;
}
.ab-color-preview {
    position: absolute !important;
    top: 0 !important;
    bottom: 0 !important;
    left: 0 !important;
    right: 0 !important;
    border-radius: 14px !important;
    background: linear-gradient(135deg, var(--ab-color-main, #7D5260) 0%, var(--ab-color-alt, #9E7B8A) 100%) !important;
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
    box-sizing: border-box !important;
    transition: top .25s cubic-bezier(0.4, 0, 0.2, 1), bottom .25s cubic-bezier(0.4, 0, 0.2, 1), left .25s cubic-bezier(0.4, 0, 0.2, 1), right .25s cubic-bezier(0.4, 0, 0.2, 1), border-radius .25s cubic-bezier(0.4, 0, 0.2, 1) !important;
}
.ab-color-item.is-active .ab-color-preview {
    top: 4px !important;
    bottom: 4px !important;
    left: 4px !important;
    right: 4px !important;
    border-radius: 12px !important;
}

@media (min-width: 1200px) {
    .ab-color-grid {
        grid-template-columns: repeat(7, minmax(56px, 1fr));
        justify-content: flex-start;
    }
}

@media (max-width: 900px) {
    .ab-color-grid {
        grid-template-columns: repeat(4, minmax(0,1fr));
    }
}

.ab-admin-quick {
    position: sticky;
    top: 0;
    z-index: 6;
    padding: 10px;
    margin: 6px 0 10px;
    border-radius: 14px;
    background: rgba(103,80,164,.06);
    border: 1px solid rgba(103,80,164,.14);
    backdrop-filter: blur(6px);
}
.ab-admin-quick-title {
    font-size: 11px;
    font-weight: 600;
    color: var(--md-on-surface-variant, #79747e);
    letter-spacing: .06em;
    text-transform: uppercase;
}
.ab-admin-quick-chips {
    display: flex;
    gap: 6px;
    margin-top: 6px;
    overflow-x: auto;
    padding-bottom: 2px;
    scrollbar-width: thin;
}
.ab-admin-chip {
    border: none;
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 11px;
    white-space: nowrap;
    cursor: pointer;
    color: var(--md-on-secondary-container, #1d192b);
    background: var(--md-secondary-container, #e8def8);
}
.ab-admin-chip:hover {
    opacity: .88;
}
.ab-card-body .ab-filter-hidden {
    display: none !important;
}
.ab-admin-target-flash {
    animation: ab-target-flash .9s ease;
}
@keyframes ab-target-flash {
    0% { background: color-mix(in srgb, var(--md-primary, #6750a4) 22%, transparent); }
    100% { background: transparent; }
}

@media (max-width: 575px) {
    .ab-global-search {
        top: var(--ab-global-search-top, 8px);
        padding: 8px 10px;
        margin-bottom: 10px;
    }
    .ab-global-search-input {
        height: 32px;
        font-size: 12px;
    }
    .ab-admin-quick {
        padding: 8px;
        margin: 4px 0 8px;
    }
    .ab-admin-chip {
        padding: 5px 10px;
        font-size: 10.5px;
    }
    .ab-global-search-results {
        max-height: 42vh;
    }
    .ab-global-search-item {
        padding: 9px 10px;
    }
    .ab-global-search-item-title {
        font-size: 12px;
    }
    .ab-global-search-item-path {
        font-size: 10.5px;
    }
    .ab-color-grid {
        grid-template-columns: repeat(4, minmax(0,1fr));
        gap: 7px;
    }
    .ab-color-item { height: 44px !important; border-radius: 12px !important; }
    .ab-color-preview { border-radius: 10px !important; }
    .ab-color-item.is-active .ab-color-preview { top: 3px !important; bottom: 3px !important; left: 3px !important; right: 3px !important; border-radius: 7px !important; }
    .ab-color-check { width: 20px !important; height: 20px !important; font-size: 14px !important; }
}

/* 设置页保存 FAB */
.ab-config-save-fab {
    position: fixed;
    right: 20px;
    bottom: 20px;
    width: 58px;
    height: 58px;
    padding: 0 18px;
    overflow: hidden;
    gap: 0;
    border: none;
    border-radius: 50%;
    z-index: 99;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #fff;
    background: linear-gradient(135deg, var(--md-primary, #6750a4), #8a6bc2);
    box-shadow: 0 10px 28px rgba(103,80,164,.35);
    transition: transform .18s ease, box-shadow .18s ease, opacity .18s ease, width .24s cubic-bezier(.2,0,.2,1), border-radius .24s cubic-bezier(.2,0,.2,1);
}
.ab-config-save-fab:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(103,80,164,.42);
}
.ab-config-save-fab .material-icons-round {
    font-size: 24px;
    flex: 0 0 auto;
}
.ab-config-save-fab-label {
    max-width: 0;
    opacity: 0;
    margin-left: 0;
    white-space: nowrap;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: .01em;
    overflow: hidden;
    transition: max-width .24s cubic-bezier(.2,0,.2,1), opacity .2s ease, margin-left .24s cubic-bezier(.2,0,.2,1);
}
@media (hover: hover) and (pointer: fine) {
    .ab-hover-capable .ab-config-save-fab:hover,
    .ab-hover-capable .ab-config-save-fab:focus-visible {
        width: 146px;
        border-radius: 999px;
    }
    .ab-hover-capable .ab-config-save-fab:hover .ab-config-save-fab-label,
    .ab-hover-capable .ab-config-save-fab:focus-visible .ab-config-save-fab-label {
        max-width: 82px;
        opacity: 1;
        margin-left: 8px;
    }
}
.ab-config-save-fab.is-busy {
    opacity: .72;
    pointer-events: none;
}

@media (max-width: 575px) {
    .ab-config-save-fab {
        right: 14px;
        bottom: 14px;
        width: 54px;
        height: 54px;
        padding: 0;
    }
    .ab-config-save-fab-label {
        display: none;
    }
}

/* ── PWA 安装栏（config.script.php 动态创建） ── */
.ab-pwa-install-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin: 8px 0 4px;
    padding: 14px 16px;
    background: rgba(103,80,164,.06);
    border-radius: 14px;
    border: 1px solid rgba(103,80,164,.12);
}
.ab-pwa-install-btn {
    padding: 8px 18px;
    border-radius: 20px;
    border: none;
    background: #6750a4;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity .2s;
    white-space: nowrap;
}
.ab-pwa-install-btn:hover { opacity: .85; }
.ab-pwa-install-tip { font-size: 12px; color: #79747e; line-height: 1.5; }

/* ── 动画（原注入在 config.script.php 的 style 标签） ── */
@keyframes ab-spin    { to { transform: rotate(360deg); } }
@keyframes ab-fadeIn  { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }

/* ══════════════════════════════════════
   暗色模式覆盖
   ══════════════════════════════════════ */
[data-theme="dark"] .ab-card {
    background: var(--md-surface-container-low, #1d1b20) !important;
    border-color: var(--md-outline-variant, rgba(255,255,255,.12)) !important;
    box-shadow: 0 1px 3px rgba(0,0,0,.4), 0 2px 8px rgba(0,0,0,.25) !important;
}
[data-theme="dark"] .ab-card-hdr:hover  { background: rgba(255,255,255,.05) !important; }
[data-theme="dark"] .ab-card-title      { color: var(--md-on-surface, #e6e1e5) !important; }
[data-theme="dark"] .ab-card-subtitle   { color: var(--md-on-surface-variant, #cac4d0) !important; }

[data-theme="dark"] .ab-card-tip {
    background: rgba(96,165,250,.1) !important;
    border-color: rgba(96,165,250,.25) !important;
}
[data-theme="dark"] .ab-card-tip-text { color: #93c5fd !important; }

[data-theme="dark"] .ab-card-tip-green {
    background: rgba(74,222,128,.08) !important;
    border-color: rgba(74,222,128,.2) !important;
}
[data-theme="dark"] .ab-card-tip-green-text        { color: #86efac !important; }
[data-theme="dark"] .ab-card-tip-green-text code   {
    background: rgba(74,222,128,.15) !important;
    color: #86efac !important;
}

[data-theme="dark"] .ab-group-label    { color: var(--md-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-group-sublabel { color: var(--md-primary, #d0bcff) !important; }
[data-theme="dark"] .ab-group-divider  { background: rgba(255,255,255,.1) !important; }

[data-theme="dark"] .ab-admin-quick {
    background: rgba(208,188,255,.08) !important;
    border-color: rgba(208,188,255,.2) !important;
}
[data-theme="dark"] .ab-global-search {
    background: rgba(33,31,38,.88) !important;
    border-color: rgba(208,188,255,.28) !important;
    box-shadow: 0 12px 30px rgba(0,0,0,.38), 0 0 0 1px rgba(208,188,255,.12) !important;
}
[data-theme="dark"] .ab-global-search-input {
    color: var(--md-dark-on-surface, #e6e1e5) !important;
}
[data-theme="dark"] .ab-global-search-results {
    background: var(--md-dark-surface-container, #211f26) !important;
    border-color: rgba(255,255,255,.14) !important;
}
[data-theme="dark"] .ab-global-search-item + .ab-global-search-item {
    border-top-color: rgba(255,255,255,.08) !important;
}
[data-theme="dark"] .ab-global-search-item:hover {
    background: rgba(208,188,255,.12) !important;
}
[data-theme="dark"] .ab-global-search-item-title {
    color: var(--md-dark-on-surface, #e6e1e5) !important;
}
[data-theme="dark"] .ab-global-search-item-path,
[data-theme="dark"] .ab-global-search-empty {
    color: var(--md-dark-on-surface-variant, #cac4d0) !important;
}
[data-theme="dark"] .ab-global-search-status {
    color: var(--md-dark-on-surface-variant, #cac4d0) !important;
}
[data-theme="dark"] .ab-global-search-status.is-warn {
    color: #f2b8b5 !important;
}
[data-theme="dark"] .ab-global-search-item.is-hidden {
    background: rgba(242,184,181,.08) !important;
}
[data-theme="dark"] .ab-global-search-item.is-hidden:hover {
    background: rgba(242,184,181,.14) !important;
}
[data-theme="dark"] .ab-color-item {
    background: transparent !important;
    border-color: transparent !important;
}
[data-theme="dark"] .ab-color-item:hover {
    background: transparent !important;
}
[data-theme="dark"] .ab-color-item.is-active {
    background: transparent !important;
    border-color: var(--ab-color-main, #d0bcff) !important;
    box-shadow: 0 2px 8px rgba(0,0,0,.35) !important;
}
[data-theme="dark"] .ab-color-item.is-active .ab-color-check {
    color: var(--md-dark-primary, #d0bcff) !important;
}
[data-theme="dark"] .ab-color-check {
    background: rgba(28,27,31,.94) !important;
}
[data-theme="dark"] .ab-config-save-fab {
    box-shadow: 0 12px 30px rgba(0,0,0,.5), 0 0 0 1px rgba(208,188,255,.12) inset;
}
[data-theme="dark"] .ab-admin-chip {
    background: rgba(208,188,255,.18) !important;
    color: var(--md-dark-on-primary-container, #381e72) !important;
}

[data-theme="dark"] .ab-pwa-install-bar {
    background: rgba(208,188,255,.08) !important;
    border-color: rgba(208,188,255,.2) !important;
}
[data-theme="dark"] .ab-pwa-install-tip { color: var(--md-on-surface-variant, #cac4d0) !important; }

/* ── 兼容脚本列表（暗色） ── */
[data-theme="dark"] #ab-compat-scripts-list .ab-compat-list-title {
    color: var(--md-on-surface, #e6e1e5) !important;
}
[data-theme="dark"] .ab-compat-script-item {
    background: var(--md-surface-container, #211f26) !important;
    border-color: var(--md-outline-variant, rgba(255,255,255,.12)) !important;
}
[data-theme="dark"] .ab-compat-script-item .ab-compat-name  { color: var(--md-on-surface, #e6e1e5) !important; }
[data-theme="dark"] .ab-compat-script-item .ab-compat-desc  { color: var(--md-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-compat-script-item .ab-compat-meta  { color: rgba(255,255,255,.4) !important; }
[data-theme="dark"] .ab-compat-script-item .ab-compat-meta[style*="background"] {
    background: rgba(255,255,255,.08) !important;
}
[data-theme="dark"] .ab-compat-script-item .ab-compat-toggle-bg        { background: rgba(255,255,255,.2) !important; }
[data-theme="dark"] .ab-compat-script-item .ab-compat-toggle-bg.active { background: var(--md-primary, #d0bcff) !important; }
[data-theme="dark"] .ab-compat-empty {
    background: rgba(250,204,21,.08) !important;
    border-color: rgba(250,204,21,.2) !important;
    color: #fde68a !important;
}

/* ============================================================
   概要页卡片设置（卡片清单 / 拖拽排序）
   注意：AdminBeautify.v2.1.57.css 里的
     .ab-card ul            { padding:4px 12px 12px !important }
     .ab-card ul li         { display:flex!important; align-items:baseline!important; ... }
     .ab-card ul li span:first-child { 日期徽章样式 }
   会污染这里的自定义列表，所有属性均需更高特异性 + !important 反制。
   ============================================================ */
.ab-card .ab-dash-cards-ui { margin: 0 0 4px; }

.ab-card .ab-dash-cards-tip {
    display: flex !important;
    align-items: flex-start !important;
    gap: 8px !important;
    padding: 12px 14px !important;
    margin: 0 0 14px !important;
    border-radius: 14px !important;
    font-size: 12.5px !important;
    line-height: 1.6 !important;
    background: rgba(125,82,96,.08) !important;
    color: #6b5b62 !important;
}
.ab-card .ab-dash-cards-tip .material-icons-round {
    font-size: 18px !important;
    line-height: 1.3 !important;
    flex: none !important;
    opacity: .7;
}

.ab-card .ab-dash-cards-list {
    list-style: none !important;
    margin: 0 !important;
    padding: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
}

/* 卡片内所有图标：防止任何残留的 span 规则（如主样式表的 .ab-card ul li span:first-child
   日期徽章）把图标渲染成药丸 —— 排序行现在是 div，结构上已隔离，这里做二次兜底。 */
.ab-card .ab-dash-cards-list .ab-dash-card-row .material-icons-round {
    width: auto !important;
    height: auto !important;
    min-width: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
    border: 0 !important;
    border-radius: 0 !important;
    background: transparent !important;
    font-variant-numeric: normal !important;
    font-weight: normal !important;
    line-height: 1 !important;
}

.ab-card .ab-dash-cards-list .ab-dash-card-row {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 10px 12px !important;
    margin: 0 !important;
    border-radius: 14px !important;
    background: #fff !important;
    border: 1px solid rgba(0,0,0,.07) !important;
    box-shadow: 0 1px 2px rgba(0,0,0,.04) !important;
    font-size: 13px !important;
    color: var(--md-on-surface, #1c1b1f) !important;
    transition: box-shadow .18s, border-color .18s !important;
    cursor: default !important;
    overflow: visible !important;
    min-width: 0 !important;
}
.ab-card .ab-dash-cards-list .ab-dash-card-row:hover {
    border-color: rgba(125,82,96,.32) !important;
    box-shadow: 0 2px 10px rgba(0,0,0,.08) !important;
    background: #fff !important;
}
.ab-card .ab-dash-cards-list .ab-dash-card-row.is-dragging {
    opacity: .55 !important;
    border-style: dashed !important;
}

/* 拖拽手柄（本行第一个 span，需反制日期徽章规则） */
.ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-drag {
    flex: none !important;
    width: auto !important;
    min-width: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
    border: 0 !important;
    border-radius: 0 !important;
    background: transparent !important;
    color: #9a9aa2 !important;
    font-size: 20px !important;
    font-weight: normal !important;
    font-variant-numeric: normal !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: grab !important;
}

.ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-idx {
    flex: none !important;
    min-width: 22px !important;
    text-align: center !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    font-variant-numeric: tabular-nums !important;
    color: #7d5260 !important;
    background: rgba(125,82,96,.1) !important;
    border-radius: 999px !important;
    padding: 2px 0 !important;
    margin: 0 !important;
}

.ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-icon {
    flex: none !important;
    width: 34px !important;
    height: 34px !important;
    min-width: 34px !important;
    padding: 0 !important;
    margin: 0 !important;
    border-radius: 10px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: rgba(125,82,96,.1) !important;
    color: #7d5260 !important;
    font-size: 19px !important;
}
.ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-icon .material-icons-round {
    font-size: 19px !important;
    color: inherit !important;
}

.ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-meta {
    display: flex !important;
    flex-direction: column !important;
    gap: 2px !important;
    min-width: 0 !important;
    flex: 1 1 auto !important;
    align-items: flex-start !important;
}
.ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-name {
    font-size: 13.5px !important;
    font-weight: 600 !important;
    color: var(--md-on-surface, #1c1b1f) !important;
    line-height: 1.4 !important;
}
.ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-desc {
    font-size: 11.5px !important;
    color: var(--md-on-surface-variant, #79747e) !important;
    line-height: 1.45 !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    max-width: 100% !important;
}

.ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-state {
    flex: none !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    padding: 2px 9px !important;
    margin: 0 !important;
    border-radius: 999px !important;
    background: rgba(0,0,0,.06) !important;
    color: #79747e !important;
    white-space: nowrap !important;
}
.ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-state.is-on {
    background: rgba(46,125,50,.12) !important;
    color: #2e7d32 !important;
}

.ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-btns {
    flex: none !important;
    display: inline-flex !important;
    gap: 4px !important;
}
.ab-dash-card-btn {
    width: 28px !important;
    height: 28px !important;
    border-radius: 9px !important;
    border: 1px solid rgba(0,0,0,.08) !important;
    background: #f7f2fa !important;
    color: #4a4458 !important;
    cursor: pointer !important;
    padding: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: background .15s, border-color .15s !important;
}
.ab-dash-card-btn .material-icons-round { font-size: 16px !important; }
.ab-dash-card-btn:hover {
    background: rgba(125,82,96,.12) !important;
    border-color: rgba(125,82,96,.3) !important;
}

.ab-card .ab-dash-cards-actions {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    margin-top: 14px !important;
}
.ab-dash-cards-reset {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    padding: 8px 14px !important;
    border-radius: 999px !important;
    cursor: pointer !important;
    border: 1px solid rgba(0,0,0,.1) !important;
    background: #fff !important;
    color: #4a4458 !important;
    font-size: 12.5px !important;
    font-weight: 600 !important;
    transition: background .15s !important;
}
.ab-dash-cards-reset .material-icons-round { font-size: 16px !important; }
.ab-dash-cards-reset:hover { background: rgba(125,82,96,.1) !important; }
.ab-dash-cards-status {
    font-size: 12px !important;
    color: #2e7d32 !important;
    opacity: 0;
    transition: opacity .2s;
}
.ab-dash-cards-status.is-on { opacity: 1; }

/* ---- 堆叠分组（概要页卡片设置）---- */
.ab-card .ab-dash-cards-addgroup {
    border-color: rgba(125,82,96,.35) !important;
    color: #7d5260 !important;
    background: rgba(125,82,96,.06) !important;
}
.ab-card .ab-dash-cards-addgroup:hover { background: rgba(125,82,96,.14) !important; }

.ab-card .ab-dash-cards-list .ab-dash-group-row {
    flex-wrap: wrap !important;
    align-items: center !important;
    padding-bottom: 6px !important;
    border: 1px dashed rgba(125,82,96,.45) !important;
    background: rgba(125,82,96,.05) !important;
}

.ab-card .ab-dash-cards-list .ab-dash-group-list {
    order: 9 !important;
    flex: 1 1 100% !important;
    min-width: 0 !important;
    width: auto !important;
    box-sizing: border-box !important;
    margin: 6px 0 2px 22px !important;
    padding: 6px 0 0 10px !important;
    border-left: 2px solid rgba(125,82,96,.25) !important;
    display: none !important;
}

.ab-card .ab-dash-cards-list .ab-dash-group-list .ab-dash-card-row {
    min-width: 0 !important;
}

.ab-card .ab-dash-cards-list .ab-dash-group-row[data-open="1"] .ab-dash-group-list {
    display: flex !important;
}

.ab-card .ab-dash-cards-list .ab-dash-group-list .ab-dash-card-row {
    background: #fff !important;
    border-color: rgba(0,0,0,.09) !important;
    box-shadow: none !important;
}

.ab-card .ab-dash-cards-list .ab-dash-group-list.is-drop-reject {
    border-left-color: #b3261e !important;
    background: rgba(179,38,30,.06) !important;
}

.ab-card .ab-dash-group-empty {
    padding: 10px 12px !important;
    border: 1px dashed rgba(125,82,96,.35) !important;
    border-radius: 12px !important;
    font-size: 12px !important;
    color: #79747e !important;
    text-align: center !important;
}

.ab-dash-card-btn-danger {
    color: #b3261e !important;
    border-color: rgba(179,38,30,.25) !important;
    background: rgba(179,38,30,.06) !important;
}
.ab-dash-card-btn-danger:hover { background: rgba(179,38,30,.14) !important; }

/* 概要页卡片设置（暗色） */
[data-theme="dark"] .ab-card .ab-dash-cards-tip {
    background: rgba(208,188,255,.1) !important;
    color: #cac4d0 !important;
}
[data-theme="dark"] .ab-card .ab-dash-cards-list .ab-dash-card-row {
    background: var(--md-surface-container, #211f26) !important;
    border-color: rgba(255,255,255,.12) !important;
    box-shadow: none !important;
}
[data-theme="dark"] .ab-card .ab-dash-cards-list .ab-dash-card-row:hover {
    background: var(--md-surface-container-high, #2b2930) !important;
    border-color: rgba(208,188,255,.35) !important;
}
[data-theme="dark"] .ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-drag {
    color: rgba(255,255,255,.45) !important;
}
[data-theme="dark"] .ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-idx {
    color: #d0bcff !important;
    background: rgba(208,188,255,.16) !important;
}
[data-theme="dark"] .ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-icon {
    background: rgba(208,188,255,.16) !important;
    color: #d0bcff !important;
}
[data-theme="dark"] .ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-name {
    color: var(--md-on-surface, #e6e1e5) !important;
}
[data-theme="dark"] .ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-desc {
    color: var(--md-on-surface-variant, #cac4d0) !important;
}
[data-theme="dark"] .ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-state {
    background: rgba(255,255,255,.1) !important;
    color: #cac4d0 !important;
}
[data-theme="dark"] .ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-state.is-on {
    background: rgba(129,199,132,.18) !important;
    color: #81c784 !important;
}
[data-theme="dark"] .ab-dash-card-btn {
    background: rgba(255,255,255,.07) !important;
    border-color: rgba(255,255,255,.12) !important;
    color: #d0bcff !important;
}
[data-theme="dark"] .ab-dash-card-btn:hover {
    background: rgba(208,188,255,.2) !important;
    border-color: rgba(208,188,255,.35) !important;
}
[data-theme="dark"] .ab-dash-cards-reset {
    background: rgba(255,255,255,.07) !important;
    border-color: rgba(255,255,255,.12) !important;
    color: #e6e1e5 !important;
}
[data-theme="dark"] .ab-dash-cards-reset:hover { background: rgba(208,188,255,.18) !important; }

[data-theme="dark"] .ab-card .ab-dash-cards-list .ab-dash-group-row {
    border-color: rgba(208,188,255,.4) !important;
    background: rgba(208,188,255,.08) !important;
}
[data-theme="dark"] .ab-card .ab-dash-cards-list .ab-dash-group-list {
    border-left-color: rgba(208,188,255,.3) !important;
}
[data-theme="dark"] .ab-card .ab-dash-cards-list .ab-dash-group-list .ab-dash-card-row {
    background: rgba(255,255,255,.04) !important;
    border-color: rgba(255,255,255,.12) !important;
}
[data-theme="dark"] .ab-card .ab-dash-group-empty {
    color: #cac4d0 !important;
    border-color: rgba(208,188,255,.3) !important;
}
[data-theme="dark"] .ab-card .ab-dash-cards-addgroup {
    border-color: rgba(208,188,255,.4) !important;
    color: #d0bcff !important;
    background: rgba(208,188,255,.1) !important;
}
[data-theme="dark"] .ab-dash-cards-status { color: #81c784 !important; }

/* 概要页卡片设置（窄屏） */
@media (max-width: 575px) {
    .ab-card .ab-dash-cards-list .ab-dash-card-row {
        flex-wrap: wrap !important;
        row-gap: 8px !important;
    }
    .ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-desc {
        white-space: normal !important;
    }
    .ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-meta {
        flex: 1 1 100% !important;
        order: 4;
    }
    .ab-card .ab-dash-cards-list .ab-dash-card-row .ab-dash-card-btns { order: 5; }
}

/* ============================================================
   概要页卡片设置 —— 自定义卡片编辑区（方案 C：自由 HTML / JS）
   ============================================================ */
.ab-card .ab-dash-custom {
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1px dashed rgba(0, 0, 0, .12);
}
.ab-card .ab-dash-custom-title {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    font-size: 14px !important;
    font-weight: 700 !important;
    color: var(--md-on-surface, #1c1b1f) !important;
    margin-bottom: 10px !important;
}
.ab-card .ab-dash-custom-title .material-icons-round { font-size: 20px !important; color: var(--md-primary, #7d5260) !important; }

/* 概要页卡片设置里的子区块（卡片排版模式 / 「更多」卡片）：
   沿用自定义卡片区的虚线分隔节奏，间距略收紧一点 */
.ab-card .ab-dash-subsection { margin-top: 18px; padding-top: 14px; }

.ab-card .ab-dash-custom-warn {
    display: flex !important;
    align-items: flex-start !important;
    gap: 8px !important;
    padding: 10px 14px !important;
    margin-bottom: 14px !important;
    border-radius: 12px !important;
    background: rgba(217, 119, 6, .1) !important;
    border: 1px solid rgba(217, 119, 6, .28) !important;
    color: #92400e !important;
    font-size: 12.5px !important;
    line-height: 1.6 !important;
}
.ab-card .ab-dash-custom-warn .material-icons-round { font-size: 18px !important; flex: none !important; line-height: 1.3 !important; }

/* 帮助文档 / 卡片广场入口 */
.ab-card .ab-dash-custom-help {
    margin: -4px 0 14px !important;
}
.ab-card .ab-dash-custom-help > a {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    padding: 9px 14px !important;
    margin-bottom: 8px !important;
    border-radius: 12px !important;
    background: rgba(125, 82, 96, .08) !important;
    border: 1px solid rgba(125, 82, 96, .2) !important;
    color: var(--md-primary, #7d5260) !important;
    font-size: 12.5px !important;
    font-weight: 600 !important;
    line-height: 1.5 !important;
    text-decoration: none !important;
    transition: background-color .2s !important;
}
.ab-card .ab-dash-custom-help > a:hover {
    background: rgba(125, 82, 96, .16) !important;
}
.ab-card .ab-dash-custom-help > a .material-icons-round {
    font-size: 18px !important;
    color: inherit !important;
    flex: none !important;
}
.ab-card .ab-dash-custom-help > a .ab-dash-custom-help-arrow {
    margin-left: auto !important;
    opacity: .7 !important;
}
.ab-card .ab-dash-custom-help > a:last-child {
    margin-bottom: 0 !important;
}
.ab-card .ab-dash-custom-help-note {
    display: flex !important;
    align-items: flex-start !important;
    gap: 8px !important;
    padding: 0 2px !important;
    color: var(--md-on-surface-variant, #49454f) !important;
    font-size: 12px !important;
    line-height: 1.7 !important;
}
.ab-card .ab-dash-custom-help-note .material-icons-round {
    font-size: 16px !important;
    flex: none !important;
    margin-top: 2px !important;
    color: var(--md-on-surface-variant, #49454f) !important;
}
.ab-card .ab-dash-custom-help-note a {
    color: var(--md-primary, #7d5260) !important;
    font-weight: 600 !important;
    text-decoration: none !important;
    border-bottom: 1px dashed currentColor !important;
}
.ab-card .ab-dash-custom-help-note a:hover { opacity: .85 !important; }

.ab-card .ab-dash-custom-enable {
    margin-bottom: 12px !important;
}
.ab-card .ab-dash-custom-enable .typecho-option { margin: 0 !important; }

.ab-card .ab-dash-custom-empty {
    padding: 14px !important;
    margin-bottom: 10px !important;
    border-radius: 12px !important;
    border: 1px dashed rgba(0, 0, 0, .14) !important;
    color: var(--md-on-surface-variant, #49454f) !important;
    font-size: 12.5px !important;
    text-align: center !important;
}

.ab-card .ab-dash-custom-item {
    padding: 12px 14px 14px !important;
    margin-bottom: 10px !important;
    border-radius: 14px !important;
    background: #fff !important;
    border: 1px solid rgba(0, 0, 0, .08) !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .04) !important;
}
.ab-card .ab-dash-custom-row {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    margin-bottom: 10px !important;
    flex-wrap: wrap !important;
}
.ab-card .ab-dash-custom-icon-wrap {
    flex: none !important;
    width: 34px !important;
    height: 34px !important;
    border-radius: 10px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: rgba(125, 82, 96, .1) !important;
    color: var(--md-primary, #7d5260) !important;
}
.ab-card .ab-dash-custom-icon-wrap .material-icons-round { font-size: 19px !important; color: inherit !important; }

.ab-card .ab-dash-custom-row input.ab-dash-custom-icon,
.ab-card .ab-dash-custom-row input.ab-dash-custom-name {
    height: 34px !important;
    padding: 0 12px !important;
    border-radius: 10px !important;
    border: 1px solid rgba(0, 0, 0, .14) !important;
    background: #fff !important;
    color: var(--md-on-surface, #1c1b1f) !important;
    font-size: 13px !important;
    box-shadow: none !important;
    box-sizing: border-box !important;
}
.ab-card .ab-dash-custom-row input.ab-dash-custom-icon { width: 150px !important; flex: none !important; }
.ab-card .ab-dash-custom-row input.ab-dash-custom-name { flex: 1 1 140px !important; min-width: 0 !important; }
.ab-card .ab-dash-custom-row input:focus {
    border-color: var(--md-primary, #7d5260) !important;
    outline: none !important;
}

.ab-card .ab-dash-custom-btns { flex: none !important; display: inline-flex !important; gap: 4px !important; margin-left: auto !important; }
.ab-card .ab-dash-custom-btns button {
    width: 30px !important;
    height: 30px !important;
    padding: 0 !important;
    border-radius: 9px !important;
    border: 1px solid rgba(0, 0, 0, .1) !important;
    background: #f7f2fa !important;
    color: #4a4458 !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.ab-card .ab-dash-custom-btns button .material-icons-round { font-size: 17px !important; }
.ab-card .ab-dash-custom-btns button:hover { background: rgba(125, 82, 96, .14) !important; }
.ab-card .ab-dash-custom-btns button[data-act="del"]:hover { background: rgba(220, 38, 38, .14) !important; color: #dc2626 !important; }

.ab-card .ab-dash-custom-label {
    font-size: 12px !important;
    font-weight: 600 !important;
    color: var(--md-on-surface-variant, #49454f) !important;
    margin: 8px 0 4px !important;
}
.ab-card .ab-dash-custom-item textarea {
    width: 100% !important;
    box-sizing: border-box !important;
    padding: 10px 12px !important;
    border-radius: 10px !important;
    border: 1px solid rgba(0, 0, 0, .14) !important;
    background: #fff !important;
    color: var(--md-on-surface, #1c1b1f) !important;
    font: 12.5px/1.6 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace !important;
    resize: vertical !important;
    box-shadow: none !important;
}
.ab-card .ab-dash-custom-item textarea:focus {
    border-color: var(--md-primary, #7d5260) !important;
    outline: none !important;
}

.ab-card .ab-dash-custom-add {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    padding: 9px 16px !important;
    border-radius: 999px !important;
    border: 1px dashed rgba(125, 82, 96, .5) !important;
    background: rgba(125, 82, 96, .08) !important;
    color: var(--md-primary, #7d5260) !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
}
.ab-card .ab-dash-custom-add:hover { background: rgba(125, 82, 96, .16) !important; }
.ab-card .ab-dash-custom-add .material-icons-round { font-size: 17px !important; }

/* 自定义卡片编辑区（暗色） */
[data-theme="dark"] .ab-card .ab-dash-custom { border-top-color: rgba(255, 255, 255, .14); }
[data-theme="dark"] .ab-card .ab-dash-custom-title { color: var(--md-dark-on-surface, #e6e1e5) !important; }
[data-theme="dark"] .ab-card .ab-dash-custom-title .material-icons-round { color: var(--md-dark-primary, #d0bcff) !important; }
[data-theme="dark"] .ab-card .ab-dash-custom-warn {
    background: rgba(250, 204, 21, .1) !important;
    border-color: rgba(250, 204, 21, .28) !important;
    color: #fde68a !important;
}
[data-theme="dark"] .ab-card .ab-dash-custom-item {
    background: var(--md-dark-surface-container, #2b2930) !important;
    border-color: rgba(255, 255, 255, .12) !important;
    box-shadow: none !important;
}
[data-theme="dark"] .ab-card .ab-dash-custom-item input,
[data-theme="dark"] .ab-card .ab-dash-custom-item textarea {
    background: rgba(255, 255, 255, .06) !important;
    border-color: rgba(255, 255, 255, .16) !important;
    color: var(--md-dark-on-surface, #e6e1e5) !important;
}
[data-theme="dark"] .ab-card .ab-dash-custom-icon-wrap { background: rgba(208, 188, 255, .16) !important; color: var(--md-dark-primary, #d0bcff) !important; }
[data-theme="dark"] .ab-card .ab-dash-custom-btns button {
    background: rgba(255, 255, 255, .07) !important;
    border-color: rgba(255, 255, 255, .14) !important;
    color: var(--md-dark-primary, #d0bcff) !important;
}
[data-theme="dark"] .ab-card .ab-dash-custom-btns button:hover { background: rgba(208, 188, 255, .2) !important; }
[data-theme="dark"] .ab-card .ab-dash-custom-label { color: var(--md-dark-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-card .ab-dash-custom-empty { border-color: rgba(255, 255, 255, .16) !important; color: var(--md-dark-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-card .ab-dash-custom-help > a {
    background: rgba(208, 188, 255, .12) !important;
    border-color: rgba(208, 188, 255, .26) !important;
    color: var(--md-dark-primary, #d0bcff) !important;
}
[data-theme="dark"] .ab-card .ab-dash-custom-help > a:hover { background: rgba(208, 188, 255, .2) !important; }
[data-theme="dark"] .ab-card .ab-dash-custom-help-note { color: var(--md-dark-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-card .ab-dash-custom-help-note .material-icons-round { color: var(--md-dark-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-card .ab-dash-custom-help-note a { color: var(--md-dark-primary, #d0bcff) !important; }
[data-theme="dark"] .ab-card .ab-dash-custom-add {
    background: rgba(208, 188, 255, .12) !important;
    border-color: rgba(208, 188, 255, .45) !important;
    color: var(--md-dark-primary, #d0bcff) !important;
}
[data-theme="dark"] .ab-card .ab-dash-custom-add:hover { background: rgba(208, 188, 255, .22) !important; }

@media (max-width: 575px) {
    .ab-card .ab-dash-custom-row input.ab-dash-custom-icon { width: 110px !important; }
    .ab-card .ab-dash-custom-title { font-size: 13px !important; }
}
</style>
