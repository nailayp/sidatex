# FINAL CHECKPOINT & DESAIN ARSITEKTUR IMPORT SIDATEX
**Dokumen:** `FINAL_DESIGN_IMPORT_DBF_SIDATEX.md`  
**Lokasi Proyek:** `D:\laragon\www\sidatex`  
**Lokasi Referensi BPS:** `D:\MAGANG\referensi_bps`  
**Status Audit:** READ ONLY — Pemeriksaan Terakhir Sebelum Implementasi  
**Tanggal:** 13 November 2026  

---

## 1. Keputusan Struktur Nama Tabel Fisik & Strategi Migrasi Data

Sistem menetapkan secara tegas arsitektur **dua tabel fisik terpisah** di dalam satu database MySQL `sidatex`:

```
                       DATABASE: sidatex
        ┌──────────────────────────────────────────────┐
        │                                              │
        ▼                                              ▼
┌─────────────────────────────┐        ┌─────────────────────────────┐
│   distribusi_transactions   │        │     neraca_transactions     │
│       (Tabel Fisik)         │        │       (Tabel Fisik)         │
│   Menyimpan Data Distribusi │        │    Menyimpan Data Neraca    │
│  (FOB Pelabuhan, CIF Kons)  │        │ (FOB Asal Komoditas, KPPBC) │
└─────────────────────────────┘        └─────────────────────────────┘
```

### A. Audit Ketergantungan Tabel Lama `transactions`
Pemeriksaan kode mendalam menemukan **139 ketergantungan aktif** terhadap tabel/model `transactions`:
- **Controllers (106 referensi):**
  - `AnalisisController.php` (22)
  - `DashboardController.php` (49)
  - `FilteringController.php` (25)
  - `UploadController.php` (8)
  - `NeracaController.php` (2)
- **Services (36 referensi):**
  - `FastExcelExporter.php` (14)
  - `DistributionStatisticsService.php` (10)
  - `DataReconciliationService.php` (6)
  - `TradeAggregationService.php` (4)
  - `DistributionUploadService.php` (4)
  - `ExcelTransactionParser.php` (3)
  - `StatisticsCalculationService.php` (1)
  - `PowerPointExportService.php` (1)
- **Models & Relationships (13 referensi):**
  - `Transaction.php`, `DataBatch.php`, `MasterHs.php`, `MasterNegara.php`, `MasterPelabuhan.php`, `MasterProvinsi.php`, `MasterKabkota.php`.

### B. Strategi Transisi Aman Tanpa Downtime & Tanpa Data Loss
Dilarang menghapus tabel `transactions` lama secara tiba-tiba sebelum migrasi dan verifikasi data selesai. Urutan transisi fisik:
1. **Langkah 1:** Buat tabel fisik baru `neraca_transactions` melalui migration baru.
2. **Langkah 2:** Salin skema dan data `transactions` (2.557 data Distribusi yang ada saat ini) ke tabel fisik baru `distribusi_transactions`.
3. **Langkah 3:** Lakukan *verification checksum* (pastikan jumlah baris, sum nilai, dan sum berat identik 100%).
4. **Langkah 4:** Arahkan model `Transaction` / buat model `DistribusiTransaction` ke `distribusi_transactions`.
5. **Langkah 5:** Setelah seluruh fitur dan pengujian terbukti berjalan sempurna, tabel lama `transactions` diarsipkan secara aman (misal `transactions_legacy_archive`).

---

## 2. Keputusan Status Batch & Audit Alur Penggantian Data

### A. Struktur Status Batch Aktual
Kolom status batch pada tabel `data_batches` saat ini bertipe:
```sql
`status` ENUM('DRAFT', 'PRIVATE', 'NON-RILIS', 'RILIS') NOT NULL DEFAULT 'DRAFT'
```

