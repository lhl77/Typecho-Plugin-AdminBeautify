---
name: ab-admin
title: AB Admin 全部设置项
description: AB Admin（AdminBeautify）所有设置项的键名、类型、默认值、合法取值与用户说法映射
keywords: AB Admin, AdminBeautify, 后台美化, 动画, 暗色, 圆角, 加载动画, 快捷键, 卡片
---

# AB Admin 全部设置项

## 一、外观类

| 键名 | 类型 | 默认值 | 合法值 | 含义 |
|---|---|---|---|---|
| `primaryColor` | string | `default` | 配色方案名 | 主色调 |
| `darkMode` | string | `auto` | `auto` / `light` / `dark` | 主题模式 |
| `borderRadius` | string | `medium` | `small` / `medium` / `large` | 圆角大小 |
| `enableAnimation` | string | `1` | `0` / `1` | 是否启用动画 |
| `loadingAnimation` | string | `morph` | `morph` / `spinner` / `topbar` | 加载动画样式 |

## 二、控制台（Dashboard）

| 键名 | 类型 | 默认值 | 含义 |
|---|---|---|---|
| `dashboardQuickShow` | string | `1` | 是否显示快捷操作区 |
| `dashboardQuickStyle` | string | `card` | 快捷操作样式 |
| `dashboardQuickHint` | string | `1` | 是否显示快捷操作提示 |
| `dashboardThemeButtonShow` | string | `1` | 是否显示主题切换按钮 |
| `dashboardHideDonate` | string | `0` | 是否隐藏捐助入口 |
| `dashboardCustomButtons` | JSON | `[]` | 自定义快捷按钮 |
| `dashboardCustomCardsEnabled` | string | `0` | 是否启用自定义卡片 |
| `dashboardCustomCards` | JSON | `[]` | 自定义卡片配置（结构 / 写法 / 示例见 `custom-cards` 技能） |
| `dashboardRecentStyle` | string | `list` | 最近文章展示样式 |

## 三、列表页

| 键名 | 类型 | 默认值 | 合法值 | 含义 |
|---|---|---|---|---|
| `postListStyle` | string | `card` | `card` / `list` | 文章列表样式 |
| `pageListStyle` | string | `card` | `card` / `list` | 独立页面列表样式 |
| `commentListStyle` | string | `card` | `card` / `list` | 评论列表样式 |
| `categoryListStyle` | string | `card` | `card` / `list` | 分类列表样式 |

## 四、概览图表

| 键名 | 类型 | 默认值 | 含义 |
|---|---|---|---|
| `overviewChartEnabled` | string | `1` | 是否显示概览图表 |
| `overviewTimeRange` | string | `7` | 图表时间范围（天） |

## 五、Umami 统计

| 键名 | 类型 | 默认值 | 含义 |
|---|---|---|---|
| `umamiEnabled` | string | `0` | 是否启用 Umami |
| `umamiProvider` | string | `umami` | 服务商 |
| `umamiApiBase` | string | `""` | API 地址 |
| `umamiWebsiteId` | string | `""` | 站点 ID |
| `umamiApiToken` | string | `""` | API Token（**AI 不可改**） |
| `umamiTimeRange` | string | `7` | 时间范围（天） |

## 六、AI 助手

| 键名 | 类型 | 默认值 | 合法值 | 含义 |
|---|---|---|---|---|
| `ai_enabled` | string | `1` | `0` / `1` | AI 助手开关 |
| `ai_provider` | string | `deepseek` | `openai` / `deepseek` / `zhipu` / `qwen` / `ollama` / `custom` | 服务商 |
| `ai_base_url` | string | 按 provider | URL | API 地址 |
| `ai_api_key` | string | `""` | — | API Key（**AI 不可改**） |
| `ai_model` | string | 按 provider | 模型名 | 模型 |
| `ai_temperature` | float | `0.7` | 0~2 | 温度 |
| `ai_max_tokens` | int | `2048` | 正整数 | 最大输出 token |
| `ai_system_prompt` | string | `""` | — | 站点级自定义指令 |
| `ai_allow_non_admin` | string | `0` | `0` / `1` | 是否允许非管理员使用 |
| `ai_rate_per_min` | int | `10` | 正整数 | 每分钟请求上限 |

## 七、用户说法 → 工具调用映射

| 用户说 | 应该调用 |
|---|---|
| 「把后台动画关掉」「去掉加载动画」 | `update_plugin_setting(key="enableAnimation", value="0")` |
| 「开启动画」 | `update_plugin_setting(key="enableAnimation", value="1")` |
| 「切换成暗色」「深色模式」 | `update_plugin_setting(key="darkMode", value="dark")` |
| 「跟随系统」 | `update_plugin_setting(key="darkMode", value="auto")` |
| 「圆角大一点」 | `update_plugin_setting(key="borderRadius", value="large")` |
| 「加载动画换成顶部进度条」 | `update_plugin_setting(key="loadingAnimation", value="topbar")` |
| 「文章列表改成卡片」 | `update_plugin_config(directory="AdminBeautify", key="postListStyle", value="card")` |
| 「关掉 AI 助手」 | `update_plugin_setting(key="ai_enabled", value="0")` |

## 八、注意事项

- `update_plugin_setting` 只允许上表「外观类」与「AI 助手」中的部分键（白名单见工具描述）。
- 其他键（列表样式、控制台、Umami 等）用 `update_plugin_config(directory="AdminBeautify", key=..., value=...)`。
- 密钥类字段（`ai_api_key`、`umamiApiToken`）**AI 一律不可改**。
- 关闭动画后，AI 面板自身的开关动画也会失效（`[data-no-animation]` 强制所有动画时长为 0），这是预期行为。
- 修改后需刷新后台页面才生效。
