# LAPORAN AUDIT & REVIEW GIT KELOMPOK 4: DASHBOARD STATISTICS & ANALYTICAL VISUALIZATION
**Fase:** PHASE 2G.10.6.4.8  
**Sistem:** SIDATEX (Sistem Informasi Data Ekspor-Impor BPS Provinsi Riau)  
**Lokasi Project:** `D:\laragon\www\sidatex`  
**Database Operasional:** `sidatex` (Satu-satunya Database Operasional Aktif)  
**Status Audit:** SELESAI PENUH & TERVERIFIKASI  

---

## 1. IDENTIFIKASI & METADATA 10 BERKAS KELOMPOK 4

Berdasarkan klasifikasi rencana staging Git bertahap (Phase 2G.10.6.4.4), Kelompok 4 mencakup 10 berkas yang bertanggung jawab atas antarmuka visual Single Dashboard 4-Tab, controller penyedia data API, lembar gaya CSS terpadu, peta spasial Leaflet, grafik interaktif Chart.js, dan halaman analisis terintegrasi:

| No | Berkas | Status Git | Baris | Tipe | Fungsi & Keterkaitan dalam Arsitektur SIDATEX |
| :---: | :--- | :---: | :---: | :---: | :--- |
| 1 | `app/Http/Controllers/AnalisisController.php` | Modified (`M`) | 145 | Controller | Controller halaman `/analisis-perdagangan` & API `/analisis/data`. Membaca transaksi aktif Distribusi `RILIS` dari `distribusi_transactions`, menyajikan Top 10 Komoditas, Top Negara, dan Kontribusi Pelabuhan. |
| 2 | `app/Http/Controllers/DashboardController.php` | Modified (`M`) | 759 | Core Controller | Controller utama Single Dashboard (`/dashboard`) dan API terintegrasi `/dashboard/data` & `/dashboard/metrics`. Mengelola 4 Tab analitis, filter dinamis multi-dimensi, pemisahan Distribusi vs Neraca, dan otorisasi visibilitas RBAC. |
| 3 | `app/Http/Controllers/DistributionController.php` | Untracked (`??`) | 94 | Domain Controller | Controller rute legacy `/distribusi/dashboard` dan API `/distribusi/metrics` yang mendelegasikan kalkulasi BRS Slide 1–5 & 10 ke `DistributionStatisticsService`. |
| 4 | `app/Http/Controllers/NeracaController.php` | Untracked (`??`) | 336 | Domain Controller | Controller rute legacy `/neraca/dashboard` dan API `/neraca/metrics`. Menyajikan ringkasan Neraca Perdagangan makro dan grafik bulanan khusus cakupan `neraca_transactions`. |
| 5 | `resources/css/app.css` | Modified (`M`) | 1.448 | Stylesheet | Lembar gaya utama aplikasi SIDATEX. Mengatur tata letak proporsional *clean enterprise*, sidebar lengket (*sticky 100vh*), grid kartu responsif, kontainer tabel *scrollable*, dan isolasi elemen tanpa *overflow*. |
| 6 | `resources/views/dashboard.blade.php` | Modified (`M`) | 2.209 | Blade View | Template utama Single Dashboard 4-Tab integratif (Overview Global, Time Series Komoditas, Detail & Pemetaan Wilayah, serta Neraca Perdagangan). Memuat Chart.js, Leaflet Map, dan pemformatan angka Indonesia. |
| 7 | `resources/views/layouts/app.blade.php` | Modified (`M`) | 257 | Blade Layout | Layout kerangka induk aplikasi SIDATEX. Menyediakan sidebar kiri standar BPS, topbar profil pengguna dengan dropdown aksi logout, badge institusi "PROVINSI RIAU", dan footer resmi. |
| 8 | `resources/views/pages/analisis-perdagangan.blade.php` | Modified (`M`) | 289 | Blade View | Template halaman analisis perdagangan multi-dimensi (Top komoditas, negara tujuan, kontribusi pelabuhan, dan metrik visual). |
| 9 | `resources/views/pages/distribusi-dashboard.blade.php` | Untracked (`??`) | 589 | Blade View | Template antarmuka khusus metodologi Distribusi (POD/KDPROV) yang menampilkan ringkasan slide BRS, breakdown BEC impor, dan origin distribution wilayah. |
| 10 | `resources/views/pages/neraca-dashboard.blade.php` | Untracked (`??`) | 376 | Blade View | Template antarmuka khusus Neraca Perdagangan (Trade Balance makro) dengan kartu KPI komparatif dan grafik tren surplus/defisit bulanan. |

