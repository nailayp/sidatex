# PANDUAN RINGKAS PELAKSANAAN MANUAL UAT SIDATEX
## Panduan Praktis Pengujian Lapangan untuk Staf BPS Provinsi Riau (Phase 2G.10.4)

Selamat datang di sesi **User Acceptance Testing (UAT) Aplikasi SIDATEX** (Sistem Informasi Data Ekspor-Impor BPS Provinsi Riau). Panduan ringkas ini disusun khusus untuk membantu Bapak/Ibu staf fungsional dan teknis BPS Provinsi Riau dalam menguji aplikasi secara mudah, tertib, dan aman.

---

## 1. LANGKAH AWAL & CARA MENGAKSES APLIKASI

1. **Pastikan Komputer Terhubung ke Jaringan Pengujian:**
   - Jika pengujian dilakukan pada komputer server/lokal: buka peramban (*browser*) dan ketik alamat:  
     `http://127.0.0.1:8000` atau `http://localhost:8000`
   - Jika pengujian dilakukan melalui jaringan lokal kantor BPS (LAN / Wi-Fi): gunakan alamat IP yang diumumkan oleh Koordinator UAT, contoh:  
     `http://192.168.1.X:8094`
2. **Gunakan Peramban Modern:** Disarankan menggunakan Google Chrome, Microsoft Edge, atau Mozilla Firefox versi terbaru.
3. **Tampilan Awal:** Pastikan halaman menampilkan layar login SIDATEX dengan logo BPS Provinsi Riau.

---

## 2. PANDUAN LOGIN SESUAI PERAN PENGUJI

Sistem SIDATEX menerapkan pembatasan hak akses berbasis peran secara ketat. Masuklah menggunakan akun yang telah ditentukan untuk tugas Bapak/Ibu:

| Peran Penguji | Nama Pengguna (*Username*) | Ruang Lingkup Pengujian | Catatan Hak Akses |
|:---|:---|:---|:---|
| **Super Admin** | `admin_sidatex` | Manajemen Data, Upload DBF Sintetis, Trash, Audit Log, Ekspor BRS | Akses penuh seluruh modul (5 menu di bilah samping). |
| **Pegawai BPS** | `pegawai_sidatex` | Single Dashboard (Tab 1–4), Filter Statistik, Uji Keamanan RBAC | Akses analisis data rilis; terblokir dari modul upload & trash. |
| **Viewer Eksekutif** | `viewer_sidatex` | Dashboard Pimpinan, Tinjauan Data Internal BPS | Tampilan ringkas untuk pimpinan, mode baca murni (*read-only*). |

> **CATATAN KREDENSIAL:**  
> Kata sandi (*password*) diserahkan langsung oleh Koordinator UAT melalui lembar tertutup pada saat pembukaan sesi. Jaga kerahasiaan akun selama sesi berlangsung.

---

## 3. CARA MENJALANKAN TEST CASE PER MODUL

Buka dokumen `SIDATEX_PHASE_2G_10_MANUAL_UAT_TEST_CASES.md` dan cari bagian yang sesuai dengan peran Bapak/Ibu:

1. **Jalankan Skenario Berurutan:**  
   Mulailah dari nomor terkecil (misal: `TC-UAT-PG-01`, lalu `TC-UAT-PG-02`, dst.). Jangan melompati urutan agar alur sistem dapat dirasakan secara wajar.
2. **Pahami Hasil yang Diharapkan (*Expected Result*):**  
   Setiap test case mencantumkan apa yang seharusnya tampil di layar (misal: muncul 4 kartu ringkasan, grafik tren dapat di-hover, atau muncul pesan error HTTP 403 saat mencoba masuk ke halaman terlarang).
3. **Bandingkan dengan Tampilan Layar:**  
   Periksa apakah sistem menampilkan hal yang sesuai dengan harapan.

---

## 4. TATA CARA MENGAMBIL BUKTI TANGKAPAN LAYAR (SCREENSHOT)

Setiap skenario pengujian wajib didukung oleh minimal 1 (satu) bukti gambar tangkapan layar.

1. **Cara Mengambil Tangkapan Layar di Windows:**
   - Tekan tombol `Windows + Shift + S` (Snipping Tool), pilih area layar yang relevan, lalu simpan file gambar.
   - Atau tekan tombol `PrtScn` (Print Screen), buka aplikasi Paint, tekan `Ctrl + V`, lalu simpan sebagai file `.png`.
