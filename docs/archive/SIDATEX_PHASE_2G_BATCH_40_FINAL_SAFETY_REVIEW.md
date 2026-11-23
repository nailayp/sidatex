# SIDATEX — PHASE 2G: BATCH #40 FINAL SAFETY REVIEW (READ-ONLY)

> **Status Dokumen:** FINAL — TELAH DI-REVIEW 3 REVIEWER INDEPENDEN (`database-guardian: PASS`, `statistics-auditor: PASS`, `independent-reviewer: APPROVED WITH MINOR NOTES` — SELURUH CATATAN HARDENING TELAH DIINTEGRASIKAN; 100% READ-ONLY — ZERO MUTASI DATABASE, ZERO PERUBAHAN KODE PRODUKSI, BELUM ADA EKSEKUSI REMEDIASI)  
> **Tanggal Audit:** 18 November 2026  
> **Dokumen Utama yang Diperkeras:** `SIDATEX_PHASE_2G_BATCH_40_REMEDIATION_PLAN.md` (**Opsi R1** tetap dipertahankan: `16` pelabuhan, `809` transaksi, `797` cube, DBF FOB `US$ 2,218,155,995.502700`, Netto `2,352,083,988.620000 Kg`, status **`PRIVATE`** — 0 perubahan pada keputusan statistik maupun nilai sumber)  
> **Verifikasi Live PDO Testing:** `SELECT DATABASE() === 'sidatex_testing'` (MySQL `8.0.30`, InnoDB)  
> **Proteksi Database Operasional (`sidatex`):** 100% TIDAK DISENTUH (`data_batches = 3`, `distribusi_transactions = 2557`, `neraca_transactions = 0`, `monthly_trade_summaries = 34847`, `monthly_origin_trade_summaries = 189`, `migrations = 26`, `brs_indicator_summaries = NOT FOUND`, `data_batches.MAX(updated_at) = 2026-11-13 07:38:33`, `monthly_trade_summaries.MAX(updated_at) = 2026-11-12 07:06:16`)

---

## Ringkasan Temuan Final Safety Review

Pemeriksaan keselamatan akhir secara **100% read-only** terhadap rencana eksekusi Opsi R1 mengungkap **lima celah operasional kritis** yang kini telah ditutup pada `SIDATEX_PHASE_2G_BATCH_40_REMEDIATION_PLAN.md` (`§7.2–§7.3`):

| # | Area Safety Review | Temuan Faktual (Read-Only) | Pengamanan yang Ditetapkan |
|---|---|---|---|
| **1** | **Backup & Restore Windows** | Shell aktual adalah **Windows PowerShell `5.1.26100.3624`** (`Desktop`); `mysqldump`/`mysql` **tidak ada di `PATH`**; operator `>` pada PS 5.1 menulis **UTF-16 LE BOM** (merusak SQL dump) dan `<` adalah **syntax error** | Path absolut Laragon `D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe` + `--result-file` + `--default-character-set=utf8mb4` + validasi 6 lapis (exit code, ukuran, SHA-256, 4-byte hex `2d2d204d`, trailer `-- Dump completed on`, 6 marker `CREATE TABLE`) + restore PS 5.1 aman via `mysql.exe -e "SOURCE ..."` atau `cmd.exe /c` dengan outer-quote aman |
| **2** | **Idempotensi & Concurrency** | `SHOW INDEX FROM data_batches` membuktikan **0 indeks** pada `(tipe_sumber, jenis_data, periode_tahun, periode_bulan)` (hanya `PRIMARY`, `uploaded_by`, `released_by`). `lockForUpdate()` saat 0 baris rawan deadlock (`REPEATABLE READ`) atau tidak mengunci apa pun (`READ COMMITTED`) | **MySQL Advisory Lock** `GET_LOCK('sidatex:remediate:distribusi:Ekspor:2026:08', 10)` (terverifikasi `IS_FREE_LOCK = 1`, **tanpa migration/unique constraint baru**) + `lockForUpdate()` defense-in-depth + **Guard Idempotensi 5-Lapis** (status `RILIS` -> HARD ABORT; jumlah/canonical SHA-256 `028082b5...` beda -> HARD ABORT) |
| **3** | **Konsistensi File & Database** | Disk `'local'` = `storage/app/private` (`'throw' => false`), `public/storage` symlink = `False`; penulisan arsip tanpa koordinasi rollback berisiko meninggalkan file yatim (ditemukan ~28 file arsip uji `*_14_Atap_ekspor_2608_POD_based.DBF` sisa pengujian lama) | Pola 2-fase: staging di `temp_staging/remediate_{uuid}.dbf` → pre-commit assertions → tulis arsip privat `uploads/raw_distribusi/{permFilename}` (cek eksplisit `$putOk === true` + verifikasi ukuran & SHA-256) → `DB::commit()`; `catch` cek `DB::transactionLevel() > 0` + rollback + hapus arsip parsial; `finally` terisolasi `try/catch` hapus staging & `RELEASE_LOCK()` |
| **4** | **Audit Origin Mart & Endpoint** | Audit penuh `monthly_origin_trade_summaries`: total **`58` baris** (HANYA `2026-08 ekspor`, **`0` artefak periode lain**). Audit `/distribusi/metrics`: middleware `auth` saja (Role 2 Pegawai bisa akses); 7 metrik terisolasi via `getReleasedBatchIds()`, tetapi `getOriginDistribution()` membaca `monthly_origin_trade_summaries` langsung tanpa cek visibilitas → **kebocoran aktif `O2` ke Role 2 terkonfirmasi** | Opsi R1 (tetap `PRIVATE`) otomatis menutup kebocoran `O2` (`58 -> 0`) tanpa menyentuh periode lain dan tanpa mengubah kode endpoint pada tahap ini; risiko struktural `getOriginDistribution()`, `DistributionStatisticsService`, & ketidakselarasan kode Dumai `1473` vs `1472` dicatat untuk Phase 2G.3 |
| **5** | **Perlindungan Batch `#71`** | Snapshot live `#71` direkam lengkap: `status = 'PRIVATE'`, `841` tx (`15679..16519`), `SUM(nilai) = 2,301,809,204.587000`, `SUM(berat) = 2,553,373,143.520000`, raw SHA-256 `ac739c0e484c711e4f1480eef439033c03b6d0069e9e64ea32ddb6e7f388f576`, `831` Tier-1, `47` Tier-2 (`MoM 3.77`, `YoY 45.04`, `C-to-C 6.96`) | Pre-check `status === 'PRIVATE'` sebelum refresh + assertion pasca-refresh bahwa `batch_id`, `status`, `updated_at` `data_batches#71`, serta seluruh `841` transaksi & raw SHA-256 **100% imutabel**; hanya ringkasan & indikator pertumbuhan yang diperbarui |

