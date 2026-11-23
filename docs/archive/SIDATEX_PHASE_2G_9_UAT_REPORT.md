# SIDATEX — LAPORAN RESMI USER ACCEPTANCE TESTING (UAT) & SYSTEM VALIDATION (PHASE 2G.9)
**Sistem Informasi Data Ekspor-Impor BPS Provinsi Riau**  
**Tanggal Pengujian:** 19 November 2026  
**Status Evaluasi:** `TECHNICALLY READY FOR UAT — BUSINESS ACCEPTANCE PENDING`  
**Database Pengujian:** `sidatex_testing` (MySQL 127.0.0.1:3306)  
**Database Operasional:** `sidatex` (100% Utuh / Nol Mutasi)  

---

## 1. Executive Summary & UAT Designation

Laporan ini mendokumentasikan pelaksanaan resmi **Phase 2G.9: User Acceptance Testing (UAT) & System Validation** pada aplikasi Sistem Informasi Data Ekspor-Impor (SIDATEX) Badan Pusat Statistik (BPS) Provinsi Riau. Pengujian ini dilaksanakan setelah penyelesaian rangkaian audit dan remediasi terkendali pada Phase 2G.8 (Remediasi F-03 Asimetri Trade Balance dan F-05 Popover Tooltip Metodologi).

### Ringkasan Hasil Teknis:
1. **Status UAT Teknis:** Seluruh prasyarat arsitektur perangkat lunak, kontrol hak akses berbasis peran (RBAC), integritas statistik BPS, isolasi database operasional, dan antarmuka pengguna telah teruji secara komprehensif dengan hasil **100% PASS** (77/77 skenario matriks UAT lolos).
2. **Status Suite Pengujian Otomatis:** PHPUnit mencapai **180/180 PASS** dengan total **1.527 assertions** tanpa kegagalan (exit code 0).
3. **Pembedaan Status Resmi (Designation Distinction):**
   > [!IMPORTANT]
   > Status teknis sistem saat ini adalah **`TECHNICALLY READY FOR UAT — BUSINESS ACCEPTANCE PENDING`**. Pengujian ini membuktikan kesiapan fungsional dan teknis aplikasi secara menyeluruh. Penerimaan akhir (*Business Sign-Off*) tetap berada pada wewenang pemangku kepentingan resmi BPS Provinsi Riau melalui pengisian formulir evaluasi pengguna (`SIDATEX_PHASE_2G_9_UAT_USER_FEEDBACK.md`).

---

## 2. Preflight Baseline & Environment Verification

Pemeriksaan preflight read-only dilaksanakan secara faktual pada koneksi PDO runtime sebelum eksekusi pengujian. Seluruh metrik baseline berhasil diverifikasi:

1. **Koneksi Database Aktif:**
   - Database runtime: `sidatex_testing` (Koneksi PDO terisolasi penuh).
   - Database operasional `sidatex`: Nol mutasi data, nol eksekusi DDL/migrasi, tidak tersentuh.
2. **Verifikasi 9 Metrik Database Operasional (`sidatex`):**
   - `data_batches`: 3 baris.
   - `distribusi_transactions`: 2.557 baris.
   - `neraca_transactions`: 0 baris.
   - `monthly_trade_summaries`: 34.847 baris.
   - `monthly_origin_trade_summaries`: 189 baris.
   - `migrations`: 26 migrasi terdaftar.
   - `brs_indicator_summaries`: NOT FOUND (Skema mart BRS operasional utuh).
   - `data_batches_max_updated_at`: `2026-11-13 07:38:33`.
   - `mts_max_updated_at`: `2026-11-12 07:06:16`.
3. **Integritas Record Kritis (`sidatex_testing`):**
   - **Batch #71:** Status `RILIS`, Ekspor September 2026, 841 baris. Kelima sidik jari kriptografis SHA-256 (batch, transaksi, MTS, BRS, MOTS) cocok 100%.
   - **Batch #720:** Status `PRIVATE`, Ekspor Agustus 2026, 809 baris. Sidik jari kanonikal SHA-256 (`028082b5...`) cocok 100%.
   - **Data Historis MTS:** 30.147 baris berstatus `PRIVATE` (`pelabuhan_key = 0`, `batch_id IS NULL`). SHA-256 (`17cc2343...`) cocok 100%.
   - **Transaksi Neraca:** 2.896 baris transaksi `neraca_transactions`. SHA-256 (`63c18733...`) cocok 100%.
   - **HS 27075000:** `oilgrp_code = 0` (NON MIGAS), `sektor = INDUSTRI PENGOLAHAN`.

---

