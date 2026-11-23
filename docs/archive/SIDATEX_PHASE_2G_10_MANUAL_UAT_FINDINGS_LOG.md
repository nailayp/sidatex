# LEMBAR PENCATATAN TEMUAN MANUAL UAT (MANUAL UAT FINDINGS LOG)
## SISTEM INFORMASI DATA EKSPOR-IMPOR (SIDATEX)
### BADAN PUSAT STATISTIK (BPS) PROVINSI RIAU

**Kode Dokumen:** `SIDATEX-DOC-UAT-FINDINGS-2G10`  
**Versi Dokumen:** 1.0  
**Tanggal Terbit:** 19 November 2026  
**Klasifikasi:** Dokumen Pencatatan Log Anomali & Defect Pengujian Lapangan  
**Status Kesiapan:** `MANUAL UAT PACKAGE PREPARED — PENDING BPS FIELD EXECUTION`

---

## 1. PETUNJUK BAGI STAF PENGUJI BPS

> [!IMPORTANT]
> 1. Dokumen ini berfungsi sebagai buku log resmi pencatatan anomali, ketidaksesuaian tampilan, galat fungsional, atau deviasi statistik yang ditemukan selama pengujian manual berlangsung.
> 2. Bagian **Tabel Temuan Lapangan Manual Penguji BPS** sengaja dibiarkan **KOSONG** untuk diisi secara langsung oleh staf penguji BPS saat sesi pengujian lapangan berlangsung.
> 3. Setiap temuan baru wajib diberi identitas bertahap: `BUG-UAT-001`, `BUG-UAT-002`, dst., serta dilampiri bukti tangkapan layar (*screenshot*) atau rekaman pesan galat (*console log*).
> 4. Jangan menggabungkan atau mengubah kode temuan historis **F-01 s.d. F-07** yang merupakan temuan audit sistem tingkat pengembang.

---

## 2. KLASIFIKASI TINGKAT KEPARAHAN (SEVERITY)

Setiap temuan yang dicatat oleh staf BPS wajib dikelompokkan ke dalam salah satu tingkat keparahan berikut:

| Tingkat Keparahan | Kriteria & Dampak Operasional BPS | Contoh Kasus |
|---|---|---|
| **Critical** | Sistem lumpuh (*crash*), data mart hilang/rusak, kebocoran data PRIVATE ke publik, atau celah keamanan kritis. | Kesalahan server 500 fatal yang tidak dapat dipulihkan, data produksi terhapus tanpa otorisasi. |
| **High** | Fitur inti tidak berfungsi, rumus statistik makro menghasilkan angka salah, atau proses upload terhenti total. | Perhitungan neraca perdagangan salah total, angka ekspor tidak cocok dengan data sumber DBF. |
| **Medium** | Fungsionalitas berjalan tetapi terdapat anomali minor, respon lambat, atau teks penjelasan membingungkan. | Tooltip tidak muncul pada resolusi kecil, filter periode membutuhkan muat ulang manual. |
| **Low** | Kesalahan kosmetik ringan, format penulisan desimal tidak seragam, atau kesalahan ketik pada label. | Typo pada judul sub-tabel, perataan teks angka rata kiri bukan rata kanan. |
| **Informational** | Saran penyempurnaan fitur atau usulan alur kerja baru di masa mendatang yang di luar spesifikasi awal. | Usulan penambahan format ekspor CSV atau pengelompokan komoditas kustom BRS. |

---

## 3. STATUS TINDAK LANJUT TEMUAN (LIFECYCLE STATUS)

- **Open:** Temuan baru dicatat oleh penguji BPS dan menunggu peninjauan tim teknis.
- **Under Review:** Temuan sedang ditinjau oleh Koordinator UAT dan tim pengembang untuk verifikasi langkah reproduksi.
- **Deferred:** Temuan disepakati untuk ditunda perbaikannya ke fase pemeliharaan berikutnya karena tidak menghambat rilis inti.
- **Resolved:** Perbaikan telah diimplementasikan oleh tim pengembang dan lulus uji regresi teknis.
- **Closed:** Perbaikan telah diverifikasi ulang oleh penguji BPS dan dinyatakan tuntas.

---

## 4. BASELINE REKONSILIASI TEMUAN AUDIT TEKNIS SEBELUMNYA (F-01 S.D. F-07)

Sebagai konteks bagi penguji BPS, berikut adalah status resmi temuan audit teknis dari Phase 2G.6 sampai Phase 2G.9:

