<?php
/**
 * AJAX request handler
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Api.php';
require_once APP_ROOT . '/includes/Auth.php';
require_once APP_ROOT . '/includes/helpers.php';

Security::initSession();

// Kiểm tra auth
if (!Auth::isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
}

// Kiểm tra rate limit
if (!Security::checkRateLimit()) {
    jsonResponse(['success' => false, 'error' => 'Too many requests'], 429);
}

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    jsonResponse(['success' => false, 'error' => 'Invalid request body'], 400);
}

// Validate CSRF
$csrf = $input['csrf_token'] ?? '';
if (!Security::validateCsrfToken($csrf)) {
    // Tạo token mới để client có thể retry
    $newToken = Security::generateCsrfToken();
    jsonResponse([
        'success' => false, 
        'error' => 'Invalid CSRF token. Please refresh and try again.',
        'new_csrf' => $newToken,
    ], 403);
}

$api = Auth::getApi();
if (!$api) {
    jsonResponse(['success' => false, 'error' => 'API connection failed'], 500);
}

$action = $input['action'] ?? '';
$serverId = $input['server_id'] ?? '';

// Validate server ID cho server actions
$serverActions = [
    'boot', 'restart', 'shutdown', 'powerOff',
    'resetPassword', 'rescue', 'vnc', 'vncDetails',
    'changeName', 'updateSettings', 'setBootOrder',
    'build', 'getTasks', 'mountISO',
];

if (in_array($action, $serverActions, true)) {
    if (!Security::isValidServerId($serverId)) {
        jsonResponse(['success' => false, 'error' => 'Invalid server ID'], 400);
    }
}

// Tạo CSRF token mới cho request tiếp theo
$newCsrf = Security::generateCsrfToken();

// Route actions
switch ($action) {
    // ===== POWER =====
    case 'boot':
        $result = $api->boot($serverId);
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    case 'restart':
        $result = $api->restart($serverId);
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    case 'shutdown':
        $result = $api->shutdown($serverId);
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    case 'powerOff':
        $result = $api->powerOff($serverId);
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    // ===== SERVER MANAGEMENT =====
    case 'resetPassword':
        $result = $api->resetPassword($serverId);
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    case 'changeName':
        $name = trim($input['name'] ?? '');
        if (empty($name) || strlen($name) > 100) {
            jsonResponse(['success' => false, 'error' => 'Invalid name', 'csrf_token' => $newCsrf], 400);
        }
        $result = $api->changeName($serverId, $name);
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    case 'rescue':
        $result = $api->toggleRescue($serverId);
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    case 'vnc':
        $result = $api->toggleVNC($serverId);
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    case 'vncDetails':
        $result = $api->getVNCSettings($serverId);
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    case 'updateSettings':
        $settings = [];
        if (isset($input['bootType']) && in_array($input['bootType'], ['uefi', 'bios'], true)) {
            $settings['bootType'] = $input['bootType'];
        }
        if (isset($input['autoConfiguration'])) {
            $settings['autoConfiguration'] = (bool)$input['autoConfiguration'];
        }
        if (empty($settings)) {
            jsonResponse(['success' => false, 'error' => 'No settings provided', 'csrf_token' => $newCsrf], 400);
        }
        $result = $api->updateSettings($serverId, $settings);
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    case 'setBootOrder':
        $order = $input['order'] ?? '';
        if (!in_array($order, ['hdd,cdrom', 'cdrom,hdd'], true)) {
            jsonResponse(['success' => false, 'error' => 'Invalid boot order', 'csrf_token' => $newCsrf], 400);
        }
        $result = $api->setBootOrder($serverId, $order);
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    // ===== BUILD =====
    case 'build':
        $buildData = [];
        $method = $input['method'] ?? '';
        if (!in_array($method, ['template', 'self'], true)) {
            jsonResponse(['success' => false, 'error' => 'Invalid build method', 'csrf_token' => $newCsrf], 400);
        }
        $buildData['method'] = $method;

        if ($method === 'template') {
            $templateId = (int)($input['templateId'] ?? 0);
            if ($templateId <= 0) {
                jsonResponse(['success' => false, 'error' => 'Template ID required', 'csrf_token' => $newCsrf], 400);
            }
            $buildData['templateId'] = $templateId;
        }

        if (!empty($input['hostname'])) {
            $buildData['hostname'] = substr(trim($input['hostname']), 0, 255);
        }
        if (!empty($input['name'])) {
            $buildData['name'] = substr(trim($input['name']), 0, 100);
        }
        if (!empty($input['timezone'])) {
            $buildData['timezone'] = $input['timezone'];
        }
        if (isset($input['swap'])) {
            $buildData['swap'] = (int)$input['swap'];
        }
        if (isset($input['ipv6'])) {
            $buildData['ipv6'] = (bool)$input['ipv6'];
        }
        if (!empty($input['sshKeys']) && is_array($input['sshKeys'])) {
            $buildData['sshKeys'] = array_map('intval', $input['sshKeys']);
        }
        if (!empty($input['userData'])) {
            $buildData['userData'] = $input['userData'];
        }

        $result = $api->buildServer($serverId, $buildData);
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    // ===== TASKS =====
    case 'getTasks':
        $result = $api->getTasks($serverId);
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    // ===== ISO =====
    case 'mountISO':
        $iso = trim($input['iso'] ?? '');
        if (empty($iso)) {
            jsonResponse(['success' => false, 'error' => 'ISO ID or URL required', 'csrf_token' => $newCsrf], 400);
        }
        $result = $api->mountISO($serverId, $iso);
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    // ===== CONNECTION TEST =====
    case 'testConnection':
        $result = $api->verifyConnection();
        jsonResponse(['success' => $result['success'], 'data' => $result['data'], 
                      'error' => $result['error'], 'csrf_token' => $newCsrf]);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action', 'csrf_token' => $newCsrf], 400);
}