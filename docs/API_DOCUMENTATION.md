# DOKUMENTASI API & KONTRAK ENDPOINT
# SISTEM INFORMASI DATA EKSPOR-IMPOR BPS PROVINSI RIAU (SIDATEX)

Dokumen ini merupakan panduan teknis bagi tim **Frontend Developer** dan pengembang sistem untuk mengintegrasikan antarmuka pengguna dengan backend Laravel SIDATEX.

---

## 1. Standar Request & Response

- **Base URL**: `http://localhost/sidatex` (atau domain internal BPS Riau)
- **Format Data**: JSON (`application/json`) dan Multipart Form (`multipart/form-data`) untuk berkas.
- **Header Standar**:
  ```http
  Accept: application/json
  X-Requested-With: XMLHttpRequest
  X-CSRF-TOKEN: <token_csrf_laravel>
  ```
- **Struktur Respons JSON Standar**:
  ```json
  {
      "success": true,
      "message": "Pesan deskriptif keberhasilan",
      "data": {}
  }
  ```
- **Struktur Respons Error**:
  ```json
  {
      "success": false,
      "message": "Pesan deskriptif kesalahan",
      "errors": {}
  }
  ```

---

## 2. Ringkasan Endpoint

| No | Modul | Method | URL Endpoint | Role yang Diizinkan | Fungsi |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | Auth | `GET` | `/login` | Publik (Guest) | Menampilkan form login |
| 2 | Auth | `POST` | `/login` | Publik (Guest) | Memproses autentikasi |
| 3 | Auth | `POST` | `/logout` | Semua Role Terautentikasi | Mengakhiri sesi pengguna |
| 4 | Dashboard | `GET` | `/dashboard` | Semua Role | Menampilkan view dashboard resmi |
| 5 | Dashboard | `GET` | `/dashboard/metrics` | Semua Role | API JSON ringkasan M-to-M, Q-to-Q, FOB |
| 6 | Upload | `GET` | `/upload-data` | Super Admin | Menampilkan halaman form upload |
| 7 | Upload | `POST` | `/upload-data/preview` | Super Admin | Pratinjau awal file Excel |
| 8 | Upload | `POST` | `/upload-data/process` | Super Admin | Menyimpan batch & streaming transaksi |
| 9 | Manajemen | `GET` | `/manajemen-data` | Semua Role (Filter status sesuai role)| Daftar dokumen aktif & trash |
| 10| Manajemen | `POST` | `/manajemen-data/{id}/toggle-release` | Super Admin | Rilis ke publik / tarik ke draft |
| 11| Manajemen | `GET` | `/manajemen-data/{id}/export` | Super Admin (Semua jika Rilis)| Unduh transaksi ke Excel .xlsx |
| 12| Manajemen | `POST` | `/manajemen-data/{id}/update` | Super Admin | Memperbarui nama file & jenis data |
| 13| Manajemen | `POST` | `/manajemen-data/{id}/delete` | Super Admin | Memindahkan dokumen ke Trash |
| 14| Manajemen | `POST` | `/manajemen-data/{id}/restore`| Super Admin | Memulihkan dokumen dari Trash |
| 15| Manajemen | `POST` | `/manajemen-data/{id}/force-delete` | Super Admin | Menghapus permanen dokumen & data |
| 16| Filtering | `GET` | `/filtering-data` | Semua Role | Halaman penelusuran data transaksi |
| 17| Filtering | `GET` | `/filtering-data/query` | Semua Role | API JSON pencarian data terfilter |
| 18| Analisis | `GET` | `/analisis-perdagangan` | Semua Role | Halaman analisis statistik |
| 19| Analisis | `GET` | `/analisis-perdagangan/data` | Semua Role | API JSON agregat komoditas & negara |
| 20| Komentar | `GET` | `/comments/batch/{id}` | Super Admin, Viewer Eksekutif | Mengambil riwayat komentar batch |
| 21| Komentar | `POST` | `/comments` | Super Admin, Viewer Eksekutif | Menambahkan komentar baru |
| 22| Komentar | `DELETE`| `/comments/{id}` | Super Admin, Pemilik Komentar | Menghapus komentar |
| 23| Ekspor PPT| `GET` | `/export/powerpoint/{id}` | Super Admin | Menghasilkan slide presentasi BRS |

