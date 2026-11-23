# SIDATEX — LAPORAN EKSEKUSI PHASE 2G.3
## Statistical Integrity, Mart Visibility Enforcement & Distribution Hardening

| Atribut | Nilai |
|---|---|
| **Fase** | Phase 2G.3 — Controlled Backend & Regression Test Execution |
| **Tanggal Eksekusi** | 2026-11-18 |
| **Target Database (Live PDO)** | `sidatex_testing` (`SELECT DATABASE() === 'sidatex_testing'`) |
| **Database Operasional (`sidatex`)** | **TIDAK TERSENTUH (ZERO TOUCH)** — 9 metrik baseline 100% identik |
| **Mutasi Data / Migration / Blade UI** | **0 migration baru, 0 mutasi permanen database, 0 perubahan Blade UI (Phase 2G.4 belum dimulai)** |
| **Backup Pra-Eksekusi** | `storage/backups/sidatex_testing_pre_2g3_20261118.sql` (`14.721.985` bytes, SHA-256 `71EC72895F7019415F87AE0EADCE36F4243FE7DD58B0252E3A62B025AE3D7C04`) |
| **Hasil PHPUnit Suite Penuh** | **165/165 PASS (1.194 assertions, 0 failures, 0 errors, 0 skips, exit code 0)** |
| **Status Review Independen** | **SELESAI — 4/4 LOLOS (`database-guardian: PASS`, `security-auditor: PASS WITH MINOR NOTES`, `statistics-auditor: PASS WITH MINOR NOTES`, `independent-reviewer: APPROVED WITH MINOR NOTES`)** |

---

## 1. Ringkasan Perubahan (5 Keputusan Persetujuan Phase 2G.3)

Seluruh pelaksanaan Phase 2G.3 mematuhi lima keputusan tertulis pengguna secara ketat tanpa menyentuh database operasional `sidatex`, tanpa migration, tanpa re-agregasi mart, tanpa perubahan `master_hs`, dan tanpa menyentuh Blade UI Phase 2G.4:

1. **Keputusan A-1 (`PRIVATE` & `RILIS`) — DITERAPKAN:**
   - Seluruh **30.147 baris historis** (`batch_id IS NULL AND pelabuhan_key = 0`) tetap dipertahankan berstatus `PRIVATE`.
   - `StatisticsCalculationService::getOfficialDashboardMetrics()` menerapkan default `$allowedStatuses = ['RILIS']` dengan helper terpusat `getAllowedStatusesForUser()`, `sanitizeAllowedStatuses()`, dan `applyMonthlySummaryVisibility()` (`LEFT JOIN data_batches` + eksklusi aktif `trash WHERE restored_at IS NULL`).
   - **Role 2 (Pegawai BPS)** hanya dapat membaca data `RILIS` (0 `PRIVATE`, 0 `DRAFT`, 0 `Trash`). **Role 1 (Super Admin)** dan **Role 3 (Viewer Eksekutif)** membaca `['RILIS', 'PRIVATE']` dengan tetap mengecualikan `DRAFT` dan `Trash`.
   - Tiga test lama diselaraskan secara eksplisit ke `['RILIS', 'PRIVATE']` untuk pengujian data historis serta ditambah assertion negatif `['RILIS']` yang membuktikan isolasi `PRIVATE`.
2. **Hardening `DistributionStatisticsService` & `DistributionController` — DITERAPKAN:**
   - Read-side visibility & Trash guard pada `DistributionStatisticsService::getOriginDistribution()` memverifikasi keberadaan batch `distribusi | Ekspor | RILIS` yang tidak berada di `trash` sebelum membaca `monthly_origin_trade_summaries`.
   - Perlindungan *single-sided* `NULL` pada `DistributionStatisticsService::getDistributionTradeBalance()` mencegah neraca palsu saat salah satu sisi (Ekspor atau Impor) belum tersedia.
   - Sanitasi respons HTTP 500 pada `DistributionController::getMetrics()` mencatat detail exception ke `Log::error()` dan hanya mengembalikan pesan generik tanpa detail SQL, path server, atau stack trace.
3. **Divergensi Klasifikasi Migas (`HS 27075000`, US\$ 252.399,00) — DITUNDA DI LUAR PHASE 2G.3:**
   - Tidak ada perubahan pada `master_hs`, `oilgrp_code`, `oilgrp_desc`, agregasi mart, maupun nilai transaksi. Didokumentasikan sebagai *technical debt* pada Bagian 12 laporan ini.
