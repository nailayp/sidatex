# LAPORAN AUDIT GIT SIDATEX — PHASE 2G.10.6.4.11
## KELOMPOK 7: ACTIVITY LOGS, FILTERING DATA UI & UNIFIED PAGINATION

**Tanggal:** 23 November 2026  
**Auditor:** Antigravity AI Agent & SIDATEX Quality Assurance Team  
**Subagent Terlibat:** Group 7 Browser Auditor (`6ca28de9-a930-4954-8b8b-e8244821c8c8`)  
**Status Audit:** **LULUS PENUH (100% PASSED) — SIAP STAGING & COMMIT**  

---

## 1. RINGKASAN EKSEKUTIF

Audit Tahap 2G.10.6.4.11 difokuskan pada **Kelompok 7**, yang mencakup sistem audit trail internal (*Activity Logs*), mesin penelusuran data transaksi ekspor-impor (*Filtering Data*), standarisasi pagination, serta integrasi antarmuka pengguna (UI) responsif dengan backend Laravel 11.

Seluruh pengujian dilaksanakan dengan mematuhi prinsip kehati-hatian tertinggi:
1. **Proteksi Database Operasional:** Database MySQL operasional `sidatex` diverifikasi tetap **100% utuh (0 mutasi)** pada baseline 12 batch, 5.169 transaksi Distribusi, 6.192 transaksi Neraca, 11.155 ringkasan bulanan mart, dan 111 log aktivitas.
2. **Isolasi Pengujian SQLite In-Memory:** Seluruh automated feature test dijalankan murni pada lingkungan SQLite `:memory:` tanpa koneksi fisik ke MySQL testing/staging.
3. **Penyempurnaan Keamanan (Anti-Information Leakage):** Dilakukan pengetatan filter opsi dropdown modul & aktivitas pada `ActivityLogController` agar Pegawai (Role 2) hanya melihat modul dan aktivitas operasional miliknya, mencegah kebocoran informasi (*information leakage*) modul dan tindakan Super Admin.
4. **Hasil Pengujian Komprehensif:** Suite automated testing lengkap menghasilkan **193 Tests, 1.596 Assertions, 0 Failures, 0 Errors (100% PASS)** dalam waktu 102,13 detik.
5. **Bukti Visual Browser:** Subagent browser berhasil mendokumentasikan 7 tangkapan layar antarmuka desktop (1280x800) dan mobile (390x844) tanpa cacat layout maupun overflow horizontal.

---

## 2. IDENTIFIKASI BERKAS KELOMPOK 7

Berdasarkan klasifikasi audit Git Phase 2G.10.6.4.4, Kelompok 7 terdiri dari 6 berkas:

| No | Nama Berkas | Status Git | Baris | Peran & Tanggung Jawab Teknis |
|:---|:---|:---:|:---:|:---|
| 1 | `app/Http/Controllers/ActivityLogController.php` | `??` (Untracked) | 120 | Controller audit trail sistem; mengelola filter pencarian, modul, aktivitas, rentang tanggal, paginasi dinamis, dan pembatasan data RBAC (Super Admin vs Pegawai). |
| 2 | `app/Http/Controllers/FilteringController.php` | `M` (Modified) | 214 | Mesin query multi-dimensi penelusuran transaksi; validasi `tipe_sumber`, pemisahan tabel Distribusi/Neraca, isolasi status RILIS/PRIVATE, filter komoditas unmapped via ANSI SQL `SUBSTR()`, dan kalkulasi presisi BCMath. |
| 3 | `resources/views/pages/activity-logs.blade.php` | `??` (Untracked) | 454 | Tampilan Blade audit trail; menyajikan kartu filter 3-kolom proporsional, tombol aksi terintegrasi di dalam kartu, tabel audit trail beraksen badge aktivitas, waktu WIB, avatar inisial, dan paginasi. |
| 4 | `resources/views/pages/filtering-data.blade.php` | `M` (Modified) | 813 | Tampilan Blade filtering transaksi komprehensif; switcher metodologi Distribusi/Neraca, grid filter 4x2 desktop, summary bar berlatar hijau, tabel interaktif dengan format ribuan koma BPS, modal detail metadata transaksi, dan numbered pagination SiPantau. |
| 5 | `resources/views/partials/pagination.blade.php` | `??` (Untracked) | 58 | Komponen navigasi paginasi modular berstandar aksesibilitas (WAI-ARIA); menjaga query parameter URL via `$paginator->appends(request()->query())`. |
| 6 | `composer.json` | `M` (Modified) | 77 | Hardening skrip dependensi; menghapus otomatisasi destruktif `@php artisan migrate --force` untuk melindungi integritas database operasional. |