---

## 3. Detail Spesifikasi Endpoint

### 3.1. Autentikasi Pengguna
#### `POST /login`
- **Tujuan**: Memproses login pengguna.
- **Request Body**:
  ```json
  {
      "username": "admin_sidatex",
      "password": "secret_password"
  }
  ```
- **Response Sukses (200 OK / 302 Redirect)**:
  ```json
  {
      "success": true,
      "message": "Login berhasil.",
      "redirect_url": "/dashboard"
  }
  ```
- **Response Gagal (422 Unprocessable Entity)**:
  ```json
  {
      "message": "Kredensial yang diberikan tidak cocok dengan data kami.",
      "errors": { "username": ["Username atau password salah."] }
  }
  ```

---

### 3.2. Dashboard & Metrik Resmi
#### `GET /dashboard/metrics`
- **Tujuan**: Mengambil angka statistik resmi berdasarkan data yang berstatus `RILIS`.
- **Query Parameters**:
  - `tahun` (opsional, integer, contoh: `2024`)
  - `jenis` (opsional, string: `Ekspor` atau `Impor`, default: `Ekspor`)
- **Response Sukses (200 OK)**:
  ```json
  {
      "success": true,
      "tahun": 2024,
      "jenis": "Ekspor",
      "summary": {
          "total_fob": 1845023000.50,
          "total_fob_formatted": "1.845,02 Juta US$",
          "total_netto": 12500400.20,
          "total_netto_formatted": "12.500,40 Ribu Ton"
      },
      "growth": {
          "mtom": 4.25,
          "qtoq": -1.15,
          "yony": 8.70,
          "ctoc": 6.40
      },
      "monthly_trend": [
          {"bulan": 1, "nama_bulan": "Jan", "nilai": 145000000},
          {"bulan": 2, "nama_bulan": "Feb", "nilai": 152000000},
          {"bulan": 12, "nama_bulan": "Des", "nilai": 0}
      ]
  }
  ```

---

### 3.3. Upload Data & Simpan Batch
#### `POST /upload-data/process`
- **Role**: `Super Admin`
- **Content-Type**: `multipart/form-data`
- **Request Body**:
  - `excel_file_final`: File Excel (.xlsx, maks 50 MB, wajib).
  - `jenis_data_final`: `Ekspor` atau `Impor` (wajib).
  - `status_final`: `Pending` (default, disimpan sebagai `DRAFT`) atau `Public` (Super Admin only).
  - `tahun_final`: integer (opsional).
  - `bulan_final`: integer (opsional).
- **Response Sukses (200 OK)**:
  ```json
  {
      "success": true,
      "message": "File 1. (Olah Data BRS) Ekspor LN Riau.xlsx berhasil disimpan ke database beserta 53.066 transaksi dengan status DRAFT.",
      "data": {
          "batch_id": 5,
          "nama_file": "1. (Olah Data BRS) Ekspor LN Riau.xlsx",
          "ukuran_file": 13.74,
          "status": "DRAFT",
          "total_transactions": 53066
      },
      "redirect_url": "/manajemen-data"
  }
  ```
- **Response Gagal (422 / 500)**:
  ```json
  {
      "success": false,
      "message": "Kolom BULAN, TAHUN, atau FOB (US$) tidak ditemukan dalam file Excel."
  }
  ```

---

### 3.4. Manajemen Data & Status Rilis

