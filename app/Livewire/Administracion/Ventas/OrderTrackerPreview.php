<?php

namespace App\Livewire\Administracion\Ventas;

use App\Models\OrderStatusItem;
use App\Models\SaleOrder;
use App\Services\OrderStatusService;
use Illuminate\Support\Collection;
use Livewire\Component;

class OrderTrackerPreview extends Component
{
    public int $orderId;

    public function mount(int $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function render()
    {
        $order = SaleOrder::with(['items', 'orderStatus.items'])->findOrFail($this->orderId);
        $statusItems = $order->orderStatus?->items ?? collect();

        return view('livewire.administracion.ventas.order-tracker-preview', [
            'order' => $order,
            'items' => $order->items,
            'shipments' => collect(),
            'steps' => [
                ['label' => 'Pedido Recibido', 'completed' => true, 'date' => $order->created_at],
                ['label' => 'Procesando Pago', 'completed' => $this->paymentStepCompleted($order, $statusItems), 'date' => $order->updated_at],
                ['label' => 'En Preparacion', 'completed' => $this->preparationStepCompleted($statusItems), 'date' => $this->firstDate($statusItems, 'warehouse_received_date')],
                ['label' => 'Enviado / En Transito', 'completed' => $this->shippingStepCompleted($statusItems), 'date' => $this->firstDate($statusItems, 'delivery_dispatch_date') ?? $this->firstDate($statusItems, 'actual_supplier_dispatch_date')],
                ['label' => 'Entregado', 'completed' => $this->deliveryStepCompleted($order, $statusItems), 'date' => $this->firstDate($statusItems, 'delivered_at') ?? ($this->orderIsCompleted($order) ? $order->updated_at : null)],
            ],
        ]);
    }

    private function paymentStepCompleted(SaleOrder $order, Collection $items): bool
    {
        return $this->orderIsCompleted($order)
            || in_array($order->status, ['processing', 'procesando', 'pago_confirmado', 'en_ruta', 'entregado'], true)
            || $items->contains(fn (OrderStatusItem $item) => ! in_array($item->status, [
                OrderStatusService::STATUS_PENDING_ORDER,
                OrderStatusService::STATUS_ORDER_GENERATED,
                OrderStatusService::STATUS_ORDER_SENT,
                OrderStatusService::STATUS_PENDING_PO,
                OrderStatusService::STATUS_PO_GENERATED,
            ], true));
    }

    private function preparationStepCompleted(Collection $items): bool
    {
        return $items->contains(fn (OrderStatusItem $item) => (float) $item->quantity_received > 0
            || (float) $item->quantity_kardex_entry > 0
            || in_array($item->status, [
                OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL,
                OrderStatusService::STATUS_RECEIVED_WAREHOUSE,
                OrderStatusService::STATUS_KARDEX_ENTRY_PARTIAL,
                OrderStatusService::STATUS_KARDEX_ENTRY,
                OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH,
                OrderStatusService::STATUS_KARDEX_OUTPUT_PARTIAL,
                OrderStatusService::STATUS_KARDEX_OUTPUT,
                OrderStatusService::STATUS_SENT_CUSTOMER,
                OrderStatusService::STATUS_DELIVERED_PARTIAL,
                OrderStatusService::STATUS_DELIVERED,
                OrderStatusService::STATUS_CLOSED,
            ], true));
    }

    private function shippingStepCompleted(Collection $items): bool
    {
        return $items->contains(fn (OrderStatusItem $item) => (float) $item->quantity_dispatched > 0
            || (float) $item->quantity_shipped > 0
            || in_array($item->status, [
                OrderStatusService::STATUS_IN_TRANSIT,
                OrderStatusService::STATUS_IN_CUSTOMS,
                OrderStatusService::STATUS_SENT_CUSTOMER,
                OrderStatusService::STATUS_DELIVERED_PARTIAL,
                OrderStatusService::STATUS_DELIVERED,
                OrderStatusService::STATUS_CLOSED,
            ], true));
    }

    private function deliveryStepCompleted(SaleOrder $order, Collection $items): bool
    {
        if ($this->orderIsCompleted($order)) {
            return true;
        }

        $activeItems = $items->reject(fn (OrderStatusItem $item) => in_array($item->status, [
            OrderStatusService::STATUS_CANCELLED_SUPPLIER,
            OrderStatusService::STATUS_REPLACEMENT_REQUIRED,
        ], true));

        return $activeItems->isNotEmpty()
            && $activeItems->every(fn (OrderStatusItem $item) => (float) $item->quantity_delivered >= (float) $item->quantity_ordered
                || in_array($item->status, [OrderStatusService::STATUS_DELIVERED, OrderStatusService::STATUS_CLOSED], true));
    }

    private function firstDate(Collection $items, string $field)
    {
        return $items
            ->pluck($field)
            ->filter()
            ->sort()
            ->first();
    }

    private function orderIsCompleted(SaleOrder $order): bool
    {
        return in_array($order->status, ['completed', 'entregado', 'entregado_total', 'cerrado'], true);
    }
}
