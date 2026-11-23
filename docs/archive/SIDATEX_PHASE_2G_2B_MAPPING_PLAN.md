# SIDATEX — PHASE 2G.2B: HISTORICAL VISIBILITY MAPPING & BACKFILL PLAN (READ-ONLY)

**Tanggal Audit:** 18 November 2026  
**Mode Eksekusi:** **100% READ-ONLY** *(0 UPDATE, 0 DELETE, 0 INSERT, 0 Migration, 0 Backfill)*  
**Target Database:** `sidatex_testing` *(terverifikasi melalui live PDO `SELECT DATABASE() === 'sidatex_testing'`)*  
**Status:** **RENCANA PEMETAAN FINAL — MENUNGGU PERSETUJUAN TERTULIS PENGGUNA SEBELUM EKSEKUSI**  
**Verdict Ketiga Reviewer Independen:**
- `database-guardian`: **`PASS WITH MINOR NOTES`** *(seluruh 4 catatan penguatan telah dimasukkan)*
- `statistics-auditor`: **`PASS WITH MINOR NOTES`** *(seluruh 4 klarifikasi metodologis telah dimasukkan)*
- `independent-reviewer`: **`APPROVED WITH MINOR NOTES`** *(seluruh 5 catatan penyempurnaan telah dimasukkan)*

> [!CAUTION]
> **Komitmen Proteksi Data:**
> - Dokumen ini **HANYA** berisi hasil audit forensik read-only, klasifikasi bukti, simulasi dampak, dan rancangan rencana sinkronisasi.
> - **BELUM ADA** satu pun baris data pada `sidatex_testing` maupun database operasional `sidatex` yang diubah.
> - Tidak ada `batch_id` yang ditebak hanya dari tahun/bulan; tidak ada data `PRIVATE` yang otomatis dijadikan `RILIS`; tidak ada baris `batch_id IS NULL` yang dianggap korup atau direncanakan untuk dihapus tanpa persetujuan eksplisit.

---

## 1. Database dan Kondisi Awal (`sidatex_testing`)

Verifikasi live PDO runtime sebelum seluruh query read-only:
- `SELECT DATABASE()` = **`sidatex_testing`** *(PASS)*
- `migrations` count = **`28`** *(termasuk `2026_11_18_000001_upgrade_mart_decimal_precision_to_20_6`)*
- `trash` aktif (`restored_at IS NULL`) = **`0`**, `comments` = **`0`**, sentinel `>= 2090` = **`0`**

| Tabel di `sidatex_testing` | Jumlah Baris | Status Visibilitas / Catatan |
|---|---:|---|
| `data_batches` | **9** | `1` batch **`RILIS`** (`#70`), `8` batch **`PRIVATE`** (`#71, #74, #81, #82, #85, #86, #87, #88`), `0` Trash |
| `distribusi_transactions` | **3.575** | Terkait ke 4 batch Distribusi `PRIVATE` (`#71, #74, #85, #86`); **Batch `#70` (`RILIS`) = `0` baris di tabel ini** |
| `neraca_transactions` | **4.065** | Terkait ke 4 batch Neraca `PRIVATE` (`#81, #82, #87, #88`) |
| `transactions` *(tabel arsip legacy)* | **2.557** | Berisi sisa transaksi pra-pemisahan tabel: **Batch `#70` (`785` baris)**, `#71` (`841`), `#74` (`931`) |
| `monthly_trade_summaries` *(Tier-1)* | **39.110** | **100% berstatus `PRIVATE`** (`0` baris `RILIS`); `31.606` baris `batch_id IS NULL` + `7.504` baris `batch_id IS NOT NULL` |
| `monthly_origin_trade_summaries` | **189** | Tidak memiliki kolom `status_visibilitas`; berisi Ekspor Distribusi `2026-07` (`65`), `2026-08` (`58`), `2026-09` (`66`) |
| `brs_indicator_summaries` *(Tier-2)* | **338** | **100% berstatus `PRIVATE`**, terkait ke 8 batch `#71–#88`; **Batch `#70` (`RILIS`) = `0` baris** |

---

## 2. Inventarisasi Seluruh Data (`data_batches`, Transaksi, dan 3 Tabel Mart)

### 2.1 Inventarisasi Seluruh `data_batches` Aktif (9 Batch — Dipisahkan per Metodologi)

#### A. Metodologi Distribusi (5 Batch)

| Batch | Jenis | Periode | Status Batch | Raw Tx (`distribusi_transactions`) | Archived `transactions` | Tier-1 (`mts`) | Tier-1 Vis | Drift Nilai Tier-1 vs Raw (USD) | Tier-2 (`brs`) |
|---:|---|---|---|---:|---:|---:|---|---:|---:|
| **#85** | `Ekspor` | `2026-07` | `PRIVATE` | `814` (`$1.910.605.444,960000`) | `0` | `805` | `PRIVATE` | `-0.570000` | `44` |
| **#86** | `Impor` | `2026-07` | `PRIVATE` | `989` (`$199.716.065,392700`) | `0` | `978` | `PRIVATE` | `-2.562700` | `38` |
| **#70** | `Impor` | `2026-08` | **`RILIS`** | **`0`** *(kosong!)* | **`785`** (`$167.347.655,15`) | `0` *(linked)* / **`768` (`NULL`)** | `PRIVATE` *(unlinked)* | `0.000000` vs arsip *(+$0,0723 vs DBF asli)* | **`0`** |
| **#71** | `Ekspor` | `2026-09` | `PRIVATE` | `841` (`$2.301.809.204,587000`) | `841` | `831` | `PRIVATE` | `-0.587000` | `47` |
| **#74** | `Impor` | `2026-09` | `PRIVATE` | `931` (`$215.260.644,472700`) | `931` | `918` | `PRIVATE` | `-2.112700` | `38` |
| **Subtotal Distribusi** | — | — | **1 RILIS, 4 PRIVATE** | **3.575** | **2.557** | **3.532 linked** | — | **Ekspor FOB: `-1.157000` / Impor CIF: `-4.675400` *(Total 4 batch linked: `-5.832400`)*** | **167** |

