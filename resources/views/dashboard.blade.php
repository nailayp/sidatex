@extends('layouts.app')

@section('title', 'Dashboard | SIDATEX')

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
            <strong>Statistik belum ditampilkan.</strong>
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
