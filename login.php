<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (current_user() !== null) {
    redirect('dashboard');
}

$error = null;
$username = '';

if (is_post()) {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    // Pembatas sederhana percobaan login per sesi (mencegah tebak-tebakan beruntun).
    $now = time();
    $_SESSION['login_fail'] = array_filter($_SESSION['login_fail'] ?? [], fn ($t) => $t > $now - 300);
    if (count($_SESSION['login_fail']) >= 5) {
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam 5 menit.';
    } else {
        $stmt = db()->prepare('SELECT id_user, username, password, nama_lengkap, role FROM tb_user WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE tb_user SET password = ? WHERE id_user = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id_user']]);
            }
            session_regenerate_id(true);
            unset($user['password'], $_SESSION['login_fail']);
            $_SESSION['user'] = $user;
            redirect('dashboard');
        }
        $_SESSION['login_fail'][] = $now;
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Masuk — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="public/app.css">
</head>
<body class="login-page">
<main class="login-card card shadow-sm">
    <div class="card-body p-4">
        <h1 class="h4 mb-1"><?= e(APP_NAME) ?></h1>
        <p class="text-body-secondary small mb-4">Sistem pendukung pengelompokan kelayakan penerima beras miskin</p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input id="username" name="username" class="form-control" value="<?= e($username) ?>" autocomplete="username" required autofocus>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary w-100">Masuk</button>
        </form>
    </div>
    <div class="card-footer small text-body-secondary text-center">
        Tugas Akhir D3 Teknik Informatika POLIBAN 2022
    </div>
</main>
</body>
</html>
