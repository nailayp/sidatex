# SIDATEX — AUDIT READ-ONLY & RENCANA IMPLEMENTASI PHASE 2G.4
## Blade UI Alignment (Penanganan Nilai `null` vs `0` Riil)

| Atribut | Nilai |
|---|---|
| **Fase** | Phase 2G.4 — Persiapan, Audit READ-ONLY & Rencana Implementasi Blade UI |
| **Tanggal Audit** | 2026-11-18 |
| **Mode Eksekusi** | **100% READ-ONLY** — 0 perubahan kode, 0 migration, 0 mutasi DB, 0 sentuhan `sidatex` |
| **Live PDO Verifikasi Payload** | `sidatex_testing` (`SELECT DATABASE() === 'sidatex_testing'`) |
| **File Target Utama** | 1. `resources/views/dashboard.blade.php` <br> 2. `resources/views/pages/distribusi-dashboard.blade.php` |
| **Review Paralel Independen** | `frontend-reviewer` (READ-ONLY) |
| **Status Implementasi** | **BELUM DIMULAI** — Menunggu persetujuan tertulis pengguna |

---

## 1. Ringkasan Eksekutif Temuan Audit

Audit *read-only* terhadap kode Blade, kontrak JSON backend pasca-Phase 2G.3, dan payload aktual `sidatex_testing` menemukan **2 kerusakan fungsional/semantik serius** serta sejumlah inkonsistensi format yang wajib diselaraskan pada Phase 2G.4:

1. **CRASH RUNTIME (`CRITICAL`) pada `resources/views/pages/distribusi-dashboard.blade.php` (`L334, L337`):**
   - Pada periode default **`September 2026`** (yang langsung dipanggil saat halaman dibuka di `L429`: `loadDashboardData(2026, 9)`) maupun **`Agustus 2026`**, backend Phase 2G.3 mengembalikan `neraca_bulanan_juta: null` (dan `neraca_kumulatif_juta: null` pada Agustus 2026) karena data *single-sided*.
   - Kode saat ini memanggil `neraca.neraca_bulanan_juta.toLocaleString()` secara langsung tanpa pemeriksaan `null` → memicu **`TypeError: Cannot read properties of null (reading 'toLocaleString')`**, melempar `alert()` error, dan **menghentikan rendering seluruh 5 tabel di bawahnya** (Sektor, Komoditas, Negara, BEC, PROVORIG macet di `"Memuat data..."`).
2. **ANGKA PALSU & STATUS SALAH (`HIGH`) pada `resources/views/dashboard.blade.php` (`L1228, L1728–1744`):**
   - Fungsi global `formatNumber(num)` (`L1228`) mengonversi `null`/`undefined`/`NaN` menjadi `'0,00'`.
   - Akibatnya, pada **Tab 4 (`renderTradeBalanceTable`)** untuk payload riil **Role 2 Pegawai (`2026`, Distribusi)** maupun **Role 1 Admin (`2026-09`, Neraca)**:
     - `item.neraca = null` tampil sebagai **`$ 0,00` berwarna merah (`#dc2626`)** (seolah defisit),
     - kolom Status menampilkan badge **`Seimbang`** dan mengabaikan `item.status` (`Data Ekspor Belum Tersedia` / `Data Impor Belum Tersedia`),
     - `item.kumulatif = null` tampil sebagai **`$ 0,00`** alih-alih **`—`**.
3. **EDGE CASE NILAI `0` RIIL & `undefined`/`NaN` (`MEDIUM`):**
   - Pada **Tab 4 Hero Card** (`dashboard.blade.php:1443–1463`), kondisi `has_complete === true && net_trade === 0` (`Seimbang`) jatuh ke cabang `else` Defisit sehingga tampil **`🔴 Defisit Perdagangan Luar Negeri: US$ 0,00`** dengan subteks `Defisit Devisa Bersih`.
   - Pada **Tab 1 KPI Growth** (`L1385–1388`), **Tab 2 MoM Komoditas** (`L1628–1631`), dan **Tab 3 Tabel MoM/QoQ** (`L1702–1705`), guard hanya memeriksa `!== null` (tidak memeriksa `undefined`/`NaN` sehingga nilai tak terdefinisi dapat tampil sebagai `undefined%` / `NaN%`), dan HTML awal masih mematok `0%` serta badge hijau `SURPLUS` sebelum AJAX selesai.
