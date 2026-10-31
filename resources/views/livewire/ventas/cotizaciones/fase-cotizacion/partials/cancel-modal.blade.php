<div>
    @if($showCancelQuoteModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <button type="button" class="absolute inset-0 bg-slate-950/60" wire:click="closeCancelQuoteModal"></button>
        <div class="relative w-full max-w-xl rounded-lg bg-white shadow-2xl">
            <div class="border-b border-slate-100 px-6 py-5">
                <h3 class="text-lg font-black text-slate-950">Cancelar orden</h3>
                <p class="mt-1 text-sm font-bold text-slate-500">La cancelacion queda registrada en Activity y Order Tracker. El motivo es obligatorio.</p>
            </div>
            <div class="space-y-4 px-6 py-5">
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Motivo general</label>
                    <select wire:model="cancelQuoteCategory" class="mt-2 w-full rounded-lg border-slate-200 text-sm font-bold text-slate-800">
                        @foreach($cancelQuoteCategories as $categoryKey => $categoryLabel)
                            <option value="{{ $categoryKey }}">{{ $categoryLabel }}</option>
                        @endforeach
                    </select>
                    @error('cancelQuoteCategory') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Justificacion obligatoria</label>
                    <textarea wire:model="cancelQuoteReason" data-testid="cancel-quote-reason" rows="5" class="mt-2 w-full rounded-lg border-slate-200 text-sm" placeholder="Explique por que se cancela esta orden..."></textarea>
                    @error('cancelQuoteReason') <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
                <button type="button" wire:click="closeCancelQuoteModal" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-50">Volver</button>
                <button type="button" wire:click="cancelQuote" data-testid="cancel-quote-submit" class="rounded-lg bg-rose-600 px-4 py-2.5 text-xs font-black text-white hover:bg-rose-700">Confirmar cancelacion</button>
            </div>
        </div>
    </div>
    @endif
</div>
