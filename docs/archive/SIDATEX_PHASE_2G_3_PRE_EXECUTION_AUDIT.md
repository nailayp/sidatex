# SIDATEX — PHASE 2G.3 PRE-EXECUTION AUDIT & IMPLEMENTATION PLAN (READ-ONLY)

> **Status Dokumen:** AUDIT PRA-EKSEKUSI 100% READ-ONLY — **PHASE 2G.3 BELUM DIEKSEKUSI**  
> **Tanggal Audit:** 18 November 2026  
> **Target Lingkungan:** `sidatex_testing` (terverifikasi via live PDO `SELECT DATABASE() = 'sidatex_testing'`, MySQL `8.0.30`)  
> **Database Operasional (`sidatex`):** 100% TIDAK DISENTUH (pembacaan metadata terbatas saja; seluruh 9 metrik identik)  
> **Mutasi Kode / Migration / Data / Arsip pada Tahap Ini:** **0 (NOL)**

---

## 1. Executive Summary

Berdasarkan penelusuran dokumentasi Phase 2G (`SIDATEX_PHASE_2G_REMEDIATION_PLAN.md`, `SIDATEX_PHASE_2G_PREFLIGHT_REPORT.md`, `SIDATEX_PHASE_2G_BATCH_40_FINAL_SAFETY_REVIEW.md`, dan `SIDATEX_PHASE_2G_BATCH_40_EXECUTION_REPORT.md`), audit langsung kode sumber, serta audit read-only database `sidatex_testing`, **Phase 2G.3 — Statistical Integrity & Mart Visibility Enforcement** memiliki misi utama menutup dua celah kritis/tinggi yang tersisa pasca-Phase 2G.2B & remediasi Batch `#40` (`Batch #720`):

1. **Penegakan Visibilitas Mart & Eksklusi Trash (`F-01`, `CRITICAL`):** Saat ini `StatisticsCalculationService::getOfficialDashboardMetrics()` membaca `monthly_trade_summaries` **tanpa filter `status_visibilitas`, tanpa join `data_batches`, dan tanpa eksklusi `trash`**, sementara `DashboardController::metrics()`, `DashboardController::data()`, `AnalisisController::getData()`, dan `ExcelPreviewController` (`L98–112`, `L545–559`) tidak meneruskan/menegakkan filter visibilitas berbasis peran. Bukti live pada `sidatex_testing` menunjukkan Pegawai BPS (Role 2) saat ini menerima angka **`US$ 16,963.40 Juta`** untuk Ekspor Distribusi 2026 (mencampur `30,147` historis `PRIVATE` + `#85` `PRIVATE` + `#720` `PRIVATE` + `#71` `RILIS`) dan **`US$ 4,541.02 Juta`** untuk Ekspor Neraca 2026 (padahal **0** batch Neraca berstatus `RILIS`).
2. **Kepatuhan Metodologi Pertumbuhan BPS (`F-06`, `HIGH`):** Bukti live pada `sidatex_testing` mengonfirmasi bahwa `StatisticsCalculationService` masih mengembalikan **`0.0`** ketika periode pembanding kosong (mis. `2020`, Impor Distribusi 2026 `qtoq/yony/ctoc = 0`), mengembalikan pertumbuhan palsu **`-100.0%`** untuk tahun kosong dengan tahun lalu berisi (terbukti live pada `tahun = 2027`: `has_data = false`, `yony = -100`, `ctoc = -100`), serta belum menerapkan **Kebijakan B** (QoQ wajib kelengkapan 3 bulan penuh pada kedua triwulan termasuk celah bulan internal).
3. **Temuan Lanjutan Terkait (*Carry-Over* ke Phase 2G.3 — Memerlukan Konfirmasi Cakupan):**
   - `DistributionStatisticsService::getOriginDistribution()` (`L454–498`) membaca `monthly_origin_trade_summaries` tanpa *read-side visibility guard* pada endpoint `GET /distribusi/metrics` (berisiko kebocoran struktural ke Role 2 bila terjadi write langsung).
   - `DistributionStatisticsService::getDistributionTradeBalance()` (`L178–203`) menghasilkan defisit/surplus semu saat salah satu sisi `0` (terbukti live: `2026-08` menampilkan `Defisit -167.35 Jt`, `2026-09` menampilkan `Surplus +2,301.81 Jt`).
   - Ditemukan **divergensi definisi Migas** aktual pada `master_hs` (`27` kode HS di Bab `27` ber-`oilgrp_code = 0 / NON MIGAS`, termasuk `HS 27075000` senilai **`US$ 252,399.00`** yang aktif di `distribusi_transactions` & `neraca_transactions`!) antara `DistributionStatisticsService` (`oilgrp_code = 1`) vs `BrsAggregationService` & `DashboardController` (`hs2 = '27'`).
   - Ditemukan **3 test lama** (`ComprehensiveAuditAndSecurityTest.php:209`, `SourceIsolationAndAggregationRegressionTest.php:230, 251`) yang memanggil `getOfficialDashboardMetrics()` tanpa `$allowedStatuses` terhadap data `PRIVATE` (`2024`, `2025`, `2026-07` berdasarkan Keputusan A-1) — ketiga test ini **pasti gagal** begitu default `['RILIS']` ditegakkan kecuali diselaraskan dengan eksplisit `['RILIS', 'PRIVATE']` (tanpa melemahkan assertion) sambil menambah test isolasi Role 2 (`['RILIS']`).

> **Sifat Perubahan Phase 2G.3:** Phase 2G.3 adalah fase **perubahan kode aplikasi (`app/Services/`, `app/Http/Controllers/`) & pengujian otomatis (`tests/`)** — **TIDAK memerlukan migrasi database (`0` DDL) dan TIDAK memerlukan mutasi data transaksi/mart (`0` write pada `Batch #720`, `Batch #71`, `30,147` historis, maupun `2,896` Neraca)**, kecuali bila pengguna secara eksplisit memutuskan mengubah kebijakan status historis atau migrasi tambahan.

---

