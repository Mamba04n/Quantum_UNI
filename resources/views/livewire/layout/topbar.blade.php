@php
    $topNav = app(\App\Support\WorkspaceNavigation::class);
    $allUserAreas = $topNav->areas(auth()->user());
    $activeAreaKey = $topNav->current($allUserAreas, request()->route()?->getName());
@endphp
<header class="relative z-50 flex shrink-0 flex-col border-b border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-2 sm:px-8 border-b border-gray-100 lg:grid lg:grid-cols-[1fr_auto_1fr]">
        <div class="flex min-w-0 basis-full items-center gap-1.5 sm:basis-0 sm:flex-1 sm:gap-3 lg:contents">
            <div class="flex shrink-0 items-center">
                <a href="{{ route('workspace') }}" aria-label="QuantumComplete" class="flex shrink-0 items-center transition-opacity hover:opacity-80">
                    <img src="{{ asset('storefront/images/logo.png') }}" alt="QuantumComplete" class="h-8 w-auto object-contain">
                </a>
            </div>
            <!-- Áreas principales arriba -->
            <div class="mx-auto flex items-center justify-center gap-1">
            <nav aria-label="Áreas de trabajo" class="hidden items-center justify-center gap-1 lg:flex">
                @foreach(['ventas' => 'Ventas', 'bodega' => 'Bodega', 'finanzas' => 'Finanzas'] as $key => $label)
                    @continue(!isset($allUserAreas[$key]))
                    @php
                        $isAreaActive = ($activeAreaKey === $key);
                    @endphp
                    <a href="{{ route('workspace', $key) }}"
                       @if($isAreaActive) aria-current="page" @endif
                       class="inline-flex max-w-full items-center justify-center rounded-xl px-2.5 py-1.5 text-center text-sm transition-colors {{ $isAreaActive ? 'bg-[#6f1220] font-semibold text-white shadow-sm' : 'font-medium text-slate-600 hover:bg-rose-50 hover:text-[#6f1220]' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            @include('livewire.layout.areas-menu')
            </div>

            @unless(request()->routeIs('workspace') && request()->route('area'))
            <a href="{{ route('explorer.index', $activeAreaKey ? ['area' => $activeAreaKey] : []) }}" aria-label="Buscar en el ERP" class="shrink-0 rounded-lg p-2 text-slate-600 hover:bg-gray-50 md:hidden ml-auto">
                <x-workspace-icon name="search"/>
            </a>
            @endunless
        </div>

        <div class="ml-auto flex items-center justify-end gap-1.5 sm:gap-2 shrink-0 sm:border-l sm:border-slate-200 sm:pl-3">
        <livewire:layout.erp-notifications />

        <!-- Messenger Dropdown -->
        @if(!request()->routeIs('customer.portal', 'customer.tickets'))
            @livewire('admin.internal-chat-dropdown')
        @endif

        <!-- Menu de usuario -->
        <div class="ms-3 relative">
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button type="button" data-testid="user-menu-trigger" class="inline-flex items-center gap-2 rounded-xl border border-slate-200/80 bg-white px-3 py-2 text-sm font-semibold leading-4 text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none">
                        <span aria-hidden="true" class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-xs font-semibold text-[#6f1220]">{{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 1)) }}</span><div class="max-w-24 truncate sm:max-w-48" x-data="{{ json_encode(['name' => auth()->user()->name ?? 'Usuario']) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name">{{ auth()->user()->name ?? 'Usuario' }}</div>

                        <div class="ms-1">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </button>
                </x-slot>

                <x-slot name="content">
                    @can('full_access')
                    <x-dropdown-link :href="route('profile')">
                        Perfil
                    </x-dropdown-link>
                    @endcan
                    <x-dropdown-link :href="url('/')" target="_blank" rel="noopener">Ver tienda online</x-dropdown-link>

                    <!-- Cierre de sesion -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" data-testid="logout-submit" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">
                            Cerrar sesion
                        </button>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
    </div>
    @unless(request()->routeIs('workspace') && request()->route('area'))
    <div class="flex min-w-0 flex-col gap-2 bg-slate-50/80 px-4 py-2 sm:px-8 md:flex-row md:items-center md:gap-4">
        @include('livewire.layout.area-modules-navigation')
        <div class="hidden w-full shrink-0 md:block md:ml-auto md:w-80 lg:w-96 [&>div]:w-full">
            @livewire('explorador.explorer-quick-search', ['area' => $activeAreaKey])
        </div>
    </div>
    @endunless
</header>
