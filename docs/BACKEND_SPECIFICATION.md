# BACKEND ARCHITECTURE & TECHNICAL SPECIFICATION
# SISTEM INFORMASI DATA EKSPOR-IMPOR BPS PROVINSI RIAU (SIDATEX)

---

## 1. Struktur Folder Backend

Aplikasi SIDATEX dikembangkan menggunakan kerangka kerja **Laravel 11/12** dengan arsitektur berbasis layanan (*Service-Oriented Architecture*) untuk memisahkan logika orkestrasi HTTP, aturan bisnis berat, dan akses basis data:

```
sidatex/
├── app/
│   ├── Console/Commands/             # Artisan command (sinkronisasi data, reset admin)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/LoginController.php     # Autentikasi sesi & logout
│   │   │   ├── DashboardController.php      # Agregasi statistik resmi & grafik
│   │   │   ├── UploadController.php         # Upload Excel, simpan batch, manajemen data, rilis
│   │   │   ├── ExcelPreviewController.php   # Pratinjau awal file Excel sebelum apply
│   │   │   ├── FilteringController.php      # Pencarian komprehensif transaksi & komoditas
│   │   │   ├── AnalisisController.php       # Analisis perdagangan menurut negara, HS, pelabuhan
│   │   │   ├── CommentController.php        # Pengelolaan catatan/komentar verifikasi batch
│   │   │   └── ExportPptController.php      # Generator bahan presentasi PowerPoint BRS
│   │   └── Middleware/
│   │       ├── Authenticate.php             # Memastikan pengguna telah login
│   │       └── CheckRole.php                # Memeriksa hak akses berbasis role_id
│   ├── Models/                              # Eloquent ORM Models
│   │   ├── Role.php                         # Definisi peran (Super Admin, Pegawai, Viewer)
│   │   ├── User.php                         # Identitas pengguna & kredensial
│   │   ├── DataBatch.php                    # Header dokumen unggahan
│   │   ├── Transaction.php                  # Baris transaksi ekspor-impor
│   │   ├── MasterHs.php                     # Kode & deskripsi barang HS
│   │   ├── MasterNegara.php                 # Negara asal/tujuan
│   │   ├── MasterProvinsi.php               # Provinsi muat/bongkar
│   │   ├── MasterKabkota.php                # Kabupaten/Kota
│   │   ├── MasterPelabuhan.php              # Pelabuhan laut & udara
│   │   ├── ActivityLog.php                  # Riwayat aksi pengguna
│   │   ├── Comment.php                      # Catatan verifikasi batch
│   │   └── Trash.php                        # Snapshot dokumen terhapus
│   └── Services/                            # Business Logic Services
│       ├── ExcelTransactionParser.php       # Low-memory streaming XML parser untuk file Excel
│       ├── StatisticsCalculationService.php # Perhitungan M-to-M, Q-to-Q, Y-on-Y, C-to-C
│       ├── ActivityLogger.php               # Helper otomatis audit trail
│       └── PowerPointExportService.php      # Generator slide presentasi BRS
├── config/                                  # Konfigurasi aplikasi, database, dan session
├── database/
│   ├── migrations/                          # Skema tabel relasional
│   └── seeders/                             # Seeder awal role, super admin, dan master data
├── docs/                                    # Dokumentasi resmi proyek (PRD, Spec, API, Report)
├── resources/views/                         # Blade templates frontend
└── routes/
    ├── web.php                              # Rute web aplikasi
    └── api.php                              # Rute API internal (jika diperlukan)
```

---

## 2. Model & Relasi Basis Data

Setiap model merepresentasikan entitas basis data secara tegas dengan relasi Eloquent yang terdefinisi dua arah:

| Model | Tabel Basis Data | Primary Key | Relasi Utama |
| :--- | :--- | :--- | :--- |
| `Role` | `roles` | `role_id` | `hasMany(User)` |
| `User` | `users` | `id` | `belongsTo(Role)`, `hasMany(DataBatch)`, `hasMany(ActivityLog)`, `hasMany(Comment)` |
| `DataBatch` | `data_batches` | `batch_id` | `belongsTo(User, 'uploaded_by')`, `belongsTo(User, 'released_by')`, `hasMany(Transaction)`, `hasMany(Comment)`, `hasOne(Trash)` |
| `Transaction` | `transactions` | `transaction_id` | `belongsTo(DataBatch)`, `belongsTo(MasterHs)`, `belongsTo(MasterNegara)`, `belongsTo(MasterProvinsi)`, `belongsTo(MasterKabkota)`, `belongsTo(MasterPelabuhan)` |
| `MasterHs` | `master_hs` | `hs_id` | `hasMany(Transaction)` |
| `MasterNegara`| `master_negara` | `negara_id` | `hasMany(Transaction)` |
| `MasterProvinsi`| `master_provinsi`| `provinsi_id` | `hasMany(Transaction)` |
| `MasterKabkota` | `master_kabkota` | `kabkota_id` | `hasMany(Transaction)` |
| `MasterPelabuhan`| `master_pelabuhan`| `pelabuhan_id` | `hasMany(Transaction)` |
| `ActivityLog` | `activity_logs` | `log_id` | `belongsTo(User)` |
| `Comment` | `comments` | `comment_id` | `belongsTo(User)`, `belongsTo(DataBatch)` |
| `Trash` | `trash` | `trash_id` | `belongsTo(DataBatch)`, `belongsTo(User, 'deleted_by')`, `belongsTo(User, 'restored_by')` |

---

## 3. Layer Layanan (Services)

