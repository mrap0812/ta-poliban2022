<?php
$pdo   = db();
$input = load_kmeans_input();
$kriteria = $input['kriteria'];
$clusters = $input['clusters'];
$ready = count($input['points']) >= count($clusters) && !$input['centroid_missing'] && $clusters;
$aksi  = is_post() ? ($_POST['aksi'] ?? '') : '';
$result = null;

if ($aksi !== '') {
    verify_csrf();
    if (!$ready) {
        flash('danger', 'Data belum siap untuk dihitung.');
        redirect('kalkulasi');
    }
    $result = kmeans_run($input['points'], $input['centroids'], 100);
    $labelMap = kmeans_label_by_severity($result['centroids'], array_column($clusters, 'urutan_keparahan', 'id_cluster'));

    if ($aksi === 'simpan') {
        require_admin();
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO tb_perhitungan (jumlah_data, jumlah_iterasi, konvergen, sse, dijalankan_oleh) VALUES (?,?,?,?,?)')
            ->execute([count($input['points']), count($result['iterations']), (int) $result['converged'], $result['sse'], current_user()['id_user']]);
        $idp = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO tb_hasil (id_perhitungan, nik, id_cluster, jarak) VALUES (?,?,?,?)');
        foreach ($result['assignment'] as $key => $cid) {
            $ins->execute([$idp, substr((string) $key, 1), $labelMap[$cid], $result['distances'][$key]]);
        }
        $pdo->commit();
        flash('success', 'Hasil perhitungan #' . $idp . ' disimpan.');
        redirect('laporan');
    }
}

$nama = [];
$st = $pdo->query('SELECT nik, nama FROM tb_kepala_keluarga');
foreach ($st as $r) $nama['n' . $r['nik']] = $r['nama'];
$excluded = $input['total_kk'] - count($input['points']);
?>
<div class="card mb-3"><div class="card-body">
    <h2 class="h6">Kesiapan data</h2>
    <ul class="mb-3">
        <li><?= count($input['points']) ?> kepala keluarga dengan kuesioner lengkap akan dihitung
            <?php if ($excluded > 0): ?>
                (<a href="<?= e(url('kuesioner', ['status' => 'belum'])) ?>"><?= $excluded ?> belum lengkap</a> tidak diikutkan)
            <?php endif; ?></li>
        <li><?= count($kriteria) ?> kriteria, <?= count($clusters) ?> cluster (K = <?= count($clusters) ?>)</li>
        <?php if ($input['centroid_missing']): ?>
            <li class="text-danger">Centroid awal belum lengkap untuk: <?= e(implode(', ', $input['centroid_missing'])) ?>
                <?php if (is_admin()): ?>— <a href="<?= e(url('cluster')) ?>">lengkapi</a><?php endif; ?></li>
        <?php endif; ?>
    </ul>
    <form method="post" class="d-flex flex-wrap gap-2">
        <?= csrf_field() ?>
        <button name="aksi" value="hitung" class="btn btn-outline-primary" <?= $ready ? '' : 'disabled' ?>><i class="bi bi-calculator"></i> Hitung &amp; tampilkan proses</button>
        <?php if (is_admin()): ?>
            <button name="aksi" value="simpan" class="btn btn-primary" <?= $ready ? '' : 'disabled' ?>><i class="bi bi-save"></i> Hitung &amp; simpan hasil</button>
        <?php endif; ?>
    </form>
</div></div>

<?php if ($result):
    $final = $result['centroids'];
    // Tampilkan centroid akhir berurutan dari yang paling rentan.
    uksort($final, fn ($a, $b) => $clusters[$labelMap[$a]]['urutan_keparahan'] <=> $clusters[$labelMap[$b]]['urutan_keparahan']);
    $relabeled = array_filter($labelMap, fn ($to, $from) => $to !== $from, ARRAY_FILTER_USE_BOTH);
?>
<div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="card"><div class="card-body"><div class="small text-body-secondary">Iterasi</div><div class="fs-4"><?= count($result['iterations']) ?></div></div></div></div>
    <div class="col-sm-4"><div class="card"><div class="card-body"><div class="small text-body-secondary">Status</div><div class="fs-4"><?= $result['converged'] ? 'Konvergen' : 'Batas iterasi' ?></div></div></div></div>
    <div class="col-sm-4"><div class="card"><div class="card-body"><div class="small text-body-secondary">SSE (jumlah kuadrat jarak)</div><div class="fs-4"><?= fmt_num($result['sse']) ?></div></div></div></div>
