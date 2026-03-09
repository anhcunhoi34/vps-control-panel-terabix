<?php
/**
 * Lớp bảo mật - CSRF, XSS, mã hóa, rate limiting
 */

if (!defined('APP_ROOT')) {
    die('Direct access not permitted');
}

class Security
{
    /**
     * Khởi tạo session bảo mật
     */
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $cookieParams = [
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isSecure,
                'httponly'  => true,
                'samesite' => 'Strict',
            ];
            session_set_cookie_params($cookieParams);
            session_name('vps_ctrl_sess');
            session_start();

            // Regenerate session ID định kỳ
            if (!isset($_SESSION['_created'])) {
                $_SESSION['_created'] = time();
            } elseif (time() - $_SESSION['_created'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['_created'] = time();
            }

            // Kiểm tra session timeout
            if (isset($_SESSION['_last_activity']) &&
                (time() - $_SESSION['_last_activity'] > SESSION_LIFETIME)) {
                self::destroySession();
                return;
            }
            $_SESSION['_last_activity'] = time();

            // Bind session to user agent
            $fingerprint = self::getSessionFingerprint();
            if (!isset($_SESSION['_fingerprint'])) {
                $_SESSION['_fingerprint'] = $fingerprint;
            } elseif ($_SESSION['_fingerprint'] !== $fingerprint) {
                self::destroySession();
                return;
            }
        }
    }

    private static function getSessionFingerprint(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return hash('sha256', $ua . ENCRYPTION_KEY);
    }

    public static function destroySession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Tạo CSRF token - Dùng chung 1 token cho cả session
     * Chỉ tạo mới khi chưa có hoặc hết hạn
     */
    public static function generateCsrfToken(): string
    {
        // Nếu đã có token và chưa hết hạn thì dùng lại
        if (!empty($_SESSION['csrf_token']) && !empty($_SESSION['csrf_token_time'])) {
            if (time() - $_SESSION['csrf_token_time'] < CSRF_TOKEN_LIFETIME) {
                return $_SESSION['csrf_token'];
            }
        }

        // Tạo token mới
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        return $token;
    }

    /**
     * Xác thực CSRF token - KHÔNG xóa token sau khi validate
     * Để cho phép nhiều AJAX requests dùng cùng token
     */
    public static function validateCsrfToken(?string $token): bool
    {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        // Kiểm tra hết hạn
        if (isset($_SESSION['csrf_token_time']) &&
            (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME)) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Refresh CSRF token - gia hạn thời gian
     */
    public static function refreshCsrfToken(): string
    {
        if (!empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token_time'] = time();
            return $_SESSION['csrf_token'];
        }
        return self::generateCsrfToken();
    }

    public static function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function escapeArray(array $data): array
    {
        $cleaned = [];
        foreach ($data as $key => $value) {
            $cleanKey = self::escape((string)$key);
            if (is_array($value)) {
                $cleaned[$cleanKey] = self::escapeArray($value);
            } elseif (is_string($value)) {
                $cleaned[$cleanKey] = self::escape($value);
            } else {
                $cleaned[$cleanKey] = $value;
            }
        }
        return $cleaned;
    }

    public static function sanitizeInput(string $input): string
    {
        $input = trim($input);
        $input = stripslashes($input);
        return self::escape($input);
    }

    public static function encrypt(string $data): string
    {
        $key = hash('sha256', ENCRYPTION_KEY, true);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    public static function decrypt(string $data): string|false
    {
        $key = hash('sha256', ENCRYPTION_KEY, true);
        $decoded = base64_decode($data);
        if ($decoded === false) return false;
        $parts = explode('::', $decoded, 2);
        if (count($parts) !== 2) return false;
        [$iv, $encrypted] = $parts;
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    public static function checkRateLimit(string $identifier = ''): bool
    {
        $key = 'rate_' . ($identifier ?: self::getClientIp());

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'start' => time()];
        }

        $rateData = &$_SESSION[$key];

        if (time() - $rateData['start'] > RATE_LIMIT_WINDOW) {
            $rateData = ['count' => 0, 'start' => time()];
        }

        $rateData['count']++;
        return $rateData['count'] <= INTERNAL_RATE_LIMIT;
    }

    public static function getClientIp(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    public static function isLoginLocked(): bool
    {
        $key = 'login_attempts_' . md5(self::getClientIp());
        if (!isset($_SESSION[$key])) return false;
        $a = $_SESSION[$key];
        if ($a['count'] >= MAX_LOGIN_ATTEMPTS) {
            if (time() - $a['last'] < LOGIN_LOCKOUT_TIME) return true;
            unset($_SESSION[$key]);
        }
        return false;
    }

    public static function recordFailedLogin(): void
    {
        $key = 'login_attempts_' . md5(self::getClientIp());
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'last' => time()];
        }
        $_SESSION[$key]['count']++;
        $_SESSION[$key]['last'] = time();
    }

    public static function resetLoginAttempts(): void
    {
        $key = 'login_attempts_' . md5(self::getClientIp());
        unset($_SESSION[$key]);
    }

    public static function isValidServerId(string $id): bool
    {
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
    }

    public static function isValidIntId($id): bool
    {
        return is_numeric($id) && (int)$id > 0;
    }
}
