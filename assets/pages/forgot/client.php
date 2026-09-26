<?php
/**
 * 「忘记密码」前端控制器
 *
 * 由 AdminBeautify_Plugin::renderLoginScript() 在**后台登录页**上注入（登录页脚本
 * 之后），把忘记密码界面直接长进登录卡片的 DOM 里：
 *
 *   /admin/login.php?ab-forgot=1  ← 地址栏始终只有这一个地址
 *        ↓ POST( format=json )
 *   插件接口 assets/pages/forgot/forgot-password.php
 *        ↓ { step, title, html, r, g }
 *   把 html 换进卡片里的表单 → 标签浮起 / 加载态 / 复制验证串等交互都在前端补齐
 *
 * 为什么这么做（对应之前踩的三个坑）：
 *   1. 地址栏不会再出现 ?step=choose&r=6152… 这种参数，刷新也不会因为丢参数回到第一步
 *      （步骤存在 sessionStorage 里，刷新后自动恢复到原来那一步）；
 *   2. 界面在真正的登录页里，后台 CSS（normalize/grid/style）由 admin/header.php 用
 *      Typecho API 正确加载，.sr-only 生效 → 输入框上方不会再多出一行纯文字标签；
 *   3. 「返回登录页面」用的是登录页自己给出的 URL，后台目录改名也不会跳错。
 *
 * 调用方需先定义：
 *
 * @var string $jsForgotApiUrl 忘记密码接口 URL（已带 ?format=json），JS 安全字符串
 * @var string $jsLoginUrl     后台登录页 URL（不带 ab-forgot），JS 安全字符串
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
?>
<script id="ab-forgot-client">
(function(){
  var API = <?php echo isset($jsForgotApiUrl) ? $jsForgotApiUrl : "''"; ?>;
  var LOGIN = <?php echo isset($jsLoginUrl) ? $jsLoginUrl : "''"; ?>;

  /* 只在登录页带 ?ab-forgot=1 时接管 */
  if (!/[?&]ab-forgot=1(&|$)/.test(location.search)) return;
  if (!API) return;

  var card = document.querySelector('.lb-card');
  var loginForm = document.querySelector('.lb-form');
  if (!card || !loginForm) return;

  var STATE_KEY = 'ab-forgot-state';
  var cur = { step: '', r: '', g: '' };
  var inflight = false;

  function readState() {
    try { return JSON.parse(sessionStorage.getItem(STATE_KEY) || 'null'); } catch (e) { return null; }
  }
  function writeState(s) {
    try {
      if (s) sessionStorage.setItem(STATE_KEY, JSON.stringify(s));
      else sessionStorage.removeItem(STATE_KEY);
    } catch (e) {}
  }

  /* ---- 换成一个全新表单：登录脚本挂在原表单上的装饰/提交拦截不会干扰 ---- */
  var form = document.createElement('form');
  form.className = 'lb-form';
  form.setAttribute('method', 'post');
  form.setAttribute('action', '#');
  loginForm.parentNode.replaceChild(form, loginForm);
  card.classList.add('ab-fp-active');   /* 隐藏「下次自动登录」（登录页专属选项） */

  var TICK = '<svg class="ab-fp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
           + ' stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
           + '<path d="m5 12.6 4.4 4.4L19 7.6"/></svg>';

  function setTitle(t) {
    var sub = card.querySelector('.lb-title .sub');
    if (sub && t) sub.textContent = t;
  }

  /* ---- 加载态 ----
     主按钮转圈；被点的那张「验证方式」卡片也转圈并改成进行中文案，
     同时把该步所有按钮原生 disable —— 发信要等 SMTP，必须挡住连点（否则会重复发验证码）。
     发送较久时把文案换成「仍在发送…」，免得用户以为卡死了。 */
  var BUSY_TITLE = { mail: '正在发送验证码…', file: '正在准备…' };
  var SLOW_TITLE = { mail: '仍在发送验证码，请稍候…', file: '仍在准备，请稍候…' };
  var slowTimer = null;

  function setBusy(on, trigger) {
    if (slowTimer) { clearTimeout(slowTimer); slowTimer = null; }

    var wrap = form.querySelector('.lb-submit');
    if (wrap) {
      wrap.classList.toggle('is-loading', !!on);
      var btn = wrap.querySelector('button');
      if (btn) btn.setAttribute('aria-busy', on ? 'true' : 'false');
    }

    var isCard = !!(trigger && trigger.classList && trigger.classList.contains('ab-fp-method'));

    Array.prototype.forEach.call(form.querySelectorAll('.ab-fp-method'), function (card) {
      var title = card.querySelector('.ab-fp-method-title');
      if (title && !title.getAttribute('data-ab-title')) {
        title.setAttribute('data-ab-title', title.textContent);
      }

      var busy = !!on && card === trigger;
      if (busy && !card.querySelector('.ab-fp-spinner')) {
        var sp = document.createElement('span');
        sp.className = 'ab-fp-spinner';
        sp.setAttribute('aria-hidden', 'true');
        card.appendChild(sp);
      }

      card.classList.toggle('is-loading', busy);
      card.setAttribute('aria-busy', busy ? 'true' : 'false');
      card.disabled = !!on;
      if (title) {
        title.textContent = busy
          ? (BUSY_TITLE[card.value] || '处理中…')
          : (title.getAttribute('data-ab-title') || title.textContent);
      }
    });

    /* 次级操作（重新开始 / 重新发送）也转圈并禁用 */
    Array.prototype.forEach.call(form.querySelectorAll('[data-ab-fp-action]'), function (el) {
      var busy = !!on && el === trigger;
      if (busy && !el.querySelector('.ab-fp-spinner')) {
        var sp2 = document.createElement('span');
        sp2.className = 'ab-fp-spinner';
        sp2.setAttribute('aria-hidden', 'true');
        el.appendChild(sp2);
      }
      el.classList.toggle('is-loading', busy);
      el.setAttribute('aria-busy', busy ? 'true' : 'false');
      el.disabled = !!on;
    });

    /* 15 秒还没回来：把文案换成「仍在发送…」，仍然禁用（防止重复发信） */
    if (on && isCard) {
      slowTimer = setTimeout(function () {
        slowTimer = null;
        if (!trigger.classList.contains('is-loading')) return;
        var t = trigger.querySelector('.ab-fp-method-title');
        if (t) t.textContent = SLOW_TITLE[trigger.value] || '仍在处理，请稍候…';
      }, 15000);
    }
  }
  function showError(msg) {
    var n = document.createElement('div');
    n.className = 'ab-fp-note ab-fp-note--error';
    n.innerHTML = '<div></div>';
    n.firstChild.textContent = String(msg);
    form.insertBefore(n, form.firstChild);
  }

  function showLoading() {
    form.innerHTML = '<div class="ab-fp-note"><div>正在加载…</div></div>';
  }

  /* ---- 浮动标签的浮起状态（与登录页脚本同一套逻辑）---- */
  function bindFields() {
    var fields = form.querySelectorAll('.lb-field--md3');
    Array.prototype.forEach.call(fields, function (field, idx) {
      var input = field.querySelector('input');
      if (!input) return;

      var sync = function () {
        if (input.value) field.classList.add('is-filled');
        else field.classList.remove('is-filled');
      };

      input.addEventListener('focus', function () { field.classList.add('is-focused'); sync(); });
      input.addEventListener('blur', function () { field.classList.remove('is-focused'); sync(); });
      input.addEventListener('input', sync);
      input.addEventListener('change', sync);
      input.addEventListener('animationstart', sync);

      field.style.animationDelay = (0.12 + idx * 0.07).toFixed(2) + 's';
      sync();
    });
  }

  /* ---- 有效时间倒计时（本地文件验证步）---- */
  function bindCountdown() {
    var els = form.querySelectorAll('[data-ab-countdown]');
    Array.prototype.forEach.call(els, function (el) {
      if (el.__abCountdown) return;
      el.__abCountdown = true;

      var leftEl = el.querySelector('[data-ab-countdown-left]');
      var total = parseInt(el.getAttribute('data-ab-countdown'), 10);
      if (!leftEl || isNaN(total)) return;

      var tick = function () {
        if (total <= 0) {
          el.classList.add('is-expired');
          leftEl.textContent = '已过期，请重新开始';
          return;
        }
        var m = Math.floor(total / 60);
        var s = total % 60;
        leftEl.textContent = '剩余 ' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
      };

      tick();
      var timer = setInterval(function () {
        if (!document.body.contains(el)) { clearInterval(timer); return; }
        total--;
        if (total < 0) {
          clearInterval(timer);
          el.classList.add('is-expired');
          leftEl.textContent = '已过期，请重新开始';
          return;
        }
        tick();
      }, 1000);
    });
  }

  function render(res) {
    cur = { step: res.step || '', r: res.r || '', g: res.g || '' };

    // 换步前先卸载上一屏的 Turnstile Widget（componnet 被 innerHTML 冲掉会留下告警）
    if (typeof window.__AB_TURNSTILE_DETACH__ === 'function') {
      try { window.__AB_TURNSTILE_DETACH__(form); } catch (e) {}
    }

    form.innerHTML = res.html || '';
    setTitle(res.title);
    bindFields();
    bindCountdown();

    // 忘记密码「填写邮箱」步：挂载 Cloudflare Turnstile（若已启用）
    // 注意：提交邮箱的那一步 step 名是 email（mail 是「输入收到的验证码」那一步）
    if (res.step === 'email'
        && typeof window.__AB_TURNSTILE__ !== 'undefined'
        && window.__AB_TURNSTILE__.forgot
        && typeof window.__AB_TURNSTILE_ATTACH__ === 'function') {
      try { window.__AB_TURNSTILE_ATTACH__(form, window.__AB_TURNSTILE__.forgot); } catch (e) {}
    }

    writeState(res.step === 'done' ? null : cur);

    var first = form.querySelector('input[type="text"],input[type="password"],input[type="email"]');
    if (first) { try { first.focus(); } catch (e) {} }
  }

  function handle(res) {
    if (!res || !res.html) {
      showError((res && res.error) || '操作失败，请稍后重试');
      return;
    }
    render(res);
  }

  function post(extra) {
    var fd = new FormData();
    if (extra) {
      Object.keys(extra).forEach(function (k) { fd.set(k, extra[k]); });
    }
    return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { error: '网络错误，请检查连接后重试' }; });
  }

  function sendFormData(fd) {
    return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { error: '网络错误，请检查连接后重试' }; });
  }

  /* ---- 提交（主按钮 / 方法卡片都走这里）---- */
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (inflight) return;

    var fd = new FormData(form);
    if (e.submitter && e.submitter.name) {
      fd.set(e.submitter.name, e.submitter.value);
    }

    var action = String(fd.get('action') || '');

    /* 完成页：直接回登录页，并清掉续接状态 */
    if (action === 'back') {
      writeState(null);
      location.href = LOGIN;
      return;
    }

    inflight = true;
    setBusy(true, e.submitter);
    sendFormData(fd).then(function (res) {
      inflight = false;
      setBusy(false);
      handle(res);
    });
  }, false);

  /* ---- 次级操作 / 复制验证串 ---- */
  form.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) return;

    var copyBtn = t.closest('.ab-fp-copy');
    if (copyBtn) {
      var val = form.querySelector('.ab-fp-token-val');
      var text = val ? (val.textContent || '').trim() : '';
      if (!text) return;

      var icon = copyBtn.innerHTML;
      var done = function () {
        copyBtn.innerHTML = TICK;
        setTimeout(function () { copyBtn.innerHTML = icon; }, 1600);
      };
      var fallback = function () {
        try {
          var r = document.createRange();
          r.selectNodeContents(val);
          var s = window.getSelection();
          s.removeAllRanges();
          s.addRange(r);
          done();
        } catch (err) {}
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, fallback);
      } else {
        fallback();
      }
      return;
    }

    var act = t.closest('[data-ab-fp-action]');
    if (!act) return;
    e.preventDefault();
    if (inflight) return;

    var name = act.getAttribute('data-ab-fp-action');
    if (name !== 'restart' && name !== 'resend') return;

    inflight = true;
    setBusy(true, act);
    post({ action: name, r: cur.r }).then(function (res) {
      inflight = false;
      setBusy(false);
      handle(res);
    });
  }, false);

  /* ---- 启动：有续接状态就恢复到那一步，否则从第一步开始 ---- */
  showLoading();
  setTitle('找回密码');

  var st = readState();
  var boot;
  if (st && st.step && st.step !== 'done') {
    boot = post({ action: 'render', step: st.step, r: st.r || '', g: st.g || '' });
  } else {
    boot = post({ action: 'start' });
  }

  boot.then(function (res) {
    if (!res || !res.html) {
      /* 接口不可用时至少交代清楚，而不是留一个空的登录卡片 */
      form.innerHTML = '';
      showError((res && res.error) || '找回密码功能暂时不可用，请稍后重试');
      return;
    }
    render(res);
  });
})();
</script>
