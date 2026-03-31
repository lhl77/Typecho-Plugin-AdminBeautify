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

    head.appendChild(titleWrap);
    card.appendChild(head);

    form.classList.add('lb-form');

    var inputs = qsa('input[type="text"], input[type="password"], input[type="email"]', form);
    inputs.forEach(function(input, idx){
      var field = document.createElement('div');
      field.className = 'lb-field';

      var label = document.createElement('label');
      var n = (input.getAttribute('name') || '').toLowerCase();
      var t = (input.getAttribute('type') || '').toLowerCase();

      if (isRegister) {
        if (idx === 0) {
          label.textContent = '用户名';
          input.setAttribute('placeholder', '请输入用户名');
        } else if (idx === 1 || t === 'email' || n === 'mail') {
          label.textContent = '邮箱';
          input.setAttribute('placeholder', '请输入邮箱');
        } else {
          label.textContent = '输入';
          if (!input.getAttribute('placeholder')) {
            input.setAttribute('placeholder', '请输入内容');
          }
        }
      } else {
        if (n.indexOf('name') !== -1 || n.indexOf('user') !== -1) {
          label.textContent = '用户名/邮箱';
          input.setAttribute('placeholder', '用户名/邮箱');
        } else if (n.indexOf('pass') !== -1) {
          label.textContent = '密码';
          if (!input.getAttribute('placeholder')) {
            input.setAttribute('placeholder', '请输入密码');
          }
        } else {
          label.textContent = '输入';
          if (!input.getAttribute('placeholder')) {
            input.setAttribute('placeholder', '请输入内容');
          }
        }
      }

      var parent = input.parentNode;
      parent.insertBefore(field, input);
      field.appendChild(label);
      field.appendChild(input);
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
      var p = submit.parentNode;
      p.insertBefore(submitWrap, submit);
      submitWrap.appendChild(submit);
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

    // Theme footer
    var lbFooter = document.createElement('div');
    lbFooter.className = 'lb-footer-theme';
    lbFooter.innerHTML = 'Theme <a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify" target="_blank" rel="noopener noreferrer">AdminBeautify</a> by <a href="https://blog.lhl.one" target="_blank" rel="noopener noreferrer">LHL</a>';
    card.appendChild(lbFooter);

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
      document.body.appendChild(btn);
    }
})();
</script>

<?php if (trim($customJs) !== '') { ?>
<script id="loginbeautify-custom-js">
<?php echo $customJs; ?>
</script>
<?php } ?>
