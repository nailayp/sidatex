# SIDATEX — PHASE 2G.9 UAT TEST CASES MATRIX
**Sistem Informasi Data Ekspor-Impor BPS Provinsi Riau**
**Document Version:** 1.0 (Final Technical UAT)
**Execution Date:** 19 November 2026
**Environment:** `sidatex_testing` (MySQL 127.0.0.1:3306)
**Execution Mode:** Automated Test Runner & Direct Request Kernel Emulation

---

## 1. Ringkasan Eksekusi Matriks UAT

| Kategori Pengujian | Kode Kasus | Total Skenario | Lolos (PASS) | Gagal (FAIL) | Tingkat Kelulusan |
| :--- | :--- | :---: | :---: | :---: | :---: |
| Super Admin (Role 1) | SA-01 s.d. SA-18 | 18 | 18 | 0 | 100% |
| Pegawai BPS (Role 2) | PG-01 s.d. PG-17 | 17 | 17 | 0 | 100% |
| Viewer Eksekutif (Role 3) | VE-01 s.d. VE-14 | 14 | 14 | 0 | 100% |
| Metodologi & Statistik | CS-01 s.d. CS-10 | 10 | 10 | 0 | 100% |
| Keamanan & Batas Hak Akses | SEC-01 s.d. SEC-10 | 10 | 10 | 0 | 100% |
| UI/UX & Browser Responsive | UI-01 s.d. UI-08 | 8 | 8 | 0 | 100% |
| **TOTAL KESELURUHAN** | — | **77** | **77** | **0** | **100%** |

---

## 2. Matriks Pengujian Super Admin (Role 1)

