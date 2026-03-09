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

// Auth check
if (!Auth::isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
}

// Rate limit
if (!Security::checkRateLimit()) {
    jsonResponse(['success' => false, 'error' => 'Too many requests'], 429);
}

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Parse body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    jsonResponse(['success' => false, 'error' => 'Invalid request body'], 400);
}

// Validate CSRF - dùng cùng 1 token, không thay đổi
$csrf = $input['csrf_token'] ?? '';
if (!Security::validateCsrfToken($csrf)) {
    $newToken = Security::generateCsrfToken();
    jsonResponse([
        'success'   => false,
        'error'     => 'Session expired. Please refresh the page.',
        'csrf_token' => $newToken,
    ], 403);
}

// Refresh CSRF token lifetime
$currentToken = Security::refreshCsrfToken();

$api = Auth::getApi();
if (!$api) {
    jsonResponse(['success' => false, 'error' => 'API connection failed', 'csrf_token' => $currentToken], 500);
}

$action = $input['action'] ?? '';
$serverId = $input['server_id'] ?? '';

// Server actions cần validate ID
$serverActions = [
    'boot', 'restart', 'shutdown', 'powerOff',
    'resetPassword', 'rescue', 'vnc', 'vncDetails',
    'changeName', 'updateSettings', 'setBootOrder',
    'build', 'getTasks', 'getTask',
    'mountISO', 'getISOs', 'getOSTemplates',
    'getServerState',
];

if (in_array($action, $serverActions, true)) {
    if (!Security::isValidServerId($serverId)) {
        jsonResponse(['success' => false, 'error' => 'Invalid server ID', 'csrf_token' => $currentToken], 400);
    }
}

// Helper function cho response
function apiResponse(array $result, string $token): void
{
    jsonResponse([
        'success'    => $result['success'],
        'data'       => $result['data'],
        'error'      => $result['error'],
        'csrf_token' => $token,
    ], $result['success'] ? 200 : ($result['http_code'] ?: 500));
}

// Route actions
switch ($action) {
    // ===== POWER =====
    case 'boot':
        apiResponse($api->boot($serverId), $currentToken);
        break;

    case 'restart':
        apiResponse($api->restart($serverId), $currentToken);
        break;

    case 'shutdown':
        apiResponse($api->shutdown($serverId), $currentToken);
        break;

    case 'powerOff':
        apiResponse($api->powerOff($serverId), $currentToken);
        break;

    // ===== SERVER MANAGEMENT =====
    case 'resetPassword':
        apiResponse($api->resetPassword($serverId), $currentToken);
        break;

    case 'changeName':
        $name = trim($input['name'] ?? '');
        if (empty($name) || strlen($name) > 100) {
            jsonResponse(['success' => false, 'error' => 'Invalid name (1-100 chars)', 'csrf_token' => $currentToken], 400);
        }
        apiResponse($api->changeName($serverId, $name), $currentToken);
        break;

    case 'rescue':
        apiResponse($api->toggleRescue($serverId), $currentToken);
        break;

    case 'vnc':
        apiResponse($api->toggleVNC($serverId), $currentToken);
        break;

    case 'vncDetails':
        apiResponse($api->getVNCSettings($serverId), $currentToken);
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
            jsonResponse(['success' => false, 'error' => 'No settings provided', 'csrf_token' => $currentToken], 400);
        }
        apiResponse($api->updateSettings($serverId, $settings), $currentToken);
        break;

    case 'setBootOrder':
        $order = $input['order'] ?? '';
        if (!in_array($order, ['hdd,cdrom', 'cdrom,hdd'], true)) {
            jsonResponse(['success' => false, 'error' => 'Invalid boot order', 'csrf_token' => $currentToken], 400);
        }
        apiResponse($api->setBootOrder($serverId, $order), $currentToken);
        break;

    // ===== SERVER STATE =====
    case 'getServerState':
        $result = $api->getServer($serverId, true);
        jsonResponse([
            'success'    => $result['success'],
            'data'       => $result['data'],
            'error'      => $result['error'],
            'csrf_token' => $currentToken,
        ]);
        break;

    // ===== BUILD =====
    case 'build':
        $buildData = [];
        $method = $input['method'] ?? '';
        if (!in_array($method, ['template', 'self'], true)) {
            jsonResponse(['success' => false, 'error' => 'Invalid build method', 'csrf_token' => $currentToken], 400);
        }
        $buildData['method'] = $method;

        if ($method === 'template') {
            $templateId = (int)($input['templateId'] ?? 0);
            if ($templateId <= 0) {
                jsonResponse(['success' => false, 'error' => 'Template ID required', 'csrf_token' => $currentToken], 400);
            }
            $buildData['templateId'] = $templateId;
        }

        if (!empty($input['hostname'])) $buildData['hostname'] = substr(trim($input['hostname']), 0, 255);
        if (!empty($input['name'])) $buildData['name'] = substr(trim($input['name']), 0, 100);
        if (!empty($input['timezone'])) $buildData['timezone'] = $input['timezone'];
        if (isset($input['swap'])) $buildData['swap'] = (int)$input['swap'];
        if (isset($input['ipv6'])) $buildData['ipv6'] = (bool)$input['ipv6'];
        if (!empty($input['sshKeys']) && is_array($input['sshKeys'])) {
            $buildData['sshKeys'] = array_map('intval', $input['sshKeys']);
        }
        if (!empty($input['userData'])) $buildData['userData'] = $input['userData'];

        apiResponse($api->buildServer($serverId, $buildData), $currentToken);
        break;

    // ===== TASKS =====
    case 'getTasks':
        apiResponse($api->getTasks($serverId), $currentToken);
        break;

    case 'getTask':
        $taskId = $input['task_id'] ?? '';
        if (empty($taskId)) {
            jsonResponse(['success' => false, 'error' => 'Task ID required', 'csrf_token' => $currentToken], 400);
        }
        apiResponse($api->getTask($serverId, $taskId), $currentToken);
        break;

    // ===== ISO =====
    case 'getISOs':
        apiResponse($api->getISOs($serverId), $currentToken);
        break;

    case 'mountISO':
        $iso = trim($input['iso'] ?? '');
        if (empty($iso)) {
            jsonResponse(['success' => false, 'error' => 'ISO identifier required', 'csrf_token' => $currentToken], 400);
        }
        apiResponse($api->mountISO($serverId, $iso), $currentToken);
        break;

    // ===== OS TEMPLATES =====
    case 'getOSTemplates':
        apiResponse($api->getOSTemplates($serverId), $currentToken);
        break;

    // ===== CONNECTION =====
    case 'testConnection':
        apiResponse($api->verifyConnection(), $currentToken);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action', 'csrf_token' => $currentToken], 400);
}
