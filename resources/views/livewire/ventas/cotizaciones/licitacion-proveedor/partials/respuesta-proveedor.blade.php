@php
    $responseSupplierDocuments = $selectedQuote->supplierRfqDocuments->filter(fn ($document) => filled($document->supplier_id) && $document->supplier)->unique('supplier_id');
    $openResponseRfqDocument = $selectedQuote->supplierRfqDocuments->first(fn ($document) => blank($document->supplier_id));
    $selectedRfqDocument = filled($selectedSupplierId) ? $selectedQuote->supplierRfqDocuments->firstWhere('supplier_id', (int) $selectedSupplierId) : $openResponseRfqDocument;
    $responseItems = $this->itemsForSupplierResponse($selectedQuote, $selectedRfqDocument);
    $activeSupplierResponse = $selectedQuote->supplierBidResponses->firstWhere('id', $activeSupplierBidResponseId)
        ?: $selectedQuote->supplierBidResponses->where('supplier_id', (int) $selectedSupplierId)->sortByDesc('id')->first();
    $savedSupplierDocuments = $activeSupplierResponse?->supplierDocumentAttachments ?? collect();
    $activeSupplierResponse?->loadMissing('items.attachments');
    $coverage = $this->supplierResponseCoverage($selectedQuote);
    $responseWorkspace = true;
    $savedResponsesBySupplier = $selectedQuote->supplierBidResponses->sortByDesc('id')->unique('supplier_id')->pluck('id', 'supplier_id');
    $primaryButton = 'inline-flex items-center justify-center rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm disabled:opacity-50';
    $secondaryButton = 'inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 disabled:opacity-50';
