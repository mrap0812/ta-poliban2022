<?php
/**
 * Uji unit K-Means tanpa dependensi (tidak perlu PHPUnit / basis data).
 * Jalankan: php tests/kmeans_test.php
 */

declare(strict_types=1);
require __DIR__ . '/../includes/kmeans.php';

$failed = 0;
function check(string $name, bool $ok): void
{
    global $failed;
    echo ($ok ? "  ok   " : "  GAGAL ") . $name . PHP_EOL;
    if (!$ok) $failed++;
}
function approx(float $a, float $b, float $eps = 1e-9): bool { return abs($a - $b) < $eps; }

echo "kmeans_distance\n";
check('jarak 3-4-5', approx(kmeans_distance([0, 0], [3, 4]), 5.0));
check('jarak ke diri sendiri = 0', approx(kmeans_distance([0.5, 1], [0.5, 1]), 0.0));

echo "kmeans_run: dua kelompok yang jelas terpisah\n";
$pts = ['a' => [0, 0], 'b' => [0, 1], 'c' => [10, 10], 'd' => [10, 11]];
$r = kmeans_run($pts, ['X' => [0, 0], 'Y' => [1, 1]]);
check('konvergen', $r['converged']);
check('a,b satu kelompok', $r['assignment']['a'] === $r['assignment']['b']);
check('c,d satu kelompok', $r['assignment']['c'] === $r['assignment']['d']);
check('a dan c beda kelompok', $r['assignment']['a'] !== $r['assignment']['c']);
check('centroid X = rata-rata (0, 0.5)', approx($r['centroids']['X'][0], 0) && approx($r['centroids']['X'][1], 0.5));
check('SSE = 4 × 0.5²', approx($r['sse'], 1.0));

echo "kmeans_run: cluster kosong tidak menyebabkan pembagian nol\n";
$r = kmeans_run(['a' => [0], 'b' => [1]], ['X' => [0.4], 'Y' => [100]]);
check('Y tetap di posisi awal', approx($r['centroids']['Y'][0], 100.0));
check('semua masuk X', array_unique($r['assignment']) === ['a' => 'X']);

echo "kmeans_run: validasi masukan\n";
foreach ([[[], ['X' => [1]]], [['a' => [1, 2]], ['X' => [1]]]] as [$p, $c]) {
    try { kmeans_run($p, $c); check('menolak masukan tidak valid', false); }
    catch (InvalidArgumentException) { check('menolak masukan tidak valid', true); }
}

echo "kmeans_run: kunci NIK numerik tetap utuh\n";
$r = kmeans_run(['n6371000000000001' => [0], 'n6371000000000002' => [1]], ['X' => [0], 'Y' => [1]]);
check('kunci tetap string berprefiks', array_keys($r['assignment']) === ['n6371000000000001', 'n6371000000000002']);

echo "kmeans_label_by_severity\n";
$map = kmeans_label_by_severity(
    ['C1' => [0.2, 0.2], 'C2' => [0.9, 0.8], 'C3' => [0.5, 0.5]],
    ['C1' => 1, 'C2' => 2, 'C3' => 3]
);
check('rata-rata tertinggi → C1 (paling rentan)', $map['C2'] === 'C1');
check('tengah → C2', $map['C3'] === 'C2');
check('terendah → C3', $map['C1'] === 'C3');

echo "Regresi: data uji TA 2022 (17 KK, 14 kriteria, K=3)\n";
$data = require __DIR__ . '/fixtures/ta2022.php';
$r = kmeans_run($data['points'], $data['centroids']);
check('konvergen', $r['converged']);
check('17 data terkelompok', count($r['assignment']) === 17);
$map = kmeans_label_by_severity($r['centroids'], ['C1' => 1, 'C2' => 2, 'C3' => 3]);
$labels = array_map(fn ($c) => $map[$c], $r['assignment']);
$nik = fn (int $i) => 'n637100' . str_pad((string) $i, 10, '0', STR_PAD_LEFT);
check('4 iterasi (snapshot)', count($r['iterations']) === 4);
check('sebaran 5 Miskin / 5 Rentan / 7 Tidak Miskin (snapshot)', array_count_values($labels) == ['C1' => 5, 'C2' => 5, 'C3' => 7]);
check('KK skor tertinggi (#7, #1) berlabel Miskin', $labels[$nik(7)] === 'C1' && $labels[$nik(1)] === 'C1');
check('KK skor terendah (#4, #15) berlabel Tidak Miskin', $labels[$nik(4)] === 'C3' && $labels[$nik(15)] === 'C3');
// Tanpa pelabelan ulang, nomor cluster mentah membalik makna (bug laten TA 2022):
check('label mentah KK #7 bukan C1 (bukti perlunya relabel)', $r['assignment'][$nik(7)] !== 'C1');
printf("       iterasi=%d, SSE=%.4f, sebaran=%s\n", count($r['iterations']), $r['sse'], json_encode(array_count_values($labels)));

echo PHP_EOL . ($failed ? "$failed uji GAGAL" : 'Semua uji lulus') . PHP_EOL;
exit($failed ? 1 : 0);
