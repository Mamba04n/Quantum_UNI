@php
    $stages = [
        [
            'key' => \App\Services\OrderStatusService::PHASE_PURCHASE,
            'label' => 'OC / Compra',
            'order' => 1,
        ],
        [
            'key' => \App\Services\OrderStatusService::PHASE_SUPPLIER_CONFIRMATION,
            'label' => 'Confirmación',
            'order' => 2,
        ],
        [
            'key' => \App\Services\OrderStatusService::PHASE_TRANSIT,
            'label' => 'Tránsito / Aduana',
            'order' => 3,
        ],
        [
            'key' => \App\Services\OrderStatusService::PHASE_WAREHOUSE,
            'label' => 'Bodega',
            'order' => 4,
        ],
        [
            'key' => \App\Services\OrderStatusService::PHASE_KARDEX_OUTPUT,
            'label' => 'Facturación',
            'order' => 5,
        ],
        [
            'key' => \App\Services\OrderStatusService::PHASE_DELIVERY,
            'label' => 'Despacho y Entrega',
            'order' => 6,
        ],
    ];

    $currentStatus = $orderStatus->current_status ?: $orderStatus->administrative_status;
    $currentPhase = $orderStatus->administrative_phase ?: $orderStatus->current_phase;

    $currentOrder = match ($currentStatus) {
        'pendiente_oc', 'oc_generada', 'esperando_confirmacion_proveedor' => 1,
        'confirmado_proveedor', 'confirmado_por_proveedor', 'pago_confirmado' => 2,
        'salida_proveedor', 'en_transito' => 3,
        'en_aduana', 'liberado_aduana' => 4,
        'recibido_bodega_parcial', 'recibido_bodega_total', 'recibido_parcial', 'recibido_total', 'entrada_kardex_parcial', 'entrada_kardex_total', 'reservado_cliente', 'available_for_dispatch', 'waiting_other_items', 'held_by_admin', 'dispatch_authorization_requested', 'approved_for_partial_dispatch' => 5,
        'salida_kardex_parcial', 'salida_kardex_total', 'en_ruta_cliente', 'entregado_parcial', 'entregado_total', 'entrega_parcial', 'cerrado' => 6,
        default => match ($currentPhase) {
            \App\Services\OrderStatusService::PHASE_PURCHASE => 1,
            \App\Services\OrderStatusService::PHASE_SUPPLIER_CONFIRMATION => 2,
            \App\Services\OrderStatusService::PHASE_TRANSIT => 3,
            \App\Services\OrderStatusService::PHASE_CUSTOMS, \App\Services\OrderStatusService::PHASE_WAREHOUSE => 4,
            \App\Services\OrderStatusService::PHASE_KARDEX_ENTRY, \App\Services\OrderStatusService::PHASE_KARDEX_OUTPUT => 5,
            \App\Services\OrderStatusService::PHASE_LOGISTICS, \App\Services\OrderStatusService::PHASE_DELIVERY => 6,
            default => 1,
        },
    };

    $isCancelled = in_array($currentStatus, ['cancelado', 'cancelado_proveedor'], true);
@endphp

<div class="rounded-2xl border border-slate-200 bg-white px-5 py-3 shadow-xs">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <!-- Acciones rápidas superiores (Izquierda) -->
        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('orders.status.index') }}"
                class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 shadow-xs transition hover:border-slate-300 hover:bg-slate-50"
            >
                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m15 18-6-6 6-6"/></svg>
                <span>Volver al Tablero</span>
            </a>

            @if($orderStatus->tracking_code)
                <span class="inline-flex h-9 items-center rounded-xl bg-slate-100 px-3 text-xs font-bold text-slate-700">
                    <svg class="mr-1.5 h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                    {{ $orderStatus->tracking_code }}
                </span>
            @endif

            <span class="inline-flex h-9 items-center rounded-xl bg-slate-50 px-3 text-xs font-bold text-slate-600 border border-slate-200">
                Progreso: {{ $orderStatus->progress_percentage }}%
            </span>
        </div>

        <!-- Barra de Progreso Chevron Minimalista (Derecha) -->
        <nav aria-label="Progreso operativo del pedido" class="overflow-x-auto">
            <div class="inline-flex h-9 items-stretch overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                @foreach($stages as $index => $stage)
                    @php
                        $isCurrent = ! $isCancelled && $stage['order'] === $currentOrder;
                        $isCompleted = ! $isCancelled && $stage['order'] < $currentOrder;
                        $isPending = $isCancelled || $stage['order'] > $currentOrder;
                        $isFirst = $loop->first;
                        $isLast = $loop->last;

                        if ($isCurrent) {
                            $clipStyle = $isFirst
                                ? 'clip-path: polygon(0 0, calc(100% - 9px) 0, 100% 50%, calc(100% - 9px) 100%, 0 100%);'
                                : ($isLast
                                    ? 'clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%, 9px 50%);'
                                    : 'clip-path: polygon(0 0, calc(100% - 9px) 0, 100% 50%, calc(100% - 9px) 100%, 0 100%, 9px 50%);');
                            $itemClass = 'bg-slate-900 text-white font-black z-20';
                        } elseif ($isCompleted) {
                            $clipStyle = $isFirst
                                ? 'clip-path: polygon(0 0, calc(100% - 9px) 0, 100% 50%, calc(100% - 9px) 100%, 0 100%);'
                                : ($isLast
                                    ? 'clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%, 9px 50%);'
                                    : 'clip-path: polygon(0 0, calc(100% - 9px) 0, 100% 50%, calc(100% - 9px) 100%, 0 100%, 9px 50%);');
                            $itemClass = 'bg-slate-100/90 text-slate-800 font-bold hover:bg-slate-200/80 z-10';
                        } else {
                            $clipStyle = $isFirst
                                ? 'clip-path: polygon(0 0, calc(100% - 9px) 0, 100% 50%, calc(100% - 9px) 100%, 0 100%);'
                                : ($isLast
                                    ? 'clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%, 9px 50%);'
                                    : 'clip-path: polygon(0 0, calc(100% - 9px) 0, 100% 50%, calc(100% - 9px) 100%, 0 100%, 9px 50%);');
                            $itemClass = 'bg-white text-slate-400 font-medium hover:text-slate-600 z-0';
                        }
                    @endphp

                    <button
                        type="button"
                        wire:click="setPhase('{{ $stage['key'] }}')"
                        class="flex items-center text-[10px] uppercase tracking-wider transition-all {{ $itemClass }} {{ $isFirst ? 'pl-3.5 pr-4' : '-ml-2 pl-5 pr-4' }}"
                        style="{{ $clipStyle }}"
                    >
                        @if($isCompleted)
                            <svg class="mr-1.5 h-3 w-3 text-emerald-600 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        @elseif($isCurrent)
                            <span class="mr-1.5 h-1.5 w-1.5 rounded-full bg-emerald-400 flex-shrink-0"></span>
                        @endif
                        <span class="whitespace-nowrap">{{ $stage['label'] }}</span>
                    </button>
                @endforeach
            </div>
        </nav>
    </div>
</div>
