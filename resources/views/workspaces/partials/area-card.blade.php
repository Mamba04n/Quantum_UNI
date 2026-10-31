<article x-show="matches(@js($workspace['title'].' '.implode(' ', array_merge(...array_values($workspace['items'])))))" class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-colors hover:border-slate-400">
    <a href="{{ route('workspace', $key) }}" class="block flex-1 p-5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-slate-600">
        <div class="mb-3 flex items-center justify-between gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-600 transition-colors group-hover:bg-slate-800 group-hover:text-white"><x-workspace-icon :name="$presentation['icons'][$key]"/></span><span class="text-xs text-slate-500">{{ count($workspace['items']) }} {{ count($workspace['items']) === 1 ? 'proceso' : 'procesos' }}</span></div>
        <h4 class="flex items-center justify-between gap-2 text-base font-semibold text-slate-900">{{ $workspace['title'] }}<x-workspace-icon name="arrow" class="h-4 w-4 text-slate-400 group-hover:text-slate-800"/></h4>
        <p class="mt-1 text-sm text-slate-500">{{ $workspace['description'] }}</p>
    </a>
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-100 bg-slate-50/50 px-5 py-3" x-show="!search.trim()">
        @foreach(array_slice($workspace['items'], 0, 2, true) as $routeName => [$label, $description])<a href="{{ route($routeName) }}" class="text-xs font-medium text-slate-600 underline decoration-slate-200 underline-offset-4 hover:text-slate-900 hover:decoration-slate-500">{{ $label }}</a>@endforeach
    </div>
    <div x-cloak x-show="search.trim()" class="divide-y divide-slate-100 border-t border-slate-100 bg-slate-50/50">
        @foreach($workspace['items'] as $routeName => [$label, $description])<a x-show="matches(@js($workspace['title'].' '.$label.' '.$description))" href="{{ route($routeName) }}" class="flex items-center justify-between gap-2 px-5 py-3 text-sm text-slate-700 hover:bg-slate-100">{{ $label }}<x-workspace-icon name="arrow" class="h-4 w-4 text-slate-400"/></a>@endforeach
    </div>
</article>
