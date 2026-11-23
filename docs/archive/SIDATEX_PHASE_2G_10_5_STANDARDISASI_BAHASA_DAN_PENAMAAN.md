# SIDATEX — STANDARDISASI BAHASA & PENAMAAN SISTEM (PHASE 2G.10.5)
## Bahasa Indonesia Standardization, Terminology Mapping, and Database Nomenclature Matrix

**Aplikasi:** Sistem Informasi Data Ekspor-Impor (SIDATEX) — BPS Provinsi Riau  
**Lokasi Direktori:** `D:\laragon\www\sidatex`  
**Tujuan Dokumen:** Pedoman baku standardisasi Bahasa Indonesia pada antarmuka pengguna, basis data, kode program, dan dokumentasi teknis agar mudah dipahami oleh staf BPS, pimpinan, penguji, dan pengembang selanjutnya.  
**Tanggal Penyusunan:** 19 November 2026  
**Status Tindakan:** HANYA PERENCANAAN DAN PEMETAAN (Dilarang mengubah nama tabel/kolom/kode pada fase ini)  

---

## 1. PRINSIP STANDARDISASI BAHASA INDONESIA SIDATEX

Aplikasi SIDATEX dikembangkan untuk digunakan sebagai instrumen diseminasi dan pengolahan data resmi internal BPS Provinsi Riau. Oleh karena itu, prinsip utama komunikasi sistem adalah:

1. **Bahasa Indonesia sebagai Bahasa Utama:**  
   Seluruh antarmuka pengguna, label menu, pesan notifikasi, pesan validasi formulir, dialog konfirmasi, dan panduan teknis wajib menggunakan Bahasa Indonesia yang baku, profesional, dan komunikatif.
2. **Preservasi Istilah Statistik Baku BPS:**  
   Istilah resmi statistik perdagangan luar negeri yang telah memiliki definisi metodologis resmi BPS dipertahankan tanpa distorsi, seperti: *Ekspor (FOB)*, *Impor (CIF)*, *Komoditas*, *Neraca Perdagangan (Trade Balance)*, *Pelabuhan Muat (POD)*, *Pelabuhan Bongkar (KDPROV)*, *Asal Barang (PROVORIG)*, *Kantor Bea Cukai (KPPBC)*, *Netto (Kg)*, dan *Kode HS 8-Digit*.
3. **Preservasi Istilah Rekayasa Perangkat Lunak:**  
   Istilah teknis pemrograman internasional dipertahankan bentuk aslinya dengan penjelasan Bahasa Indonesia untuk mencegah kerancuan sintaksis, seperti: *Foreign Key* (kunci relasi antartabel), *Primary Key* (kunci utama), *Soft Delete* (penghapusan lunak sementara), *Rollback* (pengembalian status), *API Endpoint* (titik akses komunikasi data), *Middleware* (penyaring otorisasi), dan *Seeder* (pengisi data awal).
4. **Zero Forced Translation:**  
   Dilarang menerjemahkan istilah framework secara kaku yang dapat merusak pustaka Laravel (misal: jangan mengganti kata `namespace`, `interface`, `trait`, atau nama tabel bawaan seperti `migrations`).

---

## 2. GLOSARIUM STANDARDISASI ISTILAH ANTARMUKA (UI / UX)

Pemetaan istilah antarmuka pengguna yang disarankan untuk diterapkan pada tampilan Blade:

