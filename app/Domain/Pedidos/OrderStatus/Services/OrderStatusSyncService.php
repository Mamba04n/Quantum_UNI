<?php

namespace App\Domain\Pedidos\OrderStatus\Services;

use App\Services\ApprovalService;
use App\Services\ClientOfferReviewService;
use App\Services\ClientOfferVersionService;
use App\Services\CompanyProfileService;
use App\Services\ConvertQuotationToSaleOrderService;
use App\Services\CustomerService;
use App\Services\ExchangeRateService;
use App\Services\FinanceDocumentService;
use App\Services\FinanceService;
use App\Services\InventoryReservationService;
use App\Services\InventoryService;
use App\Services\KpiService;
use App\Services\MachineComponentLifecycleService;
use App\Services\OrderStatusService;
use App\Services\OrderTrackerService;
use App\Services\PackingListService;
use App\Services\PdfService;
use App\Services\ProductSkuGenerator;
use App\Services\PurchaseOrderDocumentService;
use App\Services\PurchaseOrderProviderDocumentMapper;
use App\Services\PurchaseOrderService;
use App\Services\PurchasePreparationService;
use App\Services\PurchaseReceiptService;
use App\Services\RfqPdfDataMapper;
use App\Services\SaleOrderService;
use App\Services\SalesWorkflowService;
use App\Services\SupplierBidService;
use App\Services\SupplierRfqDocumentMapper;
use App\Services\SupplierRfqReviewService;
use App\Services\TicketService;
use App\Services\TrackerDocumentService;

use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use App\Models\User;

class OrderStatusSyncService
{
    public function __construct(private readonly OrderTrackerService $trackerService)
    {
    }

    public function syncTrackerFromOrderStatus(OrderStatus $orderStatus): void
    {
        $orderStatus->loadMissing('tracker');

        if (! $orderStatus->tracker) {
            return;
        }

        $orderStatus->tracker->forceFill([
            'operational_phase' => $orderStatus->administrative_phase,
            'operational_status' => $orderStatus->administrative_status,
            'main_reference' => $orderStatus->saleOrder?->reference ?: $orderStatus->tracker?->tracking_code,
        ])->save();
    }

    public function recordTrackerEvent(OrderStatus $orderStatus, string $eventType, array $data = [], ?User $user = null): void
    {
        $orderStatus->loadMissing('tracker');

        if (! $orderStatus->tracker) {
            return;
        }

        $this->trackerService->recordActivity(
            $orderStatus->tracker,
            $eventType,
            $data['description'] ?? str($eventType)->replace('.', ' ')->headline()->toString(),
            $data['phase'] ?? $orderStatus->administrative_phase ?? 'general',
            $data['source_module'] ?? 'order_statuses',
            $data['source_id'] ?? $orderStatus->id,
            $data['old_status'] ?? null,
            $data['new_status'] ?? $orderStatus->administrative_status,
            $user
        );
    }

    public function recalculateGeneralStatus(OrderStatus $orderStatus): OrderStatus
    {
        return app(OrderStatusService::class)->recalculate($orderStatus);
    }

    public function recalculateItemStatus(OrderStatusItem $item): string
    {
        if ((float) $item->quantity_delivered >= (float) $item->quantity_ordered) {
            return OrderStatusService::STATUS_DELIVERED;
        }

        if ((float) $item->quantity_dispatched > 0) {
            return OrderStatusService::STATUS_SENT_CUSTOMER;
        }

        if ((float) $item->quantity_kardex_output > 0) {
            return OrderStatusService::STATUS_KARDEX_OUTPUT_PARTIAL;
        }

        if ((float) $item->quantity_kardex_entry > 0) {
            return OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH;
        }

        if ((float) $item->quantity_received > 0) {
            return OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL;
        }

        return $item->status;
    }
}
