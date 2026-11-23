# SIDATEX — LAPORAN EKSEKUSI TERKENDALI PHASE 2G.2B (`sidatex_testing`)

**Tanggal Eksekusi:** 18 November 2026  
**Target Database:** `sidatex_testing` *(terverifikasi live PDO `SELECT DATABASE() === 'sidatex_testing'` sebelum setiap kelompok operasi tulis)*  
**Status:** **SELESAI — MENUNGGU PERSETUJUAN PENGGUNA SEBELUM PHASE 2G.3 / REMEDIASI BATCH `#40`**  
**Verdict Ketiga Reviewer Independen:**
- `database-guardian`: **`PASS`**
- `statistics-auditor`: **`PASS`**
- `independent-reviewer`: **`APPROVED`**

> [!IMPORTANT]
> **Kepatuhan Ruang Lingkup & Keamanan:**
> - **ZERO** mutasi pada database operasional `sidatex` (`data_batches=3`, `distribusi_transactions=2557`, `monthly_trade_summaries=34847`, `migrations=26`, `MAX(updated_at)=2026-11-13 07:38:33`).
> - **ZERO** perubahan pada **Batch `#40`** (`691` Tier-1 & `58` Origin Mart — SHA-256 bit-for-bit identik BEFORE vs AFTER & POST-TEST).
> - **ZERO** perubahan status atau `batch_id` pada **30.147 baris historis** (`PRIVATE`, `batch_id IS NULL`, SHA-256 17 kolom immutable 100% identik BEFORE vs AFTER & POST-TEST).
> - **ZERO** migration tambahan & **ZERO** perubahan berkas DBF asli (`SHA-256` identik).
> - **Phase 2G.3, Phase 2G.4, dan remediasi Batch `#40` BELUM dimulai.**

---

## 1. Snapshot Backup & Perbandingan Kondisi BEFORE vs AFTER (`sidatex_testing`)

