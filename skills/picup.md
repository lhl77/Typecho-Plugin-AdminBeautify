---
name: picup
title: PicUp 图片上传插件（完整字段参考）
description: PicUp 全部设置项、18 种存储驱动的完整字段名与默认值、5 个扩展、configJson 结构、排错
keywords: PicUp, 图片上传, 上传图片, 图床, 附件, 媒体库, configJson, 存储驱动
---

# PicUp 图片上传插件

> 本文档字段名全部从 PicUp 源码 `vendor/*Driver.php` 的 `getConfigFields()` 提取，
> 加/改方案请用 `patch_plugin_json`（局部合并），不要用 `update_plugin_config` 整段覆盖 `configJson`。

## 一、顶层设置项（`plugin:PicUp` 的键）

| 键名 | 类型 | 默认值 | 说明 |
|---|---|---|---|
| `defaultProfile` | text | `default` | 当前使用的存储方案名，**必须与 `configJson` 里的 key 一致** |
| `storagePathTemplate` | text | `{year}/{month}/` | 文件路径模板（**全局**，不在方案里） |
| `mimeScope` | radio | `image` | `image` 只接管图片 / `all` 接管所有附件 |
| `suffixProfiles` | textarea(JSON) | `{}` | 按扩展名分流到不同方案，如 `{"gif,webp":"upyun"}` |
| `backupProfiles` | textarea(JSON) | `[]` | **自动备份**：主方案上传后再传到这些方案，如 `["bak","upyun"]`（勾选即生效，主方案会自动跳过） |
| `configJson` | textarea(JSON) | 见下 | **核心**：所有存储方案 + 各方案的扩展 |
| `abPromoHidden` | hidden text | `0` | AB Admin 分享开关（隐藏字段，`0` / `1`） |

> ⚠️ 这几个是**独立设置项**，扩展配置**不在**它们里面，而在 `configJson` 每个方案的 `_extensions` 里。

## 一·五、自动备份 🅱 Beta（旧名「多方案备份」，`backupProfiles` + `PicUpLinks` 表）

> 折叠头带 `.picup-beta` 徽标；与「迁移与替换」一并在设置页标为**测试版**。

- `backupProfiles` = 备份方案名数组（JSON 字符串）。主上传成功后，用**同一份已处理文件**
  再向每个备份方案传一次；主方案同名会被跳过。
- ⚠️ **备份是异步的**：`uploadHandle()` 只把任务排进 `Plugin::$backupQueue`，
  由 `register_shutdown_function('flushBackupQueue')` 在**响应发出之后**执行
  （php-fpm 走 `fastcgi_finish_request()`，其它 SAPI 退化为 flush + `ignore_user_abort`）。
  所以：上传接口返回 ≠ 备份已完成；刚传完就查 `picup_list_backup_records` 可能还看不到那条记录，
  稍等几秒再查（或直接告诉用户「备份在后台进行」）。
- 依赖数据表 **`{prefix}PicUpLinks`**（`id, filename, primary_profile, primary_url, links, created_at, cid`）。
  从旧版升级上来的站点不会自动建表——用户在设置页点「开启自动备份」才建；表不存在时
  备份静默跳过并写 error_log，**不影响主上传**。
  三种数据库均已适配：MySQL 用 `information_schema` 判存在（不用 `SHOW TABLES LIKE`，`_` 是通配符会误判）、
  PgSQL 建表**不加引号**（Query Builder 生成的是未加引号的 `typecho_PicUpLinks`，PG 会折叠成小写）。
- **附件 ↔ 记录只按 URL 匹配**（`findRowForAttachment()`）：附件当前 `path` 必须命中
  `links` 里某条链接或 `primary_url`。**不再按文件名回退** —— `image.png` 这类同名文件会把
  别人的记录挂过来，出现「没开自动备份却已有备份链接」。命中后回填 `cid`；未命中但库里有
  `cid` 相同的历史记录时，说明是旧版按文件名错绑的，顺手解绑（`cid=0`）。
- `links` 字段是 `{"方案名":"链接"}` 的 JSON（含主方案）。查询接口：
  `/action/picup-backup`，`do=` `links-status` / `links-create` / `links-profiles`（保存勾选）/ `links-list`（`q` 搜索文件名、方案名、链接）。

