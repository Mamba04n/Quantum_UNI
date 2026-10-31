<x-app-layout>
    @php
        $presentation = config('workspace-presentation');
        $allItems = [];
        foreach ($areas as $workspace) { $allItems += $workspace['items']; }
        $items = $area ? $areas[$area]['items'] : $allItems;
        $icon = $presentation['icons'][$area] ?? 'grid';
    @endphp
    <div class="p-6 font-sans sm:p-8" data-testid="workspace-content" @if($area) data-area="{{ $area }}" @endif x-data="{ search: '', matches(value) { return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().includes(this.search.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim()) } }">
        @if($area)
            <nav aria-label="Ubicación del espacio de trabajo" class="mb-4 flex items-center gap-2 text-xs text-slate-500">
                <a href="{{ route('workspace') }}" class="font-medium hover:text-slate-900">Inicio</a>
                <span aria-hidden="true">/</span><span aria-current="page">{{ $areas[$area]['title'] }}</span>
            </nav>
        @endif
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">{{ $area ? $areas[$area]['title'] : 'Inicio' }}</h1>
                <p class="mt-0.5 text-sm text-gray-500">{{ $area ? $areas[$area]['description'] : 'Áreas de trabajo y accesos operativos del sistema.' }}</p>
            </div>
            @if(!$area)
                <div>
                    <label for="workspace-hub-search" class="sr-only">Buscar área o módulo</label>
                    <input id="workspace-hub-search" type="search" x-model="search" placeholder="Buscar área o módulo..." class="w-full sm:w-72 rounded-xl border-transparent bg-gray-50 px-3 py-2 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200">
                </div>
            @endif
        </div>
        @if(!$area)
            @include('workspaces.partials.home')
        @else
            @include('workspaces.partials.area')
        @endif
    </div>
</x-app-layout>
