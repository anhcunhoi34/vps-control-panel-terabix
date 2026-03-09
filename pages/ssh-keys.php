<?php
/**
 * Quản lý SSH Keys
 */

if (!defined('APP_ROOT')) {
    die('Direct access not permitted');
}

$api = Auth::getApi();
if (!$api) {
    header('Location: login.php');
    exit;
}

$csrfToken = Security::generateCsrfToken();

// Xử lý POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && !empty($_POST['name']) && !empty($_POST['publicKey'])) {
        $name = Security::sanitizeInput($_POST['name']);
        $publicKey = trim($_POST['publicKey']); // Không escape SSH key
        
        $result = $api->addSSHKey($name, $publicKey);
        if ($result['success']) {
            setFlash('success', 'SSH key added successfully');
        } else {
            setFlash('danger', 'Failed to add SSH key: ' . ($result['error'] ?? 'Unknown error'));
        }
        header('Location: index.php?page=ssh-keys');
        exit;
    }

    if ($action === 'delete' && !empty($_POST['key_id'])) {
        $keyId = $_POST['key_id'];
        if (Security::isValidIntId($keyId)) {
            $result = $api->deleteSSHKey($keyId);
            if ($result['success']) {
                setFlash('success', 'SSH key deleted');
            } else {
                setFlash('danger', 'Failed to delete SSH key: ' . ($result['error'] ?? 'Unknown error'));
            }
        }
        header('Location: index.php?page=ssh-keys');
        exit;
    }
}

$keysResult = $api->getSSHKeys(200);
$keys = [];

if ($keysResult['success'] && isset($keysResult['data']['data'])) {
    $keys = $keysResult['data']['data'];
}
?>

<h3 class="mb-4"><i class="bi bi-key"></i> SSH Keys</h3>

<!-- Add Key Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add SSH Key</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="index.php?page=ssh-keys">
            <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="add">
            
            <div class="mb-3">
                <label for="keyName" class="form-label">Name</label>
                <input type="text" class="form-control" id="keyName" name="name" 
                       required maxlength="100" placeholder="My SSH Key">
            </div>
            <div class="mb-3">
                <label for="publicKey" class="form-label">Public Key</label>
                <textarea class="form-control font-monospace" id="publicKey" name="publicKey" 
                          rows="4" required placeholder="ssh-rsa AAAAB3..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus"></i> Add Key
            </button>
        </form>
    </div>
</div>

<!-- Keys List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list"></i> SSH Keys (<?= count($keys) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($keys)): ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-key display-4"></i>
                <p class="mt-2">No SSH keys found</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Enabled</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($keys as $key): ?>
                        <tr>
                            <td><?= e((string)$key['id']) ?></td>
                            <td><strong><?= e($key['name']) ?></strong></td>
                            <td><span class="badge bg-secondary"><?= e($key['type'] ?? 'N/A') ?></span></td>
                            <td><?= boolBadge($key['enabled'] ?? false, 'Enabled', 'Disabled') ?></td>
                            <td><?= formatDate($key['created'] ?? '') ?></td>
                            <td>
                                <form method="POST" action="index.php?page=ssh-keys" 
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this SSH key?')">
                                    <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="key_id" value="<?= e((string)$key['id']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>