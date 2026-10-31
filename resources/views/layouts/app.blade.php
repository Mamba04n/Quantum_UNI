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
    @php($documentScroll = request()->routeIs('dashboard', 'workspace', 'sales.customer-request.create', 'sales.quotations.phase.customer-request', 'sales.quotations.phase.customer-request.alias', 'sales.quotations.phase', 'sales.quotations.kanban'))
    <body class="quantum-ui bg-slate-50 font-sans antialiased text-slate-900 flex {{ $documentScroll ? 'min-h-screen' : (!request()->routeIs('customer.portal', 'customer.tickets') ? 'h-screen overflow-hidden' : '') }}">

        <!-- Wrapper principal -->
        <div class="flex-1 flex flex-col min-w-0 {{ !$documentScroll && !request()->routeIs('customer.portal', 'customer.tickets', 'login', 'register', 'password.*', 'verification.*') ? 'overflow-hidden' : '' }}">

            <!-- Topbar / Navbar -->
            @if(!request()->routeIs('customer.portal', 'customer.tickets', 'login', 'register', 'password.*', 'verification.*'))
                @include('livewire.layout.topbar')
            @endif

            <!-- Area Principal de Contenido (Scrollable) -->
            <main class="flex-1 min-w-0 bg-slate-50 flex flex-col {{ $documentScroll ? '' : 'overflow-x-hidden overflow-y-auto' }}">
                @if(!request()->routeIs('customer.portal', 'customer.tickets', 'login', 'register', 'password.*', 'verification.*', 'workspace'))
                    @include('livewire.layout.workspace-navigation')
                @endif
                @if (isset($header) && !request()->routeIs('inventory.products'))
                    <header class="border-b border-white/70 bg-white/80 shadow-sm backdrop-blur">
                        <div class="py-4 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <!-- Slot de Livewire / Componente -->
                <div class="flex-1 flex flex-col w-full @if(!request()->routeIs('inventory.products', 'admin.chat')) pb-12 @endif">
                    {{ $slot }}
                </div>
            </main>
        </div>

        <!-- Toast Notifications -->
        <x-toast />
        @livewireScripts
        @include('partials.livewire-subdirectory-update-uri')
        <script>
            // Global listener: Livewire dispatches 'download-file' -> browser opens download
            document.addEventListener('download-file', function (event) {
                var url = event.detail && event.detail[0] ? event.detail[0].url : (event.detail ? event.detail.url : null);
                if (url) { window.open(url, '_blank'); }
            });
            // Interceptar errores de validación y mostrarlos como notificaciones flotantes (toasts)
            document.addEventListener('livewire:init', () => {
                Livewire.hook('request', ({ fail }) => {
                    fail(({ status, preventDefault }) => {
                        const messages = {
                            401: 'Inicia sesión para continuar.',
                            403: 'No tienes permiso para esta acción.',
                            405: 'No se pudo completar la acción. Actualiza la página e intenta de nuevo.',
                            419: 'Tu sesión venció. Actualiza la página para continuar.',
                            429: 'Hay demasiados intentos. Espera un momento e intenta de nuevo.',
                            500: 'No se pudo completar la acción. Intenta de nuevo.',
                            503: 'El sistema está en mantenimiento. Intenta más tarde.',
                        };
                        if (!messages[status]) return;
                        preventDefault();
                        window.dispatchEvent(new CustomEvent('notify', {
                            detail: { type: 'error', message: messages[status] }
                        }));
                        if (status === 419 && window.confirm('Tu sesión venció. ¿Actualizar la página? Se perderán los datos sin guardar.')) {
                            window.location.reload();
                        }
                    });
                });
                
                // Permiso para notificaciones de escritorio
                if ("Notification" in window && Notification.permission !== "granted" && Notification.permission !== "denied") {
                    Notification.requestPermission();
                }
                
                // Escuchar evento de nuevo mensaje
                Livewire.on('notify-new-message', (event) => {
                    const data = event[0] || event;
                    
                    if ("Notification" in window && Notification.permission === "granted") {
                        new Notification(data.title, {
                            body: data.body
                        });
                    }
                    
                    window.dispatchEvent(new CustomEvent('notify', {
                        detail: { type: 'info', message: data.title + ': ' + data.body }
                    }));
                });

                Livewire.hook('commit', ({ fail }) => {
                    fail((failure = {}) => {
                        const { status, response } = failure || {};
                        if (status === 422 && response) {
                            response.clone().json().then(data => {
                                if (data.errors) {
                                    Object.values(data.errors).forEach(errArray => {
                                        errArray.forEach(err => {
                                            window.dispatchEvent(new CustomEvent('notify', {
                                                detail: { type: 'error', message: err }
                                            }));
                                        });
                                    });
                                }
                            }).catch(() => {});
                        }
                    });
                });
            });
        </script>
    </body>
</html>
