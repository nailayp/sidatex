<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Test Suite Integrasi UI Upload dan Preview Data Neraca (Tahap 2R).
 */
class NeracaUploadUiIntegrationTest extends TestCase
{
    protected function getTestUser(int $roleId): User
    {
        $user = User::where('role_id', $roleId)->first();
        if (!$user) {
            $user = User::factory()->make(['role_id' => $roleId]);
        }
        return $user;
    }

    /**
     * 1. Akses upload oleh Super Admin (role_id: 1) diizinkan (200 OK).
     */
    public function test_superadmin_can_access_neraca_upload_page(): void
    {
        $superAdmin = $this->getTestUser(1);

        $response = $this->actingAs($superAdmin)->get(route('neraca.upload'));
        $response->assertStatus(200);
        $response->assertSee('MODE PRATINJAU (READ-ONLY)');
        $response->assertSee('Pilih Berkas');
        $response->assertSee('Fitur Ingestion');
    }

    /**
     * 2. Penolakan akses Pegawai (role_id: 2) -> 403 Forbidden.
     */
    public function test_pegawai_cannot_access_neraca_upload(): void
    {
        $pegawai = $this->getTestUser(2);

        $response = $this->actingAs($pegawai)->get(route('neraca.upload'));
        $response->assertStatus(403);

        $postRes = $this->actingAs($pegawai)->post(route('neraca.upload.preview'), []);
        $postRes->assertStatus(403);

        $rowsRes = $this->actingAs($pegawai)->get(route('neraca.upload.rows'));
        $rowsRes->assertStatus(403);
    }

    /**
     * 3. Penolakan akses Viewer Eksekutif (role_id: 3) -> 403 Forbidden.
     */
    public function test_viewer_eksekutif_cannot_access_neraca_upload(): void
    {
        $viewer = $this->getTestUser(3);

        $response = $this->actingAs($viewer)->get(route('neraca.upload'));
        $response->assertStatus(403);

        $postRes = $this->actingAs($viewer)->post(route('neraca.upload.preview'), []);
        $postRes->assertStatus(403);

        $rowsRes = $this->actingAs($viewer)->get(route('neraca.upload.rows'));
        $rowsRes->assertStatus(403);
    }

    /**
     * 4. Upload & Preview File Ekspor BRS Asli.
     */
    public function test_superadmin_can_preview_real_ekspor_file(): void
    {
        $superAdmin = $this->getTestUser(1);
        $realFile = base_path('referensi_bps/1. (Olah Data BRS) Ekspor LN Riau.xlsx');
        $this->assertFileExists($realFile);

        $uploadedFile = new UploadedFile(
            $realFile,
            '1. (Olah Data BRS) Ekspor LN Riau.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($superAdmin)->post(route('neraca.upload.preview'), [
            'file' => $uploadedFile,
            'source_type' => 'ekspor',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'preview' => [
                'source_type' => 'ekspor',
                'worksheet' => 'RawDataEkspor',
                'valid_rows_count' => 53066,
                'totals' => [
                    'total_nilai' => '127069371387.58',
                    'total_berat_kg' => '172767369262.555',
                ],
                'dimensions' => [
                    'unique_hs_count' => 666,
                    'unique_country_count' => 174,
                    'unique_kabkota_count' => 8,
                ],
            ],
        ]);

