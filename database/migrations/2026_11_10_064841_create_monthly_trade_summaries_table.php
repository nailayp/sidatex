<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel agregasi bulanan SIDATEX (Opsi B: Tabel Agregat Terpisah)
     * Mengelompokkan transaksi mentah berdasarkan 7 dimensi utama:
     * hs_id, negara_id, provinsi_id, pelabuhan_id, jenis_transaksi, bulan, tahun
     */
    public function up(): void
    {
        Schema::create('monthly_trade_summaries', function (Blueprint $table) {
            $table->increments('summary_id');

            // 7 Dimensi Utama Agregasi
            $table->unsignedInteger('hs_id');
            $table->unsignedInteger('negara_id');
            $table->unsignedInteger('provinsi_id');
            $table->unsignedInteger('pelabuhan_id')->nullable();
            $table->enum('jenis_transaksi', ['Ekspor', 'Impor']);
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');

            // Variabel Numerik Agregat (sesuai skema transaksi aktual)
            $table->decimal('total_berat', 20, 3)->default(0);
            $table->decimal('total_nilai', 20, 2)->default(0);
            $table->unsignedInteger('jumlah_transaksi')->default(0);

            $table->timestamps();

            // Relasi Foreign Key ke Master Data
            $table->foreign('hs_id')
                ->references('hs_id')
                ->on('master_hs')
                ->restrictOnDelete();

            $table->foreign('negara_id')
                ->references('negara_id')
                ->on('master_negara')
                ->restrictOnDelete();

            $table->foreign('provinsi_id')
                ->references('provinsi_id')
                ->on('master_provinsi')
                ->restrictOnDelete();

            $table->foreign('pelabuhan_id')
                ->references('pelabuhan_id')
                ->on('master_pelabuhan')
                ->nullOnDelete();

            // Unique Composite Key 7 Kolom
            $table->unique(
                ['hs_id', 'negara_id', 'provinsi_id', 'pelabuhan_id', 'jenis_transaksi', 'bulan', 'tahun'],
                'uq_mts_7_dimensi'
            );

            // Index pendukung untuk query cepat dashboard & analisis
            $table->index(['tahun', 'bulan', 'jenis_transaksi'], 'idx_mts_periode_jenis');
            $table->index(['provinsi_id', 'tahun', 'bulan'], 'idx_mts_prov_periode');
            $table->index(['pelabuhan_id', 'tahun', 'bulan'], 'idx_mts_pel_periode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_trade_summaries');
    }
};
