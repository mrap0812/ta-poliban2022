<?php
/**
 * Implementasi K-Means (jarak Euclidean) yang murni — tanpa akses database —
 * sehingga bisa diuji terpisah (lihat tests/kmeans_test.php).
 *
 * Alur sama dengan TA 2022: centroid awal ditentukan pengguna (tb_centroid_awal),
 * lalu iterasi "hitung jarak → kelompokkan ke centroid terdekat → hitung ulang
 * centroid" sampai keanggotaan tidak berubah (konvergen) atau batas iterasi.
 */

declare(strict_types=1);

/** Jarak Euclidean antara dua vektor berdimensi sama. */
function kmeans_distance(array $a, array $b): float
{
    $sum = 0.0;
    foreach ($a as $i => $v) {
        $d = $v - $b[$i];
        $sum += $d * $d;
    }
    return sqrt($sum);
}

/**
 * @param array<string, float[]> $points    id_data => vektor nilai
 * @param array<string, float[]> $centroids id_cluster => vektor centroid awal
 * @return array{
 *   iterations: list<array{centroids: array, distances: array, assignment: array}>,
 *   assignment: array<string,string>, distances: array<string,float>,
 *   centroids: array<string,float[]>, converged: bool, sse: float
 * }
 */
function kmeans_run(array $points, array $centroids, int $maxIter = 100): array
{
    if ($points === [] || $centroids === []) {
        throw new InvalidArgumentException('Data dan centroid awal tidak boleh kosong.');
    }
    $dim = count(reset($centroids));
    foreach ([$points, $centroids] as $set) {
        foreach ($set as $id => $vec) {
            if (count($vec) !== $dim) {
                throw new InvalidArgumentException("Dimensi vektor '$id' tidak sama dengan jumlah kriteria ($dim).");
            }
        }
    }
    $points    = array_map('array_values', $points);
    $centroids = array_map('array_values', $centroids);

    $iterations = [];
    $assignment = [];
    $converged  = false;

    for ($iter = 1; $iter <= $maxIter; $iter++) {
        $distances = [];
        $newAssign = [];
        foreach ($points as $pid => $vec) {
            $best = null;
            foreach ($centroids as $cid => $c) {
                $d = kmeans_distance($vec, $c);
                $distances[$pid][$cid] = $d;
                if ($best === null || $d < $distances[$pid][$best]) {
                    $best = $cid; // seri → cluster yang lebih dulu menang (deterministik)
                }
            }
            $newAssign[$pid] = $best;
        }

        $iterations[] = ['centroids' => $centroids, 'distances' => $distances, 'assignment' => $newAssign];

        if ($newAssign === $assignment) {
            $converged = true;
            break;
        }
        $assignment = $newAssign;

        // Hitung ulang centroid = rata-rata anggota. Cluster kosong mempertahankan
        // centroid lamanya (TA 2022 belum menangani kasus ini → pembagian dengan nol).
        foreach ($centroids as $cid => $old) {
            $members = array_keys($assignment, $cid, true);
            if ($members === []) {
                continue;
            }
            $sum = array_fill(0, $dim, 0.0);
            foreach ($members as $pid) {
                foreach ($points[$pid] as $i => $v) {
                    $sum[$i] += $v;
                }
            }
            $centroids[$cid] = array_map(fn ($s) => $s / count($members), $sum);
        }
    }

    $final = end($iterations);
    $own   = [];
    $sse   = 0.0;
    foreach ($final['assignment'] as $pid => $cid) {
        $own[$pid] = $final['distances'][$pid][$cid];
        $sse += $own[$pid] ** 2;
    }

    return [
        'iterations' => $iterations,
        'assignment' => $final['assignment'],
        'distances'  => $own,
        'centroids'  => $final['centroids'],
        'converged'  => $converged,
        'sse'        => $sse,
    ];
}

/**
 * K-Means tidak "tahu" arti cluster; nomor C1/C2/C3 hanya warisan centroid awal
 * dan bisa bergeser selama iterasi. Fungsi ini memetakan cluster akhir ke label
 * berdasarkan rata-rata skor centroid (skor tinggi = lebih rentan).
 *
 * @param array<string,float[]> $centroids   centroid akhir per id_cluster
 * @param array<string,int>     $severityRank id_cluster => urutan keparahan (1 = paling rentan)
 * @return array<string,string> id_cluster hasil iterasi => id_cluster label
 */
function kmeans_label_by_severity(array $centroids, array $severityRank): array
{
    $means = array_map(fn ($c) => array_sum($c) / max(count($c), 1), $centroids);
    $ids   = array_keys($means);
    // Urut menurun berdasarkan rata-rata; seri dipecah dengan id agar deterministik.
    usort($ids, fn ($a, $b) => [$means[$b], $a] <=> [$means[$a], $b]);

    asort($severityRank);
    $labels = array_keys($severityRank);

    $map = [];
    foreach ($ids as $i => $cid) {
        $map[$cid] = $labels[$i] ?? $cid;
    }
    return $map;
}
