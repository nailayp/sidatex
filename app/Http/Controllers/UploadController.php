<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class UploadController extends Controller
{
    public function index()
    {
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser && ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));

        if (!$isSuperAdmin) {
            abort(403, 'Akses ditolak. Fitur Upload Data hanya dapat diakses oleh Super Admin.');
        }

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
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser && ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));

        if (!$isSuperAdmin) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak. Pengunggahan data hanya dapat dilakukan oleh Super Admin.'], 403);
            }
            abort(403, 'Akses ditolak. Pengunggahan data hanya dapat dilakukan oleh Super Admin.');
        }

        $file = $request->file('excel_file_final');
        $originalName = $file ? $file->getClientOriginalName() : '';
        $ext = $file ? strtolower($file->getClientOriginalExtension()) : '';
        if (!$ext && $originalName) {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        }

        // Jika ekstensi tidak terbaca (misal di Windows), cek byte header DBF langsung (0x03)
        $isDbf = ($ext === 'dbf');
        if (!$isDbf && $file && file_exists($file->getRealPath())) {
            $fp = @fopen($file->getRealPath(), 'rb');
            if ($fp) {
                $firstByte = ord(fread($fp, 1));
                fclose($fp);
                if (in_array($firstByte, [0x03, 0x83, 0x8B])) {
                    $isDbf = true;
                }
            }
        }

        // JIKA BERKAS ADALAH RAW DBF (DISTRIBUSI / NERACA)
        if ($isDbf) {
            $request->validate([
                'excel_file_final' => 'required|file|max:51200',
                'jenis_data_final' => 'required|string',
                'sumber_data_final' => 'nullable|string',
            ], [
                'excel_file_final.required' => 'File DBF wajib dipilih sebelum menyimpan.',
                'excel_file_final.max' => 'Ukuran file tidak boleh melebihi 50 MB.',
                'jenis_data_final.required' => 'Jenis data (Ekspor/Impor) wajib ditentukan.',
            ]);

            $namaFile = $file->getClientOriginalName();
            $jenis = strtolower($request->input('jenis_data_final'));
            $sumber = strtolower($request->input('sumber_data_final', 'distribusi'));
            if (!$sumber) {
                $sumber = 'distribusi';
            }

            $statusInput = strtoupper(trim((string) $request->input('status_final', 'PRIVATE')));
            $currentUser = Auth::user();
            $isAuthorizedToRelease = $currentUser && ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));

            // Status default simpan: PRIVATE (tidak langsung RILIS kecuali dipilih Super Admin)
            if (($statusInput === 'RILIS' || $statusInput === 'PUBLIC') && $isAuthorizedToRelease) {
                $statusDb = 'RILIS';
            } else {
                $statusDb = 'PRIVATE';
            }

            try {
                $brsService = app(\App\Services\BrsDataProcessingService::class);
                $result = $brsService->storeBatchAndTransactions(
                    $file->getRealPath(),
                    $namaFile,
                    $sumber,
                    $jenis,
                    Auth::id() ?? 1,
                    $statusDb
                );

                $pesanSukses = "File {$namaFile} (" . round($file->getSize() / 1048576, 2) . " MB) berhasil diproses dan disimpan ke basis data (Batch #{$result['batch_id']}) dengan status {$statusDb}. Agregasi Mart BRS selesai.";

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => $pesanSukses,
                        'data' => [
                            'batch_id' => $result['batch_id'],
                            'nama_file' => $namaFile,
                            'sumber_data' => $sumber,
                            'status' => $statusDb,
                            'total_transactions' => $result['total_baris'] ?? 0,
                            'total_nilai_juta_usd' => $result['total_nilai_juta_usd'] ?? 0,
                            'total_berat_ribu_ton' => $result['total_berat_ribu_ton'] ?? 0,
                            'migas_juta_usd' => $result['migas_juta_usd'] ?? 0,
                            'non_migas_juta_usd' => $result['non_migas_juta_usd'] ?? 0,
                            'unmapped_count' => $result['unmapped_count'] ?? 0,
                            'aggregation' => $result['aggregation'] ?? null,
                        ],
                        'redirect_url' => route('manajemen-data'),
                    ]);
                }

                return redirect()->route('manajemen-data')->with('success', $pesanSukses);
            } catch (\Throwable $e) {
                logger()->error('Gagal memproses batch DBF BRS: ' . $e->getMessage());

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal memproses berkas DBF: ' . $e->getMessage(),
                    ], 422);
                }

                return redirect()->back()->withInput()->with('error', 'Gagal memproses berkas DBF: ' . $e->getMessage());
            }
        }

        // 1. Validasi Input (Pastikan file yang diunggah benar-benar Excel dengan MIME & ekstensi valid)
        $validated = $request->validate([
            'excel_file_final' => 'required|file|extensions:xlsx,xls|mimes:xlsx,xls|max:51200', // max 50MB
            'jenis_data_final' => 'required|string',
        ], [
            'excel_file_final.required' => 'File Excel wajib dipilih sebelum menyimpan.',
            'excel_file_final.file' => 'Unggahan harus berupa file yang valid.',
            'excel_file_final.extensions' => 'File harus berekstensi .xlsx atau .xls.',
            'excel_file_final.mimes' => 'Format konten file harus berupa dokumen Excel (.xlsx atau .xls) yang valid.',
            'excel_file_final.max' => 'Ukuran file tidak boleh melebihi 50 MB.',
            'jenis_data_final.required' => 'Jenis data (Ekspor/Impor) wajib ditentukan.',
        ]);

        $file = $request->file('excel_file_final');
        $rawOriginalName = basename((string) $file->getClientOriginalName());
        $safeOriginalName = preg_replace('/[^a-zA-Z0-9._\-\s()]/', '_', $rawOriginalName) ?: 'upload.xlsx';
        $safeOriginalName = str_replace('..', '_', $safeOriginalName);
        $storedFilename = time() . '_' . $safeOriginalName;
        $ukuranMB = round($file->getSize() / 1048576, 2);

        // Petakan status_final ke ENUM database ('DRAFT', 'PRIVATE', 'NON-RILIS', 'RILIS')
        $statusInput = strtoupper(trim((string) $request->input('status_final', 'PRIVATE')));
        $currentUser = Auth::user();
        $isAuthorizedToRelease = $currentUser && ((int) $currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));

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

        // Simpan file fisik secara aman ke disk private ('local' -> storage/app/private)
        $storedPath = $file->storeAs('uploads/excel', $storedFilename, 'local');
        $fullPath = Storage::disk('local')->path($storedPath);

        // 2. Gunakan Database Transaction agar penyimpanan data_batches dan transactions konsisten
        DB::beginTransaction();
        try {
            // A. Simpan data batch
            $batchId = DB::table('data_batches')->insertGetId([
                'nama_file' => $storedFilename,
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

            // B. Parse baris Excel dan simpan ke tabel distribusi_transactions
            $parser = new \App\Services\ExcelTransactionParser();
            $totalInserted = $parser->parseAndInsert(
                $fullPath,
                $batchId,
                $request->jenis_data_final
            );

            // C. Commit transaksi jika semua berhasil
            DB::commit();

            $pesanSukses = "File {$safeOriginalName} ({$ukuranMB} MB) berhasil disimpan ke database beserta " .
                           number_format($totalInserted, 0, ',', '.') .
                           " transaksi dengan status {$statusDb}.";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $pesanSukses,
                    'data' => [
                        'batch_id' => $batchId,
                        'nama_file' => $storedFilename,
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

            // Hapus file dari private storage agar tidak meninggalkan berkas orphan saat rollback
            if (!empty($storedPath) && Storage::disk('local')->exists($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

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

    public function manajemen(Request $request)
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

        // Query dengan filter pencarian dan status jika diberikan
        $filteredQuery = clone $baseQuery;

        if ($request->filled('q')) {
            $keyword = trim($request->q);
            $filteredQuery->where(function ($q) use ($keyword) {
                $q->where('data_batches.nama_file', 'like', "%{$keyword}%")
                  ->orWhere('data_batches.batch_id', 'like', "%{$keyword}%")
                  ->orWhere('data_batches.jenis_data', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'ALL') {
            $filteredQuery->where('data_batches.status', strtoupper($request->status));
        }

        $perPage = in_array((int) $request->input('per_page', 10), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 10)
            : 10;

        // Ambil data batches aktif beserta jumlah baris transaksi yang berelasi (mendukung Distribusi & Neraca)
        $dokumens = $filteredQuery
            ->leftJoin('distribusi_transactions', function ($join) {
                $join->on('data_batches.batch_id', '=', 'distribusi_transactions.batch_id')
                     ->where('data_batches.tipe_sumber', '=', 'distribusi');
            })
            ->leftJoin('neraca_transactions', function ($join) {
                $join->on('data_batches.batch_id', '=', 'neraca_transactions.batch_id')
                     ->where('data_batches.tipe_sumber', '=', 'neraca');
            })
            ->select(
                'data_batches.*',
                DB::raw('COALESCE(COUNT(distribusi_transactions.transaction_id), 0) + COALESCE(COUNT(neraca_transactions.transaction_id), 0) as total_transaksi')
            )
            ->groupBy('data_batches.batch_id')
            ->orderBy('data_batches.created_at', 'desc')
            ->orderBy('data_batches.batch_id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Ambil data Trash hanya untuk Super Admin agar metadata Trash tidak bocor ke Pegawai/Viewer
        if ($isSuperAdmin) {
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
        } else {
            $trashedItems = collect();
            $totalTrash = 0;
        }

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
    public function trash(Request $request)
    {
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser && ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));

        if (!$isSuperAdmin) {
            abort(403, 'Akses ditolak. Fitur Trash Data hanya dapat diakses oleh Super Admin.');
        }

        $perPage = in_array((int) $request->input('per_page', 10), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 10)
            : 10;

        $query = DB::table('trash')
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
            );

        if ($request->filled('q')) {
            $keyword = trim($request->q);
            $query->where(function ($q) use ($keyword) {
                $q->where('data_batches.nama_file', 'like', "%{$keyword}%")
                  ->orWhere('trash.batch_id', 'like', "%{$keyword}%")
                  ->orWhere('data_batches.jenis_data', 'like', "%{$keyword}%");
            });
        }

        $totalTrash = (clone $query)->count();

        $trashedItems = $query
            ->orderBy('trash.deleted_at', 'desc')
            ->orderBy('trash.trash_id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

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

        $isTrashed = DB::table('trash')
            ->where('batch_id', $batchId)
            ->whereNull('restored_at')
            ->exists();

        if ($isTrashed) {
            $msgTrashed = 'Status dokumen yang berada di Trash tidak dapat diubah.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msgTrashed], 422);
            }
            return redirect()->back()->with('error', $msgTrashed);
        }

        $isCurrentlyReleased = ($batch->status === 'RILIS');
        $newStatus = $isCurrentlyReleased ? 'PRIVATE' : 'RILIS';

        DB::beginTransaction();
        try {
            $brsAggregation = app(\App\Services\BrsAggregationService::class);
            $updated = $brsAggregation->updateBatchVisibility((int) $batchId, $newStatus, (int) $currentUser->id);

            if (!$updated) {
                throw new \RuntimeException("Gagal menyinkronkan status visibilitas batch #{$batchId}.");
            }

            DB::table('activity_logs')->insert([
                'user_id' => $currentUser->id,
                'aktivitas' => $isCurrentlyReleased ? 'Tarik Publikasi Batch Data' : 'Rilis Publikasi Batch Data',
                'modul' => 'Manajemen Data',
                'deskripsi' => "Mengubah status batch #{$batchId} ({$batch->nama_file}) menjadi {$newStatus}",
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            logger()->error("Gagal mengubah status rilis batch #{$batchId}: " . $e->getMessage());

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Gagal mengubah status publikasi data.'], 500);
            }
            return redirect()->back()->with('error', 'Gagal mengubah status publikasi data.');
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
     * Kebijakan RBAC (Server-Side):
     * - Super Admin (Role 1) & Viewer Eksekutif (Role 3): RILIS dan PRIVATE (aktif/non-Trash).
     * - Pegawai (Role 2): RILIS (aktif/non-Trash) saja.
     * - DRAFT dan Trash: Ditolak (HTTP 403) untuk seluruh role.
     */
    public function export(Request $request, $batchId)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $currentUser = Auth::user();
        if (!$currentUser) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        if (!$batch) {
            abort(404, 'Data dokumen tidak ditemukan.');
        }

        // Data yang berada di Trash dilarang diekspor untuk seluruh role
        $isTrashed = DB::table('trash')
            ->where('batch_id', $batchId)
            ->whereNull('restored_at')
            ->exists();

        if ($isTrashed) {
            abort(403, 'Akses ditolak. Data yang berada di Trash tidak dapat diekspor.');
        }

        $status = strtoupper(trim((string) ($batch->status ?? '')));
        if ($status === 'DRAFT') {
            abort(403, 'Akses ditolak. Data berstatus DRAFT tidak dapat diekspor.');
        }

        $roleId = (int) $currentUser->role_id;
        $roleName = strtolower(trim((string) ($currentUser->role->nama_role ?? '')));
        $isSuperAdmin = ($roleId === 1 || $roleName === 'super admin');
        $isViewerEksekutif = ($roleId === 3 || $roleName === 'viewer eksekutif');

        $allowedStatuses = ($isSuperAdmin || $isViewerEksekutif)
            ? ['RILIS', 'PRIVATE']
            : ['RILIS'];

        if (!in_array($status, $allowedStatuses, true)) {
            abort(403, "Akses ditolak. Anda tidak memiliki kewenangan mengunduh data berstatus {$status}.");
        }

        // Lepaskan session lock sesegera mungkin agar navigasi halaman lain (Dashboard, Upload Data, dll.)
        // dapat diakses seketika secara paralel tanpa menunggu proses download selesai.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename((string) $batch->nama_file));
        if (!str_ends_with(strtolower($safeName), '.xlsx')) {
            $safeName .= '.xlsx';
        }
        $downloadFilename = 'SIDATEX_Ekspor_' . $safeName;

        // Path file temporer di server
        $tempExcelPath = tempnam(sys_get_temp_dir(), 'sidatex_exp_') . '.xlsx';

        try {
            $exporter = new \App\Services\FastExcelExporter();
            $totalExported = $exporter->exportBatchToPath((int) $batchId, $tempExcelPath);

            // Catat aktivitas ekspor secara wajib (jangan samarkan kegagalan log untuk ekspor PRIVATE)
            $tipeSumber = $batch->tipe_sumber ?? 'distribusi';
            DB::table('activity_logs')->insert([
                'user_id' => $currentUser->id,
                'aktivitas' => 'Ekspor Data Batch',
                'modul' => 'Manajemen Data',
                'deskripsi' => "Mengunduh data batch #{$batchId} ({$batch->nama_file}) [Status: {$status}, Sumber: {$tipeSumber}] sebanyak {$totalExported} transaksi",
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

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

            return redirect()->back()->with('error', 'Gagal mengekspor data batch.');
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

        if (!$isSuperAdmin) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak. Pengubahan data batch hanya diizinkan bagi Super Admin.'], 403);
            }
            abort(403, 'Akses ditolak. Pengubahan data batch hanya diizinkan bagi Super Admin.');
        }

        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        if (!$batch) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Data dokumen tidak ditemukan.'], 404);
            }
            return redirect()->back()->with('error', 'Data dokumen tidak ditemukan.');
        }

        $request->validate([
            'nama_file' => [
                'required',
                'string',
                'max:255',
                'regex:/^[^\/\\\\]+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (str_contains((string) $value, '..')) {
                        $fail('Nama dokumen tidak boleh mengandung karakter traversal (..).');
                    }
                },
            ],
            'jenis_data' => 'required|in:Ekspor,Impor',
        ], [
            'nama_file.required' => 'Nama dokumen tidak boleh kosong.',
            'nama_file.regex' => 'Nama dokumen tidak boleh mengandung karakter pemisah path (/ atau \\).',
            'jenis_data.required' => 'Jenis data wajib dipilih.',
            'jenis_data.in' => 'Jenis data harus berupa Ekspor atau Impor.',
        ]);

        $tipeSumber = strtolower(trim((string) ($batch->tipe_sumber ?? 'distribusi')));
        $txTable = ($tipeSumber === 'neraca') ? 'neraca_transactions' : 'distribusi_transactions';
        $oldJenisData = (string) $batch->jenis_data;
        $jenisChanged = ($oldJenisData !== $request->jenis_data);
        $isTrashed = DB::table('trash')
            ->where('batch_id', $batchId)
            ->whereNull('restored_at')
            ->exists();
        $batchStatus = strtoupper(trim((string) ($batch->status ?? '')));
        $shouldResyncMart = $jenisChanged && !$isTrashed && in_array($batchStatus, ['PRIVATE', 'RILIS'], true);

        // Cegah tabrakan periode aktif saat mengubah jenis_data (Ekspor <-> Impor) pada batch aktif
        if ($jenisChanged && !$isTrashed) {
            $targetJenisLower = strtolower(trim((string) $request->jenis_data));
            $candidatePeriods = [];
            if ($batch->periode_tahun !== null && $batch->periode_bulan !== null) {
                $candidatePeriods[] = ['tahun' => (int) $batch->periode_tahun, 'bulan' => (int) $batch->periode_bulan];
            } else {
                $txPeriods = DB::table($txTable)
                    ->where('batch_id', $batchId)
                    ->select('tahun', 'bulan')
                    ->distinct()
                    ->get();
                foreach ($txPeriods as $tp) {
                    $candidatePeriods[] = ['tahun' => (int) $tp->tahun, 'bulan' => (int) $tp->bulan];
                }
            }

            foreach ($candidatePeriods as $period) {
                $conflictingBatch = DB::table('data_batches')
                    ->where('batch_id', '!=', $batchId)
                    ->where('tipe_sumber', $tipeSumber)
                    ->whereRaw('LOWER(jenis_data) = ?', [$targetJenisLower])
                    ->where('periode_tahun', $period['tahun'])
                    ->where('periode_bulan', $period['bulan'])
                    ->whereNotIn('batch_id', function ($sub) {
                        $sub->select('batch_id')->from('trash')->whereNull('restored_at');
                    })
                    ->first();

                if ($conflictingBatch) {
                    $msgConflict = "Gagal mengubah jenis data karena periode {$request->jenis_data} ({$tipeSumber}) {$period['bulan']}/{$period['tahun']} sudah memiliki batch aktif (#{$conflictingBatch->batch_id}).";
                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->json(['success' => false, 'message' => $msgConflict], 409);
                    }
                    return redirect()->back()->with('error', $msgConflict);
                }
            }
        }

        // Kumpulkan periode lama sebelum transaksi diubah agar origin summary jenis lama bersih tuntas
        $oldDistribusiPeriods = [];
        if ($shouldResyncMart && $tipeSumber === 'distribusi') {
            $oldDistribusiPeriods = DB::table($txTable)
                ->where('batch_id', $batchId)
                ->select('tahun', 'bulan')
                ->distinct()
                ->get()
                ->all();
        }

        DB::beginTransaction();
        try {
            $brsAggregation = $shouldResyncMart ? app(\App\Services\BrsAggregationService::class) : null;

            // PERTAHANKAN STATUS ASLI: Status Pending TIDAK BOLEH otomatis berubah menjadi Public!
            DB::table('data_batches')->where('batch_id', $batchId)->update([
                'nama_file' => $request->nama_file,
                'jenis_data' => $request->jenis_data,
                'updated_at' => now(),
            ]);

            // Jika jenis data berubah, sinkronkan nilai jenis_transaksi pada tabel transaksi sesuai metodologi batch
            // SEBELUM membersihkan mart/origin lama agar syncOriginSummariesForPeriod tidak meng-insert ulang jenis lama.
            if ($jenisChanged) {
                DB::table($txTable)->where('batch_id', $batchId)->update([
                    'jenis_transaksi' => $request->jenis_data,
                    'updated_at' => now(),
                ]);
            }

            if ($shouldResyncMart && $brsAggregation !== null) {
                $brsAggregation->removeBatchAggregation((int) $batchId);

                // Segarkan origin summaries jenis lama (distribusi) setelah transaksi sudah berganti jenis baru
                if ($tipeSumber === 'distribusi') {
                    foreach ($oldDistribusiPeriods as $p) {
                        $brsAggregation->syncOriginSummariesForPeriod((int) $p->tahun, (int) $p->bulan, $oldJenisData);
                    }
                }

                $brsAggregation->aggregateBatch((int) $batchId, $batchStatus);
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

        $alreadyTrashed = DB::table('trash')
            ->where('batch_id', $batchId)
            ->whereNull('restored_at')
            ->exists();

        if ($alreadyTrashed) {
            $msgAlreadyTrashed = 'Dokumen sudah berada di Trash.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msgAlreadyTrashed], 409);
            }
            return redirect()->back()->with('error', $msgAlreadyTrashed);
        }

        $tipeSumber = strtolower(trim((string) ($batch->tipe_sumber ?? 'distribusi')));
        $txTable = ($tipeSumber === 'neraca') ? 'neraca_transactions' : 'distribusi_transactions';
        $totalTransaksi = DB::table($txTable)->where('batch_id', $batchId)->count();

        DB::beginTransaction();
        try {
            // Buat snapshot data sebelum dimasukkan ke trash
            $snapshot = [
                'batch_id' => $batch->batch_id,
                'nama_file' => $batch->nama_file,
                'jenis_data' => $batch->jenis_data,
                'tipe_sumber' => $tipeSumber,
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

            // Keluarkan agregasi batch dari Analytical Mart secara atomik (tanpa menyentuh baris historis batch_id IS NULL)
            app(\App\Services\BrsAggregationService::class)->removeBatchAggregation((int) $batchId);

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

        // Cegah tabrakan periode aktif saat memulihkan batch dari Trash
        if ($batch && $batch->periode_tahun !== null && $batch->periode_bulan !== null) {
            $tipeSumber = strtolower(trim((string) ($batch->tipe_sumber ?? 'distribusi')));
            $jenisLower = strtolower(trim((string) ($batch->jenis_data ?? '')));
            $conflictingBatch = DB::table('data_batches')
                ->where('batch_id', '!=', $batchId)
                ->where('tipe_sumber', $tipeSumber)
                ->whereRaw('LOWER(jenis_data) = ?', [$jenisLower])
                ->where('periode_tahun', $batch->periode_tahun)
                ->where('periode_bulan', $batch->periode_bulan)
                ->whereNotIn('batch_id', function ($sub) {
                    $sub->select('batch_id')->from('trash')->whereNull('restored_at');
                })
                ->first();

            if ($conflictingBatch) {
                $msgConflict = "Gagal memulihkan dokumen karena periode {$batch->jenis_data} ({$tipeSumber}) {$batch->periode_bulan}/{$batch->periode_tahun} sudah memiliki batch aktif (#{$conflictingBatch->batch_id}).";
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'message' => $msgConflict], 409);
                }
                return redirect()->back()->with('error', $msgConflict);
            }
        }

        DB::beginTransaction();
        try {
            DB::table('trash')->where('trash_id', $trashRecord->trash_id)->update([
                'restored_by' => $currentUser->id,
                'restored_at' => now(),
            ]);

            // Jika status batch adalah PRIVATE atau RILIS, bangun ulang Two-Tier Analytical Mart secara atomik (tanpa menelan error)
            $batchStatus = $batch ? strtoupper(trim((string) ($batch->status ?? ''))) : '';
            if ($batch && in_array($batchStatus, ['PRIVATE', 'RILIS'], true)) {
                app(\App\Services\BrsAggregationService::class)->aggregateBatch((int) $batchId, $batchStatus);
            }

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

        $isTrashed = DB::table('trash')
            ->where('batch_id', $batchId)
            ->whereNull('restored_at')
            ->exists();

        if (!$isTrashed) {
            $msgNotTrashed = 'Hapus permanen hanya diizinkan untuk dokumen yang berada di Trash.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msgNotTrashed], 422);
            }
            return redirect()->back()->with('error', $msgNotTrashed);
        }

        DB::beginTransaction();
        try {
            // Pastikan seluruh baris mart milik batch ini bersih secara atomik sebelum menghapus data_batches
            app(\App\Services\BrsAggregationService::class)->removeBatchAggregation((int) $batchId);

            // Hapus referensi di tabel trash
            DB::table('trash')->where('batch_id', $batchId)->delete();

            // Hapus batch (cascade akan menghapus transaksi terkait di tabel distribusi_transactions / neraca_transactions)
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

            // Bersihkan file fisik di private storage ('local') HANYA setelah commit sukses
            // dalam blok try/catch terpisah agar kegagalan filesystem tidak memicu DB::rollBack() pasca-commit.
            try {
                $rawName = (string) ($batch->nama_file ?? '');
                $safeFilename = basename($rawName);
                $isSafeFilename = $rawName !== ''
                    && $safeFilename === $rawName
                    && $safeFilename !== '.'
                    && $safeFilename !== '..'
                    && !str_contains($rawName, '..')
                    && !str_contains($rawName, '/')
                    && !str_contains($rawName, '\\');

                if ($isSafeFilename) {
                    $stillReferenced = DB::table('data_batches')
                        ->where('nama_file', $safeFilename)
                        ->exists();

                    if (!$stillReferenced) {
                        $allowedPrivateDirs = [
                            'uploads/excel',
                            'uploads/raw_distribusi',
                            'uploads/raw_neraca',
                        ];

                        foreach ($allowedPrivateDirs as $relDir) {
                            $relPath = $relDir . '/' . $safeFilename;
                            if (!Storage::disk('local')->exists($relPath)) {
                                continue;
                            }

                            $realBase = realpath(Storage::disk('local')->path($relDir));
                            $realTarget = realpath(Storage::disk('local')->path($relPath));

                            if (
                                $realBase !== false
                                && $realTarget !== false
                                && str_starts_with($realTarget, $realBase . DIRECTORY_SEPARATOR)
                                && is_file($realTarget)
                            ) {
                                Storage::disk('local')->delete($relPath);
                            }
                        }
                    }
                }
            } catch (\Throwable $fsErr) {
                logger()->warning("Batch #{$batchId} berhasil dihapus dari DB, namun gagal membersihkan arsip fisik: " . $fsErr->getMessage());
            }

            $pesan = "Dokumen '{$batch->nama_file}' berhasil dihapus secara permanen.";
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => $pesan]);
            }

            return redirect()->route('manajemen-data')->with('success', $pesan);

        } catch (\Throwable $e) {
            DB::rollBack();
            logger()->error('Gagal hapus permanen batch: ' . $e->getMessage());

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Gagal menghapus permanen batch.'], 500);
            }

            return redirect()->back()->with('error', 'Gagal menghapus permanen batch.');
        }
    }
}

