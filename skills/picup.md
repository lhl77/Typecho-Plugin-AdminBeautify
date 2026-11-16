---
name: picup
title: PicUp 图片上传插件（完整字段参考）
description: PicUp 全部设置项、18 种存储驱动的完整字段名与默认值、4 个扩展、configJson 结构、排错
keywords: PicUp, 图片上传, 上传图片, 图床, 附件, 媒体库, configJson, 存储驱动
---

# PicUp 图片上传插件

> 本文档字段名全部从 PicUp 源码 `vendor/*Driver.php` 的 `getConfigFields()` 提取，可直接用于 `update_plugin_config`。

## 一、顶层设置项（`plugin:PicUp` 的键）

| 键名 | 类型 | 默认值 | 说明 |
|---|---|---|---|
| `defaultProfile` | text | `default` | 当前使用的存储方案名，**必须与 `configJson` 里的 key 一致** |
| `storagePathTemplate` | text | `{year}/{month}/` | 文件路径模板（**全局**，不在方案里） |
| `mimeScope` | radio | `image` | `image` 只接管图片 / `all` 接管所有附件 |
| `suffixProfiles` | textarea(JSON) | `{}` | 按扩展名分流到不同方案，如 `{"gif,webp":"upyun"}` |
| `configJson` | textarea(JSON) | 见下 | **核心**：所有存储方案 + 各方案的扩展 |
| `abPromoHidden` | hidden text | `0` | AB Admin 分享开关（隐藏字段，`0` / `1`） |

> ⚠️ 这几个是**独立设置项**，扩展配置**不在**它们里面，而在 `configJson` 每个方案的 `_extensions` 里。


### storagePathTemplate 可用变量

| 变量 | 含义 |
|---|---|
| `{year}` | 4 位年份，如 `2026` |
| `{month}` | 2 位月份，如 `11` |
| `{day}` | 2 位日期，如 `16` |
| `{md5}` | 文件内容的 MD5（32 位） |
| `{random}` | 随机字符串 |
| `{random-N}` | N 位随机字符串，如 `{random-8}` |

示例：`{year}/{month}/{random-8}/`、`{year}/{month}/{md5}`

## 二、configJson 结构（⚠️ 层级只有两层）

```
configJson = {
  "<方案名>": {              ← 第一层：方案（任意字符串）
    "driver": "<驱动标识>",    ← 必填，取值见第三节
    "<该驱动的字段>": "...",   ← 第二层：驱动字段直接平铺
    "_extensions": {         ← 可选，**必须在这里（方案内部）**，不是顶层
      "<扩展标识>": { "enabled": true, "<扩展字段>": "..." }
    }
  },
  "<另一个方案名>": { ... }
}
```

**最小可用配置**（就是插件里的默认模板 `buildConfigTemplate()`）：

```json
{
  "default": {
    "driver": "local",
    "uploadDir": "usr/uploads",
    "urlPrefix": ""
  }
}
```

**带扩展的完整示例**：

```json
{
  "default": {
    "driver": "local",
    "uploadDir": "usr/uploads",
    "urlPrefix": "",
    "_extensions": {
      "compress": { "enabled": true, "quality": "80" },
      "webp": { "enabled": true, "driver": "gd", "quality": "85" }
    }
  },
  "upyun": {
    "driver": "upyun",
    "service": "mybucket",
    "operator": "op",
    "password": "xxx",
    "prefix": "blog/",
    "urlPrefix": "https://cdn.example.com",
    "_extensions": {
      "watermark": {
        "enabled": true,
        "type": "text",
        "text": "© example.com",
        "position": "bottom-right",
        "opacity": "80"
      }
    }
  }
}
```

### ⚠️ 三个必须记住的点

1. **`_extensions` 在方案内部，且键名带下划线前缀**。服务端 `applyExtensions()` 只读
   `$profileConfig['_extensions']`；写成顶层 `{"extensions": {...}}` **会被静默忽略**
   （JSON 能存进去、后台不报错、但压缩/水印/WebP 完全不生效）。
2. **扩展是「按方案」独立配置的**，不同方案可以有不同扩展组合。
3. 顶层只有方案名这一个层级；`driver` 是方案对象的必填键，驱动字段与 `driver`
   **同级平铺**，不存在 `{"driver":"s3","config":{...}}` 这种嵌套。

### 常见错误写法（都会被静默忽略或报错）