## 3. Scope of UAT & Boundary Definition

Pengujian UAT Phase 2G.9 berfokus pada validasi perilaku nyata sistem (*end-to-end user behavior*) berdasarkan batasan ketat:
1. **Tidak Ada Rekayasa Fitur Baru:** UAT adalah fase pengujian dan validasi, bukan fase penambahan fitur atau perbaikan kode otomatis.
2. **Pemberlakuan Batasan Akses Peran:**
   - **Super Admin (Role 1):** Akses penuh operasional, upload DBF, rilis data, trash, dan log audit global.
   - **Pegawai BPS (Role 2):** Akses analisis baca (*read-only*) eksklusif pada data `RILIS`. Dilarang keras mengakses modul upload, trash data, atau melihat angka `PRIVATE`.
   - **Viewer Eksekutif (Role 3):** Akses pemantauan dashboard makro dan telaah pra-rilis data `PRIVATE`. Dilarang mengunggah data, merilis status, menghapus, atau mengakses audit trail internal.
3. **Pemisahan Tegas Metodologi Statistik:** Metodologi Distribusi (POD/KDPROV) dan Neraca (PROVORIG/KPPBC) dilarang digabungkan menjadi satu angka total.

---

## 4. User Roles & Access Control Validation (SA, PG, VE)

Eksekusi matriks pengujian lintas-peran dijalankan menggunakan akun faktual database:
- **Super Admin:** Username `admin_sidatex` (User ID: 1, Role ID: 1).
- **Pegawai BPS:** Username `pegawai_sidatex` (User ID: 2, Role ID: 2).
- **Viewer Eksekutif:** Username `viewer_sidatex` (User ID: 3, Role ID: 3).

### Ringkasan Eksekusi Matriks Hak Akses:
```
[Peran]                [Total Kasus]  [Lolos (PASS)]  [Gagal (FAIL)]  [Status RBAC]
Super Admin (Role 1)        18              18               0         TERVERIFIKASI
Pegawai BPS (Role 2)        17              17               0         TERVERIFIKASI
Viewer Eksekutif (Role 3)   14              14               0         TERVERIFIKASI
```

---

## 5. Module 1 — Single Dashboard (Tabs 1-4) Validation

Single Dashboard Terpadu (`/dashboard`) diuji pada seluruh tab dan peran pengguna:

1. **Tab 1: Overview Global & Tren Perdagangan (`tabOverview`):**
   - Kartu metrik utama (FOB, Netto, Transaksi, Rata-rata/Transaksi, Negara Mitra, Komoditas) terisi data yang tepat.
   - Diagram garis bulanan (Januari - Desember) dan triwulanan (Q1 - Q4) memisahkan komponen Migas dan Non-Migas dengan presisi.
   - Pegawai (Role 2) hanya menerima agregasi dari batch `RILIS` (Agustus bernilai $0,00; September bernilai $2.301.809.204,59).
   - Super Admin dan Viewer menerima agregasi lengkap termasuk batch `PRIVATE` (Agustus $2.218.155.995,50 dan September $2.301.809.204,59).

2. **Tab 2: Tabulasi Time Series Komoditas (`tabKomoditas`):**
   - Matriks pivot komoditas 2-digit HS (`master_hs`) terformat rapi dengan dukungan scroll horizontal.
   - Data historis 30.147 baris `monthly_trade_summaries` tidak bocor kepada Pegawai dan tetap aman dalam status `PRIVATE`.

3. **Tab 3: Detail Analisis & Pemetaan Wilayah (`tabWilayah`):**
   - Peta Leaflet.js menampilkan sebaran pelabuhan muat/bongkar dengan koordinat akurat.
   - Tabel agregasi kabupaten/kota dan negara tujuan utama terisolasi sesuai status rilis.

4. **Tab 4: Neraca Perdagangan / Trade Balance (`tabTradeBalance`):**
   - Nilai neraca dihitung dengan formula $\text{FOB} - \text{CIF}$ pada periode dan metodologi yang identik.
   - **Penanganan Periode Asimetris (F-03):** Ketika data Ekspor tersedia hingga September 2026 sedangkan Impor belum masuk, banner peringatan amber interaktif (`#alertTradeAsymmetry`) muncul dan menjelaskan perbedaan rentang data.
   - **Penanganan Nilai Null:** Komponen bulanan yang belum memiliki transaksi ditampilkan sebagai strip (`—`) dan tidak disamarkan menjadi `US$ 0,00` atau `NaN`.
   - **Definisi Metodologi BPS (F-05):** Tombol info (`#btnMethodologyInfo`) menampilkan popover dengan penjelasan konsep POD/KDPROV (Distribusi) dan PROVORIG/KPPBC (Neraca).

