<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monthly_origin_trade_summaries', function (Blueprint $table) {
            $table->dropUnique('unique_origin_summary');
            $table->unique(
                ['tahun', 'bulan', 'jenis_transaksi', 'provorig', 'hs2', 'sektor', 'is_migas'],
                'unique_origin_summary_fixed'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_origin_trade_summaries', function (Blueprint $table) {
            $table->dropUnique('unique_origin_summary_fixed');
            $table->unique(
                ['tahun', 'bulan', 'jenis_transaksi', 'provorig', 'hs2', 'is_migas'],
                'unique_origin_summary'
            );
        });
    }
};
