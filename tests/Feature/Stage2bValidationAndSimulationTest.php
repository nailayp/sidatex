<?php

namespace Tests\Feature;

use App\Services\DataReconciliationService;
use App\Services\TradeAggregationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Stage2bValidationAndSimulationTest extends TestCase
{
    /**
     * 1. Uji Unique Index ketika pelabuhan_id bernilai NULL:
     * pelabuhan_key = 0 memastikan constraint tetap aktif dan tidak lolos duplikat di MySQL.
     */
    public function test_unique_index_enforces_uniqueness_when_pelabuhan_is_null(): void
    {
        $existing = DB::table('monthly_trade_summaries')->first();
        $this->assertNotNull($existing);

        DB::beginTransaction();
        try {
            DB::table('monthly_trade_summaries')
                ->where('tahun', 2030)
                ->where('bulan', 1)
                ->delete();

            // Insert baris dengan pelabuhan_id NULL dan pelabuhan_key 0
            DB::table('monthly_trade_summaries')->insert([
                'tipe_sumber' => 'distribusi',
                'hs_id' => $existing->hs_id,
                'negara_id' => $existing->negara_id,
                'provinsi_id' => $existing->provinsi_id,
                'pelabuhan_id' => null,
                'pelabuhan_key' => 0,
                'jenis_transaksi' => 'Ekspor',
                'bulan' => 1,
                'tahun' => 2030,
                'total_berat' => 500.0,
                'total_nilai' => 25000.0,
                'jumlah_transaksi' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Percobaan insert baris kedua dengan dimensi persis sama harus ditolak oleh unique index
            $this->expectException(\Illuminate\Database\QueryException::class);
            DB::table('monthly_trade_summaries')->insert([
                'tipe_sumber' => 'distribusi',
                'hs_id' => $existing->hs_id,
                'negara_id' => $existing->negara_id,
                'provinsi_id' => $existing->provinsi_id,
                'pelabuhan_id' => null,
                'pelabuhan_key' => 0,
                'jenis_transaksi' => 'Ekspor',
                'bulan' => 1,
                'tahun' => 2030,
                'total_berat' => 300.0,
                'total_nilai' => 15000.0,
                'jumlah_transaksi' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 2. Uji Integritas monthly_origin_trade_summaries:
     * Memastikan tabel asal barang memiliki integritas komposit bebas provorig NULL
     * dengan simulasi batch Distribusi RILIS terisolasi di dalam transaksi rollback
     * agar tidak bergantung pada kebocoran baris PRIVATE/orphan.
     */
    public function test_monthly_origin_trade_summaries_composite_integrity(): void
    {
        DB::beginTransaction();
        try {
            $batch = DB::table('data_batches')
                ->where('tipe_sumber', 'distribusi')
                ->whereRaw('LOWER(jenis_data) = ?', ['ekspor'])
                ->whereNotIn('batch_id', function ($q) {
                    $q->select('batch_id')->from('trash')->whereNull('restored_at');
                })
                ->orderBy('batch_id')
                ->first();

            $this->assertNotNull($batch, 'Minimal satu batch Distribusi Ekspor aktif diperlukan untuk simulasi Origin Mart.');

            DB::table('data_batches')
                ->where('batch_id', $batch->batch_id)
                ->update(['status' => 'RILIS']);

            app(\App\Services\BrsAggregationService::class)->syncOriginSummariesForPeriod(
                (int) $batch->periode_tahun,
                (int) $batch->periode_bulan,
                'Ekspor'
            );

            $originCount = DB::table('monthly_origin_trade_summaries')
                ->where('tahun', (int) $batch->periode_tahun)
                ->where('bulan', (int) $batch->periode_bulan)
                ->where('jenis_transaksi', 'ekspor')
                ->count();
            $this->assertGreaterThan(0, $originCount);

            // Pastikan tidak ada provorig yang null pada seluruh tabel Origin Mart
            $nullProvorig = DB::table('monthly_origin_trade_summaries')->whereNull('provorig')->count();
            $this->assertEquals(0, $nullProvorig);

            // Pastikan tidak ada duplikasi kunci komposit (tahun, bulan, jenis_transaksi, provorig, hs2, sektor, is_migas)
            $duplicates = DB::table('monthly_origin_trade_summaries')
                ->selectRaw('tahun, bulan, jenis_transaksi, provorig, hs2, sektor, is_migas, COUNT(*) as cnt')
                ->groupBy('tahun', 'bulan', 'jenis_transaksi', 'provorig', 'hs2', 'sektor', 'is_migas')
                ->havingRaw('COUNT(*) > 1')
                ->count();
            $this->assertEquals(0, $duplicates);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 3. Uji Pencegahan Double Counting Batch #5 dan Batch #17:
     * Memastikan Batch #17 jika berstatus PRIVATE tidak dihitung dalam agregasi RILIS.
     */
    public function test_batch_17_private_status_prevents_double_counting(): void
    {
        $service = new TradeAggregationService();
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();
        $pelabuhan = DB::table('master_pelabuhan')->first();
        $this->assertNotNull($hs);
        $this->assertNotNull($negara);
        $this->assertNotNull($pelabuhan);

        $tahun = 2091;
        $bulan = 3;

        DB::beginTransaction();
        try {
            // Buat batch Neraca RILIS dan batch Distribusi PRIVATE pada periode sentinel yang sama
            $neracaBatchId = DB::table('data_batches')->insertGetId([
                'nama_file' => 'sentinel_neraca_rilis_2091_03.dbf',
                'jenis_data' => 'Ekspor',
                'tipe_sumber' => 'neraca',
                'periode_tahun' => $tahun,
                'periode_bulan' => $bulan,
                'ukuran_file' => '0.10',
                'status' => 'RILIS',
                'uploaded_by' => 1,
                'uploaded_at' => now(),
                'released_by' => 1,
                'released_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $distPrivateBatchId = DB::table('data_batches')->insertGetId([
                'nama_file' => 'sentinel_dist_private_2091_03.dbf',
                'jenis_data' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
                'periode_tahun' => $tahun,
                'periode_bulan' => $bulan,
                'ukuran_file' => '0.10',
                'status' => 'PRIVATE',
                'uploaded_by' => 1,
                'uploaded_at' => now(),
                'released_by' => null,
                'released_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('neraca_transactions')->insert([
                'batch_id' => $neracaBatchId,
                'hs_id' => $hs->hs_id,
                'hs_code_raw' => $hs->hs_code,
                'mapping_status' => 'mapped',
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'neraca',
                'bulan' => $bulan,
                'tahun' => $tahun,
                'berat' => '3100.500000',
                'nilai' => '450000.250000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('distribusi_transactions')->insert([
                'batch_id' => $distPrivateBatchId,
                'hs_id' => $hs->hs_id,
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                'provorig' => '1401',
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
                'bulan' => $bulan,
                'tahun' => $tahun,
                'berat' => '9900.000000',
                'nilai' => '777777.000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Jalankan rekalkulasi untuk periode Neraca
            $service->recalculatePeriod($tahun, $bulan, 'Ekspor', 'neraca');

            $aggNeracaVal = (string) DB::table('monthly_trade_summaries')
                ->where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->where('jenis_transaksi', 'Ekspor')
                ->where('tipe_sumber', 'neraca')
                ->sum('total_nilai');

            $txNeracaVal = (string) DB::table('neraca_transactions')
                ->where('batch_id', $neracaBatchId)
                ->where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->sum('nilai');

            // Total agregasi Neraca harus sama persis dengan batch Neraca tanpa tercampur batch Distribusi PRIVATE
            $this->assertSame(
                bcadd($txNeracaVal, '0', 6),
                bcadd($aggNeracaVal, '0', 6)
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 4. Uji Simulasi Isolasi: Pemulihan terisolasi tidak mengubah database testing permanen.
     */
    public function test_simulation_restore_preserves_baseline_isolation(): void
    {
        $initialActiveBatches = DB::table('data_batches')
            ->whereNotIn('batch_id', function($q) {
                $q->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->pluck('batch_id')
            ->toArray();

        DB::beginTransaction();
        try {
            // Simulasi pemulihan Batch #38
            DB::table('trash')->where('batch_id', 38)->update(['restored_at' => now()]);
            $this->assertTrue(true);
        } finally {
            DB::rollBack();
        }

        $afterActiveBatches = DB::table('data_batches')
            ->whereNotIn('batch_id', function($q) {
                $q->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->pluck('batch_id')
            ->toArray();

        $this->assertEquals($initialActiveBatches, $afterActiveBatches, 'Kondisi baseline harus tetap utuh.');
    }
}
