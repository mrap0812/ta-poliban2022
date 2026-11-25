<?php
$pdo    = db();
$me     = current_user();
$errors = [];

if (is_post()) {
    verify_csrf();
    $old  = (string) ($_POST['password_lama'] ?? '');
    $new  = (string) ($_POST['password_baru'] ?? '');
    $conf = (string) ($_POST['password_konfirmasi'] ?? '');

    $st = $pdo->prepare('SELECT password FROM tb_user WHERE id_user = ?');
    $st->execute([$me['id_user']]);
    if (!password_verify($old, (string) $st->fetchColumn())) $errors['password_lama'] = 'Password lama salah.';
    if (mb_strlen($new) < 8) $errors['password_baru'] = 'Password baru minimal 8 karakter.';
    elseif ($new !== $conf) $errors['password_konfirmasi'] = 'Konfirmasi tidak sama.';

    if (!$errors) {
        $pdo->prepare('UPDATE tb_user SET password = ? WHERE id_user = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id_user']]);
        session_regenerate_id(true);
        flash('success', 'Password berhasil diubah.');
        redirect('profil');
    }
}
?>
<div class="card mb-3" style="max-width: 520px"><div class="card-body">
    <dl class="row mb-0">
        <dt class="col-4">Username</dt><dd class="col-8"><?= e($me['username']) ?></dd>
        <dt class="col-4">Nama</dt><dd class="col-8"><?= e($me['nama_lengkap']) ?></dd>
        <dt class="col-4">Peran</dt><dd class="col-8 mb-0"><?= e($me['role']) ?></dd>
    </dl>
</div></div>

<div class="card" style="max-width: 520px">
    <div class="card-header">Ubah password</div>
    <div class="card-body">
        <form method="post">
            <?= csrf_field() ?>
            <?php foreach (['password_lama' => ['Password lama', 'current-password'], 'password_baru' => ['Password baru (min. 8 karakter)', 'new-password'], 'password_konfirmasi' => ['Ulangi password baru', 'new-password']] as $f => [$label, $ac]): ?>
                <div class="mb-3">
                    <label class="form-label" for="<?= $f ?>"><?= e($label) ?></label>
                    <input id="<?= $f ?>" name="<?= $f ?>" type="password" class="form-control<?= isset($errors[$f]) ? ' is-invalid' : '' ?>" autocomplete="<?= $ac ?>" required>
                    <?php if (isset($errors[$f])): ?><div class="invalid-feedback"><?= e($errors[$f]) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button class="btn btn-primary">Simpan password</button>
        </form>
    </div>
</div>
