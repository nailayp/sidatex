# SIDATEX — PERBANDINGAN STRUKTUR BASIS DATA (PHASE 2G.10.5)
## Schema, Columns, Precision, and Relational Comparison Report

**Aplikasi:** Sistem Informasi Data Ekspor-Impor (SIDATEX) — BPS Provinsi Riau  
**Lokasi Direktori:** `D:\laragon\www\sidatex`  
**Database Dibandingkan:** `sidatex` (Utama) vs `sidatex_testing` (Pengujian Teknis)  
**Tanggal Evaluasi:** 19 November 2026  
**Standar Arsitektur Acuan:** Two-Tier Analytical Mart, DECIMAL(20,6), Laravel 11 Eloquent  

---

## 1. IKHTISAR PERBANDINGAN STRUKTUR GLOBAL

Pemeriksaan komparatif struktur basis data dilakukan dengan mengekstrak skema DDL (*Data Definition Language*), kolom, tipe data, indeks, dan relasi kunci asing (*Foreign Key*) dari kedua database:

```
+---------------------------------------------------------------------------------------------------+
| REKAPITULASI KOMPARASI SKEMA                                                                      |
+---------------------------------------------------------------------------------------------------+
| Total Tabel pada sidatex (Utama)         : 26 Tabel                                               |
| Total Tabel pada sidatex_testing         : 27 Tabel                                               |
| Tabel yang Hilang pada sidatex           : 1 Tabel (brs_indicator_summaries)                       |
| Tabel dengan Kolom Tertinggal            : 1 Tabel (monthly_trade_summaries: kurang 9 kolom)      |
| Tabel dengan Presisi Desimal Tertinggal  : 2 Tabel (monthly_trade_summaries,                      |
|                                                     monthly_origin_trade_summaries)               |
| Tabel Master Data Identik (100% Cocok)   : 7 Tabel Master BPS (Checksum Match)                    |
| Tabel Transaksi Terpasang                : 3 Tabel (distribusi_transactions,                      |
|                                                     neraca_transactions, transactions)            |
+---------------------------------------------------------------------------------------------------+
```

---

## 2. MATRIKS PERBANDINGAN TABEL & KELENGKAPAN SKEMA

| Nama Tabel | Tipe Tabel | Ada di `sidatex` | Ada di `sidatex_testing` | Status Keselarasan dengan Sistem | Keterangan & Tindak Lanjut |
|:---|:---|:---:|:---:|:---:|:---|
| `users` | Sistem / Autentikasi | YA | YA | **SELARAS (100%)** | Struktur dan 3 akun pengguna identik. |
| `roles` | Sistem / Otorisasi | YA | YA | **SELARAS (100%)** | 3 peran (Super Admin, Pegawai, Viewer) identik. |
| `data_batches` | Operasional / Batch | YA | YA | **SELARAS (100%)** | 14 kolom identik, mendukung enum status dan ukuran_file. |
| `distribusi_transactions`| Tier-1 Transaksional | YA | YA | **SELARAS (100%)** | 16 kolom identik, presisi nilai/berat DECIMAL(20,6). |
| `neraca_transactions` | Tier-1 Transaksional | YA | YA | **SELARAS (100%)** | 20 kolom identik, presisi nilai/berat DECIMAL(20,6). |
| `transactions` (Legacy) | Transaksional Phase 1| YA | YA | **SELARAS (100%)** | Arsip data Phase 1 (2.557 baris), read-only. |
| `monthly_trade_summaries` | Tier-1 Mart Granular | YA | YA | **TIDAK SELARAS (Tertinggal)** | Kurang 9 kolom & presisi desimal masih (20,2)/(20,3). |
| `monthly_origin_trade_summaries` | Mart Asal Barang | YA | YA | **TIDAK SELARAS (Presisi)** | Presisi desimal masih (20,2), belum (20,6). |
| `brs_indicator_summaries`| Tier-2 Mart Makro BRS | **TIDAK** | YA | **HILANG DI `sidatex`** | Wajib dibentuk melalui migrasi `2026_11_16_...`. |
| `master_hs` | Master Referensi BPS | YA | YA | **SELARAS (100%)** | 11.653 baris komoditas HS 2022 otentik. |
| `master_hs_mappings` | Master Pemetaan BPS | YA | YA | **SELARAS (100%)** | 11.555 baris pemetaan HS 2017 ke 2022. |
| `master_negara` | Master Referensi BPS | YA | YA | **SELARAS (100%)** | 259 negara tujuan dan asal. |
| `master_provinsi` | Master Referensi BPS | YA | YA | **SELARAS (100%)** | 34 provinsi Indonesia. |
| `master_kabkota` | Master Referensi BPS | YA | YA | **SELARAS (100%)** | 12 kabupaten/kota Riau. |
| `master_pelabuhan` | Master Referensi BPS | YA | YA | **SELARAS (100%)** | 40 pelabuhan laut/udara. |
| `master_wilayah_bps` | Master Referensi BPS | YA | YA | **SELARAS (100%)** | 33 kode wilayah statistik. |
| `comments` | Kolaborasi / Audit | YA | YA | **SELARAS (100%)** | Struktur tabel komentar identik. |
| `activity_logs` | Audit Trail Sistem | YA | YA | **SELARAS (100%)** | Struktur tabel audit log identik. |
| `trash` | Pemulihan Dokumen | YA | YA | **SELARAS (100%)** | Struktur soft-delete snapshot identik. |
| `migrations` | Pelacak Skema Laravel| YA | YA | **TIDAK SELARAS** | `sidatex` mencatat 26 migrasi; testing 28 migrasi. |
| Tabel Framework (7 tabel)| Cache, Jobs, Sesi | YA | YA | **SELARAS (100%)** | Tabel internal Laravel bawaan. |

