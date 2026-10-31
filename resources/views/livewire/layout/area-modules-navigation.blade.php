@php
    $areaNavigation = app(\App\Support\WorkspaceNavigation::class);
    $visibleAreas = $areaNavigation->areas(auth()->user());
    $activeArea = $areaNavigation->current($visibleAreas, request()->route()?->getName());
    $currentAreaConfig = ($activeArea && isset($visibleAreas[$activeArea])) ? $visibleAreas[$activeArea] : null;
    $activeModule = $areaNavigation->currentModule(request()->route()?->getName());
@endphp

@if($currentAreaConfig && !empty($currentAreaConfig['items']) && !request()->routeIs('workspace'))
<nav aria-label="Módulos de {{ $currentAreaConfig['title'] }}" class="flex min-w-0 flex-1 overflow-x-auto scroll-smooth">
    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('workspace', $activeArea) }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border-b-2 border-transparent px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-white hover:text-[#6f1220] sm:text-sm"><x-workspace-icon name="grid" class="h-4 w-4"/> Resumen</a>
        @foreach($currentAreaConfig['items'] as $routeName => [$label, $description])
            @php
                $isCurrent = ($activeModule === $routeName);
            @endphp
            <a href="{{ route($routeName) }}"
               @if($isCurrent) aria-current="page" @endif
               title="{{ $description }}"
               class="inline-flex shrink-0 items-center rounded-lg border-b-2 px-3 py-1.5 text-xs sm:text-sm transition-colors {{ $isCurrent ? 'border-[#6f1220] bg-white font-semibold text-[#6f1220] shadow-sm' : 'border-transparent font-medium text-slate-600 hover:bg-white hover:text-[#6f1220]' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</nav>
@endif