4. **Perbaikan Tabel Pertumbuhan Dashboard & Single-Sided Trade Balance — DITERAPKAN:**
   - `StatisticsCalculationService`: memperbaiki bug `-100.0%` saat tahun berjalan kosong, mengembalikan `null` (bukan `0.0`) saat pembanding tidak tersedia, mempertahankan `0.0` untuk perubahan datar riil, menggunakan BCMath scale 8 → `round(..., 2)`, menerapkan rollover MoM Januari → Desember tahun sebelumnya, dan menegakkan **Kebijakan B QoQ** (wajib 3 bulan penuh pada kedua triwulan, menolak celah bulan internal, tanpa fallback).
   - `DashboardController::data()`: memperbaiki `pivotBulanFob` `mom_growth`, `growthMomTable` (wajib bulan bersebelahan `$m - 1`), `growthQoqTable` (Kebijakan B 3 bulan penuh), serta `monthlyTradeBalance` (*single-sided* `NULL` dengan flag `has_complete`).
   - `DashboardController::index()`: menapis dropdown `$availableYears` (`distribusi_transactions`, `neraca_transactions`, dan `monthly_trade_summaries`) sesuai `$allowedStatuses` & non-Trash agar tahun `PRIVATE`/Trash tidak bocor ke Role 2.
   - `ExcelPreviewController`: menerapkan `applyMonthlySummaryVisibility(..., ['RILIS'], 'mts')` pada kedua cabang preview (DBF & Excel) dan Kebijakan B QoQ pada pratinjau DBF.
5. **Modernisasi Regression Test — DITERAPKAN:**
   - Memperbarui referensi tabel arsip `transactions` pada `SourceIsolationAndAggregationRegressionTest.php` (Tests 1–5 & 7) ke `distribusi_transactions` & `neraca_transactions`, memperketat Tests 12–14, serta menambahkan 7 feature test baru pada `Phase2gStatisticsCalculationTest.php`.

---

## 2. Daftar File yang Diubah dan Ditambahkan

| No | File | Kategori | Perubahan Utama |
|---|---|---|---|
| 1 | `app/Services/StatisticsCalculationService.php` | Backend Service | Helper visibilitas terpusat, eksklusi Trash, default `['RILIS']`, fallback dinamis `$latestYear`, BCMath `calcBpsGrowth()`, guard `null`/anti-`-100%`, Kebijakan B QoQ |
| 2 | `app/Http/Controllers/DashboardController.php` | Backend Controller | Penyaringan `$availableYears` berbasis role & non-Trash, penerusan `$allowedStatuses`, perbaikan `pivotBulanFob`, `growthMomTable`, `growthQoqTable`, & *single-sided* `NULL` `monthlyTradeBalance` |
| 3 | `app/Http/Controllers/AnalisisController.php` | Backend Controller | Penerusan eksplisit `['RILIS']` pada `getOfficialDashboardMetrics($tahun, $jenis, 'distribusi', ['RILIS'])` |
| 4 | `app/Http/Controllers/ExcelPreviewController.php` | Backend Controller | DI `StatisticsCalculationService`, filter visibilitas `['RILIS']` & non-Trash pada query historis DBF/Excel, serta Kebijakan B QoQ pratinjau |
| 5 | `app/Services/DistributionStatisticsService.php` | Backend Service | Read-side visibility & non-Trash guard pada `getOriginDistribution()` serta *single-sided* `NULL` protection pada `getDistributionTradeBalance()` |
| 6 | `app/Http/Controllers/DistributionController.php` | Backend Controller | Sanitasi respons HTTP 500 pada `getMetrics()` dengan logging internal `Log::error()` |
| 7 | `tests/Feature/Phase2gStatisticsCalculationTest.php` | Feature Test (**BARU**) | 7 skenario komprehensif: isolasi RBAC Role 1/2/3 × `RILIS`/`PRIVATE`/`DRAFT`/`Trash`/restore, guard `null`/anti-`-100%`/real-`0.0`/Jan rollover, Kebijakan B QoQ & celah internal, *single-sided* Trade Balance, read-side guard Origin Mart, sanitasi HTTP 500, dan non-leak dropdown tahun |
| 8 | `tests/Feature/ComprehensiveAuditAndSecurityTest.php` | Feature Test | Penyelarasan eksplisit `['RILIS', 'PRIVATE']` pada historis 2024 + assertion isolasi `['RILIS']` |
| 9 | `tests/Feature/SourceIsolationAndAggregationRegressionTest.php` | Feature Test | Migrasi referensi arsip `transactions` → `distribusi_transactions` & `neraca_transactions`, penyelarasan eksplisit `['RILIS', 'PRIVATE']` + isolasi `['RILIS']` (Tests 12–13), penguatan assertion `null` (Test 14) |

> **Verifikasi Batasan:** Tidak ada perubahan pada `database/migrations/`, `resources/views/` (Blade UI Phase 2G.4), `master_hs`, atau database operasional `sidatex`.

---

## 3. Penjelasan Perubahan per Service & Controller

### 3.1 `app/Services/StatisticsCalculationService.php`
- **`getAllowedStatusesForUser($user)`:** Mengembalikan `['RILIS', 'PRIVATE']` hanya untuk Role 1 (Super Admin) dan Role 3 (Viewer Eksekutif), serta `['RILIS']` untuk Role 2 (Pegawai) atau pengguna tidak terautentikasi. `DRAFT` tidak pernah diizinkan.
- **`sanitizeAllowedStatuses(array $allowedStatuses)`:** Whitelist ketat hanya menerima `'RILIS'` dan `'PRIVATE'` dengan fallback aman `['RILIS']`.
- **`applyMonthlySummaryVisibility(Builder $query, array $allowedStatuses = ['RILIS'], string $alias = 'mts')`:** Satu sumber kebenaran (*single source of truth*) untuk kueri `monthly_trade_summaries`:
  - `LEFT JOIN data_batches as b_vis ON mts.batch_id = b_vis.batch_id`
  - `WHERE mts.status_visibilitas IN ($statuses)`
  - Untuk baris unlinked historis (`batch_id IS NULL`), mengikuti `mts.status_visibilitas` (Keputusan A-1: seluruh 30.147 baris berstatus `PRIVATE`).
  - Untuk baris ter-link (`batch_id IS NOT NULL`), mewajibkan `b_vis.status IN ($statuses)` sekaligus `mts.batch_id NOT IN (SELECT batch_id FROM trash WHERE restored_at IS NULL)`.