@endphp
<section id="supplier-response-workspace" aria-label="Preliquidación" class="min-w-0 preliq-workspace" x-data="{
    editingSupplier: @js(blank($selectedSupplierId)), dirtyItems: [], detailsDirty: false, saving: false, uploading: false,
    markDirty(id) { id = String(id); if(!this.dirtyItems.includes(id)) this.dirtyItems.push(id) },
    async addSupplier() {
        if (this.saving || this.uploading) return;
        if ((this.dirtyItems.length || this.detailsDirty || $wire.supplierProformas.length) && !confirm('¿Descartar cambios y agregar proveedor?')) return;
        this.saving = true;
        try { await $wire.prepareAnotherSupplierResponse(); this.dirtyItems = []; this.detailsDirty = false; this.editingSupplier = true; }
        finally { this.saving = false }
    },
    async selectSupplier(id) {
        if (this.saving || this.uploading || String(id) === String($wire.selectedSupplierId)) return;
        if ((this.dirtyItems.length || this.detailsDirty || $wire.supplierProformas.length) && !confirm('¿Descartar los cambios sin guardar y cambiar de proveedor?')) return;
        this.saving = true;
        try { await $wire.clearSupplierResponseForm(); await $wire.set('selectedSupplierId', String(id)); this.dirtyItems = []; this.detailsDirty = false; this.editingSupplier = false; }
        finally { this.saving = false }
    },
    async changeSupplier(event) {
        const id = event.target.value;
        const response = event.target.selectedOptions[0]?.dataset.responseId;
        if((this.dirtyItems.length || this.detailsDirty || $wire.supplierProformas.length) && !confirm('¿Descartar los cambios sin guardar y cambiar de proveedor?')) { event.target.value = $wire.selectedSupplierId; return }
        this.saving = true;
        try {
            await $wire.clearSupplierResponseForm();
            $wire.set('supplierItemProformas', [], false);
            await $wire.set('selectedSupplierId', id);
            if(response) await $wire.editSupplierResponse(Number(response));
            this.dirtyItems = []; this.detailsDirty = false;
        } finally { this.saving = false }
    },
    async saveRows() {
        if(this.saving || this.uploading) return;
        await this.$nextTick();
        const rows = {...$wire.supplierBidItems, ...$wire.supplierBidExtraItems};
        const keys = Object.keys(rows).filter(key => rows[key] && typeof rows[key] === 'object');
        if(this.dirtyItems.includes('__deleted__')) keys.push('__deleted__');
        this.saving = true;
        try { await $wire.saveSupplierResponseDraft(keys); } finally { this.saving = false }
    },
    focusAdjacentRow(itemId, direction, event) { const rows=[...document.getElementById('supplier-response-workspace').querySelectorAll('[data-supplier-row]')].filter(row => row.offsetParent !== null); const row=rows[rows.findIndex(row=>String(row.dataset.itemId)===String(itemId))+direction]; const target=row?.querySelector(direction>0?'[data-supplier-price]':'[data-supplier-delivery]'); if(target){ event.preventDefault();target.focus();target.select() } }
}"
x-on:supplier-bid-draft-saved.window="dirtyItems = dirtyItems.filter(id => !$event.detail.savedIds.map(String).includes(String(id))); if(!$event.detail.errorIds.length && !$event.detail.conflictIds.length && $event.detail.savedIds.length) detailsDirty = false"
x-on:preliquidation-items-changed.window="detailsDirty = true"
x-on:supplier-bid-line-added.window="markDirty($event.detail.itemKey)"
x-on:supplier-bid-line-removed.window="dirtyItems = dirtyItems.filter(id => id !== String($event.detail.itemKey)); if($event.detail.persisted) markDirty('__deleted__')"
x-on:supplier-bid-draft-loaded.window="dirtyItems = [...new Set([...dirtyItems, ...$event.detail.itemIds.map(String)])]"
x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-cancel="uploading = false"
x-on:beforeunload.window="if(dirtyItems.length || detailsDirty || $wire.supplierProformas.length) { $event.preventDefault(); $event.returnValue = '' }">

    @error('supplierBidBulkFile')<p role="alert" class="mb-3 text-sm text-rose-700">{{ $message }}</p>@enderror
    @error('exchange_rate')<p role="alert" class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ $message }} Registra una tasa vigente antes de capturar la respuesta.</p>@enderror

    <nav aria-label="Proveedores de la preliquidación" class="mb-3 flex flex-wrap items-center gap-2">
        @foreach($responseSupplierDocuments as $document)
            @php
                $tabResponse = $selectedQuote->supplierBidResponses->where('supplier_id', $document->supplier_id)->sortByDesc('id')->first();
                $tabItems = $tabResponse?->items ?? collect();
                $tabTotals = (string)$document->supplier_id === $selectedSupplierId ? $this->preliquidationTotals() : ($tabResponse ? app(\App\Services\PreliquidationCalculator::class)->forResponse($tabResponse) : ['count' => 0, 'exw' => 0]);
                $tabTransport = (string)$document->supplier_id === $selectedSupplierId
                    ? ($this->preliquidation['transport'] ?? 'air')
                    : ($tabResponse?->preliquidation['settings']['transport'] ?? 'air');
                $isSea = $tabTransport === 'sea';
                $isSelected = (string)$document->supplier_id === $selectedSupplierId;
            @endphp
            <button type="button" x-bind:disabled="saving || uploading" x-on:click="selectSupplier(@js((string) $document->supplier_id))" aria-pressed="{{ $isSelected ? 'true' : 'false' }}" class="rounded-xl border px-3 py-2 text-left text-sm transition-colors {{ $isSelected ? 'border-slate-800 bg-slate-800 text-white shadow-sm' : 'border-gray-200 bg-white text-slate-700 hover:bg-gray-50' }}">
                <span class="flex items-center justify-between gap-3">
                    <span class="block font-semibold">{{ $document->supplier->name }}</span>
                    <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-bold tracking-wide {{ $isSelected ? ($isSea ? 'bg-cyan-900/60 text-cyan-200 border border-cyan-700' : 'bg-sky-900/60 text-sky-200 border border-sky-700') : ($isSea ? 'bg-cyan-50 text-cyan-700 border border-cyan-200' : 'bg-sky-50 text-sky-700 border border-sky-200') }}">
                        {{ $isSea ? '🚢 Marítimo' : '✈️ Aéreo' }}
                    </span>
                </span>
                <span class="text-xs {{ $isSelected ? 'text-slate-300' : 'text-gray-500' }}">{{ $tabTotals['count'] }} partidas · USD {{ number_format($tabTotals['exw'], 2) }} EXW</span>
            </button>
        @endforeach
        @if($openResponseRfqDocument)
            <button type="button" x-bind:disabled="saving || uploading" x-on:click="selectSupplier('')" aria-pressed="{{ blank($selectedSupplierId) ? 'true' : 'false' }}" class="rounded-xl border border-dashed px-3 py-2 text-left text-sm transition-colors {{ blank($selectedSupplierId) ? 'border-amber-600 bg-amber-50 text-amber-900 shadow-sm' : 'border-amber-300 bg-white text-amber-800 hover:bg-amber-50/50' }}">
                <span class="flex items-center justify-between gap-3">
                    <span class="block font-semibold">Nuevo proveedor</span>
                    <span class="rounded bg-amber-200/80 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">Pendiente</span>
                </span>
                <span class="text-xs text-amber-700">En captura</span>
            </button>
        @endif
        <button type="button" x-bind:disabled="saving || uploading" x-on:click="addSupplier()" class="{{ $secondaryButton }}">+ Proveedor</button>
    </nav>
    <div class="preliq-supplier-editor" x-show="editingSupplier" x-cloak>
        <div x-show="editingSupplier" class="space-y-3">
            <div class="grid gap-3 md:grid-cols-3">
                <label class="block text-sm font-medium text-gray-700">Proveedor
                    <select x-on:change="changeSupplier($event); editingSupplier = false" x-bind:disabled="saving" class="mt-1 w-full rounded-xl border-gray-200 text-sm">
                        <option value="">Proveedor manual / RFQ abierto</option>
                        @foreach($responseSupplierDocuments as $document)<option value="{{ $document->supplier_id }}" data-response-id="{{ $savedResponsesBySupplier[$document->supplier_id] ?? '' }}" @selected((string)$document->supplier_id === $selectedSupplierId)>{{ $document->supplier->name }}</option>@endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium text-gray-700">Moneda<select wire:model.live="supplierCurrency" x-on:change="detailsDirty = true" class="mt-1 w-full rounded-xl border-gray-200 text-sm">@foreach(\App\Models\Currency::where('is_active', true)->orderBy('code')->get() as $currency)<option value="{{ $currency->code }}">{{ $currency->code }}</option>@endforeach</select></label>
                <label class="block text-sm font-medium text-gray-700">Contacto <span class="font-normal text-gray-400">(opcional)</span><input wire:model="supplierContactName" x-on:input="detailsDirty = true" class="mt-1 w-full rounded-xl border-gray-200 text-sm"></label>
            </div>
            @if(blank($selectedSupplierId))
                @if($openResponseRfqDocument)
                    <div class="flex flex-wrap items-end gap-3">
                        <label class="block flex-1 min-w-[240px] text-sm font-medium text-gray-700">Nombre del proveedor<input wire:model="responseSupplierName" class="mt-1 w-full rounded-xl border-gray-200 text-sm" placeholder="Proveedor que respondió"></label>
                        <button type="button" wire:click="establishOpenRfqSupplier" wire:loading.attr="disabled" class="{{ $primaryButton }}">Guardar proveedor</button>
                        <button type="button" wire:click="discardOpenRfqDocument" wire:loading.attr="disabled" class="{{ $secondaryButton }} text-rose-700 border-rose-200 hover:bg-rose-50">Descartar</button>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Selecciona un proveedor con RFQ enviado o agrega otro proveedor.</p>
                @endif
            @endif
            @if(!$openResponseRfqDocument && $responseSupplierDocuments->isNotEmpty())
                <button type="button" x-on:click="addSupplier()" x-bind:disabled="saving || uploading" class="{{ $secondaryButton }}">Otro proveedor</button>
            @endif
            @if($selectedRfqDocument?->status === \App\Models\SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER)
                <button type="button" wire:click="markSelectedSupplierNoResponse({{ $selectedRfqDocument->id }})" wire:confirm="¿Marcar este proveedor como sin respuesta?" wire:loading.attr="disabled" class="{{ $secondaryButton }}">Marcar sin respuesta</button>
            @elseif($selectedRfqDocument?->status === \App\Models\SupplierRfqDocument::STATUS_GENERATED)
                <button type="button" wire:click="markRfqSent" wire:loading.attr="disabled" class="{{ $secondaryButton }}">Marcar RFQ enviado</button>
            @endif
        </div>
        @error('selectedSupplierId')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
        @error('responseSupplierName')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
    </div>

    <fieldset x-bind:disabled="saving || uploading" class="min-w-0">
        @include('livewire.ventas.cotizaciones.licitacion-proveedor.partials.preliquidacion')
    </fieldset>
    <details class="preliq-disclosure group">
        <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-xs transition hover:bg-slate-50 focus:outline-none">
            <svg class="h-3.5 w-3.5 text-slate-500 transition-transform duration-200 group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            <span>Documentos y observaciones</span>
        </summary>
        <div class="mt-3 grid gap-4 border-t border-gray-100 pt-4 lg:grid-cols-2">
        <div class="min-w-0 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="text-sm font-semibold text-gray-900">Documentos</h3>
            <label class="{{ $secondaryButton }} cursor-pointer">Adjuntar documento<input type="file" wire:model="supplierProformas" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.xlsx,.xls,.csv" class="sr-only"></label>
        </div>
        <ul class="divide-y divide-gray-100 text-sm">
            @foreach($savedSupplierDocuments as $document)<li class="flex items-center justify-between gap-3 py-2"><a href="{{ route('tracker.attachments.show', $document) }}" target="_blank" rel="noopener" class="min-w-0 truncate rounded px-2 py-1 text-slate-700">{{ $document->file_name }}</a><button type="button" wire:click="deleteSupplierResponseDocument({{ $document->id }})" wire:confirm="¿Eliminar este documento?" wire:loading.attr="disabled" class="rounded px-2 py-1 text-rose-700">Eliminar</button></li>@endforeach
            @foreach($supplierProformas as $index => $document)<li class="flex items-center justify-between gap-3 py-2"><span class="truncate">{{ $document->getClientOriginalName() }} · Sin guardar</span><button type="button" wire:click="removePendingSupplierProforma({{ $index }})" class="rounded px-2 py-1">Quitar</button></li>@endforeach
        </ul>
        @if(!$savedSupplierDocuments->count() && !count($supplierProformas))<p class="text-sm text-gray-500">Sin documentos adjuntos.</p>@endif
        @error('supplierProformas')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
        @foreach($errors->get('supplierProformas.*') as $messages)@foreach($messages as $message)<p class="text-sm text-rose-700">{{ $message }}</p>@endforeach @endforeach
        @if(count($supplierProformas))<p class="text-xs text-gray-500">Se adjuntarán al guardar la preliquidación.</p>@endif
        </div>
        <label class="block text-sm font-medium text-gray-700">Observaciones internas<textarea wire:model="supplierNotes" x-on:input="detailsDirty = true" rows="3" class="mt-1 w-full rounded-xl border-gray-200 text-sm"></textarea></label>
    </div>
    </details>
    @include('livewire.ventas.cotizaciones.licitacion-proveedor.partials.preliquidacion-audit')
    <div class="preliq-actionbar">
        <p x-show="dirtyItems.length || detailsDirty || $wire.supplierProformas.length" role="status" class="text-sm text-amber-800">Guarda los cambios pendientes antes de continuar.</p>
        @if($coverage['responded'] === 0)<p class="text-sm text-amber-800">Se necesita al menos una respuesta válida para avanzar.</p>@endif
        @include('livewire.ventas.cotizaciones.fase-cotizacion.partials.process-actions', ['preliquidationActions' => true])

    </div>
    @include('livewire.ventas.cotizaciones.licitacion-proveedor.partials.importar-respuesta')
</section>
