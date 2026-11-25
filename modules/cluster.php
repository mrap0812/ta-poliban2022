<?php
/* Nama cluster dan centroid awal. Nilai centroid awal menentukan titik mulai
 * K-Means; dalam TA 2022 diambil dari data responden yang dianggap mewakili
 * tiap kelompok. */
$pdo      = db();
$kriteria = get_kriteria();
$clusters = get_clusters();

if (is_post()) {
    verify_csrf();
    $nama  = $_POST['nama'] ?? [];
    $nilai = $_POST['nilai'] ?? [];
    $pdo->beginTransaction();
    try {
        $upName = $pdo->prepare('UPDATE tb_cluster SET nama = ?, keterangan = ? WHERE id_cluster = ?');
        $upVal  = $pdo->prepare(
            'INSERT INTO tb_centroid_awal (id_cluster, id_kriteria, nilai) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)'
        );
        foreach ($clusters as $cid => $c) {
            $n = trim((string) ($nama[$cid] ?? ''));
            if ($n === '') throw new InvalidArgumentException("Nama cluster $cid wajib diisi.");
            $upName->execute([$n, trim((string) ($_POST['keterangan'][$cid] ?? '')) ?: null, $cid]);
            foreach ($kriteria as $k) {
                $v = $nilai[$cid][$k['id_kriteria']] ?? '';
                if (!is_numeric($v) || $v < 0 || $v > 1) {
                    throw new InvalidArgumentException("Nilai $cid untuk {$k['kode']} harus angka 0–1.");
                }
                $upVal->execute([$cid, $k['id_kriteria'], (float) $v]);
            }
        }
        $pdo->commit();
        flash('success', 'Cluster dan centroid awal disimpan.');
        redirect('cluster');
    } catch (InvalidArgumentException $ex) {
        $pdo->rollBack();
        echo '<div class="alert alert-danger">' . e($ex->getMessage()) . '</div>';
    }
}

$cent = [];
foreach ($pdo->query('SELECT * FROM tb_centroid_awal') as $r) {
    $cent[$r['id_cluster']][$r['id_kriteria']] = $r['nilai'];
}
?>
<p class="text-body-secondary">
    Urutan keparahan menentukan label akhir: setelah K-Means konvergen, cluster dengan rata-rata skor centroid
    tertinggi diberi label urutan 1 (paling rentan), dan seterusnya.
</p>
<form method="post">
    <?= csrf_field() ?>
    <div class="card mb-3"><div class="card-body table-scroll">
        <table class="table table-sm align-middle table-nowrap">
            <thead>
            <tr><th>Kriteria</th>
                <?php foreach ($clusters as $cid => $c): ?>
                    <th style="min-width: 160px">
                        <?= e($cid) ?> · urutan <?= e($c['urutan_keparahan']) ?>
                        <input name="nama[<?= e($cid) ?>]" class="form-control form-control-sm mt-1" value="<?= e($_POST['nama'][$cid] ?? $c['nama']) ?>" aria-label="Nama cluster <?= e($cid) ?>" required>
                        <input name="keterangan[<?= e($cid) ?>]" class="form-control form-control-sm mt-1" value="<?= e($_POST['keterangan'][$cid] ?? $c['keterangan']) ?>" placeholder="Keterangan" aria-label="Keterangan cluster <?= e($cid) ?>">
                    </th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($kriteria as $k): ?>
                <tr><td><?= e($k['kode'] . ' — ' . $k['nama']) ?></td>
                    <?php foreach ($clusters as $cid => $_):
                        $v = $_POST['nilai'][$cid][$k['id_kriteria']] ?? $cent[$cid][$k['id_kriteria']] ?? ''; ?>
                        <td><input name="nilai[<?= e($cid) ?>][<?= e($k['id_kriteria']) ?>]" type="number" min="0" max="1" step="0.0001"
                                   class="form-control form-control-sm <?= $v === '' ? 'is-invalid' : '' ?>" value="<?= e($v === '' ? '' : (float) $v) ?>"
                                   aria-label="<?= e("$cid {$k['kode']}") ?>" required></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <button class="btn btn-primary">Simpan centroid awal</button>
</form>
