<div class="space-y-8">
    <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Orden de Venta</p>
            <h2 class="text-3xl font-black text-gray-900 tracking-tight">{{ $order->reference }}</h2>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right hidden md:block">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Estado del Pedido</p>
                <p class="font-bold text-gray-900">{{ $order->status }}</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-slate-900 flex items-center justify-center text-white shadow-lg shadow-slate-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>
    </div>

    <!-- Tracker Visual - Estilo ERP SaaS -->
    <div class="bg-white p-10 rounded-3xl border border-gray-100 shadow-sm relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-1 bg-slate-50"></div>

        <div class="relative">
            <!-- Línea Conectora Animada -->
            <div class="absolute left-5 top-0 bottom-0 w-0.5 bg-gray-100 md:left-0 md:top-5 md:h-0.5 md:w-full rounded-full">
                @php
                    $completedCount = collect($steps)->where('completed', true)->count();
                    $progressPercentage = $completedCount > 1 ? ($completedCount - 1) * 25 : 0;
                @endphp
                <div class="absolute top-0 left-0 w-full md:h-full bg-indigo-600 transition-all duration-1000 ease-out hidden md:block" style="width: {{ $progressPercentage }}%;"></div>
                <div class="absolute top-0 left-0 h-full w-full bg-indigo-600 transition-all duration-1000 ease-out md:hidden" style="height: {{ $progressPercentage }}%;"></div>
            </div>

            <!-- Camioncito animado (Desktop) -->
            <div class="absolute top-1 -mt-3 hidden md:flex items-center justify-center transition-all duration-1000 ease-out z-20" style="left: {{ $progressPercentage }}%; transform: translateX(-50%);">
                <div class="bg-white p-1.5 rounded-full shadow-[0_4px_15px_-3px_rgba(79,70,229,0.4)] text-indigo-600 animate-[bounce_2s_infinite]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                    </svg>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-10 relative">
                @foreach($steps as $step)
                    <div class="flex md:flex-col items-center md:text-center gap-4">
                        <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 z-10 transition-all duration-500
                            {{ $step['completed'] ? 'bg-slate-900 text-white shadow-xl shadow-slate-200 scale-110' : 'bg-white border-2 border-gray-100 text-gray-300' }}">
                            @if($step['completed'])
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            @else
                                <div class="w-2 h-2 rounded-full bg-gray-200"></div>
                            @endif
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest leading-tight {{ $step['completed'] ? 'text-slate-900' : 'text-gray-400' }}">
                                {{ $step['label'] }}
                            </p>
                            @if($step['date'] && $step['completed'])
                                <p class="text-[9px] text-slate-400 mt-1 font-bold">{{ date('d M, Y', strtotime($step['date'])) }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Detalle de Productos - Estilo ERP SaaS -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-8 py-5 border-b border-gray-50 bg-slate-50/50">
            <h3 class="font-black text-gray-900 text-[10px] uppercase tracking-[0.2em]">Resumen de Partes y Repuestos</h3>
        </div>
        <div class="p-4 space-y-3">
            @foreach($items as $item)
                <div class="flex items-center justify-between p-4 bg-white rounded-2xl border border-gray-50 hover:border-slate-200 transition-colors">
                    <div class="flex items-center gap-6">
                        <div class="w-12 h-12 bg-slate-50 rounded-xl flex items-center justify-center text-slate-400 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900 tracking-tight leading-tight mb-1">{{ $item->description }}</p>
                            <p class="text-[10px] font-bold text-slate-400 tracking-widest uppercase">
                                SKU: {{ $item->part_number ?: 'N/A' }} | Cantidad: {{ (int)$item->quantity }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-black text-gray-900 tracking-tight">{{ number_format($item->total_price, 2) }} <span class="text-xs">{{ $order->currency }}</span></p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="bg-slate-900 p-8 flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-white/40">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <div>
                    <p class="text-[9px] font-black uppercase tracking-widest text-white/40 mb-1">Dirección de Envío</p>
                    <p class="text-xs text-white/80 font-medium max-w-xs">{{ $order->shipping_address ?: 'Retiro en Sucursal' }}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-black uppercase tracking-widest text-white/40 mb-1">Monto Total Liquidado</p>
                <p class="text-4xl font-black text-white leading-none tracking-tighter">
                    {{ number_format($order->total_amount, 2) }} <span class="text-lg">{{ $order->currency }}</span>
                </p>
            </div>
        </div>
    </div>
</div>
