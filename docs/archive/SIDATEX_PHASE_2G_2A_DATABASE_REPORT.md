# SIDATEX — LAPORAN IMPLEMENTASI PHASE 2G.2A (DATABASE & AGGREGATION CODE INTEGRITY)

**Tanggal:** 18 November 2026  
**Status:** **SELESAI — MENUNGGU PERSETUJUAN PENGGUNA SEBELUM PHASE 2G.2B / 2G.3**  
**Koordinator:** Lead Orchestrator (`sidatex-orchestrator`)  
**Verdict Reviewer Independen (Final):**
- `database-guardian`: **`PASS`** (setelah 1 siklus perbaikan M-1, M-2, N-1, N-2)
- `statistics-auditor`: **`PASS`** (setelah 1 siklus perbaikan 5 temuan metodologi & catatan minor)
- `independent-reviewer`: **`APPROVED`** (setelah 1 siklus perbaikan FAIL-01 & FAIL-02)

> [!IMPORTANT]
> **Kepatuhan Batasan Ruang Lingkup Phase 2G.2A:**
> - **ZERO** backfill / perubahan massal pada data eksisting `sidatex_testing` (ditunda untuk **Phase 2G.2B** setelah pemetaan disetujui).
> - **30.147 baris historis (`batch_id IS NULL AND pelabuhan_key = 0`)** dan **1.459 baris unlinked 2026-08 (`batch_id IS NULL`)** pada `monthly_trade_summaries` **100% UTUH** (tidak dihapus, tidak diubah statusnya).
> - **Batch `#70` (`distribusi | Impor | 2026-08`)** tetap berstatus **`RILIS`**.
> - **Database operasional `sidatex` 100% TIDAK TERSENTUH** (0 mutasi, `migrations = 26`, `MAX(updated_at) = 2026-11-13 07:38:33`).
> - **55 berkas legacy** di `storage/app/public/uploads/` tetap utuh; `public/storage` tidak dibuat.
> - **Phase 2G.3 (`StatisticsCalculationService`) & Phase 2G.4 (Blade UI)** belum dikerjakan.

---

## 1. Verifikasi Koneksi Runtime PDO

Sebelum migrasi maupun pengujian dijalankan, koneksi PDO aktif diverifikasi langsung menggunakan `SELECT DATABASE()`:

| Pemeriksaan | Hasil Aktual | Status |
|---|---|---|
| Live PDO `SELECT DATABASE()` sebelum migrasi | `sidatex_testing` | **PASS** |
| Live PDO `SELECT DATABASE()` pada audit akhir | `sidatex_testing` | **PASS** |
| Guard `tests/TestCase.php` (`setUpTraits()`) | Aktif pada seluruh 158 pengujian PHPUnit | **PASS** |

---

## 2. Migration yang Dibuat & Pengamanan Rollback

