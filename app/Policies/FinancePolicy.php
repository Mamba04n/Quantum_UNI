<?php

namespace App\Policies;

use App\Models\User;

class FinancePolicy
{
    public function view(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function registerPayment(User $user): bool
    {
        return $user->can('finance.register_payment') || $user->can('finance.payment.register');
    }

    public function approvePayment(User $user): bool
    {
        return $user->can('finance.approve_payment') || $user->can('finance.payment.approve');
    }

    public function viewInvoices(User $user): bool
    {
        return $user->can('finance.invoice.view');
    }
}
