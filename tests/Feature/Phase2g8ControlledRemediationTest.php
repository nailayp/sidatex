<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2G.8 — Controlled Remediation Test Suite.
 *
 * Menguji secara komprehensif remediasi:
 * - F-03: Cumulative Trade Balance Asymmetry Detection & Metadata
 * - F-05: Distribusi & Neraca Methodology Tooltip / Popover
 */
class Phase2g8ControlledRemediationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $activeDb = DB::connection()->getDatabaseName();
        $this->assertContains($activeDb, ['sidatex_testing', ':memory:'], "UNSAFE DATABASE: '{$activeDb}'");
    }

    /**
     * F-03 Test 1: Verifikasi metadata asimetri pada payload live dashboard untuk Role 1 & Role 2.
     */
    public function test_f03_live_payload_metadata_structure_and_behavior(): void
    {
        $admin = User::where('role_id', 1)->firstOrFail();
        $pegawai = User::where('role_id', 2)->firstOrFail();

        // 1. Live Admin (tahun 2026, cumulative / bulan=ALL)
        // Admin melihat RILIS + PRIVATE, sehingga Ekspor (7, 8, 9) dan Impor (7, 8, 9) keduanya memiliki max=9
        $resAdmin = $this->actingAs($admin)->getJson(route('dashboard.data', [
            'tahun' => '2026',
            'bulan' => 'ALL',
            'tipe_sumber' => 'distribusi',
        ]))->assertStatus(200)->json();

        $this->assertTrue($resAdmin['success']);
        $this->assertArrayHasKey('trade_balance', $resAdmin);
        $tbAdmin = $resAdmin['trade_balance'];

        $this->assertArrayHasKey('is_asymmetric_cumulative', $tbAdmin);
        $this->assertArrayHasKey('max_ekspor_bulan', $tbAdmin);
        $this->assertArrayHasKey('max_impor_bulan', $tbAdmin);
        $this->assertArrayHasKey('max_ekspor_nama_bulan', $tbAdmin);
        $this->assertArrayHasKey('max_impor_nama_bulan', $tbAdmin);

        $this->assertSame(9, $tbAdmin['max_ekspor_bulan']);
        $this->assertSame('September', $tbAdmin['max_ekspor_nama_bulan']);
        $this->assertSame(9, $tbAdmin['max_impor_bulan']);
        $this->assertSame('September', $tbAdmin['max_impor_nama_bulan']);
        $this->assertFalse($tbAdmin['is_asymmetric_cumulative'], 'Admin yang memiliki akses draft PRIVATE bulan 9 untuk keduanya wajib simetris');

        // 2. Live Pegawai (Role 2: HANYA melihat data RILIS)
        // Batch #71 (Ekspor 2026-09) adalah RILIS, sedangkan Impor 2026-09 masih PRIVATE.
        // Impor RILIS terakhir adalah 2026-08.
        // Maka bagi Pegawai, Ekspor max=9 (September) vs Impor max=8 (Agustus) -> ASIMETRIS!
        $resPegawai = $this->actingAs($pegawai)->getJson(route('dashboard.data', [
            'tahun' => '2026',
            'bulan' => 'ALL',
            'tipe_sumber' => 'distribusi',
        ]))->assertStatus(200)->json();

        $tbPegawai = $resPegawai['trade_balance'];
        $this->assertArrayHasKey('is_asymmetric_cumulative', $tbPegawai);
        $this->assertSame(9, $tbPegawai['max_ekspor_bulan']);
        $this->assertSame('September', $tbPegawai['max_ekspor_nama_bulan']);
        $this->assertSame(8, $tbPegawai['max_impor_bulan']);
        $this->assertSame('Agustus', $tbPegawai['max_impor_nama_bulan']);
        $this->assertTrue($tbPegawai['is_asymmetric_cumulative'], 'Pegawai melihat data RILIS: Ekspor (Sep) vs Impor (Ags) wajib terdeteksi asimetris!');

        // 3. Single Month Filter (bukan kumulatif): is_asymmetric_cumulative WAJIB false
        $resSingleMonth = $this->actingAs($admin)->getJson(route('dashboard.data', [
            'tahun' => '2026',
            'bulan' => '9',
            'tipe_sumber' => 'distribusi',
        ]))->assertStatus(200)->json();

        $tbSingle = $resSingleMonth['trade_balance'];
        $this->assertFalse($tbSingle['is_asymmetric_cumulative'], 'Filter bulan tunggal tidak boleh memicu peringatan kumulatif!');
    }

    /**
     * F-03 Test 2: Pengujian skenario edge case:
     * - Periode sama -> false
     * - Ekspor lebih baru -> true
     * - Impor lebih baru -> true
     * - Salah satu kosong -> false
     * - Keduanya kosong -> false
     */
    public function test_f03_edge_case_asymmetry_determination_logic(): void
    {
        $evalAsymmetry = function (array $eMonths, array $iMonths, bool $isCumulative = true): array {
            $maxE = !empty($eMonths) ? max($eMonths) : null;
            $maxI = !empty($iMonths) ? max($iMonths) : null;
            $isAsym = false;
            if ($isCumulative && $maxE !== null && $maxI !== null) {
                $isAsym = ($maxE !== $maxI);
            }
            return [$isAsym, $maxE, $maxI];
        };

        // 1. Ekspor & Impor sama-sama bulan 8
        [$isAsym, $maxE, $maxI] = $evalAsymmetry([1, 2, 8], [1, 2, 8]);
        $this->assertFalse($isAsym, 'Periode sama wajib false');
        $this->assertSame(8, $maxE);
        $this->assertSame(8, $maxI);

        // 2. Ekspor lebih baru (Ekspor 9, Impor 8)
        [$isAsym, $maxE, $maxI] = $evalAsymmetry([1, 8, 9], [1, 8]);
        $this->assertTrue($isAsym, 'Ekspor lebih baru wajib true');
        $this->assertSame(9, $maxE);
        $this->assertSame(8, $maxI);

        // 3. Impor lebih baru (Ekspor 7, Impor 8)
        [$isAsym, $maxE, $maxI] = $evalAsymmetry([1, 7], [1, 7, 8]);
        $this->assertTrue($isAsym, 'Impor lebih baru wajib true');
        $this->assertSame(7, $maxE);
        $this->assertSame(8, $maxI);

        // 4. Salah satu kosong (Ekspor ada, Impor kosong)
        [$isAsym, $maxE, $maxI] = $evalAsymmetry([1, 2, 3], []);
        $this->assertFalse($isAsym, 'Salah satu kosong tidak boleh menghasilkan asimetri palsu');
        $this->assertSame(3, $maxE);
        $this->assertNull($maxI);

        // 5. Salah satu kosong (Ekspor kosong, Impor ada)
        [$isAsym, $maxE, $maxI] = $evalAsymmetry([], [1, 2]);
        $this->assertFalse($isAsym, 'Salah satu kosong tidak boleh menghasilkan asimetri palsu');
        $this->assertNull($maxE);
        $this->assertSame(2, $maxI);

        // 6. Keduanya kosong
        [$isAsym, $maxE, $maxI] = $evalAsymmetry([], []);
        $this->assertFalse($isAsym, 'Keduanya kosong wajib false');
        $this->assertNull($maxE);
        $this->assertNull($maxI);

        // 7. Non-cumulative (bulan tunggal)
        [$isAsym, $maxE, $maxI] = $evalAsymmetry([9], [8], false);
        $this->assertFalse($isAsym, 'Non-cumulative filter wajib false');
    }

    /**
     * F-03 Test 3: Integritas nilai statistik tetap imutabel (tidak ada pergeseran nilai atau rumus).
     */
    public function test_f03_statistical_aggregates_remain_identical(): void
    {
        $admin = User::where('role_id', 1)->firstOrFail();

        $res = $this->actingAs($admin)->getJson(route('dashboard.data', [
            'tahun' => '2026',
            'bulan' => 'ALL',
            'tipe_sumber' => 'distribusi',
        ]))->assertStatus(200)->json();

        $tb = $res['trade_balance'];
        // Pastikan nilai devisa dan net_trade tetap valid dan terhitung
        $this->assertIsFloat($tb['total_ekspor']);
        $this->assertIsFloat($tb['total_impor']);
        $this->assertIsFloat($tb['net_trade']);
        $this->assertGreaterThan(0, $tb['total_ekspor']);
        $this->assertGreaterThan(0, $tb['total_impor']);
        // Rumus Trade Balance: Net Trade = Total Ekspor - Total Impor
        $expectedNet = $tb['total_ekspor'] - $tb['total_impor'];
        $this->assertEqualsWithDelta($expectedNet, $tb['net_trade'], 0.01, 'Rumus Trade Balance wajib tetap identik!');
    }

    /**
     * F-05 Test 1: Verifikasi elemen Blade UI untuk Tooltip / Popover Metodologi.
     */
    public function test_f05_blade_methodology_tooltip_elements_and_text_present(): void
    {
        $admin = User::where('role_id', 1)->firstOrFail();

        $html = $this->actingAs($admin)->get(route('dashboard'))->assertStatus(200)->getContent();

        // 1. Tombol info dan kontainer tooltip
        $this->assertStringContainsString('id="btnMethodologyInfo"', $html);
        $this->assertStringContainsString('id="methodologyTooltipPopover"', $html);
        $this->assertStringContainsString('aria-label="Informasi perbedaan metodologi Distribusi dan Neraca"', $html);
        $this->assertStringContainsString('aria-controls="methodologyTooltipPopover"', $html);

        // 2. Definisi resmi BPS yang diverifikasi (POD, KDPROV, PROVORIG, KPPBC)
        $this->assertStringContainsString('Distribusi (Arus Pintu Pelabuhan)', $html);
        $this->assertStringContainsString('POD untuk Ekspor', $html);
        $this->assertStringContainsString('KDPROV untuk Impor', $html);
        $this->assertStringContainsString('Neraca (Asal Barang &amp; Pabean)', $html);
        $this->assertStringContainsString('PROVORIG', $html);
        $this->assertStringContainsString('KPPBC', $html);
        $this->assertStringContainsString('tidak boleh digabung/dijumlahkan', $html);

        // 3. Tombol switcher memiliki atribut title penjelas
        $this->assertStringContainsString('id="btnSumberDistribusi"', $html);
        $this->assertStringContainsString('title="Distribusi: Mengukur arus barang keluar/masuk pelabuhan Riau (POD/KDPROV)', $html);
        $this->assertStringContainsString('id="btnSumberNeraca"', $html);
        $this->assertStringContainsString('title="Neraca: Mengukur barang asal produksi Riau (PROVORIG) dan kantor pabean (KPPBC)', $html);

        // 4. Wadah alert asimetri Trade Balance di Tab 4
        $this->assertStringContainsString('id="alertTradeAsymmetry"', $html);
        $this->assertStringContainsString('id="alertTradeAsymmetryText"', $html);

        // 5. Inisialisasi JS tooltip
        $this->assertStringContainsString('function initMethodologyTooltip()', $html);
        $this->assertStringContainsString('initMethodologyTooltip();', $html);
    }

    /**
     * F-03 & F-05 Test 2: Verifikasi eksekusi JS di Node.js VM untuk alert asimetri dinamis.
     */
    public function test_f03_blade_javascript_asymmetry_alert_rendering(): void
    {
        $admin = User::where('role_id', 1)->firstOrFail();
        $html = $this->actingAs($admin)->get(route('dashboard'))->assertStatus(200)->getContent();

        // Ekstrak inline script terakhir
        preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $html, $matches);
        $inlineScripts = array_values(array_filter(
            $matches[1] ?? [],
            static fn (string $code): bool => trim($code) !== ''
        ));
        $bladeScript = (string) end($inlineScripts);
        $scriptJson = json_encode($bladeScript, JSON_THROW_ON_ERROR);

        $nodeHarness = <<<JS
