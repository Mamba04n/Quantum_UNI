<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Quantum Soluciones' }}</title>
    @include('partials.favicons')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @vite(['resources/css/storefront-experience.css', 'resources/js/storefront-experience.js'])
    <link rel="stylesheet" href="{{ asset('storefront/fonts/poppins.css') }}">
</head>
<body class="bg-slate-50 font-sans antialiased">
    {{ $slot }}
    @include('storefront.partials.cookie-preferences')
    @livewireScripts
    @include('partials.livewire-subdirectory-update-uri')
</body>
</html>
