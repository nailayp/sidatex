<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_hs_mappings', function (Blueprint $table) {
            $table->id('mapping_id');
            $table->string('hs22', 8)->index();
            $table->string('hs17', 8)->index();
            $table->timestamps();

            $table->unique(['hs22', 'hs17']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_hs_mappings');
    }
};