---

## 1. Backup dan Restore Windows (PowerShell 5.1 Hardening)

### 1.1 Verifikasi Lingkungan Aktual
Hasil pemeriksaan langsung di host Windows:
- **PowerShell:** `PSVersion = 5.1.26100.3624`, `PSEdition = Desktop`, `BuildVersion = 10.0.26100.3624`, `CLRVersion = 4.0.30319.42000`.
- **Status `PATH`:** `mysqldump` dan `mysql` **tidak terdaftar di `PATH`** (`CommandNotFoundException`).
- **Path Binary Aktual (Laragon MySQL 8.0.30):**
  - `D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe` (`mysqldump Ver 8.0.30 for Win64 on x86_64`)
  - `D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe` (`mysql Ver 8.0.30 for Win64 on x86_64`)
- **Status `.gitignore`:** Direktori `/storage/backups/` **belum tercantum** pada `.gitignore` (saat modifikasi file diizinkan nanti, tambahkan `/storage/backups/` ke `.gitignore` dan pastikan `git status` tidak pernah men-stage dump `.sql`).
- **Verifikasi Baseline Backup Sebelumnya (`sidatex_testing_pre_2g2b_20261118.sql`):** `13,896,003` bytes, 4 byte pertama hex `2d2d204d` (`-- M`, UTF-8 murni tanpa BOM), header `-- MySQL dump 10.13 Distrib 8.0.30, for Win64 (x86_64)`, trailer `-- Dump completed on 2026-11-18 10:36:31`.

### 1.2 Mengapa Redirection `>` dan `<` Berbahaya di PowerShell 5.1
1. **Operator `>` (Backup):** Di Windows PowerShell 5.1, `mysqldump ... > file.sql` menangkap stdout sebagai teks dan menuliskannya dengan encoding **UTF-16 LE + BOM (`fffe`)**. Dump UTF-16 LE menyebabkan kegagalan restore atau korupsi karakter pada `mysql.exe`.
2. **Operator `<` (Restore):** Di Windows PowerShell 5.1, `mysql ... < file.sql` langsung gagal dengan `ParserError: The '<' operator is reserved for future use`.
3. **Piping `Get-Content file.sql | mysql`:** Membaca seluruh file ke memori sebagai array string PowerShell dan dapat mengubah encoding/newline — **dilarang** untuk dump produksi/testing.

