<?php

namespace App\Domain\Pedidos\OrderStatus\Support;

use App\Models\OrderStatusItem;
use App\Models\Product;
use App\Services\ProductSkuGenerator;
use Illuminate\Validation\ValidationException;

class OrderStatusInventoryProduct
{
    public function __construct(private readonly ProductSkuGenerator $skuGenerator)
    {
    }

    public function assertLinked(OrderStatusItem $item): void
    {
        if (! $item->product_id || ! Product::whereKey($item->product_id)->exists()) {
            throw ValidationException::withMessages(['product' => 'La partida debe tener un producto de inventario vinculado antes de avanzar.']);
        }
    }

    public function ensureForKardex(OrderStatusItem $item): void
    {
        if ($item->product_id && Product::whereKey($item->product_id)->exists()) {
            return;
        }

        $partNumber = trim((string) $item->part_number);
        if ($partNumber === '') {
            throw ValidationException::withMessages(['product' => 'La partida no tiene numero de parte para crear producto en inventario.']);
        }

        $product = Product::query()
            ->where('sku', $partNumber)
            ->orWhere('part_number', $partNumber)
            ->first();

        if (! $product) {
            $product = Product::create([
                'name' => $item->part_name ?: $partNumber,
                'description' => $item->part_name ?: $partNumber,
                'part_number' => $partNumber,
                'brand' => $item->brand,
                'type' => 'pieza',
                'piece_rotation_type' => 'baja_rotacion',
                'technical_category' => 'otro',
                'sales_price' => 0,
                'cost' => 0,
                'total_cost' => 0,
                'stock_on_hand' => 0,
                'active' => true,
            ]);

            $product->forceFill(['sku' => $this->skuGenerator->generate($product)])->save();
        }

        $item->forceFill(['product_id' => $product->id])->save();

        if ($item->sale_order_item_id) {
            $item->saleOrderItem?->forceFill(['product_id' => $product->id])->save();
        }
    }
}