</div>

<?php if ($relabeled): ?>
    <div class="alert alert-info">
        Setelah konvergen, posisi centroid bergeser sehingga nomor cluster awal tidak lagi sesuai tingkat kerentanannya.
        Label disesuaikan berdasarkan rata-rata skor centroid:
        <?php foreach ($relabeled as $from => $to): ?>
            <span class="text-nowrap"><?= e($from) ?> → <?= cluster_badge($to, $clusters[$to]['nama']) ?></span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header">Centroid akhir</div>
    <div class="card-body table-scroll">
        <table class="table table-sm table-nowrap mb-0">
            <thead><tr><th>Cluster</th><?php foreach ($kriteria as $k): ?><th class="text-end" title="<?= e($k['nama']) ?>"><?= e($k['kode']) ?></th><?php endforeach; ?><th class="text-end">Rata-rata</th></tr></thead>
            <tbody>
            <?php foreach ($final as $cid => $vec): ?>
                <tr><td><?= cluster_badge($labelMap[$cid], $clusters[$labelMap[$cid]]['nama']) ?></td>
                    <?php foreach ($vec as $v): ?><td class="text-end"><?= fmt_num($v) ?></td><?php endforeach; ?>
                    <td class="text-end fw-semibold"><?= fmt_num(array_sum($vec) / count($vec)) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Hasil pengelompokan (iterasi terakhir)</div>
    <div class="card-body table-scroll">
        <?php $last = end($result['iterations']); ?>
        <table class="table table-sm table-nowrap mb-0">
            <thead><tr><th>Nama</th><?php foreach ($final as $cid => $_): ?><th class="text-end">Jarak ke <?= e($clusters[$labelMap[$cid]]['nama']) ?></th><?php endforeach; ?><th>Cluster</th></tr></thead>
            <tbody>
            <?php foreach ($last['assignment'] as $key => $cid): ?>
                <tr><td><?= e($nama[$key] ?? substr((string) $key, 1)) ?></td>
                    <?php foreach ($final as $c => $_): $d = $last['distances'][$key][$c]; ?><td class="text-end <?= $c === $cid ? 'cell-min' : '' ?>"><?= fmt_num($d) ?></td><?php endforeach; ?>
                    <td><?= cluster_badge($labelMap[$cid], $clusters[$labelMap[$cid]]['nama']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<h2 class="h5">Rincian setiap iterasi</h2>
<p class="small text-body-secondary">Kode cluster di bawah adalah nomor mentah selama iterasi (sebelum pelabelan berdasarkan keparahan).</p>
<?php foreach ($result['iterations'] as $i => $it): ?>
    <details class="card mb-2" <?= $i === 0 ? 'open' : '' ?>>
        <summary class="card-header">Iterasi <?= $i + 1 ?></summary>
        <div class="card-body table-scroll">
            <p class="small text-body-secondary mb-1">Centroid yang dipakai pada iterasi ini:</p>
            <table class="table table-sm table-nowrap">
                <thead><tr><th>Cluster</th><?php foreach ($kriteria as $k): ?><th class="text-end"><?= e($k['kode']) ?></th><?php endforeach; ?></tr></thead>
                <tbody><?php foreach ($it['centroids'] as $cid => $vec): ?>
                    <tr><td><?= e($cid) ?></td><?php foreach ($vec as $v): ?><td class="text-end"><?= fmt_num($v) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?></tbody>
            </table>
            <p class="small text-body-secondary mb-1">Jarak Euclidean setiap data ke centroid (sel hijau = terdekat):</p>
            <table class="table table-sm table-nowrap mb-0">
                <thead><tr><th>Nama</th><?php foreach ($it['centroids'] as $cid => $_): ?><th class="text-end"><?= e($cid) ?></th><?php endforeach; ?><th>Masuk</th></tr></thead>
                <tbody><?php foreach ($it['assignment'] as $key => $cid): ?>
                    <tr><td><?= e($nama[$key] ?? '') ?></td>
                        <?php foreach ($it['distances'][$key] as $c => $d): ?><td class="text-end <?= $c === $cid ? 'cell-min' : '' ?>"><?= fmt_num($d) ?></td><?php endforeach; ?>
                        <td><?= e($cid) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table>
        </div>
    </details>
<?php endforeach; ?>
<?php endif; ?>
