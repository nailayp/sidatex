# SIDATEX — DAFTAR PERIKSA KOORDINASI MANUAL UAT (PHASE 2G.10.4)
## Field UAT Coordination Checklist

**Aplikasi:** Sistem Informasi Data Ekspor-Impor (SIDATEX) — BPS Provinsi Riau  
**Target Lingkungan:** Database Testing `sidatex_testing` (Port 3306)  
**Tujuan Dokumen:** Standar Operasional Prosedur (SOP) dan daftar periksa kesiapan bagi Tim Teknis, Penguji BPS, dan Koordinator UAT sebelum, selama, dan sesudah sesi pengujian manual lapangan.

---

## 1. STRUKTUR PROTOKOL KOORDINASI TIGA PIHAK

Pelaksanaan Manual UAT melibatkan tiga entitas kerja yang memiliki batas wewenang tegas:

```
+-------------------------------------------------------------------------------------------------+
|                                 PROTOKOL KOORDINASI TIGA PIHAK                                  |
+-------------------------------------------------------------------------------------------------+
|                                                                                                 |
|   [TIM TEKNIS SIDATEX]                 [KOORDINATOR UAT]               [TIM PENGUJI BPS RIAU]   |
|   - Infrastruktur Server               - Manajemen Skenario 30 TC      - Validasi Kebutuhan BPS |
|   - Isolasi DB Testing                 - Distribusi Lembar Form        - Eksekusi Step-by-Step  |
|   - Proteksi DB Operasional            - Rekap Temuan (Findings Log)   - Pengambilan Screenshot |
|   - Pemantauan Error Log               - Kendali Status UAT Lapangan   - Penilaian Kelayakan UI |
|                                                                                                 |
+-------------------------------------------------------------------------------------------------+
```

---

## 2. CHECKLIST TIM TEKNIS SIDATEX

Tim teknis bertindak sebagai penjaga infrastruktur, runtime, dan database. Tim teknis **TIDAK TERLIBAT** dalam penilaian subjektif bisnis BPS.

### A. Sebelum Sesi UAT (Pre-Session Preparation)
- [x] **TECH-01: Verifikasi Server Runtime Aktif**  
  Server aplikasi Laravel berjalan normal. Endpoint `http://127.0.0.1:8000/login` (atau port 8094) mengembalikan status HTTP 200 OK.
- [x] **TECH-02: Verifikasi Koneksi Database Runtime PDO**  
  Eksekusi `DB::connection()->getDatabaseName()` terkonfirmasi menghasilkan string `sidatex_testing`.
- [x] **TECH-03: Karantina Database Operasional**  
  Database produksi `sidatex` diverifikasi dalam keadaan terisolasi, read-only, dan tidak tersentuh (tercatat 3 batches, 2.557 records distribusi, 34.847 records data mart).
- [x] **TECH-04: Kesiapan Akun Pengujian Tanpa Membocorkan Kredensial**  
  Tiga akun pengujian (`admin_sidatex`, `pegawai_sidatex`, `viewer_sidatex`) terverifikasi aktif dengan role_id masing-masing (1, 2, 3). Password diserahkan tertutup.
- [x] **TECH-05: Backup Database Testing Sebelum Sesi**  
  Snapshot atau dump SQL database `sidatex_testing` sebelum sesi telah diamankan untuk memungkinkan rollback cepat jika terjadi anomali data.
- [ ] **TECH-06: Verifikasi Aksesibilitas Jaringan LAN Ruangan BPS**  
  Binding host `php artisan serve --host=0.0.0.0 --port=8094` disesuaikan dengan IP jaringan lokal kantor BPS Provinsi Riau. *(Status: `PENDING CONFIRMATION` — Menunggu lokasi fisik pengujian).*
- [x] **TECH-07: Prosedur Monitoring Log Aktif**  
  Terminal pemantau log aplikasi (`storage/logs/laravel.log`) siap dijalankan secara realtime (`Get-Content -Wait` / `tail -f`) untuk menangkap exception seketika.
- [x] **TECH-08: Pembekuan Kode & Konfigurasi (Code Freeze)**  
  Dilarang keras melakukan commit Git, migrasi database, kompilasi ulang aset, atau pengubahan `.env` selama sesi UAT berlangsung.

### B. Selama Sesi UAT Berlangsung (During-Session Support)
- [ ] **TECH-09: Standby Support Tanpa Intervensi Hasil**  
  Tim teknis standby di ruang teknis untuk mengamati stabilitas server dan log, tanpa mendikte hasil pengujian penguji BPS.
- [ ] **TECH-10: Penanganan Cepat Kendala Teknis (Infrastruktur/Network)**  
  Jika terjadi server crash, out-of-memory, atau jaringan LAN terputus, tim teknis segera mengatasi masalah teknis tanpa mengubah basis data aplikasi.
