
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel untuk tiga jenis hak akses SIDATEX.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('role_id');
            $table->string('nama_role', 50)->unique();
        });
    }

    /**
     * Menghapus tabel roles ketika migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
