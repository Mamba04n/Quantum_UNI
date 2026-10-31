@php($control = 'w-full rounded-xl border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-slate-400 focus:ring-slate-200')
<form wire:submit="save" class="space-y-5">
    @if($errors->any())<div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><p class="font-semibold">Revisa los campos de la cotización.</p><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-base font-semibold text-gray-900">Datos de la oferta</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div><label for="quote-supplier" class="mb-1 block text-sm font-medium text-gray-700">Proveedor *</label><select id="quote-supplier" wire:model="draft.supplier_id" class="{{ $control }}"><option value="">Seleccionar proveedor</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div>
            <div><label for="quote-reference" class="mb-1 block text-sm font-medium text-gray-700">Referencia del proveedor</label><input id="quote-reference" wire:model="draft.reference" maxlength="100" class="{{ $control }}"></div>
            <div><label for="quote-date" class="mb-1 block text-sm font-medium text-gray-700">Fecha de cotización *</label><input id="quote-date" type="date" wire:model="draft.quoted_at" max="{{ today()->toDateString() }}" class="{{ $control }}"></div>
            <div><label for="quote-validity" class="mb-1 block text-sm font-medium text-gray-700">Vigencia hasta (opcional)</label><input id="quote-validity" type="date" wire:model="draft.valid_until" class="{{ $control }}"></div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div><label for="quote-attachment" class="mb-1 block text-sm font-medium text-gray-700">Oferta original (opcional)</label><input id="quote-attachment" type="file" wire:model="attachment" accept=".pdf,.xlsx,.xls,.csv,.txt" class="block w-full text-sm text-gray-600"><p class="mt-1 text-xs text-gray-500">PDF, Excel o texto. Máximo 10 MB. Los PDF se adjuntan como respaldo; captura sus partidas abajo.</p><span wire:loading wire:target="attachment" class="text-sm text-gray-500">Cargando archivo…</span></div>
            <div><label for="quote-notes" class="mb-1 block text-sm font-medium text-gray-700">Condiciones y observaciones</label><textarea id="quote-notes" wire:model="draft.notes" rows="2" maxlength="5000" class="{{ $control }}"></textarea></div>
        </div>
    </section>
    <details class="rounded-xl border border-gray-200 bg-white px-5 py-3">
        <summary class="cursor-pointer text-sm font-semibold text-gray-700">Cargar partidas desde Excel o texto</summary>
        <p class="mt-3 text-sm text-gray-500">Utiliza la <a href="{{ route('purchases.supplier-prices.template') }}" class="font-semibold text-slate-700 underline">plantilla CSV</a> o sus encabezados en Excel. Decimales con punto, sin separador de miles. Al cargar se reemplazan las partidas del borrador; revisa antes de guardar.</p>
        <div class="mt-3 grid gap-4 sm:grid-cols-2"><div><label for="quote-import" class="mb-1 block text-sm font-medium text-gray-700">Excel, CSV o TXT</label><input id="quote-import" type="file" wire:model="importFile" accept=".xlsx,.xls,.csv,.txt" class="block w-full text-sm"><span wire:loading wire:target="importFile" class="text-sm text-gray-500">Cargando archivo…</span></div><div><label for="quote-paste" class="mb-1 block text-sm font-medium text-gray-700">O pega columnas con encabezados</label><textarea id="quote-paste" wire:model="paste" rows="3" class="{{ $control }}" placeholder="numero_parte&#9;descripcion&#9;cantidad&#9;unidad&#9;precio_unitario_usd"></textarea></div></div>
        <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import,importFile" class="mt-3 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50">Cargar y revisar partidas</button>
    </details>
    <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4"><h2 class="text-base font-semibold text-gray-900">Partidas · precios en USD</h2><button type="button" wire:click="addItem" class="text-sm font-semibold text-slate-700 underline">Agregar partida</button></div>
        <div class="divide-y divide-gray-100">
        @foreach($draft['items'] ?? [] as $index => $row)
            <div wire:key="draft-price-{{ $token }}-{{ $index }}" class="p-5">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
                    <div><label for="part-{{ $index }}" class="mb-1 block text-sm font-medium text-gray-700">Número de parte *</label><input id="part-{{ $index }}" wire:model="draft.items.{{ $index }}.part_number" maxlength="100" class="{{ $control }}"></div>
                    <div class="xl:col-span-2"><label for="description-{{ $index }}" class="mb-1 block text-sm font-medium text-gray-700">Descripción *</label><input id="description-{{ $index }}" wire:model="draft.items.{{ $index }}.description" maxlength="1000" class="{{ $control }}"></div>
                    <div><label for="quantity-{{ $index }}" class="mb-1 block text-sm font-medium text-gray-700">Cantidad *</label><input id="quantity-{{ $index }}" type="number" step="0.001" min="0.001" wire:model.blur="draft.items.{{ $index }}.quantity" class="{{ $control }}"></div>
                    <div><label for="unit-{{ $index }}" class="mb-1 block text-sm font-medium text-gray-700">Unidad de medida *</label><input id="unit-{{ $index }}" wire:model="draft.items.{{ $index }}.unit" maxlength="30" class="{{ $control }}"></div>
                    <div><label for="unit-price-{{ $index }}" class="mb-1 block text-sm font-medium text-gray-700">Unitario USD *</label><input id="unit-price-{{ $index }}" type="number" step="0.0001" min="0.0001" wire:model.blur="draft.items.{{ $index }}.unit_price" class="{{ $control }}"></div>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3 text-sm"><button type="button" wire:click="removeItem({{ $index }})" class="text-red-700 underline" aria-label="Quitar partida {{ $index + 1 }}">Quitar partida</button><p class="text-gray-600">Total de partida: <strong class="tabular-nums text-gray-900">US${{ number_format((is_numeric($row['quantity'] ?? null) ? (float)$row['quantity'] : 0) * (is_numeric($row['unit_price'] ?? null) ? (float)$row['unit_price'] : 0), 2) }}</strong></p></div>
            </div>
        @endforeach
        </div>
    </section>
    <div class="flex flex-wrap items-center justify-between gap-4"><p class="text-sm text-gray-500">Se conservará esta oferta como historial. La disponibilidad queda por confirmar.</p><button type="submit" wire:loading.attr="disabled" wire:target="save,attachment,importFile,import" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-900 disabled:opacity-50"><span wire:loading.remove wire:target="save">Guardar cotización</span><span wire:loading wire:target="save">Guardando…</span></button></div>
</form>
