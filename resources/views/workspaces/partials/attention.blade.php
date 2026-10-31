<div class="space-y-4">
    @if($pendingMetrics)
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Requiere atención</h2><span class="rounded-md px-2 py-0.5 text-xs font-semibold {{ $pendingCount > 0 ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600' }}">{{ $pendingCount }}</span></div>
            <div class="px-5">
                @foreach($pendingMetrics as $metric)
                    <a href="{{ route($metric['route']) }}" class="group flex items-center gap-3 border-b border-slate-100 py-4 last:border-0"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $metric['value'] > 0 ? 'bg-amber-50 text-amber-700' : 'bg-slate-50 text-slate-400' }}"><x-workspace-icon :name="$metric['icon']" class="h-4 w-4"/></span><div class="min-w-0 flex-1"><p class="text-sm font-medium text-slate-700 group-hover:text-slate-900">{{ $metric['label'] }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $metric['value'] ? $metric['value'].' por atender' : 'Sin pendientes' }}</p></div><x-workspace-icon name="arrow" class="h-4 w-4 text-slate-400"/></a>
                @endforeach
            </div>
            <p class="border-t border-slate-100 bg-slate-50/60 px-5 py-3 text-xs text-slate-500">Pendientes de los procesos incluidos en este resumen.</p>
        </section>
    @endif
    @if($quickItems)
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Accesos rápidos</h2><p class="mt-1 text-xs text-slate-500">Inicia una operación sin cambiar de área.</p></div>
            <div class="p-2">
                @foreach($quickItems as $routeName => [$label, $description])
                    <a href="{{ route($routeName) }}" class="group flex items-center gap-3 rounded-lg px-3 py-3 text-sm text-slate-600 transition-colors hover:bg-slate-50 hover:text-slate-900"><x-workspace-icon name="plus" class="h-4 w-4 text-slate-400"/><span class="flex-1">{{ $label }}</span><x-workspace-icon name="arrow" class="h-4 w-4 text-slate-400"/></a>
                @endforeach
            </div>
        </section>
    @endif
    <div class="flex items-start gap-3 px-1 py-2 text-slate-500"><x-workspace-icon name="search" class="h-4 w-4"/><p class="text-xs leading-relaxed">¿Buscas un documento? Usa el <a href="{{ route('explorer.index') }}" class="font-semibold text-slate-700 underline decoration-slate-300 underline-offset-2">Explorador</a> para encontrar órdenes, solicitudes y productos.</p></div>
</div>
