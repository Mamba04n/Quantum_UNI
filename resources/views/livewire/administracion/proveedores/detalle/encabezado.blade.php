<header class="border-b border-slate-200 px-6 py-5">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2"><span class="text-sm font-medium {{ ($selectedSupplier->status ?? 'activo') === 'activo' ? 'text-emerald-700' : 'text-slate-500' }}">{{ str($selectedSupplier->status ?? 'activo')->headline() }}</span>@foreach($selectedSupplier->quality_types ?? [] as $quality)<span class="text-xs font-medium text-slate-500">Calidad {{ $quality }}</span>@endforeach</div>
            <h1 class="mt-1 truncate text-2xl font-semibold text-slate-950">{{ $selectedSupplier->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $selectedSupplier->legal_name ?: 'Razón social no registrada' }}@if($selectedSupplier->rfc_ruc) · RUC {{ $selectedSupplier->rfc_ruc }}@endif</p>
        </div>
        <dl class="grid grid-cols-3 divide-x divide-slate-200 rounded-lg border border-slate-200 bg-slate-50">
            <div class="px-5 py-3"><dt class="text-xs text-slate-500">Marcas</dt><dd class="mt-1 text-lg font-semibold tabular-nums text-slate-950">{{ $selectedSupplier->brands->count() }}</dd></div>
            <div class="px-5 py-3"><dt class="text-xs text-slate-500">RFQ</dt><dd class="mt-1 text-lg font-semibold tabular-nums text-slate-950">{{ $selectedSupplier->supplierBidResponses->count() + $selectedSupplier->supplierBids->count() }}</dd></div>
            <div class="px-5 py-3"><dt class="text-xs text-slate-500">Órdenes</dt><dd class="mt-1 text-lg font-semibold tabular-nums text-slate-950">{{ $selectedSupplier->purchaseOrders->count() }}</dd></div>
        </dl>
    </div>
</header>
