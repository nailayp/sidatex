<?php

namespace App\Console\Commands;

use App\Services\BrsAggregationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillMonthlyTradeSummaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trade:backfill-aggregates
        {--tahun= : Tahun spesifik yang ingin dihitung}
        {--bulan= : Bulan spesifik yang ingin dihitung}
        {--jenis= : Jenis transaksi (Ekspor/Impor)}
        {--sumber=all : Sumber metodologi (distribusi, neraca, atau all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kalkulasi ulang Two-Tier Analytical Mart (monthly_trade_summaries & brs_indicator_summaries) untuk batch aktif ter-link tanpa menyentuh baris historis batch_id IS NULL';

    /**
     * Execute the console command.
     */
    public function handle(BrsAggregationService $brsAggregationService): int
    {
        $sumber = strtolower(trim((string) ($this->option('sumber') ?: 'all')));
        if (!in_array($sumber, ['all', 'distribusi', 'neraca'], true)) {
            $this->error("Opsi --sumber tidak valid: '{$sumber}'. Gunakan 'distribusi', 'neraca', atau 'all'.");
            return Command::FAILURE;
        }

        $tahun = $this->option('tahun') ? (int) $this->option('tahun') : null;
        $bulan = $this->option('bulan') ? (int) $this->option('bulan') : null;
        $jenis = $this->option('jenis') ? ucfirst(strtolower(trim((string) $this->option('jenis')))) : null;

        $this->info("Memulai proses kalkulasi ulang Two-Tier Analytical Mart (sumber: {$sumber})...");

        $batchesQuery = DB::table('data_batches')
            ->whereIn('status', ['PRIVATE', 'RILIS'])
            ->whereNotIn('batch_id', function ($sub) {
                $sub->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->orderBy('periode_tahun')
            ->orderBy('periode_bulan')
            ->orderBy('batch_id');

        if ($sumber !== 'all') {
            $batchesQuery->where('tipe_sumber', $sumber);
        }
        if ($tahun !== null) {
            $batchesQuery->where('periode_tahun', $tahun);
        }
        if ($bulan !== null) {
            $batchesQuery->where('periode_bulan', $bulan);
        }
        if ($jenis !== null) {
            $batchesQuery->whereRaw('LOWER(jenis_data) = ?', [strtolower($jenis)]);
        }

        $batches = $batchesQuery->get();
        $totalTier1 = 0;
        $totalTier2 = 0;

        foreach ($batches as $batch) {
            $res = $brsAggregationService->aggregateBatch((int) $batch->batch_id, (string) $batch->status);
            $totalTier1 += (int) ($res['tier1_rows'] ?? 0);
            $totalTier2 += (int) ($res['tier2_rows'] ?? 0);
        }

        $this->info("Selesai mengagregasi {$batches->count()} batch aktif: {$totalTier1} baris Tier-1 (monthly_trade_summaries) dan {$totalTier2} baris Tier-2 (brs_indicator_summaries).");

        return Command::SUCCESS;
    }
}
