<?php
if (!defined('APP_ROOT')) die('Direct access not permitted');

$serverId = $_GET['id'] ?? '';
if (!Security::isValidServerId($serverId)) {
    setFlash('danger', 'Invalid server ID');
    header('Location: index.php'); exit;
}

$api = Auth::getApi();
if (!$api) { header('Location: login.php'); exit; }

$result = $api->getServer($serverId, true);
if (!$result['success']) {
    setFlash('danger', 'Server not found: ' . ($result['error'] ?? 'Unknown'));
    header('Location: index.php'); exit;
}

$server = $result['data']['data'] ?? [];
$state = $server['state'] ?? null;
$csrfToken = Security::generateCsrfToken();

$isRunning = $state['running'] ?? false;
?>

<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active"><?= e($server['name'] ?? 'Server') ?></li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>
        <i class="bi bi-hdd-network"></i> <?= e($server['name'] ?? 'Server') ?>
        <?php if ($state !== null): ?>
            <span class="badge <?= $isRunning ? 'bg-success' : 'bg-danger' ?>" style="font-size:0.65rem;">
                <span class="pulse-dot <?= $isRunning ? 'green' : 'red' ?>"></span>
                <?= $isRunning ? 'RUNNING' : 'STOPPED' ?>
            </span>
        <?php endif; ?>
    </h3>
    <button class="btn btn-outline-secondary btn-sm" onclick="location.reload()">
        <i class="bi bi-arrow-clockwise"></i> Refresh
    </button>
</div>

<input type="hidden" id="serverId" value="<?= e($serverId) ?>">
<input type="hidden" id="csrfToken" value="<?= e($csrfToken) ?>">

