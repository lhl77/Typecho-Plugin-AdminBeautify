---
name: custom-cards
title: 自定义概要页卡片（HTML / JS）
description: 在 AB Admin 概要页添加自定义卡片：数据结构、写入步骤（get_plugin_config → update_plugin_config）、ab / ab.ui 可用 API、可直接照抄的示例、大小限制与排错
keywords: 自定义卡片, 卡片, 概要页, 控制台, dashboard, 添加卡片, 卡片广场, ab.ui, card, HTML, JS
---

# 自定义概要页卡片

用户说「给后台加个卡片」「做个 XXX 卡片」「自定义卡片怎么加」时用本技能。
**不要回答"做不到"** —— 你可以通过 `update_plugin_config` 直接把卡片写进设置。

---

## 一、先记住三件事

1. 卡片是**一条配置**，存在 AB Admin 插件设置项 `dashboardCustomCards`（JSON 数组字符串）里。
2. 还需要把开关 `dashboardCustomCardsEnabled` 设为 `"1"`（默认是 `"0"`，关闭时**整块不渲染**）。
3. 默认**管理员**才看得到概要页卡片；写入后会走「确认执行」流程，你必须等用户点确认。

---

## 二、数据结构

```json
[
  {
    "id": "clq3m9x2ab",
    "icon": "insights",
    "title": "我的卡片",
    "html": "",
    "js": "(function(){ /* … */ })();"
  }
]
```

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `id` | ✅ | 卡片唯一标识，**只能 `[A-Za-z0-9_-]`**。用于排序键 `custom:<id>`，绝不能与已有卡片重复 |
| `icon` | | Material Icons Round 字形名，如 `link` / `insights` / `pending_actions`，缺省 `widgets` |
| `title` | | 卡片标题栏文字 |
| `html` | | 静态 HTML 片段（会作为 innerHTML 注入卡片正文）。**用组件库时留空** |
| `js` | | 卡片渲染完成后执行的脚本源码 |

> 卡片正文的标题栏、图标、内边距、挂载容器都由插件生成，你只写正文。
> `html` 与 `js` 至少有一个非空，否则卡片渲染出来是空的（会被跳过）。

---

## 三、写入步骤（照做即可）

### 1) 读现有配置

```
get_plugin_config  directory="AdminBeautify"
```

返回的 `config.dashboardCustomCards` 是一段 **JSON 字符串**（可能为空字符串或 `""`）。
如果 `config` 里根本没有这个键，就当作 `[]`。

### 2) 在本地合并出新数组

- **必须先解析现有数组、追加新卡片、再整体写回** —— 直接写一张新卡片会把用户已有卡片全删掉。
- `id` 用「时间戳 base36 + 2 位随机」，例如 `c` + `Date.now().toString(36)` + 随机两位。

### 3) 写回（两步，都会弹确认）

```
update_plugin_config  directory="AdminBeautify"  key="dashboardCustomCards"
                      value="<上一步得到的 JSON 字符串>"

update_plugin_config  directory="AdminBeautify"  key="dashboardCustomCardsEnabled"  value="1"
```

> `value` 必须是**字符串**。JSON 里的双引号不要自己再转义一遍——工具参数本身就是字符串。

### 4) （可选）把它排进概要页顺序

`dashboardCardOrder` 是逗号分隔的键名列表（内置卡片 `umami,freq,cat,posts,replies,more`，
自定义卡片写作 `custom:<id>`）。写回前先读现有的，再在末尾追加 `custom:<id>`；
不追加时卡片默认排在所有内置卡片之后，同样会显示。

### 5) 回复用户

告诉用户：**已准备好 N 项修改，点「确认执行」后刷新概要页即可看到**。
不要声称已经添加成功（确认前还没写库）。

---

## 四、大小限制（硬性）

| 限制 | 数值 | 原因 |
| --- | --- | --- |
| 单张卡片 `html` + `js` | **≤ 12 KB** | 超过就难维护，也容易触顶 |
| 整个 `dashboardCustomCards` | **≤ 40 KB** | 插件设置是 Typecho 的一个 options 行（TEXT 上限 64 KB），写爆会让**所有插件设置一起损坏** |

