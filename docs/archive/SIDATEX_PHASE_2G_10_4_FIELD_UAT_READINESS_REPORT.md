# SIDATEX — LAPORAN KESIAPAN LAPANGAN MANUAL UAT (PHASE 2G.10.4)
## Field UAT Readiness & Coordination Assessment Report

**Aplikasi:** Sistem Informasi Data Ekspor-Impor (SIDATEX) — BPS Provinsi Riau  
**Direktori Proyek:** `D:\laragon\www\sidatex`  
**Database Pengujian:** `sidatex_testing` (Runtime MySQL Port 3306)  
**Database Operasional:** `sidatex` (Terkunci & Terisolasi Penuh)  
**Tanggal Evaluasi:** 19 November 2026  
**Peran Auditor:** Senior QA Engineer, Business Analyst, dan UAT Coordinator  
**Status Kesiapan:** `FIELD UAT READINESS DOCUMENTATION PREPARED`  
**Status UAT Bisnis:** `MANUAL UAT — PENDING BPS FIELD EXECUTION`  
**Status Skenario Toggle Release:** `TC-UAT-SA-03 — BLOCKED, WAITING FOR APPROVED TEST BATCH`  
**Status Penerimaan Bisnis:** `BUSINESS ACCEPTANCE — PENDING`  

---

## 1. RINGKASAN EKSEKUTIF KESIAPAN LAPANGAN

Fase 2G.10.4 menandai transisi penting dari persiapan dokumen pengujian (Phase 2G.10, 2G.10.1, 2G.10.2) dan penilaian keselamatan data (Phase 2G.10.3) menuju **kesiapan koordinasi lapangan** (*field execution readiness*). 

Evaluasi kesiapan lapangan ini dilaksanakan secara **100% Read-Only** tanpa melakukan modifikasi kode sumber, manipulasi database, maupun penulisan data fiktif. Seluruh instrumen pengujian dievaluasi untuk memastikan staf BPS Provinsi Riau (Tim Distribusi dan Neraca) dapat melaksanakan User Acceptance Testing secara mandiri, terstruktur, aman, dan dapat dipertanggungjawabkan.

### Rekapitulasi Status Kesiapan 30 Test Case UAT:
- **Total Skenario UAT:** Tepat **30 test case**.
- **Skenario Siap Dilaksanakan Lapangan (`READY FOR FIELD UAT`):** **29 skenario** (96,67%).
- **Skenario Ditangguhkan/Diblokir (`BLOCKED`):** **1 skenario** (`TC-UAT-SA-03` — Pengalihan Status Rilis / Toggle Release).
- **Integritas Database Operasional (`sidatex`):** **100% Utuh & Tidak Pernah Disentuh** (Batches: 3, Transaksi: 2.557, Mart Bulanan: 34.847).
- **Integritas Database Pengujian (`sidatex_testing`):** 
  - Batch #71 (Ekspor Sep 2026): Tetap **RILIS** (Anchor Publikasi).
  - Batch #720 (Ekspor Ags 2026): Tetap **PRIVATE** (Anchor Keamanan & Isolasi).
  - Batch #74 (Impor Sep 2026): Tetap **PRIVATE** (Anchor Asimetri F-03 & Empty Growth).

---

## 2. AUDIT KELENGKAPAN DOKUMEN UAT YANG TERSEDIA

Audit read-only dilakukan terhadap seluruh instrumen dokumen yang telah disiapkan pada fase-fase sebelumnya:

| No | Berkas Dokumen | Fungsi & Cakupan | Status Dokumen |
|:---:|:---|:---|:---:|
| 1 | `SIDATEX_PHASE_2G_10_MANUAL_UAT_GUIDE.md` | Panduan tata kelola, peran penguji, arsitektur, dan SOP eskalasi kendala. | **LENGKAP & TERVALIDASI** |
| 2 | `SIDATEX_PHASE_2G_10_MANUAL_UAT_TEST_CASES.md` | Katalog lengkap 30 skenario pengujian dengan langkah detail dan hasil harapan. | **LENGKAP & TERVALIDASI** (30 TC) |
| 3 | `SIDATEX_PHASE_2G_10_MANUAL_UAT_FEEDBACK_FORM.md` | Formulir evaluasi pengalaman pengguna, kemudahan, dan persepsi kesiapan rilis. | **LENGKAP & SIAP DIGUNAKAN** |
| 4 | `SIDATEX_PHASE_2G_10_MANUAL_UAT_FINDINGS_LOG.md` | Log pencatatan kendala/bug lapangan dengan klasifikasi keparahan (Critical-Cosmetic). | **LENGKAP & SIAP DIGUNAKAN** |
| 5 | `SIDATEX_PHASE_2G_10_UAT_PREREQUISITES_CHECKLIST.md` | Daftar periksa kesiapan lingkungan, akun, jaringan, dan database pengujian. | **LENGKAP & TERVERIFIKASI** |
| 6 | `SIDATEX_PHASE_2G_10_PREPARATION_REPORT.md` | Laporan eksekutif paket persiapan Manual UAT. | **TERDOKUMENTASI** |
| 7 | `SIDATEX_PHASE_2G_10_1_FINAL_DOCUMENTATION_QA_REPORT.md` | Hasil audit QA independen terhadap kelayakan dokumen UAT. | **TERDOKUMENTASI** |
| 8 | `SIDATEX_PHASE_2G_10_2_DOCUMENTATION_CORRECTION_REPORT.md` | Laporan perbaikan controlled atas jumlah test case (30) dan pedoman LAN. | **TERDOKUMENTASI** |
| 9 | `SIDATEX_PHASE_2G_10_3_BATCH_74_SAFETY_ASSESSMENT.md` | Penilaian teknis yang menetapkan Batch #74 tidak aman dan memblokir TC-UAT-SA-03. | **TERDOKUMENTASI & MENGIKAT** |

