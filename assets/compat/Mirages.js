/**
 * @name        Mirages 主题兼容
 * @description 为 Mirages 主题注入 Material Design 3 风格样式：主题设置页、文章编辑页
 *   1. 主题设置页（options-theme.php）：覆盖 dashboard.settings.min.css 中硬编码的浅色值
 *   2. 文章/页面编辑页（write-post.php / write-page.php）：
 *      - OwO 表情包弹窗 MD3 适配
 *      - Mirages 自定义工具栏按钮（FA 图标 → material-icons-round）
 *      - Mirages 对话框（#mirages-dialog）MD3 适配
 *
 * @plugins     Mirages
 * @version     1.0.1
 * @author      LHL
 */
(function () {
    'use strict';

    var STYLE_ID       = 'ab-compat-mirages';
    var STYLE_WRITE_ID = 'ab-compat-mirages-write';

    /* ══════════════════════════════════════════════════════════════
     * 一、主题设置页样式（options-theme.php）
     * ══════════════════════════════════════════════════════════════ */

    var CSS_SETTINGS = ''

        /* 1. .mirages 品牌头部 */
        + '.mirages {'
        + '  color: var(--md-on-surface) !important;'
        + '}'
        + '.mirages h1.logo {'
        + '  color: var(--md-on-surface) !important;'
        + '}'
        + '.mirages h1.logo .typecho {'
        + '  color: var(--md-on-surface-variant) !important;'
        + '}'
        + '.mirages .help-info a {'
        + '  color: var(--md-primary) !important;'
        + '}'
        + '.mirages .version {'
        + '  color: var(--md-on-surface-variant) !important;'
        + '}'

        /* 2. .collapse-block */
        + '.collapse-block {'
        + '  border-color: var(--md-outline-variant) !important;'
        + '  border-radius: var(--md-radius-lg, 16px) !important;'
        + '  overflow: hidden;'
        + '}'

        /* 3. .collapse-header */
        + '.collapse-block .collapse-header {'
        + '  background: var(--md-surface-container-low) !important;'
        + '  border-radius: 0 !important;'
        + '}'
        + '.collapse-block .collapse-header .title {'
        + '  color: var(--md-on-surface) !important;'
        + '}'

        /* 4. .collapse-content */
        + '.collapse-block .collapse-content {'
        + '  background: var(--md-surface-container-lowest) !important;'
        + '}'
        + '.collapse-block .collapse-content .description {'
        + '  color: var(--md-on-surface-variant) !important;'
        + '}'

        /* 5. 代码块 */
        + '.collapse-block .collapse-content code {'
        + '  background: var(--md-surface-container) !important;'
        + '  color: var(--md-on-surface-variant) !important;'
        + '}'
        + '.collapse-block .collapse-content pre {'
        + '  background: var(--md-surface-container) !important;'
        + '  color: var(--md-on-surface-variant) !important;'
        + '}'

        /* 6. 新版本提示 */
        + '.mirages .new-version .new-version-content {'
        + '  background: var(--md-surface-container-low) !important;'
        + '  color: var(--md-on-surface) !important;'
        + '}'
        + '.mirages .new-version .intro {'
        + '  color: var(--md-on-surface) !important;'
        + '}'
        + '.mirages .new-version .warn {'
        + '  color: var(--md-error) !important;'
        + '}'

        /* 7. 底部固定提交栏 */
        + 'ul.typecho-option.typecho-option-submit {'
        + '  background: var(--md-surface-container) !important;'
        + '  border-top: 1px solid var(--md-outline-variant) !important;'
        + '  position: relative !important;'
        + '  background : none !important;'
        + '}'

        /* 8. 暗色模式硬编码覆盖 */
        + '[data-theme="dark"] .collapse-block {'
        + '  border-color: rgba(202,196,208,.28) !important;'
        + '}'
        + '[data-theme="dark"] .collapse-block .collapse-header {'
        + '  background: #302d36 !important;'
        + '}'
        + '[data-theme="dark"] .collapse-block .collapse-content {'
        + '  background: #1c1b1f !important;'
        + '}'
        + '[data-theme="dark"] .collapse-block .collapse-content code,'
        + '[data-theme="dark"] .collapse-block .collapse-content pre {'
        + '  background: #28272c !important;'
        + '  color: #cac4d0 !important;'
        + '}'
        + '[data-theme="dark"] .mirages .new-version .new-version-content {'
        + '  background: #302d36 !important;'
        + '}'
        + '[data-theme="dark"] ul.typecho-option.typecho-option-submit {'
        + '  background: #1c1b1f !important;'
        + '  border-top-color: rgba(202,196,208,.28) !important;'
        + '}'

        ;

    /* ══════════════════════════════════════════════════════════════
     * 二、写文章 / 写页面 样式（write-post.php / write-page.php）
     * ══════════════════════════════════════════════════════════════ */

    var CSS_WRITE = ''

        /* ─── OwO 表情弹窗 ──────────────────────────────────────── */

        /* 触发按钮：采用 MD3 outline chip 风格 */
        + '.OwO .OwO-logo {'
        + '  background: var(--md-surface-container-low) !important;'
        + '  border: 1px solid var(--md-outline-variant) !important;'
        + '  border-radius: var(--md-radius-full) !important;'
        + '  color: var(--md-on-surface-variant) !important;'
        + '  font-size: 13px !important;'
        + '  padding: 4px 12px !important;'
        + '  height: auto !important;'
        + '  line-height: 22px !important;'
        + '  transition: background 0.15s, border-color 0.15s !important;'
        + '}'
        + '.OwO .OwO-logo:hover {'
        + '  background: color-mix(in srgb, var(--md-on-surface-variant) 8%, var(--md-surface-container-low)) !important;'
        + '  border-color: var(--md-outline) !important;'
        + '  color: var(--md-on-surface) !important;'
        + '}'
        + '.OwO.OwO-open .OwO-logo {'
        + '  background: var(--md-secondary-container) !important;'
        + '  border-color: transparent !important;'
        + '  border-radius: var(--md-radius-full) var(--md-radius-full) var(--md-radius-sm) var(--md-radius-sm) !important;'
        + '  color: var(--md-on-secondary-container) !important;'
        + '}'
        /* 弹出面板 */
        + '.OwO .OwO-body {'
        + '  background: var(--md-surface-container) !important;'
        + '  border: 1px solid var(--md-outline-variant) !important;'
        + '  border-radius: 0 var(--md-radius-md) var(--md-radius-md) var(--md-radius-md) !important;'
        + '  box-shadow: 0 4px 16px rgba(0,0,0,.12) !important;'
        + '  overflow: hidden !important;'
        + '}'
        /* 底部分类 tab 栏 */
        + '.OwO .OwO-body .OwO-bar {'
        + '  background: var(--md-surface-container-low) !important;'
        + '  border-top: 1px solid var(--md-outline-variant) !important;'
        + '}'
        + '.OwO .OwO-body .OwO-bar .OwO-packages li {'
        + '  color: var(--md-on-surface-variant) !important;'
        + '  border-radius: var(--md-radius-sm) !important;'
        + '  transition: background 0.15s !important;'
        + '  padding: 2px 10px !important;'
        + '}'
        + '.OwO .OwO-body .OwO-bar .OwO-packages li:hover {'
        + '  background: color-mix(in srgb, var(--md-on-surface-variant) 10%, transparent) !important;'
        + '  color: var(--md-on-surface) !important;'
        + '}'
        + '.OwO .OwO-body .OwO-bar .OwO-packages li.OwO-package-active {'
        + '  background: var(--md-secondary-container) !important;'
        + '  color: var(--md-on-secondary-container) !important;'
        + '}'
        /* OwO 表情内容区文字颜色（某些 OwO 皮肤会硬编码深色文字） */
        + '.OwO .OwO-body .OwO-items {'
        + '  color: var(--md-on-surface) !important;'
        + '}'
        /* 暗色模式 */
        + '[data-theme="dark"] .OwO .OwO-body {'
        + '  background: #2d2d31 !important;'
        + '  border-color: rgba(202,196,208,.22) !important;'
        + '}'
        + '[data-theme="dark"] .OwO .OwO-logo {'
        + '  background: #302d36 !important;'
        + '  border-color: rgba(202,196,208,.28) !important;'
        + '  color: #cac4d0 !important;'
        + '}'
        + '[data-theme="dark"] .OwO.OwO-open .OwO-logo {'
        + '  background: var(--md-secondary-container) !important;'
        + '  color: var(--md-on-secondary-container) !important;'
        + '}'
        + '[data-theme="dark"] .OwO .OwO-body .OwO-bar {'
        + '  background: #252428 !important;'
        + '  border-top-color: rgba(202,196,208,.18) !important;'
        + '}'
        + '[data-theme="dark"] .OwO .OwO-body .OwO-bar .OwO-packages li {'
        + '  color: #cac4d0 !important;'
        + '}'

        /* ─── Mirages 工具栏按钮（FA 图标已被 JS 替换后的尺寸修正）── */

        /* 确保被替换后的 material 图标尺寸与行内 pill 按钮一致 */
        + '#wmd-button-row > li.wmd-button[data-ab-mirages-styled] i.material-icons-round {'
        + '  font-size: 20px !important;'
        + '  width: 20px !important;'
        + '  height: 20px !important;'
        + '  line-height: 1 !important;'
        + '  display: block !important;'
        + '  background: none !important;'
        + '}'

        /* ─── Mirages 自定义对话框（#mirages-dialog）MD3 ────────── */

        /* 背景蒙层 */
        + '#mirages-dialog .wmd-prompt-background {'
        + '  background: rgba(0,0,0,.5) !important;'
        + '  opacity: 1 !important;'
        + '}'
        /* 滚动容器保持不变，对话框盒子 MD3 化 */
        + '#mirages-dialog .wmd-prompt-dialog {'
        + '  background: var(--md-surface-container) !important;'
        + '  border-radius: var(--md-radius-xl, 28px) !important;'
        + '  box-shadow: 0 8px 32px rgba(0,0,0,.18) !important;'
        + '  border: none !important;'
        + '  padding: 24px !important;'
        + '  color: var(--md-on-surface) !important;'
        + '}'
        + '#mirages-dialog .wmd-prompt-dialog h4,'
        + '#mirages-dialog .wmd-prompt-dialog label {'
        + '  color: var(--md-on-surface) !important;'
        + '}'
        /* 对话框内的标签卡 */
        + '#mirages-dialog .content-tabs .content-tabs-head .content-tab-title {'
        + '  background: var(--md-surface-container-high) !important;'
        + '  border-color: var(--md-outline-variant) !important;'
        + '  color: var(--md-on-surface-variant) !important;'
        + '  border-radius: var(--md-radius-sm) var(--md-radius-sm) 0 0 !important;'
        + '  transition: background 0.15s !important;'
        + '}'
        + '#mirages-dialog .content-tabs .content-tabs-head .content-tab-title:hover {'
        + '  background: color-mix(in srgb, var(--md-on-surface-variant) 8%, var(--md-surface-container-high)) !important;'
        + '  color: var(--md-on-surface) !important;'
        + '}'
        + '#mirages-dialog .content-tabs .content-tabs-head .content-tab-title.selected {'
        + '  background: var(--md-secondary-container) !important;'
        + '  border-bottom-color: var(--md-secondary-container) !important;'
        + '  color: var(--md-on-secondary-container) !important;'
        + '}'
        + '#mirages-dialog .content-tabs .content-tabs-body {'
        + '  background: var(--md-surface-container) !important;'
        + '  border-color: var(--md-outline-variant) !important;'
        + '  color: var(--md-on-surface) !important;'
        + '}'
        /* 暗色模式 */
        + '[data-theme="dark"] #mirages-dialog .wmd-prompt-dialog {'
        + '  background: #2d2d31 !important;'
        + '}'
        + '[data-theme="dark"] #mirages-dialog .content-tabs .content-tabs-head .content-tab-title {'
        + '  background: #302d36 !important;'
        + '  border-color: rgba(202,196,208,.22) !important;'
        + '}'
        + '[data-theme="dark"] #mirages-dialog .content-tabs .content-tabs-body {'
        + '  background: #2d2d31 !important;'
        + '  border-color: rgba(202,196,208,.22) !important;'
        + '}'
        /* 对话框内容内的 input/textarea/select 暗色模式 */
        + '[data-theme="dark"] #mirages-dialog input,'
        + '[data-theme="dark"] #mirages-dialog textarea,'
        + '[data-theme="dark"] #mirages-dialog select {'
        + '  background: #1c1b1f !important;'
        + '  border-color: rgba(202,196,208,.28) !important;'
        + '  color: #e6e1e5 !important;'
        + '}'

        /* ─── 全屏模式下 Mirages 对话框层级修复 ─────────────────── */
        /* .ab-fs-overlay 的 z-index 为 1100，对话框需高于此值才能在全屏下显示 */
        + '#mirages-dialog .wmd-prompt-background {'
        + '  z-index: 1150 !important;'
        + '}'
        + '#mirages-dialog .wmd-prompt-container {'
        + '  z-index: 1151 !important;'
        + '}'

        ;

    /* ══════════════════════════════════════════════════════════════
     * 三、Mirages 工具栏 FA → Material Icons 替换
     *     由 Mirages_Plugin 通过 PHP 注入 <i class="fa fa-xxx">
     *     此处用 MutationObserver 在按钮出现后统一替换为 material-icons-round
     * ══════════════════════════════════════════════════════════════ */

    // FontAwesome class → Material Icons Round name
    var FA_TO_MATERIAL = {
        'fa-video-camera':    'videocam',
        'fa-film':            'movie',
        'fa-youtube-play':    'play_circle',
        'fa-music':           'music_note',
        'fa-code':            'code',
        'fa-code-fork':       'call_split',
        'fa-terminal':        'terminal',
        'fa-link':            'link',
        'fa-chain':           'link',
        'fa-external-link':   'open_in_new',
        'fa-picture-o':       'image',
        'fa-photo':           'image',
        'fa-image':           'image',
        'fa-file-image-o':    'image',
        'fa-table':           'table_chart',
        'fa-list-ul':         'format_list_bulleted',
        'fa-list-ol':         'format_list_numbered',
        'fa-list':            'list',
        'fa-th':              'grid_view',
        'fa-th-list':         'view_list',
        'fa-columns':         'view_column',
        'fa-bold':            'format_bold',
        'fa-italic':          'format_italic',
        'fa-underline':       'format_underlined',
        'fa-strikethrough':   'format_strikethrough',
        'fa-subscript':       'subscript',
        'fa-superscript':     'superscript',
        'fa-quote-left':      'format_quote',
        'fa-quote-right':     'format_quote',
        'fa-align-left':      'format_align_left',
        'fa-align-center':    'format_align_center',
        'fa-align-right':     'format_align_right',
        'fa-align-justify':   'format_align_justify',
        'fa-text-height':     'text_fields',
        'fa-header':          'title',
        'fa-paragraph':       'notes',
        'fa-minus':           'horizontal_rule',
        'fa-arrows-h':        'swap_horiz',
        'fa-arrows-v':        'swap_vert',
        'fa-crop':            'crop',
        'fa-magic':           'auto_fix_high',
        'fa-pencil':          'edit',
        'fa-edit':            'edit',
        'fa-pencil-square-o': 'edit',
        'fa-eraser':          'backspace',
        'fa-scissors':        'content_cut',
        'fa-cut':             'content_cut',
        'fa-copy':            'content_copy',
        'fa-paste':           'content_paste',
        'fa-files-o':         'content_copy',
        'fa-save':            'save',
        'fa-floppy-o':        'save',
        'fa-upload':          'upload',
        'fa-download':        'download',
        'fa-cloud-upload':    'cloud_upload',
        'fa-cloud-download':  'cloud_download',
        'fa-paperclip':       'attach_file',
        'fa-folder':          'folder',
        'fa-folder-o':        'folder',
        'fa-folder-open':     'folder_open',
        'fa-file':            'insert_drive_file',
        'fa-file-o':          'insert_drive_file',
        'fa-file-text':       'article',
        'fa-file-text-o':     'article',
        'fa-file-code-o':     'code',
        'fa-archive':         'archive',
        'fa-envelope':        'email',
        'fa-envelope-o':      'email',
        'fa-flag':            'flag',
        'fa-flag-o':          'outlined_flag',
        'fa-tag':             'local_offer',
        'fa-tags':            'local_offer',
        'fa-bookmark':        'bookmark',
        'fa-bookmark-o':      'bookmark_border',
        'fa-star':            'star',
        'fa-star-o':          'star_border',
        'fa-heart':           'favorite',
        'fa-heart-o':         'favorite_border',
        'fa-thumbs-up':       'thumb_up',
        'fa-thumbs-o-up':     'thumb_up',
        'fa-thumbs-down':     'thumb_down',
        'fa-thumbs-o-down':   'thumb_down',
        'fa-comment':         'chat_bubble',
        'fa-comment-o':       'chat_bubble_outline',
        'fa-comments':        'forum',
        'fa-comments-o':      'forum',
        'fa-info':            'info',
        'fa-info-circle':     'info',
        'fa-question':        'help',
        'fa-question-circle': 'help',
        'fa-exclamation':     'warning',
        'fa-exclamation-circle': 'error',
        'fa-exclamation-triangle': 'warning',
        'fa-check':           'check',
        'fa-check-circle':    'check_circle',
        'fa-times':           'close',
        'fa-times-circle':    'cancel',
        'fa-ban':             'block',
        'fa-search':          'search',
        'fa-filter':          'filter_list',
        'fa-sort':            'sort',
        'fa-plus':            'add',
        'fa-plus-circle':     'add_circle',
        'fa-minus-circle':    'remove_circle',
        'fa-arrow-up':        'arrow_upward',
        'fa-arrow-down':      'arrow_downward',
        'fa-arrow-left':      'arrow_back',
        'fa-arrow-right':     'arrow_forward',
        'fa-caret-up':        'expand_less',
        'fa-caret-down':      'expand_more',
        'fa-chevron-up':      'expand_less',
        'fa-chevron-down':    'expand_more',
        'fa-refresh':         'refresh',
        'fa-repeat':          'repeat',
        'fa-undo':            'undo',
        'fa-share':           'share',
        'fa-print':           'print',
        'fa-rss':             'rss_feed',
        'fa-feed':            'rss_feed',
        'fa-globe':           'language',
        'fa-map-marker':      'place',
        'fa-location-arrow':  'navigation',
        'fa-home':            'home',
        'fa-user':            'person',
        'fa-users':           'group',
        'fa-group':           'group',
        'fa-lock':            'lock',
        'fa-unlock':          'lock_open',
        'fa-key':             'key',
        'fa-eye':             'visibility',
        'fa-eye-slash':       'visibility_off',
        'fa-wrench':          'settings',
        'fa-cog':             'settings',
        'fa-cogs':            'settings',
        'fa-gears':           'settings',
        'fa-tasks':           'checklist',
        'fa-bars':            'menu',
        'fa-navicon':         'menu',
        'fa-reorder':         'reorder',
        'fa-ellipsis-h':      'more_horiz',
        'fa-ellipsis-v':      'more_vert',
        'fa-smile-o':         'sentiment_satisfied',
        'fa-binoculars':      'pageview',
        'fa-puzzle-piece':    'extension',
        'fa-plug':            'power',
        'fa-paint-brush':     'brush',
        'fa-palette':         'palette',
        'fa-font':            'font_download',
        'fa-text-width':      'text_fields',
        'fa-indent':          'format_indent_increase',
        'fa-dedent':          'format_indent_decrease',
        'fa-outdent':         'format_indent_decrease',
        'fa-rotate-left':     'rotate_left',
        'fa-rotate-right':    'rotate_right',
        'fa-clone':           'content_copy',
        'fa-expand':          'open_in_full',
        'fa-compress':        'close_fullscreen',
        'fa-arrows-alt':      'open_with',
        'fa-long-arrow-right':'east',
        'fa-long-arrow-left': 'west',
        'fa-desktop':         'desktop_windows',
        'fa-laptop':          'laptop',
        'fa-tablet':          'tablet',
        'fa-mobile':          'phone_iphone',
        'fa-mobile-phone':    'phone_iphone',
        'fa-calculator':      'calculate',
        'fa-database':        'storage',
        'fa-server':          'dns',
        'fa-microphone':      'mic',
        'fa-volume-up':       'volume_up',
        'fa-volume-down':     'volume_down',
        'fa-volume-off':      'volume_off',
        'fa-gamepad':         'sports_esports',
        'fa-th-large':        'dashboard',
        'fa-sitemap':         'account_tree',
        'fa-exchange':        'swap_horiz',
        'fa-random':          'shuffle',
        'fa-clock-o':         'schedule',
        'fa-calendar':        'calendar_today',
        'fa-calendar-o':      'calendar_today',
        'fa-history':         'history',
        'fa-bell':            'notifications',
        'fa-bell-o':          'notifications_none',
        'fa-trophy':          'emoji_events',
        'fa-road':            'route',
        'fa-plane':           'flight',
        'fa-ship':            'directions_boat',
        'fa-car':             'directions_car',
        'fa-train':           'train',
        'fa-bus':             'directions_bus',
        'fa-bicycle':         'pedal_bike',
        'fa-leaf':            'eco',
        'fa-tree':            'park',
        'fa-sun-o':           'wb_sunny',
        'fa-moon-o':          'dark_mode',
        'fa-cloud':           'cloud',
        'fa-umbrella':        'beach_access',
        'fa-coffee':          'coffee',
        'fa-cutlery':         'restaurant',
        'fa-gift':            'card_giftcard',
        'fa-shopping-cart':   'shopping_cart',
        'fa-credit-card':     'credit_card',
        'fa-money':           'payments',
        'fa-bank':            'account_balance',
        'fa-graduation-cap':  'school',
        'fa-book':            'book',
        'fa-newspaper-o':     'newspaper',
        'fa-film':            'movie',
        'fa-camera':          'camera_alt',
        'fa-camera-retro':    'camera',
        'fa-paint-brush':     'brush',
        'fa-tint':            'water_drop',
        'fa-fire':            'local_fire_department',
        'fa-bolt':            'bolt',
        'fa-flask':           'science',
        'fa-recycle':         'recycling',
        'fa-shield':          'security',
        'fa-lock':            'lock',
        'fa-certificate':     'verified',
        'fa-check-square':    'check_box',
        'fa-check-square-o':  'check_box_outline_blank',
        'fa-square':          'check_box_outline_blank',
        'fa-square-o':        'check_box_outline_blank',
        'fa-circle':          'circle',
        'fa-circle-o':        'radio_button_unchecked',
        'fa-dot-circle-o':    'radio_button_checked',
        'fa-thumb-tack':      'push_pin',
        'fa-map-o':           'map',
        'fa-compass':         'explore',
        'fa-crosshairs':      'gps_fixed',
        'fa-paper-plane':     'send',
        'fa-paper-plane-o':   'send',
        'fa-reply':           'reply',
        'fa-reply-all':       'reply_all',
        'fa-forward':         'forward',
        'fa-step-forward':    'skip_next',
        'fa-step-backward':   'skip_previous',
        'fa-play':            'play_arrow',
        'fa-pause':           'pause',
        'fa-stop':            'stop',
        'fa-fast-forward':    'fast_forward',
        'fa-fast-backward':   'fast_rewind',
        // Mirages 插件专属（短代码/卡片/标签卡等）
        'fa-window-maximize': 'web_asset',
        'fa-object-group':    'select_all',
        'fa-id-card':         'badge',
        'fa-id-card-o':       'badge',
        'fa-id-badge':        'badge',
        'fa-address-card':    'contact_page',
        'fa-address-card-o':  'contact_page',
        'fa-sticky-note':     'sticky_note_2',
        'fa-sticky-note-o':   'sticky_note_2',
        'fa-file-zip-o':      'folder_zip',
        'fa-file-archive-o':  'folder_zip',
        'fa-toggle-on':       'toggle_on',
        'fa-toggle-off':      'toggle_off',
        'fa-sliders':         'tune',
        'fa-hashtag':         'tag',
        'fa-at':              'alternate_email',
        'fa-qrcode':          'qr_code',
        'fa-barcode':         'barcode_scanner',
        'fa-sign-in':         'login',
        'fa-sign-out':        'logout',
        'fa-legal':           'gavel',
        'fa-gavel':           'gavel',
        'fa-life-ring':       'support',
        'fa-life-bouy':       'support',
        'fa-anchor':          'anchor',
        'fa-paragraph':       'segment'
    };

    /**
     * 将 #wmd-button-row 中 Mirages 注入的 FA 图标替换为 material-icons-round
     * 跳过已被 AdminBeautify 处理的标准按钮（有 data-ab-orig-id 或 id 在 WMD 标准列表中）
     */
    var WMD_STANDARD_IDS = [
        'wmd-bold-button', 'wmd-italic-button', 'wmd-link-button',
        'wmd-quote-button', 'wmd-code-button', 'wmd-image-button',
        'wmd-olist-button', 'wmd-ulist-button', 'wmd-heading-button',
        'wmd-hr-button', 'wmd-more-button', 'wmd-undo-button',
        'wmd-redo-button', 'wmd-fullscreen-button', 'wmd-exit-fullscreen-button'
    ];

    function replaceMiragesIcons() {
        var row = document.getElementById('wmd-button-row');
        if (!row) return;
        var btns = row.querySelectorAll('li.wmd-button:not([data-ab-mirages-styled])');
        var changed = 0;
        for (var i = 0; i < btns.length; i++) {
            var btn = btns[i];
            // 跳过 WMD 标准按钮（已由 AdminBeautify 主 JS 处理）
            if (WMD_STANDARD_IDS.indexOf(btn.id) !== -1) continue;
            // 跳过已有 material-icons-round 的（已处理）
            if (btn.querySelector('i.material-icons-round')) continue;

            var faIcon = btn.querySelector('i.fa, i[class*="fa-"]');
            if (!faIcon) continue;

            // 找出 FA 图标 class 名（fa-xxx 部分），null 表示未找到映射
            var iconName = null;
            var classes = faIcon.className.split(/\s+/);
            for (var j = 0; j < classes.length; j++) {
                if (classes[j].indexOf('fa-') === 0 && FA_TO_MATERIAL[classes[j]]) {
                    iconName = FA_TO_MATERIAL[classes[j]];
                    break;
                }
            }

            // 未找到映射 → 保留原 FA 图标，仅打标记防止重复处理
            if (!iconName) {
                btn.setAttribute('data-ab-mirages-styled', '1');
                continue;
            }

            // 替换 FA 图标为 material-icons-round
            var newIcon = document.createElement('i');
            newIcon.className = 'material-icons-round';
            newIcon.textContent = iconName;

            // 保留 span（按钮文字），移除 fa icon
            faIcon.parentNode.replaceChild(newIcon, faIcon);
            btn.setAttribute('data-ab-mirages-styled', '1');
            changed++;
        }
        if (changed > 0) {
            // 通知 AdminBeautify 重新检查溢出（通过 resize 事件）
            window.dispatchEvent(new Event('resize'));
        }
    }

    /* ─── 注入 / 移除 写页面 CSS ──────────────────────────────── */

    function applyWriteFix() {
        if (!document.getElementById(STYLE_WRITE_ID)) {
            var style = document.createElement('style');
            style.id = STYLE_WRITE_ID;
            style.textContent = CSS_WRITE;
            document.head.appendChild(style);
        }

        // 立即尝试替换（可能已经存在）
        replaceMiragesIcons();

        // MutationObserver 监听 #wmd-button-row 子节点变化（插件动态注入按钮时）
        var row = document.getElementById('wmd-button-row');
        if (row && !row._abMiragesObserver) {
            var obs = new MutationObserver(function () {
                replaceMiragesIcons();
            });
            obs.observe(row, { childList: true, subtree: true });
            row._abMiragesObserver = obs;
        } else if (!row) {
            // row 还不存在，等待 DOM 挂载
            var bodyObs = new MutationObserver(function () {
                var r = document.getElementById('wmd-button-row');
                if (r) {
                    bodyObs.disconnect();
                    replaceMiragesIcons();
                    if (!r._abMiragesObserver) {
                        var obs2 = new MutationObserver(replaceMiragesIcons);
                        obs2.observe(r, { childList: true, subtree: true });
                        r._abMiragesObserver = obs2;
                    }
                }
            });
            bodyObs.observe(document.body || document.documentElement, { childList: true, subtree: true });
        }
    }

    /* ══════════════════════════════════════════════════════════════
     * 四、注入 / 移除 逻辑
     * ══════════════════════════════════════════════════════════════ */

    function applyFix(url) {
        var isSettings = (url || '').indexOf('options-theme.php') !== -1;
        var isWrite    = (url || '').indexOf('write-post.php')    !== -1
                      || (url || '').indexOf('write-page.php')    !== -1;

        /* —— 主题设置页 —— */
        if (!isSettings) {
            var old = document.getElementById(STYLE_ID);
            if (old) old.parentNode.removeChild(old);
        } else {
            if (!document.getElementById(STYLE_ID)) {
                var style = document.createElement('style');
                style.id = STYLE_ID;
                style.textContent = CSS_SETTINGS;
                document.head.appendChild(style);
            }
        }

        /* —— 写文章/写页面 —— */
        if (!isWrite) {
            var oldW = document.getElementById(STYLE_WRITE_ID);
            if (oldW) oldW.parentNode.removeChild(oldW);
        } else {
            applyWriteFix();
        }
    }

    /* ─── 首次加载 ──────────────────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            applyFix(window.location.href);
        });
    } else {
        applyFix(window.location.href);
    }

    /* ─── AdminBeautify AJAX 页面切换 ──────────────────────────── */
    document.addEventListener('ab:pageload', function (e) {
        var url = (e && e.detail && e.detail.url) ? e.detail.url : window.location.href;
        applyFix(url);
    });

})();
