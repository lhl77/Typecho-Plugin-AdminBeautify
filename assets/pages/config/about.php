<?php
/**
 * 配置面板 - 关于插件区块（暗色样式 + 卡片 HTML + 赞赏弹窗 + 初始化 JS）
 * 依赖变量：$abC1, $abC2
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<style>
[data-theme="dark"] #ab-card-about {
    background: var(--md-surface-container-low, #1d1b20) !important;
    border-color: var(--md-outline-variant, rgba(255,255,255,.12)) !important;
}
[data-theme="dark"] #ab-card-about-hdr:hover { background: rgba(255,255,255,.05) !important; }
[data-theme="dark"] .ab-about-section-title { color: var(--md-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-about-author-name { color: var(--md-on-surface, #e6e1e5) !important; }
[data-theme="dark"] .ab-about-author-bio { color: var(--md-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-about-link-btn { background: rgba(255,255,255,.08) !important; color: var(--md-on-surface, #e6e1e5) !important; border-color: rgba(255,255,255,.12) !important; }
[data-theme="dark"] .ab-about-link-btn:hover { background: rgba(255,255,255,.14) !important; }
[data-theme="dark"] .ab-about-plugin-card { background: var(--md-surface-container, #211f26) !important; border-color: var(--md-outline-variant, rgba(255,255,255,.12)) !important; }
[data-theme="dark"] .ab-about-plugin-name { color: var(--md-on-surface, #e6e1e5) !important; }
[data-theme="dark"] .ab-about-plugin-desc { color: var(--md-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-about-changelog-item { border-color: rgba(255,255,255,.08) !important; }
[data-theme="dark"] .ab-about-tag-version { background: rgba(255,255,255,.1) !important; color: var(--md-on-surface, #e6e1e5) !important; }
[data-theme="dark"] .ab-about-changelog-body { color: var(--md-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-about-support-tip { background: rgba(255,255,255,.04) !important; border-color: rgba(255,255,255,.1) !important; }
[data-theme="dark"] .ab-about-support-title { color: var(--md-on-surface, #e6e1e5) !important; }
[data-theme="dark"] .ab-about-support-desc { color: var(--md-on-surface-variant, #cac4d0) !important; }
[data-theme="dark"] .ab-about-support-qr-label { color: var(--md-on-surface-variant, #cac4d0) !important; }
</style>

<div id="ab-card-about" class="ab-card" style="margin:0 0 16px;border-radius:20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.08),0 2px 12px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.06);overflow:hidden">
    <div id="ab-card-about-hdr" class="ab-card-hdr" style="display:flex;align-items:center;gap:12px;padding:18px 22px;cursor:pointer;user-select:none;-webkit-user-select:none;transition:background .15s" onmouseover="this.style.background='rgba(0,0,0,.025)'" onmouseout="this.style.background=''">
        <div id="ab-card-about-strip" style="width:3px;height:36px;background:<?php echo $abC1; ?>;border-radius:2px;flex-shrink:0;transition:background .3s"></div>
        <div style="width:40px;height:40px;background:<?php echo $abC1; ?>1a;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">💡</div>
        <div style="flex:1;min-width:0">
            <div class="ab-card-title" style="font-size:15px;font-weight:600;color:#1c1b1f;line-height:1.3">关于插件</div>
            <div class="ab-card-subtitle" style="font-size:12px;color:#79747e;margin-top:2px">作者信息 · 更新日志 · 支持作者</div>
        </div>
        <svg id="ab-card-about-chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?php echo $abC1; ?>" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;transition:transform .35s"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div id="ab-card-about-body" class="ab-card-body" style="overflow:hidden;max-height:9999px;padding:0px 36px 16px !important;transition:max-height .4s cubic-bezier(.4,0,.2,1)">

        <!-- ── 作者信息 ── -->
        <div style="margin:16px 0 0;padding:18px;background:linear-gradient(135deg,<?php echo $abC1; ?>18,<?php echo $abC2; ?>10);border-radius:16px;border:1px solid <?php echo $abC1; ?>22">
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px">
                <img src="https://i.see.you/2026/03/08/Uei3/26ee132f48bd9453e9c4d1d3fa1d312d.jpg" alt="lhl77" style="width:52px;height:52px;border-radius:50%;border:2px solid <?php echo $abC1; ?>44;flex-shrink:0">
                <div>
                    <div class="ab-about-author-name" style="font-size:17px;font-weight:700;color:#1c1b1f;letter-spacing:-.01em">LHL (lhl77)</div>
                    <div class="ab-about-author-bio" style="font-size:12px;color:#79747e;margin-top:3px">插件作者</div>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="https://github.com/lhl77" target="_blank" rel="noopener" class="ab-about-link-btn" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#fff;border:1px solid rgba(0,0,0,.1);border-radius:20px;font-size:12px;font-weight:500;color:#333;text-decoration:none;transition:background .15s" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='#fff'">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
                    GitHub
                </a>
                <a href="https://blog.lhl.one" target="_blank" rel="noopener" class="ab-about-link-btn" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#fff;border:1px solid rgba(0,0,0,.1);border-radius:20px;font-size:12px;font-weight:500;color:#333;text-decoration:none;transition:background .15s" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='#fff'">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    个人博客
                </a>
                <a href="https://t.me/+S_rnDEUlSPPRzvW_" target="_blank" rel="noopener" class="ab-about-link-btn" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#fff;border:1px solid rgba(0,0,0,.1);border-radius:20px;font-size:12px;font-weight:500;color:#333;text-decoration:none;transition:background .15s" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='#fff'">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.17 13.667l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.978.892z"/></svg>
                    Telegram 群
                </a>
                <a href="https://qm.qq.com/q/OOzG20idi2" target="_blank" rel="noopener" class="ab-about-link-btn" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#fff;border:1px solid rgba(0,0,0,.1);border-radius:20px;font-size:12px;font-weight:500;color:#333;text-decoration:none;transition:background .15s" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='#fff'">
                <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="14" height="14" viewBox="0 0 48 48">
<path fill="#FFC107" d="M17.5,44c-3.6,0-6.5-1.6-6.5-3.5s2.9-3.5,6.5-3.5s6.5,1.6,6.5,3.5S21.1,44,17.5,44z M37,40.5c0-1.9-2.9-3.5-6.5-3.5S24,38.6,24,40.5s2.9,3.5,6.5,3.5S37,42.4,37,40.5z"></path><path fill="#37474F" d="M37.2,22.2c-0.1-0.3-0.2-0.6-0.3-1c0.1-0.5,0.1-1,0.1-1.5c0-1.4-0.1-2.6-0.1-3.6C36.9,9.4,31.1,4,24,4S11,9.4,11,16.1c0,0.9,0,2.2,0,3.6c0,0.5,0,1,0.1,1.5c-0.1,0.3-0.2,0.6-0.3,1c-1.9,2.7-3.8,6-3.8,8.5C7,35.5,8.4,35,8.4,35c0.6,0,1.6-1,2.5-2.1C13,38.8,18,43,24,43s11-4.2,13.1-10.1C38,34,39,35,39.6,35c0,0,1.4,0.5,1.4-4.3C41,28.2,39.1,24.8,37.2,22.2z"></path><path fill="#ECEFF1" d="M14.7,23c-0.5,1.5-0.7,3.1-0.7,4.8C14,35.1,18.5,41,24,41s10-5.9,10-13.2c0-1.7-0.3-3.3-0.7-4.8H14.7z"></path><path fill="#FFF" d="M23,13.5c0,1.9-1.1,3.5-2.5,3.5S18,15.4,18,13.5s1.1-3.5,2.5-3.5S23,11.6,23,13.5z M27.5,10c-1.4,0-2.5,1.6-2.5,3.5s1.1,3.5,2.5,3.5s2.5-1.6,2.5-3.5S28.9,10,27.5,10z"></path><path fill="#37474F" d="M22,13.5c0,0.8-0.4,1.5-1,1.5s-1-0.7-1-1.5s0.4-1.5,1-1.5S22,12.7,22,13.5z M27,12c-0.6,0-1,0.7-1,1.5s0.4-0.5,1-0.5s1,1.3,1,0.5S27.6,12,27,12z"></path><path fill="#FFC107" d="M32,19.5c0,0.8-3.6,2.5-8,2.5s-8-1.7-8-2.5s3.6-1.5,8-1.5S32,18.7,32,19.5z"></path><path fill="#FF3D00" d="M38.7,21.2c-0.4-1.5-1-2.2-2.1-1.3c0,0-5.9,3.1-12.5,3.1v0.1l0-0.1c-6.6,0-12.5-3.1-12.5-3.1c-1.1-0.8-1.7-0.2-2.1,1.3c-0.4,1.5-0.7,2,0.7,2.8c0.1,0.1,1.4,0.8,3.4,1.7c-0.6,3.5-0.5,6.8-0.5,7c0.1,1.5,1.3,1.3,2.9,1.3c1.6-0.1,2.9,0,2.9-1.6c0-0.9,0-2.9,0.3-5c1.6,0.3,3.2,0.6,5,0.6l0,0v0c7.3,0,13.7-3.9,13.9-4C39.3,23.3,39,22.8,38.7,21.2z"></path><path fill="#DD2C00" d="M13.2,27.7c1.6,0.6,3.5,1.3,5.6,1.7c0-0.6,0.1-1.3,0.2-2c-2.1-0.5-4-1.1-5.5-1.7C13.4,26.4,13.3,27.1,13.2,27.7z"></path>
</svg>    
                QQ 群
                </a>
            </div>
        </div>

        <!-- ── 作者的其他插件 ── -->
        <div style="margin-top:20px">
            <div class="ab-about-section-title" style="font-size:12px;font-weight:600;color:#79747e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">🧩 作者的其他插件</div>
            <div id="ab-about-more-plugins" style="margin-top:8px"><!-- GitHub API 动态加载 --></div>
        </div>

        <!-- ── 作者的其他项目 ── -->
        <div style="margin-top:20px">
            <div class="ab-about-section-title" style="font-size:12px;font-weight:600;color:#79747e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">🚀 作者的服务</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px">
                <a href="https://img.lhl.one" target="_blank" rel="noopener" class="ab-about-plugin-card" style="display:block;padding:12px 14px;background:#f8f8f8;border:1px solid rgba(0,0,0,.07);border-radius:14px;text-decoration:none;transition:box-shadow .15s" onmouseover="this.style.boxShadow='0 2px 12px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow='none'">
                    <div class="ab-about-plugin-name" style="font-size:13px;font-weight:600;color:#1c1b1f;margin-bottom:4px">🖼️ LHL's Images 聚合图床</div>
                    <div class="ab-about-plugin-desc" style="font-size:11px;color:#79747e;line-height:1.5">个人博客可申请免费使用•Telegram Bot上传•中国优化储存•S.EE•R2•OSS•Edge One</div>
                </a>
                <a href="https://shop.lhl.one" target="_blank" rel="noopener" class="ab-about-plugin-card" style="display:block;padding:12px 14px;background:#f8f8f8;border:1px solid rgba(0,0,0,.07);border-radius:14px;text-decoration:none;transition:box-shadow .15s" onmouseover="this.style.boxShadow='0 2px 12px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow='none'">
                    <div class="ab-about-plugin-name" style="font-size:13px;font-weight:600;color:#1c1b1f;margin-bottom:4px">🛒 LHL's Shop 小店</div>
                    <div class="ab-about-plugin-desc" style="font-size:11px;color:#79747e;line-height:1.5">可领取免费虚拟主机，并售卖作者的一些付费服务、虚拟主机、源码等</div>
                </a>
                <a href="https://blog.lhl.one/friends.html" target="_blank" rel="noopener" class="ab-about-plugin-card" style="display:block;padding:12px 14px;background:#f8f8f8;border:1px solid rgba(0,0,0,.07);border-radius:14px;text-decoration:none;transition:box-shadow .15s" onmouseover="this.style.boxShadow='0 2px 12px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow='none'">
                    <div class="ab-about-plugin-name" style="font-size:13px;font-weight:600;color:#1c1b1f;margin-bottom:4px">♥️ 欢迎与作者博客交换友链</div>
                    <div class="ab-about-plugin-desc" style="font-size:11px;color:#79747e;line-height:1.5">您的网站需要被至少一个搜索引擎引用，且类型需要为博客。</div>
                </a>
            </div>
        </div>

        <!-- ── 支持作者 ── -->
        <div style="margin-top:20px">
            <div class="ab-about-section-title" style="font-size:12px;font-weight:600;color:#79747e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">❤️ 支持作者</div>
            <div class="ab-about-support-tip" style="display:flex;align-items:flex-start;gap:16px;padding:16px;background:#fdf6ff;border:1px solid <?php echo $abC1; ?>22;border-radius:16px">
                <div style="flex:1;min-width:0">
                    <div class="ab-about-support-title" style="font-size:14px;font-weight:600;color:#1c1b1f;margin-bottom:6px">如果插件对你有帮助，欢迎请作者喝杯咖啡 ☕</div>
                    <div class="ab-about-support-desc" style="font-size:12px;color:#79747e;line-height:1.6;margin-bottom:12px">你的支持是作者持续维护和更新插件的动力。感谢每一位使用者！<br><span style="display:block;margin-top:8px;font-size:12px;color:#59555a">请在备注中填写：您的昵称 + GitHub 或 个人博客，作者会定期把您加入鸣谢列表。</span></div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify" target="_blank" rel="noopener" class="ab-star-btn" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:<?php echo $abC1; ?>;color:#fff;border-radius:20px;font-size:12px;font-weight:500;text-decoration:none">
                            <span class="material-icons-round">star</span> 给个 Star
                        </a>
                        <a class="ab-star-btn" href="https://pay.lhl.one/paypage/?merchant=3b8dnSzIL2EXvvz2x7WwVEsYHZ6%2BokmCo5jAUlP0klNU" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:linear-gradient(90deg,<?php echo $abC1; ?>,<?php echo $abC2; ?>);color:#fff;border-radius:20px;font-size:12px;font-weight:500;text-decoration:none">
                            <span class="material-icons-round" style="font-size:16px">volunteer_activism</span> 捐助
                        </a>
                    </div>
                </div>
                <div style="flex-shrink:0;text-align:center">
                    <img id="ab-donate-qr-img" src="https://i.see.you/2026/03/09/eS6p/4151a74124898d38a4e53fa8c7dcf3be.jpg" alt="赞赏码" style="width:110px;height:110px;border-radius:12px;object-fit:cover;border:1px solid rgba(0,0,0,.08);cursor:pointer;transition:opacity .2s" onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
                    <div class="ab-about-support-qr-label" style="font-size:10px;color:#79747e;margin-top:6px">赞赏码（点击放大）</div>
                </div>
            </div>
        </div>

        <!-- ── 鸣谢（支持者） ── -->
        <div style="margin-top:18px">
            <div class="ab-about-section-title" style="font-size:12px;font-weight:600;color:#79747e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">👏 鸣谢</div>
            <div id="ab-about-thanks" style="border-radius:12px;padding:12px;background:var(--md-surface-container-low);border:1px solid var(--md-outline-variant);">
                <div style="font-size:13px;color:var(--md-on-surface-variant);margin-bottom:8px">特别鸣谢：</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">
                    <a href="https://mzrme.com/" target="_blank" style="display:inline-flex;align-items:center;padding:3px 12px;background:rgba(0,0,0,.06);border-radius:20px;font-size:12px;color:var(--md-on-surface);text-decoration:none;transition:background .15s" onmouseover="this.style.background='rgba(0,0,0,.12)'" onmouseout="this.style.background='rgba(0,0,0,.06)'">MZRME</a>
                    <a href="https://github.com/leletheme" target="_blank" style="display:inline-flex;align-items:center;padding:3px 12px;background:rgba(0,0,0,.06);border-radius:20px;font-size:12px;color:var(--md-on-surface);text-decoration:none;transition:background .15s" onmouseover="this.style.background='rgba(0,0,0,.12)'" onmouseout="this.style.background='rgba(0,0,0,.06)'">Lele</a>
                    <a href="https://github.com/QingSongYaya" target="_blank" style="display:inline-flex;align-items:center;padding:3px 12px;background:rgba(0,0,0,.06);border-radius:20px;font-size:12px;color:var(--md-on-surface);text-decoration:none;transition:background .15s" onmouseover="this.style.background='rgba(0,0,0,.12)'" onmouseout="this.style.background='rgba(0,0,0,.06)'">QingSongYaya</a>
                </div>
                <div style="font-size:13px;color:var(--md-on-surface-variant);margin-bottom:8px">你们的支持是我开发的最大动力：</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:4px">
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 12px;background:rgba(0,0,0,.06);border-radius:20px;font-size:12px;color:var(--md-on-surface)">感谢 <a href="https://github.com/Yilimmilk" target="_blank" style="color:var(--md-primary,#6750a4);font-weight:600;text-decoration:none">Yilimmilk</a> 的 20元 打赏</span>
                </div>
                <br/><div style="font-size:13px;color:var(--md-on-surface-variant);margin-bottom:8px">（将按周期随版本更新，如有调整后续更新）</div>
            </div>
        </div>

        <!-- ── 更新日志 ── -->
        <div style="margin-top:20px">
            <div class="ab-about-section-title" style="font-size:12px;font-weight:600;color:#79747e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">📋 更新日志</div>
            <div id="ab-about-changelog" style="border-radius:12px;overflow:hidden;border:1px solid rgba(0,0,0,.07)">
                <div style="padding:20px;text-align:center;color:#79747e;font-size:13px">
                    <div style="animation:ab-spin 1s linear infinite;display:inline-block;width:20px;height:20px;border:2px solid rgba(0,0,0,.1);border-top-color:#79747e;border-radius:50%;margin-bottom:8px"></div>
                    <div>正在从 GitHub 加载更新日志...</div>
                </div>
            </div>
            <div id="ab-changelog-load-more" style="text-align:center;padding:8px 0 2px;display:none">
                <button type="button" id="ab-changelog-more-btn" style="background:none;border:none;font-size:12px;color:#79747e;cursor:pointer;padding:4px 14px;border-radius:20px;transition:background .15s;text-decoration:underline;text-underline-offset:3px" onmouseover="this.style.background='rgba(0,0,0,.05)'" onmouseout="this.style.background='none'">加载更多</button>
            </div>
        </div>

        <!-- ── 数据与隐私 ── -->
        <div style="margin-top:20px">
            <div class="ab-about-section-title" style="font-size:12px;font-weight:600;color:#79747e;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">🔒 数据与隐私</div>
            <div id="ab-telemetry-field-container"></div>
        </div>

    </div>
</div>

<!-- ── 赞赏码全屏弹窗（挂载到 body 后显示，避免 stacking-context 问题） ── -->
<div id="ab-donate-modal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:2147483647;background:rgba(0,0,0,.72);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);box-sizing:border-box;align-items:center;justify-content:center">
    <div id="ab-donate-modal-inner" style="position:relative;text-align:center;padding:28px 24px 24px;background:#fff;border-radius:24px;box-shadow:0 8px 40px rgba(0,0,0,.35);width:280px;max-width:calc(100vw - 40px);max-height:calc(100vh - 40px);overflow-y:auto;box-sizing:border-box">
        <button id="ab-donate-modal-close" style="position:absolute;top:12px;right:12px;width:28px;height:28px;border-radius:50%;border:none;background:rgba(0,0,0,.08);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;line-height:1;color:#666;padding:0">✕</button>
        <div style="font-size:14px;font-weight:600;color:#1c1b1f;margin-bottom:14px">☕ 请作者喝杯咖啡</div>
        <img src="https://i.see.you/2026/03/09/eS6p/4151a74124898d38a4e53fa8c7dcf3be.jpg" alt="赞赏码" style="width:220px;height:220px;border-radius:16px;object-fit:cover;border:1px solid rgba(0,0,0,.08);display:block;margin:0 auto">
        <div style="font-size:11px;color:#79747e;margin-top:8px"><b>微信赞赏码</b><br/>请在备注中填写：您的昵称 + GitHub 或 个人博客，作者会定期把您加入鸣谢列表。</div>
        <a class="ab-star-btn" href="https://pay.lhl.one/paypage/?merchant=3b8dnSzIL2EXvvz2x7WwVEsYHZ6%2BokmCo5jAUlP0klNU" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;margin-top:16px;padding:9px 20px;background:linear-gradient(90deg,<?php echo $abC1; ?>,<?php echo $abC2; ?>);color:#fff;border-radius:20px;font-size:13px;font-weight:500;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.18)">
            <span class="material-icons-round" style="font-size:15px">payments</span> 其他支付方式
        </a>
    </div>
</div>

<script>
(function(){
    // ── 初始化关于卡片折叠 ──
    var aboutHdr=document.getElementById("ab-card-about-hdr");
    if(aboutHdr) aboutHdr.addEventListener("click",function(){ window.abToggleCard&&window.abToggleCard("about"); });

    function restoreAboutCard(){
        var saved=null;
        try{ saved=localStorage.getItem("ab-card-about"); }catch(e){}
        var body=document.getElementById("ab-card-about-body");
        var chev=document.getElementById("ab-card-about-chev");
        var card=document.getElementById("ab-card-about");
        if(!body) return;
        if(saved==="1"){
            body.setAttribute("data-collapsed","0");
        } else {
            body.style.transition="none";
            body.style.paddingBottom="0";
            body.style.maxHeight="0";
            body.setAttribute("data-collapsed","1");
            if(chev) chev.style.transform="rotate(-90deg)";
            if(card) card.style.boxShadow="0 1px 2px rgba(0,0,0,.04)";
            setTimeout(function(){ body.style.transition="max-height .4s cubic-bezier(.4,0,.2,1)"; },50);
        }
    }

    // ── 更新颜色 ──
    function updateAboutColors(c1){
        var s=document.getElementById("ab-card-about-strip");
        if(s) s.style.background=c1;
        var v=document.getElementById("ab-card-about-chev");
        if(v) v.setAttribute("stroke",c1);
    }

    // ── 从 GitHub API 加载更新日志 ──
    // 镜像代理前缀（直连失败时依次尝试）
    var GH_API_MIRRORS=["","https://gh-proxy.org/","https://ghfast.top/","https://ghproxy.com/","https://gh1.lhl.one"];
    function ghApiGet(url,cb){
        var idx=0;
        function tryNext(){
            if(idx>=GH_API_MIRRORS.length){cb(null);return;}
            var fullUrl=GH_API_MIRRORS[idx++]+url;
            var x=new XMLHttpRequest();
            x.open("GET",fullUrl,true);
            x.withCredentials=false;
            x.timeout=8000;
            x.onload=function(){try{var d=JSON.parse(x.responseText);cb(d);}catch(e){tryNext();}};
            x.onerror=x.ontimeout=function(){tryNext();};
            x.send();
        }
        tryNext();
    }

    function loadChangelog(){
        var el=document.getElementById("ab-about-changelog");
        if(!el) return;
        ghApiGet("https://api.github.com/repos/lhl77/Typecho-Plugin-AdminBeautify/releases?per_page=5",function(releases){
            if(!releases||!Array.isArray(releases)||releases.length===0){
                el.innerHTML="<div style=\"padding:16px;text-align:center;font-size:13px;color:#79747e\">"+(releases===null?"加载失败，请访问 <a href=\"https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases\" target=\"_blank\" style=\"color:inherit\">GitHub Releases</a> 查看":"暂无更新日志")+"</div>";
                return;
            }
            var html="";
            releases.forEach(function(r,i){
                var date=r.published_at?r.published_at.substring(0,10):"";
                var body=(r.body||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/\n/g,"<br>").replace(/\*\*(.*?)\*\*/g,"<strong>$1</strong>").replace(/`(.*?)`/g,"<code style=\"background:rgba(0,0,0,.06);padding:1px 5px;border-radius:4px;font-size:11px\">$1</code>").replace(/^- /gm,"• ").replace(/<br>• /g,"<br>&nbsp;&nbsp;• ");
                var isLatest=(i===0);
                var itemId="ab-cl-item-"+i;
                html+="<div class=\"ab-about-changelog-item\" style=\""+(i>0?"border-top:1px solid rgba(0,0,0,.06)":"")+"\">";
                html+="<div class=\"ab-cl-hdr\" data-idx=\""+i+"\" style=\"display:flex;align-items:center;gap:8px;padding:12px 16px;cursor:"+(isLatest?"default":"pointer")+"\">";
                html+="<span class=\"ab-about-tag-version\" style=\"display:inline-block;padding:2px 10px;background:rgba(0,0,0,.06);border-radius:10px;font-size:12px;font-weight:600;color:#333\">"+r.tag_name+"</span>";
                if(r.prerelease) html+="<span style=\"display:inline-block;padding:2px 8px;background:#fef3c7;border-radius:10px;font-size:11px;color:#92400e\">预发布</span>";
                if(date) html+="<span style=\"font-size:11px;color:#79747e;margin-left:auto\">"+date+"</span>";
                if(!isLatest) html+="<svg class=\"ab-cl-chev\" width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"#79747e\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\" style=\"flex-shrink:0;transition:transform .25s;transform:rotate(-90deg)\"><polyline points=\"6 9 12 15 18 9\"/></svg>";
                html+="</div>";
                html+="<div id=\""+itemId+"\" class=\"ab-about-changelog-body\" style=\"padding:0 16px 12px;font-size:12px;color:#49454f;line-height:1.8;"+(isLatest?"":"display:none")+"\">"+body+"</div>";
                html+="</div>";
            });
            el.innerHTML=html;
            el.addEventListener("click",function(e){
                var hdr=e.target.closest?e.target.closest(".ab-cl-hdr"):null;
                if(!hdr)return;
                var idx=parseInt(hdr.getAttribute("data-idx"),10);
                if(idx===0)return;
                var bd=document.getElementById("ab-cl-item-"+idx);
                if(!bd)return;
                var open=bd.style.display!=="none";
                bd.style.display=open?"none":"block";
                var chev=hdr.querySelector(".ab-cl-chev");
                if(chev)chev.style.transform=open?"rotate(-90deg)":"rotate(0)";
            });
        });
    }

    // ── 从 GitHub API 加载作者其他 Repo ──
    function loadGithubRepos(){
        ghApiGet("https://api.github.com/users/lhl77/repos?sort=updated&per_page=20",function(repos){
            if(!repos||!Array.isArray(repos)) return;
            var plugins=repos.filter(function(r){ return /typecho/i.test(r.name)&&r.name!=="Typecho-Plugin-AdminBeautify"&&r.name!=="Typecho-Raw-Nontification"; });
            var pEl=document.getElementById("ab-about-more-plugins");
            if(pEl&&plugins.length>0){
                var ph="<div style=\"display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px\">";
                plugins.slice(0,8).forEach(function(r){
                    ph+="<a href=\""+r.html_url+"\" target=\"_blank\" rel=\"noopener\" class=\"ab-about-plugin-card\" style=\"display:block;padding:12px 14px;background:#f8f8f8;border:1px solid rgba(0,0,0,.07);border-radius:14px;text-decoration:none;transition:box-shadow .15s\" onmouseover=\"this.style.boxShadow='0 2px 12px rgba(0,0,0,.1)'\" onmouseout=\"this.style.boxShadow='none'\">";
                    ph+="<div class=\"ab-about-plugin-name\" style=\"font-size:13px;font-weight:600;color:#1c1b1f;margin-bottom:4px\">"+r.name.replace(/^Typecho-Plugin-/,"")+"</div>";
                    ph+="<div class=\"ab-about-plugin-desc\" style=\"font-size:11px;color:#79747e;line-height:1.5\">"+(r.description||"")+"</div>";
                    ph+="</a>";
                });
                ph+="</div>";
                pEl.innerHTML=ph;
            }
        });
    }

    function initAbout(){
        restoreAboutCard();
        // 颜色跟随主题色
        var sel=document.querySelector("[name=\"primaryColor\"]");
        if(sel){
            sel.addEventListener("change",function(){
                var abColorMap={purple:"#7D5260",blue:"#556270",teal:"#4A6363",green:"#55624C",orange:"#725A42",pink:"#74565F",red:"#775654"};
                updateAboutColors(abColorMap[this.value]||abColorMap.purple);
            });
        }
        // 插入卡片到 perf 卡片之后
        var perfCard=document.getElementById("ab-card-perf");
        var aboutCard=document.getElementById("ab-card-about");
        if(perfCard&&aboutCard){
            if(perfCard.nextSibling) perfCard.parentNode.insertBefore(aboutCard,perfCard.nextSibling);
            else perfCard.parentNode.appendChild(aboutCard);
        } else if(aboutCard){
            var anyCard=document.getElementById("ab-card-compat")||document.getElementById("ab-card-login")||document.getElementById("ab-card-admin");
            if(anyCard&&anyCard.parentNode){
                anyCard.parentNode.appendChild(aboutCard);
            }
        }
        // 延迟加载异步内容
        setTimeout(function(){ loadChangelog(); loadGithubRepos(); }, 800);
        // 将「匿名使用统计」和「插件通知」表单字段注入「数据与隐私」区块
        var telContainer=document.getElementById("ab-telemetry-field-container");
        if(telContainer){
            function findFieldUlAbout(name){
                var el=document.querySelector("ul[id^='typecho-option-item-"+name+"-']");
                if(el) return el;
                var form=document.querySelector("form.protected")||document.querySelector("form");
                if(!form) return null;
                var inp=form.querySelector("[name=\""+name+"\"]");
                if(!inp) return null;
                var c=inp.parentNode;
                while(c&&c!==form){ if(c.tagName==="UL") return c; c=c.parentNode; }
                return null;
            }
            var telUl=findFieldUlAbout("telemetryOptOut");
            if(telUl) telContainer.appendChild(telUl);
            var notifyUl=findFieldUlAbout("notifyOptOut");
            if(notifyUl) telContainer.appendChild(notifyUl);
        }

        // ── 赞赏码弹窗逻辑 ──
        if(!document.getElementById("ab-donate-modal-anim")){
            var st=document.createElement("style");
            st.id="ab-donate-modal-anim";
            st.textContent="@keyframes ab-donatePopIn{from{opacity:0;transform:scale(.85)}to{opacity:1;transform:scale(1)}}";
            document.head.appendChild(st);
        }
        function closeDonateModal(){
            var m=document.getElementById("ab-donate-modal");
            if(m){ m.style.display="none"; }
            document.body.style.overflow="";
        }
        function openDonateModal(){
            var m=document.getElementById("ab-donate-modal");
            if(!m) return;
            if(m.parentNode!==document.body) document.body.appendChild(m);
            if(!m._abEvtBound){
                m._abEvtBound=true;
                m.addEventListener("click",function(e){ if(e.target===m) closeDonateModal(); });
                var closeBtn=document.getElementById("ab-donate-modal-close");
                if(closeBtn) closeBtn.addEventListener("click",closeDonateModal);
                document.addEventListener("keydown",function(e){ if(e.key==="Escape"&&m.style.display==="flex") closeDonateModal(); });
            }
            var inner=document.getElementById("ab-donate-modal-inner");
            if(inner){ inner.style.animation="none"; void inner.offsetWidth; inner.style.animation="ab-donatePopIn .25s cubic-bezier(.34,1.56,.64,1)"; }
            m.style.display="flex";
            document.body.style.overflow="hidden";
        }
        var donateBtn=document.getElementById("ab-btn-donate");
        if(donateBtn) donateBtn.addEventListener("click",openDonateModal);
        var donateQr=document.getElementById("ab-donate-qr-img");
        if(donateQr) donateQr.addEventListener("click",openDonateModal);
    }

    if(document.readyState==="loading"){
        document.addEventListener("DOMContentLoaded",initAbout);
    } else {
        initAbout();
    }
})();
</script>
