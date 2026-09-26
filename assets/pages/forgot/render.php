<?php
/**
 * AdminBeautify 「忘记密码」分步渲染
 *
 * 这里只负责把某个步骤渲染成一段 HTML 字符串（表单内部内容），不做任何请求处理。
 *
 * ⚠️ 重要约定：本文件生成的 DOM **必须与登录页装饰后的 DOM 逐字节同构**，否则就会
 * 出现「忘记密码的输入框和登录页长得不一样」这类问题。登录页装饰后的真实形态是
 * （取自 admin/login.php 实际渲染结果）：
 *
 *   <form class="lb-form" ...>
 *     <p>
 *       <label for="name" class="sr-only">用户名</label>
 *       <div class="lb-field lb-field--md3">
 *         <span class="lb-field-label" aria-hidden="true">用户名/邮箱</span>
 *         <input type="text" id="name" name="name" class="text-l w-100" autocomplete="username" />
 *       </div>
 *     </p>
 *     <p class="submit">
 *       <div class="lb-submit">
 *         <button type="submit" class="btn btn-l w-100 primary">
 *           <span class="lb-btn-label">登录</span><span class="lb-btn-spinner" aria-hidden="true"></span>
 *         </button>
 *       </div>
 *     </p>
 *   </form>
 *
 * 也就是说：浮动标签、.lb-submit 外壳、.lb-btn-label/.lb-btn-spinner 都由**服务端**
 * 直接产出，前端只需要补「标签浮起状态」和「加载态」两件事。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** HTML 转义 */
