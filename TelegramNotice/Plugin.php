<?php
/**
 * TelegramNotice
 *
 * Telegram 推送评论通知、文章（支持多 Chat ID 群发、邮箱绑定、评论回复）。
 *
 * @package TelegramNotice
 * @author LHL
 * @version 1.1.1
 * @link https://github.com/lhl77/Typecho-Plugin-TelegramNotice
 */

namespace TypechoPlugin\TelegramNotice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use Typecho;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Utils;

class Plugin implements PluginInterface
{
    /** GitHub releases/tags */
    private const GITHUB_OWNER = 'lhl77';
    private const GITHUB_REPO  = 'Typecho-Plugin-TelegramNotice';

    /** 用于和远端 Tag 比较的当前版本（从文件头 @version 同步即可） */
    private const VERSION = '1.1.1';

    public static function activate(): string
    {
        Typecho\Plugin::factory('Widget_Feedback')->finishComment = __CLASS__ . '::onFinishComment';
        Typecho\Plugin::factory('Widget_Comments_Edit')->finishComment = __CLASS__ . '::onFinishComment';

        Utils\Helper::addAction('telegram-comment', 'TypechoPlugin\\TelegramNotice\\TelegramComment_Action');

        try {
            $menuIndex = Utils\Helper::addMenu('TelegramNotice');
            Utils\Helper::addPanel($menuIndex, 'TelegramNotice/push.php', 'Telegram文章推送', '手动推送文章到频道/群', 'administrator');
        } catch (\Throwable $e) {
        }

        try {
            $opt = Utils\Helper::options()->plugin('TelegramNotice');
            $token = trim((string)($opt->botToken ?? ''));
            if ($token !== '') {
                self::ensureWebhookConfigured($token);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return _t('TelegramNotice 启用成功');
    }

    public static function deactivate(): string
    {
        Utils\Helper::removeAction('telegram-comment');

        try {
            $menuIndex = Utils\Helper::removeMenu('TelegramNotice');
            if ($menuIndex !== null) {
                Utils\Helper::removePanel($menuIndex, 'TelegramNotice/push.php');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return _t('TelegramNotice 已禁用');
    }

    /**
     * 将 telegram-comment action 映射到本插件的 Action 类
     */
    public static function registerAction($widget, $action)
    {
    }

    public static function config(Form $form)
    {
        $showLog = true;
        try {
            $showLog = isset($_GET['webhook-result-log'])
                ? (trim((string)$_GET['webhook-result-log']) === 'true')
                : true;
        } catch (\Throwable $e) {
            $showLog = true;
        }

        $needSet = true;
        try {
            $opt = Utils\Helper::options()->plugin('TelegramNotice');
            $tokenVal = trim((string)($opt->botToken ?? ''));
            $wantUrl = self::getWantedWebhookUrl();

            if ($tokenVal !== '' && $wantUrl !== '') {
                $info = self::tgGetWebhookInfo($tokenVal);
                if (($info['ok'] ?? false) && isset($info['result'])) {
                    $currentUrl = (string)($info['result']['url'] ?? '');
                    $needSet = ($currentUrl === '' || $currentUrl !== $wantUrl);
                }
            }
        } catch (\Throwable $e) {
            $needSet = true;
        }

        $actionBase = rtrim((string)Utils\Helper::options()->siteUrl, '/') . '/action/telegram-comment';

        echo '<div class="typecho-option typecho-option-submit">';
        echo '  <label class="typecho-label">' . _t('TelegramNotice') . '</label>';
        echo '  <p class="description" style="margin-top:6px;">' . _t('Telegram 推送评论通知与审核（支持多 Chat ID 群发、邮箱绑定、评论回复）。') . '</p>';

        // ===== 版本检查 UI（增强：有新版本时红色提示 + 更新按钮）=====
        echo '  <style>
      .tg-ver-line{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:6px;}
      .tg-ver-ok{color:#1e8e3e;}
      .tg-ver-warn{color:#d63638;font-weight:600;}
      .tg-ver-muted{color:#666;}
      .tg-ver-btns .btn{display:inline-flex;align-items:center;justify-content:center;height:30px;line-height:30px;padding:0 10px;}
    </style>';

        echo '  <div class="tg-ver-line">';
        echo '    <span class="description" id="tg-ver-hint">' . _t('当前版本：') . htmlspecialchars(self::VERSION, ENT_QUOTES) . ' ...</span>';
        echo '    <span class="tg-ver-btns">';
        echo '      <button type="button" class="btn" id="tg-ver-check">' . _t('检查更新') . '</button>';
        // echo '      <a class="btn" target="_blank" href="https://github.com/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO . '/tags">' . _t('查看 Tags') . '</a>';
        echo '      <a class="btn primary" target="_blank" id="tg-ver-update" style="display:none;background-color:lightcoral" href="#">' . _t('前往下载更新') . '</a>';
        echo '    </span>';
        echo '  </div>';

        echo '  <a class="typecho-label" style="margin-top:6px;" target="_blank" href="https://github.com/lhl77/Typecho-Plugin-TelegramNotice">' . _t('Github项目') . '</a>&nbsp;<a class="typecho-label" style="margin-top:6px;" href="https://blog.lhl.one" target="_blank">' . _t('作者博客') . '</a><br/><br/>';
        echo '  <label class="typecho-label">' . _t('Webhook 操作') . '</label>';
        echo $needSet
            ? '  <p class="description" id="tg-webhook-hint" style="color:#d63638;">' . _t('检测结果：需要配置（Webhook 未设置或 URL 不一致）。请点击“一键配置 Webhook”。') . '</p>'
            : '  <p class="description" id="tg-webhook-hint" style="color:#1e8e3e;">' . _t('检测结果：Webhook 已正确配置。') . '</p>';
        echo '  <p class="description" style="margin-top:6px;">' . _t('说明：请设置 Bot Token 和 Webhook Secret 后再配置Webhook。') . '</p>';
        echo '  <p>';
        echo '    <button type="button" class="btn primary" id="tg-webhook-set">' . _t('一键配置 Webhook') . '</button> ';
        echo '    <button type="button" class="btn" id="tg-webhook-check">' . _t('重新检测') . '</button>';
        echo '  </p>';

        if ($showLog) {
            echo '  <pre id="tg-webhook-result" style="margin-top:10px;white-space:pre-wrap;"></pre>';
        } else {
            echo '  <pre id="tg-webhook-result" style="display:none;"></pre>';
        }

        echo '</div>';

        echo '<script>
(function(){
  var base = ' . json_encode($actionBase, JSON_UNESCAPED_UNICODE) . ';
  var hint = document.getElementById("tg-webhook-hint");
  var out = document.getElementById("tg-webhook-result");
  var showLog = ' . ($showLog ? 'true' : 'false') . ';

  function setHint(ok, text){
    if(!hint) return;
    hint.textContent = text;
    hint.style.color = ok ? "#1e8e3e" : "#d63638";
  }
  function pretty(obj){
    try { return JSON.stringify(obj, null, 2); } catch(e){ return String(obj); }
  }
  function getInput(name){
    var el = document.querySelector(\'input[name="\'+name+\'"], textarea[name="\'+name+\'"]\');
    if (el) return el.value || "";
    el = document.querySelector(\'input[name$="[\'+name+\']"], textarea[name$="[\'+name+\']"]\');
    if (el) return el.value || "";
    return "";
  }
  function buildPayload(doName){
    var token = (getInput("botToken") || "").trim();
    var secret = (getInput("webhookSecret") || "").trim();
    return "do=" + encodeURIComponent(doName)
      + "&botToken=" + encodeURIComponent(token)
      + "&webhookSecret=" + encodeURIComponent(secret);
  }
  function render(res){
    if(!out) return;

    if(!showLog){
      if(res && res.ok === true && res.mode === "check"){
        var need = !!res.needSet;
        setHint(!need, need ? "检测结果：需要配置（URL 不一致或未设置）" : "检测结果：Webhook 已正确配置");
      } else if(res && res.ok === true && res.mode === "set"){
        setHint(!!res.matched, !!res.matched ? "检测结果：Webhook 已正确配置" : "检测结果：需要配置（URL 仍不一致）");
      } else if(res && res.ok !== true){
        setHint(false, res.message ? ("检测结果：" + res.message) : "检测结果：需要配置（请求失败）");
      }
      return;
    }

    if(!res || typeof res !== "object"){
      out.textContent = "响应解析失败：\\n" + String(res);
      return;
    }
    if(res.ok !== true){
      setHint(false, res.message ? ("检测结果：" + res.message) : "检测结果：需要配置（请求失败）");
      out.textContent = "[失败]\\n"
        + "错误：" + (res.error || "unknown") + "\\n"
        + (res.message ? ("提示：" + res.message + "\\n") : "")
        + (res.detail ? ("详情：\\n" + pretty(res.detail) + "\\n") : "");
      return;
    }
    if(res.mode === "check"){
      var need = !!res.needSet;
      setHint(!need, need ? "检测结果：需要配置（URL 不一致或未设置）" : "检测结果：Webhook 已正确配置");
      out.textContent =
        "[检测成功]\\n"
        + (res.bot ? ("Bot：@" + res.bot.username + " (" + res.bot.id + ")\\n") : "")
        + "当前 URL：\\n" + (res.currentUrl || "(空)") + "\\n\\n";
      return;
    }
    if(res.mode === "set"){
      var matched = !!res.matched;
      setHint(matched, matched ? "检测结果：Webhook 已正确配置" : "检测结果：需要配置（URL 仍不一致）");
      out.textContent =
        "[配置完成]\\n"
        + (res.bot ? ("Bot：@" + res.bot.username + " (" + res.bot.id + ")\\n") : "")
        + (res.message ? ("结果：" + res.message + "\\n\\n") : "\\n");
      return;
    }
    out.textContent = pretty(res);
  }
  function post(doName){
    var url = base + "?do=" + encodeURIComponent(doName);
    if(out && showLog) out.textContent = "请求中...";

    var xhr = new XMLHttpRequest();
    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded; charset=UTF-8");
    xhr.onreadystatechange = function(){
      if(xhr.readyState !== 4) return;
      var txt = xhr.responseText || "";
      var res = null;
      try { res = JSON.parse(txt); } catch(e) { res = { ok:false, error:"bad_json", message:"响应不是 JSON", raw:txt }; }
      render(res);
    };
    xhr.send(buildPayload(doName));
  }

  var btnSet = document.getElementById("tg-webhook-set");
  var btnChk = document.getElementById("tg-webhook-check");
  if(btnSet) btnSet.addEventListener("click", function(){ post("webhookSet"); });
  if(btnChk) btnChk.addEventListener("click", function(){ post("webhookCheck"); });

  window.setTimeout(function(){
    post("webhookCheck");
  }, 50);
})();
</script>';

        // ===== 版本检查脚本（有新版本 -> 红色提示 + 显示更新按钮）=====
        echo '<script>
(function(){
  var cur = ' . json_encode(self::VERSION, JSON_UNESCAPED_UNICODE) . ';
  var owner = ' . json_encode(self::GITHUB_OWNER, JSON_UNESCAPED_UNICODE) . ';
  var repo  = ' . json_encode(self::GITHUB_REPO, JSON_UNESCAPED_UNICODE) . ';
  var hint  = document.getElementById("tg-ver-hint");
  var btn   = document.getElementById("tg-ver-check");
  var upBtn = document.getElementById("tg-ver-update");

  function setHint(text, cls){
    if(!hint) return;
    hint.textContent = text;
    hint.classList.remove("tg-ver-ok","tg-ver-warn","tg-ver-muted");
    if(cls) hint.classList.add(cls);
  }
  function showUpdate(tag){
    if(!upBtn) return;
    var safeTag = String(tag || "").trim();
    if(!safeTag) return;
    upBtn.href = "https://github.com/" + owner + "/" + repo + "/releases/tag/" + encodeURIComponent(safeTag);
    upBtn.style.display = "";
  }
  function hideUpdate(){
    if(!upBtn) return;
    upBtn.style.display = "none";
    upBtn.href = "#";
  }

  function normTag(tag){
    tag = String(tag || "").trim();
    if(tag[0] === "v" || tag[0] === "V") tag = tag.slice(1);
    return tag;
  }
  function parseVer(v){
    v = normTag(v);
    var m = v.match(/^(\d+)\.(\d+)\.(\d+)(?:[-+].*)?$/);
    if(!m) return null;
    return {maj:+m[1], min:+m[2], pat:+m[3], raw:v};
  }
  function cmp(a,b){
    if(a.maj!==b.maj) return a.maj-b.maj;
    if(a.min!==b.min) return a.min-b.min;
    return a.pat-b.pat;
  }

  async function check(){
    hideUpdate();
    if(btn){ btn.disabled = true; btn.textContent = "检查中..."; }
    setHint("当前版本：" + cur + "，", "tg-ver-muted");

    try{
      var url = "https://api.github.com/repos/" + owner + "/" + repo + "/tags?per_page=100";
      var res = await fetch(url, { method:"GET" });
      if(!res.ok) throw new Error("http_" + res.status);

      var tags = await res.json();
      if(!Array.isArray(tags) || tags.length === 0) throw new Error("no_tags");

      var curV = parseVer(cur);
      var best = null;
      var bestTagName = "";

      for(var i=0;i<tags.length;i++){
        var name = tags[i] && tags[i].name ? String(tags[i].name) : "";
        var v = parseVer(name);
        if(!v) continue;
        if(!best || cmp(v,best) > 0){
          best = v;
          bestTagName = name; // 保留原始 tag（用于链接，比如 v1.2.3）
        }
      }

      if(!best){
        setHint("当前版本：" + cur + "，未找到符合格式的 Tag（需 v1.0.0）", "tg-ver-muted");
        return;
      }

      if(!curV){
        setHint("当前版本：" + cur + "（无法解析），最新版本：" + best.raw, "tg-ver-muted");
        showUpdate(bestTagName);
        return;
      }

      if(cmp(best, curV) > 0){
        setHint("发现新版本：" + best.raw + "（当前 " + cur + "），需要更新", "tg-ver-warn");
        showUpdate(bestTagName);
      }else{
        setHint("当前版本：" + cur + "，已是最新（最新 " + best.raw + "）", "tg-ver-ok");
      }
    }catch(e){
      setHint("当前版本：" + cur + "，检查失败（可能被 GitHub 限流或网络不可达）", "tg-ver-muted");
    }finally{
      if(btn){ btn.disabled = false; btn.textContent = "检查更新"; }
    }
  }

  if(btn) btn.addEventListener("click", check);
  window.setTimeout(check, 120);
})();
</script>';

        // Bot Token
        $token = new Text(
            'botToken',
            null,
            '',
            _t('Bot Token （必填）'),
            _t('从 <a href="https://t.me/botfather">@BotFather</a> 获取的 token，例如：123456:ABC-DEF...')
        );
        $form->addInput($token);

        // Webhook Secret
        $webhookSecret = new Text(
            'webhookSecret',
            null,
            '',
            _t('Webhook Secret（可留空，建议填写）'),
            _t('用于校验 webhook 请求来源（建议生成一段随机字符串）。将拼接到 /action/telegram-comment?do=webhook&secret=...')
        );
        $form->addInput($webhookSecret);
        
        $chatId = new Text(
            'chatId',
            null,
            '',
            _t('评论推送：默认 Chat ID（必填，可多个）'),
            _t('多个 chat_id 用逗号或换行分隔；个人为纯数字；群组/频道通常为 -100 开头的数字')
        );
        $form->addInput($chatId);

        $emailMap = new Textarea(
            'emailChatMap',
            null,
            "",
            _t('评论推送：邮箱 -> Chat ID 绑定 (选填，如需回复功能则必填)'),
            _t("每行一条：email=chat_id\n示例：user@example.com=123456789\n命中后可单独推送给该 chat_id（并可叠加默认群发）")
        );
        $form->addInput($emailMap);

        $alsoSendDefault = new Text(
            'alsoSendDefault',
            null,
            '1',
            _t('评论推送：命中邮箱绑定时仍群发默认 Chat ID'),
            _t('1=是，0=否（默认 1）')
        );
        $form->addInput($alsoSendDefault);

        // 消息模板（HTML）
        $tplDefault = "🎉 您的文章 <b>{title}</b> 有新的回复！\n\n<b>{author} ：</b><code>{text}</code>";
        $tpl = new Textarea(
            'messageTpl',
            null,
            $tplDefault,
            _t('评论推送：模板（HTML）'),
            _t('变量：{title} {author} {text} {permalink} {ip} {created} {coid} {mail}')
        );
        $form->addInput($tpl);

        $pushChatId = new Text(
            'pushChatId',
            null,
            '',
            _t('文章推送：Chat ID（频道/群）'),
            _t('用于文章手动推送的目标 chat_id（例如频道：-100xxxxxxxxxx）。可多个，逗号/换行分隔。')
        );
        $form->addInput($pushChatId);

        // 文章推送模板
        $pushTplDefault = "📰 <b>{title}</b>\n\n{excerpt}\n\n<a href=\"{permalink}\">点击阅读</a>";
        $pushTpl = new Textarea(
            'pushTpl',
            null,
            $pushTplDefault,
            _t('文章推送：模板（HTML）'),
            _t('变量：{title} {excerpt} {permalink} {created} {cid}')
        );
        $form->addInput($pushTpl);

        $siteUrl = rtrim((string)Utils\Helper::options()->siteUrl, '/');
        $adminUrl = $siteUrl . '/admin/';
        $pushPage = $adminUrl . 'options-plugin.php?config=TelegramNotice&tab=push'; 

        echo '<div class="typecho-option typecho-option-submit">';
        echo '  <label class="typecho-label">' . _t('文章推送') . '</label>';
        echo '  <p class="description">' . _t('手动推送文章：打开下方管理页。') . '</p>';

        echo '  <p><a class="btn primary" style="display:inline-flex;align-items:center;justify-content:center;height:32px;padding:0 12px;line-height:32px;box-sizing:border-box;" href="' . htmlspecialchars($adminUrl . 'extending.php?panel=TelegramNotice%2Fpush.php', ENT_QUOTES) . '" target="_self">' . _t('打开 Telegram 文章推送页') . '</a></p>';
        echo '</div>';
    }

    public static function configCheck($settings): void
    {
        try {
            $token = trim((string)($settings->botToken ?? ''));
            $secret = (string)($settings->webhookSecret ?? '');

            // token 为空时无法设置 webhook
            if ($token === '') {
                return;
            }

            $siteUrl = (string)Utils\Helper::options()->siteUrl;
            $wantUrl = self::buildWebhookUrl($siteUrl, $secret);

            self::tgSetWebhook($token, $wantUrl);
        } catch (\Throwable $e) {
            // 不阻断保存流程
        }
    }

    /**
     * 仅检测当前 webhook 是否已配置为 wantUrl，并输出“成功/未配置”
     */
    private static function checkWebhookStatusText(string $token, string $wantUrl): string
    {
        $token = trim($token);
        $wantUrl = trim($wantUrl);

        if ($token === '' || $wantUrl === '') {
            return "未配置（参数不完整）";
        }

        $info = self::tgApi($token, 'getWebhookInfo', []);
        if (!($info['ok'] ?? false)) {
            return "未配置（检测失败：getWebhookInfo）";
        }

        $curUrl = (string)($info['result']['url'] ?? '');
        if ($curUrl !== '' && $curUrl === $wantUrl) {
            return "成功（Webhook 已配置）";
        }

        return "未配置（Webhook 为空或不一致）";
    }
    public static function getWantedWebhookUrl(): string
    {
        $opt = Utils\Helper::options()->plugin('TelegramNotice');
        return self::buildWebhookUrl((string)Utils\Helper::options()->siteUrl, (string)($opt->webhookSecret ?? ''));
    }

    public static function tgGetWebhookInfo(string $token): array
    {
        return self::tgApi($token, 'getWebhookInfo', []);
    }

    public static function tgSetWebhook(string $token, string $url): array
    {
        // 确保能收到 inline keyboard 的回调：callback_query
        return self::tgApi($token, 'setWebhook', [
            'url' => $url,
            'allowed_updates' => json_encode(['callback_query', 'message'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function personalConfig(Form $form)
    {
    }

    public static function onFinishComment($comment)
    {
        $opt = Utils\Helper::options()->plugin('TelegramNotice');

        $token = trim((string)($opt->botToken ?? ''));
        $tpl = (string)($opt->messageTpl ?? '');
        if ($token === '') {
            return;
        }

        try {
            self::ensureWebhookConfigured($token);
        } catch (\Throwable $e) {
            // ignore
        }

        if (isset($comment->authorId) && (int)$comment->authorId === 1) {
            return;
        }

        $msg = self::renderTemplate($tpl, $comment);

        // 在消息末尾追加“回复关联标记"
        $cid = (int)($comment->cid ?? 0);
        $coid = (int)($comment->coid ?? 0);
        if ($cid > 0 && $coid > 0) {
            $secret = (string)($opt->webhookSecret ?? '');
            $payload = "cid={$cid}&coid={$coid}";
            $sig = self::signCallback($secret, $payload);

            $msg .= "\n\n#TG:{$cid}:{$coid}:{$sig}";
        }

        $defaultChatIds = self::parseChatIds((string)($opt->chatId ?? ''));
        $mappedChatId = self::mapEmailToChatId((string)($opt->emailChatMap ?? ''), (string)($comment->mail ?? ''));

        $targets = [];

        if ($mappedChatId !== '') {
            $targets[] = $mappedChatId;

            $also = trim((string)($opt->alsoSendDefault ?? '1'));
            if ($also !== '0') {
                $targets = array_merge($targets, $defaultChatIds);
            }
        } else {
            $targets = $defaultChatIds;
        }

        $targets = array_values(array_unique(array_filter($targets, static fn($v) => $v !== '')));
        if (!$targets) {
            return;
        }

        $commentUrl = '';
        if (!empty($comment->permalink)) {
            $commentUrl = (string)$comment->permalink;
        } elseif (!empty($comment->cid) && !empty($comment->coid)) {
            try {
                $post = Utils\Helper::widgetById('contents', (int)$comment->cid);
                if ($post && $post->have()) {
                    $commentUrl = (string)$post->permalink . '#comment-' . (int)$comment->coid;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        foreach ($targets as $chatId) {
            $replyMarkup = self::buildModerationKeyboard(
                (string)($opt->webhookSecret ?? ''),
                (int)($comment->coid ?? 0),
                $commentUrl
            );

            self::sendTelegram($token, $chatId, $msg, $replyMarkup);
        }
    }

    private static function parseChatIds(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];
        $parts = preg_split('/[,\n\r]+/', $raw) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn($v) => $v !== ''));
    }

    private static function mapEmailToChatId(string $mapText, string $mail): string
    {
        $mail = strtolower(trim($mail));
        if ($mail === '') return '';

        $lines = preg_split('/\r\n|\r|\n/', (string)$mapText) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);

            // PHP 7.x 没有 str_starts_with，这里用兼容函数
            if ($line === '' || self::strStartsWith($line, '#')) continue;

            $pos = strpos($line, '=');
            if ($pos === false) continue;

            $k = strtolower(trim(substr($line, 0, $pos)));
            $v = trim(substr($line, $pos + 1));

            if ($k !== '' && $k === $mail) {
                return $v;
            }
        }
        return '';
    }

    /**
     * PHP < 8.0 兼容：str_starts_with
     */
    private static function strStartsWith(string $haystack, string $needle): bool
    {
        if ($needle === '') return true;
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }

    private static function buildModerationKeyboard(string $secret, int $coid, string $permalink = ''): array
    {
        $sig = self::signCallback($secret, "coid={$coid}");
        $mk = fn(string $act) => "mod:{$act}:{$coid}:{$sig}";

        // 默认为：通过 + 垃圾 + 删除
        $row1 = [
            ['text' => '通过', 'callback_data' => $mk('approve')],
            ['text' => '垃圾', 'callback_data' => $mk('spam')],
            ['text' => '删除', 'callback_data' => $mk('delete')],
        ];

        // 如果评论已通过：不显示“通过”
        try {
            $db = \Typecho\Db::get();
            $prefix = $db->getPrefix();
            $row = $db->fetchRow($db->select('status')->from($prefix . 'comments')->where('coid = ?', $coid)->limit(1));
            $status = is_array($row) ? (string)($row['status'] ?? '') : '';
            if ($status === 'approved') {
                $row1 = [
                    ['text' => '垃圾', 'callback_data' => $mk('spam')],
                    ['text' => '删除', 'callback_data' => $mk('delete')],
                ];
            }
        } catch (\Throwable $e) {
            // 出错就按默认按钮显示
        }

        // 查看评论（URL 按钮不会走 webhook callback，直接打开链接）
        $kb = ['inline_keyboard' => [$row1]];
        $permalink = trim($permalink);
        if ($permalink !== '') {
            $kb['inline_keyboard'][] = [
                ['text' => '查看评论', 'url' => $permalink],
            ];
        }

        return $kb;
    }

    private static function renderTemplate(string $tpl, $comment): string
    {
        $title = '';
        $permalink = '';
        try {
            $post = Utils\Helper::widgetById('contents', (int)$comment->cid);
            if ($post && $post->have()) {
                $title = (string)$post->title;
                $permalink = (string)$post->permalink;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if ($tpl === '') {
            // 默认模板
            $tpl = "🎉 您的文章 <b>{title}</b> 有新的回复！\n\n<b>{author} ：</b><code>{text}</code>\n\n{permalink}\n\n#coid:{coid}";
        }

        $vars = [
            '{title}' => self::escapeHtml($title),
            '{author}' => self::escapeHtml((string)($comment->author ?? '')),
            '{text}' => self::escapeHtml(self::trimText((string)($comment->text ?? ''), 800)),
            '{permalink}' => self::escapeHtml($permalink !== '' ? $permalink . '#comment-' . (int)$comment->coid : ''),
            '{ip}' => self::escapeHtml((string)($comment->ip ?? '')),
            '{created}' => self::escapeHtml(date('Y-m-d H:i:s', (int)($comment->created ?? time()))),
            '{coid}' => self::escapeHtml((string)($comment->coid ?? '')),
            '{mail}' => self::escapeHtml((string)($comment->mail ?? '')),
        ];

        return strtr($tpl, $vars);
    }

    private static function escapeHtml(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function trimText(string $s, int $maxLen): string
    {
        $s = trim($s);
        if (mb_strlen($s, 'UTF-8') > $maxLen) {
            return mb_substr($s, 0, $maxLen, 'UTF-8') . '...';
        }
        return $s;
    }

    private static function sendTelegram(string $token, string $chatId, string $text, ?array $replyMarkup = null, ?int $replyToMessageId = null, ?string $permalink = null): void
    {
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $post = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup) {
            $post['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
        }
        if ($replyToMessageId) {
            $post['reply_to_message_id'] = $replyToMessageId;
            $post['allow_sending_without_reply'] = true;
        }
        if($permalink){
            
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($post),
                'timeout' => 10,
            ]
        ]);

        @file_get_contents($url, false, $context);
    }

    private static function guessWebhookUrl(): string
    {
        try {
            $opt = Utils\Helper::options()->plugin('TelegramNotice');
            $siteUrl = (string)Utils\Helper::options()->siteUrl;
            $secret = (string)($opt->webhookSecret ?? '');
            return self::buildWebhookUrl($siteUrl, $secret);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function buildWebhookUrl(string $siteUrl, string $secret): string
    {
        $siteUrl = rtrim(trim($siteUrl), '/') . '/';
        // 目标格式：/action/telegram-comment?do=webhook&secret=xxx
        $url = $siteUrl . 'action/telegram-comment?do=webhook';
        if ($secret !== '') {
            $url .= '&secret=' . rawurlencode($secret);
        }
        return $url;
    }

    private static function ensureWebhook(string $token, string $wantUrl): string
    {
        $token = trim($token);
        $wantUrl = trim($wantUrl);

        if ($token === '' || $wantUrl === '') {
            return "未配置（参数不完整）";
        }

        $info = self::tgApi($token, 'getWebhookInfo', []);
        if (!($info['ok'] ?? false)) {
            return "未配置（检测失败：getWebhookInfo）";
        }

        $curUrl = (string)($info['result']['url'] ?? '');
        if ($curUrl === $wantUrl) {
            return "成功（Webhook 已配置）";
        }

        $set = self::tgApi($token, 'setWebhook', ['url' => $wantUrl]);
        if (!($set['ok'] ?? false)) {
            return "未配置（设置失败：setWebhook）";
        }

        return "成功（Webhook 已配置）";
    }

    public static function tgApi(string $token, string $method, array $params): array
    {
        $url = "https://api.telegram.org/bot{$token}/{$method}";
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($params),
                'timeout' => 10,
            ]
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false || $raw === '') {
            return ['ok' => false, 'error' => 'network_error', 'method' => $method];
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'bad_json', 'raw' => $raw, 'method' => $method];
        }
        return $json;
    }

    private static function pretty(array $a): string
    {
        return json_encode($a, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public static function signCallback(string $secret, string $payload): string
    {
        $secret = (string)$secret;
        if ($secret === '') return substr(sha1($payload), 0, 10);
        return substr(hash_hmac('sha256', $payload, $secret), 0, 12);
    }

    /**
     * 确保 webhook 正确配置为当前 wantUrl（带 secret + allowed_updates）
     * 只在发现不一致时才调用 setWebhook，避免每次都打 Telegram API。
     */
    private static function ensureWebhookConfigured(string $token): void
    {
        $token = trim($token);
        if ($token === '') return;

        $wantUrl = self::getWantedWebhookUrl();
        if ($wantUrl === '') return;

        $info = self::tgGetWebhookInfo($token);
        if (!($info['ok'] ?? false)) {
            // getWebhookInfo 失败时也尝试 set 一次（但不抛错）
            self::tgSetWebhook($token, $wantUrl);
            return;
        }

        $curUrl = (string)($info['result']['url'] ?? '');
        if ($curUrl !== $wantUrl) {
            self::tgSetWebhook($token, $wantUrl);
        }
    }

    private function out(string $text, bool $alert, string $reply, string $act = '', array $replyMarkup = ['inline_keyboard' => []]): array
    {
        return ['text' => $text, 'alert' => $alert, 'reply' => $reply, 'act' => $act, 'reply_markup' => $replyMarkup];
    }

}