        // Verifikasi Mapping Warning 27 HS & Normalisasi Meranti
        $data = $response->json();
        $warnings = $data['preview']['dimensions']['mapping_warnings'];
        $this->assertEquals(27, $warnings['unregistered_hs_count']);
        $this->assertCount(27, $warnings['unregistered_hs']);
        $this->assertEquals('Perlu Ditinjau', $warnings['status_indicator']);
        $this->assertNotEmpty($warnings['kabkota_normalization']);
    }

    /**
     * 5. Upload & Preview File Impor BRS Asli.
     */
    public function test_superadmin_can_preview_real_impor_file(): void
    {
        $superAdmin = $this->getTestUser(1);
        $realFile = base_path('referensi_bps/2. (Olah Data BRS) Impor LN Riau.xlsx');
        $this->assertFileExists($realFile);

        $uploadedFile = new UploadedFile(
            $realFile,
            '2. (Olah Data BRS) Impor LN Riau.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($superAdmin)->post(route('neraca.upload.preview'), [
            'file' => $uploadedFile,
            'source_type' => 'impor',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'preview' => [
                'source_type' => 'impor',
                'worksheet' => 'RawDataImpor',
                'valid_rows_count' => 92820,
            ],
        ]);
    }

    /**
     * 6. Validasi penolakan file rusak atau format tidak valid (.txt / corrupted).
     */
    public function test_preview_rejects_invalid_file_format(): void
    {
        $superAdmin = $this->getTestUser(1);

        // Ekstensi salah (.txt)
        $txtFile = UploadedFile::fake()->create('dokumen.txt', 100);
        $resTxt = $this->actingAs($superAdmin)->post(route('neraca.upload.preview'), [
            'file' => $txtFile,
            'source_type' => 'ekspor',
        ]);
        $resTxt->assertStatus(422);

        // Ekstensi .xlsx tapi bukan berkas ZIP valid
        $fakeXlsx = UploadedFile::fake()->createWithContent('rusak.xlsx', 'Bukan format zip excel yang valid');
        $resCorrupt = $this->actingAs($superAdmin)->post(route('neraca.upload.preview'), [
            'file' => $fakeXlsx,
            'source_type' => 'ekspor',
        ]);
        $resCorrupt->assertStatus(422);
    }

    /**
     * 7. Pagination preview data baris transaksi.
     */
    public function test_paginated_preview_returns_correct_slice(): void
    {
        $superAdmin = $this->getTestUser(1);
        $realFile = base_path('referensi_bps/1. (Olah Data BRS) Ekspor LN Riau.xlsx');

        // Lakukan preview awal untuk mengisi sesi
        $uploadedFile = new UploadedFile(
            $realFile,
            '1. (Olah Data BRS) Ekspor LN Riau.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
        $this->actingAs($superAdmin)->post(route('neraca.upload.preview'), [
            'file' => $uploadedFile,
            'source_type' => 'ekspor',
        ]);

        // Minta halaman 1
        $resPage1 = $this->actingAs($superAdmin)->get(route('neraca.upload.rows', ['page' => 1, 'per_page' => 10]));
        $resPage1->assertStatus(200);
        $resPage1->assertJson([
            'success' => true,
            'pagination' => [
                'current_page' => 1,
                'per_page' => 10,
                'total' => 53066,
                'from' => 1,
                'to' => 10,
            ],
        ]);
        $this->assertCount(10, $resPage1->json('pagination.data'));

        // Minta halaman 2
        $resPage2 = $this->actingAs($superAdmin)->get(route('neraca.upload.rows', ['page' => 2, 'per_page' => 10]));
        $resPage2->assertStatus(200);
        $resPage2->assertJson([
            'success' => true,
            'pagination' => [
                'current_page' => 2,
                'from' => 11,
                'to' => 20,
            ],
        ]);
    }

    /**
     * 8. Jaminan tidak ada data yang disimpan permanen ke database operasional/testing.
     */
    public function test_no_permanent_transactions_inserted_during_upload_and_preview(): void
    {
        $superAdmin = $this->getTestUser(1);
        $realFile = base_path('referensi_bps/1. (Olah Data BRS) Ekspor LN Riau.xlsx');

        $txCountBefore = DB::table('transactions')->count();
        $batchCountBefore = DB::table('data_batches')->count();

        $uploadedFile = new UploadedFile(
            $realFile,
            '1. (Olah Data BRS) Ekspor LN Riau.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($superAdmin)->post(route('neraca.upload.preview'), [
            'file' => $uploadedFile,
            'source_type' => 'ekspor',
        ]);

        $this->actingAs($superAdmin)->get(route('neraca.upload.rows', ['page' => 1, 'per_page' => 20]));

        $txCountAfter = DB::table('transactions')->count();
        $batchCountAfter = DB::table('data_batches')->count();

        // 100% tidak ada baris yang tersimpan
        $this->assertEquals($txCountBefore, $txCountAfter);
        $this->assertEquals($batchCountBefore, $batchCountAfter);
    }
}