| ID | Modul | Skenario Pengujian | Hasil yang Diharapkan | Hasil Faktual | Status | Bukti Teknis |
| :--- | :--- | :--- | :--- | :--- | :---: | :--- |
| **SA-01** | Dashboard | Akses rute utama Single Dashboard (`GET /dashboard`) | HTTP 200, layout dashboard ter-render lengkap | HTTP 200 OK | **PASS** | HTML Dashboard termuat sempurna |
| **SA-02** | Dashboard | Akses Tab 1 Overview (`GET /dashboard?tab=overview`) | HTTP 200, kartu KPI dan grafik tren bulanan termuat | HTTP 200 OK | **PASS** | Section `tabOverview` aktif |
| **SA-03** | Dashboard | Akses Tab 2 Komoditas (`GET /dashboard?tab=komoditas`) | HTTP 200, pivot komoditas HS 2-digit & master | HTTP 200 OK | **PASS** | Section `tabKomoditas` aktif |
| **SA-04** | Dashboard | Akses Tab 3 Wilayah (`GET /dashboard?tab=wilayah`) | HTTP 200, peta Leaflet & sebaran kab/kota | HTTP 200 OK | **PASS** | Section `tabWilayah` aktif |
| **SA-05** | Dashboard | Akses Tab 4 Trade Balance (`GET /dashboard?tab=trade-balance`) | HTTP 200, tabel ekspor, impor, neraca perdagangan | HTTP 200 OK | **PASS** | Section `tabTradeBalance` aktif |
| **SA-06** | Data Analytics | Akses API data analitik (`GET /dashboard/data?tipe_sumber=distribusi&jenis=Ekspor&tahun=2026`) | HTTP 200, memuat data batch `PRIVATE` (#720 Agustus) | HTTP 200, Agustus FOB = $2.218.155.995,50 | **PASS** | Batch #720 teragregasi untuk Role 1 |
| **SA-07** | Distribusi BRS | Akses metrik BRS resmi Distribusi (`GET /distribusi/metrics?tahun=2026&bulan=9`) | HTTP 200, memuat angka resmi batch `RILIS` (#71 September) | HTTP 200, FOB = $2.301.809.204,59 | **PASS** | Formula resmi BPS dihitung tepat |
| **SA-08** | Upload Data | Akses antarmuka pengunggahan Distribusi (`GET /upload-data`) | HTTP 200, formulir unggah DBF Distribusi terbuka | HTTP 200 OK | **PASS** | View `upload-data.blade.php` |
| **SA-09** | Upload Data | Akses antarmuka pengunggahan Neraca (`GET /neraca/upload`) | HTTP 200, formulir unggah DBF Neraca terbuka | HTTP 200 OK | **PASS** | View `neraca/upload.blade.php` |
| **SA-10** | Manajemen Data | Akses daftar batch dokumen (`GET /manajemen-data`) | HTTP 200, menampilkan seluruh batch (`RILIS` & `PRIVATE`) | HTTP 200, Batch #71 dan #720 tampil | **PASS** | Kolom status menampilkan badge lengkap |
| **SA-11** | Ekspor Excel | Unduh transaksi batch `RILIS` (`GET /manajemen-data/71/export`) | HTTP 200 binary response, file `.xlsx` di-stream | HTTP 200 (Streamed XLSX) | **PASS** | Header `Content-Type: spreadsheetml` |
| **SA-12** | Ekspor Excel | Unduh transaksi batch `PRIVATE` (`GET /manajemen-data/720/export`) | HTTP 200 binary response diizinkan bagi Super Admin | HTTP 200 (Streamed XLSX) | **PASS** | File terunduh tanpa kebocoran error |
| **SA-13** | Trash Data | Akses antarmuka pemulihan data (`GET /trash-data`) | HTTP 200, modul Trash Data terbuka | HTTP 200 OK | **PASS** | Super Admin memiliki akses penuh |
| **SA-14** | Audit Trail | Akses riwayat aktivitas global (`GET /activity-logs`) | HTTP 200, memuat log audit seluruh pengguna sistem | HTTP 200 OK | **PASS** | Query tanpa restriksi `user_id` |
| **SA-15** | Metodologi | Akses metrik metodologi Neraca (`GET /neraca/metrics?tahun=2026&bulan=9`) | HTTP 200, data terpisah secara independen dari Distribusi | HTTP 200 OK | **PASS** | Endpoint independen tanpa tumpang tindih |
| **SA-16** | Blade UI | Deteksi elemen indikator asimetri periode Trade Balance (F-03) | Elemen alert `alertTradeAsymmetry` tersedia di DOM | ID elemen terdeteksi di Tab 4 | **PASS** | Komponen remediasi F-03 aktif |
| **SA-17** | Blade UI | Deteksi popover tooltip definisi resmi BPS (F-05) | Elemen `methodologyTooltipPopover` memuat POD vs PROVORIG | Definisi resmi BPS terverifikasi | **PASS** | Komponen remediasi F-05 aktif |
| **SA-18** | Database Safety | Verifikasi perlindungan database operasional selama pengujian | Koneksi aktif PDO runtime tetap `sidatex_testing` | `sidatex_testing` | **PASS** | Database operasional `sidatex` utuh 100% |

---

## 3. Matriks Pengujian Pegawai BPS (Role 2)

| ID | Modul | Skenario Pengujian | Hasil yang Diharapkan | Hasil Faktual | Status | Bukti Teknis |
| :--- | :--- | :--- | :--- | :--- | :---: | :--- |
| **PG-01** | Dashboard | Akses rute utama Single Dashboard (`GET /dashboard`) | HTTP 200, dashboard publik/rilis terbuka | HTTP 200 OK | **PASS** | Hak akses baca Pegawai terpenuhi |
| **PG-02** | Dashboard | Akses Tab 1 Overview (`GET /dashboard?tab=overview`) | HTTP 200, angka berdasarkan batch `RILIS` saja | HTTP 200 OK | **PASS** | Data berstatus `RILIS` ditampilkan |
| **PG-03** | Dashboard | Akses Tab 2 Komoditas (`GET /dashboard?tab=komoditas`) | HTTP 200, komoditas teragregasi dari data `RILIS` | HTTP 200 OK | **PASS** | Pivot bersih dari data draft |
| **PG-04** | Dashboard | Akses Tab 3 Wilayah (`GET /dashboard?tab=wilayah`) | HTTP 200, sebaran wilayah dari data `RILIS` | HTTP 200 OK | **PASS** | Peta beroperasi normal |
| **PG-05** | Dashboard | Akses Tab 4 Trade Balance (`GET /dashboard?tab=trade-balance`) | HTTP 200, neraca perdagangan dari data `RILIS` | HTTP 200 OK | **PASS** | Asimetri status ditangani dengan aman |
| **PG-06** | RBAC Security | Pengambilan data analitik (`GET /dashboard/data?tipe_sumber=distribusi&jenis=Ekspor&tahun=2026`) | HTTP 200, batch `PRIVATE` (#720) disembunyikan (FOB = $0,00) | HTTP 200, Agustus FOB = $0,00 | **PASS** | Zero-leakage data PRIVATE |
| **PG-07** | Distribusi BRS | Permintaan metrik periode dengan data `PRIVATE` (`GET /distribusi/metrics?tahun=2026&bulan=8`) | HTTP 200, nilai FOB = $0,00 (tidak ada data RILIS di bulan 8) | FOB = $0,00 | **PASS** | Data belum rilis tidak bocor |
| **PG-08** | RBAC Security | Upaya akses halaman Upload Distribusi (`GET /upload-data`) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** | Middleware `superadmin` aktif |
| **PG-09** | RBAC Security | Upaya akses halaman Upload Neraca (`GET /neraca/upload`) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** | Middleware `superadmin` aktif |
| **PG-10** | RBAC Security | Upaya eksekusi simpan upload (`POST /upload-data/process`) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** | Pemeriksaan hak akses tingkat controller |
| **PG-11** | Manajemen Data | Akses daftar batch dokumen (`GET /manajemen-data`) | HTTP 200, hanya memuat batch `RILIS` (#71); #720 tersembunyi | Batch #71 tampil, #720 tidak ada | **PASS** | Isolasi baris database berhasil |
| **PG-12** | Ekspor Excel | Unduh dokumen batch `RILIS` (`GET /manajemen-data/71/export`) | HTTP 200 binary response diizinkan | HTTP 200 (Streamed XLSX) | **PASS** | Pegawai diizinkan mengunduh RILIS |
| **PG-13** | RBAC Security | Upaya paksa unduh batch `PRIVATE` via URL (`GET /manajemen-data/720/export`) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** | Akses direct URL dicegat ketat |
| **PG-14** | RBAC Security | Upaya publikasi status batch (`POST /manajemen-data/720/toggle-release`) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** | Mutasi status batch dilindungi |
| **PG-15** | RBAC Security | Upaya akses modul Trash Data (`GET /trash-data`) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** | Akses data terhapus dicegat |
| **PG-16** | Audit Trail | Akses log aktivitas pengguna (`GET /activity-logs`) | HTTP 200, hanya memuat rekaman milik pengguna sendiri | HTTP 200 (Scoped to self) | **PASS** | Filter `user_id = Auth::id()` aktif |
| **PG-17** | UI Component | Tampilan Tab 4 Trade Balance dan Tooltip Metodologi | Banner asimetri dan popover definisi tampil tanpa error | Komponen Blade ter-render rapi | **PASS** | Tidak ada crash JavaScript/DOM |

---

## 4. Matriks Pengujian Viewer Eksekutif (Role 3)

| ID | Modul | Skenario Pengujian | Hasil yang Diharapkan | Hasil Faktual | Status | Bukti Teknis |
| :--- | :--- | :--- | :--- | :--- | :---: | :--- |
| **VE-01** | Dashboard | Akses rute utama Single Dashboard (`GET /dashboard`) | HTTP 200, dashboard eksekutif terbuka | HTTP 200 OK | **PASS** | Akses monitoring eksekutif aktif |
| **VE-02** | Dashboard | Akses Tab 1 Overview (`GET /dashboard?tab=overview`) | HTTP 200, visualisasi makro termuat | HTTP 200 OK | **PASS** | Tab 1 beroperasi normal |
| **VE-03** | Dashboard | Akses Tab 4 Trade Balance (`GET /dashboard?tab=trade-balance`) | HTTP 200, tabel neraca perdagangan lengkap | HTTP 200 OK | **PASS** | Tab 4 beroperasi normal |
| **VE-04** | Data Analytics | Akses API analitik (`GET /dashboard/data?tipe_sumber=distribusi&jenis=Ekspor&tahun=2026`) | HTTP 200, memuat batch `PRIVATE` (#720) untuk telaah | HTTP 200, Agustus FOB = $2.218.155.995,50 | **PASS** | Hak telaah eksekutif terpenuhi |
| **VE-05** | RBAC Security | Upaya akses halaman Upload Distribusi (`GET /upload-data`) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** | Viewer dilarang mengunggah data |
| **VE-06** | RBAC Security | Upaya akses halaman Upload Neraca (`GET /neraca/upload`) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** | Viewer dilarang mengunggah data |
| **VE-07** | RBAC Security | Upaya eksekusi simpan upload (`POST /upload-data/process`) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** | Endpoint mutasi terkunci |
| **VE-08** | Manajemen Data | Akses daftar batch dokumen (`GET /manajemen-data`) | HTTP 200, menampilkan batch `RILIS` dan `PRIVATE` untuk evaluasi | Batch #71 dan #720 tampil | **PASS** | Visibilitas telaah eksekutif aktif |
| **VE-09** | Ekspor Excel | Unduh dokumen batch `RILIS` (`GET /manajemen-data/71/export`) | HTTP 200 binary response diizinkan | HTTP 200 (Streamed XLSX) | **PASS** | Viewer diizinkan mengunduh RILIS |
| **VE-10** | Ekspor Excel | Unduh dokumen batch `PRIVATE` (`GET /manajemen-data/720/export`) | HTTP 200 binary response diizinkan untuk pra-rilis | HTTP 200 (Streamed XLSX) | **PASS** | Unduhan telaah internal berhasil |
| **VE-11** | RBAC Security | Upaya publikasi status batch (`POST /manajemen-data/720/toggle-release`) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** | Viewer tidak berwenang rilis data |
| **VE-12** | RBAC Security | Upaya akses modul Trash Data (`GET /trash-data`) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** | Trash Data terkunci bagi Viewer |
| **VE-13** | RBAC Security | Upaya akses log aktivitas internal (`GET /activity-logs`) | HTTP 302 Redirect ke dashboard dengan pesan peringatan | HTTP 302 Redirect | **PASS** | Audit trail diisolasi dari Viewer |
| **VE-14** | UI Component | Tampilan Tab 4 Trade Balance dan Tooltip Metodologi | Komponen asimetri dan definisi metodologi tampil rapi | Komponen Blade ter-render rapi | **PASS** | UI responsif dan informatif |

---

## 5. Matriks Pengujian Metodologi & Statistik (Core Statistics)

| ID | Parameter Uji | Skenario | Hasil yang Diharapkan | Hasil Faktual | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **CS-01** | Pertumbuhan MoM/YoY Positif | Nilai berjalan 1.250, periode pembanding 1.000 | Pertumbuhan = +25,00% via BCMath | `25%` | **PASS** |
| **CS-02** | Pertumbuhan Pembagi Nol | Nilai berjalan 100, periode pembanding 0 | Mengembalikan `null` (N/A), tidak membagi dengan nol | `NULL (N/A)` | **PASS** |
| **CS-03** | Pertumbuhan Penurunan | Nilai berjalan 750, periode pembanding 1.000 | Pertumbuhan = -25,00% via BCMath | `-25%` | **PASS** |
| **CS-04** | Trade Balance Data Kosong | Periode 2026-09 di mana data Impor belum masuk | Status = `Data Impor Belum Tersedia`, nilai = `null` (bukan 0) | `Data Impor Belum Tersedia` | **PASS** |
| **CS-05** | Pemisahan Distribusi/Neraca | Verifikasi pemisahan tabel transaksi fisik | Distribusi dan Neraca tersimpan di tabel berbeda | `distribusi`: 5.169, `neraca`: 2.896 | **PASS** |
| **CS-06** | Klasifikasi HS 27075000 | Validasi integritas pengelompokan komoditas | `oilgrp_code = 0` (NON MIGAS), `sektor = INDUSTRI PENGOLAHAN` | `oilgrp_code = 0`, Industri | **PASS** |
| **CS-07** | Presisi Kolom Moneter | Tipe kolom nilai transaksi pada skema database | Bertipe `DECIMAL(20,6)` mencegah floating drift | `decimal(20,6)` terverifikasi | **PASS** |
| **CS-08** | Isolasi Data Historis | Kestabilan 30.147 baris `monthly_trade_summaries` | Tetap berstatus `PRIVATE` dan tidak termutasi | Tepat 30.147 baris | **PASS** |
| **CS-09** | Isolasi Modul Trash | Pengecualian batch yang berada di Trash | Batch di Trash dikeluarkan dari seluruh kalkulasi aktif | 0 batch trashed dihitung aktif | **PASS** |
| **CS-10** | Agregasi Asal Barang (MOTS) | Pembentukan ringkasan bulanan provinsi asal | Terbentuk record agregasi valid per provinsi asal | 6 baris MOTS terbentuk | **PASS** |

---

## 6. Matriks Keamanan & Batas Hak Akses (Security & Boundaries)

| ID | Skenario Keamanan | Peran yang Diuji | Hasil yang Diharapkan | Hasil Faktual | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **SEC-01** | Direct URL Probing `/upload-data` | Pegawai (Role 2) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** |
| **SEC-02** | Direct URL Probing `/neraca/upload` | Pegawai (Role 2) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** |
| **SEC-03** | Direct URL Probing `/trash-data` | Pegawai (Role 2) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** |
| **SEC-04** | Direct URL Probing `/upload-data` | Viewer (Role 3) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** |
| **SEC-05** | Direct URL Probing `/trash-data` | Viewer (Role 3) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** |
| **SEC-06** | Ekspor Batch Private via ID URL | Pegawai (Role 2) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** |
| **SEC-07** | Mutasi Status Batch via POST | Pegawai (Role 2) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** |
| **SEC-08** | Mutasi Status Batch via POST | Viewer (Role 3) | HTTP 403 Forbidden | HTTP 403 Forbidden | **PASS** |
| **SEC-09** | Isolasi Rekaman Audit Trail | Pegawai (Role 2) | Hanya menampilkan log user sendiri | Filter `user_id` aktif | **PASS** |
| **SEC-10** | Pengalihan Akses Audit Trail | Viewer (Role 3) | HTTP 302 Redirect ke dashboard | HTTP 302 Redirect | **PASS** |

---

## 7. Matriks UI/UX & Browser Responsive

| ID | Komponen / Tampilan | Skenario Pengujian | Hasil yang Diharapkan | Hasil Faktual | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **UI-01** | Single Dashboard Desktop | Navigasi 4 Tab pada resolusi desktop (1280x800) | Semua tab berpindah tanpa reload, layout utuh | Transisi tab mulus, 0 error | **PASS** |
| **UI-02** | Single Dashboard Mobile | Navigasi Tab pada viewport mobile (375x812) | Menu responsif, layout kartu stack vertikal | Tampilan responsif bersih | **PASS** |
| **UI-03** | Popover Tooltip Metodologi | Klik tombol info metodologi (`#btnMethodologyInfo`) | Popover terbuka, menampilkan teks BPS POD vs PROVORIG | Popover toggle berfungsi | **PASS** |
| **UI-04** | Banner Asimetri Periode | Render tabel Trade Balance saat periode asimetris | Banner amber muncul dengan teks penjelasan bulan | Banner informatif tampil | **PASS** |
| **UI-05** | Penanganan Nilai Null | Tampilan nilai Trade Balance saat data kosong | Ditampilkan sebagai dash (`—`), bukan `US$ 0,00` atau `NaN` | Menampilkan `—` secara konsisten | **PASS** |
| **UI-06** | Browser Console Errors | Pemeriksaan konsol JavaScript selama navigasi | 0 error, 0 uncaught exception | 0 console error terdeteksi | **PASS** |
| **UI-07** | Pivot Table Komoditas | Render tabel komoditas dan time series | Scroll horizontal rapi, header tetap terbaca | Tabel terstruktur dengan rapi | **PASS** |
| **UI-08** | Peta Interaktif Leaflet | Render peta sebaran perdagangan per pelabuhan | Marker dan tooltip pelabuhan muncul tepat posisi | Peta Leaflet termuat normal | **PASS** |
