-- =====================================================================
-- SPK Raskin K-Means — skema basis data (versi perbaikan pasca-kuliah)
-- Kompatibel MariaDB 10.4+ / MySQL 8.0+
--
-- Perubahan utama dari skema TA 2022 (ta_kmeans.sql):
--  * Nilai kuesioner dan centroid dinormalisasi (1 baris per kriteria),
--    sehingga menambah/menghapus kriteria tidak perlu mengubah struktur tabel
--    (dulu kolom k1..k14 di-hardcode).
--  * Nilai disimpan sebagai DECIMAL dengan CHECK 0..1, bukan VARCHAR.
--  * NIK disimpan sebagai CHAR(16) (NIK asli 16 digit tidak muat di INT).
--  * Password di-hash (bcrypt via password_hash), bukan teks polos.
--  * Hasil perhitungan disimpan per "run" agar riwayat tidak tertimpa.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS tb_hasil;
DROP TABLE IF EXISTS tb_perhitungan;
DROP TABLE IF EXISTS tb_nilai;
DROP TABLE IF EXISTS tb_centroid_awal;
DROP TABLE IF EXISTS tb_cluster;
DROP TABLE IF EXISTS tb_kriteria;
DROP TABLE IF EXISTS tb_kepala_keluarga;
DROP TABLE IF EXISTS tb_kecamatan;
DROP TABLE IF EXISTS tb_user;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE tb_user (
  id_user      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username     VARCHAR(50)  NOT NULL,
  password     VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(100) NOT NULL,
  role         ENUM('admin','surveyor') NOT NULL DEFAULT 'surveyor',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_user),
  UNIQUE KEY uq_user_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tb_kecamatan (
  id_kecamatan INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama         VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_kecamatan),
  UNIQUE KEY uq_kecamatan_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tb_kepala_keluarga (
  nik          CHAR(16)     NOT NULL,
  nama         VARCHAR(100) NOT NULL,
  alamat       VARCHAR(255) NULL,
  id_kecamatan INT UNSIGNED NOT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (nik),
  KEY idx_kk_kecamatan (id_kecamatan),
  CONSTRAINT fk_kk_kecamatan FOREIGN KEY (id_kecamatan)
    REFERENCES tb_kecamatan (id_kecamatan) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tb_kriteria (
  id_kriteria INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode        VARCHAR(5)   NOT NULL,
  nama        VARCHAR(100) NOT NULL,
  keterangan  VARCHAR(255) NULL,
  urutan      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id_kriteria),
  UNIQUE KEY uq_kriteria_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- urutan_keparahan: 1 = paling rentan (Miskin). Dipakai untuk memberi label
-- cluster akhir berdasarkan rata-rata skor centroid, bukan sekadar nomor awal.
CREATE TABLE tb_cluster (
  id_cluster        VARCHAR(5)  NOT NULL,
  nama              VARCHAR(50) NOT NULL,
  urutan_keparahan  SMALLINT UNSIGNED NOT NULL,
  keterangan        VARCHAR(255) NULL,
  PRIMARY KEY (id_cluster),
  UNIQUE KEY uq_cluster_urutan (urutan_keparahan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tb_centroid_awal (
  id_cluster  VARCHAR(5)   NOT NULL,
  id_kriteria INT UNSIGNED NOT NULL,
  nilai       DECIMAL(5,4) NOT NULL,
  PRIMARY KEY (id_cluster, id_kriteria),
  CONSTRAINT fk_ca_cluster  FOREIGN KEY (id_cluster)  REFERENCES tb_cluster (id_cluster)   ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ca_kriteria FOREIGN KEY (id_kriteria) REFERENCES tb_kriteria (id_kriteria) ON DELETE CASCADE,
  CONSTRAINT ck_ca_nilai CHECK (nilai BETWEEN 0 AND 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tb_nilai (
  nik         CHAR(16)     NOT NULL,
  id_kriteria INT UNSIGNED NOT NULL,
  nilai       DECIMAL(5,4) NOT NULL,
  diisi_oleh  INT UNSIGNED NULL,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (nik, id_kriteria),
  KEY idx_nilai_kriteria (id_kriteria),
  CONSTRAINT fk_nilai_kk       FOREIGN KEY (nik)         REFERENCES tb_kepala_keluarga (nik) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_nilai_kriteria FOREIGN KEY (id_kriteria) REFERENCES tb_kriteria (id_kriteria) ON DELETE CASCADE,
  CONSTRAINT fk_nilai_user     FOREIGN KEY (diisi_oleh)  REFERENCES tb_user (id_user) ON DELETE SET NULL,
  CONSTRAINT ck_nilai_nilai CHECK (nilai BETWEEN 0 AND 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tb_perhitungan (
  id_perhitungan INT UNSIGNED NOT NULL AUTO_INCREMENT,
  jumlah_data    INT UNSIGNED NOT NULL,
  jumlah_iterasi INT UNSIGNED NOT NULL,
  konvergen      TINYINT(1)   NOT NULL,
  sse            DECIMAL(12,6) NOT NULL,
  dijalankan_oleh INT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_perhitungan),
  CONSTRAINT fk_ph_user FOREIGN KEY (dijalankan_oleh) REFERENCES tb_user (id_user) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tb_hasil (
  id_perhitungan INT UNSIGNED NOT NULL,
  nik            CHAR(16)     NOT NULL,
  id_cluster     VARCHAR(5)   NOT NULL,
  jarak          DECIMAL(10,6) NOT NULL,
  PRIMARY KEY (id_perhitungan, nik),
  KEY idx_hasil_nik (nik),
  KEY idx_hasil_cluster (id_cluster),
  CONSTRAINT fk_hasil_ph      FOREIGN KEY (id_perhitungan) REFERENCES tb_perhitungan (id_perhitungan) ON DELETE CASCADE,
  CONSTRAINT fk_hasil_kk      FOREIGN KEY (nik)        REFERENCES tb_kepala_keluarga (nik) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_hasil_cluster FOREIGN KEY (id_cluster) REFERENCES tb_cluster (id_cluster) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
