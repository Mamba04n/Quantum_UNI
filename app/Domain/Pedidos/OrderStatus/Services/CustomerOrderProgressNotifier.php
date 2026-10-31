<?php

namespace App\Domain\Pedidos\OrderStatus\Services;

use App\Domain\Pedidos\OrderStatus\Support\OrderStatusStateCatalog;
use App\Models\OrderStatusEvent;
use App\Notifications\OrderPhaseUpdated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CustomerOrderProgressNotifier
{
    public function notifyEvent(int $eventId): void
    {
        $event = OrderStatusEvent::with([
            'orderStatus.saleOrder',
            'item',
        ])->find($eventId);

        if (! $event) {
            return;
        }

        // Aggregate events describe an internal batch. Customer progress is
        // always communicated for the exact line that changed.
        if (! $event->item) {
            $this->skip($event, 'skipped_aggregate');

            return;
        }

        if (! $event->new_status || $event->new_status === $event->old_status) {
            $this->skip($event, 'skipped_no_change');

            return;
        }

        $order = $event->orderStatus?->saleOrder;
        if (! $order?->client_email) {
            $this->skip($event, 'skipped_no_recipient');

            return;
        }

        // A single business transition can emit two internal events (for
        // example warehouse receipt and Kardex entry). Deduplicate those for
        // the same item, but never suppress another product in the order.
        $deduplicationKey = implode(':', [
            'customer-order-progress',
            $event->order_status_id,
            'item',
            $event->order_status_item_id,
            $event->phase,
            $event->new_status,
        ]);

        if (! Cache::add($deduplicationKey, $event->id, now()->addMinutes(10))) {
            $this->skip($event, 'skipped_duplicate');

            return;
        }

        $catalog = app(OrderStatusStateCatalog::class);
        $phaseLabel = $this->phaseLabel($event->phase);
        $statusLabel = $catalog->humanStatus($event->new_status);

        $event->forceFill([
            'customer_notification_status' => 'queued',
            'customer_notification_queued_at' => now(),
            'customer_notification_error' => null,
        ])->save();

        try {
            $order->notify(new OrderPhaseUpdated(
                $event->orderStatus,
                $event->item,
                $phaseLabel,
                $statusLabel,
                $this->customerMessage($event->event_type, $event->description),
                $event->id,
            ));
        } catch (\Throwable $exception) {
            Cache::forget($deduplicationKey);
            $event->forceFill([
                'customer_notification_status' => 'failed',
                'customer_notification_failed_at' => now(),
                'customer_notification_error' => mb_substr($exception->getMessage(), 0, 500),
            ])->save();

            Log::error('customer_order_notification.failed', [
                'order_status_event_id' => $event->id,
                'order_status_id' => $event->order_status_id,
                'order_status_item_id' => $event->order_status_item_id,
                'event_type' => $event->event_type,
                'exception' => $exception::class,
            ]);
        }
    }

    private function skip(OrderStatusEvent $event, string $status): void
    {
        $event->forceFill(['customer_notification_status' => $status])->save();
    }

    private function phaseLabel(string $phase): string
    {
        return match ($phase) {
            OrderStatusService::PHASE_PURCHASE => 'Compra al proveedor',
            OrderStatusService::PHASE_SUPPLIER_CONFIRMATION => 'Confirmación del proveedor',
            OrderStatusService::PHASE_TRANSIT => 'Tránsito internacional',
            OrderStatusService::PHASE_CUSTOMS => 'Aduana',
            OrderStatusService::PHASE_WAREHOUSE => 'Recepción en bodega',
            OrderStatusService::PHASE_KARDEX_ENTRY => 'Ingreso al inventario',
            OrderStatusService::PHASE_KARDEX_OUTPUT => 'Salida de bodega',
            OrderStatusService::PHASE_LOGISTICS => 'Despacho al cliente',
            OrderStatusService::PHASE_DELIVERY => 'Entrega',
            default => 'Actualización del pedido',
        };
    }

    private function customerMessage(string $eventType, string $description): string
    {
        return match ($eventType) {
            'item.cancelled_by_supplier' => 'El proveedor reportó una incidencia con esta partida. Nuestro equipo ya revisa las alternativas disponibles.',
            'item.replacement_requested' => 'Estamos gestionando un reemplazo para esta partida. Te avisaremos cuando tengamos la nueva disponibilidad.',
            'delivery.dispatch_registered' => 'Esta partida ya fue despachada y se encuentra en ruta de entrega.',
            'delivery.delivered_registered' => 'Registramos la entrega de esta partida. Gracias por elegir Quantum Soluciones.',
            default => 'Tenemos un avance en esta partida de tu pedido. Revisa la etapa y el estado actual a continuación.',
        };
    }
}
