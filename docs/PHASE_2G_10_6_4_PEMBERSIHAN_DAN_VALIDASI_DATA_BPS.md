# LAPORAN PENGOLAHAN DBF DAN REKONSILIASI EXCEL BPS
## FASE 2G.10.6.4 &mdash; SISTEM INFORMASI DATA EKSPOR-IMPOR BPS PROVINSI RIAU

**Tanggal:** 20 November 2026  
**Status Rekonsiliasi:** 100% MATCH &mdash; VERIFIED AGAINST OFFICIAL BPS EXCEL  
**Basis Data:** `sidatex` (Tabel `data_batches`, `distribusi_transactions`, `neraca_transactions`, `monthly_trade_summaries`, `brs_indicator_summaries`)  

---

## 1. PENDAHULUAN

Sesuai instruksi Fase 2G.10.6.4, seluruh data transaksi operasional lama telah dikosongkan untuk memulai siklus pengolahan data baru langsung dari berkas mentah DBF BPS yang tersimpan di direktori `referensi_bps/`.

Sistem SIDATEX melalui `DbfIngestionService`, `DbfUploadValidationService`, dan `BrsAggregationService` melakukan parsing biner DBF, validasi format, pemetaan kode HS terhadap master data, pengelompokan sektor, perhitungan berat bersih serta nilai moneter (FOB/CIF), dan pembentukan Two-Tier Analytical Mart secara atomik.

Hasil pengolahan kemudian dicocokkan secara menyeluruh (*deep reconciliation*) terhadap lembar kerja resmi BPS:
- `referensi_bps/1. (Olah Data BRS) Ekspor LN Riau.xlsx`
- `referensi_bps/2. (Olah Data BRS) Impor LN Riau.xlsx`
- `referensi_bps/14. Rilis Eksim BPS Riau September 2026.pptx`

---

## 2. INVENTARISASI 12 BATCH DATA MENTAH DBF

Sebanyak 12 berkas DBF mentah dari kuadran Distribusi dan Neraca untuk periode Juli (07), Agustus (08), dan September (09) tahun 2026 berhasil diproses dan disimpan ke database `sidatex`:

| Batch ID | Nama Berkas DBF | Metodologi | Jenis Data | Periode | Status | Baris Transaksi | Total Nilai (USD) | Total Berat (Kg) |
| :---: | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| **#1** | `14_Atap_ekspor_2607_POD_based.DBF` | Distribusi | Ekspor | 07/2026 | RILIS | 814 | $1.910.605.444,96 | 2.215.321.722,08 |
| **#2** | `14_Atap_ekspor_2608_POD_based.DBF` | Distribusi | Ekspor | 08/2026 | RILIS | 809 | $2.218.155.995,50 | 2.352.091.737,40 |
| **#3** | `14_Atap_ekspor_2609_POD_based.DBF` | Distribusi | Ekspor | 09/2026 | RILIS | 841 | $2.301.809.204,59 | 2.553.373.143,52 |
| **#4** | `14_Atap_ekspor_2607_PROVORIG_based.DBF`| Neraca | Ekspor | 07/2026 | RILIS | 960 | $1.914.887.625,97 | 2.219.008.064,28 |
| **#5** | `14_Atap_ekspor_2608_PROVORIG_based.DBF`| Neraca | Ekspor | 08/2026 | RILIS | 925 | $2.228.618.396,68 | 2.361.347.535,40 |
| **#6** | `14_Atap_ekspor_2609_PROVORIG_based.DBF`| Neraca | Ekspor | 09/2026 | RILIS | 950 | $2.319.873.512,70 | 2.569.213.921,80 |
| **#7** | `14_impor_atap_kdprov_0726.DBF` | Distribusi | Impor | 07/2026 | RILIS | 989 | $199.716.065,39 | 777.854.496,00 |
| **#8** | `14_impor_atap_kdprov_0826.DBF` | Distribusi | Impor | 08/2026 | RILIS | 785 | $167.347.655,08 | 568.583.743,00 |
| **#9** | `14_impor_atap_kdprov_0926.DBF` | Distribusi | Impor | 09/2026 | RILIS | 931 | $215.260.644,47 | 578.142.877,79 |
| **#10**| `14_impor_atap_kppbc_0726.DBF` | Neraca | Impor | 07/2026 | RILIS | 1.169 | $206.879.431,54 | 794.135.216,00 |
| **#11**| `14_impor_atap_kppbc_0826.DBF` | Neraca | Impor | 08/2026 | RILIS | 1.021 | $175.760.334,06 | 582.411.782,00 |
| **#12**| `14_impor_atap_kppbc_0926.DBF` | Neraca | Impor | 09/2026 | RILIS | 1.167 | $227.435.817,96 | 592.515.653,79 |

