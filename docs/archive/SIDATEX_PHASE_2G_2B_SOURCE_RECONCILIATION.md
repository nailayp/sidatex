# SIDATEX — PHASE 2G.2B: SOURCE RECONCILIATION & ENRICHMENT SIMULATION REPORT (READ-ONLY)

**Tanggal Audit:** 18 November 2026  
**Mode Eksekusi:** **100% READ-ONLY** *(0 INSERT, 0 UPDATE, 0 DELETE, 0 Migration, 0 Backfill, 0 Modifikasi DBF)*  
**Target Database:** `sidatex_testing` *(terverifikasi live PDO `SELECT DATABASE() === 'sidatex_testing'`)*  
**Status:** **LAPORAN FINAL — MENUNGGU PERSETUJUAN EKSEKUSI PENGGUNA**  
**Verdict Ketiga Reviewer Independen:**
- `database-guardian`: **`PASS`** *(3 catatan higienitas eksekusi non-blocking telah dimasukkan)*
- `statistics-auditor`: **`PASS`** *(2 catatan operasional minor telah dimasukkan)*
- `independent-reviewer`: **`APPROVED WITH MINOR NOTES`** *(3 catatan penyempurnaan kecil telah dimasukkan)*

> [!CAUTION]
> **Kepatuhan Keamanan Mutlak:**
> - Seluruh query hanya berjalan read-only pada `sidatex_testing`; database operasional `sidatex` tidak disentuh.
> - Berkas DBF referensi di `referensi_bps/` hanya dibaca secara streaming read-only (SHA-256 tercatat sebagai bukti keutuhan).
> - Belum ada mutasi pada `data_batches`, `distribusi_transactions`, `neraca_transactions`, `monthly_trade_summaries`, `monthly_origin_trade_summaries`, maupun `brs_indicator_summaries`.

---

## 1. Rekonsiliasi Batch `#70` (`Impor Distribusi 2026-08`, `RILIS`) — 785 Baris DBF vs 768 Baris Tier-1