| Istilah Bahasa Inggris / Campuran | Istilah Standar Bahasa Indonesia | Konteks Penggunaan pada Aplikasi SIDATEX |
|:---|:---|:---|
| Dashboard | **Dasbor** / **Dasbor Statistik** | Halaman utama penyajian visualisasi makro ekspor-impor. |
| Single Dashboard | **Dasbor Terpadu** | Integrasi 4 tab analisis dalam satu layar kerja. |
| Upload Data | **Unggah Data** | Halaman pengunggahan berkas mentah DBF/Excel. |
| Download / Export | **Unduh** / **Ekspor** | Pengunduhan data tabular Excel (.xlsx) atau slide BRS (.pptx). |
| Data Processing | **Pengolahan Data** | Tahap parsing, pembersihan, dan validasi rekaman transaksi. |
| Preview Data | **Pratinjau Data** | Tampilan sampel 10–50 baris sebelum berkas disimpan permanen. |
| Validation | **Validasi** | Pemeriksaan kesesuaian kolom, kode HS, negara, dan tipe data. |
| Data Batch / Document | **Kelompok Data** / **Batch Data** | Satu kesatuan berkas DBF yang diunggah pada suatu periode. |
| Release / Public | **Rilis Resmi** / **Rilis** | Status data yang telah disetujui untuk publikasi umum. |
| Private | **Privat** / **Internal** | Status data draft hasil olahan untuk telaah pimpinan internal BPS. |
| Pending | **Menunggu Verifikasi** | Status berkas yang belum divalidasi penuh atau sedang ditinjau. |
| Trash Data | **Tempat Sampah** / **Sampah** | Modul isolasi dokumen yang dihapus secara lunak (*soft-deleted*). |
| Restore | **Pulihkan Data** | Tindakan mengembalikan berkas dari tempat sampah ke status aktif. |
| Activity Log / Audit Trail | **Riwayat Aktivitas** / **Log Audit** | Rekaman jejak mutasi data, login, dan aksi pengguna. |
| Comments / Annotations | **Catatan Verifikasi** / **Komentar** | Catatan telaah pimpinan Viewer Eksekutif terhadap data batch. |
| User Management | **Manajemen Pengguna** | Pengelolaan akun, peran (*roles*), dan status keaktifan staf. |
| Settings | **Pengaturan** | Konfigurasi sistem dan parameter aplikasi. |
| Overview & Trend | **Ringkasan & Tren** | Tab 1: Gambaran makro dan grafik garis tren waktu perdagangan. |
| Time Series Commodity | **Runtun Waktu Komoditas** | Tab 2: Tabulasi nilai komoditas ekspor-impor menurut waktu. |
| Trade Balance | **Neraca Perdagangan** | Tab 4: Selisih nilai ekspor (FOB) dikurangi nilai impor (CIF). |

---

## 3. MATRIKS EVALUASI PENAMAAN TABEL BASIS DATA

Analisis komparatif jika penamaan tabel basis data dialihkan ke Bahasa Indonesia:

