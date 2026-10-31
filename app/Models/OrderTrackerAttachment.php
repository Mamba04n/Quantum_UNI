<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTrackerAttachment extends Model
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->file_type, 'image/');
    }
}
