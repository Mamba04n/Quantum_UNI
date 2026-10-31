<?php

namespace App\Domain\Inventario\Events;

use App\Models\StockMove;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryMovementRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public StockMove $movement)
    {
    }
}
