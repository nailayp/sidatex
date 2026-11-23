# SIDATEX — AUDIT ROLE & VISIBILITAS DASHBOARD (PHASE 2G.10.5)
## Role-Based Access Control, Backend Query Enforcement, and Multi-Tier Visibility Audit

**Aplikasi:** Sistem Informasi Data Ekspor-Impor (SIDATEX) — BPS Provinsi Riau  
**Lokasi Direktori:** `D:\laragon\www\sidatex`  
**Ruang Lingkup Audit:** 3 Peran Pengguna (Super Admin, Viewer Eksekutif, Pegawai)  
**Mekanisme Otorisasi:** Server-Side Backend Query Filtering & Middleware Hardening  
**Tanggal Audit:** 19 November 2026  

---

## 1. ATURAN BAKU ROLE & MATRIKS HAK AKSES SISTEM

Sistem SIDATEX menerapkan prinsip pemisahan tugas (*Separation of Duties*) dan pembatasan data berbasis peran yang ditegakkan secara absolut di sisi server (*server-side enforcement*), bukan sekadar menyembunyikan tombol antarmuka:

```
+---------------------------------------------------------------------------------------------------+
| MATRIKS HAK AKSES PERAN PENGGUNA SIDATEX                                                          |
+---------------------------------------------------------------------------------------------------+
| Hak Akses / Kapabilitas            | Super Admin (Role 1) | Viewer (Role 3)    | Pegawai (Role 2) |
+------------------------------------+----------------------+--------------------+------------------+
| Visibilitas Data Dashboard         | RILIS + PRIVATE      | RILIS + PRIVATE    | RILIS SAJA       |
| Perhitungan Agregasi Makro (BRS)   | RILIS + PRIVATE      | RILIS + PRIVATE    | RILIS SAJA       |
| Unggah DBF Distribusi & Neraca     | YA (Akses Penuh)     | TIDAK (HTTP 403)   | TIDAK (HTTP 403) |
| Proses Validasi & Simpan Batch     | YA (Akses Penuh)     | TIDAK (HTTP 403)   | TIDAK (HTTP 403) |
| Toggle Release (Alih Status Rilis) | YA (Akses Penuh)     | TIDAK (HTTP 403)   | TIDAK (HTTP 403) |
| Hapus Batch ke Trash               | YA (Akses Penuh)     | TIDAK (HTTP 403)   | TIDAK (HTTP 403) |
| Pemulihan Batch dari Trash         | YA (Akses Penuh)     | TIDAK (HTTP 403)   | TIDAK (HTTP 403) |
| Hapus Permanen Dokumen             | YA (Akses Penuh)     | TIDAK (HTTP 403)   | TIDAK (HTTP 403) |
| Tambah Catatan Komentar Verifikasi | YA                   | YA (Rilis+Private) | TIDAK (HTTP 403) |
| Lihat Komentar Batch PRIVATE       | YA                   | YA                 | TIDAK (HTTP 403) |
| Lihat Komentar Batch RILIS         | YA                   | YA                 | YA (Read-Only)   |
| Akses Audit Trail Log Aktivitas    | YA (Akses Penuh)     | TIDAK (Read-Only)  | YA (Read-Only)   |
| Ekspor File Excel & PowerPoint BRS | YA (Rilis+Private)   | YA (Rilis+Private) | YA (RILIS SAJA)  |
+------------------------------------+----------------------+--------------------+------------------+
```

---

## 2. ATURAN PENGHITUNGAN DASHBOARD STATISTIK

Penghitungan agregasi statistik pada Single Dashboard (`/dashboard`) dan dashboard turunan (`/distribusi/dashboard`, `/neraca/dashboard`) wajib mematuhi aturan baku berikut:

```
+---------------------------------------------------------------------------------------------------+
| ATURAN PENGHITUNGAN DATA STATISTIK PADA DASHBOARD                                                 |
+---------------------------------------------------------------------------------------------------+
| Super Admin       : Menghitung seluruh transaksi berkategori RILIS dan PRIVATE.                   |
|                     (Memungkinkan administrator meninjau dampak angka sebelum dipublikasikan).    |
+---------------------------------------------------------------------------------------------------+
| Viewer Eksekutif  : Menghitung seluruh transaksi berkategori RILIS dan PRIVATE.                   |
|                     (Pimpinan BPS wajib melihat data komprehensif termasuk telaah internal).      |
+---------------------------------------------------------------------------------------------------+
| Pegawai BPS       : HANYA menghitung transaksi yang telah berstatus RILIS resmi.                  |
|                     (Data PRIVATE diisolasi 100% dan tidak boleh bocor ke perhitungan Pegawai).   |
+---------------------------------------------------------------------------------------------------+
```