---

## 3. BEDAH DETAIL PERBEDAAN KOLOM PADA `monthly_trade_summaries`

Tabel `monthly_trade_summaries` merupakan pondasi analitik Single Dashboard SIDATEX. Perbandingan kolom menunjukkan kesenjangan signifikan:

| Nama Kolom | Tipe Data di `sidatex` | Tipe Data di `sidatex_testing` | Nullable di `sidatex` | Nullable di Testing | Keterangan Celah Skema |
|:---|:---:|:---:|:---:|:---:|:---|
| `summary_id` | int unsigned | int unsigned | NO (PK) | NO (PK) | Sama (Primary Key). |
| `batch_id` | **[TIDAK ADA]** | int unsigned | — | YES (FK) | **Hilang di `sidatex`** (Menghubungkan mart ke batch). |
| `status_visibilitas`| **[TIDAK ADA]** | enum('PRIVATE','RILIS') | — | NO | **Hilang di `sidatex`** (Filter RBAC Pegawai vs Viewer). |
| `hs_id` | int unsigned | int unsigned | **NO** | **YES** | Di `sidatex` NOT NULL; di testing nullable untuk unmapped. |
| `hs2` | **[TIDAK ADA]** | varchar(2) | — | YES | **Hilang di `sidatex`** (Agregasi 2-digit Bab HS). |
| `hs_code_raw` | **[TIDAK ADA]** | varchar(20) | — | YES | **Hilang di `sidatex`** (Preservasi kode asli DBF). |
| `mapping_status` | **[TIDAK ADA]** | enum('mapped','unmapped')| — | NO | **Hilang di `sidatex`** (Penanda komoditas unmapped). |
| `is_migas` | **[TIDAK ADA]** | tinyint(1) | — | NO | **Hilang di `sidatex`** (Klasifikasi Migas vs Non-Migas). |
| `sektor` | **[TIDAK ADA]** | varchar(50) | — | YES | **Hilang di `sidatex`** (Pertanian, Industri, Tambang). |
| `bec_category` | **[TIDAK ADA]** | varchar(50) | — | YES | **Hilang di `sidatex`** (Barang Konsumsi, Modal, Baku). |
| `provorig_key` | **[TIDAK ADA]** | varchar(10) | — | NO | **Hilang di `sidatex`** (Kode asal barang provinsi). |
| `negara_id` | int unsigned | int unsigned | NO | NO | Sama. |
| `provinsi_id` | int unsigned | int unsigned | NO | NO | Sama. |
| `pelabuhan_id` | int unsigned | int unsigned | YES | YES | Sama. |
| `pelabuhan_key` | int unsigned | int unsigned | NO | NO | Sama. |
| `jenis_transaksi` | enum('Ekspor','Impor') | enum('Ekspor','Impor') | NO | NO | Sama. |
| `tipe_sumber` | enum('distribusi','neraca') | enum('distribusi','neraca') | NO | NO | Sama. |
| `bulan` | tinyint unsigned | tinyint unsigned | NO | NO | Sama. |
| `tahun` | smallint unsigned | smallint unsigned | NO | NO | Sama. |
| `total_berat` | **decimal(20,3)** | **decimal(20,6)** | NO | NO | **Presisi lama** (Belum ditingkatkan ke 6 desimal). |
| `total_nilai` | **decimal(20,2)** | **decimal(20,6)** | NO | NO | **Presisi lama** (Belum ditingkatkan ke 6 desimal). |
| `jumlah_transaksi` | int unsigned | int unsigned | NO | NO | Sama. |
| `created_at` | timestamp | timestamp | YES | YES | Sama. |
| `updated_at` | timestamp | timestamp | YES | YES | Sama. |

---

## 4. ANALISIS TABEL HILANG: `brs_indicator_summaries`

Tabel `brs_indicator_summaries` merupakan Tier-2 Analytical Mart yang dirancang khusus untuk memenuhi kebutuhan visualisasi cepat dan ekspor Berita Resmi Statistik (BRS) pimpinan BPS.

