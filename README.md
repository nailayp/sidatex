# SIDATEX — Sistem Informasi Data Ekspor-Impor

### Badan Pusat Statistik (BPS) Provinsi Riau

SIDATEX (Sistem Informasi Data Ekspor-Impor) adalah aplikasi web analitis dan transaksional terpadu yang dirancang khusus untuk Badan Pusat Statistik (BPS) Provinsi Riau. Aplikasi ini memfasilitasi seluruh rantai nilai pengolahan data statistik perdagangan luar negeri, mulai dari pembacaan dan validasi berkas biner dBase (DBF) Bea Cukai, pembersihan dan pemetaan master referensi BPS, agregasi data mart statistik dua lapis (*two-tier data mart*), visualisasi analitis melalui Single Dashboard 4-Tab, diseminasi data ekspor-impor, hingga otomasi penyusunan materi rilis Berita Resmi Statistik (BRS).

---

## 1. Fitur Utama

- **Single Dashboard Statistik Terpadu (4 Tab):**
  - **Tab 1 — Overview Global & Tren:** Ringkasan indikator makro (Total Nilai FOB/CIF, Berat Bersih Netto, Pertumbuhan MoM, YoY, C-to-C), grafik tren bulanan, komparasi tahunan, dan distribusi sektor migas vs non-migas.
  - **Tab 2 — Tabulasi Time Series Komoditas:** Matriks pivot dinamis perkembangan nilai perdagangan per golongan komoditas (HS 2-digit) dan komoditas unggulan Riau.
  - **Tab 3 — Detail Analisis & Pemetaan Wilayah:** Visualisasi peta spasial interaktif pelabuhan muat/bongkar di Riau berbasis Leaflet.js, analisis negara mitra dagang utama, dan rincian transaksi per pelabuhan.
  - **Tab 4 — Neraca Perdagangan (Trade Balance):** Analisis surplus/defisit perdagangan Provinsi Riau secara historis dengan pemisahan metodologi Distribusi dan Neraca yang tegas.
- **Pemisahan Tegas Metodologi Distribusi dan Neraca:**
  - **Ekspor Distribusi:** Komoditas yang dimuat melalui pelabuhan muat di wilayah Riau (indikator `POD` = Riau).
  - **Ekspor Neraca:** Komoditas yang diproduksi atau berasal dari Provinsi Riau (indikator `PROVORIG` = Riau), terlepas dari pelabuhan muat.
  - **Impor Distribusi:** Komoditas yang dibongkar di pelabuhan Riau (indikator `KDPROV` = Riau).
  - **Impor Neraca:** Komoditas yang melalui kantor pengawasan Bea Cukai Riau (indikator `KPPBC` Riau).
- **Ingesti & Validasi Berkas DBF Atomik:**
  - Parser biner DBF native (`DbfReaderService`) untuk memproses berkas PEB (Ekspor) dan PIB (Impor) secara langsung tanpa konversi pihak ketiga.
  - Validasi ketat kelengkapan kolom, format tanggal, kode master HS 8-digit/10-digit, negara, pelabuhan, dan wilayah BPS.
  - Validasi formula BRS atomik dan penanganan *unmapped* codes secara eksplisit tanpa penghapusan diam-diam (*silent drop*).
- **Arsitektur Two-Tier Data Mart:**
  - Pemisahan fisik antara transaksi mentah terarsip (`distribusi_transactions`, `neraca_transactions`) dan ringkasan agregasi analitis (`monthly_trade_summaries`, `brs_indicator_summaries`).
  - Presisi tinggi `DECIMAL(20,6)` untuk seluruh nilai moneter (FOB/CIF USD) dan berat bersih (Kg) guna mencegah *floating-point drift*.
- **Role-Based Access Control (RBAC) di Sisi Server:**
  - Pembagian 3 peran pengguna: **Super Admin (Role 1)**, **Pegawai BPS (Role 2)**, dan **Viewer Eksekutif (Role 3)** dengan otorisasi ketat di sisi middleware dan controller.
- **Audit Trail & Log Aktivitas Sistem:**
  - Pencatatan seluruh aktivitas audit (login, logout, upload, hapus batch, restore trash, perubahan status publikasi, dan ekspor) pada modul `/activity-logs`.
