<div class="space-y-6">
    {{-- Filtros de Tipo --}}
    @if($requests->isNotEmpty() || $typeFilter !== '')
        <div class="flex items-center gap-2 pb-2">
            <button type="button" wire:click="$set('typeFilter', '')" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all {{ $typeFilter === '' ? 'bg-slate-900 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                Todas ({{ $requests->count() }})
            </button>
            <button type="button" wire:click="$set('typeFilter', 'cxp')" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all {{ $typeFilter === 'cxp' ? 'bg-rose-600 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                Cuentas por Pagar (CXP)
            </button>
            <button type="button" wire:click="$set('typeFilter', 'cxc')" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all {{ $typeFilter === 'cxc' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                Cuentas por Cobrar (CXC)
            </button>
        </div>
    @endif

    {{-- Grid de Solicitudes Pendientes --}}
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($requests as $request)
            @php
                $isCxp = $request->type === \App\Models\FinanceChangeRequest::TYPE_CXP;
                $originalAmount = (float) ($request->original_data['total_amount'] ?? 0);
                $newAmount = (float) ($request->requested_data['total_amount'] ?? $originalAmount);
                $partyName = $request->partyName();
                $concept = $request->accountConcept();
                $code = $request->accountCode();
            @endphp
            <button type="button" wire:key="finance-request-card-{{ $request->id }}" wire:click="selectRequest({{ $request->id }})" class="group flex flex-col text-left transition-all hover:-translate-y-1 hover:shadow-lg focus:outline-none rounded-2xl overflow-hidden border border-slate-200 bg-white">
                <div class="flex-1 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $isCxp ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200' }}">
                            {{ $isCxp ? 'CXP Modificación' : 'CXC Modificación' }}
                        </span>
                        <span class="text-[10px] font-bold text-slate-400">{{ optional($request->created_at)->diffForHumans() }}</span>
                    </div>

                    <h3 class="mt-4 truncate text-base font-black text-slate-900 group-hover:text-indigo-600" title="{{ $partyName }}">
                        {{ $partyName }}
                    </h3>
                    <p class="mt-1 text-xs font-bold text-slate-500 font-mono">{{ $code }}</p>
                    <p class="mt-1 text-xs text-slate-600 line-clamp-1" title="{{ $concept }}">{{ $concept }}</p>

                    <div class="mt-3 rounded-lg bg-slate-50 border border-slate-100 p-2 text-[11px] text-slate-600">
                        <span class="font-bold text-slate-700 block text-[10px] uppercase tracking-wider mb-0.5">Motivo:</span>
                        <p class="line-clamp-2 italic text-slate-600">"{{ $request->justification }}"</p>
                    </div>

                    <p class="mt-3 text-[10px] font-bold text-slate-400">
                        Solicitado por <span class="text-slate-700 font-extrabold">{{ $request->requester?->name ?? 'Sistema' }}</span>
                    </p>
                </div>

                <div class="border-t border-slate-100 bg-slate-50/80 px-5 py-3.5 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400">Monto Solicitado</p>
                        <p class="text-base font-black text-slate-900">C$ {{ number_format($newAmount, 2) }}</p>
                    </div>
                    @if($originalAmount !== $newAmount)
                        <div class="text-right">
                            <span class="text-[10px] font-bold text-rose-500 line-through">C$ {{ number_format($originalAmount, 2) }}</span>
                            <span class="block text-[9px] font-black {{ $newAmount > $originalAmount ? 'text-teal-700' : 'text-amber-700' }}">
                                {{ $newAmount > $originalAmount ? '▲ Incremento' : '▼ Reducción' }}
                            </span>
                        </div>
                    @endif
                </div>
            </button>
        @empty
            <div class="col-span-full flex min-h-[40vh] flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white text-center py-16 px-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h3 class="mt-4 text-sm font-black text-slate-900">No hay modificaciones pendientes</h3>
                <p class="mt-1 text-xs font-bold text-slate-500">Todo esta al dia, buen trabajo.</p>
            </div>
        @endforelse
    </div>
</div>
