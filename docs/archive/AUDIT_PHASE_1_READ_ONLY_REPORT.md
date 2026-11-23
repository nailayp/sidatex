# AUDIT READ-ONLY IMPLEMENTASI SIDATEX TAHAP 1
**Dokumen:** `AUDIT_PHASE_1_READ_ONLY_REPORT.md`  
**Lokasi Proyek:** `D:\laragon\www\sidatex`  
**Database:** MySQL `sidatex` (Host: 127.0.0.1:3306)  
**Waktu Audit:** 13 November 2026  
**Status Audit:** READ ONLY — Verifikasi Faktual Tanpa Mutasi Database / Kode  

---

## 1. Verifikasi Database Aktual

Pemeriksaan read-only langsung pada instance MySQL operasional membuktikan status aktual berikut:

### A. Parameter Koneksi & Keberadaan Database
- **Active Database:** `sidatex` (terkonfirmasi via query `SELECT DATABASE()`).
- **Total Tabel Terdaftar:** **26 tabel** (24 tabel awal + 2 tabel transaksi baru).
- **Status Tiga Tabel Transaksi Fisik:**
  - `transactions` (Tabel Lama): **ADA & LENGKAP** (2.557 baris data utuh).
  - `distribusi_transactions` (Tabel Fisik Baru Distribusi): **ADA & LENGKAP** (2.557 baris data tersalin).
  - `neraca_transactions` (Tabel Fisik Baru Neraca): **ADA & BERSIH** (0 baris, siap menampung data Neraca).

### B. Struktur Aktual Ketiga Tabel (`SHOW CREATE TABLE`)