- **`calcBpsGrowth(?string $currVal, ?string $prevVal): ?float`:** Menggunakan BCMath scale 8 (`bcdiv(bcmul(bcsub(...), '100', 8), $prevVal, 8)`) dengan pembulatan akhir `round(..., 2)`. Mengembalikan `null` bila pembanding `null` atau `<= 0`; mengembalikan `0.0` hanya jika perubahan datar riil (`$curr === $prev > 0`).
- **`getOfficialDashboardMetrics(...)`:**
  - Parameter default `$allowedStatuses = ['RILIS']`.
  - Fallback dinamis `$latestYear` ke `neraca_transactions` atau `distribusi_transactions` sesuai `$tipeSumber` (tidak lagi hardcode ke `distribusi_transactions`), terfilter status & non-Trash.
  - Memisahkan flag eksistensi bulan `$hasMonthCurrent[$m]` dan `$hasMonthPrev[$m]` dari nilai moneter `0.000000`.
  - Saat `$hasData === false` (misalnya tahun kosong `2027` sementara `2026` terisi), seluruh 4 pertumbuhan (`mtom`, `qtoq`, `yony`, `ctoc`) dijamin `null` (menghilangkan bug `-100.0%`).
  - **Kebijakan B QoQ:** Mensyaratkan seluruh 3 bulan pada triwulan berjalan (`$currQStart..$currQEnd`) **dan** seluruh 3 bulan pada triwulan tepat sebelumnya (`$prevQStart..$prevQEnd`) terisi penuh. Celah bulan internal (mis. Jul & Sep tanpa Agu) menghasilkan `qtoq = null` tanpa fallback ke triwulan sebelumnya.

### 3.2 `app/Http/Controllers/DashboardController.php`
- **`index()`:** Menentukan `$allowedStatuses` via `StatisticsCalculationService::getAllowedStatusesForUser()`, menambahkan eksklusi `trash` aktif pada `$distYears` & `$neracaYears`, serta menerapkan `applyMonthlySummaryVisibility()` pada `$historicalYears`. Dropdown tahun Pegawai (Role 2) kini hanya menampilkan tahun dengan batch `RILIS` aktif (`2026`), tanpa membocorkan tahun historis `PRIVATE` (`2023–2025`) atau tahun batch `PRIVATE`/Trash.
- **`metrics()` & `data()`:** Meneruskan `$allowedStatuses` yang sesuai peran ke `getOfficialDashboardMetrics()`.
- **`pivotBulanFob` & `growthMomTable`:** Menghitung MoM hanya terhadap bulan tepat sebelumnya (`$m - 1`, tanpa melompati bulan kosong). Bila bulan `$m - 1` tidak tersedia atau `<= 0`, mengembalikan `null`.
- **`growthQoqTable`:** Menerapkan **Kebijakan B** penuh: mengecek kelengkapan 3 bulan (`$hasFullQuarter[$q]`) pada setiap triwulan Q1–Q4; pertumbuhan QoQ bernilai `null` jika triwulan berjalan atau triwulan sebelumnya tidak lengkap 3 bulan penuh.
- **`monthlyTradeBalance`:** Menambahkan flag `has_complete = ($ekspor > 0 && $impor > 0)`. Jika hanya salah satu sisi tersedia (mis. `2026-08` hanya Impor RILIS atau `2026-09` hanya Ekspor RILIS), maka `neraca = null`, `kumulatif = null` (jika kumulatif belum memiliki kedua sisi), dan `status` menandai sisi yang belum tersedia (`Data Ekspor Belum Tersedia` / `Data Impor Belum Tersedia`).

### 3.3 `app/Http/Controllers/AnalisisController.php`
- Baris 125 secara eksplisit meneruskan `['RILIS']` pada `getOfficialDashboardMetrics($tahun, $jenis, 'distribusi', ['RILIS'])`, selaras dengan query transaksi `AnalisisController` yang secara desain menampilkan data resmi `RILIS`.

### 3.4 `app/Http/Controllers/ExcelPreviewController.php`
- Menerima `StatisticsCalculationService` melalui *constructor dependency injection*.
- Menerapkan `applyMonthlySummaryVisibility($query, ['RILIS'], 'mts')` pada query historis `monthly_trade_summaries` di cabang pratinjau DBF (`L104`) maupun pratinjau Excel (`L576`), mencegah kebocoran data `PRIVATE` atau `Trash` ke pembanding historis pratinjau.
- Menyelaraskan kalkulasi `$calcQtoq` pratinjau DBF (`L195`) ke **Kebijakan B** (wajib 3 bulan penuh pada triwulan berjalan dan triwulan tepat sebelumnya menggunakan BCMath).

