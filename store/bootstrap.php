<?php
/**
 * AB Store 内置模块 —— 引导文件
 *
 * 只在后台（__TYPECHO_ADMIN__）按需加载：前台页面不会 include 这里的任何文件。
 * 设计见 PLAN.md「插件页 = 插件中枢」；本模块**不注册 action、不注册 panel**，
 * 所有 AJAX 动作都挂在 AdminBeautify 已有的 /action/admin-beautify 上（do=store.*），
 * 避免和旧 AB Store 插件抢 actionTable / panelTable。
 *
 * @package AdminBeautify
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
require_once __DIR__ . '/Core.php';
require_once __DIR__ . '/Installer.php';
require_once __DIR__ . '/Catalog.php';
require_once __DIR__ . '/Page.php';
require_once __DIR__ . '/Takeover.php';
require_once __DIR__ . '/Ajax.php';
