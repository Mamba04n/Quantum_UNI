<div class="relative w-full" x-data @click.outside="$wire.hideSuggestions()">
    <form wire:submit="submitSearch" role="search" class="relative flex items-center w-full rounded-xl border border-[#e8e8e9] bg-[#f7f7f8] px-3.5 py-1.5 transition-all focus-within:border-[#b50e0e] focus-within:bg-white focus-within:ring-1 focus-within:ring-[#b50e0e]/20 shadow-none">
        <button type="submit" class="flex items-center text-gray-400 hover:text-gray-700 transition-colors mr-2.5 shrink-0" aria-label="Buscar en el Explorador">
            <x-portal.icon name="search" class="w-4 h-4" />
        </button>
        <input
            type="search"
            wire:model.live.debounce.250ms="search"
            wire:focus="showSuggestions"
            class="w-full bg-transparent border-0 p-0 text-xs sm:text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-0 shadow-none"
            placeholder="{{ ($areaTitle && $scope === 'area') ? 'Buscar en '.$areaTitle.'...' : 'Buscar pedido, código, cliente o módulo...' }}"
            aria-label="Buscar en el ERP"
            autocomplete="off"
        >
        @if($areaTitle)
            <button
                type="button"
                wire:click="toggleScope"
                title="{{ $scope === 'area' ? 'Buscando en '.$areaTitle.' (clic para buscar en todo el ERP)' : 'Buscando en todo el ERP (clic para buscar en '.$areaTitle.')' }}"
                class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-semibold transition-colors shrink-0 {{ $scope === 'area' ? 'bg-white text-gray-700 border border-gray-200' : 'bg-transparent text-gray-400 hover:text-gray-600' }} mr-1.5"
            >
                <span class="max-w-[64px] truncate">{{ $scope === 'area' ? $areaTitle : 'Todo' }}</span>
            </button>
        @endif
        <button
            type="submit"
            aria-label="Buscar"
            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white border border-gray-200 text-[#b50e0e] hover:bg-rose-50 transition-colors shadow-2xs"
        >
            <x-portal.icon name="arrow" class="w-3.5 h-3.5" />
        </button>
    </form>

    @if($open)
        <div class="absolute left-0 top-full z-[70] mt-2 w-full overflow-hidden rounded-2xl border border-[#e8e8e9] bg-white shadow-xl">
            @foreach($navigationResults as $destination => $result)
                <a href="{{ route($destination) }}" class="block border-b border-gray-50 px-4 py-2.5 hover:bg-rose-50/50 transition-colors">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-[#b50e0e]">{{ $result['area'] }}</span>
                    <span class="block text-sm font-semibold text-gray-900">{{ $result['label'] }}</span>
                </a>
            @endforeach
            @forelse($suggestions as $suggestion)
                <a href="{{ route('explorer.index', ['q' => $search, 'tipo' => $suggestion['type'], 'registro_tipo' => $suggestion['type'], 'registro' => $suggestion['id']]) }}"
                   class="block border-b border-gray-50 px-4 py-2.5 text-left transition last:border-b-0 hover:bg-gray-50">
                    <span class="block text-[11px] font-medium text-gray-400">{{ $suggestion['category'] }}</span>
                    <span class="mt-0.5 block truncate text-sm font-semibold text-gray-900">{{ $suggestion['reference'] ?: 'Sin referencia' }}</span>
                    @if($suggestion['description'])
                        <span class="mt-0.5 block truncate text-xs text-gray-500">{{ $suggestion['description'] }}</span>
                    @endif
                </a>
            @empty
                @if(!$navigationResults)
                <div class="px-4 py-4 text-center">
                    <p class="text-sm font-semibold text-gray-700">Sin coincidencias</p>
                    <p class="mt-1 text-xs text-gray-400">Presiona Enter para revisar la búsqueda completa.</p>
                </div>
                @endif
            @endforelse

            @if($suggestions->isNotEmpty())
                <button type="button" wire:click="submitSearch" class="block w-full border-t border-gray-100 bg-gray-50 px-4 py-2.5 text-center text-xs font-semibold text-[#b50e0e] hover:bg-rose-50 transition-colors">
                    Ver todos los resultados para “{{ $search }}” →
                </button>
            @endif
        </div>
    @endif
</div>