### 3.5 `app/Services/DistributionStatisticsService.php`
- **`getDistributionTradeBalance()` (`L178`):** Mengembalikan `neraca_bulanan_juta = null`, `has_complete_bulanan = false`, dan status eksplisit (`Data Ekspor Belum Tersedia` / `Data Impor Belum Tersedia` / `Belum Tersedia`) saat sisi Ekspor atau Impor bulanan kosong; perlindungan identik diterapkan pada kumulatif (`neraca_kumulatif_juta`, `has_complete_kumulatif`, `status_kumulatif`).
- **`getOriginDistribution()` (`L481–505`):** Sebelum membaca `monthly_origin_trade_summaries`, melakukan verifikasi *read-side* menggunakan `$this->getReleasedBatchIds()` (`tipe_sumber = 'distribusi'`, `status = 'RILIS'`, tidak berada di `trash WHERE restored_at IS NULL`) sekaligus `distribusi_transactions.exists()` untuk `(tahun, bulan, LOWER(jenis_transaksi) = 'ekspor')` — lebih kuat dari pengecekan header `periode_tahun`/`periode_bulan` karena tetap kompatibel dengan batch multi-bulan. Bila tidak memenuhi, mengembalikan struktur kosong aman (`total_fob_juta_usd = 0`, `daerah = []`).

### 3.6 `app/Http/Controllers/DistributionController.php`
- **`getMetrics()` (`L80`):** Menangkap `\Throwable`, mencatat pesan exception, kelas, file, baris, parameter periode, dan `user_id` secara internal via `Log::error()`, serta mengembalikan respons HTTP 500 JSON generik (`Terjadi kesalahan internal saat memuat metrik distribusi.`) tanpa membocorkan detail SQL, path server, atau stack trace.

---

## 4. Perubahan Regression Test

1. **`tests/Feature/Phase2gStatisticsCalculationTest.php` (BARU — 7 test, transaksi rollback terisolasi pada tahun sentinel `2092–2097`):**
   - `test_role_visibility_isolation_for_rilis_private_draft_and_trash`
   - `test_growth_null_guards_anti_minus_100_real_zero_and_january_rollover`
   - `test_qoq_policy_b_requires_full_three_months_and_rejects_internal_gaps`
   - `test_single_sided_trade_balance_returns_null_and_complete_month_calculates_surplus`
   - `test_origin_distribution_read_side_guard_blocks_private_and_orphan_rows` (menguji blokir `PRIVATE`, blokir `RILIS` di `Trash`, sekaligus memverifikasi `RILIS` aktif tetap terbaca normal)
   - `test_distribusi_metrics_http_500_sanitizes_internal_error_details`
   - `test_dashboard_index_years_exclude_private_and_trash_for_pegawai`
2. **`tests/Feature/ComprehensiveAuditAndSecurityTest.php`:**
   - Menyelaraskan panggilan `getOfficialDashboardMetrics(2024, 'Ekspor', 'distribusi', ['RILIS', 'PRIVATE'])` secara eksplisit untuk pengujian data historis `PRIVATE` 2024, serta menambahkan assertion ketat bahwa `getOfficialDashboardMetrics(2024, 'Ekspor', 'distribusi', ['RILIS'])` mengembalikan `has_data === false`, `total_fob === 0.0`, dan seluruh pertumbuhan `null`.
3. **`tests/Feature/SourceIsolationAndAggregationRegressionTest.php`:**
   - Modernisasi Tests 1–5 & 7 dari tabel arsip `transactions` ke `distribusi_transactions` & `neraca_transactions` (tanpa menghapus test atau melemahkan assertion).
   - Tests 12 & 13: verifikasi eksplisit `['RILIS', 'PRIVATE']` menghasilkan total historis valid sekaligus `['RILIS']` mengembalikan `0.0` (mengunci Keputusan A-1).
   - Test 14: diperketat untuk mengonfirmasi seluruh 4 indikator pertumbuhan (`mtom`, `qtoq`, `yony`, `ctoc`) bernilai `null` pada periode kosong.

---

## 5. Hasil Pengujian (Targeted & Full Suite)

| Eksekusi | Perintah | Hasil | Assertions | Durasi | Exit Code |
|---|---|---|---|---|---|
| **Targeted Phase 2G.3 Suite** | `php artisan test tests/Feature/Phase2gStatisticsCalculationTest.php tests/Feature/ComprehensiveAuditAndSecurityTest.php tests/Feature/SourceIsolationAndAggregationRegressionTest.php tests/Feature/DistributionModuleTest.php tests/Feature/Phase2fSingleDashboardTest.php tests/Feature/DbfPreviewAndValidationStage2b3Test.php` | **42/42 PASS** | 287 | 7,68 s | `0` |
| **Full PHPUnit Suite** | `php artisan test` | **165/165 PASS** | **1.194** | 116,19 s | `0` |

Baseline sebelum Phase 2G.3 adalah `158/158 PASS (1.096 assertions)`. Setelah Phase 2G.3 menjadi **`165/165 PASS (1.194 assertions)`** (+7 test baru, +98 assertions tambahan, 0 test dihapus, 0 test di-skip, 0 assertion dilemahkan).

---

## 6. Bukti Perlindungan `PRIVATE` & `RILIS` (Live Verification pada `sidatex_testing`)

