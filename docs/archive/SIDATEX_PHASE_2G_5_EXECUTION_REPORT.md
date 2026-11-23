# SIDATEX — LAPORAN EKSEKUSI PHASE 2G.5
## Import MoM Growth Statistical Integrity & Distribution Cleanup

| Atribut | Nilai |
|---|---|
| **Fase** | Phase 2G.5 — Controlled Backend Statistical Integrity Execution (Ruang Lingkup Lengkap A + B + C) |
| **Tanggal Eksekusi** | 2026-11-18 |
| **Target Database (Live PDO)** | `sidatex_testing` (`SELECT DATABASE() === 'sidatex_testing'`) |
| **Database Operasional (`sidatex`)** | **TIDAK TERSENTUH (ZERO TOUCH)** — 9/9 metrik baseline 100% identik |
| **Ruang Lingkup File Diubah** | **Hanya 1 file service + 1 file PHPUnit test baru** (0 perubahan Blade, controller, model, route, migration, DB, maupun `HS 27075000`) |
| **Hasil PHPUnit Suite Penuh** | **175/175 PASS (1.444 assertions, 0 failures, 0 errors, 0 skips, exit code 0)** |
| **Tinjauan Independen Read-Only** | **3/3 `PASS` (`statistics-auditor: PASS`, `security-auditor: PASS`, `database-guardian: PASS`)** |
| **Status Fase Berikutnya** | **BERHENTI DI PHASE 2G.5** — Fase berikutnya belum dimulai |

---

## 1. Ringkasan Perubahan (Ruang Lingkup Lengkap A + B + C)

Seluruh perubahan dilaksanakan secara terlokalisasi hanya pada `app/Services/DistributionStatisticsService.php` dengan injeksi konstruktor *backward-compatible* (`public function __construct(?StatisticsCalculationService $statService = null)`):

### A. Perbaikan Inti — `getSummaryMetrics()` (`L76–212`)
- **Pembedaan Observasi via `COUNT(*) as row_count`:** Kedua query agregasi (`$queryAgg` & `$queryCum`) kini menyertakan `COUNT(*) as row_count` sehingga periode tanpa observasi/belum `RILIS` (`row_count === 0`, SQL `SUM() = NULL`) tidak lagi dikoersi menjadi `0` sebelum perhitungan pertumbuhan.
- **Delegasi ke `StatisticsCalculationService::calcBpsGrowth()`:** Seluruh 9 indikator pertumbuhan (`mtom_total`, `mtom_nonmigas`, `mtom_migas`, `yony_total`, `yony_nonmigas`, `yony_migas`, `ctoc_total`, `ctoc_nonmigas`, `ctoc_migas`) didelegasikan ke formula kanonik BPS (`BCMath` scale 8 → pembulatan akhir 2 desimal) dengan input string 6 desimal (`$toDecimal6`):
  - Tidak ada observasi bulan berjalan (`!$hasCurrM`) → **`null`** (menghilangkan `-100` semu).
  - Tidak ada observasi bulan pembanding (`!$hasPrev`) atau pembanding bernilai `0` → **`null`** (tanpa `Infinity`/`NaN`).
  - Bulan berjalan terobservasi (`row_count > 0`) dengan nilai riil `0` melawan pembanding `> 0` → **`-100.0`** (penurunan 100% yang sah tetap terhitung akurat).
  - Perubahan datar riil (`curr === prev > 0`) → **`0.0`**.
- **Penyelarasan Guard `ctoc_*` terhadap `$hasCurrM`:** `$hasValidCurrCumForGrowth = ($hasCurrM && $hasCurrCum)` mencegah pembandingan jendela kumulatif timpang (mis. `Jan–Agu` vs `Jan–Sep`) saat bulan berjalan belum `RILIS`.
- **Eksposur Non-Breaking Presisi Mentah:** Menambahkan `has_data` dan `nilai_total_usd_raw` (string 6 desimal) pada `bulan_ini` & `kumulatif`, serta memformat `*_juta` & `*_ribu_ton` dengan `bcdiv(..., '1000000', 8)` → `round(..., 2)` tanpa mengubah kunci maupun format respons yang sudah ada.
- **Filter Visibilitas Utuh:** Tetap menggunakan `getReleasedBatchIds()` (`tipe_sumber = 'distribusi'`, `status = 'RILIS'`, mengecualikan `trash WHERE restored_at IS NULL`).

