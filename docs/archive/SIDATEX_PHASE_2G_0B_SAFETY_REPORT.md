# SIDATEX — PHASE 2G.0b: SAFETY RAILS IMPLEMENTATION & VERIFICATION REPORT
**Dokumen:** `SIDATEX_PHASE_2G_0B_SAFETY_REPORT.md`  
**Tanggal:** 18 November 2026  
**Koordinator:** `sidatex-orchestrator`  
**Reviewer:** `database-guardian` (**PASS**), `independent-reviewer` (**APPROVED**)  
**Status Fase:** **FIXED & VERIFIED (100% PASSED)**

---

## 1. Daftar File yang Diubah

| File | Jenis Perubahan | Temuan Terkait | Status |
| :--- | :--- | :---: | :---: |
| [tests/TestCase.php](file:///d:/laragon/www/sidatex/tests/TestCase.php) | Pindahkan guard ke `setUpTraits()` sebelum `parent::setUpTraits()`; perkeras Layer 3 ke *strict whitelist* `'sidatex_testing'` + live PDO `SELECT DATABASE()` + *fail-closed* (`\PDOException` ditangkap sebelum `\RuntimeException`). | **F-09** | **FIXED & VERIFIED** |
| [tests/Unit/DatabaseSafetyGuardTest.php](file:///d:/laragon/www/sidatex/tests/Unit/DatabaseSafetyGuardTest.php) | Pertahankan 4 tes lama 100% utuh; tambah 6 tes baru (non-whitelist `sidatex_dev`, runtime mismatch, live `SELECT DATABASE()` mismatch, *fail-closed* `PDOException`, `setUpTraits()` hook + live PDO, static safety `composer.json`/`.env.example`). | **F-09, F-12a** | **FIXED & VERIFIED** |
| [composer.json](file:///d:/laragon/www/sidatex/composer.json) | Hapus `@php artisan migrate --force` dari `scripts.setup` serta hapus `@php artisan migrate --graceful --ansi` dan `database.sqlite` dari `scripts.post-create-project-cmd`. | **F-12a** | **FIXED & VERIFIED** |
| [.env.example](file:///d:/laragon/www/sidatex/.env.example) | Ubah default baris 26 `DB_DATABASE=sidatex` menjadi `DB_DATABASE=sidatex_testing`. | **F-12a** | **FIXED & VERIFIED** |

*Lingkup Terisolasi:* 0 perubahan pada kode aplikasi (`app/`, `routes/`, `resources/`), 0 perubahan migrasi, dan 0 mutasi pada database operasional `sidatex` maupun `sidatex_testing`.

---

## 2. Ringkasan Perubahan & Alasan Teknis

1. **Penutupan Celah Urutan Lifecycle `TestCase.php` (`L16-21`):**
   - Sebelumnya `parent::setUp()` memanggil `$this->setUpTraits()` sebelum `enforceTestDatabaseSafety()`, sehingga trait database (`RefreshDatabase`/`DatabaseTransactions`) berpotensi berjalan sebelum guard.
   - Kini `TestCase::setUpTraits()` mengeksekusi `$this->enforceTestDatabaseSafety()` terlebih dahulu sebelum `return parent::setUpTraits();`.
2. **Strict Whitelist & Live PDO `SELECT DATABASE()` (*Fail-Closed*) (`L70-116`):**
   - Sebelumnya Layer 3 hanya mengecek daftar hitam (`$forbiddenDbs`), mengandalkan `getDatabaseName()`, dan menelan eksepsi non-`RuntimeException` secara diam-diam.
   - Kini Layer 3 memverifikasi `strcasecmp(..., 'sidatex_testing') === 0` pada `DB::connection()->getDatabaseName()` **dan** sesi MySQL riil `$connection->getPdo()->query('SELECT DATABASE()')->fetchColumn()`, serta menangkap `\PDOException` **sebelum** `\RuntimeException` agar selalu *fail-closed*.
3. **Eliminasi Migrasi Paksa pada `composer.json` & Default `.env.example`:**
   - Menghilangkan risiko `composer setup` menyalin `DB_DATABASE=sidatex` dan langsung mengeksekusi `php artisan migrate --force` ke database operasional.

---

## 3. Bukti Pengujian (Sebelum vs Sesudah)

- **Sebelum Phase 2G.0b (Phase 0 Baseline):**
  ```text
  Command: php artisan test tests/Unit/DatabaseSafetyGuardTest.php
  Result : {"tool":"phpunit","result":"passed","tests":4,"passed":4,"assertions":8,"duration_ms":5161}
  Exit   : 0
  ```
- **Sesudah Phase 2G.0b:**
  ```text
  Command: php artisan test tests/Unit/DatabaseSafetyGuardTest.php
  Result : {"tool":"phpunit","result":"passed","tests":10,"passed":10,"assertions":23,"duration_ms":610}
  Exit   : 0
  ```
- **Verifikasi Runtime PDO Aktif:** `SELECT DATABASE()` = **`sidatex_testing`** (terbukti pada `Uji 8`).

---

## 4. Risiko Regresi

- Karena setiap test yang mewarisi `Tests\TestCase` kini membuka koneksi PDO `SELECT DATABASE()` secara *fail-closed*, layanan MySQL (`sidatex_testing`) wajib aktif saat menjalankan PHPUnit. Hal ini merupakan perilaku pengamanan yang diinginkan.

---

## 5. Hasil Review Specialist & Independent Reviewer

- **`database-guardian`:** **`PASS`** — Memverifikasi langsung seluruh 4 file (`TestCase.php`, `DatabaseSafetyGuardTest.php`, `composer.json`, `.env.example`), urutan `setUpTraits()`, whitelist `SELECT DATABASE()`, dan pewarisan `catch (\PDOException)` sebelum `\RuntimeException`.
- **`independent-reviewer`:** **`APPROVED`** — Memverifikasi langsung seluruh 4 file, memastikan 4 tes lama 100% utuh tanpa pelemahan asersi, 6 tes baru valid, dan menyetujui kelanjutan ke **Phase 2G.1**.

---

## 6. Bukti Database Operasional (`sidatex`) Tidak Berubah

Phase 2G.0b tidak menjalankan migrasi, seeder, maupun kueri tulis database apa pun (`0` mutasi). Baseline operasional `sidatex` (`data_batches = 3`, `distribusi_transactions = 2.557`, `neraca_transactions = 0`) tetap utuh 100% (akan diverifikasi ulang via kueri read-only pada penutupan Phase 2G.1).