2. **Format Standar Penamaan File Bukti:**  
   `[Nomor_TC]_[Peran]_[Deskripsi_Singkat].png`  
   *Contoh yang benar:*
   - `TC_UAT_SA_01_SuperAdmin_Dashboard_Overview.png`
   - `TC_UAT_PG_06_Pegawai_Asimetri_Alert_Banner.png`
   - `TC_UAT_PG_10_Pegawai_Error_403_Upload_Block.png`
3. **Simpan dalam Satu Folder:**  
   Buat folder di Desktop Bapak/Ibu dengan nama `BUKTI_UAT_[NAMA_BAPAK_IBU]` untuk memudahkan pengumpulan di akhir sesi.

---

## 5. CARA MENGISI LEMBAR HASIL PENGUJIAN

1. Buka lembar kerja evaluasi Bapak/Ibu (`SIDATEX_PHASE_2G_10_MANUAL_UAT_FEEDBACK_FORM.md`).
2. Tuliskan **Hasil Aktual** apa adanya:
   - Jika tampilan sesuai petunjuk: tuliskan *"Tampil normal sesuai harapan"*.
   - Jika ada perbedaan: tuliskan apa yang berbeda (misal: *"Angka impor tidak muncul di tabel"*).
3. **Status Hasil Lapangan:**
   - Pilih `PASS` jika sistem berfungsi sesuai petunjuk.
   - Pilih `FAIL` jika sistem error, salah hitung, atau tata letak berantakan.
   - Pilih `BLOCKED` jika pengujian terhenti akibat kendala sebelumnya.

---

## 6. PROSEDUR PELAPORAN KENDALA ATAU TEMUAN (BUG)

Jika Bapak/Ibu menemukan kesalahan, jangan panik! Temuan Bapak/Ibu sangat berharga untuk kesempurnaan aplikasi.

1. **Catat Rincian Temuan pada Log Temuan (`...FINDINGS_LOG.md`):**
   - **Nomor TC Terkait:** (Contoh: `TC-UAT-PG-04`).
   - **Tingkat Keparahan:**
     - *Critical:* Aplikasi berhenti total / crash layar putih.
     - *Major:* Salah perhitungan angka BRS / salah formula.
     - *Moderate:* Fitur filter tidak merespons.
     - *Minor / Cosmetic:* Teks salah eja, tombol sedikit miring, tooltip terpotong.
   - **Langkah Menemukan:** Tuliskan tombol apa yang diklik sebelum error muncul.
2. **Ambil Tangkapan Layar Error:** Tangkap layar pesan error yang tampil.
3. **Beri Tahu Koordinator UAT:** Angkat tangan atau hubungi koordinator di ruangan.

---

## 7. ATURAN KESELAMATAN DATA (MUTLAK DIPATUHI!)

Demi menjamin pengujian berjalan lancar dan menjaga integritas basis data statistik:

> [!CAUTION]
> **ATURAN MUTLAK KESELAMATAN DATA UAT:**
> 1. **DILARANG MENGUJI TOGGLE RELEASE (TC-UAT-SA-03):**  
>    Jangan pernah mengeklik tombol alih status rilis pada **Batch #71**, **Batch #74**, maupun **Batch #720**. Skenario `TC-UAT-SA-03` secara resmi ditangguhkan (*BLOCKED*) demi melindungi data baseline statistik BRS.
> 2. **DILARANG MENGHAPUS BATCH BASELINE:**  
>    Jangan mencoba menghapus Batch #71, #74, atau #720 ke tempat sampah.
> 3. **HANYA GUNAKAN BERKAS SINTETIS UNTUK UJI UPLOAD:**  
>    Pada modul Upload Data (`TC-UAT-SA-05` dan `SA-06`), gunakan berkas DBF sintetis dummy yang telah disediakan panitia. Jangan mengunggah berkas riil BPS berukuran besar.
> 4. **JANGAN MENGUBAH DATABASE SECARA MANUAL:**  
>    Penguji tidak diperkenankan menjalankan kueri SQL langsung ke basis data.
> 5. **DATABASE OPERASIONAL DILINDUNGI:**  
>    Pengujian ini berlangsung di basis data uji `sidatex_testing` dan tidak akan memengaruhi data publikasi riil BPS di database `sidatex`.

---

**Terima kasih atas partisipasi aktif Bapak/Ibu staf BPS Provinsi Riau dalam menyukseskan implementasi SIDATEX!**
