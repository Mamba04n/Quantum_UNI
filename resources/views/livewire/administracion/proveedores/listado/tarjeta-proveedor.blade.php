<tr wire:click="selectSupplier({{ $supplier->id }})" tabindex="0" role="button" wire:keydown.enter="selectSupplier({{ $supplier->id }})" class="cursor-pointer transition-colors hover:bg-gray-50/50">
    <td class="px-5 py-3">
        <div class="font-semibold text-gray-900">{{ $supplier->name }}</div>
        <div class="mt-0.5 max-w-sm truncate text-xs text-gray-400">{{ $supplier->legal_name ?: 'Razón social no registrada' }}@if($supplier->rfc_ruc) · {{ $supplier->rfc_ruc }}@endif</div>
    </td>
    <td class="px-5 py-3">
        <div class="text-sm text-gray-700">{{ $supplier->contact_name ?: 'Sin contacto principal' }}</div>
        <div class="mt-0.5 max-w-xs truncate text-xs text-gray-400">{{ $supplier->email ?: $supplier->phone_mobile ?: 'Sin datos de contacto' }}</div>
    </td>
    <td class="px-5 py-3">
        <div class="max-w-xs truncate text-sm text-gray-700">{{ $supplier->brands->take(2)->pluck('name')->implode(', ') ?: 'Sin marcas registradas' }}</div>
        <div class="mt-0.5 max-w-xs truncate text-xs text-gray-400">{{ $supplier->productTypes->take(2)->pluck('name')->implode(', ') ?: 'Productos no clasificados' }}</div>
    </td>
    <td class="px-5 py-3 text-center">
        <span class="inline-flex rounded border px-2 py-0.5 text-[11px] font-bold {{ ($supplier->status ?? 'activo') === 'activo' ? 'border-teal-200 bg-teal-50 text-teal-700' : 'border-gray-200 bg-gray-50 text-gray-500' }}">{{ str($supplier->status ?? 'activo')->headline() }}</span>
    </td>
    <td class="px-5 py-3 text-center">
        <span class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 transition-colors group-hover:text-slate-900">Ver perfil <span aria-hidden="true">›</span></span>
    </td>
</tr>
