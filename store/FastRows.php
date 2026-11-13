<?php
/**
 * AB Store —— 插件页原生列组件的「省解析」版本
 *
 * 背景：`admin/plugins.php` 在渲染正文时会跑
 * `\Widget\Plugins\Rows::allocWithAlias('activated', 'activated=1')` 和另一次
 * `Rows`，两次都会对**每一个** `Plugin.php` 调 `\Typecho\Plugin::parseInfo()`
 * （内部是 `token_get_all(整个文件)`）。而这一页的 `<main>` 马上会被 AB Store
 * 整个替换掉 —— 也就是原生列表白算了一遍。
 *
 * 实测（Typecho 1.3 + 259 个插件）：一次页面请求里 parseInfo 被调用 518 次
 * （原生一趟 + 商店一趟）、合计 88ms，慢主机上就是几百毫秒。
 *
 * 这里把原生那天改成读 AB Store 已经缓存好的解析结果：行为与 core 完全一致，
 * 只是不再重新 tokenize。**之所以不干脆空转**，是因为 Takeover 的替换有可能
 * 降级（找不到 `<main>`、输出缓冲层级不对…），那时页面要退回原生列表，
 * 空转就会变成「插件列表空了」这种事故。
 *
 * 挂载方式（Typecho 自带的公共 API，1.2 / 1.3 都有）：
 *   \Typecho\Widget::alias('Widget\Plugins\Rows', 'AdminBeautify_StoreFastRows');
 * 只在「确定要接管 plugins.php」时于 header 过滤器里挂，不影响其它页面。
 *
 * @package AdminBeautify
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
class AdminBeautify_StoreFastRows extends \Widget\Plugins\Rows
{
    public function execute()
    {
        $pluginDirs = $this->getPlugins();
        $this->parameter->setDefault(['activated' => null]);
        $plugins = \Typecho\Plugin::export();
        $this->activatedPlugins = $plugins['activated'];
        if (empty($pluginDirs)) {
            return;
        }
        $infos = array();
        try {
            $infos = AdminBeautify_StoreCatalog::pluginInfos();
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        foreach ($pluginDirs as $key => $pluginDir) {
            $parts = $this->getPlugin($pluginDir);
            if (empty($parts)) {
                continue;
            }
            [$pluginName, $pluginFileName] = $parts;
            if (!file_exists($pluginFileName)) {
                continue;
            }
            if (isset($infos[$pluginName])) {
                $info = $infos[$pluginName];
            } else {
                $info = \Typecho\Plugin::parseInfo($pluginFileName);
                $info['description'] = trim((string) $info['description']);
            }
            $info['name'] = $pluginName;
            $info['dependence'] = \Typecho\Plugin::checkDependence($info['since']);
            $info['activated'] = true;
            if ($info['activate'] || $info['deactivate'] || $info['config'] || $info['personalConfig']) {
                $info['activated'] = isset($this->activatedPlugins[$pluginName]);
                if (isset($this->activatedPlugins[$pluginName])) {
                    unset($this->activatedPlugins[$pluginName]);
                }
            }
            if ($info['activated'] == $this->parameter->activated) {
                $this->push($info);
            }
        }
    }
}
