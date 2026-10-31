<?php

namespace App\Domain\Administracion\Approvals\Actions;

use App\Domain\Administracion\Approvals\Support\ApprovalHelper;
use App\Domain\Administracion\Approvals\Support\ApprovalValidator;
use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Services\OrderTrackerService;
use App\Services\SalesWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RequestInternalApproval
{
    public function __construct(private readonly OrderTrackerService $trackerService)
    {
    }

    public function execute(QuotationRequest $quotation, User $user, ?string $reason = null): ApprovalRequest
    {
        if (! $user->can('internal_approval.request') && ! $user->can('approval.request') && ! $user->can('supplier_bid.send_to_approval')) {
            throw ValidationException::withMessages(['approval' => 'No tienes permiso para solicitar aprobacion interna.']);
        }

        return DB::transaction(function () use ($quotation, $user, $reason) {
            $quotation = QuotationRequest::with('items')
                ->whereKey($quotation->id)
                ->lockForUpdate()
                ->firstOrFail();

            ApprovalValidator::assertCanRequestApproval($quotation);

            $hasActiveKey = Schema::hasColumn('approval_requests', 'active_key');
            $hasApprovalType = Schema::hasColumn('approval_requests', 'approval_type');
            $hasApprovalCode = Schema::hasColumn('approval_requests', 'approval_code');
            $activeKey = ApprovalHelper::activeKey($quotation);
            $existing = ApprovalRequest::query()
                ->where(function ($query) use ($activeKey, $quotation, $hasActiveKey) {
                    if ($hasActiveKey) {
                        $query->where('active_key', $activeKey)
                            ->orWhere(function ($legacy) use ($quotation) {
                                $legacy->where('source_module', 'quotation_requests')
                                    ->where('source_id', $quotation->id);
                            });

                        return;
                    }

                    $query
                        ->where(function ($legacy) use ($quotation) {
                            $legacy->where('source_module', 'quotation_requests')
                                ->where('source_id', $quotation->id);
                        });
                })
                ->whereIn('status', [ApprovalRequest::STATUS_PENDING, ApprovalRequest::STATUS_APPROVED])
                ->when($hasApprovalType, function ($query) {
                    $query->where(function ($q) {
                        $q->whereNull('approval_type')->orWhere('approval_type', ApprovalRequest::TYPE_INTERNAL_PURCHASE);
                    });
                })
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing->fresh(['tracker', 'requester']);
            }

            $tracker = $this->trackerService->trackerForQuotation($quotation);
            $oldStatus = $quotation->status;

            $quotation->forceFill([
                'status' => OrderTrackerService::QUOTE_PENDING_APPROVAL,
                'commercial_phase' => SalesWorkflowService::PHASE_APPROVAL,
            ])->save();

            if ($tracker) {
                $tracker->update([
                    'operational_phase' => 'aprobacion',
                    'operational_status' => OrderTrackerService::QUOTE_PENDING_APPROVAL,
                ]);
            }

            $approvalData = [
                'tracker_id' => $tracker?->id,
                'request_type' => ApprovalRequest::TYPE_INTERNAL_PURCHASE,
                'source_module' => 'quotation_requests',
                'source_id' => $quotation->id,
                'requested_by' => $user->id,
                'status' => ApprovalRequest::STATUS_PENDING,
                'reason' => $reason,
            ];

            if ($hasApprovalCode) {
                $approvalData['approval_code'] = ApprovalHelper::nextApprovalCode();
            }

            if ($hasApprovalType) {
                $approvalData['approval_type'] = ApprovalRequest::TYPE_INTERNAL_PURCHASE;
            }

            if ($hasActiveKey) {
                $approvalData['active_key'] = $activeKey;
            }

            $approval = ApprovalRequest::create($approvalData);

            if ($tracker) {
                $this->trackerService->recordActivity(
                    $tracker,
                    'approval.requested',
                    "Solicitud enviada a aprobacion interna por {$user->name}",
                    'aprobacion_interna',
                    'approval_requests',
                    $approval->id,
                    $oldStatus,
                    OrderTrackerService::QUOTE_PENDING_APPROVAL,
                    $user
                );

                $this->trackerService->addReference($tracker, 'internal_approval', $approval->approval_code ?: 'APV-'.$approval->id, 'approval_requests', $approval->id);
            }

            ActivityLog::log('approval', 'internal_approval.requested', "Solicitud enviada a aprobacion interna por {$user->name}.", [
                'approval_id' => $approval->id,
                'quote_id' => $quotation->id,
                'tracker_id' => $tracker?->id,
            ]);

            return $approval->fresh(['tracker', 'requester']);
        });
    }
}
