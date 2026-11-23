@extends('layouts.app')

@section('title', 'Dashboard Statistik Perdagangan Luar Negeri | SIDATEX BPS Provinsi Riau')

@push('styles')
<!-- SheetJS untuk Fitur Ekspor Excel Langsung di Seluruh Tabel Tabulasi -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<!-- Chart.js & Leaflet Maps -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    /* Kontainer Utama Dashboard SIDATEX */
    .dashboard-wrapper {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        padding: 0.25rem 0.5rem;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        color: #1e293b;
    }

    /* Header & Navigasi Terpadu */
    .dashboard-header-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .dashboard-title-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.85rem;
    }

    .dashboard-title-group h1 {
        font-size: 1.45rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        letter-spacing: -0.02em;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .dashboard-title-group p {
        font-size: 0.85rem;
        color: #64748b;
        margin: 0.25rem 0 0 0;
    }

    /* Segmented Control Pill Switchers (2 Clean Rows BPS Standard) */
    .segmented-switchers-row {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        padding: 0.85rem 1.15rem;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .switcher-row {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        flex-wrap: wrap;
    }

    .switcher-label-wrapper {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        min-width: 110px;
    }

    .switcher-label {
        font-size: 0.78rem;
        font-weight: 800;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .segmented-pills {
        display: inline-flex;
        background: #f1f5f9;
        padding: 3px;
        border-radius: 8px;
        gap: 3px;
        flex-wrap: wrap;
    }

    .segmented-pill-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid transparent;
        background: transparent;
        padding: 0.4rem 0.9rem;
        min-height: 34px;
        font-size: 0.8rem;
        font-weight: 700;
        color: #475569;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
    }

    .segmented-pill-btn:hover {
        color: #0f172a;
        background: rgba(255, 255, 255, 0.6);
    }

    .segmented-pill-btn.active {
        background: #0066cc;
        color: #ffffff;
        border-color: #0052a3;
        box-shadow: 0 1px 3px rgba(0, 102, 204, 0.25);
    }

    .segmented-pill-btn.active-neraca {
        background: #ea580c;
        color: #ffffff;
        border-color: #c2410c;
        box-shadow: 0 1px 3px rgba(234, 88, 12, 0.25);
    }

    /* Methodology Info Tooltip / Popover (F-05) */
    .methodology-tooltip-container {
        position: relative;
        display: inline-flex;
        align-items: center;
    }

    .methodology-info-btn {
        background: transparent;
        border: 1px solid #cbd5e1;
        color: #64748b;
        border-radius: 50%;
        width: 1.35rem;
        height: 1.35rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0;
        transition: all 0.15s ease;
    }

    .methodology-info-btn:hover,
    .methodology-info-btn:focus {
        background: #f1f5f9;
        color: #0f172a;
        border-color: #94a3b8;
        outline: none;
    }

    .methodology-tooltip-popover {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        z-index: 1000;
        width: 320px;
        max-width: calc(100vw - 2.5rem);
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 0.85rem 1rem;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15), 0 8px 10px -6px rgba(15, 23, 42, 0.1);
        font-size: 0.77rem;
        color: #334155;
        text-align: left;
    }

    /* Dynamic Methodology Notice Box */
    .methodology-notice-box {
        font-size: 0.78rem;
        color: #334155;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        padding: 0.45rem 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        line-height: 1.35;
    }

    .methodology-notice-box.neraca-mode {
        background: #fff7ed;
        border-color: #fed7aa;
        color: #9a3412;
    }

    /* 4 TAB NAVIGASI HORIZONTAL */
    .dashboard-nav-tabs {
        display: flex;
        gap: 0.5rem;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 0;
        margin-top: 0.25rem;
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
        -webkit-overflow-scrolling: touch;
    }

    .dashboard-nav-tabs::-webkit-scrollbar {
        display: none;
    }

    @media (min-width: 1025px) {
        .dashboard-nav-tabs {
            overflow-x: visible;
            flex-wrap: wrap;
        }
    }

    .nav-tab-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.25rem;
        font-size: 0.9rem;
        font-weight: 700;
        color: #64748b;
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .nav-tab-button:hover {
        color: #0369a1;
        background: #f8fafc;
        border-radius: 6px 6px 0 0;
    }

    .nav-tab-button.active {
        color: #0f172a;
        border-bottom-color: #0284c7;
        background: #f0f9ff;
        border-radius: 8px 8px 0 0;
    }

    .nav-tab-button .tab-icon {
        font-size: 1.05rem;
    }

    /* AREA KONTROL FILTER HORIZONTAL RINGKAS */
    .filter-bar-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem 1.25rem;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
    }

    .filter-bar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .filter-bar-title {
        font-size: 0.8rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #0f172a;
        letter-spacing: 0.05em;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 0.75rem;
        align-items: flex-end;
    }

    .filter-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .filter-item label {
        font-size: 0.72rem;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .filter-item select {
        width: 100%;
        padding: 0.5rem 0.65rem;
        font-size: 0.82rem;
        font-weight: 600;
        color: #1e293b;
        background: #f8fafc;
        border: 1.5px solid #cbd5e1;
        border-radius: 8px;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .filter-item select:focus {
        border-color: #0284c7;
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        background: #ffffff;
    }

    .btn-reset-filter {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        padding: 0.5rem 1rem;
        font-size: 0.82rem;
        font-weight: 700;
        color: #dc2626;
        background: #fef2f2;
        border: 1.5px solid #fecaca;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s;
        height: 38px;
    }

    .btn-reset-filter:hover {
        background: #fee2e2;
        border-color: #f87171;
    }

    /* KONTEN TABEL & GRAFIK (Tab Sections) */
    .dashboard-page-section {
        display: none;
        flex-direction: column;
        gap: 1.25rem;
        animation: fadeIn 0.2s ease-in-out;
    }

    .dashboard-page-section.active {
        display: flex;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* KARTU KPI MODERN */
    .kpi-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
    }

    .kpi-grid-5 {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 1rem;
    }

    .kpi-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.25rem;
    }

    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.1rem 1.25rem;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-width: 0;
        transition: transform 0.15s, box-shadow 0.15s;
    }

    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
    }

    .kpi-label {
        font-size: 0.72rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .kpi-value {
        font-size: 1.45rem;
        font-weight: 800;
        color: #0f172a;
        margin-top: 0.35rem;
        line-height: 1.15;
    }

    .kpi-subtext {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 0.35rem;
    }

    /* CARD GRAFIK & TABEL */
    .section-title-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 0.25rem;
        padding-bottom: 0.4rem;
        border-bottom: 2px solid #0284c7;
    }

    .section-title-row h2 {
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .charts-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.25rem;
    }

    .content-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
        display: flex;
        flex-direction: column;
        min-width: 0;
        overflow: hidden;
    }

    .content-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.85rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .content-card-header h3 {
        font-size: 0.9rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }

    .chart-container-box {
        position: relative;
        height: 280px;
        width: 100%;
        min-width: 0;
    }

    .chart-container-box canvas {
        max-width: 100% !important;
    }

    /* TABEL MODERN & SCROLLABLE */
    .table-responsive-box {
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        max-height: 480px;
    }

    .modern-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.82rem;
    }

    .modern-table th {
        position: sticky;
        top: 0;
        background: #0f172a;
        color: #ffffff;
        font-weight: 700;
        text-align: left;
        padding: 0.65rem 0.85rem;
        white-space: nowrap;
        z-index: 10;
        border-bottom: 2px solid #0284c7;
    }

    .modern-table th.text-right,
    .modern-table td.text-right {
        text-align: right;
    }

    .modern-table td {
        padding: 0.65rem 0.85rem;
        border-bottom: 1px solid #f1f5f9;
        white-space: nowrap;
        background: #ffffff;
        color: #1e293b;
    }

    .modern-table tbody tr:nth-child(even) td {
        background: #f8fafc;
    }

    .modern-table tbody tr:hover td {
        background: #f0f9ff;
    }

    .btn-export-excel {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.45rem 0.85rem;
        font-size: 0.78rem;
        font-weight: 700;
        color: #166534;
        background: #dcfce7;
        border: 1px solid #86efac;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s;
    }

    .btn-export-excel:hover {
        background: #bbf7d0;
    }

    .growth-badge {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
    }
    .growth-pos { background: #dcfce7; color: #15803d; }
    .growth-neg { background: #fee2e2; color: #dc2626; }
    .growth-zero { background: #f1f5f9; color: #475569; }
    .growth-na { background: #f8fafc; color: #94a3b8; border: 1px dashed #cbd5e1; }
    .badge-unavailable { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }

    /* Badge Status */
    .badge-status-rilis {
        font-size: 0.78rem;
        font-weight: 700;
        color: #0369a1;
        background: #e0f2fe;
        border: 1px solid #bae6fd;
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }

    /* Responsif Layar */
    @media (max-width: 1024px) {
        .kpi-grid-4, .kpi-grid-5 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .charts-grid-2, .kpi-grid-2 { grid-template-columns: minmax(0, 1fr); }
        .filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 640px) {
        .kpi-grid-4, .kpi-grid-5 { grid-template-columns: minmax(0, 1fr); }
        .dashboard-header-card { padding: 1rem; }
    }
</style>
@endpush

@section('content')
<div class="dashboard-wrapper">

    <!-- 1. KARTU HEADER & KONTROL TERPADU (Methodology Switcher & Transaction Switcher) -->
    <div class="dashboard-header-card">
        <div class="dashboard-title-row">
            <div class="dashboard-title-group">
                <h1>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2" style="display: inline-block; vertical-align: -3px;"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                    Dashboard Statistik Perdagangan Luar Negeri
                </h1>
                <p>Sistem Informasi Data Ekspor-Impor (SIDATEX) — Badan Pusat Statistik Provinsi Riau</p>
            </div>
            <div>
                @if(isset($canViewPrivate) && $canViewPrivate)
                    <span class="badge-status-rilis" style="background: #fef3c7; color: #b45309; border-color: #fde68a;">
                        <span style="font-size: 0.6rem;">●</span> Mode Eksekutif: Data RILIS + INTERNAL (PRIVATE)
                    </span>
                @else
                    <span class="badge-status-rilis">
                        <span style="font-size: 0.6rem;">●</span> Data Resmi Publik (Status: RILIS)
                    </span>
                @endif
            </div>
        </div>

        <!-- Segmented Control Bar: Pilihan Metodologi & Jenis Transaksi (2 Baris Rapi) -->
        <div class="segmented-switchers-row">
            <!-- Row 1: Switcher Sumber Data Metodologis -->
            <div class="switcher-row">
                <div class="switcher-label-wrapper">
                    <span class="switcher-label">Sumber Data:</span>
                    <div class="methodology-tooltip-container">
                        <button type="button"
                                id="btnMethodologyInfo"
                                class="methodology-info-btn"
                                aria-label="Informasi perbedaan metodologi Distribusi dan Neraca"
                                aria-expanded="false"
                                aria-controls="methodologyTooltipPopover"
                                title="Penjelasan Cakupan Metodologi BPS">
                            <svg style="width: 0.85rem; height: 0.85rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </button>
                        <div id="methodologyTooltipPopover"
                             class="methodology-tooltip-popover"
                             role="tooltip"
                             style="display: none;">
                            <div style="font-weight: 800; font-size: 0.8rem; margin-bottom: 0.35rem; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.25rem;">
                                Perbedaan Cakupan Metodologi BPS
                            </div>
                            <div style="margin-bottom: 0.4rem; line-height: 1.4;">
                                <strong style="color: #0369a1;">Distribusi (Arus Pintu Pelabuhan):</strong><br>
                                Mencatat arus fisik barang keluar/masuk pintu pelabuhan di Provinsi Riau (POD untuk Ekspor &amp; KDPROV untuk Impor). Ditujukan untuk analisis logistik kepelabuhanan daerah.
                            </div>
                            <div style="margin-bottom: 0.4rem; line-height: 1.4;">
                                <strong style="color: #c2410c;">Neraca (Asal Barang &amp; Pabean):</strong><br>
                                Mencatat barang yang diproduksi di Provinsi Riau (PROVORIG) untuk ekspor dan pabean pengawasan kantor Riau (KPPBC) untuk impor. Ditujukan untuk publikasi resmi BRS dan PDRB.
                            </div>
                            <div style="margin-top: 0.4rem; padding-top: 0.3rem; border-top: 1px solid #f1f5f9; font-size: 0.72rem; color: #64748b; font-style: italic;">
                                * Angka Distribusi dan Neraca memiliki cakupan berbeda sehingga tidak boleh digabung/dijumlahkan.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="segmented-pills">
                    <button type="button" class="segmented-pill-btn active" id="btnSumberDistribusi" onclick="setDashboardSumber('distribusi')" title="Distribusi: Mengukur arus barang keluar/masuk pelabuhan Riau (POD/KDPROV) untuk analisis kepelabuhanan.">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: -2px; margin-right: 4px;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                        Distribusi (POD/KDPROV)
                    </button>
                    <button type="button" class="segmented-pill-btn" id="btnSumberNeraca" onclick="setDashboardSumber('neraca')" title="Neraca: Mengukur barang asal produksi Riau (PROVORIG) dan kantor pabean (KPPBC) untuk rilis BRS &amp; PDRB.">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: -2px; margin-right: 4px;"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"></path><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"></path><path d="M7 21h10"></path><path d="M12 3v18"></path><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"></path></svg>
                        Neraca (PROVORIG/KPPBC)
                    </button>
                </div>
            </div>

            <!-- Row 2: Switcher Jenis Transaksi -->
            <div class="switcher-row">
                <div class="switcher-label-wrapper">
                    <span class="switcher-label">Jenis Data:</span>
                </div>
                <div class="segmented-pills">
                    <button type="button" class="segmented-pill-btn active" id="btnJenisEkspor" onclick="setDashboardJenis('Ekspor')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: -2px; margin-right: 4px;"><path d="M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1 .6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"></path><path d="M19.38 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.9.94 5.34 2.81 7.76"></path><path d="M19 13V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6"></path><path d="M12 10v4"></path><path d="M12 2v3"></path></svg>
                        Ekspor (FOB)
                    </button>
                    <button type="button" class="segmented-pill-btn" id="btnJenisImpor" onclick="setDashboardJenis('Impor')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: -2px; margin-right: 4px;"><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path></svg>
                        Impor (CIF)
                    </button>
                    <button type="button" class="segmented-pill-btn" id="btnJenisNeraca" onclick="setDashboardJenis('Neraca')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; vertical-align: -2px; margin-right: 4px;"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"></path><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"></path><path d="M7 21h10"></path><path d="M12 3v18"></path><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"></path></svg>
                        Neraca Perdagangan
                    </button>
                </div>
            </div>
        </div>

        <!-- Catatan Metodologi Dinamis -->
        <div class="methodology-notice-box" id="methodologyNotice">
            <span style="font-weight: 800;">Konsep Metodologi Aktif:</span>
            <span id="methodologyNoticeText">Berdasarkan pintu muat/bongkar kepabeanan pelabuhan di Provinsi Riau (POD untuk Ekspor &amp; KDPROV untuk Impor).</span>
        </div>

        <!-- 4 Tab Navigasi Horizontal -->
        <nav class="dashboard-nav-tabs" aria-label="Navigasi Analisis Dashboard">
            <button type="button" class="nav-tab-button active" id="btnTabOverview" onclick="switchDashboardTab('tabOverview', this)">
                <span class="tab-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                </span>
                <span>Overview Global &amp; Tren</span>
            </button>
            <button type="button" class="nav-tab-button" id="btnTabTimeSeries" onclick="switchDashboardTab('tabTimeSeries', this)">
                <span class="tab-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                </span>
                <span>Time Series Komoditas</span>
            </button>
            <button type="button" class="nav-tab-button" id="btnTabDetailMapping" onclick="switchDashboardTab('tabDetailMapping', this)">
                <span class="tab-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon><line x1="8" y1="2" x2="8" y2="18"></line><line x1="16" y1="6" x2="16" y2="22"></line></svg>
                </span>
                <span>Detail &amp; Pemetaan Daerah</span>
            </button>
            <button type="button" class="nav-tab-button" id="btnTabTradeBalance" onclick="switchDashboardTab('tabTradeBalance', this)">
                <span class="tab-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"></path><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"></path><path d="M7 21h10"></path><path d="M12 3v18"></path><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"></path></svg>
                </span>
                <span>Neraca Perdagangan (Trade Balance)</span>
            </button>
        </nav>
    </div>

    <!-- Banner Galat Inline (Non-Blocking) -->
    <div id="dashboardErrorAlert" role="alert" style="display: none; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 10px; padding: 0.75rem 1rem; font-size: 0.84rem; font-weight: 600;"></div>

    <!-- 2. KONTROL FILTER HORIZONTAL RINGKAS -->
    <div class="filter-bar-card">
        <div class="filter-bar-header">
            <span class="filter-bar-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2" style="display: inline-block; vertical-align: -2px; margin-right: 4px;"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Filter Analisis Data
            </span>
            <button type="button" class="btn-reset-filter" onclick="resetAllFilters()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                Reset Filter
            </button>
        </div>

        <div class="filter-grid">
            <div class="filter-item">
                <label for="filterTahun">Tahun</label>
                <select id="filterTahun" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Tahun</option>
                    @foreach($availableYears as $y)
                        <option value="{{ $y }}" {{ ($selectedTahun == $y) ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-item">
                <label for="filterBulan">Bulan</label>
                <select id="filterBulan" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Bulan</option>
                    <option value="1">Januari</option>
                    <option value="2">Februari</option>
                    <option value="3">Maret</option>
                    <option value="4">April</option>
                    <option value="5">Mei</option>
                    <option value="6">Juni</option>
                    <option value="7">Juli</option>
                    <option value="8">Agustus</option>
                    <option value="9">September</option>
                    <option value="10">Oktober</option>
                    <option value="11">November</option>
                    <option value="12">Desember</option>
                </select>
            </div>

            <div class="filter-item">
                <label for="filterTriwulan">Triwulan</label>
                <select id="filterTriwulan" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Triwulan</option>
                    <option value="Tw-I">Triwulan I (Jan-Mar)</option>
                    <option value="Tw-II">Triwulan II (Apr-Jun)</option>
                    <option value="Tw-III">Triwulan III (Jul-Sep)</option>
                    <option value="Tw-IV">Triwulan IV (Okt-Des)</option>
                </select>
            </div>

            <div class="filter-item">
                <label for="filterMigas">Kategori</label>
                <select id="filterMigas" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Kategori</option>
                    <option value="Non-Migas">Non-Migas</option>
                    <option value="Migas">Migas</option>
                </select>
            </div>

            <div class="filter-item">
                <label for="filterKabkota">Kab/Kota Riau</label>
                <select id="filterKabkota" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Wilayah</option>
                    @foreach($kabkotas as $kb)
                        <option value="{{ $kb->kabkota_id }}">{{ $kb->nama_kabkota }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-item">
                <label for="filterKomoditi">Komoditas</label>
                <select id="filterKomoditi" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Komoditas</option>
                    @foreach($komoditis as $k)
                        <option value="{{ $k->hs2 }}">HS {{ $k->hs2 }} — {{ \Illuminate\Support\Str::limit($k->hs2_desc, 22) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-item">
                <label for="filterNegara" id="labelFilterNegara">Negara Mitra</label>
                <select id="filterNegara" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Negara</option>
                    @foreach($negaras as $neg)
                        <option value="{{ $neg->negara_id }}">{{ $neg->kode_negara }} - {{ $neg->nama_negara }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-item">
                <label for="filterPelabuhan" id="labelFilterPelabuhan">Pelabuhan</label>
                <select id="filterPelabuhan" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Pelabuhan</option>
                    @foreach($pelabahans ?? [] as $pel)
                        <option value="{{ $pel->pelabuhan_id }}">{{ $pel->nama_pelabuhan }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 1 — OVERVIEW GLOBAL & TREN PERDAGANGAN                                 -->
    <!-- ========================================================================= -->
    <section id="tabOverview" class="dashboard-page-section active">

        <!-- KARTU IKHTISAR DUA METODOLOGI BERDAMPINGAN -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 1rem;">
            <!-- Kartu Ikhtisar Distribusi -->
            <div style="background: #ffffff; border: 1px solid #bfdbfe; border-top: 4px solid #0284c7; border-radius: 12px; padding: 1.15rem; box-shadow: 0 2px 6px rgba(2, 132, 199, 0.05);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                    <div>
                        <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #0284c7;">Metodologi Distribusi</span>
                        <h4 style="margin: 0.2rem 0 0 0; font-size: 1.05rem; font-weight: 800; color: #0f172a;">Pelabuhan Muat/Bongkar Riau (POD/KDPROV)</h4>
                    </div>
                    <span style="font-size: 0.7rem; font-weight: 700; background: #e0f2fe; color: #0369a1; padding: 0.2rem 0.55rem; border-radius: 20px;">
                        {{ $batchStats['distribusi_rilis'] }} Rilis / {{ $batchStats['distribusi_total'] }} Batch
                    </span>
                </div>
                <div style="display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.65rem;">
                    <div>
                        <span style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Ekspor Riau</span>
                        @if(isset($distribusiSummary['Ekspor']))
                            <div style="font-size: 0.92rem; font-weight: 800; color: #0f172a;">US$ {{ number_format($distribusiSummary['Ekspor']->total_nilai, 2, ',', '.') }}</div>
                            <span style="font-size: 0.68rem; color: #64748b;">{{ number_format($distribusiSummary['Ekspor']->total_berat, 0, ',', '.') }} kg</span>
                        @else
                            <div style="font-size: 0.8rem; font-weight: 600; color: #94a3b8; font-style: italic;">Data belum tersedia</div>
                        @endif
                    </div>
                    <div>
                        <span style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Impor Riau</span>
                        @if(isset($distribusiSummary['Impor']))
                            <div style="font-size: 0.92rem; font-weight: 800; color: #0f172a;">US$ {{ number_format($distribusiSummary['Impor']->total_nilai, 2, ',', '.') }}</div>
                            <span style="font-size: 0.68rem; color: #64748b;">{{ number_format($distribusiSummary['Impor']->total_berat, 0, ',', '.') }} kg</span>
                        @else
                            <div style="font-size: 0.8rem; font-weight: 600; color: #94a3b8; font-style: italic;">Data belum tersedia</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Kartu Ikhtisar Neraca -->
            <div style="background: #ffffff; border: 1px solid #fed7aa; border-top: 4px solid #ea580c; border-radius: 12px; padding: 1.15rem; box-shadow: 0 2px 6px rgba(234, 88, 12, 0.05);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                    <div>
                        <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #ea580c;">Metodologi Neraca</span>
                        <h4 style="margin: 0.2rem 0 0 0; font-size: 1.05rem; font-weight: 800; color: #0f172a;">Provinsi Asal &amp; Kantor Pabean BRS (PROVORIG/KPPBC)</h4>
                    </div>
                    <span style="font-size: 0.7rem; font-weight: 700; background: #ffedd5; color: #c2410c; padding: 0.2rem 0.55rem; border-radius: 20px;">
                        {{ $batchStats['neraca_rilis'] }} Rilis / {{ $batchStats['neraca_total'] }} Batch
                    </span>
                </div>
                <div style="display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.65rem;">
                    <div>
                        <span style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Ekspor Asal Riau</span>
                        @if(isset($neracaSummary['Ekspor']))
                            <div style="font-size: 0.92rem; font-weight: 800; color: #0f172a;">US$ {{ number_format($neracaSummary['Ekspor']->total_nilai, 2, ',', '.') }}</div>
                            <span style="font-size: 0.68rem; color: #64748b;">{{ number_format($neracaSummary['Ekspor']->total_berat, 0, ',', '.') }} kg</span>
                        @else
                            <div style="font-size: 0.8rem; font-weight: 600; color: #94a3b8; font-style: italic;">Data belum tersedia</div>
                        @endif
                    </div>
                    <div>
                        <span style="font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Impor Pabean Riau</span>
                        @if(isset($neracaSummary['Impor']))
                            <div style="font-size: 0.92rem; font-weight: 800; color: #0f172a;">US$ {{ number_format($neracaSummary['Impor']->total_nilai, 2, ',', '.') }}</div>
                            <span style="font-size: 0.68rem; color: #64748b;">{{ number_format($neracaSummary['Impor']->total_berat, 0, ',', '.') }} kg</span>
                        @else
                            <div style="font-size: 0.8rem; font-weight: 600; color: #94a3b8; font-style: italic;">Data belum tersedia</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Indikator Pertumbuhan Resmi BPS -->
        <div class="kpi-grid-4">
            <div class="kpi-card" style="border-left: 4px solid #0284c7;">
                <span class="kpi-label">Pertumbuhan M-to-M</span>
                <div class="kpi-value" id="kpiMtom" style="color: #64748b;">—</div>
                <span class="kpi-subtext">Bulan ke bulan sebelumnya</span>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #16a34a;">
                <span class="kpi-label">Pertumbuhan Q-to-Q</span>
                <div class="kpi-value" id="kpiQtoq" style="color: #64748b;">—</div>
                <span class="kpi-subtext">Triwulan ke triwulan sebelumnya</span>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #f59e0b;">
                <span class="kpi-label">Pertumbuhan Y-on-Y</span>
                <div class="kpi-value" id="kpiYony" style="color: #64748b;">—</div>
                <span class="kpi-subtext">vs periode sama tahun lalu</span>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #6366f1;">
                <span class="kpi-label">Pertumbuhan C-to-C</span>
                <div class="kpi-value" id="kpiCtoc" style="color: #64748b;">—</div>
                <span class="kpi-subtext">Kumulatif tahun berjalan</span>
            </div>
        </div>

        <!-- 5 Kartu KPI Utama Sesuai Referensi Kak Bekti -->
        <div class="kpi-grid-5">
            <div class="kpi-card">
                <span class="kpi-label" id="labelKpiTotalNilai">Total Nilai (US$)</span>
                <div class="kpi-value" id="p1KpiFob" style="color: #0f172a;">$0,00</div>
                <span class="kpi-subtext" id="subtextKpiTotalNilai">Nilai devisa transaksi</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Total Volume NETTO (kg)</span>
                <div class="kpi-value" id="p1KpiNetto" style="color: #0284c7;">0 kg</div>
                <span class="kpi-subtext">Volume bersih barang</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Rata-Rata / Transaksi</span>
                <div class="kpi-value" id="p1KpiAvg">$0,00</div>
                <span class="kpi-subtext">Rerata nilai per transaksi</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label" id="labelKpiNegara">Jumlah Negara Mitra</span>
                <div class="kpi-value" id="p1KpiNegara">0</div>
                <span class="kpi-subtext">Mitra dagang aktif</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Kelompok Komoditas</span>
                <div class="kpi-value" id="p1KpiKomoditi">0</div>
                <span class="kpi-subtext">Golongan barang HS aktif</span>
            </div>
        </div>

        <!-- Grafik Tren Bulanan (FOB/CIF & Netto) -->
        <div class="section-title-row">
            <h2>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2" style="display: inline-block; vertical-align: -2px; margin-right: 4px;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                Grafik Tren Perdagangan Bulanan Kronologis
            </h2>
            <span style="font-size: 0.78rem; color: #64748b;">Perkembangan Nilai &amp; Volume Netto Sepanjang Periode</span>
        </div>

        <div class="charts-grid-2">
            <div class="content-card">
                <div class="content-card-header">
                    <h3 id="titleChartTrenNilaiBulan">Tren Nilai Bulanan (US$)</h3>
                </div>
                <div class="chart-container-box"><canvas id="chartTrenFobBulan"></canvas></div>
            </div>

            <div class="content-card">
                <div class="content-card-header">
                    <h3>Tren Volume Bersih Netto per Bulan (kg)</h3>
                </div>
                <div class="chart-container-box"><canvas id="chartTrenVolBulan"></canvas></div>
            </div>
        </div>

        <!-- Perbandingan Migas vs Non-Migas -->
        <div class="section-title-row">
            <h2>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2" style="display: inline-block; vertical-align: -2px; margin-right: 4px;"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"></path><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"></path><path d="M7 21h10"></path><path d="M12 3v18"></path><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"></path></svg>
                Perbandingan Sektor Migas dan Non-Migas
            </h2>
            <span style="font-size: 0.78rem; color: #64748b;">Proporsi Nilai dan Volume Bersih Komparatif</span>
        </div>

        <div class="charts-grid-2">
            <div class="content-card">
                <div class="content-card-header">
                    <h3 id="titleChartMigasNilai">Perbandingan Nilai: Migas vs Non-Migas (US$)</h3>
                </div>
                <div class="chart-container-box"><canvas id="chartTrenMigasFob"></canvas></div>
            </div>

            <div class="content-card">
                <div class="content-card-header">
                    <h3>Perbandingan Berat Netto: Migas vs Non-Migas (kg)</h3>
                </div>
                <div class="chart-container-box"><canvas id="chartTrenMigasNetto"></canvas></div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- TAB 2 — TIME SERIES KOMODITAS                                              -->
    <!-- ========================================================================= -->
    <section id="tabTimeSeries" class="dashboard-page-section">
        <div class="section-title-row">
            <h2>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2" style="display: inline-block; vertical-align: -2px; margin-right: 4px;"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                Analisis Tabulasi Time Series Komoditas
            </h2>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="button" class="btn-export-excel" onclick="exportTableToExcel('tableTsBulanFob', 'Time_Series_Nilai_Komoditas')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Ekspor Tabel Nilai ke Excel
                </button>
            </div>
        </div>

        <!-- Tabel 1: Perkembangan Nilai per Bulan Berdasarkan Komoditas -->
        <div class="content-card">
            <div class="content-card-header">
                <h3 id="titleTableTsBulanNilai">1. Tabulasi Perkembangan Nilai per Bulan Berdasarkan Komoditas (US$)</h3>
                <span style="font-size: 0.75rem; color: #64748b;">Klik judul kolom untuk mengurutkan nilai</span>
            </div>
            <div class="table-responsive-box">
                <table class="modern-table" id="tableTsBulanFob">
                    <thead>
                        <tr>
                            <th onclick="sortTable('tableTsBulanFob', 0)" style="cursor: pointer;">Kelompok Komoditas ⇕</th>
                            <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>Mei</th><th>Jun</th>
                            <th>Jul</th><th>Agu</th><th>Sep</th><th>Okt</th><th>Nov</th><th>Des</th>
                            <th class="text-right" onclick="sortTableNumber('tableTsBulanFob', 13)" style="cursor: pointer; background: #0369a1;">Total (US$) ⇕</th>
                            <th class="text-right">Pertumbuhan MoM</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Tabel 2: Perkembangan Berat Netto per Bulan Berdasarkan Komoditas -->
        <div class="content-card">
            <div class="content-card-header">
                <h3>2. Tabulasi Perkembangan Volume Bersih Netto per Bulan Berdasarkan Komoditas (kg)</h3>
                <button type="button" class="btn-export-excel" onclick="exportTableToExcel('tableTsBulanNetto', 'Time_Series_Netto_Komoditas')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Ekspor Tabel Netto ke Excel
                </button>
            </div>
            <div class="table-responsive-box">
                <table class="modern-table" id="tableTsBulanNetto">
                    <thead>
                        <tr>
                            <th onclick="sortTable('tableTsBulanNetto', 0)" style="cursor: pointer;">Kelompok Komoditas ⇕</th>
                            <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>Mei</th><th>Jun</th>
                            <th>Jul</th><th>Agu</th><th>Sep</th><th>Okt</th><th>Nov</th><th>Des</th>
                            <th class="text-right" onclick="sortTableNumber('tableTsBulanNetto', 13)" style="cursor: pointer; background: #0369a1;">Total Netto (kg) ⇕</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Tabel 3: Perkembangan Triwulanan Nilai -->
        <div class="content-card">
            <div class="content-card-header">
                <h3 id="titleTableTsTriwulanNilai">3. Tabulasi Perkembangan Nilai per Triwulan (US$)</h3>
            </div>
            <div class="table-responsive-box">
                <table class="modern-table" id="tableTsTriwulanFob">
                    <thead>
                        <tr>
                            <th>Kelompok Komoditas</th>
                            <th class="text-right">Triwulan I</th>
                            <th class="text-right">Triwulan II</th>
                            <th class="text-right">Triwulan III</th>
                            <th class="text-right">Triwulan IV</th>
                            <th class="text-right" style="background: #0369a1;">Total Kumulatif (US$)</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- TAB 3 — DETAIL ANALISIS & PEMETAAN WILAYAH (Peta Riau & Top 10)           -->
    <!-- ========================================================================= -->
    <section id="tabDetailMapping" class="dashboard-page-section">

        <!-- 2 KPI Besar Terpisah Migas & Non-Migas -->
        <div class="kpi-grid-2">
            <div class="kpi-card" style="border-left: 5px solid #0284c7; padding: 1.25rem 1.5rem;">
                <span class="kpi-label" id="labelDetailNilaiSektoral">Nilai Sektoral (US$) Terfilter</span>
                <div class="kpi-value" id="detailFobVal" style="font-size: 1.8rem; color: #0f172a;">US$ 0,00</div>
                <div style="margin-top: 0.75rem; font-size: 0.85rem; color: #475569; display: flex; justify-content: space-between; border-top: 1px solid #f1f5f9; padding-top: 0.5rem;">
                    <span>Non-Migas: <strong id="detailNonMigasFob" style="color: #0284c7;">US$ 0,00</strong></span>
                    <span>Migas: <strong id="detailMigasFob" style="color: #f59e0b;">US$ 0,00</strong></span>
                </div>
            </div>

            <div class="kpi-card" style="border-left: 5px solid #16a34a; padding: 1.25rem 1.5rem;">
                <span class="kpi-label">Volume Bersih NETTO (kg) Sektoral</span>
                <div class="kpi-value" id="detailNettoVal" style="font-size: 1.8rem; color: #16a34a;">0 kg</div>
                <div style="margin-top: 0.75rem; font-size: 0.85rem; color: #475569; display: flex; justify-content: space-between; border-top: 1px solid #f1f5f9; padding-top: 0.5rem;">
                    <span>Non-Migas: <strong id="detailNonMigasNetto" style="color: #16a34a;">0 kg</strong></span>
                    <span>Migas: <strong id="detailMigasNetto" style="color: #f59e0b;">0 kg</strong></span>
                </div>
            </div>
        </div>

        <!-- Peta Interaktif Kontribusi Daerah & Top 10 Kab/Kota -->
        <div class="charts-grid-2">
            <div class="content-card" style="min-height: 420px;">
                <div class="content-card-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2" style="display: inline-block; vertical-align: -2px; margin-right: 4px;"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon><line x1="8" y1="2" x2="8" y2="18"></line><line x1="16" y1="6" x2="16" y2="22"></line></svg>
                        Peta Persebaran Kontribusi Daerah Provinsi Riau
                    </h3>
                    <span style="font-size: 0.75rem; color: #64748b;">Koordinat Sentra Kabupaten &amp; Kota di Riau</span>
                </div>
                <div style="flex: 1; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; min-height: 340px;">
                    <div id="mapInteractive" style="width: 100%; height: 100%; min-height: 340px;"></div>
                </div>
            </div>

            <div class="content-card" style="min-height: 420px;">
                <div class="content-card-header">
                    <h3 id="titleChartKabkotaBar">Top 10 Kabupaten / Kota Penghasil (US$)</h3>
                </div>
                <div class="chart-container-box" style="height: 340px;"><canvas id="chartKabkotaBar"></canvas></div>
            </div>
        </div>

        <!-- 3 Tabel Tabulasi Detail: Negara, Komoditas, dan Pelabuhan -->
        <div class="section-title-row">
            <h2>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2" style="display: inline-block; vertical-align: -2px; margin-right: 4px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Tabulasi Rincian Berdasarkan Kategori
            </h2>
            <button type="button" class="btn-export-excel" onclick="exportTableToExcel('tableNegaraDetail', 'Rincian_Kategori_Perdagangan')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Ekspor Tabulasi ke Excel
            </button>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 1.25rem;">
            <!-- Tabulasi Negara -->
            <div class="content-card">
                <div class="content-card-header">
                    <h3 id="titleTableNegaraDetail">Rincian Negara Mitra</h3>
                </div>
                <div class="table-responsive-box" style="max-height: 340px;">
                    <table class="modern-table" id="tableNegaraDetail">
                        <thead>
                            <tr>
                                <th>Negara Mitra</th>
                                <th class="text-right" id="thNegaraNilai">Nilai (US$)</th>
                                <th class="text-right">Netto (kg)</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Tabulasi Komoditas -->
            <div class="content-card">
                <div class="content-card-header">
                    <h3>Rincian Komoditas Unggulan</h3>
                </div>
                <div class="table-responsive-box" style="max-height: 340px;">
                    <table class="modern-table" id="tableKomoditiDetail">
                        <thead>
                            <tr>
                                <th>Kelompok Komoditas</th>
                                <th class="text-right" id="thKomoditiNilai">Nilai (US$)</th>
                                <th class="text-right">Netto (kg)</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Tabulasi Pelabuhan -->
            <div class="content-card">
                <div class="content-card-header">
                    <h3 id="titleTablePelabuhanDetail">Rincian Pelabuhan</h3>
                </div>
                <div class="table-responsive-box" style="max-height: 340px;">
                    <table class="modern-table" id="tablePelabuhanDetail">
                        <thead>
                            <tr>
                                <th>Pelabuhan</th>
                                <th class="text-right" id="thPelabuhanNilai">Nilai (US$)</th>
                                <th class="text-right">Netto (kg)</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tabel Pertumbuhan MoM & QoQ -->
        <div class="charts-grid-2">
            <div class="content-card">
                <div class="content-card-header">
                    <h3>Pertumbuhan Bulanan (MoM) &amp; Nilai Kumulatif</h3>
                </div>
                <div class="table-responsive-box" style="max-height: 280px;">
                    <table class="modern-table" id="tableGrowthMom">
                        <thead>
                            <tr>
                                <th>Periode Bulan</th>
                                <th class="text-right">Nilai (US$)</th>
                                <th class="text-right">MoM (%)</th>
                                <th class="text-right">Kumulatif Berjalan (US$)</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="content-card">
                <div class="content-card-header">
                    <h3>Pertumbuhan Triwulanan (QoQ) &amp; Nilai Kumulatif</h3>
                </div>
                <div class="table-responsive-box" style="max-height: 280px;">
                    <table class="modern-table" id="tableGrowthQoq">
                        <thead>
                            <tr>
                                <th>Triwulan</th>
                                <th class="text-right">Nilai (US$)</th>
                                <th class="text-right">QoQ (%)</th>
                                <th class="text-right">Kumulatif Berjalan (US$)</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- TAB 4 — NERACA PERDAGANGAN (TRADE BALANCE)                                  -->
    <!-- ========================================================================= -->
    <section id="tabTradeBalance" class="dashboard-page-section">

        <!-- Hero Status Card Neraca Perdagangan -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <span style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b;">Analisis Makro Neraca Perdagangan</span>
                    <h2 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0.25rem 0 0 0;" id="heroTradeStatusTitle">
                        Status Neraca Perdagangan
                    </h2>
                </div>
                <span id="badgeTradeStatus" style="font-size: 1rem; font-weight: 800; padding: 0.5rem 1.25rem; border-radius: 20px; background: #f1f5f9; color: #475569; border: 1.5px solid #cbd5e1;">
                    —
                </span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 1rem;">
                <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 10px; padding: 1rem;">
                    <span style="font-size: 0.72rem; font-weight: 700; color: #0369a1; text-transform: uppercase;">Total Ekspor (FOB)</span>
                    <div style="font-size: 1.35rem; font-weight: 800; color: #0284c7; margin-top: 0.35rem;" id="tbTotalEkspor">—</div>
                    <span style="font-size: 0.72rem; color: #64748b;">Nilai devisa keluar negeri</span>
                </div>
                <div style="background: #fff7ed; border: 1px solid #fed7aa; border-radius: 10px; padding: 1rem;">
                    <span style="font-size: 0.72rem; font-weight: 700; color: #c2410c; text-transform: uppercase;">Total Impor (CIF)</span>
                    <div style="font-size: 1.35rem; font-weight: 800; color: #ea580c; margin-top: 0.35rem;" id="tbTotalImpor">—</div>
                    <span style="font-size: 0.72rem; color: #64748b;">Nilai devisa masuk negeri</span>
                </div>
                <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 1rem;" id="boxNetTrade">
                    <span style="font-size: 0.72rem; font-weight: 700; color: #334155; text-transform: uppercase;">Surplus / Defisit Bersih</span>
                    <div style="font-size: 1.35rem; font-weight: 800; color: #64748b; margin-top: 0.35rem;" id="tbNetTrade">—</div>
                    <span style="font-size: 0.72rem; color: #64748b;" id="tbNetSubtext">Selisih Ekspor vs Impor</span>
                </div>
            </div>

            <!-- Catatan Asimetri Periode Kumulatif Ekspor vs Impor (F-03) -->
            <div id="alertTradeAsymmetry" style="display: none; margin-top: 1rem; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 0.75rem 1rem; color: #92400e; font-size: 0.82rem; line-height: 1.45;">
                <div style="display: flex; align-items: flex-start; gap: 0.6rem;">
                    <svg style="width: 1.15rem; height: 1.15rem; color: #d97706; flex-shrink: 0; margin-top: 0.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <strong style="font-weight: 700; color: #b45309;">Catatan Periode Data:</strong>
                        <span id="alertTradeAsymmetryText"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grafik Komparasi Ekspor vs Impor per Bulan -->
        <div class="content-card">
            <div class="content-card-header">
                <h3>Grafik Komparasi Nilai Ekspor (FOB) vs Impor (CIF) per Bulan</h3>
                <span style="font-size: 0.75rem; color: #64748b;">Perbandingan devisa pada periode tahun terpilih</span>
            </div>
            <div class="chart-container-box" style="height: 320px;">
                <canvas id="chartTradeBalanceCompare"></canvas>
            </div>
        </div>

        <!-- Tabel Rincian Neraca Perdagangan Bulanan -->
        <div class="content-card">
            <div class="content-card-header">
                <h3>Tabulasi Neraca Perdagangan dan Kumulatif Bulanan</h3>
                <button type="button" class="btn-export-excel" onclick="exportTableToExcel('tableTradeBalanceMonthly', 'Neraca_Perdagangan_Bulanan')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Ekspor Neraca ke Excel
                </button>
            </div>
            <div class="table-responsive-box">
                <table class="modern-table" id="tableTradeBalanceMonthly">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th class="text-right">Ekspor (FOB US$)</th>
                            <th class="text-right">Impor (CIF US$)</th>
                            <th class="text-right" style="background: #0369a1;">Neraca (US$)</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Kumulatif Devisa (US$)</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </section>

</div>
@endsection

@push('scripts')
<script>
/**
 * Global State Single Dashboard SIDATEX
 */
let currentSumber = '{{ $initialSumber ?? "distribusi" }}';
let currentJenis = '{{ $initialJenis ?? "Ekspor" }}';
let activeTab = 'tabOverview';
let dashboardCharts = {};
let riauMap = null;
let riauMapLayer = null;

// Peta Koordinat 12 Kabupaten / Kota di Provinsi Riau
const riauCoords = {
    "Pekanbaru": [0.5071, 101.4478],
    "Dumai": [1.6671, 101.4431],
    "Bengkalis": [1.4822, 102.0789],
    "Siak": [0.7937, 102.0494],
    "Kampar": [0.3394, 101.0253],
    "Rokan Hilir": [2.1585, 100.8239],
    "Rokan Hulu": [0.8631, 100.5283],
    "Indragiri Hilir": [-0.3236, 103.1558],
    "Indragiri Hulu": [-0.5517, 102.3168],
    "Pelalawan": [0.2238, 102.0833],
    "Kuantan Singingi": [-0.5303, 101.4485],
    "Kepulauan Meranti": [1.0113, 102.7118]
};

/**
 * Validasi Nilai Numerik Ketat (membedakan 0 riil vs null/undefined/NaN)
 */
function isValidNumber(val) {
    return val !== null && val !== undefined && val !== '' && Number.isFinite(Number(val));
}

/**
 * Escaping HTML untuk teks dinamis yang masuk ke markup tabel/popup
 */
function escapeHtml(val) {
    return String(val ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Format Numerik Standar Indonesia (non-nullable fallback untuk angka terjamin)
 */
function formatNumber(num, decimals = 2) {
    if (!isValidNumber(num)) return '0,00';
    return Number(num).toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

/**
 * Format Numerik Nullable: null/undefined/NaN -> '—', sedangkan 0 riil tetap '0,00'
 */
function formatNullableNumber(val, decimals = 2) {
    if (!isValidNumber(val)) return '—';
    return Number(val).toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

/**
 * Format Mata Uang Nullable: null/undefined/NaN -> '—', 0 riil -> '$ 0,00', negatif -> '-$ X,XX'
 */
function formatNullableCurrency(val, prefix = '$ ', signed = false) {
    if (!isValidNumber(val)) return '—';
    const n = Number(val);
    const formatted = formatNullableNumber(Math.abs(n), 2);
    if (n < 0) return `-${prefix}${formatted}`;
    if (signed && n > 0) return `+${prefix}${formatted}`;
    return `${prefix}${formatted}`;
}

/**
 * Format Persentase Pertumbuhan BPS: null/undefined/NaN -> '—', 0 riil -> '0,00%'
 */
function formatGrowthText(val) {
    if (!isValidNumber(val)) return '—';
    const n = Number(val);
    const sign = n > 0 ? '+' : '';
    return `${sign}${formatNullableNumber(n, 2)}%`;
}

/**
 * Bangun Badge HTML Pertumbuhan (membedakan unavailable '—' vs 0,00% riil)
 */
function buildGrowthBadgeHtml(val) {
    if (!isValidNumber(val)) {
        return '<span class="growth-badge growth-na" title="Periode pembanding belum tersedia">—</span>';
    }
    const n = Number(val);
    const badgeClass = n > 0 ? 'growth-pos' : (n < 0 ? 'growth-neg' : 'growth-zero');
    return `<span class="growth-badge ${badgeClass}">${formatGrowthText(n)}</span>`;
}

/**
 * Mengatur Sumber Metodologi (Distribusi vs Neraca)
 */
function setDashboardSumber(sumber) {
    currentSumber = sumber;
    document.getElementById('btnSumberDistribusi').classList.toggle('active', sumber === 'distribusi');
    document.getElementById('btnSumberNeraca').classList.toggle('active-neraca', sumber === 'neraca');
    document.getElementById('btnSumberNeraca').classList.toggle('active', sumber === 'neraca');

    const noticeBox = document.getElementById('methodologyNotice');
    const noticeText = document.getElementById('methodologyNoticeText');

    if (sumber === 'distribusi') {
        noticeBox.classList.remove('neraca-mode');
        noticeText.textContent = "Berdasarkan pintu muat/bongkar kepabeanan pelabuhan di Provinsi Riau (POD untuk Ekspor & KDPROV untuk Impor) — Mengukur arus fisik barang kepelabuhanan.";
    } else {
        noticeBox.classList.add('neraca-mode');
        noticeText.textContent = "Berdasarkan provinsi asal barang Riau (PROVORIG) & kantor pabean KPPBC — Rilis resmi Berita Resmi Statistik (BRS) & PDRB.";
    }

    fetchDashboardData();
}

/**
 * Mengatur Jenis Transaksi (Ekspor, Impor, Neraca Perdagangan)
 */
function setDashboardJenis(jenis) {
    currentJenis = jenis;
    document.getElementById('btnJenisEkspor').classList.toggle('active', jenis === 'Ekspor');
    document.getElementById('btnJenisImpor').classList.toggle('active', jenis === 'Impor');
    document.getElementById('btnJenisNeraca').classList.toggle('active', jenis === 'Neraca');

    // Jika memilih Neraca Perdagangan, otomatis pindah ke Tab 4
    if (jenis === 'Neraca') {
        switchDashboardTab('tabTradeBalance', document.getElementById('btnTabTradeBalance'));
    }

    // Sesuaikan Label Dinamis (FOB vs CIF, Tujuan vs Asal)
    updateDynamicLabels();
    fetchDashboardData();
}

/**
 * Memperbarui Label Antarmuka Sesuai Jenis Transaksi
 */
function updateDynamicLabels() {
    const isImpor = (currentJenis === 'Impor');
    const valLabel = isImpor ? 'CIF' : 'FOB';

    document.getElementById('labelKpiTotalNilai').textContent = `Total Nilai ${valLabel} (US$)`;
    document.getElementById('subtextKpiTotalNilai').textContent = isImpor ? 'Nilai devisa impor (CIF)' : 'Nilai devisa ekspor (FOB)';
    document.getElementById('labelKpiNegara').textContent = isImpor ? 'Jumlah Negara Asal' : 'Jumlah Negara Tujuan';
    document.getElementById('labelFilterNegara').textContent = isImpor ? 'Negara Asal' : 'Negara Tujuan';
    document.getElementById('labelFilterPelabuhan').textContent = isImpor ? 'Pelabuhan Bongkar' : 'Pelabuhan Muat';

    document.getElementById('titleChartTrenNilaiBulan').textContent = `Tren Nilai ${valLabel} per Bulan (US$)`;
    document.getElementById('titleChartMigasNilai').textContent = `Perbandingan Nilai ${valLabel}: Migas vs Non-Migas (US$)`;
    document.getElementById('titleTableTsBulanNilai').textContent = `1. Tabulasi Perkembangan Nilai ${valLabel} per Bulan Berdasarkan Komoditas (US$)`;
    document.getElementById('titleTableTsTriwulanNilai').textContent = `3. Tabulasi Perkembangan Nilai ${valLabel} per Triwulan (US$)`;

    document.getElementById('labelDetailNilaiSektoral').textContent = `Nilai ${valLabel} (US$) Sektoral Terfilter`;
    document.getElementById('titleChartKabkotaBar').textContent = `Top 10 Kabupaten / Kota Penghasil (${valLabel} US$)`;
    document.getElementById('titleTableNegaraDetail').textContent = isImpor ? 'Rincian Negara Asal' : 'Rincian Negara Tujuan';
    document.getElementById('titleTablePelabuhanDetail').textContent = isImpor ? 'Rincian Pelabuhan Bongkar' : 'Rincian Pelabuhan Muat';
    document.getElementById('thNegaraNilai').textContent = `${valLabel} (US$)`;
    document.getElementById('thKomoditiNilai').textContent = `${valLabel} (US$)`;
    document.getElementById('thPelabuhanNilai').textContent = `${valLabel} (US$)`;
}

/**
 * Berpindah Tab Analisis (Tab 1, Tab 2, Tab 3, Tab 4)
 */
function switchDashboardTab(tabId, btnElement) {
    activeTab = tabId;
    document.querySelectorAll('.dashboard-page-section').forEach(sec => sec.classList.remove('active'));
    document.querySelectorAll('.nav-tab-button').forEach(btn => btn.classList.remove('active'));

    const targetSection = document.getElementById(tabId);
    if (targetSection) targetSection.classList.add('active');
    if (btnElement) btnElement.classList.add('active');

    // Jika tab peta dibuka, invalidate size Leaflet & resize grafik di tab terkait
    if (tabId === 'tabDetailMapping') {
        setTimeout(() => {
            if (riauMap) riauMap.invalidateSize();
            if (dashboardCharts['chartKabkotaBar']) dashboardCharts['chartKabkotaBar'].resize();
        }, 150);
    } else if (tabId === 'tabTradeBalance') {
        setTimeout(() => {
            if (dashboardCharts['chartTradeBalanceCompare']) dashboardCharts['chartTradeBalanceCompare'].resize();
        }, 150);
    }
}

/**
 * Tampilkan / Bersihkan Banner Galat Inline Dashboard
 */
function setDashboardError(message) {
    const alertEl = document.getElementById('dashboardErrorAlert');
    if (!alertEl) return;
    if (message) {
        alertEl.textContent = message;
        alertEl.style.display = 'block';
    } else {
        alertEl.textContent = '';
        alertEl.style.display = 'none';
    }
}

/**
 * Reset Seluruh Filter ke Default
 */
function resetAllFilters() {
    document.getElementById('filterTahun').value = '2026';
    document.getElementById('filterBulan').value = 'ALL';
    document.getElementById('filterTriwulan').value = 'ALL';
    document.getElementById('filterMigas').value = 'ALL';
    document.getElementById('filterKabkota').value = 'ALL';
    document.getElementById('filterKomoditi').value = 'ALL';
    document.getElementById('filterNegara').value = 'ALL';
    document.getElementById('filterPelabuhan').value = 'ALL';
    fetchDashboardData();
}

/**
 * Ambil Data Terintegrasi dari Backend via AJAX
 */
function fetchDashboardData() {
    const params = new URLSearchParams({
        tipe_sumber: currentSumber,
        jenis: currentJenis,
        tahun: document.getElementById('filterTahun').value,
        bulan: document.getElementById('filterBulan').value,
        triwulan: document.getElementById('filterTriwulan').value,
        migas: document.getElementById('filterMigas').value,
        kabkota_id: document.getElementById('filterKabkota').value,
        komoditi: document.getElementById('filterKomoditi').value,
        negara_id: document.getElementById('filterNegara').value,
        pelabuhan_id: document.getElementById('filterPelabuhan').value
    });

    setDashboardError('');

    fetch(`{{ route('dashboard.data') }}?${params.toString()}`)
        .then(async response => {
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload || !payload.success) {
                throw new Error((payload && payload.message) ? payload.message : 'Gagal mengambil data dashboard.');
            }
            return payload;
        })
        .then(res => {
            updateTab1Overview(res);
            updateTab2TimeSeries(res);
            updateTab3DetailMapping(res);
            updateTab4TradeBalance(res);
        })
        .catch(err => {
            console.error('Error fetching dashboard data:', err);
            setDashboardError(err && err.message ? err.message : 'Terjadi kesalahan saat memuat data dashboard.');
        });
}

/**
 * Render Nilai KPI Pertumbuhan (Tab 1)
 */
function setGrowthKpiValue(elementId, val) {
    const el = document.getElementById(elementId);
    if (!el) return;
    if (!isValidNumber(val)) {
        el.textContent = '—';
        el.style.color = '#64748b';
        el.title = 'Periode pembanding belum tersedia';
        return;
    }
    const n = Number(val);
    el.textContent = formatGrowthText(n);
    el.title = '';
    el.style.color = n > 0 ? '#15803d' : (n < 0 ? '#dc2626' : '#0f172a');
}

/**
 * Update Elemen Tab 1: Overview Global & Tren
 */
function updateTab1Overview(res) {
    const sum = res.summary || {};
    const monthly = res.monthly || {};
    const isImpor = (currentJenis === 'Impor');
    const currencyPrefix = '$ ';

    document.getElementById('p1KpiFob').textContent = formatNullableCurrency(sum.total_fob, currencyPrefix);
    document.getElementById('p1KpiNetto').textContent = isValidNumber(sum.total_netto) ? `${formatNullableNumber(sum.total_netto)} kg` : '—';
    document.getElementById('p1KpiAvg').textContent = formatNullableCurrency(sum.avg_per_transaksi, currencyPrefix);
    document.getElementById('p1KpiNegara').textContent = isValidNumber(sum.total_negara) ? Number(sum.total_negara).toLocaleString('id-ID') : '—';
    document.getElementById('p1KpiKomoditi').textContent = isValidNumber(sum.total_komoditi) ? Number(sum.total_komoditi).toLocaleString('id-ID') : '—';

    // Pertumbuhan Resmi BPS (null -> '—', 0 riil -> '0,00%')
    setGrowthKpiValue('kpiMtom', sum.mtom);
    setGrowthKpiValue('kpiQtoq', sum.qtoq);
    setGrowthKpiValue('kpiYony', sum.yony);
    setGrowthKpiValue('kpiCtoc', sum.ctoc);

    // Render Tren Bulanan (FOB/CIF & Netto)
    renderLineChart('chartTrenFobBulan', monthly.labels || [], monthly.fobValues || [], isImpor ? 'CIF (US$)' : 'FOB (US$)', '#0284c7');
    renderLineChart('chartTrenVolBulan', monthly.labels || [], monthly.nettoValues || [], 'Netto (kg)', '#16a34a');

    // Render Perbandingan Migas vs Non-Migas
    renderDualLineChart('chartTrenMigasFob', monthly.labels || [], monthly.migasFob || [], monthly.nonMigasFob || [], 'Migas', 'Non-Migas');
    renderDualLineChart('chartTrenMigasNetto', monthly.labels || [], monthly.migasNetto || [], monthly.nonMigasNetto || [], 'Migas', 'Non-Migas');
}

/**
 * Update Elemen Tab 2: Time Series Komoditas
 */
function updateTab2TimeSeries(res) {
    renderTimeSeriesTable('tableTsBulanFob', res.pivot_bulan_fob, true);
    renderTimeSeriesTable('tableTsBulanNetto', res.pivot_bulan_netto, false);
    renderTriwulanTable('tableTsTriwulanFob', res.pivot_triwulan_fob);
}

/**
 * Update Elemen Tab 3: Detail Analisis & Pemetaan Wilayah
 */
function updateTab3DetailMapping(res) {
    const sum = res.summary || {};
    const growthTables = res.growth_tables || {};
    document.getElementById('detailFobVal').textContent = formatNullableCurrency(sum.total_fob, 'US$ ');
    document.getElementById('detailNonMigasFob').textContent = formatNullableCurrency(sum.non_migas_fob, 'US$ ');
    document.getElementById('detailMigasFob').textContent = formatNullableCurrency(sum.migas_fob, 'US$ ');

    document.getElementById('detailNettoVal').textContent = isValidNumber(sum.total_netto) ? `${formatNullableNumber(sum.total_netto)} kg` : '—';
    document.getElementById('detailNonMigasNetto').textContent = isValidNumber(sum.non_migas_netto) ? `${formatNullableNumber(sum.non_migas_netto)} kg` : '—';
    document.getElementById('detailMigasNetto').textContent = isValidNumber(sum.migas_netto) ? `${formatNullableNumber(sum.migas_netto)} kg` : '—';

    renderLeafletMap(res.kabkota_distribusi);
    renderBarChart('chartKabkotaBar', res.kabkota_distribusi);

    renderSimpleTable('tableNegaraDetail', res.top_negara);
    renderSimpleTable('tableKomoditiDetail', res.top_komoditi);
    renderSimpleTable('tablePelabuhanDetail', res.top_pelabuhan);

    renderGrowthTable('tableGrowthMom', growthTables.mom, 'Bulan');
    renderGrowthTable('tableGrowthQoq', growthTables.qoq, 'Triwulan');
}

/**
 * Render Judul Hero Trade Balance Secara Aman (DOM API, tanpa raw innerHTML)
 */
function setHeroTradeTitle(heroEl, prefixText, amountText, amountColor) {
    heroEl.textContent = '';
    heroEl.appendChild(document.createTextNode(prefixText));
    if (amountText) {
        const span = document.createElement('span');
        span.style.color = amountColor;
        span.textContent = amountText;
        heroEl.appendChild(span);
    }
}

/**
 * Update Elemen Tab 4: Neraca Perdagangan (Trade Balance)
 */
function updateTab4TradeBalance(res) {
    const tb = res.trade_balance;
    if (!tb) return;

    document.getElementById('tbTotalEkspor').textContent = formatNullableCurrency(tb.total_ekspor, 'US$ ');
    document.getElementById('tbTotalImpor').textContent = formatNullableCurrency(tb.total_impor, 'US$ ');

    const isComplete = Boolean(tb.has_complete) && isValidNumber(tb.net_trade);
    const badge = document.getElementById('badgeTradeStatus');
    const heroTitle = document.getElementById('heroTradeStatusTitle');
    const netTradeEl = document.getElementById('tbNetTrade');
    const netSubtext = document.getElementById('tbNetSubtext');

    if (isComplete) {
        const netVal = Number(tb.net_trade);
        const statusText = tb.status || (netVal > 0 ? 'Surplus' : (netVal < 0 ? 'Defisit' : 'Seimbang'));
        badge.textContent = String(statusText).toUpperCase();

        if (netVal > 0) {
            badge.style.background = '#dcfce7';
            badge.style.color = '#15803d';
            badge.style.borderColor = '#86efac';
            setHeroTradeTitle(heroTitle, 'Surplus Perdagangan Luar Negeri: ', formatNullableCurrency(netVal, 'US$ ', true), '#15803d');
            netTradeEl.textContent = formatNullableCurrency(netVal, 'US$ ', true);
            netTradeEl.style.color = '#15803d';
            netTradeEl.title = '';
            netSubtext.textContent = 'Surplus Devisa Bersih';
        } else if (netVal < 0) {
            badge.style.background = '#fee2e2';
            badge.style.color = '#dc2626';
            badge.style.borderColor = '#fca5a5';
            setHeroTradeTitle(heroTitle, 'Defisit Perdagangan Luar Negeri: ', formatNullableCurrency(netVal, 'US$ ', true), '#dc2626');
            netTradeEl.textContent = formatNullableCurrency(netVal, 'US$ ', true);
            netTradeEl.style.color = '#dc2626';
            netTradeEl.title = '';
            netSubtext.textContent = 'Defisit Devisa Bersih';
        } else {
            badge.style.background = '#f1f5f9';
            badge.style.color = '#475569';
            badge.style.borderColor = '#cbd5e1';
            setHeroTradeTitle(heroTitle, 'Neraca Perdagangan Seimbang: ', 'US$ 0,00', '#475569');
            netTradeEl.textContent = 'US$ 0,00';
            netTradeEl.style.color = '#475569';
            netTradeEl.title = '';
            netSubtext.textContent = 'Neraca Seimbang (Ekspor = Impor)';
        }
    } else {
        const unavailableStatus = tb.status || 'Data Belum Tersedia';
        badge.textContent = unavailableStatus;
        badge.style.background = '#fef3c7';
        badge.style.color = '#b45309';
        badge.style.borderColor = '#fde68a';
        heroTitle.textContent = 'Status: ' + unavailableStatus;
        netTradeEl.textContent = '—';
        netTradeEl.style.color = '#64748b';
        netTradeEl.title = unavailableStatus;
        netSubtext.textContent = 'Membutuhkan kedua sisi Ekspor & Impor';
    }

    renderTradeCompareChart('chartTradeBalanceCompare', tb.monthly);
    renderTradeBalanceTable('tableTradeBalanceMonthly', tb.monthly);

    // Penanganan Catatan Asimetri Periode Kumulatif Ekspor vs Impor (F-03)
    const asymmetryEl = document.getElementById('alertTradeAsymmetry');
    const asymmetryTextEl = document.getElementById('alertTradeAsymmetryText');
    if (asymmetryEl && asymmetryTextEl) {
        if (Boolean(tb.is_asymmetric_cumulative) && tb.max_ekspor_nama_bulan && tb.max_impor_nama_bulan) {
            asymmetryTextEl.textContent = ' Periode kumulatif ekspor tersedia hingga ' +
                tb.max_ekspor_nama_bulan + ', sedangkan impor hingga ' +
                tb.max_impor_nama_bulan + '. Perbedaan ini mengikuti ketersediaan data yang telah dirilis.';
            asymmetryEl.style.display = 'block';
        } else {
            asymmetryEl.style.display = 'none';
            asymmetryTextEl.textContent = '';
        }
    }
}

/**
 * Line Chart Reusable
 */
function renderLineChart(canvasId, labels, dataValues, datasetLabel, colorHex) {
    if (dashboardCharts[canvasId]) dashboardCharts[canvasId].destroy();
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    dashboardCharts[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: datasetLabel,
                data: dataValues,
                borderColor: colorHex,
                backgroundColor: colorHex + '18',
                fill: true,
                tension: 0.25,
                borderWidth: 2.5,
                pointRadius: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { color: '#f1f5f9' }, ticks: { callback: v => '$' + (v >= 1000000 ? (v/1000000).toFixed(1) + 'M' : v) } }
            }
        }
    });
}

/**
 * Dual Line Chart Reusable (Migas vs Non-Migas)
 */
function renderDualLineChart(canvasId, labels, dataMigas, dataNonMigas, labelMigas, labelNonMigas) {
    if (dashboardCharts[canvasId]) dashboardCharts[canvasId].destroy();
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    dashboardCharts[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                { label: labelNonMigas, data: dataNonMigas, borderColor: '#0284c7', backgroundColor: '#0284c715', fill: true, tension: 0.25, borderWidth: 2, pointRadius: 2.5 },
                { label: labelMigas, data: dataMigas, borderColor: '#f59e0b', backgroundColor: '#f59e0b15', fill: true, tension: 0.25, borderWidth: 2, pointRadius: 2.5 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { weight: 'bold' } } } },
            scales: { x: { grid: { display: false } }, y: { grid: { color: '#f1f5f9' } } }
        }
    });
}

/**
 * Horizontal Bar Chart (Top 10 Kab/Kota)
 */
function renderBarChart(canvasId, list) {
    if (dashboardCharts[canvasId]) dashboardCharts[canvasId].destroy();
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const top = (list || []).slice(0, 10);
    const labels = top.map(i => i.kabkota);
    const values = top.map(i => i.fob);

    const ctx = canvas.getContext('2d');
    dashboardCharts[canvasId] = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Nilai (US$)',
                data: values,
                backgroundColor: '#0284c7',
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { grid: { color: '#f1f5f9' } }, y: { grid: { display: false } } }
        }
    });
}

/**
 * Grouped Bar Chart Komparasi Ekspor vs Impor (Tab 4)
 */
function renderTradeCompareChart(canvasId, monthlyData) {
    if (dashboardCharts[canvasId]) dashboardCharts[canvasId].destroy();
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const labels = (monthlyData || []).map(d => d.nama_bulan);
    const eksporValues = (monthlyData || []).map(d => d.ekspor);
    const imporValues = (monthlyData || []).map(d => d.impor);

    const ctx = canvas.getContext('2d');
    dashboardCharts[canvasId] = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Ekspor (FOB US$)', data: eksporValues, backgroundColor: '#0284c7', borderRadius: 4 },
                { label: 'Impor (CIF US$)', data: imporValues, backgroundColor: '#ea580c', borderRadius: 4 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { weight: 'bold' } } } },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { color: '#f1f5f9' }, ticks: { callback: v => '$' + (v >= 1000000 ? (v/1000000).toFixed(1) + 'M' : v) } }
            }
        }
    });
}