#### B. Metodologi Neraca (4 Batch)

| Batch | Jenis | Periode | Status Batch | Raw Tx (`neraca_transactions`) | Tier-1 (`mts`) | Tier-1 Vis | Drift Nilai Tier-1 vs Raw (USD) | Tier-2 (`brs`) |
|---:|---|---|---|---:|---:|---|---:|---:|
| **#88** | `Impor` | `2026-07` | `PRIVATE` | `1.169` (`$210.026.242,934800`) | `1.153` (`175 KEY_0 + 978 KEY_GT0`) | `PRIVATE` | `-3.044800` | `39` |
| **#87** | `Ekspor` | `2026-08` | `PRIVATE` | `925` (`$2.226.732.699,021600`) | `901` (`109 KEY_0 + 792 KEY_GT0`) | `PRIVATE` | `-0.571600` | `45` |
| **#82** | `Impor` | `2026-08` | `PRIVATE` | `1.021` (`$176.287.710,172900`) | `993` (`224 KEY_0 + 769 KEY_GT0`) | `PRIVATE` | `-2.662900` | `39` |
| **#81** | `Ekspor` | `2026-09` | `PRIVATE` | `950` (`$2.314.286.425,304600`) | `925` (`102 KEY_0 + 823 KEY_GT0`) | `PRIVATE` | `-0.674600` | `48` |
| **Subtotal Neraca** | — | — | **0 RILIS, 4 PRIVATE** | **4.065** | **3.972 linked** | — | **Ekspor FOB: `-1.246200` / Impor CIF: `-5.707700` *(Total 4 batch linked: `-6.953900`)*** | **171** |

*(Sesuai `AGENTS.md` §6, angka Distribusi dan Neraca tidak pernah digabungkan ke dalam satu total.)*