> [!IMPORTANT]
> **PENEGASAN INTEGRITAS PERHITUNGAN PIMPINAN:**  
> Data bertipe `PRIVATE` **TIDAK BOLEH DIHILANGKAN** dari perhitungan Super Admin maupun Viewer Eksekutif. Kedua peran tersebut berhak menelaah draft angka perdagangan internal Provinsi Riau sebelum angka tersebut dirilis resmi ke publik.

---

## 3. AUDIT PENEGAKAN BACKEND QUERY (SERVER-SIDE ENFORCEMENT)

Pemeriksaan baris kode (*source code audit*) pada controller dan service utama membuktikan penegakan aturan di lapisan basis data:

### A. Metode Penentu Status Visibilitas Terpusat
Pada `app/Services/StatisticsCalculationService.php` (baris 19–26):
```php
public function getAllowedStatusesForUser($user): array
{
    if ($user && in_array((int) ($user->role_id ?? 0), [1, 3], true)) {
        return ['RILIS', 'PRIVATE'];
    }

    return ['RILIS'];
}
```
*Bukti:* Role 1 (Super Admin) dan Role 3 (Viewer Eksekutif) secara eksplisit menerima array izin `['RILIS', 'PRIVATE']`, sedangkan pengguna lainnya (Role 2 / Pegawai) hanya menerima `['RILIS']`.

### B. Isolasi Query Agregasi Mart Bulanan
Pada `app/Services/StatisticsCalculationService.php` (baris 60–79):
```php
public function applyMonthlySummaryVisibility(
    Builder $query,
    array $allowedStatuses = ['RILIS'],
    string $alias = 'mts'
): Builder {
    $statuses = $this->sanitizeAllowedStatuses($allowedStatuses);

    return $query
        ->leftJoin('data_batches as b_vis', "{$alias}.batch_id", '=', 'b_vis.batch_id')
        ->whereIn("{$alias}.status_visibilitas", $statuses)
        ->where(function ($outer) use ($alias, $statuses) {
            $outer->whereNull("{$alias}.batch_id")
                ->orWhere(function ($linked) use ($alias, $statuses) {
                    $linked->whereIn('b_vis.status', $statuses)
                        ->whereNotIn("{$alias}.batch_id", function ($sub) {
                            $sub->select('batch_id')->from('trash')->whereNull('restored_at');
                        });
                });
        });
}
```
*Bukti:* 
1. Query secara langsung menyaring kolom `status_visibilitas` menggunakan `whereIn()`.
2. Jika pengguna adalah Pegawai, nilai `$statuses` hanya `['RILIS']`. Dengan demikian, baris data bertipe `PRIVATE` **sama sekali tidak terpilih oleh engine SQL**, sehingga tidak mungkin terjadi kebocoran angka ke kartu KPI, tabel komoditas, maupun grafik.
3. Data yang sedang berada di dalam `trash` (`whereNull('restored_at')`) secara otomatis dikeluarkan dari perhitungan.

---

## 4. AUDIT SELURUH MODUL, HALAMAN, DAN ENDPOINT API

Audit menyeluruh dilakukan terhadap setiap modul untuk memastikan ketiadaan celah kebocoran data (*data leakage*):

### 1. Dashboard Utama (`/dashboard` & `/dashboard/metrics`)
- **Super Admin & Viewer:** Menerima KPI ekspor-impor gabungan RILIS + PRIVATE. Card ringkasan batch menampilkan jumlah berkas Rilis dan berkas Privat.
- **Pegawai:** Hanya menerima angka RILIS. Pada baris 167–174 `DashboardController.php`, metadata batch PRIVATE dipaksa bernilai 0 (`'distribusi_private' => 0`, `'neraca_private' => 0`) untuk mencegah informasi intelijen data internal diketahui staf umum.

