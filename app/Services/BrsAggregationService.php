<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk mengelola Agregasi Dua Tingkat (Two-Tier Mart) BRS SIDATEX.
 * - Tier 1: monthly_trade_summaries (Granular Cube Mart, DECIMAL(20,6))
 * - Tier 2: brs_indicator_summaries (Publication-Ready KPI Mart, DECIMAL(20,6))
 * - Origin Mart (Khusus Distribusi RILIS): monthly_origin_trade_summaries (DECIMAL(20,6))
 *
 * Mengikuti Zero Boilerplate, PSR-12, dan Clean Query Optimization.
 */
class BrsAggregationService
{
    /**
     * Menjalankan agregasi lengkap dua tingkat untuk satu batch tertentu.
     * Bersifat idempoten: menghapus data agregasi lama milik batch/periode ter-link
     * (tanpa pernah menghapus baris historis batch_id IS NULL) lalu menyusun ulang dengan BCMath scale 6.
     */
    public function aggregateBatch(int $batchId, string $statusVisibilitas = 'PRIVATE'): array
    {
        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        if (!$batch) {
            throw new Exception("Data batch #{$batchId} tidak ditemukan.");
        }

        $sourceType = strtolower(trim((string) ($batch->tipe_sumber ?? 'distribusi')));
        if (!in_array($sourceType, ['distribusi', 'neraca'], true)) {
            throw new Exception("Sumber metodologi tidak valid untuk batch #{$batchId}: '{$sourceType}'.");
        }

        $transactionType = ucfirst(strtolower(trim((string) $batch->jenis_data)));
        $statusVisibilitas = in_array(strtoupper($statusVisibilitas), ['PRIVATE', 'RILIS'], true)
            ? strtoupper($statusVisibilitas)
            : 'PRIVATE';

        $txTable = ($sourceType === 'neraca') ? 'neraca_transactions' : 'distribusi_transactions';

        return DB::transaction(function () use (
            $batch,
            $batchId,
            $sourceType,
            $transactionType,
            $statusVisibilitas,
            $txTable
        ) {
            // Ambil seluruh transaksi milik batch ini terlebih dahulu
            $txRows = DB::table($txTable)->where('batch_id', $batchId)->get();

            // Hapus agregasi lama milik batch ini sendiri (tidak pernah menyentuh batch_id IS NULL)
            DB::table('brs_indicator_summaries')->where('batch_id', $batchId)->delete();
            DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->delete();

            if ($txRows->isEmpty()) {
                return [
                    'success' => true,
                    'batch_id' => $batchId,
                    'total_nilai_usd' => '0.000000',
                    'total_berat_kg' => '0.000000',
                    'tier1_rows' => 0,
                    'tier2_rows' => 0,
                ];
            }

            // Kelompokkan transaksi menurut (tahun, bulan) secara kronologis agar batch Excel multi-bulan
            // (periode_tahun = NULL) memproses bulan lebih awal dahulu (Jan sebelum Feb untuk MoM/C-to-C).
            $rowsByPeriod = [];
            foreach ($txRows as $tx) {
                $txTahun = (int) ($tx->tahun ?: ($batch->periode_tahun ?? 0));
                $txBulan = (int) ($tx->bulan ?: ($batch->periode_bulan ?? 0));
                if ($txTahun <= 0 || $txBulan < 1 || $txBulan > 12) {
                    continue;
                }
                $periodKey = sprintf('%04d-%02d', $txTahun, $txBulan);
                $rowsByPeriod[$periodKey]['tahun'] = $txTahun;
                $rowsByPeriod[$periodKey]['bulan'] = $txBulan;
                $rowsByPeriod[$periodKey]['rows'][] = $tx;
            }
            ksort($rowsByPeriod);

            if (empty($rowsByPeriod)) {
                return [
                    'success' => true,
                    'batch_id' => $batchId,
                    'total_nilai_usd' => '0.000000',
                    'total_berat_kg' => '0.000000',
                    'tier1_rows' => 0,
                    'tier2_rows' => 0,
                ];
            }

            // Load master lookup in-memory sekali saja
            $masterHs = DB::table('master_hs')->get()->keyBy('hs_id');
            $masterNegara = DB::table('master_negara')->get()->keyBy('negara_id');
            $allPelabuhan = DB::table('master_pelabuhan')->get()->keyBy('pelabuhan_id');

            $totalNilaiBatch = '0.000000';
            $totalBeratBatch = '0.000000';
            $totalTier1Inserted = 0;
            $totalTier2Inserted = 0;
            $now = now();

            foreach ($rowsByPeriod as $periodData) {
                $tahun = $periodData['tahun'];
                $bulan = $periodData['bulan'];
                $periodRows = $periodData['rows'];

                // Bersihkan baris mart pada scope periode spesifik milik batch aktif.
                // Guard struktural: baris historis (batch_id IS NULL AND pelabuhan_key = 0) dilindungi mutlak
                // dari penghapusan, sementara baris ter-link atau unlinked KEY_GT0 (pelabuhan_key > 0) dibersihkan
                // agar tidak bentrok dengan unique key 8-dimensi uq_mts_8_dimensi.
                DB::table('brs_indicator_summaries')
                    ->where('tipe_sumber', $sourceType)
                    ->where('jenis_transaksi', $transactionType)
                    ->where('tahun', $tahun)
                    ->where('bulan', $bulan)
                    ->delete();

                DB::table('monthly_trade_summaries')
                    ->where('tipe_sumber', $sourceType)
                    ->where('jenis_transaksi', $transactionType)
                    ->where('tahun', $tahun)
                    ->where('bulan', $bulan)
                    ->where(function ($q) {
                        $q->whereNotNull('batch_id')
                            ->orWhere('pelabuhan_key', '>', 0);
                    })
                    ->delete();

                // ─────────────────────────────────────────────────────────
                // KOMPILASI TIER-1: monthly_trade_summaries (Scale 6 penuh)
                // ─────────────────────────────────────────────────────────
                $tier1Groups = [];

                foreach ($periodRows as $tx) {
                    $nilaiStr = (string) ($tx->nilai ?? '0');
                    $beratStr = (string) ($tx->berat ?? '0');
                    $totalNilaiBatch = bcadd($totalNilaiBatch, $nilaiStr, 6);
                    $totalBeratBatch = bcadd($totalBeratBatch, $beratStr, 6);

                    $hsId = $tx->hs_id ? (int) $tx->hs_id : null;
                    $hsCodeRaw = trim((string) ($tx->hs_code_raw ?? ''));
                    $mappingStatus = $tx->mapping_status ?? ($hsId ? 'mapped' : 'unmapped');

                    $hsObj = $hsId && isset($masterHs[$hsId]) ? $masterHs[$hsId] : null;

                    $hs2 = $hsObj ? (string) $hsObj->hs2 : (strlen($hsCodeRaw) >= 2 ? substr($hsCodeRaw, 0, 2) : 'UN');
                    if ($hs2 === '') {
                        $hs2 = 'UN';
                    }

                    $isMigas = false;
                    $sektor = null;
                    $becCategory = null;

                    if ($hsObj) {
                        $oilgrp = strtoupper(trim((string) $hsObj->oilgrp_desc));
                        $isMigas = ($oilgrp === 'MIGAS' || $hsObj->hs2 === '27');
                        $sektor = $isMigas ? 'MIGAS' : ($hsObj->sektor ?: 'INDUSTRI PENGOLAHAN');
                        $becCategory = $hsObj->bec_desc;
                    } else {
                        $mappingStatus = 'unmapped';
                        $sektor = 'UNMAPPED';
                        $becCategory = 'UNMAPPED';
                    }

                    $negaraId = (int) $tx->negara_id;
                    $provinsiId = (int) ($tx->provinsi_id ?? 4);
                    $pelabuhanId = $tx->pelabuhan_id ? (int) $tx->pelabuhan_id : null;
                    $pelabuhanKey = $pelabuhanId ?? 0;
                    $provorigKey = trim((string) ($tx->provorig ?? ''));

                    // Kunci grup Tier-1: sertakan $hsCodeRaw saat unmapped agar kode HS mentah berbeda tetap terlacak
                    $hsIdentityKey = $hsId !== null ? (string) $hsId : ('UNMAPPED:' . $hsCodeRaw);
                    $cubeKey = implode('|', [
                        $sourceType,
                        $hsIdentityKey,
                        $negaraId,
                        $provinsiId,
                        $pelabuhanKey,
                        $transactionType,
                        $bulan,
                        $tahun,
                    ]);

                    if (!isset($tier1Groups[$cubeKey])) {
                        $tier1Groups[$cubeKey] = [
                            'batch_id' => $batchId,
                            'status_visibilitas' => $statusVisibilitas,
                            'tipe_sumber' => $sourceType,
                            'jenis_transaksi' => $transactionType,
                            'tahun' => $tahun,
                            'bulan' => $bulan,
                            'hs_id' => $hsId,
                            'hs2' => $hs2,
                            'hs_code_raw' => $hsCodeRaw !== '' ? $hsCodeRaw : null,
                            'mapping_status' => $mappingStatus,
                            'is_migas' => $isMigas,
                            'sektor' => $sektor,
                            'bec_category' => $becCategory,
                            'provorig_key' => $provorigKey,
                            'negara_id' => $negaraId,
                            'provinsi_id' => $provinsiId,
                            'pelabuhan_id' => $pelabuhanId,
                            'pelabuhan_key' => $pelabuhanKey,
                            'total_berat' => '0.000000',
                            'total_nilai' => '0.000000',
                            'jumlah_transaksi' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    $tier1Groups[$cubeKey]['total_nilai'] = bcadd($tier1Groups[$cubeKey]['total_nilai'], $nilaiStr, 6);
                    $tier1Groups[$cubeKey]['total_berat'] = bcadd($tier1Groups[$cubeKey]['total_berat'], $beratStr, 6);
                    $tier1Groups[$cubeKey]['jumlah_transaksi']++;
                }

                // Insert Tier-1 chunks
                $tier1Inserts = array_values($tier1Groups);
                foreach (array_chunk($tier1Inserts, 500) as $chunk) {
                    DB::table('monthly_trade_summaries')->insert($chunk);
                }
                $totalTier1Inserted += count($tier1Inserts);

                // ─────────────────────────────────────────────────────────
                // KOMPILASI TIER-2: brs_indicator_summaries (Scale 6 penuh)
                // ─────────────────────────────────────────────────────────
                $indicatorRows = $this->compileBrsIndicators(
                    $batchId,
                    $statusVisibilitas,
                    $sourceType,
                    $transactionType,
                    $tahun,
                    $bulan,
                    $tier1Inserts,
                    $masterNegara,
                    $allPelabuhan
                );

                foreach (array_chunk($indicatorRows, 200) as $indChunk) {
                    DB::table('brs_indicator_summaries')->insert($indChunk);
                }
                $totalTier2Inserted += count($indicatorRows);

                // Origin summaries HANYA untuk Distribusi (tidak pernah menyentuh saat Neraca)
                if ($sourceType === 'distribusi') {
                    $this->syncOriginSummariesForPeriod($tahun, $bulan, $transactionType);
                }
            }

            return [
                'success' => true,
                'batch_id' => $batchId,
                'total_nilai_usd' => $totalNilaiBatch,
                'total_berat_kg' => $totalBeratBatch,
                'tier1_rows' => $totalTier1Inserted,
                'tier2_rows' => $totalTier2Inserted,
            ];
        });
    }

    /**
     * Menghapus agregasi Tier-1 & Tier-2 milik satu batch tertentu secara atomik
     * (misalnya saat soft-delete ke Trash atau force-delete) tanpa menyentuh batch lain
     * maupun baris historis batch_id IS NULL.
     */
    public function removeBatchAggregation(int $batchId): void
    {
        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        $sourceType = $batch ? strtolower(trim((string) ($batch->tipe_sumber ?? 'distribusi'))) : null;

        // Kumpulkan periode terdampak sebelum menghapus baris mart
        $affectedPeriods = DB::table('monthly_trade_summaries')
            ->where('batch_id', $batchId)
            ->select('tahun', 'bulan', 'jenis_transaksi', 'tipe_sumber')
            ->distinct()
            ->get();

        // Fallback jika batch Distribusi lama belum memiliki baris mart ter-link
        if ($sourceType === 'distribusi' && $affectedPeriods->isEmpty()) {
            $affectedPeriods = DB::table('distribusi_transactions')
                ->where('batch_id', $batchId)
                ->select('tahun', 'bulan', 'jenis_transaksi', DB::raw("'distribusi' as tipe_sumber"))
                ->distinct()
                ->get();
        }

        DB::transaction(function () use ($batchId, $sourceType, $affectedPeriods) {
            DB::table('brs_indicator_summaries')->where('batch_id', $batchId)->delete();
            DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->delete();

            // Jika Distribusi, segarkan origin summaries hanya untuk periode yang terdampak
            if ($sourceType === 'distribusi') {
                foreach ($affectedPeriods as $p) {
                    if ($p->tipe_sumber === 'distribusi') {
                        $this->syncOriginSummariesForPeriod((int) $p->tahun, (int) $p->bulan, (string) $p->jenis_transaksi);
                    }
                }
            }
        });
    }

    /**
     * Menyinkronkan monthly_origin_trade_summaries khusus untuk metodologi Distribusi
     * dan hanya mengagregasi transaksi aktif (non-Trash) dari batch berstatus RILIS.
     */
    public function syncOriginSummariesForPeriod(int $tahun, int $bulan, string $jenisTransaksi): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('monthly_origin_trade_summaries')) {
            return;
        }

        $jenisLower = strtolower(trim($jenisTransaksi));

        DB::table('monthly_origin_trade_summaries')
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->where('jenis_transaksi', $jenisLower)
            ->delete();

        $activeRilisBatchIds = DB::table('data_batches')
            ->where('tipe_sumber', 'distribusi')
            ->where('status', 'RILIS')
            ->whereNotIn('batch_id', function ($sub) {
                $sub->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->pluck('batch_id');

        if ($activeRilisBatchIds->isEmpty()) {
            return;
        }

        $rows = DB::table('distribusi_transactions as t')
            ->leftJoin('master_hs as h', 't.hs_id', '=', 'h.hs_id')
            ->whereIn('t.batch_id', $activeRilisBatchIds)
            ->where('t.tipe_sumber', 'distribusi')
            ->where('t.tahun', $tahun)
            ->where('t.bulan', $bulan)
            ->whereRaw('LOWER(t.jenis_transaksi) = ?', [$jenisLower])
            ->whereNotNull('t.provorig')
            ->where('t.provorig', '!=', '')
            ->select('t.provorig', 't.berat', 't.nilai', 'h.hs2', 'h.sektor', 'h.oilgrp_desc')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $grouped = [];
        $now = now();

        foreach ($rows as $row) {
            $provorig = strtoupper(trim((string) $row->provorig));
            $isRiau = str_starts_with($provorig, '14');
            $hs2 = $row->hs2 ? strtoupper(trim((string) $row->hs2)) : null;
            $sektor = $row->sektor ? strtoupper(trim((string) $row->sektor)) : null;
            $oilgrp = strtoupper(trim((string) ($row->oilgrp_desc ?? '')));
            $isMigas = ($oilgrp === 'MIGAS' || $hs2 === '27') ? 1 : 0;
            $key = implode('|', [$provorig, $hs2 ?? 'NULL', $sektor ?? 'NULL', $isMigas]);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'tahun' => $tahun,
                    'bulan' => $bulan,
                    'jenis_transaksi' => $jenisLower,
                    'provorig' => $provorig,
                    'is_riau' => $isRiau,
                    'hs2' => $hs2,
                    'sektor' => $sektor,
                    'is_migas' => (bool) $isMigas,
                    'total_berat' => '0.000000',
                    'total_nilai' => '0.000000',
                    'jumlah_transaksi' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $grouped[$key]['total_berat'] = bcadd($grouped[$key]['total_berat'], (string) ($row->berat ?? '0'), 6);
            $grouped[$key]['total_nilai'] = bcadd($grouped[$key]['total_nilai'], (string) ($row->nilai ?? '0'), 6);
            $grouped[$key]['jumlah_transaksi']++;
        }

        foreach (array_chunk(array_values($grouped), 500) as $chunk) {
            DB::table('monthly_origin_trade_summaries')->insert($chunk);
        }
    }

    /**
     * Mengompilasi seluruh indikator publikasi BRS untuk Tier-2 dengan presisi penuh BCMath scale 6.
     */
    protected function compileBrsIndicators(
        int $batchId,
        string $statusVisibilitas,
        string $sourceType,
        string $transactionType,
        int $tahun,
        int $bulan,
        array $tier1Records,
        $masterNegara,
        $allPelabuhan
    ): array {
        $now = now();
        $indicators = [];

        // 1. Akumulasi Agregat Dasar (Scale 6 penuh)
        $totalUsd = '0.000000';
        $totalKg = '0.000000';
        $migasUsd = '0.000000';
        $migasKg = '0.000000';
        $nonMigasUsd = '0.000000';
        $nonMigasKg = '0.000000';
        $unmappedUsd = '0.000000';
        $unmappedKg = '0.000000';

        $sektorMap = [
            'INDUSTRI PENGOLAHAN' => ['usd' => '0.000000', 'kg' => '0.000000'],
            'PERTANIAN' => ['usd' => '0.000000', 'kg' => '0.000000'],
            'PERTAMBANGAN' => ['usd' => '0.000000', 'kg' => '0.000000'],
            'MIGAS' => ['usd' => '0.000000', 'kg' => '0.000000'],
            'UNMAPPED' => ['usd' => '0.000000', 'kg' => '0.000000'],
        ];

        $becMap = [
            'Bahan Baku/Penolong' => ['usd' => '0.000000', 'kg' => '0.000000'],
            'Barang Modal' => ['usd' => '0.000000', 'kg' => '0.000000'],
            'Barang Konsumsi' => ['usd' => '0.000000', 'kg' => '0.000000'],
            'UNMAPPED' => ['usd' => '0.000000', 'kg' => '0.000000'],
        ];

        $hs2Map = [];
        $negaraMap = [];
        $pelabuhanMap = [];

        foreach ($tier1Records as $r) {
            $val = (string) $r['total_nilai'];
            $wgt = (string) $r['total_berat'];

            $totalUsd = bcadd($totalUsd, $val, 6);
            $totalKg = bcadd($totalKg, $wgt, 6);

            if ($r['mapping_status'] === 'unmapped') {
                // AGENTS.md §7: HS unmapped TIDAK BOLEH otomatis dimasukkan ke Non-Migas
                $unmappedUsd = bcadd($unmappedUsd, $val, 6);
                $unmappedKg = bcadd($unmappedKg, $wgt, 6);
            } elseif ($r['is_migas']) {
                $migasUsd = bcadd($migasUsd, $val, 6);
                $migasKg = bcadd($migasKg, $wgt, 6);
            } else {
                $nonMigasUsd = bcadd($nonMigasUsd, $val, 6);
                $nonMigasKg = bcadd($nonMigasKg, $wgt, 6);
            }

            // Sektor (Khusus Ekspor)
            if ($r['is_migas']) {
                $sNorm = 'MIGAS';
            } elseif ($r['mapping_status'] === 'unmapped') {
                $sNorm = 'UNMAPPED';
            } else {
                $sKey = strtoupper(trim((string) $r['sektor']));
                if (str_contains($sKey, 'INDUSTRI')) {
                    $sNorm = 'INDUSTRI PENGOLAHAN';
                } elseif (str_contains($sKey, 'TANI')) {
                    $sNorm = 'PERTANIAN';
                } elseif (str_contains($sKey, 'TAMBANG')) {
                    $sNorm = 'PERTAMBANGAN';
                } else {
                    $sNorm = 'INDUSTRI PENGOLAHAN';
                }
            }

            if (!isset($sektorMap[$sNorm])) {
                $sektorMap[$sNorm] = ['usd' => '0.000000', 'kg' => '0.000000'];
            }
            $sektorMap[$sNorm]['usd'] = bcadd($sektorMap[$sNorm]['usd'], $val, 6);
            $sektorMap[$sNorm]['kg'] = bcadd($sektorMap[$sNorm]['kg'], $wgt, 6);

            // BEC (Khusus Impor)
            $bKey = trim((string) $r['bec_category']);
            if ($r['mapping_status'] === 'unmapped') {
                $bNorm = 'UNMAPPED';
            } elseif (str_contains(strtolower($bKey), 'baku') || str_contains(strtolower($bKey), 'penolong')) {
                $bNorm = 'Bahan Baku/Penolong';
            } elseif (str_contains(strtolower($bKey), 'modal')) {
                $bNorm = 'Barang Modal';
            } elseif (str_contains(strtolower($bKey), 'konsumsi')) {
                $bNorm = 'Barang Konsumsi';
            } else {
                $bNorm = 'Bahan Baku/Penolong';
            }

            if (!isset($becMap[$bNorm])) {
                $becMap[$bNorm] = ['usd' => '0.000000', 'kg' => '0.000000'];
            }
            $becMap[$bNorm]['usd'] = bcadd($becMap[$bNorm]['usd'], $val, 6);
            $becMap[$bNorm]['kg'] = bcadd($becMap[$bNorm]['kg'], $wgt, 6);

            // HS2
            $h2 = (string) $r['hs2'];
            if (!isset($hs2Map[$h2])) {
                $hs2Map[$h2] = ['usd' => '0.000000', 'kg' => '0.000000'];
            }
            $hs2Map[$h2]['usd'] = bcadd($hs2Map[$h2]['usd'], $val, 6);
            $hs2Map[$h2]['kg'] = bcadd($hs2Map[$h2]['kg'], $wgt, 6);

            // Negara
            $nId = (int) $r['negara_id'];
            if (!isset($negaraMap[$nId])) {
                $negaraMap[$nId] = ['usd' => '0.000000', 'kg' => '0.000000'];
            }
            $negaraMap[$nId]['usd'] = bcadd($negaraMap[$nId]['usd'], $val, 6);
            $negaraMap[$nId]['kg'] = bcadd($negaraMap[$nId]['kg'], $wgt, 6);

            // Pelabuhan
            $pKey = (int) $r['pelabuhan_key'];
            if (!isset($pelabuhanMap[$pKey])) {
                $pelabuhanMap[$pKey] = ['usd' => '0.000000', 'kg' => '0.000000'];
            }
            $pelabuhanMap[$pKey]['usd'] = bcadd($pelabuhanMap[$pKey]['usd'], $val, 6);
            $pelabuhanMap[$pKey]['kg'] = bcadd($pelabuhanMap[$pKey]['kg'], $wgt, 6);
        }

        // Ambil data pembanding historis dari DB untuk MoM (wrap Jan -> Des tahun sebelumnya), YoY, dan C-to-C
        // Dengan isolasi visibilitas ketat: indikator RILIS hanya membandingkan terhadap periode RILIS.
        $prevMonthYear = ($bulan === 1) ? $tahun - 1 : $tahun;
        $prevMonthNum = ($bulan === 1) ? 12 : $bulan - 1;

        $prevMonthUsd = $this->lookupHistoricalValue($sourceType, $transactionType, $prevMonthYear, $prevMonthNum, $statusVisibilitas);
        $sameMonthPrevYearUsd = $this->lookupHistoricalValue($sourceType, $transactionType, $tahun - 1, $bulan, $statusVisibilitas);
        $cumThisYearUsd = $this->lookupCumulativeValue($sourceType, $transactionType, $tahun, $bulan, $totalUsd, $statusVisibilitas);
        $cumPrevYearUsd = $this->lookupCumulativeValue($sourceType, $transactionType, $tahun - 1, $bulan, '0.000000', $statusVisibilitas);

        // Pertumbuhan Makro
        $mtomTotal = $this->calcGrowth($totalUsd, $prevMonthUsd);
        $yoyTotal = $this->calcGrowth($totalUsd, $sameMonthPrevYearUsd);
        $ctcTotal = $this->calcGrowth($cumThisYearUsd, $cumPrevYearUsd);

        // Helper inserter
        $makeRow = function (
            $katDim,
            $kdDim,
            $nmDim,
            $valUsd,
            $wgtKg,
            $share = null,
            $mtom = null,
            $yoy = null,
            $ctc = null
        ) use ($batchId, $statusVisibilitas, $sourceType, $transactionType, $tahun, $bulan, $now) {
            $jutaUsd = bcdiv($valUsd, '1000000', 8);
            $ribuTon = bcdiv($wgtKg, '1000000', 8);

            return [
                'batch_id' => $batchId,
                'status_visibilitas' => $statusVisibilitas,
                'tipe_sumber' => $sourceType,
                'jenis_transaksi' => $transactionType,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'periode_tipe' => 'BULANAN',
                'kategori_dimensi' => $katDim,
                'kode_dimensi' => (string) $kdDim,
                'nama_dimensi' => $nmDim,
                'nilai_usd' => $valUsd,
                'nilai_juta_usd' => round((float) $jutaUsd, 2),
                'volume_ribu_ton' => round((float) $ribuTon, 2),
                'share_percentage' => $share,
                'growth_mtom' => $mtom,
                'growth_yoy' => $yoy,
                'growth_ctc' => $ctc,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        // 1. DIMENSI: TOTAL
        $indicators[] = $makeRow('TOTAL', 'TOTAL', "Total {$transactionType} Riau", $totalUsd, $totalKg, 100.0, $mtomTotal, $yoyTotal, $ctcTotal);

        // 2. DIMENSI: MIGAS_NONMIGAS (MIGAS + NON_MIGAS + UNMAPPED = TOTAL; HS unmapped tidak dimasukkan ke NON_MIGAS)
        $shareMigas = $this->calcShare($migasUsd, $totalUsd);
        $shareNonMigas = $this->calcShare($nonMigasUsd, $totalUsd);
        $indicators[] = $makeRow('MIGAS_NONMIGAS', 'MIGAS', "{$transactionType} Migas", $migasUsd, $migasKg, $shareMigas);
        $indicators[] = $makeRow('MIGAS_NONMIGAS', 'NON_MIGAS', "{$transactionType} Non-Migas", $nonMigasUsd, $nonMigasKg, $shareNonMigas);
        if (bccomp($unmappedUsd, '0', 6) > 0) {
            $shareUnmapped = $this->calcShare($unmappedUsd, $totalUsd);
            $indicators[] = $makeRow('MIGAS_NONMIGAS', 'UNMAPPED', "{$transactionType} HS Unmapped", $unmappedUsd, $unmappedKg, $shareUnmapped);
        }

        // 3. DIMENSI: SEKTOR (Khusus Ekspor)
        // Penyebut share: MIGAS & UNMAPPED terhadap TOTAL; sektor Non-Migas terhadap TOTAL NON-MIGAS
        if ($transactionType === 'Ekspor') {
            foreach ($sektorMap as $sName => $sData) {
                if (bccomp($sData['usd'], '0', 6) > 0) {
                    $denom = in_array($sName, ['MIGAS', 'UNMAPPED'], true) ? $totalUsd : $nonMigasUsd;
                    $share = $this->calcShare($sData['usd'], $denom);
                    $indicators[] = $makeRow('SEKTOR', $sName, "Sektor {$sName}", $sData['usd'], $sData['kg'], $share);
                }
            }
        }

        // 4. DIMENSI: BEC (Khusus Impor)
        if ($transactionType === 'Impor') {
            foreach ($becMap as $bName => $bData) {
                if (bccomp($bData['usd'], '0', 6) > 0) {
                    $share = $this->calcShare($bData['usd'], $totalUsd);
                    $indicators[] = $makeRow('BEC', $bName, "Golongan {$bName}", $bData['usd'], $bData['kg'], $share);
                }
            }
        }

        // 5. DIMENSI: TOP 10 HS2 (Golongan Barang Terbesar)
        uasort($hs2Map, function ($a, $b) {
            return bccomp($b['usd'], $a['usd'], 6);
        });

        $top10Hs2Count = 0;
        $accumTop10Hs2 = '0.000000';
        $accumTop10Hs2Wgt = '0.000000';

        foreach ($hs2Map as $h2 => $hData) {
            if ($top10Hs2Count < 10) {
                $top10Hs2Count++;
                $accumTop10Hs2 = bcadd($accumTop10Hs2, $hData['usd'], 6);
                $accumTop10Hs2Wgt = bcadd($accumTop10Hs2Wgt, $hData['kg'], 6);
                $share = $this->calcShare($hData['usd'], $totalUsd);
                $indicators[] = $makeRow('HS2_TOP10', $h2, "Bab {$h2}", $hData['usd'], $hData['kg'], $share);
            }
        }

        // Komponen Lainnya untuk HS2
        $sisaHs2Usd = bcsub($totalUsd, $accumTop10Hs2, 6);
        $sisaHs2Wgt = bcsub($totalKg, $accumTop10Hs2Wgt, 6);
        if (bccomp($sisaHs2Usd, '0', 6) > 0) {
            $shareSisa = $this->calcShare($sisaHs2Usd, $totalUsd);
            $indicators[] = $makeRow('HS2_TOP10', 'LAINNYA', 'Golongan Barang Lainnya', $sisaHs2Usd, $sisaHs2Wgt, $shareSisa);
        }

        // 6. DIMENSI: TOP 10 NEGARA
        uasort($negaraMap, function ($a, $b) {
            return bccomp($b['usd'], $a['usd'], 6);
        });

        $top10NegCount = 0;
        $accumTop10Neg = '0.000000';
        $accumTop10NegWgt = '0.000000';

        $aseanUsd = '0.000000';
        $aseanKg = '0.000000';
        $ueUsd = '0.000000';
        $ueKg = '0.000000';

        foreach ($negaraMap as $nId => $nData) {
            $negObj = $masterNegara->get($nId);
            $negNama = $negObj ? $negObj->nama_negara : "Negara ID {$nId}";
            $isAsean = $negObj && (bool) ($negObj->is_asean ?? false);
            $isUe = $negObj && (bool) ($negObj->is_eu ?? false);

            if ($isAsean) {
                $aseanUsd = bcadd($aseanUsd, $nData['usd'], 6);
                $aseanKg = bcadd($aseanKg, $nData['kg'], 6);
            }
            if ($isUe) {
                $ueUsd = bcadd($ueUsd, $nData['usd'], 6);
                $ueKg = bcadd($ueKg, $nData['kg'], 6);
            }

            if ($top10NegCount < 10) {
                $top10NegCount++;
                $accumTop10Neg = bcadd($accumTop10Neg, $nData['usd'], 6);
                $accumTop10NegWgt = bcadd($accumTop10NegWgt, $nData['kg'], 6);
                $share = $this->calcShare($nData['usd'], $totalUsd);
                $indicators[] = $makeRow('NEGARA_TOP10', (string) $nId, $negNama, $nData['usd'], $nData['kg'], $share);
            }
        }

        $sisaNegUsd = bcsub($totalUsd, $accumTop10Neg, 6);
        $sisaNegWgt = bcsub($totalKg, $accumTop10NegWgt, 6);
        if (bccomp($sisaNegUsd, '0', 6) > 0) {
            $shareSisa = $this->calcShare($sisaNegUsd, $totalUsd);
            $indicators[] = $makeRow('NEGARA_TOP10', 'LAINNYA', 'Negara Lainnya', $sisaNegUsd, $sisaNegWgt, $shareSisa);
        }

        if (bccomp($aseanUsd, '0', 6) > 0) {
            $shareAsean = $this->calcShare($aseanUsd, $totalUsd);
            $indicators[] = $makeRow('NEGARA_TOP10', 'BLOK_ASEAN', 'Kawasan ASEAN', $aseanUsd, $aseanKg, $shareAsean);
        }
        if (bccomp($ueUsd, '0', 6) > 0) {
            $shareUe = $this->calcShare($ueUsd, $totalUsd);
            $indicators[] = $makeRow('NEGARA_TOP10', 'BLOK_UNI_EROPA', 'Kawasan Uni Eropa', $ueUsd, $ueKg, $shareUe);
        }

        // 7. DIMENSI: PELABUHAN
        uasort($pelabuhanMap, function ($a, $b) {
            return bccomp($b['usd'], $a['usd'], 6);
        });

        foreach ($pelabuhanMap as $pId => $pData) {
            $pObj = $pId > 0 && isset($allPelabuhan[$pId]) ? $allPelabuhan[$pId] : null;
            $pNama = $pObj ? $pObj->nama_pelabuhan : ($pId === 0 ? 'Luar Daerah / Tanpa Pelabuhan' : "Pelabuhan ID {$pId}");
            $share = $this->calcShare($pData['usd'], $totalUsd);
            $indicators[] = $makeRow('PELABUHAN', (string) $pId, $pNama, $pData['usd'], $pData['kg'], $share);
        }

        return $indicators;
    }

    /**
     * Helper perhitungan pertumbuhan standar BPS (BCMath scale 8 -> pembulatan akhir 2 desimal).
     */
    protected function calcGrowth(?string $currVal, ?string $prevVal): ?float
    {
        if ($currVal === null || $prevVal === null) {
            return null;
        }

        if (bccomp($prevVal, '0', 6) <= 0) {
            return null;
        }

        $ratio = bcdiv($currVal, $prevVal, 8);
        $pct = bcmul($ratio, '100', 6);
        $growth = bcsub($pct, '100', 6);

        return round((float) $growth, 2);
    }

    /**
     * Helper perhitungan pangsa kontribusi (share % dengan pembulatan akhir 2 desimal).
     */
    protected function calcShare(string $itemVal, string $totalVal): float
    {
        if (bccomp($totalVal, '0', 6) <= 0) {
            return 0.0;
        }

        $share = bcdiv($itemVal, $totalVal, 8);
        $sharePct = bcmul($share, '100', 6);

        return round((float) $sharePct, 2);
    }

    /**
     * Mencari nilai total historis di database untuk periode tertentu dengan isolasi status visibilitas.
     * Bila menyusun baris RILIS, hanya periode pembanding RILIS yang boleh digunakan.
     */
    protected function lookupHistoricalValue(
        string $sourceType,
        string $transactionType,
        int $tahun,
        int $bulan,
        string $statusVisibilitas = 'RILIS'
    ): ?string {
        if ($tahun < 1 || $bulan < 1 || $bulan > 12) {
            return null;
        }

        $allowedStatuses = (strtoupper($statusVisibilitas) === 'RILIS')
            ? ['RILIS']
            : ['RILIS', 'PRIVATE'];

        $rec = DB::table('brs_indicator_summaries')
            ->where('tipe_sumber', $sourceType)
            ->where('jenis_transaksi', $transactionType)
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->where('kategori_dimensi', 'TOTAL')
            ->where('kode_dimensi', 'TOTAL')
            ->whereIn('status_visibilitas', $allowedStatuses)
            ->first();

        if ($rec) {
            return (string) $rec->nilai_usd;
        }

        $sum = DB::table('monthly_trade_summaries')
            ->where('tipe_sumber', $sourceType)
            ->where('jenis_transaksi', $transactionType)
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->whereIn('status_visibilitas', $allowedStatuses)
            ->sum('total_nilai');

        return ($sum !== null && bccomp((string) $sum, '0', 6) > 0) ? (string) $sum : null;
    }

    /**
     * Menghitung nilai kumulatif Jan s.d. bulan berjalan (BCMath scale 6) dengan isolasi status visibilitas.
     */
    protected function lookupCumulativeValue(
        string $sourceType,
        string $transactionType,
        int $tahun,
        int $bulan,
        string $currentMonthUsd = '0.000000',
        string $statusVisibilitas = 'RILIS'
    ): ?string {
        $cumSum = '0.000000';
        $hasData = false;

        for ($m = 1; $m <= $bulan; $m++) {
            $mVal = ($m === $bulan && bccomp($currentMonthUsd, '0', 6) > 0)
                ? $currentMonthUsd
                : $this->lookupHistoricalValue($sourceType, $transactionType, $tahun, $m, $statusVisibilitas);

            if ($mVal !== null) {
                $cumSum = bcadd($cumSum, $mVal, 6);
                $hasData = true;
            }
        }

        return $hasData ? $cumSum : null;
    }

    /**
     * Memutakhirkan visibilitas batch (PRIVATE <-> RILIS) secara atomik pada data_batches dan kedua tier mart.
     */
    public function updateBatchVisibility(int $batchId, string $targetStatus, int $userId): bool
    {
        $targetStatus = strtoupper(trim($targetStatus));
        if (!in_array($targetStatus, ['PRIVATE', 'RILIS'], true)) {
            return false;
        }

        return DB::transaction(function () use ($batchId, $targetStatus, $userId) {
            $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
            if (!$batch) {
                return false;
            }

            $releasedBy = ($targetStatus === 'RILIS') ? $userId : null;
            $releasedAt = ($targetStatus === 'RILIS') ? now() : null;

            DB::table('data_batches')->where('batch_id', $batchId)->update([
                'status' => $targetStatus,
                'released_by' => $releasedBy,
                'released_at' => $releasedAt,
                'updated_at' => now(),
            ]);

            $existingTier1Count = DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->count();
            $existingTier2Count = DB::table('brs_indicator_summaries')->where('batch_id', $batchId)->count();
            if ($existingTier1Count === 0 || $existingTier2Count === 0) {
                // Bangun ulang mart jika salah satu tier belum memiliki baris ter-link
                $this->aggregateBatch($batchId, $targetStatus);
                return true;
            }

            DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->update([
                'status_visibilitas' => $targetStatus,
                'updated_at' => now(),
            ]);

            DB::table('brs_indicator_summaries')->where('batch_id', $batchId)->update([
                'status_visibilitas' => $targetStatus,
                'updated_at' => now(),
            ]);

            // Jika Distribusi, segarkan origin summaries sesuai status RILIS terbaru
            if (strtolower((string) ($batch->tipe_sumber ?? 'distribusi')) === 'distribusi') {
                $periods = DB::table('monthly_trade_summaries')
                    ->where('batch_id', $batchId)
                    ->select('tahun', 'bulan', 'jenis_transaksi')
                    ->distinct()
                    ->get();

                foreach ($periods as $p) {
                    $this->syncOriginSummariesForPeriod((int) $p->tahun, (int) $p->bulan, (string) $p->jenis_transaksi);
                }
            }

            return true;
        });
    }
}
