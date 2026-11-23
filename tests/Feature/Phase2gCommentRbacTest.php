<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase2gCommentRbacTest extends TestCase
{
    /** @var int[] */
    private array $createdBatchIds = [];

    /** @var int[] */
    private array $createdCommentIds = [];

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
        if (!empty($this->createdCommentIds)) {
            DB::table('comments')->whereIn('comment_id', $this->createdCommentIds)->delete();
        }
        if (!empty($this->createdBatchIds)) {
            DB::table('comments')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('trash')->whereIn('batch_id', $this->createdBatchIds)->delete();
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

        $username = "p2g_cmt_r{$roleId}_{$suffix}_" . uniqid();
        $userId = DB::table('users')->insertGetId([
            'name' => "Comment Role {$roleId}",
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

    private function seedBatchWithComment(string $status, int $ownerId, string $commentText, bool $trashed = false): array
    {
        $batchId = DB::table('data_batches')->insertGetId([
            'nama_file' => "p2g_cmt_{$status}_" . uniqid() . '.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'status' => $status,
            'uploaded_by' => $ownerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $batchId;

        $commentId = DB::table('comments')->insertGetId([
            'batch_id' => $batchId,
            'user_id' => $ownerId,
            'komentar' => $commentText,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdCommentIds[] = $commentId;

        if ($trashed) {
            DB::table('trash')->insert([
                'batch_id' => $batchId,
                'data_snapshot' => json_encode(['batch_id' => $batchId]),
                'deleted_by' => $ownerId,
                'deleted_at' => now(),
            ]);
        }

        return [$batchId, $commentId];
    }

    public function test_guest_cannot_access_comment_endpoints(): void
    {
        $admin = $this->resolveOrCreateUser(1, 'g');
        [$batchId, $commentId] = $this->seedBatchWithComment('RILIS', $admin->id, 'Public note');

        $this->getJson(route('comments.batch', ['batchId' => $batchId]))->assertStatus(401);
        $this->postJson(route('comments.store'), ['batch_id' => $batchId, 'komentar' => 'Hack'])->assertStatus(401);
        $this->deleteJson(route('comments.destroy', ['commentId' => $commentId]))->assertStatus(401);
    }

    public function test_pegawai_cannot_read_private_draft_trash_or_post_any_comment(): void
    {
        $admin = $this->resolveOrCreateUser(1, 'p_a');
        $pegawai = $this->resolveOrCreateUser(2, 'p_u');

        [$rilisId] = $this->seedBatchWithComment('RILIS', $admin->id, 'PUBLIC_NOTE_OK');
        [$privateId] = $this->seedBatchWithComment('PRIVATE', $admin->id, 'SECRET_PRIVATE_MARKER_991');
        [$draftId] = $this->seedBatchWithComment('DRAFT', $admin->id, 'SECRET_DRAFT_MARKER_992');
        [$trashedId] = $this->seedBatchWithComment('RILIS', $admin->id, 'SECRET_TRASH_MARKER_993', true);

        // Read allowed ONLY on active RILIS
        $this->actingAs($pegawai)
            ->getJson(route('comments.batch', ['batchId' => $rilisId]))
            ->assertStatus(200)
            ->assertSee('PUBLIC_NOTE_OK');

        // Read DENIED on PRIVATE, DRAFT, Trash — and secret markers NEVER leaked
        $resPriv = $this->actingAs($pegawai)->getJson(route('comments.batch', ['batchId' => $privateId]));
        $resPriv->assertStatus(403);
        $this->assertStringNotContainsString('SECRET_PRIVATE_MARKER_991', $resPriv->getContent());

        $resDraft = $this->actingAs($pegawai)->getJson(route('comments.batch', ['batchId' => $draftId]));
        $resDraft->assertStatus(403);
        $this->assertStringNotContainsString('SECRET_DRAFT_MARKER_992', $resDraft->getContent());

        $resTrash = $this->actingAs($pegawai)->getJson(route('comments.batch', ['batchId' => $trashedId]));
        $resTrash->assertStatus(403);
        $this->assertStringNotContainsString('SECRET_TRASH_MARKER_993', $resTrash->getContent());

        // Write DENIED on ALL statuses (and even on nonexistent batch ID -> 403 before exists: validation!)
        $countBefore = DB::table('comments')->count();
        foreach ([$rilisId, $privateId, $draftId, $trashedId, 9999999] as $targetId) {
            $this->actingAs($pegawai)
                ->postJson(route('comments.store'), ['batch_id' => $targetId, 'komentar' => 'Unauthorized write'])
                ->assertStatus(403);
        }
        $this->assertSame($countBefore, DB::table('comments')->count());
    }

    public function test_viewer_eksekutif_can_read_and_post_on_rilis_and_private_only(): void
    {
        $admin = $this->resolveOrCreateUser(1, 'v_a');
        $viewer = $this->resolveOrCreateUser(3, 'v_u');

        [$rilisId] = $this->seedBatchWithComment('RILIS', $admin->id, 'Rilis Note');
        [$privateId] = $this->seedBatchWithComment('PRIVATE', $admin->id, 'Private Note');
        [$draftId] = $this->seedBatchWithComment('DRAFT', $admin->id, 'Draft Note');
        [$trashedId] = $this->seedBatchWithComment('PRIVATE', $admin->id, 'Trashed Private Note', true);

        // Allowed: RILIS & PRIVATE (Read + Write)
        $this->actingAs($viewer)->getJson(route('comments.batch', ['batchId' => $rilisId]))->assertStatus(200);
        $this->actingAs($viewer)->getJson(route('comments.batch', ['batchId' => $privateId]))->assertStatus(200);

        $resPostPriv = $this->actingAs($viewer)->postJson(route('comments.store'), [
            'batch_id' => $privateId,
            'komentar' => 'Catatan review pimpinan untuk data private',
        ]);
        $resPostPriv->assertStatus(201);
        $createdId = (int) $resPostPriv->json('data.comment_id');
        $this->createdCommentIds[] = $createdId;

        // Denied: DRAFT & Trash (Read + Write)
        $this->actingAs($viewer)->getJson(route('comments.batch', ['batchId' => $draftId]))->assertStatus(403);
        $this->actingAs($viewer)->getJson(route('comments.batch', ['batchId' => $trashedId]))->assertStatus(403);
        $this->actingAs($viewer)->postJson(route('comments.store'), ['batch_id' => $draftId, 'komentar' => 'No'])->assertStatus(403);
        $this->actingAs($viewer)->postJson(route('comments.store'), ['batch_id' => $trashedId, 'komentar' => 'No'])->assertStatus(403);

        // Viewer can delete OWN comment on active PRIVATE batch
        $this->actingAs($viewer)->deleteJson(route('comments.destroy', ['commentId' => $createdId]))->assertStatus(200);
        $this->assertFalse(DB::table('comments')->where('comment_id', $createdId)->exists());
    }

    public function test_comment_deletion_enforces_superadmin_or_owner_and_blocks_trashed_for_non_admin(): void
    {
        $admin = $this->resolveOrCreateUser(1, 'd_a');
        $pegawai = $this->resolveOrCreateUser(2, 'd_p');
        $viewer = $this->resolveOrCreateUser(3, 'd_v');

        [$privateId, $adminCommentId] = $this->seedBatchWithComment('PRIVATE', $admin->id, 'Admin comment');
        [$trashedId, $viewerTrashedCommentId] = $this->seedBatchWithComment('PRIVATE', $viewer->id, 'Viewer trashed comment', true);

        // Pegawai cannot delete Admin's comment -> 403
        $this->actingAs($pegawai)->deleteJson(route('comments.destroy', ['commentId' => $adminCommentId]))->assertStatus(403);
        // Viewer cannot delete Admin's comment -> 403
        $this->actingAs($viewer)->deleteJson(route('comments.destroy', ['commentId' => $adminCommentId]))->assertStatus(403);
        // Viewer cannot delete own comment on a TRASHED batch -> 403
        $this->actingAs($viewer)->deleteJson(route('comments.destroy', ['commentId' => $viewerTrashedCommentId]))->assertStatus(403);

        // Super Admin CAN delete Viewer's comment -> 200
        $this->actingAs($admin)->deleteJson(route('comments.destroy', ['commentId' => $viewerTrashedCommentId]))->assertStatus(200);
        $this->assertFalse(DB::table('comments')->where('comment_id', $viewerTrashedCommentId)->exists());

        // Super Admin cannot POST a new comment on a trashed batch -> 403
        $this->actingAs($admin)->postJson(route('comments.store'), ['batch_id' => $trashedId, 'komentar' => 'No'])->assertStatus(403);
    }
}
