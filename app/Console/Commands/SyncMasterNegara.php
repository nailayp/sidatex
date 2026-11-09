<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

#[Signature('app:sync-master-negara')]
#[Description('Menyinkronkan referensi negara dan wilayah dari file JSON')]
class SyncMasterNegara extends Command
{
    public function handle(): int
    {
        $path = storage_path('app/master_negara.json');

        // Pastikan file referensi tersedia sebelum database diproses.
        if (!File::exists($path)) {
            $this->error('File master_negara.json tidak ditemukan.');

            return self::FAILURE;
        }

        $data = json_decode(File::get($path), true);

        // Hentikan proses jika struktur atau jumlah data tidak sesuai.
        if (!is_array($data) || count($data) !== 259) {
            $this->error('Data JSON tidak valid atau jumlahnya bukan 259.');

            return self::FAILURE;
        }

        // Periksa seluruh data terlebih dahulu agar data tidak valid
        // tidak ikut dimasukkan ke database.
        foreach ($data as $row) {
            if (
                !isset($row['kode_negara'], $row['nama_negara']) ||
                !preg_match('/^\d{3}$/', (string) $row['kode_negara']) ||
                trim((string) $row['nama_negara']) === ''
            ) {
                $this->error(
                    'Ada data negara yang tidak valid. Database tidak diubah.'
                );

                return self::FAILURE;
            }
        }

        try {
            DB::transaction(function () use ($data) {
                foreach ($data as $row) {
                    $kode = (string) $row['kode_negara'];

                    DB::table('master_negara')->updateOrInsert(
                        ['kode_negara' => $kode],
                        [
                            'nama_negara' => trim(
                                (string) $row['nama_negara']
                            ),
                            'nation' => isset($row['nation'])
                                ? trim((string) $row['nation'])
                                : null,
                        ]
                    );
                }
            });
        } catch (Throwable $e) {
            report($e);

            $this->error(
                'Sinkronisasi gagal. Periksa log aplikasi untuk detailnya.'
            );

            return self::FAILURE;
        }

        $jumlah = DB::table('master_negara')->count();

        $this->info('Sinkronisasi referensi negara/wilayah selesai.');
        $this->line("Jumlah baris di master_negara sekarang: {$jumlah}");

        return self::SUCCESS;
    }
}
