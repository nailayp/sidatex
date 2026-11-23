# LAPORAN AUDIT & REVIEW GIT KELOMPOK 5: UPLOAD DATA UI & FILE MANAGEMENT
**Sistem Informasi Data Ekspor-Impor (SIDATEX) — BPS Provinsi Riau**  
**Fase:** Phase 2G.10.6.4.9 — Final Git Review Kelompok 5  
**Tanggal Audit:** 20 November 2026  
**Auditor:** Lead Architect & Security-QA Specialist  
**Status Kelayakan Commit:** **LULUS (SAFE TO COMMIT)**

---

## 1. DAFTAR BERKAS KELOMPOK 5

Berdasarkan klasifikasi rencana staging bertahap pada *Phase 2G.10.6.4.4*, Kelompok 5 mencakup 6 berkas antarmuka pengguna pengunggahan data dan manajemen berkas:

| No | Berkas | Status Git | Ukuran / Baris | Peran & Fungsi Utama |
|---|---|---|---|---|
| 1 | `app/Http/Controllers/ExcelPreviewController.php` | Modified | 630 baris | Controller pratinjau instan berkas DBF & Excel, kalkulasi metrik BRS, deteksi magic bytes header, validasi kolom, dan deteksi duplikasi batch |
| 2 | `app/Http/Controllers/UploadController.php` | Modified | 1.115 baris | Controller utama alur upload, ingest DBF, manajemen batch, soft delete Trash, pemulihan batch, force delete fisik, dan ekspor data XLSX |
| 3 | `resources/views/manajemen-data.blade.php` | Modified | 1.220 baris | Tampilan utama manajemen data terunggah, ringkasan 4 kartu statistik penyimpanan, tabel riwayat batch, modal edit, dropdown aksi, dan modal telaah eksekutif |
| 4 | `resources/views/pages/neraca-upload.blade.php` | Untracked | 1.415 baris | Tampilan khusus upload dan validasi data BRS Neraca Perdagangan (`RawDataEkspor` / `RawDataImpor`), dropzone streaming, dan pratinjau terpaginasi |
| 5 | `resources/views/pages/trash-data.blade.php` | Modified | 361 baris | Tampilan modul tempat sampah (Trash Data) dokumen batch, pemulihan data atomik, dan penghapusan permanen dengan konfirmasi berlapis |
| 6 | `resources/views/upload-data.blade.php` | Modified | 1.783 baris | Antarmuka unggah data modern dengan 6-Langkah Stepper dinamis, dropzone drag-and-drop, validasi format, banner unmapped/duplikasi, dan simulasi dashboard |

---

## 2. AUDIT ALUR KERJA UPLOAD DATA (13 TAHAP)

Seluruh alur kerja dari pemilihan berkas hingga notifikasi hasil telah ditelusuri dan diverifikasi berfungsi secara end-to-end tanpa elemen semu (*mock UI*):

```mermaid
flowchart TD
    A["1. Pilih Berkas / Drag-and-Drop"] --> B["2. Deteksi Ekstensi & Ukuran <= 50MB"]
    B --> C["3. Pembacaan Magic Header (0x03/0x83/0x8B)"]
    C --> D["4. Validasi Struktur & Kolom Wajib"]
    D --> E["5. Mapping Master HS, Negara, Pelabuhan"]
    E --> F["6. Cek Duplikasi Periode Batch Aktif"]
    F --> G["7. Render Pratinjau 6 Indikator & Sampel Baris"]
    G --> H["8. Verifikasi Status Awal (PRIVATE / RILIS)"]
    H --> I["9. Konfirmasi Modal Pengguna"]
    I --> J["10. DB Ingestion Transaksional (ACID)"]
    J --> K["11. Agregasi Analytical Mart Otomatis"]
    K --> L["12. Pencatatan Audit Trail activity_logs"]
    L --> M["13. Notifikasi Berhasil & Redirect / Reset"]
```

