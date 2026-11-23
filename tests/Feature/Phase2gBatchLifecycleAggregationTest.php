<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BrsAggregationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase2gBatchLifecycleAggregationTest extends TestCase
{
    private array $createdBatchIds = [];

    protected function tearDown(): void
    {
        if (!empty($this->createdBatchIds)) {
            DB::table('brs_indicator_summaries')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('monthly_trade_summaries')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('monthly_origin_trade_summaries')->whereIn('tahun', [2094, 2095])->delete();
            DB::table('trash')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('distribusi_transactions')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('neraca_transactions')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('data_batches')->whereIn('batch_id', $this->createdBatchIds)->delete();
            $this->createdBatchIds = [];
        }

        parent::tearDown();
    }

    public function test_toggle_release_syncs_batch_tier1_tier2_and_origin_mart_atomically(): void
    {
        $superAdmin = User::where('role_id', 1)->first();
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();
        $pelabuhan = DB::table('master_pelabuhan')->first();

        $this->assertNotNull($superAdmin);
        $this->assertNotNull($hs);
        $this->assertNotNull($negara);
        $this->assertNotNull($pelabuhan);

        $batchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_toggle_dist_2094_01.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'periode_tahun' => 2094,
            'periode_bulan' => 1,
            'ukuran_file' => '0.15',
            'status' => 'PRIVATE',
            'uploaded_by' => $superAdmin->id,
            'uploaded_at' => now(),
            'released_by' => null,
            'released_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $batchId;

        DB::table('distribusi_transactions')->insert([
            'batch_id' => $batchId,
            'hs_id' => $hs->hs_id,
            'negara_id' => $negara->negara_id,
            'provinsi_id' => 4,
            'pelabuhan_id' => $pelabuhan->pelabuhan_id,
            'provorig' => '1401',
            'jenis_transaksi' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'bulan' => 1,
            'tahun' => 2094,
            'berat' => '500.250000',
            'nilai' => '25000.750000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(BrsAggregationService::class)->aggregateBatch($batchId, 'PRIVATE');

        // Sebelum rilis: Tier-1 & Tier-2 PRIVATE, Origin Mart 0 baris (khusus RILIS)
        $this->assertSame('PRIVATE', DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->value('status_visibilitas'));
        $this->assertSame('PRIVATE', DB::table('brs_indicator_summaries')->where('batch_id', $batchId)->value('status_visibilitas'));
        $this->assertSame(0, DB::table('monthly_origin_trade_summaries')->where('tahun', 2094)->where('bulan', 1)->count());

        // Toggle PRIVATE -> RILIS
        $resRelease = $this->actingAs($superAdmin)->postJson("/manajemen-data/{$batchId}/toggle-release");
        $resRelease->assertStatus(200);
        $this->assertSame('RILIS', $resRelease->json('new_status'));

        $this->assertSame('RILIS', DB::table('data_batches')->where('batch_id', $batchId)->value('status'));
        $this->assertSame(
            0,
            DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->where('status_visibilitas', '!=', 'RILIS')->count()
        );
        $this->assertSame(
            0,
            DB::table('brs_indicator_summaries')->where('batch_id', $batchId)->where('status_visibilitas', '!=', 'RILIS')->count()
        );
        $this->assertGreaterThan(0, DB::table('monthly_origin_trade_summaries')->where('tahun', 2094)->where('bulan', 1)->count());

        // Toggle RILIS -> PRIVATE
        $resRetract = $this->actingAs($superAdmin)->postJson("/manajemen-data/{$batchId}/toggle-release");
        $resRetract->assertStatus(200);
        $this->assertSame('PRIVATE', $resRetract->json('new_status'));
        $this->assertSame('PRIVATE', DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->value('status_visibilitas'));
        $this->assertSame('PRIVATE', DB::table('brs_indicator_summaries')->where('batch_id', $batchId)->value('status_visibilitas'));
        $this->assertSame(0, DB::table('monthly_origin_trade_summaries')->where('tahun', 2094)->where('bulan', 1)->count());

        // Pindahkan ke Trash lalu pastikan toggle-release ditolak (422)
        $this->actingAs($superAdmin)->postJson(route('manajemen-data.destroy', $batchId))->assertStatus(200);
        $this->actingAs($superAdmin)->postJson(route('manajemen-data.toggle-release', $batchId))->assertStatus(422);
    }

    public function test_destroy_and_restore_sync_distribusi_and_neraca_and_enforce_collision_and_isolation(): void
    {
        $superAdmin = User::where('role_id', 1)->first();
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();
        $pelabuhan = DB::table('master_pelabuhan')->first();

        $this->assertNotNull($superAdmin);
        $this->assertNotNull($hs);
        $this->assertNotNull($negara);
        $this->assertNotNull($pelabuhan);

        // 1. Batch Distribusi RILIS 2094-02
        $distBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_dist_iso_2094_02.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'periode_tahun' => 2094,
            'periode_bulan' => 2,
            'ukuran_file' => '0.10',
            'status' => 'RILIS',
            'uploaded_by' => $superAdmin->id,
            'uploaded_at' => now(),
            'released_by' => $superAdmin->id,
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $distBatchId;

        DB::table('distribusi_transactions')->insert([
            'batch_id' => $distBatchId,
            'hs_id' => $hs->hs_id,
            'negara_id' => $negara->negara_id,
            'provinsi_id' => 4,
            'pelabuhan_id' => $pelabuhan->pelabuhan_id,
            'provorig' => '1402',
            'jenis_transaksi' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'bulan' => 2,
            'tahun' => 2094,
            'berat' => '900.000000',
            'nilai' => '90000.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Batch Neraca RILIS 2094-02
        $neracaBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_neraca_iso_2094_02.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'neraca',
            'periode_tahun' => 2094,
            'periode_bulan' => 2,
            'ukuran_file' => '0.10',
            'status' => 'RILIS',
            'uploaded_by' => $superAdmin->id,
            'uploaded_at' => now(),
            'released_by' => $superAdmin->id,
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $neracaBatchId;

        DB::table('neraca_transactions')->insert([
            [
                'batch_id' => $neracaBatchId,
                'hs_id' => $hs->hs_id,
                'hs_code_raw' => $hs->hs_code,
                'mapping_status' => 'mapped',
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => null,
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'neraca',
                'bulan' => 2,
                'tahun' => 2094,
                'berat' => '400.123456',
                'nilai' => '45000.654321',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'batch_id' => $neracaBatchId,
                'hs_id' => $hs->hs_id,
                'hs_code_raw' => $hs->hs_code,
                'mapping_status' => 'mapped',
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => null,
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'neraca',
                'bulan' => 2,
                'tahun' => 2094,
                'berat' => '100.000000',
                'nilai' => '5000.000000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $aggService = app(BrsAggregationService::class);
        $aggService->aggregateBatch($distBatchId, 'RILIS');
        $aggService->aggregateBatch($neracaBatchId, 'RILIS');

        $originCountBefore = DB::table('monthly_origin_trade_summaries')->where('tahun', 2094)->where('bulan', 2)->count();
        $this->assertGreaterThan(0, $originCountBefore);

        // Destroy Neraca -> mart Neraca terhapus, snapshot akurat = 2, Origin Mart Distribusi UTUH
        $this->actingAs($superAdmin)->postJson(route('manajemen-data.destroy', $neracaBatchId))->assertStatus(200);

        $trashRow = DB::table('trash')->where('batch_id', $neracaBatchId)->whereNull('restored_at')->first();
        $this->assertNotNull($trashRow);
        $snapshot = json_decode((string) $trashRow->data_snapshot, true);
        $this->assertSame(2, $snapshot['total_transaksi']);
        $this->assertSame('neraca', $snapshot['tipe_sumber']);

        $this->assertSame(0, DB::table('monthly_trade_summaries')->where('batch_id', $neracaBatchId)->count());
        $this->assertSame(0, DB::table('brs_indicator_summaries')->where('batch_id', $neracaBatchId)->count());
        $this->assertSame($originCountBefore, DB::table('monthly_origin_trade_summaries')->where('tahun', 2094)->where('bulan', 2)->count());
        $this->assertGreaterThan(0, DB::table('monthly_trade_summaries')->where('batch_id', $distBatchId)->count());

        // Double-trash harus mengembalikan 409 Conflict
        $this->actingAs($superAdmin)->postJson(route('manajemen-data.destroy', $neracaBatchId))->assertStatus(409);

        // Buat batch konflik aktif untuk periode Neraca Ekspor 2094-02 -> Restore harus ditolak 409
        $conflictBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_conflict_neraca_2094_02.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'neraca',
            'periode_tahun' => 2094,
            'periode_bulan' => 2,
            'ukuran_file' => '0.10',
            'status' => 'PRIVATE',
            'uploaded_by' => $superAdmin->id,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $conflictBatchId;

        $this->actingAs($superAdmin)->postJson(route('manajemen-data.restore', $neracaBatchId))->assertStatus(409);

        // Hapus batch konflik, lalu pulihkan Neraca -> Tier-1 & Tier-2 Neraca terbangun kembali dengan presisi 6 desimal
        DB::table('data_batches')->where('batch_id', $conflictBatchId)->delete();

        $this->actingAs($superAdmin)->postJson(route('manajemen-data.restore', $neracaBatchId))->assertStatus(200);

        $restoredTier1Sum = (string) DB::table('monthly_trade_summaries')->where('batch_id', $neracaBatchId)->sum('total_nilai');
        $restoredTier2Total = (string) DB::table('brs_indicator_summaries')
            ->where('batch_id', $neracaBatchId)
            ->where('kategori_dimensi', 'TOTAL')
            ->value('nilai_usd');

        $this->assertSame(0, bccomp('50000.654321', $restoredTier1Sum, 6));
        $this->assertSame(0, bccomp('50000.654321', $restoredTier2Total, 6));
        $this->assertSame($originCountBefore, DB::table('monthly_origin_trade_summaries')->where('tahun', 2094)->where('bulan', 2)->count());
    }

    public function test_update_jenis_and_force_delete_sync_neraca_transactions_and_purge_all_tiers(): void
    {
        $superAdmin = User::where('role_id', 1)->first();
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();

        $this->assertNotNull($superAdmin);
        $this->assertNotNull($hs);
        $this->assertNotNull($negara);

        $neracaBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_update_neraca_2094_03.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'neraca',
            'periode_tahun' => 2094,
            'periode_bulan' => 3,
            'ukuran_file' => '0.10',
            'status' => 'PRIVATE',
            'uploaded_by' => $superAdmin->id,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $neracaBatchId;

        DB::table('neraca_transactions')->insert([
            'batch_id' => $neracaBatchId,
            'hs_id' => $hs->hs_id,
            'hs_code_raw' => $hs->hs_code,
            'mapping_status' => 'mapped',
            'negara_id' => $negara->negara_id,
            'provinsi_id' => 4,
            'pelabuhan_id' => null,
            'jenis_transaksi' => 'Ekspor',
            'tipe_sumber' => 'neraca',
            'bulan' => 3,
            'tahun' => 2094,
            'berat' => '321.000000',
            'nilai' => '12345.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(BrsAggregationService::class)->aggregateBatch($neracaBatchId, 'PRIVATE');
        $this->assertSame('Ekspor', DB::table('monthly_trade_summaries')->where('batch_id', $neracaBatchId)->value('jenis_transaksi'));

        // Update jenis_data Ekspor -> Impor pada batch Neraca
        $resUpdate = $this->actingAs($superAdmin)->postJson(route('manajemen-data.update', $neracaBatchId), [
            'nama_file' => 'sentinel_update_neraca_2094_03_edited.dbf',
            'jenis_data' => 'Impor',
        ]);
        $resUpdate->assertStatus(200);

        $this->assertSame('Impor', DB::table('neraca_transactions')->where('batch_id', $neracaBatchId)->value('jenis_transaksi'));
        $this->assertSame('Impor', DB::table('monthly_trade_summaries')->where('batch_id', $neracaBatchId)->value('jenis_transaksi'));
        $this->assertSame('Impor', DB::table('brs_indicator_summaries')->where('batch_id', $neracaBatchId)->value('jenis_transaksi'));
        $this->assertSame('PRIVATE', DB::table('data_batches')->where('batch_id', $neracaBatchId)->value('status'));

        // Trash -> ForceDelete: bersihkan seluruh tier
        $this->actingAs($superAdmin)->postJson(route('manajemen-data.destroy', $neracaBatchId))->assertStatus(200);
        $this->actingAs($superAdmin)->postJson(route('manajemen-data.force-delete', $neracaBatchId))->assertStatus(200);

        $this->assertFalse(DB::table('data_batches')->where('batch_id', $neracaBatchId)->exists());
        $this->assertSame(0, DB::table('neraca_transactions')->where('batch_id', $neracaBatchId)->count());
        $this->assertSame(0, DB::table('monthly_trade_summaries')->where('batch_id', $neracaBatchId)->count());
        $this->assertSame(0, DB::table('brs_indicator_summaries')->where('batch_id', $neracaBatchId)->count());
        $this->assertSame(0, DB::table('trash')->where('batch_id', $neracaBatchId)->count());
    }

    public function test_multimonth_excel_batch_with_null_period_aggregates_all_months_and_preserves_historical_null_batch_rows(): void
    {
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();
        $pelabuhan = DB::table('master_pelabuhan')->first();

        $this->assertNotNull($hs);
        $this->assertNotNull($negara);
        $this->assertNotNull($pelabuhan);

        $nullBatchCountBefore = DB::table('monthly_trade_summaries')->whereNull('batch_id')->count();

        $multiBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_multimonth_excel_2095.xlsx',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'periode_tahun' => null,
            'periode_bulan' => null,
            'ukuran_file' => '0.20',
            'status' => 'PRIVATE',
            'uploaded_by' => 1,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $multiBatchId;

        DB::table('distribusi_transactions')->insert([
            [
                'batch_id' => $multiBatchId,
                'hs_id' => $hs->hs_id,
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                'provorig' => '1401',
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
                'bulan' => 1,
                'tahun' => 2095,
                'berat' => '111.000001',
                'nilai' => '1000.111111',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'batch_id' => $multiBatchId,
                'hs_id' => $hs->hs_id,
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                'provorig' => '1401',
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
                'bulan' => 2,
                'tahun' => 2095,
                'berat' => '222.000002',
                'nilai' => '2000.222222',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $aggService = app(BrsAggregationService::class);
        $res = $aggService->aggregateBatch($multiBatchId, 'PRIVATE');

        $this->assertSame('3000.333333', $res['total_nilai_usd']);
        $this->assertSame(
            '1000.111111',
            bcadd((string) DB::table('monthly_trade_summaries')->where('batch_id', $multiBatchId)->where('tahun', 2095)->where('bulan', 1)->sum('total_nilai'), '0', 6)
        );
        $this->assertSame(
            '2000.222222',
            bcadd((string) DB::table('monthly_trade_summaries')->where('batch_id', $multiBatchId)->where('tahun', 2095)->where('bulan', 2)->sum('total_nilai'), '0', 6)
        );
        $this->assertSame(0, DB::table('monthly_trade_summaries')->where('batch_id', $multiBatchId)->where('tahun', 0)->count());

        $aggService->removeBatchAggregation($multiBatchId);
        $this->assertSame(0, DB::table('monthly_trade_summaries')->where('batch_id', $multiBatchId)->count());
        $this->assertSame(0, DB::table('brs_indicator_summaries')->where('batch_id', $multiBatchId)->count());

        // Pastikan 100% baris historis batch_id IS NULL tetap utuh
        $nullBatchCountAfter = DB::table('monthly_trade_summaries')->whereNull('batch_id')->count();
        $this->assertSame($nullBatchCountBefore, $nullBatchCountAfter);
    }

    public function test_update_jenis_on_rilis_distribusi_removes_old_origin_summaries_and_inserts_new_ones(): void
    {
        $superAdmin = User::where('role_id', 1)->first();
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();
        $pelabuhan = DB::table('master_pelabuhan')->first();

        $this->assertNotNull($superAdmin);
        $this->assertNotNull($hs);
        $this->assertNotNull($negara);
        $this->assertNotNull($pelabuhan);

        $distBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_update_origin_2094_04.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'periode_tahun' => 2094,
            'periode_bulan' => 4,
            'ukuran_file' => '0.10',
            'status' => 'RILIS',
            'uploaded_by' => $superAdmin->id,
            'uploaded_at' => now(),
            'released_by' => $superAdmin->id,
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $distBatchId;

        DB::table('distribusi_transactions')->insert([
            'batch_id' => $distBatchId,
            'hs_id' => $hs->hs_id,
            'negara_id' => $negara->negara_id,
            'provinsi_id' => 4,
            'pelabuhan_id' => $pelabuhan->pelabuhan_id,
            'provorig' => '1401',
            'jenis_transaksi' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'bulan' => 4,
            'tahun' => 2094,
            'berat' => '750.123456',
            'nilai' => '33333.654321',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(BrsAggregationService::class)->aggregateBatch($distBatchId, 'RILIS');

        $this->assertGreaterThan(
            0,
            DB::table('monthly_origin_trade_summaries')->where('tahun', 2094)->where('bulan', 4)->where('jenis_transaksi', 'ekspor')->count()
        );
        $this->assertSame(
            0,
            DB::table('monthly_origin_trade_summaries')->where('tahun', 2094)->where('bulan', 4)->where('jenis_transaksi', 'impor')->count()
        );

        // Ubah jenis_data Ekspor -> Impor pada batch RILIS Distribusi
        $this->actingAs($superAdmin)->postJson(route('manajemen-data.update', $distBatchId), [
            'nama_file' => 'sentinel_update_origin_2094_04_impor.dbf',
            'jenis_data' => 'Impor',
        ])->assertStatus(200);

        // Origin summary 'ekspor' lama harus 0 (tidak boleh tersisa), dan 'impor' baru terisi presisi 6 desimal
        $this->assertSame(
            0,
            DB::table('monthly_origin_trade_summaries')->where('tahun', 2094)->where('bulan', 4)->where('jenis_transaksi', 'ekspor')->count(),
            'Baris monthly_origin_trade_summaries jenis lama (ekspor) wajib bersih tuntas saat update jenis_data.'
        );
        $imporOriginSum = (string) DB::table('monthly_origin_trade_summaries')
            ->where('tahun', 2094)
            ->where('bulan', 4)
            ->where('jenis_transaksi', 'impor')
            ->sum('total_nilai');
        $this->assertSame(0, bccomp('33333.654321', $imporOriginSum, 6));
    }

    public function test_update_jenis_returns_409_when_target_period_has_active_batch_collision(): void
    {
        $superAdmin = User::where('role_id', 1)->first();
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();
        $pelabuhan = DB::table('master_pelabuhan')->first();

        $this->assertNotNull($superAdmin);
        $this->assertNotNull($hs);
        $this->assertNotNull($negara);
        $this->assertNotNull($pelabuhan);

        $eksporBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_col_ekspor_2094_05.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'periode_tahun' => 2094,
            'periode_bulan' => 5,
            'ukuran_file' => '0.10',
            'status' => 'RILIS',
            'uploaded_by' => $superAdmin->id,
            'uploaded_at' => now(),
            'released_by' => $superAdmin->id,
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $eksporBatchId;

        $imporBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_col_impor_2094_05.dbf',
            'jenis_data' => 'Impor',
            'tipe_sumber' => 'distribusi',
            'periode_tahun' => 2094,
            'periode_bulan' => 5,
            'ukuran_file' => '0.10',
            'status' => 'RILIS',
            'uploaded_by' => $superAdmin->id,
            'uploaded_at' => now(),
            'released_by' => $superAdmin->id,
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $imporBatchId;

        DB::table('distribusi_transactions')->insert([
            [
                'batch_id' => $eksporBatchId,
                'hs_id' => $hs->hs_id,
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                'provorig' => '1401',
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
                'bulan' => 5,
                'tahun' => 2094,
                'berat' => '100.000000',
                'nilai' => '10000.000000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'batch_id' => $imporBatchId,
                'hs_id' => $hs->hs_id,
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                'provorig' => '1401',
                'jenis_transaksi' => 'Impor',
                'tipe_sumber' => 'distribusi',
                'bulan' => 5,
                'tahun' => 2094,
                'berat' => '200.000000',
                'nilai' => '20000.000000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $aggService = app(BrsAggregationService::class);
        $aggService->aggregateBatch($eksporBatchId, 'RILIS');
        $aggService->aggregateBatch($imporBatchId, 'RILIS');

        // Mencoba mengubah Ekspor 2094-05 menjadi Impor saat Impor 2094-05 aktif wajib ditolak 409
        $this->actingAs($superAdmin)->postJson(route('manajemen-data.update', $eksporBatchId), [
            'nama_file' => 'sentinel_col_ekspor_2094_05.dbf',
            'jenis_data' => 'Impor',
        ])->assertStatus(409);

        // Pastikan mart milik batch Impor 2094-05 tetap utuh 100%
        $this->assertSame(
            '20000.000000',
            bcadd((string) DB::table('monthly_trade_summaries')->where('batch_id', $imporBatchId)->sum('total_nilai'), '0', 6)
        );
        $this->assertSame('Ekspor', DB::table('data_batches')->where('batch_id', $eksporBatchId)->value('jenis_data'));
    }
}