## 2. Hasil Pemeriksaan Dokumentasi (Fakta vs Asumsi vs Belum Diverifikasi)

### 2.1 Dokumen yang Diperiksa
1. `SIDATEX_PHASE_2G_REMEDIATION_PLAN.md` (`L34–71`, `L233–267`, `L485–498`, `L532–579`)
2. `SIDATEX_PHASE_2G_PREFLIGHT_REPORT.md` (`L120–157`)
3. `SIDATEX_PHASE_2G_BATCH_40_REMEDIATION_PLAN.md` (`§6–§9`)
4. `SIDATEX_PHASE_2G_BATCH_40_FINAL_SAFETY_REVIEW.md` (`§4–§7`)
5. `SIDATEX_PHASE_2G_BATCH_40_EXECUTION_REPORT.md` (`§1–§8`, termasuk Addendum isolasi 3 fixture test `158/158 PASS`)

### 2.2 Klasifikasi Fakta, Asumsi, Belum Diverifikasi, dan Rekomendasi
| Kategori | Rincian Konkret |
|---|---|
| **Fakta Terverifikasi (`VERIFIED`)** | 1. Cakupan resmi Phase 2G.3 (`PREFLIGHT_REPORT.md:143`): penegakan filter `RILIS`/RBAC & non-Trash pada `StatisticsCalculationService`, `DashboardController::metrics()/data()/index()`, `AnalisisController`, dan `ExcelPreviewController`; penggantian `0.0 -> null` & anti-`-100%`; implementasi Kebijakan B (QoQ 3-bulan penuh kedua triwulan).<br>2. Bagian `BrsAggregationService::calcGrowth()` (`0/0 -> null`, `round(..., 2)`) dan rollover MoM Januari -> Desember $Y-1$ (`BrsAggregationService.php:529–530, 714–729`) **sudah selesai diimplementasikan di Phase 2G.2A**.<br>3. Keputusan A-1 (`30,147` baris historis `2023–2026-06` tetap `PRIVATE`) telah ditetapkan pengguna di Phase 2G.2B.<br>4. Remediasi Batch `#40` telah tuntas sebagai `Batch #720` (`PRIVATE`), `Batch #71` berstatus `RILIS`, `O2` tertutup (`58 -> 0`), dan PHPUnit saat ini `158/158 PASS` (`1,096` assertions). |
| **Asumsi yang Dihilangkan** | Tidak diasumsikan bahwa `30,147` baris historis akan dipromosikan ke `RILIS` (bertentangan dengan Keputusan A-1); tidak diasumsikan bahwa perubahan Migas `HS 27075000` atau `master_kabkota` `1473` otomatis masuk cakupan tanpa keputusan pengguna. |
| **Belum Dapat Diverifikasi (`NOT VERIFIED`)** | 1. Perilaku visual frontend JavaScript/Chart.js di browser saat `mtom/qtoq/yony/ctoc` bernilai `null` pada halaman `/dashboard` dan `/analisis-perdagangan` (dijadwalkan di Phase 2G.4; pada tahap audit read-only ini tidak dijalankan pengujian browser).<br>2. Apakah ada klien eksternal/cron/skrip di luar repo yang memanggil `/dashboard/metrics` atau `/distribusi/metrics` dan bergantung pada tipe `float 0.0` (tidak ditemukan pemanggil lain di dalam repo). |
| **Rekomendasi Utama** | Eksekusi Phase 2G.3 difokuskan pada **Core Scope (`F-01` + `F-06` + `ExcelPreviewController` + `AnalisisController`)** ditambah **Read-Side Guard `DistributionStatisticsService` & sanitasi error `DistributionController`** (dengan persetujuan eksplisit pengguna atas 5 pertanyaan keputusan di §10). |

---

## 3. Kondisi Aktual Database (`sidatex_testing` & Operasional `sidatex`)

Verifikasi live PDO: `SELECT DATABASE() = 'sidatex_testing'` (`MySQL 8.0.30`, InnoDB, 0 mutasi).

### 3.1 Inventaris Lengkap 9 Batch Aktif & Status Arsip Privat (`sidatex_testing`)
| `batch_id` | Sumber | Jenis | Periode | Status | Trash | Tx | Nilai (USD) | Tier-1 (`vis`) | Tier-2 (`vis`) | Arsip Privat (`storage/app/private/uploads/raw_*`) |
|---:|---|---|---|---|---|---:|---:|---|---|---|
| **`70`** | `distribusi` | `Impor` | `2026-08` | **`RILIS`** | `false` | `785` | `167,347,655.077700` | `768` (`RILIS`) | `37` (`RILIS`) | `14_impor_atap_kdprov_0826.DBF` — **`exists=false`** *(re-ingest 2G.2B langsung dari `referensi_bps`)* |
| **`71`** | `distribusi` | `Ekspor` | `2026-09` | **`RILIS`** | `false` | `841` | `2,301,809,204.587000` | `831` (`RILIS`) | `47` (`RILIS`) | `1794547157_14_Atap_ekspor_2609_POD_based.DBF` — `exists=true` (`74,458` B) |
| **`74`** | `distribusi` | `Impor` | `2026-09` | `PRIVATE` | `false` | `931` | `215,260,644.472700` | `918` (`PRIVATE`) | `38` (`PRIVATE`) | `1794547156_14_impor_atap_kdprov_0926.DBF` — `exists=true` (`183,857` B) |
| **`81`** | `neraca` | `Ekspor` | `2026-09` | `PRIVATE` | `false` | `950` | `2,314,286,425.304600` | `925` (`PRIVATE`) | `48` (`PRIVATE`) | `1794547157_14_Atap_ekspor_2609_PROVORIG_based.DBF` — `exists=true` (`84,050` B) |
| **`82`** | `neraca` | `Impor` | `2026-08` | `PRIVATE` | `false` | `1,021` | `176,287,710.172900` | `993` (`PRIVATE`) | `39` (`PRIVATE`) | `1794547157_14_impor_atap_kppbc_0826.DBF` — `exists=true` (`233,302` B) |
| **`85`** | `distribusi` | `Ekspor` | `2026-07` | `PRIVATE` | `false` | `814` | `1,910,605,444.960000` | `805` (`PRIVATE`) | `44` (`PRIVATE`) | `1794553145_14_Atap_ekspor_2607_POD_based.DBF` — `exists=true` (`72,082` B) |
| **`86`** | `distribusi` | `Impor` | `2026-07` | `PRIVATE` | `false` | `989` | `199,716,065.392700` | `978` (`PRIVATE`) | `38` (`PRIVATE`) | `1794553250_14_impor_atap_kdprov_0726.DBF` — `exists=true` (`195,283` B) |
| **`87`** | `neraca` | `Ekspor` | `2026-08` | `PRIVATE` | `false` | `925` | `2,226,732,699.021600` | `901` (`PRIVATE`) | `45` (`PRIVATE`) | `1794553301_14_Atap_ekspor_2608_PROVORIG_based.DBF` — `exists=true` (`81,850` B) |
| **`720`** | `distribusi` | `Ekspor` | `2026-08` | `PRIVATE` | `false` | `809` | `2,218,155,995.502700` | `797` (`PRIVATE`) | `44` (`PRIVATE`) | `1794986769_14_Atap_ekspor_2608_POD_based.DBF` — `exists=true` (`71,642` B) |

