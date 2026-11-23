<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DistributionStatisticsService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2G.5 — Import/Export MoM, YoY, C-to-C Statistical Integrity & Distribution Cleanup Tests.
 *
 * Seluruh fixture sintetis berjalan pada tahun sentinel (2092-2096) di dalam transaksi rollback
 * agar tidak menimbulkan mutasi permanen pada database sidatex_testing.
 */
class Phase2gDistributionGrowthAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $activeDb = DB::connection()->getDatabaseName();
        $this->assertContains($activeDb, ['sidatex_testing', ':memory:'], "UNSAFE DATABASE: '{$activeDb}'");
    }

    private function insertDistribusiBatch(
        string $jenis,
        int $tahun,
        int $bulan,
        string $status,
        array $txSpecs,
        bool $inTrash = false,
        bool $restoredFromTrash = false
    ): int {
        $hsNonMigas = DB::table('master_hs')->where('oilgrp_code', 0)->first();
        $hsMigas = DB::table('master_hs')->where('oilgrp_code', 1)->first();
        $negara = DB::table('master_negara')->first();
        $pelabuhan = DB::table('master_pelabuhan')->first();
        $now = now();

        $batchId = (int) DB::table('data_batches')->insertGetId([
            'nama_file' => "sentinel_2g5_{$jenis}_{$tahun}_{$bulan}_{$status}_" . uniqid() . '.DBF',
            'jenis_data' => $jenis,
            'periode_tahun' => $tahun,
            'periode_bulan' => $bulan,
            'tipe_sumber' => 'distribusi',
            'ukuran_file' => '0.01',
            'status' => $status,
            'uploaded_by' => 1,
            'uploaded_at' => $now,
            'released_by' => ($status === 'RILIS') ? 1 : null,
            'released_at' => ($status === 'RILIS') ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($txSpecs as $spec) {
            $isMigas = (bool) ($spec['migas'] ?? false);
            $hs = $isMigas ? $hsMigas : $hsNonMigas;

            DB::table('distribusi_transactions')->insert([
                'batch_id' => $batchId,
                'hs_id' => $hs->hs_id,
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'kabkota_id' => null,
                'provorig' => '1471',
                'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                'jenis_transaksi' => $jenis,
                'tipe_sumber' => 'distribusi',
                'bulan' => $bulan,
                'tahun' => $tahun,
                'berat' => $spec['berat'] ?? '1000.000000',
                'nilai' => $spec['nilai'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

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
     * 1. Payload LIVE sidatex_testing (Role 2 Pegawai):
     * - 2026-09 Impor (Batch #74 masih PRIVATE): mtom_total/nonmigas/migas HARUS null (bukan -100!).
     * - 2026-10 Ekspor (belum ada batch): mtom_total/nonmigas/migas HARUS null & top_commodities.items === [].
     * - 2026-08 Ekspor (Batch #720 masih PRIVATE): top_commodities.items === [] (tanpa 'Lainnya' semu).
     * - 2026-09 Ekspor (Batch #71 RILIS): top_commodities.items tetap 4 item (Top 3 + Lainnya).
     */
    public function test_live_missing_periods_return_null_instead_of_fake_minus_100_and_no_phantom_lainnya(): void
    {
        $pegawai = User::where('role_id', 2)->firstOrFail();

        // 2026-09: Ekspor RILIS (#71), Impor PRIVATE (#74)
        $resSep = $this->actingAs($pegawai)->getJson(route('distribusi.metrics', ['tahun' => 2026, 'bulan' => 9]));
        $resSep->assertStatus(200);
        $this->assertFalse($resSep->json('impor.bulan_ini.has_data'));
        $this->assertNull($resSep->json('impor.pertumbuhan.mtom_total'));
        $this->assertNull($resSep->json('impor.pertumbuhan.mtom_nonmigas'));
        $this->assertNull($resSep->json('impor.pertumbuhan.mtom_migas'));
        $this->assertNull($resSep->json('impor.pertumbuhan.ctoc_total'));
        $this->assertTrue($resSep->json('ekspor.bulan_ini.has_data'));
        $this->assertCount(4, $resSep->json('top_commodities.items'));
        $this->assertSame('XX', $resSep->json('top_commodities.items.3.hs2'));

        // 2026-10: Ekspor & Impor belum ada -> tidak boleh ada -100 semu dan tidak ada item 'Lainnya' semu
        $resOct = $this->actingAs($pegawai)->getJson(route('distribusi.metrics', ['tahun' => 2026, 'bulan' => 10]));
        $resOct->assertStatus(200);
        $this->assertFalse($resOct->json('ekspor.bulan_ini.has_data'));
        $this->assertNull($resOct->json('ekspor.pertumbuhan.mtom_total'));
        $this->assertNull($resOct->json('ekspor.pertumbuhan.mtom_nonmigas'));
        $this->assertNull($resOct->json('ekspor.pertumbuhan.mtom_migas'));
        $this->assertSame([], $resOct->json('top_commodities.items'));

        // 2026-08: Ekspor PRIVATE (#720), Impor RILIS (#70) -> top_commodities Ekspor harus kosong []
        $resAug = $this->actingAs($pegawai)->getJson(route('distribusi.metrics', ['tahun' => 2026, 'bulan' => 8]));
        $resAug->assertStatus(200);
        $this->assertFalse($resAug->json('ekspor.bulan_ini.has_data'));
        $this->assertTrue($resAug->json('impor.bulan_ini.has_data'));
        $this->assertSame([], $resAug->json('top_commodities.items'));
    }

    /**
     * 2. Kondisi data bulan berjalan kosong, bulan pembanding kosong, & pembanding riil nol (Ekspor & Impor).
     */
    public function test_missing_periods_and_zero_comparison_return_null_for_ekspor_and_impor(): void
    {
        $svc = new DistributionStatisticsService();

        DB::beginTransaction();
        try {
            $year = 2092;
            foreach (['Ekspor', 'Impor'] as $jenis) {
                // Bulan 1 RILIS berisi data migas + nonmigas
                $this->insertDistribusiBatch($jenis, $year, 1, 'RILIS', [
                    ['nilai' => '1000000.000000', 'migas' => false],
                    ['nilai' => '500000.000000', 'migas' => true],
                ]);
                // Bulan 4 RILIS dengan nilai agregat benar-benar 0 (sebagai pembanding nol untuk Bulan 5)
                $this->insertDistribusiBatch($jenis, $year, 4, 'RILIS', [
                    ['nilai' => '0.000000', 'migas' => false],
                ]);
                // Bulan 5 RILIS berisi data positif
                $this->insertDistribusiBatch($jenis, $year, 5, 'RILIS', [
                    ['nilai' => '750000.000000', 'migas' => false],
                ]);

                // A. Bulan pembanding kosong (Bulan 1 tanpa Des Y-1) -> seluruh pertumbuhan null
                $m1 = $svc->getSummaryMetrics($year, 1, strtolower($jenis));
                $this->assertTrue($m1['bulan_ini']['has_data']);
                $this->assertNull($m1['pertumbuhan']['mtom_total']);
                $this->assertNull($m1['pertumbuhan']['mtom_nonmigas']);
                $this->assertNull($m1['pertumbuhan']['mtom_migas']);

                // B. Bulan berjalan kosong (Bulan 2 tanpa transaksi, padahal Bulan 1 berisi) -> HARUS null (bukan -100!)
                $m2 = $svc->getSummaryMetrics($year, 2, strtolower($jenis));
                $this->assertFalse($m2['bulan_ini']['has_data']);
                $this->assertNull($m2['pertumbuhan']['mtom_total']);
                $this->assertNull($m2['pertumbuhan']['mtom_nonmigas']);
                $this->assertNull($m2['pertumbuhan']['mtom_migas']);

                // C. Nilai pembanding benar-benar nol (Bulan 5 vs Bulan 4 = 0) -> HARUS null (tanpa Infinity/NaN)
                $m5 = $svc->getSummaryMetrics($year, 5, strtolower($jenis));
                $this->assertTrue($m5['bulan_ini']['has_data']);
                $this->assertNull($m5['pertumbuhan']['mtom_total']);
                $this->assertNull($m5['pertumbuhan']['mtom_nonmigas']);
                $this->assertNull($m5['pertumbuhan']['mtom_migas']);
            }
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 3. Nilai periode berjalan benar-benar 0 pada bulan RILIS yang terobservasi:
     * - Sub-komponen migas = 0 (nonmigas sama) -> mtom_migas = -100.0 (SAH!), mtom_nonmigas = 0.0 (datar riil!).
     * - Agregat total = 0 dengan observasi RILIS (COUNT(*) > 0) -> mtom_total = -100.0 (SAH!).
     */
    public function test_observed_zero_in_released_period_produces_legitimate_minus_100_and_flat_produces_zero(): void
    {
        $svc = new DistributionStatisticsService();

        DB::beginTransaction();
        try {
            $year = 2093;
            foreach (['Ekspor', 'Impor'] as $jenis) {
                // Bulan 1 RILIS: Non-Migas 1M, Migas 500K (Total 1.5M)
                $this->insertDistribusiBatch($jenis, $year, 1, 'RILIS', [
                    ['nilai' => '1000000.000000', 'migas' => false],
                    ['nilai' => '500000.000000', 'migas' => true],
                ]);
                // Bulan 2 RILIS: Non-Migas tetap 1M, Migas benar-benar 0 (tidak ada pengapalan migas di bulan rilis)
                $this->insertDistribusiBatch($jenis, $year, 2, 'RILIS', [
                    ['nilai' => '1000000.000000', 'migas' => false],
                ]);
                // Bulan 3 RILIS: Observasi ada (row_count = 1) dengan nilai agregat benar-benar 0
                $this->insertDistribusiBatch($jenis, $year, 3, 'RILIS', [
                    ['nilai' => '0.000000', 'migas' => false],
                ]);

                $m2 = $svc->getSummaryMetrics($year, 2, strtolower($jenis));
                $this->assertTrue($m2['bulan_ini']['has_data']);
                $this->assertSame(0.0, $m2['pertumbuhan']['mtom_nonmigas'], "{$jenis}: perubahan datar riil wajib 0.0.");
                $this->assertSame(-100.0, $m2['pertumbuhan']['mtom_migas'], "{$jenis}: penurunan riil migas ke 0 pada bulan rilis sah -100.0.");
                $this->assertSame(-33.33, $m2['pertumbuhan']['mtom_total']);

                $m3 = $svc->getSummaryMetrics($year, 3, strtolower($jenis));
                $this->assertTrue($m3['bulan_ini']['has_data']);
                $this->assertSame(-100.0, $m3['pertumbuhan']['mtom_total'], "{$jenis}: agregat riil 0 dengan observasi sah menghasilkan -100.0.");
                $this->assertSame(-100.0, $m3['pertumbuhan']['mtom_nonmigas']);
                $this->assertNull($m3['pertumbuhan']['mtom_migas'], "{$jenis}: pembanding migas bulan 2 adalah 0 -> harus null.");
            }
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 4. Konsistensi MoM, YoY, dan C-to-C (termasuk guard $hasCurrM pada ctoc_* agar tidak membandingkan jendela timpang).
     */
    public function test_mom_yony_and_ctoc_consistency_with_current_month_guard(): void
    {
        $svc = new DistributionStatisticsService();

        DB::beginTransaction();
        try {
            // Tahun pembanding 2094: Jan = 1M (800K nonmigas + 200K migas), Feb = 2M (1.6M nonmigas + 400K migas) => Cum = 3M
            $this->insertDistribusiBatch('Impor', 2094, 1, 'RILIS', [
                ['nilai' => '800000.000000', 'migas' => false],
                ['nilai' => '200000.000000', 'migas' => true],
            ]);
            $this->insertDistribusiBatch('Impor', 2094, 2, 'RILIS', [
                ['nilai' => '1600000.000000', 'migas' => false],
                ['nilai' => '400000.000000', 'migas' => true],
            ]);

            // Tahun berjalan 2095: baru Jan RILIS = 1.5M (1.2M nonmigas + 300K migas); Feb belum rilis
            $this->insertDistribusiBatch('Impor', 2095, 1, 'RILIS', [
                ['nilai' => '1200000.000000', 'migas' => false],
                ['nilai' => '300000.000000', 'migas' => true],
            ]);

            // Cek 2095-01: YoY +50.0%, C-to-C +50.0%
            $mJan = $svc->getSummaryMetrics(2095, 1, 'impor');
            $this->assertSame(50.0, $mJan['pertumbuhan']['yony_total']);
            $this->assertSame(50.0, $mJan['pertumbuhan']['yony_nonmigas']);
            $this->assertSame(50.0, $mJan['pertumbuhan']['yony_migas']);
            $this->assertSame(50.0, $mJan['pertumbuhan']['ctoc_total']);

            // Cek 2095-02 (belum rilis!): MoM, YoY, DAN C-to-C wajib null semua (cegah -100% & jendela Jan vs Jan-Feb!)
            $mFebEmpty = $svc->getSummaryMetrics(2095, 2, 'impor');
            $this->assertFalse($mFebEmpty['bulan_ini']['has_data']);
            $this->assertNull($mFebEmpty['pertumbuhan']['mtom_total']);
            $this->assertNull($mFebEmpty['pertumbuhan']['yony_total']);
            $this->assertNull($mFebEmpty['pertumbuhan']['ctoc_total']);
            $this->assertNull($mFebEmpty['pertumbuhan']['ctoc_nonmigas']);
            $this->assertNull($mFebEmpty['pertumbuhan']['ctoc_migas']);

            // Rilis 2095-02 = 2.5M (2.0M nonmigas + 500K migas) => Cum 2095 = 4.0M vs Cum 2094 = 3.0M (+33.33%)
            $this->insertDistribusiBatch('Impor', 2095, 2, 'RILIS', [
                ['nilai' => '2000000.000000', 'migas' => false],
                ['nilai' => '500000.000000', 'migas' => true],
            ]);

            $mFebFull = $svc->getSummaryMetrics(2095, 2, 'impor');
            $this->assertTrue($mFebFull['bulan_ini']['has_data']);
            $this->assertSame(66.67, $mFebFull['pertumbuhan']['mtom_total']);
            $this->assertSame(25.0, $mFebFull['pertumbuhan']['yony_total']);
            $this->assertSame(33.33, $mFebFull['pertumbuhan']['ctoc_total']);
            $this->assertSame(33.33, $mFebFull['pertumbuhan']['ctoc_nonmigas']);
            $this->assertSame(33.33, $mFebFull['pertumbuhan']['ctoc_migas']);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 5. Presisi bcsub USD mentah pada getDistributionTradeBalance() tanpa pembulatan dini.
     */
    public function test_distribution_trade_balance_uses_raw_usd_bcsub_without_premature_rounding(): void
    {
        $svc = new DistributionStatisticsService();

        DB::beginTransaction();
        try {
            $year = 2096;
            // Bulan 1: Ekspor 100,004,999.00 (bulat 100.00 jt) vs Impor 99,995,001.00 (bulat 100.00 jt)
            // Selisih USD mentah = +9,998.00 USD => +0.01 jt (Surplus), BUKAN 0.00 Seimbang!
            $this->insertDistribusiBatch('Ekspor', $year, 1, 'RILIS', [
                ['nilai' => '100004999.000000', 'migas' => false],
            ]);
            $this->insertDistribusiBatch('Impor', $year, 1, 'RILIS', [
                ['nilai' => '99995001.000000', 'migas' => false],
            ]);

            $tb1 = $svc->getDistributionTradeBalance($year, 1);
            $this->assertTrue($tb1['has_complete_bulanan']);
            $this->assertTrue($tb1['has_complete_kumulatif']);
            $this->assertSame(0.01, $tb1['neraca_bulanan_juta']);
            $this->assertSame('Surplus', $tb1['status_bulanan']);
            $this->assertSame(0.01, $tb1['neraca_kumulatif_juta']);
            $this->assertSame('Surplus', $tb1['status_kumulatif']);

            // Bulan 2: Ekspor & Impor identik hingga 6 desimal -> Seimbang (0.0)
            $this->insertDistribusiBatch('Ekspor', $year, 2, 'RILIS', [
                ['nilai' => '5000000.123456', 'migas' => false],
            ]);
            $this->insertDistribusiBatch('Impor', $year, 2, 'RILIS', [
                ['nilai' => '5000000.123456', 'migas' => false],
            ]);

            $tb2 = $svc->getDistributionTradeBalance($year, 2);
            $this->assertTrue($tb2['has_complete_bulanan']);
            $this->assertSame(0.0, $tb2['neraca_bulanan_juta']);
            $this->assertSame('Seimbang', $tb2['status_bulanan']);

            // Tahun 2097 Bulan 1: Nilai mikro (< $5.000 sehingga bulat 0.00 jt) tetap terdeteksi lengkap via USD mentah
            $this->insertDistribusiBatch('Ekspor', 2097, 1, 'RILIS', [
                ['nilai' => '3000.000000', 'migas' => false],
            ]);
            $this->insertDistribusiBatch('Impor', 2097, 1, 'RILIS', [
                ['nilai' => '2000.000000', 'migas' => false],
            ]);

            $tbMicro = $svc->getDistributionTradeBalance(2097, 1);
            $this->assertTrue($tbMicro['has_complete_bulanan']);
            $this->assertTrue($tbMicro['has_complete_kumulatif']);
            $this->assertSame('Surplus', $tbMicro['status_bulanan']);
            $this->assertSame('Surplus', $tbMicro['status_kumulatif']);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 6. Isolasi ketat RILIS terhadap PRIVATE, DRAFT, dan Trash (serta pemulihan Trash) untuk Role 2.
     */
    public function test_private_draft_and_trash_are_excluded_from_growth_and_top_commodities(): void
    {
        $pegawai = User::where('role_id', 2)->firstOrFail();

        DB::beginTransaction();
        try {
            $year = 2098;
            // Bulan 1 RILIS (1M), Bulan 2 PRIVATE (2M), Bulan 3 DRAFT (3M), Bulan 4 RILIS di Trash (4M), Bulan 5 RILIS restored (5M)
            $this->insertDistribusiBatch('Ekspor', $year, 1, 'RILIS', [['nilai' => '1000000.000000', 'migas' => false]]);
            $this->insertDistribusiBatch('Ekspor', $year, 2, 'PRIVATE', [['nilai' => '2000000.000000', 'migas' => false]]);
            $this->insertDistribusiBatch('Ekspor', $year, 3, 'DRAFT', [['nilai' => '3000000.000000', 'migas' => false]]);
            $this->insertDistribusiBatch('Ekspor', $year, 4, 'RILIS', [['nilai' => '4000000.000000', 'migas' => false]], true, false);
            $this->insertDistribusiBatch('Ekspor', $year, 5, 'RILIS', [['nilai' => '5000000.000000', 'migas' => false]], true, true);

            // Bulan 2 (PRIVATE): 0 kebocoran, pertumbuhan null (bukan -100!), top_commodities kosong []
            $res2 = $this->actingAs($pegawai)->getJson(route('distribusi.metrics', ['tahun' => $year, 'bulan' => 2]))->assertStatus(200);
            $this->assertFalse($res2->json('ekspor.bulan_ini.has_data'));
            $this->assertEquals(0.0, $res2->json('ekspor.bulan_ini.nilai_total_juta'));
            $this->assertNull($res2->json('ekspor.pertumbuhan.mtom_total'));
            $this->assertSame([], $res2->json('top_commodities.items'));

            // Bulan 3 (DRAFT): 0 kebocoran
            $res3 = $this->actingAs($pegawai)->getJson(route('distribusi.metrics', ['tahun' => $year, 'bulan' => 3]))->assertStatus(200);
            $this->assertFalse($res3->json('ekspor.bulan_ini.has_data'));
            $this->assertNull($res3->json('ekspor.pertumbuhan.mtom_total'));
            $this->assertSame([], $res3->json('top_commodities.items'));

            // Bulan 4 (RILIS aktif di Trash): 0 kebocoran
            $res4 = $this->actingAs($pegawai)->getJson(route('distribusi.metrics', ['tahun' => $year, 'bulan' => 4]))->assertStatus(200);
            $this->assertFalse($res4->json('ekspor.bulan_ini.has_data'));
            $this->assertNull($res4->json('ekspor.pertumbuhan.mtom_total'));
            $this->assertSame([], $res4->json('top_commodities.items'));

            // Bulan 5 (RILIS hasil restore): terbaca sah (5M), tetapi MoM null karena Bulan 4 di Trash
            $res5 = $this->actingAs($pegawai)->getJson(route('distribusi.metrics', ['tahun' => $year, 'bulan' => 5]))->assertStatus(200);
            $this->assertTrue($res5->json('ekspor.bulan_ini.has_data'));
            $this->assertEquals(5.0, $res5->json('ekspor.bulan_ini.nilai_total_juta'));
            $this->assertNull($res5->json('ekspor.pertumbuhan.mtom_total'));
            $this->assertNotEmpty($res5->json('top_commodities.items'));
        } finally {
            DB::rollBack();
        }
    }
}