---

## 3. AUDIT ACTIVITY LOGS (AUDIT TRAIL & KEAMANAN SISTEM)

### 3.1. Cakupan Event Operasional yang Dicatat
Sistem SIDATEX secara konsisten mencatat 12 jenis aksi penting ke dalam tabel `activity_logs`:
1. **Login Pengguna (`Autentikasi`):** Mencatat nama, username, user ID, alamat IP saat berhasil login.
2. **Logout Pengguna (`Autentikasi`):** Mencatat sesi keluar pengguna.
3. **Processing DBF BRS Distribusi / Neraca (`Data Processing Engine`):** Mencatat nama file, jumlah baris transaksi valid yang diolah, periode bulan/tahun, nomor batch, dan status visibilitas.
4. **Rilis Publikasi Batch Data (`Manajemen Data`):** Mencatat perubahan status batch dari PRIVATE menjadi RILIS.
5. **Tarik Publikasi Batch Data (`Manajemen Data`):** Mencatat penarikan data publik kembali ke status internal PRIVATE.
6. **Hapus Data Batch ke Trash (`Manajemen Data`):** Mencatat pemindahan dokumen batch ke modul Trash Data.
7. **Pulihkan Data Batch dari Trash (`Manajemen Data`):** Mencatat pengembalian batch aktif dari Trash ke sistem operasional.
8. **Hapus Permanen Data Batch (`Manajemen Data`):** Mencatat penghapusan data secara permanen beserta cascading ke tabel transaksi dan mart.
9. **Edit Data Batch (`Manajemen Data`):** Mencatat pembaruan metadata nama file atau jenis transaksi batch.
10. **Ekspor Data Batch Excel (`Manajemen Data`):** Mencatat pengunduhan data mentah transaksi ke format `.xlsx`.
11. **Ekspor PowerPoint BRS (`Analisis`):** Mencatat pengunduhan slide deck presentasi Berita Resmi Statistik format `.pptx`.
12. **Tambah / Hapus Catatan Komentar (`Komentar`):** Mencatat audit komunikasi internal tim pengolahan.

### 3.2. Akurasi Zona Waktu (WIB / Asia/Jakarta)
- Konfigurasi aplikasi di `config/app.php` mengunci zona waktu pada `'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta')`.
- Pencatatan waktu menggunakan `now()` menghasilkan waktu lokal Indonesia Bagian Barat (WIB / UTC+7).
- Pada antarmuka Blade (`activity-logs.blade.php`), waktu dirender dalam dua baris terstruktur:
  - Tanggal: `{{ \Carbon\Carbon::parse($log->created_at)->translatedFormat('d M Y') }}` (contoh: `20 Nov 2026`).
  - Waktu: `{{ \Carbon\Carbon::parse($log->created_at)->format('H:i:s') }} WIB` (contoh: `14:17:51 WIB`).