### 3.1. `ExcelTransactionParser`
Menangani tantangan pengolahan file Excel 10–50 MB berisi lebih dari 50.000 baris:
- **Teknologi**: Menggunakan `ZipArchive` bawaan PHP untuk mengekstrak lembar kerja `xl/worksheets/sheetX.xml` dan string bersama `xl/sharedStrings.xml`.
- **Eksekusi**: Membaca XML secara sekuensial menggunakan `XMLReader` sehingga memori PHP yang digunakan tetap konstan (< 30 MB) tanpa membaca seluruh pohon XML ke memori RAM (*No DOM overhead*).
- **Pemetaan Otomatis**:
  - Kolom `BULAN` dan `TAHUN` dinormalisasi ke integer.
  - Kolom `KODE HS` dicocokkan dengan `master_hs.hs_code` (mendukung fallback kode 2, 4, 8, atau 10 digit).
  - Kolom `KODE_NGR` dicocokkan dengan `master_negara.kode_negara`.
  - Kolom `PROVORIG` dipetakan ke `master_provinsi` (default Riau `14`).
  - Kolom `NETTO (KG)` dan `FOB (US$)` dikonversi ke tipe data desimal presisi tinggi.
- **Bulk Insert**: Mengumpulkan baris ke dalam buffer array dan mengeksekusi `DB::table('transactions')->insert($chunk)` setiap 1.000 baris.

### 3.2. `StatisticsCalculationService`
Menyediakan kalkulasi matematis baku statistik BPS untuk grafik dan kartu indikator:
- **Nilai Perdagangan**: Agregasi bulanan $V_m = \sum \text{FOB}$.
- **Berat Bersih**: Agregasi bulanan $W_m = \sum \text{NETTO}$.
- **Month-to-Month (M-to-M)**:
  $$\text{M-to-M}_m = \frac{V_m - V_{m-1}}{V_{m-1}} \times 100\%$$
- **Quarter-to-Quarter (Q-to-Q)**:
  $$\text{Q-to-Q}_q = \frac{Q_q - Q_{q-1}}{Q_{q-1}} \times 100\%$$
- **Year-on-Year (Y-on-Y)**:
  $$\text{Y-on-Y}_{m,y} = \frac{V_{m,y} - V_{m,y-1}}{V_{m,y-1}} \times 100\%$$
- **Cumulative-to-Cumulative (C-to-C)**:
  $$\text{C-to-C}_{m,y} = \frac{\sum_{i=1}^m V_{i,y} - \sum_{i=1}^m V_{i,y-1}}{\sum_{i=1}^m V_{i,y-1}} \times 100\%$$

### 3.3. `ActivityLogger`
Mencatat seluruh aksi audit trail ke tabel `activity_logs`:
```php
ActivityLogger::log('Rilis Publikasi', 'Manajemen Data', "Batch #{$batchId} dirilis ke publik");
```

### 3.4. `PowerPointExportService`
Menyusun bahan paparan Berita Resmi Statistik dalam format Microsoft PowerPoint (.pptx):
- Menghasilkan slide pembuka BRS Ekspor-Impor Riau.
- Menghasilkan slide tabel pertumbuhan (M-to-M, Y-on-Y, C-to-C).
- Menghasilkan slide 10 komoditas unggulan dan 10 negara mitra utama.
- Dirancang modular dan siap dihubungkan dengan template resmi dari PIC BPS (Kak Bekti).

---

## 4. Otorisasi & Middleware

Sistem menerapkan otorisasi bertingkat:
1. **Middleware `auth`**: Seluruh rute internal dilindungi agar hanya pengguna yang telah berhasil login yang dapat mengakses.
2. **Middleware / Gate `role`**:
   - `role_id === 1` (**Super Admin**): Akses mutlak untuk aksi mutasi data (`store`, `update`, `destroy`, `restore`, `toggleRelease`, `forceDelete`).
   - `role_id === 2` (**Pegawai**): Akses baca (*read-only*) terhadap data yang berstatus `RILIS`.
   - `role_id === 3` (**Viewer Berkepentingan**): Akses baca terhadap data yang berstatus `RILIS` serta hak tulis pada modul komentar (`CommentController@store`).

---

## 5. Strategi Database Transaction & Error Handling

Untuk memastikan kepatuhan ACID (*Atomicity, Consistency, Isolation, Durability*):
```php
DB::beginTransaction();
try {
    // 1. Simpan Header Batch
    $batchId = DB::table('data_batches')->insertGetId([...]);

    // 2. Parse & Insert Ribuan Transaksi via Streaming
    $total = $parser->parseAndInsert($filePath, $batchId, $jenis);

    // 3. Catat Audit Log
    ActivityLogger::log('Upload Batch', 'Upload Data', "Batch #{$batchId} berhasil disimpan");

    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    // Hapus file fisik sementara jika ada
    @unlink($filePath);
    logger()->error("Gagal simpan batch: " . $e->getMessage());
    throw $e;
}
```

---

## 6. Strategi Trash & Soft-Delete

Karena tabel `data_batches` memiliki relasi ketat ke puluhan ribu baris transaksi dan tabel `trash`, penghapusan dokumen mengadopsi mekanisme *Snapshot Trash*:
1. Saat dokumen dihapus, record di `data_batches` **tidak langsung di-DROP** demi menjaga referensial foreign key dan memungkinkan pemulihan instan.
2. Seluruh metadata dan ringkasan transaksi diserialisasikan ke format JSON dan disimpan di tabel `trash` bersama ID user penghapus dan timestamp `deleted_at`.
3. Kueri dokumen aktif di seluruh sistem menyaring batch yang memiliki record di `trash` dengan kondisi `restored_at IS NULL`.
4. Saat dipulihkan (*restore*), kolom `restored_at` dan `restored_by` diisi waktu saat ini, sehingga dokumen kembali muncul seketika di daftar aktif dengan status aslinya.
