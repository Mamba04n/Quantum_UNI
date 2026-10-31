@php
    $quote = $selectedReview->quotationRequest;
    $supplier = $selectedReview->supplier;
@endphp

<x-order-context :quotation="$quote" class="mb-6" />
<div class="erp-open-panel">
    <header class="border-b border-slate-200/80 pb-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <span class="erp-chip-dark">Revision RFQ</span>
                <h3 class="mt-3 text-2xl font-black text-slate-900">{{ $quote->reference }}</h3>
                <p class="mt-1 text-sm font-bold text-slate-500">{{ $quote->quote_to_name }} - proveedor destino {{ $supplier?->name ?? 'Proveedor' }}</p>
            </div>
            <div class="grid grid-cols-3 gap-5 text-center text-xs">
                <div><p class="font-black text-slate-400">Partidas</p><strong>{{ $quote->items->count() }}</strong></div>
                <div><p class="font-black text-slate-400">Estado</p><strong>{{ $selectedReview->status_label }}</strong></div>
                <div><p class="font-black text-slate-400">Fecha</p><strong>{{ optional($selectedReview->updated_at)->format('d/m/Y') }}</strong></div>
            </div>
        </div>
    </header>

    <div class="space-y-8 py-6">
        <section class="erp-section">
            <h4 class="text-xs font-black uppercase tracking-widest text-slate-500">Datos solicitud cliente</h4>
            <div class="mt-3 grid grid-cols-1 gap-3 text-xs md:grid-cols-4">
                <div><span class="block font-black text-slate-400">Cliente</span><strong>{{ $quote->quote_to_name }}</strong></div>
                <div><span class="block font-black text-slate-400">Responsable</span><strong>{{ $quote->user?->name ?? 'Sistema' }}</strong></div>
                <div><span class="block font-black text-slate-400">Maquinaria</span><strong>{{ $quote->clientMachine?->name ?? '-' }}</strong></div>
                <div><span class="block font-black text-slate-400">Observaciones</span><strong>{{ str($quote->notes ?: '-')->limit(60) }}</strong></div>
            </div>
        </section>

        <section class="erp-table-wrap">
            <table class="w-full text-left text-[11px]">
                <thead class="bg-slate-50 text-[9px] font-black uppercase tracking-widest text-slate-500">
                    <tr><th class="px-4 py-3">Producto solicitado</th><th class="px-4 py-3">Marca</th><th class="px-4 py-3">Modelo</th><th class="px-4 py-3 text-center">Cantidad</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach($quote->items as $item)
                        <tr>
                            <td class="px-4 py-3"><p class="font-black text-slate-900">{{ $item->part_number ?: 'N/D' }}</p><p class="text-slate-500">{{ $item->part_name ?: $item->description }}</p></td>
                            <td class="px-4 py-3">{{ $item->brand ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->model ?: '-' }}</td>
                            <td class="px-4 py-3 text-center font-black">{{ number_format((float) $item->quantity, 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="erp-section">
            <h4 class="text-xs font-black uppercase tracking-widest text-slate-500">Datos proveedor y RFQ</h4>
            <div class="mt-3 grid grid-cols-1 gap-3 text-xs md:grid-cols-3">
                <div><span class="block font-black text-slate-400">Proveedor destino</span><strong>{{ $supplier?->name ?? '-' }}</strong></div>
                <div><span class="block font-black text-slate-400">Email</span><strong>{{ $supplier?->email ?? '-' }}</strong></div>
                <div><span class="block font-black text-slate-400">Telefono</span><strong>{{ $supplier?->phone_mobile ?? $supplier?->phone_home ?? '-' }}</strong></div>
                <div><span class="block font-black text-slate-400">Cotizar a nombre de</span><strong>{{ $selectedReview->quote_to_name }}</strong></div>
                <div><span class="block font-black text-slate-400">Consignatario</span><strong>{{ $selectedReview->consignee_name }}</strong></div>
                <div><span class="block font-black text-slate-400">Correos</span><strong>{{ $selectedReview->consignee_emails }}</strong></div>
                <div class="md:col-span-3"><span class="block font-black text-slate-400">Direccion</span><strong>{{ $selectedReview->consignee_address_1 }} {{ $selectedReview->consignee_address_2 }} - {{ $selectedReview->consignee_city }}, {{ $selectedReview->consignee_country }}</strong></div>
                <div class="md:col-span-3"><span class="block font-black text-slate-400">Comentario urgente</span><strong>{{ $selectedReview->consignee_urgent_comment ?: '-' }}</strong></div>
            </div>
        </section>

        <section class="erp-section">
            <textarea wire:model="reviewReason" rows="3" class="w-full rounded-xl border-slate-200 text-xs focus:border-slate-800 focus:ring-slate-500" placeholder="Motivo obligatorio para rechazar o solicitar cambios. Opcional al aprobar."></textarea>
            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <button type="button" wire:click="requestChanges" class="rounded-xl border border-amber-200 bg-white px-4 py-2.5 text-xs font-black text-amber-700 shadow-sm hover:bg-amber-50">Solicitar cambios</button>
                <button type="button" wire:click="reject" class="rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-xs font-black text-rose-700 shadow-sm hover:bg-rose-50">Rechazar RFQ</button>
                <button type="button" wire:click="approve" class="erp-button-primary">Aprobar RFQ para proveedor</button>
            </div>
        </section>
    </div>
</div>
