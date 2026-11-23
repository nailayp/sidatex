# LAPORAN AUDIT MENYELURUH DATABASE DAN SISTEM SIDATEX
## FASE 2G.10.6.4 &mdash; SISTEM INFORMASI DATA EKSPOR-IMPOR BPS PROVINSI RIAU

**Tanggal Pelaksanaan:** 20 November 2026  
**Lokasi Proyek:** `D:\laragon\www\sidatex`  
**Database Operasional Tunggal:** `sidatex`  
**Status Audit:** COMPLETED &mdash; 100% VERIFIED  
**Zona Waktu Sistem:** `Asia/Jakarta` (WIB, UTC+7)  

---

## 1. RINGKASAN EKSEKUTIF

Audit komprehensif pada Fase 2G.10.6.4 dilaksanakan untuk memastikan bahwa:
1. Sistem SIDATEX secara eksklusif menggunakan **satu database operasional tunggal**, yaitu `sidatex`. Seluruh konfigurasi runtime, `.env`, `config/app.php`, dan `config/database.php` telah diselaraskan.
2. Skema database operasional `sidatex` telah disinkronkan 100% dengan seluruh migrasi Laravel 11, termasuk pembuatan tabel data mart `brs_indicator_summaries` dan peningkatan presisi moneter/kuantitas menjadi `DECIMAL(20,6)`.
3. Seluruh data operasional lama (batch percobaan, transaksi dummy, komentar lama, dan data trash) telah dikosongkan secara terkontrol tanpa merusak integritas sistem.
4. **Data audit trail (`activity_logs`), akun otentikasi (`users`), peran (`roles`), dan seluruh tabel master data referensi BPS dipertahankan 100% utuh tanpa kehilangan 1 baris pun.**
5. Masalah ketidaksesuaian zona waktu (selisih 7 jam) telah diselesaikan secara definitif dari akar masalah konfigurasi framework dan sesi PDO MySQL.

---

## 2. STATUS DATABASE OPERASIONAL TUNGGAL (`sidatex`)

### 2.1. Audit Konfigurasi & Runtime Connection
Pemeriksaan dilakukan pada lapisan konfigurasi Laravel dan PDO runtime:
- **`.env`**:
  ```ini
  DB_CONNECTION=mysql
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=sidatex
  DB_USERNAME=root
  DB_PASSWORD=
  DB_TIMEZONE=+07:00
  APP_TIMEZONE=Asia/Jakarta
  APP_LOCALE=id
  ```
- **`config/database.php`**:
  Parameter `'timezone' => env('DB_TIMEZONE', '+07:00')` ditambahkan pada driver `mysql` dan `mariadb`. Setiap koneksi PDO secara otomatis mengeksekusi sinkronisasi sesi waktu ke `+07:00`.
- **`config/app.php`**:
  Timezone framework diatur ke `'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta')` dan `'locale' => env('APP_LOCALE', 'id')`.
- **`phpunit.xml` (Isolasi Pengujian)**:
  Pengujian otomatis diarahkan secara terisolasi ke database pengujian `<env name="DB_DATABASE" value="sidatex_testing"/>`, menjamin pengujian PHPUnit tidak akan pernah memutasi atau mengontaminasi database operasional `sidatex`.

### 2.2. Verifikasi Faktual Runtime PDO
Verifikasi runtime melalui kode PHP membuktikan:
- `Active DB Connection: sidatex`
- `App Timezone: Asia/Jakarta`
- `PHP date(): 2026-11-20 10:37:40 WIB`
- `Carbon::now(): 20 November 2026, 10.37 WIB`
- `MySQL NOW(): 2026-11-20 10:37:40`

---

## 3. HASIL SINKRONISASI SKEMA DATABASE DENGAN KODE LARAVEL

Sebelum pembersihan, audit mendeteksi bahwa dua migrasi analitik belum dieksekusi pada database operasional `sidatex` (meskipun telah berjalan pada database testing). Migrasi tersebut telah dieksekusi secara aman:

