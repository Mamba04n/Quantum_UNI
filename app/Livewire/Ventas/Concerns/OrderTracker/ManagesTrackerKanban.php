<?php

namespace App\Livewire\Ventas\Concerns\OrderTracker;

use App\Models\QuotationRequest;
use App\Services\OrderStatusService;
use App\Services\OrderTrackerService;

trait ManagesTrackerKanban
{
    public $stages = [
        'quote_pending'    => ['label' => '1. Solicitud Cliente',     'model' => 'quote', 'color' => 'bg-white border-slate-200 text-slate-700',  'header' => 'bg-slate-50'],
        'quote_processing' => ['label' => '2. Licitación Proveedor',  'model' => 'quote', 'color' => 'bg-white border-slate-200 text-slate-700',  'header' => 'bg-slate-50'],
        'quote_sent'       => ['label' => '3. Oferta Cliente',        'model' => 'quote', 'color' => 'bg-white border-slate-200 text-slate-700',  'header' => 'bg-slate-50'],
        'quote_approved'   => ['label' => '4. Aprobado',              'model' => 'quote', 'color' => 'bg-white border-slate-200 text-slate-700',  'header' => 'bg-slate-50'],
        'order_purchased'  => ['label' => '5. Pedido Extranjero',     'model' => 'order', 'color' => 'bg-white border-slate-200 text-slate-700',  'header' => 'bg-slate-50'],
        'order_transit'    => ['label' => '6. Tránsito / Aduanas',    'model' => 'order', 'color' => 'bg-white border-slate-200 text-slate-700',  'header' => 'bg-slate-50'],
        'order_warehouse'  => ['label' => '7. En Bodega Local',       'model' => 'order', 'color' => 'bg-white border-slate-200 text-slate-700',  'header' => 'bg-slate-50'],
        'order_delivery'   => ['label' => '8. Facturación / Salida',  'model' => 'order', 'color' => 'bg-white border-slate-200 text-slate-700',  'header' => 'bg-slate-50'],
        'order_delivered'  => ['label' => '9. Entregado',             'model' => 'order', 'color' => 'bg-white border-emerald-200 text-emerald-700', 'header' => 'bg-emerald-50'],
        'order_cancelled'  => ['label' => '10. Anulados / Cancelados', 'model' => 'both',  'color' => 'bg-white border-rose-200 text-rose-700',    'header' => 'bg-rose-50'],
    ];

    public function mapOrderStatusItemToStage(string $status): string
    {
        return match ($status) {
            OrderStatusService::STATUS_IN_TRANSIT,
            OrderStatusService::STATUS_IN_CUSTOMS,
            OrderStatusService::STATUS_CUSTOMS_RELEASED,
            'en_transito_aduanas',
            'in_transit',
            'in_customs' => 'order_transit',

            OrderStatusService::STATUS_RECEIVED_WAREHOUSE,
            OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL,
            OrderStatusService::STATUS_KARDEX_ENTRY,
            OrderStatusService::STATUS_KARDEX_ENTRY_PARTIAL,
            OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH,
            OrderStatusService::STATUS_HELD_BY_ADMIN,
            OrderStatusService::STATUS_WAITING_OTHER_ITEMS,
            'en_inventario_local',
            'in_warehouse',
            'received' => 'order_warehouse',

            OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED,
            OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH,
            OrderStatusService::STATUS_SENT_CUSTOMER,
            'dispatched',
            'en_ruta',
            'in_delivery' => 'order_delivery',

            OrderStatusService::STATUS_KARDEX_OUTPUT,
            OrderStatusService::STATUS_KARDEX_OUTPUT_PARTIAL,
            OrderStatusService::STATUS_DELIVERED,
            OrderStatusService::STATUS_DELIVERED_PARTIAL,
            OrderStatusService::STATUS_CLOSED,
            'entregado',
            'completed',
            'closed',
            'finalizado',
            'delivered' => 'order_delivered',

            OrderStatusService::STATUS_CANCELLED_SUPPLIER,
            'cancelado',
            'anulado',
            'cancelled',
            'rejected',
            'rechazado' => 'order_cancelled',

            default => 'order_purchased',
        };
    }

    public function itemProgressPercent($item): int
    {
        $ordered = max(1, (float) $item->quantity_ordered);
        $delivered = min($ordered, (float) $item->quantity_delivered);
        $base = match ($this->mapOrderStatusItemToStage($item->status)) {
            'order_purchased' => 20,
            'order_transit' => 45,
            'order_warehouse' => 70,
            'order_delivery' => 86,
            'order_delivered' => 100,
            'order_cancelled' => 0,
            default => 10,
        };

        if ($delivered > 0 && $delivered < $ordered) {
            return max($base, (int) round(($delivered / $ordered) * 100));
        }

        return $base;
    }

