<?php
/**
 * Xác thực người dùng
 */

if (!defined('APP_ROOT')) {
    die('Direct access not permitted');
}

class Auth
{
    /**
     * Kiểm tra đã đăng nhập
     */
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['api_token']) && !empty($_SESSION['logged_in']);
    }

    /**
     * Đăng nhập bằng API token
     */
    public static function login(string $token): array
    {
        // Sanitize token - chỉ trim, không thay đổi nội dung
        $token = trim($token);
        
        if (empty($token)) {
            return ['success' => false, 'error' => 'Token is required'];
        }

        // Token Terabix có thể rất dài (base64 encoded), nới rộng giới hạn
        if (strlen($token) < 10 || strlen($token) > 2000) {
            return ['success' => false, 'error' => 'Invalid token format (length: ' . strlen($token) . ')'];
        }

        // Thử kết nối API trước bằng /connect
        $api = new Api($token);
        $result = $api->verifyConnection();

        // Nếu /connect thất bại, thử /account như backup
        if (!$result['success']) {
            // Có thể /connect trả HTTP code khác 200 trên API này
            // Thử endpoint /account để verify
            $accountResult = $api->getAccount();
            
            if ($accountResult['success'] && isset($accountResult['data']['data'])) {
                // Token hợp lệ! Dùng kết quả account luôn
                session_regenerate_id(true);
                $_SESSION['api_token'] = Security::encrypt($token);
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['_created'] = time();
                $_SESSION['account_name'] = $accountResult['data']['data']['name'] ?? 'User';
                $_SESSION['account_email'] = $accountResult['data']['data']['email'] ?? '';

                Security::resetLoginAttempts();
                return ['success' => true];
            }

            // Cả 2 đều thất bại
            Security::recordFailedLogin();
            
            $debugInfo = '';
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $debugInfo = ' | Connect: HTTP ' . ($result['http_code'] ?? '0') . 
                            ' | Account: HTTP ' . ($accountResult['http_code'] ?? '0');
                if ($result['error']) $debugInfo .= ' | ' . $result['error'];
                if ($accountResult['error']) $debugInfo .= ' | ' . $accountResult['error'];
            }

            if (($result['http_code'] ?? 0) === 401 || ($accountResult['http_code'] ?? 0) === 401) {
                return ['success' => false, 'error' => 'Invalid API token' . $debugInfo];
            }

            return [
                'success' => false,
                'error' => 'Cannot connect to API' . $debugInfo,
            ];
        }

        // /connect thành công
        session_regenerate_id(true);
        $_SESSION['api_token'] = Security::encrypt($token);
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['_created'] = time();

        // Lấy thông tin account
        $account = $api->getAccount();
        if ($account['success'] && isset($account['data']['data'])) {
            $_SESSION['account_name'] = $account['data']['data']['name'] ?? 'User';
            $_SESSION['account_email'] = $account['data']['data']['email'] ?? '';
        }

        Security::resetLoginAttempts();
        return ['success' => true];
    }

    /**
     * Lấy API token đã giải mã
     */
    public static function getToken(): string|false
    {
        if (empty($_SESSION['api_token'])) {
            return false;
        }
        $decrypted = Security::decrypt($_SESSION['api_token']);
        if ($decrypted === false || $decrypted === '') {
            return false;
        }
        return $decrypted;
    }

    /**
     * Lấy instance API
     */
    public static function getApi(): Api|false
    {
        $token = self::getToken();
        if (!$token) {
            return false;
        }
        return new Api($token);
    }

    /**
     * Đăng xuất
     */
    public static function logout(): void
    {
        Security::destroySession();
    }

    /**
     * Yêu cầu đăng nhập - redirect nếu chưa
     */
    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }

        // Double check: token còn giải mã được không
        $token = self::getToken();
        if (!$token) {
            self::logout();
            header('Location: login.php');
            exit;
        }
    }
}