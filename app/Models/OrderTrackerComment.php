<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTrackerComment extends Model
{
    protected $guarded = [];

    public function tracker(): BelongsTo
    {
        return $this->belongsTo(OrderTracker::class, 'tracker_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(OrderTrackerActivity::class, 'activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
