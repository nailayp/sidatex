# SIDATEX — AUDIT STATISTIK READ-ONLY PERSIAPAN PHASE 2G.5
## Import MoM Growth Statistical Audit (`DistributionStatisticsService::getSummaryMetrics()`)

| Atribut | Nilai |
|---|---|
| **Fase** | Phase 2G.5 Preparation — Statistical Audit (100% READ-ONLY) |
| **Tanggal Audit** | 2026-11-18 |
| **Live PDO Verifikasi** | `sidatex_testing` (`SELECT DATABASE() === 'sidatex_testing'`) |
| **Database Operasional (`sidatex`)** | **TIDAK TERSENTUH (ZERO TOUCH)** — seluruh baseline identik |
| **Mutasi Kode / DB / Migration** | **0 perubahan kode, 0 migration, 0 mutasi data, 0 perubahan `HS 27075000`** |
| **Tinjauan Independen** | `statistics-auditor` (Strict Read-Only) |
| **Keputusan Utama** | **YA — NILAI `-100` ADALAH KESALAHAN INTERPRETASI STATISTIK; PERUBAHAN BACKEND PHASE 2G.5 DIPERLUKAN** |
| **Status Implementasi** | **BELUM DIMULAI** — Menunggu persetujuan tertulis terpisah |

---

## Ringkasan Eksekutif

Nilai `impor.pertumbuhan.mtom_total = -100` pada payload `/distribusi/metrics?tahun=2026&bulan=9` **BUKAN merupakan hasil perhitungan statistik yang sah**, melainkan **kesalahan interpretasi akibat koersi data yang tidak tersedia (`NULL`) menjadi `0`** di `DistributionStatisticsService::getSummaryMetrics()`.

- Pada `sidatex_testing`, data Impor Distribusi September 2026 (`Batch #74`, `931` transaksi, `US\$ 215,26 juta`) **berstatus `PRIVATE` (belum `RILIS`)**, sedangkan Impor Agustus 2026 (`Batch #70`, `785` transaksi, `US\$ 167,35 juta`) berstatus `RILIS`.
- Filter visibilitas `getReleasedBatchIds()` berjalan **100% benar** (mengembalikan hanya `[70, 71]`, tanpa kebocoran `PRIVATE` maupun `Trash`).
- Akar masalahnya berasal dari **kombinasi fallback `?? 0` dan ketiadaan guard eksistensi periode berjalan (`COUNT(*)`)** pada `getSummaryMetrics()` (`L76–168`), yang mengubah hasil agregasi SQL `NULL` (tidak ada baris `RILIS`) menjadi angka `0` sebelum dimasukkan ke rumus `(($curr / $prev) * 100) - 100`.
- Cacat yang sama **tidak hanya mengenai `impor.pertumbuhan.mtom_total`**, tetapi juga **seluruh 9 indikator pertumbuhan** (`mtom_*`, `yony_*`, `ctoc_*`) untuk **Impor maupun Ekspor** — terbukti live pada **`2026-09` Impor (`mtom_total/nonmigas/migas = -100`)** dan **`2026-10` Ekspor (`mtom_total/nonmigas/migas = -100`)**.

---

## 1. Temuan Teknis & Lokasi Kode

### 1.1 Sumber Data & Filter Visibilitas (`app/Services/DistributionStatisticsService.php`)
- **`getReleasedBatchIds()` (`L22–32`):**
  - Menarik `batch_id` dari `data_batches` dengan syarat `tipe_sumber = 'distribusi'`, `status = 'RILIS'`, serta `batch_id NOT IN (SELECT batch_id FROM trash WHERE restored_at IS NULL)`.
  - Pada `sidatex_testing`, mengembalikan tepat **`[70, 71]`**:
    - `Batch #70`: `Impor | 2026-08 | RILIS` (`785` transaksi, `US\$ 167.347.655,077700`)
    - `Batch #71`: `Ekspor | 2026-09 | RILIS` (`841` transaksi, `US\$ 2.301.809.204,587000`)
  - Empat batch `PRIVATE` (`#85` Ekspor `2026-07`, `#720` Ekspor `2026-08`, `#86` Impor `2026-07`, `#74` Impor `2026-09`) seluruhnya **terisolasi penuh (0 kebocoran `PRIVATE` dan 0 kebocoran `Trash`)**.

