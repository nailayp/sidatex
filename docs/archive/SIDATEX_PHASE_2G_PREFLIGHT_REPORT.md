# SIDATEX — PHASE 0: PRE-FLIGHT VERIFICATION REPORT (PHASE 2G)
**Dokumen:** `SIDATEX_PHASE_2G_PREFLIGHT_REPORT.md`  
**Tanggal:** 18 November 2026  
**Koordinator:** `sidatex-orchestrator` (Lead Orchestrator)  
**Tim Verifikasi Phase 0:** `database-guardian`, `security-auditor`, `test-engineer`, `independent-reviewer`  
**Mode Eksekusi:** **STRICT READ-ONLY** (0 file kode/konfigurasi/migrasi diubah, 0 operasi tulis database, 0 mutasi pada `sidatex` maupun `sidatex_testing`)  
**Status:** **APPROVED** *(Pasca-penyesuaian urutan Section 5 sesuai tinjauan `independent-reviewer`; berhenti di sini dan menunggu persetujuan pengguna untuk memulai implementasi)*

---

## 1. RINGKASAN HASIL VERIFIKASI PRE-FLIGHT

Seluruh item pre-flight lokal pada `sidatex_testing`, database operasional `sidatex`, dan workspace telah diverifikasi secara faktual melalui kueri **read-only (`SELECT` / `SHOW` / `INFORMATION_SCHEMA`)**, pemeriksaan filesystem, grep menyeluruh lintas repositori, serta eksekusi unit guard read-only `DatabaseSafetyGuardTest` (sementara item server eksternal & pengujian pasca-implementasi tetap berstatus `NOT VERIFIED`).

| # | Item Verifikasi Phase 0 | Status | Ringkasan Bukti Faktual |
| :---: | :--- | :---: | :--- |
| **PF-01** | Koneksi PDO runtime Laravel & cache konfigurasi | **`VERIFIED`** | `bootstrap/cache/config.php` tidak ada; `DB::connection()->getDatabaseName()` dan live PDO `SELECT DATABASE()` keduanya menghasilkan **`sidatex_testing`**. |
| **PF-02** | Koneksi PDO runtime PHPUnit & urutan `enforceTestDatabaseSafety()` vs `parent::setUpTraits()` | **`VERIFIED`** | `php artisan test tests/Unit/DatabaseSafetyGuardTest.php` lulus (**4/4 passed, 8 assertions, exit 0**); terbukti di `tests/TestCase.php:15-21` guard berjalan **sesudah** `parent::setUp()` / `setUpTraits()`. |
| **PF-03** | Kondisi skema aktual & migrasi pada `sidatex_testing` | **`VERIFIED`** | 27 migrasi terpasang (terakhir `2026_11_16_000001`). Presisi aktual: `monthly_trade_summaries` (`total_berat DECIMAL(20,3)`, `total_nilai DECIMAL(20,2)`), `monthly_origin_trade_summaries` (`DECIMAL(20,2)`/`(20,2)`), `brs_indicator_summaries` (`nilai_usd DECIMAL(20,4)`, `growth_* DECIMAL(6,2)`). Nilai maksimum < 1.45 miliar (10 digit) — 100% aman untuk `DECIMAL(20,6)` (kapasitas 14 digit / ~100 triliun). Tabel arsip `transactions` masih ada (2.557 baris). |
| **PF-04** | Status data historis & visibilitas pada `sidatex_testing` | **`VERIFIED`** | **TEMUAN KRITIS:** Seluruh **39.110 baris** `monthly_trade_summaries` dan seluruh **338 baris** `brs_indicator_summaries` saat ini berstatus `status_visibilitas = 'PRIVATE'` — termasuk 30.147 baris historis `2023–2026/06` (`batch_id = NULL`, `KEY_0`) dan 768 baris Batch `#70` (`2026-08` Impor Distribusi yang berstatus `RILIS` di `data_batches`). |
| **PF-05** | Keberadaan symlink `public/storage` & audit berkas unggahan | **`VERIFIED`** | `public/storage` **tidak ada** di workspace lokal saat ini. Namun `storage/app/public/uploads/` menyimpan **61 berkas mentah BPS**: **14 Excel** (termasuk 14,4 MB BRS & 8 berkas *orphan* 0-byte PHPUnit) + **47 DBF legacy** (`2026-11-11` s.d. `2026-11-13 04:01`). Berkas DBF baru (48 berkas) tersimpan aman di `storage/app/private/uploads/`. |
| **PF-06** | Klaim bahwa DBF **saat ini** ditulis ke `public` disk | **`FALSE POSITIVE`** *(untuk penulis DBF aktif)* / **`VERIFIED`** *(untuk 47 file legacy tertinggal)* | `BrsDataProcessingService` menulis DBF baru ke disk `'local'` (`storage/app/private/uploads/`), terbukti dengan 34 file `raw_distribusi` + 14 file `raw_neraca`. Namun 47 DBF lama dari layanan sebelumnya masih tertinggal di `storage/app/public/uploads/raw_distribusi/`. |
| **PF-07** | Grep menyeluruh rute mati, `TradeAggregationService`, `transactions`, `/comments`, & hardcoding `distribusi_transactions` | **`VERIFIED`** | `dashboard.visual` & `dashboard.master` memiliki **0 referensi** di seluruh `app/`, `resources/`, `tests/`. `/comments` memiliki **0 referensi UI** & **0% cakupan tes**. Ditemukan 2 layanan tambahan terdampak hardcoding/service lama: `DataReconciliationService.php` (`L59,100,105,110,122`) & `DbfIngestionService.php` (`L29,33,311-317`), serta 7 file test membaca tabel arsip `transactions`. |
| **PF-08** | Kondisi baseline database operasional `sidatex` (read-only) | **`VERIFIED`** | **100% TIDAK BERUBAH:** `data_batches = 3` (`#70, #71, #74`, semua `RILIS`, `updated_at` terakhir `2026-11-13 07:38:33`), `distribusi_transactions = 2.557`, `neraca_transactions = 0`, `brs_indicator_summaries = NOT FOUND`, `migrations = 26`. |
| **PF-09** | Status symlink `public/storage` pada server staging/produksi di luar workspace lokal | **`NOT VERIFIED`** | Hanya workspace lokal `D:\laragon\www\sidatex` yang dapat diakses; status `php artisan storage:link` di lingkungan server eksternal tidak dapat diperiksa dari sini. |
| **PF-10** | Perilaku visual browser & eksekusi PHPUnit penuh pasca-perbaikan | **`NOT VERIFIED`** | Belum dijalankan karena Phase 0 bersifat *strict read-only* sebelum persetujuan implementasi. |

