<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
        <p class="text-xs text-gray-400 font-medium">Eventos hoy</p>
        <p class="text-3xl font-black text-gray-900 mt-1">{{ $todayLogs }}</p>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
        <p class="text-xs text-gray-400 font-medium">Movimientos hoy</p>
        <p class="text-3xl font-black text-emerald-700 mt-1">{{ $todayMoves }}</p>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
        <p class="text-xs text-gray-400 font-medium">Usuarios activos hoy</p>
        <p class="text-3xl font-black text-slate-700 mt-1">{{ $activeToday }}</p>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
        <p class="text-xs text-gray-400 font-medium">Eventos históricos</p>
        <p class="text-3xl font-black text-gray-900 mt-1">{{ number_format($totalActions) }}</p>
    </div>
</div>
