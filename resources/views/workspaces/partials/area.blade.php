@php
    $flow = config('workspace-flows.'.$area, []);
    $orderedItems = array_replace(array_intersect_key($flow, $items), $items);
@endphp
<div class="mb-6">
    <label for="workspace-search" class="sr-only">Buscar módulo en {{ $areas[$area]['title'] }}</label>
    <input id="workspace-search" type="search" x-model="search" placeholder="Buscar módulo..." class="w-full max-w-sm rounded-xl border-transparent bg-gray-50 px-3 py-2 text-sm transition-all focus:bg-white focus:ring-2 focus:ring-slate-200">
</div>
<section aria-label="Módulos de {{ $areas[$area]['title'] }}" data-testid="area-processes">
    <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        @foreach($orderedItems as $routeName => [$label, $description])
            @php([$stepTitle, $stepHelp] = $flow[$routeName] ?? [$label, $description])
            <li x-show="matches(@js($label.' '.$description.' '.$stepTitle.' '.$stepHelp))" class="min-w-0">
                <a href="{{ route($routeName) }}" class="group flex h-full flex-col rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition-colors hover:border-slate-300 hover:bg-gray-50/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500">
                    <span class="mb-5 flex items-center justify-between gap-3">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                            <x-workspace-icon :name="(config('workspace-presentation.process-icons', [])[$routeName] ?? 'document')" class="h-6 w-6"/>
                        </span>
                        <x-workspace-icon name="arrow" class="h-5 w-5 text-slate-400 transition-transform group-hover:translate-x-1 motion-reduce:transform-none"/>
                    </span>
                    <span class="text-base font-semibold text-gray-900">{{ $label }}</span>
                    <span class="mt-2 text-sm leading-relaxed text-gray-500">{{ $stepHelp }}</span>
                </a>
            </li>
        @endforeach
    </ul>
    <p x-cloak x-show="!@js(array_map(fn ($routeName) => implode(' ', array_merge($items[$routeName], $flow[$routeName] ?? [])), array_keys($items))).some(value => matches(value))" role="status" class="py-4 text-sm text-gray-500">No se encontraron módulos en {{ $areas[$area]['title'] }}.</p>
</section>
