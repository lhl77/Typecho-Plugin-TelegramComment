# Typecho插件：TelegramNotice

[![Latest tag](https://img.shields.io/github/v/tag/lhl77/Typecho-Plugin-TelegramNotice?label=tag&sort=semver)](https://github.com/lhl77/Typecho-Plugin-TelegramNotice/tags)
[![Release](https://img.shields.io/github/v/release/lhl77/Typecho-Plugin-TelegramNotice?label=release&sort=semver)](https://github.com/lhl77/Typecho-Plugin-TelegramNotice/releases)
[![Stars](https://img.shields.io/github/stars/lhl77/Typecho-Plugin-TelegramNotice?style=flat)](https://github.com/lhl77/Typecho-Plugin-TelegramNotice/stargazers)
[![License](https://img.shields.io/github/license/lhl77/Typecho-Plugin-TelegramNotice)](https://github.com/lhl77/Typecho-Plugin-TelegramNotice/blob/main/LICENSE)
[![Typecho](https://img.shields.io/badge/Typecho-Plugin-blue)](https://typecho.org/)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D7.2-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![Telegram Bot API](https://img.shields.io/badge/Telegram-Bot%20API-26a5e4?logo=telegram&logoColor=white)](https://core.telegram.org/bots/api)
[![Webhook](https://img.shields.io/badge/Webhook-Supported-success)](https://core.telegram.org/bots/api#setwebhook)
[![Status](https://img.shields.io/badge/Status-Active-success)](#)
[![PRs](https://img.shields.io/badge/PRs-Welcome-brightgreen)](#contributing)

Typecho 插件：通过 Telegram 机器人推送评论通知，并提供后台文章手动推送（支持多 Chat ID 群发、邮箱绑定、Telegram 回复评论、评论快捷审核）。


---

## 功能清单

- 评论通知推送（HTML 模板）
- 多 Chat ID 群发（逗号/换行分隔）
- 邮箱 → Chat ID 绑定（用于“定向推送 + 可在 Telegram 中回复”）
- Telegram 内联按钮：通过 / 垃圾 / 删除（回调走 Webhook）
- 后台管理页：文章列表手动推送到频道/群（可搜索、分页）
- 后台：一键检测/配置 Webhook
- 后台：版本检查（GitHub Tags），发现新版本红色提示并提供下载按钮

---

## 环境要求

- Typecho（1.2.1+）
- PHP 7.2+（建议 7.4/8.x）
- 站点需可被 Telegram 访问（Webhook 必须是公网 HTTPS 可达）

---

## 安装

1. 下载插件并解压到：
   - `usr/plugins/TelegramNotice/`
2. 确认目录结构类似：
   - `usr/plugins/TelegramNotice/Plugin.php`
   - `usr/plugins/TelegramNotice/TelegramComment_Action.php`
   - `usr/plugins/TelegramNotice/push.php`
3. 进入 Typecho 后台 → **控制台 → 插件**，启用 **TelegramNotice**。

---

## 配置说明

进入 Typecho 后台 → 插件 → TelegramNotice 设置：

### 1) Bot Token（必填）
从 [@BotFather](https://t.me/botfather) 获取。

### 2) Webhook Secret（建议填写）
用于校验 Telegram Webhook 请求来源，防止被伪造请求调用管理接口。

插件会使用类似 URL：

- `https://你的域名/action/telegram-comment?do=webhook&secret=xxxx`

### 3) 评论推送 Chat ID（必填，可多个）
- 多个用 **逗号** 或 **换行** 分隔
- 私聊通常为纯数字
- 群组/频道通常为 `-100...` 开头

### 4) 邮箱 → Chat ID 绑定（可选）
格式：每行一条

```text
user@example.com=123456789
foo@bar.com=-1001234567890
```

说明：
- 当评论邮箱命中绑定时，可定向推送给对应 chat_id
- 若开启“命中邮箱绑定时仍群发默认 Chat ID”，则会同时群发

### 5) 模板
- 评论推送模板：支持变量 `{title} {author} {text} {permalink} ...`
- 文章推送模板：支持变量 `{title} {excerpt} {permalink} {created} {cid}`

---

## Webhook 一键配置 / 检测

在插件配置页点击：
- **一键配置 Webhook**
- **重新检测**

提示为绿色表示 URL 已匹配当前站点配置。

---

## 手动推送文章（后台管理页）

后台 → 扩展 → **Telegram文章推送**  
支持：
- 搜索标题/内容
- 分页
- 单篇点击“推送”按钮推送到 `pushChatId`（支持多个）

---

## Chat ID 获取方法（简要）

- 私聊：对机器人发消息后，调用 `getUpdates` 或用工具机器人查询
- 群/频道：将机器人加入群/频道并发一条消息，再用 `getUpdates` 获取 chat.id  
  （频道 chat_id 通常为 `-100...`）

---

## 常见问题

### 1) Webhook 配置后仍收不到消息
- 确认站点 **HTTPS** 且公网可达
- 服务器/防火墙未拦截
- 机器人 token 正确
- 后台“重新检测”显示已正确配置

### 2) 推送按钮点了没反应
- 检查 `pushChatId` 是否已配置
- 检查 Bot 是否在对应群/频道且有发言权限
- 查看服务器 PHP 错误日志

---

## 更新

- 插件配置页内置 **版本检查**（读取 GitHub Tags，Tag 格式 `v1.0.0`）
- 发现新版本会红色提示并提供“前往下载更新”按钮

---

## 许可

MIT（以仓库 `LICENSE` 为准）
