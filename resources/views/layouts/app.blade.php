<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'SIDATEX | BPS Provinsi Riau')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="sidatex-body">
    <header class="topbar">
        <button
            type="button"
            class="menu-toggle"
            id="menuToggle"
            aria-label="Buka menu navigasi"
            aria-controls="mainSidebar"
            aria-expanded="true"
        >
            <span aria-hidden="true">☰</span>
        </button>

        <a class="brand" href="{{ route('dashboard') }}" aria-label="SIDATEX, Dashboard">
            <img src="{{ asset('images/bps.png') }}" alt="Logo BPS" class="bps-logo">
            <span class="brand-divider" aria-hidden="true"></span>
            <span class="brand-copy">
                <strong>SIDATEX</strong>
                <span>Sistem Informasi Data Ekspor-Impor</span>
            </span>
        </a>

        <div class="topbar-user">
            <span class="user-avatar" aria-hidden="true">
                {{ mb_strtoupper(mb_substr(auth()->user()->nama ?? auth()->user()->name ?? 'U', 0, 1)) }}
            </span>
            <span class="user-copy">
                <strong>{{ auth()->user()->nama ?? auth()->user()->name ?? 'Pengguna SIDATEX' }}</strong>
                <span>{{ auth()->user()->nip ?? 'Pengguna SIDATEX' }}</span>
            </span>
            <form action="{{ route('logout') }}" method="POST" class="logout-form">
                @csrf
                <button type="submit" class="logout-button">Keluar</button>
            </form>
        </div>
    </header>

    <div class="page-shell" id="pageShell">
        <button class="sidebar-backdrop" id="sidebarBackdrop" type="button" aria-label="Tutup menu"></button>

        <aside class="sidebar" id="mainSidebar" aria-label="Navigasi utama">
            <div class="sidebar-section-label">Menu utama</div>
            <nav class="sidebar-nav">
                <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                    <span class="nav-icon" aria-hidden="true">▦</span>
                    <span class="nav-label">Dashboard</span>
                </a>

                <div class="sidebar-section-label sidebar-section-spaced">Pengelolaan data</div>
                <a href="{{ route('upload-data') }}" class="nav-item {{ request()->routeIs('upload-data') ? 'is-active' : '' }}" @if(request()->routeIs('upload-data')) aria-current="page" @endif>
                    <span class="nav-icon" aria-hidden="true">⇧</span>
                    <span class="nav-label">Upload Data</span>
                    <span class="nav-status">Pengembangan</span>
                </a>
                <a href="{{ route('filtering-data') }}" class="nav-item {{ request()->routeIs('filtering-data') ? 'is-active' : '' }}" @if(request()->routeIs('filtering-data')) aria-current="page" @endif>
                    <span class="nav-icon" aria-hidden="true">▤</span>
                    <span class="nav-label">Filtering Data</span>
                    <span class="nav-status">Pengembangan</span>
                </a>
                <a href="{{ route('manajemen-data') }}" class="nav-item {{ request()->routeIs('manajemen-data') ? 'is-active' : '' }}" @if(request()->routeIs('manajemen-data')) aria-current="page" @endif>
                    <span class="nav-icon" aria-hidden="true">▥</span>
                    <span class="nav-label">Manajemen Data</span>
                    <span class="nav-status">Pengembangan</span>
                </a>

                <div class="sidebar-section-label sidebar-section-spaced">Analisis</div>
                <a href="{{ route('analisis-perdagangan') }}" class="nav-item {{ request()->routeIs('analisis-perdagangan') ? 'is-active' : '' }}" @if(request()->routeIs('analisis-perdagangan')) aria-current="page" @endif>
                    <span class="nav-icon" aria-hidden="true">⌕</span>
                    <span class="nav-label">Analisis Perdagangan</span>
                    <span class="nav-status">Pengembangan</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <img src="{{ asset('images/bps.png') }}" alt="" aria-hidden="true">
                <span>BPS Provinsi Riau</span>
            </div>
        </aside>

        <main class="main-content">
            <div class="main-content-inner">
                @yield('content')
            </div>
        </main>
    </div>

    @stack('scripts')
</body>
</html>