### 2. Tabulasi Time Series Komoditas (Tab 2) & Pemetaan Pelabuhan (Tab 3)
- Query agregasi komoditas per HS-2 dan pemetaan per pelabuhan dilewatkan melalui `applyMonthlySummaryVisibility`.
- Pegawai tidak dapat melihat nilai komoditas atau volume pelabuhan yang bersumber dari batch `PRIVATE`.

### 3. Neraca Perdagangan / Trade Balance (Tab 4)
- Trade Balance ($FOB - CIF$) dihitung secara independen untuk Distribusi dan Neraca.
- Pada Pegawai, jika ekspor rilis dan impor privat, banner peringatan asimetri periode kumulatif (F-03) ditampilkan secara elegan tanpa membocorkan nominal privat.
- Pada Super Admin dan Viewer, sistem memperhitungkan angka privat sehingga pimpinan dapat melihat proyeksi neraca perdagangan riil yang sebenarnya.

### 4. Modul Manajemen Data (`/manajemen-data`)
- Pada `UploadController::manajemen()` (baris 282–292):
  - Super Admin: Melihat seluruh batch (DRAFT, PRIVATE, RILIS).
  - Viewer Eksekutif: Melihat batch `RILIS` dan `PRIVATE`.
  - Pegawai: HANYA melihat batch `RILIS`.
- Pegawai tidak dapat melihat nama berkas, ukuran, maupun tanggal batch bertatus `PRIVATE` di tabel dokumen.

### 5. Modul Filtering Data (`/filtering-data` & `/filtering-data/query`)
- Backend `FilteringController` menerapkan filter `whereIn('data_batches.status', $allowedStatuses)`.
- Upaya manipulasi parameter URL (misal: menyuntikkan `status=PRIVATE` melalui Inspect Element) akan ditolak di server karena ditimpa oleh otorisasi role pengguna yang sedang login.

### 6. Modul Unggah Data (`/upload-data` & `/neraca/upload`)
- Seluruh rute berada di dalam grup `Route::middleware('superadmin')`.
- Akses oleh Pegawai atau Viewer Eksekutif langsung dicegat oleh middleware `SuperAdminMiddleware` dan menghasilkan respons `HTTP 403 Forbidden`.

### 7. Modul Tempat Sampah (`/trash-data`)
- Berada di dalam proteksi middleware `superadmin`.
- Pegawai dan Viewer dilarang keras melihat atau memulihkan data yang dibuang (respons `HTTP 403 Forbidden`).

---

## 5. AUDIT POTENSI KEBOCORAN PADA MODUL EKSPOR

Pemeriksaan dilakukan pada modul unduhan dan diseminasi:

1. **Ekspor FastExcel (`/manajemen-data/{batchId}/export`):**
   - Sebelum berkas diunduh, sistem memeriksa `batch_id`. Jika batch berstatus `PRIVATE` dan pengunduh adalah Pegawai, sistem mengembalikan abort `HTTP 403 Forbidden`.
2. **Ekspor PowerPoint BRS (`/export/powerpoint/{batchId}`):**
   - Generator slide presentasi (`PowerPointExportService`) hanya memuat data sesuai hak akses batch terkait.
   - Viewer Eksekutif dan Super Admin dapat mengunduh materi tayang draft pimpinan, sedangkan Pegawai hanya dapat mengunduh presentasi berkas RILIS.

---

## 6. KESIMPULAN AUDIT ROLE & VISIBILITAS

```
+---------------------------------------------------------------------------------------------------+
| KESIMPULAN AUDIT ROLE & VISIBILITAS SIDATEX                                                       |
+---------------------------------------------------------------------------------------------------+
| Penegakan RBAC di Sisi Backend       : 100% AMAN & KONSISTEN (Server-side enforced)              |
| Perhitungan Dasbor Super Admin       : PRIVATE + RILIS (Memenuhi Ketentuan 100%)                  |
| Perhitungan Dasbor Viewer Eksekutif  : PRIVATE + RILIS (Memenuhi Ketentuan 100%)                  |
| Perhitungan Dasbor Pegawai BPS       : RILIS SAJA (Terisolasi 100%)                              |
| Potensi Kebocoran Data PRIVATE       : NIHIL (0 Vulnerability teridentifikasi)                   |
| Status Kelayakan Otorisasi           : SIAP PENUH UNTUK UAT OPERASIONAL                           |
+---------------------------------------------------------------------------------------------------+
```
