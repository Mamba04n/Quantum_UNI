<main class="mx-auto w-full max-w-5xl space-y-6">
    @php
        $isCxp = $selectedRequest->type === \App\Models\FinanceChangeRequest::TYPE_CXP;
        $partyName = $selectedRequest->partyName();
        $code = $selectedRequest->accountCode();
        $concept = $selectedRequest->accountConcept();
        $originalAmount = (float) ($selectedRequest->original_data['total_amount'] ?? 0);
        $newAmount = (float) ($selectedRequest->requested_data['total_amount'] ?? $originalAmount);
        $changes = $selectedRequest->changes_diff ?? [];
    @endphp

    {{-- Barra superior de retorno --}}
    <div class="flex items-center justify-between rounded-xl bg-white p-4 shadow-sm border border-slate-200">
        <button type="button" wire:click="selectRequest(null)" class="flex items-center gap-2 text-xs font-black text-slate-600 transition hover:text-slate-900">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Volver a pendientes
        </button>
        <div class="flex items-center gap-3">
            <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider {{ $isCxp ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200' }}">
                {{ $isCxp ? 'Cuenta por Pagar' : 'Cuenta por Cobrar' }}
            </span>
            <div class="text-right">
                <p class="text-[10px] font-black uppercase text-slate-400">Código</p>
                <p class="font-mono font-bold text-xs text-slate-900">{{ $code }}</p>
            </div>
        </div>
    </div>

    {{-- Tarjeta Principal / Panel de Revisión --}}
    <div class="shadow-md border-0 rounded-2xl overflow-hidden bg-white">
        {{-- Banner Principal --}}
        <header class="bg-slate-900 px-8 py-8 text-white relative overflow-hidden">
            <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="inline-flex rounded-full bg-amber-500/20 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-amber-300 border border-amber-500/30">
                        Revision Requerida
                    </span>
                    <h3 class="mt-4 text-2xl lg:text-3xl font-black">{{ $partyName }}</h3>
                    <p class="mt-2 text-xs font-medium text-slate-300">
                        {{ $code }} &middot; {{ $concept }} &middot; Solicitado por <strong class="text-white font-bold">{{ $selectedRequest->requester?->name ?? 'Sistema' }}</strong> ({{ optional($selectedRequest->created_at)->format('d/m/Y h:i A') }})
                    </p>
                </div>
                <div class="text-right bg-white/10 backdrop-blur-md px-5 py-3 rounded-xl border border-white/10">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-300">Nuevo Monto Solicitado</p>
                    <p class="text-xl font-black text-white">C$ {{ number_format($newAmount, 2) }}</p>
                    @if($originalAmount !== $newAmount)
                        <p class="text-[10px] text-rose-300 line-through">Anterior: C$ {{ number_format($originalAmount, 2) }}</p>
                    @endif
                </div>
            </div>
        </header>

        <div class="p-8 space-y-8">
            {{-- Motivo / Justificación del Solicitante --}}
            <section class="rounded-xl border border-amber-200 bg-amber-50/60 p-5">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-xs font-black uppercase tracking-wider text-amber-900">Motivo de la Modificación ingresado por el solicitante:</h4>
                        <p class="mt-1.5 text-sm font-semibold text-slate-800 leading-relaxed bg-white/80 border border-amber-200/60 p-3 rounded-lg">
                            "{{ $selectedRequest->justification }}"
                        </p>
                    </div>
                </div>
            </section>

            {{-- Tabla Comparativa de Cambios --}}
            <section>
                <h4 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Comparativo Detallado de Cambios
                </h4>
                <div class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-sm">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-100 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Campo Modificado</th>
                                <th class="px-4 py-3 text-rose-700">Valor Anterior (Actual en Sistema)</th>
                                <th class="px-4 py-3 text-teal-700">Nuevo Valor Solicitado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($changes as $change)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-4 py-3 font-bold text-slate-800">{{ $change['field'] ?? '—' }}</td>
                                    <td class="px-4 py-3 font-mono text-rose-600 line-through">{{ $change['before'] ?? '—' }}</td>
                                    <td class="px-4 py-3 font-mono font-bold text-teal-700 bg-teal-50/30">{{ $change['after'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-4 text-center text-slate-400">Actualización general de datos.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Documento de Soporte / Evidencia --}}
            @php $supportPath = $selectedRequest->support_document_path ?? $selectedRequest->original_data['support_document_path'] ?? null; @endphp
            @if($supportPath)
                <section class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-900">Documento de Soporte Adjunto</p>
                            <p class="text-[11px] text-slate-500 font-mono">{{ basename($supportPath) }}</p>
                        </div>
                    </div>
                    <a href="{{ ($supportPath && \Illuminate\Support\Facades\Storage::disk('private')->exists($supportPath)) ? route('finance.change-requests.download-support', $selectedRequest) : '#' }}" target="_blank" class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs font-bold text-slate-700 hover:bg-slate-100 transition-colors inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Ver / Descargar Soporte
                    </a>
                </section>
            @endif

            {{-- Panel de Decisión y Acciones del Administrador --}}
            <section class="border-t border-slate-200 pt-6 space-y-4">
                <h4 class="text-xs font-black uppercase tracking-widest text-slate-700">Resolución de Administración</h4>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        Notas o Comentarios del Administrador <span class="text-[11px] font-normal text-slate-400">(Obligatorio únicamente si vas a rechazar)</span>
                    </label>
                    <textarea wire:model="adminNotes" rows="2" placeholder="Ingresa comentarios de autorización o especifica el motivo si decides rechazar..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all"></textarea>
                    @error('adminNotes') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col sm:flex-row items-center gap-3 pt-2">
                    <button type="button" wire:click="reject" wire:confirm="¿Estás seguro de que deseas rechazar esta solicitud de modificación?" wire:loading.attr="disabled" class="w-full sm:w-auto px-5 py-3 rounded-xl border border-rose-300 bg-rose-50 text-rose-700 font-bold text-xs hover:bg-rose-100 transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Rechazar Modificación
                    </button>

                    <button type="button" wire:click="approve" wire:confirm="¿Confirmas que autorizas estos cambios para aplicarse inmediatamente a la cuenta?" wire:loading.attr="disabled" class="w-full sm:flex-1 px-6 py-3 rounded-xl bg-slate-900 hover:bg-black text-white font-bold text-xs transition-colors flex items-center justify-center gap-2 shadow-sm">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span wire:loading.remove wire:target="approve">Autorizar y Aplicar Cambio en la Cuenta</span>
                        <span wire:loading wire:target="approve">Aplicando cambio...</span>
                    </button>
                </div>
            </section>
        </div>
    </div>
</main>
