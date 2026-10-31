<div class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-400">Logistica</p>
                <h1 class="mt-2 text-3xl font-black text-slate-950">Despachos al cliente</h1>
                <p class="mt-2 max-w-3xl text-sm font-semibold text-slate-500">Selecciona partidas liberadas por Order Status. El stock ya fue descontado en salida Kardex; aqui solo se registra la salida logistica y se actualiza el seguimiento.</p>
            </div>
            <button wire:click="exportExcel" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-700 shadow-sm hover:bg-slate-50">
                Exportar Excel
            </button>
        </div>

        @if (session()->has('message'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-black text-emerald-800">
                {{ session('message') }}
            </div>
        @endif

        @error('dispatch')
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-800">
                {{ $message }}
            </div>
        @enderror

        <section class="grid gap-6 xl:grid-cols-[420px_1fr]">
            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <h2 class="text-sm font-black text-slate-950">Nuevo despacho</h2>
                    <p class="mt-1 text-xs font-bold text-slate-500">Partidas disponibles segun salida Kardex y decision operativa.</p>
                </div>

                <div class="space-y-4 p-4">
                    <label class="block">
                        <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Pedido con partidas listas</span>
                        <select wire:model.live="selectedOrderStatusId" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                            <option value="">Seleccionar pedido</option>
                            @foreach($pending_orders as $order)
                                <option value="{{ $order->id }}">
                                    {{ $order->order_code }} - {{ $order->saleOrder?->client_name ?: $order->customer?->name ?: 'Sin cliente' }}
                                </option>
                            @endforeach
                        </select>
                        @error('selectedOrderStatusId') <span class="mt-1 block text-xs font-bold text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    @if($selected_order)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Pedido seleccionado</p>
                            <h3 class="mt-1 text-base font-black text-slate-950">{{ $selected_order['reference'] }}</h3>
                            <p class="mt-1 text-xs font-bold text-slate-500">{{ $selected_order['client_name'] ?: 'Sin cliente' }} · {{ $selected_order['tracking_code'] ?: 'Sin tracking' }}</p>
                            <span class="mt-3 inline-flex rounded-full {{ $selected_order['ship_together'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-3 py-1 text-[10px] font-black uppercase">
                                {{ $selected_order['ship_together'] ? 'Envio conjunto listo' : 'Puede requerir autorizacion parcial' }}
                            </span>
                        </div>
                    @endif

                    @if(count($order_items) > 0)
                        <div class="space-y-2">
                            <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Partidas a despachar</p>
                            <div class="max-h-[420px] space-y-2 overflow-y-auto pr-1">
                                @foreach($order_items as $item)
                                    <div class="rounded-lg border {{ $item['allowed'] ? (!empty($dispatch[$item['id']]['selected']) ? 'border-slate-300 bg-white' : 'border-slate-200 bg-white') : 'border-amber-200 bg-amber-50' }} p-3">
                                        <div class="flex items-start gap-3">
                                            <input type="checkbox"
                                                wire:model.live="dispatch.{{ $item['id'] }}.selected"
                                                class="mt-1 rounded border-slate-300 text-slate-950 focus:ring-slate-300 disabled:opacity-40"
                                                @disabled(! $item['allowed'])>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-black text-slate-950">{{ $item['description'] }}</p>
                                                <p class="mt-1 text-xs font-bold text-slate-500">{{ $item['part_number'] ?: 'Sin referencia' }} {{ $item['brand'] ? '· '.$item['brand'] : '' }} {{ $item['model'] ? '/ '.$item['model'] : '' }}</p>
                                                <div class="mt-2 grid grid-cols-3 gap-1.5 text-center">
                                                    <div class="rounded-md bg-slate-50 px-2 py-1.5"><p class="text-[9px] font-black uppercase text-slate-400">Kardex</p><p class="text-sm font-black text-slate-900">{{ $item['quantity_kardex_output'] }}</p></div>
                                                    <div class="rounded-md bg-slate-50 px-2 py-1.5"><p class="text-[9px] font-black uppercase text-slate-400">Desp</p><p class="text-sm font-black text-slate-900">{{ $item['quantity_dispatched'] }}</p></div>
                                                    <div class="rounded-md bg-emerald-50 px-2 py-1.5"><p class="text-[9px] font-black uppercase text-emerald-700">Pend</p><p class="text-sm font-black text-emerald-900">{{ $item['quantity_pending'] }}</p></div>
                                                </div>
                                                <p class="mt-2 text-[10px] font-black uppercase {{ $item['allowed'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $item['decision_label'] }}</p>
                                            </div>
                                            @if($item['allowed'] && !empty($dispatch[$item['id']]['selected']))
                                                <input type="number"
                                                    wire:model.live="dispatch.{{ $item['id'] }}.qty"
                                                    min="1"
                                                    max="{{ $item['quantity_pending'] }}"
                                                    step="1"
                                                    class="w-20 rounded-lg border-slate-200 text-center text-sm font-black">
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @elseif($selectedOrderStatusId)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm font-bold text-amber-800">
                            Este pedido no tiene partidas autorizadas para despacho.
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Costo envio</span>
                            <input wire:model="shipping_cost" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="0.00">
                            @error('shipping_cost') <span class="mt-1 block text-xs font-bold text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Moneda</span>
                            <select wire:model="currency" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                <option value="USD">USD</option>
                                <option value="NIO">NIO</option>
                            </select>
                        </label>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Cliente</span>
                            <select wire:model="client_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                <option value="">Sin asignar</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Maquina</span>
                            <select wire:model="client_machine_id" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                <option value="">Sin maquina</option>
                                @foreach($machines as $machine)
                                    <option value="{{ $machine->id }}">{{ $machine->name ?? ('Maquina #'.$machine->id) }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <textarea wire:model="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Notas logisticas"></textarea>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input wire:model.live="generate_invoice" type="checkbox" class="mt-0.5 rounded border-slate-300 text-slate-950 focus:ring-slate-300">
                            <span>
                                <span class="block text-sm font-black text-slate-950">Generar factura con este despacho</span>
                                <span class="mt-0.5 block text-xs font-semibold text-slate-500">Se guardará con exactamente los productos y cantidades agrupados. El Packing List se genera siempre.</span>
                            </span>
                        </label>

                        @if($generate_invoice)
                            <div class="mt-3 grid gap-3 border-t border-slate-200 pt-3 sm:grid-cols-2">
                                <label class="block sm:col-span-2">
                                    <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Número de factura</span>
                                    <input wire:model="invoice_number" type="text" class="mt-1 w-full rounded-lg border-slate-200 text-sm font-black" placeholder="Ej.: 000123">
                                    @error('invoice_number') <span class="mt-1 block text-xs font-bold text-rose-600">{{ $message }}</span> @enderror
                                </label>
                                <label class="block">
                                    <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Condición</span>
                                    <select wire:model="invoice_condition" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                        <option>Contado</option><option>30 Días</option><option>60 Días</option><option>90 Días</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Vencimiento</span>
                                    <input wire:model="invoice_due_date" type="date" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                </label>
                            </div>
                        @endif
                    </div>

                    <button wire:click="save" wire:loading.attr="disabled" wire:target="save" class="w-full rounded-lg bg-slate-950 px-4 py-3 text-sm font-black text-white hover:bg-red-800 disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">{{ $generate_invoice ? 'Registrar despacho y generar factura' : 'Registrar despacho al cliente' }}</span>
                        <span wire:loading wire:target="save">Registrando...</span>
                    </button>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-black text-slate-950">Historial de despachos</h2>
                        <p class="mt-1 text-xs font-bold text-slate-500">Salidas registradas desde Order Status.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ $shipments->count() }} registros</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[920px] divide-y divide-slate-100 text-left text-xs">
                        <thead class="bg-white text-[10px] font-black uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Pedido / Cliente</th>
                                <th class="px-4 py-3">Partida</th>
                                <th class="px-4 py-3 text-center">Cantidad</th>
                                <th class="px-4 py-3 text-right">Costo</th>
                                <th class="px-4 py-3 text-center">Fecha</th>
                                <th class="px-4 py-3 text-center">PDF</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($shipments as $shipment)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3">
                                        <p class="font-black text-slate-950">{{ $shipment->order_id }}</p>
                                        <p class="mt-1 text-[10px] font-bold text-slate-500">{{ $shipment->client?->name ?? 'Sin cliente' }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="max-w-[260px] truncate font-black text-slate-800">{{ $shipment->orderStatusItem?->part_name ?: $shipment->product?->name ?: 'Producto' }}</p>
                                        <p class="mt-1 text-[10px] font-bold text-slate-400">{{ $shipment->orderStatusItem?->part_number ?: $shipment->product?->sku ?: 'Sin referencia' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">{{ $shipment->quantity }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <p class="font-black text-slate-950">{{ $shipment->currency }} {{ number_format((float) $shipment->shipping_cost, 2) }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-slate-500">
                                        {{ $shipment->shipped_at?->format('d/m/Y') ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button wire:click="exportPdf({{ $shipment->id }})" class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50">
                                            PDF
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-sm font-bold text-slate-500">Sin despachos registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
