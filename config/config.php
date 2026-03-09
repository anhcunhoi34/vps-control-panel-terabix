<?php
/**
 * Cấu hình hệ thống VPS Control Panel
 */

if (!defined('APP_ROOT')) {
    die('Direct access not permitted');
}

define('APP_NAME', 'VPS Control Panel');
define('APP_VERSION', '1.1.0');

// API Configuration
define('API_BASE_URL', 'https://cloud.terabix.net/api');
define('API_TIMEOUT', 30);
define('API_SSL_VERIFY', true);

// Debug - TẮT trên production
define('APP_DEBUG', false);

// Security
define('SESSION_LIFETIME', 3600);
define('CSRF_TOKEN_LIFETIME', 3600); // Tăng lên 1 giờ để tránh hết hạn khi dùng AJAX
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

// ĐỔI NGAY chuỗi này sau khi cài đặt!
define('ENCRYPTION_KEY', 'CHANGE-THIS-TO-YOUR-OWN-RANDOM-64-CHARACTER-STRING-RIGHT-NOW-!!!!');

// Rate limiting
define('INTERNAL_RATE_LIMIT', 120); // Tăng lên cho AJAX calls
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
