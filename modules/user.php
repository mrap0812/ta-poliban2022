<?php
$pdo  = db();
$aksi = $_GET['aksi'] ?? 'list';
$me   = (int) current_user()['id_user'];

if ($aksi === 'hapus') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === $me) {
        flash('warning', 'Anda tidak bisa menghapus akun sendiri.');
    } else {
        $pdo->prepare('DELETE FROM tb_user WHERE id_user = ?')->execute([$id]);
        flash('success', 'Pengguna dihapus.');
    }
    redirect('user');
}

if ($aksi === 'form') {
    $id  = (int) ($_GET['id'] ?? 0);
    $row = ['username' => '', 'nama_lengkap' => '', 'role' => 'surveyor'];
    if ($id) {
        $st = $pdo->prepare('SELECT username, nama_lengkap, role FROM tb_user WHERE id_user = ?');
        $st->execute([$id]);
        $row = $st->fetch() ?: redirect('user');
    }
    $errors = [];
    if (is_post()) {
        verify_csrf();
        $row = [
            'username'     => strtolower(trim((string) ($_POST['username'] ?? ''))),
            'nama_lengkap' => trim((string) ($_POST['nama_lengkap'] ?? '')),
            'role'         => in_array($_POST['role'] ?? '', ['admin', 'surveyor'], true) ? $_POST['role'] : 'surveyor',
        ];
        $pass = (string) ($_POST['password'] ?? '');
        if (!preg_match('/^[a-z0-9_.]{3,50}$/', $row['username'])) $errors['username'] = '3–50 karakter: huruf kecil, angka, titik, garis bawah.';
        if ($row['nama_lengkap'] === '') $errors['nama_lengkap'] = 'Nama wajib diisi.';
        if ((!$id || $pass !== '') && mb_strlen($pass) < 8) $errors['password'] = 'Password minimal 8 karakter.';
        if ($id === $me && $row['role'] !== 'admin') $errors['role'] = 'Anda tidak bisa menurunkan peran akun sendiri.';
        if (!$errors) {
            $st = $pdo->prepare('SELECT 1 FROM tb_user WHERE username = ? AND id_user <> ?');
            $st->execute([$row['username'], $id]);
            if ($st->fetchColumn()) $errors['username'] = 'Username sudah dipakai.';
        }
        if (!$errors) {
            if ($id) {
                $pdo->prepare('UPDATE tb_user SET username=?, nama_lengkap=?, role=? WHERE id_user=?')->execute([$row['username'], $row['nama_lengkap'], $row['role'], $id]);
                if ($pass !== '') $pdo->prepare('UPDATE tb_user SET password=? WHERE id_user=?')->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
            } else {
                $pdo->prepare('INSERT INTO tb_user (username, password, nama_lengkap, role) VALUES (?,?,?,?)')
                    ->execute([$row['username'], password_hash($pass, PASSWORD_DEFAULT), $row['nama_lengkap'], $row['role']]);
            }
            flash('success', 'Pengguna disimpan.');
            redirect('user');
        }
    }
    $inv = fn ($f) => isset($errors[$f]) ? ' is-invalid' : '';
    $fb  = fn ($f) => isset($errors[$f]) ? '<div class="invalid-feedback">' . e($errors[$f]) . '</div>' : '';
    ?>
    <div class="card" style="max-width: 520px"><div class="card-body">
        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label" for="username">Username</label>
                <input id="username" name="username" class="form-control<?= $inv('username') ?>" value="<?= e($row['username']) ?>" required><?= $fb('username') ?></div>
            <div class="mb-3"><label class="form-label" for="nama_lengkap">Nama lengkap</label>
                <input id="nama_lengkap" name="nama_lengkap" class="form-control<?= $inv('nama_lengkap') ?>" value="<?= e($row['nama_lengkap']) ?>" required><?= $fb('nama_lengkap') ?></div>
            <div class="mb-3"><label class="form-label" for="role">Peran</label>
                <select id="role" name="role" class="form-select<?= $inv('role') ?>">
                    <option value="surveyor" <?= $row['role'] === 'surveyor' ? 'selected' : '' ?>>Surveyor — input data &amp; kuesioner</option>
                    <option value="admin" <?= $row['role'] === 'admin' ? 'selected' : '' ?>>Admin — semua akses</option>
                </select><?= $fb('role') ?></div>
            <div class="mb-3"><label class="form-label" for="password">Password <?= $id ? '(kosongkan jika tidak diubah)' : '' ?></label>
                <input id="password" name="password" type="password" class="form-control<?= $inv('password') ?>" autocomplete="new-password" <?= $id ? '' : 'required' ?> minlength="8"><?= $fb('password') ?></div>
            <button class="btn btn-primary">Simpan</button>
            <a class="btn btn-link" href="<?= e(url('user')) ?>">Batal</a>
        </form>
    </div></div>
    <?php return;
}

$rows = $pdo->query('SELECT id_user, username, nama_lengkap, role, created_at FROM tb_user ORDER BY role, username')->fetchAll();
?>
<a class="btn btn-primary mb-3" href="<?= e(url('user', ['aksi' => 'form'])) ?>"><i class="bi bi-person-plus"></i> Tambah pengguna</a>
<div class="card"><div class="card-body table-scroll">
<table class="table table-hover align-middle mb-0">
    <thead><tr><th>Username</th><th>Nama</th><th>Peran</th><th class="text-end">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr><td><?= e($r['username']) ?></td><td><?= e($r['nama_lengkap']) ?></td>
            <td><span class="badge text-bg-<?= $r['role'] === 'admin' ? 'dark' : 'secondary' ?>"><?= e($r['role']) ?></span></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('user', ['aksi' => 'form', 'id' => $r['id_user']])) ?>">Ubah</a>
                <?php if ((int) $r['id_user'] !== $me): ?>
                    <form method="post" action="<?= e(url('user', ['aksi' => 'hapus'])) ?>" class="d-inline" data-confirm="Hapus pengguna <?= e($r['username']) ?>?">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($r['id_user']) ?>">
                        <button class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                <?php endif; ?>
            </td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div></div>
