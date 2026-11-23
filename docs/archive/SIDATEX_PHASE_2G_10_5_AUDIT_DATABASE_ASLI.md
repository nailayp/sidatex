# SIDATEX — AUDIT DATABASE ASLI (PHASE 2G.10.5)
## Laporan Pemeriksaan Menyeluruh Basis Data Utama `sidatex`

**Aplikasi:** Sistem Informasi Data Ekspor-Impor (SIDATEX) — BPS Provinsi Riau  
**Lokasi Direktori:** `D:\laragon\www\sidatex`  
**Database Utama (Target UAT Operasional):** `sidatex`  
**Database Pengujian Teknis (Baseline Regresi):** `sidatex_testing`  
**Database Tambahan Terdeteksi:** `sidatex_staging`  
**Tanggal Audit:** 19 November 2026  
**Mode Audit:** 100% Read-Only (Tanpa Perubahan Skema, Data, atau Konfigurasi)  

---

## 1. KONTEKS & TUJUAN AUDIT DATABASE ASLI

Rangkaian pengujian teknis, pengujian otomatis PHPUnit (180/180 PASS), pengujian browser, dan penyusunan instrumen UAT pada fase-fase sebelumnya (Phase 2G.1 hingga 2G.10.4) dijalankan pada basis data pengujian `sidatex_testing`. 

Sebelum melangkah ke tahap pemaparan dan User Acceptance Testing (UAT) operasional langsung bersama tim Badan Pusat Statistik (BPS) Provinsi Riau, sistem harus siap beroperasi pada database utama asli, yaitu **`sidatex`**.

Audit ini bertujuan untuk:
1. Mengidentifikasi konfigurasi koneksi aktif dan koneksi target aplikasi.
2. Memeriksa ketersediaan tabel, kolom, tipe data, indeks, dan relasi pada database `sidatex`.
3. Membandingkan secara faktual struktur dan data antara database utama `sidatex` dan database pengujian `sidatex_testing`.
4. Mengidentifikasi celah ketidaksinkronan (*schema and data gaps*) serta potensi risiko kegagalan sistem sebelum migrasi data operasional dilakukan.

---

## 2. AUDIT KONEKSI RUNTIME DAN KONFIGURASI DATABASE

Pemeriksaan runtime PDO dilakukan secara langsung melalui kernel Laravel dan driver MySQL untuk memastikan integritas koneksi tanpa hanya mengandalkan teks statis `.env`:

```
+---------------------------------------------------------------------------------------------------+
| HASIL PEMERIKSAAN KONEKSI RUNTIME LARAVEL                                                         |
+---------------------------------------------------------------------------------------------------+
| Driver Database Default   : mysql                                                                 |
| Database Terkonfigurasi   : sidatex_testing (saat sesi pengujian teknis)                          |
| Database PDO Aktif        : sidatex_testing                                                       |
| Host & Port Runtime       : 127.0.0.1:3306                                                        |
| Daftar Database Terdeteksi: 1. sidatex (Database Utama BPS)                                       |
|                             2. sidatex_testing (Database Pengujian Teknis)                        |
|                             3. sidatex_staging (Database Staging Eksperimental)                   |
+---------------------------------------------------------------------------------------------------+
```

### Temuan Konfigurasi Koneksi:
1. **Aplikasi Saat Ini Terhubung ke `sidatex_testing`:**  
   Konfigurasi aktif Laravel runtime saat ini mengarah ke `sidatex_testing`. Hal ini tepat untuk menjaga database utama `sidatex` tidak terkontaminasi selama pengujian otomatis PHPUnit dan audit teknis Phase 2G.
2. **Database Utama `sidatex` Bersifat Independen:**  
   Database `sidatex` berada pada instance MySQL yang sama (Port 3306), dalam kondisi terisolasi dan tidak pernah dimutasi selama pengujian fase-fase sebelumnya.
3. **Peringatan Peralihan Koneksi:**  
   Peralihan koneksi aplikasi pada `.env` (`DB_DATABASE=sidatex`) **TIDAK BOLEH DILAKUKAN SEBELUM KETIDAKSINKRONAN SKEMA PADA DATABASE `sidatex` DIREMEDIASI**, karena akan menyebabkan kegagalan runtime (*SQL Exception 1054*) pada query analitik dashboard.

---

## 3. AUDIT KELENGKAPAN TABEL PADA DATABASE UTAMA `sidatex`

Pemeriksaan menyeluruh terhadap seluruh tabel pada database `sidatex` (26 tabel) dibandingkan dengan `sidatex_testing` (27 tabel):

