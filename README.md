# SPK Raskin K-Means

**Rancang Bangun Sistem Pendukung Pengelompokan Keputusan Kelayakan Penerima Beras Miskin dengan Metode K-Means Berbasis Website**

Tugas Akhir D3 Teknik Informatika, Politeknik Negeri Banjarmasin (POLIBAN), 2022.
Disusun oleh **Aldy Firdaus** dan **M. Ramadhan Adi Putra**.

Repositori ini menyimpan kenangan proyek tersebut. Kode aslinya tidak lengkap, jadi pada 2026 aplikasinya dibangun ulang dan diperbaiki tanpa mengubah tujuan dan metodenya. Rinciannya ada di [CHANGELOG.md](CHANGELOG.md).

---

## Apa yang dilakukan aplikasi ini

Petugas survei menilai setiap kepala keluarga (KK) dengan 14 kriteria kondisi sosial-ekonomi, seperti luas dan jenis lantai rumah, sumber air minum, penghasilan, dan frekuensi makan. Setiap kriteria diberi skor **0 sampai 1**: makin tinggi skornya, makin rentan keluarga itu. Algoritma **K-Means** lalu mengelompokkan semua KK ke dalam tiga cluster:

| Cluster | Arti |
|---|---|
| **Miskin** | Prioritas utama penerima raskin |
| **Rentan Miskin** | Dipertimbangkan sesuai kuota |
| **Tidak Miskin** | Tidak diprioritaskan |

Aplikasi ini hanya **mendukung** keputusan. Penetapan penerima tetap menjadi kewenangan petugas dan instansi yang berwenang.

### Cara kerja K-Means di sini

1. **Centroid awal.** Setiap cluster punya titik awal: satu vektor berisi 14 nilai, diatur di menu *Cluster & Centroid Awal*.
2. **Hitung jarak.** Untuk setiap KK, hitung jarak Euclidean ke setiap centroid: `d = √Σ(xᵢ − cᵢ)²`.
3. **Kelompokkan.** Setiap KK masuk ke cluster dengan centroid terdekat.
4. **Perbarui centroid.** Centroid baru adalah rata-rata semua anggotanya.
5. **Ulangi** langkah 2–4 sampai tidak ada KK yang berpindah cluster (konvergen), dengan batas 100 iterasi.
6. **Beri label.** Cluster akhir diberi label menurut rata-rata skor centroidnya: rata-rata tertinggi menjadi *Miskin*, lalu *Rentan Miskin*, lalu *Tidak Miskin*. Langkah ini tambahan pasca-kuliah; alasannya dijelaskan di bawah.

Semua iterasi ditampilkan: centroid yang dipakai, jarak setiap KK ke setiap centroid, dan keanggotaan cluster. Hasilnya bisa dicocokkan dengan perhitungan manual di laporan TA.

### Catatan metodologi (temuan saat perbaikan)

K-Means tidak tahu arti sebuah cluster. Nomor C1, C2, dan C3 hanya diwarisi dari centroid awal, dan centroid itu bisa bergeser jauh selama iterasi. Pada data uji TA 2022, centroid awal *Tidak Miskin* (rata-rata skor 0,607) justru lebih tinggi daripada *Rentan Miskin* (0,554). Akibatnya, bila nomor cluster mentah dipakai langsung, keluarga dengan skor kerentanan tertinggi akan berlabel **Tidak Miskin**. Pelabelan berdasarkan rata-rata centroid (langkah 6) memperbaiki hal ini, dan `tests/kmeans_test.php` menguji kasus tersebut.

Asumsi yang perlu dijaga: **semua kriteria harus searah** (skor tinggi = lebih rentan). Kalau ada kriteria yang berlawanan arah, misalnya penghasilan dicatat "1 = penghasilan tinggi", balik dulu skornya saat pengisian kuesioner. Kalau tidak, jarak dan label akan menyesatkan.

---

## Fitur

- Login dengan dua peran:
  - **Admin**: semua fitur.
  - **Surveyor**: input data KK, kuesioner, melihat kalkulasi dan laporan.
- Data kepala keluarga dengan NIK 16 digit, kecamatan, dan status kelengkapan kuesioner.
- Kuesioner penilaian dengan skala 0 / 0,25 / 0,5 / 0,75 / 1 per kriteria.
- Master data: kecamatan, kriteria (bisa ditambah atau dihapus), nama cluster, dan centroid awal.
- Kalkulasi K-Means:
  - pratinjau proses lengkap setiap iterasi;
  - SSE (jumlah kuadrat jarak);
  - simpan hasil dengan riwayat per perhitungan.
- Dashboard: ringkasan, sebaran cluster, dan sebaran per kecamatan.
- Laporan dengan filter kecamatan dan cluster, ekspor **CSV** (terbuka di Excel), serta **cetak / simpan PDF** dari browser.
- Manajemen pengguna dan ganti password.

## Menjalankan

Kebutuhan: PHP 8.1+ dengan ekstensi `pdo_mysql`, serta MariaDB 10.4+ atau MySQL 8.0+.

### Opsi A — XAMPP

1. Salin folder proyek ke `htdocs/ta-poliban2022`.
2. Di phpMyAdmin, buat database `ta_kmeans`, lalu impor `database/schema.sql` disusul `database/seed.sql`.
3. Kredensial bawaan adalah `root` tanpa password di `127.0.0.1`. Kalau berbeda, atur environment variable `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASS`. Nilai bawaannya ada di `config/database.php`.
4. Buka `http://localhost/ta-poliban2022/`.

### Opsi B — Docker

```bash
cp .env.example .env   # lalu ganti password di .env
docker compose up -d
# buka http://localhost:8080
```

### Akun demo

| Username | Password | Peran |
|---|---|---|
| `admin` | `admin123` | admin |
| `surveyor` | `surveyor123` | surveyor |

**Segera ganti kedua password ini** (menu *Profil Saya*) jika aplikasi dipasang di tempat yang bisa diakses orang lain. Semua nama dan NIK di data contoh adalah fiktif. Nilai kuesioner dan centroid awalnya sama dengan data uji TA 2022.

## Pengujian

```bash
php tests/kmeans_test.php
```

Uji unit ini mencakup:

- jarak Euclidean;
- konvergensi;
- cluster kosong;
- validasi masukan;
- pelabelan berdasarkan keparahan;
- uji regresi dengan data TA 2022.

GitHub Actions (`.github/workflows/ci.yml`) menjalankan lint PHP, uji unit, dan pemuatan skema di MariaDB pada setiap push.

## Struktur

```
index.php              front controller + daftar halaman (route whitelist)
login.php, logout.php
config/database.php    koneksi PDO (dibaca dari environment variable)
includes/
  bootstrap.php        sesi, helper, autentikasi, CSRF
  kmeans.php           algoritma K-Means murni (tanpa database, bisa diuji)
  data.php             query bersama
  layout.php           kerangka halaman (Bootstrap 5)
modules/               satu file per menu
database/              schema.sql, seed.sql
tests/                 uji unit + fixture data TA 2022
public/                CSS & JS
```

## Lisensi

MIT. Lihat [LICENSE](LICENSE).
