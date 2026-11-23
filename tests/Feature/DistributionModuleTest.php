<?php

namespace Tests\Feature;

use App\Services\DbfReaderService;
use App\Services\DistributionStatisticsService;
use App\Services\DistributionUploadService;
use App\Services\ExportDistributionParser;
use App\Services\ImportDistributionParser;
use App\Services\TradeAggregationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DistributionModuleTest extends TestCase
{

    /**
     * Test verification of September 2026 Distribution export & import figures against official BPS release.
     */
    public function test_september_2026_distribution_reconciles_with_bps_release(): void
    {
        $statSvc = new DistributionStatisticsService();

        DB::beginTransaction();
        try {
            // Simulasi in-transaction status RILIS untuk batch distribusi 9/2026 agar formula BRS dapat diverifikasi
            DB::table('data_batches')
                ->where('periode_tahun', 2026)
                ->where('periode_bulan', 9)
                ->where('tipe_sumber', 'distribusi')
                ->update(['status' => 'RILIS']);

            DB::table('trash')->whereIn('batch_id', function ($q) {
                $q->select('batch_id')->from('data_batches')->where('periode_tahun', 2026)->where('periode_bulan', 9);
            })->update(['restored_at' => now()]);

            // Sinkronkan origin mart di dalam transaksi rollback (karena batch #71 PRIVATE tidak lagi membocorkan origin mart)
            app(\App\Services\BrsAggregationService::class)->syncOriginSummariesForPeriod(2026, 9, 'Ekspor');

            // 1. Ekspor September 2026
            $ekspor = $statSvc->getSummaryMetrics(2026, 9, 'ekspor');
            $this->assertEquals(2026, $ekspor['tahun']);
            $this->assertEquals(9, $ekspor['bulan']);

            // Target resmi rilis BPS Slide 4 & 19: US$ 2.301,81 Juta
            $this->assertEqualsWithDelta(2301.81, $ekspor['bulan_ini']['nilai_total_juta'], 0.05, 'Nilai Ekspor September 2026 harus sesuai rilis resmi BPS.');
            $this->assertGreaterThan(0, $ekspor['bulan_ini']['nilai_nonmigas_juta']);
            $this->assertGreaterThan(0, $ekspor['bulan_ini']['nilai_migas_juta']);

            // 2. Sektor Ekspor
            $sektor = $statSvc->getSektorBreakdown(2026, 9);
            $this->assertEqualsWithDelta(2301.81, $sektor['total_nilai_juta_usd'], 0.05);
            $this->assertNotEmpty($sektor['sektor']);

            // 3. Top 3 Komoditas Nonmigas
            $topComm = $statSvc->getTopCommodities(2026, 9);
            $this->assertCount(4, $topComm['items']); // Top 1, Top 2, Top 3, Lainnya
            $this->assertEquals('15', $topComm['items'][0]['hs2']); // HS 15 CPO / Lemak Hewan/Nabati

            // 4. Asal Wilayah (PROVORIG)
            $origin = $statSvc->getOriginDistribution(2026, 9);
            $this->assertEqualsWithDelta(2301.81, $origin['total_fob_juta_usd'], 0.05);
            $this->assertGreaterThan(99.0, $origin['riau_share_persen'], 'Mayoritas komoditas ekspor berasal dari Provinsi Riau.');
            $this->assertLessThan(1.0, $origin['non_riau_share_persen'], 'Sebagian kecil komoditas berasal dari luar Riau (PROVORIG).');

            // 5. Impor September 2026:
            $impor = $statSvc->getSummaryMetrics(2026, 9, 'impor');
            $this->assertEquals(2026, $impor['tahun']);
            $this->assertEquals(9, $impor['bulan']);

            // Target resmi rilis BPS Slide 10 & 19: US$ 215,26 Juta
            $this->assertEqualsWithDelta(215.26, $impor['bulan_ini']['nilai_total_juta'], 0.05, 'Nilai Impor September 2026 harus sesuai rilis resmi BPS.');

            // Impor BEC (Golongan Penggunaan Barang)
            $bec = $statSvc->getImportBecBreakdown(2026, 9);
            $this->assertEqualsWithDelta(215.26, $bec['total_impor_juta_usd'], 0.05);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Test prevention of accidental duplicate upload for the same period.
     */
    public function test_duplicate_upload_prevention_for_same_period(): void
    {
        $reader = new DbfReaderService();
        $uploadSvc = new DistributionUploadService(
            new ExportDistributionParser($reader),
            new ImportDistributionParser($reader),
            new TradeAggregationService()
        );

        $filePath = base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2609_POD_based.DBF');
        $this->assertFileExists($filePath);

        $preview = $uploadSvc->validateAndPreview($filePath, '14_Atap_ekspor_2609_POD_based.DBF', 'ekspor');
        $this->assertNotNull($preview['duplikasi_batch'], 'Sistem harus mendeteksi bahwa batch periode Sep 2026 sudah ada.');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah pernah diunggah');

        // Harus gagal jika overwriteDuplicate = false
        $uploadSvc->processAndStore($filePath, '14_Atap_ekspor_2609_POD_based.DBF', 'ekspor', 1, false);
    }
}
