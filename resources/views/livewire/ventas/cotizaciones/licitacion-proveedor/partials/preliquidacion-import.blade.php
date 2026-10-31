<details class="preliq-disclosure" x-data="{ method: 'manual' }" x-on:preliquidation-open-import.window="$el.open = true; method = 'paste'">
    <summary>+ Agregar ítems</summary>
    <div class="space-y-3 pb-3">
        <div class="flex flex-wrap gap-2">
            @foreach(['manual' => 'Manual / varias filas', 'paste' => 'Pegar desde Excel', 'file' => 'Importar Excel / CSV'] as $value => $label)
                <button type="button" x-on:click="method = '{{ $value }}'" x-bind:aria-pressed="method === '{{ $value }}'" x-bind:class="method === '{{ $value }}' ? 'bg-slate-800 text-white' : 'bg-gray-50 text-slate-600'" class="rounded-lg px-3 py-2 text-sm font-semibold">{{ $label }}</button>
            @endforeach
        </div>
        <div x-show="method === 'manual'" class="flex flex-wrap items-end gap-2">
            <label class="text-sm">Número de filas<input type="number" min="1" max="100" wire:model="preliquidationAddCount" class="preliq-input block max-w-32"></label>
            <button type="button" wire:click="addPreliquidationItems" wire:loading.attr="disabled" class="{{ $secondaryButton }}">Agregar filas</button>
        </div>
        <div x-show="method === 'paste'" x-cloak>
            <label class="block text-sm">Part Number · Description · Qty · Unit Price · Weight (lb)<textarea rows="4" wire:model="preliquidationPaste" class="mt-1 block w-full rounded-lg border-gray-200 text-sm" placeholder="Pega las cinco columnas desde Excel, con o sin encabezados."></textarea></label>
            <button type="button" wire:click="previewPreliquidationItems" wire:loading.attr="disabled" class="{{ $secondaryButton }} mt-2">Ver vista previa</button>
        </div>
        <label x-show="method === 'file'" x-cloak class="block text-sm">Excel / CSV (primera hoja, máximo 500 partidas)<input type="file" wire:model="preliquidationFile" accept=".xlsx,.csv,.txt" class="mt-2 block max-w-full"></label>
        <span wire:loading wire:target="preliquidationFile,previewPreliquidationItems" class="text-sm text-gray-500">Leyendo partidas…</span>
        @if(count($preliquidationPreview))
            <p class="text-sm font-semibold">{{ count($preliquidationPreview) }} filas detectadas</p>
            <div class="max-h-60 overflow-auto rounded-lg border border-gray-100"><table class="w-full text-left text-sm"><thead class="bg-gray-50"><tr>@foreach(['Part #', 'Descripción', 'Cant.', 'Precio USD', 'Peso lb'] as $label)<th class="px-3 py-2">{{ $label }}</th>@endforeach</tr></thead><tbody>
                @foreach($preliquidationPreview as $row)<tr>@foreach(['quoted_part_number', 'quoted_description', 'quoted_quantity', 'unit_cost', 'weight_lb'] as $field)<td class="px-3 py-2">{{ $row[$field] }}</td>@endforeach</tr>@endforeach
            </tbody></table></div>
            <div class="flex gap-2"><button type="button" wire:click="$set('preliquidationPreview', [])" class="{{ $secondaryButton }}">Cancelar</button><button type="button" wire:click="confirmPreliquidationImport" wire:loading.attr="disabled" class="{{ $primaryButton }}">Importar {{ count($preliquidationPreview) }} ítems</button></div>
        @endif
    </div>
</details>