### B. Keputusan Penanganan Batch Pengganti (Anti-Duplikasi Data)
**DITETAPKAN:** **TIDAK MENAMBAHKAN STATUS BARU SEPERTI `SUPERSEDED`** ke kolom ENUM tanpa kebutuhan mutlak, karena penambahan nilai ENUM baru akan memecah 70+ referensi status batch yang telah stabil pada:
- `UploadController.php` (44 referensi)
- `FilteringController.php` (6 referensi)
- `NeracaController.php` (5 referensi)
- `DistributionUploadService.php` (9 referensi)
- `DistributionStatisticsService.php` (4 referensi)
- Seluruh modul Dashboard dan Trash Data.

### C. Alur Penggantian Batch yang Aman:
Sebagai gantinya, alur penggantian data memanfaatkan status standar **`NON-RILIS`** dengan penanda metadata:
1. **Dashboard Isolation:**
   Seluruh query Dashboard, Ekspor BRS, dan Agregasi Bulanan **HANYA MENGHITUNG BATCH BERSTATUS `RILIS`**:
   ```sql
   WHERE b.status = 'RILIS' AND b.deleted_at IS NULL
   ```
2. **Skenario Re-upload / Penggantian Data (Contoh: Revisi September 2026):**
   - **Jika Batch Lama Berstatus `DRAFT` atau `PRIVATE`:**
     Batch lama beserta seluruh data transaksi fisiknya dihapus tuntas (`cascade delete`) di dalam satu transaksi database (`DB::transaction`), lalu batch baru disimpan.
   - **Jika Batch Lama Sudah Berstatus `RILIS`:**
     - Sistem mewajibkan Super Admin melakukan konfirmasi pembatalan rilis.
     - Status batch lama diubah dari `RILIS` menjadi `NON-RILIS`.
     - Catatan log otomatis dibuat pada `activity_logs` (*"Batch #72 dialihkan menjadi NON-RILIS digantikan oleh Batch #78"*).
     - Batch baru diunggah dan dirilis menjadi status `RILIS` yang aktif.
     - Agregasi bulanan (`monthly_trade_summaries` / `neraca_monthly_summaries`) otomatis di-rebuild ulang dari transaksi batch berstatus `RILIS`.
3. **Pencegahan Restore Ganda dari Trash:**
   Saat memulihkan batch dari menu *Trash Data*, sistem memvalidasi apakah telah ada batch berstatus `RILIS` pada periode `[tahun, bulan, tipe, modul]` yang sama. Jika sudah ada, sistem menolak restore langsung dan mewajibkan pengguna memilih salah satu batch yang sah. Dengan demikian, **tidak akan pernah ada dua versi data aktif secara bersamaan**.

---

## 3. Validasi Kapasitas Numerik `DECIMAL(20,6)`

Pemeriksaan aktual terhadap seluruh nilai numerik pada file raw DBF BPS yang berhasil dibaca menghasilkan temuan:
- **Nilai Transaksi Terbesar (USD):** `261.582.500,400000` (ditemukan pada file `14_Atap_ekspor_2609_POD_based.DBF`, field `FOB`).
  - Membutuhkan 9 digit integer + 6 digit desimal = 15 digit total presisi.
- **Nilai Berat Netto Terbesar (Kg):** `235.000.400,0000` (ditemukan pada file `14_impor_atap_kdprov_0726.DBF`, field `B0726`).
  - Membutuhkan 9 digit integer + 4 digit desimal = 13 digit total presisi.
- **Skala Desimal Maksimum Sumber:** Tepat **6 digit desimal** (pada file Ekspor) dan **4 digit desimal** (pada file Impor).

### A. Evaluasi Kapasitas `DECIMAL(20,6)`:
Tipe `DECIMAL(20,6)` pada MySQL menyediakan:
- **14 digit integer:** hingga `99.999.999.999.999` (99 Triliun).
- **6 digit pecahan:** hingga `0,999999`.
- **Daya Tampung:** Jauh melampaui transaksi terbesar Riau (~261 Juta USD), dengan margin keamanan lebih dari 300.000 kali lipat nilai transaksi terbesar yang tercatat.

### B. Ketentuan Presisi Mutlak (Zero-Float Guarantee):
1. **Dilarang Keras menggunakan `(float)` casting:**
   Pada angka 15 digit ke atas, casting ke `(float)` mengalami *binary float drift*.
