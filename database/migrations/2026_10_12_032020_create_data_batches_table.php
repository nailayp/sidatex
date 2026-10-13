
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyimpan informasi file Excel yang diunggah ke SIDATEX.
     */
    public function up(): void
    {
        Schema::create('data_batches', function (Blueprint $table) {
            $table->increments('batch_id');

            $table->string('nama_file');
            $table->string('jenis_data', 20); // Ekspor atau Impor.

            // Status menentukan apakah data boleh ditampilkan kepada pengguna.
            $table->enum('status', [
                'DRAFT',
                'NON-RILIS',
                'RILIS',
            ])->default('DRAFT');

            // Pengguna yang mengunggah file.
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at')->useCurrent();

            // Diisi ketika Super Admin merilis data.
            $table->unsignedBigInteger('released_by')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->timestamps();

            $table->foreign('uploaded_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign('released_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Menghapus tabel batch data.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_batches');
    }
};
