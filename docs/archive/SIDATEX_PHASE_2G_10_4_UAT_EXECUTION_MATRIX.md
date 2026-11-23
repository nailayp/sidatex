# SIDATEX — MATRIKS PELAKSANAAN MANUAL UAT (PHASE 2G.10.4)
## Master Field Execution Matrix (30 Test Cases)

**Aplikasi:** Sistem Informasi Data Ekspor-Impor (SIDATEX) — BPS Provinsi Riau  
**Lingkungan:** Database Testing `sidatex_testing` (MySQL Port 3306)  
**Total Skenario UAT:** 30 Skenario  
**Status Skenario:** 29 Skenario Siap UAT Lapangan | 1 Skenario Tertahan / Diblokir  
**Status Eksekusi Aktual:** `PENDING FIELD EXECUTION BY BPS RIAU` (Belum ada skenario yang dijalankan atau ditandai PASS)  

---

## 1. PETUNJUK PENGGUNAAN MATRIKS

Matriks ini merupakan instrumen pelacakan operasional resmi bagi Koordinator UAT dan Penguji BPS Provinsi Riau.
- **Status Kesiapan:**
  - `READY FOR FIELD UAT`: Skenario telah terverifikasi, prasyarat sistem terpenuhi, dan siap diuji langsung oleh staf BPS.
  - `BLOCKED — WAITING FOR APPROVED TEST BATCH`: Skenario ditangguhkan demi melindungi integritas data baseline statistik.
- **Status Hasil Lapangan:** Seluruh baris hasil lapangan sengaja dibiarkan **KOSONG / PENDING** sampai staf BPS menyelesaikan pengujian faktual dan menandatangani lembar evaluasi.
- **Format Tangkapan Layar (Screenshot):** Wajib mengikuti format penamaan baku:  
  `[Test_Case_ID]_[Role]_[Deskripsi_Tampilan].png`  
  Contoh: `TC_UAT_PG_06_Pegawai_Asymmetry_Alert_Banner.png`

---

## 2. MATRIKS 30 TEST CASE LAPANGAN

### BAGIAN A: PERAN SUPER ADMIN (10 Skenario)

| Test Case ID | Role Penguji | Modul Aplikasi | Prasyarat Pengujian | Status Kesiapan | Bukti yang Dibutuhkan | Catatan Koordinator & Peringatan Keselamatan |
|:---|:---|:---|:---|:---:|:---|:---|
| **TC-UAT-SA-01** | Super Admin | Autentikasi & Navigasi | Akun `admin_sidatex` aktif, server berjalan | `READY FOR FIELD UAT` | Screenshot Dashboard Super Admin & Sidebar lengkap (5 menu) | Verifikasi akses penuh ke seluruh modul sistem. |
| **TC-UAT-SA-02** | Super Admin | Manajemen Data | Batch #71 dan Batch #720 terdaftar | `READY FOR FIELD UAT` | Screenshot tabel data batches beserta badge status `RILIS` & `PRIVATE` | Pastikan tidak ada tombol edit langsung pada metadata historis. |
| **TC-UAT-SA-03** | Super Admin | Manajemen Data | Batch non-historis staging terisolasi | `BLOCKED — WAITING FOR APPROVED TEST BATCH` | *Tidak ada eksekusi* | **DILARANG MENGUBAH STATUS BATCH #71, #74, ATAU #720.** Menunggu batch staging disetujui. |
| **TC-UAT-SA-04** | Super Admin | Manajemen Data | Batch #71 tersedia | `READY FOR FIELD UAT` | Berkas file `.xlsx` terunduh & screenshot dialog unduh | Verifikasi struktur kolom hasil ekspor FastExcel. |
| **TC-UAT-SA-05** | Super Admin | Upload Data | Berkas DBF dummy/sintetis 5-10 baris | `READY FOR FIELD UAT` | Screenshot preview parsing DBF & ringkasan validasi record | Gunakan berkas sintetis uji; jangan gunakan DBF operasional. |
| **TC-UAT-SA-06** | Super Admin | Upload Data | Preview DBF sintetis berhasil divalidasi | `READY FOR FIELD UAT` | Screenshot notifikasi sukses simpan batch & alert audit | Pastikan data masuk ke `sidatex_testing` sebagai draft terisolasi. |
| **TC-UAT-SA-07** | Super Admin | Trash Data | Akses menu Trash Data terbuka | `READY FOR FIELD UAT` | Screenshot daftar batch terhapus lunak (soft-deleted) | Pastikan data trash tidak muncul pada Single Dashboard. |
| **TC-UAT-SA-08** | Super Admin | Trash Data | Ada batch non-kritis di trash | `READY FOR FIELD UAT` | Screenshot modal konfirmasi & notifikasi sukses pemulihan | Jangan memulihkan data yang belum diverifikasi asal-usulnya. |
| **TC-UAT-SA-09** | Super Admin | Activity Logs | Aktivitas sistem telah terekam | `READY FOR FIELD UAT` | Screenshot tabel audit log dengan timestamp & IP address | Verifikasi ketepatan pencatatan mutasi data. |
| **TC-UAT-SA-10** | Super Admin | Diseminasi BRS | Batch #71 berstatus RILIS | `READY FOR FIELD UAT` | Berkas `.pptx` terunduh & screenshot slide ringkasan ekspor | Verifikasi angka FOB pada slide cocok dengan Tab 1 Dashboard. |