### 1.2 Akar Penyebab `-100` (`app/Services/DistributionStatisticsService.php:76–168`)
1. **Ketiadaan `COUNT(*)` pada query agregasi (`L76–93` & `L96–113`):**
   - `$queryAgg` dan `$queryCum` hanya melakukan `SELECT SUM(t.nilai) ... ->first()` tanpa `GROUP BY` dan tanpa `COUNT(*) as row_count`.
   - Dalam MySQL, `SUM(...)` tanpa `GROUP BY` terhadap `0` baris cocok **selalu mengembalikan 1 objek baris dengan seluruh kolom bernilai `NULL`** (bukan `null` objek dan bukan `0`).
2. **Koersi paksa `NULL → 0` sebelum perhitungan pertumbuhan (`L157–167`):**
   ```php
   'mtom_total'    => $calcGrowth($currM->nilai_total ?? 0, $prevM->nilai_total ?? 0),
   'mtom_nonmigas' => $calcGrowth($currM->nilai_nonmigas ?? 0, $prevM->nilai_nonmigas ?? 0),
   'mtom_migas'    => $calcGrowth($currM->nilai_migas ?? 0, $prevM->nilai_migas ?? 0),
   // pola identik pada yony_total, yony_nonmigas, yony_migas, ctoc_total, ctoc_nonmigas, ctoc_migas
   ```
   Operator `?? 0` **menghapus pembedaan antara "data periode tidak tersedia/belum rilis (`NULL`)" dan "nilai perdagangan riil `0`"**.
3. **Guard asimetris & aritmetika *floating-point* pada `$calcGrowth` (`L130–133`):**
   ```php
   $calcGrowth = function ($curr, $prev) {
       if ($prev === null || $prev == 0) return null; // hanya memeriksa $prev, TIDAK memeriksa $curr!
       return round((($curr / $prev) * 100) - 100, 2); // pembagian float, belum BCMath scale 8
   };
   ```
   Ketika `$currM->nilai_total` adalah `NULL` → dikoersi jadi `0` → `$prevM->nilai_total` adalah `167347655.077700` → `((0 / 167347655.0777) * 100) - 100 = -100.0`.

### 1.3 Temuan Sekunder Terkait pada File yang Sama (Observasi Read-Only)
- **`getTopCommodities()` (`L349–359`):** Saat periode kosong (`2026-08` Ekspor `RILIS`, `0` transaksi), fungsi ini tetap menambahkan item semu `['hs2' => 'XX', 'nama_komoditas' => 'Lainnya', 'nilai_juta_usd' => 0, 'share_persen' => 0]` alih-alih mengembalikan `'items' => []` seperti metode lainnya.
- **`getDistributionTradeBalance()` (`L190, L202–207`):** Cek keberadaan kumulatif menggunakan nilai juta yang sudah dibulatkan (`$fobCum > 0`) dan mengurangkan nilai juta yang sudah dibulatkan alih-alih `bcsub` dari USD 6 desimal sebelum dibagi `1.000.000`. *(Nuansa jendela kumulatif staggered tetap tidak boleh diubah diam-diam tanpa keputusan metodologi.)*

---

## 2. Contoh Kondisi Data & Hasil Perhitungan (Live `sidatex_testing` & 6 Skenario Wajib)

### 2.1 Bukti Live pada `sidatex_testing` (Per 2026-11-18, `RELEASED_BATCH_IDS = [70, 71]`)

