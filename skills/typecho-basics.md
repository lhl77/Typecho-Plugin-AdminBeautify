---
name: typecho-basics
title: Typecho 后台与数据表完整参考
description: 全部数据表结构、文章/评论/分类/标签/页面/用户/设置的操作要点与 SQL 语义
keywords: Typecho, 文章, 分类, 标签, 评论, 页面, 设置, 数据库, 数据表, contents, metas, comments
---

# Typecho 后台与数据表完整参考

## 一、数据表结构

### `contents` — 内容表

| 字段 | 类型 | 含义 |
|---|---|---|
| `cid` | int | 主键 |
| `title` | varchar | 标题 |
| `slug` | varchar | 缩略名（URL） |
| `created` | int | 创建时间戳 |
| `modified` | int | 修改时间戳 |
| `text` | longtext | 正文（Markdown 文章以 `<!--markdown-->` 开头） |
| `order` | int | 排序 |
| `authorId` | int | 作者 uid |
| `template` | varchar | 自定义模板 |
| `type` | varchar | `post` 文章 / `page` 独立页面 / `attachment` 附件 / `post_draft` 草稿 |
| `status` | varchar | `publish` / `draft` / `private` / `waiting` / `trash` |
| `password` | varchar | 访问密码 |
| `commentsNum` | int | 评论数 |
| `allowComment` | char | 允许评论（`0`/`1`） |
| `allowPing` | char | 允许 ping |
| `allowFeed` | char | 允许 RSS |
| `parent` | int | 父级（页面层级） |

### `metas` — 分类与标签

| 字段 | 类型 | 含义 |
|---|---|---|
| `mid` | int | 主键 |
| `name` | varchar | 名称 |
| `slug` | varchar | 缩略名 |
| `type` | varchar | `category` 分类 / `tag` 标签 |
| `description` | varchar | 描述 |
| `count` | int | 内容数 |
| `order` | int | 排序 |
| `parent` | int | 父分类 mid（0 为顶级） |

### `relationships` — 内容与分类/标签关联

| 字段 | 类型 | 含义 |
|---|---|---|
| `cid` | int | 内容 ID |
| `mid` | int | 分类/标签 ID |

### `comments` — 评论

| 字段 | 类型 | 含义 |
|---|---|---|
| `coid` | int | 主键 |
| `cid` | int | 所属内容 ID |
| `created` | int | 时间戳 |
| `author` | varchar | 评论者名 |
| `authorId` | int | 注册用户 uid（0 为访客） |
| `ownerId` | int | 内容作者 uid |
| `mail` | varchar | 邮箱 |
| `url` | varchar | 网址 |
| `ip` | varchar | IP |
| `agent` | varchar | UA |
| `text` | text | 内容 |
| `type` | varchar | `comment` / `trackback` / `pingback` |
| `status` | varchar | `approved` / `waiting` / `spam` |
| `parent` | int | 父评论 coid |

### `users` — 用户

| 字段 | 类型 | 含义 |
|---|---|---|
| `uid` | int | 主键 |
| `name` | varchar | 登录名 |
| `password` | varchar | 密码哈希 |
| `mail` | varchar | 邮箱 |
| `url` | varchar | 主页 |
| `screenName` | varchar | 显示名 |
| `group` | varchar | `administrator` / `editor` / `contributor` / `subscriber` / `visitor` |
| `created` | int | 注册时间 |
| `activated` | int | 是否激活 |
| `logged` | int | 上次登录时间 |

### `options` — 设置

| 字段 | 类型 | 含义 |
|---|---|---|
| `name` | varchar | 键名（插件设置为 `plugin:<目录名>`） |
| `user` | int | 0 为站点级，其他为用户级 |
| `value` | text | 值（JSON 或序列化） |

### `fields` — 自定义字段

| 字段 | 类型 | 含义 |
|---|---|---|
| `cid` | int | 内容 ID |
| `name` | varchar | 字段名 |
| `type` | varchar | 字段类型 |
| `str_value` | varchar | 字符串值 |
| `int_value` | int | 整数值 |
| `float_value` | float | 浮点值 |

