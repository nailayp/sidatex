<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service kompatibilitas agregasi periodik SIDATEX.
 * Mendelegasikan pembangunan Two-Tier Mart ke BrsAggregationService agar
 * seluruh kolom metadata BRS, presisi DECIMAL(20,6), pemisahan Distribusi vs Neraca,
 * dan perlindungan baris historis (batch_id IS NULL) selalu konsisten.
 */
class TradeAggregationService
{
    protected BrsAggregationService $brsAggregationService;

    public function __construct(?BrsAggregationService $brsAggregationService = null)
    {
        $this->brsAggregationService = $brsAggregationService ?? app(BrsAggregationService::class);
    }

    /**
     * Hitung ulang agregasi Two-Tier Mart untuk kombinasi (tahun, bulan, jenis_transaksi, tipe_sumber).
     * Tidak pernah membaca tabel arsip `transactions` dan tidak pernah menghapus baris historis `batch_id IS NULL`.
     *
     * @param int|null $tahun Jika null, hitung seluruh batch aktif pada sumber tersebut
     * @param int|null $bulan Jika null, hitung seluruh bulan untuk tahun tersebut
     * @param string|null $jenisTransaksi 'Ekspor' atau 'Impor' (case-insensitive)
     * @param string $tipeSumber 'distribusi' atau 'neraca'
     * @return int Jumlah baris Tier-1 (monthly_trade_summaries) yang dibuat/diperbarui
     */
    public function recalculatePeriod(?int $tahun = null, ?int $bulan = null, ?string $jenisTransaksi = null, string $tipeSumber = 'distribusi'): int
    {
        $tipeSumber = strtolower(trim($tipeSumber));
        if (!in_array($tipeSumber, ['distribusi', 'neraca'], true)) {
            throw new \InvalidArgumentException("Sumber metodologi tidak valid: '{$tipeSumber}'. Hanya menerima 'distribusi' atau 'neraca'.");
        }

        $txTable = ($tipeSumber === 'neraca') ? 'neraca_transactions' : 'distribusi_transactions';

        return DB::transaction(function () use ($tahun, $bulan, $jenisTransaksi, $tipeSumber, $txTable) {
            // 1. Hapus HANYA baris agregasi ter-link (batch_id IS NOT NULL) pada scope yang dipilih.
            // Baris historis (batch_id IS NULL) dan sumber metodologi lain dilindungi secara mutlak.
            $deleteMts = DB::table('monthly_trade_summaries')
                ->whereNotNull('batch_id')
                ->where('tipe_sumber', $tipeSumber);

            $deleteBrs = DB::table('brs_indicator_summaries')
                ->where('tipe_sumber', $tipeSumber);

            if ($tahun !== null) {
                $deleteMts->where('tahun', $tahun);
                $deleteBrs->where('tahun', $tahun);
            }
            if ($bulan !== null) {
                $deleteMts->where('bulan', $bulan);
                $deleteBrs->where('bulan', $bulan);
            }
            if ($jenisTransaksi !== null) {
                $jenisLower = strtolower(trim($jenisTransaksi));
                $deleteMts->whereRaw('LOWER(jenis_transaksi) = ?', [$jenisLower]);
                $deleteBrs->whereRaw('LOWER(jenis_transaksi) = ?', [$jenisLower]);
            }

            $deleteBrs->delete();
            $deleteMts->delete();

            // 2. Temukan seluruh batch aktif (PRIVATE/RILIS, non-Trash) pada sumber metodologi yang sama
            $activeBatches = DB::table('data_batches')
                ->where('tipe_sumber', $tipeSumber)
                ->whereIn('status', ['PRIVATE', 'RILIS'])
                ->whereNotIn('batch_id', function ($query) {
                    $query->select('batch_id')
                        ->from('trash')
                        ->whereNull('restored_at');
                })
                ->select('batch_id', 'status')
                ->get();

            $activeBatchIds = $activeBatches->pluck('batch_id')->all();
            $batchStatusMap = $activeBatches->pluck('status', 'batch_id')->all();

            $matchingBatchIds = [];
            if (!empty($activeBatchIds)) {
                $txScope = DB::table($txTable)
                    ->whereIn('batch_id', $activeBatchIds)
                    ->where('tipe_sumber', $tipeSumber);

                if ($tahun !== null) {
                    $txScope->where('tahun', $tahun);
                }
                if ($bulan !== null) {
                    $txScope->where('bulan', $bulan);
                }
                if ($jenisTransaksi !== null) {
                    $txScope->whereRaw('LOWER(jenis_transaksi) = ?', [strtolower(trim($jenisTransaksi))]);
                }

                $matchingBatchIds = $txScope->distinct()->pluck('batch_id')->all();
            }

            // 3. Delegasikan kompilasi Tier-1 & Tier-2 per batch aktif ke BrsAggregationService
            $insertedCount = 0;
            foreach ($matchingBatchIds as $batchId) {
                $status = (string) ($batchStatusMap[$batchId] ?? 'PRIVATE');
                $res = $this->brsAggregationService->aggregateBatch((int) $batchId, $status);
                $insertedCount += (int) ($res['tier1_rows'] ?? 0);
            }

            // 4. Jika Distribusi dan tidak ada batch tersisa pada periode spesifik, bersihkan origin summary periode tsb
            // (Tidak pernah menyentuh monthly_origin_trade_summaries saat tipeSumber === 'neraca')
            if ($tipeSumber === 'distribusi' && empty($matchingBatchIds) && $tahun !== null) {
                if ($bulan !== null && $jenisTransaksi !== null) {
                    $this->brsAggregationService->syncOriginSummariesForPeriod($tahun, $bulan, $jenisTransaksi);
                } elseif (\Illuminate\Support\Facades\Schema::hasTable('monthly_origin_trade_summaries')) {
                    $originCleanup = DB::table('monthly_origin_trade_summaries')->where('tahun', $tahun);
                    if ($bulan !== null) {
                        $originCleanup->where('bulan', $bulan);
                    }
                    if ($jenisTransaksi !== null) {
                        $originCleanup->where('jenis_transaksi', strtolower(trim($jenisTransaksi)));
                    }
                    $originCleanup->delete();
                }
            }

            Log::info('TradeAggregationService: Recalculated Two-Tier Mart', [
                'tahun' => $tahun,
                'bulan' => $bulan,
                'jenis_transaksi' => $jenisTransaksi,
                'tipe_sumber' => $tipeSumber,
                'batches_processed' => count($matchingBatchIds),
                'inserted_rows' => $insertedCount,
            ]);

            return $insertedCount;
        });
    }

    /**
     * Hitung ulang atau bersihkan agregasi untuk satu batch tertentu.
     */
    public function recalculateForBatch(int $batchId): int
    {
        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        if (!$batch) {
            $this->brsAggregationService->removeBatchAggregation($batchId);
            return 0;
        }

        $isTrashed = DB::table('trash')
            ->where('batch_id', $batchId)
            ->whereNull('restored_at')
            ->exists();

        $status = strtoupper(trim((string) ($batch->status ?? '')));
        if ($isTrashed || !in_array($status, ['PRIVATE', 'RILIS'], true)) {
            $this->brsAggregationService->removeBatchAggregation($batchId);
            return 0;
        }

        $result = $this->brsAggregationService->aggregateBatch($batchId, $status);

        return (int) ($result['tier1_rows'] ?? 0);
    }

    /**
     * Backfill seluruh batch aktif (Distribusi & Neraca terpisah) tanpa menyentuh baris historis batch_id IS NULL.
     */
    public function backfillAll(): int
    {
        $distCount = $this->recalculatePeriod(null, null, null, 'distribusi');
        $neracaCount = $this->recalculatePeriod(null, null, null, 'neraca');

        return $distCount + $neracaCount;
    }
}
