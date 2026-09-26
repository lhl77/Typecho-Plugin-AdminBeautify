<?php
/**
 * AdminBeautify 「忘记密码」接口
 *
 * ⚠️ 这个文件是**接口**，不是页面：
 *
 *   · POST（带 format=json）→ 返回 { ok, step, title, html, r, g }，
 *     前端把 html 换进登录卡片里，地址栏始终保持 /admin/login.php?ab-forgot=1
 *   · 其它任何访问 → 302 回后台登录页并带上 ?ab-forgot=1，由登录页把界面注入进来
 *
 * 为什么改成这样：
 *   1. 地址栏里不会再出现
 *      /usr/plugins/AdminBeautify/assets/pages/forgot/forgot-password.php?step=choose&r=6152…
 *      这种又长、又暴露实现细节、刷新还会「丢参数」的地址；
 *   2. 界面长在真正的登录页 DOM 里，样式（.sr-only / MD3 文本域 / 主按钮）天然与
 *      登录页完全一致 —— 之前独立页面自己拼后台 CSS 路径，因为 Typecho 在非
 *      index.php / admin 的脚本里推不出站点根，把样式表拼成了
 *      .../forgot/admin/css/style.css（404）→ .sr-only 失效 → 输入框上方多出一行
 *      纯文字标签；后台路径也拼成 .../forgot/admin/login.php 导致「返回登录页面」跳错。
 *      现在这些 URL 全部由登录页（真正的 admin 页面）用 Typecho 的 API 给出。
 *   3. 分步走 AJAX，刷新页面由前端从 sessionStorage 恢复步骤，不会回到第一步。
 *
 * 安全：
 *   · 这个接口对未登录访客开放（忘记密码的定义），但不接受任何 URL 参数，
 *     不会变成开放重定向；
 *   · 所有状态变更都需要 rid（32 位随机十六进制，等于一次性凭证），
 *     重设密码还需要一次性 grant；
 *   · 发信有跨请求冷却（见 AdminBeautifyPasswordReset::create()），
 *     避免被反复「重新提交邮箱」触发邮件轰炸。
 */

// ======================================================================
// 1. 引导 Typecho
// ======================================================================

if (!defined('__TYPECHO_ROOT_DIR__')) {
    $abProbe = __DIR__;
    $abConfig = '';
    for ($abI = 0; $abI < 9; $abI++) {
        $abProbe = dirname($abProbe);
        if ($abProbe === '' || $abProbe === '/' || $abProbe === '.') break;
        if (is_file($abProbe . '/config.inc.php')) {
            $abConfig = $abProbe . '/config.inc.php';
            break;
        }
    }
    if ($abConfig === '') {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'AdminBeautify: 无法定位 Typecho 的 config.inc.php，请确认插件目录结构未被改动。';
        exit;
    }
    require_once $abConfig;
    unset($abProbe, $abConfig, $abI);
}

\Widget\Init::alloc();

require_once dirname(__FILE__, 4) . '/Plugin.php';
require_once __DIR__ . '/inc/smtp-mailer.php';
require_once __DIR__ . '/inc/reset-core.php';
require_once __DIR__ . '/render.php';

/** @var Widget\Options $abOptions */
$abOptions = \Widget\Options::alloc();

$abOpt = null;
try {
    $abOpt = $abOptions->plugin('AdminBeautify');
} catch (Exception $abE) {
    $abOpt = null;
} catch (Throwable $abE) {
    $abOpt = null;
}

// ======================================================================
// 2. 小工具
// ======================================================================

/** 读取请求参数（只接受标量） */
function ab_forgot_input($key, $default = '')
{
    $src = isset($_POST[$key]) ? $_POST[$key] : (isset($_GET[$key]) ? $_GET[$key] : $default);
    if (!is_array($src)) {
        return trim((string) $src);
    }
    // 数组只取字符串项（多选框场景）
    return '';
}

