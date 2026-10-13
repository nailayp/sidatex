<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Membuat akun Super Admin awal untuk pengelolaan SIDATEX.
     */
    public function run(): void
    {
        // Cari role Super Admin yang sudah dibuat oleh RoleSeeder.
        $role = Role::where('nama_role', 'Super Admin')->firstOrFail();

        // Buat akun awal jika username belum terdaftar.
        // Ganti nilai identitas dan password sebelum menjalankan seeder.
        User::firstOrCreate(
            ['username' => 'admin_sidatex'],
            [
                'role_id' => $role->role_id,
                'nip' => null,
                'name' => 'Super Admin SIDATEX',
                'nama' => 'Super Admin SIDATEX',
                'email' => 'admin@sidatex.local',
                'password' => Hash::make('GANTI_PASSWORD_INI'),
                'is_active' => true,
            ]
        );
    }
}
