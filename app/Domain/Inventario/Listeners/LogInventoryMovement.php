<?php

namespace App\Domain\Inventario\Listeners;

use App\Domain\Inventario\Events\InventoryMovementRecorded;
use App\Models\ActivityLog;

class LogInventoryMovement
{
    public function handle(InventoryMovementRecorded $event): void
    {
        $move = $event->movement->loadMissing('product');
        $type = $move->movement_type?->label() ?? (((float) $move->quantity) >= 0 ? 'Entrada' : 'Salida');

        if (class_exists(ActivityLog::class)) {
            ActivityLog::log(
                'inventario',
                'stock_movement_recorded',
                "{$type} {$move->quantity} de {$move->product?->name}",
                [
                    'stock_move_id' => $move->id,
                    'product_id' => $move->product_id,
                    'reference' => $move->reference,
                    'balance_after' => $move->balance_after,
                    'total_cost_after' => $move->total_cost_after,
                ]
            );
        }
    }
}
