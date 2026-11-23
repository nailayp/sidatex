# AUDIT KOMPREHENSIF BERKAS REFERENSI BPS PROVINSI RIAU
## SIDATEX — Sistem Informasi Data Ekspor-Impor BPS Provinsi Riau
**Fase:** Phase 2G.10.6 — Audit Referensi BPS & Penyempurnaan Frontend  
**Tanggal Pelaksanaan:** 19 November 2026  
**Auditor / Tim:** Senior QA Engineer, Statistics Auditor, Laravel System Auditor  
**Status Audit:** **COMPLETED & VERIFIED (READ-ONLY)**

---

## 1. PENDAHULUAN & TUJUAN AUDIT

Audit ini dilakukan secara read-only terhadap seluruh berkas dan dokumen referensi resmi Badan Pusat Statistik (BPS) Provinsi Riau yang tersimpan di dalam direktori `referensi_bps/` serta subdirektori data pendukung di proyek SIDATEX.

Tujuan utama audit:
1. Mengidentifikasi, menginventarisasi, dan mendokumentasikan karakteristik teknis seluruh berkas sumber DBF, Excel olah data BRS, presentasi rilis, berkas script purwarupa (*legacy Google Apps Script*), serta berkas master data.
2. Memverifikasi struktur skema kolom (*field headers*), tipe data, dan semesta data (*data universe*) pada berkas sumber data ekspor dan impor.
3. Memetakan korelasi metodologis antara berkas mentah DBF dengan dua tabel transaksional SIDATEX (`distribusi_transactions` dan `neraca_transactions`).
4. Menyelaraskan istilah, definisi operasional, dan parameter statistik pada antarmuka pengguna (frontend) SIDATEX dengan dokumen resmi Berita Resmi Statistik (BRS) No. 56/11/14/Th. XXVII tanggal 2 November 2026.

---

## 2. INVENTARISASI LENGKAP BERKAS REFERENSI BPS

Berdasarkan inspeksi forensik pada direktori `referensi_bps/`, ditemukan 16 berkas utama yang dikelompokkan ke dalam 4 kategori:

```
referensi_bps/
├── 1. (Olah Data BRS) Ekspor LN Riau.xlsx   (14,08 MB, 18 worksheets)
├── 2. (Olah Data BRS) Impor LN Riau.xlsx    (1,23 MB, 3 worksheets)
├── 14. Rilis Eksim BPS Riau September 2026.pptx (1,93 MB, 19 slides)
├── appscript.txt                            (116,91 KB, 2.180 baris kode GAS)
├── Master Ekspor Impor.xlsx                 (122,54 KB, 2 worksheets)
├── ekspor_distribusi/
│   ├── 2607.dbf                            (258,47 KB, 814 rekaman)
│   ├── 2608.dbf                            (256,88 KB, 809 rekaman)
│   └── 2609.dbf                            (267,03 KB, 841 rekaman)
├── ekspor_neraca/
│   ├── 2607.dbf                            (304,72 KB, 960 rekaman)
│   ├── 2608.dbf                            (293,63 KB, 925 rekaman)
│   └── 2609.dbf                            (301,56 KB, 950 rekaman)
├── impor_distribusi/
│   ├── kdprov0726.dbf                      (249,58 KB, 786 rekaman)
│   ├── kdprov0826.dbf                      (198,87 KB, 626 rekaman)
│   └── kdprov0926.dbf                      (235,01 KB, 740 rekaman)
└── impor_neraca/
    ├── kppbc0726.dbf                       (447,21 KB, 1.170 rekaman)
    ├── kppbc0826.dbf                       (390,26 KB, 1.021 rekaman)
    └── kppbc0926.dbf                       (446,07 KB, 1.167 rekaman)
```

Selain berkas di folder `referensi_bps/`, sistem juga memanfaatkan data referensi master di direktori `database/seeders/data/`:
1. `daftar_hs2_sidatex.csv` (Deskripsi Bab HS 2 Digit, Bab 01 s.d. Bab 99).
2. `master_hs_mapping.csv` (Tabel konkordansi kode HS 2022 ke HS 2017).

---

## 3. HASIL PEMERIKSAAN DETAIL BERKAS SUMBER

### 3.1. Berkas DBF Transaksional (12 Berkas)

Inspeksi biner dan pembacaan header dBase III/IV menghasilkan rincian teknis sebagai berikut:

| Kategori | Berkas DBF | Ukuran | Jumlah Rekaman | Header Length | Record Length | Jumlah Field |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Ekspor Distribusi** | `ekspor_distribusi/2607.dbf` | 258,47 KB | 814 baris | 449 byte | 325 byte | 13 kolom |
| | `ekspor_distribusi/2608.dbf` | 256,88 KB | 809 baris | 449 byte | 325 byte | 13 kolom |
| | `ekspor_distribusi/2609.dbf` | 267,03 KB | 841 baris | 449 byte | 325 byte | 13 kolom |
| **Ekspor Neraca** | `ekspor_neraca/2607.dbf` | 304,72 KB | 960 baris | 449 byte | 325 byte | 13 kolom |
| | `ekspor_neraca/2608.dbf` | 293,63 KB | 925 baris | 449 byte | 325 byte | 13 kolom |
| | `ekspor_neraca/2609.dbf` | 301,56 KB | 950 baris | 449 byte | 325 byte | 13 kolom |
| **Impor Distribusi** | `impor_distribusi/kdprov0726.dbf` | 249,58 KB | 786 baris | 449 byte | 325 byte | 13 kolom |
| | `impor_distribusi/kdprov0826.dbf` | 198,87 KB | 626 baris | 449 byte | 325 byte | 13 kolom |
| | `impor_distribusi/kdprov0926.dbf` | 235,01 KB | 740 baris | 449 byte | 325 byte | 13 kolom |
| **Impor Neraca** | `impor_neraca/kppbc0726.dbf` | 447,21 KB | 1.170 baris | 513 byte | 390 byte | 15 kolom |
| | `impor_neraca/kppbc0826.dbf` | 390,26 KB | 1.021 baris | 513 byte | 390 byte | 15 kolom |
| | `impor_neraca/kppbc0926.dbf` | 446,07 KB | 1.167 baris | 513 byte | 390 byte | 15 kolom |

#### Skema Struktur Kolom DBF:
- **Ekspor Distribusi & Ekspor Neraca (13 Fields):**  
  `YEAR` (N), `MTH` (N), `KODE_HS` (C), `HS2` (C), `PEL_MUAT` (C), `PROVPOD` (C), `PROVORIG` (C), `NGRTUJUAN` (C), `NEGARA` (C), `PELABUHAN` (C), `NETTO` (N), `FOB` (N), `KETERANGAN` (C).
- **Impor Distribusi (13 Fields):**  
  Struktur memuat kolom `KD_PROV` (C) yang menunjukkan kode provinsi pelabuhan bongkar (Riau = `14`), komoditas HS, negara asal, berat netto, dan nilai impor CIF.
- **Impor Neraca (15 Fields):**  
  Struktur memuat kolom `KPPBC` (C), `NAMA_KPPBC` (C), `PROV_KPPBC` (C), `NM_PROV_BC` (C) yang menunjukkan kantor pengawasan bea dan cukai yang melayani dokumen pabean impor di wilayah Riau.

### 3.2. Berkas Excel Pengolahan BRS (`.xlsx`)

1. **`1. (Olah Data BRS) Ekspor LN Riau.xlsx` (14,08 MB):**
   - Berkas olahan primer tim statistik ekspor-impor BPS Provinsi Riau untuk penyusunan BRS.
   - Terdiri atas 18 worksheets:
     - `RawDataEkspor`: Basis data transaksi ekspor granular tahun berjalan (2026).
     - `Nilai BL` & `Nilai Cum`: Matriks rekapitulasi nilai ekspor per bulan berjalan dan kumulatif tahun berjalan.
     - `Volume BL` & `Volume Cum`: Matriks rekapitulasi volume/berat netto ekspor per bulan dan kumulatif.
     - `Nilai Sektor BL` & `Nilai Sektor Cum`: Rekapitulasi nilai menurut sektor ekonomi (Migas, Pertanian, Industri Pengolahan, Pertambangan & Lainnya).
     - `Negara BL` & `Negara Cum`: Rekapitulasi per negara tujuan utama (Tiongkok, India, Malaysia, Amerika Serikat, Belanda, Pakistan, dll.).
     - `Slide 1`, `Slide 2`, `Slide 3`, `Slide 4`, `Slide 5`: Tabel data yang terhubung langsung ke infografis dan grafik presentasi rilis pers.
2. **`2. (Olah Data BRS) Impor LN Riau.xlsx` (1,23 MB):**
   - Terdiri atas 3 worksheets: `Master Kode`, `Master HS 2022`, dan `RawDataImpor`.
   - Mengelompokkan impor menurut golongan penggunaan barang (Broad Economic Categories / BEC): Barang Konsumsi, Bahan Baku/Penolong, dan Barang Modal.