| No | Nama Tabel | Kategori Fungsi | Baris di `sidatex` | Baris di `sidatex_testing` | Status Kelengkapan di `sidatex` |
|:---:|:---|:---|:---:|:---:|:---:|
| 1 | `users` | Autentikasi Pengguna | 3 | 3 | **TERSEDIA & IDENTIK** |
| 2 | `roles` | Hak Akses / RBAC | 3 | 3 | **TERSEDIA & IDENTIK** |
| 3 | `data_batches` | Kelompok Dokumen DBF | 3 | 9 | **TERSEDIA (3 Batch Riil BPS)** |
| 4 | `distribusi_transactions` | Transaksi Distribusi | 2.557 | 5.169 | **TERSEDIA (Batch #70, #71, #74)** |
| 5 | `neraca_transactions` | Transaksi Neraca | 0 | 2.896 | **TABEL ADA, DATA KOSONG (0 Baris)** |
| 6 | `transactions` | Transaksi Warisan (Phase 1) | 2.557 | 2.557 | **TERSEDIA (Arsip Read-Only)** |
| 7 | `monthly_trade_summaries` | Mart Agregasi Granular Tier-1 | 34.847 | 38.063 | **TERSEDIA (Skema Lama, Butuh Migrasi)** |
| 8 | `monthly_origin_trade_summaries` | Mart Asal Barang Warisan | 189 | 66 | **TERSEDIA (Presisi Lama 20,2)** |
| 9 | `brs_indicator_summaries` | Mart Indikator Makro Tier-2 | **[TIDAK ADA]** | 380 | **HILANG / BELUM DIMIGRASI** |
| 10 | `master_hs` | Master Komoditas HS 2022 | 11.653 | 11.653 | **TERSEDIA & IDENTIK (Checksum Cocok)** |
| 11 | `master_hs_mappings` | Pemetaan HS 2017-2022 | 11.555 | 11.555 | **TERSEDIA & IDENTIK (Checksum Cocok)** |
| 12 | `master_negara` | Master Negara Tujuan/Asal | 259 | 259 | **TERSEDIA & IDENTIK (Checksum Cocok)** |
| 13 | `master_provinsi` | Master Provinsi Indonesia | 34 | 34 | **TERSEDIA & IDENTIK (Checksum Cocok)** |
| 14 | `master_kabkota` | Master Kabupaten/Kota Riau | 12 | 12 | **TERSEDIA & IDENTIK (Checksum Cocok)** |
| 15 | `master_pelabuhan` | Master Pelabuhan Muat/Bongkar | 40 | 40 | **TERSEDIA & IDENTIK (Checksum Cocok)** |
| 16 | `master_wilayah_bps` | Master Wilayah Administrasi BPS | 33 | 33 | **TERSEDIA & IDENTIK (Checksum Cocok)** |
| 17 | `comments` | Komentar Catatan Batch | 0 | 0 | **TERSEDIA (Kosong)** |
| 18 | `activity_logs` | Audit Trail Jejak Aktivitas | 96 | 358 | **TERSEDIA (Histori Operasional Riil)** |
| 19 | `trash` | Tempat Sampah Pemulihan | 0 | 0 | **TERSEDIA (Kosong)** |
| 20 | `migrations` | Riwayat Migrasi Laravel | 26 | 28 | **TERSEDIA (Kurang 2 Migrasi)** |
| 21 | `sessions` | Sesi Autentikasi Pengguna | 6 | 3 | **TERSEDIA** |
| 22 | `cache` | Cache Aplikasi | 0 | 0 | **TERSEDIA** |
| 23 | `cache_locks` | Kunci Konkurensi Cache | 0 | 0 | **TERSEDIA** |
| 24 | `jobs` | Antrean Tugas Latar | 0 | 0 | **TERSEDIA** |
| 25 | `job_batches` | Batch Antrean Tugas | 0 | 0 | **TERSEDIA** |
| 26 | `failed_jobs` | Kegagalan Tugas Latar | 0 | 0 | **TERSEDIA** |
| 27 | `password_reset_tokens` | Token Reset Kata Sandi | 0 | 0 | **TERSEDIA** |

---

## 4. TEMUAN KRITIS: KETIDAKSINKRONAN SKEMA DATABASE UTAMA

Audit menemukan bahwa database utama `sidatex` tertinggal 2 (dua) berkas migrasi arsitektur analitik Two-Tier yang telah diuji sukses pada `sidatex_testing`:

### A. Migrasi yang Belum Berjalan pada Database `sidatex`:
1. **`2026_11_16_000001_enhance_trade_summaries_and_create_brs_indicators.php`**
   - **Dampak 1:** Tabel `brs_indicator_summaries` (Tier-2 Analytical Mart untuk visualisasi BRS makro, tren, top 10 komoditas, dan neraca perdagangan) **BELUM TERBENTUK** di `sidatex`.
   - **Dampak 2:** Tabel `monthly_trade_summaries` di `sidatex` **KEHILANGAN 9 KOLOM KRITIS**:
     - `batch_id` (unsigned int, FK ke data_batches)
     - `status_visibilitas` (enum 'PRIVATE', 'RILIS')
     - `hs2` (varchar 2)
     - `hs_code_raw` (varchar 20)
     - `mapping_status` (enum 'mapped', 'unmapped')
     - `is_migas` (boolean)
     - `sektor` (varchar 50)
     - `bec_category` (varchar 50)
     - `provorig_key` (varchar 10)
     - Kolom `hs_id` masih berstatus `NOT NULL` (seharusnya `nullable` untuk menampung transaksi unmapped tanpa menghilangkan data).
     - Indeks komposit `idx_mts_vis_period` belum ada.
2. **`2026_11_18_000001_upgrade_mart_decimal_precision_to_20_6.php`**
   - **Dampak:** Kolom `total_berat` dan `total_nilai` pada `monthly_trade_summaries` dan `monthly_origin_trade_summaries` di `sidatex` masih bertipe `decimal(20,2)` dan `decimal(20,3)` (belum ditingkatkan ke standar presisi penuh `DECIMAL(20,6)` untuk mencegah *floating point drift* sesuai pedoman AGENTS.md Seksi 2).

### B. Bukti Faktual Kegagalan Runtime jika Dihubungkan ke `sidatex` Saat Ini:
Ketika query service analitik (`StatisticsCalculationService::applyMonthlySummaryVisibility`) dieksekusi terhadap `sidatex`, MySQL langsung menolak dengan galat:
```sql
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'status_visibilitas' in 'field list'
```
Hal ini membuktikan secara ilmiah dan tidak terbantahkan bahwa **database `sidatex` BELUM SIAP digunakan secara langsung tanpa eksekusi migrasi skema pendukung terlebih dahulu**.

---

## 5. ANALISIS DATA BATCH DAN TRANSAKSI ASLI PADA `sidatex`

Database `sidatex` memuat 3 (tiga) batch transaksi riil BPS Provinsi Riau hasil unggah resmi:

| Batch ID | Nama Berkas DBF Asli | Jenis Data | Periode | Metodologi Sumber | Status | Jumlah Baris | Total Nilai USD |
|:---:|:---|:---|:---:|:---:|:---:|:---:|:---:|
| **#70** | `14_impor_atap_kdprov_0826.DBF` | Impor | 08/2026 | Distribusi (KDPROV) | `RILIS` | 785 | \$167.347.655,15 |
| **#71** | `14_Atap_ekspor_2609_POD_based.DBF` | Ekspor | 09/2026 | Distribusi (POD) | `RILIS` | 841 | \$2.301.809.204,54 |
| **#74** | `14_impor_atap_kdprov_0926.DBF` | Impor | 09/2026 | Distribusi (KDPROV) | `RILIS` | 931 | \$215.260.644,43 |
| **TOTAL** | — | — | — | — | — | **2.557** | **\$2.684.417.504,12** |

### Karakteristik Penting Data `sidatex`:
1. **Semua Batch Berstatus `RILIS`:**  
   Berbeda dengan `sidatex_testing` di mana Batch #74 dan #720 diisolasi sebagai `PRIVATE` untuk uji RBAC, pada `sidatex` ketiga batch riil berstatus resmi `RILIS`.
2. **Ketiadaan Data Transaksi Neraca:**  
   Tabel `neraca_transactions` pada `sidatex` masih berjumlah **0 baris**. Seluruh pengujian ingestasi berkas Neraca (`PROVORIG` dan `KPPBC`) sebelumnya hanya dilakukan di lingkungan pengujian `sidatex_testing` (2.896 baris).
3. **Data Mart Agregasi Historis Utuh (34.847 Baris):**  
   Tabel `monthly_trade_summaries` memuat data runtun waktu (*time series*) ekspor-impor BPS dari Januari 2023 hingga September 2026. Data ini merupakan agregasi historis resmi yang mendasari grafik tren tahunan dan analisis pertumbuhan YoY.

---

## 6. KESIMPULAN AUDIT & TINGKAT RISIKO

```
+---------------------------------------------------------------------------------------------------+
| KESIMPULAN AUDIT DATABASE UTAMA sidatex                                                           |
+---------------------------------------------------------------------------------------------------+
| Status Kesiapan Skema       : BELUM SIAP (Tertinggal 2 Migrasi Analitik)                          |
| Status Kesiapan Master Data : SIAP PENUH (100% Identik & Checksum Cocok)                          |
| Status Integritas Transaksi : SIAP & ASLI (2.557 Baris Transaksi Riil BPS)                        |
| Status Ketersediaan Neraca  : DATA BELUM TERSEDIA (0 Baris, Menunggu Unggah DBF Neraca)           |
| Tingkat Risiko Peralihan    : TINGGI (Jika dipaksakan beralih tanpa migrasi skema)                |
+---------------------------------------------------------------------------------------------------+
```

### Rekomendasi Tindak Lanjut:
1. **Jangan mengubah target `.env` ke `sidatex` secara terburu-buru.**
2. Rencanakan pelaksanaan migrasi skema yang tertinggal (`2026_11_16_...` dan `2026_11_18_...`) pada fase implementasi terpisah dengan prosedur backup ketat.
3. Setelah migrasi skema dijalankan di `sidatex`, jalankan perintah `trade:backfill-aggregates` untuk membangun tabel indikator `brs_indicator_summaries` secara otomatis dari transaksi yang ada.