| Skenario Uji (`StatisticsCalculationService` / `DistributionStatisticsService`) | Scope Status | `has_data` | `total_fob` (USD) | `mtom` | `qtoq` | `yony` | `ctoc` | Keterangan |
|---|---|---|---|---|---|---|---|---|
| Distribusi Ekspor `2026` (Pegawai / Role 2) | `['RILIS']` | `true` | `2.301.809.204,5870` | `null` | `null` | `null` | `null` | Hanya Batch `#71` (`2026-09`); Batch `#720` (`2026-08 PRIVATE`) & historis terisolasi penuh |
| Distribusi Ekspor `2026` (Admin/Viewer — Role 1/3) | `['RILIS', 'PRIVATE']` | `true` | `16.963.402.385,4597` | `+3.77%` | `+22.58%` | `+45.04%` | `+6.96%` | Mencakup historis `2026-01..06`, `#85` (`2026-07`), `#720` (`2026-08`), dan `#71` (`2026-09`) |
| Distribusi Impor `2026` (Pegawai / Role 2) | `['RILIS']` | `true` | `167.347.655,0777` | `null` | `null` | `null` | `null` | Hanya Batch `#70` (`2026-08 RILIS`) |
| Neraca Ekspor `2026` (Pegawai / Role 2) | `['RILIS']` | `false` | `0,00` | `null` | `null` | `null` | `null` | Seluruh batch Neraca saat ini `PRIVATE` → 0 kebocoran ke Role 2 |
| Neraca Ekspor `2026` (Admin/Viewer — Role 1/3) | `['RILIS', 'PRIVATE']` | `true` | `4.541.019.124,3262` | `+3.93%` | `null` | `null` | `null` | Q3 hanya terisi Agu & Sep → Kebijakan B QoQ tepat mengembalikan `null` |
| Historis `2025` Distribusi Ekspor (Pegawai / Role 2) | `['RILIS']` | `false` | `0,00` | `null` | `null` | `null` | `null` | Keputusan A-1 terjaga: 30.147 baris historis `PRIVATE` tertutup bagi Role 2 |
| Historis `2025` Distribusi Ekspor (Admin/Viewer) | `['RILIS', 'PRIVATE']` | `true` | `21.421.295.812,2400` | `+31.97%` | `-3.21%` | `+31.55%` | `+17.82%` | Data historis penuh tetap utuh bagi Role 1 & Role 3 |
| Tahun Kosong `2027` (dengan `2026` terisi) | `['RILIS']` | `false` | `0,00` | `null` | `null` | `null` | `null` | Bug `-100.0%` telah ditutup total |
| Trade Balance Distribusi `2026-08` (Impor saja `RILIS`) | `RILIS` | — | Ekspor `0` / Impor `167,35 jt` | — | — | — | — | `neraca_bulanan_juta = null`, `has_complete_bulanan = false`, `Data Ekspor Belum Tersedia` |
| Trade Balance Distribusi `2026-09` (Ekspor saja `RILIS`) | `RILIS` | — | Ekspor `2.301,81 jt` / Impor `0` | — | — | — | — | `neraca_bulanan_juta = null`, `has_complete_bulanan = false`, `Data Impor Belum Tersedia`; kumulatif `Surplus +2.134,46 jt` (`has_complete_kumulatif = true`) |
| Origin Mart `2026-08` (`PRIVATE`) | `RILIS` guard | — | `0` (`daerah = []`) | — | — | — | — | Read-side guard aktif & 0 baris `2026-08` di tabel Origin Mart |
| Origin Mart `2026-09` (`Batch #71 RILIS`) | `RILIS` guard | — | `2.301,81 jt` (`13` daerah) | — | — | — | — | Data resmi `RILIS` tetap disajikan utuh |

---

## 7. Bukti Perlindungan `Trash`

- Query `applyMonthlySummaryVisibility()` mengecualikan seluruh `batch_id` yang memiliki catatan aktif pada tabel `trash` (`restored_at IS NULL`) untuk **seluruh peran** (Role 1, Role 2, maupun Role 3).
- `DashboardController::index()` mengecualikan `trash` aktif pada ketiga query pembentuk dropdown tahun (`distribusi_transactions`, `neraca_transactions`, `monthly_trade_summaries`).
- `DistributionStatisticsService::getOriginDistribution()` mengecualikan batch di `trash` (`restored_at IS NULL`) pada read-side guard `data_batches`.
- Dibuktikan secara otomatis oleh `Phase2gStatisticsCalculationTest`:
  - Batch `RILIS` Bulan 4 (`4.000.000`) di `trash` (`restored_at IS NULL`) **dikeluarkan** dari total FOB seluruh role (Role 1, 2, 3) dan membuat MoM Bulan 5 bernilai `null`.
  - Batch `RILIS` Bulan 5 (`5.000.000`) yang pernah masuk `trash` lalu dipulihkan (`restored_at IS NOT NULL`) **kembali terbaca sah**.
  - Batch `RILIS` di `trash` pada `monthly_origin_trade_summaries` diblokir penuh oleh read-side guard `getOriginDistribution()`.

---

## 8. Bukti Imutabilitas Batch `#720` dan Batch `#71`

