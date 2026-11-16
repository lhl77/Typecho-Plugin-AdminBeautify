---
name: links-plus
title: Links+ 友链插件（完整字段）
description: Links+ 全部设置项、数据表结构、短代码参数、授权与排错
keywords: Links, 友链, 友情链接, 短代码, links, 授权码
---

# Links+ 友链插件

## 一、基本信息

- 目录名：**必须是 `Links`**（类名 `Links_Plugin`，目录名与类名前缀必须一致）
- 数据表：`typecho_links`
- 后台入口：左侧菜单「Links Plus → 友情链接」，或 `extending.php?panel=Links/manage-links.php`
- 版本：2.0.0（付费插件）

## 二、授权

未授权时处于**只读模式**：能看列表，不能新增/编辑/审核/重写/短代码。

授权码填写位置：插件设置 → `license_code`。

> AI **不能**修改 `license_code`（密钥类字段），需用户手动填写。

## 三、设置项（`plugin:Links` 的键）

| 键名 | 类型 | 默认值 | 含义 |
|---|---|---|---|
| `license_code` | string | `""` | 授权码（AI 不可改） |
| `template` | string | `default` | 前台模板名，对应 `templates/<名>/` |
| `checkInterval` | int | `24` | 友链可用性检查间隔（小时） |
| `nofollow` | string | `0` | 外链是否加 `rel="nofollow"`（`0`/`1`） |
| `target` | string | `_blank` | 链接打开方式（`_blank`/`_self`） |
| `showAvatar` | string | `1` | 是否显示头像（`0`/`1`） |
| `showDesc` | string | `1` | 是否显示描述（`0`/`1`） |
| `applyEnabled` | string | `0` | 是否开启访客自助申请（`0`/`1`） |
| `applyNeedAudit` | string | `1` | 申请是否需要审核（`0`/`1`） |
| `mailNotify` | string | `0` | 新申请是否邮件通知（`0`/`1`） |

> 实际键名以 `get_plugin_config(directory="Links")` 返回为准，不同版本可能有差异。

## 四、数据表 `typecho_links`

| 字段 | 类型 | 含义 |
|---|---|---|
| `lid` | int | 主键 |
| `name` | varchar | 站点名称 |
| `url` | varchar | 站点地址 |
| `avatar` | varchar | 头像/图标 URL |
| `description` | varchar | 描述 |
| `group` | varchar | 分组名 |
| `sort` | int | 排序值（越小越前） |
| `status` | varchar | `enable` 启用 / `disable` 禁用 / `audit` 待审核 |
| `visible` | int | 前台是否可见（1/0） |
| `created` | int | 创建时间戳 |
| `checked` | int | 上次可用性检查时间戳 |
| `available` | int | 上次检查结果（1 可用 / 0 不可用） |

## 五、短代码

在文章/页面正文中插入：

```
[links]
```

### 参数

| 参数 | 含义 | 示例 |
|---|---|---|
| `group` | 只显示指定分组 | `[links group="朋友们"]` |
| `limit` | 最多显示条数 | `[links limit="20"]` |
| `template` | 指定模板 | `[links template="z-mirages"]` |
| `order` | 排序方式 | `[links order="sort"]` / `[links order="created"]` |
| `avatar` | 是否显示头像 | `[links avatar="0"]` |
| `desc` | 是否显示描述 | `[links desc="1"]` |

### 组合示例

```
[links group="朋友们" limit="10" template="z-mirages"]
```

## 六、内置模板

| 模板名 | 风格 |
|---|---|
| `default` | 简洁列表 |
| `default-img` | 带头像卡片 |
| `z-mirages` | 卡片网格（带预览图） |

模板目录：`usr/plugins/Links/templates/<名>/`，含 `manifest.json`、`template.html`、`style.css`。

## 七、排错

| 现象 | 原因 | 处理 |
|---|---|---|
| 启用失败 | 目录名不是 `Links` | 用 `install_plugin(id=..., directory="Links")` 重装 |
| 启用失败 + `broken: true` | 缺 `core.php` 等文件 | 同上，重装修复 |
| 前台不显示 | 主题未调用短代码解析 | 检查主题是否支持 `contentEx` 钩子 |
| 只读模式 | 未填授权码 | 填写 `license_code` |
| 头像不显示 | `avatar` 为空或 URL 失效 | 补填头像地址 |
| 可用性检查不生效 | `checkInterval` 为 0 或未开启 | 设为 `24` 等正整数 |

## 八、AI 能做什么

- `list_plugins` 查看是否已装、是否 `broken`
- `install_plugin(id="381aa74a", directory="Links")` 安装/修复
- `toggle_plugin(directory="Links", enable=true)` 启用
- `get_plugin_config(directory="Links")` 读配置
- `update_plugin_config(directory="Links", key="template", value="z-mirages")` 改非密钥设置
- **不能**改 `license_code`
