<div class="p-6 sm:p-8 font-sans">

    {{-- HEADER --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-gray-900">Facturación</h2>
            <p class="text-sm text-gray-500 mt-0.5">Control de facturas emitidas, estados de cobro y desglose interno por empresa emisora.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('finance.invoices.preprinted-preview') }}" target="_blank" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition">
                Talonario preimpreso
            </a>
            <button wire:click="$set('showForm', true)" type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Registrar Factura
            </button>
        </div>
    </div>

    @if(session()->has('message'))
        <div class="mb-5 flex items-center gap-3 p-3.5 text-sm text-teal-800 bg-teal-50 border border-teal-200 rounded-xl font-medium">
            <svg class="w-4 h-4 text-teal-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- RESUMEN GLOBAL --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Total Facturado (Global)</p>
            <p class="text-2xl font-black text-gray-900">C$ {{ number_format($totalNio, 2) }}</p>
            <p class="text-[11px] text-gray-400 mt-1">{{ $invoices->count() }} factura(s) listadas</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Pendiente de Cobro</p>
            <p class="text-2xl font-black text-rose-600">C$ {{ number_format($totalPendiente, 2) }}</p>
            <p class="text-[11px] text-gray-400 mt-1">Créditos y saldos por cobrar</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-1">Cobrado Efectivo</p>
            <p class="text-2xl font-black text-teal-600">C$ {{ number_format($totalPagado, 2) }}</p>
            <p class="text-[11px] text-gray-400 mt-1">Ingresos cancelados</p>
        </div>
    </div>

    {{-- DESGLOSE INTERNO POR EMPRESA EMISORA --}}
    <div class="mb-6">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="text-sm font-extrabold tracking-tight text-gray-900 uppercase">Facturación por Empresa Emisora</h3>
                <p class="text-xs text-gray-500">Monto total facturado, cobrado y pendiente desglosado para cada empresa.</p>
            </div>
            @if($filterCompany)
                <button wire:click="$set('filterCompany', '')" class="text-xs font-semibold text-slate-600 hover:text-slate-900 underline">
                    Ver todas las empresas
                </button>
            @endif
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($companyBreakdown as $key => $comp)
                @php
                    $isSelected = ($filterCompany === $key);
                @endphp
                <div 
                    wire:click="$set('filterCompany', '{{ $isSelected ? '' : $key }}')"
                    class="cursor-pointer rounded-2xl border transition-all p-4 shadow-xs {{ $isSelected ? 'ring-2 ring-slate-900 border-slate-900 bg-slate-50/60' : 'bg-white border-gray-100 hover:border-slate-300 hover:shadow-sm' }}"
                >
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="inline-flex h-3 w-3 rounded-full" style="background-color: {{ $comp['color'] }};"></span>
                            <h4 class="text-xs font-extrabold text-gray-900 truncate" title="{{ $comp['name'] }}">{{ $comp['short_name'] }}</h4>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $comp['badge'] }}">
                            {{ $comp['count'] }} fact.
                        </span>
                    </div>
                    <p class="text-[10px] text-gray-400 mb-2 font-mono">RUC: {{ $comp['ruc'] }}</p>

                    <div class="space-y-1.5 pt-2 border-t border-gray-100">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-500 font-medium">Facturado:</span>
                            <span class="font-extrabold text-gray-900">C$ {{ number_format($comp['total'], 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-teal-700 font-medium">Cobrado:</span>
                            <span class="font-bold text-teal-700">C$ {{ number_format($comp['paid'], 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-rose-700 font-medium">Pendiente:</span>
                            <span class="font-bold text-rose-700">C$ {{ number_format($comp['pending'], 2) }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Buscar factura, cliente, descripción..." class="text-sm bg-white border border-gray-200 rounded-xl px-4 py-2 w-72 focus:ring-2 focus:ring-slate-200 focus:border-slate-400 transition-all">
        
        <select wire:model.live="filterCompany" class="text-sm bg-white border border-gray-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-slate-200 focus:border-slate-400 transition-all">
            <option value="">Todas las empresas</option>
            <option value="quantum">🔴 Quantum Soluciones S.A.</option>
            <option value="powersales">🔵 Power Sales</option>
            <option value="marcos_leiva">🟢 Marcos Cecilio Leiva Ordoñez</option>
        </select>

        <select wire:model.live="filterStatus" class="text-sm bg-white border border-gray-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-slate-200 focus:border-slate-400 transition-all">
            <option value="">Todos los estados</option>
            <option value="Pendiente">Pendiente</option>
            <option value="Pagado">Pagado</option>
            <option value="Parcial">Parcial</option>
        </select>
        
        <select wire:model.live="filterClient" class="text-sm bg-white border border-gray-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-slate-200 focus:border-slate-400 transition-all">
            <option value="">Todos los clientes</option>
            @foreach($entities as $ent)
                <option value="{{ $ent->id }}">{{ $ent->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- TABLA --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-[0_2px_10px_-3px_rgba(0,0,0,0.05)] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs whitespace-nowrap">
                <thead class="bg-gray-50 text-gray-500 uppercase text-[10px] tracking-wider border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 font-semibold">N° Fact.</th>
                        <th class="px-4 py-3 font-semibold">Empresa Emisora</th>
                        <th class="px-4 py-3 font-semibold">Nombre / Cliente</th>
                        <th class="px-4 py-3 font-semibold">Fecha</th>
                        <th class="px-4 py-3 font-semibold">Cotización</th>
                        <th class="px-4 py-3 font-semibold">Descripción</th>
                        <th class="px-4 py-3 font-semibold text-right">Monto C$</th>
                        <th class="px-4 py-3 font-semibold text-right">Menos Retención</th>
                        <th class="px-4 py-3 font-semibold text-right">Monto $</th>
                        <th class="px-4 py-3 font-semibold">Vencimiento</th>
                        <th class="px-4 py-3 font-semibold text-center">Estado</th>
                        <th class="px-4 py-3 font-semibold text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($invoices as $inv)
                        @php
                            $netAmount = $inv->amount_nio - $inv->retention_amount;
                            $isOverdue = $inv->due_date && $inv->due_date->isPast() && $inv->status !== 'Pagado';
                            $issuerKey = $inv->company_issuer ?: 'quantum';
                            $issuerBadge = match($issuerKey) {
                                'powersales' => ['label' => 'Power Sales', 'class' => 'bg-blue-50 text-blue-800 border-blue-200'],
                                'marcos_leiva' => ['label' => 'Marcos Leiva', 'class' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                                default => ['label' => 'Quantum', 'class' => 'bg-rose-50 text-rose-800 border-rose-200'],
                            };
                        @endphp
                        <tr class="hover:bg-gray-50/50 transition-colors {{ $inv->status === 'Pagado' ? 'opacity-75' : '' }}">
                            <td class="px-4 py-3">
                                <span class="font-bold text-gray-800 bg-gray-100 px-2 py-0.5 rounded text-[10px] tracking-wide">
                                    {{ $inv->invoice_number ?: '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $issuerBadge['class'] }}" title="{{ $issuerKey }}">
                                    {{ $issuerBadge['label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-bold text-gray-900 max-w-[140px] truncate">
                                {{ optional($inv->entity)->name ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 font-medium">
                                {{ $inv->date ? $inv->date->format('d/m/Y') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $inv->cotizacion_ref ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-700 max-w-[200px] truncate" title="{{ $inv->description }}">
                                {{ $inv->description }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-gray-900">
                                C$ {{ number_format($inv->amount_nio, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-700 font-medium">
                                C$ {{ number_format($netAmount, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-500">
                                {{ $inv->amount_usd ? '$ '.number_format($inv->amount_usd, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3 {{ $isOverdue ? 'text-rose-600 font-bold' : 'text-gray-600' }}">
                                {{ $inv->due_date ? $inv->due_date->format('d/m/Y') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($inv->status === 'Pagado')
                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200">Pagado</span>
                                @elseif($inv->status === 'Parcial')
                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Parcial</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Pendiente</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    {{-- Asignar empresa rápida --}}
                                    <select 
                                        wire:change="updateInvoiceCompany({{ $inv->id }}, $event.target.value)"
                                        class="rounded border border-gray-200 bg-white py-0.5 px-1.5 text-[9px] font-semibold text-gray-700 hover:bg-gray-50 focus:ring-1 focus:ring-slate-300"
                                        title="Cambiar empresa emisora de la factura"
                                    >
                                        <option value="quantum" {{ $issuerKey === 'quantum' ? 'selected' : '' }}>Quantum</option>
                                        <option value="powersales" {{ $issuerKey === 'powersales' ? 'selected' : '' }}>Power Sales</option>
                                        <option value="marcos_leiva" {{ $issuerKey === 'marcos_leiva' ? 'selected' : '' }}>M. Leiva</option>
                                    </select>

                                    @if($inv->status !== 'Pagado')
                                        <button wire:click="markAsPaid({{ $inv->id }})" class="text-[10px] font-bold px-2 py-1 bg-teal-50 text-teal-700 border border-teal-200 rounded hover:bg-teal-100 transition-colors" title="Marcar como Pagado">
                                            Cobrado
                                        </button>
                                    @endif
                                    <button wire:click="delete({{ $inv->id }})" wire:confirm="¿Eliminar esta factura?" class="p-1 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded transition-colors border border-transparent hover:border-rose-200" title="Eliminar factura">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-6 py-14 text-center">
                                <div class="w-14 h-14 bg-gray-50 border border-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <p class="text-sm font-bold text-gray-700">Sin facturas registradas</p>
                                <p class="text-xs text-gray-400 mt-1">Registra tu primera factura o cambia los filtros de búsqueda.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($invoices->count() > 0)
                <tfoot class="bg-gray-50 border-t border-gray-100">
                    <tr>
                        <td colspan="6" class="px-4 py-2.5 text-xs font-bold text-gray-600 uppercase">Totales ({{ $invoices->count() }} registros)</td>
                        <td class="px-4 py-2.5 text-right text-xs font-black text-gray-900">C$ {{ number_format($totalNio, 2) }}</td>
                        <td class="px-4 py-2.5 text-right text-xs font-black text-gray-900">C$ {{ number_format($invoices->sum(fn($i) => $i->amount_nio - $i->retention_amount), 2) }}</td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- MODAL NUEVA FACTURA --}}
    @if($showForm)
        <div class="fixed inset-0 z-50 bg-gray-900/50 flex items-center justify-center backdrop-blur-sm p-4">
            <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <div>
                        <h3 class="text-lg font-extrabold text-gray-900">Registrar Factura</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Completa los datos de la factura y selecciona la empresa emisora.</p>
                    </div>
                    <button wire:click="$set('showForm', false)" class="p-1.5 text-gray-400 hover:text-gray-700 bg-white border border-gray-200 rounded-full hover:bg-gray-100 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto max-h-[75vh]">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Empresa Emisora de la Factura <span class="text-rose-500">*</span></label>
                            <select wire:model="company_issuer" class="w-full bg-gray-50 border-gray-200 rounded-xl py-2.5 px-3 text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                                <option value="quantum">🔴 Quantum Soluciones S.A. (RUC: J0310000475024)</option>
                                <option value="powersales">🔵 Power Sales (RUC: 2831402011000W)</option>
                                <option value="marcos_leiva">🟢 Marcos Cecilio Leiva Ordoñez (RUC: 0052211730002M)</option>
                            </select>
                            <p class="text-[11px] text-gray-400 mt-1">Define internamente a qué empresa corresponde la facturación y cobranza.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">N° Factura</label>
                            <input type="text" wire:model="invoice_number" placeholder="Ej: 000086" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Cliente</label>
                            <select wire:model.live="entity_id" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                                <option value="">-- Sin cliente --</option>
                                <option value="new">+ Nuevo cliente</option>
                                @foreach($entities as $ent)
                                    <option value="{{ $ent->id }}">{{ $ent->name }}</option>
                                @endforeach
                            </select>
                            @if($entity_id === 'new')
                                <input type="text" wire:model="new_entity_name" placeholder="Nombre del nuevo cliente" class="mt-2 w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                            @endif
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Fecha</label>
                            <input type="date" wire:model="date" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">N° Cotización / Ref.</label>
                            <input type="text" wire:model="cotizacion_ref" placeholder="Ej: M0001" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Descripción <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="description" placeholder="Ej: Kit de repuestos y filtros de mantenimiento" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                            @error('description') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Monto C$ <span class="text-rose-500">*</span></label>
                            <input type="number" step="0.01" wire:model="amount_nio" placeholder="0.00" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm font-mono focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                            @error('amount_nio') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Documento de factura obligatorio</label>
                            <input type="file" wire:model="invoice_file" class="w-full bg-gray-50 border border-dashed border-slate-300 rounded-xl py-2.5 px-3 text-xs">
                            <p class="mt-1 text-[10px] text-gray-400">PDF, JPG, PNG, WEBP, XLSX o DOCX. Max 10 MB.</p>
                            @error('invoice_file') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Retención C$</label>
                            <input type="number" step="0.01" wire:model="retention_amount" placeholder="0.00" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm font-mono focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Monto USD $</label>
                            <input type="number" step="0.01" wire:model="amount_usd" placeholder="0.00" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm font-mono focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Fecha Vencimiento</label>
                            <input type="date" wire:model="due_date" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Condición</label>
                            <select wire:model="condition" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                                <option>Contado</option>
                                <option>30 Días</option>
                                <option>60 Días</option>
                                <option>90 Días</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Estado</label>
                            <select wire:model="status" class="w-full bg-gray-50 border-transparent rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:ring-2 focus:ring-slate-200 transition-all">
                                <option>Pendiente</option>
                                <option>Pagado</option>
                                <option>Parcial</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button wire:click="$set('showForm', false)" class="px-5 py-2.5 text-sm font-bold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">
                            Cancelar
                        </button>
                        <button wire:click="saveInvoice" class="px-6 py-2.5 text-sm font-bold text-white bg-slate-800 rounded-xl hover:bg-slate-900 transition-colors shadow-sm flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Guardar Factura
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
