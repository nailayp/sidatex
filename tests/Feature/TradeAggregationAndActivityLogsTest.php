<?php

namespace Tests\Feature;

use App\Models\MonthlyTradeSummary;
use App\Models\User;
use App\Services\TradeAggregationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TradeAggregationAndActivityLogsTest extends TestCase
{

    public function test_aggregation_service_recalculates_idempotently(): void
    {
        $service = new TradeAggregationService();
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();
        $pelabuhan = DB::table('master_pelabuhan')->first();
        $this->assertNotNull($hs);
        $this->assertNotNull($negara);
        $this->assertNotNull($pelabuhan);

        $tahun = 2091;
        $bulan = 1;
        $jenis = 'Ekspor';

        DB::beginTransaction();
        try {
            $batchId = DB::table('data_batches')->insertGetId([
                'nama_file' => 'sentinel_idempotency_2091_01.dbf',
                'jenis_data' => $jenis,
                'tipe_sumber' => 'distribusi',
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

            DB::table('distribusi_transactions')->insert([
                [
                    'batch_id' => $batchId,
                    'hs_id' => $hs->hs_id,
                    'negara_id' => $negara->negara_id,
                    'provinsi_id' => 4,
                    'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                    'provorig' => '1401',
                    'jenis_transaksi' => $jenis,
                    'tipe_sumber' => 'distribusi',
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'berat' => '1234.567891',
                    'nilai' => '98765.432109',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'batch_id' => $batchId,
                    'hs_id' => $hs->hs_id,
                    'negara_id' => $negara->negara_id,
                    'provinsi_id' => 4,
                    'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                    'provorig' => '1401',
                    'jenis_transaksi' => $jenis,
                    'tipe_sumber' => 'distribusi',
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'berat' => '500.111109',
                    'nilai' => '1000.567891',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            // 1. Jalankan kalkulasi periode pertama
            $count1 = $service->recalculatePeriod($tahun, $bulan, $jenis, 'distribusi');
            $this->assertGreaterThan(0, $count1);

            $totalNilaiAgg1 = (string) DB::table('monthly_trade_summaries')
                ->where('tipe_sumber', 'distribusi')
                ->where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->where('jenis_transaksi', $jenis)
                ->sum('total_nilai');

            // 2. Jalankan kalkulasi ulang untuk periode yang sama (pengujian idempotensi)
            $count2 = $service->recalculatePeriod($tahun, $bulan, $jenis, 'distribusi');
            $this->assertEquals($count1, $count2, 'Jumlah baris agregasi harus konsisten saat dihitung ulang');

            $totalNilaiAgg2 = (string) DB::table('monthly_trade_summaries')
                ->where('tipe_sumber', 'distribusi')
                ->where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->where('jenis_transaksi', $jenis)
                ->sum('total_nilai');

            $this->assertSame(
                bcadd($totalNilaiAgg1, '0', 6),
                bcadd($totalNilaiAgg2, '0', 6),
                'Nilai agregasi tidak boleh berlipat ganda saat dihitung ulang'
            );

            // 3. Bandingkan dengan perhitungan langsung dari tabel distribusi_transactions yang aktif
            $totalNilaiRaw = (string) DB::table('distribusi_transactions')
                ->where('batch_id', $batchId)
                ->where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->where('jenis_transaksi', $jenis)
                ->sum('nilai');

            $this->assertSame(
                bcadd($totalNilaiRaw, '0', 6),
                bcadd($totalNilaiAgg2, '0', 6),
                'Nilai agregasi harus persis sama hingga 6 desimal dengan nilai raw distribusi_transactions'
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_activity_logs_page_accessible_by_authenticated_user(): void
    {
        $user = User::first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user)->get(route('activity-logs'));
        $response->assertStatus(200);
        $response->assertSee('Log Aktivitas Sistem');
    }

    public function test_activity_logs_filter_by_modul_and_search(): void
    {
        $user = User::first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user)->get(route('activity-logs', ['q' => 'Batch']));
        $response->assertStatus(200);
        $response->assertSee('Log Aktivitas Sistem');
    }
}
