<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifica tu correo | Quantum Soluciones</title>
    @include('partials.favicons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/verify-email.css') }}?v={{ filemtime(public_path('css/verify-email.css')) }}">
    @livewireStyles
    <link rel="stylesheet" href="{{ asset('storefront/fonts/poppins.css') }}">
</head>
<body class="font-sans antialiased verification-page">
    <header class="verification-header"><a href="{{ route('storefront.home') }}" aria-label="Inicio Quantum Soluciones"><img src="{{ asset('storefront/images/logo.webp') }}" width="34" height="34" alt=""><img src="{{ asset('storefront/images/letras.webp') }}" width="138" height="34" alt="Quantum Soluciones"></a><a href="{{ route('storefront.products.index') }}">Volver al catálogo <span aria-hidden="true">→</span></a></header>
    <main class="verification-main">{{ $slot }}</main>
    <footer class="verification-footer"><span>Quantum Soluciones</span><a href="{{ route('storefront.contact') }}">¿Necesitas ayuda? Contáctanos</a></footer>
    @livewireScripts
    @include('partials.livewire-subdirectory-update-uri')
</body>
</html>
