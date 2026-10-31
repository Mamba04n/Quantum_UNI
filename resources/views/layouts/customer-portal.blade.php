<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mi portal | Quantum Soluciones</title>
    @include('partials.favicons')
    <link rel="stylesheet" href="{{ asset('storefront/fonts/poppins.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/customer-portal.css') }}?v={{ filemtime(public_path('css/customer-portal.css')) }}">
    @livewireStyles
</head>
<body class="customer-portal-body">
    {{ $slot }}
    <x-toast />
    @livewireScripts
    @include('partials.livewire-subdirectory-update-uri')
</body>
</html>
