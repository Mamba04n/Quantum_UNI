<?php

namespace App\Domain\Administracion\Approvals\Actions;

use App\Models\ActivityLog;
use App\Models\ClientApproval;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Services\OrderTrackerService;
use App\Services\PurchasePreparationService;
use App\Services\SalesWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterClientApproval
{
    public function __construct(
        private readonly OrderTrackerService $trackerService,
        private readonly PurchasePreparationService $purchasePreparationService,
    ) {}

    public function execute(QuotationRequest $quotation, User $user, string $decision, ?string $notes = null): ClientApproval
    {
        $permission = match ($decision) {
            'total' => 'client_approval.register_total',
            'partial' => 'client_approval.register_partial',
            'rejected' => 'client_approval.reject',
            'changes_requested' => 'client_approval.request_changes',
            default => null,
        };

        if (! $permission || (! $user->can($permission) && ! $user->can('client_approval.approve') && ! $user->can('supplier_bid.send_to_approval'))) {
            throw ValidationException::withMessages(['client_approval' => 'No tienes permiso para registrar respuesta del cliente.']);
        }

        return DB::transaction(function () use ($quotation, $user, $decision, $notes) {
            $quotation = QuotationRequest::with('items', 'offerVersions')
                ->whereKey($quotation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($quotation->status, [
                QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
                QuotationRequest::STATUS_SENT_TO_CUSTOMER,
            ], true)) {
                throw ValidationException::withMessages(['client_approval' => 'La oferta no esta esperando respuesta del cliente.']);
            }

            if (! $quotation->client_offer_pdf_path && $quotation->offerVersions->isEmpty()) {
                throw ValidationException::withMessages(['client_approval' => 'No se puede registrar aprobacion de cliente sin oferta generada.']);
            }

            $status = match ($decision) {
                'total' => QuotationRequest::STATUS_CLIENT_APPROVED_TOTAL,
                'partial' => QuotationRequest::STATUS_CLIENT_APPROVED_PARTIAL,
                'rejected' => QuotationRequest::STATUS_CLIENT_REJECTED,
                'changes_requested' => QuotationRequest::STATUS_CLIENT_REQUIRES_ADJUSTMENT,
            };

            $offer = $quotation->offerVersions()->latest('version_number')->first();

            if ($decision === 'total' && $offer) {
                $approvedItemIds = $offer->items()
                    ->whereNotNull('quotation_request_item_id')
                    ->pluck('quotation_request_item_id');

                $quotation->items()->update(['is_approved' => false]);
                $quotation->items()->whereKey($approvedItemIds)->update(['is_approved' => true]);
            }

            $approval = ClientApproval::create([
                'quotation_request_id' => $quotation->id,
                'client_offer_id' => $offer?->id,
                'customer_id' => null,
                'status' => $status,
                'approved_type' => $decision,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'notes' => $notes,
            ]);

            $oldStatus = $quotation->status;
            $quotation->forceFill([
                'status' => $status,
                'commercial_phase' => SalesWorkflowService::PHASE_APPROVAL,
            ])->save();

            $tracker = $this->trackerService->trackerForQuotation($quotation);
            if ($tracker) {
                $tracker->update([
                    'operational_phase' => SalesWorkflowService::PHASE_APPROVAL,
                    'operational_status' => $status,
                ]);
                $this->trackerService->addReference($tracker, 'client_approval', 'CA-'.$approval->id, 'client_approvals', $approval->id);
                $this->trackerService->recordActivity(
                    $tracker,
                    'client_approval.'.$decision,
                    "Respuesta del cliente registrada por {$user->name}: {$decision}",
                    SalesWorkflowService::PHASE_APPROVAL,
                    'client_approvals',
                    $approval->id,
                    $oldStatus,
                    $status,
                    $user
                );

                if (in_array($decision, ['total', 'partial'], true)) {
                    $this->purchasePreparationService->ensureForQuotation(
                        $quotation->fresh([
                            'items.supplierBidSelection.responseItem.response.supplier',
                            'offerVersions.items',
                            'clientApprovals.clientOffer.items',
                            'purchasePreparationItems',
                        ]),
                        $user,
                    );

                    $this->trackerService->recordActivity(
                        $tracker,
                        'purchase_preparation.ready',
                        'Oferta aprobada por cliente. Orden lista para preparacion de compra.',
                        'purchase_preparation',
                        'quotation_requests',
                        $quotation->id,
                        $oldStatus,
                        $status,
                        $user
                    );
                }
            }

            ActivityLog::log('approval', 'client_approval.'.$decision, "Respuesta de cliente registrada para {$quotation->reference}.", [
                'quote_id' => $quotation->id,
                'client_approval_id' => $approval->id,
            ]);

            return $approval;
        });
    }
}
