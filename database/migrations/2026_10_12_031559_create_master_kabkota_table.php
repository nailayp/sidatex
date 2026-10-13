<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel kabupaten/kota dan relasinya ke provinsi.
     */
    public function up(): void
    {
        Schema::create('master_kabkota', function (Blueprint $table) {
            $table->increments('kabkota_id');

            // Setiap kabupaten/kota terhubung ke satu provinsi.
            $table->unsignedInteger('provinsi_id');

            $table->string('kode_kabkota', 20)->unique();
            $table->string('nama_kabkota');

            $table->foreign('provinsi_id')
                ->references('provinsi_id')
                ->on('master_provinsi')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Menghapus tabel kabupaten/kota.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_kabkota');
    }
};
