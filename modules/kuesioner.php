<?php
$pdo      = db();
$aksi     = $_GET['aksi'] ?? 'list';
$kriteria = get_kriteria();

if ($aksi === 'form') {
    $nik = (string) ($_GET['nik'] ?? '');
    $st  = $pdo->prepare('SELECT kk.*, k.nama kecamatan FROM tb_kepala_keluarga kk JOIN tb_kecamatan k USING (id_kecamatan) WHERE nik = ?');
    $st->execute([$nik]);
    $kk = $st->fetch() ?: redirect('kuesioner');

    $st = $pdo->prepare('SELECT id_kriteria, nilai FROM tb_nilai WHERE nik = ?');
    $st->execute([$nik]);
    $nilai = array_map(fn ($v) => (string) (float) $v, array_column($st->fetchAll(), 'nilai', 'id_kriteria'));

    $errors = [];
    if (is_post()) {
        verify_csrf();
        $input = $_POST['nilai'] ?? [];
        foreach ($kriteria as $k) {
            $v = (string) ($input[$k['id_kriteria']] ?? '');
            if (!array_key_exists($v, SKALA_NILAI)) {
                $errors[$k['id_kriteria']] = true;
            }
            $nilai[$k['id_kriteria']] = $v;
        }
        if (!$errors) {
            $up = $pdo->prepare(
                'INSERT INTO tb_nilai (nik, id_kriteria, nilai, diisi_oleh) VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), diisi_oleh = VALUES(diisi_oleh)'
            );
            $pdo->beginTransaction();
            foreach ($kriteria as $k) {
                $up->execute([$nik, $k['id_kriteria'], (float) $nilai[$k['id_kriteria']], current_user()['id_user']]);
            }
            $pdo->commit();
            flash('success', 'Kuesioner ' . $kk['nama'] . ' disimpan. Jalankan ulang K-Means agar hasil ikut diperbarui.');
            redirect('kuesioner');
        }
        echo '<div class="alert alert-danger">Semua kriteria wajib dipilih nilainya (' . count($errors) . ' belum diisi).</div>';
    } ?>
    <div class="card mb-3"><div class="card-body py-2">
        <strong><?= e($kk['nama']) ?></strong>
        <span class="text-body-secondary">· NIK <?= e($kk['nik']) ?> · <?= e($kk['kecamatan']) ?></span>
    </div></div>
    <form method="post">
        <?= csrf_field() ?>
        <div class="card mb-3"><div class="card-body">
            <?php foreach ($kriteria as $i => $k): $cur = $nilai[$k['id_kriteria']] ?? null; ?>
                <fieldset class="mb-3 pb-3 border-bottom <?= isset($errors[$k['id_kriteria']]) ? 'text-danger' : '' ?>">
                    <legend class="fs-6 fw-semibold mb-1"><?= $i + 1 ?>. <?= e($k['nama']) ?></legend>
                    <?php if ($k['keterangan']): ?><div class="small text-body-secondary mb-2"><?= e($k['keterangan']) ?></div><?php endif; ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach (SKALA_NILAI as $val => $label): $idf = "n{$k['id_kriteria']}_" . str_replace('.', '_', $val); ?>
                            <input type="radio" class="btn-check" name="nilai[<?= e($k['id_kriteria']) ?>]" id="<?= $idf ?>" value="<?= e($val) ?>" <?= $cur === (string) $val ? 'checked' : '' ?> required>
                            <label class="btn btn-sm btn-outline-primary" for="<?= $idf ?>"><?= e($label) ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>
        </div></div>
        <button class="btn btn-primary">Simpan kuesioner</button>
        <a class="btn btn-link" href="<?= e(url('kuesioner')) ?>">Batal</a>
    </form>
    <?php return;
}

$status = $_GET['status'] ?? 'semua';
$jml    = count($kriteria);
$rows   = $pdo->query(
    'SELECT kk.nik, kk.nama, k.nama kecamatan, COUNT(n.id_kriteria) terisi, MAX(n.updated_at) diperbarui
     FROM tb_kepala_keluarga kk JOIN tb_kecamatan k USING (id_kecamatan)
     LEFT JOIN tb_nilai n ON n.nik = kk.nik
     GROUP BY kk.nik, kk.nama, k.nama ORDER BY terisi ASC, kk.nama'
)->fetchAll();
if ($status === 'belum') $rows = array_filter($rows, fn ($r) => (int) $r['terisi'] < $jml);
if ($status === 'lengkap') $rows = array_filter($rows, fn ($r) => (int) $r['terisi'] >= $jml);
?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <div class="btn-group" role="group" aria-label="Filter status">
        <?php foreach (['semua' => 'Semua', 'belum' => 'Belum lengkap', 'lengkap' => 'Lengkap'] as $s => $l): ?>
            <a class="btn btn-outline-secondary <?= $status === $s ? 'active' : '' ?>" href="<?= e(url('kuesioner', ['status' => $s])) ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
    <input type="search" class="form-control ms-auto" style="max-width: 260px" placeholder="Cari nama / NIK" data-filter="#tabel-kuesioner" aria-label="Cari">
</div>
<div class="card"><div class="card-body table-scroll">
<table id="tabel-kuesioner" class="table table-hover align-middle mb-0">
    <thead><tr><th>Nama</th><th>NIK</th><th>Kecamatan</th><th>Terisi</th><th>Diperbarui</th><th class="text-end">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $ok = (int) $r['terisi'] >= $jml; ?>
        <tr>
            <td><?= e($r['nama']) ?></td>
            <td class="font-monospace small"><?= e($r['nik']) ?></td>
            <td><?= e($r['kecamatan']) ?></td>
            <td><span class="badge <?= $ok ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e($r['terisi']) ?>/<?= $jml ?></span></td>
            <td class="small"><?= $r['diperbarui'] ? e(date('d-m-Y H:i', strtotime($r['diperbarui']))) : '-' ?></td>
            <td class="text-end"><a class="btn btn-sm <?= $ok ? 'btn-outline-secondary' : 'btn-primary' ?>" href="<?= e(url('kuesioner', ['aksi' => 'form', 'nik' => $r['nik']])) ?>"><?= $ok ? 'Ubah' : 'Isi' ?></a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-body-secondary">Tidak ada data.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div>
