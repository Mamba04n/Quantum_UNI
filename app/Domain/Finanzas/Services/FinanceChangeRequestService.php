<?php

namespace App\Domain\Finanzas\Services;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\ActivityLog;
use App\Models\FinanceChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceChangeRequestService
{
    public function __construct(
        protected FinanceService $financeService
    ) {}

    public function createRequest(
        string $type,
        int $accountId,
        array $requestedData,
        array $changesDiff,
        string $justification,
        ?string $supportPath = null,
        ?User $user = null
    ): FinanceChangeRequest {
        $user = $user ?? auth()->user();
        if (! $user) {
            throw ValidationException::withMessages(['user' => 'Usuario no autenticado.']);
        }

        if (blank($justification)) {
            throw ValidationException::withMessages([
                'change_justification' => 'El motivo o justificación de la modificación es obligatorio.',
            ]);
        }

        return DB::transaction(function () use ($type, $accountId, $requestedData, $changesDiff, $justification, $supportPath, $user): FinanceChangeRequest {
            if ($type === FinanceChangeRequest::TYPE_CXP) {
                $account = AccountPayable::query()->findOrFail($accountId);
                $originalData = $account->only([
                    'entity_id', 'supplier_id', 'invoice_number', 'concept', 'classification',
                    'condition', 'invoice_date', 'total_amount', 'amount_usd', 'due_date',
                    'support_document_path', 'status', 'lock_version',
                ]);
                $accountPayableId = $account->id;
                $accountReceivableId = null;
                $code = $account->payable_code;
            } else {
                $account = AccountReceivable::query()->findOrFail($accountId);
                $originalData = $account->only([
                    'entity_id', 'customer_id', 'invoice_number', 'concept', 'classification',
                    'condition', 'invoice_date', 'total_amount', 'due_date',
                    'support_document_path', 'status', 'lock_version',
                ]);
                $accountPayableId = null;
                $accountReceivableId = $account->id;
                $code = $account->receivable_code;
            }

            if ($supportPath) {
                $requestedData['support_document_path'] = $supportPath;
            }

            $changeRequest = FinanceChangeRequest::create([
                'type' => $type,
                'account_payable_id' => $accountPayableId,
                'account_receivable_id' => $accountReceivableId,
                'requested_by' => $user->id,
                'status' => FinanceChangeRequest::STATUS_PENDING,
                'original_data' => $originalData,
                'requested_data' => $requestedData,
                'changes_diff' => $changesDiff,
                'justification' => trim($justification),
                'support_document_path' => $supportPath,
            ]);

            ActivityLog::log(
                'finanzas',
                'finance_change_request.created',
                "Solicitud de modificación para {$code} enviada al Administrador. Motivo: {$justification}",
                [
                    'finance_change_request_id' => $changeRequest->id,
                    'account_code' => $code,
                    'type' => $type,
                    'justification' => $justification,
                    'requested_by' => $user->id,
                ]
            );

            return $changeRequest;
        });
    }

    public function approve(FinanceChangeRequest $request, User $approver, ?string $adminNotes = null): void
    {
        DB::transaction(function () use ($request, $approver, $adminNotes): void {
            if ($request->status !== FinanceChangeRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Esta solicitud ya fue procesada anteriormente.',
                ]);
            }

            $justificationNote = "Autorizado por {$approver->name}. Motivo original: {$request->justification}";
            if (filled($adminNotes)) {
                $justificationNote .= " | Nota Admin: {$adminNotes}";
            }

            if ($request->type === FinanceChangeRequest::TYPE_CXP) {
                $payable = AccountPayable::query()->findOrFail($request->account_payable_id);
                $this->financeService->updatePayable(
                    $payable,
                    $request->requested_data,
                    null,
                    $justificationNote
                );
            } else {
                $receivable = AccountReceivable::query()->findOrFail($request->account_receivable_id);
                $this->financeService->updateReceivable(
                    $receivable,
                    $request->requested_data,
                    null,
                    $justificationNote
                );
            }

            $request->forceFill([
                'status' => FinanceChangeRequest::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'admin_notes' => $adminNotes ?: null,
                'reviewed_at' => now(),
            ])->save();

            ActivityLog::log(
                'finanzas',
                'finance_change_request.approved',
                "Solicitud de modificación para {$request->accountCode()} autorizada por {$approver->name}.",
                [
                    'finance_change_request_id' => $request->id,
                    'approved_by' => $approver->id,
                    'admin_notes' => $adminNotes,
                ]
            );
        });
    }

    public function reject(FinanceChangeRequest $request, User $approver, string $adminNotes): void
    {
        DB::transaction(function () use ($request, $approver, $adminNotes): void {
            if ($request->status !== FinanceChangeRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Esta solicitud ya fue procesada anteriormente.',
                ]);
            }

            $request->forceFill([
                'status' => FinanceChangeRequest::STATUS_REJECTED,
                'approved_by' => $approver->id,
                'admin_notes' => trim($adminNotes),
                'reviewed_at' => now(),
            ])->save();

            ActivityLog::log(
                'finanzas',
                'finance_change_request.rejected',
                "Solicitud de modificación para {$request->accountCode()} rechazada por {$approver->name}. Motivo: {$adminNotes}",
                [
                    'finance_change_request_id' => $request->id,
                    'rejected_by' => $approver->id,
                    'admin_notes' => $adminNotes,
                ]
            );
        });
    }
}