### 3.3. Verifikasi Pencegahan Kebocoran Data Sensitif (Zero Leakage)
Melalui automated test `test_activity_logs_zero_sensitive_data_leakage`:
- **Password Plaintext:** 0 temuan. Password pengguna tidak pernah dituliskan ke dalam tabel log.
- **Hash Bcrypt (`$2y$`):** 0 temuan. Tidak ada hash kriptografis yang tersimpan pada field deskripsi.
- **Token Akses & Session ID:** 0 temuan. Parameter CSRF token, remember_token, dan string sesi web diisolasi dari log audit.

### 3.4. Hak Akses Berbasis Peran (RBAC) & Perbaikan Scoping Dropdown
Pada tahap audit ini, dilakukan perbaikan keamanan pada `ActivityLogController`:
```php
// Opsi modul & aktivitas untuk filter dropdown (dibatasi sesuai hak akses role)
$modQuery = DB::table('activity_logs')->whereNotNull('modul');
$actQuery = DB::table('activity_logs');
$countQuery = DB::table('activity_logs');

if (!$isSuperAdmin) {
    $modQuery->where('user_id', $currentUser->id);
    $actQuery->where('user_id', $currentUser->id);
    $countQuery->where('user_id', $currentUser->id);
}

$availableModules = $modQuery->select('modul')->distinct()->orderBy('modul')->pluck('modul');
$availableActivities = $actQuery->select('aktivitas')->distinct()->orderBy('aktivitas')->pluck('aktivitas');
$totalLogsCount = $countQuery->count();
```
- **Super Admin (Role 1):** Mendapatkan akses status 200, melihat seluruh aktivitas sistem (lintas pengguna dan modul), serta counter total log keseluruhan.
- **Pegawai BPS (Role 2):** Mendapatkan akses status 200 untuk audit internal dirinya sendiri (`where('user_id', $currentUser->id)`). Dropdown modul dan aktivitas difilter ketat hanya untuk aktivitas yang dilakukannya, sehingga nama modul rahasia/administratif Super Admin **tidak bocor**.
- **Viewer Eksekutif (Role 3):** Ditolak dengan redirect status 302 ke dashboard dengan notifikasi: *"Akses ditolak. Viewer Eksekutif hanya memiliki akses pemantauan dashboard."*
- **Tamu / Belum Login (Guest):** Ditolak dengan redirect status 302 ke halaman login.

---

## 4. AUDIT FILTERING DATA (PENELUSURAN & AGREGASI TRANSAKSI)

### 4.1. Mesin Query Multi-Dimensi & Validasi Tipe Sumber
Endpoint `GET /filtering-data/query` pada `FilteringController` telah diperkuat:
1. **Validasi Ketat `tipe_sumber`:** Hanya menerima parameter `'distribusi'` atau `'neraca'`. Nilai selain itu ditolak seketika dengan respon HTTP `422 Unprocessable Content`.
2. **Pemisahan Tabel Fisik:**
   - Parameter `'distribusi'` secara eksklusif melakukan query pada `distribusi_transactions`.
   - Parameter `'neraca'` secara eksklusif melakukan query pada `neraca_transactions`.
   - Tidak ada penggabungan (*cross-contamination*) data antara Distribusi dan Neraca.
3. **Filter Multi-Dimensi:**
   - **Periode:** `tahun` (integer), `bulan` (1-12 atau ALL), `triwulan` (Q1: 1-3, Q2: 4-6, Q3: 7-9, Q4: 10-12).
   - **Kategori Transaksi:** `Ekspor` (FOB) dan `Impor` (CIF).
   - **Wilayah:** `kabkota_id`, `negara_id`, `pelabuhan_id`.
   - **Komoditas HS:** Filter kode HS 2-digit (`hs2`).

