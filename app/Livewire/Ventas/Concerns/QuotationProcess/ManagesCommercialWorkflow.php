<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess;

use App\Domain\Ventas\FlujoTrabajo\Services\QuotationStageChangeService;
use App\Models\QuotationRequest;
use App\Models\QuotationStageChangeRequest;
use App\Models\SupplierRfqDocument;
use App\Services\OrderTrackerService;
use App\Services\PurchasePreparationService;
use App\Services\SalesWorkflowService;
use App\Services\SupplierBidResponseService;
use App\Services\SupplierRfqReviewService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

trait ManagesCommercialWorkflow
{
    public ?string $advanceStageError = null;

    public bool $showStageRevertRequestModal = false;

    public ?int $stageRevertQuoteId = null;

    public string $stageRevertReason = '';

    public function previousStageInfo(?QuotationRequest $quote = null): ?array
    {
        $quote ??= $this->selectedQuote();
        if (! $quote) {
            return null;
        }

        $stage = $this->stageKeyForQuote($quote);

        return match ($stage) {
            'supplier_response' => [
                'label' => 'Volver a Licitación Proveedor',
                'target_status' => QuotationRequest::STATUS_PENDING_BID,
                'target_stage' => 'supplier_bid',
                'warning' => 'Los RFQ y respuestas ya registradas se conservarán. Verifica documentos y estados de envío antes de avanzar nuevamente.',
            ],
            'supplier_selection' => [
                'label' => 'Volver a Respuesta Proveedor',
                'target_status' => QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
                'target_stage' => 'supplier_response',
                'warning' => 'Las respuestas, archivos y selecciones actuales se conservarán para que puedas corregirlas.',
            ],
            'client_offer' => [
                'label' => 'Volver a Preliquidación',
                'target_status' => QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING,
                'target_stage' => 'supplier_response',
                'warning' => 'El PDF vigente de Oferta Cliente quedará invalidado. Las respuestas y precios existentes se conservarán para corregirlos antes de generar una oferta nueva.',
            ],
            'order_changes', 'customer_approval' => [
                'label' => 'Volver a Oferta Cliente',
                'target_status' => QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
                'target_stage' => 'client_offer',
                'warning' => 'La aprobación o respuesta previa quedará como historial y el PDF vigente se invalidará. Revisa precios y genera una nueva oferta antes de enviarla.',
            ],
            'purchase_preparation' => [
                'label' => 'Volver a Aprobación Cliente',
                'target_status' => QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
                'target_stage' => 'customer_approval',
                'warning' => 'La preparación de compra se pausará y sus partidas volverán a revisión. No se eliminarán datos. Será necesaria una nueva confirmación del cliente.',
            ],
            default => null,
        };
    }

    public function revertStage(int $id, ?QuotationStageChangeService $service = null, ?SalesWorkflowService $workflow = null): void
    {
        $service ??= app(QuotationStageChangeService::class);
        $workflow ??= app(SalesWorkflowService::class);

        $quote = QuotationRequest::with(['purchaseOrders', 'purchasePreparationItems'])->find($id);
        if (! $quote) {
            return;
        }

        if (! $this->canRevertCommercialStage($quote)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No tienes permiso para regresar esta etapa.']);

            return;
        }

        if ($this->hasActivePurchaseOrders($quote)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se puede regresar la etapa porque ya existen Órdenes de Compra. Deben anularse mediante su flujo administrativo antes de corregir la cotización.']);

            return;
        }

        $previous = $this->previousStageInfo($quote);
        if (! $previous) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Esta es la primera etapa o no admite un retroceso.']);

            return;
        }