/**
 * Render Tabel Time Series Komoditas Bulanan
 */
function renderTimeSeriesTable(tableId, dataList, showMom) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!dataList || dataList.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${showMom ? 15 : 14}" style="text-align:center; padding: 1.5rem; color:#94a3b8;">Tidak ada data komoditas untuk filter ini</td></tr>`;
        return;
    }

    dataList.forEach(item => {
        const tr = document.createElement('tr');
        const values = Array.isArray(item.values) ? item.values : [];
        let html = `<td style="font-weight:700; color:#0f172a;">${escapeHtml(item.komoditi)}</td>`;
        values.forEach(v => {
            html += `<td style="text-align:right;">${(isValidNumber(v) && Number(v) > 0) ? formatNullableNumber(v) : '—'}</td>`;
        });
        html += `<td style="text-align:right; font-weight:800; color:#0284c7;">${formatNullableNumber(item.total)}</td>`;

        if (showMom) {
            html += `<td style="text-align:right;">${buildGrowthBadgeHtml(item.mom_growth)}</td>`;
        }

        tr.innerHTML = html;
        tbody.appendChild(tr);
    });
}

/**
 * Render Tabel Time Series Triwulanan
 */
function renderTriwulanTable(tableId, dataList) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!dataList || dataList.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 1.5rem; color:#94a3b8;">Tidak ada data triwulanan</td></tr>`;
        return;
    }

    dataList.forEach(item => {
        const tr = document.createElement('tr');
        const values = Array.isArray(item.values) ? item.values : [];
        let html = `<td style="font-weight:700; color:#0f172a;">${escapeHtml(item.komoditi)}</td>`;
        values.forEach(v => {
            html += `<td style="text-align:right;">${(isValidNumber(v) && Number(v) > 0) ? formatNullableCurrency(v, '$ ') : '—'}</td>`;
        });
        html += `<td style="text-align:right; font-weight:800; color:#0369a1;">${formatNullableCurrency(item.total, '$ ')}</td>`;
        tr.innerHTML = html;
        tbody.appendChild(tr);
    });
}

