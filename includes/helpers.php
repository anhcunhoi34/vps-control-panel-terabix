<?php
/**
 * Helper functions
 */

if (!defined('APP_ROOT')) {
    die('Direct access not permitted');
}

/**
 * Escape output
 */
function e(string $str): string
{
    return Security::escape($str);
}

/**
 * Format bytes
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Format datetime
 */
function formatDate(string $date, string $format = 'd/m/Y H:i'): string
{
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Tạo CSRF hidden field
 */
function csrfField(): string
{
    $token = Security::generateCsrfToken();
    return '<input type="hidden" name="_csrf_token" value="' . e($token) . '">';
}

/**
 * Hiển thị flash message
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Status badge HTML
 */
function statusBadge(bool $running): string
{
    if ($running) {
        return '<span class="badge badge-success">Running</span>';
    }
    return '<span class="badge badge-danger">Stopped</span>';
}

/**
 * Boolean badge
 */
function boolBadge(bool $value, string $trueText = 'Yes', string $falseText = 'No'): string
{
    if ($value) {
        return '<span class="badge badge-warning">' . e($trueText) . '</span>';
    }
    return '<span class="badge badge-secondary">' . e($falseText) . '</span>';
}

/**
 * JSON response helper cho AJAX
 */
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Lấy current page
 */
function currentPage(): string
{
    return $_GET['route'] ?? $_GET['page'] ?? 'dashboard';
}

/**
 * Check active menu
 */
function isActive(string $page): string
{
    return currentPage() === $page ? 'active' : '';
}