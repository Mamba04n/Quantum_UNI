<?php

namespace App\Domain\Ventas\FlujoTrabajo\Services;

use App\Domain\Pedidos\Tracking\Services\OrderTrackerService;
use App\Models\PurchasePreparationItem;
use App\Models\QuotationRequest;
use App\Models\QuotationStageChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationStageChangeService
{
    public function __construct(
        private readonly SalesWorkflowService $workflow,
        private readonly OrderTrackerService $trackerService,
    ) {}

    public function requestRevert(QuotationRequest $quote, array $transition, User $user, string $reason): QuotationStageChangeRequest
    {
        return DB::transaction(function () use ($quote, $transition, $user, $reason): QuotationStageChangeRequest {
            $quote = QuotationRequest::query()->with('purchaseOrders')->lockForUpdate()->findOrFail($quote->id);

            if ($this->hasActivePurchaseOrders($quote)) {
                throw ValidationException::withMessages(['stage' => 'No se puede regresar porque ya existen órdenes de compra.']);
            }

            $pending = QuotationStageChangeRequest::query()
                ->where('quotation_request_id', $quote->id)
                ->where('status', QuotationStageChangeRequest::STATUS_PENDING)
                ->exists();

            if ($pending) {
                throw ValidationException::withMessages(['stage' => 'Esta cotización ya tiene un cambio de etapa pendiente de aprobación.']);
            }

            $request = QuotationStageChangeRequest::create([
                'quotation_request_id' => $quote->id,
                'requested_by' => $user->id,
                'from_status' => $quote->status,
                'to_status' => $transition['target_status'],
                'from_stage' => $this->workflow->phaseForStatus($quote->status),
                'to_stage' => $transition['target_stage'],
                'status' => QuotationStageChangeRequest::STATUS_PENDING,
                'reason' => $reason,
                'warning' => $transition['warning'] ?? null,
            ]);

            $this->record($quote, 'commercial_stage.revert_requested', 'Retroceso de etapa solicitado para aprobación administrativa.', $user);

            return $request;
        });
    }

    public function executeRevert(QuotationRequest $quote, array $transition, User $user): void
    {
        DB::transaction(function () use ($quote, $transition, $user): void {
            $quote = QuotationRequest::query()->with('purchaseOrders')->lockForUpdate()->findOrFail($quote->id);

            if ($quote->status === QuotationRequest::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['stage' => 'La cotización se encuentra cancelada y no puede modificarse su etapa.']);
            }

            if ($this->hasActivePurchaseOrders($quote)) {
                throw ValidationException::withMessages(['stage' => 'No se puede regresar porque ya existen órdenes de compra activas.']);
            }

            $fromStatus = $quote->status;
            $toStatus = $transition['target_status'];
            $toStage = $transition['target_stage'];

            $changes = [
                'status' => $toStatus,
                'commercial_phase' => $this->workflow->phaseForStatus($toStatus),
            ];

            if (in_array($toStage, ['supplier_selection', 'client_offer', 'supplier_response', 'supplier_bid'], true)) {
                $changes['client_offer_pdf_path'] = null;
                $changes['client_offer_generated_at'] = null;
            }

            $quote->forceFill($changes)->save();

            if (in_array($toStage, ['supplier_response', 'supplier_bid'], true)) {
                $quote->supplierBidSelections()->delete();
                $quote->items()->update([
                    'supplier_selection_status' => null,
                    'is_approved' => false,
                ]);
            }

            if ($toStage === 'customer_approval') {
                $quote->purchasePreparationItems()->update([
                    'status' => PurchasePreparationItem::STATUS_PENDING_REVIEW,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);
            }

            $tracker = $this->trackerService->trackerForQuotation($quote);
            if ($tracker) {
                $phase = $this->workflow->phaseForStatus($toStatus);
                $tracker->forceFill([
                    'operational_phase' => $phase,
                    'operational_status' => $toStatus,
                ])->save();

                $this->trackerService->recordActivity(
                    $tracker,
                    'commercial_stage.reverted',
                    "Retroceso de etapa directo: {$fromStatus} → {$toStatus} ({$transition['label']}).",
                    $phase,
                    'quotation_requests',
                    $quote->id,
                    $fromStatus,
                    $toStatus,
                    $user
                );
            }
        });
    }

    public function approve(QuotationStageChangeRequest $request, User $admin, ?string $notes = null): void
    {
        DB::transaction(function () use ($request, $admin, $notes): void {
            $request = QuotationStageChangeRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertPending($request);

            $quote = QuotationRequest::query()->with('purchaseOrders')->lockForUpdate()->findOrFail($request->quotation_request_id);

            if ($quote->status === QuotationRequest::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['stage' => 'La cotización se encuentra cancelada y no puede modificarse su etapa.']);
            }

            if ($quote->status !== $request->from_status) {
                throw ValidationException::withMessages(['stage' => 'La cotización cambió de etapa desde que se creó la solicitud.']);
            }

            if ($this->hasActivePurchaseOrders($quote)) {
                throw ValidationException::withMessages(['stage' => 'Ya existen órdenes de compra y el retroceso no puede aprobarse.']);
            }

            $changes = [
                'status' => $request->to_status,
                'commercial_phase' => $this->workflow->phaseForStatus($request->to_status),
            ];

            if (in_array($request->to_stage, ['supplier_selection', 'client_offer', 'supplier_response', 'supplier_bid'], true)) {
                $changes['client_offer_pdf_path'] = null;
                $changes['client_offer_generated_at'] = null;
            }

            $quote->forceFill($changes)->save();

            if (in_array($request->to_stage, ['supplier_response', 'supplier_bid'], true)) {
                $quote->supplierBidSelections()->delete();
                $quote->items()->update([
                    'supplier_selection_status' => null,
                    'is_approved' => false,
                ]);
            }

            if ($request->to_stage === 'customer_approval') {
                $quote->purchasePreparationItems()->update([
                    'status' => PurchasePreparationItem::STATUS_PENDING_REVIEW,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);
            }

            $request->update([
                'status' => QuotationStageChangeRequest::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'review_notes' => $notes,
                'reviewed_at' => now(),
            ]);

            $this->syncTracker($quote, $request, $admin);
        });
    }

    public function reject(QuotationStageChangeRequest $request, User $admin, string $notes): void
    {
        DB::transaction(function () use ($request, $admin, $notes): void {
            $request = QuotationStageChangeRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertPending($request);

            $quote = QuotationRequest::query()->lockForUpdate()->findOrFail($request->quotation_request_id);

            $request->update([
                'status' => QuotationStageChangeRequest::STATUS_REJECTED,
                'reviewed_by' => $admin->id,
                'review_notes' => $notes,
                'reviewed_at' => now(),
            ]);

            $this->record($quote, 'commercial_stage.revert_rejected', 'Retroceso de etapa rechazado por Administración.', $admin);
        });
    }

    private function assertPending(QuotationStageChangeRequest $request): void
    {
        if ($request->status !== QuotationStageChangeRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['stage' => 'Esta solicitud ya fue procesada.']);
        }
    }

    private function syncTracker(QuotationRequest $quote, QuotationStageChangeRequest $request, User $admin): void
    {
        $tracker = $this->trackerService->trackerForQuotation($quote);
        $phase = $this->workflow->phaseForStatus($request->to_status);

        $tracker->forceFill([
            'operational_phase' => $phase,
            'operational_status' => $request->to_status,
        ])->save();

        $this->trackerService->recordActivity($tracker, 'commercial_stage.reverted_approved', "Retroceso aprobado: {$request->from_status} → {$request->to_status}.", $phase, 'quotation_requests', $quote->id, $request->from_status, $request->to_status, $admin);
    }

    private function record(QuotationRequest $quote, string $event, string $description, User $user): void
    {
        $tracker = $this->trackerService->trackerForQuotation($quote);

        $this->trackerService->recordActivity($tracker, $event, $description, $this->workflow->phaseForStatus($quote->status), 'quotation_requests', $quote->id, null, $quote->status, $user);
    }

    private function hasActivePurchaseOrders(QuotationRequest $quote): bool
    {
        return $quote->purchaseOrders->contains(
            fn ($order) => ! in_array($order->status, ['anulado', 'cancelado', 'cancelled'], true)
        );
    }
}