/**
 * Render Tabel Detail Sederhana (Negara, Komoditas, Pelabuhan)
 */
function renderSimpleTable(tableId, dataList) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!dataList || dataList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 1.25rem; color:#94a3b8;">Tidak ada data</td></tr>';
        return;
    }

    dataList.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="font-weight:600; color:#0f172a;">${escapeHtml(item.item)}</td>
            <td style="text-align:right; font-weight:700; color:#0284c7;">${formatNullableCurrency(item.fob, '$ ')}</td>
            <td style="text-align:right; color:#16a34a;">${formatNullableNumber(item.netto)}</td>
        `;
        tbody.appendChild(tr);
    });
}

/**
 * Render Tabel Pertumbuhan MoM & QoQ
 */
function renderGrowthTable(tableId, dataList, labelCol) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!dataList || dataList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 1.25rem; color:#94a3b8;">Tidak ada data pertumbuhan</td></tr>';
        return;
    }

    dataList.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="font-weight:600; color:#0f172a;">${escapeHtml(item.periode)}</td>
            <td style="text-align:right; font-weight:700; color:#0284c7;">${formatNullableCurrency(item.nilai, '$ ')}</td>
            <td style="text-align:right;">${buildGrowthBadgeHtml(item.growth)}</td>
            <td style="text-align:right; font-weight:700; color:#0f172a;">${formatNullableCurrency(item.kumulatif, '$ ')}</td>
        `;
        tbody.appendChild(tr);
    });
}

