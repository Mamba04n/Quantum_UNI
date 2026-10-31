<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderTracker extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Alias for backward compatibility
    public function getCurrentStatusAttribute()
    {
        return $this->attributes['operational_status'] ?? null;
    }

    public function setCurrentStatusAttribute($value)
    {
        $this->attributes['operational_status'] = $value;
    }

    public function getCurrentPhaseAttribute()
    {
        return $this->attributes['operational_phase'] ?? null;
    }

    public function setCurrentPhaseAttribute($value)
    {
        $this->attributes['operational_phase'] = $value;
    }

    public function getTrackingCodeAttribute()
    {
        return $this->attributes['operational_tracking_code'] ?? null;
    }

    public function setTrackingCodeAttribute($value)
    {
        $this->attributes['operational_tracking_code'] = $value;
    }

    /** @return BelongsTo<Entity, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'customer_id');
    }

    /** @return BelongsTo<Entity, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'supplier_id');
    }

    /** @return HasMany<OrderTrackerReference, $this> */
    public function references(): HasMany
    {
        return $this->hasMany(OrderTrackerReference::class, 'tracker_id');
    }

    /** @return HasMany<OrderTrackerActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(OrderTrackerActivity::class, 'tracker_id')->latest();
    }

    /** @return HasMany<OrderTrackerAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(OrderTrackerAttachment::class, 'tracker_id')->latest();
    }

    /** @return HasMany<OrderTrackerComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(OrderTrackerComment::class, 'tracker_id')->latest();
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'tracker_id')->latest();
    }

    public function orderStatus(): HasOne
    {
        return $this->hasOne(OrderStatus::class, 'order_tracker_id');
    }
}
