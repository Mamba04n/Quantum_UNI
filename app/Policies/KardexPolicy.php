<?php

namespace App\Policies;

use App\Enums\StockMovementType;
use App\Models\StockMove;
use App\Models\User;

class KardexPolicy
{
    public function view(User $user, StockMove $movement): bool
    {
        return $user->can('kardex.view');
    }

    public function createEntry(User $user): bool
    {
        return $user->can('kardex.create_entry');
    }

    public function createOutput(User $user): bool
    {
        return $user->can('kardex.create_output');
    }

    public function reverseMovement(User $user, StockMove $movement): bool
    {
        return $user->can('kardex.reverse_movement')
            && $movement->state === 'hecho';
    }

    public function viewDocuments(User $user, StockMove $movement): bool
    {
        return $user->can('warehouse.inventory.view')
            || $user->can('tracker.view_kardex')
            || $user->can('kardex.view_documents')
            || ($movement->movement_type === StockMovementType::Out && $user->can('tracker.view_kardex_documents'));
    }

    public function attachDocuments(User $user, StockMove $movement): bool
    {
        return $user->can('kardex.attach_documents')
            && $movement->state !== 'cancelado';
    }
}
