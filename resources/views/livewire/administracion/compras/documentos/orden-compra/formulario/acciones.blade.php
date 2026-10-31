<div class="sticky bottom-0 z-10 rounded-lg border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur">
    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
        <button type="submit" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-xs font-black text-slate-700 hover:bg-slate-50">Guardar borrador</button>
        <button type="button" wire:click="previewPdf" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-xs font-black text-slate-700 hover:bg-slate-50">Vista previa</button>
        <button type="button" wire:click="{{ $document->pdf_path ? 'regeneratePdf' : 'generatePdf' }}" wire:loading.attr="disabled" class="rounded-xl bg-slate-950 px-5 py-3 text-xs font-black text-white hover:bg-red-800 disabled:opacity-60">
            {{ $document->pdf_path ? 'Regenerar PDF' : 'Generar PDF' }}
        </button>
        @if($document->pdf_path && $document->status !== 'sent_to_provider')
            <button type="button" wire:click="markSentToProvider" wire:loading.attr="disabled" class="rounded-xl bg-emerald-700 px-5 py-3 text-xs font-black text-white hover:bg-emerald-800 disabled:opacity-60">
                Marcar enviada al proveedor
            </button>
        @endif
    </div>
</div>
