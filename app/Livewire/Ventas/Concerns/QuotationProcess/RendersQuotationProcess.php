<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess;

use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\Entity;
use App\Models\PurchasePreparationItem;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Models\SupplierRfqDocument;
use App\Services\ApprovalService;
use App\Services\ClientOfferReviewService;
use App\Services\ExchangeRateService;
use App\Services\InventoryReservationService;
use App\Services\OrderTrackerService;
use App\Services\PdfService;
use App\Services\PurchasePreparationService;
use App\Services\SalesWorkflowService;
use App\Services\SupplierBidResponseService;
use App\Services\SupplierRfqReviewService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

trait RendersQuotationProcess
{
    public function render(SalesWorkflowService $workflow)
    {
        $query = QuotationRequest::with([
            'items.product',
            'items.supplierBidSelection.responseItem.response.supplier',
            'items.supplierBidResponseItems.response.supplier',
            'items.supplierBidResponseItems.response.supplierDocumentAttachments',
            'user',
            'supplierBidResponses.supplier',
            'supplierBidResponses.supplierDocumentAttachments',
            'supplierBidSelections.supplier',
            'supplierRfqDocuments.supplier',
            'purchasePreparationItems.finalSupplier',
            'purchaseOrders',
        ])->orderBy('updated_at', 'desc');

        $phaseStatuses = [
            'bid' => [
                QuotationRequest::STATUS_RECEIVED,
                QuotationRequest::STATUS_IN_REVIEW,
                QuotationRequest::STATUS_READY_FOR_BID,
                QuotationRequest::STATUS_PENDING_BID,
                QuotationRequest::STATUS_RFQ_GENERATED,
                QuotationRequest::STATUS_SENT_TO_SUPPLIER,
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
                QuotationRequest::STATUS_SUPPLIER_SELECTED,
            ],
            'offer' => [
                QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
                QuotationRequest::STATUS_CLIENT_OFFER_SENT,
                QuotationRequest::STATUS_OFFER_DRAFT,
                QuotationRequest::STATUS_OFFER_PREVIEW_READY,
                QuotationRequest::STATUS_PENDING_ADMIN_REVIEW,
                QuotationRequest::STATUS_ADMIN_REJECTED,
                QuotationRequest::STATUS_APPROVED_TO_SEND,
                QuotationRequest::STATUS_FINAL_PDF_GENERATED,
                QuotationRequest::STATUS_REVISED_AFTER_CUSTOMER_CHANGE,
            ],
            'approval' => [
                QuotationRequest::STATUS_CLIENT_APPROVED_TOTAL,
                QuotationRequest::STATUS_CLIENT_APPROVED_PARTIAL,
                QuotationRequest::STATUS_CUSTOMER_ACCEPTED_TOTAL,
                QuotationRequest::STATUS_CUSTOMER_ACCEPTED_PARTIAL,
                QuotationRequest::STATUS_APPROVED_TOTAL,
                QuotationRequest::STATUS_APPROVED_PARTIAL,
                OrderTrackerService::QUOTE_PENDING_APPROVAL,
            ],
        ];

        $stageStatuses = [
            'supplier_bid' => [QuotationRequest::STATUS_RECEIVED, QuotationRequest::STATUS_IN_REVIEW, QuotationRequest::STATUS_READY_FOR_BID, QuotationRequest::STATUS_PENDING_BID, QuotationRequest::STATUS_RFQ_GENERATED],
            'supplier_response' => [QuotationRequest::STATUS_SENT_TO_SUPPLIER, QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING],
            'client_offer' => [
                QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
                QuotationRequest::STATUS_SUPPLIER_SELECTED,
                QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
                QuotationRequest::STATUS_OFFER_DRAFT,
                QuotationRequest::STATUS_OFFER_PREVIEW_READY,
                QuotationRequest::STATUS_PENDING_ADMIN_REVIEW,
                QuotationRequest::STATUS_ADMIN_REJECTED,
                QuotationRequest::STATUS_APPROVED_TO_SEND,
                QuotationRequest::STATUS_FINAL_PDF_GENERATED,
                QuotationRequest::STATUS_CLIENT_OFFER_SENT,
                QuotationRequest::STATUS_REVISED_AFTER_CUSTOMER_CHANGE,
                QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED,
            ],
            'customer_approval' => [QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING, QuotationRequest::STATUS_SENT_TO_CUSTOMER],
            'purchase_preparation' => [
                QuotationRequest::STATUS_CLIENT_APPROVED_TOTAL,
                QuotationRequest::STATUS_CLIENT_APPROVED_PARTIAL,
                QuotationRequest::STATUS_APPROVED_TOTAL,
                QuotationRequest::STATUS_APPROVED_PARTIAL,
                OrderTrackerService::QUOTE_PENDING_APPROVAL,
            ],
            'purchase_order_generated' => [],
        ];

        // En el detalle no se utilizan el listado, sus conteos ni las columnas
        // del Kanban. Evitar esas consultas hace que las acciones por partida
        // escalen con la orden abierta y no con todo el histórico comercial.
        if ($this->selectedQuoteId) {
            $selectedQuote = QuotationRequest::with([
                'items.product',
                'items.clientMachine',
                'items.inventoryReservations.product',
                'items.selectedSupplier',
                'items.selectedSupplierBidItem.bid',
                'items.supplierBidItems.bid.supplier',
                'items.supplierBidResponseItems.response.supplier',
                'items.supplierBidSelection.responseItem.response.supplier',
                'user',
                'supplierBidResponses.supplier',
                'supplierBidResponses.supplierDocumentAttachment',
                'supplierBidResponses.items.quotationItem',
                'supplierBidResponses.items.selection',
                'supplierBidSelections.supplier',
                'supplierBidSelections.responseItem.response.supplier',
                'supplierBidSelections.quotationItem',
                'supplierRfqDocuments.supplier',
                'supplierRfqDocuments.adminReviewer',
                'supplierRfqDocuments.generator',
                'offerVersions.items',
                'offerVersions.creator',
                'offerReviews.requester',
                'offerReviews.reviewer',
                'clientOfferChangeRequests',
                'inventoryReservations.product',
                'purchasePreparationItems.quotationItem.supplierBidResponseItems.response.supplier',
                'purchasePreparationItems.originalSupplier',
                'purchasePreparationItems.finalSupplier',
                'purchasePreparationItems.originalSupplierQuote.response.supplier',
                'purchasePreparationItems.finalSupplierQuote.response.supplier',
                'purchaseOrders',
            ])
                ->when($this->activeTab === 'approval', fn ($q) => $this->onlyOpenApprovalOrGeneratedCycle($q))
                ->find($this->selectedQuoteId);

            if ($selectedQuote && (blank($this->rfqQuoteToName) || empty($this->selectedRfqItemIds))) {
                $this->loadSupplierRfqDocumentDefaults();
            }

            return view('livewire.ventas.cotizaciones.fase-cotizacion.gestor-fase-cotizacion', [
                'quotes' => collect(),
                'selectedQuote' => $selectedQuote,
                'workflow' => $workflow,
                'phaseStatuses' => $phaseStatuses,
                'tabCounts' => [],
                'flowStages' => $this->flowStages,
                'kanbanColumns' => [],
                'stageCounts' => [],
                'suppliers' => $this->supplierOptions(),
                'selectedSupplier' => $this->selectedSupplierId ? Entity::with(['brands', 'paymentMethods'])->find($this->selectedSupplierId) : null,
            ]);
        }

        $tabCounts = collect($phaseStatuses)
            ->mapWithKeys(function (array $statuses, string $key) {
                $countQuery = QuotationRequest::query()->whereIn('status', $statuses);

                if ($key === 'approval') {
                    $this->onlyOpenApprovalCycle($countQuery);
                }

                return [$key => $countQuery->count()];
            })
            ->all();

        $visibleStatuses = collect($stageStatuses)->flatten()->all();

        $quotes = $query
            ->when($this->activeTab !== 'kanban', fn ($q) => $q->whereNull('sale_order_id'))
            ->when($this->activeTab === 'approval', fn ($q) => $this->onlyOpenApprovalOrGeneratedCycle($q))
            ->where(function ($visibility) use ($visibleStatuses) {
                if ($this->activeTab === 'kanban') {
                    $visibility->orWhereNotNull('sale_order_id')
                        ->orWhereIn('status', [QuotationRequest::STATUS_CLOSED, QuotationRequest::STATUS_CANCELLED]);
                }
                $visibility
                    ->orWhereIn('status', $visibleStatuses)
                    ->orWhere(function ($generated) {
                        $generated
                            ->whereHas('purchaseOrders')
                            ->whereHas('purchaseOrders.approvalRequest', function ($approval) {
                                $approval->where('status', ApprovalRequest::STATUS_PENDING);
                            });
                    });
            })
            ->when($this->search !== '', function ($q) {
                $search = '%'.$this->search.'%';

                $q->where(function ($inner) use ($search) {
                    $inner->where('reference', 'like', $search)
                        ->orWhere('quote_to_name', 'like', $search)
                        ->orWhere('status', 'like', $search)
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $search))
                        ->orWhereHas('items', function ($items) use ($search) {
                            $items->where('part_name', 'like', $search)
                                ->orWhere('part_number', 'like', $search)
                                ->orWhere('brand', 'like', $search)
                                ->orWhere('model', 'like', $search)
                                ->orWhere('description', 'like', $search)
                                ->orWhereHas('product', fn ($product) => $product->where('name', 'like', $search));
                        })
                        ->orWhereHas('supplierBidResponses.supplier', fn ($supplier) => $supplier->where('name', 'like', $search))
                        ->orWhereHas('supplierBidSelections.supplier', fn ($supplier) => $supplier->where('name', 'like', $search))
                        ->orWhereHas('purchasePreparationItems.finalSupplier', fn ($supplier) => $supplier->where('name', 'like', $search));
                });
            })
            ->get();

        $kanbanColumns = collect($this->flowStages)
            ->mapWithKeys(fn (array $stage, string $key) => [$key => $quotes->filter(fn ($quote) => $this->stageKeyForQuote($quote) === $key)->values()])
            ->all();

        $stageCounts = collect($kanbanColumns)->map(fn ($items) => $items->count())->all();

        $selectedQuote = $this->selectedQuoteId
            ? QuotationRequest::with([
                'items.product',
                'items.clientMachine',
                'items.inventoryReservations.product',
                'items.product',
                'items.clientMachine',
                'items.selectedSupplier',
                'items.selectedSupplierBidItem.bid',
                'items.supplierBidItems.bid.supplier',
                'items.supplierBidResponseItems.response.supplier',
                'items.supplierBidSelection.responseItem.response.supplier',
                'user',
                'supplierBidResponses.supplier',
                'supplierBidResponses.supplierDocumentAttachment',
                'supplierBidResponses.items.quotationItem',
                'supplierBidResponses.items.selection',
                'supplierBidSelections.supplier',
                'supplierBidSelections.responseItem.response.supplier',
                'supplierBidSelections.quotationItem',
                'supplierRfqDocuments.supplier',
                'supplierRfqDocuments.adminReviewer',
                'supplierRfqDocuments.generator',
                'offerVersions.items',
                'offerVersions.creator',
                'offerReviews.requester',
                'offerReviews.reviewer',
                'clientOfferChangeRequests',
                'inventoryReservations.product',
                'purchasePreparationItems.quotationItem.supplierBidResponseItems.response.supplier',
                'purchasePreparationItems.originalSupplier',
                'purchasePreparationItems.finalSupplier',
                'purchasePreparationItems.originalSupplierQuote.response.supplier',
                'purchasePreparationItems.finalSupplierQuote.response.supplier',
                'purchaseOrders',
            ])
                ->when($this->activeTab === 'approval', fn ($q) => $this->onlyOpenApprovalOrGeneratedCycle($q))
                ->find($this->selectedQuoteId)
            : null;

        if ($selectedQuote && (blank($this->rfqQuoteToName) || empty($this->selectedRfqItemIds))) {
            $this->loadSupplierRfqDocumentDefaults();
        }

        return view('livewire.ventas.cotizaciones.fase-cotizacion.gestor-fase-cotizacion', [
            'quotes' => $quotes,
            'selectedQuote' => $selectedQuote,
            'workflow' => $workflow,
            'phaseStatuses' => $phaseStatuses,
            'tabCounts' => $tabCounts,
            'flowStages' => $this->flowStages,
            'kanbanColumns' => $kanbanColumns,
            'stageCounts' => $stageCounts,
            'suppliers' => $this->supplierOptions(),
            'selectedSupplier' => $this->selectedSupplierId ? Entity::with(['brands', 'paymentMethods'])->find($this->selectedSupplierId) : null,
        ]);
    }

    private function supplierOptions()
    {
        $search = trim($this->supplierSearch);

        return Entity::where('type', 'proveedor')
            ->where('status', '!=', 'inactivo')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function ($supplier) use ($like) {
                    $supplier
                        ->where('name', 'like', $like)
                        ->orWhere('legal_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('contact_name', 'like', $like)
                        ->orWhere('phone_mobile', 'like', $like)
                        ->orWhere('phone_home', 'like', $like)
                        ->orWhere('country', 'like', $like)
                        ->orWhere('city', 'like', $like);
                });
            })
            ->when($this->selectedSupplierId, function ($query) {
                $query->orWhere(function ($q) {
                    $q->where('type', 'proveedor')
                        ->where('status', '!=', 'inactivo')
                        ->where('id', (int) $this->selectedSupplierId);
                });
            })
            ->orderBy('name')
            ->get();
    }

    private function onlyOpenApprovalCycle($query)
    {
        return $query
            ->whereNull('sale_order_id')
            ->whereDoesntHave('purchaseOrders')
            ->whereDoesntHave('approvalRequests', function ($approval) {
                $approval
                    ->where('status', ApprovalRequest::STATUS_APPROVED)
                    ->where(function ($type) {
                        if (Schema::hasColumn('approval_requests', 'approval_type')) {
                            $type->where('approval_type', ApprovalRequest::TYPE_INTERNAL_PURCHASE)
                                ->orWhere('request_type', ApprovalRequest::TYPE_INTERNAL_PURCHASE)
                                ->orWhere('request_type', ApprovalRequest::TYPE_FOREIGN_ORDER);

                            return;
                        }

                        $type->where('request_type', ApprovalRequest::TYPE_INTERNAL_PURCHASE)
                            ->orWhere('request_type', ApprovalRequest::TYPE_FOREIGN_ORDER);
                    });
            });
    }

    private function onlyOpenApprovalOrGeneratedCycle($query)
    {
        return $query->where(function ($approval) {
            $approval
                ->whereHas('purchaseOrders')
                ->orWhere(function ($open) {
                    $this->onlyOpenApprovalCycle($open);
                });
        });
    }

    protected function selectedQuote(): ?QuotationRequest
    {
        if (! $this->selectedQuoteId) {
            return null;
        }

        return QuotationRequest::with([
                'items.selectedSupplier',
                'items.supplierBidItems.bid.supplier',
                'items.supplierBidResponseItems.response.supplier',
                'items.supplierBidSelection.responseItem.response.supplier',
                'user',
                'supplierBidResponses.supplier',
                'supplierBidResponses.supplierDocumentAttachment',
                'supplierBidResponses.items.quotationItem',
                'supplierBidResponses.items.selection',
                'supplierBidSelections.supplier',
                'supplierBidSelections.responseItem.response',
                'supplierBidSelections.quotationItem',
                'supplierRfqDocuments.supplier',
                'supplierRfqDocuments.adminReviewer',
                'supplierRfqDocuments.generator',
                'offerReviews.requester',
                'offerReviews.reviewer',
                'clientOfferChangeRequests',
                'purchasePreparationItems.quotationItem.supplierBidResponseItems.response.supplier',
                'purchasePreparationItems.originalSupplier',
                'purchasePreparationItems.finalSupplier',
                'purchasePreparationItems.originalSupplierQuote.response.supplier',
                'purchasePreparationItems.finalSupplierQuote.response.supplier',
            ])
                ->find($this->selectedQuoteId);
    }
}
