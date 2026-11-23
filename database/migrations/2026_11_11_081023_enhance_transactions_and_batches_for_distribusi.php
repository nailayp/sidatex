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
        Schema::table('data_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('data_batches', 'periode_tahun')) {
                $table->year('periode_tahun')->nullable()->after('jenis_data');
            }
            if (!Schema::hasColumn('data_batches', 'periode_bulan')) {
                $table->tinyInteger('periode_bulan')->unsigned()->nullable()->after('periode_tahun');
            }
            if (!Schema::hasColumn('data_batches', 'tipe_sumber')) {
                $table->enum('tipe_sumber', ['distribusi', 'neraca'])->default('distribusi')->after('periode_bulan');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'provorig')) {
                $table->string('provorig', 10)->nullable()->after('kabkota_id')->comment('Kode 4-digit wilayah asal komoditas');
            }
            if (!Schema::hasColumn('transactions', 'tipe_sumber')) {
                $table->enum('tipe_sumber', ['distribusi', 'neraca'])->default('distribusi')->after('jenis_transaksi');
            }
        });

        Schema::table('monthly_trade_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('monthly_trade_summaries', 'tipe_sumber')) {
                $table->enum('tipe_sumber', ['distribusi', 'neraca'])->default('distribusi')->after('jenis_transaksi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_trade_summaries', function (Blueprint $table) {
            $table->dropColumn('tipe_sumber');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['provorig', 'tipe_sumber']);
        });

        Schema::table('data_batches', function (Blueprint $table) {
            $table->dropColumn(['periode_tahun', 'periode_bulan', 'tipe_sumber']);
        });
    }
};
