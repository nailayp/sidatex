# FORMULIR EVALUASI & PENERIMAAN PENGGUNA (USER ACCEPTANCE TESTING)
## SISTEM INFORMASI DATA EKSPOR-IMPOR (SIDATEX) — BPS PROVINSI RIAU
**Dokumen UAT:** Tahap Penerimaan Bisnis & Pengujian Lapangan Pengguna  
**Tanggal Pelaksanaan:** [ ............................................ ]  
**Lokasi Pengujian:** Kantor BPS Provinsi Riau / Daring  

---

## 1. Identitas Penguji (Tester Profile)

| Data Penguji | Rincian Informasi |
| :--- | :--- |
| **Nama Lengkap** | [ ........................................................................................................ ] |
| **NIP / Nomor Pegawai** | [ ........................................................................................................ ] |
| **Jabatan / Fungsi** | [ ........................................................................................................ ] |
| **Unit Kerja** | ( ) Fungsi Statistik Distribusi<br>( ) Fungsi Neraca Wilayah & Analisis Statistik<br>( ) Fungsi Integrasi Pengolahan & Diseminasi Statistik (IPDS)<br>( ) Pimpinan / Eksekutif<br>( ) Lainnya: [ ............................................................ ] |
| **Peran yang Diuji** | ( ) Super Admin (Role 1)<br>( ) Pegawai BPS (Role 2)<br>( ) Viewer Eksekutif (Role 3) |

---

## 2. Petunjuk Penilaian

Berikan tanda silang (**X**) atau centang (**✓**) pada kolom penilaian dengan skala:
- **SS (Sangat Setuju / 5):** Fitur berjalan sempurna, intuitif, dan sesuai metodologi BPS.
- **S (Setuju / 4):** Fitur berjalan baik dan memenuhi kebutuhan operasional.
- **CS (Cukup Setuju / 3):** Fitur berfungsi namun memerlukan sedikit penyesuaian minor.
- **TS (Tidak Setuju / 2):** Fitur memiliki kekurangan fungsional yang mengganggu tugas.
- **STS (Sangat Tidak Setuju / 1):** Fitur gagal berfungsi atau bertentangan dengan aturan BPS.

---

## 3. Lembar Penilaian Fungsional Modul

### A. Modul 1: Single Dashboard Terpadu (`/dashboard`)
| No | Kriteria Pengujian | SS | S | CS | TS | STS | Catatan / Masukan |
| :-: | :--- | :-: | :-: | :-: | :-: | :-: | :--- |
| 1 | Navigasi 4 Tab (Overview, Komoditas, Wilayah, Neraca Perdagangan) responsif dan mudah digunakan. | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 2 | Switcher Sumber Data Metodologi (Distribusi vs Neraca) memisahkan angka secara jelas tanpa mencampur adukkan nilai. | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 3 | Tooltip info metodologi (`ℹ️`) memberikan definisi yang jelas mengenai konsep Pelabuhan Muat (POD/KDPROV) vs Provinsi Asal (PROVORIG/KPPBC). | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 4 | Data berstatus `PRIVATE` tidak tampak pada akun Pegawai (Role 2) dan hanya dapat dilihat oleh Super Admin / Eksekutif. | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 5 | Tampilan Trade Balance menampilkan banner peringatan amber saat rentang bulan ekspor dan impor berbeda (asimetri periode). | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 6 | Nilai yang belum memiliki data ditampilkan sebagai tanda strip (`—`) dan tidak membingungkan pembaca dengan angka `US$ 0,00` atau `NaN`. | [ ] | [ ] | [ ] | [ ] | [ ] | |

---

### B. Modul 2: Upload Data & Pra-Pemrosesan (`/upload-data` & `/neraca/upload`)
| No | Kriteria Pengujian | SS | S | CS | TS | STS | Catatan / Masukan |
| :-: | :--- | :-: | :-: | :-: | :-: | :-: | :--- |
| 1 | Pembatasan akses berjalan benar: hanya Super Admin yang dapat membuka formulir unggah DBF. | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 2 | Validasi file DBF Distribusi dan Neraca mendeteksi kesesuaian kolom wajib dan struktur data mentah kepabeanan. | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 3 | Data yang baru diunggah tersimpan secara default dengan status `PRIVATE` sebelum disetujui rilis ke publik. | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 4 | Pesan sukses/gagal proses unggah memberikan rincian baris data dan ukuran file secara informatif. | [ ] | [ ] | [ ] | [ ] | [ ] | |

---