1. **`2026_11_16_000001_enhance_trade_summaries_and_create_brs_indicators`**:
   - Membuat tabel analitik `brs_indicator_summaries`.
   - Menambahkan kolom dimensi analitik pada `monthly_trade_summaries`: `batch_id`, `status_visibilitas`, `hs2`, `is_migas`, `provorig_key`.
2. **`2026_11_18_000001_upgrade_mart_decimal_precision_to_20_6`**:
   - Memperbarui tipe data `total_nilai` dan `total_berat` pada `monthly_trade_summaries` menjadi `DECIMAL(20,6)` untuk mencegah *floating-point drift* pada kalkulasi diseminasi statistik BPS.

### Tabel Skema Database `sidatex` Terkini (27 Tabel):
| No | Nama Tabel | Fungsi & Tingkat Arsitektur | Status Skema |
| :--- | :--- | :--- | :--- |
| 1 | `activity_logs` | Audit trail riwayat aksi pengguna | Sesuai |
| 2 | `brs_indicator_summaries` | Analytical Mart ringkasan indikator makro BRS | Sesuai (Migrasi Baru) |
| 3 | `comments` | Telaah internal & catatan pimpinan/eksekutif | Sesuai |
| 4 | `data_batches` | Metadata batch berkas DBF/Excel | Sesuai |
| 5 | `distribusi_transactions` | Transaksional tier Distribusi (POD/KDPROV Riau) | Sesuai |
| 6 | `master_hs` | Master komoditas kode HS 8-digit BPS | Sesuai |
| 7 | `master_hs_mappings` | Mapping klasifikasi kode HS ke sektor BRS | Sesuai |
| 8 | `master_kabkota` | Master wilayah 12 Kabupaten/Kota Riau | Sesuai |
| 9 | `master_negara` | Master negara mitra dagang internasional | Sesuai |
| 10 | `master_pelabuhan` | Master pelabuhan muat/bongkar di Riau | Sesuai |
| 11 | `master_provinsi` | Master 34/38 Provinsi Indonesia | Sesuai |
| 12 | `master_wilayah_bps` | Master kode wilayah standar BPS | Sesuai |
| 13 | `migrations` | Riwayat migrasi framework Laravel (27 entri) | Sesuai |
| 14 | `monthly_origin_trade_summaries` | Ringkasan bulanan asal provinsi (arsip) | Sesuai |
| 15 | `monthly_trade_summaries` | Two-Tier Analytical Mart 8 Dimensi (24 kolom) | Sesuai (DECIMAL 20,6) |
| 16 | `neraca_transactions` | Transaksional tier Neraca (PROVORIG/KPPBC Riau) | Sesuai |
| 17 | `roles` | Peran pengguna (Super Admin, Pegawai, Viewer) | Sesuai |
| 18 | `sessions` | Sesi aktif otentikasi peramban | Sesuai |
| 19 | `transactions` | Tabel transaksional lama (diarsipkan) | Sesuai |
| 20 | `trash` | Tempat sampah batch data (soft delete) | Sesuai |
| 21 | `users` | Akun pengguna terdaftar | Sesuai |
| 22..27 | Tabel framework Laravel | `cache`, `jobs`, `failed_jobs`, dll. | Sesuai |

---

## 4. INVENTARISASI & PEMBERSIHAN DATA OPERASIONAL LAMA

### 4.1. Backup Lengkap Pra-Pembersihan
Sebelum melakukan tindakan pembersihan apa pun, cadangan fisik SQL lengkap telah dibuat di direktori aman `storage/backups/`:
- `storage/backups/backup_sidatex_pre_cleanup_20261120.sql` (10.07 MB)
- `storage/backups/backup_sidatex_testing_pre_cleanup_20261120.sql` (14.60 MB)
- `storage/backups/backup_sidatex_staging_pre_cleanup_20261120.sql` (21.65 MB)

