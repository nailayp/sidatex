<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ubah enum kolom status pada data_batches agar mencakup 'DRAFT', 'PRIVATE', 'NON-RILIS', 'RILIS'
        DB::statement("ALTER TABLE `data_batches` MODIFY COLUMN `status` ENUM('DRAFT', 'PRIVATE', 'NON-RILIS', 'RILIS') NOT NULL DEFAULT 'DRAFT'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `data_batches` MODIFY COLUMN `status` ENUM('DRAFT', 'NON-RILIS', 'RILIS') NOT NULL DEFAULT 'DRAFT'");
    }
};
