# KATALOG SKENARIO PENGUJIAN MANUAL (MANUAL UAT TEST CASES)
## SISTEM INFORMASI DATA EKSPOR-IMPOR (SIDATEX)
### BADAN PUSAT STATISTIK (BPS) PROVINSI RIAU

**Kode Dokumen:** `SIDATEX-DOC-UAT-CASES-2G10`  
**Versi Dokumen:** 1.0  
**Tanggal Terbit:** 19 November 2026  
**Klasifikasi:** Dokumen Instrumen Pengujian Lapangan BPS  
**Status Kesiapan:** `MANUAL UAT PACKAGE PREPARED — PENDING BPS FIELD EXECUTION`

---

## PETUNJUK BAGI STAF PENGUJI BPS

> [!IMPORTANT]
> **ATURAN PENGISIAN LEMBAR PENGUJIAN:**
> 1. Dokumen ini diterbitkan oleh tim pengembang dalam kondisi **SIAP UJI**. Seluruh kolom **Hasil Aktual**, **Status**, **Catatan Penguji**, **Bukti Screenshot**, serta **Nama & Tanggal Penguji** sengaja dibiarkan **KOSONG**.
> 2. Staf penguji BPS Provinsi Riau wajib mengisi kolom-kolom kosong tersebut secara manual dan faktual sesuai hasil pengamatan langsung pada peramban (*web browser*).
> 3. Jangan mengubah kolom *Test Case ID*, *Modul*, *Role*, *Tujuan*, *Prasyarat*, *Langkah Pengujian*, dan *Hasil yang Diharapkan*.
> 4. Nilai pada kolom **Status** HANYA boleh diisi dengan salah satu dari empat pilihan berikut:
>    - `PASS`: Sistem beroperasi tepat sesuai kriteria hasil yang diharapkan.
>    - `FAIL`: Ditemukan ketidaksesuaian, anomali angka, perhitungan salah, atau galat sistem.
>    - `BLOCKED`: Skenario tidak dapat dijalankan karena kendala prasyarat (misal file uji DBF belum siap).
>    - `NOT TESTED`: Skenario belum diuji dalam sesi berjalan.
> 5. Jika status bernilai `FAIL` atau `BLOCKED`, catat rincian masalah ke dalam formulir `SIDATEX_PHASE_2G_10_MANUAL_UAT_FINDINGS_LOG.md`.

---

## DAFTAR MODUL DAN DISTRIBUSI SKENARIO

| Kode Modul | Nama Modul | Peran yang Menguji | Jumlah Skenario |
|:---:|---|---|:---:|
| **MOD-AUTH** | Otentikasi, Sesi, dan Hak Akses | Super Admin, Pegawai, Viewer Eksekutif | 5 Skenario |
| **MOD-DASH** | Single Dashboard Statistik Terintegrasi | Super Admin, Pegawai, Viewer Eksekutif | 8 Skenario |
| **MOD-METD** | Metodologi Distribusi & Neraca (F-03, F-05) | Pegawai, Viewer Eksekutif, Lintas Peran | 3 Skenario |
| **MOD-DATA** | Manajemen Data, Riwayat Batch, & Rilis | Super Admin | 3 Skenario |
| **MOD-UPLD** | Unggah Data DBF & Validasi Agregasi | Super Admin | 2 Skenario |
| **MOD-TRSH** | Tempat Sampah (Trash) & Pemulihan | Super Admin | 2 Skenario |
| **MOD-AUDT** | Log Aktivitas Audit Trail | Super Admin | 1 Skenario |
| **MOD-RBAC** | Pengujian Pembatasan Hak Akses Keamanan | Pegawai, Viewer Eksekutif | 6 Skenario |
| **TOTAL** | **Katalog Lengkap Skenario UAT Manual** | **3 Peran Pengguna BPS** | **30 Skenario** |

---

## BAGIAN 1: SKENARIO PERAN SUPER ADMIN (ROLE 1)

### TC-UAT-SA-01: Login Berhasil dan Pengecekan Dashboard Super Admin
- **Modul:** `MOD-AUTH`
- **Role Penguji:** Super Admin (Role 1)
- **Tujuan Pengujian:** Memverifikasi bahwa akun Super Admin dapat login dengan sukses, diarahkan ke dashboard utama, dan memiliki sidebar dengan menu administratif lengkap.
- **Prasyarat:** Peramban web terbuka pada URL login lingkungan pengujian yang disiapkan oleh teknisi (contoh lokal: `http://127.0.0.1:8000/login` atau `http://127.0.0.1:8094/login`; jika diakses dari komputer klien lain di LAN BPS, gunakan alamat IP server staging yang diberikan teknisi, misal: `http://[IP-SERVER]:[PORT]/login`). Akun `admin_sidatex` aktif.
- **Langkah Pengujian:**
  1. Buka URL lingkungan pengujian (misal: `http://127.0.0.1:8000/login` atau URL LAN staging).
  2. Masukkan Username: `admin_sidatex`.
  3. Masukkan Password: `password`.
  4. Klik tombol **Masuk**.
  5. Amati halaman yang dimuat dan periksa elemen menu pada bilah samping (*sidebar*).
