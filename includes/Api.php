<?php
/**
 * Lớp giao tiếp API Terabix
 */

if (!defined('APP_ROOT')) {
    die('Direct access not permitted');
}

class Api
{
    private string $baseUrl;
    private string $token;
    private int $timeout;
    private array $lastHeaders = [];
    private string $lastError = '';
    private int $lastHttpCode = 0;

    public function __construct(string $token)
    {
        $this->baseUrl = API_BASE_URL;
        $this->token = $token;
        $this->timeout = API_TIMEOUT;
    }

    /**
     * Thực hiện GET request
     */
    public function get(string $endpoint, array $params = []): array
    {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $this->request('GET', $url);
    }

    /**
     * Thực hiện POST request
     */
    public function post(string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        return $this->request('POST', $url, $data);
    }

    /**
     * Thực hiện PUT request
     */
    public function put(string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        return $this->request('PUT', $url, $data);
    }

    /**
     * Thực hiện DELETE request
     */
    public function delete(string $endpoint): array
    {
        $url = $this->baseUrl . $endpoint;
        return $this->request('DELETE', $url);
    }

    /**
     * Request chung
     */
    private function request(string $method, string $url, array $data = []): array
    {
        $ch = curl_init();

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
        ];

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HEADERFUNCTION => function ($ch, $header) {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $value = trim($parts[1]);
                    $this->lastHeaders[$name] = $value;
                }
                return $len;
            },
            CURLOPT_USERAGENT      => 'VPSControlPanel/' . APP_VERSION,
        ];

        // SSL Configuration - thử verify trước, fallback nếu cần
        if (defined('API_SSL_VERIFY') && API_SSL_VERIFY === false) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        } else {
            $options[CURLOPT_SSL_VERIFYPEER] = true;
            $options[CURLOPT_SSL_VERIFYHOST] = 2;
            
            // Thử tìm CA bundle
            $caBundlePaths = [
                '/etc/ssl/certs/ca-certificates.crt',
                '/etc/pki/tls/certs/ca-bundle.crt',
                '/usr/share/ssl/certs/ca-bundle.crt',
                '/etc/ssl/ca-bundle.pem',
                '/etc/pki/tls/cacert.pem',
            ];
            foreach ($caBundlePaths as $path) {
                if (file_exists($path)) {
                    $options[CURLOPT_CAINFO] = $path;
                    break;
                }
            }
        }

        switch ($method) {
            case 'POST':
                $options[CURLOPT_POST] = true;
                if (!empty($data)) {
                    $headers[] = 'Content-Type: application/json';
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
            case 'PUT':
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                $headers[] = 'Content-Type: application/json';
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
                break;
            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
        }

        $options[CURLOPT_HTTPHEADER] = $headers;
        $this->lastHeaders = [];

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        $this->lastHttpCode = $httpCode;
        $this->lastError = $curlError;

        // Nếu SSL verify thất bại, thử lại không verify
        if ($curlErrno === 60 || $curlErrno === 77 || $curlErrno === 35) {
            return $this->requestWithoutSSLVerify($method, $url, $data, $headers);
        }

        if ($curlError) {
            return [
                'success'   => false,
                'http_code' => 0,
                'error'     => 'Connection error (errno ' . $curlErrno . '): ' . $curlError,
                'data'      => null,
                'debug'     => [
                    'url'       => $url,
                    'method'    => $method,
                    'curl_errno'=> $curlErrno,
                ],
            ];
        }

        $decoded = json_decode($response, true);

        // HTTP 200-299 = success
        $success = $httpCode >= 200 && $httpCode < 300;

        return [
            'success'    => $success,
            'http_code'  => $httpCode,
            'data'       => $decoded,
            'raw'        => $response,
            'error'      => !$success ? $this->getErrorMessage($httpCode, $decoded) : null,
            'rate_limit' => [
                'limit'     => $this->lastHeaders['x-ratelimit-limit'] ?? null,
                'remaining' => $this->lastHeaders['x-ratelimit-remaining'] ?? null,
            ],
        ];
    }

    /**
     * Retry request mà không verify SSL (cho shared hosting thiếu CA bundle)
     */
    private function requestWithoutSSLVerify(string $method, string $url, array $data, array $headers): array
    {
        $ch = curl_init();

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'VPSControlPanel/' . APP_VERSION,
            CURLOPT_HEADERFUNCTION => function ($ch, $header) {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $value = trim($parts[1]);
                    $this->lastHeaders[$name] = $value;
                }
                return $len;
            },
        ];

        switch ($method) {
            case 'POST':
                $options[CURLOPT_POST] = true;
                if (!empty($data)) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
            case 'PUT':
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
                break;
            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlError) {
            return [
                'success'   => false,
                'http_code' => 0,
                'error'     => 'Connection error (SSL bypass): ' . $curlError,
                'data'      => null,
            ];
        }

        $decoded = json_decode($response, true);
        $success = $httpCode >= 200 && $httpCode < 300;

        return [
            'success'    => $success,
            'http_code'  => $httpCode,
            'data'       => $decoded,
            'raw'        => $response,
            'error'      => !$success ? $this->getErrorMessage($httpCode, $decoded) : null,
            'rate_limit' => [
                'limit'     => $this->lastHeaders['x-ratelimit-limit'] ?? null,
                'remaining' => $this->lastHeaders['x-ratelimit-remaining'] ?? null,
            ],
        ];
    }

    /**
     * Lấy thông báo lỗi
     */
    private function getErrorMessage(int $httpCode, $data): string
    {
        $messages = [
            401 => 'Unauthorized - Invalid or expired token',
            403 => 'Forbidden - Access denied',
            404 => 'Resource not found',
            409 => 'Conflict - The server may be processing another action',
            422 => 'Validation error',
            429 => 'Too many requests - Please wait before retrying',
            500 => 'Internal server error',
            503 => 'Service temporarily unavailable',
        ];

        $msg = $messages[$httpCode] ?? "HTTP Error {$httpCode}";

        if (is_array($data)) {
            if (isset($data['message'])) {
                $msg .= ': ' . $data['message'];
            }
            if (isset($data['errors'])) {
                foreach ($data['errors'] as $field => $errors) {
                    if (is_array($errors)) {
                        $msg .= ' | ' . $field . ': ' . implode(', ', $errors);
                    }
                }
            }
        }

        return $msg;
    }

    /**
     * Lấy debug info
     */
    public function getLastDebug(): array
    {
        return [
            'http_code' => $this->lastHttpCode,
            'error'     => $this->lastError,
            'headers'   => $this->lastHeaders,
        ];
    }

    /**
     * Lấy rate limit headers
     */
    public function getRateLimit(): array
    {
        return [
            'limit'     => $this->lastHeaders['x-ratelimit-limit'] ?? null,
            'remaining' => $this->lastHeaders['x-ratelimit-remaining'] ?? null,
        ];
    }

    // ================ ACCOUNT ================

    public function getAccount(): array
    {
        return $this->get('/account');
    }

    public function getSSHKeys(int $results = 20): array
    {
        return $this->get('/account/sshKeys', ['results' => $results]);
    }

    public function addSSHKey(string $name, string $publicKey): array
    {
        return $this->post('/account/sshKeys', [
            'name'      => $name,
            'publicKey' => $publicKey,
        ]);
    }

    public function deleteSSHKey(string $keyId): array
    {
        return $this->delete('/account/sshKeys/' . $keyId);
    }

    // ================ SERVERS ================

    public function getServers(int $results = 20): array
    {
        return $this->get('/server', ['results' => $results]);
    }

    public function getServer(string $serverId, bool $state = false): array
    {
        $params = $state ? ['state' => 'true'] : [];
        return $this->get('/server/' . $serverId, $params);
    }

    public function resetPassword(string $serverId): array
    {
        return $this->post('/server/' . $serverId . '/resetPassword');
    }

    public function changeName(string $serverId, string $name): array
    {
        return $this->put('/server/' . $serverId . '/name', ['name' => $name]);
    }

    public function toggleRescue(string $serverId): array
    {
        return $this->post('/server/' . $serverId . '/rescue');
    }

    public function updateSettings(string $serverId, array $settings): array
    {
        return $this->put('/server/' . $serverId . '/settings', $settings);
    }

    public function setBootOrder(string $serverId, string $order): array
    {
        return $this->post('/server/' . $serverId . '/bootOrder', ['order' => $order]);
    }

    // ================ BUILD ================

    public function buildServer(string $serverId, array $buildData): array
    {
        return $this->post('/server/' . $serverId . '/build', $buildData);
    }

    public function getOSTemplates(string $serverId): array
    {
        return $this->get('/server/' . $serverId . '/operatingSystemTemplates');
    }

    public function getSwapOptions(string $serverId): array
    {
        return $this->get('/server/' . $serverId . '/swap');
    }

    // ================ VNC ================

    public function toggleVNC(string $serverId): array
    {
        return $this->post('/server/' . $serverId . '/vnc');
    }

    public function getVNCSettings(string $serverId): array
    {
        return $this->get('/server/' . $serverId . '/vnc');
    }

    // ================ POWER ================

    public function restart(string $serverId): array
    {
        return $this->post('/server/' . $serverId . '/restart');
    }

    public function boot(string $serverId): array
    {
        return $this->post('/server/' . $serverId . '/boot');
    }

    public function shutdown(string $serverId): array
    {
        return $this->post('/server/' . $serverId . '/shutdown');
    }

    public function powerOff(string $serverId): array
    {
        return $this->post('/server/' . $serverId . '/powerOff');
    }

    // ================ TASKS ================

    public function getTasks(string $serverId): array
    {
        return $this->get('/server/' . $serverId . '/tasks');
    }

    public function getTask(string $serverId, string $taskId): array
    {
        return $this->get('/server/' . $serverId . '/task/' . $taskId);
    }

    // ================ ISO ================

    public function mountISO(string $serverId, string $iso): array
    {
        return $this->post('/server/' . $serverId . '/iso', ['iso' => $iso]);
    }

    public function getISOs(string $serverId): array
    {
        return $this->get('/server/' . $serverId . '/isos');
    }

    // ================ CONNECTION ================

    public function verifyConnection(): array
    {
        return $this->get('/connect');
    }
}