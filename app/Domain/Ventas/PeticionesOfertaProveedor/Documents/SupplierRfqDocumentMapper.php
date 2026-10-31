<?php

namespace App\Domain\Ventas\PeticionesOfertaProveedor\Documents;

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
use App\Services\OrderStatusSyncService;
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
use App\Services\SupplierRfqReviewService;
use App\Services\TicketService;
use App\Services\TrackerDocumentService;

use App\Models\QuotationRequest;
use App\Models\SupplierRfqDocument;

class SupplierRfqDocumentMapper
{
    public function __construct(private readonly RfqPdfDataMapper $itemsMapper)
    {
    }

    public function map(QuotationRequest $request): array
    {
        $request->loadMissing(['items.product', 'items.machine', 'clientMachine', 'user']);

        return [
            'rfq_id' => 'RFQ-'.$request->reference,
            'reference' => $request->reference,
            'date' => now()->format('d/m/Y'),
            'requester' => $request->user?->name,
            'response_requirements' => SupplierRfqDocument::DEFAULT_RESPONSE_REQUIREMENTS,
            'items' => $this->itemsMapper->mapItems($request->items),
        ];
    }
}