- [ ] **TECH-11: Larangan Intervensi Manual SQL**  
  Dilarang mengeksekusi perintah SQL manual (INSERT/UPDATE/DELETE) secara ad-hoc saat penguji menemukan inkonsistensi data; catat sebagai temuan murni.

### C. Setelah Sesi UAT Selesai (Post-Session Wrap-Up)
- [ ] **TECH-12: Verifikasi Integritas Pasca Sesi**  
  Pemeriksaan integritas untuk memastikan database operasional `sidatex` tetap 100% identik dengan baseline awal.
- [ ] **TECH-13: Pengarsipan Log Server**  
  Log aplikasi selama sesi UAT diekspor dan diarsipkan sebagai lampiran teknis laporan.

---

## 3. CHECKLIST PENGUJI BPS PROVINSI RIAU

Penguji merupakan staf teknis dan fungsional BPS Provinsi Riau yang bertugas memvalidasi kesesuaian sistem dengan kebutuhan analisis ekspor-impor.

### A. Sebelum Sesi UAT (Pre-Session Briefing)
- [ ] **TESTER-01: Memahami Tujuan dan Lingkup Manual UAT**  
  Penguji memahami bahwa UAT bertujuan menguji kelayakan alur kerja bisnis, antarmuka, dan validitas angka statistik BPS, bukan pengujian coba-coba bebas.
- [ ] **TESTER-02: Memahami Batasan Peran Masing-Masing**  
  Penguji telah menerima penugasan peran (Super Admin, Pegawai, atau Viewer Eksekutif) dan lembar kredensial tertutup dari Koordinator UAT.
- [ ] **TESTER-03: Kesiapan Perangkat & Peramban yang Didukung**  
  Penguji menggunakan laptop/PC dengan peramban modern (Google Chrome, Microsoft Edge, atau Mozilla Firefox) yang diperbarui.
- [ ] **TESTER-04: Menerima Paket Dokumen Pengujian**  
  Penguji telah menerima salinan `SIDATEX_PHASE_2G_10_4_UAT_QUICK_GUIDE.md`, katalog test case peran terkait, Formulir Umpan Balik, dan Log Temuan.

### B. Selama Sesi UAT Berlangsung (Execution Protocol)
- [ ] **TESTER-05: Mengikuti Skenario Berurutan (Sequential Execution)**  
  Penguji menjalankan skenario pengujian secara berurutan sesuai nomor Test Case ID (misal: `TC-UAT-PG-01` sampai `TC-UAT-PG-12`).
- [ ] **TESTER-06: Mengambil Tangkapan Layar (Screenshot) Bukti**  
  Penguji menyimpan screenshot setiap langkah kunci dengan format nama file standar: `[TC-ID]_[Role]_[Deskripsi].png`.
- [ ] **TESTER-07: Mengamati Validitas Formula Statistik BPS**  
  Penguji memeriksa kebenaran formula Neraca Perdagangan ($FOB - CIF$), pertumbuhan MoM/YoY/C-to-C, dan pemisahan tegas Distribusi vs Neraca.
- [ ] **TESTER-08: Menghormati Larangan Mutasi Data Baseline**  
  Penguji **TIDAK MENCOBA** mengubah status rilis (toggle) Batch #71, #74, maupun #720.
- [ ] **TESTER-09: Mencatat Hasil Aktual Apa Adanya**  
  Penguji mencatat hasil yang tampil di layar secara objektif pada lembar kerja, tanpa berasumsi atau menebak maksud sistem.
- [ ] **TESTER-10: Melaporkan Kendala ke Findings Log**  
  Jika ditemukan anomali tampilan, salah hitung, atau error 500, penguji mencatat rincian pada Berkas Log Temuan dan memberi tahu Koordinator UAT.

### C. Setelah Sesi UAT Selesai (Post-Session Review)
- [ ] **TESTER-11: Melengkapi Formulir Evaluasi & Umpan Balik**  
  Penguji mengisi kuesioner kemudahan, persepsi keandalan, dan rekomendasi perbaikan pada `SIDATEX_PHASE_2G_10_MANUAL_UAT_FEEDBACK_FORM.md`.
- [ ] **TESTER-12: Menyerahkan Bukti Screenshot dan Berkas Pengujian**  
  Seluruh folder screenshot dan berkas penilaian bertanda tangan diserahkan kepada Koordinator UAT.

---

## 4. CHECKLIST KOORDINATOR UAT

Koordinator UAT bertanggung jawab mengorkestrasi seluruh proses pengujian, menjamin kepatuhan metodologi, dan menjaga keabsahan pelaporan.