| 错误写法 | 后果 |
|---|---|
| `{"extensions": {...}}` 放在顶层 | 扩展不生效，`getActiveConfig` 正常，无任何报错 |
| `{"default": {"driver":"local","config":{"uploadDir":"..."}}}` | 驱动拿不到字段，回退默认值 |
| `{"default": {"type":"local"}}` | `driver` 缺失 → `getDriver: 配置中缺少 driver 字段` |
| `defaultProfile` 指向不存在的 key | `getDriver: 未找到有效配置` |
| 用单引号 / 尾逗号 | `configJson 解析失败（JSON 格式错误）` |

`suffixProfiles` 是**另一个独立设置项**（不在 configJson 里），格式
`{"jpg,jpeg,png": "方案名", "gif,webp": "另一个方案名"}`。

## 三、18 种存储驱动完整字段

驱动标识（`driver` 的值）= `vendor/` 下文件名去掉 `Driver.php` 后**转小写**，
插件用 `glob('vendor/*Driver.php')` 自动扫描（所以加驱动文件即生效）：

```
LocalDriver.php → local      S3Driver.php → s3
AliyunOssDriver.php → aliyunoss    TencentCosDriver.php → tencentcos
QiniuKodoDriver.php → qiniukodo    UpyunDriver.php → upyun
WebDavDriver.php → webdav          GithubDriver.php → github
ImgurDriver.php → imgur            SmmsDriver.php → smms
LskyDriver.php → lsky              CheveretoV4Driver.php → cheveretov4
EasyimageDriver.php → easyimage    CfimgbedDriver.php → cfimgbed
NodeimageDriver.php → nodeimage    OneimgDriver.php → oneimg
TgImagebedDriver.php → tgimagebed  ZpicDriver.php → zpic
```

### `local` 本地存储

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `uploadDir` | text | `usr/uploads` | 否 | 相对站点根目录的上传路径 |
| `urlPrefix` | text | `''` | 否 | 访问 URL 前缀，留空用站点地址 |

### `s3` Amazon S3 / 兼容对象存储

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `endpoint` | text | `''` | 是 | Endpoint 地址 |
| `region` | text | `us-east-1` | 是 | 区域 |
| `bucket` | text | `''` | 是 | 存储桶 |
| `accessKey` | text | `''` | 是 | Access Key |
| `secretKey` | password | `''` | 是 | Secret Key |
| `pathStyle` | select | `auto` | 否 | `auto` 自动 / `path` 路径风格 / `virtual` 虚拟主机 |
| `urlPrefix` | text | `''` | 否 | 自定义访问域名 |
| `prefix` | text | `''` | 否 | 存储路径前缀 |

### `aliyunoss` 阿里云 OSS

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `endpoint` | text | `oss-cn-hangzhou.aliyuncs.com` | 是 | 地域 Endpoint（不含 Bucket、不含协议头） |
| `bucket` | text | `''` | 是 | Bucket 名称 |
| `accessKeyId` | text | `''` | 是 | AccessKeyId |
| `accessKeySecret` | password | `''` | 是 | AccessKeySecret |
| `prefix` | text | `''` | 否 | 存储路径前缀 |
| `urlPrefix` | text | `''` | 否 | 访问域名（CDN / 自定义） |

### `tencentcos` 腾讯云 COS

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `region` | text | `ap-guangzhou` | 是 | 地域 |
| `bucket` | text | `''` | 是 | Bucket 名称（含 AppId） |
| `secretId` | text | `''` | 是 | SecretId |
| `secretKey` | password | `''` | 是 | SecretKey |
| `prefix` | text | `''` | 否 | 存储路径前缀 |
| `urlPrefix` | text | `''` | 否 | 访问域名（CDN / 自定义） |

### `qiniukodo` 七牛云 Kodo

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `accessKey` | text | `''` | 是 | Access Key |
| `secretKey` | password | `''` | 是 | Secret Key |
| `bucket` | text | `''` | 是 | Bucket 名称 |
| `zone` | select | `z0` | 是 | `z0` 华东 / `z1` 华北 / `z2` 华南 / `na0` 北美 / `as0` 东南亚 / `cn-east-2` 华东-浙江2 |
| `prefix` | text | `''` | 否 | 存储路径前缀 |
| `urlPrefix` | text | `''` | 是 | 访问域名 |
| `imageSuffix` | text | `''` | 否 | 图片处理后缀，如 `?imageView2/2/w/800` |

### `upyun` 又拍云

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `service` | text | `''` | 是 | 服务名（Bucket） |
| `operator` | text | `''` | 是 | 操作员名 |
| `password` | password | `''` | 是 | 操作员密码 |
| `prefix` | text | `''` | 否 | 存储路径前缀 |
| `urlPrefix` | text | `''` | 是 | 访问域名 |

