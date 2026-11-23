<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FastExcelExporter;
use App\Services\PowerPointExportService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class Phase2gNeracaFilteringAndExportTest extends TestCase
{
    private array $createdBatchIds = [];
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];

        if (!empty($this->createdBatchIds)) {
            DB::table('brs_indicator_summaries')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('monthly_trade_summaries')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('trash')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('distribusi_transactions')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('neraca_transactions')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('data_batches')->whereIn('batch_id', $this->createdBatchIds)->delete();
            $this->createdBatchIds = [];
        }

        parent::tearDown();
    }

    public function test_filtering_query_separates_neraca_and_distribusi_and_includes_unmapped_hs_raw(): void
    {
        $superAdmin = User::where('role_id', 1)->first();
        $pegawai = User::where('role_id', 2)->first();
        $viewer = User::where('role_id', 3)->first();
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();
        $pelabuhan = DB::table('master_pelabuhan')->first();

        $this->assertNotNull($superAdmin);
        $this->assertNotNull($pegawai);
        $this->assertNotNull($viewer);
        $this->assertNotNull($hs);
        $this->assertNotNull($negara);
        $this->assertNotNull($pelabuhan);

        // 1. Distribusi RILIS (2093-01)
        $distRilisId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_filter_dist_2093.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'periode_tahun' => 2093,
            'periode_bulan' => 1,
            'ukuran_file' => '0.10',
            'status' => 'RILIS',
            'uploaded_by' => $superAdmin->id,
            'uploaded_at' => now(),
            'released_by' => $superAdmin->id,
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $distRilisId;

        DB::table('distribusi_transactions')->insert([
            'batch_id' => $distRilisId,
            'hs_id' => $hs->hs_id,
            'negara_id' => $negara->negara_id,
            'provinsi_id' => 4,
            'pelabuhan_id' => $pelabuhan->pelabuhan_id,
            'jenis_transaksi' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'bulan' => 1,
            'tahun' => 2093,
            'berat' => '1000.000000',
            'nilai' => '55555.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Neraca RILIS (2093-01) dengan 1 mapped & 1 unmapped (hs_code_raw = 88887777)
        $neracaRilisId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_filter_neraca_rilis_2093.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'neraca',
            'periode_tahun' => 2093,
            'periode_bulan' => 1,
            'ukuran_file' => '0.10',
            'status' => 'RILIS',
            'uploaded_by' => $superAdmin->id,
            'uploaded_at' => now(),
            'released_by' => $superAdmin->id,
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $neracaRilisId;

        DB::table('neraca_transactions')->insert([
            [
                'batch_id' => $neracaRilisId,
                'hs_id' => $hs->hs_id,
                'hs_code_raw' => $hs->hs_code,
                'hs_desc_raw' => null,
                'mapping_status' => 'mapped',
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'neraca',
                'bulan' => 1,
                'tahun' => 2093,
                'berat' => '200.123456',
                'nilai' => '10000.123456',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'batch_id' => $neracaRilisId,
                'hs_id' => null,
                'hs_code_raw' => '88887777',
                'hs_desc_raw' => 'KOMODITAS UNMAPPED SENTINEL',
                'mapping_status' => 'unmapped',
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => null,
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'neraca',
                'bulan' => 1,
                'tahun' => 2093,
                'berat' => '50.000000',
                'nilai' => '2500.500000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // 3. Neraca PRIVATE (2093-01)
        $neracaPrivateId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_filter_neraca_private_2093.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'neraca',
            'periode_tahun' => 2093,
            'periode_bulan' => 1,
            'ukuran_file' => '0.10',
            'status' => 'PRIVATE',
            'uploaded_by' => $superAdmin->id,
            'uploaded_at' => now(),
            'released_by' => null,
            'released_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $neracaPrivateId;

        DB::table('neraca_transactions')->insert([
            'batch_id' => $neracaPrivateId,
            'hs_id' => $hs->hs_id,
            'hs_code_raw' => $hs->hs_code,
            'mapping_status' => 'mapped',
            'negara_id' => $negara->negara_id,
            'provinsi_id' => 4,
            'pelabuhan_id' => null,
            'jenis_transaksi' => 'Ekspor',
            'tipe_sumber' => 'neraca',
            'bulan' => 1,
            'tahun' => 2093,
            'berat' => '300.000000',
            'nilai' => '75000.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Neraca Trash (2093-01)
        $neracaTrashId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_filter_neraca_trash_2093.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'neraca',
            'periode_tahun' => 2093,
            'periode_bulan' => 1,
            'ukuran_file' => '0.10',
            'status' => 'RILIS',
            'uploaded_by' => $superAdmin->id,
            'uploaded_at' => now(),
            'released_by' => $superAdmin->id,
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $neracaTrashId;

        DB::table('neraca_transactions')->insert([
            'batch_id' => $neracaTrashId,
            'hs_id' => $hs->hs_id,
            'hs_code_raw' => $hs->hs_code,
            'mapping_status' => 'mapped',
            'negara_id' => $negara->negara_id,
            'provinsi_id' => 4,
            'pelabuhan_id' => null,
            'jenis_transaksi' => 'Ekspor',
            'tipe_sumber' => 'neraca',
            'bulan' => 1,
            'tahun' => 2093,
            'berat' => '999.000000',
            'nilai' => '999999.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('trash')->insert([
            'batch_id' => $neracaTrashId,
            'data_snapshot' => json_encode(['batch_id' => $neracaTrashId]),
            'deleted_by' => $superAdmin->id,
            'deleted_at' => now(),
        ]);

        // A. Pegawai: hanya melihat 2 baris Neraca RILIS (tidak termasuk PRIVATE, Trash, maupun Distribusi)
        $resPegawai = $this->actingAs($pegawai)->getJson('/filtering-data/query?tipe_sumber=neraca&tahun=2093&bulan=1');
        $resPegawai->assertStatus(200);
        $this->assertSame(2, $resPegawai->json('summary.total_rows'));
        $this->assertSame('12500.623456', $resPegawai->json('summary.total_nilai_fob_exact'));

        // B. Filter hs2='88' harus menemukan baris unmapped hs_code_raw='88887777'
        $resHsRaw = $this->actingAs($pegawai)->getJson('/filtering-data/query?tipe_sumber=neraca&tahun=2093&bulan=1&hs2=88');
        $resHsRaw->assertStatus(200);
        $this->assertSame(1, $resHsRaw->json('summary.total_rows'));
        $this->assertSame('88887777', $resHsRaw->json('data.data.0.hs_code'));
        $this->assertSame('KOMODITAS UNMAPPED SENTINEL', $resHsRaw->json('data.data.0.hs_desc'));
        $this->assertSame('unmapped', $resHsRaw->json('data.data.0.mapping_status'));

        // C. Viewer Eksekutif: melihat RILIS + PRIVATE (3 baris), tetap mengecualikan Trash & Distribusi
        $resViewer = $this->actingAs($viewer)->getJson('/filtering-data/query?tipe_sumber=neraca&tahun=2093&bulan=1');
        $resViewer->assertStatus(200);
        $this->assertSame(3, $resViewer->json('summary.total_rows'));
        $this->assertSame('87500.623456', $resViewer->json('summary.total_nilai_fob_exact'));

        // D. Super Admin query Distribusi: hanya melihat 1 baris Distribusi
        $resDist = $this->actingAs($superAdmin)->getJson('/filtering-data/query?tipe_sumber=distribusi&tahun=2093&bulan=1');
        $resDist->assertStatus(200);
        $this->assertSame(1, $resDist->json('summary.total_rows'));
        $this->assertSame('55555.000000', $resDist->json('summary.total_nilai_fob_exact'));
    }

    public function test_fast_excel_exporter_supports_neraca_impor_with_cif_header_hs_raw_and_6_decimal_precision(): void
    {
        $negara = DB::table('master_negara')->first();
        $this->assertNotNull($negara);

        $neracaImporId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_export_neraca_impor_2093.dbf',
            'jenis_data' => 'Impor',
            'tipe_sumber' => 'neraca',
            'periode_tahun' => 2093,
            'periode_bulan' => 2,
            'ukuran_file' => '0.10',
            'status' => 'RILIS',
            'uploaded_by' => 1,
            'uploaded_at' => now(),
            'released_by' => 1,
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $neracaImporId;

        DB::table('neraca_transactions')->insert([
            'batch_id' => $neracaImporId,
            'hs_id' => null,
            'hs_code_raw' => '77776666',
            'hs_desc_raw' => 'BARANG IMPOR UNMAPPED',
            'mapping_status' => 'unmapped',
            'negara_id' => $negara->negara_id,
            'provinsi_id' => 4,
            'pelabuhan_id' => null,
            'jenis_transaksi' => 'Impor',
            'tipe_sumber' => 'neraca',
            'bulan' => 2,
            'tahun' => 2093,
            'berat' => '5432.109876',
            'nilai' => '12345.678901',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tempXlsx = tempnam(sys_get_temp_dir(), 'p2g2a_xlsx_') . '.xlsx';
        $this->tempFiles[] = $tempXlsx;

        $exporter = new FastExcelExporter();
        $exportedCount = $exporter->exportBatchToPath($neracaImporId, $tempXlsx);

        $this->assertSame(1, $exportedCount, 'FastExcelExporter harus membaca baris dari neraca_transactions.');
        $this->assertFileExists($tempXlsx);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tempXlsx) === true);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertIsString($sheetXml);
        $this->assertStringContainsString('CIF (US$)', $sheetXml, 'Header kolom nilai untuk Impor wajib CIF (US$).');
        $this->assertStringContainsString('77776666', $sheetXml, 'Kode HS unmapped Neraca harus tampil di Excel.');
        $this->assertStringContainsString('BARANG IMPOR UNMAPPED', $sheetXml);
        $this->assertStringContainsString('<v>5432.109876</v>', $sheetXml, 'Presisi 6 desimal berat harus dipertahankan.');
        $this->assertStringContainsString('<v>12345.678901</v>', $sheetXml, 'Presisi 6 desimal nilai harus dipertahankan.');
    }

    public function test_powerpoint_export_service_supports_neraca_impor_with_cif_label(): void
    {
        $negara = DB::table('master_negara')->first();
        $this->assertNotNull($negara);

        $neracaImporId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_pptx_neraca_impor_2093.dbf',
            'jenis_data' => 'Impor',
            'tipe_sumber' => 'neraca',
            'periode_tahun' => 2093,
            'periode_bulan' => 3,
            'ukuran_file' => '0.10',
            'status' => 'RILIS',
            'uploaded_by' => 1,
            'uploaded_at' => now(),
            'released_by' => 1,
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $neracaImporId;

        DB::table('neraca_transactions')->insert([
            'batch_id' => $neracaImporId,
            'hs_id' => null,
            'hs_code_raw' => '66665555',
            'mapping_status' => 'unmapped',
            'negara_id' => $negara->negara_id,
            'provinsi_id' => 4,
            'pelabuhan_id' => null,
            'jenis_transaksi' => 'Impor',
            'tipe_sumber' => 'neraca',
            'bulan' => 3,
            'tahun' => 2093,
            'berat' => '2500000.000000',
            'nilai' => '12340000.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pptSvc = new PowerPointExportService();
        $tempPptx = $pptSvc->generateBrsSlideDeck($neracaImporId);
        $this->tempFiles[] = $tempPptx;

        $this->assertFileExists($tempPptx);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tempPptx) === true);
        $slide2Xml = $zip->getFromName('ppt/slides/slide2.xml');
        $zip->close();

        $this->assertIsString($slide2Xml);
        $this->assertStringContainsString('TOTAL NILAI CIF', $slide2Xml);
        $this->assertStringContainsString('12.34 Juta', $slide2Xml);
        $this->assertStringContainsString('2.5 Ribu Ton', $slide2Xml);
    }
}
