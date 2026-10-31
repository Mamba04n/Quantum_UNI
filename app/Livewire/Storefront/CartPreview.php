<?php

namespace App\Livewire\Storefront;

use App\Services\Storefront\CartQuoteSummary;
use App\Services\Storefront\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

class CartPreview extends Component
{
    #[On('storefront-cart-added')]
    public function refreshCart(CartService $cart): void
    {
        $this->dispatch('storefront-cart-count', count: $cart->count());
    }

    public function changeQuantity(int $id, int $change, CartService $cart): void
    {
        $quantities = $cart->quantities();
        if (isset($quantities[$id]) && in_array($change, [-1, 1], true)) {
            $cart->update($id, $quantities[$id] + $change);
        }
        $this->dispatch('storefront-cart-count', count: $cart->count());
    }

    public function remove(int $id, CartService $cart): void
    {
        $cart->remove($id);
        $this->dispatch('storefront-cart-count', count: $cart->count());
    }

    public function render(CartService $cart, CartQuoteSummary $summary)
    {
        $items = $cart->items();

        return view('storefront.cart.preview', ['items' => $items, 'count' => $items->sum('cart_quantity'), 'summary' => $summary->build($items)]);
    }
}
