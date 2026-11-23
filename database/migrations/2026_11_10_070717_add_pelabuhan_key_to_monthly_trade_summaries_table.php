<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Mengatasi ANSI SQL / MySQL NULL behavior pada UNIQUE index.
     * Pada standar SQL, NULL != NULL sehingga multi-kolom UNIQUE key yang mengandung NULL
     * dapat meloloskan baris duplikat jika pelabuhan_id bernilai NULL.
     *
     * Solusi:
     * Menambahkan kolom `pelabuhan_key` INT UNSIGNED NOT NULL DEFAULT 0,
     * dan mengikat UNIQUE key 7 dimensi menggunakan `pelabuhan_key`.
     */
    public function up(): void
    {
        // Pastikan kolom dan index sudah terpasang
        if (!Schema::hasColumn('monthly_trade_summaries', 'pelabuhan_key')) {
            Schema::table('monthly_trade_summaries', function (Blueprint $table) {
                $table->unsignedInteger('pelabuhan_key')->default(0)->after('pelabuhan_id');
            });

            DB::statement('UPDATE `monthly_trade_summaries` SET `pelabuhan_key` = COALESCE(`pelabuhan_id`, 0)');
        }

        // Pastikan index individual hs_id tersedia
        $existingIndexes = collect(Schema::getIndexes('monthly_trade_summaries'));
        $indexNames = $existingIndexes->pluck('name')->filter()->values();

        if (!$indexNames->contains('idx_mts_hs_id')) {
            Schema::table('monthly_trade_summaries', function (Blueprint $table) {
                $table->index('hs_id', 'idx_mts_hs_id');
            });
        }

        $uqIndex = $existingIndexes->firstWhere('name', 'uq_mts_7_dimensi');
        if ($uqIndex) {
            $cols = collect($uqIndex['columns'] ?? []);
            if ($cols->contains('pelabuhan_id')) {
                Schema::table('monthly_trade_summaries', function (Blueprint $table) {
                    $table->dropUnique('uq_mts_7_dimensi');
                });
            }
        }

        // Pasang unique key 7 dimensi dengan pelabuhan_key
        $currentIndices = collect(Schema::getIndexes('monthly_trade_summaries'))->pluck('name')->filter()->values();
        if (!$currentIndices->contains('uq_mts_7_dimensi')) {
            Schema::table('monthly_trade_summaries', function (Blueprint $table) {
                $table->unique(
                    ['hs_id', 'negara_id', 'provinsi_id', 'pelabuhan_key', 'jenis_transaksi', 'bulan', 'tahun'],
                    'uq_mts_7_dimensi'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_trade_summaries', function (Blueprint $table) {
            $table->dropUnique('uq_mts_7_dimensi');
            $table->dropColumn('pelabuhan_key');
            $table->unique(
                ['hs_id', 'negara_id', 'provinsi_id', 'pelabuhan_id', 'jenis_transaksi', 'bulan', 'tahun'],
                'uq_mts_7_dimensi'
            );
        });
    }
};
