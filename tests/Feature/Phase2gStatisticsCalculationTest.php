<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DistributionStatisticsService;
use App\Services\StatisticsCalculationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2G.3 — Statistical Integrity, Visibility Enforcement & Distribution Hardening Tests.
 *
 * Seluruh fixture berjalan pada tahun sentinel (2092-2095) di dalam transaksi rollback
 * agar tidak meninggalkan mutasi permanen pada sidatex_testing.
 */
class Phase2gStatisticsCalculationTest extends TestCase
{
    private function insertSyntheticBatchAndMart(
        string $sourceType,
        string $jenis,
        int $tahun,
        int $bulan,
        string $status,
        string $nilai,
        string $berat = '1000.000000',
        bool $inTrash = false,
        bool $restoredFromTrash = false
    ): int {
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();
        $pelabuhan = DB::table('master_pelabuhan')->first();
        $now = now();

        $batchId = (int) DB::table('data_batches')->insertGetId([
            'nama_file' => "sentinel_{$sourceType}_{$jenis}_{$tahun}_{$bulan}_{$status}_" . uniqid() . '.DBF',
            'jenis_data' => $jenis,
            'periode_tahun' => $tahun,
            'periode_bulan' => $bulan,
            'tipe_sumber' => $sourceType,
            'ukuran_file' => '0.01',
            'status' => $status,
            'uploaded_by' => 1,
            'uploaded_at' => $now,
            'released_by' => ($status === 'RILIS') ? 1 : null,
            'released_at' => ($status === 'RILIS') ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $txTable = ($sourceType === 'neraca') ? 'neraca_transactions' : 'distribusi_transactions';
        $txRow = [
            'batch_id' => $batchId,
            'hs_id' => $hs->hs_id,
            'negara_id' => $negara->negara_id,
            'provinsi_id' => 4,
            'kabkota_id' => null,
            'pelabuhan_id' => $pelabuhan->pelabuhan_id,
            'jenis_transaksi' => $jenis,
            'tipe_sumber' => $sourceType,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'berat' => $berat,
            'nilai' => $nilai,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if ($sourceType === 'distribusi') {
            $txRow['provorig'] = '1471';
        } else {
            $txRow['hs_code_raw'] = $hs->hs_code;
            $txRow['mapping_status'] = 'mapped';
        }
        DB::table($txTable)->insert($txRow);

        DB::table('monthly_trade_summaries')->insert([
            'batch_id' => $batchId,
            'status_visibilitas' => ($status === 'RILIS') ? 'RILIS' : 'PRIVATE',
            'tipe_sumber' => $sourceType,
            'jenis_transaksi' => $jenis,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'hs_id' => $hs->hs_id,
            'hs2' => $hs->hs2,
            'mapping_status' => 'mapped',
            'is_migas' => 0,
            'sektor' => 'INDUSTRI PENGOLAHAN',
            'bec_category' => $hs->bec_desc,
            'provorig_key' => '1471',
            'negara_id' => $negara->negara_id,
            'provinsi_id' => 4,
            'pelabuhan_id' => $pelabuhan->pelabuhan_id,
            'pelabuhan_key' => $pelabuhan->pelabuhan_id,
            'total_berat' => $berat,
            'total_nilai' => $nilai,
            'jumlah_transaksi' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inTrash) {
            DB::table('trash')->insert([
                'batch_id' => $batchId,
                'deleted_by' => 1,
                'deleted_at' => $now,
                'restored_at' => $restoredFromTrash ? $now : null,
                'data_snapshot' => json_encode(['batch_id' => $batchId]),
            ]);
        }

        return $batchId;
    }

    /**
     * 1. Role 2 (Pegawai) hanya boleh membaca RILIS; PRIVATE, DRAFT, & Trash wajib terisolasi penuh;
     * Role 1 (Super Admin) & Role 3 (Viewer Eksekutif) dapat membaca RILIS + PRIVATE (tetap menolak DRAFT & Trash).
     */
    public function test_role_visibility_isolation_for_rilis_private_draft_and_trash(): void
    {
        $superAdmin = User::where('role_id', 1)->firstOrFail();
        $pegawai = User::where('role_id', 2)->firstOrFail();
        $viewer = User::where('role_id', 3)->firstOrFail();

        DB::beginTransaction();
        try {
            $year = 2092;
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 1, 'RILIS', '1000000.000000');
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 2, 'PRIVATE', '2000000.000000');
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 3, 'DRAFT', '3000000.000000');
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 4, 'RILIS', '4000000.000000', '1000.000000', true, false);
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 5, 'RILIS', '5000000.000000', '1000.000000', true, true);

            // Role 2 (Pegawai) -> hanya Bulan 1 (1,000,000) + Bulan 5 hasil restore (5,000,000) = 6,000,000
            $resPegawai = $this->actingAs($pegawai)->getJson(route('dashboard.metrics', [
                'tahun' => $year,
                'jenis' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
            ]));
            $resPegawai->assertStatus(200);
            $this->assertEqualsWithDelta(6000000.0, (float) $resPegawai->json('data.total_fob'), 0.01);
            $this->assertNull($resPegawai->json('data.mtom'), 'Bulan 4 berada di Trash sehingga MoM Bulan 5 harus null!');

            // Role 3 (Viewer Eksekutif) -> RILIS (1M + 5M) + PRIVATE (2M) = 8,000,000 (DRAFT & active Trash dikecualikan!)
            $resViewer = $this->actingAs($viewer)->getJson(route('dashboard.metrics', [
                'tahun' => $year,
                'jenis' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
            ]));
            $resViewer->assertStatus(200);
            $this->assertEqualsWithDelta(8000000.0, (float) $resViewer->json('data.total_fob'), 0.01);

            // Role 1 (Super Admin) -> sama dengan Role 3 (8,000,000; DRAFT & active Trash tetap dikecualikan!)
            $resAdmin = $this->actingAs($superAdmin)->getJson(route('dashboard.metrics', [
                'tahun' => $year,
                'jenis' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
            ]));
            $resAdmin->assertStatus(200);
            $this->assertEqualsWithDelta(8000000.0, (float) $resAdmin->json('data.total_fob'), 0.01);

            // Endpoint /dashboard/data untuk Pegawai: summary.mtom/qtoq/yony/ctoc wajib mengikuti RILIS saja
            $resDataPegawai = $this->actingAs($pegawai)->getJson(route('dashboard.data', [
                'tahun' => $year,
                'jenis' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
            ]));
            $resDataPegawai->assertStatus(200);
            $this->assertEqualsWithDelta(6000000.0, (float) $resDataPegawai->json('summary.total_fob'), 0.01);
            $this->assertNull($resDataPegawai->json('summary.mtom'));
            $this->assertNull($resDataPegawai->json('summary.qtoq'));

            // Endpoint /analisis-perdagangan/data harus mengecualikan PRIVATE & Trash pada growth & monthly_trend
            $resAnalisis = $this->actingAs($pegawai)->getJson(route('analisis-perdagangan.data', [
                'tahun' => $year,
                'jenis' => 'Ekspor',
            ]));
            $resAnalisis->assertStatus(200);
            $this->assertEqualsWithDelta(6000000.0, (float) $resAnalisis->json('grand_total_fob'), 0.01);
            $this->assertNull($resAnalisis->json('growth.mtom'));
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 2. Pengujian pertumbuhan saat pembanding kosong (null, bukan 0.0), tahun kosong dengan tahun lalu berisi
     * (null, bukan -100.0), perubahan datar riil ($1000 -> $1000 => 0.0), dan rollover MoM Januari -> Desember Y-1.
     */
    public function test_growth_null_guards_anti_minus_100_real_zero_and_january_rollover(): void
    {
        $statSvc = app(StatisticsCalculationService::class);

        DB::beginTransaction();
        try {
            // Tahun 2093: hanya Des = 1,000,000
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', 2093, 12, 'RILIS', '1000000.000000');

            // Tahun 2093 hanya 1 bulan -> semua pertumbuhan harus null (bukan 0.0)
            $m2093 = $statSvc->getOfficialDashboardMetrics(2093, 'Ekspor', 'distribusi', ['RILIS']);
            $this->assertTrue($m2093['has_data']);
            $this->assertNull($m2093['mtom']);
            $this->assertNull($m2093['qtoq']);
            $this->assertNull($m2093['yony']);
            $this->assertNull($m2093['ctoc']);

            // Tahun 2094 kosong padahal 2093 berisi -> has_data false, semua growth HARUS null (bukan -100.0!)
            $m2094Empty = $statSvc->getOfficialDashboardMetrics(2094, 'Ekspor', 'distribusi', ['RILIS']);
            $this->assertFalse($m2094Empty['has_data']);
            $this->assertNull($m2094Empty['mtom']);
            $this->assertNull($m2094Empty['qtoq']);
            $this->assertNull($m2094Empty['yony']);
            $this->assertNull($m2094Empty['ctoc']);

            // Tambahkan 2094-01 = 1,000,000 -> MoM Januari vs Desember 2093 (1M -> 1M) harus tepat 0.0!
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', 2094, 1, 'RILIS', '1000000.000000');
            $m2094Jan = $statSvc->getOfficialDashboardMetrics(2094, 'Ekspor', 'distribusi', ['RILIS']);
            $this->assertSame(0.0, $m2094Jan['mtom'], 'Perubahan datar riil (1M -> 1M) harus mengembalikan tepat 0.0.');
            $this->assertNull($m2094Jan['qtoq']);
            $this->assertNull($m2094Jan['yony']);
            $this->assertNull($m2094Jan['ctoc']);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 3. Kebijakan B QoQ: Valid HANYA jika kedua triwulan lengkap 3 bulan penuh;
     * celah bulan internal (mis. Jul + Sep tanpa Agu) wajib mengembalikan null pada service & tabel dashboard.
     */
    public function test_qoq_policy_b_requires_full_three_months_and_rejects_internal_gaps(): void
    {
        $statSvc = app(StatisticsCalculationService::class);
        $superAdmin = User::where('role_id', 1)->firstOrFail();

        DB::beginTransaction();
        try {
            $year = 2095;
            // Q1 penuh (Jan, Feb, Mar): total = 300
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 1, 'RILIS', '100.000000');
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 2, 'RILIS', '100.000000');
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 3, 'RILIS', '100.000000');

            // Q2 parsial (Apr & Mei saja): QoQ harus null!
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 4, 'RILIS', '150.000000');
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 5, 'RILIS', '150.000000');
            $mPartialQ2 = $statSvc->getOfficialDashboardMetrics($year, 'Ekspor', 'distribusi', ['RILIS']);
            $this->assertNull($mPartialQ2['qtoq'], 'Q2 yang belum lengkap 3 bulan wajib menghasilkan qtoq = null.');