- **Hasil yang Diharapkan:**
  - Pengguna dialihkan ke `/dashboard`.
  - Tampil sapaan selamat datang dengan lencana peran **Super Admin**.
  - Sidebar menampilkan menu lengkap: *Dashboard*, *Upload Data*, *Manajemen Data*, *Trash Data*, dan *Log Aktivitas*.
  - Tidak ada pesan galat merah atau *crash* visual pada layar.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-SA-02: Manajemen Data – Tinjauan Riwayat Batch dan Status Rilis
- **Modul:** `MOD-DATA`
- **Role Penguji:** Super Admin (Role 1)
- **Tujuan Pengujian:** Memverifikasi Super Admin dapat melihat daftar riwayat batch data, memeriksa Batch #71 (RILIS) dan Batch #720 (PRIVATE), serta memeriksa metadata batch.
- **Prasyarat:** Login sebagai Super Admin. Berada di halaman `/dashboard`.
- **Langkah Pengujian:**
  1. Klik menu **Manajemen Data** pada sidebar (`/manajemen-data`).
  2. Periksa tabel daftar batch data perdagangan.
  3. Cari baris data untuk **Batch #71** dan **Batch #720**.
  4. Periksa kolom ID Batch, Periode Tahun/Bulan, Jenis Transaksi, Jumlah Baris, dan Status Publikasi.
- **Hasil yang Diharapkan:**
  - Halaman `/manajemen-data` terbuka dengan responsif.
  - Tabel menampilkan daftar batch data historis.
  - Batch #71 terdaftar dengan status **RILIS** (hijau).
  - Batch #720 terdaftar dengan status **PRIVATE** (kuning/abu-abu).
  - Informasi jumlah record dan metadata batch tampil utuh dan konsisten.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-SA-03: Manajemen Data – Pengalihan Status Rilis (Toggle Release) Terkendali