超限时：**不要把代码硬塞进去**。改为把 `html` / `js` 两段代码直接发在对话里，
让用户到「插件设置 → 概要页卡片设置 → 自定义卡片 → 添加卡片」自己粘贴保存
（该入口保存时会把代码转存到数据表，不受 64 KB 限制）。

> ⚠️ 还有一层限制是**单条回复的 token 上限**（`max_tokens`，默认 4096）。
> 一张卡片的 `js` 往往就是 1500～3000 token，写太长会导致工具参数被截断、写入失败。
> 所以：
> - 优先用 `ab.ui` 组件拼装（几行就能出效果），不要手写大段 HTML；
> - 需要长脚本时**分两步**：先写 `html` + 骨架 `js`，下一轮再补细节；
> - 用户如果反馈「一直无回复 / 被截断」，提醒他把「插件 → AB Admin → AI 助手 → 最大输出长度」调到 8192。

---

## 五、卡片里能写什么

### 5.1 执行方式

```js
new Function('card', 'ab', def.js).call(card, card, abApi);
```

| 变量 | 说明 |
| --- | --- |
| `this` / `card` | 卡片根元素 `div.ab-card.ab-custom-card`，已挂到 `#ab-dash-cards` |
| `ab` | 迷你 API，见下 |

约定：

- 必须用 IIFE 包裹；**顶层只用 `var`，不要 `let` / `const`**（避免与外层作用域冲突）。
- 抛错会被捕获：控制台打印 `[AB] 自定义卡片脚本出错：custom:<id>`，卡片正文顶部出现红色错误块，不影响其它卡片。
- 卡片宽度弹性（约 340px ~ 600px+），**不要写死像素宽**。

### 5.2 `ab` API 速查

| API | 用途 |
| --- | --- |
| `ab.ui` | 组件库（下面详述），**首选** |
| `ab.request(do, params)` | Promise 版后台接口调用，resolve 为 `json.data` |
| `ab.ajax(do, params, {onSuccess,onError})` | 回调版 |
| `ab.data.save/get/list/remove` | 卡片自己的持久化数据（可 `scope:'site'` 全站共享） |
| `ab.db.read(table, query)` | 只读查询其它表 |
| `ab.notify(text, tone, ms)` | 右下角轻提示，`tone`: `info/success/warn/danger` |
| `ab.copyText(text, tip)` | 复制 + 提示 |
| `ab.format` | `.bytes() .date() .relative() .duration() .num()` |
| `ab.chart.line(el, {xData, yData})` / `.polar(el, {data})` | 本地 SVG 图表（懒加载，返回 Promise） |
| `ab.interval(fn, ms)` / `ab.onCleanup(fn)` | 定时器 / 销毁回调（AJAX 切页时自动清理） |
| `ab.esc(text)` | HTML 转义 |
| `ab.config` | `{ pluginVersion, siteName, user, primaryColorHex, primaryColorDarkHex, pluginSettingsUrl, … }` |

### 5.3 常用组件 `ab.ui`

```js
var body = card.querySelector('.ab-custom-body');

ab.ui.mount(body, ab.ui.metrics([            // 一排大数字
  { icon: 'article', label: '已发布', value: '12' },
  { icon: 'drafts',  label: '草稿',   value: '2', tone: 'warn' }
], { cols: 2 }));

ab.ui.mount(body, ab.ui.rows([               // 图标胶囊 + 名称 + 右对齐数值
  { icon: 'visibility', label: '今日访问', value: '1,286' },
  { icon: 'forum',      label: '待审核',   value: '3', tone: 'danger',
    href: '/admin/manage-comments.php' }
]));

ab.ui.mount(body, ab.ui.hero({               // 一个大数字
  icon: 'trending_up', label: '本周新增', value: '+18', hint: '较上周 +12%', tone: 'primary'
}));

ab.ui.mount(body, ab.ui.list([               // 可点击行
  { icon: 'add_link', label: '新增友链', desc: '打开友链管理', href: '/admin/manage-links.php' }
]));

ab.ui.mount(body, ab.ui.actions([            // 徽标 / 按钮行
  ab.ui.badge('已同步', 'success'),
  ab.ui.button({ text: '刷新', icon: 'refresh', tone: 'info', onClick: reload })
]));

ab.ui.mount(body, ab.ui.notice({ text: '数据每 5 分钟更新一次', tone: 'info' }));

ab.ui.footer(card, { text: '去管理', href: '/admin/manage-posts.php' });  // 吸底入口
```