---

## 3. TABEL VERIFIKASI PRASYARAT LAPANGAN (FIELD PREREQUISITES)

Setiap elemen prasyarat teknis dan operasional lapangan diperiksa dan dikategorikan secara faktual:

| ID Prasyarat | Komponen Kesiapan | Nilai / Kondisi Faktual | Status Verifikasi | Keterangan & Tindak Lanjut |
|:---:|:---|:---|:---:|:---|
| **REQ-ENV-01** | Runtime PHP & Framework | PHP 8.2+ CLI / Laravel 11 terdeteksi | `VERIFIED` | Lingkungan runtime Laravel siap melayani permintaan HTTP. |
| **REQ-ENV-02** | HTTP Server Active | Listener port 8000 aktif (HTTP 200 pada `/login`) | `VERIFIED` | Server lokal siap merespons. Port 8094 siap dijalankan jika diperlukan port khusus. |
| **REQ-ENV-03** | Akses Jaringan LAN BPS | Host binding `0.0.0.0` & IP Komputer Teknisi | `PENDING CONFIRMATION` | Menunggu konfirmasi IP LAN ruangan pengujian dan izin firewall lokal kantor BPS. |
| **REQ-DB-01** | Database Engine | MySQL / MariaDB Localhost Port 3306 | `VERIFIED` | Koneksi database testing berjalan normal dan deterministik. |
| **REQ-DB-02** | Target Database Testing | PDO `DB::connection()->getDatabaseName()` = `sidatex_testing` | `VERIFIED` | Terkonfirmasi aktif pada `sidatex_testing`. |
| **REQ-DB-03** | Isolasi Database Operasional | DB `sidatex` memiliki 3 batches, 2.557 records | `VERIFIED` | Database operasional 100% utuh, terkunci, dan tidak terakses sesi pengujian. |
| **REQ-DB-04** | Keberadaan Batch Baseline #71 | Status: `RILIS` (Ekspor Sep 2026, 3.393 baris) | `VERIFIED` | Berfungsi sebagai baseline data publikasi untuk Pegawai dan Viewer. |
| **REQ-DB-05** | Keberadaan Batch Baseline #720 | Status: `PRIVATE` (Ekspor Ags 2026, 30.147 baris) | `VERIFIED` | Berfungsi sebagai baseline data tertutup untuk verifikasi isolasi RBAC Pegawai. |
| **REQ-DB-06** | Keberadaan Batch Baseline #74 | Status: `PRIVATE` (Impor Sep 2026, 931 baris) | `VERIFIED` | Berfungsi sebagai anchor asimetri F-03 dan empty growth; dilarang dimutasi. |
| **REQ-ACC-01** | Akun Penguji Super Admin | ID: 1, Username: `admin_sidatex`, Role: 1, Active: 1 | `VERIFIED` | Akun aktif di database testing; kredensial diserahkan via amplop tertutup. |
| **REQ-ACC-02** | Akun Penguji Pegawai BPS | ID: 2, Username: `pegawai_sidatex`, Role: 2, Active: 1 | `VERIFIED` | Akun aktif di database testing; kredensial diserahkan via amplop tertutup. |
| **REQ-ACC-03** | Akun Penguji Viewer Eksekutif | ID: 3, Username: `viewer_sidatex`, Role: 3, Active: 1 | `VERIFIED` | Akun aktif di database testing; kredensial diserahkan via amplop tertutup. |
| **REQ-OPR-01** | Daftar Nama Penguji BPS | Nama pejabat / staf penanggung jawab pengujian | `PENDING CONFIRMATION` | Menunggu penunjukan resmi dari Koordinator Tim Distribusi & Neraca BPS Riau. |
| **REQ-OPR-02** | Jadwal Pelaksanaan Lapangan | Tanggal, jam, dan ruang pelaksanaan sesi UAT | `PENDING CONFIRMATION` | Menunggu penetapan jadwal bersama antara BPS dan Tim Pengembang. |
| **REQ-OPR-03** | Berkas DBF Staging Non-Baseline | Berkas DBF sintetis terisolasi untuk tes upload | `PENDING CONFIRMATION` | Dibutuhkan berkas dummy sintetis 5-10 baris non-riil untuk TC-UAT-SA-05 dan SA-06. |
| **REQ-BLK-01** | Batch Uji Khusus Toggle Release | Batch non-historis untuk TC-UAT-SA-03 | `BLOCKED` | Belum ada batch uji yang disetujui; Batch #71, #74, dan #720 dilarang ditoggle. |