### 8.1 Batch `#71` (`distribusi | Ekspor | 2026-09 | RILIS`) — 5/5 SHA-256 100% Identik

| Entitas | Jumlah Baris | SHA-256 Baseline | SHA-256 Pasca-Phase 2G.3 | Status |
|---|---:|---|---|---|
| `data_batches#71` | 1 | `f2ba5f17e850f5ede81e14d70ec8b3ac1b95216a7b2288f6d57562e40ea40226` | `f2ba5f17e850f5ede81e14d70ec8b3ac1b95216a7b2288f6d57562e40ea40226` | **IDENTIK** |
| `distribusi_transactions#71` | 841 | `ac739c0e484c711e4f1480eef439033c03b6d0069e9e64ea32ddb6e7f388f576` | `ac739c0e484c711e4f1480eef439033c03b6d0069e9e64ea32ddb6e7f388f576` | **IDENTIK** |
| `monthly_trade_summaries#71` | 831 | `57aac248fbec41244694ace26700c8e7ac320e74360d85fb20723b0759cfe7ed` | `57aac248fbec41244694ace26700c8e7ac320e74360d85fb20723b0759cfe7ed` | **IDENTIK** |
| `brs_indicator_summaries#71` | 47 | `4ba64668d38e0e253e34ae96081304a818e5d4c013212190636e013d55ca0cba` | `4ba64668d38e0e253e34ae96081304a818e5d4c013212190636e013d55ca0cba` | **IDENTIK** |
| `monthly_origin_trade_summaries` (`2026-09`) | 66 (total tabel: 66; `2026-08`: 0) | `eea5fa2931aee9708f8de642e1485f3ac2c3de6fa91647aea18fe7b29bf70fa1` | `eea5fa2931aee9708f8de642e1485f3ac2c3de6fa91647aea18fe7b29bf70fa1` | **IDENTIK** |

### 8.2 Batch `#720` (`distribusi | Ekspor | 2026-08 | PRIVATE`) — 100% Utuh

| Indikator | Nilai Baseline | Nilai Pasca-Phase 2G.3 | Status |
|---|---|---|---|
| `status` & `updated_at` | `PRIVATE` / `2026-11-18 07:26:09` | `PRIVATE` / `2026-11-18 07:26:09` | **IDENTIK** |
| `distribusi_transactions` | `809` baris (`US$ 2.218.155.995,502700` / `2.352.083.988,620000` kg) | `809` baris (`US$ 2.218.155.995,502700` / `2.352.083.988,620000` kg) | **IDENTIK** |
| `monthly_trade_summaries` | `797` cube (`16` pelabuhan Riau, `MAX(updated_at) 2026-11-18 07:28:04`) | `797` cube (`16` pelabuhan Riau, `MAX(updated_at) 2026-11-18 07:28:04`) | **IDENTIK** |
| `brs_indicator_summaries` | `44` baris | `44` baris | **IDENTIK** |
| Arsip DBF Privat (`1794986769_14_Atap_ekspor_2608_POD_based.DBF`) | SHA-256 `f527ff527949756193e3170fc37725f47d33ff7cfc4dc45393acd2603d132ed5` | SHA-256 `f527ff527949756193e3170fc37725f47d33ff7cfc4dc45393acd2603d132ed5` | **IDENTIK** |

---

## 9. Bukti Imutabilitas Data Historis, Neraca, Rekonsiliasi & Database Operasional `sidatex`

### 9.1 Data Historis, Neraca, Sentinel & Rekonsiliasi (`sidatex_testing`)
- **30.147 baris historis (`batch_id IS NULL AND pelabuhan_key = 0`):** `30.147` baris, status 100% `["PRIVATE"]`, full-row SHA-256 **`17cc234367f9f5fcf9819f009609c0d4391ce28529e15deef1b3ddcc01eefb33`** (**IDENTIK**).
- **2.896 baris `neraca_transactions`:** `2.896` baris, full-row SHA-256 **`63c18733cd2822a3ab066769beaba99b3c6c556725a2ffa1065500ad6105bd6d`** (**IDENTIK**).
- **Residu sentinel (`2090–2099`):** `0` pada `data_batches`, `0` pada `monthly_trade_summaries`, `0` pada `distribusi_transactions`, `0` pada `neraca_transactions`, `0` pada `monthly_origin_trade_summaries`.
- **`DataReconciliationService::auditConsistency()`:** `is_consistent = true`, `active_batches = 9`, `trashed_batches = 0`, `historical_unlinked_rows = 30.147`, `orphan_aggregations = 0`, `unmapped_ports = 0`, `unmapped_countries = 0`, `unmapped_commodities = 0`.

### 9.2 Database Operasional `sidatex` — ZERO TOUCH (9/9 Metrik Identik)

