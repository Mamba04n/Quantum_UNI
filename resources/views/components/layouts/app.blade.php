<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ERP CRM') }}</title>
        @include('partials.favicons')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <style>
            [x-cloak] { display: none !important; }
        </style>
    </head>
    @php($documentScroll = request()->routeIs('dashboard', 'workspace', 'sales.customer-request.create', 'sales.quotations.phase.customer-request', 'sales.quotations.phase.customer-request.alias'))
    <body class="quantum-ui bg-slate-50 font-sans antialiased text-slate-900 flex {{ $documentScroll ? 'min-h-screen' : 'h-screen overflow-hidden' }}">

        <!-- Wrapper principal -->
        <div class="flex-1 flex flex-col min-w-0 {{ $documentScroll ? '' : 'overflow-hidden' }}">

            <!-- Topbar / Navbar -->
            @include('livewire.layout.topbar')

            <!-- Area Principal de Contenido (Scrollable) -->
            <main class="flex flex-1 flex-col min-w-0 bg-slate-50 {{ $documentScroll ? '' : 'overflow-x-hidden overflow-y-auto' }}">
                @include('livewire.layout.workspace-navigation')
                @if (isset($header) && !request()->routeIs('inventory.products'))
                    <header class="border-b border-white/70 bg-white/80 shadow-sm backdrop-blur">
                        <div class="py-4 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <!-- Slot de Livewire / Componente -->
                <div class="flex w-full flex-1 flex-col @if(!request()->routeIs('inventory.products')) pb-12 @endif">
                    {{ $slot }}
                </div>
            </main>
        </div>

        @livewireScripts
        @include('partials.livewire-subdirectory-update-uri')
    </body>
</html>