---

## 4. DAFTAR KENDALA & BLOKIR (BLOCKERS)

### Blocker Utama: `TC-UAT-SA-03` (Toggle Release)
- **Status Skenario:** `BLOCKED — WAITING FOR APPROVED TEST BATCH`
- **Penyebab:**
  Berdasarkan hasil asesmen teknis mendalam pada Phase 2G.10.3 (`SIDATEX_PHASE_2G_10_3_BATCH_74_SAFETY_ASSESSMENT.md`), Batch #74 (Impor Sep 2026) terbukti merupakan anchor baseline untuk:
  1. Fitur remediasi F-03 (*Cumulative Trade Balance Asymmetry Alert*) yang diuji pada `TC-UAT-PG-06`.
  2. Logika proteksi pertumbuhan kosong (*Empty Growth Rate Guard*) yang diuji pada `TC-UAT-PG-08`.
  Jika status Batch #74 diubah (*toggle*) menjadi `RILIS`, maka neraca perdagangan September 2026 menjadi simetris (ekspor dan impor sama-sama rilis), sehingga banner asimetri F-03 hilang dan skenario `TC-UAT-PG-06` akan gagal.
- **Kebijakan Keamanan:**
  - `TC-UAT-SA-03` **TIDAK BOLEH DIJALANKAN** menggunakan Batch #71, Batch #74, maupun Batch #720.
  - Skenario ini dilewati (*skipped/deferred*) pada pelaksanaan sesi UAT pertama, hingga Koordinator UAT dan Tim Teknis menyiapkan batch uji non-historis khusus yang terisolasi.
  - Penguji Super Admin wajib diberi arahan tertulis untuk tidak mengeklik tombol Toggle Release pada daftar data batch.

---

## 5. RANGKUMAN TANGGUNG JAWAB TIM LAPANGAN

Pemisahan peran secara tegas diberlakukan demi menjaga objektivitas hasil pengujian:

```
+-----------------------------------------------------------------------------------+
|                            STRUKTUR KOORDINASI UAT                                |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  [TIM TEKNIS SIDATEX]                 [KOORDINATOR UAT]       [PENGUJI BPS RIAU]  |
|  - Standby Server & LAN               - Mengatur Roster       - Menjalankan Alur  |
|  - Pantau Runtime & Log               - Membagikan Akun       - Menilai UI & Fitur|
|  - Kawal Database Testing             - Pantau Matriks 30 TC  - Ambil Screenshot  |
|  - Cegah Mutasi Baseline              - Terima Feedback Log   - Catat Temuan/Bug  |
|  - Tangani Error Sistem               - Lapor Status Bisnis   - Isi Feedback Form |
|                                                                                   |
+-----------------------------------------------------------------------------------+
```

1. **Tim Teknis SIDATEX:** Bertanggung jawab atas ketersediaan server pengujian, koneksi PDO `sidatex_testing`, pemantauan log Laravel, dan pengamanan database operasional.
2. **Penguji Lapangan BPS (Super Admin, Pegawai, Viewer):** Bertanggung jawab menjalankan 29 skenario yang telah disetujui, mengamati kesesuaian alur bisnis, menangkap tangkapan layar bukti, serta mengisi lembar evaluasi secara objektif.
3. **Koordinator UAT:** Bertanggung jawab mendistribusikan berkas pengujian, memantau matriks progres tanpa memalsukan status, dan menyusun laporan akhir berbasis bukti nyata.

---

## 6. KESIMPULAN KESIAPAN LAPANGAN

1. Seluruh dokumentasi pengujian, instrumen form, log temuan, dan panduan ringkas telah siap 100% dan terverifikasi secara formal.
2. Sebanyak **29 dari 30 skenario pengujian** dinyatakan **READY FOR FIELD UAT** dan dapat segera dieksekusi begitu jadwal dan personil BPS telah ditetapkan.
3. Skenario `TC-UAT-SA-03` secara resmi ditandai **BLOCKED** untuk menjamin perlindungan data baseline statistik dan keberhasilan pengujian skenario lainnya.
4. Database operasional `sidatex` terbukti 100% aman dan tidak tersentuh.
5. Status penerimaan bisnis tetap berada pada posisi formal: **PENDING FIELD EXECUTION BY BPS RIAU**.