### 1.1 Identitas Sumber & Keutuhan Berkas DBF Asli (Opsi B1-a)
- **Berkas DBF:** [14_impor_atap_kdprov_0826.DBF](file:///d:/laragon/www/sidatex/referensi_bps/impor_distribusi/14_impor_atap_kdprov_0826.DBF)
- **Ukuran Berkas:** `155.095` bytes
- **SHA-256:** `b27f2b8ac114b9a709150ac5b55fb41fb479730f88f02c15a9c1f9f4485065a5`
- **Hasil Parsing Read-Only (`ImportDistributionDbfParser`):**
  - Total baris mentah DBF: **`785` baris** (`0` unmapped HS, `0` unmapped Negara, `0` unmapped Pelabuhan, `0` baris memiliki `provorig`).
  - Total Nilai CIF (6 desimal): **`US$ 167.347.655,077700`** *(headline BRS: **US$ 167,35 juta**)*.
  - Total Berat Netto (6 desimal): **`568.581.779,655200 Kg`** *(headline BRS: **568,58 ribu ton**)*.
  - Rincian Migas vs Non-Migas: **Migas = `US$ 8.872.998,790000`** (`5,30%`) | **Non-Migas = `US$ 158.474.656,287700`** (`94,70%`) | **Unmapped = `0`** *(jumlah = `167.347.655,077700`, delta `0.000000`)*.
  - Rincian BEC Impor: Bahan Baku/Penolong `US$ 155.608.524,013900` (`92,99%`), Barang Modal `US$ 11.000.942,920900` (`6,57%`), Barang Konsumsi `US$ 738.188,142900` (`0,44%`) *(jumlah = `167.347.655,077700`, delta `0.000000`)*.

### 1.2 Pembuktian Matematis Selisih 17 Baris (`785` DBF vs `768` Tier-1 Mart)
Selisih `785 - 768 = 17` baris **BUKAN kehilangan data dan BUKAN baris hilang**, melainkan **penggabungan normal (agregasi) pada 17 kombinasi unique key 8-dimensi `uq_mts_8_dimensi`** `(tipe_sumber='distribusi', hs_id, negara_id, provinsi_id=4, pelabuhan_key, jenis_transaksi='Impor', bulan=8, tahun=2026)`:
- **`751` cube key** masing-masing memiliki **`1` transaksi** (`751 × 1 = 751` baris DBF).
- **`17` cube key** masing-masing memiliki **`2` transaksi** (`17 × 2 = 34` baris DBF).
- **Total:** `751 + 17 = 768` baris Tier-1 mart, mencakup `751 + 34 = 785` transaksi (`SUM(jumlah_transaksi) = 785`).

**Daftar Lengkap 17 Multi-Transaction Cube Keys (`tx = 2`) pada DBF Batch `#70`:**

| # | `hs_id\|negara_id\|prov\|pel_key` | Kode HS | Tx | Nilai CIF DBF (6 Desimal, USD) | Berat Netto DBF (6 Desimal, Kg) |
|---:|---|---|---:|---:|---:|
| 1 | `2449\|11\|4\|38` | `29051100` | 2 | `5.554.980,000000` | `12.028.190,000000` |
| 2 | `2449\|62\|4\|38` | `29051100` | 2 | `4.821.347,960000` | `12.483.367,000000` |
| 3 | `3823\|11\|4\|38` | `39239090` | 2 | `118.390,603500` | `47.468,600000` |
| 4 | `3367\|7\|4\|34` | `38099200` | 2 | `540.017,010000` | `417.580,000000` |
| 5 | `7895\|9\|4\|34` | `84213990` | 2 | `23.050,628900` | `178,900000` |
| 6 | `11007\|7\|4\|34` | `90268000` | 2 | `16.132,017600` | `173,189700` |
| 7 | `7679\|7\|4\|16` | `84137099` | 2 | `15.430,800000` | `1.340,000000` |
| 8 | `7689\|7\|4\|16` | `84139140` | 2 | `43.484,220000` | `5.947,100000` |
| 9 | `8671\|7\|4\|16` | `84842000` | 2 | `5.143,600000` | `16,000000` |
| 10 | `3436\|7\|4\|35` | `38249999` | 2 | `274.948,800000` | `1.317.482,500000` |
| 11 | `4073\|7\|4\|35` | `40169999` | 2 | `292,696600` | `20,742200` |
| 12 | `7155\|7\|4\|35` | `76101010` | 2 | `3.147,857200` | `165,831900` |
| 13 | `7159\|7\|4\|35` | `76109099` | 2 | `6.138,959900` | `1.268,482700` |
| 14 | `7328\|7\|4\|35` | `82075000` | 2 | `5.061,812300` | `166,200000` |
| 15 | `7373\|7\|4\|35` | `83021000` | 2 | `1.436,010800` | `149,096700` |
| 16 | `7888\|7\|4\|35` | `84212990` | 2 | `80.829,501600` | `3.777,831700` |
| 17 | `8144\|7\|4\|35` | `84399900` | 2 | `79.900,000000` | `15.014,000000` |

### 1.3 Rekonsiliasi Cube-by-Cube (768 Cube DBF vs 768 Baris Tier-1 Unlinked)

| Metrik Rekonsiliasi | Hasil Aktual | Keterangan |
|---|---:|---|
| Baris hanya di DBF (`onlyInDbf`) | **`0`** | Tidak ada 1 pun cube key DBF yang hilang di mart |
| Baris hanya di Mart (`onlyInMart`) | **`0`** | Tidak ada 1 pun cube key mart di luar DBF |
| Ketidakcocokan jumlah transaksi (`txMismatch`) | **`0`** | Seluruh `768/768` cube key memiliki `jumlah_transaksi` identik (`SUM = 785`) |
| Ketidakcocokan material (`> $0.01` atau `> 0.001 Kg`) | **`0`** | Nol anomali substansial |
| Cocok identik hingga 6 desimal (`exact6d`) | **`339` cube** | Selisih `0.000000` USD & `0.000000` Kg |
| Cocok dengan pembulatan legacy 2-desimal (`<= $0.0072` / `<= 0.0005 Kg`) | **`429` cube** | Berasal murni dari tabel arsip `transactions` lama (`DECIMAL(18,2)` / `DECIMAL(18,3)`) |
| Selisih total bersih (`Mart - DBF`) | **`+$0,072300` USD / `+0,006800` Kg** | Maksimum selisih per cube: `$0,007200` USD & `0,000500` Kg |

**Contoh Sampel Pembulatan Legacy pada 429 Cube:**
- `3823|11|4|38` (`HS 39239090`, `tx=2`): DBF `118390.603500` vs Mart `118390.600000` (`-0.003500`)
- `3555|7|4|34` (`HS 39069020`, `tx=1`): DBF `695948.391600` vs Mart `695948.390000` (`-0.001600`)
- `3967|7|4|34` (`HS 40103900`, `tx=1`): DBF `228960.466400` vs Mart `228960.470000` (`+0.003600`)

### 1.4 Verifikasi Keunikan Key, Metodologi, Status `RILIS`, & Simulasi Sebelum vs Sesudah (Batch `#70`)
- **Unique Key Safety:** Seluruh `768` baris unlinked B1 memiliki `pelabuhan_key > 0` (`pelabuhan_id IS NOT NULL`), sehingga `BrsAggregationService::aggregateBatch(70, 'RILIS')` menghapus `WHERE (batch_id IS NOT NULL OR pelabuhan_key > 0)` pada `(distribusi, Impor, 2026, 8)` lalu menyisipkan tepat `768` baris baru dengan `batch_id = 70` — **0 risiko duplicate entry pada `uq_mts_8_dimensi`** dan **0 bentrokan pada `uq_brs_indicator_scope`**.
- **Metodologi & Origin Mart:** Tetap 100% `tipe_sumber = 'distribusi'`, `jenis_data = 'Impor'`, `status = 'RILIS'`. Karena `provorig = 0` baris pada DBF Impor (`ImportDistributionDbfParser.php:134-150` menetapkan `'provorig' => null`), re-ingestion menulis **`0` baris** pada `monthly_origin_trade_summaries` (tidak menyentuh `O2` Ekspor).

| Objek / Metrik Batch `#70` | SEBELUM (Kondisi Saat Ini) | SESUDAH SIMULASI (Opsi B1-a) |
|---|---|---|
| `data_batches` (`batch_id = 70`) | `distribusi \| Impor \| 2026-08 \| RILIS` | **Tetap identik (`RILIS`, `released_by`/`released_at`/`nama_file` utuh)** |
| `distribusi_transactions` (`batch_id = 70`) | **`0` baris** (`$0,000000`) | **`785` baris** (`$167.347.655,077700` / `568.581.779,655200 Kg`) |
| `monthly_trade_summaries` (`batch_id IS NULL, 2026-08 Impor`) | `768` baris (`PRIVATE`) | **`0` baris** *(digantikan tepat oleh `batch_id = 70`)* |
| `monthly_trade_summaries` (`batch_id = 70`) | **`0` baris** | **`768` baris (`RILIS`, drift = `0.000000`)** |
| `brs_indicator_summaries` (`batch_id = 70`) | **`0` baris** | **~38 baris (`RILIS`, TOTAL = `$167.347.655,077700`, MoM/YoY/C-to-C = `NULL`)** |
| `monthly_origin_trade_summaries` (`2026-08 impor`) | `0` baris | **`0` baris** *(0 baris ber-`provorig`)* |
| Ekspor Excel Batch `#70` (`FastExcelExporter`) | `0 transaksi` *(bug)* | **`785 transaksi` (header `CIF (US$)`, presisi 6 desimal)** |

---

## 2. Analisis 30.147 Baris Historis (`Opsi A-1`) & Simulasi Enrichment `master_hs`

### 2.1 Kondisi Awal & Kepatuhan Keputusan A-1
- Jumlah baris: **`30.147`** (`batch_id IS NULL AND pelabuhan_key = 0`, `distribusi | Ekspor`, `2023-01` s.d. `2026-06` = 42 bulan).
- Kepatuhan mutlak Opsi **A-1**:
  - `status_visibilitas`: **`30.147` tetap `PRIVATE`** (`0` dipromosikan ke `RILIS`).
  - `batch_id`: **`30.147` tetap `NULL`** (`0` tebakan `batch_id`).
  - Penghapusan baris: **`0` baris dihapus** (`30.147` tetap `30.147`).
  - Total Nilai & Berat: **`US$ 69.091.666.591,570000`** & **`95.954.792.613,265000 Kg`** (delta **`0.000000`**).

### 2.2 Simulasi Enrichment Read-Only Menggunakan `master_hs` (Sesuai Rumus `BrsAggregationService.php:159-163`)
Hasil join read-only `monthly_trade_summaries.hs_id = master_hs.hs_id`:
- Baris dengan pemetaan `master_hs` valid: **`30.147` baris (`100,00%`)**
- Baris tanpa pemetaan `master_hs` (`hs_id IS NULL` / tidak ditemukan): **`0` baris (`0,00%`)** *(tetap dijaga aturan: kode tak ditemukan tetap `NULL`/`UNMAPPED`)*
- Baris Non-Migas dengan `master_hs.sektor` kosong: **`0` baris**

#### Tabel Simulasi Sebelum vs Sesudah Enrichment (`is_migas`, `sektor`, dan Opsional Paritas `bec_category`)

| Kolom / Kategori | SEBELUM (Baris) | SEBELUM (USD) | SESUDAH SIMULASI (Baris) | SESUDAH SIMULASI (USD) | Pangsa (%) |
|---|---:|---:|---:|---:|---:|
| **`status_visibilitas = PRIVATE`** | `30.147` | `69.091.666.591,570000` | **`30.147` *(tidak berubah)*** | **`69.091.666.591,570000`** | `100,00%` |
| **`batch_id IS NULL`** | `30.147` | `69.091.666.591,570000` | **`30.147` *(tidak berubah)*** | **`69.091.666.591,570000`** | `100,00%` |
| **`is_migas = 1` (MIGAS)** | **`0`** *(salah default!)* | `0,000000` | **`273`** | **`5.042.852.784,430000`** | **`7,30%`** |
| **`is_migas = 0` (NON-MIGAS)** | `30.147` | `69.091.666.591,570000` | **`29.874`** | **`64.048.813.807,140000`** | **`92,70%`** |
| **`sektor = NULL`** | `30.147` | `69.091.666.591,570000` | **`0`** | `0,000000` | `0,00%` |
| **`sektor = INDUSTRI PENGOLAHAN`** | `0` | `0,000000` | **`28.072`** | **`62.863.524.998,360000`** | `90,99%` |
| **`sektor = MIGAS`** | `0` | `0,000000` | **`273`** | **`5.042.852.784,430000`** | `7,30%` |
| **`sektor = PERTANIAN`** | `0` | `0,000000` | **`1.795`** | **`1.158.635.974,030000`** | `1,68%` |
| **`sektor = PERTAMBANGAN`** | `0` | `0,000000` | **`7`** | **`26.652.834,750000`** | `0,04%` |
| *(Opsional Paritas Tier-1)* `bec_category` — Bahan Baku/Penolong | `0` (`NULL=30.147`) | — | `25.086` | `65.484.438.516,530000` | `94,78%` |
| *(Opsional Paritas Tier-1)* `bec_category` — Barang Konsumsi | `0` | — | `4.765` | `3.598.936.249,070000` | `5,21%` |
| *(Opsional Paritas Tier-1)* `bec_category` — Barang Modal | `0` | — | `296` | `8.291.825,970000` | `0,01%` |

*(Catatan: `bec_category` bersifat opsional sekadar paritas kolom Tier-1 karena 30.147 baris merupakan Ekspor, sedangkan klasifikasi BEC BRS khusus untuk Impor.)*

> [!IMPORTANT]
> **Nilai Tambah Enrichment (Tanpa Menyentuh Visibility):** Saat ini **US$ 5,04 Miliar (`7,30%`, 273 baris)** Ekspor Migas historis `2023–2026/06` keliru terbaca sebagai `is_migas = 0` (Non-Migas) dan seluruh 30.147 baris ber-`sektor = NULL`. Enrichment dari `master_hs` memperbaiki klasifikasi sektoral & Migas secara 100% deterministik tanpa mengubah satu pun angka USD, berat, `batch_id`, atau `status_visibilitas = 'PRIVATE'`.

---

## 3. Rekonsiliasi Orphan Batch `#40` (`Ekspor Distribusi 2026-08`) — Temuan Kritis Korup Pelabuhan!

### 3.1 Identitas Berkas DBF Asli
- **Berkas DBF:** [14_Atap_ekspor_2608_POD_based.DBF](file:///d:/laragon/www/sidatex/referensi_bps/ekspor_distribusi/14_Atap_ekspor_2608_POD_based.DBF)
- **Ukuran Berkas:** `71.642` bytes
- **SHA-256:** `f527ff527949756193e3170fc37725f47d33ff7cfc4dc45393acd2603d132ed5`
- **Hasil Parsing Read-Only (`ExportDistributionDbfParser`):**
  - Total baris mentah DBF: **`809` baris** (`0` unmapped HS, `0` unmapped Negara, `0` unmapped Pelabuhan).
  - Total Nilai FOB (6 desimal): **`US$ 2.218.155.995,502700`** | Total Berat Netto: **`2.352.083.988,620000 Kg`**.
  - Dengan pemetaan pelabuhan aktual (16 pelabuhan Riau): menghasilkan **`797` cube key Tier-1** (`12` baris collapse multi-tx) dan **`58` key Origin Mart**.

### 3.2 Rekonsiliasi DBF (809 Baris) vs `691` Baris Tier-1 `PRIVATE` (`batch_id IS NULL`)

> [!WARNING]
> **TEMUAN FORENSIK KRITIS — SELURUH 691 BARIS TIER-1 BATCH `#40` TERKORUPSI DI DIMENSI PELABUHAN!**
> Ketika Batch `#40` diunggah pada `2026-11-11 08:17:37` (sebelum perbaikan pemetaan pelabuhan pada parser baru), **seluruh 16 pelabuhan muat Riau di DBF disatukan secara keliru ke 1 pelabuhan fallback `pelabuhan_id = 38 (Dumai)`**!

| Pengujian Perbandingan | DBF Asli (`809` baris) | Tier-1 Mart (`691` baris) | Selisih / Hasil |
|---|---:|---:|---|
| Jumlah Pelabuhan Unik | **`16` pelabuhan Riau** | **`1` pelabuhan (`38: Dumai` saja!)** | **KORUP DIMENSI PELABUHAN** *(Buatan 232 tx `$111,04 Jt`, Perawang 213 tx `$109,94 Jt`, Futong `$122,41 Jt`, Sg. Guntung 20 tx `$23,18 Jt`, Sg. Pakning `$28,10 Jt`, dll. seluruhnya dipaksa menjadi Dumai!)* |
| Jumlah Unique Key 8-Dimensi (termasuk `pelabuhan_key`) | **`797` cube** | **`691` cube** | `271` shared Dumai cubes; `onlyInDbf = 526`, `onlyInMart = 420` *(murni akibat seluruh non-Dumai digeser ke `pelabuhan_key = 38`)* |
| Jumlah Unique Key `(hs_id, negara_id)` *(mengabaikan pelabuhan)* | **`691` key** | **`691` key** | **`onlyInDbf = 0`, `onlyInMart = 0`, `exact6d = 595`, `rounded2d = 96`, `bigMismatch = 0` (100,00% MATCH!)** |
| Total Transaksi (`SUM(jumlah_transaksi)`) | **`809`** | **`809`** | **`0`** |
| Total Berat Netto (Kg) | **`2.352.083.988,620000`** | **`2.352.083.988,620000`** | **`0,000000`** |
| Total Nilai FOB (USD) | **`2.218.155.995,502700`** | **`2.218.155.995,460000`** | **`-$0,042700`** *(pembulatan 2-desimal legacy)* |

**Distribusi 16 Pelabuhan Aktual DBF vs 1 Pelabuhan Terkorupsi di Mart (Ringkasan Top 6):**
- `38: Dumai`: DBF `283` tx (`$1.810,67 Jt`) vs Mart **`809` tx (`$2.218,16 Jt`)** *(overstated `+$407,49 Jt` / `+526` tx!)*
- `34: Buatan`: DBF `232` tx (`$111,04 Jt`) vs Mart **`0`**
- `35: Perawang, Sumatra`: DBF `213` tx (`$109,94 Jt`) vs Mart **`0`**
- `39: Futong Terminal`: DBF `10` tx (`$122,41 Jt`) vs Mart **`0`**
- `8: Sungai Pakning`: DBF `4` tx (`$28,10 Jt`) vs Mart **`0`**
- `16: Sungai Guntung` + 10 pelabuhan lain (`Panipahan`, `Bengkalis`, `Tj. Medang`, `Kuala Gaung`, `Selat Panjang`, `Tembilahan`, `Kuala Enok`, `Siak Sri Indrapura`, `Rengat`, `Pekan Baru/Simpang Tiga (U)`): DBF `67` tx (`$36,00 Jt`) vs Mart **`0`**

### 3.3 Rekonsiliasi DBF (809 Baris) vs `58` Baris `monthly_origin_trade_summaries`

| Metrik Rekonsiliasi | DBF Asli (`809` baris) | Origin Mart (`58` baris) | Hasil |
|---|---:|---:|---|
| Jumlah Key Unik `(provorig, hs2, sektor, is_migas)` | **`58`** | **`58`** | **`onlyInDbf = 0`, `onlyInOrig = 0` (100,00% MATCH!)** |
| Total Transaksi / Berat (Kg) | `809` / `2.352.083.988,620000` | `809` / `2.352.083.988,620000` | **`0` / `0,000000`** |
| Total Nilai FOB (USD) | `2.218.155.995,502700` | `2.218.155.995,460000` | **`-$0,042700`** *(pembulatan 2-desimal legacy)* |
| Rincian Kecocokan per Key | — | `32` exact 6-desimal, `25` selisih `<= $0.01`, `1` selisih `-$0,0230` (`key 1405\|48\|INDUSTRI PENGOLAHAN\|0` mencakup `206` tx, rata-rata `$0,00011/tx`) | **0 mismatch struktural** |

### 3.4 Hubungan Eksak antara `691` Baris Tier-1 dan `58` Baris Origin Mart
- **Cross-Check Total (`691` Tier-1 vs `58` Origin Mart):** `ValDiff = 0.000000`, `WgtDiff = 0.000000`, `TxDiff = 0`, timestamp pembuatan identik **`2026-11-11 08:17:38`**.
- **Kesimpulan Forensik Tegas:**
  1. **SUMBER TUNGGAL IDENTIK:** Kedua kelompok (`691` Tier-1 & `58` Origin) **100% terbukti berasal dari satu sumber yang sama**, yaitu bekas **Batch `#40`** (`14_Atap_ekspor_2608_POD_based.DBF`, 809 transaksi) yang diunggah `2026-11-11 08:17:37` lalu dihapus permanen `2026-11-12 03:12:52` (log #73).
  2. **BUKAN DUPLIKAT:** Keduanya adalah **dua proyeksi dimensi berbeda** di dua tabel berbeda (Tier-1 = HS × Negara × Pelabuhan; Origin Mart = Provorig × HS2 × Sektor × Migas) dan tidak pernah dijumlahkan bersama. Juga terpisah tegas dari Batch `#87` (`neraca | Ekspor | 2026-08`, `925` tx, `$2.226,73 Jt`, metodologi PROVORIG Riau).
  3. **KONSEKUENSI KEPUTUSAN:** Sesuai instruksi Anda, **keduanya dipertahankan tanpa perubahan pada tahap ini**. Namun temuan korupsi pelabuhan pada 691 baris Tier-1 membuktikan secara mutlak bahwa **691 baris ini TIDAK BOLEH pernah dipromosikan ke `RILIS` apa adanya**; pada tahap eksekusi mendatang hanya ada 2 pilihan bersih: (a) **re-ingestion resmi** dari `14_Atap_ekspor_2608_POD_based.DBF` (menghasilkan `797` baris Tier-1 akurat di 16 pelabuhan + `58` baris Origin 6-desimal, di mana `BrsAggregationService::aggregateBatch()` otomatis membersihkan 691 baris Dumai korup berkat `pelabuhan_key > 0` tanpa bentrok `uq_mts_8_dimensi`), atau (b) **pembersihan terkendali `691` + `58` baris orphan**. Kelak setelah salah satu dipilih, jalankan refresh `aggregateBatch(71, ...)` pada Batch `#71` (`2026-09`) agar MoM & C-to-C `#71` tersinkronisasi.

---

## 4. Dampak Gabungan terhadap Mart & Dashboard (Berdasarkan Keputusan Sementara A, B, C)

| Komponen / Pengguna | Kondisi Sebelum Eksekusi | Proyeksi Sesudah Eksekusi (B1-a + 8 Batch Linked Re-agg + A-1 + Enrichment `master_hs` + C Ditahan) |
|---|---|---|
| **Pegawai (Role 2 — `RILIS` saja)** | `0` baris di semua mart; ekspor Batch `#70` = `0` tx; `O1`/`O3` bocor `PRIVATE` di origin mart | Melihat **Batch `#70` (`2026-08` Impor Distribusi, `785` tx, `768` Tier-1 `RILIS`, ~38 Tier-2 `RILIS`, US$ 167,35 Jt)** secara konsisten di Dashboard, Filtering, & Ekspor Excel. Data historis `30.147` tetap `PRIVATE`. Kebocoran `PRIVATE` (`O1` `2026-07` & `O3` `2026-09`) **tertutup** *(catatan: `58` baris `O2` `2026-08` tetap terbuka di `/distribusi/metrics` selama Bagian C ditahan)*. |
| **Super Admin & Viewer Eksekutif (Role 1 & 3)** | Drift presisi Distribusi `-$5.832400` (4 batch) & Neraca `-$6.953900` (4 batch); `30.147` historis punya `is_migas = 0` & `sektor = NULL`; Batch `#70` kosong di `dist_tx` | Drift **`0.000000`** pada seluruh 9 batch (5 Distribusi + 4 Neraca); **US$ 5,04 Miliar** Migas historis (`273` baris) dan seluruh 4 sektor historis akurat; *(catatan: `691` baris `B2` tetap `PRIVATE` dengan distorsi pelabuhan Dumai dan masih masuk penyebut internal MoM `#71` selama Bagian C ditahan)*. |
| **Pemisahan Distribusi vs Neraca** | Terpisah | **100% terpisah**; tidak ada 1 pun operasi yang menggabungkan atau mencampur `distribusi_transactions` & `neraca_transactions`. |

---

## 5. Rekomendasi Rencana Eksekusi & Rollback (Jika Disetujui pada Tahap Berikutnya)

### 5.1 Urutan Eksekusi Terkendali (Hanya pada `sidatex_testing`, Setelah Persetujuan Tertulis)
1. **Pre-Flight Gate & Backup:** Verifikasi live PDO `SELECT DATABASE() === 'sidatex_testing'`, pastikan `storage/backups/` berada di `.gitignore`, catat snapshot read-only operasional `sidatex` SEBELUM eksekusi, dan buat backup penuh `mysqldump --single-transaction --routines=0 --triggers=0 --set-gtid-purged=OFF -u root sidatex_testing > storage/backups/sidatex_testing_pre_2g2b_<timestamp>.sql` + SHA-256.
2. **Langkah 1 — Enrichment Metadata 30.147 Baris Historis (`Opsi A-1`):** Dalam `DB::transaction()`, jalankan `UPDATE` menggunakan presedensi persis `BrsAggregationService.php:159-163` (`UPPER(TRIM(COALESCE(oilgrp_desc, ''))) = 'MIGAS' OR hs2 = '27'` -> `is_migas = 1, sektor = 'MIGAS'`) hanya pada predikat ketat `WHERE batch_id IS NULL AND pelabuhan_key = 0 AND tipe_sumber = 'distribusi' AND jenis_transaksi = 'Ekspor' AND hs_id IS NOT NULL` *(kolom `sektor` & `is_migas`, plus `bec_category` hanya bila disetujui)*. Assertion pra-commit: `affected_rows === 30147`, `status_visibilitas = 'PRIVATE'` (100%), `batch_id IS NULL` (100%), `SUM(total_nilai) === 69091666591.570000`, `SUM(total_berat) === 95954792613.265000`, `is_migas=1 === 273`.
3. **Langkah 2 — Re-Agregasi Kronologis Global 9 Batch (B1-a + 8 Batch Linked):**
   - `2026-07`: `aggregateBatch(85, 'PRIVATE')` *(membersihkan kebocoran `O1`)*, `aggregateBatch(86, 'PRIVATE')`, `aggregateBatch(88, 'PRIVATE')`.
   - `2026-08`: **Batch `#70` (Opsi B1-a)** dalam transaksi atomik — assert `distribusi_transactions(batch_id=70) === 0`, verifikasi `SHA-256` berkas `14_impor_atap_kdprov_0826.DBF`, insert `785` baris valid dengan paritas kolom identik `DbfIngestionService.php:201-217` / `BrsDataProcessingService.php:656-673` (`provinsi_id = 4`, `kabkota_id`, `provorig = null`, `jenis_transaksi = 'Impor'`, `tipe_sumber = 'distribusi'`, `tahun = 2026`, `bulan = 8`, 6-desimal `berat`/`nilai`; `0` unmapped, `SUM(nilai) = 167347655.077700`, `SUM(berat) = 568581779.655200`), jaga baris `data_batches #70` utuh tanpa membuat batch baru, lalu jalankan `aggregateBatch(70, 'RILIS')` -> assert `768` baris Tier-1 `RILIS`, Tier-2 `RILIS` drift `0.000000`, `data_batches.status === 'RILIS'`; lanjut `aggregateBatch(87, 'PRIVATE')`, `aggregateBatch(82, 'PRIVATE')`.
   - `2026-09`: `aggregateBatch(71, 'PRIVATE')` *(membersihkan kebocoran `O3`)*, `aggregateBatch(74, 'PRIVATE')` *(sekarang melihat `#70` 6-desimal)*, `aggregateBatch(81, 'PRIVATE')`.
4. **Langkah 3 — Batch `#40` (`691` Tier-1 & `58` Origin):** **TIDAK DISENTUH** pada eksekusi ini sesuai keputusan Bagian C.
5. **Post-Execution Audit:** Catat jejak ke `activity_logs`, verifikasi `DataReconciliationService::auditConsistency()` menghasilkan `is_consistent = true`, drift `0.000000` pada seluruh 9 batch ter-link, dan `historical_unlinked_rows = 30838` (`30.147` H1–H4 + `691` B2), jalankan full `php artisan test`, serta verifikasi ulang operasional `sidatex` 100% tidak tersentuh.

### 5.2 Rencana Rollback Deterministik
- **Pre-Commit Rollback:** Tiap langkah berada dalam `DB::transaction()` terpisah dengan assertion angka eksak; 1 pelanggaran langsung memicu `DB::rollBack()`.
- **Post-Commit Rollback:** Verifikasi `SELECT DATABASE() === 'sidatex_testing'`, restore penuh `storage/backups/sidatex_testing_pre_2g2b_<timestamp>.sql`, verifikasi hitungan baseline (`39.110 / 189 / 338 / 3.575 / 4.065`, `30.147` historis `sektor=NULL/is_migas=0`, Batch `#70` `RILIS`, operasional `sidatex` 0 mutasi).

---

## 6. Hasil Review Independen (`database-guardian`, `statistics-auditor`, `independent-reviewer`)

| Agen Reviewer | Verdict | Ringkasan Verifikasi & Catatan yang Telah Diintegrasikan |
|---|---|---|
| **`database-guardian`** | **`PASS`** | Memverifikasi ketepatan matematis `751×1 + 17×2 = 785 -> 768` (`0` risiko `uq_mts_8_dimensi` / `uq_brs_indicator_scope`, `0` provorig), keutuhan A-1 (`30.147` baris), dampak unique key korupsi pelabuhan Batch `#40` (`pelabuhan_key = 38 > 0` aman saat ditahan dan aman digantikan saat re-ingestion nanti), serta urutan eksekusi `#70` sebelum `#74`. 3 catatan higienitas eksekusi (`WHERE` eksplisit + assert `SUM(total_berat)`, paritas kolom `BrsDataProcessingService`, cek `.gitignore` & target `historical_unlinked_rows = 30838`) telah dimasukkan ke §5.1. |
| **`statistics-auditor`** | **`PASS`** | Memverifikasi seluruh rekonsiliasi 6-desimal (`Migas + Non-Migas = BEC = $167.347.655,077700`; 4 sektor historis = `30.147` baris / `$69.091.666.591,570000`), validitas `MoM/YoY/C-to-C = NULL` untuk `#70`, bukti sumber tunggal & overstatement Dumai `+$407,49 Jt` pada Batch `#40` (menegaskan tidak boleh dipromosikan ke `RILIS`), serta pemisahan Distribusi vs Neraca. 2 catatan operasional (presedensi `is_migas` persis `BrsAggregationService` & refresh `#71` kelak bila Batch `#40` diputuskan) telah dimasukkan ke §3.4 & §5.1. |
| **`independent-reviewer`** | **`APPROVED WITH MINOR NOTES`** | Memverifikasi kelengkapan seluruh requirement A–E, konsistensi aritmetika internal 100% eksak, serta keselarasan langsung terhadap `ImportDistributionDbfParser.php` dan `DbfIngestionService.php`. 3 penyesuaian kecil (koreksi sitasi `BrsAggregationService.php:159-163`, penambahan filter `tipe_sumber/jenis_transaksi` pada Langkah 1, dan paritas kolom `DbfIngestionService.php:201-217` termasuk `kabkota_id` sambil menjaga `data_batches #70` utuh) telah diintegrasikan penuh. *(Eksekusi live SQL/SHA-256 diverifikasi oleh Orchestrator; reviewer memverifikasi konsistensi internal & kode sumber secara statis read-only.)* |
