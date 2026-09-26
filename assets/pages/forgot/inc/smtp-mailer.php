<?php
/**
 * AdminBeautify 精简 SMTP 发件客户端
 *
 * 背景：Typecho 1.2/1.3 自身没有任何邮件发送能力（`Typecho\Common` 里没有 sendMail，
 * 也没有内置 Swiftmailer / PHPMailer）。而「忘记密码」的邮件认证方式必须能发信，
 * 所以这里用 `stream_socket_client` 手写一个只依赖 PHP 标准扩展的最小 SMTP 客户端。
 *
 * 支持：
 *   - 隐式 SSL（通常 465，`ssl://`）
 *   - STARTTLS（通常 587，先明文握手再升级加密）
 *   - 明文（通常 25，仅建议内网 / 本机中继）
 *   - AUTH LOGIN（首选）与 AUTH PLAIN（回退）；服务器未要求认证时可留空用户名
 *
 * 不引入任何第三方依赖，也不写日志文件：出错只通过 getLastError() 返回中文原因，
 * 由调用方决定是否展示给用户（避免把 SMTP 细节泄漏到前台）。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class AdminBeautifySmtpMailer
{
    /** @var string SMTP 主机 */
    private $host = '';
    /** @var int 端口 */
    private $port = 465;
    /** @var string ssl | tls | none */
    private $secure = 'ssl';
    /** @var string 认证用户名 */
    private $user = '';
    /** @var string 认证密码 */
    private $pass = '';
    /** @var string 发件人邮箱 */
    private $from = '';
    /** @var string 发件人名称 */
    private $fromName = '';
    /** @var int 连接/读写超时（秒） */
    private $timeout = 15;
    /** @var resource|null 套接字 */
    private $socket = null;
    /** @var string 最近一次错误（中文） */
    private $lastError = '';
    /** @var string 最近一次 EHLO 的响应文本（用来判断服务器支持哪些 AUTH 机制） */
    private $ehloText = '';
    /** @var array 会话记录（排错用，含敏感信息，不对外输出） */
    private $trace = array();

    /**
     * @param array $conf 支持键：host, port, secure, user, pass, from, fromName, timeout
     */
    public function __construct(array $conf = array())
    {
        if (isset($conf['host']))     $this->host     = trim((string) $conf['host']);
        if (isset($conf['port']))     $this->port     = (int) $conf['port'];
        if (isset($conf['secure']))   $this->secure   = $this->normalizeSecure((string) $conf['secure']);
        if (isset($conf['user']))     $this->user     = (string) $conf['user'];
        if (isset($conf['pass']))     $this->pass     = (string) $conf['pass'];
        if (isset($conf['from']))     $this->from     = trim((string) $conf['from']);
        if (isset($conf['fromName'])) $this->fromName = trim((string) $conf['fromName']);
        if (isset($conf['timeout']))  $this->timeout  = max(5, min(60, (int) $conf['timeout']));

        if ($this->port <= 0) {
            $this->port = $this->secure === 'ssl' ? 465 : ($this->secure === 'tls' ? 587 : 25);
        }
    }

    /**
     * 从插件设置构建实例
     *
     * @param mixed $opt 插件设置对象（Widget_Options::plugin('AdminBeautify')）
     * @return self
     */
    public static function fromPluginOptions($opt)
    {
        $get = function ($key, $default = '') use ($opt) {
            if (!$opt || !isset($opt->$key)) return $default;
            $v = $opt->$key;
            return ($v === null) ? $default : (string) $v;
        };

        $from = trim($get('smtp_from'));
        if ($from === '') {
            // 兜底：用用户名当发件人（多数服务商要求 from 与登录账号一致）
            $from = trim($get('smtp_user'));
        }

        return new self(array(
            'host'     => $get('smtp_host'),
            'port'     => $get('smtp_port'),
            'secure'   => $get('smtp_secure', 'ssl'),
            'user'     => $get('smtp_user'),
            'pass'     => $get('smtp_pass'),
            'from'     => $from,
            'fromName' => $get('smtp_fromName'),
        ));
    }

    /**
     * 配置是否完整到可以尝试发信（用于决定前台是否显示「邮件认证」方式）
     *
     * @param mixed $opt 插件设置对象
     * @return bool
     */
    public static function isReadyFromPluginOptions($opt)
    {
        if (!$opt) return false;
        if ((string) (isset($opt->smtp_enabled) ? $opt->smtp_enabled : '0') !== '1') return false;

        $host = isset($opt->smtp_host) ? trim((string) $opt->smtp_host) : '';
        $from = isset($opt->smtp_from) ? trim((string) $opt->smtp_from) : '';
        $user = isset($opt->smtp_user) ? trim((string) $opt->smtp_user) : '';

        // 发件人缺失时回退到用户名（见 fromPluginOptions），两者都空才算没配
        return ($host !== '' && ($from !== '' || $user !== ''));
    }

    /** @return string 最近一次错误（中文），无错误时为空串 */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * 发送一封 HTML 邮件
     *
     * @param string $to      收件人邮箱
     * @param string $subject 主题
     * @param string $html    HTML 正文
     * @return bool
     */
    public function send($to, $subject, $html)
    {
        $to = trim((string) $to);
        if ($to === '' || strpos($to, '@') === false) {
            $this->lastError = '收件人邮箱无效';
            return false;
        }
        if ($this->host === '' || $this->from === '') {
            $this->lastError = 'SMTP 未配置完整（缺少服务器地址或发件人邮箱）';
            return false;
        }
        if (!function_exists('stream_socket_client')) {
            $this->lastError = '服务器不支持 stream_socket_client，无法发信';
            return false;
        }

        try {
            $this->connect();
            $this->greet();
            $this->auth();
            $this->deliver($to, $subject, $html);
            $this->quit();
            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->abort();
            return false;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->abort();
            return false;
        }
    }

    // ------------------------------------------------------------------
    // 内部实现
    // ------------------------------------------------------------------

    private function normalizeSecure($s)
    {
        $s = strtolower(trim($s));
        if ($s === 'starttls') $s = 'tls';
        if ($s === 'plain' || $s === 'no' || $s === 'false') $s = 'none';
        return in_array($s, array('ssl', 'tls', 'none'), true) ? $s : 'ssl';
    }

    private function connect()
    {
        $transport = ($this->secure === 'ssl') ? 'ssl' : 'tcp';
        $remote = $transport . '://' . $this->host . ':' . $this->port;

        $context = stream_context_create(array(
            'ssl' => array(
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
                'peer_name'         => $this->host,
            ),
        ));

        $errno = 0;
        $errstr = '';
        $sock = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$sock) {
            $this->lastError = sprintf(
                '无法连接 %s:%d（%s）。请确认服务器允许对外连接该端口（部分主机封禁 465/587）',
                $this->host,
                $this->port,
                ($errstr !== '' ? $errstr : 'errno ' . $errno)
            );
            throw new Exception($this->lastError);
        }

        stream_set_timeout($sock, $this->timeout);
        $this->socket = $sock;
    }

    /**
     * 读一行（带超时检测）
     */
    private function readLine()
    {
        if (!$this->socket) {
            throw new Exception('SMTP 连接已断开');
        }
        $line = @fgets($this->socket, 1024);
        if ($line === false) {
            $meta = stream_get_meta_data($this->socket);
            if (!empty($meta['timed_out'])) {
                throw new Exception('SMTP 读取超时（' . $this->timeout . ' 秒）');
            }
            throw new Exception('SMTP 连接被对端关闭');
        }
        $this->trace[] = '< ' . rtrim($line, "\r\n");
        return $line;
    }

    /**
     * 读一个完整响应，返回 [code, text]
     * SMTP 的多行响应形如：250-FIRST\r\n250-SECOND\r\n250 LAST\r\n
     */
    private function readResponse()
    {
        $code = 0;
        $text = '';
        for ($i = 0; $i < 40; $i++) {
            $line = $this->readLine();
            $code = (int) substr($line, 0, 3);
            $text .= substr($line, 4);
            // 第 4 个字符是空格 → 这是最后一行
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        return array($code, trim($text));
    }

    /**
     * 发送命令并断言期望的状态码
     *
     * @param string $cmd
     * @param array  $expect 允许的状态码
     * @param string $what   出错时的中文描述
     * @return array [code, text, rawCode]
     */
    private function cmd($cmd, array $expect, $what)
    {
        if ($cmd !== null && $cmd !== '') {
            $this->trace[] = '> ' . $cmd;
            if (@fwrite($this->socket, $cmd . "\r\n") === false) {
                throw new Exception('SMTP 写入失败（' . $what . '）');
            }
        }

        list($code, $text) = $this->readResponse();
        if (!in_array($code, $expect, true)) {
            throw new Exception(sprintf(
                '%s 失败：服务器返回 %d %s',
                $what,
                $code,
                $text !== '' ? $text : '(无描述)'
            ));
        }
        return array($code, $text);
    }

    private function greet()
    {
        list($code) = $this->readResponse();
        if ($code !== 220) {
            throw new Exception('SMTP 服务器未就绪（返回 ' . $code . '）');
        }

        // EHLO 用本机主机名；被拒时退回 HELO
        $ehloName = 'localhost';
        if (!empty($_SERVER['HTTP_HOST'])) {
            $host = preg_replace('/[^A-Za-z0-9\.\-]/', '', (string) $_SERVER['HTTP_HOST']);
            if ($host !== '') $ehloName = $host;
        }

        try {
            $res = $this->cmd('EHLO ' . $ehloName, array(250), 'EHLO 握手');
        } catch (Exception $e) {
            $res = $this->cmd('HELO ' . $ehloName, array(250), 'HELO 握手');
        }
        $this->ehloText = isset($res[1]) ? $res[1] : '';

        if ($this->secure === 'tls') {
            $this->cmd('STARTTLS', array(220), 'STARTTLS');
            $ok = @stream_socket_enable_crypto(
                $this->socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );
            if ($ok !== true) {
                throw new Exception('STARTTLS 加密协商失败（服务器可能不支持 TLS）');
            }
            // 升级加密后必须重新 EHLO
            $res2 = $this->cmd('EHLO ' . $ehloName, array(250), 'EHLO（TLS 后）');
            $this->ehloText = isset($res2[1]) ? $res2[1] : '';
        }
    }

    private function auth()
    {
        if ($this->user === '') {
            return; // 无需认证的中继
        }

        $hasLogin = stripos($this->ehloText, 'AUTH') !== false
                 && stripos($this->ehloText, 'LOGIN') !== false;

        if ($hasLogin) {
            $this->cmd('AUTH LOGIN', array(334), 'AUTH LOGIN');
            $this->cmd(base64_encode($this->user), array(334), 'AUTH 用户名');
            $this->cmd(base64_encode($this->pass), array(235, 503), 'AUTH 密码');
            return;
        }

        // 回退 AUTH PLAIN（\0user\0pass）
        $plain = base64_encode("\0" . $this->user . "\0" . $this->pass);
        $this->cmd('AUTH PLAIN ' . $plain, array(235, 503), 'AUTH PLAIN');
    }

    private function deliver($to, $subject, $html)
    {
        $from = $this->from;

        $this->cmd('MAIL FROM:<' . $from . '>', array(250), 'MAIL FROM');
        $this->cmd('RCPT TO:<' . $to . '>', array(250, 251), 'RCPT TO');
        $this->cmd('DATA', array(354), 'DATA');

        $data = $this->buildMessage($to, $subject, $html);
        $this->trace[] = '> [DATA ' . strlen($data) . ' bytes]';

        // 正文里的换行统一为 CRLF，并做点填充（行首的 . 要写成 ..）
        // 注意：必须先把 CRLF/CR 统一成 LF、再转 CRLF；直接按 "\r\n" 优先替换会因为
        // 后续的 "\r" 规则命中 CRLF 里的 \r，反而产生 "\r\n\n" 这种多出的空行。
        $data = str_replace(array("\r\n", "\r"), "\n", $data);
        $data = str_replace("\n", "\r\n", $data);
        $data = preg_replace('/^\./m', '..', $data);
        $data .= "\r\n.";

        if (@fwrite($this->socket, $data . "\r\n") === false) {
            throw new Exception('SMTP 正文写入失败');
        }

        list($code, $text) = $this->readResponse();
        if ($code !== 250) {
            throw new Exception('邮件被服务器拒收：' . $code . ' ' . $text);
        }
    }

    private function buildMessage($to, $subject, $html)
    {
        $eol = "\r\n";
        $boundary = 'ab-' . md5(uniqid('', true));

        $fromName = ($this->fromName !== '') ? $this->fromName : $this->from;
        $encodeName = function ($s) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        };

        $headers = array();
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . $encodeName($fromName) . ' <' . $this->from . '>';
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'Subject: ' . $encodeName($subject);
        $headers[] = 'Message-ID: <' . $boundary . '@' . $this->mailDomain() . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $headers[] = 'X-Mailer: AdminBeautify SMTP';

        // HTML 正文用 base64 传输，彻底避开 8bit / 行长超限 / 中文编码问题
        $body = chunk_split(base64_encode($html), 76, $eol);

        return implode($eol, $headers) . $eol . $eol . $body;
    }

    private function mailDomain()
    {
        $host = parse_url((string) (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''), PHP_URL_HOST);
        if (!$host) {
            $host = parse_url($this->from, PHP_URL_HOST);
        }
        return $host ? preg_replace('/[^A-Za-z0-9\.\-]/', '', $host) : 'localhost';
    }

    private function quit()
    {
        if ($this->socket) {
            @fwrite($this->socket, "QUIT\r\n");
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    private function abort()
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}
