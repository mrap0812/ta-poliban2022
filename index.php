<?php
/**
 * Front controller. Hanya halaman yang terdaftar di $routes yang bisa dibuka
 * (TA 2022 memakai switch + include dari ?page= tanpa cek peran).
 */

declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

require_login();

$routes = [
    // page        => [file modul,          hanya admin?, judul menu]
    'dashboard' => ['dashboard.php', false, 'Dashboard'],
    'kk'        => ['kepala_keluarga.php', false, 'Data Kepala Keluarga'],
    'kuesioner' => ['kuesioner.php',       false, 'Kuesioner Penilaian'],
    'kalkulasi' => ['kalkulasi.php',       false, 'Kalkulasi K-Means'],
    'laporan'   => ['laporan.php',         false, 'Laporan Hasil'],
    'kecamatan' => ['kecamatan.php',       true,  'Kecamatan'],
    'kriteria'  => ['kriteria.php',        true,  'Kriteria'],
    'cluster'   => ['cluster.php',         true,  'Cluster & Centroid Awal'],
    'user'      => ['user.php',            true,  'Pengguna'],
    'profil'    => ['profil.php',          false, 'Profil Saya'],
];

$page = (string) ($_GET['page'] ?? 'dashboard');
if (!isset($routes[$page])) {
    http_response_code(404);
    $page = 'dashboard';
    flash('warning', 'Halaman tidak ditemukan.');
}
[$file, $adminOnly, $title] = $routes[$page];
if ($adminOnly) {
    require_admin();
}

// Modul dijalankan di dalam buffer agar bisa redirect/ekspor sebelum layout dicetak.
ob_start();
try {
    require APP_ROOT . '/modules/' . $file;
} catch (PDOException $ex) {
    ob_clean();
    error_log((string) $ex);
    $msg = $ex->getCode() === '23000'
        ? 'Data tidak dapat disimpan/dihapus karena bentrok dengan data lain (duplikat atau masih dipakai).'
        : 'Terjadi kesalahan basis data. Detail tercatat di log server.';
    echo '<div class="alert alert-danger">' . e($msg) . '</div>';
}
$content = ob_get_clean();

require APP_ROOT . '/includes/layout.php';
