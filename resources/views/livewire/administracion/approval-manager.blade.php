<div class="p-6 font-sans sm:p-8" x-on:approval-selected.window="document.getElementById('approval-detail-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">
    @php
        $statusLabels = [
            'pending' => 'Pendiente',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'changes_requested' => 'Requiere ajuste',
        ];
        $typeLabels = [
            'internal_purchase' => 'Orden de compra',
            'foreign_order_conversion' => 'Pedido extranjero',
            'client_offer_approval' => 'Oferta cliente',
        ];
    @endphp

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">Centro de aprobaciones</h1>
            <p class="mt-0.5 text-sm text-gray-500">Solicitudes administrativas organizadas por tipo y área responsable.</p>
        </div>
        <div class="flex items-center gap-2">
            @if($selectedApproval)
                @can('purchase_order_document.view')
                    <a href="{{ route('admin.approvals.purchase-document', $selectedApproval) }}" class="rounded-lg bg-slate-950 px-4 py-2 text-xs font-black text-white hover:bg-black">Documento OC</a>
                @endcan
            @endif
        </div>
    </div>

    @if(session('message'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-bold text-emerald-700">{{ session('message') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-bold text-rose-700">{{ session('error') }}</div>
    @endif

    @if($managerSection !== 'overview')
        <div class="mb-4">
            <button type="button" wire:click="showOverview" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 hover:text-gray-900">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7"/></svg>
                Volver a todas las aprobaciones
            </button>
        </div>
    @endif

    @if($managerSection === 'overview')
        <section>
            <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Bandejas disponibles</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Cada tipo conserva su flujo, responsables y permisos.</p>
                </div>
                <div class="text-sm text-gray-500">
                    Pendientes totales: <strong class="font-semibold text-gray-900">{{ $approvalTypes->sum('count') }}</strong>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full whitespace-nowrap text-left text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Tipo de aprobación</th>
                                <th class="px-5 py-3 font-semibold">Área</th>
                                <th class="px-5 py-3 text-center font-semibold">Pendientes</th>
                                <th class="px-5 py-3 text-center font-semibold">Estado</th>
                                <th class="px-5 py-3 text-right font-semibold">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($approvalTypes as $type)
                                <tr class="transition-colors hover:bg-gray-50/50">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                                @switch($type['key'])
                                                    @case('quotation_stage_changes')
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 14-4-4 4-4M5 10h9a5 5 0 010 10h-2"/></svg>
                                                        @break
                                                    @case('purchase_orders')
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v14l-4-2-3 2-3-2-4 2V6a2 2 0 012-2z"/></svg>
                                                        @break
                                                    @case('client_offers')
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                                        @break
                                                    @case('supplier_rfqs')
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h7"/></svg>
                                                        @break
                                                    @case('dispatch_authorizations')
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h11v10H3zM14 10h4l3 3v4h-7zM7 17a2 2 0 104 0m5 0a2 2 0 104 0"/></svg>
                                                        @break
                                                    @default
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8v10m0 0v2"/></svg>
                                                @endswitch
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-gray-900">{{ $type['label'] }}</p>
                                                <p class="mt-0.5 max-w-2xl truncate text-xs text-gray-500">{{ $type['description'] }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-gray-600">{{ $type['area'] }}</td>
                                    <td class="px-5 py-3 text-center font-semibold tabular-nums text-gray-900">{{ $type['count'] }}</td>
                                    <td class="px-5 py-3 text-center">
                                        <span class="inline-flex rounded border px-2 py-0.5 text-xs font-semibold {{ $type['count'] > 0 ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                            {{ $type['count'] > 0 ? 'Requiere revisión' : 'Al día' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ $type['route'] }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900">
                                            Revisar
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-gray-500">No tienes tipos de aprobación asignados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @elseif($managerSection === 'stage_changes')
        <section>
            <div class="mb-3">
                <h2 class="text-base font-semibold text-gray-900">Cambios de etapa pendientes</h2>
                <p class="mt-0.5 text-sm text-gray-500">La cotización no cambia hasta que la solicitud sea aprobada.</p>
            </div>
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Cotización</th>
                                <th class="px-5 py-3 font-semibold">Cambio solicitado</th>
                                <th class="px-5 py-3 font-semibold">Motivo</th>
                                <th class="px-5 py-3 font-semibold">Solicitante</th>
                                <th class="px-5 py-3 text-right font-semibold">Decisión</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse($pendingStageChanges as $request)
                                <tr class="align-top transition-colors hover:bg-gray-50/50" wire:key="stage-change-{{ $request->id }}">
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-gray-900">{{ $request->quotationRequest?->reference ?: 'Cotización #'.$request->quotation_request_id }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $request->quotationRequest?->quote_to_name ?: 'Cliente no registrado' }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="font-medium text-gray-900">{{ $request->from_stage }} → {{ $request->to_stage }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $request->from_status }} → {{ $request->to_status }}</p>
                                    </td>
                                    <td class="max-w-md px-5 py-4 text-gray-600">
                                        <p class="whitespace-normal">{{ $request->reason }}</p>
                                        <textarea wire:model="stageChangeReviewNotes.{{ $request->id }}" rows="2" class="mt-3 w-full min-w-64 rounded-xl border-gray-200 text-sm focus:border-slate-400 focus:ring-slate-200" placeholder="Nota de revisión; obligatoria al rechazar"></textarea>
                                        @error('stageChangeReviewNotes.'.$request->id) <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-5 py-4 text-gray-600">
                                        <p>{{ $request->requester?->name ?: 'Usuario' }}</p>
                                        <p class="mt-0.5 text-xs text-gray-400">{{ $request->created_at?->format('d/m/Y H:i') }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
                                            @if($canRejectStageChanges)
                                                <button type="button" wire:click="rejectStageChange({{ $request->id }})" wire:loading.attr="disabled" class="rounded-xl border border-rose-200 bg-white px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-60">Rechazar</button>
                                            @endif
                                            @if($canApproveStageChanges)
                                                <button type="button" wire:click="approveStageChange({{ $request->id }})" wire:confirm="¿Aprobar el retroceso? La etapa de la cotización se actualizará inmediatamente." wire:loading.attr="disabled" class="rounded-xl bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-900 disabled:opacity-60">Aprobar</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-gray-500">No hay cambios de etapa pendientes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @elseif($managerSection === 'dispatch_authorizations')
        <section class="rounded-lg border border-amber-200 bg-white shadow-sm">
            <div class="border-b border-amber-100 bg-amber-50 px-5 py-4">
                <h2 class="text-lg font-black text-amber-950">Autorizar salidas parciales</h2>
                <p class="mt-1 text-xs font-bold text-amber-800">Solicitudes de Bodega pendientes antes de registrar salida Kardex.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($pendingDispatchItems as $item)
                    @php $saleOrder = $item->orderStatus?->saleOrder; @endphp
                    <div class="grid gap-4 px-5 py-4 md:grid-cols-[minmax(0,1fr)_auto_auto] md:items-center" wire:key="approval-dispatch-{{ $item->id }}">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-black text-slate-950">{{ $item->part_name ?: $item->product?->name ?: 'Producto' }}</p>
                            <p class="mt-1 text-xs font-bold text-slate-500">{{ $item->part_number ?: 'Sin referencia' }} · {{ $item->orderStatus?->tracker?->tracking_code ?: 'Pedido #'.$saleOrder?->id }} · {{ $saleOrder?->customer?->name ?: $saleOrder?->client_name ?: 'Cliente' }}</p>
                        </div>
                        <div class="rounded-md bg-emerald-50 px-4 py-2 text-center text-emerald-800"><span class="block text-[9px] font-black uppercase">Disponible</span><strong>{{ number_format(max(0, (float) $item->quantity_kardex_entry - (float) $item->quantity_kardex_output), 0) }}</strong></div>
                        <div class="flex flex-wrap gap-2">
                            @if($saleOrder)<a href="{{ route('orders.status.show', $saleOrder) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700">Ver pedido</a>@endif
                            @if($canApproveDispatch)<button type="button" wire:click="authorizeDispatchItem({{ $item->id }})" wire:confirm="¿Autorizar esta salida parcial?" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-black text-white">Autorizar salida</button>@endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-12 text-center"><p class="text-lg font-black text-emerald-800">No hay salidas parciales pendientes</p></div>
                @endforelse
            </div>
        </section>
    @elseif(!$selectedApproval)
        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-xs font-black uppercase tracking-widest text-slate-600">Ordenes pendientes de aprobacion</h2>
                        <p class="text-xs text-slate-400">{{ $pendingApprovals->count() }} ordenes por revisar</p>
                    </div>
                    <span class="w-fit rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-700">{{ $pendingApprovals->count() }}</span>
                </div>

                <div class="grid grid-cols-1 gap-4 p-4 xl:grid-cols-2">
                    @forelse($pendingApprovals as $approval)
                        @php
                            $quotation = $approval->quotationRequest;
                            $approvedCount = $quotation?->items?->where('is_approved', true)->count() ?? 0;
                            $finance = $quotation?->items?->where('is_approved', true)->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price) ?? 0;
                            $supplierNames = $quotation?->items?->where('is_approved', true)
                                ->map(fn ($item) => $item->supplierBidSelection?->supplier?->name ?? $item->selectedSupplier?->name)
                                ->filter()
                                ->unique()
                                ->values() ?? collect();
                        @endphp
                        <button type="button" wire:key="pending-approval-card-{{ $approval->id }}" wire:click="selectApproval({{ $approval->id }})" wire:loading.attr="disabled" data-testid="approval-open-button-{{ $approval->id }}" class="min-h-56 rounded-lg border border-slate-200 bg-white p-5 text-left transition hover:border-slate-300 hover:shadow-sm disabled:opacity-70">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-mono text-sm font-black text-slate-900">{{ $approval->approval_code ?? 'APV-'.$approval->id }}</p>
                                    <p class="font-mono text-xs text-slate-500">{{ $approval->tracker?->tracking_code ?? 'TRK pendiente' }}</p>
                                </div>
                                <span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-black uppercase text-amber-700">Pendiente</span>
                            </div>
                            <p class="truncate text-base font-black text-slate-900">{{ $quotation?->quote_to_name ?? 'Cliente no definido' }}</p>
                            <p class="mt-1 truncate text-xs font-bold text-slate-500">Solicitud {{ $quotation?->reference ?? 'N/D' }} · Solicitado por {{ $approval->requester?->name ?? 'Sistema' }}</p>

                            <div class="mt-5 grid grid-cols-2 gap-3 text-xs">
                                <div><span class="block font-black uppercase tracking-widest text-slate-400">Piezas</span><strong class="text-slate-800">{{ $approvedCount }}</strong></div>
                                <div><span class="block font-black uppercase tracking-widest text-slate-400">Proveedor</span><strong class="line-clamp-1 text-slate-800">{{ $supplierNames->isEmpty() ? 'Pendiente' : $supplierNames->join(', ') }}</strong></div>
                                <div><span class="block font-black uppercase tracking-widest text-slate-400">Fecha</span><strong class="text-slate-800">{{ $approval->created_at?->format('d/m/Y H:i') }}</strong></div>
                                <div><span class="block font-black uppercase tracking-widest text-slate-400">Total cliente</span><strong class="text-slate-900">C$ {{ number_format($finance, 2) }}</strong></div>
                            </div>
                        </button>
                    @empty
                        <div class="col-span-full px-4 py-12 text-center text-sm font-bold text-slate-400">No hay ordenes de compra pendientes de aprobacion.</div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-4 py-3">
                    <h2 class="text-xs font-black uppercase tracking-widest text-slate-600">Historial reciente</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($recentApprovals as $approval)
                        <button type="button" wire:key="recent-approval-card-{{ $approval->id }}" wire:click="selectApproval({{ $approval->id }})" wire:loading.attr="disabled" class="block w-full p-4 text-left hover:bg-slate-50 disabled:opacity-70">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-mono text-xs font-black text-slate-800">{{ $approval->approval_code ?? 'APV-'.$approval->id }}</p>
                                    <p class="text-xs text-slate-500">{{ $approval->requester?->name ?? 'Sistema' }} · {{ $approval->updated_at?->format('d/m/Y') }}</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-600">{{ $statusLabels[$approval->status] ?? $approval->status }}</span>
                            </div>
                        </button>
                    @empty
                        <div class="px-4 py-8 text-center text-sm font-bold text-slate-400">Sin historial reciente.</div>
                    @endforelse
                </div>
            </section>
        </div>
    @else
        @php
            $quotation = $selectedApproval->quotationRequest;
            $clientApproval = $quotation?->clientApprovals?->sortByDesc('approved_at')->first();
        @endphp

        <main id="approval-detail-panel" class="scroll-mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-white px-6 py-4">
                <button type="button" wire:click="backToInternalList" class="inline-flex items-center gap-2 text-sm font-black text-slate-700 hover:text-slate-950">
                    <span class="text-xl leading-none">&lt;</span>
                    Volver a ordenes pendientes
                </button>
            </div>

            <div class="border-b border-slate-100 bg-slate-950 px-6 py-5 text-white">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-widest text-slate-300">Aprobacion OC {{ $selectedApproval->approval_code ?? 'APV-'.$selectedApproval->id }}</p>
                        <h2 class="mt-1 text-2xl font-black">{{ $quotation?->reference ?? 'Sin referencia' }}</h2>
                        <p class="mt-2 text-sm text-slate-300">Cliente {{ $quotation?->quote_to_name ?? 'N/D' }} · Solicitado por {{ $selectedApproval->requester?->name ?? 'Sistema' }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-right text-xs">
                        <div><p class="text-slate-400">Seguimiento</p><p class="font-mono font-black">{{ $selectedApproval->tracker?->tracking_code ?? 'TRK pendiente' }}</p></div>
                        <div><p class="text-slate-400">Estado</p><p class="font-black">{{ $statusLabels[$selectedApproval->status] ?? $selectedApproval->status }}</p></div>
                        <div><p class="text-slate-400">Tipo</p><p class="font-black">{{ $typeLabels[$selectedApproval->request_type] ?? $selectedApproval->request_type }}</p></div>
                        <div><p class="text-slate-400">Fecha</p><p class="font-black">{{ $selectedApproval->created_at?->format('d/m/Y H:i') }}</p></div>
                    </div>
                </div>
            </div>

            <div class="border-b border-slate-100 px-6">
                <nav class="flex flex-wrap gap-2 py-3">
                    @foreach(['summary' => 'Resumen', 'products' => 'Piezas', 'supplier' => 'Proveedores', 'documents' => 'Documentos', 'timeline' => 'Historial'] as $tab => $label)
                        <button type="button" wire:click="setTab('{{ $tab }}')" class="rounded-lg px-3 py-2 text-xs font-black {{ $activeTab === $tab ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-100' }}">{{ $label }}</button>
                    @endforeach
                </nav>
            </div>

            <div class="p-6">
                @if($activeTab === 'summary')
                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <section class="rounded-lg border border-slate-200 p-4">
                            <h3 class="mb-3 text-xs font-black uppercase tracking-widest text-slate-600">Cliente</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Cliente</dt><dd class="text-right font-bold text-slate-900">{{ $quotation?->quote_to_name ?? 'No definido' }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Contacto</dt><dd class="text-right font-bold text-slate-900">{{ $selectedApproval->tracker?->customer?->contact_name ?? $quotation?->requested_by ?? 'N/D' }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Telefono</dt><dd class="text-right font-bold text-slate-900">{{ $selectedApproval->tracker?->customer?->phone_mobile ?? 'N/D' }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Correo</dt><dd class="text-right font-bold text-slate-900">{{ $quotation?->quote_to_email ?? $selectedApproval->tracker?->customer?->email ?? 'N/D' }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Maquinaria</dt><dd class="text-right font-bold text-slate-900">{{ $quotation?->clientMachine?->internal_code ?? $quotation?->clientMachine?->serial_number ?? 'N/D' }}</dd></div>
                            </dl>
                        </section>

                        <section class="rounded-lg border border-slate-200 p-4">
                            <h3 class="mb-3 text-xs font-black uppercase tracking-widest text-slate-600">Orden proveedor</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Solicitud</dt><dd class="font-bold text-slate-900">{{ $quotation?->reference ?? 'N/D' }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Aprobacion cliente</dt><dd class="font-bold text-slate-900">{{ $clientApproval?->status ?? $quotation?->status ?? 'N/D' }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Piezas aprobadas</dt><dd class="font-bold text-slate-900">{{ $approvedItems->count() }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-slate-500">Proveedores</dt><dd class="text-right font-bold text-slate-900">{{ $supplierGroups->keys()->join(', ') ?: 'Pendiente' }}</dd></div>
                            </dl>
                        </section>

                        <section class="rounded-lg border border-slate-200 p-4 lg:col-span-2">
                            <h3 class="mb-3 text-xs font-black uppercase tracking-widest text-slate-600">Resumen financiero</h3>
                            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                                @if($canViewCosts)
                                    <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Costo proveedor</p><p class="text-lg font-black text-slate-900">{{ number_format($financialSummary['supplier_cost_subtotal'] ?? 0, 2) }}</p></div>
                                    <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Costo estimado</p><p class="text-lg font-black text-slate-900">{{ number_format($financialSummary['estimated_total_cost'] ?? 0, 2) }}</p></div>
                                @endif
                                <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Total cliente</p><p class="text-lg font-black text-slate-900">{{ number_format($financialSummary['client_sales_total'] ?? 0, 2) }}</p></div>
                                @if($canViewMargin)
                                    <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Margen</p><p class="text-lg font-black text-slate-900">{{ number_format($financialSummary['gross_margin'] ?? 0, 2) }}</p></div>
                                    <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Margen %</p><p class="text-lg font-black text-slate-900">{{ number_format($financialSummary['margin_percent'] ?? 0, 2) }}%</p></div>
                                @endif
                                <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Moneda</p><p class="text-lg font-black text-slate-900">{{ $financialSummary['currency'] ?? 'USD' }}</p></div>
                            </div>
                        </section>

                        <section class="rounded-lg border border-slate-200 p-4 lg:col-span-2">
                            <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-600">OC generadas</h3>
                                    <p class="mt-1 text-xs font-bold text-slate-400">Ordenes por proveedor enlazadas a esta aprobacion.</p>
                                </div>
                                <span class="w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ $existingPurchaseOrders->count() }}</span>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                @forelse($existingPurchaseOrders as $purchaseOrder)
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-mono text-xs font-black text-slate-900">{{ $purchaseOrder->reference_number }}</p>
                                                <p class="mt-1 text-xs font-bold text-slate-500">{{ $purchaseOrder->supplier?->name ?? 'Proveedor N/D' }}</p>
                                            </div>
                                            <span class="rounded-full bg-white px-2 py-1 text-[10px] font-black text-slate-600">{{ $purchaseOrder->status }}</span>
                                        </div>
                                        <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                            <div><span class="block text-slate-400">Items</span><strong>{{ $purchaseOrder->items->count() }}</strong></div>
                                            <div><span class="block text-slate-400">Moneda</span><strong>{{ $purchaseOrder->currency ?? 'USD' }}</strong></div>
                                            <div><span class="block text-slate-400">Total</span><strong>{{ number_format((float) $purchaseOrder->total_fob, 2) }}</strong></div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm font-bold text-amber-800 md:col-span-2">Aun no hay OC enlazadas. Al generar OC por proveedor desde comercial se creara esta aprobacion con sus ordenes.</div>
                                @endforelse
                            </div>
                        </section>
                    </div>
                @elseif($activeTab === 'products')
                    @php
                        $visibleApprovedItems = $editingPurchasePreparationItemId
                            ? $approvedItems->filter(fn ($approvedItem) => $this->purchasePreparationForItem($selectedApproval, $approvedItem)?->id === $editingPurchasePreparationItemId)->values()
                            : $approvedItems;
                    @endphp

                    <div class="grid gap-3 {{ $editingPurchasePreparationItemId ? 'xl:grid-cols-1' : 'xl:grid-cols-2' }}">
                        @forelse($visibleApprovedItems as $item)
                            @php
                                $prepItem = $this->purchasePreparationForItem($selectedApproval, $item);
                                $options = $item->supplierBidResponseItems?->filter(fn ($option) => $option->is_selectable) ?? collect();
                                $supplierCostOriginal = $prepItem
                                    ? (float) ($prepItem->final_unit_cost ?? 0)
                                    : $this->supplierCost($item);
                                $supplierCurrency = $prepItem?->finalSupplierQuote?->currency
                                    ?? $item->supplierBidSelection?->currency
                                    ?? $item->supplier_currency
                                    ?? 'NIO';
                                $supplierCost = $prepItem
                                    ? (float) app(\App\Services\ExchangeRateService::class)->convert($supplierCostOriginal, $supplierCurrency, 'NIO')['amount']
                                    : $this->supplierCostInNio($item);
                                $salePrice = (float) $item->unit_price;
                                $quantity = (float) ($prepItem?->quantity_to_purchase ?? $item->quantity);
                                $lineCost = $supplierCost * $quantity;
                                $lineSale = $salePrice * (float) $item->quantity;
                                $lineMargin = $lineSale - $lineCost;
                                $delivery = $prepItem?->finalSupplierQuote?->estimated_arrival_date?->format('d/m/Y')
                                    ?? $prepItem?->finalSupplierQuote?->delivery_time
                                    ?? $item->supplierBidSelection?->estimated_arrival_date?->format('d/m/Y')
                                    ?? $item->supplierBidSelection?->delivery_time
                                    ?? 'N/D';
                            @endphp

                            <article class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" data-testid="approval-product-card-{{ $item->id }}">
                                <div>
                                    <div class="p-3">
                                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="rounded-md bg-slate-950 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-white">Item aprobado</span>
                                                    <span class="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-slate-600">Cant. {{ number_format($quantity, 2) }}</span>
                                                </div>
                                                <h3 class="mt-2 line-clamp-1 text-base font-black leading-tight text-slate-950">{{ $item->part_name ?? $item->product?->name ?? $item->description ?? 'Item sin nombre' }}</h3>
                                                <p class="mt-1 truncate text-xs font-bold text-slate-500">{{ $item->part_number ?? 'Parte N/D' }} / {{ $item->brand ?? 'Marca N/D' }} {{ $item->model ? '/ '.$item->model : '' }}</p>
                                            </div>
                                            <div class="grid grid-cols-3 gap-2 text-right text-xs">
                                                <div class="rounded-lg bg-slate-50 px-2 py-2">
                                                    <p class="text-[10px] font-black uppercase text-slate-400">Entrega</p>
                                                    <p class="mt-1 truncate font-black text-slate-900">{{ $delivery }}</p>
                                                </div>
                                                <div class="rounded-lg bg-slate-50 px-2 py-2">
                                                    <p class="text-[10px] font-black uppercase text-slate-400">Venta unit.</p>
                                                    <p class="mt-1 font-black text-slate-900">{{ number_format($salePrice, 2) }}</p>
                                                </div>
                                                @if($canViewCosts)
                                                    <div class="rounded-lg bg-slate-50 px-2 py-2">
                                                        <p class="text-[10px] font-black uppercase text-slate-400">Costo unit.</p>
                                                        <p class="mt-1 font-black text-slate-900">C$ {{ number_format($supplierCost, 2) }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="mt-3 grid grid-cols-2 gap-2">
                                            <div class="rounded-lg border border-slate-200 bg-white p-2">
                                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Proveedor oferta</p>
                                                <p class="mt-1 truncate text-xs font-black text-slate-900">{{ $prepItem?->originalSupplier?->name ?? $this->supplierName($item) }}</p>
                                                @if($canViewCosts)
                                                    <p class="mt-1 text-xs font-bold text-slate-500">{{ number_format((float) ($prepItem?->original_unit_cost ?? $this->supplierCost($item)), 2) }}</p>
                                                @endif
                                            </div>
                                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-2">
                                                <p class="text-[10px] font-black uppercase tracking-widest text-emerald-700">Proveedor final OC</p>
                                                <p class="mt-1 truncate text-xs font-black text-emerald-950">{{ $prepItem?->finalSupplier?->name ?? $this->supplierName($item) }}</p>
                                                @if($prepItem?->change_reason)
                                                    <p class="mt-1 line-clamp-2 text-xs font-bold text-emerald-700">{{ $prepItem->change_reason }}</p>
                                                @endif
                                            </div>
                                            <div class="rounded-lg border border-slate-200 bg-white p-2">
                                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Totales</p>
                                                <p class="mt-1 text-xs font-black text-slate-900">Venta {{ number_format($lineSale, 2) }}</p>
                                                @if($canViewCosts)
                                                    <p class="mt-1 text-xs font-bold text-slate-500">Costo {{ number_format($lineCost, 2) }}</p>
                                                @endif
                                            </div>
                                            @if($canViewMargin)
                                                <div class="rounded-lg border border-slate-200 bg-white p-2">
                                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Margen item</p>
                                                    <p class="mt-1 text-xs font-black {{ $lineMargin >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ number_format($lineMargin, 2) }}</p>
                                                    <p class="mt-1 text-xs font-bold text-slate-500">{{ $lineSale > 0 ? number_format(($lineMargin / $lineSale) * 100, 2) : '0.00' }}%</p>
                                                </div>
                                            @endif
                                        </div>

                                        @if($prepItem && $selectedApproval->status === 'pending')
                                            <button type="button" wire:click="{{ $editingPurchasePreparationItemId === $prepItem->id ? 'stopEditingOrder' : 'startEditingOrder('.$prepItem->id.')' }}" class="mt-3 w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">
                                                {{ $editingPurchasePreparationItemId === $prepItem->id ? 'Volver a todos los items' : 'Cambiar orden' }}
                                            </button>
                                        @endif
                                    </div>

                                    @if($editingPurchasePreparationItemId === $prepItem?->id)
                                    <aside class="border-t border-slate-200 bg-slate-50 p-3">
                                        @if($prepItem && $selectedApproval->status === 'pending')
                                            <div class="grid gap-3 xl:grid-cols-2">
                                                <div class="xl:col-span-2">
                                                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                                        <div>
                                                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Editar proveedor del item</p>
                                                            <p class="mt-1 text-xs font-bold text-slate-500">Solo estas editando este item. El cambio actualiza preparacion, OC y total.</p>
                                                        </div>
                                                        <button type="button" wire:click="stopEditingOrder" class="w-fit rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-100">Volver</button>
                                                    </div>
                                                </div>

                                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Usar cotizacion recibida</label>
                                                    <select wire:model="selectedFinalSupplierQuote.{{ $prepItem->id }}" class="mt-2 w-full rounded-lg border-slate-200 bg-white text-xs">
                                                        <option value="">Comparar proveedores / seleccionar</option>
                                                        @foreach($options as $option)
                                                            <option value="{{ $option->id }}">{{ $option->response?->supplier?->name }} / {{ $option->currency }} {{ number_format((float) $option->unit_cost, 2) }} / {{ $option->delivery_time ?: 'Entrega N/D' }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="text" wire:model="changeSupplierReason" class="mt-2 w-full rounded-lg border-slate-200 bg-white text-xs" placeholder="Motivo obligatorio">
                                                    <button type="button" wire:click="requestChangeFinalSupplier({{ $prepItem->id }})" class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-100">Guardar cambios de orden</button>
                                                </div>

                                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Agregar proveedor manual</label>
                                                    <select wire:model="manualSupplierId.{{ $prepItem->id }}" class="mt-2 w-full rounded-lg border-slate-200 bg-white text-xs">
                                                        <option value="">Seleccione proveedor</option>
                                                        @foreach($suppliers as $supplier)
                                                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="text" wire:model="manualSupplierName.{{ $prepItem->id }}" class="mt-2 w-full rounded-lg border-slate-200 bg-white text-xs" placeholder="O escriba proveedor nuevo">
                                                    <div class="mt-2 grid grid-cols-[1fr_92px] gap-2">
                                                        <input type="number" min="0" step="0.01" wire:model="manualSupplierCost.{{ $prepItem->id }}" class="w-full rounded-lg border-slate-200 bg-white text-xs" placeholder="Precio">
                                                        <select wire:model="manualSupplierCurrency.{{ $prepItem->id }}" class="w-full rounded-lg border-slate-200 bg-white text-xs">
                                                            <option value="USD">USD</option>
                                                            <option value="NIO">NIO</option>
                                                            <option value="EUR">EUR</option>
                                                        </select>
                                                    </div>
                                                    <input type="text" wire:model="manualSupplierDelivery.{{ $prepItem->id }}" class="mt-2 w-full rounded-lg border-slate-200 bg-white text-xs" placeholder="Entrega, ej. 7 dias">
                                                    <button type="button" wire:click="requestAddManualSupplierAndChange({{ $prepItem->id }})" class="mt-2 w-full rounded-lg bg-slate-900 px-4 py-2.5 text-xs font-black text-white hover:bg-black">Guardar cambios de orden</button>
                                                </div>
                                            </div>
                                        @else
                                            <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm font-bold text-slate-500">La edicion queda bloqueada cuando la aprobacion ya fue procesada.</div>
                                        @endif
                                    </aside>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-8 text-center text-sm font-bold text-slate-400">No hay piezas aprobadas.</div>
                        @endforelse
                    </div>

                    {{--
                    <div class="hidden overflow-x-auto rounded-lg border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500">
                                <tr>
                                    <th class="px-3 py-3">Pieza</th>
                                    <th class="px-3 py-3">Parte</th>
                                    <th class="px-3 py-3">Marca / Modelo</th>
                                    <th class="px-3 py-3 text-right">Cant.</th>
                                    <th class="px-3 py-3">Proveedor</th>
                                    @if($canViewCosts)<th class="px-3 py-3 text-right">Costo</th>@endif
                                    <th class="px-3 py-3 text-right">Venta cliente</th>
                                    @if($canViewMargin)<th class="px-3 py-3 text-right">Margen</th>@endif
                                    <th class="px-3 py-3">Entrega</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($approvedItems as $item)
                                    @php
                                        $prepItem = $this->purchasePreparationForItem($selectedApproval, $item);
                                        $options = $item->supplierBidResponseItems?->filter(fn ($option) => $option->is_selectable) ?? collect();
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-3 font-bold text-slate-900">{{ $item->part_name ?? $item->product?->name ?? $item->description }}</td>
                                        <td class="px-3 py-3">{{ $item->part_number ?? 'N/D' }}</td>
                                        <td class="px-3 py-3">{{ $item->brand ?? 'N/D' }} {{ $item->model ? '/ '.$item->model : '' }}</td>
                                        <td class="px-3 py-3 text-right">{{ number_format((float) $item->quantity, 2) }}</td>
                                        <td class="px-3 py-3">{{ $this->supplierName($item) }}</td>
                                        @if($canViewCosts)<td class="px-3 py-3 text-right">{{ number_format($this->supplierCost($item), 2) }}</td>@endif
                                        <td class="px-3 py-3 text-right">{{ number_format((float) $item->unit_price, 2) }}</td>
                                        @if($canViewMargin)<td class="px-3 py-3 text-right">{{ number_format($this->itemMargin($item), 2) }}</td>@endif
                                        <td class="px-3 py-3">{{ $item->supplierBidSelection?->estimated_arrival_date?->format('d/m/Y') ?? $item->supplierBidSelection?->delivery_time ?? 'N/D' }}</td>
                                    </tr>
                                    @if($prepItem && $selectedApproval->status === 'pending')
                                        <tr class="bg-slate-50/70">
                                            <td colspan="9" class="px-3 py-3">
                                                <div class="grid gap-3 lg:grid-cols-[1fr_260px_240px_180px] lg:items-end">
                                                    <div>
                                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Edicion admin OC</p>
                                                        <p class="mt-1 text-xs font-bold text-slate-600">
                                                            Proveedor final actual:
                                                            <span class="font-black text-slate-900">{{ $prepItem->finalSupplier?->name ?? 'Pendiente' }}</span>
                                                            · Costo {{ number_format((float) $prepItem->final_unit_cost, 2) }}
                                                        </p>
                                                    </div>
                                                    <select wire:model="selectedFinalSupplierQuote.{{ $prepItem->id }}" class="rounded-lg border-slate-200 bg-white text-xs">
                                                        <option value="">Comparar proveedores / seleccionar</option>
                                                        @foreach($options as $option)
                                                            <option value="{{ $option->id }}">
                                                                {{ $option->response?->supplier?->name }} · {{ $option->currency }} {{ number_format((float) $option->unit_cost, 2) }} · {{ $option->delivery_time ?: 'Entrega N/D' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <input type="text" wire:model="changeSupplierReason" class="rounded-lg border-slate-200 bg-white text-xs" placeholder="Motivo obligatorio del cambio">
                                                    <button type="button" wire:click="changeFinalSupplier({{ $prepItem->id }})" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-100">Actualizar proveedor</button>
                                                </div>
                                                <div class="mt-3 grid gap-3 border-t border-slate-200 pt-3 lg:grid-cols-[1fr_160px_110px_180px_180px] lg:items-end">
                                                    <div>
                                                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Agregar otro proveedor</label>
                                                        <select wire:model="manualSupplierId.{{ $prepItem->id }}" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-xs">
                                                            <option value="">Seleccione proveedor</option>
                                                            @foreach($suppliers as $supplier)
                                                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Precio</label>
                                                        <input type="number" min="0" step="0.01" wire:model="manualSupplierCost.{{ $prepItem->id }}" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-xs" placeholder="0.00">
                                                    </div>
                                                    <div>
                                                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Moneda</label>
                                                        <select wire:model="manualSupplierCurrency.{{ $prepItem->id }}" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-xs">
                                                            <option value="USD">USD</option>
                                                            <option value="NIO">NIO</option>
                                                            <option value="EUR">EUR</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="text-[10px] font-black uppercase tracking-widest text-slate-400">Entrega</label>
                                                        <input type="text" wire:model="manualSupplierDelivery.{{ $prepItem->id }}" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-xs" placeholder="Ej. 7 dias">
                                                    </div>
                                                    <button type="button" wire:click="addManualSupplierAndChange({{ $prepItem->id }})" class="rounded-lg bg-slate-900 px-4 py-2.5 text-xs font-black text-white hover:bg-black">Agregar y usar</button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr><td colspan="9" class="px-3 py-10 text-center font-bold text-slate-400">No hay piezas aprobadas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    --}}
                @elseif($activeTab === 'supplier')
                    <div class="space-y-4">
                        @forelse($supplierGroups as $supplierName => $items)
                            @php
                                $firstItem = $items->first();
                                $supplier = $firstItem->supplierBidSelection?->supplier ?? $firstItem->selectedSupplier;
                                $response = $firstItem->supplierBidSelection?->responseItem?->response;
                            @endphp
                            <section class="rounded-lg border border-slate-200 p-4">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <h3 class="text-lg font-black text-slate-900">{{ $supplierName }}</h3>
                                        <p class="text-sm text-slate-500">{{ $supplier?->country ?? 'Pais N/D' }} · {{ $supplier?->email ?? 'Email N/D' }}</p>
                                    </div>
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ $items->count() }} piezas</span>
                                </div>
                                <dl class="mt-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                                    <div><dt class="text-slate-500">Contacto</dt><dd class="font-bold">{{ $supplier?->contact_name ?? $response?->contact_name ?? 'N/D' }}</dd></div>
                                    <div><dt class="text-slate-500">Telefono</dt><dd class="font-bold">{{ $supplier?->phone_mobile ?? $supplier?->whatsapp ?? 'N/D' }}</dd></div>
                                    <div><dt class="text-slate-500">Pago</dt><dd class="font-bold">{{ $response?->payment_terms ?? 'N/D' }}</dd></div>
                                    <div><dt class="text-slate-500">Entrega</dt><dd class="font-bold">{{ $response?->general_delivery_time ?? $firstItem->supplierBidSelection?->delivery_time ?? 'N/D' }}</dd></div>
                                    <div><dt class="text-slate-500">Garantia</dt><dd class="font-bold">{{ $response?->general_warranty ?? $firstItem->supplierBidSelection?->warranty ?? 'N/D' }}</dd></div>
                                    <div><dt class="text-slate-500">Moneda</dt><dd class="font-bold">{{ $response?->currency ?? $firstItem->supplierBidSelection?->currency ?? 'USD' }}</dd></div>
                                </dl>
                            </section>
                        @empty
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm font-bold text-amber-800">No hay proveedor seleccionado para las piezas aprobadas.</div>
                        @endforelse
                    </div>
                @elseif($activeTab === 'documents')
                    @if($canViewDocuments)
                        <div class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-500">
                                    <tr>
                                        <th class="px-3 py-3">Nombre</th>
                                        <th class="px-3 py-3">Tipo</th>
                                        <th class="px-3 py-3">Fase</th>
                                        <th class="px-3 py-3">Fecha</th>
                                        <th class="px-3 py-3 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($documents as $document)
                                        <tr>
                                            <td class="px-3 py-3 font-bold text-slate-900">{{ $document->name }}</td>
                                            <td class="px-3 py-3">{{ $document->type }}</td>
                                            <td class="px-3 py-3">{{ $document->phase }}</td>
                                            <td class="px-3 py-3">{{ $document->date?->format('d/m/Y H:i') }}</td>
                                            <td class="px-3 py-3 text-right">
                                                <a href="{{ $document->url }}" target="_blank" class="font-black text-slate-700 hover:text-slate-900">Ver</a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-3 py-10 text-center font-bold text-slate-400">No hay documentos vinculados.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-5 text-sm font-bold text-slate-500">No tienes permiso para ver documentos relacionados.</div>
                    @endif
                @elseif($activeTab === 'timeline')
                    <div class="space-y-4">
                        @forelse($selectedApproval->tracker?->activities ?? [] as $activity)
                            <div class="border-l-2 border-slate-200 pl-4">
                                <p class="text-sm font-black text-slate-900">{{ $activity->description }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $activity->created_at?->format('d/m/Y H:i') }} · {{ $activity->user?->name ?? 'Sistema' }} · {{ $activity->phase }}</p>
                            </div>
                        @empty
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-5 text-sm font-bold text-slate-500">Sin historial para esta aprobacion.</div>
                        @endforelse
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4 md:flex-row md:items-center md:justify-between">
                <p class="text-xs font-bold text-slate-500">Esta es la unica aprobacion formal: confirma si la orden de compra puede generarse para proveedor.</p>
                <div class="flex flex-wrap gap-2">
                    @if($selectedApproval->status === 'pending')
                        @if($canApproveInternal)
                            <button type="button" wire:click="approveSelected" data-testid="approval-approve-button" wire:loading.attr="disabled" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-black disabled:opacity-60">Aprobar OC</button>
                        @endif
                        @if($canRequestInternalChanges)
                            <button type="button" wire:click="openChanges" data-testid="approval-change-request-button" class="rounded-lg border border-amber-200 bg-white px-4 py-2 text-xs font-black text-amber-700 hover:bg-amber-50">Solicitar cambios</button>
                        @endif
                        @if($canRejectInternal)
                            <button type="button" wire:click="openReject" data-testid="approval-reject-button" class="rounded-lg border border-rose-200 bg-white px-4 py-2 text-xs font-black text-rose-700 hover:bg-rose-50">Cancelar OC</button>
                        @endif
                    @else
                        <span class="rounded-lg bg-white px-4 py-2 text-xs font-black text-slate-500">Solicitud procesada</span>
                    @endif
                </div>
            </div>
        </main>
    @endif

    @if($showDecisionModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <button type="button" class="absolute inset-0 bg-slate-900/60" wire:click="closeDecision"></button>
            <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow-2xl">
                <h3 class="text-lg font-black text-slate-900">{{ $decisionMode === 'changes' ? 'Solicitar cambios en OC' : 'Cancelar orden de compra' }}</h3>
                <p class="mt-1 text-sm text-slate-500">El motivo es obligatorio y quedara registrado en actividad y Order Tracker.</p>
                <textarea wire:model="rejectionReason" data-testid="approval-reason-textarea" class="mt-4 h-32 w-full rounded-lg border-slate-200 text-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Motivo obligatorio"></textarea>
                @error('rejectionReason') <p class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="closeDecision" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-600">Cancelar</button>
                    <button type="button" wire:click="submitDecision" data-testid="approval-decision-submit-button" class="rounded-lg px-4 py-2 text-xs font-black text-white {{ $decisionMode === 'changes' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-rose-600 hover:bg-rose-700' }}">
                        {{ $decisionMode === 'changes' ? 'Enviar cambios' : 'Confirmar cancelacion' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showOrderChangeConfirmation)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <button type="button" class="absolute inset-0 bg-slate-900/60" wire:click="closeOrderChangeConfirmation"></button>
            <div class="relative w-full max-w-xl rounded-lg bg-white shadow-2xl">
                <div class="border-b border-slate-100 px-6 py-5">
                    <p class="text-[10px] font-black uppercase tracking-widest text-amber-600">Confirmar cambios de orden</p>
                    <h3 class="mt-1 text-lg font-black text-slate-950">Aplicar cambios a la OC</h3>
                    <p class="mt-2 text-sm font-bold text-slate-500">
                        Esto guardara la orden como el admin la dejo, actualizara la OC generada, sincronizara Order Status y dejara actividad en Order Tracker.
                    </p>
                </div>
                <div class="space-y-3 px-6 py-5 text-sm">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-800">
                        Revise proveedor, precio, moneda, entrega y motivo antes de confirmar. Despues de aplicar, esta sera la informacion usada para continuar el flujo operativo.
                    </div>
                    <dl class="grid grid-cols-1 gap-3 text-xs md:grid-cols-2">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <dt class="font-black uppercase tracking-widest text-slate-400">Tipo</dt>
                            <dd class="mt-1 font-black text-slate-900">{{ $pendingOrderChangeType === 'manual_supplier' ? 'Proveedor manual / nuevo' : 'Cotizacion existente' }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <dt class="font-black uppercase tracking-widest text-slate-400">Item</dt>
                            <dd class="mt-1 font-mono font-black text-slate-900">#{{ $pendingOrderChangeItemId }}</dd>
                        </div>
                    </dl>
                </div>
                <div class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="closeOrderChangeConfirmation" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-100">Revisar otra vez</button>
                    <button type="button" wire:click="confirmPendingOrderChange" data-testid="confirm-order-change-button" class="rounded-lg bg-slate-900 px-4 py-2.5 text-xs font-black text-white hover:bg-black">Aplicar cambios</button>
                </div>
            </div>
        </div>
    @endif
</div>