### 1.3 Prosedur Backup Aman (`--result-file`) & Validasi 6-Lapis
```powershell
$ErrorActionPreference = "Stop"
$dumpExe = "D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe"
$bkpDir  = "D:\laragon\www\sidatex\storage\backups"
$bkpPath = "$bkpDir\sidatex_testing_pre_batch40_YYYYMMDD.sql"

if (-not (Test-Path -LiteralPath $dumpExe)) { throw "mysqldump.exe tidak ditemukan: $dumpExe" }
if (-not (Test-Path -LiteralPath $bkpDir))  { New-Item -ItemType Directory -Path $bkpDir -Force | Out-Null }

# --result-file menulis langsung byte UTF-8 dari mysqldump.exe tanpa melewati PowerShell stdout!
# Guard ErrorActionPreference="Continue" sementara agar benign native stderr di PS 5.1 tidak memicu NativeCommandError semu;
# kegagalan tetap ditangkap mutlak via $LASTEXITCODE -ne 0.
$prevEap = $ErrorActionPreference
$ErrorActionPreference = "Continue"
& $dumpExe `
    --single-transaction `
    --routines=0 `
    --triggers=0 `
    --set-gtid-purged=OFF `
    --default-character-set=utf8mb4 `
    -h 127.0.0.1 -P 3306 -u root `
    --result-file="$bkpPath" `
    sidatex_testing
$dumpExit = $LASTEXITCODE
$ErrorActionPreference = $prevEap

# Lapis 1: Exit code wajib 0
if ($dumpExit -ne 0) { throw "BACKUP GAGAL: mysqldump exit code = $dumpExit" }

# Lapis 2: File ada dan ukuran >= 10 MB (baseline ~13.9 MB)
$item = Get-Item -LiteralPath $bkpPath
if ($item.Length -lt 10000000) { throw "BACKUP TIDAK VALID: ukuran file terlalu kecil ($($item.Length) bytes)" }

# Lapis 3: SHA-256 checksum
$sha256 = (Get-FileHash -LiteralPath $bkpPath -Algorithm SHA256).Hash

# Lapis 4: Cek 4 byte pertama = 2d2d204d ("-- M"), bebas UTF-16 BOM (fffe/feff) & UTF-8 BOM (efbbbf)
$fs = [System.IO.File]::OpenRead($bkpPath)
try {
    $buf = New-Object byte[] 4
    [void]$fs.Read($buf, 0, 4)
} finally {
    $fs.Dispose()
}
$hex4 = ($buf | ForEach-Object { $_.ToString("x2") }) -join ""
if ($hex4 -ne "2d2d204d") { throw "BACKUP CORRUPT/ENCODING SALAH: 4 byte awal = $hex4 (harus 2d2d204d)" }

# Lapis 5: Cek header & trailer lengkap (dump tidak terpotong di tengah jalan)
$head = Get-Content -LiteralPath $bkpPath -TotalCount 3 -Encoding UTF8
$tail = Get-Content -LiteralPath $bkpPath -Tail 5 -Encoding UTF8
if (-not ($head[0] -like "-- MySQL dump*")) { throw "HEADER DUMP TIDAK VALID: $($head[0])" }
if (-not (($tail -join "`n") -match "-- Dump completed on ")) { throw "TRAILER DUMP HILANG (dump terpotong!)" }

