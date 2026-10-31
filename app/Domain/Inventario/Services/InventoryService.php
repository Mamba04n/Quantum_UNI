<?php

namespace App\Domain\Inventario\Services;

use App\Domain\Inventario\Events\InventoryMovementRecorded;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMove;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryService
{
    public function applyMovement(int $productId, int $quantity, string $reference, string $state = 'hecho'): StockMove
    {
        return $this->recordMovement(
            productId: $productId,
            type: $quantity >= 0 ? StockMovementType::In : StockMovementType::Out,
            quantity: abs($quantity),
            movementDate: now(),
            costIn: $quantity >= 0 ? 0 : null,
            costOut: $quantity < 0 ? 0 : null,
            reference: $reference,
            attachmentPath: null,
            state: $state
        );
    }

    public function recordMovement(
        int $productId,
        StockMovementType|string $type,
        int $quantity,
        CarbonInterface|string|null $movementDate = null,
        ?float $costIn = null,
        ?float $costOut = null,
        ?string $reference = null,
        ?string $attachmentPath = null,
        string $state = 'hecho',
        ?int $purchaseOrderId = null,
        ?int $purchaseOrderItemId = null,
        ?int $purchaseReceiptId = null,
        ?int $purchaseReceiptItemId = null,
        ?int $saleOrderId = null,
        ?int $saleOrderItemId = null,
        ?string $sourceModule = null,
        ?int $sourceId = null
    ): StockMove {
        $type = $type instanceof StockMovementType ? $type : StockMovementType::from($type);
        $movementDate = $movementDate instanceof CarbonInterface ? $movementDate : ($movementDate ? Carbon::parse($movementDate) : now());

        return DB::transaction(function () use ($productId, $type, $quantity, $movementDate, $costIn, $costOut, $reference, $attachmentPath, $state, $purchaseOrderId, $purchaseOrderItemId, $purchaseReceiptId, $purchaseReceiptItemId, $saleOrderId, $saleOrderItemId, $sourceModule, $sourceId) {
            $product = Product::whereKey($productId)->lockForUpdate()->firstOrFail();

            $absoluteQuantity = abs($quantity);
            $signedQuantity = $type === StockMovementType::In ? $absoluteQuantity : -$absoluteQuantity;
            $currentStock = (int) $product->stock_on_hand;
            $currentTotalCost = (float) ($product->total_cost ?? ($currentStock * (float) $product->cost));
            $unitCost = (float) $product->cost;
            $newStock = $currentStock;
            $newTotalCost = $currentTotalCost;
            $newAverageCost = (float) $product->cost;

            if ($state === 'hecho') {
                if ($type === StockMovementType::Out && $currentStock < $absoluteQuantity) {
                    throw new \InvalidArgumentException('No hay existencias suficientes para registrar la salida.');
                }

                if ($type === StockMovementType::In) {
                    $unitCost = max(0, (float) ($costIn ?? $product->cost ?? 0));
                    $newStock = $currentStock + $absoluteQuantity;
                    $newTotalCost = $currentTotalCost + ($absoluteQuantity * $unitCost);
                    $newAverageCost = $newStock > 0 ? $newTotalCost / $newStock : 0;
                } else {
                    $unitCost = $currentStock > 0 ? $currentTotalCost / $currentStock : (float) ($costOut ?? $product->cost ?? 0);
                    $newStock = $currentStock - $absoluteQuantity;
                    $newTotalCost = max(0, $currentTotalCost - ($absoluteQuantity * $unitCost));
                    $newAverageCost = $newStock > 0 ? $newTotalCost / $newStock : $unitCost;
                }
            }

            $move = StockMove::create([
                'product_id' => $product->id,
                'purchase_order_id' => $purchaseOrderId,
                'purchase_order_item_id' => $purchaseOrderItemId,
                'purchase_receipt_id' => $purchaseReceiptId,
                'purchase_receipt_item_id' => $purchaseReceiptItemId,
                'sale_order_id' => $saleOrderId,
                'sale_order_item_id' => $saleOrderItemId,
                'user_id' => auth()->id(),
                'movement_type' => $type->value,
                'movement_date' => $movementDate->toDateString(),
                'quantity' => $signedQuantity,
                'cost_in' => $type === StockMovementType::In ? $unitCost : 0,
                'cost_out' => $type === StockMovementType::Out ? $unitCost : 0,
                'unit_cost' => $unitCost,
                'balance_after' => $newStock,
                'total_cost_after' => $newTotalCost,
                'reference' => $reference ?: $this->generateReference($type),
                'attachment_path' => $attachmentPath,
                'state' => $state,
                'source_module' => $sourceModule,
                'source_id' => $sourceId,
            ]);

            if ($state === 'hecho') {
                $product->forceFill([
                    'stock_on_hand' => $newStock,
                    'cost' => $newAverageCost,
                    'total_cost' => $newTotalCost,
                ])->save();
            }

            event(new InventoryMovementRecorded($move));

            return $move;
        });
    }

    public function generateReference(StockMovementType|string $type): string
    {
        $type = $type instanceof StockMovementType ? $type : StockMovementType::from($type);

        return sprintf(
            'KDX-%s-%s-%s',
            $type === StockMovementType::In ? 'IN' : 'OUT',
            now()->format('Ymd'),
            Str::upper(Str::random(6))
        );
    }
}