            // Lengkapi Q2 (Jun = 150 -> Q2 total = 450 vs Q1 = 300 => +50.0%)
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 6, 'RILIS', '150.000000');
            $mFullQ2 = $statSvc->getOfficialDashboardMetrics($year, 'Ekspor', 'distribusi', ['RILIS']);
            $this->assertSame(50.0, $mFullQ2['qtoq'], 'Q2 penuh (450) vs Q1 penuh (300) harus +50.0%.');

            // Tambahkan Q3 dengan celah bulan internal: Jul = 200, Agu = KOSONG, Sep = 250
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 7, 'RILIS', '200.000000');
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 9, 'RILIS', '250.000000');
            $mGapQ3 = $statSvc->getOfficialDashboardMetrics($year, 'Ekspor', 'distribusi', ['RILIS']);
            $this->assertNull($mGapQ3['qtoq'], 'Q3 dengan celah bulan internal (tanpa Agu) wajib qtoq = null (tanpa fallback ke Q2).');
            $this->assertNull($mGapQ3['mtom'], 'Sep tanpa Agu wajib mtom = null.');

            // Verifikasi tabel growthMomTable & growthQoqTable pada /dashboard/data
            $res = $this->actingAs($superAdmin)->getJson(route('dashboard.data', [
                'tahun' => $year,
                'jenis' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
            ]));
            $res->assertStatus(200);

            $momTable = collect($res->json('growth_tables.mom'))->keyBy('periode');
            $this->assertNull($momTable->get('Januari')['growth']);
            $this->assertNotNull($momTable->get('Februari')['growth']);
            $this->assertEqualsWithDelta(0.0, (float) $momTable->get('Februari')['growth'], 0.0001);
            $this->assertNull($momTable->get('September')['growth'], 'September tanpa Agustus tidak boleh membandingkan terhadap Juli.');

            $qoqTable = collect($res->json('growth_tables.qoq'))->keyBy('periode');
            $this->assertNull($qoqTable->get('Triwulan I (Jan-Mar)')['growth']);
            $this->assertNotNull($qoqTable->get('Triwulan II (Apr-Jun)')['growth']);
            $this->assertEqualsWithDelta(50.0, (float) $qoqTable->get('Triwulan II (Apr-Jun)')['growth'], 0.0001);
            $this->assertNull($qoqTable->get('Triwulan III (Jul-Sep)')['growth'], 'Triwulan III dengan celah Agustus wajib growth = null.');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 4. Perlindungan single-sided NULL pada Trade Balance (DashboardController::data & DistributionStatisticsService).
     */
    public function test_single_sided_trade_balance_returns_null_and_complete_month_calculates_surplus(): void
    {
        $superAdmin = User::where('role_id', 1)->firstOrFail();
        $distStatSvc = app(DistributionStatisticsService::class);

        DB::beginTransaction();
        try {
            $year = 2096;
            // Bulan 1: hanya Ekspor (single-sided)
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 1, 'RILIS', '5000000.000000');
            // Bulan 2: hanya Impor (single-sided)
            $this->insertSyntheticBatchAndMart('distribusi', 'Impor', $year, 2, 'RILIS', '2000000.000000');
            // Bulan 3: lengkap Ekspor (6M) & Impor (1M) => Surplus +5M
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 3, 'RILIS', '6000000.000000');
            $this->insertSyntheticBatchAndMart('distribusi', 'Impor', $year, 3, 'RILIS', '1000000.000000');

            // Cek DistributionStatisticsService::getDistributionTradeBalance()
            $tbJan = $distStatSvc->getDistributionTradeBalance($year, 1);
            $this->assertNull($tbJan['neraca_bulanan_juta']);
            $this->assertFalse($tbJan['has_complete_bulanan']);
            $this->assertEquals('Data Impor Belum Tersedia', $tbJan['status_bulanan']);

            $tbFeb = $distStatSvc->getDistributionTradeBalance($year, 2);
            $this->assertNull($tbFeb['neraca_bulanan_juta']);
            $this->assertFalse($tbFeb['has_complete_bulanan']);
            $this->assertEquals('Data Ekspor Belum Tersedia', $tbFeb['status_bulanan']);

            $tbMar = $distStatSvc->getDistributionTradeBalance($year, 3);
            $this->assertTrue($tbMar['has_complete_bulanan']);
            $this->assertEqualsWithDelta(5.0, $tbMar['neraca_bulanan_juta'], 0.01);
            $this->assertEquals('Surplus', $tbMar['status_bulanan']);

            // Cek /dashboard/data monthlyTradeBalance single-sided NULL
            $res = $this->actingAs($superAdmin)->getJson(route('dashboard.data', [
                'tahun' => $year,
                'jenis' => 'Neraca',
                'tipe_sumber' => 'distribusi',
            ]));
            $res->assertStatus(200);
            $monthly = collect($res->json('trade_balance.monthly'))->keyBy('bulan');

            $this->assertNull($monthly->get(1)['neraca']);
            $this->assertFalse($monthly->get(1)['has_complete']);
            $this->assertNull($monthly->get(2)['neraca']);
            $this->assertFalse($monthly->get(2)['has_complete']);
            $this->assertTrue($monthly->get(3)['has_complete']);
            $this->assertEqualsWithDelta(5000000.0, (float) $monthly->get(3)['neraca'], 0.01);
            $this->assertEquals('Surplus', $monthly->get(3)['status']);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 5. Read-side visibility guard pada DistributionStatisticsService::getOriginDistribution()
     * mencegah kebocoran baris monthly_origin_trade_summaries untuk periode PRIVATE/orphan/Trash.
     */
    public function test_origin_distribution_read_side_guard_blocks_private_and_orphan_rows(): void
    {
        $distStatSvc = app(DistributionStatisticsService::class);
        $pegawai = User::where('role_id', 2)->firstOrFail();

        DB::beginTransaction();
        try {
            $year = 2097;
            // Buat batch PRIVATE (bukan RILIS) + sisipkan baris langsung ke monthly_origin_trade_summaries
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 4, 'PRIVATE', '9000000.000000');
            DB::table('monthly_origin_trade_summaries')->insert([
                'tahun' => $year,
                'bulan' => 4,
                'jenis_transaksi' => 'ekspor',
                'provorig' => '1471',
                'is_riau' => true,
                'hs2' => '15',
                'sektor' => 'INDUSTRI PENGOLAHAN',
                'is_migas' => false,
                'total_nilai' => '9000000.000000',
                'total_berat' => '5000.000000',
                'jumlah_transaksi' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Read-side guard wajib memblokir baris PRIVATE/orphan tersebut
            $origin = $distStatSvc->getOriginDistribution($year, 4);
            $this->assertEquals(0, $origin['total_fob_juta_usd']);
            $this->assertEmpty($origin['daerah']);

            // Endpoint /distribusi/metrics oleh Pegawai (Role 2) juga wajib mengembalikan kosong untuk periode PRIVATE
            $res = $this->actingAs($pegawai)->getJson(route('distribusi.metrics', [
                'tahun' => $year,
                'bulan' => 4,
            ]));
            $res->assertStatus(200);
            $this->assertEquals(0, $res->json('origin_wilayah.total_fob_juta_usd'));
            $this->assertEmpty($res->json('origin_wilayah.daerah'));

            // Batch RILIS yang berada di Trash juga wajib diblokir oleh read-side guard
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 5, 'RILIS', '7000000.000000', '4000.000000', true, false);
            DB::table('monthly_origin_trade_summaries')->insert([
                'tahun' => $year,
                'bulan' => 5,
                'jenis_transaksi' => 'ekspor',
                'provorig' => '1471',
                'is_riau' => true,
                'hs2' => '15',
                'sektor' => 'INDUSTRI PENGOLAHAN',
                'is_migas' => false,
                'total_nilai' => '7000000.000000',
                'total_berat' => '4000.000000',
                'jumlah_transaksi' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $originTrashed = $distStatSvc->getOriginDistribution($year, 5);
            $this->assertEquals(0, $originTrashed['total_fob_juta_usd']);
            $this->assertEmpty($originTrashed['daerah']);

            // Batch RILIS aktif (bukan Trash) tetap dapat dibaca dengan normal
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', $year, 6, 'RILIS', '8000000.000000');
            DB::table('monthly_origin_trade_summaries')->insert([
                'tahun' => $year,
                'bulan' => 6,
                'jenis_transaksi' => 'ekspor',
                'provorig' => '1471',
                'is_riau' => true,
                'hs2' => '15',
                'sektor' => 'INDUSTRI PENGOLAHAN',
                'is_migas' => false,
                'total_nilai' => '8000000.000000',
                'total_berat' => '4000.000000',
                'jumlah_transaksi' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $originRilis = $distStatSvc->getOriginDistribution($year, 6);
            $this->assertEqualsWithDelta(8.0, $originRilis['total_fob_juta_usd'], 0.01);
            $this->assertNotEmpty($originRilis['daerah']);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 6. Keamanan respons HTTP 500 pada DistributionController::getMetrics():
     * tidak boleh membocorkan detail SQL, path server, atau pesan exception internal.
     */
    public function test_distribusi_metrics_http_500_sanitizes_internal_error_details(): void
    {
        $pegawai = User::where('role_id', 2)->firstOrFail();

        $mockSvc = $this->createMock(DistributionStatisticsService::class);
        $mockSvc->method('getSummaryMetrics')
            ->willThrowException(new \RuntimeException("SQLSTATE[42S02]: Table 'sidatex_secret.x' doesn't exist at D:\\laragon\\www\\sidatex\\app\\Services\\Internal.php"));
        $this->app->instance(DistributionStatisticsService::class, $mockSvc);

        $res = $this->actingAs($pegawai)->getJson(route('distribusi.metrics', [
            'tahun' => 2026,
            'bulan' => 9,
        ]));

        $res->assertStatus(500);
        $res->assertJson([
            'success' => false,
            'message' => 'Terjadi kesalahan internal saat memuat metrik distribusi.',
        ]);

        $body = $res->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('sidatex_secret', $body);
        $this->assertStringNotContainsString('D:\\laragon', $body);
    }

    /**
     * 7. Dropdown tahun pada DashboardController::index() tidak membocorkan tahun PRIVATE/Trash ke Role 2 (Pegawai).
     */
    public function test_dashboard_index_years_exclude_private_and_trash_for_pegawai(): void
    {
        $pegawai = User::where('role_id', 2)->firstOrFail();

        DB::beginTransaction();
        try {
            // Tahun sentinel 2097 hanya memiliki batch PRIVATE & Trash (0 RILIS)
            $this->insertSyntheticBatchAndMart('distribusi', 'Ekspor', 2097, 6, 'PRIVATE', '1000000.000000');
            $this->insertSyntheticBatchAndMart('neraca', 'Ekspor', 2097, 7, 'RILIS', '1000000.000000', '1000.000000', true, false);

            $res = $this->actingAs($pegawai)->get(route('dashboard'));
            $res->assertStatus(200);
            $availableYears = $res->viewData('availableYears');

            $this->assertNotContains(2097, $availableYears, 'Tahun PRIVATE/Trash tidak boleh bocor ke dropdown Pegawai (Role 2).');
            $this->assertNotContains(2025, $availableYears, 'Tahun historis PRIVATE 2025 (Keputusan A-1) tidak boleh bocor ke dropdown Pegawai (Role 2).');
            $this->assertContains(2026, $availableYears, 'Tahun 2026 memiliki batch RILIS (#70, #71) dan harus tersedia.');
        } finally {
            DB::rollBack();
        }
    }
}
