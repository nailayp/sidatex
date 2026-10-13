<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyimpan baris data ekspor-impor yang sudah diproses.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('transaction_id');

            // Setiap transaksi berasal dari satu batch unggahan.
            $table->unsignedInteger('batch_id');

            // Relasi ke tabel master/referensi.
            $table->unsignedInteger('hs_id');
            $table->unsignedInteger('negara_id');
            $table->unsignedInteger('provinsi_id');
            $table->unsignedInteger('kabkota_id')->nullable();
            $table->unsignedInteger('pelabuhan_id')->nullable();

            $table->enum('jenis_transaksi', ['Ekspor', 'Impor']);
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');

            // DECIMAL menjaga ketelitian angka berat dan nilai perdagangan.
            $table->decimal('berat', 20, 3)->default(0);
            $table->decimal('nilai', 20, 2)->default(0);

            $table->timestamps();

            // Relasi transaksi dengan batch dan tabel master.
            $table->foreign('batch_id')
                ->references('batch_id')
                ->on('data_batches')
                ->cascadeOnDelete();

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

            $table->foreign('kabkota_id')
                ->references('kabkota_id')
                ->on('master_kabkota')
                ->restrictOnDelete();

            $table->foreign('pelabuhan_id')
                ->references('pelabuhan_id')
                ->on('master_pelabuhan')
                ->restrictOnDelete();
        });
    }

    /**
     * Menghapus tabel transaksi jika migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
