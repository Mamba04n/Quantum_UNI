<div class="bg-white rounded-2xl border border-[#e8e8e9] shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
            <thead class="bg-gray-50/80 text-gray-500 text-xs uppercase tracking-wider border-b border-[#e8e8e9]">
                <tr>
                    <th class="px-5 py-3.5 font-semibold">Usuario</th>
                    <th class="px-5 py-3.5 font-semibold">Correo</th>
                    <th class="px-5 py-3.5 font-semibold">Rol</th>
                    <th class="px-5 py-3.5 font-semibold text-center">Estado</th>
                    <th class="px-5 py-3.5 font-semibold text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#e8e8e9]/60">
                @forelse($users as $user)
                    @include('livewire.administracion.usuarios.fila')
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-gray-400 text-xs italic">No se encontraron empleados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