### 3.2 Ringkasan Integritas Relasi, Mart, Historis, Origin, & Sidik Jari Baseline
- **Relasi Batch ↔ Transaksi:** `distribusi_transactions = 5,169` baris (`0` orphan), `neraca_transactions = 2,896` baris (`0` orphan), `trashed_batches = 0`.
- **`DataReconciliationService::auditConsistency()`:** `is_consistent = true`, `active_batches = 9`, `historical_unlinked_rows = 30,147`, `orphan_aggregations = 0`, `unmapped_ports = 0`, `unmapped_countries = 0`, `unmapped_commodities = 0`.
- **Komposisi `monthly_trade_summaries` (`39,068` total baris):**
  - `distribusi | Ekspor | LINKED | RILIS (2026)`: `831` baris (`$2,301,809,204.587000` — `#71`)
  - `distribusi | Impor | LINKED | RILIS (2026)`: `768` baris (`$167,347,655.077700` — `#70`)
  - `distribusi | Ekspor | LINKED | PRIVATE (2026)`: `1,602` baris (`$4,128,761,440.462700` — `#85 + #720`)
  - `distribusi | Impor | LINKED | PRIVATE (2026)`: `1,896` baris (`$414,976,709.865400` — `#86 + #74`)
  - `distribusi | Ekspor | UNLINKED | PRIVATE (2023–2026-06)`: **`30,147` baris** (`$69,091,666,591.570000`, `pelabuhan_key = 0`)
  - `neraca | Ekspor | LINKED | PRIVATE (2026)`: `1,826` baris (`$4,541,019,124.326200` — `#87 + #81`)
  - `neraca | Impor | LINKED | PRIVATE (2026)`: `993` baris (`$176,287,710.172900` — `#82`)
- **`monthly_origin_trade_summaries`:** Tepat **`66` baris** (`2026-09 ekspor`, milik `Batch #71` `RILIS`; `0` baris `2026-08`).
- **`activity_logs`:** `250` total entri (terbaru `log_id = 525`).
- **Sidik Jari Baseline Imutabel (Harus Tetap 100% Identik Pasca-Phase 2G.3):**
  - `Batch #71` (`RILIS`): `row = f2ba5f17e850f5ede81e14d70ec8b3ac1b95216a7b2288f6d57562e40ea40226`, `tx(841) = ac739c0e484c711e4f1480eef439033c03b6d0069e9e64ea32ddb6e7f388f576`, `mts(831) = 57aac248fbec41244694ace26700c8e7ac320e74360d85fb20723b0759cfe7ed`, `brs(47) = 4ba64668d38e0e253e34ae96081304a818e5d4c013212190636e013d55ca0cba`, `origin2609(66) = eea5fa2931aee9708f8de642e1485f3ac2c3de6fa91647aea18fe7b29bf70fa1`.
  - `Batch #720` (`PRIVATE`): `809` tx, canonical SHA-256 `028082b5015240f32c2fa0839f37cb49c21296502ac9cc882c8597a0f2d19507`, `797` MTS, `44` BRS, arsip SHA-256 `f527ff527949756193e3170fc37725f47d33ff7cfc4dc45393acd2603d132ed5`.
  - `30,147` Historis (`pelabuhan_key = 0`): `17cc234367f9f5fcf9819f009609c0d4391ce28529e15deef1b3ddcc01eefb33`.
  - `2,896` `neraca_transactions`: `63c18733cd2822a3ab066769beaba99b3c6c556725a2ffa1065500ad6105bd6d`.
- **Database Operasional `sidatex` (Metadata Read-Only Terbatas):** `data_batches = 3`, `distribusi_transactions = 2,557`, `neraca_transactions = 0`, `monthly_trade_summaries = 34,847`, `monthly_origin_trade_summaries = 189`, `migrations = 26`, `brs_indicator_summaries = NOT FOUND`, `data_batches.MAX(updated_at) = 2026-11-13 07:38:33`, `monthly_trade_summaries.MAX(updated_at) = 2026-11-12 07:06:16` — **100% TIDAK TERSENTUH**.

---

## 4. Hasil Audit Kode Aplikasi (Bukti File & Nomor Baris)

