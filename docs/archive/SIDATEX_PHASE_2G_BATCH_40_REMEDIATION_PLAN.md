# SIDATEX — PHASE 2G: BATCH #40 REMEDIATION PLAN (READ-ONLY)

> **Status Dokumen:** FINAL — TELAH DI-REVIEW 3 REVIEWER INDEPENDEN (100% READ-ONLY — BELUM ADA EKSEKUSI MUTASI DATABASE)  
> **Tanggal Audit:** 18 November 2026  
> **Ruang Lingkup:** Batch `#40` — Ekspor Distribusi Agustus 2026 (`691` baris Tier-1 korup pada `monthly_trade_summaries`, `58` baris `monthly_origin_trade_summaries`, serta berkas DBF sumber asli `referensi_bps/ekspor_distribusi/14_Atap_ekspor_2608_POD_based.DBF`)  
> **Target Lingkungan:** `sidatex_testing` (terverifikasi melalui live PDO `SELECT DATABASE() = 'sidatex_testing'`)  
> **Proteksi Database Operasional (`sidatex`):** 100% TIDAK DISENTUH (`data_batches = 3`, `distribusi_transactions = 2557`, `neraca_transactions = 0`, `monthly_trade_summaries = 34847`, `monthly_origin_trade_summaries = 189`, `migrations = 26`, `brs_indicator_summaries = NOT FOUND`, `data_batches.MAX(updated_at) = 2026-11-13 07:38:33`, `monthly_trade_summaries.MAX(updated_at) = 2026-11-12 07:06:16`)

---

## Ringkasan Eksekutif

Audit forensik read-only terhadap jejak **Batch `#40` (Ekspor Distribusi Agustus 2026)** pada `sidatex_testing` mengonfirmasi bahwa seluruh **`691` baris Tier-1** (`monthly_trade_summaries`, `batch_id IS NULL`, `status_visibilitas = 'PRIVATE'`) mengalami **korupsi dimensi pelabuhan total** — 100% transaksi (`809` transaksi senilai `US$ 2,218,155,995.460000`) terkolaps ke satu pelabuhan (`pelabuhan_id = 38 / pelabuhan_key = 38`, **Dumai**), padahal berkas DBF sumber resmi BPS (`14_Atap_ekspor_2608_POD_based.DBF`) mencakup **16 pelabuhan muat Riau** yang valid dan terpetakan **100% pasti (`809/809` transaksi *certain*, `0` *ambiguous*, `0` *unmapped*)**.

