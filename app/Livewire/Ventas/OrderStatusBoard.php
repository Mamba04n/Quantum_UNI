<?php

namespace App\Livewire\Ventas;

use App\Models\OrderStatus;
use Livewire\Component;

class OrderStatusBoard extends Component
{
    public string $filterStatus = '';
    public string $search = '';

    public array $stages = [
        'oc_generada' => ['label' => 'OC generada', 'color' => 'bg-amber-50 text-amber-800 border-amber-200'],
        'esperando_confirmacion_proveedor' => ['label' => 'Enviada proveedor', 'color' => 'bg-sky-50 text-sky-800 border-sky-200'],
        'confirmado_por_proveedor' => ['label' => 'Confirmada', 'color' => 'bg-indigo-50 text-indigo-800 border-indigo-200'],
        'en_transito' => ['label' => 'En tránsito', 'color' => 'bg-blue-50 text-blue-800 border-blue-200'],
        'en_aduana' => ['label' => 'Aduana', 'color' => 'bg-violet-50 text-violet-800 border-violet-200'],
        'recibido_parcial' => ['label' => 'Recibido parcial', 'color' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
        'recibido_total' => ['label' => 'Recibido total', 'color' => 'bg-teal-50 text-teal-800 border-teal-200'],
        'entrega_parcial' => ['label' => 'Entrega parcial', 'color' => 'bg-orange-50 text-orange-800 border-orange-200'],
        'entregado_total' => ['label' => 'Entregado', 'color' => 'bg-slate-100 text-slate-800 border-slate-200'],
        'con_incidencias' => ['label' => 'Incidencias', 'color' => 'bg-rose-50 text-rose-800 border-rose-200'],
    ];

    public array $workflowGuide = [
        ['step' => '1', 'title' => 'OC al proveedor', 'detail' => 'Genera o adjunta la orden y márcala como enviada.'],
        ['step' => '2', 'title' => 'Confirmación', 'detail' => 'Registra referencia, contacto y fecha estimada.'],
        ['step' => '3', 'title' => 'Tránsito y aduana', 'detail' => 'Adjunta documentos de embarque y actualiza la aduana.'],
        ['step' => '4', 'title' => 'Bodega / Kardex', 'detail' => 'Recibe cantidades, evidencia y crea la entrada a inventario.'],
        ['step' => '5', 'title' => 'Despacho y entrega', 'detail' => 'Registra salida, documento de entrega y confirmación final.'],
    ];

    public function nextStep(OrderStatus $order): array
    {
        return match ($order->current_status ?: $order->administrative_status) {
            'oc_generada' => ['title' => 'Enviar OC al proveedor', 'detail' => 'Adjunta/genera la OC y confirma el envío al proveedor.', 'tone' => 'amber'],
            'esperando_confirmacion_proveedor' => ['title' => 'Registrar confirmación', 'detail' => 'Ingresa referencia, contacto y ETA del proveedor.', 'tone' => 'sky'],
            'confirmado_por_proveedor' => ['title' => 'Registrar despacho', 'detail' => 'Carga la confirmación o documento de embarque.', 'tone' => 'indigo'],
            'en_transito' => ['title' => 'Actualizar aduana', 'detail' => 'Registra llegada a aduana y sus documentos.', 'tone' => 'blue'],
            'en_aduana' => ['title' => 'Liberar de aduana', 'detail' => 'Confirma la liberación para enviarlo a Bodega.', 'tone' => 'violet'],
            'recibido_parcial', 'recibido_total' => ['title' => 'Registrar entrada Kardex', 'detail' => 'Recibe cantidades, evidencia y ubicación en Bodega.', 'tone' => 'emerald'],
            'entrega_parcial' => ['title' => 'Completar entrega', 'detail' => 'Registra la cantidad restante y su confirmación.', 'tone' => 'orange'],
            'entregado_total' => ['title' => 'Proceso completado', 'detail' => 'La orden ya fue entregada y documentada.', 'tone' => 'slate'],
            'con_incidencias' => ['title' => 'Resolver incidencia', 'detail' => 'Documenta el problema y define el siguiente responsable.', 'tone' => 'rose'],
            default => ['title' => 'Revisar estado operativo', 'detail' => 'Abre el seguimiento para identificar el dato o documento pendiente.', 'tone' => 'slate'],
        };
    }

    public function render()
    {
        $query = OrderStatus::with(['saleOrder.customer', 'purchaseOrder', 'items.supplier', 'documents', 'purchaseOrderDocuments', 'shipmentBatches'])
            ->withCount('items')
            // Una OC originada desde una cotización comercial se refleja en la
            // orden del cliente. Nunca debe aparecer como una segunda orden
            // independiente en el tablero, aun si quedó un seguimiento legado.
            ->whereDoesntHave('purchaseOrder', fn ($purchaseOrder) => $purchaseOrder
                ->whereNotNull('quotation_request_id'))
            ->latest();

        if ($this->filterStatus) {
            $query->where('administrative_status', $this->filterStatus);
        }

        if ($this->search) {
            $search = '%'.$this->search.'%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('saleOrder', fn ($orderQuery) => $orderQuery->where('reference', 'like', $search))
                    ->orWhereHas('tracker', fn ($trackerQuery) => $trackerQuery->where('tracking_code', 'like', $search))
                    ->orWhereHas('saleOrder', fn ($orderQuery) => $orderQuery->where('client_name', 'like', $search))
                    ->orWhereHas('items', fn ($itemQuery) => $itemQuery->where('part_number', 'like', $search)->orWhere('part_name', 'like', $search));
            });
        }

        $orders = $query->get();

        return view('livewire.ventas.order-status-board', [
            'orders' => $orders,
            'stats' => [
                'total' => $orders->count(),
                'products' => $orders->sum(fn ($order) => $order->items->count()),
                'received_products' => $orders->sum(fn ($order) => $order->items->filter(fn ($item) => (float) $item->quantity_received > 0)->count()),
                'ready_products' => $orders->sum(fn ($order) => $order->items->filter(fn ($item) => (float) $item->quantity_kardex_entry > (float) $item->quantity_kardex_output)->count()),
                'pending_send' => $orders->filter(fn ($order) => $order->purchaseOrderDocuments->contains(fn ($document) => $document->pdf_path && ! $document->sent_to_provider_at))->count(),
                'in_transit' => $orders->where('current_status', 'en_transito')->count(),
                'incidents' => $orders->where('current_status', 'con_incidencias')->count(),
            ],
        ]);
    }
}
