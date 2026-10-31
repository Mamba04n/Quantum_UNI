<?php

namespace App\Domain\Administracion\Approvals\Actions;

use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RequestChangesInternalApproval
{
    public function __construct(private readonly OrderTrackerService $trackerService)
    {
    }

    public function execute(ApprovalRequest $approval, User $user, string $reason): void
    {
        if (! $user->can('internal_approval.request_changes') && ! $user->can('order_approval.request_changes') && ! $user->can('approval.request_changes')) {
            throw ValidationException::withMessages(['approval' => 'No tienes permiso para solicitar cambios.']);
        }

        DB::transaction(function () use ($approval, $user, $reason) {
            $approval = ApprovalRequest::whereKey($approval->id)->lockForUpdate()->firstOrFail();

            if ($approval->status !== ApprovalRequest::STATUS_PENDING) {
                throw ValidationException::withMessages(['approval' => 'Esta solicitud ya fue procesada.']);
            }

            $quotation = QuotationRequest::whereKey($approval->source_id)->lockForUpdate()->first();

            $approvalData = [
                'status' => ApprovalRequest::STATUS_CHANGES_REQUESTED,
                'approved_by' => $user->id,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
            ];

            if (Schema::hasColumn('approval_requests', 'active_key')) {
                $approvalData['active_key'] = null;
            }

            $approval->forceFill($approvalData)->save();

            if ($quotation) {
                $quotation->forceFill([
                    'status' => QuotationRequest::STATUS_REQUIRES_ADJUSTMENT,
                    'commercial_phase' => SalesWorkflowService::PHASE_CLIENT_OFFER,
                ])->save();
            }

            if ($approval->tracker) {
                $approval->tracker->update(['operational_phase' => 'oferta', 'operational_status' => QuotationRequest::STATUS_REQUIRES_ADJUSTMENT]);
                $this->trackerService->recordActivity(
                    $approval->tracker,
                    'internal_approval.changes_requested',
                    "Cambios solicitados por {$user->name}: {$reason}",
                    'aprobacion_interna',
                    'approval_requests',
                    $approval->id,
                    OrderTrackerService::QUOTE_PENDING_APPROVAL,
                    QuotationRequest::STATUS_REQUIRES_ADJUSTMENT,
                    $user
                );
            }

            ActivityLog::log('approval', 'internal_approval.changes_requested', "Solicitud de cambios enviada por {$user->name}.", [
                'approval_id' => $approval->id,
                'quote_id' => $quotation?->id,
            ]);
        });
    }
}
