<?php
/**
 * DEBUG TOOL - Test API connection trực tiếp
 * ⚠️ XÓA FILE NÀY SAU KHI DEBUG XONG!
 */

define('APP_ROOT', __DIR__);

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Api.php';

Security::initSession();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST only']);
    exit;
}

$token = trim($_POST['test_token'] ?? '');

if (empty($token)) {
    echo json_encode(['error' => 'No token provided']);
    exit;
}

$results = [];

// Info
$results['token_info'] = [
    'length'      => strlen($token),
    'first_10'    => substr($token, 0, 10) . '...',
    'last_10'     => '...' . substr($token, -10),
    'has_newlines'=> strpos($token, "\n") !== false || strpos($token, "\r") !== false,
    'has_spaces'  => strpos($token, ' ') !== false,
];

// Test PHP curl
$results['curl_available'] = function_exists('curl_init');
$results['curl_version'] = function_exists('curl_version') ? curl_version()['version'] : 'N/A';
$results['openssl'] = extension_loaded('openssl') ? phpversion('openssl') : 'NOT LOADED';

// Test 1: /connect
$api = new Api($token);
$connectResult = $api->verifyConnection();
$results['test_connect'] = [
    'success'   => $connectResult['success'],
    'http_code' => $connectResult['http_code'],
    'error'     => $connectResult['error'] ?? null,
    'data'      => $connectResult['data'],
    'raw_snippet' => isset($connectResult['raw']) ? substr($connectResult['raw'], 0, 500) : null,
];

// Test 2: /account
$accountResult = $api->getAccount();
$results['test_account'] = [
    'success'   => $accountResult['success'],
    'http_code' => $accountResult['http_code'],
    'error'     => $accountResult['error'] ?? null,
    'data'      => $accountResult['data'],
    'raw_snippet' => isset($accountResult['raw']) ? substr($accountResult['raw'], 0, 500) : null,
];

// Test 3: manual curl test
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://cloud.terabix.net/api/connect',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ],
    CURLOPT_VERBOSE        => false,
]);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
$error = curl_error($ch);
$errno = curl_errno($ch);
curl_close($ch);

$results['manual_curl_test'] = [
    'response'        => $response,
    'http_code'       => $info['http_code'] ?? 0,
    'total_time'      => $info['total_time'] ?? 0,
    'connect_time'    => $info['connect_time'] ?? 0,
    'ssl_verify'      => $info['ssl_verify_result'] ?? 'N/A',
    'redirect_count'  => $info['redirect_count'] ?? 0,
    'effective_url'   => $info['url'] ?? '',
    'curl_error'      => $error,
    'curl_errno'      => $errno,
    'content_type'    => $info['content_type'] ?? '',
];

// Test 4: manual curl to /account
$ch2 = curl_init();
curl_setopt_array($ch2, [
    CURLOPT_URL            => 'https://cloud.terabix.net/api/account',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ],
]);

$response2 = curl_exec($ch2);
$info2 = curl_getinfo($ch2);
$error2 = curl_error($ch2);
curl_close($ch2);

$results['manual_curl_account'] = [
    'response_snippet' => substr($response2, 0, 500),
    'http_code'        => $info2['http_code'] ?? 0,
    'curl_error'       => $error2,
];

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);