<?php

namespace App\Http\Controllers;

use App\Services\StatisticsCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalisisController extends Controller
{
    protected StatisticsCalculationService $statService;

    public function __construct(StatisticsCalculationService $statService)
    {
        $this->statService = $statService;
    }

    /**
     * Menampilkan antarmuka analisis perdagangan ekspor-impor.
     */
    public function index(Request $request)
    {
        $availableYears = DB::table('transactions')
            ->join('data_batches', 'transactions.batch_id', '=', 'data_batches.batch_id')
            ->where('data_batches.status', 'RILIS')
            ->distinct()
            ->orderBy('transactions.tahun', 'desc')
            ->pluck('transactions.tahun')
            ->toArray();

        $selectedYear = (int) $request->input('tahun', !empty($availableYears) ? $availableYears[0] : date('Y'));
        $selectedJenis = $request->input('jenis', 'Ekspor');

        return view('pages.analisis-perdagangan', compact('availableYears', 'selectedYear', 'selectedJenis'));
    }

    /**
     * API JSON menyajikan data analitik agregat (Top Komoditas, Top Negara, Pelabuhan).
     */
    public function getData(Request $request)
    {
        $tahun = (int) $request->input('tahun', date('Y'));
        $jenis = $request->input('jenis', 'Ekspor');

        // Query dasar transaksi berstatus RILIS
        $baseQuery = DB::table('transactions')
            ->join('data_batches', 'transactions.batch_id', '=', 'data_batches.batch_id')
            ->where('data_batches.status', 'RILIS')
            ->where('transactions.jenis_transaksi', $jenis)
            ->where('transactions.tahun', $tahun);

        $grandTotalFob = (clone $baseQuery)->sum('transactions.nilai') ?: 1.0;

        // 1. Top 10 Komoditas berdasarkan Golongan HS (hs2_desc)
        $topKomoditas = (clone $baseQuery)
            ->leftJoin('master_hs', 'transactions.hs_id', '=', 'master_hs.hs_id')
            ->select(
                DB::raw("COALESCE(master_hs.hs2, 'LAIN') as hs2"),
                DB::raw("COALESCE(master_hs.hs2_desc, 'Lain-lain') as komoditas"),
                DB::raw("SUM(transactions.nilai) as total_fob"),
                DB::raw("SUM(transactions.berat) as total_berat")
            )
            ->groupBy('hs2', 'komoditas')
            ->orderByDesc('total_fob')
            ->limit(10)
            ->get()
            ->map(function ($item) use ($grandTotalFob) {
                $item->total_fob = (float) $item->total_fob;
                $item->total_fob_juta = round($item->total_fob / 1000000, 2);
                $item->share_persen = round(($item->total_fob / $grandTotalFob) * 100, 2);
                return $item;
            });

        // 2. Top 10 Negara Mitra Dagang Utama
        $topNegara = (clone $baseQuery)
            ->leftJoin('master_negara', 'transactions.negara_id', '=', 'master_negara.negara_id')
            ->select(
                DB::raw("COALESCE(master_negara.nama_negara, 'Lainnya') as negara"),
                DB::raw("COALESCE(master_negara.kode_negara, 'OTH') as kode_negara"),
                DB::raw("SUM(transactions.nilai) as total_fob"),
                DB::raw("SUM(transactions.berat) as total_berat")
            )
            ->groupBy('negara', 'kode_negara')
            ->orderByDesc('total_fob')
            ->limit(10)
            ->get()
            ->map(function ($item) use ($grandTotalFob) {
                $item->total_fob = (float) $item->total_fob;
                $item->total_fob_juta = round($item->total_fob / 1000000, 2);
                $item->share_persen = round(($item->total_fob / $grandTotalFob) * 100, 2);
                return $item;
            });

        // 3. Kontribusi Pelabuhan Muat/Bongkar
        $topPelabuhan = (clone $baseQuery)
            ->leftJoin('master_pelabuhan', 'transactions.pelabuhan_id', '=', 'master_pelabuhan.pelabuhan_id')
            ->select(
                DB::raw("COALESCE(master_pelabuhan.nama_pelabuhan, 'Lainnya') as pelabuhan"),
                DB::raw("SUM(transactions.nilai) as total_fob"),
                DB::raw("SUM(transactions.berat) as total_berat")
            )
            ->groupBy('pelabuhan')
            ->orderByDesc('total_fob')
            ->limit(5)
            ->get()
            ->map(function ($item) use ($grandTotalFob) {
                $item->total_fob = (float) $item->total_fob;
                $item->total_fob_juta = round($item->total_fob / 1000000, 2);
                $item->share_persen = round(($item->total_fob / $grandTotalFob) * 100, 2);
                return $item;
            });

        // 4. Metrik resmi Dashboard
        $metrics = $this->statService->getOfficialDashboardMetrics($tahun, $jenis);

        return response()->json([
            'success' => true,
            'tahun' => $tahun,
            'jenis' => $jenis,
            'grand_total_fob' => (float) $grandTotalFob,
            'grand_total_fob_juta' => round($grandTotalFob / 1000000, 2),
            'top_komoditas' => $topKomoditas,
            'top_negara' => $topNegara,
            'top_pelabuhan' => $topPelabuhan,
            'growth' => [
                'mtom' => $metrics['mtom'],
                'qtoq' => $metrics['qtoq'],
                'yony' => $metrics['yony'],
                'ctoc' => $metrics['ctoc'],
            ],
            'monthly_trend' => $metrics['monthly_trend'],
        ]);
    }
}
