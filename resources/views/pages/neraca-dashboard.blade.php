@extends('layouts.app')

@section('title', 'Dashboard Neraca Perdagangan | SIDATEX BPS Provinsi Riau')

@push('styles')
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    .neraca-wrapper {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        padding: 0.5rem;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }
    .neraca-header-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .neraca-title h1 {
        font-size: 1.5rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }
    .neraca-title p {
        font-size: 0.85rem;
        color: #64748b;
        margin: 0.25rem 0 0 0;
    }
    .badge-neraca-sumber {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.25rem 0.6rem;
        border-radius: 6px;
    }
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
    }
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        position: relative;
        overflow: hidden;
    }
    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
    }
    .kpi-card.ekspor::before { background: #0284c7; }
    .kpi-card.impor::before { background: #e11d48; }
    .kpi-card.surplus::before { background: #16a34a; }
    .kpi-card.defisit::before { background: #dc2626; }
    .kpi-card.nodata::before { background: #94a3b8; }
    .kpi-label {
        font-size: 0.8rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }
    .kpi-value {
        font-size: 1.6rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
    }
    .kpi-subtext {
        font-size: 0.8rem;
        color: #64748b;
        margin-top: 0.4rem;
    }
    .content-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
        gap: 1.25rem;
    }
    .card-panel {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    }
    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .panel-title {
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }
</style>
@endpush

@section('content')
<div class="neraca-wrapper">
    <!-- Header -->
    <div class="neraca-header-card">
        <div class="neraca-title">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <h1>Dashboard Neraca Perdagangan</h1>
                <span class="badge-neraca-sumber">Sumber: BRS Makro / Neraca</span>
            </div>
            <p>Statistik analisis makro neraca perdagangan luar negeri (Ekspor, Impor, dan Surplus/Defisit) BPS Provinsi Riau.</p>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <label for="selectTahun" style="font-size: 0.85rem; font-weight: 700; color: #475569;">Tahun Periode:</label>
            <select id="selectTahun" style="padding: 0.4rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 700; font-size: 0.9rem; background: #ffffff;">
                @forelse($availableYears as $yr)
                    <option value="{{ $yr }}" {{ $yr == $selectedYear ? 'selected' : '' }}>{{ $yr }}</option>
                @empty
                    <option value="2026">2026</option>
                @endforelse
            </select>
        </div>
    </div>

    <!-- Alert Metodologis -->
    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 0.75rem 1rem; font-size: 0.85rem; color: #1e40af;">
        <strong>ℹ️ Karakteristik Sumber Data Neraca:</strong>
        Menampilkan data olahan kompilasi Berita Resmi Statistik (BRS) Neraca Perdagangan Provinsi Riau. Klasifikasi dikelompokkan menurut Sektor Makro dan Broad Economic Categories (BEC).
    </div>

    <!-- KPI Ringkasan Utama -->
    <div class="kpi-grid">
        <div class="kpi-card ekspor">
            <div class="kpi-label">Total Ekspor (FOB)</div>
            <div class="kpi-value" id="kpiEkspor">US$ 0,00</div>
            <div class="kpi-subtext" id="kpiEksporSub">0,00 Juta USD</div>
        </div>
        <div class="kpi-card impor">
            <div class="kpi-label">Total Impor (CIF)</div>
            <div class="kpi-value" id="kpiImpor">US$ 0,00</div>
            <div class="kpi-subtext" id="kpiImporSub">0,00 Juta USD</div>
        </div>
        <div class="kpi-card surplus" id="cardNeraca">
            <div class="kpi-label">Neraca Perdagangan</div>
            <div class="kpi-value" id="kpiNeraca">US$ 0,00</div>
            <div class="kpi-subtext" id="kpiNeracaSub">Surplus / Defisit</div>
        </div>
    </div>

    <!-- Grafik Bulanan Neraca Perdagangan -->
    <div class="card-panel">
        <div class="panel-header">
            <h2 class="panel-title">Perkembangan Ekspor, Impor, dan Neraca Bulanan</h2>
            <span style="font-size: 0.8rem; color: #64748b;" id="chartSubtitle">Januari - Desember</span>
        </div>
        <div style="height: 320px; position: relative;">
            <canvas id="chartNeracaBulanan"></canvas>
        </div>
    </div>

    <!-- Panel Sektor & BEC -->
    <div class="content-grid">
        <div class="card-panel">
            <div class="panel-header">
                <h2 class="panel-title">Distribusi Ekspor Menurut Sektor</h2>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;" id="tableSektor">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left;">
                            <th style="padding: 0.6rem;">Sektor</th>
                            <th style="padding: 0.6rem; text-align: right;">Total FOB (USD)</th>
                            <th style="padding: 0.6rem; text-align: right;">Total Netto (kg)</th>
                        </tr>
                    </thead>
                    <tbody id="tbodySektor">
                        <tr><td colspan="3" style="padding: 1rem; text-align: center; color: #94a3b8;">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-panel">
            <div class="panel-header">
                <h2 class="panel-title">Struktur Ekspor Menurut Kategori BEC</h2>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;" id="tableBec">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left;">
                            <th style="padding: 0.6rem;">Kategori BEC</th>
                            <th style="padding: 0.6rem; text-align: right;">Total FOB (USD)</th>
                            <th style="padding: 0.6rem; text-align: right;">Volume (kg)</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyBec">
                        <tr><td colspan="3" style="padding: 1rem; text-align: center; color: #94a3b8;">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let chartInstance = null;

    function formatNumber(num, decimals = 2) {
        if (!num || isNaN(num)) return '0,00';
        return parseFloat(num).toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    function loadNeracaData(tahun) {
        fetch(`/neraca/metrics?tahun=${tahun}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success) {
                    alert('Gagal mengambil data neraca: ' + (res.message || 'Terjadi kesalahan'));
                    return;
                }

                const s = res.summary;
                const hasData = Boolean(res.has_data);
                const hasComplete = Boolean(res.has_complete_trade_balance);

                document.getElementById('kpiEkspor').innerText = res.has_ekspor ? ('US$ ' + formatNumber(s.total_ekspor_fob)) : 'US$ 0,00';
                document.getElementById('kpiEksporSub').innerText = res.has_ekspor ? (formatNumber(s.total_ekspor_fob_juta) + ' Juta USD') : 'Data Belum Tersedia';
                
                document.getElementById('kpiImpor').innerText = res.has_impor ? ('US$ ' + formatNumber(s.total_impor_cif)) : 'US$ 0,00';
                document.getElementById('kpiImporSub').innerText = res.has_impor ? (formatNumber(s.total_impor_cif_juta) + ' Juta USD') : 'Data Belum Tersedia';
                
                const cardNeraca = document.getElementById('cardNeraca');
                if (!hasComplete || s.neraca_perdagangan === null) {
                    document.getElementById('kpiNeraca').innerText = '-';
                    document.getElementById('kpiNeracaSub').innerText = s.status_neraca;
                    cardNeraca.className = 'kpi-card nodata';
                } else {
                    document.getElementById('kpiNeraca').innerText = (s.neraca_perdagangan >= 0 ? '+' : '') + 'US$ ' + formatNumber(s.neraca_perdagangan);
                    document.getElementById('kpiNeracaSub').innerText = s.status_neraca + ' (' + formatNumber(s.neraca_juta) + ' Juta USD)';
                    if (s.status_neraca === 'Surplus') {
                        cardNeraca.className = 'kpi-card surplus';
                    } else if (s.status_neraca === 'Defisit') {
                        cardNeraca.className = 'kpi-card defisit';
                    } else {
                        cardNeraca.className = 'kpi-card nodata';
                    }
                }

                // Render Chart Bulanan
                renderChart(res.monthly);

                // Render Table Sektor
                const tbodySektor = document.getElementById('tbodySektor');
                if (res.sektor && res.sektor.length > 0) {
                    tbodySektor.innerHTML = res.sektor.map(sk => `
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.6rem; font-weight: 600;">${sk.sektor_nama}</td>
                            <td style="padding: 0.6rem; text-align: right;">US$ ${formatNumber(sk.total_fob)}</td>
                            <td style="padding: 0.6rem; text-align: right;">${formatNumber(sk.total_netto, 3)} kg</td>
                        </tr>
                    `).join('');
                } else {
                    tbodySektor.innerHTML = '<tr><td colspan="3" style="padding: 1rem; text-align: center; color: #94a3b8;">Data sektor tidak tersedia untuk tahun ini.</td></tr>';
                }

                // Render Table BEC
                const tbodyBec = document.getElementById('tbodyBec');
                if (res.bec && res.bec.length > 0) {
                    tbodyBec.innerHTML = res.bec.map(bc => `
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.6rem; font-weight: 600;">${bc.kategori_bec}</td>
                            <td style="padding: 0.6rem; text-align: right;">US$ ${formatNumber(bc.total_fob)}</td>
                            <td style="padding: 0.6rem; text-align: right;">${formatNumber(bc.total_netto, 3)} kg</td>
                        </tr>
                    `).join('');
                } else {
                    tbodyBec.innerHTML = '<tr><td colspan="3" style="padding: 1rem; text-align: center; color: #94a3b8;">Data klasifikasi BEC tidak tersedia untuk tahun ini.</td></tr>';
                }
            })
            .catch(err => {
                console.error(err);
            });
    }

    function renderChart(monthlyData) {
        const ctx = document.getElementById('chartNeracaBulanan').getContext('2d');
        if (chartInstance) {
            chartInstance.destroy();
        }

        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthlyData.labels,
                datasets: [
                    {
                        label: 'Ekspor FOB (USD)',
                        data: monthlyData.ekspor,
                        backgroundColor: '#0284c7',
                        borderRadius: 4,
                        order: 2
                    },
                    {
                        label: 'Impor CIF (USD)',
                        data: monthlyData.impor,
                        backgroundColor: '#e11d48',
                        borderRadius: 4,
                        order: 2
                    },
                    {
                        label: 'Neraca (Surplus/Defisit)',
                        data: monthlyData.neraca,
                        type: 'line',
                        borderColor: '#16a34a',
                        backgroundColor: '#16a34a',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.1,
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + (value / 1000000).toLocaleString('id-ID') + ' Jt';
                            }
                        }
                    }
                },
                plugins: {
                    legend: { position: 'top' }
                }
            }
        });
    }

    // Inisialisasi awal
    const selectTahun = document.getElementById('selectTahun');
    loadNeracaData(selectTahun.value);

    selectTahun.addEventListener('change', function() {
        loadNeracaData(this.value);
    });
});
</script>
@endpush
@endsection
