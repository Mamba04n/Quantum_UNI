<div class="p-6 font-sans sm:p-8">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Banco de precios de proveedores</h1>
            <p class="mt-0.5 text-sm text-gray-500">Cotizaciones e historial en USD. Confirma precio vigente y disponibilidad antes de comprar.</p>
        </div>
        @if($showForm || $detail)
            <button type="button" wire:click="back" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Volver al historial</button>
        @else
            @can('create', \App\Models\SupplierPriceQuotation::class)
                <button type="button" wire:click="create" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>Registrar cotización
                </button>
            @endcan
        @endif
    </div>

    @if($notice)<p role="status" class="mb-4 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700">{{ $notice }}</p>@endif
    @if($showForm)
        @include('livewire.compras.supplier-price-book-form')
    @else
        @if($detail)
            <section class="mb-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm" aria-label="Detalle de cotización">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 px-5 py-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ $detail->supplier->name }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $detail->reference ?: 'Sin referencia del proveedor' }} · {{ $detail->quoted_at->format('d/m/Y') }} · USD</p>
                        @if($detail->valid_until)<p class="mt-1 text-sm {{ $detail->valid_until->lt(today()) ? 'text-amber-700' : 'text-gray-500' }}">Vigencia indicada: {{ $detail->valid_until->format('d/m/Y') }}{{ $detail->valid_until->lt(today()) ? ' · Vencida' : '' }}</p>@endif
                    </div>
                    @if($detail->attachment_path)
                        @can('document', $detail)<a href="{{ route('purchases.supplier-prices.document', $detail) }}" class="text-sm font-semibold text-slate-700 underline">Descargar oferta original</a>@endcan
                    @endif
                </div>
                @if($detail->notes)<p class="whitespace-pre-line px-5 py-3 text-sm text-gray-600">{{ $detail->notes }}</p>@endif
                @include('livewire.compras.supplier-price-book-table', ['rows' => $detail->items, 'inDetail' => true])
                <p class="border-t border-gray-100 px-5 py-3 text-right text-sm font-semibold text-gray-900">Total de partidas: US${{ number_format($detail->items->sum('total_price'), 2) }}</p>
            </section>
            @if($checkingId)
                <form wire:submit="confirm" class="mb-6 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 text-base font-semibold text-gray-900">Consultar disponibilidad · {{ $detail->items->firstWhere('id', $checkingId)?->part_number }}</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label for="availability" class="mb-1 block text-sm font-medium text-gray-700">Respuesta del proveedor</label><select id="availability" wire:model="availability" class="w-full rounded-xl border-gray-200 text-sm focus:border-slate-400 focus:ring-slate-200"><option value="pending">Por confirmar</option><option value="available">Disponible al consultar</option><option value="unavailable">No disponible al consultar</option></select>@error('availability')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                        <div><label for="availability-notes" class="mb-1 block text-sm font-medium text-gray-700">Contacto y observaciones</label><input id="availability-notes" wire:model="availabilityNotes" maxlength="2000" class="w-full rounded-xl border-gray-200 text-sm focus:border-slate-400 focus:ring-slate-200">@error('notes')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-3"><button type="submit" wire:loading.attr="disabled" wire:target="confirm" class="rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-50"><span wire:loading.remove wire:target="confirm">Guardar consulta</span><span wire:loading wire:target="confirm">Guardando…</span></button><button type="button" wire:click="cancelCheck" class="px-3 py-2 text-sm text-gray-600">Cancelar</button><span class="text-sm text-gray-500">Se registrará la fecha y el responsable de esta consulta.</span></div>
                </form>
            @endif
        @else
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end">
                <div class="w-full lg:max-w-sm"><label for="price-search" class="sr-only">Buscar por número de parte o descripción</label><input id="price-search" wire:model.live.debounce.500ms="search" type="search" placeholder="Número de parte o descripción…" class="w-full rounded-xl border-transparent bg-gray-50 px-3 py-2 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200"></div>
                <div><label for="supplier-filter" class="sr-only">Proveedor</label><select id="supplier-filter" wire:model.live="supplierFilter" class="w-full rounded-xl border-gray-200 py-2 text-sm focus:border-slate-400 focus:ring-slate-200"><option value="">Todos los proveedores</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div>
                <details class="text-sm text-gray-600"><summary class="cursor-pointer py-2">Fechas de cotización</summary><div class="mt-2 flex flex-wrap gap-3"><div><label for="date-from" class="block text-sm">Desde</label><input id="date-from" type="date" wire:model.live="dateFrom" class="rounded-xl border-gray-200 text-sm"></div><div><label for="date-to" class="block text-sm">Hasta</label><input id="date-to" type="date" wire:model.live="dateTo" class="rounded-xl border-gray-200 text-sm"></div></div></details>
            </div>
            <p class="mb-3 text-sm text-gray-500">{{ $items->total() }} partidas · Más recientes primero. Los registros anteriores se conservan.</p>
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">@include('livewire.compras.supplier-price-book-table', ['rows' => $items, 'inDetail' => false])</div>
            <div class="mt-4">{{ $items->links() }}</div>
        @endif
    @endif
</div>