### B. Pembersihan `getTopCommodities()` (`L402–414`)
- Item residu `'XX' / 'Lainnya'` kini hanya ditambahkan apabila `$top3->isNotEmpty() || $totalNonmigasVal > 0`.
- Ketika periode kosong atau belum `RILIS`, `items` mengembalikan **`[]`** (tanpa item `'Lainnya'` semu `0/0/0%`). Saat data tersedia (mis. `2026-09` Ekspor `RILIS`), urutan dan nilai Top 3 + `'Lainnya'` (4 item) tetap identik 100%.

### C. Presisi BCMath pada `getDistributionTradeBalance()` (`L221–282`)
- Pengurangan neraca bulanan dan kumulatif kini dilakukan langsung pada nilai USD mentah 6 desimal menggunakan **`bcsub($fobUsdRaw, $cifUsdRaw, 6)`**, kemudian dibagi `1.000.000` dengan `bcdiv(..., '1000000', 8)` dan dibulatkan 2 desimal pada tahap akhir saja (tanpa pembulatan dini).
- Pengecekan ketersediaan sisi (`has_data && bccomp($...UsdRaw, '0', 6) > 0`) dan penetapan status (`Surplus`/`Defisit`/`Seimbang` via `bccomp($diffUsd, '0', 6)`) menggunakan USD mentah 6 desimal, menghilangkan bias pembulatan jutaan pada selisih kecil maupun nilai mikro `< US\$ 5.000`.
- Perlindungan *single-sided* `NULL` dan seluruh 12 kunci respons dipertahankan utuh.

---

## 2. Daftar File yang Diubah dan Ditambahkan

| No | File | Kategori | Perubahan |
|---|---|---|---|
| 1 | `app/Services/DistributionStatisticsService.php` | Backend Service | Constructor DI `?StatisticsCalculationService`, `COUNT(*)` + `calcBpsGrowth()` + guard `ctoc` `$hasCurrM` di `getSummaryMetrics()`, guard empty `'Lainnya'` di `getTopCommodities()`, dan `bcsub` USD mentah di `getDistributionTradeBalance()` |
| 2 | `tests/Feature/Phase2gDistributionGrowthAuditTest.php` | Feature Test (**BARU**) | 6 suite pengujian terisolasi (`DB::beginTransaction()`/`rollBack()` pada tahun sentinel `2092–2098` + pengujian live `sidatex_testing`) mencakup seluruh syarat pengujian wajib |

> **Verifikasi Batasan Ruang Lingkup:** Pemeriksaan timestamp & struktur file mengonfirmasi **hanya 2 file di atas** yang berubah selama Phase 2G.5. **0 perubahan** pada file Blade Phase 2G.4 (`dashboard.blade.php`, `distribusi-dashboard.blade.php`), controller, model, route, migration, maupun klasifikasi `HS 27075000`.

---

## 3. Hasil Pengujian (Targeted & Full PHPUnit Suite)

| Eksekusi | Perintah | Hasil | Assertions | Durasi | Exit Code |
|---|---|---|---|---|---|
| **Targeted Phase 2G.5 Suite** | `php artisan test tests/Feature/Phase2gDistributionGrowthAuditTest.php tests/Feature/DistributionModuleTest.php tests/Feature/Phase2gBladeAlignmentTest.php tests/Feature/Phase2gStatisticsCalculationTest.php` | **19/19 PASS** | 346 | 17,41 s | `0` |
| **Full PHPUnit Suite** | `php artisan test` | **175/175 PASS** | **1.444** | 321,61 s | `0` |

Baseline Phase 2G.4 adalah `169/169 PASS (1.332 assertions)` → meningkat menjadi **`175/175 PASS (1.444 assertions)`** (+6 test baru, +112 assertions tambahan, 0 test dihapus, 0 assertion dilemahkan).

---

