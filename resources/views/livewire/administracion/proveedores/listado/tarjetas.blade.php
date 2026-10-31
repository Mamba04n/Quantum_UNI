<div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full whitespace-nowrap text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-5 py-3 font-semibold">Proveedor</th>
                    <th class="px-5 py-3 font-semibold">Contacto</th>
                    <th class="px-5 py-3 font-semibold">Capacidad</th>
                    <th class="px-5 py-3 text-center font-semibold">Estado</th>
                    <th class="px-5 py-3 text-center font-semibold">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($suppliers as $supplier)
                    @include('livewire.administracion.proveedores.listado.tarjeta-proveedor')
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-xs italic text-gray-400">No se encontraron proveedores.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-5">
    {{ $suppliers->links() }}
</div>
