<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTrackerReference extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<OrderTracker, $this> */
    public function tracker(): BelongsTo
    {
        return $this->belongsTo(OrderTracker::class, 'tracker_id');
    }
}