### A. Sebelum Sesi UAT
- [ ] **COORD-01: Penetapan Jadwal Resmi bersama Pimpinan BPS**  
  Tanggal, jam mulai, estimasi durasi (2–3 jam), dan ruang rapat/laboratorium BPS telah disepakati tertulis. *(Status: `PENDING CONFIRMATION`).*
- [ ] **COORD-02: Penetapan Daftar Nama Tim Penguji (Roster)**  
  Pejabat dan staf fungsional statistik yang bertindak sebagai Super Admin, Pegawai, dan Viewer Eksekutif telah terdaftar resmi. *(Status: `PENDING CONFIRMATION`).*
- [x] **COORD-03: Kesiapan Master Execution Matrix (30 Test Cases)**  
  Matriks induk telah disiapkan dengan penegasan 29 skenario siap uji dan 1 skenario diblokir (`TC-UAT-SA-03`).
- [x] **COORD-04: Penyiapan Berkas DBF Sintetis untuk Pengujian Upload**  
  Berkas DBF dummy sintetis non-riil (5–10 record) telah disiapkan dalam flashdisk penguji untuk pengujian `TC-UAT-SA-05` dan `TC-UAT-SA-06`.

### B. Selama Sesi UAT
- [ ] **COORD-05: Membuka Sesi & Menjelaskan Aturan Keselamatan**  
  Memberikan arahan awal (briefing) 10 menit mengenai tujuan, alur, pembagian modul, dan larangan mutasi data baseline.
- [ ] **COORD-06: Memantau Kepatuhan Terhadap Pemblokiran TC-UAT-SA-03**  
  Memastikan penguji Super Admin tidak mengeksekusi toggle release pada data historis.
- [ ] **COORD-07: Mengawasi Perekaman Tangkapan Layar (Screenshot)**  
  Memastikan penguji secara konsisten menyimpan screenshot bukti untuk setiap test case yang diuji.
- [ ] **COORD-08: Membantu Triage Awal Temuan Kendala (Bug Severity)**  
  Membantu penguji mengklasifikasikan kendala yang ditemukan (apakah Critical, Major, Moderate, Minor, atau Cosmetic).

### C. Setelah Sesi UAT
- [ ] **COORD-09: Mengumpulkan dan Merekapitulasi Seluruh Lembar Hasil**  
  Mengumpulkan berkas feedback form, log temuan, dan folder tangkapan layar dari seluruh penguji.
- [ ] **COORD-10: Memeriksa Konsistensi Hasil Pengujian**  
  Memvalidasi bahwa status yang dilaporkan penguji didukung oleh screenshot yang sah dan relevan.
- [ ] **COORD-11: Menyusun Berita Acara Pelaksanaan UAT Lapangan**  
  Menyusun laporan eksekutif hasil UAT faktual berdasarkan data lapangan nyata, tanpa mengklaim kelulusan bisnis secara prematur.
- [ ] **COORD-12: Menjaga Status Penerimaan Bisnis Tetap Sah**  
  Menjaga status `BUSINESS ACCEPTANCE — PENDING` sampai ada persetujuan dan tanda tangan resmi dari Kepala / Koordinator Neraca & Distribusi BPS Provinsi Riau.

---

## 5. TABEL DISTRIBUSI TANGGUNG JAWAB PERSONIL (ROSTER)

| No | Peran dalam Sesi UAT | Nama Personil BPS / Tim | NIP / Institusi | Status Penunjukan |
|:---:|:---|:---|:---|:---:|
| 1 | **Penanggung Jawab Teknis BPS** | *(Menunggu Konfirmasi)* | BPS Provinsi Riau | `PENDING CONFIRMATION` |
| 2 | **Penguji 1 (Super Admin)** | *(Menunggu Konfirmasi)* | Tim Distribusi BPS Riau | `PENDING CONFIRMATION` |
| 3 | **Penguji 2 (Pegawai Distribusi)** | *(Menunggu Konfirmasi)* | Tim Distribusi BPS Riau | `PENDING CONFIRMATION` |
| 4 | **Penguji 3 (Pegawai Neraca)** | *(Menunggu Konfirmasi)* | Tim Neraca BPS Riau | `PENDING CONFIRMATION` |
| 5 | **Penguji 4 (Viewer Eksekutif)** | *(Menunggu Konfirmasi)* | Pimpinan / Tim Diseminasi BPS | `PENDING CONFIRMATION` |
| 6 | **Koordinator UAT (QA Lead)** | Tim QA SIDATEX | Pengembang SIDATEX | `VERIFIED` |
| 7 | **Dukungan Teknis & Database** | Tim DBA SIDATEX | Pengembang SIDATEX | `VERIFIED` |