1. **Pemilihan File & Drag and Drop:** Komponen dropzone pada `upload-data.blade.php` mendukung klik telusur maupun *drag-and-drop* dengan pendengar event `dragenter`, `dragover`, `dragleave`, dan `drop`.
2. **Pemeriksaan Format dan Ukuran:** Validasi ekstensi `.dbf`, `.xlsx`, `.xls` dan pembatasan ukuran berkas maksimum 50 MB (52.428.800 byte) berjalan ganda di sisi browser (JS) dan sisi server (`ExcelPreviewController` & `UploadController`).
3. **Pembacaan Metadata & Magic Byte Header:** Jika ekstensi tidak terbaca (misalnya di lingkungan Windows), sistem membaca 1 byte awal berkas: byte `0x03`, `0x83`, atau `0x8B` secara otomatis diidentifikasi sebagai DBF dBase III/IV.
4. **Pemilihan Parameter (Jenis & Sumber Data):** Pemilihan sumber (*Distribusi* / *Neraca*), jenis transaksi (*Ekspor* / *Impor*), serta status publikasi awal (*PRIVATE* / *RILIS*) terintegrasi dalam formulir.
5. **Validasi Struktur Berkas:** 
   - Pada berkas Distribusi: sistem memvalidasi keberadaan kolom `POD` (Pelabuhan Muat) dan `KDPROV` (Pelabuhan Bongkar).
   - Pada berkas Neraca: sistem memvalidasi keberadaan kolom `PROVORIG` (Asal Barang) dan `KPPBC` (Kantor Bea Cukai).
6. **Mapping Master Data:** Pencocokan kode HS ke `master_hs` (menggunakan 8 digit atau 6 digit subkode), kode negara ke `master_negara`, serta pelabuhan.
7. **Deteksi Duplikasi Batch:** Menghitung apakah kombinasi `tipe_sumber`, `jenis_data`, `periode_tahun`, dan `periode_bulan` sudah pernah ada dalam `data_batches` aktif. Jika terdeteksi, banner peringatan ditampilkan pada pratinjau.
8. **Pratinjau Data (Preview Stage):** Merender 4 kartu KPI utama (Total Baris, Total Nilai US$ Juta dan Nilai Eksak USD, Total Berat Ribu Ton dan Nilai Eksak Kg, Breakdown Migas & Non-Migas), tabel sampel 10 baris pertama, dan ringkasan unmapped.
9. **Konfirmasi Pemrosesan:** Modal konfirmasi modal interaktif menampilkan ringkasan berkas, jenis data, status awal, dan jumlah baris sebelum penyimpanan dieksekusi.
10. **Penyimpanan Transaksi (ACID Ingestion):** Menggunakan `DB::beginTransaction()` dan `DB::commit()` pada `UploadController::store()` didelegasikan ke `BrsDataProcessingService::storeBatchAndTransactions()`.
11. **Pembentukan Data Mart:** Agregasi granular ke `monthly_trade_summaries` dan `brs_indicator_summaries` dieksekusi secara otomatis oleh `BrsAggregationService`.
12. **Pencatatan Audit Trail:** Seluruh aksi upload, ubah status, edit, dan hapus dicatat ke tabel `activity_logs` lengkap dengan `user_id`, waktu, dan alamat IP.
13. **Notifikasi Hasil:** Stepper maju ke Tahap 6 (Sukses) dengan detail ID batch yang terbentuk dan tombol navigasi ke Manajemen Data.

---

## 3. AUDIT VALIDASI DAN PENANGANAN ANOMALI

Berdasarkan pengujian isolasi non-destruktif (*test suite* khusus 8 skenario):

1. **Penolakan Format Tidak Didukung:** Berkas selain `.dbf`, `.xlsx`, `.xls` ditolak seketika baik di client-side maupun server-side dengan kode HTTP 422. Berkas terselubung (misal PDF yang diganti nama menjadi `.dbf`) langsung digagalkan oleh pemeriksaan magic header byte `0x25` (ditolak).
2. **Penolakan Berkas 0-Byte (Kosong):** Berkas 0 byte langsung ditangkap dengan pesan: *"Berkas bukan format DBF (dBase III) yang valid."*
3. **Penolakan Berkas Rusak (Corrupt):** Berkas dengan struktur header DBF tidak lengkap ditangani dengan blok `try-catch`, mengembalikan respons JSON 422 yang informatif tanpa memicu crash server (*HTTP 500*).
4. **Kepatuhan Data Unmapped BPS:**
   - Pada data **Neraca**: kode HS yang tidak ditemukan pada master (*unmapped*) **tidak dibuang diam-diam**; baris tetap disimpan ke `neraca_transactions` dengan penanda status `mapping_status = 'unmapped'` dan dicatat dalam ringkasan `unmapped` BRS.
   - Pada data **Distribusi**: anomali HS unmapped dicatat dalam daftar kesalahan validasi baris (`detailedErrors`) dan dilaporkan kepada pengguna pada halaman pratinjau.