- **Ekspor & Diseminasi Multi-Format:**
  - Ekspor data tabular ke Excel (.xlsx) dengan streaming memory-efficient via FastExcel.
  - Generator presentasi Berita Resmi Statistik otomatis ke PowerPoint (.pptx).

---

## 2. Stack Teknologi

- **Backend Framework:** Laravel 11.x
- **Bahasa Pemrograman:** PHP 8.2+ (Direkomendasikan PHP 8.4)
- **Database:**
  - **Operasional:** MySQL 8.0+ (Database: `sidatex`, Charset: `utf8mb4`, Collation: `utf8mb4_unicode_ci`)
  - **Automated Testing:** SQLite In-Memory (`:memory:`)
- **Frontend:** Blade Views, TailwindCSS, Alpine.js, Chart.js 4, Leaflet.js, SheetJS
- **Pengujian Terotomasi:** PHPUnit 11 (193 Tests, 1,596 Assertions, 100% Pass)
- **Lingkungan Pengembangan Lokal:** Laragon (Windows) / Nginx / Apache

---

## 3. Prasyarat Sistem

Sebelum melakukan instalasi, pastikan perangkat telah terpasang:

1. **PHP 8.2 atau lebih baru** (disarankan PHP 8.4) dengan ekstensi berikut aktif:
   - `pdo_mysql`
   - `pdo_sqlite`
   - `mbstring`
   - `openssl`
   - `xml`
   - `curl`
   - `zip`
   - `fileinfo`
   - `gd`
2. **Composer 2.x**
3. **Node.js 18+ & NPM**
4. **MySQL Server 8.0+**
5. **Git**

---

## 4. Langkah Instalasi Lengkap

### Langkah 1 — Clone Repository

Buka terminal (PowerShell / Git Bash), arahkan ke direktori web server (misal Laragon `d:\laragon\www`), lalu clone repositori:

```bash
cd /d d:\laragon\www
git clone https://github.com/nailayp/sidatex.git
cd sidatex
```

### Langkah 2 — Install Dependensi Backend & Frontend

Jalankan instalasi dependensi PHP via Composer dan paket frontend via NPM:

```bash
composer install
npm install
```

### Langkah 3 — Konfigurasi Environment (`.env`)

Salin berkas template lingkungan `.env.example` menjadi `.env`:

```bash
copy .env.example .env
```

*(Pada Linux / macOS gunakan: `cp .env.example .env`)*

Generate kunci enkripsi aplikasi:

```bash
php artisan key:generate
```

### Langkah 4 — Pembuatan Database MySQL

Buat basis data MySQL bernama `sidatex` melalui Laragon Menu (**MySQL > Create database**) atau via terminal MySQL:

```sql
CREATE DATABASE sidatex CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Langkah 5 — Penyesuaian Konfigurasi `.env`

Pastikan pengaturan database pada berkas `.env` sesuai dengan konfigurasi lokal:

```dotenv
APP_NAME=SIDATEX
APP_ENV=local
APP_DEBUG=true
APP_TIMEZONE=Asia/Jakarta
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sidatex
DB_USERNAME=root
DB_PASSWORD=
```

> [!NOTE]
> Pada template `.env.example`, nilai `DB_DATABASE` secara sengaja diatur ke `sidatex_testing` sebagai pengaman operasional (*safety guard*) agar eksekusi migrasi awal developer baru tidak menimpa database operasional `sidatex`. Ubah nilainya menjadi `sidatex` pada `.env` lokal Anda setelah memastikan kesiapan environment. Jika akun MySQL Anda menggunakan kata sandi, sesuaikan nilai `DB_PASSWORD`.

### Langkah 6 — Eksekusi Migrasi Skema Database

Jalankan migrasi database untuk membangun seluruh skema tabel operasional, master referensi, transaksi Distribusi & Neraca, serta tabel data mart:

```bash
php artisan migrate
```

### Langkah 7 — Eksekusi Seeder Master Data & Pengguna Awal

Jalankan database seeder untuk mengisi data master referensi BPS (HS, Negara, Pelabuhan, Provinsi, Kab/Kota, Wilayah BPS) serta akun default:

```bash
php artisan db:seed
```

### Langkah 8 — Menjalankan Aplikasi

Jalankan development server Laravel:

```bash
php artisan serve
```

Di terminal terpisah, kompilasi aset frontend Vite:

```bash
npm run dev
```

*(Untuk kompilasi produksi siap rilis, jalankan `npm run build`)*

Aplikasi SIDATEX dapat diakses melalui browser pada alamat:
```
http://127.0.0.1:8000
```

---

## 5. Menjalankan Automated Testing (Pengujian Otomatis)

Seluruh suite pengujian otomatis SIDATEX dirancang menggunakan **PHPUnit 11** dengan isolasi penuh pada **SQLite In-Memory (`:memory:`)**.

> [!IMPORTANT]
> **ATURAN ISOLASI PENGUJIAN:**
> - Pengujian otomatis **DILARANG KERAS** menyentuh atau memodifikasi database operasional `sidatex`.
> - Pengujian **TIDAK MEMERLUKAN** koneksi database MySQL `sidatex_testing` maupun `sidatex_staging`.
> - Konfigurasi telah terkunci secara otomatis pada `phpunit.xml` menggunakan koneksi `sqlite` in-memory.

Jalankan seluruh pengujian dengan perintah:

```bash
php vendor/phpunit/phpunit/phpunit
```

Atau menggunakan Artisan:

```bash
php artisan test
```

**Status Kelulusan Pengujian:**
- **Total Tests:** 193 Tests
- **Total Assertions:** 1,596 Assertions
- **Failures / Errors:** 0 (100% PASS)

---

## 6. Arsitektur Data & Two-Tier Data Mart

SIDATEX menerapkan arsitektur *Two-Tier Data Mart* untuk menjamin integritas data mentah transaksi sekaligus memberikan performa query tinggi pada dashboard analitis:

```
┌─────────────────────────────────────────────────────────────┐
│                    DBF Raw Data Ingestion                   │
│         (PEB Ekspor & PIB Impor Bea Cukai Riau)             │
└──────────────────────────────┬──────────────────────────────┘
                               │
                ┌──────────────┴──────────────┐
                ▼                             ▼