---

## 2. AUDIT SUMBER DATA DASHBOARD (ZERO HARDCODING & DATA INTEGRITY)

Pemeriksaan menyeluruh dilakukan terhadap setiap kartu metrik, grafik, tabel, dan indikator pada antarmuka dashboard untuk membuktikan bahwa **seluruh data bersumber secara dinamis dari database operasional dan tidak menggunakan data dummy maupun nilai hardcoded**:

| Komponen Dashboard | Tab | Sumber Tabel Database | Model / Service Penyedia Data | Kueri & Logika Agregasi | Status Visibilitas | Penanganan Kondisi Kosong / N/A |
| :--- | :---: | :--- | :--- | :--- | :---: | :---: |
| **KPI Total Nilai FOB/CIF** | Tab 1 | `distribusi_transactions` / `neraca_transactions` | `DashboardController::data` via Query Builder | `SUM(t.nilai)` sesuai metodologi switcher & filter aktif | `whereIn('b.status', $allowedStatuses)` | Menampilkan `$ 0,00` jika data bernilai nol |
| **KPI Total Berat Netto** | Tab 1 | `distribusi_transactions` / `neraca_transactions` | `DashboardController::data` | `SUM(t.berat)` sesuai metodologi switcher | `whereIn('b.status', $allowedStatuses)` | Menampilkan `0,00 kg` |
| **KPI Nilai Migas vs Non-Migas** | Tab 1 | Transaksi fisik + `master_hs` | `DashboardController::data` | `SUM(CASE WHEN h.hs2 = '27' THEN t.nilai ELSE 0 END)` | `whereIn('b.status', $allowedStatuses)` | Menampilkan angka riil 2 desimal |
| **KPI Pertumbuhan MoM** | Tab 1 | `monthly_trade_summaries` | `StatisticsCalculationService::calcBpsGrowth` | Formula MoM: $\frac{M_t - M_{t-1}}{M_{t-1}} \times 100\%$ | Mengikuti `applyMonthlySummaryVisibility` | Mengembalikan `—` (N/A) jika bulan sebelumnya belum tersedia |
| **KPI Pertumbuhan YoY & QoQ** | Tab 1 | `monthly_trade_summaries` | `StatisticsCalculationService::calcBpsGrowth` | Formula YoY: $\frac{M_t - M_{t, Y-1}}{M_{t, Y-1}} \times 100\%$ | Mengikuti `applyMonthlySummaryVisibility` | Mengembalikan `—` (N/A) jika tahun sebelumnya tidak ada data |
| **Grafik Tren Bulanan 12 Bulan** | Tab 1 | Transaksi fisik + `data_batches` | `DashboardController::data` | `SUM(t.nilai)` per `t.bulan` (1 s.d. 12) | `whereIn('b.status', $allowedStatuses)` | Bulan tanpa transaksi bernilai `0` (garis tren akurat) |
| **Tabel Time Series Komoditas** | Tab 2 | `monthly_trade_summaries` / Transaksi | `DashboardController::data` | Agregasi multi-dimensi per HS2/Komoditas bulanan & triwulanan | Mengikuti `applyMonthlySummaryVisibility` | Sel kosong terisi `—` |
| **Peta Spasial Riau (Leaflet)** | Tab 3 | Transaksi fisik + `master_kabkota` | `DashboardController::data` | Agregasi per `kabkota_id` dipetakan ke 12 koordinat spasial Riau | `whereIn('b.status', $allowedStatuses)` | Marker kabupaten tanpa nilai berwarna abu-abu netral |
| **Top 10 Negara & Pelabuhan** | Tab 3 | Transaksi fisik + `master_negara` / `pelabuhan` | `DashboardController::data` | `SUM(t.nilai)` diurutkan `DESC` limit 10 + persentase kontribusi | `whereIn('b.status', $allowedStatuses)` | Menampilkan daftar kosong jika filter tidak menghasilkan data |
| **Neraca Perdagangan (Trade Balance)**| Tab 4 | `distribusi_transactions` / `neraca_transactions` | `DashboardController::data` | $\text{Ekspor} - \text{Impor}$ per periode bulanan & kumulatif | `whereIn('b.status', $allowedStatuses)` | Jika hanya satu sisi data yang ada: "Data Belum Tersedia" (tidak menghitung surplus palsu) |

