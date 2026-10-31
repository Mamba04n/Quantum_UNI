<?php

namespace App\Policies;

use App\Models\AccountPayable;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFinanceDocuments;

class AccountPayablePolicy
{
    use AuthorizesFinanceDocuments;

    public function viewDocument(User $user, AccountPayable $accountPayable): bool
    {
        return $this->canViewFinanceDocument($user, $accountPayable->created_by, ['cxp.view']);
    }
}
