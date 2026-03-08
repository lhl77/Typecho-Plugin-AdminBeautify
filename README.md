![Admin Beautify](https://i.see.you/2026/03/08/Vdo1/de2b4ab0e11757cd146b0d458fcdd55b.jpg)

<h1 align="center">Admin Beautify</h1>

<p align="center">
  <strong>Typecho 后台管理界面美化插件 · Material Design 3 风格</strong>
</p>

<p align="center">
  <a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases"><img src="https://img.shields.io/github/v/release/lhl77/Typecho-Plugin-AdminBeautify?style=flat-square&label=release&color=blue" alt="Latest Release"></a>
  <img src="https://img.shields.io/badge/Typecho->=1.3.0-orange?style=flat-square" alt="Typecho 1.3.0">
  <img src="https://img.shields.io/badge/PHP-%3E%3D7.2-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP >= 7.2">
  <img src="https://img.shields.io/badge/design-Material%20Design%203-6750A4?style=flat-square&logo=materialdesign&logoColor=white" alt="Material Design 3">
  <a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify/stargazers"><img src="https://img.shields.io/github/stars/lhl77/Typecho-Plugin-AdminBeautify?style=flat-square&logo=github" alt="GitHub Stars"></a>
  <a href="https://github.com/lhl77/Typecho-Plugin-AdminBeautify/network/members"><img src="https://img.shields.io/github/forks/lhl77/Typecho-Plugin-AdminBeautify?style=flat-square&logo=github" alt="GitHub Forks"></a>
</p>

<p align="center">
  <a href="#-功能特色">功能特色</a> •
  <a href="#-截图预览">截图预览</a> •
  <a href="#-安装">安装</a> •
  <a href="#-配置说明">配置说明</a> •
  <a href="#-常见问题">常见问题</a>
</p>

---

## ✨ 功能特色

- 🎨 **Material Design 3 风格** — 全面采用 MD3 设计规范，现代感十足
- 🌈 **7 种主题色** — 紫、蓝、青、绿、橙、粉、红，一键切换
- 🌗 **暗色模式** — 支持亮色 / 暗色 / 跟随系统三种模式
- 📐 **圆角风格** — 小 / 中 / 大三档圆角可调
- ✨ **过渡动画** — 丝滑的界面过渡动画，可开关
- 📌 **导航栏位置** — 支持侧边栏（默认）和顶部导航栏两种布局
- 🔐 **登录页美化** — 独立的登录页面美化，支持：
  - 12+ 配色预设方案（紫、蓝、粉、绿、橙、红、青、靛蓝、日落渐变、海洋渐变等）
  - 自定义主色 / 辅色
  - 背景图片 + 可调虚化效果
  - 站点名称显示 / 隐藏
  - 独立的暗色模式控制
  - 自定义 CSS / JS 注入
- ⚡ **AJAX 导航** — 无刷新页面切换，后台操作更流畅
- 📱 **PWA 响应式 APP** — 安装到您的电脑/手机，流畅管理博客 (v2.1.0 加入)
- 🧩 **兼容脚本设计** — 自动修复其他插件的排版和功能 (v2.1.0 加入)

## 📸 截图预览

### 插件管理
![](https://i.see.you/2026/03/07/iV0a/1831ea5eca3e0a41fa8347d03ffc6d67.jpg)

### 个人设置
![](https://i.see.you/2026/03/07/Yj5s/11c5ab37b81fa0bcaf65467a641780f1.jpg)

### 暗色模式
![](https://i.see.you/2026/03/08/pU8v/fb7c26b672cce88776f2fc8c7b35f25b.jpg)

### 顶部导航栏（原版）
![](https://i.see.you/2026/03/07/syL5/52cef9f1d32078f4fd830af98586a0a2.jpg)

### 文章编辑页面
![](https://i.see.you/2026/03/08/pQ9d/5af43bda6f057dfde0d06185e4b6ca53.jpg)

### 文章管理
![](https://i.see.you/2026/03/07/iB0n/1ba8fd34ec09ae6835cedf90a82439f5.jpg)


## 📦 安装

### 方式一：下载压缩包

1. 前往 [GitHub Releases](https://github.com/lhl77/Typecho-Plugin-AdminBeautify/releases) 下载最新版本的压缩包
2. 解压后将文件夹重命名为 `AdminBeautify`
3. 上传至 Typecho 安装目录下的 `/usr/plugins/` 文件夹
4. 登录 Typecho 后台，进入 **控制台** → **插件** 页面
5. 找到 **Admin Beautify** 并点击 **启用**

### 方式二：Git 克隆

```bash
cd /your-site/usr/plugins/
git clone https://github.com/lhl77/Typecho-Plugin-AdminBeautify.git AdminBeautify
```

然后登录 Typecho 后台启用插件即可。

```
your-site/
└── usr/
    └── plugins/
        └── AdminBeautify/
```

## ⚙️ 配置说明

启用插件后，进入 **控制台** → **插件** → **Admin Beautify** → **设置** 即可配置。

### 管理后台设置

| 选项 | 说明 | 默认值 |
|------|------|--------|
| 主题色 | 选择后台主题色方案 | 🟣 紫 |
| 颜色模式 | 亮色 / 暗色 / 跟随系统 | 跟随系统 |
| 圆角风格 | 控制界面元素的圆角大小 | 中圆角 |
| 过渡动画 | 是否开启界面过渡动画 | 开启 |
| 导航栏位置 | 侧边栏或顶部导航栏 | 侧边栏 |

### 登录页设置

| 选项 | 说明 | 默认值 |
|------|------|--------|
| 配色方案 | 12+ 预设方案或自定义颜色 | 🟣 紫 |
| 自定义主色/辅色 | 选择"自定义"时生效 | #7d5260 / #9e7b8a |
| 显示站点名称 | 登录页是否显示站点标题 | 显示 |
| 默认主题 | 登录页的明暗模式 | 跟随系统 |
| 主题切换按钮 | 显示/隐藏主题切换按钮 | 显示 |
| 背景图片 URL | 自定义登录页背景图 | 留空（纯色） |
| 虚化方式 | 背景图模糊效果 | filter: blur |
| 虚化大小 | 模糊程度 (0-50px) | 12px |
| 自定义 CSS | 注入自定义样式 | — |
| 自定义 JS | 注入自定义脚本 | — |

## ❓ 常见问题

**Q: 支持哪些版本的 Typecho？**
> 本插件基于 **Typecho 1.3.0** 设计开发，建议使用该版本或更高版本。

**Q: 启用后后台样式异常怎么办？**
> 请先清除浏览器缓存，确保加载最新的 CSS/JS 文件。如果仍有问题，请检查是否与其他后台美化插件冲突。

**Q: 移动端显示效果如何？**
> 插件内置了响应式设计，移动端会自动切换为顶部折叠菜单模式。

**Q: 如何自定义登录页背景？**
> 在插件设置中填入背景图片的 URL，并可调整虚化方式和虚化大小。

## 🔗 相关链接

- 📖 [作者博客](https://blog.lhl.one/artical/977.html)
- 🐛 [问题反馈](https://github.com/lhl77/Typecho-Plugin-AdminBeautify/issues)
- 🌟 [GitHub 仓库](https://github.com/lhl77/Typecho-Plugin-AdminBeautify)

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/lhl77">LHL</a>
</p>