### Spesifikasi Skema yang Dibutuhkan Aplikasi:
```sql
CREATE TABLE `brs_indicator_summaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` int unsigned NOT NULL,
  `status_visibilitas` enum('PRIVATE','RILIS') NOT NULL DEFAULT 'PRIVATE',
  `tipe_sumber` enum('distribusi','neraca') NOT NULL,
  `jenis_transaksi` enum('Ekspor','Impor','Neraca_Perdagangan') NOT NULL,
  `tahun` smallint unsigned NOT NULL,
  `bulan` tinyint unsigned NOT NULL,
  `periode_tipe` enum('BULANAN','TRIWULAN','KUMULATIF','TAHUNAN') NOT NULL DEFAULT 'BULANAN',
  `kategori_dimensi` varchar(30) NOT NULL COMMENT 'TOTAL, SEKTOR, HS2_TOP10, NEGARA_TOP10, BEC, PELABUHAN, NERACA_DAGANG',
  `kode_dimensi` varchar(50) NOT NULL,
  `nama_dimensi` varchar(150) NOT NULL,
  `nilai_usd` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `nilai_juta_usd` decimal(15,2) NOT NULL DEFAULT '0.00',
  `volume_ribu_ton` decimal(15,2) NOT NULL DEFAULT '0.00',
  `share_percentage` decimal(6,2) DEFAULT NULL,
  `growth_mtom` decimal(10,2) DEFAULT NULL,
  `growth_yoy` decimal(10,2) DEFAULT NULL,
  `growth_ctc` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_brs_indicator_scope` (`tipe_sumber`,`jenis_transaksi`,`tahun`,`bulan`,`periode_tipe`,`kategori_dimensi`,`kode_dimensi`),
  KEY `idx_brs_lookup` (`status_visibilitas`,`tipe_sumber`,`jenis_transaksi`,`tahun`,`bulan`,`periode_tipe`),
  CONSTRAINT `fk_brs_batch` FOREIGN KEY (`batch_id`) REFERENCES `data_batches` (`batch_id`) ON DELETE CASCADE
);
```

### Dampak Ketiadaan Tabel di `sidatex`:
1. Ekspor PowerPoint BRS (`ExportPptController::export`) yang membaca agregasi indikator makro akan gagal.
2. Tabulasi indikator makro cepat pada dashboard eksekutif tidak dapat menyajikan angka siap saji jika dihubungkan langsung ke `sidatex`.

---

## 5. ANALISIS MODEL ELOQUENT DAN RELASI APLIKASI

Pemeriksaan terhadap model Eloquent Laravel menunjukkan ketergantungan erat pada skema yang belum termigrasi:

1. **Model `MonthlyTradeSummary` (`app/Models/MonthlyTradeSummary.php`):**
   - Mendefinisikan relasi `belongsTo(DataBatch::class, 'batch_id', 'batch_id')`.
   - Menggunakan fillable: `status_visibilitas`, `hs2`, `hs_code_raw`, `mapping_status`, `is_migas`, `sektor`, `bec_category`, `provorig_key`.
   - Pada `sidatex`, pemanggilan atribut-atribut ini akan menghasilkan `null` atau galat SQL query.
2. **Model `BrsIndicatorSummary` (`app/Models/BrsIndicatorSummary.php`):**
   - Terikat langsung ke tabel `brs_indicator_summaries`.
   - Pada `sidatex`, pemanggilan model ini langsung menghasilkan *Table not found exception*.
3. **Model `DistribusiTransaction` & `NeracaTransaction`:**
   - Telah sepenuhnya terdefinisi dan kompatibel dengan struktur tabel di `sidatex`.
4. **Model `Comment` (`app/Models/Comment.php`):**
   - Terikat ke tabel `comments` dengan relasi `belongsTo(User::class)` dan `belongsTo(DataBatch::class)`.
   - Struktur tabel di `sidatex` telah selaras 100%.

---

## 6. KESIMPULAN PERBANDINGAN STRUKTUR

1. **Struktur Master Data & Transaksi Dasar:** 100% Selaras dan Utuh.
2. **Struktur Arsitektur Analitik Two-Tier:** **TIDAK SELARAS** di `sidatex` karena belum menjalankan migrasi Phase 2D/2E.
3. **Langkah Teknis Wajib (Prakondisi UAT Operasional):**
   - Eksekusi migrasi `2026_11_16_000001_enhance_trade_summaries_and_create_brs_indicators` pada database `sidatex`.
   - Eksekusi migrasi `2026_11_18_000001_upgrade_mart_decimal_precision_to_20_6` pada database `sidatex`.
   - Eksekusi artisan backfill `php artisan trade:backfill-aggregates` pada database `sidatex` untuk mengisi tabel indikator makro.
