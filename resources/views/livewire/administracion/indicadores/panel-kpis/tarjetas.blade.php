<section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
    @php
        $cards = [
            ['Margen promedio', data_get($dashboard, 'salesMargin.available') ? data_get($dashboard, 'salesMargin.averagePercent') . '%' : 'Oculto', data_get($dashboard, 'salesMargin.salesAnalyzed', 0) . ' ventas analizadas'],
            ['Ventas totales', 'C$ ' . number_format((float) data_get($dashboard, 'salesVolume.totalMoney', 0), 2), data_get($dashboard, 'salesVolume.orders', 0) . ' ordenes'],
            ['Cierre', data_get($dashboard, 'closeRate.closeRate', 0) . '%', data_get($dashboard, 'closeRate.approved', 0) . ' / ' . data_get($dashboard, 'closeRate.sent', 0) . ' aprobadas'],
            ['Entrega promedio', data_get($dashboard, 'deliveryTime.available') ? data_get($dashboard, 'deliveryTime.averageDays') . ' d' : 'N/D', 'Pendiente de fechas vinculadas'],
            ['Valor inventario', 'C$ ' . number_format((float) data_get($dashboard, 'inventoryCost.totalValue', 0), 2), data_get($dashboard, 'inventoryCost.units', 0) . ' unidades'],
        ];
    @endphp
    @foreach($cards as $card)
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ $card[0] }}</p>
            <p class="mt-2 text-xl font-black text-gray-900">{{ $card[1] }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $card[2] }}</p>
        </div>
    @endforeach
</section>
