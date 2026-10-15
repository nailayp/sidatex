@extends('layouts.app')

@section('title', $pageTitle.' | SIDATEX')

@section('content')
    <section class="page-heading">
        <div>
            <span class="eyebrow">BPS PROVINSI RIAU</span>
            <h1>{{ $pageTitle }}</h1>
            <p>{{ $pageDescription }}</p>
        </div>
        <span class="development-badge">
            <span class="status-dot" aria-hidden="true"></span>
            Tahap pengembangan
        </span>
    </section>

    <section class="data-notice" aria-label="Informasi fitur">
        <span class="notice-icon" aria-hidden="true">i</span>
        <p>
            <strong>Halaman modul tersedia.</strong>
            Fungsionalitas {{ mb_strtolower($pageTitle) }} masih dalam tahap pengembangan. Belum ada pemrosesan atau data yang ditampilkan di halaman ini.
        </p>
    </section>
@endsection