---

## 6. Module 2 — Upload Data & Processing Pipeline Validation

Pengujian modul pengunggahan data (`/upload-data` dan `/neraca/upload`):

1. **Otorisasi Server-Side:**
   - Akses GET ke `/upload-data` dan `/neraca/upload` ditolak dengan **HTTP 403 Forbidden** untuk Pegawai (Role 2) dan Viewer Eksekutif (Role 3).
   - Upaya pengiriman data via POST `/upload-data/process` ditolak dengan **HTTP 403 Forbidden** untuk pengguna selain Super Admin.
2. **Integritas Pra-Pemrosesan DBF:**
   - Parser DBF mendeteksi header byte raw DBF (0x03) dan membaca metadata secara akurat.
   - Status baku penyimpanan data baru adalah `PRIVATE`. Data tidak dapat dipublikasikan langsung ke publik tanpa otorisasi Super Admin.
3. **Pemisahan Pipeline Distribusi vs Neraca:**
   - Pipeline Distribusi menyimpan data ke `distribusi_transactions`.
   - Pipeline Neraca menyimpan data ke `neraca_transactions`.
   - Tabel lama `transactions` tidak menerima penulisan data baru.

---

## 7. Module 3 — Data Management & Batch Lifecycle (DRAFT, PRIVATE, RILIS)

Pengujian modul manajemen data (`/manajemen-data`):

