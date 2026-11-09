<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class UploadController extends Controller
{
    public function index()
    {
        // Mengambil semua data dari tabel master menggunakan DB Facade
        $provinsis = DB::table('master_provinsi')->orderBy('nama_provinsi', 'asc')->get();
        $kabkotas = DB::table('master_kabkota')->orderBy('nama_kabkota', 'asc')->get();
        $negaras = DB::table('master_negara')->orderBy('nama_negara', 'asc')->get();

        $hs_codes = DB::table('master_hs')
            ->select('hs2', 'hs2_desc')
            ->whereNotNull('hs2')
            ->distinct()
            ->orderBy('hs2', 'asc')
            ->get();

        return view('upload-data', compact('provinsis', 'kabkotas', 'negaras', 'hs_codes'));
    }

    public function store(Request $request)
    {
        // 1. Validasi Input (Pastikan file yang diunggah benar-benar Excel)
        $validated = $request->validate([
            'excel_file_final' => 'required|file|extensions:xlsx,xls|max:51200', // max 50MB
            'jenis_data_final' => 'required|string',
        ], [
            'excel_file_final.required' => 'File Excel wajib dipilih sebelum menyimpan.',
            'excel_file_final.file' => 'Unggahan harus berupa file yang valid.',
            'excel_file_final.extensions' => 'File harus berekstensi .xlsx atau .xls.',
            'excel_file_final.max' => 'Ukuran file tidak boleh melebihi 50 MB.',
            'jenis_data_final.required' => 'Jenis data (Ekspor/Impor) wajib ditentukan.',
        ]);

        $file = $request->file('excel_file_final');
        $namaFile = $file->getClientOriginalName();
        $ukuranMB = round($file->getSize() / 1048576, 2);

        // Petakan status_final ke ENUM database ('DRAFT', 'PRIVATE', 'NON-RILIS', 'RILIS')
        $statusInput = strtoupper(trim((string) $request->input('status_final', 'PRIVATE')));
        $currentUser = Auth::user();
        $isAuthorizedToRelease = $currentUser && ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));

        // Jika user memilih 'RILIS' dan berwenang, maka status 'RILIS'
        if ($statusInput === 'RILIS' && $isAuthorizedToRelease) {
            $statusDb = 'RILIS';
            $releasedBy = $currentUser ? $currentUser->id : 1;
            $releasedAt = now();
        } elseif ($statusInput === 'DRAFT') {
            $statusDb = 'DRAFT';
            $releasedBy = null;
            $releasedAt = null;
        } else {
            // Default adalah status 'PRIVATE' sesuai keputusan resmi BPS (hanya dilihat Super Admin & Viewer Eksekutif)
            $statusDb = 'PRIVATE';
            $releasedBy = null;
            $releasedAt = null;
        }

        // Simpan file fisik sementara/permanen ke folder storage
        $storedPath = $file->storeAs('uploads/excel', time() . '_' . $namaFile, 'public');
        $fullPath = storage_path('app/public/' . $storedPath);

        // 2. Gunakan Database Transaction agar penyimpanan data_batches dan transactions konsisten
        DB::beginTransaction();
        try {
            // A. Simpan data batch
            $batchId = DB::table('data_batches')->insertGetId([
                'nama_file' => $namaFile,
                'jenis_data' => $request->jenis_data_final,
                'ukuran_file' => $ukuranMB,
                'status' => $statusDb,
                'uploaded_by' => Auth::id() ?? 1,
                'uploaded_at' => now(),
                'released_by' => $releasedBy,
                'released_at' => $releasedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // B. Parse baris Excel dan simpan ke tabel transactions
            $parser = new \App\Services\ExcelTransactionParser();
            $totalInserted = $parser->parseAndInsert(
                $fullPath,
                $batchId,
                $request->jenis_data_final
            );

            // C. Commit transaksi jika semua berhasil
            DB::commit();

            $pesanSukses = "File {$namaFile} ({$ukuranMB} MB) berhasil disimpan ke database beserta " . 
                           number_format($totalInserted, 0, ',', '.') . 
                           " transaksi dengan status {$statusDb}.";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $pesanSukses,
                    'data' => [
                        'batch_id' => $batchId,
                        'nama_file' => $namaFile,
                        'ukuran_file' => $ukuranMB,
                        'status' => $statusDb,
                        'total_transactions' => $totalInserted,
                    ],
                    'redirect_url' => route('manajemen-data'),
                ]);
            }

            return redirect()->route('manajemen-data')->with('success', $pesanSukses);

        } catch (\Throwable $e) {
            DB::rollBack();

            logger()->error('Gagal menyimpan batch dan transaksi: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menyimpan transaksi ke database: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal menyimpan transaksi: ' . $e->getMessage());
        }
    }

    public function manajemen()
    {
        // Ambil ID batch yang sedang berada di Trash (belum dipulihkan)
        $trashedBatchIds = DB::table('trash')->whereNull('restored_at')->pluck('batch_id')->toArray();

        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser && ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));
        $isViewerEksekutif = $currentUser && ($currentUser->role_id === 3 || ($currentUser->role && $currentUser->role->nama_role === 'Viewer Eksekutif'));

        // Query dasar batch aktif (tidak di Trash)
        $baseQuery = DB::table('data_batches')->whereNotIn('data_batches.batch_id', $trashedBatchIds);

        // Isolasi status berdasarkan role:
        // - Pegawai (role 2): HANYA RILIS
        // - Viewer Eksekutif (role 3): RILIS dan PRIVATE
        // - Super Admin (role 1): SEMUA STATUS
        if (!$isSuperAdmin) {
            if ($isViewerEksekutif) {
                $baseQuery->whereIn('data_batches.status', ['RILIS', 'PRIVATE']);
            } else {
                $baseQuery->where('data_batches.status', 'RILIS');
            }
        }

        $totalFile = (clone $baseQuery)->count();
        $terverifikasi = (clone $baseQuery)->where('data_batches.status', 'RILIS')->count();
        $pending = (clone $baseQuery)->whereIn('data_batches.status', ['PRIVATE', 'DRAFT'])->count();

        $totalUkuranMB = (clone $baseQuery)->sum('data_batches.ukuran_file');
        $totalUkuranGB = $totalUkuranMB > 1024
                            ? round($totalUkuranMB / 1024, 1).' GB'
                            : round($totalUkuranMB, 1).' MB';

        // Ambil data batches aktif beserta jumlah baris transaksi yang berelasi
        $dokumens = (clone $baseQuery)
            ->leftJoin('transactions', 'data_batches.batch_id', '=', 'transactions.batch_id')
            ->select(
                'data_batches.*',
                DB::raw('COUNT(transactions.transaction_id) as total_transaksi')
            )
            ->groupBy('data_batches.batch_id')
            ->orderBy('data_batches.created_at', 'desc')
            ->paginate(10);

        // Ambil data Trash untuk modal Trash
        $trashedItems = DB::table('trash')
            ->whereNull('trash.restored_at')
            ->join('data_batches', 'trash.batch_id', '=', 'data_batches.batch_id')
            ->leftJoin('users as deleter', 'trash.deleted_by', '=', 'deleter.id')
            ->select(
                'trash.*',
                'data_batches.nama_file',
                'data_batches.jenis_data',
                'data_batches.ukuran_file',
                'data_batches.status as batch_status',
                'deleter.name as deleter_name'
            )
            ->orderBy('trash.deleted_at', 'desc')
            ->get();

        $totalTrash = $trashedItems->count();

        return view('manajemen-data', compact(
            'totalFile',
            'terverifikasi',
            'pending',
            'totalUkuranGB',
            'dokumens',
            'trashedItems',
            'totalTrash'
        ));
    }

    /**
     * Halaman Khusus Trash / Sampah Dokumen.
     */
    public function trash()
    {
        $trashedItems = DB::table('trash')
            ->whereNull('trash.restored_at')
            ->join('data_batches', 'trash.batch_id', '=', 'data_batches.batch_id')
            ->leftJoin('users as deleter', 'trash.deleted_by', '=', 'deleter.id')
            ->select(
                'trash.*',
                'data_batches.nama_file',
                'data_batches.jenis_data',
                'data_batches.ukuran_file',
                'data_batches.status as batch_status',
                'deleter.name as deleter_name'
            )
            ->orderBy('trash.deleted_at', 'desc')
            ->get();

        $totalTrash = $trashedItems->count();

        return view('pages.trash-data', compact('trashedItems', 'totalTrash'));
    }

    /**
     * Mengubah status rilis batch data (Pending/DRAFT <-> Public/RILIS).
     * Hanya akun berwenang (Super Admin / role_id 1) yang diizinkan melakukan aksi ini.
     */
    public function toggleRelease(Request $request, $batchId)
    {
        $currentUser = Auth::user();
        $isAuthorized = $currentUser && ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));

        if (!$isAuthorized) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya Super Admin / akun berwenang yang dapat merilis atau menarik data publik.',
                ], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak. Anda tidak memiliki kewenangan untuk merilis data.');
        }

        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        if (!$batch) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Data dokumen tidak ditemukan.'], 404);
            }
            return redirect()->back()->with('error', 'Data dokumen tidak ditemukan.');
        }

        $isCurrentlyReleased = ($batch->status === 'RILIS');
        $newStatus = $isCurrentlyReleased ? 'PRIVATE' : 'RILIS';
        $releasedBy = $isCurrentlyReleased ? null : $currentUser->id;
        $releasedAt = $isCurrentlyReleased ? null : now();

        DB::table('data_batches')->where('batch_id', $batchId)->update([
            'status' => $newStatus,
            'released_by' => $releasedBy,
            'released_at' => $releasedAt,
            'updated_at' => now(),
        ]);

        // Catat ke log aktivitas jika memungkinkan
        try {
            DB::table('activity_logs')->insert([
                'user_id' => $currentUser->id,
                'aktivitas' => $isCurrentlyReleased ? 'Tarik Publikasi Batch Data' : 'Rilis Publikasi Batch Data',
                'modul' => 'Manajemen Data',
                'deskripsi' => "Mengubah status batch #{$batchId} ({$batch->nama_file}) menjadi {$newStatus}",
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            logger()->warning('Gagal mencatat log aktivitas toggleRelease: ' . $e->getMessage());
        }

        $labelStatus = $newStatus === 'RILIS' ? 'Public' : 'Pending';
        $pesan = "Status data '{$batch->nama_file}' berhasil diubah menjadi {$labelStatus}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $pesan,
                'new_status' => $newStatus,
                'new_status_label' => $labelStatus,
            ]);
        }

        return redirect()->route('manajemen-data')->with('success', $pesan);
    }

    /**
     * Mengunduh/mengekspor data transaksi pada batch dalam format Excel (.xlsx).
     * Data yang belum dirilis hanya boleh diekspor oleh Super Admin atau pemilik data.
     */
    public function export(Request $request, $batchId)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser && ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));

        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        if (!$batch) {
            abort(404, 'Data dokumen tidak ditemukan.');
        }

        $isOwner = $currentUser && ($batch->uploaded_by == $currentUser->id);

        // Jika data belum dirilis (DRAFT / NON-RILIS), hanya Super Admin atau pemilik unggahan yang boleh mengunduh
        if ($batch->status !== 'RILIS' && !$isSuperAdmin && !$isOwner) {
            abort(403, 'Akses ditolak. Data ini belum dirilis dan hanya dapat diunduh oleh Super Admin atau pemilik unggahan.');
        }

        // Lepaskan session lock sesegera mungkin agar navigasi halaman lain (Dashboard, Upload Data, dll.)
        // dapat diakses seketika secara paralel tanpa menunggu proses download selesai.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $batch->nama_file);
        if (!str_ends_with(strtolower($safeName), '.xlsx')) {
            $safeName .= '.xlsx';
        }
        $downloadFilename = 'SIDATEX_Ekspor_' . $safeName;

        // Path file temporer di server
        $tempExcelPath = tempnam(sys_get_temp_dir(), 'sidatex_exp_') . '.xlsx';

        try {
            $exporter = new \App\Services\FastExcelExporter();
            $totalExported = $exporter->exportBatchToPath((int) $batchId, $tempExcelPath);

            // Catat aktivitas ekspor
            try {
                DB::table('activity_logs')->insert([
                    'user_id' => $currentUser ? $currentUser->id : 1,
                    'aktivitas' => 'Ekspor Data Batch',
                    'modul' => 'Manajemen Data',
                    'deskripsi' => "Mengunduh data batch #{$batchId} ({$batch->nama_file}) sebanyak {$totalExported} transaksi",
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                logger()->warning('Gagal mencatat log ekspor: ' . $e->getMessage());
            }

            return response()->download($tempExcelPath, $downloadFilename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            if (file_exists($tempExcelPath)) {
                @unlink($tempExcelPath);
            }
            logger()->error('Gagal mengekspor data Excel batch: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Gagal mengekspor data: ' . $e->getMessage());
        }
    }

    /**
     * Memperbarui informasi dokumen / batch data (Edit).
     * Memastikan data Pending tidak otomatis berubah menjadi Public.
     */
    public function update(Request $request, $batchId)
    {
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser && ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));

        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        if (!$batch) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Data dokumen tidak ditemukan.'], 404);
            }
            return redirect()->back()->with('error', 'Data dokumen tidak ditemukan.');
        }

        $isOwner = $currentUser && ($batch->uploaded_by == $currentUser->id);
        if (!$isSuperAdmin && !$isOwner) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak. Anda tidak berwenang mengedit data ini.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak. Anda tidak berwenang mengedit data ini.');
        }

        $request->validate([
            'nama_file' => 'required|string|max:255',
            'jenis_data' => 'required|in:Ekspor,Impor',
        ], [
            'nama_file.required' => 'Nama dokumen tidak boleh kosong.',
            'jenis_data.required' => 'Jenis data wajib dipilih.',
            'jenis_data.in' => 'Jenis data harus berupa Ekspor atau Impor.',
        ]);

        DB::beginTransaction();
        try {
            // PERTAHANKAN STATUS ASLI: Status Pending TIDAK BOLEH otomatis berubah menjadi Public!
            DB::table('data_batches')->where('batch_id', $batchId)->update([
                'nama_file' => $request->nama_file,
                'jenis_data' => $request->jenis_data,
                'updated_at' => now(),
            ]);

            // Jika jenis data berubah, sinkronkan nilai jenis_transaksi pada seluruh baris transaksi di batch ini
            if ($batch->jenis_data !== $request->jenis_data) {
                DB::table('transactions')->where('batch_id', $batchId)->update([
                    'jenis_transaksi' => $request->jenis_data,
                    'updated_at' => now(),
                ]);
            }

            // Catat log aktivitas
            DB::table('activity_logs')->insert([
                'user_id' => $currentUser ? $currentUser->id : 1,
                'aktivitas' => 'Edit Data Batch',
                'modul' => 'Manajemen Data',
                'deskripsi' => "Memperbarui informasi batch #{$batchId} (Nama: {$request->nama_file}, Jenis: {$request->jenis_data})",
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            DB::commit();

            $pesan = "Data dokumen '{$request->nama_file}' berhasil diperbarui.";
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $pesan,
                    'data' => [
                        'batch_id' => $batchId,
                        'nama_file' => $request->nama_file,
                        'jenis_data' => $request->jenis_data,
                        'status' => $batch->status,
                    ],
                ]);
            }

            return redirect()->route('manajemen-data')->with('success', $pesan);

        } catch (\Throwable $e) {
            DB::rollBack();
            logger()->error('Gagal mengedit batch: ' . $e->getMessage());

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Gagal memperbarui data: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Gagal memperbarui data: ' . $e->getMessage());
        }
    }

    /**
     * Memindahkan data batch ke Trash (Soft Delete menggunakan tabel trash project).
     * Hanya Super Admin yang berwenang menghapus data.
     */
    public function destroy(Request $request, $batchId)
    {
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser && ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));

        if (!$isSuperAdmin) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak. Hanya Super Admin yang berwenang menghapus data.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak. Hanya Super Admin yang berwenang menghapus data.');
        }

        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        if (!$batch) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Data dokumen tidak ditemukan.'], 404);
            }
            return redirect()->back()->with('error', 'Data dokumen tidak ditemukan.');
        }

        $totalTransaksi = DB::table('transactions')->where('batch_id', $batchId)->count();

        DB::beginTransaction();
        try {
            // Buat snapshot data sebelum dimasukkan ke trash
            $snapshot = [
                'batch_id' => $batch->batch_id,
                'nama_file' => $batch->nama_file,
                'jenis_data' => $batch->jenis_data,
                'ukuran_file' => $batch->ukuran_file,
                'status' => $batch->status,
                'uploaded_by' => $batch->uploaded_by,
                'uploaded_at' => $batch->uploaded_at,
                'total_transaksi' => $totalTransaksi,
            ];

            // Simpan snapshot ke tabel trash
            DB::table('trash')->insert([
                'batch_id' => $batchId,
                'data_snapshot' => json_encode($snapshot),
                'deleted_by' => $currentUser->id,
                'deleted_at' => now(),
                'restored_by' => null,
                'restored_at' => null,
            ]);

            // Catat log aktivitas
            DB::table('activity_logs')->insert([
                'user_id' => $currentUser->id,
                'aktivitas' => 'Hapus Data Batch ke Trash',
                'modul' => 'Manajemen Data',
                'deskripsi' => "Memindahkan batch #{$batchId} ({$batch->nama_file}) ke Trash",
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            DB::commit();

            $pesan = "Dokumen '{$batch->nama_file}' berhasil dipindahkan ke Trash.";
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => $pesan]);
            }

            return redirect()->route('manajemen-data')->with('success', $pesan);

        } catch (\Throwable $e) {
            DB::rollBack();
            logger()->error('Gagal menghapus batch ke trash: ' . $e->getMessage());

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Gagal menghapus data: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }

    /**
     * Memulihkan data batch dari Trash ke daftar aktif Manajemen Data.
     * Hanya Super Admin yang berwenang memulihkan data.
     */
    public function restore(Request $request, $batchId)
    {
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser && ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));

        if (!$isSuperAdmin) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak. Hanya Super Admin yang berwenang memulihkan data.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak. Hanya Super Admin yang berwenang memulihkan data.');
        }

        $trashRecord = DB::table('trash')
            ->where('batch_id', $batchId)
            ->whereNull('restored_at')
            ->orderBy('trash_id', 'desc')
            ->first();

        if (!$trashRecord) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Dokumen tidak ditemukan di Trash.'], 404);
            }
            return redirect()->back()->with('error', 'Dokumen tidak ditemukan di Trash.');
        }

        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        $namaFile = $batch ? $batch->nama_file : 'Dokumen';

        DB::beginTransaction();
        try {
            DB::table('trash')->where('trash_id', $trashRecord->trash_id)->update([
                'restored_by' => $currentUser->id,
                'restored_at' => now(),
            ]);

            // Catat log aktivitas
            DB::table('activity_logs')->insert([
                'user_id' => $currentUser->id,
                'aktivitas' => 'Pulihkan Data Batch dari Trash',
                'modul' => 'Manajemen Data',
                'deskripsi' => "Memulihkan batch #{$batchId} ({$namaFile}) dari Trash",
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            DB::commit();

            $pesan = "Dokumen '{$namaFile}' berhasil dipulihkan dari Trash.";
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => $pesan]);
            }

            return redirect()->route('manajemen-data')->with('success', $pesan);

        } catch (\Throwable $e) {
            DB::rollBack();
            logger()->error('Gagal memulihkan batch: ' . $e->getMessage());

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Gagal memulihkan data: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Gagal memulihkan data: ' . $e->getMessage());
        }
    }

    /**
     * Menghapus permanen data batch dari Trash dan database.
     * Hanya Super Admin yang berwenang.
     */
    public function forceDelete(Request $request, $batchId)
    {
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser && ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));

        if (!$isSuperAdmin) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
            }
            return redirect()->back()->with('error', 'Akses ditolak.');
        }

        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        if (!$batch) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
            }
            return redirect()->back()->with('error', 'Data tidak ditemukan.');
        }

        DB::beginTransaction();
        try {
            // Hapus referensi di tabel trash
            DB::table('trash')->where('batch_id', $batchId)->delete();

            // Hapus batch (cascade akan menghapus transaksi terkait di tabel transactions)
            DB::table('data_batches')->where('batch_id', $batchId)->delete();

            // Catat log aktivitas
            DB::table('activity_logs')->insert([
                'user_id' => $currentUser->id,
                'aktivitas' => 'Hapus Permanen Data Batch',
                'modul' => 'Manajemen Data',
                'deskripsi' => "Menghapus permanen batch #{$batchId} ({$batch->nama_file}) beserta transaksi terkait",
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            DB::commit();

            $pesan = "Dokumen '{$batch->nama_file}' berhasil dihapus secara permanen.";
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => $pesan]);
            }

            return redirect()->route('manajemen-data')->with('success', $pesan);

        } catch (\Throwable $e) {
            DB::rollBack();
            logger()->error('Gagal hapus permanen batch: ' . $e->getMessage());

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Gagal menghapus permanen: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Gagal menghapus permanen: ' . $e->getMessage());
        }
    }
}

