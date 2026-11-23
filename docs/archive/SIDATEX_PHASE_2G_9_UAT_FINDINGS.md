# SIDATEX — PHASE 2G.9 UAT FINDINGS LOG
**Sistem Informasi Data Ekspor-Impor BPS Provinsi Riau**
**Document Version:** 1.0 (Final Technical UAT)
**Execution Date:** 19 November 2026

---

## 1. Ringkasan Eksekutif Temuan

Berdasarkan audit komprehensif, eksekusi 77 skenario pengujian matriks UAT lintas-peran (Super Admin, Pegawai, Viewer Eksekutif), serta pengujian unit/feature 180 tests (1.527 assertions):
- **Temuan Kritis (Critical):** 0
- **Temuan Tinggi (High):** 0
- **Temuan Sedang (Medium):** 1 (F-02: Kebijakan Klasifikasi HS 27075000 Menunggu Ketetapan Resmi BPS)
- **Temuan Rendah (Low):** 1 (F-01: Penundaan Parsing Excel, Pipeline DBF Utama Aktif)
- **Temuan Informasi (Informational):** 2 (F-06 SOP Backup Operasional, F-07 Audit Trail Reagregasi)
- **Temuan yang Telah Terselesaikan (Resolved):** 3 (F-03 Asimetri Trade Balance, F-04 Fail-Closed Guard, F-05 Tooltip Metodologi)

---

## 2. Rincian Temuan & Status Remediasi

### F-01: Excel File Parsing (Penundaan Parsing Excel, Pipeline DBF Primer Aktif)
- **Kategori:** Usability & Ingestion Architecture
- **Tingkat Keparahan:** Low / Informational
- **Status Operasional:** DEFERRED BY DESIGN
- **Deskripsi:** Jalur pengunggahan data primer SIDATEX secara historis dan operasional BPS menggunakan file mentah DBF (`.dbf`) hasil ekstraksi sistem kepabeanan (CEISA/Bea Cukai). Dukungan pengunggahan langsung file Excel (`.xlsx`) ditunda untuk memprioritaskan integritas skema data mentah DBF Distribusi dan Neraca.
- **Dampak Bisnis:** Tidak mengganggu operasional BPS karena alur data resmi BPS menggunakan file DBF dari Pusat/Bea Cukai.
- **Rekomendasi:** Pertahankan pipeline DBF sebagai jalur produksi utama. Modul Excel dapat dikembangkan pada roadmap lanjutan apabila BPS menetapkan template Excel baku.

---

### F-02: Kebijakan Klasifikasi Komoditas HS 27075000 (Non-Migas vs Migas)
- **Kategori:** Statistical Classification & Master Data Policy
- **Tingkat Keparahan:** Medium
- **Status Operasional:** FROZEN BY POLICY — PENDING FORMAL BPS COMMITTEE RULING
- **Deskripsi:** Kode HS `27075000` (*Oth aromatic hydrocarbon mixtures...*) pada Master Komoditas BPS memiliki nilai `oilgrp_code = 0` (Non-Migas) dan `sektor = INDUSTRI PENGOLAHAN`. Walaupun secara kode 2-digit (`HS 27`) masuk dalam kelompok Bahan Bakar Mineral, klasifikasi BPS menetapkannya sebagai Non-Migas karena merupakan produk kimia hilir industri pengolahan.
- **Dampak Bisnis:** Penetapan ini konsisten dengan publikasi resmi BRS BPS Provinsi Riau historis. Mengubah nilai ini secara sepihak akan merusak komparabilitas angka BRS masa lalu.
- **Rekomendasi:** Pertahankan nilai `oilgrp_code = 0` dan catat secara permanen sebagai ketetapan baku metodologis sampai ada instruksi tertulis baru dari Tim Diseminasi & Statistik Distribusi BPS Provinsi Riau.

---

### F-03: Asimetri Periode Kumulatif Ekspor vs Impor pada Trade Balance
- **Kategori:** Statistical Visualization & Reporting
- **Tingkat Keparahan:** Medium
- **Status Operasional:** RESOLVED & VERIFIED IN PHASE 2G.8
- **Deskripsi:** Pada saat batch Ekspor telah tersedia hingga bulan $M$ sedangkan Impor baru tersedia hingga bulan $M-k$, angka kumulatif neraca perdagangan berpotensi menyesatkan jika pembaca mengira kedua angka mencakup rentang bulan yang sama.
- **Solusi Remediasi:** 
  1. Backend `DashboardController.php` mendeteksi ketidaksamaan periode kumulatif (`is_asymmetric_cumulative = true`).
  2. Blade `dashboard.blade.php` menampilkan banner peringatan amber interaktif yang menjelaskan bahwa periode ekspor dan impor memiliki rentang bulan yang berbeda.
