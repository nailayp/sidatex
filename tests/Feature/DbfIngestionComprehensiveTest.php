<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DbfIngestionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DbfIngestionComprehensiveTest extends TestCase
{
    /**
     * 1. Test Ingestion Distribusi Ekspor: Masuk ke `distribusi_transactions` saja.
     */
    public function test_distribusi_ekspor_ingestion_inserts_to_distribusi_transactions_only(): void
    {
        $admin = User::where('role_id', 1)->first();
        $this->assertNotNull($admin);

        $pathExp = base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2609_POD_based.DBF');
        $this->assertFileExists($pathExp);

        $permFile = null;
        DB::beginTransaction();
        try {
            // Hapus sementara batch eksisting untuk periode 9/2026 agar ingestion dapat diuji murni
            DB::table('distribusi_transactions')->whereIn('batch_id', function ($q) {
                $q->select('batch_id')->from('data_batches')->where('tipe_sumber', 'distribusi')->where('periode_tahun', 2026)->where('periode_bulan', 9)->where('jenis_data', 'Ekspor');
            })->delete();
            DB::table('data_batches')->where('tipe_sumber', 'distribusi')->where('periode_tahun', 2026)->where('periode_bulan', 9)->where('jenis_data', 'Ekspor')->delete();

            $neracaBefore = DB::table('neraca_transactions')->count();

            $service = app(DbfIngestionService::class);
            $result = $service->ingest(
                $pathExp,
                '14_Atap_ekspor_2609_POD_based.DBF',
                'distribusi',
                'ekspor',
                $admin->id,
                'PRIVATE',
                true
            );
            $permFile = $result['arsip_file'];

            $this->assertTrue($result['success']);
            $this->assertEquals(841, $result['total_baris']);
            $this->assertEquals('distribusi', $result['sumber_data']);
            $this->assertEquals('ekspor', $result['jenis_transaksi']);

            // Verifikasi tabel fisik
            $neracaAfter = DB::table('neraca_transactions')->count();
            $this->assertEquals($neracaBefore, $neracaAfter, 'Tabel neraca_transactions tidak boleh bertambah saat ingestion Distribusi.');

            $insertedCount = DB::table('distribusi_transactions')->where('batch_id', $result['batch_id'])->count();
            $this->assertEquals(841, $insertedCount, 'Tepat 841 baris harus tersimpan di distribusi_transactions.');

            // Verifikasi status batch
            $batch = DB::table('data_batches')->where('batch_id', $result['batch_id'])->first();
            $this->assertNotNull($batch);
            $this->assertEquals('PRIVATE', $batch->status);
            $this->assertEquals('distribusi', $batch->tipe_sumber);
            $this->assertEquals('Ekspor', $batch->jenis_data);

            // Verifikasi arsip fisik ada di storage local
            $this->assertTrue(Storage::disk('local')->exists($result['arsip_file']));
        } finally {
            if ($permFile && Storage::disk('local')->exists($permFile)) {
                Storage::disk('local')->delete($permFile);
            }
            DB::rollBack();
        }
    }

    /**
     * 2. Test Ingestion Distribusi Impor: Masuk ke `distribusi_transactions` saja.
     */
    public function test_distribusi_impor_ingestion_inserts_to_distribusi_transactions_only(): void
    {
        $admin = User::where('role_id', 1)->first();
        $this->assertNotNull($admin);

        $pathImp = base_path('referensi_bps/impor_distribusi/14_impor_atap_kdprov_0926.DBF');
        $this->assertFileExists($pathImp);

        $permFile = null;
        DB::beginTransaction();
        try {
            DB::table('distribusi_transactions')->whereIn('batch_id', function ($q) {
                $q->select('batch_id')->from('data_batches')->where('tipe_sumber', 'distribusi')->where('periode_tahun', 2026)->where('periode_bulan', 9)->where('jenis_data', 'Impor');
            })->delete();
            DB::table('data_batches')->where('tipe_sumber', 'distribusi')->where('periode_tahun', 2026)->where('periode_bulan', 9)->where('jenis_data', 'Impor')->delete();

            $service = app(DbfIngestionService::class);
            $result = $service->ingest(
                $pathImp,
                '14_impor_atap_kdprov_0926.DBF',
                'distribusi',
                'impor',
                $admin->id,
                'PRIVATE',
                true
            );
            $permFile = $result['arsip_file'];

            $this->assertTrue($result['success']);
            $this->assertEquals(931, $result['total_baris']);
            $this->assertEquals('distribusi', $result['sumber_data']);
            $this->assertEquals('impor', $result['jenis_transaksi']);

            // Verifikasi baris tersimpan
            $count = DB::table('distribusi_transactions')->where('batch_id', $result['batch_id'])->count();
            $this->assertEquals(931, $count);
        } finally {
            if ($permFile && Storage::disk('local')->exists($permFile)) {
                Storage::disk('local')->delete($permFile);
            }
            DB::rollBack();
        }
    }

    /**
     * 3. Test Ingestion Neraca Ekspor: Masuk ke `neraca_transactions` saja dan mendukung unmapped HS.
     */
    public function test_neraca_ekspor_ingestion_inserts_to_neraca_transactions_only(): void
    {
        $admin = User::where('role_id', 1)->first();
        $this->assertNotNull($admin);

        $pathNeracaExp = base_path('referensi_bps/ekspor_neraca/14_Atap_ekspor_2609_PROVORIG_based.DBF');
        if (!file_exists($pathNeracaExp) || !is_readable($pathNeracaExp)) {
            $this->markTestSkipped('File Neraca Ekspor 2609 tidak dapat diakses.');
        }

        $permFile = null;
        DB::beginTransaction();
        try {
            DB::table('neraca_transactions')->whereIn('batch_id', function ($q) {
                $q->select('batch_id')->from('data_batches')->where('tipe_sumber', 'neraca')->where('periode_tahun', 2026)->where('periode_bulan', 9)->where('jenis_data', 'Ekspor');
            })->delete();
            DB::table('data_batches')->where('tipe_sumber', 'neraca')->where('periode_tahun', 2026)->where('periode_bulan', 9)->where('jenis_data', 'Ekspor')->delete();

            $distBefore = DB::table('distribusi_transactions')->count();
            $neracaBefore = DB::table('neraca_transactions')->count();

            $service = app(DbfIngestionService::class);
            $result = $service->ingest(
                $pathNeracaExp,
                '14_Atap_ekspor_2609_PROVORIG_based.DBF',
                'neraca',
                'ekspor',
                $admin->id,
                'PRIVATE',
                true
            );
            $permFile = $result['arsip_file'];

            $this->assertTrue($result['success']);
            $this->assertEquals(950, $result['total_baris']);
            $this->assertEquals('neraca', $result['sumber_data']);
            $this->assertEquals('ekspor', $result['jenis_transaksi']);

            $distAfter = DB::table('distribusi_transactions')->count();
            $this->assertEquals($distBefore, $distAfter, 'Tabel distribusi_transactions tidak boleh berubah saat ingestion Neraca.');

            $insertedCount = DB::table('neraca_transactions')->where('batch_id', $result['batch_id'])->count();
            $this->assertEquals(950, $insertedCount, 'Tabel neraca_transactions harus bertambah tepat 950 baris untuk batch ini.');

            // Verifikasi raw HS code tersimpan di neraca_transactions
            $sampleTx = DB::table('neraca_transactions')->where('batch_id', $result['batch_id'])->first();
            $this->assertNotNull($sampleTx);
            $this->assertNotEmpty($sampleTx->hs_code_raw);
            $this->assertContains($sampleTx->mapping_status, ['mapped', 'unmapped']);
        } finally {
            if ($permFile && Storage::disk('local')->exists($permFile)) {
                Storage::disk('local')->delete($permFile);
            }
            DB::rollBack();
        }
    }

    /**
     * 4. Test Ingestion Neraca Impor: Memetakan 15 kolom pabean KPPBC ke `neraca_transactions`.
     */
    public function test_neraca_impor_ingestion_preserves_kppbc_columns(): void
    {
        $admin = User::where('role_id', 1)->first();
        $this->assertNotNull($admin);

        $pathNeracaImp = base_path('referensi_bps/impor_neraca/14_impor_atap_kppbc_0826.DBF');
        if (!file_exists($pathNeracaImp) || !is_readable($pathNeracaImp)) {
            $this->markTestSkipped('File Neraca Impor 0826 tidak dapat diakses.');
        }

        $permFile = null;
        DB::beginTransaction();
        try {
            DB::table('neraca_transactions')->whereIn('batch_id', function ($q) {
                $q->select('batch_id')->from('data_batches')->where('tipe_sumber', 'neraca')->where('periode_tahun', 2026)->where('periode_bulan', 8)->where('jenis_data', 'Impor');
            })->delete();
            DB::table('data_batches')->where('tipe_sumber', 'neraca')->where('periode_tahun', 2026)->where('periode_bulan', 8)->where('jenis_data', 'Impor')->delete();

            $service = app(DbfIngestionService::class);
            $result = $service->ingest(
                $pathNeracaImp,
                '14_impor_atap_kppbc_0826.DBF',
                'neraca',
                'impor',
                $admin->id,
                'PRIVATE',
                true
            );
            $permFile = $result['arsip_file'];

            $this->assertTrue($result['success']);
            $this->assertEquals(1021, $result['total_baris']);
            $this->assertEquals('neraca', $result['sumber_data']);
            $this->assertEquals('impor', $result['jenis_transaksi']);

            // Verifikasi kolom KPPBC terisi
            $sampleKppbc = DB::table('neraca_transactions')
                ->where('batch_id', $result['batch_id'])
                ->whereNotNull('kode_kppbc')
                ->first();

            $this->assertNotNull($sampleKppbc);
            $this->assertNotEmpty($sampleKppbc->kode_kppbc);
            $this->assertNotEmpty($sampleKppbc->nama_kppbc);
        } finally {
            if ($permFile && Storage::disk('local')->exists($permFile)) {
                Storage::disk('local')->delete($permFile);
            }
            DB::rollBack();
        }
    }

    /**
     * 5. Test Penolakan Duplikasi Periode secara Default (Safety Guard).
     */
    public function test_ingestion_rejects_duplicate_period_by_default(): void
    {
        $admin = User::where('role_id', 1)->first();
        $pathExp = base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2609_POD_based.DBF');

        $service = app(DbfIngestionService::class);

        // Upload kedua untuk periode yang sama tanpa overwrite = false HARUS DITOLAK
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah pernah');

        $service->ingest($pathExp, '14_Atap_ekspor_2609_POD_based.DBF', 'distribusi', 'ekspor', $admin->id, 'PRIVATE', false);
    }

    /**
     * 6. Test Otorisasi: Pegawai (Role 2) Ditolak Menjalankan Ingestion via HTTP.
     */
    public function test_pegawai_cannot_execute_ingestion(): void
    {
        $pegawai = User::where('role_id', 2)->first();
        $this->assertNotNull($pegawai);

        $file = new UploadedFile(
            base_path('referensi_bps/ekspor_distribusi/14_Atap_ekspor_2609_POD_based.DBF'),
            '14_Atap_ekspor_2609_POD_based.DBF',
            'application/octet-stream',
            null,
            true
        );

        $response = $this->actingAs($pegawai)->postJson(route('upload.process'), [
            'excel_file_final' => $file,
            'jenis_data_final' => 'Ekspor',
            'sumber_data_final' => 'Distribusi',
        ]);

        $response->assertStatus(403);
    }
}
