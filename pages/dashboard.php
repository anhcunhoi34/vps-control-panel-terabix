<?php
if (!defined('APP_ROOT')) die('Direct access not permitted');

$api = Auth::getApi();
if (!$api) { header('Location: login.php'); exit; }

$serversResult = $api->getServers(200);
$servers = [];
$totalServers = 0;
$errorMsg = '';

if ($serversResult['success'] && isset($serversResult['data']['data'])) {
    $servers = $serversResult['data']['data'];
    $totalServers = $serversResult['data']['total'] ?? count($servers);
} else {
    $errorMsg = $serversResult['error'] ?? 'Failed to load servers';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-hdd-rack"></i> Servers</h3>
    <button class="btn btn-outline-secondary btn-sm" onclick="location.reload()">
        <i class="bi bi-arrow-clockwise"></i> Refresh
    </button>
</div>

<?php if ($errorMsg): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($errorMsg) ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-4 col-sm-6">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Total Servers</h6>
                        <h2 class="mb-0"><?= $totalServers ?></h2>
                    </div>
                    <i class="bi bi-hdd-stack display-5 opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row" id="serverList">
    <?php if (empty($servers)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-inbox display-1" style="color:var(--text-muted)"></i>
                    <p class="mt-3" style="color:var(--text-muted)">No servers found</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($servers as $server): ?>
            <div class="col-xl-4 col-lg-6 mb-4">
                <div class="card server-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-hdd-network"></i>
                            <?= e($server['name'] ?? 'Unnamed') ?>
                        </h5>
                        <div>
                            <?php if ($server['suspended'] ?? false): ?>
                                <span class="badge bg-danger">Suspended</span>
                            <?php elseif ($server['rescue'] ?? false): ?>
                                <span class="badge bg-warning">Rescue</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="server-info">
                            <div class="info-row">
                                <span class="info-label"><i class="bi bi-cpu me-1"></i>CPU</span>
                                <span class="info-value"><?= e($server['cpu'] ?? 'N/A') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="bi bi-memory me-1"></i>Memory</span>
                                <span class="info-value"><?= e($server['memory'] ?? 'N/A') ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="bi bi-device-hdd me-1"></i>Storage</span>
                                <span class="info-value">
                                    <?php
                                    $storage = $server['storage'] ?? [];
                                    $primary = array_values(array_filter($storage, fn($s) => $s['primary'] ?? false));
                                    echo e($primary[0]['capacity'] ?? 'N/A');
                                    ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="bi bi-globe me-1"></i>IP</span>
                                <span class="info-value">
                                    <code><?= e($server['network']['primary']['ipv4'][0]['address'] ?? 'N/A') ?></code>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="bi bi-motherboard me-1"></i>Boot</span>
                                <span class="info-value"><?= ($server['uefi'] ?? false) ? 'UEFI' : 'BIOS' ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="bi bi-flag me-1"></i>Flags</span>
                                <span class="info-value">
                                    <?php
                                    $flags = [];
                                    if ($server['vncEnabled'] ?? false) $flags[] = '<span class="badge bg-info">VNC</span>';
                                    if ($server['isoMounted'] ?? false) $flags[] = '<span class="badge bg-secondary">ISO</span>';
                                    if ($server['protected'] ?? false) $flags[] = '<span class="badge bg-primary">Protected</span>';
                                    echo $flags ? implode(' ', $flags) : '<span style="color:var(--text-muted)">—</span>';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex gap-2">
                            <a href="index.php?page=server-detail&id=<?= e($server['id']) ?>" 
                               class="btn btn-primary btn-sm flex-fill">
                                <i class="bi bi-gear"></i> Manage
                            </a>
                            <div class="btn-group flex-fill">
                                <button class="btn btn-success btn-sm power-action" 
                                        data-action="boot" data-server="<?= e($server['id']) ?>" title="Boot">
                                    <i class="bi bi-play-fill"></i>
                                </button>
                                <button class="btn btn-warning btn-sm power-action" 
                                        data-action="restart" data-server="<?= e($server['id']) ?>" title="Restart">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                                <button class="btn btn-secondary btn-sm power-action" 
                                        data-action="shutdown" data-server="<?= e($server['id']) ?>" title="Shutdown">
                                    <i class="bi bi-stop-fill"></i>
                                </button>
                                <button class="btn btn-danger btn-sm power-action" 
                                        data-action="powerOff" data-server="<?= e($server['id']) ?>" title="Power Off">
                                    <i class="bi bi-power"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>