# Lapis 6: Cek keberadaan 6 tabel inti SIDATEX di dalam dump
$requiredTables = @("data_batches","distribusi_transactions","neraca_transactions","monthly_trade_summaries","monthly_origin_trade_summaries","brs_indicator_summaries")
foreach ($tbl in $requiredTables) {
    if (-not (Select-String -LiteralPath $bkpPath -Pattern "CREATE TABLE ``$tbl``" -SimpleMatch -Quiet)) {
        throw "TABEL INTI HILANG DARI DUMP: $tbl"
    }
}
Write-Output "BACKUP VALID | Size=$($item.Length) bytes | SHA256=$sha256 | Hex4=$hex4"
```

### 1.4 Prosedur Restore Kompatibel PowerShell 5.1
> **Peringatan Mutlak:** Pastikan target database adalah **`sidatex_testing`**, **DILARANG** diarahkan ke database operasional `sidatex`.

- **Metode A (Direkomendasikan — Native `mysql.exe` `SOURCE` dengan forward slash, tanpa redirection & tanpa memori buffer PowerShell):**
  ```powershell
  $mysqlExe = "D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe"
  & $mysqlExe --default-character-set=utf8mb4 -h 127.0.0.1 -P 3306 -u root sidatex_testing -e "SOURCE D:/laragon/www/sidatex/storage/backups/sidatex_testing_pre_batch40_YYYYMMDD.sql"
  if ($LASTEXITCODE -ne 0) { throw "RESTORE GAGAL: mysql exit code = $LASTEXITCODE" }
  ```
- **Metode B (Alternatif — Binary Stdin Stream via `cmd.exe /c` dengan outer-quote ganda anti *quote stripping* Windows):**
  ```powershell
  cmd.exe /c '""D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe" --default-character-set=utf8mb4 -h 127.0.0.1 -P 3306 -u root sidatex_testing < "D:\laragon\www\sidatex\storage\backups\sidatex_testing_pre_batch40_YYYYMMDD.sql""'
  if ($LASTEXITCODE -ne 0) { throw "RESTORE GAGAL: cmd/mysql exit code = $LASTEXITCODE" }
  ```
  *(Catatan: outer-quote ganda `""...""` wajib pada `cmd.exe /c` bila terdapat lebih dari 2 tanda kutip di dalam string agar Windows `cmd.exe` tidak mengupas tanda kutip pertama dan terakhir.)*
- **Verifikasi Pasca-Restore Wajib:** Verifikasi SHA-256 `691` baris Tier-1 `2026-08` kembali ke `d664178098fd04bbfe56b510144645cb109ac426813b1d069e450f43497a6543`, `58` baris Origin Mart kembali ke `ef24390f6e9ce7690afdbf4bd34963b758416094616d81a71ed8ec9ed85a72ee`, dan `data_batches` aktif kembali ke `9` batch.

---

## 2. Idempotensi dan Concurrency

### 2.1 Evaluasi Skema Aktual `data_batches` & Kelemahan `lockForUpdate()` Saat Baris Belum Ada
Hasil pemeriksaan langsung `SHOW TABLE STATUS LIKE 'data_batches'` dan `SHOW INDEX FROM data_batches` pada `sidatex_testing`:
- **Engine:** `InnoDB` (`MySQL 8.0.30`, `AUTO_INCREMENT = 720`).
- **Indeks Aktual (HANYA 3):**
  1. `PRIMARY (batch_id)` (Unique)
  2. `data_batches_uploaded_by_foreign (uploaded_by)` (Non-unique)
  3. `data_batches_released_by_foreign (released_by)` (Non-unique)
- **Temuan Kritis:** **TIDAK ADA indeks maupun unique constraint pada `(tipe_sumber, jenis_data, periode_tahun, periode_bulan)`!**

**Mengapa `lockForUpdate()` Saja Tidak Cukup Ketika Baris Belum Ada:**
1. **Pada `REPEATABLE READ` (default MySQL InnoDB):** Karena kolom periode tidak terindeks, `SELECT ... FROM data_batches WHERE tipe_sumber='distribusi' AND periode_tahun=2026 AND periode_bulan=8 FOR UPDATE` melakukan **full clustered-index scan** (mengunci seluruh baris dan gap termasuk supremum). Jika dua sesi konkuren menjalankan query tersebut bersamaan saat 0 baris cocok, keduanya memperoleh gap lock yang kompatibel, lalu saat keduanya mencoba `INSERT` (meminta *insert intention lock*), InnoDB memicu **Deadlock (`SQLSTATE[40001] 1213`)**!
2. **Pada `READ COMMITTED`:** InnoDB menonaktifkan gap lock sama sekali — sehingga `lockForUpdate()` terhadap 0 baris **tidak mengunci apa pun**, dan dua eksekusi paralel dapat sama-sama melihat `0` baris lalu menyisipkan **2 batch aktif ganda** untuk `distribusi | Ekspor | 2026-08`!

### 2.2 Mekanisme Concurrency Aman Sesuai Skema Aktual (Tanpa Migration / Unique Constraint Baru)
Sesuai batasan bahwa tidak boleh menambah unique constraint atau migration tanpa persetujuan terpisah, penguncian konkuren dilakukan dengan **MySQL Session-Level Advisory Lock** (terverifikasi aktif pada MySQL `8.0.30`: `SELECT IS_FREE_LOCK('sidatex:remediate:distribusi:Ekspor:2026:08') = 1`, panjang 45 karakter `<= 64` batas MySQL 8.0):

1. **Acquire Lock SEBELUM `DB::beginTransaction()` (pada koneksi PDO default yang sama):**
   ```php
   $lockName = 'sidatex:remediate:distribusi:Ekspor:2026:08';
   $gotLock = (int) (DB::selectOne('SELECT GET_LOCK(?, 10) AS l', [$lockName])->l ?? 0);
   if ($gotLock !== 1) {
       throw new RuntimeException("CONCURRENCY ABORT: Advisory lock '{$lockName}' sedang dipegang sesi lain.");
   }
   ```
   *(Kompatibilitas Nested Transaction terverifikasi: `BrsAggregationService::aggregateBatch()` memakai `DB::transaction()` yang oleh Laravel diterjemahkan menjadi `SAVEPOINT` tanpa *premature commit*, dan `Schema::hasTable()` di `syncOriginSummariesForPeriod()` hanya menjalankan `SELECT information_schema`, bukan DDL sehingga tidak memicu implicit commit MySQL.)*
2. **Defense-in-Depth Di Dalam `DB::transaction()`:** Tetap gunakan `->lockForUpdate()` saat query pengecekan `data_batches` guna mengurangi jendela balapan terhadap upload web biasa (`UploadController`) yang belum memakai advisory lock.
3. **Guaranteed & Isolated Release di Blok `finally`:**
   ```php
   finally {
       try {
           DB::selectOne('SELECT RELEASE_LOCK(?) AS r', [$lockName]);
       } catch (\Throwable $lockErr) {
           // Jangan menutupi root exception jika koneksi PDO terputus (MySQL otomatis melepas advisory lock saat disconnect)
       }
   }
   ```

### 2.3 Matriks Keputusan Guard Idempotensi 5-Lapis (Jika Batch Aktif Ditemukan)
Canonical Transaction Fingerprint dari seluruh `809` record DBF (terverifikasi live pada `sidatex_testing`, diurutkan secara leksikografis pada tuple `tipe_sumber|jenis_transaksi|tahun|bulan|hs_id|negara_id|provinsi_id|kabkota_id|provorig|pelabuhan_id|berat|nilai`):
- **Expected Row Count:** **`809`**
- **Expected Canonical SHA-256:** **`028082b5015240f32c2fa0839f37cb49c21296502ac9cc882c8597a0f2d19507`**
  *(Catatan paritas `kabkota_id`: `537` baris terpetakan ke `master_kabkota.kode_kabkota`, `272` baris bernilai `NULL` terdiri dari `263` baris `PROVORIG 1473` Kota Dumai karena `master_kabkota` memakai kode `1472` + `9` baris non-Riau `1371`/`1207`/`3319`/`3514` — 100% identik dengan perilaku `DbfIngestionService.php:191-195` dan Batch `#71` yang juga memiliki `283` baris `1473 -> NULL`.)*

