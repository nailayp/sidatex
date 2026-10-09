# SIDATEX

### Sistem Informasi Data Ekspor-Impor — BPS Provinsi Riau

SIDATEX merupakan aplikasi berbasis web yang dirancang untuk mendukung pengelolaan, pengolahan, visualisasi, dan analisis data ekspor-impor. Project ini dikembangkan sebagai bagian dari kegiatan Kerja Praktik (KP).

## Teknologi

* **Framework:** Laravel 13
* **Bahasa pemrograman:** PHP 8.4
* **Database:** MySQL
* **Frontend assets:** Vite, NPM
* **Code editor:** Visual Studio Code
* **Local development environment:** Laragon

## Persyaratan

Pastikan perangkat sudah memiliki:

* PHP 8.4 atau versi kompatibel dengan Laravel 13
* Composer
* MySQL
* Node.js dan NPM
* Git
* Visual Studio Code (disarankan)

## Instalasi

### 1. Clone repository

Buka terminal Laragon, kemudian jalankan:

```bash
cd D:\laragon\www
git clone https://github.com/nailayp/sidatex.git
cd sidatex
```

### 2. Install dependency

```bash
composer install
npm install
```

### 3. Konfigurasi environment

Salin file konfigurasi contoh:

```bash
copy .env.example .env
```

Buat application key:

```bash
php artisan key:generate
```

### 4. Buat database

Buat database MySQL dengan nama `sidatex` melalui Laragon atau phpMyAdmin.

### 5. Atur koneksi database

Buka file `.env` dan sesuaikan konfigurasi berikut dengan MySQL lokal:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sidatex
DB_USERNAME=root
DB_PASSWORD=
```

Jika MySQL menggunakan password, sesuaikan nilai `DB_PASSWORD`.

### 6. Jalankan migration

```bash
php artisan migrate
```

Migration yang tersedia akan membuat tabel yang sudah didefinisikan di project. Tabel khusus SIDATEX akan ditambahkan selama proses pengembangan.

### 7. Jalankan aplikasi

Untuk menjalankan Laravel:

```bash
php artisan serve
```

Untuk menyiapkan aset frontend:

```bash
npm run build
```

Buka alamat yang ditampilkan di terminal, biasanya:

http://127.0.0.1:8000

## Gambaran Pengembangan

Fitur yang direncanakan untuk SIDATEX meliputi:

* Login lokal sebagai tahap awal, dengan rencana integrasi SSO BPS.
* Pengelolaan pengguna dan hak akses.
* Upload serta pemilihan data Excel sebelum diproses.
* Preview dan validasi data.
* Pengelolaan status data: DRAFT, NON-RILIS, dan RILIS.
* Dashboard dan visualisasi data ekspor-impor.
* Analisis, perbandingan periode, dan neraca perdagangan.
* Pengelolaan master data.
* Log aktivitas, komentar, serta pemulihan data.
* Unduh hasil dan pembuatan presentasi PowerPoint.

Fitur-fitur tersebut merupakan rencana pengembangan, bukan seluruhnya sudah tersedia pada versi awal repository.

## Aturan Kerja Tim

1. Gunakan repository GitHub yang sama sebagai sumber kode utama.
2. Jangan mengerjakan fitur berbeda langsung pada branch `main`.
3. Buat branch untuk setiap fitur, misalnya:

```bash
git checkout -b feature/login
```

4. Setelah fitur selesai, lakukan commit dan push ke branch masing-masing.
5. Ajukan Pull Request untuk menggabungkan perubahan ke `main`.
6. Jangan mengunggah file `.env`, password, token, atau data internal yang bersifat rahasia.
7. Jangan mengunggah folder `vendor` dan `node_modules`.
8. Setiap anggota menggunakan `.env` dan database lokal masing-masing.

## Repository

GitHub: https://github.com/nailayp/sidatex

---

**SIDATEX — Sistem Informasi Data Ekspor-Impor**
Project Kerja Praktik, BPS Provinsi Riau.
