# SIDATEX — PHASE 2G.1: SECURITY & ACCESS CONTROL IMPLEMENTATION & VERIFICATION REPORT
**Dokumen:** `SIDATEX_PHASE_2G_1_SECURITY_REPORT.md`  
**Tanggal:** 18 November 2026  
**Koordinator:** `sidatex-orchestrator`  
**Reviewer:** `security-auditor` (**PASS**), `independent-reviewer` (**APPROVED WITH MINOR NOTES** — seluruh catatan minor langsung diselesaikan & diuji ulang)  
**Status Fase:** **SELESAI & TERVERIFIKASI — BERHENTI MENUNGGU PERSETUJUAN PHASE 2G.2**

---

## 1. DAFTAR FILE YANG DIUBAH & STATUS TEMUAN

### A. File Aplikasi & Rute yang Dimodifikasi
| File | Ringkasan Perubahan | Temuan | Status |
| :--- | :--- | :---: | :---: |
| [app/Http/Controllers/UploadController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/UploadController.php) | `store()` Excel ke disk `'local'` (`storage/app/private/uploads/excel`) + `mimes:xlsx,xls` + sanitasi `basename()` + hapus file saat `DB::rollBack()`; `manajemen()` isolasi `$trashedItems`/`$totalTrash` khusus Super Admin; `export()` penegakan matriks RBAC 3 role × 4 status (Role 1 & 3: `RILIS`+`PRIVATE`, Role 2: `RILIS`, blokir `DRAFT` & `Trash` `403`, hapus bypass `$isOwner`, wajib `activity_logs` dgn `user_id` asli); `update()` blokir `/`, `\`, `..`; `forceDelete()` wajib berstatus di Trash + pembersihan file pasca-`DB::commit()` dalam `try/catch` terpisah dengan `basename()` + `realpath()` containment khusus disk `'local'` (tidak menyentuh `storage/app/public/uploads/`). | **F-02A, F-10, Kebijakan A** | **FIXED & VERIFIED** |
| [app/Http/Controllers/CommentController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/CommentController.php) | Helper `authorizeBatchAccess()`: penegakan RBAC, status (`RILIS`/`PRIVATE`/`DRAFT`), dan blokir `Trash` pada `getByBatch()`, `store()`, & `destroy()`; cek role sebelum `exists:data_batches` pada `store()` (anti-enumerasi ID bagi Role 2); cast `(int)` & validasi ulang batch pada `destroy()`. | **F-03** | **FIXED & VERIFIED** |
| [app/Http/Controllers/Auth/LoginController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/Auth/LoginController.php) | Hanya `(int)$user->role_id === 1` diarahkan ke `route('upload-data')`; Role 2 (`Pegawai`) dan Role 3 (`Viewer Eksekutif`) diarahkan ke `route('dashboard')`. | **F-11a** | **FIXED & VERIFIED** |
| [routes/web.php](file:///d:/laragon/www/sidatex/routes/web.php) | Hapus rute mati `/dashboard/visual` (`dashboard.visual`) dan rute tak terpakai di luar `superadmin` `/dashboard/master` (`dashboard.master`). | **F-08** | **FIXED & VERIFIED** |
| [app/Http/Controllers/DashboardController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/DashboardController.php) | `index()` `$batchStats` mengecualikan batch di `trash` dan menyembunyikan jumlah batch `PRIVATE` dari Role 2 (`distribusi_private = 0`, `distribusi_total = distribusi_rilis`). | **F-11b (backend)** | **FIXED & VERIFIED** |
| [app/Services/BrsDataProcessingService.php](file:///d:/laragon/www/sidatex/app/Services/BrsDataProcessingService.php) & [app/Services/DbfIngestionService.php](file:///d:/laragon/www/sidatex/app/Services/DbfIngestionService.php) | Sanitasi `basename()`, penghapusan `..`, dan regex karakter aman pada nama arsip DBF (kebijakan disk `'local'` dipertahankan 100%). | **F-02B (hygiene)** | **FIXED & VERIFIED** |

### B. Automated Test Suites yang Ditambahkan / Diperbarui
| File Test | Cakupan Pengujian | Status |
| :--- | :--- | :---: |
| [tests/Feature/Phase2gExportRbacPolicyTest.php](file:///d:/laragon/www/sidatex/tests/Feature/Phase2gExportRbacPolicyTest.php) *(baru)* | Matriks ekspor penuh 3 role × 4 status (Super Admin, Pegawai, Viewer Eksekutif × `RILIS`, `PRIVATE`, `DRAFT`, `Trash`), pengujian peniadaan bypass `$isOwner`, dan verifikasi `activity_logs` positif/negatif. | **FIXED & VERIFIED** |
| [tests/Feature/Phase2gPrivateUploadStorageTest.php](file:///d:/laragon/www/sidatex/tests/Feature/Phase2gPrivateUploadStorageTest.php) *(baru)* | Upload `.xlsx` masuk disk `'local'` (`public` 0 file), pembersihan file saat rollback, penolakan `422` file non-Excel berkedok `.xlsx`, penolakan path traversal di `update()`, dan penolakan non-Trash + proteksi sentinel file di `forceDelete()`. | **FIXED & VERIFIED** |
| [tests/Feature/Phase2gCommentRbacTest.php](file:///d:/laragon/www/sidatex/tests/Feature/Phase2gCommentRbacTest.php) *(baru)* | Uji Guest (`401`), Pegawai (`GET` hanya `RILIS`, blokir `PRIVATE`/`DRAFT`/`Trash` tanpa kebocoran teks, `POST`/`DELETE` -> `403` sebelum enumerasi ID), Viewer (`RILIS`/`PRIVATE` baca+tulis, blokir `DRAFT`/`Trash`), dan Super Admin. | **FIXED & VERIFIED** |
| [tests/Feature/Phase2gRouteAndLoginSecurityTest.php](file:///d:/laragon/www/sidatex/tests/Feature/Phase2gRouteAndLoginSecurityTest.php) *(baru)* | Verifikasi `dashboard.visual` & `dashboard.master` tidak terdaftar (`404`), redirect login Role 1/2/3 (`Role 3 -> /dashboard 200`, akses langsung `/upload-data -> 403`), serta isolasi metadata `totalTrash` & `batchStats` bagi Pegawai. | **FIXED & VERIFIED** |
| [tests/Feature/SidatexComprehensiveTest.php](file:///d:/laragon/www/sidatex/tests/Feature/SidatexComprehensiveTest.php) *(update)* | Menambahkan `Storage::fake('local')` & `Storage::fake('public')` pada `test_upload_file_size_limits_validation` agar PHPUnit berhenti menciptakan file 0-byte orphan (0 asersi dilemahkan). | **FIXED & VERIFIED** |

### C. Item Sengaja Ditunda ke Fase Berikutnya (Sesuai Persetujuan Phase 0)
- **F-01 (`StatisticsCalculationService` & `ExcelPreviewController` filter `RILIS` mart):** **NOT FIXED (Dijadwalkan di Phase 2G.3)** — menunggu sinkronisasi mart & penanganan data historis di Phase 2G.2 agar tidak memicu *ALL-PRIVATE blank-out* pada 39.110 baris mart `sidatex_testing`.
- **F-04, F-05, F-07 (`DECIMAL(20,6)`, `TradeAggregationService`, `FilteringController`, `FastExcelExporter`):** **NOT FIXED (Dijadwalkan di Phase 2G.2)**.
- **F-06 (`0.0 -> null`, QoQ 3-bulan lengkap):** **NOT FIXED (Dijadwalkan di Phase 2G.3)**.
- **Blade UI polish (`manajemen-data.blade.php`, `dashboard.blade.php`, `filtering-data.blade.php`):** **NOT FIXED (Dijadwalkan di Phase 2G.4)**.

---

## 2. VERIFIKASI MATRIKS KEBIJAKAN EKSPOR (KEBIJAKAN A — SERVER-SIDE)

Ditegakkan secara ketat di `UploadController::export()` (`L462-549`) dan diverifikasi oleh `Phase2gExportRbacPolicyTest`:

| Role | `RILIS` (Aktif) | `PRIVATE` (Aktif) | `DRAFT` | `Trash` (`restored_at IS NULL`) | Audit Trail `activity_logs` |
| :--- | :---: | :---: | :---: | :---: | :--- |
| **Super Admin (Role 1)** | **Allowed (`200`)** | **Allowed (`200`)** | **Denied (`403`)** | **Denied (`403`)** | Tercatat dgn `user_id` Super Admin + Status + Sumber |
| **Pegawai (Role 2)** | **Allowed (`200`)** | **Denied (`403`)** *(termasuk bila `uploaded_by` = Pegawai)* | **Denied (`403`)** | **Denied (`403`)** | Tercatat hanya untuk `200` dgn `user_id` Pegawai |
| **Viewer Eksekutif (Role 3)** | **Allowed (`200`)** | **Allowed (`200`)** | **Denied (`403`)** | **Denied (`403`)** | Tercatat dgn `user_id` Viewer + Status + Sumber |

---

## 3. INVENTARISASI 55 BERKAS LAMA DI `storage/app/public/uploads/` (READ-ONLY — 0 BERKAS DIHAPUS/DIPINDAHKAN)

Sesuai Arahan D, **tidak ada satu pun berkas yang dihapus atau dipindahkan**, dan symlink `public/storage` **tidak dibuat** (`public/storage` terkonfirmasi tidak ada). Hasil inventarisasi read-only aktual menemukan **55 berkas** (total **36.239.517 bytes / ~34,56 MB**):

### Ringkasan Inventarisasi
| Subdirektori | Tipe | Jumlah Berkas | Total Ukuran | Klasifikasi Status Penggunaan |
| :--- | :---: | :---: | :---: | :--- |
| `storage/app/public/uploads/excel/` | `XLSX` | **6** | 32.565.017 B (~31,06 MB) | **`UNREFERENCED_LEGACY`** — tidak terujuk di `data_batches` operasional maupun testing (mencakup 2 file BRS 14,4 MB: `1793872339_1. (Olah Data BRS) Ekspor LN Riau.xlsx`, `1793874341_Uji_Status_Pending.xlsx`, 1 file 3,75 MB `1794284580_Ekspor.xlsx`, dan 3 file uji kecil 186 B). |
| `storage/app/public/uploads/excel/` | `XLSX` | **8** | 0 B | **`ORPHAN_TEST_0BYTE`** — berkas kosong (`boundary_50mb.xlsx` × 4, `small_valid.xlsx` × 4, tanggal `2026-11-13`) sisa pengujian PHPUnit sebelumnya sebelum `Storage::fake()` ditambahkan. |
| `storage/app/public/uploads/raw_distribusi/` | `DBF` | **39** | 3.283.934 B (~3,13 MB) | **`MATCHED_BATCH_NAME`** — nama dasar cocok dengan batch operasional `sidatex`: `#70` (`14_impor_atap_kdprov_0826.DBF`, 2 salinan, juga cocok dgn test `#70`), `#71` (`14_Atap_ekspor_2609_POD_based.DBF`, 35 salinan; `1794467165_*` tepat sama dgn `created_at` op `#71`), `#74` (`14_impor_atap_kdprov_0926.DBF`, 2 salinan; `1794500427_*` tepat sama dgn `created_at` op `#74`). |
| `storage/app/public/uploads/raw_distribusi/` | `DBF` | **2** | 390.566 B (~381 KB) | **`UNREFERENCED_LEGACY`** — `14_impor_atap_kdprov_0726.DBF` (2 salinan tanggal `2026-11-11`, sudah tergantikan arsip private di `storage/app/private/uploads/raw_distribusi/`). |
| **TOTAL** | — | **55 berkas** | **36.239.517 B (~34,56 MB)** | **Seluruh 55 berkas utuh (0 dihapus, 0 dipindahkan).** |