## 4. Verifikasi Payload Aktual (Sebelum vs Sesudah) & Isolasi Role 2

### 4.1 Perbandingan Payload Live `sidatex_testing` (`GET /distribusi/metrics` oleh Role 2 Pegawai)

| Skenario Live | `sql_row_count` (RILIS) | Sebelum Phase 2G.5 | Sesudah Phase 2G.5 | Status |
|---|---:|---|---|---|
| **Impor `2026-09`** (`Batch #74` masih `PRIVATE`) | `0` | `mtom_total/nonmigas/migas = -100` *(SALAH)* | **`mtom_total/nonmigas/migas = null`**, `ctoc_* = null`, `has_data = false` | **TERATASI** |
| **Ekspor `2026-10`** (belum ada batch) | `0` | `mtom_total/nonmigas/migas = -100` *(SALAH)*, `top_commodities = [1 item 'Lainnya']` | **`mtom_total/nonmigas/migas = null`**, **`top_commodities.items = []`** | **TERATASI** |
| **Ekspor `2026-08`** (`Batch #720` masih `PRIVATE`) | `0` | `top_commodities = [1 item 'Lainnya' 0/0/0%]` | **`top_commodities.items = []`** | **TERATASI** |
| **Ekspor `2026-09`** (`Batch #71 RILIS`) | `841` | `2.301,81 jt`, `top_commodities = 4 item` | **`2.301,81 jt`**, **`top_commodities = 4 item` (Top 3 + `'XX' Lainnya`)** | **UTUH** |
| **Trade Balance `2026-09`** | Ekspor `841` / Impor `0` | `neraca_bulanan = null`, `neraca_kumulatif = 2134.46` | **`neraca_bulanan = null`**, **`neraca_kumulatif = 2134.46` (`Surplus` via `bcsub` USD mentah)** | **UTUH & PRESISI** |

### 4.2 Pembuktian `-100%` Sah, Presisi `bcsub`, & Isolasi Role 2 (`Phase2gDistributionGrowthAuditTest.php`)
- **`-100.0%` sah tetap muncul:** Saat bulan berjalan berstatus `RILIS` (`row_count > 0`) dengan sub-komponen Migas `0` melawan bulan lalu Migas `US\$ 500.000` (atau agregat riil `0` dengan `row_count = 1`), baik Ekspor maupun Impor menghasilkan tepat **`-100.0`**, sementara perubahan datar riil menghasilkan tepat **`0.0`**, dan pembanding `0` menghasilkan **`null`** (tanpa `Infinity`/`NaN`).
- **Presisi `bcsub` USD mentah:** Ekspor `100.004.999,00` vs Impor `99.995.001,00` (keduanya bulat `100,00 jt`) menghasilkan tepat **`+0,01 jt` (`Surplus`)** — bukan `0,00` (`Seimbang`); transaksi mikro `US\$ 3.000` vs `US\$ 2.000` terdeteksi lengkap (`has_complete_bulanan = true`, `has_complete_kumulatif = true`, `Surplus`).
- **Isolasi Role 2 (`PRIVATE`, `DRAFT`, `Trash`):** Batch `PRIVATE`, `DRAFT`, dan `RILIS` yang aktif di `trash` (`restored_at IS NULL`) seluruhnya terblokir (`has_data = false`, `nilai = 0`, `pertumbuhan = null`, `top_commodities = []`), sedangkan batch `RILIS` yang telah dipulihkan (`restored_at IS NOT NULL`) kembali terbaca sah.

---

## 5. Bukti Integritas Database & Zero-Touch Operasional `sidatex`

- **Live PDO:** `sidatex_testing`.
- **Batch `#71` (`RILIS`, `2026-09`) — 5/5 SHA-256 100% IDENTIK:**
  - `data_batches#71`: `f2ba5f17e850f5ede81e14d70ec8b3ac1b95216a7b2288f6d57562e40ea40226`
  - `distribusi_transactions#71` (`841` baris): `ac739c0e484c711e4f1480eef439033c03b6d0069e9e64ea32ddb6e7f388f576`
  - `monthly_trade_summaries#71` (`831` baris): `57aac248fbec41244694ace26700c8e7ac320e74360d85fb20723b0759cfe7ed`
  - `brs_indicator_summaries#71` (`47` baris): `4ba64668d38e0e253e34ae96081304a818e5d4c013212190636e013d55ca0cba`
  - `monthly_origin_trade_summaries` (`2026-09`, `66` baris; `2026-08`: `0`): `eea5fa2931aee9708f8de642e1485f3ac2c3de6fa91647aea18fe7b29bf70fa1`
