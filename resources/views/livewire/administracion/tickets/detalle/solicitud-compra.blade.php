<div class="rounded-xl bg-slate-50 border border-slate-100 p-4 text-sm">
    <p class="text-xs text-gray-400 font-bold mb-2">Solicitud de compra</p>
    <div class="grid grid-cols-2 gap-2">
        <p><span class="font-bold">Maquinaria:</span> {{ $selectedTicket->machinery ?: '-' }}</p>
        <p><span class="font-bold">Marca:</span> {{ $selectedTicket->machine_brand ?: '-' }}</p>
        <p><span class="font-bold">Modelo:</span> {{ $selectedTicket->machine_model ?: '-' }}</p>
        <p><span class="font-bold">Serie:</span> {{ $selectedTicket->equipment_serial ?: '-' }}</p>
        <p><span class="font-bold">Pieza:</span> {{ $selectedTicket->part_code ?: '-' }}</p>
        <p><span class="font-bold">Cantidad:</span> {{ $selectedTicket->request_quantity ?: 1 }}</p>
        <p><span class="font-bold">Urgencia:</span> {{ $priorityLabels[$selectedTicket->request_urgency] ?? $selectedTicket->request_urgency }}</p>
    </div>
</div>