## 一·六、迁移与替换 🅱 Beta（`migrate-*` / `file-*`）

- **批量替换**（设置页「迁移与替换」）：源方案 → 目标方案，同时改写
  ① `PicUpLinks.primary_profile/primary_url` ② `contents.text` 里附件的 `path` ③ 文章正文中的旧 URL
  （**两种形式都要换**：明文 `http://…` 与 JSON 转义 `http:\/\/…`）。目标方案没对应链接的记录跳过。
- **单文件迁移**（媒体详情页 `admin/media.php?cid=X`）：**先备份、再替换** ——
  目标方案已有链接就直接用，没有就 `downloadToTemp()`（本站 URL 直接读磁盘，否则 curl）→
  `Plugin::uploadFileToProfile()` 上传 → 再把附件 `path` 与正文引用改成新链接。
  **迁移前会先把当前链接记到它所属方案名下**（`guessProfileForUrl()`），所以 `default → bak`
  之后 `links` 里两条都在，可在详情页一键「设为当前」切回。
- **目标可以是「历史链接」**：`file-migrate` 的 `target` 除了真方案，也接受记录里已有的
  键（`(原方案)` / `(当前)` 这类反推不出方案名的）。后者**不重新上传**，只把 `path`
  指回去 —— 否则用户就回不到迁移前的图了；`file-links` 会给这些条目返回
  `label:"原链接"` + `virtual:true`，界面据此换个中性胶囊显示。
- ⚠️ **复合存储路径**：NodeImage 把 `path` 存成 `nodeimage://<id>|<真实URL>`。
  一律用 `Plugin::publicUrl()` 归一化后再用：`downloadToTemp()`（否则 curl 必失败）、
  `guessProfileForUrl()`（否则反推不出方案）、以及 `replaceUrlEverywhere()` ——
  后者要按行类型分开替换：**附件行**用存储值（复合串），**文章行**用公网地址
  （把 `nodeimage://…|` 写进正文就是坏链接）。
- ⚠️ `replaceUrlEverywhere()` 的 `text LIKE` 只能用**文件名**做锚点：库里可能是明文
  `http://…`，也可能是 JSON 转义 `http:\/\/…`；而 `\/` 在 MySQL 的 LIKE 里是转义符、在
  SQLite/PG 里是字面量 —— 拿整条 URL 去 LIKE 必然有一边匹配不到（曾因此附件地址没跟着改）。
- 接口：`media-counts`（列表角标）、`file-links`（cid→各方案链接）、`file-migrate`（`cid,target,replaceInPosts`）、
  `migrate-preview`（`from,to` 只统计）、`migrate-run`（执行）。
- 表新增可空 `cid` 列（`Plugin::ensureLinksCidColumn()`，ALTER 失败静默忽略）。
  ⚠️ `linksRowsAll()` 的 `select()` 必须带 `cid`，否则 `$row['cid']` 恒为 `null`，
  回填与自愈全都失效（老表没这列时会回退到不带 `cid` 的查询）。
- 媒体详情页会**移除 Typecho 自带的 `#upload-panel`**：不同方案重传返回的链接不一致，会与备份/迁移记录脱节。
- 没有备份记录时 `file-links` 只用 `Plugin::guessProfileForUrl()` 反推方案名：比对每个方案配置里的
  `urlPrefix/customDomain/cdnDomain/domain/url/host/siteUrl` 与本地驱动的 `站点地址+uploadDir`，
  **取最长命中**（`usr/uploads` 是 `usr/uploads-bak` 的前缀，必须比长度）；取不到才回落 `(当前)`。
  附件本身**不记录**方案名（`contents.text` 只有 path），所以只能靠 URL 猜。
- UI 用 AB 的 `--md-*` 令牌 + `[data-theme="dark"]` 覆盖，亮/暗主题都能跟随主题色。


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

## 四、5 个扩展（写在方案的 `_extensions` 里）

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

### `exif` EXIF 信息抹除

直接改写 JPEG 段 / PNG 块 / WebP RIFF 块，**不重新编码、不掉画质**；不支持的文件格式原样跳过。
执行顺序 `getOrder() = 12`（压缩 10 之后、水印 20 之前）；压缩若已重编码，元数据本就不存在，此时自动 no-op。

