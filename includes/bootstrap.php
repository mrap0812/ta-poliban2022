<?php
/**
 * Dimuat di awal setiap halaman: sesi aman, helper, autentikasi, dan CSRF.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_NAME', 'SPK Raskin K-Means');

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/kmeans.php';
require_once APP_ROOT . '/includes/data.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('spkraskin');
    session_start();
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

/** Escape keluaran HTML — dipakai untuk SEMUA data yang ditampilkan. */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page, array $params = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $params);
}

function redirect(string $page, array $params = []): never
{
    header('Location: ' . url($page, $params));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------------- Autentikasi ---------------- */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? null) === 'admin';
}

function require_login(): void
{
    if (current_user() === null) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        flash('danger', 'Halaman tersebut hanya untuk admin.');
        redirect('dashboard');
    }
}

/* ---------------- CSRF ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Hentikan request POST yang tidak membawa token CSRF yang benar. */
function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Metode tidak diizinkan.');
    }
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/* ---------------- Domain helper ---------------- */

/** Skala jawaban kuesioner: makin tinggi makin rentan. */
const SKALA_NILAI = [
    '0'    => '0 — Sangat mampu',
    '0.25' => '0,25 — Mampu',
    '0.5'  => '0,5 — Sedang',
    '0.75' => '0,75 — Kurang mampu',
    '1'    => '1 — Sangat tidak mampu',
];

function fmt_num(float|string|null $n, int $dec = 4): string
{
    return $n === null ? '-' : number_format((float) $n, $dec, ',', '.');
}

/** Warna Bootstrap per cluster bawaan (C1 Miskin, C2 Rentan, C3 Tidak Miskin). */
function cluster_color(?string $id): string
{
    return match ($id) {
        'C1'    => 'danger',
        'C2'    => 'warning',
        'C3'    => 'success',
        default => 'secondary',
    };
}

function cluster_badge(?string $id, ?string $nama = null): string
{
    return '<span class="badge text-bg-' . cluster_color($id) . '">' . e($nama ?? $id ?? '-') . '</span>';
}

function valid_nik(string $nik): bool
{
    return (bool) preg_match('/^\d{16}$/', $nik);
}
