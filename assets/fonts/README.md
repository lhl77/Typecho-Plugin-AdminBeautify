# 本地字体文件目录

选择「本地文件」资源加载方式时，插件默认从此目录加载字体和图标 CSS。

## 需要的文件

| 文件名 | 说明 |
|--------|------|
| `NotoSansSC.css` | Noto Sans SC 字体样式表（含字体文件引用） |
| `MaterialIconsRound.css` | Material Icons Round 图标字体样式表 |
| `*.woff2` / `*.woff` | 对应的字体二进制文件（与 CSS 相对路径保持一致） |

## 获取方式

### 方法一：从 npm 包提取（推荐）

```bash
# 安装包
npm install @fontsource/noto-sans-sc material-icons

# 复制所需文件
cp node_modules/@fontsource/noto-sans-sc/index.css ./NotoSansSC.css
cp -r node_modules/@fontsource/noto-sans-sc/files ./files

cp node_modules/material-icons/iconfont/round.css ./MaterialIconsRound.css
cp node_modules/material-icons/iconfont/MaterialIconsRound* ./
```

### 方法二：从 jsDelivr 下载

- 字体：https://cdn.jsdelivr.net/npm/@fontsource/noto-sans-sc@5/index.css
- 图标：https://cdn.jsdelivr.net/npm/material-icons@1/iconfont/round.css

下载 CSS 后，同步修改 CSS 内的 `url(...)` 路径指向本地字体文件。

## 自定义路径

如果你将文件放在其他位置，可以在插件设置 → 速度优化中填写「本地字体 CSS 路径」和「本地图标 CSS 路径」（相对于网站根目录的 URL 路径，以 `/` 开头）。