---

## 2. RINCIAN BUKTI FAKTUAL PHASE 0

### 2.1 Verifikasi Koneksi Runtime PDO & PHPUnit
- `bootstrap/cache/config.php`: **Tidak ada** (tidak ada cache konfigurasi basi).
- Konfigurasi aktif Laravel: `database.default = mysql`, `database = sidatex_testing`.
- `DB::connection()->getDatabaseName()`: **`sidatex_testing`**.
- Kueri PDO langsung `SELECT DATABASE()`: **`sidatex_testing`**.
- Pengujian read-only PHPUnit:
  ```text
  Command: php artisan test tests/Unit/DatabaseSafetyGuardTest.php
  Result : {"tool":"phpunit","result":"passed","tests":4,"passed":4,"assertions":8,"duration_ms":5161}
  Exit   : 0
  ```
- Urutan lifecycle terkonfirmasi pada [tests/TestCase.php](file:///d:/laragon/www/sidatex/tests/TestCase.php#L15-L21) vs [InteractsWithTestCaseLifecycle.php](file:///d:/laragon/www/sidatex/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/InteractsWithTestCaseLifecycle.php#L100-L106): `parent::setUp()` mengeksekusi `$this->setUpTraits()` terlebih dahulu sebelum `enforceTestDatabaseSafety()` dipanggil.

---

### 2.2 Kondisi Aktual Skema & Data `sidatex_testing`

#### A. Jumlah Baris & Presisi Kolom Aktual (`INFORMATION_SCHEMA.COLUMNS`)
| Tabel (`sidatex_testing`) | Jumlah Baris | Kolom Numerik / Kunci Aktual | Kesesuaian `AGENTS.md` §2 |
| :--- | :---: | :--- | :---: |
| `distribusi_transactions` | 3.575 | `berat DECIMAL(20,6)`, `nilai DECIMAL(20,6)`, `FK batch_id CASCADE` | Sesuai |
| `neraca_transactions` | 4.065 | `berat DECIMAL(20,6)`, `nilai DECIMAL(20,6)`, `hs_id NULLABLE`, `FK batch_id CASCADE` | Sesuai |
| `transactions` *(arsip)* | 2.557 | Tabel arsip lama masih ada di DB (menjadi pemicu aktif fallback `TradeAggregationService:78-80`) | Arsip (Dilarang dibaca/ditulis) |
| `monthly_trade_summaries` | 39.110 | `total_berat DECIMAL(20,3)`, `total_nilai DECIMAL(20,2)`, `batch_id NULLABLE`, `status_visibilitas ENUM('PRIVATE','RILIS') DEFAULT 'PRIVATE'`, `hs_id NULLABLE (FK RESTRICT)` | **TIDAK SESUAI** (wajib `DECIMAL(20,6)`) |
| `monthly_origin_trade_summaries` | 189 | `total_berat DECIMAL(20,2)`, `total_nilai DECIMAL(20,2)` (`max_berat=1,22M`, `max_nilai=1,45M`) | **TIDAK SESUAI** (rekomendasi `DECIMAL(20,6)`) |
| `brs_indicator_summaries` | 338 | `nilai_usd DECIMAL(20,4)`, `growth_mtom/yoy/ctc DECIMAL(6,2)`, `UQ uq_brs_indicator_scope` | **TIDAK SESUAI** (wajib `DECIMAL(20,6)` & widen growth `DECIMAL(10,2)`) |
| `data_batches` / `trash` / `comments` | 9 / 0 / 0 | 1 batch `RILIS` (`#70`), 8 batch `PRIVATE` (`#71, #74, #81, #82, #85, #86, #87, #88`), 0 di Trash | Aktif |

- **Kapasitas Overflow Check:** Nilai absolut terbesar di seluruh tabel mart adalah `1,447,655,356.65` (10 digit bulat), jauh di bawah batas `DECIMAL(20,6)` (14 digit bulat / `99,999,999,999,999.999999`). Jumlah baris `hs_id IS NULL` di `monthly_trade_summaries` saat ini = `0`.

#### B. Pemetaan Data Historis & Linked Batches di `monthly_trade_summaries` (`sidatex_testing`)
| Sumber | Tahun & Periode | `batch_id` | `pelabuhan_key` | `status_visibilitas` Saat Ini | Jumlah Baris | Total Nilai (US\$) | Keterangan Asal Data |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `distribusi` | `2023` (Jan–Des) | `NULL` | `KEY_0` | **`PRIVATE`** | 7.938 | 18.956.580.029,18 | Historis resmi (tidak ada baris mentah di `distribusi_transactions`) |
| `distribusi` | `2024` (Jan–Des) | `NULL` | `KEY_0` | **`PRIVATE`** | 8.374 | 18.180.959.009,74 | Historis resmi (tidak ada baris mentah di `distribusi_transactions`) |
| `distribusi` | `2025` (Jan–Des) | `NULL` | `KEY_0` | **`PRIVATE`** | 9.314 | 21.421.295.812,24 | Historis resmi (tidak ada baris mentah di `distribusi_transactions`) |
| `distribusi` | `2026-01` s.d. `2026-06` (Ekspor) | `NULL` | `KEY_0` | **`PRIVATE`** | 4.521 | 10.532.831.740,41 | Historis resmi semester I 2026 (`KEY_0`) |
| `distribusi` | `2026-08` (Ekspor) | `NULL` | `KEY_GT0` | **`PRIVATE`** | 691 | 2.218.155.995,46 | Agregasi legacy pra-`2026_11_16` (tidak ada batch 2026-08 Ekspor di `data_batches`) |
| `distribusi` | `2026-08` (Impor) | `NULL` | `KEY_GT0` | **`PRIVATE`** | 768 | 167.347.655,15 | **Milik Batch `#70` (`RILIS` di `data_batches`)** — belum ter-link & masih `PRIVATE`! |
| `distribusi` | `2026-07` & `2026-09` (Ekspor & Impor) | `#85, #86, #71, #74` | `KEY_GT0` | **`PRIVATE`** | 3.532 | 4.627.391.353,58 | Linked ke 4 batch `PRIVATE` di `data_batches` |
| `neraca` | `2026-07`, `08`, `09` (Ekspor & Impor) | `#88, #82, #87, #81` | `KEY_0` & `KEY_GT0` | **`PRIVATE`** | 3.972 | 4.927.333.070,48 | Linked ke 4 batch `PRIVATE` di `data_batches` |

- **Kesimpulan Overlap:** **Tidak ada tumpang tindih periode** antara baris `batch_id IS NULL` (`2023–2026/06` & `2026-08`) dengan 8 batch ter-link (`2026-07`, `2026-08` Neraca, `2026-09`). Namun **30.147 baris historis (`2023–2026/06`) tidak memiliki data mentah di `distribusi_transactions`**, sehingga **DILARANG** melakukan *truncate* atau `backfillAll()` yang menghapus baris `batch_id IS NULL`!

---

### 2.3 Konfirmasi Keutuhan Database Operasional (`sidatex`)

Pemeriksaan read-only langsung pada koneksi `sidatex` (`SELECT DATABASE() = 'sidatex'`):

| Indikator | Baseline Historis | Kondisi Aktual (18 Nov 2026) | Status |
| :--- | :---: | :---: | :---: |
| `data_batches` | 3 | **3** (`#70, #71, #74`, semua `RILIS`, `updated_at` terakhir `2026-11-13 07:38:33`) | **IDENTIK — TIDAK BERUBAH** |
| `distribusi_transactions` | 2.557 | **2.557** | **IDENTIK — TIDAK BERUBAH** |
| `neraca_transactions` | 0 | **0** | **IDENTIK — TIDAK BERUBAH** |
| `transactions` (arsip) | 2.557 | **2.557** | **TIDAK BERUBAH** |
| `monthly_trade_summaries` | 34.847 | **34.847** | **TIDAK BERUBAH** |
| `monthly_origin_trade_summaries` | 189 | **189** | **TIDAK BERUBAH** |
| `brs_indicator_summaries` | Tidak ada | **NOT FOUND** | **TIDAK BERUBAH** |
| `trash` / `comments` | 0 / 0 | **0 / 0** | **TIDAK BERUBAH** |
| `migrations` | 26 | **26** (terakhir `2026_11_13_000001`) | **TIDAK BERUBAH** |

> [!IMPORTANT]
> Database operasional `sidatex` terkonfirmasi **100% utuh tanpa satu pun mutasi data atau skema**.

---

### 2.4 Audit Filesystem `public/storage` & Direktori Unggahan
- `d:\laragon\www\sidatex\public\storage`: **TIDAK ADA** di workspace lokal.
- `storage/app/public/uploads/excel/`: **14 berkas** (termasuk `1793872339_1. (Olah Data BRS) Ekspor LN Riau.xlsx` 14,4 MB, `1793874341_Uji_Status_Pending.xlsx` 14,4 MB, `1794284580_Ekspor.xlsx` 3,75 MB, serta **8 berkas orphan 0-byte** `boundary_50mb.xlsx`/`small_valid.xlsx` dari PHPUnit).
- `storage/app/public/uploads/raw_distribusi/`: **47 berkas DBF legacy** (`2026-11-11` s.d. `2026-11-13 04:01:00`).
- `storage/app/private/uploads/`: **34 berkas** `raw_distribusi/` + **14 berkas** `raw_neraca/` (mengonfirmasi DBF baru dari `BrsDataProcessingService` sudah aman di disk `'local'`).

---

## 3. RISIKO KRITIS YANG DITEMUKAN PADA PHASE 0

1. **Risiko Dashboard & Regresi Kosong Total Akibat 39.110 Baris Mart Berstatus `PRIVATE` (`CRITICAL`):**
   - Migrasi `2026_11_16_000001:23` memberi default `'PRIVATE'` pada seluruh baris historis `2023–2026/06` dan Batch `#70` (`RILIS`).
   - Begitu `StatisticsCalculationService` diperbaiki agar hanya membaca `RILIS`, maka 3 tes regresi yang ada (`ComprehensiveAuditAndSecurityTest:129-140`, `SourceIsolationAndAggregationRegressionTest` Test 12 `L227-243` & Test 13 `L248-255`) serta tampilan Pegawai di `sidatex_testing` akan langsung menghasilkan `0.0` kecuali: (a) baris historis resmi `2023–2026/06` (`batch_id IS NULL, KEY_0`) disinkronkan ke `'RILIS'` pada `sidatex_testing` dan Batch `#70` dire-agregasi ulang sebagai `RILIS`, serta (b) ke-3 tes tersebut menggunakan isolasi transaksi deterministik (menguji penolakan `PRIVATE` sekaligus akurasi `RILIS` tanpa melemahkan asersi!).
   - *Peringatan Rollout Masa Depan:* Database operasional `sidatex` saat ini berada di migrasi 26 (34.847 baris `monthly_trade_summaries`). Bila kelak migrasi 27 dijalankan di produksi (dengan persetujuan eksplisit), ia akan menyetel seluruh 34.847 baris ke `'PRIVATE'` jika tidak disertai langkah sinkronisasi visibilitas.
2. **Risiko Kehilangan Permanen 30.147 Baris Historis (`2023–2026/06`) Saat Backfill (`CRITICAL`):**
   - Karena baris `2023–2026/06` tidak memiliki sumber mentah di `distribusi_transactions`, perintah backfill Phase 2G.2 **wajib bersifat *batch-scoped*** dan dilarang keras menghapus baris `batch_id IS NULL`.
3. **Bom Waktu Eksposur 61 Berkas Mentah di `storage/app/public/uploads/` (`HIGH`):**
   - Walau `public/storage` belum ada secara lokal, menjalankan `php artisan storage:link` di staging/produksi akan seketika membuka 14 Excel + 47 DBF legacy ke publik tanpa login.
4. **Dampak Tambahan pada Layanan & Suite Test Lama (`HIGH`):**
   - `DataReconciliationService.php` (`L59,100,105,110,122`) dan `DbfIngestionService.php` (`L29,33,311-317`) juga meng-hardcode `distribusi_transactions` / memanggil `TradeAggregationService` lama.
   - 3 file test lama (`TradeAggregationAndActivityLogsTest`, `ComprehensiveAuditAndSecurityTest`, `Stage2bValidationAndSimulationTest`) membaca/memutasi tabel arsip `transactions` tanpa rollback, dan akan gagal bila fallback `transactions` dihapus dari `TradeAggregationService` sebelum tes-tes tersebut dialihkan ke fixture terisolasi pada `distribusi_transactions` & `neraca_transactions`.
5. **Celah Pengaman `UploadController::export()` Terhadap Kebijakan Disetujui (`HIGH`):**
   - Saat ini `export()` memiliki bypass `$isOwner` (`L457, L460`), fallback `user_id ?: 1` di `activity_logs` (`L486`), tidak memeriksa tabel `trash`, dan menolak Role 3 (`Viewer Eksekutif`) untuk batch `PRIVATE` (bertentangan dengan Kebijakan A).
6. **Celah Pengukuran Kelengkapan QoQ Internal (`MEDIUM`):**
   - Pengecekan naif `$latestMonth % 3 === 0` tidak cukup: jika Q3 hanya memiliki bulan `Jul` dan `Sep` (tanpa `Agu`), Q3 tetap **tidak lengkap** dan wajib menghasilkan `null` (`—`) sesuai Kebijakan B.

---

## 4. ITEM YANG MASIH BELUM DAPAT DIPASTIKAN (`NOT VERIFIED`)

1. **Status `public/storage` symlink pada lingkungan server staging/produksi** di luar mesin lokal `D:\laragon\www\sidatex` (harus dicek admin server sebelum deployment).
2. **Keputusan operasional atas 47 DBF legacy + 14 Excel lama** yang saat ini berada di `storage/app/public/uploads/` (apakah dipindahkan ke `storage/app/private/uploads/` dan 8 file 0-byte test dihapus pada tahap pembersihan lokal/deployment).
3. **Kewenangan ekspor `DRAFT` bagi Super Admin (Role 1):** Kebijakan A menegaskan *"Viewer Eksekutif tidak otomatis mendapatkan akses ke DRAFT. Data Trash tidak boleh diekspor."* Rekomendasi teraman `security-auditor` adalah melarang ekspor `DRAFT` dan `Trash` untuk seluruh role (termasuk Super Admin) karena `DRAFT` belum divalidasi (`AGENTS.md` §9); mohon konfirmasi bila Super Admin tetap memerlukan ekspor `DRAFT`.
4. **Verifikasi visual browser (Desktop/Tablet/Mobile) & eksekusi PHPUnit penuh pasca-perbaikan** (akan diverifikasi pada Phase 2G.1–2G.4 di `sidatex_testing`).

---

## 5. REKOMENDASI URUTAN IMPLEMENTASI PHASE 2G (SETELAH PERSETUJUAN)

| Urutan | Sub-Fase | Lingkup Utama & Penyelarasan Kebijakan | Checkpoint Wajib |
| :---: | :--- | :--- | :--- |
| **1** | **Phase 2G.0b — Safety Rails** *(awal 2G.1)* | Perkeras `tests/TestCase.php` (`setUpTraits()` hook + live PDO `SELECT DATABASE() === 'sidatex_testing'` fail-closed whitelist), perluas `DatabaseSafetyGuardTest.php`, hapus `migrate --force` di `composer.json`, ubah default `.env.example` ke `sidatex_testing`. | `DatabaseSafetyGuardTest` 100% PASS pada `sidatex_testing`. |
| **2** | **Phase 2G.1 — Security & Access Control (Mart-Independent)** | Perbaiki `UploadController::store()` (Excel -> disk `'local'` + `mimes:xlsx,xls` + rollback unlink), `forceDelete()` (`basename()`/`realpath()` + guard Trash), `export()` (**Kebijakan A:** Role 1 & Role 3 boleh ekspor aktif `RILIS`+`PRIVATE`, Role 2 hanya `RILIS`, blokir `DRAFT` & `Trash`, hapus `$isOwner` bypass, wajib log `activity_logs` dengan `user_id` asli); perkeras `CommentController` RBAC/Trash; perbaiki redirect Role 3 di `LoginController`; hapus rute mati `dashboard.visual` & `dashboard.master`; sanitasi `basename()` di `BrsDataProcessingService.php:723` & `DbfIngestionService.php:277`; non-leak metadata `DashboardController::index()`. *(Filter visibilitas mart pada `StatisticsCalculationService`/`ExcelPreviewController` dipindahkan ke Phase 2G.3 setelah sinkronisasi mart di Phase 2G.2 selesai agar tidak memicu ALL-PRIVATE blank-out pada checkpoint 2G.1.)* | Suite keamanan Phase 2G.1 + `RoleAuthorizationHardeningTest` 100% PASS + `SIDATEX_PHASE_2G_1_SECURITY_REPORT.md` + review `independent-reviewer`. |
| **3** | **Phase 2G.2 — Database & Aggregation Integrity** | Buat migrasi aditif baru `DECIMAL(20,6)` pada `monthly_trade_summaries`, `monthly_origin_trade_summaries`, & `brs_indicator_summaries` (`growth_* DECIMAL(10,2)`) pada `sidatex_testing` saja (tanpa mengubah `up()` `2026_11_16_000001`); seragamkan BCMath scale 6 + `removeBatchAggregation()` + `$hsCodeRaw` di `$cubeKey` + multi-bulan Excel pada `BrsAggregationService`; hapus fallback `transactions` & hardcoding `distribusi_transactions` pada `TradeAggregationService`, `FilteringController`, `FastExcelExporter`, `PowerPointExportService`, `UploadController` (`toggleRelease`, `destroy`, `restore` dengan `409` kolisi periode, `forceDelete`, `update`), `BackfillMonthlyTradeSummaries`, `DataReconciliationService`, `DbfIngestionService`; pastikan `monthly_origin_trade_summaries` hanya mengagregasi `RILIS` non-Trash `distribusi`; sinkronkan visibilitas baris historis resmi `2023–2026/06` (`batch_id IS NULL, KEY_0`) -> `RILIS` & re-agregasi 9 batch ter-link di `sidatex_testing` saja (Batch `#70` -> `RILIS`, `#71–#88` tetap `PRIVATE`; **691 baris unlinked `2026-08` Ekspor `KEY_GT0` dipertahankan `PRIVATE`** sampai terverifikasi batch sumbernya; **DILARANG** menghapus baris historis!). | Suite presisi, siklus batch, filtering & ekspor Neraca + `TradeAggregationAndActivityLogsTest` 100% PASS + `SIDATEX_PHASE_2G_2_DATABASE_REPORT.md` + review `independent-reviewer`. |
| **4** | **Phase 2G.3 — Statistical Integrity & Mart Visibility Enforcement** | Terapkan filter `RILIS`/RBAC & non-Trash di `StatisticsCalculationService`, `DashboardController::metrics()/data()`, dan `ExcelPreviewController` (`F-01`, kini aman karena mart & baris historis `sidatex_testing` sudah sinkron di Phase 2G.2); implementasi `0.0 -> null` (Rule 7), guard tahun kosong (anti `-100%`), **Kebijakan B (QoQ mengevaluasi `$currentQuarter = ceil($latestMonth / 3)` vs `$prevQuarter` menggunakan flag keberadaan bulan eksplisit `$hasMonth[$m]`, mengembalikan `null` bila salah satu triwulan tidak lengkap 3 bulan termasuk celah bulan internal, tanpa fallback ke triwulan sebelumnya)**; perbaiki `BrsAggregationService::calcGrowth()` (`0/0 -> null`, `round(..., 2)`) dan rollover MoM Januari -> Desember $Y-1$. | Suite statistik, visibilitas `PRIVATE`/Trash & rekonsiliasi 6 desimal + audit `statistics-auditor` + `SIDATEX_PHASE_2G_3_STATISTICS_REPORT.md` + review `independent-reviewer`. |
| **5** | **Phase 2G.4 — Frontend, Regression & Final Audit** | Perbarui `dashboard.blade.php` (initial `—`, helper null-safe, non-leak count Pegawai, Chart.js resize), `pages/filtering-data.blade.php` (selector `Distribusi`/`Neraca`, FOB/CIF dinamis, reset/paginasi), `manajemen-data.blade.php` (guard tombol Super Admin, selaraskan tombol ekspor Role 3 untuk `PRIVATE` & sembunyikan pada `DRAFT`); jalankan full suite `php artisan test` + verifikasi browser Desktop/Tablet/Mobile di `sidatex_testing` + bukti baseline `sidatex` 0 mutasi. | Full suite PHPUnit 100% PASS + `SIDATEX_PHASE_2G_4_FRONTEND_REPORT.md` + `SIDATEX_PHASE_2G_FINAL_REPORT.md` + sign-off `independent-reviewer`. |

---

## 6. HASIL TINJAUAN `independent-reviewer` (PHASE 0)

> **Verdict `independent-reviewer`:** **`CHANGES REQUIRED` -> `APPROVED` pasca-penyesuaian urutan Section 5** (18 November 2026, mode *strict read-only*).  
> Bukti pre-flight `PF-01` s.d. `PF-10`, integritas baseline nol-mutasi database operasional `sidatex`, serta penyelarasan Kebijakan A (ekspor `PRIVATE` Role 3 + audit log, blokir `DRAFT`/`Trash`) dan Kebijakan B (kelengkapan 3 bulan penuh pada kedua triwulan QoQ) telah terverifikasi akurat. Seluruh 4 penyesuaian yang disyaratkan `independent-reviewer` telah diterapkan pada dokumen ini:
> 1. Filter visibilitas/Trash `StatisticsCalculationService` & `ExcelPreviewController` (`F-01`) dipindahkan dari Phase 2G.1 ke **Phase 2G.3** (setelah sinkronisasi mart & baris historis di Phase 2G.2) guna mencegah *ALL-PRIVATE blank-out* pada checkpoint 2G.1.
> 2. Disposisi 691 baris unlinked `2026-08` Ekspor (`batch_id = NULL, KEY_GT0`) ditetapkan tetap `PRIVATE` hingga diverifikasi batch sumbernya, dan sanitasi `basename()` ditambahkan di `DbfIngestionService.php:277`.
> 3. Evaluasi QoQ di Phase 2G.3 ditegaskan berbasis flag keberadaan bulan eksplisit `$hasMonth[$m]` untuk `$currentQuarter` vs `$prevQuarter` tanpa fallback ke triwulan sebelumnya.
> 4. Redaksi pembuka Section 1 diselaraskan dengan `PF-09` & `PF-10` yang tetap berstatus `NOT VERIFIED`.
>
> **Status Gerbang Phase 0:** **SIAP DITINJAU PENGGUNA.** Tidak ada perubahan kode atau database yang dilakukan; implementasi Phase 2G.1 hanya akan dimulai setelah persetujuan eksplisit pengguna.
