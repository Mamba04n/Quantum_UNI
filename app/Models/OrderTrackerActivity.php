<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderTrackerActivity extends Model
{
    protected $guarded = [];

    public function tracker(): BelongsTo
    {
        return $this->belongsTo(OrderTracker::class, 'tracker_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(OrderTrackerAttachment::class, 'activity_id')->latest();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(OrderTrackerComment::class, 'activity_id')->latest();
    }
}