| Jenis | Periode | `sql_row_count` (RILIS) | `sql_nilai_total` | `bulan_ini_juta` | `kumulatif_juta` | `mtom_total` Saat Ini | `mtom_nonmigas` / `mtom_migas` Saat Ini | Seharusnya |
|---|---|---:|---|---:|---:|---|---|---|
| Impor | `2026-07` | `0` (`#86 PRIVATE`) | `NULL` | `0` | `0` | `null` | `null` / `null` | `null` |
| Impor | `2026-08` | `785` (`#70 RILIS`) | `167347655.077700` | `167,35` | `167,35` | `null` | `null` / `null` | `null` |
| **Impor** | **`2026-09`** | **`0` (`#74 PRIVATE`)** | **`NULL`** | **`0`** | **`167,35`** | **`-100` (SALAH!)** | **`-100` / `-100` (SALAH!)** | **`null`** |
| Impor | `2026-10` | `0` (belum ada) | `NULL` | `0` | `167,35` | `null` | `null` / `null` | `null` |
| Ekspor | `2026-08` | `0` (`#720 PRIVATE`) | `NULL` | `0` | `0` | `null` | `null` / `null` | `null` |
| Ekspor | `2026-09` | `841` (`#71 RILIS`) | `2301809204.587000` | `2.301,81` | `2.301,81` | `null` | `null` / `null` | `null` |
| **Ekspor** | **`2026-10`** | **`0` (belum ada)** | **`NULL`** | **`0`** | **`2.301,81`** | **`-100` (SALAH!)** | **`-100` / `-100` (SALAH!)** | **`null`** |

*(Sebagai perbandingan: apabila `Batch #74` Impor `2026-09` sebesar `US\$ 215.260.644,472700` kelak berstatus `RILIS`, pertumbuhan MoM Impor riil terhadap `Batch #70` `2026-08` adalah **`+28,63%`**, bukan `-100%`!)*

### 2.2 Evaluasi 6 Kondisi Edge Case Wajib

| # | Kondisi Pengujian | `curr_rows` | `prev_rows` | Hasil Saat Ini | Hasil Seharusnya (BPS) | Keterangan |
|---|---|---:|---:|---|---|---|
| **1** | **Data bulan berjalan kosong** (`2026-09` Impor, `2026-10` Ekspor) | `0` | `> 0` | **`-100`** *(SALAH)* | **`null`** (`—`) | Tidak ada observasi bulan berjalan; berlaku untuk `mtom_*`, `yony_*`, dan `ctoc_*` (agar `ctoc` tidak membandingkan `Jan–Agu` vs `Jan–Sep`). |
| **2** | **Data bulan sebelumnya kosong** (`2026-08` Impor, `2026-09` Ekspor) | `> 0` | `0` | `null` | **`null`** (`—`) | Periode pembanding tidak tersedia. |
| **3** | **Kedua periode memiliki data** (mis. `#70` & `#74` sama-sama `RILIS`) | `> 0` | `> 0` | `+28.63` (float) | **`+28.63`** (BCMath scale 8→2) | Perbandingan dua observasi resmi yang valid; jika datar riil (`curr === prev > 0`) menghasilkan **`0.0`**. |
| **4** | **Nilai periode pembanding benar-benar nol** (`prev_rows > 0`, `prev = 0`) | `> 0` | `> 0` (`0`) | `null` | **`null`** (`—`) | Pembagian dengan nol tidak terdefinisi (`AGENTS.md §7`). |
| **5** | **Nilai periode berjalan benar-benar nol pada periode yang ADA** (mis. bulan `RILIS` `curr_rows > 0` dengan Non-Migas `US\$ 200 jt` namun **Migas riil `0`** vs bulan lalu Migas `US\$ 8,62 jt`) | `> 0` (`migas = 0`) | `> 0` (`migas > 0`) | `-100.0` | **`-100.0`** *(sah HANYA bila `curr_rows > 0`!)* | Bulan berjalan terobservasi resmi; tidak adanya transaksi Migas di bulan rilis tersebut merupakan penurunan ekonomi riil `100%`. |
| **6** | **Data belum `RILIS` (`PRIVATE`/`DRAFT`) atau di `Trash` (akses Role 2)** | `0` *(terfilter)* | `> 0` | **`-100`** *(SALAH)* | **`null`** (`—`) | `PRIVATE`/`DRAFT`/`Trash` terisolasi penuh (0 kebocoran), dan periode yang belum dirilis wajib tampil tidak tersedia (`null`). |

---

