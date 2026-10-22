<?php
/**
 * 配置面板 · 「补丁」卡片（Typecho 核心补丁的安装状态 + 回滚）
 *
 * ⚠️ 外壳必须用 abCard()（assets/pages/config/card-create.php）生成 —— 自己手写 div
 *    会缺 `ab-card-strip/icon/meta/title/subtitle/chev` 等 class，与其它卡片样式不一致。
 *    卡片位置由 `placeCard()` 插到 #ab-card-perf 之后（速度优化 → 补丁 → 升级通道）。
 *
 * 数据源：GET /action/admin-beautify?do=patch-list
 *   → { status: {typecho, ab, patches:[{id,title,severity,status,files[]}], have,count,applied,conflict },
 *       backups: [{dir,ts,id,files}] }
 *
 * 动作：安装复用 window.abPatchRun(id)（SSE 进度），回滚复用 window.abPatchRollback(id)。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<style>
/* 卡片外壳（.ab-card / -hdr / -title …）的样式全部继承主题自带的那套，这里只定义卡片内部元素 */
[data-theme="dark"] .ab-patch-row{background:rgba(255,255,255,.04)!important;border-color:rgba(255,255,255,.08)!important}
[data-theme="dark"] .ab-patch-row-title{color:var(--md-dark-on-surface,#e6e1e5)!important}
[data-theme="dark"] .ab-patch-row-file{color:var(--md-dark-on-surface-variant,#cac4d0)!important}
[data-theme="dark"] .ab-patch-hint{color:var(--md-dark-on-surface-variant,#cac4d0)!important}
.ab-patch-row{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;margin:10px 0 0;background:#fafafa;border:1px solid rgba(0,0,0,.06);border-radius:14px}
.ab-patch-row-main{flex:1;min-width:0}
.ab-patch-row-title{font-size:13.5px;font-weight:600;color:#1c1b1f;line-height:1.45;word-break:break-word}
.ab-patch-row-file{font-size:11.5px;color:#79747e;margin-top:4px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all}
.ab-patch-row-acts{display:flex;gap:8px;flex-shrink:0;align-items:center}
.ab-patch-badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;line-height:1.7;white-space:nowrap}
.ab-patch-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:500;border:1px solid rgba(0,0,0,.12);background:#fff;color:#333;cursor:pointer;line-height:1.4}
.ab-patch-btn:hover{background:#f5f5f5}
[data-theme="dark"] .ab-patch-btn{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.14);color:var(--md-dark-on-surface,#e6e1e5)}
[data-theme="dark"] .ab-patch-btn:hover{background:rgba(255,255,255,.12)}
.ab-patch-hint{font-size:12px;color:#79747e;line-height:1.6;margin-top:12px}
.ab-patch-empty{font-size:13px;color:#79747e;padding:18px 0;text-align:center}
</style>

<?php
// 卡片内部 HTML：外壳统一交给 abCard()，class 与其它卡片完全一致（样式才不会跑偏）
ob_start();
?>
        <div id="ab-patch-card-list"><div class="ab-patch-empty">加载中…</div></div>
        <div class="ab-patch-hint">
            补丁只改 <code>var/</code>、<code>admin/</code> 与根目录白名单文件；应用前会自动备份，回滚即还原。<br>
            Typecho 升级会覆盖核心文件，届时这里的「已安装」会变回可安装，重新点一次即可。
        </div>
        <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
            <button type="button" class="ab-patch-btn" id="ab-patch-card-refresh">
                <span class="material-icons-round" style="font-size:15px">refresh</span> 刷新状态
            </button>
            <button type="button" class="ab-patch-btn" id="ab-patch-card-check">
                <span class="material-icons-round" style="font-size:15px">sync</span> 重新拉取清单
            </button>
        </div>
<?php
$abPatchBody = ob_get_clean();
// 与其它卡片同一套外壳：class / 结构 / 暗色适配全部继承
abCard('patch', $abC1, 'verified_user', '补丁', 'Typecho 核心补丁的安装状态，可随时回滚', $abPatchBody);
?>

<script>
(function(){
    var root = document.getElementById('ab-card-patch');
    if(!root) return;
    var listEl = document.getElementById('ab-patch-card-list');
    var loading = false;

    /* 计数挂在标题后面（abCard 的标题是纯文本，这里补一个 span） */
    var titleEl = root.querySelector('.ab-card-title');
    var cntEl = document.getElementById('ab-card-patch-count');
    if(!cntEl && titleEl){
        cntEl = document.createElement('span');
        cntEl.id = 'ab-card-patch-count';
        cntEl.style.cssText = 'font-size:12px;font-weight:500;color:#79747e;margin-left:6px';
        titleEl.appendChild(cntEl);
    }
    /* 卡片内边距与其它卡片保持一致（config.script.php 也是这么设的） */
    var bodyEl = document.getElementById('ab-card-patch-body');
    if(bodyEl) bodyEl.style.padding = '0px 38px 16px';

    /* ── 折叠：复用 abToggleCard（与其它卡片同一套动画/记忆） ── */
    var hdr = document.getElementById('ab-card-patch-hdr');
    if(hdr) hdr.addEventListener('click', function(){ window.abToggleCard && window.abToggleCard('patch'); });

    /* ── 位置：插到「速度优化」与「升级通道」之间 ──
       config.script.php 的 buildCards() 在 DOMContentLoaded 里才把 perf / channel 排好，
       所以这里也要等 DOMContentLoaded（本脚本比 config.script.php 晚执行，监听器比它晚注册 → 排在它后面）。 ──*/
    function placeCard(){
        var perf = document.getElementById('ab-card-perf');
        if(!perf || !perf.parentNode) return;
        if(root.parentNode === perf.parentNode && root.previousSibling === perf) return; // 已就位
        if(perf.nextSibling) perf.parentNode.insertBefore(root, perf.nextSibling);
        else perf.parentNode.appendChild(root);
    }
    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', placeCard);
    } else {
        placeCard();
    }
    setTimeout(placeCard, 400);   // 兜底：其它卡片异步搬完后校正一次

    function restore(){
        var saved = null;
        try{ saved = localStorage.getItem('ab-card-patch'); }catch(e){}
        var body = document.getElementById('ab-card-patch-body');
        var chev = document.getElementById('ab-card-patch-chev');
        if(!body) return;
        if(saved === '1'){
            body.setAttribute('data-collapsed','0');
        } else {
            body.style.transition = 'none';
            body.style.paddingBottom = '0';
            body.style.maxHeight = '0';
            body.setAttribute('data-collapsed','1');
            if(chev) chev.style.transform = 'rotate(-90deg)';
            root.style.boxShadow = '0 1px 2px rgba(0,0,0,.04)';
            setTimeout(function(){ body.style.transition = 'max-height .4s cubic-bezier(.4,0,.2,1)'; }, 50);
        }
    }
    restore();

    function esc(s){
        return String(s === undefined || s === null ? '' : s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    var BADGE = {
        applied:  ['#e8f5e9','#1b5e20','已安装'],
        pending:  ['#fff8e1','#8d6e00','可安装'],
        conflict: ['#fdecea','#b3261e','有冲突']
    };

    function render(data){
        var st = (data && data.status) || {};
        var ps = st.patches || [];
        var applied = (st.applied|0), count = (st.count|0), conf = (st.conflict|0);

        if(cntEl){
            cntEl.textContent = ps.length
                ? '（已安装 ' + applied + (count ? ' · 可安装 ' + count : '') + (conf ? ' · 冲突 ' + conf : '') + '）'
                : '';
        }
        if(!ps.length){
            listEl.innerHTML = '<div class="ab-patch-empty">暂无适用于 Typecho '
                + esc(st.typecho || '?') + ' 的补丁</div>';
            return;
        }

        var html = '';
        for(var i = 0; i < ps.length; i++){
            var p = ps[i];
            var badge = BADGE[p.status] || ['#eee','#555', p.status || '?'];
            var files = '';
            var fs = p.files || [];
            for(var j = 0; j < fs.length; j++){
                files += '<div class="ab-patch-row-file">' + esc(fs[j].path)
                    + (fs[j].state === 'block' && fs[j].reason ? '　— ' + esc(fs[j].reason) : '') + '</div>';
            }
            var acts = '';
            if(p.status === 'pending'){
                acts += '<button type="button" class="ab-patch-btn" data-patch-apply="' + esc(p.id) + '">安装</button>';
            } else if(p.status === 'applied'){
                acts += '<button type="button" class="ab-patch-btn" data-patch-rollback="' + esc(p.id) + '">回滚</button>';
            }
            html += '<div class="ab-patch-row">'
                +   '<div class="ab-patch-row-main">'
                +     '<div class="ab-patch-row-title">' + esc(p.title) + '</div>'
                +     files
                +   '</div>'
                +   '<div class="ab-patch-row-acts">'
                +     '<span class="ab-patch-badge" style="background:' + badge[0] + ';color:' + badge[1] + '">' + badge[2] + '</span>'
                +     acts
                +   '</div>'
                + '</div>';
        }
        listEl.innerHTML = html;

        listEl.querySelectorAll('[data-patch-apply]').forEach(function(btn){
            btn.addEventListener('click', function(){
                if(window.abPatchRun) window.abPatchRun(btn.getAttribute('data-patch-apply'));
            });
        });
        listEl.querySelectorAll('[data-patch-rollback]').forEach(function(btn){
            btn.addEventListener('click', function(){
                if(window.abPatchRollback) window.abPatchRollback(btn.getAttribute('data-patch-rollback'));
            });
        });
    }

    function load(force){
        if(loading) return;
        loading = true;
        if(!force) listEl.innerHTML = '<div class="ab-patch-empty">加载中…</div>';
        var ajax = window.__AB_AJAX__ || {};
        if(!ajax.url){ listEl.innerHTML = '<div class="ab-patch-empty">接口地址缺失</div>'; loading = false; return; }
        var sep = ajax.url.indexOf('?') >= 0 ? '&' : '?';
        var url = ajax.url + sep + 'do=patch-list' + (force ? '&force=1&_=' + encodeURIComponent(ajax.token || '') : '');
        fetch(url, {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(j){
                loading = false;
                if(!j || j.code !== 0){
                    listEl.innerHTML = '<div class="ab-patch-empty">' + esc((j && j.message) || '读取失败') + '</div>';
                    return;
                }
                render(j.data || {});
            })
            .catch(function(){
                loading = false;
                listEl.innerHTML = '<div class="ab-patch-empty">读取失败，请刷新页面重试</div>';
            });
    }

    var rb = document.getElementById('ab-patch-card-refresh');
    if(rb) rb.addEventListener('click', function(){ load(false); });
    var cb = document.getElementById('ab-patch-card-check');
    if(cb) cb.addEventListener('click', function(){ load(true); });

    /* 应用 / 回滚结束后自动刷新状态 */
    document.addEventListener('ab:patchdone', function(){ load(false); });

    /* 本卡片在 <body> 里就执行了，而 __AB_AJAX__ 要等页脚输出才有 → 轮询等待 */
    var tries = 0;
    function boot(){
        if(window.__AB_AJAX__ && window.__AB_AJAX__.url){ load(false); return; }
        if(++tries > 100){ listEl.innerHTML = '<div class="ab-patch-empty">接口未就绪，请刷新页面</div>'; return; }
        setTimeout(boot, 100);
    }
    boot();
})();
</script>
