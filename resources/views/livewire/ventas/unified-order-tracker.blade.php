<div class="p-4 font-sans bg-slate-50 min-h-screen">
    {{-- Encabezado --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Seguimiento maestro de ordenes</h2>
            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest">Flujo Unificado: Cotización → Pedido → Entrega</p>
        </div>
        <div class="flex items-center gap-4">
            <div class="flex bg-gray-200/50 p-1 rounded-xl border border-gray-200">
                <button wire:click="setViewMode('kanban')" data-testid="tracker-kanban-view-button" class="px-3 py-1.5 text-[11px] font-bold rounded-lg transition-all {{ $viewMode === 'kanban' ? 'bg-white shadow-sm text-slate-800' : 'text-gray-500 hover:text-slate-700' }}">
                    <svg class="w-4 h-4 inline-block mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path></svg>
                    Tablero
                </button>
                <button wire:click="setViewMode('list')" data-testid="tracker-list-view-button" class="px-3 py-1.5 text-[11px] font-bold rounded-lg transition-all {{ $viewMode === 'list' ? 'bg-white shadow-sm text-slate-800' : 'text-gray-500 hover:text-slate-700' }}">
                    <svg class="w-4 h-4 inline-block mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                    Lista
                </button>
            </div>

            @if(session('message'))
                <div class="bg-slate-800 text-white text-[10px] font-black px-4 py-2 rounded-xl shadow-lg animate-in slide-in-from-top duration-300">
                    {{ session('message') }}
                </div>
            @endif
        </div>
    </div>

    @if($viewMode === 'kanban')
        @include('livewire.ventas.partials.tracker.kanban-board')
    @else
        @include('livewire.ventas.partials.tracker.list-view')
    @endif

    <livewire:ventas.tracker-detail-modal />

</div>
