<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SidatexComprehensiveTest extends TestCase
{

    /**
     * Test akses halaman publik dan proteksi auth.
     */
    public function test_guest_is_redirected_to_login()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');

        $response = $this->get('/filtering-data');
        $response->assertRedirect('/login');

        $response = $this->get('/manajemen-data');
        $response->assertRedirect('/login');
    }

    /**
     * Test Super Admin dapat mengakses dashboard dan filtering.
     */
    public function test_super_admin_can_access_modules()
    {
        $admin = User::where('role_id', 1)->first();
        if (!$admin) {
            $this->markTestSkipped('Akun Super Admin tidak ditemukan.');
        }

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertStatus(200);

        $response = $this->actingAs($admin)->get('/dashboard/metrics');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data']);

        $response = $this->actingAs($admin)->get('/filtering-data');
        $response->assertStatus(200);

        $response = $this->actingAs($admin)->get('/analisis-perdagangan');
        $response->assertStatus(200);

        $response = $this->actingAs($admin)->get('/manajemen-data');
        $response->assertStatus(200);
    }

    /**
     * Test aturan rilis data: Pegawai tidak dapat toggle release status.
     */
    public function test_non_admin_cannot_toggle_release()
    {
        $pegawai = User::where('role_id', 2)->first();
        if (!$pegawai) {
            $this->markTestSkipped('Akun pegawai tidak ditemukan di database.');
        }

        $batch = DB::table('data_batches')->first();
        if (!$batch) {
            $this->markTestSkipped('Data batch tidak ditemukan di database.');
        }

        $response = $this->actingAs($pegawai)->postJson("/manajemen-data/{$batch->batch_id}/toggle-release");
        $response->assertStatus(403);
    }

    /**
     * Test API filtering data mengembalikan struktur JSON yang valid.
     */
    public function test_filtering_query_api_returns_valid_structure()
    {
        $admin = User::where('role_id', 1)->first();
        if (!$admin) {
            $this->markTestSkipped('Super Admin tidak ditemukan.');
        }

        $response = $this->actingAs($admin)->getJson('/filtering-data/query?tahun=2024&jenis_transaksi=Ekspor');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'summary' => [
                'total_rows',
                'total_nilai_fob',
                'total_nilai_fob_juta',
                'total_berat_netto',
                'total_berat_ribu_ton',
            ],
            'data' => [
                'current_page',
                'data',
            ]
        ]);
    }

    /**
     * Test API Analisis Perdagangan mengembalikan agregasi data.
     */
    public function test_analisis_data_api_returns_aggregates()
    {
        $admin = User::where('role_id', 1)->first();
        if (!$admin) {
            $this->markTestSkipped('Super Admin tidak ditemukan.');
        }

        $response = $this->actingAs($admin)->getJson('/analisis-perdagangan/data?tahun=2024&jenis=Ekspor');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'tahun',
            'jenis',
            'grand_total_fob',
            'top_komoditas',
            'top_negara',
            'top_pelabuhan',
            'growth',
            'monthly_trend',
        ]);
    }

    /**
     * Test validasi pemilihan sheet Impor vs Ekspor dengan pesan informatif daftar sheet.
     */
    public function test_preview_validates_sheet_matching_for_export_and_import()
    {
        $admin = User::where('role_id', 1)->first();
        if (!$admin) {
            $this->markTestSkipped('Super Admin tidak ditemukan.');
        }

        // 1. Buat file Excel temporer dengan sheet bernama 'Impor'
        $spreadsheetImpor = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheetImpor->getActiveSheet();
        $sheet->setTitle('Impor');
        $sheet->fromArray([
            ['BULAN', 'TAHUN', 'KODE HS', 'KODE_NGR', 'NETTO (KG)', 'FOB (US$)', 'PROVORIG'],
            [1, 2024, '27090010', '116', 5000, 100000, '14'],
        ]);
        $tempPathImpor = tempnam(sys_get_temp_dir(), 'test_impor_') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheetImpor);
        $writer->save($tempPathImpor);
        $spreadsheetImpor->disconnectWorksheets();

        $uploadedFileImpor = new \Illuminate\Http\UploadedFile(
            $tempPathImpor,
            'data_impor_test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        // Uji preview Impor menggunakan file ber-sheet 'Impor' -> harus sukses
        $response = $this->actingAs($admin)->postJson('/upload-data/preview', [
            'excel_file_final' => $uploadedFileImpor,
            'jenis_data_final' => 'Impor',
        ]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'jenis_data' => 'Impor', 'nama_sheet' => 'Impor']);

        // Uji preview Ekspor menggunakan file yang hanya memiliki sheet 'Impor' -> harus 422 dengan pesan informatif
        $uploadedFileForEkspor = new \Illuminate\Http\UploadedFile(
            $tempPathImpor,
            'data_impor_test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
        $responseEksporFail = $this->actingAs($admin)->postJson('/upload-data/preview', [
            'excel_file_final' => $uploadedFileForEkspor,
            'jenis_data_final' => 'Ekspor',
        ]);
        $responseEksporFail->assertStatus(422);
        $responseEksporFail->assertJsonFragment([
            'success' => false,
        ]);
        $this->assertStringContainsString('Worksheet untuk data Ekspor tidak ditemukan', $responseEksporFail->json('message'));
        $this->assertStringContainsString('Sheet yang tersedia: [Impor]', $responseEksporFail->json('message'));

        if (file_exists($tempPathImpor)) {
            @unlink($tempPathImpor);
        }
    }

    /**
     * Test validasi batas ukuran file upload (lolos pada <= 50MB dan ditolak pada > 50MB).
     */
    public function test_upload_file_size_limits_validation()
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');

        $admin = User::where('role_id', 1)->first();
        if (!$admin) {
            $this->markTestSkipped('Super Admin tidak ditemukan.');
        }

        // 1. File kecil valid (misal 100 KB) -> harus lolos validasi ukuran
        $smallFile = \Illuminate\Http\UploadedFile::fake()->create('small_valid.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $responseSmall = $this->actingAs($admin)->postJson('/upload-data/process', [
            'excel_file_final' => $smallFile,
            'jenis_data_final' => 'Ekspor',
        ]);
        // Tidak boleh error 422 karena ukuran file (jika ada error lain seperti parsing struktur, itu wajar untuk fake file)
        if ($responseSmall->status() === 422) {
            $this->assertFalse(str_contains(json_encode($responseSmall->json()), 'Ukuran file tidak boleh melebihi 50 MB'));
        }

        // 2. File pas 50 MB (51200 KB) -> harus lolos validasi ukuran max:51200
        $maxFile = \Illuminate\Http\UploadedFile::fake()->create('boundary_50mb.xlsx', 51200, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $responseMax = $this->actingAs($admin)->postJson('/upload-data/process', [
            'excel_file_final' => $maxFile,
            'jenis_data_final' => 'Ekspor',
        ]);
        if ($responseMax->status() === 422) {
            $this->assertFalse(str_contains(json_encode($responseMax->json()), 'Ukuran file tidak boleh melebihi 50 MB'));
        }

        // 3. File melebihi batas 50 MB (55 MB / 56320 KB) -> harus ditolak oleh Laravel validation
        $oversizedFile = \Illuminate\Http\UploadedFile::fake()->create('oversized_55mb.xlsx', 56320, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $responseOversized = $this->actingAs($admin)->postJson('/upload-data/process', [
            'excel_file_final' => $oversizedFile,
            'jenis_data_final' => 'Ekspor',
        ]);
        $responseOversized->assertStatus(422);
        $this->assertTrue(str_contains(json_encode($responseOversized->json()), 'Ukuran file tidak boleh melebihi 50 MB'));

        // 4. Uji juga rute preview dengan batas 50 MB
        $responsePreviewOversized = $this->actingAs($admin)->postJson('/upload-data/preview', [
            'excel_file_final' => $oversizedFile,
            'jenis_data_final' => 'Ekspor',
        ]);
        $responsePreviewOversized->assertStatus(422);
        $this->assertTrue(str_contains(json_encode($responsePreviewOversized->json()), 'Ukuran file tidak boleh melebihi 50 MB'));
    }

    /**
     * Test fitur ekspor batch data manajemen data (FastExcelExporter & download binary).
     */
    public function test_manajemen_data_export_batch()
    {
        $admin = User::where('role_id', 1)->first();
        if (!$admin) {
            $this->markTestSkipped('Super Admin tidak ditemukan.');
        }

        // Ambil salah satu batch di database
        $batch = DB::table('data_batches')->first();
        if (!$batch) {
            $batchId = DB::table('data_batches')->insertGetId([
                'nama_file' => 'test_export_batch.xlsx',
                'jenis_data' => 'Ekspor',
                'status' => 'RILIS',
                'uploaded_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $batchId = $batch->batch_id;
        }

        $response = $this->actingAs($admin)->get("/manajemen-data/{$batchId}/export");
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Pastikan download content disposition terpasang
        $this->assertTrue($response->headers->has('content-disposition'));
        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));
    }
}
