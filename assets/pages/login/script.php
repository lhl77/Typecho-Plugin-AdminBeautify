<?php
/**
 * 登录页底部 JS 模板
 *
 * 由 AdminBeautify_Plugin::renderLoginFooter() 通过 include 调用。
 * 调用方在 include 前已确保以下变量均已定义：
 *
 * @var string $jsShowSiteName  是否显示站点名称，JS 布尔字符串 true / false
 * @var string $jsShowToggle    是否显示主题切换按钮，JS 布尔字符串 true / false
 * @var string $jsSiteTitle     站点标题的 JS 安全字符串（已转义）
 * @var string $customJs        自定义 JS 原始字符串
 */
?>

<script id="loginbeautify-main">
(function(){
    function qs(sel, root){ return (root||document).querySelector(sel); }
    function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

    var form = qs('form[action*="login"]') || qs('form') || qs('.typecho-login form') || qs('.typecho-login');
    if (!form) return;

    var wrap = document.createElement('div');
    wrap.className = 'lb-wrap';

    var bg = document.createElement('div');
    bg.className = 'lb-bg';
    wrap.appendChild(bg);

    var overlay = document.createElement('div');
    overlay.className = 'lb-bg-overlay';
    wrap.appendChild(overlay);

    var card = document.createElement('div');
    card.className = 'lb-card';

    var head = document.createElement('div');
    head.className = 'lb-head';

    var titleWrap = document.createElement('div');
    titleWrap.className = 'lb-title';

    var showSiteName = <?php echo $jsShowSiteName; ?>;

    if (showSiteName) {
      var name = document.createElement('div');
      name.className = 'name';
      name.textContent = <?php echo $jsSiteTitle; ?>;
      titleWrap.appendChild(name);
    }

    var isRegister = location.href.indexOf('register.php') !== -1;

    var sub = document.createElement('div');
    sub.className = 'sub';
    sub.textContent = isRegister ? '注册' : '登录';
    titleWrap.appendChild(sub);

    // 顶部图标徽章（内联 SVG，不依赖图标字体）
    var badge = document.createElement('div');
    badge.className = 'lb-badge';
    badge.setAttribute('aria-hidden', 'true');
    badge.innerHTML = isRegister
      ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
          + '<circle cx="10" cy="8" r="3.7"/><path d="M3.6 20c.5-3.6 3.2-5.6 6.4-5.6 1 0 1.9.2 2.7.5"/>'
          + '<path d="M18.4 8.4v5M15.9 10.9h5"/></svg>'
      : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'
          + '<rect x="3.6" y="10.4" width="16.8" height="10.8" rx="4"/>'
          + '<path d="M7.6 10.4V7.2a4.4 4.4 0 0 1 8.8 0v3.2"/>'
          + '<circle cx="12" cy="15.8" r="1.5" fill="currentColor" stroke="none"/></svg>';
    card.appendChild(badge);

    head.appendChild(titleWrap);
    card.appendChild(head);

    form.classList.add('lb-form');

    var inputs = qsa('input[type="text"], input[type="password"], input[type="email"]', form);
    inputs.forEach(function(input, idx){
      var field = document.createElement('div');
      /* lb-field--md3 → 启用 MD3 浮动标签样式；若本脚本未执行则回退为经典布局 */
      field.className = 'lb-field lb-field--md3';

      /* 浮动标签用 span 承载视觉文案，
         无障碍名称继续由 Typecho 原生的 sr-only <label for> 提供，避免被读屏重复朗读 */
      var label = document.createElement('span');
      label.className = 'lb-field-label';
      label.setAttribute('aria-hidden', 'true');

      var n = (input.getAttribute('name') || '').toLowerCase();
      var t = (input.getAttribute('type') || '').toLowerCase();
      var ph = input.getAttribute('placeholder') || '';

      if (isRegister) {
        if (idx === 0) {
          label.textContent = '用户名';
          if (!ph) input.setAttribute('placeholder', '请输入用户名');
        } else if (idx === 1 || t === 'email' || n === 'mail') {
          label.textContent = '邮箱';
          if (!ph) input.setAttribute('placeholder', '请输入邮箱');
        } else {
          label.textContent = '输入';
          if (!ph) {
            input.setAttribute('placeholder', '请输入内容');
          }
        }
      } else {
        if (n.indexOf('name') !== -1 || n.indexOf('user') !== -1) {
          label.textContent = '用户名/邮箱';
          if (!ph) input.setAttribute('placeholder', '用户名/邮箱');
        } else if (n.indexOf('pass') !== -1) {
          label.textContent = '密码';
          if (!ph) {
            input.setAttribute('placeholder', '请输入密码');
          }
        } else {
          label.textContent = '输入';
          if (!ph) {
            input.setAttribute('placeholder', '请输入内容');
          }
        }
      }

      var parent = input.parentNode;
      parent.insertBefore(field, input);
      field.appendChild(label);
      field.appendChild(input);

      /* 依次浮现：字段逐个延迟入场，呼应卡片的入场动画 */
      field.style.animationDelay = (0.12 + idx * 0.07).toFixed(2) + 's';

      /* MD3 文本域不保留 placeholder：内容提示完全由浮动标签承担，
         否则未聚焦时 placeholder 会与标签文字重叠 */
      input.removeAttribute('placeholder');

      /* 补充 autocomplete，浏览器/密码管理器填充后标签能正确上浮 */
      if (!isRegister && !input.getAttribute('autocomplete')) {
        input.setAttribute('autocomplete', t === 'password' ? 'current-password' : 'username');
      }

      /* 浮动标签状态同步：聚焦 → is-focused，有内容 → is-filled */
      var sync = function(){
        if (input.value) {
          field.classList.add('is-filled');
        } else {
          field.classList.remove('is-filled');
        }
      };

      input.addEventListener('focus', function(){ field.classList.add('is-focused'); sync(); });
      input.addEventListener('blur',  function(){ field.classList.remove('is-focused'); sync(); });
      input.addEventListener('input', sync);
      input.addEventListener('change', sync);
      /* Chrome 自动填充会触发 animationstart，用它兜住自动填充场景 */
      input.addEventListener('animationstart', sync);

      sync();
      /* 浏览器自动填充往往发生在本脚本之后，补几次状态刷新 */
      setTimeout(sync, 80);
      setTimeout(sync, 400);
      setTimeout(sync, 1200);
    });

    var remember = qs('input[type="checkbox"]', form);
    if (remember) {
      var rememberWrap = remember.closest('p') || remember.parentNode;
      if (rememberWrap) {
        rememberWrap.classList.add('lb-remember');
      }
      // PWA 独立模式下自动勾选「记住我」，防止关闭应用后 session cookie 丢失需重新登录
      var isStandalone = window.matchMedia('(display-mode: standalone)').matches
                      || window.navigator.standalone === true;
      if (isStandalone && !remember.checked) {
        remember.checked = true;
      }
    }

    var submit = qs('input[type="submit"], button[type="submit"]', form);
    if (submit) {
      var submitWrap = document.createElement('div');
      submitWrap.className = 'lb-submit';
      /* 跟在最后一个字段之后浮现 */
      submitWrap.style.animationDelay = (0.12 + inputs.length * 0.07 + 0.05).toFixed(2) + 's';
      var p = submit.parentNode;
      p.insertBefore(submitWrap, submit);
      submitWrap.appendChild(submit);

      var isInputBtn = submit.tagName === 'INPUT';

      /* —— 文字层 + 加载指示器（MD3 按钮两态切换） —— */
      if (!isInputBtn) {
        var btnText = (submit.textContent || '').trim() || '提交';
        submit.textContent = '';

        var btnLabel = document.createElement('span');
        btnLabel.className = 'lb-btn-label';
        btnLabel.textContent = btnText;
        submit.appendChild(btnLabel);

        var btnSpinner = document.createElement('span');
        btnSpinner.className = 'lb-btn-spinner';
        btnSpinner.setAttribute('aria-hidden', 'true');
        submit.appendChild(btnSpinner);
      }

      /* —— 按压涟漪（从指针位置扩散，MD3 ripple） —— */
      if (!isInputBtn) {
        submit.addEventListener('pointerdown', function(ev){
          if (submitWrap.classList.contains('is-loading')) return;

          var rect = submit.getBoundingClientRect();
          var size = Math.max(rect.width, rect.height);
          var cx = ev.clientX || (rect.left + rect.width / 2);
          var cy = ev.clientY || (rect.top + rect.height / 2);

          var ripple = document.createElement('span');
          ripple.className = 'lb-ripple';
          ripple.style.width  = size + 'px';
          ripple.style.height = size + 'px';
          ripple.style.left   = (cx - rect.left - size / 2) + 'px';
          ripple.style.top    = (cy - rect.top  - size / 2) + 'px';
          submit.appendChild(ripple);

          setTimeout(function(){
            if (ripple.parentNode) ripple.parentNode.removeChild(ripple);
          }, 600);
        });
      }

      /* —— 提交：先播放加载动画，再真正提交 —— */
      var lbSubmitting = false;
      var originalBtnValue = '';

      form.addEventListener('submit', function(ev){
        if (lbSubmitting) return;
        lbSubmitting = true;

        /* 能触发 submit 事件即说明浏览器原生校验已通过，可安全地手动提交 */
        ev.preventDefault();

        submitWrap.classList.add('is-loading');
        submit.setAttribute('aria-busy', 'true');
        if (isInputBtn) {
          originalBtnValue = submit.value;
          submit.value = '处理中…';
        }

        /* 让加载动画先绘制出画面，否则浏览器会立即跳转，动画完全不可见 */
        setTimeout(function(){
          try {
            HTMLFormElement.prototype.submit.call(form);
          } catch (e) {
            form.submit();
          }
        }, 220);

        /* 兜底：8 秒后仍未离开本页（提交被拦截 / 网络挂起）则恢复按钮可点击 */
        setTimeout(function(){
          if (!submitWrap.parentNode) return;
          if (!submitWrap.classList.contains('is-loading')) return;
          submitWrap.classList.remove('is-loading');
          submit.removeAttribute('aria-busy');
          lbSubmitting = false;
          if (isInputBtn) submit.value = originalBtnValue;
        }, 8000);
      }, false);
    }

    card.appendChild(form);
    wrap.appendChild(card);

    document.body.insertBefore(wrap, document.body.firstChild);

    var typechoLogin = qs('.typecho-login');
    if (typechoLogin && !typechoLogin.contains(wrap)) {
      typechoLogin.classList.add('lb-hide');
    }

    // 将 Typecho 原生 .more-link（返回首页 / 用户注册）移入卡片并应用 MD3 样式
    var moreLink = qs('p.more-link') || qs('.more-link');
    if (moreLink) {
      moreLink.classList.add('lb-more-link');
      var homeIconSvg = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>';
      var regIconSvg = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
      moreLink.querySelectorAll('a').forEach(function(a) {
        var txt = a.textContent.trim();
        var icon = (txt.indexOf('\u9996\u9875') !== -1 || txt.indexOf('\u8fd4\u56de') !== -1) ? homeIconSvg : regIconSvg;
        a.innerHTML = icon + '<span>' + txt + '</span>';
      });
      // 清除原有分隔符文本节点（" • " 等）
      Array.from(moreLink.childNodes).forEach(function(node) {
        if (node.nodeType === Node.TEXT_NODE) node.textContent = '';
      });
      card.appendChild(moreLink);
    }

    /* 版权信息：放在卡片之外的页面页脚，文案为 Github AB Admin · by LHL */
    var lbFooter = document.createElement('div');
    lbFooter.className = 'lb-footer-theme';
    lbFooter.innerHTML =
      '<span class="lb-footer-pill">Github ' +
        '<a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify" target="_blank" rel="noopener noreferrer">AB Admin</a>' +
        ' \u00b7 by ' +
        '<a href="https://lhl.one" target="_blank" rel="noopener noreferrer">LHL</a>' +
      '</span>';
    document.body.appendChild(lbFooter);

    var showToggle = <?php echo $jsShowToggle; ?>;
    if (showToggle) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'lb-theme-toggle';
      btn.setAttribute('aria-label', '切换主题');

      var sunIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      sunIcon.setAttribute('viewBox', '0 0 24 24');
      sunIcon.setAttribute('fill', 'none');
      sunIcon.setAttribute('stroke', 'currentColor');
      sunIcon.setAttribute('stroke-width', '2');
      sunIcon.setAttribute('stroke-linecap', 'round');
      sunIcon.setAttribute('stroke-linejoin', 'round');
      sunIcon.setAttribute('class', 'lb-icon-sun');
      sunIcon.innerHTML = '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>';

      var moonIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      moonIcon.setAttribute('viewBox', '0 0 24 24');
      moonIcon.setAttribute('fill', 'none');
      moonIcon.setAttribute('stroke', 'currentColor');
      moonIcon.setAttribute('stroke-width', '2');
      moonIcon.setAttribute('stroke-linecap', 'round');
      moonIcon.setAttribute('stroke-linejoin', 'round');
      moonIcon.setAttribute('class', 'lb-icon-moon');
      moonIcon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';

      btn.appendChild(sunIcon);
      btn.appendChild(moonIcon);

      btn.addEventListener('click', function(){
        var cur = document.documentElement.getAttribute('data-lb-theme') === 'dark' ? 'dark' : 'light';
        var next = cur === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-lb-theme', next);
        try{ localStorage.setItem('lb-theme', next); }catch(e){}
      });

      /* 桌面端固定视口右上角；移动端嵌入卡片（右上角绝对定位） */
      var mqMobile = window.matchMedia('(max-width: 600px)');
      var placeToggle = function () {
        var host = mqMobile.matches ? card : document.body;
        if (btn.parentNode !== host) host.appendChild(btn);
      };
      placeToggle();
      if (mqMobile.addEventListener) {
        mqMobile.addEventListener('change', placeToggle);
      } else if (mqMobile.addListener) {
        mqMobile.addListener(placeToggle);
      }
    }
})();
</script>

<?php if (trim($customJs) !== '') { ?>
<script id="loginbeautify-custom-js">
<?php echo $customJs; ?>
</script>
<?php } ?>
