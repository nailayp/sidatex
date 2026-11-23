# CHECKLIST PRASYARAT KESIAPAN PELAKSANAAN MANUAL UAT
## SISTEM INFORMASI DATA EKSPOR-IMPOR (SIDATEX)
### BADAN PUSAT STATISTIK (BPS) PROVINSI RIAU

**Kode Dokumen:** `SIDATEX-DOC-UAT-CHECKLIST-2G10`  
**Versi Dokumen:** 1.0  
**Tanggal Terbit:** 19 November 2026  
**Klasifikasi:** Instrumen Verifikasi Kesiapan UAT (*Go / No-Go Decision*)  
**Status Kesiapan:** `MANUAL UAT PACKAGE PREPARED — PENDING BPS FIELD EXECUTION`

---

## 1. PENDAHULUAN & TUJUAN CHECKLIST

Sebelum staf dan analis BPS Provinsi Riau memulai pengujian manual antarmuka dan proses bisnis SIDATEX, seluruh prasyarat teknis, keamanan, integritas data, dan administratif wajib diverifikasi secara ketat.

> [!IMPORTANT]
> **ATURAN INTEGRITAS PENGECEKAN:**
> 1. Butir teknis yang telah diverifikasi oleh tim pengembang melalui bukti kode/runtime ditandai dengan status `TERPENUHI (VERIFIED)`.
> 2. Butir administratif, penugasan personel BPS, dan jadwal lapangan BPS ditandai dengan status `PENDING KOORDINASI BPS`. Tim pengembang **DILARANG** menandai terpenuhi butir-butir yang menjadi kewenangan internal BPS tanpa konfirmasi tertulis resmi.
> 3. Sesi pengujian manual baru boleh dimulai secara serentak apabila seluruh 13 butir di bawah ini telah disetujui (*signed off*) oleh Koordinator UAT BPS dan Tim Pengembang.

---

## 2. TABEL VERIFIKASI 13 BUTIR PRASYARAT KESIAPAN UAT

