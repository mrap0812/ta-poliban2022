<?php
$pdo  = db();
$aksi = $_GET['aksi'] ?? 'list';
$kecamatan = get_kecamatan();

if ($aksi === 'hapus') {
    require_admin();
    verify_csrf();
    $pdo->prepare('DELETE FROM tb_kepala_keluarga WHERE nik = ?')->execute([(string) ($_POST['nik'] ?? '')]);
    flash('success', 'Data kepala keluarga beserta nilai kuesionernya dihapus.');
    redirect('kk');
}

if ($aksi === 'form') {
    $nikLama = (string) ($_GET['nik'] ?? '');
    $row = ['nik' => '', 'nama' => '', 'alamat' => '', 'id_kecamatan' => ''];
    if ($nikLama !== '') {
        $st = $pdo->prepare('SELECT * FROM tb_kepala_keluarga WHERE nik = ?');
        $st->execute([$nikLama]);
        $row = $st->fetch() ?: redirect('kk');
    }
    $errors = [];
    if (is_post()) {
        verify_csrf();
        $row = [
            'nik'          => preg_replace('/\D/', '', (string) ($_POST['nik'] ?? '')),
            'nama'         => trim((string) ($_POST['nama'] ?? '')),
            'alamat'       => trim((string) ($_POST['alamat'] ?? '')),
            'id_kecamatan' => (int) ($_POST['id_kecamatan'] ?? 0),
        ];
        if (!valid_nik($row['nik'])) $errors['nik'] = 'NIK harus 16 digit angka.';
        if ($row['nama'] === '' || mb_strlen($row['nama']) > 100) $errors['nama'] = 'Nama wajib diisi (maks. 100 karakter).';
        if (!in_array($row['id_kecamatan'], array_map('intval', array_column($kecamatan, 'id_kecamatan')), true)) $errors['id_kecamatan'] = 'Pilih kecamatan.';
        if (!$errors && $row['nik'] !== $nikLama) {
            $st = $pdo->prepare('SELECT 1 FROM tb_kepala_keluarga WHERE nik = ?');
            $st->execute([$row['nik']]);
            if ($st->fetchColumn()) $errors['nik'] = 'NIK sudah terdaftar.';
        }
        if (!$errors) {
            $p = [$row['nik'], $row['nama'], $row['alamat'] ?: null, $row['id_kecamatan']];
            if ($nikLama !== '') {
                $pdo->prepare('UPDATE tb_kepala_keluarga SET nik=?, nama=?, alamat=?, id_kecamatan=? WHERE nik=?')->execute([...$p, $nikLama]);
                flash('success', 'Data diperbarui.');
                redirect('kk');
            }
            $pdo->prepare('INSERT INTO tb_kepala_keluarga (nik, nama, alamat, id_kecamatan) VALUES (?,?,?,?)')->execute($p);
            flash('success', 'Data ditambahkan. Silakan isi kuesionernya.');
            redirect('kuesioner', ['aksi' => 'form', 'nik' => $row['nik']]);
        }
    }
    $inv = fn ($f) => isset($errors[$f]) ? ' is-invalid' : '';
    $fb  = fn ($f) => isset($errors[$f]) ? '<div class="invalid-feedback">' . e($errors[$f]) . '</div>' : '';
    ?>
    <div class="card" style="max-width: 620px"><div class="card-body">
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="nik">NIK</label>
                <input id="nik" name="nik" class="form-control<?= $inv('nik') ?>" value="<?= e($row['nik']) ?>" inputmode="numeric" pattern="\d{16}" maxlength="16" required>
                <?= $fb('nik') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="nama">Nama kepala keluarga</label>
                <input id="nama" name="nama" class="form-control<?= $inv('nama') ?>" value="<?= e($row['nama']) ?>" maxlength="100" required>
                <?= $fb('nama') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="alamat">Alamat (opsional)</label>
                <input id="alamat" name="alamat" class="form-control" value="<?= e($row['alamat'] ?? '') ?>" maxlength="255">
            </div>
            <div class="mb-3">
                <label class="form-label" for="id_kecamatan">Kecamatan</label>
                <select id="id_kecamatan" name="id_kecamatan" class="form-select<?= $inv('id_kecamatan') ?>" required>
                    <option value="">— pilih —</option>
                    <?php foreach ($kecamatan as $k): ?>
                        <option value="<?= e($k['id_kecamatan']) ?>" <?= (int) $row['id_kecamatan'] === (int) $k['id_kecamatan'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $fb('id_kecamatan') ?>
            </div>
            <button class="btn btn-primary">Simpan</button>
            <a class="btn btn-link" href="<?= e(url('kk')) ?>">Batal</a>
        </form>
    </div></div>
    <?php return;
}

$fKec = (int) ($_GET['kecamatan'] ?? 0);
$jmlKrit = (int) $pdo->query('SELECT COUNT(*) FROM tb_kriteria')->fetchColumn();
$last = latest_perhitungan();
$sql = 'SELECT kk.*, k.nama kecamatan,
               (SELECT COUNT(*) FROM tb_nilai n WHERE n.nik = kk.nik) terisi,
               h.id_cluster, c.nama nama_cluster
        FROM tb_kepala_keluarga kk
        JOIN tb_kecamatan k ON k.id_kecamatan = kk.id_kecamatan
        LEFT JOIN tb_hasil h ON h.nik = kk.nik AND h.id_perhitungan = ?
        LEFT JOIN tb_cluster c ON c.id_cluster = h.id_cluster'
     . ($fKec ? ' WHERE kk.id_kecamatan = ?' : '') . ' ORDER BY kk.nama';
$st = $pdo->prepare($sql);
$st->execute($fKec ? [$last['id_perhitungan'] ?? 0, $fKec] : [$last['id_perhitungan'] ?? 0]);
$rows = $st->fetchAll();
?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-primary" href="<?= e(url('kk', ['aksi' => 'form'])) ?>"><i class="bi bi-plus-lg"></i> Tambah data</a>
    <form class="d-flex gap-2 ms-auto" method="get">
        <input type="hidden" name="page" value="kk">
        <select name="kecamatan" class="form-select" aria-label="Filter kecamatan" onchange="this.form.submit()">
            <option value="0">Semua kecamatan</option>
            <?php foreach ($kecamatan as $k): ?>
                <option value="<?= e($k['id_kecamatan']) ?>" <?= $fKec === (int) $k['id_kecamatan'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="search" class="form-control" placeholder="Cari nama / NIK" data-filter="#tabel-kk" aria-label="Cari">
    </form>
</div>
<div class="card"><div class="card-body table-scroll">
<table id="tabel-kk" class="table table-hover align-middle mb-0">
    <thead><tr><th>NIK</th><th>Nama</th><th>Kecamatan</th><th>Kuesioner</th><th>Cluster terakhir</th><th class="text-end">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $ok = (int) $r['terisi'] >= $jmlKrit; ?>
        <tr>
            <td class="font-monospace small"><?= e($r['nik']) ?></td>
            <td><?= e($r['nama']) ?></td>
            <td><?= e($r['kecamatan']) ?></td>
            <td><span class="badge <?= $ok ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e($r['terisi']) ?>/<?= $jmlKrit ?></span></td>
            <td><?= $r['id_cluster'] ? cluster_badge($r['id_cluster'], $r['nama_cluster']) : '<span class="text-body-secondary">-</span>' ?></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('kuesioner', ['aksi' => 'form', 'nik' => $r['nik']])) ?>">Kuesioner</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('kk', ['aksi' => 'form', 'nik' => $r['nik']])) ?>">Ubah</a>
                <?php if (is_admin()): ?>
                    <form method="post" action="<?= e(url('kk', ['aksi' => 'hapus'])) ?>" class="d-inline" data-confirm="Hapus <?= e($r['nama']) ?> beserta nilai kuesionernya?">
                        <?= csrf_field() ?><input type="hidden" name="nik" value="<?= e($r['nik']) ?>">
                        <button class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-body-secondary">Belum ada data.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div>