### Ringkasan Akumulasi Tingkat Transaksional:
- **Total Transaksi Aktif:** 11.361 baris
  - `distribusi_transactions`: 5.169 baris
  - `neraca_transactions`: 6.192 baris
- **Total Agregasi Mart Bulanan (`monthly_trade_summaries`):** 11.155 baris analitik
- **Total Ringkasan Indikator Makro (`brs_indicator_summaries`):** 504 baris indikator
- **Unmapped Pelabuhan:** 0
- **Unmapped Negara:** 0
- **Unmapped Komoditas:** 0
- **Orphan Aggregations:** 0 (Seluruh agregasi memiliki induk transaksi faktual)

---

## 3. HASIL PENCOCOKAN DENGAN EXCEL RESMI BPS

### 3.1. Rekonsiliasi Ekspor Distribusi (FOB USD)
Membandingkan output agregasi SIDATEX dengan Sheet `Nilai Bl` dan `Volume Bl` pada berkas `1. (Olah Data BRS) Ekspor LN Riau.xlsx`:

| Periode | Klasifikasi | Nilai Excel BPS | Nilai SIDATEX | Selisih | Status |
| :--- | :--- | :---: | :---: | :---: | :---: |
| **Juli 2026** | Migas | US$ 36,80 Juta | US$ 36,80 Juta | $0,00 | **SESUAI (100%)** |
| | Non-Migas | US$ 1.873,81 Juta | US$ 1.873,81 Juta | $0,00 | **SESUAI (100%)** |
| | **Total Ekspor** | **US$ 1.910,61 Juta** | **US$ 1.910,61 Juta** | **$0,00** | **SESUAI (100%)** |
| **Agustus 2026**| Migas | US$ 49,52 Juta | US$ 49,52 Juta | $0,00 | **SESUAI (100%)** |
| | Non-Migas | US$ 2.168,64 Juta | US$ 2.168,64 Juta | $0,00 | **SESUAI (100%)** |
| | **Total Ekspor** | **US$ 2.218,16 Juta** | **US$ 2.218,16 Juta** | **$0,00** | **SESUAI (100%)** |
| **September 2026**| Migas | US$ 19,57 Juta | US$ 19,57 Juta | $0,00 | **SESUAI (100%)** |
| | Non-Migas | US$ 2.282,24 Juta | US$ 2.282,24 Juta | $0,00 | **SESUAI (100%)** |
| | **Total Ekspor** | **US$ 2.301,81 Juta** | **US$ 2.301,81 Juta** | **$0,00** | **SESUAI (100%)** |

### 3.2. Rekonsiliasi Impor Distribusi (CIF USD)
Membandingkan output agregasi SIDATEX dengan lembar kerja `2. (Olah Data BRS) Impor LN Riau.xlsx`:

| Periode | Klasifikasi | Nilai Excel BPS | Nilai SIDATEX | Selisih | Status |
| :--- | :--- | :---: | :---: | :---: | :---: |
| **Juli 2026** | Migas | US$ 15,94 Juta | US$ 15,94 Juta | $0,00 | **SESUAI (100%)** |
| | Non-Migas | US$ 183,78 Juta | US$ 183,78 Juta | $0,00 | **SESUAI (100%)** |
| | **Total Impor** | **US$ 199,72 Juta** | **US$ 199,72 Juta** | **$0,00** | **SESUAI (100%)** |
| **Agustus 2026**| Migas | US$ 8,87 Juta | US$ 8,87 Juta | $0,00 | **SESUAI (100%)** |
| | Non-Migas | US$ 158,47 Juta | US$ 158,47 Juta | $0,00 | **SESUAI (100%)** |
| | **Total Impor** | **US$ 167,35 Juta** | **US$ 167,35 Juta** | **$0,00** | **SESUAI (100%)** |
| **September 2026**| Migas | US$ 9,41 Juta | US$ 9,41 Juta | $0,00 | **SESUAI (100%)** |
| | Non-Migas | US$ 205,86 Juta | US$ 205,86 Juta | $0,00 | **SESUAI (100%)** |
| | **Total Impor** | **US$ 215,26 Juta** | **US$ 215,26 Juta** | **$0,00** | **SESUAI (100%)** |

### 3.3. Neraca Perdagangan / Trade Balance (Surplus/Defisit)
$$\text{Neraca Perdagangan} = \text{Total Ekspor (FOB)} - \text{Total Impor (CIF)}$$

| Periode Data | Total Ekspor Distribusi | Total Impor Distribusi | Neraca Perdagangan | Status Neraca |
| :--- | :---: | :---: | :---: | :---: |
| **Juli 2026** | US$ 1.910,61 Juta | US$ 199,72 Juta | **+US$ 1.710,89 Juta** | SURPLUS |
| **Agustus 2026** | US$ 2.218,16 Juta | US$ 167,35 Juta | **+US$ 2.050,81 Juta** | SURPLUS |
| **September 2026** | US$ 2.301,81 Juta | US$ 215,26 Juta | **+US$ 2.086,55 Juta** | SURPLUS |
| **Kumulatif Triwulan III (Q3)** | **US$ 6.430,57 Juta** | **US$ 582,32 Juta** | **+US$ 5.848,25 Juta** | **SURPLUS KUAT** |

---

## 4. PERBEDAAN METODOLOGI DISTRIBUSI VS NERACA

Sesuai aturan baku SIDATEX, data Distribusi dan Neraca tidak pernah dicampur aduk. Tabel berikut membuktikan perbedaan data universe antara cakupan pintu keluar (Distribusi) dan asal komoditas (Neraca):

| Indikator (Sep 2026) | Metodologi Distribusi (POD/KDPROV) | Metodologi Neraca (PROVORIG/KPPBC) | Karakteristik Perbedaan |
| :--- | :---: | :---: | :--- |
| **Total Nilai Ekspor** | US$ 2.301,81 Juta | US$ 2.319,87 Juta | Neraca mencakup barang asal Riau yang diekspor melalui pelabuhan luar Riau. |
| **Total Nilai Impor** | US$ 215,26 Juta | US$ 227,44 Juta | Neraca mencakup barang yang diawasi oleh KPPBC Riau. |
| **Jumlah Transaksi Ekspor**| 841 baris | 950 baris | Transaksi Neraca lebih banyak karena menjangkau pergerakan lintas provinsi. |
| **Jumlah Transaksi Impor** | 931 baris | 1.167 baris | Transaksi kantor pabean Riau mencakup barang transit kawasan pabean. |

---

## 5. KESIMPULAN REKONSILIASI

1. Seluruh 12 berkas DBF mentah telah diolah menjadi data bersih dengan integritas 100%.
2. Tidak ditemukan perbedaan (*zero drift*) antara hasil kalkulasi agregasi SIDATEX dengan Excel BRS resmi BPS Provinsi Riau.
3. Seluruh angka yang ditampilkan pada Single Dashboard kini bersumber langsung dari data transaksi aktual operasional di database `sidatex`.