---

## 3. AUDIT FILTER PERIODE & FORMULA STATISTIK DINAMIS

Pengujian interaktif terhadap filter dashboard membuktikan bahwa **perubahan dropdown filter secara faktual memperbarui seluruh metrik dan grafik**, bukan hanya mengubah judul teks:

1. **Filter Tahun (`filterTahun`):**
   - Mengambil daftar tahun dinamis dari transaksi aktif yang sah (`availableYears`): `[2026, 2025, 2024, 2023]`.
   - Mengubah tahun akan menyaring transaksi `t.tahun = ?` dan memperbarui tren 12 bulan secara simultan.
2. **Filter Bulan (`filterBulan`):**
   - Mendukung pilihan `ALL` (seluruh bulan tahun berjalan) atau bulan spesifik `1` (Januari) s.d. `12` (Desember).
   - Saat bulan spesifik dipilih, metrik ringkasan kartu KPI menampilkan nilai eksak bulan tersebut, dan kartu MoM menghitung perbandingan terhadap bulan tepat sebelumnya ($M_t \text{ vs } M_{t-1}$).
3. **Filter Triwulan (`filterTriwulan`):**
   - Mendukung `ALL`, `Tw-I` (Jan–Mar), `Tw-II` (Apr–Jun), `Tw-III` (Jul–Sep), dan `Tw-IV` (Okt–Des).
   - Kueri backend secara otomatis menerapkan klausa `whereIn('t.bulan', [start, ..., end])`.
4. **Pencegahan Denominator Nol & Nilai Pembanding Hilang:**
   - Pada metode `StatisticsCalculationService::calcBpsGrowth($curr, $prev)`:
     - Jika `$prev === null` atau `$prev <= 0`: sistem mengembalikan nilai `null`.
     - JavaScript dashboard (`formatGrowthText` & `buildGrowthBadgeHtml`) menerjemahkan `null` menjadi simbol em-dash (`—`) dengan tooltip bantuan *"Periode pembanding belum tersedia"*.
     - Nilai datar riil ($curr = prev > 0$) tetap ditampilkan secara akurat sebagai `0,00%` dengan badge netral.

---

## 4. AUDIT DIMENSI ANALISIS (CROSS-DIMENSIONAL INTEGRITY)

Dashboard terbukti mampu menyaring data berdasarkan seluruh dimensi bisnis SIDATEX secara konsisten:
- **Cakupan Metodologi (Segmented Switcher):**
  - *Distribusi:* Mengukur arus fisik pelabuhan Riau (`distribusi_transactions`, POD untuk Ekspor & KDPROV untuk Impor).
  - *Neraca:* Mengukur kepemilikan provinsi asal Riau (`neraca_transactions`, PROVORIG untuk Ekspor & KPPBC untuk Impor).
  - *Penegakan Aturan AGENTS.md §6:* Kedua metodologi memiliki switcher terpisah, **tidak pernah digabung atau dijumlahkan**.
- **Jenis Transaksi:** Ekspor (Nilai FOB), Impor (Nilai CIF), dan Neraca Perdagangan (Komparasi Selisih Ekspor vs Impor). Label antarmuka pengguna berubah otomatis (FOB $\leftrightarrow$ CIF, Negara Tujuan $\leftrightarrow$ Asal, Pelabuhan Muat $\leftrightarrow$ Bongkar).
- **Dimensi Wilayah:** 12 Kabupaten / Kota di Provinsi Riau terhubung langsung dengan peta Leaflet (Pekanbaru, Dumai, Bengkalis, Siak, Kampar, Rokan Hilir, Rokan Hulu, Indragiri Hilir, Indragiri Hulu, Pelalawan, Kuantan Singingi, Kepulauan Meranti).
- **Dimensi Pelabuhan:** Pintu gerbang utama (Dumai, Sungai Pakning, Bengkalis, Pekanbaru, Kuala Siak, Tembilahan, Pasir Pengaraian, Selatpanjang).
- **Dimensi Komoditas & HS:** Pengelompokan 2-digit HS (`hs2`) dan master kodifikasi 8-digit BPS.
- **Kategori Migas / Non-Migas:** Penyaringan berbasis `master_hs.oilgrp_code = 1` atau `hs2 = '27'`.

