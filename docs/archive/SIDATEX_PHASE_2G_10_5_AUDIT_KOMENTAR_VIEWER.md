# SIDATEX — AUDIT FITUR KOMENTAR VIEWER EKSEKUTIF (PHASE 2G.10.5)
## Executive Viewer Annotation, Privacy Scoping, and Audit Trail Verification Report

**Aplikasi:** Sistem Informasi Data Ekspor-Impor (SIDATEX) — BPS Provinsi Riau  
**Lokasi Direktori:** `D:\laragon\www\sidatex`  
**Fokus Evaluasi:** Fitur Catatan Verifikasi & Masukan Pimpinan (Viewer Eksekutif / Role 3)  
**Komponen yang Diaudit:** Skema Tabel, Endpoint Route, Controller, Model, Activity Logger, dan Tampilan Blade  
**Tanggal Audit:** 19 November 2026  

---

## 1. TUJUAN & KEBUTUHAN BISNIS FITUR KOMENTAR

Sebagai pimpinan dan penelaah data makro di lingkungan BPS Provinsi Riau, peran **Viewer Eksekutif (Role 3)** memiliki fungsi strategis dalam mengevaluasi angka perdagangan luar negeri (ekspor dan impor) sebelum angka tersebut dirilis ke publik. 

Untuk menjalankan fungsi telaah tersebut, Viewer Eksekutif membutuhkan sarana untuk:
1. Menelaah dokumen kelompok data (*data batch*) berstatus `RILIS` maupun draft internal `PRIVATE`.
2. Menyematkan catatan verifikasi, masukan analisis, atau pertanyaan konfirmasi pada batch terkait.
3. Memastikan catatan tersebut tersimpan aman tanpa mengubah data transaksi asli.
4. Mengetahui identitas pengunggah komentar beserta stempel waktu (*timestamp*).
5. Menjaga kerahasiaan catatan pada data `PRIVATE` agar tidak bocor ke staf umum (Pegawai BPS).

---

## 2. HASIL AUDIT LAPISAN BASIS DATA (SCHEMA LEVEL)

Struktur tabel `comments` telah tersedia pada database `sidatex` (dan identik pada `sidatex_testing`):

```sql
CREATE TABLE `comments` (
  `comment_id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `batch_id` int unsigned NOT NULL,
  `komentar` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`comment_id`),
  KEY `comments_user_id_foreign` (`user_id`),
  KEY `comments_batch_id_foreign` (`batch_id`),
  CONSTRAINT `comments_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `data_batches` (`batch_id`) ON DELETE CASCADE,
  CONSTRAINT `comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Karakteristik Skema:
- **Integritas Relasional:** Memiliki foreign key cascade ke `users` dan `data_batches`.
- **Isolasi Mutlak:** Komentar berada pada tabel independen dan **TIDAK MEMILIKI RELASI LANGSUNG DENGAN RECORD TRANSAKSI**. Viewer Eksekutif yang menambahkan komentar dipastikan tidak dapat menyentuh atau mengubah nominal CIF/FOB, berat netto, maupun klasifikasi HS transaksi perdagangan.

---

## 3. HASIL AUDIT LAPISAN LOGIKA BACKEND & OTORISASI (CONTROLLER LEVEL)

Implementasi pada `app/Http/Controllers/CommentController.php` diaudit baris per baris:

```
+---------------------------------------------------------------------------------------------------+
| HASIL VERIFIKASI METODE CommentController                                                         |
+---------------------------------------------------------------------------------------------------+
| 1. authorizeBatchAccess(batchId, user, requireWrite):                                             |
|    - Hak Tulis (requireWrite = true) dibatasi HANYA untuk Super Admin (1) dan Viewer (3).          |
|    - Pegawai (2) yang mencoba menulis langsung ditolak dengan respons HTTP 403 Forbidden.         |
|    - Viewer (3) diizinkan membaca & menulis HANYA pada batch RILIS dan PRIVATE (DRAFT ditolak).   |
|    - Batch yang berada di Trash ditolak untuk penulisan komentar (HTTP 403).                      |
|                                                                                                   |
| 2. getByBatch(batchId):                                                                           |
|    - Mengembalikan riwayat komentar lengkap dengan nama penulis, peran, dan created_at.           |
|    - Jika batch berstatus PRIVATE, Pegawai yang mengakses URL langsung ditolak HTTP 403.         |
|                                                                                                   |
| 3. store(Request):                                                                                |
|    - Validasi panjang teks komentar maksimal 2.000 karakter.                                      |
|    - Pengecekan otorisasi role dilakukan sebelum validasi exists:data_batches untuk mencegah      |
|      enumerasi ID batch tertutup.                                                                 |
|    - Mencatat aksi ke log audit: ActivityLogger::log('Tambah Komentar', 'Komentar', ...).        |
|                                                                                                   |
| 4. destroy(commentId):                                                                            |
|    - Hanya Super Admin atau pembuat komentar itu sendiri (owner) yang diizinkan menghapus.        |
|    - Menolak penghapusan oleh pengguna lain (HTTP 403).                                           |
+---------------------------------------------------------------------------------------------------+
```