| Metrik Operasional (`sidatex`) | Baseline Pra-2G.3 | Pasca-2G.3 | Status |
|---|---:|---:|---|
| `data_batches` | 3 | 3 | **IDENTIK** |
| `distribusi_transactions` | 2.557 | 2.557 | **IDENTIK** |
| `neraca_transactions` | 0 | 0 | **IDENTIK** |
| `monthly_trade_summaries` | 34.847 | 34.847 | **IDENTIK** |
| `monthly_origin_trade_summaries` | 189 | 189 | **IDENTIK** |
| `migrations` | 26 | 26 | **IDENTIK** |
| `brs_indicator_summaries` | `NOT FOUND` | `NOT FOUND` | **IDENTIK** |
| `data_batches.MAX(updated_at)` | `2026-11-13 07:38:33` | `2026-11-13 07:38:33` | **IDENTIK** |
| `monthly_trade_summaries.MAX(updated_at)` | `2026-11-12 07:06:16` | `2026-11-12 07:06:16` | **IDENTIK** |

---

## 10. Ringkasan Git Diff (Ruang Lingkup Phase 2G.3)

- **File tracked yang dimodifikasi pada Phase 2G.3 (4 file):**
  - `app/Services/StatisticsCalculationService.php`
  - `app/Http/Controllers/DashboardController.php`
  - `app/Http/Controllers/AnalisisController.php`
  - `app/Http/Controllers/ExcelPreviewController.php`
- **File baru/untracked yang dimodifikasi atau dibuat pada Phase 2G.3 (5 file):**
  - `app/Services/DistributionStatisticsService.php` (hardening `getOriginDistribution` & `getDistributionTradeBalance`)
  - `app/Http/Controllers/DistributionController.php` (sanitasi HTTP 500 `getMetrics`)
  - `tests/Feature/Phase2gStatisticsCalculationTest.php` (**file baru — 7 feature test**)
  - `tests/Feature/ComprehensiveAuditAndSecurityTest.php` (penyelarasan `['RILIS', 'PRIVATE']` + isolasi `['RILIS']`)
  - `tests/Feature/SourceIsolationAndAggregationRegressionTest.php` (migrasi arsip `transactions` → tabel aktif + penyelarasan `['RILIS', 'PRIVATE']` + penguatan assertion `null`)
- **Zero drift di luar cakupan:** Tidak ada perubahan pada `database/migrations/`, `resources/views/`, `routes/web.php`, `master_hs`, atau direktori backup.

---

## 11. Risiko Tersisa & Prasyarat Phase 2G.4

1. **Penyelarasan Blade UI untuk Nilai `null` pada Trade Balance & Growth Tables (Masuk Ruang Lingkup Phase 2G.4):**
   - Backend `DashboardController::data()` dan `DistributionStatisticsService::getDistributionTradeBalance()` kini mengembalikan `null` untuk `neraca` / `neraca_bulanan_juta` / `neraca_kumulatif_juta` saat data *single-sided*, serta `null` untuk `growth` MoM/QoQ saat periode pembanding tidak valid.
   - Modul Blade UI (`resources/views/dashboard.blade.php` — `renderTradeBalanceTable`, `renderGrowthTables`, Chart.js, serta `resources/views/pages/distribusi-dashboard.blade.php`) **sengaja belum diubah** pada Phase 2G.3. Pada Phase 2G.4, renderer JS perlu dipastikan memformat `null` secara konsisten sebagai `—` / badge status (`Data Ekspor Belum Tersedia` / `Data Impor Belum Tersedia`) tanpa memanggil `.toFixed()` pada nilai `null`.
2. **Penyelarasan Konsumen Internal `getOfficialDashboardMetrics()` Tanpa Argumen Ke-4:**
   - Karena default kini fail-closed ke `['RILIS']`, pemanggil baru di masa depan wajib secara sadar meneruskan `$this->statService->getAllowedStatusesForUser(Auth::user())` bila memang diperuntukkan bagi Role 1/3 yang membutuhkan visibilitas `PRIVATE`.

---

## 12. Technical Debt yang Ditunda: Divergensi Klasifikasi Migas (`HS 27075000`, US\$ 252.399,00)

Sesuai **Keputusan #3**, divergensi klasifikasi Migas **ditunda di luar Phase 2G.3** dan tidak ada satu pun baris `master_hs`, mart agregasi, maupun transaksi yang diubah.

### Fakta Terverifikasi pada `sidatex_testing`
- Pada tabel `master_hs`:
  - `oilgrp_code = 1` berjumlah **64 kode HS**, identik 100% dengan `UPPER(TRIM(oilgrp_desc)) = 'MIGAS'` (**64 kode HS**).
  - `hs2 = '27'` berjumlah **92 kode HS**, sehingga terdapat **28 kode HS Bab 27** (seperti produk distilasi tar batu bara / residu non-migas) yang berstatus `oilgrp_code = 0` (`NON MIGAS`).
- Pada data transaksi aktif:
  - Ditemukan transaksi pada **`HS 27075000`** (*Oils and other products of the distillation of high temperature coal tar*, `oilgrp_code = 0`, `oilgrp_desc = 'NON MIGAS'`) dengan nilai **US\$ 252.399,00** pada `distribusi_transactions` maupun `neraca_transactions`.