function ab_forgot_esc($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * 内联 SVG 图标
 *
 * 登录页脚本全部使用内联 SVG（登录页不加载图标字体），这里保持一致。
 *
 * @param string $name
 * @param string $cls
 * @return string
 */
function ab_forgot_icon($name, $cls = 'ab-fp-ico')
{
    static $paths = array(
        'mail'        => '<rect x="3" y="5" width="18" height="14" rx="2.6"/><path d="m4.2 7.2 7.8 5.9 7.8-5.9"/>',
        'shield'      => '<path d="M12 3 5 6v5.4c0 4.2 2.9 7.7 7 8.6 4.1-.9 7-4.4 7-8.6V6l-7-3Z"/><path d="m9.2 12 2.1 2.1 3.9-4.1"/>',
        'lock'        => '<rect x="4.2" y="10.4" width="15.6" height="9.8" rx="3"/><path d="M8 10.4V7.6a4 4 0 0 1 8 0v2.8"/><path d="M12 14.2v2.2"/>',
        'check'       => '<path d="m5 12.6 4.4 4.4L19 7.6"/>',
        'checkCircle' => '<circle cx="12" cy="12" r="8.8"/><path d="m8.4 12.2 2.4 2.4 4.8-5"/>',
        'info'        => '<circle cx="12" cy="12" r="8.8"/><path d="M12 11.2v5"/><circle cx="12" cy="7.9" r="1.05" fill="currentColor" stroke="none"/>',
        'alert'       => '<circle cx="12" cy="12" r="8.8"/><path d="M12 7.4v5.4"/><circle cx="12" cy="16.4" r="1.05" fill="currentColor" stroke="none"/>',
        'mailDot'     => '<rect x="3" y="5.6" width="18" height="13.2" rx="2.6"/><path d="m4.2 7.8 7.8 5.7 7.8-5.7"/><circle cx="18.6" cy="5.4" r="2.7" fill="currentColor" stroke="none"/>',
        'folder'      => '<path d="M3 7.2A2.2 2.2 0 0 1 5.2 5h3.6l2 2.4h8A2.2 2.2 0 0 1 21 9.6V17a2.2 2.2 0 0 1-2.2 2.2H5.2A2.2 2.2 0 0 1 3 17V7.2Z"/>',
        'chevron'     => '<path d="m10 6.8 5.2 5.2L10 17.2"/>',
        'copy'        => '<rect x="9" y="9" width="11" height="11" rx="2.6"/><path d="M15 6.6A2.6 2.6 0 0 0 12.4 4H6.6A2.6 2.6 0 0 0 4 6.6v5.8A2.6 2.6 0 0 0 6.6 15"/>',
        'clock'       => '<circle cx="12" cy="12" r="8.8"/><path d="M12 7.4V12l3 1.8"/>',
        'arrowBack'   => '<path d="M19 12H5"/><path d="m11 6-6 6 6 6"/>',
        'refresh'     => '<path d="M20.2 12a8.2 8.2 0 1 1-2.7-6.1"/><path d="M20.4 4.6v5.2h-5.2"/>',
        'restart'     => '<path d="M3.8 12a8.2 8.2 0 1 0 2.7-6.1"/><path d="M3.6 4.6v5.2h5.2"/>',
    );

    $p = isset($paths[$name]) ? $paths[$name] : $paths['info'];
    return '<svg class="' . ab_forgot_esc($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
         . ' stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . $p . '</svg>';
}

/** 步骤条上的一个点 */
function ab_forgot_step_pill($label, $icon, $state)
{
    $cls = 'ab-fp-step' . ($state !== '' ? ' is-' . $state : '');
    return '<span class="' . $cls . '">' . ab_forgot_icon($icon) . ab_forgot_esc($label) . '</span>';
}

/**
 * 步骤指示条
 *
 * @param string $current email|choose|mail|file|reset|done
 * @return string
 */
function ab_forgot_steps($current)
{
    $order = array('email', 'verify', 'reset', 'done');
    $meta  = array(
        'email'  => array('填写邮箱', 'mail'),
        'verify' => array('身份验证', 'shield'),
        'reset'  => array('重设密码', 'lock'),
        'done'   => array('完成', 'checkCircle'),
    );

    // choose / mail / file 都归入「身份验证」这一步
    $stage = array('email' => 'email', 'choose' => 'verify', 'mail' => 'verify',
                   'file' => 'verify', 'reset' => 'reset', 'done' => 'done');
    $cur = isset($stage[$current]) ? $stage[$current] : 'email';
    $seen = false;

    $out = '<div class="ab-fp-steps" aria-hidden="true">';
    foreach ($order as $i => $key) {
        $state = '';
        if ($key === $cur) {
            $state = 'active';
            $seen = true;
        } elseif (!$seen) {
            $state = 'done';
        }
        $out .= ab_forgot_step_pill($meta[$key][0], $meta[$key][1], $state);
        if ($i < count($order) - 1) {
            $out .= '<span class="ab-fp-step-sep">·</span>';
        }
    }
    $out .= '</div>';
    return $out;
}

/**
 * 提示条
 *
 * @param string $html  允许内联标签
 * @param string $type  '' | 'error' | 'ok'
 * @return string
 */
function ab_forgot_note($html, $type = '')
{
    if ($html === '') return '';
    $icon = ($type === 'error') ? 'alert' : (($type === 'ok') ? 'checkCircle' : 'info');
    $cls = 'ab-fp-note' . ($type !== '' ? ' ab-fp-note--' . $type : '');
    return '<div class="' . $cls . '">' . ab_forgot_icon($icon) . '<div>' . $html . '</div></div>';
}

/**
 * 表单字段（与登录页装饰后的 DOM 同构）
 *
 * @param array $f id / name / label / type / value / autocomplete / inputmode / maxlength / required / autofocus
 * @return string
 */
function ab_forgot_field(array $f)
{
    $type  = isset($f['type']) ? $f['type'] : 'text';
    $label = isset($f['label']) ? $f['label'] : '';

    $extra = '';
    foreach (array('autocomplete', 'inputmode', 'maxlength') as $k) {
        if (!empty($f[$k])) {
            $extra .= ' ' . $k . '="' . ab_forgot_esc($f[$k]) . '"';
        }
    }
    if (!empty($f['required'])) $extra .= ' required';
    if (!empty($f['autofocus'])) $extra .= ' autofocus';

    return '<p>'
         . '<label for="' . ab_forgot_esc($f['id']) . '" class="sr-only">' . ab_forgot_esc($label) . '</label>'
         . '<div class="lb-field lb-field--md3">'
         . '<span class="lb-field-label" aria-hidden="true">' . ab_forgot_esc($label) . '</span>'
         . '<input type="' . ab_forgot_esc($type) . '" id="' . ab_forgot_esc($f['id']) . '"'
         . ' name="' . ab_forgot_esc($f['name']) . '"'
         . ' value="' . ab_forgot_esc(isset($f['value']) ? $f['value'] : '') . '"'
         . ' class="text-l w-100"' . $extra . ' />'
         . '</div>'
         . '</p>';
}

/** 主按钮（与登录页的 .lb-submit 结构一致） */
function ab_forgot_submit($text)
{
    return '<p class="submit"><div class="lb-submit">'
         . '<button type="submit" class="btn btn-l w-100 primary">'
         . '<span class="lb-btn-label">' . ab_forgot_esc($text) . '</span>'
         . '<span class="lb-btn-spinner" aria-hidden="true"></span>'
         . '</button></div></p>';
}

/**
 * 次级操作行
 *
 * 全部用 <button type="button" data-ab-fp-action="...">，由前端脚本接管；
 * 这样地址栏不会因为点一下就多出一串 ?step=&r= 参数。
 *
 * @param array $links [['action' => 'restart', 'text' => '重新开始', 'icon' => 'restart'], ...]
 * @return string
 */
function ab_forgot_links(array $links)
{
    if (empty($links)) return '';
    $out = '<div class="ab-fp-links">';
    foreach ($links as $l) {
        $out .= '<button type="button" data-ab-fp-action="' . ab_forgot_esc($l['action']) . '">'
              . ab_forgot_icon(isset($l['icon']) ? $l['icon'] : 'arrowBack')
              . ab_forgot_esc($l['text']) . '</button>';
    }
    $out .= '</div>';
    return $out;
}

/** 隐藏字段 */
function ab_forgot_hidden($name, $value)
{
    return '<input type="hidden" name="' . ab_forgot_esc($name) . '" value="' . ab_forgot_esc($value) . '" />';
}

/**
 * 渲染某个步骤的表单内部 HTML
 *
 * @param string $step email|choose|mail|file|reset|done
 * @param array  $d    rid / mail / token / expiresAt / notice / noticeType / values / smtpReady / grant
 * @return string
 */
function ab_forgot_step_html($step, array $d)
{
    $g = function ($k, $def = '') use ($d) {
        return isset($d[$k]) ? $d[$k] : $def;
    };
    $notice = ab_forgot_note($g('notice'), $g('noticeType'));
    $values = isset($d['values']) && is_array($d['values']) ? $d['values'] : array();

    if ($step === 'email') {
        return ab_forgot_steps('email')
             . ab_forgot_hidden('action', 'lookup')
             . $notice
             . ab_forgot_note('请输入账号绑定的邮箱。验证通过后即可设置新密码，重置成功后需要使用新密码重新登录。')
             . ab_forgot_field(array(
                   'id' => 'ab-fp-mail', 'name' => 'mail', 'type' => 'email',
                   'label' => '邮箱', 'autocomplete' => 'email', 'required' => true,
                   'autofocus' => true, 'value' => isset($values['mail']) ? $values['mail'] : '',
               ))
             . ab_forgot_submit('下一步');
    }

    if ($step === 'choose') {
        $out = ab_forgot_steps('choose')
             . ab_forgot_hidden('action', 'choose')
             . ab_forgot_hidden('r', $g('rid'))
             . $notice
             . '<div class="ab-fp-methods">';

        // 邮件认证仅在已填写 SMTP 发件设置时出现
        if ($g('smtpReady')) {
            $out .= '<button type="submit" class="ab-fp-method" name="method" value="mail" data-ab-raw="1">'
                  . '<span class="ab-fp-method-icon">' . ab_forgot_icon('mailDot') . '</span>'
                  . '<span class="ab-fp-method-text">'
                  . '<span class="ab-fp-method-title">邮件认证</span>'
                  . '<span class="ab-fp-method-desc">向 <b>' . ab_forgot_esc($g('mail')) . '</b> 发送 6 位验证码</span>'
                  . '</span>'
                  . ab_forgot_icon('chevron', 'ab-fp-ico ab-fp-method-arrow')
                  . '</button>';
        }

        $out .= '<button type="submit" class="ab-fp-method" name="method" value="file" data-ab-raw="1">'
              . '<span class="ab-fp-method-icon">' . ab_forgot_icon('folder') . '</span>'
              . '<span class="ab-fp-method-text">'
              . '<span class="ab-fp-method-title">验证本地文件</span>'
              . '<span class="ab-fp-method-desc">在站点目录 usr/ 中放置一个验证文件</span>'
              . '</span>'
              . ab_forgot_icon('chevron', 'ab-fp-ico ab-fp-method-arrow')
              . '</button>'
              . '</div>';

        if (!$g('smtpReady')) {
            $out .= ab_forgot_note('站点未配置 SMTP 发件设置，暂不支持邮件认证，请使用本地文件验证。');
        }

        return $out . ab_forgot_links(array(
            array('action' => 'restart', 'text' => '重新开始', 'icon' => 'restart'),
        ));
    }

    if ($step === 'mail') {
        return ab_forgot_steps('mail')
             . ab_forgot_hidden('action', 'verify_mail')
             . ab_forgot_hidden('r', $g('rid'))
             . $notice
             . ab_forgot_note('验证码已发送至 <b>' . ab_forgot_esc($g('mail')) . '</b>，'
                            . '<b>1 小时内</b>有效，请勿转告他人。')
             . ab_forgot_field(array(
                   'id' => 'ab-fp-code', 'name' => 'code', 'type' => 'text',
                   'label' => '验证码', 'autocomplete' => 'one-time-code',
                   'inputmode' => 'numeric', 'maxlength' => 6, 'required' => true,
                   'autofocus' => true, 'value' => isset($values['code']) ? $values['code'] : '',
               ))
             . ab_forgot_submit('验证')
             . ab_forgot_links(array(
                   array('action' => 'resend', 'text' => '重新发送验证码', 'icon' => 'refresh'),
                   array('action' => 'restart', 'text' => '重新开始', 'icon' => 'restart'),
               ));
    }

    if ($step === 'file') {
        $token = (string) $g('token');
        $left = max(0, (int) $g('expiresAt') - time());
        $leftLabel = ($left > 0)
            ? sprintf('剩余 %02d:%02d', intdiv($left, 60), $left % 60)
            : '已过期，请重新开始';

        return ab_forgot_steps('file')
             . ab_forgot_hidden('action', 'verify_file')
             . ab_forgot_hidden('r', $g('rid'))
             . $notice
             . ab_forgot_note('请在服务器站点目录 <code>usr/</code> 下新建一个文件，'
                            . '<b>文件名</b>和<b>文件内容</b>都填下面这串字符。'
                            . '文件名可用下面两种之一，带不带 <code>.txt</code> 后缀都行。')
             . '<div class="ab-fp-token">'
             . '<span class="ab-fp-token-val" id="ab-fp-token">' . ab_forgot_esc($token) . '</span>'
             . '<button type="button" class="ab-fp-copy" aria-label="复制验证串">'
             . ab_forgot_icon('copy')
             . '</button>'
             . '</div>'
             . '<div class="ab-fp-file-names">'
             . ab_forgot_icon('folder', 'ab-fp-ico ab-fp-file-names-icon')
             . '<div class="ab-fp-file-names-body">'
             . '<div class="ab-fp-file-names-title">文件名（任选其一）</div>'
             . '<code class="ab-fp-file-name">usr/' . ab_forgot_esc($token) . '</code>'
             . '<code class="ab-fp-file-name">usr/' . ab_forgot_esc($token) . '.txt</code>'
             . '</div>'
             . '</div>'
             . '<div class="ab-fp-validity' . ($left <= 0 ? ' is-expired' : '') . '"'
             . ' data-ab-countdown="' . (int) $left . '">'
             . ab_forgot_icon('clock')
             . '<span class="ab-fp-validity-label">有效时间</span>'
             . '<span class="ab-fp-validity-left" data-ab-countdown-left>' . ab_forgot_esc($leftLabel) . '</span>'
             . '</div>'
             . ab_forgot_submit('验证本地文件')
             . ab_forgot_links(array(
                   array('action' => 'restart', 'text' => '重新开始', 'icon' => 'restart'),
               ));
    }

    if ($step === 'reset') {
        return ab_forgot_steps('reset')
             . ab_forgot_hidden('action', 'reset')
             . ab_forgot_hidden('r', $g('rid'))
             . ab_forgot_hidden('g', $g('grant'))
             . $notice
             . ab_forgot_note('身份验证已通过，请设置新密码。为了确认无误，需要输入两遍。')
             . ab_forgot_field(array(
                   'id' => 'ab-fp-pass1', 'name' => 'password', 'type' => 'password',
                   'label' => '新密码', 'autocomplete' => 'new-password',
                   'required' => true, 'autofocus' => true,
               ))
             . ab_forgot_field(array(
                   'id' => 'ab-fp-pass2', 'name' => 'password2', 'type' => 'password',
                   'label' => '请再输入一遍', 'autocomplete' => 'new-password', 'required' => true,
               ))
             . ab_forgot_submit('确认修改');
    }

    if ($step === 'done') {
        return ab_forgot_steps('done')
             . ab_forgot_hidden('action', 'back')
             . '<div class="ab-fp-done">'
             . '<span class="ab-fp-done-icon">' . ab_forgot_icon('checkCircle') . '</span>'
             . '<p class="ab-fp-done-text"><b>已经完成修改，请返回重新登录</b></p>'
             . '<p class="ab-fp-done-hint">旧密码已失效，其他设备上的登录状态也已全部退出。</p>'
             . '</div>'
             . ab_forgot_submit('返回登录页面');
    }

    // 未知步骤兜底：回到第一步
    return ab_forgot_step_html('email', $d);
}

/** 每个步骤的卡片副标题 */
function ab_forgot_step_title($step)
{
    $titles = array(
        'email'  => '找回密码',
        'choose' => '选择验证方式',
        'mail'   => '邮箱验证',
        'file'   => '本地文件验证',
        'reset'  => '重置密码',
        'done'   => '修改完成',
    );
    return isset($titles[$step]) ? $titles[$step] : '找回密码';
}