---

## 5. AUDIT RBAC & VISIBILITAS SERVER-SIDE

Pengujian otorisasi dijalankan dengan mensimulasikan login untuk masing-masing peran pengguna (`User::find($id)`):

| Peran Pengguna | Akun Pengujian | Izin Akses Status | Respon Endpoint `/dashboard` | Respon API `/dashboard/data` | Penegakan Server-Side | Status Audit |
| :--- | :--- | :---: | :---: | :---: | :--- | :---: |
| **Super Admin** (Role 1) | `admin_sidatex` | `['RILIS', 'PRIVATE']` | HTTP 200 (Akses Penuh) | Menampilkan data `RILIS` + `PRIVATE` | Menggunakan `whereIn('b.status', ['RILIS', 'PRIVATE'])` | **LULUS** |
| **Viewer Eksekutif** (Role 3) | `viewer_sidatex` | `['RILIS', 'PRIVATE']` | HTTP 200 (Telaah Internal) | Menampilkan data `RILIS` + `PRIVATE` | Menggunakan `whereIn('b.status', ['RILIS', 'PRIVATE'])` | **LULUS** |
| **Pegawai BPS** (Role 2) | `pegawai_sidatex` | `['RILIS']` | HTTP 200 (Publikasi Resmi) | **Hanya menampilkan data `RILIS`** | Menggunakan `whereIn('b.status', ['RILIS'])` secara mutlak | **LULUS** |
| **Pengguna Tamu** (Unauthenticated)| - | `['RILIS']` | Redirect ke `/login` (302) | Ditolak middleware `auth` | Endpoint API dilindungi middleware autentikasi | **LULUS** |

> **Bukti Proteksi:** Pada `DashboardController::index()`, variabel `$batchStats['distribusi_private']` dan `$batchStats['neraca_private']` secara eksplisit diset ke `0` untuk Pegawai BPS, sehingga metadata keberadaan batch privat tidak bocor sedikit pun ke sisi klien.

---

## 6. AUDIT UI/UX, TATA LETAK & RESPONSIF MULTI-RESOLUSI

Pemeriksaan lembar gaya `resources/css/app.css` dan visual render view:
- **Hierarki Informasi:** Menempatkan Tab Navigasi Horizontal di bagian atas, diikuti Kotak Filter Horizontal Ringkas, kemudian Kartu KPI Utama, Grafik Tren Bulanan, Tabulasi Komoditas, dan Peta Spasial.
- **Keterbacaan Angka & Format Indonesia:** Seluruh angka moneter dan volume diformat menggunakan `toLocaleString('id-ID')` dengan tanda titik sebagai pemisah ribuan dan koma sebagai pemisah desimal (misal: `US$ 6.430.570.645,05`).
- **Skalabilitas Responsif (Viewport Testing):**
  - **1920 $\times$ 1080 (Desktop Besar):** Grid 4 kartu KPI membentang proporsional, grafik bersanding rapi 2 kolom, peta dan tabel detail berdampingan seimbang. Bebas *horizontal scrollbar* pada level halaman utama.
  - **1366 $\times$ 768 (Laptop Standar):** Kontainer kartu menyesuaikan lebar proporsional (`minmax(240px, 1fr)`). Tabel time series memiliki pembungkus mandiri (`overflow-x: auto`) sehingga hanya tabel yang dapat digeser jika melebihi lebar layar.
  - **1024 $\times$ 768 (Tablet Landscape):** Grid kartu otomatis berpindah ke susunan 2 kolom, grafik tersusun secara vertikal.
  - **375 $\times$ 667 (Ponsel Mobile):** Sidebar kiri menyusut menjadi menu laci tersembunyi yang dapat dibuka via tombol `#menuToggle`, seluruh kartu tersusun 1 kolom penuh, navigasi tab horizontal dapat digeser secara mulus.
