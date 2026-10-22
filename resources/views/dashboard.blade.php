@extends('layouts.app')

@section('title', 'Dashboard | SIDATEX')

@push('styles')
<style>
    /* =========================================
       1. STYLING HEADER HALAMAN (Page Heading)
       ========================================= */
    .page-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        padding: 1.5rem 2rem;
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        margin-bottom: 2rem;
        border: 1px solid rgba(255, 255, 255, 0.4);
    }

    .page-heading h1 {
        font-family: Georgia, 'Times New Roman', serif;
        font-style: italic;
        font-size: 2rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0.25rem 0;
        letter-spacing: 0.03em;
    }

    .eyebrow {
        color: #1976d2;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }

    .page-heading p {
        color: #64748b;
        margin: 0;
        font-size: 0.95rem;
    }

    .development-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background-color: #fef08a; /* Kuning Peringatan */
        color: #854d0e;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .status-dot {
        width: 8px;
        height: 8px;
        background-color: #eab308;
        border-radius: 50%;
        display: inline-block;
    }

    /* =========================================
       2. NOTIS DATA & JUDUL SEKSI
       ========================================= */
    .data-notice {
        background-color: rgba(239, 246, 255, 0.85); /* Biru Muda Transparan */
        backdrop-filter: blur(8px);
        border-left: 4px solid #3b82f6;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        align-items: flex-start;
    }

    .notice-icon {
        background-color: #3b82f6;
        color: white;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.9rem;
        flex-shrink: 0;
    }

    .data-notice p { margin: 0; color: #1e293b; font-size: 0.9rem; line-height: 1.5; }

    .section-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 1.25rem;
    }

    .section-heading h2 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 0.25rem 0;
    }

    .section-heading p { margin: 0; color: #64748b; font-size: 0.9rem; }
    .section-caption { font-size: 0.85rem; color: #ef4444; font-weight: 600; }

    /* =========================================
       3. GRID INDIKATOR (GLASSMORPHISM)
       ========================================= */
    .indicator-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2.5rem;
    }

    .indicator-card {
        background-color: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.6);
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        padding: 1.5rem;
        position: relative;
        display: flex;
        flex-direction: column;
        transition: transform 0.2s ease;
    }

    .indicator-card:hover { transform: translateY(-4px); }

    .indicator-label {
        font-size: 1.05rem;
        font-weight: 700;
        color: #334155;
        margin-bottom: 0.5rem;
    }

    .indicator-value {
        font-size: 2.5rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0.5rem 0;
        line-height: 1;
    }

    .indicator-footnote { font-size: 0.8rem; color: #94a3b8; }

    .indicator-mark {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        background-color: #f1f5f9;
        color: #64748b;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.85rem;
    }

    /* =========================================
       4. GRID RINGKASAN DATA
       ========================================= */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2.5rem;
    }

    .summary-card {
        background-color: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .summary-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.1rem;
        color: white;
        flex-shrink: 0;
    }

    .summary-icon-blue { background-color: #3b82f6; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3); }
    .summary-icon-green { background-color: #10b981; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3); }

    .summary-card div { flex-grow: 1; }
    .summary-card h3 { margin: 0 0 0.25rem 0; font-size: 1.1rem; color: #1e293b; }
    .summary-card p { margin: 0; font-size: 0.85rem; color: #64748b; }
    .summary-card strong { font-size: 1.1rem; color: #ef4444; }

    /* =========================================
       5. GRID MODUL SIDATEX
       ========================================= */
    .module-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    .module-card {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .module-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

    .module-icon {
        font-size: 1.5rem;
        background-color: #f1f5f9;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #475569;
    }

    .module-card h3 { margin: 0 0 0.25rem 0; font-size: 1.05rem; color: #1e293b; }
    .module-card p { margin: 0; font-size: 0.85rem; color: #64748b; line-height: 1.4; flex-grow: 1; }

    .module-status {
        align-self: flex-start;
        background-color: #f1f5f9;
        color: #475569;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    /* Responsivitas */
    @media (max-width: 768px) {
        .page-heading { flex-direction: column; align-items: flex-start; gap: 1rem; }
        .development-badge { align-self: flex-start; }
        .section-heading { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
    }
</style>
@endpush

@section('content')
    <section class="page-heading">
        <div>
            <span class="eyebrow">BPS PROVINSI RIAU</span>
            <h1>Dashboard</h1>
            <p>Ringkasan data ekspor-impor Provinsi Riau.</p>
        </div>
        <span class="development-badge">
            <span class="status-dot" aria-hidden="true"></span>
            Tahap pengembangan
        </span>
    </section>

    <section class="data-notice" aria-label="Status data">
        <span class="notice-icon" aria-hidden="true">i</span>
        <p>
            <strong>Statistik belum ditampilkan.</strong><br>
            Dashboard ini belum terhubung ke data transaksi SIDATEX. Nilai di bawah ini ditandai sebagai belum tersedia dan bukan statistik BPS.
        </p>
    </section>

    <section class="dashboard-section" aria-labelledby="indicatorsHeading">
        <div class="section-heading">
            <div>
                <h2 id="indicatorsHeading">Indikator perdagangan</h2>
                <p>Perbandingan perubahan antarperiode</p>
            </div>
            <span class="section-caption">Belum tersedia</span>
        </div>

        <div class="indicator-grid">
            <article class="indicator-card">
                <span class="indicator-label">Month-to-Month</span>
                <strong class="indicator-value">—</strong>
                <span class="indicator-footnote">Menunggu data transaksi</span>
                <span class="indicator-mark" aria-hidden="true">M</span>
            </article>
            <article class="indicator-card">
                <span class="indicator-label">Quarter-to-Quarter</span>
                <strong class="indicator-value">—</strong>
                <span class="indicator-footnote">Menunggu data transaksi</span>
                <span class="indicator-mark" aria-hidden="true">Q</span>
            </article>
            <article class="indicator-card">
                <span class="indicator-label">Year-on-Year</span>
                <strong class="indicator-value">—</strong>
                <span class="indicator-footnote">Menunggu data transaksi</span>
                <span class="indicator-mark" aria-hidden="true">Y</span>
            </article>
            <article class="indicator-card">
                <span class="indicator-label">Cumulative-to-Cumulative</span>
                <strong class="indicator-value">—</strong>
                <span class="indicator-footnote">Menunggu data transaksi</span>
                <span class="indicator-mark" aria-hidden="true">C</span>
            </article>
        </div>
    </section>

    <section class="dashboard-section" aria-labelledby="summaryHeading">
        <div class="section-heading">
            <div>
                <h2 id="summaryHeading">Ringkasan perdagangan</h2>
                <p>Nilai ekspor dan impor menurut periode</p>
            </div>
        </div>

        <div class="summary-grid">
            <article class="summary-card">
                <span class="summary-icon summary-icon-blue" aria-hidden="true">US$</span>
                <div>
                    <h3>FOB</h3>
                    <p>Nilai Free on Board</p>
                </div>
                <strong>Belum tersedia</strong>
            </article>
            <article class="summary-card">
                <span class="summary-icon summary-icon-green" aria-hidden="true">Kg</span>
                <div>
                    <h3>NETTO</h3>
                    <p>Berat bersih komoditas</p>
                </div>
                <strong>Belum tersedia</strong>
            </article>
        </div>
    </section>

    <section class="dashboard-section" aria-labelledby="nextStepsHeading">
        <div class="section-heading">
            <div>
                <h2 id="nextStepsHeading">Modul SIDATEX</h2>
                <p>Fitur akan tersedia setelah pengembangan modul selesai.</p>
            </div>
        </div>
        <div class="module-grid">
            <article class="module-card">
                <span class="module-icon" aria-hidden="true">⇧</span>
                <div>
                    <h3>Upload Data</h3>
                    <p>Unggah dan validasi data ekspor-impor.</p>
                </div>
                <span class="module-status">Segera hadir</span>
            </article>
            <article class="module-card">
                <span class="module-icon" aria-hidden="true">▤</span>
                <div>
                    <h3>Filtering Data</h3>
                    <p>Telusuri data berdasarkan komoditas dan periode.</p>
                </div>
                <span class="module-status">Segera hadir</span>
            </article>
            <article class="module-card">
                <span class="module-icon" aria-hidden="true">▥</span>
                <div>
                    <h3>Manajemen Data</h3>
                    <p>Kelola data dan status publikasinya.</p>
                </div>
                <span class="module-status">Segera hadir</span>
            </article>
        </div>
    </section>
@endsection
