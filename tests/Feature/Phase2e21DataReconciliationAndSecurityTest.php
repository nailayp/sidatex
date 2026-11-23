<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BrsAggregationService;
use App\Services\BrsDataProcessingService;
use App\Services\DbfReaderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test Suite Phase 2E.2.1 — Final UI Verification & Data Reconciliation.
 * 
 * Memvalidasi:
 * 1. Rekonsiliasi 4 kombinasi DBF (Preview vs DBF vs Storage vs Mart)
 * 2. Integritas Two-Tier Data Mart (Tier-1 & Tier-2)
 * 3. Penegakan hak akses peran (Super Admin, Pegawai, Viewer Eksekutif)
 * 4. Pencegahan duplikasi, rollback atomik, dan idempoten agregasi
 */
class Phase2e21DataReconciliationAndSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $dbName = DB::connection()->getDatabaseName();
        $this->assertContains(
            $dbName,
            ['sidatex_testing', ':memory:'],
            "CRITICAL: Pengujian harus berjalan strictly di database sidatex_testing atau SQLite ':memory:'!"
        );
    }

    protected function getTestUser(int $roleId): User
    {
        $user = User::where('role_id', $roleId)->first();
        if (!$user) {
            $user = User::factory()->make(['role_id' => $roleId]);
        }
        return $user;
    }

    /**
     * 1. Rekonsiliasi Kombinasi 1: Ekspor Distribusi (Agustus 2026).
     */
    public function test_reconciliation_ekspor_distribusi(): void
    {
        $superAdmin = $this->getTestUser(1);
        $path = base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2608_POD_based.DBF');
        $this->assertFileExists($path);

        $file = new UploadedFile($path, '14_Atap_ekspor_2608_POD_based.DBF', 'application/octet-stream', null, true);

        $response = $this->actingAs($superAdmin)->postJson(route('upload.preview'), [
            'excel_file_final' => $file,
            'jenis_data_final' => 'Ekspor',
            'sumber_data_final' => 'Distribusi',
        ]);

        $response->assertStatus(200);
        $res = $response->json();

        $this->assertTrue($res['success']);
        $this->assertEquals('distribusi', $res['sumber_data']);
        $this->assertEquals('Ekspor', $res['jenis_data']);
        $this->assertEquals(2026, $res['tahun']);
        $this->assertEquals(8, $res['bulan']);
        $this->assertEquals(809, $res['jumlah_baris']);

        // Data Enrichment & BRS
        $data = $res['data'];
        $this->assertEquals(809, $data['baris_valid']);
        $this->assertEquals(0, $data['baris_invalid']);
        $this->assertEqualsWithDelta(2218.16, $data['total_nilai_juta_usd'], 0.05);
        $this->assertEqualsWithDelta(2352.08, $data['total_berat_ribu_ton'], 0.05);
        $this->assertEqualsWithDelta(49.52, $data['migas_nilai_usd'] / 1000000, 0.05);
        $this->assertEqualsWithDelta(2168.64, $data['non_migas_nilai_usd'] / 1000000, 0.05);
        $this->assertEquals(0, $data['unmapped']['count']);
    }

    /**
     * 2. Rekonsiliasi Kombinasi 2: Impor Distribusi (September 2026).
     */
    public function test_reconciliation_impor_distribusi(): void
    {
        $superAdmin = $this->getTestUser(1);
        $path = base_path('referensi_bps/impor_distribusi/14_impor_atap_kdprov_0926.DBF');
        $this->assertFileExists($path);

        $file = new UploadedFile($path, '14_impor_atap_kdprov_0926.DBF', 'application/octet-stream', null, true);

        $response = $this->actingAs($superAdmin)->postJson(route('upload.preview'), [
            'excel_file_final' => $file,
            'jenis_data_final' => 'Impor',
            'sumber_data_final' => 'Distribusi',
        ]);

        $response->assertStatus(200);
        $res = $response->json();

        $this->assertTrue($res['success']);
        $this->assertEquals('distribusi', $res['sumber_data']);
        $this->assertEquals('Impor', $res['jenis_data']);
        $this->assertEquals(2026, $res['tahun']);
        $this->assertEquals(9, $res['bulan']);
        $this->assertEquals(931, $res['jumlah_baris']);

        $data = $res['data'];
        $this->assertEquals(931, $data['baris_valid']);
        $this->assertEquals(0, $data['baris_invalid']);
        $this->assertEqualsWithDelta(215.26, $data['total_nilai_juta_usd'], 0.05);
        $this->assertEqualsWithDelta(578.15, $data['total_berat_ribu_ton'], 0.05);
        $this->assertEqualsWithDelta(9.41, $data['migas_nilai_usd'] / 1000000, 0.05);
        $this->assertEqualsWithDelta(205.86, $data['non_migas_nilai_usd'] / 1000000, 0.05);
        $this->assertEquals(0, $data['unmapped']['count']);

        // Verifikasi BEC breakdown
        $this->assertArrayHasKey('bec_summary', $data);
        $this->assertArrayHasKey('Bahan Baku/Penolong', $data['bec_summary']);
    }

    /**
     * 3. Rekonsiliasi Kombinasi 3: Ekspor Neraca (Juli 2026).
     */
    public function test_reconciliation_ekspor_neraca(): void
    {
        $superAdmin = $this->getTestUser(1);
        $path = base_path('referensi_bps/ekspor_neraca/14_Atap_ekspor_2607_PROVORIG_based.DBF');
        $this->assertFileExists($path);

        $file = new UploadedFile($path, '14_Atap_ekspor_2607_PROVORIG_based.DBF', 'application/octet-stream', null, true);

        $response = $this->actingAs($superAdmin)->postJson(route('upload.preview'), [
            'excel_file_final' => $file,
            'jenis_data_final' => 'Ekspor',
            'sumber_data_final' => 'Neraca',
        ]);

        $response->assertStatus(200);
        $res = $response->json();

        $this->assertTrue($res['success']);
        $this->assertEquals('neraca', $res['sumber_data']);
        $this->assertEquals('Ekspor', $res['jenis_data']);
        $this->assertEquals(2026, $res['tahun']);
        $this->assertEquals(7, $res['bulan']);
        $this->assertEquals(960, $res['jumlah_baris']);

        $data = $res['data'];
        $this->assertEquals(960, $data['baris_valid']);
        $this->assertEquals(0, $data['baris_invalid']);
        $this->assertEqualsWithDelta(1922.36, $data['total_nilai_juta_usd'], 0.05);
        $this->assertEqualsWithDelta(2226.79, $data['total_berat_ribu_ton'], 0.05);
        $this->assertEqualsWithDelta(36.81, $data['migas_nilai_usd'] / 1000000, 0.05);
        $this->assertEqualsWithDelta(1885.55, $data['non_migas_nilai_usd'] / 1000000, 0.05);
        $this->assertEquals(0, $data['unmapped']['count']);
    }

    /**
     * 4. Rekonsiliasi Kombinasi 4: Impor Neraca (September 2026).
     */
    public function test_reconciliation_impor_neraca(): void
    {
        $superAdmin = $this->getTestUser(1);
        $path = base_path('referensi_bps/impor_neraca/14_impor_atap_kppbc_0926.DBF');
        $this->assertFileExists($path);

        $file = new UploadedFile($path, '14_impor_atap_kppbc_0926.DBF', 'application/octet-stream', null, true);

        $response = $this->actingAs($superAdmin)->postJson(route('upload.preview'), [
            'excel_file_final' => $file,
            'jenis_data_final' => 'Impor',
            'sumber_data_final' => 'Neraca',
        ]);

        $response->assertStatus(200);
        $res = $response->json();

        $this->assertTrue($res['success']);
        $this->assertEquals('neraca', $res['sumber_data']);
        $this->assertEquals('Impor', $res['jenis_data']);
        $this->assertEquals(2026, $res['tahun']);
        $this->assertEquals(9, $res['bulan']);
        $this->assertEquals(1167, $res['jumlah_baris']);

        $data = $res['data'];
        $this->assertEquals(1167, $data['baris_valid']);
        $this->assertEquals(0, $data['baris_invalid']);
        $this->assertEqualsWithDelta(223.76, $data['total_nilai_juta_usd'], 0.05);
        $this->assertEqualsWithDelta(584.50, $data['total_berat_ribu_ton'], 0.05);
        $this->assertEqualsWithDelta(9.41, $data['migas_nilai_usd'] / 1000000, 0.05);
        $this->assertEqualsWithDelta(214.36, $data['non_migas_nilai_usd'] / 1000000, 0.05);
        $this->assertEquals(0, $data['unmapped']['count']);
    }

    protected function cloneDbfWithSentinelYear(string $sourcePath, int $sentinelYear): string
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
     * 5. Uji Penyimpanan dan Rekonsiliasi Data Mart (Tier-1 & Tier-2).
     */
    public function test_storage_and_mart_reconciliation(): void
    {
        $superAdmin = $this->getTestUser(1);
        $sourcePath = base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2608_POD_based.DBF');
        $isolatedPath = $this->cloneDbfWithSentinelYear($sourcePath, 2098);
        $file = new UploadedFile($isolatedPath, '14_Atap_ekspor_9808_POD_based.DBF', 'application/octet-stream', null, true);

        $archiveRelPath = null;
        DB::beginTransaction();

        try {
            $response = $this->actingAs($superAdmin)->postJson(route('upload.process'), [
                'excel_file_final' => $file,
                'jenis_data_final' => 'Ekspor',
                'sumber_data_final' => 'Distribusi',
                'status_final' => 'RILIS',
            ]);

            $response->assertStatus(200);
            $res = $response->json();
            $this->assertTrue($res['success']);

            $batchId = $res['data']['batch_id'];
            $this->assertNotNull($batchId);
            $storedFilename = DB::table('data_batches')->where('batch_id', $batchId)->value('nama_file');
            if ($storedFilename) {
                $archiveRelPath = 'uploads/raw_distribusi/' . basename((string) $storedFilename);
            }

            $this->assertEquals('RILIS', $res['data']['status']);
            $this->assertEquals(809, $res['data']['total_transactions']);

            // 1. Verifikasi tabel distribusi_transactions
            $txCount = DB::table('distribusi_transactions')->where('batch_id', $batchId)->count();
            $this->assertEquals(809, $txCount, "Jumlah transaksi tersimpan harus presisi 809 baris (0 dropped rows).");

            $sumTx = DB::table('distribusi_transactions')
                ->where('batch_id', $batchId)
                ->selectRaw('SUM(nilai) as total_val, SUM(berat) as total_wgt')
                ->first();

            $this->assertEqualsWithDelta(2218155995.50, (float)$sumTx->total_val, 1.0);
            $this->assertEqualsWithDelta(2352083988.62, (float)$sumTx->total_wgt, 1.0);

            // 2. Verifikasi Tier-1: monthly_trade_summaries
            $tier1Rows = DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->get();
            $this->assertNotEmpty($tier1Rows, "Tier-1 Mart harus terisi secara otomatis.");

            $tier1Sum = DB::table('monthly_trade_summaries')
                ->where('batch_id', $batchId)
                ->selectRaw('SUM(total_nilai) as val, SUM(total_berat) as wgt')
                ->first();

            $this->assertEqualsWithDelta(2218155995.50, (float)$tier1Sum->val, 5.0, "Total nilai Tier-1 harus konsisten dengan transaksi.");
            $this->assertEqualsWithDelta(2352083988.62, (float)$tier1Sum->wgt, 5.0, "Total berat Tier-1 harus konsisten dengan transaksi.");

            // 3. Verifikasi Tier-2: brs_indicator_summaries
            $tier2Rows = DB::table('brs_indicator_summaries')->where('batch_id', $batchId)->get();
            $this->assertNotEmpty($tier2Rows, "Tier-2 Mart harus terisi secara otomatis.");

            // Pastikan ada dimensi TOTAL, SEKTOR, HS2_TOP10, NEGARA_TOP10, PELABUHAN
            $categories = $tier2Rows->pluck('kategori_dimensi')->unique()->toArray();
            $this->assertContains('TOTAL', $categories);
            $this->assertContains('SEKTOR', $categories);
            $this->assertContains('HS2_TOP10', $categories);
            $this->assertContains('NEGARA_TOP10', $categories);
            $this->assertContains('PELABUHAN', $categories);

            // Verifikasi nilai total makro pada Tier-2
            $totalInd = $tier2Rows->firstWhere('kategori_dimensi', 'TOTAL');
            $this->assertNotNull($totalInd);
            $this->assertEquals(2218.16, (float)$totalInd->nilai_juta_usd);
            $this->assertEquals(2352.08, (float)$totalInd->volume_ribu_ton);

        } finally {
            DB::rollBack();
            if ($archiveRelPath && Storage::disk('local')->exists($archiveRelPath)) {
                Storage::disk('local')->delete($archiveRelPath);
            }
            if (file_exists($isolatedPath)) {
                @unlink($isolatedPath);
            }
        }
    }

    /**
     * 6. Pengujian Hak Akses: Pegawai ditolak upload & tidak bisa akses endpoint upload.
     */
    public function test_role_pegawai_cannot_access_upload_endpoints(): void
    {
        $pegawai = $this->getTestUser(2);
        $this->assertNotNull($pegawai);

        // GET /upload-data -> 403
        $resGet = $this->actingAs($pegawai)->get(route('upload-data'));
        $resGet->assertStatus(403);

        // POST /upload-data/preview -> 403
        $resPrev = $this->actingAs($pegawai)->postJson(route('upload.preview'), []);
        $resPrev->assertStatus(403);

        // POST /upload-data/process -> 403
        $resProc = $this->actingAs($pegawai)->postJson(route('upload.process'), []);
        $resProc->assertStatus(403);
    }

    /**
     * 7. Pengujian Hak Akses: Viewer Eksekutif ditolak upload tetapi dapat melihat data.
     */
    public function test_role_viewer_eksekutif_access_rules(): void
    {
        $viewer = $this->getTestUser(3);
        $this->assertNotNull($viewer);

        // Viewer ditolak dari modul upload
        $resGet = $this->actingAs($viewer)->get(route('upload-data'));
        $resGet->assertStatus(403);

        $resPrev = $this->actingAs($viewer)->postJson(route('upload.preview'), []);
        $resPrev->assertStatus(403);

        $resProc = $this->actingAs($viewer)->postJson(route('upload.process'), []);
        $resProc->assertStatus(403);

        // Namun Viewer diizinkan mengakses dashboard (termasuk status PRIVATE)
        $resDash = $this->actingAs($viewer)->get(route('dashboard.default'));
        $resDash->assertStatus(200);
    }

    /**
     * 8. Pengujian Hak Akses: Pegawai tidak dapat mengakses data PRIVATE pada query filter.
     */
    public function test_pegawai_is_strictly_restricted_from_private_data(): void
    {
        $pegawai = $this->getTestUser(2);

        // Jalankan query filtering via controller
        $filteringCtrl = app(\App\Http\Controllers\FilteringController::class);
        $request = \Illuminate\Http\Request::create('/filtering/data', 'GET', [
            'status' => 'PRIVATE', // Pegawai mencoba memfilter data PRIVATE
        ]);
        $request->setUserResolver(fn() => $pegawai);

        // Eksekusi filter
        $response = $filteringCtrl->query($request);
        $data = json_decode($response->getContent(), true);

        // Verifikasi bahwa Pegawai HANYA mendapatkan data RILIS (tidak pernah PRIVATE)
        if (!empty($data['data'])) {
            foreach ($data['data'] as $row) {
                if (isset($row['batch_status'])) {
                    $this->assertEquals('RILIS', $row['batch_status'], "Pegawai dilarang keras menerima baris berstatus PRIVATE!");
                }
            }
        }
        $this->assertTrue(true);
    }

    /**
     * 9. Uji Duplikasi: Percobaan upload periode yang sudah ada wajib ditolak (HTTP 422).
     */
    public function test_duplicate_upload_is_strictly_rejected(): void
    {
        $superAdmin = $this->getTestUser(1);
        $path = base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2609_POD_based.DBF');
        $file = new UploadedFile($path, '14_Atap_ekspor_2609_POD_based.DBF', 'application/octet-stream', null, true);

        // Ekspor Distribusi September 2026 sudah ada (Batch #71)
        $response = $this->actingAs($superAdmin)->postJson(route('upload.process'), [
            'excel_file_final' => $file,
            'jenis_data_final' => 'Ekspor',
            'sumber_data_final' => 'Distribusi',
            'status_final' => 'PRIVATE',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('sudah pernah tersimpan', $response->json('message'));
    }

    /**
     * 10. Uji Atomisitas & Rollback: Kegagalan tidak meninggalkan data transaksi yatim (orphaned).
     */
    public function test_atomic_rollback_on_failure_leaves_no_orphaned_records(): void
    {
        $superAdmin = $this->getTestUser(1);
        $initialBatches = DB::table('data_batches')->count();
        $initialDistribusi = DB::table('distribusi_transactions')->count();
        $initialNeraca = DB::table('neraca_transactions')->count();

        // Coba kirim berkas kosong / corrupt
        $tempPath = tempnam(sys_get_temp_dir(), 'corrupt_') . '.dbf';
        file_put_contents($tempPath, "BOGUS_DATA_NOT_A_VALID_DBF_FILE");

        $corruptFile = new UploadedFile($tempPath, 'corrupt.dbf', 'application/octet-stream', null, true);

        $response = $this->actingAs($superAdmin)->postJson(route('upload.process'), [
            'excel_file_final' => $corruptFile,
            'jenis_data_final' => 'Ekspor',
            'sumber_data_final' => 'Distribusi',
            'status_final' => 'PRIVATE',
        ]);

        $response->assertStatus(422);

        // Verifikasi tidak ada penambahan record sama sekali
        $this->assertEquals($initialBatches, DB::table('data_batches')->count(), "Jumlah batch tidak boleh bertambah jika gagal.");
        $this->assertEquals($initialDistribusi, DB::table('distribusi_transactions')->count(), "Jumlah transaksi distribusi tidak boleh bertambah.");
        $this->assertEquals($initialNeraca, DB::table('neraca_transactions')->count(), "Jumlah transaksi neraca tidak boleh bertambah.");

        @unlink($tempPath);
    }

    /**
     * 11. Uji Idempotensi Agregasi: Agregasi ulang batch yang sama tidak menggandakan summary.
     */
    public function test_reaggregation_is_idempotent(): void
    {
        $superAdmin = $this->getTestUser(1);
        $sourcePath = base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2608_POD_based.DBF');
        $isolatedPath = $this->cloneDbfWithSentinelYear($sourcePath, 2099);

        $archiveRelPath = null;
        DB::beginTransaction();

        try {
            $svc = app(BrsDataProcessingService::class);
            $aggSvc = app(BrsAggregationService::class);

            $result = $svc->storeBatchAndTransactions(
                $isolatedPath,
                '14_Atap_ekspor_9908_POD_based.DBF',
                'distribusi',
                'ekspor',
                $superAdmin->id,
                'PRIVATE'
            );

            $batchId = $result['batch_id'];
            $archiveRelPath = $result['arsip_file'] ?? null;

            $tier1CountInitial = DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->count();
            $tier2CountInitial = DB::table('brs_indicator_summaries')->where('batch_id', $batchId)->count();

            $this->assertGreaterThan(0, $tier1CountInitial);
            $this->assertGreaterThan(0, $tier2CountInitial);

            // Jalankan agregasi ulang untuk batch yang sama
            $aggSvc->aggregateBatch($batchId, 'PRIVATE');

            $tier1CountAfter = DB::table('monthly_trade_summaries')->where('batch_id', $batchId)->count();
            $tier2CountAfter = DB::table('brs_indicator_summaries')->where('batch_id', $batchId)->count();

            // Verifikasi jumlah baris ringkasan TIDAK berlipat ganda
            $this->assertEquals($tier1CountInitial, $tier1CountAfter, "Agregasi ulang tidak boleh menggandakan baris Tier-1.");
            $this->assertEquals($tier2CountInitial, $tier2CountAfter, "Agregasi ulang tidak boleh menggandakan baris Tier-2.");
        } finally {
            DB::rollBack();
            if ($archiveRelPath && Storage::disk('local')->exists($archiveRelPath)) {
                Storage::disk('local')->delete($archiveRelPath);
            }
            if (file_exists($isolatedPath)) {
                @unlink($isolatedPath);
            }
        }
    }
}
