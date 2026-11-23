<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use App\Services\NeracaUploadService;
use Exception;

/**
 * Controller untuk Dashboard Neraca Perdagangan (BRS & Analisis Makro).
 * Khusus mengolah dan menampilkan data transaksi dengan tipe_sumber = 'neraca'
 * yang berstatus 'RILIS' dan bukan berkas di trash.
 */
class NeracaController extends Controller
{
    /**
     * Tampilkan Halaman Antarmuka Dashboard Neraca.
     */
    public function index(Request $request): View
    {
        // Ambil tahun-tahun yang tersedia khusus sumber Neraca yang RILIS dari neraca_transactions
        $availableYears = DB::table('neraca_transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->where('b.status', 'RILIS')
            ->where('t.tipe_sumber', 'neraca')
            ->where('b.tipe_sumber', 'neraca')
            ->whereNotIn('t.batch_id', function ($sub) {
                $sub->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->distinct()
            ->orderBy('t.tahun', 'desc')
            ->pluck('t.tahun')
            ->toArray();

        $selectedYear = (int)$request->input('tahun', !empty($availableYears) ? $availableYears[0] : 2026);

        return view('pages.neraca-dashboard', [
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
        ]);
    }

    /**
     * API JSON Metrik Data Dashboard Neraca Perdagangan.
     */
    public function getMetrics(Request $request): JsonResponse
    {
        $tahun = (int)$request->input('tahun', 2026);

        // Kueri dasar transaksi Neraca berstatus RILIS dan non-trash dari neraca_transactions
        $baseQuery = DB::table('neraca_transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->where('b.status', 'RILIS')
            ->where('t.tipe_sumber', 'neraca')
            ->where('b.tipe_sumber', 'neraca')
            ->whereNotIn('t.batch_id', function ($sub) {
                $sub->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->where('t.tahun', $tahun);

        // 1. Total Ekspor & Impor Tahunan
        $eksporQuery = (clone $baseQuery)->where('t.jenis_transaksi', 'Ekspor');
        $imporQuery = (clone $baseQuery)->where('t.jenis_transaksi', 'Impor');

        $hasEkspor = (clone $eksporQuery)->exists();
        $hasImpor = (clone $imporQuery)->exists();
        $hasData = ($hasEkspor || $hasImpor);
        $hasCompleteTradeBalance = ($hasEkspor && $hasImpor);

        $totalEksporFob = (float)$eksporQuery->sum('t.nilai');
        $totalEksporNetto = (float)$eksporQuery->sum('t.berat');
        $totalImporCif = (float)$imporQuery->sum('t.nilai');
        $totalImporNetto = (float)$imporQuery->sum('t.berat');

        // Logika Metodologis Neraca Perdagangan:
        // 1. Surplus/Defisit hanya boleh dihitung jika kedua sisi (Ekspor dan Impor) sama-sama tersedia.
        // 2. Jika hanya Ekspor tersedia: "Data Impor Belum Tersedia"
        // 3. Jika hanya Impor tersedia: "Data Ekspor Belum Tersedia"
        // 4. Jika keduanya tidak tersedia: "Data Belum Tersedia"
        if ($hasCompleteTradeBalance) {
            $surplusDefisit = $totalEksporFob - $totalImporCif;
            if ($surplusDefisit > 0) {
                $statusNeraca = 'Surplus';
            } elseif ($surplusDefisit < 0) {
                $statusNeraca = 'Defisit';
            } else {
                $statusNeraca = 'Seimbang';
            }
        } else {
            $surplusDefisit = null;
            if ($hasEkspor && !$hasImpor) {
                $statusNeraca = 'Data Impor Belum Tersedia';
            } elseif (!$hasEkspor && $hasImpor) {
                $statusNeraca = 'Data Ekspor Belum Tersedia';
            } else {
                $statusNeraca = 'Data Belum Tersedia';
            }
        }

        // 2. Perkembangan Bulanan (Januari - Desember)
        $namaBulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $monthlyEkspor = array_fill(1, 12, 0.0);
        $monthlyImpor = array_fill(1, 12, 0.0);
        $monthlyNeraca = array_fill(1, 12, 0.0);

        $eksporByMonth = (clone $eksporQuery)
            ->select('t.bulan', DB::raw('SUM(t.nilai) as total_val'))
            ->groupBy('t.bulan')
            ->pluck('total_val', 't.bulan')
            ->toArray();

        $imporByMonth = (clone $imporQuery)
            ->select('t.bulan', DB::raw('SUM(t.nilai) as total_val'))
            ->groupBy('t.bulan')
            ->pluck('total_val', 't.bulan')
            ->toArray();

        for ($m = 1; $m <= 12; $m++) {
            $eVal = (float)($eksporByMonth[$m] ?? 0.0);
            $iVal = (float)($imporByMonth[$m] ?? 0.0);
            $monthlyEkspor[$m] = $eVal;
            $monthlyImpor[$m] = $iVal;
            // Hanya isi nilai neraca bulanan jika kedua sisi tersedia atau ada data aktif
            $hasMonthE = isset($eksporByMonth[$m]);
            $hasMonthI = isset($imporByMonth[$m]);
            $monthlyNeraca[$m] = ($hasMonthE && $hasMonthI) ? ($eVal - $iVal) : 0.0;
        }

        // 3. Klasifikasi Sektor (Industri Pengolahan, Pertanian, Pertambangan, dll.)
        $sektorEkspor = (clone $eksporQuery)
            ->leftJoin('master_hs as h', 't.hs_id', '=', 'h.hs_id')
            ->select(
                DB::raw("COALESCE(h.sektor, 'Lainnya') as sektor_nama"),
                DB::raw('SUM(t.nilai) as total_fob'),
                DB::raw('SUM(t.berat) as total_netto')
            )
            ->groupBy('sektor_nama')
            ->orderByDesc('total_fob')
            ->get();

        // 4. Klasifikasi Migas vs Non-Migas (Berdasarkan HS2 = 27 atau Sektor Minyak & Gas)
        $migasEkspor = (clone $eksporQuery)
            ->leftJoin('master_hs as h', 't.hs_id', '=', 'h.hs_id')
            ->where(function ($q) {
                $q->where('h.hs2', '27')->orWhere('h.sektor', 'like', '%Gas%');
            })
            ->sum('t.nilai');
        $nonMigasEkspor = max(0, $totalEksporFob - (float)$migasEkspor);

        $migasImpor = (clone $imporQuery)
            ->leftJoin('master_hs as h', 't.hs_id', '=', 'h.hs_id')
            ->where(function ($q) {
                $q->where('h.hs2', '27')->orWhere('h.sektor', 'like', '%Gas%');
            })
            ->sum('t.nilai');
        $nonMigasImpor = max(0, $totalImporCif - (float)$migasImpor);

        // 5. Klasifikasi BEC (Broad Economic Categories) jika tersedia
        $becEkspor = (clone $eksporQuery)
            ->leftJoin('master_hs as h', 't.hs_id', '=', 'h.hs_id')
            ->whereNotNull('h.bec_desc')
            ->select(
                'h.bec_desc as kategori_bec',
                DB::raw('SUM(t.nilai) as total_fob'),
                DB::raw('SUM(t.berat) as total_netto')
            )
            ->groupBy('h.bec_desc')
            ->orderByDesc('total_fob')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'tahun' => $tahun,
            'has_data' => $hasData,
            'has_ekspor' => $hasEkspor,
            'has_impor' => $hasImpor,
            'has_complete_trade_balance' => $hasCompleteTradeBalance,
            'summary' => [
                'total_ekspor_fob' => $totalEksporFob,
                'total_ekspor_fob_juta' => round($totalEksporFob / 1000000, 2),
                'total_ekspor_netto' => $totalEksporNetto,
                'total_impor_cif' => $totalImporCif,
                'total_impor_cif_juta' => round($totalImporCif / 1000000, 2),
                'total_impor_netto' => $totalImporNetto,
                'neraca_perdagangan' => $surplusDefisit,
                'neraca_juta' => $surplusDefisit !== null ? round($surplusDefisit / 1000000, 2) : null,
                'surplus_defisit' => $surplusDefisit,
                'status_neraca' => $statusNeraca,
                'migas_ekspor' => (float)$migasEkspor,
                'non_migas_ekspor' => (float)$nonMigasEkspor,
                'migas_impor' => (float)$migasImpor,
                'non_migas_impor' => (float)$nonMigasImpor,
            ],
            'monthly' => [
                'labels' => array_slice($namaBulan, 1),
                'ekspor' => array_values($monthlyEkspor),
                'impor' => array_values($monthlyImpor),
                'neraca' => array_values($monthlyNeraca),
            ],
            'sektor' => $sektorEkspor,
            'bec' => $becEkspor,
        ]);
    }

    /**
     * Tampilkan Halaman Antarmuka Upload Khusus Neraca (Super Admin).
     */
    public function uploadView(): View
    {
        $this->ensureSuperAdmin();

        return view('pages.neraca-upload');
    }

    /**
     * Endpoint API Proses Upload Berkas dan Menghasilkan Pratinjau (Preview).
     * TIDAK MELAKUKAN INGESTION / INSERT KE DATABASE.
     */
    public function previewUpload(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin();

        $request->validate([
            'file' => 'required|file|max:102400', // max 100MB
            'source_type' => 'required|in:ekspor,impor',
        ], [
            'file.required' => 'Silakan pilih berkas Excel Neraca (.xlsx).',
            'file.max' => 'Ukuran berkas tidak boleh melebihi 100 MB.',
            'source_type.required' => 'Pilih jenis data transaksi (Ekspor atau Impor).',
            'source_type.in' => 'Jenis transaksi harus berupa Ekspor atau Impor.',
        ]);

        $uploadedFile = $request->file('file');
        $sourceType = $request->input('source_type');

        // Validasi ekstensi
        $ext = strtolower($uploadedFile->getClientOriginalExtension());
        if ($ext !== 'xlsx') {
            return response()->json([
                'success' => false,
                'message' => 'Format berkas tidak valid. Harap unggah berkas berekstensi .xlsx khusus modul Neraca.',
            ], 422);
        }

        // Simpan sementara di storage temp terisolasi untuk preview
        $tempDir = 'temp_neraca_preview';
        $tempFileName = 'preview_' . uniqid() . '_' . time() . '.xlsx';
        $storedPath = $uploadedFile->storeAs($tempDir, $tempFileName, 'local');
        $fullPath = Storage::disk('local')->path($storedPath);

        try {
            $service = new NeracaUploadService();
            // Default 15 rows untuk sampel awal
            $preview = $service->generatePreview($fullPath, $sourceType, 15);

            // Simpan token referensi file ke session agar pagination bisa membaca tanpa re-upload
            session([
                'neraca_preview_file' => $fullPath,
                'neraca_preview_source' => $sourceType,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Berkas berhasil dibaca dan diverifikasi.',
                'preview' => $preview,
            ]);

        } catch (Exception $e) {
            // Hapus file sementara jika gagal diproses
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca berkas: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Endpoint API Pagination untuk Baris Transaksi Pratinjau.
     */
    public function paginatedPreview(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin();

        $filePath = session('neraca_preview_file');
        $sourceType = session('neraca_preview_source', 'ekspor');

        if (!$filePath || !file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi pratinjau telah kedaluwarsa atau berkas tidak ditemukan. Silakan unggah kembali berkas Anda.',
            ], 404);
        }

        $page = max(1, (int)$request->input('page', 1));
        $perPage = min(100, max(5, (int)$request->input('per_page', 20)));

        try {
            $service = new NeracaUploadService();
            $pagination = $service->getPaginatedRows($filePath, $sourceType, $page, $perPage);

            return response()->json([
                'success' => true,
                'pagination' => $pagination,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca halaman data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Memastikan hanya Super Admin yang dapat mengakses fungsi upload/preview Neraca.
     */
    protected function ensureSuperAdmin(): void
    {
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser && ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));

        if (!$isSuperAdmin) {
            abort(403, 'Akses ditolak. Halaman upload dan preview Neraca hanya dapat diakses oleh Super Admin.');
        }
    }
}