### C. Modul 3: Manajemen Data & Batch Lifecycle (`/manajemen-data`)
| No | Kriteria Pengujian | SS | S | CS | TS | STS | Catatan / Masukan |
| :-: | :--- | :-: | :-: | :-: | :-: | :-: | :--- |
| 1 | Daftar batch dokumen memuat informasi periode tahun-bulan, jenis transaksi, dan badge status (`RILIS` / `PRIVATE`). | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 2 | Pegawai (Role 2) hanya melihat batch berstatus `RILIS` pada tabel manajemen data. | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 3 | Fitur unduh Excel (`.xlsx`) menghasilkan data transaksi yang lengkap, rapi, dan sesuai dengan dokumen aslinya. | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 4 | Hak publikasi status (`RILIS`/`PRIVATE`) terlindungi dan hanya dapat diubah oleh Super Admin. | [ ] | [ ] | [ ] | [ ] | [ ] | |

---

### D. Modul 4: Tempat Sampah & Pemulihan Data (`/trash-data`)
| No | Kriteria Pengujian | SS | S | CS | TS | STS | Catatan / Masukan |
| :-: | :--- | :-: | :-: | :-: | :-: | :-: | :--- |
| 1 | Modul Trash Data hanya dapat diakses oleh Super Admin; peran Pegawai dan Viewer ditolak (403 Forbidden). | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 2 | Dokumen yang dihapus lunak (*soft deleted*) langsung keluar dari kalkulasi Single Dashboard. | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 3 | Fitur pemulihan (*restore*) mengembalikan dokumen ke daftar aktif beserta agregasi statistiknya secara utuh. | [ ] | [ ] | [ ] | [ ] | [ ] | |

---

### E. Modul 5: Log Aktivitas & Audit Trail (`/activity-logs`)
| No | Kriteria Pengujian | SS | S | CS | TS | STS | Catatan / Masukan |
| :-: | :--- | :-: | :-: | :-: | :-: | :-: | :--- |
| 1 | Super Admin dapat memantau seluruh aktivitas pengguna sistem secara terperinci (waktu, modul, deskripsi aksi, IP). | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 2 | Pegawai hanya dapat melihat rekam jejak aktivitas yang dilakukannya sendiri. | [ ] | [ ] | [ ] | [ ] | [ ] | |
| 3 | Viewer Eksekutif dialihkan secara aman dari modul log audit sistem. | [ ] | [ ] | [ ] | [ ] | [ ] | |

---

## 4. Evaluasi Metodologis Statistik BPS

| No | Parameter Metodologi BPS | Sesuai | Belum Sesuai | Catatan Khusus Validator Statistik |
| :-: | :--- | :---: | :---: | :--- |
| 1 | **Rumus Pertumbuhan (MoM, YoY, QoQ, C-to-C):** Menggunakan formula resmi BPS $(\frac{M - M_{prev}}{M_{prev}}) \times 100$. | [ ] | [ ] | |
| 2 | **Penanganan Pembagi Nol:** Menampilkan status *unavailable* / `—` saat periode pembanding kosong atau bernilai nol. | [ ] | [ ] | |
| 3 | **Pemisahan Cakupan:** Angka ekspor/impor Distribusi dan Neraca tidak dijumlahkan menjadi satu total semu. | [ ] | [ ] | |
| 4 | **Klasifikasi Komoditas (Master HS):** Pemetaan kode HS 8-digit, 2-digit, serta sektor Migas/Non-Migas. | [ ] | [ ] | |
| 5 | **Trade Balance (Neraca Perdagangan):** Dihitung sebagai $\text{Ekspor (FOB)} - \text{Impor (CIF)}$ pada metodologi dan periode yang sama. | [ ] | [ ] | |

---

## 5. Catatan, Temuan Lapangan, dan Saran Perbaikan

Silakan tuliskan masukan tambahan, kendala teknis yang ditemui, atau usulan pengembangan antarmuka:

```text
[ Tuliskan catatan Anda di sini ]
........................................................................................................................
........................................................................................................................
........................................................................................................................
........................................................................................................................
........................................................................................................................
```

---

## 6. Pernyataan & Rekomendasi Penerimaan Pengguna

Berdasarkan pengujian langsung yang telah dilakukan terhadap aplikasi SIDATEX BPS Provinsi Riau, saya menyatakan:

- [ ] **DITERIMA PENUH (ACCEPTED):** Sistem siap digunakan untuk kegiatan operasional dan penyusunan BRS tanpa perbaikan mayor.
- [ ] **DITERIMA DENGAN CATATAN (ACCEPTED WITH MINOR NOTES):** Sistem dapat digunakan, namun catatan perbaikan minor pada bagian 5 disarankan untuk ditindaklanjuti pada pemeliharaan berkala.
- [ ] **DITOLAK / PERLU PERBAIKAN ULANG (REJECTED):** Sistem belum dapat digunakan karena terdapat kendala mayor/kritis yang harus diperbaiki terlebih dahulu.

---

**Ditandatangani di:** Pekanbaru  
**Pada tanggal:** [ ............................................ ]  

**Penguji / Stakeholder BPS Provinsi Riau,**


*( tanda tangan & nama terang )*  
NIP. [ .................................................... ]
