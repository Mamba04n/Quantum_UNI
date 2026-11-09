<x-app-layout>
    @php
        $presentation = config('workspace-presentation');
        $allItems = [];
        foreach ($areas as $workspace) { $allItems += $workspace['items']; }
        $items = $area ? $areas[$area]['items'] : $allItems;
        $icon = $presentation['icons'][$area] ?? 'grid';
    @endphp
    <div class="p-6 font-sans sm:p-8" data-testid="workspace-content" @if($area) data-area="{{ $area }}" @endif x-data="{ search: '', matches(value) { return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().includes(this.search.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim()) } }">
        @if(!$area)
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#b50e0e] mb-1">ESPACIO DE TRABAJO</p>
                    <h1 class="text-2xl font-bold tracking-tight text-[#1e1e1e]">Inicio</h1>
                    <p class="mt-0.5 text-xs text-gray-500 font-medium">Áreas de trabajo y accesos operativos del sistema.</p>
                </div>
            </div>
            @include('workspaces.partials.home')
        @else
            @include('workspaces.partials.area')
        @endif
    </div>
</x-app-layout>