### 4.1 Audit Komponen Core Phase 2G.3
| File & Lokasi | Temuan Faktual Terverifikasi | Bukti Live / Dampak |
|---|---|---|
| `app/Services/StatisticsCalculationService.php` (`L17–39`, `L45–64`) | Tidak menerima `$allowedStatuses`; query `monthly_trade_summaries` tanpa `status_visibilitas`, tanpa `LEFT JOIN data_batches`, tanpa eksklusi `trash`; fallback `$latestYear` (`L32`) meng-hardcode `distribusi_transactions` meski `$tipeSumber = 'neraca'`. | **CRITICAL (`F-01`):** Ekspor Distribusi 2026 mengembalikan `$16,963.40M` (mencampur `PRIVATE`) dan Ekspor Neraca 2026 mengembalikan `$4,541.02M` (padahal 0 batch Neraca `RILIS`). |
| `app/Services/StatisticsCalculationService.php` (`L67–139`) | Menggunakan PHP `float` (bukan BCMath); `L90–96` default `$latestMonth = 1` meski `$totalFob == 0`; `L99, 125, 129, 138` mengembalikan `0.0` saat pembanding kosong; `L108–125` QoQ tidak mengecek kelengkapan 3 bulan penuh (`Kebijakan B`). | **HIGH (`F-06`):** Live proof `tahun = 2027` mengembalikan `has_data = false` dengan **`yony = -100, ctoc = -100`**; `tahun = 2020` & Impor Distribusi 2026 mengembalikan `0` alih-alih `null`. |
| `app/Http/Controllers/DashboardController.php` (`L48–65`, `L185–197`, `L625–629`) | `index()` (`L48–65`) tidak mengecualikan `trash` pada `$distYears`/`$neracaYears` dan tidak memfilter `status_visibilitas`/Trash pada `$historicalYears`; `metrics()` (`L191`) & `data()` (`L625`) tidak meneruskan `$allowedStatuses` ke `StatisticsCalculationService`. | **CRITICAL (`F-01`):** Role 2 Pegawai melihat tahun `PRIVATE` di dropdown dan menerima angka pertumbuhan `mtom/qtoq/yony/ctoc` yang tercemar `PRIVATE`/Trash (kontradiksi dengan `total_fob` di sebelahnya). |
| `app/Http/Controllers/DashboardController.php` (`L489–562`, `L605–618`) | Tab 2/3 (`pivotBulanFob`, `growthMomTable`, `growthQoqTable`) membandingkan terhadap bulan/kuartal non-nol sebelumnya (bukan `$m-1` / kuartal 3-bulan penuh yang berdekatan); Tab 4 `monthlyTradeBalance` menghitung `$bal = $eVal - $iVal` meski satu sisi `0`. | **MEDIUM:** Potensi label MoM/QoQ menyesatkan bila ada celah bulan, serta saldo bulanan negatif semu saat hanya satu sisi perdagangan tersedia. |
| `app/Http/Controllers/AnalisisController.php` (`L125`) | `L52–61` sudah ketat `RILIS` non-Trash `distribusi_transactions`, tetapi `L125` memanggil `getOfficialDashboardMetrics($tahun, $jenis)` tanpa argumen `$allowedStatuses = ['RILIS']`. | **HIGH (`F-01`):** `growth` & `monthly_trend` pada `/analisis-perdagangan/data` tercemar `PRIVATE`/Trash. |
| `app/Http/Controllers/ExcelPreviewController.php` (`L98–112`, `L185–196`, `L545–559`) | Kedua query `monthly_trade_summaries` tanpa filter `status_visibilitas` / `data_batches` / `trash` (bertentangan dengan komentar `L91`); QoQ simulasi (`L185–196`) membandingkan kuartal parsial tanpa cek 3 bulan penuh. | **MEDIUM-HIGH:** Pratinjau pertumbuhan/tren saat upload tercemar data `PRIVATE`/Trash dan tidak konsisten dengan Kebijakan B. |

### 4.2 Audit Komponen Carry-Over & Temuan Baru
| File & Lokasi | Temuan Faktual Terverifikasi | Catatan |
|---|---|---|
| `app/Services/DistributionStatisticsService.php` (`L454–498`) | `getOriginDistribution()` membaca `monthly_origin_trade_summaries` langsung tanpa cek apakah ada batch `distribusi` `Ekspor` aktif berstatus `RILIS` pada `(tahun, bulan)`. | **HIGH (Struktural):** Risiko kebocoran `PRIVATE` ke Role 2 melalui `GET /distribusi/metrics` bila suatu saat write-side cleanup gagal. |
| `app/Services/DistributionStatisticsService.php` (`L178–203`) | `getDistributionTradeBalance()` mengurangkan `fobBulan - cifBulan` meski satu sisi `0`. | **MEDIUM:** Bukti live `2026-08` -> `Defisit -167.35 Jt` (`ekspor = 0`), `2026-09` -> `Surplus +2,301.81 Jt` (`impor = 0`). |
| `app/Http/Controllers/DistributionController.php` (`L82`) | Mengembalikan `$e->getMessage()` mentah pada HTTP 500 JSON. | **LOW-MEDIUM:** Potensi kebocoran detail SQL/stack internal. |
| `master_hs` vs `BrsAggregationService.php:161` vs `DistributionStatisticsService.php:87` vs `DashboardController.php:317` | Di `master_hs` (`11,653` baris): `oilgrp_code = 1` (`64` HS) === `oilgrp_desc = 'MIGAS'` (`64` HS), sedangkan `hs2 = '27'` berjumlah `92` HS (**`27` kode HS Bab 27 adalah `NON MIGAS`**). Aktif di DB: **`HS 27075000` (`NON MIGAS`, `US$ 252,399.00`)** tercatat di `distribusi_transactions` & `neraca_transactions` — diklasifikasikan MIGAS oleh `BrsAggregationService`/`DashboardController` namun NON-MIGAS oleh `DistributionStatisticsService`. | **HIGH (Metodologi BPS):** Wajib dikonfirmasi pemangku kepentingan BPS sebelum mengubah definisi Migas (jangan ubah sepihak!). |
| `app/Services/BrsAggregationService.php` (`L529–530`, `L714–729`, `L761–816`) | `calcGrowth()` (`prev <= 0 -> null`, `round(..., 2)`) dan rollover MoM Januari -> Desember $Y-1$ **sudah beres di Phase 2G.2A**. `lookupHistoricalValue()` (`L765–788`) mengisolasi `status_visibilitas`, namun belum memiliki klausa eksplisit `whereNotIn('batch_id', active_trash)` sebagai *defense-in-depth*. | **LOW:** Opsional menambah *defense-in-depth* eksklusi `trash` pada `lookupHistoricalValue()` tanpa memicu re-agregasi data existing. |