## 二、文章操作

### Markdown 判定

`contents.text` 以 `<!--markdown-->` 开头 → Markdown 文章。
AI 的 `create_post` / `update_post` 会自动补这个前缀。

### 状态流转

```
draft（草稿） → publish（发布） → trash（回收站）
                ↓
            private（私密）
```

### 分类关联

新建文章时若指定分类名：
1. 查 `metas` 找 `type='category' AND name=?`
2. 找到 → 用其 `mid`
3. 找不到 → 用 `mid` 最小的分类
4. 写入 `relationships(cid, mid)`

### 删除

AI 的 `delete_post` 是**软删除**（`status='trash'`），同时删除 `relationships` 关联，不物理删除。

## 三、评论操作

| 状态 | 含义 |
|---|---|
| `approved` | 已通过 |
| `waiting` | 待审核 |
| `spam` | 垃圾 |

AI 工具：
- `list_comments(status?, limit?)` — 返回评论列表 + 各状态计数
- `moderate_comments(coids, action)` — `action` 为 `approve` / `spam` / `delete`；`coids` 可传 `all-waiting`

## 四、分类与标签

- `list_terms(type="category"|"tag")` — 返回 `mid`、`name`、`slug`、`count`
- `create_term(type, name, slug?)` — 同名已存在时直接返回已有 `mid`

## 五、独立页面

- `list_pages()` — 返回 `cid`、`title`、`slug`、`status`
- 页面层级通过 `contents.parent` 实现

## 六、站点设置

`get_site_options()` 读取，`update_site_option(key, value)` 修改。

| 键 | 含义 | 合法值 |
|---|---|---|
| `title` | 站点名 | 任意字符串 |
| `description` | 站点描述 | 任意字符串 |
| `siteUrl` | 站点地址 | 必须 `http://` 或 `https://` 开头 |
| `postsListSize` | 每页文章数 | 正整数 |
| `timezone` | 时区 | 秒数偏移，如 `28800` = UTC+8 |
| `allowRegister` | 是否允许注册 | `"0"` / `"1"` |

## 七、用户

`list_users()` 返回 `uid`、`name`、`screenName`、`mail`、`group`、`posts`（文章数）。

角色权限：
- `administrator` — 全部权限
- `editor` — 管理所有文章/评论
- `contributor` — 只能管理自己的文章
- `subscriber` — 只能阅读
- `visitor` — 未登录

## 八、插件

| 工具 | 作用 |
|---|---|
| `list_plugins` | 列出已装插件（含 `broken` 残缺标记、`missing` 缺失文件、`classMismatch` 类名不匹配） |
| `search_store_plugins(keyword)` | 在 AB Store 搜索 |
| `install_plugin(id, directory?)` | 安装/修复（`directory` 可指定安装目录） |
| `toggle_plugin(directory, enable)` | 启用/停用 |
| `remove_plugin(directory)` | 删除（自动备份到 `.ab-store/backup`） |
| `get_plugin_config(directory)` | 读任意插件配置（密钥脱敏） |
| `update_plugin_config(directory, key, value)` | 改任意插件配置（密钥禁止） |

### 重要规则

**插件目录名必须与插件类名前缀一致**：
- 类 `Links_Plugin` → 必须在 `Links` 目录
- 类 `DbManager_Plugin` → 必须在 `DbManager` 目录
- 新式命名空间插件：`namespace TypechoPlugin\Xxx` → 必须在 `Xxx` 目录

不一致时 `Plugin::portal()` 拼不出类名，启用会 500。

## 九、批量操作

| 工具 | 作用 |
|---|---|
| `search_posts(keyword, limit?)` | 按关键词搜标题+正文，返回 `cids` |
| `get_post_content(cid)` | 读单篇完整正文（自动剥离 `<!--markdown-->`） |
| `batch_replace_in_posts(cids, search, replace, dryRun?)` | 批量文本替换，`cids` 可传 `all` |

推荐流程：`search_posts` 定位 → `get_post_content` 确认 → `batch_replace_in_posts`（先 `dryRun=true` 预演）→ 用户确认执行。
