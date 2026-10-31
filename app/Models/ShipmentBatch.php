<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentBatch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'departure_date' => 'date',
        'estimated_arrival_date' => 'date',
        'actual_arrival_date' => 'date',
    ];

    public function orderStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }

    /** @return HasMany<ShipmentBatchItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentBatchItem::class);
    }
}
