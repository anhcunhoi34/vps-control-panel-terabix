<?php
/**
 * Cấu hình hệ thống VPS Control Panel
 */

if (!defined('APP_ROOT')) {
    die('Direct access not permitted');
}

// Thông tin ứng dụng
define('APP_NAME', 'VPS Control Panel');
define('APP_VERSION', '1.0.0');

// API Configuration
define('API_BASE_URL', 'https://cloud.terabix.net/api');
define('API_TIMEOUT', 30);

// SSL Verify - đặt false nếu hosting không có CA bundle
// Sau khi fix xong nên đặt lại true
define('API_SSL_VERIFY', true);

// Debug mode - BẬT đặt true để xem lỗi chi tiết, TẮT trên production
define('APP_DEBUG', true);

// Security
define('SESSION_LIFETIME', 3600);
define('CSRF_TOKEN_LIFETIME', 1800);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

// ĐỔI NGAY chuỗi này thành random string riêng của bạn!
define('ENCRYPTION_KEY', '1234567890djfherueydhdksjdyheusjasisjdydhfgetrhsdfghuehdgsyteryudhg');

// Rate limiting nội bộ
define('INTERNAL_RATE_LIMIT', 60);
define('RATE_LIMIT_WINDOW', 60);

// Timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Error reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    $logDir = APP_ROOT . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    ini_set('error_log', $logDir . '/error.log');
}