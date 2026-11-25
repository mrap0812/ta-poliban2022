<?php
$pdo      = db();
$clusters = get_clusters();
$riwayat  = $pdo->query('SELECT p.*, u.nama_lengkap FROM tb_perhitungan p LEFT JOIN tb_user u ON u.id_user = p.dijalankan_oleh ORDER BY id_perhitungan DESC LIMIT 50')->fetchAll();

if (!$riwayat) {
    echo '<div class="alert alert-info">Belum ada hasil tersimpan. Jalankan <a href="' . e(url('kalkulasi')) . '">Kalkulasi K-Means</a> lalu simpan hasilnya.</div>';
    return;
}

$idp  = (int) ($_GET['id'] ?? $riwayat[0]['id_perhitungan']);
$run  = current(array_filter($riwayat, fn ($r) => (int) $r['id_perhitungan'] === $idp)) ?: $riwayat[0];
$idp  = (int) $run['id_perhitungan'];
$fKec = (int) ($_GET['kecamatan'] ?? 0);
$fCl  = (string) ($_GET['cluster'] ?? '');

$sql = 'SELECT kk.nik, kk.nama, kk.alamat, k.nama kecamatan, h.id_cluster, c.nama nama_cluster, h.jarak
        FROM tb_hasil h
        JOIN tb_kepala_keluarga kk ON kk.nik = h.nik
        JOIN tb_kecamatan k ON k.id_kecamatan = kk.id_kecamatan
        JOIN tb_cluster c ON c.id_cluster = h.id_cluster
        WHERE h.id_perhitungan = ?';
$params = [$idp];
if ($fKec) { $sql .= ' AND kk.id_kecamatan = ?'; $params[] = $fKec; }
if (isset($clusters[$fCl])) { $sql .= ' AND h.id_cluster = ?'; $params[] = $fCl; }
$sql .= ' ORDER BY c.urutan_keparahan, k.nama, kk.nama';
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

if (($_GET['format'] ?? '') === 'csv') {
    // Ekspor CSV (dibuka langsung oleh Excel/LibreOffice). Menggantikan ekspor
    // Excel/PDF TA 2022 yang bergantung pada library pihak ketiga.
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="hasil-kmeans-' . $idp . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
    $safe = fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : $v; // cegah CSV formula injection
    fputcsv($out, ['No', 'NIK', 'Nama', 'Alamat', 'Kecamatan', 'Cluster', 'Jarak ke centroid'], ';');
    foreach ($rows as $i => $r) {
        fputcsv($out, array_map($safe, [$i + 1, "\u{200B}" . $r['nik'], $r['nama'], $r['alamat'], $r['kecamatan'], $r['nama_cluster'], fmt_num($r['jarak'], 6)]), ';');
    }
    exit;
}

$count = array_count_values(array_column($rows, 'id_cluster'));
$q = ['id' => $idp, 'kecamatan' => $fKec, 'cluster' => $fCl];
?>
<form method="get" class="row g-2 mb-3 d-print-none">
    <input type="hidden" name="page" value="laporan">
    <div class="col-md-4">
        <label class="form-label small mb-0" for="id">Perhitungan</label>
        <select id="id" name="id" class="form-select">
            <?php foreach ($riwayat as $r): ?>
                <option value="<?= e($r['id_perhitungan']) ?>" <?= (int) $r['id_perhitungan'] === $idp ? 'selected' : '' ?>>
                    #<?= e($r['id_perhitungan']) ?> — <?= e(date('d-m-Y H:i', strtotime($r['created_at']))) ?> (<?= e($r['jumlah_data']) ?> data)
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-0" for="kecamatan">Kecamatan</label>
        <select id="kecamatan" name="kecamatan" class="form-select">
            <option value="0">Semua</option>
            <?php foreach (get_kecamatan() as $k): ?>
                <option value="<?= e($k['id_kecamatan']) ?>" <?= $fKec === (int) $k['id_kecamatan'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-0" for="cluster">Cluster</label>
        <select id="cluster" name="cluster" class="form-select">
            <option value="">Semua</option>
            <?php foreach ($clusters as $cid => $c): ?>
                <option value="<?= e($cid) ?>" <?= $fCl === $cid ? 'selected' : '' ?>><?= e($c['nama']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-secondary w-100">Terapkan</button></div>
</form>

<div class="d-flex flex-wrap gap-2 mb-3 d-print-none">
    <a class="btn btn-success" href="<?= e(url('laporan', $q + ['format' => 'csv'])) ?>"><i class="bi bi-filetype-csv"></i> Unduh CSV (Excel)</a>
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Cetak / simpan PDF</button>
</div>

<div class="card"><div class="card-body">
    <div class="mb-3">
        <h2 class="h5 mb-1">Hasil Pengelompokan Kelayakan Penerima Beras Miskin</h2>
        <div class="small text-body-secondary">
            Perhitungan #<?= e($idp) ?> · <?= e(date('d-m-Y H:i', strtotime($run['created_at']))) ?> · oleh <?= e($run['nama_lengkap'] ?? '-') ?> ·
            <?= e($run['jumlah_iterasi']) ?> iterasi (<?= $run['konvergen'] ? 'konvergen' : 'batas iterasi' ?>) · SSE <?= fmt_num($run['sse']) ?>
        </div>
        <div class="mt-2">
            <?php foreach ($clusters as $cid => $c): ?>
                <?= cluster_badge($cid, $c['nama'] . ': ' . ($count[$cid] ?? 0)) ?>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="table-scroll">
    <table class="table table-sm table-striped align-middle mb-0">
        <thead><tr><th>No</th><th>NIK</th><th>Nama</th><th>Kecamatan</th><th>Cluster</th><th class="text-end">Jarak</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
            <tr><td><?= $i + 1 ?></td><td class="font-monospace small"><?= e($r['nik']) ?></td><td><?= e($r['nama']) ?></td>
                <td><?= e($r['kecamatan']) ?></td><td><?= cluster_badge($r['id_cluster'], $r['nama_cluster']) ?></td>
                <td class="text-end"><?= fmt_num($r['jarak']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-body-secondary">Tidak ada data untuk filter ini.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div></div>