## 3. Analisis Kesesuaian Metodologi Statistik & Konsistensi Kode SIDATEX

Perbandingan seluruh mesin perhitungan pertumbuhan di codebase SIDATEX membuktikan bahwa `DistributionStatisticsService::getSummaryMetrics()` adalah **satu-satunya outlier tersisa** yang masih melanggar `AGENTS.md §7`:

| Modul / Service | Fungsi Pertumbuhan | Guard Periode Berjalan Kosong | Presisi Aritmetika | Status Konsistensi |
|---|---|---|---|---|
| `StatisticsCalculationService` (`L86–100, L233–301`) | MoM, QoQ (Kebijakan B), YoY, C-to-C | Flag eksplisit `$hasMonthCurrent` / `$hasData` → **`null`** (anti-`-100%`) | BCMath scale 8 → `round(..., 2)` | **SESUAI BPS** |
| `DashboardController::data()` (`L507–602`) | `pivotBulanFob`, `growthMomTable`, `growthQoqTable` | Hanya memproses bulan/triwulan berdata + `calcBpsGrowth()` → **`null`** | BCMath scale 8 → `round(..., 2)` | **SESUAI BPS** |
| `BrsAggregationService` (`L538–540, L714–729`) | Tier-2 `brs_indicator_summaries` (MoM, YoY, C-to-C) | Dieksekusi pada batch aktif + guard `$currVal === null \|\| $prevVal === null` → **`null`** | BCMath scale 8 → `round(..., 2)` | **SESUAI BPS** |
| `ExcelPreviewController` (`L193–238`) | Pratinjau MoM, QoQ (Kebijakan B), YoY, C-to-C | Guard `$valCurrM > 0 && $valPrevM > 0` → **`null`** | BCMath (QoQ) / float (lainnya) | **SESUAI GUARD `null`** |
| **`DistributionStatisticsService` (`L76–168`)** | **MoM, YoY, C-to-C (`total`/`nonmigas`/`migas`)** | **TIDAK ADA — `?? 0` memaksa `NULL → 0` → menghasilkan `-100` palsu!** | **Float `($curr / $prev) * 100 - 100`** | **MENYIMPANG (BUTUH FIX PHASE 2G.5)** |

---

## 4. Risiko terhadap Dashboard & Interpretasi Pengguna

1. **Disinformasi Makro pada Periode Default (`September 2026`):**
   - Pada `pages/distribusi-dashboard.blade.php` (yang memuat `2026-09` secara default), kartu KPI Total Impor menampilkan **`M-to-M: -100,00%` berwarna merah** tepat ketika Impor September 2026 sebenarnya bernilai `US\$ 215,26 juta` (tumbuh `+28,63%`) dan hanya belum berstatus `RILIS`.
   - Pengguna eksekutif atau pegawai dapat salah menyimpulkan terjadi kolaps impor 100% di Provinsi Riau.
2. **Anomali Berulang Setiap Awal Bulan & Awal Tahun:**
   - Setiap kali pengguna memilih bulan berjalan sebelum batch dirilis (mis. `2026-10` Ekspor saat `#71` `2026-09` sudah `RILIS`), seluruh indikator MoM Ekspor langsung melaporkan `-100%`.
   - Ketika tahun 2027 mulai tersedia atau dipilih, `yony_*` dan `ctoc_*` juga akan melaporkan `-100%` palsu terhadap `2026`.
3. **Risiko Ketidakcocokan Jendela Kumulatif (`C-to-C`):**
   - Tanpa syarat `$currRows > 0` pada `ctoc_*`, saat bulan ke-9 belum dirilis sementara bulan ke-8 sudah dirilis, `currCum` hanya berisi 8 bulan sedangkan `prevCum` (jika tahun lalu lengkap) berisi 9 bulan — menghasilkan pembandingan jendela waktu yang timpang.
4. **Verifikasi Isolasi `PRIVATE` & `Trash`:**
   - Tidak ditemukan kebocoran data `PRIVATE` atau `Trash` pada query `DistributionStatisticsService` (semua tetap tersaring ketat lewat `getReleasedBatchIds()`). Namun menampilkan `-100%` pada bulan yang belum rilis menciptakan sinyal menyesatkan yang kontradiktif dengan status `Data Impor Belum Tersedia` di kartu Neraca sebelahnya.

