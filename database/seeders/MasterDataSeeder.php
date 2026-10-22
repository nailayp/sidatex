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
        $kabkotas = [
            ['kabkota_id' => '1401', 'nama_kabkota' => 'Kab. Kuantan Singingi'],
            ['kabkota_id' => '1402', 'nama_kabkota' => 'Kab. Indragiri Hulu'],
            ['kabkota_id' => '1403', 'nama_kabkota' => 'Kab. Indragiri Hilir'],
            ['kabkota_id' => '1404', 'nama_kabkota' => 'Kab. Pelalawan'],
            ['kabkota_id' => '1405', 'nama_kabkota' => 'Kab. Siak'],
            ['kabkota_id' => '1406', 'nama_kabkota' => 'Kab. Kampar'],
            ['kabkota_id' => '1407', 'nama_kabkota' => 'Kab. Rokan Hulu'],
            ['kabkota_id' => '1408', 'nama_kabkota' => 'Kab. Bengkalis'],
            ['kabkota_id' => '1409', 'nama_kabkota' => 'Kab. Rokan Hilir'],
            ['kabkota_id' => '1410', 'nama_kabkota' => 'Kab. Kepulauan Meranti'],
            ['kabkota_id' => '1471', 'nama_kabkota' => 'Kota Pekanbaru'],
            ['kabkota_id' => '1472', 'nama_kabkota' => 'Kota Dumai'],
        ];
        foreach ($kabkotas as $kab) {
            DB::table('master_kabkota')->insertOrIgnore($kab);
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

        // 4. DATA HS CODE (Tetap sama)
        $hscodes = [
            ['hs2' => '01', 'hs2_desc' => 'Binatang Hidup'],
            ['hs2' => '02', 'hs2_desc' => 'Daging Hewan'],
            ['hs2' => '03', 'hs2_desc' => 'Ikan dan Udang'],
            ['hs2' => '15', 'hs2_desc' => 'Lemak & Minyak Hewan/Nabati'],
            ['hs2' => '27', 'hs2_desc' => 'Bahan Bakar Mineral'],
            ['hs2' => '44', 'hs2_desc' => 'Kayu, Barang dari Kayu'],
            ['hs2' => '47', 'hs2_desc' => 'Bubur Kayu (Pulp)'],
            ['hs2' => '48', 'hs2_desc' => 'Kertas dan Karton'],
            ['hs2' => '61', 'hs2_desc' => 'Barang-barang Rajutan'],
            ['hs2' => '85', 'hs2_desc' => 'Mesin/Peralatan Listrik'],
        ];
        foreach ($hscodes as $hs) {
            DB::table('master_hs')->insertOrIgnore([
                'hs_code' => $hs['hs2'] . '0000',
                'hs2' => $hs['hs2'],
                'hs2_desc' => $hs['hs2_desc'],
            ]);
        }
    }
}