    public function getKanbanBoard($quotes, $orders)
    {
        $board = [];
        foreach ($this->stages as $stage => $data) {
            $board[$stage] = [];
        }

        foreach ($quotes as $q) {
            if ($q->sale_order_id && $orders->contains('id', $q->sale_order_id)) {
                continue;
            }

            $status = (string) ($q->status ?: QuotationRequest::STATUS_RECEIVED);
            if (in_array($status, ['cancelado', 'rechazado', 'cancelled', 'rejected'], true)) {
                $board['order_cancelled'][] = ['id' => $q->id, 'type' => 'quote', 'data' => $q];
                continue;
            }

            if (in_array($status, ['pedido_colocado', 'convertida'], true)) {
                continue;
            }

            $mapped = match (true) {
                in_array($status, [QuotationRequest::STATUS_RECEIVED, QuotationRequest::STATUS_IN_REVIEW, 'recibida']) => 'quote_pending',
                in_array($status, [QuotationRequest::STATUS_READY_FOR_BID, QuotationRequest::STATUS_PENDING_BID, QuotationRequest::STATUS_RFQ_GENERATED, QuotationRequest::STATUS_SENT_TO_SUPPLIER, QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING, QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED, QuotationRequest::STATUS_SUPPLIER_SELECTED, 'pendiente_licitacion', 'rfq_generada', 'proveedor_respuesta_pendiente', 'respuesta_recibida']) => 'quote_processing',
                in_array($status, [QuotationRequest::STATUS_CLIENT_OFFER_GENERATED, QuotationRequest::STATUS_CLIENT_OFFER_SENT, QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED, QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED, QuotationRequest::STATUS_REQUIRES_ADJUSTMENT, QuotationRequest::STATUS_CLIENT_REQUIRES_ADJUSTMENT, QuotationRequest::STATUS_REVISED_AFTER_CUSTOMER_CHANGE, 'oferta_generada', 'final_pdf_generated']) => 'quote_sent',
                in_array($status, [QuotationRequest::STATUS_APPROVED_TOTAL, QuotationRequest::STATUS_APPROVED_PARTIAL, OrderTrackerService::QUOTE_PENDING_APPROVAL, 'aprobada_total', 'closed']) => 'quote_approved',
                default => 'quote_pending',
            };
            if (isset($board[$mapped])) {
                $board[$mapped][] = ['id' => $q->id, 'type' => 'quote', 'data' => $q];
            }
        }

        foreach ($orders as $o) {
            $status = (string) $o->status;

            if (in_array($status, ['cancelado', 'anulado', 'cancelled', 'rejected', 'rechazado'], true)) {
                $board['order_cancelled'][] = ['id' => $o->id, 'type' => 'order', 'data' => $o, 'progress' => 0];
                continue;
            }

            if ($o->orderStatus && $o->orderStatus->items->isNotEmpty()) {
                foreach ($o->orderStatus->items as $orderItem) {
                    $mapped = $this->mapOrderStatusItemToStage($orderItem->status);
                    if (isset($board[$mapped])) {
                        $board[$mapped][] = [
                            'id' => $o->id,
                            'type' => 'order',
                            'data' => $o,
                            'order_status_item' => $orderItem,
                            'progress' => $this->itemProgressPercent($orderItem),
                        ];
                    }
                }

                continue;
            }

            $mapped = match ($status) {
                'cotizacion' => 'quote_approved',
                'solicitado_proveedor', 'purchase_order_generated', 'generated', 'pending_purchase', 'processing', 'confirmed' => 'order_purchased',
                'en_transito_aduanas', 'in_transit', 'customs' => 'order_transit',
                'en_inventario_local', 'in_warehouse', 'received' => 'order_warehouse',
                'en_ruta', 'dispatched', 'in_delivery' => 'order_delivery',
                'entregado', 'completed', 'closed', 'finalizado', 'delivered' => 'order_delivered',
                'cancelado', 'anulado', 'cancelled', 'rejected', 'rechazado' => 'order_cancelled',
                default => 'order_purchased',
            };
            if (isset($board[$mapped])) {
                $board[$mapped][] = ['id' => $o->id, 'type' => 'order', 'data' => $o, 'progress' => $mapped === 'order_delivered' ? 100 : 0];
            }
        }

        return $board;
    }
}