> [!WARNING]
> **Temuan Forensik Kritis #1 — Batch `#70` (`RILIS`) Tertinggal di Tabel Arsip `transactions`:**
> Batch `#70` (`14_impor_atap_kdprov_0826.DBF`, diunggah 12 Nov 2026 03:01:49, dirilis ke `RILIS` 12 Nov 2026 03:14:11 pada log #79) memiliki **`0` baris di `distribusi_transactions`** karena pemisahan tabel `distribusi_transactions` dilakukan pada 13 Nov (log #94–#130) dan hanya memigrasikan ulang `#71` & `#74`. Akibatnya:
> 1. **785 transaksi mentah Batch `#70`** masih berada di tabel arsip `transactions` (`SUM(nilai) = 167,347,655.15`, `SUM(berat) = 568,581,779.662`), sedangkan berkas DBF asli `referensi_bps/impor_distribusi/14_impor_atap_kdprov_0826.DBF` (155.095 bytes) memiliki exact 6-desimal `SUM(nilai) = 167,347,655.077700`, `SUM(berat) = 568,581,779.655200` (selisih `+$0,0723` USD & `+0,0068` kg berasal dari pembulatan 2 desimal pada tabel arsip lama `DECIMAL(18,2)`). Pada satuan publikasi BRS 2 desimal, **keduanya menghasilkan headline identik: US$ 167,35 juta dan 568,58 ribu ton**.
> 2. Ekspor Excel Batch `#70` saat ini menghasilkan **`0 transaksi`** (terbukti pada `activity_logs` #131, #227, #375) dan Single Dashboard `/dashboard/data` untuk Pegawai (Role 2) menampilkan **0 baris**.
> 3. **768 baris unlinked `2026-08 Impor distribusi`** di `monthly_trade_summaries` cocok **100,00% cube-by-cube (`768/768`, `onlyInMts=0`, `onlyInArch=0`, `mismatch=0`)** dengan 785 baris Batch `#70` di tabel arsip `transactions`.

> [!WARNING]
> **Temuan Forensik Kritis #2 — Drift Presisi Pra-2G.2A pada Seluruh 8 Batch Ter-Link:**
> Seluruh 8 batch ter-link (`#71, #74, #85, #86` pada Distribusi dengan total drift **`-$5.832400`** dan `#81, #82, #87, #88` pada Neraca dengan total drift **`-$6.953900`**) diagregasi pada `2026-11-16 03:42:08..20` sebelum upgrade `DECIMAL(20,6)` dan BCMath scale 6 di Phase 2G.2A.

### 2.2 Inventarisasi Data Historis (`batch_id IS NULL`) di `monthly_trade_summaries` (31.606 Baris)

| Kelompok | Sumber & Jenis | Periode | `pelabuhan_key` | Baris Mart | Jumlah Tx | Total Nilai (USD) | Status Saat Ini | `created_at` | Bukti Provenance (`activity_logs`) |
|---|---|---|---|---:|---:|---:|---|---|---|
| **H1 (2023)** | `distribusi \| Ekspor` | `2023-01..2023-12` (12 bln) | `KEY_0` (`0`) | `7.938` | `10.037` | `18.956.580.029,180000` | `PRIVATE` | `2026-11-10 07:14:03` & `11-11 08:03/10:29` | Agregasi pra-`batch_id` (Batch `#5` `1. (Olah Data BRS) Ekspor LN Riau.xlsx`, 53.066 tx, pernah `RILIS` log #11/#19/#24, dihapus permanen 12 Nov log #69; selisih `37.282` vs `53.066` tx perlu diverifikasi terhadap angka resmi BPS). Kolom `sektor` = `NULL`, `bec_category` = `NULL`, `is_migas` = `0` (default migrasi), `hs_id` & `hs2` terisi 100%. |
| **H2 (2024)** | `distribusi \| Ekspor` | `2024-01..2024-12` (12 bln) | `KEY_0` (`0`) | `8.374` | `10.432` | `18.180.959.009,740000` | `PRIVATE` | `2026-11-10 07:14:03` | Sama dengan H1 (default `PRIVATE` dari migrasi `2026_11_16_000001`). |
| **H3 (2025)** | `distribusi \| Ekspor` | `2025-01..2025-12` (12 bln) | `KEY_0` (`0`) | `9.314` | `11.383` | `21.421.295.812,240000` | `PRIVATE` | `2026-11-10 07:14:03` | Sama dengan H1. |
| **H4 (2026 H1)** | `distribusi \| Ekspor` | `2026-01..2026-06` (6 bln) | `KEY_0` (`0`) | `4.521` | `5.430` | `10.532.831.740,410000` | `PRIVATE` | `2026-11-10 07:14:03` | Sama dengan H1. *(Subtotal H1–H4 = **30.147 baris**, 42 bulan, US$ 69,09 Miliar.)* |
| **U1 (2026-08 Ekspor)** | `distribusi \| Ekspor` | `2026-08` (1 bln) | `KEY_GT0` (`>0`) | `691` | `809` | `2.218.155.995,460000` | `PRIVATE` | `2026-11-11 08:17:38` | Berasal dari **Batch `#40`** (`14_Atap_ekspor_2608_POD_based.DBF`, 809 baris, log #38), di-trash 11 Nov (log #57), **dihapus permanen** 12 Nov (log #73). 0 baris raw di DB. |
| **U2 (2026-08 Impor)** | `distribusi \| Impor` | `2026-08` (1 bln) | `KEY_GT0` (`>0`) | `768` | `785` | `167.347.655,150000` | `PRIVATE` | `2026-11-18 02:11:20` | **100% IDENTIK (`768/768` cube match)** dengan **Batch `#70`** (`14_impor_atap_kdprov_0826.DBF`, **`RILIS`**) di tabel arsip `transactions`. *(Subtotal U1+U2 = **1.459 baris**.)* |

### 2.3 Inventarisasi `monthly_origin_trade_summaries` (189 Baris)

| Periode | Jenis | Baris | Tx | Total Nilai (USD) | `created_at` | Batch Aktif di Periode Sama | Status Kebocoran / Drift |
|---|---|---:|---:|---:|---|---|---|
| `2026-07` | `ekspor` | `65` | `814` | `1.910.605.445,020000` | `2026-11-11 08:17:36` | **Batch `#85` (`PRIVATE`)** (`val=1.910.605.444,96`) | **AMBIGUOUS / BOCOR:** Dibuat dari Batch `#38` (dihapus permanen log #70), namun periode kini dimiliki Batch `#85` (`PRIVATE`) dengan selisih `+$0,06`. Tabel origin tidak punya kolom `status_visibilitas` dan seharusnya hanya berisi `RILIS`. |
| `2026-08` | `ekspor` | `58` | `809` | `2.218.155.995,460000` | `2026-11-11 08:17:38` | **NONE** (Batch `#40` dihapus permanen log #73) | **ORPHAN / TERBUKA PUBLIK:** Tidak ada batch aktif, namun masih terekspos di `/distribusi/metrics` (`DistributionStatisticsService::getOriginDistribution(2026, 8)`). |
| `2026-09` | `ekspor` | `66` | `841` | `2.301.809.204,540000` | `2026-11-12 07:06:07` | **Batch `#71` (`PRIVATE`)** (`val=2.301.809.204,587`) | **MATCHED (VISIBILITY LEAK):** Dibuat saat upload Batch `#71` (`PRIVATE`); membocorkan rincian asal wilayah batch `PRIVATE` ke tampilan publik. |

---

## 3. Rekonsiliasi Jumlah Baris & Irisan Antarkelompok (Zero Double-Counting Proof)

Partisi saling lepas (*mutually exclusive*) atas seluruh **39.110 baris** `monthly_trade_summaries`:

```
TOTAL monthly_trade_summaries = 39.110 baris
├── [A] 30.147 baris : batch_id IS NULL  AND pelabuhan_key = 0  (Distribusi Ekspor 2023-01 .. 2026-06)  --> TERPISAH (0 irisan)
├── [B]  1.459 baris : batch_id IS NULL  AND pelabuhan_key > 0  (Distribusi 2026-08)
│        ├── [B1] 768 baris : Distribusi Impor  2026-08  <=== 100% BERIRISAN / IDENTIK DENGAN BATCH #70 (RILIS)
│        └── [B2] 691 baris : Distribusi Ekspor 2026-08  <=== ORPHAN (sisa Batch #40 yang dihapus permanen)
└── [C]  7.504 baris : batch_id IS NOT NULL                     (8 Batch aktif PRIVATE)                  --> TERPISAH (0 irisan)
         ├── [C1] 3.532 baris : Distribusi (#85:805, #86:978, #71:831, #74:918)
         └── [C2] 3.972 baris : Neraca     (#88:1.153, #87:901, #82:993, #81:925)

VERIFIKASI PENJUMLAHAN: 30.147 + (768 + 691) + (3.532 + 3.972) = 30.147 + 1.459 + 7.504 = 39.110 (EXACT!)
```

**Penegasan Irisan:**
1. **`30.147` vs `1.459`:** **100% TERPISAH (0 irisan)** — berbeda `pelabuhan_key` (`0` vs `>0`) dan berbeda periode (`2023-01..2026-06` vs `2026-08`).
2. **`1.459` vs data mart Batch `#70`:** **BERIRISAN SEBAGIAN (EXACT 768 baris)** — data mart Tier-1 milik Batch `#70` saat ini adalah **768 baris (`B1`) di dalam kelompok `1.459`** (`batch_id = 70` di `monthly_trade_summaries` saat ini `0` baris).
3. **`Batch #70` vs `7.504` baris linked:** **100% TERPISAH (0 irisan).**

---

## 4. Klasifikasi Forensik: `MATCHED`, `AMBIGUOUS`, `UNMAPPED`, dan `ORPHAN`

| Kategori | Tabel Mart | Sub-Kelompok | Jumlah Baris | Periode | Visibilitas Saat Ini | Sumber Transaksi Ditemukan | Batch Sumber Terbukti | Status Batch Sumber |
|---|---|---|---:|---|---|---|---|---|
| **`MATCHED`** | `monthly_trade_summaries` | **B1:** Distribusi Impor `2026-08` (`NULL`, `KEY_GT0`) | **768** | `2026-08` | `PRIVATE` *(mismatch!)* | `785` baris di arsip `transactions` (`100%` cube match) & DBF asli `referensi_bps/impor_distribusi/14_impor_atap_kdprov_0826.DBF` *(0 baris di `distribusi_transactions`)* | **Batch `#70`** | **`RILIS`** |
| **`MATCHED`** | `monthly_trade_summaries` | **C1:** Distribusi Linked (`#85,#86,#71,#74`) | **3.532** | `2026-07`, `2026-09` | `PRIVATE` *(sinkron)* | `3.575` baris di `distribusi_transactions` | **Batch `#85, #86, #71, #74`** | **`PRIVATE`** |
| **`MATCHED`** | `monthly_trade_summaries` | **C2:** Neraca Linked (`#88,#87,#82,#81`) | **3.972** | `2026-07..2026-09` | `PRIVATE` *(sinkron)* | `4.065` baris di `neraca_transactions` | **Batch `#88, #87, #82, #81`** | **`PRIVATE`** |
| **`MATCHED`** | `brs_indicator_summaries` | **T2-C:** Indikator 8 Batch Linked | **338** | `2026-07..2026-09` | `PRIVATE` *(sinkron)* | Sama dengan C1 & C2 *(Batch `#70` = `0` baris, perlu dibangun)* | **Batch `#71,#74,#81,#82,#85,#86,#87,#88`** | **`PRIVATE`** |
| **`MATCHED`** | `monthly_origin_trade_summaries` | **O3:** Distribusi Ekspor `2026-09` | **66** | `2026-09` | *(tanpa kolom vis — tampil publik!)* | `841` baris di `distribusi_transactions` (`batch_id = 71`) | **Batch `#71`** | **`PRIVATE`** *(visibility leak!)* |
| **`AMBIGUOUS`** | `monthly_origin_trade_summaries` | **O1:** Distribusi Ekspor `2026-07` | **65** | `2026-07` | *(tanpa kolom vis — tampil publik!)* | Dibuat `11 Nov` dari Batch `#38` *(dihapus permanen)*, namun periode sama kini dimiliki Batch `#85` (`814` baris, `PRIVATE`, selisih `+$0,06`) | Konflik historis `#38` *(deleted)* vs aktif `#85` (`PRIVATE`) | **`PRIVATE`** *(untuk batch aktif `#85`)* |
| **`UNMAPPED`** | *(Seluruh 3 Mart)* | — | **0** | — | — | Seluruh kelompok teridentifikasi asal-usulnya lewat cube match & `activity_logs` | — | — |
| **`ORPHAN`** | `monthly_trade_summaries` | **A:** Historis Distribusi Ekspor (`NULL`, `KEY_0`) | **30.147** | `2023-01..2026-06` (42 bln) | `PRIVATE` *(default migrasi 16 Nov)* | **0 baris** di DB *(sumber Batch `#5` 53.066 tx &/atau `#17` dihapus permanen 12 Nov log #69/#72)* | Tidak ada batch aktif di `data_batches` | *Batch `#5` pernah `RILIS` sebelum dihapus* |
| **`ORPHAN`** | `monthly_trade_summaries` | **B2:** Distribusi Ekspor `2026-08` (`NULL`, `KEY_GT0`) | **691** | `2026-08` | `PRIVATE` | **0 baris** di DB *(sumber Batch `#40` 809 tx dihapus permanen 12 Nov log #73; DBF asli masih ada di `referensi_bps`)* | Tidak ada batch aktif di `data_batches` | *Dihapus permanen* |
| **`ORPHAN`** | `monthly_origin_trade_summaries` | **O2:** Distribusi Ekspor `2026-08` | **58** | `2026-08` | *(tanpa kolom vis)* | **0 baris** di DB *(pasangan origin dari B2 / Batch `#40`)* | Tidak ada batch aktif di `data_batches` | *Dihapus permanen* |

**Ringkasan Total per Kategori (`monthly_trade_summaries` = 39.110 | `monthly_origin_trade_summaries` = 189 | `brs_indicator_summaries` = 338):**
- **`MATCHED`:** `8.272` Tier-1 (`768 + 3.532 + 3.972`) + `338` Tier-2 + `66` Origin (`2026-09`).
- **`AMBIGUOUS`:** `0` Tier-1 + `0` Tier-2 + `65` Origin (`2026-07`).
- **`UNMAPPED`:** `0` di ketiga tabel mart.
- **`ORPHAN`:** `30.838` Tier-1 (`30.147` historis + `691` `2026-08` Ekspor) + `0` Tier-2 + `58` Origin (`2026-08`).

---

## 5. Rekomendasi Sinkronisasi per Kelompok & Analisis Risiko

> [!IMPORTANT]
> **Simulasi Rencana Saja — TIDAK Diterapkan ke Database pada Tahap Ini.**  
> **Urutan Eksekusi Kronologis Global Wajib (jika disetujui):**  
> `2026-07` (`#85, #86, #88`) -> `2026-08` (**`#70 [RILIS]`**, `#87, #82`) -> `2026-09` (`#71, #74, #81`).  
> Batch **`#70` (`2026-08`) WAJIB selesai sebelum Batch `#74` (`2026-09`)** agar Tier-2 MoM & C-to-C pada `#74` (`PRIVATE`) memakai baseline 6-desimal terbaru `#70` (`$167.347.655,077700`) dan bukan baris 2-desimal lama.

| Kelompok | Kategori | Risiko Jika Visibility Diubah / Tidak Diubah | Rekomendasi Tindakan (Menunggu Keputusan Anda) |
|---|---|---|---|
| **B1 — Batch `#70`** (`768` baris MTS, `0` `dist_tx`, `0` BRS) | **`MATCHED`** (`RILIS`) | **Jika dibiarkan:** Batch `#70` berstatus `RILIS` tetapi Pegawai melihat `0` baris di Dashboard, `0` transaksi saat ekspor Excel, dan `0` baris di Tier-2 BRS. **Jika diperbaiki:** Selaras 100% dengan status resmi `RILIS`. | **WAJIB SINKRONKAN (Pilih salah satu sumber raw):**<br>• **Opsi B1-a (Direkomendasikan — DBF Asli 6 Desimal):** Re-populate `785` baris `distribusi_transactions` (`batch_id = 70`) langsung dari `referensi_bps/impor_distribusi/14_impor_atap_kdprov_0826.DBF` (`$167.347.655,077700`), lalu jalankan `BrsAggregationService::aggregateBatch(70, 'RILIS')` -> `768` Tier-1 `RILIS` + ~38 Tier-2 `RILIS` (drift `0.000000`, headline BRS tetap identik US$ 167,35 Jt, arsip `transactions` tidak tersentuh).<br>• **Opsi B1-b (Konservatif — Dari Arsip DB):** Salin `785` baris dari arsip `transactions` (`$167.347.655,15`, 2 desimal) ke `distribusi_transactions` lalu jalankan `aggregateBatch(70, 'RILIS')` (mewariskan selisih `+$0,0723` vs DBF asli). |
| **C1 & C2 — 8 Batch Linked** (`7.504` MTS, `338` BRS) | **`MATCHED`** (`PRIVATE`) | **Jika status diubah ke `RILIS`:** **DILARANG** — melanggar RBAC. **Jika tidak dire-agregasi:** Menyimpan drift pembulatan pra-2G.2A (Distribusi `-$5.832400`; Neraca `-$6.953900`). | **PERTAHANKAN `PRIVATE` + RE-AGREGASI PRESISI 6 DESIMAL:** Jalankan `BrsAggregationService::aggregateBatch($id, 'PRIVATE')` dalam urutan kronologis global di atas. Status tetap **`PRIVATE`**; drift menjadi **`0.000000`** pada seluruh 8 batch; Tier-2 mendapat isolasi `UNMAPPED` & rollover MoM. |
| **A — 30.147 Baris Historis** (`2023-01..2026-06`, `distribusi \| Ekspor`, `KEY_0`) | **`ORPHAN`** *(Historical Baseline Valid)* | **Jika dibiarkan `PRIVATE`:** Pasca-2G.3, Pegawai kehilangan seluruh tren Ekspor 2023–Jun 2026 (42 bln). **Jika dipromosikan otomatis tanpa persetujuan:** Melanggar prinsip bukti batch & butuh verifikasi angka terhadap publikasi BPS (selisih `37.282` vs `53.066` tx di Batch `#5`). | **JANGAN HAPUS & JANGAN UBAH OTOMATIS — BUTUH KEPUTUSAN KEBIJAKAN ANDA:**<br>• **Opsi A-1 (Konservatif — Default Aman):** Pertahankan `30.147` baris tetap `status_visibilitas = 'PRIVATE'` dan `batch_id = NULL` (`0` mutasi).<br>• **Opsi A-2 (Keputusan Publikasi Historis BPS):** Setelah angka tahunan (`2023: $18,96M`, `2024: $18,18M`, `2025: $21,42M`, `2026 H1: $10,53M`) dikonfirmasi sesuai publikasi resmi BPS Riau, jalankan `UPDATE` terkendali dengan backup pra-eksekusi: `SET status_visibilitas = 'RILIS' WHERE batch_id IS NULL AND pelabuhan_key = 0 AND tipe_sumber = 'distribusi' AND jenis_transaksi = 'Ekspor' AND (tahun < 2026 OR (tahun = 2026 AND bulan <= 6))` (`30.147` baris). |
| **B2 — `691` Baris `2026-08` Ekspor Distribusi** (`NULL`, `KEY_GT0`) | **`ORPHAN`** | **Jika diubah ke `RILIS`:** **BERISIKO TINGGI** — sumbernya (Batch `#40`) telah dihapus permanen (log #73). **Jika dibiarkan `PRIVATE`:** Tetap menjadi basis MoM/QoQ/C-to-C internal bagi Batch `#71` (`2026-09`, Role 1/3) meskipun `distribusi_transactions` = `$0` untuk `2026-08`. | **PERTAHANKAN `PRIVATE` (JANGAN PROMOSIKAN & JANGAN HAPUS OTOMATIS).** Pilihan strategis bagi pengguna di tahap selanjutnya: (a) **Re-ingestion resmi** `14_Atap_ekspor_2608_POD_based.DBF` melalui modul Upload Data agar tertaut ke `batch_id` aktif, **atau** (b) persetujuan eksplisit pembersihan orphan eks Batch `#40`. |
| **O1 (`2026-07`, 65), O2 (`2026-08`, 58), O3 (`2026-09`, 66)** pada `monthly_origin_trade_summaries` | **`AMBIGUOUS` (O1), `ORPHAN` (O2), `MATCHED/LEAK` (O3)** | **Jika dibiarkan:** O1 (`#85 PRIVATE`) & O3 (`#71 PRIVATE`) membocorkan rincian asal wilayah batch `PRIVATE`; **O2 (`2026-08`)** dari Batch `#40` yang sudah dihapus permanen **tetap terekspos publik** di `/distribusi/metrics` (`US$ 2.218,16 Jt` padahal Ekspor `2026-08` = `$0`). | • **O1 (`2026-07`) & O3 (`2026-09`):** Otomatis dibersihkan menjadi `0` baris saat `aggregateBatch(85, 'PRIVATE')` & `aggregateBatch(71, 'PRIVATE')` dijalankan (menutup kebocoran `PRIVATE`; terisi kembali otomatis saat dirilis ke `RILIS`).<br>• **O2 (`2026-08`, 58 baris):** **Jangan hapus tanpa persetujuan** — pilih antara membersihkan `O2` bersamaan dengan keputusan `B2`, atau me-reingest `14_Atap_ekspor_2608_POD_based.DBF`. |

---

## 6. Simulasi Dampak terhadap Dashboard & Indikator Statistik (Read-Only)

Proyeksi tampilan **setelah Phase 2G.2B + Phase 2G.3** (saat filter `status_visibilitas` aktif penuh di `StatisticsCalculationService` dan `DashboardController`):

### 6.1 Perbandingan Skenario untuk Role **Pegawai (Role 2 — Hanya `RILIS`)**

| Metrik / Modul Dashboard (Role 2: Pegawai) | Kondisi Saat Ini (Pasca-2G.3 Tanpa 2G.2B) | Skenario 1: Hanya Sync `MATCHED` (B1 + C1/C2, Opsi A-1: Historis Tetap `PRIVATE`) | Skenario 2: Sync `MATCHED` + Keputusan Kebijakan Opsi A-2 (Historis `2023–2026/06` -> `RILIS`) |
|---|---|---|---|
| **Distribusi — Ekspor (`monthly_trade_summaries`)** | `0` baris (`has_data = false`) | `0` baris (`has_data = false`, grafik kosong) | **`30.147` baris (42 bln: `2023-01..2026-06`, US$ 69,09 M)** |
| **Distribusi — Impor (`monthly_trade_summaries`)** | `0` baris (`has_data = false`) | **`768` baris (`2026-08` Batch `#70`, US$ 167,35 Jt)** | **`768` baris (`2026-08` Batch `#70`, US$ 167,35 Jt)** |
| **Distribusi — Tier-2 BRS (`brs_indicator_summaries`)** | `0` baris | **~38 baris (`2026-08` Impor Batch `#70`)** | **~38 baris (`2026-08` Impor Batch `#70`)** |
| **Neraca — Ekspor & Impor** | `0` baris *(seluruh 4 batch Neraca `PRIVATE`)* | `0` baris *(tepat sesuai RBAC)* | `0` baris *(tepat sesuai RBAC)* |
| **Single Dashboard `/dashboard/data` & Ekspor Batch `#70`** | `0` transaksi *(bug!)* | **`785` transaksi Impor Batch `#70` tampil & bisa diekspor** | **`785` transaksi Impor Batch `#70` tampil & bisa diekspor** *(⚠️ **Asimetri:** Untuk Ekspor 2023–2026/06, `/dashboard/data` membaca `distribusi_transactions` = `$0` sementara 4 kartu pertumbuhan membaca `monthly_trade_summaries` -> wajib diselaraskan di Phase 2G.3)* |
| **Pertumbuhan Ekspor Distribusi (MoM, QoQ, YoY, C-to-C)** | Semua `N/A` | Semua `N/A` | **Aktif akurat s.d. Juni 2026** *(MoM Jun vs Mei 2026, QoQ Tw-II vs Tw-I 2026 penuh 3 bln, YoY Jun 2026 vs 2025, C-to-C Jan–Jun 2026 vs 2025)* |
| **Pertumbuhan Impor Distribusi (`2026-08`)** | Semua `N/A` | Semua `N/A` *(hanya 1 bulan `RILIS`, bukan `0.0%`)* | Semua `N/A` *(hanya 1 bulan `RILIS`, bukan `0.0%`)* |
| **Neraca Perdagangan (Trade Balance) Distribusi** | Tidak tersedia | Pasangan `2026-08` tidak lengkap (Impor ada, Ekspor `RILIS` kosong) -> `null / tidak lengkap` | Ekspor `2023–2026/06` tanpa Impor historis; Impor `2026-08` tanpa Ekspor `2026-08` -> hanya valid jika kedua sisi pada periode identik sama-sama `RILIS` |
| **Origin Mart (`monthly_origin_trade_summaries`)** | **BOCOR** (`O1 #85 PRIVATE`, `O3 #71 PRIVATE`, & `O2 #40 orphan`) | **Kebocoran `PRIVATE` (`O1/O3`) TERTUTUP** *(⚠️ namun **58 baris orphan `O2` (`2026-08`) eks Batch `#40` masih terekspos publik** di `/distribusi/metrics` sampai diputuskan)* | Sama dengan Skenario 1 |

### 6.2 Dampak untuk Role **Super Admin (Role 1) & Viewer Eksekutif (Role 3 — `RILIS` + `PRIVATE`)**

| Metrik (Role 1 & Role 3) | Sebelum 2G.2B | Sesudah 2G.2B (Skenario 1 maupun 2) |
|---|---|---|
| Periode Distribusi Ekspor | `45` bln (`2023-01..2026-06` + `07,08*,09`) | Tetap `45` bln *(⚠️ `2026-08*` = orphan `B2` eks Batch `#40` masih terpakai untuk MoM/QoQ/C-to-C internal `#71` sementara `distribusi_transactions` `2026-08` = `$0`)* |
| Periode Distribusi Impor | `3` bln (`2026-07, 08, 09`) | Tetap `3` bln, dengan **Batch `#70` (`2026-08`) kini lengkap di `distribusi_transactions`, Tier-1 (`batch_id=70, RILIS`), & Tier-2 (`RILIS`)** |
| Periode Neraca *(terpisah mutlak dari Distribusi)* | `2` bln Ekspor (`08,09`), `2` bln Impor (`07,08`) | Tetap sama |
| Akurasi Rekonsiliasi Raw vs Tier-1 vs Tier-2 | Drift Distribusi **`-$5.832400`** (4 batch) + Neraca **`-$6.953900`** (4 batch) + Batch `#70` kosong di `dist_tx` | Drift **`0.000000`** pada seluruh 5 batch Distribusi & 4 batch Neraca |

---

## 7. Rencana Rollback & Protokol Eksekusi Aman (Jika Phase 2G.2B Disetujui Nantinya)

Seluruh eksekusi Phase 2G.2B (hanya setelah persetujuan tertulis) wajib mengikuti protokol berikut pada **`sidatex_testing` SAJA**:

1. **Pre-Flight Live PDO & Operational Baseline Check:**
   - Abort otomatis jika `SELECT DATABASE() !== 'sidatex_testing'`.
   - Rekam baseline read-only database operasional `sidatex` **SEBELUM dan SESUDAH** eksekusi (`data_batches=3`, `distribusi_transactions=2557`, `monthly_trade_summaries=34847`, `migrations=26`, `MAX(updated_at)=2026-11-13 07:38:33`) untuk bukti mutlak `0` mutasi operasional.
2. **Pre-Execution Snapshot Backup Penuh (`mysqldump`):**
   - Eksekusi: `mysqldump --single-transaction --routines=0 --triggers=0 --set-gtid-purged=OFF -u root sidatex_testing > storage/backups/sidatex_testing_pre_2g2b_<timestamp>.sql` (mencakup seluruh tabel termasuk `trash` & `activity_logs` agar FK-safe saat restore), verifikasi bahwa `storage/backups/` berada di `.gitignore`, simpan checksum SHA-256 dan hitungan baris baseline.
3. **Eksekusi Atomik Berurutan Kronologis dengan Assertion Pra-Commit:**
   - **Khusus Batch `#70` (Opsi B1-a):** Di dalam `DB::transaction()`, sebelum commit wajib lolos assertion:
     - `distribusi_transactions` (`batch_id = 70`) sebelum insert === `0` (idempotency guard);
     - Parse DBF `14_impor_atap_kdprov_0826.DBF` menghasilkan tepat **`785` baris valid, `0` error, `0` unmapped HS/negara/pelabuhan (`pelabuhan_id IS NOT NULL`)**, `bccomp(SUM(nilai), '167347655.077700', 6) === 0`, dan `bccomp(SUM(berat), '568581779.655200', 6) === 0`;
     - Verifikasi efek `provorig` di `monthly_origin_trade_summaries` (`jenis_transaksi = 'impor'`); tabel arsip `transactions` 100% read-only (tidak disentuh).
   - **Urutan kronologis global 9 batch:** `2026-07` (`#85, #86, #88`) -> `2026-08` (**`#70`**, `#87, #82`) -> `2026-09` (`#71, #74, #81`), masing-masing memverifikasi rekonsiliasi `0.000000` (Raw vs Tier-1 vs Tier-2) serta mencatat jejak pada `activity_logs`.
   - Jika satu assertion gagal, `DB::rollBack()` mengembalikan state ke titik sebelum transaksi.
4. **Prosedur Rollback Penuh Pasca-Commit (Jika Diperlukan):**
   - Verifikasi `SELECT DATABASE() === 'sidatex_testing'`.
   - Restore penuh snapshot `storage/backups/sidatex_testing_pre_2g2b_<timestamp>.sql` ke `sidatex_testing`.
   - Verifikasi kembali seluruh hitungan baseline (`39.110 / 189 / 338 / 3.575 / 4.065`), `30.147` historis `batch_id IS NULL & key = 0`, Batch `#70 = RILIS`, dan operasional `sidatex` tetap `0` mutasi.

---

## 8. Hasil Review Ketiga Reviewer Independen

| Agen Reviewer | Verdict | Ringkasan Hasil Pemeriksaan & Catatan yang Telah Diintegrasikan |
|---|---|---|
| **`database-guardian`** | **`PASS WITH MINOR NOTES`** | Memverifikasi ketepatan partisi nol *double-counting* (`30.147 + 1.459 + 7.504 = 39.110`), keamanan unique key (`uq_mts_8_dimensi`, `unique_origin_summary_fixed`, `uq_brs_indicator_scope`), perlindungan struktural 30.147 baris historis, dan mendukung Opsi **B1-a**. 4 catatan penguatan telah dimasukkan: urutan eksekusi `#70` sebelum `#74`, idempotency & completeness guard B1-a (`785` baris, `0` null pelabuhan), dokumentasi interaksi residu `B2/O2`, serta `mysqldump --single-transaction` penuh + verifikasi sebelum/sesudah operasional `sidatex`. |
| **`statistics-auditor`** | **`PASS WITH MINOR NOTES`** | Memverifikasi pemisahan metodologi Distribusi vs Neraca, akurasi simulasi pertumbuhan & RBAC, dan mendukung Opsi **B1-a** (headline BRS identik US$ 167,35 Jt & 568,58 Rb Ton). 4 klarifikasi metodologis telah dimasukkan: pemisahan pelaporan drift Distribusi (`-$5.832400`) vs Neraca (`-$6.953900`), pencatatan asimetri `/dashboard/data` vs `StatisticsCalculationService` di bawah Opsi A-2, pencatatan keterbukaan publik `O2` (`58` baris) & pengaruh `B2` pada `#71` (Role 1/3), serta selisih `37.282` vs `53.066` tx dan default `is_migas = 0` pada 30.147 baris historis. |
| **`independent-reviewer`** | **`APPROVED WITH MINOR NOTES`** | Memverifikasi kelengkapan seluruh 9 bagian wajib, konsistensi aritmetika internal 100% eksak, nol tebakan `batch_id`, nol promosi otomatis `PRIVATE -> RILIS`, dan kesesuaian terhadap kode sumber (`DashboardController.php:62-65`). 5 catatan penyempurnaan (urutan `#70` sebelum `#74`, pemisahan drift per metodologi, dampak `B2` terhadap `#71`, assertion pra-commit B1-a + cek `provorig`, dan implikasi Opsi A-1 pada test `2024 Ekspor` di Phase 2G.3) telah diintegrasikan penuh. *(Catatan: verifikasi angka live SQL/DBF dilakukan oleh Orchestrator; reviewer memverifikasi konsistensi internal dokumen & keselarasan kode secara statis read-only.)* |

---

## 9. Daftar Risiko yang Belum Terselesaikan (Menunggu Keputusan Pengguna)

1. **Keputusan Kebijakan 30.147 Baris Historis (`2023-01..2026-06`) — Opsi A-1 vs A-2:**
   - Memerlukan keputusan eksplisit Anda. Catatan forensik penting sebelum memilih **Opsi A-2**: jumlah transaksi teragregasi `37.282` berbeda dari `53.066` tx pada Batch `#5` yang dihapus (selisih `15.784` tx — kemungkinan periode di luar `2023–2026/06` atau deduplikasi), sehingga total tahunan (`2023: $18,96M`, `2024: $18,18M`, `2025: $21,42M`, `2026 H1: $10,53M`) disarankan diverifikasi terhadap publikasi resmi BPS Riau. Sebaliknya, jika memilih **Opsi A-1** (tetap `PRIVATE`), seluruh tahun `2023–2026 H1` akan bernilai `0` untuk query `RILIS` pasca-2G.3 (termasuk implikasi pada `ComprehensiveAuditAndSecurityTest::test_dashboard_metrics_consistent_with_raw_data` untuk `2024 Ekspor`).
2. **Pemilihan Sumber Raw Batch `#70` — Opsi B1-a (Direkomendasikan) vs B1-b:**
   - Terdapat selisih pembulatan historis `US$ 0,0723` (`$167.347.655,077700` pada DBF asli vs `$167.347.655,15` pada arsip 2-desimal), dengan headline BRS identik **US$ 167,35 Juta**. Ketiga reviewer merekomendasikan **Opsi B1-a**.
3. **Metadata Belum Lengkap pada 30.147 Baris Historis (`sektor = NULL`, `bec_category = NULL`, `is_migas = 0`):**
   - Seluruh 30.147 baris memiliki `hs_id` & `hs2` valid (`null_hs = 0`), namun kolom `sektor = NULL`, `bec_category = NULL`, dan **`is_migas = 0` (default migrasi)** sehingga breakdown Migas/Non-Migas langsung dari `monthly_trade_summaries` untuk `2023–2026/06` akan keliru membaca Bab 27 sebagai Non-Migas kecuali disetujui pengayaan metadata dari `master_hs`.
4. **Residu Orphan Batch `#40` (`B2`: 691 baris Tier-1 `PRIVATE` & `O2`: 58 baris Origin `2026-08` Ekspor Distribusi):**
   - Batch `#40` sudah dihapus permanen pada 12 Nov, namun DBF asli (`14_Atap_ekspor_2608_POD_based.DBF`) masih ada di `referensi_bps/ekspor_distribusi/`. Membiarkan `B2` & `O2` menyebabkan: (a) `O2` (`58` baris) tetap terekspos publik di `/distribusi/metrics` (`US$ 2.218,16 Jt` padahal Ekspor `2026-08` = `$0`), dan (b) `B2` tetap menjadi basis MoM/QoQ/C-to-C internal `#71` (`2026-09`) bagi Role 1 & Role 3. Butuh keputusan Anda: **re-ingestion resmi DBF `2026-08` Ekspor**, **pembersihan terkendali `B2` + `O2`**, atau **tetap ditahan dulu**.
5. **Asimetri Dashboard & Ketergantungan pada Phase 2G.3 (`StatisticsCalculationService` & `DashboardController`):**
   - Di bawah Opsi A-2, `/dashboard/data` membaca `distribusi_transactions` (`0` baris untuk Ekspor `2023–2026/06`) sementara 4 kartu pertumbuhan membaca `monthly_trade_summaries`, sehingga penyelarasan fallback historis wajib ditangani di Phase 2G.3.
   - `DashboardController::index()` (`L62`) `$historicalYears` belum memfilter `status_visibilitas`, dan `DistributionStatisticsService::getDistributionTradeBalance()` (`L178–203`) belum memiliki guard pasangan periode tidak lengkap (masuk cakupan Phase 2G.3).
6. **Isolasi Database Operasional (`sidatex`):**
   - Seluruh rencana ini **hanya berlaku untuk `sidatex_testing`**. Database operasional `sidatex` (`data_batches = 3`, `migrations = 26`, tanpa `brs_indicator_summaries`) tetap 100% tidak tersentuh hingga fase & persetujuan produksi terpisah.
