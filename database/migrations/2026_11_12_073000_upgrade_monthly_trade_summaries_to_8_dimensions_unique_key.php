<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Mengganti Unique Index uq_mts_7_dimensi menjadi uq_mts_8_dimensi dengan menyertakan tipe_sumber.
     */
    public function up(): void
    {
        Schema::table('monthly_trade_summaries', function (Blueprint $table) {
            // Drop unique index 7 dimensi lama jika ada
            $table->dropUnique('uq_mts_7_dimensi');

            // Tambahkan unique index 8 dimensi yang menyertakan tipe_sumber
            $table->unique(
                ['tipe_sumber', 'hs_id', 'negara_id', 'provinsi_id', 'pelabuhan_key', 'jenis_transaksi', 'bulan', 'tahun'],
                'uq_mts_8_dimensi'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_trade_summaries', function (Blueprint $table) {
            $table->dropUnique('uq_mts_8_dimensi');

            $table->unique(
                ['hs_id', 'negara_id', 'provinsi_id', 'pelabuhan_key', 'jenis_transaksi', 'bulan', 'tahun'],
                'uq_mts_7_dimensi'
            );
        });
    }
};