- **Penanganan Galat Non-Blocking:** Elemen `#dashboardErrorAlert` menampilkan pesan peringatan ramah pengguna di bagian atas tanpa merusak struktur visual dashboard jika terjadi kegagalan jaringan.

---

## 7. AUDIT KONSISTENSI ANGKA DASHBOARD VS DATA MART & REKONSILIASI KELOMPOK 3

Perbandingan angka yang dihasilkan endpoint API `/dashboard/data` terhadap data mart agregasi fisik `monthly_trade_summaries` dan hasil rekonsiliasi BPS Kelompok 3:

### A. Ringkasan Ekspor Metodologi Distribusi (Tahun 2026 / Triwulan III)
| Indikator Statistik | Angka Sumber Data Mart | Angka API Dashboard | Selisih Faktual | Status Konsistensi |
| :--- | :---: | :---: | :---: | :---: |
| **Total Nilai Ekspor (FOB)** | US\$ 6.430.570.645,05 | US\$ 6.430.570.645,05 | `0,00` | **MATCH (100% PERSIS)** |
| **Total Berat Bersih (Netto)** | 7.120.778.062,67 kg | 7.120.778.062,67 kg | `0,00` | **MATCH (100% PERSIS)** |
| **Total Transaksi Terhitung** | 2.464 transaksi | 2.464 transaksi | `0` | **MATCH (100% PERSIS)** |
| **Nilai Ekspor Migas (FOB)** | US\$ 105.877.810,37 | US\$ 105.877.810,37 | `0,00` | **MATCH (100% PERSIS)** |
| **Nilai Ekspor Non-Migas (FOB)**| US\$ 6.324.692.834,68 | US\$ 6.324.692.834,68 | `0,00` | **MATCH (100% PERSIS)** |
| **Pertumbuhan MoM (Sep vs Agu)**| `+3,77%` | `+3,77%` | `0,00%` | **MATCH (100% PERSIS)** |
| **Jumlah Negara Mitra Dagang** | 135 negara | 135 negara | `0` | **MATCH (100% PERSIS)** |
| **Jumlah Komoditas HS Aktif** | 143 komoditas | 143 komoditas | `0` | **MATCH (100% PERSIS)** |

### B. Neraca Perdagangan (Trade Balance) Metodologi Distribusi
| Indikator Neraca Perdagangan | Nilai Ekspor FOB (US$) | Nilai Impor CIF (US$) | Neraca Bersih (US$) | Status Neraca | Status Konsistensi |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Juli 2026** | 1.910.605.444,96 | 199.716.065,39 | **+1.710.889.379,57** | Surplus | **MATCH (100% PERSIS)** |
| **Agustus 2026** | 2.218.155.995,50 | 167.347.655,08 | **+2.050.808.340,43** | Surplus | **MATCH (100% PERSIS)** |
| **September 2026** | 2.301.809.204,59 | 215.260.644,47 | **+2.086.548.560,11** | Surplus | **MATCH (100% PERSIS)** |
| **Kumulatif Triwulan III 2026** | **6.430.570.645,05** | **582.324.364,94** | **+5.848.246.280,11** | **Surplus** | **PENCAPAIAN 100% PERSIS** |

### C. Komoditas, Negara Mitra & Pelabuhan Teratas (Distribusi Ekspor 2026)
1. **Top Komoditas Utama:**
   - HS 15 (Lemak & Minyak Hewani/Nabati / CPO): US\$ 3.864.717.391,33 (Pangsa: 60,10%)
   - HS 47 (Bubur Kayu / Pulp): US\$ 1.258.490.120,44 (Pangsa: 19,57%)
   - HS 48 (Kertas & Karton): US\$ 543.201.890,12 (Pangsa: 8,45%)
2. **Top Negara Mitra Dagang Utama:**
   - Tiongkok / China (CN): US\$ 1.285.340.912,50 (Pangsa: 19,99%)
   - India (IN): US\$ 982.114.780,10 (Pangsa: 15,27%)
   - Belanda / Netherlands (NL): US\$ 412.390.115,20 (Pangsa: 6,41%)
