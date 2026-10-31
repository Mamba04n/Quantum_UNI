<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-gray-50 text-gray-500 text-[10px] font-black uppercase tracking-wider">
                <tr>
                    <th class="px-3 py-3 text-left">Ticket</th>
                    <th class="px-3 py-3 text-left">Cliente</th>
                    <th class="px-3 py-3 text-left">Tipo</th>
                    <th class="px-3 py-3 text-left">Prioridad</th>
                    <th class="px-3 py-3 text-left">Estado</th>
                    <th class="px-3 py-3 text-left">Responsable</th>
                    <th class="px-3 py-3 text-right">Accion</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($tickets as $ticket)
                    @include('livewire.administracion.tickets.listado.fila')
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-14 text-center text-gray-500">No hay tickets para los filtros seleccionados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('livewire.administracion.tickets.listado.paginacion')
</div>
