<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

<sidatex-project-guidelines>
# SIDATEX Project Rules & Guidelines

Pedoman baku dan persisten pengembangan Sistem Informasi Data Ekspor-Impor (SIDATEX) BPS Provinsi Riau. Seluruh agen AI dan pengembang wajib mematuhi aturan berikut secara ketat.

---

## 1. Konteks Aplikasi SIDATEX
- **Definisi:** SIDATEX adalah aplikasi web internal BPS Provinsi Riau untuk pengelolaan, pemrosesan data mentah DBF Ekspor-Impor, agregasi data mart statistik, visualisasi Single Dashboard, diseminasi data, dan penerbitan Berita Resmi Statistik (BRS).
- **Struktur Halaman Utama:**
  - Single Dashboard Statistik (`/dashboard`): Memuat 4 tab terintegrasi (Overview Global & Tren, Tabulasi Time Series Komoditas, Detail Analisis & Pemetaan Wilayah, Neraca Perdagangan / Trade Balance).
  - Manajemen Upload Data (`/upload-data`): Validasi, pra-pemrosesan DBF, pembersihan, verifikasi rumus BRS, dan penyimpanan transaksional.
  - Manajemen Data & Riwayat Batch (`/manajemen-data`).
  - Trash Data & Pemulihan (`/trash-data`).
  - Log Aktivitas Audit Trail (`/activity-logs`).

---

## 2. Arsitektur Laravel dan Database
- **Framework & Stack:** Laravel 11 (PHP 8.2+), Blade Views, TailwindCSS, Alpine.js, Chart.js 4, Leaflet.js, dan SheetJS.
- **Model Data & Mart Agregasi (Two-Tier Data Mart):**
  1. *Transactional Tier:*
     - `distribusi_transactions`: Menyimpan data transaksi Distribusi (cakupan Pelabuhan Muat Riau / POD dan Pelabuhan Bongkar Riau / KDPROV).
     - `neraca_transactions`: Menyimpan data transaksi Neraca (cakupan Asal Barang Riau / PROVORIG dan Kantor Pengawasan Bea Cukai Riau / KPPBC).
     - *Catatan Penting:* Tabel lama `transactions` diarsipkan dan **DILARANG** digunakan untuk penulisan transaksi baru.
  2. *Analytical Mart Tier:*
     - `monthly_trade_summaries`: Agregasi granular bulanan per dimensi sumber, jenis, tahun, bulan, komoditas, negara, dan pelabuhan.
     - `brs_indicator_summaries`: Ringkasan indikator makro siap saji untuk dashboard eksekutif dan publikasi BRS.
- **Tipe Data & Presisi:**
  - Nilai moneter (FOB / CIF dalam USD) dan berat bersih (Netto dalam Kg) wajib menggunakan `DECIMAL(20,6)` untuk menjamin integritas kalkulasi statistik dan mencegah *floating point drift*.

---

## 3. Larangan Mutlak Mutasi Database Operasional (`sidatex`)
- **PROTEKSI MUTLAK DATABASE OPERASIONAL:**
  - Database operasional `sidatex` **TIDAK BOLEH** diubah, dimutasi, dimigrasi, di-truncate, atau ditimpa data pengujian tanpa persetujuan eksplisit tertulis dari pengguna.
  - Seluruh pengujian otomatis **WAJIB** berjalan pada lingkungan terisolasi SQLite in-memory (`:memory:`), tanpa menyentuh MySQL `sidatex` maupun MySQL testing fisik.

---

## 4. Kewajiban Verifikasi Koneksi Runtime PDO
- Sebelum menjalankan perintah migrasi, seeder, upload simulasi, atau pengujian, sistem/agen wajib memvalidasi koneksi runtime agar tidak pernah mengarahkan operasi pengujian ke database operasional `sidatex`:
  ```php
  $activeDriver = DB::connection()->getDriverName();
  $activeDb = DB::connection()->getDatabaseName();
  if (app()->environment('testing') && ($activeDriver !== 'sqlite' || $activeDb !== ':memory:')) {
      throw new RuntimeException("UNSAFE TEST DATABASE: Driver '$activeDriver' DB '$activeDb', expected SQLite ':memory:'!");
  }
  ```
- Tidak boleh hanya mengandalkan pengecekan teks statis pada `.env` karena konfigurasi Laravel dapat ter-cache.

---

## 5. Standar Lingkungan Pengujian (SQLite In-Memory)
- Semua automated test dijalankan pada SQLite in-memory (`:memory:`) melalui `phpunit.xml` (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).
- Tidak ada dependensi terhadap database MySQL testing (`sidatex_testing`) maupun staging (`sidatex_staging`).
- Fixture pengujian harus terisolasi dan deterministik, membersihkan datanya sendiri secara otomatis saat koneksi in-memory ditutup.

---