---

### BAGIAN B: PERAN PEGAWAI BPS (12 Skenario)

| Test Case ID | Role Penguji | Modul Aplikasi | Prasyarat Pengujian | Status Kesiapan | Bukti yang Dibutuhkan | Catatan Koordinator & Peringatan Keselamatan |
|:---|:---|:---|:---|:---:|:---|:---|
| **TC-UAT-PG-01** | Pegawai BPS | Autentikasi & Navigasi | Akun `pegawai_sidatex` aktif | `READY FOR FIELD UAT` | Screenshot sidebar terbatas (hanya Dashboard Statistik) | Pastikan menu Upload, Manajemen Data, Trash, & Log tidak tampil. |
| **TC-UAT-PG-02** | Pegawai BPS | Single Dashboard (Tab 1) | Data Batch #71 aktif | `READY FOR FIELD UAT` | Screenshot 4 KPI Card (Ekspor, Impor, Neraca, Volume) & Grafik Tren | Uji interaktivitas hover tooltip Chart.js. |
| **TC-UAT-PG-03** | Pegawai BPS | Single Dashboard (Tab 2) | Agregasi HS komoditas tersedia | `READY FOR FIELD UAT` | Screenshot tabel time series komoditas & paginasi/sorting | Verifikasi pencarian kode HS dan agregasi nilai USD. |
| **TC-UAT-PG-04** | Pegawai BPS | Single Dashboard (Tab 3) | Koordinat pelabuhan Riau tersedia | `READY FOR FIELD UAT` | Screenshot peta Leaflet.js dengan marker pelabuhan & popup detail | Uji zoom-in dan klik pada marker Pelabuhan Dumai/Siak. |
| **TC-UAT-PG-05** | Pegawai BPS | Single Dashboard (Tab 4) | Data Distribusi & Neraca tersedia | `READY FOR FIELD UAT` | Screenshot perbandingan Trade Balance bulanan & kumulatif | Periksa kesesuaian formula: Ekspor (FOB) - Impor (CIF). |
| **TC-UAT-PG-06** | Pegawai BPS | Single Dashboard (Tab 4) | Batch #71 RILIS & Batch #74 PRIVATE | `READY FOR FIELD UAT` | Screenshot banner notifikasi Amber "Asimetri Periode Kumulatif" | Verifikasi teks peringatan remediasi F-03 tampil jelas. |
| **TC-UAT-PG-07** | Pegawai BPS | Single Dashboard (Filter) | Switcher Metodologi aktif | `READY FOR FIELD UAT` | Screenshot popup tooltip metodologi Distribusi (POD/KDPROV) & Neraca | Verifikasi kejelasan definisi statistik BPS Riau (remediasi F-05). |
| **TC-UAT-PG-08** | Pegawai BPS | Single Dashboard (Tab 1) | Filter periode tanpa data pembanding | `READY FOR FIELD UAT` | Screenshot badge pertumbuhan bertuliskan `N/A` (bukan `0,00%`) | Pastikan sistem tidak mengganti pembagian nol secara diam-diam. |
| **TC-UAT-PG-09** | Pegawai BPS | Single Dashboard (Filter) | Batch #720 berstatus PRIVATE | `READY FOR FIELD UAT` | Screenshot ketiadaan angka Batch #720 pada filter dashboard Pegawai | Bukti isolasi data sensitif/internal dari publikasi staf umum. |
| **TC-UAT-PG-10** | Pegawai BPS | Otorisasi RBAC URL | URL langsung `/upload-data` | `READY FOR FIELD UAT` | Screenshot halaman error HTTP 403 Forbidden | Verifikasi server-side security gate bekerja sempurna. |
| **TC-UAT-PG-11** | Pegawai BPS | Otorisasi RBAC URL | URL langsung `/trash-data` | `READY FOR FIELD UAT` | Screenshot halaman error HTTP 403 Forbidden | Verifikasi server-side security gate bekerja sempurna. |
| **TC-UAT-PG-12** | Pegawai BPS | Otorisasi RBAC URL | URL langsung `/activity-logs` | `READY FOR FIELD UAT` | Screenshot halaman error HTTP 403 Forbidden | Verifikasi server-side security gate bekerja sempurna. |