- **Status Verifikasi:** 100% Lolos pada pengujian Phase 2G.8 dan diverifikasi ulang pada skenario SA-16, PG-15, dan VE-14.

---

### F-04: Fail-Closed Security Guard pada DistributionStatisticsService
- **Kategori:** RBAC Security & Data Isolation
- **Tingkat Keparahan:** High (Sebelumnya) -> RESOLVED
- **Status Operasional:** RESOLVED & VERIFIED IN PHASE 2G.3 & 2G.5
- **Deskripsi:** Metode pembacaan ringkasan statistik berisiko mengekspos data `PRIVATE` atau `Trash` jika pemanggil tidak menyuplai filter status secara eksplisit.
- **Solusi Remediasi:** Menerapkan prinsip *fail-closed*: secara baku hanya membaca batch berstatus `RILIS` (`tipe_sumber = distribusi`, non-trash). Hak akses untuk membaca `PRIVATE` dibatasi hanya untuk Super Admin dan Viewer Eksekutif melalui penyaringan ketat.
- **Status Verifikasi:** 100% Lolos pada skenario PG-06 dan PG-07. Data `PRIVATE` Batch #720 terbukti nol bagi Pegawai.

---

### F-05: Penjelasan Interaktif Cakupan Metodologi Distribusi vs Neraca
- **Kategori:** UI/UX & Methodology Clarity
- **Tingkat Keparahan:** Low
- **Status Operasional:** RESOLVED & VERIFIED IN PHASE 2G.8
- **Deskripsi:** Pengguna eksekutif dan staf membutuhkan panduan ringkas langsung pada antarmuka dashboard untuk membedakan konsep Distribusi (POD/KDPROV) dan Neraca (PROVORIG/KPPBC).
- **Solusi Remediasi:** Penambahan tombol info interaktif (`#btnMethodologyInfo`) dengan popover tooltip (`#methodologyTooltipPopover`) yang memuat definisi baku BPS.
- **Status Verifikasi:** 100% Lolos pada skenario SA-17, PG-16, VE-14, dan UI-03.

---

### F-06: Standard Operating Procedure (SOP) Pencadangan Database Operasional
- **Kategori:** Operational Database Safety
- **Tingkat Keparahan:** Informational
- **Status Operasional:** DOCUMENTED SOP ACTIVE
- **Deskripsi:** Database operasional `sidatex` wajib dilindungi dari segala bentuk mutasi tidak sah.
- **Ketentuan Baku:**
  1. Agen AI dan otomatisasi dilarang menulis ke database `sidatex`.
  2. Seluruh mutasi produksi wajib diawali dengan pencadangan terkompresi menggunakan mysqldump:
     `mysqldump -u root -p sidatex | gzip > backup_sidatex_YYYYMMDD_HHMMSS.sql.gz`
  3. Verifikasi integritas cadangan wajib dilakukan sebelum tindakan apa pun.

---

### F-07: Prosedur Audit Trail Reagregasi Batch
- **Kategori:** Audit Trail & Traceability
- **Tingkat Keparahan:** Informational
- **Status Operasional:** DOCUMENTED SOP ACTIVE
- **Deskripsi:** Setiap tindakan perubahan status batch (`toggle-release`), pemindahan ke Trash, atau pemulihan batch wajib tercatat secara persisten pada tabel `activity_logs` dengan menyertakan `user_id`, waktu presisi, IP address, dan rincian transaksi yang dipengaruhi.
- **Status Verifikasi:** Terverifikasi pada modul `UploadController` dan `ActivityLogController`.

---

## 3. Matriks Risiko Pasca-UAT

| Kode Risiko | Deskripsi Risiko | Kemungkinan | Dampak | Strategi Mitigasi Terpasang |
| :--- | :--- | :---: | :---: | :--- |
| **RSK-01** | Kebocoran Data Draft/Private ke Pegawai | Sangat Rendah | Tinggi | Pemeriksaan lapis ganda (*Double-tier RBAC*): Query filter database + Otorisasi Controller. |
| **RSK-02** | Penggabungan Angka Distribusi & Neraca | Sangat Rendah | Kritis | Pemisahan rute, controller, dan switcher UI terpisah tanpa tombol agregasi gabungan. |
| **RSK-03** | Pembagian dengan Nol pada Indikator Pertumbuhan | Sangat Rendah | Sedang | Formula resmi `calcBpsGrowth()` otomatis mengembalikan `null` (N/A) saat pembagi nol. |
| **RSK-04** | Akses Tidak Sah Modul Administratif | Sangat Rendah | Tinggi | Middleware `superadmin` melindungi rute `/upload-data`, `/neraca/upload`, dan `/trash-data`. |
| **RSK-05** | Kesalahan Interpretasi Neraca Perdagangan Asimetris | Sangat Rendah | Sedang | Banner peringatan visual amber interaktif (F-03) otomatis muncul saat rentang bulan berbeda. |
