<section class="min-w-0 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <header class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-3">
        <div><h2 class="text-sm font-semibold text-gray-900">{{ $queueTitle }}</h2><p class="mt-0.5 text-xs text-gray-500">{{ $queueContext }}</p></div>
        <a href="{{ route($queueRoute) }}" class="text-sm font-semibold text-slate-700">Ver todas →</a>
    </header>
    <table class="w-full table-fixed text-left text-sm">
        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
            <tr><th class="w-1/2 px-5 py-3 font-semibold">Expediente / cliente</th><th class="px-5 py-3 font-semibold">Etapa y siguiente paso</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($queueRows as $row)
                <tr class="transition-colors hover:bg-gray-50/50">
                    <td class="break-words px-5 py-3 align-top"><span class="font-semibold text-gray-900">{{ $row['reference'] }}</span><p class="mt-1 text-gray-500">{{ $row['client'] ?: 'Sin cliente' }}</p></td>
                    <td class="break-words px-5 py-3 align-top"><p class="text-gray-600">{{ $row['stage'] }}</p><a href="{{ $row['url'] }}" aria-label="{{ $row['action'] }} · {{ $row['reference'] }}" class="mt-1 inline-flex items-center gap-1 font-semibold text-slate-800">{{ $row['action'] }} <span aria-hidden="true">→</span></a></td>
                </tr>
            @empty
                <tr><td colspan="2" class="px-5 py-5 text-sm text-gray-500">No hay expedientes activos para mostrar.</td></tr>
            @endforelse
        </tbody>
    </table>
</section>
