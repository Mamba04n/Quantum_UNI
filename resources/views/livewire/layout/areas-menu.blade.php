@php($menuAreas = app(\App\Support\WorkspaceNavigation::class)->areas(auth()->user()))
<div x-data="{ open: false }" x-on:keydown.escape.window="if(open) { open = false; $refs.trigger.focus() }" x-on:click.outside="open = false">
    <button type="button" x-ref="trigger" x-on:click="open = !open" x-bind:aria-expanded="open" aria-controls="global-areas" aria-label="Todas las áreas" title="Todas las áreas" x-bind:class="open ? 'bg-rose-50 text-[#6f1220]' : 'text-slate-700'" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold transition-colors hover:bg-rose-50 hover:text-[#6f1220]">
        <span class="lg:hidden">Áreas</span><svg class="h-4 w-4 transition-transform" x-bind:class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <nav id="global-areas" x-cloak x-show="open" aria-label="Todas las áreas" class="absolute inset-x-3 top-full z-50 mt-1 max-h-[75vh] overflow-y-auto rounded-2xl border border-gray-200 bg-white p-5 shadow-lg sm:inset-x-8 sm:p-6">
        <div class="mb-5 flex items-center justify-between"><h2 class="text-base font-semibold text-gray-900">Áreas de trabajo</h2><button type="button" x-on:click="open = false; $refs.trigger.focus()" class="rounded-lg px-3 py-2 text-sm text-slate-600">Cerrar</button></div>
        <div class="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($menuAreas as $areaKey => $menuArea)
                <div>
                    <a href="{{ route('workspace', $areaKey) }}" @if(($activeAreaKey ?? null) === $areaKey) aria-current="page" @endif class="mb-2 flex items-center gap-2 rounded-lg px-2 py-2 text-sm font-semibold {{ ($activeAreaKey ?? null) === $areaKey ? 'bg-[#6f1220] text-white' : 'bg-slate-50 text-[#6f1220]' }}"><x-workspace-icon :name="config('workspace-presentation.icons.'.$areaKey, 'grid')" class="h-4 w-4"/>{{ $menuArea['title'] }}</a>
                    <p class="mb-2 px-2 text-xs text-gray-500">{{ $menuArea['description'] }}</p>
                    <ul class="space-y-1">@foreach($menuArea['items'] as $destination => [$label, $description])<li><a href="{{ route($destination) }}" class="block rounded-lg px-2 py-1.5 text-sm text-slate-600">{{ $label }}</a></li>@endforeach</ul>
                </div>
            @endforeach
        </div>
    </nav>
</div>
