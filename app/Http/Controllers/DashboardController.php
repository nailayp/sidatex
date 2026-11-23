<?php

namespace App\Http\Controllers;

use App\Services\StatisticsCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    protected StatisticsCalculationService $statService;

    public function __construct(StatisticsCalculationService $statService)
    {
        $this->statService = $statService;
    }

    /**
     * Halaman Dashboard Tunggal Terpadu Resmi SIDATEX.
     * Mengintegrasikan 4 Tab Analisis:
     * 1. Overview Global & Tren Perdagangan
     * 2. Tabulasi Time Series Komoditas
     * 3. Detail Analisis & Pemetaan Wilayah
     * 4. Neraca Perdagangan (Trade Balance)
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $allowedStatuses = $this->statService->getAllowedStatusesForUser($user);
        $canViewPrivate = in_array('PRIVATE', $allowedStatuses, true);

        $initialSumber = strtolower((string) $request->input('sumber', 'distribusi'));
        if (!in_array($initialSumber, ['distribusi', 'neraca'], true)) {
            $initialSumber = 'distribusi';
        }

        $initialJenis = $request->input('jenis', 'Ekspor');
        if (!in_array($initialJenis, ['Ekspor', 'Impor', 'Neraca'], true)) {
            $initialJenis = 'Ekspor';
        }

        $tahun = $request->filled('tahun') && $request->tahun !== 'ALL' ? (int) $request->tahun : null;

        // Ambil daftar tahun unik dari kedua sumber (terfilter visibilitas & eksklusi Trash)
        $distYears = DB::table('distribusi_transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->whereIn('b.status', $allowedStatuses)
            ->where('t.tipe_sumber', 'distribusi')
            ->where('b.tipe_sumber', 'distribusi')
            ->whereNotIn('t.batch_id', function ($sub) {
                $sub->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->distinct()
            ->pluck('t.tahun')
            ->toArray();

        $neracaYears = DB::table('neraca_transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->whereIn('b.status', $allowedStatuses)
            ->where('t.tipe_sumber', 'neraca')
            ->where('b.tipe_sumber', 'neraca')
            ->whereNotIn('t.batch_id', function ($sub) {
                $sub->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->distinct()
            ->pluck('t.tahun')
            ->toArray();

        $historicalYearsQuery = DB::table('monthly_trade_summaries as mts');
        $this->statService->applyMonthlySummaryVisibility($historicalYearsQuery, $allowedStatuses, 'mts');
        $historicalYears = $historicalYearsQuery
            ->distinct()
            ->pluck('mts.tahun')
            ->toArray();

        $availableYears = array_values(array_unique(array_merge($distYears, $neracaYears, $historicalYears)));
        rsort($availableYears);
        if (empty($availableYears)) {
            $availableYears = [2026, 2025, 2024, 2023];
        }

        $selectedTahun = $tahun ?? $availableYears[0];

        // Master lookup untuk dropdown filter
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

        // Ringkasan ringkas berdampingan untuk kartu ikhtisar metodologi
        $distribusiSummary = DB::table('distribusi_transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->whereIn('b.status', $allowedStatuses)
            ->where('t.tipe_sumber', 'distribusi')
            ->where('b.tipe_sumber', 'distribusi')
            ->whereNotIn('t.batch_id', function ($sub) {
                $sub->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->select(
                't.jenis_transaksi',
                DB::raw('COUNT(*) as total_records'),
                DB::raw('SUM(t.nilai) as total_nilai'),
                DB::raw('SUM(t.berat) as total_berat'),
                DB::raw('MAX(t.tahun) as max_tahun'),
                DB::raw('MAX(t.bulan) as max_bulan')
            )
            ->groupBy('t.jenis_transaksi')
            ->get()
            ->keyBy('jenis_transaksi');

        $neracaSummary = DB::table('neraca_transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->whereIn('b.status', $allowedStatuses)
            ->where('t.tipe_sumber', 'neraca')
            ->where('b.tipe_sumber', 'neraca')
            ->whereNotIn('t.batch_id', function ($sub) {
                $sub->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->select(
                't.jenis_transaksi',
                DB::raw('COUNT(*) as total_records'),
                DB::raw('SUM(t.nilai) as total_nilai'),
                DB::raw('SUM(t.berat) as total_berat'),
                DB::raw('MAX(t.tahun) as max_tahun'),
                DB::raw('MAX(t.bulan) as max_bulan')
            )
            ->groupBy('t.jenis_transaksi')
            ->get()
            ->keyBy('jenis_transaksi');

        $activeBatchQuery = fn (string $source) => DB::table('data_batches')
            ->where('tipe_sumber', $source)
            ->whereNotIn('batch_id', function ($sub) {
                $sub->select('batch_id')->from('trash')->whereNull('restored_at');
            });

        $distRilis = $activeBatchQuery('distribusi')->where('status', 'RILIS')->count();
        $neracaRilis = $activeBatchQuery('neraca')->where('status', 'RILIS')->count();

        if ($canViewPrivate) {
            $batchStats = [
                'distribusi_total' => $activeBatchQuery('distribusi')->whereIn('status', $allowedStatuses)->count(),
                'distribusi_rilis' => $distRilis,
                'distribusi_private' => $activeBatchQuery('distribusi')->where('status', 'PRIVATE')->count(),
                'neraca_total' => $activeBatchQuery('neraca')->whereIn('status', $allowedStatuses)->count(),
                'neraca_rilis' => $neracaRilis,
                'neraca_private' => $activeBatchQuery('neraca')->where('status', 'PRIVATE')->count(),
            ];
        } else {
            // Pegawai (Role 2) tidak boleh menerima metadata jumlah batch PRIVATE/Trash
            $batchStats = [
                'distribusi_total' => $distRilis,
                'distribusi_rilis' => $distRilis,
                'distribusi_private' => 0,
                'neraca_total' => $neracaRilis,
                'neraca_rilis' => $neracaRilis,
                'neraca_private' => 0,
            ];
        }

        return view('dashboard', compact(
            'availableYears',
            'selectedTahun',
            'initialSumber',
            'initialJenis',
            'komoditis',
            'kabkotas',
            'negaras',
            'pelabahans',
            'distribusiSummary',
            'neracaSummary',
            'batchStats',
            'canViewPrivate'
        ));
    }

    /**
     * API JSON Ringkasan Metrik Resmi Dashboard.
     */
    public function metrics(Request $request): JsonResponse
    {
        $tahun = $request->filled('tahun') && $request->tahun !== 'ALL' ? (int) $request->tahun : null;
        $jenis = $request->input('jenis', 'Ekspor');
        $tipeSumber = strtolower((string) $request->input('tipe_sumber', 'distribusi'));
        $allowedStatuses = $this->statService->getAllowedStatusesForUser(Auth::user());

        $metrics = $this->statService->getOfficialDashboardMetrics($tahun, $jenis, $tipeSumber, $allowedStatuses);

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * API JSON Data Lengkap Terfilter untuk 4 Tab Dashboard Tunggal SIDATEX.
     */
    public function data(Request $request): JsonResponse
    {
        $tipeSumber = strtolower((string) $request->input('tipe_sumber', 'distribusi'));
        if (!in_array($tipeSumber, ['distribusi', 'neraca'], true)) {
            $tipeSumber = 'distribusi';
        }

        $jenis = $request->input('jenis', 'Ekspor');
        if (!in_array($jenis, ['Ekspor', 'Impor', 'Neraca', 'ALL'], true)) {
            $jenis = 'Ekspor';
        }

        $user = Auth::user();
        $allowedStatuses = $this->statService->getAllowedStatusesForUser($user);
        $canViewPrivate = in_array('PRIVATE', $allowedStatuses, true);

        $txTable = ($tipeSumber === 'neraca') ? 'neraca_transactions' : 'distribusi_transactions';

        // Query dasar transaksi aktif
        $baseQuery = DB::table($txTable . ' as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->leftJoin('master_hs as h', 't.hs_id', '=', 'h.hs_id')
            ->leftJoin('master_negara as n', 't.negara_id', '=', 'n.negara_id')
            ->leftJoin('master_kabkota as k', 't.kabkota_id', '=', 'k.kabkota_id')
            ->leftJoin('master_pelabuhan as p', 't.pelabuhan_id', '=', 'p.pelabuhan_id')
            ->whereIn('b.status', $allowedStatuses)
            ->where('t.tipe_sumber', $tipeSumber)
            ->where('b.tipe_sumber', $tipeSumber)
            ->whereNotIn('t.batch_id', function ($sub) {
                $sub->select('batch_id')->from('trash')->whereNull('restored_at');
            });

        // Filter Tahun
        if ($request->filled('tahun') && $request->tahun !== 'ALL') {
            $baseQuery->where('t.tahun', (int) $request->tahun);
        }

        // Filter Bulan (1-12)
        if ($request->filled('bulan') && $request->bulan !== 'ALL') {
            $baseQuery->where('t.bulan', (int) $request->bulan);
        }

        // Filter Triwulan (1, 2, 3, 4 atau Tw-I s.d. Tw-IV)
        if ($request->filled('triwulan') && $request->triwulan !== 'ALL') {
            $tw = (string) $request->triwulan;
            $monthRange = [];
            if ($tw === '1' || $tw === 'Tw-I' || $tw === 'Triwulan I') $monthRange = [1, 2, 3];
            elseif ($tw === '2' || $tw === 'Tw-II' || $tw === 'Triwulan II') $monthRange = [4, 5, 6];
            elseif ($tw === '3' || $tw === 'Tw-III' || $tw === 'Triwulan III') $monthRange = [7, 8, 9];
            elseif ($tw === '4' || $tw === 'Tw-IV' || $tw === 'Triwulan IV') $monthRange = [10, 11, 12];

            if (!empty($monthRange)) {
                $baseQuery->whereIn('t.bulan', $monthRange);
            }
        }

        // Filter Komoditas (HS2 atau Deskripsi)
        if ($request->filled('komoditi') && $request->komoditi !== 'ALL') {
            $baseQuery->where(function ($q) use ($request) {
                $q->where('h.hs2', $request->komoditi)
                    ->orWhere('h.hs2_desc', 'like', "%{$request->komoditi}%")
                    ->orWhere('h.hs_code', $request->komoditi);
            });
        }

        // Filter Wilayah Kab/Kota
        if ($request->filled('kabkota_id') && $request->kabkota_id !== 'ALL') {
            $baseQuery->where('t.kabkota_id', (int) $request->kabkota_id);
        }

        // Filter Negara Mitra
        if ($request->filled('negara_id') && $request->negara_id !== 'ALL') {
            $baseQuery->where('t.negara_id', (int) $request->negara_id);
        }

        // Filter Pelabuhan
        if ($request->filled('pelabuhan_id') && $request->pelabuhan_id !== 'ALL') {
            $baseQuery->where('t.pelabuhan_id', (int) $request->pelabuhan_id);
        }

        // Filter Kategori Migas / Non-Migas
        if ($request->filled('migas') && $request->migas !== 'ALL') {
            if ($request->migas === 'Migas') {
                $baseQuery->where(function ($q) {
                    $q->where('h.sektor', 'like', '%Gas%')
                        ->orWhere('h.hs2', '27');
                });
            } else {
                $baseQuery->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('h.sektor')
                            ->orWhere('h.sektor', 'not like', '%Gas%');
                    })->where(function ($sub2) {
                        $sub2->whereNull('h.hs2')
                            ->orWhere('h.hs2', '!=', '27');
                    });
                });
            }
        }

        // ─────────────────────────────────────────────────────────────────
        // KOMPILASI DATA SPESIFIK TRANSAKSI (Default: Ekspor atau Impor)
        // ─────────────────────────────────────────────────────────────────
        $activeJenis = ($jenis === 'Neraca' || $jenis === 'ALL') ? 'Ekspor' : $jenis;
        $query = (clone $baseQuery)->where('t.jenis_transaksi', $activeJenis);

        // 1. KPI Ringkasan Utama Tab 1
        $totalFob = (float) (clone $query)->sum('t.nilai');
        $totalNetto = (float) (clone $query)->sum('t.berat');
        $totalRows = (int) (clone $query)->count();
        $avgPerTransaksi = $totalRows > 0 ? round($totalFob / $totalRows, 2) : 0;
        $totalNegara = (int) (clone $query)->distinct()->count('t.negara_id');
        $totalKomoditi = (int) (clone $query)->distinct()->count('t.hs_id');

        // Nilai & Berat Migas vs Non-Migas
        $migasFob = (float) (clone $query)->where('h.hs2', '27')->sum('t.nilai');
        $nonMigasFob = max(0, $totalFob - $migasFob);
        $migasNetto = (float) (clone $query)->where('h.hs2', '27')->sum('t.berat');
        $nonMigasNetto = max(0, $totalNetto - $migasNetto);

        // 2. Agregasi Kronologis Bulanan (1-12)
        $namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $bulanAgg = (clone $query)
            ->select(
                't.bulan',
                DB::raw('SUM(t.nilai) as fob'),
                DB::raw('SUM(t.berat) as netto'),
                DB::raw("SUM(CASE WHEN h.hs2 = '27' THEN t.nilai ELSE 0 END) as migas_fob"),
                DB::raw("SUM(CASE WHEN h.hs2 != '27' OR h.hs2 IS NULL THEN t.nilai ELSE 0 END) as non_migas_fob"),
                DB::raw("SUM(CASE WHEN h.hs2 = '27' THEN t.berat ELSE 0 END) as migas_netto"),
                DB::raw("SUM(CASE WHEN h.hs2 != '27' OR h.hs2 IS NULL THEN t.berat ELSE 0 END) as non_migas_netto")
            )
            ->groupBy('t.bulan')
            ->orderBy('t.bulan')
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

        // 3. Agregasi Triwulanan (Q1 - Q4)
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

        // 4. Top 10 Komoditas Unggulan
        $topKomoditi = (clone $query)
            ->select(
                DB::raw("COALESCE(h.hs2_desc, h.hs_desc, 'Lain-lain') as item"),
                DB::raw('SUM(t.nilai) as fob'),
                DB::raw('SUM(t.berat) as netto')
            )
            ->groupBy('item')
            ->orderByDesc('fob')
            ->limit(10)
            ->get();

        // 5. Top 10 Negara Mitra
        $topNegara = (clone $query)
            ->select(
                DB::raw("COALESCE(n.nama_negara, 'Lainnya') as item"),
                DB::raw('SUM(t.nilai) as fob'),
                DB::raw('SUM(t.berat) as netto')
            )
            ->groupBy('item')
            ->orderByDesc('fob')
            ->limit(10)
            ->get();

        // 6. Top 10 Pelabuhan Muat/Bongkar
        $topPelabuhan = (clone $query)
            ->select(
                DB::raw("COALESCE(p.nama_pelabuhan, 'Lainnya') as item"),
                DB::raw('SUM(t.nilai) as fob'),
                DB::raw('SUM(t.berat) as netto')
            )
            ->groupBy('item')
            ->orderByDesc('fob')
            ->limit(10)
            ->get();

        // 7. Distribusi per Kabupaten/Kota Riau (Peta Leaflet & Bar Chart)
        $kabkotaDistribusi = (clone $query)
            ->select(
                DB::raw("COALESCE(k.nama_kabkota, 'Daerah Lain') as kabkota"),
                DB::raw('SUM(t.nilai) as fob'),
                DB::raw('SUM(t.berat) as netto')
            )
            ->groupBy('kabkota')
            ->orderByDesc('fob')
            ->get();

        // 8. Tabulasi Time Series Komoditas (Tab 2: Bulanan & Triwulanan)
        $topCommodities = (clone $query)
            ->select('h.hs2_desc as item', DB::raw('SUM(t.nilai) as total_fob'), DB::raw('SUM(t.berat) as total_netto'))
            ->whereNotNull('h.hs2_desc')
            ->groupBy('h.hs2_desc')
            ->orderByDesc('total_fob')
            ->limit(15)
            ->get();

        $pivotBulanFob = [];
        $pivotBulanNetto = [];
        $pivotTriwulanFob = [];
        $pivotTriwulanNetto = [];

        foreach ($topCommodities as $commObj) {
            $comm = $commObj->item;
            $rowMonthsFob = [];
            $rowMonthsNetto = [];
            $rowQuartersFob = [0, 0, 0, 0];
            $rowQuartersNetto = [0, 0, 0, 0];

            $commData = (clone $query)
                ->where('h.hs2_desc', $comm)
                ->select('t.bulan', DB::raw('SUM(t.nilai) as fob'), DB::raw('SUM(t.berat) as netto'))
                ->groupBy('t.bulan')
                ->get()
                ->keyBy('bulan');

            $momGrowth = null;
            $latestCommMonth = null;

            for ($m = 1; $m <= 12; $m++) {
                $fobVal = $commData->has($m) ? (float) $commData->get($m)->fob : 0.0;
                $nettoVal = $commData->has($m) ? (float) $commData->get($m)->netto : 0.0;
                $rowMonthsFob[] = $fobVal;
                $rowMonthsNetto[] = $nettoVal;

                $qIdx = (int) floor(($m - 1) / 3);
                $rowQuartersFob[$qIdx] += $fobVal;
                $rowQuartersNetto[$qIdx] += $nettoVal;

                if ($fobVal > 0) {
                    $latestCommMonth = $m;
                }
            }

            // Hitung MoM komoditas HANYA terhadap bulan tepat sebelumnya ($latestCommMonth - 1)
            if ($latestCommMonth !== null && $latestCommMonth > 1 && $commData->has($latestCommMonth - 1)) {
                $currStr = bcadd((string) $commData->get($latestCommMonth)->fob, '0', 6);
                $prevStr = bcadd((string) $commData->get($latestCommMonth - 1)->fob, '0', 6);
                $momGrowth = $this->statService->calcBpsGrowth($currStr, $prevStr);
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

            $pivotTriwulanNetto[] = [
                'komoditi' => $comm,
                'values' => $rowQuartersNetto,
                'total' => array_sum($rowQuartersNetto)
            ];
        }

        // 9. Tabel Analisis Pertumbuhan (Tab 3: MoM & QoQ sesuai metodologi BPS dan Kebijakan B)
        $growthMomTable = [];
        $runningCumulativeMom = '0.000000';
        for ($m = 1; $m <= 12; $m++) {
            $currRow = $bulanAgg->get($m);
            $valStr = $currRow ? bcadd((string) $currRow->fob, '0', 6) : '0.000000';
            if (bccomp($valStr, '0', 6) > 0) {
                $runningCumulativeMom = bcadd($runningCumulativeMom, $valStr, 6);
                // MoM wajib membandingkan terhadap bulan tepat sebelumnya ($m - 1), bukan bulan non-nol terpisah
                $prevRow = ($m > 1) ? $bulanAgg->get($m - 1) : null;
                $prevStr = $prevRow ? bcadd((string) $prevRow->fob, '0', 6) : null;
                $growthPct = ($prevStr !== null && bccomp($prevStr, '0', 6) > 0)
                    ? $this->statService->calcBpsGrowth($valStr, $prevStr)
                    : null;

                $growthMomTable[] = [
                    'periode' => $namaBulan[$m],
                    'nilai' => (float) $valStr,
                    'growth' => $growthPct,
                    'kumulatif' => (float) $runningCumulativeMom
                ];
            }
        }

        // QoQ (Kebijakan B): Valid HANYA jika triwulan berjalan DAN triwulan tepat sebelumnya sama-sama lengkap 3 bulan
        $quarterComplete = [];
        $quarterSumStr = [];
        for ($qIdx = 0; $qIdx < 4; $qIdx++) {
            $qStart = ($qIdx * 3) + 1;
            $qEnd = $qStart + 2;
            $isFull = true;
            $qSum = '0.000000';
            for ($mm = $qStart; $mm <= $qEnd; $mm++) {
                $mRow = $bulanAgg->get($mm);
                $mStr = $mRow ? bcadd((string) $mRow->fob, '0', 6) : '0.000000';
                if (bccomp($mStr, '0', 6) <= 0) {
                    $isFull = false;
                } else {
                    $qSum = bcadd($qSum, $mStr, 6);
                }
            }
            $quarterComplete[$qIdx] = $isFull;
            $quarterSumStr[$qIdx] = $qSum;
        }

        $growthQoqTable = [];
        $runningCumulativeQoq = '0.000000';
        foreach ($quarterlyChronological['labels'] as $idx => $label) {
            $qStr = $quarterSumStr[$idx];
            if (bccomp($qStr, '0', 6) > 0) {
                $runningCumulativeQoq = bcadd($runningCumulativeQoq, $qStr, 6);
                $qGrowth = ($idx > 0 && $quarterComplete[$idx] && $quarterComplete[$idx - 1])
                    ? $this->statService->calcBpsGrowth($qStr, $quarterSumStr[$idx - 1])
                    : null;

                $growthQoqTable[] = [
                    'periode' => $label,
                    'nilai' => (float) $qStr,
                    'growth' => $qGrowth,
                    'kumulatif' => (float) $runningCumulativeQoq
                ];
            }
        }

        // 10. Data Neraca Perdagangan Terintegrasi (Tab 4: Trade Balance)
        $eksporQuery = (clone $baseQuery)->where('t.jenis_transaksi', 'Ekspor');
        $imporQuery = (clone $baseQuery)->where('t.jenis_transaksi', 'Impor');

        $totalEksporFob = (float) (clone $eksporQuery)->sum('t.nilai');
        $totalImporCif = (float) (clone $imporQuery)->sum('t.nilai');

        $hasEkspor = $totalEksporFob > 0;
        $hasImpor = $totalImporCif > 0;
        $hasCompleteTradeBalance = ($hasEkspor && $hasImpor);

        $netTrade = $hasCompleteTradeBalance ? ($totalEksporFob - $totalImporCif) : null;
        if ($hasCompleteTradeBalance) {
            $tradeStatus = $netTrade > 0 ? 'Surplus' : ($netTrade < 0 ? 'Defisit' : 'Seimbang');
        } else {
            if ($hasEkspor && !$hasImpor) {
                $tradeStatus = 'Data Impor Belum Tersedia';
            } elseif (!$hasEkspor && $hasImpor) {
                $tradeStatus = 'Data Ekspor Belum Tersedia';
            } else {
                $tradeStatus = 'Data Belum Tersedia';
            }
        }

        // Breakdown Neraca Bulanan (Ekspor vs Impor per bulan)
        $eksporMonthlyAgg = (clone $eksporQuery)
            ->select('t.bulan', DB::raw('SUM(t.nilai) as nilai'))
            ->groupBy('t.bulan')
            ->pluck('nilai', 'bulan');

        $imporMonthlyAgg = (clone $imporQuery)
            ->select('t.bulan', DB::raw('SUM(t.nilai) as nilai'))
            ->groupBy('t.bulan')
            ->pluck('nilai', 'bulan');

        $monthlyTradeBalance = [];
        $runningBalanceStr = '0.000000';
        for ($m = 1; $m <= 12; $m++) {
            $eStr = isset($eksporMonthlyAgg[$m]) ? bcadd((string) $eksporMonthlyAgg[$m], '0', 6) : '0.000000';
            $iStr = isset($imporMonthlyAgg[$m]) ? bcadd((string) $imporMonthlyAgg[$m], '0', 6) : '0.000000';
            $hasE = bccomp($eStr, '0', 6) > 0;
            $hasI = bccomp($iStr, '0', 6) > 0;

            if ($hasE || $hasI) {
                // Perlindungan single-sided NULL: neraca bulanan hanya dihitung bila Ekspor & Impor keduanya tersedia
                $isCompleteMonth = ($hasE && $hasI);
                $bal = null;
                $kumulatif = null;

                if ($isCompleteMonth) {
                    $balStr = bcsub($eStr, $iStr, 6);
                    $runningBalanceStr = bcadd($runningBalanceStr, $balStr, 6);
                    $bal = (float) $balStr;
                    $kumulatif = (float) $runningBalanceStr;
                    $mStatus = bccomp($balStr, '0', 6) > 0
                        ? 'Surplus'
                        : (bccomp($balStr, '0', 6) < 0 ? 'Defisit' : 'Seimbang');
                } else {
                    $mStatus = $hasE ? 'Data Impor Belum Tersedia' : 'Data Ekspor Belum Tersedia';
                }

                $monthlyTradeBalance[] = [
                    'bulan' => $m,
                    'nama_bulan' => $namaBulan[$m],
                    'ekspor' => (float) $eStr,
                    'impor' => (float) $iStr,
                    'neraca' => $bal,
                    'status' => $mStatus,
                    'has_complete' => $isCompleteMonth,
                    'kumulatif' => $kumulatif
                ];
            }
        }

        // Metadata Periode Kumulatif & Deteksi Asimetri Ekspor vs Impor (Remediasi F-03)
        $isCumulativeFilter = (!$request->filled('bulan') || $request->bulan === 'ALL') &&
                              (!$request->filled('triwulan') || $request->triwulan === 'ALL');

        $eksporMonthsWithData = [];
        foreach ($eksporMonthlyAgg as $mVal => $val) {
            if (bccomp(bcadd((string) $val, '0', 6), '0', 6) > 0) {
                $eksporMonthsWithData[] = (int) $mVal;
            }
        }
        $imporMonthsWithData = [];
        foreach ($imporMonthlyAgg as $mVal => $val) {
            if (bccomp(bcadd((string) $val, '0', 6), '0', 6) > 0) {
                $imporMonthsWithData[] = (int) $mVal;
            }
        }

        $maxEksporMonth = !empty($eksporMonthsWithData) ? max($eksporMonthsWithData) : null;
        $maxImporMonth = !empty($imporMonthsWithData) ? max($imporMonthsWithData) : null;

        $isAsymmetricCumulative = false;
        if ($isCumulativeFilter && $maxEksporMonth !== null && $maxImporMonth !== null) {
            $isAsymmetricCumulative = ($maxEksporMonth !== $maxImporMonth);
        }

        // Metrik pertumbuhan M-to-M, Q-to-Q, Y-on-Y, C-to-C (diselaraskan dengan izin visibilitas peran pengguna)
        $metricsOfficial = $this->statService->getOfficialDashboardMetrics(
            $request->filled('tahun') && $request->tahun !== 'ALL' ? (int) $request->tahun : null,
            $activeJenis,
            $tipeSumber,
            $allowedStatuses
        );

        return response()->json([
            'success' => true,
            'tipe_sumber' => $tipeSumber,
            'jenis' => $activeJenis,
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
            'pivot_bulan_fob' => $pivotBulanFob,
            'pivot_bulan_netto' => $pivotBulanNetto,
            'pivot_triwulan_fob' => $pivotTriwulanFob,
            'pivot_triwulan_netto' => $pivotTriwulanNetto,
            'growth_tables' => [
                'mom' => $growthMomTable,
                'qoq' => $growthQoqTable,
            ],
            'trade_balance' => [
                'total_ekspor' => $totalEksporFob,
                'total_impor' => $totalImporCif,
                'net_trade' => $netTrade,
                'status' => $tradeStatus,
                'has_complete' => $hasCompleteTradeBalance,
                'monthly' => $monthlyTradeBalance,
                'is_asymmetric_cumulative' => $isAsymmetricCumulative,
                'max_ekspor_bulan' => $maxEksporMonth,
                'max_impor_bulan' => $maxImporMonth,
                'max_ekspor_nama_bulan' => $maxEksporMonth ? ($namaBulan[$maxEksporMonth] ?? null) : null,
                'max_impor_nama_bulan' => $maxImporMonth ? ($namaBulan[$maxImporMonth] ?? null) : null,
            ]
        ]);
    }
}
