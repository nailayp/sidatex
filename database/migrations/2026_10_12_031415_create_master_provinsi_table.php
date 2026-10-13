<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel referensi provinsi.
     */
    public function up(): void
    {
        Schema::create('master_provinsi', function (Blueprint $table) {
            $table->increments('provinsi_id');
            $table->string('kode_provinsi', 20)->unique();
            $table->string('nama_provinsi');
        });
    }

    /**
     * Menghapus tabel referensi provinsi.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_provinsi');
    }
};
