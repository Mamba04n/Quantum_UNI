<?php

namespace App\Http\Controllers\Api\V1\Integration;

use App\Http\Controllers\Controller;
use App\Models\InventoryReservation;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class StockController extends Controller
{
    /**
     * Consulta la existencia física, reservas activas y disponibilidad real de un ítem por bodega.
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::query()->find($id);
        abort_unless($product, 404, 'El ítem solicitado no existe en el catálogo ERP.');

        $onHand = (float) $product->stock_on_hand;

        // Reservas activas en estado 'reserved' (descontando lo ya consumido)
        $reserved = (float) InventoryReservation::query()
            ->where('product_id', $product->id)
            ->where('status', 'reserved')
            ->selectRaw('COALESCE(SUM(quantity - consumed_quantity), 0) as total_reserved')
            ->value('total_reserved');

        $available = max(0.0, $onHand - $reserved);

        // Estado del cálculo de stock
        $status = 'OK';
        if ($onHand < 0 || ! is_numeric($product->stock_on_hand)) {
            $status = 'UNKNOWN';
        }

        // Estructura extensible para múltiples bodegas
        $warehouses = [
            [
                'warehouse_code' => 'BODEGA-CENTRAL',
                'warehouse_name' => 'Bodega Central Principal',
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'available' => $available,
                'unit' => 'UND',
            ],
        ];

        return response()->json([
            'data' => [
                'item' => [
                    'id' => (string) $product->id,
                    'sku' => (string) ($product->sku ?: $product->part_number ?: $product->id),
                    'part_number' => (string) ($product->part_number ?? ''),
                    'name' => (string) $product->name,
                ],
                'status' => $status,
                'total_on_hand' => $onHand,
                'total_reserved' => $reserved,
                'total_available' => $available,
                'warehouses' => $warehouses,
                'updated_at' => now()->toIso8601ZuluString(),
            ],
        ]);
    }
}
