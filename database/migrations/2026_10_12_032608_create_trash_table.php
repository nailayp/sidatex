
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyimpan snapshot data yang dihapus agar dapat dipulihkan.
     */
    public function up(): void
    {
        Schema::create('trash', function (Blueprint $table) {
            $table->increments('trash_id');

            // Batch asal dari data yang dihapus.
            $table->unsignedInteger('batch_id');

            // Snapshot menyimpan salinan data sebelum dihapus.
            $table->json('data_snapshot');

            $table->unsignedBigInteger('deleted_by');
            $table->timestamp('deleted_at')->useCurrent();

            // Diisi ketika data berhasil dipulihkan.
            $table->unsignedBigInteger('restored_by')->nullable();
            $table->timestamp('restored_at')->nullable();

            $table->foreign('batch_id')
                ->references('batch_id')
                ->on('data_batches')
                ->restrictOnDelete();

            $table->foreign('deleted_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign('restored_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['deleted_at', 'restored_at']);
        });
    }

    /**
     * Menghapus tabel trash jika migration dibatalkan.
     */
    public function down(): void
    {
        Schema::dropIfExists('trash');
    }
};
