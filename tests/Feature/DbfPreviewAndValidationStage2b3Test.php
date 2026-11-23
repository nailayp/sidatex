<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DbfUploadValidationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DbfPreviewAndValidationStage2b3Test extends TestCase
{
    /**
     * 1. Test DbfUploadValidationService secara terisolasi untuk 4 varian parser.
     */
    public function test_dbf_upload_validation_service_routes_all_four_parsers_accurately(): void
    {
        $service = app(DbfUploadValidationService::class);

        // A. Distribusi Ekspor
        $pathDistExp = base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2609_POD_based.DBF');
        $this->assertFileExists($pathDistExp);
        $resDistExp = $service->validateAndPreview($pathDistExp, '14_Atap_ekspor_2609_POD_based.DBF', 'distribusi', 'ekspor');
        $this->assertEquals('distribusi', $resDistExp['sumber_data']);
        $this->assertEquals('ekspor', $resDistExp['jenis_transaksi']);
        $this->assertEquals(2026, $resDistExp['tahun']);
        $this->assertEquals(9, $resDistExp['bulan']);
        $this->assertEquals(841, $resDistExp['total_baris']);
        $this->assertEquals(841, $resDistExp['baris_valid']);
        $this->assertEquals(0, $resDistExp['baris_invalid']);
        $this->assertNotEmpty($resDistExp['sample_data']);

        // B. Distribusi Impor
        $pathDistImp = base_path('referensi_bps/impor_distribusi/14_impor_atap_kdprov_0926.DBF');
        $this->assertFileExists($pathDistImp);
        $resDistImp = $service->validateAndPreview($pathDistImp, '14_impor_atap_kdprov_0926.DBF', 'distribusi', 'impor');
        $this->assertEquals('distribusi', $resDistImp['sumber_data']);
        $this->assertEquals('impor', $resDistImp['jenis_transaksi']);
        $this->assertEquals(2026, $resDistImp['tahun']);
        $this->assertEquals(9, $resDistImp['bulan']);
        $this->assertEquals(931, $resDistImp['total_baris']);
        $this->assertEquals(931, $resDistImp['baris_valid']);
        $this->assertEquals(0, $resDistImp['baris_invalid']);

        // C. Neraca Ekspor
        $pathNeracaExp = base_path('referensi_bps/ekspor_neraca/14_Atap_ekspor_2609_PROVORIG_based.DBF');
        if (file_exists($pathNeracaExp) && is_readable($pathNeracaExp)) {
            $resNeracaExp = $service->validateAndPreview($pathNeracaExp, '14_Atap_ekspor_2609_PROVORIG_based.DBF', 'neraca', 'ekspor');
            $this->assertEquals('neraca', $resNeracaExp['sumber_data']);
            $this->assertEquals('ekspor', $resNeracaExp['jenis_transaksi']);
            $this->assertEquals(2026, $resNeracaExp['tahun']);
            $this->assertEquals(9, $resNeracaExp['bulan']);
            $this->assertEquals(950, $resNeracaExp['total_baris']);
            $this->assertEquals(950, $resNeracaExp['baris_valid']);
        }

        // D. Neraca Impor (KPPBC 15 Kolom)
        $pathNeracaImp = base_path('referensi_bps/impor_neraca/14_impor_atap_kppbc_0826.DBF');
        if (file_exists($pathNeracaImp) && is_readable($pathNeracaImp)) {
            $resNeracaImp = $service->validateAndPreview($pathNeracaImp, '14_impor_atap_kppbc_0826.DBF', 'neraca', 'impor');
            $this->assertEquals('neraca', $resNeracaImp['sumber_data']);
            $this->assertEquals('impor', $resNeracaImp['jenis_transaksi']);
            $this->assertEquals(2026, $resNeracaImp['tahun']);
            $this->assertEquals(8, $resNeracaImp['bulan']);
            $this->assertEquals(1021, $resNeracaImp['total_baris']);
            $this->assertEquals(1021, $resNeracaImp['baris_valid']);
            $this->assertArrayHasKey('kppbc', $resNeracaImp['sample_data'][0]);
            $this->assertArrayHasKey('nama_kppbc', $resNeracaImp['sample_data'][0]);
        }
    }

    /**
     * 2. Test Endpoint HTTP POST /upload-data/preview untuk Distribusi Ekspor & Impor.
     */
    public function test_http_preview_endpoint_supports_distribusi_ekspor_and_impor(): void
    {
        $admin = User::where('role_id', 1)->first();
        $this->assertNotNull($admin);

        // Ekspor Distribusi
        $fileExp = new UploadedFile(
            base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2609_POD_based.DBF'),
            '14_Atap_ekspor_2609_POD_based.DBF',
            'application/octet-stream',
            null,
            true
        );

        $resExp = $this->actingAs($admin)->postJson(route('upload.preview'), [
            'excel_file_final' => $fileExp,
            'sumber_data_final' => 'Distribusi',
            'jenis_data_final' => 'Ekspor',
        ]);

        $resExp->assertStatus(200);
        $resExp->assertJsonPath('success', true);
        $resExp->assertJsonPath('sumber_data', 'distribusi');
        $resExp->assertJsonPath('jenis_data', 'Ekspor');
        $resExp->assertJsonPath('jumlah_baris', 841);
        $resExp->assertJsonPath('tahun', 2026);
        $resExp->assertJsonPath('bulan', 9);

        // Impor Distribusi
        $fileImp = new UploadedFile(
            base_path('referensi_bps/impor_distribusi/14_impor_atap_kdprov_0926.DBF'),
            '14_impor_atap_kdprov_0926.DBF',
            'application/octet-stream',
            null,
            true
        );

        $resImp = $this->actingAs($admin)->postJson(route('upload.preview'), [
            'excel_file_final' => $fileImp,
            'sumber_data_final' => 'Distribusi',
            'jenis_data_final' => 'Impor',
        ]);

        $resImp->assertStatus(200);
        $resImp->assertJsonPath('success', true);
        $resImp->assertJsonPath('sumber_data', 'distribusi');
        $resImp->assertJsonPath('jenis_data', 'Impor');
        $resImp->assertJsonPath('jumlah_baris', 931);
    }

    /**
     * 3. Test Endpoint HTTP POST /upload-data/preview untuk Neraca Ekspor & Impor.
     */
    public function test_http_preview_endpoint_supports_neraca_ekspor_and_impor(): void
    {
        $admin = User::where('role_id', 1)->first();
        $this->assertNotNull($admin);

        $pathNeracaExp = base_path('referensi_bps/ekspor_neraca/14_Atap_ekspor_2609_PROVORIG_based.DBF');
        if (file_exists($pathNeracaExp) && is_readable($pathNeracaExp)) {
            $fileExp = new UploadedFile(
                $pathNeracaExp,
                '14_Atap_ekspor_2609_PROVORIG_based.DBF',
                'application/octet-stream',
                null,
                true
            );

            $resExp = $this->actingAs($admin)->postJson(route('upload.preview'), [
                'excel_file_final' => $fileExp,
                'sumber_data_final' => 'Neraca',
                'jenis_data_final' => 'Ekspor',
            ]);

            $resExp->assertStatus(200);
            $resExp->assertJsonPath('success', true);
            $resExp->assertJsonPath('sumber_data', 'neraca');
            $resExp->assertJsonPath('jenis_data', 'Ekspor');
            $resExp->assertJsonPath('jumlah_baris', 950);
        }

        $pathNeracaImp = base_path('referensi_bps/impor_neraca/14_impor_atap_kppbc_0826.DBF');
        if (file_exists($pathNeracaImp) && is_readable($pathNeracaImp)) {
            $fileImp = new UploadedFile(
                $pathNeracaImp,
                '14_impor_atap_kppbc_0826.DBF',
                'application/octet-stream',
                null,
                true
            );

            $resImp = $this->actingAs($admin)->postJson(route('upload.preview'), [
                'excel_file_final' => $fileImp,
                'sumber_data_final' => 'Neraca',
                'jenis_data_final' => 'Impor',
            ]);

            $resImp->assertStatus(200);
            $resImp->assertJsonPath('success', true);
            $resImp->assertJsonPath('sumber_data', 'neraca');
            $resImp->assertJsonPath('jenis_data', 'Impor');
            $resImp->assertJsonPath('jumlah_baris', 1021);
        }
    }

    /**
     * 4. Verifikasi bahwa Preview bersifat READ-ONLY dan TIDAK mengubah basis data.
     */
    public function test_preview_is_strictly_read_only_and_does_not_mutate_database(): void
    {
        $admin = User::where('role_id', 1)->first();
        $this->assertNotNull($admin);

        $distCountBefore = DB::table('distribusi_transactions')->count();
        $neracaCountBefore = DB::table('neraca_transactions')->count();
        $batchCountBefore = DB::table('data_batches')->count();

        $fileExp = new UploadedFile(
            base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2609_POD_based.DBF'),
            '14_Atap_ekspor_2609_POD_based.DBF',
            'application/octet-stream',
            null,
            true
        );

        $response = $this->actingAs($admin)->postJson(route('upload.preview'), [
            'excel_file_final' => $fileExp,
            'sumber_data_final' => 'Distribusi',
            'jenis_data_final' => 'Ekspor',
        ]);

        $response->assertStatus(200);

        $distCountAfter = DB::table('distribusi_transactions')->count();
        $neracaCountAfter = DB::table('neraca_transactions')->count();
        $batchCountAfter = DB::table('data_batches')->count();

        $this->assertSame($distCountBefore, $distCountAfter, 'Tabel distribusi_transactions tidak boleh berubah saat preview.');
        $this->assertSame($neracaCountBefore, $neracaCountAfter, 'Tabel neraca_transactions tidak boleh berubah saat preview.');
        $this->assertSame($batchCountBefore, $batchCountAfter, 'Tabel data_batches tidak boleh bertambah saat preview.');
    }
}
