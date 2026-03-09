<?php
define('APP_ROOT', realpath(__DIR__));

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Api.php';
require_once APP_ROOT . '/includes/Auth.php';
require_once APP_ROOT . '/includes/helpers.php';

Security::initSession();

if (Auth::isLoggedIn()) { header('Location: index.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } elseif (Security::isLoginLocked()) {
        $error = 'Too many attempts. Please wait a few minutes.';
    } elseif (!Security::checkRateLimit('login')) {
        $error = 'Too many requests.';
    } else {
        $token = trim($_POST['api_token'] ?? '');
        $result = Auth::login($token);
        if ($result['success']) { header('Location: index.php'); exit; }
        $error = $result['error'];
    }
}

$csrfToken = Security::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="card">
            <div class="card-body">
                <div class="text-center mb-4">
                    <i class="bi bi-hdd-rack display-3" style="color:var(--accent-blue)"></i>
                    <h2 class="mt-3" style="color:#fff;font-weight:800"><?= e(APP_NAME) ?></h2>
                    <p style="color:var(--text-muted)">Enter your API token to continue</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php" autocomplete="off">
                    <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
                    
                    <div class="mb-4">
                        <label for="api_token" class="form-label"><i class="bi bi-key"></i> API Token</label>
                        <textarea class="form-control font-monospace" id="api_token" name="api_token" 
                                  placeholder="Paste your token here..."
                                  required autofocus rows="4"
                                  autocomplete="off" spellcheck="false"></textarea>
                        <div class="form-text mt-2">
                            <i class="bi bi-shield-lock"></i> Encrypted & stored in your session only
                            <span id="tokenLen" class="float-end"></span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100" id="loginBtn">
                        <i class="bi bi-box-arrow-in-right"></i> Connect
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
    const ta = document.getElementById('api_token');
    const tl = document.getElementById('tokenLen');
    ta?.addEventListener('input', () => {
        const l = ta.value.trim().length;
        tl.textContent = l ? l + ' chars' : '';
    });

    document.querySelector('form')?.addEventListener('submit', () => {
        const b = document.getElementById('loginBtn');
        b.disabled = true;
        b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Connecting...';
    });
    </script>
</body>
</html>