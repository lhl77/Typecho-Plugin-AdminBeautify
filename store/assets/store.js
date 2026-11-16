(function(){'use strict';var LS_SORT='ab-store-sort';function wrapEl(){return document.querySelector('.abp-wrap');}
function tabsEl(){return document.querySelector('.abp-tabs');}
function barEl(){return document.querySelector('.abp-bar');}
function toolsEl(){return document.querySelector('.abp-bar-tools');}
function listEl(){return document.getElementById('abp-list');}
function sortInput(){return document.getElementById('abp-sort');}
function searchEl(){return document.getElementById('abp-q');}
function batchEl(){return document.getElementById('abp-batch');}
function allRows(){var l=listEl();return l?[].slice.call(l.querySelectorAll('.abp-row')):[];}
function cfg(){var w=wrapEl();var old=window.__AB_STORE__||{};return{ajaxUrl:(w&&w.getAttribute('data-ajax'))||old.ajaxUrl||'',token:(w&&w.getAttribute('data-token'))||old.token||''};}
function closest(e,sel){var t=e&&e.target;return(t&&t.closest)?t.closest(sel):null;}
function collate(a,b){try{return String(a).localeCompare(String(b),'zh-Hans-CN',{numeric:true,sensitivity:'base'});}catch(e){return String(a)<String(b)?-1:(String(a)>String(b)?1:0);}}
function groupOf(name){return/^[a-z0-9]/i.test(name)?0:1;}
function ensureBaseOrder(){var rows=allRows();var max=0;for(var i=0;i<rows.length;i++){var v=parseInt(rows[i].getAttribute('data-sort-idx'),10);if(!isNaN(v)&&v>=max)max=v+1;}
for(var j=0;j<rows.length;j++){if(!rows[j].hasAttribute('data-sort-idx')){rows[j].setAttribute('data-sort-idx',String(max++));}}
return max;}
function applySort(){var list=listEl();if(!list)return;var rows=allRows().filter(function(r){return!(r.closest&&r.closest('.abp-private'));});if(!rows.length)return;ensureBaseOrder();var sel=sortInput();var mode=sel?sel.value:'default';var sorted=rows.slice();if(mode==='name-asc'||mode==='name-desc'){sorted.sort(function(a,b){var na=a.getAttribute('data-sort-name')||'';var nb=b.getAttribute('data-sort-name')||'';var ga=groupOf(na),gb=groupOf(nb);return(ga-gb)||(mode==='name-asc'?collate(na,nb):-collate(na,nb));});}else{sorted.sort(function(a,b){var ia=parseInt(a.getAttribute('data-sort-idx'),10);var ib=parseInt(b.getAttribute('data-sort-idx'),10);if(isNaN(ia))ia=1e9;if(isNaN(ib))ib=1e9;return ia-ib;});}
var same=true;for(var i=0;i<sorted.length;i++){if(sorted[i]!==rows[i]){same=false;break;}}
if(!same){sorted.forEach(function(el){list.appendChild(el);});}}
function syncSortUI(){var menu=document.getElementById('abp-sort-menu');var label=document.getElementById('abp-sort-label');var sel=sortInput();if(!menu||!sel)return;var cur=sel.value;var txt='默认顺序';[].forEach.call(menu.querySelectorAll('[data-sort]'),function(b){var on=b.getAttribute('data-sort')===cur;b.classList.toggle('is-checked',on);if(on)txt=(b.textContent||'').trim();});if(label)label.textContent=txt;}
function applyFilter(){var wrap=wrapEl();var list=listEl();if(!wrap)return;var tab=wrap.getAttribute('data-tab')||'all';var s=searchEl();var q=(s&&s.value?s.value:'').trim().toLowerCase();var rows=allRows();var priv=document.getElementById('abp-private');var privShown=0;rows.forEach(function(row){if(priv&&priv.contains(row))return;var flags=(row.getAttribute('data-flags')||'').split(/\s+/);var okTab=tab==='all'?flags.indexOf('notinstalled')<0:flags.indexOf(tab)>=0;var okQ=!q||(row.getAttribute('data-search')||'').indexOf(q)>=0;row.style.display=(okTab&&okQ)?'':'none';});if(priv){var showSec=(tab==='store');[].forEach.call(priv.querySelectorAll('.abp-row'),function(row){var okQ=!q||(row.getAttribute('data-search')||'').indexOf(q)>=0;var on=showSec&&okQ;row.style.display=on?'':'none';if(on)privShown++;});priv.hidden=!privShown;}
var visible=privShown+rows.filter(function(r){return!(priv&&priv.contains(r))&&r.style.display!=='none';}).length;var empty=document.getElementById('abp-empty-dyn');if(!visible&&list&&rows.length){var icon=q?'search_off':(tab==='update'?'check_circle':'inbox');var text=q?'没有匹配的插件':(tab==='update'?'所有插件都是最新版本':'这个分类下还没有插件');if(!empty){empty=document.createElement('div');empty.id='abp-empty-dyn';empty.className='abp-empty';list.appendChild(empty);}
empty.innerHTML='<span class="material-icons-round">'+icon+'</span><p>'+text+'</p>';}else if(empty&&empty.parentNode){empty.parentNode.removeChild(empty);}
refreshBar();}
function measureTabs(){var t=tabsEl();if(!t)return{text:0,icon:0};t.classList.remove('is-icon');var text=t.scrollWidth;t.classList.add('is-icon');var icon=t.scrollWidth;t.classList.remove('is-icon');return{text:text,icon:icon};}
function revealActiveTab(){var t=tabsEl();if(!t)return;var act=t.querySelector('.is-active');if(!act)return;var tr=t.getBoundingClientRect();var ar=act.getBoundingClientRect();if(ar.right>tr.right){t.scrollLeft+=(ar.right-tr.right)+8;}else if(ar.left<tr.left){t.scrollLeft-=(tr.left-ar.left)+8;}}
function syncTabOverflow(){var t=tabsEl();if(!t)return;var scroll=t.scrollWidth-t.clientWidth>2;t.classList.toggle('is-scrollable',scroll);t.classList.toggle('is-scrolled',t.scrollLeft>2);}
function syncBarLayout(){var b=barEl(),t=tabsEl(),tl=toolsEl();if(!b||!t||!tl)return;b.classList.remove('is-stacked');t.classList.remove('is-icon');var avail=b.clientWidth;var m=measureTabs();var stacked=(m.text+tl.offsetWidth+12)>avail;var icons=(m.text>avail);b.classList.toggle('is-stacked',stacked);t.classList.toggle('is-icon',icons);syncTabOverflow();revealActiveTab();}
var ro=null;function observeBar(){if(!window.ResizeObserver)return;var b=barEl();if(!b)return;if(!ro){ro=new ResizeObserver(function(entries){var w=entries[0].contentRect.width;if(Math.abs(w-(ro.__lastW||0))<1)return;ro.__lastW=w;syncBarLayout();});}
ro.disconnect();ro.__lastW=0;ro.observe(b);}
function selectTab(name){var wrap=wrapEl();var t=tabsEl();if(!wrap||!t||!name)return;wrap.setAttribute('data-tab',name);[].forEach.call(t.querySelectorAll('.abp-tab'),function(x){var on=x.getAttribute('data-tab')===name;x.classList.toggle('is-active',on);x.setAttribute('aria-selected',on?'true':'false');});applyFilter();revealActiveTab();syncTabOverflow();}
function pushUrl(name){if(!window.history||!history.replaceState)return;var url=location.pathname+(name==='all'?'':'?tab='+name);history.replaceState(null,'',url);}
var toastTimer=null;function toast(text,isErr){var el=document.getElementById('abp-toast');if(!el){el=document.createElement('div');el.id='abp-toast';el.className='abp-toast';document.body.appendChild(el);}
el.textContent=text;el.classList.toggle('err',!!isErr);el.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(function(){el.classList.remove('show');},3600);}
var CONFIRM={install:function(name){return'安装「'+name+'」？\n安装包会从 AB Store 下载并解压到 usr/plugins。';},upgrade:function(name){return'把「'+name+'」更新到新版本？\n旧版本会先备份到 usr/plugins/.ab-store/backup。';},uninstall:function(name){return'卸载「'+name+'」？\n插件目录会被删除（已自动备份），该插件的设置数据不会自动删除。';},block:function(name,ver){if(ver==='*'){return'忽略「'+name+'」的所有更新提醒？\n以后有新版也不会再提示，直到在「更多」里恢复提醒。';}
return'忽略 '+name+' v'+ver+' 的更新提醒？（以后有更高版本仍会提示）';},unblock:function(){return'';}};function post(act,data,btn,silent,onSuccess){var c=cfg();if(!c.ajaxUrl)return Promise.reject(new Error('缺少接口地址'));var body=new FormData();body.append('do','store.'+act);body.append('_',c.token);Object.keys(data||{}).forEach(function(k){body.append(k,data[k]);});var label=btn?btn.querySelector('.abp-btn-t'):null;var oldText=label?label.textContent:'';if(btn){btn.disabled=true;if(label)label.textContent='处理中…';}
return fetch(c.ajaxUrl,{method:'POST',body:body,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(res){if(!res||res.code!==0){throw new Error((res&&res.message)||'操作失败');}
if(btn){btn.disabled=false;if(label)label.textContent=oldText;}
if(typeof onSuccess==='function'){onSuccess(res);}else if(!silent){toast(res.message||'已完成');setTimeout(function(){location.reload();},700);}
return res;}).catch(function(err){if(btn){btn.disabled=false;if(label)label.textContent=oldText;}
if(!silent)toast(err.message||'操作失败',true);throw err;});}
function escHtml(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function fmtSize(n){n=Number(n)||0;if(n<1024)return n+' B';if(n<1048576)return(n/1024).toFixed(1)+' KB';return(n/1048576).toFixed(1)+' MB';}
function openModal(title){closeModal();var scrim=document.createElement('div');scrim.id='abp-modal';scrim.className='abp-modal';scrim.innerHTML='<div class="abp-modal-card" role="dialog" aria-modal="true">'
+'<div class="abp-modal-head"><b>'+escHtml(title)+'</b>'
+'<button type="button" class="abp-iconbtn abp-modal-x" aria-label="关闭">'
+'<span class="material-icons-round">close</span></button></div>'
+'<div class="abp-modal-body"></div></div>';document.body.appendChild(scrim);var close=function(){closeModal();};scrim.addEventListener('click',function(e){if(e.target===scrim)close();});scrim.querySelector('.abp-modal-x').addEventListener('click',close);return{scrim:scrim,body:scrim.querySelector('.abp-modal-body'),close:close};}
function closeModal(){var m=document.getElementById('abp-modal');if(m&&m.parentNode)m.parentNode.removeChild(m);}
function zipPrecheck(file,type){return new Promise(function(resolve){var need=(type==='theme')?'index.php':'Plugin.php';if(!file){resolve({ok:false,reason:'没有选择文件'});return;}
if(!/\.zip$/i.test(file.name)){resolve({ok:false,reason:'只接受 .zip 压缩包'});return;}
if(file.size<22){resolve({ok:false,reason:'文件太小，不是有效 zip'});return;}
var tailSize=Math.min(file.size,66000);Promise.all([file.slice(0,4).arrayBuffer(),file.slice(file.size-tailSize,file.size).arrayBuffer()]).then(function(arrs){var head=new Uint8Array(arrs[0]);if(!(head[0]===0x50&&head[1]===0x4b&&(head[2]===3||head[2]===5||head[2]===7))){resolve({ok:false,reason:'不是有效的 ZIP（文件头不对）'});return;}
var tail=new DataView(arrs[1]);var eocd=-1;for(var i=tail.byteLength-22;i>=0;i--){if(tail.getUint32(i,true)===0x06054b50){eocd=i;break;}}
if(eocd<0){resolve({ok:false,reason:'不是有效的 ZIP（找不到目录尾）'});return;}
var count=tail.getUint16(eocd+10,true);var cdSize=tail.getUint32(eocd+12,true);var cdOff=tail.getUint32(eocd+16,true);if(!count){resolve({ok:false,reason:'ZIP 是空的'});return;}
if(cdSize>4*1024*1024||cdOff+cdSize>file.size){resolve({ok:true});return;}
file.slice(cdOff,cdOff+cdSize).arrayBuffer().then(function(cdBuf){var cd=new DataView(cdBuf);var p=0,hasNeed=false,bad=false;for(var n=0;n<count;n++){if(p+46>cd.byteLength)break;if(cd.getUint32(p,true)!==0x02014b50)break;var nlen=cd.getUint16(p+28,true);var xlen=cd.getUint16(p+30,true);var clen=cd.getUint16(p+32,true);var fname='';try{fname=new TextDecoder('utf-8').decode(new Uint8Array(cdBuf,p+46,nlen));}catch(e2){fname='';}
if(fname==='..'||fname.indexOf('../')!==-1||fname.charAt(0)==='/'||/^[A-Za-z]:/.test(fname)){bad=true;}
if(fname===need||fname.slice(-(need.length+1))==='/'+need){hasNeed=true;}
p+=46+nlen+xlen+clen;}
if(bad){resolve({ok:false,reason:'压缩包含非法路径（../ 或绝对路径）'});return;}
if(!hasNeed){resolve({ok:false,reason:'压缩包里没有找到 '+need+'（'+(type==='theme'?'主题需 index.php':'插件需 Plugin.php')+'）'});return;}
resolve({ok:true});}).catch(function(){resolve({ok:false,reason:'读取 zip 目录失败'});});}).catch(function(){resolve({ok:false,reason:'读取文件失败'});});});}
function uploadZip(file,type,onProgress){var c=cfg();return new Promise(function(resolve,reject){if(!c.ajaxUrl){reject(new Error('缺少接口地址'));return;}
var fd=new FormData();fd.append('do',type==='theme'?'store.local-theme-install':'store.local-install');fd.append('_',c.token);fd.append('zip',file,file.name);var xhr=new XMLHttpRequest();xhr.open('POST',c.ajaxUrl,true);xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');if(onProgress){xhr.upload.onprogress=function(ev){if(ev.lengthComputable)onProgress(ev.loaded,ev.total);};}
xhr.onload=function(){var res=null;try{res=JSON.parse(xhr.responseText);}catch(e){res=null;}
if(!res){reject(new Error('服务器返回无法解析（HTTP '+xhr.status+'）'));return;}
if(res.code!==0){reject(new Error(res.message||'安装失败'));return;}
resolve(res);};xhr.onerror=function(){reject(new Error('网络错误'));};xhr.send(fd);});}
function openLocalInstall(){var m=openModal('本地安装插件');m.body.innerHTML=''
+'<div class="abp-up-hint">'
+'<p>上传插件压缩包（<b>.zip</b>）。包内需包含 <code>Plugin.php</code>，建议把文件放在一个顶层目录中：</p>'
+'<pre class="abp-up-tree">MyPlugin/\n  Plugin.php\n  ...</pre>'
+'</div>'
+'<label class="abp-up-pick"><input type="file" accept=".zip,application/zip" hidden id="abp-up-file">'
+'<span class="material-icons-round">upload_file</span><span>选择 zip 文件</span></label>'
+'<div class="abp-up-name" id="abp-up-name"></div>'
+'<div class="abp-up-progress" id="abp-up-progress" hidden>'
+'<div class="abp-up-bar"><i></i></div><div class="abp-up-stat"></div></div>'
+'<div class="abp-up-actions">'
+'<button type="button" class="abp-btn abp-btn-filled" id="abp-up-go" disabled>开始安装</button></div>';var input=m.body.querySelector('#abp-up-file');var nameEl=m.body.querySelector('#abp-up-name');var goBtn=m.body.querySelector('#abp-up-go');var prog=m.body.querySelector('#abp-up-progress');var bar=prog.querySelector('.abp-up-bar > i');var stat=prog.querySelector('.abp-up-stat');var file=null;var checking=false;input.addEventListener('change',function(){file=(input.files&&input.files[0])?input.files[0]:null;goBtn.disabled=true;nameEl.className='abp-up-name';if(!file){nameEl.textContent='';return;}
checking=true;nameEl.classList.add('is-checking');nameEl.textContent='正在检查 '+file.name+'…';zipPrecheck(file,'plugin').then(function(r){checking=false;if(!r.ok){nameEl.className='abp-up-name is-err';nameEl.textContent='✗ '+r.reason;return;}
nameEl.className='abp-up-name is-ok';nameEl.textContent='✓ 格式检查通过：'+file.name+'（'+fmtSize(file.size)+'）';goBtn.disabled=false;});});goBtn.addEventListener('click',function(){if(!file||checking)return;goBtn.disabled=true;prog.hidden=false;stat.classList.remove('is-err');var start=Date.now();uploadZip(file,'plugin',function(loaded,total){var pct=total?Math.min(100,Math.round(loaded/total*100)):0;bar.style.width=pct+'%';var secs=(Date.now()-start)/1000;var speed=secs>0?loaded/secs:0;stat.textContent=pct+'% · '+fmtSize(loaded)+' / '+fmtSize(total)+' · '+fmtSize(speed)+'/s';}).then(function(res){bar.style.width='100%';stat.textContent='安装完成，正在刷新…';toast((res&&res.message)||'安装成功');setTimeout(function(){location.reload();},900);}).catch(function(err){goBtn.disabled=false;stat.classList.add('is-err');stat.textContent='✗ '+(err.message||'安装失败');});});}
function openBackup(){var m=openModal('备份管理');m.body.innerHTML='<div class="abp-bk-empty">正在读取备份…</div>';post('backup-list',{},null,true,function(res){renderBackupPanel(m.body,(res&&res.data&&res.data.items)||[]);}).catch(function(err){m.body.innerHTML='<div class="abp-bk-empty">读取失败：'+escHtml(err.message||'')+'</div>';});}
function renderBackupPanel(body,items){if(!items.length){body.innerHTML='<div class="abp-bk-empty"><span class="material-icons-round">backup</span><p>还没有任何备份</p></div>';return;}
body.innerHTML=''
+'<div class="abp-bk-toolbar">'
+'<label class="abp-bk-search"><span class="material-icons-round">search</span>'
+'<input type="search" placeholder="搜索插件 / 主题" aria-label="搜索备份"></label>'
+'<select class="abp-bk-sort" aria-label="排序">'
+'<option value="date-desc">最新优先</option>'
+'<option value="date-asc">最早优先</option>'
+'<option value="name-asc">名称 A→Z</option>'
+'<option value="name-desc">名称 Z→A</option>'
+'</select>'
+'</div>'
+'<div class="abp-bk-actionbar">'
+'<label class="abp-bk-all"><input type="checkbox" class="abp-bk-check-all">全选</label>'
+'<span class="abp-bk-count"></span>'
+'<button type="button" class="abp-btn abp-btn-outline abp-btn-warn" id="abp-bk-clear" disabled>清空选中</button>'
+'<button type="button" class="abp-btn abp-btn-outline abp-btn-warn" id="abp-bk-clear-all">清空全部</button>'
+'</div>'
+'<div class="abp-bk-list"></div>';var q=body.querySelector('.abp-bk-search input');var sel=body.querySelector('.abp-bk-sort');var countEl=body.querySelector('.abp-bk-count');var listEl=body.querySelector('.abp-bk-list');var allBox=body.querySelector('.abp-bk-check-all');var clearBtn=body.querySelector('#abp-bk-clear');var clearAllBtn=body.querySelector('#abp-bk-clear-all');var state={q:'',sort:'date-desc'};function filtered(){var kw=state.q.toLowerCase();var list=items.filter(function(it){if(!kw)return true;return(it.dir+' '+(it.version||'')+' '+it.type).toLowerCase().indexOf(kw)!==-1;});list.sort(function(a,b){if(state.sort==='date-asc')return a.ts-b.ts;if(state.sort==='name-asc')return String(a.dir).localeCompare(String(b.dir));if(state.sort==='name-desc')return String(b.dir).localeCompare(String(a.dir));return b.ts-a.ts;});return list;}
function checks(){return[].slice.call(listEl.querySelectorAll('.abp-bk-check'));}
function selectedNames(){return checks().filter(function(c){return c.checked;}).map(function(c){return c.value;});}
function refreshSel(){var n=selectedNames().length;var total=checks().length;clearBtn.textContent=n?('清空选中 ('+n+')'):'清空选中';clearBtn.disabled=n===0;if(allBox){allBox.checked=total>0&&n===total;allBox.indeterminate=n>0&&n<total;}}
function paint(){var list=filtered();countEl.textContent='共 '+list.length+' 份';if(!list.length){listEl.innerHTML='<div class="abp-bk-empty">没有匹配的备份</div>';refreshSel();return;}
listEl.innerHTML=list.map(function(it){return'<div class="abp-bk-row">'
+'<label class="abp-bk-pick"><input type="checkbox" class="abp-bk-check" value="'+escHtml(it.name)+'"></label>'
+'<div class="abp-bk-main"><b class="abp-bk-name">'+escHtml(it.dir)+'</b>'
+'<span class="abp-bk-meta">'+(it.type==='theme'?'主题':'插件')
+(it.version?' · v'+escHtml(it.version):'')+'</span></div>'
+'<div class="abp-bk-side"><span class="abp-bk-date">'+escHtml(it.date)+'</span>'
+'<span class="abp-bk-size">'+fmtSize(it.size)+'</span></div>'
+'<button type="button" class="abp-btn abp-btn-filled abp-bk-restore" data-name="'+escHtml(it.name)+'">回滚</button>'
+'</div>';}).join('');checks().forEach(function(c){c.addEventListener('change',refreshSel);});[].forEach.call(listEl.querySelectorAll('.abp-bk-restore'),function(b){b.addEventListener('click',function(){var name=b.getAttribute('data-name');if(!window.confirm('回滚到「'+name+'」？\n当前版本会先自动备份。'))return;b.disabled=true;post('backup-restore',{name:name},b);});});refreshSel();}
q.addEventListener('input',function(){state.q=q.value.trim();paint();});sel.addEventListener('change',function(){state.sort=sel.value;paint();});allBox.addEventListener('change',function(){checks().forEach(function(c){c.checked=allBox.checked;});refreshSel();});clearBtn.addEventListener('click',function(){var names=selectedNames();if(!names.length)return;if(!window.confirm('删除选中的 '+names.length+' 份备份？此操作不可撤销。'))return;clearBtn.disabled=true;post('backup-delete',{names:JSON.stringify(names)},clearBtn,true).then(function(res){toast((res&&res.message)||'已删除');post('backup-list',{},null,true,function(r2){renderBackupPanel(body,(r2&&r2.data&&r2.data.items)||[]);});}).catch(function(err){toast((err&&err.message)||'删除失败',true);refreshSel();});});clearAllBtn.addEventListener('click',function(){if(!window.confirm('确定清空全部备份？此操作不可撤销。'))return;clearAllBtn.disabled=true;post('backup-clear',{},clearAllBtn);});paint();}
function checkBoxes(){return[].slice.call(document.querySelectorAll('.abp-check'));}
function selected(){return checkBoxes().filter(function(c){return c.checked;});}
function can(c,key){var v=c.getAttribute(key)||'';return v!==''&&v!=='0';}
function refreshBar(){var bar=batchEl();if(!bar)return;var sel=selected();bar.hidden=sel.length===0;var cnt=document.getElementById('abp-batch-count');if(cnt)cnt.textContent=sel.length;if(!sel.length)return;var flags={install:sel.some(function(c){return can(c,'data-can-install');}),update:sel.some(function(c){return can(c,'data-can-update');}),disable:sel.some(function(c){return can(c,'data-disable-url');}),uninstall:sel.some(function(c){return can(c,'data-can-uninstall');})};Object.keys(flags).forEach(function(k){var b=bar.querySelector('[data-batch="'+k+'"]');if(b)b.disabled=!flags[k];});}
function runBatch(act,items,btn){var total=items.length;var done=0;var failed=[];var label=btn.querySelector('.abp-btn-t')||btn;var oldText=label.textContent;var queue=items.slice();btn.disabled=true;function step(){if(!queue.length){btn.disabled=false;label.textContent=oldText;if(failed.length){toast('完成 '+done+'/'+total+'，失败：'+failed.join('、'),true);setTimeout(function(){location.reload();},2600);}else{toast('已完成 '+done+'/'+total+'，正在刷新…');setTimeout(function(){location.reload();},700);}
return;}
var c=queue.shift();var dir=c.getAttribute('data-dir');label.textContent=(done+1)+'/'+total;var task;if(act==='disable'){task=fetch(c.getAttribute('data-disable-url'),{credentials:'same-origin'}).then(function(res){if(!res.ok)throw new Error('禁用失败（HTTP '+res.status+'）');return true;});}else if(act==='install'){task=post('install',{id:c.getAttribute('data-id')||'',dir:c.getAttribute('data-install-dir')||dir},null,true).then(function(){return true;});}else{task=post(act==='update'?'upgrade':'uninstall',{dir:dir},null,true).then(function(){return true;});}
task.then(function(){done++;}).catch(function(){failed.push(dir);}).then(step);}
step();}
function menuHost(menu){var row=menu.closest?menu.closest('.abp-row, .abp-private'):null;return row||null;}
function menuLayers(menu){var layers=[];var host=menuHost(menu);if(host)layers.push(host);var sec=menu.closest?menu.closest('.abp-private'):null;if(sec&&sec!==host)layers.push(sec);return layers;}
function openMenu(menu,trigger){if(!menu)return;clearTimeout(menu.__abpClose);menu.hidden=false;void menu.offsetWidth;menu.classList.add('is-open');menuLayers(menu).forEach(function(el){el.classList.add('is-menu-open');});if(trigger)trigger.setAttribute('aria-expanded','true');}
function closeMenu(menu,trigger){if(!menu||menu.hidden)return;menu.classList.remove('is-open');if(trigger)trigger.setAttribute('aria-expanded','false');var layers=menuLayers(menu);clearTimeout(menu.__abpClose);menu.__abpClose=setTimeout(function(){if(menu.classList.contains('is-open'))return;menu.hidden=true;layers.forEach(function(el){el.classList.remove('is-menu-open');});},220);}
function closeAllMenus(except){[].forEach.call(document.querySelectorAll('.abp-menu, .abp-sortmenu'),function(m){if(m===except)return;if(!m.hidden){var t=m.parentNode?m.parentNode.querySelector('[aria-haspopup], .abp-more'):null;closeMenu(m,t);}});}
function parseToken(raw){var s=String(raw||'').trim();if(!s)return null;var m=s.match(/ab-store\.lhl\.one\/plugin\/([A-Za-z0-9_-]+)/i);if(m){var tm=s.match(/[?&]token=([A-Za-z0-9_-]+)/);return{id:m[1],token:tm?tm[1]:''};}
m=s.match(/^([A-Za-z0-9_-]+)\?token=([A-Za-z0-9_-]+)$/);if(m)return{id:m[1],token:m[2]};var parts=s.split(/[\s:+]+/).filter(function(x){return x;});if(parts.length>=2)return{id:parts[0],token:parts[1]};if(/^[A-Za-z0-9_-]+$/.test(s))return{id:s,token:''};return null;}
function storeTabUrl(hash){return location.pathname+'?tab=store'+(hash?'#'+hash:'');}
function ensurePrivateSection(){var sec=document.getElementById('abp-private');if(sec)return sec;var list=listEl();if(!list)return null;sec=document.createElement('section');sec.className='abp-private';sec.id='abp-private';sec.innerHTML='<div class="abp-private-h"><span class="material-icons-round">key</span>'
+'<b>私密插件</b><span class="abp-private-n">口令安装 · 0 个</span></div>';list.insertBefore(sec,list.firstChild);return sec;}
function updatePrivateCount(){var sec=document.getElementById('abp-private');if(!sec)return;var n=sec.querySelectorAll('.abp-row').length;var label=sec.querySelector('.abp-private-n');if(label)label.textContent='口令安装 · '+n+' 个';if(!n)sec.remove();}
function insertStoreRow(data){var list=listEl();if(!list||!data||!data.html)return null;var tmp=document.createElement('div');tmp.innerHTML=String(data.html).trim();var row=tmp.firstElementChild;if(!row)return null;var isPrivate=!!data.private&&!data.installed;var old=data.id?document.querySelector('.abp-row[data-store-id="'+String(data.id).replace(/["'\\]/g,'')+'"]'):null;if(old){if(isPrivate){var sec0=ensurePrivateSection();if(sec0&&old.parentNode!==sec0)sec0.appendChild(old);}
if(old.hasAttribute('data-sort-idx')){row.setAttribute('data-sort-idx',old.getAttribute('data-sort-idx'));}
old.replaceWith(row);}else if(isPrivate){var sec=ensurePrivateSection();(sec||list).appendChild(row);}else{list.insertBefore(row,list.firstElementChild);}
updatePrivateCount();selectTab('store');applySort();applyFilter();syncBarLayout();var target=isPrivate?document.getElementById('abp-private'):row;if(target){try{target.scrollIntoView({block:'center'});}catch(e){target.scrollIntoView();}
target.classList.add('is-flash');setTimeout(function(){target.classList.remove('is-flash');},3200);}
return row;}
function tokenInstall(btn){var opts='输入插件口令或完整分享链接';var ask;if(window.AdminBeautify&&typeof AdminBeautify.prompt==='function'){ask=AdminBeautify.prompt(opts);}else{ask=Promise.resolve(window.prompt(opts,''));}
ask.then(function(val){if(val===null||val===undefined)return;var p=parseToken(val);if(!p){toast('格式不对：请输入插件 ID 或完整分享链接',true);return;}
if(btn)btn.disabled=true;var fail=function(err){if(btn)btn.disabled=false;toast((err&&err.message)||'口令安装失败',true);};post('row',{id:p.id,token:p.token||'',dir:''},null,true).then(function(res){if(btn)btn.disabled=false;var d=(res&&res.data)||{};insertStoreRow(d);toast((res&&res.message)||'已找到插件');}).catch(fail);});}
function jumpFromHash(){var h=String(location.hash||'').replace(/^#/,'');if(!h)return;var el=null;if(h==='abp-private'){el=document.getElementById('abp-private');}else if(h.indexOf('abp-store-')===0){var id=decodeURIComponent(h.slice(10)).replace(/["'\\]/g,'');if(id){var rows=allRows();for(var i=0;i<rows.length;i++){if(rows[i].getAttribute('data-store-id')===id){el=rows[i];break;}}}}
if(!el)return;try{el.scrollIntoView({block:'center'});}catch(e){el.scrollIntoView();}
el.classList.add('is-flash');setTimeout(function(){el.classList.remove('is-flash');},3200);}
document.addEventListener('click',function(e){var wrap=wrapEl();if(!wrap)return;var tab=closest(e,'.abp-tab');if(tab&&tab.closest('.abp-wrap')){e.preventDefault();e.stopPropagation();var name=tab.getAttribute('data-tab');if(name){selectTab(name);pushUrl(name);}
return;}
var go=closest(e,'[data-goto-tab]');if(go&&go.closest('.abp-wrap')){e.preventDefault();e.stopPropagation();selectTab(go.getAttribute('data-goto-tab'));}},true);document.addEventListener('input',function(e){if(e.target&&e.target.id==='abp-q')applyFilter();},true);document.addEventListener('change',function(e){if(e.target&&e.target.classList&&e.target.classList.contains('abp-check'))refreshBar();},true);document.addEventListener('click',function(e){if(!wrapEl())return;var batchBtn=closest(e,'[data-batch]');if(batchBtn){if(batchBtn.disabled)return;var bact=batchBtn.getAttribute('data-batch');if(bact==='clear'){checkBoxes().forEach(function(c){c.checked=false;});refreshBar();return;}
var sel=selected();if(!sel.length)return;var items,ask;if(bact==='install'){items=sel.filter(function(c){return can(c,'data-can-install');});ask='安装选中的 '+items.length+' 个插件？\n安装包会从 AB Store 下载并解压到 usr/plugins。';}else if(bact==='update'){items=sel.filter(function(c){return can(c,'data-can-update');});ask='把选中的 '+items.length+' 个插件更新到最新版？\n旧版本会先备份到 usr/plugins/.ab-store/backup。';}else if(bact==='disable'){items=sel.filter(function(c){return can(c,'data-disable-url');});ask='禁用选中的 '+items.length+' 个插件？';}else if(bact==='uninstall'){items=sel.filter(function(c){return can(c,'data-can-uninstall');});ask='卸载选中的 '+items.length+' 个插件？\n插件目录会被删除（已自动备份），设置数据不会自动删除。';}else{return;}
if(!items.length){toast('选中的插件不支持这个操作',true);return;}
if(!window.confirm(ask))return;runBatch(bact,items,batchBtn);return;}
var sortBtn=closest(e,'#abp-sort-btn');var sortMenu=document.getElementById('abp-sort-menu');if(sortBtn&&sortMenu){e.stopPropagation();if(sortMenu.hidden){closeAllMenus(sortMenu);openMenu(sortMenu,sortBtn);}else{closeMenu(sortMenu,sortBtn);}
return;}
var opt=closest(e,'#abp-sort-menu [data-sort]');if(opt){var s=sortInput();if(s){s.value=opt.getAttribute('data-sort');try{localStorage.setItem(LS_SORT,s.value);}catch(err){}
var sb=document.getElementById('abp-sort-btn');if(sortMenu)closeMenu(sortMenu,sb);syncSortUI();applySort();}
return;}
var localBtn=closest(e,'#abp-local-install');if(localBtn){if(localBtn.disabled)return;e.preventDefault();openLocalInstall();return;}
var backupBtn=closest(e,'#abp-backup');if(backupBtn){e.preventDefault();openBackup();return;}
var tokenBtn=closest(e,'#abp-token');if(tokenBtn){if(tokenBtn.disabled)return;tokenInstall(tokenBtn);return;}
var refreshBtn=closest(e,'#abp-refresh');if(refreshBtn){if(refreshBtn.disabled||refreshBtn.classList.contains('is-loading'))return;refreshBtn.classList.add('is-loading');refreshBtn.disabled=true;var done=function(){refreshBtn.classList.remove('is-loading');refreshBtn.disabled=false;};post('refresh',{},refreshBtn,true).then(function(){try{localStorage.removeItem('ab-store-update-check-ts');}catch(err2){}
setTimeout(function(){location.reload();},750);},function(err){done();toast((err&&err.message)||'刷新失败',true);});return;}
var actEl=closest(e,'[data-store-act]');if(actEl){e.preventDefault();closeAllMenus();var act=actEl.getAttribute('data-store-act');var title=actEl.getAttribute('data-name')||actEl.getAttribute('data-dir')||'该插件';var ver=actEl.getAttribute('data-ver')||actEl.getAttribute('data-version')||'';var askFn=CONFIRM[act];var askText=askFn?askFn(title,ver):'';if(askText&&!window.confirm(askText))return;var data={};['id','dir','version'].forEach(function(k){var v=actEl.getAttribute('data-'+k);if(v)data[k]=v;});var after=null;if((act==='install'||act==='upgrade')&&data.id){var sid=data.id;after=function(res){if(act!=='upgrade')toast((res&&res.message)||'已完成');setTimeout(function(){location.href=storeTabUrl('abp-store-'+sid);},700);};}
post(act,data,actEl,false,after).catch(function(){});return;}
var more=closest(e,'.abp-more');var insideMenu=closest(e,'.abp-menu');var moreMenu=more?more.parentNode.querySelector('.abp-menu'):null;closeAllMenus(moreMenu||insideMenu);if(more&&moreMenu){if(moreMenu.hidden)openMenu(moreMenu,more);else closeMenu(moreMenu,more);}});document.addEventListener('scroll',function(e){var t=tabsEl();if(t&&e.target===t)syncTabOverflow();},true);var lastWrap=null;function init(){var w=wrapEl();if(!w)return;lastWrap=w;var s=sortInput();if(s){try{var saved=localStorage.getItem(LS_SORT);if(saved)s.value=saved;}catch(e){}}
syncSortUI();applySort();applyFilter();syncBarLayout();observeBar();jumpFromHash();}
function ensure(){var w=wrapEl();if(!w){lastWrap=null;return;}
if(w!==lastWrap)init();}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
document.addEventListener('ab:pageload',function(){setTimeout(ensure,0);});document.addEventListener('click',function(){setTimeout(ensure,0);},true);document.addEventListener('focusin',function(){setTimeout(ensure,0);},true);window.addEventListener('resize',syncBarLayout);})();