Selain korupsi pelabuhan, audit ini menemukan **empat cacat tambahan** pada `691` baris korup tersebut:
1. **Salah Klasifikasi Migas:** Seluruh `691` baris memiliki `is_migas = 0`, sehingga **3 transaksi Migas HS `27101941` senilai `US$ 49,515,228.970000` (`2.23%`)** salah diklasifikasikan sebagai Non-Migas.
2. **Atribut Dimensi Kosong:** Seluruh `691` baris memiliki `sektor = NULL`, `bec_category = NULL`, dan `provorig_key = ''` (string kosong, `NOT NULL DEFAULT ''`).
3. **Ketiadaan Batch/Transaksi/Tier-2:** Batch `#40` telah dihapus permanen pada `2026-11-12 03:12:52` (log #73), meninggalkan `0` baris pada `data_batches`, `0` baris pada `distribusi_transactions`, dan `0` baris pada `brs_indicator_summaries` untuk `distribusi | Ekspor | 2026-08`.
4. **Kebocoran Visibilitas Origin Mart (`O2`):** `58` baris `monthly_origin_trade_summaries` untuk `2026-08 ekspor` masih tersisa dan terekspos ke publik/Pegawai melalui `/distribusi/metrics` karena tabel tersebut tidak memiliki kolom `status_visibilitas`, sementara status data `2026-08` Ekspor Distribusi adalah `PRIVATE`.

**Bukti Matematis Mengapa `UPDATE` In-Place Mustahil:**  
Apabila dimensi pelabuhan diabaikan, `809` transaksi DBF membentuk tepat **`691` pasangan unik `(hs_id, negara_id)`** — identik 100% (`0` *only-in-DBF*, `0` *only-in-Mart*) dengan `691` baris di mart. Namun saat 16 pelabuhan riil diperhitungkan, **`92` pasangan `(hs_id, negara_id)` tersebar di lebih dari satu pelabuhan Riau**, memecah `691` baris menjadi **`797` cube unik 8-dimensi (`+106` cube tambahan)**. Karena angka beberapa pelabuhan telah dijumlahkan ke satu baris `Dumai`, koreksi in-place melalui `UPDATE` tidak mungkin dilakukan secara matematis; koreksi wajib melalui **re-ingestion DBF asli (Opsi R1 — Direkomendasikan)** atau **pembersihan total orphan (Opsi R2 — Alternatif)**.

---

## 1. Kondisi Awal (`sidatex_testing`)

Verifikasi live PDO dijalankan pada seluruh skrip audit:
```php
$activeDb = DB::selectOne('SELECT DATABASE() AS db')->db;
// Output: 'sidatex_testing' (0 mutasi dijalankan)
```

### 1.1 Jejak Historis Batch `#40`
| Peristiwa | Timestamp | Referensi Audit Log | Keterangan |
|---|---|---|---|
| Upload Batch `#40` | `2026-11-11 08:17:37` | `activity_logs` #38 | Upload Ekspor Distribusi `2026-08`; membentuk `691` baris Tier-1 dan `58` baris Origin Mart pada `08:17:38` |
| Soft-Delete ke Trash | `2026-11-11 14:33:52` | `activity_logs` #57 | Batch dipindahkan ke Trash |
| Force-Delete Permanen | `2026-11-12 03:12:52` | `activity_logs` #73 | `data_batches` & `distribusi_transactions` terhapus, namun `691` baris Tier-1 (`batch_id = NULL`) dan `58` baris Origin Mart tertinggal sebagai orphan |

### 1.2 Snapshot Tabel Terkait Periode `distribusi | Ekspor | 2026-08`
| Tabel | Baris Saat Ini | Jumlah Tx | Total Nilai (USD) | Total Berat (Kg) | Status / Temuan |
|---|---|---|---|---|---|
| `data_batches` | `0` | — | — | — | Tidak ada batch aktif maupun soft-deleted |
| `distribusi_transactions` | `0` | `0` | `0.000000` | `0.000000` | Tidak ada data transaksi mentah |
| `brs_indicator_summaries` | `0` | — | — | — | Tidak ada indikator Tier-2 |
| `monthly_trade_summaries` | **`691`** | **`809`** | **`2,218,155,995.460000`** | **`2,352,083,988.620000`** | `batch_id = NULL`, `status_visibilitas = 'PRIVATE'`, **100% korup di `pelabuhan_id = 38` (`Dumai`)** |
| `monthly_origin_trade_summaries` | **`58`** | **`809`** | **`2,218,155,995.460000`** | **`2,352,083,988.620000`** | Tanpa kolom visibilitas → **kebocoran `PRIVATE` ke publik (`O2`)** |

### 1.3 Audit Lengkap Kolom Dimensi pada `691` Baris Tier-1 Korup
- **Fingerprint SHA-256 (691 baris Tier-1):** `d664178098fd04bbfe56b510144645cb109ac426813b1d069e450f43497a6543`
- **Fingerprint SHA-256 (58 baris Origin Mart):** `ef24390f6e9ce7690afdbf4bd34963b758416094616d81a71ed8ec9ed85a72ee`
- **Rentang Timestamp `created_at`:** `2026-11-11 08:17:38` (seluruh 691 baris Tier-1 & 58 baris Origin Mart)

| Atribut Dimensi | Nilai Aktual pada 691 Baris | Status Audit |
|---|---|---|
| `batch_id` | `NULL` (691/691) | **CACAT** — Terlepas dari `data_batches` (menambah `historical_unlinked_rows` dari `30,147` menjadi `30,838`) |
| `status_visibilitas` | `'PRIVATE'` (691/691) | Valid secara kebijakan (`PRIVATE`), tidak boleh diubah ke `RILIS` tanpa keputusan eksplisit |
| `pelabuhan_id` / `pelabuhan_key` | `38` / `38` (`Dumai`) pada 691/691 baris | **KORUP TOTAL** — 15 pelabuhan Riau lainnya hilang (0 baris) |
| `provinsi_id` | `4` (Riau) pada 691/691 baris | Konsisten |
| `hs_id` / `hs2` / `mapping_status` | `0` NULL / `0` NULL / `'mapped'` (691/691) | Kode HS terpetakan lengkap |
| `is_migas` | `0` pada 691/691 baris (`migas_1 = 0`) | **CACAT** — 3 transaksi Migas HS `27101941` (`US$ 49,515,228.970000`) salah bertanda Non-Migas |
| `sektor` | `NULL` pada 691/691 baris | **CACAT** — Dimensi sektor hilang total |
| `bec_category` | `NULL` pada 691/691 baris | **CACAT** — Dimensi BEC hilang total |
| `provorig_key` | `''` (blank string, `VARCHAR(10) NOT NULL DEFAULT ''`) pada 691/691 baris | **CACAT** — Dimensi kode asal tidak terisi |

---

## 2. Bukti Sumber DBF Asli

### 2.1 Identitas dan Integritas Berkas
- **Path Relatif:** `referensi_bps/ekspor_distribusi/14_Atap_ekspor_2608_POD_based.DBF`
- **Ukuran Berkas:** `71,642` bytes (`0.07` MB)
- **SHA-256 Checksum:** `f527ff527949756193e3170fc37725f47d33ff7cfc4dc45393acd2603d132ed5`
- **Parser Resmi SIDATEX:** `App\Services\Parsers\ExportDistributionDbfParser`
- **Jumlah Record:** **`809`** transaksi aktif (Periode `YEAR = 2026`, `MTH = 8`, Cakupan POD Riau `PROVPOD = 14`)
- **Struktur 13 Kolom DBF:** `YEAR`, `MTH`, `KODE_HS`, `HS2`, `PEL_MUAT`, `PROVPOD`, `PROVORIG`, `NGRTUJUAN`, `NEGARA`, `PELABUHAN`, `NETTO`, `FOB`, `KETERANGAN`

### 2.2 Klasifikasi Ketepatan Pemetaan Tingkat Transaksi (809/809 Record)
| Kategori Pemetaan | Jumlah Transaksi | Persentase | Rincian |
|---|---|---|---|
| **Certain (100% Unik & Terpetakan)** | **`809`** | **`100.00%`** | Tepat 1 `master_hs`, tepat 1 `master_negara`, tepat 1 `master_pelabuhan`, dan `PROVORIG` valid di `master_wilayah_bps` |
| **Ambiguous (`>1` kandidat pelabuhan)** | **`0`** | **`0.00%`** | Tidak ada kode `PEL_MUAT` / `UN/LOCODE` yang ambigu |
| **Unmapped (`HS` / `Negara` / `Pelabuhan` / `PROVORIG`)** | **`0`** | **`0.00%`** | `unmappedHs = 0`, `unmappedNeg = 0`, `unmappedPel = 0`, `unmappedProvorig = 0` |

### 2.3 Ringkasan Nilai Moneter, Berat, Migas, dan Sektor DBF Asli (BCMath Scale 6)
| Indikator | Nilai DBF Asli (6 Desimal) | Share thd TOTAL (%) | Jumlah Tx |
|---|---|---|---|
| **Total Ekspor (FOB USD)** | **`2,218,155,995.502700`** | `100.00%` | `809` |
| **Total Berat Bersih (Netto Kg)** | **`2,352,083,988.620000`** | `100.00%` | `809` |
| **Migas (`oilgrp_desc = 'MIGAS'` / `HS2 = 27`)** | **`49,515,228.970000`** | `2.23%` | `3` |
| **Non-Migas** | **`2,168,640,766.532700`** | `97.77%` | `806` |
| — Sektor `INDUSTRI PENGOLAHAN` | `2,145,973,895.993400` | `96.75%` *(98.95% thd Non-Migas)* | `744` |
| — Sektor `MIGAS` | `49,515,228.970000` | `2.23%` | `3` |
| — Sektor `PERTANIAN` | `22,666,870.539300` | `1.02%` *(1.05% thd Non-Migas)* | `62` |

> **Catatan Penyebut Share `SEKTOR` pada Tier-2:** Tabel di atas menampilkan pangsa sektor terhadap **Total FOB** (`96.75% + 2.23% + 1.02% = 100.00%`). Pada Tier-2 `brs_indicator_summaries` (`BrsAggregationService.php:598`), dimensi `SEKTOR` menghitung pangsa `MIGAS` terhadap Total (`2.23%`), sedangkan sektor Non-Migas dihitung terhadap **Total Non-Migas** (`INDUSTRI PENGOLAHAN = 98.95%`, `PERTANIAN = 1.05%`). Keduanya sah dan konsisten dengan standar BRS BPS.

**Rincian 3 Transaksi Migas yang Sebelumnya Salah Klasifikasi (`is_migas = 0`):**
| Baris DBF | Kode HS | HS2 | Pelabuhan Muat | Negara Tujuan | PROVORIG | Nilai FOB (USD) | Berat Netto (Kg) |
|---:|---|---|---|---|---|---:|---:|
| `#238` | `27101941` | `27` | Dumai (`ID=38`) | Cina | `1473` | `1,387,230.110000` | `1,000,440.000000` |
| `#239` | `27101941` | `27` | Dumai (`ID=38`) | Korea Selatan | `1473` | `5,547,658.630000` | `4,000,850.000000` |
| `#240` | `27101941` | `27` | Dumai (`ID=38`) | Belanda | `1473` | `42,580,340.230000` | `25,943,750.000000` |
| **Subtotal Migas** | — | — | **Dumai** | **3 Negara** | **`1473`** | **`49,515,228.970000`** | **`30,945,040.000000`** |

---

## 3. Rekonsiliasi 691 Baris Tier-1 (`monthly_trade_summaries`) terhadap DBF

### 3.1 Rekonsiliasi Tanpa Dimensi Pelabuhan — Pembuktian Identitas Sumber
Jika 809 record DBF diagregasikan menurut `(hs_id, negara_id)` saja (meniru bug legacy yang memaksa semua `pelabuhan_id = 38`):
- **Jumlah Pasangan Unik `(hs_id, negara_id)` DBF:** **`691`**
- **Jumlah Baris Tier-1 Saat Ini di Mart:** **`691`**
- **Only-in-DBF:** **`0`** | **Only-in-Mart:** **`0`** (kecocokan kunci `691 / 691 = 100.00%`)
- **Perbandingan Nilai FOB USD:**
  - `595` pasangan identik mutlak hingga 6 desimal (`diff = 0.000000`)
  - `96` pasangan memiliki selisih pembulatan legacy 2 desimal (`|diff| <= 0.01 USD`)
  - `0` pasangan dengan selisih material (`> 0.01 USD`)
  - **Net Delta Nilai FOB (DBF − Mart):** **`+US$ 0.042700`** (`2,218,155,995.502700 - 2,218,155,995.460000`)
- **Perbandingan Berat Netto Kg:** **`0.000000` Kg** (`2,352,083,988.620000 === 2,352,083,988.620000`)

### 3.2 Rekonsiliasi Penuh 8-Dimensi (`uq_mts_8_dimensi`) — Mengapa `691` Berubah Menjadi `797`
Kunci unik Tier-1 pada `monthly_trade_summaries` adalah `(tipe_sumber, hs_id, negara_id, provinsi_id, pelabuhan_key, jenis_transaksi, bulan, tahun)`:
- Dari `691` pasangan `(hs_id, negara_id)`, sebanyak **`92` pasangan tersebar di lebih dari 1 pelabuhan Riau**, menghasilkan **`+106` cube tambahan**:
  $$691 + 106 = 797 \text{ cube unik 8-dimensi}$$
- Selisih antara `809` transaksi DBF dan `797` cube unik (`809 - 797 = 12` transaksi) terjadi secara **normal dan valid** pada **`9` multi-tx cube di Pelabuhan Dumai** yang masing-masing menampung barang dari beberapa kabupaten/kota asal (`PROVORIG`) berbeda namun berbagi `(HS, Negara, Pelabuhan)` identik:

| # | Kode HS | Negara Tujuan | Pelabuhan | Jml Tx | Daftar `PROVORIG` dalam Cube | Total FOB (USD) | Total Netto (Kg) |
|---:|---|---|---|---:|---|---:|---:|
| 1 | `03028911` | Malaysia | Dumai | 2 | `1408`, `1473` | `4,000.000000` | `2,000.000000` |
| 2 | `03028919` | Malaysia | Dumai | 3 | `1408`, `1409`, `1473` | `297,000.000000` | `148,500.000000` |
| 3 | `07089000` | Malaysia | Dumai | 2 | `1371`, `1473` | `3,618.565700` | `13,700.000000` |
| 4 | `08045030` | Malaysia | Dumai | 2 | `1371`, `1473` | `74,841.030700` | `154,550.000000` |
| 5 | `09109990` | Malaysia | Dumai | 2 | `1371`, `1473` | `11,433.193100` | `30,850.000000` |
| 6 | `15111000` | India | Dumai | 4 | `1207`, `1407`, `1409`, `1473` | `165,396,544.760000` | `142,358,644.000000` |
| 7 | `15119020` | Italia | Dumai | 2 | `1404`, `1473` | `48,470,066.780000` | `41,191,372.000000` |
| 8 | `23066010` | Korea Selatan | Dumai | 2 | `1408`, `1473` | `4,410,676.200000` | `31,881,456.000000` |
| 9 | `38231920` | Italia | Dumai | 2 | `1404`, `1473` | `9,779,979.750000` | `10,086,337.000000` |

- **Verifikasi Keseimbangan:** `788×1 + 7×2 + 1×3 + 1×4 = 788 + 14 + 3 + 4 = 809` transaksi → `797` cube unik (`0` duplikasi, `0` kehilangan transaksi).
- **Catatan Arsitektur `provorig_key`:** Pada 9 multi-tx cube di atas, `monthly_trade_summaries.provorig_key` menyimpan nilai `provorig` representatif pertama karena `provorig_key` tidak masuk indeks `uq_mts_8_dimensi`; rincian penuh multi-`PROVORIG` dipertahankan 100% utuh pada `distribusi_transactions` (`809` baris) dan pada `monthly_origin_trade_summaries` (saat dirilis).

---

## 4. Rekonsiliasi 58 Baris Origin Mart (`monthly_origin_trade_summaries`)

### 4.1 Rekonsiliasi Kunci & Nilai terhadap DBF Asli
Agregasi DBF berdasarkan kunci `(provorig, hs2, sektor, is_migas)` menghasilkan tepat **`58` baris**, identik dengan `58` baris existing di `monthly_origin_trade_summaries` untuk `2026-08 ekspor`:
- **Key Match:** **`58 / 58` (`100.00%`)** — `onlyInDbf = 0`, `onlyInOriginMart = 0`.
- **Akurasi Nilai FOB USD:**
  - `32` baris identik hingga 6 desimal (`diff = 0.000000`)
  - `25` baris memiliki selisih pembulatan legacy `<= 0.01 USD`
  - `1` baris (`provorig = 1405`, `hs2 = 48`, `sektor = INDUSTRI PENGOLAHAN`, `is_migas = 0`, `206` transaksi) memiliki akumulasi pembulatan legacy **`-US$ 0.023000`**
  - **Net Delta Total (Origin Mart − DBF):** **`-US$ 0.042700`** (`2,218,155,995.460000` vs `2,218,155,995.502700`)
- **Akurasi Berat Netto Kg:** **`0.000000` Kg** (`2,352,083,988.620000 === 2,352,083,988.620000`).
- **Komposisi Wilayah Asal (`is_riau`):**
  - **Asal Provinsi Riau (`14xx`):** `51` baris — **`US$ 2,212,492,301.047900`** (**`99.74%`**)
  - **Asal Luar Provinsi Riau (`!14xx`, ekspor via pelabuhan Riau):** `7` baris — **`US$ 5,663,694.454800`** (**`0.26%`**)

### 4.2 Evaluasi Kebocoran Visibilitas `O2` dan Perilaku `BrsAggregationService`
Merujuk pada `BrsAggregationService::syncOriginSummariesForPeriod()` (`app/Services/BrsAggregationService.php:311-351`):
- Tabel `monthly_origin_trade_summaries` **tidak memiliki kolom `status_visibilitas`** dan dikonsumsi oleh endpoint publik/Pegawai `/distribusi/metrics`.
- Kontrak keamanan Phase 2G.2A menetapkan bahwa `syncOriginSummariesForPeriod()` **hanya** mengagregasi batch `distribusi` aktif (non-Trash) yang berstatus **`RILIS`**.
- Saat ini `58` baris `2026-08 ekspor` masih tertinggal sebagai artefak pra-Phase 2G.2A, sehingga **membocorkan data `PRIVATE` ke Role Pegawai (Role 2)**.
- **Dampak saat Remediasi Dijalankan:**
  - Selama batch Ekspor Distribusi `2026-08` tetap berstatus **`PRIVATE`** (Opsi R1) atau dibersihkan (Opsi R2), pemanggilan `syncOriginSummariesForPeriod(2026, 8, 'Ekspor')` akan **mengosongkan `58` baris tersebut (`58 -> 0`)**, menutup kebocoran `O2` secara tuntas (sehingga total baris `monthly_origin_trade_summaries` pada `sidatex_testing` menjadi `0`).
  - Begitu Super Admin di masa mendatang secara resmi merilis batch tersebut ke **`RILIS`** melalui `updateBatchVisibility($newBatchId, 'RILIS', $userId)`, `syncOriginSummariesForPeriod(2026, 8, 'Ekspor')` akan **otomatis membangun kembali seluruh `58` baris Origin Mart** dengan presisi penuh 6 desimal (`US$ 2,218,155,995.502700`).

---

## 5. Daftar Lengkap 16 Pelabuhan — Simulasi SEBELUM vs SESUDAH Koreksi

Seluruh 16 pelabuhan berikut berlokasi di Provinsi Riau (`PROVPOD = 14`, `provinsi_id = 4`) dan terpetakan 1-ke-1 ke `master_pelabuhan`:

| # | `pelabuhan_id` | Kode BPS | Raw DBF / UN-LOCODE | Nama Pelabuhan | SEBELUM (Tx / Cube / FOB USD / Share) | SESUDAH Tx | SESUDAH Cube | SESUDAH FOB (USD) | Share (%) | SESUDAH Netto (Kg) | Delta FOB (USD) |
|---:|---:|---|---|---|---:|---:|---:|---:|---:|---:|---:|
| 1 | `38` | `225` | `14225` / `IDDUM` | **Dumai** | `809` / `691` / `2,218,155,995.460000` (`100.00%`) | `283` | `271` | `1,810,669,357.459300` | `81.63%` | `1,659,830,927.950000` | **`-407,486,638.000700`** |
| 2 | `39` | `003` | `14003` / `IDFTG` | **Futong Terminal** | `0` / `0` / `0.000000` (`0.00%`) | `10` | `10` | `122,406,692.170000` | `5.52%` | `232,793,836.700000` | `+122,406,692.170000` |
| 3 | `34` | `277` | `14277` / `IDBUN` | **Buatan** | `0` / `0` / `0.000000` (`0.00%`) | `232` | `232` | `111,040,124.090000` | `5.01%` | `177,093,351.160000` | `+111,040,124.090000` |
| 4 | `35` | `278` | `14278` / `IDPWG` | **Perawang, Sumatra** | `0` / `0` / `0.000000` (`0.00%`) | `213` | `213` | `109,936,050.083000` | `4.96%` | `152,951,307.900000` | `+109,936,050.083000` |
| 5 | `8` | `227` | `14227` / `IDSEQ` | **Sungai Pakning** | `0` / `0` / `0.000000` (`0.00%`) | `4` | `4` | `28,095,005.000000` | `1.27%` | `51,400,000.000000` | `+28,095,005.000000` |
| 6 | `16` | `235` | `14235` / `IDSUQ` | **Sungai Guntung** | `0` / `0` / `0.000000` (`0.00%`) | `20` | `20` | `23,175,063.047700` | `1.04%` | `17,767,264.910000` | `+23,175,063.047700` |
| 7 | `12` | `231` | `14231` / `IDSSI` | **Siak Sri Indrapura** | `0` / `0` / `0.000000` (`0.00%`) | `2` | `2` | `3,602,721.550000` | `0.16%` | `36,997,119.000000` | `+3,602,721.550000` |
| 8 | `24` | `243` | `14243` / `IDKEN` | **Kuala Enok** | `0` / `0` / `0.000000` (`0.00%`) | `2` | `2` | `3,466,509.687500` | `0.16%` | `2,689,313.000000` | `+3,466,509.687500` |
| 9 | `9` | `228` | `14228` / `IDBLS` | **Bengkalis** | `0` / `0` / `0.000000` (`0.00%`) | `8` | `8` | `1,702,999.965500` | `0.08%` | `847,775.000000` | `+1,702,999.965500` |
| 10 | `17` | `236` | `14236` / `IDKGD` | **Kuala Gaung** | `0` / `0` / `0.000000` (`0.00%`) | `8` | `8` | `1,378,276.647700` | `0.06%` | `7,466,000.000000` | `+1,378,276.647700` |
| 11 | `11` | `230` | `14230` / `IDSPA` | **Selat Panjang** | `0` / `0` / `0.000000` (`0.00%`) | `4` | `4` | `873,822.789500` | `0.04%` | `2,912,100.000000` | `+873,822.789500` |
| 12 | `26` | `244` | `14244` / `IDRGA` | **Rengat** | `0` / `0` / `0.000000` (`0.00%`) | `1` | `1` | `844,343.660000` | `0.04%` | `8,277,879.000000` | `+844,343.660000` |
| 13 | `7` | `226` | `14226` / `IDTMD` | **Tanjung Medang** | `0` / `0` / `0.000000` (`0.00%`) | `6` | `6` | `362,655.232400` | `0.02%` | `247,850.000000` | `+362,655.232400` |
| 14 | `30` | `261` | `14261` / `IDPKU` | **Pekan Baru/Simpang Tiga (U)** | `0` / `0` / `0.000000` (`0.00%`) | `1` | `1` | `292,099.500000` | `0.01%` | `53,614.000000` | `+292,099.500000` |
| 15 | `23` | `242` | `14242` / `IDTEM` | **Tembilahan** | `0` / `0` / `0.000000` (`0.00%`) | `3` | `3` | `158,627.106500` | `0.01%` | `498,000.000000` | `+158,627.106500` |
| 16 | `5` | `223` | `14223` / `IDPAI` | **Panipahan** | `0` / `0` / `0.000000` (`0.00%`) | `12` | `12` | `151,647.513600` | `0.01%` | `257,650.000000` | `+151,647.513600` |
| — | **TOTAL** | — | **16 Pelabuhan** | **Seluruh Pelabuhan Riau** | **`809` / `691` / `2,218,155,995.460000` (`100.00%`)** | **`809`** | **`797`** | **`2,218,155,995.502700`** | **`100.00%`** | **`2,352,083,988.620000`** | **`+0.042700`** |

> **Pengecekan Aritmetika:**  
> - `SUM(SESUDAH Tx)` = `283+10+232+213+4+20+2+2+8+8+4+1+6+1+3+12` = **`809`**  
> - `SUM(SESUDAH Cube)` = `271+10+232+213+4+20+2+2+8+8+4+1+6+1+3+12` = **`797`**  
> - `SUM(SESUDAH FOB)` = **`2,218,155,995.502700`** USD (`Delta Dumai = -$407,486,638.000700`; `SUM 15 pelabuhan lain = +$407,486,638.043400`; `Net Delta = +$0.042700`)  
> - `SUM(SESUDAH Netto)` = **`2,352,083,988.620000`** Kg (`Net Delta = 0.000000` Kg)

---

## 6. Dampak terhadap Total Perdagangan, Indikator Pertumbuhan, & Pemisahan Metodologi

### 6.1 Dampak Makro pada `2026-08` (Distribusi Ekspor, Scope `PRIVATE`)
| Indikator | SEBELUM (691 Baris Korup) | SESUDAH Opsi R1 (Re-Ingestion `PRIVATE`) | SESUDAH Opsi R2 (Purge Orphan) |
|---|---|---|---|
| **Total Ekspor FOB (USD)** | `2,218,155,995.460000` | **`2,218,155,995.502700`** (`+$0.042700`) | `0.000000` (periode kosong) |
| **Headline BRS Ekspor** | `US$ 2,218.16 Juta` | **`US$ 2,218.16 Juta`** (TIDAK BERUBAH) | `N/A` (hilang) |
| **Total Berat Netto (Kg)** | `2,352,083,988.620000` | **`2,352,083,988.620000`** (`0.000000` delta) | `0.000000` |
| **Ekspor Migas (USD)** | `0.000000` (**SALAH**) | **`49,515,228.970000`** (`2.23%`, 3 tx) | `0.000000` |
| **Ekspor Non-Migas (USD)** | `2,218,155,995.460000` (**SALAH**) | **`2,168,640,766.532700`** (`97.77%`, 806 tx) | `0.000000` |
| **Pangsa Pelabuhan Dumai** | `100.00%` (**KORUP**) | **`81.63%`** (15 pelabuhan lain `18.37%`) | `N/A` |
| **Tier-2 `brs_indicator_summaries`** | `0` baris (kosong) | **~`48` baris** (`TOTAL`, `MIGAS_NONMIGAS`, `SEKTOR`, `HS2_TOP10`, `NEGARA_TOP10`, `16 PELABUHAN`) | `0` baris |
| **MoM `2026-08` vs `#85` (`2026-07`)** | Tidak tersimpan di Tier-2 | **`+16.10%`** (`16.0970%`, vs `$1,910,605,444.96`) | `NULL` |
| **YoY `2026-08` vs `2025-08`** | Tidak tersimpan di Tier-2 | **`+5.70%`** (`5.6956%`, vs `$2,098,626,017.09`) | `NULL` |
| **C-to-C `Jan–Agt 2026` vs `2025`** | Tidak tersimpan di Tier-2 | **`+2.72%`** (`2.7211%`, `$14.66B` vs `$14.27B`) | `NULL` |

### 6.2 Dampak terhadap Neraca Perdagangan (`Trade Balance`) Distribusi `2026-08`
Sesuai `AGENTS.md` §7, Neraca Perdagangan dihitung pada metodologi identik (**Distribusi Ekspor − Distribusi Impor**):
- **Impor Distribusi `2026-08` (Batch `#70`, `RILIS`):** `US$ 167,347,655.077700` (`568,581,779.655200 Kg`).
- **Scope Super Admin & Viewer Eksekutif (`RILIS + PRIVATE`):**
  - **SEBELUM:** Surplus `US$ 2,050,808,340.382300` (`US$ 2,050.81 Juta`)
  - **SESUDAH Opsi R1:** Surplus **`US$ 2,050,808,340.425000`** (`+$0.042700`; headline tetap **Surplus `US$ 2,050.81 Juta`**)
  - **SESUDAH Opsi R2:** Komponen Ekspor `2026-08` lenyap — `DashboardController::data()` (Tab 4) mengembalikan `net_trade = null` (`'Data Ekspor Belum Tersedia'`), sementara `DistributionStatisticsService::getDistributionTradeBalance()` menghasilkan defisit semu `-US$ 167,347,655.077700` (penyelarasan layanan ini dijadwalkan di Phase 2G.3).
- **Scope Pegawai BPS (Role 2 — `RILIS` Only):**
  - Karena Ekspor Distribusi `2026-08` tetap berstatus `PRIVATE` (sesuai ketentuan bahwa remediasi ini **tidak mengubah visibility**), komponen Ekspor `RILIS` `2026-08` tetap `US$ 0.000000` hingga Super Admin secara eksplisit merilisnya.

### 6.3 Dampak terhadap Batch Tetangga `#71` (`distribusi | Ekspor | 2026-09`, `PRIVATE`)
| Indikator Batch `#71` (`2026-09`, `$2,301,809,204.587000`) | SEBELUM | SESUDAH Opsi R1 | SESUDAH Opsi R2 |
|---|---|---|---|
| `growth_mtom` (`2026-09` vs `2026-08`) | `+3.77%` (`3.7713%`) | **`+3.77%`** (`3.7713%` — IDENTIK) | **`NULL` (`N/A`)** — seri MoM terputus |
| `growth_yoy` (`2026-09` vs `2025-09`) | `+45.04%` | **`+45.04%`** (IDENTIK) | `+45.04%` |
| `growth_ctc` (`Jan–Sep 2026` vs `2025`) | `+6.96%` (`6.9556%`) | **`+6.96%`** (`6.9556%` — IDENTIK) | **`-7.03%`** — **DISTORSI PARAH (-13.99 poin %)** karena membandingkan 8 bulan `2026` terhadap 9 bulan `2025`! |

### 6.4 Verifikasi Pemisahan Tegas Distribusi vs Neraca (`AGENTS.md` §6)
- **Batch `#87` (`neraca | Ekspor | 2026-08`, `PRIVATE`):** `925` transaksi, `901` cube Tier-1, FOB `US$ 2,226,732,699.021600`, Netto `2,363,207,850.844000 Kg` (cakupan `PROVORIG = Riau`).
- **Batch `#40` (`distribusi | Ekspor | 2026-08`, `PRIVATE`):** `809` transaksi, `797` cube Tier-1, FOB `US$ 2,218,155,995.502700`, Netto `2,352,083,988.620000 Kg` (cakupan `PROVPOD = Riau`).
- Selisih metodologis antara Neraca dan Distribusi (`+$8,576,703.518900`) adalah wajar secara statistik BPS (barang asal Riau yang dimuat di pelabuhan luar Riau vs barang luar Riau yang dimuat di pelabuhan Riau). Kedua metodologi **terpisah 100%** di `distribusi_transactions` vs `neraca_transactions`, `tipe_sumber`, dan `BrsAggregationService`; remediasi ini **tidak menyentuh `neraca_transactions` maupun Batch `#87`**.

### 6.5 Dampak pada `DataReconciliationService::auditConsistency()`
- Saat ini `historical_unlinked_rows = 30,838` (`30,147` historis `2023–2026-06` + `691` orphan Batch `#40`).
- Setelah **Opsi R1** (maupun Opsi R2), `historical_unlinked_rows` turun menjadi tepat **`30,147`** (100% murni data historis `pelabuhan_key = 0` sesuai keputusan A-1), dan seluruh batch ter-link (`10` batch pada Opsi R1) memiliki **`0.000000` drift**.

---

## 7. Rencana Koreksi & Protokol Rollback

### 7.1 Evaluasi Tiga Pendekatan
| Opsi | Deskripsi | Kelayakan | Keputusan |
|---|---|---|---|
| **In-Place `UPDATE` pada 691 Baris** | Memperbaiki kolom `pelabuhan_id`/`pelabuhan_key` langsung pada `691` baris existing | **MUSTAHIL SECARA MATEMATIS** — `92` pasangan `(hs_id, negara_id)` multi-pelabuhan telah tergabung ke `Dumai` (`691` baris tidak bisa dipecah menjadi `797` cube tanpa 809 record DBF) | **DITOLAK** |
| **Opsi R1 (DIREKOMENDASIKAN)** | **Re-Ingestion Resmi sebagai Batch Baru `PRIVATE`** dari DBF asli `14_Atap_ekspor_2608_POD_based.DBF` | **LAYAK & OPTIMAL** — Memulihkan 16 pelabuhan (`797` cube), 3 baris Migas, `sektor`/`bec_category`/`kabkota_id`, Tier-2 lengkap, mempertahankan kontinuitas `#71` (`MoM +3.77%`, `C-to-C +6.96%`), menutup kebocoran `O2` (`58 -> 0` selama `PRIVATE`), tanpa mengubah visibility menjadi `RILIS` | **DIREKOMENDASIKAN** |
| **Opsi R2 (Alternatif)** | **Pembersihan Terkendali Orphan** (`DELETE` `691` baris Tier-1 korup & `58` baris Origin Mart `O2` tanpa re-ingestion) + refresh `#71` | Layak hanya jika `2026-08` Ekspor Distribusi memang sengaja dikosongkan; **menimbulkan distorsi parah pada `C-to-C` `#71` (`+6.96% -> -7.03%`) dan `MoM` `#71` (`NULL`)** | Alternatif (tidak direkomendasikan) |

### 7.2 Urutan Langkah Eksekusi Terkendali — Opsi R1 (Jika Disetujui — Diperkeras Final Safety Review)
> **Prasyarat Mutlak:** Persetujuan tertulis eksplisit dari pengguna; target HANYA `sidatex_testing`. Detail teknis pengamanan lengkap (backup Windows PS 5.1, advisory lock concurrency, konsistensi file-DB, audit Origin Mart & `/distribusi/metrics`, serta perlindungan Batch `#71`) terdokumentasi di `SIDATEX_PHASE_2G_BATCH_40_FINAL_SAFETY_REVIEW.md`.

1. **Step 0 — Verifikasi Live PDO, Snapshot Operasional (`sidatex`), & Snapshot Batch `#71`:**
   - Pastikan `SELECT DATABASE() === 'sidatex_testing'`.
   - Catat row-count, `data_batches.MAX(updated_at) = 2026-11-13 07:38:33`, dan `monthly_trade_summaries.MAX(updated_at) = 2026-11-12 07:06:16` secara read-only pada database operasional `sidatex`.
   - Catat baseline BEFORE Batch `#71` (`status = 'PRIVATE'`, `841` tx, `SUM(nilai) = 2,301,809,204.587000`, `SUM(berat) = 2,553,373,143.520000`, raw SHA-256 `ac739c0e484c711e4f1480eef439033c03b6d0069e9e64ea32ddb6e7f388f576`, `831` Tier-1, `47` Tier-2).
2. **Step 1 — Backup Penuh Pra-Eksekusi Aman Windows PowerShell 5.1 (`sidatex_testing`):**
   - Gunakan path absolut binary Laragon MySQL `8.0.30` dan `--result-file` (**DILARANG** memakai shell redirection `>` di Windows PowerShell `5.1.26100.3624` karena menghasilkan UTF-16 LE BOM yang merusak dump SQL):
     ```powershell
     $dumpExe = "D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe"
     $bkpPath = "D:\laragon\www\sidatex\storage\backups\sidatex_testing_pre_batch40_YYYYMMDD.sql"
     & $dumpExe --single-transaction --routines=0 --triggers=0 --set-gtid-purged=OFF --default-character-set=utf8mb4 -h 127.0.0.1 -P 3306 -u root --result-file="$bkpPath" sidatex_testing
     if ($LASTEXITCODE -ne 0) { throw "BACKUP GAGAL (exit code $LASTEXITCODE)" }
     ```
   - Validasi multi-lapis wajib sebelum lanjut (jangan menganggap backup valid hanya karena file terbentuk): cek `$LASTEXITCODE -eq 0`, ukuran file `> 0` (baseline `~13.9 MB`), catat `Get-FileHash -Algorithm SHA256`, verifikasi 4 byte pertama = `2d2d204d` (`-- M`, murni UTF-8 tanpa UTF-16 BOM `fffe`/`feff`), baris akhir memuat `-- Dump completed on`, dan memuat marker `CREATE TABLE` untuk 5 tabel inti (`data_batches`, `distribusi_transactions`, `monthly_trade_summaries`, `monthly_origin_trade_summaries`, `brs_indicator_summaries`).
3. **Step 2 — Penguncian Concurrency (`GET_LOCK`), Verifikasi DBF, & Guard Idempotensi 5-Lapis:**
   - Karena `data_batches` hanya memiliki indeks `PRIMARY (batch_id)` dan 2 indeks FK (tanpa indeks/unique constraint pada periode), `lockForUpdate()` saja saat 0 baris tidak mencegah race condition secara andal (rawan deadlock di `REPEATABLE READ` atau tanpa kunci di `READ COMMITTED`). Tanpa menambah migration/unique constraint, peroleh **MySQL Session Advisory Lock** sebelum `DB::transaction()`:
     `SELECT GET_LOCK('sidatex:remediate:distribusi:Ekspor:2026:08', 10)` (ABORT jika hasil `!== 1`; wajib `RELEASE_LOCK(...)` pada blok `finally`).
   - Pastikan SHA-256 DBF sumber `referensi_bps/ekspor_distribusi/14_Atap_ekspor_2608_POD_based.DBF` === `f527ff527949756193e3170fc37725f47d33ff7cfc4dc45393acd2603d132ed5` (`71,642` bytes).
   - Salin sumber DBF ke staging temporer privat `storage/app/private/temp_staging/remediate_{uuid}.dbf` (disk `'local'`), verifikasi ulang SHA-256 & ukuran; wajib dihapus pada blok `finally`.
   - Di dalam transaksi, periksa `data_batches` aktif (`NOT IN active trash`) untuk `('distribusi', 'Ekspor', 2026, 8)` dengan `lockForUpdate()` sebagai *defense-in-depth*:
     - Jika ditemukan `> 1` batch aktif → **HARD ABORT**.
     - Jika ditemukan `1` batch aktif:
       1. Verifikasi `tipe_sumber === 'distribusi'`, `strtolower(jenis_data) === 'ekspor'`, `periode_tahun === 2026`, `periode_bulan === 8`.
       2. Jika `status === 'RILIS'` → **HARD ABORT & minta persetujuan baru** (dilarang menyentuh batch yang sudah rilis). Jika `status !== 'PRIVATE'` (mis. `DRAFT`) → **HARD ABORT**.
       3. Verifikasi checksum DBF arsip (jika ada) === `f527ff527949756193e3170fc37725f47d33ff7cfc4dc45393acd2603d132ed5`.
       4. Verifikasi `COUNT(distribusi_transactions WHERE batch_id = $existingId) === 809` dan **canonical SHA-256 seluruh 809 transaksi** === `028082b5015240f32c2fa0839f37cb49c21296502ac9cc882c8597a0f2d19507`. Jika tidak cocok → **HARD ABORT**.
       5. Hanya jika seluruh 4 cek di atas lolos 100%, lewati pembuatan batch/transaksi baru dan jalankan `aggregateBatch($existingId, 'PRIVATE')`.
4. **Step 3 — Transaksi Atomik `DB::transaction()` (Ingestion + Agregasi + Konsistensi File Privat + Audit Trail):**
   - **Insert `data_batches`** sesuai skema aktual (`DbfIngestionService.php:156-170`, `DataBatch.php:18-27`):
     - `nama_file` (basename aman: `{timestamp}_14_Atap_ekspor_2608_POD_based.DBF` bila pengarsipan disetujui, atau `'14_Atap_ekspor_2608_POD_based.DBF'` bila tanpa arsip fisik), `jenis_data = 'Ekspor'`, `periode_tahun = 2026`, `periode_bulan = 8`, `tipe_sumber = 'distribusi'`, `ukuran_file = '0.07'` (`round(71642 / 1048576, 2)`), `status = 'PRIVATE'`, `uploaded_by = 1` (ID Super Admin valid), `uploaded_at = $now`, `released_by = null`, `released_at = null`, `created_at = $now`, `updated_at = $now`.
   - **Insert `809` baris ke `distribusi_transactions`** via `ExportDistributionDbfParser::parseStream()` sesuai skema aktual (`DbfIngestionService.php:201-217`, `DistribusiTransaction.php:21-35`):
     - `batch_id = $newBatchId`, `tipe_sumber = 'distribusi'`, `jenis_transaksi = 'Ekspor'`, `tahun = 2026`, `bulan = 8`, `hs_id`, `negara_id`, `provinsi_id = 4`, **`kabkota_id`** (dipetakan dari `PROVORIG` via `master_kabkota.kode_kabkota`: `537` terpetakan + `272` `NULL` sesuai paritas `DbfIngestionService.php:191-195` & Batch `#71`), `pelabuhan_id` (1-ke-1 ke 16 pelabuhan valid), `provorig`, `nilai` & `berat` (BCMath scale 6), `created_at = $now`, `updated_at = $now` *(tanpa `mapping_status`)*.
   - **Catat Audit Trail di `activity_logs`:** Sisipkan log aksi remediasi yang mereferensikan insiden historis Batch `#40` dan ID batch baru `$newBatchId`.
   - **Jalankan `BrsAggregationService::aggregateBatch($newBatchId, 'PRIVATE')`:**
     - Otomatis menghapus `691` baris korup `2026-08` melalui guard `pelabuhan_key > 0` (`BrsAggregationService.php:122-131`) tanpa menyentuh satu pun dari `30,147` baris historis (`batch_id IS NULL AND pelabuhan_key = 0`).
     - Menyisipkan `797` baris Tier-1 `PRIVATE` (`16` pelabuhan akurat, `3` baris Migas `is_migas = 1`, `sektor` & `bec_category` terisi penuh).
     - Menyisipkan Tier-2 `brs_indicator_summaries` `PRIVATE` (`TOTAL`, `MIGAS_NONMIGAS`, `SEKTOR`, `HS2_TOP10`, `NEGARA_TOP10`, `16 PELABUHAN`).
     - Memanggil `syncOriginSummariesForPeriod(2026, 8, 'Ekspor')` → otomatis membersihkan `58` baris kebocoran `O2` (`58 -> 0`) karena status batch masih `PRIVATE`.
   - **Jalankan `BrsAggregationService::aggregateBatch(71, 'PRIVATE')`** untuk merefresh ringkasan & indikator pertumbuhan Batch `#71` (`2026-09`) terhadap `2026-08` presisi 6 desimal (hanya setelah pre-check `data_batches#71.status === 'PRIVATE'`).
   - **Pre-Commit Assertions (abort & rollback atomik + hapus file temporer/arsip jika gagal):**
     - `COUNT(distribusi_transactions WHERE batch_id = $newBatchId) === 809`, `SUM(nilai) === '2218155995.502700'`, `SUM(berat) === '2352083988.620000'`, canonical SHA-256 === `028082b5015240f32c2fa0839f37cb49c21296502ac9cc882c8597a0f2d19507`
     - `COUNT(monthly_trade_summaries WHERE batch_id = $newBatchId) === 797`, `COUNT(DISTINCT pelabuhan_id) === 16`, `SUM(total_nilai WHERE is_migas = 1 AND batch_id = $newBatchId) === '49515228.970000'`
     - `COUNT(monthly_trade_summaries WHERE batch_id IS NULL) === 30147` & SHA-256 17-kolom imutabel `30,147` baris historis === `8c946a1ad978a04eda84517d062f5ddc6b09ae7abbbd9fdfb94e217abbf8207e`
     - `COUNT(monthly_origin_trade_summaries) === 0` (seluruh `58` baris `O2` tertutup, 0 baris periode lain tersentuh)
     - `COUNT(neraca_transactions) === 4065` (tidak tersentuh)
     - **Perlindungan Imutabilitas Batch `#71`:** `data_batches#71` (`batch_id = 71`, `status = 'PRIVATE'`, `released_by = null`, `updated_at = '2026-11-13 05:19:17'`), `distribusi_transactions#71` (`COUNT = 841`, `SUM(nilai) = 2301809204.587000`, `SUM(berat) = 2553373143.520000`, raw SHA-256 === `ac739c0e484c711e4f1480eef439033c03b6d0069e9e64ea32ddb6e7f388f576`), `MTS#71` (`831` baris, `PRIVATE`), `BRS#71` (`47` baris, `growth_mtom = 3.77`, `growth_yoy = 45.04`, `growth_ctc = 6.96`).
   - **Konsistensi File Privat & Database (Bila Pengarsipan Disetujui):**
     - Tulis berkas arsip ke disk privat `local` (`storage/app/private/uploads/raw_distribusi/{permFilename}`, non-publik karena `public/storage` symlink tidak ada) **tepat setelah seluruh pre-commit assertions lulus**, verifikasi `file_exists && filesize === 71642 && sha256 === f527ff52...`, lalu jalankan `DB::commit()`.
     - Jika penulisan berkas gagal atau `DB::commit()` gagal → `DB::rollBack()` dan `catch` langsung menghapus `$permFullPath` jika sempat terbuat; blok `finally` selalu menghapus `temp_staging/remediate_{uuid}.dbf` dan melepas `RELEASE_LOCK(...)` → **0 file sementara/arsip yatim tertinggal**.
5. **Step 4 — Penyelarasan Test, Uji Idempotensi, Rekonsiliasi, PHPUnit, & Audit Operasional:**
   - **Penyelarasan Regression Test (`Stage2bValidationAndSimulationTest.php:73-81`):** Selaraskan `test_monthly_origin_trade_summaries_composite_integrity()` menggunakan simulasi in-transaction `RILIS` + `syncOriginSummariesForPeriod()` + `DB::rollBack()` (pola identik dengan `DistributionModuleTest.php:37-38`), **tanpa melemahkan assertion**.
   - Jalankan ulang skrip remediasi/agregasi untuk membuktikan idempotensi (`GET_LOCK` + 5-guard idempotency → 0 batch baru, `797` baris, `0.000000` drift).
   - Jalankan `DataReconciliationService::auditConsistency()` → wajib `CONSISTENT`, `is_consistent = true`, `historical_unlinked_rows = 30147`.
   - Jalankan full suite PHPUnit (`php artisan test`) → `100% PASS` (0 failure).
   - Verifikasi ulang read-only database operasional `sidatex` 100% identik dengan Step 0.

### 7.3 Protokol Rollback (Kompatibel Windows PowerShell 5.1)
1. **Rollback Otomatis Dalam Transaksi + Cleanup File:** Setiap kegagalan validasi, pre-commit assertion, atau penulisan arsip langsung mengeksekusi `DB::rollBack()`, menghapus berkas arsip parsial (di `catch`), menghapus berkas staging temporer (di `finally`), dan melepas advisory lock `RELEASE_LOCK(...)`.
2. **Rollback Logis Pasca-Commit *(Catatan Batasan)*:** Menghapus `$newBatchId` via `removeBatchAggregation($newBatchId)` + `DELETE` pada `distribusi_transactions` & `data_batches` hanya mengosongkan periode `2026-08` (setara Opsi R2) dan **tidak** mengembalikan `691` baris korup + `58` baris Origin Mart semula.
3. **Rollback Fisik Penuh — Satu-Satunya Rollback Deterministik ke Baseline Pra-Eksekusi (Kompatibel PowerShell 5.1):**
   > **PERINGATAN WINDOWS POWERSHELL 5.1:** Operator `<` **TIDAK VALID** di PowerShell 5.1 (`The '<' operator is reserved for future use`), dan piping `Get-Content | mysql` berisiko korupsi encoding/memori. Gunakan salah satu metode aman berikut (dan verifikasi target adalah `sidatex_testing`, **DILARANG** ke `sidatex`!):
   ```powershell
   # Metode A (Direkomendasikan — native mysql SOURCE dengan forward slash, tanpa shell redirection):
   $mysqlExe = "D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe"
   & $mysqlExe --default-character-set=utf8mb4 -h 127.0.0.1 -P 3306 -u root sidatex_testing -e "SOURCE D:/laragon/www/sidatex/storage/backups/sidatex_testing_pre_batch40_YYYYMMDD.sql"
   if ($LASTEXITCODE -ne 0) { throw "RESTORE GAGAL (exit code $LASTEXITCODE)" }

   # Metode B (Alternatif — binary stream via cmd.exe /c dengan outer-quote aman anti quote-stripping Windows):
   cmd.exe /c '""D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe" --default-character-set=utf8mb4 -h 127.0.0.1 -P 3306 -u root sidatex_testing < "D:\laragon\www\sidatex\storage\backups\sidatex_testing_pre_batch40_YYYYMMDD.sql""'
   if ($LASTEXITCODE -ne 0) { throw "RESTORE GAGAL (exit code $LASTEXITCODE)" }
   ```
   Setelah restore, hapus berkas arsip baru milik `$newBatchId` (jika sempat dibuat di `storage/app/private/uploads/raw_distribusi/`) dan verifikasi SHA-256 `691` baris Tier-1 kembali ke `d664178098fd04bbfe56b510144645cb109ac426813b1d069e450f43497a6543` serta `58` baris Origin Mart kembali ke `ef24390f6e9ce7690afdbf4bd34963b758416094616d81a71ed8ec9ed85a72ee`.

---

## 8. Hasil Review Independen (3 Specialist Agents — Read-Only)

| Reviewer Agent | Verdict | Ringkasan Temuan & Status Integrasi ke Dokumen Final |
|---|---|---|
| **`database-guardian`** | **`PASS WITH MINOR NOTES`** | Memverifikasi bukti matematis `691 -> 797` cube, keamanan guard `BrsAggregationService.php:122-131` (perlindungan ganda `30,147` baris historis `pelabuhan_key = 0`), dan seluruh angka 6-desimal. Mengidentifikasi 4 koreksi wajib sebelum eksekusi: (C-1) skema kolom aktual `data_batches` & `distribusi_transactions` (`ukuran_file`, `kabkota_id`, tanpa `total_rows`/`total_nilai_usd`/`total_berat_kg`/`mapping_status`), (C-2) dampak pengosongan `O2` (`58 -> 0`) terhadap `Stage2bValidationAndSimulationTest.php:75-76`, (C-3) klarifikasi timestamp operasional `sidatex` (`data_batches` vs `monthly_trade_summaries`), dan (C-4) `provorig_key = ''`, flag `mysqldump`, klarifikasi rollback deterministik, & `activity_logs`. **Seluruh 4 catatan telah diintegrasikan penuh ke dokumen ini.** |
| **`statistics-auditor`** | **`PASS WITH MINOR NOTES`** | Memverifikasi 100% eksak seluruh rekonsiliasi 6-desimal (`+$0.042700` FOB, `0.000000` Kg), tabel 16 pelabuhan (`809` tx, `797` cube, delta Dumai `-$407,486,638.000700` / `-18.37%`), 3 transaksi Migas (`$49,515,228.97` / `2.23%`), sektor, Origin (`99.74%` Riau / `0.26%` Non-Riau), pertumbuhan BPS (`#40` MoM `+16.10%`, YoY `+5.70%`, C-to-C `+2.72%`; `#71` Opsi R1 MoM `+3.77%` & C-to-C `+6.96%` vs Opsi R2 `-7.03%`), Trade Balance, dan pemisahan Distribusi vs Neraca (`#87`). Merekomendasikan penuh **Opsi R1** dengan catatan: pemetaan `kabkota_id` untuk Tab 3 Leaflet, nuansa Trade Balance Opsi R2 (`DashboardController` `null` vs `DistributionStatisticsService`), catatan penyebut share `SEKTOR` Tier-2 (`BrsAggregationService.php:598`), dan pembulatan `3.7713%`. **Seluruh catatan telah diintegrasikan penuh ke dokumen ini.** |
| **`independent-reviewer`** | **`APPROVED WITH MINOR NOTES`** | Mengonfirmasi kelengkapan seluruh 9 bagian, akurasi aritmetika internal 100%, serta validitas rekomendasi **Opsi R1 (`PRIVATE`)**. Menegaskan secara independen 4 perbaikan konkret sebelum presentasi/eksekusi: dampak `Stage2bValidationAndSimulationTest.php:73-81`, kesesuaian kolom SQL `DbfIngestionService.php:156-170, 201-217`, rekonsiliasi timestamp operasional `sidatex` (`data_batches = 2026-11-13 07:38:33` vs `monthly_trade_summaries = 2026-11-12 07:06:16`), serta flag aman `mysqldump` dan guard idempotensi `lockForUpdate()`. **Seluruh 4 catatan telah diintegrasikan penuh ke dokumen ini.** |

---

## 9. Risiko yang Belum Terselesaikan (Open Risks)

1. **Asimetri Visibilitas Trade Balance `2026-08` untuk Pegawai BPS (Role 2):**
   - Impor Distribusi `2026-08` (Batch `#70`) sudah berstatus `RILIS`, sedangkan Ekspor Distribusi `2026-08` (remediasi Batch `#40`) tetap berstatus `PRIVATE` sesuai ketentuan agar remediasi tidak mengubah status visibilitas secara sepihak.
   - Akibatnya, selama Ekspor Distribusi `2026-08` belum dirilis oleh Super Admin, Pegawai BPS (Role 2) akan melihat `Ekspor = 0` vs `Impor = $167.35 Juta` pada `2026-08` (`DashboardController::data()` menampilkan `net_trade = null` / `'Data Ekspor Belum Tersedia'`, sementara `DistributionStatisticsService` akan diselaraskan di Phase 2G.3). **Mitigasi:** Keputusan promosi `PRIVATE -> RILIS` diserahkan sepenuhnya kepada Super Admin setelah verifikasi hasil remediasi.
2. **Kekosongan `monthly_origin_trade_summaries` untuk `2026-08 ekspor` Selama Status `PRIVATE` & Dampak Test:**
   - Pembersihan `58` baris `O2` (`58 -> 0`) menutup kebocoran `PRIVATE` ke Role Pegawai, namun membuat total baris `monthly_origin_trade_summaries` di `sidatex_testing` menjadi `0` hingga ada batch Distribusi yang dirilis ke `RILIS`.
   - **Dampak Test Teridentifikasi:** `tests/Feature/Stage2bValidationAndSimulationTest.php:73-81` wajib diselaraskan (in-transaction `RILIS` simulation + `DB::rollBack()`, sama seperti `DistributionModuleTest.php:37-38`) sebagai bagian dari eksekusi Step 4 agar PHPUnit tetap `100% PASS` tanpa melemahkan assertion.
   - Evaluasi penambahan kolom `status_visibilitas` pada `monthly_origin_trade_summaries` dapat menjadi item opsional di fase berikutnya jika Viewer Eksekutif perlu melihat Origin `PRIVATE`.
3. **Batas `provorig_key` pada 9 Multi-Tx Cube di Tier-1:**
   - Pada `9` cube Dumai yang menggabungkan `2–4` transaksi ber-`PROVORIG` berbeda, kolom `monthly_trade_summaries.provorig_key` hanya merekam satu kode `PROVORIG` pertama. Analisis asal barang (`PROVORIG`) wajib tetap membaca `monthly_origin_trade_summaries` atau `distribusi_transactions` (tidak pernah dari `monthly_trade_summaries.provorig_key`).
4. **Cakupan Lingkungan `sidatex_testing` vs Operasional `sidatex`:**
   - Rencana ini hanya berlaku untuk `sidatex_testing`. Penerapan serupa ke database operasional `sidatex` di kemudian hari memerlukan migrasi skema (saat ini operasional baru di `26` migrasi dan belum memiliki `brs_indicator_summaries`) serta persetujuan terpisah yang sangat ketat.
5. **Re-Ingestion Tanpa Mengulang ID `#40`:**
   - Karena `batch_id` menggunakan `AUTO_INCREMENT` dan Batch `#40` telah dihapus permanen (log #73), re-ingestion Opsi R1 akan menghasilkan `batch_id` baru (mis. `#89`), bukan memaksa insert explicit `batch_id = 40`, guna menjaga integritas jejak audit `activity_logs`. Catatan referensi ke insiden historis Batch `#40` dicatat eksplisit pada `activity_logs` di Step 3.