| Kondisi pada `data_batches` Aktif (`NOT IN active trash`) untuk `('distribusi', 'Ekspor', 2026, 8)` | Aksi Wajib |
|---|---|
| `0` batch aktif | Lanjutkan eksekusi normal Opsi R1 (insert batch baru `PRIVATE` + `809` transaksi + agregasi). |
| `> 1` batch aktif | **HARD ABORT** — Anomali duplikasi batch terdeteksi; hentikan proses dan laporkan ke pengguna. |
| `1` batch aktif dengan `status === 'RILIS'` | **HARD ABORT & MINTA PERSETUJUAN BARU** — Dilarang menyentuh atau mengagregasi ulang batch yang sudah rilis tanpa persetujuan eksplisit baru. |
| `1` batch aktif dengan `status !== 'PRIVATE'` (mis. `DRAFT`) | **HARD ABORT** — Status batch tidak sesuai prasyarat Opsi R1. |
| `1` batch aktif `PRIVATE`, namun `tipe_sumber`/`jenis_data`/`periode` tidak cocok, **atau** checksum DBF arsip (jika ada) `!== f527ff52...`, **atau** `COUNT(distribusi_transactions) !== 809`, **atau** canonical SHA-256 transaksi `!== 028082b5...` | **HARD ABORT** — Batch berisi data berbeda atau parsial; dilarang menimpa atau mengagregasi ulang secara otomatis. |
| `1` batch aktif `PRIVATE` yang lolos **100% seluruh 5 pemeriksaan** (sumber, periode, status `PRIVATE`, checksum DBF `f527ff52...`, `809` transaksi & canonical SHA-256 `028082b5...`) | **IDEMPOTENT NO-OP INSERT** — Lewati insert `data_batches`/`distribusi_transactions` & pengarsipan ulang; jalankan `aggregateBatch($existingBatchId, 'PRIVATE')` + verifikasi perlindungan `#71` saja. |

---

## 3. Konsistensi File dan Database

### 3.1 Verifikasi Konfigurasi Storage Privat Aktual
Hasil pemeriksaan langsung `config/filesystems.php:33-39`, `UploadController.php`, dan direktori filesystem:
- **Disk `'local'`:** `root => storage_path('app/private')` (`d:\laragon\www\sidatex\storage\app\private`), `'serve' => true` (butuh signed URL, non-publik), `'throw' => false` (**wajib memeriksa return boolean `Storage::disk('local')->put(...) === true` secara eksplisit!**).
- **Status Symlink `public/storage`:** **`False`** (tidak ada symlink dari `public/` ke `storage/`; berkas di `storage/app/private` 100% tidak dapat diakses melalui URL publik web server).
- **Konvensi Paritas `UploadController::forceDelete()` (`L1010–1050`):** Penghapusan permanen mencari file di `Storage::disk('local')` pada `uploads/raw_distribusi/{nama_file}` menggunakan basename persis yang tercatat di `data_batches.nama_file`.
- **Observasi Forensik Read-Only (`storage/app/private/uploads/raw_distribusi`):** Saat ini terdapat ~`28` berkas `*_14_Atap_ekspor_2608_POD_based.DBF` sisa pengujian otomatis/manual terdahulu yang tidak direferensikan `data_batches`. **Sesuai aturan read-only, berkas historis tersebut TIDAK dihapus dalam tahap ini**; dicatat sebagai item *housekeeping* terpisah agar tidak bercampur dengan ruang lingkup remediasi Batch `#40`.

### 3.2 Protokol Atomic File-Database Consistency (Zero Orphan Archive, Zero Temp Residue)
Agar tidak pernah ada berkas sementara tertinggal saat gagal maupun berkas arsip tertinggal tanpa batch yang sesuai:

```text
[1] Acquire GET_LOCK()
[2] Stage copy DBF ke disk 'local': temp_staging/remediate_{uuid}.dbf
    -> Cek eksplisit $putStage === true (karena 'throw' => false pada disk 'local')
    -> Verifikasi filesize === 71642 & sha256 === f527ff52...
[3] DB::beginTransaction()
    -> Insert data_batches (nama_file = $permFilename jika arsip aktif)
    -> Insert 809 distribusi_transactions + activity_logs
    -> aggregateBatch($newBatchId, 'PRIVATE') & aggregateBatch(71, 'PRIVATE')
    -> Jalankan SELURUH Pre-Commit Assertions (Batch baru, 30.147 historis, Origin 0, Neraca 4065, Batch #71 imutabel)
[4] (Jika pengarsipan diaktifkan) Tulis arsip ke disk privat 'local':
    uploads/raw_distribusi/{timestamp}_14_Atap_ekspor_2608_POD_based.DBF
    -> Cek eksplisit $putArchive === true (karena 'throw' => false!)
    -> Verifikasi file_exists && filesize === 71642 && sha256 === f527ff52...
    -> Tandai $archiveWrittenPath = $permRelPath
[5] DB::commit()
```

