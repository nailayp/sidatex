<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sidatex:reset-super-admin-password {--stdin : Read hidden password input from standard input.}')]
#[Description('Atur ulang password akun Super Admin SIDATEX yang sudah ada.')]
class ResetSuperAdminPassword extends Command
{
    private const USERNAME = 'admin_sidatex';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $readFromStandardInput = (bool) $this->option('stdin');

        if ($readFromStandardInput && $this->input->isInteractive()) {
            $this->error('Mode input aman hanya dapat digunakan dengan input yang dialirkan dari helper PowerShell.');

            return self::FAILURE;
        }

        if (! $readFromStandardInput && ! $this->input->isInteractive()) {
            $this->error('Jalankan perintah ini di terminal interaktif atau gunakan helper PowerShell.');

            return self::FAILURE;
        }

        if (! $readFromStandardInput && PHP_OS_FAMILY === 'Windows') {
            $this->error('Prompt password tersembunyi tidak kompatibel dengan terminal Windows ini. Jalankan .\\scripts\\reset-super-admin-password.ps1.');

            return self::FAILURE;
        }

        $user = User::query()
            ->where('username', self::USERNAME)
            ->first();

        if (! $user) {
            $this->error('Akun admin_sidatex tidak ditemukan. Tidak ada akun yang dibuat atau diubah.');

            return self::FAILURE;
        }

        if ($user->role?->nama_role !== 'Super Admin') {
            $this->error('Akun admin_sidatex tidak memiliki role Super Admin. Password tidak diubah.');

            return self::FAILURE;
        }

        if (! $user->is_active) {
            $this->error('Akun admin_sidatex tidak aktif. Password tidak diubah; status akun tetap seperti semula.');

            return self::FAILURE;
        }

        if ($readFromStandardInput) {
            $passwords = $this->readPasswordsFromStandardInput();

            if ($passwords === null) {
                $this->error('Input password tidak lengkap. Password tidak diubah.');

                return self::FAILURE;
            }
        } else {
            $passwords = $this->readPasswordsFromHiddenPrompt();
        }

        $newPassword = $passwords['password'];
        $confirmation = $passwords['confirmation'];

        if (! is_string($newPassword) || strlen($newPassword) < 12) {
            $this->error('Password tidak memenuhi panjang minimum. Password tidak diubah.');

            return self::FAILURE;
        }

        if (! is_string($confirmation) || ! hash_equals($newPassword, $confirmation)) {
            $this->error('Konfirmasi password tidak cocok. Password tidak diubah.');

            return self::FAILURE;
        }

        // Model User memiliki cast hashed, sehingga password di-hash sebelum disimpan.
        $user->password = $newPassword;
        $user->save();

        $this->info('Password akun admin_sidatex berhasil diperbarui. Nilai password tidak ditampilkan.');

        return self::SUCCESS;
    }

    /**
     * @return array{password: string, confirmation: string}|null
     */
    private function readPasswordsFromStandardInput(): ?array
    {
        $password = fgets(STDIN);
        $confirmation = fgets(STDIN);

        if ($password === false || $confirmation === false) {
            return null;
        }

        return [
            'password' => rtrim($password, "\r\n"),
            'confirmation' => rtrim($confirmation, "\r\n"),
        ];
    }

    /**
     * @return array{password: string, confirmation: string}
     */
    private function readPasswordsFromHiddenPrompt(): array
    {
        return [
            'password' => (string) $this->secret('Password baru (ketikan tidak ditampilkan, minimal 12 karakter)', false),
            'confirmation' => (string) $this->secret('Ulangi password baru (ketikan tidak ditampilkan)', false),
        ];
    }
}
