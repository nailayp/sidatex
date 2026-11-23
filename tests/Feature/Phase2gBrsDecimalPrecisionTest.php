<?php

namespace Tests\Feature;

use App\Services\BrsAggregationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase2gBrsDecimalPrecisionTest extends TestCase
{
    private array $createdBatchIds = [];

    protected function tearDown(): void
    {
        if (!empty($this->createdBatchIds)) {
            DB::table('brs_indicator_summaries')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('monthly_trade_summaries')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('monthly_origin_trade_summaries')->whereIn('tahun', [2091, 2092])->delete();
            DB::table('trash')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('distribusi_transactions')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('neraca_transactions')->whereIn('batch_id', $this->createdBatchIds)->delete();
            DB::table('data_batches')->whereIn('batch_id', $this->createdBatchIds)->delete();
            $this->createdBatchIds = [];
        }

        parent::tearDown();
    }

    public function test_mart_columns_have_decimal_20_6_and_growth_10_2_precision(): void
    {
        $activeDb = DB::connection()->getDatabaseName();
        $this->assertContains($activeDb, ['sidatex_testing', ':memory:']);

        $expected = [
            'monthly_trade_summaries' => [
                'total_berat' => 'decimal(20,6)',
                'total_nilai' => 'decimal(20,6)',
            ],
            'monthly_origin_trade_summaries' => [
                'total_berat' => 'decimal(20,6)',
                'total_nilai' => 'decimal(20,6)',
            ],
            'brs_indicator_summaries' => [
                'nilai_usd' => 'decimal(20,6)',
                'growth_mtom' => 'decimal(10,2)',
                'growth_yoy' => 'decimal(10,2)',
                'growth_ctc' => 'decimal(10,2)',
            ],
        ];

        if (DB::connection()->getDriverName() === 'mysql') {
            foreach ($expected as $table => $columns) {
                foreach ($columns as $column => $expectedType) {
                    $colMeta = DB::selectOne(
                        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                        [$activeDb, $table, $column]
                    );

                    $this->assertNotNull($colMeta, "Kolom {$table}.{$column} harus ada.");
                    $this->assertSame(
                        $expectedType,
                        strtolower((string) $colMeta->COLUMN_TYPE),
                        "Presisi kolom {$table}.{$column} harus {$expectedType}."
                    );
                }
            }
        } else {
            foreach ($expected as $table => $columns) {
                foreach ($columns as $column => $expectedType) {
                    $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn($table, $column), "Kolom {$table}.{$column} harus ada.");
                }
            }
        }
    }

    public function test_distribusi_and_neraca_three_way_reconciliation_exact_6_decimals_over_100_rows(): void
    {
        $hsRows = DB::table('master_hs')->limit(5)->get();
        $negaraRows = DB::table('master_negara')->limit(5)->get();
        $pelabuhan = DB::table('master_pelabuhan')->first();

        $this->assertGreaterThanOrEqual(2, $hsRows->count());
        $this->assertGreaterThanOrEqual(2, $negaraRows->count());
        $this->assertNotNull($pelabuhan);

        $aggService = app(BrsAggregationService::class);

        // 1. Batch Distribusi Ekspor (2092-01) — 100 baris fraksional 6 desimal
        $distBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_precision_dist_2092_01.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
            'periode_tahun' => 2092,
            'periode_bulan' => 1,
            'ukuran_file' => '0.25',
            'status' => 'RILIS',
            'uploaded_by' => 1,
            'uploaded_at' => now(),
            'released_by' => 1,
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $distBatchId;

        $expectedDistNilai = '0.000000';
        $expectedDistBerat = '0.000000';
        $distInserts = [];

        for ($i = 1; $i <= 100; $i++) {
            $nilai = sprintf('%d.%06d', 100 + $i, ($i * 7919) % 1000000);
            $berat = sprintf('%d.%06d', 50 + $i, ($i * 3571) % 1000000);
            $expectedDistNilai = bcadd($expectedDistNilai, $nilai, 6);
            $expectedDistBerat = bcadd($expectedDistBerat, $berat, 6);

            $distInserts[] = [
                'batch_id' => $distBatchId,
                'hs_id' => $hsRows[$i % $hsRows->count()]->hs_id,
                'negara_id' => $negaraRows[$i % $negaraRows->count()]->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                'provorig' => '1401',
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
                'bulan' => 1,
                'tahun' => 2092,
                'berat' => $berat,
                'nilai' => $nilai,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('distribusi_transactions')->insert($distInserts);

        $distRes = $aggService->aggregateBatch($distBatchId, 'RILIS');
        $this->assertSame($expectedDistNilai, $distRes['total_nilai_usd']);
        $this->assertSame($expectedDistBerat, $distRes['total_berat_kg']);

        $rawDistSum = (string) DB::table('distribusi_transactions')->where('batch_id', $distBatchId)->sum('nilai');
        $tier1DistSum = (string) DB::table('monthly_trade_summaries')->where('batch_id', $distBatchId)->sum('total_nilai');
        $tier2DistRow = DB::table('brs_indicator_summaries')
            ->where('batch_id', $distBatchId)
            ->where('kategori_dimensi', 'TOTAL')
            ->where('kode_dimensi', 'TOTAL')
            ->first();

        $this->assertNotNull($tier2DistRow);
        $this->assertSame(0, bccomp($expectedDistNilai, $rawDistSum, 6), 'Raw Distribusi harus cocok 6 desimal.');
        $this->assertSame(0, bccomp($expectedDistNilai, $tier1DistSum, 6), 'Tier-1 Distribusi harus cocok 6 desimal.');
        $this->assertSame(0, bccomp($expectedDistNilai, (string) $tier2DistRow->nilai_usd, 6), 'Tier-2 Distribusi harus cocok 6 desimal.');
        $this->assertNull($tier2DistRow->growth_mtom, 'MoM tanpa bulan sebelumnya harus NULL, bukan 0.0.');
        $this->assertNull($tier2DistRow->growth_yoy, 'YoY tanpa tahun sebelumnya harus NULL, bukan 0.0.');
        $this->assertNull($tier2DistRow->growth_ctc, 'C-to-C tanpa tahun sebelumnya harus NULL, bukan 0.0.');

        // 2. Batch Neraca Impor (2092-02) — 98 baris mapped + 2 baris unmapped dengan kode HS mentah berbeda
        $neracaBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_precision_neraca_2092_02.dbf',
            'jenis_data' => 'Impor',
            'tipe_sumber' => 'neraca',
            'periode_tahun' => 2092,
            'periode_bulan' => 2,
            'ukuran_file' => '0.25',
            'status' => 'PRIVATE',
            'uploaded_by' => 1,
            'uploaded_at' => now(),
            'released_by' => null,
            'released_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $neracaBatchId;

        $expectedNeracaNilai = '0.000000';
        $neracaInserts = [];

        for ($i = 1; $i <= 98; $i++) {
            $nilai = sprintf('%d.%06d', 200 + $i, ($i * 6133) % 1000000);
            $berat = sprintf('%d.%06d', 80 + $i, ($i * 2221) % 1000000);
            $expectedNeracaNilai = bcadd($expectedNeracaNilai, $nilai, 6);

            $hsObj = $hsRows[$i % $hsRows->count()];
            $neracaInserts[] = [
                'batch_id' => $neracaBatchId,
                'hs_id' => $hsObj->hs_id,
                'hs_code_raw' => $hsObj->hs_code,
                'mapping_status' => 'mapped',
                'negara_id' => $negaraRows[$i % $negaraRows->count()]->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => null,
                'jenis_transaksi' => 'Impor',
                'tipe_sumber' => 'neraca',
                'bulan' => 2,
                'tahun' => 2092,
                'berat' => $berat,
                'nilai' => $nilai,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // 2 baris unmapped dengan negara & pelabuhan sama tetapi hs_code_raw berbeda
        foreach (['99999901' => '111.111111', '99999902' => '222.222222'] as $rawCode => $rawVal) {
            $expectedNeracaNilai = bcadd($expectedNeracaNilai, $rawVal, 6);
            $neracaInserts[] = [
                'batch_id' => $neracaBatchId,
                'hs_id' => null,
                'hs_code_raw' => $rawCode,
                'mapping_status' => 'unmapped',
                'negara_id' => $negaraRows[0]->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => null,
                'jenis_transaksi' => 'Impor',
                'tipe_sumber' => 'neraca',
                'bulan' => 2,
                'tahun' => 2092,
                'berat' => '10.000001',
                'nilai' => $rawVal,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('neraca_transactions')->insert($neracaInserts);

        $aggService->aggregateBatch($neracaBatchId, 'PRIVATE');

        $tier1NeracaSum = (string) DB::table('monthly_trade_summaries')->where('batch_id', $neracaBatchId)->sum('total_nilai');
        $tier2NeracaRow = DB::table('brs_indicator_summaries')
            ->where('batch_id', $neracaBatchId)
            ->where('kategori_dimensi', 'TOTAL')
            ->where('kode_dimensi', 'TOTAL')
            ->first();

        $this->assertNotNull($tier2NeracaRow);
        $this->assertSame(0, bccomp($expectedNeracaNilai, $tier1NeracaSum, 6), 'Tier-1 Neraca harus cocok 6 desimal.');
        $this->assertSame(0, bccomp($expectedNeracaNilai, (string) $tier2NeracaRow->nilai_usd, 6), 'Tier-2 Neraca harus cocok 6 desimal.');

        // Pastikan kedua kode HS unmapped tetap terpisah dan mempertahankan hs_code_raw + mapping_status='unmapped'
        $unmappedRows = DB::table('monthly_trade_summaries')
            ->where('batch_id', $neracaBatchId)
            ->whereNull('hs_id')
            ->orderBy('hs_code_raw')
            ->get();

        $this->assertCount(2, $unmappedRows, 'Dua kode HS unmapped yang berbeda harus dipertahankan terpisah di Tier-1.');
        $this->assertSame('99999901', $unmappedRows[0]->hs_code_raw);
        $this->assertSame('unmapped', $unmappedRows[0]->mapping_status);
        $this->assertSame('UNMAPPED', $unmappedRows[0]->sektor);
        $this->assertSame('99999902', $unmappedRows[1]->hs_code_raw);
    }

    public function test_migration_2026_11_16_down_guard_aborts_if_unmapped_hs_exists(): void
    {
        $negara = DB::table('master_negara')->first();
        $this->assertNotNull($negara);

        $migration = require database_path('migrations/2026_11_16_000001_enhance_trade_summaries_and_create_brs_indicators.php');

        DB::beginTransaction();
        try {
            DB::table('monthly_trade_summaries')->insert([
                'tipe_sumber' => 'neraca',
                'hs_id' => null,
                'hs_code_raw' => '99998888',
                'mapping_status' => 'unmapped',
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => null,
                'pelabuhan_key' => 0,
                'jenis_transaksi' => 'Ekspor',
                'bulan' => 12,
                'tahun' => 2092,
                'total_berat' => '1.000000',
                'total_nilai' => '1.000000',
                'jumlah_transaksi' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Rollback dibatalkan');
            $migration->down();
        } finally {
            DB::rollBack();
        }
    }

    public function test_january_mom_wraps_to_previous_december_and_isolates_private_from_rilis(): void
    {
        $hs = DB::table('master_hs')->first();
        $negara = DB::table('master_negara')->first();
        $pelabuhan = DB::table('master_pelabuhan')->first();
        $this->assertNotNull($hs);
        $this->assertNotNull($negara);
        $this->assertNotNull($pelabuhan);

        $aggService = app(BrsAggregationService::class);

        $makeBatch = function (int $tahun, int $bulan, string $status, string $nilai) use ($hs, $negara, $pelabuhan, $aggService): int {
            $id = DB::table('data_batches')->insertGetId([
                'nama_file' => "sentinel_mom_{$tahun}_{$bulan}_{$status}.dbf",
                'jenis_data' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
                'periode_tahun' => $tahun,
                'periode_bulan' => $bulan,
                'ukuran_file' => '0.10',
                'status' => $status,
                'uploaded_by' => 1,
                'uploaded_at' => now(),
                'released_by' => $status === 'RILIS' ? 1 : null,
                'released_at' => $status === 'RILIS' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->createdBatchIds[] = $id;

            DB::table('distribusi_transactions')->insert([
                'batch_id' => $id,
                'hs_id' => $hs->hs_id,
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => $pelabuhan->pelabuhan_id,
                'provorig' => '1401',
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'distribusi',
                'bulan' => $bulan,
                'tahun' => $tahun,
                'berat' => '100.000000',
                'nilai' => $nilai,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $aggService->aggregateBatch($id, $status);

            return $id;
        };

        // 1. Desember 2091 RILIS (1000) -> Januari 2092 RILIS (1250) => MoM harus +25.00%
        $makeBatch(2091, 12, 'RILIS', '1000.000000');
        $janBatchId = $makeBatch(2092, 1, 'RILIS', '1250.000000');

        $janTotal = DB::table('brs_indicator_summaries')
            ->where('batch_id', $janBatchId)
            ->where('kategori_dimensi', 'TOTAL')
            ->where('kode_dimensi', 'TOTAL')
            ->first();

        $this->assertNotNull($janTotal);
        $this->assertEqualsWithDelta(25.0, (float) $janTotal->growth_mtom, 0.001, 'MoM Januari wajib dibandingkan terhadap Desember tahun sebelumnya.');

        // 2. Februari 2092 PRIVATE (2000) -> Maret 2092 RILIS (2500) => MoM Maret RILIS wajib NULL (tidak boleh bocor dari PRIVATE)
        $makeBatch(2092, 2, 'PRIVATE', '2000.000000');
        $marBatchId = $makeBatch(2092, 3, 'RILIS', '2500.000000');

        $marTotal = DB::table('brs_indicator_summaries')
            ->where('batch_id', $marBatchId)
            ->where('kategori_dimensi', 'TOTAL')
            ->where('kode_dimensi', 'TOTAL')
            ->first();

        $this->assertNotNull($marTotal);
        $this->assertNull($marTotal->growth_mtom, 'Indikator RILIS tidak boleh menggunakan periode PRIVATE sebagai pembanding MoM.');
    }

    public function test_unmapped_hs_is_separated_from_non_migas_and_migas_sector_uses_total_denominator(): void
    {
        $migasHs = DB::table('master_hs')
            ->where(function ($q) {
                $q->where('hs2', '27')->orWhereRaw('UPPER(oilgrp_desc) = ?', ['MIGAS']);
            })
            ->first();
        $nonMigasHs = DB::table('master_hs')
            ->where('hs2', '!=', '27')
            ->where(function ($q) {
                $q->whereNull('oilgrp_desc')->orWhereRaw('UPPER(oilgrp_desc) != ?', ['MIGAS']);
            })
            ->whereRaw('UPPER(COALESCE(sektor, "")) LIKE ?', ['%INDUSTRI%'])
            ->first();
        $negara = DB::table('master_negara')->first();

        $this->assertNotNull($migasHs, 'Master HS Migas harus tersedia.');
        $this->assertNotNull($nonMigasHs, 'Master HS Non-Migas Industri harus tersedia.');
        $this->assertNotNull($negara);

        $neracaBatchId = DB::table('data_batches')->insertGetId([
            'nama_file' => 'sentinel_migas_unmapped_2092_04.dbf',
            'jenis_data' => 'Ekspor',
            'tipe_sumber' => 'neraca',
            'periode_tahun' => 2092,
            'periode_bulan' => 4,
            'ukuran_file' => '0.10',
            'status' => 'RILIS',
            'uploaded_by' => 1,
            'uploaded_at' => now(),
            'released_by' => 1,
            'released_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createdBatchIds[] = $neracaBatchId;

        DB::table('neraca_transactions')->insert([
            [
                'batch_id' => $neracaBatchId,
                'hs_id' => $migasHs->hs_id,
                'hs_code_raw' => $migasHs->hs_code,
                'mapping_status' => 'mapped',
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => null,
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'neraca',
                'bulan' => 4,
                'tahun' => 2092,
                'berat' => '600.000000',
                'nilai' => '600.000000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'batch_id' => $neracaBatchId,
                'hs_id' => $nonMigasHs->hs_id,
                'hs_code_raw' => $nonMigasHs->hs_code,
                'mapping_status' => 'mapped',
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => null,
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'neraca',
                'bulan' => 4,
                'tahun' => 2092,
                'berat' => '300.000000',
                'nilai' => '300.000000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'batch_id' => $neracaBatchId,
                'hs_id' => null,
                'hs_code_raw' => '99991111',
                'mapping_status' => 'unmapped',
                'negara_id' => $negara->negara_id,
                'provinsi_id' => 4,
                'pelabuhan_id' => null,
                'jenis_transaksi' => 'Ekspor',
                'tipe_sumber' => 'neraca',
                'bulan' => 4,
                'tahun' => 2092,
                'berat' => '100.000000',
                'nilai' => '100.000000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        app(BrsAggregationService::class)->aggregateBatch($neracaBatchId, 'RILIS');

        $migasDim = DB::table('brs_indicator_summaries')
            ->where('batch_id', $neracaBatchId)
            ->where('kategori_dimensi', 'MIGAS_NONMIGAS')
            ->get()
            ->keyBy('kode_dimensi');

        $this->assertTrue($migasDim->has('MIGAS'));
        $this->assertTrue($migasDim->has('NON_MIGAS'));
        $this->assertTrue($migasDim->has('UNMAPPED'), 'HS unmapped harus dipisahkan dari NON_MIGAS pada dimensi MIGAS_NONMIGAS.');

        $this->assertSame(0, bccomp('600.000000', (string) $migasDim['MIGAS']->nilai_usd, 6));
        $this->assertSame(0, bccomp('300.000000', (string) $migasDim['NON_MIGAS']->nilai_usd, 6));
        $this->assertSame(0, bccomp('100.000000', (string) $migasDim['UNMAPPED']->nilai_usd, 6));
        $this->assertEqualsWithDelta(60.0, (float) $migasDim['MIGAS']->share_percentage, 0.01);
        $this->assertEqualsWithDelta(30.0, (float) $migasDim['NON_MIGAS']->share_percentage, 0.01);
        $this->assertEqualsWithDelta(10.0, (float) $migasDim['UNMAPPED']->share_percentage, 0.01);

        $sektorDim = DB::table('brs_indicator_summaries')
            ->where('batch_id', $neracaBatchId)
            ->where('kategori_dimensi', 'SEKTOR')
            ->get()
            ->keyBy('kode_dimensi');

        $this->assertEqualsWithDelta(60.0, (float) $sektorDim['MIGAS']->share_percentage, 0.01, 'Share sektor MIGAS harus terhadap TOTAL (60%), bukan terhadap NON_MIGAS (200%).');
        $this->assertEqualsWithDelta(10.0, (float) $sektorDim['UNMAPPED']->share_percentage, 0.01, 'Share sektor UNMAPPED harus terhadap TOTAL (10%).');
        $this->assertEqualsWithDelta(100.0, (float) $sektorDim['INDUSTRI PENGOLAHAN']->share_percentage, 0.01, 'Share sektor Non-Migas terhadap TOTAL NON_MIGAS (100%).');
    }
}
