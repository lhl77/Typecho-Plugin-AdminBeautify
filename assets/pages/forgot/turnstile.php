<script>
(function () {
  'use strict';

  window.__AB_TURNSTILE__ = {
    login: <?php echo $jsTurnstileLogin; ?>,
    register: <?php echo $jsTurnstileRegister; ?>,
    forgot: <?php echo $jsTurnstileForgot; ?>
  };

  var SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
  var WAIT_MS = 12000;   /* 点了提交之后，等组件吐出令牌的上限 */

  /* ---- 加载官方脚本（幂等；无论成败都要回调）---- */
  function loadTurnstile(cb) {
    if (typeof window.turnstile !== 'undefined') { cb(true); return; }

    var called = false;
    var done = function (ok) { if (called) return; called = true; cb(!!ok); };

    var s = document.getElementById('ab-turnstile-api');
    if (s) {
      s.addEventListener('load', function () { done(true); });
      s.addEventListener('error', function () { done(false); });
      /* 标签在、对象还没出来：再给它一点时间 */
      setTimeout(function () { done(typeof window.turnstile !== 'undefined'); }, 1500);
      return;
    }

    s = document.createElement('script');
    s.id = 'ab-turnstile-api';
    s.src = SCRIPT_URL;
    s.async = true;
    s.defer = true;
    s.onload = function () { done(true); };
    s.onerror = function () { done(false); };
    document.head.appendChild(s);
  }

  /* ---- 提交拦截（capture 阶段，赶在页面上其它 submit 处理器之前）----
       令牌已就绪 → 放行；
       组件在算令牌 → 先拦住，令牌到手自动替你接着提交（不留死胡同）；
       组件没加载出来 → 说清原因 + 给「重新加载验证」按钮。 */
  function makeGate(form) {
    form.addEventListener('submit', function (e) {
      var st = form.__abTurnstile;
      if (!st || !st.wrap || !form.contains(st.wrap)) return;  // 当前界面没挂验证
      if (st.token) return;                                    // 已通过

      e.preventDefault();
      e.stopImmediatePropagation();

      if (st.loadFailed || typeof window.turnstile === 'undefined') {
        st.showError('人机验证组件没能加载，请检查网络后点「重新加载验证」', true);
        return;
      }

      st.showError('正在完成人机验证，请稍候…', false);
      st.pending = true;
      if (st.timer) clearTimeout(st.timer);
      st.timer = setTimeout(function () {
        st.pending = false;
        st.showError('人机验证一直没有完成，请点「重新加载验证」或刷新页面重试', true);
      }, WAIT_MS);
    }, true);
  }

  /* 挂载（可重复调用：表单 innerHTML 被替换后再次调用即可重建组件） */
  window.__AB_TURNSTILE_ATTACH__ = function (form, siteKey) {
    if (!form || !siteKey) return;

    if (!form.__abTurnstile) {
      form.__abTurnstile = {
        token: '', wrap: null, widgetId: null,
        pending: false, timer: null, loadFailed: false
      };
      makeGate(form);
    }
    var st = form.__abTurnstile;

    var wrap = form.querySelector('.ab-turnstile-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'ab-turnstile-wrap';

      /* 小标题：说明这块是干什么的（Cloudflare 的组件本身没有文案） */
      var cap = document.createElement('div');
      cap.className = 'ab-turnstile-cap';
      cap.innerHTML =
        '<svg class="ab-fp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        + ' stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        + '<path d="M12 3 5 6v5.4c0 4.2 2.9 7.7 7 8.6 4.1-.9 7-4.4 7-8.6V6l-7-3Z"/>'
        + '<path d="m9.2 12 2.1 2.1 3.9-4.1"/></svg>'
        + '<span>人机验证</span>';
      wrap.appendChild(cap);

      var box = document.createElement('div');
      box.className = 'ab-turnstile-box';
      wrap.appendChild(box);

      var err = document.createElement('div');
      err.className = 'ab-turnstile-error';
      err.innerHTML = '<span class="ab-turnstile-error-text"></span>'
        + '<button type="button" class="ab-turnstile-retry">重新加载验证</button>';
      err.style.display = 'none';
      wrap.appendChild(err);

      /* 位置：提交按钮（登录 / 注册 / 下一步）之上，先验证再提交。
         .lb-submit 在 <p class="submit"> 里，所以插到整个提交段落之前。 */
      var anchor = form.querySelector('p.submit')
        || form.querySelector('.lb-submit')
        || form.querySelector('button[type="submit"]');
      if (anchor && anchor.parentNode) {
        anchor.parentNode.insertBefore(wrap, anchor);
      } else {
        form.appendChild(wrap);
      }
    }
    st.wrap = wrap;

    var errText = wrap.querySelector('.ab-turnstile-error-text');
    var retryBtn = wrap.querySelector('.ab-turnstile-retry');

    st.showError = function (msg, withRetry) {
      if (errText) errText.textContent = msg;
      var err = wrap.querySelector('.ab-turnstile-error');
      if (err) {
        err.style.display = 'block';
        /* 需要给出「重新加载验证」时是错误态（红），只是等待时用中性提示色 */
        err.classList.toggle('is-info', !withRetry);
      }
      if (retryBtn) retryBtn.style.display = withRetry ? 'inline-flex' : 'none';
    };

    st.hideError = function () {
      var err = wrap.querySelector('.ab-turnstile-error');
      if (err) {
        err.style.display = 'none';
        err.classList.remove('is-info');
      }
      if (retryBtn) retryBtn.style.display = 'none';
    };

    /* 令牌到手：把刚才被拦下的那次提交接着走完 */
    st.resume = function () {
      st.pending = false;
      if (st.timer) { clearTimeout(st.timer); st.timer = null; }
      /* 合成一次 submit：capture 已经拦不住（令牌在手），页面自己的处理器照常接管 */
      try {
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
      } catch (e) {}
    };

    /* token 由 Cloudflare 自己写进组件内部的隐藏域 cf-turnstile-response，
       它就在表单里，new FormData(form) / 原生提交都会带上，无需再自建字段 */
    var render = function () {
      st.token = '';
      st.loadFailed = false;
      st.hideError();

      var box = wrap.querySelector('.ab-turnstile-box');
      if (!box) return;

      loadTurnstile(function (ok) {
        if (!ok || typeof window.turnstile === 'undefined') {
          st.loadFailed = true;
          st.showError('人机验证组件没能加载，请检查网络后点「重新加载验证」', true);
          return;
        }
        box.innerHTML = '';
        try {
          st.widgetId = window.turnstile.render(box, {
            sitekey: siteKey,
            theme: (document.documentElement.getAttribute('data-lb-theme') === 'dark') ? 'dark' : 'light',
            callback: function (t) {
              st.token = t;
              st.loadFailed = false;
              st.hideError();
              if (st.pending) st.resume();
            },
            'expired-callback': function () { st.token = ''; },
            'error-callback': function () {
              st.token = '';
              st.showError('人机验证组件报错，请点「重新加载验证」重试', true);
            }
          });
        } catch (err) {
          st.loadFailed = true;
          st.showError('人机验证组件初始化失败，请刷新页面重试', true);
        }
      });
    };
    st.retry = render;

    if (retryBtn) {
      retryBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        st.pending = false;
        if (st.timer) { clearTimeout(st.timer); st.timer = null; }
        if (st.widgetId) {
          try { window.turnstile.remove(st.widgetId); } catch (err) {}
          st.widgetId = null;
        }
        render();
      });
    }

    render();
  };

  /* 卸载：换步前调用，避免留下没人管的 Widget（Turnstile 会打警告） */
  window.__AB_TURNSTILE_DETACH__ = function (form) {
    var st = form && form.__abTurnstile;
    if (!st) return;
    if (st.timer) { clearTimeout(st.timer); st.timer = null; }
    st.pending = false;
    st.token = '';
    if (!st.widgetId) { st.wrap = null; return; }
    try {
      if (window.turnstile && typeof window.turnstile.remove === 'function') {
        window.turnstile.remove(st.widgetId);
      }
    } catch (e) {}
    st.widgetId = null;
    st.wrap = null;
  };

  /* 登录 / 注册页：自动门控（忘记密码界面由 client.php 单独处理） */
  var onForgot = /[?&]ab-forgot=1(&|$)/.test(location.search);
  if (!onForgot) {
    var f = document.querySelector('form[name="login"]') || document.querySelector('form[name="register"]');
    if (f) {
      var isRegister = location.href.indexOf('register.php') !== -1;
      var key = isRegister ? window.__AB_TURNSTILE__.register : window.__AB_TURNSTILE__.login;
      if (key) {
        try { window.__AB_TURNSTILE_ATTACH__(f, key); } catch (err) {}
      }
    }
  }
})();
</script>
