<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Phase2gPrivateUploadStorageTest extends TestCase
{
    /** @var int[] */
    private array $createdBatchIds = [];

    /** @var string[] */
    private array $tempFiles = [];

    private int $maxLogIdBefore = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maxLogIdBefore = (int) (DB::table('activity_logs')->max('log_id') ?? 0);
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdBatchIds)) {
            DB::table('trash')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('distribusi_transactions')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('data_batches')->whereIn('batch_id', $this->createdBatchIds)->delete();
        }

        DB::table('activity_logs')->where('log_id', '>', $this->maxLogIdBefore)->delete();

        foreach ($this->tempFiles as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }

        parent::tearDown();
    }

    private function buildMinimalXlsx(string $hsCode, string $negaraCode, int $tahun = 2094, int $bulan = 5): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ekspor');
        $sheet->fromArray(
            ['TAHUN', 'BULAN', 'HS', 'KODE_NGR', 'PROVORIG', 'NETTO', 'FOB'],
            null,
            'A1'
        );
        $sheet->fromArray(
            [$tahun, $bulan, $hsCode, $negaraCode, '14', '250.50', '15000.75'],
            null,
            'A2'
        );

        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p2g_test_' . uniqid() . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpPath);
        $this->tempFiles[] = $tmpPath;

        return $tmpPath;
    }

    public function test_excel_upload_stores_file_on_local_private_disk_not_public(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::where('role_id', 1)->firstOrFail();
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();

        $xlsxPath = $this->buildMinimalXlsx((string) $hs->hs_code, (string) $negara->kode_negara);
        $uploaded = new UploadedFile(
            $xlsxPath,
            'p2g_private_upload.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($admin)->postJson(route('upload.process'), [
            'excel_file_final' => $uploaded,
            'jenis_data_final' => 'Ekspor',
            'status_final' => 'PRIVATE',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $batchId = (int) $response->json('data.batch_id');
        $this->assertGreaterThan(0, $batchId);
        $this->createdBatchIds[] = $batchId;

        // File MUST exist on 'local' private disk and NEVER on 'public' disk
        $localFiles = Storage::disk('local')->allFiles('uploads/excel');
        $publicFiles = Storage::disk('public')->allFiles();

        $this->assertCount(1, $localFiles);
        $this->assertCount(0, $publicFiles);
        $this->assertStringEndsWith('_p2g_private_upload.xlsx', $localFiles[0]);
    }

    public function test_excel_upload_rollback_deletes_private_file_without_leaving_orphans(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::where('role_id', 1)->firstOrFail();
        $negara = DB::table('master_negara')->first();
        $batchesBefore = DB::table('data_batches')->count();

        // HS code '99999999' does not exist in master_hs -> triggers parse error & DB rollback
        $xlsxPath = $this->buildMinimalXlsx('99999999', (string) $negara->kode_negara);
        $uploaded = new UploadedFile(
            $xlsxPath,
            'p2g_bad_hs.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($admin)->postJson(route('upload.process'), [
            'excel_file_final' => $uploaded,
            'jenis_data_final' => 'Ekspor',
            'status_final' => 'PRIVATE',
        ]);

        $response->assertStatus(500);
        $this->assertSame($batchesBefore, DB::table('data_batches')->count());
        $this->assertCount(0, Storage::disk('local')->allFiles('uploads/excel'));
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_disguised_non_excel_file_is_rejected_by_mime_validation(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::where('role_id', 1)->firstOrFail();
        $fakePhpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p2g_fake_' . uniqid() . '.xlsx';
        file_put_contents($fakePhpPath, "<?php echo 'not excel';");
        $this->tempFiles[] = $fakePhpPath;

        $uploaded = new UploadedFile(
            $fakePhpPath,
            'evil.xlsx',
            'text/plain',
            null,
            true
        );

        $response = $this->actingAs($admin)->postJson(route('upload.process'), [
            'excel_file_final' => $uploaded,
            'jenis_data_final' => 'Ekspor',
        ]);

        $response->assertStatus(422);
        $this->assertCount(0, Storage::disk('local')->allFiles('uploads/excel'));
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_update_rejects_path_traversal_in_nama_file(): void
    {
        $admin = User::where('role_id', 1)->firstOrFail();
        $batchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'p2g_safe_name.xlsx',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'status' => 'PRIVATE',
            'uploaded_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $batchId;

        foreach (['../../../../../.env', 'subdir/evil.xlsx', 'subdir\\evil.xlsx', 'file..name.xlsx'] as $payload) {
            $res = $this->actingAs($admin)->postJson(route('manajemen-data.update', ['batchId' => $batchId]), [
                'nama_file' => $payload,
                'jenis_data' => 'Ekspor',
            ]);
            $res->assertStatus(422);
        }

        $this->assertSame('p2g_safe_name.xlsx', DB::table('data_batches')->where('batch_id', $batchId)->value('nama_file'));
    }

    public function test_force_delete_blocks_non_trashed_and_prevents_path_traversal_deletion(): void
    {
        Storage::fake('local');

        $admin = User::where('role_id', 1)->firstOrFail();

        // 1. Active (non-trashed) batch cannot be force-deleted
        $activeBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'p2g_active_batch.xlsx',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'status' => 'PRIVATE',
            'uploaded_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $activeBatchId;

        $this->actingAs($admin)
            ->postJson(route('manajemen-data.force-delete', ['batchId' => $activeBatchId]))
            ->assertStatus(422);
        $this->assertTrue(DB::table('data_batches')->where('batch_id', $activeBatchId)->exists());

        // 2. Sentinel file outside uploads/ MUST survive tampered traversal nama_file on forceDelete
        Storage::disk('local')->put('sentinel_outside_uploads.txt', 'DO NOT DELETE');
        $sentinelFullPath = Storage::disk('local')->path('sentinel_outside_uploads.txt');
        $this->assertFileExists($sentinelFullPath);

        $tamperedBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => '../../sentinel_outside_uploads.txt',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'status' => 'PRIVATE',
            'uploaded_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $tamperedBatchId;

        DB::table('trash')->insert([
            'batch_id' => $tamperedBatchId,
            'data_snapshot' => json_encode(['batch_id' => $tamperedBatchId]),
            'deleted_by' => $admin->id,
            'deleted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->postJson(route('manajemen-data.force-delete', ['batchId' => $tamperedBatchId]))
            ->assertStatus(200);

        // Sentinel outside uploads/ MUST STILL EXIST!
        $this->assertFileExists($sentinelFullPath);

        // 3. Valid trashed batch with private archive file IS safely deleted from local disk
        Storage::disk('local')->put('uploads/excel/p2g_valid_archive.xlsx', 'dummy');
        $validBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'p2g_valid_archive.xlsx',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'status' => 'PRIVATE',
            'uploaded_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $validBatchId;

        DB::table('trash')->insert([
            'batch_id' => $validBatchId,
            'data_snapshot' => json_encode(['batch_id' => $validBatchId]),
            'deleted_by' => $admin->id,
            'deleted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->postJson(route('manajemen-data.force-delete', ['batchId' => $validBatchId]))
            ->assertStatus(200);

        $this->assertFalse(Storage::disk('local')->exists('uploads/excel/p2g_valid_archive.xlsx'));
    }
}
