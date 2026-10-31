<?php

namespace App\Policies;

use App\Models\User;

class ClientPolicy
{
    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['full_access', 'customer_request.create', 'customer_request.view']);
    }
}
