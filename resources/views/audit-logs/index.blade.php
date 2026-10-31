<x-app-layout>
    <div class="p-6 sm:p-10 space-y-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">Registro de Actividad</h1>
                <p class="text-gray-500 mt-1 text-sm">Historial completo de acciones de todos los usuarios en el sistema.</p>
            </div>
            <div>
                <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors shadow-sm">
                    Volver al panel
                </a>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-600 font-medium border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4">Usuario</th>
                            <th class="px-6 py-4">Acción</th>
                            <th class="px-6 py-4">Módulo (Entidad)</th>
                            <th class="px-6 py-4">Fecha y Hora</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($logs as $log)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 font-medium text-gray-900 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-xs shrink-0">
                                    {{ $log->user ? mb_strtoupper(mb_substr(trim($log->user->name), 0, 2, 'UTF-8'), 'UTF-8') : '??' }}
                                </div>
                                {{ $log->user ? $log->user->name : 'Sistema' }}
                            </td>
                            <td class="px-6 py-4 text-gray-700">{{ $log->action }}</td>
                            <td class="px-6 py-4 text-gray-500">
                                <span class="px-2.5 py-1 bg-gray-100 rounded-md text-xs font-medium">
                                    {{ $log->entity_type ? class_basename($log->entity_type) : 'N/A' }}
                                    {{ $log->entity_id ? '#'.$log->entity_id : '' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">
                                {{ $log->created_at ? $log->created_at->format('d/m/Y H:i') : 'Desconocida' }}
                                <span class="text-xs text-gray-400 block">{{ $log->created_at ? $log->created_at->diffForHumans() : '' }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">No hay actividad registrada aún.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-gray-100">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
