<?php

namespace App\Policies;

use App\Models\SupplierPriceQuotation;
use App\Models\User;

class SupplierPriceQuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->hasRole(User::ROLE_CLIENTE_TIENDA) && $user->can('supplier_bid.view_costs');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->hasAnyPermission(['supplier_bid.add_supplier_response', 'supplier_response.create']);
    }

    public function confirmAvailability(User $user): bool
    {
        return $this->viewAny($user) && $user->hasAnyPermission(['supplier_bid.edit_supplier_response', 'supplier_bid.add_supplier_response', 'supplier_response.create']);
    }

    public function document(User $user, SupplierPriceQuotation $quotation): bool
    {
        return $this->viewAny($user) && $user->can('supplier_bid.view_supplier_documents');
    }
}
