<?php

namespace App\Http\Controllers;

use App\Services\DistributionStatisticsService;
use App\Services\DistributionUploadService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Controller for Distribusi Ekspor & Impor (Upload, Preview, Process, and Dashboard API).
 */
class DistributionController extends Controller
{
    protected DistributionUploadService $uploadService;
    protected DistributionStatisticsService $statisticsService;

    public function __construct(
        DistributionUploadService $uploadService,
        DistributionStatisticsService $statisticsService
    ) {
        $this->uploadService = $uploadService;
        $this->statisticsService = $statisticsService;
    }

    /**
     * Tampilkan Halaman Dashboard Distribusi.
     */
    public function dashboardIndex(Request $request): View
    {
        $availablePeriods = $this->statisticsService->getAvailablePeriods();
        
        // Default tahun dan bulan terbaru
        $selectedYear = (int)$request->input('tahun', 2026);
        $selectedMonth = (int)$request->input('bulan', 9);

        return view('pages.distribusi-dashboard', [
            'availablePeriods' => $availablePeriods,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
        ]);
    }

    /**
     * Endpoint API Data Metrik Dashboard Distribusi.
     */
    public function getMetrics(Request $request): JsonResponse
    {
        $tahun = (int)$request->input('tahun', 2026);
        $bulan = (int)$request->input('bulan', 9);

        try {
            $ekspor = $this->statisticsService->getSummaryMetrics($tahun, $bulan, 'ekspor');
            $impor = $this->statisticsService->getSummaryMetrics($tahun, $bulan, 'impor');
            $neraca = $this->statisticsService->getDistributionTradeBalance($tahun, $bulan);
            $sektor = $this->statisticsService->getSektorBreakdown($tahun, $bulan);
            $topComm = $this->statisticsService->getTopCommodities($tahun, $bulan);
            $topCountries = $this->statisticsService->getTopCountries($tahun, $bulan, 'ekspor');
            $bec = $this->statisticsService->getImportBecBreakdown($tahun, $bulan);
            $origin = $this->statisticsService->getOriginDistribution($tahun, $bulan);

            return response()->json([
                'success' => true,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'ekspor' => $ekspor,
                'impor' => $impor,
                'neraca' => $neraca,
                'sektor' => $sektor,
                'top_commodities' => $topComm,
                'top_countries' => $topCountries,
                'bec_impor' => $bec,
                'origin_wilayah' => $origin,
            ]);
        } catch (\Throwable $e) {
            Log::error('Gagal mengambil metrik distribusi', [
                'tahun' => $tahun,
                'bulan' => $bulan,
                'user_id' => Auth::id(),
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal saat memuat metrik distribusi.',
            ], 500);
        }
    }
}
