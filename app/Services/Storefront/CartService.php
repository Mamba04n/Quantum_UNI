<?php

namespace App\Services\Storefront;

use App\Models\Product;
use Illuminate\Support\Collection;

class CartService
{
    private const SESSION_KEY = 'storefront.cart';

    public function add(Product $product, int $quantity = 1): void
    {
        abort_unless($product->active, 404);
        $cart = session(self::SESSION_KEY, []);
        $cart[$product->getKey()] = min(999, ($cart[$product->getKey()] ?? 0) + max(1, $quantity));
        session([self::SESSION_KEY => $cart]);
    }

    public function update(int $productId, int $quantity): void
    {
        $cart = session(self::SESSION_KEY, []);
        if (isset($cart[$productId])) {
            $cart[$productId] = min(999, max(1, $quantity));
            session([self::SESSION_KEY => $cart]);
        }
    }

    public function remove(int $productId): void
    {
        $cart = session(self::SESSION_KEY, []);
        unset($cart[$productId]);
        session([self::SESSION_KEY => $cart]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function quantities(): array
    {
        return session(self::SESSION_KEY, []);
    }

    public function items(): Collection
    {
        $quantities = $this->quantities();

        return Product::query()
            ->where('active', true)
            ->whereIn('id', array_keys($quantities))
            ->get()
            ->map(function (Product $product) use ($quantities) {
                $quantity = (int) $quantities[$product->getKey()];
                $product->cart_quantity = $quantity;
                $product->cart_line_total = $quantity * (float) $product->sales_price;

                return $product;
            });
    }

    public function count(): int
    {
        return array_sum($this->quantities());
    }

    public function subtotal(): float
    {
        return round((float) $this->items()->sum('cart_line_total'), 2);
    }
}
