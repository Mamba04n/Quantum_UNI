<div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
        <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-400">Aprobacion interna / compra</p>
        <h1 class="mt-2 text-3xl font-black text-slate-950">Orden de compra proveedor</h1>
        <p class="mt-2 text-sm text-slate-500">Edita consignatario, envio y comentarios antes de generar el PDF final para proveedor.</p>
    </div>
    @if($purchaseOrders->count() > 1)
        <div class="flex flex-wrap gap-2">
            @foreach($purchaseOrders as $purchaseOrder)
                <a href="{{ route('admin.approvals.purchase-document', ['approval' => $approval, 'purchase_order' => $purchaseOrder->id]) }}" class="rounded-xl border px-3 py-2 text-xs font-black {{ $purchaseOrder->id === $purchaseOrderId ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                    {{ $purchaseOrder->reference_number }} · {{ $purchaseOrder->supplier?->name ?: 'Proveedor' }}
                </a>
            @endforeach
        </div>
    @endif
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.approvals') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 hover:text-gray-900">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7"/></svg>
            Volver a todas las aprobaciones
        </a>
        @if($pdfUrl)
            <a href="{{ $pdfUrl }}" target="_blank" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-50">Ver PDF</a>
            <a href="{{ $pdfUrl }}?download=1" class="rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white hover:bg-black">Descargar</a>
        @endif
    </div>
</div>
