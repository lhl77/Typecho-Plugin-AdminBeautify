<?php
/**
 * AB AI 助手 —— 服务端核心
 *
 *  · 只做 OpenAI 兼容协议的流式代理（DeepSeek / 智谱 / 通义 / Ollama 等同一协议）
 *  · API Key 只在服务端；前端拿到的配置一律脱敏
 *  · baseUrl 做 SSRF 校验（默认拒绝内网/环回，Ollama 本地场景特判放行）
 *  · 按登录用户做简单限流（用量存 {prefix}abadmin，键 card-ai-rate-<uid>）
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
class AdminBeautify_AI
{
    const VERSION = '0.2.0';
    const AD_REPO_RAW = 'https://raw.githubusercontent.com/lhl77/Typecho-Raw-Nontification/main/AdminBeautify/ai_ad.md';
    const AD_MIRROR = 'https://gh1.lhl.one/';
    const AD_CONTACT = 'admin@lhl.one';
    const AD_CACHE_KEY = 'ai-ad-cache';
    const AD_CACHE_TTL = 600;
    private static $chatImages = array();
    public static function adContent($force = false)
    {
        $cacheFile = self::adCacheFile();
        if (!$force && is_file($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            $row = $raw ? @json_decode($raw, true) : null;
            if (is_array($row) && isset($row['at']) && (time() - (int) $row['at']) < self::AD_CACHE_TTL) {
                $md = isset($row['markdown']) ? (string) $row['markdown'] : '';
                return array(
                    'markdown'    => $md,
                    'contact'     => self::AD_CONTACT,
                    'placeholder' => trim($md) === '',
                );
            }
        }
        $md = self::fetchAdMarkdown();
        if ($md === null) {
            if (is_file($cacheFile)) {
                $raw = @file_get_contents($cacheFile);
                $row = $raw ? @json_decode($raw, true) : null;
                if (is_array($row)) {
                    $old = isset($row['markdown']) ? (string) $row['markdown'] : '';
                    return array(
                        'markdown'    => $old,
                        'contact'     => self::AD_CONTACT,
                        'placeholder' => trim($old) === '',
                    );
                }
            }
            return array('markdown' => '', 'contact' => self::AD_CONTACT, 'placeholder' => true);
        }
        @file_put_contents($cacheFile, json_encode(array(
            'markdown' => $md,
            'at'       => time(),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return array(
            'markdown'    => $md,
            'contact'     => self::AD_CONTACT,
            'placeholder' => trim($md) === '',
        );
    }
    private static function adCacheFile()
    {
        $dir = dirname(__FILE__) . '/store/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/ai-ad.json';
    }
    private static function fetchAdMarkdown()
    {
        $urls = array(
            self::AD_MIRROR . self::AD_REPO_RAW,
            self::AD_REPO_RAW,
        );
        foreach ($urls as $url) {
            $body = self::httpGetText($url);
            if ($body !== null) {
                return $body;
            }
        }
        return null;
    }
    private static function httpGetText($url)
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'AdminBeautify-AI/' . self::VERSION,
        ));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) {
            return null;
        }
        $body = trim((string) $body);
        if ($body === '') {
            return null;
        }
        if (mb_strlen($body) > 4000) {
            $body = mb_substr($body, 0, 4000);
        }
        return $body;
    }
    private static function presets()
    {
        return array(
            'openai'   => array('url' => 'https://api.openai.com/v1',              'model' => 'gpt-4o-mini'),
            'deepseek' => array('url' => 'https://api.deepseek.com/v1',            'model' => 'deepseek-chat'),
            'zhipu'    => array('url' => 'https://open.bigmodel.cn/api/paas/v4',   'model' => 'glm-4-flash'),
            'qwen'     => array('url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1', 'model' => 'qwen-plus'),
            'ollama'   => array('url' => 'http://127.0.0.1:11434/v1',             'model' => 'llama3'),
            'custom'   => array('url' => '',                                       'model' => ''),
        );
    }
    public static function config()
    {
        $opt = AdminBeautify_Plugin::pluginOptions();
        $provider = isset($opt->ai_provider) ? (string) $opt->ai_provider : 'deepseek';
        $presets = self::presets();
        $def = isset($presets[$provider]) ? $presets[$provider] : $presets['custom'];
        $baseUrl = (isset($opt->ai_base_url) && trim((string) $opt->ai_base_url) !== '')
            ? rtrim(trim((string) $opt->ai_base_url), '/')
            : $def['url'];
        $model = (isset($opt->ai_model) && trim((string) $opt->ai_model) !== '')
            ? trim((string) $opt->ai_model)
            : $def['model'];
        $enabledRaw = isset($opt->ai_enabled) ? trim((string) $opt->ai_enabled) : '';
        $enabled = ($enabledRaw === '') ? true : ($enabledRaw === '1');
        return array(
            'enabled'         => $enabled,
            'provider'        => $provider,
            'base_url'        => $baseUrl,
            'api_key'         => isset($opt->ai_api_key) ? (string) $opt->ai_api_key : '',
            'model'           => $model,
            'temperature'     => (float) (isset($opt->ai_temperature) ? $opt->ai_temperature : 0.7),
            'max_tokens'      => (int) (isset($opt->ai_max_tokens) ? $opt->ai_max_tokens : 4096),
            'system_prompt'   => isset($opt->ai_system_prompt) ? (string) $opt->ai_system_prompt : '',
            'allow_non_admin' => isset($opt->ai_allow_non_admin) && (string) $opt->ai_allow_non_admin === '1',
            'rate_per_min'    => (int) (isset($opt->ai_rate_per_min) ? $opt->ai_rate_per_min : 10),
        );
    }
    public static function publicConfig($user)
    {
        $c = self::config();
        $isAdmin = $user->pass('administrator', true);
        $hasKey = $c['api_key'] !== '';
        $hasUrl = $c['base_url'] !== '';
        $ready = $c['enabled'] && $hasKey && $hasUrl;
        $canUse = $isAdmin || $c['allow_non_admin'];
        $missing = array();
        if (!$c['enabled']) $missing[] = '未启用';
        if (!$hasUrl) $missing[] = 'API 地址';
        if (!$hasKey) $missing[] = 'API Key';
        return array(
            'enabled'   => $c['enabled'] && $canUse,
            'available' => $ready && $canUse,
            'isAdmin'   => $isAdmin,
            'model'     => $c['model'],
            'hasKey'    => $hasKey,
            'hasUrl'    => $hasUrl,
            'missing'   => $missing,
            'version'   => self::VERSION,
            'beta'      => true,
            'ad'        => self::adContent(),
        );
    }
    public static function safeBaseUrl($url, $provider = '')
    {
        $url = trim((string) $url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return false;
        }
        $parts = @parse_url($url);
        if (!isset($parts['host'])) {
            return false;
        }
        $host = strtolower((string) $parts['host']);
        if ($provider === 'ollama' && in_array($host, array('localhost', '127.0.0.1', '::1'), true)) {
            return true;
        }
        if (in_array($host, array('localhost', '127.0.0.1', '0.0.0.0', '::1'), true)) {
            return false;
        }
        if (preg_match('/^(10\.|192\.168\.|169\.254\.|172\.(1[6-9]|2\d|3[01])\.)/', $host)) {
            return false;
        }
        return true;
    }
    public static function systemPrompt($page, $role, $uid = 0)
    {
        $site = '';
        try {
            $site = (string) Typecho_Widget::widget('Widget_Options')->title;
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        if ($site === '') {
            $site = '本博客';
        }
        $base = '你是「' . $site . '」Typecho 博客后台的 AB AI 助手（版本 ' . self::VERSION . '，测试版）。'
            . '当前用户角色：' . $role . '；所在页面：' . $page . '。'
            . '请用简体中文、简洁准确地回答后台使用、Typecho 操作与写作相关问题。'
            . '不要编造站点的具体数据（除非用户明确提供了数据，或已通过工具查到）。'
            . '你可以调用工具操作后台：查询类工具直接返回结果；写入类工具（新建/修改/删除文章、修改插件设置、安装或启用插件）会先返回 pending，必须等用户在界面上点击「确认执行」才会真正生效。'
            . '调用写入类工具后，只能用「已准备好，请在界面点击确认执行」这类表述，绝不能声称操作已经完成。'
            . '创建或修改文章时，正文请使用 Markdown 语法（标题用 ##，列表用 -，代码用围栏代码块）。'
            . '插件目录名必须与插件类名前缀一致（例如类 Links_Plugin 必须放在 Links 目录），否则启用会失败；'
            . '若 list_plugins 返回某插件 broken=true，说明文件残缺，应先用 install_plugin 传 directory 重装，再启用。'
            . "\n\n批量操作（重要）：当用户要求「把所有文章里的 X 改成 Y」这类跨文章操作时，不要直接拒绝，也不要逐篇调用 update_post。正确流程是："
            . '① 先调用 search_posts 用关键词定位目标文章（关键词取旧值里最有辨识度的片段，如域名、URL 片段）；'
            . '② 需要确认内容时用 get_post_content 读一两篇；'
            . '③ 调用 batch_replace_in_posts，cids 传 search_posts 返回的 cids（或 all），search 传旧文本，replace 传新文本；'
            . '④ 该工具会返回 pending，等用户点「确认执行」后才会真正写入。'
            . '如果用户想先看影响范围，可先传 dryRun=true 预演一次。'
            . '注意 search 是纯文本精确匹配（区分大小写），若用户给的是域名，提醒他确认是否包含 http:// 前缀与末尾斜杠。'
            . "\n\n你还能做这些事（不要说自己做不到）："
            . '评论审核（list_comments 查待审核 → moderate_comments 通过/标记垃圾/删除，coids 可传 all-waiting）；'
            . '分类与标签（list_terms 查看、create_term 新建）；'
            . '独立页面（list_pages）；'
            . '站点设置（get_site_options 读取、update_site_option 修改站点名/描述/地址/每页文章数/时区/是否允许注册）；'
            . '用户列表（list_users）；'
            . '任意插件设置（get_plugin_config 读取、update_plugin_config 修改，密钥类字段出于安全禁止 AI 修改）；'
            . '插件安装/启用/停用/删除（search_store_plugins、install_plugin、toggle_plugin、remove_plugin）。'
            . '遇到用户请求时先想「有没有对应工具」，有就直接用，不要凭印象说没有该能力。'
            . self::skillsPrompt();
        $custom = '';
        $c = self::config();
        if (isset($c['system_prompt']) && trim((string) $c['system_prompt']) !== '') {
            $custom = "\n\n站点级自定义指令：\n" . trim((string) $c['system_prompt']);
        }
        $userPart = '';
        if ((int) $uid > 0) {
            $prefs = self::userPrefs((int) $uid);
            if ($prefs['prompt'] !== '') {
                $userPart .= "\n\n当前用户的个人指令（优先遵守）：\n" . $prefs['prompt'];
            }
            if ($prefs['knowledge'] !== '') {
                $userPart .= "\n\n当前用户提供的知识库（回答相关问题时以此为准）：\n" . $prefs['knowledge'];
            }
        }
        return $base . $custom . $userPart;
    }
    public static function testConnection()
    {
        $c = self::config();
        if ($c['base_url'] === '') {
            return array('error' => '请先填写 API 地址', 'code' => 400);
        }
        if (!self::safeBaseUrl($c['base_url'], $c['provider'])) {
            return array('error' => 'API 地址不合法（仅允许公网地址；Ollama 可用本地地址）', 'code' => 400);
        }
        if ($c['api_key'] === '' && $c['provider'] !== 'ollama') {
            return array('error' => '请先填写 API Key', 'code' => 400);
        }
        $url = rtrim($c['base_url'], '/') . '/chat/completions';
        $payload = array(
            'model'       => $c['model'],
            'messages'    => array(array('role' => 'user', 'content' => 'ping')),
            'max_tokens'  => 4,
            'stream'      => false,
        );
        $headers = array('Content-Type: application/json');
        if ($c['api_key'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $c['api_key'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ));
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false) {
            return array('error' => '连接失败：' . ($err !== '' ? $err : '未知错误'), 'code' => 502);
        }
        if ($code >= 400) {
            $msg = @json_decode((string) $body, true);
            $detail = isset($msg['error']['message']) ? $msg['error']['message'] : 'HTTP ' . $code;
            return array('error' => 'AI 服务返回错误：' . $detail, 'code' => 502);
        }
        return array('ok' => true, 'data' => array('model' => $c['model']), 'message' => '连接成功（' . $c['model'] . '）');
    }
    public static function chat($messages, $emit)
    {
        $c = self::config();
        if (!$c['enabled'] || $c['api_key'] === '' || $c['base_url'] === '') {
            return array('error' => 'AI 助手未配置（请在插件设置里填写 API Key）', 'code' => 400);
        }
        if (!self::safeBaseUrl($c['base_url'], $c['provider'])) {
            return array('error' => 'API 地址不合法', 'code' => 400);
        }
        $url = rtrim($c['base_url'], '/') . '/chat/completions';
        $payload = array(
            'model'       => $c['model'],
            'messages'    => $messages,
            'stream'      => true,
            'temperature' => $c['temperature'],
        );
        if ($c['max_tokens'] > 0) {
            $payload['max_tokens'] = $c['max_tokens'];
        }
        $headers = array('Content-Type: application/json', 'Accept: text/event-stream');
        if ($c['api_key'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $c['api_key'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_WRITEFUNCTION  => function ($ch, $data) use ($emit) {
                $emit($data);
                return strlen($data);
            },
        ));
        $ok   = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($ok === false) {
            return array('error' => '连接失败：' . ($err !== '' ? $err : '未知错误'), 'code' => 502);
        }
        if ($code >= 400) {
            return array('error' => 'AI 服务返回错误（HTTP ' . $code . '）', 'code' => 502);
        }
        return array('ok' => true);
    }
    public static function rateLimit($uid)
    {
        $c = self::config();
        $limit = max(1, $c['rate_per_min']);
        $window = (int) floor(time() / 60);
        $key = 'ai-rate-' . (int) $uid;
        $used = 0;
        $res = AdminBeautify_Plugin::cardDataGet($key, 'user');
        if (is_array($res) && !empty($res['json']) && is_array($res['json'])) {
            if ((int) (isset($res['json']['w']) ? $res['json']['w'] : 0) === $window) {
                $used = (int) (isset($res['json']['n']) ? $res['json']['n'] : 0);
            }
        }
        if ($used >= $limit) {
            return array('error' => '请求过于频繁，请稍后再试', 'code' => 429);
        }
        AdminBeautify_Plugin::cardDataSave($key, json_encode(array('w' => $window, 'n' => $used + 1)), 'user');
        return array('ok' => true);
    }
    private static function prefKey()
    {
        return 'ai-prefs';
    }
    public static function userPrefs($uid)
    {
        $uid = (int) $uid;
        $res = AdminBeautify_Plugin::cardDataGet(self::prefKey(), 'user');
        $data = (is_array($res) && !empty($res['json'])) ? $res['json'] : array();
        if (is_string($data)) {
            $data = @json_decode($data, true);
        }
        if (!is_array($data)) {
            $data = array();
        }
        return array(
            'prompt'    => isset($data['prompt']) ? (string) $data['prompt'] : '',
            'knowledge' => isset($data['knowledge']) ? (string) $data['knowledge'] : '',
        );
    }
    public static function saveUserPrefs($uid, $prompt, $knowledge)
    {
        $uid = (int) $uid;
        if ($uid <= 0) {
            return array('error' => '无法识别当前用户', 'code' => 400);
        }
        $prompt = trim((string) $prompt);
        $knowledge = trim((string) $knowledge);
        if (mb_strlen($prompt) > 4000) {
            $prompt = mb_substr($prompt, 0, 4000);
        }
        if (mb_strlen($knowledge) > 8000) {
            $knowledge = mb_substr($knowledge, 0, 8000);
        }
        AdminBeautify_Plugin::cardDataSave(
            self::prefKey(),
            json_encode(array('prompt' => $prompt, 'knowledge' => $knowledge), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'user'
        );
        return array('ok' => true, 'data' => array('prompt' => $prompt, 'knowledge' => $knowledge));
    }
    private static function convKey()
    {
        return 'ai-conv';
    }
    private static function convAll()
    {
        $res = AdminBeautify_Plugin::cardDataGet(self::convKey(), 'user');
        return (is_array($res) && !empty($res['json']) && is_array($res['json'])) ? $res['json'] : array();
    }
    private static function convWrite(array $list)
    {
        AdminBeautify_Plugin::cardDataSave(self::convKey(), json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'user');
    }
    public static function convList()
    {
        $out = array();
        foreach (self::convAll() as $c) {
            if (!is_array($c) || !isset($c['id'])) continue;
            $msgs = isset($c['messages']) && is_array($c['messages']) ? $c['messages'] : array();
            $out[] = array(
                'id'         => (string) $c['id'],
                'title'      => isset($c['title']) ? (string) $c['title'] : '新对话',
                'updated_at' => isset($c['updated_at']) ? (int) $c['updated_at'] : 0,
                'count'      => count($msgs),
            );
        }
        usort($out, function ($a, $b) { return $b['updated_at'] - $a['updated_at']; });
        return $out;
    }
    public static function convGet($id)
    {
        foreach (self::convAll() as $c) {
            if (is_array($c) && isset($c['id']) && (string) $c['id'] === (string) $id) {
                return $c;
            }
        }
        return null;
    }
    public static function convSave($id, $title, array $messages)
    {
        $id = (string) $id;
        if ($id === '') return array('error' => '会话 ID 无效', 'code' => 400);
        $messages = array_slice($messages, -40);
        $title = $title !== '' ? mb_substr((string) $title, 0, 40) : '新对话';
        $list = self::convAll();
        $found = false;
        for ($i = 0; $i < count($list); $i++) {
            if (is_array($list[$i]) && isset($list[$i]['id']) && (string) $list[$i]['id'] === $id) {
                $list[$i] = array('id' => $id, 'title' => $title, 'updated_at' => time(), 'messages' => $messages);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $list[] = array('id' => $id, 'title' => $title, 'updated_at' => time(), 'messages' => $messages);
        }
        usort($list, function ($a, $b) { return $b['updated_at'] - $a['updated_at']; });
        $list = array_slice($list, 0, 20);
        self::convWrite($list);
        return array('ok' => true, 'id' => $id);
    }
    public static function convDelete($id)
    {
        $list = self::convAll();
        $kept = array();
        foreach ($list as $c) {
            if (is_array($c) && isset($c['id']) && (string) $c['id'] !== (string) $id) {
                $kept[] = $c;
            }
        }
        self::convWrite($kept);
        return array('ok' => true);
    }
    public static function generateTitle($firstMsg)
    {
        $firstMsg = trim((string) $firstMsg);
        if ($firstMsg === '') return '新对话';
        $messages = array(
            array('role' => 'system', 'content' => '你是标题生成器。把用户的话概括成不超过 12 个字的简短标题，只输出标题本身，不要引号、不要句号。'),
            array('role' => 'user', 'content' => mb_substr($firstMsg, 0, 200)),
        );
        $res = self::callProvider($messages, null);
        if (isset($res['error'])) return mb_substr($firstMsg, 0, 12);
        $title = trim((string) (isset($res['message']['content']) ? $res['message']['content'] : ''));
        $title = preg_replace('/^["「]+|["」。]+$/', '', $title);
        return $title !== '' ? mb_substr($title, 0, 20) : mb_substr($firstMsg, 0, 12);
    }
    public static function listSessions($uid)
    {
        $uid = (int) $uid;
        return self::convList();
    }
    public static function getSession($uid, $id)
    {
        $uid = (int) $uid;
        $c = self::convGet($id);
        if (null === $c) return null;
        return array(
            'id'         => isset($c['id']) ? (string) $c['id'] : '',
            'title'      => isset($c['title']) ? (string) $c['title'] : '新对话',
            'updated_at' => isset($c['updated_at']) ? (int) $c['updated_at'] : 0,
            'messages'   => isset($c['messages']) && is_array($c['messages']) ? $c['messages'] : array(),
        );
    }
    public static function deleteSession($uid, $id)
    {
        $uid = (int) $uid;
        if (null === self::convGet($id)) return false;
        self::convDelete($id);
        return true;
    }
    public static function ensureSessionTitle($uid, $sid, $user)
    {
        $uid = (int) $uid;
        $c = self::convGet($sid);
        if (null === $c) return null;
        if (isset($c['title']) && trim((string) $c['title']) !== '' && (string) $c['title'] !== '新对话') {
            return (string) $c['title'];
        }
        $first = '';
        $msgs = isset($c['messages']) && is_array($c['messages']) ? $c['messages'] : array();
        foreach ($msgs as $m) {
            if (is_array($m) && isset($m['role']) && $m['role'] === 'user') {
                $first = (string) (isset($m['content']) ? $m['content'] : '');
                break;
            }
        }
        $title = self::generateTitle($first);
        self::convSave($sid, $title, $msgs);
        return $title;
    }
    public static function saveChatSession($sid, array $messages)
    {
        $sid = (string) $sid;
        if ($sid === '' || empty($messages)) return array('ok' => true, 'id' => $sid);
        $existing = self::convGet($sid);
        $title = ($existing && isset($existing['title']) && (string) $existing['title'] !== '新对话')
            ? (string) $existing['title']
            : '新对话';
        return self::convSave($sid, $title, $messages);
    }
    public static function tools()
    {
        return array(
            self::toolDef('get_site_stats', '获取站点统计（文章数、评论数、分类数）', array(), array()),
            self::toolDef('list_posts', '列出最近的文章，用于查找要修改/删除的文章 ID', array(
                'limit'  => array('type' => 'integer', 'description' => '数量，默认 10，最多 20'),
                'status' => array('type' => 'string', 'description' => '可选：publish 已发布 / draft 草稿 / private 私密'),
            ), array()),
            self::toolDef('list_plugins', '列出本站已安装的插件（目录名、标题、版本、是否启用）', array(), array()),
            self::toolDef('search_store_plugins', '在 AB Store 插件仓库中搜索可安装的插件，返回插件 id/名称/目录/简介', array(
                'keyword' => array('type' => 'string', 'description' => '搜索关键词，如 备份、SEO、图片'),
            ), array('keyword')),
            self::toolDef('create_post', '创建一篇新文章（正文用 Markdown 编写，默认存为草稿）。注意：本操作需要用户在界面上点击「确认执行」后才会真正写入，调用后请提示用户确认，不要重复调用。', array(
                'title'    => array('type' => 'string', 'description' => '文章标题'),
                'content'  => array('type' => 'string', 'description' => '文章正文（Markdown 语法）'),
                'category' => array('type' => 'string', 'description' => '分类名（可选）'),
                'publish'  => array('type' => 'boolean', 'description' => '是否直接发布，默认 false（草稿）'),
            ), array('title', 'content')),
            self::toolDef('update_post', '按文章 ID 修改已有文章的标题或正文（正文用 Markdown）。需要用户确认后执行。', array(
                'cid'     => array('type' => 'integer', 'description' => '文章 ID'),
                'title'   => array('type' => 'string', 'description' => '新标题（可选）'),
                'content' => array('type' => 'string', 'description' => '新正文（Markdown，可选）'),
            ), array('cid')),
            self::toolDef('delete_post', '删除指定 ID 的文章（移入回收站）。需要用户确认后执行。', array(
                'cid' => array('type' => 'integer', 'description' => '文章 ID'),
            ), array('cid')),
            self::toolDef('update_plugin_setting', '修改 AB Admin 插件的某项设置。可用键名与合法值：enableAnimation("0"关闭动画|"1"开启动画)、darkMode("auto"|"light"|"dark")、borderRadius("small"|"medium"|"large")、loadingAnimation("morph"|"spinner"|"topbar")、ai_enabled("0"|"1")、ai_allow_non_admin("0"|"1")。用户要求「关闭/去掉动画或加载动画」时用 key="enableAnimation", value="0"。需要用户确认后执行。', array(
                'key'   => array('type' => 'string', 'description' => '设置项键名，必须是上面列出的合法键名之一'),
                'value' => array('type' => 'string', 'description' => '新值，必须是该键的合法值之一'),
            ), array('key', 'value')),
            self::toolDef('install_plugin', '从 AB Store 插件仓库安装或升级一个插件。先用 search_store_plugins 找到 id。若本地已有同名但残缺的插件目录（list_plugins 返回 broken=true），传入 directory 指定安装到该目录即可修复。需要用户确认后执行。', array(
                'id'        => array('type' => 'string', 'description' => '仓库插件 id（search_store_plugins 返回的 id）'),
                'directory' => array('type' => 'string', 'description' => '可选：安装到的插件目录名。用于修复本地残缺插件（如 Real_Links）'),
            ), array('id')),
            self::toolDef('toggle_plugin', '启用或停用某个已安装的插件。先用 list_plugins 拿到目录名（directory）。需要用户确认后执行。', array(
                'directory' => array('type' => 'string', 'description' => '插件目录名，如 Links'),
                'enable'    => array('type' => 'boolean', 'description' => 'true 启用，false 停用'),
            ), array('directory', 'enable')),
            self::toolDef('remove_plugin', '删除一个插件目录（会先自动备份到 .ab-store/backup）。用于清理残缺或重复的插件目录。需要用户确认后执行。', array(
                'directory' => array('type' => 'string', 'description' => '要删除的插件目录名'),
            ), array('directory')),
            self::toolDef('get_page_context', '读取用户当前所在后台页面的上下文（页面类型、URL、正在编辑的文章标题/ID、正文摘要等）。只在需要了解用户当前在做什么时调用，不要每次都调用。', array(
                'what' => array('type' => 'string', 'description' => '可选：summary（默认，只给概要）| content（含正文，仅编辑页有效）'),
            ), array()),
            self::toolDef('edit_editor_content', '直接修改用户当前正在编辑的文章正文（仅文章/页面编辑页有效）。支持 AB Vditor、AB Editor.md 和原生编辑器。默认只写入编辑器不保存，用户可自行保存或撤销。', array(
                'content' => array('type' => 'string', 'description' => '新的完整正文（Markdown）'),
                'mode'    => array('type' => 'string', 'description' => 'replace 整体替换（默认）| append 追加到末尾 | prepend 插入到开头'),
            ), array('content')),
            self::toolDef('search_posts', '按关键词搜索文章（标题或正文），返回匹配的文章 ID 列表。用于批量操作前先定位目标文章。', array(
                'keyword' => array('type' => 'string', 'description' => '搜索关键词，会同时匹配标题和正文'),
                'limit'   => array('type' => 'integer', 'description' => '最多返回多少条，默认 20，最多 50'),
            ), array('keyword')),
            self::toolDef('get_post_content', '读取指定文章 ID 的完整正文（Markdown），用于批量替换前先确认内容。', array(
                'cid' => array('type' => 'integer', 'description' => '文章 ID'),
            ), array('cid')),
            self::toolDef('batch_replace_in_posts', '批量替换多篇文章正文中的文本（例如把所有文章里的旧域名改成新域名）。先用 search_posts 找到目标文章，再调用本工具。需要用户确认后执行。', array(
                'cids'    => array('type' => 'string', 'description' => '文章 ID 列表，逗号分隔，如 "1,2,3"；传 all 表示全部文章'),
                'search'  => array('type' => 'string', 'description' => '要被替换的原文（纯文本，区分大小写）'),
                'replace' => array('type' => 'string', 'description' => '替换成的新文本'),
                'dryRun'  => array('type' => 'boolean', 'description' => 'true 只统计不写入（默认 false）'),
            ), array('cids', 'search', 'replace')),
            self::toolDef('list_comments', '列出评论，可按状态筛选。用于评论审核。', array(
                'status' => array('type' => 'string', 'description' => 'approved 已通过 / waiting 待审核 / spam 垃圾；留空为全部'),
                'limit'  => array('type' => 'integer', 'description' => '数量，默认 20，最多 50'),
            ), array()),
            self::toolDef('moderate_comments', '批量审核评论：通过、标记垃圾或删除。先用 list_comments 拿到评论 ID。需要用户确认后执行。', array(
                'coids'  => array('type' => 'string', 'description' => '评论 ID 列表，逗号分隔；传 all-waiting 表示全部待审核'),
                'action' => array('type' => 'string', 'description' => 'approve 通过 / spam 标记垃圾 / delete 删除'),
            ), array('coids', 'action')),
            self::toolDef('list_terms', '列出分类或标签（含文章数量）。', array(
                'type' => array('type' => 'string', 'description' => 'category 分类 / tag 标签'),
            ), array('type')),
            self::toolDef('create_term', '新建分类或标签。需要用户确认后执行。', array(
                'type' => array('type' => 'string', 'description' => 'category 分类 / tag 标签'),
                'name' => array('type' => 'string', 'description' => '名称'),
                'slug' => array('type' => 'string', 'description' => '别名（可选，留空自动生成）'),
            ), array('type', 'name')),
            self::toolDef('list_pages', '列出独立页面（ID、标题、状态）。', array(), array()),
            self::toolDef('get_site_options', '读取站点基本设置（站点名、描述、站点地址、每页文章数、时区等）。', array(), array()),
            self::toolDef('update_site_option', '修改站点基本设置。可用键名：title(站点名)、description(站点描述)、siteUrl(站点地址)、postsListSize(每页文章数)、timezone(时区)、allowRegister("0"|"1")。需要用户确认后执行。', array(
                'key'   => array('type' => 'string', 'description' => '设置项键名'),
                'value' => array('type' => 'string', 'description' => '新值'),
            ), array('key', 'value')),
            self::toolDef('list_users', '列出站点用户（ID、用户名、昵称、角色、文章数）。', array(), array()),
            self::toolDef('get_plugin_config', '读取任意插件（不只是 AB Admin）的当前设置项。先用 list_plugins 拿到目录名。', array(
                'directory' => array('type' => 'string', 'description' => '插件目录名，如 Links'),
            ), array('directory')),
            self::toolDef('update_plugin_config', '修改任意插件的设置项（不只是 AB Admin）。先用 get_plugin_config 看清现有键名与值，再改。需要用户确认后执行。', array(
                'directory' => array('type' => 'string', 'description' => '插件目录名'),
                'key'       => array('type' => 'string', 'description' => '设置项键名'),
                'value'     => array('type' => 'string', 'description' => '新值'),
            ), array('directory', 'key', 'value')),
            self::toolDef('list_skills', '列出所有可用技能（Skills）的名称与简介。', array(), array()),
            self::toolDef('read_skill', '读取某个技能的完整说明。当用户问题涉及插件用法、短代码、图片上传等主题时，先读对应技能再回答。', array(
                'name' => array('type' => 'string', 'description' => '技能名，如 picup、image-upload、links-plus、ab-admin、typecho-basics'),
            ), array('name')),
            self::toolDef('upload_image', '上传图片到站点（写入 usr/uploads 并登记为附件），返回可访问 URL 与 Markdown 片段。source=base64 时 data 传 base64（可带 data:image/...;base64, 前缀）；source=url 时 data 传图片地址。', array(
                'source'   => array('type' => 'string', 'description' => 'base64 或 url'),
                'data'     => array('type' => 'string', 'description' => 'base64 内容或图片 URL'),
                'filename' => array('type' => 'string', 'description' => '文件名（可选，含扩展名）'),
            ), array('source', 'data')),
            self::toolDef('save_chat_image', '把用户在对话里发来的图片保存到站点媒体库（已启用 PicUp 时交给 PicUp 的存储方案与扩展处理：压缩/水印/WebP/远程存储）。用户说「把这张图存下来 / 上传到媒体库 / 插进文章」时用它，不要再向用户索要图片地址。', array(
                'index'    => array('type' => 'integer', 'description' => '图片编号，从 1 开始（见系统提示里列出的图片清单）'),
                'filename' => array('type' => 'string', 'description' => '文件名（可选，含扩展名）'),
            ), array('index')),
        );
    }
    private static function toolDef($name, $desc, array $props, array $required)
    {
        return array(
            'type'     => 'function',
            'function' => array(
                'name'        => $name,
                'description' => $desc,
                'parameters'  => array(
                    'type'       => 'object',
                    'properties' => (object) $props,
                    'required'   => $required,
                ),
            ),
        );
    }
    private static function callProvider($messages, $tools = null)
    {
        $c = self::config();
        if (!self::safeBaseUrl($c['base_url'], $c['provider'])) {
            return array('error' => 'API 地址不合法', 'code' => 400);
        }
        $url = rtrim($c['base_url'], '/') . '/chat/completions';
        $payload = array(
            'model'       => $c['model'],
            'messages'    => $messages,
            'stream'      => false,
            'temperature' => $c['temperature'],
        );
        if ($c['max_tokens'] > 0) $payload['max_tokens'] = $c['max_tokens'];
        if ($tools) $payload['tools'] = $tools;
        $headers = array('Content-Type: application/json');
        if ($c['api_key'] !== '') $headers[] = 'Authorization: Bearer ' . $c['api_key'];
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 120,
        ));
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false) {
            return array('error' => '连接失败：' . ($err !== '' ? $err : '未知错误'), 'code' => 502);
        }
        if ($code >= 400) {
            $m = @json_decode((string) $body, true);
            $detail = isset($m['error']['message']) ? $m['error']['message'] : 'HTTP ' . $code;
            return array('error' => 'AI 服务返回错误：' . $detail, 'code' => 502);
        }
        $data = @json_decode((string) $body, true);
        $msg = isset($data['choices'][0]['message']) ? $data['choices'][0]['message'] : null;
        if (!$msg) {
            return array('error' => 'AI 响应格式异常', 'code' => 502);
        }
        return array('ok' => true, 'message' => $msg);
    }
    private static function streamOnce($messages, $tools, $emit)
    {
        $c = self::config();
        if (!self::safeBaseUrl($c['base_url'], $c['provider'])) {
            return array('error' => 'API 地址不合法', 'code' => 400);
        }
        $url = rtrim($c['base_url'], '/') . '/chat/completions';
        $payload = array(
            'model'       => $c['model'],
            'messages'    => $messages,
            'stream'      => true,
            'temperature' => $c['temperature'],
        );
        if ($c['max_tokens'] > 0) {
            $payload['max_tokens'] = $c['max_tokens'];
        }
        if ($tools) {
            $payload['tools'] = $tools;
        }
        $headers = array('Content-Type: application/json', 'Accept: text/event-stream');
        if ($c['api_key'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $c['api_key'];
        }
        $content = '';
        $toolBuf = array();
        $finishReason = '';
        $lineBuf = '';
        $errMsg  = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_WRITEFUNCTION  => function ($ch, $data) use (&$content, &$toolBuf, &$lineBuf, &$errMsg, &$finishReason, $emit) {
                $lineBuf .= $data;
                while (false !== ($pos = strpos($lineBuf, "\n"))) {
                    $line = rtrim(substr($lineBuf, 0, $pos), "\r");
                    $lineBuf = substr($lineBuf, $pos + 1);
                    $line = trim($line);
                    if ($line === '' || strpos($line, 'data:') !== 0) {
                        continue;
                    }
                    $raw = trim(substr($line, 5));
                    if ($raw === '' || $raw === '[DONE]') {
                        continue;
                    }
                    $obj = @json_decode($raw, true);
                    if (!is_array($obj)) {
                        continue;
                    }
                    if (isset($obj['error']['message'])) {
                        $errMsg = (string) $obj['error']['message'];
                        continue;
                    }
                    $delta = isset($obj['choices'][0]['delta']) ? $obj['choices'][0]['delta'] : null;
                    if (isset($obj['choices'][0]['finish_reason']) && (string) $obj['choices'][0]['finish_reason'] !== '') {
                        $finishReason = (string) $obj['choices'][0]['finish_reason'];
                    }
                    if (!is_array($delta)) {
                        continue;
                    }
                    if (isset($delta['content']) && is_string($delta['content']) && $delta['content'] !== '') {
                        $content .= $delta['content'];
                        self::emitDelta($emit, $delta['content']);
                    }
                    if (isset($delta['tool_calls']) && is_array($delta['tool_calls'])) {
                        foreach ($delta['tool_calls'] as $tc) {
                            $idx = isset($tc['index']) ? (int) $tc['index'] : 0;
                            if (!isset($toolBuf[$idx])) {
                                $toolBuf[$idx] = array('id' => '', 'name' => '', 'arguments' => '');
                            }
                            if (isset($tc['id']) && (string) $tc['id'] !== '') {
                                $toolBuf[$idx]['id'] = (string) $tc['id'];
                            }
                            if (isset($tc['function']['name']) && (string) $tc['function']['name'] !== '') {
                                $toolBuf[$idx]['name'] = (string) $tc['function']['name'];
                            }
                            if (isset($tc['function']['arguments'])) {
                                $toolBuf[$idx]['arguments'] .= (string) $tc['function']['arguments'];
                            }
                        }
                    }
                }
                return strlen($data);
            },
        ));
        $ok   = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($ok === false) {
            return array('error' => '连接失败：' . ($err !== '' ? $err : '未知错误'), 'code' => 502);
        }
        if ($errMsg !== '') {
            return array('error' => 'AI 服务返回错误：' . $errMsg, 'code' => 502);
        }
        if ($code >= 400) {
            return array('error' => 'AI 服务返回错误（HTTP ' . $code . '）', 'code' => 502);
        }
        $toolCalls = array();
        foreach ($toolBuf as $idx => $t) {
            if ($t['name'] === '') {
                continue;
            }
            $toolCalls[] = array(
                'id'       => $t['id'] !== '' ? $t['id'] : ('call_' . $idx),
                'type'     => 'function',
                'function' => array('name' => $t['name'], 'arguments' => $t['arguments']),
            );
        }
        return array('ok' => true, 'content' => $content, 'tool_calls' => $toolCalls, 'finish_reason' => $finishReason);
    }
    public static function chatAgent($messages, $emit, $withTools = true)
    {
        $tools = $withTools ? self::tools() : null;
        self::$chatImages = $withTools ? self::collectChatImages($messages) : array();
        if (!empty(self::$chatImages)
            && isset($messages[0]['role']) && $messages[0]['role'] === 'system'
            && is_string($messages[0]['content'])) {
            $lines = array();
            foreach (self::$chatImages as $i => $im) {
                $lines[] = '#' . ($i + 1) . ' ' . $im['name']
                    . '（' . $im['mime'] . '，' . self::humanSize($im['size']) . '）';
            }
            $messages[0]['content'] .= "\n\n用户已经发来以下图片，服务端已拿到它们的原始二进制数据（不需要再向用户索要图片地址或让他重新上传）：\n"
                . implode("\n", $lines)
                . "\n当用户要求「保存 / 上传到媒体库 / 传到图床 / 插进文章正文」时，直接调用 save_chat_image，index 传上面的编号；上传成功后会返回可访问 URL 与 Markdown 片段。";
        }
        $finalContent = '';
        $truncated = false;
        for ($round = 0; $round < 8; $round++) {
            $res = self::streamOnce($messages, $tools, $emit);
            if (isset($res['error'])) {
                return $res;
            }
            if (isset($res['finish_reason']) && $res['finish_reason'] === 'length') {
                $truncated = true;
            }
            $toolCalls = isset($res['tool_calls']) ? $res['tool_calls'] : array();
            $piece = isset($res['content']) ? (string) $res['content'] : '';
            if (!empty($toolCalls)) {
                $assistant = array('role' => 'assistant', 'content' => $piece);
                $assistant['tool_calls'] = $toolCalls;
                $messages[] = $assistant;
                $finalContent .= $piece;
                if (trim($finalContent) !== '') {
                    self::emitDelta($emit, "\n\n");
                    $finalContent .= "\n\n";
                }
                foreach ($toolCalls as $tc) {
                    $fn = isset($tc['function']) ? $tc['function'] : array();
                    $name = isset($fn['name']) ? (string) $fn['name'] : '';
                    $rawArgs = isset($fn['arguments']) ? (string) $fn['arguments'] : '';
                    $args = $rawArgs === '' ? array() : @json_decode($rawArgs, true);
                    if (!is_array($args)) {
                        $truncated = true;
                        $result = json_encode(array(
                            'ok'    => false,
                            'error' => '这次工具调用的参数不完整（JSON 被截断，通常是单条回复超过了 max_tokens 上限）。'
                                . '请把内容拆小后再试：例如自定义卡片先只写 html，下一轮再写 js；或把 js 压缩到必要的最小实现。'
                                . '不要原样重试同样长度的内容。',
                        ), JSON_UNESCAPED_UNICODE);
                        $messages[] = array(
                            'role'         => 'tool',
                            'tool_call_id' => isset($tc['id']) ? (string) $tc['id'] : ('call_' . $round),
                            'content'      => $result,
                        );
                        continue;
                    }
                    $result = self::runTool($name, $args);
                    $decoded = @json_decode($result, true);
                    if (is_array($decoded) && !empty($decoded['pending']) && !empty($decoded['confirm'])) {
                        $emit('event: confirm' . "\n"
                            . 'data: ' . json_encode($decoded['confirm'], JSON_UNESCAPED_UNICODE) . "\n\n");
                    }
                    if (is_array($decoded) && !empty($decoded['editor']) && isset($decoded['content'])) {
                        $emit('event: editor' . "\n"
                            . 'data: ' . json_encode(array(
                                'mode'    => isset($decoded['mode']) ? $decoded['mode'] : 'replace',
                                'content' => $decoded['content'],
                            ), JSON_UNESCAPED_UNICODE) . "\n\n");
                    }
                    $messages[] = array(
                        'role'         => 'tool',
                        'tool_call_id' => isset($tc['id']) ? (string) $tc['id'] : ('call_' . $round),
                        'content'      => $result,
                    );
                }
                continue;
            }
            $finalContent .= $piece;
            $hitLimit = (isset($res['finish_reason']) && $res['finish_reason'] === 'length');
            if (trim($finalContent) === '') {
                $finalContent = ($truncated || $hitLimit)
                    ? '（模型这次输出被 max_tokens 上限截断了，没能给出完整回答。'
                        . '请在「插件 → AB Admin → AI 助手」里把「最大输出长度」调大（如 4096～8192），'
                        . '或把需求拆小一点（例如先让我写卡片的结构、再单独写脚本）再试一次。）'
                    : '（这次没有拿到内容，可能是模型返回了空消息。可以点「重新生成」再试一次。）';
                self::emitDelta($emit, $finalContent);
            } elseif ($hitLimit) {
                $note = "\n\n---\n（这条回答被 max_tokens 上限截断了，后面可能还没说完。"
                    . '可以把「插件 → AB Admin → AI 助手 → 最大输出长度」调到 8192，或让我「继续」。）';
                self::emitDelta($emit, $note);
                $finalContent .= $note;
            }
            return array('ok' => true, 'content' => $finalContent);
        }
        return array('error' => '操作步骤过多，已中止（多半是同一个工具反复失败）。可以换个说法或把需求拆小再试。', 'code' => 400);
    }
    private static function emitDelta($emit, $text)
    {
        $emit('data: ' . json_encode(array(
            'choices' => array(array('delta' => array('content' => $text))),
        ), JSON_UNESCAPED_UNICODE) . "\n\n");
    }
    private static $pageCtx = array();
    public static function setPageContext(array $ctx)
    {
        self::$pageCtx = $ctx;
    }
    private static function toolGetPageContext(array $args)
    {
        $what = isset($args['what']) ? (string) $args['what'] : 'summary';
        $ctx = self::$pageCtx;
        if (empty($ctx)) {
            return json_encode(array('ok' => false, 'error' => '当前页面没有可读取的上下文'), JSON_UNESCAPED_UNICODE);
        }
        $out = array(
            'ok'       => true,
            'page'     => isset($ctx['page']) ? (string) $ctx['page'] : '',
            'url'      => isset($ctx['url']) ? (string) $ctx['url'] : '',
            'title'    => isset($ctx['title']) ? (string) $ctx['title'] : '',
            'editor'   => isset($ctx['editor']) ? (string) $ctx['editor'] : '',
            'cid'      => isset($ctx['cid']) ? (int) $ctx['cid'] : 0,
            'editable' => !empty($ctx['editable']),
        );
        if (isset($ctx['summary']) && $ctx['summary'] !== '') {
            $out['summary'] = (string) $ctx['summary'];
        }
        if ($what === 'content') {
            if (empty($ctx['editable'])) {
                $out['note'] = '当前页面不是可编辑的文章/页面编辑页，没有正文可读';
            } elseif (isset($ctx['content'])) {
                $content = (string) $ctx['content'];
                $out['content'] = $content;
                $out['contentLength'] = mb_strlen($content);
            }
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }
    private static function toolEditEditorContent(array $args)
    {
        $content = isset($args['content']) ? (string) $args['content'] : '';
        $mode = isset($args['mode']) ? (string) $args['mode'] : 'replace';
        if (!in_array($mode, array('replace', 'append', 'prepend'), true)) {
            $mode = 'replace';
        }
        if (trim($content) === '') {
            return json_encode(array('ok' => false, 'error' => '正文不能为空'), JSON_UNESCAPED_UNICODE);
        }
        $ctx = self::$pageCtx;
        if (empty($ctx['editable'])) {
            return json_encode(array(
                'ok'    => false,
                'error' => '当前页面不是文章/页面编辑页，无法直接写入编辑器。请让用户先打开编辑页，或改用 update_post 工具。',
            ), JSON_UNESCAPED_UNICODE);
        }
        return json_encode(array(
            'ok'      => true,
            'editor'  => true,
            'mode'    => $mode,
            'content' => $content,
            'length'  => mb_strlen($content),
            'message' => '已写入编辑器（未保存）。用户可自行点击保存，或用撤销按钮恢复原内容。',
        ), JSON_UNESCAPED_UNICODE);
    }
    public static function runTool($name, array $args)
    {
        switch ($name) {
            case 'get_site_stats':
                return self::toolGetStats();
            case 'list_posts':
                return self::toolListPosts($args);
            case 'list_plugins':
                return self::toolListPlugins();
            case 'search_store_plugins':
                return self::toolSearchStore($args);
            case 'get_page_context':
                return self::toolGetPageContext($args);
            case 'edit_editor_content':
                return self::toolEditEditorContent($args);
            case 'search_posts':
                return self::toolSearchPosts($args);
            case 'get_post_content':
                return self::toolGetPostContent($args);
            case 'list_comments':
                return self::toolListComments($args);
            case 'list_terms':
                return self::toolListTerms($args);
            case 'list_pages':
                return self::toolListPages();
            case 'get_site_options':
                return self::toolGetSiteOptions();
            case 'list_users':
                return self::toolListUsers();
            case 'get_plugin_config':
                return self::toolGetPluginConfig($args);
            case 'list_skills':
                return self::toolListSkills();
            case 'read_skill':
                return self::toolReadSkill($args);
            case 'upload_image':
                return self::toolUploadImage($args);
            case 'save_chat_image':
                return self::toolSaveChatImage($args);
            case 'create_post':
                return self::pendingOrRun('create_post', '创建文章', self::describeCreate($args), $args);
            case 'update_post':
                return self::pendingOrRun('update_post', '修改文章', '修改文章 #' . (isset($args['cid']) ? (int) $args['cid'] : 0), $args);
            case 'delete_post':
                return self::pendingOrRun('delete_post', '删除文章', '删除文章 #' . (isset($args['cid']) ? (int) $args['cid'] : 0), $args);
            case 'update_plugin_setting':
                return self::pendingOrRun(
                    'update_plugin_setting',
                    '修改插件设置',
                    '把 ' . (isset($args['key']) ? (string) $args['key'] : '') . ' 改为 ' . (isset($args['value']) ? (string) $args['value'] : ''),
                    $args
                );
            case 'install_plugin':
                return self::pendingOrRun('install_plugin', '安装插件', self::describeInstall($args), $args);
            case 'toggle_plugin':
                $on = !empty($args['enable']);
                return self::pendingOrRun(
                    'toggle_plugin',
                    $on ? '启用插件' : '停用插件',
                    ($on ? '启用' : '停用') . '插件 ' . (isset($args['directory']) ? (string) $args['directory'] : ''),
                    $args
                );
            case 'remove_plugin':
                return self::pendingOrRun(
                    'remove_plugin',
                    '删除插件目录',
                    '删除插件目录 ' . (isset($args['directory']) ? (string) $args['directory'] : '') . '（会先备份）',
                    $args
                );
            case 'batch_replace_in_posts':
                $cids = isset($args['cids']) ? (string) $args['cids'] : '';
                $search = isset($args['search']) ? (string) $args['search'] : '';
                $replace = isset($args['replace']) ? (string) $args['replace'] : '';
                $dry = !empty($args['dryRun']);
                $scope = ($cids === 'all') ? '全部文章' : ('文章 ' . $cids);
                return self::pendingOrRun(
                    'batch_replace_in_posts',
                    $dry ? '批量替换（预演）' : '批量替换正文',
                    ($dry ? '预演：' : '') . '在' . $scope . '中把「' . mb_substr($search, 0, 40) . '」替换为「' . mb_substr($replace, 0, 40) . '」',
                    $args
                );
            case 'moderate_comments':
                $coids = isset($args['coids']) ? (string) $args['coids'] : '';
                $act = isset($args['action']) ? (string) $args['action'] : '';
                $actLabel = array('approve' => '通过', 'spam' => '标记垃圾', 'delete' => '删除');
                $label = isset($actLabel[$act]) ? $actLabel[$act] : $act;
                $scope = ($coids === 'all-waiting') ? '全部待审核评论' : ('评论 ' . $coids);
                return self::pendingOrRun(
                    'moderate_comments',
                    '审核评论',
                    $label . '：' . $scope,
                    $args
                );
            case 'create_term':
                $type = isset($args['type']) ? (string) $args['type'] : '';
                $name = isset($args['name']) ? (string) $args['name'] : '';
                return self::pendingOrRun(
                    'create_term',
                    $type === 'tag' ? '新建标签' : '新建分类',
                    '新建' . ($type === 'tag' ? '标签' : '分类') . '「' . $name . '」',
                    $args
                );
            case 'update_site_option':
                return self::pendingOrRun(
                    'update_site_option',
                    '修改站点设置',
                    '把 ' . (isset($args['key']) ? (string) $args['key'] : '') . ' 改为 '
                        . self::previewValue(isset($args['value']) ? $args['value'] : ''),
                    $args
                );
            case 'update_plugin_config':
                return self::pendingOrRun(
                    'update_plugin_config',
                    '修改插件设置',
                    '把插件 ' . (isset($args['directory']) ? (string) $args['directory'] : '')
                        . ' 的 ' . (isset($args['key']) ? (string) $args['key'] : '')
                        . ' 改为 ' . self::previewValue(isset($args['value']) ? $args['value'] : ''),
                    $args
                );
            default:
                return json_encode(array('ok' => false, 'error' => '未知工具：' . $name), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function currentUid()
    {
        try {
            $u = Typecho_Widget::widget('Widget_User');
            return (int) $u->uid;
        } catch (Exception $e) {
            return 0;
        } catch (Throwable $e) {
            return 0;
        }
    }
    private static function previewValue($v)
    {
        if (is_array($v) || is_object($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $v = (string) $v;
        if (trim($v) === '') {
            return '（清空，设为空值）';
        }
        $v = preg_replace('/\s+/u', ' ', $v);
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            $len = mb_strlen($v, 'UTF-8');
            if ($len > 160) {
                return '「' . mb_substr($v, 0, 160, 'UTF-8') . '…」（共 ' . $len . ' 字）';
            }
        }
        return '「' . $v . '」';
    }
    private static function pendingOrRun($action, $title, $summary, array $params)
    {
        $uid = self::currentUid();
        if ($uid <= 0) {
            return json_encode(array('ok' => false, 'error' => '无法识别当前用户'), JSON_UNESCAPED_UNICODE);
        }
        try {
            $token = bin2hex(random_bytes(6));
        } catch (Exception $e) {
            $token = substr(md5(uniqid('', true)), 0, 12);
        }
        $key = 'ai-pending-' . $uid . '-' . $token;
        AdminBeautify_Plugin::cardDataSave($key, json_encode(array(
            'action' => $action,
            'params' => $params,
            'at'     => time(),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'user');
        return json_encode(array(
            'ok'      => false,
            'pending' => true,
            'message' => '已准备就绪，等待用户在界面上点击「确认执行」。请告知用户确认后才会真正生效，不要重复调用该工具。',
            'confirm' => array(
                'token'   => $token,
                'action'  => $action,
                'title'   => $title,
                'summary' => $summary,
            ),
        ), JSON_UNESCAPED_UNICODE);
    }
    public static function executeConfirmed($uid, $token)
    {
        $uid = (int) $uid;
        $token = (string) $token;
        if (!preg_match('/^[a-f0-9]{8,32}$/', $token)) {
            return array('error' => '无效的确认令牌', 'code' => 400);
        }
        $key = 'ai-pending-' . $uid . '-' . $token;
        $res = AdminBeautify_Plugin::cardDataGet($key, 'user');
        if (!$res || empty($res['json'])) {
            return array('error' => '操作不存在或已执行过', 'code' => 404);
        }
        $row = $res['json'];
        if (is_string($row)) {
            $row = @json_decode($row, true);
        }
        AdminBeautify_Plugin::cardDataDelete($key, 'user');
        if (!is_array($row) || empty($row['action'])) {
            return array('error' => '操作数据异常', 'code' => 400);
        }
        if (time() - (int) (isset($row['at']) ? $row['at'] : 0) > 900) {
            return array('error' => '操作已过期，请让 AI 重新发起', 'code' => 410);
        }
        $params = isset($row['params']) && is_array($row['params']) ? $row['params'] : array();
        $raw = '';
        switch ($row['action']) {
            case 'create_post':
                $raw = self::toolCreatePost($params);
                break;
            case 'update_post':
                $raw = self::toolUpdatePost($params);
                break;
            case 'delete_post':
                $raw = self::toolDeletePost($params);
                break;
            case 'update_plugin_setting':
                $raw = self::toolUpdateSetting($params);
                break;
            case 'install_plugin':
                $raw = self::toolInstallPlugin($params);
                break;
            case 'toggle_plugin':
                $raw = self::toolTogglePlugin($params);
                break;
            case 'remove_plugin':
                $raw = self::toolRemovePlugin($params);
                break;
            case 'batch_replace_in_posts':
                $raw = self::toolBatchReplace($params);
                break;
            case 'moderate_comments':
                $raw = self::toolModerateComments($params);
                break;
            case 'create_term':
                $raw = self::toolCreateTerm($params);
                break;
            case 'update_site_option':
                $raw = self::toolUpdateSiteOption($params);
                break;
            case 'update_plugin_config':
                $raw = self::toolUpdatePluginConfig($params);
                break;
            default:
                return array('error' => '不支持的操作：' . $row['action'], 'code' => 400);
        }
        $data = @json_decode($raw, true);
        if (!is_array($data)) {
            return array('error' => '执行结果异常', 'code' => 500);
        }
        if (empty($data['ok'])) {
            return array('error' => isset($data['error']) ? (string) $data['error'] : '执行失败', 'code' => 500);
        }
        return array('ok' => true, 'data' => $data, 'message' => self::successMessage($row['action'], $data));
    }
    private static function briefValue($v)
    {
        $s = (is_array($v) || is_object($v))
            ? (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) $v;
        if (trim($s) === '') {
            return '（空）';
        }
        $len = function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
        $trim = ltrim($s);
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            $decoded = json_decode($s, true);
            if (is_array($decoded)) {
                $n = count($decoded);
                return '（JSON，' . $n . ' 项，' . $len . ' 字）';
            }
        }
        if ($len > 60) {
            $head = function_exists('mb_substr') ? mb_substr($s, 0, 60, 'UTF-8') : substr($s, 0, 60);
            return '「' . $head . '…」（共 ' . $len . ' 字）';
        }
        return '「' . $s . '」';
    }
    private static function successMessage($action, array $data)
    {
        switch ($action) {
            case 'create_post':
                return '已创建文章《' . (isset($data['title']) ? $data['title'] : '') . '》（' . (!empty($data['status']) && $data['status'] === 'publish' ? '已发布' : '草稿') . '，ID ' . (isset($data['cid']) ? $data['cid'] : '') . '）';
            case 'update_post':
                return '已更新文章 #' . (isset($data['cid']) ? $data['cid'] : '');
            case 'delete_post':
                return '已删除文章 #' . (isset($data['cid']) ? $data['cid'] : '');
            case 'update_plugin_setting':
                return '已把 ' . (isset($data['key']) ? $data['key'] : '') . ' 设为 ' . self::briefValue(isset($data['value']) ? $data['value'] : '');
            case 'install_plugin':
                return '已安装插件 ' . (isset($data['directory']) ? $data['directory'] : '');
            case 'toggle_plugin':
                return (!empty($data['enabled']) ? '已启用插件 ' : '已停用插件 ') . (isset($data['directory']) ? $data['directory'] : '');
            case 'remove_plugin':
                return '已删除插件目录 ' . (isset($data['directory']) ? $data['directory'] : '');
            case 'batch_replace_in_posts':
                $n = isset($data['changed']) ? (int) $data['changed'] : 0;
                $t = isset($data['scanned']) ? (int) $data['scanned'] : 0;
                return (!empty($data['dryRun']) ? '预演完成：' : '已替换：')
                    . '扫描 ' . $t . ' 篇，命中 ' . $n . ' 篇';
            case 'moderate_comments':
                return '已处理 ' . (isset($data['affected']) ? (int) $data['affected'] : 0) . ' 条评论';
            case 'create_term':
                return '已新建' . (isset($data['type']) && $data['type'] === 'tag' ? '标签' : '分类')
                    . '「' . (isset($data['name']) ? $data['name'] : '') . '」';
            case 'update_site_option':
                return '已把站点设置 ' . (isset($data['key']) ? $data['key'] : '') . ' 设为 ' . self::briefValue(isset($data['value']) ? $data['value'] : '');
            case 'update_plugin_config':
                return '已更新插件 ' . (isset($data['directory']) ? $data['directory'] : '')
                    . ' 的 ' . (isset($data['key']) ? $data['key'] : '') . ' '
                    . self::briefValue(isset($data['value']) ? $data['value'] : '');
            default:
                return '操作已完成';
        }
    }
    private static function describeCreate(array $args)
    {
        $title = isset($args['title']) ? (string) $args['title'] : '';
        $content = isset($args['content']) ? (string) $args['content'] : '';
        $len = mb_strlen(trim($content));
        return '创建文章《' . $title . '》' . (!empty($args['publish']) ? '并直接发布' : '（草稿）') . '，正文约 ' . $len . ' 字';
    }
    private static function describeInstall(array $args)
    {
        $id = isset($args['id']) ? (string) $args['id'] : '';
        $name = $id;
        try {
            require_once dirname(__FILE__) . '/store/Core.php';
            $item = AdminBeautify_Store::find($id);
            if ($item && isset($item['name'])) {
                $name = (string) $item['name'];
            }
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        $dir = isset($args['directory']) ? (string) $args['directory'] : '';
        return '从 AB Store 安装插件「' . $name . '」' . ($dir !== '' ? '到目录 ' . $dir . '（覆盖现有文件，旧版会先备份）' : '');
    }
    private static function toolGetStats()
    {
        try {
            $db = \Typecho\Db::get();
            $posts = $db->fetchObject($db->select(array('COUNT(*)' => 'n'))->from('table.contents')->where('type = ?', 'post'))->n;
            $comments = $db->fetchObject($db->select(array('COUNT(*)' => 'n'))->from('table.comments'))->n;
            $cats = $db->fetchObject($db->select(array('COUNT(*)' => 'n'))->from('table.metas')->where('type = ?', 'category'))->n;
            return json_encode(array('ok' => true, 'posts' => (int) $posts, 'comments' => (int) $comments, 'categories' => (int) $cats), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolCreatePost(array $args)
    {
        try {
            $title = isset($args['title']) ? trim((string) $args['title']) : '';
            $content = isset($args['content']) ? (string) $args['content'] : '';
            if ($title === '') return json_encode(array('ok' => false, 'error' => '标题不能为空'), JSON_UNESCAPED_UNICODE);
            if (trim($content) === '') return json_encode(array('ok' => false, 'error' => '正文不能为空'), JSON_UNESCAPED_UNICODE);
            $user = Typecho_Widget::widget('Widget_User');
            $db = \Typecho\Db::get();
            $now = time();
            $status = !empty($args['publish']) ? 'publish' : 'draft';
            $slug = '';
            try {
                $slug = (string) \Typecho\Common::slugName($title);
            } catch (Exception $e) {
                $slug = '';
            } catch (Throwable $e) {
                $slug = '';
            }
            if ($slug === '') $slug = 'post-' . $now;
            $text = (0 === strpos($content, '<!--markdown-->')) ? $content : '<!--markdown-->' . $content;
            $cid = $db->query($db->insert('table.contents')->rows(array(
                'title'        => $title,
                'slug'         => $slug,
                'text'         => $text,
                'type'         => 'post',
                'status'       => $status,
                'authorId'     => (int) $user->uid,
                'created'      => $now,
                'modified'     => $now,
                'allowComment' => '1',
                'allowPing'    => '1',
                'allowFeed'    => '1',
            )));
            $cid = (int) $cid;
            $mid = 0;
            $catName = isset($args['category']) ? trim((string) $args['category']) : '';
            if ($catName !== '') {
                $cat = $db->fetchRow($db->select('mid')->from('table.metas')
                    ->where('type = ?', 'category')->where('name = ?', $catName)->limit(1));
                if ($cat) {
                    $mid = (int) $cat['mid'];
                }
            }
            if ($mid <= 0) {
                $cat = $db->fetchRow($db->select('mid')->from('table.metas')
                    ->where('type = ?', 'category')->order('mid', \Typecho\Db::SORT_ASC)->limit(1));
                if ($cat) {
                    $mid = (int) $cat['mid'];
                }
            }
            if ($mid > 0 && $cid > 0) {
                $db->query($db->insert('table.relationships')->rows(array('cid' => $cid, 'mid' => $mid)));
            }
            return json_encode(array(
                'ok'     => true,
                'cid'    => $cid,
                'title'  => $title,
                'status' => $status,
                'markdown' => true,
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolUpdatePost(array $args)
    {
        try {
            $cid = isset($args['cid']) ? (int) $args['cid'] : 0;
            if ($cid <= 0) return json_encode(array('ok' => false, 'error' => '缺少文章 ID'), JSON_UNESCAPED_UNICODE);
            $db = \Typecho\Db::get();
            $row = $db->fetchRow($db->select()->from('table.contents')->where('cid = ?', $cid)->where('type = ?', 'post'));
            if (!$row) return json_encode(array('ok' => false, 'error' => '文章不存在'), JSON_UNESCAPED_UNICODE);
            $updates = array('modified' => time());
            if (isset($args['title']) && trim((string) $args['title']) !== '') {
                $updates['title'] = trim((string) $args['title']);
            }
            if (array_key_exists('content', $args) && trim((string) $args['content']) !== '') {
                $text = (string) $args['content'];
                $updates['text'] = (0 === strpos($text, '<!--markdown-->')) ? $text : '<!--markdown-->' . $text;
            }
            $db->query($db->update('table.contents')->rows($updates)->where('cid = ?', $cid));
            return json_encode(array('ok' => true, 'cid' => $cid), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolDeletePost(array $args)
    {
        try {
            $cid = isset($args['cid']) ? (int) $args['cid'] : 0;
            if ($cid <= 0) return json_encode(array('ok' => false, 'error' => '缺少文章 ID'), JSON_UNESCAPED_UNICODE);
            $db = \Typecho\Db::get();
            $row = $db->fetchRow($db->select('cid', 'type')->from('table.contents')->where('cid = ?', $cid));
            if (!$row) return json_encode(array('ok' => false, 'error' => '文章不存在'), JSON_UNESCAPED_UNICODE);
            $db->query($db->update('table.contents')
                ->rows(array('status' => 'trash', 'modified' => time()))
                ->where('cid = ?', $cid));
            $db->query($db->delete('table.relationships')->where('cid = ?', $cid));
            return json_encode(array('ok' => true, 'cid' => $cid, 'trashed' => true), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolListPosts(array $args)
    {
        try {
            $db = \Typecho\Db::get();
            $limit = isset($args['limit']) ? (int) $args['limit'] : 10;
            $limit = max(1, min(20, $limit));
            $status = isset($args['status']) ? (string) $args['status'] : '';
            $select = $db->select('cid', 'title', 'status', 'created')
                ->from('table.contents')
                ->where('type = ?', 'post');
            if ($status !== '') {
                $select->where('status = ?', $status);
            } else {
                $select->where('status <> ?', 'trash');
            }
            $select->order('created', \Typecho\Db::SORT_DESC)->limit($limit);
            $rows = $db->fetchAll($select);
            $out = array();
            foreach ($rows as $r) {
                $out[] = array(
                    'cid'     => (int) $r['cid'],
                    'title'   => (string) $r['title'],
                    'status'  => (string) $r['status'],
                    'created' => date('Y-m-d H:i', (int) $r['created']),
                );
            }
            return json_encode(array('ok' => true, 'posts' => $out, 'count' => count($out)), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolListPlugins()
    {
        try {
            $root = __TYPECHO_ROOT_DIR__ . '/usr/plugins';
            $exported = \Typecho\Plugin::export();
            $activated = isset($exported['activated']) && is_array($exported['activated']) ? $exported['activated'] : array();
            $out = array();
            $files = @glob($root . '/*/Plugin.php');
            if (is_array($files)) {
                foreach ($files as $file) {
                    $dir = basename(dirname($file));
                    $info = array();
                    try {
                        $info = \Typecho\Plugin::parseInfo($file);
                    } catch (Exception $e) {
                    } catch (Throwable $e) {
                    }
                    $missing = self::pluginMissingFiles($file);
                    $mismatch = self::pluginClassMismatch($file, $dir);
                    $row = array(
                        'directory' => $dir,
                        'title'     => isset($info['title']) ? (string) $info['title'] : $dir,
                        'version'   => isset($info['version']) ? (string) $info['version'] : '',
                        'activated' => isset($activated[$dir]),
                    );
                    if (!empty($missing)) {
                        $row['broken'] = true;
                        $row['missing'] = $missing;
                        $row['note'] = '插件文件不完整（缺少 ' . implode('、', $missing) . '），无法启用，需要从商店重装';
                    }
                    if (!empty($mismatch)) {
                        $row['broken'] = true;
                        $row['classMismatch'] = $mismatch;
                        $row['note'] = '插件类名 ' . $mismatch['class'] . ' 与目录名 ' . $dir
                            . ' 不匹配（应放在 ' . $mismatch['expected'] . ' 目录），启用会失败';
                    }
                    $out[] = $row;
                }
            }
            return json_encode(array('ok' => true, 'plugins' => $out, 'count' => count($out)), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function pluginMissingFiles($pluginFile)
    {
        $code = (string) @file_get_contents($pluginFile);
        if ($code === '') {
            return array();
        }
        $missing = array();
        $re = '/require(?:_once)?\s*\(?\s*(__DIR__\s*\.\s*)?[\'"]([^\'"]+)[\'"]/';
        if (preg_match_all($re, $code, $m, PREG_SET_ORDER)) {
            foreach ($m as $one) {
                $hasDir = isset($one[1]) && trim($one[1]) !== '';
                $rel = isset($one[2]) ? $one[2] : '';
                if ($rel === '' || preg_match('#^[a-z]+://#i', $rel)) {
                    continue;
                }
                if (!$hasDir && strpos($rel, '/') === 0) {
                    continue;
                }
                $path = dirname($pluginFile) . '/' . ltrim($rel, '/');
                if (!is_file($path)) {
                    $missing[] = ltrim($rel, '/');
                }
            }
        }
        return array_values(array_unique($missing));
    }
    private static function pluginClassMismatch($pluginFile, $dir)
    {
        $code = (string) @file_get_contents($pluginFile);
        if ($code === '') {
            return array();
        }
        if (preg_match('/namespace\s+TypechoPlugin\\\\?([A-Za-z0-9_\\\\]*)/i', $code, $ns)) {
            $nsDir = trim(str_replace('\\', '/', isset($ns[1]) ? $ns[1] : ''), '/');
            if ($nsDir !== '' && strcasecmp($nsDir, $dir) !== 0) {
                return array('class' => 'TypechoPlugin\\' . $nsDir . '\\Plugin', 'expected' => $dir);
            }
            return array();
        }
        if (preg_match('/class\s+([A-Za-z_][A-Za-z0-9_]*)\s+(?:extends\s+[A-Za-z0-9_\\\\]+\s+)?implements\s+[^\{]*Plugin/i', $code, $m)) {
            $cls = $m[1];
            $prefix = $cls;
            if (preg_match('/^(.+)_Plugin$/i', $cls, $pm)) {
                $prefix = $pm[1];
            }
            if (strcasecmp($prefix, $dir) !== 0) {
                return array('class' => $cls, 'expected' => $prefix);
            }
        }
        return array();
    }
    private static function toolSearchStore(array $args)
    {
        try {
            $kw = isset($args['keyword']) ? trim((string) $args['keyword']) : '';
            if ($kw === '') return json_encode(array('ok' => false, 'error' => '请提供搜索关键词'), JSON_UNESCAPED_UNICODE);
            require_once dirname(__FILE__) . '/store/Core.php';
            $reg = AdminBeautify_Store::allPlugins();
            $list = isset($reg['plugins']) && is_array($reg['plugins']) ? $reg['plugins'] : array();
            $kwLower = mb_strtolower($kw);
            $out = array();
            foreach ($list as $p) {
                $hay = mb_strtolower(
                    (isset($p['name']) ? $p['name'] : '') . ' ' .
                    (isset($p['directory']) ? $p['directory'] : '') . ' ' .
                    (isset($p['description']) ? $p['description'] : '') . ' ' .
                    (isset($p['tags']) && is_array($p['tags']) ? implode(' ', $p['tags']) : '')
                );
                if ($kwLower !== '' && mb_strpos($hay, $kwLower) === false) {
                    continue;
                }
                $out[] = array(
                    'id'          => isset($p['id']) ? (string) $p['id'] : '',
                    'name'        => isset($p['name']) ? (string) $p['name'] : '',
                    'directory'   => isset($p['directory']) ? (string) $p['directory'] : '',
                    'version'     => isset($p['version']) ? (string) $p['version'] : '',
                    'description' => isset($p['description']) ? mb_substr((string) $p['description'], 0, 80) : '',
                );
                if (count($out) >= 8) break;
            }
            if (empty($out)) {
                return json_encode(array('ok' => true, 'plugins' => array(), 'message' => '没有找到匹配的插件'), JSON_UNESCAPED_UNICODE);
            }
            return json_encode(array('ok' => true, 'plugins' => $out, 'count' => count($out)), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolInstallPlugin(array $args)
    {
        try {
            $id = isset($args['id']) ? trim((string) $args['id']) : '';
            if ($id === '') return json_encode(array('ok' => false, 'error' => '缺少插件 id'), JSON_UNESCAPED_UNICODE);
            require_once dirname(__FILE__) . '/store/Core.php';
            require_once dirname(__FILE__) . '/store/Installer.php';
            $item = AdminBeautify_Store::find($id);
            if (!$item) {
                return json_encode(array('ok' => false, 'error' => '商店里找不到这个插件（可能已下架）'), JSON_UNESCAPED_UNICODE);
            }
            $override = isset($args['directory']) ? AdminBeautify_Store::safeDir($args['directory']) : '';
            if ($override !== '') {
                $item['directory'] = $override;
            }
            $res = AdminBeautify_StoreInstall::install($item);
            if (isset($res['error'])) {
                return json_encode(array('ok' => false, 'error' => (string) $res['error']), JSON_UNESCAPED_UNICODE);
            }
            $dir = isset($item['directory']) && $item['directory'] !== '' ? (string) $item['directory'] : (string) $item['name'];
            return json_encode(array(
                'ok'        => true,
                'directory' => $dir,
                'name'      => isset($item['name']) ? (string) $item['name'] : $dir,
                'message'   => isset($res['message']) ? (string) $res['message'] : '安装完成',
                'note'      => '安装完成后还需要启用插件（可用 toggle_plugin）',
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolTogglePlugin(array $args)
    {
        try {
            $dir = isset($args['directory']) ? trim((string) $args['directory']) : '';
            $enable = !empty($args['enable']);
            if ($dir === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $dir)) {
                return json_encode(array('ok' => false, 'error' => '插件目录名不合法'), JSON_UNESCAPED_UNICODE);
            }
            $file = __TYPECHO_ROOT_DIR__ . '/usr/plugins/' . $dir . '/Plugin.php';
            if (!is_file($file)) {
                return json_encode(array('ok' => false, 'error' => '插件不存在：' . $dir), JSON_UNESCAPED_UNICODE);
            }
            $missing = self::pluginMissingFiles($file);
            if (!empty($missing)) {
                return json_encode(array(
                    'ok'      => false,
                    'broken'  => true,
                    'missing' => $missing,
                    'error'   => '插件文件不完整，缺少 ' . implode('、', $missing)
                        . '，无法启用。请先用 install_plugin 从商店重装到目录 ' . $dir . '（传 directory="' . $dir . '"），再启用。',
                ), JSON_UNESCAPED_UNICODE);
            }
            $mismatch = self::pluginClassMismatch($file, $dir);
            if (!empty($mismatch)) {
                return json_encode(array(
                    'ok'            => false,
                    'broken'        => true,
                    'classMismatch' => $mismatch,
                    'error'         => '插件类名 ' . $mismatch['class'] . ' 与目录名 ' . $dir
                        . ' 不匹配，Typecho 无法加载该类，启用会失败。'
                        . '请用 install_plugin 从商店重装到目录 ' . $mismatch['expected']
                        . '（传 directory="' . $mismatch['expected'] . '"），再启用；'
                        . '旧目录 ' . $dir . ' 可用 remove_plugin 清理（会先备份）。',
                ), JSON_UNESCAPED_UNICODE);
            }
            $options = Typecho_Widget::widget('Widget_Options');
            $pluginFile = __TYPECHO_ROOT_DIR__ . '/usr/plugins/' . $dir . '/Plugin.php';
            $info = array();
            try {
                $info = \Typecho\Plugin::parseInfo($pluginFile);
            } catch (Exception $e) {
            } catch (Throwable $e) {
            }
            $exported = \Typecho\Plugin::export();
            $activated = isset($exported['activated']) && is_array($exported['activated']) ? $exported['activated'] : array();
            $isOn = isset($activated[$dir]);
            if ($enable === $isOn) {
                return json_encode(array(
                    'ok'        => true,
                    'directory' => $dir,
                    'enabled'   => $isOn,
                    'message'   => '插件已经是' . ($isOn ? '启用' : '停用') . '状态',
                ), JSON_UNESCAPED_UNICODE);
            }
            require_once $pluginFile;
            $target = '';
            $nsClass = '\TypechoPlugin\\' . $dir . '\Plugin';
            if (class_exists($nsClass)) {
                $target = $nsClass;
            } else {
                $code = (string) @file_get_contents($pluginFile);
                if (preg_match('/class\s+([A-Za-z_][A-Za-z0-9_]*)\s+(?:extends\s+[A-Za-z0-9_\\\\]+\s+)?implements\s+[^\{]*Plugin/i', $code, $m)) {
                    if (class_exists($m[1])) {
                        $target = $m[1];
                    }
                }
                if ($target === '') {
                    $declared = array();
                    foreach (get_declared_classes() as $c) {
                        if (stripos($c, $dir . '_Plugin') !== false) {
                            $declared[] = $c;
                        }
                    }
                    if (!empty($declared)) {
                        $target = $declared[count($declared) - 1];
                    }
                }
            }
            if ($target === '') {
                return json_encode(array('ok' => false, 'error' => '插件类加载失败（该插件可能不兼容当前 Typecho 版本）'), JSON_UNESCAPED_UNICODE);
            }
            $db = \Typecho\Db::get();
            if ($enable) {
                call_user_func(array($target, 'activate'));
                \Typecho\Plugin::activate($dir);
                $db->query($db->update('table.options')
                    ->rows(array('value' => json_encode(\Typecho\Plugin::export())))
                    ->where('name = ?', 'plugins'));
                try {
                    $form = new \Typecho\Widget\Helper\Form();
                    call_user_func(array($target, 'config'), $form);
                    $values = $form->getValues();
                    if (!empty($values)) {
                        self::savePluginConfig($dir, $values);
                    }
                } catch (Exception $e) {
                } catch (Throwable $e) {
                }
            } else {
                if (method_exists($target, 'deactivate')) {
                    call_user_func(array($target, 'deactivate'));
                }
                \Typecho\Plugin::deactivate($dir);
                $db->query($db->update('table.options')
                    ->rows(array('value' => json_encode(\Typecho\Plugin::export())))
                    ->where('name = ?', 'plugins'));
            }
            return json_encode(array(
                'ok'        => true,
                'directory' => $dir,
                'enabled'   => $enable,
                'title'     => isset($info['title']) ? (string) $info['title'] : $dir,
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolListComments(array $args)
    {
        try {
            $db = \Typecho\Db::get();
            $limit = isset($args['limit']) ? (int) $args['limit'] : 20;
            $limit = max(1, min(50, $limit));
            $status = isset($args['status']) ? (string) $args['status'] : '';
            $select = $db->select('coid', 'cid', 'author', 'mail', 'text', 'status', 'created')
                ->from('table.comments');
            if ($status !== '') {
                $select->where('status = ?', $status);
            }
            $select->order('created', \Typecho\Db::SORT_DESC)->limit($limit);
            $rows = $db->fetchAll($select);
            $out = array();
            foreach ($rows as $r) {
                $out[] = array(
                    'coid'    => (int) $r['coid'],
                    'cid'     => (int) $r['cid'],
                    'author'  => (string) $r['author'],
                    'mail'    => (string) $r['mail'],
                    'status'  => (string) $r['status'],
                    'created' => date('Y-m-d H:i', (int) $r['created']),
                    'excerpt' => mb_substr(trim(strip_tags((string) $r['text'])), 0, 80),
                );
            }
            $counts = array();
            foreach (array('approved', 'waiting', 'spam') as $st) {
                $row = $db->fetchRow($db->select(array('COUNT(*)' => 'n'))->from('table.comments')->where('status = ?', $st));
                $counts[$st] = $row ? (int) $row['n'] : 0;
            }
            return json_encode(array(
                'ok'       => true,
                'comments' => $out,
                'count'    => count($out),
                'counts'   => $counts,
                'coids'    => implode(',', array_map(function ($x) { return $x['coid']; }, $out)),
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolModerateComments(array $args)
    {
        try {
            $coidsRaw = isset($args['coids']) ? trim((string) $args['coids']) : '';
            $action = isset($args['action']) ? (string) $args['action'] : '';
            if (!in_array($action, array('approve', 'spam', 'delete'), true)) {
                return json_encode(array('ok' => false, 'error' => 'action 必须是 approve / spam / delete'), JSON_UNESCAPED_UNICODE);
            }
            if ($coidsRaw === '') {
                return json_encode(array('ok' => false, 'error' => 'coids 不能为空'), JSON_UNESCAPED_UNICODE);
            }
            $db = \Typecho\Db::get();
            $affected = 0;
            if (strtolower($coidsRaw) === 'all-waiting') {
                if ($action === 'delete') {
                    $affected = (int) $db->query($db->delete('table.comments')->where('status = ?', 'waiting'));
                } else {
                    $newStatus = ($action === 'approve') ? 'approved' : 'spam';
                    $affected = (int) $db->query($db->update('table.comments')
                        ->rows(array('status' => $newStatus))
                        ->where('status = ?', 'waiting'));
                }
            } else {
                $ids = array();
                foreach (explode(',', $coidsRaw) as $one) {
                    $n = (int) trim($one);
                    if ($n > 0) $ids[] = $n;
                }
                if (empty($ids)) {
                    return json_encode(array('ok' => false, 'error' => 'coids 格式不正确'), JSON_UNESCAPED_UNICODE);
                }
                if ($action === 'delete') {
                    $affected = (int) $db->query($db->delete('table.comments')->where('coid IN ?', $ids));
                } else {
                    $newStatus = ($action === 'approve') ? 'approved' : 'spam';
                    $affected = (int) $db->query($db->update('table.comments')
                        ->rows(array('status' => $newStatus))
                        ->where('coid IN ?', $ids));
                }
            }
            return json_encode(array(
                'ok'       => true,
                'action'   => $action,
                'affected' => $affected,
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolListTerms(array $args)
    {
        try {
            $type = isset($args['type']) ? (string) $args['type'] : 'category';
            if (!in_array($type, array('category', 'tag'), true)) {
                return json_encode(array('ok' => false, 'error' => 'type 必须是 category 或 tag'), JSON_UNESCAPED_UNICODE);
            }
            $db = \Typecho\Db::get();
            $rows = $db->fetchAll($db->select('mid', 'name', 'slug', 'count', 'description')
                ->from('table.metas')->where('type = ?', $type)->order('mid', \Typecho\Db::SORT_ASC));
            $out = array();
            foreach ($rows as $r) {
                $out[] = array(
                    'mid'         => (int) $r['mid'],
                    'name'        => (string) $r['name'],
                    'slug'        => (string) $r['slug'],
                    'count'       => (int) $r['count'],
                    'description' => (string) $r['description'],
                );
            }
            return json_encode(array('ok' => true, 'type' => $type, 'terms' => $out, 'count' => count($out)), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolCreateTerm(array $args)
    {
        try {
            $type = isset($args['type']) ? (string) $args['type'] : 'category';
            $name = isset($args['name']) ? trim((string) $args['name']) : '';
            $slug = isset($args['slug']) ? trim((string) $args['slug']) : '';
            if (!in_array($type, array('category', 'tag'), true)) {
                return json_encode(array('ok' => false, 'error' => 'type 必须是 category 或 tag'), JSON_UNESCAPED_UNICODE);
            }
            if ($name === '') {
                return json_encode(array('ok' => false, 'error' => '名称不能为空'), JSON_UNESCAPED_UNICODE);
            }
            $db = \Typecho\Db::get();
            $exists = $db->fetchRow($db->select('mid')->from('table.metas')
                ->where('type = ?', $type)->where('name = ?', $name)->limit(1));
            if ($exists) {
                return json_encode(array(
                    'ok'      => true,
                    'mid'     => (int) $exists['mid'],
                    'name'    => $name,
                    'type'    => $type,
                    'existed' => true,
                ), JSON_UNESCAPED_UNICODE);
            }
            if ($slug === '') {
                try {
                    $slug = (string) \Typecho\Common::slugName($name);
                } catch (Exception $e) {
                    $slug = '';
                } catch (Throwable $e) {
                    $slug = '';
                }
                if ($slug === '') $slug = $type . '-' . time();
            }
            $mid = (int) $db->query($db->insert('table.metas')->rows(array(
                'name'        => $name,
                'slug'        => $slug,
                'type'        => $type,
                'description' => '',
                'count'       => 0,
                'order'       => 0,
                'parent'      => 0,
            )));
            return json_encode(array(
                'ok'   => true,
                'mid'  => $mid,
                'name' => $name,
                'type' => $type,
                'slug' => $slug,
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolListPages()
    {
        try {
            $db = \Typecho\Db::get();
            $rows = $db->fetchAll($db->select('cid', 'title', 'slug', 'status', 'created')
                ->from('table.contents')->where('type = ?', 'page')
                ->where('status <> ?', 'trash')
                ->order('created', \Typecho\Db::SORT_DESC)->limit(50));
            $out = array();
            foreach ($rows as $r) {
                $out[] = array(
                    'cid'     => (int) $r['cid'],
                    'title'   => (string) $r['title'],
                    'slug'    => (string) $r['slug'],
                    'status'  => (string) $r['status'],
                    'created' => date('Y-m-d', (int) $r['created']),
                );
            }
            return json_encode(array('ok' => true, 'pages' => $out, 'count' => count($out)), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolGetSiteOptions()
    {
        try {
            $db = \Typecho\Db::get();
            $keys = array('title', 'description', 'siteUrl', 'postsListSize', 'timezone', 'allowRegister', 'defaultCategory', 'lang');
            $out = array();
            foreach ($keys as $k) {
                $row = $db->fetchRow($db->select('value')->from('table.options')
                    ->where('name = ?', $k)->where('user = ?', 0)->limit(1));
                if ($row) {
                    $out[$k] = (string) $row['value'];
                }
            }
            return json_encode(array('ok' => true, 'options' => $out), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolUpdateSiteOption(array $args)
    {
        try {
            $key = isset($args['key']) ? (string) $args['key'] : '';
            $value = isset($args['value']) ? (string) $args['value'] : '';
            $valid = array(
                'title'          => null,
                'description'    => null,
                'siteUrl'        => null,
                'postsListSize'  => null,
                'timezone'       => null,
                'allowRegister'  => array('0', '1'),
            );
            if (!array_key_exists($key, $valid)) {
                return json_encode(array(
                    'ok'    => false,
                    'error' => '不允许修改该站点设置：' . $key . '。可用键名：' . implode('、', array_keys($valid)),
                ), JSON_UNESCAPED_UNICODE);
            }
            if (is_array($valid[$key]) && !in_array($value, $valid[$key], true)) {
                return json_encode(array('ok' => false, 'error' => '值不合法（' . $key . '）'), JSON_UNESCAPED_UNICODE);
            }
            if ($key === 'siteUrl' && !preg_match('#^https?://#i', $value)) {
                return json_encode(array('ok' => false, 'error' => 'siteUrl 必须以 http:// 或 https:// 开头'), JSON_UNESCAPED_UNICODE);
            }
            $db = \Typecho\Db::get();
            $exists = $db->fetchRow($db->select('name')->from('table.options')
                ->where('name = ?', $key)->where('user = ?', 0)->limit(1));
            if ($exists) {
                $db->query($db->update('table.options')->rows(array('value' => $value))
                    ->where('name = ?', $key)->where('user = ?', 0));
            } else {
                $db->query($db->insert('table.options')->rows(array(
                    'name' => $key, 'user' => 0, 'value' => $value,
                )));
            }
            return json_encode(array('ok' => true, 'key' => $key, 'value' => $value), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolListUsers()
    {
        try {
            $db = \Typecho\Db::get();
            $rows = $db->fetchAll($db->select('uid', 'name', 'screenName', 'mail', 'group', 'created')
                ->from('table.users')->order('uid', \Typecho\Db::SORT_ASC)->limit(50));
            $out = array();
            foreach ($rows as $r) {
                $posts = $db->fetchRow($db->select(array('COUNT(*)' => 'n'))->from('table.contents')
                    ->where('authorId = ?', (int) $r['uid'])->where('type = ?', 'post'));
                $out[] = array(
                    'uid'        => (int) $r['uid'],
                    'name'       => (string) $r['name'],
                    'screenName' => (string) $r['screenName'],
                    'mail'       => (string) $r['mail'],
                    'group'      => (string) $r['group'],
                    'posts'      => $posts ? (int) $posts['n'] : 0,
                );
            }
            return json_encode(array('ok' => true, 'users' => $out, 'count' => count($out)), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolGetPluginConfig(array $args)
    {
        try {
            $dir = isset($args['directory']) ? trim((string) $args['directory']) : '';
            if ($dir === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $dir)) {
                return json_encode(array('ok' => false, 'error' => '插件目录名不合法'), JSON_UNESCAPED_UNICODE);
            }
            $db = \Typecho\Db::get();
            $row = $db->fetchRow($db->select('value')->from('table.options')
                ->where('name = ?', 'plugin:' . $dir)->where('user = ?', 0)->limit(1));
            if (!$row) {
                return json_encode(array(
                    'ok'      => true,
                    'directory' => $dir,
                    'config'  => array(),
                    'message' => '该插件还没有保存过设置（可能未启用或使用默认值）',
                ), JSON_UNESCAPED_UNICODE);
            }
            $cfg = @json_decode((string) $row['value'], true);
            if (!is_array($cfg)) {
                $cfg = @unserialize((string) $row['value']);
            }
            if (!is_array($cfg)) {
                $cfg = array();
            }
            foreach ($cfg as $k => $v) {
                if (preg_match('/key|secret|token|password|passwd/i', (string) $k) && is_string($v) && $v !== '') {
                    $cfg[$k] = '（已填写，长度 ' . strlen($v) . '）';
                }
            }
            return json_encode(array('ok' => true, 'directory' => $dir, 'config' => $cfg), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolUpdatePluginConfig(array $args)
    {
        try {
            $dir = isset($args['directory']) ? trim((string) $args['directory']) : '';
            $key = isset($args['key']) ? (string) $args['key'] : '';
            $value = isset($args['value']) ? (string) $args['value'] : '';
            if ($dir === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $dir)) {
                return json_encode(array('ok' => false, 'error' => '插件目录名不合法'), JSON_UNESCAPED_UNICODE);
            }
            if ($key === '') {
                return json_encode(array('ok' => false, 'error' => '缺少设置项键名'), JSON_UNESCAPED_UNICODE);
            }
            if (preg_match('/key|secret|token|password|passwd/i', $key)) {
                return json_encode(array(
                    'ok'    => false,
                    'error' => '出于安全考虑，不能通过 AI 修改密钥/密码类设置项（' . $key . '），请手动在插件设置页填写。',
                ), JSON_UNESCAPED_UNICODE);
            }
            $db = \Typecho\Db::get();
            $row = $db->fetchRow($db->select('value')->from('table.options')
                ->where('name = ?', 'plugin:' . $dir)->where('user = ?', 0)->limit(1));
            $cfg = array();
            if ($row) {
                $cfg = @json_decode((string) $row['value'], true);
                if (!is_array($cfg)) {
                    $cfg = @unserialize((string) $row['value']);
                }
                if (!is_array($cfg)) {
                    $cfg = array();
                }
            }
            $cfg[$key] = $value;
            $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (strlen($json) > 60000 && strlen($json) > strlen((string) ($row ? $row['value'] : ''))) {
                return json_encode(array(
                    'ok'    => false,
                    'error' => '这次写入会让插件设置超过 64KB 上限（当前 '
                        . strlen($json) . ' 字节），可能损坏全部插件设置。'
                        . '请缩短内容（自定义卡片的 HTML+JS 建议 ≤12KB），'
                        . '或改成到「插件设置 → 概要页卡片设置 → 自定义卡片」里手动粘贴保存。',
                ), JSON_UNESCAPED_UNICODE);
            }
            if ($row) {
                $db->query($db->update('table.options')->rows(array('value' => $json))
                    ->where('name = ?', 'plugin:' . $dir)->where('user = ?', 0));
            } else {
                $db->query($db->insert('table.options')->rows(array(
                    'name' => 'plugin:' . $dir, 'user' => 0, 'value' => $json,
                )));
            }
            return json_encode(array(
                'ok'        => true,
                'directory' => $dir,
                'key'       => $key,
                'value'     => $value,
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function skillsDir()
    {
        return dirname(__FILE__) . '/skills';
    }
    public static function listSkills()
    {
        $dir = self::skillsDir();
        if (!is_dir($dir)) {
            return array();
        }
        $out = array();
        $files = @glob($dir . '/*.md');
        if (!is_array($files)) {
            return array();
        }
        foreach ($files as $file) {
            $base = basename($file);
            if ($base === 'README.md') {
                continue;
            }
            $meta = self::parseSkillMeta($file);
            if ($meta === null) {
                continue;
            }
            $out[] = $meta;
        }
        usort($out, function ($a, $b) { return strcmp($a['name'], $b['name']); });
        return $out;
    }
    private static function parseSkillMeta($file)
    {
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $meta = array(
            'name'        => basename($file, '.md'),
            'title'       => basename($file, '.md'),
            'description' => '',
            'keywords'    => '',
        );
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $raw, $m)) {
            foreach (explode("\n", $m[1]) as $line) {
                if (!preg_match('/^\s*([A-Za-z_]+)\s*:\s*(.*)$/', $line, $kv)) {
                    continue;
                }
                $k = strtolower($kv[1]);
                $v = trim($kv[2]);
                if (isset($meta[$k])) {
                    $meta[$k] = $v;
                }
            }
        }
        return $meta;
    }
    public static function readSkill($name)
    {
        $name = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $name);
        if ($name === '') {
            return null;
        }
        $file = self::skillsDir() . '/' . $name . '.md';
        if (!is_file($file)) {
            return null;
        }
        $raw = (string) @file_get_contents($file);
        if ($raw === '') {
            return null;
        }
        $body = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $raw, 1);
        if (mb_strlen($body) > 12000) {
            $body = mb_substr($body, 0, 12000) . "\n\n（内容过长已截断）";
        }
        return trim($body);
    }
    public static function skillsPrompt()
    {
        $list = self::listSkills();
        if (empty($list)) {
            return '';
        }
        $lines = array();
        foreach ($list as $s) {
            $lines[] = '- ' . $s['name'] . '：' . $s['title'] . ' —— ' . $s['description'];
        }
        return "\n\n可用技能（Skills）：当用户的问题涉及下列主题时，先调用 read_skill 读取对应技能再回答，不要凭印象猜。\n"
            . implode("\n", $lines);
    }
    private static function toolListSkills()
    {
        $list = self::listSkills();
        return json_encode(array('ok' => true, 'skills' => $list, 'count' => count($list)), JSON_UNESCAPED_UNICODE);
    }
    private static function toolReadSkill(array $args)
    {
        $name = isset($args['name']) ? (string) $args['name'] : '';
        if ($name === '') {
            return json_encode(array('ok' => false, 'error' => '请提供技能名（name）'), JSON_UNESCAPED_UNICODE);
        }
        $body = self::readSkill($name);
        if ($body === null) {
            $avail = array();
            foreach (self::listSkills() as $s) {
                $avail[] = $s['name'];
            }
            return json_encode(array(
                'ok'      => false,
                'error'   => '技能不存在：' . $name,
                'available' => $avail,
            ), JSON_UNESCAPED_UNICODE);
        }
        return json_encode(array('ok' => true, 'name' => $name, 'content' => $body), JSON_UNESCAPED_UNICODE);
    }
    private static function toolUploadImage(array $args)
    {
        try {
            $source = isset($args['source']) ? (string) $args['source'] : 'base64';
            $data = isset($args['data']) ? (string) $args['data'] : '';
            $filename = isset($args['filename']) ? (string) $args['filename'] : '';
            if ($data === '') {
                return json_encode(array('ok' => false, 'error' => '缺少图片数据（data）'), JSON_UNESCAPED_UNICODE);
            }
            $binary = null;
            if ($source === 'base64') {
                if (preg_match('#^data:image/([a-z0-9+.-]+);base64,#i', $data, $m)) {
                    if ($filename === '') $filename = 'ai-' . time() . '.' . strtolower($m[1]);
                    $data = substr($data, strlen($m[0]));
                }
                $binary = @base64_decode($data, true);
                if ($binary === false) {
                    return json_encode(array('ok' => false, 'error' => 'base64 解码失败'), JSON_UNESCAPED_UNICODE);
                }
            } elseif ($source === 'url') {
                if (!preg_match('#^https?://#i', $data)) {
                    return json_encode(array('ok' => false, 'error' => '图片 URL 必须以 http(s):// 开头'), JSON_UNESCAPED_UNICODE);
                }
                if (!self::safeBaseUrl($data)) {
                    return json_encode(array('ok' => false, 'error' => '图片 URL 不合法（不允许内网地址）'), JSON_UNESCAPED_UNICODE);
                }
                $binary = self::httpGetBinary($data);
                if ($binary === null) {
                    return json_encode(array('ok' => false, 'error' => '下载图片失败'), JSON_UNESCAPED_UNICODE);
                }
                if ($filename === '') {
                    $path = parse_url($data, PHP_URL_PATH);
                    $filename = $path ? basename($path) : ('ai-' . time() . '.png');
                }
            } else {
                return json_encode(array('ok' => false, 'error' => 'source 必须是 base64 或 url'), JSON_UNESCAPED_UNICODE);
            }
            return self::storeImageBinary($binary, $filename);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolSaveChatImage(array $args)
    {
        $n = count(self::$chatImages);
        if ($n === 0) {
            return json_encode(array(
                'ok'    => false,
                'error' => '当前对话里没有可保存的图片（用户还没在这个会话里发过图片）。',
            ), JSON_UNESCAPED_UNICODE);
        }
        $idx = isset($args['index']) ? (int) $args['index'] : 0;
        if ($idx < 1 || $idx > $n) {
            return json_encode(array(
                'ok'    => false,
                'error' => '图片编号超出范围，当前可用的编号是 1-' . $n,
            ), JSON_UNESCAPED_UNICODE);
        }
        $im = self::$chatImages[$idx - 1];
        $filename = isset($args['filename']) ? trim((string) $args['filename']) : '';
        if ($filename === '') {
            $filename = $im['name'];
        }
        if (strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) === '') {
            $filename .= '.' . pathinfo($im['name'], PATHINFO_EXTENSION);
        }
        return self::storeImageBinary($im['data'], $filename, $im['mime']);
    }
    private static function storeImageBinary($binary, $filename, $mime = '')
    {
        if (!is_string($binary) || $binary === '') {
            return json_encode(array('ok' => false, 'error' => '图片数据为空'), JSON_UNESCAPED_UNICODE);
        }
        if (strlen($binary) > 10 * 1024 * 1024) {
            return json_encode(array('ok' => false, 'error' => '图片超过 10MB 限制'), JSON_UNESCAPED_UNICODE);
        }
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $allow = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'avif');
        if (!in_array($ext, $allow, true)) {
            return json_encode(array(
                'ok'    => false,
                'error' => '不支持的图片格式：' . ($ext === '' ? '(无扩展名)' : $ext)
                    . '（允许：' . implode(', ', $allow) . '）',
            ), JSON_UNESCAPED_UNICODE);
        }
        if ($mime === '') {
            $mime = self::mimeByExt($ext);
        }
        $picupErr = '';
        $via = self::picUpStore($binary, $filename, $mime, $picupErr);
        if (is_array($via)) {
            if ($picupErr !== '') {
                $via['note'] = $picupErr;
            }
            return json_encode($via, JSON_UNESCAPED_UNICODE);
        }
        $root = __TYPECHO_ROOT_DIR__;
        $sub = date('Y') . '/' . date('m');
        $dir = $root . '/usr/uploads/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return json_encode(array('ok' => false, 'error' => '无法创建上传目录（检查 usr/uploads 写权限）'), JSON_UNESCAPED_UNICODE);
        }
        $safeName = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $filename);
        if ($safeName === '' || $safeName === '.') {
            $safeName = 'ai-' . time() . '.' . $ext;
        }
        $target = $dir . '/' . $safeName;
        $i = 1;
        while (is_file($target)) {
            $base = pathinfo($safeName, PATHINFO_FILENAME);
            $target = $dir . '/' . $base . '-' . $i++ . '.' . $ext;
        }
        if (@file_put_contents($target, $binary) === false) {
            return json_encode(array('ok' => false, 'error' => '写入文件失败（检查目录权限）'), JSON_UNESCAPED_UNICODE);
        }
        $relPath = '/usr/uploads/' . $sub . '/' . basename($target);
        $siteUrl = '';
        try {
            $siteUrl = rtrim((string) Typecho_Widget::widget('Widget_Options')->siteUrl, '/');
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        $url = $siteUrl . $relPath;
        $cid = self::insertAttachmentRow(basename($target), $relPath, strlen($binary), $ext, $mime);
        $out = array(
            'ok'       => true,
            'url'      => $url,
            'path'     => $relPath,
            'name'     => basename($target),
            'size'     => strlen($binary),
            'cid'      => $cid,
            'storage'  => 'local',
            'markdown' => '![' . pathinfo(basename($target), PATHINFO_FILENAME) . '](' . $url . ')',
        );
        if ($picupErr !== '') {
            $out['note'] = $picupErr;
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }
    private static function picUpStore($binary, $filename, $mime, &$picupErr = '')
    {
        $cls = 'TypechoPlugin\\PicUp\\Plugin';
        if (!class_exists($cls) || !method_exists($cls, 'uploadHandle')) {
            return null;
        }
        try {
            $res = $cls::uploadHandle(array(
                'name'  => $filename,
                'size'  => strlen($binary),
                'type'  => $mime,
                'bytes' => $binary,
            ));
        } catch (Exception $e) {
            $picupErr = 'PicUp 上传失败，已回退到本地存储：' . $e->getMessage();
            return null;
        } catch (Throwable $e) {
            $picupErr = 'PicUp 上传失败，已回退到本地存储：' . $e->getMessage();
            return null;
        }
        if (!is_array($res) || empty($res['path'])) {
            $picupErr = 'PicUp 未返回存储路径，已回退到本地存储（请检查 PicUp 的驱动器配置）';
            return null;
        }
        $path = (string) $res['path'];
        $url = '';
        try {
            $url = (string) $cls::attachmentHandle(array('attachment' => array('path' => $path)));
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        if ($url === '') {
            $url = $path;
        }
        $name = isset($res['name']) ? (string) $res['name'] : $filename;
        $type = isset($res['type']) ? (string) $res['type'] : strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $mimeOut = isset($res['mime']) && $res['mime'] !== '' ? (string) $res['mime'] : $mime;
        $size = isset($res['size']) && (int) $res['size'] > 0 ? (int) $res['size'] : strlen($binary);
        $storePath = $path;
        if (preg_match('#^https?://#i', $path)) {
            try {
                $siteUrl = rtrim((string) Typecho_Widget::widget('Widget_Options')->siteUrl, '/');
                if ($siteUrl !== '' && strpos($path, $siteUrl) === 0) {
                    $storePath = substr($path, strlen($siteUrl));
                    if ($storePath === '') {
                        $storePath = '/';
                    }
                }
            } catch (Exception $e) {
            } catch (Throwable $e) {
            }
        }
        $cid = self::insertAttachmentRow($name, $storePath, $size, $type, $mimeOut);
        return array(
            'ok'       => true,
            'url'      => $url,
            'path'     => $path,
            'name'     => $name,
            'size'     => $size,
            'mime'     => $mimeOut,
            'cid'      => $cid,
            'storage'  => 'PicUp',
            'markdown' => '![' . pathinfo($name, PATHINFO_FILENAME) . '](' . $url . ')',
        );
    }
    private static function insertAttachmentRow($name, $relPath, $size, $type, $mime)
    {
        try {
            $user = Typecho_Widget::widget('Widget_User');
            $db = \Typecho\Db::get();
            $now = time();
            $type = trim((string) $type);
            if ($type === '') {
                $type = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));
            }
            $mime = is_string($mime) ? trim($mime) : '';
            if ($mime === '') {
                $mime = self::mimeByExt($type);
            }
            $info = array(
                'name' => (string) $name,
                'path' => (string) $relPath,
                'size' => (int) $size,
                'type' => $type,
                'mime' => $mime,
            );
            return (int) $db->query($db->insert('table.contents')->rows(array(
                'title'        => $name,
                'slug'         => 'attachment-' . $now . '-' . mt_rand(1000, 9999),
                'text'         => self::encodeAttachmentText($info),
                'type'         => 'attachment',
                'status'       => 'publish',
                'authorId'     => (int) $user->uid,
                'created'      => $now,
                'modified'     => $now,
                'allowComment' => '0',
                'allowPing'    => '0',
                'allowFeed'    => '0',
            )));
        } catch (Exception $e) {
        } catch (Throwable $e) {
        }
        return 0;
    }
    private static function encodeAttachmentText($info)
    {
        if (!class_exists('AdminBeautify_Action')) {
            $file = __DIR__ . '/Action.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
        if (class_exists('AdminBeautify_Action')) {
            return AdminBeautify_Action::encodeAttachment($info);
        }
        return serialize($info);
    }
    private static function collectChatImages(array $messages, $limit = 8, $maxBytes = 8388608)
    {
        $map = array(
            'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png',
            'image/gif' => 'gif', 'image/webp' => 'webp', 'image/bmp' => 'bmp',
            'image/svg+xml' => 'svg', 'image/avif' => 'avif',
        );
        $out = array();
        foreach ($messages as $m) {
            if (!is_array($m) || (isset($m['role']) ? $m['role'] : '') !== 'user') {
                continue;
            }
            $content = isset($m['content']) ? $m['content'] : null;
            if (!is_array($content)) {
                continue;
            }
            foreach ($content as $part) {
                if (!is_array($part) || (isset($part['type']) ? $part['type'] : '') !== 'image_url') {
                    continue;
                }
                $url = isset($part['image_url']['url']) ? (string) $part['image_url']['url'] : '';
                if (strpos($url, 'data:image/') !== 0) {
                    continue;
                }
                if (!preg_match('#^data:image/([a-z0-9+.\-]+);base64,(.+)$#is', $url, $mm)) {
                    continue;
                }
                $binary = @base64_decode($mm[2], true);
                if ($binary === false || $binary === '') {
                    continue;
                }
                if (strlen($binary) > $maxBytes) {
                    continue;
                }
                $mime = 'image/' . strtolower($mm[1]);
                $ext = isset($map[$mime]) ? $map[$mime] : 'png';
                $out[] = array(
                    'name' => 'chat-image-' . (count($out) + 1) . '.' . $ext,
                    'mime' => $mime,
                    'size' => strlen($binary),
                    'data' => $binary,
                );
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }
        return $out;
    }
    private static function humanSize($n)
    {
        $n = (int) $n;
        if ($n >= 1048576) {
            return round($n / 1048576, 1) . ' MB';
        }
        if ($n >= 1024) {
            return round($n / 1024) . ' KB';
        }
        return $n . ' B';
    }
    public static function stripImagesForStore(array $messages)
    {
        foreach ($messages as $i => $m) {
            if (!is_array($m) || !isset($m['content']) || !is_array($m['content'])) {
                continue;
            }
            $text = '';
            $imgs = 0;
            foreach ($m['content'] as $p) {
                if (!is_array($p) || !isset($p['type'])) {
                    continue;
                }
                if ($p['type'] === 'image_url') {
                    $imgs++;
                } elseif ($p['type'] === 'text') {
                    $text .= isset($p['text']) ? (string) $p['text'] : '';
                }
            }
            if ($imgs > 0) {
                $text = trim($text) === ''
                    ? '（图片 ×' . $imgs . '）'
                    : ($text . "\n\n（图片 ×" . $imgs . '）');
            }
            $messages[$i]['content'] = $text;
        }
        return $messages;
    }
    private static function mimeByExt($ext)
    {
        $map = array(
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
        );
        return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
    }
    private static function httpGetBinary($url)
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'AdminBeautify-AI/' . self::VERSION,
        ));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) {
            return null;
        }
        return $body;
    }
    private static function toolRemovePlugin(array $args)
    {
        try {
            $dir = isset($args['directory']) ? trim((string) $args['directory']) : '';
            if ($dir === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $dir)) {
                return json_encode(array('ok' => false, 'error' => '插件目录名不合法'), JSON_UNESCAPED_UNICODE);
            }
            if ($dir === 'AdminBeautify') {
                return json_encode(array('ok' => false, 'error' => '不能删除 AdminBeautify 自身'), JSON_UNESCAPED_UNICODE);
            }
            require_once dirname(__FILE__) . '/store/Core.php';
            require_once dirname(__FILE__) . '/store/Installer.php';
            $res = AdminBeautify_StoreInstall::uninstall($dir);
            if (isset($res['error'])) {
                return json_encode(array('ok' => false, 'error' => (string) $res['error']), JSON_UNESCAPED_UNICODE);
            }
            return json_encode(array(
                'ok'        => true,
                'directory' => $dir,
                'message'   => isset($res['message']) ? (string) $res['message'] : '已删除',
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolSearchPosts(array $args)
    {
        try {
            $kw = isset($args['keyword']) ? trim((string) $args['keyword']) : '';
            if ($kw === '') {
                return json_encode(array('ok' => false, 'error' => '请提供搜索关键词'), JSON_UNESCAPED_UNICODE);
            }
            $limit = isset($args['limit']) ? (int) $args['limit'] : 20;
            $limit = max(1, min(50, $limit));
            $db = \Typecho\Db::get();
            $like = '%' . $kw . '%';
            $rows = $db->fetchAll($db->select('cid', 'title', 'status', 'created')
                ->from('table.contents')
                ->where('type = ?', 'post')
                ->where('status <> ?', 'trash')
                ->where('title LIKE ? OR text LIKE ?', $like, $like)
                ->order('created', \Typecho\Db::SORT_DESC)
                ->limit($limit));
            $out = array();
            foreach ($rows as $r) {
                $out[] = array(
                    'cid'     => (int) $r['cid'],
                    'title'   => (string) $r['title'],
                    'status'  => (string) $r['status'],
                    'created' => date('Y-m-d', (int) $r['created']),
                );
            }
            return json_encode(array(
                'ok'      => true,
                'keyword' => $kw,
                'posts'   => $out,
                'count'   => count($out),
                'cids'    => implode(',', array_map(function ($x) { return $x['cid']; }, $out)),
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolGetPostContent(array $args)
    {
        try {
            $cid = isset($args['cid']) ? (int) $args['cid'] : 0;
            if ($cid <= 0) return json_encode(array('ok' => false, 'error' => '缺少文章 ID'), JSON_UNESCAPED_UNICODE);
            $db = \Typecho\Db::get();
            $row = $db->fetchRow($db->select('cid', 'title', 'text', 'status')
                ->from('table.contents')->where('cid = ?', $cid));
            if (!$row) return json_encode(array('ok' => false, 'error' => '文章不存在'), JSON_UNESCAPED_UNICODE);
            $text = (string) $row['text'];
            $isMd = (0 === strpos($text, '<!--markdown-->'));
            if ($isMd) $text = substr($text, strlen('<!--markdown-->'));
            return json_encode(array(
                'ok'       => true,
                'cid'      => $cid,
                'title'    => (string) $row['title'],
                'status'   => (string) $row['status'],
                'markdown' => $isMd,
                'length'   => mb_strlen($text),
                'content'  => $text,
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function toolBatchReplace(array $args)
    {
        try {
            $cidsRaw = isset($args['cids']) ? trim((string) $args['cids']) : '';
            $search = isset($args['search']) ? (string) $args['search'] : '';
            $replace = isset($args['replace']) ? (string) $args['replace'] : '';
            $dry = !empty($args['dryRun']);
            if ($search === '') {
                return json_encode(array('ok' => false, 'error' => 'search 不能为空'), JSON_UNESCAPED_UNICODE);
            }
            if ($cidsRaw === '') {
                return json_encode(array('ok' => false, 'error' => 'cids 不能为空（传 all 表示全部文章）'), JSON_UNESCAPED_UNICODE);
            }
            $db = \Typecho\Db::get();
            $select = $db->select('cid', 'title', 'text')->from('table.contents')
                ->where('type = ?', 'post')->where('status <> ?', 'trash');
            if (strtolower($cidsRaw) !== 'all') {
                $ids = array();
                foreach (explode(',', $cidsRaw) as $one) {
                    $n = (int) trim($one);
                    if ($n > 0) $ids[] = $n;
                }
                if (empty($ids)) {
                    return json_encode(array('ok' => false, 'error' => 'cids 格式不正确'), JSON_UNESCAPED_UNICODE);
                }
                $select->where('cid IN ?', $ids);
            }
            $rows = $db->fetchAll($select);
            $scanned = 0;
            $changed = 0;
            $details = array();
            foreach ($rows as $row) {
                $scanned++;
                $text = (string) $row['text'];
                if (strpos($text, $search) === false) {
                    continue;
                }
                $hits = substr_count($text, $search);
                $newText = str_replace($search, $replace, $text);
                $changed++;
                $details[] = array(
                    'cid'   => (int) $row['cid'],
                    'title' => (string) $row['title'],
                    'hits'  => $hits,
                );
                if (!$dry) {
                    $db->query($db->update('table.contents')
                        ->rows(array('text' => $newText, 'modified' => time()))
                        ->where('cid = ?', (int) $row['cid']));
                }
            }
            return json_encode(array(
                'ok'      => true,
                'dryRun'  => $dry,
                'scanned' => $scanned,
                'changed' => $changed,
                'search'  => $search,
                'replace' => $replace,
                'details' => array_slice($details, 0, 20),
            ), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
    private static function savePluginConfig($pluginName, array $values)
    {
        $db = \Typecho\Db::get();
        $name = 'plugin:' . $pluginName;
        $json = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $exists = $db->fetchRow($db->select('name')->from('table.options')->where('name = ?', $name)->where('user = ?', 0));
        if ($exists) {
            $db->query($db->update('table.options')->rows(array('value' => $json))->where('name = ?', $name)->where('user = ?', 0));
        } else {
            $db->query($db->insert('table.options')->rows(array('name' => $name, 'user' => 0, 'value' => $json)));
        }
    }
    private static function toolUpdateSetting(array $args)
    {
        try {
            $key = isset($args['key']) ? (string) $args['key'] : '';
            $value = isset($args['value']) ? (string) $args['value'] : '';
            $valid = array(
                'enableAnimation'    => array('0', '1'),
                'darkMode'           => array('auto', 'light', 'dark'),
                'borderRadius'       => array('small', 'medium', 'large'),
                'loadingAnimation'   => array('morph', 'spinner', 'topbar'),
                'ai_enabled'         => array('0', '1'),
                'ai_allow_non_admin' => array('0', '1'),
            );
            if (!isset($valid[$key])) {
                $keys = implode('、', array_keys($valid));
                return json_encode(array('ok' => false, 'error' => '不允许修改该设置项：' . $key . '。可用键名：' . $keys), JSON_UNESCAPED_UNICODE);
            }
            if (!in_array($value, $valid[$key], true)) {
                return json_encode(array('ok' => false, 'error' => '值不合法（' . $key . '），可用值：' . implode('|', $valid[$key])), JSON_UNESCAPED_UNICODE);
            }
            AdminBeautify_Plugin::savePluginOptions('AdminBeautify', array($key => $value));
            return json_encode(array('ok' => true, 'key' => $key, 'value' => $value), JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
        }
    }
}
