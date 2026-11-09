<section class="mb-3 border-b border-gray-100 pb-3" aria-label="Proveedor del RFQ">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">{{ blank($selectedSupplierId) ? 'Asignar proveedor al RFQ' : 'Proveedores de esta cotización' }}</h3>
            <p class="mt-0.5 text-sm text-gray-500">{{ blank($selectedSupplierId) ? 'Este RFQ no tiene proveedor. Indica quién respondió para guardar su preliquidación.' : 'Selecciona un proveedor para trabajar en sus partidas y precios.' }}</p>
        </div>
        @if(filled($selectedSupplierId))
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" x-on:click="editingSupplier = !editingSupplier" x-bind:aria-expanded="editingSupplier" class="{{ $secondaryButton }}">Datos del proveedor</button>
                <button type="button" x-bind:disabled="saving || uploading" x-on:click="addSupplier()" class="{{ $secondaryButton }}">+ Agregar proveedor</button>
            </div>
        @endif
    </div>

    @if($responseSupplierDocuments->isNotEmpty())
        <nav aria-label="Proveedores agregados" class="mt-3 flex flex-wrap gap-2">
            @foreach($responseSupplierDocuments as $document)
                @php
                    $isSelected = (string) $document->supplier_id === $selectedSupplierId;
                    $tabResponse = $selectedQuote->supplierBidResponses->where('supplier_id', $document->supplier_id)->sortByDesc('id')->first();
                @endphp
                <button type="button" x-bind:disabled="saving || uploading" x-on:click="selectSupplier(@js((string) $document->supplier_id))" aria-pressed="{{ $isSelected ? 'true' : 'false' }}" class="rounded-xl border px-3 py-2 text-left text-sm transition-colors {{ $isSelected ? 'border-slate-800 bg-slate-800 text-white shadow-sm' : 'border-gray-200 bg-white text-slate-700 hover:bg-gray-50' }}">
                    <span class="flex items-center gap-3"><span class="font-semibold">{{ $document->supplier->name }}</span>@if($isSelected)<span class="text-xs text-slate-200">Activo</span>@endif</span>
                    <span class="mt-0.5 block text-xs {{ $isSelected ? 'text-slate-300' : 'text-gray-500' }}">{{ $tabResponse ? 'Respuesta registrada' : 'Pendiente de respuesta' }}</span>
                </button>
            @endforeach
            @if($openResponseRfqDocument)
                <button type="button" x-bind:disabled="saving || uploading" x-on:click="selectSupplier('')" aria-pressed="{{ blank($selectedSupplierId) ? 'true' : 'false' }}" class="rounded-xl border px-3 py-2 text-left text-sm {{ blank($selectedSupplierId) ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-gray-200 bg-white text-slate-700 hover:bg-gray-50' }}">
                    <span class="block font-semibold">RFQ sin proveedor</span><span class="block text-xs">Asignar proveedor</span>
                </button>
            @endif
        </nav>
    @endif

    @if(blank($selectedSupplierId))
        @if($openResponseRfqDocument)
            <div class="preliq-supplier-editor mt-3">
                <div class="grid items-end gap-3 md:grid-cols-3">
                    <label class="block text-sm font-medium text-gray-700">Nombre del proveedor que respondió
                        <input x-ref="supplierName" wire:model="responseSupplierName" wire:keydown.enter="establishOpenRfqSupplier" aria-describedby="supplier-name-help" class="mt-1 w-full rounded-xl border-gray-200 text-sm" placeholder="Escribe el nombre del proveedor">
                    </label>
                    <label class="block text-sm font-medium text-gray-700">Contacto <span class="font-normal text-gray-400">(opcional)</span>
                        <input wire:model="supplierContactName" class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                    </label>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" wire:click="establishOpenRfqSupplier" wire:loading.attr="disabled" wire:target="establishOpenRfqSupplier" x-bind:disabled="saving || uploading" class="{{ $primaryButton }}">
                            <span wire:loading.remove wire:target="establishOpenRfqSupplier">Asignar proveedor</span><span wire:loading wire:target="establishOpenRfqSupplier">Asignando…</span>
                        </button>
                        @if($responseSupplierDocuments->isNotEmpty())
                            <button type="button" wire:click="discardOpenRfqDocument" wire:confirm="¿Descartar este RFQ sin proveedor?" wire:loading.attr="disabled" wire:target="discardOpenRfqDocument,establishOpenRfqSupplier" class="text-sm font-medium text-gray-500 hover:text-rose-700">Descartar RFQ sin proveedor</button>
                        @endif
                    </div>
                </div>
                <p id="supplier-name-help" class="mt-2 text-xs text-gray-500">Si el proveedor ya existe, utiliza su mismo nombre. Al asignarlo aparecerá arriba como activo.</p>
            </div>
        @else
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <p class="text-sm text-gray-500">Agrega un proveedor para registrar su respuesta.</p>
                <button type="button" x-bind:disabled="saving || uploading" x-on:click="addSupplier()" class="{{ $primaryButton }}">+ Agregar proveedor</button>
            </div>
        @endif
    @else
        <div class="preliq-supplier-editor mt-3" x-show="editingSupplier" x-cloak>
            <div class="flex flex-wrap items-end gap-3">
                <label class="block text-sm font-medium text-gray-700">Contacto <span class="font-normal text-gray-400">(opcional)</span><input wire:model="supplierContactName" x-on:input="detailsDirty = true" class="mt-1 w-full rounded-xl border-gray-200 text-sm"></label>
                <button type="button" x-on:click="editingSupplier = false" class="{{ $secondaryButton }}">Cerrar datos</button>
                @if($selectedRfqDocument?->status === \App\Models\SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER)
                    <button type="button" wire:click="markSelectedSupplierNoResponse({{ $selectedRfqDocument->id }})" wire:confirm="¿Marcar este proveedor como sin respuesta?" wire:loading.attr="disabled" wire:target="markSelectedSupplierNoResponse" class="{{ $secondaryButton }}">Marcar sin respuesta</button>
                @elseif($selectedRfqDocument?->status === \App\Models\SupplierRfqDocument::STATUS_GENERATED)
                    <button type="button" wire:click="markRfqSent" wire:loading.attr="disabled" wire:target="markRfqSent" class="{{ $secondaryButton }}">Marcar RFQ enviado</button>
                @endif
            </div>
            <p class="mt-2 text-xs text-gray-500">Los datos de contacto se guardan con la preliquidación.</p>
        </div>
    @endif
    @error('selectedSupplierId')<p role="alert" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
    @error('responseSupplierName')<p role="alert" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
</section>
