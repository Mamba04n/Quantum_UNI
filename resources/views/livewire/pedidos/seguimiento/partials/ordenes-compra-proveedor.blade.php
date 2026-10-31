@if($purchaseOrderDocuments->isNotEmpty())
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" data-testid="supplier-purchase-orders">
        <div class="flex flex-col gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-black text-slate-950">Órdenes de compra al proveedor</h2>
                <p class="mt-1 text-xs font-bold text-slate-500">PDF generados automáticamente, disponibles para consultar, descargar y enviar.</p>
            </div>
            <span class="w-fit rounded-full bg-slate-200 px-3 py-1 text-[10px] font-black uppercase text-slate-700">
                {{ $purchaseOrderDocuments->count() }} {{ $purchaseOrderDocuments->count() === 1 ? 'documento' : 'documentos' }}
            </span>
        </div>

        <div class="divide-y divide-slate-100">
            @foreach($purchaseOrderDocuments as $purchaseDocument)
                <div class="flex flex-col gap-4 px-5 py-4 xl:flex-row xl:items-center xl:justify-between" wire:key="supplier-purchase-order-{{ $purchaseDocument['id'] }}">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-black text-slate-950">{{ $purchaseDocument['code'] }}</p>
                            @if($purchaseDocument['sent_at'])
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[9px] font-black uppercase text-emerald-700">Enviada</span>
                            @else
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[9px] font-black uppercase text-amber-700">Pendiente de envío</span>
                            @endif
                        </div>
                        <p class="mt-1 truncate text-xs font-bold text-slate-600">Proveedor: {{ $purchaseDocument['supplier'] }}</p>
                        <p class="mt-1 text-[10px] font-semibold text-slate-400">
                            @if($purchaseDocument['sent_at'])
                                Enviada {{ $purchaseDocument['sent_at']->format('d/m/Y H:i') }}{{ $purchaseDocument['sender'] ? ' por '.$purchaseDocument['sender'] : '' }}
                            @elseif($purchaseDocument['generated_at'])
                                Generada {{ $purchaseDocument['generated_at']->format('d/m/Y H:i') }}
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if($purchaseDocument['view_url'])
                            <a href="{{ $purchaseDocument['view_url'] }}" target="_blank" rel="noopener" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-black uppercase text-slate-700 hover:bg-slate-50">Ver PDF</a>
                            <a href="{{ $purchaseDocument['download_url'] }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-black uppercase text-slate-700 hover:bg-slate-50">Descargar</a>
                        @endif
                        @if(!$purchaseDocument['sent_at'] && (auth()->user()?->can('order_status.manage') || auth()->user()?->can('purchase_order_provider.edit') || auth()->user()?->can('purchase_order_document.edit')))
                            <button type="button" wire:click="markPurchaseOrderSent({{ $purchaseDocument['id'] }})" wire:loading.attr="disabled" wire:target="markPurchaseOrderSent({{ $purchaseDocument['id'] }})" class="rounded-lg bg-slate-950 px-3 py-2 text-[10px] font-black uppercase text-white hover:bg-slate-800 disabled:opacity-60">
                                Registrar como enviada
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