**Penanganan Kegagalan (Rollback & Cleanup Deterministik):**
- **Jika kegagalan terjadi pada langkah `[2]` atau `[3]` (DB Rollback):** `catch` mengecek `if (DB::transactionLevel() > 0) { DB::rollBack(); }`; arsip permanen **belum pernah ditulis**, dan `finally` (dalam `try/catch` terisolasi) menghapus `temp_staging/remediate_{uuid}.dbf` serta melepas `RELEASE_LOCK()`. → **0 file temporer, 0 file arsip yatim.**
- **Jika penulisan arsip pada langkah `[4]` gagal (`$putArchive !== true` atau checksum tidak cocok):** `catch` menjalankan `DB::rollBack()` dan segera menghapus berkas parsial `$archiveWrittenPath` jika sempat terbentuk; `finally` menghapus staging temporer & melepas lock. → **0 batch parsial, 0 file arsip yatim.**
- **Jika `DB::commit()` pada langkah `[5]` melempar exception:** `catch` mengecek `if (DB::transactionLevel() > 0) { DB::rollBack(); }` lalu segera memanggil `Storage::disk('local')->delete($archiveWrittenPath)`; `finally` menghapus staging temporer & melepas lock. → **0 file arsip tertinggal tanpa batch.**
- **Jika `DB::commit()` sukses namun hapus `temp_staging` di `finally` gagal:** Dibungkus `try/catch` terisolasi agar **tidak** menutupi sukses commit maupun memicu rollback semu; cukup catat warning agar file staging dibersihkan manual.
- **Jika rollback fisik penuh (`§1.4`) dilakukan pasca-commit:** Operator wajib menghapus berkas spesifik `storage/app/private/uploads/raw_distribusi/{permFilename}` milik `$newBatchId` agar filesystem kembali 1:1 dengan snapshot DB.

---

## 4. Audit Origin Mart dan Endpoint `/distribusi/metrics` (READ-ONLY)

### 4.1 Audit Lengkap Seluruh Periode `monthly_origin_trade_summaries` (`sidatex_testing`)
Hasil query read-only terhadap seluruh isi tabel `monthly_origin_trade_summaries` (tanpa filter periode):

| Tahun | Bulan | Jenis Transaksi | Jumlah Baris | Jumlah Tx | Total Nilai (USD) | Total Berat (Kg) | `created_at` | Status Audit |
|---:|---:|---|---:|---:|---:|---:|---|---|
| `2026` | `8` | `ekspor` | **`58`** | `809` | `2,218,155,995.460000` | `2,352,083,988.620000` | `2026-11-11 08:17:38` | **Orphan `PRIVATE` (`O2` — Batch `#40`)** |
| *(Periode lain)* | — | — | **`0`** | `0` | `0.000000` | `0.000000` | — | **BERSIH** (`O1` 2026-07 & `O3` 2026-09 sudah dibersihkan di Phase 2G.2B) |
| **TOTAL TABEL** | — | — | **`58`** | **`809`** | **`2,218,155,995.460000`** | **`2,352,083,988.620000`** | — | **Tidak ada artefak `PRIVATE`/orphan periode lain** |

### 4.2 Audit Sumber Data & Kontrol Akses Endpoint `/distribusi/metrics`
Pemeriksaan langsung terhadap `routes/web.php:41, 97`, `app/Http/Controllers/DistributionController.php:51-85`, dan `app/Services/DistributionStatisticsService.php:1-499` (0 perubahan kode):

- **Route & Middleware (`routes/web.php:41, 97`):** `GET /distribusi/metrics` berada di dalam grup `Route::middleware('auth')` **tanpa pembatasan role**. Seluruh pengguna login — termasuk **Pegawai BPS (Role 2)** yang dilarang melihat data `PRIVATE` — dapat memanggil endpoint ini.
- **Sumber Data per Komponen JSON (`DistributionController::getMetrics()`):**

| Field JSON Response | Method Service | Tabel Sumber | Filter Visibilitas / RBAC | Status Kebocoran `PRIVATE` |
|---|---|---|---|---|
| `ekspor`, `impor`, `neraca`, `sektor`, `top_commodities`, `top_countries`, `bec_impor` | `getSummaryMetrics()`, `getDistributionTradeBalance()`, `getSektorBreakdown()`, `getTopCommodities()`, `getTopCountries()`, `getImportBecBreakdown()` | `distribusi_transactions` | `whereIn('t.batch_id', getReleasedBatchIds())` (`status = 'RILIS'`, `tipe_sumber = 'distribusi'`, `NOT IN active trash`) | **AMAN** — Terisolasi ketat ke `RILIS` |
| **`origin_wilayah`** | **`getOriginDistribution()` (`L454-498`)** | **`monthly_origin_trade_summaries`** | **TIDAK ADA filter `status_visibilitas`, `batch_id`, maupun cek role!** (Tabel memang tidak memiliki kolom `status_visibilitas`/`batch_id`) | **BOCOR AKTIF (SAAT INI)** — Role 2 Pegawai memanggil `/distribusi/metrics?tahun=2026&bulan=8` menerima `origin_wilayah = US$ 2,218.16 Juta` dari `58` baris orphan `O2`! |

### 4.3 Laporan Risiko Kebocoran & Temuan Tambahan (Untuk Phase 2G.3 — Tidak Diubah Saat Ini)
1. **Ketergantungan Penuh pada Write-Side Discipline (Risiko Kebocoran Struktural):** Selama `monthly_origin_trade_summaries` tidak memiliki kolom `status_visibilitas` dan `getOriginDistribution()` tidak memverifikasi eksistensi batch `RILIS` untuk `(tahun, bulan, 'ekspor')`, setiap write langsung atau gagal-cleanup ke tabel tersebut langsung terekspos ke Pegawai BPS (Role 2). Eksekusi Opsi R1 akan langsung menutup kebocoran aktif `O2` (`58 -> 0`), sedangkan *read-side guard* pada `getOriginDistribution()` direkomendasikan sebagai perbaikan permanen di **Phase 2G.3**.
2. **Temuan Metodologis & Keamanan Sekunder pada `DistributionStatisticsService` & `DistributionController` (Non-Blocking untuk Batch `#40`, Dicatat untuk Phase 2G.3):**
   - `DistributionStatisticsService.php:87-90, 225, 274, 361` memisahkan Migas memakai `h.oilgrp_code = 1` (berbeda dari `BrsAggregationService` yang memakai `oilgrp_desc = 'MIGAS' || hs2 = '27'`).
   - `DistributionStatisticsService::getDistributionTradeBalance()` (`L178-203`) menghitung `fobBulan - cifBulan` meskipun salah satu sisi `0` (menghasilkan defisit semu `-US$ 167.35 Jt` untuk `2026-08` karena Impor `#70` `RILIS` sementara Ekspor `PRIVATE`), berbeda dari `DashboardController::data()` yang mengembalikan `null`.
   - `DistributionController::getMetrics()` (`L82`) mengembalikan `$e->getMessage()` mentah pada HTTP `500` JSON (minor *error information disclosure*).