        try {
            $service->executeRevert($quote, $previous, auth()->user());

            QuotationStageChangeRequest::query()
                ->where('quotation_request_id', $quote->id)
                ->where('status', QuotationStageChangeRequest::STATUS_PENDING)
                ->update([
                    'status' => QuotationStageChangeRequest::STATUS_APPROVED,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'review_notes' => 'Retroceso aplicado directamente por el usuario.',
                ]);

            $targetTab = $this->flowStages[$previous['target_stage']]['tab']
                ?? (match ($previous['target_stage']) {
                    'supplier_bid', 'supplier_response', 'supplier_selection' => 'bid',
                    'client_offer' => 'offer',
                    'customer_approval', 'purchase_preparation' => 'approval',
                    default => 'bid',
                });

            $this->activeTab = $targetTab;
            session(['sales_quotation_active_tab' => $targetTab]);

            if (in_array($previous['target_stage'], ['supplier_bid', 'supplier_response'], true)) {
                $this->bidStep = $previous['target_stage'] === 'supplier_bid' ? 1 : 2;
            }

            $this->closeStageRevertRequestModal();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Se regresó a la etapa: {$previous['label']}. Las partidas y datos registrados se conservaron.",
            ]);
            $this->dispatch('commercial-stage-reverted', ['stage' => $previous['target_stage']]);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', ['type' => 'error', 'message' => \App\Support\UserMessage::error($e, 'No se pudo cambiar la etapa. Revisa su estado e intenta de nuevo.')]);
        }
    }

    public function submitStageRevertRequest(QuotationStageChangeService $service): void
    {
        $this->validate([
            'stageRevertQuoteId' => ['required', 'integer'],
            'stageRevertReason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'stageRevertReason.required' => 'Explica por qué necesitas regresar la etapa.',
            'stageRevertReason.min' => 'El motivo debe contener al menos 10 caracteres.',
        ]);

        $quote = QuotationRequest::with('purchaseOrders')->findOrFail($this->stageRevertQuoteId);
        abort_unless($this->canRevertCommercialStage($quote), 403);
        $previous = $this->previousStageInfo($quote);
        if (! $previous) {
            throw ValidationException::withMessages(['stageRevertReason' => 'La etapa actual ya no admite este retroceso.']);
        }

        try {
            $service->requestRevert($quote, $previous, auth()->user(), $this->stageRevertReason);
            $this->closeStageRevertRequestModal();
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Solicitud enviada a Administración. La etapa no cambiará hasta ser aprobada.']);
        } catch (ValidationException $exception) {
            $this->addError('stageRevertReason', collect($exception->errors())->flatten()->first());
        }
    }

    public function closeStageRevertRequestModal(): void
    {
        $this->showStageRevertRequestModal = false;
        $this->stageRevertQuoteId = null;
        $this->stageRevertReason = '';
        $this->resetValidation(['stageRevertReason']);
    }

    public function pendingStageChangeRequest(QuotationRequest $quote): ?QuotationStageChangeRequest
    {
        return QuotationStageChangeRequest::query()
            ->where('quotation_request_id', $quote->id)
            ->where('status', QuotationStageChangeRequest::STATUS_PENDING)
            ->latest()
            ->first();
    }

    private function canRevertCommercialStage(QuotationRequest $quote): bool
    {
        return (bool) auth()->user();
    }

    public function advanceStage(int $id, SalesWorkflowService $workflow, PurchasePreparationService $purchasePreparation): void
    {
        $this->advanceStageError = null;
        Log::info("advanceStage called with id: {$id}");
        $recentAdvanceKey = 'quotation-stage-advanced-recently:'.$id;
        if (Cache::get($recentAdvanceKey)) {
            Log::warning("advanceStage: rate limited for {$id}");
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'La etapa ya se esta actualizando. Espere un momento.']);
            $this->dispatch('advance-stage-finished');

            return;
        }

        $lock = Cache::lock('quotation-stage-advance:'.$id, 10);
        if (! $lock->get()) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'La etapa ya se esta actualizando. Espere un momento.']);
            $this->dispatch('advance-stage-finished');

            return;
        }

        $quote = QuotationRequest::with(['items', 'supplierBidSelections', 'supplierRfqDocuments'])->find($id);

        if (! $quote) {
            $lock->release();
            $this->dispatch('advance-stage-finished');

            return;
        }

        $previousStatus = $quote->status;
        $advanced = false;

        try {
            match ($this->stageKeyForQuote($quote)) {
                'customer_request' => $workflow->transition($quote, QuotationRequest::STATUS_PENDING_BID, auth()->user()),
                'supplier_bid' => $this->advanceSupplierBidStage($quote, $workflow),
                'supplier_response' => $this->advanceSupplierResponseStage($quote, $workflow),
                'supplier_selection' => app(SupplierBidResponseService::class)->sendSelectionsToClientOffer($quote, auth()->user()),
                'client_offer' => $this->advanceClientOfferStage($quote, $workflow),
                'order_changes' => $this->reopenClientOfferForRevision(),
                'purchase_preparation' => $this->advancePurchasePreparationStage($quote, $purchasePreparation),
                'purchase_order_generated' => throw ValidationException::withMessages(['purchase_order' => 'Las órdenes de compra están esperando aprobación administrativa. Una vez aprobadas, se generará el Pedido (Sales Order) automáticamente.']),
                default => null,
            };

            $sentGeneratedPurchaseOrderToApproval = $this->stageKeyForQuote($quote) === 'purchase_order_generated';
            $quote->refresh();
            $advanced = $quote->status !== $previousStatus;
            $newStage = $this->stageKeyForQuote($quote);
            $this->activeTab = $this->flowStages[$newStage]['tab'] ?? $this->activeTab;
            session(['sales_quotation_active_tab' => $this->activeTab]);

            if ($sentGeneratedPurchaseOrderToApproval) {
                $this->selectedQuoteId = null;
                $this->showDetail = false;
            } else {
                $this->selectedQuoteId = $quote->id;
                $this->showDetail = true;
            }

            $message = $sentGeneratedPurchaseOrderToApproval
                ? 'Orden de compra enviada a aprobaciones.'
                : 'La solicitud avanzo a la siguiente etapa.';

            $this->dispatch('notify', ['type' => 'success', 'message' => $message]);
            $this->dispatch('commercial-phase-advanced');
        } catch (ValidationException $e) {
            $this->advanceStageError = collect($e->errors())->flatten()->first();
            $this->dispatch('notify', ['type' => 'error', 'message' => $this->advanceStageError]);
        } catch (Throwable $e) {
            report($e);
            $this->advanceStageError = \App\Support\UserMessage::error($e, 'No se pudo completar la acción. Revisa los datos e intenta de nuevo.');
            $this->dispatch('notify', ['type' => 'error', 'message' => $this->advanceStageError]);
        } finally {
            if ($advanced) {
                Cache::put($recentAdvanceKey, true, now()->addSeconds(3));
            }

            $lock->release();
            $this->dispatch('advance-stage-finished');
        }
    }

    public function reviewAndRouteRequest(SalesWorkflowService $workflow): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        try {
            $workflow->reviewAndRouteRequest($quote, auth()->user());
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Solicitud revisada y enrutada segun stock/proveedor.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function stageKeyForStatus(?string $status): string
    {
        return match ($status) {
            QuotationRequest::STATUS_RECEIVED,
            QuotationRequest::STATUS_IN_REVIEW => 'supplier_bid',

            QuotationRequest::STATUS_READY_FOR_BID,
            QuotationRequest::STATUS_PENDING_BID,
            QuotationRequest::STATUS_RFQ_GENERATED => 'supplier_bid',

            QuotationRequest::STATUS_SENT_TO_SUPPLIER,
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING => 'supplier_response',

            QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            QuotationRequest::STATUS_SUPPLIER_SELECTED,
            QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            QuotationRequest::STATUS_OFFER_DRAFT,
            QuotationRequest::STATUS_OFFER_PREVIEW_READY,
            QuotationRequest::STATUS_ADMIN_CHANGES_REQUESTED,
            QuotationRequest::STATUS_REVISED_AFTER_CUSTOMER_CHANGE,
            QuotationRequest::STATUS_PENDING_ADMIN_REVIEW,
            QuotationRequest::STATUS_ADMIN_REJECTED,
            QuotationRequest::STATUS_APPROVED_TO_SEND,
            QuotationRequest::STATUS_FINAL_PDF_GENERATED,
            QuotationRequest::STATUS_CUSTOMER_CHANGES_REQUESTED,
            QuotationRequest::STATUS_CLIENT_OFFER_SENT => 'client_offer',

            QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
            QuotationRequest::STATUS_SENT_TO_CUSTOMER => 'customer_approval',

            QuotationRequest::STATUS_CLIENT_APPROVED_TOTAL,
            QuotationRequest::STATUS_CLIENT_APPROVED_PARTIAL,
            QuotationRequest::STATUS_APPROVED_TOTAL,
            QuotationRequest::STATUS_APPROVED_PARTIAL,
            OrderTrackerService::QUOTE_PENDING_APPROVAL => 'purchase_preparation',

            default => 'supplier_bid',
        };
    }

    public function stageKeyForQuote(QuotationRequest $quote): string
    {
        $quote->loadMissing('purchaseOrders');

        if ($this->hasActivePurchaseOrders($quote)) {
            return 'purchase_order_generated';
        }

        return $this->stageKeyForStatus($quote->status);
    }

    public function humanStatus(?string $status): string
    {
        $stage = $this->stageKeyForStatus($status);

        return $this->flowStages[$stage]['visual'] ?? 'Solicitud cliente';
    }

    public function nextActionForStatus(?string $status): string
    {
        $stage = $this->stageKeyForStatus($status);

        return $this->flowStages[$stage]['action'] ?? 'Abrir detalle';
    }

    public function quoteHasBlockingAlert(QuotationRequest $quote): bool
    {
        $stage = $this->stageKeyForQuote($quote);

        return match ($stage) {
            'customer_request' => blank($quote->quote_to_name) || $quote->items->isEmpty() || $quote->items->contains(fn ($item) => (float) $item->quantity <= 0),
            'supplier_bid' => $quote->supplierRfqDocuments->isEmpty() && $quote->supplierBidResponses->isEmpty(),
            'supplier_response' => app(SupplierBidResponseService::class)->responseCoverage($quote)['responded'] <= 0,
            'supplier_selection' => $quote->supplierBidSelections->isEmpty(),
            'client_offer' => blank($quote->client_offer_pdf_path),
            'order_changes' => true,
            'customer_approval' => ! in_array($quote->status, [QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING, QuotationRequest::STATUS_SENT_TO_CUSTOMER], true),
            'purchase_preparation' => $quote->purchasePreparationItems->isNotEmpty()
                && $quote->purchasePreparationItems->contains(fn ($item) => ! $item->final_supplier_id || (float) $item->final_unit_cost <= 0 || (float) $item->quantity_to_purchase <= 0),
            default => false,
        };
    }

    private function advanceSupplierBidStage(QuotationRequest $quote, SalesWorkflowService $workflow): void
    {
        $quote->loadMissing('supplierRfqDocuments');

        if (! $quote->supplierRfqDocuments->contains(fn ($document) => filled($document->pdf_path))) {
            throw ValidationException::withMessages(['rfq' => 'Genera al menos un PDF RFQ antes de avanzar.']);
        }

        if (! $quote->supplierRfqDocuments->contains(fn ($document) => $document->status === SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER)) {
            throw ValidationException::withMessages(['rfq' => 'Es obligatorio marcar al menos un RFQ como enviado al proveedor antes de avanzar a Respuesta Proveedor.']);
        }

        app(SupplierRfqReviewService::class)->assertAllItemsHaveSentRfq($quote);

        $workflow->transition($quote, QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING, auth()->user(), true);
    }

    private function advanceSupplierResponseStage(QuotationRequest $quote, SalesWorkflowService $workflow): void
    {
        $quote->supplierRfqDocuments()->whereNull('supplier_id')->delete();

        $bidService = app(SupplierBidResponseService::class);
        $bidService->assertHasValidResponseForSelection($quote);
        $bidService->selectLowestCostItemsForOffer($quote, auth()->user());

        $workflow->transition($quote, QuotationRequest::STATUS_CLIENT_OFFER_GENERATED, auth()->user(), true);
        $this->activeTab = 'offer';
        session(['sales_quotation_active_tab' => 'offer']);
    }

    public function returnToSupplierResponse(int $id, SalesWorkflowService $workflow): void
    {
        $quote = QuotationRequest::findOrFail($id);

        if (! in_array($quote->status, [QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED, QuotationRequest::STATUS_SUPPLIER_SELECTED, QuotationRequest::STATUS_APPROVED_TO_SEND, QuotationRequest::STATUS_CLIENT_OFFER_GENERATED], true)) {
            throw ValidationException::withMessages(['workflow' => 'Solo se puede volver a Respuesta Proveedor desde la oferta o selección.']);
        }

        $quote->supplierBidSelections()->delete();
        $quote->items()->update(['is_approved' => false, 'supplier_selection_status' => null]);

        $workflow->transition($quote, QuotationRequest::STATUS_SUPPLIER_RESPONSE_PENDING, auth()->user(), true);
        $this->bidStep = 2;
        $this->activeTab = $this->flowStages['supplier_response']['tab'] ?? 'licitacion';
        session(['sales_quotation_active_tab' => $this->activeTab]);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'La solicitud volvió a Respuesta Proveedor. Las respuestas y adjuntos existentes se conservaron.']);
    }

    public function supplierResponseCoverage(QuotationRequest $quote): array
    {
        return app(SupplierBidResponseService::class)->responseCoverage($quote);
    }

    private function advancePurchasePreparationStage(QuotationRequest $quote, PurchasePreparationService $purchasePreparation): void
    {
        $quote->loadMissing('purchaseOrders');
        if (! $this->hasActivePurchaseOrders($quote)) {
            throw ValidationException::withMessages(['purchase_order' => 'Debe generar las Órdenes de Compra usando el botón correspondiente antes de avanzar.']);
        }
    }

    private function hasActivePurchaseOrders(QuotationRequest $quote): bool
    {
        $quote->loadMissing('purchaseOrders');

        return $quote->purchaseOrders->contains(
            fn ($order) => ! in_array($order->status, ['anulado', 'cancelado', 'cancelled'], true)
        );
    }

    private function advanceClientOfferStage(QuotationRequest $quote, SalesWorkflowService $workflow): void
    {
        $quote->loadMissing('offerVersions');
        $hasGeneratedPdf = filled($quote->client_offer_pdf_path) || $quote->offerVersions->isNotEmpty();

        if (! $hasGeneratedPdf) {
            throw ValidationException::withMessages([
                'client_offer' => 'Es obligatorio generar el PDF de la Oferta Cliente antes de continuar a la respuesta del cliente.',
            ]);
        }

        if ($quote->status === QuotationRequest::STATUS_FINAL_PDF_GENERATED) {
            app(\App\Domain\Ventas\OfertasCliente\Services\ClientOfferReviewService::class)->markSentToCustomer($quote, auth()->user());
        } else {
            $workflow->transition($quote, QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING, auth()->user(), true);
        }

        $this->activeTab = 'approval';
        session(['sales_quotation_active_tab' => 'approval']);
    }
}
