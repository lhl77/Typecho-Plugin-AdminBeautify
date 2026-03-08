# AdminBeautify 兼容性脚本目录

此目录存放用于兼容其他 Typecho 插件的 JS 脚本文件。

## 工作原理

- AdminBeautify 会自动扫描本目录下的所有 `.js` 文件
- 每个脚本的 **元数据**（名称、简介、适用插件等）将展示在 AdminBeautify 设置页面中
- 用户可在设置页面中 **单独启用/禁用** 每个脚本
- 每个 JS 文件应自行判断当前页面是否需要执行（通过 URL、DOM 等），避免影响其他页面
- 脚本在每次页面加载时执行一次（含 AdminBeautify **AJAX 导航**，见下文）
- 用户还可以在设置面板中添加 **外部 JS 文件链接** 来加载额外的兼容脚本

## 元数据格式（必须）

每个 JS 文件的 **头部注释** 中必须包含以下 `@tag` 元数据，AdminBeautify 会解析并展示：

```javascript
/**
 * @name        脚本名称
 * @description 脚本简介说明
 * @plugins     Plugin1, Plugin2
 * @version     1.0.0
 * @author      作者名
 */
```

| 标签 | 必填 | 说明 |
|------|------|------|
| `@name` | 推荐 | 脚本名称（不填则使用文件名） |
| `@description` | 推荐 | 一句话功能说明 |
| `@plugins` | 推荐 | 适用的插件名称，多个用逗号分隔 |
| `@version` | 可选 | 版本号 |
| `@author` | 可选 | 作者 |

## 脚本结构

### 基础模板（仅首次加载）

```javascript
/**
 * @name        MyPlugin 兼容
 * @description 修复 MyPlugin 在 AdminBeautify 下的排版问题
 * @plugins     MyPlugin
 * @version     1.0.0
 * @author      YourName
 */
(function () {
    'use strict';

    // 1. 判断当前页面是否需要执行（务必加此判断）
    var url = window.location.href;
    if (url.indexOf('panel=MyPlugin') === -1) return;

    // 2. 等待 DOM 就绪后执行修复
    function applyFix() {
        var style = document.createElement('style');
        style.id = 'ab-compat-myplugin';
        style.textContent = '/* your CSS fixes */';
        document.head.appendChild(style);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyFix);
    } else {
        applyFix();
    }
})();
```

### AJAX 导航模板（推荐）

AdminBeautify 使用 AJAX 进行后台页面导航，不会完整重载页面。脚本只在首次页面加载时执行一次，若不处理 `ab:pageload` 事件，从其他页面 AJAX 跳转过来时修复将不会生效。

**`ab:pageload` 事件说明：**

| 属性 | 说明 |
|------|------|
| 触发时机 | `history.pushState` 之后，`document.title` 和 DOM 主内容区已完成替换 |
| `e.detail.url` | 导航目标的完整 URL |
| 监听方式 | `document.addEventListener('ab:pageload', fn)` |

> **注意**：`ab:pageload` 在每次 AJAX 导航后均会触发，包括 **离开** 目标页面时。修复函数应通过 URL 判断当前是否处于目标页面，并在离开时清理已注入的样式（保持幂等）。

```javascript
/**
 * @name        MyPlugin 兼容
 * @description 修复 MyPlugin 在 AdminBeautify 下的排版问题，已支持 AJAX 导航（ab:pageload）
 * @plugins     MyPlugin
 * @version     1.0.0
 * @author      YourName
 */
(function () {
    'use strict';

    var STYLE_ID = 'ab-compat-myplugin';

    // ── 核心修复函数（幂等，可重复调用）
    function applyFix(url) {
        var isTarget = (url || '').indexOf('panel=MyPlugin') !== -1;

        if (!isTarget) {
            // 离开目标页面时清理已注入的样式
            var old = document.getElementById(STYLE_ID);
            if (old) old.remove();
            return;
        }

        // 注入修复样式（幂等：已存在则跳过）
        if (!document.getElementById(STYLE_ID)) {
            var style = document.createElement('style');
            style.id = STYLE_ID;
            style.textContent = '/* your CSS fixes */';
            document.head.appendChild(style);
        }

        // 其他 DOM 修复操作...
    }

    // ── 初始执行（首次页面加载）
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            applyFix(window.location.href);
        });
    } else {
        applyFix(window.location.href);
    }

    // ── 监听 AdminBeautify AJAX 导航事件
    // e.detail.url 是导航目标 URL；此时 document.title 和 DOM 主内容区已替换完毕。
    document.addEventListener('ab:pageload', function (e) {
        var url = (e && e.detail && e.detail.url) ? e.detail.url : window.location.href;
        applyFix(url);
    });
})();
```

## 注意事项

1. **必须判断页面**：每个脚本必须先检测当前 URL 或 DOM，确定是目标页面才执行
2. **支持 AJAX 导航**：监听 `ab:pageload` 事件以响应 AdminBeautify 的 AJAX 页面切换（推荐）
3. **保持幂等**：修复函数可能被多次调用（首次加载 + 每次 AJAX 导航），需避免重复注入；离开目标页面时应清理已注入的样式
4. **使用 IIFE**：用 `(function(){ ... })()` 包裹，避免全局变量污染
5. **使用 `'use strict'`**：启用严格模式
6. **兼容暗色模式**：如有样式修复，注意处理 `[data-theme="dark"]`
7. **可用 CSS 变量**：AdminBeautify 已定义大量 MD3 CSS 变量，如 `--md-primary`、`--md-surface` 等

## 内置兼容脚本

| 文件 | 适用插件 | 说明 |
|------|----------|------|
| `Notice.js` | [Notice](https://github.com/Barcodehn/typecho-plugin-notice) | 修复「编辑邮件模版」页面文本框过窄、侧边栏博客名称前多余 `-` 前缀；已支持 AJAX 导航（`ab:pageload`） |
| `Links.js` | [Links Plus](https://github.com/links-plus/links-plus) | ① 亮色模式：恢复被插件 `:root {}` 覆盖的主题色变量，使按钮颜色跟随 AdminBeautify 所选主题色；② 暗色模式：修复 AppBar 标题、按钮文字、操作栏白色渐变背景、状态标签、检查结果高亮行等硬编码亮色值；已支持 AJAX 导航（`ab:pageload`） |
| `TelegramNotice.js` | [TelegramNotice](https://github.com/lhl77/Typecho-Plugin-TelegramNotice) | 暗色模式：修复插件设置页及「Telegram 文章推送」独立面板中 `.tg-card`、`.tg-stickybar`、`.tg-badge`、`.tg-danger` 等自定义组件的硬编码亮色背景与文字颜色；移动端响应式适配（768/575/560/420px 四档断点）；已支持 AJAX 导航（`ab:pageload`） |
| `TeStore.js` | [TeStore](https://www.yzmb.me/archives/net/testore-for-typecho) | 修复插件仓库市场页中内联 `.error` 标签异常块化、操作列按钮溢出、底部 `.notice` 链接样式错乱、暗色模式 SVG 图标不可见等问题；拦截 `te-store/` 路由链接的 AJAX 导航（强制整页刷新，避免页面重复渲染）；已支持 AJAX 导航（`ab:pageload`） |

