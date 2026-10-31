<div class="erp-page-header flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between mb-8">
    <div>
        <p class="erp-kicker text-red-600 font-extrabold tracking-widest text-[11px] uppercase">Control Financiero</p>
        <h2 class="erp-title text-2xl lg:text-3xl font-black text-slate-900 tracking-tight mt-1">Revision Admin de Modificaciones</h2>
        <p class="erp-subtitle text-xs text-slate-500 font-medium mt-1">Autoriza o rechaza cambios en Cuentas por Pagar (CXP) y Cuentas por Cobrar (CXC) antes de que afecten la contabilidad.</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.approvals') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 hover:text-gray-900">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7"/></svg>
            Volver a todas las aprobaciones
        </a>
        <a href="{{ route('finance.cxp') }}" class="erp-button-secondary inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
            Ir a CXP / CXC
        </a>
    </div>
</div>