/**
 * Render Tabel Neraca Perdagangan Bulanan (Tab 4 — Null-Safe & Real-Zero Safe)
 */
function renderTradeBalanceTable(tableId, monthlyList) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!monthlyList || monthlyList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 1.25rem; color:#94a3b8;">Tidak ada data neraca perdagangan</td></tr>';
        return;
    }

    monthlyList.forEach(item => {
        const tr = document.createElement('tr');
        const isComplete = Boolean(item.has_complete) && isValidNumber(item.neraca);
        let neracaText = '—';
        let neracaColor = '#64748b';
        let neracaTitle = '';
        let statusBadge = '';

        if (isComplete) {
            const netVal = Number(item.neraca);
            if (netVal > 0) {
                neracaText = formatNullableCurrency(netVal, '$ ', true);
                neracaColor = '#16a34a';
                statusBadge = '<span class="growth-badge growth-pos">Surplus</span>';
            } else if (netVal < 0) {
                neracaText = formatNullableCurrency(netVal, '$ ', true);
                neracaColor = '#dc2626';
                statusBadge = '<span class="growth-badge growth-neg">Defisit</span>';
            } else {
                neracaText = '$ 0,00';
                neracaColor = '#475569';
                statusBadge = '<span class="growth-badge growth-zero">Seimbang</span>';
            }
        } else {
            const unavailableLabel = item.status || 'Data Belum Tersedia';
            neracaText = '—';
            neracaColor = '#64748b';
            neracaTitle = ` title="${escapeHtml(unavailableLabel)}"`;
            statusBadge = `<span class="growth-badge badge-unavailable">${escapeHtml(unavailableLabel)}</span>`;
        }

        const kumText = formatNullableCurrency(item.kumulatif, '$ ');
        const kumColor = isValidNumber(item.kumulatif) ? '#0f172a' : '#64748b';

        tr.innerHTML = `
            <td style="font-weight:700; color:#0f172a;">${escapeHtml(item.nama_bulan)}</td>
            <td style="text-align:right; font-weight:600; color:#0284c7;">${formatNullableCurrency(item.ekspor, '$ ')}</td>
            <td style="text-align:right; font-weight:600; color:#ea580c;">${formatNullableCurrency(item.impor, '$ ')}</td>
            <td style="text-align:right; font-weight:800; color:${neracaColor};"${neracaTitle}>${neracaText}</td>
            <td style="text-align:center;">${statusBadge}</td>
            <td style="text-align:right; font-weight:700; color:${kumColor};">${kumText}</td>
        `;
        tbody.appendChild(tr);
    });
}

