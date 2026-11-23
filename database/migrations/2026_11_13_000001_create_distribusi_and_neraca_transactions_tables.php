<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Membuat dua tabel transaksi fisik terpisah:
     * 1. distribusi_transactions : Tabel transaksi fisik khusus modul Distribusi.
     * 2. neraca_transactions     : Tabel transaksi fisik khusus modul Neraca.
     */
    public function up(): void
    {
        // 1. Buat tabel fisik distribusi_transactions jika belum ada
        if (!Schema::hasTable('distribusi_transactions')) {
            Schema::create('distribusi_transactions', function (Blueprint $table) {
                $table->increments('transaction_id');
                $table->unsignedInteger('batch_id');
                $table->unsignedInteger('hs_id');
                $table->unsignedInteger('negara_id');
                $table->unsignedInteger('provinsi_id');
                $table->unsignedInteger('kabkota_id')->nullable();
                $table->string('provorig', 10)->nullable()->comment('Kode 4-digit wilayah asal komoditas');
                $table->unsignedInteger('pelabuhan_id')->nullable();
                $table->enum('jenis_transaksi', ['Ekspor', 'Impor']);
                $table->enum('tipe_sumber', ['distribusi', 'neraca'])->default('distribusi');
                $table->unsignedTinyInteger('bulan');
                $table->unsignedSmallInteger('tahun');
                // Menggunakan DECIMAL(20,6) untuk mempertahankan presisi desimal penuh tanpa pembulatan
                $table->decimal('berat', 20, 6)->default(0);
                $table->decimal('nilai', 20, 6)->default(0);
                $table->timestamps();

                // Foreign keys & Indexes
                $table->foreign('batch_id', 'dist_tx_batch_id_fk')
                      ->references('batch_id')->on('data_batches')
                      ->onDelete('cascade');

                $table->foreign('hs_id', 'dist_tx_hs_id_fk')
                      ->references('hs_id')->on('master_hs')
                      ->onDelete('restrict');

                $table->foreign('negara_id', 'dist_tx_negara_id_fk')
                      ->references('negara_id')->on('master_negara')
                      ->onDelete('restrict');

                $table->foreign('provinsi_id', 'dist_tx_provinsi_id_fk')
                      ->references('provinsi_id')->on('master_provinsi')
                      ->onDelete('restrict');

                $table->foreign('kabkota_id', 'dist_tx_kabkota_id_fk')
                      ->references('kabkota_id')->on('master_kabkota')
                      ->onDelete('restrict');

                $table->foreign('pelabuhan_id', 'dist_tx_pelabuhan_id_fk')
                      ->references('pelabuhan_id')->on('master_pelabuhan')
                      ->onDelete('restrict');

                $table->index(['tahun', 'bulan', 'jenis_transaksi'], 'dist_tx_period_idx');
            });
        }

        // 2. Buat tabel fisik neraca_transactions jika belum ada
        if (!Schema::hasTable('neraca_transactions')) {
            Schema::create('neraca_transactions', function (Blueprint $table) {
                $table->increments('transaction_id');
                $table->unsignedInteger('batch_id');
                // Pada modul Neraca, hs_id nullable untuk mengakomodasi komoditas unmapped
                $table->unsignedInteger('hs_id')->nullable();
                $table->string('hs_code_raw', 20)->comment('Kode HS asli dari berkas sumber');
                $table->string('hs_desc_raw', 255)->nullable()->comment('Deskripsi barang dari sumber/lookup');
                $table->enum('mapping_status', ['mapped', 'unmapped'])->default('mapped');
                $table->unsignedInteger('negara_id');
                $table->unsignedInteger('provinsi_id')->comment('Asal komoditas (Ekspor) / Kantor Pabean (Impor)');
                $table->unsignedInteger('kabkota_id')->nullable()->comment('Kab/Kota produsen asal barang');
                $table->unsignedInteger('pelabuhan_id')->nullable()->comment('Pelabuhan muat/bongkar');
                // Kolom kepabeanan khusus Neraca Impor (KPPBC)
                $table->string('kode_kppbc', 10)->nullable()->comment('Kode KPPBC pabean impor (misal: 021200)');
                $table->string('nama_kppbc', 100)->nullable()->comment('Nama kantor pabean KPPBC');
                $table->enum('jenis_transaksi', ['Ekspor', 'Impor']);
                $table->enum('tipe_sumber', ['distribusi', 'neraca'])->default('neraca');
                $table->unsignedTinyInteger('bulan');
                $table->unsignedSmallInteger('tahun');
                // Presisi penuh DECIMAL(20,6)
                $table->decimal('berat', 20, 6)->default(0);
                $table->decimal('nilai', 20, 6)->default(0);
                $table->timestamps();

                // Foreign keys & Indexes
                $table->foreign('batch_id', 'neraca_tx_batch_id_fk')
                      ->references('batch_id')->on('data_batches')
                      ->onDelete('cascade');

                $table->foreign('hs_id', 'neraca_tx_hs_id_fk')
                      ->references('hs_id')->on('master_hs')
                      ->onDelete('restrict');

                $table->foreign('negara_id', 'neraca_tx_negara_id_fk')
                      ->references('negara_id')->on('master_negara')
                      ->onDelete('restrict');

                $table->foreign('provinsi_id', 'neraca_tx_provinsi_id_fk')
                      ->references('provinsi_id')->on('master_provinsi')
                      ->onDelete('restrict');

                $table->foreign('kabkota_id', 'neraca_tx_kabkota_id_fk')
                      ->references('kabkota_id')->on('master_kabkota')
                      ->onDelete('restrict');

                $table->foreign('pelabuhan_id', 'neraca_tx_pelabuhan_id_fk')
                      ->references('pelabuhan_id')->on('master_pelabuhan')
                      ->onDelete('restrict');

                $table->index(['tahun', 'bulan', 'jenis_transaksi'], 'neraca_tx_period_idx');
                $table->index(['hs_code_raw', 'mapping_status'], 'neraca_tx_hs_raw_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('neraca_transactions');
        Schema::dropIfExists('distribusi_transactions');
    }
};
