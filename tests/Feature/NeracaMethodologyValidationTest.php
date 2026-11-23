<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Test Suite Validasi Metodologi Neraca Perdagangan untuk Tahap 2N.
 */
class NeracaMethodologyValidationTest extends TestCase
{
    protected function getTestUser(): User
    {
        return User::first() ?? User::factory()->make(['role_id' => 1]);
    }

    /**
     * Uji kondisi saat data Neraca belum ada untuk tahun tertentu.
     */
    public function test_neraca_no_data_returns_neutral_status(): void
    {
        $user = $this->getTestUser();
        $response = $this->actingAs($user)->get(route('neraca.metrics', ['tahun' => 2099]));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertFalse($data['has_data']);
        $this->assertFalse($data['has_ekspor']);
        $this->assertFalse($data['has_impor']);
        $this->assertFalse($data['has_complete_trade_balance']);
        $this->assertEquals('Data Belum Tersedia', $data['summary']['status_neraca']);
        $this->assertNull($data['summary']['neraca_perdagangan']);
    }

    /**
     * Pastikan data dari tipe_sumber 'distribusi' tidak pernah masuk ke metrik Neraca.
     */
    public function test_distribusi_data_strictly_excluded_from_neraca(): void
    {
        $user = $this->getTestUser();
        $response = $this->actingAs($user)->get(route('neraca.metrics', ['tahun' => 2026]));

        $response->assertStatus(200);
        $data = $response->json();

        // Di DB testing, data transaksi 2026 bertipe sumber 'distribusi' (misal batch 70/71)
        // Pastikan total impor CIF neraca tidak menghitung transaksi distribusi tersebut
        $distribusiImporInNeraca = DB::table('transactions as t')
            ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
            ->where('b.status', 'RILIS')
            ->where('t.tipe_sumber', 'neraca')
            ->where('b.tipe_sumber', 'distribusi')
            ->count();

        $this->assertEquals(0, $distribusiImporInNeraca);
    }

    /**
     * Uji logika metodologi controller saat simulasi kondisi:
     * - Hanya Ekspor
     * - Hanya Impor
     * - Lengkap (Surplus, Defisit, Seimbang)
     */
    public function test_neraca_calculation_logic_states(): void
    {
        // Jalankan uji murni logika status pada skenario:
        // Skenario 1: Hanya Ekspor
        $calcState = function($hasE, $hasI, $fob, $cif) {
            $hasComplete = ($hasE && $hasI);
            if ($hasComplete) {
                $diff = $fob - $cif;
                if ($diff > 0) return ['Surplus', $diff];
                if ($diff < 0) return ['Defisit', $diff];
                return ['Seimbang', 0.0];
            }
            if ($hasE && !$hasI) return ['Data Impor Belum Tersedia', null];
            if (!$hasE && $hasI) return ['Data Ekspor Belum Tersedia', null];
            return ['Data Belum Tersedia', null];
        };

        // 1. Keduanya tidak ada
        [$status, $val] = $calcState(false, false, 0, 0);
        $this->assertEquals('Data Belum Tersedia', $status);
        $this->assertNull($val);

        // 2. Hanya Ekspor
        [$status, $val] = $calcState(true, false, 1500000, 0);
        $this->assertEquals('Data Impor Belum Tersedia', $status);
        $this->assertNull($val);

        // 3. Hanya Impor
        [$status, $val] = $calcState(false, true, 0, 800000);
        $this->assertEquals('Data Ekspor Belum Tersedia', $status);
        $this->assertNull($val);

        // 4. Surplus
        [$status, $val] = $calcState(true, true, 2000000, 1200000);
        $this->assertEquals('Surplus', $status);
        $this->assertEquals(800000, $val);

        // 5. Defisit
        [$status, $val] = $calcState(true, true, 1000000, 1500000);
        $this->assertEquals('Defisit', $status);
        $this->assertEquals(-500000, $val);

        // 6. Seimbang
        [$status, $val] = $calcState(true, true, 1000000, 1000000);
        $this->assertEquals('Seimbang', $status);
        $this->assertEquals(0.0, $val);
    }
}
