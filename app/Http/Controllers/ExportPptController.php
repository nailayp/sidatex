<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\PowerPointExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExportPptController extends Controller
{
    protected PowerPointExportService $pptService;

    public function __construct(PowerPointExportService $pptService)
    {
        $this->pptService = $pptService;
    }

    /**
     * Mengunduh slide presentasi PowerPoint BRS BPS Riau untuk batch terpilih.
     */
    public function export(Request $request, $batchId)
    {
        $currentUser = Auth::user();
        if (!$currentUser || $currentUser->role_id !== 1) {
            abort(403, 'Akses ditolak. Pembuatan bahan presentasi PowerPoint BRS hanya diizinkan bagi Super Admin.');
        }

        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        if (!$batch) {
            abort(404, 'Data dokumen tidak ditemukan.');
        }

        try {
            $filePath = $this->pptService->generateBrsSlideDeck((int) $batchId);

            ActivityLogger::log(
                'Ekspor PowerPoint BRS',
                'Analisis',
                "Mengunduh bahan presentasi BRS untuk batch #{$batchId} ({$batch->nama_file})"
            );

            $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $batch->nama_file);
            $filename = "BRS_Statistik_Riau_{$safeName}.pptx";

            return response()->download($filePath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ])->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            logger()->error('Gagal generate PowerPoint BRS: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal membuat bahan presentasi PowerPoint: ' . $e->getMessage());
        }
    }
}
