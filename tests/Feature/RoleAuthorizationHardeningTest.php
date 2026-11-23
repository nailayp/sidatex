<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Test Suite Hardening Otorisasi Role (Super Admin, Pegawai, Viewer Eksekutif)
 * untuk Tahap 2N.
 */
class RoleAuthorizationHardeningTest extends TestCase
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
     * Pegawai (role_id: 2) DILARANG mengakses modul Upload Data.
     */
    public function test_pegawai_cannot_access_upload_data(): void
    {
        $pegawai = $this->getTestUser(2);

        $response = $this->actingAs($pegawai)->get(route('upload-data'));
        $response->assertStatus(403);

        $postProcess = $this->actingAs($pegawai)->post(route('upload.process'), []);
        $postProcess->assertStatus(403);

        $postPreview = $this->actingAs($pegawai)->post(route('upload.preview'), []);
        $postPreview->assertStatus(403);
    }

    /**
     * Pegawai (role_id: 2) DILARANG mengubah atau merilis batch dan DILARANG mengelola Trash.
     */
    public function test_pegawai_cannot_manage_batches_or_trash(): void
    {
        $pegawai = $this->getTestUser(2);

        // Toggle release batch
        $resToggle = $this->actingAs($pegawai)->post(route('manajemen-data.toggle-release', ['batchId' => 9999]));
        $resToggle->assertStatus(403);

        // Update batch
        $resUpdate = $this->actingAs($pegawai)->post(route('manajemen-data.update', ['batchId' => 9999]), [
            'nama_file' => 'test.xlsx',
            'jenis_data' => 'Ekspor',
        ]);
        $resUpdate->assertStatus(403);

        // Delete batch ke Trash
        $resDelete = $this->actingAs($pegawai)->post(route('manajemen-data.destroy', ['batchId' => 9999]));
        $resDelete->assertStatus(403);

        // Akses halaman Trash
        $resTrash = $this->actingAs($pegawai)->get(route('trash-data'));
        $resTrash->assertStatus(403);

        // Restore dari Trash
        $resRestore = $this->actingAs($pegawai)->post(route('manajemen-data.restore', ['batchId' => 9999]));
        $resRestore->assertStatus(403);

        // Force delete
        $resForce = $this->actingAs($pegawai)->post(route('manajemen-data.force-delete', ['batchId' => 9999]));
        $resForce->assertStatus(403);
    }

    /**
     * Viewer Eksekutif (role_id: 3) DILARANG mengakses fungsi pengelolaan data dan audit trail.
     */
    public function test_viewer_eksekutif_cannot_access_management_and_logs(): void
    {
        $viewer = $this->getTestUser(3);

        $resUpload = $this->actingAs($viewer)->get(route('upload-data'));
        $resUpload->assertStatus(403);

        $resTrash = $this->actingAs($viewer)->get(route('trash-data'));
        $resTrash->assertStatus(403);

        $resLogs = $this->actingAs($viewer)->get(route('activity-logs'));
        $this->assertTrue(in_array($resLogs->getStatusCode(), [302, 403]));

        $resPptx = $this->actingAs($viewer)->get(route('export.powerpoint', ['batchId' => 9999]));
        $resPptx->assertStatus(403);
    }

    /**
     * Super Admin (role_id: 1) TETAP DAPAT mengakses seluruh fungsi pengelolaan.
     */
    public function test_superadmin_can_access_all_management_modules(): void
    {
        $superAdmin = $this->getTestUser(1);

        $resUpload = $this->actingAs($superAdmin)->get(route('upload-data'));
        $resUpload->assertStatus(200);

        $resManajemen = $this->actingAs($superAdmin)->get(route('manajemen-data'));
        $resManajemen->assertStatus(200);

        $resTrash = $this->actingAs($superAdmin)->get(route('trash-data'));
        $resTrash->assertStatus(200);

        $resLogs = $this->actingAs($superAdmin)->get(route('activity-logs'));
        $resLogs->assertStatus(200);
    }

    /**
     * Ketiga role (Super Admin, Pegawai, Viewer) DAPAT melihat ketiga Dashboard.
     */
    public function test_all_three_roles_can_view_dashboards(): void
    {
        $roles = [1, 2, 3];
        foreach ($roles as $roleId) {
            $user = $this->getTestUser($roleId);

            $this->actingAs($user)->get(route('dashboard'))->assertStatus(200);
            $this->actingAs($user)->get(route('distribusi.dashboard'))->assertStatus(200);
            $this->actingAs($user)->get(route('neraca.dashboard'))->assertStatus(200);
            $this->actingAs($user)->get(route('filtering-data'))->assertStatus(200);
        }
    }

    /**
     * Sidebar hanya menampilkan link Upload Data dan Trash untuk Super Admin.
     */
    public function test_sidebar_hides_upload_and_trash_for_non_admin(): void
    {
        $pegawai = $this->getTestUser(2);
        $resPegawai = $this->actingAs($pegawai)->get(route('dashboard'));
        $resPegawai->assertDontSee('Upload Data</a>', false);
        $resPegawai->assertDontSee('Trash Dokumen</a>', false);

        $viewer = $this->getTestUser(3);
        $resViewer = $this->actingAs($viewer)->get(route('dashboard'));
        $resViewer->assertDontSee('Upload Data</a>', false);
        $resViewer->assertDontSee('Trash Dokumen</a>', false);

        $admin = $this->getTestUser(1);
        $resAdmin = $this->actingAs($admin)->get(route('dashboard'));
        $resAdmin->assertSee('Upload Data');
        $resAdmin->assertSee('Trash Dokumen');
    }
}