### `webdav` WebDAV

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `endpoint` | text | `''` | 是 | WebDAV 地址 |
| `username` | text | `''` | 是 | 用户名 |
| `password` | password | `''` | 是 | 密码 |
| `basePath` | text | `''` | 否 | 存储基础路径 |
| `urlPrefix` | text | `''` | 是 | 公开访问地址前缀 |
| `autoMkdir` | select | `1` | 否 | `1` 自动创建目录 / `0` 不创建 |

### `github` GitHub 仓库

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `token` | password | `''` | 是 | PAT，需 Contents 读写权限 |
| `repo` | text | `''` | 是 | `owner/repo` |
| `branch` | text | `main` | 否 | 目标分支 |
| `prefix` | text | `''` | 否 | 仓库内目录前缀 |
| `cdn` | text | `''` | 否 | CDN / 访问域名，留空用 raw.githubusercontent.com |

### `imgur` Imgur

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `client_id` | password | `''` | 是 | Client ID |
| `access_token` | password | `''` | 否 | 留空为匿名上传 |
| `album_hash` | text | `''` | 否 | 相册 deletehash |
| `cdn` | text | `''` | 否 | 替换 `https://i.imgur.com` 的 CDN 域名 |

### `smms` SM.MS

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `token` | password | `''` | 是 | API Token |

### `lsky` Lsky Pro

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `server` | text | `''` | 是 | 图床地址（末尾不加 /） |
| `token` | password | `''` | 是 | API Token（不含 Bearer 前缀） |
| `strategy_id` | number | `''` | 否 | 储存策略 ID，留空用默认 |
| `album_id` | number | `''` | 否 | 相册 ID（仅 V2） |
| `api_version` | select | `v1` | 是 | `v1` Lsky 1.x / `v2` Lsky 2.x |

### `cheveretov4` Chevereto V4

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `server` | text | `''` | 是 | 站点地址（末尾不加 /） |
| `api_key` | password | `''` | 是 | Dashboard → API 的 API v1 Key |
| `album_id` | text | `''` | 否 | 相册 ID |

### `easyimage` EasyImage

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `domain` | text | `''` | 是 | 图床域名（末尾不加 /） |
| `token` | password | `''` | 是 | 后台 tokenList 中的 Token |

### `cfimgbed` CloudFlare ImgBed

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `domain` | text | `''` | 是 | 部署域名（末尾不加 /） |
| `token` | password | `''` | 否 | 具有 upload/delete 权限的 API Token |
| `authCode` | password | `''` | 否 | 上传认证码；与 token 都填时优先 authCode |
| `uploadChannel` | select | `telegram` | 否 | `telegram` / `cfr2` Cloudflare R2 / `s3` / `discord` / `huggingface` |
| `uploadFolder` | text | `''` | 否 | 上传目录 |

### `nodeimage` NodeImage

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `api_key` | password | `''` | 是 | API Key |

### `oneimg` OneImg

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `server` | text | `''` | 是 | 图床地址 |
| `token` | password | `''` | 是 | Bearer Token |
| `bucket_id` | number | `''` | 否 | 存储桶 ID |
| `url_prefix` | text | `''` | 否 | 自定义 URL 前缀 |

### `tgimagebed` Telegram 图床

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `server` | text | `''` | 是 | 图床地址 |
| `token` | password | `''` | 否 | Token |

### `zpic` Zpic / ImgURL Pro

| 字段 | 类型 | 默认 | 必填 | 说明 |
|---|---|---|---|---|
| `server` | text | `''` | 是 | 图床域名 |
| `api_version` | select | `v3` | 是 | `v3` Bearer Token（推荐）/ `v2` uid + token |
| `token` | password | `''` | 是 | Token / API Key |
| `uid` | text | `''` | 否 | UID（仅 V2） |
| `album_id` | number | `0` | 否 | 相册 ID |

## 四、4 个扩展（写在方案的 `_extensions` 里）

> 键名是 **`_extensions`（带下划线）**，位置在**方案对象内部**，不是 configJson 顶层。
> 未启用的扩展不必写；`enabled` 写 `true`（布尔）或 `"true"` 都行，
> 服务端判定是 `!empty($conf['enabled']) && $conf['enabled'] !== 'false'`。

```json
{
  "default": {
    "driver": "local",
    "uploadDir": "usr/uploads",
    "urlPrefix": "",
    "_extensions": {
      "compress":  { "enabled": true, "quality": "80" },
      "watermark": { "enabled": true, "type": "text", "text": "© example.com" }
    }
  }
}
```