### Bukti Pengujian Otomatis:
Fitur komentar dan pembatasan privasinya telah diverifikasi melalui pengujian fitur `tests/Feature/Phase2gCommentRbacTest.php` yang mencakup 4 skenario pengetesan ketat:
- `test_guest_cannot_access_comment_endpoints` (HTTP 401)
- `test_pegawai_cannot_read_private_draft_trash_or_post_any_comment` (HTTP 403)
- `test_viewer_eksekutif_can_read_and_post_on_rilis_and_private_only` (HTTP 200 & 201)
- `test_comment_deletion_enforces_superadmin_or_owner_and_blocks_trashed_for_non_admin` (HTTP 200 & 403)

---

## 4. TEMUAN KESENJANGAN: ANTARMUKA PENGGUNA (FRONTEND BLADE GAP)

Meskipun fondasi backend (Tabel Database, Model Eloquent, Controller, Route API, Audit Logging, dan Unit Test) telah **100% SELESAI DAN TERUJI SUKSES**, audit menemukan kesenjangan pada antarmuka pengguna:

### Temuan Antarmuka:
1. **Ketiadaan Tombol Pemicu di Tabel Dokumen:**  
   Pada `resources/views/manajemen-data.blade.php`, menu aksi baris dokumen (titik tiga ⋮) memuat tombol:
   - Ekspor Excel (.xlsx)
   - Ekspor PowerPoint (.pptx)
   - Edit Dokumen
   - Tarik/Publish (Super Admin)
   - Hapus ke Trash (Super Admin)  
   Namun, **belum tersedia tombol khusus "Catatan Verifikasi" / "Komentar Batch"** untuk membuka modal komentar.
2. **Ketiadaan Komponen Modal Komentar:**  
   Komponen modal dialog interaktif (berisi form input teks, tombol kirim, dan daftar linimasa komentar yang mengambil data via `fetch('/comments/batch/{id}')`) belum di-render pada view Blade.
3. **Dampak terhadap UAT:**  
   Viewer Eksekutif saat ini dapat menguji API komentar melalui HTTP request, tetapi belum dapat melihat tombol fisik pada peramban web tanpa penambahan markup antarmuka modal.

---

## 5. REKOMENDASI DESAIN ANTARMUKA KOMENTAR (UNSLOP & MINIMALIS)

Untuk mengaktifkan fitur komentar bagi Viewer Eksekutif secara visual saat UAT, disarankan menambahkan komponen modal minimalis pada `resources/views/manajemen-data.blade.php` pada fase implementasi terpisah:

```html
<!-- Tombol Aksi di Dropdown Manajemen Data -->
<button type="button" onclick="openCommentModal({{ $docId }}, '{{ addslashes($doc->nama_file) }}')">
    💬 Catatan Verifikasi
</button>

<!-- Modal Catatan Verifikasi Batch (Alpine.js / Vanilla JS) -->
<div id="modal-comment" class="modal-backdrop" style="display: none;">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Catatan Verifikasi Batch</h3>
            <button onclick="closeCommentModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Linimasa Komentar Terdahulu -->
            <div id="comment-list" class="comment-timeline"></div>
            
            <!-- Form Tambah Catatan (Khusus Super Admin & Viewer) -->
            @if(in_array((int) auth()->user()->role_id, [1, 3], true))
            <form id="form-comment" onsubmit="submitComment(event)">
                <textarea name="komentar" placeholder="Tuliskan catatan telaah atau konfirmasi angka..." maxlength="2000" required></textarea>
                <button type="submit" class="btn-primary">Kirim Catatan</button>
            </form>
            @endif
        </div>
    </div>
</div>
```

---

## 6. KESIMPULAN AUDIT FITUR KOMENTAR

```
+---------------------------------------------------------------------------------------------------+
| STATUS KESIAPAN FITUR KOMENTAR VIEWER EKSEKUTIF                                                   |
+---------------------------------------------------------------------------------------------------+
| Lapisan Skema Database         : SIAP (Tabel comments tersedia dan berelasi benar)               |
| Lapisan Logika Controller      : SIAP (Otorisasi RBAC Role 1 & 3, proteksi data PRIVATE)          |
| Lapisan Keamanan Data          : SIAP (Komentar terisolasi dari mutasi transaksi perdagangan)    |
| Lapisan Audit Trail            : SIAP (Tercatat otomatis di activity_logs)                       |
| Lapisan Antarmuka (Blade UI)   : BELUM TERPASANG (Butuh modal trigger di manajemen-data.blade.php)|
| KEPUTUSAN KELAYAKAN            : SIAP SECARA SISTEM BACKEND — BUTUH INTEGRASI UI PADA FASE DEPAN  |
+---------------------------------------------------------------------------------------------------+
```
