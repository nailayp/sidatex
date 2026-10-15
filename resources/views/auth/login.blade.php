<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Masuk | SIDATEX BPS Provinsi Riau</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-body">
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

    <main
        class="login-page"
        style="--login-background-image: url('{{ asset('images/foto-kantor.jpg') }}')"
    >
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

        <p class="login-page-caption">Sistem Informasi Data Ekspor-Impor · BPS Provinsi Riau</p>
    </main>
</body>
</html>