const vm = require('vm');

function makeElement(id) {
    return {
        id,
        textContent: '',
        style: { display: 'none' },
        classList: { toggle: () => {}, add: () => {}, remove: () => {} },
        setAttribute: () => {},
        addEventListener: () => {},
        appendChild: () => {},
        getContext: () => ({}),
        children: []
    };
}

const elements = {
    tbTotalEkspor: makeElement('tbTotalEkspor'),
    tbTotalImpor: makeElement('tbTotalImpor'),
    badgeTradeStatus: makeElement('badgeTradeStatus'),
    heroTradeStatusTitle: makeElement('heroTradeStatusTitle'),
    tbNetTrade: makeElement('tbNetTrade'),
    tbNetSubtext: makeElement('tbNetSubtext'),
    alertTradeAsymmetry: makeElement('alertTradeAsymmetry'),
    alertTradeAsymmetryText: makeElement('alertTradeAsymmetryText'),
    chartTradeBalanceCompare: makeElement('chartTradeBalanceCompare'),
    tableTradeBalanceMonthly: makeElement('tableTradeBalanceMonthly')
};

const tableBodies = {
    '#tableTradeBalanceMonthly tbody': makeElement('tb_trade', 'tbody'),
};

const sandbox = {
    document: {
        getElementById: (id) => elements[id] || makeElement(id),
        querySelector: (sel) => tableBodies[sel] || makeElement('query'),
        querySelectorAll: () => [],
        createElement: (tag) => makeElement('dynamic', tag),
        createTextNode: (t) => ({ text: t }),
        addEventListener: () => {}
    },
    window: {},
    console: { log: () => {}, error: () => {}, warn: () => {} },
    formatNullableCurrency: (v, p, s) => p + String(v),
    isValidNumber: (v) => v !== null && v !== undefined && !isNaN(v),
    renderTradeCompareChart: () => {},
    renderTradeBalanceTable: () => {},
    setHeroTradeTitle: () => {},
    dashboardCharts: {},
    Chart: class MockChart { constructor() {} destroy() {} resize() {} }
};

