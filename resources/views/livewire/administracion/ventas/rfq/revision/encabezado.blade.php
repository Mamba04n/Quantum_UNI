<div class="erp-page-header flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div>
        <p class="erp-kicker">Control comercial</p>
        <h2 class="erp-title">Revision Admin de RFQ</h2>
        <p class="erp-subtitle">Autoriza, solicita cambios o rechaza RFQ antes de enviarlos a proveedores.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.approvals') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 hover:text-gray-900">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7"/></svg>
            Volver a todas las aprobaciones
        </a>
        <a href="{{ route('sales.quotations.phase') }}" class="erp-button-secondary">Ir a Fase de Cotización</a>
    </div>
</div>