- **Modul:** `MOD-DATA`
- **Role Penguji:** Super Admin (Role 1)
- **Tujuan Pengujian:** Memverifikasi tombol pengalihan status (*Toggle Release*) berfungsi dengan konfirmasi modal aman tanpa merusak batch produksi utama.
- **Prasyarat:** Login sebagai Super Admin di `/manajemen-data`. Gunakan **Batch #74** (atau batch uji pementasan/staging non-historis yang ditunjuk teknisi). **DILARANG KERAS** melakukan pengujian toggle release pada Batch #71 (baseline RILIS) atau Batch #720 (baseline PRIVATE).
- **Langkah Pengujian:**
  1. Temukan batch uji coba yang telah ditentukan untuk pengujian status (direkomendasikan: **Batch #74**).
  2. Klik tombol **Toggle Release** / Ubah Status.
  3. Amati dialog konfirmasi persetujuan rilis data.
  4. Lanjutkan konfirmasi (atau batalkan jika hanya menguji modal).
  5. Periksa apakah status berubah dan pesan notifikasi sukses muncul.
- **Hasil yang Diharapkan:**
  - Muncul dialog konfirmasi sebelum status rilis diubah (*safe guard*).
  - Perubahan status berhasil disimpan dan indikator status pada tabel terbarui.
  - Batch #71 tetap dalam status RILIS dan Batch #720 tetap dalam status PRIVATE tanpa mengalami perubahan apapun.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-SA-04: Ekspor Data Batch ke Format Excel
- **Modul:** `MOD-DATA`
- **Role Penguji:** Super Admin (Role 1)
- **Tujuan Pengujian:** Memverifikasi fitur unduh data batch transaksional dalam format Excel (.xlsx) berjalan tanpa galat memori atau format rusak.
- **Prasyarat:** Login sebagai Super Admin di `/manajemen-data`.
- **Langkah Pengujian:**
  1. Pada tabel manajemen data, pilih salah satu batch (misal Batch #71).
  2. Klik tombol aksi **Export Excel** / Unduh Data.
  3. Tunggu proses peramban mengunduh berkas.
  4. Buka berkas `.xlsx` hasil unduhan menggunakan aplikasi spreadsheet (Microsoft Excel / LibreOffice).
- **Hasil yang Diharapkan:**
  - Berkas spreadsheet berhasil diunduh dengan penamaan yang sesuai.
  - Berkas dapat dibuka tanpa pesan berkas korup (*not corrupted*).
  - Kolom nilai moneter (FOB/CIF) dan berat bersih (Netto) tersaji rapi dan sesuai dengan angka pada database.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-SA-05: Modul Unggah Data DBF – Pratinjau Parsing Berkas Sintetis
- **Modul:** `MOD-UPLD`
- **Role Penguji:** Super Admin (Role 1)
- **Tujuan Pengujian:** Memverifikasi alur pratinjau (*preview*) berkas DBF perdagangan luar negeri dengan file DBF uji coba sintetis.
- **Prasyarat:** Login sebagai Super Admin. Tersedia file DBF uji sintetis terisolasi yang telah divalidasi tim BPS.
- **Langkah Pengujian:**
  1. Navigasikan ke menu **Upload Data** (`/upload-data`).
  2. Pilih jenis perdagangan (Ekspor / Impor) dan periode tahun/bulan pengujian.
  3. Pilih berkas DBF uji coba melalui tombol *Choose File*.
  4. Klik tombol **Preview / Validasi Data**.
  5. Amati tabel ringkasan pratinjau record dan status validasi master HS/Pelabuhan/Negara.
- **Hasil yang Diharapkan:**
  - Sistem mem-parsing berkas DBF dan menampilkan ringkasan data, jumlah baris, serta total nilai FOB/CIF sementara.
  - Kode HS yang valid terpetakan ke Master Komoditas.
  - Jika belum ada berkas DBF uji coba yang disiapkan, tandai skenario ini dengan `BLOCKED` atau `NOT TESTED` (jangan mengunggah berkas mentah operasional).
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-SA-06: Modul Unggah Data DBF – Penyimpanan Batch Transaksional Terisolasi
- **Modul:** `MOD-UPLD`
- **Role Penguji:** Super Admin (Role 1)
- **Tujuan Pengujian:** Memverifikasi penyimpanan batch dari hasil preview ke dalam tabel transaksional dan mart agregasi bulanan.
- **Prasyarat:** TC-UAT-SA-05 telah berhasil dijalankan pada lingkungan terisolasi dengan file sintetis.
- **Langkah Pengujian:**
  1. Pada halaman preview hasil unggah, periksa kembali angka ringkasan.
  2. Klik tombol **Simpan Data / Proses ke Database**.
  3. Tunggu hingga proses agregasi statistik selesai.
  4. Amati pesan konfirmasi dan pengalihan ke daftar batch.
- **Hasil yang Diharapkan:**
  - Data tersimpan dengan status awal **PRIVATE** (telaah internal).
  - Agregasi mart bulanan (`monthly_trade_summaries`) terbentuk tanpa kegagalan koneksi runtime.
  - Jika tidak dilakukan unggah, tandai `BLOCKED` / `NOT TESTED`.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-SA-07: Modul Tempat Sampah (Trash Data) – Tinjauan Data Terhapus Lunak
- **Modul:** `MOD-TRSH`
- **Role Penguji:** Super Admin (Role 1)
- **Tujuan Pengujian:** Memverifikasi daftar batch data yang telah dihapus lunak (*soft deleted*) terisolasi di halaman Trash dan tidak mempengaruhi dashboard.
- **Prasyarat:** Login sebagai Super Admin di `/trash-data`.
- **Langkah Pengujian:**
  1. Klik menu **Trash Data** pada sidebar (`/trash-data`).
  2. Periksa daftar entri batch pada tabel tempat sampah.
  3. Pastikan informasi ID batch, tanggal penghapusan, dan user yang menghapus tampil jelas.
- **Hasil yang Diharapkan:**
  - Halaman `/trash-data` terbuka normal.
  - Data yang ada di Trash terbukti tidak muncul pada perhitungan dashboard utama.
  - Tampil tombol aksi *Restore* (Pemulihan) dan *Force Delete* (Hapus Permanen).
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-SA-08: Modul Tempat Sampah – Pemulihan Batch Uji (Restore)
- **Modul:** `MOD-TRSH`
- **Role Penguji:** Super Admin (Role 1)
- **Tujuan Pengujian:** Memverifikasi fungsi pemulihan (*restore*) batch uji coba dari Trash kembali ke daftar aktif Manajemen Data.
- **Prasyarat:** Login Super Admin di `/trash-data`. Tersedia entri uji coba terhapus lunak.
- **Langkah Pengujian:**
  1. Pilih baris batch uji coba pada tabel Trash Data.
  2. Klik tombol **Restore** / Pulihkan.
  3. Konfirmasi aksi pada dialog yang muncul.
  4. Buka menu `/manajemen-data` dan cari batch yang baru saja dipulihkan.
- **Hasil yang Diharapkan:**
  - Notifikasi sukses pemulihan batch tampil.
  - Batch uji tersebut berpindah dari Trash kembali ke tabel `/manajemen-data` dalam kondisi utuh.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-SA-09: Modul Audit Trail Log Aktivitas – Verifikasi Riwayat Operasional
- **Modul:** `MOD-AUDT`
- **Role Penguji:** Super Admin (Role 1)
- **Tujuan Pengujian:** Memverifikasi pencatatan audit trail menyeluruh terhadap aktivitas pengguna pada modul `/activity-logs`.
- **Prasyarat:** Login sebagai Super Admin di `/activity-logs`.
- **Langkah Pengujian:**
  1. Klik menu **Log Aktivitas** pada sidebar (`/activity-logs`).
  2. Periksa urutan kronologis log aktivitas.
  3. Periksa rincian setiap baris: Waktu, Nama Pengguna, Tipe Aktivitas, Deskripsi, dan Alamat IP.
  4. Coba lakukan pencarian atau filter berdasarkan nama aksi.
- **Hasil yang Diharapkan:**
  - Seluruh riwayat aksi kritis (login, perubahan rilis, unduh data) tercatat secara transparan.
  - Data audit log tersaji urut waktu (terbaru di atas) dan dapat ditelusuri untuk akuntabilitas.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-SA-10: Ekspor Presentasi PowerPoint (BRS Eksekutif)
- **Modul:** `MOD-DASH`
- **Role Penguji:** Super Admin (Role 1)
- **Tujuan Pengujian:** Memverifikasi pembuatan slide presentasi Berita Resmi Statistik (.pptx) berjalan lancar.
- **Prasyarat:** Login Super Admin. Berada di `/manajemen-data` atau halaman ekspor presentasi.
- **Langkah Pengujian:**
  1. Pada batch data yang telah RILIS (misal Batch #71), klik tombol aksi **Export PowerPoint / PPT**.
  2. Tunggu proses penyiapan berkas unduhan.
  3. Buka berkas `.pptx` hasil unduhan dengan Microsoft PowerPoint.
- **Hasil yang Diharapkan:**
  - Berkas presentasi terunduh dengan baik.
  - Slide memuat judul, ringkasan nilai ekspor/impor, dan visualisasi grafik makro BPS Riau secara profesional.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

## BAGIAN 2: SKENARIO PERAN PEGAWAI BPS (ROLE 2)

### TC-UAT-PG-01: Login Pegawai BPS dan Verifikasi Sidebar Terbatas
- **Modul:** `MOD-AUTH`
- **Role Penguji:** Pegawai BPS (Role 2)
- **Tujuan Pengujian:** Memverifikasi login Pegawai BPS berhasil dan menu administratif disembunyikan secara otomatis pada sidebar antarmuka.
- **Prasyarat:** Berada di halaman login lingkungan pengujian yang disiapkan teknisi. Akun `pegawai_sidatex` aktif.
- **Langkah Pengujian:**
  1. Buka URL lingkungan pengujian (misal: `http://127.0.0.1:8000/login` atau `http://127.0.0.1:8094/login` atau URL server LAN staging BPS).
  2. Masukkan Username: `pegawai_sidatex` dan Password: `password123`.
  3. Klik tombol **Masuk**.
  4. Amati dashboard dan periksa menu sidebar.
- **Hasil yang Diharapkan:**
  - Login berhasil, dialihkan ke `/dashboard`.
  - Tampil lencana identitas peran **Pegawai**.
  - Menu *Upload Data*, *Trash Data*, dan *Log Aktivitas* **TIDAK ADA** pada sidebar.
  - Hanya menu analisis statistik yang tersedia.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-PG-02: Single Dashboard Tab 1 – Overview Makro & Tren Perdagangan
- **Modul:** `MOD-DASH`
- **Role Penguji:** Pegawai BPS (Role 2)
- **Tujuan Pengujian:** Memverifikasi kartu ringkasan ekspor (FOB), impor (CIF), neraca perdagangan, dan grafik tren pada Tab 1 menyajikan angka yang tepat.
- **Prasyarat:** Login sebagai Pegawai BPS. Berada di `/dashboard` (Tab 1 aktif).
- **Langkah Pengujian:**
  1. Buka Tab 1: **Overview Global & Tren**.
  2. Pilih periode Tahun: `2026` (atau tahun data uji yang tersedia) dan Bulan: `September`.
  3. Periksa kartu ringkasan Nilai Ekspor dan Nilai Impor.
  4. Periksa indikator pertumbuhan MoM dan YoY.
  5. Amati visualisasi grafik tren bulanan Chart.js.
- **Hasil yang Diharapkan:**
  - Kartu menampilkan format moneter rapi dalam Juta US$ dengan pemisah ribuan desimal Indonesia yang benar.
  - Grafik tren Chart.js ter-render dengan mulus dan legenda data dapat di-klik (*toggle*).
  - Tampilan stabil tanpa galat konsol peramban.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-PG-03: Single Dashboard Tab 2 – Tabulasi Time Series Komoditas
- **Modul:** `MOD-DASH`
- **Role Penguji:** Pegawai BPS (Role 2)
- **Tujuan Pengujian:** Memverifikasi tabulasi data runtun waktu (Time Series) per komoditas utama dan kode HS 2-digit.
- **Prasyarat:** Login sebagai Pegawai BPS di `/dashboard`.
- **Langkah Pengujian:**
  1. Klik Tab 2: **Tabulasi Time Series Komoditas**.
  2. Pilih sub-tab (misal: Komoditas Utama / Golongan Barang HS 2-digit).
  3. Ubah pilihan tampilan periode: Bulanan, Triwulanan, atau Kumulatif.
  4. Periksa baris-baris komoditas unggulan Riau (Minyak Kelapa Sawit / CPO, Lemak & Minyak Hewani/Nabati HS 15, Bubur Kayu/Pulp HS 47, Kertas/Karton HS 48).
- **Hasil yang Diharapkan:**
  - Matriks data time series tersaji dalam tabel yang bersih dan mudah dibaca.
  - Nilai desimal dan total kumulatif terhitung tepat.
  - Fitur pengurutan kolom (*sorting*) dan paginasi tabel berfungsi lancar.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-PG-04: Single Dashboard Tab 3 – Pemetaan Wilayah Pelabuhan (Leaflet.js)
- **Modul:** `MOD-DASH`
- **Role Penguji:** Pegawai BPS (Role 2)
- **Tujuan Pengujian:** Memverifikasi visualisasi peta interaktif Leaflet.js pelabuhan muat/bongkar di Provinsi Riau dan negara mitra dagang.
- **Prasyarat:** Login sebagai Pegawai BPS di `/dashboard`.
- **Langkah Pengujian:**
  1. Klik Tab 3: **Detail Analisis & Pemetaan Wilayah**.
  2. Amati peta Leaflet.js wilayah Provinsi Riau.
  3. Lakukan *zoom in* dan *zoom out* pada peta.
  4. Klik salah satu titik penanda pelabuhan (misal: Pelabuhan Dumai, Sungai Pakning, Bengkalis, atau Pekanbaru).
  5. Periksa tabel 10 Negara Tujuan Ekspor dan Negara Asal Impor Terbesar.
- **Hasil yang Diharapkan:**
  - Peta digital merespons navigasi pengguna tanpa patah-patah (*smooth pan/zoom*).
  - Pop-up penanda pelabuhan menampilkan nama pelabuhan, volume ekspor/impor, dan nilai moneter.
  - Tabel negara mitra menyajikan bendera/nama negara, nilai perdagangan, dan pangsa persentase (*share %*).
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-PG-05: Single Dashboard Tab 4 – Neraca Perdagangan Bulanan & Kumulatif
- **Modul:** `MOD-DASH`
- **Role Penguji:** Pegawai BPS (Role 2)
- **Tujuan Pengujian:** Memverifikasi perhitungan Neraca Perdagangan (Trade Balance = Ekspor FOB - Impor CIF) untuk periode bulanan dan kumulatif.
- **Prasyarat:** Login Pegawai BPS di `/dashboard`.
- **Langkah Pengujian:**
  1. Klik Tab 4: **Neraca Perdagangan / Trade Balance**.
  2. Pilih periode tahun dan bulan aktif.
  3. Periksa kartu Neraca Perdagangan Bulanan: Nilai Ekspor, Nilai Impor, dan Selisih Neraca.
  4. Periksa kartu Neraca Perdagangan Kumulatif tahun berjalan.
  5. Amati lencana status (*Badge Surplus / Defisit*).
- **Hasil yang Diharapkan:**
  - Angka selisih neraca perdagangan tepat sama dengan nilai Ekspor dikurangi Impor.
  - Jika selisih positif, lencana menampilkan **Surplus** (hijau); jika negatif, menampilkan **Defisit** (merah).
  - Tidak ada nilai *NaN* atau teks rancu.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-PG-06: Verifikasi Notifikasi Asimetri Kumulatif Trade Balance (F-03)
- **Modul:** `MOD-METD`
- **Role Penguji:** Pegawai BPS (Role 2)
- **Tujuan Pengujian:** Memverifikasi banner peringatan (*Amber Alert*) muncul secara transparan saat rentang bulan kumulatif ekspor dan impor tidak setara.
- **Prasyarat:** Login Pegawai BPS di `/dashboard` -> Tab 4. Pilih periode data uji dengan rentang ketersediaan data ekspor dan impor yang berbeda.
- **Langkah Pengujian:**
  1. Pada filter periode Tab 4, pilih tahun/bulan yang memiliki kondisi ketersediaan data ekspor dan impor tidak sama panjang.
  2. Amati area di sekitar kartu Neraca Kumulatif.
  3. Periksa keberadaan kotak pemberitahuan berwarna kuning/amber (`#alertTradeAsymmetry`).
  4. Baca teks peringatan yang ditampilkan.
- **Hasil yang Diharapkan:**
  - Banner peringatan warna kuning/amber tampil dengan jelas.
  - Teks menjelaskan dengan gamblang bahwa periode kumulatif ekspor (misal: Jan–Sep) berbeda dengan periode kumulatif impor (misal: Jan–Agt), sehingga pengguna berhati-hati dalam menafsirkan angka neraca kumulatif.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-PG-07: Verifikasi Tooltip Penjelasan Metodologi Distribusi vs Neraca (F-05)
- **Modul:** `MOD-METD`
- **Role Penguji:** Pegawai BPS (Role 2)
- **Tujuan Pengujian:** Memverifikasi ketersediaan dan kejelasan tooltip metodologi pada tombol pilihan cakupan data.
- **Prasyarat:** Login Pegawai BPS di `/dashboard` atau `/distribusi/dashboard`.
- **Langkah Pengujian:**
  1. Temukan switcher / pilihan metodologi **Distribusi** dan **Neraca**.
  2. Arahkan kursor atau klik ikon informasi `(i)` di dekat tombol tersebut.
  3. Amati pop-up tooltip interaktif yang muncul.
  4. Periksa definisi POD (Pelabuhan Muat Riau), KDPROV (Pelabuhan Bongkar Riau), PROVORIG (Asal Barang Riau), dan KPPBC (Bea Cukai Pengawasan Riau).
  5. Klik di luar pop-up untuk menutupnya.
- **Hasil yang Diharapkan:**
  - Tooltip muncul secara elegan (*smooth transition*).
  - Teks definisi mudah dipahami dan sesuai kaidah metodologi BPS.
  - Tooltip dapat ditutup kembali tanpa mengganggu elemen antarmuka lainnya.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-PG-08: Verifikasi Penanganan Data Kosong dan Pertumbuhan Statistik
- **Modul:** `MOD-DASH`
- **Role Penguji:** Pegawai BPS (Role 2)
- **Tujuan Pengujian:** Memverifikasi sistem tidak menampilkan angka pertumbuhan palsu `-100.00%` ketika bulan berjalan tidak memiliki transaksi data.
- **Prasyarat:** Login Pegawai BPS di `/dashboard`.
- **Langkah Pengujian:**
  1. Pilih filter tahun dan bulan di mana transaksi belum tersedia (data kosong).
  2. Amati kartu ringkasan Nilai Impor dan pertumbuhan MoM/YoY.
  3. Amati tabel Top Komoditas.
- **Hasil yang Diharapkan:**
  - Indikator pertumbuhan menampilkan tanda strip `—` atau `N/A`.
  - **TIDAK MUNCUL ANGKA `-100.00%`** palsu.
  - Tabel top komoditas tidak menampilkan item semu `'Lainnya'`.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-PG-09: Isolasi Data Tertutup – Verifikasi Ketiadaan Data PRIVATE (Batch #720)
- **Modul:** `MOD-RBAC`
- **Role Penguji:** Pegawai BPS (Role 2)
- **Tujuan Pengujian:** Memastikan Pegawai BPS sama sekali tidak dapat melihat data batch bertipe PRIVATE (Batch #720) pada kalkulasi dashboard manapun.
- **Prasyarat:** Login Pegawai BPS. Database memuat Batch #71 (RILIS) dan Batch #720 (PRIVATE).
- **Langkah Pengujian:**
  1. Periksa angka total ekspor dan impor pada periode yang mencakup Batch #720.
  2. Bandingkan angka yang terlihat oleh Pegawai dengan angka yang terlihat oleh Super Admin.
  3. Periksa apakah data Batch #720 terhitung ke dalam ringkasan Pegawai.
- **Hasil yang Diharapkan:**
  - Total nilai yang ditampilkan ke Pegawai HANYA berasal dari data RILIS (Batch #71).
  - Transaksi Batch #720 (PRIVATE) terisolasi 100% dan tidak bocor ke Pegawai.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-PG-10: Uji Pelanggaran Hak Akses – Blokir Halaman Upload Data (HTTP 403)
- **Modul:** `MOD-RBAC`
- **Role Penguji:** Pegawai BPS (Role 2)
- **Tujuan Pengujian:** Memverifikasi Pegawai BPS dilarang membuka halaman unggah data melalui pengetikan URL langsung pada peramban.
- **Prasyarat:** Login sebagai Pegawai BPS.
- **Langkah Pengujian:**
  1. Pada bilah alamat (*address bar*) peramban web, ketikkan URL upload data: `[URL-UAT]/upload-data` (misal: `http://127.0.0.1:8000/upload-data` atau `http://127.0.0.1:8094/upload-data` atau sesuai port/host aktif).
  2. Tekan tombol `Enter`.
  3. Amati respons yang diberikan oleh sistem.
- **Hasil yang Diharapkan:**
  - Akses DITOLAK secara tegas oleh server.
  - Tampil halaman galat **403 Forbidden** (Hak Akses Ditolak) atau pengguna dialihkan kembali ke `/dashboard` dengan notifikasi peringatan.
  - Formulir unggah data tidak dapat diakses atau dilihat sama sekali.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-PG-11: Uji Pelanggaran Hak Akses – Blokir Halaman Trash Data (HTTP 403)
- **Modul:** `MOD-RBAC`
- **Role Penguji:** Pegawai BPS (Role 2)
- **Tujuan Pengujian:** Memverifikasi Pegawai BPS dilarang membuka halaman modul Trash Data melalui URL langsung.
- **Prasyarat:** Login sebagai Pegawai BPS.
- **Langkah Pengujian:**
  1. Pada bilah alamat peramban web, ketikkan URL trash data: `[URL-UAT]/trash-data`.
  2. Tekan tombol `Enter`.
  3. Amati respons sistem.
- **Hasil yang Diharapkan:**
  - Akses DITOLAK dengan status **403 Forbidden**.
  - Daftar berkas atau batch yang terhapus lunak tidak dapat dilihat oleh Pegawai.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-PG-12: Uji Pelanggaran Hak Akses – Blokir Halaman Log Aktivitas (HTTP 403)
- **Modul:** `MOD-RBAC`
- **Role Penguji:** Pegawai BPS (Role 2)
- **Tujuan Pengujian:** Memverifikasi Pegawai BPS dilarang mengakses log audit trail sistem (`/activity-logs`).
- **Prasyarat:** Login sebagai Pegawai BPS.
- **Langkah Pengujian:**
  1. Pada bilah alamat peramban web, ketikkan URL activity logs: `[URL-UAT]/activity-logs`.
  2. Tekan tombol `Enter`.
  3. Amati respons sistem.
- **Hasil yang Diharapkan:**
  - Akses DITOLAK dengan respons **403 Forbidden** oleh Controller Guard / Middleware.
  - Rekam jejak audit trail terlindungi dari akses non-administrator.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

## BAGIAN 3: SKENARIO PERAN VIEWER EKSEKUTIF (ROLE 3)

### TC-UAT-VE-01: Login Viewer Eksekutif dan Tampilan Dashboard Pimpinan
- **Modul:** `MOD-AUTH`
- **Role Penguji:** Viewer Eksekutif (Role 3)
- **Tujuan Pengujian:** Memverifikasi login pimpinan/eksekutif BPS berhasil dan dashboard menampilkan ringkasan eksekutif secara elegan.
- **Prasyarat:** Berada di halaman login lingkungan pengujian. Akun `viewer_sidatex` aktif.
- **Langkah Pengujian:**
  1. Buka URL lingkungan pengujian (misal: `http://127.0.0.1:8000/login` atau `http://127.0.0.1:8094/login` atau URL server LAN staging BPS).
  2. Masukkan Username: `viewer_sidatex` dan Password: `password`.
  3. Klik tombol **Masuk**.
  4. Periksa dashboard dan lencana pengguna pada antarmuka.
- **Hasil yang Diharapkan:**
  - Pengguna dialihkan ke `/dashboard`.
  - Tampil identitas peran **Viewer Eksekutif**.
  - Sidebar menyajikan navigasi dashboard dan analisis makro yang bersih tanpa menu operasional upload/trash.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-VE-02: Telaah Angka Internal – Akses Data RILIS dan Data PRIVATE (Batch #720)
- **Modul:** `MOD-DASH`
- **Role Penguji:** Viewer Eksekutif (Role 3)
- **Tujuan Pengujian:** Memverifikasi Viewer Eksekutif diizinkan meninjau data berstatus PRIVATE (Batch #720) bersama data RILIS untuk evaluasi angka sebelum rilis resmi BRS.
- **Prasyarat:** Login sebagai Viewer Eksekutif di `/dashboard`.
- **Langkah Pengujian:**
  1. Pada filter periode dashboard, pilih periode yang mencakup Batch #720 (status PRIVATE).
  2. Amati kartu ringkasan ekspor, impor, dan grafik tren makro.
  3. Periksa apakah sistem menyajikan data telaah internal tersebut untuk dievaluasi oleh pimpinan.
- **Hasil yang Diharapkan:**
  - Sistem mengizinkan Viewer Eksekutif melihat agregasi data PRIVATE.
  - Angka tersaji lengkap sehingga pimpinan BPS dapat menelaah validitas data sebelum Super Admin melakukan rilis resmi.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-VE-03: Integritas Read-Only – Ketiadaan Tombol Mutasi dan Hapus
- **Modul:** `MOD-RBAC`
- **Role Penguji:** Viewer Eksekutif (Role 3)
- **Tujuan Pengujian:** Memverifikasi seluruh antarmuka Viewer Eksekutif bebas dari tombol mutasi, hapus, unggah, maupun ubah status rilis.
- **Prasyarat:** Login sebagai Viewer Eksekutif.
- **Langkah Pengujian:**
  1. Telusuri seluruh tab pada dashboard dan halaman analisis.
  2. Periksa apakah terdapat tombol Tambah Data, Edit, Hapus, atau Toggle Release.
- **Hasil yang Diharapkan:**
  - Seluruh halaman bersifat murni baca (*read-only*).
  - Tidak ada tombol tindakan manipulasi data yang tampil pada layar pimpinan.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-VE-04: Blokir Tindakan Mutasi Data Server-Side (HTTP 403)
- **Modul:** `MOD-RBAC`
- **Role Penguji:** Viewer Eksekutif (Role 3)
- **Tujuan Pengujian:** Memverifikasi sistem menolak percobaan manipulasi data atau akses halaman upload oleh Viewer Eksekutif di sisi server.
- **Prasyarat:** Login sebagai Viewer Eksekutif.
- **Langkah Pengujian:**
  1. Pada bilah alamat peramban, coba ketikkan URL administratif: `[URL-UAT]/upload-data` (misal: `http://127.0.0.1:8000/upload-data` atau `http://127.0.0.1:8094/upload-data`).
  2. Tekan `Enter`.
- **Hasil yang Diharapkan:**
  - Sistem menolak akses dengan halaman **403 Forbidden** atau pengalihan aman.
  - Hak ases mutasi data tetap terkunci rapat.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

## BAGIAN 4: SKENARIO KHUSUS METODOLOGI & REGRESI FITUR

### TC-UAT-SP-01: Perbandingan Metodologi Distribusi vs Neraca pada Periode yang Sama
- **Modul:** `MOD-METD`
- **Role Penguji:** Pegawai BPS / Super Admin
- **Tujuan Pengujian:** Memverifikasi bahwa nilai Ekspor Distribusi berbeda dengan Ekspor Neraca, dan sistem tidak pernah menggabungkan keduanya.
- **Prasyarat:** Login di `/dashboard`.
- **Langkah Pengujian:**
  1. Pilih Periode: Tahun 2026 / 2023.
  2. Klik tombol metodologi **Distribusi**: Catat total nilai Ekspor (POD Riau).
  3. Klik tombol metodologi **Neraca**: Catat total nilai Ekspor (PROVORIG Riau).
  4. Periksa apakah angka keduanya berbeda sesuai basis pencatatan masing-masing.
- **Hasil yang Diharapkan:**
  - Sistem menampilkan data yang terpisah secara tegas.
  - Tidak ada baris atau kartu yang menjumlahkan angka Distribusi dan Neraca secara serampangan.
  - Angka konsisten dengan metodologi resmi BPS.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-SP-02: Uji Respon Desain Tampilan (Responsive Layout & Zoom)
- **Modul:** `MOD-DASH`
- **Role Penguji:** Staf BPS (Semua Peran)
- **Tujuan Pengujian:** Memverifikasi kenyamanan tata letak dashboard saat peramban diubah ukurannya atau di-zoom.
- **Prasyarat:** Berada di `/dashboard`.
- **Langkah Pengujian:**
  1. Buka dashboard pada layar monitor standar ($1366 \times 768$ atau $1920 \times 1080$).
  2. Lakukan perbesaran peramban (*browser zoom*) ke 110%, 125%, dan 90% (`Ctrl +` / `Ctrl -`).
  3. Amati apakah tata letak kartu metrik, grafik, dan tabel tetap rapi tanpa teks tumpang tindih.
- **Hasil yang Diharapkan:**
  - Tata letak responsif menyesuaikan lebar kontainer secara elegan.
  - Seluruh teks, lencana, dan tombol tetap dapat dibaca dan di-klik dengan nyaman.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-SP-03: Uji Penghentian Sesi (Logout) dan Perlindungan Halaman Terkunci
- **Modul:** `MOD-AUTH`
- **Role Penguji:** Staf BPS (Semua Peran)
- **Tujuan Pengujian:** Memverifikasi fungsi Logout membersihkan sesi peramban dan tombol kembali (*Back button*) tidak membuka data terproteksi.
- **Prasyarat:** Login ke dalam sistem.
- **Langkah Pengujian:**
  1. Klik foto profil / menu akun di pojok kanan atas.
  2. Klik tombol **Keluar / Logout**.
  3. Pastikan sistem mengarahkan kembali ke halaman `/login`.
  4. Klik tombol panah **Kembali (*Back*)** pada peramban web.
- **Hasil yang Diharapkan:**
  - Sesi otentikasi dihapus sepenuhnya.
  - Menekan tombol Back tidak membuka data dashboard melainkan tetap tertahan di halaman login atau dialihkan kembali ke login.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

### TC-UAT-SP-04: Uji Pemulihan Sesi setelah Batas Waktu Habis (Session Timeout)
- **Modul:** `MOD-AUTH`
- **Role Penguji:** Staf BPS (Semua Peran)
- **Tujuan Pengujian:** Memverifikasi keamanan aplikasi saat sesi pengguna dibiarkan tidak aktif dalam jangka waktu tertentu.
- **Prasyarat:** Login ke dalam sistem.
- **Langkah Pengujian:**
  1. Biarkan peramban tidak aktif hingga masa sesi habis (atau hapus cookie sesi secara manual pada tab Application peramban).
  2. Coba klik salah satu menu atau tombol pada layar.
- **Hasil yang Diharapkan:**
  - Sistem mengarahkan pengguna kembali ke halaman `/login` dengan notifikasi sesi telah kedaluwarsa secara aman.
  - Data statistik terlindungi dari akses tanpa pengawasan.
- **Hasil Aktual:** 
- **Status (PASS/FAIL/BLOCKED/NOT TESTED):** 
- **Catatan Penguji:** 
- **Bukti Screenshot:** 
- **Nama & Tanggal Penguji:** 

---

## REKAPITULASI HASIL PENGUJIAN MANUAL BPS
*(Bagian ini diisi oleh Koordinator UAT BPS setelah seluruh skenario diuji)*

| Status Pengujian | Jumlah Skenario | Persentase (%) |
|---|:---:|:---:|
| **PASS** (Lulus) | | |
| **FAIL** (Gagal / Ditemukan Bug) | | |
| **BLOCKED** (Terkendala Prasyarat) | | |
| **NOT TESTED** (Belum Diuji) | | |
| **TOTAL SKENARIO** | **30** | **100%** |

**Tanggal Penyelesaian Pengujian Lapangan:** `____________________`  
**Koordinator Pengujian BPS Provinsi Riau:** `____________________` (Tanda Tangan)
