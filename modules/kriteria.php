<?php
$pdo  = db();
$aksi = $_GET['aksi'] ?? 'list';

if ($aksi === 'hapus') {
    verify_csrf();
    $pdo->prepare('DELETE FROM tb_kriteria WHERE id_kriteria = ?')->execute([(int) ($_POST['id'] ?? 0)]);
    flash('success', 'Kriteria dihapus beserta nilai kuesioner dan centroid awalnya.');
    redirect('kriteria');
}

if ($aksi === 'form') {
    $id  = (int) ($_GET['id'] ?? 0);
    $row = ['kode' => '', 'nama' => '', 'keterangan' => '', 'urutan' => (int) $pdo->query('SELECT COALESCE(MAX(urutan),0)+1 FROM tb_kriteria')->fetchColumn()];
    if ($id) {
        $st = $pdo->prepare('SELECT * FROM tb_kriteria WHERE id_kriteria = ?');
        $st->execute([$id]);
        $row = $st->fetch() ?: redirect('kriteria');
    }
    $errors = [];
    if (is_post()) {
        verify_csrf();
        $row = [
            'kode'       => strtoupper(trim((string) ($_POST['kode'] ?? ''))),
            'nama'       => trim((string) ($_POST['nama'] ?? '')),
            'keterangan' => trim((string) ($_POST['keterangan'] ?? '')),
            'urutan'     => (int) ($_POST['urutan'] ?? 0),
        ];
        if (!preg_match('/^[A-Z0-9]{1,5}$/', $row['kode'])) $errors['kode'] = 'Kode 1–5 huruf/angka, mis. K15.';
        if ($row['nama'] === '' || mb_strlen($row['nama']) > 100) $errors['nama'] = 'Nama wajib diisi (maks. 100 karakter).';
        if (!$errors) {
            $params = [$row['kode'], $row['nama'], $row['keterangan'] ?: null, $row['urutan']];
            if ($id) {
                $pdo->prepare('UPDATE tb_kriteria SET kode=?, nama=?, keterangan=?, urutan=? WHERE id_kriteria=?')->execute([...$params, $id]);
                flash('success', 'Kriteria diperbarui.');
            } else {
                $pdo->prepare('INSERT INTO tb_kriteria (kode, nama, keterangan, urutan) VALUES (?,?,?,?)')->execute($params);
                flash('warning', 'Kriteria ditambahkan. Lengkapi centroid awalnya di menu Cluster dan nilai kuesioner setiap KK sebelum menghitung ulang.');
            }
            redirect('kriteria');
        }
    }
    $field = function (string $name, string $label, string $type = 'text', array $attr = []) use ($row, $errors) {
        $a = '';
        foreach ($attr as $k => $v) $a .= ' ' . $k . '="' . e($v) . '"';
        echo '<div class="mb-3"><label class="form-label" for="' . $name . '">' . e($label) . '</label>'
            . '<input id="' . $name . '" name="' . $name . '" type="' . $type . '" class="form-control' . (isset($errors[$name]) ? ' is-invalid' : '') . '" value="' . e($row[$name]) . '"' . $a . '>'
            . (isset($errors[$name]) ? '<div class="invalid-feedback">' . e($errors[$name]) . '</div>' : '') . '</div>';
    }; ?>
    <div class="card" style="max-width: 620px"><div class="card-body">
        <form method="post">
            <?= csrf_field() ?>
            <?php
            $field('kode', 'Kode', 'text', ['maxlength' => 5, 'required' => 'required']);
            $field('nama', 'Nama kriteria', 'text', ['maxlength' => 100, 'required' => 'required']);
            $field('keterangan', 'Keterangan / panduan penilaian (opsional)', 'text', ['maxlength' => 255]);
            $field('urutan', 'Urutan tampil', 'number', ['min' => 0]);
            ?>
            <button class="btn btn-primary">Simpan</button>
            <a class="btn btn-link" href="<?= e(url('kriteria')) ?>">Batal</a>
        </form>
    </div></div>
    <?php return;
}

$rows = get_kriteria();
?>
<p class="text-body-secondary">Setiap kriteria dinilai dengan skala 0–1 (0 = sangat mampu, 1 = sangat tidak mampu).</p>
<a class="btn btn-primary mb-3" href="<?= e(url('kriteria', ['aksi' => 'form'])) ?>"><i class="bi bi-plus-lg"></i> Tambah kriteria</a>
<div class="card"><div class="card-body table-scroll">
<table class="table table-hover align-middle mb-0">
    <thead><tr><th>Kode</th><th>Kriteria</th><th>Keterangan</th><th class="text-end">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e($r['kode']) ?></td>
            <td><?= e($r['nama']) ?></td>
            <td class="small text-body-secondary"><?= e($r['keterangan'] ?? '') ?></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('kriteria', ['aksi' => 'form', 'id' => $r['id_kriteria']])) ?>">Ubah</a>
                <form method="post" action="<?= e(url('kriteria', ['aksi' => 'hapus'])) ?>" class="d-inline"
                      data-confirm="Hapus kriteria <?= e($r['nama']) ?>? Semua nilai kuesioner dan centroid untuk kriteria ini ikut terhapus.">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($r['id_kriteria']) ?>">
                    <button class="btn btn-sm btn-outline-danger">Hapus</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div></div>
