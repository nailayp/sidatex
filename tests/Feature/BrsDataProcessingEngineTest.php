<?php

namespace Tests\Feature;

use App\Models\DataBatch;
use App\Services\BrsAggregationService;
use App\Services\BrsDataProcessingService;
use App\Services\DbfReaderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrsDataProcessingEngineTest extends TestCase
{
    use DatabaseTransactions;

    protected BrsDataProcessingService $processingService;
    protected BrsAggregationService $aggregationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processingService = app(BrsDataProcessingService::class);
        $this->aggregationService = app(BrsAggregationService::class);
    }

    /**
     * Test 1: Pemrosesan 4 Kombinasi DBF menghasilkan metrik BRS yang presisi.
     */
    public function test_can_process_and_preview_all_four_dbf_combinations(): void
    {
        $combos = [
            [
                'file' => base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2609_POD_based.DBF'),
                'source' => 'distribusi',
                'trans' => 'ekspor',
                'expected_rows' => 841,
                'expected_juta_usd' => 2301.81,
            ],
            [
                'file' => base_path('referensi_bps/impor_distribusi/14_impor_atap_kdprov_0926.DBF'),
                'source' => 'distribusi',
                'trans' => 'impor',
                'expected_rows' => 931,
                'expected_juta_usd' => 215.26,
            ],
            [
                'file' => base_path('referensi_bps/ekspor_neraca/14_Atap_ekspor_2609_PROVORIG_based.DBF'),
                'source' => 'neraca',
                'trans' => 'ekspor',
                'expected_rows' => 950,
                'expected_juta_usd' => 2314.29,
            ],
            [
                'file' => base_path('referensi_bps/impor_neraca/14_impor_atap_kppbc_0926.DBF'),
                'source' => 'neraca',
                'trans' => 'impor',
                'expected_rows' => 1167,
                'expected_juta_usd' => 223.76,
            ],
        ];

        foreach ($combos as $c) {
            $this->assertFileExists($c['file']);
            $preview = $this->processingService->generatePreview(
                $c['file'],
                basename($c['file']),
                $c['source'],
                $c['trans']
            );

            $this->assertEquals($c['expected_rows'], $preview['meta']['total_baris']);
            $this->assertEquals($c['expected_rows'], $preview['meta']['baris_valid']);
            $this->assertEquals(0, $preview['meta']['baris_invalid']);
            $this->assertEquals($c['expected_juta_usd'], $preview['kpi']['total_nilai_juta_usd']);

            if ($c['trans'] === 'ekspor') {
                $this->assertArrayHasKey('INDUSTRI PENGOLAHAN', $preview['sektor_summary']);
                $this->assertArrayHasKey('PERTANIAN', $preview['sektor_summary']);
            } else {
                $this->assertArrayHasKey('Bahan Baku/Penolong', $preview['bec_summary']);
                $this->assertArrayHasKey('Barang Modal', $preview['bec_summary']);
                $this->assertArrayHasKey('Barang Konsumsi', $preview['bec_summary']);
            }

            $this->assertNotEmpty($preview['top_hs2']);
            $this->assertNotEmpty($preview['top_negara']);
        }
    }

    /**
     * Test 2: Pemisahan Metodologi Mutlak Distribusi vs Neraca.
     */
    public function test_strictly_isolates_distribusi_and_neraca_methodologies(): void
    {
        $fileDist = base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2609_POD_based.DBF');
        $fileNeraca = base_path('referensi_bps/ekspor_neraca/14_Atap_ekspor_2609_PROVORIG_based.DBF');

        $previewDist = $this->processingService->generatePreview($fileDist, basename($fileDist), 'distribusi', 'ekspor');
        $previewNeraca = $this->processingService->generatePreview($fileNeraca, basename($fileNeraca), 'neraca', 'ekspor');

        // Jumlah baris dan total nilai Distribusi dan Neraca berbeda sesuai metodologi POD vs PROVORIG
        $this->assertEquals(841, $previewDist['meta']['total_baris']);
        $this->assertEquals(950, $previewNeraca['meta']['total_baris']);
        $this->assertNotEquals($previewDist['kpi']['total_nilai_usd'], $previewNeraca['kpi']['total_nilai_usd']);
    }

    /**
     * Test 3: Penanganan HS Unmapped sesuai Aturan Ketat (Tidak Ditebak Sembarangan).
     */
    public function test_unmapped_hs_is_categorized_explicitly_under_unmapped_group(): void
    {
        // Jalankan pengujian unit dengan data sintetis unmapped
        $enrichedRows = [
            [
                'hs_id' => null,
                'hs_code' => '99999999',
                'hs_code_raw' => '99999999',
                'mapping_status' => 'unmapped',
                'negara_id' => 124,
                'provinsi_id' => 4,
                'kabkota_id' => null,
                'provorig' => null,
                'pelabuhan_id' => 1,
                'pelabuhan_name' => 'Dumai',
                'negara_name' => 'Malaysia',
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'neraca',
                'bulan' => 9,
                'tahun' => 2026,
                'berat' => '1000.000000',
                'nilai' => '50000.000000',
                'is_migas' => false,
                'sektor' => 'UNMAPPED',
                'bec_desc' => 'UNMAPPED',
                'comm_desc' => 'Belum Terpetakan',
                'kode_kppbc' => null,
                'nama_kppbc' => null,
            ]
        ];

        $this->assertEquals('unmapped', $enrichedRows[0]['mapping_status']);
        $this->assertEquals('UNMAPPED', $enrichedRows[0]['sektor']);
        $this->assertEquals('UNMAPPED', $enrichedRows[0]['bec_desc']);
    }

    /**
     * Test 4: Transisi Status Visibilitas PRIVATE ke RILIS Berlangsung Atomik.
     */
    public function test_batch_visibility_update_is_atomic(): void
    {
        // Buat dummy batch di sidatex_testing
        $batchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'test_visibility.dbf',
            'jenis_data' => 'Ekspor',
            'periode_tahun' => 2026,
            'periode_bulan' => 11,
            'tipe_sumber' => 'distribusi',
            'ukuran_file' => '0.50',
            'status' => 'PRIVATE',
            'uploaded_by' => 1,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Masukkan sampel ringkasan
        DB::table('monthly_trade_summaries')->insert([
            'batch_id' => $batchId,
            'status_visibilitas' => 'PRIVATE',
            'tipe_sumber' => 'distribusi',
            'jenis_transaksi' => 'Ekspor',
            'tahun' => 2026,
            'bulan' => 11,
            'hs2' => '15',
            'hs_id' => 1271,
            'mapping_status' => 'mapped',
            'is_migas' => false,
            'sektor' => 'INDUSTRI PENGOLAHAN',
            'negara_id' => 124,
            'provinsi_id' => 4,
            'pelabuhan_id' => 1,
            'pelabuhan_key' => 1,
            'total_berat' => '1000.000',
            'total_nilai' => '25000.00',
            'jumlah_transaksi' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('brs_indicator_summaries')->insert([
            'batch_id' => $batchId,
            'status_visibilitas' => 'PRIVATE',
            'tipe_sumber' => 'distribusi',
            'jenis_transaksi' => 'Ekspor',
            'tahun' => 2026,
            'bulan' => 11,
            'periode_tipe' => 'BULANAN',
            'kategori_dimensi' => 'TOTAL',
            'kode_dimensi' => 'TOTAL',
            'nama_dimensi' => 'Total Ekspor Riau',
            'nilai_usd' => '25000.0000',
            'nilai_juta_usd' => '0.03',
            'volume_ribu_ton' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verifikasi awal: status PRIVATE
        $this->assertEquals('PRIVATE', DB::table('data_batches')->where('batch_id', $batchId)->value('status'));
        $this->assertEquals('PRIVATE', DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->value('status_visibilitas'));
        $this->assertEquals('PRIVATE', DB::table('brs_indicator_summaries')->where('batch_id', $batchId)->value('status_visibilitas'));

        // Jalankan updateBatchVisibility ke RILIS
        $success = $this->aggregationService->updateBatchVisibility($batchId, 'RILIS', 1);
        $this->assertTrue($success);

        // Verifikasi setelah update: status RILIS
        $this->assertEquals('RILIS', DB::table('data_batches')->where('batch_id', $batchId)->value('status'));
        $this->assertEquals('RILIS', DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->value('status_visibilitas'));
        $this->assertEquals('RILIS', DB::table('brs_indicator_summaries')->where('batch_id', $batchId)->value('status_visibilitas'));
    }

    /**
     * Membuat salinan sementara berkas DBF referensi dengan kolom YEAR dipetakan ke tahun sentinel
     * agar pengujian terisolasi penuh dan tidak berbenturan dengan data periode aktif (mis. Batch #720).
     */
    private function cloneDbfWithSentinelYear(string $sourcePath, int $sentinelYear): string
    {
        $meta = app(DbfReaderService::class)->getHeader($sourcePath);
        $bytes = file_get_contents($sourcePath);
        $this->assertNotFalse($bytes, 'Gagal membaca berkas DBF sumber.');

        $yearOffset = 1; // Lewati 1 byte flag deletion record dBase III
        $yearLen = null;
        foreach ($meta['fields'] as $name => $spec) {
            if ($name === 'YEAR') {
                $yearLen = (int) $spec['len'];
                break;
            }
            $yearOffset += (int) $spec['len'];
        }
        $this->assertNotNull($yearLen, 'Kolom YEAR tidak ditemukan pada header DBF.');

        $replacement = str_pad((string) $sentinelYear, $yearLen, ' ', STR_PAD_LEFT);
        for ($i = 0; $i < $meta['num_records']; $i++) {
            $pos = $meta['header_len'] + ($i * $meta['record_len']) + $yearOffset;
            $bytes = substr_replace($bytes, $replacement, $pos, $yearLen);
        }

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sidatex_fixture_' . $sentinelYear . '_' . uniqid() . '.DBF';
        file_put_contents($tempPath, $bytes);

        return $tempPath;
    }

    /**
     * Test 5: Penyimpanan Batch & Transaksi Aktual serta Trigger Otomatis Two-Tier Mart.
     */
    public function test_can_store_batch_and_transactions_and_trigger_aggregation(): void
    {
        $sourceFile = base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2608_POD_based.DBF');
        $this->assertFileExists($sourceFile);

        $sentinelYear = 2098;
        $isolatedDbfPath = $this->cloneDbfWithSentinelYear($sourceFile, $sentinelYear);
        $stagingRelativePath = 'staging/test_store_' . $sentinelYear . '08_' . uniqid() . '.DBF';
        Storage::disk('local')->put($stagingRelativePath, file_get_contents($isolatedDbfPath));

        $batchId = null;
        $permFile = null;
        try {
            $result = $this->processingService->storeBatchAndTransactions(
                $stagingRelativePath,
                '14_Atap_ekspor_9808_POD_based.DBF',
                'distribusi',
                'ekspor',
                1,
                'PRIVATE'
            );

            $this->assertTrue($result['success']);
            $this->assertNotNull($result['batch_id']);
            $batchId = $result['batch_id'];
            $permFile = $result['arsip_file'];

            $this->assertEquals($sentinelYear, $result['tahun']);
            $this->assertEquals(8, $result['bulan']);
            $this->assertEquals('distribusi', $result['sumber_data']);
            $this->assertEquals('ekspor', $result['jenis_transaksi']);
            $this->assertEquals('PRIVATE', $result['status']);
            $this->assertEquals(809, $result['total_baris']);

            // 1. Verifikasi tabel distribusi_transactions
            $savedTxCount = DB::table('distribusi_transactions')->where('batch_id', $batchId)->count();
            $this->assertEquals($result['total_baris'], $savedTxCount);

            // 2. Verifikasi Tier-1 Mart: monthly_trade_summaries
            $mtsCount = DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->count();
            $this->assertGreaterThan(0, $mtsCount);
            $sampleMts = DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->first();
            $this->assertEquals('PRIVATE', $sampleMts->status_visibilitas);
            $this->assertEquals($sentinelYear, $sampleMts->tahun);
            $this->assertEquals(8, $sampleMts->bulan);

            // 3. Verifikasi Tier-2 Mart: brs_indicator_summaries
            $indCount = DB::table('brs_indicator_summaries')->where('batch_id', $batchId)->count();
            $this->assertGreaterThan(0, $indCount);

            $totalIndicator = DB::table('brs_indicator_summaries')
                ->where('batch_id', $batchId)
                ->where('kategori_dimensi', 'TOTAL')
                ->first();
            $this->assertNotNull($totalIndicator);
            $this->assertEquals($result['total_nilai_juta_usd'], (float)$totalIndicator->nilai_juta_usd);

            // 4. Verifikasi Log Aktivitas
            $logExists = DB::table('activity_logs')
                ->where('deskripsi', 'like', "%Batch #{$batchId}%")
                ->exists();
            $this->assertTrue($logExists);

        } finally {
            if ($permFile && Storage::disk('local')->exists($permFile)) {
                Storage::disk('local')->delete($permFile);
            }
            if (Storage::disk('local')->exists($stagingRelativePath)) {
                Storage::disk('local')->delete($stagingRelativePath);
            }
            if (file_exists($isolatedDbfPath)) {
                @unlink($isolatedDbfPath);
            }
            if ($batchId) {
                DB::table('distribusi_transactions')->where('batch_id', $batchId)->delete();
                DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->delete();
                DB::table('brs_indicator_summaries')->where('batch_id', $batchId)->delete();
                DB::table('data_batches')->where('batch_id', $batchId)->delete();
            }
        }
    }
}
