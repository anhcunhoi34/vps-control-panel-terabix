<?php
/**
 * Lớp bảo mật - Xử lý CSRF, XSS, mã hóa, rate limiting
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
            $cookieParams = [
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly'  => true,
                'samesite'  => 'Strict',
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

            // Bind session to user agent và IP
            $fingerprint = self::getSessionFingerprint();
            if (!isset($_SESSION['_fingerprint'])) {
                $_SESSION['_fingerprint'] = $fingerprint;
            } elseif ($_SESSION['_fingerprint'] !== $fingerprint) {
                self::destroySession();
                return;
            }
        }
    }

    /**
     * Tạo fingerprint session
     */
    private static function getSessionFingerprint(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return hash('sha256', $ua . ENCRYPTION_KEY);
    }

    /**
     * Hủy session an toàn
     */
    public static function destroySession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Tạo CSRF token
     */
    public static function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        return $token;
    }

    /**
     * Xác thực CSRF token
     */
    public static function validateCsrfToken(?string $token): bool
    {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        // Kiểm tra thời gian hết hạn
        if (isset($_SESSION['csrf_token_time']) && 
            (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME)) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Lọc XSS cho output
     */
    public static function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Lọc đệ quy cho mảng
     */
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

    /**
     * Sanitize input
     */
    public static function sanitizeInput(string $input): string
    {
        $input = trim($input);
        $input = stripslashes($input);
        return self::escape($input);
    }

    /**
     * Mã hóa dữ liệu nhạy cảm
     */
    public static function encrypt(string $data): string
    {
        $key = hash('sha256', ENCRYPTION_KEY, true);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Giải mã dữ liệu
     */
    public static function decrypt(string $data): string|false
    {
        $key = hash('sha256', ENCRYPTION_KEY, true);
        $parts = explode('::', base64_decode($data), 2);
        if (count($parts) !== 2) {
            return false;
        }
        [$iv, $encrypted] = $parts;
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Rate limiting nội bộ
     */
    public static function checkRateLimit(string $identifier = ''): bool
    {
        $key = 'rate_' . ($identifier ?: self::getClientIp());
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'start' => time()];
        }

        $rateData = &$_SESSION[$key];

        // Reset nếu hết window
        if (time() - $rateData['start'] > RATE_LIMIT_WINDOW) {
            $rateData = ['count' => 0, 'start' => time()];
        }

        $rateData['count']++;

        return $rateData['count'] <= INTERNAL_RATE_LIMIT;
    }

    /**
     * Lấy IP client thực
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * Kiểm tra login lockout
     */
    public static function isLoginLocked(): bool
    {
        $ip = self::getClientIp();
        $key = 'login_attempts_' . md5($ip);

        if (!isset($_SESSION[$key])) {
            return false;
        }

        $attempts = $_SESSION[$key];
        if ($attempts['count'] >= MAX_LOGIN_ATTEMPTS) {
            if (time() - $attempts['last'] < LOGIN_LOCKOUT_TIME) {
                return true;
            }
            // Reset sau lockout time
            unset($_SESSION[$key]);
        }

        return false;
    }

    /**
     * Ghi nhận login thất bại
     */
    public static function recordFailedLogin(): void
    {
        $ip = self::getClientIp();
        $key = 'login_attempts_' . md5($ip);

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'last' => time()];
        }

        $_SESSION[$key]['count']++;
        $_SESSION[$key]['last'] = time();
    }

    /**
     * Reset login attempts
     */
    public static function resetLoginAttempts(): void
    {
        $ip = self::getClientIp();
        $key = 'login_attempts_' . md5($ip);
        unset($_SESSION[$key]);
    }

    /**
     * Validate server ID format (UUID)
     */
    public static function isValidServerId(string $id): bool
    {
        return (bool)preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $id
        );
    }

    /**
     * Validate integer ID
     */
    public static function isValidIntId($id): bool
    {
        return is_numeric($id) && (int)$id > 0;
    }
}