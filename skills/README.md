# AB AI Skills

AI 助手的「按需知识库」。每个 `.md` 文件是一份技能说明，AI 通过 `read_skill` 工具按需读取，
避免把所有细节一次性塞进系统提示（省 token）。

## 文件格式

```markdown
---
name: 技能名（唯一标识，AI 用它来读取）
title: 显示标题
description: 一句话说明这个技能解决什么问题（AI 靠它判断要不要读）
keywords: 关键词1, 关键词2
---

正文（Markdown）
```

## 现有技能

| name | 说明 |
|---|---|
| `picup` | PicUp 图片上传插件的设置与用法、如何上传图片 |
| `image-upload` | AI 上传图片（本地上传 / 剪贴板粘贴）的完整流程与错误处理 |
| `links-plus` | Links+ 友链插件的设置与短代码 |
| `ab-admin` | AB Admin 自身设置项说明 |
| `custom-cards` | **在概要页添加自定义卡片**（数据结构、写入步骤、`ab.ui` 组件库、示例与排错） |
| `typecho-basics` | Typecho 后台常用操作（文章、分类、评论、设置） |

## 新增技能

在 `skills/` 下新建 `<name>.md`，按上面的 frontmatter 格式写即可，无需改代码。

## 单独更新（不跟插件发版）

技能库支持单独更新，入口在 **AI 助手面板顶部**（发现新版本时自动出现一条提示）。

机制：

1. 本目录的 **`manifest.json`** 是唯一的版本清单 —— 检查更新**只拉这一个文件**
   （一次请求），比对 `version` 就能判断要不要更新；
2. 版本不同 → 逐个拉取 `files` 里列出的 md，覆盖本地 `skills/`；
3. 检查间隔 **30 分钟**（服务端 `AdminBeautify_Skills::TTL` + 前端 localStorage 双重节流）；
4. 大陆网络：直连 `raw.githubusercontent.com` 失败时自动走 `https://gh1.lhl.one/` 镜像。

**改动任何技能文件后，请务必同步修改 `manifest.json`**：

```json
{
  "version": "YYYYMMDD-N",      // 递增即可，不用语义化
  "updated": "2026-11-19 12:00:00",
  "files": ["picup.md", "..."]
}
```

新增技能时把文件名补进 `files`，否则单独更新拉不到它。
覆盖前的旧文件会备份到 `usr/plugins/.ab-store/skills-backup/<时间>/`，
当前版本记录在 `usr/plugins/.ab-store/skills.json`。
