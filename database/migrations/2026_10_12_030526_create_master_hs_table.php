<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel referensi kode HS dan klasifikasi komoditas.
     */
    public function up(): void
    {
        Schema::create('master_hs', function (Blueprint $table) {
            $table->increments('hs_id');

            $table->string('hs_code', 20)->unique();
            $table->string('hs2', 10)->nullable();
            $table->string('hs2_desc')->nullable();

            $table->string('bec_code', 20)->nullable();
            $table->string('bec_desc')->nullable();

            $table->string('sektor')->nullable();
            $table->string('commgrp_code', 20)->nullable();
            $table->string('commgrp_desc')->nullable();
            $table->string('comm_code', 20)->nullable();
            $table->string('comm_desc')->nullable();

            $table->string('kbli2015', 20)->nullable();
            $table->string('desk_kbli20')->nullable();
            $table->text('hs_desc')->nullable();
        });
    }

    /**
     * Menghapus tabel referensi kode HS.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_hs');
    }
};
