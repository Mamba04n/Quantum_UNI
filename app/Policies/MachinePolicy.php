<?php

namespace App\Policies;

use App\Models\ClientMachine;
use App\Models\User;

class MachinePolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole(User::ROLE_CLIENTE_TIENDA)) {
            return false;
        }

        return $user->hasAnyPermission(['full_access', 'customer_request.view', 'machines.view_all', 'operations.view']);
    }

    public function view(User $user, ClientMachine $machine): bool
    {
        if ($this->viewAny($user)) {
            return true;
        }

        $machine->loadMissing('entity');

        return $machine->entity && $machine->entity->user_id === $user->id;
    }
}