| 字段 | 类型 | 默认 | 说明 |
|---|---|---|---|
| `enabled` | bool | `false` | 是否启用 |
| `keepOrientation` | select | `"1"` | `"1"` 保留方向（只写回 Orientation 一个标签）/ `"0"` 一并抹除 |
| `keepIcc` | select | `"1"` | `"1"` 保留 ICC 色彩配置（Adobe RGB / P3 图片重要）/ `"0"` 移除 |
| `stripIptc` | select | `"1"` | `"1"` 同时移除 IPTC / XMP（含作者、版权、地理位置）/ `"0"` 保留 |

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

### 7.1 专用工具（PicUp 1.3.1+ 提供 `Api.php` 接口层，AI 直接调用）

带 🔒 的是**写操作**：调用后只会生成一张确认卡片，用户点「确认执行」才真正落库，
所以**不要重复调用同一个写工具**。

| 工具 | 说明 | 典型用法 |
|---|---|---|
| `picup_status` | 方案列表、默认方案、自动备份是否开启、已勾选备份方案、记录条数 | 任何 PicUp 问题先调它 |
| `picup_list_backup_records` | 列出自动备份链接记录（`q` 搜索文件名/方案名/链接，`page`/`limit` 分页） | 「看看都备份了哪些图」 |
| `picup_get_file_links` | 某附件在各方案下的链接，标出哪条是当前；只给 `keyword` 时返回候选 cid 列表 | 「这张图备份到哪几个地方了」 |
| `picup_migrate_preview` | **只统计**批量替换的影响面（记录/附件/文章数） | 批量替换前必调 |
| 🔒 `picup_migrate_batch` | 批量：记录 `primary_*` + 附件 `path` + 文章正文一起换（明文与 `http:\/\/` 两种写法都换） | 「把所有图从 default 换到 bak」 |
| 🔒 `picup_migrate_file` | 单文件迁移：目标方案没有就先下载+上传，再切链接；**原方案链接保留** | 「把这张图迁到 bak」 |
| 🔒 `picup_enable_backup` | 开启自动备份（先建 `{prefix}PicUpLinks` 表，再存方案勾选）；`profiles` 传空＝关闭 | 「开启自动备份，备份到 bak」 |

标准流程：

1. **开启自动备份** → `picup_status` 看有哪些方案 → `picup_enable_backup(profiles="bak")`
2. **批量替换** → `picup_migrate_preview(from,to)` 报数字给用户 → 确认后 `picup_migrate_batch`
3. **单文件迁移** → `picup_get_file_links(keyword=…)` 拿 cid → `picup_migrate_file(cid,target)`
4. **看备份情况** → `picup_list_backup_records(q="")`

> 没有 `Api.php` 的老版本 PicUp 会提示「版本过旧」，此时只能退化成
> `get_plugin_config` / `update_plugin_config` 改 `backupProfiles` 明文。

### 7.1.5 新增 / 修改存储方案（**必须用 `patch_plugin_json`**）

> 🚨 **血泪教训**：曾用 `update_plugin_config(key="configJson", value="…")` 整段覆盖去
> 加一个方案，模型把值写短了 → **用户所有存储方案被清空**。
> `configJson` 是「方案名 → 方案对象」的嵌套 JSON，**永远不要整段覆盖**。

正确姿势 —— `patch_plugin_json` 只合并你给出的键，其余原地保留：

```
patch_plugin_json(
  directory = "PicUp",
  key       = "configJson",
  patch     = {"lhl-oss":{"driver":"s3","bucket":"mybucket","endpoint":"oss-cn-hangzhou.aliyuncs.com",
                          "accessKeyId":"…","accessKeySecret":"…","urlPrefix":"https://cdn.example.com",
                          "_extensions":{"compress":{"enabled":true,"quality":"82"}}}}
)
```

- 同名方案会**递归合并字段**（只传要改的字段即可，不会把别的字段冲掉）；
- 删除某方案：`remove="lhl-oss"`（顶层键，逗号分隔）；
- 想确认现有方案名：先 `get_plugin_config(directory="PicUp")` 看 `configJson` 的顶层键，
  或直接 `picup_status` 看 `profiles`；
- 新方案建好后，需要让它生效再改 `defaultProfile`；
- `update_plugin_config` 现在会**主动拦截**「会让 configJson 顶层键变少」的写入并报错，
  看到那个报错就是提示你改用 `patch_plugin_json`。

