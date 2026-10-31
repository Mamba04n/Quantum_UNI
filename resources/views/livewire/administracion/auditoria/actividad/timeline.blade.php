<section class="xl:col-span-2 bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest">Historial centralizado</h3>
    </div>
    <div class="divide-y divide-gray-100">
        @forelse($logs as $log)
            @php
                $humanActions = [
                    'inventory.movement' => 'Movimiento de inventario',
                    'ticket.opened' => 'Ticket registrado',
                    'created' => 'Registro creado',
                    'updated' => 'Registro actualizado',
                    'deleted' => 'Registro eliminado',
                    'restored' => 'Registro restaurado',
                    'baseline.snapshot' => 'Estado inicial observado',
                    'auth.login' => 'Inicio de sesión',
                    'auth.logout' => 'Cierre de sesión',
                    'auth.failed' => 'Acceso rechazado',
                    'http.operation' => 'Solicitud al sistema',
                    'http.error' => 'Solicitud rechazada / error',
                    'application.exception' => 'Error durante una operación',
                    'customer_request.sent_to_bid' => 'Solicitud enviada a licitacion',
                    'supplier_bid.response_created' => 'Respuesta de proveedor registrada',
                    'client_offer.pdf_generated' => 'PDF de oferta cliente generado',
                    'client_offer.sent_to_approval' => 'Oferta enviada a aprobacion',
                    'order_approval.approved' => 'Orden aprobada',
                    'customer.deactivated' => 'Cliente desactivado',
                    'customer.deleted' => 'Cliente eliminado',
                    'user.deactivated' => 'Usuario desactivado',
                    'user.deleted' => 'Usuario eliminado',
                    'component_usage_updated' => 'Uso de componente actualizado',
                    'component_status_alert' => 'Alerta de componente',
                    'hourmeter_updated' => 'Horometro actualizado',
                ];
                $actionLabel = $humanActions[$log->action] ?? str($log->action)->replace(['_', '.'], ' ')->headline();
                $badge = match($log->module) {
                    'admin' => 'bg-violet-50 text-violet-700 border-violet-100',
                    'ventas' => 'bg-teal-50 text-teal-700 border-teal-100',
                    'finanzas' => 'bg-blue-50 text-blue-700 border-blue-100',
                    'crm' => 'bg-rose-50 text-rose-700 border-rose-100',
                    'compras' => 'bg-amber-50 text-amber-700 border-amber-100',
                    'kpi' => 'bg-slate-50 text-slate-700 border-slate-100',
                    default => 'bg-gray-50 text-gray-700 border-gray-100',
                };
            @endphp
            <article wire:key="event-{{ $log->source }}-{{ $log->id }}" class="px-5 py-4 hover:bg-gray-50/60">
                <div class="flex items-start gap-4">
                    <div class="mt-1 h-9 w-9 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center text-[11px] font-black shrink-0">
                        {{ $log->user ? mb_strtoupper(mb_substr(trim($log->user->name), 0, 2, 'UTF-8'), 'UTF-8') : 'S' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-black text-gray-900">{{ $actionLabel }}</span>
                            <span class="inline-flex px-2 py-0.5 rounded-full border text-[10px] font-black {{ $badge }}">{{ $modules[$log->module] ?? ucfirst($log->module) }}</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-600">{{ $log->description }}</p>
                        @if(is_array($log->metadata['calls'] ?? null) && $log->metadata['calls'])
                            <p class="mt-1 text-sm text-gray-600">Acción solicitada: {{ implode(', ', array_map(fn ($method) => ['saveDirectExpense' => 'Registrar gasto', 'saveDirectIncome' => 'Registrar ingreso', 'saveCxcPayment' => 'Registrar cobro', 'saveCxpPayment' => 'Registrar pago'][$method] ?? $method, array_filter($log->metadata['calls'], 'is_string'))) }}</p>
                        @endif
                        <div class="mt-2 flex flex-wrap items-center gap-3 text-[11px] text-gray-400">
                            <span>{{ $log->created_at->format('d/m/Y H:i:s') }}</span>
                            <span>{{ ['auditoria' => 'Auditoría de registros / accesos', 'actividad' => 'Actividad de negocio', 'inventario' => 'Movimiento de inventario', 'soporte' => 'Ticket de soporte', 'seguimiento' => 'Seguimiento de pedidos'][$log->source] ?? $log->source }}</span>
                            <span>{{ $log->user?->name ?? 'Sistema / sin usuario registrado' }}</span>
                            <span>{{ $roles[$log->user?->role ?? ''] ?? ($log->user?->role ?? 'Sin rol') }}</span>
                        </div>
                        @if(auth()->user()?->isAdmin())
                            <details class="mt-3">
                                <summary class="cursor-pointer text-[11px] font-bold text-slate-600">Ver detalles tecnicos</summary>
                                <div class="mt-2 rounded-lg bg-gray-50 p-3 font-mono text-[10px] text-gray-500">
                                    <div>accion: {{ $log->action }}</div>
                                    <div>ip: {{ $log->ip_address ?? '-' }}</div>
                                    <div>user_id: {{ $log->user_id ?? '-' }}</div>
                                    @if($log->metadata)
                                        <pre class="mt-2 whitespace-pre-wrap break-words">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    @endif
                                </div>
                            </details>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="px-6 py-14 text-center text-gray-500">Sin registros de actividad general.</div>
        @endforelse
    </div>
    @if($logs->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">{{ $logs->links() }}</div>
    @endif
</section>
