@php
    $logoFullVersion = filemtime(public_path('storefront/images/logo-completo.png'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Laravel') }}</title>
    @include('partials.favicons')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="login-shell">
        <div class="login-layout">
            <section class="login-brand-panel" aria-label="Identidad de marca">
                <img class="login-brand-logo" src="{{ asset('storefront/images/logo-completo.png') }}?v={{ $logoFullVersion }}" alt="Quantum Soluciones" loading="eager" decoding="async">
            </section>
            <div class="login-wrap">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
