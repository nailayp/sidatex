<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Mengisi tiga jenis hak akses pengguna SIDATEX.
     */
    public function run(): void
    {
        $roles = [
            ['nama_role' => 'Super Admin'],
            ['nama_role' => 'Pegawai'],
            ['nama_role' => 'Viewer Eksekutif'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['nama_role' => $role['nama_role']],
                $role
            );
        }
    }
}
