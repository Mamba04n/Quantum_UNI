<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole(User::ROLE_CLIENTE_TIENDA)) {
            return $ticket->customer_id === $user->id;
        }

        return $user->hasAnyPermission(['full_access', 'support.ticket.create', 'support.ticket.reply', 'support.ticket.close']);
    }

    public function create(User $user): bool
    {
        return (bool) $user;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
