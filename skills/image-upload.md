---
name: image-upload
title: AI 上传图片（完整流程与错误码）
description: 本地上传 / 剪贴板粘贴 / AI 主动上传的完整流程、接口细节、全部错误码与处理方式
keywords: 上传图片, 粘贴图片, 插图, 图片, 剪贴板, upload, 多模态, 视觉模型
---

# AI 上传图片

## 一、前提条件

1. 若要让 AI **理解图片内容**，当前模型必须支持多模态（视觉）输入。
2. 要把图片**存进媒体库**，需要 `usr/uploads/` 可写；装了 PicUp 则自动交给 PicUp 的存储方案。
3. **不再需要**上传插件就能让 AI 看图 —— 前端把图片转成 base64 直接随消息发给模型。

### 支持视觉的常见模型

| Provider | 模型 |
|---|---|
| OpenAI | `gpt-4o`、`gpt-4o-mini`、`gpt-4.1` |
| 通义千问 | `qwen-vl-max`、`qwen-vl-plus`、`qwen2.5-vl-72b-instruct` |
| 智谱 | `glm-4v`、`glm-4v-plus` |
| DeepSeek | `deepseek-vl2`（部分部署） |
| Ollama | `llava`、`llama3.2-vision`、`qwen2.5vl` |

> 纯文本模型（如 `deepseek-chat`、`glm-4-flash`）**不能看图**。上传仍会成功拿到 URL，但 AI 无法描述图片内容。

## 二、四种入口

### A. 本地上传（用户选文件）

1. 用户点 AI 输入框左侧「图片」按钮
2. 选择本地文件（`accept="image/*"`）
3. 前端用 `FileReader.readAsDataURL` 转成 `data:image/...;base64,...`
4. 作为待发送图片挂在胶囊上方的缩略条（`.ab-ai-chips`），**不经过任何上传接口**
5. 发送时拼进多模态 `content` 数组交给模型

### B. 剪贴板粘贴

1. 用户在 AI 输入框内 `Ctrl+V`
2. 前端监听 `paste`，取 `clipboardData.items` 里 `type.indexOf('image/') === 0` 的项
3. 同上转 base64

> A / B 都不会调 `/action/upload`，所以**后台没装上传插件时也能正常看图**。

### C. AI 主动上传（工具调用）

```
upload_image(source="base64" | "url", data="...", filename="xxx.png")
```

- `source=base64`：`data` 可带 `data:image/png;base64,` 前缀
- `source=url`：`data` 传图片地址（必须公网 http/https）

### D. 把对话里的图片存进媒体库（工具调用，推荐）

```
save_chat_image(index=1, filename="xxx.png")
```

- 用户在对话里发过的图片，**服务端会从消息的 `content` 数组里解出原始二进制**，并自动注入到系统提示中（带 `#1` `#2` 编号）
- 用户说「把这张图存下来 / 上传到媒体库 / 插进文章」时**直接调它**，不要向用户索要图片地址或让他重新上传
- 已启用 PicUp 时走 `PicUp\Plugin::uploadHandle()`，因此压缩/水印/WebP/远程存储都生效；PicUp 未启用或失败时自动回退到 `usr/uploads` + `contents` 附件行
- 返回：`url`、`path`、`name`、`size`、`cid`、`storage`、`markdown`

## 三、接口细节

```
POST /index.php/action/upload
Content-Type: multipart/form-data
X-Requested-With: XMLHttpRequest

字段：
  file  文件二进制（必填）

成功响应（200）：
{
  "cid": 123,
  "url": "https://example.com/usr/uploads/2026/11/xxx.png",
  "name": "xxx.png"
}

失败响应：
{ "error": "具体原因" }
```

## 四、错误码对照表

| 错误 / 状态 | 含义 | AI 应该怎么说 |
|---|---|---|
| `模型不支持图片输入` | 当前 provider/model 无视觉能力 | 明确告知需换用支持视觉的模型，并列出可选模型 |
| `当前对话里没有可保存的图片` | 用户还没发图，或会话是刷新后恢复的（历史里的图片已剥除 base64） | 请用户在当前会话重新发一次图 |
| `图片编号超出范围` | `save_chat_image` 的 `index` 不在系统提示列出的编号里 | 只能使用 `#1`…`#N` |
| `PicUp 上传失败，已回退到本地存储` | PicUp 的驱动器配置有误/远端不可达 | 结果里 `note` 会带原因；文件仍在本地入库 |
| HTTP 401 | 未登录或 CSRF token 失效 | 让用户刷新后台页面重试 |
| HTTP 403 | 无上传权限 | 检查用户角色是否为管理员/编辑 |
| HTTP 413 | 文件超过服务器限制 | 告知 nginx `client_max_body_size` 限制，建议压缩或调大 |
| HTTP 500 | 服务端异常 | 让用户查看 PHP 错误日志 |
| `未找到上传插件` | 没有 PicUp 等插件 | 建议先安装启用 PicUp |
| `不支持的图片格式` | 扩展名不在白名单 | 列出允许格式：jpg/jpeg/png/gif/webp/svg/bmp |
| `图片超过 10MB 限制` | 超过 AI 工具上限 | 建议压缩后重试 |
| `base64 解码失败` | base64 数据损坏 | 让用户重新复制图片 |
| `下载图片失败` | 远程 URL 不可达 | 检查 URL 是否可公开访问 |
| `图片 URL 不合法` | 内网地址被 SSRF 防护拦截 | 只能上传公网图片 |
| `无法创建上传目录` | `usr/uploads/` 不可写 | 检查目录权限（755，属主为 Web 用户） |
| `写入文件失败` | 磁盘满或权限不足 | 检查磁盘空间与权限 |

**绝对不要**在上传失败时编造 URL 或假装成功。

## 五、上传后插入文章

```markdown
![图片说明](https://cdn.example.com/2026/11/xxx.png)
```

用 `edit_editor_content` 写入编辑器：

```
edit_editor_content(content="![图](url)", mode="append")
```

`mode` 可选：
- `replace` 整体替换
- `append` 追加到末尾
- `prepend` 插入到开头

## 六、常见问题

| 现象 | 原因 | 处理 |
|---|---|---|
| 上传成功但图片 404 | `urlPrefix` / `cdn` 配错 | 检查 PicUp 的访问域名配置 |
| 粘贴无反应 | 不在编辑页 / 浏览器禁止剪贴板 | 在文章编辑页重试，或改用按钮上传 |
| 图片显示但很慢 | 未走 CDN | 配置 `urlPrefix` 指向 CDN |
| 上传后文件名乱码 | 原文件名含中文/特殊字符 | 前端会做 `[^A-Za-z0-9_.\-]` 替换 |
