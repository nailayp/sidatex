# FORMULIR PENILAIAN PENGGUNA (MANUAL UAT FEEDBACK FORM)
## SISTEM INFORMASI DATA EKSPOR-IMPOR (SIDATEX)
### BADAN PUSAT STATISTIK (BPS) PROVINSI RIAU

**Kode Dokumen:** `SIDATEX-DOC-UAT-FEEDBACK-2G10`  
**Versi Dokumen:** 1.0  
**Tanggal Terbit:** 19 November 2026  
**Klasifikasi:** Instrumen Evaluasi Kepuasan Pengguna Internal BPS  
**Status Kesiapan:** `MANUAL UAT PACKAGE PREPARED — PENDING BPS FIELD EXECUTION`

---

## PETUNJUK PENGISIAN BAGI PENGUJI BPS

> [!NOTE]
> 1. Formulir ini ditujukan untuk mengumpulkan evaluasi objektif, pengalaman langsung, dan masukan konstruktif dari staf dan pimpinan BPS Provinsi Riau yang bertindak sebagai penguji pengguna (*user acceptance tester*).
> 2. Seluruh isian data diri, nilai skala, jawaban pertanyaan terbuka, dan tanda tangan pada formulir ini sengaja disediakan dalam kondisi **KOSONG** untuk diisi secara mandiri oleh staf penguji BPS setelah menyelesaikan sesi pengujian.
> 3. Mohon berikan penilaian secara jujur dan transparan demi penyempurnaan kualitas sistem informasi statistik perdagangan luar negeri Provinsi Riau.

---

## 1. PROFIL PENGUJI BPS

| Data Penguji | Isian Penguji BPS |
|---|---|
| **Nama Lengkap Penguji** | `__________________________________________________` |
| **NIP (Nomor Induk Pegawai)** | `__________________________________________________` |
| **Unit Kerja / Fungsi** | `[ ] Fungsi Statistik Distribusi`<br>`[ ] Fungsi Neraca Wilayah dan Analisis Statistik`<br>`[ ] Fungsi IPDS (Integrasi Pengolahan & Diseminasi Statistik)`<br>`[ ] Bagian Umum / Pimpinan`<br>`[ ] Lainnya: _________________________` |
| **Peran Akun saat UAT** | `[ ] Super Admin (Role 1)`<br>`[ ] Pegawai BPS (Role 2)`<br>`[ ] Viewer Eksekutif (Role 3)` |
| **Tanggal Pengujian** | `____ / ____________ / 2026` |
| **Peramban yang Digunakan** | `[ ] Google Chrome   [ ] Microsoft Edge   [ ] Mozilla Firefox` |

---

## 2. KUESIONER EVALUASI SISTEM (SKALA LIKERT 1 – 5)

**Keterangan Skala Penilaian:**
- **1** = Sangat Tidak Setuju (STS)
- **2** = Tidak Setuju (TS)
- **3** = Cukup / Netral (C)
- **4** = Setuju (S)
- **5** = Sangat Setuju (SS)

### A. Kemudahan Penggunaan & Navigasi (Usability & Navigation)

| No | Pernyataan Evaluasi | 1 (STS) | 2 (TS) | 3 (C) | 4 (S) | 5 (SS) |
|:---:|---|:---:|:---:|:---:|:---:|:---:|
| 1 | Antarmuka aplikasi SIDATEX mudah dipahami dan navigasi antarmenu berjalan lancar. | [ ] | [ ] | [ ] | [ ] | [ ] |
| 2 | Proses login, pergantian menu, dan logout berjalan cepat tanpa kendala teknis. | [ ] | [ ] | [ ] | [ ] | [ ] |
| 3 | Struktur sidebar dan pengelompokan fitur sesuai dengan peran pengguna saya. | [ ] | [ ] | [ ] | [ ] | [ ] |

### B. Kejelasan Tampilan Dashboard & Visualisasi Data (Visual Clarity & Charts)

| No | Pernyataan Evaluasi | 1 (STS) | 2 (TS) | 3 (C) | 4 (S) | 5 (SS) |
|:---:|---|:---:|:---:|:---:|:---:|:---:|
| 4 | Kartu metrik ringkasan nilai ekspor (FOB) dan impor (CIF) menyajikan angka dengan format yang jelas dan mudah dibaca. | [ ] | [ ] | [ ] | [ ] | [ ] |
| 5 | Grafik tren perdagangan bulanan (Chart.js) interaktif, informatif, dan membantu memahami tren data makro. | [ ] | [ ] | [ ] | [ ] | [ ] |
| 6 | Peta interaktif Leaflet.js membantu memvisualisasikan persebaran volume ekspor/impor di pelabuhan Riau secara efektif. | [ ] | [ ] | [ ] | [ ] | [ ] |

### C. Kesesuaian Informasi Statistik & Integritas Metodologi BPS

| No | Pernyataan Evaluasi | 1 (STS) | 2 (TS) | 3 (C) | 4 (S) | 5 (SS) |
|:---:|---|:---:|:---:|:---:|:---:|:---:|
| 7 | Angka statistik ekspor-impor yang disajikan sesuai dengan kaidah perhitungan resmi Berita Resmi Statistik (BRS). | [ ] | [ ] | [ ] | [ ] | [ ] |
| 8 | Nilai pertumbuhan (MoM, YoY) terhitung tepat dan menampilkan strip `—` saat data tidak tersedia (tidak muncul angka palsu `-100%`). | [ ] | [ ] | [ ] | [ ] | [ ] |
| 9 | Perhitungan Neraca Perdagangan (Trade Balance = Ekspor FOB - Impor CIF) pada Tab 4 akurat dan status Surplus/Defisit tepat. | [ ] | [ ] | [ ] | [ ] | [ ] |

