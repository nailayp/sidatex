<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel referensi negara untuk data ekspor-impor.
     */
    public function up(): void
    {
        Schema::create('master_negara', function (Blueprint $table) {
            $table->increments('negara_id');

            $table->string('kode_negara', 20)->unique();
            $table->string('nama_negara');
            $table->string('nation')->nullable();
        });
    }

    /**
     * Menghapus tabel referensi negara.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_negara');
    }
};
