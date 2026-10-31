<div class="p-6 sm:p-8 font-sans" x-on:scroll-to-finance-form.window="$nextTick(() => document.getElementById($event.detail.target)?.scrollIntoView({ behavior: 'smooth', block: 'start' }))">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-gray-900">Cuentas por Pagar</h2>
            <p class="text-sm text-gray-500 mt-0.5">Gestión de deudas con proveedores, socios y compromisos de pago.</p>
        </div>
        <button type="button" wire:click="togglePayableForm" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900">
            @if($showForm)
                Cerrar formulario
            @else
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Registrar CXP manual
            @endif
        </button>
    </div>

    @if(session()->has('message'))
        <div class="mb-5 flex items-center gap-3 p-3.5 text-sm text-teal-800 bg-teal-50 border border-teal-200 rounded-xl font-medium">
            <svg class="w-4 h-4 text-teal-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('message') }}
        </div>
    @endif

    @if($showForm || $editing_payable_id)
    <div id="cxp-form" class="mb-6 scroll-mt-6">
        <p class="mb-3 text-sm text-gray-500">Identifica al beneficiario, completa el importe y vencimiento, y revisa los datos antes de guardar.</p>
        {{-- FORMULARIO --}}
        <div class="w-full">
            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-start justify-between gap-2 border-b border-gray-100 pb-3">
                    <div>
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-gray-900">
                            {{ $editing_payable_id ? 'Editar CXP' : 'Registrar CXP' }}
                        </h3>
                        <p class="mt-1 text-[10px] leading-relaxed text-gray-500">
                            @if($editing_payable_id)
                                Origen: <span class="font-bold text-slate-700">{{ $editing_origin }}</span>
                            @else
                                Se guardará como <span class="font-bold text-slate-700">Sin orden vinculada</span>.
                            @endif
                        </p>
                    </div>
                    @if($editing_payable_id)
                        <button type="button" wire:click="cancelPayableEdit" class="text-[10px] font-bold text-gray-500 hover:text-gray-900">Cancelar</button>
                    @endif
                </div>

                @error('payable_form')
                    <div class="mb-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-[11px] font-semibold text-rose-700">{{ $message }}</div>
                @enderror

                @unless($editing_payable_id)
                    <div class="mb-3 rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2 text-[10px] leading-relaxed text-indigo-800">
                        Las órdenes de compra generan su CXP automáticamente al autorizarse. Este formulario es para cuentas manuales o compromisos directos.
                    </div>
                @endunless

                <div class="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="block font-bold text-gray-600 mb-1">Acreedor / Proveedor <span class="text-rose-500">*</span></label>
                        <select wire:model.live="entity_id" @disabled($editing_has_order) class="w-full bg-gray-50 border-transparent rounded-xl py-2 px-3 focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all text-xs disabled:cursor-not-allowed disabled:opacity-60">
                            <option value="">-- Seleccionar --</option>
                            <option value="new">+ Registrar Nuevo</option>
                            @foreach($entities as $ent)
                                <option value="{{ $ent->id }}">{{ $ent->name }} ({{ ucfirst($ent->type) }})</option>
                            @endforeach
                        </select>
                        @if($editing_has_order)<p class="mt-1 text-[10px] text-gray-400">El acreedor pertenece a la orden de compra y no puede cambiarse aquí.</p>@endif
                        @error('entity_id') <span class="text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    @if($entity_id === 'new' && ! $editing_has_order)
                        <div class="space-y-2">
                            <input type="text" wire:model="new_entity_name" placeholder="Razón Social" class="w-full bg-gray-50 border-transparent rounded-xl py-2 px-3 text-xs focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                            <select wire:model="new_entity_type" class="w-full bg-gray-50 border-transparent rounded-xl py-2 px-3 text-xs focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                                <option value="socio">Socio Inversionista</option>
                                <option value="proveedor">Proveedor</option>
                            </select>
                        </div>
                    @endif
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold text-gray-600 mb-1">N° Factura proveedor</label>
                            <input type="text" wire:model="invoice_number" placeholder="341027" class="w-full bg-gray-50 border-transparent rounded-xl py-2 px-3 text-xs font-mono focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                            <p class="mt-1 text-[10px] font-bold text-indigo-600">Código interno: {{ $nextPayableCode }}</p>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-600 mb-1">Condición</label>
                            <select wire:model="condition" class="w-full bg-gray-50 border-transparent rounded-xl py-2 px-3 text-xs focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                                <option>Contado</option>
                                <option>30 Días</option>
                                <option>60 Días</option>
                                <option>90 Días</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-600 mb-1">Concepto</label>
                        <input type="text" wire:model="concept" placeholder="Ej: Compra de piezas Cummins" class="w-full bg-gray-50 border-transparent rounded-xl py-2 px-3 text-xs focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                        @error('concept') <span class="text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-bold text-gray-600 mb-1">Clasificación</label>
                        <select wire:model="classification" class="w-full bg-gray-50 border-transparent rounded-xl py-2 px-3 text-xs focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                            <option>Compra de piezas</option>
                            <option>Compra de módulo / equipo</option>
                            <option>Préstamo de socio</option>
                            <option>Servicio externo</option>
                            <option>Otro</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold text-gray-600 mb-1">Fec. Factura</label>
                            <input type="date" wire:model="invoice_date" class="w-full bg-gray-50 border-transparent rounded-xl py-2 px-3 text-xs focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-600 mb-1">Fec. Vencimiento</label>
                            <input type="date" wire:model="due_date" class="w-full bg-gray-50 border-transparent rounded-xl py-2 px-3 text-xs focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold text-gray-600 mb-1">Importe C$ <span class="text-rose-500">*</span></label>
                            <input type="number" step="0.01" wire:model="total_amount" placeholder="0.00" class="w-full bg-gray-50 border-transparent rounded-xl py-2 px-3 text-xs font-mono font-bold focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                            @error('total_amount') <span class="text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-bold text-gray-600 mb-1">Importe $</label>
                            <input type="number" step="0.01" wire:model="amount_usd" placeholder="0.00" class="w-full bg-gray-50 border-transparent rounded-xl py-2 px-3 text-xs font-mono focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-600 mb-1">{{ $editing_payable_id ? 'Reemplazar soporte (opcional)' : 'Adjuntar evidencia obligatoria' }}</label>
                        <input type="file" wire:model="support_document" class="w-full bg-gray-50 border border-dashed border-slate-300 rounded-xl py-2 px-3 text-xs">
                        <p class="mt-1 text-[10px] text-gray-400">PDF, JPG, PNG, WEBP, XLSX o DOCX. Max 10 MB.</p>
                        @error('support_document') <span class="text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex items-end justify-end gap-2 border-t border-gray-100 pt-4 md:col-span-2 xl:col-span-4">
                        @if($editing_payable_id)
                            <button type="button" wire:click="cancelPayableEdit" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</button>
                        @else
                            <button type="button" wire:click="togglePayableForm" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</button>
                        @endif
                        <button type="button" wire:click="savePayable" wire:loading.attr="disabled" wire:target="savePayable,support_document" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="savePayable,support_document">{{ $editing_payable_id ? 'Guardar cambios' : 'Registrar CXP' }}</span>
                            <span wire:loading wire:target="savePayable,support_document">Procesando...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

        {{-- TABLA --}}
        <div class="min-w-0 space-y-4">

            {{-- KPI Cards --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Saldo por Pagar</p>
                    <p class="text-xl font-black text-gray-900 mt-1">C$ {{ number_format($totalPendiente, 2) }}</p>
                </div>
                <div class="bg-white rounded-xl border border-rose-100 shadow-sm p-4">
                    <p class="text-[10px] font-bold text-rose-500 uppercase tracking-wider">Saldo vencido</p>
                    <p class="text-xl font-black text-rose-600 mt-1">C$ {{ number_format($totalVencido, 2) }}</p>
                </div>
            </div>

            {{-- Filtros --}}
            <div class="flex flex-wrap gap-2">
                <div class="relative flex-1 min-w-[180px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input wire:model.live.debounce.500ms="search"
                           type="text"
                           placeholder="Buscar por código CXP, orden, factura, proveedor o concepto..."
                           aria-label="Buscar cuentas por pagar"
                           class="w-full rounded-xl border-transparent bg-white py-2.5 pl-9 pr-3 text-sm shadow-sm ring-1 ring-slate-200 transition-all placeholder:text-gray-400 focus:ring-2 focus:ring-slate-300">
                </div>
                <select wire:model.live="filterStatus"
                        class="bg-gray-50 border-transparent rounded-xl py-2 px-3 text-xs focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="parcial">Parcial</option>
                    <option value="pagado">Pagado</option>
                </select>
                @if($search || $filterStatus)
                    <button wire:click="$set('search', ''); $set('filterStatus', '')"
                            class="px-3 py-2 text-xs text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors">
                        Limpiar
                    </button>
                @endif
                <span class="ml-auto self-center text-xs text-gray-400 font-medium">{{ $payables->total() }} registros</span>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1540px] whitespace-nowrap text-left text-sm">

                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3 font-semibold">CXP / # de orden</th><th class="px-4 py-3 font-semibold">Nº factura</th><th class="px-4 py-3 font-semibold">Proveedor</th><th class="px-4 py-3 font-semibold">Pagar a</th><th class="px-4 py-3 font-semibold">Concepto</th><th class="px-4 py-3 font-semibold">Fec. factura</th><th class="px-4 py-3 text-right font-semibold">Importe C$</th><th class="px-4 py-3 font-semibold">Fec. vencimiento</th><th class="px-4 py-3 text-right font-semibold">Pagado</th><th class="px-4 py-3 text-right font-semibold">Saldo</th><th class="px-4 py-3 text-center font-semibold">Estado</th><th class="px-4 py-3 text-center font-semibold">Días</th><th class="sticky right-0 z-10 border-l border-gray-100 bg-gray-50 px-4 py-3 text-center font-semibold">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($payables as $pay)
                                @php
                                    $saldo = $pay->total_amount - $pay->paid_amount;
                                    $dias = $pay->due_date ? now()->diffInDays(\Carbon\Carbon::parse($pay->due_date), false) : null;
                                    $isOverdue = $dias !== null && $dias < 0 && $pay->status !== 'pagado';
                                @endphp
                                <tr class="group transition-colors hover:bg-gray-50/50 {{ $pay->status === 'pagado' ? 'opacity-60' : '' }}">
                                    <td class="px-4 py-3"><p class="font-mono text-xs font-semibold text-gray-900">{{ $pay->payable_code ?: '—' }}</p><p class="mt-1 text-xs text-gray-500">{{ $pay->purchaseOrderNumber() ?: 'Sin orden vinculada' }}</p></td>
                                    <td class="px-4 py-3 font-mono text-xs font-semibold text-gray-700">{{ $pay->invoice_number ?: '—' }}</td>
                                    <td class="max-w-[190px] truncate px-4 py-3 font-semibold text-gray-900" title="{{ $pay->supplierName() }}">{{ $pay->supplierName() ?: '—' }}</td>
                                    <td class="max-w-[190px] truncate px-4 py-3 text-gray-700" title="{{ $pay->paymentRecipientName() }}">{{ $pay->paymentRecipientName() ?: '—' }}</td>
                                    <td class="max-w-[240px] truncate px-4 py-3 text-gray-700" title="{{ $pay->concept }}">{{ $pay->concept ?: '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $pay->invoice_date ? \Carbon\Carbon::parse($pay->invoice_date)->format('d/m/Y') : '—' }}</td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-900">C$ {{ number_format($pay->total_amount, 2) }}</td>
                                    <td class="px-4 py-3 {{ $isOverdue ? 'font-semibold text-rose-600' : 'text-gray-600' }}">{{ $pay->due_date ? \Carbon\Carbon::parse($pay->due_date)->format('d/m/Y') : '—' }}</td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums text-teal-700">{{ $pay->paid_amount > 0 ? 'C$ '.number_format($pay->paid_amount, 2) : '—' }}</td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums {{ $saldo > 0 ? 'text-gray-900' : 'text-teal-700' }}">C$ {{ number_format($saldo, 2) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        @if($pay->status === 'pagado')
                                            <span class="text-xs font-semibold text-teal-700">Pagado</span>
                                        @elseif($pay->status === 'parcial')
                                            <span class="text-xs font-semibold text-amber-700">Parcial</span>
                                        @else
                                            <span class="text-xs font-semibold {{ $isOverdue ? 'text-rose-700' : 'text-gray-700' }}">{{ $isOverdue ? 'Vencido' : 'Pendiente' }}</span>
                                        @endif
                                        @if($pay->pendingChangeRequest)
                                            <span class="mt-1 block text-xs font-medium text-amber-700" title="Modificación solicitada por {{ $pay->pendingChangeRequest->requester?->name }}">En revisión</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center text-xs {{ $isOverdue ? 'font-semibold text-rose-600' : 'text-gray-500' }}">@if($dias !== null && $pay->status !== 'pagado'){{ $dias < 0 ? abs((int) $dias).' d venc.' : (int) $dias.' d' }}@else—@endif</td>
                                    <td class="sticky right-0 border-l border-gray-100 bg-white px-4 py-3 group-hover:bg-gray-50"><div class="flex items-center justify-center gap-2"><button type="button" wire:click="editPayable({{ $pay->id }})" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">Editar</button>@if($pay->status !== 'pagado')<button type="button" wire:click="openPaymentModal({{ $pay->id }})" class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-900">Registrar pago</button>@else<span class="text-xs text-gray-400">Liquidado</span>@endif</div></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="px-6 py-12 text-center">
                                        <p class="text-sm font-bold text-gray-700">Sin deudas registradas</p>
                                        <p class="text-xs text-gray-400 mt-1">No hay cuentas por pagar activas.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($payables->count() > 0)
                        <tfoot class="bg-gray-50/75 border-t-2 border-gray-200">
                            <tr class="text-xs">
                                <td colspan="6" class="px-4 py-3 text-right font-semibold uppercase tracking-wider text-gray-500">Totales de esta página</td><td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-900">C$ {{ number_format($payables->sum('total_amount'), 2) }}</td><td></td><td class="px-4 py-3 text-right font-medium tabular-nums text-teal-700">C$ {{ number_format($payables->sum('paid_amount'), 2) }}</td><td class="px-4 py-3 text-right font-semibold tabular-nums text-rose-700">C$ {{ number_format($payables->sum(fn($p) => $p->total_amount - $p->paid_amount), 2) }}</td><td colspan="3"></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
            @if($payables->hasPages())
                <div class="pt-1">
                    {{ $payables->onEachSide(1)->links() }}
                </div>
            @endif
        </div>

    {{-- MODAL APROBACIÓN Y JUSTIFICACIÓN DE CAMBIOS SENSIBLES --}}
    @if($showApprovalModal)
        <div class="fixed inset-0 flex items-center justify-center z-50 p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="closeApprovalModal"></div>
            <div class="bg-white p-6 rounded-2xl shadow-2xl w-full max-w-lg relative z-10 border border-gray-100">
                <div class="flex items-start justify-between gap-3 mb-4 pb-4 border-b border-gray-100">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-extrabold text-gray-900">Solicitud de Aprobación de Modificación</h3>
                            <p class="text-[11px] text-gray-500 mt-0.5">Estás solicitando modificar datos financieros de una Cuenta por Pagar. Esta solicitud se enviará al Administrador para su revisión y autorización.</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeApprovalModal" class="p-1.5 text-gray-400 hover:text-gray-700 bg-gray-50 rounded-full border border-gray-200 hover:bg-gray-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Resumen de cambios detectados --}}
                <div class="mb-4">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2">Cambios solicitados:</p>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 overflow-hidden divide-y divide-slate-100">
                        @foreach($pending_changes as $change)
                            <div class="px-3 py-2 text-xs flex items-center justify-between gap-2">
                                <span class="font-bold text-slate-700 text-[11px]">{{ $change['field'] }}</span>
                                <div class="flex items-center gap-2 font-mono text-[11px]">
                                    <span class="text-rose-600 line-through">{{ $change['before'] }}</span>
                                    <span class="text-gray-400">→</span>
                                    <span class="text-teal-700 font-bold">{{ $change['after'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Justificación obligatoria --}}
                <div class="space-y-2 mb-4">
                    <label class="block text-xs font-bold text-gray-700">
                        Motivo / Justificación de la modificación <span class="text-rose-500">*</span>
                    </label>
                    <textarea wire:model="change_justification" rows="3" placeholder="Ingresa el motivo detallado de esta modificación (ej: Corrección por nota de crédito, ajuste de factura autorizada por administración...)" class="w-full bg-gray-50 border border-slate-200 rounded-xl p-3 text-xs focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all"></textarea>
                    <p class="text-[10px] text-gray-400">Esta justificación quedará registrada de forma inalterable en la bitácora de auditoría y será revisada por el Administrador.</p>
                    @error('change_justification') <span class="text-xs text-rose-500 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex gap-2 pt-3 border-t border-gray-100">
                    <button type="button" wire:click="closeApprovalModal" class="flex-1 bg-white border border-gray-200 text-gray-700 font-bold py-2.5 rounded-xl hover:bg-gray-50 transition-colors text-xs">
                        Cancelar
                    </button>
                    <button type="button" wire:click="confirmSavePayable" wire:loading.attr="disabled" wire:target="confirmSavePayable,support_document" class="flex-[2] bg-slate-900 hover:bg-black text-white font-bold py-2.5 px-4 rounded-xl shadow-sm transition-colors text-xs flex justify-center items-center gap-2 disabled:cursor-not-allowed disabled:opacity-60">
                        <svg wire:loading.remove wire:target="confirmSavePayable,support_document" class="w-3.5 h-3.5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        <span wire:loading.remove wire:target="confirmSavePayable,support_document">Enviar Solicitud de Aprobación</span>
                        <span wire:loading wire:target="confirmSavePayable,support_document">Enviando solicitud...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL PAGO --}}
    @if($showPaymentModal)
        <div class="fixed inset-0 flex items-center justify-center z-50">
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" wire:click="$set('showPaymentModal', false)"></div>
            <div class="bg-white p-6 rounded-2xl shadow-2xl w-[26rem] relative z-10 border border-gray-100">
                <div class="flex items-center justify-between mb-5 pb-4 border-b border-gray-100">
                    <div>
                        <h3 class="text-base font-extrabold text-gray-900">Emitir Pago</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Registrar salida de fondos al acreedor.</p>
                    </div>
                    <button wire:click="$set('showPaymentModal', false)" class="p-1.5 text-gray-400 hover:text-gray-700 bg-gray-50 rounded-full border border-gray-200 hover:bg-gray-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="space-y-4">
                    @error('payment_form')
                        <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">{{ $message }}</div>
                    @enderror
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Moneda del pago</label>
                        <select wire:model.live="payment_currency" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                            <option value="NIO">Córdobas (C$)</option>
                            <option value="USD">Dólares (USD)</option>
                        </select>
                        @error('payment_currency') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Monto a Pagar ({{ $payment_currency === 'USD' ? 'USD' : 'C$' }})</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 text-sm font-bold">{{ $payment_currency === 'USD' ? 'USD' : 'C$' }}</span>
                            <input type="number" wire:model="payment_amount" placeholder="0.00" class="w-full bg-gray-50 border-transparent rounded-xl py-3 pl-10 pr-4 text-base font-bold text-gray-900 focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                        </div>
                        @error('payment_amount') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    @if($payment_currency === 'USD')
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Tasa aplicada (C$ por USD)</label>
                            <input type="text" value="{{ number_format((float) $payment_exchange_rate, 6) }}" readonly class="w-full cursor-not-allowed rounded-xl border-transparent bg-slate-100 px-3 py-2.5 text-sm font-bold text-slate-600">
                            <p class="mt-1 text-[10px] font-bold text-slate-500">Definida globalmente en Finanzas → Tasas de cambio.</p>
                        </div>
                    @endif
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Canal de Pago</label>
                        <select wire:model="payment_method" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                            <option value="Transferencia">Transferencia / ACH</option>
                            <option value="Efectivo">Efectivo</option>
                            <option value="Cheque">Cheque Bancario</option>
                        </select>
                        @error('payment_method') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Fecha del Pago</label>
                        <input type="date" wire:model="payment_date" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                        @error('payment_date') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Referencia bancaria</label>
                        <input type="text" wire:model="payment_reference" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                        @error('payment_reference') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Evidencia obligatoria</label>
                        <input type="file" wire:model="payment_evidence" class="w-full bg-gray-50 border border-dashed border-slate-300 rounded-xl py-2.5 px-3 text-xs">
                        <span wire:loading wire:target="payment_evidence" class="mt-1 block text-xs font-semibold text-sky-700">Cargando evidencia...</span>
                        @error('payment_evidence') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="mt-5 flex gap-3">
                    <button type="button" wire:click="$set('showPaymentModal', false)" class="flex-1 bg-white border border-gray-200 text-gray-700 font-bold py-2.5 rounded-xl hover:bg-gray-50 transition-colors text-sm">Cancelar</button>
                    <button type="button" wire:click.prevent="savePayment" wire:loading.attr="disabled" wire:target="savePayment,payment_evidence" data-testid="apply-payment-button" class="flex-[2] bg-slate-800 hover:bg-slate-900 text-white font-bold py-2.5 rounded-xl transition-colors text-sm flex justify-center items-center gap-2 disabled:cursor-not-allowed disabled:opacity-60">
                        <svg wire:loading.remove wire:target="savePayment,payment_evidence" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span wire:loading.remove wire:target="savePayment,payment_evidence">Aplicar Pago</span>
                        <span wire:loading wire:target="savePayment,payment_evidence">Procesando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
