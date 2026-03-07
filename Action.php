<?php
/**
 * AdminBeautify AJAX Action Handler
 *
 * @package AdminBeautify
 * @author LHL
 * @version 2.0.0
 * @link https://blog.lhl.one
 */

class AdminBeautify_Action extends Typecho_Widget implements Widget_Interface_Do
{
    /**
     * @var Typecho_Db
     */
    private $db;

    /**
     * @var object
     */
    private $options;

    /**
     * @var object
     */
    private $pluginOptions;

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->db = Typecho_Db::get();
        $this->options = Typecho_Widget::widget('Widget_Options');
        $this->pluginOptions = $this->options->plugin('AdminBeautify');
    }

    public function execute()
    {
    }

    /**
     * 检查管理员权限
     */
    private function checkAuth()
    {
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->hasLogin()) {
            $this->jsonError('未登录', 401);
        }
    }

    /**
     * 检查管理员权限（仅管理员）
     */
    private function checkAdmin()
    {
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->hasLogin() || !$user->pass('administrator', true)) {
            $this->jsonError('权限不足', 403);
        }
    }

    /**
     * 输出 JSON 成功响应
     */
    private function jsonSuccess($data = null, $message = 'ok')
    {
        $this->response->throwJson(array(
            'code'    => 0,
            'message' => $message,
            'data'    => $data,
        ));
    }

    /**
     * 输出 JSON 错误响应
     */
    private function jsonError($message = 'error', $code = 400)
    {
        $this->response->throwJson(array(
            'code'    => $code,
            'message' => $message,
            'data'    => null,
        ));
    }

    // ================================================================
    // AJAX 业务方法
    // ================================================================

    /**
     * 获取插件信息 & 仪表盘统计（示例）
     */
    public function info()
    {
        $this->checkAuth();

        $postsCount = $this->db->fetchObject(
            $this->db->select(array('COUNT(*)' => 'num'))
                ->from('table.contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
        )->num;

        $commentsCount = $this->db->fetchObject(
            $this->db->select(array('COUNT(*)' => 'num'))
                ->from('table.comments')
                ->where('status = ?', 'approved')
        )->num;

        $categoriesCount = $this->db->fetchObject(
            $this->db->select(array('COUNT(*)' => 'num'))
                ->from('table.metas')
                ->where('type = ?', 'category')
        )->num;

        $this->jsonSuccess(array(
            'version'    => '1.4.5',
            'posts'      => (int) $postsCount,
            'comments'   => (int) $commentsCount,
            'categories' => (int) $categoriesCount,
        ));
    }

    /**
     * 保存插件设置（AJAX 方式）
     */
    public function saveSettings()
    {
        $this->checkAdmin();

        // 获取请求中的设置参数
        $settings = $this->request->from(
            'primaryColor', 'darkMode', 'borderRadius',
            'enableAnimation', 'navPosition'
        );

        // 过滤空值
        $settings = array_filter($settings, function ($v) {
            return $v !== null && $v !== '';
        });

        if (empty($settings)) {
            $this->jsonError('没有需要保存的设置');
        }

        try {
            // 使用 Typecho 方式更新插件配置
            $pluginName = 'AdminBeautify';
            $currentSettings = $this->options->plugin($pluginName);

            $merged = array();
            // 取出当前所有设置
            foreach ($currentSettings as $key => $value) {
                $merged[$key] = $value;
            }
            // 覆盖变化的部分
            foreach ($settings as $key => $value) {
                $merged[$key] = $value;
            }

            // 写入数据库
            $this->db->query(
                $this->db->update('table.options')
                    ->rows(array('value' => serialize($merged)))
                    ->where('name = ?', 'plugin:' . $pluginName)
            );

            $this->jsonSuccess($merged, '设置已保存');
        } catch (Exception $e) {
            $this->jsonError('保存失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取当前插件配置（AJAX 获取设置）
     */
    public function getSettings()
    {
        $this->checkAdmin();

        $settings = array();
        foreach ($this->pluginOptions as $key => $value) {
            $settings[$key] = $value;
        }

        $this->jsonSuccess($settings);
    }

    /**
     * 通用 ping / 心跳检测
     */
    public function ping()
    {
        $this->jsonSuccess(array(
            'time'   => time(),
            'plugin' => 'AdminBeautify',
        ), 'pong');
    }

    // ================================================================
    // Action 路由入口
    // ================================================================

    /**
     * 绑定动作 — 根据 ?do=xxx 分发到对应方法
     *
     * @access public
     * @return void
     */
    public function action()
    {
        // 设置 JSON 响应头
        $this->response->setContentType('application/json');

        $do = $this->request->get('do', '');

        switch ($do) {
            case 'ping':
                $this->ping();
                break;

            case 'info':
                $this->info();
                break;

            case 'get-settings':
                $this->getSettings();
                break;

            case 'save-settings':
                $this->saveSettings();
                break;

            default:
                $this->jsonError('未知的操作: ' . $do, 404);
                break;
        }
    }
}
