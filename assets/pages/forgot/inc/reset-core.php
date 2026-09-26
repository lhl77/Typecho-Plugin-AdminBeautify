<?php
/**
 * AdminBeautify 「忘记密码」核心逻辑
 *
 * 两种身份验证方式（都不需要登录）：
 *
 *   方式一 · 邮件认证
 *     向用户邮箱发送 6 位数字验证码；仅当插件已启用且填写完整 SMTP 设置时可用。
 *
 *   方式二 · 本地文件验证
 *     前台显示一串 md5（由 邮箱 + 随机 rid + 签发时间 推导，1 小时内有效），
 *     站长在站点根目录的 usr/ 下创建一个「文件名与内容都等于该串」的文件，
 *     前台点击「验证本地文件」后由服务端读取该文件比对。
 *     安全性来自「只有能写服务器文件系统的人才能完成这一步」。
 *
 * 验证通过后签发一次性 grant，进入重置密码页；重置成功后立即销毁记录，
 * 并同时刷新 users.authCode 让所有旧会话失效（强制重新登录）。
 *
 * 存储：复用插件自建表 {prefix}abadmin（uid=0，card_key='pwdreset-<rid>'）。
 * 之所以用数据库而不是文件：方式二要求「站长手工放文件」，说明 usr/ 目录
 * 未必对 PHP 可写，因此不能把服务端状态写到那里。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class AdminBeautifyPasswordReset
{
    /** 记录键前缀（与自定义卡片的 card- 前缀不冲突） */
    const KEY_PREFIX = 'pwdreset-';

    /** 验证有效期：1 小时 */
    const TTL = 3600;

    /** 单条记录最多可尝试次数（验证码 / 本地文件共用） */
    const MAX_ATTEMPTS = 5;

    /** 同一邮箱重新发送验证码的最小间隔（秒） */
    const MAIL_RESEND_COOLDOWN = 60;

    /** 验证通过后，grant 的有效期（秒）——留足填新密码的时间 */
    const GRANT_TTL = 900;

    /** 本地文件所在目录（相对站点根） */
    const FILE_DIR = '/usr/';

    // ==================================================================
    // 数据库
    // ==================================================================

    /** @return Typecho_Db|null */
    private static function db()
    {
        try {
            $db = Typecho_Db::get();
            return $db ? $db : null;
        } catch (Exception $e) {
            return null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * 确保存储表存在（首次使用时自动建表）
     *
     * @return bool
     */
    public static function ensureStorage()
    {
        if (!class_exists('AdminBeautify_Plugin')) {
            return false;
        }
        try {
            return (bool) AdminBeautify_Plugin::ensureCardTable();
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * 读取一条记录
     *
     * @param string $rid
     * @return array|null
     */
    public static function find($rid)
    {
        $rid = self::cleanRid($rid);
        if ($rid === '') return null;

        $db = self::db();
        if (!$db) return null;

        try {
            $row = $db->fetchRow(
                $db->select()->from('table.abadmin')
                    ->where('uid = ?', 0)
                    ->where('card_key = ?', self::KEY_PREFIX . $rid)
                    ->limit(1)
            );
        } catch (Exception $e) {
            return null;
        } catch (Throwable $e) {
            return null;
        }

        if (!$row || empty($row['content'])) return null;

        $rec = json_decode((string) $row['content'], true);
        if (!is_array($rec) || empty($rec['rid'])) return null;
        return $rec;
    }

    /**
     * 按邮箱找回「仍有效」的记录（用于同邮箱只保留一份待处理请求）
     *
     * @param string $mail
     * @return array|null
     */
    public static function findByMail($mail)
    {
        $mail = self::normMail($mail);
        if ($mail === '') return null;

        $db = self::db();
        if (!$db) return null;

        try {
            $rows = $db->fetchAll(
                $db->select()->from('table.abadmin')
                    ->where('uid = ?', 0)
                    ->where('card_key LIKE ?', self::KEY_PREFIX . '%')
            );
        } catch (Exception $e) {
            return null;
        } catch (Throwable $e) {
            return null;
        }

        foreach ((array) $rows as $row) {
            $rec = json_decode((string) (isset($row['content']) ? $row['content'] : ''), true);
            if (is_array($rec) && isset($rec['mail']) && self::normMail($rec['mail']) === $mail) {
                return $rec;
            }
        }
        return null;
    }

    /**
     * 写入 / 覆盖记录
     *
     * @param array $rec
     * @return bool
     */
    public static function save(array $rec)
    {
        if (empty($rec['rid'])) return false;

        $db = self::db();
        if (!$db) return false;

        $key = self::KEY_PREFIX . self::cleanRid($rec['rid']);
        $now = time();
        $json = json_encode($rec, JSON_UNESCAPED_UNICODE);

        try {
            $exists = $db->fetchRow(
                $db->select('id')->from('table.abadmin')
                    ->where('uid = ?', 0)->where('card_key = ?', $key)->limit(1)
            );

            if ($exists) {
                $db->query($db->update('table.abadmin')
                    ->rows(array('content' => $json, 'updated_at' => $now))
                    ->where('id = ?', (int) $exists['id']));
            } else {
                $db->query($db->insert('table.abadmin')->rows(array(
                    'uid'        => 0,
                    'card_key'   => $key,
                    'content'    => $json,
                    'created_at' => $now,
                    'updated_at' => $now,
                )));
            }
            return true;
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * 删除记录
     *
     * @param string $rid
     * @return bool
     */
    public static function destroy($rid)
    {
        $rid = self::cleanRid($rid);
        if ($rid === '') return false;

        $db = self::db();
        if (!$db) return false;

        try {
            $db->query($db->delete('table.abadmin')
                ->where('uid = ?', 0)
                ->where('card_key = ?', self::KEY_PREFIX . $rid));
            return true;
        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * 清掉所有已过期记录（每次新建请求时顺手调用，避免表里堆垃圾）
     *
     * @return int 清理条数
     */
    public static function purgeExpired()
    {
        $db = self::db();
        if (!$db) return 0;

        try {
            $rows = $db->fetchAll(
                $db->select()->from('table.abadmin')
                    ->where('uid = ?', 0)
                    ->where('card_key LIKE ?', self::KEY_PREFIX . '%')
            );
        } catch (Exception $e) {
            return 0;
        } catch (Throwable $e) {
            return 0;
        }

        $n = 0;
        foreach ((array) $rows as $row) {
            $rec = json_decode((string) (isset($row['content']) ? $row['content'] : ''), true);
            $key = (string) (isset($row['card_key']) ? $row['card_key'] : '');
            $rid = substr($key, strlen(self::KEY_PREFIX));

            $expired = !is_array($rec)
                    || !isset($rec['expiresAt'])
                    || (int) $rec['expiresAt'] < time();

            if ($expired && $rid !== '') {
                self::destroy($rid);
                $n++;
            }
        }
        return $n;
    }

    // ==================================================================
    // 用户与请求
    // ==================================================================

    /**
     * 按邮箱查用户（任意已注册用户，凭 users.mail 匹配）
     *
     * @param string $mail
     * @return array|null
     */
    public static function lookupUser($mail)
    {
        $mail = trim((string) $mail);
        if ($mail === '') return null;

        $db = self::db();
        if (!$db) return null;

        // 两段查询，兼顾各数据库的大小写行为：
        //   MySQL 默认排序规则不区分大小写 → 第一步直接命中（且能用上 mail 的索引）；
        //   SQLite / PgSQL 的比较区分大小写 → 用 LOWER() 兜底，
        //   否则站长把邮箱存成 Alice@Example.com 时就找不回来了。
        $tries = array(
            array('mail = ?', $mail),
            array('LOWER(mail) = ?', self::normMail($mail)),
        );

        foreach ($tries as $try) {
            try {
                $row = $db->fetchRow(
                    $db->select('uid', 'name', 'screenName', 'mail', 'group')
                        ->from('table.users')
                        ->where($try[0], $try[1])
                        ->limit(1)
                );
            } catch (Exception $e) {
                $row = false;
            } catch (Throwable $e) {
                $row = false;
            }

            if ($row && !empty($row['uid'])) return $row;
        }

        return null;
    }

    /**
     * 创建一个新的找回请求（同邮箱只保留最新一份）
     *
     * @param string $mail 邮箱
     * @param int    $uid  用户 id
     * @return array|null  成功返回记录（含明文 token，仅本次可拿到）
     */
    public static function create($mail, $uid)
    {
        self::ensureStorage();
        self::purgeExpired();

        // 同邮箱的旧请求：正常情况作废重建，避免出现多份可用凭证。
        // 但如果旧请求刚发过验证码、还在重发冷却窗口内，就直接复用旧记录 ——
        // 否则攻击者只要反复提交邮箱就能把冷却重置掉，形成邮件轰炸。
        $old = self::findByMail($mail);
        if (is_array($old) && !empty($old['rid'])) {
            if ((int) $old['mailSentAt'] > 0 && self::resendWait($old) > 0) {
                return $old;
            }
            self::destroy($old['rid']);
        }

        $rid = bin2hex(self::randomBytes(16));
        $now = time();

        $rec = array(
            'rid'        => $rid,
            'mail'       => self::normMail($mail),
            'uid'        => (int) $uid,
            'method'     => '',
            'token'      => self::deriveToken($mail, $rid, $now),
            'codeHash'   => '',
            'attempts'   => 0,
            'createdAt'  => $now,
            'expiresAt'  => $now + self::TTL,
            'mailSentAt' => 0,
            'grantHash'  => '',
            'grantUntil' => 0,
        );

        if (!self::save($rec)) return null;
        return $rec;
    }

    /**
     * 生成 6 位数字验证码并写入记录（不落明文，只存 sha1）
     *
     * @param array $rec
     * @return string|null 明文验证码
     */
    public static function issueMailCode(array &$rec)
    {
        $num = self::randomInt(100000, 999999);
        $code = (string) $num;

        $rec['method']     = 'mail';
        $rec['codeHash']   = sha1($code);
        $rec['attempts']   = 0;
        $rec['mailSentAt'] = time();

        if (!self::save($rec)) return null;
        return $code;
    }

    /**
     * 是否处于「重新发送」冷却中
     *
     * @param array $rec
     * @return int 剩余秒数（0 表示可以再发）
     */
    public static function resendWait(array $rec)
    {
        $sent = isset($rec['mailSentAt']) ? (int) $rec['mailSentAt'] : 0;
        if ($sent <= 0) return 0;
        $left = self::MAIL_RESEND_COOLDOWN - (time() - $sent);
        return $left > 0 ? $left : 0;
    }

    /**
     * 校验邮件验证码
     *
     * @param array  $rec
     * @param string $code
     * @return array [bool $ok, string $error]
     */
    public static function verifyMailCode(array &$rec, $code)
    {
        $state = self::checkAlive($rec);
        if (!$state[0]) return $state;

        $code = preg_replace('/\D/', '', (string) $code);
        if (strlen($code) !== 6) {
            return array(false, '请输入 6 位数字验证码');
        }

        if (empty($rec['codeHash'])) {
            return array(false, '请先点击「发送验证邮件」再填写验证码');
        }

        if (!hash_equals((string) $rec['codeHash'], sha1($code))) {
            return self::failAttempt($rec, '验证码不正确', '验证码错误次数过多，本次找回已失效，请重新开始');
        }

        // 若请求被换过方式，确保当前 method 与校验路径一致
        $rec['method'] = 'mail';
        self::save($rec);
        return array(true, '');
    }

    /**
     * 方式二的「本地文件」路径候选（容许带 .txt 后缀，方便 Windows 站长操作）
     *
     * @param string $token
     * @return array
     */
    public static function candidateFiles($token)
    {
        $dir = rtrim(__TYPECHO_ROOT_DIR__, '/\\') . self::FILE_DIR;
        return array(
            $dir . $token,
            $dir . $token . '.txt',
        );
    }

    /**
     * 校验本地文件
     *
     * @param array $rec
     * @return array [bool $ok, string $error, string $foundFile]
     */
    public static function verifyLocalFile(array &$rec)
    {
        $state = self::checkAlive($rec);
        if (!$state[0]) return array(false, $state[1], '');

        $token = isset($rec['token']) ? (string) $rec['token'] : '';
        if ($token === '') {
            return array(false, '请求状态异常，请重新开始', '');
        }

        $rec['method'] = 'file';

        $found = '';
        $content = null;
        foreach (self::candidateFiles($token) as $file) {
            if (@is_file($file)) {
                $found = $file;
                $content = @file_get_contents($file);
                break;
            }
        }

        if ($found === '') {
            return self::failAttempt($rec, '未找到验证文件。请在站点目录 usr/ 下新建文件，文件名与文件内容都填：' . $token);
        }

        if (trim((string) $content) !== $token) {
            return self::failAttempt($rec, '验证文件内容不匹配（内容必须是：' . $token . '）');
        }

        // 校验通过：顺手删掉验证文件，避免长期留一个可被利用的凭证
        @unlink($found);

        self::save($rec);
        return array(true, '', $found);
    }

    /**
     * 验证通过 → 签发一次性 grant
     *
     * @param array $rec
     * @return string|null 明文 grant
     */
    public static function issueGrant(array &$rec)
    {
        $grant = bin2hex(self::randomBytes(24));
        $rec['grantHash']  = sha1($grant);
        $rec['grantUntil'] = time() + self::GRANT_TTL;
        if (!self::save($rec)) return null;
        return $grant;
    }

    /**
     * 校验 grant（重置密码页提交时使用）
     *
     * @param string $rid
     * @param string $grant
     * @return array|null 记录；失败返回 null
     */
    public static function checkGrant($rid, $grant)
    {
        $rec = self::find($rid);
        if (!$rec) return null;

        if (empty($rec['grantHash']) || (int) $rec['grantUntil'] < time()) return null;
        if (!hash_equals((string) $rec['grantHash'], sha1((string) $grant))) return null;

        return $rec;
    }

    /**
     * 重设密码
     *
     * @param int    $uid
     * @param string $password 明文新密码
     * @return array [bool $ok, string $error]
     */
    public static function resetPassword($uid, $password)
    {
        $uid = (int) $uid;
        if ($uid <= 0) return array(false, '账号信息已失效，请重新开始');

        $password = (string) $password;
        if (strlen($password) < 6) return array(false, '密码至少 6 位');
        if (strlen($password) > 64) return array(false, '密码过长（最多 64 个字符）');

        $db = self::db();
        if (!$db) return array(false, '数据库不可用，请联系站点管理员');

        try {
            // 用 Typecho 自己的 hash（$T$ + 9 位随机盐 + md5），保证新密码能被正常登录校验
            $hash = \Typecho\Common::hash($password);

            if (!is_string($hash) || $hash === '') {
                return array(false, '密码加密失败，请联系站点管理员');
            }

            $db->query($db->update('table.users')
                ->rows(array(
                    'password' => $hash,
                    // 顺手换掉 authCode：所有旧的「记住我」cookie / 会话立即失效
                    'authCode' => \Typecho\Common::randString(20),
                ))
                ->where('uid = ?', $uid));

            return array(true, '');
        } catch (Exception $e) {
            return array(false, '写入新密码失败，请联系站点管理员');
        } catch (Throwable $e) {
            return array(false, '写入新密码失败，请联系站点管理员');
        }
    }

    // ==================================================================
    // 工具
    // ==================================================================

    /**
     * 记录一次验证失败；达到上限则直接作废本次找回
     *
     * @param array  $rec
     * @param string $msg      未达上限时的提示（会补上剩余次数）
     * @param string $finalMsg 达到上限时的提示
     * @return array [bool $ok, string $error]
     */
    private static function failAttempt(array &$rec, $msg, $finalMsg = '')
    {
        $rec['attempts'] = (int) $rec['attempts'] + 1;
        $left = self::MAX_ATTEMPTS - (int) $rec['attempts'];

        if ($left <= 0) {
            self::destroy($rec['rid']);
            if ($finalMsg === '') {
                $finalMsg = '验证失败次数过多，本次找回已失效，请重新开始';
            }
            return array(false, $finalMsg);
        }

        self::save($rec);
        return array(false, $msg . '，还可以尝试 ' . $left . ' 次');
    }

    /**
     * 记录是否仍然有效
     *
     * @param array $rec
     * @return array [bool $ok, string $error]
     */
    private static function checkAlive(array $rec)
    {
        if (empty($rec['rid'])) return array(false, '请求已失效，请重新开始');
        if ((int) $rec['expiresAt'] < time()) {
            self::destroy($rec['rid']);
            return array(false, '验证已超过 1 小时，请重新开始');
        }
        if ((int) $rec['attempts'] >= self::MAX_ATTEMPTS) {
            self::destroy($rec['rid']);
            return array(false, '验证失败次数过多，请重新开始');
        }
        return array(true, '');
    }

    /**
     * 方式二的验证串：md5(邮箱 + rid + 签发时间)，含随机 rid 所以不可预测
     *
     * @param string $mail
     * @param string $rid
     * @param int    $ts
     * @return string 32 位 md5
     */
    public static function deriveToken($mail, $rid, $ts)
    {
        return md5(self::normMail($mail) . '|' . $rid . '|' . (int) $ts);
    }

    public static function normMail($mail)
    {
        return strtolower(trim((string) $mail));
    }

    private static function cleanRid($rid)
    {
        return preg_replace('/[^a-f0-9]/', '', strtolower((string) $rid));
    }

    /** 兼容老版本 PHP 的随机数 */
    private static function randomBytes($len)
    {
        if (function_exists('random_bytes')) {
            return random_bytes($len);
        }
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= chr(mt_rand(0, 255));
        }
        return $out;
    }

    private static function randomInt($min, $max)
    {
        if (function_exists('random_int')) {
            try {
                return random_int($min, $max);
            } catch (Exception $e) {
                // 落到 mt_rand
            }
        }
        return mt_rand($min, $max);
    }
}
