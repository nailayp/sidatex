@extends('layouts.app')

@section('title', 'Penyaringan Data | SIDATEX BPS Riau')

@push('styles')
<style>
    .filtering-wrapper {
        min-height: calc(100vh - 120px);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }
    .page-heading {
        background-color: #ffffff;
        padding: 1.25rem 1.75rem;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        margin-bottom: 1.5rem;
        border: 1px solid #e2e8f0;
    }
    .page-heading h1 {
        font-size: 1.35rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0.2rem 0 0 0;
        border-left: 4px solid #0284c7;
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
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
    }
    .filter-grid-4x2 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem 1.25rem;
    }
    @media (max-width: 1024px) {
        .filter-grid-4x2 {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 640px) {
        .filter-grid-4x2 {
            grid-template-columns: 1fr;
        }
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        width: 100%;
    }
    .form-group label {
        font-size: 0.76rem;
        font-weight: 700;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        white-space: nowrap;
    }
    .form-group select, .form-group input {
        height: 38px;
        min-height: 38px;
        width: 100%;
        padding: 0 0.75rem;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        font-size: 0.85rem;
        color: #1e293b;
        background: #ffffff;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .form-group select:focus, .form-group input:focus {
        border-color: #0066cc;
        box-shadow: 0 0 0 2px rgba(0, 102, 204, 0.15);
    }
    .filter-action-row {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.75rem;
        margin-top: 1.25rem;
        padding-top: 1rem;
        border-top: 1px solid #f1f5f9;
    }
    .btn-apply {
        background: #0066cc;
        color: white;
        border: 1px solid #0052a3;
        padding: 0 1.5rem;
        height: 38px;
        min-height: 38px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.15s ease;
        box-shadow: 0 1px 2px rgba(0, 102, 204, 0.2);
    }
    .btn-apply:hover { background: #0052a3; }
    .btn-reset {
        background: #ffffff;
        color: #475569;
        border: 1px solid #cbd5e1;
        padding: 0 1.25rem;
        height: 38px;
        min-height: 38px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.15s ease;
    }
    .btn-reset:hover { background: #f1f5f9; border-color: #94a3b8; color: #0f172a; }

    .methodology-pills {
        display: inline-flex;
        background: #f1f5f9;
        padding: 3px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        gap: 3px;
    }
    .methodology-pill-btn {
        padding: 5px 12px;
        font-size: 0.8rem;
        font-weight: 600;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        background: transparent;
        color: #475569;
        transition: all 0.15s ease;
    }
    .methodology-pill-btn:hover:not(.active) {
        background: #e2e8f0;
        color: #1e293b;
    }
    .methodology-pill-btn.active {
        background: #0066cc;
        color: #ffffff;
        box-shadow: 0 1px 2px rgba(0, 102, 204, 0.25);
    }
    .btn-table-action {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #0066cc;
    }
    .btn-table-action:hover {
        background: #0066cc;
        color: #ffffff;
        border-color: #0066cc;
    }
    
    .summary-bar {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-left: 4px solid #16a34a;
        padding: 1rem 1.35rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
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
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        padding: 1.25rem 1.5rem 1.5rem;
        overflow-x: auto;
    }
    .data-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 6px;
        text-align: left;
    }
    .data-table th {
        background: #f8fafc;
        padding: 0.75rem 0.85rem;
        font-size: 0.78rem;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-top: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }
    .data-table td {
        background: #f8fafc;
        padding: 0.75rem 0.85rem;
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
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .badge-status {
        font-size: 0.74rem;
        padding: 0.2rem 0.55rem;
        border-radius: 9999px;
        font-weight: 700;
    }
    .badge-rilis { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
    .badge-draft { background: #fef9c3; color: #a16207; border: 1px solid #fde047; }
    .badge-private { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

    .btn-detail-link {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 4px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 0.75rem;
        font-weight: 600;
        border: 1px solid #bfdbfe;
        cursor: pointer;
        margin-left: 6px;
        vertical-align: middle;
        transition: all 0.15s;
    }
    .btn-detail-link:hover {
        background: #2563eb;
        color: #ffffff;
    }

    /* Modal Detail Komoditas */
    .commodity-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(4px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    .commodity-modal-card {
        background: #ffffff;
        border-radius: 14px;
        width: 90%;
        max-width: 520px;
        padding: 1.5rem 1.75rem;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
    }

    /* Numbered Pagination Buttons */
    .btn-page-num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 6px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
        border-radius: 6px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
    }
    .btn-page-num:hover:not(:disabled) {
        background: #f1f5f9;
        color: #0284c7;
    }
    .btn-page-num.active {
        background: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
    }
    .btn-page-num:disabled {
        color: #94a3b8;
        background: #f8fafc;
        cursor: not-allowed;
    }
</style>
@endpush

@section('content')
<div class="filtering-wrapper">
    <section class="page-heading">
        <span class="eyebrow">BPS PROVINSI RIAU</span>
        <h1>Filtering &amp; Penelusuran Data</h1>
        <p>Eksplorasi transaksi ekspor dan impor berdasarkan komoditas, negara mitra, pelabuhan, dan periode waktu.</p>
    </section>

    <!-- Filter Card -->
    <div class="filter-card">
        <form id="filterForm">
            <input type="hidden" name="tipe_sumber" id="filterTipeSumber" value="distribusi">

            <!-- Header Filter: Switcher Metodologi & Data per Halaman -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    <span style="font-size: 0.76rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">Metodologi Cakupan:</span>
                    <div class="methodology-pills" id="methodologyPills">
                        <button type="button" class="methodology-pill-btn active" data-tipe="distribusi" onclick="setFilterTipeSumber('distribusi')">
                            Distribusi (Pelabuhan Riau)
                        </button>
                        <button type="button" class="methodology-pill-btn" data-tipe="neraca" onclick="setFilterTipeSumber('neraca')">
                            Neraca (Asal Barang Riau)
                        </button>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <label for="filterPerPage" style="font-size: 0.76rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">Data per Halaman:</label>
                    <select name="per_page" id="filterPerPage" style="height: 36px; padding: 0 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 0.85rem; color: #1e293b; background: #ffffff;">
                        <option value="10" selected>10 data</option>
                        <option value="25">25 data</option>
                        <option value="50">50 data</option>
                        <option value="100">100 data</option>
                    </select>
                </div>
            </div>

            <!-- Filter Grid 4x2 Desktop -->
            <div class="filter-grid-4x2">
                <!-- Row 1: Tahun, Bulan, Triwulan, Kategori Transaksi -->
                <div class="form-group">
                    <label for="filterTahun">Tahun</label>
                    <input type="number" name="tahun" id="filterTahun" value="{{ date('Y') }}" min="2000" max="2099">
                </div>

                <div class="form-group">
                    <label for="filterBulan">Bulan</label>
                    <select name="bulan" id="filterBulan">
                        <option value="">Semua Bulan (1 - 12)</option>
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">Bulan {{ $m }}</option>
                        @endfor
                    </select>
                </div>

                <div class="form-group">
                    <label for="filterTriwulan">Triwulan</label>
                    <select name="triwulan" id="filterTriwulan">
                        <option value="">Semua Triwulan</option>
                        <option value="1">Triwulan I (Jan - Mar)</option>
                        <option value="2">Triwulan II (Apr - Jun)</option>
                        <option value="3">Triwulan III (Jul - Sep)</option>
                        <option value="4">Triwulan IV (Okt - Des)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filterJenis">Kategori Transaksi</label>
                    <select name="jenis_transaksi" id="filterJenis">
                        <option value="">Semua Kategori</option>
                        <option value="Ekspor" selected>Ekspor</option>
                        <option value="Impor">Impor</option>
                    </select>
                </div>

                <!-- Row 2: Kabupaten/Kota, Komoditas HS, Negara Mitra, Pelabuhan -->
                <div class="form-group">
                    <label for="filterKabkota">Kabupaten / Kota</label>
                    <select name="kabkota_id" id="filterKabkota">
                        <option value="ALL">Semua Kabupaten / Kota</option>
                        @foreach($kabkotas as $kab)
                            <option value="{{ $kab->kabkota_id }}">{{ $kab->nama_kabkota }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="filterHs2">Komoditas (Golongan HS)</label>
                    <select name="hs2" id="filterHs2">
                        <option value="ALL">Semua Golongan HS</option>
                        @foreach($hs_codes as $h)
                            <option value="{{ $h->hs2 }}">HS {{ $h->hs2 }} - {{ \Illuminate\Support\Str::limit($h->hs2_desc, 28) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="filterNegara">Negara Tujuan / Asal</label>
                    <select name="negara_id" id="filterNegara">
                        <option value="ALL">Semua Negara</option>
                        @foreach($negaras as $neg)
                            <option value="{{ $neg->negara_id }}">{{ $neg->kode_negara }} - {{ $neg->nama_negara }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="filterPelabuhan">Pelabuhan Muat / Bongkar</label>
                    <select name="pelabuhan_id" id="filterPelabuhan">
                        <option value="ALL">Semua Pelabuhan</option>
                        @foreach($pelabahans as $pel)
                            <option value="{{ $pel->pelabuhan_id }}">{{ $pel->nama_pelabuhan }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="filter-action-row">
                <button type="button" class="btn-reset" id="btnFilterReset" onclick="resetFilterForm()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                    Reset Filter
                </button>
                <button type="submit" class="btn-apply" id="btnFilterSubmit">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Terapkan Filter
                </button>
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
                <small style="color: #4b5563; font-weight: 600;">Total Nilai</small>
                <div style="font-size: 1.15rem; font-weight: 800; color: #15803d;" id="sumFob">US$ 0,00</div>
            </div>
            <div>
                <small style="color: #4b5563; font-weight: 600;">Total Berat Netto</small>
                <div style="font-size: 1.15rem; font-weight: 800; color: #15803d;" id="sumNetto">0,00 Ton</div>
            </div>
        </div>
    </div>

    <!-- Data Table Container -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 45px; text-align: center;">No</th>
                    <th>Periode</th>
                    <th>Jenis</th>
                    <th>Kode HS</th>
                    <th style="max-width: 250px;">Uraian Komoditas</th>
                    <th>Negara Mitra</th>
                    <th>Pelabuhan</th>
                    <th>Berat Netto (Kg)</th>
                    <th>Nilai (US$)</th>
                    <th>Status Batch</th>
                    <th style="width: 90px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <tr>
                    <td colspan="11" style="text-align: center; padding: 2rem; color: #64748b;">
                        Silakan tekan <strong>Terapkan Filter</strong> untuk menampilkan data transaksi.
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Numbered Pagination Controls SiPantau Style -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; flex-wrap: wrap; gap: 1rem;" id="paginationContainer">
            <span style="font-size: 0.82rem; color: #64748b;" id="pageInfo">Menampilkan 0 data</span>
            <div style="display: flex; gap: 0.35rem; align-items: center;" id="pageButtons"></div>
        </div>
    </div>
</div>

<!-- Modal Detail Metadata Transaksi Lengkap -->
<div id="modalCommodityDetail" class="commodity-modal-overlay">
    <div class="commodity-modal-card" style="max-width: 620px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.85rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0066cc" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Detail Metadata Transaksi
                </h3>
                <span id="detailHsCodeBadge" style="display: inline-block; font-size: 0.8rem; font-weight: 700; color: #0066cc; background: #e0f2fe; padding: 2px 8px; border-radius: 4px; margin-top: 4px;"></span>
            </div>
            <button type="button" onclick="closeCommodityDetail()" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #94a3b8; line-height: 1;" title="Tutup Modal">&times;</button>
        </div>

        <!-- Grid Metadata Transaksi -->
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; margin-bottom: 1rem;">
            <div style="background: #f8fafc; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Periode &amp; Jenis:</label>
                <div style="font-size: 0.88rem; font-weight: 700; color: #0f172a;" id="detailPeriodeJenis">—</div>
            </div>
            <div style="background: #f8fafc; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Status Batch:</label>
                <div style="font-size: 0.88rem; font-weight: 700;" id="detailStatus">—</div>
            </div>
            <div style="background: #f8fafc; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Negara Mitra:</label>
                <div style="font-size: 0.88rem; font-weight: 600; color: #1e293b;" id="detailNegara">—</div>
            </div>
            <div style="background: #f8fafc; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Pelabuhan:</label>
                <div style="font-size: 0.88rem; font-weight: 600; color: #1e293b;" id="detailPelabuhan">—</div>
            </div>
            <div style="background: #f8fafc; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Berat Netto:</label>
                <div style="font-size: 0.88rem; font-weight: 700; color: #0284c7;" id="detailBerat">—</div>
            </div>
            <div style="background: #f8fafc; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Nilai Perdagangan:</label>
                <div style="font-size: 0.88rem; font-weight: 800; color: #15803d;" id="detailNilai">—</div>
            </div>
        </div>

        <div style="background: #f8fafc; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 1rem;">
            <label style="display: block; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Batch Sumber:</label>
            <div style="font-size: 0.84rem; color: #334155;" id="detailBatch">—</div>
        </div>

        <div style="margin-bottom: 0.85rem;">
            <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 0.25rem;">Golongan HS 2-Digit:</label>
            <div id="detailHsChapter" style="font-size: 0.85rem; font-weight: 600; color: #1e293b; background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #e2e8f0;"></div>
        </div>

        <div style="margin-bottom: 1.25rem;">
            <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 0.25rem;">Uraian Lengkap Master BPS:</label>
            <div id="detailHsFullDesc" style="font-size: 0.88rem; color: #334155; background: #f8fafc; padding: 0.75rem; border-radius: 8px; border: 1px solid #e2e8f0; line-height: 1.5; max-height: 160px; overflow-y: auto;"></div>
        </div>

        <div style="display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeCommodityDetail()" style="padding: 0.5rem 1.25rem; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">Tutup</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('filterForm');
    let currentPage = 1;
    let currentTransactions = [];

    window.showTransactionDetail = function(index) {
        const item = currentTransactions[index];
        if (!item) return;

        document.getElementById('detailHsCodeBadge').textContent = 'Kode HS: ' + (item.hs_code || '—');
        document.getElementById('detailPeriodeJenis').textContent = `${item.bulan}/${item.tahun} • ${item.jenis_transaksi || '—'}`;

        const badgeClass = (item.batch_status === 'RILIS')
            ? 'badge-rilis'
            : (item.batch_status === 'PRIVATE' ? 'badge-private' : 'badge-draft');
        document.getElementById('detailStatus').innerHTML = `<span class="badge-status ${badgeClass}">${item.batch_status}</span>`;

        document.getElementById('detailNegara').textContent = item.nama_negara || '—';
        document.getElementById('detailPelabuhan').textContent = item.nama_pelabuhan || '—';
        document.getElementById('detailBerat').textContent = `${Number(item.berat).toLocaleString('id-ID', {minimumFractionDigits: 2})} Kg`;
        document.getElementById('detailNilai').textContent = `US$ ${Number(item.nilai).toLocaleString('id-ID', {minimumFractionDigits: 2})}`;
        document.getElementById('detailBatch').textContent = `Batch #${item.batch_id || '—'} (${item.nama_file || '—'})`;
        document.getElementById('detailHsChapter').textContent = item.hs2_desc || '—';
        document.getElementById('detailHsFullDesc').textContent = item.hs_desc || item.hs2_desc || 'Tidak ada deskripsi rinci master BPS.';

        document.getElementById('modalCommodityDetail').style.display = 'flex';
    };

    window.closeCommodityDetail = function() {
        document.getElementById('modalCommodityDetail').style.display = 'none';
    };

    function loadData(page = 1) {
        currentPage = page;
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);
        params.set('page', page);

        document.getElementById('tableBody').innerHTML = `
            <tr><td colspan="11" style="text-align: center; padding: 2rem; color: #64748b;">Memuat data transaksi dari database...</td></tr>
        `;

        fetch(`{{ route('filtering-data.query') }}?${params.toString()}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success) {
                    alert('Gagal mengambil data: ' + (res.message || ''));
                    return;
                }

                // Update Ringkasan
                document.getElementById('summaryText').textContent = `Ditemukan ${res.summary.total_rows.toLocaleString('id-ID')} transaksi (${res.summary.tipe_sumber === 'neraca' ? 'Metodologi Neraca' : 'Metodologi Distribusi'})`;
                document.getElementById('sumFob').textContent = `US$ ${res.summary.total_nilai_fob_juta.toLocaleString('id-ID')} Juta`;
                document.getElementById('sumNetto').textContent = `${res.summary.total_berat_ribu_ton.toLocaleString('id-ID')} Ribu Ton`;

                // Update Table
                const tbody = document.getElementById('tableBody');
                tbody.innerHTML = '';

                if (!res.data.data || res.data.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="11" style="text-align: center; padding: 2.5rem; color: #64748b;">Tidak ada data transaksi yang sesuai dengan kriteria filter.</td></tr>`;
                    document.getElementById('pageInfo').textContent = 'Menampilkan 0 data';
                    document.getElementById('pageButtons').innerHTML = '';
                    currentTransactions = [];
                    return;
                }

                currentTransactions = res.data.data;
                let no = (res.data.current_page - 1) * res.data.per_page + 1;
                res.data.data.forEach((item, idx) => {
                    const tr = document.createElement('tr');
                    const badgeClass = (item.batch_status === 'RILIS')
                        ? 'badge-rilis'
                        : (item.batch_status === 'PRIVATE' ? 'badge-private' : 'badge-draft');

                    const desc = item.hs_desc || item.hs2_desc || '—';

                    tr.innerHTML = `
                        <td style="text-align: center; color: #64748b; font-weight: 600;">${no++}</td>
                        <td>${item.bulan}/${item.tahun}</td>
                        <td><strong>${item.jenis_transaksi}</strong></td>
                        <td><code>${item.hs_code || '—'}</code></td>
                        <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeAttr(desc)}">
                            ${escapeHtml(desc)}
                        </td>
                        <td>${item.nama_negara || '—'}</td>
                        <td>${item.nama_pelabuhan || '—'}</td>
                        <td>${Number(item.berat).toLocaleString('id-ID', {minimumFractionDigits: 2})}</td>
                        <td><strong>US$ ${Number(item.nilai).toLocaleString('id-ID', {minimumFractionDigits: 2})}</strong></td>
                        <td><span class="badge-status ${badgeClass}">${item.batch_status}</span></td>
                        <td style="text-align: center;">
                            <button type="button" class="btn-table-action btn-action-view" onclick="showTransactionDetail(${idx})" title="Lihat detail metadata lengkap transaksi">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: -1px; margin-right: 2px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                Detail
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });

                // Update Numbered Pagination SiPantau Style
                const fromItem = (res.data.current_page - 1) * res.data.per_page + 1;
                const toItem = Math.min(res.data.current_page * res.data.per_page, res.data.total);
                document.getElementById('pageInfo').innerHTML = `Menampilkan data <strong>${fromItem}</strong> &ndash; <strong>${toItem}</strong> dari total <strong>${res.data.total.toLocaleString('id-ID')}</strong> data`;

                renderNumberedPagination(res.data.current_page, res.data.last_page);
            })
            .catch(err => {
                console.error(err);
                document.getElementById('tableBody').innerHTML = `<tr><td colspan="11" style="text-align: center; color: red; padding: 2rem;">Terjadi kesalahan saat memuat data.</td></tr>`;
            });
    }

    function renderNumberedPagination(current, last) {
        const pageBtns = document.getElementById('pageButtons');
        pageBtns.innerHTML = '';
        if (last <= 1) return;

        // Tombol Prev
        const btnPrev = document.createElement('button');
        btnPrev.className = 'btn-page-num';
        btnPrev.innerHTML = `&laquo; Sebelumnya`;
        btnPrev.disabled = (current <= 1);
        if (current > 1) {
            btnPrev.onclick = () => loadData(current - 1);
        }
        pageBtns.appendChild(btnPrev);

        // Algoritma nomor halaman (misal: 1 ... 4 5 6 ... 10)
        let delta = 2;
        let range = [];
        for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
            range.push(i);
        }

        // Halaman 1
        addPageBtn(1, current, pageBtns);

        if (range.length > 0 && range[0] > 2) {
            const dots = document.createElement('span');
            dots.textContent = '...';
            dots.style.padding = '0 4px';
            dots.style.color = '#94a3b8';
            pageBtns.appendChild(dots);
        }

        range.forEach(p => {
            addPageBtn(p, current, pageBtns);
        });

        if (range.length > 0 && range[range.length - 1] < last - 1) {
            const dots = document.createElement('span');
            dots.textContent = '...';
            dots.style.padding = '0 4px';
            dots.style.color = '#94a3b8';
            pageBtns.appendChild(dots);
        }

        // Halaman Terakhir (jika last > 1)
        if (last > 1) {
            addPageBtn(last, current, pageBtns);
        }

        // Tombol Next
        const btnNext = document.createElement('button');
        btnNext.className = 'btn-page-num';
        btnNext.innerHTML = `Selanjutnya &raquo;`;
        btnNext.disabled = (current >= last);
        if (current < last) {
            btnNext.onclick = () => loadData(current + 1);
        }
        pageBtns.appendChild(btnNext);
    }

    function addPageBtn(p, current, container) {
        const btn = document.createElement('button');
        btn.className = 'btn-page-num' + (p === current ? ' active' : '');
        btn.textContent = p;
        btn.onclick = () => loadData(p);
        container.appendChild(btn);
    }

    function escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function escapeAttr(text) {
        return String(text).replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    window.setFilterTipeSumber = function(tipe) {
        document.getElementById('filterTipeSumber').value = tipe;
        document.querySelectorAll('.methodology-pill-btn').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-tipe') === tipe);
        });
        loadData(1);
    };

    window.resetFilterForm = function() {
        form.reset();
        document.getElementById('filterTipeSumber').value = 'distribusi';
        document.querySelectorAll('.methodology-pill-btn').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-tipe') === 'distribusi');
        });
        document.getElementById('filterTahun').value = '{{ date("Y") }}';
        document.getElementById('filterBulan').value = '';
        document.getElementById('filterTriwulan').value = '';
        document.getElementById('filterJenis').value = 'Ekspor';
        document.getElementById('filterKabkota').value = 'ALL';
        document.getElementById('filterHs2').value = 'ALL';
        document.getElementById('filterNegara').value = 'ALL';
        document.getElementById('filterPelabuhan').value = 'ALL';
        document.getElementById('filterPerPage').value = '10';
        loadData(1);
    };

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadData(1);
    });

    document.getElementById('filterPerPage').addEventListener('change', function() {
        loadData(1);
    });

    // Auto load data pertama kali
    loadData(1);
});
</script>
@endpush