执行顺序由各扩展的 `getOrder()` 决定（不受 JSON 里书写顺序影响）。
扩展不可用时（缺 PHP 扩展）会被自动跳过，不报错。

### `compress` 图片压缩

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| `enabled` | bool | `false` | 是否启用 |
| `quality` | number | `"80"` | 压缩质量 1–100 |

### `watermark` 水印

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| `enabled` | bool | `false` | 是否启用 |
| `type` | select | `text` | `text` 文字水印 / `image` 图片水印 |
| `text` | text | `''` | 水印文字 |
| `font_size` | number | `"16"` | 字体大小（px） |
| `font_color` | text | `#ffffff` | 十六进制颜色 |
| `font_path` | text | `''` | TTF/OTF 绝对路径；留空用内置 NotoSansCJK，失败降级 GD 内置字体（不支持中文） |
| `opacity` | number | `"80"` | 透明度 0–100 |
| `position` | select | `bottom-right` | `top-left` / `top-right` / `bottom-left` / `bottom-right` / `center` |
| `margin` | number | `"10"` | 边距（px） |
| `image_path` | text | `''` | 水印图片绝对路径（`type=image` 时用） |
| `image_scale` | number | `"20"` | 水印图片宽度占原图百分比 |

### `webp` WebP 转换

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| `enabled` | bool | `false` | 是否启用 |
| `driver` | select | 首个可用 | `gd` / `imagick`（需对应扩展支持 WebP） |
| `quality` | number | `"85"` | 转换质量 1–100 |

### `avif` AVIF 转换

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| `enabled` | bool | `false` | 是否启用 |
| `driver` | select | 首个可用 | `gd` / `imagick`（需对应扩展支持 AVIF） |
| `quality` | number | `"60"` | 转换质量 1–100 |

## 五、上传接口

```
POST /index.php/action/upload
Content-Type: multipart/form-data
字段：file
```

**返回格式（Typecho 原生）**：

```json
[ "https://example.com/usr/uploads/2026/11/xxx.png", { "name": "xxx.png", "type": "image/png", "size": 12345 } ]
```

⚠️ 是**数组** `[url, meta]`，不是 `{url: ...}`。前端解析时两种都要兼容。

## 六、排错

| 现象 | 原因 | 处理 |
|---|---|---|
| 上传 413 | nginx `client_max_body_size` 太小 | 调大该值并 reload |
| 上传成功但 404 | `urlPrefix` 配置错，或 CDN 未回源 | 检查 `configJson` 对应方案的 `urlPrefix` |
| `getDriver: 未找到有效配置` | `defaultProfile` 与 `configJson` 的 key 不匹配 | 让两者一致 |
| `configJson 解析失败` | JSON 语法错误（多余逗号、单引号） | 用 JSON 校验器检查 |
| **压缩/水印/WebP 不生效** | 写成了顶层 `"extensions"`，或键名写成 `extensions`（缺下划线） | 改成**方案内部**的 `"_extensions"` |
| 扩展写了但没反应 | `enabled` 缺失或为 `false`；或服务器缺 gd/imagick | 补 `"enabled": true`；查 `php -m` |
| 粘贴上传无反应 | 只在文章编辑页生效，且需浏览器允许剪贴板 | 换用 AI 面板的图片按钮 |
| 权限错误 | `usr/uploads/` 不可写 | `chmod 755` 并确认属主 |

## 七、AI 能做什么

- `get_plugin_config(directory="PicUp")` 读取当前配置
- `update_plugin_config(directory="PicUp", key="configJson", value="...")` 修改配置
  - `value` 必须是**完整的两层 JSON 字符串**（方案名 → 方案对象），不是片段；
    扩展一定要写在**方案对象**的 `_extensions` 里
  - ⚠️ **`configJson` 里的密钥不会被脱敏**（脱敏只作用于顶层键名），
    所以读到的 `"accessKeySecret"` / `"token"` 等都是明文 ——
    **不要复述、不要写进回复**；回写时要原样带上这些字段，不能丢
  - `update_plugin_config` 会**拒绝** key 名里含 `key|secret|token|password|passwd`
    的设置项，因此 `configJson`（名字不含这些词）**可以改**，
    但改完后其中的密钥仍需用户自己确认是否正确
  - 改完 `defaultProfile` 时，一定确认它指向的 key 真实存在
- `upload_image` 上传图片拿 URL，再用 `edit_editor_content` 插入正文
- **不能**把驱动密钥（`token` / `secretKey` / `accessKey` / `password` / `api_key`）
  当成独立设置项去改 —— PicUp 没有这些顶层项，它们只存在于 `configJson` 里
