
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel komentar pada batch data SIDATEX.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->increments('comment_id');

            // Menyimpan pengguna dan batch yang diberi komentar.
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('batch_id');

            $table->text('komentar');

            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign('batch_id')
                ->references('batch_id')
                ->on('data_batches')
                ->cascadeOnDelete();

            // Memudahkan pencarian komentar pada suatu batch.
            $table->index(['batch_id', 'created_at']);
        });
    }

    /**
     * Menghapus tabel komentar jika migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