┌──────────────────────────────┐┌──────────────────────────────┐
│     distribusi_transactions  ││     neraca_transactions      │
│  (Cakupan Pelabuhan Riau:    ││  (Cakupan Asal Barang/KPPBC: │
│     POD / KDPROV = Riau)     ││     PROVORIG / KPPBC Riau)   │
└──────────────┬───────────────┘└──────────────┬───────────────┘
               │                               │
               └───────────────┬───────────────┘
                               │ (Atomik & Idempotent Aggregation)
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                   monthly_trade_summaries                   │
│   (Analytical Mart — Unique Key 8-Dimensi Granular)         │
│   [sumber_data, jenis_transaksi, tahun, bulan, hs_id,       │
│    negara_id, pelabuhan_id, provinsi_id]                    │
│   Presisi Nilai: DECIMAL(20,6) FOB / CIF / Netto Kg         │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                  brs_indicator_summaries                    │
│   (Macro KPI Mart Siap Saji Dashboard & Rilis BRS)          │
│   [Total FOB, CIF, Netto, Migas, Non-Migas, MoM, YoY, C-to-C│
└─────────────────────────────────────────────────────────────┘
```

1. **Transactional Tier:**
   - `distribusi_transactions`: Menyimpan detail transaksi Distribusi (ekspor via pelabuhan muat Riau, impor via pelabuhan bongkar Riau).
   - `neraca_transactions`: Menyimpan detail transaksi Neraca (ekspor berasal dari produsen Riau, impor diawasi kantor pabean Riau).
   - *Catatan:* Tabel historis `transactions` telah diarsipkan dan tidak digunakan untuk penulisan baru.
2. **Analytical Mart Tier:**
   - `monthly_trade_summaries`: Agregasi bulanan granular per 8 dimensi (`uq_mts_8_dimensi`), menjamin idempotensi rekalkulasi saat ada perubahan batch.
   - `brs_indicator_summaries`: Ringkasan indikator makro bulanan siap saji untuk kartu KPI Single Dashboard dan materi rilis BRS.
3. **Presisi Tinggi Data Statistik:**
   - Seluruh kolom moneter (`nilai_fob_usd`, `nilai_cif_usd`) dan berat bersih (`berat_bersih_kg`) bertipe `DECIMAL(20,6)` untuk meniadakan distorsi pembulatan *floating point*.

---

## 7. Role-Based Access Control (RBAC)

Aplikasi mengimplementasikan kontrol akses ketat berbasis 3 tingkatan peran:

| Hak Akses / Modul | Super Admin (Role 1) | Pegawai BPS (Role 2) | Viewer Eksekutif (Role 3) |
| :--- | :---: | :---: | :---: |
| Single Dashboard Statistik (`/dashboard`) | Ya (Rilis & Private) | Ya (Hanya Rilis) | Ya (Rilis & Private Telaah) |
| Tabulasi & Filtering Data (`/filtering-data`) | Ya (Rilis & Private) | Ya (Hanya Rilis) | Ya (Rilis & Private Telaah) |
| Detail Analisis Komoditas (`/analisis-perdagangan`) | Ya (Rilis & Private) | Ya (Hanya Rilis) | Ya (Rilis & Private Telaah) |
| Upload & Validasi DBF (`/upload-data`) | **Ya** | **Tidak (403)** | **Tidak (403)** |
| Riwayat Batch & Publikasi (`/manajemen-data`) | **Ya (Penuh)** | **Hanya Baca Rilis** | **Hanya Baca** |
| Ubah Status Data (Rilis / Private) | **Ya** | **Tidak (403)** | **Tidak (403)** |
| Soft Delete & Force Delete Batch | **Ya** | **Tidak (403)** | **Tidak (403)** |
| Tempat Sampah / Pemulihan (`/trash-data`) | **Ya** | **Tidak (403)** | **Tidak (403)** |
| Log Audit Trail (`/activity-logs`) | **Ya (Semua Log)** | **Hanya Log Pribadi** | **Hanya Log Pribadi** |
| Ekspor Laporan Excel & Slide PowerPoint | **Ya** | **Ya (Data Rilis)** | **Ya** |

---

## 8. Siklus Hidup Data (Data Lifecycle)

```
[ Berkas DBF Mentah ]
         │
         ▼
[ Validasi & Preview ] ── (Gagal) ──► Ditolak dengan Notifikasi Kesalahan
         │
         ▼ (Lolos Validasi)
[ Penyimpanan Batch ] ──► Status: DRAFT / PRIVATE
         │
         ▼
[ Agregasi Otomatis ] ──► Pengisian monthly_trade_summaries & brs_indicator_summaries
         │
         ▼
[ Telaah Internal BPS ] ──► Hanya Super Admin & Viewer Eksekutif
         │
         ▼ (Disetujui untuk Rilis Publik)
[ Publikasi: RILIS ] ──► Muncul di Dashboard Umum & Akses Pegawai BPS
         │
         ▼ (Jika Dihapus)
[ Soft Delete: TRASH ] ──► Dikeluarkan otomatis dari kalkulasi Single Dashboard
         │
         ├──► [ Restore ] ──► Dikembalikan ke data aktif & dihitung ulang
         └──► [ Force Delete ] ──► Dihapus permanen beserta berkas fisik & transaksi
```

---

## 9. Prosedur Backup & Pemulihan (Backup & Restore) Database

Untuk menjaga keamanan data operasional BPS Provinsi Riau, lakukan pencadangan rutin basis data MySQL `sidatex`:

### Cadangkan Database Operasional (`sidatex`):

```bash
mysqldump -u root -p --routines --triggers --single-transaction sidatex > storage/backups/sidatex_backup_$(date +%Y%m%d_%H%M%S).sql
```

*(Pada Windows PowerShell tanpa variabel date:)*
```powershell
mysqldump -u root -p --routines --triggers --single-transaction sidatex > storage/backups/sidatex_backup.sql
```

### Pemulihan (Restore) Database:

Jika diperlukan pemulihan database dari berkas cadangan:

```bash
mysql -u root -p sidatex < storage/backups/sidatex_backup.sql
```

> [!CAUTION]
> Jangan pernah memulihkan file SQL langsung ke database produksi tanpa melakukan pencadangan awal atas basis data yang sedang aktif.

---

## 10. Lisensi dan Pengembang

- **Institusi:** Badan Pusat Statistik (BPS) Provinsi Riau
- **Fungsi:** Sistem Informasi Data Ekspor-Impor (SIDATEX)
- **Repositori:** [https://github.com/nailayp/sidatex](https://github.com/nailayp/sidatex)

---
*Dokumentasi ini dimutakhirkan secara resmi pada Fase 2G.10.6.4.12 — Git Review Group 8.*
