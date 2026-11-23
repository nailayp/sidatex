# DOKUMENTASI STRUKTUR PROJECT SIDATEX
**Sistem Informasi Data Ekspor-Impor BPS Provinsi Riau**

---

## 1. Ringkasan Eksekutif
Aplikasi SIDATEX dikembangkan dengan kerangka kerja **Laravel** untuk mengelola, menganalisis, dan mempublikasikan data statistik perdagangan luar negeri (ekspor dan impor) Provinsi Riau. Sumber data primer berasal dari file resmi BPS berformat **dBase (.DBF)** dan **Excel (.xlsx)**.

---

## 2. Struktur Direktori Utama

```
sidatex/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ActivityLogController.php      # Audit log aktivitas sistem
│   │   │   ├── AnalisisController.php         # Analisis perdagangan ekspor-impor
│   │   │   ├── Auth/LoginController.php       # Autentikasi pengguna & peran
│   │   │   ├── DashboardController.php        # Ringkasan KPI makro perdagangan
│   │   │   ├── DistributionController.php     # Endpoint agregasi distribusi
│   │   │   ├── ExcelPreviewController.php     # Validasi pra-unggah DBF & Excel
│   │   │   ├── ExportPptController.php        # Generator slide presentasi BRS PowerPoint
│   │   │   ├── FilteringController.php        # Filter dinamis transaksi perdagangan
│   │   │   └── UploadController.php           # Unggah data, manajemen batch, dan trash
│   ├── Models/
│   │   ├── DataBatch.php                      # Model metadata batch file
│   │   ├── DistribusiTransaction.php          # Model transaksi Distribusi (POD/KDPROV Riau)
│   │   ├── NeracaTransaction.php              # Model transaksi Neraca (PROVORIG/KPPBC Riau)
│   │   ├── MonthlyTradeSummary.php            # Agregasi perdagangan bulanan 8-dimensi (Data Mart)
│   │   ├── BrsIndicatorSummary.php            # Ringkasan indikator makro BRS (KPI Mart)
│   │   ├── Transaction.php                    # Model transaksi lama (diarsipkan, read-only)
│   │   ├── MasterKabKota.php                  # Master wilayah kabupaten/kota
│   │   ├── MasterPelabuhan.php                # Master pelabuhan & kode 5-digit BPS
│   │   ├── MasterNegara.php                   # Master negara mitra dagang
│   │   ├── MasterKomoditas.php                # Master komoditas kode HS
│   │   └── MonthlyOriginTradeSummary.php      # Agregasi asal barang kabupaten/kota
│   └── Services/
│       ├── DbfReaderService.php               # Parser biner DBF BPS native
│       ├── DbfIngestionService.php            # Ingestion transaksional DBF & dispatch batch
│       ├── DbfUploadValidationService.php     # Validasi struktur & integritas berkas DBF
│       ├── BrsDataProcessingService.php       # Orkestrasi pra-pemrosesan & kalkulasi BRS
│       ├── BrsAggregationService.php          # Agregasi indikator makro BRS
│       ├── DistributionUploadService.php      # Parser & validator resolusi master pelabuhan
│       ├── NeracaUploadService.php            # Parser & validator transaksi Neraca
│       ├── ExportDistributionParser.php       # Parser khusus skema DBF ekspor
│       ├── ImportDistributionParser.php       # Parser khusus skema DBF impor
│       ├── FastExcelExporter.php              # Streaming exporter transaksi ke XLSX
│       ├── PowerPointExportService.php        # Penyusun slide presentasi PPTX otomatis
│       ├── TradeAggregationService.php        # Mesin kalkulasi agregasi data BRS
│       └── DataReconciliationService.php      # Audit konsistensi database & rekonsiliasi
├── config/                                    # Konfigurasi aplikasi, auth, dan database
├── database/
│   ├── migrations/                            # Skema struktur tabel
│   └── seeders/                               # Master data awal sistem
├── resources/
│   └── views/
│       ├── layouts/
│       │   └── app.blade.php                  # Shell antarmuka & sidebar BPS
│       ├── pages/
│       │   └── trash-data.blade.php           # Antarmuka tempat sampah / soft delete
│       ├── partials/
│       │   └── pagination.blade.php           # Komponen pagination bernomor BPS
│       ├── dashboard.blade.php                # Tampilan dashboard statistik
│       ├── manajemen-data.blade.php           # Antarmuka manajemen batch & multi-export
│       └── upload-data.blade.php              # Halaman unggah terpadu DBF & Excel
├── routes/
│   └── web.php                                # Definisi rute HTTP & otorisasi role
└── storage/
    └── app/
        ├── backups/                           # Snapshot & logical backup database
        └── public/uploads/                    # Arsip file sumber terunggah
```

