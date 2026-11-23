<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2G.4 — Blade UI Alignment & JS Null-Safety Verification Tests.
 *
 * Mengeksekusi skrip JS aktual yang dirender dari `dashboard.blade.php` dan
 * `pages/distribusi-dashboard.blade.php` di dalam Node.js VM (strict read-only,
 * 0 mutasi DB) terhadap payload live sidatex_testing maupun skenario edge case.
 */
class Phase2gBladeAlignmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $activeDb = DB::connection()->getDatabaseName();
        $this->assertContains($activeDb, ['sidatex_testing', ':memory:'], "UNSAFE DATABASE: '{$activeDb}'");
    }

    private function extractLastInlineScript(string $html): string
    {
        preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $html, $matches);
        $inlineScripts = array_values(array_filter(
            $matches[1] ?? [],
            static fn (string $code): bool => trim($code) !== ''
        ));

        $this->assertNotEmpty($inlineScripts, 'Inline script Blade tidak ditemukan pada HTML hasil render.');

        return (string) end($inlineScripts);
    }

    /**
     * Menjalankan harness JS di Node.js dan mengembalikan output JSON terstruktur.
     *
     * @return array<string, mixed>
     */
    private function runNodeScript(string $harnessSource): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sidatex_2g4_') . '.cjs';
        file_put_contents($tmpFile, $harnessSource);

        try {
            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open(['node', $tmpFile], $descriptorSpec, $pipes);
            $this->assertIsResource($process, 'Gagal menjalankan Node.js untuk verifikasi runtime Blade JS.');

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            $this->assertSame(0, $exitCode, "Node.js harness gagal (exit {$exitCode}): {$stderr}\nSTDOUT: {$stdout}");
            $decoded = json_decode((string) $stdout, true);
            $this->assertIsArray($decoded, "Output JSON dari Node.js tidak valid: {$stdout}");

            return $decoded;
        } finally {
            @unlink($tmpFile);
        }
    }

    private function buildDistribusiJsHarness(string $bladeScript, array $fetchResponses): string
    {
        $scriptJson = json_encode($bladeScript, JSON_THROW_ON_ERROR);
        $responsesJson = json_encode($fetchResponses, JSON_THROW_ON_ERROR);

        return <<<JS
const vm = require('vm');

function makeElement(id, tag = 'div', initialValue = '') {
    const el = {
        id,
        tagName: tag.toUpperCase(),
        textContent: '',
        innerText: '',
        _innerHTML: '',
        value: initialValue,
        className: '',
        title: '',
        disabled: false,
        style: {},
        children: [],
        listeners: {},
        get innerHTML() {
            return this._innerHTML;
        },
        set innerHTML(v) {
            this._innerHTML = String(v ?? '');
            this.children = [];
        },
        classList: {
            add() {},
            remove() {},
            toggle() {}
        },
        appendChild(child) {
            this.children.push(child);
            return child;
        },
        addEventListener(evt, fn) {
            this.listeners[evt] = fn;
        }
    };
    return el;
}

const elements = {
    filterBulan: makeElement('filterBulan', 'select', '9'),
    filterTahun: makeElement('filterTahun', 'select', '2026'),
    btnFilter: makeElement('btnFilter', 'button'),
    filterSpinner: makeElement('filterSpinner', 'span'),
    distribusiErrorAlert: makeElement('distribusiErrorAlert', 'div'),
    badgePeriodeAktif: makeElement('badgePeriodeAktif', 'span'),
    cardEksporTotal: makeElement('cardEksporTotal', 'div'),
    cardEksporNonmigas: makeElement('cardEksporNonmigas', 'div'),
    cardEksporMigas: makeElement('cardEksporMigas', 'div'),
    cardEksporCum: makeElement('cardEksporCum', 'strong'),
    growthEksporMtoM: makeElement('growthEksporMtoM', 'strong'),
    cardImporTotal: makeElement('cardImporTotal', 'div'),
    cardImporCum: makeElement('cardImporCum', 'strong'),
    growthImporMtoM: makeElement('growthImporMtoM', 'strong'),
    cardNeracaTotal: makeElement('cardNeracaTotal', 'div'),
    cardNeracaStatus: makeElement('cardNeracaStatus', 'strong'),
    cardNeracaCum: makeElement('cardNeracaCum', 'strong'),
    badgeShareRiau: makeElement('badgeShareRiau', 'span'),
    badgeShareNonRiau: makeElement('badgeShareNonRiau', 'span'),
    tableSektorBody: makeElement('tableSektorBody', 'tbody'),
    tableKomoditasBody: makeElement('tableKomoditasBody', 'tbody'),
    tableNegaraBody: makeElement('tableNegaraBody', 'tbody'),
    tableBecBody: makeElement('tableBecBody', 'tbody'),
    tableOriginBody: makeElement('tableOriginBody', 'tbody'),
};

const queue = {$responsesJson};
let callIndex = 0;
let domReadyCallback = null;
const runtimeErrors = [];

const sandbox = {
    console: {
        error: (...args) => runtimeErrors.push(args.join(' ')),
        log: () => {}
    },
    alert: (msg) => runtimeErrors.push('ALERT:' + msg),
    encodeURIComponent,
    Number,
    String,
    Boolean,
    Array,
    Math,
    Object,
    Error,
    Promise,
    setTimeout,
    document: {
        addEventListener(evt, fn) {
            if (evt === 'DOMContentLoaded') domReadyCallback = fn;
        },
        getElementById(id) {
            return elements[id] || null;
        },
        createElement(tag) {
            return makeElement('', tag);
        },
        createTextNode(text) {
            return { nodeType: 3, textContent: String(text) };
        }
    },
    fetch: async () => {
        const entry = queue[callIndex++] || queue[queue.length - 1];
        return {
            ok: entry.ok !== false,
            status: entry.status || (entry.ok === false ? 500 : 200),
            json: async () => entry.payload
        };
    }
};

function snapshot() {
    const collectRows = (tbody) => tbody.children.length > 0
        ? tbody.children.map(r => r.innerHTML)
        : [tbody.innerHTML];

    return {
        errorAlert: { text: elements.distribusiErrorAlert.textContent, display: elements.distribusiErrorAlert.style.display || '' },
        badgePeriodeAktif: elements.badgePeriodeAktif.textContent,
        cardEksporTotal: elements.cardEksporTotal.textContent,
        cardImporTotal: elements.cardImporTotal.textContent,
        growthEksporMtoM: { text: elements.growthEksporMtoM.textContent, color: elements.growthEksporMtoM.style.color || '' },
        growthImporMtoM: { text: elements.growthImporMtoM.textContent, color: elements.growthImporMtoM.style.color || '' },
        cardNeracaTotal: { text: elements.cardNeracaTotal.textContent, color: elements.cardNeracaTotal.style.color || '' },
        cardNeracaStatus: { text: elements.cardNeracaStatus.textContent, color: elements.cardNeracaStatus.style.color || '' },
        cardNeracaCum: { text: elements.cardNeracaCum.textContent, color: elements.cardNeracaCum.style.color || '' },
        badgeShareRiau: elements.badgeShareRiau.textContent,
        badgeShareNonRiau: elements.badgeShareNonRiau.textContent,
        tables: {
            sektor: collectRows(elements.tableSektorBody),
            komoditas: collectRows(elements.tableKomoditasBody),
            negara: collectRows(elements.tableNegaraBody),
            bec: collectRows(elements.tableBecBody),
            origin: collectRows(elements.tableOriginBody),
        },
        runtimeErrors: [...runtimeErrors]
    };
}

(async () => {
    vm.runInNewContext({$scriptJson}, sandbox);
    const snapshots = [];

    const firstBefore = JSON.stringify(queue[0].payload);
    domReadyCallback();
    await new Promise(r => setTimeout(r, 30));
    snapshots.push({
        snapshot: snapshot(),
        payloadUnchanged: firstBefore === JSON.stringify(queue[0].payload)
    });

    for (let i = 1; i < queue.length; i++) {
        elements.filterTahun.value = String(queue[i].tahun || 2026);
        elements.filterBulan.value = String(queue[i].bulan || 9);
        if (queue[i].injectUndefinedAndNaN) {
            const p = queue[i].payload;
            p.ekspor.bulan_ini.nilai_total_juta = undefined;
            p.ekspor.pertumbuhan.mtom_total = NaN;
            p.impor.pertumbuhan.mtom_total = undefined;
            p.neraca.neraca_bulanan_juta = NaN;
            p.neraca.neraca_kumulatif_juta = undefined;
            p.neraca.has_complete_bulanan = true;
            p.neraca.has_complete_kumulatif = true;
            p.sektor.sektor = [{ sektor: 'Uji', nilai_juta_usd: undefined, berat_ribu_ton: NaN, share_persen: undefined }];
        }
        const beforeStr = queue[i].injectUndefinedAndNaN ? null : JSON.stringify(queue[i].payload);
        elements.btnFilter.listeners.click();
        await new Promise(r => setTimeout(r, 30));
        snapshots.push({
            snapshot: snapshot(),
            payloadUnchanged: beforeStr === null ? true : (beforeStr === JSON.stringify(queue[i].payload))
        });
    }

    process.stdout.write(JSON.stringify({ snapshots, runtimeErrors }));
})();
JS;
    }

    private function buildDashboardJsHarness(string $bladeScript, array $scenarios): string
    {
        $scriptJson = json_encode($bladeScript, JSON_THROW_ON_ERROR);
        $scenariosJson = json_encode($scenarios, JSON_THROW_ON_ERROR);

        return <<<JS
const vm = require('vm');

function makeElement(id, tag = 'div', initialValue = 'ALL') {
    const el = {
        id,
        tagName: tag.toUpperCase(),
        _text: '',
        innerText: '',
        _innerHTML: '',
        value: initialValue,
        className: '',
        title: '',
        style: {},
        children: [],
        get innerHTML() {
            return this._innerHTML;
        },
        set innerHTML(v) {
            this._innerHTML = String(v ?? '');
            this.children = [];
        },
        classList: {
            add() {},
            remove() {},
            toggle() {}
        },
        get textContent() {
            if (this.children.length > 0 && this._text === '') {
                return this.children.map(c => c.textContent || '').join('');
            }
            return this._text;
        },
        set textContent(v) {
            this._text = String(v ?? '');
            this.children = [];
        },
        appendChild(child) {
            this.children.push(child);
            return child;
        },
        getContext() {
            return {};
        }
    };
    return el;
}

const ids = [
    'dashboardErrorAlert', 'btnSumberDistribusi', 'btnSumberNeraca', 'btnJenisEkspor', 'btnJenisImpor', 'btnJenisNeraca',
    'methodologyNotice', 'methodologyNoticeText', 'btnTabTradeBalance',
    'filterTahun', 'filterBulan', 'filterTriwulan', 'filterMigas', 'filterKabkota', 'filterKomoditi', 'filterNegara', 'filterPelabuhan',
    'labelKpiTotalNilai', 'subtextKpiTotalNilai', 'labelKpiNegara', 'labelFilterNegara', 'labelFilterPelabuhan',
    'titleChartTrenNilaiBulan', 'titleChartMigasNilai', 'titleTableTsBulanNilai', 'titleTableTsTriwulanNilai',
    'labelDetailNilaiSektoral', 'titleChartKabkotaBar', 'titleTableNegaraDetail', 'titleTablePelabuhanDetail',
    'thNegaraNilai', 'thKomoditiNilai', 'thPelabuhanNilai',
    'p1KpiFob', 'p1KpiNetto', 'p1KpiAvg', 'p1KpiNegara', 'p1KpiKomoditi',
    'kpiMtom', 'kpiQtoq', 'kpiYony', 'kpiCtoc',
    'detailFobVal', 'detailNonMigasFob', 'detailMigasFob', 'detailNettoVal', 'detailNonMigasNetto', 'detailMigasNetto',
    'tbTotalEkspor', 'tbTotalImpor', 'badgeTradeStatus', 'heroTradeStatusTitle', 'tbNetTrade', 'tbNetSubtext',
    'chartTrenFobBulan', 'chartTrenVolBulan', 'chartTrenMigasFob', 'chartTrenMigasNetto', 'chartKabkotaBar', 'chartTradeBalanceCompare',
    'mapInteractive'
];

const elements = {};
ids.forEach(id => { elements[id] = makeElement(id); });
elements.filterTahun.value = '2026';

const tableBodies = {
    '#tableTsBulanFob tbody': makeElement('tb_ts_fob', 'tbody'),
    '#tableTsBulanNetto tbody': makeElement('tb_ts_netto', 'tbody'),
    '#tableTsTriwulanFob tbody': makeElement('tb_ts_tw', 'tbody'),
    '#tableNegaraDetail tbody': makeElement('tb_neg', 'tbody'),
    '#tableKomoditiDetail tbody': makeElement('tb_kom', 'tbody'),
    '#tablePelabuhanDetail tbody': makeElement('tb_pel', 'tbody'),
    '#tableGrowthMom tbody': makeElement('tb_mom', 'tbody'),
    '#tableGrowthQoq tbody': makeElement('tb_qoq', 'tbody'),
    '#tableTradeBalanceMonthly tbody': makeElement('tb_trade', 'tbody'),
};

const runtimeErrors = [];
const sandbox = {
    console: {
        error: (...args) => runtimeErrors.push(args.join(' ')),
        log: () => {}
    },
    URLSearchParams,
    Number,
    String,
    Boolean,
    Array,
    Math,
    Object,
    Error,
    Promise,
    Date,
    setTimeout,
    Chart: class MockChart {
        constructor() {}
        destroy() {}
        resize() {}
    },
    L: {
        map: () => ({ setView: () => ({ invalidateSize() {} }), invalidateSize() {} }),
        tileLayer: () => ({ addTo() {} }),
        layerGroup: () => ({ addTo: () => ({ clearLayers() {} }), clearLayers() {} }),
        circleMarker: () => ({ bindPopup() {}, addTo() {} })
    },
    document: {
        addEventListener() {},
        getElementById(id) { return elements[id] || null; },
        querySelector(sel) { return tableBodies[sel] || null; },
        querySelectorAll() { return []; },
        createElement(tag) { return makeElement('', tag); },
        createTextNode(text) { return { nodeType: 3, textContent: String(text) }; }
    },
    fetch: async () => ({ ok: true, json: async () => ({ success: true }) })
};

vm.runInNewContext({$scriptJson}, sandbox);

const scenarios = {$scenariosJson};
const results = [];

for (const sc of scenarios) {
    let payload = sc.payload;
    if (sc.injectUndefinedAndNaN) {
        payload = JSON.parse(JSON.stringify(payload));
        payload.summary.mtom = undefined;
        payload.summary.qtoq = NaN;
        payload.summary.yony = null;
        payload.summary.ctoc = undefined;
        payload.growth_tables.mom = [
            { periode: 'Januari', nilai: undefined, growth: undefined, kumulatif: NaN },
            { periode: 'Februari', nilai: 0, growth: NaN, kumulatif: 0 }
        ];
        payload.growth_tables.qoq = [
            { periode: 'Triwulan I', nilai: NaN, growth: undefined, kumulatif: undefined }
        ];
        payload.pivot_bulan_fob = [
            { komoditi: 'Komoditas Uji', values: [undefined, NaN, null, 0, 100], total: NaN, mom_growth: undefined }
        ];
        payload.trade_balance.net_trade = NaN;
        payload.trade_balance.has_complete = true;
        payload.trade_balance.monthly = [
            { bulan: 1, nama_bulan: 'Januari', ekspor: undefined, impor: NaN, neraca: undefined, status: 'Data Belum Tersedia', has_complete: false, kumulatif: NaN }
        ];
    }

    const beforeStr = sc.injectUndefinedAndNaN ? null : JSON.stringify(payload);
    sandbox.updateTab1Overview(payload);
    sandbox.updateTab2TimeSeries(payload);
    sandbox.updateTab3DetailMapping(payload);
    sandbox.updateTab4TradeBalance(payload);

    const collectRows = (tb) => tb.children.length > 0 ? tb.children.map(r => r.innerHTML) : [tb.innerHTML];

    results.push({
        name: sc.name,
        payloadUnchanged: beforeStr === null ? true : (beforeStr === JSON.stringify(payload)),
        kpis: {
            fob: elements.p1KpiFob.textContent,
            mtom: { text: elements.kpiMtom.textContent, color: elements.kpiMtom.style.color || '' },
            qtoq: { text: elements.kpiQtoq.textContent, color: elements.kpiQtoq.style.color || '' },
            yony: { text: elements.kpiYony.textContent, color: elements.kpiYony.style.color || '' },
            ctoc: { text: elements.kpiCtoc.textContent, color: elements.kpiCtoc.style.color || '' },
        },
        hero: {
            badgeText: elements.badgeTradeStatus.textContent,
            badgeColor: elements.badgeTradeStatus.style.color || '',
            badgeBg: elements.badgeTradeStatus.style.background || '',
            titleText: elements.heroTradeStatusTitle.textContent,
            netText: elements.tbNetTrade.textContent,
            netColor: elements.tbNetTrade.style.color || '',
            subtext: elements.tbNetSubtext.textContent,
            ekspor: elements.tbTotalEkspor.textContent,
            impor: elements.tbTotalImpor.textContent,
        },
        tables: {
            tradeBalance: collectRows(tableBodies['#tableTradeBalanceMonthly tbody']),
            mom: collectRows(tableBodies['#tableGrowthMom tbody']),
            qoq: collectRows(tableBodies['#tableGrowthQoq tbody']),
            pivotFob: collectRows(tableBodies['#tableTsBulanFob tbody']),
        }
    });
}

process.stdout.write(JSON.stringify({ results, runtimeErrors }));
JS;
    }

    /**
     * Uji 1–3: distribusi-dashboard.blade.php dengan payload LIVE 2026-09 (neraca_bulanan_juta: null)
     * dan 2026-08 (neraca_bulanan_juta: null & neraca_kumulatif_juta: null) — 0 crash & 5 tabel tetap terender.
     */
    public function test_distribusi_blade_handles_live_null_neraca_without_crash_and_renders_all_five_tables(): void
    {
        $pegawai = User::where('role_id', 2)->firstOrFail();

        $liveSep = $this->actingAs($pegawai)->getJson(route('distribusi.metrics', ['tahun' => 2026, 'bulan' => 9]))->assertStatus(200)->json();
        $liveAug = $this->actingAs($pegawai)->getJson(route('distribusi.metrics', ['tahun' => 2026, 'bulan' => 8]))->assertStatus(200)->json();

        $this->assertNull($liveSep['neraca']['neraca_bulanan_juta']);
        $this->assertNull($liveAug['neraca']['neraca_bulanan_juta']);
        $this->assertNull($liveAug['neraca']['neraca_kumulatif_juta']);

        $html = view('pages.distribusi-dashboard', [
            'availablePeriods' => ['years' => [2026], 'periods' => []],
            'selectedYear' => 2026,
            'selectedMonth' => 9,
        ])->render();
        $script = $this->extractLastInlineScript($html);

        $out = $this->runNodeScript($this->buildDistribusiJsHarness($script, [
            ['ok' => true, 'tahun' => 2026, 'bulan' => 9, 'payload' => $liveSep],
            ['ok' => true, 'tahun' => 2026, 'bulan' => 8, 'payload' => $liveAug],
        ]));

        $this->assertEmpty($out['runtimeErrors'], 'Tidak boleh ada runtime error atau alert() pada distribusi-dashboard!');

        // Snapshot 0: September 2026 (neraca_bulanan_juta: null, kumulatif: 2134.46 Surplus)
        $sep = $out['snapshots'][0]['snapshot'];
        $this->assertTrue($out['snapshots'][0]['payloadUnchanged'], 'Payload asli tidak boleh dimutasi.');
        $this->assertSame('—', $sep['cardNeracaTotal']['text']);
        $this->assertSame('#64748b', $sep['cardNeracaTotal']['color']);
        $this->assertSame('Data Impor Belum Tersedia', $sep['cardNeracaStatus']['text']);
        $this->assertSame('#b45309', $sep['cardNeracaStatus']['color'], 'Status belum tersedia tidak boleh berwarna merah Defisit.');
        $this->assertStringContainsString('2.134,46', $sep['cardNeracaCum']['text']);
        $this->assertStringContainsString('Surplus', $sep['cardNeracaCum']['text']);
        $this->assertSame('—', $sep['growthEksporMtoM']['text']);

        // Kelima tabel wajib terender penuh (bukan macet di "Memuat data...")
        $this->assertCount(3, $sep['tables']['sektor']);
        $this->assertCount(4, $sep['tables']['komoditas']);
        $this->assertCount(10, $sep['tables']['negara']);
        $this->assertStringContainsString('belum tersedia', $sep['tables']['bec'][0]);
        $this->assertCount(13, $sep['tables']['origin']);

        // Snapshot 1: Agustus 2026 (kedua neraca bulanan & kumulatif null)
        $aug = $out['snapshots'][1]['snapshot'];
        $this->assertTrue($out['snapshots'][1]['payloadUnchanged']);
        $this->assertSame('—', $aug['cardNeracaTotal']['text']);
        $this->assertSame('Data Ekspor Belum Tersedia', $aug['cardNeracaStatus']['text']);
        $this->assertSame('— (Data Ekspor Belum Tersedia)', $aug['cardNeracaCum']['text']);
        $this->assertStringNotContainsString('US$ 0', $aug['cardNeracaTotal']['text']);
        $this->assertStringNotContainsString('US$ 0', $aug['cardNeracaCum']['text']);
        $this->assertStringContainsString('belum tersedia', $aug['tables']['sektor'][0]);
        $this->assertNotEmpty($aug['tables']['komoditas']);
        $this->assertStringContainsString('belum tersedia', $aug['tables']['negara'][0]);
        $this->assertCount(3, $aug['tables']['bec']);
        $this->assertStringContainsString('belum tersedia', $aug['tables']['origin'][0]);
    }

    /**
     * Uji 4–7: distribusi-dashboard.blade.php — nilai 0 riil (Seimbang & 0,00%), Surplus (+),
     * Defisit (-), undefined/NaN -> '—', dan penanganan error HTTP 500 tersanitasi.
     */
    public function test_distribusi_blade_preserves_real_zero_formats_signed_values_and_guards_undefined_nan(): void
    {
        $html = view('pages.distribusi-dashboard', [
            'availablePeriods' => ['years' => [2026], 'periods' => []],
            'selectedYear' => 2026,
            'selectedMonth' => 9,
        ])->render();
        $script = $this->extractLastInlineScript($html);

        $makePayload = static fn (array $overrides): array => array_replace_recursive([
            'success' => true,
            'ekspor' => [
                'bulan_ini' => ['nilai_total_juta' => 100.0, 'nilai_nonmigas_juta' => 100.0, 'nilai_migas_juta' => 0.0],
                'kumulatif' => ['nilai_total_juta' => 100.0],
                'pertumbuhan' => ['mtom_total' => 0.0],
            ],
            'impor' => [
                'bulan_ini' => ['nilai_total_juta' => 100.0],
                'kumulatif' => ['nilai_total_juta' => 100.0],
                'pertumbuhan' => ['mtom_total' => 0.0],
            ],
            'neraca' => [
                'neraca_bulanan_juta' => 0.0,
                'status_bulanan' => 'Seimbang',
                'has_complete_bulanan' => true,
                'neraca_kumulatif_juta' => 0.0,
                'status_kumulatif' => 'Seimbang',
                'has_complete_kumulatif' => true,
            ],
            'sektor' => ['sektor' => [['sektor' => 'Industri', 'nilai_juta_usd' => 0.0, 'berat_ribu_ton' => 0.0, 'share_persen' => 0.0]]],
            'top_commodities' => ['items' => [['hs2' => '15', 'nama_komoditas' => 'CPO', 'nilai_juta_usd' => 100.0, 'share_persen' => 100.0]]],
            'top_countries' => ['countries' => [['negara' => 'INDIA', 'kode_iso' => 'IN', 'nilai_juta_usd' => 100.0, 'share_persen' => 100.0]]],
            'bec_impor' => ['golongan_barang' => [['golongan' => 'Bahan Baku', 'nilai_juta_usd' => 100.0, 'berat_ribu_ton' => 50.0, 'share_persen' => 100.0]]],
            'origin_wilayah' => [
                'riau_share_persen' => 100.0,
                'riau_fob_juta_usd' => 100.0,
                'non_riau_share_persen' => 0.0,
                'non_riau_fob_juta_usd' => 0.0,
                'daerah' => [['kode' => '1471', 'nama' => 'Pekanbaru', 'is_riau' => true, 'nilai_juta_usd' => 100.0, 'berat_ribu_ton' => 50.0, 'share_persen' => 100.0]],
            ],
        ], $overrides);

        $zeroPayload = $makePayload([]);
        $surplusPayload = $makePayload([
            'ekspor' => ['pertumbuhan' => ['mtom_total' => 12.5]],
            'neraca' => ['neraca_bulanan_juta' => 250.75, 'status_bulanan' => 'Surplus', 'neraca_kumulatif_juta' => 500.25, 'status_kumulatif' => 'Surplus'],
        ]);
        $defisitPayload = $makePayload([
            'impor' => ['pertumbuhan' => ['mtom_total' => -8.4]],
            'neraca' => ['neraca_bulanan_juta' => -45.25, 'status_bulanan' => 'Defisit', 'neraca_kumulatif_juta' => -90.5, 'status_kumulatif' => 'Defisit'],
        ]);
        $errorPayload = ['success' => false, 'message' => 'Terjadi kesalahan internal saat memuat metrik distribusi.'];

        $out = $this->runNodeScript($this->buildDistribusiJsHarness($script, [
            ['ok' => true, 'tahun' => 2026, 'bulan' => 1, 'payload' => $zeroPayload],
            ['ok' => true, 'tahun' => 2026, 'bulan' => 2, 'payload' => $surplusPayload],
            ['ok' => true, 'tahun' => 2026, 'bulan' => 3, 'payload' => $defisitPayload],
            ['ok' => true, 'tahun' => 2026, 'bulan' => 4, 'payload' => $zeroPayload, 'injectUndefinedAndNaN' => true],
            ['ok' => false, 'status' => 500, 'tahun' => 2026, 'bulan' => 5, 'payload' => $errorPayload],
        ]));

        $this->assertEmpty($out['runtimeErrors']);

        // 1. Real 0 -> US$ 0,00 Juta, Seimbang netral (#475569, bukan merah #dc2626!), growth 0,00%
        $sZero = $out['snapshots'][0]['snapshot'];
        $this->assertSame('US$ 0,00 Juta', $sZero['cardNeracaTotal']['text']);
        $this->assertSame('#475569', $sZero['cardNeracaTotal']['color']);
        $this->assertSame('Seimbang', $sZero['cardNeracaStatus']['text']);
        $this->assertSame('#475569', $sZero['cardNeracaStatus']['color']);
        $this->assertSame('US$ 0,00 Juta (Seimbang)', $sZero['cardNeracaCum']['text']);
        $this->assertSame('0,00%', $sZero['growthEksporMtoM']['text']);
        $this->assertSame('#475569', $sZero['growthEksporMtoM']['color']);
        $this->assertStringContainsString('0,00', $sZero['tables']['sektor'][0]);

        // 2. Surplus (+) -> hijau
        $sSur = $out['snapshots'][1]['snapshot'];
        $this->assertSame('US$ 250,75 Juta', $sSur['cardNeracaTotal']['text']);
        $this->assertSame('#15803d', $sSur['cardNeracaTotal']['color']);
        $this->assertSame('Surplus', $sSur['cardNeracaStatus']['text']);
        $this->assertSame('#15803d', $sSur['cardNeracaStatus']['color']);
        $this->assertSame('+12,50%', $sSur['growthEksporMtoM']['text']);
        $this->assertSame('#15803d', $sSur['growthEksporMtoM']['color']);

        // 3. Defisit (-) -> merah
        $sDef = $out['snapshots'][2]['snapshot'];
        $this->assertSame('-US$ 45,25 Juta', $sDef['cardNeracaTotal']['text']);
        $this->assertSame('#dc2626', $sDef['cardNeracaTotal']['color']);
        $this->assertSame('Defisit', $sDef['cardNeracaStatus']['text']);
        $this->assertSame('#dc2626', $sDef['cardNeracaStatus']['color']);
        $this->assertSame('-8,40%', $sDef['growthImporMtoM']['text']);
        $this->assertSame('#dc2626', $sDef['growthImporMtoM']['color']);

        // 4. undefined & NaN -> '—', ZERO 'NaN' atau 'undefined'
        $sStress = $out['snapshots'][3]['snapshot'];
        $stressJson = json_encode($sStress, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('NaN', $stressJson);
        $this->assertStringNotContainsString('undefined', $stressJson);
        $this->assertSame('—', $sStress['cardEksporTotal']);
        $this->assertSame('—', $sStress['growthEksporMtoM']['text']);
        $this->assertSame('—', $sStress['growthImporMtoM']['text']);
        $this->assertSame('—', $sStress['cardNeracaTotal']['text']);
        $this->assertStringStartsWith('—', $sStress['cardNeracaCum']['text']);

        // 5. HTTP 500 sanitized inline alert (tanpa alert() blocking)
        $sErr = $out['snapshots'][4]['snapshot'];
        $this->assertSame('block', $sErr['errorAlert']['display']);
        $this->assertSame('Terjadi kesalahan internal saat memuat metrik distribusi.', $sErr['errorAlert']['text']);
    }

    /**
     * Uji 4–8: dashboard.blade.php — live Role 2 null trade balance, live Role 1 full complete payload,
     * real 0 Seimbang (netral, bukan Defisit merah), Surplus/Defisit, dan guard undefined/NaN.
     */
    public function test_dashboard_blade_trade_balance_and_growth_alignment_across_live_and_edge_payloads(): void
    {
        $admin = User::where('role_id', 1)->firstOrFail();
        $pegawai = User::where('role_id', 2)->firstOrFail();

        $livePegawai = $this->actingAs($pegawai)->getJson(route('dashboard.data', [
            'tahun' => '2026',
            'jenis' => 'Neraca',
            'tipe_sumber' => 'distribusi',
        ]))->assertStatus(200)->json();

        $liveAdmin = $this->actingAs($admin)->getJson(route('dashboard.data', [
            'tahun' => '2026',
            'jenis' => 'Ekspor',
            'tipe_sumber' => 'distribusi',
        ]))->assertStatus(200)->json();

        $zeroBalanced = $liveAdmin;
        $zeroBalanced['summary']['mtom'] = 0.0;
        $zeroBalanced['summary']['qtoq'] = 0.0;
        $zeroBalanced['growth_tables']['mom'] = [
            ['periode' => 'Januari', 'nilai' => 1000.0, 'growth' => 0.0, 'kumulatif' => 1000.0],
        ];
        $zeroBalanced['trade_balance'] = [
            'total_ekspor' => 5000.0,
            'total_impor' => 5000.0,
            'net_trade' => 0.0,
            'status' => 'Seimbang',
            'has_complete' => true,
            'monthly' => [
                ['bulan' => 1, 'nama_bulan' => 'Januari', 'ekspor' => 5000.0, 'impor' => 5000.0, 'neraca' => 0.0, 'status' => 'Seimbang', 'has_complete' => true, 'kumulatif' => 0.0],
                ['bulan' => 2, 'nama_bulan' => 'Februari', 'ekspor' => 2000.0, 'impor' => 3500.0, 'neraca' => -1500.0, 'status' => 'Defisit', 'has_complete' => true, 'kumulatif' => -1500.0],
            ],
        ];

        $incompleteHero = $livePegawai;
        $incompleteHero['trade_balance']['has_complete'] = false;
        $incompleteHero['trade_balance']['net_trade'] = null;
        $incompleteHero['trade_balance']['status'] = 'Data Impor Belum Tersedia';

        $html = $this->actingAs($admin)->get(route('dashboard'))->assertStatus(200)->getContent();
        $script = $this->extractLastInlineScript($html);

        $out = $this->runNodeScript($this->buildDashboardJsHarness($script, [
            ['name' => 'live_pegawai_null_monthly', 'payload' => $livePegawai],
            ['name' => 'live_admin_full_complete', 'payload' => $liveAdmin],
            ['name' => 'real_zero_seimbang_and_defisit', 'payload' => $zeroBalanced],
            ['name' => 'incomplete_hero_null', 'payload' => $incompleteHero],
            ['name' => 'undefined_and_nan_stress', 'payload' => $liveAdmin, 'injectUndefinedAndNaN' => true],
        ]));

        $this->assertEmpty($out['runtimeErrors'], 'Tidak boleh ada runtime error pada dashboard.blade.php!');
        $byName = collect($out['results'])->keyBy('name');

        // 1. Live Pegawai: Agustus & September single-sided -> neraca & kumulatif '—', BUKAN '$ 0,00' & BUKAN 'Seimbang'
        $rPeg = $byName->get('live_pegawai_null_monthly');
        $this->assertTrue($rPeg['payloadUnchanged']);
        $this->assertSame('—', $rPeg['kpis']['mtom']['text']);
        $this->assertSame('—', $rPeg['kpis']['qtoq']['text']);
        $this->assertCount(2, $rPeg['tables']['tradeBalance']);
        foreach ($rPeg['tables']['tradeBalance'] as $rowHtml) {
            $this->assertStringContainsString('>—<', $rowHtml);
            $this->assertStringNotContainsString('Seimbang', $rowHtml, 'Bulan single-sided null tidak boleh berstatus Seimbang!');
            $this->assertStringNotContainsString('#dc2626', $rowHtml, 'Bulan single-sided null tidak boleh berwarna merah Defisit!');
            $this->assertStringContainsString('badge-unavailable', $rowHtml);
        }
        $this->assertStringContainsString('Data Ekspor Belum Tersedia', $rPeg['tables']['tradeBalance'][0]);
        $this->assertStringContainsString('Data Impor Belum Tersedia', $rPeg['tables']['tradeBalance'][1]);

        // 2. Live Admin Full Complete: seluruh 4 KPI growth & 3 bulan Surplus tampil normal
        $rAdm = $byName->get('live_admin_full_complete');
        $this->assertTrue($rAdm['payloadUnchanged']);
        $this->assertSame('+3,77%', $rAdm['kpis']['mtom']['text']);
        $this->assertSame('+22,58%', $rAdm['kpis']['qtoq']['text']);
        $this->assertSame('+45,04%', $rAdm['kpis']['yony']['text']);
        $this->assertSame('+6,96%', $rAdm['kpis']['ctoc']['text']);
        $this->assertSame('SURPLUS', $rAdm['hero']['badgeText']);
        $this->assertSame('#15803d', $rAdm['hero']['badgeColor']);
        $this->assertCount(3, $rAdm['tables']['tradeBalance']);
        $this->assertStringContainsString('Surplus', $rAdm['tables']['tradeBalance'][0]);

        // 3. Real 0 Seimbang + Defisit negatif
        $rZero = $byName->get('real_zero_seimbang_and_defisit');
        $this->assertTrue($rZero['payloadUnchanged']);
        $this->assertSame('0,00%', $rZero['kpis']['mtom']['text']);
        $this->assertSame('0,00%', $rZero['kpis']['qtoq']['text']);
        $this->assertSame('SEIMBANG', $rZero['hero']['badgeText']);
        $this->assertSame('#475569', $rZero['hero']['badgeColor'], 'Hero Seimbang (0) wajib netral, bukan merah Defisit!');
        $this->assertSame('US$ 0,00', $rZero['hero']['netText']);
        $this->assertStringNotContainsString('Defisit', $rZero['hero']['titleText']);
        $this->assertStringContainsString('$ 0,00', $rZero['tables']['tradeBalance'][0]);
        $this->assertStringContainsString('Seimbang', $rZero['tables']['tradeBalance'][0]);
        $this->assertStringContainsString('#475569', $rZero['tables']['tradeBalance'][0]);
        $this->assertStringNotContainsString('#dc2626', $rZero['tables']['tradeBalance'][0]);
        $this->assertStringContainsString('-$ 1.500,00', $rZero['tables']['tradeBalance'][1]);
        $this->assertStringContainsString('Defisit', $rZero['tables']['tradeBalance'][1]);
        $this->assertStringContainsString('#dc2626', $rZero['tables']['tradeBalance'][1]);
        $this->assertStringContainsString('0,00%', $rZero['tables']['mom'][0]);

        // 4. Incomplete Hero (net_trade: null) -> '—', bukan 'US$ 0,00'
        $rInc = $byName->get('incomplete_hero_null');
        $this->assertSame('—', $rInc['hero']['netText']);
        $this->assertSame('Data Impor Belum Tersedia', $rInc['hero']['badgeText']);
        $this->assertSame('#b45309', $rInc['hero']['badgeColor']);

        // 5. undefined & NaN -> '—', ZERO 'NaN' atau 'undefined' di seluruh output DOM!
        $rStress = $byName->get('undefined_and_nan_stress');
        $renderedOnly = json_encode([
            'kpis' => $rStress['kpis'],
            'hero' => $rStress['hero'],
            'tables' => $rStress['tables'],
        ], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('NaN', $renderedOnly);
        $this->assertStringNotContainsString('undefined', $renderedOnly);
        $this->assertSame('—', $rStress['kpis']['mtom']['text']);
        $this->assertSame('—', $rStress['kpis']['qtoq']['text']);
        $this->assertSame('—', $rStress['hero']['netText']);
    }

    /**
     * Uji 9: Placeholder HTML awal bersih (tanpa 0% / SURPLUS palsu) & 0 regresi pada halaman dashboard lain.
     */
    public function test_initial_blade_placeholders_and_other_dashboards_have_no_regression(): void
    {
        $admin = User::where('role_id', 1)->firstOrFail();

        $dashHtml = $this->actingAs($admin)->get(route('dashboard'))->assertStatus(200)->getContent();
        $this->assertStringNotContainsString('id="kpiMtom">0%<', $dashHtml);
        $this->assertStringNotContainsString('id="kpiQtoq">0%<', $dashHtml);
        $this->assertStringContainsString('id="dashboardErrorAlert"', $dashHtml);
        $this->assertStringContainsString('function isValidNumber(', $dashHtml);

        $distHtml = view('pages.distribusi-dashboard', [
            'availablePeriods' => ['years' => [2026], 'periods' => []],
            'selectedYear' => 2026,
            'selectedMonth' => 9,
        ])->render();
        $this->assertStringNotContainsString('id="cardNeracaStatus">Surplus<', $distHtml);
        $this->assertStringContainsString('id="distribusiErrorAlert"', $distHtml);
        $this->assertStringContainsString('function isValidNumber(', $distHtml);

        // Pastikan rute dashboard lain tetap sehat (HTTP 200)
        $this->actingAs($admin)->get('/distribusi/dashboard')->assertStatus(200);
        $this->actingAs($admin)->get('/neraca/dashboard')->assertStatus(200);
        $this->actingAs($admin)->get(route('analisis-perdagangan'))->assertStatus(200);
        $this->actingAs($admin)->get(route('upload-data'))->assertStatus(200);
    }
}