2. **String Sanitization:**
   Parser memproses angka sebagai string murni dan memisahkan tanda titik secara manual.
3. **SQL Binding:**
   Nilai dikirimkan ke parameter PDO sebagai string numerik murni (`PDO::PARAM_STR`), menjamin penyimpanan 100% identik tanpa pembulatan atau konversi notasi ilmiah (*scientific notation*).

---

## 4. Status Verifikasi 12 File DBF Referensi BPS Pusat

Audit binary transparan menetapkan status resmi 12 berkas DBF pada `D:\MAGANG\referensi_bps`:

```
D:\MAGANG\referensi_bps\
├── ekspor_distribusi/   (3 file)
├── impor_distribusi/    (3 file)
├── ekspor_neraca/       (3 file)
└── impor_neraca/        (3 file)
```

| No | Folder Referensi | Nama Berkas DBF | Header Rec | Active Rec | Deleted (`0x2A`) | Status Audit Fisik |
|:--:|:---|:---|:---:|:---:|:---:|:---:|
| 1 | `ekspor_distribusi` | `14_Atap_ekspor_2607_POD_based.DBF` | 814 | **814** | 0 | **TERVERIFIKASI** |
| 2 | `ekspor_distribusi` | `14_Atap_ekspor_2608_POD_based.DBF` | 809 | **809** | 0 | **TERVERIFIKASI** |
| 3 | `ekspor_distribusi` | `14_Atap_ekspor_2609_POD_based.DBF` | 841 | **841** | 0 | **TERVERIFIKASI** |
| 4 | `impor_distribusi` | `14_impor_atap_kdprov_0726.DBF` | 989 | **989** | 0 | **TERVERIFIKASI** |
| 5 | `impor_distribusi` | `14_impor_atap_kdprov_0826.DBF` | 785 | **785** | 0 | **TERVERIFIKASI** |
| 6 | `impor_distribusi` | `14_impor_atap_kdprov_0926.DBF` | 931 | **931** | 0 | **TERVERIFIKASI** |
| 7 | `ekspor_neraca` | `14_Atap_ekspor_2607_PROVORIG_based.DBF` | - | - | - | **BELUM TERVERIFIKASI (TERKUNCI OS)** |
| 8 | `ekspor_neraca` | `14_Atap_ekspor_2608_PROVORIG_based.DBF` | 925 | **925** | 0 | **TERVERIFIKASI** |
| 9 | `ekspor_neraca` | `14_Atap_ekspor_2609_PROVORIG_based.DBF` | 950 | **950** | 0 | **TERVERIFIKASI** |
| 10 | `impor_neraca` | `14_impor_atap_kppbc_0726.DBF` | 1.169 | **1.169** | 0 | **TERVERIFIKASI** |
| 11 | `impor_neraca` | `14_impor_atap_kppbc_0826.DBF` | 1.021 | **1.021** | 0 | **TERVERIFIKASI** |
| 12 | `impor_neraca` | `14_impor_atap_kppbc_0926.DBF` | - | - | - | **BELUM TERVERIFIKASI (TERKUNCI OS)** |

> [!IMPORTANT]
> Berkas nomor 7 dan 12 tetap dicatat berstatus **BELUM TERVERIFIKASI**. Keduanya memiliki *Exclusive Lock* dari sistem operasi. Jumlah record aktif tidak dikarang dan tidak diasumsikan sama dengan file lain sampai file stream dapat dibuka secara alami.

---

## 5. Pemetaan Kolom Sumber ke Dua Tabel Fisik

### A. Tabel Fisik: `distribusi_transactions`

