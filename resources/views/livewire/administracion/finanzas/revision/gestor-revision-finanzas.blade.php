<div class="erp-page">
    <div class="erp-shell max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @include('livewire.administracion.finanzas.revision.encabezado')

        @if(session()->has('message'))
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('message') }}</span>
            </div>
        @endif

        @if(!$selectedRequest)
            @include('livewire.administracion.finanzas.revision.listado-pendientes')
        @else
            @include('livewire.administracion.finanzas.revision.detalle-revision')
        @endif
    </div>
</div>