---

### BAGIAN C: PERAN VIEWER EKSEKUTIF (4 Skenario)

| Test Case ID | Role Penguji | Modul Aplikasi | Prasyarat Pengujian | Status Kesiapan | Bukti yang Dibutuhkan | Catatan Koordinator & Peringatan Keselamatan |
|:---|:---|:---|:---|:---:|:---|:---|
| **TC-UAT-VE-01** | Viewer Eksekutif | Dashboard Eksekutif | Akun `viewer_sidatex` aktif | `READY FOR FIELD UAT` | Screenshot Dashboard Eksekutif dengan visualisasi KPI makro | Verifikasi tata letak ringkas ramah pimpinan. |
| **TC-UAT-VE-02** | Viewer Eksekutif | Dashboard Eksekutif | Batch #71 (RILIS) & #720 (PRIVATE) | `READY FOR FIELD UAT` | Screenshot angka gabungan/tinjauan angka internal sebelum rilis | Viewer diizinkan menelaah draft angka internal BPS. |
| **TC-UAT-VE-03** | Viewer Eksekutif | Dashboard Eksekutif | Hak akses Read-Only murni | `READY FOR FIELD UAT` | Screenshot area kerja bebas dari tombol aksi/mutasi | Tidak ada tombol upload, toggle, delete, atau restore. |
| **TC-UAT-VE-04** | Viewer Eksekutif | Otorisasi Mutasi URL | Akses POST langsung ke endpoint mutasi | `READY FOR FIELD UAT` | Screenshot respons HTTP 403 Forbidden atau redirect proteksi | Verifikasi pencegahan eksekusi API/endpoint non-GET. |

---

### BAGIAN D: SKENARIO KHUSUS & LINTAS PERAN (4 Skenario)

| Test Case ID | Role Penguji | Modul Aplikasi | Prasyarat Pengujian | Status Kesiapan | Bukti yang Dibutuhkan | Catatan Koordinator & Peringatan Keselamatan |
|:---|:---|:---|:---|:---:|:---|:---|
| **TC-UAT-SP-01** | Tim Gabungan | Single Dashboard | Data periode yang sama pada kedua metodologi | `READY FOR FIELD UAT` | Screenshot perbandingan angka Distribusi vs Neraca | **DILARANG MENJUMLAHKAN KEDUA METODOLOGI.** Verifikasi pemisahan tegas. |
| **TC-UAT-SP-02** | Penguji Bebas | Tampilan Antarmuka | Browser desktop & tablet/laptop | `READY FOR FIELD UAT` | Screenshot tampilan pada resolusi 1366x768, 1920x1080, & zoom 125% | Pastikan tata letak tabel dan grafik tidak saling tumpang tindih. |
| **TC-UAT-SP-03** | Seluruh Role | Manajemen Sesi | Login aktif pada aplikasi | `READY FOR FIELD UAT` | Screenshot redirect ke `/login` & tombol browser Back terproteksi | Pastikan sesi terhapus bersih dan tidak bisa diakses lewat Back history. |
| **TC-UAT-SP-04** | Seluruh Role | Manajemen Sesi | Sesi dibiarkan idle hingga timeout | `READY FOR FIELD UAT` | Screenshot pesan session expired & redirect aman ke form login | Menjamin perlindungan data statistik saat komputer ditinggalkan. |

---

## 3. REKAPITULASI KESIAPAN EKSEKUSI

```
+-----------------------------------------------------------------------------------+
|                        RINGKASAN STATUS KESIAPAN SKENARIO                         |
+-----------------------------------------------------------------------------------+
| Total Skenario Diuji             : 30 Skenario                                    |
| Skenario Siap UAT Lapangan       : 29 Skenario (TC-UAT-SA-01 s.d. TC-UAT-SP-04)   |
| Skenario Ditangguhkan (Blocked)  :  1 Skenario (TC-UAT-SA-03 Toggle Release)      |
| Skenario Lulus Faktual           :  0 Skenario (Menunggu Pelaksanaan Staf BPS)    |
| Skenario Gagal Faktual           :  0 Skenario (Menunggu Pelaksanaan Staf BPS)    |
+-----------------------------------------------------------------------------------+
```

> **PERINGATAN KOORDINATOR UAT:**  
> Dilarang mengisi kolom status eksekusi aktual dengan klaim "PASS" sebelum pengujian faktual dilakukan oleh staf BPS Provinsi Riau di lapangan. Seluruh hasil wajib didukung bukti tangkapan layar (*screenshot*) dan lembar umpan balik bertanda tangan.
