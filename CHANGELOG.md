# Catatan Perubahan

## 2.0.0 — Perbaikan pasca-kuliah (2026)

Repositori 2022 hanya berisi kerangka halaman (layout, login, dashboard) dan dump basis data. Modul kuesioner, kalkulasi, CRUD, konfigurasi, dan aset yang dirujuk `index.php` tidak ikut ter-commit, sehingga aplikasinya tidak bisa dijalankan dari repo. Versi ini membangun ulang seluruh modul dengan metode yang sama, lalu memperbaiki kekurangan berikut.

### Keamanan

- **Login sebelumnya tidak memeriksa password sama sekali.** `proses_login.php` menghitung `MD5(password)` tetapi tidak pernah membandingkannya, jadi username yang benar saja sudah cukup untuk masuk. Password juga tersimpan sebagai teks polos. Sekarang password di-hash dengan bcrypt (`password_hash` / `password_verify`).
- Semua query memakai *prepared statement* PDO. Sebelumnya input login disisipkan langsung ke SQL dan rentan SQL injection.
- Semua keluaran di-escape (`htmlspecialchars`) untuk mencegah XSS.
- Token CSRF dipasang di semua formulir. Aksi hapus dan logout hanya menerima POST.
- Hak akses per peran ditegakkan di server. Sebelumnya peran hanya dipakai untuk redirect setelah login.
- `session_regenerate_id` dijalankan setelah login, cookie sesi memakai `HttpOnly` dan `SameSite`, header keamanan dasar ditambahkan, dan percobaan login dibatasi per sesi (pembatas sederhana, bukan pengganti rate-limit di server).
- Pemeriksaan sesi yang memakai `header()` tanpa `exit` diperbaiki, karena halaman tetap tereksekusi setelah redirect.
- Ekspor CSV dilindungi dari *formula injection*.
- `.htaccess` menolak akses langsung ke folder internal dan file `.sql`.

### Algoritma

- K-Means dipisahkan menjadi fungsi murni (`includes/kmeans.php`) yang bisa diuji tanpa basis data.
- **Label cluster ditentukan dari rata-rata skor centroid akhir**, bukan dari nomor centroid awal. Dengan data uji 2022, nomor mentah membuat keluarga paling rentan berlabel "Tidak Miskin" (lihat README, bagian *Catatan metodologi*).
- Cluster kosong tidak lagi menyebabkan pembagian dengan nol.
- Ditambahkan batas iterasi, status konvergen, dan SSE.
- Hanya KK dengan kuesioner lengkap yang ikut dihitung. KK yang belum lengkap dilaporkan terpisah.

### Basis data

- Nilai kuesioner dan centroid dinormalisasi menjadi satu baris per kriteria. Sebelumnya kolom `k1..k14` di-hardcode, sehingga menu tambah kriteria tidak berfungsi.
- Nilai disimpan sebagai `DECIMAL` dengan `CHECK 0..1`, bukan `VARCHAR`.
- NIK disimpan sebagai `CHAR(16)`. NIK asli tidak muat di `INT(11)`.
- Hasil disimpan per perhitungan (`tb_perhitungan`, `tb_hasil`) sehingga ada riwayat.
- Ditambahkan foreign key, `UNIQUE`, dan `ON DELETE` yang konsisten.
- Data contoh diganti nama dan NIK fiktif yang layak; nilai uji 2022 dipertahankan. File `ta_kmeans.sql` lama masih bisa dilihat di riwayat git.

### Fitur & tampilan

- Antarmuka Bootstrap 5 yang responsif, menggantikan template Matrix Admin yang asetnya tidak ada di repo.
- Halaman yang dibangun ulang:
  - dashboard ringkasan;
  - status kelengkapan kuesioner;
  - rincian setiap iterasi;
  - laporan dengan filter dan ekspor CSV / cetak PDF;
  - manajemen pengguna;
  - ganti password.
- Menu dan footer yang rusak diperbaiki. Tautan logout sebelumnya mengarah ke `?page=login.php`.

### Pengembangan

- Uji unit dan uji regresi (`tests/kmeans_test.php`).
- CI di GitHub Actions: lint, uji, dan pemuatan skema.
- Docker Compose untuk menjalankan aplikasi dengan satu perintah.
- Konfigurasi basis data dibaca dari environment variable.

## 1.0.0 — Tugas Akhir (2022)

Versi asli yang diajukan untuk sidang Tugas Akhir D3 Teknik Informatika POLIBAN.
