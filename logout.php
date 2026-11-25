<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

// Logout lewat POST + CSRF agar tidak bisa dipicu tautan dari situs lain.
verify_csrf();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