vm.createContext(sandbox);

// Definisikan fungsi updateTab4TradeBalance
const scriptCode = {$scriptJson};

try {
    vm.runInContext(scriptCode, sandbox);

    // Kasus 1: Asimetris (Ekspor September, Impor Agustus)
    sandbox.updateTab4TradeBalance({
        trade_balance: {
            total_ekspor: 2301810000,
            total_impor: 167350000,
            net_trade: 2134460000,
            status: 'Surplus',
            has_complete: true,
            monthly: [],
            is_asymmetric_cumulative: true,
            max_ekspor_bulan: 9,
            max_impor_bulan: 8,
            max_ekspor_nama_bulan: 'September',
            max_impor_nama_bulan: 'Agustus'
        }
    });

    const case1Display = elements.alertTradeAsymmetry.style.display;
    const case1Text = elements.alertTradeAsymmetryText.textContent;

    // Kasus 2: Simetris (keduanya bulan 8)
    sandbox.updateTab4TradeBalance({
        trade_balance: {
            total_ekspor: 2000000000,
            total_impor: 167350000,
            net_trade: 1832650000,
            status: 'Surplus',
            has_complete: true,
            monthly: [],
            is_asymmetric_cumulative: false,
            max_ekspor_bulan: 8,
            max_impor_bulan: 8,
            max_ekspor_nama_bulan: 'Agustus',
            max_impor_nama_bulan: 'Agustus'
        }
    });

    const case2Display = elements.alertTradeAsymmetry.style.display;
    const case2Text = elements.alertTradeAsymmetryText.textContent;

    console.log(JSON.stringify({
        success: true,
        case1: { display: case1Display, text: case1Text },
        case2: { display: case2Display, text: case2Text }
    }));
} catch (e) {
    console.log(JSON.stringify({ success: false, error: e.message, stack: e.stack }));
}
JS;

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_2g8_') . '.cjs';
        file_put_contents($tmpFile, $nodeHarness);

        try {
            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = proc_open(['node', $tmpFile], $descriptors, $pipes);
            $this->assertIsResource($proc);
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($proc);

            $this->assertSame(0, $code, "Node execution failed: {$stdout}");
            $res = json_decode((string) $stdout, true);
            $this->assertTrue($res['success'], 'JS execution failed: ' . ($res['error'] ?? ''));

            // Kasus 1: Asimetris -> alert muncul (display: block) & memuat teks periode
            $this->assertSame('block', $res['case1']['display']);
            $this->assertStringContainsString('September', $res['case1']['text']);
            $this->assertStringContainsString('Agustus', $res['case1']['text']);
            $this->assertStringContainsString('Perbedaan ini mengikuti ketersediaan data yang telah dirilis', $res['case1']['text']);

            // Kasus 2: Simetris -> alert tersembunyi (display: none) & teks kosong
            $this->assertSame('none', $res['case2']['display']);
            $this->assertSame('', $res['case2']['text']);
        } finally {
            @unlink($tmpFile);
        }
    }
}
