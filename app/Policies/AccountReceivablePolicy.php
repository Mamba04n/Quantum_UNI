<?php

namespace App\Policies;

use App\Models\AccountReceivable;
use App\Models\User;
use App\Policies\Concerns\AuthorizesFinanceDocuments;

class AccountReceivablePolicy
{
    use AuthorizesFinanceDocuments;

    public function viewDocument(User $user, AccountReceivable $accountReceivable): bool
    {
        return $this->canViewFinanceDocument($user, $accountReceivable->created_by, ['cxc.view']);
    }
}
