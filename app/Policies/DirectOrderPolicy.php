<?php

namespace App\Policies;

use App\Models\SaleOrder;
use App\Models\User;

class DirectOrderPolicy
{
    public function view(User $user, SaleOrder $order): bool
    {
        $order->loadMissing('customer');

        return $user->can('direct_order.view')
            || ($user->can('customer_order.view_own') && $order->customer?->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('direct_order.create');
    }

    public function createFromInventory(User $user): bool
    {
        return $user->can('direct_order.create_from_inventory');
    }

    public function validateStock(User $user): bool
    {
        return $user->can('direct_order.validate_stock');
    }

    public function reserveStock(User $user, SaleOrder $order): bool
    {
        return $user->can('direct_order.reserve_stock')
            && ! in_array($order->status, ['entregado', 'cancelado'], true);
    }

    public function releaseReservedStock(User $user, SaleOrder $order): bool
    {
        return $user->can('direct_order.release_reserved_stock')
            && ! in_array($order->status, ['entregado', 'cancelado'], true);
    }

    public function generateKardexOutput(User $user, SaleOrder $order): bool
    {
        return $user->can('direct_order.generate_kardex_output')
            && $order->status !== 'cancelado';
    }

    public function reverseKardexOutput(User $user, SaleOrder $order): bool
    {
        return $user->can('direct_order.reverse_kardex_output')
            && $order->status !== 'entregado';
    }

    public function cancel(User $user, SaleOrder $order): bool
    {
        return $user->can('direct_order.cancel')
            && ! in_array($order->status, ['entregado', 'cancelado'], true);
    }

    public function complete(User $user, SaleOrder $order): bool
    {
        return $user->can('direct_order.complete')
            && ! in_array($order->status, ['entregado', 'cancelado'], true);
    }
}
