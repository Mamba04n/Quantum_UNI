<a href="{{ route($routeName) }}" x-show="matches(@js($label.' '.$description))" class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-colors hover:border-slate-400 hover:bg-slate-50/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-600">
    <div class="mb-3 flex items-center justify-between gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-600 transition-colors group-hover:bg-slate-800 group-hover:text-white"><x-workspace-icon :name="$presentation['process-icons'][$routeName] ?? $cardIcon"/></span><x-workspace-icon name="arrow" class="h-4 w-4 text-slate-400 group-hover:text-slate-800"/></div>
    <h4 class="text-base font-semibold text-slate-900">{{ $label }}</h4>
    <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
    <span class="mt-4 border-t border-slate-100 pt-3 text-xs font-semibold text-slate-600">Abrir proceso</span>
</a>
