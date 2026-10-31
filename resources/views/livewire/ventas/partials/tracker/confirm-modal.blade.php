<div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 transition-opacity" wire:click="$set('showConfirmModal', false)"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden animate-in fade-in zoom-in duration-200 border border-slate-200">

        {{-- Encabezado del modal: sobrio y directo --}}
        <div class="bg-slate-50 p-8 border-b border-slate-100">
            <div class="flex items-center gap-5">
                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center border border-slate-200 shadow-sm">
                    <svg class="w-6 h-6 text-slate-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold tracking-tight text-slate-800">Validación de Conversión</h3>
                    <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-1">Confirmación de Pedido Maestro</p>
                </div>
            </div>
        </div>

        {{-- Modal Body --}}
        <div class="p-8 space-y-6">
            {{-- Summary Stats --}}
            <div class="grid grid-cols-2 gap-4">
                <div class="p-5 bg-white rounded-2xl border border-slate-100 shadow-sm">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Partidas Aprobadas</p>
                    <p class="text-2xl font-black text-slate-800">{{ $confirmSummary['items_count'] }} <span class="text-xs font-medium text-slate-400 italic">ítems</span></p>
                </div>
                <div class="p-5 bg-white rounded-2xl border border-slate-100 shadow-sm">
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Items Excluidos</p>
                    <p class="text-2xl font-black text-rose-600">{{ $confirmSummary['excluded_count'] }} <span class="text-xs font-medium text-slate-400 italic">ítems</span></p>
                </div>
            </div>

            {{-- Data Card --}}
            <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200">
                <div class="space-y-4">
                    <div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Consignatario / Cliente</p>
                        <p class="text-base font-bold text-slate-900">{{ $confirmSummary['client'] }}</p>
                    </div>
                    <div class="pt-4 border-t border-slate-200 flex justify-between items-end">
                        <div>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Valor FOB Total</p>
                            <p class="text-[10px] text-slate-500 italic">Monto a registrar en sistema</p>
                        </div>
                        <p class="text-3xl font-black text-slate-900">${{ number_format($confirmSummary['total'], 2) }}</p>
                    </div>
                </div>
            </div>

            {{-- Info Note --}}
            <div class="flex items-start gap-3 px-4 py-3 bg-blue-50/50 rounded-xl border border-blue-100 text-blue-800">
                <svg class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <p class="text-[11px] font-medium leading-relaxed">
                    Esta acción generará un pedido oficial y cerrará la fase de cotización. Verifique que los totales coincidan con la orden del cliente.
                </p>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Motivo / observacion</label>
                <textarea wire:model="approvalReason" class="w-full h-24 rounded-xl border-slate-200 text-xs focus:ring-slate-500 focus:border-slate-500" placeholder="Esta accion enviara una solicitud de aprobacion. No se creara el pedido hasta que un admin la apruebe."></textarea>
            </div>
        </div>

        {{-- Modal Footer --}}
        <div class="px-8 py-6 bg-slate-50 border-t border-slate-200 flex gap-3">
            <button wire:click="requestForeignOrderApproval"
                    wire:loading.attr="disabled"
                    class="flex-1 flex items-center justify-center gap-2 bg-slate-900 hover:bg-black text-white text-[11px] font-bold py-4 rounded-xl transition-all shadow-lg uppercase tracking-wider disabled:opacity-70 disabled:cursor-wait">
                <span wire:loading.remove wire:target="requestForeignOrderApproval">Enviar solicitud de aprobacion</span>
                <span wire:loading wire:target="requestForeignOrderApproval" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Enviando solicitud...
                </span>
            </button>
            <button wire:click="$set('showConfirmModal', false)" class="px-8 py-4 bg-white border border-slate-200 text-slate-500 text-[11px] font-bold rounded-xl hover:bg-slate-50 transition-all uppercase tracking-wider">
                Cancelar
            </button>
        </div>
    </div>
</div>
