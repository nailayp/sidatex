<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase2gGroup7ActivityLogsAndFilteringTest extends TestCase
{
    /** @var int[] */
    private array $createdBatchIds = [];

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
            DB::table('neraca_transactions')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('data_batches')->whereIn('batch_id', $this->createdBatchIds)->delete();
        }

        DB::table('activity_logs')->where('log_id', '>', $this->maxLogIdBefore)->delete();

        parent::tearDown();
    }

    protected function getTestUser(int $roleId): User
    {
        $user = User::where('role_id', $roleId)->first();
        if (!$user) {
            $user = User::factory()->create(['role_id' => $roleId]);
        }
        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | Activity Logs Tests
    |--------------------------------------------------------------------------
    */

    public function test_activity_logs_requires_authentication(): void
    {
        $response = $this->get(route('activity-logs'));
        $response->assertRedirect(route('login'));
    }

    public function test_activity_logs_super_admin_can_view_all_logs(): void
    {
        $admin = $this->getTestUser(1);
        $pegawai = $this->getTestUser(2);

        // Buat log untuk admin dan pegawai
        DB::table('activity_logs')->insert([
            [
                'user_id' => $admin->id,
                'aktivitas' => 'Testing Admin Log',
                'modul' => 'Sistem',
                'deskripsi' => 'Aktivitas pengujian super admin',
                'ip_address' => '127.0.0.1',
                'created_at' => now(),
            ],
            [
                'user_id' => $pegawai->id,
                'aktivitas' => 'Testing Pegawai Log',
                'modul' => 'Sistem',
                'deskripsi' => 'Aktivitas pengujian pegawai BPS',
                'ip_address' => '127.0.0.1',
                'created_at' => now(),
            ]
        ]);

        $response = $this->actingAs($admin)->get(route('activity-logs'));
        $response->assertStatus(200);
        $response->assertSee('Log Aktivitas Sistem');
        $response->assertSee('Testing Admin Log');
        $response->assertSee('Testing Pegawai Log');
    }

    public function test_activity_logs_pegawai_sees_only_own_logs(): void
    {
        $admin = $this->getTestUser(1);
        $pegawai = $this->getTestUser(2);

        DB::table('activity_logs')->insert([
            [
                'user_id' => $admin->id,
                'aktivitas' => 'Secret Admin Operation 999',
                'modul' => 'Sistem',
                'deskripsi' => 'Operasi rahasia admin yang tidak boleh dilihat pegawai',
                'ip_address' => '127.0.0.1',
                'created_at' => now(),
            ],
            [
                'user_id' => $pegawai->id,
                'aktivitas' => 'Pegawai Personal Action 888',
                'modul' => 'Sistem',
                'deskripsi' => 'Operasi pegawai sendiri',
                'ip_address' => '127.0.0.1',
                'created_at' => now(),
            ]
        ]);

        $response = $this->actingAs($pegawai)->get(route('activity-logs'));
        $response->assertStatus(200);
        $response->assertSee('Pegawai Personal Action 888');
        $response->assertDontSee('Secret Admin Operation 999');
    }

    public function test_activity_logs_viewer_eksekutif_is_redirected(): void
    {
        $viewer = $this->getTestUser(3);

        $response = $this->actingAs($viewer)->get(route('activity-logs'));
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_activity_logs_filters_work_correctly(): void
    {
        $admin = $this->getTestUser(1);

        DB::table('activity_logs')->insert([
            [
                'user_id' => $admin->id,
                'aktivitas' => 'Audit Alpha Unique Action',
                'modul' => 'ModulKhususAlpha',
                'deskripsi' => 'KataKunciAlpha unik',
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subDays(5),
            ],
            [
                'user_id' => $admin->id,
                'aktivitas' => 'Audit Beta Unique Action',
                'modul' => 'ModulKhususBeta',
                'deskripsi' => 'KataKunciBeta unik',
                'ip_address' => '127.0.0.1',
                'created_at' => now(),
            ]
        ]);

        // Filter by keyword q
        $resQ = $this->actingAs($admin)->get(route('activity-logs', ['q' => 'KataKunciAlpha']));
        $resQ->assertStatus(200);
        $resQ->assertSee('KataKunciAlpha unik');
        $resQ->assertDontSee('KataKunciBeta unik');

        // Filter by modul
        $resMod = $this->actingAs($admin)->get(route('activity-logs', ['modul' => 'ModulKhususBeta']));
        $resMod->assertStatus(200);
        $resMod->assertSee('KataKunciBeta unik');
        $resMod->assertDontSee('KataKunciAlpha unik');

        // Filter by date range
        $resDate = $this->actingAs($admin)->get(route('activity-logs', [
            'tanggal_mulai' => now()->subDays(2)->format('Y-m-d'),
            'tanggal_selesai' => now()->addDay()->format('Y-m-d'),
        ]));
        $resDate->assertStatus(200);
        $resDate->assertSee('KataKunciBeta unik');
        $resDate->assertDontSee('KataKunciAlpha unik');
    }

    public function test_activity_logs_zero_sensitive_data_leakage(): void
    {
        $logs = DB::table('activity_logs')->get();

        foreach ($logs as $log) {
            $desc = strtolower((string) $log->deskripsi);
            $this->assertStringNotContainsString('$2y$', $log->deskripsi, 'Bcrypt hash leaked in activity log');
            $this->assertStringNotContainsString('password', $desc, 'Password term should not appear in activity log descriptions');
            $this->assertStringNotContainsString('remember_token', $desc, 'Token leaked in activity log');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Filtering Data Tests
    |--------------------------------------------------------------------------
    */

    public function test_filtering_query_rejects_invalid_source(): void
    {
        $admin = $this->getTestUser(1);

        $response = $this->actingAs($admin)->getJson(route('filtering-data.query', [
            'tipe_sumber' => 'invalid_source_type',
        ]));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_filtering_query_strictly_separates_distribusi_and_neraca(): void
    {
        $admin = $this->getTestUser(1);

        $resDistribusi = $this->actingAs($admin)->getJson(route('filtering-data.query', [
            'tipe_sumber' => 'distribusi',
        ]));
        $resDistribusi->assertStatus(200);
        $this->assertEquals('distribusi', $resDistribusi->json('summary.tipe_sumber'));

        $resNeraca = $this->actingAs($admin)->getJson(route('filtering-data.query', [
            'tipe_sumber' => 'neraca',
        ]));
        $resNeraca->assertStatus(200);
        $this->assertEquals('neraca', $resNeraca->json('summary.tipe_sumber'));
    }

    public function test_filtering_query_pegawai_cannot_see_private_data(): void
    {
        $pegawai = $this->getTestUser(2);

        // Buat batch PRIVATE
        $privateBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'test_private_filtering.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'periode_tahun' => 2095,
            'periode_bulan' => 5,
            'ukuran_file' => '0.50',
            'status' => 'PRIVATE',
            'uploaded_by' => 1,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $privateBatchId;

        DB::table('distribusi_transactions')->insert([
            'batch_id' => $privateBatchId,
            'hs_id' => 1,
            'negara_id' => 1,
            'provinsi_id' => 4,
            'pelabuhan_id' => 1,
            'provorig' => '1401',
            'jenis_transaksi' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'bulan' => 5,
            'tahun' => 2095,
            'berat' => '500.000000',
            'nilai' => '25000.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pegawai query data tahun 2095
        $response = $this->actingAs($pegawai)->getJson(route('filtering-data.query', [
            'tipe_sumber' => 'distribusi',
            'tahun' => 2095,
        ]));

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('summary.total_rows'), 'Pegawai tidak boleh melihat baris dari batch PRIVATE');

        // Super Admin query data tahun 2095 -> Harus melihat 1 baris
        $admin = $this->getTestUser(1);
        $resAdmin = $this->actingAs($admin)->getJson(route('filtering-data.query', [
            'tipe_sumber' => 'distribusi',
            'tahun' => 2095,
        ]));
        $resAdmin->assertStatus(200);
        $this->assertEquals(1, $resAdmin->json('summary.total_rows'), 'Super Admin harus dapat melihat batch PRIVATE');
    }

    public function test_filtering_query_excludes_trashed_batches(): void
    {
        $admin = $this->getTestUser(1);

        $trashedBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'test_trashed_filtering.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'periode_tahun' => 2096,
            'periode_bulan' => 6,
            'ukuran_file' => '0.50',
            'status' => 'RILIS',
            'uploaded_by' => 1,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $trashedBatchId;

        DB::table('distribusi_transactions')->insert([
            'batch_id' => $trashedBatchId,
            'hs_id' => 1,
            'negara_id' => 1,
            'provinsi_id' => 4,
            'pelabuhan_id' => 1,
            'provorig' => '1401',
            'jenis_transaksi' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'bulan' => 6,
            'tahun' => 2096,
            'berat' => '100.000000',
            'nilai' => '5000.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Masukkan ke trash
        DB::table('trash')->insert([
            'batch_id' => $trashedBatchId,
            'data_snapshot' => '{}',
            'deleted_by' => 1,
            'deleted_at' => now(),
            'restored_at' => null,
        ]);

        $response = $this->actingAs($admin)->getJson(route('filtering-data.query', [
            'tipe_sumber' => 'distribusi',
            'tahun' => 2096,
        ]));

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('summary.total_rows'), 'Batch yang ada di trash harus diabaikan dari query');
    }

    public function test_filtering_query_summary_calculation_matches_exact_sum(): void
    {
        $admin = $this->getTestUser(1);

        $batchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'test_calc_filtering.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'periode_tahun' => 2097,
            'periode_bulan' => 7,
            'ukuran_file' => '0.50',
            'status' => 'RILIS',
            'uploaded_by' => 1,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $batchId;

        DB::table('distribusi_transactions')->insert([
            [
                'batch_id' => $batchId,
                'hs_id' => 1,
                'negara_id' => 1,
                'provinsi_id' => 4,
                'pelabuhan_id' => 1,
                'provorig' => '1401',
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
                'bulan' => 7,
                'tahun' => 2097,
                'berat' => '100.123456',
                'nilai' => '200.654321',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'batch_id' => $batchId,
                'hs_id' => 1,
                'negara_id' => 1,
                'provinsi_id' => 4,
                'pelabuhan_id' => 1,
                'provorig' => '1401',
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
                'bulan' => 7,
                'tahun' => 2097,
                'berat' => '300.654321',
                'nilai' => '400.123456',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        $response = $this->actingAs($admin)->getJson(route('filtering-data.query', [
            'tipe_sumber' => 'distribusi',
            'tahun' => 2097,
        ]));

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('summary.total_rows'));
        // 200.654321 + 400.123456 = 600.777777
        $this->assertSame('600.777777', $response->json('summary.total_nilai_fob_exact'));
        // 100.123456 + 300.654321 = 400.777777
        $this->assertSame('400.777777', $response->json('summary.total_berat_netto_exact'));
    }

    public function test_filtering_query_neraca_unmapped_hs_code_filtering(): void
    {
        $admin = $this->getTestUser(1);

        $batchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'test_unmapped_hs_neraca.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'neraca',
            'periode_tahun' => 2098,
            'periode_bulan' => 8,
            'ukuran_file' => '0.50',
            'status' => 'RILIS',
            'uploaded_by' => 1,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $batchId;

        // Insert unmapped transaction in Neraca with hs_code_raw starting with '44' (Kayu dan Barang dari Kayu)
        DB::table('neraca_transactions')->insert([
            'batch_id' => $batchId,
            'hs_id' => null,
            'hs_code_raw' => '44071100',
            'hs_desc_raw' => 'Kayu Konifer Olahan (Unmapped)',
            'mapping_status' => 'unmapped',
            'negara_id' => 1,
            'provinsi_id' => 4,
            'kabkota_id' => null,
            'pelabuhan_id' => 1,
            'kode_kppbc' => '030100',
            'nama_kppbc' => 'KPPBC TMP B PEKANBARU',
            'jenis_transaksi' => 'Ekspor',
            'tipe_sumber' => 'neraca',
            'bulan' => 8,
            'tahun' => 2098,
            'berat' => '1500.000000',
            'nilai' => '75000.000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Filter by hs2 = '44'
        $response = $this->actingAs($admin)->getJson(route('filtering-data.query', [
            'tipe_sumber' => 'neraca',
            'tahun' => 2098,
            'hs2' => '44',
        ]));

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('summary.total_rows'), 'Baris unmapped Neraca dengan hs_code_raw 44 harus ditemukan via filter hs2=44');
        $this->assertSame('44071100', $response->json('data.data.0.hs_code'));
        $this->assertSame('unmapped', $response->json('data.data.0.mapping_status'));

        // Filter by hs2 = '15' (harus 0 baris)
        $resOther = $this->actingAs($admin)->getJson(route('filtering-data.query', [
            'tipe_sumber' => 'neraca',
            'tahun' => 2098,
            'hs2' => '15',
        ]));
        $resOther->assertStatus(200);
        $this->assertEquals(0, $resOther->json('summary.total_rows'));
    }

    public function test_filtering_query_supports_per_page_options(): void
    {
        $admin = $this->getTestUser(1);

        foreach ([10, 25, 50, 100] as $perPage) {
            $response = $this->actingAs($admin)->getJson(route('filtering-data.query', [
                'tipe_sumber' => 'distribusi',
                'per_page' => $perPage,
            ]));
            $response->assertStatus(200);
            $this->assertEquals($perPage, $response->json('data.per_page'));
        }
    }
}
