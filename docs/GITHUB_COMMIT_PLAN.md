# PANDUAN RENCANA COMMIT BERTAHAP GITHUB & VS CODE
## FASE 2G.10.6.4 &mdash; SISTEM INFORMASI DATA EKSPOR-IMPOR BPS PROVINSI RIAU (SIDATEX)

**Tanggal:** 20 November 2026  
**Status:** DRAF PERSIAPAN &mdash; SIAP DILAKUKAN COMMIT SECARA MANDIRI VIA VS CODE  
**Aturan Utama:**  
- **Dilarang melakukan commit atau push otomatis.**
- Pengembang/pengguna dapat meninjau diff dan melakukan staging file per kelompok fitur melalui antarmuka *Source Control* di VS Code.
- Seluruh file sensitif (`.env`, file cadangan SQL, token) telah dilindungi oleh `.gitignore`.

---

## 1. DAFTAR KELOMPOK COMMIT BERTAHAP

Tabel berikut memetakan urutan pengerjaan staging dan commit logis agar riwayat Git terstruktur, bersih, dan mudah dilacak (*traceable*):

| Urutan | Kelompok Fitur / Perubahan | File Terkait (Staged) | Pengujian Terkait | Usulan Pesan Commit (Conventional Commits) |
| :---: | :--- | :--- | :--- | :--- |
| **1** | **Database & Migrations** | `database/migrations/*`<br>`app/Models/DataBatch.php`<br>`app/Models/DistribusiTransaction.php`<br>`app/Models/NeracaTransaction.php`<br>`app/Models/MonthlyTradeSummary.php`<br>`app/Models/BrsIndicatorSummary.php` | `tests/Unit/DatabaseSafetyGuardTest.php`<br>`tests/Feature/Phase2gBrsDecimalPrecisionTest.php` | `fix(database): synchronize two-tier data mart schema and DECIMAL(20,6) precision` |
| **2** | **Engine Pengolahan DBF & Agregasi BRS** | `app/Services/DbfIngestionService.php`<br>`app/Services/DbfReaderService.php`<br>`app/Services/DbfUploadValidationService.php`<br>`app/Services/BrsDataProcessingService.php`<br>`app/Services/BrsAggregationService.php`<br>`app/Services/TradeAggregationService.php`<br>`app/Services/DataReconciliationService.php`<br>`app/Services/Parsers/*`<br>`app/Services/Contracts/*` | `tests/Feature/DbfIngestionComprehensiveTest.php`<br>`tests/Feature/BrsDataProcessingEngineTest.php` | `feat(dbf): implement atomic DBF ingestion, validation, and BRS aggregation engine` |
| **3** | **Keamanan & Otorisasi RBAC** | `app/Http/Controllers/Auth/LoginController.php`<br>`app/Http/Controllers/CommentController.php`<br>`app/Http/Controllers/ActivityLogController.php`<br>`app/Http/Middleware/*`<br>`routes/web.php` | `tests/Feature/RoleAuthorizationHardeningTest.php`<br>`tests/Feature/Phase2gRouteAndLoginSecurityTest.php`<br>`tests/Feature/Phase2gCommentRbacTest.php` | `feat(security): enforce server-side RBAC and protected endpoint authorization` |
| **4** | **Akurasi Zona Waktu (WIB)** | `config/app.php`<br>`config/database.php`<br>`.env.example`<br>`app/Services/ActivityLogger.php` | `tests/Feature/TradeAggregationAndActivityLogsTest.php` | `fix(timezone): align application, MySQL PDO connection, and Carbon to Asia/Jakarta (WIB)` |
| **5** | **Redesain Halaman Unggah Data** | `resources/views/upload-data.blade.php`<br>`app/Http/Controllers/UploadController.php`<br>`app/Http/Controllers/ExcelPreviewController.php` | `tests/Feature/DbfUploadWorkflowIntegrationTest.php`<br>`tests/Feature/DbfPreviewAndValidationStage2b3Test.php` | `feat(upload): redesign data upload workflow with 6-step progress stepper and drag-and-drop` |
| **6** | **Single Dashboard & Analisis** | `resources/views/dashboard.blade.php`<br>`resources/views/pages/distribusi-dashboard.blade.php`<br>`resources/views/pages/neraca-dashboard.blade.php`<br>`resources/views/pages/filtering-data.blade.php`<br>`resources/views/pages/analisis-perdagangan.blade.php`<br>`app/Http/Controllers/DashboardController.php`<br>`app/Http/Controllers/FilteringController.php`<br>`app/Http/Controllers/AnalisisController.php` | `tests/Feature/Phase2fSingleDashboardTest.php`<br>`tests/Feature/NeracaAndDistribusiModuleSeparationTest.php` | `feat(dashboard): integrate single dashboard tabs with real operational data mart` |
| **7** | **Manajemen Data, Trash & Log Aktivitas UI** | `resources/views/manajemen-data.blade.php`<br>`resources/views/pages/trash-data.blade.php`<br>`resources/views/pages/activity-logs.blade.php`<br>`resources/views/layouts/app.blade.php`<br>`resources/views/partials/pagination.blade.php`<br>`resources/css/app.css` | `tests/Feature/Phase2gBatchLifecycleAggregationTest.php`<br>`tests/Feature/Phase2gBladeAlignmentTest.php` | `fix(ui): refine table layouts, filters, pagination, and activity logs without overflow` |
| **8** | **Konfigurasi Git & Dokumentasi Audit** | `.gitignore`<br>`.env.example`<br>`README.md`<br>`STRUKTUR_PROJECT.md`<br>`.agents/*`<br>`docs/PHASE_2G_*`<br>`docs/GITHUB_COMMIT_PLAN.md` | Seluruh Test Suite (193 Tests, 1,596 Assertions PASS) | `docs: add comprehensive database audit, BPS data reconciliation, and commit plan` |

