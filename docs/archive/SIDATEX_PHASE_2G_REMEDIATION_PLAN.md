# SIDATEX — PHASE 2G REMEDIATION PLAN
**Dokumen:** `SIDATEX_PHASE_2G_REMEDIATION_PLAN.md`  
**Tanggal:** 18 November 2026  
**Koordinator:** `sidatex-orchestrator` (Lead Orchestrator)  
**Tim Verifikasi Spesialis (7 Agen):** `security-auditor`, `database-guardian`, `independent-reviewer`, `statistics-auditor`, `laravel-implementer`, `frontend-reviewer`, `test-engineer`  
**Mode Penyusunan:** **STRICT READ-ONLY** (0 file aplikasi, konfigurasi, migrasi, maupun database diubah)  
**Status Rencana:** **APPROVED WITH MINOR NOTES** (Ditinjau oleh `independent-reviewer`, siap eksekusi setelah persetujuan pengguna)

---

## 1. EXECUTIVE SUMMARY

Menindaklanjuti hasil [SIDATEX_AGENT_CAPABILITY_TEST_REPORT.md](file:///d:/laragon/www/sidatex/SIDATEX_AGENT_CAPABILITY_TEST_REPORT.md), seluruh **7 agen spesialis** telah melaksanakan verifikasi ulang kode sumber (*strict read-only*) atas 8 temuan target utama beserta risiko turunan yang saling terkait di lapisan keamanan, integritas data mart, metodologi statistik BPS, antarmuka Blade, dan isolasi pengujian PHPUnit.

### Ringkasan Status Verifikasi
| Kategori | Jumlah | Rincian |
| :--- | :---: | :--- |
| **`VERIFIED`** | **12 Temuan** | 7 dari 8 target utama terverifikasi penuh (+ temuan 2A Excel terverifikasi), ditambah 4 temuan kritis/menengah yang terungkap saat penelusuran kode (guard `TestCase.php`, `forceDelete` traversal + `export()` Trash bypass, redirect Role 3 + UI RBAC leak, `composer.json` `migrate --force` + `down()` rollback). |
| **`FALSE POSITIVE`** | **1 Sub-temuan** | **F-02B (DBF pada `public` disk):** Upload DBF melalui `BrsDataProcessingService` terbukti sudah tersimpan di disk `'local'` (`storage/app/private`), bukan `'public'`. Yang terekspos di disk `'public'` adalah upload **Excel** (`UploadController::store`). |
| **`NOT VERIFIED`** | **6 Item Runtime/Live** | Status symlink `public/storage` di server, status baris historis `< 2026/07` (`batch_id IS NULL`) di DB live `sidatex_testing`, hasil `SHOW CREATE TABLE` aktual, perilaku visual Chart.js lintas tab di browser, dan eksekusi PHPUnit pasca-perbaikan (seluruhnya tidak dijalankan demi menjaga prinsip *strict read-only*). |

### Akar Masalah Utama yang Saling Berkaitan (*Interconnected Root Causes*)
Verifikasi lintas agen membuktikan bahwa sebagian besar temuan tidak berdiri sendiri, melainkan berpusat pada **3 klaster sistemik**:
1. **Klaster 1 — Desinkronisasi Siklus Hidup Two-Tier Data Mart (`F-01`, `F-05`, `F-07`):** `UploadController` (`toggleRelease`, `destroy`, `restore`, `forceDelete`) masih bergantung pada layanan warisan `TradeAggregationService` (yang meng-hardcode `distribusi_transactions`, memiliki fallback terlarang ke tabel arsip `transactions`, menghapus `monthly_origin_trade_summaries` lintas sumber, dan menghilangkan 9 kolom metadata BRS) serta belum memanggil `BrsAggregationService::updateBatchVisibility()`. Akibatnya, `StatisticsCalculationService` membaca data `PRIVATE` dan `Trash` secara tidak sengaja.
2. **Klaster 2 — Hardcoding `distribusi_transactions` pada Modul Pendukung (`F-04`, `F-05`):** Single Dashboard (`/dashboard`) sudah memisahkan `distribusi_transactions` vs `neraca_transactions`, tetapi modul pendukung (`FilteringController`, `FastExcelExporter`, `TradeAggregationService`, `UploadController::update/destroy`, `PowerPointExportService`, fallback `StatisticsCalculationService`, dan UI `pages/filtering-data.blade.php`) belum sepenuhnya mendukung `neraca_transactions` dan kode HS *unmapped* (`hs_code_raw`).
3. **Klaster 3 — Penegakan Batas Keamanan & Kepatuhan Metodologi BPS (`F-02A`, `F-03`, `F-06`, `F-09`, `F-10`, `F-11`):** Penyimpanan Excel di disk publik, IDOR baca/tulis pada `CommentController`, pengembalian `0.0` (dan `-100.0`) alih-alih `null` pada periode pembanding kosong (melanggar Aturan 7), serta urutan eksekusi guard PDO di `tests/TestCase.php` yang berjalan setelah `parent::setUpTraits()`.

---

## 2. DAFTAR TEMUAN TERVERIFIKASI

---

### [F-01] Kebocoran Data `PRIVATE` & `Trash` ke Perhitungan Dashboard via `StatisticsCalculationService`
- **Status:** **`VERIFIED`**
- **Severity / Prioritas:** **CRITICAL / P0**
- **Agen Pemverifikasi:** `security-auditor`, `independent-reviewer`, `statistics-auditor`, `database-guardian`, `laravel-implementer`
- **Path File & Nomor Baris Aktual:**
  - [app/Services/StatisticsCalculationService.php](file:///d:/laragon/www/sidatex/app/Services/StatisticsCalculationService.php#L10-L64) (`L10-11`, `L25-39`, `L45-64`)
  - [app/Http/Controllers/DashboardController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/DashboardController.php#L48-L65) (`L48-65`, `L136-143`, `L164-176`, `L604-608`, `L624-627`)
  - [app/Http/Controllers/AnalisisController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/AnalisisController.php#L125-L142) (`L125`, `L136-142`)
  - [app/Http/Controllers/UploadController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/UploadController.php#L404-L409) (`toggleRelease` `L404-409`, `destroy` `L644-651`)
  - [app/Services/BrsAggregationService.php](file:///d:/laragon/www/sidatex/app/Services/BrsAggregationService.php#L146-L147) (`L146-147`, `L565-586`, `L614-644`)
- **Potongan Kode Bukti:**
  ```php
  // app/Services/StatisticsCalculationService.php:45-49 (Docblock L11 mengklaim hanya RILIS, namun query tanpa filter!)
  $summariesQuery = DB::table('monthly_trade_summaries')
      ->where('jenis_transaksi', $jenis)
      ->where('tipe_sumber', $tipeSumber)
      ->whereIn('tahun', [$tahun, $tahun - 1]);
  // Tidak ada filter status_visibilitas = 'RILIS', tidak ada JOIN data_batches, dan tidak ada eksklusi tabel trash!

  // app/Http/Controllers/UploadController.php:404-409 (toggleRelease tidak menyinkronkan mart!)
  DB::table('data_batches')->where('batch_id', $batchId)->update([
      'status' => $newStatus, 'released_by' => $releasedBy, 'released_at' => $releasedAt, 'updated_at' => now(),
  ]);
  ```
- **Risiko & Dampak:**
  - Melanggar mutlak `AGENTS.md` Seksi 8 & 9: Pegawai BPS (Role 2) yang mengakses `/dashboard/metrics`, `/dashboard/data` (indikator `mtom/qtoq/yony/ctoc`), dan `/analisis-perdagangan/data` menerima angka pertumbuhan makro yang sudah tercampur data internal `PRIVATE` dan batch terhapus di `Trash`.
  - Menyebabkan kontradiksi internal pada `/dashboard`: total FOB/Netto memfilter `RILIS` saja untuk Pegawai, tetapi persentase pertumbuhan di sebelahnya membaca `PRIVATE` + `Trash`.
  - Menarik rilis (`RILIS -> PRIVATE`) atau menghapus batch ke Trash tidak mengubah angka di `monthly_trade_summaries` maupun `brs_indicator_summaries`.
- **Rekomendasi Perbaikan:**
  1. Tambahkan parameter opsional `array $allowedStatuses = ['RILIS']` pada `StatisticsCalculationService::getOfficialDashboardMetrics()`; terapkan `LEFT JOIN data_batches as b ON mts.batch_id = b.batch_id` dengan penyaringan ketat: bila `mts.batch_id IS NOT NULL`, wajib `b.status IN $allowedStatuses`, `mts.status_visibilitas IN $allowedStatuses`, dan `mts.batch_id NOT IN (SELECT batch_id FROM trash WHERE restored_at IS NULL)`; bila `mts.batch_id IS NULL` (baris historis pra-batch), izinkan hanya sesuai aturan status historis `RILIS` (verifikasi nilai aktual `status_visibilitas` di `sidatex_testing` terlebih dahulu).
  2. Perbaiki fallback `$latestYear` (`L31-36`) agar memilih tabel dinamis (`neraca_transactions` vs `distribusi_transactions`) dengan join `data_batches` + filter `$allowedStatuses` + eksklusi `trash`.
  3. Di `UploadController::toggleRelease()`, panggil `BrsAggregationService::updateBatchVisibility((int)$batchId, $newStatus, (int)$currentUser->id)` (metode sudah tersedia di `BrsAggregationService.php:614-644`).
  4. Di `UploadController::destroy()`, hapus baris mart milik `$batchId` (`removeBatchAggregation()`) secara atomik di dalam transaksi DB.
  5. Di `DashboardController::metrics()` dan `data()`, teruskan `$allowedStatuses` berbasis peran pengguna agar angka KPI dan pertumbuhan selalu rekonsiliatif; di `index()`, kecualikan `trash` pada `$distYears`, `$neracaYears`, `$historicalYears`, dan sembunyikan jumlah batch `PRIVATE` dari Role 2.
- **Test yang Harus Ditambahkan/Diperbarui:**
  - `Phase2gStatisticsCalculationTest`: uji kombinasi batch `RILIS`, `PRIVATE`, `DRAFT`, `Trash` (`restored_at = NULL`), dan batch hasil *restore* (`restored_at != NULL`) untuk Distribusi & Neraca pada Role 1, 2, 3.
  - `Phase2gBatchLifecycleAggregationTest`: uji `toggleRelease` (`PRIVATE <-> RILIS`) dan `destroy` langsung menyinkronkan kedua tier mart serta output `/dashboard/metrics`.

---

### [F-02A] Berkas Excel Unggahan Disimpan pada `public` Disk
- **Status:** **`VERIFIED`**
- **Severity / Prioritas:** **HIGH / P1**
- **Agen Pemverifikasi:** `security-auditor`, `independent-reviewer`, `laravel-implementer`
- **Path File & Nomor Baris Aktual:**
  - [app/Http/Controllers/UploadController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/UploadController.php#L151-L189) (`L151-152`, `L163`, `L188-189`, `L196`, `L240-257`)
  - [config/filesystems.php](file:///d:/laragon/www/sidatex/config/filesystems.php#L33-L48) (`L33-48`, `L76-78`)
  - [public/.htaccess](file:///d:/laragon/www/sidatex/public/.htaccess#L22-L24) (`L22-24`)
- **Potongan Kode Bukti:**
  ```php
  // app/Http/Controllers/UploadController.php:188-189
  $storedPath = $file->storeAs('uploads/excel', time() . '_' . $namaFile, 'public');
  $fullPath = storage_path('app/public/' . $storedPath);
  ```
- **Risiko & Dampak:**
  - Berkas Excel mentah (termasuk data rahasia `PRIVATE`/`DRAFT` sebelum rilis BPS) ditulis ke `storage/app/public/uploads/excel/`. Apabila `php artisan storage:link` aktif di server produksi, web server melayani file tersebut langsung di `/storage/uploads/excel/...` **tanpa melewati autentikasi dan RBAC Laravel**.
  - Selain itu: validasi `extensions:xlsx,xls` (`L152`) tidak memeriksa MIME type; `$namaFile` (`getClientOriginalName()`) tidak disanitasi dengan `basename()`; dan pada kegagalan parsing (`catch` `L240-257`), file fisik tertinggal sebagai *orphan file* di disk publik.
- **Rekomendasi Perbaikan:**
  1. Ubah disk penyimpanan di `UploadController::store()` (`L188-189`) dari `'public'` menjadi `'local'` (`storage/app/private/uploads/excel`) dengan sanitasi `basename()` + karakter aman, dan resolve path via `Storage::disk('local')->path($storedPath)`.
  2. Tambahkan validasi `mimes:xlsx,xls` bersama `extensions:xlsx,xls`.
  3. Di blok `catch` (`L240-257`) setelah `DB::rollBack()`, hapus file yang baru diunggah via `Storage::disk('local')->delete($storedPath)`.
  4. Simpan nama file tersimpan secara konsisten agar `forceDelete()` dapat menghapus arsipnya secara akurat (dan saat rilis produksi kelak, pindahkan/bersihkan file lama yang terlanjur berada di `storage/app/public/uploads/`).
- **Test yang Harus Ditambahkan/Diperbarui:**
  - `Phase2gPrivateUploadStorageTest`: menggunakan `Storage::fake('local')` & `Storage::fake('public')`, pastikan upload `.xlsx` menulis ke `local` dan `public` tetap 0 file; pastikan rollback parsing menghapus file dari `local`; pastikan file non-Excel berkedok `.xlsx` ditolak `422`.

---

### [F-02B] Klarifikasi: Penyimpanan Berkas DBF pada `public` Disk
- **Status:** **`FALSE POSITIVE`** *(untuk klaim eksposur di disk `public`; **VERIFIED** hanya untuk mismatch path pembersihan di `forceDelete()` & sanitasi nama file)*
- **Severity / Prioritas:** **LOW / P3**
- **Path File & Nomor Baris Aktual:**
  - [app/Services/BrsDataProcessingService.php](file:///d:/laragon/www/sidatex/app/Services/BrsDataProcessingService.php#L604) (`L604`, `L721-730`)
  - [app/Http/Controllers/UploadController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/UploadController.php#L815-L825) (`L815-825`)
- **Potongan Kode Bukti:**
  ```php
  // app/Services/BrsDataProcessingService.php:721-729 (Sudah aman menggunakan disk 'local' -> storage/app/private)
  $permDir = "uploads/raw_{$sourceType}";
  $permFilename = "{$timestampPrefix}_{$originalFilename}";
  $moveSuccess = Storage::disk('local')->put($permRelativePath, Storage::disk('local')->get($stagingRelativePath));
  ```
- **Rekomendasi Perbaikan:** Pertahankan disk `'local'`, tambahkan `basename()` pada `$originalFilename` (`L723`), dan selaraskan `UploadController::forceDelete()` agar mencari direktori arsip pada disk `'local'` (`uploads/raw_distribusi`, `uploads/raw_neraca`, `uploads/excel`).

---

### [F-03] IDOR Baca & Tulis serta Ketiadaan RBAC pada Komentar Batch (`CommentController`)
- **Status:** **`VERIFIED`**
- **Severity / Prioritas:** **HIGH / P1**
- **Agen Pemverifikasi:** `security-auditor`, `independent-reviewer`, `laravel-implementer`
- **Path File & Nomor Baris Aktual:**
  - [routes/web.php](file:///d:/laragon/www/sidatex/routes/web.php#L88-L91) (`L88-91`)
  - [app/Http/Controllers/CommentController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/CommentController.php#L16-L133) (`getByBatch` `L16-43`, `store` `L46-100`, `destroy` `L106-133`)
- **Potongan Kode Bukti:**
  ```php
  // app/Http/Controllers/CommentController.php:16-22 (getByBatch tanpa cek role, status batch, maupun trash)
  public function getByBatch($batchId) {
      $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
      if (!$batch) { return response()->json(['success' => false, 'message' => 'Batch data tidak ditemukan.'], 404); }
      $comments = DB::table('comments')->...

  // app/Http/Controllers/CommentController.php:47-66 (Docblock menyatakan Super Admin & Viewer, tapi role tidak dicek!)
  public function store(Request $request) {
      $currentUser = Auth::user();
      ...
      $commentId = DB::table('comments')->insertGetId([...]); // Role 2 Pegawai bisa POST ke batch PRIVATE/DRAFT/Trash!
  ```
- **Risiko & Dampak:**
  - Pegawai BPS (Role 2 — seharusnya *read-only* untuk data `RILIS`) dapat melakukan enumerasi `GET /comments/batch/{id}` untuk membaca catatan telaah internal pada batch `PRIVATE`, `DRAFT`, dan `Trash`, sekaligus mengirim komentar baru via `POST /comments` ke batch mana pun.
  - Viewer Eksekutif (Role 3) juga dapat membaca dan menulis komentar pada batch `DRAFT` dan `Trash`.
  - Di `destroy()` (`L115-116`), perbandingan ketat `===` tanpa cast `(int)` rentan gagal bila PDO mengembalikan string ID.
- **Rekomendasi Perbaikan:**
  1. Tambahkan helper otorisasi terpusat di `CommentController` dengan cast `(int) $user->role_id`:
     - Batch yang sedang di `trash` (`restored_at IS NULL`): tolak non-SuperAdmin (`403`/`404`) dan blokir penulisan komentar baru untuk semua role.
     - **`getByBatch()` (Read):** Role 1 diizinkan pada batch aktif; Role 3 hanya `RILIS`/`PRIVATE`; Role 2 hanya `RILIS` (tolak `PRIVATE`/`DRAFT`/Trash dengan `403`).
     - **`store()` (Write):** Hanya Role 1 (batch aktif) dan Role 3 (`RILIS`/`PRIVATE`); **tolak Role 2 dengan HTTP `403`** sebelum validasi `exists:data_batches` agar tidak membuka enumerasi ID.
     - **`destroy()`:** Cast `(int)` pada `role_id` dan `user_id`, dan pastikan non-SuperAdmin hanya dapat menghapus miliknya sendiri pada batch non-Trash yang boleh diaksesnya.
- **Test yang Harus Ditambahkan/Diperbarui:**
  - `Phase2gCommentRbacTest`: matriks uji lengkap Role 1, 2, 3 × (`RILIS`, `PRIVATE`, `DRAFT`, `Trash`) untuk `GET`, `POST`, dan `DELETE /comments/*`.

---

### [F-04] Hardcoding `distribusi_transactions` pada `FilteringController`, `FastExcelExporter`, dan Modul Pendukung
- **Status:** **`VERIFIED`**
- **Severity / Prioritas:** **HIGH / P1**
- **Agen Pemverifikasi:** `database-guardian`, `independent-reviewer`, `statistics-auditor`, `laravel-implementer`, `frontend-reviewer`
- **Path File & Nomor Baris Aktual:**
  - [app/Http/Controllers/FilteringController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/FilteringController.php#L41-L60) (`L41-60`, `L81-135`, `L139-145`)
  - [app/Services/FastExcelExporter.php](file:///d:/laragon/www/sidatex/app/Services/FastExcelExporter.php#L135-L206) (`L135`, `L146-168`, `L182`, `L205-206`)
  - [resources/views/pages/filtering-data.blade.php](file:///d:/laragon/www/sidatex/resources/views/pages/filtering-data.blade.php#L181-L244) (`L181-244`, `L255`, `L278`, `L333-349`)
  - *(Hardcoding identik di file lain)*: [UploadController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/UploadController.php#L556-L561) (`update()` `L556-561`, `destroy()` `L627`), [PowerPointExportService.php](file:///d:/laragon/www/sidatex/app/Services/PowerPointExportService.php#L29) (`L29`), [StatisticsCalculationService.php](file:///d:/laragon/www/sidatex/app/Services/StatisticsCalculationService.php#L32) (`L32`).
- **Potongan Kode Bukti:**
  ```php
  // app/Http/Controllers/FilteringController.php:41-57
  $tipeSumber = strtolower((string) $request->input('tipe_sumber', 'distribusi'));
  ...
  $query = DB::table('distribusi_transactions')
      ->join('data_batches', 'distribusi_transactions.batch_id', '=', 'data_batches.batch_id')
      ...
      ->where('distribusi_transactions.tipe_sumber', $tipeSumber); // Selalu 0 baris saat tipe_sumber='neraca'!

  // app/Services/FastExcelExporter.php:146-147
  $query = DB::table('distribusi_transactions')
      ->where('distribusi_transactions.batch_id', $batchId) // Batch Neraca menghasilkan .xlsx kosong!
  ```
- **Risiko & Dampak:**
  - `GET /filtering-data/query?tipe_sumber=neraca` selalu mengembalikan 0 baris dan US\$ 0 (*silent false zero*), dan UI `pages/filtering-data.blade.php` bahkan tidak menyediakan pilihan `tipe_sumber`.
  - Ekspor Excel batch Neraca via `/manajemen-data/{batchId}/export` menghasilkan file `.xlsx` kosong (header saja, 0 baris transaksi).
  - Pada `FastExcelExporter`: header `M1` hardcoded `'FOB (US$)'` meski batch berjenis `Impor` (seharusnya `'CIF (US$)'`); baris unmapped Neraca (`hs_id = NULL`) kehilangan `hs_code_raw`; cast `(float)` dengan `sprintf('%.2F')` merusak presisi `DECIMAL(20,6)` pada sel Excel; dan terdapat label goto PHP tak terpakai `cJenis:` di `L182`.
  - Di `UploadController::update()` (`L557`), perubahan `jenis_data` pada batch Neraca tidak memperbarui `neraca_transactions`; di `destroy()` (`L627`), snapshot Trash mencatat `total_transaksi = 0`; di `PowerPointExportService` (`L29`), slide PPT batch Neraca menampilkan US\$ 0.
- **Rekomendasi Perbaikan:**
  1. Terapkan routing tabel dinamis `$txTable = ($tipeSumber === 'neraca') ? 'neraca_transactions' : 'distribusi_transactions'` dengan alias `t` di `FilteringController::query()`, `FastExcelExporter::exportBatchToPath()`, `UploadController::update()`, `UploadController::destroy()`, dan `PowerPointExportService::generateBrsSlideDeck()`.
  2. Khusus saat `$txTable === 'neraca_transactions'`, gunakan `COALESCE(master_hs.hs_code, t.hs_code_raw)` dan `COALESCE(master_hs.hs_desc, t.hs_desc_raw)` (dan filter `hs2` mencakup `LEFT(t.hs_code_raw, 2)`) agar kode HS *unmapped* tetap terbaca tanpa menyebabkan error kolom tak dikenal di `distribusi_transactions`.
  3. Di `FastExcelExporter`, buat header `M1` dinamis (`CIF (US$)` untuk Impor, `FOB (US$)` untuk Ekspor), hapus label `cJenis:`, dan tulis nilai numerik string berpresisi penuh ke `<v>` tanpa cast `(float)`.
  4. Di `resources/views/pages/filtering-data.blade.php`, tambahkan dropdown `Sumber Data (tipe_sumber)` berisi **hanya** `Distribusi (POD/KDPROV)` dan `Neraca (PROVORIG/KPPBC)` (tanpa opsi gabungan), perbaiki label dinamis FOB/CIF, dan perbaiki reset form + paginasi saat data kosong.
- **Test yang Harus Ditambahkan/Diperbarui:**
  - `Phase2gNeracaFilteringAndExportTest`: uji isolasi Distribusi vs Neraca pada `/filtering-data/query` (termasuk baris HS unmapped `hs_id = NULL`), uji RBAC & eksklusi Trash, uji isi XML `.xlsx` hasil `FastExcelExporter` untuk batch Neraca Ekspor & Impor (cek header `CIF (US$)` dan presisi 6 desimal).
  - Perkuat Test 10 pada `SourceIsolationAndAggregationRegressionTest.php`.

---

### [F-05] Hardcoding `distribusi_transactions` pada `TradeAggregationService` & Keterputusan Siklus Mart (`restore`, `forceDelete`, `destroy`, `toggleRelease`, `backfill`)
- **Status:** **`VERIFIED`** *(Dieskalasi ke **CRITICAL / P0**)*
- **Severity / Prioritas:** **CRITICAL / P0**
- **Agen Pemverifikasi:** `database-guardian`, `independent-reviewer`, `statistics-auditor`, `laravel-implementer`, `test-engineer`
- **Path File & Nomor Baris Aktual:**
  - [app/Services/TradeAggregationService.php](file:///d:/laragon/www/sidatex/app/Services/TradeAggregationService.php#L28-L85) (`L28-53`, `L56-63`, `L66-85`, `L125-140`, `L151-156`, `L235-262`)
  - [app/Http/Controllers/UploadController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/UploadController.php#L723-L735) (`restore` `L723-735`, `forceDelete` `L798-812`)
  - [app/Console/Commands/BackfillMonthlyTradeSummaries.php](file:///d:/laragon/www/sidatex/app/Console/Commands/BackfillMonthlyTradeSummaries.php#L15-L39) (`L15`, `L27-39`)
  - [app/Services/DistributionUploadService.php](file:///d:/laragon/www/sidatex/app/Services/DistributionUploadService.php#L344) (`L344`)
- **Potongan Kode Bukti:**
  ```php
  // app/Services/TradeAggregationService.php:43-53 & 66-84
  $deleteOriginQuery = DB::table('monthly_origin_trade_summaries'); // TANPA filter tipe_sumber!
  ...
  $targetSourceTable = 'distribusi_transactions';
  ...
  if (!$checkQuery->exists() && Schema::hasTable('transactions')) {
      $targetSourceTable = 'transactions'; // Fallback TERLARANG ke tabel arsip transactions!
  }
  // L125-140: Insert ke monthly_trade_summaries TANPA batch_id, status_visibilitas (default PRIVATE!), hs2, sektor, is_migas, bec_category!
  // L240 (recalculateForBatch): Hardcode DB::table('distribusi_transactions') meski $tipeSumber sudah dibaca di L238!
  ```
- **Risiko & Dampak:**
  - Memulihkan (`restore()`) atau menghapus permanen (`forceDelete()`) batch **Neraca** akan **menghapus** `monthly_trade_summaries` Neraca, **menghapus** `monthly_origin_trade_summaries` Distribusi pada periode sama, lalu membaca `distribusi_transactions` sehingga menyisipkan **0 baris** dan membiarkan `brs_indicator_summaries` kosong/basi.
  - Pada batch **Distribusi** berstatus `RILIS`, `TradeAggregationService` menimpa `monthly_trade_summaries` tanpa mengisi `batch_id` dan membiarkan `status_visibilitas` jatuh ke default `'PRIVATE'` (menyembunyikan data `RILIS`!), serta menghilangkan seluruh metadata BRS (`hs2`, `is_migas`, `sektor`, `bec_category`).
  - Saat batch terakhir periode dihapus, `L78-80` jatuh kembali ke tabel arsip lama `transactions` (melanggar `AGENTS.md` Seksi 2).
  - Blok `try/catch` di `UploadController::restore()` (`L732`) dan `forceDelete()` (`L809`) menelan eksepsi agregasi sehingga transaksi tetap di-commit dalam kondisi mart rusak.
- **Rekomendasi Perbaikan:**
  1. Jadikan `BrsAggregationService` sebagai satu-satunya mesin Two-Tier Mart: tambahkan metode `removeBatchAggregation(int $batchId)` (menghapus baris `monthly_trade_summaries` & `brs_indicator_summaries` milik batch serta menyegarkan `monthly_origin_trade_summaries` khusus Distribusi) dan dukung batch Excel yang memiliki `periode_tahun`/`periode_bulan = NULL` dengan membaca pasangan `(tahun, bulan)` unik dari tabel transaksinya.
  2. Di `UploadController`:
     - `restore()`: tolak dengan `409 Conflict` bila sudah ada batch aktif lain pada `(tipe_sumber, jenis_data, periode_tahun, periode_bulan)` yang sama; panggil `BrsAggregationService::aggregateBatch($batchId, $batch->status)` untuk `PRIVATE` maupun `RILIS` **tanpa** menelan eksepsi.
     - `destroy()` & `forceDelete()`: panggil `removeBatchAggregation($batchId)` secara atomik di dalam transaksi DB (dan bangun ulang hanya bila masih ada batch aktif lain pada periode sama).
  3. Di `TradeAggregationService`: hapus total fallback `transactions` (`L78-80`), gunakan tabel dinamis dari `$tipeSumber`, batasi `monthly_origin_trade_summaries` khusus `$tipeSumber === 'distribusi'` dengan `LEFT JOIN master_hs`, dan delegasikan ulang perhitungan ke `BrsAggregationService` agar tidak ada dua skema mart yang saling menimpa.
  4. Di `BackfillMonthlyTradeSummaries`: tambahkan opsi `{--sumber=all}` dan jalankan `BrsAggregationService::aggregateBatch()` per batch aktif (`PRIVATE`/`RILIS`, non-Trash).
- **Test yang Harus Ditambahkan/Diperbarui:**
  - `Phase2gBatchLifecycleAggregationTest`: uji penuh `destroy -> restore -> forceDelete` dan `toggleRelease` untuk Distribusi & Neraca (status `RILIS` dan `PRIVATE`), pastikan `monthly_origin_trade_summaries` Distribusi aman saat Neraca di-restore, dan pastikan `transactions` tidak pernah dibaca.
  - Perbarui `TradeAggregationAndActivityLogsTest` (`L20, L58`) agar tidak lagi membaca tabel arsip `transactions`.

---

### [F-06] Pengembalian `0.0` (dan `-100.0`) Ketika Periode Pembanding Kosong / Penyebut Nol (Pelanggaran Aturan 7)
- **Status:** **`VERIFIED`**
- **Severity / Prioritas:** **MEDIUM-HIGH / P1**
- **Agen Pemverifikasi:** `independent-reviewer`, `statistics-auditor`, `frontend-reviewer`, `laravel-implementer`, `test-engineer`
- **Path File & Nomor Baris Aktual:**
  - [app/Services/StatisticsCalculationService.php](file:///d:/laragon/www/sidatex/app/Services/StatisticsCalculationService.php#L90-L138) (`L90-96`, `L98-105`, `L108-125`, `L128-129`, `L138`)
  - [app/Services/BrsAggregationService.php](file:///d:/laragon/www/sidatex/app/Services/BrsAggregationService.php#L342) (`L342`, `L522-538`, `L558-562`)
  - [resources/views/dashboard.blade.php](file:///d:/laragon/www/sidatex/resources/views/dashboard.blade.php#L781-L802) (`L781-802`, `L1384-1388`)
- **Potongan Kode Bukti:**
  ```php
  // app/Services/StatisticsCalculationService.php:99, 125, 129, 138
  $mtom = 0.0;
  ...
  $qtoq = ($qPrevSum > 0) ? round((($qCurrentSum - $qPrevSum) / $qPrevSum) * 100, 2) : 0.0;
  $yony = ($valSameMonthLastYear > 0) ? round((($valLatest - $valSameMonthLastYear) / $valSameMonthLastYear) * 100, 2) : 0.0;
  $ctoc = ($cumPrev > 0) ? round((($cumCurrent - $cumPrev) / $cumPrev) * 100, 2) : 0.0;

  // app/Services/BrsAggregationService.php:528-530 & 342
  if (bccomp($prevVal, '0', 4) <= 0) {
      return (bccomp($currVal, '0', 4) > 0) ? null : 0.0; // 0/0 mengembalikan 0.0 alih-alih null!
  }
  $prevMonthUsd = $this->lookupHistoricalValue($sourceType, $transactionType, $tahun, $bulan - 1); // Jan -> bulan 0 -> selalu null!
  ```
- **Risiko & Dampak:**
  - Melanggar `AGENTS.md` Seksi 7: ketika data periode pembanding tidak tersedia atau penyebut nol, `StatisticsCalculationService` mengembalikan `0.0` (tampil `0%` pada kartu KPI dashboard, seolah perdagangan stagnan 0%), padahal JS `dashboard.blade.php:1385-1388` sudah siap merender `'—'` bila menerima `null`.
  - **Bug Tambahan (`-100.0%`):** Saat tahun terpilih kosong (`$totalFob == 0`), `$latestMonth` tetap bernilai `1`; jika tahun sebelumnya memiliki data Desember/Januari/Q4 `> 0`, sistem menampilkan pertumbuhan palsu **`-100%`** meski `has_data === false`.
  - **Bug Tambahan (Januari MoM di `BrsAggregationService`):** `$bulan - 1 = 0` tidak membungkus ke Desember `$tahun - 1`, sehingga MoM Januari di Tier-2 selalu `null`.
  - **Bug Tambahan (Initial SSR HTML):** `dashboard.blade.php:784, 789, 794, 799` menuliskan teks awal `0%` sebelum AJAX selesai (dan tetap `0%` bila fetch gagal).
- **Rekomendasi Perbaikan:**
  1. Di `StatisticsCalculationService`: lacak eksistensi periode secara eksplisit; bila tidak ada data tahun berjalan (`!$hasData`), kembalikan `null` untuk seluruh 4 pertumbuhan; kembalikan `null` (bukan `0.0`) setiap kali periode pembanding tidak ada atau penyebut `<= 0`; pertahankan `0.0` **hanya** saat `$curr === $prev > 0`; hitung dengan `bcmath`. *(Catatan metodologi QoQ kuartal parsial: jangan ubah konvensi BPS tanpa konfirmasi stakeholder — dokumentasikan untuk persetujuan BPS Riau.)*
  2. Di `BrsAggregationService`: ubah `calcGrowth()` agar selalu mengembalikan `null` bila `bccomp($prevVal, '0', 6) <= 0`; bungkus MoM Januari ke `($tahun - 1, 12)`; gunakan `round((float) ..., 2)` dari hasil scale 6 alih-alih truncating `bcsub(..., 2)`.
  3. Di `resources/views/dashboard.blade.php`: ubah teks awal `0%` (`L784, 789, 794, 799`) menjadi `—`, buat helper `formatGrowthCard()` dengan guard `null`/`undefined`/`NaN` + tooltip `"Periode pembanding tidak tersedia"`, dan tampilkan banner error di blok `.catch()` (`L1365`).
- **Test yang Harus Ditambahkan/Diperbarui:**
  - `Phase2gStatisticsCalculationTest`: uji periode tunggal -> semua 4 growth `assertNull`; uji tahun kosong dengan tahun lalu berisi -> semua 4 growth `assertNull` (bukan `-100.0`); uji perubahan datar riil ($1000 \to 1000$) -> `assertSame(0.0, ...)`; uji `BrsAggregationService::calcGrowth('0', '0') === null` dan MoM Januari vs Desember $Y-1$.

---

### [F-07] Ketidaksesuaian Presisi `DECIMAL(20,6)` pada Mart (`brs_indicator_summaries`, `monthly_trade_summaries`, & `BrsAggregationService`)
- **Status:** **`VERIFIED`** *(Seluruh DDL migrasi & kode BCMath telah diverifikasi langsung)*
- **Severity / Prioritas:** **HIGH / P1**
- **Agen Pemverifikasi:** `database-guardian`, `statistics-auditor`, `independent-reviewer`, `sidatex-orchestrator` (membaca langsung DDL migrasi `2026_11_10_064841` & `2026_11_16_000001`)
- **Path File & Nomor Baris Aktual:**
  - [database/migrations/2026_11_16_000001_enhance_trade_summaries_and_create_brs_indicators.php](file:///d:/laragon/www/sidatex/database/migrations/2026_11_16_000001_enhance_trade_summaries_and_create_brs_indicators.php#L77-L83) (`L77-83`)
  - [database/migrations/2026_11_10_064841_create_monthly_trade_summaries_table.php](file:///d:/laragon/www/sidatex/database/migrations/2026_11_10_064841_create_monthly_trade_summaries_table.php#L30-L31) (`L30-31`)
  - [app/Services/BrsAggregationService.php](file:///d:/laragon/www/sidatex/app/Services/BrsAggregationService.php#L164-L173) (`L164-173`, `L231-269`, `L528-552`, `L592-606`)
- **Potongan Kode Bukti:**
  ```php
  // database/migrations/2026_11_10_064841_create_monthly_trade_summaries_table.php:30-31
  $table->decimal('total_berat', 20, 3)->default(0); // Seharusnya DECIMAL(20,6)!
  $table->decimal('total_nilai', 20, 2)->default(0); // Seharusnya DECIMAL(20,6)!

  // database/migrations/2026_11_16_000001_enhance_trade_summaries_and_create_brs_indicators.php:77
  $table->decimal('nilai_usd', 20, 4)->default(0);   // Seharusnya DECIMAL(20,6)!

  // app/Services/BrsAggregationService.php:172-173 & 261-262
  $tier1Groups[$cubeKey]['total_nilai'] = bcadd($tier1Groups[$cubeKey]['total_nilai'], $nilaiStr, 2); // Truncate scale 2 per baris!
  $tier1Groups[$cubeKey]['total_berat'] = bcadd($tier1Groups[$cubeKey]['total_berat'], $beratStr, 3); // Truncate scale 3 per baris!
  ...
  $totalUsd = bcadd($totalUsd, $val, 4); // Scale 4 dari Tier-1 yang sudah terpotong!
  ```
- **Risiko & Dampak:**
  - Melanggar `AGENTS.md` Seksi 2 (`DECIMAL(20,6)`). PHP `bcadd(..., 2)` memotong (*floor toward zero*) pada **setiap baris transaksi**, menyebabkan penyimpangan ke bawah secara kumulatif (*systematic downward drift*) sehingga `SUM(transactions.nilai) != SUM(monthly_trade_summaries.total_nilai) != brs_indicator_summaries(TOTAL).nilai_usd`.
  - Kolom `growth_mtom/yoy/ctc` (`DECIMAL(6,2)`) juga rentan error *out-of-range* di MySQL strict mode apabila lonjakan dari basis kecil melebihi `9999.99%`.
- **Rekomendasi Perbaikan:**
  1. **JANGAN mengedit** migrasi yang sudah pernah dijalankan (`2026_11_10` maupun `2026_11_16`). Buat **migrasi aditif baru** (`upgrade_mart_decimal_precision_to_20_6.php`) setelah mengecek `MAX(ABS(...))` pada `sidatex_testing`:
     - Ubah `brs_indicator_summaries.nilai_usd` menjadi `DECIMAL(20,6)` (dan perlebar `growth_mtom`, `growth_yoy`, `growth_ctc` ke `DECIMAL(10,2)` nullable).
     - Ubah `monthly_trade_summaries.total_nilai` dan `total_berat` menjadi `DECIMAL(20,6)`.
  2. Di `BrsAggregationService.php`: gunakan `'0.000000'` dan scale `6` pada seluruh operasi `bcadd`, `bcsub`, `bccomp` di Tier-1 (`L164-173`) dan Tier-2 (`L231-515`, `L592-606`), dan lakukan pembulatan 2 desimal (`round(..., 2)`) **hanya** pada kolom presentasi akhir (`nilai_juta_usd`, `volume_ribu_ton`, `share_percentage`, `growth_*`).
- **Test yang Harus Ditambahkan/Diperbarui:**
  - `Phase2gBrsDecimalPrecisionTest`: verifikasi `information_schema.COLUMNS` pada `sidatex_testing` (`NUMERIC_PRECISION = 20, NUMERIC_SCALE = 6` untuk `brs_indicator_summaries.nilai_usd`, `monthly_trade_summaries.total_nilai`, dan `total_berat`) + uji rekonsiliasi 3-arah eksak 6 desimal (0 delta) pada ratusan baris berpecahan 6 desimal.

---

### [F-08] Rute Mati `/dashboard/visual` di `routes/web.php`
- **Status:** **`VERIFIED`**
- **Severity / Prioritas:** **LOW / P3**
- **Agen Pemverifikasi:** `independent-reviewer`, `frontend-reviewer`, `laravel-implementer`, `test-engineer`
- **Path File & Nomor Baris Aktual:**
  - [routes/web.php](file:///d:/laragon/www/sidatex/routes/web.php#L49-L52) (`L49-51`; plus `L52` `/dashboard/master` di luar middleware `superadmin`)
  - `resources/views/dashboard/visual.blade.php` & `.php` — **TIDAK DITEMUKAN**
- **Potongan Kode Bukti:**
  ```php
  // routes/web.php:49-52
  Route::get('/dashboard/visual', function () {
      return view('dashboard.visual');
  })->name('dashboard.visual');
  Route::get('/dashboard/master', [UploadController::class, 'index'])->name('dashboard.master');
  ```
- **Risiko & Dampak:** Mengakses `/dashboard/visual` memicu `InvalidArgumentException: View [dashboard.visual] not found` (HTTP 500). Tidak ada satu pun Blade view, sidebar, atau JS yang merujuk ke rute ini. Selain itu, `/dashboard/master` (`L52`) berada di luar middleware `superadmin` (saat ini tertolong pengecekan inline di `UploadController::index()`).
- **Rekomendasi Perbaikan:** Hapus rute `/dashboard/visual` (`L49-51`) setelah grep menyeluruh di `tests/` dan pindahkan `/dashboard/master` (`L52`) ke dalam grup `middleware('superadmin')` (atau hapus bila tidak terpakai).
- **Test yang Harus Ditambahkan/Diperbarui:**
  - `Phase2gRouteCleanupTest`: pastikan `Route::has('dashboard.visual') === false` dan `GET /dashboard/visual` menghasilkan `404` (bukan `500`) untuk Role 1, 2, dan 3.

---

### [F-09] Temuan Eskalasi Kritis: Urutan Eksekusi & Blacklist Guard PDO pada `tests/TestCase.php`
- **Status:** **`VERIFIED`** *(Dieskalasi ke **CRITICAL / P0** oleh `database-guardian` & `test-engineer`)*
- **Severity / Prioritas:** **CRITICAL / P0**
- **Path File & Nomor Baris Aktual:**
  - [tests/TestCase.php](file:///d:/laragon/www/sidatex/tests/TestCase.php#L15-L83) (`L15-21`, `L55-82`)
  - [vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/InteractsWithTestCaseLifecycle.php](file:///d:/laragon/www/sidatex/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/InteractsWithTestCaseLifecycle.php#L100-L106) (`L100-106`)
- **Potongan Kode Bukti:**
  ```php
  // tests/TestCase.php:15-21 (parent::setUp() menjalankan setUpTraits() SEBELUM guard ini!)
  protected function setUp(): void {
      parent::setUp();
      $this->enforceTestDatabaseSafety();
  }
  // tests/TestCase.php:71-82 (Layer 3 hanya cek blacklist, tidak cek SELECT DATABASE(), dan catch menelan error!)
  try {
      $realDb = DB::connection()->getDatabaseName();
      if (in_array(strtolower((string) $realDb), $forbiddenDbs, true)) { throw new \RuntimeException(...); }
  } catch (\Throwable $e) {
      if ($e instanceof \RuntimeException) { throw $e; }
  }
  ```
- **Risiko & Dampak:** Bila suatu saat sebuah test menggunakan trait `RefreshDatabase`/`DatabaseMigrations`, trait tersebut akan berjalan *sebelum* `enforceTestDatabaseSafety()`. Selain itu, Layer 3 memakai blacklist dan *fail-open* saat `PDOException` terjadi, bertentangan dengan `AGENTS.md` Seksi 4 (*strict whitelist* `=== 'sidatex_testing'`).
- **Rekomendasi Perbaikan:** Override `setUpTraits()` di `tests/TestCase.php` agar `enforceTestDatabaseSafety()` berjalan **sebelum** `parent::setUpTraits()`; ubah Layer 3 menjadi *strict whitelist* `strcasecmp((string)$realDb, 'sidatex_testing') === 0` ditambah verifikasi sesi MySQL riil `SELECT DATABASE() === 'sidatex_testing'` dengan perilaku *fail-closed* (hapus penelanan eksepsi).
- **Test yang Harus Ditambahkan/Diperbarui:** Perluas `tests/Unit/DatabaseSafetyGuardTest.php` dengan uji DB tak terdaftar (`sidatex_dev`), uji mismatch PDO runtime, uji *fail-closed*, dan uji urutan `setUpTraits()`.

---

### [F-10] Temuan Keamanan Sekunder: Path Traversal pada `forceDelete()` & Bypass Trash pada `export()`
- **Status:** **`VERIFIED`**
- **Severity / Prioritas:** **MEDIUM / P2**
- **Path File & Nomor Baris Aktual:**
  - [app/Http/Controllers/UploadController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/UploadController.php#L452-L462) (`export` `L452-462`, `update` `L537-553`, `forceDelete` `L814-825`)
- **Potongan Kode Bukti:**
  ```php
  // UploadController::export() L452-462: Tidak memeriksa apakah $batchId ada di tabel trash!
  // UploadController::update() L537-550: 'nama_file' => 'required|string|max:255' tanpa tolak '/', '\', '..'
  // UploadController::forceDelete() L815-824:
  $possiblePaths = [
      storage_path('app/public/uploads/raw_distribusi/' . $batch->nama_file),
      storage_path('app/public/uploads/excel/' . $batch->nama_file),
  ];
  foreach ($possiblePaths as $filePath) { if (file_exists($filePath)) { @unlink($filePath); } }
  ```
- **Risiko & Dampak:** (1) Pegawai/Viewer masih bisa mengunduh `.xlsx` batch `RILIS` yang sedang berada di Trash via URL langsung `/manajemen-data/{id}/export`. (2) Manipulasi `nama_file` (mis. `../../../../../.env`) pada `update()` lalu memicu `forceDelete()` berisiko menghapus file arbitrer server sebelum `DB::commit()`.
- **Rekomendasi Perbaikan:**
  1. Di `export()`: cek tabel `trash` (`whereNull('restored_at')`); tolak non-SuperAdmin (`403`/`404`) dan selaraskan izin ekspor Role 3 pada batch `PRIVATE`.
  2. Di `update()`: tolak karakter `/`, `\`, dan `..` pada validasi `nama_file`.
  3. Di `forceDelete()`: wajibkan batch berada di `trash`, pindahkan penghapusan file **setelah `DB::commit()`**, pastikan tidak dipakai batch lain, dan gunakan guard `basename()` + `realpath()` containment check pada direktori `local` (`app/private/uploads/...`) dan fallback legacy `public`.
- **Test yang Harus Ditambahkan/Diperbarui:** Uji penolakan `422` pada `nama_file` berkarakter traversal, uji sentinel file aman saat `forceDelete()`, dan uji penolakan `403`/`404` saat Pegawai/Viewer mengunduh batch di Trash.

---

### [F-11] Temuan UX & UI RBAC: Redirect Login Role 3 (403) & Kebocoran Tombol/Metadata UI
- **Status:** **`VERIFIED`**
- **Severity / Prioritas:** **LOW-MEDIUM / P2**
- **Path File & Nomor Baris Aktual:**
  - [app/Http/Controllers/Auth/LoginController.php](file:///d:/laragon/www/sidatex/app/Http/Controllers/Auth/LoginController.php#L64-L70) (`L64-70`)
  - [resources/views/manajemen-data.blade.php](file:///d:/laragon/www/sidatex/resources/views/manajemen-data.blade.php#L346-L351) (`L346-351`, `L404-407`, `L471-476`, `L512-544`)
  - [resources/views/dashboard.blade.php](file:///d:/laragon/www/sidatex/resources/views/dashboard.blade.php#L720-L722) (`L720-722`, `L753-755`)
- **Potongan Kode Bukti:**
  ```php
  // app/Http/Controllers/Auth/LoginController.php:64-66
  if ($user->role_id == 1 || $user->role_id == 3) {
      return redirect()->route('upload-data'); // Role 3 langsung kena 403 dari middleware superadmin!
  }
  ```
- **Risiko & Dampak:** Setiap login Viewer Eksekutif (Role 3) langsung berakhir pada halaman error 403. Di `manajemen-data.blade.php`, Pegawai/Viewer melihat tombol & angka `Trash Dokumen`, `Edit Dokumen`, dan `PowerPoint (.pptx)` (semua 403 saat diklik). Di `dashboard.blade.php`, Pegawai melihat `X Rilis / Y Batch` yang menyingkap jumlah batch `PRIVATE`/Trash.
- **Rekomendasi Perbaikan:** Ubah `LoginController` agar hanya `(int)$user->role_id === 1` diarahkan ke `upload-data` (Role 2 & 3 ke `dashboard`); bungkus tombol/modal khusus admin di `manajemen-data.blade.php` dengan `@if(Auth::user() && (int)Auth::user()->role_id === 1)` dan jangan isi `$totalTrash` untuk non-SuperAdmin di `UploadController::manajemen()`; tampilkan hanya `N Batch Rilis` bagi `!$canViewPrivate` di `dashboard.blade.php`.
- **Test yang Harus Ditambahkan/Diperbarui:** Uji login redirect Role 1 (`upload-data`), Role 2 (`dashboard`), Role 3 (`dashboard` -> `200`, bukan `403`), serta uji DOM non-leak pada `/manajemen-data` dan `/dashboard` untuk Role 2.

---

### [F-12] Temuan Keselamatan DB/Migrasi: `composer.json` `migrate --force` & Rollback `down()` `hs_id`
- **Status:** **`VERIFIED`**
- **Severity / Prioritas:** **MEDIUM / P2**
- **Path File & Nomor Baris Aktual:**
  - [composer.json](file:///d:/laragon/www/sidatex/composer.json#L38-L45) (`L38-45`, `L64-68`) & [.env.example](file:///d:/laragon/www/sidatex/.env.example#L23-L28) (`L26`)
  - [database/migrations/2026_11_16_000001_enhance_trade_summaries_and_create_brs_indicators.php](file:///d:/laragon/www/sidatex/database/migrations/2026_11_16_000001_enhance_trade_summaries_and_create_brs_indicators.php#L102-L122) (`L102-122`)
- **Potongan Kode Bukti:**
  ```json
  // composer.json:40-42 (+ .env.example:26 DB_DATABASE=sidatex)
  "@php -r \"file_exists('.env') || copy('.env.example', '.env');\"",
  "@php artisan migrate --force"
  ```
  ```php
  // 2026_11_16_000001...php:104-120 (MySQL implicit DDL commit!)
  Schema::dropIfExists('brs_indicator_summaries');
  ... // drop 9 kolom berhasil commit permanen
  $table->unsignedInteger('hs_id')->nullable(false)->change(); // CRASH di strict mode jika ada hs_id NULL!
  ```
- **Risiko & Dampak:** (1) `composer setup` dapat menyalin `DB_DATABASE=sidatex` dan memigrasi DB operasional secara paksa tanpa konfirmasi. (2) Rollback migrasi `2026_11_16_000001` saat terdapat baris Neraca unmapped (`hs_id IS NULL`) menyebabkan korupsi skema setengah jalan karena MySQL melakukan *implicit commit* tiap DDL.
- **Rekomendasi Perbaikan:** Hapus `migrate --force` dari `composer.json` (`setup` & `post-create-project-cmd`), ubah default `.env.example` ke `DB_DATABASE=sidatex_testing`, dan tambahkan pengecekan pre-flight di awal `down()` (sebelum DDL destruktif apa pun berjalan) yang menghentikan rollback secara bersih bila terdapat `hs_id IS NULL` (tanpa menghapus data unmapped).
- **Test yang Harus Ditambahkan/Diperbarui:** Uji statis pada `DatabaseSafetyGuardTest.php` terhadap `composer.json`/`.env.example`, dan uji rollback aman migrasi pada `sidatex_testing`.

---

## 3. TINGKAT RISIKO DAN PRIORITAS

| Prioritas | ID Temuan | Ringkasan | Tingkat Risiko | Alasan Pengurutan |
| :---: | :---: | :--- | :---: | :--- |
| **P0** | **F-09** | Hardening `tests/TestCase.php` (`setUpTraits()` + PDO whitelist `SELECT DATABASE()`) | **CRITICAL** | Prasyarat keselamatan absolut sebelum menjalankan satu pun test/migrasi Phase 2G pada `sidatex_testing`. |
| **P0** | **F-05** | `TradeAggregationService` & siklus mart (`destroy`, `restore`, `forceDelete`, `toggleRelease`, `backfill`) | **CRITICAL** | Kerusakan aktif pada data mart Neraca & Distribusi + fallback terlarang ke tabel `transactions`; prasyarat sebelum F-01 mengandalkan `status_visibilitas`. |
| **P0** | **F-01** | Kebocoran `PRIVATE` & `Trash` via `StatisticsCalculationService` | **CRITICAL** | Pelanggaran langsung kerahasiaan data pra-rilis BPS (Aturan 8 & 9) pada endpoint publik/Pegawai. |
| **P1** | **F-02A** | Penyimpanan Excel pada `public` disk + rollback unlink | **HIGH** | Risiko unduhan publik tanpa autentikasi atas data perdagangan mentah. |
| **P1** | **F-03** | IDOR baca & tulis + absennya RBAC pada `CommentController` | **HIGH** | Pegawai dapat membaca & menulis catatan verifikasi pada batch `PRIVATE`/`DRAFT`/Trash. |
| **P1** | **F-07** | Presisi `DECIMAL(20,6)` pada `brs_indicator_summaries`, `monthly_trade_summaries`, & BCMath scale 6 | **HIGH** | Drift pembulatan kumulatif ke bawah; gagal rekonsiliasi 3-arah dengan tabel transaksi dan Excel BRS. |
| **P1** | **F-04** | Hardcoding `distribusi_transactions` pada `FilteringController`, `FastExcelExporter`, UI filter, dll. | **HIGH** | Modul Filtering & Ekspor Excel lumpuh total (0 baris) untuk seluruh data Neraca. |
| **P1** | **F-06** | `0.0` (dan `-100.0`) alih-alih `null` pada periode pembanding kosong + MoM Januari | **MEDIUM-HIGH** | Pelanggaran metodologi statistik BPS (Aturan 7) yang menyesatkan pembaca dashboard. |
| **P2** | **F-10** | Path traversal `forceDelete()` & bypass Trash `export()` | **MEDIUM** | Risiko penghapusan berkas server & unduhan batch terhapus. |
| **P2** | **F-11** | Redirect login Role 3 (403) & kebocoran UI RBAC (`manajemen-data`, `dashboard`) | **LOW-MEDIUM** | Memutus alur login Viewer Eksekutif dan menyingkap metadata/tombol terlarang di UI. |
| **P2** | **F-12** | `composer.json` `migrate --force` & `down()` `hs_id` rollback safety | **MEDIUM** | Pengamanan operasional jangka panjang terhadap migrasi tak sengaja dan rollback parsial. |
| **P3** | **F-08, F-02B** | Hapus rute mati `/dashboard/visual`, guard `/dashboard/master`, hygiene `basename()` DBF | **LOW** | Pembersihan rute HTTP 500 & konsistensi arsip disk `'local'`. |

---

## 4. FILE YANG TERDAMPAK

| Kategori | Path File | Temuan Terkait |
| :--- | :--- | :--- |
| **Keselamatan Test & Lingkungan** | `tests/TestCase.php`, `tests/Unit/DatabaseSafetyGuardTest.php`, `composer.json`, `.env.example` | F-09, F-12 |
| **Database & Migrasi** | `database/migrations/<NEW>_upgrade_mart_decimal_precision_to_20_6.php` *(baru)*, `database/migrations/2026_11_16_000001_enhance_trade_summaries_and_create_brs_indicators.php` *(`up()` 100% immutable; hanya opsi pre-flight guard pada `down()` atau SOP rollback)* | F-07, F-12 |
| **Backend Services** | `app/Services/BrsAggregationService.php`, `app/Services/TradeAggregationService.php`, `app/Services/StatisticsCalculationService.php`, `app/Services/FastExcelExporter.php`, `app/Services/BrsDataProcessingService.php`, `app/Services/DistributionUploadService.php`, `app/Services/PowerPointExportService.php` | F-01, F-02B, F-04, F-05, F-06, F-07 |
| **Backend Controllers & Commands** | `app/Http/Controllers/UploadController.php`, `app/Http/Controllers/CommentController.php`, `app/Http/Controllers/FilteringController.php`, `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/ExcelPreviewController.php`, `app/Http/Controllers/Auth/LoginController.php`, `app/Console/Commands/BackfillMonthlyTradeSummaries.php` | F-01, F-02A, F-03, F-04, F-05, F-10, F-11 |
| **Routing** | `routes/web.php` | F-08 |
| **Frontend Blade Views** | `resources/views/dashboard.blade.php`, `resources/views/pages/filtering-data.blade.php`, `resources/views/manajemen-data.blade.php` | F-04, F-06, F-11 |
| **Automated Test Suites** | 7 file tes baru `tests/Feature/Phase2g*.php` + pembaruan `tests/Feature/TradeAggregationAndActivityLogsTest.php`, `tests/Feature/SourceIsolationAndAggregationRegressionTest.php`, `tests/Unit/DatabaseSafetyGuardTest.php` | F-01 s.d. F-12 |

---

## 5. RENCANA PERBAIKAN PER TAHAP

Implementasi dilakukan secara bertahap dengan gerbang verifikasi (*gate*) di akhir tiap tahap. Seluruh eksekusi wajib memverifikasi koneksi PDO runtime `SELECT DATABASE() === 'sidatex_testing'` sebelum operasi tulis apa pun.

### Tahap 0 — Gerbang Keselamatan Lingkungan & Database (`F-09`, `F-12a`)
- **Lingkup:**
  1. Perkeras `tests/TestCase.php`: pindahkan `enforceTestDatabaseSafety()` agar berjalan di `setUpTraits()` sebelum `parent::setUpTraits()`, ubah Layer 3 ke *strict whitelist* `=== 'sidatex_testing'` dengan kueri PDO riil `SELECT DATABASE()`, dan hilangkan blok *fail-open* `catch`.
  2. Hapus `migrate --force` / `migrate --graceful` dari `composer.json` dan ubah default `.env.example` ke `DB_DATABASE=sidatex_testing`.
  3. Lakukan inspeksi read-only singkat di `sidatex_testing` untuk memverifikasi nilai `status_visibilitas` (yang ber-default `'PRIVATE'` pada migrasi `2026_11_16_000001:23`) & `batch_id` pada baris historis `< 2026/07`, serta `MAX(ABS(total_nilai/total_berat))` pada `monthly_trade_summaries` — lalu tentukan penanganan eksplisit baris historis `batch_id IS NULL` sebelum Tahap 3.
- **Gate Kelulusan:** `php artisan test tests/Unit/DatabaseSafetyGuardTest.php` lulus 100% (0 gagal, 0 skip).

### Tahap 1 — Penutupan Celah Keamanan, RBAC & Rute (`F-02A`, `F-02B`, `F-03`, `F-08`, `F-10`, `F-11a`)
- **Lingkup:**
  1. `CommentController.php`: implementasi otorisasi peran (Role 1/2/3), visibilitas batch (`RILIS`/`PRIVATE`/`DRAFT`), dan pemblokiran Trash pada `getByBatch`, `store`, `destroy` (dengan `(int)` cast).
  2. `UploadController::store()` (jalur Excel): pindahkan disk ke `'local'` (`storage/app/private/uploads/excel`), tambahkan validasi `mimes:xlsx,xls`, sanitasi `basename()`, dan hapus file di blok `catch` saat rollback. Tambahkan `basename()` di `BrsDataProcessingService.php:723`.
  3. `UploadController::export()`, `update()`, `forceDelete()`: tambahkan guard Trash pada `export()`, blokir karakter path traversal pada `update()`, dan terapkan guard `basename()` + `realpath()` containment pasca-`DB::commit()` pada `forceDelete()` (mencakup `local` + fallback legacy `public`).
  4. `LoginController.php`: arahkan Role 3 (`Viewer Eksekutif`) ke `route('dashboard')` (hanya Role 1 ke `upload-data`).
  5. `routes/web.php`: hapus rute mati `/dashboard/visual` dan pindahkan `/dashboard/master` ke dalam middleware `superadmin`.
- **Gate Kelulusan:** `Phase2gCommentRbacTest`, `Phase2gPrivateUploadStorageTest`, `Phase2gRouteCleanupTest`, dan `RoleAuthorizationHardeningTest` lulus 100%.

### Tahap 2 — Integritas Two-Tier Mart, Presisi `DECIMAL(20,6)`, & Sinkronisasi Siklus Batch (`F-05`, `F-07`, `F-12b`)
- **Lingkup:**
  1. Buat migrasi aditif baru `upgrade_mart_decimal_precision_to_20_6.php` untuk mengubah `brs_indicator_summaries.nilai_usd` (`20,4 -> 20,6`), `growth_*` (`6,2 -> 10,2`), serta `monthly_trade_summaries.total_nilai` (`20,2 -> 20,6`) dan `total_berat` (`20,3 -> 20,6`). Bagian `up()` pada migrasi `2026_11_16_000001` **100% immutable (tidak diubah)**; tambahkan pre-flight guard hanya pada `down()` (atau dokumentasikan dalam SOP rollback bila preferensi strict immutability). Jalankan migrasi **hanya pada `sidatex_testing`** setelah verifikasi PDO.
  2. Perbarui `BrsAggregationService.php`: seragamkan scale `6` pada seluruh `bcadd`/`bcsub`/`bccomp` Tier-1 & Tier-2; tambahkan `removeBatchAggregation(int $batchId)`; perbaiki `lookupHistoricalValue()` (filter visibilitas, eksklusi Trash, dan rollover Januari -> Desember $Y-1$); perbaiki `calcGrowth()` (`prev <= 0` selalu `null` + `round(..., 2)`); tangani batch Excel multi-bulan ber-`periode_tahun = NULL` dengan menjaga keunikan `uq_brs_indicator_scope` agar tidak menimpa batch aktif lain pada `(tahun, bulan)` yang sama; dan pastikan penyegaran `monthly_origin_trade_summaries` hanya mengagregasi `distribusi_transactions` berstatus **`RILIS` non-Trash** (mencegah kebocoran `PRIVATE` melalui `/distribusi/metrics`).
  3. Perbarui `TradeAggregationService.php`: hapus fallback terlarang ke tabel `transactions`, terapkan routing tabel dinamis berdasarkan `$tipeSumber`, batasi `monthly_origin_trade_summaries` khusus `distribusi` berstatus `RILIS` non-Trash dengan `LEFT JOIN master_hs`, dan delegasikan pembangunan mart ke `BrsAggregationService`.
  4. Perbarui `UploadController.php` (`toggleRelease`, `destroy`, `restore`, `forceDelete`, `update`) serta `BackfillMonthlyTradeSummaries.php` dan `DistributionUploadService.php` agar seluruh mutasi batch menyinkronkan Tier-1 & Tier-2 secara atomik (tanpa menelan eksepsi; dengan guard `409 Conflict` saat restore bentrok periode).
- **Gate Kelulusan:** `Phase2gBrsDecimalPrecisionTest`, `Phase2gBatchLifecycleAggregationTest`, dan `TradeAggregationAndActivityLogsTest` (yang sudah dibersihkan dari tabel `transactions`) lulus 100%.

### Tahap 3 — Perbaikan `StatisticsCalculationService` & Paritas Distribusi/Neraca pada Modul Baca/Ekspor (`F-01`, `F-04`, `F-06`)
- **Lingkup:**
  1. Perbarui `StatisticsCalculationService.php`: terapkan parameter `array $allowedStatuses = ['RILIS']`, `LEFT JOIN data_batches` + eksklusi `trash` (aman bagi baris historis `batch_id IS NULL`), fallback `max('tahun')` dinamis (`neraca_transactions` vs `distribusi_transactions`), kembalikan `null` untuk periode pembanding kosong/nol dan saat tahun kosong (`!$hasData`), serta kalkulasi presisi `bcmath`.
  2. Perbarui `DashboardController.php` dan `ExcelPreviewController.php` (`L98-112, L545-559`): teruskan `$allowedStatuses` ke `getOfficialDashboardMetrics()` di `metrics()` & `data()`; kecualikan `trash` dan lindungi metadata `PRIVATE` di `index()`; terapkan filter `RILIS` & non-Trash pada kueri `monthly_trade_summaries` di `ExcelPreviewController` agar pratinjau historis tidak tercemar `PRIVATE`/`Trash`.
  3. Perbarui `FilteringController.php`, `FastExcelExporter.php`, dan `PowerPointExportService.php`: routing tabel dinamis (`distribusi_transactions` vs `neraca_transactions`), `COALESCE(master_hs.hs_code, t.hs_code_raw)` khusus Neraca, header dinamis `CIF (US$)` untuk Impor (termasuk label `TOTAL NILAI FOB/CIF` pada `PowerPointExportService.php:93`), hapus `cJenis:`, dan presisi penuh di `<v>` Excel.
- **Gate Kelulusan:** `Phase2gStatisticsCalculationTest`, `Phase2gNeracaFilteringAndExportTest`, `SourceIsolationAndAggregationRegressionTest`, dan `Phase2fSingleDashboardTest` lulus 100%.

### Tahap 4 — Penyelarasan Frontend Blade, Verifikasi Browser & Audit Rilis (`F-04`, `F-06`, `F-11b`)
- **Lingkup:**
  1. `resources/views/dashboard.blade.php`: ubah teks awal `0%` ke `—`, tambahkan helper `formatGrowthCard()` yang aman terhadap `null`/`undefined`/`NaN` beserta status error fetch, sembunyikan jumlah batch `PRIVATE` bagi `!$canViewPrivate`, dan tambahkan resize Chart.js pada `switchDashboardTab()`.
  2. `resources/views/pages/filtering-data.blade.php`: tambahkan dropdown `Sumber Data (tipe_sumber)` (`Distribusi` vs `Neraca`, tanpa gabungan), label dinamis FOB/CIF, perbaikan tombol Reset & reset paginasi kosong, serta badge `.badge-private`.
  3. `resources/views/manajemen-data.blade.php` & `UploadController::manajemen()`: bungkus tombol/modal khusus Super Admin (`Trash Dokumen`, `Edit Dokumen`, `PowerPoint`) dengan `@if(role_id === 1)` dan jangan kirim `$totalTrash` ke non-SuperAdmin.
  4. Jalankan **seluruh suite PHPUnit (`php artisan test`)** + verifikasi browser Desktop (`1440×900`), Tablet (`768×1024`), dan Mobile (`390×844`) pada `sidatex_testing`, dilanjutkan tinjauan independen akhir.
- **Gate Kelulusan:** Full PHPUnit suite lulus 100% (0 gagal, 0 error, 0 skip), 0 kontaminasi fixture tahun sentinel, database operasional `sidatex` terverifikasi 0 mutasi, dan *sign-off* dari `independent-reviewer`.

---

## 6. TEST CASE DAN KRITERIA KEBERHASILAN

### A. Strategi Isolasi Fixture Pengujian
- Gunakan **tahun sentinel sintetis (`2091`–`2095`)** agar tidak pernah bertabrakan dengan data referensi `2020`–`2026` di `sidatex_testing`; jangan gunakan `RefreshDatabase` (menjaga master data HS/Negara/Pelabuhan/Wilayah tetap utuh).
- Hapus hanya record milik test dalam `tearDown()`/`finally` berdasarkan daftar ID yang dibuat selama test.
- Gunakan `Storage::fake('local')` dan `Storage::fake('public')` untuk seluruh uji upload dan penghapusan berkas.
- Pilih akun uji secara eksplisit berdasarkan `role_id` (`1`, `2`, `3`), tidak pernah `User::first()`.

### B. Ringkasan Suite Test Baru & Diperbarui
| Suite PHPUnit | Target Temuan | Skenario Kunci (Positif & Negatif) |
| :--- | :---: | :--- |
| `tests/Unit/DatabaseSafetyGuardTest.php` *(update)* | F-09, F-12 | Whitelist `sidatex_testing` lolos; `sidatex`, `sidatex_staging`, `sidatex_dev`, mismatch PDO `SELECT DATABASE()`, dan *fail-closed* semuanya melempar `RuntimeException`; urutan `setUpTraits()` teruji; `composer.json`/`.env.example` bebas `migrate --force`/`sidatex`. |
| `tests/Feature/Phase2gStatisticsCalculationTest.php` *(baru)* | F-01, F-06 | Periode tunggal -> `mtom/qtoq/yony/ctoc === null`; tahun kosong + tahun lalu berisi -> semua `null` (bukan `-100.0`); perubahan riil 0% ($1000 \to 1000$) -> `assertSame(0.0)`; batch `PRIVATE`, `DRAFT`, dan `Trash` terisolasi mutlak dari Pegawai pada `/dashboard/metrics`, `/dashboard/data`, `/analisis-perdagangan/data`; batch hasil restore kembali masuk hitungan. |
| `tests/Feature/Phase2gPrivateUploadStorageTest.php` *(baru)* | F-02A, F-10 | Upload `.xlsx` masuk disk `local` (`public` kosong); gagal parsing menghapus file dari `local`; file berkedok `.xlsx` ditolak `422`; `nama_file` berpola `../` ditolak `422`; `forceDelete()` tidak menghapus sentinel file di luar direktori sah. |
| `tests/Feature/Phase2gCommentRbacTest.php` *(baru)* | F-03 | Guest -> `302`/`401`; Pegawai `POST /comments` -> `403`, `GET` batch `PRIVATE`/`DRAFT`/Trash -> `403`, `GET` `RILIS` -> `200`; Viewer `GET`/`POST` `RILIS`/`PRIVATE` -> `200`/`201`, `DRAFT`/Trash -> `403`; `DELETE` hanya pemilik atau Super Admin; Role 3 login -> redirect `/dashboard` (`200`). |
| `tests/Feature/Phase2gNeracaFilteringAndExportTest.php` *(baru)* | F-04, F-10 | `/filtering-data/query?tipe_sumber=neraca` mengembalikan baris Neraca saja (termasuk `hs_code_raw` unmapped) tanpa kebocoran Distribusi; Pegawai terfilter `RILIS` saja; `FastExcelExporter` batch Neraca mengekspor seluruh baris dengan `hs_code_raw` & header `CIF (US$)` untuk Impor; Pegawai/Viewer ditolak `403`/`404` mengunduh batch di Trash. |
| `tests/Feature/Phase2gBatchLifecycleAggregationTest.php` *(baru)* | F-05, F-01 | `toggleRelease` menyinkronkan `data_batches` + kedua tier mart; `destroy` membersihkan mart; `restore` membangun ulang kedua tier mart untuk Distribusi & Neraca (`RILIS` & `PRIVATE`) tanpa menghapus `monthly_origin_trade_summaries` Distribusi dan menolak kolisi periode (`409`); `forceDelete` menghapus tuntas tanpa membaca tabel `transactions`; idempotensi 2× run identik. |
| `tests/Feature/Phase2gBrsDecimalPrecisionTest.php` *(baru)* | F-07, F-06 | `information_schema.COLUMNS` membuktikan `DECIMAL(20,6)` pada `brs_indicator_summaries.nilai_usd` & `monthly_trade_summaries.total_nilai/total_berat`; agregasi 100+ transaksi 6 desimal memiliki 0.000000 delta antara transaksi mentah, Tier-1, dan Tier-2; MoM Januari membandingkan Desember $Y-1$; `calcGrowth('0','0') === null`. |
| `tests/Feature/Phase2gRouteCleanupTest.php` *(baru)* | F-08 | `Route::has('dashboard.visual') === false`; `GET /dashboard/visual` -> `404` (bukan `500`); `/dashboard`, `/distribusi/dashboard`, `/neraca/dashboard` tetap `200`. |
| `tests/Feature/TradeAggregationAndActivityLogsTest.php` & `SourceIsolationAndAggregationRegressionTest.php` *(update)* | F-05, F-04, F-06 | Bebaskan `TradeAggregationAndActivityLogsTest` dan Tests 1–5 & 7 `SourceIsolationAndAggregationRegressionTest` dari tabel arsip `transactions` / asersi tautologis; perkuat Test 10 (Neraca > 0 baris) & Test 14 (`assertNull` growth) tanpa melemahkan satu pun asersi lama. |

### C. Kriteria Keberhasilan (Definition of Done Phase 2G)
1. **Bukti PDO Runtime:** `DB::connection()->getDatabaseName() === 'sidatex_testing'` dan `SELECT DATABASE() === 'sidatex_testing'` sebelum dan sesudah pengujian; DB operasional `sidatex` terverifikasi 0 mutasi.
2. **Keamanan Migrasi:** Migrasi aditif `DECIMAL(20,6)` lulus `up()` dan `down()` di `sidatex_testing` tanpa kehilangan data; `information_schema.COLUMNS` mengonfirmasi `(20,6)`.
3. **100% Lulus PHPUnit:** Seluruh suite baru Phase 2G + seluruh suite regresi + full suite `php artisan test` lulus dengan **exit code 0, 0 failures, 0 errors, 0 skips**.
4. **Verifikasi Browser (Desktop, Tablet, Mobile):** Kartu pertumbuhan menampilkan `—` (tidak pernah `0%` atau `undefined%`) saat pembanding kosong; `/filtering-data` dapat beralih Distribusi vs Neraca secara bersih; Pegawai tidak melihat tombol `Trash`/`Edit`/`PowerPoint` maupun hitungan batch `PRIVATE`; Role 3 login langsung mendarat di `/dashboard` (`200`).
5. **Audit Akhir:** Seluruh diff dan bukti eksekusi ditinjau ulang dan disetujui oleh `independent-reviewer`.

---

## 7. RISIKO REGRESI & MITIGASI

| # | Risiko Regresi | Pemicu | Strategi Mitigasi |
| :---: | :--- | :--- | :--- |
| **1** | **Hilangnya data historis `< 2026/07` (mematahkan Tests 12 & 13 `SourceIsolationAndAggregationRegressionTest`)** | Menggunakan `INNER JOIN data_batches` pada `StatisticsCalculationService` padahal baris historis pra-batch di `monthly_trade_summaries` berpotensi memiliki `batch_id = NULL`. | Gunakan `LEFT JOIN data_batches` + verifikasi read-only isi aktual `batch_id` & `status_visibilitas` baris historis di `sidatex_testing` pada Tahap 0 sebelum mengubah query. |
| **2** | **Kegagalan `BrsAggregationService::aggregateBatch()` pada batch Excel lama (`periode_tahun = NULL`)** | `UploadController::store()` jalur Excel tidak mengisi `periode_tahun`/`periode_bulan`; cast `(int) null -> 0` akan menulis baris mart `tahun=0, bulan=0`. | Tambahkan deteksi pasangan `(tahun, bulan)` unik dari tabel transaksi di `aggregateBatch()` bila metadata periode batch bernilai `NULL`, dan uji khusus dengan batch Excel. |
| **3** | **Penimpaan diam-diam data mart periode aktif saat `restore()` batch lama** | `BrsAggregationService::aggregateBatch()` menghapus mart per `(tipe_sumber, jenis_transaksi, tahun, bulan)` sebelum menulis ulang. | Tambahkan pengecekan kolisi batch aktif pada periode sama di `UploadController::restore()` (kembalikan `409 Conflict` jika sudah ada batch pengganti aktif). |
| **4** | **Error kolom tak dikenal (`hs_code_raw`) pada tabel `distribusi_transactions`** | Menjalankan `COALESCE(master_hs.hs_code, t.hs_code_raw)` tanpa membedakan `$txTable`. | Gunakan ekspresi `hs_code_raw`/`hs_desc_raw` **hanya** saat `$txTable === 'neraca_transactions'`. |
| **5** | **Kegagalan tes unit lama akibat *fail-closed* PDO di `TestCase.php`** | Pengujian unit murni gagal bila MySQL `sidatex_testing` tidak berjalan. | Pastikan layanan MySQL Laragon aktif sebelum menjalankan PHPUnit; pisahkan unit test murni non-Laravel bila kelak diperlukan tanpa melonggarkan guard `Tests\TestCase`. |
| **6** | **Kegagalan `TradeAggregationAndActivityLogsTest` (`L20, L58`)** | Test lama membaca tabel arsip `transactions` yang akan dihapus fallback-nya. | Tulis ulang test menggunakan fixture terisolasi di `distribusi_transactions` & `neraca_transactions` pada tahun sentinel `2095` (tanpa melemahkan tujuan pengujian idempotensi). |
| **7** | **Asersi lama atau JS pihak lain yang mengasumsikan `mtom/qtoq/yony/ctoc` selalu float (bukan `null`)** | Perubahan `0.0 -> null` pada `StatisticsCalculationService`. | Terverifikasi `dashboard.blade.php` sudah mendukung `null` dan `analisis-perdagangan.blade.php` tidak merender `res.growth`; tambahkan helper JS anti-`undefined`/`NaN` dan perbarui test yang secara keliru mengizinkan `0.0`. |
| **8** | **File Excel lama di `storage/app/public/uploads/excel/` tak terhapus saat `forceDelete()`** | Memindahkan upload baru ke disk `local` (`app/private`) tanpa fallback pembersihan direktori lama. | Sertakan direktori legacy `public` dalam allowlist ber-guard `realpath()` di `forceDelete()`, dan rencanakan pemindahan berkas fisik lama secara terpisah di luar scope testing. |
| **9** | **Perubahan perilaku kebijakan ekspor Excel `PRIVATE` bagi Viewer Eksekutif (Role 3)** | `manajemen-data.blade.php` menampilkan tombol Ekspor untuk Role 3 pada batch `PRIVATE`, sedangkan `UploadController::export()` saat ini mengembalikan `403`. | Pastikan keputusan pemangku kepentingan secara eksplisit sebelum Tahap 1/4: apakah Role 3 diizinkan mengunduh Excel `PRIVATE` atau tombol Ekspor disembunyikan untuk batch non-`RILIS` bagi Role 3. |
| **10** | **Data mart lama tetap terpotong 2/3/4 desimal setelah migrasi `DECIMAL(20,6)`** | Memperlebar kolom DB tidak otomatis mengembalikan digit ke-3 s.d. ke-6 yang sebelumnya sudah terpotong. | Jalankan perintah backfill baru (`artisan trade:backfill-aggregates --sumber=all`) pada `sidatex_testing` pasca-Tahap 2; untuk operasional `sidatex`, hanya dilakukan lewat rencana deployment terpisah dengan persetujuan tertulis pengguna. |

---

## 8. DAFTAR HAL YANG MASIH BELUM DAPAT DIVERIFIKASI (`NOT VERIFIED`)

Sesuai aturan *strict read-only* (tanpa koneksi database live, tanpa modifikasi file, tanpa eksekusi PHPUnit/browser), hal-hal berikut belum dapat diverifikasi dan wajib dicek pada **Tahap 0 (Pre-flight)** sebelum mengubah kode:

1. **Koneksi PDO Runtime & Status Baris Historis `< 2026/07` di `monthly_trade_summaries` (`sidatex_testing`):**
   - Belum diverifikasi secara live apakah baris historis (`pelabuhan_key = 0`) memiliki `batch_id = NULL` atau nilai `status_visibilitas` tertentu, serta apakah Batch 38 di `sidatex_testing` berstatus `RILIS` atau `PRIVATE` (krusial untuk menjaga Tests 12 & 13 `SourceIsolationAndAggregationRegressionTest`).
2. **Skema Live MySQL (`SHOW CREATE TABLE`) & Nilai Maksimum `monthly_trade_summaries` / `brs_indicator_summaries`:**
   - DDL migrasi telah diverifikasi dari kode sumber (`2026_11_10` & `2026_11_16`), namun struktur tabel live aktual pada `sidatex_testing` (indeks, foreign key pada `hs_id`, dan `MAX(ABS(total_nilai/total_berat))`) belum diinspeksi langsung melalui MySQL.
3. **Keberadaan Symlink `public/storage` & File Lama di `storage/app/public/uploads/`:**
   - Belum diverifikasi secara fisik pada filesystem server apakah symlink `public/storage` aktif dan berapa file Excel lama yang saat ini berada di `storage/app/public/uploads/excel/`.
4. **Grep Menyeluruh di Seluruh `tests/` & `app/Console/`:**
   - Pemanggil tambahan potensial dari `TradeAggregationService` dan referensi `dashboard.visual` di seluruh subdirektori tes perlu dikonfirmasi sekali lagi dengan pencarian teks penuh sebelum menghapus rute/metode.
5. **Keputusan Kebijakan Bisnis BPS pada 2 Detail Non-Teknis:**
   - (a) Apakah **QoQ pada bulan non-akhir triwulan** (mis. bulan 4 dibandingkan total Q1 3 bulan) tetap dipertahankan atau ditandai `null`/parsial sesuai konvensi BRS BPS Provinsi Riau?
   - (b) Apakah **Viewer Eksekutif (Role 3)** diperbolehkan mengunduh raw Excel `.xlsx` batch `PRIVATE` atau hanya melihatnya di Single Dashboard?
6. **Perilaku Visual Browser Chart.js Pasca Perpindahan Tab & Eksekusi PHPUnit Full Suite:**
   - Hanya dapat diverifikasi secara nyata pada Tahap 4 setelah implementasi disetujui dan dijalankan pada `sidatex_testing`.

---

## 9. HASIL TINJAUAN `independent-reviewer`

> **Verdict:** **`APPROVED WITH MINOR NOTES`** (ditinjau oleh `independent-reviewer` dalam mode *strict read-only*, 18 November 2026).  
> Seluruh 8 bagian wajib lengkap, seluruh 8 temuan target (+ 4 temuan eskalasi) terverifikasi akurat terhadap kode sumber tanpa klaim fabrikasi, urutan tahapan (Tahap 0–4) dan proteksi database operasional `sidatex` sudah solid. Implementasi Phase 2G pada `sidatex_testing` **diizinkan dimulai setelah persetujuan pengguna**, dengan 5 catatan minor yang telah diintegrasikan ke dalam Tahap 0–4 rencana ini:
> 1. `app/Http/Controllers/ExcelPreviewController.php` (`L98-112, L545-559`) ditambahkan ke lingkup Tahap 3 agar pratinjau historis mengecualikan `PRIVATE` & `Trash`.
> 2. `monthly_origin_trade_summaries` pada Tahap 2 dibatasi hanya mengagregasi `distribusi_transactions` berstatus `RILIS` non-Trash (mencegah kebocoran `PRIVATE` lewat `/distribusi/metrics`).
> 3. Pada Tahap 0 pre-flight, verifikasi nilai `status_visibilitas` (yang ber-default `'PRIVATE'` pada migrasi `2026_11_16_000001:23`) bagi baris historis `batch_id IS NULL` `< 2026/07` dan tetapkan penanganan eksplisit sebelum Tahap 3.
> 4. Ditegaskan bahwa `up()` migrasi `2026_11_16_000001` tidak diubah sama sekali (hanya opsi pre-flight guard pada `down()`, atau pindahkan ke SOP rollback), dan agregasi batch Excel multi-bulan di `BrsAggregationService` wajib menjaga keunikan `uq_brs_indicator_scope` agar tidak menimpa batch aktif lain pada `(tahun, bulan)` yang sama.
> 5. Referensi tabel lama `transactions` & asersi tautologis pada Tests 1–5 & 7 `SourceIsolationAndAggregationRegressionTest.php` serta label hardcoded `TOTAL NILAI FOB` pada `PowerPointExportService.php:93` dicatat untuk diperbaiki pada Tahap 3/4.
>
> **Gerbang Akhir Implementasi:** Seluruh hasil implementasi, output PHPUnit, bukti PDO `SELECT DATABASE() === 'sidatex_testing'`, dan verifikasi nol-mutasi `sidatex` wajib ditinjau ulang oleh `independent-reviewer` sebelum Phase 2G dinyatakan selesai.
