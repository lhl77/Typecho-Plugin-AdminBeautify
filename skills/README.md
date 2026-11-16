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
| `typecho-basics` | Typecho 后台常用操作（文章、分类、评论、设置） |

## 新增技能

在 `skills/` 下新建 `<name>.md`，按上面的 frontmatter 格式写即可，无需改代码。