### 4.3 Audit Dampak pada Test Suite Existing (`158/158 PASS` Saat Ini)
Tiga test lama memanggil `StatisticsCalculationService::getOfficialDashboardMetrics()` **tanpa parameter `$allowedStatuses`** terhadap data `PRIVATE`:
1. `tests/Feature/ComprehensiveAuditAndSecurityTest.php:209` — menguji tahun `2024` (`30,147` historis `PRIVATE`).
2. `tests/Feature/SourceIsolationAndAggregationRegressionTest.php:230` — Test 12 menguji Juli `2026` (`Batch #85` `PRIVATE`, `$1,910,605,445.02`).
3. `tests/Feature/SourceIsolationAndAggregationRegressionTest.php:251` — Test 13 menguji tahun `2025` (`30,147` historis `PRIVATE`).

Begitu default `$allowedStatuses = ['RILIS']` ditegakkan pada `StatisticsCalculationService`, ketiga test tersebut akan gagal (`total_fob = 0`) kecuali diperbarui untuk memanggil `['RILIS', 'PRIVATE']` secara eksplisit (menguji tampilan Role 1/3) disertai test terpisah untuk Role 2 (`['RILIS']` -> `0` / `null`). Selain itu, `SourceIsolationAndAggregationRegressionTest.php` Tests 1–5 & 7 (`L32–148`) masih mereferensikan tabel arsip `transactions`.

---

## 5. Analisis Risiko & Mitigasi

