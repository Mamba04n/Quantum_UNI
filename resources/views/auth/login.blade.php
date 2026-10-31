@php
    $logoFullVersion = filemtime(public_path('storefront/images/logo-completo.png'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso a tu cuenta - Quantum Soluciones</title>
    @include('partials.favicons')
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
    <link rel="stylesheet" href="{{ asset('storefront/fonts/poppins.css') }}">
</head>
<body>
    <div class="login-shell">
        <div class="login-layout">
            <section class="login-brand-panel" aria-label="Identidad de marca">
                <img class="login-brand-logo" src="{{ asset('storefront/images/logo-completo.png') }}?v={{ $logoFullVersion }}" alt="Quantum Soluciones" loading="eager" decoding="async">
            </section>
            <div class="login-wrap">
                <form class="login-card" method="POST" action="{{ route('login') }}">
                    @csrf
                    <h2>Iniciar sesión</h2>
                    <p class="login-card-sub">Ingresa tus credenciales para acceder al portal.</p>
                    @if(session('status'))
                        <p class="error">{{ session('status') }}</p>
                    @endif
                    @error('email')<p class="error">{{ $message }}</p>@enderror
                    @error('password')<p class="error">{{ $message }}</p>@enderror
                    <div class="form-row">
                        <label>Correo</label>
                        <input type="email" name="email" required autofocus value="{{ old('email') }}">
                    </div>
                    <div class="form-row">
                        <label>Contraseña</label>
                        <input type="password" name="password" required>
                        <div style="text-align: right; margin-top: 5px;">
                            <a href="{{ route('password.request') }}" style="font-size: 11px; color: #888; text-decoration: none;">¿Olvidaste tu contraseña?</a>
                        </div>
                    </div>
                    <button class="btn" type="submit">Ingresar a mi cuenta</button>

                    <div style="margin-top:20px; text-align:center;">
                        <a href="{{ route('register') }}" style="font-size:12px; color:#666; text-decoration:underline; font-weight:600;">¿No tienes cuenta? Registrarme como cliente</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @include('storefront.partials.cookie-preferences')
    @vite(['resources/css/storefront-experience.css', 'resources/js/storefront-experience.js'])
</body>
</html>
