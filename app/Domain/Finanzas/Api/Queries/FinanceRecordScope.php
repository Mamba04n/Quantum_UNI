<?php

namespace App\Domain\Finanzas\Api\Queries;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class FinanceRecordScope
{
    /** @return Builder<AccountReceivable> */
    public function receivables(User $user): Builder
    {
        return $this->scope(AccountReceivable::query(), $user, 'created_by', ['cxc.view']);
    }

    /** @return Builder<AccountPayable> */
    public function payables(User $user): Builder
    {
        return $this->scope(AccountPayable::query(), $user, 'created_by', ['cxp.view']);
    }

    /** @return Builder<Invoice> */
    public function invoices(User $user): Builder
    {
        $query = Invoice::query();

        if ($user->hasPermissionTo('finance.documents.view_all')) {
            return $query;
        }

        if (! $user->hasAnyPermission(['finance.documents.view_assigned', 'finance.invoice.view', 'invoice.view'])) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('generated_by', $user->id)
                ->orWhere(fn (Builder $query) => $query->whereNull('generated_by')->where('uploaded_by', $user->id));
        });
    }

    /** @return Builder<Payment> */
    public function payments(User $user): Builder
    {
        return $this->scope(Payment::query(), $user, 'registered_by', ['payment.view']);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $legacyPermissions
     * @return Builder<TModel>
     */
    private function scope(Builder $query, User $user, string $ownerColumn, array $legacyPermissions): Builder
    {
        if ($user->hasPermissionTo('finance.documents.view_all')) {
            return $query;
        }

        if (! $user->hasAnyPermission(['finance.documents.view_assigned', ...$legacyPermissions])) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($ownerColumn, $user->id);
    }
}
