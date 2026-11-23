# SIDATEX — KLASIFIKASI DATA BASIS DATA UTAMA (PHASE 2G.10.5)
## Data Categorization & Preservation Report

**Aplikasi:** Sistem Informasi Data Ekspor-Impor (SIDATEX) — BPS Provinsi Riau  
**Lokasi Direktori:** `D:\laragon\www\sidatex`  
**Database Sasaran:** `sidatex` (Database Operasional Utama) & `sidatex_testing` (Database Pengujian Teknis)  
**Tanggal Klasifikasi:** 19 November 2026  
**Metodologi:** Verifikasi Bukti Sumber, Struktur Berkas, Relasi Foreign Key, dan Histori Audit  

---

## 1. KERANGKA KERJA EMPAT KATEGORI DATA

Sesuai dengan ketentuan baku BPS Provinsi Riau dan pedoman Phase 2G.10.5, seluruh data di dalam database diklasifikasikan secara ketat menjadi 4 (empat) kategori berdasarkan bukti asal-usul (*provenance*), bukan sekadar asumsi nama berkas atau status rilis:

```
+---------------------------------------------------------------------------------------------------+
| KATEGORI A: DATA ASLI BPS                                                                         |
| Data otentik dari BPS Riau (Master referensi, batch DBF resmi, transaksi riil, mart historis).    |
| Status Tindakan: WAJIB DILINDUNGI & DIPERTAHANKAN 100%. DILARANG DIHAPUS.                         |
+---------------------------------------------------------------------------------------------------+
| KATEGORI B: DATA DUMMY                                                                            |
| Data sintetis acak atau berkas contoh percobaan non-riil yang tidak merefleksikan perdagangan.    |
| Status Tindakan: DIUSULKAN DIBERSIHKAN (Hanya setelah persetujuan tertulis BPS).                  |
+---------------------------------------------------------------------------------------------------+
| KATEGORI C: DATA PENGUJIAN TEKNIS                                                                 |
| Data penunjang pengujian regresi otomatis (PHPUnit), pengujian RBAC, dan audit konsistensi.       |
| Status Tindakan: DIPERTAHANKAN PADA sidatex_testing; JANGAN DIPINDAHKAN KE sidatex.               |
+---------------------------------------------------------------------------------------------------+
| KATEGORI D: DATA YANG BELUM TERSEDIA                                                              |
| Data BPS yang belum diunggah ke sistem dan akan diunggah melalui alur resmi UI SIDATEX saat UAT.  |
| Status Tindakan: DISIAPKAN UNTUK DIUNGGAH OLEH PENGUJI BPS PADA SESI UAT.                         |
+---------------------------------------------------------------------------------------------------+
```

---

## 2. TABEL KLASIFIKASI DATA DETAIL PER TABEL

Audit menyeluruh terhadap setiap entitas tabel pada database `sidatex` menghasilkan rincian inventarisasi berikut:

| Nama Tabel | Jumlah Baris | Periode Data | Status Visibilitas | Kategori Klasifikasi | Bukti & Justifikasi Klasifikasi |
|:---|:---:|:---:|:---:|:---:|:---|
| `master_hs` | 11.653 | 2022 | Publik | **KATEGORI A (Asli BPS)** | Mengacu pada Buku Tarif Kepabeanan Indonesia (BTKI) 2022 resmi BPS. |
| `master_hs_mappings` | 11.555 | 2017–2022 | Publik | **KATEGORI A (Asli BPS)** | Tabel korespondensi resmi HS 2017 ke HS 2022 untuk integrasi runtun waktu. |
| `master_negara` | 259 | Baku | Publik | **KATEGORI A (Asli BPS)** | Master kode negara ISO/BPS untuk negara asal dan tujuan perdagangan. |
| `master_provinsi` | 34 | Baku | Publik | **KATEGORI A (Asli BPS)** | Master wilayah 34 provinsi seluruh Indonesia. |
| `master_kabkota` | 12 | Baku | Publik | **KATEGORI A (Asli BPS)** | Master 12 kabupaten/kota administratif di Provinsi Riau. |
| `master_pelabuhan` | 40 | Baku | Publik | **KATEGORI A (Asli BPS)** | Master pelabuhan muat dan bongkar di wilayah pengawasan Riau. |
| `master_wilayah_bps` | 33 | Baku | Publik | **KATEGORI A (Asli BPS)** | Master kode referensi wilayah statistik BPS. |
| `users` | 3 | — | Aktif | **KATEGORI A (Asli BPS)** | 3 akun resmi sistem: Super Admin, Pegawai, dan Viewer Eksekutif. |
| `roles` | 3 | — | Aktif | **KATEGORI A (Asli BPS)** | Definisi 3 peran pengguna (Role 1, Role 2, Role 3). |
| `data_batches` (Batch #70) | 1 | 08/2026 | `RILIS` | **KATEGORI A (Asli BPS)** | Berkas asli: `14_impor_atap_kdprov_0826.DBF` (Impor Agustus 2026). |
| `data_batches` (Batch #71) | 1 | 09/2026 | `RILIS` | **KATEGORI A (Asli BPS)** | Berkas asli: `14_Atap_ekspor_2609_POD_based.DBF` (Ekspor September 2026). |
| `data_batches` (Batch #74) | 1 | 09/2026 | `RILIS` | **KATEGORI A (Asli BPS)** | Berkas asli: `14_impor_atap_kdprov_0926.DBF` (Impor September 2026). |
| `distribusi_transactions` | 2.557 | 08–09/2026 | `RILIS` | **KATEGORI A (Asli BPS)** | Transaksi riil hasil parsing DBF asli (Batch #70: 785, #71: 841, #74: 931). |
| `transactions` (Legacy) | 2.557 | 08–09/2026 | `RILIS` | **KATEGORI A (Asli BPS)** | Salinan arsip transaksi Phase 1 sebelum pemisahan tabel Two-Tier. |
| `neraca_transactions` | 0 | — | — | **KATEGORI D (Belum Tersedia)** | Data transaksi Neraca belum diunggah ke `sidatex`; menunggu berkas DBF BPS. |
| `monthly_trade_summaries` | 34.847 | 2023–2026 | Agregasi | **KATEGORI A (Asli BPS)** | Agregasi historis runtun waktu 2023–2026 resmi BPS untuk grafik tren. |
| `monthly_origin_trade_summaries`| 189 | 2023–2026 | Agregasi | **KATEGORI A (Asli BPS)** | Agregasi historis asal barang warisan Phase 1. |
| `brs_indicator_summaries` | 0 (tabel belum ada)| — | — | **KATEGORI D (Belum Tersedia)** | Indikator makro siap saji BRS akan dibangun dari transaksi saat migrasi skema. |
| `comments` | 0 | — | — | **KATEGORI D (Belum Tersedia)** | Belum ada catatan verifikasi; siap digunakan Viewer Eksekutif saat UAT. |
| `activity_logs` | 96 | 11/2026 | Audit | **KATEGORI A (Asli BPS)** | Jejak rekam operasional riil sejak implementasi awal Phase 1 & 2. |
| `trash` | 0 | — | — | Bersih | Tidak ada data yang sedang berada di tempat sampah. |

---

## 3. ANALISIS DATA DUMMY (KATEGORI B)

### Hasil Pemeriksaan di Database Utama `sidatex`:
Berdasarkan verifikasi terhadap rentang nilai transaksi, nama berkas DBF, kode komoditas HS, dan relasi master:
- **TIDAK DITEMUKAN DATA DUMMY PADA DATABASE `sidatex`!**
- Seluruh 3 batch yang ada di `sidatex` (Batch #70, #71, dan #74) memiliki penamaan resmi instansi (`14_..._atap_...DBF`), struktur field sesuai modul impor/ekspor BPS, nilai transaksi riil komoditas Riau (seperti minyak kelapa sawit CPO, bubur kayu/pulp, dan produk karet), serta dicatat dalam `activity_logs` resmi.
- Tidak ditemukan berkas bertanda dummy seperti `dummy.dbf`, `test_sample.xlsx`, transaksi bernilai \$0,00 rekaan, atau baris percobaan pengembang di database `sidatex`.

### Catatan Terkait Batch Uji di Database Pengujian `sidatex_testing`:
Pada database `sidatex_testing`, terdapat batch-batch tambahan yang digunakan untuk kebutuhan pengujian teknis (Kategori C):
- Batch #85 & #86 (Data Distribusi Juli 2026, 1.803 baris)
- Batch #81, #82, & #87 (Data Neraca Juli–September 2026, 2.896 baris)
- Batch #720 (Data Staging Ekspor Ags 2026, 809 baris transaksi, diunggah untuk menguji isolasi PRIVATE)

> **KEBIJAKAN PEMISAHAN:**  
> Data pengujian teknis pada `sidatex_testing` (Kategori C) **DILARANG DISALIN SECARA OTOMATIS** ke database utama `sidatex`. Database `sidatex` harus tetap menjaga kemurnian data operasionalnya.

---

## 4. DAFTAR DATA YANG BELUM TERSEDIA (KATEGORI D)

Untuk keperluan pemaparan dan pengujian UAT operasional lengkap bersama BPS Provinsi Riau, berikut adalah daftar data yang belum tersedia pada database `sidatex` dan perlu disiapkan:

1. **Berkas DBF Neraca Ekspor (PROVORIG-based):**
   - Belum pernah diunggah ke `sidatex`.
   - Diperlukan untuk mengisi `neraca_transactions` dan mengaktifkan visualisasi Single Dashboard Tab 4 (Trade Balance Metodologi Neraca).
2. **Berkas DBF Neraca Impor (KPPBC-based):**
   - Belum pernah diunggah ke `sidatex`.
   - Diperlukan untuk perbandingan ekspor vs impor pada cakupan pengawasan pabean Riau.
3. **Tabel Ringkasan Indikator BRS (`brs_indicator_summaries`):**
   - Belum terbentuk di `sidatex` karena migrasi `2026_11_16_000001` belum dieksekusi.
   - Akan terisi secara otomatis setelah skema dimigrasikan dan artisan command `trade:backfill-aggregates` dijalankan.
4. **Catatan Verifikasi Komentar Pimpinan (`comments`):**
   - Saat ini tabel kosong (0 baris), siap diisi oleh Viewer Eksekutif selama pengujian skenario evaluasi data.

---

## 5. KETENTUAN PERLINDUNGAN DATA MUTLAK

Berdasarkan kesimpulan klasifikasi ini, ditetapkan aturan proteksi yang mengikat:

1. **Dilarang Menghapus Data Kategori A:**  
   Master tables (7 tabel), data mart bulanan (34.847 baris), batch data #70, #71, dan #74, transaksi distribusi (2.557 baris), serta 96 log aktivitas **DILARANG DIHAPUS, DI-TRUNCATE, ATAU DIUBAH SECARA AD-HOC**.
2. **Perlindungan Invariant Database Testing:**  
   Batch #71, #74, dan #720 pada database `sidatex_testing` tetap menjadi acuan pengujian teknis dan regresi otomatis. Identitas batch pada `sidatex` dan `sidatex_testing` tidak boleh disamakan atau ditukar.
3. **Pengunggahan Data Kategori D Dilakukan Melalui UI:**  
   Penambahan data Neraca yang belum tersedia wajib dilakukan oleh staf BPS melalui antarmuka modul Upload SIDATEX (`/neraca/upload` atau `/upload-data`), bukan melalui injeksi SQL langsung ke basis data produksi.