**写坏了怎么救**（回答用户时可直接给这两条）：

1. 每次 AI 写插件设置前，旧值会自动备份到 `usr/plugins/.ab-store/config-backup/<插件>-<时间>.json`；
2. PicUp 设置页 →「配置备份」里，若用户之前点过「备份当前配置」，可直接「恢复选中备份」。

### 7.2 通用工具

- `get_plugin_config(directory="PicUp")` 读取当前配置
- `update_plugin_config(directory="PicUp", key="configJson", value="...")` 修改配置
  - ⚠️ **只适合改「扁平」的单值设置项**（`defaultProfile` / `backupProfiles` 这类）。
    嵌套 JSON（`configJson` / `suffixProfiles`）**请用 `patch_plugin_json`**，别整段覆盖
  - `value` 必须是**完整的两层 JSON 字符串**（方案名 → 方案对象），不是片段；
    扩展一定要写在**方案对象**的 `_extensions` 里
  - ⚠️ **`configJson` 里的密钥不会被脱敏**（脱敏只作用于顶层键名），
    所以读到的 `"accessKeySecret"` / `"token"` 等都是明文 ——
    **不要复述、不要写进回复**；只在用户明确给了新值时才写，**不要自行改写已有密钥字段**
  - `update_plugin_config` 会**拒绝** key 名里含 `key|secret|token|password|passwd`
    的设置项，因此 `configJson`（名字不含这些词）**可以改**，
    但改完后其中的密钥仍需用户自己确认是否正确
  - 改完 `defaultProfile` 时，一定确认它指向的 key 真实存在
- `upload_image` 上传图片拿 URL，再用 `edit_editor_content` 插入正文
- `save_chat_image` 把对话里的图片存进媒体库（走 PicUp 的方案与扩展）
- **不能**把驱动密钥（`token` / `secretKey` / `accessKey` / `password` / `api_key`）
  当成独立设置项去改 —— PicUp 没有这些顶层项，它们只存在于 `configJson` 里

### 7.3 新老版本兼容（重要）

`picup_*` 这套专用工具**依赖 PicUp 1.3.1 起提供的 `Api.php` 接口层**。
老版本 PicUp（`< 1.3.1`，或手动删过文件的残缺安装）没有它，此时必须走**原来的通用调用方式**，
下面这套写法**一直有效，请保留使用**。

**怎么判断**：先调一次 `picup_status`。

- 返回 `{"ok": true, ...}` → 用 7.1 的专用工具
- 返回 `{"ok": false, "error": "...版本过旧..."}` 或 `...未启用...` → 走下面的降级方式

| 需求 | 新方式（1.3.1+） | 老方式（任何版本都能用） |
|---|---|---|
| 看有哪些方案 | `picup_status` | `get_plugin_config(directory="PicUp")` 看 `configJson` 的键 |
| 看自动备份是否开启 | `picup_status` | `get_plugin_config` 看 `backupProfiles` 是否为 `[]` |
| 开启 / 关闭自动备份 | `picup_enable_backup(profiles="bak")` | `update_plugin_config(directory="PicUp", key="backupProfiles", value="[\"bak\"]")`；关闭传 `"[]"` |
| 看某文件的备份链接 | `picup_get_file_links` | 无接口，只能看 `get_plugin_config` 或让用户去 **管理 → 文件 → 详情页** |
| 批量 / 单文件迁移 | `picup_migrate_batch` / `picup_migrate_file` | 无接口，**让用户自己去设置页「迁移与替换」或详情页操作**，不要尝试直接改数据库 |
| 看链接记录 | `picup_list_backup_records` | 无接口（`get_plugin_config` 读不到 `{prefix}PicUpLinks` 表） |

老方式的两个硬性注意点：

1. `backupProfiles` 的值是**JSON 数组字符串**，如 `["bak","upyun"]`，不是逗号分隔；
   关掉自动备份传 `"[]"`（不是空字符串）。
2. 老版本**建表只能由用户在设置页点「开启自动备份」按钮触发**。
   如果 `backupProfiles` 有值但链接表不存在，备份会在上传时静默跳过
   （只写 error_log）—— 这时要明确提示用户去后台点那个按钮，不要说「已经开好了」。