- **Batch `#720` (`PRIVATE`, `2026-08`):** `809` tx, `797` MTS (`16` pelabuhan), `44` BRS, arsip DBF privat SHA-256 `f527ff527949756193e3170fc37725f47d33ff7cfc4dc45393acd2603d132ed5` (**IDENTIK**).
- **30.147 baris historis `PRIVATE`:** SHA-256 `17cc234367f9f5fcf9819f009609c0d4391ce28529e15deef1b3ddcc01eefb33` (**IDENTIK**).
- **2.896 baris `neraca_transactions`:** SHA-256 `63c18733cd2822a3ab066769beaba99b3c6c556725a2ffa1065500ad6105bd6d` (**IDENTIK**).
- **Residu sentinel (`2090–2099`):** `0` pada seluruh 5 tabel; **`DataReconciliationService::auditConsistency()`:** `is_consistent = true` (`9` active batches, `0` trashed, `0` orphans, `0` unmapped).
- **Database Operasional `sidatex` (9/9 Metrik IDENTIK — ZERO TOUCH):** `data_batches=3`, `distribusi_transactions=2557`, `neraca_transactions=0`, `monthly_trade_summaries=34847`, `monthly_origin_trade_summaries=189`, `migrations=26`, `brs_indicator_summaries=NOT FOUND`, `data_batches.MAX(updated_at)=2026-11-13 07:38:33`, `monthly_trade_summaries.MAX(updated_at)=2026-11-12 07:06:16`.

---

## 6. Hasil Tinjauan Independen Read-Only (3/3 `PASS`, 0 Blocker)

| Subagent Independen | Verdict | Ringkasan Verifikasi |
|---|---|---|
| **`statistics-auditor`** | **`PASS`** | Memverifikasi langsung `COUNT(*) as row_count` pada `$queryAgg`/`$queryCum`, pemisahan `null` (tak terobservasi) vs `-100.0` (nol terobservasi), delegasi `calcBpsGrowth()` (BCMath scale 8→2), guard `ctoc` `$hasCurrM && $hasCurrCum`, `bcsub` USD mentah pada `getDistributionTradeBalance()`, pembersihan `'Lainnya'` semu pada `getTopCommodities()`, dan keutuhan `HS 27075000`. *(Catatan batas verifikasi: runtime PHPUnit bersumber dari eksekusi Orchestrator.)* |
| **`security-auditor`** | **`PASS`** | 0 Critical/High/Medium/Low blocker. Memverifikasi `getReleasedBatchIds()` (`tipe_sumber='distribusi'`, `status='RILIS'`, eksklusi `trash`) diterapkan di seluruh kueri, isolasi penuh Role 2 terhadap `PRIVATE`/`DRAFT`/`Trash`, binding parameter SQL aman (0 risiko SQLi), serta keamanan transaksi rollback pada test. |
| **`database-guardian`** | **`PASS`** | Memverifikasi kardinalitas join `1:1` (`distribusi_transactions JOIN master_hs ON hs_id`, 0 multiplikasi baris `COUNT(*)`/`SUM`), 0 referensi tabel arsip `transactions`, presisi `DECIMAL(20,6)` → BCMath, serta isolasi rollback test sentinel. *(Catatan batas verifikasi: bukti PDO/SHA-256 live bersumber dari Orchestrator karena mode `read_file`-only.)* |

---

> **Penegasan Batas Tahap:** **Phase 2G.5 selesai 100%** dengan seluruh verifikasi dan 3/3 verdict independen **`PASS`**. **Fase berikutnya BELUM dimulai** dan menunggu persetujuan tertulis terpisah dari pengguna.