/**
 * Peta Interaktif Leaflet Kontribusi Daerah
 */
function renderLeafletMap(kabList) {
    const mapContainer = document.getElementById('mapInteractive');
    if (!mapContainer) return;

    if (!riauMap) {
        riauMap = L.map('mapInteractive').setView([0.55, 101.8], 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap & BPS Riau'
        }).addTo(riauMap);
        riauMapLayer = L.layerGroup().addTo(riauMap);
    }
    riauMapLayer.clearLayers();

    if (!kabList || kabList.length === 0) return;

    kabList.forEach(item => {
        const coords = riauCoords[item.kabkota] || [0.5071, 101.4478];
        const circle = L.circleMarker(coords, {
            radius: Math.min(Math.max((item.fob / 50000000) * 12, 8), 24),
            fillColor: '#0284c7',
            color: '#ffffff',
            weight: 2,
            fillOpacity: 0.85
        });
        circle.bindPopup(`
            <div style="font-family: sans-serif; font-size: 0.85rem; padding: 4px;">
                <strong style="color: #0f172a; font-size: 0.92rem;">${escapeHtml(item.kabkota)}</strong><br/>
                <span style="color:#0284c7; font-weight:bold;">Nilai: ${formatNullableCurrency(item.fob, 'US$ ')}</span><br/>
                <span style="color:#16a34a;">Netto: ${isValidNumber(item.netto) ? formatNullableNumber(item.netto) + ' kg' : '—'}</span>
            </div>
        `);
        circle.addTo(riauMapLayer);
    });
}

