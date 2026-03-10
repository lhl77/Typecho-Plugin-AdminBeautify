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
 * @version     1.0.0
 * @author      LHL
 */
(function () {
    'use strict';

    var STYLE_ID       = 'ab-compat-mirages';
    var STYLE_WRITE_ID = 'ab-compat-mirages-write';

    /* \E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90
     * \E4\B8\80\E3\80\81\E4\B8\BB\E9\A2\98\E8\AE\BE\E7\BD\AE\E9\A1\B5\E6\A0\B7\E5\BC\8F\EF\BC\88options-theme.php\EF\BC\89
     * \E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90 */

    var CSS_SETTINGS = ''

        /* 1. .mirages \E5\93\81\E7\89\8C\E5\A4\B4\E9\83\A8 */
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

        /* 5. \E4\BB\A3\E7\A0\81\E5\9D\97 */
        + '.collapse-block .collapse-content code {'
        + '  background: var(--md-surface-container) !important;'
        + '  color: var(--md-on-surface-variant) !important;'
        + '}'
        + '.collapse-block .collapse-content pre {'
        + '  background: var(--md-surface-container) !important;'
        + '  color: var(--md-on-surface-variant) !important;'
        + '}'

        /* 6. \E6\96\B0\E7\89\88\E6\9C\AC\E6\8F\90\E7\A4\BA */
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

        /* 7. \E5\BA\95\E9\83\A8\E5\9B\BA\E5\AE\9A\E6\8F\90\E4\BA\A4\E6\A0\8F */
        + 'ul.typecho-option.typecho-option-submit {'
        + '  background: var(--md-surface-container) !important;'
        + '  border-top: 1px solid var(--md-outline-variant) !important;'
        + '}'

        /* 8. \E6\9A\97\E8\89\B2\E6\A8\A1\E5\BC\8F\E7\A1\AC\E7\BC\96\E7\A0\81\E8\A6\86\E7\9B\96 */
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

    /* \E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90
     * \E4\BA\8C\E3\80\81\E5\86\99\E6\96\87\E7\AB\A0 / \E5\86\99\E9\A1\B5\E9\9D\A2 \E6\A0\B7\E5\BC\8F\EF\BC\88write-post.php / write-page.php\EF\BC\89
     * \E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90 */

    var CSS_WRITE = ''

        /* \E2\94\80\E2\94\80\E2\94\80 OwO \E8\A1\A8\E6\83\85\E5\BC\B9\E7\AA\97 \E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80 */

        /* \E8\A7\A6\E5\8F\91\E6\8C\89\E9\92\AE\EF\BC\9A\E9\87\87\E7\94\A8 MD3 outline chip \E9\A3\8E\E6\A0\BC */
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
        /* \E5\BC\B9\E5\87\BA\E9\9D\A2\E6\9D\BF */
        + '.OwO .OwO-body {'
        + '  background: var(--md-surface-container) !important;'
        + '  border: 1px solid var(--md-outline-variant) !important;'
        + '  border-radius: 0 var(--md-radius-md) var(--md-radius-md) var(--md-radius-md) !important;'
        + '  box-shadow: 0 4px 16px rgba(0,0,0,.12) !important;'
        + '  overflow: hidden !important;'
        + '}'
        /* \E5\BA\95\E9\83\A8\E5\88\86\E7\B1\BB tab \E6\A0\8F */
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
        /* OwO \E8\A1\A8\E6\83\85\E5\86\85\E5\AE\B9\E5\8C\BA\E6\96\87\E5\AD\97\E9\A2\9C\E8\89\B2\EF\BC\88\E6\9F\90\E4\BA\9B OwO \E7\9A\AE\E8\82\A4\E4\BC\9A\E7\A1\AC\E7\BC\96\E7\A0\81\E6\B7\B1\E8\89\B2\E6\96\87\E5\AD\97\EF\BC\89 */
        + '.OwO .OwO-body .OwO-items {'
        + '  color: var(--md-on-surface) !important;'
        + '}'
        /* \E6\9A\97\E8\89\B2\E6\A8\A1\E5\BC\8F */
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

        /* \E2\94\80\E2\94\80\E2\94\80 Mirages \E5\B7\A5\E5\85\B7\E6\A0\8F\E6\8C\89\E9\92\AE\EF\BC\88FA \E5\9B\BE\E6\A0\87\E5\B7\B2\E8\A2\AB JS \E6\9B\BF\E6\8D\A2\E5\90\8E\E7\9A\84\E5\B0\BA\E5\AF\B8\E4\BF\AE\E6\AD\A3\EF\BC\89\E2\94\80\E2\94\80 */

        /* \E7\A1\AE\E4\BF\9D\E8\A2\AB\E6\9B\BF\E6\8D\A2\E5\90\8E\E7\9A\84 material \E5\9B\BE\E6\A0\87\E5\B0\BA\E5\AF\B8\E4\B8\8E\E8\A1\8C\E5\86\85 pill \E6\8C\89\E9\92\AE\E4\B8\80\E8\87\B4 */
        + '#wmd-button-row > li.wmd-button[data-ab-mirages-styled] i.material-icons-round {'
        + '  font-size: 20px !important;'
        + '  width: 20px !important;'
        + '  height: 20px !important;'
        + '  line-height: 1 !important;'
        + '  display: block !important;'
        + '  background: none !important;'
        + '}'

        /* \E2\94\80\E2\94\80\E2\94\80 Mirages \E8\87\AA\E5\AE\9A\E4\B9\89\E5\AF\B9\E8\AF\9D\E6\A1\86\EF\BC\88#mirages-dialog\EF\BC\89MD3 \E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80 */

        /* \E8\83\8C\E6\99\AF\E8\92\99\E5\B1\82 */
        + '#mirages-dialog .wmd-prompt-background {'
        + '  background: rgba(0,0,0,.5) !important;'
        + '  opacity: 1 !important;'
        + '}'
        /* \E6\BB\9A\E5\8A\A8\E5\AE\B9\E5\99\A8\E4\BF\9D\E6\8C\81\E4\B8\8D\E5\8F\98\EF\BC\8C\E5\AF\B9\E8\AF\9D\E6\A1\86\E7\9B\92\E5\AD\90 MD3 \E5\8C\96 */
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
        /* \E5\AF\B9\E8\AF\9D\E6\A1\86\E5\86\85\E7\9A\84\E6\A0\87\E7\AD\BE\E5\8D\A1 */
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
        /* \E6\9A\97\E8\89\B2\E6\A8\A1\E5\BC\8F */
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
        /* \E5\AF\B9\E8\AF\9D\E6\A1\86\E5\86\85\E5\AE\B9\E5\86\85\E7\9A\84 input/textarea/select \E6\9A\97\E8\89\B2\E6\A8\A1\E5\BC\8F */
        + '[data-theme="dark"] #mirages-dialog input,'
        + '[data-theme="dark"] #mirages-dialog textarea,'
        + '[data-theme="dark"] #mirages-dialog select {'
        + '  background: #1c1b1f !important;'
        + '  border-color: rgba(202,196,208,.28) !important;'
        + '  color: #e6e1e5 !important;'
        + '}'

        /* \E2\94\80\E2\94\80\E2\94\80 \E5\85\A8\E5\B1\8F\E6\A8\A1\E5\BC\8F\E4\B8\8B Mirages \E5\AF\B9\E8\AF\9D\E6\A1\86\E5\B1\82\E7\BA\A7\E4\BF\AE\E5\A4\8D \E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80 */
        /* .ab-fs-overlay \E7\9A\84 z-index \E4\B8\BA 1100\EF\BC\8C\E5\AF\B9\E8\AF\9D\E6\A1\86\E9\9C\80\E9\AB\98\E4\BA\8E\E6\AD\A4\E5\80\BC\E6\89\8D\E8\83\BD\E5\9C\A8\E5\85\A8\E5\B1\8F\E4\B8\8B\E6\98\BE\E7\A4\BA */
        + '#mirages-dialog .wmd-prompt-background {'
        + '  z-index: 1150 !important;'
        + '}'
        + '#mirages-dialog .wmd-prompt-container {'
        + '  z-index: 1151 !important;'
        + '}'

        ;

    /* \E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90
     * \E4\B8\89\E3\80\81Mirages \E5\B7\A5\E5\85\B7\E6\A0\8F FA \E2\86\92 Material Icons \E6\9B\BF\E6\8D\A2
     *     \E7\94\B1 Mirages_Plugin \E9\80\9A\E8\BF\87 PHP \E6\B3\A8\E5\85\A5 <i class="fa fa-xxx">
     *     \E6\AD\A4\E5\A4\84\E7\94\A8 MutationObserver \E5\9C\A8\E6\8C\89\E9\92\AE\E5\87\BA\E7\8E\B0\E5\90\8E\E7\BB\9F\E4\B8\80\E6\9B\BF\E6\8D\A2\E4\B8\BA material-icons-round
     * \E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90 */

    // FontAwesome class \E2\86\92 Material Icons Round name
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
        // Mirages \E6\8F\92\E4\BB\B6\E4\B8\93\E5\B1\9E\EF\BC\88\E7\9F\AD\E4\BB\A3\E7\A0\81/\E5\8D\A1\E7\89\87/\E6\A0\87\E7\AD\BE\E5\8D\A1\E7\AD\89\EF\BC\89
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
     * \E5\B0\86 #wmd-button-row \E4\B8\AD Mirages \E6\B3\A8\E5\85\A5\E7\9A\84 FA \E5\9B\BE\E6\A0\87\E6\9B\BF\E6\8D\A2\E4\B8\BA material-icons-round
     * \E8\B7\B3\E8\BF\87\E5\B7\B2\E8\A2\AB AdminBeautify \E5\A4\84\E7\90\86\E7\9A\84\E6\A0\87\E5\87\86\E6\8C\89\E9\92\AE\EF\BC\88\E6\9C\89 data-ab-orig-id \E6\88\96 id \E5\9C\A8 WMD \E6\A0\87\E5\87\86\E5\88\97\E8\A1\A8\E4\B8\AD\EF\BC\89
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
            // \E8\B7\B3\E8\BF\87 WMD \E6\A0\87\E5\87\86\E6\8C\89\E9\92\AE\EF\BC\88\E5\B7\B2\E7\94\B1 AdminBeautify \E4\B8\BB JS \E5\A4\84\E7\90\86\EF\BC\89
            if (WMD_STANDARD_IDS.indexOf(btn.id) !== -1) continue;
            // \E8\B7\B3\E8\BF\87\E5\B7\B2\E6\9C\89 material-icons-round \E7\9A\84\EF\BC\88\E5\B7\B2\E5\A4\84\E7\90\86\EF\BC\89
            if (btn.querySelector('i.material-icons-round')) continue;

            var faIcon = btn.querySelector('i.fa, i[class*="fa-"]');
            if (!faIcon) continue;

            // \E6\89\BE\E5\87\BA FA \E5\9B\BE\E6\A0\87 class \E5\90\8D\EF\BC\88fa-xxx \E9\83\A8\E5\88\86\EF\BC\89\EF\BC\8Cnull \E8\A1\A8\E7\A4\BA\E6\9C\AA\E6\89\BE\E5\88\B0\E6\98\A0\E5\B0\84
            var iconName = null;
            var classes = faIcon.className.split(/\s+/);
            for (var j = 0; j < classes.length; j++) {
                if (classes[j].indexOf('fa-') === 0 && FA_TO_MATERIAL[classes[j]]) {
                    iconName = FA_TO_MATERIAL[classes[j]];
                    break;
                }
            }

            // \E6\9C\AA\E6\89\BE\E5\88\B0\E6\98\A0\E5\B0\84 \E2\86\92 \E4\BF\9D\E7\95\99\E5\8E\9F FA \E5\9B\BE\E6\A0\87\EF\BC\8C\E4\BB\85\E6\89\93\E6\A0\87\E8\AE\B0\E9\98\B2\E6\AD\A2\E9\87\8D\E5\A4\8D\E5\A4\84\E7\90\86
            if (!iconName) {
                btn.setAttribute('data-ab-mirages-styled', '1');
                continue;
            }

            // \E6\9B\BF\E6\8D\A2 FA \E5\9B\BE\E6\A0\87\E4\B8\BA material-icons-round
            var newIcon = document.createElement('i');
            newIcon.className = 'material-icons-round';
            newIcon.textContent = iconName;

            // \E4\BF\9D\E7\95\99 span\EF\BC\88\E6\8C\89\E9\92\AE\E6\96\87\E5\AD\97\EF\BC\89\EF\BC\8C\E7\A7\BB\E9\99\A4 fa icon
            faIcon.parentNode.replaceChild(newIcon, faIcon);
            btn.setAttribute('data-ab-mirages-styled', '1');
            changed++;
        }
        if (changed > 0) {
            // \E9\80\9A\E7\9F\A5 AdminBeautify \E9\87\8D\E6\96\B0\E6\A3\80\E6\9F\A5\E6\BA\A2\E5\87\BA\EF\BC\88\E9\80\9A\E8\BF\87 resize \E4\BA\8B\E4\BB\B6\EF\BC\89
            window.dispatchEvent(new Event('resize'));
        }
    }

    /* \E2\94\80\E2\94\80\E2\94\80 \E6\B3\A8\E5\85\A5 / \E7\A7\BB\E9\99\A4 \E5\86\99\E9\A1\B5\E9\9D\A2 CSS \E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80 */

    function applyWriteFix() {
        if (!document.getElementById(STYLE_WRITE_ID)) {
            var style = document.createElement('style');
            style.id = STYLE_WRITE_ID;
            style.textContent = CSS_WRITE;
            document.head.appendChild(style);
        }

        // \E7\AB\8B\E5\8D\B3\E5\B0\9D\E8\AF\95\E6\9B\BF\E6\8D\A2\EF\BC\88\E5\8F\AF\E8\83\BD\E5\B7\B2\E7\BB\8F\E5\AD\98\E5\9C\A8\EF\BC\89
        replaceMiragesIcons();

        // MutationObserver \E7\9B\91\E5\90\AC #wmd-button-row \E5\AD\90\E8\8A\82\E7\82\B9\E5\8F\98\E5\8C\96\EF\BC\88\E6\8F\92\E4\BB\B6\E5\8A\A8\E6\80\81\E6\B3\A8\E5\85\A5\E6\8C\89\E9\92\AE\E6\97\B6\EF\BC\89
        var row = document.getElementById('wmd-button-row');
        if (row && !row._abMiragesObserver) {
            var obs = new MutationObserver(function () {
                replaceMiragesIcons();
            });
            obs.observe(row, { childList: true, subtree: true });
            row._abMiragesObserver = obs;
        } else if (!row) {
            // row \E8\BF\98\E4\B8\8D\E5\AD\98\E5\9C\A8\EF\BC\8C\E7\AD\89\E5\BE\85 DOM \E6\8C\82\E8\BD\BD
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

    /* \E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90
     * \E5\9B\9B\E3\80\81\E6\B3\A8\E5\85\A5 / \E7\A7\BB\E9\99\A4 \E9\80\BB\E8\BE\91
     * \E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90\E2\95\90 */

    function applyFix(url) {
        var isSettings = (url || '').indexOf('options-theme.php') !== -1;
        var isWrite    = (url || '').indexOf('write-post.php')    !== -1
                      || (url || '').indexOf('write-page.php')    !== -1;

        /* \E2\80\94\E2\80\94 \E4\B8\BB\E9\A2\98\E8\AE\BE\E7\BD\AE\E9\A1\B5 \E2\80\94\E2\80\94 */
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

        /* \E2\80\94\E2\80\94 \E5\86\99\E6\96\87\E7\AB\A0/\E5\86\99\E9\A1\B5\E9\9D\A2 \E2\80\94\E2\80\94 */
        if (!isWrite) {
            var oldW = document.getElementById(STYLE_WRITE_ID);
            if (oldW) oldW.parentNode.removeChild(oldW);
        } else {
            applyWriteFix();
        }
    }

    /* \E2\94\80\E2\94\80\E2\94\80 \E9\A6\96\E6\AC\A1\E5\8A\A0\E8\BD\BD \E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80 */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            applyFix(window.location.href);
        });
    } else {
        applyFix(window.location.href);
    }

    /* \E2\94\80\E2\94\80\E2\94\80 AdminBeautify AJAX \E9\A1\B5\E9\9D\A2\E5\88\87\E6\8D\A2 \E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80\E2\94\80 */
    document.addEventListener('ab:pageload', function (e) {
        var url = (e && e.detail && e.detail.url) ? e.detail.url : window.location.href;
        applyFix(url);
    });

})();
(function(){'use strict';var STYLE_ID='ab-compat-mirages';var STYLE_WRITE_ID='ab-compat-mirages-write';var CSS_SETTINGS='.mirages {  color: var(--md-on-surface) !important;}.mirages h1.logo {  color: var(--md-on-surface) !important;}.mirages h1.logo .typecho {  color: var(--md-on-surface-variant) !important;}.mirages .help-info a {  color: var(--md-primary) !important;}.mirages .version {  color: var(--md-on-surface-variant) !important;}.collapse-block {  border-color: var(--md-outline-variant) !important;  border-radius: var(--md-radius-lg, 16px) !important;  overflow: hidden;}.collapse-block .collapse-header {  background: var(--md-surface-container-low) !important;  border-radius: 0 !important;}.collapse-block .collapse-header .title {  color: var(--md-on-surface) !important;}.collapse-block .collapse-content {  background: var(--md-surface-container-lowest) !important;}.collapse-block .collapse-content .description {  color: var(--md-on-surface-variant) !important;}.collapse-block .collapse-content code {  background: var(--md-surface-container) !important;  color: var(--md-on-surface-variant) !important;}.collapse-block .collapse-content pre {  background: var(--md-surface-container) !important;  color: var(--md-on-surface-variant) !important;}.mirages .new-version .new-version-content {  background: var(--md-surface-container-low) !important;  color: var(--md-on-surface) !important;}.mirages .new-version .intro {  color: var(--md-on-surface) !important;}.mirages .new-version .warn {  color: var(--md-error) !important;}ul.typecho-option.typecho-option-submit {  background: var(--md-surface-container) !important;  border-top: 1px solid var(--md-outline-variant) !important;}[data-theme="dark"] .collapse-block {  border-color: rgba(202,196,208,.28) !important;}[data-theme="dark"] .collapse-block .collapse-header {  background: #302d36 !important;}[data-theme="dark"] .collapse-block .collapse-content {  background: #1c1b1f !important;}[data-theme="dark"] .collapse-block .collapse-content code,[data-theme="dark"] .collapse-block .collapse-content pre {  background: #28272c !important;  color: #cac4d0 !important;}[data-theme="dark"] .mirages .new-version .new-version-content {  background: #302d36 !important;}[data-theme="dark"] ul.typecho-option.typecho-option-submit {  background: #1c1b1f !important;  border-top-color: rgba(202,196,208,.28) !important;}';var CSS_WRITE='.OwO .OwO-logo {  background: var(--md-surface-container-low) !important;  border: 1px solid var(--md-outline-variant) !important;  border-radius: var(--md-radius-full) !important;  color: var(--md-on-surface-variant) !important;  font-size: 13px !important;  padding: 4px 12px !important;  height: auto !important;  line-height: 22px !important;  transition: background 0.15s, border-color 0.15s !important;}.OwO .OwO-logo:hover {  background: color-mix(in srgb, var(--md-on-surface-variant) 8%, var(--md-surface-container-low)) !important;  border-color: var(--md-outline) !important;  color: var(--md-on-surface) !important;}.OwO.OwO-open .OwO-logo {  background: var(--md-secondary-container) !important;  border-color: transparent !important;  border-radius: var(--md-radius-full) var(--md-radius-full) var(--md-radius-sm) var(--md-radius-sm) !important;  color: var(--md-on-secondary-container) !important;}.OwO .OwO-body {  background: var(--md-surface-container) !important;  border: 1px solid var(--md-outline-variant) !important;  border-radius: 0 var(--md-radius-md) var(--md-radius-md) var(--md-radius-md) !important;  box-shadow: 0 4px 16px rgba(0,0,0,.12) !important;  overflow: hidden !important;}.OwO .OwO-body .OwO-bar {  background: var(--md-surface-container-low) !important;  border-top: 1px solid var(--md-outline-variant) !important;}.OwO .OwO-body .OwO-bar .OwO-packages li {  color: var(--md-on-surface-variant) !important;  border-radius: var(--md-radius-sm) !important;  transition: background 0.15s !important;  padding: 2px 10px !important;}.OwO .OwO-body .OwO-bar .OwO-packages li:hover {  background: color-mix(in srgb, var(--md-on-surface-variant) 10%, transparent) !important;  color: var(--md-on-surface) !important;}.OwO .OwO-body .OwO-bar .OwO-packages li.OwO-package-active {  background: var(--md-secondary-container) !important;  color: var(--md-on-secondary-container) !important;}.OwO .OwO-body .OwO-items {  color: var(--md-on-surface) !important;}[data-theme="dark"] .OwO .OwO-body {  background: #2d2d31 !important;  border-color: rgba(202,196,208,.22) !important;}[data-theme="dark"] .OwO .OwO-logo {  background: #302d36 !important;  border-color: rgba(202,196,208,.28) !important;  color: #cac4d0 !important;}[data-theme="dark"] .OwO.OwO-open .OwO-logo {  background: var(--md-secondary-container) !important;  color: var(--md-on-secondary-container) !important;}[data-theme="dark"] .OwO .OwO-body .OwO-bar {  background: #252428 !important;  border-top-color: rgba(202,196,208,.18) !important;}[data-theme="dark"] .OwO .OwO-body .OwO-bar .OwO-packages li {  color: #cac4d0 !important;}#wmd-button-row > li.wmd-button[data-ab-mirages-styled] i.material-icons-round {  font-size: 20px !important;  width: 20px !important;  height: 20px !important;  line-height: 1 !important;  display: block !important;  background: none !important;}#mirages-dialog .wmd-prompt-background {  background: rgba(0,0,0,.5) !important;  opacity: 1 !important;}#mirages-dialog .wmd-prompt-dialog {  background: var(--md-surface-container) !important;  border-radius: var(--md-radius-xl, 28px) !important;  box-shadow: 0 8px 32px rgba(0,0,0,.18) !important;  border: none !important;  padding: 24px !important;  color: var(--md-on-surface) !important;}#mirages-dialog .wmd-prompt-dialog h4,#mirages-dialog .wmd-prompt-dialog label {  color: var(--md-on-surface) !important;}#mirages-dialog .content-tabs .content-tabs-head .content-tab-title {  background: var(--md-surface-container-high) !important;  border-color: var(--md-outline-variant) !important;  color: var(--md-on-surface-variant) !important;  border-radius: var(--md-radius-sm) var(--md-radius-sm) 0 0 !important;  transition: background 0.15s !important;}#mirages-dialog .content-tabs .content-tabs-head .content-tab-title:hover {  background: color-mix(in srgb, var(--md-on-surface-variant) 8%, var(--md-surface-container-high)) !important;  color: var(--md-on-surface) !important;}#mirages-dialog .content-tabs .content-tabs-head .content-tab-title.selected {  background: var(--md-secondary-container) !important;  border-bottom-color: var(--md-secondary-container) !important;  color: var(--md-on-secondary-container) !important;}#mirages-dialog .content-tabs .content-tabs-body {  background: var(--md-surface-container) !important;  border-color: var(--md-outline-variant) !important;  color: var(--md-on-surface) !important;}[data-theme="dark"] #mirages-dialog .wmd-prompt-dialog {  background: #2d2d31 !important;}[data-theme="dark"] #mirages-dialog .content-tabs .content-tabs-head .content-tab-title {  background: #302d36 !important;  border-color: rgba(202,196,208,.22) !important;}[data-theme="dark"] #mirages-dialog .content-tabs .content-tabs-body {  background: #2d2d31 !important;  border-color: rgba(202,196,208,.22) !important;}[data-theme="dark"] #mirages-dialog input,[data-theme="dark"] #mirages-dialog textarea,[data-theme="dark"] #mirages-dialog select {  background: #1c1b1f !important;  border-color: rgba(202,196,208,.28) !important;  color: #e6e1e5 !important;}#mirages-dialog .wmd-prompt-background {  z-index: 1150 !important;}#mirages-dialog .wmd-prompt-container {  z-index: 1151 !important;}';var FA_TO_MATERIAL={'fa-video-camera':'videocam','fa-film':'movie','fa-youtube-play':'play_circle','fa-music':'music_note','fa-code':'code','fa-code-fork':'call_split','fa-terminal':'terminal','fa-link':'link','fa-chain':'link','fa-external-link':'open_in_new','fa-picture-o':'image','fa-photo':'image','fa-image':'image','fa-file-image-o':'image','fa-table':'table_chart','fa-list-ul':'format_list_bulleted','fa-list-ol':'format_list_numbered','fa-list':'list','fa-th':'grid_view','fa-th-list':'view_list','fa-columns':'view_column','fa-bold':'format_bold','fa-italic':'format_italic','fa-underline':'format_underlined','fa-strikethrough':'format_strikethrough','fa-subscript':'subscript','fa-superscript':'superscript','fa-quote-left':'format_quote','fa-quote-right':'format_quote','fa-align-left':'format_align_left','fa-align-center':'format_align_center','fa-align-right':'format_align_right','fa-align-justify':'format_align_justify','fa-text-height':'text_fields','fa-header':'title','fa-paragraph':'notes','fa-minus':'horizontal_rule','fa-arrows-h':'swap_horiz','fa-arrows-v':'swap_vert','fa-crop':'crop','fa-magic':'auto_fix_high','fa-pencil':'edit','fa-edit':'edit','fa-pencil-square-o':'edit','fa-eraser':'backspace','fa-scissors':'content_cut','fa-cut':'content_cut','fa-copy':'content_copy','fa-paste':'content_paste','fa-files-o':'content_copy','fa-save':'save','fa-floppy-o':'save','fa-upload':'upload','fa-download':'download','fa-cloud-upload':'cloud_upload','fa-cloud-download':'cloud_download','fa-paperclip':'attach_file','fa-folder':'folder','fa-folder-o':'folder','fa-folder-open':'folder_open','fa-file':'insert_drive_file','fa-file-o':'insert_drive_file','fa-file-text':'article','fa-file-text-o':'article','fa-file-code-o':'code','fa-archive':'archive','fa-envelope':'email','fa-envelope-o':'email','fa-flag':'flag','fa-flag-o':'outlined_flag','fa-tag':'local_offer','fa-tags':'local_offer','fa-bookmark':'bookmark','fa-bookmark-o':'bookmark_border','fa-star':'star','fa-star-o':'star_border','fa-heart':'favorite','fa-heart-o':'favorite_border','fa-thumbs-up':'thumb_up','fa-thumbs-o-up':'thumb_up','fa-thumbs-down':'thumb_down','fa-thumbs-o-down':'thumb_down','fa-comment':'chat_bubble','fa-comment-o':'chat_bubble_outline','fa-comments':'forum','fa-comments-o':'forum','fa-info':'info','fa-info-circle':'info','fa-question':'help','fa-question-circle':'help','fa-exclamation':'warning','fa-exclamation-circle':'error','fa-exclamation-triangle':'warning','fa-check':'check','fa-check-circle':'check_circle','fa-times':'close','fa-times-circle':'cancel','fa-ban':'block','fa-search':'search','fa-filter':'filter_list','fa-sort':'sort','fa-plus':'add','fa-plus-circle':'add_circle','fa-minus-circle':'remove_circle','fa-arrow-up':'arrow_upward','fa-arrow-down':'arrow_downward','fa-arrow-left':'arrow_back','fa-arrow-right':'arrow_forward','fa-caret-up':'expand_less','fa-caret-down':'expand_more','fa-chevron-up':'expand_less','fa-chevron-down':'expand_more','fa-refresh':'refresh','fa-repeat':'repeat','fa-undo':'undo','fa-share':'share','fa-print':'print','fa-rss':'rss_feed','fa-feed':'rss_feed','fa-globe':'language','fa-map-marker':'place','fa-location-arrow':'navigation','fa-home':'home','fa-user':'person','fa-users':'group','fa-group':'group','fa-lock':'lock','fa-unlock':'lock_open','fa-key':'key','fa-eye':'visibility','fa-eye-slash':'visibility_off','fa-wrench':'settings','fa-cog':'settings','fa-cogs':'settings','fa-gears':'settings','fa-tasks':'checklist','fa-bars':'menu','fa-navicon':'menu','fa-reorder':'reorder','fa-ellipsis-h':'more_horiz','fa-ellipsis-v':'more_vert','fa-smile-o':'sentiment_satisfied','fa-binoculars':'pageview','fa-puzzle-piece':'extension','fa-plug':'power','fa-paint-brush':'brush','fa-palette':'palette','fa-font':'font_download','fa-text-width':'text_fields','fa-indent':'format_indent_increase','fa-dedent':'format_indent_decrease','fa-outdent':'format_indent_decrease','fa-rotate-left':'rotate_left','fa-rotate-right':'rotate_right','fa-clone':'content_copy','fa-expand':'open_in_full','fa-compress':'close_fullscreen','fa-arrows-alt':'open_with','fa-long-arrow-right':'east','fa-long-arrow-left':'west','fa-desktop':'desktop_windows','fa-laptop':'laptop','fa-tablet':'tablet','fa-mobile':'phone_iphone','fa-mobile-phone':'phone_iphone','fa-calculator':'calculate','fa-database':'storage','fa-server':'dns','fa-microphone':'mic','fa-volume-up':'volume_up','fa-volume-down':'volume_down','fa-volume-off':'volume_off','fa-gamepad':'sports_esports','fa-th-large':'dashboard','fa-sitemap':'account_tree','fa-exchange':'swap_horiz','fa-random':'shuffle','fa-clock-o':'schedule','fa-calendar':'calendar_today','fa-calendar-o':'calendar_today','fa-history':'history','fa-bell':'notifications','fa-bell-o':'notifications_none','fa-trophy':'emoji_events','fa-road':'route','fa-plane':'flight','fa-ship':'directions_boat','fa-car':'directions_car','fa-train':'train','fa-bus':'directions_bus','fa-bicycle':'pedal_bike','fa-leaf':'eco','fa-tree':'park','fa-sun-o':'wb_sunny','fa-moon-o':'dark_mode','fa-cloud':'cloud','fa-umbrella':'beach_access','fa-coffee':'coffee','fa-cutlery':'restaurant','fa-gift':'card_giftcard','fa-shopping-cart':'shopping_cart','fa-credit-card':'credit_card','fa-money':'payments','fa-bank':'account_balance','fa-graduation-cap':'school','fa-book':'book','fa-newspaper-o':'newspaper','fa-film':'movie','fa-camera':'camera_alt','fa-camera-retro':'camera','fa-paint-brush':'brush','fa-tint':'water_drop','fa-fire':'local_fire_department','fa-bolt':'bolt','fa-flask':'science','fa-recycle':'recycling','fa-shield':'security','fa-lock':'lock','fa-certificate':'verified','fa-check-square':'check_box','fa-check-square-o':'check_box_outline_blank','fa-square':'check_box_outline_blank','fa-square-o':'check_box_outline_blank','fa-circle':'circle','fa-circle-o':'radio_button_unchecked','fa-dot-circle-o':'radio_button_checked','fa-thumb-tack':'push_pin','fa-map-o':'map','fa-compass':'explore','fa-crosshairs':'gps_fixed','fa-paper-plane':'send','fa-paper-plane-o':'send','fa-reply':'reply','fa-reply-all':'reply_all','fa-forward':'forward','fa-step-forward':'skip_next','fa-step-backward':'skip_previous','fa-play':'play_arrow','fa-pause':'pause','fa-stop':'stop','fa-fast-forward':'fast_forward','fa-fast-backward':'fast_rewind','fa-window-maximize':'web_asset','fa-object-group':'select_all','fa-id-card':'badge','fa-id-card-o':'badge','fa-id-badge':'badge','fa-address-card':'contact_page','fa-address-card-o':'contact_page','fa-sticky-note':'sticky_note_2','fa-sticky-note-o':'sticky_note_2','fa-file-zip-o':'folder_zip','fa-file-archive-o':'folder_zip','fa-toggle-on':'toggle_on','fa-toggle-off':'toggle_off','fa-sliders':'tune','fa-hashtag':'tag','fa-at':'alternate_email','fa-qrcode':'qr_code','fa-barcode':'barcode_scanner','fa-sign-in':'login','fa-sign-out':'logout','fa-legal':'gavel','fa-gavel':'gavel','fa-life-ring':'support','fa-life-bouy':'support','fa-anchor':'anchor','fa-paragraph':'segment'};var WMD_STANDARD_IDS=['wmd-bold-button','wmd-italic-button','wmd-link-button','wmd-quote-button','wmd-code-button','wmd-image-button','wmd-olist-button','wmd-ulist-button','wmd-heading-button','wmd-hr-button','wmd-more-button','wmd-undo-button','wmd-redo-button','wmd-fullscreen-button','wmd-exit-fullscreen-button'];function replaceMiragesIcons(){var row=document.getElementById('wmd-button-row');if(!row)return;var btns=row.querySelectorAll('li.wmd-button:not([data-ab-mirages-styled])');var changed=0;for(var i=0;i<btns.length;i++){var btn=btns[i];if(WMD_STANDARD_IDS.indexOf(btn.id)!==-1)continue;if(btn.querySelector('i.material-icons-round'))continue;var faIcon=btn.querySelector('i.fa, i[class*="fa-"]');if(!faIcon)continue;var iconName=null;var classes=faIcon.className.split(/\s+/);for(var j=0;j<classes.length;j++){if(classes[j].indexOf('fa-')===0&&FA_TO_MATERIAL[classes[j]]){iconName=FA_TO_MATERIAL[classes[j]];break}}if(!iconName){btn.setAttribute('data-ab-mirages-styled','1');continue}var newIcon=document.createElement('i');newIcon.className='material-icons-round';newIcon.textContent=iconName;faIcon.parentNode.replaceChild(newIcon,faIcon);btn.setAttribute('data-ab-mirages-styled','1');changed++}if(changed>0){window.dispatchEvent(new Event('resize'))}}function applyWriteFix(){if(!document.getElementById(STYLE_WRITE_ID)){var style=document.createElement('style');style.id=STYLE_WRITE_ID;style.textContent=CSS_WRITE;document.head.appendChild(style)}replaceMiragesIcons();var row=document.getElementById('wmd-button-row');if(row&&!row._abMiragesObserver){var obs=new MutationObserver(function(){replaceMiragesIcons()});obs.observe(row,{childList:true,subtree:true});row._abMiragesObserver=obs}else if(!row){var bodyObs=new MutationObserver(function(){var r=document.getElementById('wmd-button-row');if(r){bodyObs.disconnect();replaceMiragesIcons();if(!r._abMiragesObserver){var obs2=new MutationObserver(replaceMiragesIcons);obs2.observe(r,{childList:true,subtree:true});r._abMiragesObserver=obs2}}});bodyObs.observe(document.body||document.documentElement,{childList:true,subtree:true})}}function applyFix(url){var isSettings=(url||'').indexOf('options-theme.php')!==-1;var isWrite=(url||'').indexOf('write-post.php')!==-1||(url||'').indexOf('write-page.php')!==-1;if(!isSettings){var old=document.getElementById(STYLE_ID);if(old)old.parentNode.removeChild(old)}else{if(!document.getElementById(STYLE_ID)){var style=document.createElement('style');style.id=STYLE_ID;style.textContent=CSS_SETTINGS;document.head.appendChild(style)}}if(!isWrite){var oldW=document.getElementById(STYLE_WRITE_ID);if(oldW)oldW.parentNode.removeChild(oldW)}else{applyWriteFix()}}if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',function(){applyFix(window.location.href)})}else{applyFix(window.location.href)}document.addEventListener('ab:pageload',function(e){var url=(e&&e.detail&&e.detail.url)?e.detail.url:window.location.href;applyFix(url)})})();
