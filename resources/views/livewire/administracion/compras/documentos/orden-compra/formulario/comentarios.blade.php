<section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-lg font-black text-slate-950">Comentarios</h2>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <label class="block">
            <span class="text-xs font-black uppercase text-slate-500">Comentarios para proveedor</span>
            <textarea wire:model="supplier_comments" rows="4" class="mt-1 w-full rounded-xl border-slate-200 text-sm"></textarea>
        </label>
        <label class="block">
            <span class="text-xs font-black uppercase text-slate-500">Instrucciones especiales</span>
            <textarea wire:model="special_instructions" rows="4" class="mt-1 w-full rounded-xl border-slate-200 text-sm"></textarea>
        </label>
        @can('purchase_order_document.view_internal_comments')
            <label class="block">
                <span class="text-xs font-black uppercase text-slate-500">Comentarios internos</span>
                <textarea wire:model="internal_comments" rows="4" class="mt-1 w-full rounded-xl border-slate-200 text-sm"></textarea>
            </label>
        @endcan
        <label class="block">
            <span class="text-xs font-black uppercase text-slate-500">Comentarios pie del documento</span>
            <textarea wire:model="document_footer_comments" rows="4" class="mt-1 w-full rounded-xl border-slate-200 text-sm"></textarea>
        </label>
    </div>
</section>
