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
use App\Services\SaleOrderService;
use App\Services\SalesWorkflowService;
use App\Services\SupplierBidService;
use App\Services\SupplierRfqDocumentMapper;
use App\Services\SupplierRfqReviewService;
use App\Services\TicketService;
use App\Services\TrackerDocumentService;

use App\Models\QuotationRequestItem;

class RfqPdfDataMapper
{
    public function mapRfqItem(QuotationRequestItem $item): array
    {
        return [
            'id' => $item->id,
            'part_name' => $this->firstFilled([
                $item->nombre_pieza ?? null,
                $item->part_name ?? null,
                $item->product_name ?? null,
                data_get($item, 'product.name'),
                $item->description ?? null,
            ]),
            'reference' => $this->firstFilled([
                $item->part_number ?? null,
                $item->numero_parte ?? null,
                $item->reference ?? null,
                $item->referencia ?? null,
                $item->serial_number ?? null,
                $item->serie ?? null,
            ]),
            'brand' => $this->firstFilled([
                $item->brand ?? null,
                $item->marca ?? null,
                $item->name ?? null,
                data_get($item, 'product.brand'),
            ]),
            'quantity' => (int) ($item->quantity ?? $item->cantidad ?? 0),
            'model' => $this->firstFilled([
                $item->model ?? null,
                $item->modelo ?? null,
                $item->component_model ?? null,
                $item->machine_model ?? null,
                data_get($item, 'product.model'),
                data_get($item, 'machine.model'),
                data_get($item, 'quotationRequest.clientMachine.model'),
                data_get($item, 'quotationRequest.machine_model'),
            ]),
            'specifications' => $this->firstFilled([
                $item->rfq_item_specifications ?? null,
                $item->technical_specs ?? null,
                $item->specifications ?? null,
                $item->notes ?? null,
            ]),
        ];
    }

    public function mapItems(iterable $items): array
    {
        $mapped = [];

        foreach ($items as $item) {
            $mapped[] = $this->mapRfqItem($item);
        }

        return $mapped;
    }

    private function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return (string) $value;
            }
        }

        return '-';
    }
}