### 4.2. Penanganan HS Unmapped Neraca via ANSI SQL `SUBSTR()`
Pada metodologi Neraca, komoditas yang belum terdaftar di Master HS BPS (`t.hs_id IS NULL`) tetap dicatat dengan status `mapping_status = 'unmapped'` dan menyimpan kode mentah pada kolom `t.hs_code_raw`.
Query filter HS2 pada `FilteringController` menggunakan sintaks standar ANSI SQL:
```php
if ($request->filled('hs2') && $request->hs2 !== 'ALL') {
    $hs2Filter = trim((string) $request->hs2);
    if ($tipeSumber === 'neraca') {
        $query->where(function ($q) use ($hs2Filter) {
            $q->where('master_hs.hs2', $hs2Filter)
                ->orWhere(function ($sub) use ($hs2Filter) {
                    $sub->whereNull('t.hs_id')
                        ->whereRaw('SUBSTR(t.hs_code_raw, 1, 2) = ?', [$hs2Filter]);
                });
        });
    } else {
        $query->where('master_hs.hs2', $hs2Filter);
    }
}
```
*Keunggulan:* Sintaks `SUBSTR()` kompatibel secara penuh pada MySQL operasional maupun SQLite in-memory, tanpa menyebabkan kesalahan driver seperti `LEFT()`.

### 4.3. Isolasi Status Visibilitas & Proteksi Trash
- **Pegawai (Role 2):** Wajib dibatasi hanya membaca baris dari batch berstatus `data_batches.status = 'RILIS'`. Data draft atau internal PRIVATE tidak bocor ke Pegawai.
- **Viewer Eksekutif (Role 3):** Membaca data `RILIS` dan `PRIVATE`.
- **Super Admin (Role 1):** Dapat memantau seluruh status atau memfilter status tertentu.
- **Pengecualian Data Sampah:** Seluruh transaksi dari batch yang berada di modul Trash (`whereNull('restored_at')`) secara otomatis dikeluarkan dari query penelusuran.

### 4.4. Presisi Kalkulasi Finansial (BCMath DECIMAL 20,6)
Ringkasan metrik penelusuran dihitung menggunakan fungsi matematika presisi tinggi `BCMath` untuk mencegah distorsi *floating point*:
```php
$summaryAgg = (clone $query)
    ->selectRaw('COUNT(*) as total_rows, COALESCE(SUM(t.nilai), 0) as total_nilai, COALESCE(SUM(t.berat), 0) as total_berat')
    ->first();

$totalRows = (int) ($summaryAgg->total_rows ?? 0);
$totalNilaiExact = bcadd((string) ($summaryAgg->total_nilai ?? '0'), '0', 6);
$totalBeratExact = bcadd((string) ($summaryAgg->total_berat ?? '0'), '0', 6);
$totalNilaiJuta = round((float) bcdiv($totalNilaiExact, '1000000', 6), 2);
$totalBeratRibuTon = round((float) bcdiv($totalBeratExact, '1000000', 6), 2);
```

---

## 5. AUDIT ANTARMUKA & INTERAKSI PENGGUNA (UI/UX)

Pengujian visual interaktif dilakukan menggunakan subagent browser pada server lokal `http://127.0.0.1:8000`.

### 5.1. Bukti Visual Halaman Log Aktivitas (`/activity-logs`)
- **Tampilan Desktop (1280x800):**
  - Kartu filter tertata rapi dalam grid 3 kolom yang proporsional.
  - Tombol aksi "Reset Filter" dan "Terapkan Filter" sejajar sempurna di sudut kanan bawah card (*boxed alignment*), tidak mengambang di luar kartu.
  - Tabel audit trail menyajikan 7 kolom dengan penomoran berlanjut (*continuous numbering*).
  - Waktu transaksi mencantumkan penanda `WIB` yang jelas.
  - Alamat IP dirender menggunakan elemen `<code>` pill abu-abu.
- **Uji Interaksi Filter:**
  - Penerapan filter modul `Upload Data` berhasil menyaring 12 record secara instan.
  - Tombol `Reset Filter` mengembalikan data ke tampilan penuh (111 log tercatat).
