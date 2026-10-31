<?php

namespace App\Livewire\Administracion\Concerns;

use App\Services\ApprovalService;

trait ManagesApprovalActions
{
    public function approveSelected(ApprovalService $approvalService): void
    {
        abort_unless($this->canAny(['internal_approval.approve', 'approval.approve', 'order_approval.approve']), 403);

        $approval = $this->selectedApproval();

        try {
            $order = $approvalService->approveRequest($approval, auth()->user());
            session()->flash('message', "Aprobación registrada. Orden {$order->reference} generada.");
            $this->selectedApprovalId = null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Approval error: ' . $e->getMessage(), ['exception' => $e]);
            report($e);
            session()->flash('error', \App\Support\UserMessage::error($e, 'No se pudo completar la acción. Revisa los datos e intenta de nuevo.'));
        }
    }

    public function openReject(): void
    {
        abort_unless($this->canAny(['internal_approval.reject', 'approval.reject', 'order_approval.reject']), 403);

        $this->selectedApproval();
        $this->rejectionReason = '';
        $this->decisionMode = 'reject';
        $this->showDecisionModal = true;
    }

    public function openChanges(): void
    {
        abort_unless($this->canAny(['internal_approval.request_changes', 'approval.request_changes', 'order_approval.request_changes']), 403);

        $this->selectedApproval();
        $this->rejectionReason = '';
        $this->decisionMode = 'changes';
        $this->showDecisionModal = true;
    }

    public function closeDecision(): void
    {
        $this->rejectionReason = '';
        $this->decisionMode = 'reject';
        $this->showDecisionModal = false;
    }

    public function submitDecision(ApprovalService $approvalService): void
    {
        $requiredPermission = $this->decisionMode === 'changes'
            ? ['internal_approval.request_changes', 'approval.request_changes', 'order_approval.request_changes']
            : ['internal_approval.reject', 'approval.reject', 'order_approval.reject'];

        abort_unless($this->canAny($requiredPermission), 403);

        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $approval = $this->selectedApproval();

        try {
            if ($this->decisionMode === 'changes') {
                $approvalService->requestChanges($approval, auth()->user(), $this->rejectionReason);
                $message = 'Solicitud de cambios enviada.';
            } else {
                $approvalService->rejectRequest($approval, auth()->user(), $this->rejectionReason);
                $message = 'Orden de compra cancelada. Motivo registrado.';
            }

            $this->closeDecision();
            session()->flash('message', $message);
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', \App\Support\UserMessage::error($e, 'No se pudo completar la acción. Revisa los datos e intenta de nuevo.'));
        }
    }
}
