@extends('layouts.app')

@section('title', 'Analisis Perdagangan | SIDATEX BPS Riau')

@push('styles')
<style>
    .analisis-wrapper {
        min-height: calc(100vh - 80px);
        padding: 1.25rem 1.5rem;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }
    .page-heading {
        background-color: #ffffff;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 1.25rem;
        border: 1px solid #e2e8f0;
    }
    .page-heading h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0.2rem 0 0 0;
        border-left: 3px solid #0284c7;
        padding-left: 1rem;
    }
    .eyebrow {
        color: #0284c7;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding-left: 1rem;
    }
    .page-heading p {
        margin: 0.25rem 0 0 1rem;
        font-size: 0.85rem;
        color: #64748b;
    }
    .filter-bar {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        background: #ffffff;
        padding: 0.85rem 1.25rem;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
        margin-bottom: 1.25rem;
        flex-wrap: wrap;
    }
    .filter-select {
        padding: 0.4rem 0.85rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.85rem;
        color: #1e293b;
        background: #ffffff;
        outline: none;
    }
    .btn-filter {
        background: #0284c7;
        color: white;
        border: none;
        padding: 0.45rem 1.2rem;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-filter:hover { background: #0369a1; }
    
    .grid-2 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
        gap: 1.25rem;
        margin-bottom: 1.25rem;
    }
    .chart-card {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        padding: 1.25rem 1.5rem;
    }
    .chart-card h2 {
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 0.2rem;
    }
    .chart-card p {
        font-size: 0.8rem;
        color: #64748b;
        margin: 0 0 1rem;
    }
    .item-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .item-row {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        background: #f8fafc;
        padding: 0.6rem 0.85rem;
        border-radius: 8px;
        border: 1px solid #f1f5f9;
        transition: background 0.15s ease;
    }
    .item-row:hover {
        background: #ffffff;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    }
    .item-header {
        display: flex;
        justify-content: space-between;
        font-size: 0.82rem;
    }
    .item-title { font-weight: 600; color: #1e293b; }
    .item-val { font-weight: 700; color: #0284c7; }
    .progress-bar-bg {
        width: 100%;
        height: 6px;
        background: #e2e8f0;
        border-radius: 3px;
        overflow: hidden;
    }
    .progress-bar-fill {
        height: 100%;
        background: #0284c7;
        border-radius: 3px;
    }
</style>
@endpush

@section('content')
    <section class="page-heading">
        <span class="eyebrow">BPS PROVINSI RIAU</span>
        <h1>Analisis Perdagangan Luar Negeri</h1>
        <p>Struktur komoditas unggulan, negara tujuan/asal utama, dan kinerja pelabuhan Provinsi Riau.</p>
    </section>

    <!-- Controls -->
    <div class="filter-bar">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Tahun:</label>
            <select id="analisisTahun" class="filter-select">
                @if(empty($availableYears))
                    <option value="{{ date('Y') }}">{{ date('Y') }}</option>
                @else
                    @foreach($availableYears as $y)
                        <option value="{{ $y }}" {{ ($selectedYear == $y) ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                @endif
            </select>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Jenis:</label>
            <select id="analisisJenis" class="filter-select">
                <option value="Ekspor" {{ ($selectedJenis == 'Ekspor') ? 'selected' : '' }}>Ekspor</option>
                <option value="Impor" {{ ($selectedJenis == 'Impor') ? 'selected' : '' }}>Impor</option>
            </select>
        </div>

        <button type="button" id="btnRefreshAnalisis" class="btn-filter">Perbarui Analisis</button>
    </div>

    <div class="grid-2">
        <!-- Top Komoditas -->
        <div class="chart-card">
            <h2>Top 10 Komoditas Unggulan</h2>
            <p>Berdasarkan Golongan HS 2 Digit dan Pangsa Nilai FOB</p>
            <div class="item-list" id="listKomoditas">
                <div style="color: #64748b; font-size: 0.85rem;">Memuat data komoditas...</div>
            </div>
        </div>

        <!-- Top Negara -->
        <div class="chart-card">
            <h2>Top 10 Negara Mitra Dagang</h2>
            <p>Negara tujuan/asal utama perdagangan luar negeri Riau</p>
            <div class="item-list" id="listNegara">
                <div style="color: #64748b; font-size: 0.85rem;">Memuat data negara...</div>
            </div>
        </div>
    </div>

    <!-- Pelabuhan -->
    <div class="chart-card" style="margin-bottom: 2rem;">
        <h2>Peran Pelabuhan Muat / Bongkar</h2>
        <p>Kontribusi arus logistik perdagangan ekspor-impor Provinsi Riau</p>
        <div class="item-list" id="listPelabuhan">
            <div style="color: #64748b; font-size: 0.85rem;">Memuat data pelabuhan...</div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selTahun = document.getElementById('analisisTahun');
    const selJenis = document.getElementById('analisisJenis');
    const btnRefresh = document.getElementById('btnRefreshAnalisis');

    function loadAnalisis() {
        const tahun = selTahun.value;
        const jenis = selJenis.value;

        fetch(`{{ route('analisis-perdagangan.data') }}?tahun=${tahun}&jenis=${jenis}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success) return;

                // Render Komoditas
                const listKom = document.getElementById('listKomoditas');
                listKom.innerHTML = '';
                if (res.top_komoditas.length === 0) {
                    listKom.innerHTML = '<div style="color: #64748b; font-size: 0.85rem;">Tidak ada transaksi berstatus RILIS untuk periode ini.</div>';
                } else {
                    res.top_komoditas.forEach(item => {
                        listKom.innerHTML += `
                            <div class="item-row">
                                <div class="item-header">
                                    <span class="item-title">HS ${item.hs2} - ${item.komoditas}</span>
                                    <span class="item-val">US$ ${item.total_fob_juta.toLocaleString('id-ID')} Jt (${item.share_persen}%)</span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: ${item.share_persen}%;"></div>
                                </div>
                            </div>
                        `;
                    });
                }

                // Render Negara
                const listNeg = document.getElementById('listNegara');
                listNeg.innerHTML = '';
                if (res.top_negara.length === 0) {
                    listNeg.innerHTML = '<div style="color: #64748b; font-size: 0.85rem;">Tidak ada transaksi berstatus RILIS untuk periode ini.</div>';
                } else {
                    res.top_negara.forEach(item => {
                        listNeg.innerHTML += `
                            <div class="item-row">
                                <div class="item-header">
                                    <span class="item-title">${item.kode_negara} - ${item.negara}</span>
                                    <span class="item-val">US$ ${item.total_fob_juta.toLocaleString('id-ID')} Jt (${item.share_persen}%)</span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: ${item.share_persen}%; background: #16a34a;"></div>
                                </div>
                            </div>
                        `;
                    });
                }

                // Render Pelabuhan
                const listPel = document.getElementById('listPelabuhan');
                listPel.innerHTML = '';
                if (res.top_pelabuhan.length === 0) {
                    listPel.innerHTML = '<div style="color: #64748b; font-size: 0.85rem;">Tidak ada transaksi berstatus RILIS untuk periode ini.</div>';
                } else {
                    res.top_pelabuhan.forEach(item => {
                        listPel.innerHTML += `
                            <div class="item-row">
                                <div class="item-header">
                                    <span class="item-title">${item.pelabuhan}</span>
                                    <span class="item-val">US$ ${item.total_fob_juta.toLocaleString('id-ID')} Jt (${item.share_persen}%)</span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: ${item.share_persen}%; background: #ea580c;"></div>
                                </div>
                            </div>
                        `;
                    });
                }
            })
            .catch(err => {
                console.error(err);
            });
    }

    btnRefresh.addEventListener('click', loadAnalisis);
    loadAnalisis();
});
</script>
@endpush
