<?php

namespace App\Http\Controllers;

use App\Services\StatisticsCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected StatisticsCalculationService $statService;

    public function __construct(StatisticsCalculationService $statService)
    {
        $this->statService = $statService;
    }

    /**
     * Halaman Dashboard Utama Resmi SIDATEX.
     * Menyajikan 3 Halaman Dashboard Terintegrasi dengan filter Triwulan, Bulan, Tahun, Komoditi, Kab/Kota, Negara, Pelabuhan.
     */
    public function index(Request $request)
    {
        $tahun = $request->filled('tahun') && $request->tahun !== 'ALL' ? (int) $request->tahun : null;
        $jenis = $request->input('jenis', 'Ekspor');

        $metrics = $this->statService->getOfficialDashboardMetrics($tahun, $jenis);

        $availableYears = DB::table('transactions')
            ->join('data_batches', 'transactions.batch_id', '=', 'data_batches.batch_id')
            ->where('data_batches.status', 'RILIS')
            ->distinct()
            ->orderBy('transactions.tahun', 'desc')
            ->pluck('transactions.tahun')
            ->toArray();

        $komoditis = DB::table('master_hs')
            ->whereNotNull('hs2_desc')
            ->select('hs2', 'hs2_desc')
            ->distinct()
            ->orderBy('hs2')
            ->get();

        $kabkotas = DB::table('master_kabkota')
            ->orderBy('nama_kabkota')
            ->get();

        $negaras = DB::table('master_negara')
            ->orderBy('nama_negara')
            ->get();

        $pelabahans = DB::table('master_pelabuhan')
            ->orderBy('nama_pelabuhan')
            ->get();

        return view('dashboard', compact('metrics', 'availableYears', 'jenis', 'komoditis', 'kabkotas', 'negaras', 'pelabahans'));
    }

    /**
     * API JSON Ringkasan Metrik Resmi Dashboard.
     */
    public function metrics(Request $request)
    {
        $tahun = $request->filled('tahun') && $request->tahun !== 'ALL' ? (int) $request->tahun : null;
        $jenis = $request->input('jenis', 'Ekspor');

        $metrics = $this->statService->getOfficialDashboardMetrics($tahun, $jenis);

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * API JSON Data Lengkap Terfilter untuk 3 Halaman Dashboard.
     */
    public function data(Request $request)
    {
        $query = DB::table('transactions')
            ->join('data_batches', 'transactions.batch_id', '=', 'data_batches.batch_id')
            ->leftJoin('master_hs', 'transactions.hs_id', '=', 'master_hs.hs_id')
            ->leftJoin('master_negara', 'transactions.negara_id', '=', 'master_negara.negara_id')
            ->leftJoin('master_kabkota', 'transactions.kabkota_id', '=', 'master_kabkota.kabkota_id')
            ->leftJoin('master_pelabuhan', 'transactions.pelabuhan_id', '=', 'master_pelabuhan.pelabuhan_id')
            ->where('data_batches.status', 'RILIS');

        // Filter Jenis Transaksi (Default Ekspor)
        $jenis = $request->input('jenis', 'Ekspor');
        if ($jenis !== 'ALL') {
            $query->where('transactions.jenis_transaksi', $jenis);
        }

        // Filter Tahun
        if ($request->filled('tahun') && $request->tahun !== 'ALL') {
            $query->where('transactions.tahun', (int) $request->tahun);
        }

        // Filter Bulan (1-12)
        if ($request->filled('bulan') && $request->bulan !== 'ALL') {
            $query->where('transactions.bulan', (int) $request->bulan);
        }

        // Filter Triwulan (1, 2, 3, 4 atau Tw-I, Tw-II, Tw-III, Tw-IV)
        if ($request->filled('triwulan') && $request->triwulan !== 'ALL') {
            $tw = $request->triwulan;
            $monthRange = [];
            if ($tw == '1' || $tw == 'Tw-I' || $tw == 'Triwulan I') $monthRange = [1, 2, 3];
            elseif ($tw == '2' || $tw == 'Tw-II' || $tw == 'Triwulan II') $monthRange = [4, 5, 6];
            elseif ($tw == '3' || $tw == 'Tw-III' || $tw == 'Triwulan III') $monthRange = [7, 8, 9];
            elseif ($tw == '4' || $tw == 'Tw-IV' || $tw == 'Triwulan IV') $monthRange = [10, 11, 12];

            if (!empty($monthRange)) {
                $query->whereIn('transactions.bulan', $monthRange);
            }
        }

        // Filter Komoditi (HS2 atau HS Code)
        if ($request->filled('komoditi') && $request->komoditi !== 'ALL') {
            $query->where(function($q) use ($request) {
                $q->where('master_hs.hs2', $request->komoditi)
                  ->orWhere('master_hs.hs2_desc', 'like', "%{$request->komoditi}%")
                  ->orWhere('master_hs.hs_code', $request->komoditi);
            });
        }

        // Filter Kab/Kota
        if ($request->filled('kabkota_id') && $request->kabkota_id !== 'ALL') {
            $query->where('transactions.kabkota_id', (int) $request->kabkota_id);
        }

        // Filter Negara
        if ($request->filled('negara_id') && $request->negara_id !== 'ALL') {
            $query->where('transactions.negara_id', (int) $request->negara_id);
        }

        // Filter Pelabuhan
        if ($request->filled('pelabuhan_id') && $request->pelabuhan_id !== 'ALL') {
            $query->where('transactions.pelabuhan_id', (int) $request->pelabuhan_id);
        }

        // Filter Migas/Non Migas
        if ($request->filled('migas') && $request->migas !== 'ALL') {
            if ($request->migas === 'Migas') {
                $query->where(function($q) {
                    $q->where('master_hs.sektor', 'like', '%Gas%')
                      ->orWhere('master_hs.hs2', '27');
                });
            } else {
                $query->where(function($q) {
                    $q->whereNull('master_hs.sektor')
                      ->orWhere('master_hs.sektor', 'not like', '%Gas%')
                      ->where('master_hs.hs2', '!=', '27');
                });
            }
        }

        $cloneQuery = clone $query;

        // KPI Ringkasan
        $totalFob = (float) $cloneQuery->sum('transactions.nilai');
        $totalNetto = (float) $cloneQuery->sum('transactions.berat');
        $totalRows = (int) $cloneQuery->count();
        $avgPerTransaksi = $totalRows > 0 ? round($totalFob / $totalRows, 2) : 0;
        $totalNegara = (int) (clone $query)->distinct()->count('transactions.negara_id');
        $totalKomoditi = (int) (clone $query)->distinct()->count('transactions.hs_id');

        // Nilai Migas vs Non-Migas
        $migasFob = (float) (clone $query)->where('master_hs.hs2', '27')->sum('transactions.nilai');
        $nonMigasFob = max(0, $totalFob - $migasFob);
        $migasNetto = (float) (clone $query)->where('master_hs.hs2', '27')->sum('transactions.berat');
        $nonMigasNetto = max(0, $totalNetto - $migasNetto);

        // Agregasi Bulanan (Chronological 1-12)
        $namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $bulanAgg = (clone $query)
            ->select(
                'transactions.bulan',
                DB::raw('SUM(transactions.nilai) as fob'),
                DB::raw('SUM(transactions.berat) as netto'),
                DB::raw("SUM(CASE WHEN master_hs.hs2 = '27' THEN transactions.nilai ELSE 0 END) as migas_fob"),
                DB::raw("SUM(CASE WHEN master_hs.hs2 != '27' OR master_hs.hs2 IS NULL THEN transactions.nilai ELSE 0 END) as non_migas_fob"),
                DB::raw("SUM(CASE WHEN master_hs.hs2 = '27' THEN transactions.berat ELSE 0 END) as migas_netto"),
                DB::raw("SUM(CASE WHEN master_hs.hs2 != '27' OR master_hs.hs2 IS NULL THEN transactions.berat ELSE 0 END) as non_migas_netto")
            )
            ->groupBy('transactions.bulan')
            ->orderBy('transactions.bulan')
            ->get()
            ->keyBy('bulan');

        $monthlyChronological = [
            'labels' => [],
            'fobValues' => [],
            'nettoValues' => [],
            'migasFob' => [],
            'nonMigasFob' => [],
            'migasNetto' => [],
            'nonMigasNetto' => []
        ];

        for ($m = 1; $m <= 12; $m++) {
            $monthlyChronological['labels'][] = $namaBulan[$m];
            $row = $bulanAgg->get($m);
            $monthlyChronological['fobValues'][] = $row ? (float) $row->fob : 0;
            $monthlyChronological['nettoValues'][] = $row ? (float) $row->netto : 0;
            $monthlyChronological['migasFob'][] = $row ? (float) $row->migas_fob : 0;
            $monthlyChronological['nonMigasFob'][] = $row ? (float) $row->non_migas_fob : 0;
            $monthlyChronological['migasNetto'][] = $row ? (float) $row->migas_netto : 0;
            $monthlyChronological['nonMigasNetto'][] = $row ? (float) $row->non_migas_netto : 0;
        }

        // Agregasi Triwulanan (Q1 - Q4)
        $quarterlyChronological = [
            'labels' => ['Triwulan I (Jan-Mar)', 'Triwulan II (Apr-Jun)', 'Triwulan III (Jul-Sep)', 'Triwulan IV (Okt-Des)'],
            'fobValues' => [
                array_sum(array_slice($monthlyChronological['fobValues'], 0, 3)),
                array_sum(array_slice($monthlyChronological['fobValues'], 3, 3)),
                array_sum(array_slice($monthlyChronological['fobValues'], 6, 3)),
                array_sum(array_slice($monthlyChronological['fobValues'], 9, 3)),
            ],
            'nettoValues' => [
                array_sum(array_slice($monthlyChronological['nettoValues'], 0, 3)),
                array_sum(array_slice($monthlyChronological['nettoValues'], 3, 3)),
                array_sum(array_slice($monthlyChronological['nettoValues'], 6, 3)),
                array_sum(array_slice($monthlyChronological['nettoValues'], 9, 3)),
            ],
            'migasFob' => [
                array_sum(array_slice($monthlyChronological['migasFob'], 0, 3)),
                array_sum(array_slice($monthlyChronological['migasFob'], 3, 3)),
                array_sum(array_slice($monthlyChronological['migasFob'], 6, 3)),
                array_sum(array_slice($monthlyChronological['migasFob'], 9, 3)),
            ],
            'nonMigasFob' => [
                array_sum(array_slice($monthlyChronological['nonMigasFob'], 0, 3)),
                array_sum(array_slice($monthlyChronological['nonMigasFob'], 3, 3)),
                array_sum(array_slice($monthlyChronological['nonMigasFob'], 6, 3)),
                array_sum(array_slice($monthlyChronological['nonMigasFob'], 9, 3)),
            ],
            'migasNetto' => [
                array_sum(array_slice($monthlyChronological['migasNetto'], 0, 3)),
                array_sum(array_slice($monthlyChronological['migasNetto'], 3, 3)),
                array_sum(array_slice($monthlyChronological['migasNetto'], 6, 3)),
                array_sum(array_slice($monthlyChronological['migasNetto'], 9, 3)),
            ],
            'nonMigasNetto' => [
                array_sum(array_slice($monthlyChronological['nonMigasNetto'], 0, 3)),
                array_sum(array_slice($monthlyChronological['nonMigasNetto'], 3, 3)),
                array_sum(array_slice($monthlyChronological['nonMigasNetto'], 6, 3)),
                array_sum(array_slice($monthlyChronological['nonMigasNetto'], 9, 3)),
            ],
        ];

        // Top 10 Komoditas Unggulan
        $topKomoditi = (clone $query)
            ->select(
                DB::raw("COALESCE(master_hs.hs2_desc, master_hs.hs_desc, 'Lain-lain') as item"),
                DB::raw('SUM(transactions.nilai) as fob'),
                DB::raw('SUM(transactions.berat) as netto')
            )
            ->groupBy('item')
            ->orderByDesc('fob')
            ->limit(10)
            ->get();

        // Top 10 Negara Tujuan
        $topNegara = (clone $query)
            ->select(
                DB::raw("COALESCE(master_negara.nama_negara, 'Lainnya') as item"),
                DB::raw('SUM(transactions.nilai) as fob'),
                DB::raw('SUM(transactions.berat) as netto')
            )
            ->groupBy('item')
            ->orderByDesc('fob')
            ->limit(10)
            ->get();

        // Top Pelabuhan Muat
        $topPelabuhan = (clone $query)
            ->select(
                DB::raw("COALESCE(master_pelabuhan.nama_pelabuhan, 'Lainnya') as item"),
                DB::raw('SUM(transactions.nilai) as fob'),
                DB::raw('SUM(transactions.berat) as netto')
            )
            ->groupBy('item')
            ->orderByDesc('fob')
            ->limit(10)
            ->get();

        // Distribusi Ekspor per Kabupaten/Kota (Untuk Peta & Visualisasi Gradasi)
        $kabkotaDistribusi = (clone $query)
            ->select(
                DB::raw("COALESCE(master_kabkota.nama_kabkota, 'Daerah Lain') as kabkota"),
                DB::raw('SUM(transactions.nilai) as fob'),
                DB::raw('SUM(transactions.berat) as netto')
            )
            ->groupBy('kabkota')
            ->orderByDesc('fob')
            ->get();

        // Time Series Pivot Komoditas per Bulan & Triwulan (Halaman 2: FOB & Netto)
        $topCommodities = (clone $query)
            ->select('master_hs.hs2_desc as item', DB::raw('SUM(transactions.nilai) as total_fob'), DB::raw('SUM(transactions.berat) as total_netto'))
            ->whereNotNull('master_hs.hs2_desc')
            ->groupBy('master_hs.hs2_desc')
            ->orderByDesc('total_fob')
            ->limit(15)
            ->get();

        $pivotBulanFob = [];
        $pivotBulanNetto = [];
        $pivotTriwulanFob = [];

        foreach ($topCommodities as $commObj) {
            $comm = $commObj->item;
            $rowMonthsFob = [];
            $rowMonthsNetto = [];
            $rowQuartersFob = [0, 0, 0, 0];

            $commData = (clone $query)
                ->where('master_hs.hs2_desc', $comm)
                ->select('transactions.bulan', DB::raw('SUM(transactions.nilai) as fob'), DB::raw('SUM(transactions.berat) as netto'))
                ->groupBy('transactions.bulan')
                ->get()
                ->keyBy('bulan');

            $lastVal = null;
            $momGrowth = null;

            for ($m = 1; $m <= 12; $m++) {
                $fobVal = $commData->has($m) ? (float) $commData->get($m)->fob : 0;
                $nettoVal = $commData->has($m) ? (float) $commData->get($m)->netto : 0;
                $rowMonthsFob[] = $fobVal;
                $rowMonthsNetto[] = $nettoVal;

                $qIdx = (int) floor(($m - 1) / 3);
                $rowQuartersFob[$qIdx] += $fobVal;

                if ($fobVal > 0) {
                    if ($lastVal !== null && $lastVal > 0) {
                        $momGrowth = round((($fobVal - $lastVal) / $lastVal) * 100, 2);
                    }
                    $lastVal = $fobVal;
                }
            }

            $pivotBulanFob[] = [
                'komoditi' => $comm,
                'values' => $rowMonthsFob,
                'total' => array_sum($rowMonthsFob),
                'mom_growth' => $momGrowth
            ];

            $pivotBulanNetto[] = [
                'komoditi' => $comm,
                'values' => $rowMonthsNetto,
                'total' => array_sum($rowMonthsNetto)
            ];

            $pivotTriwulanFob[] = [
                'komoditi' => $comm,
                'values' => $rowQuartersFob,
                'total' => array_sum($rowQuartersFob)
            ];
        }

        // Metrik pertumbuhan M-to-M, Q-to-Q, Y-on-Y, C-to-C
        $metricsOfficial = $this->statService->getOfficialDashboardMetrics($request->filled('tahun') && $request->tahun !== 'ALL' ? (int) $request->tahun : null, $jenis);

        return response()->json([
            'success' => true,
            'summary' => [
                'total_fob' => $totalFob,
                'total_netto' => $totalNetto,
                'avg_per_transaksi' => $avgPerTransaksi,
                'total_negara' => $totalNegara,
                'total_komoditi' => $totalKomoditi,
                'migas_fob' => $migasFob,
                'non_migas_fob' => $nonMigasFob,
                'migas_netto' => $migasNetto,
                'non_migas_netto' => $nonMigasNetto,
                'mtom' => $metricsOfficial['mtom'],
                'qtoq' => $metricsOfficial['qtoq'],
                'yony' => $metricsOfficial['yony'],
                'ctoc' => $metricsOfficial['ctoc'],
            ],
            'monthly' => $monthlyChronological,
            'quarterly' => $quarterlyChronological,
            'top_komoditi' => $topKomoditi,
            'top_negara' => $topNegara,
            'top_pelabuhan' => $topPelabuhan,
            'kabkota_distribusi' => $kabkotaDistribusi,
            'pivot_bulan' => $pivotBulanFob, // fallback
            'pivot_triwulan' => $pivotTriwulanFob, // fallback
            'pivot_bulan_fob' => $pivotBulanFob,
            'pivot_bulan_netto' => $pivotBulanNetto,
            'pivot_triwulan_fob' => $pivotTriwulanFob,
        ]);
    }
}
