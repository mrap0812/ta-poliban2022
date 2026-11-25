<?php
/** @var string $content @var string $title @var string $page @var array $routes */
$user = current_user();
$menu = [
    'Utama'    => ['dashboard' => 'bi-speedometer2', 'kk' => 'bi-people', 'kuesioner' => 'bi-ui-checks', 'kalkulasi' => 'bi-diagram-3', 'laporan' => 'bi-file-earmark-bar-graph'],
    'Master'   => ['kecamatan' => 'bi-geo-alt', 'kriteria' => 'bi-list-check', 'cluster' => 'bi-bullseye', 'user' => 'bi-person-gear'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title) ?> — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="public/app.css">
</head>
<body>
<nav class="navbar navbar-dark bg-dark sticky-top d-print-none">
    <div class="container-fluid">
        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand" href="<?= e(url('dashboard')) ?>"><i class="bi bi-basket2"></i> <?= e(APP_NAME) ?></a>
        <div class="dropdown ms-auto">
            <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i> <?= e($user['nama_lengkap']) ?>
                <span class="badge text-bg-secondary"><?= e($user['role']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(url('profil')) ?>"><i class="bi bi-key"></i> Profil &amp; password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="logout.php"><?= csrf_field() ?>
                        <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> Keluar</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="d-flex">
    <aside id="sidebar" class="offcanvas-lg offcanvas-start sidebar bg-body-tertiary border-end d-print-none" tabindex="-1" aria-label="Menu">
        <div class="offcanvas-body flex-column p-3">
            <?php foreach ($menu as $group => $items):
                $visible = array_filter($items, fn ($_, $p) => !$routes[$p][1] || is_admin(), ARRAY_FILTER_USE_BOTH);
                if (!$visible) continue; ?>
                <div class="sidebar-heading"><?= e($group) ?></div>
                <ul class="nav nav-pills flex-column mb-3">
                    <?php foreach ($visible as $p => $icon): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $p === $page ? 'active' : 'link-body-emphasis' ?>" href="<?= e(url($p)) ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>>
                                <i class="bi <?= $icon ?>"></i> <?= e($routes[$p][2]) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </div>
    </aside>

    <main class="flex-grow-1 p-3 p-lg-4 main-content">
        <h1 class="h3 mb-3"><?= e($title) ?></h1>
        <?php foreach (take_flashes() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($f['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        <?php endforeach; ?>

        <?= $content ?>

        <footer class="text-center text-body-secondary small mt-5 pt-3 border-top">
            Tugas Akhir oleh Aldy Firdaus &amp; M. Ramadhan Adi Putra — D3 Teknik Informatika POLIBAN, 2022.
            Diperbarui pasca-kuliah, 2026.
        </footer>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="public/app.js"></script>
</body>
</html>