/**
 * 后台目录下的 URL
 *
 * 为什么不能直接用 $options->adminUrl / loginUrl：
 *   Typecho 的 Request::getRequestRoot() 是按「当前请求」反推站点根的，只对
 *   index.php 与 admin/*.php 准。本文件埋在插件深层目录（assets/pages/forgot/），
 *   反推出来的根就是「当前脚本所在目录」，于是
 *     adminUrl → .../forgot/admin/
 *     loginUrl → .../forgot/admin/login.php
 *     adminStaticUrl('css','style.css') → .../forgot/admin/css/style.css（404）
 *   这正是之前「返回登录页面跳错」「后台 CSS 404 → .sr-only 失效 → 输入框上方
 *   多出一行纯文字标签」的根源。
 *
 * 所以这里用 **siteUrl（任何页面上都正确）+ 官方后台目录常量 __TYPECHO_ADMIN_DIR__**
 * 来拼 —— 与 Typecho 自己的 Options::___adminUrl() 用的是同一套来源，
 * 站点装在不同目录 / 后台目录改名都能跟上。
 *
 * @param Widget\Options $options
 * @param string $file
 * @return string
 */
function ab_forgot_admin_url($options, $file = '')
{
    $adminDir = defined('__TYPECHO_ADMIN_DIR__') ? (string) __TYPECHO_ADMIN_DIR__ : '/admin/';
    $base = rtrim((string) $options->siteUrl, '/') . '/' . trim($adminDir, '/') . '/';
    return ($file === '') ? $base : $base . ltrim($file, '/');
}

/** 后台登录页 URL */
function ab_forgot_login_url($options)
{
    return ab_forgot_admin_url($options, 'login.php');
}

/** 站点标题（用于邮件主题/正文） */
function ab_forgot_site_title($options)
{
    $t = trim((string) $options->title);
    return ($t !== '') ? $t : '站点';
}

/**
 * 发送验证码邮件
 *
 * @return string 成功返回空串，失败返回中文错误
 */