<div class="row">
    <!-- LEFT COLUMN -->
    <div class="col-lg-8">
        <!-- Server Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-info-circle"></i> Server Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><th width="120">ID</th><td><code style="font-size:0.7rem"><?= e($server['id']) ?></code></td></tr>
                            <tr><th>Name</th><td id="serverName"><?= e($server['name'] ?? 'N/A') ?></td></tr>
                            <tr><th>Hostname</th><td><?= e($server['hostname'] ?? '—') ?></td></tr>
                            <tr>
                                <th>CPU</th>
                                <td>
                                    <?= e($server['cpu'] ?? 'N/A') ?>
                                    <?php if ($state && isset($state['cpu'])): ?>
                                        <span class="badge bg-info ms-1"><?= e($state['cpu']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr><th>Memory</th><td><?= e($server['memory'] ?? 'N/A') ?></td></tr>
                            <tr><th>Boot</th><td><?= ($server['uefi'] ?? false) ? 'UEFI' : 'BIOS' ?></td></tr>
                            <tr><th>Boot Order</th><td><?= e(implode(' → ', $server['bootOrder'] ?? [])) ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><th width="120">Suspended</th><td><?= boolBadge($server['suspended'] ?? false) ?></td></tr>
                            <tr><th>Protected</th><td><?= boolBadge($server['protected'] ?? false) ?></td></tr>
                            <tr><th>Rescue</th><td><?= boolBadge($server['rescue'] ?? false, 'Active', 'Off') ?></td></tr>
                            <tr><th>VNC</th><td><?= boolBadge($server['vncEnabled'] ?? false, 'Enabled', 'Disabled') ?></td></tr>
                            <tr><th>ISO</th><td><?= boolBadge($server['isoMounted'] ?? false, 'Mounted', 'None') ?></td></tr>
                            <tr><th>Created</th><td><?= formatDate($server['created'] ?? '') ?></td></tr>
                            <tr>
                                <th>Period</th>
                                <td>
                                    <?php 
                                    $p = $server['currentMonthlyPeriod'] ?? null;
                                    echo $p ? e(formatDate($p['start'],'d/m') . ' — ' . formatDate($p['end'],'d/m/Y')) : '—';
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Network -->
        <div class="card mb-4">
            <div class="card-header"><h5><i class="bi bi-diagram-3"></i> Network</h5></div>
            <div class="card-body">
                <?php 
                $network = $server['network'] ?? [];
                foreach (['primary', 'secondary'] as $nic):
                    $d = $network[$nic] ?? null;
                    if (!$d || (is_array($d) && empty($d))) continue;
                ?>
                <h6 class="text-uppercase mb-2" style="color:var(--text-muted); font-size:0.6875rem; letter-spacing:1px;">
                    <?= ucfirst($nic) ?> NIC
                </h6>
                <table class="table table-sm mb-3">
                    <tr><th width="100">MAC</th><td><code><?= e($d['mac'] ?? 'N/A') ?></code></td></tr>
                    <?php if (isset($d['limit']) && $d['limit']): ?>
                        <tr><th>Limit</th><td><?= e($d['limit']) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($d['ipv4'] ?? [] as $ip): ?>
                        <tr>
                            <th>IPv4</th>
                            <td>
                                <code><?= e($ip['address']) ?></code>
                                <small style="color:var(--text-muted)">
                                    &nbsp;GW <?= e($ip['gateway']) ?> / <?= e($ip['netmask']) ?>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($d['ipv6'] ?? [] as $ip6): ?>
                        <tr>
                            <th>IPv6</th>
                            <td><code style="font-size:0.7rem"><?= e($ip6['addresses'][0] ?? $ip6['subnet'] ?? 'N/A') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php endforeach; ?>

                <?php if ($state && isset($state['network'])): ?>
                <h6 class="text-uppercase mb-2 mt-3" style="color:var(--text-muted); font-size:0.6875rem; letter-spacing:1px;">
                    Traffic (Current Period)
                </h6>
                <?php foreach ($state['network'] as $nicName => $ns): 
                    $t = $ns['traffic'] ?? [];
                ?>
                <div class="d-flex gap-4 align-items-center mb-2" style="font-size:0.8125rem">
                    <span style="color:var(--text-muted);min-width:60px"><?= ucfirst(e($nicName)) ?></span>
                    <span><i class="bi bi-arrow-down-circle text-success"></i> <?= formatBytes($t['rx'] ?? 0) ?></span>
                    <span><i class="bi bi-arrow-up-circle text-danger"></i> <?= formatBytes($t['tx'] ?? 0) ?></span>
                    <span class="fw-bold"><i class="bi bi-arrow-left-right"></i> <?= formatBytes($t['total'] ?? 0) ?></span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Storage -->
        <div class="card mb-4">
            <div class="card-header"><h5><i class="bi bi-device-hdd"></i> Storage</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Capacity</th><th>Primary</th><th>Enabled</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($server['storage'] ?? [] as $disk): ?>
                        <tr>
                            <td><strong><?= e($disk['capacity'] ?? 'N/A') ?></strong></td>
                            <td><?= boolBadge($disk['primary'] ?? false) ?></td>
                            <td><?= boolBadge($disk['enabled'] ?? false, 'Yes', 'No') ?></td>
                            <td style="color:var(--text-muted)"><?= formatDate($disk['created'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tasks -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-list-task"></i> Recent Tasks</h5>
                <button class="btn btn-outline-secondary btn-sm" id="refreshTasks">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <div class="card-body" id="tasksContainer">
                <div class="text-center py-4" style="color:var(--text-muted)">
                    <div class="spinner-border spinner-border-sm"></div> Loading tasks...
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div class="col-lg-4">
        <!-- Power -->
        <div class="card mb-4">
            <div class="card-header"><h5><i class="bi bi-lightning-charge"></i> Power Control</h5></div>
            <div class="card-body">
                <div class="power-grid">
                    <button class="btn btn-success server-action" data-action="boot">
                        <i class="bi bi-play-fill"></i> Boot
                    </button>
                    <button class="btn btn-warning server-action" data-action="restart">
                        <i class="bi bi-arrow-repeat"></i> Restart
                    </button>
                    <button class="btn btn-secondary server-action" data-action="shutdown">
                        <i class="bi bi-stop-fill"></i> Shutdown
                    </button>
                    <button class="btn btn-danger server-action" 
                            data-action="powerOff" data-confirm="Force power off this server?">
                        <i class="bi bi-power"></i> Power Off
                    </button>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="card mb-4">
            <div class="card-header"><h5><i class="bi bi-tools"></i> Actions</h5></div>
            <div class="card-body">
                <!-- Rename -->
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="newServerName" 
                           placeholder="Server name" maxlength="100"
                           value="<?= e($server['name'] ?? '') ?>">
                    <button class="btn btn-outline-primary" id="changeNameBtn">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>

                <div class="action-stack">
                    <button class="btn btn-warning server-action" 
                            data-action="resetPassword"
                            data-confirm="Reset password? A new one will be generated.">
                        <i class="bi bi-key"></i> Reset Password
                    </button>

                    <button class="btn btn-info server-action" data-action="rescue">
                        <i class="bi bi-life-preserver"></i> Toggle Rescue
                        <?php if ($server['rescue'] ?? false): ?>
                            <span class="badge bg-warning ms-auto">Active</span>
                        <?php endif; ?>
                    </button>

                    <button class="btn btn-outline-info server-action" data-action="vnc" id="toggleVncBtn">
                        <i class="bi bi-display"></i> Toggle VNC
                        <?php if ($server['vncEnabled'] ?? false): ?>
                            <span class="badge bg-success ms-auto">On</span>
                        <?php endif; ?>
                    </button>

                    <button class="btn btn-outline-secondary" id="vncDetailsBtn">
                        <i class="bi bi-info-circle"></i> VNC Connection Info
                    </button>

                    <a href="index.php?page=build&id=<?= e($serverId) ?>" class="btn btn-outline-danger">
                        <i class="bi bi-wrench"></i> Build / Rebuild
                    </a>
                </div>
            </div>
        </div>

        <!-- Settings -->
        <div class="card mb-4">
            <div class="card-header"><h5><i class="bi bi-gear"></i> Settings</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Boot Type</label>
                    <select class="form-select" id="bootType">
                        <option value="bios" <?= ($server['uefi'] ?? false) ? '' : 'selected' ?>>BIOS</option>
                        <option value="uefi" <?= ($server['uefi'] ?? false) ? 'selected' : '' ?>>UEFI</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Boot Order</label>
                    <select class="form-select" id="bootOrder">
                        <option value="hdd,cdrom">HDD → CDROM</option>
                        <option value="cdrom,hdd">CDROM → HDD</option>
                    </select>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" id="saveSettingsBtn">
                        <i class="bi bi-check-lg"></i> Save Settings
                    </button>
                    <button class="btn btn-outline-primary" id="saveBootOrderBtn">
                        <i class="bi bi-sort-numeric-down"></i> Save Boot Order
                    </button>
                </div>
            </div>
        </div>

        <!-- VNC Details -->
        <div class="card mb-4 d-none" id="vncDetailsCard">
            <div class="card-header"><h5><i class="bi bi-display"></i> VNC Connection</h5></div>
            <div class="card-body" id="vncDetailsBody"></div>
        </div>
    </div>
</div>