| Nama Tabel Saat Ini | Usulan Nama Bahasa Indonesia | Fungsi Entitas | Relasi Antartabel | Dampak Perubahan Kode Jika Diganti | Rekomendasi Auditor |
|:---|:---|:---|:---|:---|:---|
| `users` | `pengguna` | Akun pengguna sistem | `roles`, `comments`, `activity_logs` | **SANGAT TINGGI:** Memerlukan refactoring `User.php`, config `auth.php`, migration, dan ratusan query. | **DIPERTAHANKAN** (Standar Laravel) |
| `roles` | `peran` | Definisi peran hak akses | `users` | **TINGGI:** Memerlukan perubahan `Role.php`, relasi `belongsTo`, middleware otorisasi. | **DIPERTAHANKAN** |
| `data_batches` | `kelompok_data` | Identitas berkas batch DBF | `transactions`, `comments`, `trash` | **SANGAT TINGGI:** Kunci relasi `batch_id` dipakai di seluruh controller, service, dan mart. | **DIPERTAHANKAN** |
| `distribusi_transactions` | `transaksi_distribusi` | Transaksi Tier-1 Distribusi | `data_batches`, master tables | **SANGAT TINGGI:** Mempengaruhi parser DBF, indexing, dan service kalkulasi BRS. | **DIPERTAHANKAN** |
| `neraca_transactions` | `transaksi_neraca` | Transaksi Tier-1 Neraca | `data_batches`, master tables | **SANGAT TINGGI:** Mempengaruhi modul upload Neraca dan agregasi. | **DIPERTAHANKAN** |
| `transactions` | `transaksi_lama` | Tabel transaksi warisan Phase 1 | `data_batches` | **RENDAH:** Tabel arsip read-only. | **DIPERTAHANKAN** (Arsip) |
| `monthly_trade_summaries` | `ringkasan_perdagangan_bulanan`| Analytical Mart Tier-1 | `data_batches`, master tables | **SANGAT TINGGI:** Pondasi query Single Dashboard dan agregator BRS. | **DIPERTAHANKAN** |
| `monthly_origin_trade_summaries` | `ringkasan_asal_perdagangan` | Mart asal barang Phase 1 | `data_batches` | **SEDANG:** Dipakai pada perbandingan historis lama. | **DIPERTAHANKAN** |
| `brs_indicator_summaries` | `indikator_makro_brs` | Analytical Mart Tier-2 | `data_batches` | **TINGGI:** Digunakan oleh service ekspor PowerPoint dan KPI card. | **DIPERTAHANKAN** |
| `master_hs` | `master_hs` | Master kode HS 8-digit | `distribusi_transactions`, mart | **NOL:** Sudah menggunakan istilah baku statistik BPS. | **DIPERTAHANKAN** |
| `master_hs_mappings` | `master_pemetaan_hs` | Pemetaan HS 2017 ke 2022 | `master_hs` | **SEDANG:** Dipakai saat ingestasi berkas tahun transisi. | **DIPERTAHANKAN** |
| `master_negara` | `master_negara` | Master negara mitra dagang | Transaksi, mart | **NOL:** Sudah Bahasa Indonesia baku. | **DIPERTAHANKAN** |
| `master_provinsi` | `master_provinsi` | Master 34 provinsi | Transaksi, mart | **NOL:** Sudah Bahasa Indonesia baku. | **DIPERTAHANKAN** |
| `master_kabkota` | `master_kabkota` | Master 12 kab/kota Riau | Transaksi, mart | **NOL:** Sudah Bahasa Indonesia baku. | **DIPERTAHANKAN** |
| `master_pelabuhan` | `master_pelabuhan` | Master pelabuhan muat/bongkar| Transaksi, mart | **NOL:** Sudah Bahasa Indonesia baku. | **DIPERTAHANKAN** |
| `master_wilayah_bps` | `master_wilayah_bps` | Master wilayah adm BPS | Transaksi, mart | **NOL:** Sudah Bahasa Indonesia baku. | **DIPERTAHANKAN** |
| `comments` | `komentar` | Catatan verifikasi batch | `users`, `data_batches` | **SEDANG:** Memerlukan penyesuaian `Comment.php` dan `CommentController.php`. | **DIPERTAHANKAN** |
| `activity_logs` | `log_aktivitas` | Audit trail jejak audit | `users` | **SEDANG:** Memerlukan penyesuaian `ActivityLogger.php`. | **DIPERTAHANKAN** |
| `trash` | `tempat_sampah` | Penampung soft-delete | `data_batches` | **SEDANG:** Digunakan pada query eksklusi dashboard. | **DIPERTAHANKAN** |
| `cache`, `jobs`, `sessions` | — | Tabel framework internal | Framework internal | **FATAL:** Mengganti tabel ini merusak fungsi internal core Laravel. | **MUTLAK DIPERTAHANKAN** |

> **KESIMPULAN AUDITOR TENTANG NAMA TABEL:**  
> Mengubah nama tabel database secara fisik pada saat aplikasi sudah berada pada tahap persiapan UAT memiliki **risiko regresi ekstrem (Breaking Changes)** tanpa memberikan nilai tambah fungsional bagi pengguna akhir (staf BPS tidak berinteraksi langsung dengan query SQL). Disarankan **MEMPERTAHANKAN SELURUH NAMA TABEL YANG ADA**, dan memfokuskan standardisasi Bahasa Indonesia pada **Lapisan Tampilan Antarmuka (Blade Views) dan Label Pengguna**.

