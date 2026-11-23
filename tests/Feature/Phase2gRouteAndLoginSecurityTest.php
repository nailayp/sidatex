<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase2gRouteAndLoginSecurityTest extends TestCase
{
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
        DB::table('activity_logs')->where('log_id', '>', $this->maxLogIdBefore)->delete();

        if (!empty($this->createdUserIds)) {
            DB::table('users')->whereIn('id', $this->createdUserIds)->delete();
        }

        parent::tearDown();
    }

    private function createTempUser(int $roleId, string $password = 'secret1234'): User
    {
        $username = "p2g_login_r{$roleId}_" . uniqid();
        $userId = DB::table('users')->insertGetId([
            'name' => "Temp Login Role {$roleId}",
            'username' => $username,
            'email' => "{$username}@sidatex.test",
            'password' => Hash::make($password),
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdUserIds[] = $userId;

        return User::findOrFail($userId);
    }

    public function test_dead_and_unguarded_routes_are_removed_and_return_404(): void
    {
        $this->assertFalse(Route::has('dashboard.visual'));
        $this->assertFalse(Route::has('dashboard.master'));

        foreach ([1, 2, 3] as $roleId) {
            $user = $this->createTempUser($roleId);
            $this->actingAs($user)->get('/dashboard/visual')->assertStatus(404);
            $this->actingAs($user)->get('/dashboard/master')->assertStatus(404);
        }

        // Core dashboard routes remain intact
        $admin = $this->createTempUser(1);
        $this->actingAs($admin)->get(route('dashboard'))->assertStatus(200);
    }

    public function test_login_redirect_sends_viewer_eksekutif_to_dashboard_not_forbidden_upload(): void
    {
        $admin = $this->createTempUser(1, 'AdminPass123');
        $pegawai = $this->createTempUser(2, 'PegawaiPass123');
        $viewer = $this->createTempUser(3, 'ViewerPass123');

        // Role 1 -> upload-data (200)
        $resAdmin = $this->post('/login', ['username' => $admin->username, 'password' => 'AdminPass123']);
        $resAdmin->assertRedirect(route('upload-data'));
        $this->get(route('upload-data'))->assertStatus(200);
        $this->post('/logout');

        // Role 2 -> dashboard (200)
        $resPegawai = $this->post('/login', ['username' => $pegawai->username, 'password' => 'PegawaiPass123']);
        $resPegawai->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertStatus(200);
        $this->post('/logout');

        // Role 3 (Viewer Eksekutif) -> dashboard (200), NOT upload-data (403)!
        $resViewer = $this->post('/login', ['username' => $viewer->username, 'password' => 'ViewerPass123']);
        $resViewer->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertStatus(200);
        // Direct access to /upload-data MUST still return 403 for Role 3
        $this->get(route('upload-data'))->assertStatus(403);
    }

    public function test_pegawai_does_not_receive_private_batch_counts_or_trash_metadata(): void
    {
        $pegawai = $this->createTempUser(2);

        $dashRes = $this->actingAs($pegawai)->get(route('dashboard'));
        $dashRes->assertStatus(200);
        $batchStats = $dashRes->viewData('batchStats');

        $this->assertSame(0, $batchStats['distribusi_private']);
        $this->assertSame(0, $batchStats['neraca_private']);
        $this->assertSame($batchStats['distribusi_rilis'], $batchStats['distribusi_total']);
        $this->assertSame($batchStats['neraca_rilis'], $batchStats['neraca_total']);

        $manajemenRes = $this->actingAs($pegawai)->get(route('manajemen-data'));
        $manajemenRes->assertStatus(200);
        $this->assertSame(0, $manajemenRes->viewData('totalTrash'));
        $this->assertCount(0, $manajemenRes->viewData('trashedItems'));
    }
}
