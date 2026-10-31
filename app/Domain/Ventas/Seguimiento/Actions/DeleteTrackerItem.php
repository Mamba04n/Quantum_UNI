<?php

namespace App\Domain\Ventas\Seguimiento\Actions;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\SaleOrder;
use Illuminate\Validation\ValidationException;

class DeleteTrackerItem
{
    public function execute(int $id, string $type): void
    {
        if ($type === 'quote') {
            throw ValidationException::withMessages([
                'type' => 'Las cotizaciones se cancelan desde Gestor Comercial con motivo obligatorio.',
            ]);
        }

        $item = SaleOrder::with('items')->find($id);

        if (! $item) {
            return;
        }

        if ($item->status !== 'cancelado') {
            foreach ($item->items as $line) {
                if (! empty($line->part_number)) {
                    $product = Product::where('sku', $line->part_number)->first();
                    if ($product) {
                        $quantity = (float) $line->quantity;

                        if (floor($quantity) !== $quantity) {
                            throw ValidationException::withMessages([
                                'quantity' => 'El inventario solo admite unidades enteras.',
                            ]);
                        }

                        $product->stock_on_hand += (int) $quantity;
                        $product->save();
                    }
                }
            }

            $item->status = 'cancelado';
            $item->save();

            ActivityLog::log(
                'ventas',
                'cancel_order',
                "Cancelo el pedido {$item->reference} y devolvio el inventario",
                ['order_id' => $item->id]
            );
        }
    }
}