---

## 4. STANDARDISASI PENAMAAN KOLOM BASIS DATA

Untuk penamaan kolom, sistem SIDATEX telah menerapkan pendekatan campuran yang bijak:
- **Kolom Standar Framework Laravel:** `id`, `created_at`, `updated_at`, `remember_token`, `password`, `email` dipertahankan dalam Bahasa Inggris agar kompatibel dengan Eloquent timestamping dan auth provider.
- **Kolom Domain Bisnis BPS:** Sudah menggunakan Bahasa Indonesia, seperti:
  - `nama_file`, `jenis_data`, `periode_tahun`, `periode_bulan`, `tipe_sumber`, `ukuran_file`, `uploaded_by`, `released_by` (pada `data_batches`).
  - `nilai`, `berat`, `netto`, `nilai_usd`, `volume`, `nama_komoditi` (pada transaksi dan mart).
  - `komentar` (pada `comments`).
  - `aktivitas`, `modul`, `deskripsi`, `ip_address` (pada `activity_logs`).
  - `nama_role` (pada `roles`).

---

## 5. STANDARDISASI KODE PROGRAM & KOMENTAR DOKUMENTATIF

Sesuai dengan pedoman maintainability (Unslop Guidelines):
1. **Nama Class & File:** Mematuhi standar PSR-12 dan konvensi PascalCase Laravel (misal: `StatisticsCalculationService`, `DashboardController`, `BrsAggregationService`).
2. **Komentar Kode (PHPDoc):** Wajib menggunakan Bahasa Indonesia yang natural, deskriptif, dan menjelaskan alasan bisnis (*the 'why'*) serta aturan BPS yang melandasinya.
   - *Contoh Baik:*
     ```php
     /**
      * Menghitung pertumbuhan ekonomi Month-to-Month (MoM) sesuai formula resmi BPS:
      * ((Nilai Bulan Berjalan - Nilai Bulan Sebelumnya) / Nilai Bulan Sebelumnya) * 100.
      * Mengembalikan null jika periode pembanding tidak tersedia untuk mencegah pembagian nol.
      */
     ```
   - *Contoh Dihindari (Slop/Boilerplate):*
     ```php
     // fungsi untuk menghitung
     public function hitung()
     ```
3. **Nama Variabel & Metode:** Menggunakan camelCase yang deskriptif dan mudah dipahami oleh programmer Indonesia maupun internasional (misal: `$totalNilaiUsd`, `$bulanTerpilih`, `$allowedStatuses`, `$brsIndicatorSummary`).

---

## 6. KESIMPULAN REKOMENDASI STANDARDISASI

```
+---------------------------------------------------------------------------------------------------+
| KESIMPULAN REKOMENDASI STANDARDISASI BAHASA & PENAMAAN                                            |
+---------------------------------------------------------------------------------------------------+
| Standardisasi Antarmuka Pengguna (UI)  : WAJIB BAHASA INDONESIA 100% (Menu, Tombol, Notifikasi)   |
| Nama Database Utama                    : TETAP `sidatex` (Stabil & Resmi)                         |
| Nama Database Pengujian Teknis         : USULAN `sidatex_pengujian` (Direalisasikan fase terpisah)|
| Nama Tabel Basis Data Saat Ini         : DIPERTAHANKAN (Mencegah Regresi & Kerusakan Framework)   |
| Nama Kolom Bisnis Statistik            : DIPERTAHANKAN (Sudah berbahasa Indonesia baku)           |
| Penjelasan Logika & PHPDoc             : WAJIB BAHASA INDONESIA NATURAL & DESKRIPTIF              |
| PENEGASAN MUTLAK                       : TIDAK ADA PERUBAHAN NAMA KODE/TABEL PADA PHASE 2G.10.5   |
+---------------------------------------------------------------------------------------------------+
```
