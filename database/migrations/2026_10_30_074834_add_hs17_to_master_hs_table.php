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
    Schema::table('master_hs', function (Blueprint $table) {
        $table->string('hs17', 8)->nullable()->index();
    });
}

public function down(): void
{
    Schema::table('master_hs', function (Blueprint $table) {
        $table->dropIndex(['hs17']);
        $table->dropColumn('hs17');
    });
}
};
