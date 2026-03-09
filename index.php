<?php
/**
 * Entry point - Router chính
 */

define('APP_ROOT', realpath(__DIR__));

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Api.php';
require_once APP_ROOT . '/includes/Auth.php';
require_once APP_ROOT . '/includes/helpers.php';

Security::initSession();
Auth::requireLogin();

$page = currentPage();

// Whitelist các trang cho phép
$allowedPages = [
    'dashboard',
    'server-detail',
    'ssh-keys',
    'settings',
    'build',
];

if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

$pageFile = APP_ROOT . '/pages/' . $page . '.php';

// Debug: kiểm tra file có tồn tại không
if (!file_exists($pageFile)) {
    // Nếu trang được yêu cầu không tồn tại, thử dashboard
    $page = 'dashboard';
    $pageFile = APP_ROOT . '/pages/dashboard.php';
    
    // Nếu dashboard cũng không tồn tại, báo lỗi rõ ràng
    if (!file_exists($pageFile)) {
        die(
            '<h3>Setup Error</h3>' .
            '<p>Missing pages directory or files.</p>' .
            '<p><strong>APP_ROOT:</strong> ' . htmlspecialchars(APP_ROOT) . '</p>' .
            '<p><strong>Looking for:</strong> ' . htmlspecialchars($pageFile) . '</p>' .
            '<p><strong>Directory exists:</strong> ' . (is_dir(APP_ROOT . '/pages') ? 'YES' : 'NO') . '</p>' .
            '<h4>Please create these directories and files:</h4>' .
            '<pre>' .
            "vps-control/\n" .
            "├── pages/\n" .
            "│   ├── dashboard.php\n" .
            "│   ├── server-detail.php\n" .
            "│   ├── ssh-keys.php\n" .
            "│   ├── settings.php\n" .
            "│   └── build.php\n" .
            '</pre>'
        );
    }
}

// Lấy thông tin chung
$accountName = $_SESSION['account_name'] ?? 'User';
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-hdd-rack"></i> <?= e(APP_NAME) ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= isActive('dashboard') ?>" href="index.php?page=dashboard">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= isActive('ssh-keys') ?>" href="index.php?page=ssh-keys">
                            <i class="bi bi-key"></i> SSH Keys
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= isActive('settings') ?>" href="index.php?page=settings">
                            <i class="bi bi-gear"></i> Account
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" 
                           data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= e($accountName) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="index.php?page=settings">
                                <i class="bi bi-gear"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php require $pageFile; ?>
        </div>
    </div>

    <!-- Confirm Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalTitle">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="confirmModalBody">
                    Are you sure?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmModalBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>