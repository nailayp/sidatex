<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('data_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('data_batches', 'ukuran_file')) {
                $table->decimal('ukuran_file', 8, 2)->nullable()->after('jenis_data');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_batches', function (Blueprint $table) {
            if (Schema::hasColumn('data_batches', 'ukuran_file')) {
                $table->dropColumn('ukuran_file');
            }
        });
    }
};
