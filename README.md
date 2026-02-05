# Typecho插件：TelegramNotice

[![Typecho](https://img.shields.io/badge/Typecho-Plugin-blue)](https://typecho.org/)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D7.0-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![Telegram Bot API](https://img.shields.io/badge/Telegram-Bot%20API-26a5e4?logo=telegram&logoColor=white)](https://core.telegram.org/bots/api)
[![Webhook](https://img.shields.io/badge/Webhook-Supported-success)](https://core.telegram.org/bots/api#setwebhook)
[![Inline Keyboard](https://img.shields.io/badge/Inline%20Keyboard-Supported-success)](https://core.telegram.org/bots/api#inlinekeyboardmarkup)
[![License](https://img.shields.io/badge/License-MIT-green)](#license)
[![Status](https://img.shields.io/badge/Status-Active-success)](#)
[![PRs](https://img.shields.io/badge/PRs-Welcome-brightgreen)](#contributing)

Telegram 推送 Typecho 评论通知与审核（支持多 Chat ID 群发、邮箱绑定、并支持在 Telegram 内直接“回复评论”）。

---

## 功能特性

- 评论通知推送到 Telegram（支持 HTML 模板）
- 支持多个默认 `chat_id` 群发（逗号/换行分隔）
- 支持 **邮箱 -> Chat ID** 绑定：命中邮箱时可定向推送
- 支持评论审核按钮（Inline Keyboard）：
  - 通过 / 垃圾 / 删除https://blog.lhl.one/artical/815.html
- 支持在 Telegram 中 **直接回复推送消息** 来发布 Typecho 回复评论
- Webhook 一键配置/检测（插件设置页内按钮触发 AJAX）
(TODO:文章推送)
---

## 目录结构说明

你的仓库里同时存在两套文件（根目录与 `TelegramNotice/` 目录），核心实现位于：

- 插件主类：[`TypechoPlugin\TelegramNotice\Plugin`](TelegramNotice/Plugin.php)（[TelegramNotice/Plugin.php](TelegramNotice/Plugin.php)）
- Action（Webhook/AJAX）：[`TypechoPlugin\TelegramNotice\TelegramComment_Action`](TelegramNotice/TelegramComment_Action.php)（[TelegramNotice/TelegramComment_Action.php](TelegramNotice/TelegramComment_Action.php)）

> 建议实际部署时只保留一套代码路径，避免 Typecho 加载到旧文件导致行为不一致。

---

## 安装

1. 将本项目放到 Typecho 插件目录，例如：
   - `usr/plugins/TelegramNotice/`
2. 确保插件目录名与插件包名一致（通常目录名应为 `TelegramNotice`）
3. 后台启用插件：控制台 → 插件 → **TelegramNotice** → 启用

---

## 配置说明（后台插件设置）

在插件设置页配置以下字段（对应 [`TypechoPlugin\TelegramNotice\Plugin::config`](TelegramNotice/Plugin.php)）：

### 1) Bot Token（必填）

从 [@BotFather](https://t.me/botfather) 获取。

### 2) 默认 Chat ID（必填，可多个）

- 私聊：纯数字（如 `123456789`）
- 群组/频道：通常 `-100` 开头（如 `-1001234567890`）
- 多个用 **逗号** 或 **换行** 分隔

### 3) Webhook Secret（建议填写）

用于校验 webhook 请求来源（会拼到 URL 的 `secret=...` 上）：
- Webhook URL 形如：`/action/telegram-comment?do=webhook&secret=xxx`

### 4) 邮箱 -> Chat ID 绑定（可选，但要用“Telegram 回复评论”则必填）

格式：每行一条

```text
user@example.com=123456789
admin@example.com=-1001234567890
```

### 5) 命中邮箱绑定时仍群发默认 Chat ID

- `1`：是（默认）
- `0`：否（只发给绑定的 chat_id）

### 6) 消息模板（HTML）

默认模板变量（来自 [`TypechoPlugin\TelegramNotice\Plugin::renderTemplate`](TelegramNotice/Plugin.php)）：
- `{title}` `{author}` `{text}` `{permalink}` `{ip}` `{created}` `{coid}` `{mail}`

---

## Webhook 配置（推荐）

插件设置页顶部提供：
- **一键配置 Webhook**
- **重新检测**

对应 Action 入口：`/action/telegram-comment`  
由 [`TypechoPlugin\TelegramNotice\TelegramComment_Action::execute`](TelegramNotice/TelegramComment_Action.php) 分发：
- `do=webhookCheck`
- `do=webhookSet`
- `do=webhook`（Telegram Webhook 回调）

插件也会在以下场景尽量自动确保 webhook 正确：
- 启用插件时（[`TypechoPlugin\TelegramNotice\Plugin::activate`](TelegramNotice/Plugin.php)）
- 有新评论推送时（[`TypechoPlugin\TelegramNotice\Plugin::onFinishComment`](TelegramNotice/Plugin.php)）
- 保存插件配置时强制 setWebhook（[`TypechoPlugin\TelegramNotice\Plugin::configCheck`](TelegramNotice/Plugin.php)）

---

## Telegram 内审核评论

推送消息会带 Inline Keyboard（见 [`TypechoPlugin\TelegramNotice\Plugin::buildModerationKeyboard`](TelegramNotice/Plugin.php)）：
- 通过：设置评论状态为 `approved`
- 垃圾：设置评论状态为 `spam`
- 删除：删除评论记录

回调处理在 [`TypechoPlugin\TelegramNotice\TelegramComment_Action::handleCallback`](TelegramNotice/TelegramComment_Action.php)。

---

## Telegram 内直接回复评论（Reply-to 发布回复）

工作流程：

1. 插件推送消息末尾会追加标记（见 [`TypechoPlugin\TelegramNotice\Plugin::onFinishComment`](TelegramNotice/Plugin.php)）：

   `#TG:cid:coid:sig`

2. 你在 Telegram 中 **回复这条推送消息**，输入文本并发送。
3. Webhook 收到 `message.reply_to_message` 后解析标记并写入 Typecho 评论表（见 [`TypechoPlugin\TelegramNotice\TelegramComment_Action::handleTelegramWebhook`](TelegramNotice/TelegramComment_Action.php)）。

安全限制：
- 必须当前 `chat_id` 能在“邮箱 -> Chat ID 绑定”里反查到邮箱（防止陌生人滥用）
- 必须签名校验通过（签名算法见 [`TypechoPlugin\TelegramNotice\Plugin::signCallback`](TelegramNotice/Plugin.php)）
- 原评论必须为 `approved` 才允许回复

注：回复时ip取`127.0.0.1`,Agent取`TelegramReply`

---

## 常见问题

### 1) 收不到按钮回调 / 回复消息
确认 `setWebhook` 时包含 `allowed_updates`（本插件设置为 `callback_query` 和 `message`），见：
- [`TypechoPlugin\TelegramNotice\Plugin::tgSetWebhook`](TelegramNotice/Plugin.php)

### 2) Webhook URL 不一致
检查：
- Typecho 的 `站点地址`（`siteUrl`）是否正确指向可公网访问的域名
- 是否启用了 HTTPS（Telegram 推荐 HTTPS）
- `webhookSecret` 是否变化（变化后需要重新 setWebhook）

---

## 贡献

欢迎提交 Issue/PR

---

## License

MIT License
