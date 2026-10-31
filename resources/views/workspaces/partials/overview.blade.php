<section aria-label="Resumen del área" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl bg-slate-800 p-4 text-white shadow-sm">
        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-slate-200">{{ $area ? 'Resumen de '.$areas[$area]['title'] : 'Tu centro de trabajo' }}</p>
            <x-workspace-icon :name="$icon" class="h-5 w-5 text-teal-300" />
        </div>
        <p class="text-2xl font-extrabold tabular-nums">{{ $area ? count($items) : count($areas) }} <span class="text-sm font-medium text-slate-300">{{ $area ? 'procesos' : 'áreas' }}</span></p>
        <p class="mt-2 text-xs text-slate-300">Disponibles para tu perfil · {{ now()->format('H:i') }}</p>
    </div>
    @foreach($metrics as $metric)
        <a href="{{ route($metric['route']) }}" class="min-w-0 rounded-2xl transition-shadow hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-slate-500">
            <x-dashboard-metric :label="$metric['label']" :value="number_format($metric['value'])" :icon="$metric['icon']" :tone="$metric['pending'] && $metric['value'] > 0 ? 'amber' : 'teal'" :context="$metric['context']" />
        </a>
    @endforeach
</section>
