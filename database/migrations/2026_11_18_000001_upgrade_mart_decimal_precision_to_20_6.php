<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Migrasi aditif lossless untuk menyelaraskan seluruh tabel Analytical Mart
     * (monthly_trade_summaries, monthly_origin_trade_summaries, brs_indicator_summaries)
     * dengan standar presisi DECIMAL(20,6) sesuai AGENTS.md Seksi 2.
     * Tidak menghapus atau memodifikasi data historis maupun status visibilitas.
     */
    public function up(): void
    {
        if (Schema::hasTable('monthly_trade_summaries')) {
            Schema::table('monthly_trade_summaries', function (Blueprint $table) {
                $table->decimal('total_berat', 20, 6)->default(0)->change();
                $table->decimal('total_nilai', 20, 6)->default(0)->change();
            });
        }

        if (Schema::hasTable('monthly_origin_trade_summaries')) {
            Schema::table('monthly_origin_trade_summaries', function (Blueprint $table) {
                $table->decimal('total_berat', 20, 6)->default(0)->change();
                $table->decimal('total_nilai', 20, 6)->default(0)->change();
            });
        }

        if (Schema::hasTable('brs_indicator_summaries')) {
            Schema::table('brs_indicator_summaries', function (Blueprint $table) {
                $table->decimal('nilai_usd', 20, 6)->default(0)->change();
                $table->decimal('growth_mtom', 10, 2)->nullable()->change();
                $table->decimal('growth_yoy', 10, 2)->nullable()->change();
                $table->decimal('growth_ctc', 10, 2)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('brs_indicator_summaries')) {
            Schema::table('brs_indicator_summaries', function (Blueprint $table) {
                $table->decimal('nilai_usd', 20, 4)->default(0)->change();
                $table->decimal('growth_mtom', 6, 2)->nullable()->change();
                $table->decimal('growth_yoy', 6, 2)->nullable()->change();
                $table->decimal('growth_ctc', 6, 2)->nullable()->change();
            });
        }

        if (Schema::hasTable('monthly_origin_trade_summaries')) {
            Schema::table('monthly_origin_trade_summaries', function (Blueprint $table) {
                $table->decimal('total_berat', 20, 2)->default(0)->change();
                $table->decimal('total_nilai', 20, 2)->default(0)->change();
            });
        }

        if (Schema::hasTable('monthly_trade_summaries')) {
            Schema::table('monthly_trade_summaries', function (Blueprint $table) {
                $table->decimal('total_berat', 20, 3)->default(0)->change();
                $table->decimal('total_nilai', 20, 2)->default(0)->change();
            });
        }
    }
};