### 4.2. Matriks Pembersihan Tabel (Sebelum vs Sesudah)
| Kategori | Nama Tabel | Baris Sebelum | Baris Sesudah | Status Proteksi |
| :--- | :--- | :--- | :--- | :--- |
| **Operasional Lama** | `distribusi_transactions` | 2.557 | **0** | Dikosongkan Terkontrol |
| **Operasional Lama** | `neraca_transactions` | 0 | **0** | Dikosongkan Terkontrol |
| **Operasional Lama** | `transactions` (arsip lama) | 2.557 | **0** | Dikosongkan Terkontrol |
| **Operasional Lama** | `monthly_trade_summaries` | 34.847 | **0** | Dikosongkan Terkontrol |
| **Operasional Lama** | `monthly_origin_trade_summaries` | 189 | **0** | Dikosongkan Terkontrol |
| **Operasional Lama** | `data_batches` | 3 | **0** | Dikosongkan Terkontrol |
| **Operasional Lama** | `comments` | 0 | **0** | Dikosongkan Terkontrol |
| **Operasional Lama** | `trash` | 0 | **0** | Dikosongkan Terkontrol |
| **Audit Trail** | `activity_logs` | 96 | **96** | **100% DIPERTAHANKAN** |
| **Otentikasi** | `users` (admin, pegawai, viewer) | 3 | **3** | **100% DIPERTAHANKAN** |
| **Otorisasi** | `roles` (Super Admin, Pegawai, Viewer) | 3 | **3** | **100% DIPERTAHANKAN** |
| **Master Data** | `master_hs` | 11.653 | **11.653** | **100% DIPERTAHANKAN** |
| **Master Data** | `master_hs_mappings` | 11.555 | **11.555** | **100% DIPERTAHANKAN** |
| **Master Data** | `master_kabkota` | 12 | **12** | **100% DIPERTAHANKAN** |
| **Master Data** | `master_negara` | 259 | **259** | **100% DIPERTAHANKAN** |
| **Master Data** | `master_pelabuhan` | 40 | **40** | **100% DIPERTAHANKAN** |
| **Master Data** | `master_provinsi` | 34 | **34** | **100% DIPERTAHANKAN** |
| **Master Data** | `master_wilayah_bps` | 33 | **33** | **100% DIPERTAHANKAN** |

---

## 5. PENEGAKAN KEAMANAN DAN ROLE-BASED ACCESS CONTROL (RBAC)

Uji verifikasi hak akses pada endpoint pengunggahan dan audit log membuktikan penegakan izin di sisi server (*server-side enforcement*):

```text
=== TEST RBAC UNGGAH DATA (/upload-data) ===
1. Super Admin (ID 1, Role 1)       -> Status: 200 OK (Diizinkan)
2. Pegawai BPS (ID 2, Role 2)        -> Status: 403 Forbidden (Ditolak)
3. Viewer Eksekutif (ID 3, Role 3)   -> Status: 403 Forbidden (Ditolak)
```

- **Super Admin**: Memiliki hak akses penuh untuk mengunggah DBF/Excel, melakukan validasi, menyimpan transaksi, mengelola status rilis (PRIVATE/RILIS), mengelola trash, dan melihat seluruh log aktivitas.
- **Pegawai BPS**: Dibatasi hanya untuk membaca data publik bertipe `RILIS`. Percobaan akses langsung ke `/upload-data`, `/trash-data`, maupun mutasi rilis menghasilkan respon HTTP 403 Forbidden.
- **Viewer Eksekutif**: Memiliki akses ke Single Dashboard dengan visibilitas data resmi `RILIS` dan data telaah internal `PRIVATE`, serta dapat memberikan catatan telaah (komentar), tetapi tidak memiliki hak mengubah data mentah atau mengunggah berkas.

---

## 6. KESIMPULAN AUDIT SISTEM

Sistem SIDATEX telah memenuhi seluruh kriteria kesiapan arsitektur:
1. Beroperasi pada **satu database operasional tunggal** (`sidatex`).
2. Bersih dari data transaksi dummy dan batch percobaan sebelumnya.
3. Seluruh riwayat audit trail `activity_logs` dan master data referensi BPS terlindungi 100%.
4. Skema database, presisi `DECIMAL(20,6)`, dan integrasi Two-Tier Analytical Mart sepenuhnya sinkron dengan model dan layanan Laravel.
5. Zona waktu WIB berjalan akurat di seluruh lapisan aplikasi.
