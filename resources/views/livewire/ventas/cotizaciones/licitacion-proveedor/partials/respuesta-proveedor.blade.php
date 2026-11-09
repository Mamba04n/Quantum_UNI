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
        try { await $wire.prepareAnotherSupplierResponse(); this.dirtyItems = []; this.detailsDirty = false; this.editingSupplier = true; this.$nextTick(() => this.$refs.supplierName?.focus()); }
        finally { this.saving = false }
    },
    async selectSupplier(id) {
        if (this.saving || this.uploading) return;
        if (String(id) === String($wire.selectedSupplierId)) {
            if (!id) { this.editingSupplier = true; this.$nextTick(() => this.$refs.supplierName?.focus()); }
            return;
        }
        if ((this.dirtyItems.length || this.detailsDirty || $wire.supplierProformas.length) && !confirm('¿Descartar los cambios sin guardar y cambiar de proveedor?')) return;
        this.saving = true;
        try { await $wire.clearSupplierResponseForm(); await $wire.set('selectedSupplierId', String(id)); this.dirtyItems = []; this.detailsDirty = false; this.editingSupplier = false; }
        finally { this.saving = false }
    },
    async saveRows() {
        if(this.saving || this.uploading) return;
        await this.$nextTick();
        const rows = {...$wire.supplierBidItems, ...$wire.supplierBidExtraItems};
        const keys = Object.keys(rows).filter(key => rows[key] && typeof rows[key] === 'object');
        if(this.dirtyItems.includes('__deleted__')) keys.push('__deleted__');
        this.saving = true;
        try {
            if (Object.keys($wire.preliquidation || {}).length) {
                await $wire.saveAllSupplierResponseItems();
            } else {
                await $wire.saveSupplierResponseDraft(keys);
            }
        } finally { this.saving = false }
    },
    focusAdjacentRow(itemId, direction, event) { const rows=[...document.getElementById('supplier-response-workspace').querySelectorAll('[data-supplier-row]')].filter(row => row.offsetParent !== null); const row=rows[rows.findIndex(row=>String(row.dataset.itemId)===String(itemId))+direction]; const target=row?.querySelector(direction>0?'[data-supplier-price]':'[data-supplier-delivery]'); if(target){ event.preventDefault();target.focus();target.select() } }
}"
x-on:supplier-bid-draft-saved.window="dirtyItems = dirtyItems.filter(id => !$event.detail.savedIds.map(String).includes(String(id))); if(!$event.detail.errorIds.length && !$event.detail.conflictIds.length && $event.detail.savedIds.length) detailsDirty = false"
x-on:preliquidation-items-changed.window="detailsDirty = true"
x-on:preliquidation-saved.window="dirtyItems = []; detailsDirty = false"
x-on:rfq-supplier-established.window="editingSupplier = false"
x-on:supplier-bid-line-added.window="markDirty($event.detail.itemKey)"
x-on:supplier-bid-line-removed.window="dirtyItems = dirtyItems.filter(id => id !== String($event.detail.itemKey)); if($event.detail.persisted) markDirty('__deleted__')"
x-on:supplier-bid-draft-loaded.window="dirtyItems = [...new Set([...dirtyItems, ...$event.detail.itemIds.map(String)])]"
x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-cancel="uploading = false"
x-on:beforeunload.window="if(dirtyItems.length || detailsDirty || $wire.supplierProformas.length) { $event.preventDefault(); $event.returnValue = '' }">

    @error('supplierBidBulkFile')<p role="alert" class="mb-3 text-sm text-rose-700">{{ $message }}</p>@enderror
    @error('exchange_rate')<p role="alert" class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ $message }} Registra una tasa vigente antes de capturar la respuesta.</p>@enderror

    @include('livewire.ventas.cotizaciones.licitacion-proveedor.partials.preliquidacion-proveedor')

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
