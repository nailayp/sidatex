
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel untuk mencatat aktivitas pengguna SIDATEX.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->increments('log_id');

            // Pengguna yang melakukan aktivitas.
            $table->unsignedBigInteger('user_id');

            // Contoh: LOGIN, UPLOAD, RELEASE, DELETE, atau RESTORE.
            $table->string('aktivitas', 50);
            $table->string('modul', 100)->nullable();
            $table->text('deskripsi')->nullable();

            // Menyimpan alamat IP untuk membantu penelusuran aktivitas.
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            // Mempercepat pencarian log berdasarkan pengguna dan waktu.
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Menghapus tabel log aktivitas jika migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
