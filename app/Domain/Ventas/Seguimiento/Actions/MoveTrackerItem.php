<?php

namespace App\Domain\Ventas\Seguimiento\Actions;

use App\Models\ActivityLog;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;
use Illuminate\Validation\ValidationException;

class MoveTrackerItem
{
    public function __construct(
        private OrderTrackerService $trackerService,
        private SalesWorkflowService $workflowService
    ) {
    }

    public function execute(int $id, string $type, string $newStatus, ?User $user = null): void
    {
        if ($type === 'quote') {
            $this->moveQuote($id, $newStatus, $user);
        } else {
            $this->moveOrder($id, $newStatus, $user);
        }
    }

    private function moveQuote(int $id, string $newStatus, ?User $user): void
    {
        $item = QuotationRequest::find($id);
        if (! $item) {
            return;
        }

        $quoteStatusMap = [
            'quote_pending'          => QuotationRequest::STATUS_RECEIVED,
            'quote_processing'       => QuotationRequest::STATUS_PENDING_BID,
            'quote_sent'             => QuotationRequest::STATUS_CLIENT_OFFER_SENT,
            'quote_approved'         => QuotationRequest::STATUS_APPROVED_TOTAL,
            'quote_corrections'      => QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED,
            'quote_pending_approval' => OrderTrackerService::QUOTE_PENDING_APPROVAL,
        ];

        if (! isset($quoteStatusMap[$newStatus])) {
            return;
        }

        if (in_array($newStatus, ['quote_pending_approval', 'quote_corrections'], true)) {
            throw ValidationException::withMessages(['status' => 'La aprobacion o correccion debe gestionarse desde su flujo especifico, no moviendo la tarjeta manualmente.']);
        }

        $this->workflowService->transition($item, $quoteStatusMap[$newStatus], $user);
    }

    private function moveOrder(int $id, string $newStatus, ?User $user): void
    {
        $item = SaleOrder::find($id);
        if (! $item) {
            return;
        }

        $orderStatusMap = [
            'order_purchased' => 'solicitado_proveedor',
            'order_transit' => 'en_transito_aduanas',
            'order_warehouse' => 'en_inventario_local',
            'order_delivery' => 'en_ruta',
            'order_delivered' => 'entregado',
        ];

        if (! isset($orderStatusMap[$newStatus])) {
            return;
        }

        $oldStatus = $item->status;
        $item->status = $orderStatusMap[$newStatus];
        $item->save();

        ActivityLog::log(
            'ventas',
            'update_order_status',
            "Cambio el estado del pedido {$item->reference} de {$oldStatus} a {$item->status}",
            ['order_id' => $item->id]
        );

        $tracker = $this->trackerService->trackerForOrder($item);
        if ($tracker) {
            $tracker->update(['operational_phase' => $newStatus, 'operational_status' => $item->status]);
            $this->trackerService->recordActivity(
                $tracker,
                'status_changed',
                "Cambio de estado en pedido {$item->reference}",
                'pedido_extranjero',
                'sale_orders',
                $item->id,
                $oldStatus,
                $item->status,
                $user
            );
        }
    }
}