1. **Visibilitas Berbasis Peran:**
   - **Pegawai (Role 2):** Hanya melihat batch `RILIS` (#71). Batch `PRIVATE` (#720) disembunyikan sepenuhnya dari query database.
   - **Super Admin (Role 1) & Viewer Eksekutif (Role 3):** Melihat seluruh batch `RILIS` dan `PRIVATE` (#71 dan #720).
2. **Fitur Ekspor Excel (`.xlsx`):**
   - Super Admin dapat mengunduh batch `RILIS` (#71) dan `PRIVATE` (#720).
   - Viewer Eksekutif dapat mengunduh batch `RILIS` (#71) dan `PRIVATE` (#720) untuk penelaahan.
   - Pegawai dapat mengunduh batch `RILIS` (#71), namun diblokir dengan **HTTP 403 Forbidden** saat mencoba mengunduh batch `PRIVATE` (#720) melalui probing URL langsung.
3. **Perlindungan Mutasi Status Batch:**
   - Aksi toggle status (`POST /manajemen-data/{batchId}/toggle-release`) hanya diizinkan untuk Super Admin. Pegawai dan Viewer diblokir dengan **HTTP 403 Forbidden**.

---

## 8. Module 4 — Trash Management & Soft Delete Isolation

Pengujian modul tempat sampah (`/trash-data`):

1. **Otorisasi Ketat:**
   - Rute `/trash-data` dilindungi oleh middleware `superadmin`. Pegawai dan Viewer menerima **HTTP 403 Forbidden**.
2. **Isolasi Agregasi Mart:**
   - Batch yang dipindahkan ke Trash secara atomik dikeluarkan dari tabel agregasi mart analitik (`monthly_trade_summaries` dan `brs_indicator_summaries`) tanpa menyentuh data historis.
   - Perhitungan Single Dashboard secara otomatis mengecualikan batch yang berada di Trash.
3. **Pemulihan Data (Restore):**
   - Super Admin dapat memulihkan batch dari Trash, yang secara atomik membangun ulang data mart terkait.

---

## 9. Module 5 — Activity Logs & Audit Trail RBAC

Pengujian modul jejak audit (`/activity-logs`):

1. **Super Admin (Role 1):**
   - Menampilkan seluruh catatan audit trail sistem dari semua pengguna, modul, dan rentang waktu.
2. **Pegawai BPS (Role 2):**
   - Akses dibatasi secara otomatis pada query database (`WHERE user_id = Auth::id()`), sehingga Pegawai hanya melihat riwayat aktivitas miliknya sendiri.
3. **Viewer Eksekutif (Role 3):**
   - Akses ke `/activity-logs` dicegat dan dialihkan (**HTTP 302 Redirect**) kembali ke dashboard dengan pesan peringatan bahwa Viewer tidak berwenang melihat audit trail internal sistem.

---

## 10. Statistical Integrity & Methodology Validation

Pengujian metodologi statistik BPS (Skenario CS-01 s.d. CS-10):

1. **Rumus Pertumbuhan Resmi BPS (`StatisticsCalculationService::calcBpsGrowth`):**
   - Pertumbuhan positif dihitung tepat: $1.250$ vs $1.000 = +25,00\%$.
   - Pertumbuhan negatif dihitung tepat: $750$ vs $1.000 = -25,00\%$.
   - Pembagian dengan nol mengembalikan **`null` (N/A)** dan tidak menghasilkan crash atau angka 0 semu.
2. **Pemisahan Semesta Data Distribusi vs Neraca:**
   - Data Distribusi (5.169 transaksi di testing DB) dan Neraca (2.896 transaksi) tersimpan pada tabel terpisah.
   - Single Dashboard menyajikan kedua metodologi melalui switcher eksplisit dan tidak pernah menjumlahkannya menjadi satu total komposit.
3. **Klasifikasi Komoditas Master HS:**
   - Kode HS `27075000` terverifikasi memiliki `oilgrp_code = 0` (NON MIGAS) dan `sektor = INDUSTRI PENGOLAHAN`.
4. **Presisi Kolom Moneter:**
   - Kolom `nilai` dan `berat` terverifikasi menggunakan `DECIMAL(20,6)` untuk mencegah *floating point drift*.

---

## 11. Cross-Role Security & Boundary Verification

Pengujian penetrasi batas hak akses (Skenario SEC-01 s.d. SEC-10):

| Target URL / Aksi | Peran Penguji | Respon Aktual | Status Keamanan |
| :--- | :--- | :---: | :---: |
| `GET /upload-data` | Pegawai (Role 2) | HTTP 403 Forbidden | **AMAN** |
| `GET /neraca/upload` | Pegawai (Role 2) | HTTP 403 Forbidden | **AMAN** |
| `POST /upload-data/process` | Pegawai (Role 2) | HTTP 403 Forbidden | **AMAN** |
| `GET /trash-data` | Pegawai (Role 2) | HTTP 403 Forbidden | **AMAN** |
| `GET /manajemen-data/720/export` | Pegawai (Role 2) | HTTP 403 Forbidden | **AMAN** |
| `POST /manajemen-data/720/toggle-release` | Pegawai (Role 2) | HTTP 403 Forbidden | **AMAN** |
| `GET /upload-data` | Viewer (Role 3) | HTTP 403 Forbidden | **AMAN** |
| `POST /upload-data/process` | Viewer (Role 3) | HTTP 403 Forbidden | **AMAN** |
| `GET /trash-data` | Viewer (Role 3) | HTTP 403 Forbidden | **AMAN** |
| `GET /activity-logs` | Viewer (Role 3) | HTTP 302 Redirect | **AMAN** |

---

## 12. UI/UX, Responsive, & Browser Verification

Pengujian antarmuka pengguna pada browser (Desktop & Mobile):

1. **Resolusi Desktop (1280x800):**
   - Transisi antar tab berlangsung instan tanpa reload halaman penuh.
   - Grafik tren Chart.js 4 dan peta Leaflet.js ter-render dengan rasio proporsional.
2. **Resolusi Mobile (375x812):**
   - Segmented control switcher dan filter bar tertata rapi dalam layout bertumpuk (*stacked*).
   - Tabel pivot komoditas dan time series dilengkapi scroll horizontal tanpa merusak pembungkus utama (*wrapper*).
3. **Kesehatan Konsol JavaScript:**
   - **0 console error / uncaught exception** tercatat pada seluruh penjelajahan tab dashboard.
   - Panggilan `.toLocaleString()` terlindungi dengan guard nilai valid, mencegah runtime error saat nilai bernilai `null`.

---

## 13. Summary of UAT Findings & Risk Assessment

Status lengkap temuan berdasarkan audit Phase 2G.6 hingga 2G.9:

| Kode | Judul Temuan | Tingkat Keparahan | Status Terkini | Tindak Lanjut |
| :--- | :--- | :---: | :---: | :--- |
| **F-01** | Penundaan Parsing Excel | Low | Deferred | Pipeline DBF aktif sebagai jalur primer produksi. |
| **F-02** | Klasifikasi HS 27075000 (Non-Migas) | Medium | Frozen by Policy | Dipertahankan `oilgrp_code = 0` sesuai BRS historis BPS. |
| **F-03** | Asimetri Periode Trade Balance | Medium | **RESOLVED (2G.8)** | Banner amber otomatis muncul saat rentang bulan berbeda. |
| **F-04** | Fail-Closed Read Guard | High (Lama) | **RESOLVED (2G.5)** | Isolasi status RILIS/PRIVATE di tingkat service. |
| **F-05** | Tooltip Definisi Metodologi | Low | **RESOLVED (2G.8)** | Popover interaktif memuat konsep POD vs PROVORIG. |
| **F-06** | SOP Backup Operasional | Info | Documented SOP | Wajib mysqldump sebelum tindakan produksi apa pun. |
| **F-07** | Audit Trail Reagregasi | Info | Documented SOP | Seluruh mutasi batch tercatat pada `activity_logs`. |

---

## 14. Traceability Matrix

| Kebutuhan Bisnis BPS | Pengujian Terkait | Skenario UAT | Status |
| :--- | :--- | :---: | :---: |
| Single Dashboard 4 Tab | `Phase2gBladeAlignmentTest` | SA-01 s.d. SA-05, UI-01 | **PASS** |
| Pembatasan Hak Akses Pegawai | `Phase2g3ExecutionVerificationTest` | PG-06 s.d. PG-16, SEC-01 s.d. SEC-07 | **PASS** |
| Visibilitas Telaah Eksekutif | `Phase2g8ControlledRemediationTest` | VE-01 s.d. VE-14 | **PASS** |
| Integritas Pertumbuhan BPS | `Phase2g5StatisticalAuditTest` | CS-01 s.d. CS-04 | **PASS** |
| Pemisahan Distribusi & Neraca | `DatabaseSafetyTest` | CS-05, SA-14, SA-15 | **PASS** |
| Perlindungan DB Operasional | `phase2g9_preflight.php` | SA-18, SEC-01 s.d. SEC-10 | **PASS** |

---

## 15. Independent Specialist Reviews & Verdicts

Empat agen spesialis independen telah meninjau hasil eksekusi Phase 2G.9:

1. **Statistics Auditor:**
   > *"Formula pertumbuhan BPS resmi (BCMath scale 8 -> round 2) terbukti konsisten pada skenario CS-01 hingga CS-04. Pemisahan Distribusi dan Neraca terjaga ketat tanpa pencampuran semesta data. Status klasifikasi HS 27075000 tetap non-migas sesuai rekam historis publikasi BPS. Trade balance asimetris tertangani dengan transparan. **VERDICT: APPROVED (PASS)**."*

2. **Security Auditor:**
   > *"Pemeriksaan batas otorisasi lintas-peran membuktikan bahwa Pegawai (Role 2) dan Viewer (Role 3) tidak dapat mengakses fungsi upload, rilis data, atau trash data baik melalui UI maupun penembakan URL langsung (HTTP 403 Forbidden). Kebocoran data PRIVATE batch #720 terbukti 0. Audit trail Pegawai terisolasi ke ID sendiri. **VERDICT: APPROVED (PASS)**."*

3. **Database Guardian:**
   > *"Database operasional `sidatex` terbukti 100% utuh tanpa mutasi baris, migrasi, atau penulisan data uji (9 metrik baseline identik). Seluruh pengujian diarahkan ke `sidatex_testing`. Batch #71 (RILIS), Batch #720 (PRIVATE), 30.147 baris historis MTS, dan 2.896 transaksi Neraca terjaga keasliannya. **VERDICT: APPROVED (PASS)**."*

4. **Independent Reviewer:**
   > *"Laporan UAT menyajikan bukti empiris yang solid dari 77 skenario pengujian tanpa klaim palsu. Pembedaan status `TECHNICALLY READY FOR UAT — BUSINESS ACCEPTANCE PENDING` telah ditegakkan dengan tepat. Tidak ada perbaikan kode liar yang dilakukan selama UAT. **VERDICT: APPROVED (PASS)**."*

---

## 16. Recommendations & Sign-Off Readiness

### Kesimpulan & Rekomendasi Akhir:
1. **Kesiapan Teknis:** Aplikasi SIDATEX BPS Provinsi Riau dinyatakan **100% Lolos UAT Teknis** dan siap memasuki tahap peninjauan dan penerimaan bisnis langsung oleh pemangku kepentingan BPS Provinsi Riau.
2. **Tindak Lanjut Pemangku Kepentingan:**
   - Berikan salinan formulir `SIDATEX_PHASE_2G_9_UAT_USER_FEEDBACK.md` kepada Tim Fungsi Statistik Distribusi, Neraca Wilayah, IPDS, dan Pimpinan BPS Provinsi Riau untuk pengujian operasional lapangan dan penandatanganan berita acara penerimaan.
3. **Kondisi Berhenti:**
   > [!IMPORTANT]
   > Sesuai pedoman baku, pekerjaan dihentikan pada tahap ini. Jangan memulai Phase 2G.10 atau tindakan implementasi baru apa pun tanpa instruksi tertulis eksplisit dari pengguna.
