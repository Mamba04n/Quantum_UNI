<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
    @forelse($quotes as $quote)
        @php
            $isFromAdmin = $quote->status === \App\Models\QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED;
            $badgeColor = $isFromAdmin ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700';
            $badgeText = $isFromAdmin ? 'Admin Requiere Cambios' : 'Cliente Requiere Cambios';
        @endphp
        <button type="button" wire:click="selectQuote({{ $quote->id }})" class="group flex flex-col text-left transition-all hover:-translate-y-1 hover:shadow-lg focus:outline-none">
            <div class="flex-1 rounded-t-2xl border border-b-0 border-slate-200 bg-white p-5">
                <div class="flex items-start justify-between gap-3">
                    <span class="rounded-full {{ $badgeColor }} px-2.5 py-1 text-[10px] font-black uppercase tracking-wider">{{ $badgeText }}</span>
                    <span class="text-[10px] font-bold text-slate-400">{{ optional($quote->updated_at)->diffForHumans() }}</span>
                </div>
                <h3 class="mt-4 truncate text-base font-black text-slate-900 group-hover:text-indigo-600">{{ $quote->quote_to_name }}</h3>
                <p class="mt-1 text-xs font-bold text-slate-500">{{ $quote->reference }}</p>
                @if($quote->offer_review_notes)
                    <p class="mt-3 text-[11px] font-medium text-slate-600 italic line-clamp-2">"{{ $quote->offer_review_notes }}"</p>
                @endif
            </div>
            <div class="rounded-b-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                <p class="text-[10px] font-black uppercase text-slate-400">Total Actual</p>
                <p class="text-lg font-black text-slate-900">C$ {{ number_format($quote->total_amount, 2) }}</p>
            </div>
        </button>
    @empty
        @include('livewire.ventas.cotizaciones.correcciones-orden.partials.sin-correcciones')
    @endforelse
</div>
