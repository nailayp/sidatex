<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Menjalankan seeder utama SIDATEX.
     * Seeder ini menyiapkan data awal yang dibutuhkan sistem.
     */

    public function run(): void
    {
        // Pastikan role tersedia sebelum akun dibuat.
        $this->call([
            RoleSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
