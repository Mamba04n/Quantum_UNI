<div class="p-6 font-sans sm:p-8">
    <div class="mb-6">
        <h2 class="text-2xl font-extrabold tracking-tight text-gray-900">Aplicación de pagos</h2>
        <p class="mt-0.5 text-sm text-gray-500">Selecciona la cuenta pendiente, registra el importe y adjunta el comprobante.</p>
    </div>

    @if (session()->has('message'))
        <div class="bg-teal-50 border border-teal-200 text-teal-800 px-4 py-3 rounded-xl mb-6 flex items-center gap-3 text-sm font-medium shadow-sm">
            <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl mb-6 flex items-center gap-3 text-sm font-medium shadow-sm">
            <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-[0_2px_10px_-3px_rgba(0,0,0,0.05)] border border-gray-100 overflow-hidden">
        <div class="p-5">
            <form wire:submit.prevent="processPayment">

                <h3 class="text-xs font-bold tracking-widest text-slate-500 uppercase mb-5 border-b border-gray-100 pb-2">Datos de la Transacción</h3>

                <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <!-- Tipo de Operación -->
                    <div>
                        <label class="block mb-1.5 text-sm font-bold text-gray-700">Tipo de Operación</label>
                        <select wire:model.live="type" class="w-full bg-gray-50 border-transparent rounded-xl focus:bg-white focus:ring-2 focus:ring-slate-200 focus:border-slate-400 transition-all py-3 px-4 text-sm font-medium text-gray-800">
                            <option value="cxc">Ingreso (Cobro a Cliente)</option>
                            <option value="cxp">Egreso (Pago a Proveedor/Socio)</option>
                        </select>
                    </div>

                    <!-- Cuenta a Pagar/Cobrar -->
                    <div>
                        <label class="block mb-1.5 text-sm font-bold text-gray-700">Seleccionar Cuenta Pendiente</label>
                        <select wire:model="account_id" class="w-full bg-gray-50 border-transparent rounded-xl focus:bg-white focus:ring-2 focus:ring-slate-200 focus:border-slate-400 transition-all py-3 px-4 text-sm text-gray-800">
                            <option value="">-- Seleccione una cuenta --</option>
                            @foreach($pendingAccounts as $account)
                                <option value="{{ $account->id }}">
                                    {{ $account->entity->name ?? 'Desconocido' }} | {{ $account->concept }}
                                    | Pendiente: C$ {{ number_format($account->total_amount - $account->paid_amount, 2) }}
                                </option>
                            @endforeach
                        </select>
                        @error('account_id') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- Monto a Aplicar -->
                    <div>
                        <label class="block mb-1.5 text-sm font-bold text-gray-700">Monto a Aplicar (C$)</label>
                        <input type="number" step="0.01" wire:model="amount" class="w-full bg-gray-50 border-transparent rounded-xl focus:bg-white focus:ring-2 focus:ring-slate-200 focus:border-slate-400 transition-all py-3 px-4 text-sm font-semibold text-gray-900" placeholder="0.00">
                        @error('amount') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- Fecha del Pago -->
                    <div>
                        <label class="block mb-1.5 text-sm font-bold text-gray-700">Fecha Efectiva</label>
                        <input type="date" wire:model="payment_date" class="w-full bg-gray-50 border-transparent rounded-xl focus:bg-white focus:ring-2 focus:ring-slate-200 focus:border-slate-400 transition-all py-3 px-4 text-sm text-gray-800">
                        @error('payment_date') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- Método de Pago -->
                    <div>
                        <label class="block mb-1.5 text-sm font-bold text-gray-700">Método de Pago</label>
                        <select wire:model="payment_method" class="w-full bg-gray-50 border-transparent rounded-xl focus:bg-white focus:ring-2 focus:ring-slate-200 focus:border-slate-400 transition-all py-3 px-4 text-sm text-gray-800">
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia Bancaria</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Tarjeta">Tarjeta de Crédito/Débito</option>
                        </select>
                        @error('payment_method') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>

                    <!-- Número de Referencia (Voucher) -->
                    <div>
                        <label class="block mb-1.5 text-sm font-bold text-gray-700">Referencia / Váucher</label>
                        <input type="text" wire:model="reference" class="w-full bg-gray-50 border-transparent rounded-xl focus:bg-white focus:ring-2 focus:ring-slate-200 focus:border-slate-400 transition-all py-3 px-4 text-sm text-gray-800" placeholder="N/A o número de comprobante">
                        @error('reference') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block mb-1.5 text-sm font-bold text-gray-700">Evidencia obligatoria del pago</label>
                        <input type="file" wire:model="payment_evidence" class="w-full bg-gray-50 border border-dashed border-slate-300 rounded-xl py-3 px-4 text-sm text-gray-800">
                        <p class="mt-1 text-xs text-gray-400">Comprobante, voucher, captura bancaria o recibo. Max 10 MB.</p>
                        @error('payment_evidence') <span class="text-rose-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end pt-6 border-t border-gray-100">
                    <button type="submit" wire:loading.attr="disabled" wire:target="processPayment,payment_evidence" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 disabled:opacity-60">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <span wire:loading.remove wire:target="processPayment">Registrar pago</span>
                        <span wire:loading wire:target="processPayment">Registrando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
