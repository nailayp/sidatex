<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterDataSeeder extends Seeder{
    public function run()
    {
        // 1. DATA 34 PROVINSI INDONESIA (Tetap sama)
        $provinsis = [
            ['kode_provinsi' => '11', 'nama_provinsi' => 'Aceh'],
            ['kode_provinsi' => '12', 'nama_provinsi' => 'Sumatera Utara'],
            ['kode_provinsi' => '13', 'nama_provinsi' => 'Sumatera Barat'],
            ['kode_provinsi' => '14', 'nama_provinsi' => 'Riau'],
            ['kode_provinsi' => '15', 'nama_provinsi' => 'Jambi'],
            ['kode_provinsi' => '16', 'nama_provinsi' => 'Sumatera Selatan'],
            ['kode_provinsi' => '17', 'nama_provinsi' => 'Bengkulu'],
            ['kode_provinsi' => '18', 'nama_provinsi' => 'Lampung'],
            ['kode_provinsi' => '19', 'nama_provinsi' => 'Kepulauan Bangka Belitung'],
            ['kode_provinsi' => '21', 'nama_provinsi' => 'Kepulauan Riau'],
            ['kode_provinsi' => '31', 'nama_provinsi' => 'DKI Jakarta'],
            ['kode_provinsi' => '32', 'nama_provinsi' => 'Jawa Barat'],
            ['kode_provinsi' => '33', 'nama_provinsi' => 'Jawa Tengah'],
            ['kode_provinsi' => '34', 'nama_provinsi' => 'DI Yogyakarta'],
            ['kode_provinsi' => '35', 'nama_provinsi' => 'Jawa Timur'],
            ['kode_provinsi' => '36', 'nama_provinsi' => 'Banten'],
            ['kode_provinsi' => '51', 'nama_provinsi' => 'Bali'],
            ['kode_provinsi' => '52', 'nama_provinsi' => 'Nusa Tenggara Barat'],
            ['kode_provinsi' => '53', 'nama_provinsi' => 'Nusa Tenggara Timur'],
            ['kode_provinsi' => '61', 'nama_provinsi' => 'Kalimantan Barat'],
            ['kode_provinsi' => '62', 'nama_provinsi' => 'Kalimantan Tengah'],
            ['kode_provinsi' => '63', 'nama_provinsi' => 'Kalimantan Selatan'],
            ['kode_provinsi' => '64', 'nama_provinsi' => 'Kalimantan Timur'],
            ['kode_provinsi' => '65', 'nama_provinsi' => 'Kalimantan Utara'],
            ['kode_provinsi' => '71', 'nama_provinsi' => 'Sulawesi Utara'],
            ['kode_provinsi' => '72', 'nama_provinsi' => 'Sulawesi Tengah'],
            ['kode_provinsi' => '73', 'nama_provinsi' => 'Sulawesi Selatan'],
            ['kode_provinsi' => '74', 'nama_provinsi' => 'Sulawesi Tenggara'],
            ['kode_provinsi' => '75', 'nama_provinsi' => 'Gorontalo'],
            ['kode_provinsi' => '76', 'nama_provinsi' => 'Sulawesi Barat'],
            ['kode_provinsi' => '81', 'nama_provinsi' => 'Maluku'],
            ['kode_provinsi' => '82', 'nama_provinsi' => 'Maluku Utara'],
            ['kode_provinsi' => '91', 'nama_provinsi' => 'Papua Barat'],
            ['kode_provinsi' => '94', 'nama_provinsi' => 'Papua']
        ];
        foreach ($provinsis as $prov) {
            DB::table('master_provinsi')->insertOrIgnore($prov);
        }

       // 2. DATA KABUPATEN/KOTA (Fokus Provinsi Riau)

            // Ambil ID Provinsi Riau dari database agar relasinya benar.
            $provinsiRiau = DB::table('master_provinsi')
                ->where('kode_provinsi', '14')
                ->first();

            if (!$provinsiRiau) {
                throw new \RuntimeException(
                    'Provinsi Riau belum tersedia di tabel master_provinsi.'
                );
            }

            // Kode kabupaten/kota mengikuti kode wilayah yang digunakan
            // pada data referensi SIDATEX.
            $kabkotas = [
                ['kode_kabkota' => '1401', 'nama_kabkota' => 'Kab. Kuantan Singingi'],
                ['kode_kabkota' => '1402', 'nama_kabkota' => 'Kab. Indragiri Hulu'],
                ['kode_kabkota' => '1403', 'nama_kabkota' => 'Kab. Indragiri Hilir'],
                ['kode_kabkota' => '1404', 'nama_kabkota' => 'Kab. Pelalawan'],
                ['kode_kabkota' => '1405', 'nama_kabkota' => 'Kab. Siak'],
                ['kode_kabkota' => '1406', 'nama_kabkota' => 'Kab. Kampar'],
                ['kode_kabkota' => '1407', 'nama_kabkota' => 'Kab. Rokan Hulu'],
                ['kode_kabkota' => '1408', 'nama_kabkota' => 'Kab. Bengkalis'],
                ['kode_kabkota' => '1409', 'nama_kabkota' => 'Kab. Rokan Hilir'],
                ['kode_kabkota' => '1410', 'nama_kabkota' => 'Kab. Kepulauan Meranti'],
                ['kode_kabkota' => '1471', 'nama_kabkota' => 'Kota Pekanbaru'],
                ['kode_kabkota' => '1472', 'nama_kabkota' => 'Kota Dumai'],
            ];

            foreach ($kabkotas as $kab) {
                DB::table('master_kabkota')->updateOrInsert(
                    ['kode_kabkota' => $kab['kode_kabkota']],
                    [
                        'provinsi_id' => $provinsiRiau->provinsi_id,
                        'nama_kabkota' => $kab['nama_kabkota'],
                    ]
                );
            }

        // 3. DATA NEGARA (Ditambah Indonesia)
        $negaras = [
            ['kode_negara' => '000', 'nama_negara' => 'Indonesia', 'nation' => 'INDONESIA'], // <- Indonesia ditambahkan
            ['kode_negara' => '111', 'nama_negara' => 'Jepang', 'nation' => 'JAPAN'],
            ['kode_negara' => '112', 'nama_negara' => 'Hongkong', 'nation' => 'HONG KONG'],
            ['kode_negara' => '113', 'nama_negara' => 'Korea Utara', 'nation' => 'KOREA, DEM. PEOPLE\'S REP.'],
            ['kode_negara' => '114', 'nama_negara' => 'Korea Selatan', 'nation' => 'KOREA, REPUBLIC OF'],
            ['kode_negara' => '115', 'nama_negara' => 'Taiwan', 'nation' => 'TAIWAN'],
            ['kode_negara' => '116', 'nama_negara' => 'Tiongkok', 'nation' => 'CHINA'],
            ['kode_negara' => '121', 'nama_negara' => 'Malaysia', 'nation' => 'MALAYSIA'],
            ['kode_negara' => '122', 'nama_negara' => 'Singapura', 'nation' => 'SINGAPORE'],
            ['kode_negara' => '123', 'nama_negara' => 'Thailand', 'nation' => 'THAILAND'],
            ['kode_negara' => '124', 'nama_negara' => 'Filipina', 'nation' => 'PHILIPPINES'],
            ['kode_negara' => '125', 'nama_negara' => 'Vietnam', 'nation' => 'VIET NAM'],
            ['kode_negara' => '212', 'nama_negara' => 'Amerika Serikat', 'nation' => 'UNITED STATES'],
            ['kode_negara' => '311', 'nama_negara' => 'Inggris', 'nation' => 'UNITED KINGDOM'],
            ['kode_negara' => '312', 'nama_negara' => 'Belanda', 'nation' => 'NETHERLANDS'],
            ['kode_negara' => '313', 'nama_negara' => 'Jerman', 'nation' => 'GERMANY'],
            ['kode_negara' => '411', 'nama_negara' => 'Australia', 'nation' => 'AUSTRALIA'],
        ];
        foreach ($negaras as $neg) {
            DB::table('master_negara')->insertOrIgnore($neg);
        }

        // 4. DATA HS CODE (Kelompok HS 2 Digit)
        // Sumber data: database/seeders/data/daftar_hs2_sidatex.csv

        $filePath = database_path('seeders/data/daftar_hs2_sidatex.csv');

        // Pastikan file sumber tersedia sebelum proses impor dimulai.
        if (!file_exists($filePath)) {
            throw new \RuntimeException(
                'File daftar HS2 tidak ditemukan: ' . $filePath
            );
        }

        $file = fopen($filePath, 'r');

        if ($file === false) {
            throw new \RuntimeException(
                'File daftar HS2 tidak dapat dibuka.'
            );
        }

        // Lewati baris pertama karena berisi nama kolom.
        fgetcsv($file);

        while (($row = fgetcsv($file)) !== false) {
            $hs2 = str_pad(trim($row[0] ?? ''), 2, '0', STR_PAD_LEFT);
            $hs2Desc = trim($row[1] ?? '');

            // Abaikan baris yang tidak memiliki kode atau deskripsi.
            if (!preg_match('/^\d{2}$/', $hs2) || $hs2Desc === '') {
                continue;
            }

           // Cari kelompok HS2 yang sudah tersimpan.
$existingHs = DB::table('master_hs')
    ->where('hs2', $hs2)
    ->orderBy('hs_id')
    ->first();

if ($existingHs) {
    // Perbarui deskripsi tanpa mengubah ID dan kode HS yang lama.
    DB::table('master_hs')
        ->where('hs_id', $existingHs->hs_id)
        ->update([
            'hs2_desc' => $hs2Desc,
        ]);

    continue;
}

            // Gunakan format kode yang konsisten dengan data master saat ini.
            $hsCode = $hs2 . '0000';

            // Periksa apakah kode tersebut sudah digunakan oleh baris lain.
            // Jangan menimpa data yang memiliki kode sama tetapi HS2 berbeda.
            $existingCode = DB::table('master_hs')
                ->where('hs_code', $hsCode)
                ->first();

            if ($existingCode) {
                if ($existingCode->hs2 !== null && $existingCode->hs2 !== $hs2) {
                    throw new \RuntimeException(
                        "Kode HS {$hsCode} sudah digunakan oleh HS2 {$existingCode->hs2}, "
                        . "sedangkan CSV memuat HS2 {$hs2}."
                    );
                }

                // Kode sudah ada, tetapi HS2 belum terisi.
                // Isi HS2 dan deskripsi tanpa membuat ID baru.
                DB::table('master_hs')
                    ->where('hs_id', $existingCode->hs_id)
                    ->update([
                        'hs2' => $hs2,
                        'hs2_desc' => $hs2Desc,
                    ]);

                continue;
            }

            // Tambahkan kelompok HS2 baru.
            DB::table('master_hs')->insert([
                'hs_code' => $hsCode,
                'hs2' => $hs2,
                'hs2_desc' => $hs2Desc,
            ]);
        }

        fclose($file);
    }
}