3. **Temuan Master Data untuk Backlog (Di Luar Ruang Lingkup Batch `#40`):** Ketidakselarasan kode BPS Kota Dumai (`PROVORIG = '1473'` di DBF vs `kode_kabkota = '1472'` di `master_kabkota`) menyebabkan transaksi asal Kota Dumai (`263` baris di `#40`, `283` baris di `#71`) jatuh ke `'Daerah Lain'` pada Peta Leaflet & grafik Kab/Kota Tab 3 Dashboard. Mempertahankan paritas `kabkota_id = NULL` saat remediasi Batch `#40` adalah keputusan tepat agar konsisten antar-batch; penyelarasan `master_kabkota` diajukan terpisah untuk Phase 2G.3+.

---

## 5. Perlindungan Batch `#71` (`distribusi | Ekspor | 2026-09`)

### 5.1 Snapshot BEFORE Lengkap (Live Read-Only dari `sidatex_testing`)
| Komponen Batch `#71` | Snapshot BEFORE (Aktual Saat Ini) | Ekspektasi AFTER Opsi R1 | Sifat Perubahan |
|---|---|---|---|
| **`data_batches` (`batch_id = 71`)** | `batch_id = 71`, `tipe_sumber = 'distribusi'`, `jenis_data = 'Ekspor'`, `periode = 2026-09`, **`status = 'PRIVATE'`**, `nama_file = '1794547157_14_Atap_ekspor_2609_POD_based.DBF'`, `ukuran_file = '0.07'`, `uploaded_by = 1`, `released_by = null`, `released_at = null`, `updated_at = '2026-11-13 05:19:17'` | **IDENTIK 100%** (termasuk `batch_id = 71`, `status = 'PRIVATE'`, `updated_at = '2026-11-13 05:19:17'`) | **IMUTABEL (0 write)** |
| **`distribusi_transactions` (`batch_id = 71`)** | `COUNT = 841` (`transaction_id 15679..16519`), **`SUM(nilai) = 2,301,809,204.587000` USD**, **`SUM(berat) = 2,553,373,143.520000` Kg**, Raw SHA-256 = **`ac739c0e484c711e4f1480eef439033c03b6d0069e9e64ea32ddb6e7f388f576`** | **IDENTIK 100%** (`COUNT = 841`, `15679..16519`, `SUM(nilai) = 2,301,809,204.587000`, `SUM(berat) = 2,553,373,143.520000`, Raw SHA-256 = `ac739c0e...`) | **IMUTABEL (0 write)** |
| **`monthly_trade_summaries` (`batch_id = 71`)** | `row_cnt = 831`, `SUM(tx) = 841`, `SUM(total_nilai) = 2,301,809,204.587000`, `SUM(total_berat) = 2,553,373,143.520000`, `status_visibilitas = 'PRIVATE'` | `row_cnt = 831`, `SUM(tx) = 841`, `SUM(total_nilai) = 2,301,809,204.587000`, `SUM(total_berat) = 2,553,373,143.520000`, `status_visibilitas = 'PRIVATE'` | Di-rebuild identik (timestamp `updated_at` baru) |
| **`brs_indicator_summaries` (`batch_id = 71`)** | `row_cnt = 47`; `TOTAL/TOTAL` (`id = 4201`): `status_visibilitas = 'PRIVATE'`, `nilai_usd = 2,301,809,204.587000`, `nilai_juta_usd = 2301.81`, `volume_ribu_ton = 2553.37`, **`growth_mtom = 3.77`**, **`growth_yoy = 45.04`**, **`growth_ctc = 6.96`** | `row_cnt = 47`; `TOTAL/TOTAL`: `status_visibilitas = 'PRIVATE'`, `nilai_usd = 2,301,809,204.587000`, `nilai_juta_usd = 2301.81`, `volume_ribu_ton = 2553.37`, **`growth_mtom = 3.77`**, **`growth_yoy = 45.04`**, **`growth_ctc = 6.96`** | Di-refresh terhadap `2026-08` 6-desimal (`+$0.0427` → 2-desimal tetap identik) |

