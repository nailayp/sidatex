@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <!-- Header Dashboard & Filter Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 text-gray-800 font-weight-bold mb-1">Dashboard Distribusi Ekspor & Impor</h1>
            <p class="text-muted small mb-0">Statistik resmi Berita Resmi Statistik (BRS) Provinsi Riau berdasarkan raw data POD & Kepabeanan.</p>
        </div>
        
        <!-- Filter Periode Dinamis -->
        <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white font-weight-bold text-dark">Periode:</span>
                <select id="filterBulan" class="form-select form-select-sm font-weight-bold">
                    <option value="1">Januari</option>
                    <option value="2">Februari</option>
                    <option value="3">Maret</option>
                    <option value="4">April</option>
                    <option value="5">Mei</option>
                    <option value="6">Juni</option>
                    <option value="7">Juli</option>
                    <option value="8">Agustus</option>
                    <option value="9" selected>September</option>
                    <option value="10">Oktober</option>
                    <option value="11">November</option>
                    <option value="12">Desember</option>
                </select>
                <select id="filterTahun" class="form-select form-select-sm font-weight-bold">
                    <option value="2026" selected>2026</option>
                    <option value="2025">2025</option>
                    <option value="2024">2024</option>
                </select>
                <button id="btnFilter" class="btn btn-primary btn-sm px-3 font-weight-bold">
                    <span class="spinner-border spinner-border-sm d-none mr-1" id="filterSpinner" role="status"></span>
                    Terapkan
                </button>
            </div>
            @if(Auth::user() && Auth::user()->role_id === 1)
            <a href="{{ route('upload-data') }}" class="btn btn-outline-success btn-sm font-weight-bold ml-2">
                + Upload DBF
            </a>
            @endif
        </div>
    </div>

    <!-- Alert Informasi Metodologi BPS -->
    <div class="alert alert-info py-2 px-3 small d-flex justify-content-between align-items-center mb-4">
        <div>
            <strong>ℹ️ Konsep Metodologis Distribusi BPS:</strong>
            Nilai Ekspor menggunakan <em>Free on Board (FOB)</em> pintu muat Riau (POD-based). Nilai Impor menggunakan <em>Cost, Insurance, and Freight (CIF)</em>. Selisih Nilai Ekspor FOB dan Impor CIF (Data Distribusi) dihitung sebagai selisih FOB Ekspor Pintu Riau dikurangi CIF Impor Pintu Riau (bukan data Neraca Perdagangan resmi BPS).
        </div>
        <span class="badge bg-primary text-white" id="badgePeriodeAktif">September 2026</span>
    </div>

    <!-- Banner Galat Inline (Non-Blocking) -->
    <div id="distribusiErrorAlert" role="alert" style="display: none; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.85rem; font-weight: 600;"></div>

    <!-- 1. KARTU KPI UTAMA (Slide 1 & Slide 10 BPS) -->
    <div class="row mb-4">
        <!-- Ekspor Bulanan -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow-sm h-100 py-2 border-0 bg-white">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Ekspor (FOB)</div>
                    <div class="h4 mb-1 font-weight-bold text-gray-800" id="cardEksporTotal">—</div>
                    <div class="small mt-2" id="cardEksporGrowth">
                        <span class="text-muted">M-to-M: </span><strong id="growthEksporMtoM" style="color: #64748b;">—</strong> | 
                        <span class="text-muted">Kumulatif: </span><strong id="cardEksporCum">—</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ekspor Nonmigas -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow-sm h-100 py-2 border-0 bg-white">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Ekspor Nonmigas</div>
                    <div class="h4 mb-1 font-weight-bold text-gray-800" id="cardEksporNonmigas">—</div>
                    <div class="small mt-2 text-muted">
                        Migas: <strong class="text-dark" id="cardEksporMigas">—</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Impor Bulanan -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow-sm h-100 py-2 border-0 bg-white">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Impor (CIF)</div>
                    <div class="h4 mb-1 font-weight-bold text-gray-800" id="cardImporTotal">—</div>
                    <div class="small mt-2" id="cardImporGrowth">
                        <span class="text-muted">M-to-M: </span><strong id="growthImporMtoM" style="color: #64748b;">—</strong> | 
                        <span class="text-muted">Kumulatif: </span><strong id="cardImporCum">—</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Selisih Ekspor-Impor Distribusi -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow-sm h-100 py-2 border-0 bg-white">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Selisih Ekspor FOB & Impor CIF (Distribusi)</div>
                    <div class="h4 mb-1 font-weight-bold" id="cardNeracaTotal" style="color: #64748b;">—</div>
                    <div class="small mt-2 text-muted">
                        Status: <strong id="cardNeracaStatus" style="color: #64748b;">—</strong> | 
                        Kum: <strong id="cardNeracaCum" style="color: #64748b;">—</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. GRAFIK & DISTRIBUSI SEKTOR & KOMODITAS (Slide 2 & Slide 3 BPS) -->
    <div class="row mb-4">
        <!-- Struktur Sektor Ekspor (Slide 2) -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-dark">Struktur Ekspor Menurut Sektor Ekonomi</h6>
                    <small class="text-muted">Slide 2 BRS</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered text-center small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-left">Sektor Ekonomi</th>
                                    <th>Nilai (Juta US$)</th>
                                    <th>Volume (Ribu Ton)</th>
                                    <th>Pangsa (%)</th>
                                </tr>
                            </thead>
                            <tbody id="tableSektorBody">
                                <tr><td colspan="4" class="text-muted py-3">Memuat data sektor...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top 3 Komoditas Nonmigas + Lainnya (Slide 3) -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-dark">Komoditas Ekspor Nonmigas Terbesar</h6>
                    <small class="text-muted">Slide 3 BRS</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered text-center small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode HS</th>
                                    <th class="text-left">Kelompok Komoditas</th>
                                    <th>Nilai (Juta US$)</th>
                                    <th>Pangsa (%)</th>
                                </tr>
                            </thead>
                            <tbody id="tableKomoditasBody">
                                <tr><td colspan="4" class="text-muted py-3">Memuat data komoditas...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. NEGARA TUJUAN & GOLONGAN BARANG IMPOR (Slide 4 & Slide 11 BPS) -->
    <div class="row mb-4">
        <!-- Top 10 Negara Tujuan Ekspor (Slide 4) -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-dark">10 Negara Tujuan Ekspor Nonmigas Utama</h6>
                    <small class="text-muted">Slide 4 BRS</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped text-center small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th class="text-left">Negara Tujuan</th>
                                    <th>Kode ISO</th>
                                    <th>Nilai (Juta US$)</th>
                                    <th>Pangsa (%)</th>
                                </tr>
                            </thead>
                            <tbody id="tableNegaraBody">
                                <tr><td colspan="5" class="text-muted py-3">Memuat data negara tujuan...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Klasifikasi Penggunaan Barang Impor BEC (Slide 11) -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-dark">Impor Menurut Golongan Penggunaan Barang (BEC)</h6>
                    <small class="text-muted">Slide 11 BRS</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered text-center small mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-left">Golongan Penggunaan Barang</th>
                                    <th>Nilai (Juta US$)</th>
                                    <th>Volume (Ribu Ton)</th>
                                    <th>Pangsa (%)</th>
                                </tr>
                            </thead>
                            <tbody id="tableBecBody">
                                <tr><td colspan="4" class="text-muted py-3">Memuat data penggunaan barang...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-light border small text-muted mb-0">
                        <strong>Metodologi BEC BPS:</strong> Barang Konsumsi, Bahan Baku/Penolong, dan Barang Modal diklasifikasikan langsung dari kode BEC pada Master HS 2022.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. DISTRIBUSI ASAL WILAYAH KOMODITAS (PROVORIG) -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="m-0 font-weight-bold text-primary">Distribusi Wilayah Asal Komoditas Ekspor (PROVORIG)</h6>
                        <small class="text-muted">Rincian kabupaten/kota tempat komoditas diproduksi sebelum diekspor melalui pelabuhan Riau.</small>
                    </div>
                    <div class="text-right">
                        <span class="badge bg-success text-white mr-2" id="badgeShareRiau">Riau: —</span>
                        <span class="badge bg-secondary text-white" id="badgeShareNonRiau">Luar Riau: —</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-striped text-center small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode Wilayah BPS</th>
                                    <th class="text-left">Kabupaten / Kota Asal</th>
                                    <th>Cakupan</th>
                                    <th>Nilai FOB (Juta US$)</th>
                                    <th>Volume (Ribu Ton)</th>
                                    <th>Pangsa terhadap Total FOB (%)</th>
                                </tr>
                            </thead>
                            <tbody id="tableOriginBody">
                                <tr><td colspan="6" class="text-muted py-3">Memuat data wilayah asal komoditas...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterBulan = document.getElementById('filterBulan');
    const filterTahun = document.getElementById('filterTahun');
    const btnFilter = document.getElementById('btnFilter');
    const filterSpinner = document.getElementById('filterSpinner');
    const errorAlert = document.getElementById('distribusiErrorAlert');

    function isValidNumber(val) {
        return val !== null && val !== undefined && val !== '' && Number.isFinite(Number(val));
    }

    function formatNumberId(val, decimals = 2) {
        if (!isValidNumber(val)) return '—';
        return Number(val).toLocaleString('id-ID', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function formatUsdJuta(val) {
        if (!isValidNumber(val)) return '—';
        const n = Number(val);
        const sign = n < 0 ? '-' : '';
        return `${sign}US$ ${formatNumberId(Math.abs(n), 2)} Juta`;
    }

    function formatPercentId(val, signed = false) {
        if (!isValidNumber(val)) return '—';
        const n = Number(val);
        const prefix = signed && n > 0 ? '+' : '';
        return `${prefix}${formatNumberId(n, 2)}%`;
    }

    function escapeHtml(val) {
        return String(val ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function applyGrowthDisplay(el, val) {
        if (!el) return;
        if (!isValidNumber(val)) {
            el.textContent = '—';
            el.className = 'text-muted';
            el.style.color = '#64748b';
            el.title = 'Periode pembanding belum tersedia';
            return;
        }
        const n = Number(val);
        el.textContent = formatPercentId(n, true);
        el.title = '';
        if (n > 0) {
            el.className = 'text-success';
            el.style.color = '#15803d';
        } else if (n < 0) {
            el.className = 'text-danger';
            el.style.color = '#dc2626';
        } else {
            el.className = 'text-secondary';
            el.style.color = '#475569';
        }
    }

    function applyBalanceColor(el, val, isComplete) {
        if (!el) return;
        if (!isComplete || !isValidNumber(val)) {
            el.style.color = '#64748b';
            return;
        }
        const n = Number(val);
        el.style.color = n > 0 ? '#15803d' : (n < 0 ? '#dc2626' : '#475569');
    }

    function applyStatusDisplay(el, statusText, isComplete) {
        if (!el) return;
        const label = statusText || 'Data Belum Tersedia';
        el.textContent = label;
        if (!isComplete) {
            el.className = 'text-warning';
            el.style.color = '#b45309';
        } else if (label === 'Surplus') {
            el.className = 'text-success';
            el.style.color = '#15803d';
        } else if (label === 'Defisit') {
            el.className = 'text-danger';
            el.style.color = '#dc2626';
        } else {
            el.className = 'text-secondary';
            el.style.color = '#475569';
        }
    }

    function showError(message) {
        if (!errorAlert) return;
        errorAlert.textContent = message || 'Terjadi kesalahan saat memuat data dashboard.';
        errorAlert.style.display = 'block';
    }

    function clearError() {
        if (!errorAlert) return;
        errorAlert.textContent = '';
        errorAlert.style.display = 'none';
    }

    function renderEmptyRow(tbody, colspan, message) {
        if (!tbody) return;
        tbody.innerHTML = `<tr><td colspan="${colspan}" class="text-muted py-3">${escapeHtml(message)}</td></tr>`;
    }

    function renderDistributionPayload(res, tahun, bulan) {
        const monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const monthLabel = monthNames[Number(bulan)] || String(bulan);
        document.getElementById('badgePeriodeAktif').textContent = `${monthLabel} ${tahun}`;

        // 1. KPI Ekspor
        const exp = res.ekspor || {};
        const expBulan = exp.bulan_ini || {};
        const expCum = exp.kumulatif || {};
        document.getElementById('cardEksporTotal').textContent = formatUsdJuta(expBulan.nilai_total_juta);
        document.getElementById('cardEksporNonmigas').textContent = formatUsdJuta(expBulan.nilai_nonmigas_juta);
        document.getElementById('cardEksporMigas').textContent = formatUsdJuta(expBulan.nilai_migas_juta);
        document.getElementById('cardEksporCum').textContent = formatUsdJuta(expCum.nilai_total_juta);
        applyGrowthDisplay(document.getElementById('growthEksporMtoM'), exp.pertumbuhan ? exp.pertumbuhan.mtom_total : null);

        // 2. KPI Impor
        const imp = res.impor || {};
        const impBulan = imp.bulan_ini || {};
        const impCum = imp.kumulatif || {};
        document.getElementById('cardImporTotal').textContent = formatUsdJuta(impBulan.nilai_total_juta);
        document.getElementById('cardImporCum').textContent = formatUsdJuta(impCum.nilai_total_juta);
        applyGrowthDisplay(document.getElementById('growthImporMtoM'), imp.pertumbuhan ? imp.pertumbuhan.mtom_total : null);

        // 3. KPI Neraca Distribusi (Null-Safe & Real-Zero Safe)
        const neraca = res.neraca || {};
        const hasCompleteBulanan = Boolean(neraca.has_complete_bulanan) && isValidNumber(neraca.neraca_bulanan_juta);
        const hasCompleteKumulatif = Boolean(neraca.has_complete_kumulatif) && isValidNumber(neraca.neraca_kumulatif_juta);

        const elNeracaTotal = document.getElementById('cardNeracaTotal');
        const elNeracaStatus = document.getElementById('cardNeracaStatus');
        const elNeracaCum = document.getElementById('cardNeracaCum');

        if (hasCompleteBulanan) {
            const valBulanan = Number(neraca.neraca_bulanan_juta);
            elNeracaTotal.textContent = formatUsdJuta(valBulanan);
            elNeracaTotal.title = '';
            applyBalanceColor(elNeracaTotal, valBulanan, true);
        } else {
            elNeracaTotal.textContent = '—';
            elNeracaTotal.title = neraca.status_bulanan || 'Data belum tersedia';
            applyBalanceColor(elNeracaTotal, null, false);
        }

        applyStatusDisplay(elNeracaStatus, neraca.status_bulanan, hasCompleteBulanan);

        if (hasCompleteKumulatif) {
            const valKum = Number(neraca.neraca_kumulatif_juta);
            const statusKumLabel = neraca.status_kumulatif || '—';
            elNeracaCum.textContent = `${formatUsdJuta(valKum)} (${statusKumLabel})`;
            applyBalanceColor(elNeracaCum, valKum, true);
        } else {
            const statusKumSuffix = neraca.status_kumulatif ? ` (${neraca.status_kumulatif})` : '';
            elNeracaCum.textContent = `—${statusKumSuffix}`;
            elNeracaCum.style.color = '#64748b';
        }

        // 4. Tabel Sektor (Slide 2)
        const tbodySektor = document.getElementById('tableSektorBody');
        const sektorItems = (res.sektor && Array.isArray(res.sektor.sektor)) ? res.sektor.sektor : [];
        tbodySektor.innerHTML = '';
        if (sektorItems.length === 0) {
            renderEmptyRow(tbodySektor, 4, 'Data struktur sektor belum tersedia untuk periode ini.');
        } else {
            sektorItems.forEach(s => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-left font-weight-bold">${escapeHtml(s.sektor)}</td>
                    <td class="text-right">${formatNumberId(s.nilai_juta_usd)}</td>
                    <td class="text-right">${formatNumberId(s.berat_ribu_ton)}</td>
                    <td class="text-right font-weight-bold">${formatPercentId(s.share_persen, false)}</td>
                `;
                tbodySektor.appendChild(tr);
            });
        }

        // 5. Tabel Top 3 Komoditas (Slide 3)
        const tbodyKom = document.getElementById('tableKomoditasBody');
        const komItems = (res.top_commodities && Array.isArray(res.top_commodities.items)) ? res.top_commodities.items : [];
        tbodyKom.innerHTML = '';
        if (komItems.length === 0) {
            renderEmptyRow(tbodyKom, 4, 'Data komoditas ekspor nonmigas belum tersedia untuk periode ini.');
        } else {
            komItems.forEach(k => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><code>${escapeHtml(k.hs2 || '—')}</code></td>
                    <td class="text-left font-weight-bold">${escapeHtml(k.nama_komoditas)}</td>
                    <td class="text-right">${formatNumberId(k.nilai_juta_usd)}</td>
                    <td class="text-right font-weight-bold">${formatPercentId(k.share_persen, false)}</td>
                `;
                tbodyKom.appendChild(tr);
            });
        }

        // 6. Tabel Top 10 Negara (Slide 4)
        const tbodyNeg = document.getElementById('tableNegaraBody');
        const negaraItems = (res.top_countries && Array.isArray(res.top_countries.countries)) ? res.top_countries.countries : [];
        tbodyNeg.innerHTML = '';
        if (negaraItems.length === 0) {
            renderEmptyRow(tbodyNeg, 5, 'Data negara tujuan ekspor belum tersedia untuk periode ini.');
        } else {
            negaraItems.forEach((n, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${idx + 1}</td>
                    <td class="text-left font-weight-bold">${escapeHtml(n.negara)}</td>
                    <td><code>${escapeHtml(n.kode_iso || '—')}</code></td>
                    <td class="text-right">${formatNumberId(n.nilai_juta_usd)}</td>
                    <td class="text-right font-weight-bold">${formatPercentId(n.share_persen, false)}</td>
                `;
                tbodyNeg.appendChild(tr);
            });
        }

        // 7. Tabel BEC Impor (Slide 11)
        const tbodyBec = document.getElementById('tableBecBody');
        const becItems = (res.bec_impor && Array.isArray(res.bec_impor.golongan_barang)) ? res.bec_impor.golongan_barang : [];
        tbodyBec.innerHTML = '';
        if (becItems.length === 0) {
            renderEmptyRow(tbodyBec, 4, 'Data penggunaan barang impor (BEC) belum tersedia untuk periode ini.');
        } else {
            becItems.forEach(b => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-left font-weight-bold">${escapeHtml(b.golongan)}</td>
                    <td class="text-right">${formatNumberId(b.nilai_juta_usd)}</td>
                    <td class="text-right">${formatNumberId(b.berat_ribu_ton)}</td>
                    <td class="text-right font-weight-bold">${formatPercentId(b.share_persen, false)}</td>
                `;
                tbodyBec.appendChild(tr);
            });
        }

        // 8. Tabel Asal Wilayah (PROVORIG)
        const orig = res.origin_wilayah || {};
        document.getElementById('badgeShareRiau').textContent = `Asal Riau: ${formatPercentId(orig.riau_share_persen, false)} (${formatUsdJuta(orig.riau_fob_juta_usd)})`;
        document.getElementById('badgeShareNonRiau').textContent = `Luar Riau: ${formatPercentId(orig.non_riau_share_persen, false)} (${formatUsdJuta(orig.non_riau_fob_juta_usd)})`;

        const tbodyOrig = document.getElementById('tableOriginBody');
        const origItems = Array.isArray(orig.daerah) ? orig.daerah : [];
        tbodyOrig.innerHTML = '';
        if (origItems.length === 0) {
            renderEmptyRow(tbodyOrig, 6, 'Data distribusi wilayah asal komoditas (PROVORIG) belum tersedia untuk periode ini.');
        } else {
            origItems.forEach(d => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><code>${escapeHtml(d.kode || '—')}</code></td>
                    <td class="text-left font-weight-bold">${escapeHtml(d.nama)}</td>
                    <td><span class="badge ${d.is_riau ? 'bg-success' : 'bg-warning'} text-white">${d.is_riau ? 'Provinsi Riau' : 'Luar Riau'}</span></td>
                    <td class="text-right">${formatNumberId(d.nilai_juta_usd)}</td>
                    <td class="text-right">${formatNumberId(d.berat_ribu_ton)}</td>
                    <td class="text-right font-weight-bold">${formatPercentId(d.share_persen, false)}</td>
                `;
                tbodyOrig.appendChild(tr);
            });
        }
    }

    function loadDashboardData(tahun, bulan) {
        btnFilter.disabled = true;
        filterSpinner.classList.remove('d-none');
        clearError();

        fetch(`{{ route('distribusi.metrics') }}?tahun=${encodeURIComponent(tahun)}&bulan=${encodeURIComponent(bulan)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async response => {
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload || !payload.success) {
                throw new Error((payload && payload.message) ? payload.message : 'Gagal memuat data metrik distribusi.');
            }
            return payload;
        })
        .then(res => {
            btnFilter.disabled = false;
            filterSpinner.classList.add('d-none');
            renderDistributionPayload(res, tahun, bulan);
        })
        .catch(err => {
            btnFilter.disabled = false;
            filterSpinner.classList.add('d-none');
            showError(err && err.message ? err.message : 'Terjadi kesalahan saat memuat data dashboard.');
            renderEmptyRow(document.getElementById('tableSektorBody'), 4, 'Data gagal dimuat.');
            renderEmptyRow(document.getElementById('tableKomoditasBody'), 4, 'Data gagal dimuat.');
            renderEmptyRow(document.getElementById('tableNegaraBody'), 5, 'Data gagal dimuat.');
            renderEmptyRow(document.getElementById('tableBecBody'), 4, 'Data gagal dimuat.');
            renderEmptyRow(document.getElementById('tableOriginBody'), 6, 'Data gagal dimuat.');
        });
    }

    // Event Filter
    btnFilter.addEventListener('click', function () {
        loadDashboardData(filterTahun.value, filterBulan.value);
    });

    // Inisialisasi awal September 2026
    loadDashboardData(filterTahun.value || 2026, filterBulan.value || 9);
});
</script>
@endpush
