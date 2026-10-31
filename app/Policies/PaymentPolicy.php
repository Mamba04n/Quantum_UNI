<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFinanceDocuments;

class PaymentPolicy
{
    use AuthorizesFinanceDocuments;

    public function viewDocument(User $user, Payment $payment): bool
    {
        return $this->canViewFinanceDocument($user, $payment->registered_by, ['payment.view']);
    }
}