3. **Top Pelabuhan Muat Utama:**
   - Pelabuhan Dumai (IDDUM): US\$ 5.412.809.114,20 (Pangsa: 84,17%)
   - Pelabuhan Sungai Pakning (IDSPK): US\$ 621.450.890,00 (Pangsa: 9,66%)
   - Pelabuhan Bengkalis (IDBKS): US\$ 214.390.120,10 (Pangsa: 3,33%)

---

## 8. HASIL PENGUJIAN OTOMATIS & BROWSER INTERACTIVITY

Pengujian otomatis terhadap fungsionalitas Single Dashboard dijalankan melalui PHPUnit:
- Berkas uji: `tests/Feature/Phase2fSingleDashboardTest.php`
- **Hasil:**
  - `test_superadmin_can_access_single_dashboard` &rarr; **PASSED** (200 OK, 4 Tab & Switchers hadir)
  - `test_sidebar_contains_strictly_single_dashboard_menu` &rarr; **PASSED** (Hanya 1 menu Dashboard Statistik terpadu)
  - `test_legacy_dashboard_routes_render_single_dashboard` &rarr; **PASSED** (Backward compatibility terjamin)
  - `test_api_dashboard_data_distribusi_ekspor_and_impor` &rarr; **PASSED** (Payload JSON valid & lengkap)
  - `test_api_dashboard_data_neraca_ekspor_and_impor` &rarr; **PASSED** (Payload JSON Neraca valid)
  - `test_api_dashboard_data_includes_trade_balance` &rarr; **PASSED** (Neraca Perdagangan Tab 4 terhitung tepat)
  - `test_rbac_pegawai_cannot_see_private_data_on_dashboard` &rarr; **PASSED** (Isolasi data PRIVATE terbukti)
- **Status Server Lokal:** Server Laravel aktif pada port 8000 (`http://127.0.0.1:8000/dashboard`, HTTP Status 200).

---

## 9. KESIMPULAN PER ASPEK & KELAYAKAN COMMIT KELOMPOK 4

```
========================================================================================
RINGKASAN STATUS AUDIT KELOMPOK 4: DASHBOARD STATISTICS & ANALYTICAL VISUALIZATION
========================================================================================
1. Identifikasi & Penelaahan 10 Berkas ................... LULUS (100% Diperiksa)
2. Keaslian Sumber Data (Zero Hardcoded / Zero Dummy) .... LULUS (100% Terintegrasi DB)
3. Fungsionalitas Filter Periode (Bulan, Tw, Tahun) ...... LULUS (Dinamis & Teruji)
4. Dimensi Analisis (Komoditas, Negara, Pelabuhan, Peta) . LULUS (Cross-Dimensional)
5. Penegakan Otorisasi RBAC Sisi Server .................. LULUS (Server-Side Enforced)
6. UI/UX, Skalabilitas Responsif & Tanpa Scroll Rusak ... LULUS (1920, 1366, 1024, 375)
7. Konsistensi Angka Dashboard vs Data Mart .............. LULUS (100% Exact Match)
8. Pengujian Interaktif Browser & Backward Compatibility . LULUS (4 Tab Berfungsi Penuh)
========================================================================================
STATUS AKHIR KELOMPOK 4: LAYAK STAGING & COMMIT (SAFE TO COMMIT)
========================================================================================
```

### Rekomendasi Perintah Manual Git Staging (Terminal / VS Code):
```bash
git add app/Http/Controllers/AnalisisController.php \
        app/Http/Controllers/DashboardController.php \
        app/Http/Controllers/DistributionController.php \
        app/Http/Controllers/NeracaController.php \
        resources/css/app.css \
        resources/views/dashboard.blade.php \
        resources/views/layouts/app.blade.php \
        resources/views/pages/analisis-perdagangan.blade.php \
        resources/views/pages/distribusi-dashboard.blade.php \
        resources/views/pages/neraca-dashboard.blade.php
```

#### Pesan Commit yang Direkomendasikan:
```bash
git commit -m "feat(dashboard): refine single dashboard 4-tabs, proportional layout, chart.js, and leaflet maps"
```
