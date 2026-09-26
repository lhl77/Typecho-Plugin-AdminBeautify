<?php
/**
 * AdminBeautify 登录动作扩展：Cloudflare Turnstile 人机验证
 *
 * 通过 Utils\Helper::addAction('login', 'AdminBeautify_Login') 接管登录动作。
 * 未启用验证时直接透传父类逻辑；启用后先校验 token，失败则提示并返回。
 *
 * @package AdminBeautify
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
require_once dirname(__FILE__) . '/Plugin.php';
class AdminBeautify_Login extends \Widget\Login
{
    public function action()
    {
        if (!AdminBeautify_Plugin::turnstileEnabledFor('login')) {
            parent::action();
            return;
        }
        if (!AdminBeautify_Plugin::turnstileVerifyRequest('login')) {
            \Widget\Notice::alloc()->set(_t('安全验证未通过，请刷新页面后重试。'), 'error');
            $this->response->goBack();
            return;
        }
        parent::action();
    }
}
