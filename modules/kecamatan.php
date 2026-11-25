<?php
$pdo  = db();
$aksi = $_GET['aksi'] ?? 'list';

if ($aksi === 'hapus') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $used = $pdo->prepare('SELECT COUNT(*) FROM tb_kepala_keluarga WHERE id_kecamatan = ?');
    $used->execute([$id]);
    if ($used->fetchColumn() > 0) {
        flash('warning', 'Kecamatan masih dipakai oleh data kepala keluarga, tidak bisa dihapus.');
    } else {
        $pdo->prepare('DELETE FROM tb_kecamatan WHERE id_kecamatan = ?')->execute([$id]);
        flash('success', 'Kecamatan dihapus.');
    }
    redirect('kecamatan');
}

if ($aksi === 'form') {
    $id   = (int) ($_GET['id'] ?? 0);
    $nama = '';
    if ($id) {
        $st = $pdo->prepare('SELECT nama FROM tb_kecamatan WHERE id_kecamatan = ?');
        $st->execute([$id]);
        $nama = $st->fetchColumn();
        if ($nama === false) redirect('kecamatan');
    }
    $error = null;
    if (is_post()) {
        verify_csrf();
        $nama = trim((string) ($_POST['nama'] ?? ''));
        if ($nama === '' || mb_strlen($nama) > 50) {
            $error = 'Nama kecamatan wajib diisi (maks. 50 karakter).';
        } else {
            $id
                ? $pdo->prepare('UPDATE tb_kecamatan SET nama = ? WHERE id_kecamatan = ?')->execute([$nama, $id])
                : $pdo->prepare('INSERT INTO tb_kecamatan (nama) VALUES (?)')->execute([$nama]);
            flash('success', 'Kecamatan disimpan.');
            redirect('kecamatan');
        }
    } ?>
    <div class="card" style="max-width: 520px">
        <div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <label class="form-label" for="nama">Nama kecamatan</label>
                <input id="nama" name="nama" class="form-control <?= $error ? 'is-invalid' : '' ?>" value="<?= e($nama) ?>" maxlength="50" required autofocus>
                <?php if ($error): ?><div class="invalid-feedback"><?= e($error) ?></div><?php endif; ?>
                <div class="mt-3">
                    <button class="btn btn-primary">Simpan</button>
                    <a class="btn btn-link" href="<?= e(url('kecamatan')) ?>">Batal</a>
                </div>
            </form>
        </div>
    </div>
    <?php return;
}

$rows = $pdo->query(
    'SELECT k.*, COUNT(kk.nik) jumlah FROM tb_kecamatan k
     LEFT JOIN tb_kepala_keluarga kk ON kk.id_kecamatan = k.id_kecamatan
     GROUP BY k.id_kecamatan ORDER BY k.nama'
)->fetchAll();
?>
<a class="btn btn-primary mb-3" href="<?= e(url('kecamatan', ['aksi' => 'form'])) ?>"><i class="bi bi-plus-lg"></i> Tambah kecamatan</a>
<div class="card"><div class="card-body table-scroll">
<table class="table table-hover align-middle mb-0">
    <thead><tr><th>Kecamatan</th><th class="text-end">Jumlah KK</th><th class="text-end">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e($r['nama']) ?></td>
            <td class="text-end"><?= e($r['jumlah']) ?></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('kecamatan', ['aksi' => 'form', 'id' => $r['id_kecamatan']])) ?>">Ubah</a>
                <form method="post" action="<?= e(url('kecamatan', ['aksi' => 'hapus'])) ?>" class="d-inline" data-confirm="Hapus kecamatan <?= e($r['nama']) ?>?">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($r['id_kecamatan']) ?>">
                    <button class="btn btn-sm btn-outline-danger" <?= $r['jumlah'] > 0 ? 'disabled title="Masih dipakai"' : '' ?>>Hapus</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="3" class="text-body-secondary">Belum ada kecamatan.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div>
