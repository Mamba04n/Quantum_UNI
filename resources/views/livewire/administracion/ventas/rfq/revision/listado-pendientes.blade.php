<aside class="lg:col-span-4">
    <div class="erp-card">
        <div class="erp-card-header">
            <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-500">RFQ pendientes de revision</h3>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($reviews as $review)
                @php $quote = $review->quotationRequest; @endphp
                <button type="button" wire:click="selectReview({{ $review->id }})" class="block w-full p-4 text-left transition hover:bg-slate-50 {{ $selectedReviewId === $review->id ? 'border-l-4 border-red-700 bg-red-50/50' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <span class="text-[10px] font-black text-slate-800">{{ $quote?->reference }}</span>
                        <span class="text-[9px] font-bold text-slate-400">{{ optional($review->updated_at)->format('d/m H:i') }}</span>
                    </div>
                    <p class="mt-1 truncate text-sm font-black text-slate-900">{{ $review->supplier?->name ?? 'Proveedor' }}</p>
                    <p class="mt-1 text-[10px] font-bold text-slate-500">Cliente {{ $quote?->quote_to_name ?: 'N/D' }} - Solicitado por {{ $quote?->user?->name ?? 'Sistema' }}</p>
                    <div class="mt-3 flex items-center justify-between text-[10px]">
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 font-black uppercase text-amber-700">Pendiente</span>
                        <strong class="text-slate-900">{{ $quote?->items?->count() ?? 0 }} partidas</strong>
                    </div>
                </button>
            @empty
                <div class="p-10 text-center text-xs font-bold uppercase text-slate-400">No hay RFQ pendientes</div>
            @endforelse
        </div>
    </div>
</aside>
