<?php

namespace Tests\Feature;

use App\Models\MonthlyTradeSummary;
use App\Models\User;
use App\Services\TradeAggregationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComprehensiveAuditAndSecurityTest extends TestCase
{

    /**
     * 1. Audit Composite Unique Key dengan pelabuhan_id = NULL
     * Memastikan database menolak baris ganda meskipun pelabuhan_id bernilai NULL
     * berkat adanya pelabuhan_key = COALESCE(pelabuhan_id, 0).
     */
    public function test_unique_key_blocks_duplicate_even_with_null_pelabuhan(): void
    {
        $existing = DB::table('monthly_trade_summaries')->first();
        $this->assertNotNull($existing);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('monthly_trade_summaries')->insert([
            'hs_id' => $existing->hs_id,
            'negara_id' => $existing->negara_id,
            'provinsi_id' => $existing->provinsi_id,
            'pelabuhan_id' => null,
            'pelabuhan_key' => 0,
            'jenis_transaksi' => $existing->jenis_transaksi,
            'bulan' => $existing->bulan,
            'tahun' => $existing->tahun,
            'total_berat' => 100,
            'total_nilai' => 100,
            'jumlah_transaksi' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 2. Audit Multi-Batch & Trash/Restore Agregasi
     * Memastikan bahwa batch yang ada di Trash diabaikan oleh kalkulasi agregasi,
     * dan penghitungan ulang tidak menduplikasi nilai.
     */
    public function test_trash_batches_are_excluded_from_aggregations(): void
    {
        $service = new TradeAggregationService();
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();
        $pelabuhan = DB::table('master_pelabuhan')->first();
        $this->assertNotNull($hs);
        $this->assertNotNull($negara);
        $this->assertNotNull($pelabuhan);

        $tahun = 2091;
        $bulan = 2;
        $jenisTransaksi = 'Ekspor';
        $tipeSumber = 'distribusi';

        DB::beginTransaction();
        try {
            $activeBatchId = DB::table('data_batches')->insertGetId([
                'nama_file' => 'sentinel_active_2091_02.dbf',
                'jenis_data' => $jenisTransaksi,
                'tipe_sumber' => $tipeSumber,
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

            $trashedBatchId = DB::table('data_batches')->insertGetId([
                'nama_file' => 'sentinel_trashed_2091_02.dbf',
                'jenis_data' => $jenisTransaksi,
                'tipe_sumber' => $tipeSumber,
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
                    'batch_id' => $activeBatchId,
                    'hs_id' => $hs->hs_id,
                    'negara_id' => $negara->negara_id,
                    'provinsi_id' => 4,
                    'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                    'provorig' => '1401',
                    'jenis_transaksi' => $jenisTransaksi,
                    'tipe_sumber' => $tipeSumber,
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'berat' => '2500.000000',
                    'nilai' => '150000.123456',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'batch_id' => $trashedBatchId,
                    'hs_id' => $hs->hs_id,
                    'negara_id' => $negara->negara_id,
                    'provinsi_id' => 4,
                    'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                    'provorig' => '1401',
                    'jenis_transaksi' => $jenisTransaksi,
                    'tipe_sumber' => $tipeSumber,
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'berat' => '9999.000000',
                    'nilai' => '888888.654321',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('trash')->insert([
                'batch_id' => $trashedBatchId,
                'data_snapshot' => json_encode(['batch_id' => $trashedBatchId]),
                'deleted_by' => 1,
                'deleted_at' => now(),
                'restored_by' => null,
                'restored_at' => null,
            ]);

            // Jalankan rekalkulasi periode secara terisolasi
            $service->recalculatePeriod($tahun, $bulan, $jenisTransaksi, $tipeSumber);

            // Nilai transaksi aktif periode tersebut harus sama persis dengan total agregasi periode terkait
            $expectedTotalNilai = (string) DB::table('distribusi_transactions')
                ->where('batch_id', $activeBatchId)
                ->where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->where('jenis_transaksi', $jenisTransaksi)
                ->sum('nilai');

            $actualTotalNilai = (string) DB::table('monthly_trade_summaries')
                ->where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->where('jenis_transaksi', $jenisTransaksi)
                ->where('tipe_sumber', $tipeSumber)
                ->sum('total_nilai');

            $this->assertSame(
                bcadd($expectedTotalNilai, '0', 6),
                bcadd($actualTotalNilai, '0', 6),
                'Agregasi periode hanya boleh menghitung transaksi dari batch aktif yang tidak berada di trash.'
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 3. Audit Hak Akses Role terhadap Log Aktivitas
     */
    public function test_super_admin_can_access_activity_logs(): void
    {
        $admin = User::where('role_id', 1)->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get(route('activity-logs'));
        $response->assertStatus(200);
        $response->assertSee('Log Aktivitas Sistem');
    }

    public function test_pegawai_can_access_activity_logs(): void
    {
        $pegawai = User::where('role_id', 2)->first();
        $this->assertNotNull($pegawai);

        $response = $this->actingAs($pegawai)->get(route('activity-logs'));
        $response->assertStatus(200);
        $response->assertSee('Log Aktivitas Sistem');
    }

    public function test_viewer_eksekutif_is_blocked_from_activity_logs(): void
    {
        $viewer = User::where('role_id', 3)->first();
        $this->assertNotNull($viewer);

        $response = $this->actingAs($viewer)->get(route('activity-logs'));
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    /**
     * 4. Audit Konsistensi Nilai Total & Netto Dashboard (termasuk isolasi PRIVATE vs RILIS)
     */
    public function test_dashboard_metrics_consistent_with_raw_data(): void
    {
        $statService = new \App\Services\StatisticsCalculationService();

        // Data historis 2024 berstatus PRIVATE (Keputusan A-1): harus terlihat untuk Role 1/3 (['RILIS', 'PRIVATE'])
        $metricsExecutive = $statService->getOfficialDashboardMetrics(2024, 'Ekspor', 'distribusi', ['RILIS', 'PRIVATE']);

        $expectedFob2024 = DB::table('monthly_trade_summaries')
            ->where('tahun', 2024)
            ->where('jenis_transaksi', 'Ekspor')
            ->where('tipe_sumber', 'distribusi')
            ->whereIn('status_visibilitas', ['RILIS', 'PRIVATE'])
            ->sum('total_nilai');

        $this->assertEqualsWithDelta($expectedFob2024, $metricsExecutive['total_fob'], 0.05);

        // Role 2 (['RILIS'] saja) tidak boleh membaca data historis PRIVATE 2024
        $metricsPublic = $statService->getOfficialDashboardMetrics(2024, 'Ekspor', 'distribusi', ['RILIS']);
        $this->assertFalse($metricsPublic['has_data']);
        $this->assertEquals(0.0, $metricsPublic['total_fob']);
        $this->assertNull($metricsPublic['mtom']);
        $this->assertNull($metricsPublic['qtoq']);
        $this->assertNull($metricsPublic['yony']);
        $this->assertNull($metricsPublic['ctoc']);
    }
}
