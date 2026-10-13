<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel referensi pelabuhan dan wilayahnya.
     */
    public function up(): void
    {
        Schema::create('master_pelabuhan', function (Blueprint $table) {
            $table->increments('pelabuhan_id');

            // Menghubungkan pelabuhan dengan kabupaten/kota.
            $table->unsignedInteger('kabkota_id');

            $table->string('kode_pelabuhan', 30)->unique();
            $table->string('nama_pelabuhan');

            $table->foreign('kabkota_id')
                ->references('kabkota_id')
                ->on('master_kabkota')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Menghapus tabel referensi pelabuhan.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_pelabuhan');
    }
};