- **Sumber Divergensi di Kode:**
  - **`DistributionStatisticsService.php`** (`L87–90, 107–110, 252, 301, 321, 388, 408`) mengklasifikasikan Migas berdasarkan `oilgrp_code = 1` (menempatkan `HS 27075000` di **Non-Migas**).
  - **`BrsDataProcessingService`**, **`BrsAggregationService`** (baik Tier-1 `monthly_trade_summaries` `L155` maupun Tier-2 `brs_indicator_summaries` `L450` — keduanya konsisten satu sama lain; `TradeAggregationService` sejak Phase 2G.2A mendelegasikan penuh ke `BrsAggregationService`), dan **`DashboardController.php`** (`L299–300, 330–333, 342–345`) menggunakan kondisi `hs2 = '27'` / `oilgrp_desc = 'MIGAS' OR hs2 = '27'` (menempatkan `HS 27075000` di **Migas**), sehingga menimbulkan divergensi klasifikasi sebesar **US\$ 252.399,00** antara modul distribusi (`DistributionStatisticsService`) melawan pipeline BRS & Single Dashboard (`BrsDataProcessingService`/`BrsAggregationService`/`DashboardController`).

### Rekomendasi Penanganan Pasca-Phase 2G.3 (Membutuhkan Persetujuan Metodologi BPS Terpisah)
1. **Konfirmasi Resmi Tim Statistik Distribusi & Neraca BPS Provinsi Riau:** Pastikan apakah publikasi BRS Riau menetapkan Migas secara ketat mengacu pada `oilgrp_code = 1` / `oilgrp_desc = 'MIGAS'` (standar kode klasifikasi Migas BPS RI, mengecualikan batu bara/tar HS 2701–2708) atau seluruh Bab 27.
2. **Standarisasi Satu Sumber Kebenaran:** Setelah keputusan metodologi resmi ditetapkan, seragamkan aturan klasifikasi di `DistributionStatisticsService`, `BrsDataProcessingService`, `BrsAggregationService`, dan `DashboardController` ke satu helper terpusat (disarankan `oilgrp_code = 1` sesuai master BPS resmi).
3. **Rekonsiliasi & Re-agregasi Terkendali:** Lakukan simulasi dampak sebelum/sesudah per batch dan jalankan re-agregasi terkendali hanya pada batch terdampak setelah persetujuan tertulis pengguna.

---

## 13. Hasil Tinjauan Independen (Post-Execution Review — 4/4 Lolos, 0 Blocker)

Empat subagent spesialis melakukan tinjauan independen secara paralel dengan mode ketat *read-only* (0 edit file, 0 mutasi DB):

| Subagent Independen | Verdict | Ringkasan Temuan & Verifikasi |
|---|---|---|
| **`database-guardian`** | **`PASS`** | Memverifikasi langsung backup pra-2G.3 (`14.721.985` bytes, UTF-8 tanpa BOM, trailer lengkap), kardinalitas `1:0..1` pada `LEFT JOIN data_batches` (0 multiplikasi baris), keamanan subquery `trash` terhadap 3-valued `NULL NOT IN`, pembersihan total referensi tabel arsip `transactions`, serta isolasi rollback test sentinel `2092–2097`. *(Catatan cakupan: bukti PDO/SHA-256 live bersumber dari eksekusi Orchestrator karena mode `view_file`-only.)* |
| **`security-auditor`** | **`PASS WITH MINOR NOTES`** | Memverifikasi 0 blocker keamanan (0 Critical/High/Medium): fail-closed default `['RILIS']`, whitelist `sanitizeAllowedStatuses()`, pembatasan server-side Role 2 (termasuk zero leak metadata dropdown tahun & `batchStats`), isolasi `DRAFT` & `Trash` untuk semua role, read-side guard `getOriginDistribution()`, sanitasi HTTP 500 `DistributionController::getMetrics()`, dan proteksi historis `ExcelPreviewController`. Catatan minor: unused import kosmetik `Exception` di `DistributionController.php` dan catatan guard level periode pada `monthly_origin_trade_summaries`. |
| **`statistics-auditor`** | **`PASS WITH MINOR NOTES`** | Kode backend & suite pengujian **PASS 100%** secara metodologi BPS: BCMath scale 8→2, MoM adjacent + rollover Januari→Desember, Kebijakan B QoQ (wajib 3 bulan penuh, tolak celah internal, tanpa fallback), anti-`-100%` & real `0.0`, *single-sided* `NULL` Trade Balance, dan pemisahan Distribusi vs Neraca. Koreksi atribusi kelas pada §12 (`DistributionStatisticsService` vs `BrsDataProcessingService`/`BrsAggregationService`/`DashboardController`) telah diselaraskan pada laporan final ini. |
| **`independent-reviewer`** | **`APPROVED WITH MINOR NOTES`** | Memverifikasi langsung seluruh 9 file produksi & test Phase 2G.3: 0 bug blocking, 0 test dihapus, 0 assertion dilemahkan, 0 migration, 0 perubahan Blade UI Phase 2G.4, dan 0 perubahan `master_hs`. Seluruh 3 catatan ketepatan teks laporan (§3.5, §6 `#85`, §12 atribusi Migas) telah diperbarui dalam versi final ini. *(Catatan cakupan: eksekusi shell/SQL live bersumber dari log Orchestrator.)* |

> **Penegasan Batas Tahap:** Eksekusi **Phase 2G.3 selesai 100%** dengan seluruh persyaratan integritas dan tinjauan independen terpenuhi. **Phase 2G.4 (penyelarasan Blade UI)** **BELUM DIMULAI** dan menunggu persetujuan tertulis terpisah dari pengguna.