| Kolom Sumber DBF Ekspor | Kolom Sumber DBF Impor | Kolom Target Database | Tipe Data | Logika Pemetaan |
|:---|:---|:---|:---|:---|
| `YEAR` | Nama file / `MMYY` | `tahun` | SMALLINT UNSIGNED | Tahun transaksi |
| `MTH` | Nama file / `MMYY` | `bulan` | TINYINT UNSIGNED | Bulan transaksi (1-12) |
| Constant `'Ekspor'` | Constant `'Impor'` | `jenis_transaksi` | ENUM('Ekspor','Impor') | Arah perdagangan |
| `KODE_HS` (8) | `HS` (12) | `hs_id` | INT UNSIGNED (FK) | Lookup `hs_codes.hs_id` |
| `NEGARA` (3) | `K_NEGARA` (8) | `negara_id` | INT UNSIGNED (FK) | Lookup master negara BPS |
| `PROVPOD` ('14') | `KD_PROV` ('14') | `provinsi_id` | INT UNSIGNED (FK) | Pelabuhan Riau / Konsumen Riau |
| `PROVORIG` (4) | *NULL* | `kabkota_id` | INT UNSIGNED (FK, NULLable)| Kab/Kota muat di Riau |
| `PROVORIG` (4) | *NULL* | `provorig` | VARCHAR(10) (NULLable) | Raw code wilayah asal |
| `PEL_MUAT` (5) | `PEL_BONG` (5) | `pelabuhan_id` | INT UNSIGNED (FK, NULLable)| UN/LOCODE pelabuhan Riau |
| `NETTO` (N20,6) | `B[MMYY]` (N20,4) | `berat` | DECIMAL(20,6) | Berat netto (Kg) presisi penuh |
| `FOB` (N20,6) | `N[MMYY]` (N20,4) | `nilai` | DECIMAL(20,6) | Ekspor: FOB, Impor: CIF (USD) |

### B. Tabel Fisik: `neraca_transactions`

| Kolom Sumber DBF Ekspor | Kolom Sumber DBF Impor | Kolom Target Database | Tipe Data | Logika Pemetaan |
|:---|:---|:---|:---|:---|
| `YEAR` | Header / `MMYY` | `tahun` | SMALLINT UNSIGNED | Tahun transaksi |
| `MTH` | Header / `MMYY` | `bulan` | TINYINT UNSIGNED | Bulan transaksi (1-12) |
| Constant `'Ekspor'` | Constant `'Impor'` | `jenis_transaksi` | ENUM('Ekspor','Impor') | Arah perdagangan |
| `KODE_HS` | `HS` | `hs_id` | INT UNSIGNED (FK, NULLable)| `NULL` jika belum terdaftar |
| `KODE_HS` | `HS` | `hs_code_raw` | VARCHAR(20) | Kode HS raw asli |
| *NULL* / Master lookup | *NULL* / Master lookup | `hs_desc_raw` | VARCHAR(255) (NULLable) | Deskripsi komoditas |
| `KODE_HS` / master | `HS` / master | `mapping_status` | ENUM('mapped','unmapped') | Status pemetaan HS |
| `NEGARA` (3) | `K_NEGARA` (8) | `negara_id` | INT UNSIGNED (FK) | Lookup master negara |
| `PROVORIG2` ('14') | `PROV_KPPBC` ('14') | `provinsi_id` | INT UNSIGNED (FK) | Asal Komoditas / Kantor Pabean Riau |
| `PROVORIG` (4) | *NULL* | `kabkota_id` | INT UNSIGNED (FK, NULLable)| Kab/Kota produsen di Riau |
| `PEL_MUAT` (5) | `PEL_BONG` (5) | `pelabuhan_id` | INT UNSIGNED (FK, NULLable)| Pelabuhan (dapat di luar Riau) |
| *NULL* | `KPPBC` (C6) | `kode_kppbc` | VARCHAR(10) (NULLable) | **Khusus Neraca Impor** |
| *NULL* | `NAMA_KPPBC` (C30) | `nama_kppbc` | VARCHAR(100) (NULLable) | **Khusus Neraca Impor** |
| `NETTO` (N20,6) | `B[MMYY]` (N20,4) | `berat` | DECIMAL(20,6) | Berat netto (Kg) presisi penuh |
| `FOB` (N20,6) | `N[MMYY]` (N20,4) | `nilai` | DECIMAL(20,6) | Ekspor: FOB, Impor: CIF (USD) |