| No | Butir Prasyarat UAT | Deskripsi Kebutuhan & Kriteria Verifikasi | Bukti / Catatan Verifikasi Lapangan | Status Kesiapan |
|:---:|---|---|---|:---:|
| **1** | **URL / Lingkungan Pengujian Tersedia** | Aplikasi dapat diakses via peramban web pada port/URL lingkungan pengujian yang disiapkan teknisi BPS tanpa hambatan jaringan. Untuk pengujian multi-user via LAN, web server wajib di-bind ke `0.0.0.0`. | URL lokal aktif: `http://127.0.0.1:8000/login` (HTTP 200) atau port `8094` via `php artisan serve`. Untuk akses dari PC klien BPS lain di LAN, teknisi membagikan URL IP server lokal (misal: `http://[IP-SERVER]:[PORT]/login`). | **TERPENUHI** *(Technical Ready)* |
| **2** | **Bukan Database Operasional** | Lingkungan runtime dipastikan 100% terhubung ke database pengujian `sidatex_testing`, dan database produksi `sidatex` terproteksi. | Pengecekan PDO runtime: `DB::connection()->getDatabaseName()` mengembalikan `sidatex_testing`. Sembilan metrik `sidatex` utuh. | **TERPENUHI** *(Technical Ready)* |
| **3** | **Akun Super Admin Tersedia** | Akun pengguna dengan hak akses penuh tersedia dan dapat login tanpa galat. | Username: `admin_sidatex`, Role ID: 1, kredensial aktif dan terverifikasi pada tabel `users`. | **TERPENUHI** *(Technical Ready)* |
| **4** | **Akun Pegawai BPS Tersedia** | Akun pengguna analis statistik (*read-only*) tersedia untuk pengujian pembatasan fitur. | Username: `pegawai_sidatex`, Role ID: 2, kredensial aktif dan terverifikasi pada tabel `users`. | **TERPENUHI** *(Technical Ready)* |
| **5** | **Akun Viewer Eksekutif Tersedia** | Akun pengguna pimpinan (*executive viewer*) tersedia untuk penelaahan angka pra-rilis. | Username: `viewer_sidatex`, Role ID: 3, kredensial aktif dan terverifikasi pada tabel `users`. | **TERPENUHI** *(Technical Ready)* |
| **6** | **Role & Hak Akses Terverifikasi** | Middleware dan Controller Guard membatasi akses URL secara tegas (*fail-closed*) dengan HTTP 403. | Tes PHPUnit RBAC lulus (100% lolos), verifikasi route guard `/upload-data`, `/trash-data`, `/activity-logs` berhasil. | **TERPENUHI** *(Technical Ready)* |
| **7** | **Data Pengujian Tersedia & Disetujui** | Tersedia dataset uji coba representatif: Batch #71 (RILIS) dan Batch #720 (PRIVATE). | Batch #71 (3.393 record Distribusi) dan Batch #720 (30.147 record PRIVATE) siap sebagai baseline uji baca. | **TERPENUHI** *(Technical Ready)* |
| **8** | **Data Sensitif Diamankan** | Tidak ada paparan data rahasia perorangan/perusahaan yang tidak sah. Data PRIVATE hanya tampil untuk Role 1 & 3. | Filter visibilitas data diterapkan pada tingkat query Eloquent/DB, diuji pada PHPUnit Test. | **TERPENUHI** *(Technical Ready)* |
| **9** | **Browser Pengujian Tersedia** | Perangkat komputer staf BPS telah terpasang peramban web modern yang mendukung ECMAScript modern. | Google Chrome v110+, Microsoft Edge v110+, atau Mozilla Firefox v110+ dengan resolusi minimal $1366 \times 768$. | **TERPENUHI** *(Technical Ready)* |
| **10** | **Panduan Pengujian Tersedia** | Dokumen panduan langkah demi langkah dan katalog test case telah disusun dan siap digunakan. | Dokumen `SIDATEX_PHASE_2G_10_MANUAL_UAT_GUIDE.md` dan katalog 30 skenario `...TEST_CASES.md` telah selesai disusun. | **TERPENUHI** *(Technical Ready)* |
| **11** | **Staf Penguji Ditentukan** | BPS Provinsi Riau telah menunjuk personel resmi perwakilan dari Fungsi Distribusi, Neraca, dan IPDS. | Daftar nama personel penguji BPS per role (Super Admin, Pegawai, Viewer) menunggu penetapan resmi BPS. | `[ ] PENDING KOORDINASI BPS` |
| **12** | **Jadwal Pengujian Disepakati** | Jadwal sesi pengujian manual, alokasi waktu pengujian, dan batas akhir pengembalian feedback disepakati. | Waktu pelaksanaan pengujian lapangan menunggu konfirmasi kesiapan ruangan dan jadwal staf BPS. | `[ ] PENDING KOORDINASI BPS` |
| **13** | **Penanggung Jawab Pencatatan Ditunjuk** | Personel BPS yang bertindak sebagai Koordinator UAT dan pencatat rangkuman log temuan telah ditetapkan. | Penunjukan PIC Koordinator UAT BPS menunggu penetapan internal pimpinan BPS Provinsi Riau. | `[ ] PENDING KOORDINASI BPS` |

---

## 3. LANGKAH PREFLIGHT WAJIB SEBELUM PENGUJIAN DIMULAI

Sebelum mengklik tombol apapun pada peramban, penguji dan teknisi BPS wajib melakukan 3 langkah verifikasi cepat:

```text
LANGKAH 1: Pastikan Laragon / Web Server aktif (Apache/Nginx dan MySQL berjalan normal).
LANGKAH 2: Pastikan terminal tidak menunjukkan galat koneksi basis data.
LANGKAH 3: Buka URL login pengujian (misal: http://127.0.0.1:8000/login atau port yang disiapkan) dan pastikan formulir login tampil dengan sempurna.
```

---

## 4. LEMBAR PERSETUJUAN KESIAPAN (*GO / NO-GO SIGN-OFF*)

*(Ditandatangani saat seluruh 13 butir prasyarat di atas telah tuntas diverifikasi)*

Berdasarkan hasil pemeriksaan prasyarat di atas, lingkungan dan instrumen pengujian SIDATEX dinyatakan:

- [ ] **GO (SIAP DILAKSANAKAN PENGUJIAN MANUAL LAPANGAN)**
- [ ] **NO-GO (DITUNDA HINGGA BUTIR PRASYARAT YANG BELUM TERPENUHI DISELESAIKAN)**

<br>

| Diverifikasi oleh Tim Teknis Pengembang: | Disetujui oleh Koordinator Pengujian BPS: |
|:---:|:---:|
| <br><br><br>________________________________________<br>**( Lead QA / System Auditor )**<br>Tanggal: 19 November 2026 | <br><br><br>________________________________________<br>**( Koordinator UAT BPS Provinsi Riau )**<br>Tanggal: `____ / ____________ / 2026` |