/**
 * Fitur Ekspor Tabel HTML ke File Excel (.xlsx) Menggunakan SheetJS
 */
function exportTableToExcel(tableId, fileName) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const wb = XLSX.utils.table_to_book(table, { sheet: "Sheet1" });
    XLSX.writeFile(wb, `${fileName}_${new Date().toISOString().slice(0,10)}.xlsx`);
}

/**
 * Fitur Pengurutan Tabel (Sort Alphabetic & Numeric)
 */
function sortTable(tableId, colIdx) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    rows.sort((a, b) => {
        const aText = a.children[colIdx]?.innerText.trim() || '';
        const bText = b.children[colIdx]?.innerText.trim() || '';
        return aText.localeCompare(bText);
    });
    tbody.innerHTML = '';
    rows.forEach(r => tbody.appendChild(r));
}

function sortTableNumber(tableId, colIdx) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const parseCellNumber = (row) => {
        const raw = row.children[colIdx]?.innerText.trim() || '';
        if (raw === '' || raw === '—') return -Infinity;
        const cleaned = raw.replace(/[^0-9,-]/g, '').replace(',', '.');
        const parsed = parseFloat(cleaned);
        return Number.isFinite(parsed) ? parsed : -Infinity;
    };
    rows.sort((a, b) => parseCellNumber(b) - parseCellNumber(a));
    tbody.innerHTML = '';
    rows.forEach(r => tbody.appendChild(r));
}

// Inisialisasi Popover Tooltip Metodologi Distribusi & Neraca (F-05)
function initMethodologyTooltip() {
    const btn = document.getElementById('btnMethodologyInfo');
    const pop = document.getElementById('methodologyTooltipPopover');
    if (!btn || !pop) return;

    function toggle(show) {
        const isShown = (typeof show === 'boolean') ? show : (pop.style.display === 'none');
        pop.style.display = isShown ? 'block' : 'none';
        btn.setAttribute('aria-expanded', isShown ? 'true' : 'false');
    }

    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        toggle();
    });

    document.addEventListener('click', function(e) {
        if (!pop.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
            toggle(false);
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && pop.style.display === 'block') {
            toggle(false);
            btn.focus();
        }
    });
}

// Inisialisasi Pertama Kali Muat Halaman
document.addEventListener('DOMContentLoaded', function() {
    initMethodologyTooltip();
    updateDynamicLabels();
    fetchDashboardData();
});
</script>
@endpush
