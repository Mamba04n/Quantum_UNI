<?php

namespace App\Domain\Ventas\FlujoTrabajo\Services;

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
use App\Services\SupplierBidService;
use App\Services\SupplierRfqDocumentMapper;
use App\Services\SupplierRfqReviewService;
use App\Services\TicketService;
use App\Services\TrackerDocumentService;

use App\Models\ActivityLog;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\QuotationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesWorkflowService
{
    public const PHASE_REQUEST = 'customer_request';
    public const PHASE_SUPPLIER_BID = 'supplier_bid';
    public const PHASE_CLIENT_OFFER = 'client_offer';
    public const PHASE_APPROVAL = 'approval';
    public const PHASE_DIRECT_ORDER = 'direct_order';

    public function __construct(private readonly OrderTrackerService $trackerService)
    {
    }

    public function reviewAndRouteRequest(QuotationRequest $request, User $user): QuotationRequest
    {
        return app(\App\Domain\Ventas\FlujoTrabajo\Actions\ReviewAndRouteRequest::class)->execute($this, $request, $user);
    }

    public function transition(QuotationRequest $request, string $newStatus, User $user, bool $system = false): QuotationRequest
    {
        return app(\App\Domain\Ventas\FlujoTrabajo\Actions\TransitionQuotationStatus::class)->execute($this, $request, $newStatus, $user, $system);
    }

    public function approveAll(QuotationRequest $request, User $user): QuotationRequest
    {
        return app(\App\Domain\Ventas\FlujoTrabajo\Actions\ApproveQuotation::class)->execute($this, $request, $user);
    }

    public function manualStatusOptions(QuotationRequest $request): array
    {
        $status = $request->status ?: QuotationRequest::STATUS_RECEIVED;

        return collect($this->systemAllowedStatuses($status))
            ->mapWithKeys(fn ($allowed) => [$allowed => $this->labelForStatus($allowed)])
            ->all();
    }

    public function phaseForStatus(?string $status): string
    {
        return match ($status) {
            QuotationRequest::STATUS_RECEIVED,
            QuotationRequest::STATUS_IN_REVIEW,
            QuotationRequest::STATUS_CANCELLED => self::PHASE_REQUEST,

            QuotationRequest::STATUS_READY_FOR_BID,
            QuotationRequest::STATUS_PENDING_BID,
            QuotationRequest::STATUS_RFQ_GENERATED,
            QuotationRequest::STATUS_SENT_TO_SUPPLIER,
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            QuotationRequest::STATUS_SUPPLIER_SELECTED => self::PHASE_SUPPLIER_BID,

            QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            QuotationRequest::STATUS_CLIENT_OFFER_SENT,
            QuotationRequest::STATUS_OFFER_DRAFT,
            QuotationRequest::STATUS_OFFER_PREVIEW_READY,
            QuotationRequest::STATUS_PENDING_ADMIN_REVIEW,
            QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED,
            QuotationRequest::STATUS_ADMIN_REJECTED,
            QuotationRequest::STATUS_APPROVED_TO_SEND,
            QuotationRequest::STATUS_FINAL_PDF_GENERATED,
            QuotationRequest::STATUS_REVISED_AFTER_CUSTOMER_CHANGE => self::PHASE_CLIENT_OFFER,

            QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
            QuotationRequest::STATUS_SENT_TO_CUSTOMER,
            QuotationRequest::STATUS_CUSTOMER_ACCEPTED_TOTAL,
            QuotationRequest::STATUS_CUSTOMER_ACCEPTED_PARTIAL,
            QuotationRequest::STATUS_CUSTOMER_REJECTED,
            QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED,
            QuotationRequest::STATUS_CLIENT_APPROVED_TOTAL,
            QuotationRequest::STATUS_CLIENT_APPROVED_PARTIAL,
            QuotationRequest::STATUS_CLIENT_REJECTED,
            QuotationRequest::STATUS_CLIENT_REQUIRES_ADJUSTMENT,
            QuotationRequest::STATUS_APPROVED_TOTAL,
            QuotationRequest::STATUS_APPROVED_PARTIAL,
            QuotationRequest::STATUS_REJECTED,
            QuotationRequest::STATUS_REQUIRES_ADJUSTMENT,
            OrderTrackerService::QUOTE_PENDING_APPROVAL => self::PHASE_APPROVAL,

            default => self::PHASE_REQUEST,
        };
    }

    public function hasValidSupplierBid(QuotationRequest $request): bool
    {
        $request->loadMissing('items.product', 'supplierBidSelections.responseItem.response');

        return $request->supplierBidSelections->isNotEmpty()
            || $request->items->contains(fn ($item) => $item->selected_supplier_id && (float) ($item->unit_cost ?? 0) > 0);
    }

    public function assertReadyForClientOffer(QuotationRequest $request): void
    {
        $request->loadMissing('items');

        if ($request->items->isEmpty()) {
            $this->deny('No se puede pasar a Oferta Cliente sin partidas registradas.');
        }
    }

    public function hasAvailableInventory(QuotationRequest $request): bool
    {
        if ($request->items->isEmpty()) {
            return false;
        }

        return $request->items->every(function ($item) use ($request) {
            $product = $item->product_id ? Product::find($item->product_id) : null;

            if (! $product && filled($item->part_number)) {
                $product = Product::query()
                    ->where('sku', $item->part_number)
                    ->orWhere('part_number', $item->part_number)
                    ->first();
            }

            if (!$product) {
                return false;
            }

            $reserved = InventoryReservation::where('product_id', $product->id)
                ->where('status', 'reserved')
                ->where(function ($query) use ($request) {
                    $query->whereNull('quotation_request_id')->orWhere('quotation_request_id', '!=', $request->id);
                })
                ->sum('quantity');

            return ((float) $product->stock_on_hand - (float) $reserved) >= (float) $item->quantity;
        });
    }

    public function labelForStatus(string $status): string
    {
        return str($status)->replace('_', ' ')->headline()->toString();
    }

    public function validateTransitionRules(QuotationRequest $request, string $newStatus): void
    {
        // Bloqueo: no licitar si la solicitud no fue revisada primero
        if ($newStatus === QuotationRequest::STATUS_PENDING_BID
            && !in_array($request->status, [
                QuotationRequest::STATUS_IN_REVIEW,
                QuotationRequest::STATUS_READY_FOR_BID,
                QuotationRequest::STATUS_RECEIVED,
            ], true)) {
            $this->deny('No se puede enviar a licitacion: la solicitud debe estar en revision o lista para licitar.');
        }

        if (in_array($newStatus, [
            QuotationRequest::STATUS_PENDING_BID,
            QuotationRequest::STATUS_RFQ_GENERATED,
            QuotationRequest::STATUS_SENT_TO_SUPPLIER,
        ], true)) {
            $this->assertRequestItemsAreNormalized($request);
        }

        // Bloqueo: validar que existan partidas para oferta
        if (in_array($newStatus, [QuotationRequest::STATUS_CLIENT_OFFER_GENERATED, QuotationRequest::STATUS_CLIENT_OFFER_SENT, QuotationRequest::STATUS_APPROVED_TO_SEND], true)) {
            $this->assertReadyForClientOffer($request);
        }

        // Bloqueo: no avanzar a respuesta del cliente sin PDF de oferta generado
        if (in_array($newStatus, [QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING, QuotationRequest::STATUS_SENT_TO_CUSTOMER], true)) {
            $hasOffer = filled($request->client_offer_pdf_path) || $request->offerVersions()->exists();
            if (! $hasOffer) {
                $this->deny('No se puede avanzar a respuesta del cliente sin haber generado la Oferta Cliente (PDF).');
            }
        }

        // Bloqueo: no aprobar sin PDF de oferta
        if ($newStatus === QuotationRequest::STATUS_APPROVED_TOTAL && !$request->client_offer_pdf_path) {
            $this->deny('No se puede aprobar sin PDF de oferta cliente generado.');
        }

        if ($newStatus === OrderTrackerService::QUOTE_PENDING_APPROVAL) {
            if (!$request->client_offer_pdf_path) {
                $this->deny('No se puede enviar a aprobacion sin PDF de oferta cliente.');
            }

            if ($request->items->isEmpty()) {
                $this->deny('No se puede enviar a aprobacion sin partidas.');
            }

            if ($request->items->contains(fn ($item) => (float) ($item->unit_price ?? 0) <= 0)) {
                $this->deny('No se puede enviar a aprobacion: existen partidas sin precio final.');
            }

            if (blank($request->quote_to_name)) {
                $this->deny('No se puede enviar a aprobacion sin cliente.');
            }
        }
    }

    public function systemAllowedStatuses(string $status): array
    {
        return match ($status) {
            // Fase 1 → Licitación
            QuotationRequest::STATUS_RECEIVED => [
                QuotationRequest::STATUS_IN_REVIEW,
                QuotationRequest::STATUS_READY_FOR_BID,
                QuotationRequest::STATUS_PENDING_BID,
                QuotationRequest::STATUS_CANCELLED,
            ],
            QuotationRequest::STATUS_IN_REVIEW => [
                QuotationRequest::STATUS_READY_FOR_BID,
                QuotationRequest::STATUS_PENDING_BID,
                QuotationRequest::STATUS_CANCELLED,
            ],
            QuotationRequest::STATUS_READY_FOR_BID => [
                QuotationRequest::STATUS_PENDING_BID,
                QuotationRequest::STATUS_CANCELLED,
            ],
            // Fase 2: Licitación Proveedor
            QuotationRequest::STATUS_PENDING_BID => [
                QuotationRequest::STATUS_RFQ_GENERATED,
                QuotationRequest::STATUS_SENT_TO_SUPPLIER,
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            ],
            QuotationRequest::STATUS_RFQ_GENERATED => [
                QuotationRequest::STATUS_SENT_TO_SUPPLIER,
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            ],
            QuotationRequest::STATUS_SENT_TO_SUPPLIER => [
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            ],
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING => [
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
                QuotationRequest::STATUS_APPROVED_TO_SEND,
                QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            ],
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED => [
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
                QuotationRequest::STATUS_SUPPLIER_SELECTED,
                QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
                QuotationRequest::STATUS_APPROVED_TO_SEND,
                QuotationRequest::STATUS_OFFER_DRAFT,
                QuotationRequest::STATUS_FINAL_PDF_GENERATED,
                QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
            ],
            QuotationRequest::STATUS_SUPPLIER_SELECTED => [
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
                QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
                QuotationRequest::STATUS_APPROVED_TO_SEND,
                QuotationRequest::STATUS_OFFER_DRAFT,
                QuotationRequest::STATUS_FINAL_PDF_GENERATED,
                QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
            ],
            // Fase 3: Oferta Cliente
            QuotationRequest::STATUS_CLIENT_OFFER_GENERATED => [
                QuotationRequest::STATUS_OFFER_PREVIEW_READY,
                QuotationRequest::STATUS_PENDING_ADMIN_REVIEW,
                QuotationRequest::STATUS_APPROVED_TO_SEND,
                QuotationRequest::STATUS_FINAL_PDF_GENERATED,
                QuotationRequest::STATUS_CLIENT_OFFER_SENT,
                QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
                OrderTrackerService::QUOTE_PENDING_APPROVAL,
                QuotationRequest::STATUS_APPROVED_TOTAL,
                QuotationRequest::STATUS_REQUIRES_ADJUSTMENT,
            ],
            QuotationRequest::STATUS_OFFER_DRAFT,
            QuotationRequest::STATUS_OFFER_PREVIEW_READY,
            QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED,
            QuotationRequest::STATUS_REVISED_AFTER_CUSTOMER_CHANGE => [
                QuotationRequest::STATUS_PENDING_ADMIN_REVIEW,
                QuotationRequest::STATUS_APPROVED_TO_SEND,
                QuotationRequest::STATUS_FINAL_PDF_GENERATED,
                QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
            ],
            QuotationRequest::STATUS_PENDING_ADMIN_REVIEW => [
                QuotationRequest::STATUS_APPROVED_TO_SEND,
                QuotationRequest::STATUS_FINAL_PDF_GENERATED,
                QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
                QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED,
                QuotationRequest::STATUS_ADMIN_REJECTED,
            ],
            QuotationRequest::STATUS_APPROVED_TO_SEND => [
                QuotationRequest::STATUS_FINAL_PDF_GENERATED,
                QuotationRequest::STATUS_CLIENT_OFFER_SENT,
                QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
            ],
            QuotationRequest::STATUS_FINAL_PDF_GENERATED => [
                QuotationRequest::STATUS_CLIENT_OFFER_SENT,
                QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
                QuotationRequest::STATUS_CLIENT_APPROVED_TOTAL,
                QuotationRequest::STATUS_APPROVED_TOTAL,
            ],
            QuotationRequest::STATUS_CLIENT_OFFER_SENT => [
                QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
                OrderTrackerService::QUOTE_PENDING_APPROVAL,
                QuotationRequest::STATUS_APPROVED_TOTAL,
                QuotationRequest::STATUS_APPROVED_PARTIAL,
                QuotationRequest::STATUS_REJECTED,
                QuotationRequest::STATUS_REQUIRES_ADJUSTMENT,
            ],
            QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING => [
                QuotationRequest::STATUS_CLIENT_APPROVED_TOTAL,
                QuotationRequest::STATUS_CLIENT_APPROVED_PARTIAL,
                QuotationRequest::STATUS_CLIENT_REJECTED,
                QuotationRequest::STATUS_CLIENT_REQUIRES_ADJUSTMENT,
                QuotationRequest::STATUS_CUSTOMER_ACCEPTED_TOTAL,
                QuotationRequest::STATUS_CUSTOMER_ACCEPTED_PARTIAL,
                QuotationRequest::STATUS_CUSTOMER_REJECTED,
                QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED,
            ],
            // Fase 4: Aprobación → Orden
            QuotationRequest::STATUS_REQUIRES_ADJUSTMENT => [
                QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
                QuotationRequest::STATUS_REJECTED,
            ],
            OrderTrackerService::QUOTE_PENDING_APPROVAL => [
                QuotationRequest::STATUS_APPROVED_TOTAL,
                QuotationRequest::STATUS_APPROVED_PARTIAL,
                QuotationRequest::STATUS_REJECTED,
                QuotationRequest::STATUS_REQUIRES_ADJUSTMENT,
            ],
            default => [],
        };
    }

    public function permissionForStatus(string $status): ?string
    {
        return match ($status) {
            QuotationRequest::STATUS_APPROVED_TO_SEND => 'client_offer_review.approve',
            QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED => 'client_offer_review.request_changes',
            QuotationRequest::STATUS_ADMIN_REJECTED => 'client_offer_review.reject',
            QuotationRequest::STATUS_APPROVED_TOTAL => 'order_approval.approve',
            QuotationRequest::STATUS_APPROVED_PARTIAL => 'order_approval.partial_approve',
            QuotationRequest::STATUS_REJECTED => 'order_approval.reject',
            QuotationRequest::STATUS_REQUIRES_ADJUSTMENT => 'order_approval.request_changes',
            default => null,
        };
    }

    public function record(QuotationRequest $request, string $action, string $description, string $phase, ?string $oldStatus, ?string $newStatus, User $user): void
    {
        $tracker = $this->trackerService->trackerForQuotation($request);
        $tracker->update([
            'operational_phase' => $phase,
            'operational_status' => $request->status,
            'main_reference' => $request->reference,
        ]);

        $this->trackerService->recordActivity($tracker, $action, $description, $phase, 'quotation_requests', $request->id, $oldStatus, $newStatus, $user);

        ActivityLog::log('ventas', $action, $description, [
            'quote_id' => $request->id,
            'tracker_id' => $tracker->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);
    }

    public function deny(string $message): void
    {
        throw ValidationException::withMessages(['workflow' => $message]);
    }

    public function assertRequestItemsAreNormalized(QuotationRequest $request): void
    {
        $request->loadMissing('items');

        foreach ($request->items as $item) {
            if (blank($item->part_name)) {
                $this->deny("La partida {$this->lineLabel($item)} no tiene nombre de pieza.");
            }

            if (blank($item->part_number)) {
                $this->deny("La partida {$this->lineLabel($item)} no tiene numero de parte o referencia.");
            }

            if ((float) ($item->quantity ?? 0) <= 0) {
                $this->deny("La partida {$this->lineLabel($item)} no tiene cantidad valida.");
            }
        }
    }

    private function lineLabel($item): string
    {
        return $item->part_number ?: ($item->part_name ?: 'sin referencia');
    }
}