### 2.1 Migration Baru (Additive — Dieksekusi Hanya pada `sidatex_testing`)
- File: [2026_11_18_000001_upgrade_mart_decimal_precision_to_20_6.php](file:///d:/laragon/www/sidatex/database/migrations/2026_11_18_000001_upgrade_mart_decimal_precision_to_20_6.php)
- Tujuan: Menaikkan presisi kolom moneter & berat pada seluruh tabel Analytical Mart menjadi `DECIMAL(20,6)` sesuai `AGENTS.md` §2 dan memperlebar kolom pertumbuhan menjadi `DECIMAL(10,2)` tanpa mengubah atau memutasi data eksisting.
- Verifikasi langsung `information_schema.COLUMNS` pada `sidatex_testing` pasca-migrasi:

| Tabel Mart | Kolom | Tipe Sebelum | Tipe Sesudah (`sidatex_testing`) | Nullable |
|---|---|---|---|---|
| `monthly_trade_summaries` | `total_berat` | `decimal(18,3)` | **`decimal(20,6)`** | NO |
| `monthly_trade_summaries` | `total_nilai` | `decimal(18,2)` | **`decimal(20,6)`** | NO |
| `monthly_origin_trade_summaries` | `total_berat` | `decimal(18,3)` | **`decimal(20,6)`** | NO |
| `monthly_origin_trade_summaries` | `total_nilai` | `decimal(18,2)` | **`decimal(20,6)`** | NO |
| `brs_indicator_summaries` | `nilai_usd` | `decimal(20,2)` | **`decimal(20,6)`** | NO |
| `brs_indicator_summaries` | `growth_mtom` | `decimal(8,2)` | **`decimal(10,2)`** | YES |
| `brs_indicator_summaries` | `growth_yoy` | `decimal(8,2)` | **`decimal(10,2)`** | YES |
| `brs_indicator_summaries` | `growth_ctc` | `decimal(8,2)` | **`decimal(10,2)`** | YES |

### 2.2 Pengamanan `down()` pada Migration Lama
- File: [2026_11_16_000001_enhance_trade_summaries_and_create_brs_indicators.php](file:///d:/laragon/www/sidatex/database/migrations/2026_11_16_000001_enhance_trade_summaries_and_create_brs_indicators.php#L107-L115)
- Method `up()` dipertahankan **100% immutable** (0 byte diubah).
- Ditambahkan pre-flight guard pada awal `down()` yang melempar `\RuntimeException` sebelum DDL dijalankan apabila `monthly_trade_summaries` memiliki baris `hs_id IS NULL` (melindungi data HS unmapped Neraca dari gagal rollback parsial).

---

## 3. Ringkasan Perubahan Kode (Services, Controllers, Command & Tests)

| File | Jenis | Ringkasan Perubahan & Alasan |
|---|---|---|
| [BrsAggregationService.php](file:///d:/laragon/www/sidatex/app/Services/BrsAggregationService.php) | Modified | Presisi penuh **BCMath scale 6** pada Tier-1, Tier-2, & Origin Mart; dukungan batch multi-bulan (`periode_tahun = NULL`) berurutan kronologis (`ksort`); guard struktural melindungi baris historis (`batch_id IS NULL AND pelabuhan_key = 0`); pelestarian distinct `hs_code_raw` Neraca unmapped di Tier-1; pemisahan dimensi `MIGAS_NONMIGAS / UNMAPPED` agar HS unmapped tidak masuk `NON_MIGAS` (`AGENTS.md` §7); penyebut share sektor `MIGAS`/`UNMAPPED` menggunakan `$totalUsd`; rollover MoM Januari ke Desember tahun sebelumnya (`$tahun - 1, 12`); isolasi `status_visibilitas` pada `lookupHistoricalValue()`/`lookupCumulativeValue()` agar data `PRIVATE` tidak bocor ke indikator `RILIS`; `calcGrowth()` mengembalikan `null` saat penyebut `<= 0`; penambahan `removeBatchAggregation()` & `syncOriginSummariesForPeriod()` (khusus Distribusi `RILIS`); sinkronisasi atomik `updateBatchVisibility()`. |
| [TradeAggregationService.php](file:///d:/laragon/www/sidatex/app/Services/TradeAggregationService.php) | Modified | Menghapus total fallback terlarang ke tabel arsip `transactions`; routing dinamis `distribusi_transactions` vs `neraca_transactions`; delegasi kompilasi Two-Tier Mart ke `BrsAggregationService`; perlindungan mutlak `batch_id IS NULL`; sinkronisasi `monthly_origin_trade_summaries` dibatasi ketat hanya untuk `distribusi`. |
| [UploadController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/UploadController.php) | Modified | `toggleRelease()`: tolak Trash (`422`) + sinkron atomik `data_batches`, Tier-1, Tier-2, & Origin Mart via `BrsAggregationService::updateBatchVisibility()`. `update()`: guard `409 Conflict` tabrakan periode aktif saat `jenis_data` berubah, update dinamis `$txTable` (`distribusi_transactions`/`neraca_transactions`), pembersihan mart & origin summary jenis lama *setelah* transaksi diperbarui (memperbaiki bug stale origin rows), lalu `aggregateBatch()`. `destroy()`: tolak double-trash (`409`), hitung `total_transaksi` dari `$txTable` dinamis, dan panggil `removeBatchAggregation()` secara atomik. `restore()`: guard `409` tabrakan periode aktif + `aggregateBatch()` tanpa menelan exception. `forceDelete()`: panggil `removeBatchAggregation()` sebelum hapus `data_batches` sambil mempertahankan post-commit cleanup file private dari Phase 2G.1. |
| [FilteringController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/FilteringController.php) | Modified | Menghapus hardcode `distribusi_transactions`; routing dinamis ke `neraca_transactions` saat `tipe_sumber = neraca` dengan `COALESCE(master_hs.hs_code, t.hs_code_raw)`, `COALESCE(master_hs.hs_desc, t.hs_desc_raw)`, filter `hs2` mencakup `LEFT(t.hs_code_raw, 2)`, BCMath scale 6, clamp `per_page` (1–100), serta metadata `tipe_sumber` & `nilai_label` (`FOB`/`CIF`/`Campuran`). |
| [FastExcelExporter.php](file:///d:/laragon/www/sidatex/app/Services/FastExcelExporter.php) | Modified | Routing dinamis `$txTable` + `where('t.tipe_sumber', $tipeSumber)`; dukungan `hs_code_raw`/`hs_desc_raw` Neraca; header kolom `M1` dinamis `CIF (US$)` untuk Impor vs `FOB (US$)` untuk Ekspor; nilai sel `<v>` memakai string presisi penuh 6 desimal (`bcadd(..., 6)`); menghapus label PHP liar `cJenis:`. |
| [PowerPointExportService.php](file:///d:/laragon/www/sidatex/app/Services/PowerPointExportService.php) | Modified | Routing dinamis `$txTable`; label dinamis `TOTAL NILAI CIF` (Impor) vs `TOTAL NILAI FOB` (Ekspor); BCMath scale 6; mencantumkan metodologi (`Distribusi` vs `Neraca`) & rentang bulan pada slide. |
| [DataReconciliationService.php](file:///d:/laragon/www/sidatex/app/Services/DataReconciliationService.php) | Modified | Routing dinamis `distribusi_transactions` vs `neraca_transactions`; memisahkan total `summary_by_source['distribusi']` dan `summary_by_source['neraca']` (tidak pernah menggabungkan kedua metodologi, `AGENTS.md` §6); melaporkan `neraca_unmapped_hs_preserved` & `historical_unlinked_rows` (`batch_id IS NULL`) secara terpisah tanpa false-positive orphan; toleransi rekonsiliasi eksak `0.000000` (`bccomp(..., '0', 6)`); menambahkan rekonsiliasi Tier-2 `brs_indicator_summaries`. |
| [DbfIngestionService.php](file:///d:/laragon/www/sidatex/app/Services/DbfIngestionService.php) | Modified | Finalisasi status batch & pemanggilan `BrsAggregationService::aggregateBatch()` secara atomik untuk `PRIVATE` maupun `RILIS` pada **Distribusi dan Neraca**. |
| [DistributionUploadService.php](file:///d:/laragon/www/sidatex/app/Services/DistributionUploadService.php) | Modified | Akumulasi preview memakai BCMath scale 6; penyimpanan batch `PRIVATE`/`RILIS` memanggil `BrsAggregationService::aggregateBatch()` secara atomik. |
| [BackfillMonthlyTradeSummaries.php](file:///d:/laragon/www/sidatex/app/Console/Commands/BackfillMonthlyTradeSummaries.php) | Modified | Menambahkan opsi `--sumber=all|distribusi|neraca` dan memproses batch aktif ter-link melalui `BrsAggregationService::aggregateBatch()` tanpa menyentuh `batch_id IS NULL`. *(Hanya diperbaiki kodenya; **TIDAK dieksekusi** pada Phase 2G.2A.)* |
| [Phase2gBrsDecimalPrecisionTest.php](file:///d:/laragon/www/sidatex/tests/Feature/Phase2gBrsDecimalPrecisionTest.php) | Created | 5 test: audit DDL `DECIMAL(20,6)` & `DECIMAL(10,2)`, rekonsiliasi 3-tier 6 desimal atas 100 baris Distribusi & Neraca (+ 2 HS unmapped terpisah), guard `down()` migrasi `2026_11_16_000001`, rollover MoM Jan->Des & isolasi `PRIVATE`/`RILIS`, serta pemisahan `UNMAPPED` dari `NON_MIGAS` & penyebut share sektor `MIGAS`. |
| [Phase2gNeracaFilteringAndExportTest.php](file:///d:/laragon/www/sidatex/tests/Feature/Phase2gNeracaFilteringAndExportTest.php) | Created | 3 test: isolasi Distribusi vs Neraca & `hs_code_raw` di `/filtering-data/query` (lintas role + Trash), verifikasi XML `FastExcelExporter` Neraca Impor (`CIF (US$)`, `hs_code_raw`, 6-desimal `<v>`), dan verifikasi XML `PowerPointExportService` Neraca Impor (`TOTAL NILAI CIF`). |
| [Phase2gBatchLifecycleAggregationTest.php](file:///d:/laragon/www/sidatex/tests/Feature/Phase2gBatchLifecycleAggregationTest.php) | Created | 6 test: `toggleRelease` (`PRIVATE <-> RILIS` + 422 Trash), `destroy`/`restore` (409 double-trash, 409 collision, isolasi origin Distribusi saat restore Neraca), `update` & `forceDelete` Neraca, multi-month `periode_tahun = NULL` + keutuhan `batch_id IS NULL`, `update` `RILIS` Distribusi membersihkan origin summary jenis lama, dan `update` 409 collision guard. |
| [TradeAggregationAndActivityLogsTest.php](file:///d:/laragon/www/sidatex/tests/Feature/TradeAggregationAndActivityLogsTest.php) | Modified | Dipindahkan dari tabel arsip `transactions` ke fixture sentinel `distribusi_transactions` (`2091-01`) dalam transaksi rollback + assertion eksak 6 desimal. |
| [ComprehensiveAuditAndSecurityTest.php](file:///d:/laragon/www/sidatex/tests/Feature/ComprehensiveAuditAndSecurityTest.php) | Modified | Dipindahkan dari tabel arsip `transactions` ke fixture sentinel `distribusi_transactions` (`2091-02`) dalam transaksi rollback + assertion eksak 6 desimal. |
| [Stage2bValidationAndSimulationTest.php](file:///d:/laragon/www/sidatex/tests/Feature/Stage2bValidationAndSimulationTest.php) | Modified | Dipindahkan dari tabel arsip `transactions` ke fixture sentinel `neraca_transactions` & `distribusi_transactions` (`2091-03`) dalam transaksi rollback. |
| [DataReconciliationAndIndexTest.php](file:///d:/laragon/www/sidatex/tests/Feature/DataReconciliationAndIndexTest.php) | Modified | Pengujian orphan ter-link secara deterministik (`2091-04` dalam transaksi rollback) + verifikasi `summary_by_source` & `historical_unlinked_rows`. |

---

## 4. Hasil Pengujian PHPUnit (`sidatex_testing`)

### 4.1 Focused Phase 2G.2A & Reconciliation Suites
- **Perintah:** `php artisan test tests/Feature/Phase2gBrsDecimalPrecisionTest.php tests/Feature/Phase2gNeracaFilteringAndExportTest.php tests/Feature/Phase2gBatchLifecycleAggregationTest.php tests/Feature/DataReconciliationAndIndexTest.php`
- **Hasil:** `{"tool":"phpunit","result":"passed","tests":17,"passed":17,"assertions":203,"duration_ms":25479}`
- **Exit Code:** `0`

### 4.2 Full Regression Suite
- **Perintah:** `php artisan test`
- **Hasil:** `{"tool":"phpunit","result":"passed","tests":158,"passed":158,"assertions":1088,"duration_ms":128775}`
- **Exit Code:** `0` *(158 passed, 0 failed, 0 errors, 0 skipped — naik +14 test / +198 assertions dari baseline Phase 2G.1: 144 passed, 890 assertions).*

---

## 5. Perbandingan Kondisi Database BEFORE vs AFTER

### 5.1 Database Testing (`sidatex_testing`)

| Objek / Metrik | BEFORE (Pra-2G.2A) | AFTER (Pasca-2G.2A) | Status |
|---|---|---|---|
| Runtime PDO `SELECT DATABASE()` | `sidatex_testing` | `sidatex_testing` | **VERIFIED** |
| `migrations` | `27` | **`28`** (`+2026_11_18_000001`) | **EXPECTED** |
| `data_batches` | `9` | `9` | **UTUH** |
| Batch `#70` (`distribusi \| Impor \| 2026-08`) | `RILIS` | **`RILIS`** | **UTUH** |
| `distribusi_transactions` | `3.575` | `3.575` | **UTUH** |
| `neraca_transactions` | `4.065` | `4.065` | **UTUH** |
| `monthly_trade_summaries` (total) | `39.110` | `39.110` | **UTUH** |
| `monthly_trade_summaries` (`batch_id NULL & key=0` historis) | `30.147` (`PRIVATE`) | **`30.147` (`PRIVATE`)** | **UTUH 100%** |
| `monthly_trade_summaries` (`batch_id NULL & key>0` 2026-08) | `1.459` (`PRIVATE`) | **`1.459` (`PRIVATE`)** | **UTUH 100%** |
| `monthly_origin_trade_summaries` | `189` | `189` | **UTUH** |
| `brs_indicator_summaries` | `338` | `338` | **UTUH** |
| `trash` / `comments` / sentinel `>= 2090` | `0 / 0 / 0` | `0 / 0 / 0` | **BERSIH** |

### 5.2 Database Operasional (`sidatex`) & Legacy Storage

| Objek / Metrik | BEFORE (Pra-2G.2A) | AFTER (Pasca-2G.2A) | Status |
|---|---|---|---|
| Operational `sidatex` `data_batches` | `3` | `3` | **UNTOUCHED** |
| Operational `sidatex` `distribusi_transactions` | `2.557` | `2.557` | **UNTOUCHED** |
| Operational `sidatex` `neraca_transactions` | `0` | `0` | **UNTOUCHED** |
| Operational `sidatex` `monthly_trade_summaries` | `34.847` | `34.847` | **UNTOUCHED** |
| Operational `sidatex` `monthly_origin_trade_summaries` | `189` | `189` | **UNTOUCHED** |
| Operational `sidatex` `migrations` | `26` | `26` | **UNTOUCHED** |
| Operational `sidatex` `brs_indicator_summaries` | `NOT FOUND` | `NOT FOUND` | **UNTOUCHED** |
| Operational `sidatex` `data_batches MAX(updated_at)` | `2026-11-13 07:38:33` | `2026-11-13 07:38:33` | **UNTOUCHED** |
| `storage/app/public/uploads/` file count | `55` | `55` | **UNTOUCHED** |
| `public/storage` symlink | `NO` | `NO` | **UNTOUCHED** |

---

## 6. Hasil Review Independen (Siklus 1 -> Perbaikan -> Final Sign-Off)

| Agen Reviewer | Verdict Awal | Temuan Utama Siklus 1 | Verdict Final |
|---|---|---|---|
| `database-guardian` | `PASS WITH MINOR NOTES` | M-1 (`UploadController::update()` stale origin summary & missing 409 guard), M-2 (structural guard `pelabuhan_key=0` & Jan MoM wrap), N-1/N-2. | **`PASS`** |
| `statistics-auditor` | `FAIL` | 5 temuan metodologi: unmapped HS masuk `NON_MIGAS`, share sektor `MIGAS` salah penyebut, MoM Jan `null`, lookup historis tanpa `status_visibilitas`, `DataReconciliationService` menggabung Distribusi+Neraca & false-positive orphan. | **`PASS`** |
| `independent-reviewer` | `REJECTED` (`CHANGES REQUIRED`) | FAIL-01 (urutan `removeBatchAggregation()` sebelum update `$txTable` pada `UploadController::update()`), FAIL-02 (missing 409 collision guard pada `update()`). | **`APPROVED`** |

Seluruh temuan dari siklus pertama telah diperbaiki secara tuntas, dilengkapi 4 regression tests baru, dan diverifikasi ulang lulus oleh ketiga reviewer.

---

## 7. Item yang Sengaja Ditunda untuk Tahap Berikutnya (Menunggu Persetujuan)

1. **Phase 2G.2B — Pemetaan & Sinkronisasi Terkendali Data Eksisting (`sidatex_testing` saja):**
   - Menyiapkan pemetaan eksplisit untuk: (a) **30.147 baris historis (`2023–2026/06`, `batch_id IS NULL`)** — menentukan kebijakan status publikasi (`RILIS` vs tetap `PRIVATE`), (b) **1.459 baris unlinked `2026-08` (`KEY_GT0`)** — termasuk penyelarasan visibility Batch `#70` (`RILIS`), dan (c) re-agregasi presisi `DECIMAL(20,6)` pada batch aktif ter-link di `sidatex_testing`.
2. **Phase 2G.3 — Statistical Integrity (`StatisticsCalculationService`):**
   - Penerapan filter `status_visibilitas = 'RILIS'` (serta dukungan `PRIVATE` untuk Super Admin / Viewer Eksekutif sesuai RBAC), penggantian `0.0 -> null` saat periode pembanding kosong/penyebut nol, dan **Policy B (QoQ completeness 3 bulan penuh di kedua triwulan pembanding)**.
   - Penyelarasan aturan pelabuhan Distribusi (`< 2026/07` vs `pelabuhan_key > 0`) pada fallback historis `BrsAggregationService::lookupHistoricalValue()` dan `StatisticsCalculationService`.
3. **Phase 2G.4 — Dashboard Blade UI & Browser Verification:**
   - Pembaruan tampilan `N/A` / `—` untuk pertumbuhan `null`, banner/indikator status data, dan verifikasi browser penuh.
4. **Hygiene Minor (Opsional):**
   - Pembersihan unused DI `TradeAggregationService` pada konstruktor `DbfIngestionService` dan `DistributionUploadService` setelah seluruh test/caller diselaraskan.
   - Audit & pembersihan terkendali 55 berkas legacy di `storage/app/public/uploads/` bila disetujui terpisah.

> [!NOTE]
> **Proses dihentikan sesuai instruksi pada akhir Phase 2G.2A.** Mohon tinjauan dan persetujuan Anda untuk menentukan langkah berikutnya (pemetaan **Phase 2G.2B** atau lanjut ke **Phase 2G.3**).
