<section aria-label="Resumen del área" class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-xl bg-slate-900 p-3.5 sm:p-4 text-white shadow-2xs flex flex-col justify-between">
        <div class="mb-1 flex items-center justify-between gap-2">
            <p class="text-[11px] font-semibold text-slate-300 truncate">{{ $area ? 'Resumen de '.$areas[$area]['title'] : 'Tu centro de trabajo' }}</p>
            <span class="inline-flex items-center justify-center rounded-lg bg-white/10 p-1 text-teal-300">
                <x-portal.icon :name="$icon" class="w-3.5 h-3.5" />
            </span>
        </div>
        <p class="text-xl sm:text-2xl font-bold tabular-nums text-white leading-tight">
            {{ $area ? count($items) : count($areas) }} <span class="text-xs font-normal text-slate-400">{{ $area ? 'procesos' : 'áreas' }}</span>
        </p>
        <p class="mt-1 text-[11px] text-slate-400 truncate">Disponibles · {{ now()->format('H:i') }}</p>
    </div>
    @foreach($metrics as $metric)
        <a href="{{ route($metric['route']) }}" class="min-w-0 rounded-xl transition-all hover:shadow-xs focus-visible:outline focus-visible:outline-2 focus-visible:outline-slate-500">
            <x-dashboard-metric :compact="true" :label="$metric['label']" :value="number_format($metric['value'])" :icon="$metric['icon']" :tone="$metric['pending'] && $metric['value'] > 0 ? 'amber' : 'teal'" :context="$metric['context']" />
        </a>
    @endforeach
</section>
