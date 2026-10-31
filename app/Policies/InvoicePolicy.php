<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFinanceDocuments;

class InvoicePolicy
{
    use AuthorizesFinanceDocuments;

    public function viewDocument(User $user, Invoice $invoice): bool
    {
        return $this->canViewFinanceDocument(
            $user,
            $invoice->generated_by ?: $invoice->uploaded_by,
            ['finance.invoice.view', 'invoice.view']
        );
    }
}
