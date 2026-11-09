@extends('layouts.app')

@section('title', 'Filtering Data | SIDATEX BPS Riau')

@push('styles')
<style>
    .filtering-wrapper {
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
    .filter-card {
        background: #ffffff;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.25rem;
    }
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.85rem;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .form-group label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
    }
    .form-group select, .form-group input {
        padding: 0.45rem 0.75rem;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        font-size: 0.85rem;
        color: #1e293b;
        background: #ffffff;
        outline: none;
    }
    .btn-apply {
        background: #0284c7;
        color: white;
        border: none;
        padding: 0.5rem 1.25rem;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .btn-apply:hover { background: #0369a1; }
    .btn-reset {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-reset:hover { background: #e2e8f0; }
    
    .summary-bar {
        background: #f0fdf4;
        border-left: 4px solid #16a34a;
        padding: 0.85rem 1.25rem;
        border-radius: 8px;
        margin-bottom: 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .table-container {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        padding: 1.5rem;
        overflow-x: auto;
    }
    .data-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 8px;
        text-align: left;
    }
    .data-table th {
        background: transparent;
        padding: 0 0.85rem 0.75rem 0.85rem;
        font-size: 0.8rem;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        border-bottom: 2px solid #e2e8f0;
        white-space: nowrap;
    }
    .data-table td {
        background: #f8fafc;
        padding: 0.85rem 1rem;
        font-size: 0.85rem;
        color: #1e293b;
        vertical-align: middle;
        white-space: nowrap;
        border-top: 1px solid #f1f5f9;
        border-bottom: 1px solid #f1f5f9;
    }
    .data-table td:first-child {
        border-top-left-radius: 8px;
        border-bottom-left-radius: 8px;
        border-left: 1px solid #f1f5f9;
    }
    .data-table td:last-child {
        border-top-right-radius: 8px;
        border-bottom-right-radius: 8px;
        border-right: 1px solid #f1f5f9;
    }
    .data-table tbody tr:hover td {
        background: #ffffff;
        box-shadow: 0 3px 10px rgba(0,0,0,0.04);
    }
    .badge-status {
        font-size: 0.75rem;
        padding: 0.2rem 0.55rem;
        border-radius: 4px;
        font-weight: 700;
    }
    .badge-rilis { background: #dcfce7; color: #15803d; }
    .badge-draft { background: #fef9c3; color: #a16207; }
</style>
@endpush

@section('content')
<div class="filtering-wrapper">
    <section class="page-heading">
        <span class="eyebrow">BPS PROVINSI RIAU</span>
        <h1>Filtering & Penelusuran Data</h1>
        <p>Eksplorasi transaksi ekspor dan impor berdasarkan komoditas, negara mitra, dan periode.</p>
    </section>

    <!-- Filter Card -->
    <div class="filter-card">
        <form id="filterForm">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Jenis Transaksi</label>
                    <select name="jenis_transaksi" id="filterJenis">
                        <option value="">Semua (Ekspor & Impor)</option>
                        <option value="Ekspor" selected>Ekspor</option>
                        <option value="Impor">Impor</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tahun</label>
                    <input type="number" name="tahun" id="filterTahun" value="{{ date('Y') }}" min="2000" max="2099">
                </div>

                <div class="form-group">
                    <label>Bulan</label>
                    <select name="bulan" id="filterBulan">
                        <option value="">Semua Bulan (1 - 12)</option>
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">Bulan {{ $m }}</option>
                        @endfor
                    </select>
                </div>

                <div class="form-group">
                    <label>Negara Mitra</label>
                    <select name="negara_id" id="filterNegara">
                        <option value="ALL">Semua Negara</option>
                        @foreach($negaras as $neg)
                            <option value="{{ $neg->negara_id }}">{{ $neg->kode_negara }} - {{ $neg->nama_negara }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Golongan HS (2 Digit)</label>
                    <select name="hs2" id="filterHs2">
                        <option value="ALL">Semua Golongan HS</option>
                        @foreach($hs_codes as $h)
                            <option value="{{ $h->hs2 }}">HS {{ $h->hs2 }} - {{ \Illuminate\Support\Str::limit($h->hs2_desc, 30) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Pelabuhan Muat/Bongkar</label>
                    <select name="pelabuhan_id" id="filterPelabuhan">
                        <option value="ALL">Semua Pelabuhan</option>
                        @foreach($pelabahans as $pel)
                            <option value="{{ $pel->pelabuhan_id }}">{{ $pel->nama_pelabuhan }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 1.25rem;">
                <button type="submit" class="btn-apply" id="btnFilterSubmit">
                    <span>🔍</span> Terapkan Filter
                </button>
                <button type="reset" class="btn-reset" id="btnFilterReset">Reset</button>
            </div>
        </form>
    </div>

    <!-- Summary Bar -->
    <div class="summary-bar" id="summaryBar">
        <div>
            <span style="color: #166534; font-weight: 700; font-size: 0.95rem;">Ringkasan Hasil Penelusuran</span>
            <div style="color: #4b5563; font-size: 0.85rem;" id="summaryText">Memuat ringkasan data...</div>
        </div>
        <div style="display: flex; gap: 1.5rem;">
            <div>
                <small style="color: #4b5563;">Total Nilai FOB</small>
                <div style="font-size: 1.15rem; font-weight: 800; color: #15803d;" id="sumFob">US$ 0,00</div>
            </div>
            <div>
                <small style="color: #4b5563;">Total Berat Netto</small>
                <div style="font-size: 1.15rem; font-weight: 800; color: #15803d;" id="sumNetto">0,00 Ton</div>
            </div>
        </div>
    </div>

    <!-- Data Table Container -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Periode</th>
                    <th>Jenis</th>
                    <th>Kode HS</th>
                    <th>Uraian Komoditas</th>
                    <th>Negara Tujuan/Asal</th>
                    <th>Pelabuhan</th>
                    <th>Berat Netto (Kg)</th>
                    <th>Nilai FOB (US$)</th>
                    <th>Status Batch</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <tr>
                    <td colspan="10" style="text-align: center; padding: 2rem; color: #64748b;">
                        Silakan tekan <strong>Terapkan Filter</strong> untuk menampilkan data transaksi.
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination Controls -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem;" id="paginationContainer">
            <span style="font-size: 0.85rem; color: #64748b;" id="pageInfo">Menampilkan 0 data</span>
            <div style="display: flex; gap: 0.5rem;" id="pageButtons"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('filterForm');
    let currentPage = 1;

    function loadData(page = 1) {
        currentPage = page;
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        params.set('page', page);

        document.getElementById('tableBody').innerHTML = `
            <tr><td colspan="10" style="text-align: center; padding: 2rem; color: #64748b;">Memuat data transaksi dari database...</td></tr>
        `;

        fetch(`{{ route('filtering-data.query') }}?${params.toString()}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success) {
                    alert('Gagal mengambil data');
                    return;
                }

                // Update Ringkasan
                document.getElementById('summaryText').textContent = `Ditemukan ${res.summary.total_rows.toLocaleString('id-ID')} transaksi`;
                document.getElementById('sumFob').textContent = `US$ ${res.summary.total_nilai_fob_juta.toLocaleString('id-ID')} Juta`;
                document.getElementById('sumNetto').textContent = `${res.summary.total_berat_ribu_ton.toLocaleString('id-ID')} Ribu Ton`;

                // Update Table
                const tbody = document.getElementById('tableBody');
                tbody.innerHTML = '';

                if (res.data.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="10" style="text-align: center; padding: 2rem; color: #64748b;">Tidak ada data yang sesuai dengan kriteria filter.</td></tr>`;
                    return;
                }

                let no = (res.data.current_page - 1) * res.data.per_page + 1;
                res.data.data.forEach(item => {
                    const tr = document.createElement('tr');
                    const badgeClass = item.batch_status === 'RILIS' ? 'badge-rilis' : 'badge-draft';
                    tr.innerHTML = `
                        <td>${no++}</td>
                        <td>${item.bulan}/${item.tahun}</td>
                        <td><strong>${item.jenis_transaksi}</strong></td>
                        <td><code>${item.hs_code || '—'}</code></td>
                        <td>${item.hs_desc || item.hs2_desc || '—'}</td>
                        <td>${item.nama_negara || '—'}</td>
                        <td>${item.nama_pelabuhan || '—'}</td>
                        <td>${Number(item.berat).toLocaleString('id-ID', {minimumFractionDigits: 2})}</td>
                        <td><strong>US$ ${Number(item.nilai).toLocaleString('id-ID', {minimumFractionDigits: 2})}</strong></td>
                        <td><span class="badge-status ${badgeClass}">${item.batch_status}</span></td>
                    `;
                    tbody.appendChild(tr);
                });

                // Update Pagination
                document.getElementById('pageInfo').textContent = `Halaman ${res.data.current_page} dari ${res.data.last_page} (Total: ${res.data.total.toLocaleString('id-ID')} data)`;
                const pageBtns = document.getElementById('pageButtons');
                pageBtns.innerHTML = '';

                if (res.data.prev_page_url) {
                    const btnPrev = document.createElement('button');
                    btnPrev.className = 'btn-reset';
                    btnPrev.style.padding = '0.3rem 0.8rem';
                    btnPrev.textContent = '« Prev';
                    btnPrev.onclick = () => loadData(currentPage - 1);
                    pageBtns.appendChild(btnPrev);
                }

                if (res.data.next_page_url) {
                    const btnNext = document.createElement('button');
                    btnNext.className = 'btn-reset';
                    btnNext.style.padding = '0.3rem 0.8rem';
                    btnNext.textContent = 'Next »';
                    btnNext.onclick = () => loadData(currentPage + 1);
                    pageBtns.appendChild(btnNext);
                }
            })
            .catch(err => {
                console.error(err);
                document.getElementById('tableBody').innerHTML = `<tr><td colspan="10" style="text-align: center; color: red; padding: 2rem;">Terjadi kesalahan saat memuat data.</td></tr>`;
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadData(1);
    });

    // Auto load data pertama kali
    loadData(1);
});
</script>
@endpush