---

## 5. Rekomendasi Perbaikan (Ruang Lingkup Terkendali Phase 2G.5)

Perbaikan dapat dilakukan secara sangat terlokalisasi, aman, dan *backward-compatible* **tanpa migration dan tanpa mutasi database**:

1. **Tambahkan `COUNT(*) as row_count` pada `$queryAgg` (`L84–92`) dan `$queryCum` (`L104–112`) di `app/Services/DistributionStatisticsService.php`:**
   - Bedakan secara tegas antara `$hasCurrM = ((int) ($currM->row_count ?? 0) > 0)` (periode terobservasi) vs `0` baris (`NULL` / belum dirilis).
   - Lakukan hal identik untuk `$hasPrevM`, `$hasPrevY`, `$hasCurrCum`, dan `$hasPrevCum`.
2. **Delegasikan perhitungan pertumbuhan ke `StatisticsCalculationService::calcBpsGrowth()` (BCMath scale 8 → `round(..., 2)`):**
   - Suntikkan `StatisticsCalculationService` melalui konstruktor dengan parameter opsional agar `new DistributionStatisticsService()` tanpa argumen pada `DistributionModuleTest.php:22` tetap kompatibel 100%:
     ```php
     public function __construct(?StatisticsCalculationService $statService = null)
     {
         $this->statService = $statService ?? app(StatisticsCalculationService::class);
     }
     ```
   - Terapkan helper ringkas `$calcMetricGrowth(bool $hasCurr, ?string $currRaw, bool $hasPrev, ?string $prevRaw): ?float`:
     - Bila `!$hasCurr || !$hasPrev || $currRaw === null || $prevRaw === null` → kembalikan **`null`**.
     - Selain itu, teruskan string 6 desimal (`bcadd($currRaw, '0', 6)`, `bcadd($prevRaw, '0', 6)`) ke `$this->statService->calcBpsGrowth(...)`.
     - **Untuk `ctoc_*`:** syaratkan `($hasCurrM && $hasCurrCum)` dan `$hasPrevCum` agar jendela kumulatif `Jan..$bulan` tidak dihitung sebelum bulan `$bulan` dirilis.
   - **Hasil presisi untuk semua kasus:**
     - `curr_rows === 0` → **`null`** (menutup bug `-100%` pada `2026-09` Impor, `2026-10` Ekspor, dan `2027` YoY/C-to-C).
     - `curr_rows > 0` dengan sub-komponen `migas = 0.000000` & `prev_migas > 0` → **`-100.0`** (penurunan riil tetap akurat!).
     - `curr === prev > 0` → **`0.0`** (datar riil).
     - `prev === 0` atau `prev_rows === 0` → **`null`** (pembagian nol terlindungi).
3. *(Opsional — direkomendasikan sekalian dalam ruang lingkup `DistributionStatisticsService.php`)*:
   - Perbaiki `getTopCommodities()` (`L353–359`) agar hanya menambahkan baris `'Lainnya'` bila `$totalNonmigasVal > 0`, sehingga periode kosong mengembalikan `'items' => []` konsisten dengan 4 metode tabel lainnya.
   - Selaraskan `getDistributionTradeBalance()` (`L190, L202–207`) agar cek eksistensi kumulatif & pengurangan surplus/defisit menggunakan USD mentah (`nilai_total_usd` + BCMath `bcsub`) sebelum pembulatan jutaan.
4. **Tidak diubah di Phase 2G.5:**
   - **0 migration**, **0 perubahan database**, **0 re-agregasi**, **0 perubahan `HS 27075000` (`oilgrp_code = 1`)**, **0 sentuhan `sidatex`**, **0 perubahan Batch `#71` / `#720` / `30.147` historis / `2.896` Neraca**.

---

## 6. Daftar Pengujian yang Diperlukan (Saat Phase 2G.5 Disetujui)

