<?php
/**
 * AB-Admin (Admin Beautify) - 最美 Typecho 后台美化增强插件，Material Design 3 设计
 *
 * @package AB-Admin
 * @author LHL
 * @version 2.1.38
 * @link https://github.com/lhl77/Typecho-Plugin-AdminBeautify
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
class AdminBeautify_Plugin implements Typecho_Plugin_Interface
{
    private static function isLoginPage()
    {
        try {
            return !Typecho_Widget::widget('Widget_User')->hasLogin();
        } catch (Exception $e) {
            return true;
        }
    }
    private static function jsString($s)
    {
        return json_encode((string) $s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    public static function activate()
    {
        Typecho_Plugin::factory('admin/header.php')->header = array(__CLASS__, 'renderHeader');
        Typecho_Plugin::factory('admin/footer.php')->begin = array(__CLASS__, 'renderFooter');
        Typecho_Plugin::factory('admin/footer.php')->end = array(__CLASS__, 'renderLoginFooter');
        Utils\Helper::addAction('admin-beautify', 'AdminBeautify_Action');
        return _t('AB-Admin 已启用');
    }
    public static function deactivate()
    {
        Utils\Helper::removeAction('admin-beautify');
        return _t('AdminBeautify 已禁用');
    }
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $abConfigColors = array(
            'purple' => array('#7D5260', '#9E7B8A'),
            'blue'   => array('#556270', '#7A8A9E'),
            'teal'   => array('#4A6363', '#6A8A8A'),
            'green'  => array('#55624C', '#7A8A6E'),
            'orange' => array('#725A42', '#9E8062'),
            'pink'   => array('#74565F', '#9E7A85'),
            'red'    => array('#775654', '#A27A78'),
        );
        try {
            $abOpt = Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautify');
            $abScheme = isset($abOpt->primaryColor) ? (string) $abOpt->primaryColor : 'purple';
        } catch (Exception $e) {
            $abScheme = 'purple';
        }
        if (!isset($abConfigColors[$abScheme])) $abScheme = 'purple';
        $abC1 = $abConfigColors[$abScheme][0];
        $abC2 = $abConfigColors[$abScheme][1];
        $abVer = '2.1.38';
        include dirname(__FILE__) . '/assets/pages/config/header.php';
        include dirname(__FILE__) . '/assets/pages/config/config.style.php';
        include_once dirname(__FILE__) . '/assets/pages/config/card-create.php';
        abCard('admin', $abC1, '⚙️', '管理后台设置', '主题色、暗色模式、圆角、动画、布局');
        $primaryColor = new Typecho_Widget_Helper_Form_Element_Select(
            'primaryColor',
            array(
                'purple'  => '🟣 紫 (默认)',
                'blue'    => '🔵 蓝',
                'teal'    => '🩵 青',
                'green'   => '🟢 绿',
                'orange'  => '🟠 橙',
                'pink'    => '🩷 粉',
                'red'     => '🔴 红',
            ),
            'purple',
            _t('主题色'),
            _t('选择管理后台的主题色方案')
        );
        $form->addInput($primaryColor);
        $darkMode = new Typecho_Widget_Helper_Form_Element_Select(
            'darkMode',
            array(
                'auto'  => '跟随系统',
                'light' => '浅色模式',
                'dark'  => '深色模式',
            ),
            'auto',
            _t('颜色模式'),
            _t('选择后台的明暗模式')
        );
        $form->addInput($darkMode);
        $borderRadius = new Typecho_Widget_Helper_Form_Element_Select(
            'borderRadius',
            array(
                'small'  => '小圆角',
                'medium' => '中圆角 (默认)',
                'large'  => '大圆角',
            ),
            'medium',
            _t('圆角风格'),
            _t('控制界面元素的圆角大小')
        );
        $form->addInput($borderRadius);
        $enableAnimation = new Typecho_Widget_Helper_Form_Element_Select(
            'enableAnimation',
            array(
                '1' => '开启',
                '0' => '关闭',
            ),
            '1',
            _t('过渡动画'),
            _t('是否开启界面元素的过渡动画效果')
        );
        $form->addInput($enableAnimation);
        $navPosition = new Typecho_Widget_Helper_Form_Element_Select(
            'navPosition',
            array(
                'left' => '侧边栏 (默认)',
                'top'  => '导航栏 (原版)',
            ),
            'left',
            _t('导航栏位置'),
            _t('选择导航栏在页面顶部还是左侧显示（仅桌面端生效，移动端始终为顶部折叠菜单）')
        );
        $form->addInput($navPosition);
        $pluginCardView = new Typecho_Widget_Helper_Form_Element_Select(
            'pluginCardView',
            array(
                '1' => '卡片网格 (默认)',
                '0' => '原始表格',
            ),
            '1',
            _t('插件列表样式'),
            _t('选择插件管理页面的展示方式：卡片网格更直观，原始表格与 Typecho 默认保持一致')
        );
        $form->addInput($pluginCardView);
        $dashboardQuickShow = new Typecho_Widget_Helper_Form_Element_Select(
            'dashboardQuickShow',
            array(
                '1' => '显示 (默认)',
                '0' => '隐藏',
            ),
            '1',
            _t('概要页快捷操作'),
            _t('是否显示概要页中 Typecho 原有的快捷操作按钮（写文章、管理评论等）')
        );
        $form->addInput($dashboardQuickShow);
        $dashboardQuickStyle = new Typecho_Widget_Helper_Form_Element_Select(
            'dashboardQuickStyle',
            array(
                'small' => '小（胶囊按钮，默认）',
                'large' => '大（图标卡片）',
            ),
            'large',
            _t('快捷操作按钮样式'),
            _t('小：横排胶囊按钮（图标 + 文字并排）；大：网格图标卡片（图标在上、文字在下）')
        );
        $form->addInput($dashboardQuickStyle);
        $dashboardQuickHint = new Typecho_Widget_Helper_Form_Element_Select(
            'dashboardQuickHint',
            array(
                '1' => '显示 (默认)',
                '0' => '隐藏',
            ),
            '1',
            _t('快捷按钮 - "自定义"'),
            _t('是否在概要页中显示添加"自定义"快捷按钮')
        );
        $form->addInput($dashboardQuickHint);
        $dashboardHideDonate = new Typecho_Widget_Helper_Form_Element_Select(
            'dashboardHideDonate',
            array(
                '0' => '显示 (默认)',
                '1' => '隐藏',
            ),
            '0',
            _t('快捷按钮 - "捐助作者"'),
            _t('是否在概要页快捷操作中显示"捐助作者"按钮')
        );
        $form->addInput($dashboardHideDonate);
        $dashboardCustomButtons = new Typecho_Widget_Helper_Form_Element_Textarea(
            'dashboardCustomButtons',
            null,
            '',
            _t('概要页自定义快捷按钮'),
            _t('每行一个按钮，格式：<code>名称:地址:图标</code> 或 <code>名称:地址:图标:highlight</code>（加 <code>:highlight</code> 为强调样式）。<br>图标名称来自 <a href="https://fonts.google.com/icons" target="_blank" rel="noopener noreferrer">Material Symbols</a>。<br>⚠️ 外链地址必须以 <code>http://</code> 或 <code>https://</code> 开头，站内路径无需协议头。<br>示例：<br><code>写文章:write-post.php:edit</code><br><code>查看前台:https://example.com:public:highlight</code><br><code>管理评论:manage-comments.php:comment</code>')
        );
        $form->addInput($dashboardCustomButtons);
        $dashboardRecentStyle = new Typecho_Widget_Helper_Form_Element_Select(
            'dashboardRecentStyle',
            array(
                'md3'      => 'MD卡片（默认）',
                'original' => '原版',
            ),
            'md3',
            _t('最近文章/评论卡片样式'),
            _t('MD卡片：Material Design 3 列表风格，时间居右，文章单行、评论双行；原版：Typecho 原有样式。同时会隐藏"官方最新日志"卡片。')
        );
        $form->addInput($dashboardRecentStyle);
        $overviewChartEnabled = new Typecho_Widget_Helper_Form_Element_Select(
            'overviewChartEnabled',
            array(
                '1' => '开启（默认）',
                '0' => '关闭',
            ),
            '1',
            _t('概要页图表'),
            _t('是否在概要页底部显示「更新频率」折线图和「近期评论分类」极坐标图')
        );
        $form->addInput($overviewChartEnabled);
        $overviewTimeRange = new Typecho_Widget_Helper_Form_Element_Select(
            'overviewTimeRange',
            array(
                '7'  => '近 7 天',
                '30' => '近 30 天（默认）',
                '0'  => '全部',
            ),
            '30',
            _t('图表统计时间范围'),
            _t('「更新频率」和「近期评论分类」图表的数据统计时间范围')
        );
        $form->addInput($overviewTimeRange);
        $umamiEnabled = new Typecho_Widget_Helper_Form_Element_Select(
            'umamiEnabled',
            array(
                '0' => '关闭（默认）',
                '1' => '开启',
            ),
            '0',
            _t('Umami 访问统计'),
            _t('开启后在概要页显示 Umami 访问统计卡片（今日访问量、总访问量、热门文章、跳出率）。需同时填写下方 API 配置。')
        );
        $form->addInput($umamiEnabled);
        $umamiApiBase = new Typecho_Widget_Helper_Form_Element_Text(
            'umamiApiBase',
            null,
            '',
            _t('Umami API 地址'),
            _t('Umami 实例地址，不含末尾斜线，例如：<code>https://umami.example.com</code>')
        );
        $form->addInput($umamiApiBase);
        $umamiWebsiteId = new Typecho_Widget_Helper_Form_Element_Text(
            'umamiWebsiteId',
            null,
            '',
            _t('Umami 网站 ID'),
            _t('在 Umami → 网站设置 中查看 Website ID（UUID 格式）')
        );
        $form->addInput($umamiWebsiteId);
        $umamiApiToken = new Typecho_Widget_Helper_Form_Element_Text(
            'umamiApiToken',
            null,
            '',
            _t('Umami API Token'),
            _t('Token 需要手动 Post 获取，详情见<a href="https://docs.umami.is/docs/api/authentication" href="_blank">官方文档</a>')
        );
        $form->addInput($umamiApiToken);
        $umamiTimeRange = new Typecho_Widget_Helper_Form_Element_Select(
            'umamiTimeRange',
            array(
                '7'  => '近 7 天',
                '30' => '近 30 天（默认）',
                '0'  => '全部',
            ),
            '30',
            _t('Umami 数据时间范围'),
            _t('访客数量、平均访问时长、跳出率 这三项的统计时间范围（今日访问量 / 总访问量 始终固定为今日 / 全部时间）')
        );
        $form->addInput($umamiTimeRange);
        abCard('editor', $abC1, '✏️', '编辑器设置', 'Vditor Markdown 编辑器，所见即所得 / 实时预览 / 分屏编辑',
            abCardTip('✏️', '开启 Vditor 后，文章 / 页面编辑界面将使用 Vditor 替代原版 PageDown 编辑器。原工具栏将被 Vditor 内置工具栏接管，原"撰写/预览"切换将变为 <strong>所见即所得 / 实时预览 / 分屏编辑</strong> 三种模式切换按钮。')
        );
        $editorVditor = new Typecho_Widget_Helper_Form_Element_Select(
            'editor_vditor',
            array(
                '0' => 'AB Typecho 优化',
                '1' => 'AB Vditor',
                '2' => '兼容其他编辑器',
            ),
            '0',
            _t('编辑器'),
            _t('AB Typecho 优化：使用 AB-Admin 优化后的原版编辑器（含 AB 工具栏）；AB Vditor：替换为 Vditor Markdown 编辑器；兼容其他编辑器：不注入任何编辑器相关 CSS / JS，适合已安装第三方编辑器插件时使用。')
        );
        $form->addInput($editorVditor);
        $editorVditorMode = new Typecho_Widget_Helper_Form_Element_Select(
            'editor_vditorMode',
            array(
                'wysiwyg' => '所见即所得',
                'ir'      => '实时预览（默认）',
                'sv'      => '分屏编辑（宽屏推荐）',
            ),
            'ir',
            _t('AB Vditor 默认模式'),
            _t('首次打开编辑器时使用的模式，用户可通过编辑器上方的模式切换按钮随时切换')
        );
        $form->addInput($editorVditorMode);
        $editorHideToolbar = new Typecho_Widget_Helper_Form_Element_Select(
            'editor_hideToolbar',
            array(
                '0' => '保留原有工具栏',
                '1' => '隐藏原有工具栏',
            ),
            '0',
            _t('原有工具栏'),
            _t('仅在"兼容其他编辑器"模式下生效。开启后将强制隐藏 Typecho 原有编辑工具栏（#wmd-button-bar），以避免与第三方编辑器的工具栏叠加显示。')
        );
        $form->addInput($editorHideToolbar);
        abCard('login', $abC2, '🔐', '登录页设置', '配色方案、背景图片、虚化效果、自定义样式',
            abCardTip('💡', '以下设置控制登录页面的样式，支持自定义配色、背景图片、虚化效果等。')
        );
        $loginIsEnabled = new Typecho_Widget_Helper_Form_Element_Radio(
            'login_isEnabled',
            array('1' => _t('使用AB登录页'), '0' => _t('不使用/使用其他登录页美化')),
            '1',
            _t('登录页启用状态')
        );
        $form->addInput($loginIsEnabled);
        $loginColorPreset = new Typecho_Widget_Helper_Form_Element_Select(
            'login_colorPreset',
            array(
                'custom'   => _t('自定义'),
                'purple'   => _t('🟣 紫 (默认)'),
                'blue'     => _t('🔵 蓝'),
                'pink'     => _t('🌸 粉'),
                'green'    => _t('🌿 绿'),
                'orange'   => _t('🍊 橙'),
                'red'      => _t('❤️ 红'),
                'teal'     => _t('🌊 青'),
                'indigo'   => _t('💙 靛蓝'),
                'sunset'   => _t('🌅 日落渐变'),
                'ocean'    => _t('🌊 海洋渐变'),
                'forest'   => _t('🌲 森林渐变'),
                'lavender' => _t('💜 薰衣草'),
            ),
            'purple',
            _t('登录页配色方案'),
            _t('选择预设配色或使用自定义颜色')
        );
        $form->addInput($loginColorPreset);
        $loginPrimaryColor = new Typecho_Widget_Helper_Form_Element_Text(
            'login_primaryColor',
            null,
            '#7d5260',
            _t('登录页主色（自定义）'),
            _t('选择"自定义"方案后生效。如：#625fa0')
        );
        $form->addInput($loginPrimaryColor);
        $loginPrimaryColor2 = new Typecho_Widget_Helper_Form_Element_Text(
            'login_primaryColor2',
            null,
            '#9e7b8a',
            _t('登录页辅色（自定义）'),
            _t('选择"自定义"方案后生效。如：#7a6ec0')
        );
        $form->addInput($loginPrimaryColor2);
        $loginShowSiteName = new Typecho_Widget_Helper_Form_Element_Radio(
            'login_showSiteName',
            array('1' => _t('显示'), '0' => _t('隐藏')),
            '1',
            _t('登录页显示站点名称')
        );
        $form->addInput($loginShowSiteName);
        $loginThemeMode = new Typecho_Widget_Helper_Form_Element_Radio(
            'login_themeMode',
            array('auto' => _t('跟随系统'), 'light' => _t('亮色'), 'dark' => _t('暗色')),
            'auto',
            _t('登录页默认主题')
        );
        $form->addInput($loginThemeMode);
        $loginShowThemeToggle = new Typecho_Widget_Helper_Form_Element_Radio(
            'login_showThemeToggle',
            array('1' => _t('显示'), '0' => _t('隐藏')),
            '1',
            _t('登录页显示主题切换按钮')
        );
        $form->addInput($loginShowThemeToggle);
        $loginBgImage = new Typecho_Widget_Helper_Form_Element_Text(
            'login_bgImage',
            null,
            '',
            _t('登录页背景图片 URL'),
            _t('留空则使用纯色背景。')
        );
        $form->addInput($loginBgImage);
        $loginBlurType = new Typecho_Widget_Helper_Form_Element_Radio(
            'login_blurType',
            array(
                'none'   => _t('不虚化'),
                'filter' => _t('背景图模糊（filter: blur）'),
            ),
            'filter',
            _t('登录页虚化方式')
        );
        $form->addInput($loginBlurType);
        $loginBlurSize = new Typecho_Widget_Helper_Form_Element_Text(
            'login_blurSize',
            null,
            '12',
            _t('登录页虚化大小(px)'),
            _t('建议 0-50。')
        );
        $form->addInput($loginBlurSize);
        $loginCustomCss = new Typecho_Widget_Helper_Form_Element_Textarea(
            'login_customCss',
            null,
            '',
            _t('登录页自定义 CSS'),
            _t('将注入到登录页。无需 style 标签。如果不生效请加 !important')
        );
        $form->addInput($loginCustomCss);
        $loginCustomJs = new Typecho_Widget_Helper_Form_Element_Textarea(
            'login_customJs',
            null,
            '',
            _t('登录页自定义 JavaScript'),
            _t('将注入到登录页。无需 script 标签。')
        );
        $form->addInput($loginCustomJs);
        self::renderLoginPreview();
        abCard('pwa', $abC1, '📱', 'PWA 应用设置', '将管理后台安装为渐进式 Web 应用，自定义名称和图标');
        $pwaAppName = new Typecho_Widget_Helper_Form_Element_Text(
            'pwa_appName',
            null,
            '',
            _t('PWA 应用名称'),
            _t('安装为 PWA 后显示的应用名称。留空则默认为「博客名称 + 管理后台」')
        );
        $form->addInput($pwaAppName);
        $pwaAppIcon = new Typecho_Widget_Helper_Form_Element_Text(
            'pwa_appIcon',
            null,
            'https://i.see.you/2026/03/08/Uei3/26ee132f48bd9453e9c4d1d3fa1d312d.jpg',
            _t('PWA 应用图标 URL'),
            _t('安装为 PWA 后显示的应用图标，建议使用 512×512 的正方形图片。')
        );
        $form->addInput($pwaAppIcon);
        abCard('perf', $abC1, '⚡', '速度优化', '字体与图标静态资源来源，支持 Google CDN、国内镜像或自定义');
        $staticResource = new Typecho_Widget_Helper_Form_Element_Select(
            'staticResource',
            array(
                'google'    => _t('Google CDN（fonts.googleapis.com）'),
                'loli'      => _t('loli.net 镜像（fonts.loli.net）'),
                'jsdelivr'  => _t('jsDelivr CDN（cdn.jsdelivr.net）'),
                'local'     => _t('本地文件（零外部依赖）'),
                'custom'    => _t('自定义 URL')
            ),
            'local',
            _t('字体 & 图标资源来源'),
            _t('选择 Noto Sans SC 字体与 Material Icons 图标的加载方式')
        );
        $form->addInput($staticResource);
        $customFontUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'customFontUrl',
            null,
            '',
            _t('自定义字体 CSS URL'),
            _t('选「自定义 URL」后生效。填入 Noto Sans SC 的 CSS 链接，如本机路径或其他 CDN。')
        );
        $form->addInput($customFontUrl);
        $customIconUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'customIconUrl',
            null,
            '',
            _t('自定义图标 CSS URL'),
            _t('选「自定义 URL」后生效。填入 Material Icons Round 的 CSS 链接。注：一定是Material Icons Round，内含 .material-icons-round 类样式。')
        );
        $form->addInput($customIconUrl);
        $localFontUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'localFontUrl',
            null,
            '',
            _t('本地字体 CSS 路径（留空则使用默认）'),
            _t('选「本地文件」后生效，留空则使用插件内默认路径（assets/fonts/Noto-Sans-SC-CSS/all.css）。')
        );
        $form->addInput($localFontUrl);
        $localIconUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'localIconUrl',
            null,
            '',
            _t('本地图标 CSS 路径（留空则使用默认）'),
            _t('选「本地文件」后生效，留空则使用插件内默认路径（assets/fonts/MDIR/MaterialIconsRound.css）。')
        );
        $form->addInput($localIconUrl);
        $avatarSource = new Typecho_Widget_Helper_Form_Element_Select(
            'avatarSource',
            array(
                'loli'     => _t('loli.net 镜像（gravatar.loli.net，推荐）'),
                'gravatar' => _t('Gravatar 官方（www.gravatar.com）'),
                'cravatar' => _t('Cravatar（cravatar.cn）'),
                'custom'   => _t('自定义域名'),
            ),
            'loli',
            _t('Gravatar 头像源'),
            _t('选择后台头像（侧栏、移动端、个人设置页、评论管理页）的加载域名。')
        );
        $form->addInput($avatarSource);
        $customAvatarUrl = new Typecho_Widget_Helper_Form_Element_Text(
            'customAvatarUrl',
            null,
            '',
            _t('自定义头像域名'),
            _t('选「自定义域名」后生效。填入完整头像服务地址，如 https://example.com/avatar，末尾不加斜线，格式需与 Gravatar 兼容（路径后接 MD5 邮箱哈希）。')
        );
        $form->addInput($customAvatarUrl);
        abCard('compat', $abC2, '🧩', '兼容脚本管理', '手动启用兼容脚本，修复其他插件/旧版 Typecho 的页面排版',
            abCardTip('📦',
                '兼容脚本默认不加载，请根据需要手动开启。脚本位于 <code>assets/compat/</code> 目录。<br>'
                . '开发者可参考 <code>assets/compat/README.md</code> 编写兼容脚本（需包含 <code>@name</code> / <code>@plugins</code> / <code>@description</code> 元数据）。<br>'
                . '文档：<a href="https://blog.lhl.one/artical/977.html" target="_blank">https://blog.lhl.one/artical/977.html</a>',
                true
            )
        );
        $compatDir = dirname(__FILE__) . '/assets/compat/';
        $compatScripts = self::scanCompatScripts($compatDir);
        $enabledRaw = '';
        try {
            $abOptCompat = Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautify');
            $enabledRaw = isset($abOptCompat->compat_disabledScripts) ? (string) $abOptCompat->compat_disabledScripts : '';
        } catch (Exception $e) {
            $enabledRaw = '';
        }
        $enabledList = ($enabledRaw !== '') ? (array) json_decode($enabledRaw, true) : array();
        if (!is_array($enabledList)) $enabledList = array();
        self::renderCompatScriptsList($compatScripts, $enabledList, $abC1);
        $compatEnabledScripts = new Typecho_Widget_Helper_Form_Element_Hidden(
            'compat_disabledScripts',
            null,
            $enabledRaw
        );
        $form->addInput($compatEnabledScripts);
        $compatExternalJs = new Typecho_Widget_Helper_Form_Element_Textarea(
            'compat_externalJs',
            null,
            '',
            _t('外部兼容脚本 URL'),
            _t('每行一个 JS 文件 URL，将在后台所有页面加载。示例：https://cdn.example.com/compat/my-plugin-fix.js')
        );
        $form->addInput($compatExternalJs);
        $telemetryOptOut = new Typecho_Widget_Helper_Form_Element_Radio(
            'telemetryOptOut',
            array(
                '0' => _t('允许'),
                '1' => _t('关闭统计'),
            ),
            '0',
            _t('匿名使用统计'),
            _t('启用后，插件会通过浏览器向开发者统计服务发送，仅包含：插件版本号、站点表示。选择"关闭统计"后立即停止上报。')
        );
        $form->addInput($telemetryOptOut);
        $notifyOptOut = new Typecho_Widget_Helper_Form_Element_Radio(
            'notifyOptOut',
            array(
                '0' => _t('显示通知（默认）'),
                '1' => _t('关闭通知'),
            ),
            '0',
            _t('插件通知'),
            _t('关闭后，将不再显示插件更新横幅通知和公告弹窗。')
        );
        $form->addInput($notifyOptOut);
        include dirname(__FILE__) . '/assets/pages/config/config.script.php';
        include dirname(__FILE__) . '/assets/pages/config/about.php';
        include dirname(__FILE__) . '/assets/pages/config/notice.script.php';
    }
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }
    public static function renderHeader($header)
    {
        $header .= '<script>(function(){try{'
            . 'console.log('
            .   '"%c AB-Admin %c v2.1.38 %c",'
            .   '"background:#6750a4;color:#fff;padding:3px 10px;border-radius:3px 0 0 3px;font-family:sans-serif;font-size:12px;font-weight:600",'
            .   '"background:#625b71;color:#fff;padding:3px 10px;font-family:sans-serif;font-size:12px",'
            .   '"background:#e8def8;color:#21005d;padding:3px 10px;border-radius:0 3px 3px 0;font-family:sans-serif;font-size:12px"'
            . ');'
            . 'console.log("%c  \uD83D\uDD17 https://see.lhl.one/Typecho-AB-Admin ","color:#6750a4;font-size:11px");'
            . '}catch(e){}})();</script>';
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginOptions = $options->plugin('AdminBeautify');
        $loginIsEnabled = isset($pluginOptions->login_isEnabled) ? (string)$pluginOptions->login_isEnabled : '1';
        if (self::isLoginPage() && $loginIsEnabled == '1') {
            ob_start();
            self::outputLoginHeaderCss();
            $inject = ob_get_clean();
            return $header . $inject;
        } else if (self::isLoginPage()) {
            return $header;
        }
        return self::renderAdminHeader($header);
    }
    private static function renderAdminHeader($header)
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginOptions = $options->plugin('AdminBeautify');
        $primaryColor = $pluginOptions->primaryColor ?: 'purple';
        $darkMode = $pluginOptions->darkMode ?: 'auto';
        $borderRadius = $pluginOptions->borderRadius ?: 'medium';
        $rawAnim = isset($pluginOptions->enableAnimation) ? (string)$pluginOptions->enableAnimation : '';
        $enableAnimation = ($rawAnim !== '') ? $rawAnim : '1';
        $navPosition = $pluginOptions->navPosition ?: 'left';
    $colors = self::getColorScheme($primaryColor);
    $lightBg = isset($colors['--md-surface'])      ? $colors['--md-surface']      : '#FFFBFE';
    $darkBg  = isset($colors['--md-dark-surface']) ? $colors['--md-dark-surface'] : '#1C1B1F';
    $cssUrl = Typecho_Common::url('AdminBeautify/assets/AdminBeautify', $options->pluginUrl);
        $earlyScript = '<script>';
        $earlyScript .= '(function(){';
        $earlyScript .= 'var s;try{s=localStorage.getItem("adminBeautifyTheme");}catch(e){s=null;}';
        $earlyScript .= 'var d;';
        $earlyScript .= 'if(s==="dark"){d=true;}';
        $earlyScript .= 'else if(s==="light"){d=false;}';
        $earlyScript .= 'else{';
        if ($darkMode === 'dark') {
            $earlyScript .= 'd=true;';
        } elseif ($darkMode === 'light') {
            $earlyScript .= 'd=false;';
        } else {
            $earlyScript .= 'd=!!(window.matchMedia&&window.matchMedia("(prefers-color-scheme:dark)").matches);';
        }
        $earlyScript .= '}';
        $earlyScript .= 'if(d){document.documentElement.setAttribute("data-theme","dark");document.documentElement.style.setProperty("color-scheme","dark");}';
        $earlyScript .= 'else{document.documentElement.removeAttribute("data-theme");document.documentElement.style.setProperty("color-scheme","light");}';
        $earlyScript .= '})();';
        if ($navPosition === 'left') {
            $earlyScript .= 'document.documentElement.setAttribute("data-nav","left");if(localStorage.getItem("adminBeautifySidebarCollapsed")==="1"){document.documentElement.setAttribute("data-nav-collapsed","");}';
        }
        if ($enableAnimation === '0') {
            $earlyScript .= 'document.documentElement.setAttribute("data-no-animation","");';
        }
        $earlyScript .= 'document.documentElement.setAttribute("data-ab-loading","");';
        $earlyScript .= '</script>';
        $colors = self::getColorScheme($primaryColor);
        $lightBg = isset($colors['--md-surface'])      ? $colors['--md-surface']      : '#FFFBFE';
        $darkBg  = isset($colors['--md-dark-surface']) ? $colors['--md-dark-surface'] : '#1C1B1F';
        $colorSchemeValue = 'light dark';
        $injectHead = "\n" . '<meta name="color-scheme" content="' . $colorSchemeValue . '">';
        $injectHead .= "\n" . $earlyScript;
        $injectHead .= "\n" . '<style>';
        $injectHead .= '[data-ab-loading],[data-ab-loading] *{transition:none!important;}';
        $injectHead .= '[data-ab-loading] .typecho-head-nav{visibility:hidden!important;}';
        $injectHead .= 'html,body{background:' . $lightBg . '!important;}';
        $injectHead .= 'html[data-theme="dark"],html[data-theme="dark"] body{background:' . $darkBg . '!important;}';
        $injectHead .= ':root{';
        $injectHead .= 'color-scheme:' . $colorSchemeValue . ';';
        foreach ($colors as $key => $value) {
            $injectHead .= $key . ':' . $value . ';';
        }
        $radiusMap = array(
            'small'  => array('--md-radius-xs' => '4px', '--md-radius-sm' => '6px', '--md-radius-md' => '8px', '--md-radius-lg' => '12px', '--md-radius-xl' => '16px', '--md-radius-full' => '9999px'),
            'medium' => array('--md-radius-xs' => '6px', '--md-radius-sm' => '8px', '--md-radius-md' => '12px', '--md-radius-lg' => '16px', '--md-radius-xl' => '28px', '--md-radius-full' => '9999px'),
            'large'  => array('--md-radius-xs' => '8px', '--md-radius-sm' => '12px', '--md-radius-md' => '16px', '--md-radius-lg' => '24px', '--md-radius-xl' => '32px', '--md-radius-full' => '9999px'),
        );
        if (isset($radiusMap[$borderRadius])) {
            foreach ($radiusMap[$borderRadius] as $key => $value) {
                $injectHead .= $key . ':' . $value . ';';
            }
        }
        if ($enableAnimation === '0') {
            $injectHead .= '--md-transition-duration:0s;';
        } else {
            $injectHead .= '--md-transition-duration:0.2s;';
        }
        $injectHead .= '}';
        $injectHead .= '[data-ab-loading] body{visibility:hidden!important;}';
        $injectHead .= '#ab-page-loader{display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:999999;background:var(--md-surface,#FFFBFE);align-items:center;justify-content:center;}';
        $injectHead .= '[data-theme="dark"] #ab-page-loader{background:var(--md-dark-surface,#1C1B1F)!important;}';
        $injectHead .= '[data-ab-loading] #ab-page-loader{display:flex!important;visibility:visible!important;}';
        $injectHead .= '#ab-loader-spinner{width:44px;height:44px;border-radius:50%;border:3.5px solid rgba(0,0,0,0.1);border-top-color:var(--md-primary,#1976D2);animation:ab-spin 0.72s linear infinite;}';
        $injectHead .= '[data-theme="dark"] #ab-loader-spinner{border-color:rgba(255,255,255,0.1);border-top-color:var(--md-primary,#90CAF9);}';
        $injectHead .= '@keyframes ab-spin{to{transform:rotate(360deg)}}';
        $injectHead .= '</style>';
        $injectTail = "\n" . '<link rel="stylesheet" href="' . $cssUrl . '.' .'v2.1.38' . '.css">';
        $editorVditor = isset($pluginOptions->editor_vditor) ? (string)$pluginOptions->editor_vditor : '0';
        $reqUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $isWritePage = (strpos($reqUri, 'write-post.php') !== false || strpos($reqUri, 'write-page.php') !== false);
        if ($editorVditor === '1' && $isWritePage) {
            $vditorIndexCssUrl = Typecho_Common::url('AdminBeautify/assets/lib/vditor/index.css', $options->pluginUrl);
            echo '<link rel="stylesheet" href="' . htmlspecialchars($vditorIndexCssUrl) . '">';
        }
        $editorHideToolbar = isset($pluginOptions->editor_hideToolbar) ? (string)$pluginOptions->editor_hideToolbar : '0';
        if ($editorVditor === '2' && $editorHideToolbar === '1' && $isWritePage) {
            $injectTail .= '<style>body #wmd-button-bar,body .wmd-button-bar{display:none!important;}</style>';
        }
        $staticResource = isset($pluginOptions->staticResource) ? (string) $pluginOptions->staticResource : 'google';
        $customFontUrl  = isset($pluginOptions->customFontUrl)  ? trim((string) $pluginOptions->customFontUrl)  : '';
        $customIconUrl  = isset($pluginOptions->customIconUrl)  ? trim((string) $pluginOptions->customIconUrl)  : '';
        $localFontUrl   = isset($pluginOptions->localFontUrl)   ? trim((string) $pluginOptions->localFontUrl)   : '';
        $localIconUrl   = isset($pluginOptions->localIconUrl)   ? trim((string) $pluginOptions->localIconUrl)   : '';
        if ($staticResource === 'google') {
            $resFontUrl = 'https://fonts.googleapis.com/css2?family=Not o+Sans+SC:wght@400;500;600;700&display=swap';
            $resIconUrl = 'https://fonts.googleapis.com/icon?family=Material+Icons+Round';
        } elseif ($staticResource === 'loli') {
            $resFontUrl = 'https://fonts.loli.net/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap';
            $resIconUrl = 'https://fonts.loli.net/icon?family=Material+Icons+Round';
        } elseif ($staticResource === 'jsdelivr') {
            $resFontUrl = 'https://cdn.jsdelivr.net/npm/noto-sans-sc@37.0.0/noto_sans_sc_medium/css.min.css';
            $resIconUrl = 'https://cdn.jsdelivr.net/npm/material-icons@1.13.14/iconfont/material-icons.min.css';
        } elseif ($staticResource === 'local') {
            $localPluginFontDefault = Typecho_Common::url('AdminBeautify/assets/fonts/Noto-Sans-SC-CSS/all.css', $options->pluginUrl);
            $localPluginIconDefault = Typecho_Common::url('AdminBeautify/assets/fonts/MDIR/MaterialIconsRound.css', $options->pluginUrl);
            $resFontUrl = ($localFontUrl !== '') ? $localFontUrl : $localPluginFontDefault;
            $resIconUrl = ($localIconUrl !== '') ? $localIconUrl : $localPluginIconDefault;
        } elseif ($staticResource === 'custom') {
            $resFontUrl = $customFontUrl;
            $resIconUrl = $customIconUrl;
        } else {
            $resFontUrl = '';
            $resIconUrl = '';
        }
        if ($resFontUrl !== '') {
            $injectTail .= "\n" . '<link rel="stylesheet" href="' . htmlspecialchars($resFontUrl) . '">';
        }
        if ($resIconUrl !== '') {
            $injectTail .= "\n" . '<link rel="stylesheet" href="' . htmlspecialchars($resIconUrl) . '">';
        }
        $injectTail .= '<script>document.addEventListener("DOMContentLoaded",function(){document.documentElement.removeAttribute("data-ab-loading");},false);</script>';
        $themeColorMap = array(
            'purple' => '#7D5260', 'blue' => '#556270', 'teal' => '#4A6363',
            'green'  => '#55624C', 'orange' => '#725A42', 'pink' => '#74565F', 'red' => '#775654',
        );
        $themeHex = isset($themeColorMap[$primaryColor]) ? $themeColorMap[$primaryColor] : '#7D5260';
        $manifestUrl = Typecho_Common::url('/action/admin-beautify?do=manifest', $options->index);
        $pwaAppName = isset($pluginOptions->pwa_appName) ? trim((string) $pluginOptions->pwa_appName) : '';
        $pwaAppIcon = isset($pluginOptions->pwa_appIcon) ? trim((string) $pluginOptions->pwa_appIcon) : '';
        $pwaTitle = ($pwaAppName !== '') ? $pwaAppName : ((string) $options->title . ' 管理后台');
        $injectTail .= "\n" . '<link rel="manifest" href="' . htmlspecialchars($manifestUrl) . '">';
        $injectTail .= "\n" . '<meta name="theme-color" content="' . $themeHex . '">';
        $injectTail .= "\n" . '<meta name="apple-mobile-web-app-capable" content="yes">';
        $injectTail .= "\n" . '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">';
        $injectTail .= "\n" . '<meta name="apple-mobile-web-app-title" content="' . htmlspecialchars($pwaTitle) . '">';
        $injectTail .= "\n" . '<meta name="mobile-web-app-capable" content="yes">';
        if ($pwaAppIcon !== '') {
            $injectTail .= "\n" . '<link rel="apple-touch-icon" sizes="180x180" href="' . htmlspecialchars($pwaAppIcon) . '">';
        }
        $telemetryOptOut = isset($pluginOptions->telemetryOptOut) ? (string)$pluginOptions->telemetryOptOut : '0';
        if ($telemetryOptOut !== '1') {
            $injectTail .= "\n" . '<script defer src="https://umami.lhl.one/script.js" data-website-id="dfabc99f-991e-4f7c-9358-03177fbee0ec"></script>';
        }
        return $injectHead . $header . $injectTail;
    }
    public static function renderFooter()
    {
        if (self::isLoginPage()) {
            return;
        }
        echo '<div id="ab-page-loader"><div id="ab-loader-spinner"></div></div>';
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginOptions = $options->plugin('AdminBeautify');
        $darkMode = $pluginOptions->darkMode ?: 'auto';
        $rawAnim = isset($pluginOptions->enableAnimation) ? (string)$pluginOptions->enableAnimation : '';
        $enableAnimation = ($rawAnim !== '') ? $rawAnim : '1';
        $pluginCardView = isset($pluginOptions->pluginCardView) ? (string)$pluginOptions->pluginCardView : '1';
        $editorVditor = isset($pluginOptions->editor_vditor) ? (string)$pluginOptions->editor_vditor : '0';
        $editorVditorMode = isset($pluginOptions->editor_vditorMode) ? (string)$pluginOptions->editor_vditorMode : 'ir';
        $dashboardQuickShow = isset($pluginOptions->dashboardQuickShow) ? (string)$pluginOptions->dashboardQuickShow : '1';
        $dashboardQuickStyle = isset($pluginOptions->dashboardQuickStyle) ? (string)$pluginOptions->dashboardQuickStyle : 'small';
        $dashboardQuickHint = isset($pluginOptions->dashboardQuickHint) ? (string)$pluginOptions->dashboardQuickHint : '1';
        $dashboardHideDonate = isset($pluginOptions->dashboardHideDonate) ? (string)$pluginOptions->dashboardHideDonate : '0';
        $dashboardCustomButtons = isset($pluginOptions->dashboardCustomButtons) ? (string)$pluginOptions->dashboardCustomButtons : '';
        $dashboardRecentStyle = isset($pluginOptions->dashboardRecentStyle) ? (string)$pluginOptions->dashboardRecentStyle : 'md3';
        $overviewChartEnabled = isset($pluginOptions->overviewChartEnabled) ? (string)$pluginOptions->overviewChartEnabled : '1';
        $overviewTimeRange    = isset($pluginOptions->overviewTimeRange)    ? (string)$pluginOptions->overviewTimeRange    : '30';
        $umamiEnabled         = isset($pluginOptions->umamiEnabled)         ? (string)$pluginOptions->umamiEnabled         : '0';
        $umamiApiBase         = isset($pluginOptions->umamiApiBase)         ? (string)$pluginOptions->umamiApiBase         : '';
        $umamiWebsiteId       = isset($pluginOptions->umamiWebsiteId)       ? (string)$pluginOptions->umamiWebsiteId       : '';
        $umamiApiToken        = isset($pluginOptions->umamiApiToken)        ? (string)$pluginOptions->umamiApiToken        : '';
        $umamiTimeRange       = isset($pluginOptions->umamiTimeRange)       ? (string)$pluginOptions->umamiTimeRange       : '30';
        $primaryColorScheme = $pluginOptions->primaryColor ?: 'purple';
        $colorSchemeData = self::getColorScheme($primaryColorScheme);
        $primaryColorHex     = $colorSchemeData['--md-primary'];
        $primaryColorDarkHex = $colorSchemeData['--md-dark-primary'];
        $user = Typecho_Widget::widget('Widget_User');
        $avatarUrl  = '';
        $avatarHost = 'https://gravatar.loli.net/avatar';
        if ($user->hasLogin() && $user->mail) {
            $avatarSrcOpt   = isset($pluginOptions->avatarSource)    ? (string)$pluginOptions->avatarSource    : 'loli';
            $customAvatarOpt = isset($pluginOptions->customAvatarUrl) ? trim((string)$pluginOptions->customAvatarUrl) : '';
            $avatarHostMap = array(
                'loli'     => 'https://gravatar.loli.net/avatar',
                'gravatar' => 'https://www.gravatar.com/avatar',
                'cravatar' => 'https://cravatar.cn/avatar',
                'custom'   => rtrim($customAvatarOpt, '/'),
            );
            $avatarHost = (isset($avatarHostMap[$avatarSrcOpt]) && $avatarHostMap[$avatarSrcOpt] !== '')
                ? $avatarHostMap[$avatarSrcOpt]
                : 'https://gravatar.loli.net/avatar';
            $hash = md5(strtolower(trim($user->mail)));
            $avatarUrl = $avatarHost . '/' . $hash . '?s=80&d=mp';
        }
        $screenName = $user->hasLogin() ? $user->screenName : '';
        $ajaxUrl = Typecho_Common::url('/action/admin-beautify', $options->index);
        $security = Typecho_Widget::widget('Widget_Security');
        $token = $security->getToken($ajaxUrl);
        echo '<script>window.__AB_USER__=' . json_encode(array(
            'avatar' => $avatarUrl,
            'name'   => $screenName,
        )) . ';';
        echo 'window.__AB_AJAX__=' . json_encode(array(
            'url'   => $ajaxUrl,
            'token' => $token,
        )) . ';';
        echo 'window.__AB_AVATAR_HOST__=' . json_encode($avatarHost) . ';';
        echo '</script>';
        echo '<script>(function(){';
        echo 'var host=window.__AB_AVATAR_HOST__;';
        echo 'if(!host)return;';
        echo 'function replaceAvatar(img){';
        echo '  var src=img.getAttribute("src")||"";';
        echo '  var m=src.match(/https?:\/\/[^\/]+\/avatar\/([a-f0-9]{32})(.*)/);';
        echo '  if(m)img.setAttribute("src",host+"/"+m[1]+m[2]);';
        echo '}';
        echo 'function runReplace(){';
        echo '  var imgs=document.querySelectorAll(".profile-avatar img,.ab-profile-avatar-wrap img,td.author img,.typecho-list-table td img");';
        echo '  [].forEach.call(imgs,function(img){replaceAvatar(img);});';
        echo '}';
        echo 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",runReplace);}';
        echo 'else{runReplace();}';
        echo 'document.addEventListener("ab:pageload",runReplace);';
        echo '})();</script>';
        echo '<script>';
        $notifyOptOut = isset($pluginOptions->notifyOptOut) ? (string)$pluginOptions->notifyOptOut : '0';
        $customBtnsRaw = $dashboardCustomButtons;
        $customBtnsParsed = array();
        foreach (array_filter(array_map('trim', explode("\n", $customBtnsRaw))) as $line) {
            $parts = array_map('trim', explode(':', $line, 4));
            if (count($parts) >= 2 && $parts[0] !== '' && $parts[1] !== '') {
                $customBtnsParsed[] = array(
                    'label'     => $parts[0],
                    'href'      => $parts[1],
                    'icon'      => isset($parts[2]) && $parts[2] !== '' ? $parts[2] : 'link',
                    'highlight' => isset($parts[3]) && trim(strtolower($parts[3])) === 'highlight',
                );
            }
        }
        $enabledCompatPlugins = array();
        $compatEnabledRaw = isset($pluginOptions->compat_disabledScripts) ? (string)$pluginOptions->compat_disabledScripts : '';
        $compatEnabledFiles = ($compatEnabledRaw !== '') ? (array)json_decode($compatEnabledRaw, true) : array();
        if (is_array($compatEnabledFiles) && !empty($compatEnabledFiles)) {
            $compatDir = dirname(__FILE__) . '/assets/compat/';
            foreach ($compatEnabledFiles as $compatFile) {
                if (substr($compatFile, -3) === '.js' && is_file($compatDir . $compatFile)) {
                    $compatMeta = self::parseCompatMeta($compatDir . $compatFile);
                    if (!empty($compatMeta['plugins'])) {
                        foreach (array_map('trim', explode(',', $compatMeta['plugins'])) as $pName) {
                            if ($pName !== '') $enabledCompatPlugins[] = strtolower($pName);
                        }
                    }
                }
            }
        }
        $enabledCompatPlugins = array_values(array_unique($enabledCompatPlugins));
        $pendingCompatSuggestions = array();
        $compatDirScan = dirname(__FILE__) . '/assets/compat/';
        $pluginsBaseDir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR;
        if (is_dir($compatDirScan)) {
            $scanFiles = @scandir($compatDirScan);
            if ($scanFiles) {
                foreach ($scanFiles as $scanFile) {
                    if (substr($scanFile, -3) !== '.js' || !is_file($compatDirScan . $scanFile)) continue;
                    if (is_array($compatEnabledFiles) && in_array($scanFile, $compatEnabledFiles)) continue;
                    $scanMeta = self::parseCompatMeta($compatDirScan . $scanFile);
                    if (empty($scanMeta['plugins'])) continue;
                    $scanPluginList = array_map('trim', explode(',', $scanMeta['plugins']));
                    foreach ($scanPluginList as $scanPlugin) {
                        if ($scanPlugin === '') continue;
                        if (is_dir($pluginsBaseDir . $scanPlugin)) {
                            $pendingCompatSuggestions[] = array(
                                'file'        => $scanFile,
                                'name'        => ($scanMeta['name'] !== '' ? $scanMeta['name'] : basename($scanFile, '.js')),
                                'plugin'      => $scanPlugin,
                                'description' => $scanMeta['description'],
                            );
                            break;
                        }
                    }
                }
            }
        }
        $pluginSettingsUrl = $options->adminUrl . 'options-plugin.php?config=AdminBeautify';
        $currentPageCompatKey = '';
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (strpos($requestUri, 'options-plugin.php') !== false && isset($_GET['config'])) {
            $currentPageCompatKey = strtolower(trim((string)$_GET['config']));
        } elseif (strpos($requestUri, 'options-theme.php') !== false) {
            $currentPageCompatKey = strtolower(trim((string)$options->theme));
        } elseif (strpos($requestUri, 'extending.php') !== false && isset($_GET['panel'])) {
            $panelParts = explode('/', trim((string)$_GET['panel']));
            $currentPageCompatKey = strtolower($panelParts[0]);
        }
        echo 'window.__AB_CONFIG__=' . json_encode(array(
            'darkMode'               => $darkMode,
            'enableAnimation'        => $enableAnimation,
            'pluginCardView'         => $pluginCardView,
            'siteName'               => $options->title,
            'editorVditor'           => $editorVditor,
            'editorVditorMode'       => $editorVditorMode,
            'pluginVersion'          => '2.1.38',
            'notifyOptOut'           => $notifyOptOut,
            'dashboardQuickShow'     => $dashboardQuickShow,
            'dashboardQuickStyle'    => $dashboardQuickStyle,
            'dashboardQuickHint'     => $dashboardQuickHint,
            'dashboardHideDonate'    => $dashboardHideDonate,
            'dashboardCustomButtons' => $customBtnsParsed,
            'dashboardRecentStyle'   => $dashboardRecentStyle,
            'overviewChartEnabled'   => $overviewChartEnabled,
            'overviewTimeRange'      => $overviewTimeRange,
            'umamiEnabled'           => $umamiEnabled,
            'umamiApiBase'           => $umamiApiBase,
            'umamiWebsiteId'         => $umamiWebsiteId,
            'umamiApiToken'          => $umamiApiToken,
            'umamiTimeRange'         => $umamiTimeRange,
            'primaryColorHex'        => $primaryColorHex,
            'primaryColorDarkHex'    => $primaryColorDarkHex,
            'enabledCompatPlugins'       => $enabledCompatPlugins,
            'currentPageCompatKey'       => $currentPageCompatKey,
            'pendingCompatSuggestions'   => $pendingCompatSuggestions,
            'pluginSettingsUrl'          => $pluginSettingsUrl,
        )) . ';</script>';
        $jsUrlPrefix = Typecho_Common::url('AdminBeautify/assets/AdminBeautify.min', $options->pluginUrl);
        echo '<script src="' . $jsUrlPrefix . '.v2.1.38.js"></script>';
        $reqUriForEditor = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $isWritePageForEditor = (strpos($reqUriForEditor, 'write-post.php') !== false || strpos($reqUriForEditor, 'write-page.php') !== false);
        if ($editorVditor === '2' && $isWritePageForEditor) {
            echo '<script>if(window.AdminBeautify){AdminBeautify.initEditorToolbar=function(){};}</script>';
        }
        if ($darkMode === 'auto') {
            echo '<script>AdminBeautify.watchSystemTheme();</script>';
        }
        $telemetryOptOut = isset($pluginOptions->telemetryOptOut) ? (string)$pluginOptions->telemetryOptOut : '0';
        if ($telemetryOptOut !== '1') {
            echo '<script>(function(){function abTrack(){if(window.umami&&typeof window.umami.track==="function"){window.umami.track("settings_visit",{domain:window.location.hostname,version:"2.1.38"});}else{setTimeout(abTrack,300);}}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",function(){setTimeout(abTrack,200);});}else{setTimeout(abTrack,200);}})();</script>';
        }
        if ($notifyOptOut !== '1') {
            echo '<script>(function(){
var CFG=window.__AB_CONFIG__||{};
var ver=CFG.pluginVersion||"";
if(!ver) return;
var lsKey="ab-seen-version";
var seen="";
try{seen=localStorage.getItem(lsKey)||"";}catch(e){}
if(seen===ver) return;
function mkBanner(release){
    var tag=release.tag_name||ver;
    var body=release.body||"";
    var url=release.html_url||("https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases");
    var lines=body.split("\n").filter(function(l){return l.trim()!=="";});
    var preview=lines.slice(0,4).join("\n");
    var hasMore=lines.length>4;
    var wrap=document.createElement("div");
    wrap.id="ab-banner-notify";
    wrap.setAttribute("role","region");
    wrap.setAttribute("aria-label","插件更新通知");
    wrap.style.transform="translateX(380px)";
    var hdr=document.createElement("div");
    hdr.className="ab-notify-hdr";
    var ic=document.createElement("span");
    ic.textContent="🎉";
    ic.style.fontSize="18px";
    var ttl=document.createElement("div");
    ttl.className="ab-notify-title";
    ttl.textContent="AdminBeautify 已更新";
    var pill=document.createElement("span");
    pill.className="ab-notify-pill";
    pill.textContent=tag;
    var cls=document.createElement("button");
    cls.className="ab-notify-close";
    cls.setAttribute("aria-label","关闭");
    cls.textContent="×";
    cls.onclick=function(){
        wrap.style.transform="translateX(380px)";
        setTimeout(function(){wrap.parentNode&&wrap.parentNode.removeChild(wrap);},400);
        try{localStorage.setItem(lsKey,ver);}catch(e){}
    };
    hdr.appendChild(ic);hdr.appendChild(ttl);hdr.appendChild(pill);hdr.appendChild(cls);
    var bd=document.createElement("div");
    bd.className="ab-notify-bd";
    var pre=document.createElement("pre");
    pre.id="ab-banner-preview";
    pre.className="ab-notify-pre";
    pre.textContent=preview;
    bd.appendChild(pre);
    if(hasMore){
        var full=document.createElement("pre");
        full.id="ab-banner-full";
        full.className="ab-notify-pre-full";
        full.textContent=lines.join("\n");
        bd.appendChild(full);
        var tog=document.createElement("button");
        tog.className="ab-notify-toggle";
        tog.textContent="展开更多 ▾";
        tog.onclick=function(){
            var expanded=full.style.display!=="none";
            full.style.display=expanded?"none":"block";
            pre.style.display=expanded?"block":"none";
            tog.textContent=expanded?"展开更多 ▾":"收起 ▴";
        };
        bd.appendChild(tog);
    }
    var ft=document.createElement("div");
    ft.className="ab-notify-ft";
    var lnk=document.createElement("a");
    lnk.href=url;lnk.target="_blank";lnk.rel="noopener";
    lnk.className="ab-notify-link";
    lnk.textContent="查看完整更新日志 →";
    ft.appendChild(lnk);
    wrap.appendChild(hdr);wrap.appendChild(bd);wrap.appendChild(ft);
    document.body.appendChild(wrap);
    setTimeout(function(){wrap.style.transform="translateX(0)";},50);
}
// api.github.com 直连（不走代理，避免封禁）
(function(){
    fetch("https://api.github.com/repos/lhl77/Typecho-Plugin-AdminBeautify/releases/latest",{cache:"no-cache"})
        .then(function(r){return r.ok?r.json():Promise.reject();})
        .then(function(d){if(d&&d.tag_name)mkBanner(d);})
        .catch(function(){});
}());
})();</script>';
        }
        $reqUriFooter = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $isWritePageFooter = (strpos($reqUriFooter, 'write-post.php') !== false || strpos($reqUriFooter, 'write-page.php') !== false);
        if ($editorVditor === '1' && $isWritePageFooter) {
            $vditorCssUrl = Typecho_Common::url('AdminBeautify/assets/lib/vditor/vditor_v1.0.1.css', $options->pluginUrl);
            $vditorJsUrl  = Typecho_Common::url('AdminBeautify/assets/lib/vditor/vditor_v1.0.3.js', $options->pluginUrl);
            echo '<link rel="stylesheet" href="' . htmlspecialchars($vditorCssUrl) . '">';
            echo '<script src="' . htmlspecialchars($vditorJsUrl) . '"></script>';
        }
        $swUrl = Typecho_Common::url('/action/admin-beautify?do=sw', $options->index);
        echo '<script>(function(){'
            . 'function abSwToast(){'
            .   'if(document.getElementById("ab-sw-toast"))return;'
            .   'var t=document.createElement("div");'
            .   't.id="ab-sw-toast";'
            .   't.setAttribute("role","alert");'
            .   'var isDark=document.documentElement.getAttribute("data-theme")==="dark";'
            .   'var bg=isDark?"#2d2b31":"#1c1b1f";'
            .   'var fg=isDark?"#e6e1e5":"#f3eff4";'
            .   'var btnBg=isDark?"rgba(208,188,255,.18)":"rgba(208,188,255,.22)";'
            .   't.style.cssText="position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);'
            .     'background:"+bg+";color:"+fg+";padding:14px 20px;border-radius:16px;'
            .     'box-shadow:0 4px 20px rgba(0,0,0,.35);display:flex;align-items:center;gap:14px;'
            .     'font-size:14px;font-family:inherit;z-index:9999;white-space:nowrap;'
            .     'transition:transform .35s cubic-bezier(.4,0,.2,1),opacity .35s;opacity:0;";'
            .   'var txt=document.createElement("span");'
            .   'txt.textContent="✨ 插件已更新，刷新后生效";'
            .   'var btn=document.createElement("button");'
            .   'btn.textContent="立即刷新";'
            .   'btn.style.cssText="border:none;background:"+btnBg+";color:"+fg+";padding:7px 16px;'
            .     'border-radius:999px;cursor:pointer;font-size:13px;font-weight:500;flex-shrink:0;'
            .     'transition:background .15s;";'
            .   'btn.onmouseover=function(){this.style.background=isDark?"rgba(208,188,255,.32)":"rgba(208,188,255,.36)";};'
            .   'btn.onmouseout=function(){this.style.background=btnBg;};'
            .   'btn.onclick=function(){window.location.reload();};'
            .   'var close=document.createElement("button");'
            .   'close.textContent="✕";'
            .   'close.title="关闭";'
            .   'close.style.cssText="border:none;background:transparent;color:"+fg+";'
            .     'cursor:pointer;font-size:16px;padding:2px 4px;opacity:.6;flex-shrink:0;";'
            .   'close.onclick=function(){'
            .     't.style.opacity="0";t.style.transform="translateX(-50%) translateY(80px)";'
            .     'setTimeout(function(){t.parentNode&&t.parentNode.removeChild(t);},400);'
            .   '};'
            .   't.appendChild(txt);t.appendChild(btn);t.appendChild(close);'
            .   'document.body.appendChild(t);'
            .   'requestAnimationFrame(function(){requestAnimationFrame(function(){'
            .     't.style.opacity="1";t.style.transform="translateX(-50%) translateY(0)";'
            .   '});});'
            . '}'
            . 'if("serviceWorker"in navigator){'
            .   'navigator.serviceWorker.addEventListener("message",function(e){'
            .     'if(e.data&&e.data.type==="SW_UPDATED"){ abSwToast(); }'
            .   '});'
            .   'var refreshing=false;'
            .   'navigator.serviceWorker.addEventListener("controllerchange",function(){'
            .     'if(refreshing)return;'
            .     '/* controller 已切换，toast 由 SW_UPDATED 消息触发，此处不再重复 */'
            .   '});'
            .   'navigator.serviceWorker.register(' . json_encode($swUrl) . ',{scope:' . json_encode(rtrim((string)$options->adminUrl, '/') . '/') . '})'
            .   '.then(function(reg){'
            .     'reg.addEventListener("updatefound",function(){'
            .       'var newSW=reg.installing;'
            .       'newSW.addEventListener("statechange",function(){'
            .         'if(newSW.state==="installed"&&navigator.serviceWorker.controller){'
            .           '/* 新 SW 已安装，等待激活后由 SW_UPDATED 消息触发 toast */'
            .           '/* 若 SW 没有自动 skipWaiting，则主动通知它跳过等待 */'
            .           'newSW.postMessage({type:"SKIP_WAITING"});'
            .         '}'
            .       '});'
            .     '});'
            .   '})'
            .   '.catch(function(){});'
            . '}'
            . '}());</script>';
        $pingUrl = Typecho_Common::url('/action/admin-beautify?do=ping', $options->index);
        echo '<script>(function(){'
            . 'var isStandalone=window.matchMedia&&window.matchMedia("(display-mode:standalone)").matches||window.navigator.standalone===true;'
            . 'if(!isStandalone)return;'
            . 'function renewCookies(){'
            .   'var cookies=document.cookie.split(";");'
            .   'for(var i=0;i<cookies.length;i++){'
            .     'var c=cookies[i].trim();'
            .     'if(c.indexOf("__typecho_uid=")=== 0||c.indexOf("__typecho_authCode=")===0){'
            .       'var eqIdx=c.indexOf("=");'
            .       'var name=c.substring(0,eqIdx);'
            .       'var value=c.substring(eqIdx+1);'
            .       'var d=new Date();d.setTime(d.getTime()+30*24*60*60*1000);'
            .       'document.cookie=name+"="+value+";expires="+d.toUTCString()+";path=/;SameSite=Lax";'
            .     '}'
            .   '}'
            . '}'
            . 'renewCookies();'
            . 'setInterval(renewCookies,10*60*1000);'
            . 'setInterval(function(){fetch(' . json_encode($pingUrl) . ',{credentials:"include"}).catch(function(){});},15*60*1000);'
            . '}());</script>';
        echo '<script>(function(){';
        echo 'var __AB_VER__="2.1.38";';
        echo <<<'UPDATEJS'