### 1.1 Pre-Execution Full Snapshot Backup (`mysqldump`)
- **File Backup:** [sidatex_testing_pre_2g2b_20261118.sql](file:///d:/laragon/www/sidatex/storage/backups/sidatex_testing_pre_2g2b_20261118.sql)
- **Perintah:** `mysqldump --single-transaction --routines=0 --triggers=0 --set-gtid-purged=OFF -h 127.0.0.1 -P 3306 -u root sidatex_testing`
- **Ukuran:** `13.896.003` bytes (~13,25 MB)
- **SHA-256:** `3cd487b1a7af27d6d4c436a94f04c383da6e55dc13e179b2b8ac311102a3efd3`
- **Rollback Deterministik:** Tersedia penuh (pre-commit via `DB::transaction()` pada setiap kelompok, dan post-commit via restore snapshot SQL di atas ke `sidatex_testing`).

### 1.2 Perbandingan Tabel Utama `sidatex_testing` (BEFORE vs AFTER & POST-TEST)

| Tabel / Metrik (`sidatex_testing`) | BEFORE | AFTER Eksekusi | POST-TEST (`158` PHPUnit) | Keterangan |
|---|---:|---:|---:|---|
| Live PDO `SELECT DATABASE()` | `sidatex_testing` | `sidatex_testing` | `sidatex_testing` | Terverifikasi di tiap tahap |
| `data_batches` | `9` | `9` | `9` | `1 RILIS (#70)` + `8 PRIVATE` |
| `distribusi_transactions` | `3.575` | **`4.360`** (`+785`) | **`4.360`** | Penambahan tepat 785 baris Batch `#70` |
| `neraca_transactions` | `4.065` | `4.065` | `4.065` | Utuh, 0 kebocoran lintas metodologi |
| `transactions` *(tabel arsip)* | `2.557` | `2.557` | `2.557` | Tidak dipakai & tidak dimutasi |
| `monthly_trade_summaries` | `39.110` | `39.110` | `39.110` | `768` B1 unlinked diganti tepat oleh `768` Batch `#70 RILIS` |
| `monthly_origin_trade_summaries` | `189` | **`58`** (`-131`) | **`58`** | Kebocoran `PRIVATE` `O1` (`65`) & `O3` (`66`) bersih; `O2` (`58` Batch `#40`) utuh |
| `brs_indicator_summaries` | `338` | **`375`** (`+37`) | **`375`** | Penambahan `37` baris Tier-2 `RILIS` Batch `#70` |
| `trash` / `comments` / `migrations` | `0 / 0 / 28` | `0 / 0 / 28` | `0 / 0 / 28` | 0 migration baru, 0 sentinel |

---

## 2. Detail Re-Ingestion Batch `#70` (Opsi B1-a — DBF Asli)

- **Berkas Sumber:** [14_impor_atap_kdprov_0826.DBF](file:///d:/laragon/www/sidatex/referensi_bps/impor_distribusi/14_impor_atap_kdprov_0826.DBF)
  - Ukuran: `155.095` bytes (BEFORE === AFTER)
  - SHA-256: `b27f2b8ac114b9a709150ac5b55fb41fb479730f88f02c15a9c1f9f4485065a5` (BEFORE === AFTER)
- **Sumber Fallback:** **0** pembacaan dari tabel arsip `transactions`.
- **Hasil Ingestion & Idempotency Guard:**
  - Baris DBF diparse & di-insert ke `distribusi_transactions` (`batch_id = 70`): **`785` baris** (`0` unmapped HS/Negara/Pelabuhan, `0` `provorig`, `kabkota_id` & `provinsi_id = 4` terisi paritas `DbfIngestionService.php:201-217`).
  - **Uji Idempotensi Langsung:** Pemanggilan ulang fungsi ingestion segera mendeteksi `existing_tx = 785` (`lockForUpdate()`) dan melewati insert secara aman (`skipped_insert = true`), menjamin **0 duplikasi transaksi**.
  - Metadata `data_batches #70` tetap utuh (`status = 'RILIS'`, `released_by`/`released_at` tidak berubah, tidak membuat batch baru).
- **Hasil Agregasi `BrsAggregationService::aggregateBatch(70, 'RILIS')`:**
  - Tier-1 (`monthly_trade_summaries`, `batch_id = 70`): **`768` cube key unik** (`751` single-tx + **`17` multi-tx cube normal** -> `SUM(jumlah_transaksi) = 785`), seluruhnya **`status_visibilitas = 'RILIS'`**, `0` baris unlinked tersisa pada `(distribusi, Impor, 2026, 8)`.
  - Total Nilai CIF (Raw === Tier-1 === Tier-2): **`US$ 167.347.655,077700`** *(drift = **`0.000000`**; headline BRS: **US$ 167,35 juta**)*.
  - Total Berat Netto (Raw === Tier-1): **`568.581.779,655200 Kg`** *(drift = **`0.000000`**; headline BRS: **568,58 ribu ton**)*.
  - Tier-2 (`brs_indicator_summaries`, `batch_id = 70`): **`37` baris `RILIS`**, seluruh 6 kategori dimensi merekonsiliasi tepat ke **`167.347.655,077700`**:
    - `TOTAL` (`1` baris): `167.347.655,077700` (`growth_mtom = NULL`, `growth_yoy = NULL`, `growth_ctc = NULL` — tidak bocor dari `#86 PRIVATE`)
    - `MIGAS_NONMIGAS` (`2` baris): `167.347.655,077700` (`Migas = 8.872.998,790000`, `Non-Migas = 158.474.656,287700`)
    - `BEC` (`3` baris): `167.347.655,077700`
    - `HS2_TOP10` (`11` baris): `167.347.655,077700`
    - `NEGARA_TOP10` (`11` baris): `167.347.655,077700`
    - `PELABUHAN` (`9` baris): `167.347.655,077700`
  - Origin Mart (`monthly_origin_trade_summaries`, `2026-08 impor`): **`0` baris** (sesuai DBF Impor tanpa `provorig`).

---

## 3. Hasil Re-Agregasi Kronologis 9 Batch (`2026-07 -> 2026-08 -> 2026-09`)

Urutan eksekusi kronologis aktual: `#85, #86, #88` (`2026-07`) -> **`#70 (RILIS)`**, `#87, #82` (`2026-08`) -> `#71, #74, #81` (`2026-09`).

| Batch | Sumber | Jenis | Periode | Status | Raw Tx | Nilai Raw (USD, 6d) | Tier-1 Rows | Drift Tier-1 (BEFORE -> AFTER) | Tier-2 Rows | Drift Tier-2 (BEFORE -> AFTER) | MoM Tier-2 |
|---:|---|---|---|---|---:|---:|---:|---|---:|---|---:|
| **#85** | `distribusi` | `Ekspor` | `2026-07` | `PRIVATE` | `814` | `1.910.605.444,960000` | `805` | `-0.570000 ->` **`0.000000`** | `44` | `-0.570000 ->` **`0.000000`** | `0.53%` |
| **#86** | `distribusi` | `Impor` | `2026-07` | `PRIVATE` | `989` | `199.716.065,392700` | `978` | `-2.562700 ->` **`0.000000`** | `38` | `-2.562700 ->` **`0.000000`** | `NULL` |
| **#88** | `neraca` | `Impor` | `2026-07` | `PRIVATE` | `1.169` | `210.026.242,934800` | `1.153` | `-3.044800 ->` **`0.000000`** | `39` | `-3.044800 ->` **`0.000000`** | `NULL` |
| **#70** | `distribusi` | `Impor` | `2026-08` | **`RILIS`** | **`785`** | **`167.347.655,077700`** | **`768`** | `N/A ->` **`0.000000`** | **`37`** | `N/A ->` **`0.000000`** | **`NULL`** |
| **#87** | `neraca` | `Ekspor` | `2026-08` | `PRIVATE` | `925` | `2.226.732.699,021600` | `901` | `-0.571600 ->` **`0.000000`** | `45` | `-0.571600 ->` **`0.000000`** | `NULL` |
| **#82** | `neraca` | `Impor` | `2026-08` | `PRIVATE` | `1.021` | `176.287.710,172900` | `993` | `-2.662900 ->` **`0.000000`** | `39` | `-2.662900 ->` **`0.000000`** | `-16.06%` |
| **#71** | `distribusi` | `Ekspor` | `2026-09` | `PRIVATE` | `841` | `2.301.809.204,587000` | `831` | `-0.587000 ->` **`0.000000`** | `47` | `-0.587000 ->` **`0.000000`** | `3.77%` |
| **#74** | `distribusi` | `Impor` | `2026-09` | `PRIVATE` | `931` | `215.260.644,472700` | `918` | `-2.112700 ->` **`0.000000`** | `38` | `-2.112700 ->` **`0.000000`** | `28.63%` |
| **#81** | `neraca` | `Ekspor` | `2026-09` | `PRIVATE` | `950` | `2.314.286.425,304600` | `925` | `-0.674600 ->` **`0.000000`** | `48` | `-0.674600 ->` **`0.000000`** | `3.93%` |

- **Audit `DataReconciliationService::auditConsistency()`:**
  - `status` = **`CONSISTENT`**, `is_consistent` = **`TRUE`**, `unmapped_ports/countries/commodities` = `0/0/0`, `orphan_aggregations` = `0`, `historical_unlinked_rows` = **`30.838`** (`30.147` H1–H4 + `691` B2).
  - Pemisahan metodologi 100% terjaga:
    - **Distribusi (5 batch, `4.360` tx):** Ekspor FOB `4.212.414.649,547000` | Impor CIF `582.324.364,943100` | Netto `6.693.283.731,166800 Kg`.
    - **Neraca (4 batch, `4.065` tx):** Ekspor FOB `4.541.019.124,326200` | Impor CIF `386.313.953,107700` | Netto `6.290.583.221,798500 Kg`.
- **Penutupan Kebocoran Origin Mart:** `O1` (`2026-07` `65` baris) & `O3` (`2026-09` `66` baris) milik batch `PRIVATE` `#85` & `#71` telah bersih (`0` baris).

---

## 4. Hasil Enrichment 30.147 Baris Historis (`sektor`, `is_migas`, `bec_category`)

- **Statistik Pemetaan `master_hs`:**
  - Mapping valid berhasil: **`30.147` baris (`100,00%`)**
  - Tidak ditemukan (`unmapped`): **`0` baris**
  - Ambigu (`duplicate hs_id` di `master_hs`): **`0` baris**
- **Bukti Integritas Kolom Immutable (0 Perubahan di Luar 3 Atribut Enrichment):**
  - SHA-256 atas 17 kolom immutable (`summary_id, batch_id, tipe_sumber, jenis_transaksi, tahun, bulan, hs_id, hs2, negara_id, provinsi_id, pelabuhan_id, pelabuhan_key, total_berat, total_nilai, jumlah_transaksi, status_visibilitas, mapping_status`):
    - **BEFORE:** `8c946a1ad978a04eda84517d062f5ddc6b09ae7abbbd9fdfb94e217abbf8207e`
    - **AFTER & POST-TEST:** `8c946a1ad978a04eda84517d062f5ddc6b09ae7abbbd9fdfb94e217abbf8207e` (**100% IDENTIK**)
  - Seluruh `30.147` baris tetap **`status_visibilitas = 'PRIVATE'`** dan **`batch_id IS NULL`** (`0` promosi ke `RILIS`).
- **Distribusi Aktual Pasca-Enrichment (Tervalidasi Eksak):**
  - **`is_migas = 1` (MIGAS):** **`273` baris** — **`US$ 5.042.852.784,430000`** (`7,30%`)
  - **`is_migas = 0` (NON-MIGAS):** **`29.874` baris** — **`US$ 64.048.813.807,140000`** (`92,70%`)
  - **`sektor`:** `INDUSTRI PENGOLAHAN` = `28.072` (`$62.863.524.998,36`), `PERTANIAN` = `1.795` (`$1.158.635.974,03`), `MIGAS` = `273` (`$5.042.852.784,43`), `PERTAMBANGAN` = `7` (`$26.652.834,75`), `NULL` = `0`.
  - **`bec_category`:** `Bahan Baku/Penolong` = `25.086` (`$65.484.438.516,53`), `Barang Konsumsi` = `4.765` (`$3.598.936.249,07`), `Barang Modal` = `296` (`$8.291.825,97`), `NULL` = `0`.

---

## 5. Bukti Batch `#40` Tidak Berubah & Database Operasional `sidatex` Tidak Tersentuh

### 5.1 Bukti Kriptografis Batch `#40` (691 Tier-1 & 58 Origin — 100% Identik)

| Kelompok Batch `#40` | Jumlah Baris (BEFORE / AFTER) | SHA-256 BEFORE | SHA-256 AFTER & POST-TEST | Status |
|---|---:|---|---|---|
| Tier-1 `monthly_trade_summaries` (`2026-08 Ekspor`) | `691 / 691` | `d664178098fd04bbfe56b510144645cb109ac426813b1d069e450f43497a6543` | `d664178098fd04bbfe56b510144645cb109ac426813b1d069e450f43497a6543` | **100% IDENTIK** |
| Origin `monthly_origin_trade_summaries` (`2026-08 ekspor`) | `58 / 58` | `ef24390f6e9ce7690afdbf4bd34963b758416094616d81a71ed8ec9ed85a72ee` | `ef24390f6e9ce7690afdbf4bd34963b758416094616d81a71ed8ec9ed85a72ee` | **100% IDENTIK** |
| Berkas DBF `14_Atap_ekspor_2608_POD_based.DBF` | `71.642` bytes | `f527ff527949756193e3170fc37725f47d33ff7cfc4dc45393acd2603d132ed5` | `f527ff527949756193e3170fc37725f47d33ff7cfc4dc45393acd2603d132ed5` | **100% IDENTIK** |

### 5.2 Bukti Database Operasional (`sidatex`) Tidak Tersentuh

| Metrik Operasional `sidatex` | BEFORE | AFTER & POST-TEST | Status |
|---|---|---|---|
| `data_batches` / `distribusi_transactions` / `neraca_transactions` | `3 / 2.557 / 0` | `3 / 2.557 / 0` | **UNTOUCHED** |
| `monthly_trade_summaries` / `monthly_origin_trade_summaries` | `34.847 / 189` | `34.847 / 189` | **UNTOUCHED** |
| `trash` / `comments` / `migrations` | `0 / 0 / 26` | `0 / 0 / 26` | **UNTOUCHED** |
| `brs_indicator_summaries` | `NOT FOUND` | `NOT FOUND` | **UNTOUCHED** |
| `data_batches MAX(updated_at)` | `2026-11-13 07:38:33` | `2026-11-13 07:38:33` | **UNTOUCHED** |

---

## 6. Hasil Pengujian PHPUnit (`sidatex_testing`)

- **Penyesuaian 1 Test Lama ([DistributionModuleTest.php](file:///d:/laragon/www/sidatex/tests/Feature/DistributionModuleTest.php#L37-L38)):**
  - `test_september_2026_distribution_reconciles_with_bps_release()` sebelumnya lulus secara kebetulan karena bergantung pada kebocoran `66` baris `PRIVATE` (`O3`) di `monthly_origin_trade_summaries`. Setelah `O3` bersih pada Phase 2G.2B, test diselaraskan memanggil `BrsAggregationService::syncOriginSummariesForPeriod(2026, 9, 'Ekspor')` di dalam transaksi rollback (simulasi rilis realistis tanpa melemahkan assertion & tanpa menyentuh DB permanen).
- **Focused Tests (`5` suites):**
  - `php artisan test tests/Feature/Phase2gBrsDecimalPrecisionTest.php tests/Feature/Phase2gNeracaFilteringAndExportTest.php tests/Feature/Phase2gBatchLifecycleAggregationTest.php tests/Feature/DataReconciliationAndIndexTest.php tests/Feature/Phase2gExportRbacPolicyTest.php`
  - Hasil: **`20/20 passed, 235 assertions, 4.43s, exit code 0`**.
- **Full Regression Suite:**
  - `php artisan test`
  - Hasil: **`158/158 passed, 1088 assertions, 103.16s, exit code 0`** *(0 failed, 0 errors, 0 skipped)*.

---

## 7. Hasil Review Independen (`database-guardian`, `statistics-auditor`, `independent-reviewer`)

| Agen Reviewer | Verdict | Ringkasan Hasil Review Pasca-Eksekusi |
|---|---|---|
| **`database-guardian`** | **`PASS`** | Memverifikasi kelengkapan backup `mysqldump` (`13.896.003` bytes, SHA-256), kesesuaian seluruh delta baris BEFORE/AFTER/POST-TEST, idempotensi Batch `#70` (`785 -> 768`, `17` multi-tx, `0` fallback `transactions`), rekonsiliasi `0.000000` seluruh 9 batch, keutuhan SHA-256 30.147 historis & Batch `#40`, operasional `sidatex` 0 mutasi, dan sahnya penyesuaian `DistributionModuleTest.php:37-38`. Catatan minor non-blocking: tambahkan `/storage/backups/` ke `.gitignore` sebelum commit Git berikutnya. |
| **`statistics-auditor`** | **`PASS`** | Memverifikasi eksak seluruh rekonsiliasi 6 desimal (`0.000000` diff) pada 9 batch termasuk 6 kategori dimensi Tier-2 Batch `#70` (`US$ 167.347.655,077700`, `MoM/YoY/C-to-C = NULL`), menghitung ulang seluruh persentase MoM Tier-2 (`#82 = -16.06%`, `#81 = 3.93%`, `#74 = 28.63%`, `#71 = 3.77%`) 100% cocok rumus BPS, memverifikasi enrichment 30.147 historis (`273` Migas / `$5,04 M`), pemisahan tegas Distribusi vs Neraca, keutuhan Batch `#40`, dan validitas `DistributionModuleTest.php`. |
| **`independent-reviewer`** | **`APPROVED`** | Memverifikasi kepatuhan penuh instruksi pengguna (A–G), tidak ada scope creep (`StatisticsCalculationService.php` diverifikasi belum tersentuh), `DistributionModuleTest.php:37-38` tidak melemahkan satu pun assertion, dan seluruh konsistensi aritmetika & MoM 9 batch 100% eksak. *(Bukti live SQL, `mysqldump`, SHA-256 live, & PHPUnit `158/158` diterima dari log Orchestrator dalam mode `view_file`-only.)* |

---

## 8. Daftar Risiko yang Masih Tersisa (Di Luar Ruang Lingkup Phase 2G.2B)

1. **Batch `#40` (`691` Baris Tier-1 & `58` Baris Origin `2026-08` Ekspor Distribusi):** Sengaja dibiarkan utuh sesuai Bagian D. Membutuhkan rencana remediasi terpisah karena: (a) seluruh 691 baris Tier-1 terkorupsi dimensi pelabuhannya (`16` pelabuhan dipaksa ke `38: Dumai`, overstated `+$407,49 Jt`), (b) `58` baris Origin Mart (`O2`) masih terekspos di `/distribusi/metrics`, dan (c) `691` baris `B2` (`PRIVATE`) masih menjadi basis internal MoM `#71` (`2026-09`) bagi Role 1/3.
2. **Phase 2G.3 (`StatisticsCalculationService` & `DashboardController`):** Belum dimulai. `StatisticsCalculationService` belum menerapkan filter `status_visibilitas`, masih mengembalikan `0.0` saat pembanding kosong, belum menerapkan Policy B (QoQ 3 bulan penuh), serta `DashboardController::index()` (`$historicalYears`) & `DistributionStatisticsService::getDistributionTradeBalance()` menunggu penyelarasan Phase 2G.3.
3. **Phase 2G.4 (Dashboard Blade UI & Browser Verification):** Belum dimulai.
4. **Status Historis `30.147` Baris (`PRIVATE`):** Tetap `PRIVATE` sesuai Opsi A-1; setelah Phase 2G.3 mengaktifkan filter `RILIS`, seluruh periode `2023–2026 H1` tidak tampil untuk Pegawai (Role 2) sampai ada keputusan publikasi resmi BPS atau re-upload terverifikasi.
5. **Higienitas `.gitignore` untuk `/storage/backups/`:** File `.gitignore` saat ini belum mencantumkan `/storage/backups/`, sehingga perlu ditambahkan sebelum staging/commit Git agar dump SQL `sidatex_testing_pre_2g2b_20261118.sql` (`13,89 MB`) tidak ikut ter-track.
6. **Isolasi Database Operasional (`sidatex`):** Seluruh eksekusi Phase 2G.2B hanya dilakukan pada `sidatex_testing`. Penerapan ke produksi membutuhkan jendela perubahan dan persetujuan terpisah.
