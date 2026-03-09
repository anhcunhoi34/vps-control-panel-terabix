<?php
/**
 * Account Settings
 */

if (!defined('APP_ROOT')) {
    die('Direct access not permitted');
}

$api = Auth::getApi();
if (!$api) {
    header('Location: login.php');
    exit;
}

$accountResult = $api->getAccount();
$account = [];

if ($accountResult['success'] && isset($accountResult['data']['data'])) {
    $account = $accountResult['data']['data'];
}
?>

<h3 class="mb-4"><i class="bi bi-gear"></i> Account Settings</h3>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person"></i> Account Information</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <th>Name:</th>
                        <td><?= e($account['name'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?= e($account['email'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Timezone:</th>
                        <td><?= e($account['timezone'] ?? 'N/A') ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-shield-check"></i> Session</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <th>Login Time:</th>
                        <td><?= date('d/m/Y H:i:s', $_SESSION['login_time'] ?? time()) ?></td>
                    </tr>
                    <tr>
                        <th>Session Expires:</th>
                        <td><?= date('d/m/Y H:i:s', ($_SESSION['_last_activity'] ?? time()) + SESSION_LIFETIME) ?></td>
                    </tr>
                    <tr>
                        <th>Your IP:</th>
                        <td><code><?= e(Security::getClientIp()) ?></code></td>
                    </tr>
                </table>
                <a href="logout.php" class="btn btn-danger">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-link-45deg"></i> API Connection</h5>
            </div>
            <div class="card-body">
                <button class="btn btn-outline-primary" id="testConnection">
                    <i class="bi bi-wifi"></i> Test Connection
                </button>
                <div id="connectionResult" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>