---

## 3. Alur Kerja Sistem (System Workflow)

### A. Pengunggahan Data (Upload Flow)
1. **Penerimaan Berkas**: Pengguna mengunggah berkas mentah DBF atau Excel pada rute `/upload-data`.
2. **Validasi & Preview**:
   - `ExcelPreviewController` membaca berkas secara non-destruktif.
   - Sistem memvalidasi ketersediaan kolom kunci: `PEB`/`PIB`, `PEL`/`KODEPEL`, `KODENEG`, `HS`, `BERAT`, `NILAI`.
   - Resolusi pelabuhan dilakukan secara ketat (5-digit exact match, UN/LOCODE, atau 3-digit suffix). Fallback otomatis dicegah.
3. **Penyimpanan Transaksi**:
   - Berkas mentah disimpan ke direktori storage arsip.
   - Batch baru dibuat dengan status awal `PRIVATE` atau `DRAFT`.
   - Transaksi disimpan menggunakan mekanisme chunking transaksi database.

### B. Siklus Hidup Batch & Manajemen Data
- **Status Publikasi**:
  - `PRIVATE`: Hanya dapat dilihat oleh Super Admin dan Viewer Eksekutif.
  - `RILIS`: Ditampilkan pada dashboard umum, filtering data, dan laporan analisis.
- **Soft Delete (Trash)**:
  - Batch yang dihapus dipindahkan ke tabel `trash`.
  - Transaksi tidak dihapus seketika, menjaga integritas historis.
- **Pemulihan (Restore)**:
  - Mengembalikan batch ke daftar aktif di `manajemen-data`.
  - Menjalankan rekalkulasi ringkasan agregasi makro (`TradeAggregationService`) jika batch berstatus `RILIS`.
- **Hapus Permanen (Force Delete)**:
  - Menghapus referensi trash, batch, transaksi terkait, dan file fisik terkait.
  - Mengkalkulasi ulang data agregasi makro.

### C. Ekspor Multi-Format
1. **Excel (.xlsx)**: Menggunakan streaming writer untuk memproses ratusan ribu baris transaksi secara efisien tanpa kebocoran memori.
2. **PowerPoint (.pptx)**: Mengompilasi ringkasan data ekspor dan impor per komoditas serta negara tujuan menjadi draf bahan presentasi rilis Berita Resmi Statistik (BRS).
3. **Cetak / PDF**: Tampilan tabel ringkas siap cetak langsung dari browser.

---

## 4. Otorisasi & Peran Pengguna (RBAC)
- **Super Admin (Role 1)**: Akses menyeluruh ke seluruh modul, pengunggahan & validasi DBF, rilis publikasi data (PRIVATE/RILIS), soft delete & force delete, serta audit log aktivitas sistem lengkap.
- **Pegawai BPS (Role 2)**: Akses baca (*read-only*) untuk data berstatus RILIS, analisis data, dan mengekspor dokumen kerja. Akses unggah, hapus, dan data PRIVATE ditutup di sisi server (HTTP 403 Forbidden).
- **Viewer Eksekutif (Role 3)**: Memantau visualisasi dashboard eksekutif untuk data RILIS dan data PRIVATE telaah internal, tanpa hak upload atau manipulasi data.
