<?php

namespace Tests\Feature;

use App\Services\DataReconciliationService;
use App\Services\TradeAggregationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataReconciliationAndIndexTest extends TestCase
{
    /**
     * 1. Buktikan Unique Index 8-dimensi memungkinkan Neraca dan Distribusi memiliki dimensi sama tanpa bertabrakan.
     */
    public function test_unique_index_8_dimensions_permits_coexistence_of_neraca_and_distribusi(): void
    {
        $existing = DB::table('monthly_trade_summaries')->first();
        $this->assertNotNull($existing);

        DB::beginTransaction();
        try {
            // Hapus record uji coba jika sudah ada
            DB::table('monthly_trade_summaries')
                ->where('tahun', 2029)
                ->where('bulan', 12)
                ->delete();

            // Insert data Distribusi
            DB::table('monthly_trade_summaries')->insert([
                'tipe_sumber' => 'distribusi',
                'hs_id' => $existing->hs_id,
                'negara_id' => $existing->negara_id,
                'provinsi_id' => $existing->provinsi_id,
                'pelabuhan_id' => $existing->pelabuhan_id,
                'pelabuhan_key' => $existing->pelabuhan_key,
                'jenis_transaksi' => 'Ekspor',
                'bulan' => 12,
                'tahun' => 2029,
                'total_berat' => 1000.000,
                'total_nilai' => 50000.00,
                'jumlah_transaksi' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert data Neraca dengan 7 dimensi yang persis sama namun tipe_sumber berbeda
            DB::table('monthly_trade_summaries')->insert([
                'tipe_sumber' => 'neraca',
                'hs_id' => $existing->hs_id,
                'negara_id' => $existing->negara_id,
                'provinsi_id' => $existing->provinsi_id,
                'pelabuhan_id' => $existing->pelabuhan_id,
                'pelabuhan_key' => $existing->pelabuhan_key,
                'jenis_transaksi' => 'Ekspor',
                'bulan' => 12,
                'tahun' => 2029,
                'total_berat' => 1200.000,
                'total_nilai' => 65000.00,
                'jumlah_transaksi' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Berhasil insert kedua baris tanpa duplicate entry error
            $count = DB::table('monthly_trade_summaries')
                ->where('tahun', 2029)
                ->where('bulan', 12)
                ->count();

            $this->assertEquals(2, $count, 'Kedua sumber data harus dapat tersimpan berdampingan berkat indeks 8 dimensi.');

            // Namun jika mencoba memasukkan tipe_sumber yang sama persis (duplikat 8 dimensi), harus ditolak DB
            $this->expectException(\Illuminate\Database\QueryException::class);
            DB::table('monthly_trade_summaries')->insert([
                'tipe_sumber' => 'distribusi',
                'hs_id' => $existing->hs_id,
                'negara_id' => $existing->negara_id,
                'provinsi_id' => $existing->provinsi_id,
                'pelabuhan_id' => $existing->pelabuhan_id,
                'pelabuhan_key' => $existing->pelabuhan_key,
                'jenis_transaksi' => 'Ekspor',
                'bulan' => 12,
                'tahun' => 2029,
                'total_berat' => 1000.000,
                'total_nilai' => 50000.00,
                'jumlah_transaksi' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        } finally {
            DB::rollBack();
        }
    }

    /**
     * 2. Uji DataReconciliationService mendeteksi Orphan Aggregation ter-link, memisahkan Distribusi vs Neraca,
     * dan tidak melabeli baris historis batch_id IS NULL sebagai orphan.
     */
    public function test_reconciliation_service_detects_orphan_and_calculates_metrics(): void
    {
        $reconSvc = new DataReconciliationService();
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();
        $pelabuhan = DB::table('master_pelabuhan')->first();
        $this->assertNotNull($hs);
        $this->assertNotNull($negara);
        $this->assertNotNull($pelabuhan);

        // 1. Pada baseline bersih tanpa linked orphan: baris historis batch_id IS NULL dilaporkan terpisah
        // dan tidak menyebabkan false-positive ORPHAN_AGGREGATION_DETECTED.
        $baselineAudit = $reconSvc->auditConsistency();
        $this->assertIsArray($baselineAudit);
        $this->assertArrayHasKey('total_nilai_fob_ekspor', $baselineAudit);
        $this->assertArrayHasKey('total_nilai_cif_impor', $baselineAudit);
        $this->assertArrayHasKey('total_berat_netto_kg', $baselineAudit);
        $this->assertArrayHasKey('summary_by_source', $baselineAudit);
        $this->assertArrayHasKey('distribusi', $baselineAudit['summary_by_source']);
        $this->assertArrayHasKey('neraca', $baselineAudit['summary_by_source']);
        $this->assertArrayHasKey('historical_unlinked_rows', $baselineAudit);
        $this->assertGreaterThan(0, $baselineAudit['historical_unlinked_rows']);
        $this->assertArrayHasKey('orphan_aggregations', $baselineAudit);
        $this->assertArrayHasKey('aggregation_comparison', $baselineAudit);

        // 2. Uji deteksi orphan ter-link secara deterministik (baris mart ter-link milik batch yang sudah di-trash)
        DB::beginTransaction();
        try {
            $orphanBatchId = DB::table('data_batches')->insertGetId([
                'nama_file' => 'sentinel_orphan_recon_2091_04.dbf',
                'jenis_data' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
                'periode_tahun' => 2091,
                'periode_bulan' => 4,
                'ukuran_file' => '0.10',
                'status' => 'RILIS',
                'uploaded_by' => 1,
                'uploaded_at' => now(),
                'released_by' => 1,
                'released_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('trash')->insert([
                'batch_id' => $orphanBatchId,
                'data_snapshot' => json_encode(['batch_id' => $orphanBatchId]),
                'deleted_by' => 1,
                'deleted_at' => now(),
            ]);

            DB::table('monthly_trade_summaries')->insert([
                'batch_id' => $orphanBatchId,
                'status_visibilitas' => 'RILIS',
                'tipe_sumber' => 'distribusi',
                'hs_id' => $hs->hs_id,
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                'pelabuhan_key' => $pelabuhan->pelabuhan_id,
                'jenis_transaksi' => 'Ekspor',
                'bulan' => 4,
                'tahun' => 2091,
                'total_berat' => '500.000000',
                'total_nilai' => '12345.678900',
                'jumlah_transaksi' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $audit = $reconSvc->auditConsistency();
            $this->assertNotEmpty($audit['orphan_aggregations'], 'Layanan harus mendeteksi agregasi ter-link tanpa transaksi sumber aktif.');
            $this->assertEquals('ORPHAN_AGGREGATION_DETECTED', $audit['status']);
            $this->assertFalse($audit['is_consistent']);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 3. Uji Rekalkulasi Terisolasi: Rekalkulasi satu periode/sumber tidak mengubah periode/sumber lain.
     */
    public function test_recalculate_period_is_isolated_and_rollback_safe(): void
    {
        $aggSvc = new TradeAggregationService();

        DB::beginTransaction();
        try {
            // Hitung total agregasi periode lain (misal 2025 bulan 8) sebelum rekalkulasi
            $beforeVal = DB::table('monthly_trade_summaries')
                ->where('tahun', 2025)
                ->where('bulan', 8)
                ->sum('total_nilai');

            // Jalankan rekalkulasi hanya untuk 2026 bulan 9 Ekspor distribusi
            $aggSvc->recalculatePeriod(2026, 9, 'Ekspor', 'distribusi');

            // Total agregasi periode lain tidak boleh berubah sedikitpun
            $afterVal = DB::table('monthly_trade_summaries')
                ->where('tahun', 2025)
                ->where('bulan', 8)
                ->sum('total_nilai');

            $this->assertEquals($beforeVal, $afterVal, 'Rekalkulasi periode 2026/9 tidak boleh mengganggu periode 2025/8.');

        } finally {
            DB::rollBack();
        }
    }
}
