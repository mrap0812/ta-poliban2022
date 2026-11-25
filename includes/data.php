<?php
/** Query yang dipakai lebih dari satu modul. */

declare(strict_types=1);

/** @return list<array{id_kriteria:int,kode:string,nama:string,keterangan:?string,urutan:int}> */
function get_kriteria(): array
{
    return db()->query('SELECT * FROM tb_kriteria ORDER BY urutan, id_kriteria')->fetchAll();
}

/** @return array<string,array> id_cluster => baris cluster */
function get_clusters(): array
{
    $rows = db()->query('SELECT * FROM tb_cluster ORDER BY urutan_keparahan')->fetchAll();
    return array_column($rows, null, 'id_cluster');
}

function get_kecamatan(): array
{
    return db()->query('SELECT * FROM tb_kecamatan ORDER BY nama')->fetchAll();
}

/**
 * Siapkan masukan K-Means dari basis data. Hanya kepala keluarga yang sudah
 * mengisi SEMUA kriteria yang ikut dihitung; sisanya dilaporkan terpisah.
 */
function load_kmeans_input(): array
{
    $kriteria = get_kriteria();
    $ids = array_map('intval', array_column($kriteria, 'id_kriteria'));
    $pos = array_flip($ids);

    $points = [];
    $rows = db()->query('SELECT nik, id_kriteria, nilai FROM tb_nilai')->fetchAll();
    foreach ($rows as $r) {
        if (isset($pos[(int) $r['id_kriteria']])) {
            $points['n' . $r['nik']][$pos[(int) $r['id_kriteria']]] = (float) $r['nilai'];
        }
    }
    $complete = [];
    foreach ($points as $key => $vec) {
        if (count($vec) === count($ids)) {
            ksort($vec);
            $complete[$key] = $vec; // prefix 'n' agar NIK tidak diubah PHP menjadi kunci integer
        }
    }

    $centroids = [];
    $rows = db()->query('SELECT id_cluster, id_kriteria, nilai FROM tb_centroid_awal')->fetchAll();
    foreach ($rows as $r) {
        if (isset($pos[(int) $r['id_kriteria']])) {
            $centroids[$r['id_cluster']][$pos[(int) $r['id_kriteria']]] = (float) $r['nilai'];
        }
    }
    $clusters = get_clusters();
    $missing = [];
    foreach ($clusters as $cid => $_) {
        if (count($centroids[$cid] ?? []) !== count($ids)) {
            $missing[] = $cid;
        } else {
            ksort($centroids[$cid]);
        }
    }

    $total = (int) db()->query('SELECT COUNT(*) FROM tb_kepala_keluarga')->fetchColumn();

    return [
        'kriteria'          => $kriteria,
        'clusters'          => $clusters,
        'points'            => $complete,
        'centroids'         => array_intersect_key($centroids, $clusters),
        'centroid_missing'  => $missing,
        'total_kk'          => $total,
    ];
}

function latest_perhitungan(): ?array
{
    $row = db()->query('SELECT p.*, u.nama_lengkap FROM tb_perhitungan p LEFT JOIN tb_user u ON u.id_user = p.dijalankan_oleh ORDER BY id_perhitungan DESC LIMIT 1')->fetch();
    return $row ?: null;
}