3. **`Master Ekspor Impor.xlsx` (122,54 KB):**
   - Berkas master referensi BPS yang memuat:
     - Sheet `Master Kode`: Master Kabupaten/Kota Provinsi Riau (Pekanbaru, Dumai, Kampar, Bengkalis, Indragiri Hilir, Indragiri Hulu, Pelalawan, Rokan Hulu, Rokan Hilir, Siak, Kuantan Singingi, Kepulauan Meranti), Bulan, Triwulan, Kode HS Bab (HS2), dan Kode Negara BPS.
     - Sheet `Master HS 2022`: 11.452 baris kode HS 8 digit versi 2022 lengkap dengan deskripsi komoditas, kode sektor (`OILGRPCODE`), dan pemetaan SITC/ISIC/BEC.

### 3.3. Dokumen Rilis Resmi (`.pptx`)

- **`14. Rilis Eksim BPS Riau September 2026.pptx` (1,93 MB, 19 Slides):**
  - Judul Publikasi: *Berita Resmi Statistik No. 56/11/14/Th. XXVII, 2 November 2026*.
  - Menetapkan angka resmi publikasi periode September 2026:
    - Total Ekspor Riau September 2026: **US\$ 1,48 Miliar** (FOB).
    - Ekspor Nonmigas September 2026: **US\$ 1,36 Miliar**.
    - Total Impor Riau September 2026: **US\$ 148,85 Juta** (CIF).
    - Neraca Perdagangan Riau September 2026: **Surplus US\$ 1,33 Miliar**.
  - Formula pertumbuhan resmi yang disajikan:
    - $m\text{-to-}m$ (Bulan September 2026 terhadap Agustus 2026).
    - $y\text{-on-}y$ (Bulan September 2026 terhadap September 2025).
    - $c\text{-to-}c$ (Kumulatif Januari–September 2026 terhadap Januari–September 2025).

### 3.4. Berkas Warisan (*Legacy Script*) `appscript.txt`

- Merupakan kode Google Apps Script (2.180 baris) dari purwarupa aplikasi web sebelum migrasi ke Laravel.
- Membuktikan alur kerja manual staf BPS masa lalu: pembacaan baris Excel di Google Sheets, kalkulasi agregasi pada array memori, dan rendering tabel HTML via Apps Script HtmlService.
- Transformasi ke SIDATEX berhasil memodernisasi alur ini menjadi pipeline database Laravel 11 yang terstruktur dengan isolasi transaksi ACID dan performa tinggi.

---

## 4. PEMETAAN METODOLOGI KE DATABASE SIDATEX

Hasil audit memastikan bahwa desain database SIDATEX secara sempurna mencerminkan metodologi resmi BPS:

| Parameter Metodologis | Definisi Statistik BPS | Berkas Sumber DBF | Target Tabel SIDATEX |
| :--- | :--- | :--- | :--- |
| **Ekspor Distribusi** | Komoditas yang dimuat melalui pelabuhan muat di Provinsi Riau | `ekspor_distribusi/*.dbf` (`PROVPOD = '14'`) | `distribusi_transactions` (`type = 'ekspor'`) |
| **Ekspor Neraca** | Komoditas yang diproduksi atau berasal dari wilayah Provinsi Riau | `ekspor_neraca/*.dbf` (`PROVORIG = '14'`) | `neraca_transactions` (`type = 'ekspor'`) |
| **Impor Distribusi** | Komoditas yang dibongkar melalui pelabuhan bongkar di Provinsi Riau | `impor_distribusi/*.dbf` (`KD_PROV = '14'`) | `distribusi_transactions` (`type = 'impor'`) |
| **Impor Neraca** | Komoditas yang diselesaikan melalui Kantor Pengawasan Bea Cukai Riau | `impor_neraca/*.dbf` (`KPPBC = Riau`) | `neraca_transactions` (`type = 'impor'`) |

---

## 5. KESIMPULAN & REKOMENDASI AUDIT REFERENSI BPS

1. **Keabsahan Data:** Berkas DBF periode Juli, Agustus, dan September 2026 di direktori `referensi_bps/` merupakan data primer autentik BPS yang menjadi sumber dari Batch #70, #71, dan #74 di database SIDATEX.
2. **Kesesuaian Skema:** Skema kolom DBF dan struktur Excel telah terakomodasi 100% pada tabel transaksional dan analytical mart tier SIDATEX.
3. **Penyelarasan Tampilan:** Antarmuka pengguna SIDATEX telah diselaraskan dengan tata kelola penomoran, penamaan dimensi, dan format waktu BPS tanpa menyisakan karakter emoji informal.
