<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Quantum Soluciones ERP') }}</title>
        @include('partials.favicons')

        <!-- Fonts (Poppins local como en Portal de Clientes) -->
        <link rel="stylesheet" href="{{ asset('storefront/fonts/poppins.css') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <style>
            [x-cloak] { display: none !important; }
            body, .font-sans, .quantum-ui {
                font-family: Poppins, Figtree, system-ui, -apple-system, sans-serif !important;
            }
        </style>
        <script>
            if (typeof window.erpShell !== 'function') {
                window.erpShell = function() {
                    return {
                        collapsed: false,
                        menuOpen: false,
                        mobile: matchMedia('(max-width: 1023px)').matches,
                        init() {
                            try {
                                this.collapsed = localStorage.getItem('erp.sidebar.collapsed') === 'true';
                            } catch (e) {}
                            this.media = matchMedia('(max-width: 1023px)');
                            this.listener = (e) => {
                                this.mobile = e.matches;
                                if (!this.mobile) this.menuOpen = false;
                            };
                            this.media.addEventListener('change', this.listener);
                        },
                        destroy() {
                            if (this.media && this.listener) this.media.removeEventListener('change', this.listener);
                        },
                        toggleSidebar() {
                            this.collapsed = !this.collapsed;
                            try {
                                localStorage.setItem('erp.sidebar.collapsed', String(this.collapsed));
                            } catch (e) {}
                        },
                        openMenu() {
                            this.menuOpen = true;
                        },
                        closeMenu() {
                            this.menuOpen = false;
                        }
                    };
                };
            }
        </script>
    </head>
    @php
        $isStandaloneAuth = request()->routeIs('customer.portal', 'customer.tickets', 'login', 'register', 'password.*', 'verification.*');
        $documentScroll = request()->routeIs('dashboard', 'workspace', 'sales.customer-request.create', 'sales.quotations.phase.customer-request', 'sales.quotations.phase.customer-request.alias', 'sales.quotations.phase', 'sales.quotations.kanban');
    @endphp
    <body
        x-data="erpShell"
        @keydown.escape.window="closeMenu()"
        class="bg-[#f7f7f8] text-[#1e1e1e] font-sans antialiased min-h-screen flex flex-col"
    >
        @if(!$isStandaloneAuth)
            {{-- Mobile Drawer Overlay --}}
            <div
                x-show="menuOpen"
                x-cloak
                class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden transition-opacity"
                @click="closeMenu()"
                aria-hidden="true"
            ></div>

            <div class="flex flex-1 min-h-screen w-full">
                {{-- Sidebar Desplegable / Colapsable estilo CMMS con estética Portal de Clientes --}}
                @include('livewire.layout.sidebar')

                {{-- Contenedor Principal de Trabajo --}}
                <div class="flex-1 flex flex-col min-w-0 bg-[#f7f7f8] min-h-screen transition-all duration-300">
                    {{-- Topbar estilo Portal de Clientes --}}
                    @include('livewire.layout.topbar')

                    {{-- Área de Contenido --}}
                    <main class="flex-1 min-w-0 bg-[#f7f7f8] flex flex-col {{ $documentScroll ? '' : 'overflow-x-hidden overflow-y-auto' }}">
                        @if(!request()->routeIs('workspace'))
                            @include('livewire.layout.workspace-navigation')
                        @endif

                        @if (isset($header) && !request()->routeIs('inventory.products'))
                            <header class="border-b border-[#e8e8e9] bg-white shadow-sm">
                                <div class="py-4 px-4 sm:px-6 lg:px-8">
                                    {{ $header }}
                                </div>
                            </header>
                        @endif

                        {{-- Slot de Contenido --}}
                        <div class="flex-1 flex flex-col w-full @if(!request()->routeIs('inventory.products', 'admin.chat')) pb-12 @endif">
                            {{ $slot }}
                        </div>
                    </main>
                </div>
            </div>
        @else
            <main class="flex-1 min-w-0">
                {{ $slot }}
            </main>
        @endif

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
