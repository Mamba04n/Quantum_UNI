<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentBatchItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity_shipped' => 'decimal:2',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ShipmentBatch::class, 'shipment_batch_id');
    }

    /** @return BelongsTo<OrderStatusItem, $this> */
    public function orderStatusItem(): BelongsTo
    {
        return $this->belongsTo(OrderStatusItem::class);
    }
}