---

## 2. DETAIL FILE SENSITIF & PENGAMANAN `.gitignore`

File-file berikut telah dipastikan **TIDAK AKAN PERNAH** terunggah ke repositori GitHub karena telah masuk dalam aturan proteksi `.gitignore`:
1. `.env` &mdash; Berisi kredensial database lokal, app key, dan konfigurasi lingkungan.
2. `*.sql` & `/storage/backups/*.sql` &mdash; Berkas dump fisik database yang berisi data mentah.
3. `/storage/app/temp_staging/` &mdash; Folder berkas sementara pengunggahan DBF.
4. `/referensi_bps/` &mdash; Folder data referensi internal BPS (DBF mentah & lembar kerja BRS).
5. `vendor/` & `node_modules/` &mdash; Pustaka dependensi eksternal.

> [!TIP]
> File `.env.example` telah disiapkan dengan nilai *placeholder* yang aman tanpa kredensial rahasia, sehingga dapat menjadi acuan setup pengembang lain.

---

## 3. LANGKAH-LANGKAH MELAKUKAN COMMIT VIA VS CODE

Bagi pengembang yang akan melakukan commit melalui VS Code:
1. Buka tab **Source Control** di sisi kiri VS Code (`Ctrl + Shift + G`).
2. Pilih file-file yang termasuk dalam **Kelompok 1 (Database & Migrations)**.
3. Klik tanda tambah (**+**) di sebelah nama file untuk melakukan *Stage Changes*.
4. Masukkan pesan commit pada kotak teks di bagian atas:
   ```text
   fix(database): synchronize two-tier data mart schema and DECIMAL(20,6) precision
   ```
5. Klik tombol centang (**Commit**) atau tekan `Ctrl + Enter`.
6. Ulangi langkah 2 s.d. 5 secara berurutan untuk **Kelompok 2 hingga Kelompok 8**.
7. Sebelum melakukan *Push* ke remote, jalankan perintah pengujian akhir:
   ```bash
   php artisan test
   ```
   Pastikan seluruh 193 tes menghasilkan status **PASS** (1,596 assertions, 0 failures, 0 errors).