- **Responsivitas Mobile (390x844):**
  - Grid filter kolaps secara otomatis menjadi 1 kolom vertikal.
  - Tabel audit trail ditempatkan dalam container horizontal scroll yang mulus; lebar bodi `375.2px` aman di dalam viewport `390px` (**0 horizontal layout break**).

### 5.2. Bukti Visual Halaman Filtering Data (`/filtering-data`)
- **Tampilan Desktop (1280x800):**
  - Heading halaman memuat eyebrow biru uppercase `BPS PROVINSI RIAU`.
  - Switcher metodologi berbentuk segmented pills: `Distribusi (Pelabuhan Riau)` dan `Neraca (Asal Barang Riau)`.
  - Pilihan data per halaman: 10, 25, 50, dan 100 data.
  - Grid filter 4x2 desktop: Tahun, Bulan, Triwulan, Kategori Transaksi, Kabupaten/Kota, Komoditas HS, Negara Mitra, dan Pelabuhan.
  - **Summary Bar (Hijau):** Menampilkan total baris hasil penelusuran, Total Nilai dalam Juta US$, dan Total Berat Netto dalam Ribu Ton.
  - **Format Angka Standar BPS:** Angka nilai dan berat menggunakan pemisah ribuan titik (`.`) dan desimal koma (`,`).
  - **Modal Detail Transaksi:** Mengklik tombol "Detail" membuka modal dialog dengan backdrop blur yang memuat rincian komprehensif: Badge HS, Periode, Jenis, Status Batch, Negara, Pelabuhan, Nilai FOB/CIF, Berat Netto, Batch Sumber, serta Uraian Lengkap Master HS BPS.
  - **Paginasi SiPantau:** Navigasi nomor halaman dengan tombol Sebelumnya/Selanjutnya dan elipsis (`1 ... 4 5 6 ... 10`).
- **Responsivitas Mobile (390x844):**
  - Filter 4x2 kolaps menjadi 1 kolom vertikal yang mudah disentuh (*touch-friendly*).
  - Summary bar menyesuaikan tinggi baris secara dinamis tanpa pemotongan teks.

### 5.3. Daftar Berkas Bukti Tangkapan Layar (Browser Evidence)
Seluruh screenshot tersimpan rapi pada direktori `browser_evidence/phase_2g_10_6_4_11/`:

| No | File Screenshot | Resolusi | Deskripsi Verifikasi Visual |
|:---:|:---|:---:|:---|
| 1 | `02_activity_logs_desktop.png` | 1280x800 | Halaman Log Aktivitas Sistem desktop, tabel audit trail, dan filter card 3-kolom. |
| 2 | `03_activity_logs_filtered.png` | 1280x800 | Hasil filter modul Upload Data dan verifikasi fungsi tombol Reset. |
| 3 | `04_activity_logs_mobile.png` | 390x844 | Tampilan mobile Log Aktivitas, grid kolaps 1 kolom, container tabel horizontal scroll. |
| 4 | `05_filtering_data_desktop.png` | 1280x800 | Halaman Filtering Data metodologi Distribusi, summary bar hijau, tabel transaksi. |
| 5 | `06_filtering_neraca_switch.png` | 1280x800 | Verifikasi switching metodologi ke Neraca (Asal Barang Riau) secara reaktif. |
| 6 | `07_filtering_modal_detail.png` | 1280x800 | Modal dialog 'Detail Metadata Transaksi' terbuka dengan uraian lengkap master BPS. |
| 7 | `08_filtering_data_mobile.png` | 390x844 | Tampilan mobile Filtering Data, formulir filter vertikal tanpa overflow halaman. |

---

## 6. HASIL PENGUJIAN BACKEND & PHPUNIT SUITE