#### `POST /manajemen-data/{batchId}/toggle-release`
- **Role**: `Super Admin`
- **Tujuan**: Merilis data ke publik atau menarik kembali menjadi draft.
- **Response Sukses (200 OK)**:
  ```json
  {
      "success": true,
      "message": "Status data 'Ekspor_Riau.xlsx' berhasil diubah menjadi Public.",
      "new_status": "RILIS",
      "new_status_label": "Public"
  }
  ```
- **Response Akses Ditolak (403 Forbidden)**:
  ```json
  {
      "success": false,
      "message": "Akses ditolak. Hanya Super Admin / akun berwenang yang dapat merilis atau menarik data publik."
  }
  ```

#### `GET /manajemen-data/{batchId}/export`
- **Role**: `Super Admin` (jika status Rilis, dapat diakses `Pegawai` & `Viewer`).
- **Response Sukses (200 OK)**: Binary file stream dengan header:
  ```http
  Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
  Content-Disposition: attachment; filename="SIDATEX_Ekspor_Ekspor_Riau.xlsx"
  ```
- **Response Ditolak (403 Forbidden)** jika data masih DRAFT dan diakses oleh non-admin.

#### `POST /manajemen-data/{batchId}/update`
- **Role**: `Super Admin`
- **Request Body**:
  ```json
  {
      "nama_file": "Ekspor_Riau_Final_Revisi.xlsx",
      "jenis_data": "Ekspor"
  }
  ```
- **Response Sukses (200 OK)**:
  ```json
  {
      "success": true,
      "message": "Data dokumen 'Ekspor_Riau_Final_Revisi.xlsx' berhasil diperbarui.",
      "data": {
          "batch_id": 5,
          "nama_file": "Ekspor_Riau_Final_Revisi.xlsx",
          "jenis_data": "Ekspor",
          "status": "DRAFT"
      }
  }
  ```

#### `POST /manajemen-data/{batchId}/delete` (Soft Delete ke Trash)
- **Role**: `Super Admin`
- **Response Sukses (200 OK)**:
  ```json
  {
      "success": true,
      "message": "Dokumen 'Ekspor_Riau.xlsx' berhasil dipindahkan ke Trash."
  }
  ```

#### `POST /manajemen-data/{batchId}/restore` (Pulihkan dari Trash)
- **Role**: `Super Admin`
- **Response Sukses (200 OK)**:
  ```json
  {
      "success": true,
      "message": "Dokumen 'Ekspor_Riau.xlsx' berhasil dipulihkan dari Trash."
  }
  ```

---

### 3.5. Modul Komentar & Catatan Verifikasi

#### `GET /comments/batch/{batchId}`
- **Role**: `Super Admin`, `Viewer Berkepentingan`
- **Response Sukses (200 OK)**:
  ```json
  {
      "success": true,
      "data": [
          {
              "comment_id": 1,
              "batch_id": 5,
              "komentar": "Data migas telah divalidasi dengan kantor pabean Dumai.",
              "created_at": "2026-11-05 14:30:00",
              "user": {
                  "id": 1,
                  "name": "Super Admin SIDATEX",
                  "role": "Super Admin"
              }
          }
      ]
  }
  ```

#### `POST /comments`
- **Role**: `Super Admin`, `Viewer Berkepentingan`
- **Request Body**:
  ```json
  {
      "batch_id": 5,
      "komentar": "Catatan evaluasi untuk Berita Resmi Statistik."
  }
  ```
- **Response Sukses (201 Created)**:
  ```json
  {
      "success": true,
      "message": "Komentar berhasil ditambahkan.",
      "data": {
          "comment_id": 2,
          "komentar": "Catatan evaluasi untuk Berita Resmi Statistik."
      }
  }
  ```

---

### 3.6. Ekspor Bahan Presentasi (PowerPoint BPS)

#### `GET /export/powerpoint/{batchId}`
- **Role**: `Super Admin`
- **Response Sukses**: Stream file presentasi `.pptx`:
  ```http
  Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation
  Content-Disposition: attachment; filename="BRS_Ekspor_Impor_Riau_Batch_5.pptx"
  ```
