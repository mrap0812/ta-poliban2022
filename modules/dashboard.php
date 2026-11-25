<?php
$pdo = db();
$totalKk    = (int) $pdo->query('SELECT COUNT(*) FROM tb_kepala_keluarga')->fetchColumn();
$jmlKrit    = (int) $pdo->query('SELECT COUNT(*) FROM tb_kriteria')->fetchColumn();
$lengkap    = (int) $pdo->query(
    'SELECT COUNT(*) FROM (SELECT nik FROM tb_nilai GROUP BY nik HAVING COUNT(*) >= ' . max($jmlKrit, 1) . ') t'
)->fetchColumn();
$last       = latest_perhitungan();
$clusters   = get_clusters();

$dist = [];
$perKec = [];
if ($last) {
    $st = $pdo->prepare('SELECT id_cluster, COUNT(*) n FROM tb_hasil WHERE id_perhitungan = ? GROUP BY id_cluster');
    $st->execute([$last['id_perhitungan']]);
    $dist = array_column($st->fetchAll(), 'n', 'id_cluster');

    $st = $pdo->prepare(
        'SELECT k.nama kecamatan, h.id_cluster, COUNT(*) n
         FROM tb_hasil h JOIN tb_kepala_keluarga kk ON kk.nik = h.nik
         JOIN tb_kecamatan k ON k.id_kecamatan = kk.id_kecamatan
         WHERE h.id_perhitungan = ? GROUP BY k.nama, h.id_cluster ORDER BY k.nama'
    );
    $st->execute([$last['id_perhitungan']]);
    foreach ($st as $r) {
        $perKec[$r['kecamatan']][$r['id_cluster']] = (int) $r['n'];
    }
}
$hasilTotal = array_sum($dist);
?>
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Kepala keluarga', $totalKk, 'bi-people', url('kk')],
        ['Kuesioner lengkap', $lengkap, 'bi-ui-checks', url('kuesioner')],
        ['Belum lengkap', $totalKk - $lengkap, 'bi-hourglass-split', url('kuesioner', ['status' => 'belum'])],
        ['Kriteria', $jmlKrit, 'bi-list-check', is_admin() ? url('kriteria') : null],
    ] as [$label, $val, $icon, $href]): ?>
        <div class="col-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <div class="text-body-secondary small"><i class="bi <?= $icon ?>"></i> <?= e($label) ?></div>
                    <div class="display-6"><?= e($val) ?></div>
                    <?php if ($href): ?><a href="<?= e($href) ?>" class="stretched-link" aria-label="Buka <?= e($label) ?>"></a><?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Hasil pengelompokan terakhir</div>
            <div class="card-body">
                <?php if (!$last): ?>
                    <p class="text-body-secondary">Belum ada hasil tersimpan.</p>
                    <a class="btn btn-primary" href="<?= e(url('kalkulasi')) ?>"><i class="bi bi-play-fill"></i> Jalankan K-Means</a>
                <?php else: ?>
                    <p class="small text-body-secondary mb-3">
                        <?= e(date('d-m-Y H:i', strtotime($last['created_at']))) ?> oleh <?= e($last['nama_lengkap'] ?? '-') ?> ·
                        <?= e($last['jumlah_data']) ?> data · <?= e($last['jumlah_iterasi']) ?> iterasi
                    </p>
                    <?php foreach ($clusters as $cid => $c):
                        $n = (int) ($dist[$cid] ?? 0);
                        $pct = $hasilTotal ? round($n / $hasilTotal * 100, 1) : 0; ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small">
                                <span><?= cluster_badge($cid, $c['nama']) ?></span>
                                <span><?= $n ?> KK (<?= fmt_num($pct, 1) ?>%)</span>
                            </div>
                            <div class="progress" role="progressbar" aria-label="<?= e($c['nama']) ?>" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-<?= cluster_color($cid) ?>" style="width: <?= $pct ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="<?= e(url('laporan')) ?>">Lihat laporan lengkap &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Sebaran per kecamatan</div>
            <div class="card-body table-scroll">
                <?php if (!$perKec): ?>
                    <p class="text-body-secondary mb-0">Akan tampil setelah ada hasil perhitungan.</p>
                <?php else: ?>
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Kecamatan</th>
                            <?php foreach ($clusters as $cid => $c): ?><th class="text-end"><?= e($c['nama']) ?></th><?php endforeach; ?>
                            <th class="text-end">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($perKec as $kec => $row): ?>
                            <tr><td><?= e($kec) ?></td>
                                <?php foreach ($clusters as $cid => $_): ?><td class="text-end"><?= (int) ($row[$cid] ?? 0) ?></td><?php endforeach; ?>
                                <td class="text-end fw-semibold"><?= array_sum($row) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