### D. Pemahaman Metodologi Distribusi vs Neraca & Edukasi Tooltip (F-03 & F-05)

| No | Pernyataan Evaluasi | 1 (STS) | 2 (TS) | 3 (C) | 4 (S) | 5 (SS) |
|:---:|---|:---:|:---:|:---:|:---:|:---:|
| 10 | Tombol pemisah metodologi Distribusi dan Neraca mempermudah pemilahan semesta data statistik. | [ ] | [ ] | [ ] | [ ] | [ ] |
| 11 | Tooltip interaktif (F-05) memberikan penjelasan yang gamblang mengenai perbedaan cakupan pelabuhan (POD/KDPROV) dan asal barang (PROVORIG/KPPBC). | [ ] | [ ] | [ ] | [ ] | [ ] |
| 12 | Kotak peringatan asimetri periode kumulatif (F-03) pada Tab 4 membantu mencegah salah tafsir neraca kumulatif saat rentang data tidak setara. | [ ] | [ ] | [ ] | [ ] | [ ] |

### E. Fleksibilitas Filter & Kemudahan Unduh Data (Filtering & Export)

| No | Pernyataan Evaluasi | 1 (STS) | 2 (TS) | 3 (C) | 4 (S) | 5 (SS) |
|:---:|---|:---:|:---:|:---:|:---:|:---:|
| 13 | Filter tahun, bulan, komoditas, dan negara mitra bekerja responsif dan memperbarui data secara akurat. | [ ] | [ ] | [ ] | [ ] | [ ] |
| 14 | Fitur unduh berkas spreadsheet (Excel) dan slide presentasi (PowerPoint) menghasilkan dokumen yang rapi dan siap pakai. | [ ] | [ ] | [ ] | [ ] | [ ] |

### F. Kesesuaian Sistem dengan Kebutuhan Kerja BPS (Business Fit)

| No | Pernyataan Evaluasi | 1 (STS) | 2 (TS) | 3 (C) | 4 (S) | 5 (SS) |
|:---:|---|:---:|:---:|:---:|:---:|:---:|
| 15 | SIDATEX secara keseluruhan sangat mendukung efisiensi penyusunan BRS dan diseminasi data ekspor-impor di BPS Provinsi Riau. | [ ] | [ ] | [ ] | [ ] | [ ] |

---

## 3. EVALUASI KUALITATIF & MASUKAN PENGUJI (PERTANYAAN TERBUKA)

### 3.1 Kendala yang Ditemukan saat Pengujian
*Jelaskan kendala teknis, kebingungan antarmuka, kelambatan data, atau pesan kesalahan yang Anda jumpai:*
```text
____________________________________________________________________________________
____________________________________________________________________________________
____________________________________________________________________________________
____________________________________________________________________________________
```

### 3.2 Fitur yang Paling Bermanfaat bagi Pekerjaan Anda
*Sebutkan modul atau fitur SIDATEX yang paling membantu tugas analisis Anda sehari-hari:*
```text
____________________________________________________________________________________
____________________________________________________________________________________
____________________________________________________________________________________
```

### 3.3 Fitur atau Bagian yang Perlu Disempurnakan
*Sebutkan fitur yang menurut Anda perlu disempurnakan tampilannya atau logikanya:*
```text
____________________________________________________________________________________
____________________________________________________________________________________
____________________________________________________________________________________
```

### 3.4 Saran Pengembangan untuk Masa Depan
*Tuliskan ide atau saran fitur tambahan yang bermanfaat bagi BPS Provinsi Riau ke depan:*
```text
____________________________________________________________________________________
____________________________________________________________________________________
____________________________________________________________________________________
```

---

## 4. KESIMPULAN REKOMENDASI PENGUJI LAPANGAN BPS

Pilih salah satu kesimpulan evaluasi subjektif Anda terhadap sistem SIDATEX:

- [ ] **SISTEM DITERIMA PENUH (FULL ACCEPTANCE):** Sistem telah memenuhi kebutuhan operasional dan siap digunakan.
- [ ] **SISTEM DITERIMA DENGAN CATATAN MINOR (ACCEPTANCE WITH MINOR NOTES):** Sistem dapat digunakan, namun catatan perbaikan non-kritis perlu dijadwalkan pada pemeliharaan berkala.
- [ ] **SISTEM MEMERLUKAN PERBAIKAN SEBELUM RILIS (REMEDIATION REQUIRED):** Ditemukan kendala substansial yang wajib diperbaiki terlebih dahulu sebelum sistem dapat diterima.
- [ ] **PENGUJIAN BELUM LENGKAP (INCOMPLETE TESTING):** Diperlukan sesi pengujian lanjutan untuk menguji skenario tertentu.

---

## 5. PENGESAHAN LEMBAR EVALUASI

*(Lembar ini ditandatangani oleh staf penguji dan koordinator setelah seluruh bagian di atas diisi secara manual)*

<br>

| Diserahkan oleh Penguji BPS: | Diketahui oleh Koordinator UAT BPS: |
|:---:|:---:|
| <br><br><br>________________________________________<br>**( Nama Penguji BPS )**<br>NIP: ______________________________ | <br><br><br>________________________________________<br>**( Koordinator Pengujian UAT )**<br>NIP: ______________________________ |
| Tanggal: `____ / ____________ / 2026` | Tanggal: `____ / ____________ / 2026` |