Buat suite pengujian terisolasi (`tests/Feature/Phase2gDistributionGrowthAuditTest.php`, menggunakan transaksi rollback pada tahun sentinel `2092–2096` serta verifikasi payload live `sidatex_testing`):

1. **Live `2026-09` Impor (`/distribusi/metrics` & `getSummaryMetrics`):** `mtom_total`, `mtom_nonmigas`, `mtom_migas` wajib **`null`** (bukan `-100`), sementara `bulan_ini.nilai_total_juta === 0.0` dan `kumulatif.nilai_total_juta === 167.35`.
2. **Live `2026-10` Ekspor:** `mtom_total`, `mtom_nonmigas`, `mtom_migas` wajib **`null`** (bukan `-100`).
3. **Kedua periode `RILIS` (normal & datar riil):** bulan `M` & `M-1` `RILIS` → MoM sesuai BCMath 2 desimal (`+28.63%` untuk simulasi in-transaction `#74` vs `#70`, dan tepat `0.0` untuk nilai sama).
4. **Periode pembanding kosong atau riil nol (`prev = 0`):** wajib **`null`**.
5. **Sub-komponen riil nol di bulan `RILIS` (`curr_rows > 0`, `migas = 0`, `nonmigas > 0`, `prev_migas > 0`):** `mtom_migas` wajib **`-100.0`** sementara `mtom_total` dan `mtom_nonmigas` terhitung normal (membuktikan pembedaan presisi antara *missing period* vs *observed zero*).
6. **Isolasi `PRIVATE`, `DRAFT`, dan `Trash`:** batch `PRIVATE`/`DRAFT`/aktif `Trash` pada bulan berjalan menghasilkan `row_count = 0` → seluruh pertumbuhan **`null`** tanpa kebocoran data; batch `Trash` yang sudah di-restore kembali terhitung sah.
7. **Guard `yony_*` dan `ctoc_*`:** bulan berjalan kosong dengan tahun lalu berisi wajib menghasilkan `yony_* = null` dan `ctoc_* = null` (anti-`-100%` dan anti-jendela kumulatif timpang).
8. **Regresi penuh:** `DistributionModuleTest`, `Phase2gBladeAlignmentTest`, dan seluruh suite PHPUnit (`169+` tests) lulus 100% tanpa melemahkan assertion, plus verifikasi integritas SHA-256 `sidatex_testing` & operasional `sidatex`.

---

## 7. Keputusan: Apakah Diperlukan Perubahan Backend?

**KEPUTUSAN: YA — PERUBAHAN BACKEND TERBATAS (PHASE 2G.5) DIPERLUKAN.**

- **Alasan:** Masalah `-100%` pada `/distribusi/metrics` bersumber langsung dari logika koersi `?? 0` dan ketiadaan guard `COUNT(*)` di backend `app/Services/DistributionStatisticsService.php`. Masalah ini **tidak dapat dan tidak boleh ditutup di sisi Blade UI**, karena di sisi UI nilai `-100` dari JSON tidak dapat dibedakan dari penurunan riil `-100%` (misalnya sub-komponen Migas yang benar-benar turun menjadi nol).
- **Kesiapan Frontend:** Karena Phase 2G.4 telah menyelesaikan null-handling pada `resources/views/pages/distribusi-dashboard.blade.php` (`null → '—'`, `0 → '0,00%'`), begitu backend Phase 2G.5 mengembalikan `null` untuk periode tak tersedia, tampilan KPI M-to-M pada dashboard distribusi akan **langsung otomatis tampil sebagai `—` secara konsisten tanpa perlu mengubah Blade lagi**.

---

> **Gerbang Persetujuan:** Audit statistik *read-only* persiapan Phase 2G.5 telah selesai. **Belum ada perubahan kode, migration, maupun database yang dilakukan.** Mohon keputusan dan persetujuan tertulis Anda sebelum saya memulai implementasi Phase 2G.5 (termasuk apakah 2 perbaikan sekunder opsional pada `getTopCommodities()` dan presisi `bcsub` `getDistributionTradeBalance()` turut disertakan atau hanya fokus pada `getSummaryMetrics()`).
