<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DbfUploadWorkflowIntegrationTest extends TestCase
{

    /**
     * 1. Test Preview endpoint on /upload-data/preview accepts .dbf for Ekspor and returns correct parsed structure.
     */
    public function test_preview_accepts_ekspor_dbf_and_returns_summary(): void
    {
        $admin = User::where('role_id', 1)->first();
        $this->assertNotNull($admin);

        $pathExp = base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2609_POD_based.DBF');
        $this->assertFileExists($pathExp);

        $file = new UploadedFile($pathExp, '14_Atap_ekspor_2609_POD_based.DBF', 'application/octet-stream', null, true);

        $response = $this->actingAs($admin)->postJson(route('upload.preview'), [
            'excel_file_final' => $file,
            'jenis_data_final' => 'Ekspor',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('format', 'DBF');
        $response->assertJsonPath('tahun', 2026);
        $response->assertJsonPath('bulan', 9);
        $response->assertJsonPath('jumlah_baris', 841);
        $this->assertEqualsWithDelta(2301.81, $response->json('total_nilai'), 0.05);
    }

    /**
     * 2. Test Preview endpoint on /upload-data/preview accepts .dbf for Impor and returns correct parsed structure.
     */
    public function test_preview_accepts_impor_dbf_and_returns_summary(): void
    {
        $admin = User::where('role_id', 1)->first();
        $this->assertNotNull($admin);

        $pathImp = base_path('referensi_bps/impor_distribusi/14_impor_atap_kdprov_0926.DBF');
        $this->assertFileExists($pathImp);

        $file = new UploadedFile($pathImp, '14_impor_atap_kdprov_0926.DBF', 'application/octet-stream', null, true);

        $response = $this->actingAs($admin)->postJson(route('upload.preview'), [
            'excel_file_final' => $file,
            'jenis_data_final' => 'Impor',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('format', 'DBF');
        $response->assertJsonPath('tahun', 2026);
        $response->assertJsonPath('bulan', 9);
        $response->assertJsonPath('jumlah_baris', 931);
        $this->assertEqualsWithDelta(215.26, $response->json('total_nilai'), 0.05);
    }

    /**
     * 3. Test duplicate check prevents duplicate batch unless overwrite is specified.
     */
    public function test_duplicate_batch_is_prevented_by_default(): void
    {
        $admin = User::where('role_id', 1)->first();
        $this->assertNotNull($admin);

        $pathExp = base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2609_POD_based.DBF');
        $file = new UploadedFile($pathExp, '14_Atap_ekspor_2609_POD_based.DBF', 'application/octet-stream', null, true);

        // Ekspor September 2026 sudah ada di database, percobaan simpan tanpa overwrite harus ditolak
        $response = $this->actingAs($admin)->postJson(route('upload.process'), [
            'excel_file_final' => $file,
            'jenis_data_final' => 'Ekspor',
            'status_final' => 'PRIVATE',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('sudah pernah tersimpan', $response->json('message'));
    }
}
