@if($selectedQuote->offer_review_notes)
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm flex items-start gap-3">
        <div class="mt-0.5 flex-shrink-0 text-amber-500">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div>
            <h4 class="text-xs font-black uppercase tracking-widest text-amber-800">Instrucciones / Motivo de Cambio</h4>
            <p class="mt-1 text-sm font-medium text-amber-900">{{ $selectedQuote->offer_review_notes }}</p>
            <p class="mt-1 text-[10px] font-bold text-amber-700">Ultima actualizacion: {{ optional($selectedQuote->updated_at)->format('d/m/Y h:i A') }}</p>
        </div>
    </div>
@endif
