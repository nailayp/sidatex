<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression Test Suite for Tahap 2K:
 * - Pemisahan Modul Neraca dan Distribusi
 * - Aksesibilitas Route & Navigasi
 * - Isolasi Data Neraca vs Distribusi
 */
class NeracaAndDistribusiModuleSeparationTest extends TestCase
{
    /**
     * Pastikan rute Dashboard Neraca dapat diakses oleh pengguna yang login.
     */
    public function test_neraca_dashboard_accessible_by_authenticated_user(): void
    {
        $user = User::first() ?? User::factory()->make(['role_id' => 1]);
        $response = $this->actingAs($user)->get(route('neraca.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Dashboard Statistik Perdagangan Luar Negeri');
        $response->assertSee('Metodologi Neraca');
    }

    /**
     * Pastikan endpoint API metrik Neraca mengembalikan struktur data yang benar dan terisolasi ke sumber 'neraca'.
     */
    public function test_neraca_metrics_endpoint_strictly_returns_neraca_data(): void
    {
        $user = User::first() ?? User::factory()->make(['role_id' => 1]);
        $response = $this->actingAs($user)->get(route('neraca.metrics', ['tahun' => 2026]));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('monthly', $data);
        $this->assertArrayHasKey('sektor', $data);
        $this->assertArrayHasKey('bec', $data);

        // Pastikan tidak ada transaksi Distribusi yang ikut dihitung dalam metrik Neraca
        $distribusiInNeraca = DB::table('transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->where('b.status', 'RILIS')
            ->where('t.tipe_sumber', 'neraca')
            ->where('b.tipe_sumber', 'distribusi')
            ->count();

        $this->assertEquals(0, $distribusiInNeraca);
    }

    /**
     * Pastikan Dashboard Distribusi tetap terisolasi dan dapat diakses.
     */
    public function test_distribusi_dashboard_remains_intact_and_isolated(): void
    {
        $user = User::first() ?? User::factory()->make(['role_id' => 1]);
        $response = $this->actingAs($user)->get(route('distribusi.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Dashboard Statistik Perdagangan Luar Negeri');
        $response->assertSee('Metodologi Distribusi');
    }

    /**
     * Pastikan navigasi sidebar menampilkan menu tunggal Dashboard sesuai Phase 2F Single Dashboard Policy.
     */
    public function test_sidebar_navigation_renders_all_three_dashboards(): void
    {
        $user = User::first() ?? User::factory()->make(['role_id' => 1]);
        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Dashboard Statistik');
    }

    /**
     * Pastikan ketika data Neraca tidak tersedia, status_neraca mengembalikan 'Data Belum Tersedia' dan has_data false.
     */
    public function test_neraca_metrics_handles_missing_data_with_neutral_status(): void
    {
        $user = User::first() ?? User::factory()->make(['role_id' => 1]);
        // Cari tahun yang pasti tidak ada data Neraca RILIS di DB testing
        $response = $this->actingAs($user)->get(route('neraca.metrics', ['tahun' => 2099]));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertFalse($data['has_data']);
        $this->assertEquals('Data Belum Tersedia', $data['summary']['status_neraca']);
        $this->assertNotEquals('Surplus', $data['summary']['status_neraca']);
        $this->assertNotEquals('Defisit', $data['summary']['status_neraca']);
    }
}
