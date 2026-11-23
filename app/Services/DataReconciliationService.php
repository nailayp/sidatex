<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DataReconciliationService
{
    /**
     * Memeriksa rekonsiliasi data menyeluruh antara:
     * 1. Data Batches (aktif, private, dan terhapus)
     * 2. Transactions (Jumlah, FOB/CIF Nilai, Netto Berat)
     * 3. Monthly Trade Summaries (Agregasi BRS Makro)
     * 4. Deteksi Orphan Aggregation (Agregasi tanpa sumber transaksi aktif)
     * 5. Pemisahan Sumber Neraca vs Distribusi
     *
     * @param int|null $batchId Jika diberikan, periksa batch tertentu
     * @return array
     */
    public function auditConsistency(?int $batchId = null): array
    {
        $trashedBatchIds = DB::table('trash')
            ->whereNull('restored_at')
            ->pluck('batch_id')
            ->toArray();

        $batchesQuery = DB::table('data_batches');
        if ($batchId !== null) {
            $batchesQuery->where('batch_id', $batchId);
        }
        $batches = $batchesQuery->get();

        $results = [
            'total_batches' => $batches->count(),
            'active_batches' => 0,
            'trashed_batches' => count($trashedBatchIds),
            'total_transactions_db' => 0,
            'active_transactions_db' => 0,
            'trashed_transactions_db' => 0,
            'total_nilai_fob_ekspor' => 0.0,
            'total_nilai_cif_impor' => 0.0,
            'total_berat_netto_kg' => 0.0,
            'summary_by_source' => [
                'distribusi' => [
                    'active_batches' => 0,
                    'active_transactions' => 0,
                    'total_nilai_fob_ekspor' => 0.0,
                    'total_nilai_fob_ekspor_exact' => '0.000000',
                    'total_nilai_cif_impor' => 0.0,
                    'total_nilai_cif_impor_exact' => '0.000000',
                    'total_berat_netto_kg' => 0.0,
                    'total_berat_netto_kg_exact' => '0.000000',
                ],
                'neraca' => [
                    'active_batches' => 0,
                    'active_transactions' => 0,
                    'total_nilai_fob_ekspor' => 0.0,
                    'total_nilai_fob_ekspor_exact' => '0.000000',
                    'total_nilai_cif_impor' => 0.0,
                    'total_nilai_cif_impor_exact' => '0.000000',
                    'total_berat_netto_kg' => 0.0,
                    'total_berat_netto_kg_exact' => '0.000000',
                ],
            ],
            'unmapped_ports' => 0,
            'unmapped_countries' => 0,
            'unmapped_commodities' => 0,
            'neraca_unmapped_hs_preserved' => 0,
            'historical_unlinked_rows' => 0,
            'batch_details' => [],
            'orphan_aggregations' => [],
            'aggregation_comparison' => [],
            'tier2_comparison' => [],
            'status' => 'CONSISTENT',
            'is_consistent' => true,
        ];

        $sourceTotals = [
            'distribusi' => ['ekspor' => '0.000000', 'impor' => '0.000000', 'berat' => '0.000000'],
            'neraca' => ['ekspor' => '0.000000', 'impor' => '0.000000', 'berat' => '0.000000'],
        ];

        $activeDistribusiIds = [];
        $activeNeracaIds = [];

        foreach ($batches as $batch) {
            $isTrashed = in_array($batch->batch_id, $trashedBatchIds);
            $tipeSumber = strtolower(trim((string) ($batch->tipe_sumber ?? 'distribusi')));
            $sourceKey = ($tipeSumber === 'neraca') ? 'neraca' : 'distribusi';
            $txTable = ($sourceKey === 'neraca') ? 'neraca_transactions' : 'distribusi_transactions';

            if (!$isTrashed) {
                $results['active_batches']++;
                $results['summary_by_source'][$sourceKey]['active_batches']++;
                if ($sourceKey === 'neraca') {
                    $activeNeracaIds[] = $batch->batch_id;
                } else {
                    $activeDistribusiIds[] = $batch->batch_id;
                }
            }

            $txStats = DB::table($txTable)
                ->where('batch_id', $batch->batch_id)
                ->selectRaw('COUNT(*) as tx_count, COALESCE(SUM(nilai), 0) as total_val, COALESCE(SUM(berat), 0) as total_netto')
                ->first();

            $txCount = (int) ($txStats->tx_count ?? 0);
            $totalValStr = bcadd((string) ($txStats->total_val ?? '0'), '0', 6);
            $totalNettoStr = bcadd((string) ($txStats->total_netto ?? '0'), '0', 6);
            $totalVal = (float) $totalValStr;
            $totalNetto = (float) $totalNettoStr;

            $results['total_transactions_db'] += $txCount;

            if ($isTrashed) {
                $results['trashed_transactions_db'] += $txCount;
            } else {
                $results['active_transactions_db'] += $txCount;
                $results['summary_by_source'][$sourceKey]['active_transactions'] += $txCount;
                $sourceTotals[$sourceKey]['berat'] = bcadd($sourceTotals[$sourceKey]['berat'], $totalNettoStr, 6);
                if (strtolower((string) $batch->jenis_data) === 'ekspor') {
                    $sourceTotals[$sourceKey]['ekspor'] = bcadd($sourceTotals[$sourceKey]['ekspor'], $totalValStr, 6);
                } else {
                    $sourceTotals[$sourceKey]['impor'] = bcadd($sourceTotals[$sourceKey]['impor'], $totalValStr, 6);
                }
            }

            $results['batch_details'][] = [
                'batch_id' => $batch->batch_id,
                'nama_file' => $batch->nama_file,
                'jenis_data' => $batch->jenis_data,
                'tipe_sumber' => $sourceKey,
                'status' => $batch->status,
                'is_trashed' => $isTrashed,
                'total_transaksi' => $txCount,
                'total_nilai' => $totalVal,
                'total_nilai_exact' => $totalValStr,
                'total_berat_netto' => $totalNetto,
                'total_berat_netto_exact' => $totalNettoStr,
            ];
        }

        // AGENTS.md §6: Angka Distribusi dan Neraca WAJIB dipisahkan, DILARANG digabung menjadi satu total.
        foreach (['distribusi', 'neraca'] as $srcKey) {
            $results['summary_by_source'][$srcKey]['total_nilai_fob_ekspor'] = (float) $sourceTotals[$srcKey]['ekspor'];
            $results['summary_by_source'][$srcKey]['total_nilai_fob_ekspor_exact'] = $sourceTotals[$srcKey]['ekspor'];
            $results['summary_by_source'][$srcKey]['total_nilai_cif_impor'] = (float) $sourceTotals[$srcKey]['impor'];
            $results['summary_by_source'][$srcKey]['total_nilai_cif_impor_exact'] = $sourceTotals[$srcKey]['impor'];
            $results['summary_by_source'][$srcKey]['total_berat_netto_kg'] = (float) $sourceTotals[$srcKey]['berat'];
            $results['summary_by_source'][$srcKey]['total_berat_netto_kg_exact'] = $sourceTotals[$srcKey]['berat'];
        }

        // Kompatibilitas kunci legacy top-level: tampilkan sumber batch tunggal jika $batchId diisi,
        // atau default hanya metodologi Distribusi (tidak pernah menggabungkan Distribusi + Neraca).
        $primarySourceKey = ($batchId !== null && !empty($results['batch_details']))
            ? $results['batch_details'][0]['tipe_sumber']
            : 'distribusi';
        $results['total_nilai_fob_ekspor'] = $results['summary_by_source'][$primarySourceKey]['total_nilai_fob_ekspor'];
        $results['total_nilai_cif_impor'] = $results['summary_by_source'][$primarySourceKey]['total_nilai_cif_impor'];
        $results['total_berat_netto_kg'] = $results['summary_by_source'][$primarySourceKey]['total_berat_netto_kg'];

        // Cek integritas master mapping pada transaksi aktif:
        // - Distribusi: pelabuhan Riau, negara, dan kode HS wajib terpetakan.
        // - Neraca: pelabuhan luar Riau (pelabuhan_id NULL) dan HS unmapped (mapping_status='unmapped')
        //   adalah kondisi metodologis resmi BPS yang dipertahankan (bukan anomali integritas);
        //   hanya negara_id NULL yang dihitung sebagai unmapped_countries lintas metodologi.
        $unmappedPorts = 0;
        $unmappedCountries = 0;
        $unmappedCommodities = 0;
        $neracaUnmappedHsPreserved = 0;

        if (!empty($activeDistribusiIds)) {
            $unmappedPorts += DB::table('distribusi_transactions')
                ->whereIn('batch_id', $activeDistribusiIds)
                ->whereNull('pelabuhan_id')
                ->count();
            $unmappedCountries += DB::table('distribusi_transactions')
                ->whereIn('batch_id', $activeDistribusiIds)
                ->whereNull('negara_id')
                ->count();
            $unmappedCommodities += DB::table('distribusi_transactions')
                ->whereIn('batch_id', $activeDistribusiIds)
                ->whereNull('hs_id')
                ->count();
        }

        if (!empty($activeNeracaIds)) {
            $unmappedCountries += DB::table('neraca_transactions')
                ->whereIn('batch_id', $activeNeracaIds)
                ->whereNull('negara_id')
                ->count();
            $neracaUnmappedHsPreserved = DB::table('neraca_transactions')
                ->whereIn('batch_id', $activeNeracaIds)
                ->whereNull('hs_id')
                ->count();
        }

        $results['unmapped_ports'] = $unmappedPorts;
        $results['unmapped_countries'] = $unmappedCountries;
        $results['unmapped_commodities'] = $unmappedCommodities;
        $results['neraca_unmapped_hs_preserved'] = $neracaUnmappedHsPreserved;

        // Catat jumlah baris historis tanpa batch_id secara terpisah (tidak dilabeli orphan)
        $results['historical_unlinked_rows'] = DB::table('monthly_trade_summaries')
            ->whereNull('batch_id')
            ->count();

        // Audit Perbandingan Agregasi Tier-1 (monthly_trade_summaries) khusus baris ter-link (batch_id IS NOT NULL)
        $aggQuery = DB::table('monthly_trade_summaries')
            ->whereNotNull('batch_id')
            ->selectRaw('tahun, bulan, jenis_transaksi, tipe_sumber, status_visibilitas, SUM(total_nilai) as agg_nilai, SUM(total_berat) as agg_berat, COUNT(*) as summary_count')
            ->groupBy('tahun', 'bulan', 'jenis_transaksi', 'tipe_sumber', 'status_visibilitas');

        if ($batchId !== null) {
            $aggQuery->where('batch_id', $batchId);
        }

        $aggregationGroups = $aggQuery->get();

        foreach ($aggregationGroups as $agg) {
            $aggSumber = strtolower(trim((string) ($agg->tipe_sumber ?? 'distribusi')));
            $aggTxTable = ($aggSumber === 'neraca') ? 'neraca_transactions' : 'distribusi_transactions';
            $aggStatus = strtoupper(trim((string) ($agg->status_visibilitas ?? 'RILIS')));

            $transQuery = DB::table("{$aggTxTable} as t")
                ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
                ->where('b.status', $aggStatus)
                ->where('t.tahun', $agg->tahun)
                ->where('t.bulan', $agg->bulan)
                ->whereRaw('LOWER(t.jenis_transaksi) = ?', [strtolower((string) $agg->jenis_transaksi)])
                ->where('t.tipe_sumber', $aggSumber)
                ->selectRaw('COUNT(*) as tx_count, COALESCE(SUM(t.nilai), 0) as trans_nilai, COALESCE(SUM(t.berat), 0) as trans_berat');

            if (!empty($trashedBatchIds)) {
                $transQuery->whereNotIn('b.batch_id', $trashedBatchIds);
            }

            if ($batchId !== null) {
                $transQuery->where('b.batch_id', $batchId);
            }

            $transMatch = $transQuery->first();

            $activeTxCount = (int) ($transMatch->tx_count ?? 0);
            $transNilaiStr = bcadd((string) ($transMatch->trans_nilai ?? '0'), '0', 6);
            $transBeratStr = bcadd((string) ($transMatch->trans_berat ?? '0'), '0', 6);
            $aggNilaiStr = bcadd((string) ($agg->agg_nilai ?? '0'), '0', 6);
            $aggBeratStr = bcadd((string) ($agg->agg_berat ?? '0'), '0', 6);

            $diffNilaiStr = ltrim(bcsub($aggNilaiStr, $transNilaiStr, 6), '-');
            $diffBeratStr = ltrim(bcsub($aggBeratStr, $transBeratStr, 6), '-');

            $transNilai = (float) $transNilaiStr;
            $transBerat = (float) $transBeratStr;
            $aggNilai = (float) $aggNilaiStr;
            $aggBerat = (float) $aggBeratStr;
            $diffNilai = (float) $diffNilaiStr;
            $diffBerat = (float) $diffBeratStr;

            $periodStatus = 'CONSISTENT';
            if ($activeTxCount === 0 && bccomp($aggNilaiStr, '0', 6) > 0) {
                $periodStatus = 'ORPHAN_AGGREGATION_DETECTED';
                $results['orphan_aggregations'][] = [
                    'tahun' => $agg->tahun,
                    'bulan' => $agg->bulan,
                    'jenis_transaksi' => $agg->jenis_transaksi,
                    'tipe_sumber' => $aggSumber,
                    'status_visibilitas' => $aggStatus,
                    'orphan_nilai' => $aggNilai,
                    'orphan_berat' => $aggBerat,
                    'summary_count' => $agg->summary_count,
                ];
            } elseif (bccomp($diffNilaiStr, '0', 6) > 0 || bccomp($diffBeratStr, '0', 6) > 0) {
                // Presisi eksak 0.000000 sesuai DECIMAL(20,6) + BCMath scale 6
                $periodStatus = 'DISCREPANCY';
            }

            // Rekonsiliasi Tier-2 (brs_indicator_summaries TOTAL) jika tabel tersedia
            $tier2NilaiStr = null;
            $tier2DiffStr = null;
            if (\Illuminate\Support\Facades\Schema::hasTable('brs_indicator_summaries')) {
                $tier2Query = DB::table('brs_indicator_summaries')
                    ->where('tipe_sumber', $aggSumber)
                    ->whereRaw('LOWER(jenis_transaksi) = ?', [strtolower((string) $agg->jenis_transaksi)])
                    ->where('tahun', $agg->tahun)
                    ->where('bulan', $agg->bulan)
                    ->where('status_visibilitas', $aggStatus)
                    ->where('kategori_dimensi', 'TOTAL')
                    ->where('kode_dimensi', 'TOTAL');

                if ($batchId !== null) {
                    $tier2Query->where('batch_id', $batchId);
                }

                $tier2Row = $tier2Query->selectRaw('COALESCE(SUM(nilai_usd), 0) as t2_nilai, COUNT(*) as t2_count')->first();
                if ($tier2Row && (int) $tier2Row->t2_count > 0) {
                    $tier2NilaiStr = bcadd((string) $tier2Row->t2_nilai, '0', 6);
                    $tier2DiffStr = ltrim(bcsub($tier2NilaiStr, $aggNilaiStr, 6), '-');
                    if (bccomp($tier2DiffStr, '0', 6) > 0 && $periodStatus === 'CONSISTENT') {
                        $periodStatus = 'DISCREPANCY';
                    }

                    $results['tier2_comparison'][] = [
                        'tahun' => $agg->tahun,
                        'bulan' => $agg->bulan,
                        'jenis_transaksi' => $agg->jenis_transaksi,
                        'tipe_sumber' => $aggSumber,
                        'status_visibilitas' => $aggStatus,
                        'tier1_nilai_exact' => $aggNilaiStr,
                        'tier2_nilai_exact' => $tier2NilaiStr,
                        'diff_nilai_exact' => $tier2DiffStr,
                        'status' => bccomp($tier2DiffStr, '0', 6) > 0 ? 'DISCREPANCY' : 'CONSISTENT',
                    ];
                }
            }

            $results['aggregation_comparison'][] = [
                'tahun' => $agg->tahun,
                'bulan' => $agg->bulan,
                'jenis_transaksi' => $agg->jenis_transaksi,
                'tipe_sumber' => $aggSumber,
                'status_visibilitas' => $aggStatus,
                'trans_count' => $activeTxCount,
                'trans_nilai' => $transNilai,
                'agg_nilai' => $aggNilai,
                'diff_nilai' => $diffNilai,
                'diff_nilai_exact' => $diffNilaiStr,
                'trans_berat' => $transBerat,
                'agg_berat' => $aggBerat,
                'diff_berat' => $diffBerat,
                'diff_berat_exact' => $diffBeratStr,
                'tier2_nilai_exact' => $tier2NilaiStr,
                'tier2_diff_exact' => $tier2DiffStr,
                'status' => $periodStatus,
            ];
        }

        // Tentukan status global
        if ($results['unmapped_ports'] > 0 || $results['unmapped_countries'] > 0 || $results['unmapped_commodities'] > 0) {
            $results['status'] = 'UNMAPPED_MASTER_DATA';
            $results['is_consistent'] = false;
        } elseif (!empty($results['orphan_aggregations'])) {
            $results['status'] = 'ORPHAN_AGGREGATION_DETECTED';
            $results['is_consistent'] = false;
        } else {
            foreach ($results['aggregation_comparison'] as $item) {
                if ($item['status'] === 'DISCREPANCY') {
                    $results['status'] = 'DISCREPANCY';
                    $results['is_consistent'] = false;
                    break;
                }
            }
        }

        return $results;
    }
}