// ---- abCheckUpdate: 向后端请求最新版信息 ----
// manual=true  → ?force=1，跳过缓存直连 GitHub，等待真实结果（超时 25s）
// manual=false → 自动后台模式，stale-while-revalidate，立即返回缓存
window.abCheckUpdate=function(manual){
    var btn=document.getElementById("ab-btn-update");
    var origHTML=btn?btn.innerHTML:"";
    var ajax=window.__AB_AJAX__||{};
    if(!ajax.url) return;
    var spinSVG='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:ab-spin 1s linear infinite"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>';
    if(btn){ btn.disabled=true; btn.innerHTML=spinSVG+' 检查中...'; }
    var url=ajax.url+(manual?"?do=check-update&force=1":"?do=check-update");
    var xhr=new XMLHttpRequest();
    xhr.open("GET",url,true);
    xhr.setRequestHeader("X-Requested-With","XMLHttpRequest");
    // 手动：等待 GitHub 实时响应（最多 4 节点×5s=20s，预留余量）
    // 自动：后端立即返回缓存，8s 已足够
    xhr.timeout=manual?25000:8000;
    xhr.onload=function(){
        if(btn){ btn.disabled=false; btn.innerHTML=origHTML; }
        try{
            var res=JSON.parse(xhr.responseText);
            if(res.code!==0){
                if(manual) abShowUpdateToast("error","❌ "+(res.message||"检查失败"));
                return;
            }
            var d=res.data;
            // ── 自动后台模式：stale-while-revalidate 处理 ──
            if(!manual && d.cache_stale){
                // 若有旧缓存数据且有更新，仍然展示（不阻止旧通知）
                if(d.has_update && d.latest){
                    try{ if(localStorage.getItem("ab-update-dismissed-v"+d.latest)) return; }catch(e){}
                    abShowUpdateAvailable(d);
                }
                // 10 秒后自动重新检查，等后台刷新完成
                setTimeout(function(){ window.abCheckUpdate(false); }, 10000);
                return;
            }
            // ── 手动模式且网络不通（force_failed）：已降级返回缓存 ──
            if(manual && d.force_failed){
                abShowUpdateToast("error","⚠️ 无法连接 GitHub，以下为缓存结果");
                // 继续往下展示缓存内容，不 return
            }
            // 写入 localStorage（手动直连的新鲜数据，或自动命中新鲜缓存）
            if(!d.cache_stale && !d.force_failed){
                try{ localStorage.setItem("ab-update-check",JSON.stringify({ts:Date.now(),data:d})); }catch(e){}
            }
            if(!d.has_update){
                // 已是最新版：清理旧"已忽略"标记
                try{
                    var keysToRemove=[];
                    for(var k in localStorage){
                        if(k.indexOf("ab-update-dismissed-v")===0) keysToRemove.push(k);
                    }
                    keysToRemove.forEach(function(k){ localStorage.removeItem(k); });
                }catch(e){}
                if(manual) abShowUpdateToast("ok","✅ 已是最新版本 v"+d.current);
                return;
            }
            // 检查是否已忽略该版本
            try{ if(localStorage.getItem("ab-update-dismissed-v"+d.latest)) return; }catch(e){}
            abShowUpdateAvailable(d);
        }catch(e){
            if(manual) abShowUpdateToast("error","❌ 响应解析失败");
        }
    };
    xhr.onerror=xhr.ontimeout=function(){
        if(btn){ btn.disabled=false; btn.innerHTML=origHTML; }
        if(manual) abShowUpdateToast("error","❌ 检查超时，服务器无法访问 GitHub");
    };
    xhr.send();
};
// ---- abShowUpdateAvailable: 配置页用 banner 内嵌，其他页面用固定顶栏 ----
window.abShowUpdateAvailable=function(d){
    var old=document.getElementById("ab-update-notify"); if(old) old.remove();
    var banner=document.getElementById("ab-header-banner");
    var notify=document.createElement("div");
    notify.id="ab-update-notify";
    var dismissBtn='<button type="button" onclick="(function(el){el.remove();try{localStorage.setItem(\'ab-update-dismissed-v\'+el.dataset.ver,\'1\')}catch(e){}})(document.getElementById(\'ab-update-notify\'))" data-ver="'+d.latest+'" style="background:none;border:none;cursor:pointer;font-size:16px;opacity:.7;padding:0 0 0 8px;color:inherit;line-height:1" title="忽略此版本">✕</button>';
    // 统一按钮基础样式：圆角描边，透明背景，继承颜色
    // box-sizing + line-height + vertical-align 三项确保 button/a/span 高度完全一致
    var btnBase='display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:500;line-height:1.4;white-space:nowrap;text-decoration:none;border:1px solid currentColor;box-sizing:border-box;vertical-align:middle;';
    var actionBtns='';
    if(d.can_direct){
        actionBtns+='<button id="ab-btn-do-update" type="button" onclick="abDoUpdate()" style="'+btnBase+'background:rgba(255,255,255,.25);color:inherit;cursor:pointer;">立即更新</button>';
    } else {
        // 不支持直接更新：显示禁用态按钮 + 原因提示
        actionBtns+='<span style="'+btnBase+'background:rgba(255,255,255,.08);color:inherit;opacity:.5;cursor:not-allowed;" title="当前版本跨越了主/次版本号，需手动下载">立即更新</span>'
                   +'<span style="font-size:11px;opacity:.65;align-self:center;">需手动更新</span>';
    }
    actionBtns+='<a href="'+d.html_url+'" target="_blank" style="'+btnBase+'background:transparent;color:inherit;opacity:.85;color:white!important">查看详情</a>';
    if(banner){
        // 配置页：嵌入 banner 内
        notify.style.cssText="margin:12px 0 0;padding:12px 16px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.35);border-radius:14px;font-size:13px;color:#fff;animation:ab-fadeIn .3s ease;backdrop-filter:blur(4px)";
        var bodyText='';
        if(d.body){ bodyText=d.body.replace(/[#*`]/g,"").replace(/\r?\n/g," ").trim(); if(bodyText.length>120) bodyText=bodyText.substring(0,120)+"..."; }
        notify.innerHTML='<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">'
            +'<div style="flex:1"><div style="font-weight:600;margin-bottom:'+(bodyText?'6px':'0')+'">🎉 发现新版本 <strong>v'+d.latest+'</strong> <span style="font-size:11px;opacity:.7;font-weight:400">（当前 v'+d.current+'）</span></div>'
            +(bodyText?'<div style="font-size:12px;opacity:.8;margin-bottom:10px;line-height:1.5">'+bodyText+'</div>':'')
            +'<div style="display:flex;gap:8px;flex-wrap:wrap">'+actionBtns+'</div></div>'
            +dismissBtn+'</div>';
        banner.appendChild(notify);
    } else {
        // 其他页面：固定顶部通知栏
        notify.style.cssText="position:fixed;top:0;left:0;right:0;z-index:99999;padding:8px 16px;background:linear-gradient(90deg,#5c6bc0,#7e57c2);color:#fff;font-size:13px;display:flex;align-items:center;gap:10px;box-shadow:0 2px 12px rgba(0,0,0,.25);animation:ab-slideDown .3s ease;font-weight:500";
        notify.innerHTML='<span style="flex:1">🎉 AB-Admin 发现新版本 <strong>v'+d.latest+'</strong> <span style="opacity:.8;font-size:12px;font-weight:400">（当前 v'+d.current+'）</span></span>'
            +'<div style="display:flex;gap:8px;align-items:center">'+actionBtns+dismissBtn+'</div>';
        document.body.style.paddingTop=(parseInt(document.body.style.paddingTop||0)+40)+'px';
        document.body.insertBefore(notify,document.body.firstChild);
    }
    if(d.can_direct){
        notify.setAttribute("data-download-url",d.download_url||"");
        notify.setAttribute("data-new-version",d.latest);
    }
};
// ---- abDoUpdate: 执行直接更新（fetch 流式版，全屏进度遮罩）----
window.abDoUpdate=function(){
    var notify=document.getElementById("ab-update-notify");
    if(!notify) return;
    var dlUrl=notify.getAttribute("data-download-url");
    var newVer=notify.getAttribute("data-new-version");
    if(!dlUrl||!newVer){ window.open("https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases","_blank"); return; }
    var ajax=window.__AB_AJAX__||{};
    if(!ajax.url) return;
    // ---- 注入全屏进度遮罩 ----
    var oldOvl=document.getElementById("ab-update-overlay");
    if(oldOvl) oldOvl.remove();
    // 注入遮罩样式（幂等）
    if(!document.getElementById("ab-update-overlay-style")){
        var st=document.createElement("style");
        st.id="ab-update-overlay-style";
        st.textContent=[
            "#ab-update-overlay{",
            "  position:fixed;inset:0;z-index:999999;",
            "  display:flex;align-items:center;justify-content:center;",
            "  background:rgba(0,0,0,.55);backdrop-filter:blur(6px);",
            "  -webkit-backdrop-filter:blur(6px);",
            "  animation:ab-ovl-in .25s ease;",
            "  padding:16px;box-sizing:border-box;",
            "}",
            "@keyframes ab-ovl-in{from{opacity:0}to{opacity:1}}",
            "#ab-update-card{",
            "  width:100%;max-width:420px;",
            "  background:var(--md-surface,#fff);",
            "  color:var(--md-on-surface,#1c1b1f);",
            "  border-radius:28px;",
            "  padding:32px 28px 28px;",
            "  box-shadow:0 8px 40px rgba(0,0,0,.28);",
            "  box-sizing:border-box;",
            "  animation:ab-card-in .3s cubic-bezier(.4,0,.2,1);",
            "}",
            "[data-theme=dark] #ab-update-card{",
            "  background:var(--md-dark-surface,#1c1b1f);",
            "  color:var(--md-dark-on-surface,#e6e1e5);",
            "  box-shadow:0 8px 40px rgba(0,0,0,.55);",
            "}",
            "@keyframes ab-card-in{from{transform:translateY(24px);opacity:0}to{transform:translateY(0);opacity:1}}",
            "#ab-update-card .ab-ovl-icon{font-size:36px;text-align:center;margin-bottom:16px;}",
            "#ab-update-card .ab-ovl-title{",
            "  font-size:18px;font-weight:700;text-align:center;",
            "  margin-bottom:6px;",
            "  color:var(--md-on-surface,#1c1b1f);",
            "}",
            "[data-theme=dark] #ab-update-card .ab-ovl-title{color:var(--md-dark-on-surface,#e6e1e5);}",
            "#ab-update-card .ab-ovl-sub{",
            "  font-size:13px;text-align:center;",
            "  color:var(--md-on-surface-variant,#49454f);",
            "  margin-bottom:24px;",
            "}",
            "[data-theme=dark] #ab-update-card .ab-ovl-sub{color:var(--md-dark-on-surface-variant,#cac4d0);}",
            "#ab-ovl-bar-track{",
            "  height:6px;border-radius:3px;overflow:hidden;",
            "  background:var(--md-surface-variant,#e7e0ec);",
            "  margin-bottom:10px;",
            "}",
            "[data-theme=dark] #ab-ovl-bar-track{background:var(--md-dark-surface-variant,#49454f);}",
            "#ab-ovl-bar-fill{",
            "  height:100%;border-radius:3px;",
            "  background:var(--md-primary,#6750a4);",
            "  transition:width .45s cubic-bezier(.4,0,.2,1);",
            "  width:0%;",
            "}",
            "#ab-ovl-msg{",
            "  font-size:13px;text-align:center;min-height:20px;",
            "  color:var(--md-on-surface-variant,#49454f);",
            "}",
            "[data-theme=dark] #ab-ovl-msg{color:var(--md-dark-on-surface-variant,#cac4d0);}",
            "#ab-ovl-pct{",
            "  font-size:12px;text-align:right;",
            "  color:var(--md-on-surface-variant,#49454f);",
            "  margin-bottom:4px;min-height:16px;",
            "}",
            "[data-theme=dark] #ab-ovl-pct{color:var(--md-dark-on-surface-variant,#cac4d0);}",
            "@keyframes ab-bar-pulse{0%,100%{opacity:.5}50%{opacity:1}}",
            /* ---- 按钮样式（含暗色适配）---- */
            ".ab-ovl-btn-primary{",
            "  display:inline-flex;align-items:center;justify-content:center;",
            "  padding:10px 20px;border-radius:20px;",
            "  background:var(--md-primary,#6750a4);color:#fff;",
            "  font-size:13px;font-weight:500;border:none;cursor:pointer;",
            "  text-decoration:none;gap:6px;",
            "}",
            ".ab-ovl-btn-secondary{",
            "  display:inline-flex;align-items:center;justify-content:center;",
            "  padding:10px 20px;border-radius:20px;",
            "  background:var(--md-surface-variant,#e7e0ec);",
            "  color:var(--md-on-surface,#1c1b1f);",
            "  font-size:13px;font-weight:500;border:none;cursor:pointer;gap:6px;",
            "  text-decoration:none;",
            "}",
            "[data-theme=dark] .ab-ovl-btn-secondary{",
            "  background:var(--md-dark-surface-variant,#49454f);",
            "  color:var(--md-dark-on-surface,#e6e1e5);",
            "}",
            "@media(max-width:480px){",
            "  #ab-update-card{padding:24px 18px 20px;border-radius:20px;}",
            "  #ab-update-card .ab-ovl-title{font-size:16px;}",
            "  .ab-ovl-btn-primary,.ab-ovl-btn-secondary{padding:9px 16px;font-size:12px;}",
            "}"
        ].join("");
        document.head.appendChild(st);
    }
    var overlay=document.createElement("div");
    overlay.id="ab-update-overlay";
    overlay.innerHTML=[
        '<div id="ab-update-card">',
        '  <div class="ab-ovl-icon">☁️</div>',
        '  <div class="ab-ovl-title">正在更新到 v'+newVer+'</div>',
        '  <div class="ab-ovl-sub">请勿关闭或刷新页面</div>',
        '  <div id="ab-ovl-pct"></div>',
        '  <div id="ab-ovl-bar-track"><div id="ab-ovl-bar-fill"></div></div>',
        '  <div id="ab-ovl-msg">连接服务器...</div>',
        '</div>'
    ].join("");
    document.body.appendChild(overlay);
    // 屏蔽键盘退出（ESC / 返回键）和页面关闭提示
    function onBeforeUnload(e){ e.preventDefault(); e.returnValue=''; }
    window.addEventListener("beforeunload", onBeforeUnload);
    document.addEventListener("keydown", function onKey(e){
        if(e.key==="Escape"){ e.stopPropagation(); e.preventDefault(); }
    }, true);
    var barFill=document.getElementById("ab-ovl-bar-fill");
    var barMsg =document.getElementById("ab-ovl-msg");
    var barPct =document.getElementById("ab-ovl-pct");
    var card   =document.getElementById("ab-update-card");
    var lastType="";
    function setProgress(pct, msg){
        if(barMsg) barMsg.textContent=msg||"";
        if(pct>=0){
            if(barFill){
                barFill.style.animation="";
                barFill.style.width=Math.min(100,pct)+"%";
            }
            if(barPct) barPct.textContent=Math.min(100,pct)+"%";
        } else {
            if(barFill){
                barFill.style.animation="ab-bar-pulse 1.2s ease infinite";
                barFill.style.width="35%";
            }
            if(barPct) barPct.textContent="";
        }
    }
    function showBtns(html){
        if(!card) return;
        var w=document.createElement("div");
        w.style.cssText="display:flex;gap:10px;justify-content:center;margin-top:20px;flex-wrap:wrap;";
        w.innerHTML=html;
        card.appendChild(w);
    }
    // 成功处理
    function onDone(){
        window.removeEventListener("beforeunload", onBeforeUnload);
        if(barFill){ barFill.style.animation=""; barFill.style.width="100%"; }
        if(barPct) barPct.textContent="100%";
        setTimeout(function(){
            if(card){
                card.querySelector(".ab-ovl-icon").textContent="✅";
                card.querySelector(".ab-ovl-title").textContent="更新成功！";
                card.querySelector(".ab-ovl-sub").textContent="即将自动刷新页面...";
            }
            try{ localStorage.removeItem("ab-update-check"); }catch(e){}
            try{
                if(navigator.serviceWorker&&navigator.serviceWorker.controller){
                    navigator.serviceWorker.controller.postMessage({type:"CLEAR_CACHE"});
                }
                if(navigator.serviceWorker&&navigator.serviceWorker.getRegistration){
                    navigator.serviceWorker.getRegistration().then(function(r){if(r)r.update();}).catch(function(){});
                }
            }catch(e){}
            setTimeout(function(){ location.reload(); },2500);
        },600);
    }
    // 失败处理
    function onError(msg){
        window.removeEventListener("beforeunload", onBeforeUnload);
        if(card){
            card.querySelector(".ab-ovl-icon").textContent="❌";
            card.querySelector(".ab-ovl-title").style.color="#dc2626";
            card.querySelector(".ab-ovl-title").textContent="更新失败";
            card.querySelector(".ab-ovl-sub").textContent=msg||"请前往 GitHub 手动下载";
            if(barFill) barFill.style.background="#dc2626";
        }
        showBtns(
            '<a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases"'+
            ' target="_blank" class="ab-ovl-btn-primary">前往 GitHub 下载</a>'+
            '<button class="ab-ovl-btn-secondary"'+
            ' onclick="document.getElementById(\'ab-update-overlay\').remove()">关闭</button>'
        );
    }
    // 连接中断处理
    function onInterrupt(){
        window.removeEventListener("beforeunload", onBeforeUnload);
        if(card){
            card.querySelector(".ab-ovl-icon").textContent="⚠️";
            card.querySelector(".ab-ovl-title").textContent="连接中断";
            card.querySelector(".ab-ovl-sub").textContent="更新状态未知，请手动检查后台文件是否已更新";
        }
        showBtns(
            '<button class="ab-ovl-btn-primary" onclick="location.reload()">刷新页面</button>'+
            '<button class="ab-ovl-btn-secondary"'+
            ' onclick="document.getElementById(\'ab-update-overlay\').remove()">关闭</button>'
        );
    }
    // 处理单个 SSE 事件
    function handleEvent(ev){
        var type=ev.type, msg=ev.message, pct=ev.progress;
        lastType=type;
        setProgress(pct, msg);
        if(type==="done") onDone();
        else if(type==="error") onError(msg);
    }
    // ---- 构造 SSE URL ----
    var sep=ajax.url.indexOf('?')>=0?'&':'?';
    var sseUrl=ajax.url+sep+"do=do-update-stream"
        +"&download_url="+encodeURIComponent(dlUrl)
        +"&new_version="+encodeURIComponent(newVer)
        +"&_="+encodeURIComponent(ajax.token||"");
    // ---- 优先使用 fetch + ReadableStream（兼容性更好，可读取错误内容）----
    // 不支持时降级到非流式 AJAX（do-update）
    var useStream=!!(window.fetch&&window.ReadableStream&&window.TextDecoder);
    if(useStream){
        fetch(sseUrl,{credentials:"same-origin",headers:{Accept:"text/event-stream"}})
        .then(function(resp){
            if(!resp.ok){
                return resp.text().then(function(t){
                    var detail=t?t.replace(/<[^>]+>/g,"").trim().substring(0,200):"";
                    onError("服务器返回 HTTP "+resp.status+(detail?"\n"+detail:""));
                });
            }
            var ct=resp.headers.get("content-type")||"";
            if(ct.indexOf("text/event-stream")<0){
                return resp.text().then(function(t){
                    var detail=t?t.replace(/<[^>]+>/g,"").trim().substring(0,200):"";
                    onError("响应格式异常（"+ct+"）"+(detail?"\n"+detail:""));
                });
            }
            // 流式读取 SSE 帧
            var reader=resp.body.getReader();
            var dec=new TextDecoder();
            var buf="";
            function readChunk(){
                return reader.read().then(function(r){
                    if(r.done){
                        if(lastType!=="done"&&lastType!=="error") onInterrupt();
                        return;
                    }
                    buf+=dec.decode(r.value,{stream:true});
                    var parts=buf.split("\n\n");
                    buf=parts.pop();
                    for(var i=0;i<parts.length;i++){
                        var m=parts[i].match(/^data:\s*(.+)$/m);
                        if(m){ try{ handleEvent(JSON.parse(m[1])); }catch(ex){} }
                    }
                    return readChunk();
                });
            }
            return readChunk();
        })
        .catch(function(err){
            if(lastType!=="done"&&lastType!=="error") onInterrupt();
        });
    } else {
        // 降级：非流式单次 AJAX（无实时进度，仅 loading 动画）
        setProgress(-1,"正在更新，请稍候...");
        var fd=new URLSearchParams();
        fd.append("do","do-update");
        fd.append("download_url",dlUrl);
        fd.append("new_version",newVer);
        fd.append("_",ajax.token||"");
        fetch(ajax.url,{method:"POST",body:fd,credentials:"same-origin"})
        .then(function(r){ return r.json(); })
        .then(function(d){
            if(d&&d.code===0) handleEvent({type:"done",message:"更新成功",progress:100});
            else onError((d&&d.message)||"更新失败");
        })
        .catch(function(){ onInterrupt(); });
    }
};
// ---- abShowUpdateToast: 轻量级提示（主要用于配置页手动检查反馈）----
window.abShowUpdateToast=function(type,msg){
    var old=document.getElementById("ab-update-notify"); if(old) old.remove();
    var colors={ok:"#059669",update:"#d97706",error:"#dc2626",info:"#6366f1"};
    var toast=document.createElement("div");
    toast.id="ab-update-notify";
    var banner=document.getElementById("ab-header-banner");
    if(banner){
        toast.style.cssText="margin:12px 0 0;padding:9px 14px;background:#fff;border:1px solid "+(colors[type]||"#6366f1")+";border-radius:12px;font-size:13px;color:"+(colors[type]||"#6366f1")+";display:flex;align-items:center;gap:8px;animation:ab-fadeIn .3s ease;font-weight:500;box-shadow:0 2px 8px rgba(0,0,0,.1)";
        toast.innerHTML="<span>"+msg+"</span>";
        banner.appendChild(toast);
    } else {
        toast.style.cssText="position:fixed;top:0;left:0;right:0;z-index:99999;padding:10px 20px;background:"+(colors[type]||"#6366f1")+";color:#fff;font-size:13px;text-align:center;animation:ab-slideDown .3s ease;font-weight:500";
        toast.innerHTML=msg;
        document.body.insertBefore(toast,document.body.firstChild);
    }
    setTimeout(function(){ if(toast.parentNode) toast.remove(); },6000);
};
// ---- 注入动画样式（如未注入）----
if(!document.getElementById("ab-update-anim")){
    var st=document.createElement("style");
    st.id="ab-update-anim";
    st.textContent="@keyframes ab-spin{to{transform:rotate(360deg)}}@keyframes ab-slideDown{from{transform:translateY(-100%);opacity:0}to{transform:translateY(0);opacity:1}}";
    document.head.appendChild(st);
}
// ---- abVerCompare: 返回 1(a>b) / -1(a<b) / 0(a===b) ----
function abVerCompare(a,b){
    var pa=(a||"").replace(/^v/i,"").split(".").map(Number);
    var pb=(b||"").replace(/^v/i,"").split(".").map(Number);
    for(var i=0;i<Math.max(pa.length,pb.length);i++){
        var na=pa[i]||0, nb=pb[i]||0;
        if(na>nb) return 1;
        if(na<nb) return -1;
    }
    return 0;
}
// ---- 自动检查（每小时一次，页面加载后 4 秒执行）----
setTimeout(function(){
    try{
        var cached=JSON.parse(localStorage.getItem("ab-update-check")||"null");
        var ONE_HOUR=3600000;
        // 若缓存中记录的"最新版"不高于当前运行版本，说明已更新完成但缓存未清除，直接丢弃
        if(cached&&cached.data&&cached.data.latest&&abVerCompare(cached.data.latest,__AB_VER__)<=0){
            try{ localStorage.removeItem("ab-update-check"); }catch(e){}
            cached=null;
        }
        if(!cached||(Date.now()-cached.ts)>ONE_HOUR){
            // 缓存已过期或不存在，发起网络请求
            window.abCheckUpdate(false);
        } else if(cached.data&&cached.data.has_update){
            // 缓存中有更新信息，检查是否已忽略
            var dismissed=localStorage.getItem("ab-update-dismissed-v"+cached.data.latest);
            if(!dismissed) window.abShowUpdateAvailable(cached.data);
        }
    }catch(e){}
},4000);
UPDATEJS;
        echo '})();</script>';
        echo '<script>(function(){'
            . '"use strict";'
            . 'function abIsMobile(){return window.innerWidth<=575;}'
            . 'document.addEventListener("click",function(e){'
            .   'if(!abIsMobile())return;'
            .   'var mb=document.querySelector(".typecho-head-nav .menu-bar[open]");'
            .   'if(!mb)return;'
            .   'var dr=document.querySelector(".typecho-head-nav nav > menu");'
            .   'if(mb.contains(e.target))return;'
            .   'if(dr&&dr.contains(e.target))return;'
            .   'mb.removeAttribute("open");'
            . '},true);'
            . 'function abInitMobileNav(){'
            .   'if(!abIsMobile())return;'
            .   'var drawerMenu=document.querySelector(".typecho-head-nav nav > menu");'
            .   'if(drawerMenu&&!document.getElementById("ab-mobile-branding")){'
            .     'var user=window.__AB_USER__||{};'
            .     'var cfg=window.__AB_CONFIG__||{};'
            .     'var siteName=cfg.siteName||"";'
            .     'var avatarUrl=user.avatar||"";'
            .     'var bd=document.createElement("div");'
            .     'bd.id="ab-mobile-branding";'
            .     'var bhtml="";'
            .     'if(avatarUrl)bhtml+="<img src=\""+avatarUrl+"\" alt=\"\" style=\"width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0\">";'
            .     'if(siteName)bhtml+="<span style=\"font-size:15px;font-weight:600;color:var(--md-on-surface);white-space:nowrap;overflow:hidden;text-overflow:ellipsis\">"+siteName+"</span>";'
            .     'bd.innerHTML=bhtml;'
            .     'drawerMenu.insertBefore(bd,drawerMenu.firstChild);'
            .   '}'
            .   'var items=document.querySelectorAll(".typecho-head-nav nav > menu > li");'
            .   '[].forEach.call(items,function(li){'
            .     'var sub=li.querySelector("menu");'
            .     'if(!sub)return;'
            .     '/* 标记父级菜单项：避免被 AJAX 导航捕获拦截 */'
            .     'li.classList.add("ab-has-children");'
            .     'if(li.classList.contains("focus"))li.classList.add("ab-expanded");'
            .   '});'
            . '}'
            . 'if(document.readyState==="loading"){'
            .   'document.addEventListener("DOMContentLoaded",abInitMobileNav);'
            . '}else{abInitMobileNav();}'
            . 'var abResizeTimer;'
            . 'window.addEventListener("resize",function(){'
            .   'clearTimeout(abResizeTimer);'
            .   'abResizeTimer=setTimeout(function(){'
            .     'var bd=document.getElementById("ab-mobile-branding");'
            .     'if(!abIsMobile()&&bd){'
            .       'bd.parentNode&&bd.parentNode.removeChild(bd);'
            .     '} else if(abIsMobile()&&!document.getElementById("ab-mobile-branding")){'
            .       'abInitMobileNav();'
            .     '}'
            .   '},100);'
            . '});'
            . '}());</script>';
        $typechoVer = isset($options->version) ? (string) $options->version : '';
        if ($typechoVer && version_compare($typechoVer, '1.3.0', '<')) {
            $noteMsg = '检测到 Typecho 版本：v' . htmlspecialchars($typechoVer) . '，建议升级到 v1.3.0 以获得最佳兼容性。';
            if (version_compare($typechoVer, '1.2.1', '==')) {
                $noteMsg .= ' 如果暂时无法升级，请在 AB-Admin 插件设置中手动启用 Typecho 1.2.1 兼容脚本。';
            }
            echo '<script>(function(){try{var b=document.createElement("div");b.id="ab-typecho-version-note";b.style.cssText="position:fixed;top:0;left:0;right:0;z-index:99998;padding:10px 14px;background:#fef3c7;color:#92400e;font-size:13px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 12px rgba(0,0,0,.06);";b.innerHTML=' . json_encode($noteMsg) . ';document.body.insertBefore(b,document.body.firstChild);document.body.style.paddingTop=(parseInt(document.body.style.paddingTop||0)+48)+"px";setTimeout(function(){b.style.transform="translateY(0)";},10);}catch(e){} })();</script>';
        }
        $absDir = rtrim(dirname(__FILE__), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'AdminBeautifyStore';
        $absInstalled = is_dir($absDir);
        $absActivated = false;
        if ($absInstalled) {
            try {
                $options->plugin('AdminBeautifyStore');
                $absActivated = true;
            } catch (Exception $e) {
                $absActivated = false;
            }
        }
        if (!$absInstalled) {
            $absStoreUrl  = '';
            $absBtnText   = '安装AB插件仓库';
            $absTarget    = '';
            $absWarnText  = '';
            $absWarnUrl   = '';
            $absAction    = 'install';
        } elseif (!$absActivated) {
            $absStoreUrl  = $options->adminUrl . 'plugins.php';
            $absBtnText   = 'AB插件仓库';
            $absTarget    = '_self';
            $absWarnText  = '插件未启用，请先启用 AB-Store';
            $absWarnUrl   = $options->adminUrl . 'plugins.php';
            $absAction    = 'go';
        } else {
            $absStoreUrl  = $options->adminUrl . 'extending.php?panel=' . urlencode('AdminBeautifyStore/Panel.php');
            $absBtnText   = 'AB插件仓库';
            $absTarget    = '_self';
            $absWarnText  = '';
            $absWarnUrl   = '';
            $absAction    = 'go';
        }
        echo '<script>(function(){'
            . 'var BTN_ID="ab-store-shortcut-btn";'
            . 'var WARN_ID="ab-store-shortcut-warn";'
            . 'var BTN_URL=' . json_encode($absStoreUrl) . ';'
            . 'var BTN_TEXT=' . json_encode($absBtnText) . ';'
            . 'var BTN_TARGET=' . json_encode($absTarget) . ';'
            . 'var WARN_TEXT=' . json_encode($absWarnText) . ';'
            . 'var BTN_ACTION=' . json_encode($absAction) . ';'
            . 'function abInjectStoreBtn(){'
            .   'if(document.getElementById(BTN_ID)) return;'
            .   'var titleArea=document.querySelector(".typecho-page-title");'
            .   'if(!titleArea) return;'
            .   'titleArea.style.display="flex";'
            .   'titleArea.style.alignItems="center";'
            .   'titleArea.style.flexWrap="wrap";'
            .   'titleArea.style.gap="8px";'
            .   'var h2=titleArea.querySelector("h2,h3");'
            .   'if(h2) h2.style.flex="1 1 auto";'
            .   'if(WARN_TEXT&&!document.getElementById(WARN_ID)){'
            .     'var warn=document.createElement("span");'
            .     'warn.id=WARN_ID;'
            .     'warn.textContent=WARN_TEXT;'
            .     'warn.style.cssText="font-size:12px;color:var(--md-error,#b3261e,#b3261e);font-weight:500;white-space:nowrap;";'
            .     'titleArea.appendChild(warn);'
            .   '}'
            .   'var btn;'
            .   'if(BTN_ACTION==="install"){'
            .     'btn=document.createElement("button");'
            .     'btn.type="button";'
            .     'btn.onclick=function(){'
            .       'btn.disabled=true;'
            .       'btn.textContent="安装中...";'
            .       'var ajax=window.__AB_AJAX__||{};'
            .       'if(!ajax.url){btn.textContent="❌ 获取接口地址失败";btn.disabled=false;return;}'
            .       'var xhr=new XMLHttpRequest();'
            .       'xhr.open("POST",ajax.url+"?do=install-abs",true);'
            .       'xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded");'
            .       'xhr.setRequestHeader("X-Requested-With","XMLHttpRequest");'
            .       'xhr.onload=function(){'
            .         'try{'
            .           'var res=JSON.parse(xhr.responseText);'
            .           'if(res.code===0){'
            .             'btn.textContent="✅ 安装成功，刷新中...";'
            .             'setTimeout(function(){location.reload();},1500);'
            .           '}else{'
            .             'btn.textContent="❌ "+(res.message||"安装失败");'
            .             'btn.disabled=false;'
            .           '}'
            .         '}catch(e){'
            .           'btn.textContent="❌ 安装失败";'
            .           'btn.disabled=false;'
            .         '}'
            .       '};'
            .       'xhr.onerror=function(){btn.textContent="❌ 网络错误";btn.disabled=false;};'
            .       'xhr.send("_="+encodeURIComponent(ajax.token||""));'
            .     '};'
            .   '}else{'
            .     'btn=document.createElement("a");'
            .     'btn.href=BTN_URL;'
            .     'btn.target=BTN_TARGET;'
            .     'btn.rel="noopener";'
            .   '}'
            .   'btn.id=BTN_ID;'
            .   'btn.innerHTML=\'<span class="material-icons-round" style="font-size:18px;margin-right:6px;vertical-align:-3px">store</span>\'+BTN_TEXT;'
            .   'btn.className="ab-store-shortcut-md3-btn";'
            .   'btn.style.cssText='
            .     '"display:inline-flex;align-items:center;margin-left:auto;flex-shrink:0;padding:8px 16px 8px 12px;'
            .     'border-radius:20px;border:none;cursor:pointer;font-size:14px;font-weight:600;font-family:inherit;'
            .     'background:var(--md-secondary-container,#e8def8);color:var(--md-on-secondary-container,#1d192b);'
            .     'box-shadow:0 1px 3px rgba(0,0,0,.12);transition:box-shadow .2s,background .2s;line-height:1;white-space:nowrap;'
            .     'text-decoration:none;";'
            .   'btn.addEventListener("mouseover",function(){this.style.boxShadow="0 3px 10px rgba(0,0,0,.18)";});'
            .   'btn.addEventListener("mouseout",function(){this.style.boxShadow="0 1px 3px rgba(0,0,0,.12)";});'
            .   'titleArea.appendChild(btn);'
            . '}'
            . 'function abRemoveStoreBtn(){'
            .   'var old=document.getElementById(BTN_ID);'
            .   'if(old)old.parentNode.removeChild(old);'
            .   'var ow=document.getElementById(WARN_ID);'
            .   'if(ow)ow.parentNode.removeChild(ow);'
            . '}'
            . 'function abCheckAndInject(url){'
            .   'if((url||window.location.href).indexOf("plugins.php")!==-1){abInjectStoreBtn();}'
            .   'else{abRemoveStoreBtn();}'
            . '}'
            . 'if(document.readyState==="loading"){'
            .   'document.addEventListener("DOMContentLoaded",function(){abCheckAndInject();});'
            . '}else{abCheckAndInject();}'
            . 'document.addEventListener("ab:pageload",function(e){'
            .   'var url=(e&&e.detail&&e.detail.url)?e.detail.url:window.location.href;'
            .   'abCheckAndInject(url);'
            . '});'
            . '})();</script>';
        echo '<script>(function(){
setTimeout(function(){
    var LS_KEY="ab-compat-auto-sync-ts";
    var ONE_DAY=86400000;
    var last=0;
    try{last=parseInt(localStorage.getItem(LS_KEY)||"0",10)||0;}catch(e){}
    if(Date.now()-last<ONE_DAY)return;
    var ajax=window.__AB_AJAX__||{};
    if(!ajax.url)return;
    var xhr=new XMLHttpRequest();
    xhr.open("POST",ajax.url+"?do=sync-compat",true);
    xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
    xhr.setRequestHeader("X-Requested-With","XMLHttpRequest");
    xhr.timeout=60000;
    xhr.onload=function(){
        try{
            var res=JSON.parse(xhr.responseText);
            if(res.code===0){
                try{localStorage.setItem(LS_KEY,Date.now().toString());}catch(e){}
                var s=(res.data||{}).summary||{};
                var n=(s.added||0)+(s.updated||0);
                if(n>0)abCompatAutoSyncToast(n);
            }
        }catch(e){}
    };
    xhr.onerror=xhr.ontimeout=function(){};
    xhr.send("_="+encodeURIComponent(ajax.token||""));
},10000);
function abCompatAutoSyncToast(n){
    if(document.getElementById("ab-compat-autosync-toast"))return;
    var t=document.createElement("div");
    t.id="ab-compat-autosync-toast";
    t.style.cssText="position:fixed;bottom:24px;right:24px;z-index:9999;background:var(--md-surface,#fff);border:1px solid var(--md-outline-variant,#cac4d0);border-radius:16px;padding:14px 18px;font-size:13px;color:var(--md-on-surface,#1c1b1f);box-shadow:0 4px 20px rgba(0,0,0,.14);display:flex;align-items:center;gap:10px;animation:ab-fade-in .3s ease;max-width:320px";
    t.innerHTML="<span class=\"material-icons-round\" style=\"font-size:20px;color:var(--md-primary,#6750a4)\">update</span>"
        +"<span>兼容脚本自动更新了 <strong>"+n+"</strong> 个，请刷新页面生效。</span>";
    var cls=document.createElement("button");
    cls.textContent="\u00d7";
    cls.style.cssText="border:none;background:transparent;cursor:pointer;font-size:16px;color:var(--md-on-surface-variant,#49454f);opacity:.6;flex-shrink:0;padding:0";
    cls.onclick=function(){t.parentNode&&t.parentNode.removeChild(t);};
    t.appendChild(cls);
    document.body.appendChild(t);
    setTimeout(function(){if(t.parentNode)t.parentNode.removeChild(t);},8000);
}
})();</script>';
        echo '<script>';
        echo 'window.__AB_TS_URL__='  . json_encode(Typecho_Common::url('/action/admin-beautify', $options->index)) . ';';
        echo 'window.__AB_TS_TOKEN__=' . json_encode($token) . ';';
        echo '</script>';
        self::loadCompatScripts($options, $pluginOptions);
    }
    private static function loadCompatScripts($options, $pluginOptions)
    {
        $compatBaseUrl = Typecho_Common::url('AdminBeautify/assets/compat/', $options->pluginUrl);
        $compatDir = dirname(__FILE__) . '/assets/compat/';
        $enabledRaw = isset($pluginOptions->compat_disabledScripts) ? (string) $pluginOptions->compat_disabledScripts : '';
        $enabledList = ($enabledRaw !== '') ? (array) json_decode($enabledRaw, true) : array();
        if (!is_array($enabledList)) $enabledList = array();
        if (is_dir($compatDir) && !empty($enabledList)) {
            foreach ($enabledList as $file) {
                if (substr($file, -3) === '.js' && is_file($compatDir . $file)) {
                    $fileUrl = $compatBaseUrl . $file;
                    $fileMtime = filemtime($compatDir . $file);
                    echo '<script src="' . htmlspecialchars($fileUrl) . '?v=' . $fileMtime . '"></script>' . "\n";
                }
            }
        }
        $externalJs = isset($pluginOptions->compat_externalJs) ? trim((string) $pluginOptions->compat_externalJs) : '';
        if ($externalJs !== '') {
            $lines = preg_split('/[\r\n]+/', $externalJs);
            foreach ($lines as $line) {
                $url = trim($line);
                if ($url !== '' && (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0 || strpos($url, '//') === 0)) {
                    echo '<script src="' . htmlspecialchars($url) . '"></script>' . "\n";
                }
            }
        }
    }
    private static function scanCompatScripts($compatDir)
    {
        $result = array();
        if (!is_dir($compatDir)) return $result;
        $files = scandir($compatDir);
        if (!$files) return $result;
        foreach ($files as $file) {
            if (substr($file, -3) !== '.js' || !is_file($compatDir . $file)) continue;
            $meta = self::parseCompatMeta($compatDir . $file);
            $meta['file'] = $file;
            $result[] = $meta;
        }
        return $result;
    }
    private static function parseCompatMeta($filePath)
    {
        $defaults = array(
            'name'        => '',
            'description' => '',
            'plugins'     => '',
            'version'     => '',
            'author'      => '',
        );
        $content = @file_get_contents($filePath, false, null, 0, 2048);
        if ($content === false) return $defaults;
        if (!preg_match('/\/\*\*(.*?)\*\//s', $content, $blockMatch)) {
            return $defaults;
        }
        $block = $blockMatch[1];
        $tags = array('name', 'description', 'plugins', 'version', 'author');
        foreach ($tags as $tag) {
            if (preg_match('/@' . $tag . '\s+(.+)/i', $block, $m)) {
                $defaults[$tag] = trim($m[1]);
            }
        }
        if ($defaults['name'] === '') {
            $defaults['name'] = basename($filePath, '.js');
        }
        return $defaults;
    }
    private static function renderCompatScriptsList($scripts, $enabledList, $accentColor)
    {
        if (empty($scripts)) {
            echo '<div id="ab-compat-scripts-list" class="ab-compat-empty" style="margin:12px 0 20px;padding:16px;background:#fefce8;border:1px solid #fde68a;border-radius:10px;font-size:13px;color:#854d0e">
                <span style="margin-right:6px">📂</span> <code>assets/compat/</code> 目录中未找到兼容脚本。
            </div>';
            return;
        }
        echo '<div id="ab-compat-scripts-list" style="margin:12px 0 20px">';
        echo '<div class="ab-compat-list-title" style="font-size:14px;font-weight:600;color:#374151;margin-bottom:10px;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
            <span style="font-size:16px">📋</span>
            <span style="flex:1">本地兼容脚本（共 ' . count($scripts) . ' 个）</span>
            <button id="ab-compat-sync-btn" type="button" onclick="abSyncCompat()" style="'
                . 'display:inline-flex;align-items:center;gap:6px;padding:6px 14px;'
                . 'background:' . $accentColor . ';color:#fff;border:none;border-radius:20px;'
                . 'font-size:12px;font-weight:500;cursor:pointer;transition:opacity .2s;'
                . 'box-shadow:0 1px 4px rgba(0,0,0,.15);flex-shrink:0'
                . '" onmouseover="this.style.opacity=\'.85\'" onmouseout="this.style.opacity=\'1\'">'
                . '<span id="ab-compat-sync-icon">☁️</span><span id="ab-compat-sync-label">从 GitHub 同步</span>'
            . '</button>
        </div>
        <div id="ab-compat-sync-result" style="display:none;margin-bottom:12px"></div>';
        foreach ($scripts as $s) {
            $file = htmlspecialchars($s['file']);
            $name = htmlspecialchars($s['name']);
            $desc = htmlspecialchars($s['description']);
            $plugins = htmlspecialchars($s['plugins']);
            $version = htmlspecialchars($s['version']);
            $author = htmlspecialchars($s['author']);
            $isEnabled = in_array($s['file'], $enabledList);
            $toggleId = 'ab-compat-toggle-' . md5($s['file']);
            echo '<div class="ab-compat-script-item" data-file="' . $file . '" style="'
                . 'display:flex;align-items:flex-start;gap:14px;padding:14px 18px;margin-bottom:8px;'
                . 'background:' . ($isEnabled ? '#fff' : '#f9fafb') . ';'
                . 'border:1px solid ' . ($isEnabled ? $accentColor . '44' : '#e5e7eb') . ';'
                . 'border-radius:12px;transition:all .2s;'
                . ($isEnabled ? '' : 'opacity:.6;')
                . '">';
            echo '<div style="flex-shrink:0;padding-top:2px">'
                . '<label style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer">'
                . '<input type="checkbox" class="ab-compat-checkbox" data-file="' . $file . '" '
                . ($isEnabled ? 'checked' : '') . ' '
                . 'style="opacity:0;width:0;height:0;position:absolute">'
                . '<span style="'
                . 'position:absolute;top:0;left:0;right:0;bottom:0;'
                . 'background:' . ($isEnabled ? $accentColor : '#d1d5db') . ';'
                . 'border-radius:12px;transition:background .2s;'
                . '"></span>'
                . '<span style="'
                . 'position:absolute;top:2px;left:' . ($isEnabled ? '22px' : '2px') . ';'
                . 'width:20px;height:20px;background:#fff;border-radius:50%;'
                . 'transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);'
                . '"></span>'
                . '</label>'
                . '</div>';
            echo '<div style="flex:1;min-width:0">';
            echo '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">';
            echo '<span class="ab-compat-name" style="font-size:14px;font-weight:600;color:#111827">' . $name . '</span>';
            if ($version) {
                echo '<span class="ab-compat-meta" style="font-size:11px;color:#6b7280;background:#f3f4f6;padding:1px 8px;border-radius:8px">v' . $version . '</span>';
            }
            if ($author) {
                echo '<span class="ab-compat-meta" style="font-size:11px;color:#6b7280">by ' . $author . '</span>';
            }
            echo '</div>';
            if ($desc) {
                echo '<div class="ab-compat-desc" style="font-size:13px;color:#4b5563;line-height:1.5;margin-bottom:4px">' . $desc . '</div>';
            }
            if ($plugins) {
                $pluginArr = array_map('trim', explode(',', $plugins));
                echo '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">';
                foreach ($pluginArr as $p) {
                    echo '<span style="font-size:11px;color:' . $accentColor . ';background:' . $accentColor . '15;padding:2px 10px;border-radius:8px;border:1px solid ' . $accentColor . '33;font-weight:500">'
                        . htmlspecialchars($p) . '</span>';
                }
                echo '</div>';
            }
            echo '<div class="ab-compat-meta" style="font-size:11px;color:#9ca3af;margin-top:4px">📄 ' . $file . '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '<script>
(function(){
    function updateEnabledList(){
        var items = document.querySelectorAll(".ab-compat-checkbox");
        var enabled = [];
        for(var i=0;i<items.length;i++){
            if(items[i].checked){
                enabled.push(items[i].getAttribute("data-file"));
            }
        }
        var hidden = document.querySelector("[name=compat_disabledScripts]");
        if(hidden) hidden.value = JSON.stringify(enabled);
    }
    function initToggles(){
        var checkboxes = document.querySelectorAll(".ab-compat-checkbox");
        for(var i=0;i<checkboxes.length;i++){
            checkboxes[i].addEventListener("change", function(){
                var item = this.closest(".ab-compat-script-item");
                var track = this.nextElementSibling;
                var thumb = track ? track.nextElementSibling : null;
                if(this.checked){
                    if(item){item.style.opacity="1";item.style.background="#fff";}
                    if(track) track.style.background="' . $accentColor . '";
                    if(thumb) thumb.style.left="22px";
                }else{
                    if(item){item.style.opacity=".6";item.style.background="#f9fafb";}
                    if(track) track.style.background="#d1d5db";
                    if(thumb) thumb.style.left="2px";
                }
                updateEnabledList();
            });
        }
    }
    if(document.readyState==="loading"){
        document.addEventListener("DOMContentLoaded",initToggles);
    }else{
        initToggles();
    }
})();
// ---- 从 GitHub 同步兼容脚本 ----
window.abSyncCompat = function(){
    var btn = document.getElementById("ab-compat-sync-btn");
    var icon = document.getElementById("ab-compat-sync-icon");
    var label = document.getElementById("ab-compat-sync-label");
    var resultBox = document.getElementById("ab-compat-sync-result");
    if(!btn) return;
    btn.disabled = true;
    btn.style.opacity = ".6";
    if(icon) icon.textContent = "⏳";
    if(label) label.textContent = "同步中...";
    if(resultBox){ resultBox.style.display="none"; resultBox.innerHTML=""; }
    var ajax = window.__AB_AJAX__ || {};
    var url = (ajax.url || "") + "?do=sync-compat";
    var token = ajax.token || "";
    var xhr = new XMLHttpRequest();
    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.timeout = 60000;
    xhr.onload = function(){
        btn.disabled = false; btn.style.opacity = "1";
        if(icon) icon.textContent = "☁️";
        if(label) label.textContent = "从 GitHub 同步";
        try{
            var res = JSON.parse(xhr.responseText);
            if(res.code === 0){
                var d = res.data || {};
                var summary = d.summary || {};
                var results = d.results || [];
                var html = "<div style=\"padding:12px 16px;border-radius:12px;font-size:13px;line-height:1.8;"
                    + "background:#f0fdf4;border:1px solid #bbf7d0;color:#166534\">"
                    + "<div style=\"font-weight:600;margin-bottom:6px\">✅ " + res.message + "</div>"
                    + "<div style=\"display:flex;gap:16px;flex-wrap:wrap;margin-bottom:8px\">"
                    + "<span>❗ 新增 <strong>" + (summary.added||0) + "</strong></span>"
                    + "<span>☁️ 更新 <strong>" + (summary.updated||0) + "</strong></span>"
                    + "<span>✔️ 跳过 <strong>" + (summary.skipped||0) + "</strong></span>"
                    + (summary.errors > 0 ? "<span style=\"color:#dc2626\">❌ 失败 <strong>" + summary.errors + "</strong></span>" : "")
                    + "</div>";
                var changed = [];
                for(var i=0;i<results.length;i++){
                    var r = results[i];
                    if(r.status === "added" || r.status === "updated" || r.status === "error"){
                        var icon2 = r.status==="added"?"❗":r.status==="updated"?"☁️":"❌";
                        changed.push("<span style=\"margin-right:12px\">" + icon2 + " " + r.file
                            + (r.msg ? " <span style=\"color:#6b7280;font-size:12px\">(" + r.msg + ")</span>" : "")
                            + "</span>");
                    }
                }
                if(changed.length > 0){
                    html += "<div style=\"flex-wrap:wrap;display:flex\">" + changed.join("") + "</div>";
                }
                if((summary.added||0) + (summary.updated||0) > 0){
                    html += "<div style=\"margin-top:8px;font-size:12px;color:#166534\">💡 有脚本已更新，请刷新页面以查看最新列表。</div>";
                }
                html += "</div>";
                if(resultBox){ resultBox.innerHTML=html; resultBox.style.display="block"; }
            } else {
                var errHtml = "<div style=\"padding:12px 16px;border-radius:12px;font-size:13px;background:#fef2f2;border:1px solid #fecaca;color:#dc2626\">"
                    + "❌ " + (res.message || "同步失败") + "</div>";
                if(resultBox){ resultBox.innerHTML=errHtml; resultBox.style.display="block"; }
            }
        } catch(e){
            if(resultBox){ resultBox.innerHTML="<div style=\"padding:12px;border-radius:10px;background:#fef2f2;color:#dc2626;font-size:13px\">❌ 响应解析失败</div>"; resultBox.style.display="block"; }
        }
    };
    xhr.onerror = xhr.ontimeout = function(){
        btn.disabled = false; btn.style.opacity = "1";
        if(icon) icon.textContent = "☁️";
        if(label) label.textContent = "从 GitHub 同步";
        if(resultBox){ resultBox.innerHTML="<div style=\"padding:12px;border-radius:10px;background:#fef2f2;color:#dc2626;font-size:13px\">❌ 请求超时或网络错误，请检查服务器是否能访问 GitHub</div>"; resultBox.style.display="block"; }
    };
    xhr.send("_=" + encodeURIComponent(token));
};
</script>';
    }
    public static function renderLoginFooter()
    {
        if (!self::isLoginPage()) {
            return;
        }
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginOptions = $options->plugin('AdminBeautify');
        $loginIsEnabled = isset($pluginOptions->login_isEnabled) ? (string)$pluginOptions->login_isEnabled : '1';
        if ($loginIsEnabled !== '1') {
            return;
        }
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginOptions = $options->plugin('AdminBeautify');
        $showSiteName = ((string) $pluginOptions->login_showSiteName !== '0');
        $showThemeToggle = ((string) $pluginOptions->login_showThemeToggle !== '0');
        $customJs = (string) $pluginOptions->login_customJs;
        $siteTitle = (string) $options->title;
        $jsSiteTitle = self::jsString($siteTitle);
        $jsShowSiteName = $showSiteName ? 'true' : 'false';
        $jsShowToggle = $showThemeToggle ? 'true' : 'false';
        include dirname(__FILE__) . '/assets/pages/login/script.php';
    }
    private static function outputLoginHeaderCss()
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginOptions = $options->plugin('AdminBeautify');
        $loginIsEnabled = isset($pluginOptions->login_isEnabled) ? (string)$pluginOptions->login_isEnabled : '1';
        if ($loginIsEnabled !== '1') {
            return;
        }
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginOptions = $options->plugin('AdminBeautify');
        $themeMode = isset($pluginOptions->login_themeMode) ? (string) $pluginOptions->login_themeMode : 'auto';
        if (!in_array($themeMode, array('auto', 'light', 'dark'), true)) {
            $themeMode = 'auto';
        }
        $bgImage = trim((string) $pluginOptions->login_bgImage);
        $blurType = in_array($pluginOptions->login_blurType, array('none', 'filter', 'backdrop'), true) ? $pluginOptions->login_blurType : 'filter';
        $blurSize = (int) $pluginOptions->login_blurSize;
        if ($blurSize < 0) $blurSize = 0;
        if ($blurSize > 80) $blurSize = 80;
        $customCss = (string) $pluginOptions->login_customCss;
        $preset = isset($pluginOptions->login_colorPreset) ? (string) $pluginOptions->login_colorPreset : 'purple';
        $colorPresets = array(
            'purple'   => array('#7d5260', '#9e7b8a'),
            'blue'     => array('#556270', '#7a8a9e'),
            'pink'     => array('#74565f', '#9e7a85'),
            'green'    => array('#55624c', '#7a8a6e'),
            'orange'   => array('#725a42', '#9e8062'),
            'red'      => array('#775654', '#a27a78'),
            'teal'     => array('#4a6363', '#6a8a8a'),
            'indigo'   => array('#5a4fd9', '#7b6ef2'),
            'sunset'   => array('#d38d1a', '#e06b3a'),
            'ocean'    => array('#0da0d8', '#39c1dd'),
            'forest'   => array('#2f7a3b', '#7fbf3a'),
            'lavender' => array('#8f6ee8', '#b89cfb'),
        );
        if ($preset === 'custom') {
            $primary = isset($pluginOptions->login_primaryColor) && trim((string) $pluginOptions->login_primaryColor) !== '' ? trim((string) $pluginOptions->login_primaryColor) : '#7d5260';
            $primary2 = isset($pluginOptions->login_primaryColor2) && trim((string) $pluginOptions->login_primaryColor2) !== '' ? trim((string) $pluginOptions->login_primaryColor2) : '#9e7b8a';
        } else {
            $colors = isset($colorPresets[$preset]) ? $colorPresets[$preset] : $colorPresets['purple'];
            $primary = $colors[0];
            $primary2 = $colors[1];
        }
        $bgCss = $bgImage !== '' ? "url(" . htmlspecialchars($bgImage, ENT_QUOTES, 'UTF-8') . ")" : "none";
        $jsThemeMode = self::jsString($themeMode);
        include dirname(__FILE__) . '/assets/pages/login/style.php';
    }
    private static function renderLoginPreview()
    {
        try {
            $opt = Typecho_Widget::widget('Widget_Options')->plugin('AdminBeautify');
            $preset = isset($opt->login_colorPreset) ? (string) $opt->login_colorPreset : 'purple';
            $pc1 = isset($opt->login_primaryColor) ? (string) $opt->login_primaryColor : '#7d5260';
            $pc2 = isset($opt->login_primaryColor2) ? (string) $opt->login_primaryColor2 : '#9e7b8a';
            $bgUrl = isset($opt->login_bgImage) ? (string) $opt->login_bgImage : '';
            $blurTypeVal = isset($opt->login_blurType) ? (string) $opt->login_blurType : 'filter';
            $blurSizeVal = isset($opt->login_blurSize) ? (int) $opt->login_blurSize : 12;
        } catch (Exception $e) {
            $preset = 'purple';
            $pc1 = '#7d5260';
            $pc2 = '#9e7b8a';
            $bgUrl = '';
            $blurTypeVal = 'filter';
            $blurSizeVal = 12;
        }
        include dirname(__FILE__) . '/assets/pages/login/preview.php';
    }
    private static function getColorScheme($scheme)
    {
        $schemes = array(
            'purple' => array(
                '--md-primary'           => '#7D5260',
                '--md-on-primary'        => '#FFFFFF',
                '--md-primary-container' => '#FFD8E4',
                '--md-on-primary-container' => '#21005D',
                '--md-secondary'         => '#625B71',
                '--md-on-secondary'      => '#FFFFFF',
                '--md-secondary-container' => '#E8DEF8',
                '--md-on-secondary-container' => '#1D192B',
                '--md-tertiary'          => '#7D5260',
                '--md-surface'           => '#FFFBFE',
                '--md-surface-dim'       => '#DED8E1',
                '--md-surface-bright'    => '#FFFBFE',
                '--md-surface-container-lowest'  => '#FFFFFF',
                '--md-surface-container-low'     => '#F7F2FA',
                '--md-surface-container'         => '#F3EDF7',
                '--md-surface-container-high'    => '#ECE6F0',
                '--md-surface-container-highest' => '#E6E0E9',
                '--md-on-surface'        => '#1C1B1F',
                '--md-on-surface-variant' => '#49454F',
                '--md-outline'           => '#79747E',
                '--md-outline-variant'   => '#CAC4D0',
                '--md-error'             => '#B3261E',
                '--md-on-error'          => '#FFFFFF',
                '--md-error-container'   => '#F9DEDC',
                '--md-on-error-container' => '#410E0B',
                '--md-dark-primary'           => '#D0BCFF',
                '--md-dark-on-primary'        => '#381E72',
                '--md-dark-primary-container' => '#4F378B',
                '--md-dark-on-primary-container' => '#EADDFF',
                '--md-dark-secondary'         => '#CCC2DC',
                '--md-dark-surface'           => '#1C1B1F',
                '--md-dark-surface-dim'       => '#1C1B1F',
                '--md-dark-surface-bright'    => '#3B383E',
                '--md-dark-surface-container-lowest'  => '#0F0D13',
                '--md-dark-surface-container-low'     => '#211F26',
                '--md-dark-surface-container'         => '#2B2930',
                '--md-dark-surface-container-high'    => '#36343B',
                '--md-dark-surface-container-highest' => '#484649',
                '--md-dark-on-surface'        => '#E6E1E5',
                '--md-dark-on-surface-variant' => '#CAC4D0',
                '--md-dark-outline'           => '#938F99',
                '--md-dark-outline-variant'   => '#49454F',
                '--md-dark-error'             => '#F2B8B5',
            ),
            'blue' => array(
                '--md-primary'           => '#556270',
                '--md-on-primary'        => '#FFFFFF',
                '--md-primary-container' => '#D9E2FF',
                '--md-on-primary-container' => '#001A41',
                '--md-secondary'         => '#565E71',
                '--md-on-secondary'      => '#FFFFFF',
                '--md-secondary-container' => '#DAE2F9',
                '--md-on-secondary-container' => '#131C2B',
                '--md-tertiary'          => '#715573',
                '--md-surface'           => '#FDFBFF',
                '--md-surface-dim'       => '#DBD9DD',
                '--md-surface-bright'    => '#FDFBFF',
                '--md-surface-container-lowest'  => '#FFFFFF',
                '--md-surface-container-low'     => '#F5F3F7',
                '--md-surface-container'         => '#EFEDF1',
                '--md-surface-container-high'    => '#EAE7EC',
                '--md-surface-container-highest' => '#E4E2E6',
                '--md-on-surface'        => '#1B1B1F',
                '--md-on-surface-variant' => '#44474F',
                '--md-outline'           => '#74777F',
                '--md-outline-variant'   => '#C4C6D0',
                '--md-error'             => '#BA1A1A',
                '--md-on-error'          => '#FFFFFF',
                '--md-error-container'   => '#FFDAD6',
                '--md-on-error-container' => '#410E0B',
                '--md-dark-primary'           => '#ADC6FF',
                '--md-dark-on-primary'        => '#002E69',
                '--md-dark-primary-container' => '#004494',
                '--md-dark-on-primary-container' => '#D8E2FF',
                '--md-dark-secondary'         => '#BEC6DC',
                '--md-dark-surface'           => '#1B1B1F',
                '--md-dark-surface-dim'       => '#1B1B1F',
                '--md-dark-surface-bright'    => '#3A3A3F',
                '--md-dark-surface-container-lowest'  => '#0E0E13',
                '--md-dark-surface-container-low'     => '#1F1F24',
                '--md-dark-surface-container'         => '#2A2A2F',
                '--md-dark-surface-container-high'    => '#35353A',
                '--md-dark-surface-container-highest' => '#464649',
                '--md-dark-on-surface'        => '#E4E2E6',
                '--md-dark-on-surface-variant' => '#C4C6D0',
                '--md-dark-outline'           => '#8E9099',
                '--md-dark-outline-variant'   => '#44474F',
                '--md-dark-error'             => '#FFB4AB',
            ),
            'teal' => array(
                '--md-primary'           => '#4A6363',
                '--md-on-primary'        => '#FFFFFF',
                '--md-primary-container' => '#CCE8E7',
                '--md-on-primary-container' => '#002020',
                '--md-secondary'         => '#4A6363',
                '--md-on-secondary'      => '#FFFFFF',
                '--md-secondary-container' => '#CCE8E7',
                '--md-on-secondary-container' => '#051F1F',
                '--md-tertiary'          => '#4B607C',
                '--md-surface'           => '#FAFDFC',
                '--md-surface-dim'       => '#DBE4E3',
                '--md-surface-bright'    => '#FAFDFC',
                '--md-surface-container-lowest'  => '#FFFFFF',
                '--md-surface-container-low'     => '#F2FAF9',
                '--md-surface-container'         => '#EDF5F3',
                '--md-surface-container-high'    => '#E7EFEE',
                '--md-surface-container-highest' => '#E1E9E8',
                '--md-on-surface'        => '#191C1C',
                '--md-on-surface-variant' => '#3F4948',
                '--md-outline'           => '#6F7979',
                '--md-outline-variant'   => '#BEC9C8',
                '--md-error'             => '#BA1A1A',
                '--md-on-error'          => '#FFFFFF',
                '--md-error-container'   => '#FFDAD6',
                '--md-on-error-container' => '#410E0B',
                '--md-dark-primary'           => '#4CDADA',
                '--md-dark-on-primary'        => '#003737',
                '--md-dark-primary-container' => '#004F50',
                '--md-dark-on-primary-container' => '#6FF7F6',
                '--md-dark-secondary'         => '#B0CCCB',
                '--md-dark-surface'           => '#191C1C',
                '--md-dark-surface-dim'       => '#191C1C',
                '--md-dark-surface-bright'    => '#3A3D3D',
                '--md-dark-surface-container-lowest'  => '#0C0F0F',
                '--md-dark-surface-container-low'     => '#1F2222',
                '--md-dark-surface-container'         => '#2A2D2D',
                '--md-dark-surface-container-high'    => '#353838',
                '--md-dark-surface-container-highest' => '#464949',
                '--md-dark-on-surface'        => '#E1E9E8',
                '--md-dark-on-surface-variant' => '#BEC9C8',
                '--md-dark-outline'           => '#899392',
                '--md-dark-outline-variant'   => '#3F4948',
                '--md-dark-error'             => '#FFB4AB',
            ),
            'green' => array(
                '--md-primary'           => '#55624C',
                '--md-on-primary'        => '#FFFFFF',
                '--md-primary-container' => '#D9E7CB',
                '--md-on-primary-container' => '#042100',
                '--md-secondary'         => '#55624C',
                '--md-on-secondary'      => '#FFFFFF',
                '--md-secondary-container' => '#D9E7CB',
                '--md-on-secondary-container' => '#131F0D',
                '--md-tertiary'          => '#386666',
                '--md-surface'           => '#FDFDF5',
                '--md-surface-dim'       => '#DBDBD4',
                '--md-surface-bright'    => '#FDFDF5',
                '--md-surface-container-lowest'  => '#FFFFFF',
                '--md-surface-container-low'     => '#F5F5ED',
                '--md-surface-container'         => '#F0F0E8',
                '--md-surface-container-high'    => '#EAEAE2',
                '--md-surface-container-highest' => '#E4E4DD',
                '--md-on-surface'        => '#1A1C18',
                '--md-on-surface-variant' => '#44483E',
                '--md-outline'           => '#74796D',
                '--md-outline-variant'   => '#C4C8BB',
                '--md-error'             => '#BA1A1A',
                '--md-on-error'          => '#FFFFFF',
                '--md-error-container'   => '#FFDAD6',
                '--md-on-error-container' => '#410E0B',
                '--md-dark-primary'           => '#9CD67D',
                '--md-dark-on-primary'        => '#0E3900',
                '--md-dark-primary-container' => '#215107',
                '--md-dark-on-primary-container' => '#B7F397',
                '--md-dark-secondary'         => '#BDC9B0',
                '--md-dark-surface'           => '#1A1C18',
                '--md-dark-surface-dim'       => '#1A1C18',
                '--md-dark-surface-bright'    => '#3A3D36',
                '--md-dark-surface-container-lowest'  => '#0D0F0B',
                '--md-dark-surface-container-low'     => '#1F2220',
                '--md-dark-surface-container'         => '#2A2D28',
                '--md-dark-surface-container-high'    => '#353833',
                '--md-dark-surface-container-highest' => '#464944',
                '--md-dark-on-surface'        => '#E4E4DD',
                '--md-dark-on-surface-variant' => '#C4C8BB',
                '--md-dark-outline'           => '#8E9285',
                '--md-dark-outline-variant'   => '#44483E',
                '--md-dark-error'             => '#FFB4AB',
            ),
            'orange' => array(
                '--md-primary'           => '#725A42',
                '--md-on-primary'        => '#FFFFFF',
                '--md-primary-container' => '#FFDCBE',
                '--md-on-primary-container' => '#2C1600',
                '--md-secondary'         => '#725A42',
                '--md-on-secondary'      => '#FFFFFF',
                '--md-secondary-container' => '#FDDDBF',
                '--md-on-secondary-container' => '#2A1706',
                '--md-tertiary'          => '#586339',
                '--md-surface'           => '#FFFBFF',
                '--md-surface-dim'       => '#E0D9D1',
                '--md-surface-bright'    => '#FFFBFF',
                '--md-surface-container-lowest'  => '#FFFFFF',
                '--md-surface-container-low'     => '#FAF3EB',
                '--md-surface-container'         => '#F4EDE5',
                '--md-surface-container-high'    => '#EEE8E0',
                '--md-surface-container-highest' => '#E8E2DA',
                '--md-on-surface'        => '#201B13',
                '--md-on-surface-variant' => '#51453A',
                '--md-outline'           => '#837568',
                '--md-outline-variant'   => '#D5C3B5',
                '--md-error'             => '#BA1A1A',
                '--md-on-error'          => '#FFFFFF',
                '--md-error-container'   => '#FFDAD6',
                '--md-on-error-container' => '#410E0B',
                '--md-dark-primary'           => '#FFB870',
                '--md-dark-on-primary'        => '#4A2800',
                '--md-dark-primary-container' => '#6A3C00',
                '--md-dark-on-primary-container' => '#FFDCBE',
                '--md-dark-secondary'         => '#DFBFA3',
                '--md-dark-surface'           => '#201B13',
                '--md-dark-surface-dim'       => '#201B13',
                '--md-dark-surface-bright'    => '#423A31',
                '--md-dark-surface-container-lowest'  => '#140F08',
                '--md-dark-surface-container-low'     => '#29231B',
                '--md-dark-surface-container'         => '#332D25',
                '--md-dark-surface-container-high'    => '#3E3830',
                '--md-dark-surface-container-highest' => '#4A443B',
                '--md-dark-on-surface'        => '#ECE0D4',
                '--md-dark-on-surface-variant' => '#D5C3B5',
                '--md-dark-outline'           => '#9D8E81',
                '--md-dark-outline-variant'   => '#51453A',
                '--md-dark-error'             => '#FFB4AB',
            ),
            'pink' => array(
                '--md-primary'           => '#74565F',
                '--md-on-primary'        => '#FFFFFF',
                '--md-primary-container' => '#FFD9E3',
                '--md-on-primary-container' => '#3E001E',
                '--md-secondary'         => '#74565F',
                '--md-on-secondary'      => '#FFFFFF',
                '--md-secondary-container' => '#FFD9E3',
                '--md-on-secondary-container' => '#2B151C',
                '--md-tertiary'          => '#7C5635',
                '--md-surface'           => '#FFFBFF',
                '--md-surface-dim'       => '#E4D6DB',
                '--md-surface-bright'    => '#FFFBFF',
                '--md-surface-container-lowest'  => '#FFFFFF',
                '--md-surface-container-low'     => '#FEF0F4',
                '--md-surface-container'         => '#F9EAEF',
                '--md-surface-container-high'    => '#F3E5E9',
                '--md-surface-container-highest' => '#EDDFE4',
                '--md-on-surface'        => '#201A1C',
                '--md-on-surface-variant' => '#524347',
                '--md-outline'           => '#847377',
                '--md-outline-variant'   => '#D5C2C6',
                '--md-error'             => '#BA1A1A',
                '--md-on-error'          => '#FFFFFF',
                '--md-error-container'   => '#FFDAD6',
                '--md-on-error-container' => '#410E0B',
                '--md-dark-primary'           => '#FFB0CA',
                '--md-dark-on-primary'        => '#5E1133',
                '--md-dark-primary-container' => '#7C294A',
                '--md-dark-on-primary-container' => '#FFD9E3',
                '--md-dark-secondary'         => '#E2BDC7',
                '--md-dark-surface'           => '#201A1C',
                '--md-dark-surface-dim'       => '#201A1C',
                '--md-dark-surface-bright'    => '#3F383A',
                '--md-dark-surface-container-lowest'  => '#150F11',
                '--md-dark-surface-container-low'     => '#29222B',
                '--md-dark-surface-container'         => '#332C2F',
                '--md-dark-surface-container-high'    => '#3E373A',
                '--md-dark-surface-container-highest' => '#4A4345',
                '--md-dark-on-surface'        => '#EDDFE4',
                '--md-dark-on-surface-variant' => '#D5C2C6',
                '--md-dark-outline'           => '#9E8C90',
                '--md-dark-outline-variant'   => '#524347',
                '--md-dark-error'             => '#FFB4AB',
            ),
            'red' => array(
                '--md-primary'           => '#775654',
                '--md-on-primary'        => '#FFFFFF',
                '--md-primary-container' => '#FFDAD7',
                '--md-on-primary-container' => '#410005',
                '--md-secondary'         => '#775654',
                '--md-on-secondary'      => '#FFFFFF',
                '--md-secondary-container' => '#FFDAD7',
                '--md-on-secondary-container' => '#2C1514',
                '--md-tertiary'          => '#725B2E',
                '--md-surface'           => '#FFFBFF',
                '--md-surface-dim'       => '#E3D6D5',
                '--md-surface-bright'    => '#FFFBFF',
                '--md-surface-container-lowest'  => '#FFFFFF',
                '--md-surface-container-low'     => '#FDF0EF',
                '--md-surface-container'         => '#F8EAEA',
                '--md-surface-container-high'    => '#F2E4E3',
                '--md-surface-container-highest' => '#ECDEDE',
                '--md-on-surface'        => '#201A19',
                '--md-on-surface-variant' => '#534342',
                '--md-outline'           => '#857372',
                '--md-outline-variant'   => '#D8C2C0',
                '--md-error'             => '#BA1A1A',
                '--md-on-error'          => '#FFFFFF',
                '--md-error-container'   => '#FFDAD6',
                '--md-on-error-container' => '#410E0B',
                '--md-dark-primary'           => '#FFB3AD',
                '--md-dark-on-primary'        => '#68000E',
                '--md-dark-primary-container' => '#930016',
                '--md-dark-on-primary-container' => '#FFDAD7',
                '--md-dark-secondary'         => '#E7BDB9',
                '--md-dark-surface'           => '#201A19',
                '--md-dark-surface-dim'       => '#201A19',
                '--md-dark-surface-bright'    => '#413735',
                '--md-dark-surface-container-lowest'  => '#140E0D',
                '--md-dark-surface-container-low'     => '#291F1E',
                '--md-dark-surface-container'         => '#332928',
                '--md-dark-surface-container-high'    => '#3E3433',
                '--md-dark-surface-container-highest' => '#4A3F3E',
                '--md-dark-on-surface'        => '#ECDEDE',
                '--md-dark-on-surface-variant' => '#D8C2C0',
                '--md-dark-outline'           => '#A08C8B',
                '--md-dark-outline-variant'   => '#534342',
                '--md-dark-error'             => '#FFB4AB',
            ),
        );
        return isset($schemes[$scheme]) ? $schemes[$scheme] : $schemes['purple'];
    }
}