## 6. Pemisahan Tegas Metodologi Distribusi dan Neraca
- Metodologi **Distribusi** dan **Neraca** memiliki semesta data (*data universe*) dan definisi statistik yang berbeda:
  - **Ekspor Distribusi:** Komoditas yang dimuat melalui pelabuhan muat di wilayah Riau (indikator POD = Riau).
  - **Ekspor Neraca:** Komoditas yang diproduksi atau berasal dari Provinsi Riau (indikator PROVORIG = Riau), terlepas dari apakah barang diekspor melalui pelabuhan di dalam atau di luar Riau.
  - **Impor Distribusi:** Komoditas yang dibongkar di pelabuhan Riau (indikator KDPROV = Riau).
  - **Impor Neraca:** Komoditas yang melalui kantor pabean pengawasan Riau (indikator KPPBC Riau).
- **ATURAN MUTLAK:** Angka Distribusi dan Neraca **DILARANG DIGABUNG ATAU DIJUMLAHKAN** menjadi satu total tanpa dasar metodologi resmi BPS. Seluruh visualisasi dashboard, matriks pivot, dan laporan wajib memisahkan kedua cakupan ini melalui filter / switcher eksplisit.

---

## 7. Metodologi Statistik BPS & Validasi Perhitungan
- **Trade Balance (Neraca Perdagangan):**
  $$\text{Neraca Perdagangan} = \text{Total Ekspor (FOB)} - \text{Total Impor (CIF)}$$
  Hanya boleh dihitung pada metodologi yang sama (Distribusi dengan Distribusi, atau Neraca dengan Neraca) dan periode waktu yang identik.
- **Pertumbuhan Statistik:**
  - **Month-to-Month (MoM):** Dibandingkan terhadap bulan tepat sebelumnya ($M$ vs $M-1$).
  - **Quarter-to-Quarter (QoQ):** Dibandingkan terhadap triwulan tepat sebelumnya ($Q$ vs $Q-1$).
  - **Year-on-Year (YoY):** Dibandingkan terhadap bulan/triwulan yang sama pada tahun sebelumnya ($M_{Y}$ vs $M_{Y-1}$).
  - **Cumulative-to-Cumulative (C-to-C):** Perbandingan kumulatif tahun berjalan sampai bulan tertentu terhadap periode kumulatif yang sama pada tahun sebelumnya.
  - Jika penyebut bernilai nol atau periode pembanding tidak tersedia, sistem wajib menandai status *unavailable* (N/A) dan tidak menggantinya secara diam-diam dengan 0.
- **Klasifikasi HS & Migas/Non-Migas:**
  - Kode HS wajib mengacu pada Master Komoditas BPS.
  - Kode HS yang tidak ditemukan pada master (*HS unmapped*) **tidak boleh dihapus atau otomatis dimasukkan ke Non-Migas / LAINNYA**; pertahankan kode asli, beri flag status *unmapped*, dan catat dalam log validasi.

---

## 8. Role-Based Access Control (RBAC)
Sistem SIDATEX menerapkan pembatasan hak akses berbasis peran secara ketat di sisi server (*server-side enforcement*):
- **Super Admin (Role 1):**
  - Hak akses penuh: Upload DBF, pra-pemrosesan, validasi, simpan batch, hapus batch, audit trail, publikasi/rilis data, dan melihat data bertipe `RILIS` maupun `PRIVATE`.
- **Pegawai BPS (Role 2):**
  - Akses baca (*read-only*) untuk analisis data berstatus `RILIS`.
  - Dilarang mengakses endpoint upload (`/upload-data`), penghapusan data, dan data berstatus `PRIVATE` (respons: HTTP 403 Forbidden).
- **Viewer Eksekutif / Berkepentingan (Role 3):**
  - Akses dashboard eksekutif untuk data berstatus `RILIS` dan data draft internal `PRIVATE` untuk peninjauan sebelum rilis resmi.

---

## 9. Siklus Hidup dan Status Data (DRAFT, PRIVATE, RILIS)
- **DRAFT:** Data hasil preview parsing yang belum divalidasi dan belum disimpan ke data mart.
- **PRIVATE:** Data yang telah disimpan ke transaksi dan mart agregasi tetapi masih berstatus telaah internal tim statistik BPS. Hanya dapat diakses oleh Super Admin dan Viewer Eksekutif.
- **RILIS:** Data resmi yang telah disetujui untuk publikasi umum dan dapat diakses oleh seluruh pengguna (termasuk Pegawai).
- **Trash:** Data yang dihapus secara lunak (*soft deleted*) yang diisolasi ke modul tempat sampah (`/trash-data`) dan dikeluarkan secara otomatis dari kalkulasi Single Dashboard.

---

## 10. Standar PSR-12, Maintainability, dan Pengujian
- **Zero Boilerplate:** Hindari wrapper spekulatif, generic interfaces untuk kasus tunggal, dan komentar kode yang sekadar mengulang kode.
- **PSR-12 Compliance:** Format kode PHP wajib mematuhi standar PSR-12.
- **Query Optimization:** Cegah masalah *N+1 Query* pada relasi Eloquent; gunakan eager loading (`with()`) atau agregasi terencana pada data mart.
- **Pengujian Terisolasi:** Setiap penambahan atau perbaikan fitur backend wajib dilengkapi pengujian otomatis PHPUnit yang menguji skenario sukses, kegagalan validasi, otorisasi RBAC, rollback transaksi, dan idempotensi batch.
</sidatex-project-guidelines>
