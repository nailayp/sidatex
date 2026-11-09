# DOKUMENTASI INTEGRASI FRONTEND & PERANCANGAN SISTEM SIDATEX
**Sistem Informasi Data Ekspor-Impor BPS Provinsi Riau**

Dokumen ini disusun untuk Tim Frontend dan Perancang Sistem agar dapat langsung menghubungkan tampilan antarmuka (UI/UX) dengan backend Laravel yang telah aktif.

---

## 1. DAFTAR ENDPOINT & KONTRAK INTEGRASI FRONTEND

### A. Autentikasi
- **POST** `/login`
  - Body: `{ username: string, password: string }`
  - Redirect berhasil: `/dashboard` (atau redirect sesuai role)
  - Error: Pesan flash session `error` / validation errors
- **POST** `/logout`
  - Redirect: `/login`

### B. Dashboard & Metrik
- **GET** `/dashboard`
  - Query Params: `tahun` (opsional), `jenis` (`Ekspor` | `Impor`)
  - Server-side rendered Blade view dengan variabel `$metrics`
- **GET** `/dashboard/metrics`
  - Query Params: `tahun` (opsional), `jenis` (`Ekspor` | `Impor`)
  - Response JSON:
    ```json
    {
      "success": true,
      "data": {
        "has_data": true,
        "tahun": 2024,
        "bulan_terakhir": 5,
        "nama_bulan_terakhir": "Mei",
        "jenis": "Ekspor",
        "total_fob": 1500000000,
        "total_fob_juta_usd": 1500.00,
        "total_netto": 2000000000,
        "total_netto_ribu_ton": 2000.00,
        "mtom": 3.45,
        "qtoq": -1.20,
        "yony": 7.80,
        "ctoc": 5.12,
        "monthly_trend": [
          { "bulan": 1, "nama_bulan": "Jan", "nilai": 300000000, "nilai_juta_usd": 300, "berat": 400000000 }
        ]
      }
    }
    ```

### C. Upload Data & Pratinjau
- **POST** `/upload-data/preview`
  - Request: `multipart/form-data` dengan field `file` (Excel .xlsx/.xls)
  - Response JSON: `{ success: true, preview: [...5 baris pertama...], total_rows: 50000 }`
- **POST** `/upload-data/process`
  - Request: `multipart/form-data` dengan field:
    - `file` (file Excel)
    - `jenis_data` ('Ekspor' | 'Impor')
    - `status` ('DRAFT' | 'PENDING')
  - Response: Redirect ke `/manajemen-data` dengan flash alert `success`

### D. Manajemen Data
- **GET** `/manajemen-data`
  - Menampilkan daftar batch aktif (tidak di-trash)
  - Menampilkan modal trash dengan daftar item yang di-soft-delete
- **POST** `/manajemen-data/{batchId}/toggle-release`
  - Role: **Super Admin Only** (Role ID 1)
  - Response: JSON `{ success: true, new_status: 'RILIS'|'NON-RILIS', message: string }` atau redirect
- **POST** `/manajemen-data/{batchId}/update`
  - Body: `{ nama_file: string, jenis_data: 'Ekspor'|'Impor' }`
  - Catatan: Status data TIDAK berubah saat diedit (tetap status sebelumnya)
- **POST** `/manajemen-data/{batchId}/delete` (Soft Delete ke Trash)
  - Memindahkan batch ke tabel `trash` dengan snapshot JSON
- **POST** `/manajemen-data/{batchId}/restore`
  - Mengembalikan batch dari `trash` ke daftar aktif
- **POST** `/manajemen-data/{batchId}/force-delete`
  - Menghapus permanen batch dan transaksi terkait
- **GET** `/manajemen-data/{batchId}/export`
  - Role: Super Admin & Pegawai (Viewer ditolak)
  - Aturan: Data belum RILIS hanya bisa diunduh oleh Super Admin. Pegawai hanya bisa mengunduh data RILIS.

### E. Filtering Data
- **GET** `/filtering-data`
  - Halaman antarmuka filter multi-kriteria
- **GET** `/filtering-data/query`
  - Query Params:
    - `jenis_transaksi`: 'Ekspor' | 'Impor'
    - `tahun`: integer
    - `bulan`: 1 - 12
    - `negara_id`: integer / 'ALL'
    - `hs2`: 2 digit HS / 'ALL'
    - `pelabuhan_id`: integer / 'ALL'
    - `page`: integer (pagination)
  - Aturan Keamanan Backend: Pegawai dan Viewer otomatis hanya mendapatkan transaksi berstatus RILIS.

### F. Analisis Perdagangan
- **GET** `/analisis-perdagangan`
  - Halaman grafik analisis
- **GET** `/analisis-perdagangan/data`
  - Query Params: `tahun`, `jenis`
  - Response JSON:
    - `top_komoditas`: array 10 komoditas terbesar beserta % share
    - `top_negara`: array 10 negara mitra utama beserta % share
    - `top_pelabuhan`: array 5 pelabuhan terbesar
    - `growth`: { mtom, qtoq, yony, ctoc }

### G. Komentar Batch (Kolaborasi & Review)
- **GET** `/comments/batch/{batchId}`
  - Response: list komentar pada batch tersebut
- **POST** `/comments`
  - Body: `{ batch_id: integer, komentar: string }`
- **DELETE** `/comments/{commentId}`
  - Role: Penulis komentar atau Super Admin

### H. Ekspor PowerPoint BRS
- **GET** `/export/powerpoint/{batchId}`
  - Mengunduh presentasi ringkasan BRS format `.pptx` hasil kalkulasi otomatis PhpPresentation.

---

## 2. ACUAN UNTUK PERANCANG SISTEM (SYSTEM ANALYST)

1. **Use Case Diagram**:
   - Super Admin: Upload Data, Toggle Status Rilis, Edit Batch, Soft Delete, Restore, Force Delete, Ekspor Excel, Ekspor PPT, Beri Komentar.
   - Pegawai: Lihat Dashboard Rilis, Filtering Data Rilis, Ekspor Data Rilis, Beri Komentar.
   - Viewer Berkepentingan: Lihat Dashboard Rilis, Filtering Data Rilis, Beri Komentar (Read-only + feedback).

2. **Activity Diagram Alur Data**:
   `Upload Excel` -> `Streaming Parse XMLReader` -> `Validasi Master ID` -> `Bulk Insert Transaksi (DRAFT)` -> `Review Tim` -> `Persetujuan Super Admin (RILIS)` -> `Kalkulasi Dashboard & Analisis Otomatis`.

3. **Status Transisi Batch**:
   `[DRAFT]` -> `[PENDING]` -> `[RILIS]` (oleh Super Admin)
   `[RILIS]` -> `[NON-RILIS]` (penarikan sementara oleh Super Admin)
   Semua status dapat di-soft-delete ke `[TRASH]` dan dipulihkan `[RESTORE]`.
