@php
    $maximum = max(3, (int) ceil(max(array_merge(...array_column($series, 'values'))) / 3) * 3);
    $chartColors = ['#6f1220', '#64748b'];
@endphp
<section class="min-w-0 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm" aria-label="Actividad de {{ $areas[$area]['title'] }}">
    <div class="border-b border-gray-100 px-5 py-3">
        <h2 class="text-sm font-semibold text-gray-900">Actividad del área</h2>
        <p class="mt-0.5 text-sm text-gray-500">Registros creados por mes · últimos seis meses.</p>
    </div>
    <div class="px-5 py-3">
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Gráfica mensual desplazable">
        <svg viewBox="0 0 900 220" class="max-h-56 w-full min-w-[600px]" role="img" aria-label="Evolución mensual de registros de {{ $areas[$area]['title'] }}">
            <title>Actividad mensual. Los valores exactos están en el detalle inferior.</title>
            @foreach([0, 1, 2, 3] as $line)
                <line x1="60" x2="840" y1="{{ 180 - $line * 50 }}" y2="{{ 180 - $line * 50 }}" stroke="#f1f5f9" />
                <text x="48" y="{{ 184 - $line * 50 }}" text-anchor="end" fill="#64748b" font-size="17">{{ (int) ceil($maximum * $line / 3) }}</text>
            @endforeach
            @foreach($series as $seriesIndex => $entry)
                @php($points = collect($entry['values'])->map(fn ($value, $i) => (60 + $i * 156).','.round(180 - $value / $maximum * 150, 2))->implode(' '))
                <polyline points="{{ $points }}" fill="none" stroke="{{ $chartColors[$seriesIndex % 2] }}" stroke-width="3" stroke-linejoin="round" />
                @foreach($entry['values'] as $i => $value)
                    <circle cx="{{ 60 + $i * 156 }}" cy="{{ 180 - $value / $maximum * 150 }}" r="4" fill="{{ $chartColors[$seriesIndex % 2] }}"><title>{{ $entry['label'] }} · {{ $areaDashboard['months'][$i] }}: {{ $value }}</title></circle>
                @endforeach
            @endforeach
            @foreach($areaDashboard['months'] as $i => $month)
                <text x="{{ 60 + $i * 156 }}" y="208" text-anchor="middle" fill="#64748b" font-size="17">{{ $month }}</text>
            @endforeach
        </svg>
        </div>
        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm">
            @foreach($series as $seriesIndex => $entry)
                <span class="inline-flex items-center gap-2 text-slate-600"><span class="h-2 w-2 rounded-full" style="background-color: {{ $chartColors[$seriesIndex % 2] }}" aria-hidden="true"></span>{{ $entry['label'] }}</span>
            @endforeach
        </div>
        @if(array_sum(array_merge(...array_column($series, 'values'))) === 0)
            <p class="mt-4 text-sm text-gray-500">Sin actividad registrada en este período.</p>
        @endif
        <details class="mt-2 text-sm text-slate-600">
            <summary class="cursor-pointer py-1 font-semibold">Ver valores por mes</summary>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full whitespace-nowrap text-left text-sm">
                    <thead><tr><th scope="col" class="py-2 pr-4 font-semibold">Mes</th>@foreach($series as $entry)<th scope="col" class="px-3 py-2 font-semibold">{{ $entry['label'] }}</th>@endforeach</tr></thead>
                    <tbody>@foreach($areaDashboard['months'] as $i => $month)<tr class="border-t border-gray-100"><th scope="row" class="py-2 pr-4 font-normal">{{ $month }}</th>@foreach($series as $entry)<td class="px-3 py-2 tabular-nums">{{ $entry['values'][$i] }}</td>@endforeach</tr>@endforeach</tbody>
                </table>
            </div>
        </details>
    </div>
</section>
