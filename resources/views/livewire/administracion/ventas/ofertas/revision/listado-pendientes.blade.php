        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse($reviews as $review)
                @php
                    $quote = $review->quotationRequest;
                    $subtotal = (float) ($review->offerVersion?->subtotal ?? $quote?->subtotal ?? 0);
                    $total = (float) ($review->offerVersion?->total_amount ?? $quote?->total_amount ?? 0);
                @endphp
                <button type="button" wire:click="selectReview({{ $review->id }})" class="group flex flex-col text-left transition-all hover:-translate-y-1 hover:shadow-lg focus:outline-none">
                    <div class="flex-1 rounded-t-2xl border border-b-0 border-slate-200 bg-white p-5">
                        <div class="flex items-start justify-between gap-3">
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-amber-700">Pendiente</span>
                            <span class="text-[10px] font-bold text-slate-400">{{ optional($review->requested_at)->diffForHumans() }}</span>
                        </div>
                        <h3 class="mt-4 truncate text-base font-black text-slate-900 group-hover:text-indigo-600">{{ $quote?->quote_to_name }}</h3>
                        <p class="mt-1 text-xs font-bold text-slate-500">{{ $quote?->reference }}</p>
                        <p class="mt-3 text-[10px] font-bold text-slate-400">Solicitado por {{ $review->requester?->name ?? 'Sistema' }}</p>
                    </div>
                    <div class="rounded-b-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                        <p class="text-[10px] font-black uppercase text-slate-400">Monto Oferta</p>
                        <p class="text-lg font-black text-slate-900">C$ {{ number_format($total ?: $subtotal, 2) }}</p>
                    </div>
                </button>
            @empty
                <div class="col-span-full flex min-h-[40vh] flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h3 class="mt-4 text-sm font-black text-slate-900">No hay ofertas pendientes</h3>
                    <p class="mt-1 text-xs font-bold text-slate-500">Todo esta al dia, buen trabajo.</p>
                </div>
            @endforelse
        </div>