| Kode | Deskripsi Temuan Audit Awal | Severity | Status Resmi & Catatan Rekonsiliasi |
|:---:|---|:---:|---|
| **F-01** | Alur unggah Excel sekunder tidak memicu `BrsAggregationService` | Medium | **Deferred technical debt**; alur utama operasional DBF berjalan normal dan teragregasi penuh. Keputusan penghapusan/penyempurnaan alur Excel menunggu kebijakan tertulis BPS. |
| **F-02** | Perbedaan klasifikasi/filter Migas Bab 27 antara `DashboardController` (`hs2 = '27'`) dan `DistributionStatisticsService` (`oilgrp_code = 1`) | Medium | **Technical safety freeze**; kode implementasi dibekukan demi kestabilan runtime sambil menunggu ketetapan tertulis metodologi klasifikasi BPS Riau. |
| **F-03** | Window kumulatif Neraca Perdagangan (*Trade Balance*) asimetris | Low | **Resolved & verified Phase 2G.8**; sistem telah dilengkapi banner peringatan warna kuning/amber (`#alertTradeAsymmetry`) dan penanganan nilai kosong. |
| **F-04** | Proteksi rute `/activity-logs` mengandalkan controller guard, bukan route middleware | Low | **Deferred post-UAT**; perlindungan di tingkat controller terbukti *fail-closed* menolak akses non-admin dengan HTTP 403. Penambahan middleware dijadwalkan pasca-UAT. |
| **F-05** | Tooltip metodologi Distribusi vs Neraca belum tersedia | Informational | **Resolved & verified Phase 2G.8**; popover tooltip interaktif (`#methodologyTooltipPopover`) telah aktif di dashboard. |
| **F-06** | Kebijakan Trash tanpa *auto-purge* membutuhkan SOP pembersihan manual Super Admin | Informational | **Informational**; bukan bug kode, melainkan kebutuhan penyusunan Standar Operasional Prosedur (SOP) retensi data internal BPS. |
| **F-07** | Kode `HS 270000` memiliki `oilgrp_code = NULL` pada tabel master | Informational | **Informational**; kode komoditas dummy pada master referensi dengan 0 transaksi, tanpa dampak statistik. Evaluasi master data dijadwalkan pasca-UAT. |

---

## 5. TABEL PENCATATAN TEMUAN MANUAL LAPANGAN PENGUJI BPS

*(Tabel di bawah ini sengaja dibiarkan KOSONG dan siap digunakan oleh staf penguji BPS Provinsi Riau)*

| ID Temuan | Tanggal | Role Penguji | Modul | Deskripsi Temuan / Anomali | Langkah Reproduksi | Bukti (Screenshot / Log) | Tingkat Keparahan (Severity) | Rekomendasi Solusi | Status Tindak Lanjut |
|---|---|---|---|---|---|---|---|---|---|
| *(Contoh)* | *19/11/2026* | *Pegawai* | *MOD-DASH* | *Grafik tren tidak memuat legenda ekspor* | *Buka tab 1, klik filter 2026* | *TC-PG-02_chart_err.png* | *Medium* | *Perbaiki inisialisasi legenda* | *Open* |
| | | | | | | | | | |
| | | | | | | | | | |
| | | | | | | | | | |
| | | | | | | | | | |
| | | | | | | | | | |
| | | | | | | | | | |
| | | | | | | | | | |
| | | | | | | | | | |
| | | | | | | | | | |

*Catatan: Tambahkan baris baru sesuai dengan jumlah temuan yang ditemukan selama sesi pengujian.*

---

## 6. REKAPITULASI TEMUAN UAT MANUAL LAPANGAN
*(Bagian ini dirangkum oleh Koordinator UAT BPS setelah sesi pengujian berakhir)*

| Tingkat Keparahan (Severity) | Jumlah Temuan Terbuka (Open) | Jumlah Temuan Ditunda (Deferred) | Jumlah Temuan Selesai (Closed) | Total Temuan |
|---|:---:|:---:|:---:|:---:|
| **Critical** | | | | |
| **High** | | | | |
| **Medium** | | | | |
| **Low** | | | | |
| **Informational** | | | | |
| **TOTAL** | | | | |

<br>

**Tanggal Rangkuman Temuan:** `____ / ____________ / 2026`  
**Koordinator Pengujian BPS Provinsi Riau:** `________________________________________` (Tanda Tangan)
