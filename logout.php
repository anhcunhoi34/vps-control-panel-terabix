<?php
define('APP_ROOT', __DIR__);

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/Security.php';
require_once APP_ROOT . '/includes/Auth.php';

Security::initSession();
Auth::logout();

header('Location: login.php');
exit;