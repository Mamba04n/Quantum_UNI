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

class RejectInternalApproval
{
    public function __construct(private readonly OrderTrackerService $trackerService)
    {
    }

    public function execute(ApprovalRequest $approval, User $user, string $reason): void
    {
        if (! $user->can('internal_approval.reject') && ! $user->can('order_approval.reject') && ! $user->can('approval.reject')) {
            throw ValidationException::withMessages(['approval' => 'No tienes permiso para rechazar.']);
        }

        DB::transaction(function () use ($approval, $user, $reason) {
            $approval = ApprovalRequest::whereKey($approval->id)->lockForUpdate()->firstOrFail();

            if ($approval->status !== ApprovalRequest::STATUS_PENDING) {
                throw ValidationException::withMessages(['approval' => 'Esta solicitud ya fue procesada.']);
            }

            $quotation = QuotationRequest::whereKey($approval->source_id)->lockForUpdate()->first();
            $oldStatus = $quotation?->status;

            $approvalData = [
                'status' => ApprovalRequest::STATUS_REJECTED,
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
                    'status' => QuotationRequest::STATUS_REJECTED,
                    'commercial_phase' => SalesWorkflowService::PHASE_APPROVAL,
                ])->save();
            }

            $tracker = $approval->tracker;
            if ($tracker) {
                $tracker->update(['operational_phase' => 'aprobacion', 'operational_status' => QuotationRequest::STATUS_REJECTED]);
                $this->trackerService->recordActivity(
                    $tracker,
                    'internal_approval.rejected',
                    "Aprobacion interna rechazada por {$user->name}: {$reason}",
                    'aprobacion_interna',
                    'approval_requests',
                    $approval->id,
                    $oldStatus,
                    QuotationRequest::STATUS_REJECTED,
                    $user
                );
            }

            ActivityLog::log('approval', 'internal_approval.rejected', "Aprobacion interna rechazada por {$user->name}.", [
                'approval_id' => $approval->id,
                'quote_id' => $quotation?->id,
            ]);
        });
    }
}