function ab_forgot_send_code($opt, array $rec, $code, $siteTitle)
{
    $mailer = AdminBeautifySmtpMailer::fromPluginOptions($opt);

    $subject = '[' . $siteTitle . '] 找回密码验证码';
    $body = '<div style="font-family:-apple-system,\'Segoe UI\',Roboto,\'PingFang SC\',\'Microsoft YaHei\',sans-serif;'
          . 'font-size:14px;line-height:1.8;color:#1f2937;">'
          . '<p>您好，</p>'
          . '<p>我们收到了来自 <b>' . htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8') . '</b> 的找回密码请求，您的验证码是：</p>'
          . '<p style="font-size:26px;font-weight:700;letter-spacing:6px;color:#111827;margin:18px 0;">'
          . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>'
          . '<p>验证码 <b>1 小时内</b>有效，请勿转告他人。验证通过后即可重新设置密码。</p>'
          . '<p style="color:#6b7280;font-size:12px;margin-top:24px;">'
          . '如果这不是您本人的操作，忽略本邮件即可，您的密码不会被修改。'
          . '</p></div>';

    try {
        if ($mailer->send($rec['mail'], $subject, $body)) return '';
        return $mailer->getLastError() !== '' ? $mailer->getLastError() : '未知错误';
    } catch (Exception $e) {
        return $e->getMessage();
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

// ======================================================================
// 3. 响应构造
// ======================================================================

$abSmtpReady = AdminBeautifySmtpMailer::isReadyFromPluginOptions($abOpt);
$abSiteTitle = ab_forgot_site_title($abOptions);
$abSteps = array('email', 'choose', 'mail', 'file', 'reset', 'done');

/**
 * 构造一步的响应
 *
 * @param string $step
 * @param array  $d rid/mail/token/expiresAt/grant/notice/noticeType/values
 * @return array
 */
function ab_forgot_res($step, array $d = array())
{
    if (!isset($d['smtpReady'])) {
        $d['smtpReady'] = $GLOBALS['abSmtpReady'];
    }
    return array(
        'ok'    => true,
        'step'  => $step,
        'title' => ab_forgot_step_title($step),
        'html'  => ab_forgot_step_html($step, $d),
        'r'     => isset($d['rid']) ? (string) $d['rid'] : '',
        'g'     => isset($d['grant']) ? (string) $d['grant'] : '',
    );
}

/** 输出 JSON 并结束 */
function ab_forgot_json(array $res)
{
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

/** 记录里的公共字段 */
function ab_forgot_rec_data(array $rec, array $extra = array())
{
    return $extra + array(
        'rid'       => (string) $rec['rid'],
        'mail'      => (string) $rec['mail'],
        'token'     => (string) $rec['token'],
        'expiresAt' => (int) $rec['expiresAt'],
    );
}

// ======================================================================
// 4. 非 AJAX 访问：一律送回后台登录页
// ======================================================================

if (ab_forgot_input('format') !== 'json') {
    $abTarget = ab_forgot_login_url($abOptions);
    $abTarget .= (strpos($abTarget, '?') === false ? '?' : '&') . 'ab-forgot=1';

    if (!headers_sent()) {
        header('Location: ' . $abTarget, true, 302);
        header('Content-Type: text/html; charset=UTF-8');
    }

    // 极少数情况（已被 header 占用）下给一个可点的兜底
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">'
       . '<meta name="robots" content="noindex,nofollow"><title>请从登录页使用</title></head>'
       . '<body style="font:14px/1.8 system-ui;padding:40px;text-align:center">'
       . '<p>忘记密码功能已集成在后台登录页中。</p>'
       . '<p><a href="' . htmlspecialchars($abTarget, ENT_QUOTES, 'UTF-8') . '">点这里前往登录页</a></p>'
       . '</body></html>';
    exit;
}

// ======================================================================
// 5. 请求处理（AJAX）
// ======================================================================

$abAction = ab_forgot_input('action');
$abRid = preg_replace('/[^a-f0-9]/', '', strtolower(ab_forgot_input('r')));
$abGrant = ab_forgot_input('g');
$abRec = ($abRid !== '') ? AdminBeautifyPasswordReset::find($abRid) : null;

// ---- 开始 / 恢复 ---------------------------------------------------------
if ($abAction === 'start') {
    ab_forgot_json(ab_forgot_res('email'));
}

if ($abAction === 'render') {
    $abStep = ab_forgot_input('step', 'email');
    if (!in_array($abStep, $abSteps, true)) {
        $abStep = 'email';
    }

    if ($abStep === 'email' || $abStep === 'done') {
        ab_forgot_json(ab_forgot_res($abStep));
    }

    if (!$abRec) {
        ab_forgot_json(ab_forgot_res('email', array(
            'notice' => '请求已失效，请重新输入邮箱', 'noticeType' => 'error',
        )));
    }

    if ($abStep === 'reset') {
        $abChecked = ($abGrant !== '') ? AdminBeautifyPasswordReset::checkGrant($abRid, $abGrant) : null;
        if (!$abChecked) {
            ab_forgot_json(ab_forgot_res('email', array(
                'notice' => '重置链接已失效或已使用，请重新发起找回', 'noticeType' => 'error',
            )));
        }
        ab_forgot_json(ab_forgot_res('reset', ab_forgot_rec_data($abRec, array('grant' => $abGrant))));
    }

    if ($abStep === 'mail' && !$abSmtpReady) {
        ab_forgot_json(ab_forgot_res('file', ab_forgot_rec_data($abRec, array(
            'notice' => '站点尚未配置 SMTP 发件设置，已切换为「验证本地文件」方式',
        ))));
    }

    $abMore = array();
    if ($abStep === 'mail') {
        $abMore['notice'] = '验证码已于 ' . date('H:i', (int) $abRec['mailSentAt'])
                          . ' 发送至 <b>' . htmlspecialchars($abRec['mail'], ENT_QUOTES, 'UTF-8') . '</b>，'
                          . '<b>1 小时内</b>有效。';
    }
    ab_forgot_json(ab_forgot_res($abStep, ab_forgot_rec_data($abRec, $abMore)));
}

// ---- 重新开始 ------------------------------------------------------------
if ($abAction === 'restart') {
    if ($abRec) {
        AdminBeautifyPasswordReset::destroy($abRec['rid']);
    }
    ab_forgot_json(ab_forgot_res('email'));
}

// ---- 第一步：提交邮箱 ----------------------------------------------------
if ($abAction === 'lookup') {
    // Cloudflare Turnstile 人机验证（已启用才校验；网络异常等按 fail-open 放行，见 Plugin.php）
    if (!AdminBeautify_Plugin::turnstileVerifyRequest('forgot')) {
        ab_forgot_json(ab_forgot_res('email', array(
            'notice' => '安全验证未通过，请完成人机验证后重试', 'noticeType' => 'error',
            'values' => array('mail' => AdminBeautifyPasswordReset::normMail(ab_forgot_input('mail'))),
        )));
    }

    $abMail = AdminBeautifyPasswordReset::normMail(ab_forgot_input('mail'));

    if ($abMail === '' || !filter_var($abMail, FILTER_VALIDATE_EMAIL)) {
        ab_forgot_json(ab_forgot_res('email', array(
            'notice' => '请输入正确的邮箱地址', 'noticeType' => 'error',
            'values' => array('mail' => $abMail),
        )));
    }

    $abUser = AdminBeautifyPasswordReset::lookupUser($abMail);
    if (!$abUser) {
        ab_forgot_json(ab_forgot_res('email', array(
            'notice' => '该邮箱未绑定本站账号，请确认后重试', 'noticeType' => 'error',
            'values' => array('mail' => $abMail),
        )));
    }

    $abNew = AdminBeautifyPasswordReset::create($abMail, (int) $abUser['uid']);
    if (!$abNew) {
        ab_forgot_json(ab_forgot_res('email', array(
            'notice' => '暂时无法创建找回请求（数据库不可写），请联系站点管理员', 'noticeType' => 'error',
            'values' => array('mail' => $abMail),
        )));
    }

    ab_forgot_json(ab_forgot_res('choose', ab_forgot_rec_data($abNew)));
}

// ---- 第二步：选择验证方式 ------------------------------------------------
if ($abAction === 'choose' || $abAction === 'resend') {
    if (!$abRec) {
        ab_forgot_json(ab_forgot_res('email', array(
            'notice' => '请求已失效，请重新输入邮箱', 'noticeType' => 'error',
        )));
    }

    $abMethod = ($abAction === 'resend') ? 'mail' : ab_forgot_input('method');

    if ($abMethod === 'file') {
        ab_forgot_json(ab_forgot_res('file', ab_forgot_rec_data($abRec)));
    }

    if ($abMethod === 'mail') {
        if (!$abSmtpReady) {
            ab_forgot_json(ab_forgot_res('choose', ab_forgot_rec_data($abRec, array(
                'notice' => '站点尚未配置 SMTP 发件设置，请改用「验证本地文件」', 'noticeType' => 'error',
            ))));
        }

        // 冷却期内不重复发信（防止被反复触发造成邮件轰炸）
        $abWait = AdminBeautifyPasswordReset::resendWait($abRec);
        if ($abWait > 0) {
            ab_forgot_json(ab_forgot_res('mail', ab_forgot_rec_data($abRec, array(
                'notice' => '验证码已于 ' . date('H:i', (int) $abRec['mailSentAt'])
                          . ' 发送至 <b>' . htmlspecialchars($abRec['mail'], ENT_QUOTES, 'UTF-8') . '</b>，'
                          . '请稍后再试（约 ' . (int) $abWait . ' 秒后可重新发送）。',
            ))));
        }

        $abCode = AdminBeautifyPasswordReset::issueMailCode($abRec);
        if (!$abCode) {
            ab_forgot_json(ab_forgot_res('choose', ab_forgot_rec_data($abRec, array(
                'notice' => '验证码写入失败，请联系站点管理员', 'noticeType' => 'error',
            ))));
        }

        $abErr = ab_forgot_send_code($abOpt, $abRec, $abCode, $abSiteTitle);
        if ($abErr !== '') {
            ab_forgot_json(ab_forgot_res('choose', ab_forgot_rec_data($abRec, array(
                'notice' => '邮件发送失败：' . htmlspecialchars($abErr, ENT_QUOTES, 'UTF-8')
                          . '，可稍后重试或改用「验证本地文件」',
                'noticeType' => 'error',
            ))));
        }

        ab_forgot_json(ab_forgot_res('mail', ab_forgot_rec_data($abRec, array(
            'notice' => '新验证码已发送至 <b>' . htmlspecialchars($abRec['mail'], ENT_QUOTES, 'UTF-8') . '</b>',
            'noticeType' => 'ok',
        ))));
    }

    ab_forgot_json(ab_forgot_res('choose', ab_forgot_rec_data($abRec)));
}

// ---- 第三步 A：校验邮件验证码 --------------------------------------------
if ($abAction === 'verify_mail') {
    if (!$abRec) {
        ab_forgot_json(ab_forgot_res('email', array(
            'notice' => '请求已失效，请重新输入邮箱', 'noticeType' => 'error',
        )));
    }

    $abCode = preg_replace('/\D/', '', ab_forgot_input('code'));
    list($abOk, $abErr) = AdminBeautifyPasswordReset::verifyMailCode($abRec, $abCode);

    if (!$abOk) {
        // 记录可能已被销毁（超次数/过期），此时退回第一步
        if (!AdminBeautifyPasswordReset::find($abRec['rid'])) {
            ab_forgot_json(ab_forgot_res('email', array('notice' => $abErr, 'noticeType' => 'error')));
        }
        ab_forgot_json(ab_forgot_res('mail', ab_forgot_rec_data($abRec, array(
            'notice' => $abErr, 'noticeType' => 'error',
            'values' => array('code' => $abCode),
        ))));
    }

    $abNewGrant = AdminBeautifyPasswordReset::issueGrant($abRec);
    if (!$abNewGrant) {
        ab_forgot_json(ab_forgot_res('mail', ab_forgot_rec_data($abRec, array(
            'notice' => '签发重置凭证失败，请重新开始', 'noticeType' => 'error',
        ))));
    }

    ab_forgot_json(ab_forgot_res('reset', ab_forgot_rec_data($abRec, array('grant' => $abNewGrant))));
}

// ---- 第三步 B：校验本地文件 ----------------------------------------------
if ($abAction === 'verify_file') {
    if (!$abRec) {
        ab_forgot_json(ab_forgot_res('email', array(
            'notice' => '请求已失效，请重新输入邮箱', 'noticeType' => 'error',
        )));
    }

    list($abOk, $abErr) = AdminBeautifyPasswordReset::verifyLocalFile($abRec);

    if (!$abOk) {
        if (!AdminBeautifyPasswordReset::find($abRec['rid'])) {
            ab_forgot_json(ab_forgot_res('email', array('notice' => $abErr, 'noticeType' => 'error')));
        }
        ab_forgot_json(ab_forgot_res('file', ab_forgot_rec_data($abRec, array(
            'notice' => $abErr, 'noticeType' => 'error',
        ))));
    }

    $abNewGrant = AdminBeautifyPasswordReset::issueGrant($abRec);
    if (!$abNewGrant) {
        ab_forgot_json(ab_forgot_res('file', ab_forgot_rec_data($abRec, array(
            'notice' => '签发重置凭证失败，请重新开始', 'noticeType' => 'error',
        ))));
    }

    ab_forgot_json(ab_forgot_res('reset', ab_forgot_rec_data($abRec, array('grant' => $abNewGrant))));
}

// ---- 第四步：重设密码 ----------------------------------------------------
if ($abAction === 'reset') {
    $abPass1 = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $abPass2 = isset($_POST['password2']) ? (string) $_POST['password2'] : '';

    $abChecked = ($abRid !== '' && $abGrant !== '')
        ? AdminBeautifyPasswordReset::checkGrant($abRid, $abGrant)
        : null;

    if (!$abChecked) {
        ab_forgot_json(ab_forgot_res('email', array(
            'notice' => '重置凭证已失效，请重新发起找回', 'noticeType' => 'error',
        )));
    }

    if ($abPass1 === '' || $abPass2 === '') {
        ab_forgot_json(ab_forgot_res('reset', ab_forgot_rec_data($abChecked, array(
            'grant' => $abGrant, 'notice' => '请填写两遍新密码', 'noticeType' => 'error',
        ))));
    }

    if ($abPass1 !== $abPass2) {
        ab_forgot_json(ab_forgot_res('reset', ab_forgot_rec_data($abChecked, array(
            'grant' => $abGrant, 'notice' => '两次输入的密码不一致，请再输入一遍', 'noticeType' => 'error',
        ))));
    }

    list($abOk, $abErr) = AdminBeautifyPasswordReset::resetPassword((int) $abChecked['uid'], $abPass1);
    if (!$abOk) {
        ab_forgot_json(ab_forgot_res('reset', ab_forgot_rec_data($abChecked, array(
            'grant' => $abGrant, 'notice' => $abErr, 'noticeType' => 'error',
        ))));
    }

    // 用完即毁：grant 立刻失效
    AdminBeautifyPasswordReset::destroy($abChecked['rid']);
    ab_forgot_json(ab_forgot_res('done'));
}

// ---- 其它：当作重新开始 --------------------------------------------------
ab_forgot_json(ab_forgot_res('email'));
