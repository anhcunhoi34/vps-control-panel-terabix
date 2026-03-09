<?php
/**
 * Build / Rebuild server
 */

if (!defined('APP_ROOT')) {
    die('Direct access not permitted');
}

$serverId = $_GET['id'] ?? '';

if (!Security::isValidServerId($serverId)) {
    setFlash('danger', 'Invalid server ID');
    header('Location: index.php');
    exit;
}

$api = Auth::getApi();
if (!$api) {
    header('Location: login.php');
    exit;
}

$csrfToken = Security::generateCsrfToken();

// Lấy server info
$serverResult = $api->getServer($serverId);
$server = $serverResult['data']['data'] ?? [];

// Lấy OS templates
$templatesResult = $api->getOSTemplates($serverId);
$templates = $templatesResult['data']['data'] ?? [];

// Lấy swap options
$swapResult = $api->getSwapOptions($serverId);
$swapOptions = $swapResult['data']['data'] ?? [];

// Lấy SSH keys
$keysResult = $api->getSSHKeys(200);
$sshKeys = [];
if ($keysResult['success'] && isset($keysResult['data']['data'])) {
    $sshKeys = $keysResult['data']['data'];
}
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item">
            <a href="index.php?page=server-detail&id=<?= e($serverId) ?>">
                <?= e($server['name'] ?? 'Server') ?>
            </a>
        </li>
        <li class="breadcrumb-item active">Build</li>
    </ol>
</nav>

<h3 class="mb-4">
    <i class="bi bi-wrench"></i> Build / Rebuild Server
</h3>

<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <strong>Warning:</strong> Building/Rebuilding will erase all data on this server. 
    This action cannot be undone!
</div>

<div class="card">
    <div class="card-body">
        <form id="buildForm">
            <input type="hidden" id="buildServerId" value="<?= e($serverId) ?>">
            <input type="hidden" id="buildCsrf" value="<?= e($csrfToken) ?>">

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="buildMethod" class="form-label">Build Method *</label>
                        <select class="form-select" id="buildMethod" required>
                            <option value="template">Template</option>
                            <option value="self">Self Install (ISO)</option>
                        </select>
                    </div>

                    <div class="mb-3" id="templateGroup">
                        <label for="templateId" class="form-label">Operating System *</label>
                        <select class="form-select" id="templateId" required>
                            <option value="">Select OS Template...</option>
                            <?php foreach ($templates as $tpl): ?>
                                <option value="<?= e((string)$tpl['id']) ?>" 
                                        title="<?= e($tpl['description'] ?? '') ?>">
                                    <?= e($tpl['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="buildHostname" class="form-label">Hostname</label>
                        <input type="text" class="form-control" id="buildHostname" 
                               placeholder="server.example.com" maxlength="255">
                    </div>

                    <div class="mb-3">
                        <label for="buildName" class="form-label">Server Name</label>
                        <input type="text" class="form-control" id="buildName" 
                               value="<?= e($server['name'] ?? '') ?>" maxlength="100">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="buildSwap" class="form-label">Swap</label>
                        <select class="form-select" id="buildSwap">
                            <?php foreach ($swapOptions as $swap): ?>
                                <option value="<?= e((string)$swap['value']) ?>">
                                    <?= e($swap['description']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="buildTimezone" class="form-label">Timezone</label>
                        <select class="form-select" id="buildTimezone">
                            <option value="UTC" selected>UTC</option>
                            <option value="Asia/Ho_Chi_Minh">Asia/Ho_Chi_Minh</option>
                            <option value="Asia/Bangkok">Asia/Bangkok</option>
                            <option value="Asia/Tokyo">Asia/Tokyo</option>
                            <option value="Asia/Singapore">Asia/Singapore</option>
                            <option value="Europe/London">Europe/London</option>
                            <option value="America/New_York">America/New_York</option>
                            <option value="America/Los_Angeles">America/Los_Angeles</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="buildIpv6" checked>
                            <label class="form-check-label" for="buildIpv6">Enable IPv6</label>
                        </div>
                    </div>

                    <?php if (!empty($sshKeys)): ?>
                    <div class="mb-3">
                        <label class="form-label">SSH Keys</label>
                        <?php foreach ($sshKeys as $key): ?>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input ssh-key-check" 
                                       value="<?= e((string)$key['id']) ?>"
                                       id="sshKey<?= e((string)$key['id']) ?>">
                                <label class="form-check-label" for="sshKey<?= e((string)$key['id']) ?>">
                                    <?= e($key['name']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-3">
                <label for="buildUserData" class="form-label">User Data (cloud-init)</label>
                <textarea class="form-control font-monospace" id="buildUserData" 
                          rows="4" placeholder="Optional cloud-init user data..."></textarea>
                <div class="form-text">
                    Valid cloud-init user_data. See 
                    <a href="https://cloudinit.readthedocs.io/en/latest/reference/examples.html" 
                       target="_blank" rel="noopener">documentation</a>.
                </div>
            </div>

            <div class="mt-4">
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="confirmBuild" required>
                    <label class="form-check-label text-danger" for="confirmBuild">
                        <strong>I understand this will erase all data on this server</strong>
                    </label>
                </div>
                <button type="submit" class="btn btn-danger btn-lg" id="buildBtn" disabled>
                    <i class="bi bi-wrench"></i> Build Server
                </button>
            </div>
        </form>
    </div>
</div>