*Rekomendasi Pemulihan Terpisah (Menunggu Persetujuan Eksplisit):* Sebelum `php artisan storage:link` pernah diaktifkan di lingkungan mana pun, lakukan backup arsip ZIP seluruh folder `storage/app/public/uploads/` + checksum SHA-256, relokasikan 3 berkas DBF primer yang cocok timestamp batch operasional (`#70`, `#71`, `#74`) dan berkas BRS relevan ke `storage/app/private/uploads/legacy_archive/`, verifikasi integritas, lalu baru bersihkan berkas duplikat/orphan 0-byte.

---

## 4. BUKTI PENGUJIAN (SEBELUM VS SESUDAH)

- **Baseline Full Suite Sebelum Phase 2G:**
  ```text
  {"tool":"phpunit","result":"passed","tests":123,"passed":123,"assertions":759}
  ```
- **Focused Phase 2G.1 Security Suites (Pasca-Implementasi & Hygiene Polish):**
  ```text
  Command: php artisan test tests/Feature/Phase2gExportRbacPolicyTest.php tests/Feature/Phase2gPrivateUploadStorageTest.php tests/Feature/Phase2gCommentRbacTest.php tests/Feature/Phase2gRouteAndLoginSecurityTest.php
  Result : {"tool":"phpunit","result":"passed","tests":15,"passed":15,"assertions":116,"duration_ms":2815}
  Exit   : 0
  ```
