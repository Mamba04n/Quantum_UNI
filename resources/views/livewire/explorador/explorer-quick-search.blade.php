<div class="relative hidden w-full md:block" x-data @click.outside="$wire.hideSuggestions()">
    <form wire:submit="submitSearch" role="search" class="relative flex items-center">
        <button type="submit" class="absolute inset-y-0 left-0 z-10 flex items-center pl-3 text-slate-400 transition hover:text-slate-700" aria-label="Buscar en el Explorador">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
        </button>
        <input type="search" wire:model.live.debounce.250ms="search" wire:focus="showSuggestions"
               class="w-full rounded-xl border border-slate-200 bg-white py-1.5 pl-9 {{ $areaTitle ? 'pr-24' : 'pr-4' }} text-xs text-slate-800 placeholder-slate-400 shadow-2xs transition-all focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-200 sm:text-sm"
               placeholder="{{ ($areaTitle && $scope === 'area') ? 'Buscar en '.$areaTitle.'...' : 'Buscar en Quantum...' }}" aria-label="Buscar en el ERP" autocomplete="off">
        @if($areaTitle)
            <div class="absolute inset-y-0 right-1.5 flex items-center">
                <button type="button" wire:click="toggleScope" title="{{ $scope === 'area' ? 'Buscando en '.$areaTitle.' (clic para buscar en todo el ERP)' : 'Buscando en todo el ERP (clic para buscar en '.$areaTitle.')' }}"
                        class="inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-[11px] font-semibold transition-colors {{ $scope === 'area' ? 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200/80' : 'bg-transparent text-slate-400 hover:text-slate-600' }}">
                    <span class="max-w-[72px] truncate">{{ $scope === 'area' ? $areaTitle : 'Todo' }}</span>
                    <svg class="h-2.5 w-2.5 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                </button>
            </div>
        @endif
    </form>

    @if($open)
        <div class="absolute left-0 top-full z-[70] mt-2 w-full overflow-hidden border border-slate-200 bg-white shadow-lg">
            @foreach($navigationResults as $destination => $result)
                <a href="{{ route($destination) }}" class="block border-b border-slate-100 px-4 py-3"><span class="block text-xs text-slate-500">{{ $result['area'] }}</span><span class="block text-sm font-semibold text-slate-900">{{ $result['label'] }}</span></a>
            @endforeach
            @forelse($suggestions as $suggestion)
                <a href="{{ route('explorer.index', ['q' => $search, 'tipo' => $suggestion['type'], 'registro_tipo' => $suggestion['type'], 'registro' => $suggestion['id']]) }}"
                   class="block border-b border-slate-100 px-4 py-3 text-left transition last:border-b-0 hover:bg-slate-50">
                    <span class="block text-xs text-slate-500">{{ $suggestion['category'] }}</span>
                    <span class="mt-0.5 block truncate text-sm font-semibold text-slate-900">{{ $suggestion['reference'] ?: 'Sin referencia' }}</span>
                    @if($suggestion['description'])
                        <span class="mt-0.5 block truncate text-xs text-slate-600">{{ $suggestion['description'] }}</span>
                    @endif
                </a>
            @empty
                @if(!$navigationResults)
                <div class="px-4 py-4">
                    <p class="text-sm font-medium text-slate-700">Sin coincidencias</p>
                    <p class="mt-1 text-xs text-slate-500">Presiona Enter para revisar la búsqueda completa.</p>
                </div>
                @endif
            @endforelse

            @if($suggestions->isNotEmpty())
                <button type="button" wire:click="submitSearch" class="block w-full border-t border-slate-200 bg-slate-50 px-4 py-3 text-left text-xs font-medium text-slate-700 hover:bg-slate-100">
                    Ver todos los resultados para “{{ $search }}”
                </button>
            @endif
        </div>
    @endif
</div>
