<?php
/**
 * AB Store 内置模块 —— plugins.php 整页接管
 *
 * 背景：admin/plugins.php 是 core 模板，**没有任何插件钩子**。可用的最早时机是
 * admin/header.php 的 header 过滤器（`$header = Plugin::factory('admin/header.php')->filter('header', $header)`），
 * 它位于 `?><!DOCTYPE` 之前 —— 此时还没有任何字节输出，可以安全 ob_start()。
 *
 * 流程：
 *   renderHeader()（过滤器）→ arm()：ob_start()，原生页面 HTML 全部进缓冲
 *   renderFooter()（footer begin）→ finish()：ob_get_clean()，按 <main class="main"> 切分，
 *                                   中段换成统一界面，head/侧栏/页脚原样保留
 *
 * 降级：任何异常 / 找不到切分标记 / 缓冲层级不对 → 原样输出缓冲内容（原生页照旧可用）。
 *
 * @package AdminBeautify
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
class AdminBeautify_StoreTakeover
{
    private static $armed = false;
    private static $level = 0;
    public static function isTargetPage()
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
        return (bool) preg_match('#/plugins\.php$#', $script);
    }
    public static function canTakeover()
    {
        if (!defined('__TYPECHO_ADMIN__')) {
            return false;
        }
        if (!AdminBeautify_Store::enabled()) {
            return false;
        }
        if (!self::isTargetPage()) {
            return false;
        }
        try {
            $user = Typecho_Widget::widget('Widget_User');
            return $user->hasLogin() && $user->pass('administrator', true);
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        return false;
    }
    public static function arm()
    {
        if (self::$armed) {
            return;
        }
        try {
            AdminBeautify_Store::migrate();
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        self::$level = ob_get_level();
        ob_start();
        self::$armed = true;
    }
    public static function armed()
    {
        return self::$armed;
    }
    public static function finish()
    {
        if (!self::$armed) {
            return;
        }
        self::$armed = false;
        if (ob_get_level() !== self::$level + 1) {
            return;
        }
        $html = ob_get_clean();
        if (!is_string($html) || $html === '') {
            return;
        }
        $out = null;
        try {
            $out = self::splice($html);
        } catch (Exception $e) {
            $out = null;
        } catch (Throwable $e) {
            $out = null;
        }
        echo ($out === null) ? $html : $out;
    }
    private static function splice($html)
    {
        $open = strpos($html, '<main class="main">');
        if ($open === false) {
            return null;
        }
        $close = strrpos($html, '</main>');
        if ($close === false || $close < $open) {
            return null;
        }
        $ui = '';
        try {
            ob_start();
            $ui = AdminBeautify_StorePage::render();
            ob_end_clean();
        } catch (Exception $e) {
            $ui = '';
        } catch (Throwable $e) {
            $ui = '';
        }
        if (!is_string($ui) || $ui === '') {
            return null;
        }
        return substr($html, 0, $open) . $ui . substr($html, $close + strlen('</main>'));
    }
}