- **Full PHPUnit Regression Suite (`php artisan test`):**
  ```text
  Command: php artisan test
  Result : {"tool":"phpunit","result":"passed","tests":144,"passed":144,"assertions":890,"duration_ms":118758}
  Exit   : 0 (0 failures, 0 errors, 0 skips)
  ```
- **Kebersihan Pasca-Uji `sidatex_testing`:** `data_batches = 9`, `distribusi_transactions = 3.575`, `neraca_transactions = 4.065`, `monthly_trade_summaries = 39.110` (0 baris historis diubah/dihapus), `brs_indicator_summaries = 338`, `trash = 0`, `comments = 0`, `sentinel_batches (>=2090) = 0`, `temp_p2g_users = 0`.

---

## 5. RISIKO REGRESI & CATATAN LANJUTAN

1. **Sinkronisasi Tombol UI dengan Kebijakan Backend:** Di `resources/views/manajemen-data.blade.php`, tombol `Trash Dokumen` (kini menampilkan `0` bagi non-SuperAdmin), `Edit Dokumen`, dan `PowerPoint` masih dirender secara visual untuk Role 2/3 meski server memblokirnya dengan `403`. Perapihan tampilan Blade dijadwalkan di **Phase 2G.4**.
2. **Kebocoran Mart `PRIVATE`/Trash di `StatisticsCalculationService` (`F-01`):** Masih terbuka secara terencana hingga **Phase 2G.2** menyinkronkan siklus mart & data historis, lalu **Phase 2G.3** mengaktifkan filter `RILIS` tanpa menyebabkan *ALL-PRIVATE blank-out*.
3. **Eksklusi Trash pada Dropdown Tahun `DashboardController::index()` (`L48-60`):** Dicatat untuk ditangani bersamaan di **Phase 2G.3**.