| # | Kategori Risiko | Tingkat Risiko | Deskripsi Konkret | Mitigasi Konkret |
|---|---|---|---|---|
| **1** | **Kebocoran Data `PRIVATE` & `Trash` ke Role 2 (Status Saat Ini / Bila Filter Tidak Lengkap)** | **CRITICAL** | `StatisticsCalculationService`, `DashboardController::metrics()/data()/index()`, `AnalisisController`, `ExcelPreviewController`, dan `DistributionStatisticsService::getOriginDistribution()` saat ini membocorkan atau rentan membocorkan data `PRIVATE`/`Trash` ke Pegawai (Role 2). | Terapkan `array $allowedStatuses = ['RILIS']` (strict whitelist) + `LEFT JOIN data_batches` + eksklusi `trash` (`restored_at IS NULL`) di semua jalur baca; tambahkan read-side guard pada `getOriginDistribution()`; uji matriks lengkap Role 1/2/3 × (`RILIS`, `PRIVATE`, `DRAFT`, `Trash`) di `Phase2gStatisticsCalculationTest`. |
| **2** | **Ketidaksesuaian Angka Statistik & Pelanggaran Metodologi BPS (QoQ / `0.0` / `-100%` / Migas)** | **HIGH** | Pengembalian `0.0` dan `-100.0%` pada periode kosong menyesatkan publikasi; QoQ parsial melanggar Kebijakan B; divergensi Migas `HS 27075000` (`$252,399`) antar-service menyebabkan angka berbeda antara Single Dashboard vs `/distribusi/metrics`. | Gunakan flag `$hasMonth[$m]` untuk 3-bulan penuh kedua triwulan (QoQ); kembalikan `null` bila pembanding kosong atau `!$hasData`; gunakan BCMath scale 6; **bekukan definisi Migas** hingga keputusan eksplisit pengguna (§10 #3). |
| **3** | **Dampak Regresi pada Tampilan Role 2 Pasca Keputusan A-1 & 3 Test Lama** | **HIGH** | Karena `30,147` historis berstatus `PRIVATE` (Keputusan A-1), penegakan `['RILIS']` membuat Pegawai (Role 2) melihat hanya `#70` (`Impor 2026-08`) & `#71` (`Ekspor 2026-09`) dengan seluruh 4 kartu pertumbuhan bernilai `null` (`—`), serta memecahkan 3 test lama (`ComprehensiveAuditAndSecurityTest:209`, `SourceIsolation...:230,251`). | Konfirmasikan ekspektasi tampilan Role 2 sebelum eksekusi (§10 #1); perbarui 3 test lama agar menguji eksplisit `['RILIS', 'PRIVATE']` tanpa melemahkan assertion, dan tambahkan assertion eksplisit `['RILIS']` untuk Role 2; verifikasi null-safety di `dashboard.blade.php` (sudah mendukung `null -> '—'` pada `L1385–1388`, penyempurnaan visual tetap di Phase 2G.4). |
| **4** | **Kerusakan Data / Perubahan Tidak Sengaja pada Batch `#720`, `#71`, `30,147` Historis, atau `sidatex` Operasional** | **HIGH** | Kesalahan menjalankan script tulis/re-agregasi atau PHPUnit tanpa guard PDO dapat mengubah `#71` (`RILIS`), `#720` (`PRIVATE`), `30,147` historis, atau operasional `sidatex`. | Phase 2G.3 **TIDAK menyertakan migrasi maupun mutasi data mart** (100% perubahan kode baca + test ber-rollback transaksi); wajib backup 6-lapis pra-eksekusi, verifikasi live PDO `SELECT DATABASE() === 'sidatex_testing'`, dan verifikasi 7 SHA-256 baseline + 9 metrik operasional `sidatex` BEFORE & AFTER. |
| **5** | **Dampak ke Fitur SIDATEX Lainnya (Dashboard Tab 1–4, Analisis, Preview Upload, Distribusi Metrics)** | **MEDIUM** | Perubahan tipe return `mtom/qtoq/yony/ctoc` dari `float 0.0` menjadi `?float null`, serta perubahan `DistributionStatisticsService::getDistributionTradeBalance()` (`null` saat satu sisi kosong) berpotensi memicu error JS (`toFixed()` pada `null`) jika konsumen tidak null-safe. | Audit seluruh konsumen JS (`dashboard.blade.php`, `analisis-perdagangan.blade.php`, `distribusi-dashboard.blade.php`, `upload-data.blade.php`) sebelum mengubah kontrak JSON; pada Phase 2G.3 pertahankan kontrak backward-compatible untuk field yang dikonsumsi UI belum-hardened atau pastikan semua guard null sudah ada (Phase 2G.4 menyempurnakan tampilan Blade). |
| **6** | **Duplikasi Transaksi / Ketidaksinkronan File-Database / Concurrency** | **LOW** | Phase 2G.3 **tidak mengubah** jalur ingestion, `UploadController::store/destroy/restore/forceDelete`, file arsip privat DBF, maupun `BrsAggregationService::aggregateBatch()`. Risiko mutasi transaksi/file/concurrency adalah **LOW** (hanya muncul pada fixture test PHPUnit). | Seluruh test baru wajib menggunakan tahun sentinel (`2091–2097`), `DB::beginTransaction()`/`DB::rollBack()`, dan tidak menulis file fisik ke `storage/app/private/uploads/raw_*`. |

---

## 6. Rencana Eksekusi Phase 2G.3 (14 Komponen Wajib — BELUM DIEKSEKUSI)

### 6.1 Tujuan dan Ruang Lingkup
- **Tujuan:** Menegakkan isolasi visibilitas `RILIS`/`PRIVATE`/`DRAFT`/`Trash` pada seluruh jalur perhitungan statistik & pratinjau (`F-01`) dan menyelaraskan seluruh formula pertumbuhan statistik terhadap metodologi resmi BPS & Kebijakan B (`F-06`) pada `sidatex_testing`.
- **Ruang Lingkup Core:** `StatisticsCalculationService`, `DashboardController`, `AnalisisController`, `ExcelPreviewController`, serta test suite `Phase2gStatisticsCalculationTest` + pembaruan 3 test lama terkait.
- **Ruang Lingkup Opsional (Menunggu Keputusan §10):** `DistributionStatisticsService` (read-side guard `getOriginDistribution()` + null-guard `getDistributionTradeBalance()`), `DistributionController` (sanitasi error 500), dan penyeragaman Migas (`HS 27075000`).

### 6.2 Komponen yang Akan Terdampak
- `app/Services/StatisticsCalculationService.php`
- `app/Http/Controllers/DashboardController.php` (`index()`, `metrics()`, `data()`)
- `app/Http/Controllers/AnalisisController.php` (`getData()`)
- `app/Http/Controllers/ExcelPreviewController.php` (`preview()` DBF & Excel historical queries + QoQ)
- *(Opsional sesuai persetujuan §10 #2 & #3)*: `app/Services/DistributionStatisticsService.php`, `app/Http/Controllers/DistributionController.php`
- `tests/Feature/Phase2gStatisticsCalculationTest.php` *(baru)*
- `tests/Feature/ComprehensiveAuditAndSecurityTest.php` (`L206–217`), `tests/Feature/SourceIsolationAndAggregationRegressionTest.php` (`L227–255` + opsional pembersihan tabel `transactions` di `L32–148`)

### 6.3 Komponen yang TIDAK BOLEH Disentuh
- Database operasional `sidatex` (0 mutasi, 0 koneksi tulis).
- `database/migrations/` (0 migrasi baru atau edit migrasi lama).
- Data di `sidatex_testing`: **Batch `#720` (`PRIVATE`)**, **Batch `#71` (`RILIS`, termasuk `66` baris Origin `2026-09`)**, **Batch `#70` (`RILIS`)**, seluruh **`30,147` baris historis (`pelabuhan_key = 0`)**, **`2,896` `neraca_transactions`**, dan seluruh berkas arsip di `storage/app/private/uploads/raw_*`.
- `app/Services/BrsAggregationService.php` *(kecuali jika disetujui defense-in-depth `trash` pada `lookupHistoricalValue()`; DILARANG menjalankan ulang agregasi Batch `#71` atau `#720`)*.
- Frontend Blade (`resources/views/*.blade.php`) — tetap menjadi ruang lingkup **Phase 2G.4** (tidak dicampur ke Phase 2G.3).

### 6.4 Prasyarat Eksekusi
1. Persetujuan tertulis eksplisit dari pengguna atas rencana ini dan seluruh keputusan di §10.
2. Live PDO terverifikasi `SELECT DATABASE() === 'sidatex_testing'`.
3. Backup pra-eksekusi `sidatex_testing` lulus validasi 6-lapis (`§7`).
4. Baseline `php artisan test` = `158/158 PASS` dan `DataReconciliationService::auditConsistency()` = `is_consistent: true` sebelum edit pertama.

### 6.5 Urutan Langkah Eksekusi & Kriteria PASS/FAIL per Langkah
| Step | Aktivitas | Kriteria PASS | Kriteria FAIL (Hentikan / Rollback) |
|---:|---|---|---|
| **0** | **Backup & Pre-Check Baseline** (`sidatex_testing` + metadata `sidatex` + 7 SHA-256 baseline + git status) | Backup valid 6-lapis; PDO = `sidatex_testing`; 7 SHA-256 cocok; operasional `sidatex` cocok | Exit code `!= 0`, PDO `!= sidatex_testing`, atau baseline mismatch -> **HARD ABORT** |
| **1** | **Hardening `StatisticsCalculationService.php`** (`$allowedStatuses = ['RILIS']`, `LEFT JOIN data_batches`, eksklusi `trash`, fallback `$latestYear` dinamis `distribusi`/`neraca`, BCMath, `0.0 -> null`, guard `!$hasData` anti-`-100%`, Kebijakan B QoQ 3-bulan penuh berbasis `$hasMonth[$m]`) | Syntax valid (`php -l`), kontrak array return terjaga, pertumbuhan `2027`/`2020` = `null`, Role 2 tidak menerima data `PRIVATE`/Trash | Syntax error, masih return `0.0`/`-100.0` saat pembanding kosong, atau `PRIVATE` masih bocor |
| **2** | **Sinkronisasi Caller Controller** (`DashboardController::index()/metrics()/data()`, `AnalisisController::getData()`, `ExcelPreviewController::preview()` + opsional `DistributionStatisticsService`/`DistributionController` sesuai §10) | Seluruh caller meneruskan `$allowedStatuses` berbasis role; `$distYears`/`$neracaYears`/`$historicalYears` mengecualikan `trash`/`PRIVATE` bagi Role 2; preview historis memfilter `RILIS` non-Trash + QoQ Kebijakan B | Role 2 masih menerima metadata/angka `PRIVATE` atau error HTTP 500 |
| **3** | **Pembuatan `Phase2gStatisticsCalculationTest.php` & Penyelarasan 3 Test Lama** (tahun sentinel `2091–2097`, `DB::beginTransaction()`/`DB::rollBack()`, tanpa melemahkan assertion) | `Phase2gStatisticsCalculationTest`, `ComprehensiveAuditAndSecurityTest`, dan `SourceIsolationAndAggregationRegressionTest` lulus 100% (0 fail, 0 skip) | Ada test gagal, skip, atau residu sentinel di DB |
| **4** | **Full Regression Suite & Post-Check Integritas** (`php artisan test`, `DataReconciliationService`, 7 SHA-256 baseline, operasional `sidatex`, `git diff`) | Full suite PHPUnit **100% PASS** (`158 + N` tests, 0 fail/error/skip); 7 SHA-256 identik 100%; operasional `sidatex` identik 100%; 0 file produksi di luar scope | Kegagalan PHPUnit mana pun, perubahan pada `#71`/`#720`/`30,147`/`2,896`, atau perubahan operasional `sidatex` |
| **5** | **Review Independen & Laporan `SIDATEX_PHASE_2G_3_STATISTICS_REPORT.md`** (`statistics-auditor`, `security-auditor`, `database-guardian`, `independent-reviewer`) | Seluruh reviewer memberikan `PASS` / `APPROVED` | Verdict `BLOCKED` / `NEEDS REVISION` -> perbaiki sebelum dinyatakan selesai |

---

## 7. Strategi Backup dan Rollback (Kompatibel Windows PowerShell 5.1)

1. **Snapshot Git Sebelum Edit Kode:** Catat `git status --short` dan buat backup kode/diff kerja sebelum menyentuh file PHP mana pun.
2. **Backup Database Pra-Eksekusi (`sidatex_testing` ONLY — Windows PowerShell `5.1.26100.3624`):**
   - Gunakan binary absolut `D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe` dengan `--single-transaction --routines=0 --triggers=0 --set-gtid-purged=OFF --default-character-set=utf8mb4 --result-file="D:\laragon\www\sidatex\storage\backups\sidatex_testing_pre_2g3_YYYYMMDD.sql"` (DILARANG memakai `>`).
   - Validasi 6 lapis wajib: `$LASTEXITCODE === 0`, ukuran `>= 10 MB`, `Get-FileHash SHA256`, 4-byte awal hex `2d2d204d` (`try/finally { $fs.Dispose() }`), header `-- MySQL dump*` + trailer `-- Dump completed on `, serta 6 marker `CREATE TABLE` via `Select-String -LiteralPath`.
3. **Rollback Plan:**
   - **Rollback Kode (Utama — karena Phase 2G.3 adalah perubahan kode & test):** Kembalikan file PHP yang dimodifikasi ke kondisi pra-2G.3 via `git checkout -- <file_terdampak>` (dan hapus file test baru jika batal) lalu verifikasi `php artisan test` kembali `158/158 PASS`.
   - **Rollback Database (Darurat — jika terjadi mutasi tak terduga pada `sidatex_testing`):**
     ```powershell
     # Metode A (Direkomendasikan):
     & "D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe" --default-character-set=utf8mb4 -h 127.0.0.1 -P 3306 -u root sidatex_testing -e "SOURCE D:/laragon/www/sidatex/storage/backups/sidatex_testing_pre_2g3_YYYYMMDD.sql"
     # Metode B (Alternatif cmd.exe /c dengan outer-quote aman):
     cmd.exe /c '""D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe" --default-character-set=utf8mb4 -h 127.0.0.1 -P 3306 -u root sidatex_testing < "D:\laragon\www\sidatex\storage\backups\sidatex_testing_pre_2g3_YYYYMMDD.sql""'
     ```

---

## 8. Checklist Pre-Check, Post-Check, Regresi, & Bukti Audit

### 8.1 Pre-Check Wajib (PASS/FAIL)
- [ ] `SELECT DATABASE() === 'sidatex_testing'` (PASS)
- [ ] Backup 6-lapis `sidatex_testing_pre_2g3_YYYYMMDD.sql` valid (PASS)
- [ ] 7 SHA-256 baseline (`#71` 5-tabel, `30,147` historis, `2,896` Neraca) & integritas `#720` terkonfirmasi (PASS)
- [ ] Operasional `sidatex` 9 metrik tercatat (PASS)
- [ ] `php artisan test` = `158/158 PASS` sebelum perubahan (PASS)

### 8.2 Post-Check & Pengujian Regresi Wajib (PASS/FAIL)
- [ ] `Phase2gStatisticsCalculationTest` (matriks Role 1/2/3 × `RILIS`/`PRIVATE`/`DRAFT`/`Trash`, `0.0 -> null`, anti-`-100%`, real `0.0%` untuk `$1000 -> $1000`, Kebijakan B QoQ dengan celah bulan internal, `DashboardController::index()/metrics()/data()`, `AnalisisController`, `ExcelPreviewController`) — **100% PASS**
- [ ] Full PHPUnit Suite (`php artisan test`) — **100% PASS** (`0` fail, `0` error, `0` skip)
- [ ] `DataReconciliationService::auditConsistency()` — `is_consistent === true`, `historical_unlinked_rows === 30147`, `orphan_aggregations === []`
- [ ] 0 residu tahun sentinel (`2091–2099`) di DB maupun filesystem
- [ ] 7 SHA-256 baseline (`#71` 5-tabel, `30,147` historis, `2,896` Neraca) + `#720` (`809` tx / `797` MTS / `44` BRS / arsip SHA-256 `f527ff52...`) **100% IDENTIK**
- [ ] Operasional `sidatex` 9 metrik **100% IDENTIK**
- [ ] `git diff --stat` hanya mencakup file yang disetujui (0 file di `database/migrations/`, `resources/views/`, atau `storage/backups/`)

### 8.3 Bukti Audit yang Wajib Disimpan
1. Log backup 6-lapis + SHA-256 dump SQL.
2. Output JSON pre-check & post-check (live PDO, 7 SHA-256, `DataReconciliationService`, operasional `sidatex`).
3. Output lengkap `php artisan test` (jumlah test, assertions, exit code `0`).
4. `git diff --stat` & laporan `SIDATEX_PHASE_2G_3_STATISTICS_REPORT.md` + verdict 3–4 reviewer independen.

---

## 9. Kriteria HARD ABORT

Hentikan eksekusi Phase 2G.3 secara seketika apabila terjadi salah satu kondisi berikut:
1. `SELECT DATABASE()` mengembalikan nilai selain `'sidatex_testing'` (terutama `'sidatex'`).
2. Backup pra-eksekusi gagal pada salah satu dari 6 lapis validasi.
3. Terdapat perubahan pada **1 byte pun** dari 5 SHA-256 `Batch #71` (`RILIS`), `Batch #720` (`PRIVATE`), `30,147` baris historis, `2,896` `neraca_transactions`, atau 9 metrik database operasional `sidatex`.
4. Test baru/lama meninggalkan residu record sentinel (`2091–2099`) atau file arsip baru di `storage/app/private/uploads/`.
5. Terdapat kegagalan pada full suite `php artisan test` atau perubahan tak terduga pada kontrak JSON yang memicu HTTP `500` di endpoint `/dashboard/*`, `/analisis-perdagangan/*`, `/upload-data/preview`, atau `/distribusi/metrics`.

---

## 10. Keputusan yang Masih Membutuhkan Persetujuan Anda Sebelum Eksekusi Phase 2G.3

1. **Konfirmasi Efek Keputusan A-1 pada Role 2 (Pegawai BPS) & 3 Test Lama:**
   - Dengan `30,147` historis tetap `PRIVATE`, penegakan `allowedStatuses = ['RILIS']` di Phase 2G.3 akan membuat Pegawai (Role 2) hanya melihat `Batch #70` (`Impor 2026-08`) & `Batch #71` (`Ekspor 2026-09`) dengan `mtom/qtoq/yony/ctoc = null` (`—`), serta mengharuskan penyelarasan 3 test lama (`ComprehensiveAuditAndSecurityTest.php:209` dan `SourceIsolationAndAggregationRegressionTest.php:230, 251`) agar memanggil `['RILIS', 'PRIVATE']` secara eksplisit (plus test baru untuk Role 2 `['RILIS']`).
   - **Apakah Anda menyetujui pendekatan ini (mempertahankan Keputusan A-1 tanpa promosi data historis)?** *(Direkomendasikan: Ya)*
2. **Sertakan Hardening `DistributionStatisticsService` & `DistributionController` dalam Phase 2G.3:**
   - Menambahkan *read-side guard* pada `getOriginDistribution()` (`L454–498`, memverifikasi keberadaan batch `distribusi` `Ekspor` aktif berstatus `RILIS`), menyelaraskan `getDistributionTradeBalance()` (`L178–203`, mengembalikan `null` saat satu sisi kosong seperti `DashboardController::data()`), dan menyembunyikan `$e->getMessage()` mentah pada HTTP 500 `DistributionController::getMetrics()` (`L82`).
   - **Apakah ketiga perbaikan carry-over ini disetujui masuk ke Phase 2G.3?** *(Direkomendasikan: Ya — periksa juga kompatibilitas `DistributionModuleTest.php` & Blade `distribusi-dashboard.blade.php`)*
3. **Keputusan Metodologi BPS atas Divergensi Klasifikasi Migas (`HS 27075000` — `US$ 252,399.00`):**
   - `master_hs` memuat `27` kode HS Bab `27` berstatus resmi `oilgrp_code = 0 / oilgrp_desc = 'NON MIGAS'` (termasuk `27075000` yang aktif di transaksi). Saat ini `DistributionStatisticsService` memakai `oilgrp_code = 1`, sedangkan `BrsAggregationService` & `DashboardController` memakai `hs2 = '27'`.
   - **Pilihan:** (a) **Tunda** di luar Phase 2G.3 (pertahankan perilaku masing-masing layanan untuk mencegah pergeseran angka mart existing tanpa re-agregasi), **atau** (b) **Standarkan** seluruh layanan ke `oilgrp_code = 1 / oilgrp_desc = 'MIGAS'` (perlu perhatian bahwa ini akan berdampak pada angka Migas jika mart dire-agregasi kelak). *(Direkomendasikan untuk Phase 2G.3: Opsi (a) Tunda / catat untuk keputusan metodologi BPS terpisah agar tidak memicu mutasi mart pada Phase 2G.3)*
4. **Cakupan `growthMomTable` / `growthQoqTable` di `DashboardController::data()` (`L489–562`) & `monthlyTradeBalance` (`L605–618`):**
   - Apakah perbaikan MoM/QoQ celah bulan pada tabel Tab 2/3 & guard single-sided `monthlyTradeBalance` Tab 4 ikut dimasukkan dalam Phase 2G.3, atau dibatasi pada `StatisticsCalculationService` + caller (`F-01` & `F-06`)? *(Direkomendasikan: Masukkan perbaikan `growthMomTable` & `growthQoqTable` agar konsisten dengan Kebijakan B di seluruh Single Dashboard)*
5. **Pembersihan Referensi Tabel Arsip `transactions` di `SourceIsolationAndAggregationRegressionTest.php` (`L32–148`, Tests 1–5 & 7):**
   - Apakah 6 test lama tersebut disetujui dimutakhirkan agar menguji `distribusi_transactions` & `neraca_transactions` secara nyata (bukan tabel arsip `transactions`)? *(Direkomendasikan: Ya)*
