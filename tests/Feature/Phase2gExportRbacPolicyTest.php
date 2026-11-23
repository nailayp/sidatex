<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase2gExportRbacPolicyTest extends TestCase
{
    /** @var int[] */
    private array $createdBatchIds = [];

    /** @var int[] */
    private array $createdUserIds = [];

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

        if (!empty($this->createdUserIds)) {
            DB::table('users')->whereIn('id', $this->createdUserIds)->delete();
        }

        parent::tearDown();
    }

    private function resolveOrCreateUser(int $roleId, string $suffix): User
    {
        $user = User::where('role_id', $roleId)->first();
        if ($user) {
            return $user;
        }

        $username = "p2g_exp_r{$roleId}_{$suffix}_" . uniqid();
        $userId = DB::table('users')->insertGetId([
            'name' => "Test Role {$roleId}",
            'username' => $username,
            'email' => "{$username}@sidatex.test",
            'password' => Hash::make('password123'),
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdUserIds[] = $userId;

        return User::findOrFail($userId);
    }

    private function seedBatchWithTransaction(string $status, int $uploadedBy, bool $trashed = false): int
    {
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();
        $prov = DB::table('master_provinsi')->first();
        $pel = DB::table('master_pelabuhan')->first();

        $batchId = DB::table('data_batches')->insertGetId([
            'nama_file' => "p2g_export_{$status}_" . uniqid() . '.xlsx',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'periode_tahun' => 2094,
            'periode_bulan' => 4,
            'ukuran_file' => 0.10,
            'status' => $status,
            'uploaded_by' => $uploadedBy,
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $batchId;

        DB::table('distribusi_transactions')->insert([
            'batch_id' => $batchId,
            'hs_id' => $hs->hs_id,
            'negara_id' => $negara->negara_id,
            'provinsi_id' => $prov->provinsi_id,
            'pelabuhan_id' => $pel ? $pel->pelabuhan_id : null,
            'jenis_transaksi' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'bulan' => 4,
            'tahun' => 2094,
            'berat' => '150.250000',
            'nilai' => '12500.500000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($trashed) {
            DB::table('trash')->insert([
                'batch_id' => $batchId,
                'data_snapshot' => json_encode(['batch_id' => $batchId, 'status' => $status]),
                'deleted_by' => $uploadedBy,
                'deleted_at' => now(),
                'restored_by' => null,
                'restored_at' => null,
            ]);
        }

        return $batchId;
    }

    public function test_super_admin_export_matrix_allows_rilis_and_private_blocks_draft_and_trash(): void
    {
        $admin = $this->resolveOrCreateUser(1, 'admin');

        $rilisId = $this->seedBatchWithTransaction('RILIS', $admin->id, false);
        $privateId = $this->seedBatchWithTransaction('PRIVATE', $admin->id, false);
        $draftId = $this->seedBatchWithTransaction('DRAFT', $admin->id, false);
        $trashedRilisId = $this->seedBatchWithTransaction('RILIS', $admin->id, true);
        $trashedPrivateId = $this->seedBatchWithTransaction('PRIVATE', $admin->id, true);

        // Allowed: RILIS & PRIVATE
        $resRilis = $this->actingAs($admin)->get(route('manajemen-data.export', ['batchId' => $rilisId]));
        $resRilis->assertStatus(200);
        $resRilis->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $resPrivate = $this->actingAs($admin)->get(route('manajemen-data.export', ['batchId' => $privateId]));
        $resPrivate->assertStatus(200);
        $resPrivate->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Denied: DRAFT & Trash
        $this->actingAs($admin)->get(route('manajemen-data.export', ['batchId' => $draftId]))->assertStatus(403);
        $this->actingAs($admin)->get(route('manajemen-data.export', ['batchId' => $trashedRilisId]))->assertStatus(403);
        $this->actingAs($admin)->get(route('manajemen-data.export', ['batchId' => $trashedPrivateId]))->assertStatus(403);

        // Activity logs recorded ONLY for permitted exports with exact user_id
        $this->assertTrue(DB::table('activity_logs')
            ->where('log_id', '>', $this->maxLogIdBefore)
            ->where('user_id', $admin->id)
            ->where('aktivitas', 'Ekspor Data Batch')
            ->where('deskripsi', 'like', "%batch #{$rilisId}%")
            ->where('deskripsi', 'like', '%[Status: RILIS%')
            ->exists());

        $this->assertTrue(DB::table('activity_logs')
            ->where('log_id', '>', $this->maxLogIdBefore)
            ->where('user_id', $admin->id)
            ->where('aktivitas', 'Ekspor Data Batch')
            ->where('deskripsi', 'like', "%batch #{$privateId}%")
            ->where('deskripsi', 'like', '%[Status: PRIVATE%')
            ->exists());

        $this->assertFalse(DB::table('activity_logs')
            ->where('log_id', '>', $this->maxLogIdBefore)
            ->where('deskripsi', 'like', "%batch #{$draftId}%")
            ->exists());

        $this->assertFalse(DB::table('activity_logs')
            ->where('log_id', '>', $this->maxLogIdBefore)
            ->where('deskripsi', 'like', "%batch #{$trashedRilisId}%")
            ->exists());
    }

    public function test_viewer_eksekutif_can_export_rilis_and_private_but_not_draft_or_trash(): void
    {
        $admin = $this->resolveOrCreateUser(1, 'admin_v');
        $viewer = $this->resolveOrCreateUser(3, 'viewer');

        // Owned by Super Admin — Viewer Eksekutif must be allowed on RILIS & PRIVATE via role policy
        $rilisId = $this->seedBatchWithTransaction('RILIS', $admin->id, false);
        $privateId = $this->seedBatchWithTransaction('PRIVATE', $admin->id, false);
        $draftId = $this->seedBatchWithTransaction('DRAFT', $admin->id, false);
        $trashedRilisId = $this->seedBatchWithTransaction('RILIS', $admin->id, true);
        $trashedPrivateId = $this->seedBatchWithTransaction('PRIVATE', $admin->id, true);

        $resRilis = $this->actingAs($viewer)->get(route('manajemen-data.export', ['batchId' => $rilisId]));
        $resRilis->assertStatus(200);
        $resRilis->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $resPrivate = $this->actingAs($viewer)->get(route('manajemen-data.export', ['batchId' => $privateId]));
        $resPrivate->assertStatus(200);
        $resPrivate->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Denied: DRAFT & Trash
        $this->actingAs($viewer)->get(route('manajemen-data.export', ['batchId' => $draftId]))->assertStatus(403);
        $this->actingAs($viewer)->get(route('manajemen-data.export', ['batchId' => $trashedRilisId]))->assertStatus(403);
        $this->actingAs($viewer)->get(route('manajemen-data.export', ['batchId' => $trashedPrivateId]))->assertStatus(403);

        // Activity log uses Viewer's real user_id (never fallback to 1)
        $this->assertTrue(DB::table('activity_logs')
            ->where('log_id', '>', $this->maxLogIdBefore)
            ->where('user_id', $viewer->id)
            ->where('aktivitas', 'Ekspor Data Batch')
            ->where('deskripsi', 'like', "%batch #{$privateId}%")
            ->where('deskripsi', 'like', '%[Status: PRIVATE%')
            ->exists());

        $this->assertFalse(DB::table('activity_logs')
            ->where('log_id', '>', $this->maxLogIdBefore)
            ->where('user_id', $viewer->id)
            ->where('deskripsi', 'like', "%batch #{$draftId}%")
            ->exists());
    }

    public function test_pegawai_can_only_export_active_rilis_and_owner_bypass_is_blocked(): void
    {
        $admin = $this->resolveOrCreateUser(1, 'admin_p');
        $pegawai = $this->resolveOrCreateUser(2, 'pegawai');

        $rilisId = $this->seedBatchWithTransaction('RILIS', $admin->id, false);
        // Even if uploaded_by equals pegawai->id, PRIVATE & DRAFT MUST be denied (no $isOwner bypass)
        $privateOwnedByPegawai = $this->seedBatchWithTransaction('PRIVATE', $pegawai->id, false);
        $draftOwnedByPegawai = $this->seedBatchWithTransaction('DRAFT', $pegawai->id, false);
        $trashedRilisId = $this->seedBatchWithTransaction('RILIS', $admin->id, true);

        $resRilis = $this->actingAs($pegawai)->get(route('manajemen-data.export', ['batchId' => $rilisId]));
        $resRilis->assertStatus(200);
        $resRilis->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($pegawai)->get(route('manajemen-data.export', ['batchId' => $privateOwnedByPegawai]))->assertStatus(403);
        $this->actingAs($pegawai)->get(route('manajemen-data.export', ['batchId' => $draftOwnedByPegawai]))->assertStatus(403);
        $this->actingAs($pegawai)->get(route('manajemen-data.export', ['batchId' => $trashedRilisId]))->assertStatus(403);

        // Activity log recorded for RILIS export with pegawai's user_id, zero logs for denied attempts
        $this->assertTrue(DB::table('activity_logs')
            ->where('log_id', '>', $this->maxLogIdBefore)
            ->where('user_id', $pegawai->id)
            ->where('aktivitas', 'Ekspor Data Batch')
            ->where('deskripsi', 'like', "%batch #{$rilisId}%")
            ->exists());

        $this->assertFalse(DB::table('activity_logs')
            ->where('log_id', '>', $this->maxLogIdBefore)
            ->where('deskripsi', 'like', "%batch #{$privateOwnedByPegawai}%")
            ->exists());
    }
}