### 6.1. Pengujian Khusus Kelompok 7 (`Phase2gGroup7ActivityLogsAndFilteringTest`)
File test baru ditambahkan di `tests/Feature/Phase2gGroup7ActivityLogsAndFilteringTest.php` untuk menguji skenario ekstensif:
1. `test_activity_logs_requires_authentication`: Menolak pengguna tanpa login (redirect 302).
2. `test_activity_logs_super_admin_can_view_all_logs`: Super Admin melihat log seluruh pengguna.
3. `test_activity_logs_pegawai_sees_only_own_logs`: Pegawai hanya melihat aktivitas miliknya sendiri (termasuk isolasi dropdown).
4. `test_activity_logs_viewer_eksekutif_is_redirected`: Viewer Eksekutif dilarang mengakses audit trail (redirect 302 ke dashboard).
5. `test_activity_logs_filters_work_correctly`: Pengujian filter kata kunci `q`, modul, dan rentang tanggal.
6. `test_activity_logs_zero_sensitive_data_leakage`: Memastikan zero leakage terhadap password plaintext, hash `$2y$`, dan token.
7. `test_filtering_query_rejects_invalid_source`: Menolak nilai sumber selain distribusi/neraca dengan status 422.
8. `test_filtering_query_strictly_separates_distribusi_and_neraca`: Memastikan isolasi data mart Distribusi dan Neraca.
9. `test_filtering_query_pegawai_cannot_see_private_data`: Pegawai dilarang membaca data dari batch PRIVATE.
10. `test_filtering_query_excludes_trashed_batches`: Data transaksi pada batch di tabel Trash diabaikan.
11. `test_filtering_query_summary_calculation_matches_exact_sum`: Total nilai dan berat ringkasan persis sama dengan jumlah baris transaksi (presisi BCMath).
12. `test_filtering_query_neraca_unmapped_hs_code_filtering`: Baris unmapped pada Neraca terfilter akurat via ANSI SQL `SUBSTR(t.hs_code_raw, 1, 2)`.
13. `test_filtering_query_supports_per_page_options`: Memverifikasi opsi paginasi 10, 25, 50, dan 100 data.

**Hasil Eksekusi:**
```
Tests: 13, Passed: 13, Assertions: 69, Duration: 1.44s (100% PASS)
```

### 6.2. Eksekusi Seluruh Test Suite Repositori
Menjalankan perintah `php vendor/phpunit/phpunit/phpunit` secara menyeluruh:
```
{"tool":"phpunit","result":"passed","tests":193,"passed":193,"assertions":1596,"duration_ms":102134}
```
**Status: 193 Tests LULUS 100% (0 Failed, 0 Errors, 1.596 Assertions, Waktu: 102 detik).**

---

## 7. VERIFIKASI INTEGRITAS DATABASE OPERASIONAL

Pemeriksaan PDO runtime secara faktual terhadap database operasional `sidatex` membuktikan bahwa seluruh proses audit, eksekusi test PHPUnit in-memory, dan uji antarmuka browser sama sekali tidak mencemari database operasional:

```
Connected Database: sidatex
data_batches: 12
distribusi_transactions: 5169
neraca_transactions: 6192
monthly_trade_summaries: 11155
activity_logs: 111
trash: 0
users: 3
```
*Kesimpulan Proteksi:* **0 Baris Bertambah / Berkurang (Zero Mutation). Database operasional tetap murni dan terlindungi.**

---

## 8. KESIMPULAN & STATUS KESIAPAN COMMIT

Seluruh 6 berkas dalam **Kelompok 7: Activity Logs, Filter UI & Standarisasi Halaman** telah diaudit, disempurnakan keamanannya, diverifikasi tata letak responsifnya, dan diuji melalui 193 pengujian otomatis dengan tingkat kelulusan 100%.

### Status Kesiapan: **READY FOR COMMIT**
- **Rekomendasi Pesan Commit:**  
  `feat(logs): refine activity logs table, unified pagination, and filter grid styling`
- **Tindakan Lanjutan:** Melanjutkan audit Git ke Kelompok 8 (Dokumentasi Proyek, Arsip Laporan & Konfigurasi Git).