4. **Catatan Cross-Check View Lain (Di Luar Ruang Lingkup Edit Phase 2G.4):**
   - `resources/views/upload-data.blade.php`: sudah memeriksa `!== null && !== undefined && !isNaN(...)` pada `renderSimTsTable` (`L2165`).
   - `resources/views/pages/analisis-perdagangan.blade.php`: tidak merender field `growth` maupun `trade_balance`.
   - **Catatan Transparansi Backend (TIDAK diubah di Phase 2G.4 sesuai Aturan #6):** Pada payload live `/distribusi/metrics?tahun=2026&bulan=9`, `DistributionStatisticsService::getSummaryMetrics()` (`L130–132`) menghasilkan `impor.pertumbuhan.mtom_total = -100` (karena `2026-09` Impor `0` vs `2026-08` Impor `167,35 jt`). Sesuai Aturan #6, rumus backend ini **tidak diubah** pada Phase 2G.4 dan dicatat sebagai observasi terpisah.

---

## 2. Daftar Komponen Blade yang Terdampak

### 2.1 `resources/views/dashboard.blade.php`

| No | Lokasi / Komponen | Baris | Masalah Saat Ini | Tingkat Risiko |
|---|---|---|---|---|
| 1 | Helper global `formatNumber()` | `L1227–1230` | Memaksa `null`/`undefined`/`NaN` menjadi `'0,00'`, menjadi akar masalah angka palsu `$ 0,00` di banyak tabel. | **HIGH** |
| 2 | Tab 4 — Tabel Neraca Bulanan (`renderTradeBalanceTable`) | `L1719–1745` | `neraca: null` tampil **`$ 0,00` merah**, status salah jadi **`Seimbang`** (mengabaikan `item.status` & `has_complete: false`), `kumulatif: null` tampil **`$ 0,00`**; nilai `0` riil (`Seimbang`) juga berwarna merah (`#dc2626`). | **HIGH** |
| 3 | Tab 4 — Hero Card & Net Trade Box (`updateTab4TradeBalance` + HTML awal) | `L1131–1151`, `L1435–1473` | HTML awal hardcode hijau `SURPLUS` & `US$ 0,00`; saat `!has_complete` menulis `'Data Belum Lengkap'` alih-alih `—` pada nilai KPI utama; saat `has_complete && net_trade === 0` (`Seimbang`) salah masuk cabang **Defisit merah**; `tb.status.toUpperCase()` tanpa null guard; angka negatif tampil `US$ -100,00`. | **MEDIUM** |
| 4 | Tab 1 — 4 Kartu KPI Pertumbuhan (`kpiMtom`, `kpiQtoq`, `kpiYony`, `kpiCtoc`) | `L781–802`, `L1384–1388` | HTML awal hardcode `0%` (tetap `0%` jika fetch error); JS hanya cek `!== null` (rawan `undefined%`/`NaN%`); desimal titik JS (`3.77%`) tidak konsisten dengan locale `id-ID` (`3,77%`). | **MEDIUM** |
| 5 | Tab 3 — Tabel Pertumbuhan MoM & QoQ (`renderGrowthTable`) | `L1689–1714` | Hanya cek `item.growth !== null` (rawan `undefined%`/`NaN%`); `item.nilai` & `item.kumulatif` memakai `formatNumber` tanpa guard `null`; `0%` riil dan `—` berbagi style identik tanpa pembeda visual/tooltip; raw `innerHTML` pada `item.periode`. | **MEDIUM** |
| 6 | Tab 2 — Tabel Time Series Komoditas (`renderTimeSeriesTable`, `renderTriwulanTable`) | `L1608–1661` | `item.mom_growth` hanya cek `!== null` (rawan `undefined%`/`NaN%`); `item.values` tanpa optional guard; raw `innerHTML` pada `item.komoditi`. *(Catatan: sel bulan pivot bernilai `0.0` dari backend merepresentasikan tidak adanya transaksi komoditas di bulan tsb, sehingga `v > 0 ? ... : '—'` dipertahankan khusus sel matriks sparse, sedangkan `item.total` & `mom_growth = 0` tetap tampil sebagai `0,00` / `0,00%`.)* | **LOW-MEDIUM** |
| 7 | `sortTableNumber()` & `fetchDashboardData()` error state | `L1339–1368`, `L1812–1824` | `sortTableNumber` mengurai `'—'` sebagai `0` (bisa mengurutkan baris tak tersedia di atas angka negatif); `catch()` hanya `console.error` tanpa indikator galat UI. | **LOW** |

### 2.2 `resources/views/pages/distribusi-dashboard.blade.php`

*(Catatan routing: `GET /distribusi/dashboard` saat ini diarahkan ke `DashboardController::index` di `routes/web.php:93–96`, namun `DistributionController::dashboardIndex()` (`L34–47`) tetap merender view `pages.distribusi-dashboard` yang mengonsumsi `GET /distribusi/metrics`.)*

| No | Lokasi / Komponen | Baris | Masalah Saat Ini | Tingkat Risiko |
|---|---|---|---|---|
| 1 | KPI Selisih Ekspor-Impor (`#cardNeracaTotal`, `#cardNeracaStatus`, `#cardNeracaCum`) | `L100–112`, `L332–338` | **CRASH `TypeError`** saat memanggil `.toLocaleString()` pada `neraca_bulanan_juta: null` (`2026-09` & `2026-08`) dan `neraca_kumulatif_juta: null` (`2026-08`), memutus render 5 tabel di bawahnya; HTML awal hardcode hijau `Surplus`; JS mewarnai merah (`text-danger`) seluruh status selain `Surplus` termasuk `Data Impor Belum Tersedia` dan `Seimbang` (`0`), sementara `#cardNeracaTotal` permanen `text-success`. | **CRITICAL** |
| 2 | KPI Pertumbuhan M-to-M Ekspor & Impor (`#growthEksporMtoM`, `#growthImporMtoM`) | `L66, L93`, `L307–315`, `L322–330` | Saat `null` tampil teks panjang `'Data pembanding belum tersedia'` (tidak seragam dengan standar `—`); hanya cek `!== null` (rawan `undefined`/`NaN`); nilai `0` riil diwarnai hijau `text-success` alih-alih netral. | **MEDIUM** |
| 3 | KPI Ekspor/Impor & 5 Tabel Detail (`Sektor`, `Komoditas`, `Negara`, `BEC`, `PROVORIG`) | `L301–321`, `L340–415` | Pemanggilan `.toLocaleString()` langsung tanpa null-guard & tanpa locale `'id-ID'`; bila array kosong (terjadi live pada `bec_impor` `2026-09` & `sektor`/`countries`/`origin` `2026-08`), `<tbody>` menjadi **kosong total tanpa pesan status**; `n.kode_iso || '-'` memakai tanda hubung ASCII; raw `innerHTML` tanpa escaping. | **MEDIUM** |
| 4 | Penanganan Error HTTP (`fetch` `/distribusi/metrics`) & HTML Awal | `L64–108`, `L243–244`, `L284–295`, `L416–420` | Mengabaikan pesan error aman `res.message` hasil sanitasi HTTP 500 Phase 2G.3 (`DistributionController:90`), menggunakan `alert()` blocking, dan menyisakan status `"Memuat data..."` di tabel. | **MEDIUM** |

---

## 3. Contoh Kondisi Sebelum vs Sesudah Perbaikan (Berdasarkan Payload Aktual `sidatex_testing`)

### Skenario A — Tab 4 Tabel Neraca Bulanan (`dashboard.blade.php`, Role 2 Pegawai, `2026`, Distribusi)
**Payload aktual dari `GET /dashboard/data?tahun=2026&jenis=Neraca&tipe_sumber=distribusi`:**
```json
[
  { "bulan": 8, "nama_bulan": "Agustus",   "ekspor": 0,              "impor": 167347655.0777, "neraca": null, "status": "Data Ekspor Belum Tersedia", "has_complete": false, "kumulatif": null },
  { "bulan": 9, "nama_bulan": "September", "ekspor": 2301809204.587, "impor": 0,              "neraca": null, "status": "Data Impor Belum Tersedia",  "has_complete": false, "kumulatif": null }
]
```

| Kolom | Sebelum Perbaikan (Buggy) | Sesudah Perbaikan (Target Phase 2G.4) |
|---|---|---|
| **Agustus — Ekspor (FOB)** | `$ 0,00` | `$ 0,00` *(tetap angka `0` riil dari backend)* |
| **Agustus — Impor (CIF)** | `$ 167.347.655,08` | `$ 167.347.655,08` |
| **Agustus — Neraca** | **`$ 0,00`** *(berwarna merah `#dc2626`!)* | **`—`** *(muted `#64748b`, title: "Data Ekspor Belum Tersedia")* |
| **Agustus — Status** | Badge abu-abu **`Seimbang`** *(SALAH!)* | Badge amber **`Data Ekspor Belum Tersedia`** |
| **Agustus — Kumulatif** | **`$ 0,00`** *(SALAH!)* | **`—`** *(muted `#64748b`)* |
| **September — Neraca / Status / Kumulatif** | **`$ 0,00` merah** / **`Seimbang`** / **`$ 0,00`** | **`—`** / Badge amber **`Data Impor Belum Tersedia`** / **`—`** |

### Skenario B — Nilai `0` Statistik Riil (Wajib Tetap `0`, BUKAN `—`)

| Kasus Uji | Payload Backend | Sebelum Perbaikan | Sesudah Perbaikan (Target Phase 2G.4) |
|---|---|---|---|
| **Pertumbuhan datar riil** (`$1M → $1M`) | `mtom: 0` / `growth: 0` | `0%` (KPI) / `0%` (tabel), warna hijau di `distribusi-dashboard` | **`0,00%`** (badge netral `growth-zero` / `text-secondary`) — **tetap `0`, BUKAN `—`** |
| **Pertumbuhan tak tersedia** (pembanding kosong) | `mtom: null` / `qtoq: null` | `0%` di HTML awal → `—` (rawan `undefined%` jika undefined) | **`—`** (muted, tooltip *"Periode pembanding belum tersedia"*) |
| **Neraca seimbang riil** (`Ekspor = Impor > 0`) | `has_complete: true, net_trade: 0, status: "Seimbang"` | Hero: **`🔴 Defisit: US$ 0,00`** merah; Tabel: **`$ 0,00` merah** | Hero: **`⚖️ Neraca Perdagangan Seimbang: US$ 0,00`** (netral); Tabel: **`$ 0,00`** (netral `#475569`) + badge **`Seimbang`** |
| **Neraca bulanan belum lengkap** (Hero Card) | `has_complete: false, net_trade: null, status: "Data Impor Belum Tersedia"` | Nilai KPI: `Data Belum Lengkap` | Nilai KPI: **`—`**; Badge: **`Data Impor Belum Tersedia`**; Subteks: `Membutuhkan kedua sisi Ekspor & Impor` |

### Skenario C — Dashboard Distribusi (`distribusi-dashboard.blade.php`, Default `September 2026` & `Agustus 2026`)
**Payload aktual `GET /distribusi/metrics?tahun=2026&bulan=9`:**
- `neraca_bulanan_juta: null`, `status_bulanan: "Data Impor Belum Tersedia"`, `has_complete_bulanan: false`
- `neraca_kumulatif_juta: 2134.46`, `status_kumulatif: "Surplus"`, `has_complete_kumulatif: true`
- `ekspor.pertumbuhan.mtom_total: null`, `bec_impor.golongan_barang: []` (`0` baris)

| Elemen UI | Sebelum Perbaikan (Buggy) | Sesudah Perbaikan (Target Phase 2G.4) |
|---|---|---|
| **Eksekusi JS** | **CRASH `TypeError`** di `L334` + `alert('Terjadi kesalahan...')` | **0 error console**, seluruh kartu & 5 tabel terender penuh |
| `#cardNeracaTotal` (`2026-09`) | Stuck `US$ - Juta` hijau (`text-success`) | **`—`** (netral `text-muted`) |
| `#cardNeracaStatus` (`2026-09`) | Stuck `Surplus` hijau (atau merah `text-danger`) | **`Data Impor Belum Tersedia`** (netral/amber, bukan merah) |
| `#cardNeracaCum` (`2026-09`) | Stuck `-` | **`US$ 2.134,46 Juta (Surplus)`** |
| `#cardNeracaCum` (`2026-08`, `null`) | **CRASH `TypeError`** di `L337` | **`— (Data Ekspor Belum Tersedia)`** |
| `#growthEksporMtoM` (`null`) | `Data pembanding belum tersedia` | **`—`** (muted, `title="Periode pembanding belum tersedia"`) |
| `#tableBecBody` (`2026-09`, `[]`) | Stuck `"Memuat data..."` / tabel kosong tanpa pesan | Baris empty-state: `Data penggunaan barang (BEC) belum tersedia untuk periode ini.` |

---

## 4. Rencana Perubahan File & Pendekatan Implementasi (Setelah Disetujui)

### Batasan Ketat yang Dijaga
- **Hanya 2 file produksi yang dimodifikasi:**
  1. `resources/views/dashboard.blade.php`
  2. `resources/views/pages/distribusi-dashboard.blade.php`
- **0 perubahan** pada controller, service, rumus statistik, routes, `database/migrations/`, `master_hs`, Batch `#71`, Batch `#720`, 30.147 baris historis, maupun database operasional `sidatex`.
- **1 file test baru (atau penambahan test khusus Phase 2G.4):** `tests/Feature/Phase2gBladeAlignmentTest.php` untuk menguji rendering Blade & kontrak null-handling tanpa menyentuh data permanen.

### Standar Helper Formatting Terpusat (Tanpa Boilerplate)
Kedua file Blade akan menerapkan kontrak utilitas kecil yang identik dan ketat membedakan `null` vs `0`:
1. **`isValidNumber(val)`:** `val !== null && val !== undefined && val !== '' && Number.isFinite(Number(val))`
   - `0`, `0.0`, `'0'` → **`true`** (tetap ditampilkan sebagai `0` / `0,00` / `0,00%`)
   - `null`, `undefined`, `NaN`, `''` → **`false`** (ditampilkan sebagai `—`)
2. **`formatNullableNumber(val, decimals = 2)`:** mengembalikan `'—'` jika `!isValidNumber(val)`, selain itu `Number(val).toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })`.
3. **`formatNullableCurrency(val, prefix = '$ ', signed = false)`:** mengembalikan `'—'` jika `!isValidNumber(val)`; untuk angka negatif memformat `'-' + prefix + format(Math.abs(n))` (mis. `-$ 100,00` / `-US$ 100,00`), untuk `> 0` dengan `signed` memformat `'+' + prefix + ...`, untuk `0` memformat `prefix + '0,00'`.
4. **`formatGrowthText(val)` & `getGrowthBadgeMeta(val)`:**
   - `!isValidNumber(val)` → teks `'—'`, kelas `.growth-na` (muted netral) + `title="Periode pembanding belum tersedia"`.
   - `Number(val) === 0` → teks `'0,00%'`, kelas `.growth-zero` (netral — **tetap `0,00%`, bukan `—`**).
   - `Number(val) > 0` → teks `'+X,XX%'`, kelas `.growth-pos` (hijau).
   - `Number(val) < 0` → teks `'-X,XX%'`, kelas `.growth-neg` (merah).
5. **`escapeHtml(str)`:** mencegah injeksi HTML pada interpolasi `item.status`, `item.periode`, `item.nama_bulan`, `item.komoditi`, `item.item`, dll.

### Rincian Perubahan per File
1. **`resources/views/dashboard.blade.php`:**
   - Tambahkan style CSS ringan `.growth-na` (muted) dan `.badge-unavailable` (amber `#fef3c7` / `#b45309`).
   - Ubah placeholder HTML awal 4 kartu KPI pertumbuhan (`L784, L789, L794, L799`) dari `0%` → `—` dan Hero Trade Balance (`L1131–1133, L1149`) dari `SURPLUS`/`US$ 0,00` → `—` / `Memuat...` (netral).
   - Tambahkan helper `isValidNumber`, `formatNullableNumber`, `formatNullableCurrency`, `formatGrowthText`, `buildGrowthBadgeHtml`, dan `escapeHtml`. Pertahankan `formatNumber` untuk angka pasti yang non-nullable, dan gunakan helper nullable di seluruh field yang dapat bernilai `null`.
   - Perbarui `updateTab1Overview()` (`L1385–1388`) memakai `formatGrowthText()` dengan warna/tooltip jelas.
   - Perbarui `renderTimeSeriesTable()` (`L1625–1632`) & `renderGrowthTable()` (`L1698–1713`) memakai `buildGrowthBadgeHtml()`, `formatNullableCurrency()`, dan `escapeHtml()`.
   - Perbarui `updateTab4TradeBalance()` (`L1435–1473`): tangani **3 cabang lengkap** (`Surplus` hijau, `Defisit` merah, `Seimbang` `0` netral `US$ 0,00`) + cabang tak lengkap (`!tb.has_complete || !isValidNumber(tb.net_trade)` → `#tbNetTrade` = `—`, badge = `tb.status`).
   - Perbarui `renderTradeBalanceTable()` (`L1719–1745`): cek `item.has_complete && isValidNumber(item.neraca)` → jika tidak lengkap tampilkan `—` (muted), badge amber `escapeHtml(item.status || 'Data Belum Tersedia')`, dan `formatNullableCurrency(item.kumulatif)` (`—` bila `null`); jika lengkap tangani `> 0` Surplus hijau, `< 0` Defisit merah, `=== 0` Seimbang `$ 0,00` netral.
   - Perkuat `sortTableNumber()` (`L1818`) agar `'—'` tidak diurai sebagai `0` di atas nilai negatif (pakai `-Infinity`), tambahkan pemanggilan `.resize()` Chart.js saat berpindah tab di `switchDashboardTab()`, serta tampilkan notifikasi inline non-blocking bila fetch gagal.
2. **`resources/views/pages/distribusi-dashboard.blade.php`:**
   - Ubah placeholder HTML awal KPI & badge (`L64–108, L243–244`) ke `—` netral (hilangkan hardcoded hijau `Surplus` & `text-success` permanen pada `#cardNeracaTotal`), serta tambahkan banner error inline (`#distribusiErrorAlert`, `role="alert"`) sebagai pengganti `alert()` blocking.
   - Tambahkan helper `isValidNumber`, `formatNumberId`, `formatGrowthDist`, `getStatusColorStyle`, dan `escapeHtml` (dengan fallback inline color/CSS agar tetap konsisten meski layout utama memakai Tailwind, bukan Bootstrap penuh).
   - Perbaiki blok KPI Neraca (`L332–338`):
     - `neraca_bulanan_juta`: jika `!neraca.has_complete_bulanan || !isValidNumber(neraca.neraca_bulanan_juta)` → `—` (muted); jika valid → `US$ ${formatNumberId(...)} Juta` (`0` → `US$ 0,00 Juta` netral, `> 0` hijau, `< 0` merah).
     - `status_bulanan`: gunakan `.textContent` + pewarnaan 4 keadaan (`Surplus` hijau, `Defisit` merah, `Seimbang` netral, tak tersedia amber/muted).
     - `neraca_kumulatif_juta`: jika `!neraca.has_complete_kumulatif || !isValidNumber(neraca.neraca_kumulatif_juta)` → `— (${neraca.status_kumulatif})`; jika valid → `US$ ${formatNumberId(...)} Juta (${neraca.status_kumulatif})`.
   - Perbaiki KPI M-to-M Ekspor & Impor (`L307–330`): tampilkan `—` saat `null`/`undefined`/`NaN`, `0,00%` (netral) saat `0` riil, `+X,XX%` hijau / `-X,XX%` merah.
   - Lindungi seluruh panggilan `.toLocaleString()` di KPI & 5 tabel dengan `formatNumberId()`, tambahkan empty-state row yang jelas pada kelima tabel bila array kosong, pakai `escapeHtml()`, dan tangani respons error HTTP 500 JSON (`res.message`) secara aman.

---

## 5. Daftar Risiko Regresi & Mitigasinya

| No | Risiko Regresi | Dampak | Mitigasi |
|---|---|---|---|
| 1 | **Nilai `0` riil tidak sengaja berubah menjadi `—`** (mis. `if (val)` truthy check pada `0.0%` atau `net_trade = 0`) | Melanggar Aturan #5 dan menyembunyikan hasil `Seimbang` atau pertumbuhan datar `0,00%` | Wajib gunakan `isValidNumber(val)` secara ketat; uji eksplisit kasus `0`, `0.0`, dan `null` di unit/browser verification. |
| 2 | **Breaking perubahan ID/elemen DOM yang diuji PHPUnit lama** (`Phase2fSingleDashboardTest`, `DistributionModuleTest`) | Test suite `165/165` bisa gagal jika atribut `id` atau teks statis yang di-assert berubah | Pertahankan seluruh `id` DOM (`tabOverview`, `tabTradeBalance`, `kpiMtom`, `tableTradeBalanceMonthly`, `cardNeracaTotal`, dll.) dan seluruh teks sidebar/header 100% utuh. |
| 3 | **Ekspor Excel (SheetJS `exportTableToExcel`) terganggu oleh HTML badge/tooltip** | Sel Excel memuat teks tak bersih atau format rusak | Pastikan konten teks di dalam `<td>` tetap bersih (`—`, `Surplus`, `Data Impor Belum Tersedia`, `$ 2.050.808.340,43`) tanpa teks tersembunyi; tooltip gunakan atribut HTML `title`. |
| 4 | **Kelas CSS Bootstrap tidak terdefinisi di `distribusi-dashboard.blade.php`** (karena `layouts/app.blade.php` memakai Tailwind v4) | Warna status (`text-muted`, `text-success`, `text-danger`) atau hide/show banner error (`d-none`) gagal tampil | Gunakan gaya CSS eksplisit/scoped atau `style.color` / `style.display` sebagai jaminan tampilan lintas layout. |
| 5 | **Perubahan tidak sengaja pada sel kosong pivot komoditas Tab 2** | Menukar `—` menjadi `$ 0,00` di 11 kolom kosong akan membuat matriks 12 bulan sulit dibaca | Pertahankan `v > 0 ? ... : '—'` khusus sel matriks sparse pivot bulanan/triwulanan, sementara kolom `Total` dan `Pertumbuhan MoM` (`0,00%`) tetap menampilkan angka `0` riil. |
| 6 | **Kebocoran data atau perubahan target DB saat pengujian browser** | Risiko sentuh operasional `sidatex` | Verifikasi PDO `SELECT DATABASE() === 'sidatex_testing'` sebelum server lokal/test dijalankan; 0 mutasi DB. |

---

## 6. Rekomendasi Pengujian yang Diperlukan (Setelah Persetujuan Implementasi)

1. **Automated PHPUnit Regression & Blade Contract Tests (`sidatex_testing`):**
   - Tambahkan `tests/Feature/Phase2gBladeAlignmentTest.php` untuk memverifikasi:
     - Rendering HTML awal `dashboard.blade.php` & `pages/distribusi-dashboard.blade.php` tidak lagi memuat placeholder bias (`0%`, hardcoded `SURPLUS`) dan menyertakan helper null-safe (`isValidNumber`, `escapeHtml`).
     - Seluruh kontrak respons `/dashboard/data` & `/distribusi/metrics` untuk Role 2 Pegawai (`2026-08`/`2026-09` single-sided `null`) dan Role 1 Admin (`2026` lengkap + `2026-09` Neraca single-sided `null`) tetap konsisten.
   - Jalankan ulang **seluruh PHPUnit suite (`php artisan test`)** — target minimal **165+ PASS, 0 failures/errors/skips**.
2. **Live Browser Verification (Subagent `browser` pada `sidatex_testing`):**
   - Login sebagai **Role 2 (Pegawai)** dan **Role 1 (Super Admin)** di `/dashboard`:
     - Verifikasi Tab 1 (KPI Growth `—` untuk Role 2 vs `+3,77%`, `+22,58%`, `+45,04%`, `+6,96%` untuk Role 1), Tab 2 (Pivot Komoditas + MoM), Tab 3 (Tabel MoM & QoQ dengan `—` untuk periode tanpa pembanding), Tab 4 (Trade Balance: Agustus & September Pegawai menampilkan `—` + `Data Ekspor/Impor Belum Tersedia` tanpa `$ 0,00` merah atau `Seimbang`).
     - Verifikasi `0` console errors / uncaught `TypeError`, uji responsive desktop & mobile, serta ambil screenshot bukti.
   - Uji fungsi JS `distribusi-dashboard.blade.php` terhadap payload aktual `2026-09` (`neraca_bulanan_juta: null`) dan `2026-08` (`neraca_bulanan_juta: null`, `neraca_kumulatif_juta: null`), serta simulasi kasus `0` riil (`0.0%` & `Seimbang` `0`) untuk memastikan **0 `TypeError`**, `null` → `—`, dan `0` riil → `0,00` / `0,00%`.
3. **Post-Execution Independent Review & Zero-Touch Integrity Check:**
   - Review *read-only* oleh `frontend-reviewer` dan `independent-reviewer`.
   - Verifikasi `git diff --stat` (hanya 2 file Blade + file test), SHA-256 Batch `#71` / `#720` / `30.147` historis / `2.896` Neraca, serta 9 metrik operasional `sidatex` tetap 100% identik.

---

> **Gerbang Persetujuan:** Audit *read-only* dan rencana implementasi Phase 2G.4 telah selesai. **Belum ada perubahan kode atau file produksi yang dilakukan.** Mohon tinjauan dan persetujuan tertulis Anda untuk memulai eksekusi Phase 2G.4 sesuai rencana di atas.
