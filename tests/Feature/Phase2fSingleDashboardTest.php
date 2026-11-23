<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase2fSingleDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Verifikasi koneksi runtime PHPUnit menggunakan database sidatex_testing atau SQLite :memory:
        $activeDb = DB::connection()->getDatabaseName();
        $this->assertContains(
            $activeDb,
            ['sidatex_testing', ':memory:'],
            "PHPUnit HARUS terhubung ke database sidatex_testing atau SQLite ':memory:'! Terdeteksi: {$activeDb}"
        );
    }

    /**
     * Uji 1: Super Admin dapat mengakses halaman Single Dashboard resmi (/dashboard) dengan sukses (200 OK).
     */
    public function test_superadmin_can_access_single_dashboard(): void
    {
        $superadmin = User::where('role_id', 1)->first();
        $this->assertNotNull($superadmin, 'Akun Super Admin harus tersedia di database sidatex_testing.');

        $response = $this->actingAs($superadmin)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertViewIs('dashboard');

        // Pastikan 4 Tab Analisis hadir di antarmuka
        $response->assertSee('id="tabOverview"', false);
        $response->assertSee('id="tabTimeSeries"', false);
        $response->assertSee('id="tabDetailMapping"', false);
        $response->assertSee('id="tabTradeBalance"', false);

        // Pastikan Segmented Switchers hadir
        $response->assertSee('id="btnSumberDistribusi"', false);
        $response->assertSee('id="btnSumberNeraca"', false);
        $response->assertSee('id="btnJenisEkspor"', false);
        $response->assertSee('id="btnJenisImpor"', false);
        $response->assertSee('id="btnJenisNeraca"', false);
    }

    /**
     * Uji 2: Sidebar navigasi hanya memiliki SATU menu Dashboard, tanpa menu Dashboard Distribusi dan Dashboard Neraca terpisah.
     */
    public function test_sidebar_contains_strictly_single_dashboard_menu(): void
    {
        $superadmin = User::where('role_id', 1)->first();

        $response = $this->actingAs($superadmin)->get(route('dashboard'));
        $content = $response->getContent();

        // Pastikan ada SATU menu Dashboard Statistik
        $this->assertStringContainsString('Dashboard Statistik', $content);

        // Pastikan TIDAK ADA menu terpisah Dashboard Distribusi atau Dashboard Neraca di sidebar
        $this->assertStringNotContainsString('Dashboard Distribusi</span>', $content);
        $this->assertStringNotContainsString('Dashboard Neraca</span>', $content);
        $this->assertStringNotContainsString('Dashboard Utama (Ringkasan)', $content);
    }

    /**
     * Uji 3: Backward compatibility untuk rute /distribusi/dashboard dan /neraca/dashboard.
     */
    public function test_legacy_dashboard_routes_render_single_dashboard(): void
    {
        $superadmin = User::where('role_id', 1)->first();

        $resDist = $this->actingAs($superadmin)->get('/distribusi/dashboard');
        $resDist->assertStatus(200);
        $resDist->assertViewIs('dashboard');

        $resNeraca = $this->actingAs($superadmin)->get('/neraca/dashboard');
        $resNeraca->assertStatus(200);
        $resNeraca->assertViewIs('dashboard');
    }

    /**
     * Uji 4: API /dashboard/data mengembalikan respon JSON lengkap untuk Distribusi Ekspor & Impor.
     */
    public function test_api_dashboard_data_distribusi_ekspor_and_impor(): void
    {
        $superadmin = User::where('role_id', 1)->first();

        // 1. Ekspor Distribusi
        $resEkspor = $this->actingAs($superadmin)->getJson(route('dashboard.data', [
            'tipe_sumber' => 'distribusi',
            'jenis' => 'Ekspor',
            'tahun' => '2026',
        ]));
        $resEkspor->assertStatus(200);
        $resEkspor->assertJsonPath('success', true);
        $resEkspor->assertJsonPath('tipe_sumber', 'distribusi');
        $resEkspor->assertJsonPath('jenis', 'Ekspor');
        $this->assertGreaterThan(0, $resEkspor->json('summary.total_fob'));

        // 2. Impor Distribusi
        $resImpor = $this->actingAs($superadmin)->getJson(route('dashboard.data', [
            'tipe_sumber' => 'distribusi',
            'jenis' => 'Impor',
            'tahun' => '2026',
        ]));
        $resImpor->assertStatus(200);
        $resImpor->assertJsonPath('success', true);
        $resImpor->assertJsonPath('tipe_sumber', 'distribusi');
        $resImpor->assertJsonPath('jenis', 'Impor');
        $this->assertGreaterThan(0, $resImpor->json('summary.total_fob'));
    }

    /**
     * Uji 5: API /dashboard/data mengembalikan respon JSON lengkap untuk Neraca Ekspor & Impor.
     */
    public function test_api_dashboard_data_neraca_ekspor_and_impor(): void
    {
        $superadmin = User::where('role_id', 1)->first();

        // 1. Ekspor Neraca
        $resEkspor = $this->actingAs($superadmin)->getJson(route('dashboard.data', [
            'tipe_sumber' => 'neraca',
            'jenis' => 'Ekspor',
            'tahun' => '2026',
        ]));
        $resEkspor->assertStatus(200);
        $resEkspor->assertJsonPath('success', true);
        $resEkspor->assertJsonPath('tipe_sumber', 'neraca');
        $resEkspor->assertJsonPath('jenis', 'Ekspor');
        $this->assertGreaterThan(0, $resEkspor->json('summary.total_fob'));

        // 2. Impor Neraca
        $resImpor = $this->actingAs($superadmin)->getJson(route('dashboard.data', [
            'tipe_sumber' => 'neraca',
            'jenis' => 'Impor',
            'tahun' => '2026',
        ]));
        $resImpor->assertStatus(200);
        $resImpor->assertJsonPath('success', true);
        $resImpor->assertJsonPath('tipe_sumber', 'neraca');
        $resImpor->assertJsonPath('jenis', 'Impor');
        $this->assertGreaterThan(0, $resImpor->json('summary.total_fob'));
    }

    /**
     * Uji 6: API /dashboard/data menyajikan data Neraca Perdagangan (Trade Balance) komparatif untuk Tab 4.
     */
    public function test_api_dashboard_data_includes_trade_balance(): void
    {
        $superadmin = User::where('role_id', 1)->first();

        $res = $this->actingAs($superadmin)->getJson(route('dashboard.data', [
            'tipe_sumber' => 'distribusi',
            'tahun' => '2026',
        ]));
        $res->assertStatus(200);
        $res->assertJsonStructure([
            'success',
            'trade_balance' => [
                'total_ekspor',
                'total_impor',
                'net_trade',
                'status',
                'has_complete',
                'monthly'
            ]
        ]);

        $this->assertNotEmpty($res->json('trade_balance.monthly'));
        $this->assertEquals('Surplus', $res->json('trade_balance.status'));
        $this->assertGreaterThan(0, $res->json('trade_balance.net_trade'));
    }

    /**
     * Uji 7: RBAC data isolation — Pegawai tidak dapat melihat data berstatus PRIVATE pada dashboard.
     */
    public function test_rbac_pegawai_cannot_see_private_data_on_dashboard(): void
    {
        $pegawai = User::where('role_id', 2)->first();
        $this->assertNotNull($pegawai);

        // Akses dashboard oleh pegawai
        $res = $this->actingAs($pegawai)->get(route('dashboard'));
        $res->assertStatus(200);

        // Pegawai tidak melihat badge Mode Eksekutif
        $res->assertDontSee('Mode Eksekutif: Data RILIS + INTERNAL (PRIVATE)');
        $res->assertSee('Data Resmi Publik (Status: RILIS)');
    }

    /**
     * Uji 8: Halaman Upload Data tidak rusak dan tetap berfungsi bagi Super Admin.
     */
    public function test_upload_data_page_remains_intact_and_accessible_for_superadmin(): void
    {
        $superadmin = User::where('role_id', 1)->first();

        $response = $this->actingAs($superadmin)->get(route('upload-data'));
        $response->assertStatus(200);
        $response->assertSee('Unggah Data');
    }
}