---

## 6. HASIL REVIEW SPECIALIST & INDEPENDENT REVIEWER

- **`security-auditor`:** **`PASS`** — Memverifikasi seluruh kontrol Phase 2G.1 (storage private Excel, MIME & rollback unlink, matriks ekspor 3×4 + mandatory `activity_logs`, proteksi path traversal `update()`/`forceDelete()`, RBAC & anti-enumerasi `CommentController`, redirect Role 3, penghapusan rute mati, sanitasi DBF `basename()`). Catatan hygiene `try/catch` terpisah pasca-`DB::commit()` pada `forceDelete()` telah langsung diterapkan & diuji ulang.
- **`independent-reviewer`:** **`APPROVED WITH MINOR NOTES`** — Memverifikasi langsung seluruh 12 berkas sumber & tes Phase 2G.1, mengonfirmasi tidak ada pelemahan asersi tes lama dan tidak ada perubahan di luar ruang lingkup Phase 2G.1. Kedua catatan minor (`try/catch` terpisah di `forceDelete()` dan penghapusan properti `$createdLogIdsBefore` di `Phase2gExportRbacPolicyTest.php`) telah langsung diperbaiki dan diverifikasi lulus (`15/15 passed, 116 assertions`).

---

## 7. BUKTI DATABASE OPERASIONAL (`sidatex`) TIDAK BERUBAH

Hasil pemeriksaan read-only pasca-Phase 2G.1 langsung terhadap sesi PDO `SELECT DATABASE() = 'sidatex'`:
- `data_batches`: **3** (`MAX(updated_at)` = **`2026-11-13 07:38:33`** — identik)
- `distribusi_transactions`: **2.557** (identik)
- `neraca_transactions`: **0** (identik)
- `monthly_trade_summaries`: **34.847** (identik)
- `monthly_origin_trade_summaries`: **189** (identik)
- `trash` / `comments`: **0 / 0** (identik)
- `migrations`: **26** (identik)
- `storage/app/public/uploads/`: **55 berkas** (identik, 0 diubah/dihapus/dipindahkan)

> **Status Checkpoint:** **Phase 2G.0b & Phase 2G.1 SELESAI 100%.** Eksekusi dihentikan di sini sesuai instruksi. Menunggu tinjauan dan persetujuan pengguna sebelum memulai **Phase 2G.2 — Database & Aggregation Integrity**.
