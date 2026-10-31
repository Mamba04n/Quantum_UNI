<?php

namespace App\Domain\Ventas\FlujoTrabajo\Actions;

use App\Domain\Ventas\FlujoTrabajo\Services\SalesWorkflowService;
use App\Models\QuotationRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TransitionQuotationStatus
{
    public function execute(SalesWorkflowService $workflow, QuotationRequest $request, string $newStatus, User $user, bool $system = false): QuotationRequest
    {
        $request->loadMissing('items.product');
        $oldStatus = $request->status ?: QuotationRequest::STATUS_RECEIVED;

        if ($oldStatus === $newStatus) {
            return $request->fresh('items');
        }

        $allowed = $system ? $workflow->systemAllowedStatuses($oldStatus) : array_keys($workflow->manualStatusOptions($request));

        if (!in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages(['workflow' => "Cambio de estado no permitido: {$oldStatus} -> {$newStatus}."]);
        }

        $permission = $workflow->permissionForStatus($newStatus);
        if ($permission && !$user->can($permission)) {
            throw ValidationException::withMessages(['workflow' => 'No tienes permiso para aplicar este estado.']);
        }

        $workflow->validateTransitionRules($request, $newStatus);

        $request->forceFill([
            'status' => $newStatus,
            'commercial_phase' => $workflow->phaseForStatus($newStatus),
        ])->save();

        $workflow->record($request, 'status_changed', "Estado comercial actualizado a {$newStatus}.", $workflow->phaseForStatus($newStatus), $oldStatus, $newStatus, $user);

        return $request->fresh('items');
    }
}