---

## 6. Daftar Komponen Kode yang Akan Terdampak

Berikut adalah daftar lengkap komponen kode aplikasi yang akan disesuaikan saat izin implementasi diberikan:

```
app/
├── Models/
│   ├── DistribusiTransaction.php          (Model Baru untuk distribusi_transactions)
│   ├── NeracaTransaction.php              (Model Baru untuk neraca_transactions)
│   ├── DataBatch.php                      (Relasi ke distribusi dan neraca)
│   └── Transaction.php                    (Aliaskan / persiapkan fase transisi)
├── Services/
│   ├── ExportDistributionParser.php       (Sanitisasi string DECIMAL 20,6 murni)
│   ├── ImportDistributionParser.php       (Sanitisasi string DECIMAL 20,6 murni)
│   ├── NeracaExportDbfParser.php          (Parser Baru DBF Neraca Ekspor PROVORIG)
│   ├── NeracaImportDbfParser.php          (Parser Baru DBF Neraca Impor KPPBC)
│   ├── DistributionUploadService.php      (Simpan ke distribusi_transactions, cek SHA-256)
│   ├── NeracaUploadService.php            (Simpan ke neraca_transactions, cek SHA-256)
│   └── TradeAggregationService.php        (Agregasi terpisah untuk masing-masing modul)
├── Http/Controllers/
│   ├── UploadController.php               (Orkestrasi upload Distribusi vs Neraca)
│   ├── DashboardController.php            (Konsumsi batch RILIS aktif)
│   ├── DistributionController.php         (Query ke distribusi_transactions)
│   └── NeracaController.php               (Query ke neraca_transactions)
database/migrations/
├── create_distribusi_transactions_table.php (Tabel fisik distribusi)
├── create_neraca_transactions_table.php     (Tabel fisik neraca)
└── add_fingerprint_to_data_batches.php      (file_hash, total_records, sum_nilai, sum_berat)
resources/views/
└── upload-data.blade.php                  (Excel Distribusi disabled, format DBF aktif)
```

---

## 7. Urutan Implementasi yang Aman (Safe Step-by-Step Rollout)

Pelaksanaan implementasi fisik akan dilakukan secara bertahap tanpa risiko:

1. **Fase 1: Migration Skema Database (Non-Destructive)**
   - Membuat migration tabel fisik `distribusi_transactions` dan `neraca_transactions` dengan presisi `DECIMAL(20,6)`.
   - Menambahkan kolom sidik jari `file_hash` SHA-256 pada tabel `data_batches`.
   - Tabel lama `transactions` dibiarkan utuh.
2. **Fase 2: Migrasi & Verifikasi Data Distribusi Lama**
   - Menyalin data 2.557 record dari `transactions` ke `distribusi_transactions`.
   - Menjalankan audit checksum untuk memastikan nilai dan baris 100% identik.
3. **Fase 3: Refactor Parser Distribusi & Pembuatan Parser Neraca**
   - Memperbarui `ExportDistributionParser` dan `ImportDistributionParser` dengan metode string decimal sanitization.
   - Mengimplementasikan `NeracaExportDbfParser` dan `NeracaImportDbfParser`.
4. **Fase 4: Penyesuaian Model, Service, dan Controller**
   - Mengarahkan alur data Distribusi ke `distribusi_transactions` dan alur Neraca ke `neraca_transactions`.
   - Memastikan isolasi query dashboard hanya mengonsumsi batch `RILIS` aktif.
5. **Fase 5: Penyesuaian UI Upload Data**
   - Menonaktifkan opsi Excel Distribusi di UI dengan label `(Dalam Pengembangan)`, dan mengaktifkan pilihan DBF Distribusi, DBF Neraca, serta Excel Neraca.
6. **Fase 6: Pengujian Suite Lengkap**
   - Menjalankan unit test presisi numerik, test isolasi modul, dan pengujian alur re-upload penggantian batch.
   - Verifikasi bahwa seluruh fungsionalitas berjalan lancar tanpa regresi.
