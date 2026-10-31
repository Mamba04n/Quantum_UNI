<section>
    <div class="flex items-center justify-between mb-4">
        <h4 class="text-xs font-black uppercase tracking-widest text-slate-500">Productos en la Orden</h4>
    </div>

    <div class="overflow-x-auto border border-slate-200 rounded-xl">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-100 bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-400">
                <tr>
                    <th class="px-4 py-3 min-w-[200px]">Producto / Codigo</th>
                    <th class="px-4 py-3 w-24">Cantidad</th>
                    <th class="px-4 py-3 min-w-[140px] w-36">Costo U.</th>
                    <th class="px-4 py-3 min-w-[150px] w-40">Precio U.</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($selectedQuote->items as $item)
                    <tr class="hover:bg-slate-50/50">
                        <td class="px-4 py-3">
                            <input type="text" wire:model="editingItems.{{ $item->id }}.part_number" class="w-full text-xs font-black text-slate-900 border-0 p-0 focus:ring-0 mb-1" placeholder="Codigo">
                            <input type="text" wire:model="editingItems.{{ $item->id }}.part_name" class="w-full text-xs text-slate-500 border-0 p-0 focus:ring-0" placeholder="Nombre">
                        </td>
                        <td class="px-4 py-3">
                            <input type="number" wire:model="editingItems.{{ $item->id }}.quantity" class="w-full rounded-lg border-slate-200 text-xs py-1.5 focus:border-indigo-500 focus:ring-indigo-500" min="1">
                        </td>
                        <td class="px-4 py-3">
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-slate-400 text-xs">$</span>
                                <input type="number" step="0.01" wire:model="editingItems.{{ $item->id }}.unit_cost" class="w-full rounded-lg border-slate-200 text-xs py-1.5 pl-6 focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-slate-400 text-xs">C$</span>
                                <input type="number" step="0.01" wire:model="editingItems.{{ $item->id }}.unit_price" class="w-full rounded-lg border-slate-200 text-xs py-1.5 pl-7 focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" wire:click="updateItem({{ $item->id }})" class="text-indigo-600 hover:text-indigo-900 font-bold text-[10px] uppercase bg-indigo-50 px-2 py-1 rounded">Guardar</button>
                                <button type="button" wire:click="deleteItem({{ $item->id }})" wire:confirm="¿Eliminar este producto?" class="text-rose-600 hover:text-rose-900 font-bold text-[10px] uppercase bg-rose-50 px-2 py-1 rounded">Eliminar</button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
