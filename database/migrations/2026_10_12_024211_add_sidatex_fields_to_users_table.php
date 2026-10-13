<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan identitas dan hak akses pengguna SIDATEX.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Relasi pengguna dengan tabel roles.
            $table->unsignedInteger('role_id')->nullable();

            // Identitas pegawai BPS.
            $table->string('nip', 30)->nullable()->unique();
            $table->string('nama')->nullable();

            // Username untuk login; email bawaan Laravel tetap tersedia.
            $table->string('username')->nullable()->unique();

            // Penanda apakah akun boleh digunakan.
            $table->boolean('is_active')->default(true);

            $table->foreign('role_id')
                ->references('role_id')
                ->on('roles')
                ->nullOnDelete();
        });
    }


    /**
     * Menghapus kolom tambahan ketika migration dibatalkan.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hapus relasi sebelum menghapus kolom role_id.
            $table->dropForeign(['role_id']);

            // Hapus indeks unik berdasarkan kolomnya.
            $table->dropUnique(['nip']);
            $table->dropUnique(['username']);

            // Hapus kolom tambahan SIDATEX.
            $table->dropColumn([
                'role_id',
                'nip',
                'nama',
                'username',
                'is_active',
            ]);
        });
    }
};
