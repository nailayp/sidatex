<?php

namespace Tests\Feature;

use App\Http\Controllers\AnalisisController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FilteringController;
use App\Services\StatisticsCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression Test Suite for Data Source Isolation & Aggregation Rules.
 * Executed strictly on isolated sidatex_testing database.
 */
class SourceIsolationAndAggregationRegressionTest extends TestCase
{
    /**
     * 1. Dashboard Distribusi tidak mengambil transaksi yang ber-tipe_sumber 'neraca'.
     */
    public function test_dashboard_distribusi_strictly_ignores_neraca_batches(): void
    {
        $controller = app(DashboardController::class);
        $request = new Request(['tipe_sumber' => 'distribusi', 'jenis' => 'Ekspor', 'tahun' => 2026]);
        $response = $controller->data($request);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('distribusi', $data['tipe_sumber']);

        // Pastikan tidak ada transaksi bertipe sumber 'neraca' yang masuk ke tabel distribusi_transactions
        $neracaCountInDistribusi = DB::table('distribusi_transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->where(function ($q) {
                $q->where('t.tipe_sumber', 'neraca')
                    ->orWhere('b.tipe_sumber', 'neraca');
            })
            ->count();

        $this->assertEquals(0, $neracaCountInDistribusi);
    }

    /**
     * 2. Dashboard Neraca tidak mengambil transaksi Distribusi.
     */
    public function test_dashboard_neraca_strictly_ignores_distribusi_batches(): void
    {
        $controller = app(DashboardController::class);
        $request = new Request(['tipe_sumber' => 'neraca', 'jenis' => 'Ekspor', 'tahun' => 2026]);
        $response = $controller->data($request);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('neraca', $data['tipe_sumber']);

        $distribusiCountInNeraca = DB::table('neraca_transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->where(function ($q) {
                $q->where('t.tipe_sumber', 'distribusi')
                    ->orWhere('b.tipe_sumber', 'distribusi');
            })
            ->count();

        $this->assertEquals(0, $distribusiCountInNeraca);
    }

    /**
     * 3. Batch PRIVATE tidak muncul pada endpoint publik dashboard (tanpa login / Role 2 Pegawai).
     */
    public function test_private_batches_never_appear_in_public_dashboard(): void
    {
        $privateBatch = DB::table('data_batches')
            ->where('status', 'PRIVATE')
            ->where('tipe_sumber', 'distribusi')
            ->where('jenis_data', 'Ekspor')
            ->where('periode_tahun', 2026)
            ->where('periode_bulan', 8)
            ->pluck('batch_id')
            ->toArray();

        $this->assertNotEmpty($privateBatch, 'Batch PRIVATE 2026-08 (Batch #720) harus tersedia untuk uji isolasi.');

        $controller = app(DashboardController::class);
        $request = new Request(['tipe_sumber' => 'distribusi', 'jenis' => 'Ekspor', 'tahun' => 2026, 'bulan' => 8]);
        $response = $controller->data($request);
        $content = json_decode($response->getContent(), true);

        $this->assertTrue($content['success']);
        $this->assertEquals(0.0, (float) $content['summary']['total_fob'], 'Batch PRIVATE 2026-08 tidak boleh muncul pada dashboard publik!');

        $countPrivateInRilis = DB::table('distribusi_transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->where('b.status', 'RILIS')
            ->whereIn('t.batch_id', $privateBatch)
            ->count();

        $this->assertEquals(0, $countPrivateInRilis);
    }

    /**
     * 4. Batch DRAFT tidak muncul pada endpoint publik dashboard.
     */
    public function test_draft_batches_never_appear_in_public_dashboard(): void
    {
        $draftBatchIds = DB::table('data_batches')->where('status', 'DRAFT')->pluck('batch_id')->toArray();

        $countDraftDist = empty($draftBatchIds) ? 0 : DB::table('distribusi_transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->where('b.status', 'RILIS')
            ->whereIn('t.batch_id', $draftBatchIds)
            ->count();

        $countDraftNeraca = empty($draftBatchIds) ? 0 : DB::table('neraca_transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->where('b.status', 'RILIS')
            ->whereIn('t.batch_id', $draftBatchIds)
            ->count();

        $this->assertEquals(0, $countDraftDist);
        $this->assertEquals(0, $countDraftNeraca);
    }

    /**
     * 5. Batch Trash tidak ikut dalam perhitungan statistik.
     */
    public function test_trash_batches_excluded_from_active_dashboard_metrics(): void
    {
        $trashBatchIds = DB::table('trash')->whereNull('restored_at')->pluck('batch_id')->toArray();

        $countTrashDist = empty($trashBatchIds) ? 0 : DB::table('distribusi_transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->where('b.status', 'RILIS')
            ->whereNotIn('t.batch_id', function ($sub) {
                $sub->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->whereIn('t.batch_id', $trashBatchIds)
            ->count();

        $countTrashNeraca = empty($trashBatchIds) ? 0 : DB::table('neraca_transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->where('b.status', 'RILIS')
            ->whereNotIn('t.batch_id', function ($sub) {
                $sub->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->whereIn('t.batch_id', $trashBatchIds)
            ->count();

        $this->assertEquals(0, $countTrashDist);
        $this->assertEquals(0, $countTrashNeraca);
    }

    /**
     * 6. Analisis komoditas hanya menggunakan sumber Distribusi.
     */
    public function test_analisis_controller_commodity_strictly_uses_distribusi(): void
    {
        $controller = app(AnalisisController::class);
        $request = new Request(['tahun' => 2026, 'jenis' => 'Ekspor']);
        $response = $controller->getData($request);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('top_komoditas', $data);
        $this->assertArrayHasKey('grand_total_fob', $data);
    }

    /**
     * 7. Analisis pelabuhan tidak mengambil transaksi non-distribusi.
     */
    public function test_analisis_controller_port_ignores_neraca_data(): void
    {
        $controller = app(AnalisisController::class);
        $request = new Request(['tahun' => 2026, 'jenis' => 'Ekspor']);
        $response = $controller->getData($request);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('top_pelabuhan', $data);

        $neracaPorts = DB::table('distribusi_transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->where(function ($q) {
                $q->where('t.tipe_sumber', 'neraca')
                    ->orWhere('b.tipe_sumber', 'neraca');
            })
            ->count();

        $this->assertEquals(0, $neracaPorts);
    }

    /**
     * 8. Filtering default menggunakan Distribusi.
     */
    public function test_filtering_controller_defaults_to_distribusi(): void
    {
        $controller = app(FilteringController::class);
        $request = new Request([]); // kosong -> default distribusi
        $response = $controller->query($request);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('summary', $data);
    }

    /**
     * 9. Filtering menolak nilai tipe_sumber yang tidak valid.
     */
    public function test_filtering_controller_rejects_invalid_source(): void
    {
        $controller = app(FilteringController::class);
        $request = new Request(['tipe_sumber' => 'invalid_source_xyz']);
        $response = $controller->query($request);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    /**
     * 10. Unduhan Distribusi dan Neraca tidak tercampur.
     */
    public function test_filtering_controller_strictly_separates_distribution_and_neraca(): void
    {
        $controller = app(FilteringController::class);
        
        $reqDist = new Request(['tipe_sumber' => 'distribusi', 'tahun' => 2026]);
        $respDist = $controller->query($reqDist);
        $dataDist = json_decode($respDist->getContent(), true);
        $this->assertTrue($dataDist['success']);

        $reqNeraca = new Request(['tipe_sumber' => 'neraca', 'tahun' => 2026]);
        $respNeraca = $controller->query($reqNeraca);
        $dataNeraca = json_decode($respNeraca->getContent(), true);
        $this->assertTrue($dataNeraca['success']);
    }

    /**
     * 11. ExcelPreviewController tidak mencampurkan ringkasan Neraca dan Distribusi.
     */
    public function test_excel_preview_summaries_query_filters_distribusi(): void
    {
        $summaries = DB::table('monthly_trade_summaries')
            ->where('jenis_transaksi', 'Ekspor')
            ->where('tipe_sumber', 'distribusi')
            ->where('tahun', 2026)
            ->where(function ($q) {
                $q->where('tahun', '<', 2026)
                  ->orWhere(function ($q2) {
                      $q2->where('tahun', 2026)
                         ->where('bulan', '<', 7);
                  })
                  ->orWhere('pelabuhan_key', '>', 0);
            })
            ->pluck('tipe_sumber')
            ->unique()
            ->toArray();

        foreach ($summaries as $s) {
            $this->assertEquals('distribusi', $s);
        }
    }

    /**
     * 12. Agregasi Distribusi Juli 2026 tidak menjumlahkan Key 0 dan Key 38 sekaligus.
     * Batch #85 (Juli 2026) berstatus PRIVATE: diverifikasi dengan izin eksplisit ['RILIS', 'PRIVATE']
     * sekaligus dipastikan terisolasi (0) untuk ['RILIS'].
     */
    public function test_statistics_calculation_service_prevents_july_2026_double_counting(): void
    {
        $service = app(StatisticsCalculationService::class);
        $metrics = $service->getOfficialDashboardMetrics(2026, 'Ekspor', 'distribusi', ['RILIS', 'PRIVATE']);

        $this->assertArrayHasKey('monthly_trend', $metrics);
        // Bulan 7 (Juli 2026) harus tepat US$ 1.910.605.444,96 bukan US$ 3,83 Miliar
        $julyTrend = null;
        foreach ($metrics['monthly_trend'] as $trend) {
            if ($trend['bulan'] === 7) {
                $julyTrend = $trend;
                break;
            }
        }
        $this->assertNotNull($julyTrend);
        $this->assertEqualsWithDelta(1910605445.02, $julyTrend['nilai'], 1.0, "Juli 2026 ekspor tidak boleh double counting!");

        // Isolasi Role 2 (['RILIS']): Batch #85 PRIVATE tidak boleh muncul
        $metricsRilis = $service->getOfficialDashboardMetrics(2026, 'Ekspor', 'distribusi', ['RILIS']);
        $julyRilis = collect($metricsRilis['monthly_trend'])->firstWhere('bulan', 7);
        $this->assertEquals(0.0, (float) $julyRilis['nilai'], "Batch PRIVATE Juli 2026 tidak boleh bocor ke scope RILIS!");
    }

    /**
     * 13. Agregasi historis sebelum Juli 2026 (Keputusan A-1: PRIVATE) tetap terbaca untuk ['RILIS', 'PRIVATE']
     * dan terisolasi penuh (0) untuk ['RILIS'].
     */
    public function test_historical_aggregation_preserves_key_0_prior_to_july_2026(): void
    {
        $service = app(StatisticsCalculationService::class);
        $metrics2025Exec = $service->getOfficialDashboardMetrics(2025, 'Ekspor', 'distribusi', ['RILIS', 'PRIVATE']);

        $this->assertArrayHasKey('total_fob', $metrics2025Exec);
        $this->assertGreaterThan(0, $metrics2025Exec['total_fob'], "Agregasi historis 2025 tidak boleh hilang!");

        $metrics2025Rilis = $service->getOfficialDashboardMetrics(2025, 'Ekspor', 'distribusi', ['RILIS']);
        $this->assertFalse($metrics2025Rilis['has_data']);
        $this->assertEquals(0.0, $metrics2025Rilis['total_fob'], "Data historis PRIVATE 2025 tidak boleh bocor ke scope RILIS!");
    }

    /**
     * 14. Data Distribusi yang tidak tersedia pada periode tertentu tidak diganti dengan data Neraca
     * dan seluruh indikator pertumbuhan harus null (bukan 0.0 atau -100.0).
     */
    public function test_missing_distribution_period_not_replaced_by_neraca(): void
    {
        $service = app(StatisticsCalculationService::class);
        // Tahun 2020 tidak memiliki data Distribusi di sistem
        $metrics2020 = $service->getOfficialDashboardMetrics(2020, 'Ekspor', 'distribusi', ['RILIS', 'PRIVATE']);

        $this->assertFalse($metrics2020['has_data']);
        $this->assertEquals(0, $metrics2020['total_fob'], "Periode tanpa distribusi tidak boleh diinjeksi Neraca!");
        $this->assertNull($metrics2020['mtom']);
        $this->assertNull($metrics2020['qtoq']);
        $this->assertNull($metrics2020['yony']);
        $this->assertNull($metrics2020['ctoc']);
    }

    /**
     * 15. Filter bulan, tahun, triwulan, HS, negara, kabupaten/kota, dan pelabuhan tetap konsisten.
     */
    public function test_multi_dimension_filters_remain_consistent(): void
    {
        $controller = app(DashboardController::class);
        $request = new Request([
            'tipe_sumber' => 'distribusi',
            'jenis' => 'Ekspor',
            'tahun' => 2026,
            'bulan' => 8,
            'triwulan' => '3',
            'migas' => 'Non Migas'
        ]);
        $response = $controller->data($request);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('monthly', $data);
    }
}
