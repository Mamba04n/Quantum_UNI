@php
    $user = auth()->user();
    $userInitials = mb_strtoupper(mb_substr(trim($user->name ?? 'U'), 0, 2, 'UTF-8'), 'UTF-8');
    $firstName = str($user->name ?? 'Usuario')->before(' ');
@endphp

<header class="sticky top-0 z-40 flex h-16 shrink-0 items-center justify-between border-b border-[#e8e8e9] bg-white px-4 sm:px-6 backdrop-blur transition-all">
    {{-- Izquierda: Botón Menú Hamburguesa (colapsar/expandir en escritorio o abrir en móvil tipo CMMS) --}}
    <div class="flex items-center gap-3 shrink-0">
        <button
            type="button"
            class="p-2 sm:p-2.5 rounded-xl text-gray-500 hover:text-gray-900 hover:bg-gray-100 transition-colors focus:outline-none"
            @click="mobile ? openMenu() : toggleSidebar()"
            :aria-expanded="mobile ? menuOpen : !collapsed"
            aria-label="Colapsar o expandir menú"
            :title="mobile ? 'Abrir menú' : (collapsed ? 'Expandir barra lateral' : 'Contraer barra lateral')"
        >
            <x-portal.icon name="menu" class="w-6 h-6 text-gray-700" />
        </button>
    </div>

    {{-- Centro: Buscador del ERP estilo Portal de Clientes --}}
    <div class="flex items-center justify-center flex-1 max-w-lg mx-3 sm:mx-6">
        <div class="w-full">
            @livewire('explorador.explorer-quick-search')
        </div>
    </div>

    {{-- Derecha: Notificaciones, Tickets, Chat interno y Menú de usuario estilo Portal --}}
    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
        <livewire:layout.erp-notifications />

        @if(!request()->routeIs('customer.portal', 'customer.tickets'))
            @livewire('admin.internal-chat-dropdown')
        @endif

        {{-- Dropdown de Cuenta de Usuario estilo Portal de Clientes --}}
        <div class="relative ms-1">
            <x-dropdown align="right" width="56">
                <x-slot name="trigger">
                    <button
                        type="button"
                        data-testid="user-menu-trigger"
                        class="inline-flex items-center gap-2.5 rounded-full border border-gray-200 bg-white py-1.5 pl-1.5 pr-3 text-sm font-semibold text-gray-700 shadow-sm transition-all hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#b50e0e]/20"
                    >
                        <span aria-hidden="true" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-rose-50 text-xs font-bold text-[#b50e0e]">
                            {{ $userInitials }}
                        </span>
                        <span class="max-w-28 truncate sm:max-w-40 text-xs font-semibold text-gray-800" x-data="{{ json_encode(['name' => $firstName]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name">
                            {{ $firstName }}
                        </span>
                        <x-portal.icon name="chevron-down" class="w-3.5 h-3.5 text-gray-400" />
                    </button>
                </x-slot>

                <x-slot name="content">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <p class="text-xs font-semibold text-gray-900 truncate">{{ $user->name ?? 'Usuario' }}</p>
                        <p class="text-[11px] text-gray-500 truncate">{{ $user->email ?? '' }}</p>
                    </div>

                    @can('full_access')
                    <x-dropdown-link :href="route('profile')">
                        Mi Perfil
                    </x-dropdown-link>
                    @endcan

                    <x-dropdown-link :href="route('customer.portal')" target="_blank" rel="noopener">
                        Ir al Portal de Clientes
                    </x-dropdown-link>

                    <x-dropdown-link :href="url('/')" target="_blank" rel="noopener">
                        Ver Tienda Online
                    </x-dropdown-link>

                    <div class="border-t border-gray-100 my-1"></div>

                    <!-- Cierre de sesión -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            data-testid="logout-submit"
                            class="block w-full px-4 py-2 text-start text-xs font-semibold text-rose-700 hover:bg-rose-50 focus:outline-none transition-colors"
                        >
                            Cerrar sesión
                        </button>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
</header>