### 5.2 Daftar Assertion Perlindungan Wajib Batch `#71` (Pre-Execution & Pre-Commit)
1. **Pre-Check Sebelum Refresh:** Verifikasi `data_batches#71` ada, tidak di Trash, dan `status === 'PRIVATE'`. Jika `status !== 'PRIVATE'` → **HARD ABORT**.
2. **Assertion Imutabilitas Identitas & Status:** Setelah `aggregateBatch(71, 'PRIVATE')`, pastikan `batch_id === 71`, `status === 'PRIVATE'`, `released_by === null`, `released_at === null`, dan `data_batches.updated_at === '2026-11-13 05:19:17'`.
3. **Assertion Imutabilitas Transaksi Sumber:** Pastikan `COUNT(distribusi_transactions WHERE batch_id = 71) === 841`, `MIN(transaction_id) === 15679`, `MAX(transaction_id) === 16519`, `bccomp(SUM(nilai), '2301809204.587000', 6) === 0`, `bccomp(SUM(berat), '2553373143.520000', 6) === 0`, serta SHA-256 seluruh `841` baris transaksi === `ac739c0e484c711e4f1480eef439033c03b6d0069e9e64ea32ddb6e7f388f576`.
4. **Assertion Ringkasan & Pertumbuhan:** Pastikan `MTS#71` tetap `831` baris / `PRIVATE` / `0.000000` drift dan `BRS#71` `TOTAL/TOTAL` tetap `PRIVATE`, `nilai_usd === '2301809204.587000'`, `growth_mtom === '3.77'`, `growth_yoy === '45.04'`, `growth_ctc === '6.96'`.

---

## 6. Verifikasi Kepatuhan Batasan Keamanan

- [x] **Live PDO Verified:** `SELECT DATABASE() === 'sidatex_testing'` sebelum seluruh query pemeriksaan.
- [x] **Zero Mutasi Operasional `sidatex`:** Seluruh metrik operasional `sidatex` tidak berubah (`data_batches.MAX(updated_at) = 2026-11-13 07:38:33`, `monthly_trade_summaries.MAX(updated_at) = 2026-11-12 07:06:16`).
- [x] **Zero Mutasi Testing / Kode / Migrasi:** Tidak ada `INSERT`/`UPDATE`/`DELETE`, tidak ada ingestion, aggregation, perubahan kode produksi, maupun penambahan unique constraint/migration.
- [x] **30.147 Baris Historis `PRIVATE` Terlindungi:** Guard `pelabuhan_key = 0` + verifikasi SHA-256 17-kolom `8c946a1ad978a04eda84517d062f5ddc6b09ae7abbbd9fdfb94e217abbf8207e` dipertahankan mutlak.
- [x] **Status Batch Remediasi Tetap `PRIVATE`:** Tidak dipromosikan ke `RILIS`.
- [x] **Assertion Pengujian Tidak Dilemahkan:** Penyelarasan `Stage2bValidationAndSimulationTest.php:73-81` tetap mempertahankan seluruh assertion integritas komposit.

---

## 7. Hasil Review Independen Final Safety Review (3 Specialist Agents — Read-Only)

| Reviewer Agent | Verdict | Ringkasan Temuan & Status Integrasi |
|---|---|---|
| **`database-guardian`** | **`PASS`** | Memverifikasi penuh seluruh 5 pilar keselamatan database & backup: Laragon MySQL 8.0.30 `--result-file` + validasi 6 lapis aman PS 5.1, diagnosis tidak adanya indeks periode pada `data_batches` + `GET_LOCK` (45 karakter, tanpa DDL/migration) + guard 5-lapis idempotensi, konsistensi 2-fase file privat-DB, audit `monthly_origin_trade_summaries` & `/distribusi/metrics`, serta imutabilitas Batch `#71`. Seluruh 3 catatan implementasi minor (tabel ke-6 `neraca_transactions` + `-LiteralPath` + guard `$ErrorActionPreference` pada backup; cek `DB::transactionLevel() > 0` & `try/catch` pada `RELEASE_LOCK()`; catatan `.gitignore` `/storage/backups/`) **telah diintegrasikan penuh ke dokumen ini**. |
| **`statistics-auditor`** | **`PASS`** | Memverifikasi 0 perubahan pada keputusan statistik Opsi R1 (`16` pelabuhan, `809` tx, `797` cube, `$2,218,155,995.502700` FOB, `2,352,083,988.620000 Kg`, `3` Migas, status `PRIVATE`), paritas penuh `kabkota_id` (`537` mapped + `272` `NULL`) dengan `DbfIngestionService` & `#71`, audit Origin Mart (`58` baris `2026-08` saja) & temuan `DistributionStatisticsService`, serta snapshot BEFORE & perlindungan `#71`. Catatan backlog Master Data Kota Dumai (`1473` vs `1472`) **telah dicatat di `§4.3 #3`**. |
| **`independent-reviewer`** | **`APPROVED WITH MINOR NOTES`** | Memverifikasi sinkronisasi 100% seluruh SHA-256 & skema antara `REMEDIATION_PLAN.md` dan `FINAL_SAFETY_REVIEW.md`, kompatibilitas `GET_LOCK` + nested `SAVEPOINT` (`BrsAggregationService`) + `Schema::hasTable()` (non-DDL), serta seluruh sitasi kode. Menemukan 1 bug kutipan `cmd.exe /c` (*quote stripping*) pada Restore Metode B dan 2 catatan hardening (`$putOk === true` karena `'throw' => false` pada disk `'local'`; `try/finally { $fs.Dispose() }` + `-LiteralPath` + isolasi `finally`). **Seluruh 3 catatan telah diperbaiki penuh pada kedua dokumen.** |