5. **Deteksi Duplikasi Periode:** Berkas dengan periode yang sudah terdaftar aktif (misal Juli 2026 yang sudah berada pada Batch #1) terdeteksi secara otomatis dengan menyertakan `existing_batch_id: 1` dan memunculkan banner peringatan kuning di antarmuka pengguna.
6. **Kesesuaian Angka Pratinjau vs Penyimpanan:** Rumus pada `ExcelPreviewController` menggunakan service tunggal `BrsDataProcessingService`, menjamin angka pada pratinjau 100% identik dengan hasil penyimpanan transaksi.

---

## 4. AUDIT KEAMANAN SISTEM UPLOAD (SECURITY & RBAC)

| Aspek Keamanan | Mekanisme Perlindungan | Status Evaluasi |
|---|---|---|
| **Otorisasi Server-Side** | Seluruh rute upload (`GET /upload-data`, `POST /upload-data/process`, `POST /upload-data/preview`, `GET /neraca/upload`, `POST /neraca/upload/preview`) dilindungi middleware `superadmin` dan pengecekan ganda runtime `role_id === 1`. Pegawai dan Viewer diblokir dengan respons `403 Forbidden`. | **LULUS (Ketat)** |
| **Pencegahan Path Traversal** | Nama berkas disanitasi menggunakan `basename()`, ekspresi reguler anti-pemisah `^[^\/\\\\]+$`, dan penolakan eksplisit substring `..`. | **LULUS (Aman)** |
| **Isolasi Penyimpanan Berkas** | Berkas diunggah disimpan pada private disk `local` (`storage/app/uploads/`), bukan pada direktori publik `public/`. Berkas tidak dapat diunduh via URL statis tanpa otorisasi. | **LULUS (Aman)** |
| **Pembersihan Berkas Temporer** | Berkas staging (`stage_{uuid}.dbf`) dan berkas ekspor sementara di `/tmp` selalu dibersihkan di dalam blok `finally` atau pasca-pengiriman (`deleteFileAfterSend(true)`). | **LULUS (Bersih)** |
| **Perlindungan CSRF** | Seluruh form menyertakan direktif `@csrf` dan seluruh pemanggilan AJAX `fetch()` menyertakan header `X-CSRF-TOKEN`. | **LULUS (Aman)** |
| **Pencegahan Bypass Client-Side** | Validasi diulang secara mandiri di controller; manipulasi parameter melalui Postman/Curl tetap tertahan oleh validasi Laravel Form Request dan middleware. | **LULUS (Aman)** |

---

## 5. AUDIT UI/UX DAN RESPONSIVITAS

1. **Konsistensi Tema Visual BPS Riau:**
   - Dominasi palet warna Navy BPS (`#0066cc`, `#0284c7`, `#0f172a`, `#f8fafc`).
   - Tipografi rapi menggunakan standar sistem sans-serif modern dengan kontras warna teks sesuai standar aksesibilitas WCAG AA.
2. **Indikator Progres & Interaktivitas:**
   - 6-Langkah Stepper dinamis dengan garis penghubung bertahap (`step-line active/completed`).
   - Progress bar simulasi pemeriksaan berkas (`inspectionProgressBar`).
   - Indikator pemrosesan animasi (*spinner & pulsating badge*) saat transaksi dan data mart dihitung.
3. **Responsivitas Lintas Perangkat:**
   - **Desktop Full HD (1920 × 1080):** Tampilan lebar maksimal (*fluid container* 1280px–1400px), dropzone lapang, tabel pratinjau luas.
   - **Standard Laptop (1366 × 768):** Tampilan seimbang, kartu statistik 4 kolom tertata proporsional tanpa *horizontal scroll* pada halaman utama.
   - **Tablet Portrait (1024 × 768):** Grid 4 kartu statistik otomatis membungkus (*wrap*) menjadi 2 × 2, dropzone tetap proporsional.
   - **Ponsel Pintar (375 × 667):** Formulir dan dropzone bertumpuk vertikal (*single column*), tabel pratinjau dilengkapi pembungkus *horizontal scroll* internal terisolasi sehingga bodi halaman tidak meluap (*no whole-page horizontal overflow*).

---

## 6. AUDIT INTEGRASI BACKEND & PEMETAAN RUTE

Seluruh tombol antarmuka dan form terpetakan secara presisi ke rute, controller, dan method backend:

| Komponen Antarmuka | Aksi / Method | Rute Terkait | Handler Backend |
|---|---|---|---|
| Dropzone / Tombol Periksa | `POST` AJAX | `/upload-data/preview` | `ExcelPreviewController@preview` |
| Modal Konfirmasi Simpan | `POST` AJAX | `/upload-data/process` | `UploadController@store` |
| Filter & Pencarian Batch | `GET` Form | `/manajemen-data` | `UploadController@manajemen` |
| Unduh Transaksi XLSX | `GET` Link | `/manajemen-data/{batchId}/export` | `UploadController@export` |
| Toggle Publikasi RILIS/PRIVATE | `POST` Form | `/manajemen-data/{batchId}/toggle-release` | `UploadController@toggleRelease` |
| Modal Edit Dokumen | `POST` Form | `/manajemen-data/{batchId}/update` | `UploadController@update` |
| Pindahkan ke Trash | `POST` Form | `/manajemen-data/{batchId}/delete` | `UploadController@destroy` |
| Filter & Pencarian Trash | `GET` Form | `/trash-data` | `UploadController@trash` |
| Pulihkan dari Trash | `POST` Form | `/manajemen-data/{batchId}/restore` | `UploadController@restore` |
| Hapus Permanen Dokumen | `POST` Form | `/manajemen-data/{batchId}/force-delete` | `UploadController@forceDelete` |
| Unggah Neraca Excel | `POST` AJAX | `/neraca/upload/preview` | `NeracaController@previewUpload` |
| Baris Neraca Terpaginasi | `GET` AJAX | `/neraca/upload/rows` | `NeracaController@paginatedPreview` |

*Catatan Integritas:* Tidak ditemukan satupun tombol semu (*dummy/mock button*) pada keenam berkas Kelompok 5.

---

## 7. HASIL PENGUJIAN ISOLASI NON-DESTRUKTIF

Pengujian dieksekusi secara terisolasi tanpa mengubah basis data operasional `sidatex`:

```
=================================================================
NON-DESTRUCTIVE ISOLATED UPLOAD & VALIDATION TESTS
Active DB: sidatex | Time: 2026-11-20 16:06:30
=================================================================
[PASS] Scenario 1: Valid DBF returns complete metadata & KPI
       Details: Tahun: 2026, Bulan: 7, Nilai: US$ 1,910,605,444.96
[PASS] Scenario 2: Corrupt DBF rejected gracefully with exception
       Details: Error captured: Berkas bukan format DBF (dBase III) yang valid.
[PASS] Scenario 3: Unsupported file formats & disguised non-DBF headers rejected
       Details: Allowed: dbf, xlsx, xls | First byte of %PDF: 0x25 (rejected)
[PASS] Scenario 4: 0-byte empty file rejected immediately
       Details: Exception: Berkas bukan format DBF (dBase III) yang valid.
[PASS] Scenario 5: Duplicate batch correctly detected during preview
       Details: Existing Batch ID detected: #1 for July 2026
[PASS] Scenario 6: Unmapped HS handled according to BPS methodology
       Details: Neraca Unmapped Count: 1 (retained) | Distribusi Errors: 1 (reported)
[PASS] Scenario 7: Excel missing column detector correctly identifies required columns
       Details: Identified missing: BULAN, TAHUN, KODE_NGR, NETTO (KG), PROVORIG
[PASS] Scenario 8: ACID Transaction rollback verified: 0 orphan batches, 0 orphan transactions
       Details: Batches before: 12 | Batches after: 12
=================================================================
TEST RESULTS SUMMARY: 8 PASSED, 0 FAILED (100% SUCCESS)
Active DB post-test: sidatex
Total Batches post-test: 12 (0 Perubahan)
Distribusi Tx post-test: 5.169 (0 Perubahan)
Neraca Tx post-test: 6.192 (0 Perubahan)
=================================================================
```

---

## 8. KESIMPULAN STATUS KELAYAKAN PER BERKAS

| No | Berkas | Status Evaluasi | Rekomendasi |
|---|---|---|---|
| 1 | `app/Http/Controllers/ExcelPreviewController.php` | **LULUS** | Siap commit. Bersih, aman, performa prima. |
| 2 | `app/Http/Controllers/UploadController.php` | **LULUS** | Siap commit. Transaksional ACID, anti-path traversal, RBAC teruji. |
| 3 | `resources/views/manajemen-data.blade.php` | **LULUS** | Siap commit. Fitur lengkap, responsif, terintegrasi backend penuh. |
| 4 | `resources/views/pages/neraca-upload.blade.php` | **LULUS** | Siap commit (untracked baru). Mendukung upload & validasi BRS Neraca. |
| 5 | `resources/views/pages/trash-data.blade.php` | **LULUS** | Siap commit. Modul pemulihan aman, pagination rapi, konfirmasi berlapis. |
| 6 | `resources/views/upload-data.blade.php` | **LULUS** | Siap commit. Stepper 6-langkah modern, dropzone interaktif, informatif. |

**Status Akhir Kelompok 5:** **LULUS SECARA KESELURUHAN (SAFE TO COMMIT)**  
**Pesan Commit Rekomendasi:**  
`feat(ui): redesign upload page with modern dropzone, batch management, and trash module`

---
*Laporan ini disusun secara faktual berbasis inspeksi kode sumber, audit rute Laravel, pengujian non-destruktif, dan verifikasi integritas database tanpa mengubah data operasional SIDATEX.*
