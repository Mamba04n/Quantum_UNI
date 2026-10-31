<div class="p-6 font-sans sm:p-8">
    {{-- Notificaciones flash --}}
    @if (session()->has('message'))
        <div class="mb-5 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ session('message') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-700 hover:text-emerald-900">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-5 flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-rose-700 hover:text-rose-900">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    {{-- Encabezado de página canónico --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">
                Flujo financiero y balance
            </h1>
            <p class="mt-0.5 text-sm text-gray-500">
                Control unificado de ingresos, egresos, cartera por cobrar y cuentas por pagar.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <button
                type="button"
                wire:click="$set('showDirectIncomeModal', true)"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 shadow-xs transition-colors hover:bg-emerald-100"
            >
                <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Registrar ingreso
            </button>
            <button
                type="button"
                wire:click="$set('showDirectExpenseModal', true)"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900"
            >
                <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                </svg>
                Registrar egreso / gasto
            </button>
        </div>
    </div>

    {{-- Tarjetas KPI en formato totalmente horizontal (uno al lado del otro, en una sola línea) --}}
    <div class="mb-6 overflow-x-auto pb-1">
        <div class="flex flex-row items-stretch gap-3 w-full min-w-[780px] lg:min-w-0" style="display: flex; flex-direction: row; gap: 0.75rem; width: 100%;">
            {{-- 1. Total Ingresos --}}
            <div class="flex-1 min-w-0 flex items-center justify-between gap-2 rounded-xl border border-emerald-100 bg-white px-3.5 py-2.5 shadow-xs" style="flex: 1 1 0%; min-width: 0;">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
                        </svg>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 truncate">Ingresos</span>
                </div>
                <span class="text-sm font-bold text-gray-900 shrink-0">C$ {{ number_format($totalIngresos, 2) }}</span>
            </div>

            {{-- 2. Total Egresos --}}
            <div class="flex-1 min-w-0 flex items-center justify-between gap-2 rounded-xl border border-rose-100 bg-white px-3.5 py-2.5 shadow-xs" style="flex: 1 1 0%; min-width: 0;">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6" />
                        </svg>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-rose-700 truncate">Egresos</span>
                </div>
                <span class="text-sm font-bold text-gray-900 shrink-0">C$ {{ number_format($totalEgresos, 2) }}</span>
            </div>

            {{-- 3. Balance Neto --}}
            <div class="flex-1 min-w-0 flex items-center justify-between gap-2 rounded-xl border border-gray-100 bg-white px-3.5 py-2.5 shadow-xs" style="flex: 1 1 0%; min-width: 0;">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-600 truncate">Balance neto</span>
                </div>
                <span class="text-sm font-bold shrink-0 {{ $balanceNeto >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">C$ {{ number_format($balanceNeto, 2) }}</span>
            </div>

            {{-- 4. Por Cobrar (CXC) --}}
            <div class="flex-1 min-w-0 flex items-center justify-between gap-2 rounded-xl border border-amber-100 bg-white px-3.5 py-2.5 shadow-xs" style="flex: 1 1 0%; min-width: 0;">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-amber-700 truncate">Por cobrar</span>
                </div>
                <span class="text-sm font-bold text-gray-900 shrink-0">C$ {{ number_format($totalPorCobrar, 2) }}</span>
            </div>

            {{-- 5. Por Pagar (CXP) --}}
            <div class="flex-1 min-w-0 flex items-center justify-between gap-2 rounded-xl border border-indigo-100 bg-white px-3.5 py-2.5 shadow-xs" style="flex: 1 1 0%; min-width: 0;">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-indigo-700 truncate">Por pagar</span>
                </div>
                <span class="text-sm font-bold text-gray-900 shrink-0">C$ {{ number_format($totalPorPagar, 2) }}</span>
            </div>
        </div>
    </div>

    {{-- Navegación por pestañas y Buscador --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="inline-flex rounded-xl bg-gray-100 p-1">
            <button
                type="button"
                wire:click="setTab('resumen')"
                class="rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-all {{ $tab === 'resumen' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-900' }}"
            >
                Resumen general
            </button>
            <button
                type="button"
                wire:click="setTab('ingresos')"
                class="rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-all {{ $tab === 'ingresos' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-900' }}"
            >
                Ingresos
            </button>
            <button
                type="button"
                wire:click="setTab('egresos')"
                class="rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-all {{ $tab === 'egresos' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-900' }}"
            >
                Egresos
            </button>
            <button
                type="button"
                wire:click="setTab('cxc')"
                class="rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-all {{ $tab === 'cxc' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-900' }}"
            >
                Por cobrar (CXC)
            </button>
            <button
                type="button"
                wire:click="setTab('cxp')"
                class="rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-all {{ $tab === 'cxp' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-500 hover:text-gray-900' }}"
            >
                Por pagar (CXP)
            </button>
        </div>

        <div class="relative w-full max-w-sm">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Buscar por código, ref, cliente o notas..."
                class="w-full rounded-xl border-transparent bg-gray-50 px-3.5 py-2 pl-9 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200"
            />
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            @if ($search)
                <button
                    type="button"
                    wire:click="$set('search', '')"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            @endif
        </div>
    </div>

    {{-- Contenido según la pestaña activa --}}

    {{-- 1. TAB: RESUMEN (Vista simultánea Ingresos y Egresos recientes) --}}
    @if ($tab === 'resumen')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Columna Ingresos Recientes --}}
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50/60 px-5 py-3.5">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                        <h2 class="text-sm font-bold text-gray-900">Ingresos recientes</h2>
                    </div>
                    <button type="button" wire:click="setTab('ingresos')" class="text-xs font-semibold text-emerald-700 hover:text-emerald-900">
                        Ver todos &rarr;
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-left text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-2.5 font-semibold">Fecha</th>
                                <th class="px-5 py-2.5 font-semibold">Concepto / Origen</th>
                                <th class="px-5 py-2.5 font-semibold">Método</th>
                                <th class="px-5 py-2.5 text-right font-semibold">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($incomes as $item)
                                <tr class="transition-colors hover:bg-gray-50/50">
                                    <td class="px-5 py-2.5 text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($item->payment_date)->format('d/m/Y') }}
                                    </td>
                                    <td class="px-5 py-2.5">
                                        <p class="text-xs font-semibold text-gray-900">
                                            {{ $item->accountReceivable?->entity?->name ?: ($item->receipt_reference ?: $item->payment_code) }}
                                        </p>
                                        <p class="text-xs text-gray-500 truncate max-w-xs">{{ $item->notes ?: 'Cobro de cliente' }}</p>
                                    </td>
                                    <td class="px-5 py-2.5 text-xs text-gray-600">
                                        {{ $item->payment_method }}
                                    </td>
                                    <td class="px-5 py-2.5 text-right font-semibold text-emerald-600">
                                        +C$ {{ number_format($item->amount, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-6 text-center text-xs text-gray-400">
                                        No hay ingresos registrados recientemente.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Columna Egresos Recientes --}}
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50/60 px-5 py-3.5">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-2 w-2 rounded-full bg-rose-500"></span>
                        <h2 class="text-sm font-bold text-gray-900">Egresos recientes</h2>
                    </div>
                    <button type="button" wire:click="setTab('egresos')" class="text-xs font-semibold text-rose-700 hover:text-rose-900">
                        Ver todos &rarr;
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-left text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-2.5 font-semibold">Fecha</th>
                                <th class="px-5 py-2.5 font-semibold">Concepto / Destino</th>
                                <th class="px-5 py-2.5 font-semibold">Método</th>
                                <th class="px-5 py-2.5 text-right font-semibold">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($expenses as $item)
                                <tr class="transition-colors hover:bg-gray-50/50">
                                    <td class="px-5 py-2.5 text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($item->payment_date)->format('d/m/Y') }}
                                    </td>
                                    <td class="px-5 py-2.5">
                                        <p class="text-xs font-semibold text-gray-900">
                                            {{ $item->accountPayable?->entity?->name ?: ($item->receipt_reference ?: $item->payment_code) }}
                                        </p>
                                        <p class="text-xs text-gray-500 truncate max-w-xs">{{ $item->notes ?: 'Pago a proveedor' }}</p>
                                    </td>
                                    <td class="px-5 py-2.5 text-xs text-gray-600">
                                        {{ $item->payment_method }}
                                    </td>
                                    <td class="px-5 py-2.5 text-right font-semibold text-rose-600">
                                        -C$ {{ number_format($item->amount, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-6 text-center text-xs text-gray-400">
                                        No hay egresos registrados recientemente.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- 2. TAB: INGRESOS (Tabla completa paginada) --}}
    @if ($tab === 'ingresos')
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Código / Fecha</th>
                            <th class="px-5 py-3 font-semibold">Cliente / Origen</th>
                            <th class="px-5 py-3 font-semibold">Concepto / Notas</th>
                            <th class="px-5 py-3 font-semibold">Método / Ref</th>
                            <th class="px-5 py-3 text-right font-semibold">Monto Original</th>
                            <th class="px-5 py-3 text-right font-semibold">Total (NIO)</th>
                            <th class="px-5 py-3 text-center font-semibold">Soporte</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($incomes as $item)
                            <tr class="transition-colors hover:bg-gray-50/50">
                                <td class="px-5 py-3">
                                    <span class="font-mono text-xs font-bold text-gray-900">{{ $item->payment_code }}</span>
                                    <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($item->payment_date)->format('d/m/Y') }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-xs font-semibold text-gray-900">
                                        {{ $item->accountReceivable?->entity?->name ?: 'Ingreso Directo' }}
                                    </span>
                                    @if ($item->accountReceivable)
                                        <p class="text-xs text-gray-400">Cuenta: {{ $item->accountReceivable->receivable_code }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <p class="max-w-xs truncate text-xs text-gray-700" title="{{ $item->notes }}">
                                        {{ $item->notes ?: 'Cobro de cliente' }}
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-600">
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 font-medium text-gray-800">
                                        {{ $item->payment_method }}
                                    </span>
                                    <p class="mt-0.5 text-xs text-gray-400">Ref: {{ $item->payment_reference ?: 'S/R' }}</p>
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-gray-700">
                                    {{ $item->currency }} {{ number_format($item->amount_original ?: $item->amount, 2) }}
                                </td>
                                <td class="px-5 py-3 text-right font-bold text-emerald-600">
                                    C$ {{ number_format($item->amount, 2) }}
                                </td>
                                <td class="px-5 py-3 text-center">
                                    @if ($item->payment_evidence_path)
                                        <a
                                            href="{{ route('finance.documents.download', ['type' => 'payments', 'id' => $item->id]) }}"
                                            target="_blank"
                                            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50"
                                            title="Descargar comprobante"
                                        >
                                            <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                            </svg>
                                            Ver
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-sm text-gray-500">
                                    No se encontraron ingresos registrados con los filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($incomes instanceof \Illuminate\Pagination\LengthAwarePaginator && $incomes->hasPages())
                <div class="border-t border-gray-100 p-4">
                    {{ $incomes->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- 3. TAB: EGRESOS (Tabla completa paginada) --}}
    @if ($tab === 'egresos')
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Código / Fecha</th>
                            <th class="px-5 py-3 font-semibold">Proveedor / Beneficiario</th>
                            <th class="px-5 py-3 font-semibold">Concepto / Categoría</th>
                            <th class="px-5 py-3 font-semibold">Método / Ref</th>
                            <th class="px-5 py-3 text-right font-semibold">Monto Original</th>
                            <th class="px-5 py-3 text-right font-semibold">Total (NIO)</th>
                            <th class="px-5 py-3 text-center font-semibold">Soporte</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($expenses as $item)
                            <tr class="transition-colors hover:bg-gray-50/50">
                                <td class="px-5 py-3">
                                    <span class="font-mono text-xs font-bold text-gray-900">{{ $item->payment_code }}</span>
                                    <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($item->payment_date)->format('d/m/Y') }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-xs font-semibold text-gray-900">
                                        {{ $item->accountPayable?->entity?->name ?: 'Egreso Operativo' }}
                                    </span>
                                    @if ($item->accountPayable)
                                        <p class="text-xs text-gray-400">Cuenta: {{ $item->accountPayable->payable_code }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <p class="max-w-xs truncate text-xs text-gray-700" title="{{ $item->notes }}">
                                        {{ $item->notes ?: 'Pago a proveedor' }}
                                    </p>
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-600">
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 font-medium text-gray-800">
                                        {{ $item->payment_method }}
                                    </span>
                                    <p class="mt-0.5 text-xs text-gray-400">Ref: {{ $item->payment_reference ?: 'S/R' }}</p>
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-gray-700">
                                    {{ $item->currency }} {{ number_format($item->amount_original ?: $item->amount, 2) }}
                                </td>
                                <td class="px-5 py-3 text-right font-bold text-rose-600">
                                    -C$ {{ number_format($item->amount, 2) }}
                                </td>
                                <td class="px-5 py-3 text-center">
                                    @if ($item->payment_evidence_path)
                                        <a
                                            href="{{ route('finance.documents.download', ['type' => 'payments', 'id' => $item->id]) }}"
                                            target="_blank"
                                            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50"
                                            title="Descargar comprobante"
                                        >
                                            <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                            </svg>
                                            Ver
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-sm text-gray-500">
                                    No se encontraron egresos registrados con los filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($expenses instanceof \Illuminate\Pagination\LengthAwarePaginator && $expenses->hasPages())
                <div class="border-t border-gray-100 p-4">
                    {{ $expenses->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- 4. TAB: CUENTAS POR COBRAR (CXC con Abono directo) --}}
    @if ($tab === 'cxc')
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Código / Factura</th>
                            <th class="px-5 py-3 font-semibold">Cliente</th>
                            <th class="px-5 py-3 font-semibold">Vencimiento</th>
                            <th class="px-5 py-3 text-right font-semibold">Monto Total</th>
                            <th class="px-5 py-3 text-right font-semibold">Cobrado</th>
                            <th class="px-5 py-3 text-right font-semibold">Saldo Pendiente</th>
                            <th class="px-5 py-3 text-center font-semibold">Estado</th>
                            <th class="px-5 py-3 text-center font-semibold">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($receivables as $item)
                            @php
                                $saldo = (float) $item->total_amount - (float) $item->paid_amount;
                            @endphp
                            <tr class="transition-colors hover:bg-gray-50/50">
                                <td class="px-5 py-3">
                                    <span class="font-mono text-xs font-bold text-gray-900">{{ $item->receivable_code }}</span>
                                    @if ($item->invoice_number)
                                        <p class="text-xs text-gray-500">Factura: {{ $item->invoice_number }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-xs font-semibold text-gray-900">{{ $item->entity?->name ?: 'Cliente no especificado' }}</span>
                                    <p class="text-xs text-gray-400 truncate max-w-xs">{{ $item->concept }}</p>
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-600">
                                    {{ $item->due_date ? \Carbon\Carbon::parse($item->due_date)->format('d/m/Y') : 'Sin fecha' }}
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-gray-700">
                                    {{ $item->currency }} {{ number_format($item->total_amount, 2) }}
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-emerald-600 font-medium">
                                    {{ $item->currency }} {{ number_format($item->paid_amount, 2) }}
                                </td>
                                <td class="px-5 py-3 text-right text-xs font-bold text-gray-900">
                                    {{ $item->currency }} {{ number_format(max(0, $saldo), 2) }}
                                </td>
                                <td class="px-5 py-3 text-center">
                                    @if ($item->status === 'pagado')
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                            Pagado
                                        </span>
                                    @elseif ($saldo < $item->total_amount && $saldo > 0)
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                            Abonado
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">
                                            Pendiente
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-center">
                                    @if ($saldo > 0.01)
                                        <button
                                            type="button"
                                            wire:click="openCxcModal({{ $item->id }})"
                                            class="inline-flex items-center gap-1 rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white shadow-xs transition-colors hover:bg-slate-900"
                                        >
                                            <svg class="h-3.5 w-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Abonar / Cobrar
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">Completado</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-8 text-center text-sm text-gray-500">
                                    No se encontraron cuentas por cobrar con los filtros actuales.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($receivables instanceof \Illuminate\Pagination\LengthAwarePaginator && $receivables->hasPages())
                <div class="border-t border-gray-100 p-4">
                    {{ $receivables->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- 5. TAB: CUENTAS POR PAGAR (CXP con Abono directo) --}}
    @if ($tab === 'cxp')
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Código / Factura</th>
                            <th class="px-5 py-3 font-semibold">Proveedor</th>
                            <th class="px-5 py-3 font-semibold">Vencimiento</th>
                            <th class="px-5 py-3 text-right font-semibold">Monto Total</th>
                            <th class="px-5 py-3 text-right font-semibold">Pagado</th>
                            <th class="px-5 py-3 text-right font-semibold">Saldo Pendiente</th>
                            <th class="px-5 py-3 text-center font-semibold">Estado</th>
                            <th class="px-5 py-3 text-center font-semibold">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($payables as $item)
                            @php
                                $saldo = (float) $item->total_amount - (float) $item->paid_amount;
                            @endphp
                            <tr class="transition-colors hover:bg-gray-50/50">
                                <td class="px-5 py-3">
                                    <span class="font-mono text-xs font-bold text-gray-900">{{ $item->payable_code }}</span>
                                    @if ($item->invoice_number)
                                        <p class="text-xs text-gray-500">Factura: {{ $item->invoice_number }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-xs font-semibold text-gray-900">{{ $item->entity?->name ?: ($item->supplier?->company_name ?: 'Proveedor') }}</span>
                                    <p class="text-xs text-gray-400 truncate max-w-xs">{{ $item->concept }}</p>
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-600">
                                    {{ $item->due_date ? \Carbon\Carbon::parse($item->due_date)->format('d/m/Y') : 'Sin fecha' }}
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-gray-700">
                                    {{ $item->currency }} {{ number_format($item->total_amount, 2) }}
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-indigo-600 font-medium">
                                    {{ $item->currency }} {{ number_format($item->paid_amount, 2) }}
                                </td>
                                <td class="px-5 py-3 text-right text-xs font-bold text-gray-900">
                                    {{ $item->currency }} {{ number_format(max(0, $saldo), 2) }}
                                </td>
                                <td class="px-5 py-3 text-center">
                                    @if ($item->status === 'pagado')
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                            Liquidado
                                        </span>
                                    @elseif ($saldo < $item->total_amount && $saldo > 0)
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                            Abonado
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">
                                            Por Pagar
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-center">
                                    @if ($saldo > 0.01)
                                        <button
                                            type="button"
                                            wire:click="openCxpModal({{ $item->id }})"
                                            class="inline-flex items-center gap-1 rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white shadow-xs transition-colors hover:bg-slate-900"
                                        >
                                            <svg class="h-3.5 w-3.5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            Pagar / Abonar
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">Liquidado</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-8 text-center text-sm text-gray-500">
                                    No se encontraron cuentas por pagar con los filtros actuales.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($payables instanceof \Illuminate\Pagination\LengthAwarePaginator && $payables->hasPages())
                <div class="border-t border-gray-100 p-4">
                    {{ $payables->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- MODAL 1: REGISTRAR INGRESO DIRECTO --}}
    @if ($showDirectIncomeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
            <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" @click.outside="$wire.set('showDirectIncomeModal', false)">
                <div class="mb-4 flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Registrar Ingreso</h3>
                    <button type="button" wire:click="$set('showDirectIncomeModal', false)" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit.prevent="saveDirectIncome">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700">Concepto o Descripción</label>
                            <input type="text" wire:model="direct_income_concept" placeholder="Ej: Venta de mostrador, Anticipo de servicio..." class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                            @error('direct_income_concept') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Monto</label>
                                <input type="number" step="0.01" wire:model="direct_income_amount" placeholder="0.00" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                                @error('direct_income_amount') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Moneda</label>
                                <select wire:model="direct_income_currency" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800">
                                    <option value="NIO">NIO (Córdobas)</option>
                                    <option value="USD">USD (Dólares)</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Método de Cobro</label>
                                <select wire:model="direct_income_method" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800">
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Tarjeta">Tarjeta</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Fecha</label>
                                <input type="date" wire:model="direct_income_date" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                                @error('direct_income_date') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">N° Referencia / Recibo</label>
                                <input type="text" wire:model="direct_income_reference" placeholder="Ej: TRF-83921" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                                @error('direct_income_reference') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Cliente / Pagador (Opcional)</label>
                                <input type="text" wire:model="direct_income_party" placeholder="Nombre o empresa" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">Comprobante de ingreso (Opcional)</label>
                            <input type="file" wire:model="direct_income_evidence" class="mt-1 w-full text-xs text-gray-500 file:mr-3 file:rounded-xl file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-200" />
                            @error('direct_income_evidence') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-2.5 border-t border-gray-100 pt-4">
                        <button type="button" wire:click="$set('showDirectIncomeModal', false)" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-slate-900">
                            Guardar ingreso
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL 2: REGISTRAR EGRESO / GASTO --}}
    @if ($showDirectExpenseModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
            <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" @click.outside="$wire.set('showDirectExpenseModal', false)">
                <div class="mb-4 flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Registrar Egreso / Gasto</h3>
                    <button type="button" wire:click="$set('showDirectExpenseModal', false)" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit.prevent="saveDirectExpense">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-xs font-semibold text-gray-700">Categoría</label>
                                <select wire:model="direct_expense_category" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800">
                                    <option value="Gasto operativo">Gasto operativo</option>
                                    <option value="Servicios básicos">Servicios básicos (Luz/Agua/Internet)</option>
                                    <option value="Nómina y personal">Nómina y personal</option>
                                    <option value="Logística y fletes">Logística y fletes</option>
                                    <option value="Mantenimiento">Mantenimiento de equipo/local</option>
                                    <option value="Impuestos y tasas">Impuestos y tasas</option>
                                    <option value="Otros egresos">Otros egresos</option>
                                </select>
                            </div>
                            <div class="col-span-2 sm:col-span-1">
                                <label class="block text-xs font-semibold text-gray-700">Beneficiario / Proveedor (Opcional)</label>
                                <input type="text" wire:model="direct_expense_party" placeholder="Ej: Disnorte, Claro..." class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">Concepto o Detalle</label>
                            <input type="text" wire:model="direct_expense_concept" placeholder="Ej: Pago factura de internet mensual..." class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                            @error('direct_expense_concept') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Monto</label>
                                <input type="number" step="0.01" wire:model="direct_expense_amount" placeholder="0.00" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                                @error('direct_expense_amount') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Moneda</label>
                                <select wire:model="direct_expense_currency" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800">
                                    <option value="NIO">NIO (Córdobas)</option>
                                    <option value="USD">USD (Dólares)</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Método de Pago</label>
                                <select wire:model="direct_expense_method" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800">
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Tarjeta">Tarjeta de débito/crédito</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Fecha</label>
                                <input type="date" wire:model="direct_expense_date" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                                @error('direct_expense_date') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">N° Referencia / Factura soporte</label>
                            <input type="text" wire:model="direct_expense_reference" placeholder="Ej: FAC-001923 o TRF-4819" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                            @error('direct_expense_reference') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">Comprobante o factura recibida (Opcional)</label>
                            <input type="file" wire:model="direct_expense_evidence" class="mt-1 w-full text-xs text-gray-500 file:mr-3 file:rounded-xl file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-200" />
                            @error('direct_expense_evidence') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-2.5 border-t border-gray-100 pt-4">
                        <button type="button" wire:click="$set('showDirectExpenseModal', false)" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-slate-900">
                            Guardar egreso
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL 3: REGISTRAR ABONO / COBRO CXC --}}
    @if ($showCxcModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
            <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" @click.outside="$wire.set('showCxcModal', false)">
                <div class="mb-4 flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Registrar Cobro / Abono</h3>
                        <p class="text-xs text-gray-500">Acreditar a la cuenta por cobrar de cliente</p>
                    </div>
                    <button type="button" wire:click="$set('showCxcModal', false)" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit.prevent="saveCxcPayment">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Monto del abono</label>
                                <input type="number" step="0.01" wire:model="cxc_amount" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                                @error('cxc_amount') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Moneda</label>
                                <select wire:model="cxc_currency" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800">
                                    <option value="NIO">NIO</option>
                                    <option value="USD">USD</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Método</label>
                                <select wire:model="cxc_method" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800">
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Tarjeta">Tarjeta</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Fecha del cobro</label>
                                <input type="date" wire:model="cxc_date" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                                @error('cxc_date') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">Referencia de transacción / recibo</label>
                            <input type="text" wire:model="cxc_reference" placeholder="Ej: DEP-8912739" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                            @error('cxc_reference') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">Comprobante de depósito / transferencia (Obligatorio)</label>
                            <input type="file" wire:model="cxc_evidence" class="mt-1 w-full text-xs text-gray-500 file:mr-3 file:rounded-xl file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-200" />
                            @error('cxc_evidence') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-2.5 border-t border-gray-100 pt-4">
                        <button type="button" wire:click="$set('showCxcModal', false)" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-slate-900">
                            Confirmar cobro
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MODAL 4: REGISTRAR ABONO / PAGO CXP --}}
    @if ($showCxpModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-xs">
            <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" @click.outside="$wire.set('showCxpModal', false)">
                <div class="mb-4 flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Registrar Pago a Proveedor</h3>
                        <p class="text-xs text-gray-500">Liquidar o abonar a la cuenta por pagar</p>
                    </div>
                    <button type="button" wire:click="$set('showCxpModal', false)" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit.prevent="saveCxpPayment">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Monto del pago</label>
                                <input type="number" step="0.01" wire:model="cxp_amount" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                                @error('cxp_amount') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Moneda</label>
                                <select wire:model="cxp_currency" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800">
                                    <option value="NIO">NIO</option>
                                    <option value="USD">USD</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Método de salida</label>
                                <select wire:model="cxp_method" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800">
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Efectivo">Efectivo</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Fecha del pago</label>
                                <input type="date" wire:model="cxp_date" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                                @error('cxp_date') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">Referencia bancaria / N° Comprobante</label>
                            <input type="text" wire:model="cxp_reference" placeholder="Ej: TRF-PROV-9102" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-slate-800 focus:ring-1 focus:ring-slate-800" />
                            @error('cxp_reference') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700">Comprobante de débito o transferencia (Obligatorio)</label>
                            <input type="file" wire:model="cxp_evidence" class="mt-1 w-full text-xs text-gray-500 file:mr-3 file:rounded-xl file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-200" />
                            @error('cxp_evidence') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-2.5 border-t border-gray-100 pt-4">
                        <button type="button" wire:click="$set('showCxpModal', false)" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-slate-900">
                            Confirmar pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