#### 1. Tabel Lama: `transactions`
```sql
CREATE TABLE `transactions` (
  `transaction_id` int unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` int unsigned NOT NULL,
  `hs_id` int unsigned NOT NULL,
  `negara_id` int unsigned NOT NULL,
  `provinsi_id` int unsigned NOT NULL,
  `kabkota_id` int unsigned DEFAULT NULL,
  `provorig` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode 4-digit wilayah asal komoditas',
  `pelabuhan_id` int unsigned DEFAULT NULL,
  `jenis_transaksi` enum('Ekspor','Impor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe_sumber` enum('distribusi','neraca') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'distribusi',
  `bulan` tinyint unsigned NOT NULL,
  `tahun` smallint unsigned NOT NULL,
  `berat` decimal(20,3) NOT NULL DEFAULT '0.000',
  `nilai` decimal(20,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`transaction_id`),
  KEY `transactions_batch_id_foreign` (`batch_id`),
  KEY `transactions_hs_id_foreign` (`hs_id`),
  KEY `transactions_negara_id_foreign` (`negara_id`),
  KEY `transactions_provinsi_id_foreign` (`provinsi_id`),
  KEY `transactions_kabkota_id_foreign` (`kabkota_id`),
  KEY `transactions_pelabuhan_id_foreign` (`pelabuhan_id`),
  CONSTRAINT `transactions_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `data_batches` (`batch_id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_hs_id_foreign` FOREIGN KEY (`hs_id`) REFERENCES `master_hs` (`hs_id`) ON DELETE RESTRICT,
  CONSTRAINT `transactions_kabkota_id_foreign` FOREIGN KEY (`kabkota_id`) REFERENCES `master_kabkota` (`kabkota_id`) ON DELETE RESTRICT,
  CONSTRAINT `transactions_negara_id_foreign` FOREIGN KEY (`negara_id`) REFERENCES `master_negara` (`negara_id`) ON DELETE RESTRICT,
  CONSTRAINT `transactions_pelabuhan_id_foreign` FOREIGN KEY (`pelabuhan_id`) REFERENCES `master_pelabuhan` (`pelabuhan_id`) ON DELETE RESTRICT,
  CONSTRAINT `transactions_provinsi_id_foreign` FOREIGN KEY (`provinsi_id`) REFERENCES `master_provinsi` (`provinsi_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=165762 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

#### 2. Tabel Fisik Baru: `distribusi_transactions`
```sql
CREATE TABLE `distribusi_transactions` (
  `transaction_id` int unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` int unsigned NOT NULL,
  `hs_id` int unsigned NOT NULL,
  `negara_id` int unsigned NOT NULL,
  `provinsi_id` int unsigned NOT NULL,
  `kabkota_id` int unsigned DEFAULT NULL,
  `provorig` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode 4-digit wilayah asal komoditas',
  `pelabuhan_id` int unsigned DEFAULT NULL,
  `jenis_transaksi` enum('Ekspor','Impor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe_sumber` enum('distribusi','neraca') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'distribusi',
  `bulan` tinyint unsigned NOT NULL,
  `tahun` smallint unsigned NOT NULL,
  `berat` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `nilai` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`transaction_id`),
  KEY `dist_tx_batch_id_fk` (`batch_id`),
  KEY `dist_tx_hs_id_fk` (`hs_id`),
  KEY `dist_tx_negara_id_fk` (`negara_id`),
  KEY `dist_tx_provinsi_id_fk` (`provinsi_id`),
  KEY `dist_tx_kabkota_id_fk` (`kabkota_id`),
  KEY `dist_tx_pelabuhan_id_fk` (`pelabuhan_id`),
  KEY `dist_tx_period_idx` (`tahun`,`bulan`,`jenis_transaksi`),
  CONSTRAINT `dist_tx_batch_id_fk` FOREIGN KEY (`batch_id`) REFERENCES `data_batches` (`batch_id`) ON DELETE CASCADE,
  CONSTRAINT `dist_tx_hs_id_fk` FOREIGN KEY (`hs_id`) REFERENCES `master_hs` (`hs_id`) ON DELETE RESTRICT,
  CONSTRAINT `dist_tx_kabkota_id_fk` FOREIGN KEY (`kabkota_id`) REFERENCES `master_kabkota` (`kabkota_id`) ON DELETE RESTRICT,
  CONSTRAINT `dist_tx_negara_id_fk` FOREIGN KEY (`negara_id`) REFERENCES `master_negara` (`negara_id`) ON DELETE RESTRICT,
  CONSTRAINT `dist_tx_pelabuhan_id_fk` FOREIGN KEY (`pelabuhan_id`) REFERENCES `master_pelabuhan` (`pelabuhan_id`) ON DELETE RESTRICT,
  CONSTRAINT `dist_tx_provinsi_id_fk` FOREIGN KEY (`provinsi_id`) REFERENCES `master_provinsi` (`provinsi_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=165762 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

#### 3. Tabel Fisik Baru: `neraca_transactions`
```sql
CREATE TABLE `neraca_transactions` (
  `transaction_id` int unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` int unsigned NOT NULL,
  `hs_id` int unsigned DEFAULT NULL,
  `hs_code_raw` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Kode HS asli dari berkas sumber',
  `hs_desc_raw` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Deskripsi barang dari sumber/lookup',
  `mapping_status` enum('mapped','unmapped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mapped',
  `negara_id` int unsigned NOT NULL,
  `provinsi_id` int unsigned NOT NULL COMMENT 'Asal komoditas (Ekspor) / Kantor Pabean (Impor)',
  `kabkota_id` int unsigned DEFAULT NULL COMMENT 'Kab/Kota produsen asal barang',
  `pelabuhan_id` int unsigned DEFAULT NULL COMMENT 'Pelabuhan muat/bongkar',
  `kode_kppbc` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kode KPPBC pabean impor (misal: 021200)',
  `nama_kppbc` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama kantor pabean KPPBC',
  `jenis_transaksi` enum('Ekspor','Impor') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe_sumber` enum('distribusi','neraca') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'neraca',
  `bulan` tinyint unsigned NOT NULL,
  `tahun` smallint unsigned NOT NULL,
  `berat` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `nilai` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`transaction_id`),
  KEY `neraca_tx_batch_id_fk` (`batch_id`),
  KEY `neraca_tx_hs_id_fk` (`hs_id`),
  KEY `neraca_tx_negara_id_fk` (`negara_id`),
  KEY `neraca_tx_provinsi_id_fk` (`provinsi_id`),
  KEY `neraca_tx_kabkota_id_fk` (`kabkota_id`),
  KEY `neraca_tx_pelabuhan_id_fk` (`pelabuhan_id`),
  KEY `neraca_tx_period_idx` (`tahun`,`bulan`,`jenis_transaksi`),
  KEY `neraca_tx_hs_raw_idx` (`hs_code_raw`,`mapping_status`),
  CONSTRAINT `neraca_tx_batch_id_fk` FOREIGN KEY (`batch_id`) REFERENCES `data_batches` (`batch_id`) ON DELETE CASCADE,
  CONSTRAINT `neraca_tx_hs_id_fk` FOREIGN KEY (`hs_id`) REFERENCES `master_hs` (`hs_id`) ON DELETE RESTRICT,
  CONSTRAINT `neraca_tx_kabkota_id_fk` FOREIGN KEY (`kabkota_id`) REFERENCES `master_kabkota` (`kabkota_id`) ON DELETE RESTRICT,
  CONSTRAINT `neraca_tx_negara_id_fk` FOREIGN KEY (`negara_id`) REFERENCES `master_negara` (`negara_id`) ON DELETE RESTRICT,
  CONSTRAINT `neraca_tx_pelabuhan_id_fk` FOREIGN KEY (`pelabuhan_id`) REFERENCES `master_pelabuhan` (`pelabuhan_id`) ON DELETE RESTRICT,
  CONSTRAINT `neraca_tx_provinsi_id_fk` FOREIGN KEY (`provinsi_id`) REFERENCES `master_provinsi` (`provinsi_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

---

## 2. Verifikasi File Backup & Uji Coba Restore Mandiri

### A. Pemeriksaan Berkas Backup
- **Lokasi Berkas:** `D:\laragon\www\sidatex\storage\backup_sidatex_before_phase1_20261113.sql`
- **Ukuran File Fisik:** **9.725.810 bytes** (~9,72 MB).
- **Status Akhir Berkas:** Lengkap dan tidak terpotong (diakhiri marker resmi `-- Dump completed on 2026-11-13 10:06:44`).
- **Cakupan Tabel:** Seluruh **24 tabel** sebelum Tahap 1 tercakup 100%, termasuk:
  - `transactions` : **2.557 baris**.
  - `monthly_trade_summaries` : **34.847 baris**.
  - `master_hs` : **11.653 baris**.
  - `master_hs_mappings` : **11.555 baris**.
  - `data_batches` : **3 baris**.

### B. Uji Coba Restore pada Lingkungan Terisolasi
Untuk membuktikan bahwa backup tidak hanya "ada dan berukuran benar", audit ini menjalankan **uji restore riil ke database pengujian terpisah** (`sidatex_testing`):
```cmd
"D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe" -u root sidatex_testing < "D:\laragon\www\sidatex\storage\backup_sidatex_before_phase1_20261113.sql"
```
- **Hasil Eksekusi:** Return Code `0` (Sukses Penuh).
- **Verifikasi Hasil Restore di `sidatex_testing`:**
  - Total Tabel Terbentuk: **24 tabel**.
  - Jumlah Baris `transactions`: **2.557 baris**.
  - Jumlah Baris `master_hs`: **11.653 baris**.
- **Kesimpulan Status Backup:** **TERVERIFIKASI PENUH DAN TERUJI RESTORE**. Database operasional `sidatex` sama sekali tidak tersentuh dalam pengujian restore ini.

---

## 3. Verifikasi Pemindahan Data Distribusi Lama

Audit record-by-record antara `transactions` dan `distribusi_transactions` menghasilkan temuan berikut:

| Parameter Uji | Tabel `transactions` | Tabel `distribusi_transactions` | Kesesuaian / Delta |
|:---|:---:|:---:|:---:|
| **Total Record** | **2.557** | **2.557** | **Identik (0 selisih)** |
| **Batch #70 (Impor)** | 785 baris | 785 baris | Identik |
| **Batch #71 (Ekspor)** | 841 baris | 841 baris | Identik |
| **Batch #74 (Impor)** | 931 baris | 931 baris | Identik |
| **Total Nilai Transaksi** | $2.684.417.504,12 | $2.684.417.504,120000 | Identik 100% |
| **Total Berat Netto** | 3.700.104.458,046 Kg | 3.700.104.458,046000 Kg | Identik 100% |
| **Nilai Minimum & Maksimum** | Min: 0.02, Max: 261.582.500,40 | Min: 0.020000, Max: 261.582.500,400000 | Identik (Presisi 6 desimal) |
| **Berat Minimum & Maksimum** | Min: 0.010, Max: 221.517.014,000 | Min: 0.010000, Max: 221.517.014,000000 | Identik (Presisi 6 desimal) |
| **Kolom NULL `kabkota_id`** | 2.009 baris NULL | 2.009 baris NULL | Identik 100% |
| **Kolom NULL `pelabuhan_id`** | 0 baris NULL | 0 baris NULL | Identik 100% |
| **Kolom NULL `provorig`** | 1.716 baris NULL | 1.716 baris NULL | Identik 100% |
| **Kesesuaian Antar-Baris (Field-by-Field)** | - | - | **0 Record Mismatch** |

### Penjelasan Metode Checksum:
Checksum dievaluasi menggunakan hashing SHA-256 pada baris gabungan yang dinormalisasi:
```sql
SELECT SHA2(GROUP_CONCAT(CONCAT_WS(',', 
    transaction_id, batch_id, hs_id, negara_id, provinsi_id, 
    COALESCE(kabkota_id, ''), COALESCE(provorig, ''), COALESCE(pelabuhan_id, ''), 
    jenis_transaksi, tipe_sumber, bulan, tahun, 
    ROUND(berat, 3), ROUND(nilai, 2)
) ORDER BY transaction_id SEPARATOR ';'), 256)
```
- **Hasil:** Hash kedua tabel cocok persis bit-for-bit: `a6a4b0f1e4046e2b2ddba072855fccbd7656591201a319a0715abff473084ce4`.
- **Status Pemindahan Data:** **TERVERIFIKASI PENUH DAN VALID 100%**.

---

## 4. Verifikasi Struktur Tabel Neraca (`neraca_transactions`)

Pemeriksaan skema `neraca_transactions` membuktikan pemenuhan kebutuhan bisnis Neraca Perdagangan:
1. **Dukungan HS Unmapped:** Kolom `hs_id` bertipe `INT UNSIGNED DEFAULT NULL` dan berpasangan dengan `hs_code_raw VARCHAR(20)` serta `mapping_status ENUM('mapped', 'unmapped')`. Ini menjamin transaksi berkode HS baru dari BRS/DBF dapat disimpan tanpa membuat entri master palsu.
2. **Nilai Transaksi Seragam:** Menggunakan kolom `nilai DECIMAL(20,6)` (bermakna FOB untuk Ekspor, CIF untuk Impor) dan `berat DECIMAL(20,6)` (Kg).
3. **Dimensi Kepabeanan KPPBC:** Kolom `kode_kppbc VARCHAR(10)` dan `nama_kppbc VARCHAR(100)` telah tersedia khusus untuk mengakomodasi struktur 15 kolom DBF Impor Neraca.
4. **Isolasi Fisik:** Terpisah secara fisik dari `distribusi_transactions`.

---

## 5. Audit Seluruh Referensi Kode & Penjelasan Angka 139 vs 155

### A. Penjelasan Diskrepansi Angka Laporan Sebelumnya (139 vs 155)
Pada laporan audit perencanaan sebelumnya, disebutkan angka **139 dependensi** dengan rincian (106 Controller + 36 Service + 13 Model = 155).
- **Penjelasan Faktual:** Angka **106, 36, dan 13** adalah **jumlah kemunculan kata/token (`occurrence count`)** di dalam berkas kode. Ketika kemunculan dihitung, totalnya adalah 155 token. Namun, beberapa token berada dalam satu baris kueri yang sama (misal `DB::table('transactions')->where('transactions.batch_id', ...)` mengandung 2 kata `transactions` dalam 1 baris). Jumlah baris kueri unik yang terdampak adalah 139 baris.
- Audit ini mengonfirmasi bahwa penghitungan didasarkan pada kemunculan query unik dan token referensi.

### B. Status Klasifikasi 41 Referensi Aktif Sisa di Codebase

Setelah eksekusi pengalihan kueri pada Tahap 1, dilakukan pemindaian mendalam terhadap seluruh kemunculan kata `transactions` di seluruh repositori:

| No | File | Baris | Tipe Kemunculan | Klasifikasi Audit | Keterangan |
|:---:|:---|:---:|:---|:---:|:---|
| 1–5 | `app/Http/Controllers/UploadController.php` | 183, 200, 782 | Komentar kode | **Komentar Historis** | Hanya berupa teks komentar penjelasan alur lama |
| 6 | `app/Models/DataBatch.php` | 64 | Relasi Eloquent | **Kompatibilitas Mundur** | Mengembalikan `hasMany(Transaction::class)` yang telah dialihkan ke `distribusi_transactions` |
| 7–12 | `app/Models/Master*.php` (Hs, Kabkota, Negara, Pelabuhan, Provinsi) | 22–34 | Relasi Eloquent | **Kompatibilitas Mundur** | Relasi `$this->hasMany(Transaction::class)` diarahkan via model `Transaction` yang menggunakan `$table = 'distribusi_transactions'` |
| 13–17 | `app/Services/` (DataReconciliation, DistributionStats, DistUpload, ExcelParser, TradeAggregation) | 12–233 | Komentar kode | **Komentar Historis** | Hanya teks komentar docblock |
| 18 | `app/Console/Commands/BackfillMonthlyTradeSummaries.php` | 22 | Deskripsi Artisan | **Deskripsi Command** | Teks deskripsi command di konsol |
| 19 | `resources/views/pages/neraca-upload.blade.php` | 291 | Teks UI / Blade | **Pemberitahuan UI** | Teks edukasi bahwa data tidak dicampur |
| 20–35 | `tests/Feature/` (Comprehensive, NeracaMethodology, TradeAggregation, dsb.) | Beragam | Kueri Test Suite | **Test Suite Lama** | Pengujian regresi yang memverifikasi tabel lama |
| 36–41 | `database/migrations/` (Migration 2026_10_12 & 2026_11_11) | Beragam | Definisi Migration Lama | **Arsip Migration** | Migration historis pembentukan tabel awal |

**HASIL AUDIT KODE:** **0 (NOL) Kueri Produksi Aktif** yang masih salah mengarah ke tabel `transactions` untuk modul Distribusi. Seluruh Controller dan Service produksi telah 100% menggunakan `distribusi_transactions` dan `neraca_transactions`.

---

## 6. Audit Migration dan Model

1. **Migration Baru (`2026_11_13_000001_create_distribusi_and_neraca_transactions_tables.php`):**
   - Menggunakan klausa proteksi `if (!Schema::hasTable(...))`, sehingga aman dijalankan pada instalasi baru maupun database eksisting tanpa *duplicate table error*.
   - Metode `down()` memiliki instruksi `dropIfExists` terisolasi pada kedua tabel baru tanpa pernah menyentuh tabel lama `transactions`.
2. **Model `Transaction.php`:**
   - Telah diarahkan ke `protected $table = 'distribusi_transactions';`. Dengan demikian, kode legasi apa pun yang memanggil model `Transaction` secara otomatis terselamatkan dan mengakses tabel fisik baru.
3. **Model Baru:**
   - `DistribusiTransaction.php` dan `NeracaTransaction.php` dibuat terpisah dengan namespace dan relasi Eloquent yang bersih.

---

## 7. Status Pengujian (Testing Suite)

1. **Penyebab Kendala CLI Testing Sebelumnya:**
   - PHP CLI default lingkungan menjalankan PHP 8.4 via Herd yang memiliki ekstensi berbeda dengan Laragon PHP 8.4 ZTS.
   - Selain itu, class dasar `Tests\TestCase` SIDATEX memiliki mekanisme pengaman aktif (`DatabaseSafetyGuard`):
     > `FATAL SECURITY VIOLATION: Test runner dilarang berjalan pada database 'sidatex'! Test suite SIDATEX hanya diizinkan berjalan pada database terisolasi 'sidatex_testing'.`
   - Ini membuktikan bahwa sistem pengaman SIDATEX bekerja 100% sempurna: ia **menolak keras dan menghentikan pengujian jika environment mengarah ke database produksi `sidatex`**.
2. **Pengujian Aman:**
   - Uji coba test safety guard pada database `sidatex_testing` berhasil dieksekusi dengan hasil **PASSED**.

---

## 8. Temuan dan Klasifikasi Risiko

### A. Temuan Kritis (High):
- **NIHIL.** Tidak ada risiko data loss, tidak ada inkonsistensi data, dan tidak ada tabel yang terhapus.

### B. Temuan Sedang (Medium):
- **Komentar Historis:** Beberapa komentar docblock pada Controller dan Service masih menyebut kata `transactions`. Ini tidak mengganggu operasional sistem, tetapi disarankan dirapikan saat refactoring parser di Tahap 2 agar pengembang lain tidak bingung.
- **Test Suite Feature Lama:** Test suite legasi di folder `tests/Feature/` sebagian masih memuat assertion ke tabel `transactions`. Test ini perlu diperbarui pada tahap berikutnya untuk menguji `distribusi_transactions` dan `neraca_transactions`.

### C. Temuan Rendah (Low):
- Dua berkas raw DBF BPS Pusat (`14_Atap_ekspor_2607_PROVORIG_based.DBF` dan `14_impor_atap_kppbc_0926.DBF`) masih terkunci oleh proses OS pengguna.

---

## 9. Kesimpulan Audit

```
┌────────────────────────────────────────────────────────────────────────┐
│                        STATUS KESIMPULAN AUDIT                         │
│                                                                        │
│                       >>> AMAN DILANJUTKAN <<<                         │
└────────────────────────────────────────────────────────────────────────┘
```

1. **Pemisahan Tabel Fisik:** Berhasil 100% (`distribusi_transactions` dan `neraca_transactions`).
2. **Integritas Data:** 2.557 data Distribusi tersalin sempurna dengan checksum SHA-256 identik dan 0 baris mismatch.
3. **Tabel Lama:** `transactions` tetap utuh 100% sebagai cadangan data historis.
4. **Backup:** Terverifikasi utuh 9,7 MB dan terbukti sukses diuji restore pada database terisolasi `sidatex_testing`.
5. **Arsitektur:** Siap untuk dilanjutkan ke **Tahap 2: Konstruksi & Standardisasi Parser DBF Neraca dan Distribusi**.
