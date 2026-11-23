<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Service to calculate BRS Distribution statistics (Slide 1–5, Sektor, Komoditas, Negara, BEC, & Origin)
 * strictly replicating BPS Excel formulas and isolating data by RILIS status and tipe_sumber = distribusi.
 */
class DistributionStatisticsService
{
    protected StatisticsCalculationService $statService;

    public function __construct(?StatisticsCalculationService $statService = null)
    {
        $this->statService = $statService ?? app(StatisticsCalculationService::class);
    }

    /**
     * Get list of active and released batch IDs for distribution.
     * Strictly excludes:
     * - Batches in trash
     * - Batches with status != 'RILIS' (e.g. PRIVATE, PENDING)
     * - Batches with tipe_sumber != 'distribusi'
     *
     * @return array
     */
    public function getReleasedBatchIds(): array
    {
        return DB::table('data_batches')
            ->where('tipe_sumber', 'distribusi')
            ->where('status', 'RILIS')
            ->whereNotIn('batch_id', function ($query) {
                $query->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->pluck('batch_id')
            ->toArray();
    }

    /**
     * Get available distinct periods (years and months) for distribution.
     *
     * @return array
     */
    public function getAvailablePeriods(): array
    {
        $batchIds = $this->getReleasedBatchIds();

        $periods = DB::table('distribusi_transactions')
            ->whereIn('batch_id', $batchIds)
            ->where('tipe_sumber', 'distribusi')
            ->select('tahun', 'bulan', 'jenis_transaksi')
            ->distinct()
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->get();

        $years = $periods->pluck('tahun')->unique()->values()->toArray();

        return [
            'years' => $years,
            'periods' => $periods
        ];
    }

    /**
     * Get monthly and cumulative summary metrics for a given period.
     * (Slide 1 BRS Ekspor & Slide 10 BRS Impor)
     *
     * @param int $tahun
     * @param int $bulan
     * @param string $jenis 'ekspor' or 'impor'
     * @return array
     */
    public function getSummaryMetrics(int $tahun, int $bulan, string $jenis = 'ekspor'): array
    {
        $jenis = strtolower($jenis);
        $jenisFilter = ucfirst($jenis);
        $batchIds = $this->getReleasedBatchIds();

        // Query monthly directly from released distribution transactions to guarantee isolation
        $queryAgg = function (int $y, int $m) use ($jenisFilter, $batchIds) {
            return DB::table('distribusi_transactions as t')
                ->join('master_hs as h', 't.hs_id', '=', 'h.hs_id')
                ->whereIn('t.batch_id', $batchIds)
                ->where('t.tipe_sumber', 'distribusi')
                ->where('t.tahun', $y)
                ->where('t.bulan', $m)
                ->whereRaw('LOWER(t.jenis_transaksi) = ?', [strtolower($jenisFilter)])
                ->select([
                    DB::raw('COUNT(*) as row_count'),
                    DB::raw('SUM(t.nilai) as nilai_total'),
                    DB::raw('SUM(t.berat) as berat_total'),
                    DB::raw('SUM(CASE WHEN h.oilgrp_code = 1 THEN t.nilai ELSE 0 END) as nilai_migas'),
                    DB::raw('SUM(CASE WHEN h.oilgrp_code = 1 THEN t.berat ELSE 0 END) as berat_migas'),
                    DB::raw('SUM(CASE WHEN COALESCE(h.oilgrp_code, 0) = 0 THEN t.nilai ELSE 0 END) as nilai_nonmigas'),
                    DB::raw('SUM(CASE WHEN COALESCE(h.oilgrp_code, 0) = 0 THEN t.berat ELSE 0 END) as berat_nonmigas'),
                ])
                ->first();
        };

        // Query cumulative (January up to $bulan)
        $queryCum = function (int $y, int $m) use ($jenisFilter, $batchIds) {
            return DB::table('distribusi_transactions as t')
                ->join('master_hs as h', 't.hs_id', '=', 'h.hs_id')
                ->whereIn('t.batch_id', $batchIds)
                ->where('t.tipe_sumber', 'distribusi')
                ->where('t.tahun', $y)
                ->whereBetween('t.bulan', [1, $m])
                ->whereRaw('LOWER(t.jenis_transaksi) = ?', [strtolower($jenisFilter)])
                ->select([
                    DB::raw('COUNT(*) as row_count'),
                    DB::raw('SUM(t.nilai) as nilai_total'),
                    DB::raw('SUM(t.berat) as berat_total'),
                    DB::raw('SUM(CASE WHEN h.oilgrp_code = 1 THEN t.nilai ELSE 0 END) as nilai_migas'),
                    DB::raw('SUM(CASE WHEN h.oilgrp_code = 1 THEN t.berat ELSE 0 END) as berat_migas'),
                    DB::raw('SUM(CASE WHEN COALESCE(h.oilgrp_code, 0) = 0 THEN t.nilai ELSE 0 END) as nilai_nonmigas'),
                    DB::raw('SUM(CASE WHEN COALESCE(h.oilgrp_code, 0) = 0 THEN t.berat ELSE 0 END) as berat_nonmigas'),
                ])
                ->first();
        };

        // 1. Current Month (m, y)
        $currM = $queryAgg($tahun, $bulan);
        // 2. Previous Month (m-1, y)
        $prevBulan = ($bulan > 1) ? $bulan - 1 : 12;
        $prevTahun = ($bulan > 1) ? $tahun : $tahun - 1;
        $prevM = $queryAgg($prevTahun, $prevBulan);
        // 3. Same Month Previous Year (m, y-1)
        $prevY = $queryAgg($tahun - 1, $bulan);

        // 4. Cumulative Current Year (Jan-m, y)
        $currCum = $queryCum($tahun, $bulan);
        // 5. Cumulative Previous Year (Jan-m, y-1)
        $prevCum = $queryCum($tahun - 1, $bulan);

        // Flag eksistensi observasi berbasis COUNT(*) untuk membedakan data tidak tersedia (NULL) vs nilai riil 0
        $hasCurrM = ((int) ($currM->row_count ?? 0) > 0);
        $hasPrevM = ((int) ($prevM->row_count ?? 0) > 0);
        $hasPrevY = ((int) ($prevY->row_count ?? 0) > 0);
        $hasCurrCum = ((int) ($currCum->row_count ?? 0) > 0);
        $hasPrevCum = ((int) ($prevCum->row_count ?? 0) > 0);

        $toDecimal6 = static function (mixed $val): string {
            return ($val === null || $val === '') ? '0.000000' : bcadd((string) $val, '0', 6);
        };

        // Delegasi formula pertumbuhan resmi BPS ke StatisticsCalculationService::calcBpsGrowth() (BCMath scale 8 -> round 2)
        $calcMetricGrowth = function (bool $hasCurr, mixed $currRaw, bool $hasPrev, mixed $prevRaw) use ($toDecimal6): ?float {
            if (!$hasCurr || !$hasPrev || $currRaw === null || $prevRaw === null) {
                return null;
            }

            return $this->statService->calcBpsGrowth($toDecimal6($currRaw), $toDecimal6($prevRaw));
        };

        $currTotalRaw = $hasCurrM ? $toDecimal6($currM->nilai_total) : '0.000000';
        $currNonmigasRaw = $hasCurrM ? $toDecimal6($currM->nilai_nonmigas) : '0.000000';
        $currMigasRaw = $hasCurrM ? $toDecimal6($currM->nilai_migas) : '0.000000';
        $currBeratTotalRaw = $hasCurrM ? $toDecimal6($currM->berat_total) : '0.000000';
        $currBeratNonmigasRaw = $hasCurrM ? $toDecimal6($currM->berat_nonmigas) : '0.000000';
        $currBeratMigasRaw = $hasCurrM ? $toDecimal6($currM->berat_migas) : '0.000000';

        $currCumTotalRaw = $hasCurrCum ? $toDecimal6($currCum->nilai_total) : '0.000000';
        $currCumNonmigasRaw = $hasCurrCum ? $toDecimal6($currCum->nilai_nonmigas) : '0.000000';
        $currCumMigasRaw = $hasCurrCum ? $toDecimal6($currCum->nilai_migas) : '0.000000';
        $currCumBeratTotalRaw = $hasCurrCum ? $toDecimal6($currCum->berat_total) : '0.000000';

        // C-to-C mensyaratkan bulan berjalan ($hasCurrM) telah memiliki observasi RILIS agar jendela Jan-m sebanding
        $hasValidCurrCumForGrowth = ($hasCurrM && $hasCurrCum);

        return [
            'tahun' => $tahun,
            'bulan' => $bulan,
            'jenis' => $jenis,
            // Monthly Values
            'bulan_ini' => [
                'has_data' => $hasCurrM,
                'nilai_total_usd' => (float) $currTotalRaw,
                'nilai_total_usd_raw' => $currTotalRaw,
                'nilai_total_juta' => round((float) bcdiv($currTotalRaw, '1000000', 8), 2),
                'nilai_nonmigas_juta' => round((float) bcdiv($currNonmigasRaw, '1000000', 8), 2),
                'nilai_migas_juta' => round((float) bcdiv($currMigasRaw, '1000000', 8), 2),
                'berat_total_ribu_ton' => round((float) bcdiv($currBeratTotalRaw, '1000000', 8), 2),
                'berat_nonmigas_ribu_ton' => round((float) bcdiv($currBeratNonmigasRaw, '1000000', 8), 2),
                'berat_migas_ribu_ton' => round((float) bcdiv($currBeratMigasRaw, '1000000', 8), 2),
            ],
            // Cumulative Values (Jan - Current Month)
            'kumulatif' => [
                'has_data' => $hasCurrCum,
                'nilai_total_usd' => (float) $currCumTotalRaw,
                'nilai_total_usd_raw' => $currCumTotalRaw,
                'nilai_total_juta' => round((float) bcdiv($currCumTotalRaw, '1000000', 8), 2),
                'nilai_nonmigas_juta' => round((float) bcdiv($currCumNonmigasRaw, '1000000', 8), 2),
                'nilai_migas_juta' => round((float) bcdiv($currCumMigasRaw, '1000000', 8), 2),
                'berat_total_ribu_ton' => round((float) bcdiv($currCumBeratTotalRaw, '1000000', 8), 2),
            ],
            // Growths (BPS Official Formula via StatisticsCalculationService::calcBpsGrowth)
            'pertumbuhan' => [
                'mtom_total' => $calcMetricGrowth($hasCurrM, $currM->nilai_total ?? null, $hasPrevM, $prevM->nilai_total ?? null),
                'mtom_nonmigas' => $calcMetricGrowth($hasCurrM, $currM->nilai_nonmigas ?? null, $hasPrevM, $prevM->nilai_nonmigas ?? null),
                'mtom_migas' => $calcMetricGrowth($hasCurrM, $currM->nilai_migas ?? null, $hasPrevM, $prevM->nilai_migas ?? null),
                'yony_total' => $calcMetricGrowth($hasCurrM, $currM->nilai_total ?? null, $hasPrevY, $prevY->nilai_total ?? null),
                'yony_nonmigas' => $calcMetricGrowth($hasCurrM, $currM->nilai_nonmigas ?? null, $hasPrevY, $prevY->nilai_nonmigas ?? null),
                'yony_migas' => $calcMetricGrowth($hasCurrM, $currM->nilai_migas ?? null, $hasPrevY, $prevY->nilai_migas ?? null),
                'ctoc_total' => $calcMetricGrowth($hasValidCurrCumForGrowth, $currCum->nilai_total ?? null, $hasPrevCum, $prevCum->nilai_total ?? null),
                'ctoc_nonmigas' => $calcMetricGrowth($hasValidCurrCumForGrowth, $currCum->nilai_nonmigas ?? null, $hasPrevCum, $prevCum->nilai_nonmigas ?? null),
                'ctoc_migas' => $calcMetricGrowth($hasValidCurrCumForGrowth, $currCum->nilai_migas ?? null, $hasPrevCum, $prevCum->nilai_migas ?? null),
            ]
        ];
    }

    /**
     * Get Combined Trade Balance (Surplus/Defisit Distribusi) for Dashboard KPI.
     *
     * @param int $tahun
     * @param int $bulan
     * @return array
     */
    public function getDistributionTradeBalance(int $tahun, int $bulan): array
    {
        $exp = $this->getSummaryMetrics($tahun, $bulan, 'ekspor');
        $imp = $this->getSummaryMetrics($tahun, $bulan, 'impor');

        $fobBulan = $exp['bulan_ini']['nilai_total_juta'];
        $cifBulan = $imp['bulan_ini']['nilai_total_juta'];
        $fobUsdRaw = $exp['bulan_ini']['nilai_total_usd_raw'];
        $cifUsdRaw = $imp['bulan_ini']['nilai_total_usd_raw'];
        $hasEksporBulan = $exp['bulan_ini']['has_data'] && bccomp($fobUsdRaw, '0', 6) > 0;
        $hasImporBulan = $imp['bulan_ini']['has_data'] && bccomp($cifUsdRaw, '0', 6) > 0;
        $hasCompleteBulan = ($hasEksporBulan && $hasImporBulan);

        if ($hasCompleteBulan) {
            // Pengurangan BCMath pada USD mentah 6 desimal sebelum konversi & pembulatan jutaan
            $diffBulanUsd = bcsub($fobUsdRaw, $cifUsdRaw, 6);
            $surplusBulan = round((float) bcdiv($diffBulanUsd, '1000000', 8), 2);
            $cmpBulan = bccomp($diffBulanUsd, '0', 6);
            $statusBulan = ($cmpBulan > 0) ? 'Surplus' : (($cmpBulan < 0) ? 'Defisit' : 'Seimbang');
        } else {
            // Perlindungan single-sided NULL: hindari defisit/surplus semu bila satu sisi belum tersedia
            $surplusBulan = null;
            $statusBulan = $hasEksporBulan
                ? 'Data Impor Belum Tersedia'
                : ($hasImporBulan ? 'Data Ekspor Belum Tersedia' : 'Data Belum Tersedia');
        }

        $fobCum = $exp['kumulatif']['nilai_total_juta'];
        $cifCum = $imp['kumulatif']['nilai_total_juta'];
        $fobCumUsdRaw = $exp['kumulatif']['nilai_total_usd_raw'];
        $cifCumUsdRaw = $imp['kumulatif']['nilai_total_usd_raw'];
        $hasEksporCum = $exp['kumulatif']['has_data'] && bccomp($fobCumUsdRaw, '0', 6) > 0;
        $hasImporCum = $imp['kumulatif']['has_data'] && bccomp($cifCumUsdRaw, '0', 6) > 0;
        $hasCompleteCum = ($hasEksporCum && $hasImporCum);

        if ($hasCompleteCum) {
            $diffCumUsd = bcsub($fobCumUsdRaw, $cifCumUsdRaw, 6);
            $surplusCum = round((float) bcdiv($diffCumUsd, '1000000', 8), 2);
            $cmpCum = bccomp($diffCumUsd, '0', 6);
            $statusCum = ($cmpCum > 0) ? 'Surplus' : (($cmpCum < 0) ? 'Defisit' : 'Seimbang');
        } else {
            $surplusCum = null;
            $statusCum = $hasEksporCum
                ? 'Data Impor Belum Tersedia'
                : ($hasImporCum ? 'Data Ekspor Belum Tersedia' : 'Data Belum Tersedia');
        }

        return [
            'tahun' => $tahun,
            'bulan' => $bulan,
            'ekspor_fob_juta' => $fobBulan,
            'impor_cif_juta' => $cifBulan,
            'neraca_bulanan_juta' => $surplusBulan,
            'status_bulanan' => $statusBulan,
            'has_complete_bulanan' => $hasCompleteBulan,
            'ekspor_cum_juta' => $fobCum,
            'impor_cum_juta' => $cifCum,
            'neraca_kumulatif_juta' => $surplusCum,
            'status_kumulatif' => $statusCum,
            'has_complete_kumulatif' => $hasCompleteCum,
        ];
    }

    /**
     * Get Sektor Breakdown (Slide 2 BRS Ekspor)
     *
     * @param int $tahun
     * @param int $bulan
     * @return array
     */
    public function getSektorBreakdown(int $tahun, int $bulan): array
    {
        $batchIds = $this->getReleasedBatchIds();

        $rows = DB::table('distribusi_transactions as t')
            ->join('master_hs as h', 't.hs_id', '=', 'h.hs_id')
            ->whereIn('t.batch_id', $batchIds)
            ->where('t.tipe_sumber', 'distribusi')
            ->where('t.tahun', $tahun)
            ->where('t.bulan', $bulan)
            ->whereRaw('LOWER(t.jenis_transaksi) = ?', ['ekspor'])
            ->select([
                DB::raw("CASE 
                    WHEN h.oilgrp_code = 1 THEN 'Migas'
                    WHEN UPPER(COALESCE(h.sektor, '')) LIKE '%INDUSTRI%' THEN 'Industri Pengolahan'
                    WHEN UPPER(COALESCE(h.sektor, '')) LIKE '%PERTANIAN%' THEN 'Pertanian dan Lainnya'
                    ELSE 'Pertambangan dan Lainnya'
                END as sektor_bps"),
                DB::raw('SUM(t.nilai) as nilai_usd'),
                DB::raw('SUM(t.berat) as berat_kg'),
            ])
            ->groupBy('sektor_bps')
            ->get();

        $totalNilai = $rows->sum('nilai_usd');
        $sektors = [];

        foreach ($rows as $r) {
            $share = ($totalNilai > 0) ? round(($r->nilai_usd / $totalNilai) * 100, 2) : 0;
            $sektors[] = [
                'sektor' => $r->sektor_bps,
                'nilai_juta_usd' => round($r->nilai_usd / 1000000, 2),
                'berat_ribu_ton' => round($r->berat_kg / 1000000, 2),
                'share_persen' => $share
            ];
        }

        return [
            'total_nilai_juta_usd' => round($totalNilai / 1000000, 2),
            'sektor' => $sektors
        ];
    }

    /**
     * Get Top 3 Komoditas Nonmigas + Lainnya (Slide 3 BRS Ekspor)
     *
     * @param int $tahun
     * @param int $bulan
     * @return array
     */
    public function getTopCommodities(int $tahun, int $bulan): array
    {
        $batchIds = $this->getReleasedBatchIds();

        // Top 3 Bab HS Nonmigas
        $top3 = DB::table('distribusi_transactions as t')
            ->join('master_hs as h', 't.hs_id', '=', 'h.hs_id')
            ->whereIn('t.batch_id', $batchIds)
            ->where('t.tipe_sumber', 'distribusi')
            ->where('t.tahun', $tahun)
            ->where('t.bulan', $bulan)
            ->whereRaw('LOWER(t.jenis_transaksi) = ?', ['ekspor'])
            ->where('h.oilgrp_code', 0)
            ->select([
                'h.hs2',
                'h.hs2_desc',
                DB::raw('SUM(t.nilai) as nilai_usd'),
                DB::raw('SUM(t.berat) as berat_kg'),
            ])
            ->groupBy('h.hs2', 'h.hs2_desc')
            ->orderByDesc('nilai_usd')
            ->limit(3)
            ->get();

        // Total Nonmigas
        $totNonmigas = DB::table('distribusi_transactions as t')
            ->join('master_hs as h', 't.hs_id', '=', 'h.hs_id')
            ->whereIn('t.batch_id', $batchIds)
            ->where('t.tipe_sumber', 'distribusi')
            ->where('t.tahun', $tahun)
            ->where('t.bulan', $bulan)
            ->whereRaw('LOWER(t.jenis_transaksi) = ?', ['ekspor'])
            ->where('h.oilgrp_code', 0)
            ->select([
                DB::raw('SUM(t.nilai) as nilai_usd'),
                DB::raw('SUM(t.berat) as berat_kg'),
            ])
            ->first();

        $totalNonmigasVal = (float)($totNonmigas->nilai_usd ?? 0);
        $totalNonmigasVol = (float)($totNonmigas->berat_kg ?? 0);

        $items = [];
        $topValSum = 0;
        $topVolSum = 0;

        foreach ($top3 as $r) {
            $topValSum += $r->nilai_usd;
            $topVolSum += $r->berat_kg;
            $share = ($totalNonmigasVal > 0) ? round(($r->nilai_usd / $totalNonmigasVal) * 100, 2) : 0;
            $items[] = [
                'hs2' => $r->hs2,
                'nama_komoditas' => $r->hs2_desc ?: "Bab {$r->hs2}",
                'nilai_juta_usd' => round($r->nilai_usd / 1000000, 2),
                'berat_ribu_ton' => round($r->berat_kg / 1000000, 2),
                'share_persen' => $share
            ];
        }

        // Lainnya (Residu formula Slide 3 BPS: Total Nonmigas - Top1 - Top2 - Top3)
        // Hanya ditambahkan bila periode memiliki data nonmigas valid (cegah item 'Lainnya' semu saat periode kosong)
        if ($top3->isNotEmpty() || $totalNonmigasVal > 0) {
            $lainnyaVal = max(0, $totalNonmigasVal - $topValSum);
            $lainnyaVol = max(0, $totalNonmigasVol - $topVolSum);
            $lainnyaShare = ($totalNonmigasVal > 0) ? round(($lainnyaVal / $totalNonmigasVal) * 100, 2) : 0;

            $items[] = [
                'hs2' => 'XX',
                'nama_komoditas' => 'Lainnya',
                'nilai_juta_usd' => round($lainnyaVal / 1000000, 2),
                'berat_ribu_ton' => round($lainnyaVol / 1000000, 2),
                'share_persen' => $lainnyaShare
            ];
        }

        return [
            'total_nonmigas_juta_usd' => round($totalNonmigasVal / 1000000, 2),
            'items' => $items
        ];
    }

    /**
     * Get Top 10 Negara Tujuan Ekspor / Asal Impor
     *
     * @param int $tahun
     * @param int $bulan
     * @param string $jenis
     * @return array
     */
    public function getTopCountries(int $tahun, int $bulan, string $jenis = 'ekspor'): array
    {
        $jenisFilter = strtolower($jenis);
        $batchIds = $this->getReleasedBatchIds();

        $rows = DB::table('distribusi_transactions as t')
            ->join('master_hs as h', 't.hs_id', '=', 'h.hs_id')
            ->join('master_negara as n', 't.negara_id', '=', 'n.negara_id')
            ->whereIn('t.batch_id', $batchIds)
            ->where('t.tipe_sumber', 'distribusi')
            ->where('t.tahun', $tahun)
            ->where('t.bulan', $bulan)
            ->whereRaw('LOWER(t.jenis_transaksi) = ?', [$jenisFilter])
            ->where('h.oilgrp_code', 0)
            ->select([
                'n.negara_id',
                'n.nama_negara',
                'n.nation',
                DB::raw('SUM(t.nilai) as nilai_usd'),
                DB::raw('SUM(t.berat) as berat_kg'),
            ])
            ->groupBy('n.negara_id', 'n.nama_negara', 'n.nation')
            ->orderByDesc('nilai_usd')
            ->limit(10)
            ->get();

        $totVal = DB::table('distribusi_transactions as t')
            ->join('master_hs as h', 't.hs_id', '=', 'h.hs_id')
            ->whereIn('t.batch_id', $batchIds)
            ->where('t.tipe_sumber', 'distribusi')
            ->where('t.tahun', $tahun)
            ->where('t.bulan', $bulan)
            ->whereRaw('LOWER(t.jenis_transaksi) = ?', [$jenisFilter])
            ->where('h.oilgrp_code', 0)
            ->sum('t.nilai');

        $countries = [];
        foreach ($rows as $r) {
            $share = ($totVal > 0) ? round(($r->nilai_usd / $totVal) * 100, 2) : 0;
            $countries[] = [
                'negara' => $r->nama_negara ?: $r->nation,
                'kode_iso' => $r->nation,
                'nilai_juta_usd' => round($r->nilai_usd / 1000000, 2),
                'berat_ribu_ton' => round($r->berat_kg / 1000000, 2),
                'share_persen' => $share
            ];
        }

        return [
            'total_nonmigas_juta_usd' => round($totVal / 1000000, 2),
            'countries' => $countries
        ];
    }

    /**
     * Get Impor Breakdown by BEC (Broad Economic Categories - Slide 11 BRS Impor)
     *
     * @param int $tahun
     * @param int $bulan
     * @return array
     */
    public function getImportBecBreakdown(int $tahun, int $bulan): array
    {
        $batchIds = $this->getReleasedBatchIds();

        $rows = DB::table('distribusi_transactions as t')
            ->join('master_hs as h', 't.hs_id', '=', 'h.hs_id')
            ->whereIn('t.batch_id', $batchIds)
            ->where('t.tipe_sumber', 'distribusi')
            ->where('t.tahun', $tahun)
            ->where('t.bulan', $bulan)
            ->whereRaw('LOWER(t.jenis_transaksi) = ?', ['impor'])
            ->select([
                DB::raw("COALESCE(h.bec_desc, 'Bahan Baku/Penolong') as golongan_bec"),
                DB::raw('SUM(t.nilai) as nilai_usd'),
                DB::raw('SUM(t.berat) as berat_kg'),
            ])
            ->groupBy('golongan_bec')
            ->get();

        $totVal = $rows->sum('nilai_usd');
        $becItems = [];

        foreach ($rows as $r) {
            $share = ($totVal > 0) ? round(($r->nilai_usd / $totVal) * 100, 2) : 0;
            $becItems[] = [
                'golongan' => $r->golongan_bec,
                'nilai_juta_usd' => round($r->nilai_usd / 1000000, 2),
                'berat_ribu_ton' => round($r->berat_kg / 1000000, 2),
                'share_persen' => $share
            ];
        }

        return [
            'total_impor_juta_usd' => round($totVal / 1000000, 2),
            'golongan_barang' => $becItems
        ];
    }

    /**
     * Get Origin Distribution (Kabupaten/Kota Asal Barang Ekspor via PROVORIG)
     *
     * @param int $tahun
     * @param int $bulan
     * @return array
     */
    public function getOriginDistribution(int $tahun, int $bulan): array
    {
        // Read-side visibility & Trash guard:
        // monthly_origin_trade_summaries tidak memiliki kolom status_visibilitas, sehingga
        // wajib memverifikasi keberadaan batch Distribusi Ekspor aktif berstatus RILIS (non-Trash)
        // pada periode (tahun, bulan) sebelum mengembalikan data asal wilayah.
        $releasedBatchIds = $this->getReleasedBatchIds();
        $hasReleasedEksporPeriod = !empty($releasedBatchIds) && DB::table('distribusi_transactions')
            ->whereIn('batch_id', $releasedBatchIds)
            ->where('tipe_sumber', 'distribusi')
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->whereRaw('LOWER(jenis_transaksi) = ?', ['ekspor'])
            ->exists();

        if (!$hasReleasedEksporPeriod) {
            return [
                'total_fob_juta_usd' => 0,
                'riau_fob_juta_usd' => 0,
                'riau_share_persen' => 0,
                'non_riau_fob_juta_usd' => 0,
                'non_riau_share_persen' => 0,
                'daerah' => [],
            ];
        }

        $rows = DB::table('monthly_origin_trade_summaries as o')
            ->leftJoin('master_wilayah_bps as w', 'o.provorig', '=', 'w.kode_wilayah')
            ->where('o.tahun', $tahun)
            ->where('o.bulan', $bulan)
            ->where('o.jenis_transaksi', 'ekspor')
            ->select([
                'o.provorig',
                'o.is_riau',
                DB::raw("COALESCE(w.nama_wilayah, CONCAT('Wilayah ', o.provorig)) as nama_daerah"),
                DB::raw('SUM(o.total_nilai) as nilai_usd'),
                DB::raw('SUM(o.total_berat) as berat_kg'),
                DB::raw('SUM(o.jumlah_transaksi) as total_tx')
            ])
            ->groupBy('o.provorig', 'o.is_riau', 'w.nama_wilayah')
            ->orderByDesc('nilai_usd')
            ->get();

        $totFob = $rows->sum('nilai_usd');
        $riauFob = $rows->where('is_riau', true)->sum('nilai_usd');
        $nonRiauFob = $rows->where('is_riau', false)->sum('nilai_usd');

        $origins = [];
        foreach ($rows as $r) {
            $share = ($totFob > 0) ? round(($r->nilai_usd / $totFob) * 100, 2) : 0;
            $origins[] = [
                'kode' => $r->provorig,
                'nama' => $r->nama_daerah,
                'is_riau' => (bool)$r->is_riau,
                'nilai_juta_usd' => round($r->nilai_usd / 1000000, 2),
                'berat_ribu_ton' => round($r->berat_kg / 1000000, 2),
                'share_persen' => $share
            ];
        }

        return [
            'total_fob_juta_usd' => round($totFob / 1000000, 2),
            'riau_fob_juta_usd' => round($riauFob / 1000000, 2),
            'riau_share_persen' => ($totFob > 0) ? round(($riauFob / $totFob) * 100, 2) : 0,
            'non_riau_fob_juta_usd' => round($nonRiauFob / 1000000, 2),
            'non_riau_share_persen' => ($totFob > 0) ? round(($nonRiauFob / $totFob) * 100, 2) : 0,
            'daerah' => $origins
        ];
    }
}