`ab.ui.el(ptag, cls, text)` / `ab.ui.icon(name)` 可以自己拼；`ab.ui.mount(容器, 内容)` 会先清空容器。

常用 `do=` 只读接口：`stats`（文章/评论/分类统计）、`chart-data`（`days`）、
`list-media`（`page/per/parent/search`）、`get-media-urls`（`cids`）。

### 5.4 样式约定

- 颜色**只用主题变量**，不要写死十六进制：`var(--md-primary)`、`var(--md-on-surface-variant)`、
  `var(--md-surface-container)`、`var(--md-outline-variant)`；深色主题会自动重映射。
- 需要自定义 CSS 时写在 `html` 字段的 `<style>` 里，并给所有选择器加唯一前缀（如 `.mycard-*`）。
  （2.1.49 起 `<style>` 会自动搬到卡片根元素上，`ab.ui.mount` 反复重绘也不会丢。）
- 图表颜色用 `ab.config.primaryColorHex` / `primaryColorDarkHex`，能跟随后台主题色。

---

## 六、两个可以直接用的模板

### 模板 A：纯 HTML（无需数据）

```json
{
  "id": "coff01",
  "icon": "lightbulb",
  "title": "写作提示",
  "html": "<div class=\"ab-ui-rows\"><div class=\"ab-ui-row\"><span class=\"ab-ui-row-label\">今天也写点什么吧</span></div></div>",
  "js": ""
}
```

### 模板 B：用组件库 + 站点统计（推荐）

```js
{
  "id": "statsbox1",
  "icon": "insights",
  "title": "文章概况",
  "html": "",
  "js": "(function(){var body=card.querySelector('.ab-custom-body');ab.ui.mount(body,ab.ui.rows([{icon:'article',label:'已发布',value:'—'},{icon:'forum',label:'评论',value:'—'},{icon:'folder',label:'分类',value:'—'}]));ab.request('stats').then(function(d){ab.ui.mount(body,ab.ui.rows([{icon:'article',label:'已发布',value:String(d.posts.total),href:'/admin/manage-posts.php'},{icon:'forum',label:'评论',value:String(d.comments.total),href:'/admin/manage-comments.php'},{icon:'folder',label:'分类',value:String(d.cats.total),href:'/admin/manage-categories.php'}]));}).catch(function(){ab.ui.mount(body,ab.ui.notice({text:'统计数据加载失败',tone:'danger'}));});})();"
}
```

> `js` 是**一个字符串**：换行写成 `\n`，引号用单引号或转义。写完建议让用户先看效果。

---

## 七、排错

| 现象 | 原因 / 处理 |
| --- | --- |
| 概要页完全没有卡片 | `dashboardCustomCardsEnabled` 不是 `"1"`；或当前用户不是管理员 |
| 卡片在但正文空白 | `html` 和 `js` 都为空；或 `js` 里没往 `.ab-custom-body` 写内容 |
| 只有部分卡片出现 | `id` 重复 → 后面的被覆盖；检查每个 `id` 唯一 |
| 卡片顶部出现红色错误块 | `js` 运行报错。看控制台 `[AB] 自定义卡片脚本出错`，检查是否用了 `let/const`、`?.` 之外的语法，或取到了空元素 |
| 改完没变化 | 设置写进库了但页面没刷新；让用户刷新概要页（或 AJAX 切走再切回） |
| 设置页里某张卡显示「卡片代码缺失」 | 该卡在设置项里只有元数据、数据表里却没代码 —— 之前的写入被截断过，重新用模板写一次 |
| 保存后所有插件设置都乱了 | 说明 options 行超了 64 KB（写太大的卡片导致）。让用户从「数据与安全」里的备份恢复，并把卡片拆小 |

---

## 八、用户想删卡片

把 `dashboardCustomCards` 里对应的那一项去掉、重新写回即可；
`dashboardCardOrder` 里残留的 `custom:<id>` 会被前端自动忽略，不用特意清理。
