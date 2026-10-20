<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Masuk | SIDATEX BPS Provinsi Riau</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* =========================================
           1. STYLING DASAR & LAYOUT FLEKSIBEL
           ========================================= */
        body.login-body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            position: relative;
        }

        /* Styling Video Latar Belakang */
        .video-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            object-fit: cover;
            z-index: -2;
        }

        /* Overlay gelap agar form dan teks lebih mudah dibaca */
        .video-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.45);
            z-index: -1;
        }

        /* Container utama menggunakan flexbox untuk menengahkan panel */
        .login-page {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            padding-top: 80px; /* Ruang untuk header fixed */
            background-color: transparent !important;
            background-image: none !important;
        }

        /* =========================================
           2. STYLING HEADER (MEWAH & CINEMATIC)
           ========================================= */
        .login-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 4rem;
            box-sizing: border-box;
            z-index: 10;
            background: linear-gradient(to bottom, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0) 100%);
        }

        .login-brand {
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: transform 0.3s ease;
        }

        .login-brand:hover {
            transform: scale(1.02);
        }

        .login-bps-logo {
            height: 50px;
            width: auto;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.5));
        }

        .login-brand-divider {
            width: 1.5px;
            height: 42px;
            background-color: rgba(255, 255, 255, 0.3);
            margin: 0 1.2rem;
        }

        .login-brand-copy {
            display: flex;
            flex-direction: column;
        }

        .login-brand-copy strong {
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-shadow: 0 2px 4px rgba(0,0,0,0.6);
            line-height: 1.1;
        }

        .login-brand-copy span {
            color: #e2e8f0;
            font-size: 0.85rem;
            font-weight: 400;
            letter-spacing: 0.02em;
            text-shadow: 0 1px 3px rgba(0,0,0,0.6);
        }

        .login-header-caption {
            color: #ffffff;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 0.5rem 1.2rem;
            border-radius: 2rem;
            background-color: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            white-space: nowrap;
        }

        /* =========================================
           3. STYLING PANEL LOGIN
           ========================================= */
        .login-panel {
            background-color: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            padding: 3rem 2.5rem;
            width: 100%;
            max-width: 420px; /* Membatasi lebar maksimal agar tidak terlalu memanjang */
            box-sizing: border-box;
            margin: 2rem 1rem; /* Margin agar tidak menempel di sisi layar saat di HP */
        }

        .login-panel-heading {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-kicker {
            display: block;
            color: #1976d2;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .login-panel-heading h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 0.5rem 0;
        }

        .login-panel-heading p {
            color: #64748b;
            font-size: 0.9rem;
            margin: 0;
        }

        /* Styling Form & Input */
        .login-field {
            margin-bottom: 1.25rem;
        }

        .login-field label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #334155;
            font-size: 0.85rem;
        }

        .login-field input {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 0.5rem;
            background-color: #eef2f6; /* Warna biru muda sesuai gambar */
            border: 1px solid transparent;
            font-size: 0.95rem;
            color: #0f172a;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .login-field input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            background-color: #ffffff;
        }

        .login-submit {
            width: 100%;
            padding: 0.85rem;
            border-radius: 0.5rem;
            background-color: #1976d2;
            color: #ffffff;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            margin-top: 1rem;
            transition: background-color 0.2s ease;
        }

        .login-submit:hover {
            background-color: #1565c0;
        }

        .login-help-text {
            text-align: center;
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 1.5rem;
            margin-bottom: 0;
        }

        .login-page-caption {
            color: rgba(255, 255, 255, 0.7);
            text-align: center;
            font-size: 0.8rem;
            margin-top: auto;
            padding-bottom: 1.5rem;
        }

        /* =========================================
           4. MEDIA QUERIES (UNTUK HP / FLEKSIBEL)
           ========================================= */
        @media (max-width: 768px) {
            /* Menyesuaikan header saat di layar kecil */
            .login-header {
                padding: 1rem 1.5rem;
                flex-direction: column; /* Menumpuk logo dan badge */
                gap: 1rem;
            }

            .login-brand-divider {
                height: 35px;
                margin: 0 0.8rem;
            }

            .login-bps-logo {
                height: 40px;
            }

            .login-brand-copy strong {
                font-size: 1.25rem;
            }

            .login-brand-copy span {
                font-size: 0.75rem;
            }

            .login-header-caption {
                font-size: 0.75rem;
                padding: 0.4rem 1rem;
            }

            /* Menyesuaikan jarak panel form */
            .login-page {
                padding-top: 130px; /* Menambah ruang karena header ditumpuk */
            }

            .login-panel {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body class="login-body">

    <!-- ELEMEN VIDEO BARU -->
    <video autoplay loop muted playsinline class="video-background">
        <source src="{{ asset('videos/video-kantor.mp4') }}" type="video/mp4">
        Maaf, browser Anda tidak mendukung pemutaran video latar belakang.
    </video>

    <!-- Overlay Gelap -->
    <div class="video-overlay"></div>
    <!-- Header -->
    <header class="login-header">
        <a href="{{ route('login') }}" class="login-brand" aria-label="SIDATEX BPS Provinsi Riau">
            <img src="{{ asset('images/bps.png') }}" alt="Logo BPS" class="login-bps-logo">
            <span class="login-brand-divider" aria-hidden="true"></span>
            <span class="login-brand-copy">
                <strong>SIDATEX</strong>
                <span>Sistem Informasi Data Ekspor-Impor</span>
            </span>
        </a>
        <span class="login-header-caption">BPS PROVINSI RIAU</span>
    </header>

    <main class="login-page">
        <section class="login-panel" aria-labelledby="loginHeading">
            <div class="login-panel-heading">
                <span class="login-kicker">PORTAL INTERNAL</span>
                <h1 id="loginHeading">Selamat datang</h1>
                <p>Masuk untuk melanjutkan ke SIDATEX.</p>
            </div>

            @if ($errors->any())
                <div class="login-alert" role="alert" aria-live="polite">
                    <strong>Login belum berhasil.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('login.process') }}" method="POST" class="login-form">
                @csrf

                <div class="login-field">
                    <label for="username">Username</label>
                    <input
                        id="username"
                        name="username"
                        type="text"
                        value="{{ old('username') }}"
                        autocomplete="username"
                        required
                        autofocus
                        @error('username') aria-invalid="true" aria-describedby="usernameError" @enderror
                    >
                    @error('username')
                        <p class="login-field-error" id="usernameError">{{ $message }}</p>
                    @enderror
                </div>

                <div class="login-field">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        @error('password') aria-invalid="true" aria-describedby="passwordError" @enderror
                    >
                    @error('password')
                        <p class="login-field-error" id="passwordError">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="login-submit">Masuk</button>
            </form>

            <p class="login-help-text">Gunakan akun SIDATEX yang telah diberikan oleh administrator.</p>
        </section>

        <p class="login-page-caption">Sistem Informasi Data Ekspor-Impor - BPS Provinsi Riau</p>
    </main>
</body>
</html>
